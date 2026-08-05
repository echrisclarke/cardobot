<?php
/**
 * Card-o-Bot chat endpoint (agenda + lore tiers).
 */

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/openai.php';
require_once __DIR__ . '/../includes/cardy.php';
require_once __DIR__ . '/../includes/state.php';
require_once __DIR__ . '/../includes/ml_client.php';
require_once __DIR__ . '/../includes/stats.php';

api_boot(false);
api_assert_same_origin();
$user = api_require_login();
ensure_user_row();
$username = $user['username'] ?? '';
$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
    $userId = (int)(ensure_user_row() ?: 0);
}

$data = api_require_post_json();

$sessionId   = isset($data['session_id']) && is_string($data['session_id']) ? trim($data['session_id']) : '';
$userMessage = isset($data['user_message']) && is_string($data['user_message']) ? trim($data['user_message']) : '';
$action      = isset($data['action']) && is_string($data['action']) ? $data['action'] : '';
$value       = isset($data['value']) && is_string($data['value']) ? $data['value'] : '';
$conceptPatch = isset($data['concept_patch']) && is_array($data['concept_patch']) ? $data['concept_patch'] : null;

if (mb_strlen($userMessage) > 1000) {
    $userMessage = mb_substr($userMessage, 0, 1000);
}

if ($sessionId !== '') {
    $session = cardy_session_find($sessionId);
    if ($session === null) {
        api_error('unknown_session', 'Unknown session_id', 404);
    }
} else {
    $session = cardy_session_create();
}

$skipModel = false;
$staticMessage = null;
$staticSuggestions = [];
$autoRender = false;

$greetingMessage = "Oh. A visitor. I'm Cardy. I run Card-o-Bot aboard this ship: we invent someone together, then I print them onto a trading card. Want to make one with me?";
$greetingSuggestions = ['Yeah, make a card', 'Make a detailed one', 'Fill out a form', 'Just chat for now'];

