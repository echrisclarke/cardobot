<?php

/**
 * Environment Variable Loader for Card-o-Bot
 * Based on /openai/env.php pattern
 * Production-only: uses /private/.env outside public_html
 */

/**
 * Emit a safe API error response and log detailed diagnostics server-side.
 */
function emit_env_error(string $publicError, array $debugDetails = []): void {
  if (!empty($debugDetails)) {
    error_log('[cardobot env] ' . $publicError . ' | ' . json_encode($debugDetails));
  } else {
    error_log('[cardobot env] ' . $publicError);
  }

  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'error' => $publicError
  ]);
  exit;
}

/**
 * Parse a dotenv file with support for comments and quoted values.
 * Returns ['env' => array, 'issues' => array]
 */
function parse_dotenv_file(string $path): array {
  $env = [];
  $issues = [];

  $lines = @file($path, FILE_IGNORE_NEW_LINES);
  if ($lines === false) {
    return [
      'env' => [],
      'issues' => ['Could not read .env file contents']
    ];
  }

  foreach ($lines as $idx => $line) {
    $lineNumber = $idx + 1;

    // Remove UTF-8 BOM on first line if present
    if ($idx === 0) {
      $line = preg_replace('/^\xEF\xBB\xBF/', '', $line);
    }

    $trimmed = trim($line);
    if ($trimmed === '' || strpos($trimmed, '#') === 0 || strpos($trimmed, ';') === 0) {
      continue;
    }

    if (strpos($trimmed, 'export ') === 0) {
      $trimmed = ltrim(substr($trimmed, 7));
    }

    $eqPos = strpos($trimmed, '=');
    if ($eqPos === false) {
      $issues[] = "Line {$lineNumber}: Missing '=' sign";
      continue;
    }

    $key = trim(substr($trimmed, 0, $eqPos));
    $value = ltrim(substr($trimmed, $eqPos + 1));

    if ($key === '' || !preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key)) {
      $issues[] = "Line {$lineNumber}: Invalid key '{$key}'";
      continue;
    }

    if ($value === '') {
      $env[$key] = '';
      continue;
    }

    $firstChar = $value[0];
    if ($firstChar === '"' || $firstChar === "'") {
      $quote = $firstChar;
      $closingPos = strrpos($value, $quote);
      if ($closingPos === 0) {
        $issues[] = "Line {$lineNumber}: Unterminated quoted value";
        continue;
      }

      $rawQuoted = substr($value, 1, $closingPos - 1);
      if ($quote === '"') {
        $rawQuoted = strtr($rawQuoted, [
          '\\n' => "\n",
          '\\r' => "\r",
          '\\t' => "\t",
          '\\"' => '"',
          '\\\\' => '\\'
        ]);
      }

      $env[$key] = $rawQuoted;
      continue;
    }

    // Strip trailing inline comments when comment marker is preceded by whitespace.
    $uncommented = preg_replace('/\s[;#].*$/', '', $value);
    $env[$key] = trim($uncommented ?? $value);
  }

  return [
    'env' => $env,
    'issues' => $issues
  ];
}

