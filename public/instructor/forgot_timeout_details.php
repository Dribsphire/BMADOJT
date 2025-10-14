<?php
/**
 * AJAX endpoint for forgot timeout request details
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

// Get request details
$request = $reviewService->getRequestDetails($requestId, $user->id);

if (!$request) {
    http_response_code(404);
    echo json_encode(['error' => 'Request not found']);
    exit;
}

// Set content type
header('Content-Type: application/json');

// Return request details
echo json_encode([
    'success' => true,
    'request' => $request
]);
