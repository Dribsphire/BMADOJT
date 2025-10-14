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
    header('Location: documents.php?error=invalid_document');
    exit;
}

// Get document
$documentService = new DocumentService();
$document = $documentService->getDocumentById($documentId);

if (!$document || !$document->isTemplate()) {
    header('Location: documents.php?error=document_not_found');
    exit;
}

// Get student's section ID
$pdo = App\Utils\Database::getInstance();
$stmt = $pdo->prepare("SELECT section_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

// Check if student can access this template
// Allow access if: template is pre-loaded (uploaded_for_section IS NULL) OR template is for student's section
if ($document->uploaded_for_section !== null && $document->uploaded_for_section != $student['section_id']) {
    header('Location: documents.php?error=access_denied');
    exit;
}

// Check if file exists (adjust path for current directory)
$filePath = getcwd() . '/' . $document->file_path;
if (!file_exists($filePath)) {
    header('Location: documents.php?error=file_not_found');
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
$mimeType = mime_content_type($filePath);
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . basename($document->file_path) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Output file
readfile($filePath);
exit;
