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
    <link rel="icon" type="image/png" href="../images/CHMSU.png">
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
        
        /* Top Banner Section */
        .top-banner {
            background: #0ea539;
            color: white;
            padding: 2rem 0;
            position: relative;
            overflow: hidden;
            border-radius: 10px;
        }

        
        .welcome-message {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: white;
            margin-left: 1rem;
        }
        
        .student-info {
            display: flex;
            gap: 1rem;
            margin-left: 1rem;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
         .info-item i {
             font-size: 1.2rem;
         }
         
         .chmsu-logo {
             height: 500px;
             width: auto;
         }
         
         .logo-info {
             align-items: center;
             width: 100px;
             height: 100px;
             object-fit: contain;
             display: flex;
             justify-content: center;
             align-items: center;
             margin-right: 1rem;
             margin-left: 1rem;
         }
        
        /* Main Content Layout */
        .main-content {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        
        /* OJT Progress Section */
        .ojt-progress-section {
            margin-bottom: 2rem;
        }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }
        
         .single-progress-card {
             padding: 1rem;
             transition: transform 0.2s;
         }
         
         
         .progress-header {
             display: flex;
             align-items: center;
             gap: 1rem;
             margin-bottom: 1.5rem;
         }
         
         .progress-icon {
             font-size: 2rem;
             color: var(--chmsu-green);
         }
         
         .progress-title {
             font-size: 1.2rem;
             font-weight: 600;
             color: #333;
         }
        
        .card-icon {
            font-size: 2rem;
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .card-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--chmsu-green);
            margin-bottom: 0.5rem;
        }
        
        .card-label {
            font-size: 0.9rem;
            color: #666;
            margin: 0;
        }
        
        /* Progress Bar Styles */
         .progress-bar-container {
             width: 100%;
             height: 12px;
             background-color: #e9ecef;
             border-radius: 6px;
             overflow: hidden;
             position: relative;
         }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--chmsu-green) 0%, var(--chmsu-green-light) 100%);
            border-radius: 4px;
            transition: width 1.5s ease-in-out;
            position: relative;
            overflow: hidden;
        }
        
        .progress-bar-fill::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.3) 50%, transparent 100%);
            animation: progressShimmer 2s infinite;
        }
        
        @keyframes progressShimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .progress-text {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 0.5rem;
            font-size: 0.8rem;
            color: #666;
        }
        
        .progress-percentage {
            font-weight: 600;
            color: var(--chmsu-green);
        }
        
        /* Recent Attendance Section */
        .attendance-section {
            margin-bottom: 2rem;
        }
        
        /* Attendance Table Styling */
        .attendance-table {
            background: white;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
        }
        
        .attendance-table thead th {
            background: #0ea539;
            color: white;
            font-weight: 600;
            padding: 1rem 0.75rem;
            font-size: 0.9rem;
            border: none;
        }
        
        .attendance-table tbody tr {
            border-bottom: 1px solid #f0f0f0;
            transition: all 0.3s ease;
        }
        
        .attendance-table tbody tr:hover {
            background-color: #f8f9fa;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .attendance-table tbody td {
            padding: 1rem 0.75rem;
            border: none;
            vertical-align: middle;
        }
        
        .attendance-table tbody tr:last-child {
            border-bottom: none;
        }
        
        /* Block Badge Styling */
        .badge-morning {
            color: #ffffff;
            background:rgb(231, 143, 27);
            border-radius: 8px;
            font-size: 10px;
            font-weight: 400;
        }
        
        .badge-overtime {
            background:rgb(231, 143, 27);
            color: white;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 400;
        }
        .badge-afternoon {
            color: #ffffff;
            background:#0ea539;
            border-radius: 8px;
            font-size: 10px;
            font-weight: 400;
        }
        
        /* Table Responsive */
        @media (max-width: 768px) {
            .attendance-table {
                font-size: 0.85rem;
            }
            
            .attendance-table thead th,
            .attendance-table tbody td {
                padding: 0.75rem 0.5rem;
            }
            
            .badge-morning,
            .badge-afternoon,
            .badge-overtime {
                font-size: 0.75rem;
                padding: 0.3rem 0.6rem;
            }
        }
        
        /* Sidebar Sections */
        .sidebar-section {
            margin-bottom: 1rem;
        }
        
        .sidebar-title {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #333;
        }
        
        .instructor-profiles {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }
        
        .instructor-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 8px;
            transition: background-color 0.2s;
        }
        
        .instructor-item:hover {
            background: #e9ecef;
        }
        
        .instructor-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--chmsu-green);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            flex-shrink: 0;
        }
        
        .instructor-name {
            font-size: 0.9rem;
            color: #333;
            font-weight: 500;
            flex: 1;
        }
        
        .notices-section {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .notice-item {
            padding: 1rem 0;
            border-bottom: 1px solid #eee;
        }
        
        .notice-item:last-child {
            border-bottom: none;
        }
        
        .notice-title {
            font-weight: 600;
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .notice-description {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .notice-link {
            color: var(--chmsu-green);
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .notice-link:hover {
            text-decoration: underline;
        }
        
        /* Minimap Section */
        .minimap-section {
            background: white;
            border-radius: 15px;
            padding: 1.1rem;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }
        
        .minimap {
            width: 100%;
            height: 200px;
            background: #f8f9fa;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px dashed #ddd;
            margin-bottom: 1rem;
        }
        
        .minimap-placeholder {
            text-align: center;
            color: #666;
        }
        
        .workplace-info {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        
        .workplace-detail {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }
        
        .workplace-detail i {
            width: 16px;
            color: var(--chmsu-green);
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .main-content {
                grid-template-columns: 1fr;
            }
            
            .progress-cards {
                grid-template-columns: 1fr;
            }
            
            
            .welcome-message {
                font-size: 1.8rem;
            }
            
            .student-info {
                flex-direction: column;
                gap: 1rem;
            }

            .top-banner{
                background: #0ea539;
                color: white;
                padding: 1rem;
                position: relative;
                overflow: hidden;
                border-radius: 10px;
                height: 120px;
                display: flex;
                align-items: center;
            }
            .top-banner img{
                position: absolute;
                right: -50px;
                top: 50%;
                transform: translateY(-50%);
                height: 200px;
                width: auto;
                opacity: 0.3;
                z-index: 1;
            }
            .top-banner .container-fluid {
                position: relative;
                z-index: 2;
                width: 100%;
            }
            .welcome-message {
                margin-left: 0;
                font-size: 1.5rem;
                font-weight: 700;
                margin-top: 17rem;
            }
            .student-info {
               display: none !important;
            }

        }
        
        /* Fixed position alert styles */
        .alert-fixed {
            position: fixed !important;
            top: 20px !important;
            right: 20px !important;
            z-index: 9999 !important;
            min-width: 300px !important;
            max-width: 500px !important;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
            border-radius: 8px !important;
        }

        /* Auto-dismiss animation */
        .alert-auto-dismiss {
            animation: slideInRight 0.3s ease-out, fadeOut 0.3s ease-in 4.7s forwards;
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
    </style>
</head>
<body>
<?php include 'student-sidebar.php'; ?>
<main>
    <!-- Top Banner Section -->
    <div class="top-banner">
        <div class="container-fluid">
            <div class="row align-items-center">

                <div class="col-md-10">
                    <div class="welcome-message">Welcome back, <?= htmlspecialchars($user['full_name'] ?? 'Student') ?>!</div>
                    <div class="student-info">
                        <div class="info-item">
                            <i class="bi bi-person-badge" style="color: white;"></i>
                            <span style="color: white;">ID: <?= htmlspecialchars($user['school_id'] ?? 'N/A') ?></span>
                        </div>
                        <div class="info-item">
                            <i class="bi bi-people"style="color: white;" ></i>
                            <span style="color: white;"><?= htmlspecialchars($section['section_name'] ?? 'Not assigned') ?></span>
                    </div>
                    </div>
                </div>
                <div class="logo-info" >
                        <img src="../images/CHMSU.png" alt="CHMSU Logo" class="chmsu-logo">
                </div>

                </div>
            </div>
        </div>
        
    <div class="container-fluid">
        <!-- Forgot Time-Out Notification -->
        <?php if ($missingTimeoutCount > 0): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="alert alert-warning alert-fixed alert-auto-dismiss" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
                        <div class="flex-grow-1">
                            <h6 class="alert-heading mb-1">
                                <i class="bi bi-clock-history me-2"></i>YOU HAVE MISSING TIME-OUTS
                            </h6>
                            <p class="mb-2">
                                You have <strong><?= $missingTimeoutCount ?></strong> attendance record(s) with missing time-outs. 
                                Submit a request for approval for these hours.
                            </p>
                            <a href="forgot_timeout.php" class="btn btn-warning btn-sm">
                                <i class="bi bi-clock-history me-1"></i>Submit Request
                            </a>
                        </div>
                        <button type="button" class="btn-close ms-auto" onclick="dismissAlert(this.parentElement.parentElement)" aria-label="Close"></button>
                    </div>
                </div>
                <script>setTimeout(() => { const alert = document.querySelector('.alert-fixed'); if(alert) alert.remove(); }, 5000);</script>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Main Content Layout -->
        <div class="main-content">
            <!-- Left Column - Main Content -->
            <div class="left-column">
                <!-- OJT Progress Section -->
                <div class="ojt-progress-section " >
                    <div class="single-progress-card">
                        <div class="progress-header">
                            <div class="progress-icon">
                                <div class="progress-title">OJT Hours Progress</div>
                        </div>

                    </div>
                        <div class="progress-container">
                            <div class="progress-bar-container">
                                <div class="progress-bar-fill" style="width: <?= $progressPercentage ?>%"></div>
                </div>
                            <div class="progress-text">
                                <span><?= number_format($totalHours, 0) ?>/600 hours</span>
                                <span class="progress-percentage"><?= number_format($progressPercentage, 1) ?>% progress</span>
                    </div>
                </div>
            </div>
        </div>
        
                <!-- Recent Attendance Section -->
                <div class="attendance-section">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="section-title mb-0">Recent Attendance</h2>
                    </div>
                    
                    <?php if (empty($recentAttendance)): ?>
                        <div class="text-center py-4">
                            <i class="bi bi-calendar-x text-muted" style="font-size: 3rem;"></i>
                            <p class="text-muted mt-2">No recent attendance records</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover attendance-table">
                                <thead>
                                    <tr>
                                        <th class="text-center">Block</th>
                                        <th class="text-center">Date</th>
                                        <th class="text-center">Time In</th>
                                        <th class="text-center">Time Out</th>
                                        <th class="text-center">Hours</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach (array_slice($recentAttendance, 0, 5) as $attendance): ?>
                                    <tr class="attendance-row">
                                        <td class="text-center">
                                            <span class="badge badge-<?= $attendance['block_type'] ?>">
                                                <?= ucfirst($attendance['block_type']) ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <i class="bi bi-calendar-date me-1"></i>
                                            <?= date('M d, Y', strtotime($attendance['date'])) ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($attendance['time_in']): ?>
                                                <i class="bi bi-arrow-right-circle text-success me-1"></i>
                                                <?= date('g:i A', strtotime($attendance['time_in'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not started</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($attendance['time_out']): ?>
                                                <i class="bi bi-arrow-left-circle text-danger me-1"></i>
                                                <?= date('g:i A', strtotime($attendance['time_out'])) ?>
                                            <?php else: ?>
                                                <span class="text-muted">Not completed</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <i class="bi bi-clock-history me-1"></i>
                                            <strong><?= number_format($attendance['hours_earned'], 1) ?>h</strong>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Column - Sidebar -->
            <div class="right-column">
                <!-- Minimap Section -->
                <div class="sidebar-section">
                    <h3 class="sidebar-title">OJT Location</h3>
                    <div class="minimap-section">
                        <div class="minimap">
                            <?php if ($profile['workplace_latitude'] && $profile['workplace_longitude']): ?>
                            <!-- Real Map with coordinates -->
                            <div id="workplace-map" style="width: 100%; height: 200px; border-radius: 10px;"></div>
                            <script>
                                // Initialize map when coordinates are available
                                function initWorkplaceMap() {
                                    const lat = <?= $profile['workplace_latitude'] ?>;
                                    const lng = <?= $profile['workplace_longitude'] ?>;
                                    const workplaceName = '<?= addslashes($profile['workplace_name'] ?? 'Workplace') ?>';
                                    
                                    // Create map using Leaflet (lightweight map library)
                                    const map = L.map('workplace-map').setView([lat, lng], 15);
                                    
                                    // Add OpenStreetMap tiles
                                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                        attribution: '© OpenStreetMap contributors'
                                    }).addTo(map);
                                    
                                    // Add marker for workplace
                                    L.marker([lat, lng])
                                        .addTo(map)
                                        .bindPopup('<strong>' + workplaceName + '</strong><br>OJT Workplace Location')
                                        .openPopup();
                                }
                                
                                // Load Leaflet CSS and JS
                                const link = document.createElement('link');
                                link.rel = 'stylesheet';
                                link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                                document.head.appendChild(link);
                                
                                const script = document.createElement('script');
                                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                                script.onload = initWorkplaceMap;
                                document.head.appendChild(script);
                            </script>
                            <?php else: ?>
                            <!-- Fallback placeholder when no coordinates -->
                            <div class="minimap-placeholder">
                                <i class="bi bi-geo-alt" style="font-size: 2rem; margin-bottom: 0.5rem;"></i>
                                <div>Map View</div>
                                <small>Location: <?= htmlspecialchars($profile['workplace_name'] ?? 'Not assigned') ?></small>
                                <br><small class="text-muted">No coordinates available</small>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="workplace-info">
                            <div class="workplace-detail">
                                <i class="bi bi-building"></i>
                                <span><strong><?= htmlspecialchars($profile['workplace_name'] ?? 'Not assigned') ?></strong></span>
                            </div>
                            <div class="workplace-detail">
                                <i class="bi bi-person-badge"></i>
                                <span>Supervisor: <?= htmlspecialchars($profile['supervisor_name'] ?? 'Not assigned') ?></span>
                            </div>
                            <div class="workplace-detail">
                                <i class="bi bi-briefcase"></i>
                                <span>Position: <?= htmlspecialchars($profile['student_position'] ?? 'Not assigned') ?></span>
                            </div>
                            <?php if ($profile['workplace_latitude'] && $profile['workplace_longitude']): ?>
                            <div class="workplace-detail">
                                <i class="bi bi-geo-alt"></i>
                                <span>Coordinates: <?= number_format($profile['workplace_latitude'], 6) ?>, <?= number_format($profile['workplace_longitude'], 6) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Alert dismiss function
        function dismissAlert(alertElement) {
            alertElement.style.animation = 'fadeOut 0.3s ease-in forwards';
            setTimeout(() => alertElement.remove(), 300);
        }
    </script>
</main>
</body>
</html>
