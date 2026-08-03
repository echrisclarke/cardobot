<?php
/**
 * Check Users in Database
 * Lists all users and their information
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/auth.php';

// Require admin access
if (!is_admin()) {
    header('Location: ' . get_base_path() . '/index.php');
    exit;
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><title>User Check</title>";
echo "<style>body{font-family:monospace;padding:20px;} table{border-collapse:collapse;width:100%;} th,td{border:1px solid #ddd;padding:8px;text-align:left;} th{background:#f2f2f2;}</style>";
echo "</head><body>";
echo "<h1>Card-o-Bot Users Database Check</h1>";

// Get database connection
$pdo = get_auth_db();
if (!$pdo) {
    die("❌ Failed to connect to database. Check your .env file.");
}

echo "<p>✅ Connected to database successfully</p>";

// Check if table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'cardobot_users'");
    $tableExists = $stmt->rowCount() > 0;
    
    if (!$tableExists) {
        die("❌ Table 'cardobot_users' does not exist. Run admin/database/setup.php first.");
    }
    
    echo "<p>✅ Table 'cardobot_users' exists</p>";
} catch (PDOException $e) {
    die("❌ Error checking table: " . $e->getMessage());
}

// Get all users
try {
    $stmt = $pdo->query("SELECT * FROM cardobot_users ORDER BY id ASC");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Users (" . count($users) . " total)</h2>";
    
    if (count($users) === 0) {
        echo "<p>⚠️ No users found in database.</p>";
    } else {
        echo "<table>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Username</th>";
        echo "<th>Email</th>";
        echo "<th>Name</th>";
        echo "<th>Has Password</th>";
        echo "<th>Google ID</th>";
        echo "<th>Auth Method</th>";
        echo "<th>Is Admin</th>";
        echo "<th>Created At</th>";
        echo "<th>Last Login</th>";
        echo "</tr>";
        
        foreach ($users as $user) {
            $hasPassword = !empty($user['password_hash']) ? '✅ Yes' : '❌ No';
            $hasGoogle = !empty($user['google_id']) ? '✅ Yes' : '❌ No';
            $isAdmin = !empty($user['is_admin']) ? '✅ Yes' : '❌ No';
            
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id'] ?? 'N/A') . "</td>";
            echo "<td><strong>" . htmlspecialchars($user['username'] ?? 'N/A') . "</strong></td>";
            echo "<td>" . htmlspecialchars($user['email'] ?? 'Not set') . "</td>";
            echo "<td>" . htmlspecialchars($user['name'] ?? 'Not set') . "</td>";
            echo "<td>" . $hasPassword . "</td>";
            echo "<td>" . ($hasGoogle === '✅ Yes' ? substr($user['google_id'], 0, 20) . '...' : 'No') . "</td>";
            echo "<td>" . htmlspecialchars($user['auth_method'] ?? 'password') . "</td>";
            echo "<td>" . $isAdmin . "</td>";
            echo "<td>" . htmlspecialchars($user['created_at'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($user['last_login'] ?? 'Never') . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
        
        // Summary
        echo "<h2>Summary</h2>";
        echo "<ul>";
        $withPassword = count(array_filter($users, function($u) { return !empty($u['password_hash']); }));
        $withGoogle = count(array_filter($users, function($u) { return !empty($u['google_id']); }));
        $withEmail = count(array_filter($users, function($u) { return !empty($u['email']); }));
        $admins = count(array_filter($users, function($u) { return !empty($u['is_admin']); }));
        
        echo "<li>Total users: " . count($users) . "</li>";
        echo "<li>Users with password: " . $withPassword . "</li>";
        echo "<li>Users with Google linked: " . $withGoogle . "</li>";
        echo "<li>Users with email: " . $withEmail . "</li>";
        echo "<li>Admin users: " . $admins . "</li>";
        echo "</ul>";
        
        // Check for issues
        echo "<h2>Issues Found</h2>";
        $issues = [];
        
        foreach ($users as $user) {
            if (empty($user['id'])) {
                $issues[] = "User '{$user['username']}' has no ID";
            }
            if (empty($user['username'])) {
                $issues[] = "User ID {$user['id']} has no username";
            }
            if (empty($user['password_hash']) && empty($user['google_id'])) {
                $issues[] = "User '{$user['username']}' has no password and no Google account linked";
            }
            if (empty($user['created_at'])) {
                $issues[] = "User '{$user['username']}' has no created_at timestamp";
            }
        }
        
        if (empty($issues)) {
            echo "<p>✅ No issues found! All users look good.</p>";
        } else {
            echo "<ul>";
            foreach ($issues as $issue) {
                echo "<li>⚠️ " . htmlspecialchars($issue) . "</li>";
            }
            echo "</ul>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p>❌ Error getting users: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
