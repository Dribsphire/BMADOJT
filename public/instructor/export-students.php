<?php

/**
 * Export Students to CSV
 * OJT Route - Export student list with filtering and search
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;

// Start session
session_start();

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

// Get instructor's section information
$pdo = Database::getInstance();

// Get section details
$stmt = $pdo->prepare("
    SELECT s.*
    FROM sections s
    WHERE s.id = ?
");
$stmt->execute([$user->section_id]);
$section = $stmt->fetch(PDO::FETCH_OBJ);

if (!$section) {
    $_SESSION['error'] = 'You are not assigned to any section. Please contact the administrator.';
    header('Location: ../login.php');
    exit;
}

// Search and filter parameters (same as student-list.php)
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'full_name';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'ASC';

// Validate sort parameters
$allowed_sorts = ['school_id', 'full_name', 'workplace_name', 'total_hours'];
$allowed_orders = ['ASC', 'DESC'];

if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'full_name';
}
if (!in_array($sort_order, $allowed_orders)) {
    $sort_order = 'ASC';
}

// Build the query (same as student-list.php)
$where_conditions = ["u.section_id = ?", "u.role = 'student'"];
$params = [$user->section_id];

if (!empty($search)) {
    $where_conditions[] = "(u.school_id LIKE ? OR u.full_name LIKE ? OR sp.workplace_name LIKE ?)";
    $search_param = "%{$search}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

if (!empty($status_filter)) {
    $where_conditions[] = "sp.status = ?";
    $params[] = $status_filter;
}

$where_clause = implode(' AND ', $where_conditions);

// Get all students (no pagination for export)
$students_sql = "
    SELECT 
        u.id,
        u.school_id,
        u.full_name,
        u.email,
        u.contact,
        sp.workplace_name,
        sp.supervisor_name,
        sp.student_position,
        sp.ojt_start_date,
        sp.status,
        COALESCE(SUM(ar.hours_earned), 0) as total_accumulated_hours,
        COUNT(ar.id) as attendance_records_count,
        MAX(ar.date) as last_attendance_date,
        MIN(ar.date) as first_attendance_date
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    LEFT JOIN attendance_records ar ON u.id = ar.student_id
    WHERE {$where_clause}
    GROUP BY u.id, u.school_id, u.full_name, u.email, u.contact, sp.workplace_name, 
             sp.supervisor_name, sp.student_position, sp.ojt_start_date, sp.status
    ORDER BY {$sort_by} {$sort_order}
";

$stmt = $pdo->prepare($students_sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Set headers for CSV download
$filename = 'students_' . $section->section_code . '_' . date('Y-m-d_H-i-s') . '.csv';
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Create output stream
$output = fopen('php://output', 'w');

// Add BOM for UTF-8 to ensure proper encoding in Excel
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// CSV Headers
$headers = [
    'School ID',
    'Full Name',
    'Email',
    'Contact',
    'Workplace Name',
    'Supervisor Name',
    'Student Position',
    'OJT Start Date',
    'Status',
    'Total Accumulated Hours',
    'Attendance Records Count',
    'First Attendance Date',
    'Last Attendance Date'
];

fputcsv($output, $headers);

// Add section information as metadata
fputcsv($output, []); // Empty row
fputcsv($output, ['Section Information']);
fputcsv($output, ['Section Code', $section->section_code]);
fputcsv($output, ['Section Name', $section->section_name]);
fputcsv($output, ['Export Date', date('Y-m-d H:i:s')]);
fputcsv($output, ['Total Students', count($students)]);
fputcsv($output, []); // Empty row

// Add filter information if any filters were applied
if (!empty($search) || !empty($status_filter)) {
    fputcsv($output, ['Applied Filters']);
    if (!empty($search)) {
        fputcsv($output, ['Search Term', $search]);
    }
    if (!empty($status_filter)) {
        fputcsv($output, ['Status Filter', ucfirst(str_replace('_', ' ', $status_filter))]);
    }
    fputcsv($output, []); // Empty row
}

// Add data rows
foreach ($students as $student) {
    $row = [
        $student['school_id'],
        $student['full_name'],
        $student['email'],
        $student['contact'] ?? '',
        $student['workplace_name'] ?? '',
        $student['supervisor_name'] ?? '',
        $student['student_position'] ?? '',
        $student['ojt_start_date'] ?? '',
        ucfirst(str_replace('_', ' ', $student['status'] ?? 'on_track')),
        number_format($student['total_accumulated_hours'], 2),
        $student['attendance_records_count'],
        $student['first_attendance_date'] ? date('Y-m-d', strtotime($student['first_attendance_date'])) : '',
        $student['last_attendance_date'] ? date('Y-m-d', strtotime($student['last_attendance_date'])) : ''
    ];
    
    fputcsv($output, $row);
}

// Add summary statistics
fputcsv($output, []); // Empty row
fputcsv($output, ['Summary Statistics']);

if (!empty($students)) {
    $total_hours = array_sum(array_column($students, 'total_accumulated_hours'));
    $avg_hours = $total_hours / count($students);
    $max_hours = max(array_column($students, 'total_accumulated_hours'));
    $min_hours = min(array_column($students, 'total_accumulated_hours'));
    
    fputcsv($output, ['Total Hours (All Students)', number_format($total_hours, 2)]);
    fputcsv($output, ['Average Hours per Student', number_format($avg_hours, 2)]);
    fputcsv($output, ['Highest Hours', number_format($max_hours, 2)]);
    fputcsv($output, ['Lowest Hours', number_format($min_hours, 2)]);
    
    // Status breakdown
    $status_counts = [];
    foreach ($students as $student) {
        $status = $student['status'] ?? 'on_track';
        $status_counts[$status] = ($status_counts[$status] ?? 0) + 1;
    }
    
    fputcsv($output, []); // Empty row
    fputcsv($output, ['Status Breakdown']);
    foreach ($status_counts as $status => $count) {
        fputcsv($output, [ucfirst(str_replace('_', ' ', $status)), $count]);
    }
}

fclose($output);
exit;
