<?php
/**
 * CardSession state machine (Chat v2).
 */

require_once __DIR__ . '/cardy.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const CARDY_MODE_CARD     = 'card';
const CARDY_MODE_FREECHAT = 'free_chat';
const CARDY_MAX_REVISES   = 2;

function cardy_new_session_id(): string {
    return 'cs_' . bin2hex(random_bytes(8));
}

function cardy_session_create(?string $sessionId = null): array {
    if (!isset($_SESSION['cardobot_sessions']) || !is_array($_SESSION['cardobot_sessions'])) {
        $_SESSION['cardobot_sessions'] = [];
    }
    if ($sessionId === null) {
        $sessionId = cardy_new_session_id();
    }

    $session = [
        'id'             => $sessionId,
        'step'           => CARDY_STEP_GREETING,
        'mode'           => null,
        'answers'        => [],
        'visual_concept' => cardy_empty_concept(),
        'history'        => [],
        'image_task_id'  => null,
        'image_url'      => null,
        'image_b64'      => null,
        'art_url'        => null,
        'revise_count'   => 0,
        'hue'            => 195,
        'saturation'     => 65,
        'lightness'      => 40,
        'created_at'     => time(),
        'updated_at'     => time(),
    ];

    $_SESSION['cardobot_sessions'][$sessionId] = $session;
    return $session;
}

function cardy_session_get(string $sessionId): array {
    if ($sessionId === '') {
        return cardy_session_create();
    }
    if (!isset($_SESSION['cardobot_sessions'][$sessionId])) {
        return cardy_session_create($sessionId);
    }
    $session = $_SESSION['cardobot_sessions'][$sessionId];
    // Normalize legacy concept shape
    $session['visual_concept'] = cardy_merge_concept(
        cardy_empty_concept(),
        is_array($session['visual_concept'] ?? null) ? $session['visual_concept'] : []
    );
    if (!isset($session['revise_count'])) {
        $session['revise_count'] = 0;
    }
    return $session;
}

function cardy_session_save(array $session): void {
    if (empty($session['id'])) {
        return;
    }
    $session['updated_at'] = time();
    $_SESSION['cardobot_sessions'][$session['id']] = $session;
}

function cardy_merge_concept(array $base, array $patch): array {
    foreach ($patch as $key => $val) {
        if ($key === 'palette') {
            if (is_array($val)) {
                $base['palette'] = array_values(array_filter(array_map(static function ($c) {
                    return is_string($c) ? trim($c) : '';
                }, $val), static fn($c) => $c !== ''));
            } elseif (is_string($val) && trim($val) !== '') {
                $base['palette'] = array_map('trim', explode(',', $val));
            }
            continue;
        }
        if ($key === 'type') {
            $t = strtoupper(trim((string)$val));
            if ($t === 'BOT' || $t === 'CRITTER') {
                $base['type'] = $t;
            }
            continue;
        }
        if (is_string($val)) {
            $trimmed = trim($val);
            if ($trimmed !== '' || array_key_exists($key, $base)) {
                // Allow explicit clears only for revision_notes
                if ($trimmed !== '' || $key === 'revision_notes') {
                    $base[$key] = $trimmed;
                }
            }
        }
    }
    return $base;
}

function cardy_next_step(string $current, ?string $mode): string {
    if ($mode === CARDY_MODE_FREECHAT) {
        return CARDY_STEP_FREE_CHAT;
    }
    switch ($current) {
        case CARDY_STEP_GREETING:      return CARDY_STEP_CHOOSE_INTENT;
        case CARDY_STEP_CHOOSE_INTENT: return CARDY_STEP_Q_SUBJECT;
        case CARDY_STEP_Q_SUBJECT:     return CARDY_STEP_Q_VIBE;
        case CARDY_STEP_Q_VIBE:        return CARDY_STEP_Q_LOOKS;
        case CARDY_STEP_Q_LOOKS:       return CARDY_STEP_Q_WORLD;
        case CARDY_STEP_Q_WORLD:       return CARDY_STEP_Q_SPARK;
        case CARDY_STEP_Q_SPARK:       return CARDY_STEP_CONFIRM;
        case CARDY_STEP_CONFIRM:       return CARDY_STEP_READY;
        case CARDY_STEP_READY:         return CARDY_STEP_RENDERING;
        case CARDY_STEP_RENDERING:     return CARDY_STEP_REVEAL;
        case CARDY_STEP_REVISE:        return CARDY_STEP_RENDERING;
        case CARDY_STEP_REVEAL:        return CARDY_STEP_REVEAL;
        case CARDY_STEP_STUDIO:        return CARDY_STEP_STUDIO;
        // Legacy
        case 'q1_subject': return CARDY_STEP_Q_VIBE;
        case 'q2_vibe':    return CARDY_STEP_Q_LOOKS;
        case 'q3_details': return CARDY_STEP_CONFIRM;
        case 'q4_setting': return CARDY_STEP_Q_SPARK;
        case 'choose_mode': return CARDY_STEP_Q_SUBJECT;
        default:           return $current;
    }
}

function cardy_session_advance(array $session, string $userMessage = ''): array {
    if ($userMessage !== '') {
        $session['answers'][$session['step']] = $userMessage;
        $session['history'][] = ['role' => 'user', 'content' => $userMessage];
    }
    $session['step'] = cardy_next_step($session['step'], $session['mode']);
    return $session;
}

function cardy_session_record_reply(array $session, string $message, ?array $visualConcept): array {
    $session['history'][] = ['role' => 'assistant', 'content' => $message];
    if (count($session['history']) > 16) {
        $session['history'] = array_slice($session['history'], -16);
    }
    if (is_array($visualConcept)) {
        $session['visual_concept'] = cardy_merge_concept($session['visual_concept'], $visualConcept);
    }
    return $session;
}

function cardy_session_set_image_task(array $session, string $taskId): array {
    $session['image_task_id'] = $taskId;
    $session['step'] = CARDY_STEP_RENDERING;
    return $session;
}

function cardy_session_set_image(array $session, ?string $url, ?string $b64): array {
    $session['image_url'] = $url;
    $session['image_b64'] = $b64;
    if ($url) {
        $session['art_url'] = $url;
    }
    $session['step'] = CARDY_STEP_REVEAL;
    return $session;
}

function cardy_session_reset(array $session): array {
    return cardy_session_create();
}

function cardy_is_ready_to_render(array $session): bool {
    $c = $session['visual_concept'] ?? [];
    return !empty($c['subject']) && !empty($c['details']);
}

function cardy_revise_remaining(array $session): int {
    $used = (int)($session['revise_count'] ?? 0);
    return max(0, CARDY_MAX_REVISES - $used);
}
