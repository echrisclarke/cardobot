<?php
/**
 * Start Google OAuth on this host (Railway / cardobot.com).
 * Avoids Bluehost ModSecurity by never sending the Google callback through herbiecreative.com.
 *
 * Query:
 *   mode=login (default) | link
 *   redirect= optional post-login path
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google-auth.php';
auth_boot(true);

$basePath = get_base_path();

if (!is_google_oauth_configured()) {
    header('Location: ' . $basePath . '/login.php?error=' . urlencode('Google authentication is not configured'));
    exit;
}

$mode = strtolower((string)($_GET['mode'] ?? 'login'));
if ($mode === 'link') {
    if (!is_logged_in()) {
        header('Location: ' . $basePath . '/login.php?error=' . urlencode('Please sign in before linking a Google account.'));
        exit;
    }
    $_SESSION['link_google_account'] = true;
} else {
    unset($_SESSION['link_google_account']);
}

$redirect = (string)($_GET['redirect'] ?? '');
if ($redirect !== '' && str_starts_with($redirect, '/')) {
    $_SESSION['redirect_after_login'] = $redirect;
}

$state = generate_google_state();
header('Location: ' . get_google_auth_url($state));
exit;
