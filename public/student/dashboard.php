<?php

/**
 * Student Dashboard
 * OJT Route - Student dashboard with OJT progress
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;

// Start session
session_start();

// Set timezone to Philippines (UTC+08:00)
date_default_timezone_set('Asia/Manila');

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication and authorization
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('student')) {
    $authMiddleware->redirectToUnauthorized();
}

// Get current user
$user = $authMiddleware->getCurrentUser();

// Get student profile
$pdo = Database::getInstance();

$stmt = $pdo->prepare("
    SELECT sp.*, s.section_name, s.section_code
    FROM student_profiles sp
    LEFT JOIN users u ON sp.user_id = u.id
    LEFT JOIN sections s ON u.section_id = s.id
    WHERE sp.user_id = ?
");
$stmt->execute([$user->id]);
$profile = $stmt->fetch();

// Get student's section info
$stmt = $pdo->prepare("
    SELECT s.*, 
           GROUP_CONCAT(u.full_name SEPARATOR ', ') as instructor_names,
           COUNT(u.id) as instructor_count
    FROM sections s
    LEFT JOIN users u ON s.id = u.section_id AND u.role = 'instructor'
    WHERE s.id = ?
    GROUP BY s.id
");
$stmt->execute([$user->section_id]);
$section = $stmt->fetch();

// Get enhanced statistics for student dashboard
$stats = [];

// Get total OJT hours
$totalHours = $profile['total_hours_accumulated'] ?? 0;
$requiredHours = 600;
$hoursProgress = min(100, ($totalHours / $requiredHours) * 100);

// Get document compliance status
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_documents,
        SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_documents
    FROM student_documents 
    WHERE student_id = ?
");
$stmt->execute([$user->id]);
$documentStats = $stmt->fetch();
$documentProgress = $documentStats['total_documents'] > 0 ? 
    ($documentStats['approved_documents'] / $documentStats['total_documents']) * 100 : 0;

// Get recent attendance records
$stmt = $pdo->prepare("
    SELECT 
        date,
        block_type,
        time_in,
        time_out,
        hours_earned
    FROM attendance_records 
    WHERE student_id = ? 
    ORDER BY time_in DESC 
    LIMIT 10
");
$stmt->execute([$user->id]);
$recentAttendance = $stmt->fetchAll();

// Get last time-in
$lastTimeIn = null;
if (!empty($recentAttendance)) {
    $lastTimeIn = $recentAttendance[0];
}

// Get pending documents
$stmt = $pdo->prepare("
    SELECT COUNT(*) as pending_documents
    FROM student_documents 
    WHERE student_id = ? AND status IN ('pending', 'revision_required')
");
$stmt->execute([$user->id]);
$pendingDocuments = $stmt->fetchColumn();

// Get unread messages (placeholder - will be implemented in Epic 4)
$unreadMessages = 0; // This will be implemented when messaging system is built

// Check for missing time-outs (forgot timeout notifications)
use App\Services\ForgotTimeoutService;
$forgotTimeoutService = new ForgotTimeoutService();
$missingTimeouts = $forgotTimeoutService->getAttendanceRecordsWithoutTimeout($user->id);
$missingTimeoutCount = count($missingTimeouts);

// Get today's attendance
$stmt = $pdo->prepare("
    SELECT * FROM attendance_records 
    WHERE student_id = ? AND date = CURDATE()
    ORDER BY block_type, time_in
");
$stmt->execute([$user->id]);
$todayAttendance = $stmt->fetchAll();

// Get recent attendance (last 7 days)
$stmt = $pdo->prepare("
    SELECT date, block_type, time_in, time_out, hours_earned
    FROM attendance_records 
    WHERE student_id = ? AND date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
    ORDER BY date DESC, block_type
");
$stmt->execute([$user->id]);
$recentAttendance = $stmt->fetchAll();

// Get document status
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total_documents,
           SUM(CASE WHEN sd.status = 'approved' THEN 1 ELSE 0 END) as approved_documents,
           SUM(CASE WHEN sd.status = 'pending' THEN 1 ELSE 0 END) as pending_documents,
           SUM(CASE WHEN sd.status = 'rejected' THEN 1 ELSE 0 END) as rejected_documents
    FROM student_documents sd
    JOIN documents d ON sd.document_id = d.id
    WHERE sd.student_id = ?
    AND d.document_type IN ('moa', 'endorsement', 'parental_consent', 'misdemeanor_penalty', 'ojt_plan', 'notarized_consent', 'pledge')
");
$stmt->execute([$user->id]);
$documentStats = $stmt->fetch();

// Calculate progress
$totalHours = $profile['total_hours_accumulated'] ?? 0;
$progressPercentage = ($totalHours / 600) * 100;
$hoursRemaining = max(0, 600 - $totalHours);

// Determine status
$status = 'Unknown';
$statusClass = 'secondary';
if ($profile) {
    $status = $profile['status'] ?? 'Unknown';
    $statusClass = match($status) {
        'on_track' => 'success',
        'needs_attention' => 'warning',
        'at_risk' => 'danger',
        default => 'secondary'
    };
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - OJT Route</title>
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
            background: var(--chmsu-green) ;
            color: white;
            border-radius: 15px;
        }
        
        .stat-card .card-body {
            padding: 1.5rem;
        }
        
        .stat-number {
            font-size: 1.3rem;
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
        
        .progress {
            height: 12px;
            border-radius: 6px;
        }
        
        .attendance-block {
            border-left: 4px solid var(--chmsu-green);
            padding-left: 1rem;
        }
        
        .attendance-block.morning {
            border-left-color: #ffc107;
        }
        
        .attendance-block.afternoon {
            border-left-color: #17a2b8;
        }
        
        .attendance-block.overtime {
            border-left-color: #6f42c1;
        }
    </style>
</head>
<body>
<?php include 'student-sidebar.php'; ?>
<main>
   <?php include 'nav-bar.php'; ?>
    
    <!-- Main Content -->
    <div class="container-fluid py-4">
        
        <!-- Welcome Message -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card welcome-card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-house-door me-2"></i>Welcome back, <?= htmlspecialchars($user->getDisplayName()) ?>!
                        </h5>
                        <p class="card-text">
                            You are in <strong><?= htmlspecialchars($section['section_name'] ?? 'Not assigned') ?></strong>
                            <?php if ($section): ?>
                                (<?= htmlspecialchars($section['section_code']) ?>)
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Forgot Time-Out Notification -->
        <?php if ($missingTimeoutCount > 0): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                        <div class="flex-grow-1">
                            <h6 class="alert-heading mb-1">
                                <i class="bi bi-clock-history me-2"></i>Missing Time-Outs Detected
                            </h6>
                            <p class="mb-2">
                                You have <strong><?= $missingTimeoutCount ?></strong> attendance record(s) with missing time-outs. 
                                Submit a forgot time-out request to get approval for these hours.
                            </p>
                            <div class="mb-2">
                                <strong>Missing Time-Outs:</strong>
                                <ul class="mb-0 mt-1">
                                    <?php foreach ($missingTimeouts as $timeout): ?>
                                    <li>
                                        <?php 
                                        $timeoutDate = DateTime::createFromFormat('Y-m-d', $timeout['date']);
                                        $timeoutTimeIn = DateTime::createFromFormat('Y-m-d H:i:s', $timeout['time_in']);
                                        echo $timeoutDate ? $timeoutDate->format('M j, Y') : $timeout['date'];
                                        ?> - 
                                        <?= ucfirst($timeout['block_type']) ?> Block
                                        (Time-in: <?= $timeoutTimeIn ? $timeoutTimeIn->format('g:i A') : $timeout['time_in'] ?>)
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                            <a href="forgot_timeout.php" class="btn btn-warning btn-sm">
                                <i class="bi bi-clock-history me-1"></i>Submit Request
                            </a>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Summary Cards -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= number_format($totalHours, 0) ?> / <?= $requiredHours ?></h3>
                        <p class="stat-label">Total OJT Hours</p>
                        <div class="progress mt-2" style="height: 8px;">
                            <div class="progress-bar bg-success" style="width: <?= $hoursProgress ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= $documentStats['approved_documents'] ?> / 7</h3>
                        <p class="stat-label">Document Compliance</p>
                        <div class="progress mt-2" style="height: 8px;">
                            <div class="progress-bar <?= $documentStats['approved_documents'] >= 7 ? 'bg-success' : 'bg-warning' ?>" 
                                 style="width: <?= ($documentStats['approved_documents'] / 7) * 100 ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card stat-card" >
                    <div class="card-body text-center">
                        <h3 class="stat-number">
                            <?php if ($lastTimeIn): ?>
                                <?= date('M j, g:i A', strtotime($lastTimeIn['time_in'])) ?>
                            <?php else: ?>
                                No attendance
                            <?php endif; ?>
                        </h3>
                        <p class="stat-label" style="font-size:14px;">Recent Attendance</p>
                        <?php if ($lastTimeIn): ?>
                            <small class="text-white-50"><?= ucfirst($lastTimeIn['block_type']) ?> block</small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Status -->
        <div class="row mb-4">
           
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-person-check me-2"></i>Status
                        </h5>
                        <div class="d-flex align-items-center">
                            <span class="badge bg-<?= $statusClass ?> me-3" style="font-size: 1rem;">
                                <?= ucfirst(str_replace('_', ' ', $status)) ?>
                            </span>
                            <div>
                                <p class="mb-1">Workplace: <strong><?= htmlspecialchars($profile['workplace_name'] ?? 'Not set') ?></strong></p>
                                <p class="mb-0">Supervisor: <strong><?= htmlspecialchars($profile['supervisor_name'] ?? 'Not set') ?></strong></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Today's Attendance -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-clock me-2"></i>Today's Attendance
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($todayAttendance)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-clock text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No attendance records for today</p>
                            <a href="attendance.php" class="btn btn-primary">
                                <i class="bi bi-plus me-1"></i>Start Attendance
                            </a>
                        </div>
                        <?php else: ?>
                        <div class="row">
                            <?php foreach ($todayAttendance as $attendance): ?>
                            <div class="col-md-4 mb-3">
                                <div class="attendance-block attendance-<?= $attendance['block_type'] ?>">
                                    <h6 class="text-uppercase fw-bold">
                                        <?= ucfirst($attendance['block_type']) ?> Block
                                    </h6>
                                    <p class="mb-1">
                                        <i class="bi bi-arrow-right-circle me-1"></i>
                                        Time In: <strong><?= $attendance['time_in'] ? date('H:i', strtotime($attendance['time_in'])) : 'Not started' ?></strong>
                                    </p>
                                    <p class="mb-1">
                                        <i class="bi bi-arrow-left-circle me-1"></i>
                                        Time Out: <strong><?= $attendance['time_out'] ? date('H:i', strtotime($attendance['time_out'])) : 'Not completed' ?></strong>
                                    </p>
                                    <p class="mb-0">
                                        <i class="bi bi-clock-history me-1"></i>
                                        Hours: <strong><?= number_format($attendance['hours_earned'], 1) ?></strong>
                                    </p>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
    
        </div>
        
        <!-- Recent Attendance -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar-week me-2"></i>Recent Attendance (Last 7 Days)
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recentAttendance)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No recent attendance records</p>
                        </div>
                        <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Block</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentAttendance as $attendance): ?>
                                    <tr>
                                        <td><?= date('M d, Y', strtotime($attendance['date'])) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $attendance['block_type'] === 'morning' ? 'warning' : ($attendance['block_type'] === 'afternoon' ? 'info' : 'info') ?>">
                                                <?= ucfirst($attendance['block_type']) ?>
                                            </span>
                                        </td>
                                        <td><?= $attendance['time_in'] ? date('g:i A', strtotime($attendance['time_in'])) : '-' ?></td>
                                        <td><?= $attendance['time_out'] ? date('g:i A', strtotime($attendance['time_out'])) : '-' ?></td>
                                        <td><strong><?= number_format($attendance['hours_earned'], 1) ?></strong></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <?php endif; ?>
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
                    <a href="../logout.php" class="btn btn-danger">
                        <i class="bi bi-box-arrow-right me-1"></i>Yes, Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
                                    
</main>
</body>
</html>

