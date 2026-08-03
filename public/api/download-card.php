<?php
/**
 * Authenticated download / view of a saved card PNG.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/cards.php';

if (!is_logged_in()) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Authentication required']);
    exit;
}

$userId = ensure_user_row();
$cardId = isset($_GET['card_id']) ? trim((string)$_GET['card_id']) : '';
if (!$userId || $cardId === '') {
    http_response_code(400);
    exit('Missing card');
}

$card = get_card_for_user((int)$userId, $cardId);
if (!$card) {
    http_response_code(404);
    exit('Card not found');
}

$path = get_upload_root() . '/cards/' . (int)$userId . '/' . $cardId . '.png';
if (!is_file($path)) {
    // Fallback: remote URL redirect if stored as absolute http
    $url = $card['image_url'] ?? '';
    if (preg_match('#^https?://#', $url) && !str_contains($url, 'download-card.php')) {
        header('Location: ' . $url);
        exit;
    }
    http_response_code(404);
    exit('File missing');
}

$download = isset($_GET['download']) && $_GET['download'] === '1';
$nickname = preg_replace('/[^a-zA-Z0-9_-]+/', '_', (string)($card['nickname'] ?? 'card'));
header('Content-Type: image/png');
header('Content-Length: ' . filesize($path));
if ($download) {
    header('Content-Disposition: attachment; filename="' . $nickname . '.png"');
} else {
    header('Content-Disposition: inline; filename="' . $nickname . '.png"');
}
header('Cache-Control: private, max-age=3600');
readfile($path);
exit;
