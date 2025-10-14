<?php

/**
 * Student List Page
 * OJT Route - Instructor view of all students in their section
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

// Handle CSV export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Location: export-students.php?' . http_build_query($_GET));
    exit;
}

// Get instructor's section information
$pdo = Database::getInstance();

// Get section details
$stmt = $pdo->prepare("
    SELECT s.*, COUNT(stu.id) as student_count
    FROM sections s
    LEFT JOIN users stu ON s.id = stu.section_id AND stu.role = 'student'
    WHERE s.id = ?
    GROUP BY s.id
");
$stmt->execute([$user->section_id]);
$section = $stmt->fetch(PDO::FETCH_OBJ);

if (!$section) {
    $_SESSION['error'] = 'You are not assigned to any section. Please contact the administrator.';
    header('Location: ../login.php');
    exit;
}

// Pagination and filtering parameters
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10; // Students per page
$offset = ($page - 1) * $limit;

// Search and filter parameters
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

// Build the query
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

// Get total count for pagination
$count_sql = "
    SELECT COUNT(DISTINCT u.id)
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE {$where_clause}
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_students = $stmt->fetchColumn();
$total_pages = ceil($total_students / $limit);

// Get students with pagination
$students_sql = "
    SELECT 
        u.id,
        u.school_id,
        u.full_name,
        u.email,
        sp.workplace_name,
        sp.supervisor_name,
        sp.student_position,
        sp.ojt_start_date,
        sp.status,
        COALESCE(SUM(ar.hours_earned), 0) as total_accumulated_hours,
        COUNT(ar.id) as attendance_records_count,
        MAX(ar.date) as last_attendance_date
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    LEFT JOIN attendance_records ar ON u.id = ar.student_id
    WHERE {$where_clause}
    GROUP BY u.id, u.school_id, u.full_name, u.email, sp.workplace_name, 
             sp.supervisor_name, sp.student_position, sp.ojt_start_date, sp.status
    ORDER BY {$sort_by} {$sort_order}
    LIMIT {$limit} OFFSET {$offset}
";

$stmt = $pdo->prepare($students_sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// Get status counts for filter
$status_counts_sql = "
    SELECT 
        sp.status,
        COUNT(DISTINCT u.id) as count
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE u.section_id = ? AND u.role = 'student'
    GROUP BY sp.status
";
$stmt = $pdo->prepare($status_counts_sql);
$stmt->execute([$user->section_id]);
$status_counts = $stmt->fetchAll();

// Calculate section statistics
$stats_sql = "
    SELECT 
        u.id,
        COALESCE(SUM(ar.hours_earned), 0) as total_hours
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    LEFT JOIN attendance_records ar ON u.id = ar.student_id
    WHERE u.section_id = ? AND u.role = 'student'
    GROUP BY u.id
";
$stmt = $pdo->prepare($stats_sql);
$stmt->execute([$user->section_id]);
$section_stats = $stmt->fetchAll();

$total_students_count = count($section_stats);
$total_hours_array = array_column($section_stats, 'total_hours');
$avg_hours = $total_students_count > 0 ? array_sum($total_hours_array) / $total_students_count : 0;
$max_hours = $total_students_count > 0 ? max($total_hours_array) : 0;
$min_hours = $total_students_count > 0 ? min($total_hours_array) : 0;

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student List - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <script type="text/javascript" src="../js/sidebarSlide.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --chmsu-green: #0ea539;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            
        }
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
        }
        .status-on_track { background-color: #d4edda; color: #155724; }
        .status-needs_attention { background-color: #fff3cd; color: #856404; }
        .status-at_risk { background-color: #f8d7da; color: #721c24; }
        .hours-badge {
            font-size: 0.9rem;
            font-weight: 600;
        }
        .search-container {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            margin-bottom: 2rem;
            font-size: 1.2rem;
        }
        .stats-card {
            background: var(--chmsu-green) ;
            color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        .table-responsive {
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        .pagination-container {
            background-color: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
        }
        .btn{
            background-color: var(--chmsu-green);
            color: white;
        }
        .btn:hover{
            background-color: #0d8a2f;
            color: white;
        }
    </style>
</head>
<body class="bg-light">

    <?php include 'teacher-sidebar.php'; ?>

 <main>
    <?php include 'navigation-header.php'; ?>
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-1">Student List</h2>
                            <p class="text-muted mb-0">Section: <?= htmlspecialchars($section->section_name) ?> (<?= htmlspecialchars($section->section_code) ?>)</p>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-success" onclick="exportToCSV()">
                                <i class="bi bi-download me-1" style="color: white;"></i>Export CSV
                            </button>
                            <button class="btn btn-success" onclick="refreshPage()">
                                <i class="bi bi-arrow-clockwise me-1" style="color: white;"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter Section -->
            <div class="search-container" style="font-size: 1rem;">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label for="search" class="form-label" >Search Students</label>
                        <input type="text" class="form-control" id="search" name="search" 
                               value="<?= htmlspecialchars($search) ?>" style="font-size: 13px;"
                               placeholder="Search by school ID, name, or workplace...">
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label" >Status</label>
                        <select class="form-select" id="status" name="status" style="font-size: 13px;">
                            <option value="">All Status</option>
                            <?php foreach ($status_counts as $status): ?>
                                <option value="<?= $status['status'] ?>" 
                                        <?= $status_filter === $status['status'] ? 'selected' : '' ?>>
                                    <?= ucfirst(str_replace('_', ' ', $status['status'])) ?> (<?= $status['count'] ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="sort" class="form-label" >Sort By</label>
                        <select class="form-select" id="sort" name="sort" style="font-size: 13px;">
                            <option value="full_name" <?= $sort_by === 'full_name' ? 'selected' : '' ?>>Name</option>
                            <option value="school_id" <?= $sort_by === 'school_id' ? 'selected' : '' ?>>School ID</option>
                            <option value="workplace_name" <?= $sort_by === 'workplace_name' ? 'selected' : '' ?>>Workplace</option>
                            <option value="total_hours" <?= $sort_by === 'total_hours' ? 'selected' : '' ?>>Total Hours</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="order" class="form-label" style="font-size: 13px;">Order</label>
                        <select class="form-select" id="order" name="order" style="font-size: 13px;">
                            <option value="ASC" <?= $sort_order === 'ASC' ? 'selected' : '' ?>>ASC</option>
                            <option value="DESC" <?= $sort_order === 'DESC' ? 'selected' : '' ?>>DESC</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="bi bi-search me-1" style="color: white; font-size: 12px;"></i>
                        </button>
                    </div>
                    <div class="col-md-1" >
                        <a href="student-list.php" class="btn btn-outline-secondary w-100">
                            <i class="bi bi-x-circle me-1" style="color: white; font-size: 12px;" ></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Students Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-list-ul me-2" style="color: var(--chmsu-green);"></i>Students
                        <span class="badge bg-success ms-2" style="font-size: 13px;"><?= $total_students ?> total</span>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($students)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-people fs-1 text-muted" style="color: white;"></i>
                            <h5 class="mt-3">No students found</h5>
                            <p class="text-muted">No students match your current search criteria.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>School ID</th>
                                        <th>Full Name</th>
                                        <th>Workplace</th>
                                        <th>Position</th>
                                        <th>Status</th>
                                        <th>Total Hours</th>
                                        <th>Last Attendance</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): ?>
                                        <tr>
                                            <td>
                                                <span class="fw-bold" style="color: var(--chmsu-green);"><?= htmlspecialchars($student['school_id']) ?></span>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="fw-bold"><?= htmlspecialchars($student['full_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($student['email']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div><?= htmlspecialchars($student['workplace_name']) ?></div>
                                                    <small class="text-muted"><?= htmlspecialchars($student['supervisor_name']) ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="text-muted"><?= htmlspecialchars($student['student_position']) ?></span>
                                            </td>
                                            <td>
                                                <?php
                                                $status_class = 'status-' . ($student['status'] ?? 'on_track');
                                                $status_text = ucfirst(str_replace('_', ' ', $student['status'] ?? 'on_track'));
                                                ?>
                                                <span class="badge status-badge <?= $status_class ?>">
                                                    <?= $status_text ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-success hours-badge">
                                                    <?= number_format($student['total_accumulated_hours'], 1) ?> hrs
                                                </span>
                                            </td>
                                            <td>
                                                <?php if ($student['last_attendance_date']): ?>
                                                    <span class="text-muted">
                                                        <?= date('M j, Y', strtotime($student['last_attendance_date'])) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">No attendance</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" 
                                                            onclick="viewStudent(<?= $student['id'] ?>)"
                                                            title="View Details">
                                                        <i class="bi bi-eye" style="color: white;"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container mt-4">
                    <nav aria-label="Student list pagination">
                        <ul class="pagination justify-content-center mb-0">
                            <!-- Previous Page -->
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">
                                        <i class="bi bi-chevron-left"></i> Previous
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php
                            $start_page = max(1, $page - 2);
                            $end_page = min($total_pages, $page + 2);
                            ?>

                            <?php if ($start_page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => 1])) ?>">1</a>
                                </li>
                                <?php if ($start_page > 2): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <?php if ($end_page < $total_pages): ?>
                                <?php if ($end_page < $total_pages - 1): ?>
                                    <li class="page-item disabled"><span class="page-link">...</span></li>
                                <?php endif; ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $total_pages])) ?>">
                                        <?= $total_pages ?>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- Next Page -->
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">
                                        Next <i class="bi bi-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewStudent(studentId) {
            window.location.href = 'student-detail.php?id=' + studentId;
        }

        function viewAttendance(studentId) {
            // Redirect to student detail page with attendance focus
            window.location.href = 'student-detail.php?id=' + studentId + '#attendance';
        }

        function viewDocuments(studentId) {
            // Redirect to student detail page with documents focus
            window.location.href = 'student-detail.php?id=' + studentId + '#documents';
        }

        function exportToCSV() {
            // Get current search parameters
            const params = new URLSearchParams(window.location.search);
            params.set('export', 'csv');
            
            // Create download link
            const link = document.createElement('a');
            link.href = 'student-list.php?' + params.toString();
            link.download = 'students_export.csv';
            link.click();
        }

        function refreshPage() {
            window.location.reload();
        }

        // Auto-submit form on filter change
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('sort').addEventListener('change', function() {
            this.form.submit();
        });

        document.getElementById('order').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
    </main>
</body>
</html>
