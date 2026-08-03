<?php
/**
 * Database Setup Script for Card-o-Bot
 * 
 * This script automatically creates all required database tables.
 * It's safe to run multiple times (uses CREATE TABLE IF NOT EXISTS).
 * 
 * Usage:
 * - Via browser: https://herbiecreative.com/cardobot/database/setup.php
 * - Via CLI: php setup.php
 */

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin access
if (!is_admin()) {
    header('Location: ' . get_base_path() . '/index.php');
    exit;
}

// Check if database exists (returns true if exists, false if not)
function database_exists($host, $username, $password, $dbname) {
    try {
        // Connect without specifying database
        $dsn = "mysql:host={$host};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // Check if database exists
        $stmt = $pdo->query("SHOW DATABASES LIKE '{$dbname}'");
        return $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        // If we can't check (connection error, etc.), assume it doesn't exist
        // Don't throw - just return false so we can try to create it
        return false;
    }
}

// Try to create database if it doesn't exist (may fail due to permissions)
function try_create_database($host, $username, $password, $dbname) {
    try {
        // Connect without specifying database
        $dsn = "mysql:host={$host};charset=utf8mb4";
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        
        // Try to create database
        $pdo->exec("CREATE DATABASE `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        return true; // Created successfully
    } catch (PDOException $e) {
        // Check if error is permission-related (1044 = Access denied)
        $errorCode = $e->getCode();
        $errorMessage = $e->getMessage();
        
        if ($errorCode == 1044 || 
            strpos($errorMessage, 'Access denied') !== false || 
            strpos($errorMessage, '1044') !== false) {
            return false; // Permission denied - user can't create databases
        }
        // For any other error, return false (don't throw)
        return false;
    }
}

// Get database connection for setup script (creates DB if needed)
function get_db_connection_for_setup() {
    $creds = get_db_credentials();
    
    if (empty($creds['database']) || empty($creds['username'])) {
        die("ERROR: Database credentials not found in .env file\n" .
            "Required: CARDOBOT_DB_NAME (or DB_NAME), CARDOBOT_DB_USER (or DB_USER), CARDOBOT_DB_PASS (or DB_PASS)\n" .
            "Add to .env file (app-specific):\n" .
            "CARDOBOT_DB_NAME=your_database_name\n" .
            "CARDOBOT_DB_USER=your_database_user\n" .
            "CARDOBOT_DB_PASS=your_database_password\n" .
            "\nOr use generic (shared across apps):\n" .
            "DB_NAME=your_database_name\n" .
            "DB_USER=your_database_user\n" .
            "DB_PASS=your_database_password\n");
    }
    
    // Check if database exists
    $dbExists = database_exists(
        $creds['host'], 
        $creds['username'], 
        $creds['password'], 
        $creds['database']
    );
    
    if (!$dbExists) {
        // Try to create database
        $created = try_create_database(
            $creds['host'], 
            $creds['username'], 
            $creds['password'], 
            $creds['database']
        );
        
        if ($created) {
            echo "   ✅ Database '{$creds['database']}' created\n";
        } else {
            // User doesn't have permission to create databases
            die("\n   ⚠️  Database '{$creds['database']}' does not exist and cannot be created automatically.\n" .
                "   Your database user doesn't have permission to create databases.\n\n" .
                "   Please create the database manually:\n" .
                "   Option 1 - Via cPanel:\n" .
                "   1. Log into cPanel\n" .
                "   2. Find 'MySQL Databases' section\n" .
                "   3. Enter database name: {$creds['database']}\n" .
                "   4. Click 'Create Database'\n\n" .
                "   Option 2 - Via phpMyAdmin:\n" .
                "   1. Go to phpMyAdmin\n" .
                "   2. Click 'Databases' tab\n" .
                "   3. Enter database name: {$creds['database']}\n" .
                "   4. Select collation: utf8mb4_unicode_ci\n" .
                "   5. Click 'Create'\n\n" .
                "   Note: If you see a prefix requirement (like 'herbiecr_'), use that prefix.\n" .
                "   Then update CARDOBOT_DB_NAME in your .env file and run this script again.\n\n");
        }
    }
    
    // Use the helper function from env.php
    $pdo = get_db_connection();
    if (!$pdo) {
        die("Database connection failed\n" .
            "Check your .env file: DB_HOST, DB_NAME, DB_USER, DB_PASS\n");
    }
    
    return $pdo;
}

// Set content type for browser viewing
if (php_sapi_name() !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
}

echo "========================================\n";
echo "Card-o-Bot Database Setup\n";
echo "========================================\n\n";

// Test database connection and create if needed
echo "1. Checking database...\n";
try {
    $creds = get_db_credentials();
    
    if (empty($creds['database'])) {
        die("   ❌ Database name not set in .env file\n" .
            "   Add to .env: CARDOBOT_DB_NAME=your_database_name\n" .
            "   Or use generic: DB_NAME=your_database_name\n\n");
    }
    
    $pdo = get_db_connection_for_setup();
    echo "   ✅ Connected to database successfully\n";
    
    // Show database name
    $stmt = $pdo->query("SELECT DATABASE() as dbname");
    $actualDbname = $stmt->fetch()['dbname'];
    echo "   Database: {$actualDbname}\n\n";
} catch (Exception $e) {
    die("   ❌ Connection failed: " . $e->getMessage() . "\n\n");
}

// Check if tables already exist
echo "2. Checking existing tables...\n";
$existingTables = [];
$tables = ['cardobot_users', 'cardobot_cards', 'cardobot_sessions'];
foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
    if ($stmt->rowCount() > 0) {
        $existingTables[] = $table;
        echo "   ⚠️  Table '{$table}' already exists\n";
    }
}

