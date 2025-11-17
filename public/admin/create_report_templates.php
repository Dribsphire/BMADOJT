<?php
/**
 * Create Weekly and Monthly Report Templates
 * Run this script once to create the report templates in the database
 */

require_once '../../vendor/autoload.php';
use App\Utils\Database;

$pdo = Database::getInstance();

try {
    // Check if templates already exist
    $stmt = $pdo->prepare("SELECT id FROM documents WHERE document_type IN ('weekly_report', 'monthly_report') AND uploaded_by = 1 AND uploaded_for_section IS NULL");
    $stmt->execute();
    $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($existing)) {
        echo "Report templates already exist in the database.\n";
        echo "Existing templates:\n";
        foreach ($existing as $template) {
            $stmt = $pdo->prepare("SELECT document_name, document_type FROM documents WHERE id = ?");
            $stmt->execute([$template['id']]);
            $info = $stmt->fetch(PDO::FETCH_ASSOC);
            echo "  - {$info['document_name']} ({$info['document_type']})\n";
        }
        exit;
    }
    
    // Create Weekly Report template
    $stmt = $pdo->prepare("
        INSERT INTO documents (document_name, document_type, file_path, uploaded_by, uploaded_for_section, is_required, created_at)
        VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    $stmt->execute([
        'Weekly Report',
        'weekly_report',
        '', // No template file needed for reports
        1, // System user
        NULL, // Global template
        0 // Not required (students submit on their own schedule)
    ]);
    $weeklyId = $pdo->lastInsertId();
    echo "✓ Created Weekly Report template (ID: $weeklyId)\n";
    
    // Create Monthly Report template
    $stmt->execute([
        'Monthly Report',
        'monthly_report',
        '', // No template file needed for reports
        1, // System user
        NULL, // Global template
        0 // Not required (students submit on their own schedule)
    ]);
    $monthlyId = $pdo->lastInsertId();
    echo "✓ Created Monthly Report template (ID: $monthlyId)\n";
    
    echo "\nSuccess! Weekly and Monthly Report templates have been created.\n";
    echo "Students can now submit weekly and monthly reports.\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

