<?php
/**
 * User Management Page
 * Admin-only page to manage users
 */

require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/google-auth.php';

// Require admin access
if (!is_admin()) {
    header('Location: ' . get_base_path() . '/index.php');
    exit;
}

$assetPath = get_asset_path();
$basePath = get_base_path();
$error = '';
$success = '';
$action = $_GET['action'] ?? 'list';
$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'create_user') {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $email = trim($_POST['email'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $isAdmin = isset($_POST['is_admin']) && $_POST['is_admin'] === '1';
        
        $result = create_user($username, $password);
        if ($result['success']) {
            // Update additional fields if provided
            $newUser = get_user_by_username($username);
            if ($newUser && ($email || $name || $isAdmin)) {
                $pdo = get_auth_db();
                if ($pdo) {
                    $updateFields = [];
                    $updateParams = [];
                    
                    if (!empty($email)) {
                        $updateFields[] = 'email = ?';
                        $updateParams[] = $email;
                    }
                    if (!empty($name)) {
                        $updateFields[] = 'name = ?';
                        $updateParams[] = $name;
                    }
                    if ($isAdmin) {
                        $updateFields[] = 'is_admin = ?';
                        $updateParams[] = 1;
                    }
                    
                    if (!empty($updateFields)) {
                        $updateParams[] = $newUser['id'];
                        $stmt = $pdo->prepare("UPDATE cardobot_users SET " . implode(', ', $updateFields) . " WHERE id = ?");
                        $stmt->execute($updateParams);
                    }
                }
            }
            $success = 'User created successfully';
            $action = 'list';
        } else {
            $error = $result['message'];
        }
    } elseif ($postAction === 'update_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $name = trim($_POST['name'] ?? '');
        $isAdmin = isset($_POST['is_admin']) && $_POST['is_admin'] === '1';
        $newPassword = $_POST['new_password'] ?? '';
        
        $pdo = get_auth_db();
        if (!$pdo) {
            $error = 'Database connection failed';
        } else {
            try {
                $updateFields = [];
                $updateParams = [];
                
                // Update username if changed
                $currentUser = get_user_by_id($userId);
                if ($currentUser && $currentUser['username'] !== $username) {
                    $usernameResult = change_username($userId, $username);
                    if (!$usernameResult['success']) {
                        $error = $usernameResult['message'];
                    }
                }
                
                if (!empty($email)) {
                    $updateFields[] = 'email = ?';
                    $updateParams[] = $email;
                } else {
                    $updateFields[] = 'email = NULL';
                }
                
                if (!empty($name)) {
                    $updateFields[] = 'name = ?';
                    $updateParams[] = $name;
                } else {
                    $updateFields[] = 'name = NULL';
                }
                
                $updateFields[] = 'is_admin = ?';
                $updateParams[] = $isAdmin ? 1 : 0;
                
                // Update password if provided
                if (!empty($newPassword)) {
                    if (is_valid_password($newPassword)) {
                        $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
                        $updateFields[] = 'password_hash = ?';
                        $updateParams[] = $newHash;
                    } else {
                        $error = 'Password must be at least 4 characters long';
                    }
                }
                
                if (empty($error) && !empty($updateFields)) {
                    $updateParams[] = $userId;
                    $stmt = $pdo->prepare("UPDATE cardobot_users SET " . implode(', ', $updateFields) . " WHERE id = ?");
                    $stmt->execute($updateParams);
                    $success = 'User updated successfully';
                    $action = 'list';
                }
            } catch (PDOException $e) {
                $error = 'Error updating user: ' . $e->getMessage();
            }
        }
    } elseif ($postAction === 'delete_user') {
        $userId = (int)($_POST['user_id'] ?? 0);
        $confirm = $_POST['confirm'] ?? '';
        
        if ($confirm !== 'DELETE') {
            $error = 'Please type DELETE to confirm';
        } else {
            $pdo = get_auth_db();
            if (!$pdo) {
                $error = 'Database connection failed';
            } else {
                try {
                    // Don't allow deleting yourself
                    $currentUser = get_logged_in_user();
                    if ($currentUser['id'] == $userId) {
                        $error = 'You cannot delete your own account';
                    } else {
                        $stmt = $pdo->prepare("DELETE FROM cardobot_users WHERE id = ?");
                        $stmt->execute([$userId]);
                        $success = 'User deleted successfully';
                        $action = 'list';
                    }
                } catch (PDOException $e) {
                    $error = 'Error deleting user: ' . $e->getMessage();
                }
            }
        }
    }
}

// Get all users for list view
$allUsers = [];
if ($action === 'list' || $action === 'edit') {
    $pdo = get_auth_db();
    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT * FROM cardobot_users ORDER BY created_at DESC");
            $allUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $error = 'Error loading users: ' . $e->getMessage();
        }
    }
}