if (!empty($existingTables)) {
    echo "\n   ⚠️  WARNING: Some tables already exist!\n";
    echo "   This script will NOT overwrite existing tables.\n";
    echo "   If you want to recreate tables, drop them first in phpMyAdmin.\n\n";
} else {
    echo "   ✅ No existing tables found\n\n";
}

// Create tables
echo "3. Creating database tables...\n\n";

try {
    $pdo->beginTransaction();
    
    // Users table
    echo "   Creating cardobot_users table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cardobot_users` (
      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `username` VARCHAR(50) NOT NULL UNIQUE,
      `password_hash` VARCHAR(255) NULL DEFAULT NULL,
      `email` VARCHAR(255) NULL DEFAULT NULL,
      `google_id` VARCHAR(255) NULL DEFAULT NULL,
      `name` VARCHAR(255) NULL DEFAULT NULL,
      `given_name` VARCHAR(255) NULL DEFAULT NULL,
      `family_name` VARCHAR(255) NULL DEFAULT NULL,
      `picture` VARCHAR(500) NULL DEFAULT NULL,
      `auth_method` ENUM('password', 'google') NOT NULL DEFAULT 'password',
      `is_admin` BOOLEAN NOT NULL DEFAULT FALSE,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `last_login` TIMESTAMP NULL DEFAULT NULL,
      `updated_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      INDEX `idx_username` (`username`),
      INDEX `idx_google_id` (`google_id`),
      INDEX `idx_email` (`email`),
      INDEX `idx_auth_method` (`auth_method`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "      ✅ cardobot_users created\n";
    
    // Cards table
    echo "   Creating cardobot_cards table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cardobot_cards` (
      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `card_id` VARCHAR(50) NOT NULL UNIQUE,
      `user_id` INT UNSIGNED NOT NULL,
      `type` ENUM('BOT', 'CRITTER') NOT NULL DEFAULT 'BOT',
      `image_url` VARCHAR(500) NULL DEFAULT NULL,
      `drawing_data` LONGTEXT NULL DEFAULT NULL COMMENT 'Base64 encoded canvas data',
      `nickname` VARCHAR(100) NULL DEFAULT NULL,
      `bio` TEXT NULL DEFAULT NULL,
      `power` VARCHAR(255) NULL DEFAULT NULL,
      `ability` TEXT NULL DEFAULT NULL,
      `hp` INT UNSIGNED NULL DEFAULT NULL,
      `att` INT UNSIGNED NULL DEFAULT NULL,
      `str` INT UNSIGNED NULL DEFAULT NULL,
      `los` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Line of Sight',
      `con` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Constitution',
      `npo` INT UNSIGNED NULL DEFAULT NULL COMMENT 'NPO stat',
      `hue` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Color hue (0-360)',
      `saturation` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Color saturation (0-100)',
      `lightness` INT UNSIGNED NULL DEFAULT NULL COMMENT 'Color lightness (0-100)',
      `attributes_json` JSON NULL DEFAULT NULL COMMENT 'Additional attributes as JSON',
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `modified_at` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      FOREIGN KEY (`user_id`) REFERENCES `cardobot_users`(`id`) ON DELETE CASCADE,
      INDEX `idx_user_id` (`user_id`),
      INDEX `idx_card_id` (`card_id`),
      INDEX `idx_type` (`type`),
      INDEX `idx_created_at` (`created_at`),
      INDEX `idx_user_type` (`user_id`, `type`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "      ✅ cardobot_cards created\n";
    
    // Sessions table (optional)
    echo "   Creating cardobot_sessions table...\n";
    $pdo->exec("CREATE TABLE IF NOT EXISTS `cardobot_sessions` (
      `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      `user_id` INT UNSIGNED NULL DEFAULT NULL,
      `session_id` VARCHAR(128) NOT NULL,
      `ip_address` VARCHAR(45) NULL DEFAULT NULL,
      `user_agent` VARCHAR(500) NULL DEFAULT NULL,
      `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
      `last_activity` TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
      `expires_at` TIMESTAMP NULL DEFAULT NULL,
      FOREIGN KEY (`user_id`) REFERENCES `cardobot_users`(`id`) ON DELETE CASCADE,
      INDEX `idx_session_id` (`session_id`),
      INDEX `idx_user_id` (`user_id`),
      INDEX `idx_expires_at` (`expires_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "      ✅ cardobot_sessions created\n";
    
    $pdo->commit();
    echo "\n   ✅ All tables created successfully!\n\n";
    
} catch (PDOException $e) {
    $pdo->rollBack();
    die("\n   ❌ Error creating tables: " . $e->getMessage() . "\n\n");
}

// Verify tables
echo "4. Verifying tables...\n";
$created = 0;
foreach ($tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
    if ($stmt->rowCount() > 0) {
        // Get row count
        $countStmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
        $count = $countStmt->fetch()['count'];
        echo "   ✅ {$table} exists ({$count} rows)\n";
        $created++;
    } else {
        echo "   ❌ {$table} NOT found\n";
    }
}

echo "\n========================================\n";
if ($created === count($tables)) {
    echo "✅ Database setup complete!\n";
    echo "\nNext steps:\n";
    echo "1. If you have existing users in JSON, run: migrate-from-json.php\n";
    echo "2. Update auth.php to use database instead of JSON\n";
    echo "3. Test user registration and login\n";
} else {
    echo "⚠️  Some tables may not have been created.\n";
    echo "Check the error messages above.\n";
}
echo "========================================\n";
