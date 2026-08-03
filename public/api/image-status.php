<?php
/**
 * Image Generation Status Endpoint
 *
 * POST { "task_id": "img_..." }
 *
 * Polled by the frontend after api/render-image.php returns a task_id.
 * On servers that *did* finish the request asynchronously (fastcgi), the
 * task will already be 'completed' here. On servers without fastcgi we run
 * the OpenAI call inline on the first poll and persist the result.
 *
 * Either way, when the image completes we mirror it back into the related
 * cardy session so the wizard's reveal step can read session.image_url.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/openai.php';
require_once __DIR__ . '/../includes/state.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = [];
}

$taskId = trim($data['task_id'] ?? '');
if ($taskId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'task_id is required']);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['image_tasks'][$taskId])) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Task not found']);
    exit;
}

$task = $_SESSION['image_tasks'][$taskId];

// Inline-generate fallback. Only runs when render-image.php couldn't use
// fastcgi_finish_request and explicitly handed the work off via
// source='inline'. Without this gate every 1s poll would fire its own
// concurrent OpenAI image call (huge cost + duplicate images in the UI).
$source = $task['source'] ?? 'fastcgi';
if ($task['status'] === 'generating' && $source === 'inline' && !empty($task['prompt'])) {
    // Claim the work so a second concurrent poll won't also generate.
    $_SESSION['image_tasks'][$taskId]['source'] = 'inline_running';
    session_write_close();
    session_start();

    $result = openai_image($task['prompt'], ['size' => '1024x1024', 'quality' => 'high']);

    if (!$result['ok']) {
        $_SESSION['image_tasks'][$taskId]['status'] = 'failed';
        $_SESSION['image_tasks'][$taskId]['error']  = $result['error'] ?? 'Image generation failed';
        error_log('image-status: inline render failed: ' . ($result['error'] ?? 'unknown'));
    } else {
        $img = $result['image'];
        $_SESSION['image_tasks'][$taskId] = [
            'status'        => 'completed',
            'image'         => $img,
            'visual_data'   => $task['visual_data'] ?? null,
            'card_session'  => $task['card_session'] ?? null,
            'username'      => $task['username'] ?? '',
            'created_at'    => $task['created_at'] ?? time(),
            'completed_at'  => time(),
            'source'        => 'inline',
        ];
    }
    $task = $_SESSION['image_tasks'][$taskId];
}

// Mirror finished image back to the originating cardy session.
if ($task['status'] === 'completed' && !empty($task['card_session'])) {
    $cs = cardy_session_get($task['card_session']);
    if (empty($cs['image_url']) && empty($cs['image_b64'])) {
        $cs = cardy_session_set_image(
            $cs,
            $task['image']['url'] ?? null,
            $task['image']['b64_json'] ?? null
        );
        cardy_session_save($cs);
    }
}

$response = [
    'ok'         => true,
    'task_id'    => $taskId,
    'status'     => $task['status'],
    'created_at' => $task['created_at'] ?? null,
];

if ($task['status'] === 'completed' && isset($task['image'])) {
    $response['image']        = $task['image'];
    $response['completed_at'] = $task['completed_at'] ?? null;
} elseif ($task['status'] === 'failed') {
    $response['error'] = $task['error'] ?? 'Image generation failed';
}

echo json_encode($response);
