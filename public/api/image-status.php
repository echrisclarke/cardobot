<?php
/**
 * Image generation status (DB-backed).
 */

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/xai.php';
require_once __DIR__ . '/../includes/state.php';
require_once __DIR__ . '/../includes/image_tasks.php';

api_boot(false);
api_assert_same_origin();
$user = api_require_login();
$userId = (int)($user['id'] ?? 0);
if ($userId <= 0) {
    $userId = (int)(ensure_user_row() ?: 0);
}

$data = api_require_post_json();
$taskId = trim((string)($data['task_id'] ?? ''));
if ($taskId === '') {
    api_error('missing_task', 'task_id is required', 400);
}

$task = image_task_get($taskId, $userId);
if ($task === null) {
    api_error('task_not_found', 'Task not found', 404);
}

$source = $task['source'] ?? 'fastcgi';
if ($task['status'] === 'generating' && $source === 'inline' && !empty($task['prompt'])) {
    image_task_update($taskId, ['source' => 'inline_running']);

    $result = generate_card_image((string)$task['prompt']);
    if (!$result['ok']) {
        image_task_update($taskId, [
            'status' => 'failed',
            'error' => $result['error'] ?? 'Image generation failed',
            'source' => 'inline',
        ]);
    } else {
        $img = $result['image'];
        $sessionId = (string)($task['session_id'] ?? '');
        $localUrl = persist_generated_art($userId, $sessionId !== '' ? $sessionId : $taskId, $img);
        $finalUrl = $localUrl ?: ($img['url'] ?? null);
        image_task_update($taskId, [
            'status' => 'completed',
            'image_url' => $finalUrl,
            'source' => 'inline',
            'error' => null,
        ]);

        if ($sessionId !== '') {
            $cs = cardy_session_find($sessionId);
            if ($cs) {
                $cs = cardy_session_set_image($cs, $finalUrl, $localUrl ? null : ($img['b64_json'] ?? null));
                $cs['art_url'] = $finalUrl;
                $cs['step'] = CARDY_STEP_REVEAL;
                cardy_session_save($cs);
            }
        }
    }
    $task = image_task_get($taskId, $userId) ?? $task;
}

if ($task['status'] === 'completed' && !empty($task['session_id']) && !empty($task['image_url'])) {
    $cs = cardy_session_find((string)$task['session_id']);
    if ($cs && empty($cs['image_url'])) {
        $cs = cardy_session_set_image($cs, $task['image_url'], null);
        $cs['art_url'] = $task['image_url'];
        $cs['step'] = CARDY_STEP_REVEAL;
        cardy_session_save($cs);
    }
}

$response = [
    'ok' => true,
    'task_id' => $taskId,
    'status' => $task['status'],
    'created_at' => $task['created_at'] ?? null,
];

if ($task['status'] === 'completed') {
    $response['image'] = ['url' => $task['image_url']];
    $response['image_url'] = $task['image_url'];
    $response['completed_at'] = $task['completed_at'] ?? null;
} elseif ($task['status'] === 'failed') {
    $response['error'] = $task['error'] ?? 'Image generation failed';
    $response['message'] = $response['error'];
}

api_json($response);
