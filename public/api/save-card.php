<?php
/**
 * Save the rendered image from a card session into the user's collection.
 *
 * POST JSON: { "session_id": "cs_..." }
 *
 * Idempotent: re-saving the same session updates the existing row instead of
 * inserting a duplicate. Returns the saved card_id (= session_id).
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cards.php';
require_once __DIR__ . '/../includes/state.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

$userId = ensure_user_row();
if (!$userId) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not load your account']);
    exit;
}

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
$imageUrl = $session['image_url'] ?? '';
if ($imageUrl === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'No image to save -- render one first.']);
    exit;
}

$result = save_image_card(
    $userId,
    $sessionId,
    $imageUrl,
    $session['visual_concept'] ?? []
);

if (!$result['success']) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $result['message']]);
    exit;
}

echo json_encode([
    'ok'      => true,
    'card_id' => $result['card_id'],
    'message' => 'Saved to your collection.',
]);
