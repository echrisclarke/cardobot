<?php
/**
 * Authentication System Test Page
 * Tests login, logout, session management, and Google OAuth configuration
 */

require_once __DIR__ . '/includes/auth.php';

// Try to load Google auth, but don't fail if it doesn't exist
$googleAuthAvailable = false;
if (file_exists(__DIR__ . '/includes/google-auth.php')) {
    try {
        require_once __DIR__ . '/includes/google-auth.php';
        $googleAuthAvailable = true;
    } catch (Exception $e) {
        // Google auth not available, continue without it
    }
}

header('Content-Type: application/json; charset=utf-8');

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// Test 1: Session functionality
try {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $results['tests']['session'] = [
            'status' => 'pass',
            'message' => 'Session is active',
            'session_id' => session_id()
        ];
    } else {
        throw new Exception('Session is not active');
    }
} catch (Exception $e) {
    $results['tests']['session'] = [
        'status' => 'fail',
        'message' => $e->getMessage()
    ];
}

// Test 2: Current login status
try {
    $isLoggedIn = is_logged_in();
    $currentUser = get_logged_in_user();
    $username = get_username();
    
    $results['tests']['login_status'] = [
        'status' => 'pass',
        'message' => $isLoggedIn ? 'User is logged in' : 'User is not logged in',
        'is_logged_in' => $isLoggedIn,
        'username' => $username,
        'user_data' => $currentUser
    ];
} catch (Exception $e) {
    $results['tests']['login_status'] = [
        'status' => 'fail',
        'message' => $e->getMessage()
    ];
}

// Test 3: Database connection and user storage
try {
    require_once __DIR__ . '/includes/env.php';
    $pdo = get_db_connection();
    
    if ($pdo) {
        // Check if users table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'cardobot_users'");
        $tableExists = $stmt->rowCount() > 0;
        
        if ($tableExists) {
            // Get user count
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM cardobot_users");
            $userCount = $stmt->fetch()['count'];
            
            // Test database operations
            $testUsername = 'test_user_' . time();
            $testUser = get_user_by_username($testUsername);
            $canRead = ($testUser === null); // Should return null for non-existent user
            
            $results['tests']['user_storage'] = [
                'status' => 'pass',
                'message' => 'Database connection successful and users table accessible',
                'table_exists' => true,
                'user_count' => $userCount,
                'can_read' => $canRead,
                'storage_type' => 'database'
            ];
        } else {
            $results['tests']['user_storage'] = [
                'status' => 'fail',
                'message' => 'Database connected but cardobot_users table does not exist',
                'table_exists' => false,
                'storage_type' => 'database'
            ];
        }
    } else {
        $results['tests']['user_storage'] = [
            'status' => 'fail',
            'message' => 'Database connection failed',
            'storage_type' => 'database'
        ];
    }
} catch (Exception $e) {
    $results['tests']['user_storage'] = [
        'status' => 'fail',
        'message' => $e->getMessage(),
        'storage_type' => 'database'
    ];
}

// Test 4: User directory creation
try {
    $testUsername = 'test_user_' . time();
    $userDir = get_user_dir($testUsername);
    $dirExists = is_dir($userDir);
    
    // Clean up test directory
    if ($dirExists && strpos($userDir, 'test_user_') !== false) {
        @rmdir($userDir);
    }
    
    $results['tests']['user_directory'] = [
        'status' => $dirExists ? 'pass' : 'fail',
        'message' => $dirExists 
            ? 'User directory can be created'
            : 'Failed to create user directory',
        'test_dir' => $userDir,
        'created' => $dirExists
    ];
} catch (Exception $e) {
    $results['tests']['user_directory'] = [
        'status' => 'fail',
        'message' => $e->getMessage()
    ];
}

// Test 5: Google OAuth configuration
if ($googleAuthAvailable) {
    try {
        $googleConfigured = is_google_oauth_configured();
        $creds = get_google_credentials();
        $hasClientId = !empty($creds['client_id']);
        $hasClientSecret = !empty($creds['client_secret']);
        
        $results['tests']['google_oauth'] = [
            'status' => $googleConfigured ? 'pass' : 'warning',
            'message' => $googleConfigured 
                ? 'Google OAuth is configured'
                : 'Google OAuth is not configured (optional feature)',
            'configured' => $googleConfigured,
            'has_client_id' => $hasClientId,
            'has_client_secret' => $hasClientSecret,
            'redirect_uri' => $googleConfigured ? get_google_redirect_uri() : null
        ];
    } catch (Exception $e) {
        $results['tests']['google_oauth'] = [
            'status' => 'fail',
            'message' => $e->getMessage()
        ];
    }
} else {
    $results['tests']['google_oauth'] = [
        'status' => 'skip',
        'message' => 'Google OAuth module not available (optional feature)'
    ];
}

// Test 6: Username validation
try {
    $validUsernames = ['testuser', 'user123', 'test_user', 'user-name'];
    $invalidUsernames = ['ab', 'user@name', 'user name', 'user.name'];
    
    $validResults = [];
    $invalidResults = [];
    
    foreach ($validUsernames as $username) {
        $validResults[$username] = is_valid_username($username);
    }
    
    foreach ($invalidUsernames as $username) {
        $invalidResults[$username] = is_valid_username($username);
    }
    
    $allValid = !in_array(false, $validResults);
    $allInvalid = !in_array(true, $invalidResults);
    
    $results['tests']['username_validation'] = [
        'status' => ($allValid && $allInvalid) ? 'pass' : 'fail',
        'message' => ($allValid && $allInvalid)
            ? 'Username validation works correctly'
            : 'Username validation has issues',
        'valid_usernames' => $validResults,
        'invalid_usernames' => $invalidResults
    ];
} catch (Exception $e) {
    $results['tests']['username_validation'] = [
        'status' => 'fail',
        'message' => $e->getMessage()
    ];
}

// Calculate overall status
$allPassed = true;
$hasWarnings = false;
foreach ($results['tests'] as $test) {
    if ($test['status'] === 'fail') {
        $allPassed = false;
    }
    if ($test['status'] === 'warning') {
        $hasWarnings = true;
    }
}

$results['overall'] = $allPassed ? ($hasWarnings ? 'warning' : 'pass') : 'fail';
$results['summary'] = $allPassed 
    ? ($hasWarnings 
        ? 'All critical tests passed! Some optional features are not configured.' 
        : 'All tests passed! Authentication system is ready.')
    : 'Some tests failed. Check individual test results.';

// Set HTTP status code
http_response_code($allPassed ? 200 : 500);

echo json_encode($results, JSON_PRETTY_PRINT);
