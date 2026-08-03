<?php
/**
 * Database Browser
 * Admin-only page to view and edit database tables
 */

require_once __DIR__ . '/../includes/auth.php';

// Require admin access
if (!is_admin()) {
    header('Location: ' . get_base_path() . '/index.php');
    exit;
}

$assetPath = get_asset_path();
$basePath = get_base_path();
$error = '';
$success = '';
$action = $_GET['action'] ?? 'tables';
$tableName = $_GET['table'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;
$queryResults = [];
$queryRowCount = 0;

// Get database connection
$pdo = get_auth_db();
if (!$pdo) {
    $error = 'Database connection failed. Check your .env file.';
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $pdo) {
    $postAction = $_POST['action'] ?? '';
    
    if ($postAction === 'execute_query') {
        $query = trim($_POST['query'] ?? '');
        if (!empty($query)) {
            try {
                // Only allow SELECT, UPDATE, INSERT, DELETE for safety
                $queryUpper = strtoupper(trim($query));
                $allowedPrefixes = ['SELECT', 'UPDATE', 'INSERT', 'DELETE', 'SHOW', 'DESCRIBE', 'EXPLAIN'];
                $isAllowed = false;
                foreach ($allowedPrefixes as $prefix) {
                    if (strpos($queryUpper, $prefix) === 0) {
                        $isAllowed = true;
                        break;
                    }
                }
                
                if (!$isAllowed) {
                    $error = 'Only SELECT, UPDATE, INSERT, DELETE, SHOW, DESCRIBE, and EXPLAIN queries are allowed.';
                } else {
                    $stmt = $pdo->prepare($query);
                    $stmt->execute();
                    
                    if (strpos($queryUpper, 'SELECT') === 0 || strpos($queryUpper, 'SHOW') === 0 || strpos($queryUpper, 'DESCRIBE') === 0 || strpos($queryUpper, 'EXPLAIN') === 0) {
                        $queryResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $queryRowCount = count($queryResults);
                        $success = 'Query executed successfully. ' . $queryRowCount . ' row(s) returned.';
                    } else {
                        $affected = $stmt->rowCount();
                        $success = 'Query executed successfully. ' . $affected . ' row(s) affected.';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Query error: ' . $e->getMessage();
            }
        }
    } elseif ($postAction === 'update_row') {
        $table = $_POST['table'] ?? '';
        $id = $_POST['id'] ?? '';
        $idColumn = $_POST['id_column'] ?? 'id';
        
        if (empty($table) || empty($id)) {
            $error = 'Missing table or ID';
        } else {
            try {
                // Get current row
                $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `{$idColumn}` = ? LIMIT 1");
                $stmt->execute([$id]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$row) {
                    $error = 'Row not found';
                } else {
                    // Build update query from POST data
                    $updates = [];
                    $params = [];
                    
                    foreach ($_POST as $key => $value) {
                        if ($key !== 'action' && $key !== 'table' && $key !== 'id' && $key !== 'id_column') {
                            $updates[] = "`{$key}` = ?";
                            $params[] = $value;
                        }
                    }
                    
                    if (!empty($updates)) {
                        $params[] = $id;
                        $sql = "UPDATE `{$table}` SET " . implode(', ', $updates) . " WHERE `{$idColumn}` = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $success = 'Row updated successfully';
                        $action = 'view';
                    }
                }
            } catch (PDOException $e) {
                $error = 'Update error: ' . $e->getMessage();
            }
        }
    } elseif ($postAction === 'delete_row') {
        $table = $_POST['table'] ?? '';
        $id = $_POST['id'] ?? '';
        $idColumn = $_POST['id_column'] ?? 'id';
        $confirm = $_POST['confirm'] ?? '';
        
        if ($confirm !== 'DELETE') {
            $error = 'Please type DELETE to confirm';
        } elseif (empty($table) || empty($id)) {
            $error = 'Missing table or ID';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM `{$table}` WHERE `{$idColumn}` = ?");
                $stmt->execute([$id]);
                $success = 'Row deleted successfully';
                $action = 'view';
            } catch (PDOException $e) {
                $error = 'Delete error: ' . $e->getMessage();
            }
        }
    }
}

// Get all tables
$tables = [];
if ($pdo && $action === 'tables') {
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $error = 'Error loading tables: ' . $e->getMessage();
    }
}

// Get table data
$tableData = [];
$tableColumns = [];
$tableInfo = [];
$totalRows = 0;

if ($pdo && $action === 'view' && !empty($tableName)) {
    try {
        // Get column info
        $stmt = $pdo->query("DESCRIBE `{$tableName}`");
        $tableColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get total count
        $countStmt = $pdo->query("SELECT COUNT(*) as total FROM `{$tableName}`");
        $totalRows = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get table data with pagination (LIMIT doesn't work with placeholders in some MySQL versions)
        $stmt = $pdo->prepare("SELECT * FROM `{$tableName}` LIMIT " . (int)$perPage . " OFFSET " . (int)$offset);
        $stmt->execute();
        $tableData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get table info
        $infoStmt = $pdo->query("SHOW TABLE STATUS LIKE '{$tableName}'");
        $tableInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = 'Error loading table data: ' . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Browser - Admin - Card-o-Bot</title>
    <link rel="stylesheet" href="<?php echo $assetPath; ?>/assets/css/base.css">
</head>
<body class="admin-page">
    <header class="header">
        <div class="header-content">
            <h1>🗄️ Database Browser</h1>
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

        <!-- Navigation Tabs -->
        <div class="admin-tabs">
            <a href="?action=tables" class="tab <?php echo $action === 'tables' ? 'active' : ''; ?>">
                📋 Tables
            </a>
            <?php if ($action === 'view' && !empty($tableName)): ?>
                <a href="?action=view&table=<?php echo urlencode($tableName); ?>" class="tab active">
                    📊 <?php echo htmlspecialchars($tableName); ?>
                </a>
            <?php endif; ?>
            <a href="?action=query" class="tab <?php echo $action === 'query' ? 'active' : ''; ?>">
                🔍 SQL Query
            </a>
        </div>

        <!-- Tables List -->
        <?php if ($action === 'tables'): ?>
            <div class="admin-section">
                <div class="admin-section-header">
                    <h2>Database Tables (<?php echo count($tables); ?>)</h2>
                </div>
                <div class="admin-section-body">
                    <?php if (empty($tables)): ?>
                        <p>No tables found in database.</p>
                    <?php else: ?>
                        <div class="table-list">
                            <?php foreach ($tables as $table): ?>
                                <div class="table-item">
                                    <a href="?action=view&table=<?php echo urlencode($table); ?>" class="table-link">
                                        <strong><?php echo htmlspecialchars($table); ?></strong>
                                    </a>
                                    <a href="?action=view&table=<?php echo urlencode($table); ?>" class="btn btn-sm btn-primary">View</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Table View -->
        <?php if ($action === 'view' && !empty($tableName)): ?>
            <div class="admin-section">
                <div class="admin-section-header">
                    <h2>Table: <?php echo htmlspecialchars($tableName); ?></h2>
                    <?php if ($tableInfo): ?>
                        <p class="table-info">
                            Rows: <?php echo number_format($totalRows); ?> | 
                            Engine: <?php echo htmlspecialchars($tableInfo['Engine'] ?? 'N/A'); ?> | 
                            Collation: <?php echo htmlspecialchars($tableInfo['Collation'] ?? 'N/A'); ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="admin-section-body">
                    <?php if (empty($tableData)): ?>
                        <p>No data found in this table.</p>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <?php foreach ($tableColumns as $col): ?>
                                            <th><?php echo htmlspecialchars($col['Field']); ?></th>
                                        <?php endforeach; ?>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tableData as $row): ?>
                                        <tr>
                                            <?php foreach ($tableColumns as $col): ?>
                                                <td>
                                                    <?php 
                                                    $value = $row[$col['Field']] ?? null;
                                                    if ($value === null) {
                                                        echo '<em>NULL</em>';
                                                    } elseif (strlen($value) > 100) {
                                                        echo htmlspecialchars(substr($value, 0, 100)) . '...';
                                                    } else {
                                                        echo htmlspecialchars($value);
                                                    }
                                                    ?>
                                                </td>
                                            <?php endforeach; ?>
                                            <td class="actions">
                                                <button onclick="editRow('<?php echo htmlspecialchars($tableName); ?>', <?php echo htmlspecialchars(json_encode($row)); ?>)" class="btn btn-sm btn-secondary">Edit</button>
                                                <button onclick="deleteRow('<?php echo htmlspecialchars($tableName); ?>', '<?php echo htmlspecialchars($row[$tableColumns[0]['Field']] ?? ''); ?>', '<?php echo htmlspecialchars($tableColumns[0]['Field']); ?>')" class="btn btn-sm btn-danger">Delete</button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalRows > $perPage): ?>
                            <div class="pagination">
                                <?php
                                $totalPages = ceil($totalRows / $perPage);
                                if ($page > 1):
                                ?>
                                    <a href="?action=view&table=<?php echo urlencode($tableName); ?>&page=<?php echo $page - 1; ?>" class="btn btn-secondary">Previous</a>
                                <?php endif; ?>
                                
                                <span>Page <?php echo $page; ?> of <?php echo $totalPages; ?></span>
                                
                                <?php if ($page < $totalPages): ?>
                                    <a href="?action=view&table=<?php echo urlencode($tableName); ?>&page=<?php echo $page + 1; ?>" class="btn btn-secondary">Next</a>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- SQL Query Interface -->
        <?php if ($action === 'query'): ?>
            <div class="admin-section">
                <div class="admin-section-header">
                    <h2>🔍 SQL Query Interface</h2>
                </div>
                <div class="admin-section-body">
                    <p class="alert alert-warning">
                        <strong>⚠️ Warning:</strong> Only SELECT, UPDATE, INSERT, DELETE, SHOW, DESCRIBE, and EXPLAIN queries are allowed. 
                        DROP, ALTER, CREATE, and other DDL statements are disabled for safety.
                    </p>
                    
                    <div class="admin-section admin-section-secondary" style="margin-bottom: var(--spacing-4);">
                        <div class="admin-section-header">
                            <h3>💡 Common SQL Examples</h3>
                        </div>
                        <div class="admin-section-body">
                            <details>
                                <summary><strong>View All Users</strong></summary>
                                <pre class="code-example">SELECT id, username, email, name, is_admin, created_at, last_login 
FROM cardobot_users 
ORDER BY created_at DESC;</pre>
                            </details>
                            
                            <details>
                                <summary><strong>Add a User</strong></summary>
                                <pre class="code-example">INSERT INTO cardobot_users (username, password_hash, email, name, auth_method, created_at)
VALUES ('newuser', '$2y$10$...', 'user@example.com', 'User Name', 'password', NOW());</pre>
                                <p><small>⚠️ Note: Use PHP's <code>password_hash('password', PASSWORD_DEFAULT)</code> to generate password_hash. Better to use User Management page.</small></p>
                            </details>
                            
                            <details>
                                <summary><strong>Update a User</strong></summary>
                                <pre class="code-example">UPDATE cardobot_users 
SET email = 'newemail@example.com', name = 'New Name' 
WHERE id = 1;</pre>
                            </details>
                            
                            <details>
                                <summary><strong>Make User Admin</strong></summary>
                                <pre class="code-example">UPDATE cardobot_users 
SET is_admin = 1 
WHERE id = 1;</pre>
                            </details>
                            
                            <details>
                                <summary><strong>Remove Admin Status</strong></summary>
                                <pre class="code-example">UPDATE cardobot_users 
SET is_admin = 0 
WHERE id = 1;</pre>
                            </details>
                            
                            <details>
                                <summary><strong>Change User Password</strong></summary>
                                <pre class="code-example">UPDATE cardobot_users 
SET password_hash = '$2y$10$...' 
WHERE id = 1;</pre>
                                <p><small>⚠️ Note: Generate hash using PHP: <code>password_hash('newpassword', PASSWORD_DEFAULT)</code>. Better to use User Management page.</small></p>
                            </details>
                            
                            <details>
                                <summary><strong>Delete a User</strong></summary>
                                <pre class="code-example">DELETE FROM cardobot_users WHERE id = 1;</pre>
                                <p><small>⚠️ Warning: This permanently deletes the user and all their data! Use User Management page for safer deletion.</small></p>
                            </details>
                            
                            <details>
                                <summary><strong>View User Cards</strong></summary>
                                <pre class="code-example">SELECT c.*, u.username 
FROM cardobot_cards c 
JOIN cardobot_users u ON c.user_id = u.id 
WHERE u.id = 1;</pre>
                            </details>
                        </div>
                    </div>
                    
                    <form method="POST" class="admin-form">
                        <input type="hidden" name="action" value="execute_query">
                        <div class="form-group">
                            <label for="query">SQL Query</label>
                            <textarea id="query" name="query" rows="10" class="form-control code-textarea" required placeholder="SELECT * FROM cardobot_users LIMIT 10;"><?php echo htmlspecialchars($_POST['query'] ?? ''); ?></textarea>
                        </div>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Execute Query</button>
                        </div>
                    </form>
                    
                    <?php if (!empty($queryResults)): ?>
                        <div class="admin-section" style="margin-top: var(--spacing-4);">
                            <div class="admin-section-header">
                                <h3>Query Results (<?php echo $queryRowCount; ?> row(s))</h3>
                            </div>
                            <div class="admin-section-body">
                                <div class="table-responsive">
                                    <table class="admin-table">
                                        <thead>
                                            <tr>
                                                <?php if (!empty($queryResults)): ?>
                                                    <?php foreach (array_keys($queryResults[0]) as $column): ?>
                                                        <th><?php echo htmlspecialchars($column); ?></th>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($queryResults as $row): ?>
                                                <tr>
                                                    <?php foreach ($row as $value): ?>
                                                        <td>
                                                            <?php 
                                                            if ($value === null) {
                                                                echo '<em>NULL</em>';
                                                            } elseif (strlen($value) > 100) {
                                                                echo htmlspecialchars(substr($value, 0, 100)) . '...';
                                                            } else {
                                                                echo htmlspecialchars($value);
                                                            }
                                                            ?>
                                                        </td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <!-- Edit Row Modal -->
    <div id="edit-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Edit Row</h3>
            <form method="POST" id="edit-form">
                <input type="hidden" name="action" value="update_row">
                <input type="hidden" name="table" id="edit-table">
                <input type="hidden" name="id" id="edit-id">
                <input type="hidden" name="id_column" id="edit-id-column">
                <div id="edit-fields"></div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update</button>
                    <button type="button" onclick="closeEditModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="delete-modal" class="modal" style="display: none;">
        <div class="modal-content">
            <h3>Confirm Delete</h3>
            <p>Are you sure you want to delete this row?</p>
            <p class="alert alert-error">This action cannot be undone!</p>
            <form method="POST" id="delete-form">
                <input type="hidden" name="action" value="delete_row">
                <input type="hidden" name="table" id="delete-table">
                <input type="hidden" name="id" id="delete-id">
                <input type="hidden" name="id_column" id="delete-id-column">
                <div class="form-group">
                    <label for="confirm-delete">Type <strong>DELETE</strong> to confirm:</label>
                    <input type="text" id="confirm-delete" name="confirm" class="form-control" required>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-danger">Delete Row</button>
                    <button type="button" onclick="closeDeleteModal()" class="btn btn-secondary">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function editRow(tableName, row) {
            document.getElementById('edit-table').value = tableName;
            document.getElementById('edit-id').value = Object.values(row)[0];
            document.getElementById('edit-id-column').value = Object.keys(row)[0];
            
            const fieldsDiv = document.getElementById('edit-fields');
            fieldsDiv.innerHTML = '';
            
            for (const [key, value] of Object.entries(row)) {
                const div = document.createElement('div');
                div.className = 'form-group';
                
                const label = document.createElement('label');
                label.textContent = key;
                label.setAttribute('for', 'edit-' + key);
                
                const input = document.createElement('input');
                input.type = 'text';
                input.id = 'edit-' + key;
                input.name = key;
                input.value = value === null ? '' : value;
                input.className = 'form-control';
                
                div.appendChild(label);
                div.appendChild(input);
                fieldsDiv.appendChild(div);
            }
            
            document.getElementById('edit-modal').style.display = 'flex';
        }
        
        function closeEditModal() {
            document.getElementById('edit-modal').style.display = 'none';
            document.getElementById('edit-form').reset();
        }
        
        function deleteRow(tableName, id, idColumn) {
            document.getElementById('delete-table').value = tableName;
            document.getElementById('delete-id').value = id;
            document.getElementById('delete-id-column').value = idColumn;
            document.getElementById('delete-modal').style.display = 'flex';
        }
        
        function closeDeleteModal() {
            document.getElementById('delete-modal').style.display = 'none';
            document.getElementById('delete-form').reset();
        }
        
        // Close modals on outside click
        window.onclick = function(event) {
            const editModal = document.getElementById('edit-modal');
            const deleteModal = document.getElementById('delete-modal');
            if (event.target === editModal) {
                closeEditModal();
            }
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
    </script>
</body>
</html>
