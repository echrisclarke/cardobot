<?php
/**
 * Legacy save path: requires a local framed asset (use export-card for product saves).
 */

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/cards.php';
require_once __DIR__ . '/../includes/state.php';

api_boot(false);
api_assert_same_origin();
api_require_login();

$userId = ensure_user_row();
if (!$userId) {
    api_error('account_error', 'Could not load your account', 500);
}

$data = api_require_post_json();
$sessionId = isset($data['session_id']) && is_string($data['session_id']) ? trim($data['session_id']) : '';
if ($sessionId === '') {
    api_error('missing_session', 'session_id is required', 400);
}

$session = cardy_session_find($sessionId);
if ($session === null) {
    api_error('unknown_session', 'Unknown session_id', 404);
}

$framedPath = get_upload_root() . '/cards/' . (int)$userId . '/' . $sessionId . '.png';
if (!is_file($framedPath)) {
    api_error(
        'export_required',
        'Finish and export the framed card first (use export-card). Raw paint URLs are not saved.',
        400
    );
}

$basePath = get_base_path();
$imageUrl = $basePath . '/api/download-card.php?card_id=' . rawurlencode($sessionId);

$result = save_finished_card($userId, $sessionId, [
    'image_url' => $imageUrl,
    'visual_concept' => $session['visual_concept'] ?? [],
    'art_url' => $session['art_url'] ?? $session['image_url'] ?? null,
    'stats' => $session['stats'] ?? null,
    'hue' => $session['hue'] ?? null,
    'saturation' => $session['saturation'] ?? null,
    'lightness' => $session['lightness'] ?? null,
]);

if (!$result['success']) {
    api_error('save_failed', $result['message'] ?? 'Save failed', 500);
}

api_json([
    'ok' => true,
    'card_id' => $result['card_id'],
    'message' => 'Saved to your collection.',
]);
