<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cards.php';
require_once __DIR__ . '/../includes/env.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

$userId = ensure_user_row();
$raw = file_get_contents('php://input');
$data = json_decode($raw, true) ?: [];
$cardId = isset($data['card_id']) ? trim((string)$data['card_id']) : '';

if (!$userId || $cardId === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'card_id required']);
    exit;
}

$ok = delete_user_card((int)$userId, $cardId);
if ($ok) {
    $path = get_upload_root() . '/cards/' . (int)$userId . '/' . $cardId . '.png';
    if (is_file($path)) {
        @unlink($path);
    }
}

echo json_encode(['ok' => $ok]);
