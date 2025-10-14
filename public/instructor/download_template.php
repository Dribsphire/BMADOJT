<?php
require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Services\DocumentService;
use App\Utils\Database;

$authService = new AuthenticationService();
$authMiddleware = new App\Middleware\AuthMiddleware($authService);

// Ensure user is logged in and is an instructor
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}
if (!$authMiddleware->requireRole('instructor')) {
    $authMiddleware->redirectToUnauthorized();
}

$documentId = (int) ($_GET['id'] ?? 0);

if ($documentId <= 0) {
    header('Location: templates.php?error=invalid_document');
    exit;
}

$documentService = new DocumentService();
$document = $documentService->getDocumentById($documentId);

if (!$document) {
    header('Location: templates.php?error=document_not_found');
    exit;
}

// Check if instructor owns this template or it's a system template
if ($document->uploaded_by !== $_SESSION['user_id'] && $document->uploaded_by !== 1) {
    header('Location: templates.php?error=access_denied');
    exit;
}

$filePath = '';
$fileName = '';

if ($document->isTemplate()) {
    // For templates, use the file_path from the documents table
    $filePath = __DIR__ . '/../../' . $document->file_path;
    $fileName = basename($document->file_path);
} else {
    header('Location: templates.php?error=not_a_template');
    exit;
}

if (!file_exists($filePath)) {
    error_log("File not found: " . $filePath);
    header('Location: templates.php?error=file_not_found');
    exit;
}

// Log download activity
$pdo = Database::getInstance();
$stmt = $pdo->prepare("
    INSERT INTO activity_logs (user_id, action, description) 
    VALUES (?, 'Document Download', ?)
");
$stmt->execute([
    $_SESSION['user_id'],
    "Downloaded template: {$document->document_name}"
]);

// Determine content type based on file extension
$extension = strtolower(pathinfo($document->file_path, PATHINFO_EXTENSION));
$contentType = match($extension) {
    'pdf' => 'application/pdf',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'doc' => 'application/msword',
    'txt' => 'text/plain',
    default => 'application/octet-stream'
};

// Set headers for file download
header('Content-Type: ' . $contentType);
header('Content-Disposition: attachment; filename="' . $fileName . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Output file
readfile($filePath);
exit;
