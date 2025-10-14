<?php

/**
 * Student Attendance Detail
 * OJT Route - Detailed view of individual student attendance
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Services\SectionAttendanceService;
use App\Utils\Database;

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

if (!$authMiddleware->requireRole('instructor')) {
    $authMiddleware->redirectToUnauthorized();
}

// Get current user
$user = $authMiddleware->getCurrentUser();

// Get student ID
$studentId = $_GET['student_id'] ?? null;
if (!$studentId) {
    $_SESSION['error'] = 'Student ID is required.';
    header('Location: section_attendance.php');
    exit;
}

// Initialize services
$pdo = Database::getInstance();
$attendanceService = new SectionAttendanceService($pdo);

// Verify student belongs to instructor's section
$stmt = $pdo->prepare("
    SELECT u.*, s.section_name, s.section_code, sp.workplace_name
    FROM users u
    INNER JOIN sections s ON u.section_id = s.id
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    WHERE u.id = ? AND s.instructor_id = ? AND u.role = 'student'
");
$stmt->execute([$studentId, $user->id]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    $_SESSION['error'] = 'Student not found or not in your section.';
    header('Location: section_attendance.php');
    exit;
}

// Handle date range filter
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); // First day of current month
$dateTo = $_GET['date_to'] ?? date('Y-m-d'); // Today

$dateRange = [
    'start' => $dateFrom,
    'end' => $dateTo
];

// Get student's attendance history
$attendanceHistory = $attendanceService->getStudentAttendanceHistory($studentId, $dateRange);

// Calculate statistics
$totalHours = array_sum(array_column($attendanceHistory, 'hours_earned'));
$completedRecords = count(array_filter($attendanceHistory, function($record) {
    return $record['time_in'] && $record['time_out'];
}));
$incompleteRecords = count(array_filter($attendanceHistory, function($record) {
    return $record['time_in'] && !$record['time_out'];
}));
$missedRecords = count(array_filter($attendanceHistory, function($record) {
    return !$record['time_in'] && !$record['time_out'];
}));
$totalRecords = count($attendanceHistory);
$completionRate = $totalRecords > 0 ? round(($completedRecords / $totalRecords) * 100, 2) : 0;

// Get pending forgot timeout requests
$stmt = $pdo->prepare("
    SELECT ftr.*, ar.date, ar.block_type
    FROM forgot_timeout_requests ftr
    INNER JOIN attendance_records ar ON ftr.attendance_record_id = ar.id
    WHERE ftr.student_id = ? AND ftr.status = 'pending'
    ORDER BY ftr.created_at DESC
");
$stmt->execute([$studentId]);
$pendingRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Attendance Detail - OJT Route</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .attendance-card {
            transition: transform 0.2s;
        }
        
        .attendance-card:hover {
            transform: translateY(-2px);
        }
        
        .metric-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
        }
        
        .metric-card.success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .metric-card.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .metric-card.info {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .attendance-row {
            transition: background-color 0.2s;
        }
        
        .attendance-row:hover {
            background-color: #f8f9fa;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .photo-thumbnail {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .location-info {
            font-size: 0.8rem;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <?php include 'navigation-header.php'; ?>
    
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block bg-light sidebar">
                <?php include 'teacher-sidebar.php'; ?>
            </div>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <div>
                        <h1 class="h2">
                            <i class="bi bi-person-fill me-2"></i>Student Attendance Detail
                        </h1>
                        <p class="text-muted mb-0">
                            <?php echo htmlspecialchars($student['full_name']); ?> 
                            (<?php echo htmlspecialchars($student['school_id']); ?>) - 
                            <?php echo htmlspecialchars($student['section_name']); ?>
                        </p>
                    </div>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="section_attendance.php" class="btn btn-outline-secondary btn-sm me-2">
                            <i class="bi bi-arrow-left"></i> Back to Overview
                        </a>
                        <a href="messages.php?student_id=<?php echo $studentId; ?>" class="btn btn-primary btn-sm">
                            <i class="bi bi-chat"></i> Send Message
                        </a>
                    </div>
                </div>
                
                <!-- Student Info Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="d-flex align-items-center">
                                    <div class="avatar-lg bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                        <?php 
                                        $nameParts = explode(' ', $student['full_name']);
                                        $initials = '';
                                        if (count($nameParts) >= 2) {
                                            $initials = strtoupper(substr($nameParts[0], 0, 1) . substr($nameParts[1], 0, 1));
                                        } else {
                                            $initials = strtoupper(substr($student['full_name'], 0, 2));
                                        }
                                        echo $initials;
                                        ?>
                                    </div>
                                    <div>
                                        <h4 class="mb-1"><?php echo htmlspecialchars($student['full_name']); ?></h4>
                                        <p class="text-muted mb-1"><?php echo htmlspecialchars($student['school_id']); ?></p>
                                        <p class="text-muted mb-0"><?php echo htmlspecialchars($student['workplace_name']); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h5 class="mb-1 text-primary"><?php echo number_format($totalHours, 2); ?></h5>
                                            <small class="text-muted">Total Hours</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center">
                                            <h5 class="mb-1 text-success"><?php echo $completionRate; ?>%</h5>
                                            <small class="text-muted">Completion Rate</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Summary Metrics -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card metric-card success">
                            <div class="card-body text-center">
                                <h3 class="mb-1"><?php echo $completedRecords; ?></h3>
                                <p class="mb-0">Completed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card metric-card warning">
                            <div class="card-body text-center">
                                <h3 class="mb-1"><?php echo $incompleteRecords; ?></h3>
                                <p class="mb-0">Incomplete</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card metric-card">
                            <div class="card-body text-center">
                                <h3 class="mb-1"><?php echo $missedRecords; ?></h3>
                                <p class="mb-0">Missed</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card metric-card info">
                            <div class="card-body text-center">
                                <h3 class="mb-1"><?php echo $totalRecords; ?></h3>
                                <p class="mb-0">Total Records</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Requests Alert -->
                <?php if (!empty($pendingRequests)): ?>
                    <div class="alert alert-warning alert-dismissible fade show" role="alert">
                        <h6 class="alert-heading">
                            <i class="bi bi-exclamation-triangle me-2"></i>Pending Forgot Time-out Requests
                        </h6>
                        <p class="mb-2">This student has <?php echo count($pendingRequests); ?> pending forgot time-out request(s).</p>
                        <a href="forgot_timeout_review.php?student_id=<?php echo $studentId; ?>" class="btn btn-warning btn-sm">
                            <i class="bi bi-clock-history"></i> Review Requests
                        </a>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="student_id" value="<?php echo $studentId; ?>">
                        <div class="col-md-4">
                            <label for="date_from" class="form-label">Date From</label>
                            <input type="date" class="form-control" id="date_from" name="date_from" 
                                   value="<?php echo $dateFrom; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="date_to" class="form-label">Date To</label>
                            <input type="date" class="form-control" id="date_to" name="date_to" 
                                   value="<?php echo $dateTo; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-search"></i> Filter
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Attendance Records -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-calendar-check me-2"></i>Attendance Records
                            <span class="badge bg-primary ms-2"><?php echo count($attendanceHistory); ?> records</span>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($attendanceHistory)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="bi bi-calendar-x fs-1"></i>
                                <p class="mt-2">No attendance records found for the selected date range.</p>
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
                                            <th>Status</th>
                                            <th>Location</th>
                                            <th>Photo</th>
                                            <th>Request</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendanceHistory as $record): ?>
                                            <tr class="attendance-row">
                                                <td>
                                                    <div>
                                                        <div class="fw-bold"><?php echo date('M j, Y', strtotime($record['date'])); ?></div>
                                                        <small class="text-muted"><?php echo date('l', strtotime($record['date'])); ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <span class="badge bg-secondary"><?php echo ucfirst($record['block_type']); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($record['time_in']): ?>
                                                        <div class="text-success">
                                                            <i class="bi bi-clock-fill me-1"></i>
                                                            <?php echo date('g:i A', strtotime($record['time_in'])); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not recorded</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($record['time_out']): ?>
                                                        <div class="text-success">
                                                            <i class="bi bi-clock-fill me-1"></i>
                                                            <?php echo date('g:i A', strtotime($record['time_out'])); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not recorded</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <span class="fw-bold text-primary">
                                                        <?php echo number_format($record['hours_earned'], 2); ?>h
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = '';
                                                    $statusText = '';
                                                    if ($record['time_in'] && $record['time_out']) {
                                                        $statusClass = 'bg-success';
                                                        $statusText = 'Completed';
                                                    } elseif ($record['time_in'] && !$record['time_out']) {
                                                        $statusClass = 'bg-warning';
                                                        $statusText = 'Incomplete';
                                                    } else {
                                                        $statusClass = 'bg-danger';
                                                        $statusText = 'Missed';
                                                    }
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($record['location_lat_in'] && $record['location_long_in']): ?>
                                                        <div class="location-info">
                                                            <i class="bi bi-geo-alt me-1"></i>
                                                            <?php echo number_format($record['location_lat_in'], 4); ?>, 
                                                            <?php echo number_format($record['location_long_in'], 4); ?>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="text-muted">No location</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($record['photo_path']): ?>
                                                        <img src="../view_attendance_photo.php?id=<?php echo $record['id']; ?>&type=time_in" 
                                                             class="photo-thumbnail" 
                                                             alt="Time-in Photo"
                                                             onclick="showPhotoModal('<?php echo $record['id']; ?>', 'time_in')">
                                                    <?php else: ?>
                                                        <span class="text-muted">No photo</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($record['forgot_timeout_request_id']): ?>
                                                        <span class="badge bg-info"><?php echo ucfirst($record['forgot_timeout_status']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">None</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Photo Modal -->
    <div class="modal fade" id="photoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Attendance Photo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <img id="photoModalImage" src="" class="img-fluid" alt="Attendance Photo">
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function showPhotoModal(recordId, type) {
            const modal = new bootstrap.Modal(document.getElementById('photoModal'));
            const img = document.getElementById('photoModalImage');
            img.src = `../view_attendance_photo.php?id=${recordId}&type=${type}`;
            modal.show();
        }
    </script>
</body>
</html>
