<?php

/**
 * User Management
 * OJT Route - Admin user management page
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Services\EmailService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;
use App\Utils\AdminAccess;

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication and authorization
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}


// Check admin access (including acting as instructor)
AdminAccess::requireAdminAccess();

// Get current user
$user = $authMiddleware->getCurrentUser();

// Get database connection
$pdo = Database::getInstance();

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'delete_user':
            $userId = $_POST['user_id'] ?? '';
            if ($userId) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND role != 'admin'");
                    $stmt->execute([$userId]);
                    $_SESSION['success'] = 'User deleted successfully.';
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error deleting user: ' . $e->getMessage();
                }
            }
            break;
            
        case 'change_password':
            // Debug: Log received POST data
            error_log('Change Password - POST data: ' . print_r($_POST, true));
            
            $userId = (int) ($_POST['user_id'] ?? 0);
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            
            // Debug: Log parsed values
            error_log("Change Password - User ID: $userId, Password length: " . strlen($newPassword));
            
            if ($userId <= 0) {
                $_SESSION['error'] = 'Invalid user ID. User ID received: ' . ($_POST['user_id'] ?? 'not set');
            } elseif (empty($newPassword)) {
                $_SESSION['error'] = 'New password is required.';
            } elseif (strlen($newPassword) < 8) {
                $_SESSION['error'] = 'Password must be at least 8 characters long.';
            } elseif ($newPassword !== $confirmPassword) {
                $_SESSION['error'] = 'Passwords do not match.';
            } else {
                try {
                    // Verify user exists and is not an admin (prevent changing admin passwords this way)
                    $stmt = $pdo->prepare("SELECT id, role, school_id, full_name FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if (!$targetUser) {
                        $_SESSION['error'] = 'User not found.';
                    } elseif ($targetUser['role'] === 'admin') {
                        $_SESSION['error'] = 'Cannot change admin password through this interface.';
                    } else {
                        // Update password
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                        $stmt->execute([$hashedPassword, $userId]);
                        
                        // Log activity
                        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
                        $stmt->execute([
                            $user->id,
                            'password_reset',
                            "Admin reset password for user {$targetUser['school_id']} ({$targetUser['full_name']})"
                        ]);
                        
                        $_SESSION['success'] = "Password changed successfully for {$targetUser['full_name']} ({$targetUser['school_id']}).";
                    }
                } catch (Exception $e) {
                    $_SESSION['error'] = 'Error changing password: ' . $e->getMessage();
                }
            }
            
            // Redirect back to users page
            header('Location: users.php');
            exit;
            break;
            
        case 'bulk_register':
            // Handle bulk registration
            $users = $_POST['users'] ?? [];
            $sectionId = $_POST['section_id'] ?? '';
            $password = $_POST['password'] ?? 'Password@2024';
            
            if (!empty($users) && $sectionId) {
                $successCount = 0;
                $errorCount = 0;
                
                foreach ($users as $userData) {
                    try {
                        $stmt = $pdo->prepare("INSERT INTO users (school_id, password_hash, email, full_name, role, section_id) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([
                            $userData['school_id'],
                            password_hash($password, PASSWORD_DEFAULT),
                            $userData['email'],
                            $userData['full_name'],
                            $userData['role'],
                            $sectionId
                        ]);
                        $successCount++;
                    } catch (Exception $e) {
                        $errorCount++;
                    }
                }
                
                if ($successCount > 0) {
                    $_SESSION['success'] = "Successfully registered {$successCount} users.";
                }
                if ($errorCount > 0) {
                    $_SESSION['error'] = "Failed to register {$errorCount} users.";
                }
            }
            break;
            
        case 'send_notification':
            // Handle notification sending
            try {
                $emailService = new EmailService();
                $recipient_type = $_POST['recipient_type'] ?? '';
                $subject = $_POST['subject'] ?? '';
                $message = $_POST['message'] ?? '';
                $specific_users = $_POST['specific_users'] ?? [];
                
                if (empty($recipient_type) || empty($subject) || empty($message)) {
                    throw new Exception('All fields are required.');
                }
                
                // Get recipients based on type
                $recipients = [];
                switch ($recipient_type) {
                    case 'all_students':
                        $stmt = $pdo->query("SELECT id, email, full_name FROM users WHERE role = 'student' AND email IS NOT NULL AND role != 'admin'");
                        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        break;
                    case 'all_instructors':
                        $stmt = $pdo->query("SELECT id, email, full_name FROM users WHERE role = 'instructor' AND email IS NOT NULL AND role != 'admin'");
                        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        break;
                    case 'all_students_instructors':
                        $stmt = $pdo->query("SELECT id, email, full_name FROM users WHERE role IN ('student', 'instructor') AND email IS NOT NULL AND role != 'admin'");
                        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        break;
                    case 'specific_students':
                        if (empty($specific_users)) {
                            throw new Exception('Please select at least one student.');
                        }
                        $placeholders = str_repeat('?,', count($specific_users) - 1) . '?';
                        $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE id IN ($placeholders) AND role = 'student' AND email IS NOT NULL");
                        $stmt->execute($specific_users);
                        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        break;
                    case 'specific_instructors':
                        if (empty($specific_users)) {
                            throw new Exception('Please select at least one instructor.');
                        }
                        $placeholders = str_repeat('?,', count($specific_users) - 1) . '?';
                        $stmt = $pdo->prepare("SELECT id, email, full_name FROM users WHERE id IN ($placeholders) AND role = 'instructor' AND email IS NOT NULL");
                        $stmt->execute($specific_users);
                        $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        break;
                    default:
                        throw new Exception('Invalid recipient type.');
                }
                
                if (empty($recipients)) {
                    throw new Exception('No recipients found for the selected type.');
                }
                
                // Send emails
                $successCount = 0;
                $failureCount = 0;
                foreach ($recipients as $recipient) {
                    try {
                        $result = $emailService->sendEmail(
                            $recipient['email'],
                            $subject,
                            $message,
                            [],
                            true
                        );
                        if ($result['success']) {
                            $successCount++;
                        } else {
                            $failureCount++;
                        }
                    } catch (Exception $e) {
                        $failureCount++;
                        error_log("Failed to send email to {$recipient['email']}: " . $e->getMessage());
                    }
                }
                
                if ($successCount > 0) {
                    $_SESSION['success'] = "Notification sent successfully! {$successCount} email(s) sent, {$failureCount} failed.";
                } else {
                    $_SESSION['error'] = "Failed to send notification. {$failureCount} error(s).";
                }
                
            } catch (Exception $e) {
                $_SESSION['error'] = 'Error sending notification: ' . $e->getMessage();
            }
            break;
    }
    
    // Redirect to prevent form resubmission
    header('Location: users_view.php');
    exit;
}

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$section = $_GET['section'] ?? '';
$showArchived = isset($_GET['show_archived']) && $_GET['show_archived'] === '1';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query
$whereConditions = [];
$params = [];

// Filter by archived status (default: show only non-archived)
if (!$showArchived) {
    $whereConditions[] = "(u.archived = 0 OR u.archived IS NULL)";
}

if (!empty($search)) {
    $whereConditions[] = "(u.full_name LIKE ? OR u.email LIKE ? OR u.school_id LIKE ?)";
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if (!empty($role)) {
    $whereConditions[] = "u.role = ?";
    $params[] = $role;
}

if (!empty($section)) {
    $whereConditions[] = "u.section_id = ?";
    $params[] = $section;
}

$whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

// Get total count
$countQuery = "SELECT COUNT(*) FROM users u {$whereClause}";
$stmt = $pdo->prepare($countQuery);
$stmt->execute($params);
$totalUsers = $stmt->fetchColumn();

$totalPages = ceil($totalUsers / $limit);

// Get users
$query = "SELECT u.*, s.section_name 
          FROM users u 
          LEFT JOIN sections s ON u.section_id = s.id 
          {$whereClause} 
          ORDER BY u.created_at DESC 
          LIMIT {$limit} OFFSET {$offset}";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get sections for filter
$sections = $pdo->query("SELECT id, section_code, section_name FROM sections ORDER BY section_name")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
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
        
        /* Prevent page jump on form submission */
        html {
            scroll-behavior: smooth;
        }
        
        /* Ensure smooth transitions */
        * {
            scroll-behavior: smooth;
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
        }
        
        .btn-primary:hover {
            background: var(--chmsu-green-dark);
        }
        
        .table th {
            background-color: #f8f9fa;
            border-top: none;
            font-weight: 600;
        }
        
        .badge {
            font-size: 0.75rem;
        }
        
        /* Fix modal positioning and z-index issues */
        .modal {
            top: 0 !important;
            left: 0 !important;
            z-index: 1055 !important;
            display: none;
            width: 100%;
            height: 100%;
            overflow-x: hidden;
            overflow-y: auto;
            outline: 0;
        }
        
        .modal.show {
            display: block !important;
        }
        
        .modal-backdrop {
            top: 0 !important;
            left: 0 !important;
            z-index: 1040 !important;
            width: 100vw !important;
            height: 100vh !important;
            background-color: rgba(0, 0, 0, 0.5) !important;
        }
        
        .modal-backdrop.show {
            opacity: 0.5;
        }
        
        .modal-dialog {
            width: auto;
            margin: 1.75rem auto;
            pointer-events: none;
            z-index: 1055 !important;
            max-width: 500px;
        }
        
        .modal-dialog * {
            pointer-events: auto;
        }
        
        .modal-dialog-scrollable {
            max-height: calc(100% - 3.5rem);
        }
        
        .modal-dialog-centered {
            display: flex;
            align-items: center;
            min-height: calc(100% - 3.5rem);
        }
        
        .modal-content {
            display: flex;
            flex-direction: column;
            width: 100%;
            pointer-events: auto;
            background-color: #fff;
            background-clip: padding-box;
            border: 1px solid rgba(0, 0, 0, 0.2);
            border-radius: 0.3rem;
            outline: 0;
            z-index: 1055 !important;
        }
        
        /* Ensure sidebar stays below modals */
        #sidebar {
            z-index: 1000 !important;
        }
        
        /* Fix cursor in input fields */
        .modal input, .modal textarea, .modal select {
            cursor: text !important;
        }
        
        .modal button {
            cursor: pointer !important;
        }
        
        /* Notification Modal Fixes */
        #composeNotificationModal .modal-dialog {
            max-width: 800px;
            margin: 1.75rem auto;
        }
        
        #composeNotificationModal .modal-body {
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        #composeNotificationModal .modal-footer {
            bottom: 0;
            background: white;
            z-index: 1;
            margin: 0;
            border-top: 1px solid #dee2e6;
        }
        
        /* Ensure checkbox container doesn't overflow */
        #specificUsersContainer .border.rounded {
            max-width: 100%;
            overflow-x: hidden;
        }
        
        #specificUsersContainer .form-check-label {
            max-width: 100%;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        /* Responsive modal */
        @media (max-width: 768px) {
            #composeNotificationModal .modal-dialog {
                max-width: 95%;
                margin: 0.5rem auto;
            }
        }
    </style>
