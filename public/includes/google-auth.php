<?php
/**
 * Google OAuth 2.0 Authentication
 * Lightweight implementation using cURL (no external dependencies)
 */

require_once __DIR__ . '/env.php';

/**
 * Get Google OAuth credentials from .env
 * @return array ['client_id' => string, 'client_secret' => string]
 */
function get_google_credentials(): array {
    $env = load_env();
    return [
        'client_id' => $env['GOOGLE_CLIENT_ID'] ?? '',
        'client_secret' => $env['GOOGLE_CLIENT_SECRET'] ?? ''
    ];
}

/**
 * Check if Google OAuth is configured
 * @return bool
 */
function is_google_oauth_configured(): bool {
    $creds = get_google_credentials();
    return !empty($creds['client_id']) && !empty($creds['client_secret']);
}

/**
 * Get Google OAuth authorization URL
 * @param string $state CSRF state token
 * @return string Authorization URL
 */
function get_google_auth_url(string $state): string {
    $creds = get_google_credentials();
    $redirectUri = get_google_redirect_uri();
    
    $params = [
        'client_id' => $creds['client_id'],
        'redirect_uri' => $redirectUri,
        'response_type' => 'code',
        'scope' => 'openid email profile',
        'state' => $state,
        'access_type' => 'online',
        'prompt' => 'select_account'
    ];
    
    return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
}

/**
 * Get Google OAuth redirect URI
 * @return string Full callback URL
 */
function get_google_redirect_uri(): string {
    require_once __DIR__ . '/env.php';
    $appUrl = get_app_url();
    if ($appUrl !== '') {
        return rtrim($appUrl, '/') . '/api/google-callback.php';
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = '';
    if (str_contains($host, 'herbiecreative.com')) {
        $base = '/cardobot';
    }
    return $protocol . '://' . $host . $base . '/api/google-callback.php';
}

/**
 * Exchange authorization code for access token
 * @param string $code Authorization code from Google
 * @return array ['success' => bool, 'access_token' => string|null, 'error' => string|null]
 */
function exchange_google_code(string $code): array {
    $creds = get_google_credentials();
    $redirectUri = get_google_redirect_uri();
    
    $data = [
        'code' => $code,
        'client_id' => $creds['client_id'],
        'client_secret' => $creds['client_secret'],
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code'
    ];
    
    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'access_token' => null, 'error' => 'cURL error: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error_description'] ?? $errorData['error'] ?? 'Unknown error';
        return ['success' => false, 'access_token' => null, 'error' => $errorMsg];
    }
    
    $tokenData = json_decode($response, true);
    if (!isset($tokenData['access_token'])) {
        return ['success' => false, 'access_token' => null, 'error' => 'No access token in response'];
    }
    
    return ['success' => true, 'access_token' => $tokenData['access_token'], 'error' => null];
}

/**
 * Get user info from Google using access token
 * @param string $accessToken
 * @return array ['success' => bool, 'user' => array|null, 'error' => string|null]
 */
function get_google_user_info(string $accessToken): array {
    $ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        return ['success' => false, 'user' => null, 'error' => 'cURL error: ' . $curlError];
    }
    
    if ($httpCode !== 200) {
        $errorData = json_decode($response, true);
        $errorMsg = $errorData['error']['message'] ?? 'Unknown error';
        return ['success' => false, 'user' => null, 'error' => $errorMsg];
    }
    
    $userData = json_decode($response, true);
    if (!isset($userData['id'])) {
        return ['success' => false, 'user' => null, 'error' => 'Invalid user data from Google'];
    }
    
    return ['success' => true, 'user' => $userData, 'error' => null];
}

/**
 * Generate CSRF state token
 * @return string
 */
function generate_google_state(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $state = bin2hex(random_bytes(16));
    $_SESSION['google_oauth_state'] = $state;
    return $state;
}

/**
 * Verify CSRF state token
 * @param string $state
 * @return bool
 */
function verify_google_state(string $state): bool {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $storedState = $_SESSION['google_oauth_state'] ?? '';
    unset($_SESSION['google_oauth_state']); // Use once
    return hash_equals($storedState, $state);
}

