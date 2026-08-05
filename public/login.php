<?php
/**
 * Login Page for Card-o-Bot
 */

require_once __DIR__ . '/includes/auth.php';
auth_boot(true);
cardobot_ensure_test_user();

$basePath = get_base_path();

// Try to load Google auth, but don't fail if it doesn't exist or isn't configured
$googleEnabled = false;
if (file_exists(__DIR__ . '/includes/google-auth.php')) {
    try {
        require_once __DIR__ . '/includes/google-auth.php';
        $googleEnabled = is_google_oauth_configured();
    } catch (Exception $e) {
        // Google auth not available or not configured, continue without it
        $googleEnabled = false;
    }
}

// Direct Google OAuth on this host (Railway / cardobot.com). No Bluehost handoff.
$googleStartUrl = $basePath . '/api/google-start.php';
if (!empty($_GET['redirect'])) {
    $googleStartUrl .= '?' . http_build_query(['redirect' => (string)$_GET['redirect']]);
}

// If already logged in, redirect to main app
if (is_logged_in()) {
    header('Location: ' . $basePath . '/index.php');
    exit;
}

$error = $_GET['error'] ?? '';
$success = '';
$showRegister = false;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'login';
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if ($action === 'register') {
        // Register new user
        $result = create_user($username, $password);
        if ($result['success']) {
            $success = $result['message'];
            $showRegister = false; // Switch to login form
        } else {
            $error = $result['message'];
            $showRegister = true;
        }
    } else {
        // Login
        $result = authenticate_user($username, $password);
        if ($result['success']) {
            login_user($result['user']);
            
            // Redirect to requested page or main app
            $redirect = $_GET['redirect'] ?? $basePath . '/index.php';
            header('Location: ' . $redirect);
            exit;
        } else {
            $error = $result['message'];
        }
    }
}

// Check if register link was clicked
if (isset($_GET['register'])) {
    $showRegister = true;
}

require_once __DIR__ . '/includes/console.php';

// Start console wrapper
console_start($showRegister ? 'Create Account - Card-o-Bot' : 'Login - Card-o-Bot', true);
?>
                <h1 class="text-center" style="margin-bottom: 1rem;">Card-o-Bot</h1>
                <p class="subtitle text-center" style="margin-bottom: 1.5rem;"><?php echo $showRegister ? 'Create your account' : 'Sign in to your account'; ?></p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                <?php endif; ?>
                
                <?php if ($googleEnabled): ?>
                    <a href="<?php echo htmlspecialchars($googleStartUrl); ?>" class="btn-google">
                        <svg viewBox="0 0 24 24">
                            <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                            <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                            <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"/>
                            <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"/>
                        </svg>
                        Sign in with Google
                    </a>

                    <div class="divider">or</div>
                <?php endif; ?>
                
                <!-- Login Form -->
                <form method="POST" action="" id="loginForm" class="<?php echo $showRegister ? 'hidden' : ''; ?>">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label for="login_username">Username</label>
                        <input type="text" id="login_username" name="username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               autocomplete="username">
                    </div>
                    
                    <div class="form-group">
                        <label for="login_password">Password</label>
                        <input type="password" id="login_password" name="password" required 
                               autocomplete="current-password">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Sign In</button>
                </form>
                
                <!-- Register Form -->
                <form method="POST" action="" id="registerForm" class="<?php echo $showRegister ? '' : 'hidden'; ?>">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="form-group">
                        <label for="register_username">Username</label>
                        <input type="text" id="register_username" name="username" required 
                               value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                               autocomplete="username"
                               pattern="[a-zA-Z0-9_-]{3,50}" 
                               title="3-50 characters, letters, numbers, underscores, and hyphens only">
                    </div>
                    
                    <div class="form-group">
                        <label for="register_password">Password</label>
                        <input type="password" id="register_password" name="password" required 
                               autocomplete="new-password"
                               minlength="4"
                               title="At least 4 characters">
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </form>
                
                <div class="toggle-form">
                    <?php if ($showRegister): ?>
                        <span>Already have an account? <a href="?">Sign in</a></span>
                    <?php else: ?>
                        <span>Don't have an account? <a href="?register=1">Create one</a></span>
                    <?php endif; ?>
                </div>

                <nav class="login-legal-links" aria-label="Legal links">
                    <a href="<?php echo $basePath; ?>/privacy.php">Privacy Policy</a>
                    <span aria-hidden="true">|</span>
                    <a href="<?php echo $basePath; ?>/terms.php">Terms of Service</a>
                </nav>
                
                <div style="text-align: center; margin-top: 1.5rem; padding-top: 1rem; border-top: 1px solid var(--color-border-gray);">
                    <p style="font-size: 0.75rem; color: var(--color-text-secondary); margin: 0;">
                        ⚠️ Warning: This site contains flashing/flickering effects that may trigger photosensitive epilepsy.
                    </p>
            </div>
<?php
// End console wrapper
console_end();
?>
