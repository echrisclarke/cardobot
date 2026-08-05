<?php
/**
 * Authentication System for Card-o-Bot
 * Handles user login, logout, and session management
 */

require_once __DIR__ . '/api.php';

/**
 * Ensure a PHP session is available.
 * Pages/login may create; pass false to only resume (APIs).
 */
function auth_boot(bool $create = true): void {
    api_boot($create);
}

/**
 * Get the current logged-in user
 * @return array|null User data or null if not logged in
 */
function get_logged_in_user(): ?array {
    if (session_status() === PHP_SESSION_NONE) {
        auth_boot(false);
    }
    if (session_status() === PHP_SESSION_NONE || !isset($_SESSION['user'])) {
        return null;
    }
    return $_SESSION['user'];
}

/**
 * Check if user is logged in
 * @return bool
 */
function is_logged_in(): bool {
    if (session_status() === PHP_SESSION_NONE) {
        auth_boot(false);
    }
    if (session_status() === PHP_SESSION_NONE) {
        return false;
    }
    return isset($_SESSION['user']) && !empty($_SESSION['user']['username']);
}

/**
 * Get current username
 * @return string|null Username or null if not logged in
 */
function get_username(): ?string {
    $user = get_logged_in_user();
    return $user['username'] ?? null;
}

/**
 * Get base path for redirects based on current domain
 * @return string Base path (empty for cardobot.com, '/cardobot' for herbiecreative.com)
 */
function get_base_path(): string {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    // Standalone deploy (Railway, cardobot.com apex, localhost): app at domain root
    if ($host === 'cardobot.com' || $host === 'www.cardobot.com') {
        return '';
    }
    if (str_contains($host, 'railway.app') || str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
        return '';
    }
    // Legacy: herbiecreative.com/cardobot subdirectory
    if (str_contains($host, 'herbiecreative.com')) {
        return '/cardobot';
    }
    // Default: root (standalone repo)
    return '';
}

/**
 * Get asset path (for CSS, JS, images) based on current domain
 * @return string Asset base path (empty for cardobot.com, '/cardobot' for herbiecreative.com)
 */
function get_asset_path(): string {
    return get_base_path();
}

/**
 * Require authentication - redirect to login if not logged in
 * @param string $redirectTo Optional redirect URL after login
 */
function require_auth(string $redirectTo = ''): void {
    auth_boot(true);
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        $basePath = get_base_path();
        $redirect = !empty($redirectTo) ? '?redirect=' . urlencode($redirectTo) : '';
        header('Location: ' . $basePath . '/login.php' . $redirect);
        exit;
    }
}

/**
 * Get user data directory path
 * @param string $username
 * @return string Full path to user directory
 */
function get_user_dir(string $username): string {
    $baseDir = __DIR__ . '/../user-cards';
    $userDir = $baseDir . '/' . sanitize_username($username);
    
    // Create directory if it doesn't exist
    if (!is_dir($userDir)) {
        mkdir($userDir, 0755, true);
    }
    
    return $userDir;
}

/**
 * Sanitize username for filesystem use
 * @param string $username
 * @return string Sanitized username
 */
function sanitize_username(string $username): string {
    // Remove any path traversal attempts
    $username = basename($username);
    // Only allow alphanumeric, underscore, and hyphen
    $username = preg_replace('/[^a-zA-Z0-9_-]/', '', $username);
    // Limit length
    return substr($username, 0, 50);
}

/**
 * Validate username format
 * @param string $username
 * @return bool
 */
function is_valid_username(string $username): bool {
    // 3-50 characters, alphanumeric, underscore, hyphen only
    return preg_match('/^[a-zA-Z0-9_-]{3,50}$/', $username) === 1;
}

/**
 * Validate password format
 * @param string $password
 * @return bool
 */
function is_valid_password(string $password): bool {
    // At least 4 characters (simple requirement)
    return strlen($password) >= 4;
}

