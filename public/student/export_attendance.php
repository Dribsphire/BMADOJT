<?php
/**
 * Export attendance history to CSV format
 * Generates a comprehensive attendance report for students
 */

require_once '../../vendor/autoload.php';
use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Services\AttendanceHistoryService;
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
    
    // Get database connection
    $pdo = Database::getInstance();
    $historyService = new AttendanceHistoryService($pdo);
    
    // Get all attendance records
    $attendanceHistory = $historyService->getAttendanceHistory($user->id, 'all', 1000, 0);
    $stats = $historyService->getAttendanceStats($user->id);
    
    // Set headers for CSV download
    $filename = "attendance_history_" . $user->school_id . "_" . date('Y-m-d') . ".csv";
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add BOM for UTF-8 compatibility with Excel
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Write header row
    fputcsv($output, [
        'Date',
        'Day of Week',
        'Block Type',
        'Time In',
        'Time Out',
        'Hours Earned',
        'Status',
        'Location (Lat)',
        'Location (Lng)',
        'Photo Available'
    ]);
    
    // Write attendance records
    foreach ($attendanceHistory as $record) {
        $status = $historyService->getAttendanceStatus($record);
        $statusText = $historyService->getStatusDisplayText($status);
        
        $dateFormatted = date('M j, Y', strtotime($record['date']));
        $dayOfWeek = date('l', strtotime($record['date']));
        $timeIn = $record['time_in'] ? date('g:i A', strtotime($record['time_in'])) : 'Not recorded';
        $timeOut = $record['time_out'] ? date('g:i A', strtotime($record['time_out'])) : 'Not recorded';
        $hours = $record['hours_earned'] > 0 ? number_format($record['hours_earned'], 2) : '0.00';
        $photoAvailable = $record['photo_path'] ? 'Yes' : 'No';
        
        fputcsv($output, [
            $dateFormatted,
            $dayOfWeek,
            ucfirst($record['block_type']),
            $timeIn,
            $timeOut,
            $hours,
            $statusText,
            $record['location_lat_in'] ?? '',
            $record['location_long_in'] ?? '',
            $photoAvailable
        ]);
    }
    
    // Add summary section
    fputcsv($output, []); // Empty row
    fputcsv($output, ['SUMMARY']);
    fputcsv($output, ['Total Hours Accumulated', number_format($stats['total_hours'], 2)]);
    fputcsv($output, ['Completed Records', $stats['completed_records']]);
    fputcsv($output, ['Incomplete Records', $stats['incomplete_records']]);
    fputcsv($output, ['Missed Records', $stats['missed_records']]);
    fputcsv($output, ['Total Records', $stats['total_records']]);
    fputcsv($output, ['Completion Rate', $stats['completion_rate'] . '%']);
    fputcsv($output, ['Required Hours', $stats['required_hours']]);
    fputcsv($output, ['Progress', number_format($stats['hours_progress'], 2) . '%']);
    
    // Add hours breakdown
    fputcsv($output, []); // Empty row
    fputcsv($output, ['HOURS BREAKDOWN']);
    fputcsv($output, ['Morning Hours', number_format($stats['morning_hours'], 2)]);
    fputcsv($output, ['Afternoon Hours', number_format($stats['afternoon_hours'], 2)]);
    fputcsv($output, ['Overtime Hours', number_format($stats['overtime_hours'], 2)]);
    
    fclose($output);
    
} catch (Exception $e) {
    error_log("Export attendance error: " . $e->getMessage());
    http_response_code(500);
    echo "Error generating export: " . $e->getMessage();
}
?>
