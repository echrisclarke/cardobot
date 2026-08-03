<?php
/**
 * Path Verification Script
 * Checks all paths and URLs in admin directory
 */

require_once __DIR__ . '/../includes/auth.php';

// Require admin access
if (!is_admin()) {
    header('Location: ' . get_base_path() . '/index.php');
    exit;
}

$basePath = get_base_path();
$assetPath = get_asset_path();

$issues = [];
$checks = [];

// Check 1: Admin dashboard links
$checks[] = [
    'name' => 'Admin Dashboard Links',
    'file' => 'admin/dashboard.php',
    'checks' => [
        '/admin/users.php',
        '/admin/database.php',
        '/admin/status.php',
        '/admin/test-dashboard.php',
        '/admin/check-users.php',
        '/admin/tests/test-image.php',
        '/admin/database/setup.php'
    ]
];

// Check 2: Test dashboard endpoints
$checks[] = [
    'name' => 'Test Dashboard Endpoints',
    'file' => 'admin/test-dashboard.php',
    'checks' => [
        'tests/test-env.php',
        'tests/test-auth.php',
        'tests/test-db.php',
        'tests/test-google-oauth.php',
        'tests/test-image.php'
    ]
];

// Check 3: Test file require paths
$testFiles = [
    'admin/tests/test-env.php' => '../../includes/env.php',
    'admin/tests/test-auth.php' => '../../includes/auth.php',
    'admin/tests/test-db.php' => '../../includes/env.php',
    'admin/tests/test-image.php' => '../../includes/auth.php',
    'admin/tests/test-google-oauth.php' => '../../includes/google-auth.php',
    'admin/tests/test-profile.php' => '../../includes/auth.php',
    'admin/tests/test-google-callback-debug.php' => '../../includes/auth.php'
];

// Check 4: Admin file require paths
$adminFiles = [
    'admin/dashboard.php' => '../includes/auth.php',
    'admin/users.php' => '../includes/auth.php',
    'admin/database.php' => '../includes/auth.php',
    'admin/status.php' => '../includes/auth.php',
    'admin/test-dashboard.php' => '../includes/auth.php',
    'admin/check-users.php' => '../includes/env.php'
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Path Verification - Admin</title>
    <link rel="stylesheet" href="<?php echo $assetPath; ?>/assets/css/base.css">
</head>
<body class="admin-page">
    <header class="header">
        <div class="header-content">
            <h1>🔍 Path Verification</h1>
            <div class="user-info">
                <a href="<?php echo $basePath; ?>/admin/dashboard.php" class="btn btn-secondary">Dashboard</a>
            </div>
        </div>
    </header>
    
    <main class="container">
        <div class="admin-section">
            <div class="admin-section-header">
                <h2>Path Verification Results</h2>
            </div>
            <div class="admin-section-body">
                <h3>Base Paths</h3>
                <ul>
                    <li><strong>Base Path:</strong> <?php echo htmlspecialchars($basePath ?: '(empty - correct for cardobot.com)'); ?></li>
                    <li><strong>Asset Path:</strong> <?php echo htmlspecialchars($assetPath ?: '(empty - correct for cardobot.com)'); ?></li>
                </ul>

                <h3>Test File Require Paths</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Expected Path</th>
                            <th>File Exists</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($testFiles as $file => $expectedPath): ?>
                            <?php
                            $fullPath = __DIR__ . '/../' . str_replace('admin/', '', $file);
                            $includePath = __DIR__ . '/../' . $expectedPath;
                            $fileExists = file_exists($fullPath);
                            $includeExists = file_exists($includePath);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file); ?></td>
                                <td><?php echo htmlspecialchars($expectedPath); ?></td>
                                <td>
                                    <?php if ($fileExists && $includeExists): ?>
                                        ✅
                                    <?php else: ?>
                                        ❌
                                        <?php if (!$fileExists) echo "File missing"; ?>
                                        <?php if (!$includeExists) echo "Include missing"; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3>Admin File Require Paths</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>File</th>
                            <th>Expected Path</th>
                            <th>File Exists</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($adminFiles as $file => $expectedPath): ?>
                            <?php
                            $fullPath = __DIR__ . '/../' . str_replace('admin/', '', $file);
                            $includePath = __DIR__ . '/../' . $expectedPath;
                            $fileExists = file_exists($fullPath);
                            $includeExists = file_exists($includePath);
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($file); ?></td>
                                <td><?php echo htmlspecialchars($expectedPath); ?></td>
                                <td>
                                    <?php if ($fileExists && $includeExists): ?>
                                        ✅
                                    <?php else: ?>
                                        ❌
                                        <?php if (!$fileExists) echo "File missing"; ?>
                                        <?php if (!$includeExists) echo "Include missing"; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3>Test Endpoint Files</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Endpoint</th>
                            <th>Expected Location</th>
                            <th>Exists</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $endpoints = [
                            'tests/test-env.php',
                            'tests/test-auth.php',
                            'tests/test-db.php',
                            'tests/test-google-oauth.php',
                            'tests/test-image.php'
                        ];
                        foreach ($endpoints as $endpoint):
                            $fullPath = __DIR__ . '/' . $endpoint;
                            $exists = file_exists($fullPath);
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($endpoint); ?></td>
                                <td><?php echo htmlspecialchars($fullPath); ?></td>
                                <td><?php echo $exists ? '✅' : '❌'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3>API Endpoints</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Relative Path</th>
                            <th>From File</th>
                            <th>Resolves To</th>
                            <th>Exists</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $apiEndpoints = [
                            '../../api/generate-image.php' => ['From admin/tests/test-image.php', 'api/generate-image.php'],
                        ];
                        foreach ($apiEndpoints as $endpoint => $info):
                            list($context, $resolved) = $info;
                            $fullPath = __DIR__ . '/../' . $resolved;
                            $exists = file_exists($fullPath);
                        ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($endpoint); ?></code></td>
                                <td><?php echo htmlspecialchars($context); ?></td>
                                <td><code><?php echo htmlspecialchars($resolved); ?></code></td>
                                <td><?php echo $exists ? '✅' : '❌'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <h3>Admin Dashboard Links</h3>
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Link</th>
                            <th>Expected File</th>
                            <th>Exists</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $dashboardLinks = [
                            '/admin/users.php' => 'admin/users.php',
                            '/admin/database.php' => 'admin/database.php',
                            '/admin/status.php' => 'admin/status.php',
                            '/admin/test-dashboard.php' => 'admin/test-dashboard.php',
                            '/admin/check-users.php' => 'admin/check-users.php',
                            '/admin/tests/test-image.php' => 'admin/tests/test-image.php',
                            '/admin/database/setup.php' => 'admin/database/setup.php'
                        ];
                        foreach ($dashboardLinks as $link => $file):
                            $fullPath = __DIR__ . '/../' . $file;
                            $exists = file_exists($fullPath);
                        ?>
                            <tr>
                                <td><code><?php echo htmlspecialchars($link); ?></code></td>
                                <td><code><?php echo htmlspecialchars($file); ?></code></td>
                                <td><?php echo $exists ? '✅' : '❌'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</body>
</html>
