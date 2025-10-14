<?php
/**
 * Secure endpoint to serve attendance photos
 * Handles time-in and time-out photos with proper authentication
 */

require_once '../vendor/autoload.php';
use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;

session_start();
date_default_timezone_set('Asia/Manila');

try {
    // Authentication
    $authService = new AuthenticationService();
    $authMiddleware = new AuthMiddleware();
    
    if (!$authMiddleware->requireRole('student')) {
        http_response_code(401);
        echo "Unauthorized access";
        exit;
    }
    
    $user = $authService->getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo "User not found";
        exit;
    }
    
    // Get parameters
    $recordId = $_GET['id'] ?? null;
    $photoType = $_GET['type'] ?? null;
    
    if (!$recordId || !$photoType) {
        http_response_code(400);
        echo "Missing parameters";
        exit;
    }
    
    if (!in_array($photoType, ['time_in', 'time_out'])) {
        http_response_code(400);
        echo "Invalid photo type";
        exit;
    }
    
    // Get database connection
    $pdo = Database::getInstance();
    
    // Verify the attendance record belongs to the current user
    $stmt = $pdo->prepare("
        SELECT id, photo_path 
        FROM attendance_records 
        WHERE id = ? AND student_id = ?
    ");
    $stmt->execute([$recordId, $user->id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$record) {
        http_response_code(404);
        echo "Attendance record not found";
        exit;
    }
    
    // Get the photo path (only time-in photos are stored currently)
    $photoPath = null;
    if ($photoType === 'time_in') {
        $photoPath = $record['photo_path'];
    } elseif ($photoType === 'time_out') {
        // Time-out photos are not currently stored
        http_response_code(404);
        echo "Time-out photos not available";
        exit;
    }
    
    if (!$photoPath) {
        http_response_code(404);
        echo "Photo not found";
        exit;
    }
    
    // Construct full file path
    // The photoPath from database is already "uploads/attendance_photos/filename.jpg"
    $fullPath = __DIR__ . '/../' . $photoPath;
    
    // Check if file exists
    if (!file_exists($fullPath)) {
        http_response_code(404);
        echo "Photo file not found";
        exit;
    }
    
    // Get file info
    $fileInfo = pathinfo($fullPath);
    $mimeType = mime_content_type($fullPath);
    
    // Set headers for image display
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($fullPath));
    header('Cache-Control: public, max-age=3600');
    header('Content-Disposition: inline; filename="' . basename($photoPath) . '"');
    
    // Output the image
    readfile($fullPath);
    
} catch (Exception $e) {
    error_log("View attendance photo error: " . $e->getMessage());
    http_response_code(500);
    echo "Internal server error";
}
?>
