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
    
    <!-- FullCalendar CSS -->
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.css" rel="stylesheet">
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
        
        .total-hours-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            background: linear-gradient(135deg, #0ea539, #0d8a2f);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 8px rgba(14, 165, 57, 0.3);
        }
        
        .hours-number {
            font-size: 2rem;
            font-weight: bold;
            line-height: 1;
        }
        
        .hours-label {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-top: 0.2rem;
        }
        
        .progress-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
        }
        
        .student-avatar {
            flex-shrink: 0;
        }
        
        .profile-image {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #0ea539;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
        }
        
        .student-info {
            flex-grow: 1;
        }
        
        .student-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: #333;
            margin-bottom: 0.2rem;
        }
        
        .student-id {
            font-size: 0.9rem;
            color: #666;
            display: flex;
            align-items: center;
        }
        
        /* Ensure equal height for side-by-side cards */
        .row .col-md-6 {
            display: flex;
        }
        
        .row .col-md-6 .info-card {
            width: 100%;
        }
        
        /* Calendar Customization */
        .fc {
            font-family: inherit;
        }
        .fc-event {
            border-radius: 4px;
            border: none;
            font-size: 0.7rem;
            padding: 1px 3px;
        }
        .fc-event-title {
            font-weight: 500;
        }
        
        /* Make calendar days clickable */
        .fc-daygrid-day {
            cursor: pointer;
        }
        .fc-daygrid-day:hover {
            background-color: rgba(14, 165, 57, 0.1);
        }
        
        /* Status Colors */
        .event-completed {
            background-color: #28a745 !important;
            color: white !important;
        }
        .event-incomplete {
            background-color: #ffc107 !important;
            color: #212529 !important;
        }
        .event-missed {
            background-color: #dc3545 !important;
            color: white !important;
        }
        .event-pending {
            background-color: #0ea539 !important;
            color: white !important;
        }
        
        .calendar-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            padding: 15px;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .status-legend {
            font-size: 0.75rem;
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



            <div class="row">
                <!-- Student Information -->
                <div class="col-md-6 mb-4">
                    <div class="info-card">
                        <h5 class="mb-3">
                            <i class="bi bi-person-lines-fill me-2"></i>Student Information
                        </h5>
                        <div class="row">
                            <div class="col-sm-4"><strong>Student:</strong></div>
                            <div class="col-sm-8">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="student-avatar">
                                        <?php 
                                        // Check if profile_picture exists and is not empty
                                        $hasProfilePic = !empty($student['profile_picture']) && $student['profile_picture'] !== null;
                                        
                                        // Use absolute path for file existence check
                                        $absoluteProfilePath = $hasProfilePic ? __DIR__ . '/../../uploads/profiles/' . $student['profile_picture'] : '';
                                        $profilePicPath = $hasProfilePic ? '../../uploads/profiles/' . $student['profile_picture'] : '';
                                        $defaultPicPath = '../assets/images/default-avatar.svg';
                                        
                                        // Check file existence using absolute path, but use relative path for display
                                        $fullPath = ($hasProfilePic && file_exists($absoluteProfilePath)) ? $profilePicPath : $defaultPicPath;
                                        
                                        ?>
                                        <img src="<?= htmlspecialchars($fullPath) ?>" 
                                             alt="Student Profile" 
                                             class="profile-image"
                                             onerror="this.src='<?= htmlspecialchars($defaultPicPath) ?>'">
                                    </div>
                                    <div class="student-info">
                                        <div class="student-name"><?= htmlspecialchars($student['full_name']) ?></div>
                                        <div class="student-id">
                                            <i class="bi bi-person-badge me-1"></i>
                                            <?= htmlspecialchars($student['school_id']) ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-sm-4"><strong>Email:</strong></div>
                            <div class="col-sm-8"><?= htmlspecialchars($student['email']) ?></div>
                            
                            <div class="col-sm-4"><strong>Contact:</strong></div>
                            <div class="col-sm-8"><?= htmlspecialchars($student['contact'] ?? 'Not provided') ?></div>
                            
                            <div class="col-sm-4"><strong>Section:</strong></div>
                            <div class="col-sm-8"><?= htmlspecialchars($student['section_name']) ?> (<?= htmlspecialchars($student['section_code']) ?>)</div>
                            
                            <div class="col-sm-4"><strong>Total Hours:</strong></div>
                            <div class="col-sm-8">
                                <div class="d-flex align-items-center gap-3">
                                    <div class="total-hours-display">
                                        <span class="hours-number"><?= number_format($attendance_summary['total_hours'], 1) ?></span>
                                        <span class="hours-label">hours</span>
                                    </div>
                                    <div class="progress-container">
                                        <div class="progress" style="width: 200px; height: 8px;">
                                            <div class="progress-bar bg-primary" role="progressbar" 
                                                 style="width: <?= min(100, ($attendance_summary['total_hours'] / 600) * 100) ?>%"
                                                 aria-valuenow="<?= $attendance_summary['total_hours'] ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="600">
                                            </div>
                                        </div>
                                        <small class="text-muted"><?= number_format($attendance_summary['total_hours'], 1) ?>/600 hours</small>
                                    </div>
                                </div>
                            </div>
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

            <!-- Attendance Calendar -->
            <div class="row mb-4" id="attendance">
                <div class="col-12">
                    <div class="info-card">
                        <h5 class="mb-3">
                            <i class="bi bi-calendar-check me-2"></i>Attendance Calendar
                        </h5>
                        
                        <!-- Status Legend -->
                        <div class="row mb-3">
                            <div class="col-12">
                                <div class="card border-0 bg-light">
                                    <div class="card-body py-2">
                                        <div class="row g-2">
                                            <div class="col-6 col-md-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="event-completed me-2" style="width: 12px; height: 12px; border-radius: 2px;"></div>
                                                    <small class="text-muted">Completed</small>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="event-incomplete me-2" style="width: 12px; height: 12px; border-radius: 2px;"></div>
                                                    <small class="text-muted">Time-in Only</small>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="event-missed me-2" style="width: 12px; height: 12px; border-radius: 2px;"></div>
                                                    <small class="text-muted">Missed</small>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <div class="d-flex align-items-center">
                                                    <div class="event-pending me-2" style="width: 12px; height: 12px; border-radius: 2px;"></div>
                                                    <small class="text-muted">Pending Request</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Mini Calendar -->
                        <div class="calendar-container">
                            <div id="attendanceCalendar"></div>
                        </div>
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
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- FullCalendar JS -->
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>
    
    <script>
        let calendar;
        const studentId = <?= $student_id ?>;

        document.addEventListener('DOMContentLoaded', function() {
            // Initialize FullCalendar
            const calendarEl = document.getElementById('attendanceCalendar');
            
            calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek'
                },
                height: 'auto',
                events: function(info, successCallback, failureCallback) {
                    // Fetch attendance data for the calendar
                    fetch(`../student/attendance_calendar_data.php?student_id=${studentId}`)
                        .then(response => {
                            if (!response.ok) {
                                throw new Error(`HTTP error! status: ${response.status}`);
                            }
                            return response.json();
                        })
                        .then(data => {
                            // Check if data is an array
                            if (!Array.isArray(data)) {
                                console.error('Invalid data format:', data);
                                successCallback([]);
                                return;
                            }
                            
                            const events = data.map(record => {
                                const status = getAttendanceStatus(record);
                                const statusClass = getStatusColorClass(status);
                                
                                // Handle missed days specially
                                let title;
                                if (record.is_missed || record.block_type === 'missed') {
                                    title = `Missed - No Attendance`;
                                } else {
                                    title = `${record.block_type.charAt(0).toUpperCase() + record.block_type.slice(1)} - ${status}`;
                                }
                                
                                return {
                                    id: record.id,
                                    title: title,
                                    start: record.date,
                                    allDay: true,
                                    className: `event-${status}`,
                                    extendedProps: {
                                        record: record,
                                        status: status,
                                        statusClass: statusClass
                                    }
                                };
                            });
                            successCallback(events);
                        })
                        .catch(error => {
                            console.error('Error loading calendar events:', error);
                            successCallback([]);
                        });
                },
                eventClick: function(info) {
                    showDayAttendanceDetails(info.event.start);
                },
                dateClick: function(info) {
                    showDayAttendanceDetails(info.date);
                },
                eventDidMount: function(info) {
                    // Add tooltip
                    info.el.title = `${info.event.title} - Click for details`;
                },
                dayMaxEvents: 2,
                moreLinkClick: 'popover'
            });

            calendar.render();
        });

        function getAttendanceStatus(record) {
            // Check if this is a missed day (virtual record)
            if (record.is_missed || record.block_type === 'missed') {
                return 'missed';
            }
            
            if (record.time_in === null) {
                return 'missed';
            }
            
            if (record.time_out === null) {
                if (record.forgot_timeout_request_id && record.forgot_timeout_status === 'pending') {
                    return 'pending';
                }
                return 'incomplete';
            }
            
            return 'completed';
        }

        function getStatusColorClass(status) {
            const classes = {
                'completed': 'event-completed',
                'incomplete': 'event-incomplete', 
                'missed': 'event-missed',
                'pending': 'event-pending'
            };
            return classes[status] || 'event-missed';
        }

        function showDayAttendanceDetails(clickedDate) {
            // Use local date formatting to avoid timezone issues
            const year = clickedDate.getFullYear();
            const month = String(clickedDate.getMonth() + 1).padStart(2, '0');
            const day = String(clickedDate.getDate()).padStart(2, '0');
            const dateStr = `${year}-${month}-${day}`;
            
            // Fetch all attendance records for this date
            fetch(`../student/attendance_calendar_data.php?student_id=${studentId}&date=${dateStr}`)
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(records => {
                    // Check if data is an array
                    if (!Array.isArray(records)) {
                        console.error('Invalid records format:', records);
                        showNoAttendanceModal(clickedDate);
                        return;
                    }
                    
                    if (records.length === 0) {
                        showNoAttendanceModal(clickedDate);
                        return;
                    }
                    
                    // Check if this is a missed day
                    const missedRecord = records.find(r => r.is_missed || r.block_type === 'missed');
                    if (missedRecord) {
                        showMissedDayModal(clickedDate);
                        return;
                    }
                    
                    // Group records by block type
                    const morningRecord = records.find(r => r.block_type === 'morning');
                    const afternoonRecord = records.find(r => r.block_type === 'afternoon');
                    const overtimeRecord = records.find(r => r.block_type === 'overtime');
                    
                    const modalHtml = createDayModalHtml(clickedDate, morningRecord, afternoonRecord, overtimeRecord);
                    
                    // Remove existing modal if any
                    const existingModal = document.getElementById('dayAttendanceModal');
                    if (existingModal) {
                        existingModal.remove();
                    }
                    
                    // Add modal to body
                    document.body.insertAdjacentHTML('beforeend', modalHtml);
                    
                    // Show modal
                    const modal = new bootstrap.Modal(document.getElementById('dayAttendanceModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error fetching day attendance:', error);
                    alert('Error loading attendance data: ' + error.message);
                    showNoAttendanceModal(clickedDate);
                });
        }
        
        function createDayModalHtml(date, morningRecord, afternoonRecord, overtimeRecord) {
            const dateFormatted = date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            const totalHours = parseFloat(morningRecord?.hours_earned || 0) + 
                              parseFloat(afternoonRecord?.hours_earned || 0) + 
                              parseFloat(overtimeRecord?.hours_earned || 0);
            
            return `
                <div class="modal fade" id="dayAttendanceModal" tabindex="-1">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-calendar-date me-2"></i>${dateFormatted}
                                    <span class="badge bg-primary ms-2">${(totalHours || 0).toFixed(2)} hours</span>
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    ${createBlockCard('Morning', morningRecord, 'morning')}
                                    ${createBlockCard('Afternoon', afternoonRecord, 'afternoon')}
                                    ${createBlockCard('Overtime', overtimeRecord, 'overtime')}
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }
        
        function createBlockCard(blockName, record, blockType) {
            if (!record) {
                return `
                    <div class="col-md-4 mb-3">
                        <div class="card border-secondary">
                            <div class="card-header bg-light">
                                <h6 class="mb-0">
                                    <i class="bi bi-sun me-1"></i>${blockName}
                                    <span class="badge bg-secondary float-end">No Record</span>
                                </h6>
                            </div>
                            <div class="card-body text-center">
                                <i class="bi bi-dash-circle text-muted fs-1"></i>
                                <p class="text-muted mb-0">No attendance recorded</p>
                            </div>
                        </div>
                    </div>
                `;
            }
            
            const status = getAttendanceStatus(record);
            const statusText = getStatusDisplayText(status);
            const statusClass = getStatusColorClass(status).replace('event-', '');
            
            const timeIn = record.time_in ? new Date(record.time_in).toLocaleTimeString() : 'Not recorded';
            const timeOut = record.time_out ? new Date(record.time_out).toLocaleTimeString() : 'Not recorded';
            const hours = record.hours_earned > 0 ? `${record.hours_earned} hours` : 'No hours earned';
            
            const photoHtml = record.time_in_photo_path ? `
                <div class="text-center mt-2">
                    <img src="../view_attendance_photo.php?id=${record.id}&type=time_in" 
                         class="img-fluid rounded shadow-sm" 
                         style="max-width: 200px; max-height: 150px; object-fit: cover;"
                         alt="Time-in Photo"
                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                    <div class="alert alert-warning mt-2" style="display: none; font-size: 0.8rem;">
                        <i class="bi bi-image me-1"></i>Photo not available
                    </div>
                </div>
            ` : `
                <div class="text-center mt-2">
                    <div class="alert alert-info py-2" style="font-size: 0.8rem;">
                        <i class="bi bi-info-circle me-1"></i>No photo available
                    </div>
                </div>
            `;
            
            return `
                <div class="col-md-4 mb-3">
                    <div class="card border-${statusClass}">
                        <div class="card-header bg-${statusClass} text-white">
                            <h6 class="mb-0">
                                <i class="bi bi-${blockType === 'morning' ? 'sun' : blockType === 'afternoon' ? 'sun-fill' : 'moon'} me-1"></i>${blockName}
                                <span class="badge bg-white text-${statusClass} float-end">${statusText}</span>
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row mb-2">
                                <div class="col-6">
                                    <small class="text-muted">Time In:</small><br>
                                    <span class="fw-bold text-success">${timeIn}</span>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Time Out:</small><br>
                                    <span class="fw-bold text-success">${timeOut}</span>
                                </div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-12">
                                    <small class="text-muted">Hours:</small><br>
                                    <span class="fw-bold text-primary">${hours}</span>
                                </div>
                            </div>
                            
                            ${photoHtml}
                            
                            ${record.forgot_timeout_request_id ? `
                            <div class="alert alert-info mt-2 py-1" style="font-size: 0.8rem;">
                                <i class="bi bi-info-circle me-1"></i>
                                <strong>Request:</strong> ${record.forgot_timeout_status}
                            </div>
                            ` : ''}
                        </div>
                    </div>
                </div>
            `;
        }
        
        function showMissedDayModal(date) {
            const dateFormatted = date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            const modalHtml = `
                <div class="modal fade" id="dayAttendanceModal" tabindex="-1">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">
                                    <i class="bi bi-calendar-x me-2"></i>${dateFormatted}
                                    <span class="badge bg-white text-danger ms-2">Missed Day</span>
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-12">
                                        <div class="card border-danger">
                                            <div class="card-header bg-danger text-white">
                                                <h6 class="mb-0">
                                                    <i class="bi bi-exclamation-triangle me-2"></i>No Attendance Recorded
                                                </h6>
                                            </div>
                                            <div class="card-body text-center py-4">
                                                <i class="bi bi-calendar-x text-danger" style="font-size: 3rem;"></i>
                                                <h5 class="text-danger mt-3">Missed Day</h5>
                                                <p class="text-muted">No attendance was recorded for this date during the student's OJT period.</p>
                                                <div class="alert alert-warning mt-3">
                                                    <i class="bi bi-info-circle me-2"></i>
                                                    <strong>Note:</strong> This day is marked as missed because no attendance was recorded during the scheduled OJT period.
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('dayAttendanceModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('dayAttendanceModal'));
            modal.show();
        }
        
        function showNoAttendanceModal(date) {
            const dateFormatted = date.toLocaleDateString('en-US', { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            });
            
            const modalHtml = `
                <div class="modal fade" id="dayAttendanceModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="bi bi-calendar-date me-2"></i>${dateFormatted}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center">
                                <i class="bi bi-calendar-x text-muted fs-1 mb-3"></i>
                                <h5>No Attendance Records</h5>
                                <p class="text-muted">No attendance was recorded for this date.</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('dayAttendanceModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to body
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('dayAttendanceModal'));
            modal.show();
        }

        function getStatusDisplayText(status) {
            const texts = {
                'completed': 'Completed',
                'incomplete': 'Time-in Only',
                'missed': 'Missed',
                'pending': 'Pending Request'
            };
            return texts[status] || 'Unknown';
        }

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
