<?php
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cards.php';
require_once __DIR__ . '/../includes/env.php';

api_boot(false);
api_assert_same_origin();
api_require_login();

$userId = ensure_user_row();
$data = api_require_post_json();
$cardId = isset($data['card_id']) ? trim((string)$data['card_id']) : '';

if (!$userId || $cardId === '') {
    api_error('missing_card', 'card_id required', 400);
}

$ok = delete_user_card((int)$userId, $cardId);
if ($ok) {
    $path = get_upload_root() . '/cards/' . (int)$userId . '/' . $cardId . '.png';
    if (is_file($path)) {
        @unlink($path);
    }
    $art = get_upload_root() . '/art/' . (int)$userId . '/' . $cardId . '_art.png';
    if (is_file($art)) {
        @unlink($art);
    }
}

api_json([
    'ok' => $ok,
    'error' => $ok ? null : 'delete_failed',
    'message' => $ok ? 'Deleted' : 'Could not delete card',
], $ok ? 200 : 404);
