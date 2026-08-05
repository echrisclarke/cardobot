<?php
/**
 * Card-o-Bot Profile & Collection Hub
 * Your personal Card-o-Bot command center
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/includes/auth.php';

// Require authentication first (before loading other files that might fail)
require_auth();

// Load other includes after auth check
require_once __DIR__ . '/includes/google-auth.php';
require_once __DIR__ . '/includes/cards.php';
require_once __DIR__ . '/includes/profile.php';

$user = get_logged_in_user();
if (!$user || empty($user['username'])) {
    header('Location: ' . get_base_path() . '/login.php');
    exit;
}

$userId = ensure_user_row();
if (!$userId) {
    error_log("Profile page: ensure_user_row() failed - username: " . ($user['username'] ?? 'unknown'));
    die("Error: Could not load your account. Please try logging in again.");
}

$userFromDb = get_user_by_id($userId);
if (!$userFromDb) {
    error_log("Profile page: ensure_user_row returned id={$userId} but get_user_by_id failed");
    die("Error: Could not load your account. Please try logging in again.");
}

$user = $userFromDb;

// Update session with full user data (but don't store password_hash in session)
$sessionUser = $_SESSION['user'] ?? [];
$_SESSION['user'] = array_merge($sessionUser, [
    'id' => $userId,
    'email' => $user['email'] ?? null,
    'name' => $user['name'] ?? null,
    'created_at' => $user['created_at'] ?? null
]);
// Note: We don't store password_hash in session for security

$assetPath = get_asset_path();
$basePath = get_base_path();
require_once __DIR__ . '/includes/console.php';

$error = '';
$success = '';
$activeTab = $_GET['tab'] ?? 'collection'; // collection, profile, security

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'change_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // If user doesn't have a password, current password is optional
        $needsCurrentPassword = !empty($user['password_hash']);
        
        if ($needsCurrentPassword && empty($currentPassword)) {
            $error = 'Current password is required';
            $activeTab = 'security';
        } elseif ($newPassword !== $confirmPassword) {
            $error = 'New passwords do not match';
            $activeTab = 'security';
        } else {
            $result = change_password($userId, $currentPassword, $newPassword);
            if ($result['success']) {
                $success = $result['message'];
                // Refresh user data from database
                $user = get_user_by_username($user['username']);
                if ($user) {
                    $userFromDb = $user;
                }
                $hasPassword = !empty($user['password_hash'] ?? '');
                $activeTab = 'security';
            } else {
                $error = $result['message'];
                $activeTab = 'security';
            }
        }
    } elseif ($action === 'change_username') {
        $newUsername = trim($_POST['new_username'] ?? '');
        $result = change_username($userId, $newUsername);
        if ($result['success']) {
            $success = $result['message'];
            // Refresh user data from database
            $user = get_user_by_username($newUsername);
            if ($user) {
                $userFromDb = $user;
                $userId = (int)$user['id'];
            }
            $activeTab = 'profile';
        } else {
            $error = $result['message'];
            $activeTab = 'profile';
        }
    } elseif ($action === 'link_google') {
        auth_boot(true);
        $_SESSION['link_google_account'] = true;
        $state = generate_google_state();
        $authUrl = get_google_auth_url($state);
        header('Location: ' . $authUrl);
        exit;
    } elseif ($action === 'unlink_google') {
        $result = unlink_google_account($userId);
        if ($result['success']) {
            $success = $result['message'];
            $user = get_logged_in_user();
            $activeTab = 'security';
        } else {
            $error = $result['message'];
            $activeTab = 'security';
        }
    } elseif ($action === 'update_profile') {
        // Only allow profile updates if Google is not linked
        if (!$hasGoogleLinked) {
            $profileData = [];
            
            // Handle name
            if (isset($_POST['name'])) {
                $profileData['name'] = trim($_POST['name'] ?? '');
            }
            
            // Handle email
            if (isset($_POST['email'])) {
                $profileData['email'] = trim($_POST['email'] ?? '');
            }
            
            // Handle profile picture upload
            if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = save_profile_picture($_FILES['profile_picture'], $userId);
                if ($uploadResult['success']) {
                    // Delete old picture if it exists
                    if (!empty($user['picture'])) {
                        delete_old_profile_picture($user['picture'], $userId);
                    }
                    $profileData['picture'] = $uploadResult['url'];
                } else {
                    $error = $uploadResult['message'];
                    $activeTab = 'profile';
                }
            }
            
            // Update profile if no errors
            if (empty($error) && !empty($profileData)) {
                $result = update_user_profile($userId, $profileData);
                if ($result['success']) {
                    $success = $result['message'];
                    // Refresh user data from database
                    $user = get_user_by_username($user['username']);
                    if ($user) {
                        $userFromDb = $user;
                        // Update session with new profile data
                        $_SESSION['user']['name'] = $user['name'] ?? null;
                        $_SESSION['user']['email'] = $user['email'] ?? null;
                        $_SESSION['user']['picture'] = $user['picture'] ?? null;
                    }
                    // Re-check Google link status after update
                    $hasGoogleLinked = has_google_linked($userId);
                    $activeTab = 'profile';
                } else {
                    $error = $result['message'];
                    $activeTab = 'profile';
                }
            } else {
                $activeTab = 'profile';
            }
        } else {
            $error = 'Profile information is managed through your Google account';
            $activeTab = 'profile';
        }
    }
}

// User ID should already be validated above, but ensure it's an integer
$userId = (int)$userId;

// Get user stats with error handling
$userCards = [];
$cardCount = 0;
$botCount = 0;
$critterCount = 0;

try {
    if (function_exists('get_user_cards')) {
        $userCards = get_user_cards($userId);
        $cardCount = count($userCards);
    }
} catch (Exception $e) {
    error_log("Error getting user cards: " . $e->getMessage());
}

try {
    if (function_exists('get_user_cards_by_type')) {
        $botCount = count(get_user_cards_by_type($userId, 'BOT'));
        $critterCount = count(get_user_cards_by_type($userId, 'CRITTER'));
    }
} catch (Exception $e) {
    error_log("Error getting cards by type: " . $e->getMessage());
}

try {
    if (function_exists('has_google_linked')) {
        $hasGoogleLinked = has_google_linked($userId);
    } else {
        $hasGoogleLinked = false;
    }
} catch (Exception $e) {
    error_log("Error checking Google link: " . $e->getMessage());
    $hasGoogleLinked = false;
}

$hasPassword = !empty($user['password_hash'] ?? '');

// Calculate account age with error handling
$accountAgeText = 'Just created!';
try {
    if (!empty($user['created_at'])) {
        $createdAt = new DateTime($user['created_at']);
        $now = new DateTime();
        $accountAge = $createdAt->diff($now);
        
        if ($accountAge->y > 0) {
            $accountAgeText = $accountAge->y . ' year' . ($accountAge->y > 1 ? 's' : '');
        } elseif ($accountAge->m > 0) {
            $accountAgeText = $accountAge->m . ' month' . ($accountAge->m > 1 ? 's' : '');
        } elseif ($accountAge->d > 0) {
            $accountAgeText = $accountAge->d . ' day' . ($accountAge->d > 1 ? 's' : '');
        }
    }
} catch (Exception $e) {
    error_log("Error calculating account age: " . $e->getMessage());
    // Use default "Just created!" text
}

// Start console wrapper
console_start('My Collection - Card-o-Bot');
$assetPath = get_asset_path();
?>
<link rel="stylesheet" href="<?php echo cardobot_asset_url('assets/css/studio.css'); ?>">
<link rel="stylesheet" href="<?php echo cardobot_asset_url('assets/css/card-viewer.css'); ?>">
<script src="<?php echo cardobot_asset_url('assets/js/card-layout.js'); ?>"></script>
<script src="<?php echo cardobot_asset_url('assets/js/drawing-engine.js'); ?>"></script>
<script src="<?php echo cardobot_asset_url('assets/js/card-studio.js'); ?>"></script>
<script src="<?php echo cardobot_asset_url('assets/js/card-viewer.js'); ?>"></script>
        <!-- Profile Header -->
        <div class="profile-header card">
            <div class="profile-header-content">
                <div class="profile-avatar">
                    <?php if (!empty($user['picture'])): ?>
                        <img src="<?php echo htmlspecialchars($user['picture']); ?>" alt="Profile" class="avatar-img">
                    <?php else: ?>
                        <div class="avatar-placeholder"><?php echo strtoupper(substr($user['username'] ?? 'U', 0, 1)); ?></div>
                    <?php endif; ?>
                </div>
                <div class="profile-info">
                    <h2 class="profile-name"><?php echo htmlspecialchars($user['name'] ?? $user['username'] ?? 'Card Collector'); ?></h2>
                    <p class="profile-username">@<?php echo htmlspecialchars($user['username'] ?? 'collector'); ?></p>
                    <?php if (!empty($user['email'])): ?>
                        <p class="profile-email">📧 <?php echo htmlspecialchars($user['email']); ?></p>
                    <?php endif; ?>
                    <p class="profile-stats">
                        <span class="stat-badge"><?php echo $cardCount; ?> Cards</span>
                        <span class="stat-badge"><?php echo (int)$cardCount; ?> cards</span>
                        <span class="stat-badge">⭐ Collector for <?php echo $accountAgeText; ?></span>
                    </p>
                </div>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>⚠️ Alert:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>✅ Success:</strong> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Tabs -->
        <div class="profile-tabs">
            <a href="?tab=collection" class="tab <?php echo $activeTab === 'collection' ? 'active' : ''; ?>">
                My Collection
            </a>
            <a href="?tab=profile" class="tab <?php echo $activeTab === 'profile' ? 'active' : ''; ?>">
                👤 Profile
            </a>
            <a href="?tab=security" class="tab <?php echo $activeTab === 'security' ? 'active' : ''; ?>">
                🔒 Security
            </a>
        </div>

        <!-- Collection Tab -->
        <?php if ($activeTab === 'collection'): ?>
            <div class="card collection-section">
                <div class="card-header">
                    <h2>Your Card Collection</h2>
                </div>
                <div class="card-body">
                    <?php if ($cardCount === 0): ?>
                        <div class="empty-collection">
                            <div class="empty-icon">📭</div>
                            <h3>Your Collection is Empty</h3>
                            <p>Start building your collection by creating your first card!</p>
                            <a href="<?php echo $basePath; ?>/index.php" class="btn btn-primary">Create Your First Card</a>
                        </div>
                    <?php else: ?>
                        <div class="collection-grid">
                            <?php foreach ($userCards as $card):
                                $cid = $card['card_id'] ?? '';
                                $viewUrl = $basePath . '/api/download-card.php?card_id=' . rawurlencode($cid);
                                $dlUrl = $viewUrl . '&download=1';
                                $imgSrc = !empty($card['image_url']) ? $card['image_url'] : $viewUrl;
                            ?>
                                <?php
                                  $attrs = [];
                                  if (!empty($card['attributes_json'])) {
                                      $decoded = json_decode($card['attributes_json'], true);
                                      if (is_array($decoded)) $attrs = $decoded;
                                  }
                                  $artUrl = $attrs['art_url'] ?? $viewUrl;
                                  $payload = [
                                      'sessionId' => $cid,
                                      'artUrl' => $artUrl,
                                      'concept' => [
                                          'nickname' => $card['nickname'] ?? '',
                                          'bio' => $card['bio'] ?? '',
                                          'type' => $card['type'] ?? 'BOT',
                                          'power_name' => $card['power'] ?? '',
                                          'ability_line' => $card['ability'] ?? '',
                                          'subject' => $attrs['subject'] ?? ($card['nickname'] ?? ''),
                                          'details' => $attrs['details'] ?? '',
                                          'vibe' => $attrs['vibe'] ?? '',
                                      ],
                                      'stats' => [
                                          'hp' => (int)($card['hp'] ?? 0),
                                          'npo' => (int)($card['npo'] ?? 0),
                                          'att' => (int)($card['att'] ?? 0),
                                          'str' => (int)($card['str'] ?? 0),
                                          'los' => (int)($card['los'] ?? 0),
                                          'con' => (int)($card['con'] ?? 0),
                                      ],
                                  ];
                                ?>
                                <div class="collection-card" data-card-id="<?php echo htmlspecialchars($cid); ?>" data-viewer="<?php echo htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="button" class="collection-open-viewer" style="all:unset;cursor:pointer;display:block;width:100%;">
                                        <img src="<?php echo htmlspecialchars($imgSrc); ?>" alt="<?php echo htmlspecialchars($card['nickname'] ?? 'Card'); ?>" class="card-image">
                                    </button>
                                    <div class="card-info">
                                        <h4 class="card-nickname"><?php echo htmlspecialchars($card['nickname'] ?? 'Unnamed Card'); ?></h4>
                                        <?php
                                        $vibeLine = trim((string)($card['vibe'] ?? ''));
                                        if ($vibeLine === '') {
                                            $vibeLine = trim((string)($card['subject'] ?? ''));
                                        }
                                        if ($vibeLine !== ''):
                                        ?>
                                            <p class="card-type"><?php echo htmlspecialchars($vibeLine); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($card['bio'])): ?>
                                            <p class="card-bio"><?php echo htmlspecialchars(substr($card['bio'], 0, 100)) . (strlen($card['bio']) > 100 ? '...' : ''); ?></p>
                                        <?php endif; ?>
                                        <div class="card-actions" style="display:flex;gap:0.4rem;flex-wrap:wrap;margin-top:0.5rem;">
                                            <button type="button" class="btn btn-primary collection-open-viewer">View</button>
                                            <a class="btn btn-primary" href="<?php echo htmlspecialchars($dlUrl); ?>">Download</a>
                                            <button type="button" class="btn btn-secondary collection-delete" data-card-id="<?php echo htmlspecialchars($cid); ?>">Delete</button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <script>
                        (function() {
                            const base = <?php echo json_encode($basePath); ?>;
                            const assetBase = <?php echo json_encode($assetPath); ?>;
                            let viewer = null;
                            function ensureViewer() {
                                if (viewer) return viewer;
                                viewer = new window.CardobotViewer({
                                    assetBase: assetBase,
                                    apiBase: base,
                                    onClose: () => {},
                                    onSave: null,
                                });
                                return viewer;
                            }
                            document.querySelectorAll('.collection-open-viewer').forEach((btn) => {
                                btn.addEventListener('click', async () => {
                                    const card = btn.closest('.collection-card');
                                    if (!card) return;
                                    let payload = {};
                                    try { payload = JSON.parse(card.getAttribute('data-viewer') || '{}'); } catch (e) {}
                                    const framed = base + '/api/download-card.php?card_id=' + encodeURIComponent(payload.sessionId || '');
                                    await ensureViewer().open({
                                        sessionId: payload.sessionId,
                                        concept: payload.concept || {},
                                        stats: payload.stats || {},
                                        artUrl: payload.artUrl || framed,
                                        compositeUrl: framed,
                                        mode: 'viewer',
                                    });
                                });
                            });
                            document.querySelectorAll('.collection-delete').forEach((btn) => {
                                btn.addEventListener('click', async () => {
                                    const id = btn.getAttribute('data-card-id');
                                    if (!id || !confirm('Delete this card from your collection?')) return;
                                    try {
                                        const res = await fetch(base + '/api/delete-card.php', {
                                            method: 'POST',
                                            credentials: 'same-origin',
                                            headers: { 'Content-Type': 'application/json' },
                                            body: JSON.stringify({ card_id: id }),
                                        });
                                        const data = await res.json();
                                        if (data.ok) {
                                            const card = btn.closest('.collection-card');
                                            if (card) card.remove();
                                        } else {
                                            alert(data.message || 'Could not delete card.');
                                        }
                                    } catch (e) {
                                        alert('Could not delete card.');
                                    }
                                });
                            });
                        })();
                        </script>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Profile Tab -->
        <?php if ($activeTab === 'profile'): ?>
            <div class="card">
                <div class="card-header">
                    <h2>👤 Profile Information</h2>
                </div>
                <div class="card-body">
                    <form method="POST" class="profile-form" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="change_username">
                        
                        <div class="form-group">
                            <label for="new_username">Username</label>
                            <input type="text" id="new_username" name="new_username" value="<?php echo htmlspecialchars($user['username'] ?? ''); ?>" required class="form-control">
                            <small class="form-help">Your unique identifier in the Card-o-Bot universe</small>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update Username</button>
                        </div>
                    </form>
                    
                    <?php if (!$hasGoogleLinked): ?>
                        <hr style="margin: var(--spacing-6) 0; border: none; border-top: var(--border-width) solid var(--color-border);">
                        
                        <h3 style="margin-bottom: var(--spacing-4);">Profile Details</h3>
                        <p class="text-muted" style="margin-bottom: var(--spacing-4); color: var(--color-text-secondary);">
                            Manage your profile information. These fields are only editable when Google OAuth is not linked.
                        </p>
                        
                        <form method="POST" class="profile-form" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" class="form-control" maxlength="255">
                                <small class="form-help">Your display name (optional)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" class="form-control" maxlength="255">
                                <small class="form-help">Your email address (optional)</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="profile_picture">Profile Picture</label>
                                <div style="margin-bottom: var(--spacing-2);">
                                    <?php if (!empty($user['picture'])): ?>
                                        <img src="<?php echo htmlspecialchars($user['picture']); ?>" alt="Current profile picture" style="max-width: 150px; max-height: 150px; border-radius: var(--border-radius); border: var(--border-width) solid var(--color-border); margin-bottom: var(--spacing-2);">
                                        <br>
                                    <?php endif; ?>
                                    <input type="file" id="profile_picture" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="form-control">
                                </div>
                                <small class="form-help">Upload a profile picture (JPEG, PNG, GIF, or WebP, max 2MB)</small>
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">Update Profile</button>
                            </div>
                        </form>
                    <?php else: ?>
                        <hr style="margin: var(--spacing-6) 0; border: none; border-top: var(--border-width) solid var(--color-border);">
                        
                        <div class="alert alert-info" style="background: var(--color-primary-light); color: var(--color-primary-dark); padding: var(--spacing-4); border-radius: var(--border-radius);">
                            <strong>🔗 Google Account Linked</strong><br>
                            Your profile information (name, email, and picture) is managed through your Google account. 
                            Unlink Google in the Security tab to edit these fields manually.
                        </div>
                        
                        <div class="form-group" style="margin-top: var(--spacing-4);">
                            <label>Email</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?>" disabled class="form-control" readonly>
                            <small class="form-help">Synced from Google account</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Name</label>
                            <input type="text" value="<?php echo htmlspecialchars($user['name'] ?? 'Not set'); ?>" disabled class="form-control" readonly>
                            <small class="form-help">Synced from Google account</small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Security Tab -->
        <?php if ($activeTab === 'security'): ?>
            <div class="card">
                <div class="card-header">
                    <h2>🔒 Security & Access</h2>
                </div>
                <div class="card-body">
                    <!-- Password Section -->
                    <div class="security-section">
                        <h3>Password Protection</h3>
                        <form method="POST" class="security-form">
                            <input type="hidden" name="action" value="change_password">
                            
                            <?php if ($hasPassword): ?>
                                <div class="form-group">
                                    <label for="current_password">Current Password</label>
                                    <input type="password" id="current_password" name="current_password" required class="form-control">
                                    <small class="form-help">Enter your current password to change it</small>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info" style="margin-bottom: var(--spacing-4);">
                                    <strong>🔐 No Password Set</strong><br>
                                    You don't have a password yet. Set one now to secure your collection!
                                </div>
                            <?php endif; ?>
                            
                            <div class="form-group">
                                <label for="new_password"><?php echo $hasPassword ? 'New Password' : 'Set Password'; ?></label>
                                <input type="password" id="new_password" name="new_password" required class="form-control" minlength="4">
                                <small class="form-help">Minimum 4 characters</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm <?php echo $hasPassword ? 'New ' : ''; ?>Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" required class="form-control" minlength="4">
                            </div>
                            
                            <div class="form-actions">
                                <button type="submit" class="btn btn-primary">
                                    <?php echo $hasPassword ? 'Change Password' : 'Set Password'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Google Account Section -->
                    <div class="security-section">
                        <h3>Google Account Linking</h3>
                        <div class="login-method">
                            <div class="method-status">
                                <strong>Google Sign-In:</strong>
                                <span class="status-badge <?php echo $hasGoogleLinked ? 'status-pass' : 'status-fail'; ?>">
                                    <?php echo $hasGoogleLinked ? '✅ Linked' : '❌ Not Linked'; ?>
                                </span>
                            </div>
                            
                            <?php if ($hasGoogleLinked): ?>
                                <p class="text-muted">Your Google account is linked. You can sign in with Google or your password.</p>
                                <form method="POST" class="inline-form">
                                    <input type="hidden" name="action" value="unlink_google">
                                    <button type="submit" class="btn btn-secondary" onclick="return confirm('Are you sure? You will need a password to log in after unlinking.')">
                                        Unlink Google Account
                                    </button>
                                </form>
                            <?php else: ?>
                                <p class="text-muted">Link your Google account for quick and secure sign-in.</p>
                                <?php if (is_google_oauth_configured()): ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="action" value="link_google">
                                        <button type="submit" class="btn btn-primary">
                                            🔗 Link Google Account
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <p class="text-muted">Google OAuth is not available on this server.</p>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
            </div>
        </div>
<?php
// End console wrapper
console_end();
?>
