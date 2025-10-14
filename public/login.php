<?php

require_once '../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
session_start();

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check if already logged in
if ($authMiddleware->check()) {
    $user = $authMiddleware->getCurrentUser();
    
        if ($user) {
            // Redirect based on role
            switch ($user->role) {
                case 'admin':
                    // Admin should use admin login page
                    header('Location: admin_login.php');
                    break;
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
        
        if ($user) {
            // Check if admin is trying to login through regular login
            if ($user->role === 'admin') {
                $error = 'Administrators must use the Admin Login page.';
                $success = ''; // Clear any success message
            } else {
                $authService->startSession($user);
                
                // Redirect based on role
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
        } else {
            $error = 'Invalid School ID or Password.';
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
    <title>Login - OJT Route</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-container">
                    <div class="login-header">
                        <div class="chmsu-logo">
                            <img src="../public/images/CHMSU.png" alt="CHMSU Logo">
                        </div>
                        <h1>OJT Route</h1>
                    <p>CARLOS HILADO MEMORIAL STATE UNIVERSITY OJT SYSTEM</p>
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
                                       placeholder="School ID" value="<?= htmlspecialchars($_POST['school_id'] ?? '') ?>" required>
                                <label for="school_id">
                                    <i class="bi bi-person-badge me-2"></i>School ID
                                </label>
                            </div>
                            
                            <div class="form-floating position-relative">
                                <input type="password" class="form-control" id="password" name="password" 
                                       placeholder="Password" required>
                                <label for="password">
                                    <i class="bi bi-lock me-2"></i>Password
                                </label>
                                <button type="button" class="btn btn-link password-toggle" id="passwordToggle">
                                    <i class="bi bi-eye" id="passwordIcon"></i>
                                </button>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-box-arrow-in-right me-2"></i>Login
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
            
            // Password visibility toggle
            const passwordToggle = document.getElementById('passwordToggle');
            const passwordInput = document.getElementById('password');
            const passwordIcon = document.getElementById('passwordIcon');
            
            passwordToggle.addEventListener('click', function() {
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    passwordIcon.classList.remove('bi-eye');
                    passwordIcon.classList.add('bi-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    passwordIcon.classList.remove('bi-eye-slash');
                    passwordIcon.classList.add('bi-eye');
                }
            });
        });
    </script>
</body>
<style>
        :root {
            --chmsu-green: #0ea539;
            --chmsu-green-light: #34d399;
            --chmsu-green-dark: #059669;
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
            header:90%;
        }
        
        .login-header {
            background: var(--chmsu-green);
            color: white;
            padding: 2rem;
            text-align: center;
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
            border-color: var(--chmsu-green);
            box-shadow: 0 0 0 0.2rem rgba(14, 165, 57, 0.25);
        }
        
        .btn-primary {
            background: var(--chmsu-green);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            width: 100%;
        }
        
        .btn-primary:hover {
            background: var(--chmsu-green-dark);
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
        
        .chmsu-logo {
            width: 70px;
            height: 70px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 1.5rem;
            color: var(--chmsu-green);
        }
        .chmsu-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .password-toggle {
            position: absolute;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            z-index: 10;
            border: none;
            background: none;
            color: #6b7280;
            padding: 0;
            font-size: 1.2rem;
            transition: color 0.2s ease;
        }
        
        .password-toggle:hover {
            color: var(--chmsu-green);
        }
        
        .password-toggle:focus {
            outline: none;
            box-shadow: none;
        }
        
        .form-floating.position-relative .form-control {
            padding-right: 3rem;
        }
    </style>
</html>