/**
 * Get database connection (requires env.php)
 * @return PDO|null
 */
function get_auth_db(): ?PDO {
    require_once __DIR__ . '/env.php';
    return get_db_connection();
}

/**
 * Get user by ID from database
 * @param int $userId
 * @return array|null User data or null if not found
 */
function get_user_by_id(int $userId): ?array {
    $pdo = get_auth_db();
    if (!$pdo) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM cardobot_users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (PDOException $e) {
        error_log("Error getting user by ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user by username from database
 * @param string $username
 * @return array|null User data or null if not found
 */
function get_user_by_username(string $username): ?array {
    $pdo = get_auth_db();
    if (!$pdo) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM cardobot_users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (PDOException $e) {
        error_log("Error getting user by username: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user by Google ID from database
 * @param string $googleId
 * @return array|null User data or null if not found
 */
function get_user_by_google_id(string $googleId): ?array {
    $pdo = get_auth_db();
    if (!$pdo) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT * FROM cardobot_users WHERE google_id = ?");
        $stmt->execute([$googleId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (PDOException $e) {
        error_log("Error getting user by Google ID: " . $e->getMessage());
        return null;
    }
}

/**
 * Get user by email from database
 * @param string $email
 * @return array|null User data or null if not found
 */
function get_user_by_email(string $email): ?array {
    $email = trim($email);
    if ($email === '') {
        return null;
    }

    $pdo = get_auth_db();
    if (!$pdo) {
        return null;
    }

    try {
        $stmt = $pdo->prepare("SELECT * FROM cardobot_users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return $user ?: null;
    } catch (PDOException $e) {
        error_log("Error getting user by email: " . $e->getMessage());
        return null;
    }
}

/**
 * Check if username exists in database
 * @param string $username
 * @return bool
 */
function username_exists(string $username): bool {
    $user = get_user_by_username($username);
    return $user !== null;
}

/**
 * Check if user has Google account linked
 * @param int $userId
 * @return bool
 */
function has_google_linked(int $userId): bool {
    $pdo = get_auth_db();
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT google_id FROM cardobot_users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        return !empty($user['google_id'] ?? '');
    } catch (PDOException $e) {
        error_log("Error checking Google link: " . $e->getMessage());
        return false;
    }
}

/**
 * Change user password
 * @param int $userId
 * @param string $currentPassword (can be empty if user doesn't have a password)
 * @param string $newPassword
 * @return array ['success' => bool, 'message' => string]
 */
function change_password(int $userId, string $currentPassword, string $newPassword): array {
    $user = get_user_by_id($userId);
    if (!$user) {
        return ['success' => false, 'message' => 'User not found'];
    }
    
    // If user has a password, verify current password
    if (!empty($user['password_hash'])) {
        if (empty($currentPassword)) {
            return ['success' => false, 'message' => 'Current password is required'];
        }
        if (!password_verify($currentPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
    }
    // If user doesn't have a password, currentPassword can be empty (setting first password)
    
    // Validate new password
    if (!is_valid_password($newPassword)) {
        return ['success' => false, 'message' => 'Password must be at least 4 characters long'];
    }
    
    // Update password
    $pdo = get_auth_db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
        // If user has Google linked, keep auth_method flexible, otherwise set to 'password'
        $authMethod = (!empty($user['google_id'])) ? $user['auth_method'] : 'password';
        $stmt = $pdo->prepare("UPDATE cardobot_users SET password_hash = ?, auth_method = ? WHERE id = ?");
        $stmt->execute([$newHash, $authMethod, $userId]);
        return ['success' => true, 'message' => 'Password ' . (empty($user['password_hash']) ? 'set' : 'changed') . ' successfully'];
    } catch (PDOException $e) {
        error_log("Error changing password: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to change password'];
    }
}

/**
 * Change username
 * @param int $userId
 * @param string $newUsername
 * @return array ['success' => bool, 'message' => string]
 */
function change_username(int $userId, string $newUsername): array {
    // Validate username
    if (!is_valid_username($newUsername)) {
        return ['success' => false, 'message' => 'Username must be 3-50 characters and contain only letters, numbers, underscores, and hyphens.'];
    }
    
    // Check if username already exists (excluding current user)
    $existingUser = get_user_by_username($newUsername);
    if ($existingUser && $existingUser['id'] != $userId) {
        return ['success' => false, 'message' => 'Username is already taken'];
    }
    
    // Update username
    $pdo = get_auth_db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed'];
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE cardobot_users SET username = ? WHERE id = ?");
        $stmt->execute([$newUsername, $userId]);
        
        // Update session
        if (isset($_SESSION['user'])) {
            $_SESSION['user']['username'] = $newUsername;
        }
        
        return ['success' => true, 'message' => 'Username changed successfully'];
    } catch (PDOException $e) {
        error_log("Error changing username: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to change username'];
    }
}

/**
 * Create a new user account
 * @param string $username
 * @param string $password
 * @return array ['success' => bool, 'message' => string]
 */
function create_user(string $username, string $password): array {
    // Validate input
    if (!is_valid_username($username)) {
        return ['success' => false, 'message' => 'Username must be 3-50 characters and contain only letters, numbers, underscores, and hyphens.'];
    }
    
    if (!is_valid_password($password)) {
        return ['success' => false, 'message' => 'Password must be at least 4 characters long.'];
    }
    
    $pdo = get_auth_db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed.'];
    }
    
    // Check if username already exists
    if (username_exists($username)) {
        return ['success' => false, 'message' => 'Username already exists.'];
    }
    
    try {
        // Insert new user
        $stmt = $pdo->prepare("
            INSERT INTO cardobot_users 
            (username, password_hash, auth_method, created_at)
            VALUES (?, ?, 'password', NOW())
        ");
        
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $stmt->execute([$username, $passwordHash]);
        
        // Create user directory
        get_user_dir($username);
        
        return ['success' => true, 'message' => 'Account created successfully!'];
    } catch (PDOException $e) {
        error_log("Error creating user: " . $e->getMessage());
        return ['success' => false, 'message' => 'Failed to create user account.'];
    }
}

/**
 * Authenticate user login
 * @param string $username
 * @param string $password
 * @return array ['success' => bool, 'message' => string, 'user' => array|null]
 */
function authenticate_user(string $username, string $password): array {
    if (empty($password)) {
        return ['success' => false, 'message' => 'Password is required.', 'user' => null];
    }
    
    // Check admin credentials first (from .env)
    require_once __DIR__ . '/env.php';
    $adminUsername = get_admin_username();
    $adminPassword = get_admin_password();
    
    if (!empty($adminUsername) && !empty($adminPassword)) {
        if ($username === $adminUsername && $password === $adminPassword) {
            // Admin login successful
            return [
                'success' => true,
                'message' => 'Admin login successful!',
                'user' => [
                    'username' => $adminUsername,
                    'is_admin' => true,
                    'created' => date('Y-m-d H:i:s'),
                    'last_login' => date('Y-m-d H:i:s')
                ]
            ];
        }
    }
    
    // Validate username format for regular users
    if (!is_valid_username($username)) {
        return ['success' => false, 'message' => 'Invalid username format.', 'user' => null];
    }
    
    $pdo = get_auth_db();
    if (!$pdo) {
        return ['success' => false, 'message' => 'Database connection failed.', 'user' => null];
    }
    
    // Get user from database
    $user = get_user_by_username($username);
    
    // Check if user exists
    if (!$user) {
        return ['success' => false, 'message' => 'Invalid username or password.', 'user' => null];
    }
    
    // Verify password
    if (empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
        return ['success' => false, 'message' => 'Invalid username or password.', 'user' => null];
    }
    
    // Update last login
    try {
        $stmt = $pdo->prepare("UPDATE cardobot_users SET last_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
    } catch (PDOException $e) {
        error_log("Error updating last login: " . $e->getMessage());
        // Don't fail login if update fails
    }
    
    // Return user data (without password hash)
    unset($user['password_hash']);
    
    return ['success' => true, 'message' => 'Login successful!', 'user' => $user];
}

/**
 * Dedicated pipeline test account (always starts a fresh Cardy session on login).
 */
function cardobot_test_username(): string {
    require_once __DIR__ . '/env.php';
    $env = load_env();
    $name = trim((string)($env['TEST_USERNAME'] ?? ''));
    return $name !== '' ? $name : 'test';
}

function cardobot_test_password(): string {
    require_once __DIR__ . '/env.php';
    $env = load_env();
    $pass = (string)($env['TEST_PASSWORD'] ?? '');
    return $pass !== '' ? $pass : 'test1234';
}

function cardobot_is_test_user(string $username): bool {
    return strcasecmp(trim($username), cardobot_test_username()) === 0;
}

/**
 * Ensure the always-fresh test account exists and password stays known.
 */
function cardobot_ensure_test_user(): void {
    $username = cardobot_test_username();
    $password = cardobot_test_password();
    if ($username === '' || !is_valid_username($username) || !is_valid_password($password)) {
        return;
    }
    $pdo = get_auth_db();
    if (!$pdo) {
        error_log('cardobot_ensure_test_user: no DB connection');
        return;
    }
    $hash = password_hash($password, PASSWORD_DEFAULT);
    try {
        if (username_exists($username)) {
            $stmt = $pdo->prepare(
                "UPDATE cardobot_users
                 SET password_hash = ?, auth_method = 'password'
                 WHERE username = ?"
            );
            $stmt->execute([$hash, $username]);
            return;
        }
        $stmt = $pdo->prepare(
            "INSERT INTO cardobot_users (username, password_hash, auth_method, created_at)
             VALUES (?, ?, 'password', NOW())"
        );
        $stmt->execute([$username, $hash]);
        get_user_dir($username);
    } catch (Throwable $e) {
        error_log('cardobot_ensure_test_user: ' . $e->getMessage());
    }
}

/**
 * Wipe PHP (+ DB if available) Cardy chat state for the always-fresh test account.
 * Also resets language so a prior Chinese/Spanish preference cannot sticky-lock the pipeline.
 */
function cardobot_wipe_test_user_chat(int $userId): void {
    $_SESSION['cardobot_sessions'] = [];
    unset($_SESSION['cardobot_locale']);

    require_once __DIR__ . '/i18n.php';
    require_once __DIR__ . '/state.php';

    if ($userId > 0 && function_exists('cardy_session_clear_for_user')) {
        cardy_session_clear_for_user($userId);
    }

    // Clear saved language preference so Accept-Language / English soft-confirm can run clean.
    if ($userId > 0) {
        $pdo = function_exists('get_db_connection') ? get_db_connection() : get_auth_db();
        if ($pdo) {
            try {
                i18n_ensure_schema($pdo);
                $stmt = $pdo->prepare('UPDATE cardobot_users SET preferred_locale = NULL WHERE id = ?');
                $stmt->execute([$userId]);
            } catch (Throwable $e) {
                error_log('cardobot_wipe_test_user_chat locale: ' . $e->getMessage());
            }
        }
    }

    // Fresh test pipeline always boots in English (browser Chinese must not stick).
    i18n_set_session_locale('en');
}

/**
 * Log in a user (set session)
 * @param array $user User data (from database or admin)
 */
function login_user(array $user): void {
    auth_boot(true);
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }

    // Handle both database format (created_at) and legacy format (created)
    $created = $user['created_at'] ?? $user['created'] ?? date('Y-m-d H:i:s');
    $lastLogin = $user['last_login'] ?? date('Y-m-d H:i:s');
    
    $_SESSION['user'] = [
        'username' => $user['username'],
        'is_admin' => isset($user['is_admin']) ? (bool)$user['is_admin'] : false,
        'created' => $created,
        'last_login' => $lastLogin,
        'id' => $user['id'] ?? null,
        'email' => $user['email'] ?? null,
        'auth_method' => $user['auth_method'] ?? 'password'
    ];

    // Pipeline test account: always start Cardy from a clean slate.
    if (cardobot_is_test_user((string)($user['username'] ?? ''))) {
        $uid = (int)($user['id'] ?? 0);
        if ($uid <= 0) {
            $uid = (int)(ensure_user_row() ?: 0);
        }
        cardobot_wipe_test_user_chat($uid);
    }
}

/**
 * Check if current user is admin
 * @return bool
 */
function is_admin(): bool {
    $user = get_logged_in_user();
    return isset($user['is_admin']) && $user['is_admin'] === true;
}

/**
 * Log out current user
 */
function logout_user(): void {
    auth_boot(false);
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?: '/',
                'domain' => $params['domain'] ?: '',
                'secure' => (bool)$params['secure'],
                'httponly' => (bool)$params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }
}

/**
 * Ensure the logged-in user has a matching row in cardobot_users.
 *
 * Solves two real-world cases:
 *   1. Admin login from .env (auth_method='password', no DB row yet).
 *   2. Stale session whose id no longer maps to a row.
 *
 * On success, $_SESSION['user']['id'] is guaranteed to be a real row id and
 * the row is auto-created from session data if missing. Returns the id, or
 * null if the user cannot be ensured (e.g. no DB connection).
 *
 * Safe to call on every request: short-circuits when the session id is
 * already valid.
 */
function ensure_user_row(): ?int {
    if (!isset($_SESSION['user']) || empty($_SESSION['user']['username'])) {
        return null;
    }

    $sessionUser = $_SESSION['user'];
    $username = $sessionUser['username'];
    $sessionId = isset($sessionUser['id']) ? (int)$sessionUser['id'] : 0;

    if ($sessionId > 0) {
        $existing = get_user_by_id($sessionId);
        if ($existing) {
            return $sessionId;
        }
    }

    $existing = get_user_by_username($username);
    if ($existing && !empty($existing['id'])) {
        $realId = (int)$existing['id'];
        $_SESSION['user']['id'] = $realId;
        $_SESSION['user']['email']       = $existing['email']       ?? ($sessionUser['email'] ?? null);
        $_SESSION['user']['name']        = $existing['name']        ?? ($sessionUser['name']  ?? null);
        $_SESSION['user']['auth_method'] = $existing['auth_method'] ?? ($sessionUser['auth_method'] ?? 'password');
        return $realId;
    }

    $pdo = get_auth_db();
    if (!$pdo) {
        error_log('ensure_user_row: no DB connection (username=' . $username . ')');
        return null;
    }

    try {
        $isAdmin    = !empty($sessionUser['is_admin']) ? 1 : 0;
        $email      = $sessionUser['email']       ?? null;
        $authMethod = $sessionUser['auth_method'] ?? 'password';

        $stmt = $pdo->prepare(
            "INSERT INTO cardobot_users
                (username, email, auth_method, is_admin, created_at, last_login)
             VALUES (?, ?, ?, ?, NOW(), NOW())"
        );
        $stmt->execute([$username, $email, $authMethod, $isAdmin]);
        $realId = (int)$pdo->lastInsertId();

        $_SESSION['user']['id'] = $realId;
        error_log("ensure_user_row: auto-created cardobot_users row for '{$username}' (id={$realId})");

        get_user_dir($username);

        return $realId;
    } catch (PDOException $e) {
        error_log("ensure_user_row: failed to create row for '{$username}': " . $e->getMessage());
        return null;
    }
}
