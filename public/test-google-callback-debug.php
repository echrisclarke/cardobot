<?php
require_once __DIR__ . '/includes/api.php';
api_boot();
api_deny_public_probe();
/**
 * Google OAuth Callback Debug Script
 * This helps identify what's causing the HTTP 500 error
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/includes/auth.php';

// Require admin access
if (!is_admin()) {
    header('Location: ' . get_base_path() . '/index.php');
    exit;
}

echo "<h1>Google OAuth Callback Debug</h1>";
echo "<pre>";

// Test 1: Check if required files exist
echo "=== Test 1: File Existence ===\n";
$files = [
    'google-auth.php' => __DIR__ . '/includes/google-auth.php',
    'auth.php' => __DIR__ . '/includes/auth.php',
    'env.php' => __DIR__ . '/includes/env.php'
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    echo "$name: " . ($exists ? "✅ EXISTS" : "❌ NOT FOUND") . "\n";
    if (!$exists) {
        echo "  Path: $path\n";
    }
}
echo "\n";

// Test 2: Try to load env.php
echo "=== Test 2: Loading env.php ===\n";
try {
    require_once __DIR__ . '/includes/env.php';
    echo "✅ env.php loaded successfully\n";
} catch (Exception $e) {
    echo "❌ Error loading env.php: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Check if .env file can be loaded
echo "=== Test 3: Loading .env file ===\n";
try {
    $env = load_env();
    echo "✅ .env file loaded successfully\n";
    echo "Keys found: " . count($env) . "\n";
} catch (Exception $e) {
    echo "❌ Error loading .env: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Check Google OAuth credentials
echo "=== Test 4: Google OAuth Credentials ===\n";
try {
    require_once __DIR__ . '/includes/google-auth.php';
    echo "✅ google-auth.php loaded successfully\n";
    
    $creds = get_google_credentials();
    $hasClientId = !empty($creds['client_id']);
    $hasClientSecret = !empty($creds['client_secret']);
    
    echo "GOOGLE_CLIENT_ID: " . ($hasClientId ? "✅ SET" : "❌ MISSING") . "\n";
    if ($hasClientId) {
        echo "  Preview: " . substr($creds['client_id'], 0, 20) . "...\n";
    }
    
    echo "GOOGLE_CLIENT_SECRET: " . ($hasClientSecret ? "✅ SET" : "❌ MISSING") . "\n";
    if ($hasClientSecret) {
        echo "  Preview: " . substr($creds['client_secret'], 0, 10) . "...\n";
    }
    
    $isConfigured = is_google_oauth_configured();
    echo "OAuth Configured: " . ($isConfigured ? "✅ YES" : "❌ NO") . "\n";
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
echo "\n";

// Test 5: Check redirect URI generation
echo "=== Test 5: Redirect URI Generation ===\n";
try {
    $redirectUri = get_google_redirect_uri();
    echo "✅ Redirect URI generated: $redirectUri\n";
} catch (Exception $e) {
    echo "❌ Error generating redirect URI: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 6: Check database connection
echo "=== Test 6: Database Connection ===\n";
try {
    require_once __DIR__ . '/includes/auth.php';
    echo "✅ auth.php loaded successfully\n";
    
    $pdo = get_auth_db();
    if ($pdo) {
        echo "✅ Database connection successful\n";
    } else {
        echo "❌ Database connection returned null\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
echo "\n";

// Test 7: Check required functions
echo "=== Test 7: Required Functions ===\n";
$requiredFunctions = [
    'get_google_credentials',
    'is_google_oauth_configured',
    'get_google_redirect_uri',
    'verify_google_state',
    'exchange_google_code',
    'get_google_user_info',
    'find_or_create_google_user',
    'login_user',
    'get_auth_db'
];

foreach ($requiredFunctions as $func) {
    $exists = function_exists($func);
    echo "$func: " . ($exists ? "✅ EXISTS" : "❌ NOT FOUND") . "\n";
}
echo "\n";

// Test 8: Check session
echo "=== Test 8: Session ===\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "✅ Session started\n";
} else {
    echo "✅ Session already active\n";
}
echo "Session ID: " . session_id() . "\n";
echo "\n";

// Test 9: Check callback URL parameters
echo "=== Test 9: Callback Parameters ===\n";
echo "Code parameter: " . (isset($_GET['code']) ? "✅ PRESENT" : "❌ MISSING") . "\n";
if (isset($_GET['code'])) {
    echo "  Length: " . strlen($_GET['code']) . " characters\n";
}
echo "State parameter: " . (isset($_GET['state']) ? "✅ PRESENT" : "❌ MISSING") . "\n";
if (isset($_GET['state'])) {
    echo "  Value: " . substr($_GET['state'], 0, 20) . "...\n";
}
echo "\n";

// Test 10: Try to verify state (if state is present)
echo "=== Test 10: State Verification ===\n";
if (isset($_GET['state'])) {
    try {
        // Set a test state in session first
        $_SESSION['google_oauth_state'] = $_GET['state'];
        $isValid = verify_google_state($_GET['state']);
        echo "State verification: " . ($isValid ? "✅ VALID" : "❌ INVALID") . "\n";
        echo "Note: This test sets the state in session, so it should pass if state matches\n";
    } catch (Exception $e) {
        echo "❌ Error verifying state: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️  No state parameter to test\n";
}
echo "\n";

echo "</pre>";
echo "<p><strong>If you see any ❌ errors above, those are likely causing the HTTP 500 error.</strong></p>";
echo "<p>Check your PHP error logs for more details: <code>/home4/herbiecr/logs/error_log</code></p>";
