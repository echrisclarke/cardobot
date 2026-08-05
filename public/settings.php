<?php
/**
 * Account Settings Page
 * Manage account settings, link/unlink Google account
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google-auth.php';

// Require authentication
require_auth();

$user = get_logged_in_user();
$userId = $user['id'] ?? null;
$assetPath = get_asset_path();
$basePath = get_base_path();

$error = '';
$success = '';

if (isset($_GET['linked']) && $_GET['linked'] === '1') {
    $success = 'Google account linked successfully!';
}

if (isset($_GET['error']) && $_GET['error'] !== '') {
    $error = (string)$_GET['error'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'unlink_google') {
        // Unlink Google account
        $result = unlink_google_account($userId);
        if ($result['success']) {
            $success = $result['message'];
            // Refresh user data
            $user = get_logged_in_user();
        } else {
            $error = $result['message'];
        }
    }
}

// Direct Google OAuth on this host (Railway / cardobot.com).
$linkGoogleHref = $basePath . '/api/google-start.php?mode=link';

$hasGoogleLinked = has_google_linked($userId);
$hasPassword = !empty($user['password_hash']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - Card-o-Bot</title>
    <link rel="stylesheet" href="<?php echo $assetPath; ?>/assets/css/base.css">
</head>
<body>
    <header class="header">
        <div class="header-content">
            <h1>⚙️ Account Settings</h1>
            <div class="user-info">
                <span class="username"><?php echo htmlspecialchars($user['username'] ?? 'User'); ?></span>
                <a href="<?php echo $basePath; ?>/index.php" class="btn btn-secondary">Back</a>
            </div>
        </div>
    </header>
    
    <main class="container container-narrow">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h2>Account Information</h2>
            </div>
            <div class="card-body">
                <dl class="account-info">
                    <dt>Username:</dt>
                    <dd><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></dd>
                    
                    <dt>Email:</dt>
                    <dd><?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?></dd>
                    
                    <dt>Name:</dt>
                    <dd><?php echo htmlspecialchars($user['name'] ?? 'Not set'); ?></dd>
                </dl>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h2>Login Methods</h2>
            </div>
            <div class="card-body">
                <div class="login-method">
                    <div class="method-status">
                        <strong>Password Login:</strong>
                        <span class="status-badge <?php echo $hasPassword ? 'status-pass' : 'status-fail'; ?>">
                            <?php echo $hasPassword ? 'Enabled' : 'Not Set'; ?>
                        </span>
                    </div>
                    <?php if (!$hasPassword): ?>
                        <p class="text-muted">You don't have a password set. You can set one here or link a Google account.</p>
                    <?php endif; ?>
                </div>
                
                <div class="login-method">
                    <div class="method-status">
                        <strong>Google Login:</strong>
                        <span class="status-badge <?php echo $hasGoogleLinked ? 'status-pass' : 'status-fail'; ?>">
                            <?php echo $hasGoogleLinked ? 'Linked' : 'Not Linked'; ?>
                        </span>
                    </div>
                    
                    <?php if ($hasGoogleLinked): ?>
                        <p class="text-muted">Your Google account is linked. You can sign in with Google or your password.</p>
                        <form method="POST" class="inline-form">
                            <input type="hidden" name="action" value="unlink_google">
                            <button type="submit" class="btn btn-secondary" onclick="return confirm('Are you sure you want to unlink your Google account? You will need a password to log in.')">
                                Unlink Google Account
                            </button>
                        </form>
                    <?php else: ?>
                        <p class="text-muted">Link your Google account to enable Google sign-in.</p>
                        <?php if (is_google_oauth_configured()): ?>
                            <a href="<?php echo htmlspecialchars($linkGoogleHref); ?>" class="btn btn-primary">
                                Link Google Account
                            </a>
                        <?php else: ?>
                            <p class="text-muted">Google OAuth is not configured on this server.</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
