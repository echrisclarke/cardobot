<?php
/**
 * Card-o-Bot image rendering endpoint (durable DB tasks + provider facade).
 */

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xai.php';
require_once __DIR__ . '/../includes/state.php';
require_once __DIR__ . '/../includes/prompt_compiler.php';
require_once __DIR__ . '/../includes/ml_client.php';
require_once __DIR__ . '/../includes/image_tasks.php';
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
$sessionId = isset($data['session_id']) && is_string($data['session_id']) ? trim($data['session_id']) : '';
if ($sessionId === '') {
    api_error('missing_session', 'session_id is required', 400);
}

$session = cardy_session_find($sessionId);
if ($session === null) {
    api_error('unknown_session', 'Unknown session_id', 404);
}

if (!empty($session['image_url']) || !empty($session['image_b64'])) {
    $image = [];
    if (!empty($session['image_url'])) {
        $image['url'] = $session['image_url'];
    }
    if (!empty($session['image_b64'])) {
        $image['b64_json'] = $session['image_b64'];
    }
    api_json([
        'ok' => true,
        'status' => 'completed',
        'image' => $image,
        'image_url' => $session['image_url'],
        'cached' => true,
        'stats' => $session['stats'] ?? null,
    ]);
}

$concept = $session['visual_concept'] ?? null;
if (!is_array($concept) || empty($concept['subject'])) {
    api_error('no_concept', 'No visual concept yet. Finish the wizard first.', 400);
}

if (empty($session['stats'])) {
    $session['stats'] = cardobot_generate_stats($concept);
    cardy_session_save($session);
}

$memoryHints = [];
$hintSeed = trim(($concept['subject'] ?? '') . ' ' . ($concept['details'] ?? ''));
if ($userId > 0 && $hintSeed !== '') {
    $memoryHints = ml_similar_hints($userId, $hintSeed, 2);
}
$prompt = build_render_prompt($concept, $memoryHints);

$safety = ml_safety_check($prompt);
if (isset($safety['safe']) && $safety['safe'] === false) {
    api_error('unsafe_concept', 'That concept looks a little too spicy for the ship printers. Want to tweak it?', 400);
}

$useFastcgi = function_exists('fastcgi_finish_request');
$source = $useFastcgi ? 'fastcgi' : 'inline';
$taskId = image_task_create($userId, $session['id'], $prompt, $source, $concept);
if ($taskId === null) {
    api_error('task_create_failed', 'Could not start paint job', 500);
}

$session = cardy_session_set_image_task($session, $taskId);
$session['step'] = CARDY_STEP_RENDERING;
cardy_session_save($session);

$response = [
    'ok' => true,
    'status' => 'generating',
    'task_id' => $taskId,
    'session_id' => $session['id'],
];

if ($useFastcgi) {
    http_response_code(200);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($response);
    fastcgi_finish_request();

    $result = generate_card_image($prompt);
    if (!$result['ok']) {
        image_task_update($taskId, [
            'status' => 'failed',
            'error' => $result['error'] ?? 'Image generation failed',
        ]);
        $session = cardy_session_find($sessionId) ?? $session;
        $session['step'] = CARDY_STEP_READY;
        cardy_session_save($session);
        exit;
    }

    $img = $result['image'];
    $localUrl = persist_generated_art($userId, $sessionId, $img);
    $finalUrl = $localUrl ?: ($img['url'] ?? null);
    image_task_update($taskId, [
        'status' => 'completed',
        'image_url' => $finalUrl,
        'error' => null,
    ]);

    $session = cardy_session_find($sessionId) ?? $session;
    $session = cardy_session_set_image($session, $finalUrl, $localUrl ? null : ($img['b64_json'] ?? null));
    $session['art_url'] = $finalUrl;
    $session['step'] = CARDY_STEP_REVEAL;
    cardy_session_save($session);
    exit;
}

api_json($response);
