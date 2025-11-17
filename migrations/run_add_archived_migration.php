<?php
/**
 * Migration: Add archived column to users table
 * Run this script to add archive functionality for users
 */

require_once __DIR__ . '/../vendor/autoload.php';
use App\Utils\Database;

$pdo = Database::getInstance();

try {
    // Read SQL file
    $sqlFile = __DIR__ . '/add_archived_to_users.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Split SQL statements by semicolon, handling potential issues with comments
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            try {
                $pdo->exec($statement);
                echo "✓ Executed: " . substr($statement, 0, 60) . "...\n";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column name') !== false || 
                    strpos($e->getMessage(), 'already exists') !== false ||
                    strpos($e->getMessage(), 'Duplicate key name') !== false) {
                    echo "⚠ Column/Index already exists, skipping...\n";
                } else {
                    throw $e; // Re-throw other PDO exceptions
                }
            }
        }
    }
    
    echo "\n✓ Successfully updated users table\n";
    echo "\nChanges:\n";
    echo "  - Added archived TINYINT(1) field (default: 0)\n";
    echo "  - Added archived_at TIMESTAMP field\n";
    echo "  - Added archived_by INT UNSIGNED field\n";
    echo "  - Added index on archived\n";
    echo "  - Added foreign key for archived_by\n";
    
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

