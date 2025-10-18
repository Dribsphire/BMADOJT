<?php

/**
 * Forgot Time-Out Request Submission Handler
 * OJT Route - Forgot Time-Out Request System
 */

require_once '../../vendor/autoload.php';

use App\Controllers\ForgotTimeoutController;
use App\Middleware\AuthMiddleware;

// Start session
session_start();

// Initialize authentication
$authMiddleware = new AuthMiddleware();

// Check authentication and authorization
if (!$authMiddleware->check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

if (!$authMiddleware->requireRole('student')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Initialize controller
$controller = new ForgotTimeoutController();

// Handle different actions
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    switch ($action) {
        case 'get_attendance_records':
            $controller->getAttendanceRecordsWithoutTimeout();
            break;
            
        case 'get_requests':
            $controller->getStudentRequests();
            break;
            
        case 'get_request':
            if (isset($_GET['id'])) {
                $controller->getRequestDetails((int) $_GET['id']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID required']);
            }
            break;
            
        case 'download_letter':
            if (isset($_GET['id'])) {
                $controller->downloadLetter((int) $_GET['id']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID required']);
            }
            break;
            
        case 'preview_letter':
            if (isset($_GET['id'])) {
                $controller->previewLetter((int) $_GET['id']);
            } else {
                http_response_code(400);
                echo json_encode(['error' => 'Request ID required']);
            }
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} else {
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $controller->submitRequest();
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}
