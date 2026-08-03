<?php
/**
 * Database Setup Script for Card-o-Bot
 * 
 * This script automatically creates all required database tables.
 * It's safe to run multiple times (uses CREATE TABLE IF NOT EXISTS).
 * 
 * Usage:
 * - Via browser: https://herbiecreative.com/cardobot/admin/database/setup.php
 * - Via CLI: php setup.php
 */

// Log errors server-side only — never display to browser
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../../includes/env.php';
require_once __DIR__ . '/../../includes/auth.php';

// Require admin access
if (!is_admin()) {
    header('Location: ' . get_base_path() . '/index.php');
    exit;
}

$assetPath = get_asset_path();
$basePath = get_base_path();

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
function get_db_connection_for_setup(&$error, &$messages) {
    $creds = get_db_credentials();
    
    if (empty($creds['database']) || empty($creds['username'])) {
        $error = "ERROR: Database credentials not found in .env file<br>" .
            "Required: CARDOBOT_DB_NAME (or DB_NAME), CARDOBOT_DB_USER (or DB_USER), CARDOBOT_DB_PASS (or DB_PASS)<br>" .
            "Add to .env file (app-specific):<br>" .
            "CARDOBOT_DB_NAME=your_database_name<br>" .
            "CARDOBOT_DB_USER=your_database_user<br>" .
            "CARDOBOT_DB_PASS=your_database_password<br>" .
            "<br>Or use generic (shared across apps):<br>" .
            "DB_NAME=your_database_name<br>" .
            "DB_USER=your_database_user<br>" .
            "DB_PASS=your_database_password";
        return null;
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
            $messages[] = "✅ Database '{$creds['database']}' created";
        } else {
            // User doesn't have permission to create databases
            $error = "⚠️ Database '{$creds['database']}' does not exist and cannot be created automatically.<br>" .
                "Your database user doesn't have permission to create databases.<br><br>" .
                "Please create the database manually:<br>" .
                "<strong>Option 1 - Via cPanel:</strong><br>" .
                "1. Log into cPanel<br>" .
                "2. Find 'MySQL Databases' section<br>" .
                "3. Enter database name: {$creds['database']}<br>" .
                "4. Click 'Create Database'<br><br>" .
                "<strong>Option 2 - Via phpMyAdmin:</strong><br>" .
                "1. Go to phpMyAdmin<br>" .
                "2. Click 'Databases' tab<br>" .
                "3. Enter database name: {$creds['database']}<br>" .
                "4. Select collation: utf8mb4_unicode_ci<br>" .
                "5. Click 'Create'<br><br>" .
                "Note: If you see a prefix requirement (like 'herbiecr_'), use that prefix.<br>" .
                "Then update CARDOBOT_DB_NAME in your .env file and run this script again.";
            return null;
        }
    }
    
    // Use the helper function from env.php
    $pdo = get_db_connection();
    if (!$pdo) {
        $error = "Database connection failed<br>" .
            "Check your .env file: DB_HOST, DB_NAME, DB_USER, DB_PASS";
        return null;
    }
    
    return $pdo;
}

// Initialize variables
$error = '';
$messages = [];
$tablesCreated = [];
$tablesVerified = [];
$pdo = null;
$actualDbname = '';

// Test database connection and create if needed
try {
    $creds = get_db_credentials();
    
    if (empty($creds['database'])) {
        $error = "❌ Database name not set in .env file<br>" .
            "Add to .env: CARDOBOT_DB_NAME=your_database_name<br>" .
            "Or use generic: DB_NAME=your_database_name";
    } else {
        $pdo = get_db_connection_for_setup($error, $messages);
        
        if ($pdo) {
            $messages[] = "✅ Connected to database successfully";
    
    // Show database name
    $stmt = $pdo->query("SELECT DATABASE() as dbname");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $actualDbname = $result['dbname'] ?? $creds['database'];
            $messages[] = "Database: {$actualDbname}";
        }
    }
} catch (Exception $e) {
    $error = "❌ Connection failed: " . htmlspecialchars($e->getMessage());
}

// Check if tables already exist
$existingTables = [];
$tables = ['cardobot_users', 'cardobot_cards', 'cardobot_sessions'];

