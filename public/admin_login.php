<?php

/**
 * Admin Login Page
 * OJT Route - Admin authentication
 */

require_once '../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;

// Start session
session_start();

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check if already logged in
if ($authMiddleware->check()) {
    $user = $authMiddleware->getCurrentUser();
    
    if ($user && $user->role === 'admin') {
        header('Location: admin/dashboard.php');
        exit;
    } elseif ($user) {
        // Redirect non-admin users to their respective dashboards
        switch ($user->role) {
            case 'instructor':
                header('Location: instructor/dashboard.php');
                break;
            case 'student':
                header('Location: student/dashboard.php');
                break;
        }
        exit;
    }
}

$error = '';
$success = '';

// Check for logout success message (only if not submitting login form)
if (!isset($_POST['school_id']) && isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'You have been successfully logged out.';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $schoolId = trim($_POST['school_id'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($schoolId) || empty($password)) {
        $error = 'Please enter both School ID and Password.';
        $success = ''; // Clear any success message
    } else {
        $user = $authService->authenticate($schoolId, $password);
        
        if ($user && $user->role === 'admin') {
            $authService->startSession($user);
            header('Location: admin/dashboard.php');
            exit;
        } else {
            $error = 'Invalid Admin credentials. Only administrators can access this page.';
            $success = ''; // Clear any success message
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - OJT Route</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --chmsu-green: #0ea539;
            --chmsu-green-light: #34d399;
            --chmsu-green-dark: #059669;
            --admin-red:rgb(211, 165, 16);
            --admin-red-light: #f8d7da;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-image: url('../public/images/homepage.png');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 500px;
            width: 100%;
            margin: 20px;
        }
        
        .login-header {
            background: var(--admin-red);
            color: white;
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        
        .login-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, var(--admin-red), rgb(211, 165, 16));
            opacity: 0.9;
        }
        
        .login-header > * {
            position: relative;
            z-index: 1;
        }
        
        .login-header h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
        }
        
        .login-header p {
            margin: 0.5rem 0 0 0;
            opacity: 0.9;
        }
        
        .login-body {
            padding: 2rem;
        }
        
        .form-floating {
            margin-bottom: 1rem;
        }
        
        .form-control {
            border-radius: 10px;
            border: 2px solid #e5e7eb;
            padding: 0.75rem 1rem;
        }
        
        .form-control:focus {
            border-color: var(--admin-red);
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
        
        .btn-primary {
            background: var(--admin-red);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: rgb(194, 151, 12);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            position: fixed;
            top: 20px;
            left: 50%;
            transform: translateX(-50%);
            z-index: 1050;
            min-width: 300px;
            max-width: 500px;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateX(-50%) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
        }
        
        .alert.fade-out {
            animation: fadeOut 0.5s ease-out forwards;
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateX(-50%) translateY(0);
            }
            to {
                opacity: 0;
                transform: translateX(-50%) translateY(-20px);
            }
        }
        
        .admin-logo {
            width: 60px;
            height: 60px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: var(--admin-red);
        }
        
        .admin-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .back-link {
            position: absolute;
            top: 20px;
            left: 20px;
            color: white;
            text-decoration: none;
            font-size: 1.2rem;
            transition: color 0.3s ease;
        }
        
        .back-link:hover {
            color: #f8d7da;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-container">
                    <div class="login-header">
                        <a href="login.php" class="back-link">
                            <i class="bi bi-arrow-left"></i>
                        </a>
                        <div class="admin-logo">
                            <i class="bi bi-shield-lock"></i>
                        </div>
                        <h1>Admin Portal</h1>
                        <p>OJT Route Administration</p>
                    </div>
                    
                    <div class="login-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger" role="alert">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success" role="alert">
                                <i class="bi bi-check-circle me-2"></i>
                                <?= htmlspecialchars($success) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" action="">
                            <div class="form-floating">
                                <input type="text" class="form-control" id="school_id" name="school_id" 
                                       placeholder="Admin School ID" value="<?= htmlspecialchars($_POST['school_id'] ?? '') ?>" required>
                                <label for="school_id">
                                    <i class="bi bi-person-badge me-2"></i>Admin School ID
                                </label>
                            </div>
                            
                            <div class="form-floating">
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Password" required>
                                <label for="password">
                                    <i class="bi bi-lock me-2"></i>Password
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-shield-lock me-2"></i>Admin Login
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <small class="text-muted">
                                CHMSU OJT routing system @2025
                            </small>
                        </div>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.classList.add('fade-out');
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
</body>
</html>