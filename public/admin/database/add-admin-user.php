<?php
/**
 * Add Admin User to Database
 * 
 * This script adds the original "herbie" admin user to the database
 * using credentials from .env file
 */

// Enable error reporting
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

// Get admin credentials from .env using proper functions
$adminUsername = get_admin_username();
$adminPassword = get_admin_password();

if (empty($adminUsername)) {
    $adminUsername = 'herbie'; // Default fallback
}

if (empty($adminPassword)) {
    $error = "ERROR: ADMIN_PASSWORD not found in .env file<br>" .
        "Please add to your .env file:<br>" .
        "ADMIN_USERNAME=herbie<br>" .
        "ADMIN_PASSWORD=your_password_here";
}

// Initialize variables
$error = '';
$messages = [];
$userAdded = false;
$userExists = false;
$pdo = null;

// Only proceed if we have admin password
if (!empty($adminPassword)) {
    // Get database connection
    $pdo = get_db_connection();
    if (!$pdo) {
        $error = "❌ Database connection failed<br>Check your .env file: DB_HOST, DB_NAME, DB_USER, DB_PASS";
    } else {
        $messages[] = "✅ Connected to database successfully";
        
        // Check if user already exists
        try {
        $stmt = $pdo->prepare("SELECT id, username, is_admin FROM cardobot_users WHERE username = ?");
        $stmt->execute([$adminUsername]);
        $existingUser = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingUser) {
            $userExists = true;
            $messages[] = "⚠️ User '{$adminUsername}' already exists in database";
            $messages[] = "User ID: {$existingUser['id']}";
            $messages[] = "Is Admin: " . ($existingUser['is_admin'] ? 'Yes' : 'No');
            
            // Check if we need to update admin status or password
            if (!$existingUser['is_admin']) {
                $messages[] = "⚠️ User exists but is not marked as admin. Updating...";
                try {
                    $updateStmt = $pdo->prepare("UPDATE cardobot_users SET is_admin = 1 WHERE id = ?");
                    $updateStmt->execute([$existingUser['id']]);
                    $messages[] = "✅ Admin status updated";
                } catch (PDOException $e) {
                    $error = "❌ Error updating admin status: " . htmlspecialchars($e->getMessage());
                }
            }
            
            // Update password if needed (in case it changed in .env)
            $messages[] = "Updating password hash...";
            try {
                $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                $updateStmt = $pdo->prepare("UPDATE cardobot_users SET password_hash = ? WHERE id = ?");
                $updateStmt->execute([$passwordHash, $existingUser['id']]);
                $messages[] = "✅ Password hash updated";
            } catch (PDOException $e) {
                $error = "❌ Error updating password: " . htmlspecialchars($e->getMessage());
            }
        } else {
            // Create new user
            $messages[] = "Creating admin user '{$adminUsername}'...";
            try {
                $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    INSERT INTO cardobot_users 
                    (username, password_hash, auth_method, is_admin, created_at, last_login) 
                    VALUES (?, ?, 'password', 1, NOW(), NOW())
                ");
                $stmt->execute([$adminUsername, $passwordHash]);
                $userId = $pdo->lastInsertId();
                $userAdded = true;
                $messages[] = "✅ Admin user created successfully";
                $messages[] = "User ID: {$userId}";
            } catch (PDOException $e) {
                $error = "❌ Error creating user: " . htmlspecialchars($e->getMessage());
            }
        }
        
        // Verify the user
        if (empty($error)) {
            $messages[] = "Verifying user in database...";
            $stmt = $pdo->prepare("SELECT * FROM cardobot_users WHERE username = ?");
            $stmt->execute([$adminUsername]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                $messages[] = "✅ User verified in database";
                $messages[] = "ID: {$user['id']}";
                $messages[] = "Username: {$user['username']}";
                $messages[] = "Is Admin: " . ($user['is_admin'] ? 'Yes ✅' : 'No ❌');
                $messages[] = "Auth Method: {$user['auth_method']}";
                $messages[] = "Has Password: " . (!empty($user['password_hash']) ? 'Yes ✅' : 'No ❌');
                $messages[] = "Created: {$user['created_at']}";
            } else {
                $error = "❌ User verification failed - user not found after creation";
            }
        }
        
    } catch (PDOException $e) {
        $error = "❌ Database error: " . htmlspecialchars($e->getMessage());
    }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Admin User - Card-o-Bot</title>
    <link rel="stylesheet" href="<?php echo $assetPath; ?>/assets/css/base.css">
</head>
<body class="admin-page">
    <header class="header">
        <div class="header-content">
            <h1>👤 Add Admin User</h1>
            <div class="user-info">
                <a href="<?php echo $basePath; ?>/admin/dashboard.php" class="btn btn-secondary">Back to Admin</a>
            </div>
        </div>
    </header>
    
    <main class="container">
        <div class="admin-section">
            <div class="admin-section-header">
                <h2>Add Admin User to Database</h2>
            </div>
            <div class="admin-section-body">
                <p>This script will add the admin user from your <code>.env</code> file to the database.</p>
                <p><strong>Username:</strong> <?php echo htmlspecialchars($adminUsername); ?></p>
                
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
                
                <?php if (empty($error) && ($userAdded || $userExists)): ?>
                    <div class="alert alert-success" style="background: var(--color-success); color: var(--color-text-light); padding: var(--spacing-4); border-radius: var(--border-radius); margin-bottom: var(--spacing-4);">
                        <strong>✅ Admin user is ready!</strong>
                        <br><br>
                        <strong>Next steps:</strong><br>
                        1. Log out and log back in to refresh your session<br>
                        2. Your user account will now be in the database<br>
                        3. You can test Google OAuth linking from your profile page<br>
                        4. Visit <a href="<?php echo $basePath; ?>/profile.php" style="color: var(--color-text-light); text-decoration: underline;">your profile page</a> to manage your account
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
