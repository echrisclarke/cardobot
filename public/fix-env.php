<?php
/**
 * .env File Diagnostic and Fix Script
 * Run this to check and fix common .env file issues
 * 
 * Access: https://yourdomain.com/cardobot/fix-env.php
 * 
 * WARNING: This script can modify your .env file. Use with caution.
 */

require_once __DIR__ . '/includes/auth.php';

// Require admin access
if (!is_admin()) {
    header('Location: ' . get_base_path() . '/index.php');
    exit;
}

header('Content-Type: text/plain; charset=utf-8');

$envPath = dirname($_SERVER['DOCUMENT_ROOT']) . '/private/.env';

echo "=== .env File Diagnostic and Fix Tool ===\n\n";
echo "File path: {$envPath}\n\n";

if (!file_exists($envPath)) {
    echo "❌ ERROR: .env file not found at: {$envPath}\n";
    exit;
}

echo "✅ .env file found\n\n";

// Read the file
$content = file_get_contents($envPath);
$lines = explode("\n", $content);

echo "File stats:\n";
echo "- Total lines: " . count($lines) . "\n";
echo "- File size: " . strlen($content) . " bytes\n\n";

// Check for parse errors
echo "=== Checking for parse errors ===\n";
$env = @parse_ini_file($envPath, false, INI_SCANNER_RAW);
$lastError = error_get_last();

if (!is_array($env)) {
    echo "❌ Parse error detected!\n";
    if ($lastError) {
        echo "Error: " . $lastError['message'] . "\n";
        if (preg_match('/line (\d+)/', $lastError['message'], $matches)) {
            $errorLine = intval($matches[1]);
            echo "\nProblematic line {$errorLine}:\n";
            if (isset($lines[$errorLine - 1])) {
                echo "  " . trim($lines[$errorLine - 1]) . "\n";
            }
        }
    }
    echo "\n";
} else {
    echo "✅ File parses correctly!\n";
    echo "Found " . count($env) . " environment variables\n\n";
}

// Check for common issues
echo "=== Checking for common issues ===\n";
$issues = [];
$fixedContent = [];

foreach ($lines as $lineNum => $line) {
    $lineNum = $lineNum + 1; // 1-based
    $originalLine = $line;
    $trimmed = trim($line);
    $fixedLine = $line;
    
    // Skip empty lines
    if (empty($trimmed)) {
        $fixedContent[] = $fixedLine;
        continue;
    }
    
    // Check for comments with parentheses
    if (strpos($trimmed, '#') === 0 && preg_match('/\([^)]+\)/', $trimmed)) {
        $issues[] = "Line {$lineNum}: Comment contains parentheses - may cause parse errors";
        // Fix: Remove parentheses from comments
        $fixedLine = preg_replace('/\(([^)]+)\)/', '- $1', $fixedLine);
    }
    
    // Check for unquoted values with special characters
    if (strpos($trimmed, '=') !== false && strpos($trimmed, '#') !== 0) {
        list($key, $value) = explode('=', $trimmed, 2);
        $key = trim($key);
        $value = trim($value);
        
        // Check if value has special characters and isn't quoted
        if (!empty($value) && 
            (strpos($value, '#') !== false || 
             strpos($value, '(') !== false || 
             strpos($value, ')') !== false ||
             strpos($value, ' ') !== false) &&
            !preg_match('/^["\'].*["\']$/', $value)) {
            $issues[] = "Line {$lineNum}: Value contains special characters but isn't quoted: {$key}";
            // Fix: Quote the value (preserve original line structure)
            $valuePart = trim($value, '"\'');
            $fixedLine = $key . '="' . $valuePart . '"';
            // Preserve any trailing whitespace/newline from original
            if (preg_match('/\s*$/', $originalLine, $matches)) {
                $fixedLine .= $matches[0];
            } else {
                $fixedLine .= "\n";
            }
        }
    }
    
    $fixedContent[] = $fixedLine;
}

if (empty($issues)) {
    echo "✅ No common issues found\n\n";
} else {
    echo "⚠️  Found " . count($issues) . " potential issue(s):\n";
    foreach ($issues as $issue) {
        echo "  - {$issue}\n";
    }
    echo "\n";
}

// Show current content around line 20
echo "=== Content around line 20 (where error occurs) ===\n";
for ($i = max(0, 18); $i < min(count($lines), 23); $i++) {
    $lineNum = $i + 1;
    $marker = ($lineNum == 20) ? " >>> " : "     ";
    echo $marker . ($lineNum) . ": " . rtrim($lines[$i]) . "\n";
}
echo "\n";

// Offer to fix
if (!empty($issues) || !is_array($env)) {
    echo "=== Fix Available ===\n";
    echo "The script can attempt to fix the issues automatically.\n";
    echo "A backup will be created first.\n\n";
    
    if (isset($_GET['fix']) && $_GET['fix'] === 'yes') {
        // Create backup
        $backupPath = $envPath . '.backup.' . date('Y-m-d_H-i-s');
        if (copy($envPath, $backupPath)) {
            echo "✅ Backup created: {$backupPath}\n";
            
            // Write fixed content
            $fixed = implode("\n", $fixedContent);
            if (file_put_contents($envPath, $fixed)) {
                echo "✅ Fixed .env file written\n\n";
                
                // Test the fixed file
                echo "=== Testing fixed file ===\n";
                $testEnv = @parse_ini_file($envPath, false, INI_SCANNER_RAW);
                if (is_array($testEnv)) {
                    echo "✅ SUCCESS! File now parses correctly\n";
                    echo "Found " . count($testEnv) . " environment variables\n";
                    
                    // Show key variables
                    $importantKeys = ['OPENAI_API_KEY', 'ADMIN_USERNAME', 'ADMIN_PASSWORD', 'OPENAI_DASHBOARD_PASSWORD'];
                    echo "\nImportant variables:\n";
                    foreach ($importantKeys as $key) {
                        if (isset($testEnv[$key])) {
                            $value = $testEnv[$key];
                            if (strlen($value) > 30) {
                                $value = substr($value, 0, 20) . '...' . substr($value, -10);
                            }
                            echo "  ✅ {$key}: {$value}\n";
                        } else {
                            echo "  ⚠️  {$key}: NOT SET\n";
                        }
                    }
                } else {
                    echo "❌ Still has parse errors. Restoring backup...\n";
                    copy($backupPath, $envPath);
                    echo "Backup restored.\n";
                }
            } else {
                echo "❌ Failed to write fixed file\n";
            }
        } else {
            echo "❌ Failed to create backup. Aborting fix.\n";
        }
    } else {
        echo "To apply fixes, visit: " . $_SERVER['REQUEST_URI'] . "?fix=yes\n";
        echo "⚠️  WARNING: This will modify your .env file. Make sure you have a backup!\n";
    }
} else {
    echo "✅ No fixes needed - file is valid!\n";
}

echo "\n=== End of Diagnostic ===\n";