</head>
<body>
    
    <?php include 'sidebar.php'; ?>
    <main>
    <!-- Main Content -->
    <div class="container-fluid py-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h2 class="mb-0">
                        <i class="bi bi-people me-2"></i>User Management
                    </h2>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#composeNotificationModal" title="Compose Notification">
                        <i class="bi bi-bell-fill" style="color: white; font-size: 1.2rem;"></i>
                    </button>
                    
                </div>
            </div>
        </div>
        <br>
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= $stats['student'] ?? 0 ?></h3>
                        <p class="stat-label">Students</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= $stats['instructor'] ?? 0 ?></h3>
                        <p class="stat-label">Instructors</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= $stats['admin'] ?? 0 ?></h3>
                        <p class="stat-label">Admins</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= $stats['total'] ?? 0 ?></h3>
                        <p class="stat-label">Total Users</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <h5 class="card-title">
                                    <i class="bi bi-upload me-2"></i>CSV Registration
                                </h5>
                                <p class="card-text">Register multiple users from CSV file.</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#bulkRegisterModal">
                                    <i class="bi bi-upload me-1" style="color: white;"></i>CSV Register
                                </button>
                            </div>
                            <div class="col-md-4">
                                <h5 class="card-title">
                                    <i class="bi bi-person-plus me-2"></i>Manual Registration
                                </h5>
                                <p class="card-text">Register individual users manually.</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#manualRegisterModal">
                                    <i class="bi bi-person-plus me-1" style="color: white;"></i>Add User
                                </button>
                            </div>
                            <div class="col-md-4">
                                <h5 class="card-title">
                                    <i class="bi bi-archive me-2"></i>Archive Users
                                </h5>
                                <p class="card-text">Archive all users (except admins) when batch completes OJT.</p>
                                <button type="button" class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#archiveAllModal">
                                    <i class="bi bi-archive me-1" style="color: white;"></i>Archive All Users
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        
        <!-- Users Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="bi bi-table me-2"></i>All Users
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle me-2"></i>
                                <?= htmlspecialchars($_SESSION['success']) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['success']); ?>
                        <?php endif; ?>
                        
                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?= htmlspecialchars($_SESSION['error']) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['error']); ?>
                        <?php endif; ?>
                        
                        <!-- Search and Filter Controls -->
                        <div class="row mb-4" id="search-filters">
                            <div class="col-12">
                                <form method="GET" class="row g-2 align-items-end" id="search-form">
                                    <div class="col-md-3">
                                        <label for="search" class="form-label">Search</label>
                                        <input type="text" class="form-control" id="search" name="search" 
                                               placeholder="Search by name, email, or school ID" 
                                               value="<?= htmlspecialchars($_GET['search'] ?? '') ?>" style="font-size: 11px;">
                                    </div>
                                    <div class="col-md-2">
                                        <label for="role_filter" class="form-label">Role</label>
                                        <select class="form-select" id="role_filter" name="role" style="font-size: 11px;">
                                            <option value="">All Roles</option>
                                            <option value="admin" <?= ($_GET['role'] ?? '') === 'admin' ? 'selected' : '' ?>>Admin</option>
                                            <option value="instructor" <?= ($_GET['role'] ?? '') === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                                            <option value="student" <?= ($_GET['role'] ?? '') === 'student' ? 'selected' : '' ?>>Student</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3" >
                                        <label for="section" class="form-label">Section</label>
                                        <select class="form-select" id="section" name="section" style="font-size: 11px;">
                                            <option value="">All Sections</option>
                                            <?php foreach ($sections as $section): ?>
                                                <option value="<?= $section['id'] ?>" 
                                                        <?= ($_GET['section'] ?? '') == $section['id'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($section['section_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <div class="form-check mt-4">
                                            <input class="form-check-input" type="checkbox" id="show_archived" name="show_archived" value="1" 
                                                   <?= $showArchived ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="show_archived" style="font-size: 11px;">
                                                Show Archived Users
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="d-grid" ">
                                            <button type="submit" class="btn btn-primary" id="search-btn" title="Search" >
                                                <i class="bi bi-search" id="search-icon" style="color: white; font-size: 11px;"></i>
                                                <span class="spinner-border spinner-border-sm d-none" id="search-spinner" role="status"></span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="col-md-1">
                                        <div class="d-grid">
                                            <a href="users.php" class="btn btn-outline-secondary" title="Clear Filters">
                                                <i class="bi bi-x-circle" style="font-size: 11px;"></i>
                                            </a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>School ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Section</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $userRow): 
                                        $isArchived = isset($userRow['archived']) && $userRow['archived'] == 1;
                                    ?>
                                    <tr class="<?= $isArchived ? 'table-secondary opacity-75' : '' ?>">
                                        <td>
                                            <strong><?= htmlspecialchars($userRow['school_id']) ?></strong>
                                            <?php if ($isArchived): ?>
                                                <i class="bi bi-archive text-muted ms-1" title="Archived"></i>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($userRow['full_name']) ?></td>
                                        <td><?= htmlspecialchars($userRow['email']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $userRow['role'] === 'admin' ? 'danger' : ($userRow['role'] === 'instructor' ? 'warning' : 'primary') ?>">
                                                <?= ucfirst($userRow['role']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $userRow['section_name'] ? htmlspecialchars($userRow['section_name']) : '<span class="text-muted">No section</span>' ?>
                                        </td>
                                        <td>
                                            <?php if ($isArchived): ?>
                                                <span class="badge bg-secondary">Archived</span>
                                            <?php else: ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                                        data-bs-toggle="modal" data-bs-target="#assignSectionModal"
                                                        data-user-id="<?= $userRow['id'] ?>"
                                                        data-user-name="<?= htmlspecialchars($userRow['full_name']) ?>"
                                                        data-current-section="<?= $userRow['section_id'] ?>">
                                                    <i class="bi bi-gear"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-warning btn-sm" 
                                                        data-bs-toggle="modal" data-bs-target="#changePasswordModal"
                                                        data-user-id="<?= $userRow['id'] ?>"
                                                        data-user-name="<?= htmlspecialchars($userRow['full_name']) ?>"
                                                        data-school-id="<?= htmlspecialchars($userRow['school_id']) ?>">
                                                    <i class="bi bi-key"></i>
                                                </button>
                                                <?php if ($userRow['role'] !== 'admin'): ?>
                                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                                        onclick="confirmDelete(<?= $userRow['id'] ?>, '<?= htmlspecialchars($userRow['full_name']) ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <nav aria-label="Users pagination">
                            <ul class="pagination justify-content-center">
                                <?php 
                                // Build query string for pagination
                                $queryParams = [];
                                if (!empty($_GET['search'])) $queryParams['search'] = $_GET['search'];
                                if (!empty($_GET['role'])) $queryParams['role'] = $_GET['role'];
                                if (!empty($_GET['section'])) $queryParams['section'] = $_GET['section'];
                                if ($showArchived) $queryParams['show_archived'] = '1';
                                
                                for ($i = 1; $i <= $totalPages; $i++): 
                                    $queryParams['page'] = $i;
                                    $queryString = http_build_query($queryParams);
                                ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?<?= $queryString ?>"><?= $i ?></a>
                                </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
  
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Notification form handling
        document.addEventListener('DOMContentLoaded', function() {
            const recipientType = document.getElementById('recipientType');
            const specificUsersContainer = document.getElementById('specificUsersContainer');
            const studentsList = document.getElementById('studentsList');
            const instructorsList = document.getElementById('instructorsList');
            const selectAllCheckbox = document.getElementById('selectAllUsers');
            const userCheckboxes = document.querySelectorAll('.user-checkbox');
            const notificationForm = document.getElementById('notificationForm');
            
            // Toggle specific users container based on recipient type
            recipientType.addEventListener('change', function() {
                const value = this.value;
                
                // Hide container by default
                specificUsersContainer.classList.add('d-none');
                studentsList.classList.add('d-none');
                instructorsList.classList.add('d-none');
                
                // Show appropriate list based on selection
                if (value === 'specific_students') {
                    specificUsersContainer.classList.remove('d-none');
                    studentsList.classList.remove('d-none');
                    instructorsList.classList.add('d-none');
                    // Uncheck all checkboxes
                    userCheckboxes.forEach(cb => cb.checked = false);
                    selectAllCheckbox.checked = false;
                } else if (value === 'specific_instructors') {
                    specificUsersContainer.classList.remove('d-none');
                    studentsList.classList.add('d-none');
                    instructorsList.classList.remove('d-none');
                    // Uncheck all checkboxes
                    userCheckboxes.forEach(cb => cb.checked = false);
                    selectAllCheckbox.checked = false;
                }
            });
            
            // Select all users (only in visible list)
            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    // Get the visible list (studentsList or instructorsList)
                    const visibleList = studentsList.classList.contains('d-none') ? instructorsList : studentsList;
                    if (visibleList && !visibleList.classList.contains('d-none')) {
                        const visibleCheckboxes = visibleList.querySelectorAll('.user-checkbox');
                        visibleCheckboxes.forEach(checkbox => {
                            checkbox.checked = this.checked;
                        });
                    }
                });
            }
            
            // Handle form submission
            notificationForm.addEventListener('submit', function(e) {
                const value = recipientType.value;
                
                // Validate specific user selections
                if (value === 'specific_students' || value === 'specific_instructors') {
                    const visibleList = value === 'specific_students' ? studentsList : instructorsList;
                    const visibleCheckboxes = visibleList ? visibleList.querySelectorAll('.user-checkbox:checked') : [];
                    
                    if (visibleCheckboxes.length === 0) {
                        e.preventDefault();
                        const roleType = value === 'specific_students' ? 'student' : 'instructor';
                        alert(`Please select at least one ${roleType}.`);
                        return false;
                    }
                }
                
                // Show loading state
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalBtnText = submitBtn.innerHTML;
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Sending...';
                
                return true;
            });
            
            // Filter users by role
            const filterUsersByRole = (role) => {
                userCheckboxes.forEach(checkbox => {
                    const userRow = checkbox.closest('.form-check');
                    if (role === 'all') {
                        userRow.style.display = '';
                    } else {
                        userRow.style.display = checkbox.dataset.role === role ? '' : 'none';
                    }
                });
            };
            
            // Add role filter buttons
            const roleFilterHtml = `
                <div class="btn-group btn-group-sm mb-3" role="group">
                    <button type="button" class="btn btn-outline-primary filter-btn active" data-role="all">All</button>
                    <button type="button" class="btn btn-outline-primary filter-btn" data-role="student">Students</button>
                    <button type="button" class="btn btn-outline-primary filter-btn" data-role="instructor">Instructors</button>
                </div>
                <div id="usersCheckboxContainer">
            `;
            
            const container = document.getElementById('usersCheckboxContainer');
            container.insertAdjacentHTML('beforebegin', roleFilterHtml);
            
            // Handle role filter clicks
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                    this.classList.add('active');
                    filterUsersByRole(this.dataset.role);
                });
            });
        });
        
    </script>
    <script>
        // Assign Section Modal - Wait for DOM to be ready
        document.addEventListener('DOMContentLoaded', function() {
            const assignSectionModal = document.getElementById('assignSectionModal');
            if (assignSectionModal) {
                assignSectionModal.addEventListener('show.bs.modal', function (event) {
                    const button = event.relatedTarget;
                    const userId = button.getAttribute('data-user-id');
                    const userName = button.getAttribute('data-user-name');
                    const currentSection = button.getAttribute('data-current-section');
                    
                    document.getElementById('assign_user_id').value = userId;
                    document.getElementById('assign_user_name').textContent = userName;
                    document.getElementById('assign_section_id').value = currentSection || '';
                });
            }
        });
        
        // Change Password Modal - Wait for DOM
        document.addEventListener('DOMContentLoaded', function() {
            const changePasswordModal = document.getElementById('changePasswordModal');
            if (!changePasswordModal) {
                console.error('Change Password Modal not found in DOM!');
                return;
            }
            
            changePasswordModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                
                // Get data attributes from the button
                const userId = button.getAttribute('data-user-id');
                const userName = button.getAttribute('data-user-name');
                const schoolId = button.getAttribute('data-school-id');
                
                // Debug: Log what we got from the button
                console.log('Modal opened - Button data:', {
                    userId: userId,
                    userName: userName,
                    schoolId: schoolId,
                    button: button
                });
                
                // Validate we got the user_id
                if (!userId || userId === 'null' || userId === '') {
                    console.error('ERROR: User ID is missing from button data attribute!');
                    alert('Error: User ID is missing. Please refresh the page and try again.');
                    return;
                }
                
                // Store user_id before reset
                const storedUserId = userId;
                
                // Reset form (clears password fields)
                const form = document.getElementById('changePasswordForm');
                if (form) {
                    form.reset();
                    
                    // Immediately restore user_id and display info after reset
                    const userIdField = document.getElementById('change_password_user_id');
                    if (userIdField) {
                        userIdField.value = storedUserId;
                        console.log('User ID set in form field:', userIdField.value);
                    }
                    
                    document.getElementById('change_password_user_name').textContent = userName || '';
                    document.getElementById('change_password_school_id').textContent = schoolId || '';
                    document.getElementById('password_match_text').textContent = '';
                    document.getElementById('password_match_text').className = 'form-text';
                    
                    // Verify the value is actually set
                    setTimeout(() => {
                        const verifyId = document.getElementById('change_password_user_id').value;
                        console.log('Verified User ID in form field:', verifyId);
                        if (!verifyId || verifyId === '') {
                            console.error('WARNING: User ID was lost after form reset!');
                        }
                    }, 100);
                }
            });
            
            // Password match validation
            const newPasswordInput = document.getElementById('new_password');
            const confirmPasswordInput = document.getElementById('confirm_password');
            const passwordMatchText = document.getElementById('password_match_text');
            
            function validatePasswordMatch() {
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                if (confirmPassword.length === 0) {
                    passwordMatchText.textContent = '';
                    passwordMatchText.className = 'form-text';
                    return;
                }
                
                if (newPassword === confirmPassword) {
                    passwordMatchText.textContent = '✓ Passwords match';
                    passwordMatchText.className = 'form-text text-success';
                } else {
                    passwordMatchText.textContent = '✗ Passwords do not match';
                    passwordMatchText.className = 'form-text text-danger';
                }
            }
            
            newPasswordInput.addEventListener('input', validatePasswordMatch);
            confirmPasswordInput.addEventListener('input', validatePasswordMatch);
            
            // Form submission validation
            document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
                const userIdField = document.getElementById('change_password_user_id');
                const userId = userIdField ? userIdField.value : '';
                const newPassword = newPasswordInput.value;
                const confirmPassword = confirmPasswordInput.value;
                
                // Debug: Log all form data
                console.log('Form submission - Form data:', {
                    userId: userId,
                    userIdType: typeof userId,
                    newPasswordLength: newPassword.length,
                    confirmPasswordLength: confirmPassword.length,
                    allFormData: new FormData(this)
                });
                
                // Check if user_id is present
                if (!userId || userId === '' || userId === 'null' || parseInt(userId) <= 0) {
                    e.preventDefault();
                    alert('Error: User ID is missing or invalid. User ID: "' + userId + '". Please close and reopen the modal.');
                    console.error('User ID missing on form submit:', {
                        userId: userId,
                        userIdField: userIdField,
                        fieldValue: userIdField ? userIdField.value : 'field not found'
                    });
                    return false;
                }
                
                if (newPassword.length < 8) {
                    e.preventDefault();
                    alert('Password must be at least 8 characters long.');
                    return false;
                }
                
                if (newPassword !== confirmPassword) {
                    e.preventDefault();
                    alert('Passwords do not match. Please try again.');
                    return false;
                }
                
                // Show confirmation
                if (!confirm(`Are you sure you want to change the password for ${document.getElementById('change_password_user_name').textContent}?`)) {
                    e.preventDefault();
                    return false;
                }
                
                // Debug: Log form data before submission
                console.log('Submitting password change for User ID:', userId);
            });
        });
        
        // Confirm Delete
        function confirmDelete(userId, userName) {
            if (confirm(`Are you sure you want to delete user "${userName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '?action=delete';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'user_id';
                input.value = userId;
                
                form.appendChild(input);
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Show loading indicator
        function showLoadingIndicator() {
            const searchIcon = document.getElementById('search-icon');
            const searchSpinner = document.getElementById('search-spinner');
            const searchBtn = document.getElementById('search-btn');
            
            if (searchIcon && searchSpinner && searchBtn) {
                searchIcon.classList.add('d-none');
                searchSpinner.classList.remove('d-none');
                searchBtn.disabled = true;
            }
        }
        
        // Auto-submit form on filter change
        document.addEventListener('DOMContentLoaded', function() {
            // Only target filter form selects, not modal form selects
            const filterForm = document.getElementById('search-form');
            if (filterForm) {
                const roleSelect = filterForm.querySelector('#role_filter');
                const sectionSelect = filterForm.querySelector('#section');
                const statusSelect = filterForm.querySelector('#status');
                
                [roleSelect, sectionSelect, statusSelect].forEach(select => {
                    if (select) {
                        select.addEventListener('change', function() {
                            // Save scroll position before submitting
                            sessionStorage.setItem('scrollPosition', window.pageYOffset);
                            showLoadingIndicator();
                            this.form.submit();
                        });
                    }
                });
            }
            
            // Add enter key support for search
            const searchInput = document.getElementById('search');
            if (searchInput) {
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        // Save scroll position before submitting
                        sessionStorage.setItem('scrollPosition', window.pageYOffset);
                        showLoadingIndicator();
                        this.form.submit();
                    }
                });
            }
            
            // Add loading indicator for search button
            const searchForm = document.getElementById('search-form');
            if (searchForm) {
                searchForm.addEventListener('submit', function() {
                    showLoadingIndicator();
                });
            }
            
            // Restore scroll position after page load
            const savedScrollPosition = sessionStorage.getItem('scrollPosition');
            if (savedScrollPosition !== null) {
                // Use requestAnimationFrame for smooth scroll restoration
                requestAnimationFrame(() => {
                    window.scrollTo(0, parseInt(savedScrollPosition));
                    sessionStorage.removeItem('scrollPosition');
                });
            }
            
            // Alternative: Scroll to search filters section if no saved position
            if (savedScrollPosition === null && window.location.search.includes('search=') || 
                window.location.search.includes('role=') || 
                window.location.search.includes('section=') || 
                window.location.search.includes('status=')) {
                const searchSection = document.getElementById('search-filters');
                if (searchSection) {
                    searchSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
        });
    </script>
    
    
     </main>
    
    <!-- All Modals - Moved outside main container to fix Bootstrap modal positioning issues -->
    <!-- Bulk Registration Modal -->
    <div class="modal fade" id="bulkRegisterModal" tabindex="-1" aria-labelledby="bulkRegisterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bulkRegisterModalLabel">
                        <i class="bi bi-upload me-2" ></i>CSV Registration
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close" ></button>
                </div>
                <form method="POST" action="?action=bulk_register" enctype="multipart/form-data">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="user_type" class="form-label">User Type</label>
                            <select class="form-select" id="user_type" name="user_type" required>
                                <option value="">Select user type</option>
                                <option value="student">Student</option>
                                <option value="instructor">Instructor</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="csv_file" class="form-label">CSV File</label>
                            <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                            <div class="form-text">
                                CSV format: school_id, email, full_name, gender, contact, facebook_name, section_id
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-upload me-1"></i>Upload & Register
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Manual Registration Modal -->
    <div class="modal fade" id="manualRegisterModal" tabindex="-1" aria-labelledby="manualRegisterModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="manualRegisterModalLabel">
                        <i class="bi bi-person-plus me-2"></i>Add New User
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="?action=register">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="school_id" class="form-label">School ID</label>
                                <input type="text" class="form-control" id="school_id" name="school_id" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="full_name" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="full_name" name="full_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="role" class="form-label">Role</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="">Select role</option>
                                    <option value="student">Student</option>
                                    <option value="instructor">Instructor</option>
                                    <option value="admin">Admin</option>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="gender" class="form-label">Gender</label>
                                <select class="form-select" id="gender" name="gender">
                                    <option value="">Select gender</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="non-binary">Non-binary</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="contact" class="form-label">Contact</label>
                                <input type="text" class="form-control" id="contact" name="contact">
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="facebook_name" class="form-label">Facebook Name</label>
                            <input type="text" class="form-control" id="facebook_name" name="facebook_name">
                        </div>
                        <div class="mb-3">
                            <label for="section_id" class="form-label">Section</label>
                            <select class="form-select" id="section_id" name="section_id">
                                <option value="">No section</option>
                                <?php foreach ($sections as $section): ?>
                                <option value="<?= $section['id'] ?>">
                                    <?= htmlspecialchars($section['section_code']) ?> - <?= htmlspecialchars($section['section_name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" 
                                   value="Password@2025" required>
                            <div class="form-text">Default password: Password@2025</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-person-plus me-1"></i>Create User
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Compose Notification Modal (Gmail Style) -->
    <div class="modal fade" id="composeNotificationModal" tabindex="-1" aria-labelledby="composeNotificationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" style="max-width: 800px;">
            <div class="modal-content">
                <div class="modal-header bg-light border-bottom">
                    <h5 class="modal-title" id="composeNotificationModalLabel">
                        <i class="bi bi-envelope me-2"></i>New Message
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="notificationForm" method="POST">
                    <input type="hidden" name="action" value="send_notification">
                    <div class="modal-body p-0" style="max-height: 70vh; overflow-y: auto;">
                        <!-- To Field (Gmail Style) -->
                        <div class="border-bottom p-3">
                            <div class="d-flex align-items-center">
                                <label for="recipientType" class="form-label mb-0 me-2 fw-bold" style="min-width: 60px; flex-shrink: 0;">To:</label>
                                <select class="form-select border-0 shadow-none" id="recipientType" name="recipient_type" required style="font-size: 0.95rem; flex: 1;">
                                    <option value="">Select recipients...</option>
                                    <option value="all_students">All Students</option>
                                    <option value="all_instructors">All Instructors</option>
                                    <option value="all_students_instructors">All Students & Instructors</option>
                                    <option value="specific_students">Specific Students</option>
                                    <option value="specific_instructors">Specific Instructors</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Specific Users Selection -->
                        <div id="specificUsersContainer" class="border-bottom p-3 d-none" style="background-color: #f8f9fa;">
                            <label class="form-label fw-bold mb-2">Select Users:</label>
                            <div class="border rounded p-2" style="max-height: 250px; overflow-y: auto; overflow-x: hidden; background-color: white;">
                                <div class="form-check mb-2 pb-2 border-bottom">
                                    <input class="form-check-input select-all" type="checkbox" id="selectAllUsers">
                                    <label class="form-check-label fw-bold" for="selectAllUsers">
                                        Select All
                                    </label>
                                </div>
                                <div id="usersCheckboxContainer">
                                    <?php 
                                    // Get students
                                    $students = $pdo->query("SELECT id, full_name, school_id FROM users WHERE role = 'student' AND email IS NOT NULL ORDER BY full_name")->fetchAll();
                                    // Get instructors
                                    $instructors = $pdo->query("SELECT id, full_name, school_id FROM users WHERE role = 'instructor' AND email IS NOT NULL ORDER BY full_name")->fetchAll();
                                    ?>
                                    <div id="studentsList" class="d-none">
                                        <?php foreach ($students as $student): ?>
                                            <div class="form-check">
                                                <input class="form-check-input user-checkbox" type="checkbox" name="specific_users[]" value="<?= $student['id'] ?>" data-role="student">
                                                <label class="form-check-label" style="word-wrap: break-word; overflow-wrap: break-word;">
                                                    <?= htmlspecialchars($student['full_name']) ?> <small class="text-muted">(<?= htmlspecialchars($student['school_id']) ?>)</small>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div id="instructorsList" class="d-none">
                                        <?php foreach ($instructors as $instructor): ?>
                                            <div class="form-check">
                                                <input class="form-check-input user-checkbox" type="checkbox" name="specific_users[]" value="<?= $instructor['id'] ?>" data-role="instructor">
                                                <label class="form-check-label" style="word-wrap: break-word; overflow-wrap: break-word;">
                                                    <?= htmlspecialchars($instructor['full_name']) ?> <small class="text-muted">(<?= htmlspecialchars($instructor['school_id']) ?>)</small>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Subject Field -->
                        <div class="border-bottom p-3">
                            <div class="d-flex align-items-center">
                                <label for="subject" class="form-label mb-0 me-2 fw-bold" style="min-width: 60px; flex-shrink: 0;">Subject:</label>
                                <input type="text" class="form-control border-0 shadow-none" id="subject" name="subject" placeholder="Enter subject..." required style="font-size: 0.95rem; flex: 1;">
                            </div>
                        </div>
                        
                        <!-- Message Field -->
                        <div class="p-3">
                            <textarea class="form-control border-0 shadow-none" id="message" name="message" rows="10" placeholder="Enter your message here..." required style="font-size: 0.95rem; resize: vertical; min-height: 200px;"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-top" style="position: sticky; bottom: 0; z-index: 1; margin: 0; border-top: 1px solid #dee2e6;">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="bi bi-x-circle me-1"></i>Discard
                        </button>
                        <button type="submit" class="btn btn-primary btn-sm">
                            <i class="bi bi-send me-1"></i>Send
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Assign Section Modal -->
    <div class="modal fade" id="assignSectionModal" tabindex="-1" aria-labelledby="assignSectionModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="assignSectionModalLabel">
                        <i class="bi bi-gear me-2"></i>Assign Section
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="?action=assign_section">
                    <div class="modal-body">
                        <input type="hidden" id="assign_user_id" name="user_id">
                        <div class="mb-3">
                            <label class="form-label">User</label>
                            <p class="form-control-plaintext" id="assign_user_name"></p>
                        </div>
                        <div class="mb-3">
                            <label for="assign_section_id" class="form-label">Section</label>
                            <select class="form-select" id="assign_section_id" name="section_id">
                                <option value="">No section</option>
                                <?php foreach ($sections as $section): ?>
                                <option value="<?= $section['id'] ?>">
                                    <?= htmlspecialchars($section['section_code'] ?? '') ?> - <?= htmlspecialchars($section['section_name'] ?? 'Unnamed Section') ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-gear me-1"></i>Assign Section
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">
                        <i class="bi bi-key me-2"></i>Change Password
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="changePasswordForm" action="users.php?action=change_password">
                    <input type="hidden" name="action" value="change_password">
                    <input type="hidden" id="change_password_user_id" name="user_id" value="">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Note:</strong> This will reset the user's password. They will need to use the new password to login.
                        </div>
                        <div class="mb-3">
                            <label class="form-label">User</label>
                            <p class="form-control-plaintext fw-bold" id="change_password_user_name"></p>
                            <small class="text-muted" id="change_password_school_id"></small>
                        </div>
                        <div class="mb-3">
                            <label for="new_password" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                   placeholder="Enter new password" required minlength="8">
                            <div class="form-text">Password must be at least 8 characters long.</div>
                        </div>
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                   placeholder="Confirm new password" required minlength="8">
                            <div class="form-text" id="password_match_text"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-key me-1"></i>Change Password
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Archive All Users Confirmation Modal -->
    <div class="modal fade" id="archiveAllModal" tabindex="-1" aria-labelledby="archiveAllModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title" id="archiveAllModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Archive All Users
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Warning:</strong> This action will archive all users except administrators.
                    </div>
                    <p>This action is typically used when a batch of students completes their OJT and you want to start fresh with a new batch.</p>
                    <p class="mb-0"><strong>What will happen:</strong></p>
                    <ul>
                        <li>All students and instructors will be archived</li>
                        <li>Admin users will <strong>NOT</strong> be archived</li>
                        <li>Archived users will be hidden from the main user list</li>
                        <li>You can view archived users by checking "Show Archived Users" in the filter</li>
                        <li>This action cannot be easily undone</li>
                    </ul>
                    <p class="text-danger mt-3 mb-0"><strong>Are you sure you want to proceed?</strong></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <form method="POST" action="users.php?action=archive_all" style="display: inline;">
                        <button type="submit" class="btn btn-warning">
                            <i class="bi bi-archive me-1"></i>Yes, Archive All Users
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModalUsers" tabindex="-1" aria-labelledby="logoutModalUsersLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutModalUsersLabel">
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
</body>
</html>

