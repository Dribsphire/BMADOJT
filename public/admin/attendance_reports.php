<?php

/**
 * Admin Attendance Reports
 * OJT Route - Administrator attendance reports and analytics
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

// Get current user
$user = $authMiddleware->getCurrentUser();

// Initialize database
$pdo = Database::getInstance();

// Handle date range filters
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Today
$sectionFilter = $_GET['section_id'] ?? '';
$instructorFilter = $_GET['instructor_id'] ?? '';

// Get system-wide attendance statistics
$whereConditions = ["ar.time_in IS NOT NULL"];
$params = [];

if ($dateFrom && $dateTo) {
    $whereConditions[] = "DATE(ar.date) BETWEEN ? AND ?";
    $params[] = $dateFrom;
    $params[] = $dateTo;
}

if ($sectionFilter) {
    $whereConditions[] = "u.section_id = ?";
    $params[] = $sectionFilter;
}

if ($instructorFilter) {
    $whereConditions[] = "s.instructor_id = ?";
    $params[] = $instructorFilter;
}

$whereClause = implode(' AND ', $whereConditions);

// Get overall statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT ar.student_id) as total_students,
        COUNT(ar.id) as total_attendance_records,
        COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as completed_attendance,
        COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NULL THEN 1 END) as incomplete_attendance,
        ROUND(AVG(ar.hours_earned), 2) as avg_hours_per_record,
        SUM(ar.hours_earned) as total_hours_earned
    FROM attendance_records ar
    INNER JOIN users u ON ar.student_id = u.id
    LEFT JOIN sections s ON u.section_id = s.id
    WHERE {$whereClause}
");
$stmt->execute($params);
$overallStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get overall student reports
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.school_id,
        u.full_name,
        u.email,
        s.section_name,
        s.section_code,
        COUNT(ar.id) as total_records,
        COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as completed_records,
        COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NULL THEN 1 END) as incomplete_records,
        ROUND(COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) * 100.0 / COUNT(ar.id), 2) as completion_rate,
        SUM(ar.hours_earned) as total_hours,
        ROUND(AVG(ar.hours_earned), 2) as avg_hours_per_record,
        MIN(ar.date) as first_attendance,
        MAX(ar.date) as last_attendance
    FROM users u
    LEFT JOIN sections s ON u.section_id = s.id
    LEFT JOIN attendance_records ar ON u.id = ar.student_id AND {$whereClause}
    WHERE u.role = 'student'
    " . ($sectionFilter ? "AND u.section_id = ?" : "") . "
    GROUP BY u.id, u.school_id, u.full_name, u.email, s.section_name, s.section_code
    ORDER BY total_hours DESC, completion_rate DESC
");

if ($sectionFilter) {
    $stmt->execute(array_merge($params, [$sectionFilter]));
} else {
$stmt->execute($params);
}
$studentReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get sections for filter dropdown
$stmt = $pdo->query("
    SELECT s.id, s.section_name, s.section_code, u.full_name as instructor_name
    FROM sections s
    LEFT JOIN users u ON s.instructor_id = u.id
    ORDER BY s.section_name
");
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get instructors for filter dropdown
$stmt = $pdo->query("
    SELECT id, full_name, school_id
    FROM users 
    WHERE role = 'instructor'
    ORDER BY full_name
");
$instructors = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Reports - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <script type="text/javascript" src="../js/sidebarSlide.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .stats-card {
            background:#0ea539;
            color: white;
            border: none;
        }
        .stats-card.success {
            background:#0ea539 ;
        }
        .stats-card.warning {
            background:#0ea539;
        }
        .stats-card.info {
            background:#0ea539;
        }
        .chart-container {
            position: relative;
            height: 300px;
        }
        .filter-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">
                <i class="bi bi-graph-up me-2"></i>Attendance Reports
            </h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-outline-primary" onclick="exportToCSV()">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </button>
                </div>
            </div>
        </div>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?= $overallStats['total_students'] ?></h3>
                        <p class="card-text" style="color: white;">Total Students</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card success">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?= $overallStats['completed_attendance'] ?></h3>
                        <p class="card-text" style="color: white;">Completed Records</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card warning">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?= $overallStats['incomplete_attendance'] ?></h3>
                        <p class="card-text" style="color: white;">Incomplete Records</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card info">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?= number_format($overallStats['total_hours_earned'], 1) ?></h3>
                        <p class="card-text" style="color: white;">Total Hours</p>
                    </div>
                </div>
            </div>
        </div>
        <!-- Filters -->
        <div class="card filter-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-2">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    <div class="col-md-2">
                        <label for="section_id" class="form-label">Section</label>
                        <select class="form-select" id="section_id" name="section_id">
                            <option value="">All Sections</option>
                            <?php foreach ($sections as $section): ?>
                                <option value="<?= $section['id'] ?>" 
                                        <?= $sectionFilter == $section['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($section['section_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="instructor_id" class="form-label">Instructor</label>
                        <select class="form-select" id="instructor_id" name="instructor_id">
                            <option value="">All Instructors</option>
                            <?php foreach ($instructors as $instructor): ?>
                                <option value="<?= $instructor['id'] ?>" 
                                        <?= $instructorFilter == $instructor['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($instructor['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1"></i>Apply
                        </button>
                    </div>
                    <div class="col-md-2">
                        <a href="attendance_reports.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <!-- Attendance Reports -->
        <div id="attendance-tab">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-people me-2"></i>Student Attendance Reports
                    </h5>
                </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Section</th>
                                <th>Records</th>
                                <th>Completed</th>
                                <th>Total Hours</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentReports as $student): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($student['full_name']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($student['school_id']) ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?= htmlspecialchars($student['section_code'] ?? 'No Section') ?></span>
                                    </td>
                                    <td><?= $student['total_records'] ?></td>
                                    <td>
                                        <span class="badge bg-success"><?= $student['completed_records'] ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-primary"><?= number_format($student['total_hours'], 1) ?>h</span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        
        <script>
            
            // Export functions
            function exportToCSV() {
                const params = new URLSearchParams(window.location.search);
                params.set('format', 'csv');
                window.location.href = 'attendance_export.php?' + params.toString();
            }
            
            function exportToPDF() {
                const params = new URLSearchParams(window.location.search);
                params.set('format', 'pdf');
                window.location.href = 'attendance_export.php?' + params.toString();
            }
        </script>
    </body>
</html>