// Get user for edit view
$editUser = null;
if ($action === 'edit' && $userId > 0) {
    $editUser = get_user_by_id($userId);
    if (!$editUser) {
        $error = 'User not found';
        $action = 'list';
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admin - Card-o-Bot</title>
    <link rel="stylesheet" href="<?php echo $assetPath; ?>/assets/css/base.css">
</head>
<body class="admin-page">
    <header class="header">
        <div class="header-content">
            <h1>👥 User Management</h1>
            <div class="user-info">
                <span class="username"><?php echo htmlspecialchars(get_username() ?? 'Admin'); ?></span>
                <a href="<?php echo $basePath; ?>/admin/dashboard.php" class="btn btn-secondary">Dashboard</a>
                <a href="<?php echo $basePath; ?>/index.php" class="btn btn-secondary">App</a>
                <a href="<?php echo $basePath; ?>/logout.php" class="btn btn-primary">Logout</a>
            </div>
        </div>
    </header>
    
    <main class="container">
        <?php if ($error): ?>
            <div class="alert alert-error">
                <strong>⚠️ Error:</strong> <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success">
                <strong>✅ Success:</strong> <?php echo htmlspecialchars($success); ?>
            </div>
        <?php endif; ?>

        <!-- Action Tabs -->
        <div class="admin-tabs">
            <a href="?action=list" class="tab <?php echo $action === 'list' ? 'active' : ''; ?>">
                📋 User List
            </a>
            <a href="?action=create" class="tab <?php echo $action === 'create' ? 'active' : ''; ?>">
                ➕ Create User
            </a>
            <?php if ($action === 'edit' && $editUser): ?>
                <a href="?action=edit&id=<?php echo $userId; ?>" class="tab active">
                    ✏️ Edit User
                </a>
            <?php endif; ?>
        </div>

        <!-- User List View -->
        <?php if ($action === 'list'): ?>
            <div class="admin-section">
                <div class="admin-section-header">
                    <h2>All Users (<?php echo count($allUsers); ?>)</h2>
                </div>
                <div class="admin-section-body">
                    <?php if (empty($allUsers)): ?>
                        <p>No users found in database.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Username</th>
                                        <th>Email</th>
                                        <th>Name</th>
                                        <th>Auth Method</th>
                                        <th>Has Password</th>
                                        <th>Google Linked</th>
                                        <th>Admin</th>
                                        <th>Created</th>
                                        <th>Last Login</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($allUsers as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($user['id'] ?? 'N/A'); ?></td>
                                            <td><strong><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></strong></td>
                                            <td><?php echo htmlspecialchars($user['email'] ?? 'Not set'); ?></td>
                                            <td><?php echo htmlspecialchars($user['name'] ?? 'Not set'); ?></td>
                                            <td><span class="admin-badge-<?php echo $user['auth_method'] === 'google' ? 'info' : 'secondary'; ?>"><?php echo htmlspecialchars($user['auth_method'] ?? 'password'); ?></span></td>
                                            <td><?php echo !empty($user['password_hash']) ? '✅' : '❌'; ?></td>
                                            <td><?php echo !empty($user['google_id']) ? '✅' : '❌'; ?></td>
                                            <td><?php echo !empty($user['is_admin']) ? '✅' : '❌'; ?></td>
                                            <td><?php echo htmlspecialchars($user['created_at'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($user['last_login'] ?? 'Never'); ?></td>
                                            <td class="actions">
                                                <a href="?action=edit&id=<?php echo $user['id']; ?>" class="btn btn-sm btn-secondary">Edit</a>
                                                <?php if ($user['id'] != get_logged_in_user()['id']): ?>
                                                    <button onclick="confirmDelete(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" class="btn btn-sm btn-danger">Delete</button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Create User Form -->
        <?php if ($action === 'create'): ?>
            <div class="admin-section">
                <div class="admin-section-header">
                    <h2>➕ Create New User</h2>
                </div>
                <div class="admin-section-body">
                    <form method="POST" class="admin-form">
                        <input type="hidden" name="action" value="create_user">
                        
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" required class="form-control" minlength="3" maxlength="50">
                            <small class="form-help">3-50 characters, letters, numbers, underscores, and hyphens only</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="password">Password *</label>
                            <input type="password" id="password" name="password" required class="form-control" minlength="4">
                            <small class="form-help">Minimum 4 characters</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_admin" value="1">
                                Make this user an admin
                            </label>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Create User</button>
                            <a href="?action=list" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>

        <!-- Edit User Form -->
        <?php if ($action === 'edit' && $editUser): ?>
            <div class="admin-section">
                <div class="admin-section-header">
                    <h2>✏️ Edit User: <?php echo htmlspecialchars($editUser['username']); ?></h2>
                </div>
                <div class="admin-section-body">
                    <form method="POST" class="admin-form">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                        
                        <div class="form-group">
                            <label for="username">Username *</label>
                            <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($editUser['username'] ?? ''); ?>" required class="form-control" minlength="3" maxlength="50">
                        </div>
                        
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($editUser['email'] ?? ''); ?>" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="name">Full Name</label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($editUser['name'] ?? ''); ?>" class="form-control">
                        </div>
                        
                        <div class="form-group">
                            <label for="new_password">New Password (leave blank to keep current)</label>
                            <input type="password" id="new_password" name="new_password" class="form-control" minlength="4">
                            <small class="form-help">Only fill this if you want to change the password</small>
                        </div>
                        
                        <div class="form-group">
                            <label>
                                <input type="checkbox" name="is_admin" value="1" <?php echo !empty($editUser['is_admin']) ? 'checked' : ''; ?>>
                                Admin User
                            </label>
                        </div>
                        
                        <div class="form-group">
                            <p><strong>Account Info:</strong></p>
                            <ul>
                                <li>Auth Method: <?php echo htmlspecialchars($editUser['auth_method'] ?? 'password'); ?></li>
                                <li>Has Password: <?php echo !empty($editUser['password_hash']) ? 'Yes' : 'No'; ?></li>
                                <li>Google Linked: <?php echo !empty($editUser['google_id']) ? 'Yes' : 'No'; ?></li>
                                <li>Created: <?php echo htmlspecialchars($editUser['created_at'] ?? 'N/A'); ?></li>
                                <li>Last Login: <?php echo htmlspecialchars($editUser['last_login'] ?? 'Never'); ?></li>
                            </ul>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Update User</button>
                            <a href="?action=list" class="btn btn-secondary">Cancel</a>
                        </div>
                    </form>
                    
                    <!-- Delete User Section -->
                    <div class="danger-zone admin-section" style="margin-top: var(--spacing-6); border-color: var(--color-primary);">
                        <div class="admin-section-header">
                            <h3>🗑️ Delete User</h3>
                        </div>
                        <div class="admin-section-body">
                            <p><strong>Warning:</strong> This action cannot be undone. All user data and cards will be permanently deleted.</p>
                            <?php if ($editUser['id'] == get_logged_in_user()['id']): ?>
                                <p class="alert alert-error">You cannot delete your own account.</p>
                            <?php else: ?>
                                <form method="POST" id="delete-form" onsubmit="return confirmDeleteForm()">
                                    <input type="hidden" name="action" value="delete_user">
                                    <input type="hidden" name="user_id" value="<?php echo $editUser['id']; ?>">
                                    <div class="form-group">
                                        <label for="confirm">Type <strong>DELETE</strong> to confirm:</label>
                                        <input type="text" id="confirm" name="confirm" class="form-control" required>
                                    </div>
                                    <button type="submit" class="btn btn-danger">Delete User</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Confirm Delete</h3>
            <p>Are you sure you want to delete user <strong id="delete-username"></strong>?</p>
            <p class="alert alert-error">This action cannot be undone!</p>
            <form method="POST" id="delete-confirm-form">
                <input type="hidden" name="action" value="delete_user">
                <input type="hidden" name="user_id" id="delete-user-id">
                <div class="form-group">
                    <label for="confirm-delete">Type <strong>DELETE</strong> to confirm:</label>
                    <input type="text" id="confirm-delete" name="confirm" class="form-control" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">Delete User</button>
                    <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function confirmDelete(userId, username) {
            document.getElementById('delete-user-id').value = userId;
            document.getElementById('delete-username').textContent = username;
            document.getElementById('delete-modal').style.display = 'flex';
        }
        
        function closeDeleteModal() {
            document.getElementById('delete-modal').style.display = 'none';
            document.getElementById('delete-confirm-form').reset();
        }
        
        function confirmDeleteForm() {
            const confirm = document.getElementById('confirm').value;
            if (confirm !== 'DELETE') {
                alert('Please type DELETE to confirm');
                return false;
            }
            return confirm('Are you absolutely sure? This will permanently delete the user and all their data!');
        }
        
        // Close modal on outside click
        window.onclick = function(event) {
            const modal = document.getElementById('delete-modal');
            if (event.target === modal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>
