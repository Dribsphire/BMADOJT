<?php

/**
 * Admin Attendance Export
 * OJT Route - Export attendance data to CSV/PDF
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
$format = $_GET['format'] ?? 'csv';
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

// Get attendance data
$stmt = $pdo->prepare("
    SELECT 
        ar.id,
        ar.date,
        ar.block_type,
        ar.time_in,
        ar.time_out,
        ar.hours_earned,
        ar.location_lat_in,
        ar.location_long_in,
        u.school_id,
        u.full_name as student_name,
        u.email as student_email,
        s.section_name,
        s.section_code,
        inst.full_name as instructor_name,
        sp.workplace_name,
        CASE 
            WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 'Completed'
            WHEN ar.time_in IS NOT NULL AND ar.time_out IS NULL THEN 'Incomplete'
            ELSE 'Missed'
        END as status
    FROM attendance_records ar
    INNER JOIN users u ON ar.student_id = u.id
    LEFT JOIN sections s ON u.section_id = s.id
    LEFT JOIN users inst ON s.instructor_id = inst.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE {$whereClause}
    ORDER BY ar.date DESC, u.full_name, ar.block_type
");
$stmt->execute($params);
$attendanceData = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get summary statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT ar.student_id) as total_students,
        COUNT(ar.id) as total_records,
        COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as completed_records,
        COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NULL THEN 1 END) as incomplete_records,
        SUM(ar.hours_earned) as total_hours,
        ROUND(AVG(ar.hours_earned), 2) as avg_hours_per_record
    FROM attendance_records ar
    INNER JOIN users u ON ar.student_id = u.id
    LEFT JOIN sections s ON u.section_id = s.id
    WHERE {$whereClause}
");
$stmt->execute($params);
$summaryStats = $stmt->fetch(PDO::FETCH_ASSOC);

if ($format === 'csv') {
    // CSV Export
    $filename = 'attendance_report_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $output = fopen('php://output', 'w');
    
    // CSV Headers
    fputcsv($output, [
        'Student ID',
        'Student Name',
        'Student Email',
        'Section',
        'Instructor',
        'Workplace',
        'Date',
        'Block Type',
        'Time In',
        'Time Out',
        'Hours Earned',
        'Status',
        'Location (Lat)',
        'Location (Lng)'
    ]);
    
    // CSV Data
    foreach ($attendanceData as $row) {
        fputcsv($output, [
            $row['school_id'],
            $row['student_name'],
            $row['student_email'],
            $row['section_name'],
            $row['instructor_name'],
            $row['workplace_name'],
            $row['date'],
            ucfirst($row['block_type']),
            $row['time_in'] ? date('H:i:s', strtotime($row['time_in'])) : '',
            $row['time_out'] ? date('H:i:s', strtotime($row['time_out'])) : '',
            $row['hours_earned'],
            $row['status'],
            $row['location_lat_in'],
            $row['location_long_in']
        ]);
    }
    
    // Summary section
    fputcsv($output, []); // Empty row
    fputcsv($output, ['SUMMARY STATISTICS']);
    fputcsv($output, ['Total Students', $summaryStats['total_students']]);
    fputcsv($output, ['Total Records', $summaryStats['total_records']]);
    fputcsv($output, ['Completed Records', $summaryStats['completed_records']]);
    fputcsv($output, ['Incomplete Records', $summaryStats['incomplete_records']]);
    fputcsv($output, ['Total Hours', $summaryStats['total_hours']]);
    fputcsv($output, ['Average Hours per Record', $summaryStats['avg_hours_per_record']]);
    fputcsv($output, ['Report Generated', date('Y-m-d H:i:s')]);
    fputcsv($output, ['Date Range', $dateFrom . ' to ' . $dateTo]);
    
    fclose($output);
    exit;
    
} elseif ($format === 'pdf') {
    // PDF Export using TCPDF
    require_once '../../vendor/autoload.php';
    
    // Create new PDF document
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('OJT Route System');
    $pdf->SetAuthor('OJT Route Admin');
    $pdf->SetTitle('Attendance Report - ' . date('Y-m-d'));
    $pdf->SetSubject('Attendance Report');
    
    // Set default header data
    $pdf->SetHeaderData('', 0, 'OJT Route Attendance Report', 'Generated on ' . date('F j, Y \a\t g:i A'));
    
    // Set header and footer fonts
    $pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
    $pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));
    
    // Set default monospaced font
    $pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);
    
    // Set margins
    $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
    $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
    $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);
    
    // Set auto page breaks
    $pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);
    
    // Set image scale factor
    $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);
    
    // Add a page
    $pdf->AddPage();
    
    // Set font
    $pdf->SetFont('helvetica', '', 10);
    
    // Report header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Attendance Report', 0, 1, 'C');
    $pdf->Ln(5);
    
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 8, 'Date Range: ' . date('F j, Y', strtotime($dateFrom)) . ' to ' . date('F j, Y', strtotime($dateTo)), 0, 1, 'C');
    $pdf->Ln(10);
    
    // Summary statistics
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 8, 'Summary Statistics', 0, 1, 'L');
    $pdf->Ln(2);
    
    $pdf->SetFont('helvetica', '', 10);
    $summaryData = [
        ['Total Students', $summaryStats['total_students']],
        ['Total Records', $summaryStats['total_records']],
        ['Completed Records', $summaryStats['completed_records']],
        ['Incomplete Records', $summaryStats['incomplete_records']],
        ['Total Hours', number_format($summaryStats['total_hours'], 1)],
        ['Average Hours per Record', $summaryStats['avg_hours_per_record']]
    ];
    
    foreach ($summaryData as $row) {
        $pdf->Cell(80, 6, $row[0] . ':', 0, 0, 'L');
        $pdf->Cell(40, 6, $row[1], 0, 1, 'L');
    }
    
    $pdf->Ln(10);
    
    // Attendance data table
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 8, 'Attendance Records', 0, 1, 'L');
    $pdf->Ln(2);
    
    // Table headers
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(25, 6, 'Student ID', 1, 0, 'C');
    $pdf->Cell(40, 6, 'Student Name', 1, 0, 'C');
    $pdf->Cell(30, 6, 'Section', 1, 0, 'C');
    $pdf->Cell(25, 6, 'Date', 1, 0, 'C');
    $pdf->Cell(20, 6, 'Block', 1, 0, 'C');
    $pdf->Cell(20, 6, 'Time In', 1, 0, 'C');
    $pdf->Cell(20, 6, 'Time Out', 1, 0, 'C');
    $pdf->Cell(15, 6, 'Hours', 1, 0, 'C');
    $pdf->Cell(20, 6, 'Status', 1, 1, 'C');
    
    // Table data
    $pdf->SetFont('helvetica', '', 7);
    foreach ($attendanceData as $row) {
        $pdf->Cell(25, 5, $row['school_id'], 1, 0, 'C');
        $pdf->Cell(40, 5, substr($row['student_name'], 0, 20), 1, 0, 'L');
        $pdf->Cell(30, 5, substr($row['section_name'] ?? 'N/A', 0, 15), 1, 0, 'C');
        $pdf->Cell(25, 5, $row['date'], 1, 0, 'C');
        $pdf->Cell(20, 5, ucfirst($row['block_type']), 1, 0, 'C');
        $pdf->Cell(20, 5, $row['time_in'] ? date('H:i', strtotime($row['time_in'])) : '', 1, 0, 'C');
        $pdf->Cell(20, 5, $row['time_out'] ? date('H:i', strtotime($row['time_out'])) : '', 1, 0, 'C');
        $pdf->Cell(15, 5, $row['hours_earned'], 1, 0, 'C');
        $pdf->Cell(20, 5, $row['status'], 1, 1, 'C');
    }
    
    // Footer
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'I', 8);
    $pdf->Cell(0, 6, 'This report was generated by OJT Route Attendance System', 0, 1, 'C');
    
    // Output PDF
    $filename = 'attendance_report_' . date('Y-m-d_H-i-s') . '.pdf';
    $pdf->Output($filename, 'D');
    exit;
}

// If no format specified, redirect back to reports page
header('Location: attendance_reports.php');
exit;
