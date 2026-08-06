<?php
/**
 * Authenticated download / view of a saved card PNG (or raw art).
 */

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/cards.php';

api_boot(false);

if (!is_logged_in()) {
    api_error('auth_required', 'Authentication required', 401);
}

$userId = ensure_user_row();
$cardId = isset($_GET['card_id']) ? trim((string)$_GET['card_id']) : '';
$kind = isset($_GET['kind']) ? trim((string)$_GET['kind']) : 'card';
if (!$userId || $cardId === '') {
    http_response_code(400);
    exit('Missing card');
}

if ($kind === 'art') {
    $path = get_upload_root() . '/art/' . (int)$userId . '/' . $cardId . '_art.png';
    if (!is_file($path)) {
        http_response_code(404);
        exit('Art missing');
    }
    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($path));
    header('Content-Disposition: inline; filename="' . $cardId . '_art.png"');
    header('Cache-Control: private, max-age=3600');
    // Allow studio canvas decode from our own origins.
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? (string)$_SERVER['HTTP_ORIGIN'] : '';
    $originHost = $origin !== '' ? strtolower((string)(parse_url($origin, PHP_URL_HOST) ?: '')) : '';
    if ($originHost !== '' && in_array($originHost, api_allowed_hosts(), true)) {
        header('Access-Control-Allow-Origin: ' . $origin);
        header('Access-Control-Allow-Credentials: true');
        header('Vary: Origin');
    }
    readfile($path);
    exit;
}

$card = get_card_for_user((int)$userId, $cardId);
if (!$card) {
    http_response_code(404);
    exit('Card not found');
}

$path = get_upload_root() . '/cards/' . (int)$userId . '/' . $cardId . '.png';
if (!is_file($path)) {
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
