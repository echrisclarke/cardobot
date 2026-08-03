<?php
/**
 * Accept a finished card PNG (and optional drawing JSON), store it, save to DB.
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/state.php';
require_once __DIR__ . '/../includes/cards.php';
require_once __DIR__ . '/../includes/ml_client.php';

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
$composite = isset($data['composite_png']) && is_string($data['composite_png']) ? $data['composite_png'] : '';
$drawingData = $data['drawing_data'] ?? null;
$hue = isset($data['hue']) ? (int)$data['hue'] : null;
$sat = isset($data['saturation']) ? (int)$data['saturation'] : null;
$light = isset($data['lightness']) ? (int)$data['lightness'] : null;

if ($sessionId === '' || $composite === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'session_id and composite_png are required']);
    exit;
}

if (!preg_match('#^data:image/png;base64,#', $composite)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'composite_png must be a PNG data URL']);
    exit;
}

$b64 = substr($composite, strpos($composite, ',') + 1);
$b64 = str_replace(' ', '+', $b64);
$bin = base64_decode($b64, true);
if ($bin === false || strlen($bin) < 100) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid PNG data']);
    exit;
}
if (strlen($bin) > 12 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Image too large']);
    exit;
}

$session = cardy_session_get($sessionId);
$uploadRoot = get_upload_root();
$userDir = $uploadRoot . '/cards/' . $userId;
if (!is_dir($userDir)) {
    mkdir($userDir, 0755, true);
}
$filename = $sessionId . '.png';
$diskPath = $userDir . '/' . $filename;
if (file_put_contents($diskPath, $bin) === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not write upload']);
    exit;
}

// Public URL served via download-card.php or uploads symlink
$basePath = get_base_path();
$imageUrl = $basePath . '/api/download-card.php?card_id=' . rawurlencode($sessionId);

$result = save_finished_card($userId, $sessionId, [
    'image_url' => $imageUrl,
    'drawing_data' => $drawingData,
    'hue' => $hue,
    'saturation' => $sat,
    'lightness' => $light,
    'visual_concept' => $session['visual_concept'] ?? [],
    'art_url' => $session['art_url'] ?? $session['image_url'] ?? null,
]);

if (!$result['success']) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $result['message']]);
    exit;
}

$vc = $session['visual_concept'] ?? [];
$indexText = trim(implode(' ', array_filter([
    $vc['nickname'] ?? '',
    $vc['subject'] ?? '',
    $vc['vibe'] ?? '',
    $vc['details'] ?? '',
    $vc['setting'] ?? '',
])));
ml_index_card((int)$userId, $sessionId, $indexText, $imageUrl);

echo json_encode([
    'ok' => true,
    'card_id' => $sessionId,
    'image_url' => $imageUrl,
    'message' => 'Saved to your collection.',
]);
