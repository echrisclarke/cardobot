<?php
/**
 * Legacy image proxy — admin + login only.
 */

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/openai.php';

api_boot(false);
api_assert_same_origin();
$user = api_require_login();
if (empty($user['is_admin'])) {
    api_error('forbidden', 'Admin only', 403);
}

$data = api_require_post_json();
$prompt = trim((string)($data['prompt'] ?? ''));
if ($prompt === '') {
    api_error('missing_prompt', 'prompt is required', 400);
}

$result = openai_image($prompt, [
    'model' => trim((string)($data['model'] ?? get_image_model())),
    'size' => trim((string)($data['size'] ?? '1024x1024')),
    'quality' => trim((string)($data['quality'] ?? 'high')),
]);

if (!$result['ok']) {
    api_error('image_failed', $result['error'] ?? 'Image generation failed', 502);
}

api_json([
    'ok' => true,
    'image' => $result['image'],
]);
