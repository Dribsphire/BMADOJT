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

// Get attendance by section
$stmt = $pdo->prepare("
    SELECT 
        s.id,
        s.section_name,
        s.section_code,
        u.full_name as instructor_name,
        COUNT(DISTINCT ar.student_id) as students_with_attendance,
        COUNT(ar.id) as total_records,
        COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as completed_records,
        ROUND(COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) * 100.0 / COUNT(ar.id), 2) as completion_rate,
        SUM(ar.hours_earned) as total_hours
    FROM sections s
    LEFT JOIN users u ON s.instructor_id = u.id
    LEFT JOIN users stu ON stu.section_id = s.id AND stu.role = 'student'
    LEFT JOIN attendance_records ar ON stu.id = ar.student_id AND {$whereClause}
    GROUP BY s.id, s.section_name, s.section_code, u.full_name
    ORDER BY total_hours DESC
");
$stmt->execute($params);
$sectionStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get attendance trends (last 7 days)
$stmt = $pdo->prepare("
    SELECT 
        DATE(ar.date) as attendance_date,
        COUNT(DISTINCT ar.student_id) as students_attended,
        COUNT(ar.id) as total_records,
        COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as completed_records,
        SUM(ar.hours_earned) as total_hours
    FROM attendance_records ar
    INNER JOIN users u ON ar.student_id = u.id
    LEFT JOIN sections s ON u.section_id = s.id
    WHERE {$whereClause}
    GROUP BY DATE(ar.date)
    ORDER BY attendance_date DESC
    LIMIT 7
");
$stmt->execute($params);
$trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        .stats-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .stats-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .stats-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
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
                    <button type="button" class="btn btn-outline-success" onclick="exportToPDF()">
                        <i class="bi bi-file-pdf me-1"></i>Export PDF
                    </button>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card filter-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="date_from" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="date_from" name="date_from" 
                               value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="date_to" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="date_to" name="date_to" 
                               value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                    <div class="col-md-3">
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
                    <div class="col-md-3">
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
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-search me-1"></i>Apply Filters
                        </button>
                        <a href="attendance_reports.php" class="btn btn-outline-secondary ms-2">
                            <i class="bi bi-x-circle me-1"></i>Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?= $overallStats['total_students'] ?></h3>
                        <p class="card-text">Total Students</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card success">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?= $overallStats['completed_attendance'] ?></h3>
                        <p class="card-text">Completed Records</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card warning">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?= $overallStats['incomplete_attendance'] ?></h3>
                        <p class="card-text">Incomplete Records</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card info">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?= number_format($overallStats['total_hours_earned'], 1) ?></h3>
                        <p class="card-text">Total Hours</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Attendance Trends</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="trendsChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Section Performance</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="sectionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Statistics Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="bi bi-table me-2"></i>Section Performance
                </h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th>Instructor</th>
                                <th>Students</th>
                                <th>Total Records</th>
                                <th>Completed</th>
                                <th>Completion Rate</th>
                                <th>Total Hours</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sectionStats as $section): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($section['section_name']) ?></strong>
                                            <br><small class="text-muted"><?= htmlspecialchars($section['section_code']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($section['instructor_name'] ?? 'Unassigned') ?></td>
                                    <td>
                                        <span class="badge bg-primary"><?= $section['students_with_attendance'] ?></span>
                                    </td>
                                    <td><?= $section['total_records'] ?></td>
                                    <td>
                                        <span class="badge bg-success"><?= $section['completed_records'] ?></span>
                                    </td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar 
                                                <?php 
                                                    if ($section['completion_rate'] >= 80) echo 'bg-success';
                                                    else if ($section['completion_rate'] >= 60) echo 'bg-warning';
                                                    else echo 'bg-danger';
                                                ?>" 
                                                role="progressbar" 
                                                style="width: <?= $section['completion_rate'] ?>%;" 
                                                aria-valuenow="<?= $section['completion_rate'] ?>" 
                                                aria-valuemin="0" 
                                                aria-valuemax="100">
                                                <?= $section['completion_rate'] ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-primary"><?= number_format($section['total_hours'], 1) ?>h</span>
                                    </td>
                                    <td>
                                        <a href="attendance_export.php?section_id=<?= $section['id'] ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-download"></i> Export
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Trends Chart
        const trendsData = <?php echo json_encode($trends); ?>;
        const trendsCtx = document.getElementById('trendsChart').getContext('2d');
        
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: trendsData.map(t => t.attendance_date).reverse(),
                datasets: [{
                    label: 'Students Attended',
                    data: trendsData.map(t => t.students_attended).reverse(),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }, {
                    label: 'Completed Records',
                    data: trendsData.map(t => t.completed_records).reverse(),
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Section Performance Chart
        const sectionData = <?php echo json_encode($sectionStats); ?>;
        const sectionCtx = document.getElementById('sectionChart').getContext('2d');
        
        new Chart(sectionCtx, {
            type: 'bar',
            data: {
                labels: sectionData.map(s => s.section_name),
                datasets: [{
                    label: 'Completion Rate (%)',
                    data: sectionData.map(s => s.completion_rate),
                    backgroundColor: 'rgba(54, 162, 235, 0.8)',
                    borderColor: 'rgba(54, 162, 235, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
        
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
