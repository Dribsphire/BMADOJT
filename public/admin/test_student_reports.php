<?php
/**
 * Test script to verify student_reports table is working
 * This will show if reports are being saved correctly
 */

require_once '../../vendor/autoload.php';
use App\Utils\Database;

$pdo = Database::getInstance();

echo "<h2>Student Reports Table Test</h2>";

// Check if table exists
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'student_reports'");
    if ($stmt->rowCount() > 0) {
        echo "<p style='color: green;'>✓ Table 'student_reports' exists</p>";
        
        // Get table structure
        $stmt = $pdo->query("DESCRIBE student_reports");
        $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "<h3>Table Structure:</h3><ul>";
        foreach ($columns as $col) {
            echo "<li><strong>{$col['Field']}</strong>: {$col['Type']} " . ($col['Null'] === 'YES' ? '(NULL)' : '(NOT NULL)') . "</li>";
        }
        echo "</ul>";
        
        // Count existing reports
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM student_reports");
        $total = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<p><strong>Total Reports:</strong> {$total['total']}</p>";
        
        // Show recent reports
        $stmt = $pdo->query("
            SELECT sr.*, u.full_name, u.school_id 
            FROM student_reports sr
            LEFT JOIN users u ON sr.student_id = u.id
            ORDER BY sr.created_at DESC
            LIMIT 10
        ");
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($reports)) {
            echo "<h3>Recent Reports (Last 10):</h3>";
            echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Student</th><th>Type</th><th>Period</th><th>Status</th><th>Submitted</th></tr>";
            foreach ($reports as $report) {
                echo "<tr>";
                echo "<td>{$report['id']}</td>";
                echo "<td>{$report['full_name']} ({$report['school_id']})</td>";
                echo "<td>{$report['report_type']}</td>";
                echo "<td>{$report['report_period']}</td>";
                echo "<td>{$report['status']}</td>";
                echo "<td>{$report['submitted_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p style='color: orange;'>⚠ No reports found in table yet. Students need to submit reports first.</p>";
        }
        
    } else {
        echo "<p style='color: red;'>✗ Table 'student_reports' does NOT exist. Please run the migration script.</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

