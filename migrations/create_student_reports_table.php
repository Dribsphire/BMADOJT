<?php
/**
 * Migration: Create student_reports table
 * Run this script to create the table for weekly and monthly report submissions
 */

require_once __DIR__ . '/../vendor/autoload.php';
use App\Utils\Database;

$pdo = Database::getInstance();

try {
    // Read SQL file
    $sqlFile = __DIR__ . '/create_student_reports_table.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found: $sqlFile");
    }
    
    $sql = file_get_contents($sqlFile);
    
    // Remove comments and split by semicolon
    $sql = preg_replace('/--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', explode(';', $sql)));
    
    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }
    
    echo "✓ Successfully created student_reports table\n";
    echo "\nTable structure:\n";
    echo "  - id: Primary key\n";
    echo "  - student_id: Foreign key to users table\n";
    echo "  - report_type: 'weekly' or 'monthly'\n";
    echo "  - report_period: Week (e.g., '2024-W01') or Month (e.g., '2024-01')\n";
    echo "  - file_path: Path to uploaded file\n";
    echo "  - status: pending, approved, revision_required, rejected\n";
    echo "  - Unique constraint: One report per student per period\n";
    
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'already exists') !== false) {
        echo "⚠ Table student_reports already exists\n";
    } else {
        echo "✗ Error: " . $e->getMessage() . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "✗ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

