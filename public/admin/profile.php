<?php
/**
 * Admin Profile Page
 * OJT Route - Admin profile management with picture upload
 */

require_once '../../vendor/autoload.php';

use App\Services\FileUploadService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;

// Start session
session_start();

// Initialize services
$fileUploadService = new FileUploadService();
$authMiddleware = new AuthMiddleware();

// Check authentication and authorization
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('admin')) {
    $authMiddleware->redirectToUnauthorized();
}

// Get current user
$user = $authMiddleware->getCurrentUser();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        // Handle profile information update
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
            $pdo = Database::getInstance();
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
            $pdo = Database::getInstance();
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
        
    } elseif ($action === 'upload_profile_picture') {
        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
            $result = $fileUploadService->uploadProfilePicture($_FILES['profile_picture'], $user->id);
            
            if ($result['success']) {
                // Log activity
                $pdo = Database::getInstance();
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
                $stmt->execute([
                    $user->id,
                    'profile_picture_upload',
                    "User {$user->school_id} uploaded profile picture"
                ]);
                
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
            // Log activity
            $pdo = Database::getInstance();
            $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
            $stmt->execute([
                $user->id,
                'profile_picture_delete',
                "User {$user->school_id} deleted profile picture"
            ]);
            
            $_SESSION['success'] = $result['message'];
        } else {
            $_SESSION['error'] = $result['message'];
        }
        
        header('Location: profile.php');
        exit;
    }
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
    </style>
</head>
<body>
<?php include 'sidebar.php'; ?>
            <!-- Main Content -->
            <main>
                    <!-- Navigation -->
            <nav class="navbar navbar-expand-lg navbar-dark">
                <div class="container-fluid">
                    <a class="navbar-brand" href="#">
                        <i class="bi bi-mortarboard me-2"></i>OJT Route
                    </a>
                    <div class="navbar-nav ms-auto">
                        <span class="navbar-text me-3">
                            Welcome, <?= htmlspecialchars($user->getDisplayName()) ?>
                        </span>
                        <a class="nav-link me-2" href="profile.php">
                            <i class="bi bi-person me-1"></i>My Profile
                        </a>
                        <button type="button" class="btn btn-outline-light btn-sm" 
                                data-bs-toggle="modal" data-bs-target="#logoutModal">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </button>
                    </div>
                </div>
            </nav>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">My Profile</h1>
                </div>

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

                <!-- Profile Information -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="bi bi-person me-2"></i>Profile Information
                                </h5>
                                <form method="POST" id="profileForm">
                                    <input type="hidden" name="action" value="update_profile">
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
                                        <button type="button" class="btn btn-outline-secondary" onclick="resetForm()">
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
            </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form reset functions
        function resetForm() {
            document.getElementById('profileForm').reset();
        }
        
        function resetPasswordForm() {
            document.getElementById('passwordChangeForm').reset();
        }
        
        // Client-side validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
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
