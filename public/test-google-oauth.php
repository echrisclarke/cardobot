<?php
/**
 * Google OAuth Configuration Test
 * Tests Google OAuth setup and configuration
 */

header('Content-Type: application/json; charset=utf-8');

$results = [
    'timestamp' => date('Y-m-d H:i:s'),
    'tests' => []
];

// Check if Google auth file exists
$googleAuthFile = __DIR__ . '/includes/google-auth.php';
$googleAuthAvailable = file_exists($googleAuthFile);

if (!$googleAuthAvailable) {
    $results['tests']['google_auth_file'] = [
        'status' => 'fail',
        'message' => 'Google OAuth module not found',
        'expected_path' => $googleAuthFile
    ];
    $results['overall'] = 'fail';
    $results['summary'] = 'Google OAuth module not available';
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit;
}

$results['tests']['google_auth_file'] = [
    'status' => 'pass',
    'message' => 'Google OAuth module found'
];

// Load Google auth functions
try {
    require_once $googleAuthFile;
} catch (Exception $e) {
    $results['tests']['google_auth_load'] = [
        'status' => 'fail',
        'message' => 'Failed to load Google OAuth module: ' . $e->getMessage()
    ];
    $results['overall'] = 'fail';
    $results['summary'] = 'Failed to load Google OAuth module';
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit;
}

$results['tests']['google_auth_load'] = [
    'status' => 'pass',
    'message' => 'Google OAuth module loaded successfully'
];

// Test 1: Check if credentials are configured
try {
    $creds = get_google_credentials();
    $hasClientId = !empty($creds['client_id']);
    $hasClientSecret = !empty($creds['client_secret']);
    $isConfigured = is_google_oauth_configured();
    
    $results['tests']['credentials'] = [
        'status' => $isConfigured ? 'pass' : 'fail',
        'message' => $isConfigured 
            ? 'Google OAuth credentials are configured'
            : 'Google OAuth credentials are missing or incomplete',
        'has_client_id' => $hasClientId,
        'has_client_secret' => $hasClientSecret,
        'client_id_preview' => $hasClientId ? substr($creds['client_id'], 0, 20) . '...' : null,
        'client_secret_preview' => $hasClientSecret ? substr($creds['client_secret'], 0, 10) . '...' : null,
        'configured' => $isConfigured
    ];
} catch (Exception $e) {
    $results['tests']['credentials'] = [
        'status' => 'fail',
        'message' => 'Error checking credentials: ' . $e->getMessage()
    ];
}

// Test 2: Check redirect URI generation
try {
    $redirectUri = get_google_redirect_uri();
    $host = $_SERVER['HTTP_HOST'] ?? 'unknown';
    $expectedUris = [
        'cardobot.com' => 'https://cardobot.com/api/google-callback.php',
        'www.cardobot.com' => 'https://www.cardobot.com/api/google-callback.php',
        'herbiecreative.com' => 'https://herbiecreative.com/cardobot/api/google-callback.php'
    ];
    
    $isValid = false;
    $expectedUri = null;
    
    if ($host === 'cardobot.com' || $host === 'www.cardobot.com') {
        $expectedUri = $expectedUris[$host] ?? $expectedUris['cardobot.com'];
        $isValid = ($redirectUri === $expectedUri);
    } else {
        $expectedUri = $expectedUris['herbiecreative.com'];
        $isValid = ($redirectUri === $expectedUri || strpos($redirectUri, '/cardobot/api/google-callback.php') !== false);
    }
    
    $results['tests']['redirect_uri'] = [
        'status' => $isValid ? 'pass' : 'warning',
        'message' => $isValid 
            ? 'Redirect URI generated correctly for current domain'
            : 'Redirect URI may not match expected format',
        'current_host' => $host,
        'generated_uri' => $redirectUri,
        'expected_format' => $host === 'cardobot.com' || $host === 'www.cardobot.com'
            ? 'https://' . $host . '/api/google-callback.php'
            : 'https://herbiecreative.com/cardobot/api/google-callback.php',
        'is_https' => strpos($redirectUri, 'https://') === 0
    ];
} catch (Exception $e) {
    $results['tests']['redirect_uri'] = [
        'status' => 'fail',
        'message' => 'Error generating redirect URI: ' . $e->getMessage()
    ];
}

