<?php

/**
 * Student Profile Page
 * OJT Route - Student profile management with workplace setup
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Services\FileUploadService;
use App\Services\WorkplaceEditRequestService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;

// Start session
session_start();

// Initialize services
$authService = new AuthenticationService();
$fileUploadService = new FileUploadService();
$workplaceEditRequestService = new WorkplaceEditRequestService();
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
$userId = is_array($user) ? $user['id'] : $user->id;

// Fetch complete user data from users table
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

// If user data is not found in database, use session data as fallback
if (!$userData) {
    $userData = [
        'id' => $userId,
        'full_name' => $_SESSION['full_name'] ?? 'Student',
        'email' => $_SESSION['email'] ?? '',
        'school_id' => $_SESSION['school_id'] ?? '',
        'role' => $_SESSION['role'] ?? 'student',
        'contact' => $_SESSION['contact'] ?? '',
        'facebook_name' => $_SESSION['facebook_name'] ?? '',
        'profile_picture' => $_SESSION['profile_picture'] ?? null
    ];
}

// Get student profile
$stmt = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload_profile_picture') {
        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $result = $fileUploadService->uploadProfilePicture($_FILES['profile_picture'], $userId);
            
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
                // Update session with new profile picture path
                if (isset($result['profile_picture_path'])) {
                    $_SESSION['profile_picture'] = $result['profile_picture_path'];
                }
            } else {
                $_SESSION['error'] = $result['message'];
            }
            
            header('Location: profile.php');
            exit;
        } else {
            $_SESSION['error'] = 'No file uploaded or upload error occurred.';
            header('Location: profile.php');
            exit;
        }
    } elseif ($action === 'delete_profile_picture') {
        // Handle profile picture deletion
        $result = $fileUploadService->deleteProfilePicture($userId);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
            // Clear profile picture from session
            unset($_SESSION['profile_picture']);
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        header('Location: profile.php');
        exit;
    } elseif ($action === 'update_basic_profile') {
        // Handle basic profile information update
        $full_name = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $contact = trim($_POST['contact'] ?? '');
        $facebook_name = trim($_POST['facebook_name'] ?? '');
        
        $errors = [];
        
        // Validation
        if (empty($full_name)) {
            $errors[] = 'Full name is required.';
        }
        
        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        
        // Check for duplicate email (excluding current user)
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $userId]);
            if ($stmt->fetch()) {
                $errors[] = 'Email already in use by another account.';
            }
        }
        
        if (empty($errors)) {
            // Update profile
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, contact = ?, facebook_name = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $contact, $facebook_name, $userId]);
            
            // Log activity
            $schoolId = is_array($user) ? $user['school_id'] : $user->school_id;
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
            $stmt->execute([
                $userId,
                'profile_update',
                "User {$schoolId} updated profile"
            ]);
            
            // Update session user data
            $_SESSION['full_name'] = $full_name;
            $_SESSION['email'] = $email;
            $_SESSION['contact'] = $contact;
            $_SESSION['facebook_name'] = $facebook_name;
            
            // Update the userData array for display
            $userData['full_name'] = $full_name;
            $userData['email'] = $email;
            $userData['contact'] = $contact;
            $userData['facebook_name'] = $facebook_name;
            
            $_SESSION['success'] = 'Profile updated successfully!';
            header('Location: profile.php');
            exit;
        } else {
            $_SESSION['error'] = implode('<br>', $errors);
            header('Location: profile.php');
            exit;
        }
        
    } elseif ($action === 'change_password') {
        // Handle password change
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        $errors = [];
        
        // Validation
        if (empty($current_password)) {
            $errors[] = 'Current password is required.';
        } else {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $userData = $stmt->fetch();
            if (!$userData || !password_verify($current_password, $userData['password_hash'])) {
                $errors[] = 'Current password is incorrect.';
            }
        }
        
        if (empty($new_password)) {
            $errors[] = 'New password is required.';
        } elseif (strlen($new_password) < 8) {
            $errors[] = 'New password must be at least 8 characters long.';
        }
        
        if ($new_password !== $confirm_password) {
            $errors[] = 'New password and confirmation do not match.';
        }
        
        if (empty($errors)) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $userId]);
            
            // Log activity
            $schoolId = is_array($user) ? $user['school_id'] : $user->school_id;
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
            $stmt->execute([
                $userId,
                'password_change',
                "User {$schoolId} changed password"
            ]);
            
            $_SESSION['success'] = 'Password changed successfully!';
            header('Location: profile.php');
            exit;
        } else {
            $_SESSION['error'] = implode('<br>', $errors);
            header('Location: profile.php');
            exit;
        }
        
    } elseif ($action === 'update_profile') {
        // Check if workplace location is locked and no approved edit request
        $hasApprovedEditRequest = $requestStatus['workplace_edit_request_status'] === 'approved';
        
        if ($profile && $profile['workplace_location_locked'] && !$hasApprovedEditRequest) {
            $_SESSION['error'] = 'Workplace information has already been set. Contact your instructor or admin to make changes.';
            header('Location: profile.php');
            exit;
        }
        
        $profileData = [
            'workplace_name' => trim($_POST['workplace_name'] ?? ''),
            'supervisor_name' => trim($_POST['supervisor_name'] ?? ''),
            'company_head' => trim($_POST['company_head'] ?? ''),
            'student_position' => trim($_POST['student_position'] ?? ''),
            'ojt_start_date' => $_POST['ojt_start_date'] ?? '',
            'workplace_latitude' => $_POST['workplace_latitude'] ?? null,
            'workplace_longitude' => $_POST['workplace_longitude'] ?? null,
            'workplace_location_locked' => 1 // Always lock after saving (one-time setup rule)
        ];
        
        try {
            if ($profile) {
                // Update existing profile
                $stmt = $pdo->prepare("
                    UPDATE student_profiles SET 
                        workplace_name = ?, supervisor_name = ?, company_head = ?, 
                        student_position = ?, ojt_start_date = ?, workplace_latitude = ?, 
                        workplace_longitude = ?, workplace_location_locked = ?, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $result = $stmt->execute([
                    $profileData['workplace_name'], $profileData['supervisor_name'], 
                    $profileData['company_head'], $profileData['student_position'], 
                    $profileData['ojt_start_date'], $profileData['workplace_latitude'], 
                    $profileData['workplace_longitude'], $profileData['workplace_location_locked'], 
                    $userId
                ]);
                
                // If this was an approved edit request, reset the request status
                if ($hasApprovedEditRequest) {
                    $stmt = $pdo->prepare("
                        UPDATE student_profiles SET 
                            workplace_edit_request_status = 'none',
                            workplace_edit_request_date = NULL,
                            workplace_edit_request_reason = NULL,
                            workplace_edit_approved_by = NULL,
                            workplace_edit_approved_at = NULL,
                            workplace_edit_hours_decision = NULL
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$userId]);
                }
            } else {
                // Create new profile
                $stmt = $pdo->prepare("
                    INSERT INTO student_profiles 
                    (user_id, workplace_name, supervisor_name, company_head, student_position, 
                     ojt_start_date, workplace_latitude, workplace_longitude, workplace_location_locked, 
                     total_hours_accumulated, status, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 'on_track', NOW(), NOW())
                ");
                $stmt->execute([
                    $userId, $profileData['workplace_name'], $profileData['supervisor_name'], 
                    $profileData['company_head'], $profileData['student_position'], 
                    $profileData['ojt_start_date'], $profileData['workplace_latitude'], 
                    $profileData['workplace_longitude'], $profileData['workplace_location_locked']
                ]);
            }
            
            $_SESSION['success'] = 'Profile updated successfully!';
            header('Location: profile.php');
            exit;
            
        } catch (Exception $e) {
            $_SESSION['error'] = 'Error updating profile: ' . $e->getMessage();
        }
    } elseif ($action === 'submit_workplace_edit_request') {
        // Handle workplace edit request submission
        $reason = trim($_POST['request_reason'] ?? '');
        
        $result = $workplaceEditRequestService->submitRequest($userId, $reason);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        header('Location: profile.php');
        exit;
    }
}

// Get updated profile after potential changes
$stmt = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
$stmt->execute([$userId]);
$profile = $stmt->fetch();

// Get workplace edit request status
$requestStatus = $workplaceEditRequestService->getRequestStatus($userId);

// If workplace is locked and there's an old approved request, clear it
if ($profile && $profile['workplace_location_locked'] && $requestStatus['workplace_edit_request_status'] === 'approved') {
    // Clear the approved request since workplace is already locked
    $stmt = $pdo->prepare("
        UPDATE student_profiles SET 
            workplace_edit_request_status = 'none',
            workplace_edit_request_date = NULL,
            workplace_edit_request_reason = NULL,
            workplace_edit_approved_by = NULL,
            workplace_edit_approved_at = NULL,
            workplace_edit_hours_decision = NULL
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    
    // Refresh request status
    $requestStatus = $workplaceEditRequestService->getRequestStatus($userId);
}

// Debug request status
if (isset($_GET['debug'])) {
    echo "<div class='alert alert-warning'>";
    echo "<strong>Request Status Debug:</strong><br>";
    echo "Request Status: " . ($requestStatus['workplace_edit_request_status'] ?? 'none') . "<br>";
    echo "Request Date: " . ($requestStatus['workplace_edit_request_date'] ?? 'null') . "<br>";
    echo "Approved At: " . ($requestStatus['workplace_edit_approved_at'] ?? 'null') . "<br>";
    echo "</div>";
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <link rel="icon" type="image/png" href="../images/CHMSU.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <style>
        :root {
            --chmsu-green: #0ea539;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
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
        
        .navbar {
            background: var(--chmsu-green) !important;
        }
        
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .form-control:focus {
            border-color: var(--chmsu-green);
            box-shadow: 0 0 0 0.2rem rgba(14, 165, 57, 0.25);
        }
        
        .btn-primary {
            background-color: var(--chmsu-green);
            border-color: var(--chmsu-green);
        }
        
        .btn-primary:hover {
            background-color: #0d8a2f;
            border-color: #0d8a2f;
        }
        
        #map {
            height: 300px;
            border-radius: 10px;
        }
        
        .workplace-status {
            border-left: 4px solid #dc3545;
            background-color: #f8d7da;
        }
        
        .workplace-status.complete {
            border-left-color: #28a745;
            background-color: #d4edda;
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
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">   
                    
                <!-- Workplace Status -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card workplace-status <?= $profile && $profile['workplace_location_locked'] ? 'complete' : '' ?>">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-geo-alt me-2"></i>Workplace Information And Documents Status
                        </h5>
                        <?php if ($profile && $profile['workplace_location_locked']): ?>
                            <p class="card-text text-success">
                                <i class="bi bi-check-circle me-2"></i>Workplace location is set and locked. You can now mark attendance.
                            </p>
                        <?php else: ?>
                            <p class="card-text text-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>Complete your workplace information below and pass all the required documents after to enable attendance features.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
                    
                    <!-- Success/Error Messages -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-fixed alert-auto-dismiss" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-check-circle me-2"></i>
                                <span><?= htmlspecialchars($_SESSION['success']) ?></span>
                                <button type="button" class="btn-close ms-auto" onclick="dismissAlert(this.parentElement.parentElement)"></button>
                        </div>
                        </div>
                        <script>setTimeout(() => document.querySelector('.alert-fixed').remove(), 5000);</script>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-fixed alert-auto-dismiss" role="alert">
                            <div class="d-flex align-items-center">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <span><?= htmlspecialchars($_SESSION['error']) ?></span>
                                <button type="button" class="btn-close ms-auto" onclick="dismissAlert(this.parentElement.parentElement)"></button>
                        </div>
                        </div>
                        <script>setTimeout(() => { const alert = document.querySelector('.alert-fixed'); if(alert) alert.remove(); }, 5000);</script>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <!-- Basic Profile Information -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="bi bi-person me-2"></i>Basic Profile Information
                                    </h5>

                                    <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="bi bi-camera me-2"></i>Profile Picture
                                    </h5>
                                    <div class="row align-items-center">
                                        <div class="col-md-3 text-center">
                                            <?php 
                                            $profilePictureUrl = $fileUploadService->getProfilePictureUrl($userData['profile_picture'] ?? null);
                                            ?>
                                            <img src="<?= $profilePictureUrl ?>" 
                                                 alt="Profile Picture" 
                                                 class="img-thumbnail rounded-circle mb-3" 
                                                 style="width: 120px; height: 120px; object-fit: cover;">
                                        </div>
                                        <div class="col-md-9">
                                            <form method="POST" enctype="multipart/form-data" class="mb-3">
                                                <input type="hidden" name="action" value="upload_profile_picture">
                                                <div class="mb-3">
                                                    <label for="profile_picture" class="form-label">Upload New Picture</label>
                                                    <input type="file" 
                                                           class="form-control" 
                                                           id="profile_picture" 
                                                           name="profile_picture" 
                                                           accept="image/*"
                                                           required>
                                                    <div class="form-text">
                                                        Supported formats: JPEG, PNG, GIF, WebP. Maximum size: 5MB.
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-upload me-2" style="color:white;"></i>Upload Picture
                                                </button>
                                            </form>
                                            
                                            <?php if ($userData['profile_picture'] ?? null): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete_profile_picture">
                                                    <button type="submit" 
                                                            class="btn btn-outline-danger btn-sm"
                                                            onclick="return confirm('Are you sure you want to delete your profile picture?')">
                                                        <i class="bi bi-trash me-2"></i>Delete Picture
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                    <form method="POST" id="basicProfileForm">
                                        <input type="hidden" name="action" value="update_basic_profile">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="full_name" class="form-label">Full Name *</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="full_name" 
                                                           name="full_name" 
                                                           value="<?= htmlspecialchars($userData['full_name'] ?? '') ?>" 
                                                           required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">School ID</label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($userData['school_id'] ?? '') ?>" readonly>
                                                    <div class="form-text">School ID cannot be changed</div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="email" class="form-label">Email *</label>
                                                    <input type="email" 
                                                           class="form-control" 
                                                           id="email" 
                                                           name="email" 
                                                           value="<?= htmlspecialchars($userData['email'] ?? '') ?>" 
                                                           required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Role</label>
                                                    <input type="text" class="form-control" value="<?= ucfirst($userData['role'] ?? '') ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="contact" class="form-label">Contact</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="contact" 
                                                           name="contact" 
                                                           value="<?= htmlspecialchars($userData['contact'] ?? '') ?>" 
                                                           placeholder="Phone number (optional)">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="facebook_name" class="form-label">Facebook Name</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="facebook_name" 
                                                           name="facebook_name" 
                                                           value="<?= htmlspecialchars($userData['facebook_name'] ?? '') ?>" 
                                                           placeholder="Facebook name (optional)">
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-save me-2"></i>Update Profile
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary" onclick="resetBasicForm()">
                                                <i class="bi bi-arrow-clockwise me-2"></i>Reset
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Password Change Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="bi bi-shield-lock me-2"></i>Change Password
                                    </h5>
                                    <button class="btn btn-outline-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#passwordForm" aria-expanded="false">
                                        <i class="bi bi-key me-2"></i>Change Password
                                    </button>
                                    
                                    <div class="collapse" id="passwordForm">
                                        <form method="POST" id="passwordChangeForm">
                                            <input type="hidden" name="action" value="change_password">
                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label for="current_password" class="form-label">Current Password *</label>
                                                        <input type="password" 
                                                               class="form-control" 
                                                               id="current_password" 
                                                               name="current_password" 
                                                               required>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label for="new_password" class="form-label">New Password *</label>
                                                        <input type="password" 
                                                               class="form-control" 
                                                               id="new_password" 
                                                               name="new_password" 
                                                               minlength="8"
                                                               required>
                                                        <div class="form-text">Minimum 8 characters</div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label for="confirm_password" class="form-label">Confirm New Password *</label>
                                                        <input type="password" 
                                                               class="form-control" 
                                                               id="confirm_password" 
                                                               name="confirm_password" 
                                                               required>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="d-flex gap-2">
                                                <button type="submit" class="btn btn-warning">
                                                    <i class="bi bi-shield-check me-2"></i>Change Password
                                                </button>
                                                <button type="button" class="btn btn-outline-secondary" onclick="resetPasswordForm()">
                                                    <i class="bi bi-arrow-clockwise me-2"></i>Reset
                                                </button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                
                    
                    <!-- Profile Picture Section -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Form -->
                    <div class="row">
                        <div class="col-lg-8">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="bi bi-building me-2"></i>Workplace Information
                                    </h5>
                                    
                                    <?php 
                                    $isWorkplaceLocked = $profile && $profile['workplace_location_locked'];
                                    $hasApprovedEditRequest = $requestStatus['workplace_edit_request_status'] === 'approved';
                                    
                                    // Simple logic: if workplace is locked, disable form unless there's an approved request
                                    if ($isWorkplaceLocked) {
                                        $isFormDisabled = !$hasApprovedEditRequest;
                                    } else {
                                        $isFormDisabled = false;
                                    }
                                    
                                    // Debug information (remove in production)
                                    if (isset($_GET['debug'])) {
                                        echo "<div class='alert alert-info'>";
                                        echo "<strong>Debug Info:</strong><br>";
                                        echo "isWorkplaceLocked: " . ($isWorkplaceLocked ? 'true' : 'false') . "<br>";
                                        echo "hasApprovedEditRequest: " . ($hasApprovedEditRequest ? 'true' : 'false') . "<br>";
                                        echo "isFormDisabled: " . ($isFormDisabled ? 'true' : 'false') . "<br>";
                                        echo "Request Status: " . ($requestStatus['workplace_edit_request_status'] ?? 'none') . "<br>";
                                        echo "Workplace Locked Value: " . ($profile['workplace_location_locked'] ?? 'null') . "<br>";
                                        echo "Request Status !== 'approved': " . (($requestStatus['workplace_edit_request_status'] ?? 'none') !== 'approved' ? 'true' : 'false') . "<br>";
                                        echo "</div>";
                                    }
                                    ?>
                                    
                                    <!-- Workplace Edit Request Status -->
                                    <?php if ($requestStatus['workplace_edit_request_status'] !== 'none'): ?>
                                        <div class="alert alert-<?= $requestStatus['workplace_edit_request_status'] === 'pending' ? 'warning' : ($requestStatus['workplace_edit_request_status'] === 'approved' ? 'success' : 'danger') ?> mb-3">
                                            <div class="d-flex align-items-center">
                                                <i class="bi bi-<?= $requestStatus['workplace_edit_request_status'] === 'pending' ? 'clock' : ($requestStatus['workplace_edit_request_status'] === 'approved' ? 'check-circle' : 'x-circle') ?> me-2"></i>
                                                <div>
                                                    <strong>
                                                        <?php if ($requestStatus['workplace_edit_request_status'] === 'pending'): ?>
                                                            Edit Request Pending
                                                        <?php elseif ($requestStatus['workplace_edit_request_status'] === 'approved'): ?>
                                                            Edit Request Approved
                                                        <?php else: ?>
                                                            Edit Request Denied
                                                        <?php endif; ?>
                                                    </strong>
                                                    <br>
                                                    <small>
                                                        <?php if ($requestStatus['workplace_edit_request_status'] === 'pending'): ?>
                                                            Your request was submitted on <?= date('M d, Y g:i A', strtotime($requestStatus['workplace_edit_request_date'])) ?>. Waiting for instructor approval.
                                                        <?php elseif ($requestStatus['workplace_edit_request_status'] === 'approved'): ?>
                                                            You can now edit your workplace information. Hours decision: <?= $requestStatus['workplace_edit_hours_decision'] === 'keep' ? 'Keep existing hours' : 'Reset to zero' ?>.
                                                        <?php else: ?>
                                                            Your request was denied on <?= date('M d, Y g:i A', strtotime($requestStatus['workplace_edit_approved_at'])) ?>.
                                                        <?php endif; ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($isWorkplaceLocked): ?>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle me-2"></i>
                                            <strong>Workplace location is locked.</strong> Contact your instructor or admin to make changes.
                                        </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" id="profileForm" <?= $isFormDisabled ? 'onsubmit="return false;"' : '' ?>>
                                        <script>
                                        document.addEventListener('DOMContentLoaded', function() {
                                            const form = document.getElementById('profileForm');
                                            console.log('Form setup - isFormDisabled:', isFormDisabled);
                                            if (form && isFormDisabled) {
                                                console.log('Adding form submit protection');
                                                form.addEventListener('submit', function(e) {
                                                    console.log('Form submit attempted but DISABLED - showing alert');
                                                    e.preventDefault();
                                                    alert('Workplace information has already been set. Contact your instructor or admin to make changes.');
                                                    return false;
                                                });
                                            } else {
                                                console.log('Form is ENABLED - no submit protection');
                                            }
                                        });
                                        </script>
                                        <input type="hidden" name="action" value="update_profile">
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="workplace_name" class="form-label">Company/Workplace Name *</label>
                                                <input type="text" class="form-control" id="workplace_name" name="workplace_name" 
                                                       value="<?= htmlspecialchars($profile['workplace_name'] ?? '') ?>" 
                                                       <?= $isFormDisabled ? 'readonly' : 'required' ?>>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="supervisor_name" class="form-label">Supervisor Name *</label>
                                                <input type="text" class="form-control" id="supervisor_name" name="supervisor_name" 
                                                       value="<?= htmlspecialchars($profile['supervisor_name'] ?? '') ?>" 
                                                       <?= $isFormDisabled ? 'readonly' : 'required' ?>>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="company_head" class="form-label">Company Head</label>
                                                <input type="text" class="form-control" id="company_head" name="company_head" 
                                                       value="<?= htmlspecialchars($profile['company_head'] ?? '') ?>" 
                                                       <?= $isFormDisabled ? 'readonly' : '' ?>>
                                            </div>
                                            
                                            <div class="col-md-6 mb-3">
                                                <label for="student_position" class="form-label">Your Position *</label>
                                                <input type="text" class="form-control" id="student_position" name="student_position" 
                                                       value="<?= htmlspecialchars($profile['student_position'] ?? '') ?>" 
                                                       <?= $isFormDisabled ? 'readonly' : 'required' ?>>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label for="ojt_start_date" class="form-label">OJT Start Date *</label>
                                                <input type="date" class="form-control" id="ojt_start_date" name="ojt_start_date" 
                                                       value="<?= htmlspecialchars($profile['ojt_start_date'] ?? '') ?>" 
                                                       <?= $isFormDisabled ? 'readonly' : 'required' ?>>
                                            </div>
                                            
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Workplace Location *</label>
                                            <p class="text-muted small">
                                                <?php if ($isFormDisabled): ?>
                                                    Your workplace location is set and cannot be changed.
                                                <?php else: ?>
                                                    Click on the map to set your workplace location for attendance tracking.
                                                <?php endif; ?>
                                            </p>
                                            
                                            <?php if (!$isFormDisabled): ?>
                                            <div class="mb-2">
                                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="getCurrentLocation()">
                                                    <i class="bi bi-geo-alt me-1"></i>Set My Location
                                                </button>
                                                <small class="text-muted ms-2">Use GPS to center map on your current location</small>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div id="map" <?= $isFormDisabled ? 'style="pointer-events: none; opacity: 0.6; position: relative;"' : '' ?>>
                                                <?php if ($isFormDisabled): ?>
                                                <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(0,0,0,0.8); color: white; padding: 20px; border-radius: 10px; text-align: center; z-index: 1000;">
                                                    <i class="bi bi-lock-fill" style="font-size: 2rem; margin-bottom: 10px;"></i>
                                                    <div><strong>Location Locked</strong></div>
                                                    <small>Contact your instructor to make changes</small>
                                                </div>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <!-- GPS Accuracy Indicator -->
                                            <div id="gps-accuracy" class="mt-2" style="display: none;">
                                                <div class="alert alert-warning alert-sm">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                                    <span id="accuracy-message"></span>
                                                </div>
                                            </div>
                                            
                                            <!-- Coordinates Display -->
                                            <div class="mt-2">
                                                <small class="text-muted">
                                                    <strong>Coordinates:</strong> 
                                                    <span id="coordinates-display">
                                                        <?php if ($profile && $profile['workplace_latitude'] && $profile['workplace_longitude']): ?>
                                                            Lat: <?= htmlspecialchars($profile['workplace_latitude']) ?>, 
                                                            Long: <?= htmlspecialchars($profile['workplace_longitude']) ?>
                                                        <?php else: ?>
                                                            Click on map to set location
                                                        <?php endif; ?>
                                                    </span>
                                                </small>
                                            </div>
                                            
                                            <input type="hidden" id="workplace_latitude" name="workplace_latitude" 
                                                   value="<?= htmlspecialchars($profile['workplace_latitude'] ?? '') ?>">
                                            <input type="hidden" id="workplace_longitude" name="workplace_longitude" 
                                                   value="<?= htmlspecialchars($profile['workplace_longitude'] ?? '') ?>">
                                        </div>
                                        
                                        <div class="d-flex gap-2">
                                            <?php if (!$isFormDisabled): ?>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-save me-2"></i>Save Profile
                                                </button>
                                            <?php else: ?>
                                                <button type="button" class="btn btn-secondary" disabled>
                                                    <i class="bi bi-lock me-2"></i>Profile Locked
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($isWorkplaceLocked && $requestStatus['workplace_edit_request_status'] === 'none'): ?>
                                                <button type="button" class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#workplaceEditRequestModal">
                                                    <i class="bi bi-pencil-square me-2" style="color:#0ea539;"></i>Request Edit
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-lg-4">
                            <div class="card">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="bi bi-info-circle me-2"></i>Profile Information
                                    </h5>
                                    <p class="card-text">
                                        <strong>Name:</strong> <?= htmlspecialchars($userData['full_name'] ?? $userData['school_id'] ?? 'Student') ?><br>
                                        <strong>School ID:</strong> <?= htmlspecialchars($userData['school_id'] ?? '') ?><br>
                                        <strong>Email:</strong> <?= htmlspecialchars($userData['email'] ?? '') ?><br>
                                        <strong>Section:</strong> <?= htmlspecialchars($userData['section_name'] ?? 'Not assigned') ?>
                                    </p>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="bi bi-geo-alt me-2"></i>Location Status
                                    </h5>
                                    <?php if ($profile && $profile['workplace_latitude'] && $profile['workplace_longitude']): ?>
                                        <p class="text-success">
                                            <i class="bi bi-check-circle me-2"></i>Location Set
                                        </p>
                                        <p class="small text-muted">
                                            Lat: <?= htmlspecialchars($profile['workplace_latitude']) ?><br>
                                            Lng: <?= htmlspecialchars($profile['workplace_longitude']) ?>
                                        </p>
                                    <?php else: ?>
                                        <p class="text-danger">
                                            <i class="bi bi-exclamation-triangle me-2"></i>Location Not Set
                                        </p>
                                        <p class="small text-muted">
                                            Set your workplace location to enable attendance features.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    
    <!-- Logout Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Logout</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    Are you sure you want to logout?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="../logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Alert dismiss function
        function dismissAlert(alertElement) {
            alertElement.style.animation = 'fadeOut 0.3s ease-in forwards';
            setTimeout(() => alertElement.remove(), 300);
        }
        
        // Initialize map
        let map;
        let marker;
        let currentLocation = null;
        
        // Get form state from PHP
        const isFormDisabled = <?= $isFormDisabled ? 'true' : 'false' ?>;
        const isWorkplaceLocked = <?= $isWorkplaceLocked ? 'true' : 'false' ?>;
        
        // Debug logging
        console.log('Debug Info:');
        console.log('isFormDisabled:', isFormDisabled);
        console.log('isWorkplaceLocked:', isWorkplaceLocked);
        console.log('workplace_location_locked from DB:', <?= $profile['workplace_location_locked'] ?? 0 ?>);
        
        // Get current location using GPS
        function getCurrentLocation() {
            // Check if form is disabled
            if (isFormDisabled) {
                alert('Workplace location is locked. Contact your instructor to make changes.');
                return;
            }
            
            if (!navigator.geolocation) {
                alert('Geolocation is not supported by this browser.');
                return;
            }
            
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Getting Location...';
            button.disabled = true;
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    const accuracy = position.coords.accuracy;
                    
                    // Update map center
                    map.setView([lat, lng], 15);
                    
                    // Update or create marker
                    if (marker) {
                        marker.setLatLng([lat, lng]);
                    } else {
                        marker = L.marker([lat, lng]).addTo(map);
                    }
                    
                    // Update hidden inputs
                    document.getElementById('workplace_latitude').value = lat;
                    document.getElementById('workplace_longitude').value = lng;
                    
                    // Update coordinates display
                    updateCoordinatesDisplay(lat, lng);
                    
                    // Check GPS accuracy
                    checkGPSAccuracy(accuracy);
                    
                    // Show success message
                    showLocationMessage('Location set successfully!', 'success');
                    
                    // Reset button
                    button.innerHTML = originalText;
                    button.disabled = false;
                },
                function(error) {
                    let errorMessage = 'Error getting location: ';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage += 'Permission denied. Please allow location access.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage += 'Location information unavailable.';
                            break;
                        case error.TIMEOUT:
                            errorMessage += 'Location request timed out.';
                            break;
                        default:
                            errorMessage += 'Unknown error occurred.';
                            break;
                    }
                    
                    showLocationMessage(errorMessage, 'danger');
                    
                    // Reset button
                    button.innerHTML = originalText;
                    button.disabled = false;
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 300000
                }
            );
        }
        
        // Check GPS accuracy and show warning if needed
        function checkGPSAccuracy(accuracy) {
            const accuracyDiv = document.getElementById('gps-accuracy');
            const accuracyMessage = document.getElementById('accuracy-message');
            
            if (accuracy > 50) {
                accuracyMessage.textContent = `Your GPS signal is weak (accuracy: ${Math.round(accuracy)}m). Move outdoors for better accuracy.`;
                accuracyDiv.style.display = 'block';
            } else {
                accuracyDiv.style.display = 'none';
            }
        }
        
        // Update coordinates display
        function updateCoordinatesDisplay(lat, lng) {
            const display = document.getElementById('coordinates-display');
            display.textContent = `Lat: ${lat.toFixed(6)}, Long: ${lng.toFixed(6)}`;
        }
        
        // Show location message
        function showLocationMessage(message, type) {
            const alert = document.createElement('div');
            alert.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alert.style.top = '20px';
            alert.style.right = '20px';
            alert.style.zIndex = '9999';
            alert.innerHTML = `
                <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alert);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alert.parentNode) {
                    alert.parentNode.removeChild(alert);
                }
            }, 5000);
        }
        
        function initMap() {
            // Default center (Cagayan de Oro)
            const defaultLat = 8.4809;
            const defaultLng = 124.6442;
            
            // Use existing coordinates or default
            let lat = defaultLat;
            let lng = defaultLng;
            <?php if ($profile && $profile['workplace_latitude'] && $profile['workplace_longitude']): ?>
            lat = <?= $profile['workplace_latitude'] ?>;
            lng = <?= $profile['workplace_longitude'] ?>;
            <?php endif; ?>
            
            map = L.map('map').setView([lat, lng], 15);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add marker if location exists
            if (lat !== defaultLat && lng !== defaultLng) {
                marker = L.marker([lat, lng]).addTo(map);
                
                // Disable marker dragging if form is disabled
                if (isFormDisabled) {
                    marker.dragging.disable();
                    marker.options.draggable = false;
                    
                    // Add drag event listener to prevent dragging
                    marker.on('dragstart', function(e) {
                        e.target.dragging.disable();
                        alert('Workplace location is locked. Contact your instructor to make changes.');
                    });
                }
            }
            
            // Add click event to set location (only if form is not disabled)
            console.log('Setting up map click events. isFormDisabled:', isFormDisabled);
            
            if (!isFormDisabled) {
                console.log('Map is ENABLED - adding click events');
                map.on('click', function(e) {
                    console.log('Map clicked - form is enabled');
                    const lat = e.latlng.lat;
                    const lng = e.latlng.lng;
                    
                    // Update hidden inputs
                    document.getElementById('workplace_latitude').value = lat;
                    document.getElementById('workplace_longitude').value = lng;
                    
                    // Update or create marker
                    if (marker) {
                        marker.setLatLng([lat, lng]);
                    } else {
                        marker = L.marker([lat, lng]).addTo(map);
                    }
                    
                    // Ensure marker is draggable when form is enabled
                    marker.dragging.enable();
                    marker.options.draggable = true;
                    
                    // Update coordinates display
                    updateCoordinatesDisplay(lat, lng);
                    
                    // Show success message
                    showLocationMessage('Location set successfully!', 'success');
                });
            } else {
                console.log('Map is DISABLED - adding protection');
                // Map is disabled - add click protection
                map.on('click', function(e) {
                    console.log('Map clicked but DISABLED - showing alert');
                    alert('Workplace location is locked. Contact your instructor to make changes.');
                    return false;
                });
                
                // Disable all map interactions
                console.log('Disabling map interactions');
                map.dragging.disable();
                map.touchZoom.disable();
                map.doubleClickZoom.disable();
                map.scrollWheelZoom.disable();
                map.boxZoom.disable();
                map.keyboard.disable();
                
                // Disable marker dragging
                if (marker) {
                    console.log('Disabling marker dragging');
                    marker.dragging.disable();
                    marker.options.draggable = false;
                    
                    // Add drag event listener to prevent dragging
                    marker.on('dragstart', function(e) {
                        console.log('Marker drag attempted - showing alert');
                        e.target.dragging.disable();
                        alert('Workplace location is locked. Contact your instructor to make changes.');
                    });
                }
            }
        }
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            
            // Additional marker protection after map loads
            setTimeout(function() {
                console.log('Additional marker protection - isFormDisabled:', isFormDisabled, 'marker exists:', !!marker);
                if (isFormDisabled && marker) {
                    console.log('Applying additional marker protection');
                    marker.dragging.disable();
                    marker.options.draggable = false;
                    
                    // Remove any existing drag listeners and add new ones
                    marker.off('dragstart');
                    marker.on('dragstart', function(e) {
                        console.log('Additional marker drag protection triggered');
                        e.target.dragging.disable();
                        alert('Workplace location is locked. Contact your instructor to make changes.');
                    });
                }
            }, 1000); // Wait 1 second for map to fully load
        });
        
        // Form reset functions
        function resetBasicForm() {
            document.getElementById('basicProfileForm').reset();
        }
        
        function resetPasswordForm() {
            document.getElementById('passwordChangeForm').reset();
        }
        
        // Client-side validation
        document.getElementById('basicProfileForm').addEventListener('submit', function(e) {
            const fullName = document.getElementById('full_name').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (!fullName) {
                e.preventDefault();
                alert('Full name is required.');
                return;
            }
            
            if (!email) {
                e.preventDefault();
                alert('Email is required.');
                return;
            }
            
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
                e.preventDefault();
                alert('Please enter a valid email address.');
                return;
            }
        });
        
        document.getElementById('passwordChangeForm').addEventListener('submit', function(e) {
            const currentPassword = document.getElementById('current_password').value;
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (!currentPassword) {
                e.preventDefault();
                alert('Current password is required.');
                return;
            }
            
            if (!newPassword) {
                e.preventDefault();
                alert('New password is required.');
                return;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('New password must be at least 8 characters long.');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('New password and confirmation do not match.');
                return;
            }
            // Auto-dismiss function
            function showAlert(type, message) {
                // Remove existing alerts
                document.querySelectorAll('.alert-fixed').forEach(alert => alert.remove());
                
                // Create new alert
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-fixed alert-auto-dismiss`;
                alertDiv.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="bi bi-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                        <span>${message}</span>
                        <button type="button" class="btn-close ms-auto" onclick="dismissAlert(this.parentElement.parentElement)"></button>
                    </div>
                `;
                
                document.body.appendChild(alertDiv);
                
                // Auto-dismiss after 5 seconds
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.style.animation = 'fadeOut 0.3s ease-in forwards';
                        setTimeout(() => alertDiv.remove(), 300);
                    }
                }, 5000);
            }

            function dismissAlert(alertElement) {
                alertElement.style.animation = 'fadeOut 0.3s ease-in forwards';
                setTimeout(() => alertElement.remove(), 300);
            }
        });
    </script>
    
    <!-- Workplace Edit Request Modal -->
    <div class="modal fade" id="workplaceEditRequestModal" tabindex="-1" aria-labelledby="workplaceEditRequestModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="workplaceEditRequestModalLabel">
                        <i class="bi bi-pencil-square me-2"></i>Request Workplace Edit
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="workplaceEditRequestForm">
                    <input type="hidden" name="action" value="submit_workplace_edit_request">
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>Note:</strong> Once you submit this request, your instructor will review it and decide whether to approve it. If approved, you'll be able to edit your workplace information.
                        </div>
                        
                        <div class="mb-3">
                            <label for="request_reason" class="form-label">Reason for Edit (Optional)</label>
                            <textarea class="form-control" id="request_reason" name="request_reason" rows="3" 
                                      placeholder="Please explain why you need to edit your workplace information..."></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Important:</strong> If your request is approved, your instructor will decide whether to keep your existing OJT hours or reset them to zero.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-send me-2"></i>Submit Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
