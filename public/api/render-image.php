<?php
/**
 * Card-o-Bot image rendering endpoint.
 *
 * POST JSON: { "session_id": "cs_..." }
 *
 * Idempotent: if the session already has an image, returns it immediately
 * (no new OpenAI bill). Otherwise builds a clean prompt from
 * session.visual_concept, kicks off generation, and uses
 * fastcgi_finish_request to do the actual API call in the background while
 * the client gets an immediate task_id to poll via api/image-status.php.
 *
 * Response (already-generated):
 *   { ok: true, status: "completed", image: {url: ...}, image_url: ... }
 *
 * Response (kicked off):
 *   { ok: true, status: "generating", task_id: "img_..." }
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/openai.php';
require_once __DIR__ . '/../includes/state.php';
require_once __DIR__ . '/../includes/prompt_compiler.php';
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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = [];
}

$sessionId = isset($data['session_id']) && is_string($data['session_id']) ? trim($data['session_id']) : '';
if ($sessionId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'session_id is required']);
    exit;
}

$session = cardy_session_get($sessionId);

// ---- Idempotent: if already generated, return cached result -------------
if (!empty($session['image_url']) || !empty($session['image_b64'])) {
    $image = [];
    if (!empty($session['image_url'])) {
        $image['url'] = $session['image_url'];
    }
    if (!empty($session['image_b64'])) {
        $image['b64_json'] = $session['image_b64'];
    }
    echo json_encode([
        'ok'        => true,
        'status'    => 'completed',
        'image'     => $image,
        'image_url' => $session['image_url'],
        'cached'    => true,
    ]);
    exit;
}

// ---- Validate concept ---------------------------------------------------
$concept = $session['visual_concept'] ?? null;
if (!is_array($concept) || empty($concept['subject'])) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'No visual concept yet. Finish the wizard first.',
    ]);
    exit;
}

// ---- Build the image prompt --------------------------------------------
$userId = (int)($user['id'] ?? 0);
$memoryHints = [];
$hintSeed = trim(($concept['subject'] ?? '') . ' ' . ($concept['details'] ?? ''));
if ($userId > 0 && $hintSeed !== '') {
    $memoryHints = ml_similar_hints($userId, $hintSeed, 2);
}
$prompt = build_render_prompt($concept, $memoryHints);

$safety = ml_safety_check($prompt);
if (isset($safety['safe']) && $safety['safe'] === false) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'That concept looks a little too spicy for the ship printers. Want to tweak it?',
    ]);
    exit;
}

// ---- Mint a task and store in session ----------------------------------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['image_tasks']) || !is_array($_SESSION['image_tasks'])) {
    $_SESSION['image_tasks'] = [];
}

$taskId = 'img_' . time() . '_' . bin2hex(random_bytes(3));

// 'source' tells image-status.php who's responsible for actually calling
// OpenAI. With fastcgi we own it from this request; otherwise the first
// poll to image-status.php takes over. Without this flag image-status
// would fire its own concurrent OpenAI calls on every 1s poll.
$useFastcgi = function_exists('fastcgi_finish_request');

$_SESSION['image_tasks'][$taskId] = [
    'status'         => 'generating',
    'prompt'         => $prompt,
    'visual_data'    => $concept,
    'card_session'   => $session['id'],
    'username'       => $username,
    'created_at'     => time(),
    'source'         => $useFastcgi ? 'fastcgi' : 'inline',
];

$session = cardy_session_set_image_task($session, $taskId);
cardy_session_save($session);

$response = [
    'ok'         => true,
    'status'     => 'generating',
    'task_id'    => $taskId,
    'session_id' => $session['id'],
];

if ($useFastcgi) {
    echo json_encode($response);
    fastcgi_finish_request();

    $result = openai_image($prompt, ['size' => '1024x1024', 'quality' => 'high']);

    if (!$result['ok']) {
        $_SESSION['image_tasks'][$taskId]['status'] = 'failed';
        $_SESSION['image_tasks'][$taskId]['error']  = $result['error'] ?? 'Image generation failed';
        error_log('render-image (async) failed: ' . ($result['error'] ?? 'unknown'));
        // Refresh session to get latest copy in case other requests modified it.
        $session = cardy_session_get($session['id']);
        $session['step'] = CARDY_STEP_READY; // back to ready so user can retry
        cardy_session_save($session);
        exit;
    }

    $img = $result['image'];
    $_SESSION['image_tasks'][$taskId] = [
        'status'        => 'completed',
        'image'         => $img,
        'visual_data'   => $concept,
        'card_session'  => $session['id'],
        'username'      => $username,
        'created_at'    => $_SESSION['image_tasks'][$taskId]['created_at'],
        'completed_at'  => time(),
    ];

    $session = cardy_session_get($session['id']);
    $session = cardy_session_set_image(
        $session,
        $img['url'] ?? null,
        $img['b64_json'] ?? null
    );
    cardy_session_save($session);
    exit;
}

// No fastcgi_finish_request -- the existing api/image-status.php endpoint
// will run the generation on the client's first poll. Just return the task.
echo json_encode($response);
