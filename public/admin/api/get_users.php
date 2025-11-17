<?php

/**
 * Get Users by Role API
 * Returns a list of users filtered by role (student or instructor)
 * Excludes admin users and users without valid email addresses
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

// Get role from request
$role = $_GET['role'] ?? '';

if (empty($role)) {
    http_response_code(400);
    echo json_encode(['error' => 'Role parameter required']);
    exit;
}

// Validate role parameter
if (!in_array($role, ['student', 'instructor'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid role. Must be "student" or "instructor"']);
    exit;
}

try {
    $pdo = Database::getInstance();
    
    // Query to fetch users by role, excluding admins and users without valid emails
    $stmt = $pdo->prepare("
        SELECT id, full_name, school_id, email
        FROM users
        WHERE role = :role
          AND email IS NOT NULL 
          AND email != ''
        ORDER BY full_name
    ");
    
    $stmt->execute(['role' => $role]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $users,
        'count' => count($users),
        'role' => $role
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
