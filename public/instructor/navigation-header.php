<?php
// Get notification count for new attendance records
require_once '../../vendor/autoload.php';
use App\Services\NotificationService;

$notificationService = new NotificationService();
$newAttendanceCount = 0;

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'instructor') {
    $newAttendanceCount = $notificationService->getNewAttendanceCountSinceLastVisit($_SESSION['user_id']);
}
?>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard me-2"></i>OJT Route
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="student-list.php">
                    <i class="bi bi-people me-1"></i>Student List
                </a>
                <a class="nav-link position-relative" href="section_attendance.php">
                    <i class="bi bi-people me-1"></i>Student Attendance
                    <?php if ($newAttendanceCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                            <?php echo $newAttendanceCount; ?>
                            <span class="visually-hidden">new attendance records</span>
                        </span>
                    <?php endif; ?>
                </a>
                <a class="nav-link" href="overdue_management.php">
                    <i class="bi bi-people me-1"></i>Overdue
                </a>
                <a class="nav-link" href="review_documents.php">
                    <i class="bi bi-file-text me-1"></i>Student Requirements
                </a>
                <a class="nav-link" href="forgot_timeout_review.php">
                    <i class="bi bi-file-text me-1"></i>Log time-out
                </a>
                <a class="nav-link" href="profile.php">
                    <i class="bi bi-person me-1"></i>My Profile
                </a>
                <button type="button" style="margin-left: 2rem;" class="btn btn-outline-light btn-sm" 
                        data-bs-toggle="modal" data-bs-target="#logoutModal">
                    <i class="bi bi-box-arrow-right me-1" style="color: white;"></i>Logout
                </button>
            </div>
        </div>
    </nav>
    <style>
        .navbar {
            background: var(--chmsu-green) !important;
        }
        
        .navbar-brand {
            font-weight: 600;
        }
        
        .notification-badge {
            font-size: 0.75rem;
            min-width: 18px;
            height: 18px;
            line-height: 18px;
            padding: 0 6px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
        
        .nav-link:hover .notification-badge {
            animation: none;
        }
    </style>