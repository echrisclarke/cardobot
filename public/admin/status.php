<?php
/**
 * System Status Page
 * Admin-only page to view system health and configuration
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/env.php';

// Require admin access
if (!is_admin()) {
    header('Location: ' . get_base_path() . '/index.php');
    exit;
}

$assetPath = get_asset_path();
$basePath = get_base_path();

// Get database connection
$pdo = get_auth_db();

// Get system stats
$stats = [
    'users' => 0,
    'cards' => 0,
    'sessions' => 0,
    'tables' => []
];

if ($pdo) {
    try {
        // Count users
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM cardobot_users");
        $stats['users'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Count cards
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM cardobot_cards");
        $stats['cards'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Count sessions
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM cardobot_sessions");
        $stats['sessions'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
        
        // Get all tables with row counts
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($tables as $table) {
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM `{$table}`");
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
            $stats['tables'][$table] = $count;
        }
    } catch (PDOException $e) {
        // Error handled below
    }
}

// Get environment info (without sensitive data)
$envInfo = [
    'OpenAI API Key' => get_openai_api_key() ? '✅ Set' : '❌ Not set',
    'OpenAI Image Model' => get_openai_image_model() ?? 'Not set',
    'OpenAI Text Model' => get_openai_text_model() ?? 'Not set',
    'Database Host' => get_db_host() ?? 'Not set',
    'Database Name' => get_db_name() ?? 'Not set',
    'Database User' => get_db_user() ? '✅ Set' : '❌ Not set',
    'Google OAuth Client ID' => get_google_client_id() ? '✅ Set' : '❌ Not set',
    'Google OAuth Client Secret' => get_google_client_secret() ? '✅ Set' : '❌ Not set',
];

// Get PHP info
$phpInfo = [
    'PHP Version' => PHP_VERSION,
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'Current Domain' => $_SERVER['HTTP_HOST'] ?? 'Unknown',
    'Session Status' => session_status() === PHP_SESSION_ACTIVE ? '✅ Active' : '❌ Not active',
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Status - Admin - Card-o-Bot</title>
    <link rel="stylesheet" href="<?php echo $assetPath; ?>/assets/css/base.css">
</head>
<body class="admin-page">
    <header class="header">
        <div class="header-content">
            <h1>📊 System Status</h1>
            <div class="user-info">
                <span class="username"><?php echo htmlspecialchars(get_username() ?? 'Admin'); ?></span>
                <a href="<?php echo $basePath; ?>/admin/dashboard.php" class="btn btn-secondary">Dashboard</a>
                <a href="<?php echo $basePath; ?>/index.php" class="btn btn-secondary">App</a>
                <a href="<?php echo $basePath; ?>/logout.php" class="btn btn-primary">Logout</a>
            </div>
        </div>
    </header>
    
    <main class="container">
        <!-- Database Stats -->
        <div class="admin-section">
            <div class="admin-section-header">
                <h2>📊 Database Statistics</h2>
            </div>
            <div class="admin-section-body">
                <?php if (!$pdo): ?>
                    <p class="alert alert-error">❌ Database connection failed</p>
                <?php else: ?>
                    <div class="stats-grid">
                        <div class="admin-stat-card">
                            <div class="stat-value"><?php echo number_format($stats['users']); ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="admin-stat-card">
                            <div class="stat-value"><?php echo number_format($stats['cards']); ?></div>
                            <div class="stat-label">Total Cards</div>
                        </div>
                        <div class="admin-stat-card">
                            <div class="stat-value"><?php echo number_format($stats['sessions']); ?></div>
                            <div class="stat-label">Active Sessions</div>
                        </div>
                    </div>
                    
                    <h3>Table Row Counts</h3>
                    <div class="table-responsive">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th>Table Name</th>
                                    <th>Row Count</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stats['tables'] as $table => $count): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($table); ?></strong></td>
                                        <td><?php echo number_format($count); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Environment Configuration -->
        <div class="admin-section">
            <div class="admin-section-header">
                <h2>⚙️ Environment Configuration</h2>
            </div>
            <div class="admin-section-body">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($envInfo as $key => $value): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($key); ?></strong></td>
                                    <td><?php echo $value; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- PHP Information -->
        <div class="admin-section">
            <div class="admin-section-header">
                <h2>🐘 PHP Information</h2>
            </div>
            <div class="admin-section-body">
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>Setting</th>
                                <th>Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($phpInfo as $key => $value): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($key); ?></strong></td>
                                    <td><?php echo htmlspecialchars($value); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Database Connection Test -->
        <div class="admin-section">
            <div class="admin-section-header">
                <h2>🔌 Database Connection</h2>
            </div>
            <div class="admin-section-body">
                <?php if ($pdo): ?>
                    <p class="alert alert-success">✅ Database connection successful</p>
                    <?php
                    try {
                        $stmt = $pdo->query("SELECT DATABASE() as db_name, USER() as db_user, VERSION() as db_version");
                        $dbInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <tbody>
                                    <tr>
                                        <td><strong>Database Name</strong></td>
                                        <td><?php echo htmlspecialchars($dbInfo['db_name'] ?? 'Unknown'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>Database User</strong></td>
                                        <td><?php echo htmlspecialchars($dbInfo['db_user'] ?? 'Unknown'); ?></td>
                                    </tr>
                                    <tr>
                                        <td><strong>MySQL Version</strong></td>
                                        <td><?php echo htmlspecialchars($dbInfo['db_version'] ?? 'Unknown'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    <?php
                    } catch (PDOException $e) {
                        echo '<p class="alert alert-error">Error getting database info: ' . htmlspecialchars($e->getMessage()) . '</p>';
                    }
                    ?>
                <?php else: ?>
                    <p class="alert alert-error">❌ Database connection failed. Check your .env file.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
