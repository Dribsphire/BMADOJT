<?php
// Get notification counts for new attendance records and pending documents
require_once '../../vendor/autoload.php';
use App\Services\NotificationService;

$notificationService = new NotificationService();
$newAttendanceCount = 0;
$pendingDocumentsCount = 0;
$overdueDocumentsCount = 0;
$pendingForgotTimeoutCount = 0;

if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'instructor') {
    $newAttendanceCount = $notificationService->getNewAttendanceCountSinceLastVisit($_SESSION['user_id']);
    $pendingDocumentsCount = $notificationService->getPendingDocumentsCount($_SESSION['user_id']);
    $overdueDocumentsCount = $notificationService->getOverdueDocumentsCount($_SESSION['user_id']);
    $pendingForgotTimeoutCount = $notificationService->getPendingForgotTimeoutCount($_SESSION['user_id']);
    
    // Debug: Log the counts (remove after testing)
    error_log("Navigation Header - Pending Documents Count: " . $pendingDocumentsCount . " for user ID: " . $_SESSION['user_id']);
    error_log("Navigation Header - Overdue Documents Count: " . $overdueDocumentsCount . " for user ID: " . $_SESSION['user_id']);
    error_log("Navigation Header - Pending Forgot Timeout Count: " . $pendingForgotTimeoutCount . " for user ID: " . $_SESSION['user_id']);
}

// Get current page filename to determine active link
$currentPage = basename($_SERVER['PHP_SELF']);

// Function to check if a link should be active (only declare if not already declared)
if (!function_exists('isActive')) {
    function isActive($page, $currentPage) {
        return $page === $currentPage ? 'active' : '';
    }
}
?>

<!-- Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="bi bi-mortarboard me-2" style="color: white; "></i>OJT Route
            </a>
            <div class="navbar-nav ms-auto gap-3">
                <a class="nav-link <?= isActive('student-list.php', $currentPage) ?>" href="student-list.php">
                    <i class="bi bi-people me-1" style="color: white; "></i>Student List
                </a>
                <a class="nav-link position-relative <?= isActive('section_attendance.php', $currentPage) ?>" href="section_attendance.php">
                    <i class="bi bi-people me-1" style="color: white; " ></i>Student Attendance
                    <?php if ($newAttendanceCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                            <?php echo $newAttendanceCount; ?>
                            <span class="visually-hidden">new attendance records</span>
                        </span>
                    <?php endif; ?>
                </a>
                <a class="nav-link position-relative <?= isActive('overdue_management.php', $currentPage) ?>" href="overdue_management.php">
                    <i class="bi bi-people me-1" style="color: white; "></i>Overdue
                    <?php if ($overdueDocumentsCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                            <?php echo $overdueDocumentsCount; ?>
                            <span class="visually-hidden">overdue documents</span>
                        </span>
                    <?php endif; ?>
                </a>
                <a class="nav-link position-relative <?= isActive('review_documents.php', $currentPage) ?>" href="review_documents.php">
                    <i class="bi bi-file-text me-1" style="color: white; "></i>Student Requirements
                    <?php if ($pendingDocumentsCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                            <?php echo $pendingDocumentsCount; ?>
                            <span class="visually-hidden">pending documents</span>
                        </span>
                    <?php endif; ?>
                </a>
                <a class="nav-link position-relative <?= isActive('forgot_timeout_review.php', $currentPage) ?>" href="forgot_timeout_review.php">
                    <i class="bi bi-file-text me-1" style="color: white; "></i>Log time-out
                    <?php if ($pendingForgotTimeoutCount > 0): ?>
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                            <?php echo $pendingForgotTimeoutCount; ?>
                            <span class="visually-hidden">pending forgot timeout requests</span>
                        </span>
                    <?php endif; ?>
                </a>
                <a class="nav-link <?= isActive('profile.php', $currentPage) ?>" href="profile.php">
                    <i class="bi bi-person me-1" style="color: white; "></i>My Profile
                </a>
                
                <?php if (isset($_SESSION['acting_role']) && $_SESSION['acting_role'] === 'instructor' && isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'admin'): ?>
                    <form method="POST" action="../admin/switch_role.php" style="display: inline-block; margin: 0;">
                        <input type="hidden" name="action" value="switch_back_to_admin">
                        <input type="hidden" name="redirect" value="../admin/dashboard.php">
                        <button type="submit" class="nav-link switch-to-admin-btn" style="background: none; border: none; color: white; padding: 0.5rem 1rem; cursor: pointer;">
                            <i class="bi bi-arrow-left-circle me-1"></i>Switch to Admin
                        </button>
                    </form>
                <?php endif; ?>

            </div>
        </div>
    </nav>
    <style>
        .navbar {
            background: #0ea539 !important;
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
        
        /* Active link styling */
        .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2) !important;
            border-radius: 6px;
            font-weight: 600;
            padding: 0.5rem 1rem;
        }
        
        .nav-link.active:hover {
            background-color: rgba(255, 255, 255, 0.25) !important;
        }
        
        .nav-link:not(.active):hover {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 6px;
            transition: background-color 0.3s ease;
        }
        
        .switch-to-admin-btn {
            font-size: 1rem;
            font-weight: 500;
        }
        
        .switch-to-admin-btn:hover {
            background-color: rgba(255, 255, 255, 0.1) !important;
            border-radius: 6px;
            transition: background-color 0.3s ease;
        }
    </style>