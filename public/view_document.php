<?php
session_start();

require_once '../vendor/autoload.php';
use App\Middleware\AuthMiddleware;

// Check authentication
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->check()) {
    http_response_code(401);
    die('Unauthorized');
}

// Check if user has permission to view documents (instructor or admin)
if (!$authMiddleware->requireAnyRole(['instructor', 'admin'])) {
    http_response_code(403);
    die('Forbidden');
}

// Get document ID from query parameter
$documentId = $_GET['id'] ?? 0;

if (!$documentId) {
    http_response_code(400);
    die('Document ID required');
}

try {
    $pdo = App\Utils\Database::getInstance();
    
    // Get document details
    $stmt = $pdo->prepare("
        SELECT sd.*, d.document_name, d.document_type,
               u.full_name as student_name, u.section_id
        FROM student_documents sd
        JOIN documents d ON sd.document_id = d.id
        JOIN users u ON sd.student_id = u.id
        WHERE sd.id = ?
    ");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch();
    
    if (!$document) {
        http_response_code(404);
        die('Document not found');
    }
    
    // Check if instructor has access to this student's section
    if ($_SESSION['role'] === 'instructor') {
        $instructorStmt = $pdo->prepare("
            SELECT section_id FROM users WHERE id = ?
        ");
        $instructorStmt->execute([$_SESSION['user_id']]);
        $instructor = $instructorStmt->fetch();
        
        if (!$instructor || $instructor['section_id'] != $document['section_id']) {
            http_response_code(403);
            die('Access denied to this document');
        }
    }
    
    // Construct file path
    $filePath = __DIR__ . '/../uploads/student_documents/' . basename($document['submission_file_path']);
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        die('File not found');
    }
    
    // Get file info
    $fileSize = filesize($filePath);
    $mimeType = mime_content_type($filePath);
    $fileName = basename($document['submission_file_path']);
    
    // Set appropriate headers
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . $fileSize);
    header('Content-Disposition: inline; filename="' . $fileName . '"');
    header('Cache-Control: private, max-age=3600');
    
    // Output file content
    readfile($filePath);
    
} catch (Exception $e) {
    http_response_code(500);
    die('Error loading document: ' . $e->getMessage());
}