// Test 3: Check if state generation works
try {
    $state = generate_google_state();
    $isValidState = !empty($state) && strlen($state) >= 32;
    
    $results['tests']['state_generation'] = [
        'status' => $isValidState ? 'pass' : 'fail',
        'message' => $isValidState 
            ? 'State token generation works'
            : 'State token generation failed',
        'state_length' => strlen($state),
        'state_preview' => substr($state, 0, 10) . '...'
    ];
} catch (Exception $e) {
    $results['tests']['state_generation'] = [
        'status' => 'fail',
        'message' => 'Error generating state: ' . $e->getMessage()
    ];
}

// Test 4: Check if auth URL generation works
try {
    if (is_google_oauth_configured()) {
        $state = generate_google_state();
        $authUrl = get_google_auth_url($state);
        $hasClientId = strpos($authUrl, 'client_id=') !== false;
        $hasRedirectUri = strpos($authUrl, 'redirect_uri=') !== false;
        $hasState = strpos($authUrl, 'state=') !== false;
        $hasScopes = strpos($authUrl, 'scope=') !== false;
        
        $results['tests']['auth_url'] = [
            'status' => ($hasClientId && $hasRedirectUri && $hasState && $hasScopes) ? 'pass' : 'fail',
            'message' => ($hasClientId && $hasRedirectUri && $hasState && $hasScopes)
                ? 'Authorization URL generation works'
                : 'Authorization URL is missing required parameters',
            'url_preview' => substr($authUrl, 0, 80) . '...',
            'has_client_id' => $hasClientId,
            'has_redirect_uri' => $hasRedirectUri,
            'has_state' => $hasState,
            'has_scopes' => $hasScopes
        ];
    } else {
        $results['tests']['auth_url'] = [
            'status' => 'skip',
            'message' => 'Skipped - OAuth not configured'
        ];
    }
} catch (Exception $e) {
    $results['tests']['auth_url'] = [
        'status' => 'fail',
        'message' => 'Error generating auth URL: ' . $e->getMessage()
    ];
}

// Test 5: Verify callback file exists
try {
    $callbackFile = __DIR__ . '/api/google-callback.php';
    $callbackExists = file_exists($callbackFile);
    
    $results['tests']['callback_file'] = [
        'status' => $callbackExists ? 'pass' : 'fail',
        'message' => $callbackExists 
            ? 'Google OAuth callback file exists'
            : 'Google OAuth callback file not found',
        'file_path' => $callbackFile,
        'exists' => $callbackExists
    ];
} catch (Exception $e) {
    $results['tests']['callback_file'] = [
        'status' => 'fail',
        'message' => 'Error checking callback file: ' . $e->getMessage()
    ];
}

// Calculate overall status
$allPassed = true;
$hasWarnings = false;
$hasSkips = false;

foreach ($results['tests'] as $test) {
    if ($test['status'] === 'fail') {
        $allPassed = false;
    }
    if ($test['status'] === 'warning') {
        $hasWarnings = true;
    }
    if ($test['status'] === 'skip') {
        $hasSkips = true;
    }
}

$results['overall'] = $allPassed ? ($hasWarnings ? 'warning' : 'pass') : 'fail';
$results['summary'] = $allPassed 
    ? ($hasWarnings 
        ? 'Google OAuth is configured! Some warnings detected.'
        : 'Google OAuth is fully configured and ready to use!')
    : 'Google OAuth configuration has issues. Check individual test results.';

// Set HTTP status code
http_response_code($allPassed ? 200 : 500);

echo json_encode($results, JSON_PRETTY_PRINT);
