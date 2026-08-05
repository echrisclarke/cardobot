<?php
/**
 * Google OAuth Callback Handler
 * Processes the OAuth callback from Google
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Disabled in production
ini_set('log_errors', 1);

/**
 * Redirect to login with error message
 * @param string $error Error message
 */
function redirect_with_error(string $error): void {
    if (!function_exists('get_base_path')) {
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $basePath = (
            $host === 'cardobot.com'
            || $host === 'www.cardobot.com'
            || str_contains($host, 'railway.app')
            || str_contains($host, 'localhost')
            || str_contains($host, '127.0.0.1')
        ) ? '' : '/cardobot';
    } else {
        $basePath = get_base_path();
    }
    $errorUrl = $basePath . '/login.php?error=' . urlencode($error);
    header('Location: ' . $errorUrl);
    exit;
}

try {
    // Load required files
    if (!file_exists(__DIR__ . '/../includes/google-auth.php')) {
        error_log('Google OAuth: google-auth.php not found');
        redirect_with_error('Google authentication is not configured');
    }
    
    require_once __DIR__ . '/../includes/google-auth.php';
    
    if (!file_exists(__DIR__ . '/../includes/auth.php')) {
        error_log('Google OAuth: auth.php not found');
        redirect_with_error('Authentication system error');
    }
    
    require_once __DIR__ . '/../includes/auth.php';
    auth_boot(true);
    
    // Check if Google OAuth is configured
    if (!is_google_oauth_configured()) {
        error_log('Google OAuth: Credentials not configured in .env file');
        redirect_with_error('Google authentication is not configured. Please contact the administrator.');
    }
    
    // Check for errors from Google
    if (isset($_GET['error'])) {
        $error = htmlspecialchars($_GET['error']);
        error_log('Google OAuth error from Google: ' . $error);
        redirect_with_error('Google authentication failed: ' . $error);
    }
    
    // Get authorization code and state
    $code = $_GET['code'] ?? '';
    $state = $_GET['state'] ?? '';
    
    if (empty($code)) {
        error_log('Google OAuth: No authorization code received');
        redirect_with_error('No authorization code received from Google');
    }
    
    // Verify state (CSRF protection)
    if (!function_exists('verify_google_state')) {
        error_log('Google OAuth: verify_google_state function not found');
        redirect_with_error('Authentication system error');
    }
    
    if (!verify_google_state($state)) {
        error_log('Google OAuth: Invalid state parameter');
        redirect_with_error('Invalid state parameter. Please try again.');
    }
    
    // Exchange code for access token
    if (!function_exists('exchange_google_code')) {
        error_log('Google OAuth: exchange_google_code function not found');
        redirect_with_error('Authentication system error');
    }
    
    $tokenResult = exchange_google_code($code);
    if (!$tokenResult['success']) {
        $errorMsg = $tokenResult['error'] ?? 'Unknown error';
        error_log('Google OAuth: Failed to exchange code for token: ' . $errorMsg);
        redirect_with_error('Failed to get access token: ' . $errorMsg);
    }
    
    // Get user info from Google
    if (!function_exists('get_google_user_info')) {
        error_log('Google OAuth: get_google_user_info function not found');
        redirect_with_error('Authentication system error');
    }
    
    $userInfoResult = get_google_user_info($tokenResult['access_token']);
    if (!$userInfoResult['success']) {
        $errorMsg = $userInfoResult['error'] ?? 'Unknown error';
        error_log('Google OAuth: Failed to get user info: ' . $errorMsg);
        redirect_with_error('Failed to get user info: ' . $errorMsg);
    }
    
    // Find or create user
    if (!function_exists('find_or_create_google_user')) {
        error_log('Google OAuth: find_or_create_google_user function not found');
        redirect_with_error('Authentication system error');
    }
    
    // Check if this is for account linking (user already logged in)
    $isLinking = isset($_SESSION['link_google_account']) && $_SESSION['link_google_account'] === true;
    
    if ($isLinking && is_logged_in()) {
        // User is already logged in and wants to link Google account
        $currentUser = get_logged_in_user();
        $linkResult = link_google_account($currentUser['id'], $userInfoResult['user']);
        
        unset($_SESSION['link_google_account']);
        
        if ($linkResult['success']) {
            $basePath = get_base_path();
            header('Location: ' . $basePath . '/settings.php?linked=1');
            exit;
        } else {
            redirect_with_error($linkResult['message']);
        }
    }
    
    $userResult = find_or_create_google_user($userInfoResult['user']);
    
    // Check if account linking is required (email matches existing account)
    if (!$userResult['success'] && $userResult['message'] === 'account_linking_required') {
        // Redirect to account linking page
        $basePath = get_base_path();
        header('Location: ' . $basePath . '/link-account.php');
        exit;
    }
    
    if (!$userResult['success']) {
        $errorMsg = $userResult['message'] ?? 'Unknown error';
        error_log('Google OAuth: Failed to find or create user: ' . $errorMsg);
        redirect_with_error($errorMsg);
    }
    
    // Log in the user
    if (!function_exists('login_user')) {
        error_log('Google OAuth: login_user function not found');
        redirect_with_error('Authentication system error');
    }
    
    login_user($userResult['user']);
    
    // Redirect to main app
    $basePath = get_base_path();
    $redirect = $_SESSION['redirect_after_login'] ?? $basePath . '/index.php';
    unset($_SESSION['redirect_after_login']);
    header('Location: ' . $redirect);
    exit;
    
} catch (Throwable $e) {
    // Catch any PHP errors or exceptions
    $errorMsg = 'An unexpected error occurred during Google authentication';
    error_log('Google OAuth Fatal Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());
    
    // In development, you might want to show the actual error
    // In production, show a generic message
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        $errorMsg .= ': ' . $e->getMessage();
    }
    
    redirect_with_error($errorMsg);
}
