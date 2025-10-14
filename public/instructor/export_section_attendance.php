<?php

/**
 * Export Section Attendance
 * OJT Route - Export attendance data for selected students
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Services\SectionAttendanceService;
use App\Utils\Database;

// Start session
session_start();
date_default_timezone_set('Asia/Manila');

try {
    // Initialize authentication
    $authService = new AuthenticationService();
    $authMiddleware = new AuthMiddleware();

    // Check authentication and authorization
    if (!$authMiddleware->check()) {
        $authMiddleware->redirectToLogin();
    }

    if (!$authMiddleware->requireRole('instructor')) {
        $authMiddleware->redirectToUnauthorized();
    }

    // Get current user
    $user = $authMiddleware->getCurrentUser();

    // Get selected student IDs
    $studentIds = $_GET['student_ids'] ?? [];
    if (empty($studentIds)) {
        $_SESSION['error'] = 'No students selected for export.';
        header('Location: section_attendance.php');
        exit;
    }

    // Initialize services
    $pdo = Database::getInstance();
    $attendanceService = new SectionAttendanceService($pdo);

    // Verify students belong to instructor's section
    $placeholders = str_repeat('?,', count($studentIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT u.id, u.school_id, u.full_name, u.email, sp.workplace_name, s.section_name
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        INNER JOIN sections s ON u.section_id = s.id
        WHERE u.id IN ($placeholders) AND s.instructor_id = ? AND u.role = 'student'
        ORDER BY u.full_name
    ");
    $params = array_merge($studentIds, [$user->id]);
    $stmt->execute($params);
    $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($students)) {
        $_SESSION['error'] = 'No valid students found for export.';
        header('Location: section_attendance.php');
        exit;
    }

    // Get section info
    $stmt = $pdo->prepare("
        SELECT section_name, section_code
        FROM sections
        WHERE instructor_id = ?
    ");
    $stmt->execute([$user->id]);
    $section = $stmt->fetch(PDO::FETCH_ASSOC);

    // Set headers for CSV download
    $filename = "section_attendance_" . $section['section_code'] . "_" . date('Y-m-d') . ".csv";
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
        'Student ID',
        'Student Name',
        'Section',
        'Workplace',
        'Email',
        'Total Hours',
        'Completion Rate (%)',
        'Completed Records',
        'Incomplete Records',
        'Missed Records',
        'Pending Requests',
        'Today Status',
        'Today Hours'
    ]);

    // Get attendance data for each student
    foreach ($students as $student) {
        $attendanceData = $attendanceService->getSectionStudents($user->id, ['search' => $student['school_id']]);
        $studentData = $attendanceData[0] ?? null;

        if ($studentData) {
            fputcsv($output, [
                $student['school_id'],
                $student['full_name'],
                $student['section_name'],
                $student['workplace_name'],
                $student['email'],
                number_format($studentData['total_hours'], 2),
                $studentData['completion_rate'],
                $studentData['completed_records'],
                $studentData['incomplete_records'],
                $studentData['missed_records'],
                $studentData['pending_requests'],
                ucfirst($studentData['today_status']),
                number_format($studentData['today_hours'], 2)
            ]);
        }
    }

    // Add summary section
    fputcsv($output, []); // Empty row
    fputcsv($output, ['SECTION SUMMARY']);
    fputcsv($output, ['Section Name', $section['section_name']]);
    fputcsv($output, ['Section Code', $section['section_code']]);
    fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Total Students Exported', count($students)]);
    fputcsv($output, ['Instructor', $user->full_name]);

    fclose($output);

} catch (Exception $e) {
    error_log("Export section attendance error: " . $e->getMessage());
    $_SESSION['error'] = 'Error generating export: ' . $e->getMessage();
    header('Location: section_attendance.php');
}
?>
