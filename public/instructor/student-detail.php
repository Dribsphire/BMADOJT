<?php

/**
 * Student Detail Page
 * OJT Route - Detailed view of a specific student
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

// Get student ID from URL
$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$student_id) {
    $_SESSION['error'] = 'Student ID is required.';
    header('Location: student-list.php');
    exit;
}

// Get instructor's section information
$pdo = Database::getInstance();

// Verify student belongs to instructor's section
$stmt = $pdo->prepare("
    SELECT u.*, sp.*, s.section_name, s.section_code
    FROM users u
    LEFT JOIN student_profiles sp ON u.id = sp.user_id
    LEFT JOIN sections s ON u.section_id = s.id
    WHERE u.id = ? AND u.section_id = ? AND u.role = 'student'
");
$stmt->execute([$student_id, $user->section_id]);
$student = $stmt->fetch();

if (!$student) {
    $_SESSION['error'] = 'Student not found or not in your section.';
    header('Location: student-list.php');
    exit;
}

// Get student's attendance summary
$attendance_sql = "
    SELECT 
        DATE(date) as attendance_date,
        block_type,
        time_in,
        time_out,
        hours_earned,
        CASE 
            WHEN time_in IS NOT NULL AND time_out IS NOT NULL THEN 'completed'
            WHEN time_in IS NOT NULL AND time_out IS NULL THEN 'in_progress'
            ELSE 'pending'
        END as status
    FROM attendance_records
    WHERE student_id = ?
    ORDER BY date DESC, block_type
    LIMIT 30
";
$stmt = $pdo->prepare($attendance_sql);
$stmt->execute([$student_id]);
$attendance_records = $stmt->fetchAll();

// Get total hours
$total_hours_sql = "
    SELECT 
        COALESCE(SUM(hours_earned), 0) as total_hours,
        COUNT(*) as total_records,
        COUNT(CASE WHEN time_in IS NOT NULL AND time_out IS NOT NULL THEN 1 END) as completed_records,
        COUNT(CASE WHEN time_in IS NOT NULL AND time_out IS NULL THEN 1 END) as in_progress_records
    FROM attendance_records
    WHERE student_id = ?
";
$stmt = $pdo->prepare($total_hours_sql);
$stmt->execute([$student_id]);
$attendance_summary = $stmt->fetch();

// Get student's document submissions
$documents_sql = "
    SELECT 
        sd.*,
        d.document_name,
        d.document_type,
        d.deadline
    FROM student_documents sd
    JOIN documents d ON sd.document_id = d.id
    WHERE sd.student_id = ?
    ORDER BY sd.created_at DESC
    LIMIT 10
";
$stmt = $pdo->prepare($documents_sql);
$stmt->execute([$student_id]);
$document_submissions = $stmt->fetchAll();

// Define status variables for display
$status = $student['status'] ?? 'on_track';
$status_class = 'status-' . $status;
$status_text = ucfirst(str_replace('_', ' ', $status));

// Get recent activity logs
$activity_sql = "
    SELECT 
        al.*,
        u.full_name as user_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.description LIKE ? OR al.user_id = ?
    ORDER BY al.created_at DESC
    LIMIT 10
";
$stmt = $pdo->prepare($activity_sql);
$stmt->execute(["%{$student['full_name']}%", $student_id]);
$activity_logs = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Detail - <?= htmlspecialchars($student['full_name']) ?> - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <script type="text/javascript" src="../js/sidebarSlide.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <!-- Leaflet.js CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
        .status-badge {
            font-size: 0.8rem;
            padding: 0.25rem 0.5rem;
            color: #0ea539;
        }
        .status-on_track { background-color: #d4edda; color: #155724; }
        .status-needs_attention { background-color: #fff3cd; color: #856404; }
        .status-at_risk { background-color: #f8d7da; color: #721c24; }
        .status-completed { background-color: #d4edda; color: #155724; }
        .status-in_progress { background-color: #fff3cd; color: #856404; }
        .status-pending { background-color: #f8d7da; color: #721c24; }
        .profile-header {
            background: var(--chmsu-green)!important;
            color: white;
            border-radius: 0.3rem;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .stats-card {
            background: white;
            border-radius: 0.2rem;
            padding: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-left: 4px solid #0ea539;
        }
        .info-card {
            background: white;
            border-radius: 0.5rem;
            padding: 1rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            height: 100%;
            display: flex;
            flex-direction: column;
            font-size: 0.8rem;
        }
        
        .info-card .row {
            flex-grow: 1;
        }
        
        /* Ensure equal height for side-by-side cards */
        .row .col-md-6 {
            display: flex;
        }
        
        .row .col-md-6 .info-card {
            width: 100%;
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'teacher-sidebar.php'; ?>

    <main>
        <div class="container-fluid py-4">
            <!-- Back Button -->
            <div class="row mb-3">
                <div class="col-12">
                    <a href="student-list.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Student List
                    </a>
                </div>
            </div>

            <div class="profile-header">
                    <h4 class="mb-2"><?= htmlspecialchars($student['full_name']) ?></h4>
                            <i class="bi bi-person-badge me-2" style="color:white;"></i>
                            <?= htmlspecialchars($student['school_id']) ?>
                            
            </div>

            <!-- Statistics Cards -->
            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h3 class="mb-1 text-primary"><?= number_format($attendance_summary['total_hours'], 1) ?></h3>
                                <p class="mb-0 text-muted">Total Hours</p>
                            </div>
                            <i class="bi bi-clock-fill fs-1 text-primary opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h3 class="mb-1 text-success"><?= $attendance_summary['completed_records'] ?></h3>
                                <p class="mb-0 text-muted">Completed Sessions</p>
                            </div>
                            <i class="bi bi-check-circle-fill fs-1 text-success opacity-50"></i>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="d-flex align-items-center">
                            <div class="flex-grow-1">
                                <h3 class="mb-1 text-info"><?= count($document_submissions) ?></h3>
                                <p class="mb-0 text-muted">Documents</p>
                            </div>
                            <i class="bi bi-file-text-fill fs-1 text-info opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Student Information -->
                <div class="col-md-6 mb-4">
                    <div class="info-card">
                        <h5 class="mb-3">
                            <i class="bi bi-person-lines-fill me-2"></i>Student Information
                        </h5>
                        <div class="row">
                            <div class="col-sm-4"><strong>School ID:</strong></div>
                            <div class="col-sm-8"><?= htmlspecialchars($student['school_id']) ?></div>
                            
                            <div class="col-sm-4"><strong>Full Name:</strong></div>
                            <div class="col-sm-8"><?= htmlspecialchars($student['full_name']) ?></div>
                            
                            <div class="col-sm-4"><strong>Email:</strong></div>
                            <div class="col-sm-8"><?= htmlspecialchars($student['email']) ?></div>
                            
                            <div class="col-sm-4"><strong>Contact:</strong></div>
                            <div class="col-sm-8"><?= htmlspecialchars($student['contact'] ?? 'Not provided') ?></div>
                            
                            <div class="col-sm-4"><strong>Section:</strong></div>
                            <div class="col-sm-8"><?= htmlspecialchars($student['section_name']) ?> (<?= htmlspecialchars($student['section_code']) ?>)</div>
                        </div>
                    </div>
                </div>

                <!-- Workplace Information -->
                <div class="col-md-6 mb-4">
                    <div class="info-card">
                        <h5 class="mb-3">
                            <i class="bi bi-building me-2"></i>Workplace Information
                        </h5>
                        <div class="row">
                            <div class="col-sm-4"><strong>Company:</strong></div>
                            <div class="col-sm-8"><?= htmlspecialchars($student['workplace_name'] ?? 'Not specified') ?></div>
                            
                            <div class="col-sm-4"><strong>Supervisor:</strong></div>
                            <div class="col-sm-8"><?= htmlspecialchars($student['supervisor_name'] ?? 'Not specified') ?></div>
                            
                            <div class="col-sm-4"><strong>Position:</strong></div>
                            <div class="col-sm-8"><?= htmlspecialchars($student['student_position'] ?? 'Not specified') ?></div>
                            
                            <div class="col-sm-4"><strong>Start Date:</strong></div>
                            <div class="col-sm-8"><?= $student['ojt_start_date'] ? date('M j, Y', strtotime($student['ojt_start_date'])) : 'Not specified' ?></div>
                            
                            <div class="col-sm-4"><strong>Status:</strong></div>
                            <div class="col-sm-8">
                                <span class="badge status-badge <?= $status_class ?>" style="color: #0ea539;">
                                    <?= $status_text ?>
                                </span>
                            </div>
                        </div>
                        
                        <?php if (!empty($student['workplace_latitude']) && !empty($student['workplace_longitude'])): ?>
                        <!-- Workplace Location Map -->
                        <div class="row mt-3">
                            <div class="col-12">
                                <h6 class="mb-2">
                                    <i class="bi bi-geo-alt me-2"></i>Workplace Location
                                </h6>
                                <div id="workplace-map" style="height: 150px; width: 100%; border-radius: 8px; border: 1px solid #dee2e6;"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Attendance -->
            <div class="row mb-4" id="attendance">
                <div class="col-12">
                    <div class="info-card">
                        <h5 class="mb-3">
                            <i class="bi bi-calendar-check me-2"></i>Recent Attendance Records
                        </h5>
                        <?php if (empty($attendance_records)): ?>
                            <p class="text-muted">No attendance records found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>Block</th>
                                            <th>Time In</th>
                                            <th>Time Out</th>
                                            <th>Hours</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($attendance_records as $record): ?>
                                            <tr>
                                                <td><?= date('M j, Y', strtotime($record['attendance_date'])) ?></td>
                                                <td><?= ucfirst($record['block_type']) ?></td>
                                                <td><?= $record['time_in'] ? date('g:i A', strtotime($record['time_in'])) : '-' ?></td>
                                                <td><?= $record['time_out'] ? date('g:i A', strtotime($record['time_out'])) : '-' ?></td>
                                                <td><?= number_format($record['hours_earned'], 1) ?></td>
                                                <td>
                                                    <span class="badge status-badge status-<?= $record['status'] ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $record['status'])) ?>
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Document Submissions -->
            <div class="row mb-4" id="documents">
                <div class="col-12">
                    <div class="info-card">
                        <h5 class="mb-3">
                            <i class="bi bi-file-text me-2"></i>Recent Document Submissions
                        </h5>
                        <?php if (empty($document_submissions)): ?>
                            <p class="text-muted">No document submissions found.</p>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Document</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Submitted</th>
                                            <th>Deadline</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($document_submissions as $doc): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($doc['document_name']) ?></td>
                                                <td><?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?></td>
                                                <td>
                                                    <span class="badge status-badge status-<?= $doc['status'] ?>">
                                                        <?= ucfirst(str_replace('_', ' ', $doc['status'])) ?>
                                                    </span>
                                                </td>
                                                <td><?= date('M j, Y', strtotime($doc['created_at'])) ?></td>
                                                <td><?= $doc['deadline'] ? date('M j, Y', strtotime($doc['deadline'])) : 'No deadline' ?></td>
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
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewFullAttendance(studentId) {
            // TODO: Implement full attendance view
            alert('View full attendance for student ID: ' + studentId);
        }

        function viewAllDocuments(studentId) {
            // TODO: Implement all documents view
            alert('View all documents for student ID: ' + studentId);
        }

        function exportStudentData(studentId) {
            // TODO: Implement student data export
            alert('Export data for student ID: ' + studentId);
        }
    </script>
    
    <!-- Leaflet.js JavaScript -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
    <script>
    // Initialize workplace map if coordinates are available
    <?php if (!empty($student['workplace_latitude']) && !empty($student['workplace_longitude'])): ?>
    document.addEventListener('DOMContentLoaded', function() {
        const latitude = <?= $student['workplace_latitude'] ?>;
        const longitude = <?= $student['workplace_longitude'] ?>;
        const workplaceName = '<?= addslashes($student['workplace_name'] ?? 'Workplace') ?>';
        
        // Initialize the map
        const map = L.map('workplace-map').setView([latitude, longitude], 15);
        
        // Add OpenStreetMap tiles
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors'
        }).addTo(map);
        
        // Add a marker for the workplace
        const marker = L.marker([latitude, longitude]).addTo(map);
        marker.bindPopup(`
            <div style="text-align: center;">
                <strong>${workplaceName}</strong><br>
                <small>Student's Workplace</small>
            </div>
        `).openPopup();
        
        // Add a circle to show the area
        L.circle([latitude, longitude], {
            color: '#0ea539',
            fillColor: '#0ea539',
            fillOpacity: 0.1,
            radius: 100
        }).addTo(map);
    });
    <?php endif; ?>
    </script>
</body>
</html>