function load_env(): array {
  static $cachedEnv = null;
  if (is_array($cachedEnv)) {
    return $cachedEnv;
  }

  $env = [];

  // 1) Process / Railway environment variables win when present.
  $processKeys = [
    'APP_URL', 'APP_ENV', 'OPENAI_API_KEY', 'OPENAI_TEXT_MODEL', 'OPENAI_IMAGE_MODEL',
    'OPENAI_MAX_TOKENS', 'OPENAI_TEMPERATURE',
    'CARDOBOT_DB_HOST', 'CARDOBOT_DB_NAME', 'CARDOBOT_DB_USER', 'CARDOBOT_DB_PASS',
    'CARDOBOT_DB_PASSWORD', 'CARDOBOT_DB_CHARSET',
    'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS', 'DB_PASSWORD', 'DB_CHARSET',
    'MYSQLHOST', 'MYSQLDATABASE', 'MYSQLUSER', 'MYSQLPASSWORD', 'MYSQLPORT',
    'ADMIN_USERNAME', 'ADMIN_PASSWORD',
    'GOOGLE_CLIENT_ID', 'GOOGLE_CLIENT_SECRET',
    'UPLOAD_ROOT', 'ML_SERVICE_URL', 'ML_SERVICE_TOKEN', 'ML_EMBED_MODEL',
    'ENV_PATH',
  ];
  foreach ($processKeys as $key) {
    $val = $_ENV[$key] ?? getenv($key);
    if ($val !== false && $val !== null && $val !== '') {
      $env[$key] = (string)$val;
    }
  }

  // Railway MySQL plugin aliases
  if (empty($env['CARDOBOT_DB_HOST']) && !empty($env['MYSQLHOST'])) {
    $env['CARDOBOT_DB_HOST'] = $env['MYSQLHOST'];
  }
  if (empty($env['CARDOBOT_DB_PORT']) && !empty($env['MYSQLPORT'])) {
    $env['CARDOBOT_DB_PORT'] = $env['MYSQLPORT'];
  }
  if (empty($env['CARDOBOT_DB_NAME']) && !empty($env['MYSQLDATABASE'])) {
    $env['CARDOBOT_DB_NAME'] = $env['MYSQLDATABASE'];
  }
  if (empty($env['CARDOBOT_DB_USER']) && !empty($env['MYSQLUSER'])) {
    $env['CARDOBOT_DB_USER'] = $env['MYSQLUSER'];
  }
  if (empty($env['CARDOBOT_DB_PASS']) && !empty($env['MYSQLPASSWORD'])) {
    $env['CARDOBOT_DB_PASS'] = $env['MYSQLPASSWORD'];
  }

  // 2) Optional .env file (local / Bluehost private/.env)
  $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
  $possiblePaths = [];
  if (!empty($_ENV['ENV_PATH']) || getenv('ENV_PATH')) {
    $possiblePaths[] = (string)($_ENV['ENV_PATH'] ?? getenv('ENV_PATH'));
  }
  // Repo root next to public/
  $possiblePaths[] = dirname(__DIR__, 2) . '/.env';
  $possiblePaths[] = dirname(__DIR__) . '/../.env';
  $possiblePaths[] = dirname($docRoot) . '/.env';
  $possiblePaths[] = dirname($docRoot) . '/private/.env';
  if (strpos($docRoot, '/public_html/') !== false || strpos($docRoot, '\\public_html\\') !== false) {
    $parts = preg_split('/[\/\\\\]public_html[\/\\\\]?/', $docRoot);
    if (!empty($parts[0])) {
      $possiblePaths[] = $parts[0] . '/private/.env';
    }
  }
  $possiblePaths[] = dirname(dirname($docRoot)) . '/private/.env';

  $path = null;
  foreach ($possiblePaths as $tryPath) {
    if ($tryPath && file_exists($tryPath)) {
      $path = $tryPath;
      break;
    }
  }

  if ($path) {
    $parseResult = parse_dotenv_file($path);
    $fileEnv = $parseResult['env'] ?? [];
    if (is_array($fileEnv)) {
      // Process env already set wins; file fills gaps.
      $env = array_merge($fileEnv, $env);
    }
  }

  // If we still have no OpenAI key and no DB and no file, fail only when key is requested.
  $cachedEnv = $env;
  return $cachedEnv;
}

/**
 * Public app URL (no trailing slash), for OAuth redirects and absolute links.
 */
function get_app_url(): string {
  $env = load_env();
  $configured = rtrim((string)($env['APP_URL'] ?? ''), '/');
  if ($configured !== '') {
    return $configured;
  }
  $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
  $protocol = $https ? 'https' : 'http';
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $protocol . '://' . $host;
}

function get_upload_root(): string {
  $env = load_env();
  $root = trim((string)($env['UPLOAD_ROOT'] ?? ''));
  if ($root !== '') {
    return rtrim($root, '/\\');
  }
  $dir = __DIR__ . '/../uploads';
  if (!is_dir($dir)) {
    @mkdir($dir, 0755, true);
  }
  return $dir;
}

function get_openai_key(): string {
  $env = load_env();
  $key = $env['OPENAI_API_KEY'] ?? '';
  if (!$key) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'OPENAI_API_KEY not found in .env']);
    exit;
  }
  return $key;
}

function get_image_model(): string {
  $env = load_env();
  return $env['OPENAI_IMAGE_MODEL'] ?? 'chatgpt-image-latest';
}

