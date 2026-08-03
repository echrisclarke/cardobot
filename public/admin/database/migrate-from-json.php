<?php
/**
 * Migration Script: JSON to Database
 * 
 * This script migrates user data from JSON file to database.
 * Run this ONCE after setting up the database tables.
 * 
 * Usage: Visit this file in your browser or run via CLI:
 * php migrate-from-json.php
 */

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin access
if (!is_admin()) {
    header('Location: ' . get_base_path() . '/index.php');
    exit;
}

// Get database connection
function get_db_connection() {
    $env = load_env();
    
    $host = $env['DB_HOST'] ?? 'localhost';
    $dbname = $env['DB_NAME'] ?? '';
    $username = $env['DB_USER'] ?? '';
    $password = $env['DB_PASS'] ?? $env['DB_PASSWORD'] ?? '';
    $charset = $env['DB_CHARSET'] ?? 'utf8mb4';
    
    if (empty($dbname) || empty($username)) {
        die("ERROR: Database credentials not found in .env file\n");
    }
    
    try {
        $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $pdo = new PDO($dsn, $username, $password, $options);
        return $pdo;
    } catch (PDOException $e) {
        die("Database connection failed: " . $e->getMessage() . "\n");
    }
}

// Set content type for browser viewing
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "========================================\n";
echo "Card-o-Bot: JSON to Database Migration\n";
echo "========================================\n\n";

// Check if database tables exist
$pdo = get_db_connection();
echo "✅ Database connection successful\n\n";

// Check tables
$tables = ['cardobot_users', 'cardobot_cards'];
foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
    if ($stmt->rowCount() === 0) {
        die("❌ ERROR: Table '{$table}' does not exist. Please run schema.sql first!\n");
    }
}
echo "✅ All required tables exist\n\n";

// Load existing JSON users
echo "Loading users from JSON file...\n";
$jsonUsers = load_users();
$userCount = count($jsonUsers);
echo "Found {$userCount} users in JSON file\n\n";

if ($userCount === 0) {
    echo "No users to migrate. Migration complete!\n";
    exit;
}

// Migrate users
echo "Migrating users to database...\n";
$migrated = 0;
$skipped = 0;
$errors = 0;

$pdo->beginTransaction();

try {
    foreach ($jsonUsers as $username => $userData) {
        // Check if user already exists in database
        $stmt = $pdo->prepare("SELECT id FROM cardobot_users WHERE username = ?");
        $stmt->execute([$username]);
        
        if ($stmt->rowCount() > 0) {
            echo "  ⚠️  User '{$username}' already exists in database, skipping...\n";
            $skipped++;
            continue;
        }
        
        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO cardobot_users (
                username, password_hash, email, google_id, name, 
                given_name, family_name, picture, auth_method, 
                is_admin, created_at, last_login
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $userData['username'] ?? $username,
            $userData['password_hash'] ?? null,
            $userData['email'] ?? null,
            $userData['google_id'] ?? null,
            $userData['name'] ?? null,
            $userData['given_name'] ?? null,
            $userData['family_name'] ?? null,
            $userData['picture'] ?? null,
            $userData['auth_method'] ?? 'password',
            isset($userData['is_admin']) ? (int)$userData['is_admin'] : 0,
            $userData['created'] ?? date('Y-m-d H:i:s'),
            $userData['last_login'] ?? null
        ]);
        
        echo "  ✅ Migrated user: {$username}\n";
        $migrated++;
    }
    
    $pdo->commit();
    echo "\n✅ Migration completed successfully!\n";
    echo "   Migrated: {$migrated} users\n";
    echo "   Skipped: {$skipped} users (already in database)\n";
    echo "   Errors: {$errors}\n\n";
    
    echo "⚠️  IMPORTANT: After verifying the migration, you can:\n";
    echo "   1. Backup the JSON file: /private/cardobot_users.json\n";
    echo "   2. Update auth.php to use database instead of JSON\n";
    echo "   3. Test login with migrated users\n";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
    echo "   No changes were made to the database.\n";
    exit(1);
}