/**
 * Link Google account to existing user account
 * @param int $userId
 * @param array $googleUser Google user data
 * @return array ['success' => bool, 'message' => string]
 */
function link_google_account(int $userId, array $googleUser): array {
    require_once __DIR__ . '/auth.php';
    
    $googleId = $googleUser['id'] ?? '';
    $email = $googleUser['email'] ?? '';
    $name = $googleUser['name'] ?? '';
    $givenName = $googleUser['given_name'] ?? '';
    $familyName = $googleUser['family_name'] ?? '';
    $picture = $googleUser['picture'] ?? '';
    
    if (empty($googleId)) {
        return ['success' => false, 'message' => 'Invalid Google user data'];
    }
    
    // Check if Google ID is already linked to another account
    $existingUser = get_user_by_google_id($googleId);
    if ($existingUser && $existingUser['id'] != $userId) {
        return ['success' => false, 'message' => 'This Google account is already linked to another user.'];
    }
    
    $pdo = get_auth_db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        // Update user with Google info
        $updateFields = ['google_id = ?'];
        $updateParams = [$googleId];
        
        if (!empty($email)) {
            $updateFields[] = 'email = ?';
            $updateParams[] = $email;
        }
        if (!empty($name)) {
            $updateFields[] = 'name = ?';
            $updateParams[] = $name;
        }
        if (!empty($givenName)) {
            $updateFields[] = 'given_name = ?';
            $updateParams[] = $givenName;
        }
        if (!empty($familyName)) {
            $updateFields[] = 'family_name = ?';
            $updateParams[] = $familyName;
        }
        if (!empty($picture)) {
            $updateFields[] = 'picture = ?';
            $updateParams[] = $picture;
        }
        
        // Update auth_method if user doesn't have password
        $user = get_user_by_id($userId);
        if ($user && empty($user['password_hash'])) {
            $updateFields[] = 'auth_method = ?';
            $updateParams[] = 'google';
        } elseif ($user && !empty($user['password_hash'])) {
            // User has both password and Google - keep auth_method as 'password' but allow Google login
            // We could add a 'both' option, but for now we'll allow both methods
        }
        
        $updateParams[] = $userId;
        $stmt = $pdo->prepare("UPDATE cardobot_users SET " . implode(', ', $updateFields) . " WHERE id = ?");
        $stmt->execute($updateParams);
        
        return ['success' => true, 'message' => 'Google account linked successfully!'];
    } catch (PDOException $e) {
        error_log("Error linking Google account: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to link Google account'];
    }
}

/**
 * Unlink Google account from user
 * @param int $userId
 * @return array ['success' => bool, 'message' => string]
 */
function unlink_google_account(int $userId): array {
    require_once __DIR__ . '/auth.php';
    
    $pdo = get_auth_db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        $user = get_user_by_id($userId);
        if (!$user) {
            return ['success' => false, 'message' => 'User not found'];
        }
        
        // Don't allow unlinking if it's the only auth method
        if (empty($user['password_hash']) && !empty($user['google_id'])) {
            return ['success' => false, 'message' => 'Cannot unlink Google account. Please set a password first in account settings.'];
        }
        
        $stmt = $pdo->prepare("UPDATE cardobot_users SET google_id = NULL, auth_method = 'password' WHERE id = ?");
        $stmt->execute([$userId]);
        
        return ['success' => true, 'message' => 'Google account unlinked successfully'];
    } catch (PDOException $e) {
        error_log("Error unlinking Google account: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to unlink Google account'];
    }
}

/**
 * Find or create user from Google account
 * @param array $googleUser Google user data
 * @return array ['success' => bool, 'user' => array|null, 'message' => string]
 */