function get_text_model(): string {
  $env = load_env();
  return $env['OPENAI_TEXT_MODEL'] ?? 'gpt-5-mini';
}

function get_max_tokens(): int {
  $env = load_env();
  return intval($env['OPENAI_MAX_TOKENS'] ?? 150);
}

function get_temperature(): float {
  $env = load_env();
  return floatval($env['OPENAI_TEMPERATURE'] ?? 0.8);
}

/**
 * Get admin username from .env
 * @return string Admin username
 */
function get_admin_username(): string {
  $env = load_env();
  return $env['ADMIN_USERNAME'] ?? '';
}

/**
 * Get admin password from .env
 * @return string Admin password
 */
function get_admin_password(): string {
  $env = load_env();
  return $env['ADMIN_PASSWORD'] ?? '';
}

/**
 * Get database credentials from .env
 * Supports both generic (DB_NAME) and app-specific (CARDOBOT_DB_NAME) formats
 * @return array Database connection parameters
 */
function get_db_credentials(): array {
  $env = load_env();
  
  // Try app-specific first, fallback to generic
  $dbName = $env['CARDOBOT_DB_NAME'] ?? $env['DB_NAME'] ?? '';
  $dbHost = $env['CARDOBOT_DB_HOST'] ?? $env['DB_HOST'] ?? 'localhost';
  $dbPort = $env['CARDOBOT_DB_PORT'] ?? $env['DB_PORT'] ?? $env['MYSQLPORT'] ?? '';
  $dbUser = $env['CARDOBOT_DB_USER'] ?? $env['DB_USER'] ?? '';
  $dbPass = $env['CARDOBOT_DB_PASS'] ?? $env['CARDOBOT_DB_PASSWORD'] ?? $env['DB_PASS'] ?? $env['DB_PASSWORD'] ?? '';
  $dbCharset = $env['CARDOBOT_DB_CHARSET'] ?? $env['DB_CHARSET'] ?? 'utf8mb4';
  
  return [
    'host' => $dbHost,
    'port' => $dbPort,
    'database' => $dbName,
    'username' => $dbUser,
    'password' => $dbPass,
    'charset' => $dbCharset
  ];
}

/**
 * Get database connection (PDO)
 * @return PDO|null Database connection or null on failure
 */
function get_db_connection(): ?PDO {
  $creds = get_db_credentials();
  
  if (empty($creds['database']) || empty($creds['username'])) {
    error_log('Database credentials not found in .env file');
    return null;
  }
  
  try {
    $dsn = "mysql:host={$creds['host']};dbname={$creds['database']};charset={$creds['charset']}";
    if (!empty($creds['port'])) {
      $dsn = "mysql:host={$creds['host']};port={$creds['port']};dbname={$creds['database']};charset={$creds['charset']}";
    }
    $options = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    return new PDO($dsn, $creds['username'], $creds['password'], $options);
  } catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    return null;
  }
}

/**
 * Get OpenAI API key (alias for get_openai_key)
 * @return string OpenAI API key
 */
function get_openai_api_key(): string {
  return get_openai_key();
}

/**
 * Get OpenAI image model (alias for get_image_model)
 * @return string Model name
 */
function get_openai_image_model(): string {
  return get_image_model();
}

/**
 * Get OpenAI text model (alias for get_text_model)
 * @return string Model name
 */
function get_openai_text_model(): string {
  return get_text_model();
}

/**
 * Get database host
 * @return string|null Database host
 */
function get_db_host(): ?string {
  $creds = get_db_credentials();
  return $creds['host'] ?? null;
}

/**
 * Get database name
 * @return string|null Database name
 */
function get_db_name(): ?string {
  $creds = get_db_credentials();
  return $creds['database'] ?? null;
}

/**
 * Get database user
 * @return string|null Database username
 */
function get_db_user(): ?string {
  $creds = get_db_credentials();
  return $creds['username'] ?? null;
}

/**
 * Get Google OAuth Client ID
 * @return string|null Client ID
 */
function get_google_client_id(): ?string {
  $env = load_env();
  return $env['GOOGLE_CLIENT_ID'] ?? null;
}

/**
 * Get Google OAuth Client Secret
 * @return string|null Client Secret
 */
function get_google_client_secret(): ?string {
  $env = load_env();
  return $env['GOOGLE_CLIENT_SECRET'] ?? null;
}
