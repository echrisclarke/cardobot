<?php
/**
 * Accept a finished card PNG (and optional drawing JSON), store it, save to DB.
 */

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/state.php';
require_once __DIR__ . '/../includes/cards.php';
require_once __DIR__ . '/../includes/ml_client.php';
require_once __DIR__ . '/../includes/stats.php';

api_boot(false);
api_assert_same_origin();
api_require_login();

$userId = ensure_user_row();
if (!$userId) {
    api_error('account_error', 'Could not load your account', 500);
}

$data = api_require_post_json();

$sessionId = isset($data['session_id']) && is_string($data['session_id']) ? trim($data['session_id']) : '';
$composite = isset($data['composite_png']) && is_string($data['composite_png']) ? $data['composite_png'] : '';
$drawingData = $data['drawing_data'] ?? null;
$hue = isset($data['hue']) ? (int)$data['hue'] : null;
$sat = isset($data['saturation']) ? (int)$data['saturation'] : null;
$light = isset($data['lightness']) ? (int)$data['lightness'] : null;

if ($sessionId === '' || $composite === '') {
    api_error('missing_fields', 'session_id and composite_png are required', 400);
}

if (!preg_match('#^data:image/png;base64,#', $composite)) {
    api_error('invalid_png', 'composite_png must be a PNG data URL', 400);
}

$b64 = substr($composite, strpos($composite, ',') + 1);
$b64 = str_replace(' ', '+', $b64);
$bin = base64_decode($b64, true);
if ($bin === false || strlen($bin) < 100) {
    api_error('invalid_png', 'Invalid PNG data', 400);
}
if (strlen($bin) > 12 * 1024 * 1024) {
    api_error('too_large', 'Image too large', 400);
}

$session = cardy_session_find($sessionId);
if ($session === null) {
    api_error('unknown_session', 'Unknown session_id', 404);
}

$uploadRoot = get_upload_root();
$userDir = $uploadRoot . '/cards/' . $userId;
if (!is_dir($userDir)) {
    mkdir($userDir, 0755, true);
}
$filename = $sessionId . '.png';
$diskPath = $userDir . '/' . $filename;
if (file_put_contents($diskPath, $bin) === false) {
    api_error('write_failed', 'Could not write upload', 500);
}

$basePath = get_base_path();
$imageUrl = $basePath . '/api/download-card.php?card_id=' . rawurlencode($sessionId);

$stats = is_array($data['stats'] ?? null) ? $data['stats'] : ($session['stats'] ?? null);
if (!is_array($stats)) {
    $stats = cardobot_generate_stats($session['visual_concept'] ?? []);
}

$visualConcept = is_array($session['visual_concept'] ?? null) ? $session['visual_concept'] : [];
if (array_key_exists('show_credit', $data)) {
    $visualConcept['show_credit'] = !empty($data['show_credit']);
}

$saveOpts = [
    'image_url' => $imageUrl,
    'visual_concept' => $visualConcept,
    'art_url' => $session['art_url'] ?? $session['image_url'] ?? null,
    'stats' => $stats,
];
if (array_key_exists('drawing_data', $data)) {
    $saveOpts['drawing_data'] = $drawingData;
}
if (array_key_exists('hue', $data)) {
    $saveOpts['hue'] = $hue;
}
if (array_key_exists('saturation', $data)) {
    $saveOpts['saturation'] = $sat;
}
if (array_key_exists('lightness', $data)) {
    $saveOpts['lightness'] = $light;
}
if (array_key_exists('back_variant', $data)) {
    $saveOpts['back_variant'] = $data['back_variant'];
}
if (array_key_exists('back_hue', $data)) {
    $saveOpts['back_hue'] = (int)$data['back_hue'];
    $saveOpts['back_saturation'] = isset($data['back_saturation']) ? (int)$data['back_saturation'] : 65;
    $saveOpts['back_lightness'] = isset($data['back_lightness']) ? (int)$data['back_lightness'] : 40;
}

$result = save_finished_card($userId, $sessionId, $saveOpts);

if (!$result['success']) {
    api_error('save_failed', $result['message'] ?? 'Save failed', 500);
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

// Grow ship memory after the response so save stays snappy.
$vcForStory = $session['visual_concept'] ?? [];
$statsForStory = is_array($stats) ? $stats : [];
$uidForStory = (int)$userId;
$sidForStory = $sessionId;
register_shutdown_function(static function () use ($vcForStory, $statsForStory, $uidForStory, $sidForStory) {
    try {
        require_once __DIR__ . '/../includes/story.php';
        if (!function_exists('generate_and_update_story_chapter')) {
            return;
        }
        generate_and_update_story_chapter([
            'card_name' => $vcForStory['nickname'] ?? ($vcForStory['subject'] ?? 'Unknown'),
            'type_line' => $vcForStory['vibe'] ?? '',
            'bio' => $vcForStory['bio'] ?? '',
            'stats' => $statsForStory,
            'ability_name' => $vcForStory['power_name'] ?? '',
            'ability_effect' => $vcForStory['ability_line'] ?? '',
            'height' => $statsForStory['height'] ?? ($vcForStory['height'] ?? ''),
            'mass' => $statsForStory['mass'] ?? ($vcForStory['mass'] ?? ''),
        ], $uidForStory, $sidForStory);
    } catch (Throwable $e) {
        error_log('export-card story chapter: ' . $e->getMessage());
    }
});

api_json([
    'ok' => true,
    'card_id' => $sessionId,
    'image_url' => $imageUrl,
    'message' => 'Saved to your collection.',
    'stats' => $stats,
]);
