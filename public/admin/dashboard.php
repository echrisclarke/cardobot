<?php
/**
 * Admin Dashboard
 * Main admin control panel
 */

require_once __DIR__ . '/../includes/auth.php';

// Require admin access
if (!is_admin()) {
    header('Location: ' . get_base_path() . '/index.php');
    exit;
}

$assetPath = get_asset_path();
$basePath = get_base_path();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Card-o-Bot</title>
    <link rel="stylesheet" href="<?php echo $assetPath; ?>/assets/css/base.css">
</head>
<body class="admin-page">
    <header class="header">
        <div class="header-content">
            <h1>⚙️ Admin Dashboard</h1>
            <div class="user-info">
                <span class="username"><?php echo htmlspecialchars(get_username() ?? 'Admin'); ?></span>
                <a href="<?php echo $basePath; ?>/index.php" class="btn btn-secondary">Back to App</a>
                <a href="<?php echo $basePath; ?>/logout.php" class="btn btn-primary">Logout</a>
            </div>
        </div>
    </header>
    
    <main class="container">
        <div class="admin-section">
            <div class="admin-section-header">
                <h2>🔧 Administration Panel</h2>
            </div>
            <div class="admin-section-body">
                <p>Welcome to the Card-o-Bot administration dashboard. Manage users, view database tables, and monitor system health.</p>
            </div>
        </div>

        <div class="admin-modules">
            <div class="admin-module admin-section">
                <div class="admin-section-header">
                    <h3>👥 User Management</h3>
                </div>
                <div class="admin-section-body">
                    <p>Add, edit, remove users and manage their accounts.</p>
                    <a href="<?php echo $basePath; ?>/admin/users.php" class="btn btn-primary">Manage Users</a>
                </div>
            </div>

            <div class="admin-module admin-section">
                <div class="admin-section-header">
                    <h3>🗄️ Database Browser</h3>
                </div>
                <div class="admin-section-body">
                    <p>View and edit database tables, run queries, and manage data.</p>
                    <a href="<?php echo $basePath; ?>/admin/database.php" class="btn btn-primary">Browse Database</a>
                </div>
            </div>

            <div class="admin-module admin-section">
                <div class="admin-section-header">
                    <h3>📊 System Status</h3>
                </div>
                <div class="admin-section-body">
                    <p>View system health, database stats, and configuration.</p>
                    <a href="<?php echo $basePath; ?>/admin/status.php" class="btn btn-primary">View Status</a>
                </div>
            </div>

            <div class="admin-module admin-section">
                <div class="admin-section-header">
                    <h3>🧪 Test Dashboard</h3>
                </div>
                <div class="admin-section-body">
                    <p>Run automated tests for all system components.</p>
                    <a href="<?php echo $basePath; ?>/admin/test-dashboard.php" class="btn btn-primary">Test Dashboard</a>
                </div>
            </div>

            <div class="admin-module admin-section">
                <div class="admin-section-header">
                    <h3>🔍 Check Users</h3>
                </div>
                <div class="admin-section-body">
                    <p>Quick view of all users in the database.</p>
                    <a href="<?php echo $basePath; ?>/admin/check-users.php" class="btn btn-primary">Check Users</a>
                </div>
            </div>

            <div class="admin-module admin-section">
                <div class="admin-section-header">
                    <h3>🖼️ Test Image Generation</h3>
                </div>
                <div class="admin-section-body">
                    <p>Test the OpenAI image generation API.</p>
                    <a href="<?php echo $basePath; ?>/admin/tests/test-image.php" class="btn btn-primary">Test Images</a>
                </div>
            </div>

            <div class="admin-module admin-section">
                <div class="admin-section-header">
                    <h3>⚙️ Database Setup</h3>
                </div>
                <div class="admin-section-body">
                    <p>Create database tables and initialize schema.</p>
                    <a href="<?php echo $basePath; ?>/admin/database/setup.php" class="btn btn-primary">Setup Database</a>
                </div>
            </div>

            <div class="admin-module admin-section">
                <div class="admin-section-header">
                    <h3>👤 Add Admin User</h3>
                </div>
                <div class="admin-section-body">
                    <p>Add your admin user from .env to the database.</p>
                    <a href="<?php echo $basePath; ?>/admin/database/add-admin-user.php" class="btn btn-primary">Add Admin User</a>
                </div>
            </div>

        </div>
    </main>
</body>
</html>
