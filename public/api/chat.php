<?php
/**
 * Card-o-Bot chat endpoint (Chat v2).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/openai.php';
require_once __DIR__ . '/../includes/cardy.php';
require_once __DIR__ . '/../includes/state.php';
require_once __DIR__ . '/../includes/ml_client.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

ensure_user_row();
$user = get_logged_in_user();
$username = $user['username'] ?? '';
$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
    $userId = (int)(ensure_user_row() ?: 0);
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = [];
}

$sessionId   = isset($data['session_id']) && is_string($data['session_id']) ? trim($data['session_id']) : '';
$userMessage = isset($data['user_message']) && is_string($data['user_message']) ? trim($data['user_message']) : '';
$action      = isset($data['action']) && is_string($data['action']) ? $data['action'] : '';
$value       = isset($data['value']) && is_string($data['value']) ? $data['value'] : '';
$conceptPatch = isset($data['concept_patch']) && is_array($data['concept_patch']) ? $data['concept_patch'] : null;

if (mb_strlen($userMessage) > 1000) {
    $userMessage = mb_substr($userMessage, 0, 1000);
}

$session = $sessionId !== '' ? cardy_session_get($sessionId) : cardy_session_create();
$skipModel = false;
$staticMessage = null;
$staticSuggestions = [];

switch ($action) {
    case 'reset':
        $session = cardy_session_reset($session);
        break;

    case 'select_path':
        if ($value === 'chat') {
            $session['mode'] = CARDY_MODE_FREECHAT;
            $session['step'] = CARDY_STEP_FREE_CHAT;
        } else {
            $session['mode'] = CARDY_MODE_CARD;
            $session['step'] = CARDY_STEP_Q_SUBJECT;
        }
        break;

    case 'advance':
        $session = cardy_session_advance($session, $userMessage);
        $userMessage = '';
        break;

    case 'confirm':
        // User affirmed concept → ready for paint
        if (is_array($conceptPatch)) {
            $session['visual_concept'] = cardy_merge_concept($session['visual_concept'], $conceptPatch);
        }
        if ($userMessage !== '') {
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
        }
        $session['step'] = CARDY_STEP_READY;
        $session['mode'] = CARDY_MODE_CARD;
        break;

    case 'update_concept':
        if (is_array($conceptPatch)) {
            $session['visual_concept'] = cardy_merge_concept($session['visual_concept'], $conceptPatch);
        }
        $session['step'] = CARDY_STEP_CONFIRM;
        $skipModel = true;
        $staticMessage = 'Got it. I tweaked the concept. Ready when you are.';
        $staticSuggestions = ['Paint it!', 'Change more'];
        break;

    case 'revise':
        if (cardy_revise_remaining($session) <= 0) {
            $skipModel = true;
            $staticMessage = 'I can only tweak this one a couple more times. Want to save it, draw on it, or start a new card?';
            $staticSuggestions = ['Save it', 'Draw on it', 'Start over'];
            break;
        }
        $session['revise_count'] = (int)($session['revise_count'] ?? 0) + 1;
        if ($userMessage !== '') {
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            $session['visual_concept']['revision_notes'] = $userMessage;
        }
        $session['step'] = CARDY_STEP_REVISE;
        $session['image_url'] = null;
        $session['image_b64'] = null;
        $session['image_task_id'] = null;
        $userMessage = '';
        break;

    case 'enter_studio':
        $session['step'] = CARDY_STEP_STUDIO;
        $skipModel = true;
        $staticMessage = 'The drawing deck is open. Add layers if you want, or just save when it feels right.';
        $staticSuggestions = [];
        break;

    default:
        if ($userMessage !== '' && $session['mode'] === CARDY_MODE_FREECHAT) {
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
        } elseif ($userMessage !== '' && $session['step'] === CARDY_STEP_GREETING) {
            // First user reply after greeting often chooses path
            $low = strtolower($userMessage);
            if (str_contains($low, 'chat') || str_contains($low, 'tell me more')) {
                $session['mode'] = CARDY_MODE_FREECHAT;
                $session['step'] = CARDY_STEP_FREE_CHAT;
                $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            } else {
                $session['mode'] = CARDY_MODE_CARD;
                $session = cardy_session_advance($session, $userMessage);
                $userMessage = '';
            }
        } elseif ($userMessage !== '' && in_array($session['step'], [
            CARDY_STEP_Q_SUBJECT, CARDY_STEP_Q_VIBE, CARDY_STEP_Q_LOOKS,
            CARDY_STEP_Q_WORLD, CARDY_STEP_Q_SPARK, CARDY_STEP_CHOOSE_INTENT,
            'q1_subject', 'q2_vibe', 'q3_details',
        ], true)) {
            $session = cardy_session_advance($session, $userMessage);
            $userMessage = '';
        } elseif ($userMessage !== '' && $session['step'] === CARDY_STEP_CONFIRM) {
            $low = strtolower($userMessage);
            if (str_contains($low, 'paint') || str_contains($low, 'yes') || str_contains($low, 'ready') || str_contains($low, 'go')) {
                $session['history'][] = ['role' => 'user', 'content' => $userMessage];
                $session['step'] = CARDY_STEP_READY;
                $userMessage = '';
            } else {
                $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            }
        }
        break;
}

$memoryHints = [];
$conceptText = trim(($session['visual_concept']['subject'] ?? '') . ' ' . ($session['visual_concept']['details'] ?? ''));
if ($conceptText !== '' && $userId > 0) {
    $memoryHints = ml_similar_hints($userId, $conceptText, 3);
}

if ($skipModel) {
    if ($staticMessage !== null) {
        $session = cardy_session_record_reply($session, $staticMessage, null);
    }
    cardy_session_save($session);
    echo json_encode([
        'ok' => true,
        'session_id' => $session['id'],
        'step' => $session['step'],
        'mode' => $session['mode'],
        'message' => $staticMessage ?? '',
        'suggestions' => $staticSuggestions,
        'visual_concept' => $session['visual_concept'],
        'ready_to_render' => $session['step'] === CARDY_STEP_READY,
        'revise_remaining' => cardy_revise_remaining($session),
        'memory_hints' => $memoryHints,
        'image_url' => $session['image_url'] ?? null,
        'tokens' => null,
    ]);
    exit;
}

$inMode = $session['mode'] === CARDY_MODE_FREECHAT ? 'chat' : 'card';
$schema = $inMode === 'chat' ? cardy_chat_schema() : cardy_card_schema();
$schemaName = $inMode === 'chat' ? 'cardy_chat_turn' : 'cardy_card_turn';

$input = cardy_build_input(
    $session['step'],
    $session,
    $userMessage,
    $session['history'],
    $username,
    $memoryHints
);

$result = openai_chat_responses($input, [
    'schema' => $schema,
    'schema_name' => $schemaName,
    'max_output_tokens' => 4000,
    'timeout' => 90,
]);

if (!$result['ok'] || !is_array($result['parsed'])) {
    error_log('chat.php: openai_chat_responses failed: ' . ($result['error'] ?? 'unknown'));
    cardy_session_save($session);
    http_response_code(502);
    echo json_encode([
        'ok' => false,
        'session_id' => $session['id'],
        'step' => $session['step'],
        'mode' => $session['mode'],
        'error' => $result['error'] ?? 'Cardy is having a glitch. Try again? *beep*',
        'message' => 'Oops, I had a little glitch there. Could you try again? *beep boop*',
    ]);
    exit;
}

$parsed = $result['parsed'];
$replyMessage = isset($parsed['message']) && is_string($parsed['message']) ? trim($parsed['message']) : '';
$replySuggestions = isset($parsed['suggestions']) && is_array($parsed['suggestions']) ? array_values($parsed['suggestions']) : [];
$readyHint = !empty($parsed['ready_to_render']);
$visualConcept = isset($parsed['visual_concept']) && is_array($parsed['visual_concept']) ? $parsed['visual_concept'] : null;

$replyMessage = str_replace(["\xE2\x80\x94", "\xE2\x80\x93"], [',', ','], $replyMessage);

$forbidden = ['type your own', 'enter your own', 'custom response', 'write your own'];
$replySuggestions = array_values(array_filter($replySuggestions, function ($s) use ($forbidden) {
    if (!is_string($s) || trim($s) === '') {
        return false;
    }
    $low = strtolower($s);
    foreach ($forbidden as $bad) {
        if (strpos($low, $bad) !== false) {
            return false;
        }
    }
    return true;
}));
if (count($replySuggestions) > 3) {
    $replySuggestions = array_slice($replySuggestions, 0, 3);
}

// After revise model turn, advance into rendering so client can re-paint
if ($session['step'] === CARDY_STEP_REVISE && is_array($visualConcept)) {
    $session = cardy_session_record_reply($session, $replyMessage, $visualConcept);
    $session['step'] = CARDY_STEP_READY;
    cardy_session_save($session);
    echo json_encode([
        'ok' => true,
        'session_id' => $session['id'],
        'step' => $session['step'],
        'mode' => $session['mode'],
        'message' => $replyMessage,
        'suggestions' => [],
        'visual_concept' => $session['visual_concept'],
        'ready_to_render' => true,
        'auto_render' => true,
        'revise_remaining' => cardy_revise_remaining($session),
        'memory_hints' => $memoryHints,
        'tokens' => $result['usage'],
    ]);
    exit;
}

$session = cardy_session_record_reply($session, $replyMessage, $visualConcept);
cardy_session_save($session);

echo json_encode([
    'ok' => true,
    'session_id' => $session['id'],
    'step' => $session['step'],
    'mode' => $session['mode'],
    'message' => $replyMessage,
    'suggestions' => $replySuggestions,
    'visual_concept' => $session['visual_concept'],
    'ready_to_render' => ($readyHint && $session['step'] === CARDY_STEP_READY) || $session['step'] === CARDY_STEP_READY,
    'revise_remaining' => cardy_revise_remaining($session),
    'memory_hints' => $memoryHints,
    'image_url' => $session['image_url'] ?? null,
    'tokens' => $result['usage'],
]);
