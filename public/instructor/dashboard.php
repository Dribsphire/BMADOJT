<?php

/**
 * Instructor Dashboard
 * OJT Route - Instructor dashboard with section overview
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Services\WorkplaceEditRequestService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;

// Start session
session_start();

// Initialize authentication
$authService = new AuthenticationService();
$workplaceEditRequestService = new WorkplaceEditRequestService();
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
    SELECT s.*, COUNT(stu.id) as student_count
    FROM sections s
    LEFT JOIN users stu ON s.id = stu.section_id AND stu.role = 'student'
    WHERE s.id = ?
    GROUP BY s.id
");
$stmt->execute([$user->section_id]);
$section = $stmt->fetch();

if (!$section) {
    $_SESSION['error'] = 'You are not assigned to any section. Please contact the administrator.';
    header('Location: ../login.php');
    exit;
}

// Get enhanced statistics for instructor dashboard
$stats = [];

// Get students in section with their status
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.school_id,
        u.full_name,
        u.email,
        sp.status,
        sp.total_hours_accumulated,
        sp.workplace_name,
        MAX(ar.time_in) as last_activity
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    LEFT JOIN attendance_records ar ON u.id = ar.student_id
    WHERE u.section_id = ? AND u.role = 'student'
    GROUP BY u.id, u.school_id, u.full_name, u.email, sp.status, sp.total_hours_accumulated, sp.workplace_name
    ORDER BY u.full_name
");
$stmt->execute([$user->section_id]);
$students = $stmt->fetchAll();

// Handle workplace edit request actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'approve_workplace_edit') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $hoursDecision = $_POST['hours_decision'] ?? 'keep';
        
        $result = $workplaceEditRequestService->approveRequest($studentId, $user->id, $hoursDecision);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        header('Location: dashboard.php');
        exit;
        
    } elseif ($action === 'deny_workplace_edit') {
        $studentId = (int)($_POST['student_id'] ?? 0);
        
        $result = $workplaceEditRequestService->denyRequest($studentId, $user->id);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        header('Location: dashboard.php');
        exit;
    }
}

// Get pending workplace edit requests
$pendingRequests = $workplaceEditRequestService->getPendingRequestsForInstructor($user->id);
$pendingRequestsCount = count($pendingRequests);

// Count students by status
$onTrackCount = 0;
$needsAttentionCount = 0;
$atRiskCount = 0;
$activeTodayCount = 0;

foreach ($students as $student) {
    switch ($student['status']) {
        case 'on_track':
            $onTrackCount++;
            break;
        case 'needs_attention':
            $needsAttentionCount++;
            break;
        case 'at_risk':
            $atRiskCount++;
            break;
    }
    
    // Check if student was active today
    if ($student['last_activity'] && date('Y-m-d', strtotime($student['last_activity'])) === date('Y-m-d')) {
        $activeTodayCount++;
    }
}

// Count pending documents
$stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_documents
    FROM student_documents sd
    JOIN users u ON sd.student_id = u.id
    WHERE u.section_id = ? AND sd.status = 'pending'
");
$stmt->execute([$user->section_id]);
$pendingDocuments = $stmt->fetchColumn();

// Count pending forgot time-out requests
$stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_requests
    FROM forgot_timeout_requests ftr
    JOIN users u ON ftr.student_id = u.id
    WHERE u.section_id = ? AND ftr.status = 'pending'
");
$stmt->execute([$user->section_id]);
$forgotTimeOutRequests = $stmt->fetchColumn();

// Get students in this section
$stmt = $pdo->prepare("
    SELECT u.*, sp.status, sp.total_hours_accumulated
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE u.section_id = ? AND u.role = 'student'
    ORDER BY u.full_name
");
$stmt->execute([$section['id']]);
$students = $stmt->fetchAll();

// Get section statistics
$stats = [
    'total_students' => count($students),
    'on_track' => 0,
    'needs_attention' => 0,
    'at_risk' => 0,
    'pending_documents' => 0,
    'active_today' => 0
];

foreach ($students as $student) {
    if ($student['status']) {
        $stats[$student['status']]++;
    }
}

// Get pending documents count
$stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_count
    FROM student_documents sd
    JOIN users u ON sd.student_id = u.id
    WHERE u.section_id = ? AND sd.status = 'pending'
");
$stmt->execute([$section['id']]);
$stats['pending_documents'] = $stmt->fetchColumn();

// Get active students today (students with attendance records today)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ar.student_id) as active_count
    FROM attendance_records ar
    JOIN users u ON ar.student_id = u.id
    WHERE u.section_id = ? AND DATE(ar.time_in) = CURDATE()
");
$stmt->execute([$section['id']]);
$stats['active_today'] = $stmt->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instructor Dashboard - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <script type="text/javascript" src="../js/sidebarSlide.js" defer></script>
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
            background: var(--chmsu-green) ;
            color: white;
        }
        
        .stat-card .card-body {
            padding: 1.5rem;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }
        
        .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin: 0;
            color: white;
        }
        
        .btn-primary {
            background: var(--chmsu-green);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: var(--chmsu-green-dark);
        }
        
        .status-badge {
            font-size: 0.75rem;
        }
        
        .progress {
            height: 8px;
            border-radius: 4px;
        }
        
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include 'teacher-sidebar.php'; ?>
    <main>
    <?php include 'navigation-header.php'; ?>
    
    <!-- Main Content -->
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <h2 class="mb-4">
                    <i class="bi bi-speedometer2 me-2"></i>Instructor Dashboard
                </h2>
            </div>
        </div>
        
        <!-- Welcome Message -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card welcome-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-house-door me-2"></i>Welcome back, <?= htmlspecialchars($user->getDisplayName()) ?>!
                        </h5>
                        <p class="card-text">
                            You are managing <strong><?= htmlspecialchars($section['section_name']) ?></strong> 
                            (<?= htmlspecialchars($section['section_code']) ?>) with <?= count($students) ?> students.
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if ($forgotTimeOutRequests > 0): ?>
        <!-- Forgot Timeout Notification -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                        <div class="flex-grow-1">
                            <h6 class="alert-heading mb-1">
                                <i class="bi bi-clock-history me-2"></i>Forgot Time-Out Requests Need Attention
                            </h6>
                            <p class="mb-2">
                                You have <strong><?= $forgotTimeOutRequests ?></strong> pending forgot time-out request(s) that need your review and approval.
                            </p>
                            <a href="forgot_timeout_review.php" class="btn btn-warning btn-sm">
                                <i class="bi bi-clock-history me-1"></i>Review Requests
                            </a>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <?php if ($pendingRequestsCount > 0): ?>
        <!-- Workplace Edit Requests Notification -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-pencil-square fs-4 me-3"></i>
                        <div class="flex-grow-1">
                            <h6 class="alert-heading mb-1">
                                <i class="bi bi-pencil-square me-2"></i>Workplace Edit Requests Need Attention
                            </h6>
                            <p class="mb-2">
                                You have <strong><?= $pendingRequestsCount ?></strong> pending workplace edit request(s) that need your review and approval.
                            </p>
                            <button type="button" class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#workplaceEditRequestsModal">
                                <i class="bi bi-pencil-square me-1"></i>Review Requests
                            </button>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        
        <!-- Students Overview -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="bi bi-people me-2"></i>Students Overview
                        </h5>
                        <a href="student-list.php" class="btn btn-primary btn-sm">
                            <i class="bi bi-arrow-right me-1" style="color: white;"></i>View All Students
                        </a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>School ID</th>
                                        <th>Status</th>
                                        <th>Progress</th>
                                        <th>Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($students, 0, 5) as $student): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($student['full_name']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($student['school_id']) ?></td>
                                        <td>
                                            <?php
                                            $statusClass = match($student['status']) {
                                                'on_track' => 'success',
                                                'needs_attention' => 'warning',
                                                'at_risk' => 'danger',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?= $statusClass ?> status-badge">
                                                <?= ucfirst(str_replace('_', ' ', $student['status'] ?? 'Unknown')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $progress = $student['total_hours_accumulated'] ? ($student['total_hours_accumulated'] / 600) * 100 : 0;
                                            ?>
                                            <div class="progress">
                                                <div class="progress-bar bg-success" style="width: <?= min($progress, 100) ?>%"></div>
                                            </div>
                                            <small class="text-muted"><?= number_format($progress, 1) ?>%</small>
                                        </td>
                                        <td>
                                            <strong><?= number_format($student['total_hours_accumulated'] ?? 0, 1) ?></strong>
                                            <small class="text-muted">/ 600</small>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (count($students) > 5): ?>
                        <div class="text-center mt-3">
                            <a href="student-list.php" class="btn btn-outline-primary">
                                View All <?= count($students) ?> Students
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        <br>
    
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
                    <a href="../logout.php" class="btn btn-danger">
                        <i class="bi bi-box-arrow-right me-1"></i>Yes, Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</main>

    <!-- Workplace Edit Requests Modal -->
    <div class="modal fade" id="workplaceEditRequestsModal" tabindex="-1" aria-labelledby="workplaceEditRequestsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="workplaceEditRequestsModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>Workplace Edit Requests
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (empty($pendingRequests)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No pending workplace edit requests</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Student</th>
                                        <th>Current Workplace</th>
                                        <th>Request Date</th>
                                        <th>Reason</th>
                                        <th>Current Hours</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingRequests as $request): ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($request['student_name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($request['school_id']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($request['workplace_name']) ?></strong><br>
                                                <small class="text-muted"><?= htmlspecialchars($request['supervisor_name']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <?= date('M d, Y g:i A', strtotime($request['workplace_edit_request_date'])) ?>
                                        </td>
                                        <td>
                                            <?= $request['workplace_edit_request_reason'] ? htmlspecialchars($request['workplace_edit_request_reason']) : '<em>No reason provided</em>' ?>
                                        </td>
                                        <td>
                                            <strong><?= number_format($request['total_hours_accumulated'], 1) ?>h</strong>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button type="button" class="btn btn-success btn-sm" 
                                                        onclick="showApproveModal(<?= $request['user_id'] ?>, '<?= htmlspecialchars($request['student_name']) ?>', <?= $request['total_hours_accumulated'] ?>)">
                                                    <i class="bi bi-check-circle me-1"></i>Approve
                                                </button>
                                                <button type="button" class="btn btn-danger btn-sm" 
                                                        onclick="showDenyModal(<?= $request['user_id'] ?>, '<?= htmlspecialchars($request['student_name']) ?>')">
                                                    <i class="bi bi-x-circle me-1"></i>Deny
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
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Approve Request Modal -->
    <div class="modal fade" id="approveRequestModal" tabindex="-1" aria-labelledby="approveRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveRequestModalLabel">
                        <i class="bi bi-check-circle me-2"></i>Approve Workplace Edit Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="approveRequestForm">
                    <input type="hidden" name="action" value="approve_workplace_edit">
                    <input type="hidden" name="student_id" id="approve_student_id">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Student:</strong> <span id="approve_student_name"></span><br>
                            <strong>Current Hours:</strong> <span id="approve_current_hours"></span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Hours Decision</label>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="hours_decision" id="keep_hours" value="keep" checked>
                                <label class="form-check-label" for="keep_hours">
                                    <strong>Keep existing hours</strong> - Student will retain their current accumulated hours
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="hours_decision" id="reset_hours" value="reset">
                                <label class="form-check-label" for="reset_hours">
                                    <strong>Reset to zero</strong> - Student will start with 0 hours in their new workplace
                                </label>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Note:</strong> Once approved, the student will be able to edit their workplace information immediately.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle me-2"></i>Approve Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Deny Request Modal -->
    <div class="modal fade" id="denyRequestModal" tabindex="-1" aria-labelledby="denyRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="denyRequestModalLabel">
                        <i class="bi bi-x-circle me-2"></i>Deny Workplace Edit Request
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="denyRequestForm">
                    <input type="hidden" name="action" value="deny_workplace_edit">
                    <input type="hidden" name="student_id" id="deny_student_id">
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Student:</strong> <span id="deny_student_name"></span>
                        </div>
                        
                        <p>Are you sure you want to deny this workplace edit request? The student will not be able to edit their workplace information.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-x-circle me-2"></i>Deny Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showApproveModal(studentId, studentName, currentHours) {
            document.getElementById('approve_student_id').value = studentId;
            document.getElementById('approve_student_name').textContent = studentName;
            document.getElementById('approve_current_hours').textContent = currentHours + ' hours';
            
            const modal = new bootstrap.Modal(document.getElementById('approveRequestModal'));
            modal.show();
        }
        
        function showDenyModal(studentId, studentName) {
            document.getElementById('deny_student_id').value = studentId;
            document.getElementById('deny_student_name').textContent = studentName;
            
            const modal = new bootstrap.Modal(document.getElementById('denyRequestModal'));
            modal.show();
        }
    </script>

</body>
</html>

