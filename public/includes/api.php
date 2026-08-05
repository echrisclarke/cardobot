<?php
/**
 * Shared API bootstrap for Card-o-Bot product endpoints.
 */

require_once __DIR__ . '/env.php';

/**
 * Configure + start session.
 *
 * @param bool $create When false, only resume an existing session cookie.
 *                     Avoids minting a new empty PHPSESSID that can overwrite
 *                     a valid login cookie the browser failed to send.
 */
function api_boot(bool $create = true): void {
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $appUrl = get_app_url();
    $secure = str_starts_with($appUrl, 'https://')
        || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    // Persist sessions on the Railway volume when available.
    $savePath = getenv('SESSION_SAVE_PATH') ?: '';
    if ($savePath === '' && is_dir('/data')) {
        $savePath = '/data/sessions';
    }
    if ($savePath !== '') {
        if (!is_dir($savePath)) {
            @mkdir($savePath, 0700, true);
        }
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }
    }

    $name = session_name();
    if (!$create && empty($_COOKIE[$name])) {
        return;
    }

    session_start();
}

/**
 * Emit JSON and exit.
 */
function api_json(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload);
    exit;
}

/**
 * Emit a standard error envelope and exit.
 */
function api_error(string $error, string $message = '', int $status = 400, array $extra = []): void {
    $payload = array_merge([
        'ok' => false,
        'error' => $error,
        'message' => $message !== '' ? $message : $error,
    ], $extra);
    api_json($payload, $status);
}

/**
 * Require a logged-in user (JSON 401).
 */
function api_require_login(): array {
    require_once __DIR__ . '/auth.php';
    if (session_status() === PHP_SESSION_NONE) {
        api_boot(false);
    }
    if (session_status() === PHP_SESSION_NONE || !is_logged_in()) {
        api_error('auth_required', 'Authentication required. Please sign in again.', 401);
    }
    $user = get_logged_in_user();
    return is_array($user) ? $user : [];
}

/**
 * Decode JSON POST body once.
 */
function api_require_post_json(): array {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        api_error('method_not_allowed', 'POST required', 405);
    }
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        api_error('invalid_json', 'Request body must be JSON', 400);
    }
    return $data;
}

/**
 * Hosts always trusted for Card-o-Bot (custom domain + Railway).
 */
function api_allowed_hosts(): array {
    $appUrl = get_app_url();
    $appHost = strtolower((string)(parse_url($appUrl, PHP_URL_HOST) ?: ''));
    $reqHost = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $hosts = array_filter([
        $appHost,
        $reqHost,
        'cardobot.com',
        'www.cardobot.com',
        'web-production-ef9df3.up.railway.app',
    ]);
    return array_values(array_unique($hosts));
}

/**
 * Same-origin check for mutating endpoints.
 */
function api_assert_same_origin(): void {
    $allowed = api_allowed_hosts();
    if (empty($allowed)) {
        return;
    }

    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $referer = $_SERVER['HTTP_REFERER'] ?? '';

    $candidateHost = '';
    if ($origin !== '') {
        $candidateHost = strtolower((string)(parse_url($origin, PHP_URL_HOST) ?: ''));
    } elseif ($referer !== '') {
        $candidateHost = strtolower((string)(parse_url($referer, PHP_URL_HOST) ?: ''));
    } else {
        return;
    }

    if ($candidateHost === '' || !in_array($candidateHost, $allowed, true)) {
        api_error('forbidden_origin', 'Request origin not allowed', 403);
    }
}

/**
 * True when APP_ENV is production.
 */
function api_is_production(): bool {
    $env = load_env();
    $mode = strtolower((string)($env['APP_ENV'] ?? 'production'));
    return $mode === 'production' || $mode === 'prod';
}

/**
 * Block public probes in production unless admin.
 */
function api_deny_public_probe(): void {
    require_once __DIR__ . '/auth.php';
    if (!api_is_production()) {
        return;
    }
    if (session_status() === PHP_SESSION_NONE) {
        api_boot(false);
    }
    $user = (session_status() !== PHP_SESSION_NONE) ? get_logged_in_user() : null;
    $isAdmin = !empty($user['is_admin']);
    if (!$isAdmin) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Not found';
        exit;
    }
}
