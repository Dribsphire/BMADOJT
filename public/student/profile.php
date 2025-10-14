<?php

/**
 * Student Profile Page
 * OJT Route - Student profile management with workplace setup
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Services\FileUploadService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;

// Start session
session_start();

// Initialize services
$authService = new AuthenticationService();
$fileUploadService = new FileUploadService();
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
$stmt = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
$stmt->execute([$user->id]);
$profile = $stmt->fetch();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload_profile_picture') {
        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $result = $fileUploadService->uploadProfilePicture($_FILES['profile_picture'], $user->id);
            
            if ($result['success']) {
                $_SESSION['success'] = $result['message'];
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
        $result = $fileUploadService->deleteProfilePicture($user->id);
        
        if ($result['success']) {
            $_SESSION['success'] = $result['message'];
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
            $stmt->execute([$email, $user->id]);
            if ($stmt->fetch()) {
                $errors[] = 'Email already in use by another account.';
            }
        }
        
        if (empty($errors)) {
            // Update profile
            $stmt = $pdo->prepare("UPDATE users SET full_name = ?, email = ?, contact = ?, facebook_name = ? WHERE id = ?");
            $stmt->execute([$full_name, $email, $contact, $facebook_name, $user->id]);
            
            // Log activity
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
            $stmt->execute([
                $user->id,
                'profile_update',
                "User {$user->school_id} updated profile"
            ]);
            
            // Update session user data
            $user->full_name = $full_name;
            $user->email = $email;
            $user->contact = $contact;
            $user->facebook_name = $facebook_name;
            
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
        } elseif (!$user->verifyPassword($current_password)) {
            $errors[] = 'Current password is incorrect.';
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
            $stmt->execute([$hashed_password, $user->id]);
            
            // Log activity
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
            $stmt->execute([
                $user->id,
                'password_change',
                "User {$user->school_id} changed password"
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
        // Check if workplace location is locked (one-time setup restriction)
        if ($profile && $profile['workplace_location_locked']) {
            $_SESSION['error'] = 'Workplace location is locked. Contact your instructor or admin to make changes.';
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
            'workplace_location_locked' => 1 // Auto-lock when coordinates are set
        ];
        
        try {
            if ($profile) {
                // Update existing profile (only if workplace location not set)
                $stmt = $pdo->prepare("
                    UPDATE student_profiles SET 
                        workplace_name = ?, supervisor_name = ?, company_head = ?, 
                        student_position = ?, ojt_start_date = ?, workplace_latitude = ?, 
                        workplace_longitude = ?, workplace_location_locked = ?, updated_at = NOW()
                    WHERE user_id = ? AND (workplace_latitude IS NULL OR workplace_longitude IS NULL)
                ");
                $result = $stmt->execute([
                    $profileData['workplace_name'], $profileData['supervisor_name'], 
                    $profileData['company_head'], $profileData['student_position'], 
                    $profileData['ojt_start_date'], $profileData['workplace_latitude'], 
                    $profileData['workplace_longitude'], $profileData['workplace_location_locked'], 
                    $user->id
                ]);
                
                if ($stmt->rowCount() === 0) {
                    $_SESSION['error'] = 'Workplace information has already been set. Contact your instructor or admin to make changes.';
                    header('Location: profile.php');
                    exit;
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
                    $user->id, $profileData['workplace_name'], $profileData['supervisor_name'], 
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
    }
}

// Get updated profile after potential changes
$stmt = $pdo->prepare("SELECT * FROM student_profiles WHERE user_id = ?");
$stmt->execute([$user->id]);
$profile = $stmt->fetch();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
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
    </style>
</head>
<body>
    <?php include 'student-sidebar.php'; ?>
    <main>
        <!-- Navigation -->
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container-fluid">
                <a class="navbar-brand" href="dashboard.php">
                    <i class="bi bi-mortarboard me-2"></i>OJT Route
                </a>
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="dashboard.php">
                        <i class="bi bi-speedometer2 me-1"></i>Dashboard
                    </a>
                    <a class="nav-link" href="attendance.php">
                        <i class="bi bi-clock me-1"></i>Attendance
                    </a>
                    <a class="nav-link" href="documents.php">
                        <i class="bi bi-file-text me-1"></i>Documents
                    </a>
                    <a class="nav-link active" href="profile.php">
                        <i class="bi bi-person me-1"></i>Profile
                    </a>
                    <a class="nav-link" href="messages.php">
                        <i class="bi bi-chat me-1"></i>Messages
                    </a>
                    <span class="navbar-text me-3">
                        Welcome, <?= htmlspecialchars($user->getDisplayName()) ?>
                    </span>
                    <button type="button" class="btn btn-outline-light btn-sm" 
                            data-bs-toggle="modal" data-bs-target="#logoutModal">
                        <i class="bi bi-box-arrow-right me-1"></i>Logout
                    </button>
                </div>
            </div>
        </nav><br>
        
                            <!-- Workplace Status -->
                            <div class="row mb-4">
                        <div class="col-12">
                            <div class="card workplace-status <?= $profile && $profile['workplace_location_locked'] ? 'complete' : '' ?>">
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="bi bi-geo-alt me-2"></i>Workplace Information Status
                                    </h5>
                                    <?php if ($profile && $profile['workplace_location_locked']): ?>
                                        <p class="card-text text-success">
                                            <i class="bi bi-check-circle me-2"></i>Workplace location is set and locked. You can now mark attendance.
                                        </p>
                                    <?php else: ?>
                                        <p class="card-text text-danger">
                                            <i class="bi bi-exclamation-triangle me-2"></i>Complete your workplace information below to enable attendance features.
                                        </p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
        <!-- Main Content -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <h2 class="mb-4">
                        <i class="bi bi-person me-2"></i>My Profile
                    </h2>
                    
                    <!-- Success/Error Messages -->
                    <?php if (isset($_SESSION['success'])): ?>
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($_SESSION['success']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                        <?php unset($_SESSION['success']); ?>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($_SESSION['error']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
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
                                                           value="<?= htmlspecialchars($user->full_name) ?>" 
                                                           required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">School ID</label>
                                                    <input type="text" class="form-control" value="<?= htmlspecialchars($user->school_id) ?>" readonly>
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
                                                           value="<?= htmlspecialchars($user->email) ?>" 
                                                           required>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label class="form-label">Role</label>
                                                    <input type="text" class="form-control" value="<?= ucfirst($user->role) ?>" readonly>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="mb-3">
                                                    <label for="contact" class="form-label">Contact</label>
                                                    <input type="text" 
                                                           class="form-control" 
                                                           id="contact" 
                                                           name="contact" 
                                                           value="<?= htmlspecialchars($user->contact ?? '') ?>" 
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
                                                           value="<?= htmlspecialchars($user->facebook_name ?? '') ?>" 
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
                                <div class="card-body">
                                    <h5 class="card-title">
                                        <i class="bi bi-camera me-2"></i>Profile Picture
                                    </h5>
                                    <div class="row align-items-center">
                                        <div class="col-md-3 text-center">
                                            <?php 
                                            $profilePictureUrl = $fileUploadService->getProfilePictureUrl($user->profile_picture);
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
                                                    <i class="bi bi-upload me-2"></i>Upload Picture
                                                </button>
                                            </form>
                                            
                                            <?php if ($user->profile_picture): ?>
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
                                    $isFormDisabled = $isWorkplaceLocked;
                                    ?>
                                    
                                    <?php if ($isWorkplaceLocked): ?>
                                        <div class="alert alert-info">
                                            <i class="bi bi-info-circle me-2"></i>
                                            <strong>Workplace location is locked.</strong> Contact your instructor or admin to make changes.
                                        </div>
                                    <?php endif; ?>
                                    
                                    <form method="POST" id="profileForm" <?= $isFormDisabled ? 'onsubmit="return false;"' : '' ?>>
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
                                            
                                            <div class="col-md-6 mb-3">
                                                <div class="form-check mt-4">
                                                    <input class="form-check-input" type="checkbox" id="workplace_location_locked" 
                                                           name="workplace_location_locked" 
                                                           <?= $profile && $profile['workplace_location_locked'] ? 'checked' : '' ?>
                                                           <?= $isFormDisabled ? 'disabled' : '' ?>>
                                                    <label class="form-check-label" for="workplace_location_locked">
                                                        Lock workplace location (prevents changes)
                                                    </label>
                                                </div>
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
                                            
                                            <div id="map" <?= $isFormDisabled ? 'style="pointer-events: none; opacity: 0.6;"' : '' ?>></div>
                                            
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
                                            <a href="dashboard.php" class="btn btn-outline-secondary">
                                                <i class="bi bi-arrow-left me-2"></i>Back to Dashboard
                                            </a>
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
                                        <strong>Name:</strong> <?= htmlspecialchars($user->getDisplayName()) ?><br>
                                        <strong>School ID:</strong> <?= htmlspecialchars($user->school_id) ?><br>
                                        <strong>Email:</strong> <?= htmlspecialchars($user->email) ?><br>
                                        <strong>Section:</strong> <?= htmlspecialchars($user->section_name ?? 'Not assigned') ?>
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
        // Initialize map
        let map;
        let marker;
        let currentLocation = null;
        
        // Get current location using GPS
        function getCurrentLocation() {
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
            const lat = <?= $profile && $profile['workplace_latitude'] ? $profile['workplace_latitude'] : 'defaultLat' ?>;
            const lng = <?= $profile && $profile['workplace_longitude'] ? $profile['workplace_longitude'] : 'defaultLng' ?>;
            
            map = L.map('map').setView([lat, lng], 15);
            
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);
            
            // Add marker if location exists
            if (lat !== defaultLat && lng !== defaultLng) {
                marker = L.marker([lat, lng]).addTo(map);
            }
            
            // Add click event to set location (only if form is not disabled)
            <?php if (!$isFormDisabled): ?>
            map.on('click', function(e) {
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
                
                // Update coordinates display
                updateCoordinatesDisplay(lat, lng);
                
                // Show success message
                showLocationMessage('Location set successfully!', 'success');
            });
            <?php endif; ?>
        }
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', initMap);
        
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
        });
    </script>
</body>
</html>
