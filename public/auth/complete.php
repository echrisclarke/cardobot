<?php
/**
 * Card-o-Bot — Central Login Authority handoff completion endpoint.
 *
 * Receives a one-time HMAC-signed token minted by
 * https://herbiecreative.com/auth/google-callback.php, verifies it, and
 * creates a local Card-o-Bot session (or links the Google account to an
 * already-logged-in user when mode=link).
 *
 * See API-TOOLS-CREDENTIALS-GUIDE.md §5.4.1.
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google-auth.php';

// Resolve the shared library across hosts/addon-domains. DOCUMENT_ROOT differs
// between cardobot.com (which points at .../public_html/cardobot) and
// herbiecreative.com (which points at .../public_html). Try both layouts.
$handoffLib = null;
foreach ([
    dirname(__DIR__, 2) . '/includes/auth-handoff.php',           // when DOCUMENT_ROOT=public_html
    dirname($_SERVER['DOCUMENT_ROOT'] ?? '', 1) . '/includes/auth-handoff.php',
    dirname($_SERVER['DOCUMENT_ROOT'] ?? '', 2) . '/public_html/includes/auth-handoff.php',
    '/home/herbiecr/public_html/includes/auth-handoff.php',
    '/home4/herbiecr/public_html/includes/auth-handoff.php',
] as $candidate) {
    if ($candidate && is_file($candidate)) {
        $handoffLib = $candidate;
        break;
    }
}

if ($handoffLib === null) {
    error_log('[cardobot/auth/complete] auth-handoff.php not found');
    $basePath = get_base_path();
    header('Location: ' . $basePath . '/login.php?error=' . urlencode('Authentication is temporarily unavailable'));
    exit;
}
require_once $handoffLib;

function complete_redirect_login_error(string $msg): void {
    $basePath = get_base_path();
    header('Location: ' . $basePath . '/login.php?error=' . urlencode($msg));
    exit;
}

function complete_redirect_settings_error(string $msg): void {
    $basePath = get_base_path();
    header('Location: ' . $basePath . '/settings.php?error=' . urlencode($msg));
    exit;
}

$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
if ($token === '') {
    error_log('[cardobot/auth/complete] missing token');
    complete_redirect_login_error('Sign-in failed. Please try again.');
}

try {
    $payload = auth_handoff_verify($token, 'cardobot');
} catch (Throwable $e) {
    error_log('[cardobot/auth/complete] verify exception: ' . $e->getMessage());
    complete_redirect_login_error('Sign-in failed. Please try again.');
}

if (!is_array($payload)) {
    complete_redirect_login_error('Sign-in expired or invalid. Please try again.');
}

$googleUser = [
    'id' => (string)($payload['sub'] ?? ''),
    'email' => (string)($payload['email'] ?? ''),
    'verified_email' => (bool)($payload['email_verified'] ?? false),
    'name' => (string)($payload['name'] ?? ''),
    'given_name' => (string)($payload['given_name'] ?? ''),
    'family_name' => (string)($payload['family_name'] ?? ''),
    'picture' => (string)($payload['picture'] ?? ''),
];

if ($googleUser['id'] === '') {
    complete_redirect_login_error('Google profile data was missing.');
}

$mode = (string)($payload['mode'] ?? 'login');
$basePath = get_base_path();

if ($mode === 'link') {
    if (!is_logged_in()) {
        complete_redirect_login_error('Please sign in before linking a Google account.');
    }
    $current = get_logged_in_user();
    $currentId = (int)($current['id'] ?? 0);
    if ($currentId <= 0) {
        complete_redirect_login_error('Sign-in session is invalid.');
    }
    $linkResult = link_google_account($currentId, $googleUser);
    if (!empty($linkResult['success'])) {
        header('Location: ' . $basePath . '/settings.php?linked=1');
        exit;
    }
    error_log('[cardobot/auth/complete] link failed: ' . ($linkResult['message'] ?? 'unknown'));
    complete_redirect_settings_error($linkResult['message'] ?? 'Could not link Google account.');
}

$result = find_or_create_google_user($googleUser);

// Existing flow: when an email matches an unlinked account, route the user
// through the link-account confirmation page.
if (empty($result['success']) && ($result['message'] ?? '') === 'account_linking_required') {
    header('Location: ' . $basePath . '/link-account.php');
    exit;
}

if (empty($result['success']) || empty($result['user'])) {
    error_log('[cardobot/auth/complete] find_or_create failed: ' . ($result['message'] ?? 'unknown'));
    complete_redirect_login_error($result['message'] ?? 'Sign-in failed. Please try again.');
}

login_user($result['user']);

$redirect = $_SESSION['redirect_after_login'] ?? ($basePath . '/index.php');
unset($_SESSION['redirect_after_login']);
header('Location: ' . $redirect);
exit;
