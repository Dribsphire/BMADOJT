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
    
    // Check if user is student or instructor
    if (!$authMiddleware->check()) {
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
    
    // Check if user has valid role
    if (!in_array($user->role, ['student', 'instructor'])) {
        http_response_code(401);
        echo json_encode(['error' => 'Unauthorized role']);
        exit;
    }
    
    // Get database connection
    $pdo = Database::getInstance();
    
    // Determine student ID
    $studentId = $user->id; // Default to current user
    
    // If instructor is accessing, get student ID from parameter
    if ($user->role === 'instructor' && isset($_GET['student_id'])) {
        $studentId = (int)$_GET['student_id'];
        
        // Verify instructor has access to this student
        $stmt = $pdo->prepare("
            SELECT u.id 
            FROM users u 
            WHERE u.id = ? AND u.section_id = ? AND u.role = 'student'
        ");
        $stmt->execute([$studentId, $user->section_id]);
        if (!$stmt->fetch()) {
            http_response_code(403);
            echo json_encode(['error' => 'Access denied to this student']);
            exit;
        }
    }
    $historyService = new AttendanceHistoryService($pdo);
    
    // Check if specific date or month is requested
    $dateFilter = $_GET['date'] ?? null;
    $monthFilter = $_GET['month'] ?? null;
    
    if ($monthFilter) {
        // Get records for specific month
        $stmt = $pdo->prepare("
            SELECT 
                id, date, block_type, time_in, time_out, hours_earned, photo_path
            FROM attendance_records
            WHERE student_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
            ORDER BY date ASC, 
                CASE block_type 
                    WHEN 'morning' THEN 1 
                    WHEN 'afternoon' THEN 2 
                    WHEN 'overtime' THEN 3 
                END
        ");
        $stmt->execute([$studentId, $monthFilter]);
        $attendanceHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For monthly data, just return the records without missed day logic
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
    } else if ($dateFilter) {
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
        $stmt->execute([$studentId, $dateFilter]);
        $attendanceHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // For specific date, just return the records without missed day logic
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
        
        // If no records for this date, check if it should be marked as missed
        if (empty($calendarData)) {
            // Get student's OJT start date to check if this date should be marked as missed
            $stmt = $pdo->prepare("
                SELECT ojt_start_date 
                FROM student_profiles 
                WHERE user_id = ?
            ");
            $stmt->execute([$studentId]);
            $ojtStart = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($ojtStart) {
                $startDate = $ojtStart['ojt_start_date'];
                $currentDate = date('Y-m-d');
                
                // Check if the requested date is within OJT period and before today
                if ($dateFilter >= $startDate && $dateFilter <= $currentDate) {
                    // Check if it's a weekday
                    $dayOfWeek = date('w', strtotime($dateFilter));
                    if ($dayOfWeek != 0 && $dayOfWeek != 6) { // Not weekend
                        $calendarData[] = [
                            'id' => 'missed_' . str_replace('-', '', $dateFilter),
                            'date' => $dateFilter,
                            'block_type' => 'missed',
                            'time_in' => null,
                            'time_out' => null,
                            'hours_earned' => 0,
                            'time_in_photo_path' => null,
                            'forgot_timeout_request_id' => null,
                            'forgot_timeout_status' => null,
                            'instructor_response' => null,
                            'is_missed' => true
                        ];
                    }
                }
            }
        }
    } else {
        // Get all attendance records for calendar (no pagination for calendar)
        $attendanceHistory = $historyService->getAttendanceHistory($studentId, 'all', 1000, 0);
        
        // Get student's OJT start date from student_profiles table
        $stmt = $pdo->prepare("
            SELECT ojt_start_date 
            FROM student_profiles 
            WHERE user_id = ?
        ");
        $stmt->execute([$studentId]);
        $ojtStart = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $startDate = $ojtStart ? $ojtStart['ojt_start_date'] : date('Y-m-01'); // Default to current month start
        $endDate = date('Y-m-t'); // Default to current month end
        $currentDate = date('Y-m-d');
        
        // Create array of all dates in the OJT period
        $allDates = [];
        $start = new DateTime($startDate);
        $end = new DateTime($endDate);
        $current = new DateTime($currentDate);
        
        // Only include dates up to today
        if ($current < $end) {
            $end = $current;
        }
        
        while ($start <= $end) {
            // Skip weekends (Saturday = 6, Sunday = 0)
            $dayOfWeek = (int)$start->format('w');
            if ($dayOfWeek != 0 && $dayOfWeek != 6) {
                $allDates[] = $start->format('Y-m-d');
            }
            $start->add(new DateInterval('P1D'));
        }
        
        // Get all dates that have attendance records
        $attendedDates = [];
        foreach ($attendanceHistory as $record) {
            if (!in_array($record['date'], $attendedDates)) {
                $attendedDates[] = $record['date'];
            }
        }
        
        // Find missed dates (dates in OJT period but no attendance)
        $missedDates = array_diff($allDates, $attendedDates);
        
        // Transform data for calendar
        $calendarData = [];
        
        // Add actual attendance records
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
        
        // Add missed days as virtual records
        foreach ($missedDates as $missedDate) {
            $calendarData[] = [
                'id' => 'missed_' . str_replace('-', '', $missedDate), // Virtual ID for missed days
                'date' => $missedDate,
                'block_type' => 'missed',
                'time_in' => null,
                'time_out' => null,
                'hours_earned' => 0,
                'time_in_photo_path' => null,
                'forgot_timeout_request_id' => null,
                'forgot_timeout_status' => null,
                'instructor_response' => null,
                'is_missed' => true // Flag to identify missed days
            ];
        }
    }
    
    echo json_encode($calendarData);
    
} catch (Exception $e) {
    error_log("Attendance calendar data error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
