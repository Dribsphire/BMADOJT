<?php
/**
 * Letter download endpoint for forgot timeout requests
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Services\ForgotTimeoutReviewService;
use App\Utils\Database;

// Start session
session_start();

// Set timezone to Philippines (UTC+08:00)
date_default_timezone_set('Asia/Manila');

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication
if (!$authMiddleware->requireRole('instructor')) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get current user
$user = $authService->getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get request ID
$requestId = (int)($_GET['id'] ?? 0);
if (!$requestId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request ID']);
    exit;
}

// Initialize services
$pdo = Database::getInstance();
$reviewService = new ForgotTimeoutReviewService($pdo);

// Get letter file path
$filePath = $reviewService->getLetterFilePath($requestId, $user->id);

if (!$filePath) {
    http_response_code(404);
    echo json_encode(['error' => 'Letter not found']);
    exit;
}

// Construct full file path
$fullPath = __DIR__ . '/../../uploads/letters/' . basename($filePath);

// Check if file exists
if (!file_exists($fullPath)) {
    http_response_code(404);
    echo json_encode(['error' => 'Letter file not found']);
    exit;
}

// Get file info
$fileInfo = pathinfo($fullPath);
$mimeType = match($fileInfo['extension']) {
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    default => 'application/octet-stream'
};

// Set headers for preview (inline) or download based on file type
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . filesize($fullPath));
header('Cache-Control: no-cache, must-revalidate');
header('Expires: 0');

// For PDF files, use inline to open in browser
// For other files, use attachment to download
if ($fileInfo['extension'] === 'pdf') {
    header('Content-Disposition: inline; filename="' . basename($filePath) . '"');
} else {
    header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
}

// Output file
readfile($fullPath);
exit;
