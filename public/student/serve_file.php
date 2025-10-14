<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../src/Utils/Database.php';
require_once '../../src/Services/DocumentService.php';
require_once '../../src/Middleware/AuthMiddleware.php';

use App\Services\DocumentService;
use App\Middleware\AuthMiddleware;

// Check authentication
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('student')) {
    $authMiddleware->redirectToUnauthorized();
}

// Get document ID
$documentId = (int) ($_GET['id'] ?? 0);

if ($documentId <= 0) {
    http_response_code(400);
    echo "Invalid document ID";
    exit;
}

try {
    // Get document
    $documentService = new DocumentService();
    $document = $documentService->getDocumentById($documentId);

    if (!$document) {
        http_response_code(404);
        echo "Document not found";
        exit;
    }

    // Check if student has access to this document
    // Get student's section
    $pdo = App\Utils\Database::getInstance();
    $stmt = $pdo->prepare("SELECT section_id FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$student) {
        http_response_code(403);
        echo "Access denied";
        exit;
    }
    
    // Allow access if:
    // 1. It's a pre-loaded template (uploaded_for_section is null)
    // 2. It's uploaded for the student's section
    $hasAccess = $document->uploaded_for_section === null || 
                 $document->uploaded_for_section == $student['section_id'];
    
    if (!$hasAccess) {
        http_response_code(403);
        echo "Access denied - document not available for your section";
        exit;
    }

    // Build file path
    $filePath = __DIR__ . '/../../' . $document->file_path;
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo "File not found: " . $filePath;
        exit;
    }

    // Log download activity
    $pdo = App\Utils\Database::getInstance();
    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description) 
        VALUES (?, 'download_template', ?)
    ");
    $stmt->execute([
        $_SESSION['user_id'],
        "Downloaded template: {$document->document_name}"
    ]);

    // Set headers for file download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($document->file_path) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

    // Output file
    readfile($filePath);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo "Error: " . $e->getMessage();
    exit;
}
