<?php
/**
 * Simple test to check if profile.php can be accessed
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../includes/auth.php';

// Require admin access
if (!is_admin()) {
    header('Location: ' . get_base_path() . '/index.php');
    exit;
}

echo "<h1>Profile Page Test</h1>";
echo "<pre>";

// Test 1: Check if files exist
echo "=== Test 1: File Existence ===\n";
$files = [
    'auth.php' => __DIR__ . '/../../includes/auth.php',
    'google-auth.php' => __DIR__ . '/../../includes/google-auth.php',
    'cards.php' => __DIR__ . '/../../includes/cards.php',
    'profile.php' => __DIR__ . '/../../profile.php'
];

foreach ($files as $name => $path) {
    $exists = file_exists($path);
    echo "$name: " . ($exists ? "✅ EXISTS" : "❌ NOT FOUND") . "\n";
    if (!$exists) {
        echo "  Path: $path\n";
    }
}
echo "\n";

// Test 2: Try to load auth.php
echo "=== Test 2: Loading auth.php ===\n";
try {
    require_once __DIR__ . '/../../includes/auth.php';
    echo "✅ auth.php loaded successfully\n";
} catch (Exception $e) {
    echo "❌ Error loading auth.php: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Check session
echo "=== Test 3: Session ===\n";
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    echo "✅ Session started\n";
} else {
    echo "✅ Session already active\n";
}
echo "Session ID: " . session_id() . "\n";
echo "\n";

// Test 4: Check if user is logged in
echo "=== Test 4: Authentication ===\n";
if (function_exists('is_logged_in')) {
    $isLoggedIn = is_logged_in();
    echo "Is logged in: " . ($isLoggedIn ? "✅ YES" : "❌ NO") . "\n";
    
    if ($isLoggedIn) {
        if (function_exists('get_logged_in_user')) {
            $user = get_logged_in_user();
            echo "User data: " . print_r($user, true) . "\n";
            
            if ($user && !empty($user['username'])) {
                if (function_exists('get_user_by_username')) {
                    $userFromDb = get_user_by_username($user['username']);
                    if ($userFromDb) {
                        echo "✅ User found in database\n";
                        echo "User ID: " . ($userFromDb['id'] ?? 'NOT SET') . "\n";
                    } else {
                        echo "❌ User NOT found in database\n";
                    }
                }
            }
        }
    } else {
        echo "⚠️  Not logged in - profile.php will redirect to login\n";
    }
} else {
    echo "❌ is_logged_in() function not found\n";
}
echo "\n";

// Test 5: Check required functions
echo "=== Test 5: Required Functions ===\n";
$requiredFunctions = [
    'get_logged_in_user',
    'is_logged_in',
    'get_user_by_username',
    'get_user_by_id',
    'get_user_cards',
    'get_user_cards_by_type',
    'has_google_linked',
    'change_password',
    'change_username'
];

foreach ($requiredFunctions as $func) {
    $exists = function_exists($func);
    echo "$func: " . ($exists ? "✅ EXISTS" : "❌ NOT FOUND") . "\n";
}
echo "\n";

// Test 6: Check database connection
echo "=== Test 6: Database Connection ===\n";
try {
    if (function_exists('get_auth_db')) {
        $pdo = get_auth_db();
        if ($pdo) {
            echo "✅ Database connection successful\n";
        } else {
            echo "❌ Database connection returned null\n";
        }
    } else {
        echo "❌ get_auth_db() function not found\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
echo "\n";

echo "</pre>";
echo "<p><strong>If you see any ❌ errors above, those are likely causing the issue.</strong></p>";
echo "<p><a href='../../profile.php'>Try accessing profile.php directly</a></p>";