function find_or_create_google_user(array $googleUser): array {
    require_once __DIR__ . '/auth.php';
    
    $googleId = $googleUser['id'] ?? '';
    $email = $googleUser['email'] ?? '';
    $name = $googleUser['name'] ?? '';
    $givenName = $googleUser['given_name'] ?? '';
    $familyName = $googleUser['family_name'] ?? '';
    $picture = $googleUser['picture'] ?? '';
    
    if (empty($googleId)) {
        return ['success' => false, 'user' => null, 'message' => 'Invalid Google user data'];
    }
    
    // Generate username from email or name
    $username = '';
    if (!empty($email)) {
        $username = strtolower(explode('@', $email)[0]);
        // Sanitize username
        $username = preg_replace('/[^a-z0-9_-]/', '', $username);
        if (strlen($username) < 3) {
            $username = 'user' . substr(md5($email), 0, 8);
        }
    } else {
        $username = 'user' . substr($googleId, 0, 12);
    }
    
    // Check if Google ID already exists in database
    $existingUser = get_user_by_google_id($googleId);
    
    if ($existingUser) {
        // This user already exists, update last login and profile info
        $pdo = get_auth_db();
        if ($pdo) {
            try {
                $updateFields = ['last_login = NOW()'];
                $updateParams = [];
                
                if (!empty($email) && empty($existingUser['email'])) {
                    $updateFields[] = 'email = ?';
                    $updateParams[] = $email;
                }
                if (!empty($picture) && empty($existingUser['picture'])) {
                    $updateFields[] = 'picture = ?';
                    $updateParams[] = $picture;
                }
                
                if (count($updateParams) > 0) {
                    $updateParams[] = $existingUser['id'];
                    $stmt = $pdo->prepare("UPDATE cardobot_users SET " . implode(', ', $updateFields) . " WHERE id = ?");
                    $stmt->execute($updateParams);
                } else {
                    $stmt = $pdo->prepare("UPDATE cardobot_users SET last_login = NOW() WHERE id = ?");
                    $stmt->execute([$existingUser['id']]);
                }
            } catch (PDOException $e) {
                error_log("Error updating Google user: " . $e->getMessage());
            }
        }
        
        unset($existingUser['password_hash']);
        return ['success' => true, 'user' => $existingUser, 'message' => 'Welcome back!'];
    }
    
    // Check if email matches an existing account (for account linking)
    if (!empty($email)) {
        $existingUserByEmail = get_user_by_email($email);
        if ($existingUserByEmail && empty($existingUserByEmail['google_id'])) {
            // Email matches existing account without Google link
            // Store Google info in session for linking confirmation
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['pending_google_link'] = [
                'google_id' => $googleId,
                'email' => $email,
                'name' => $name,
                'given_name' => $givenName,
                'family_name' => $familyName,
                'picture' => $picture,
                'existing_user_id' => $existingUserByEmail['id'],
                'existing_username' => $existingUserByEmail['username']
            ];
            return [
                'success' => false,
                'user' => null,
                'message' => 'account_linking_required',
                'existing_username' => $existingUserByEmail['username']
            ];
        } elseif ($existingUserByEmail && !empty($existingUserByEmail['google_id'])) {
            // Email matches but Google ID is different - this shouldn't happen, but handle it
            return [
                'success' => false,
                'user' => null,
                'message' => 'This email is already linked to a different Google account.'
            ];
        }
    }
    
    // Ensure username is unique for new user
    $originalUsername = $username;
    $counter = 1;
    while (username_exists($username)) {
        $username = $originalUsername . $counter;
        $counter++;
    }
    
    // Create new user in database
    $pdo = get_auth_db();
    if (!$pdo) {
        return ['success' => false, 'user' => null, 'message' => 'Database connection failed'];
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO cardobot_users 
            (username, google_id, email, name, given_name, family_name, picture, auth_method, created_at, last_login)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'google', NOW(), NOW())
        ");
        
        $stmt->execute([
            $username,
            $googleId,
            $email,
            $name,
            $givenName,
            $familyName,
            $picture
        ]);
        
        // Get the created user
        $newUser = get_user_by_username($username);
        
        if ($newUser) {
            // Create user directory
            get_user_dir($username);
            unset($newUser['password_hash']);
            return ['success' => true, 'user' => $newUser, 'message' => 'Account created successfully!'];
        } else {
            return ['success' => false, 'user' => null, 'message' => 'Failed to retrieve created user'];
        }
    } catch (PDOException $e) {
        error_log("Error creating Google user: " . $e->getMessage());
        return ['success' => false, 'user' => null, 'message' => 'Failed to create user account'];
    }
}
