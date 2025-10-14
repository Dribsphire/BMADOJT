<?php

/**
 * Get Recipient Count API
 * Returns the count of recipients for notification sending
 */

require_once '../../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;
use App\Utils\AdminAccess;

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

// Check admin access
try {
    AdminAccess::requireAdminAccess();
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

// Get recipient type from request
$recipient_type = $_GET['type'] ?? '';

if (empty($recipient_type)) {
    http_response_code(400);
    echo json_encode(['error' => 'Recipient type required']);
    exit;
}

try {
    $pdo = Database::getInstance();
    
    switch ($recipient_type) {
        case 'students':
            $stmt = $pdo->query("
                SELECT COUNT(*) as count
                FROM users 
                WHERE role = 'student' AND email IS NOT NULL AND email != ''
            ");
            break;
        case 'instructors':
            $stmt = $pdo->query("
                SELECT COUNT(*) as count
                FROM users 
                WHERE role = 'instructor' AND email IS NOT NULL AND email != ''
            ");
            break;
        case 'all':
            $stmt = $pdo->query("
                SELECT COUNT(*) as count
                FROM users 
                WHERE email IS NOT NULL AND email != ''
            ");
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid recipient type']);
            exit;
    }
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = (int)$result['count'];
    
    // Get additional details
    $details = [];
    if ($recipient_type === 'all') {
        $stmt = $pdo->query("
            SELECT role, COUNT(*) as count
            FROM users 
            WHERE email IS NOT NULL AND email != ''
            GROUP BY role
        ");
        $role_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $details['by_role'] = $role_counts;
    }
    
    echo json_encode([
        'success' => true,
        'count' => $count,
        'type' => $recipient_type,
        'details' => $details
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
