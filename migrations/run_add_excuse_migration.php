<?php
/**
 * Migration: Add excuse to student_reports table
 * Run this script to add 'excuse' as a report_type and add excuse_date field
 */

require_once __DIR__ . '/../vendor/autoload.php';
use App\Utils\Database;

$pdo = Database::getInstance();

try {
    // Read SQL file
    $sqlFile = __DIR__ . '/add_excuse_to_student_reports.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Remove comments and split by semicolon
    $sql = preg_replace('/--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            // Handle IF NOT EXISTS for MySQL (MySQL doesn't support IF NOT EXISTS for ALTER TABLE ADD COLUMN)
            if (stripos($statement, 'ADD COLUMN IF NOT EXISTS') !== false) {
                $columnName = '';
                if (preg_match('/ADD COLUMN IF NOT EXISTS\s+(\w+)/i', $statement, $matches)) {
                    $columnName = $matches[1];
                    // Check if column exists
                    $checkStmt = $pdo->query("SHOW COLUMNS FROM student_reports LIKE '$columnName'");
                    if ($checkStmt->rowCount() > 0) {
                        echo "⚠ Column $columnName already exists, skipping...\n";
                        continue;
                    }
                    // Remove IF NOT EXISTS from statement
                    $statement = str_ireplace('IF NOT EXISTS', '', $statement);
                }
            }
            if (stripos($statement, 'ADD INDEX IF NOT EXISTS') !== false) {
                $indexName = '';
                if (preg_match('/ADD INDEX IF NOT EXISTS\s+(\w+)/i', $statement, $matches)) {
                    $indexName = $matches[1];
                    // Check if index exists
                    $checkStmt = $pdo->query("SHOW INDEX FROM student_reports WHERE Key_name = '$indexName'");
                    if ($checkStmt->rowCount() > 0) {
                        echo "⚠ Index $indexName already exists, skipping...\n";
                        continue;
                    }
                    // Remove IF NOT EXISTS from statement
                    $statement = str_ireplace('IF NOT EXISTS', '', $statement);
                }
            }
            
            $pdo->exec($statement);
            echo "✓ Executed: " . substr($statement, 0, 60) . "...\n";
        }
    }
    
    echo "\n✓ Successfully updated student_reports table\n";
    echo "\nChanges:\n";
    echo "  - Added 'excuse' to report_type ENUM\n";
    echo "  - Added excuse_date DATE field\n";
    echo "  - Added index on excuse_date\n";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false || 
        strpos($e->getMessage(), 'already exists') !== false ||
        strpos($e->getMessage(), 'Duplicate key name') !== false) {
        echo "⚠ Some changes may already exist: " . $e->getMessage() . "\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

