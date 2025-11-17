<?php
/**
 * API endpoint to provide student data for DTR printing
 * Returns JSON data with student information needed for DTR header
 */

require_once '../../vendor/autoload.php';
use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;

session_start();
date_default_timezone_set('Asia/Manila');

// Set JSON header
header('Content-Type: application/json');

try {
    // Authentication
    $authService = new AuthenticationService();
    $authMiddleware = new AuthMiddleware();
    
    if (!$authMiddleware->requireRole('student')) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $user = $authService->getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    // Get database connection
    $pdo = Database::getInstance();
    
    // Get student data with profile and section info
    $stmt = $pdo->prepare("
        SELECT 
            u.full_name,
            u.school_id,
            s.section_code,
            s.section_name,
            sp.workplace_name,
            sp.supervisor_name,
            sp.student_position,
            sp.company_head
        FROM users u
        LEFT JOIN sections s ON u.section_id = s.id
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.id = ?
    ");
    $stmt->execute([$user->id]);
    $studentData = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$studentData) {
        http_response_code(404);
        echo json_encode(['error' => 'Student data not found']);
        exit;
    }
    
    echo json_encode($studentData);
    
} catch (Exception $e) {
    error_log("Get student DTR data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>