if ($pdo) {
foreach ($tables as $table) {
        try {
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
    if ($stmt->rowCount() > 0) {
        $existingTables[] = $table;
                $messages[] = "⚠️ Table '{$table}' already exists";
            }
        } catch (PDOException $e) {
            $messages[] = "⚠️ Error checking table '{$table}': " . htmlspecialchars($e->getMessage());
    }
}

if (!empty($existingTables)) {
        $messages[] = "⚠️ WARNING: Some tables already exist!";
        $messages[] = "This script will NOT overwrite existing tables.";
        $messages[] = "If you want to recreate tables, drop them first in phpMyAdmin.";
} else {
        $messages[] = "✅ No existing tables found";
}

// Create tables
    if (empty($error)) {
try {
            // Note: DDL statements (CREATE TABLE) cannot be rolled back in MySQL
            // So we don't use transactions here
    
    // Users table
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
            $tablesCreated[] = 'cardobot_users';
            $messages[] = "✅ cardobot_users created";
    
    // Cards table
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
            $tablesCreated[] = 'cardobot_cards';
            $messages[] = "✅ cardobot_cards created";
    
    // Sessions table (optional)
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
            $tablesCreated[] = 'cardobot_sessions';
            $messages[] = "✅ cardobot_sessions created";
    
            $messages[] = "✅ All tables created successfully!";
    
} catch (PDOException $e) {
            $error = "❌ Error creating tables: " . htmlspecialchars($e->getMessage());
        }
}

// Verify tables
    if (empty($error)) {
foreach ($tables as $table) {
            try {
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
    if ($stmt->rowCount() > 0) {
        // Get row count
        $countStmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
                    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
                    $tablesVerified[$table] = $count;
                    $messages[] = "✅ {$table} exists ({$count} rows)";
    } else {
                    $messages[] = "❌ {$table} NOT found";
                }
            } catch (PDOException $e) {
                $messages[] = "❌ Error verifying {$table}: " . htmlspecialchars($e->getMessage());
    }
}
    }
}

// Output HTML
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Setup - Card-o-Bot</title>
    <link rel="stylesheet" href="<?php echo $assetPath; ?>/assets/css/base.css">
</head>
<body class="admin-page">
    <header class="header">
        <div class="header-content">
            <h1>⚙️ Database Setup</h1>
            <div class="user-info">
                <a href="<?php echo $basePath; ?>/admin/dashboard.php" class="btn btn-secondary">Back to Admin</a>
            </div>
        </div>
    </header>
    
    <main class="container">
        <div class="admin-section">
            <div class="admin-section-header">
                <h2>Card-o-Bot Database Setup</h2>
            </div>
            <div class="admin-section-body">
                <?php if (!empty($error)): ?>
                    <div class="alert alert-error" style="background: var(--color-error); color: var(--color-text-light); padding: var(--spacing-4); border-radius: var(--border-radius); margin-bottom: var(--spacing-4);">
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($messages)): ?>
                    <div class="setup-messages" style="font-family: monospace; background: var(--color-bg-light); border: var(--border-width) solid var(--color-border); padding: var(--spacing-4); border-radius: var(--border-radius); margin-bottom: var(--spacing-4);">
                        <?php foreach ($messages as $msg): ?>
                            <div style="margin-bottom: var(--spacing-2); color: var(--color-text-primary);"><?php echo $msg; ?></div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (count($tablesVerified) === count($tables)): ?>
                    <div class="alert alert-success" style="background: var(--color-success); color: var(--color-text-light); padding: var(--spacing-4); border-radius: var(--border-radius); margin-bottom: var(--spacing-4);">
                        <strong>✅ Database setup complete!</strong>
                        <br><br>
                        <strong>Next steps:</strong><br>
                        1. If you have existing users in JSON, run: migrate-from-json.php<br>
                        2. Test user registration and login<br>
                        3. Visit the <a href="<?php echo $basePath; ?>/admin/database.php" style="color: var(--color-text-light); text-decoration: underline;">Database Browser</a> to verify tables
                    </div>
                <?php elseif (!empty($tablesVerified) && count($tablesVerified) < count($tables)): ?>
                    <div class="alert alert-warning" style="background: var(--color-warning); color: var(--color-text-dark); padding: var(--spacing-4); border-radius: var(--border-radius); margin-bottom: var(--spacing-4);">
                        <strong>⚠️ Some tables may not have been created.</strong><br>
                        Check the messages above for details.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
