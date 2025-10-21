<?php

/**
 * Admin Dashboard
 * OJT Route - Administrator dashboard
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;
use App\Utils\AdminAccess;

// Start session
session_start();

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication and authorization
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

// Debug: Log session state before access check
error_log("Admin dashboard access - Session state: user_id=" . ($_SESSION['user_id'] ?? 'NOT SET') . 
         ", role=" . ($_SESSION['role'] ?? 'NOT SET') . 
         ", acting_role=" . ($_SESSION['acting_role'] ?? 'NOT SET') . 
         ", original_role=" . ($_SESSION['original_role'] ?? 'NOT SET'));

// Check admin access (including acting as instructor)
AdminAccess::requireAdminAccess();

// Get current user
$user = $authMiddleware->getCurrentUser();

// Get system statistics
$pdo = Database::getInstance();

// Count users by role
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$userStats = $stmt->fetchAll();

// Count active students today
$stmt = $pdo->query("
    SELECT COUNT(DISTINCT ar.student_id) as active_students
    FROM attendance_records ar
    WHERE DATE(ar.time_in) = CURDATE()
");
$activeStudents = $stmt->fetchColumn();

// Count pending documents
$stmt = $pdo->query("
    SELECT COUNT(*) as pending_documents
    FROM student_documents
    WHERE status = 'pending'
");
$pendingDocuments = $stmt->fetchColumn();

// Count sections
$stmt = $pdo->query("SELECT COUNT(*) as total_sections FROM sections");
$totalSections = $stmt->fetchColumn();

// Get detailed statistics for enhanced dashboard
$stats = [];

// Student status breakdown
$stmt = $pdo->query("
    SELECT 
        status,
        COUNT(*) as count
    FROM student_profiles 
    GROUP BY status
");
$studentStatusStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Students by section with instructor info
$stmt = $pdo->query("
    SELECT 
        s.section_code,
        s.section_name,
        u.full_name as instructor_name,
        COUNT(st.id) as total_students,
        SUM(CASE WHEN sp.status = 'on_track' THEN 1 ELSE 0 END) as on_track,
        SUM(CASE WHEN sp.status = 'needs_attention' THEN 1 ELSE 0 END) as needs_attention,
        SUM(CASE WHEN sp.status = 'at_risk' THEN 1 ELSE 0 END) as at_risk,
        AVG(sp.total_hours_accumulated) as avg_hours
    FROM sections s
    LEFT JOIN users u ON s.id = u.section_id AND u.role = 'instructor'
    LEFT JOIN users st ON s.id = st.section_id AND st.role = 'student'
    LEFT JOIN student_profiles sp ON st.id = sp.user_id
    GROUP BY s.id, s.section_code, s.section_name, u.full_name
    ORDER BY s.section_code
");
$sectionsData = $stmt->fetchAll();

// Recent activity log
$stmt = $pdo->query("
    SELECT 
        al.action,
        al.description,
        al.created_at,
        u.full_name,
        u.school_id,
        u.role
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 20
");
$recentActivity = $stmt->fetchAll();

// Calculate percentages
$totalStudents = array_sum($studentStatusStats);
$onTrackCount = $studentStatusStats['on_track'] ?? 0;
$needsAttentionCount = $studentStatusStats['needs_attention'] ?? 0;
$atRiskCount = $studentStatusStats['at_risk'] ?? 0;

$onTrackPercentage = $totalStudents > 0 ? round(($onTrackCount / $totalStudents) * 100) : 0;
$needsAttentionPercentage = $totalStudents > 0 ? round(($needsAttentionCount / $totalStudents) * 100) : 0;
$atRiskPercentage = $totalStudents > 0 ? round(($atRiskCount / $totalStudents) * 100) : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --chmsu-green: #0ea539;
            --chmsu-green-light: #34d399;
            --chmsu-green-dark: #059669;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar {
            background: var(--chmsu-green) !important;
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        
        .card:hover {
            transform: translateY(-2px);
        }
        
        .stat-card {
            background: #0ea539 ;
            color: white;
        }
        
        .stat-card .card-body {
            padding: 1.5rem;
        }
        
        .stat-number {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }
        
        .stat-label {
            color: white;
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
        }
        
        /* Equal card sizing */
        .summary-cards .col-md-2 {
            display: flex;
        }
        
        .summary-cards .card {
            flex: 1;
            min-height: 120px;
            display: flex;
            flex-direction: column;
        }
        
        .summary-cards .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 1rem;
        }
        
        /* Medium font sizes */
        .summary-cards .stat-number {
            font-size: 1.5rem;
            font-weight: 600;
            line-height: 1.2;
        }
        
        .summary-cards .stat-label {
            font-size: 0.85rem;
            font-weight: 500;
            opacity: 0.9;
            margin-top: 0.5rem;
            text-align: center;
            line-height: 1.2;
        }
        
        .welcome-card {
            background: white;
            border-left: 4px solid var(--chmsu-green);
        }
        
        /* Management cards equal height */
        .management-cards .col-md-4 {
            display: flex;
        }
        
        .management-cards .card {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .management-cards .card-body {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .management-cards .card-title {
            margin-bottom: 1rem;
        }
        
        .management-cards .card-text {
            flex: 1;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    <main>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-mortarboard me-2"></i>OJT Route
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?= htmlspecialchars($user->getDisplayName()) ?>
                </span>
                <a class="nav-link me-2" href="profile.php">
                    <i class="bi bi-person me-1"></i>My Profile
                </a>
                <button type="button" class="btn btn-outline-light btn-sm" 
                        data-bs-toggle="modal" data-bs-target="#logoutModal">
                    <i class="bi bi-box-arrow-right me-1"></i>Logout
                </button>
            </div>
        </div>
    </nav>
    
    <!-- Main Content -->
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-speedometer2 me-2"></i>Admin Dashboard
                </h2>
            </div>
        </div>
        
        <!-- Welcome Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card welcome-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-house-door me-2"></i>Welcome back, System Administrator!
                        </h5>
                        <p class="card-text">
                            You are logged in as <strong><?= htmlspecialchars($user->getRoleDisplayName()) ?></strong>. 
                            Monitor and manage the OJT system from this dashboard.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Summary Cards -->
        <div class="row mb-4 summary-cards">
            <div class="col-md-2 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= $userStats[0]['count'] ?? 0 ?></h3>
                        <p class="stat-label">Total Students</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= $userStats[1]['count'] ?? 0 ?></h3>
                        <p class="stat-label">Total Instructors</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= $totalSections ?></h3>
                        <p class="stat-label">Total Sections</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number text-success"><?= $onTrackCount ?> (<?= $onTrackPercentage ?>%)</h3>
                        <p class="stat-label">Students On Track</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number text-warning"><?= $needsAttentionCount ?> (<?= $needsAttentionPercentage ?>%)</h3>
                        <p class="stat-label">Needs Attention</p>
                    </div>
                </div>
            </div>
            <div class="col-md-2 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number text-danger"><?= $atRiskCount ?> (<?= $atRiskPercentage ?>%)</h3>
                        <p class="stat-label">Students At Risk</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <!-- Management Cards -->
        <div class="row management-cards">
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-people me-2"></i>User Management
                        </h5>
                        <p class="card-text">Manage students, instructors, and sections.</p>
                        <a href="users.php" class="btn btn-primary">
                            <i class="bi bi-arrow-right me-1"></i>Manage Users
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-gear me-2"></i>System Settings
                        </h5>
                        <p class="card-text">Configure system-wide settings and preferences.</p>
                        <a href="settings.php" class="btn btn-warning">
                            <i class="bi bi-arrow-right me-1"></i>Settings
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-tools me-2"></i>System Maintenance
                        </h5>
                        <p class="card-text">Monitor system health and perform maintenance tasks.</p>
                        <a href="maintenance.php" class="btn btn-info">
                            <i class="bi bi-arrow-right me-1"></i>Maintenance
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Role Switching -->
        <div class="row">
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-arrow-left-right me-2"></i>Role Switching
                        </h5>
                        <p class="card-text">Switch to instructor mode to access instructor features.</p>
                        <?php if ($user->section_id): ?>
                            <form method="POST" action="switch_role.php" class="d-inline">
                                <input type="hidden" name="action" value="switch_to_instructor">
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-arrow-right me-1"></i>Switch to Instructor Mode
                                </button>
                            </form>
                        <?php else: ?>
                            <p class="text-muted small">You need to be assigned to a section to switch to instructor mode.</p>
                            <a href="users.php" class="btn btn-outline-primary btn-sm">
                                <i class="bi bi-person-plus me-1"></i>Assign Section to Yourself
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-graph-up me-2"></i>System Reports
                        </h5>
                        <p class="card-text">View attendance reports and analytics.</p>
                        <a href="#" class="btn btn-primary">
                            <i class="bi bi-arrow-right me-1"></i>View Reports
                        </a>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Data Tables Section -->
        <div class="row mb-4">
            <!-- Students by Section Table -->
            <div class="col-lg-8 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-table me-2"></i>Students by Section
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover" id="sectionsTable">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Section</th>
                                        <th>Instructor</th>
                                        <th>Total Students</th>
                                        <th>On Track</th>
                                        <th>Needs Attention</th>
                                        <th>At Risk</th>
                                        <th>Avg Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($sectionsData)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center text-muted py-4">
                                                <i class="bi bi-inbox me-2"></i>No sections found
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($sectionsData as $section): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= htmlspecialchars($section['section_code']) ?></strong>
                                                    <?php if ($section['section_name']): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($section['section_name']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($section['instructor_name']): ?>
                                                        <?= htmlspecialchars($section['instructor_name']) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No instructor assigned</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><span class="badge bg-primary"><?= $section['total_students'] ?></span></td>
                                                <td><span class="badge bg-success"><?= $section['on_track'] ?></span></td>
                                                <td><span class="badge bg-warning"><?= $section['needs_attention'] ?></span></td>
                                                <td><span class="badge bg-danger"><?= $section['at_risk'] ?></span></td>
                                                <td><?= number_format($section['avg_hours'] ?? 0, 1) ?>h</td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-clock-history me-2"></i>Recent Activity
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="activity-feed" style="max-height: 400px; overflow-y: auto;">
                            <?php if (empty($recentActivity)): ?>
                                <div class="text-center text-muted py-3">
                                    <i class="bi bi-inbox me-2"></i>No recent activity
                                </div>
                            <?php else: ?>
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="activity-item mb-3">
                                        <div class="d-flex">
                                            <div class="flex-shrink-0">
                                                <i class="bi bi-circle-fill text-primary" style="font-size: 8px;"></i>
                                            </div>
                                            <div class="flex-grow-1 ms-2">
                                                <div class="activity-description">
                                                    <strong><?= htmlspecialchars($activity['full_name'] ?? 'System') ?></strong>
                                                    <?php if ($activity['school_id']): ?>
                                                        <small class="text-muted">(<?= htmlspecialchars($activity['school_id']) ?>)</small>
                                                    <?php endif; ?>
                                                    <br>
                                                    <small><?= htmlspecialchars($activity['description']) ?></small>
                                                </div>
                                                <small class="text-muted">
                                                    <?= date('M j, g:i A', strtotime($activity['created_at'])) ?>
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-lightning me-2"></i>Quick Actions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-2 mb-2">
                                <a href="users.php?action=register" class="btn btn-primary w-100">
                                    <i class="bi bi-person-plus me-1"></i>Add Student
                                </a>
                            </div>
                            <div class="col-md-2 mb-2">
                                <a href="users.php?action=register" class="btn btn-info w-100">
                                    <i class="bi bi-person-plus me-1"></i>Add Instructor
                                </a>
                            </div>
                            <div class="col-md-2 mb-2">
                                <a href="users.php?action=bulk_register" class="btn btn-warning w-100">
                                    <i class="bi bi-upload me-1"></i>Bulk Registration
                                </a>
                            </div>
                            <div class="col-md-2 mb-2">
                                <a href="sections.php" class="btn btn-success w-100">
                                    <i class="bi bi-collection me-1"></i>Manage Sections
                                </a>
                            </div>
                            <div class="col-md-2 mb-2">
                                <a href="#" class="btn btn-secondary w-100">
                                    <i class="bi bi-download me-1"></i>Export Reports
                                </a>
                            </div>
                            <div class="col-md-2 mb-2">
                                <a href="settings.php" class="btn btn-outline-primary w-100">
                                    <i class="bi bi-gear me-1"></i>Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Status -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-info-circle me-2"></i>System Status
                        </h5>
                        <div class="row">
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Database Connected</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>Authentication Active</span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-check-circle text-success me-2"></i>
                                    <span>System Ready</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutModalLabel">
                        <i class="bi bi-box-arrow-right me-2"></i>Confirm Logout
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to logout from OJT Route?</p>
                    <p class="text-muted small">You will need to login again to access the system.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <a href="../logoutadmin.php" class="btn btn-danger">
                        <i class="bi bi-box-arrow-right me-1"></i>Yes, Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
    </main>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/modal-fix.js"></script>
</body>
</html>
