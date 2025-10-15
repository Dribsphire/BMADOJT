<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Utils/Database.php';

try {
    $pdo = App\Utils\Database::getInstance();
    
    // Read the SQL file
    $sql = file_get_contents(__DIR__ . '/005_seed_document_templates.sql');
    
    if ($sql === false) {
        throw new Exception('Could not read migration file');
    }
    
    // Execute the migration
    $pdo->exec($sql);
    
    echo "Migration 005 executed successfully!\n";
    echo "7 document templates have been seeded into the database.\n";
    
    // Verify the insertion
    $stmt = $pdo->prepare("
        SELECT 
            id,
            document_name,
            document_type,
            file_path,
            uploaded_by,
            uploaded_for_section,
            created_at
        FROM documents 
        WHERE uploaded_for_section IS NULL
        ORDER BY document_type
    ");
    $stmt->execute();
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "\nTemplates created:\n";
    foreach ($templates as $template) {
        echo "- {$template['document_name']} ({$template['document_type']})\n";
    }
    
} catch (Exception $e) {
    echo "Migration failed: " . $e->getMessage() . "\n";
    exit(1);
}
