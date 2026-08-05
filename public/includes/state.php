<?php
/**
 * CardSession state machine (agenda + landing spots).
 */

require_once __DIR__ . '/api.php';
require_once __DIR__ . '/cardy.php';
require_once __DIR__ . '/card_flavor.php';

// Resume only; callers that need a fresh session (login/pages) boot with create.
if (session_status() === PHP_SESSION_NONE) {
    api_boot(false);
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
        'path'           => null,
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

function cardy_session_find(string $sessionId): ?array {
    if ($sessionId === '') {
        return null;
    }
    if (!isset($_SESSION['cardobot_sessions']) || !is_array($_SESSION['cardobot_sessions'])) {
        return null;
    }
    if (!isset($_SESSION['cardobot_sessions'][$sessionId])) {
        return null;
    }
    $session = $_SESSION['cardobot_sessions'][$sessionId];
    $session['visual_concept'] = cardy_scrub_meta_concept(cardy_merge_concept(
        cardy_empty_concept(),
        is_array($session['visual_concept'] ?? null) ? $session['visual_concept'] : []
    ));
    if (!isset($session['revise_count'])) {
        $session['revise_count'] = 0;
    }
    if (!isset($session['stats']) || !is_array($session['stats'])) {
        $session['stats'] = null;
    }
    if (!isset($session['path'])) {
        $session['path'] = null;
    }
    // Normalize legacy gather steps to agenda
    $step = $session['step'] ?? '';
    if (in_array($step, [
        CARDY_STEP_Q_WHO, CARDY_STEP_Q_FLAVOR, CARDY_STEP_CHOOSE_INTENT,
        CARDY_STEP_Q_SUBJECT, CARDY_STEP_Q_VIBE, CARDY_STEP_Q_LOOKS,
        CARDY_STEP_Q_WORLD, CARDY_STEP_Q_SPARK,
        'q1_subject', 'q2_vibe', 'q3_details', 'q4_setting', 'choose_mode',
    ], true)) {
        $session['step'] = CARDY_STEP_AGENDA;
    }
    return $session;
}

function cardy_session_get(string $sessionId): array {
    $found = cardy_session_find($sessionId);
    if ($found !== null) {
        return $found;
    }
    if ($sessionId === '') {
        return cardy_session_create();
    }
    return cardy_session_create($sessionId);
}

function cardy_session_save(array $session): void {
    if (empty($session['id'])) {
        return;
    }
    $session['updated_at'] = time();
    $_SESSION['cardobot_sessions'][$session['id']] = $session;
}

function cardy_merge_concept(array $base, array $patch): array {
    return cardy_merge_concept_authored($base, $patch, true);
}

/**
 * Apply model concept patch with authorship protection.
 */
function cardy_merge_concept_from_model(array $base, array $patch): array {
    return cardy_merge_concept_authored($base, $patch, false);
}

function cardy_next_step(string $current, ?string $mode): string {
    if ($mode === CARDY_MODE_FREECHAT) {
        return CARDY_STEP_FREE_CHAT;
    }
    switch ($current) {
        case CARDY_STEP_GREETING:
        case CARDY_STEP_CHOOSE_INTENT:
            return CARDY_STEP_AGENDA;
        case CARDY_STEP_AGENDA:
        case CARDY_STEP_Q_WHO:
        case CARDY_STEP_Q_FLAVOR:
        case CARDY_STEP_Q_SUBJECT:
        case CARDY_STEP_Q_VIBE:
        case CARDY_STEP_Q_LOOKS:
        case CARDY_STEP_Q_WORLD:
        case CARDY_STEP_Q_SPARK:
            return CARDY_STEP_AGENDA;
        case CARDY_STEP_CONFIRM:
            // Confirm does not auto-advance via next_step; paint uses confirm action
            return CARDY_STEP_CONFIRM;
        case CARDY_STEP_READY:         return CARDY_STEP_RENDERING;
        case CARDY_STEP_RENDERING:     return CARDY_STEP_REVEAL;
        case CARDY_STEP_REVISE:        return CARDY_STEP_READY;
        case CARDY_STEP_REVEAL:        return CARDY_STEP_REVEAL;
        case CARDY_STEP_STUDIO:        return CARDY_STEP_STUDIO;
        default:                       return $current;
    }
}

function cardy_session_advance(array $session, string $userMessage = ''): array {
    if ($userMessage !== '') {
        $session['answers'][$session['step']] = $userMessage;
        $session['history'][] = ['role' => 'user', 'content' => $userMessage];
    }
    $path = cardy_session_path($session);
    if (in_array($session['step'], [CARDY_STEP_AGENDA, CARDY_STEP_Q_WHO, CARDY_STEP_Q_FLAVOR], true)
        || str_starts_with((string)$session['step'], 'q_')
    ) {
        $missing = cardy_missing_slots($session['visual_concept'], $path === CARDY_PATH_CHAT ? CARDY_PATH_FAST : $path);
        $focus = $missing[0] ?? 'look';
        if ($userMessage !== '') {
            $session['visual_concept'] = cardy_absorb_user_text($session['visual_concept'], $userMessage, $focus);
        }
        if (cardy_slots_complete($session['visual_concept'], $path === CARDY_PATH_CHAT ? CARDY_PATH_FAST : $path)) {
            $session['step'] = CARDY_STEP_CONFIRM;
        } else {
            $session['step'] = CARDY_STEP_AGENDA;
        }
        return $session;
    }
    $session['step'] = cardy_next_step($session['step'], $session['mode']);
    return $session;
}

/**
 * After model reply: merge concept, land confirm if slots full.
 */
function cardy_session_after_model(array $session, string $message, ?array $visualConcept): array {
    $session['history'][] = ['role' => 'assistant', 'content' => $message];
    if (count($session['history']) > 16) {
        $session['history'] = array_slice($session['history'], -16);
    }
    if (is_array($visualConcept)) {
        $session['visual_concept'] = cardy_merge_concept_from_model($session['visual_concept'], $visualConcept);
    }
    $session['visual_concept'] = cardy_scrub_meta_concept($session['visual_concept']);
    $path = cardy_session_path($session);
    if (in_array($session['step'], [CARDY_STEP_AGENDA, CARDY_STEP_Q_WHO, CARDY_STEP_Q_FLAVOR], true)
        && $path !== CARDY_PATH_CHAT
        && cardy_slots_complete($session['visual_concept'], $path)
    ) {
        $session['visual_concept'] = cardy_ensure_power_ability($session['visual_concept']);
        $session['step'] = CARDY_STEP_CONFIRM;
    }
    return $session;
}

function cardy_session_record_reply(array $session, string $message, ?array $visualConcept): array {
    return cardy_session_after_model($session, $message, $visualConcept);
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
    $path = cardy_session_path($session);
    if ($path === CARDY_PATH_CHAT) {
        $path = CARDY_PATH_FAST;
    }
    return cardy_slots_complete($session['visual_concept'] ?? [], $path);
}

function cardy_revise_remaining(array $session): int {
    $used = (int)($session['revise_count'] ?? 0);
    return max(0, CARDY_MAX_REVISES - $used);
}

function cardy_select_path_value(string $value): array {
    $value = strtolower(trim($value));
    if (in_array($value, ['chat', 'lore', 'talk'], true)
        || str_contains($value, 'just chat')
        || str_contains($value, 'just talk')
    ) {
        return ['path' => CARDY_PATH_CHAT, 'mode' => CARDY_MODE_FREECHAT, 'step' => CARDY_STEP_FREE_CHAT];
    }
    if (in_array($value, ['form', 'form_path'], true)
        || str_contains($value, 'fill out a form')
        || str_contains($value, 'fill in a form')
        || str_contains($value, 'use a form')
        || (preg_match('/\bform\b/', $value) && !str_contains($value, 'transform') && !str_contains($value, 'format'))
    ) {
        // Skip agenda chat; open the confirm-style form panel.
        return ['path' => CARDY_PATH_FORM, 'mode' => CARDY_MODE_CARD, 'step' => CARDY_STEP_CONFIRM];
    }
    if (in_array($value, ['long', 'remember', 'detailed'], true)
        || str_contains($value, 'detailed')
        || str_contains($value, 'remember')
    ) {
        return ['path' => CARDY_PATH_LONG, 'mode' => CARDY_MODE_CARD, 'step' => CARDY_STEP_AGENDA];
    }
    // fast / card / default
    return ['path' => CARDY_PATH_FAST, 'mode' => CARDY_MODE_CARD, 'step' => CARDY_STEP_AGENDA];
}
