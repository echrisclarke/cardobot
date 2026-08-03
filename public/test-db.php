<?php
/**
 * Database Connection Test
 * Tests database connectivity and table existence
 */

require_once __DIR__ . '/includes/env.php';

header('Content-Type: application/json; charset=utf-8');

$results = [
  'timestamp' => date('Y-m-d H:i:s'),
  'overall' => 'unknown',
  'summary' => 'Database test in progress...',
  'tests' => []
];

// Test 1: Database credentials retrieval
try {
  $creds = get_db_credentials();
  $results['tests']['db_credentials'] = [
    'status' => 'pass',
    'message' => 'Database credentials retrieved successfully',
    'host' => $creds['host'],
    'database' => $creds['database'],
    'username' => $creds['username'],
    'charset' => $creds['charset'],
    'password_set' => !empty($creds['password'])
  ];
} catch (Exception $e) {
  $results['tests']['db_credentials'] = [
    'status' => 'fail',
    'message' => $e->getMessage()
  ];
  $results['overall'] = 'fail';
  $results['summary'] = 'Failed to retrieve database credentials';
  echo json_encode($results, JSON_PRETTY_PRINT);
  exit;
}

// Test 2: Database connection
try {
  $pdo = get_db_connection();
  if ($pdo) {
    $results['tests']['db_connection'] = [
      'status' => 'pass',
      'message' => 'Database connection successful'
    ];
  } else {
    $results['tests']['db_connection'] = [
      'status' => 'fail',
      'message' => 'Database connection returned null'
    ];
    $results['overall'] = 'fail';
    $results['summary'] = 'Database connection failed';
    echo json_encode($results, JSON_PRETTY_PRINT);
    exit;
  }
} catch (Exception $e) {
  $results['tests']['db_connection'] = [
    'status' => 'fail',
    'message' => $e->getMessage()
  ];
  $results['overall'] = 'fail';
  $results['summary'] = 'Database connection error: ' . $e->getMessage();
  echo json_encode($results, JSON_PRETTY_PRINT);
  exit;
}

// Test 3: Check if required tables exist
$requiredTables = ['cardobot_users', 'cardobot_cards', 'cardobot_sessions'];
$existingTables = [];
$missingTables = [];

foreach ($requiredTables as $table) {
  try {
    $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
    if ($stmt->rowCount() > 0) {
      $existingTables[] = $table;
      
      // Get row count
      $countStmt = $pdo->query("SELECT COUNT(*) as count FROM {$table}");
      $count = $countStmt->fetch()['count'];
      
      $results['tests']["table_{$table}"] = [
        'status' => 'pass',
        'message' => "Table '{$table}' exists",
        'row_count' => $count
      ];
    } else {
      $missingTables[] = $table;
      $results['tests']["table_{$table}"] = [
        'status' => 'fail',
        'message' => "Table '{$table}' does not exist"
      ];
    }
  } catch (Exception $e) {
    $missingTables[] = $table;
    $results['tests']["table_{$table}"] = [
      'status' => 'fail',
      'message' => "Error checking table '{$table}': " . $e->getMessage()
    ];
  }
}

// Test 4: Test table structure (check for key columns)
if (in_array('cardobot_users', $existingTables)) {
  try {
    $stmt = $pdo->query("DESCRIBE cardobot_users");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $requiredColumns = ['id', 'username', 'password_hash', 'auth_method', 'is_admin'];
    $foundColumns = array_intersect($requiredColumns, $columns);
    
    $results['tests']['users_table_structure'] = [
      'status' => count($foundColumns) === count($requiredColumns) ? 'pass' : 'warning',
      'message' => 'Users table structure check',
      'required_columns' => $requiredColumns,
      'found_columns' => array_values($foundColumns),
      'missing_columns' => array_diff($requiredColumns, $foundColumns)
    ];
  } catch (Exception $e) {
    $results['tests']['users_table_structure'] = [
      'status' => 'fail',
      'message' => 'Error checking table structure: ' . $e->getMessage()
    ];
  }
}

if (in_array('cardobot_cards', $existingTables)) {
  try {
    $stmt = $pdo->query("DESCRIBE cardobot_cards");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $requiredColumns = ['id', 'card_id', 'user_id', 'type', 'nickname', 'bio'];
    $foundColumns = array_intersect($requiredColumns, $columns);
    
    $results['tests']['cards_table_structure'] = [
      'status' => count($foundColumns) === count($requiredColumns) ? 'pass' : 'warning',
      'message' => 'Cards table structure check',
      'required_columns' => $requiredColumns,
      'found_columns' => array_values($foundColumns),
      'missing_columns' => array_diff($requiredColumns, $foundColumns)
    ];
  } catch (Exception $e) {
    $results['tests']['cards_table_structure'] = [
      'status' => 'fail',
      'message' => 'Error checking table structure: ' . $e->getMessage()
    ];
  }
}

// Test 5: Test foreign key relationships
if (in_array('cardobot_cards', $existingTables) && in_array('cardobot_users', $existingTables)) {
  try {
    $stmt = $pdo->query("
      SELECT 
        CONSTRAINT_NAME, 
        TABLE_NAME, 
        REFERENCED_TABLE_NAME 
      FROM 
        INFORMATION_SCHEMA.KEY_COLUMN_USAGE 
      WHERE 
        TABLE_SCHEMA = DATABASE() 
        AND TABLE_NAME = 'cardobot_cards' 
        AND REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $hasUserForeignKey = false;
    foreach ($foreignKeys as $fk) {
      if ($fk['REFERENCED_TABLE_NAME'] === 'cardobot_users') {
        $hasUserForeignKey = true;
        break;
      }
    }
    
    $results['tests']['foreign_keys'] = [
      'status' => $hasUserForeignKey ? 'pass' : 'warning',
      'message' => 'Foreign key relationships check',
      'foreign_keys_found' => $foreignKeys,
      'has_user_fk' => $hasUserForeignKey
    ];
  } catch (Exception $e) {
    $results['tests']['foreign_keys'] = [
      'status' => 'warning',
      'message' => 'Could not check foreign keys: ' . $e->getMessage()
    ];
  }
}

// Determine overall status
if (!empty($missingTables)) {
  $results['overall'] = 'fail';
  $results['summary'] = 'Missing required tables: ' . implode(', ', $missingTables);
} else {
  $allPassed = true;
  foreach ($results['tests'] as $test) {
    if ($test['status'] === 'fail') {
      $allPassed = false;
      break;
    }
  }
  
  if ($allPassed) {
    $results['overall'] = 'pass';
    $results['summary'] = 'All database tests passed! Database is ready to use.';
  } else {
    $results['overall'] = 'warning';
    $results['summary'] = 'Database connection works, but some tests had warnings.';
  }
}

echo json_encode($results, JSON_PRETTY_PRINT);
