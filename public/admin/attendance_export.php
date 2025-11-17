<?php

/**
 * Admin Attendance Export
 * OJT Route - Export attendance data to CSV
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;
use App\Utils\AdminAccess;

// Start session
session_start();
date_default_timezone_set('Asia/Manila');

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication and authorization
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

// Check admin access
AdminAccess::requireAdminAccess();

// Get export parameters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01');
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$sectionId = $_GET['section_id'] ?? '';
$instructorId = $_GET['instructor_id'] ?? '';

// Initialize database
$pdo = Database::getInstance();

// Build query conditions
$whereConditions = ["ar.time_in IS NOT NULL"];
$params = [];

if ($dateFrom && $dateTo) {
    $whereConditions[] = "DATE(ar.date) BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
}

if ($sectionId) {
    $whereConditions[] = "u.section_id = ?";
    $params[] = $sectionId;
}

if ($instructorId) {
    $whereConditions[] = "s.instructor_id = ?";
    $params[] = $instructorId;
}

$whereClause = implode(' AND ', $whereConditions);

// Get student data with total hours grouped by student
$stmt = $pdo->prepare("
    SELECT 
        u.id as student_id,
        u.full_name as student_name,
        s.section_code,
        COALESCE(SUM(ar.hours_earned), 0) as total_hours,
        sp.workplace_name,
        sp.student_position
    FROM users u
    INNER JOIN attendance_records ar ON u.id = ar.student_id
    LEFT JOIN sections s ON u.section_id = s.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE {$whereClause}
    GROUP BY u.id, u.full_name, s.section_code, sp.workplace_name, sp.student_position
    ORDER BY u.full_name
");
$stmt->execute($params);
$studentData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// CSV Export
$filename = 'attendance_report_' . date('Y-m-d_H-i-s') . '.csv';

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');

// CSV Headers
fputcsv($output, [
    'No.',
    'Student Name',
    'Section',
    'Total Hours',
    'Workplace',
    'Position'
]);

// CSV Data
$counter = 1;
foreach ($studentData as $row) {
    fputcsv($output, [
        $counter,
        $row['student_name'],
        $row['section_code'] ?? 'N/A',
        number_format($row['total_hours'], 2),
        $row['workplace_name'] ?? '',
        $row['student_position'] ?? ''
    ]);
    $counter++;
}

// Footer section
fputcsv($output, []); // Empty row
$year = date('Y', strtotime($dateTo));
fputcsv($output, ['Year', $year]);
fputcsv($output, ['Date', date('F j, Y', strtotime($dateTo))]);

fclose($output);
exit;
