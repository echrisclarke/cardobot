<?php
/**
 * Account Linking Page
 * Allows users to link their Google account to an existing account
 */

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/google-auth.php';

$assetPath = get_asset_path();
$basePath = get_base_path();
require_once __DIR__ . '/includes/console.php';

auth_boot(true);

$pendingLink = $_SESSION['pending_google_link'] ?? null;

if (!$pendingLink) {
    // No pending link, redirect to login
    header('Location: ' . $basePath . '/login.php');
    exit;
}

$existingUsername = $pendingLink['existing_username'] ?? 'Unknown';
$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'link') {
        // User wants to link accounts
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        // Verify credentials
        $result = authenticate_user($username, $password);
        if ($result['success']) {
            // Verify this is the correct user
            if ($result['user']['username'] === $pendingLink['existing_username']) {
                // Link the Google account
                $linkResult = link_google_account($result['user']['id'], [
                    'id' => $pendingLink['google_id'],
                    'email' => $pendingLink['email'],
                    'name' => $pendingLink['name'],
                    'given_name' => $pendingLink['given_name'],
                    'family_name' => $pendingLink['family_name'],
                    'picture' => $pendingLink['picture']
                ]);
                
                if ($linkResult['success']) {
                    // Clear pending link
                    unset($_SESSION['pending_google_link']);
                    // Log in the user
                    login_user($result['user']);
                    // Redirect to main app
                    header('Location: ' . $basePath . '/index.php?linked=1');
                    exit;
                } else {
                    $error = $linkResult['message'];
                }
            } else {
                $error = 'The username does not match the account associated with this email.';
            }
        } else {
            $error = $result['message'];
        }
    } elseif ($action === 'cancel') {
        // User wants to create a new account instead
        unset($_SESSION['pending_google_link']);
        // Create new account with Google
        $userResult = find_or_create_google_user([
            'id' => $pendingLink['google_id'],
            'email' => $pendingLink['email'],
            'name' => $pendingLink['name'],
            'given_name' => $pendingLink['given_name'],
            'family_name' => $pendingLink['family_name'],
            'picture' => $pendingLink['picture']
        ]);
        
        if ($userResult['success']) {
            login_user($userResult['user']);
            header('Location: ' . $basePath . '/index.php');
            exit;
        } else {
            $error = $userResult['message'];
        }
    }
}

// Start console wrapper
console_start('Link Google Account - Card-o-Bot');
?>
                <h1 style="margin-bottom: 1rem;">Link Google Account</h1>
                <p style="margin-bottom: 0.5rem;">We found an existing account with the email <strong><?php echo htmlspecialchars($pendingLink['email'] ?? ''); ?></strong>.</p>
                <p style="margin-bottom: 1.5rem;">Would you like to link your Google account to this existing account?</p>
                
                <?php if ($error): ?>
                    <div class="alert alert-error">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="link-account-form">
                    <input type="hidden" name="action" value="link">
                    
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($existingUsername); ?>" required class="form-control" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required class="form-control" placeholder="Enter your password to confirm">
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Link Accounts</button>
                        <button type="submit" formaction="<?php echo $_SERVER['PHP_SELF']; ?>" name="action" value="cancel" class="btn btn-secondary">Create New Account Instead</button>
                    </div>
                </form>
                
                <div class="alert alert-info mt-4">
                    <strong>Note:</strong> Linking your Google account will allow you to sign in with either your password or Google.
                </div>
            </div>
        </div>
<?php
// End console wrapper
console_end();
?>
