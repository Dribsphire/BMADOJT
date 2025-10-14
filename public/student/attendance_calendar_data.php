<?php
/**
 * API endpoint to provide attendance data for the calendar
 * Returns JSON data for FullCalendar events
 */

require_once '../../vendor/autoload.php';
use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Services\AttendanceHistoryService;
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
    $historyService = new AttendanceHistoryService($pdo);
    
    // Check if specific date is requested
    $dateFilter = $_GET['date'] ?? null;
    
    if ($dateFilter) {
        // Get records for specific date
        $stmt = $pdo->prepare("
            SELECT 
                id, date, block_type, time_in, time_out, hours_earned, photo_path
            FROM attendance_records
            WHERE student_id = ? AND date = ?
            ORDER BY 
                CASE block_type 
                    WHEN 'morning' THEN 1 
                    WHEN 'afternoon' THEN 2 
                    WHEN 'overtime' THEN 3 
                END
        ");
        $stmt->execute([$user->id, $dateFilter]);
        $attendanceHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Get all attendance records for calendar (no pagination for calendar)
        $attendanceHistory = $historyService->getAttendanceHistory($user->id, 'all', 1000, 0);
    }
    
    // Transform data for calendar
    $calendarData = [];
    
    foreach ($attendanceHistory as $record) {
        // Get forgot timeout request info if exists
        $forgotTimeoutInfo = null;
        if ($record['time_in'] && !$record['time_out']) {
            $stmt = $pdo->prepare("
                SELECT id, status, instructor_response 
                FROM forgot_timeout_requests 
                WHERE attendance_record_id = ?
            ");
            $stmt->execute([$record['id']]);
            $forgotTimeoutInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        $calendarData[] = [
            'id' => $record['id'],
            'date' => $record['date'],
            'block_type' => $record['block_type'],
            'time_in' => $record['time_in'],
            'time_out' => $record['time_out'],
            'hours_earned' => $record['hours_earned'],
            'time_in_photo_path' => $record['photo_path'] ?? null,
            'forgot_timeout_request_id' => $forgotTimeoutInfo ? $forgotTimeoutInfo['id'] : null,
            'forgot_timeout_status' => $forgotTimeoutInfo ? $forgotTimeoutInfo['status'] : null,
            'instructor_response' => $forgotTimeoutInfo ? $forgotTimeoutInfo['instructor_response'] : null
        ];
    }
    
    echo json_encode($calendarData);
    
} catch (Exception $e) {
    error_log("Attendance calendar data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