switch ($action) {
    case 'reset':
        $session = cardy_session_reset($session);
        break;

    case 'select_path':
        $sel = cardy_select_path_value($value !== '' ? $value : 'fast');
        $session['path'] = $sel['path'];
        $session['mode'] = $sel['mode'];
        $session['step'] = $sel['step'];
        if ($sel['path'] === CARDY_PATH_FORM) {
            // Form path: skip agenda chat; panel collects the card fields.
            if ($userMessage !== '') {
                $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            }
            $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
            $session['step'] = CARDY_STEP_CONFIRM;
            $skipModel = true;
            $staticMessage = 'Sure. Skip the Q and A. Fill this in, then I will print from what you wrote. *beep*';
            $staticSuggestions = [];
            $userMessage = '';
            break;
        }
        if ($userMessage !== '') {
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            // Path chips / menu lines are never character answers
            if ($sel['path'] !== CARDY_PATH_CHAT && !cardy_is_path_intent_message($userMessage)) {
                $path = $sel['path'];
                $missing = cardy_missing_slots($session['visual_concept'], $path);
                $focus = $missing[0] ?? 'identity';
                $session['visual_concept'] = cardy_absorb_user_text($session['visual_concept'], $userMessage, $focus);
                $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
                if (cardy_slots_complete($session['visual_concept'], $path)) {
                    $session['visual_concept'] = cardy_ensure_power_ability($session['visual_concept']);
                    $session['step'] = CARDY_STEP_CONFIRM;
                    $skipModel = true;
                    $staticMessage = 'Got it. Take a look, then we can paint your card. *whirr*';
                    $staticSuggestions = [];
                    $userMessage = '';
                }
            } else {
                $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
            }
            // Already in history; model reads history
            if (!$skipModel) {
                $userMessage = '';
            }
        }
        break;

    case 'advance':
        // Legacy: treat as agenda user message
        if ($userMessage !== '') {
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            $path = cardy_session_path($session);
            if ($path === CARDY_PATH_CHAT) {
                $session['mode'] = CARDY_MODE_CARD;
                $session['path'] = CARDY_PATH_FAST;
                $path = CARDY_PATH_FAST;
                $session['step'] = CARDY_STEP_AGENDA;
            }
            $missing = cardy_missing_slots($session['visual_concept'], $path);
            $focus = $missing[0] ?? 'identity';
            $session['visual_concept'] = cardy_absorb_user_text($session['visual_concept'], $userMessage, $focus);
            $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
            $session['step'] = CARDY_STEP_AGENDA;
            if (cardy_slots_complete($session['visual_concept'], $path)) {
                $session['visual_concept'] = cardy_ensure_power_ability($session['visual_concept']);
                $session['step'] = CARDY_STEP_CONFIRM;
                $skipModel = true;
                $staticMessage = 'Got it. Take a look, then we can paint your card. *whirr*';
                $staticSuggestions = [];
                $userMessage = '';
            }
        }
        break;

    case 'confirm':
        if (is_array($conceptPatch)) {
            $session['visual_concept'] = cardy_merge_concept($session['visual_concept'], $conceptPatch);
        }
        if ($userMessage !== '') {
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
        }
        // Paint needs a subject; form path may only have nickname.
        $nick = trim((string)($session['visual_concept']['nickname'] ?? ''));
        $subj = trim((string)($session['visual_concept']['subject'] ?? ''));
        if ($subj === '' && $nick !== '') {
            $session['visual_concept']['subject'] = $nick;
        }
        // Cardy invents a fresh printers-running line; then frontend starts paint.
        $session['visual_concept'] = cardy_ensure_power_ability($session['visual_concept']);
        $session['step'] = CARDY_STEP_RENDERING;
        $session['mode'] = CARDY_MODE_CARD;
        if (empty($session['path']) || $session['path'] === CARDY_PATH_CHAT) {
            $session['path'] = CARDY_PATH_FAST;
        }
        $session['stats'] = cardobot_generate_stats($session['visual_concept']);
        $userMessage = '';
        $autoRender = true;
        break;

    case 'update_concept':
        if (is_array($conceptPatch)) {
            $session['visual_concept'] = cardy_merge_concept($session['visual_concept'], $conceptPatch);
        }
        $session['visual_concept'] = cardy_ensure_power_ability($session['visual_concept']);
        $session['step'] = CARDY_STEP_CONFIRM;
        $skipModel = true;
        $staticMessage = 'Got it. Ready when you are.';
        $staticSuggestions = [];
        break;

    case 'revise':
        if (cardy_revise_remaining($session) <= 0) {
            $skipModel = true;
            $staticMessage = 'I can only tweak this one a couple more times. Want to save it, draw on it, or start a new card?';
            $staticSuggestions = ['View card', 'Draw on it', 'Start over'];
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
        $staticMessage = 'Ink deck online. Draw on the art, then save when it feels right.';
        $staticSuggestions = [];
        break;

    case 'greeting':
        $skipModel = true;
        $staticMessage = $greetingMessage;
        $staticSuggestions = $greetingSuggestions;
        $session['step'] = CARDY_STEP_GREETING;
        break;

    default:
        if ($userMessage !== '' && ($session['mode'] === CARDY_MODE_FREECHAT || cardy_session_path($session) === CARDY_PATH_CHAT)) {
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            $low = strtolower($userMessage);
            if (str_contains($low, 'print a card') || str_contains($low, "let's print")
                || str_contains($low, 'make a card') || str_contains($low, 'make a detailed')
                || str_contains($low, 'remember someone') || str_contains($low, 'yeah, make')) {
                $sel = cardy_select_path_value(
                    (str_contains($low, 'remember') || str_contains($low, 'detailed')) ? 'long' : 'fast'
                );
                $session['path'] = $sel['path'];
                $session['mode'] = $sel['mode'];
                $session['step'] = $sel['step'];
            }
        } elseif ($userMessage !== '' && $session['step'] === CARDY_STEP_GREETING) {
            $low = strtolower($userMessage);
            if (str_contains($low, 'talk') || str_contains($low, 'chat') || str_contains($low, 'tell me more')) {
                $sel = cardy_select_path_value('chat');
            } elseif (str_contains($low, 'remember')) {
                $sel = cardy_select_path_value('long');
            } else {
                $sel = cardy_select_path_value('fast');
            }
            $session['path'] = $sel['path'];
            $session['mode'] = $sel['mode'];
            $session['step'] = $sel['step'];
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            if ($sel['path'] !== CARDY_PATH_CHAT && !cardy_is_path_intent_message($userMessage) && mb_strlen($userMessage) > 12) {
                $session['visual_concept'] = cardy_absorb_user_text($session['visual_concept'], $userMessage, 'identity');
                $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
                if (cardy_slots_complete($session['visual_concept'], $sel['path'])) {
                    $session['visual_concept'] = cardy_ensure_power_ability($session['visual_concept']);
                    $session['step'] = CARDY_STEP_CONFIRM;
                    $skipModel = true;
                    $staticMessage = 'Got it. Take a look, then we can paint your card. *whirr*';
                    $staticSuggestions = [];
                    $userMessage = '';
                }
            } else {
                $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
            }
        } elseif ($userMessage !== '' && $session['step'] === CARDY_STEP_CONFIRM) {
            // Tweaks only; never auto-paint from free text
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            if (cardy_looks_like_nickname($userMessage)) {
                $session['visual_concept']['nickname'] = trim($userMessage);
                $staticMessage = 'Name locked in. Tweak anything else, then paint.';
            } else {
                $session['visual_concept'] = cardy_absorb_user_text($session['visual_concept'], $userMessage, 'look');
                $staticMessage = 'Updated. Check the plate when you are ready.';
            }
            $skipModel = true;
            $staticSuggestions = [];
        } elseif ($userMessage !== '' && in_array($session['step'], [
            CARDY_STEP_AGENDA, CARDY_STEP_Q_WHO, CARDY_STEP_Q_FLAVOR, CARDY_STEP_CHOOSE_INTENT,
            CARDY_STEP_Q_SUBJECT, CARDY_STEP_Q_VIBE, CARDY_STEP_Q_LOOKS,
            CARDY_STEP_Q_WORLD, CARDY_STEP_Q_SPARK,
        ], true)) {
            $session['history'][] = ['role' => 'user', 'content' => $userMessage];
            $path = cardy_session_path($session);
            if ($path === CARDY_PATH_CHAT) {
                $session['path'] = CARDY_PATH_FAST;
                $session['mode'] = CARDY_MODE_CARD;
                $path = CARDY_PATH_FAST;
            }
            $missing = cardy_missing_slots($session['visual_concept'], $path);
            $focus = $missing[0] ?? 'identity';
            $session['visual_concept'] = cardy_absorb_user_text($session['visual_concept'], $userMessage, $focus);
            $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
            $session['step'] = CARDY_STEP_AGENDA;
            if (cardy_slots_complete($session['visual_concept'], $path)) {
                $session['visual_concept'] = cardy_ensure_power_ability($session['visual_concept']);
                $session['step'] = CARDY_STEP_CONFIRM;
                $skipModel = true;
                $staticMessage = 'Got it. Take a look, then we can paint your card. *whirr*';
                $staticSuggestions = [];
                $userMessage = '';
            }
        }
        break;
}

if (!$skipModel && $session['step'] === CARDY_STEP_GREETING && $userMessage === '' && $action === '') {
    $skipModel = true;
    $staticMessage = $greetingMessage;
    $staticSuggestions = $greetingSuggestions;
}

$memoryHints = [];
$conceptText = trim(($session['visual_concept']['subject'] ?? '') . ' ' . ($session['visual_concept']['details'] ?? ''));
if ($conceptText !== '' && $userId > 0) {
    $memoryHints = ml_similar_hints($userId, $conceptText, 3);
}

$path = cardy_session_path($session);
$injectLore = cardy_should_inject_lore($path, $userMessage, $session['mode'] ?? null);
$loreBlock = '';
if ($injectLore) {
    $loreBlock = "\n\n" . cardy_lore_packet();
    if (file_exists(__DIR__ . '/../includes/story.php')) {
        require_once __DIR__ . '/../includes/story.php';
        if (function_exists('get_master_story')) {
            $master = get_master_story();
            $summary = trim((string)($master['summary'] ?? ''));
            if ($summary !== '') {
                $loreBlock .= "\nLiving memory summary (short; do not dump): " . mb_substr($summary, 0, 400);
            }
            if (function_exists('get_recent_chapters')) {
                $chapters = get_recent_chapters(2);
                if (!empty($chapters)) {
                    $bits = [];
                    foreach ($chapters as $ch) {
                        $t = trim((string)($ch['chapter_title'] ?? ''));
                        $x = trim((string)($ch['chapter_text'] ?? ''));
                        if ($t !== '' || $x !== '') {
                            $bits[] = ($t !== '' ? $t . ': ' : '') . mb_substr($x, 0, 160);
                        }
                    }
                    if ($bits !== []) {
                        $loreBlock .= "\nRecent fragments: " . implode(' | ', $bits);
                    }
                }
            }
        }
    }
}

if ($skipModel) {
    if ($staticMessage !== null) {
        $session['history'][] = ['role' => 'assistant', 'content' => $staticMessage];
        if (count($session['history']) > 16) {
            $session['history'] = array_slice($session['history'], -16);
        }
    }
    if ($session['step'] === CARDY_STEP_CONFIRM && empty($session['stats'])) {
        $session['stats'] = cardobot_generate_stats($session['visual_concept']);
    }
    cardy_session_save($session);
    $nickSuggestions = ($session['step'] === CARDY_STEP_CONFIRM)
        ? cardy_nickname_suggestions($session['visual_concept'] ?? [])
        : [];
    api_json([
        'ok' => true,
        'session_id' => $session['id'],
        'step' => $session['step'],
        'mode' => $session['mode'],
        'path' => $session['path'] ?? null,
        'message' => $staticMessage ?? '',
        'suggestions' => $staticSuggestions,
        'nickname_suggestions' => $nickSuggestions,
        'visual_concept' => $session['visual_concept'],
        'stats' => $session['stats'] ?? null,
        'ready_to_render' => false,
        'auto_render' => $autoRender,
        'revise_remaining' => cardy_revise_remaining($session),
        'memory_hints' => $memoryHints,
        'image_url' => $session['image_url'] ?? null,
        'tokens' => null,
    ]);
}

$inMode = ($session['mode'] === CARDY_MODE_FREECHAT || $path === CARDY_PATH_CHAT) ? 'chat' : 'card';
$schema = $inMode === 'chat' ? cardy_chat_schema() : cardy_card_schema();
$schemaName = $inMode === 'chat' ? 'cardy_chat_turn' : 'cardy_card_turn';

// For select_path opening with empty message, model opens the first ask
$modelMessage = $userMessage;
if ($action === 'select_path' && $modelMessage === '' && $inMode === 'card') {
    $modelMessage = '';
}

$input = cardy_build_input(
    $session['step'],
    $session,
    $modelMessage,
    $session['history'],
    $username,
    $memoryHints,
    $loreBlock
);

$result = openai_chat_responses($input, [
    'schema' => $schema,
    'schema_name' => $schemaName,
    'max_output_tokens' => 900,
    'reasoning_effort' => get_reasoning_effort(),
    'timeout' => 90,
]);

if (!$result['ok'] || !is_array($result['parsed'])) {
    error_log('chat.php: openai_chat_responses failed: ' . ($result['error'] ?? 'unknown'));
    cardy_session_save($session);
    api_error(
        'cardy_glitch',
        $result['error'] ?? 'Cardy is having a glitch. Try again? *beep*',
        502,
        [
            'session_id' => $session['id'],
            'step' => $session['step'],
            'mode' => $session['mode'],
            'message' => 'Oops, I had a little glitch there. Could you try again? *beep boop*',
        ]
    );
}

$parsed = $result['parsed'];
$replyMessage = isset($parsed['message']) && is_string($parsed['message']) ? trim($parsed['message']) : '';
$replySuggestions = isset($parsed['suggestions']) && is_array($parsed['suggestions']) ? array_values($parsed['suggestions']) : [];
$visualConcept = isset($parsed['visual_concept']) && is_array($parsed['visual_concept']) ? $parsed['visual_concept'] : null;

$replyMessage = str_replace(["\xE2\x80\x94", "\xE2\x80\x93"], [',', ','], $replyMessage);

$forbidden = ['type your own', 'enter your own', 'custom response', 'write your own', 'paint it'];
$chipPath = ($path === CARDY_PATH_CHAT) ? CARDY_PATH_FAST : $path;
$missingForChips = cardy_missing_slots($session['visual_concept'] ?? [], $chipPath);
$focusChip = $missingForChips[0] ?? '';

if ($session['step'] === CARDY_STEP_CONFIRM) {
    $replySuggestions = [];
} elseif ($focusChip === 'kind') {
    // Reliable short kind menu; AI still writes the ask in her own words.
    $replySuggestions = ['A bot', 'An android', 'A human', 'A critter'];
} else {
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
    $replySuggestions = array_map(static function ($s) {
        return cardy_shorten_chip((string)$s, 24);
    }, $replySuggestions);
    $replySuggestions = array_values(array_filter($replySuggestions, static fn($s) => $s !== ''));
    if (count($replySuggestions) > 4) {
        $replySuggestions = array_slice($replySuggestions, 0, 4);
    }
    // Identity chips: kill stale dock-pun generics; pad with type-aware invents.
    if ($focusChip === 'identity') {
        $replySuggestions = cardy_sanitize_name_suggestions(
            $replySuggestions,
            $session['visual_concept'] ?? []
        );
    }
}

if ($session['step'] === CARDY_STEP_REVISE && is_array($visualConcept)) {
    $session = cardy_session_after_model($session, $replyMessage, $visualConcept);
    $session['step'] = CARDY_STEP_READY;
    $session['stats'] = cardobot_generate_stats($session['visual_concept']);
    cardy_session_save($session);
    api_json([
        'ok' => true,
        'session_id' => $session['id'],
        'step' => $session['step'],
        'mode' => $session['mode'],
        'path' => $session['path'] ?? null,
        'message' => $replyMessage,
        'suggestions' => [],
        'visual_concept' => $session['visual_concept'],
        'stats' => $session['stats'],
        'ready_to_render' => false,
        'auto_render' => true,
        'revise_remaining' => cardy_revise_remaining($session),
        'memory_hints' => $memoryHints,
        'tokens' => $result['usage'],
    ]);
}

$session = cardy_session_after_model($session, $replyMessage, $visualConcept);
if ($session['step'] === CARDY_STEP_CONFIRM && empty($session['stats'])) {
    $session['stats'] = cardobot_generate_stats($session['visual_concept']);
}
// Keep printers-running step when we asked Cardy for a paint line.
if ($autoRender) {
    $session['step'] = CARDY_STEP_RENDERING;
}
cardy_session_save($session);

$nickSuggestions = ($session['step'] === CARDY_STEP_CONFIRM)
    ? cardy_nickname_suggestions($session['visual_concept'] ?? [])
    : [];

api_json([
    'ok' => true,
    'session_id' => $session['id'],
    'step' => $session['step'],
    'mode' => $session['mode'],
    'path' => $session['path'] ?? null,
    'message' => $replyMessage,
    'suggestions' => $replySuggestions,
    'nickname_suggestions' => $nickSuggestions,
    'visual_concept' => $session['visual_concept'],
    'stats' => $session['stats'] ?? null,
    'ready_to_render' => false,
    'auto_render' => $autoRender,
    'revise_remaining' => cardy_revise_remaining($session),
    'memory_hints' => $memoryHints,
    'image_url' => $session['image_url'] ?? null,
    'tokens' => $result['usage'],
]);
