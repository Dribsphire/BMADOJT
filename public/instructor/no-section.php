<?php

/**
 * No Section Assigned
 * OJT Route - Instructor without section assignment
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;

// Start session
session_start();

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check if user is logged in
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

// Get current user
$user = $authMiddleware->getCurrentUser();

// Check if user is instructor
if (!$user || !$user->isInstructor()) {
    $authMiddleware->redirectToUnauthorized();
}

// Check if instructor now has a section assigned
if ($user->section_id) {
    // Instructor has been assigned a section, redirect to dashboard
    header('Location: /bmadOJT/public/instructor/dashboard.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>No Section Assigned - OJT Route</title>
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
        
        .alert-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .alert-header {
            background: linear-gradient(135deg, #F59E0B 0%, #FBBF24 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }
        
        .icon-large {
            font-size: 4rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card alert-card">
                    <div class="card-header alert-header text-center">
                        <i class="bi bi-exclamation-triangle icon-large mb-3"></i>
                        <h3 class="mb-0">No Section Assigned</h3>
                    </div>
                    <div class="card-body text-center py-5">
                        <h5 class="card-title mb-4">
                            <i class="bi bi-person-badge me-2"></i>Access Restricted
                        </h5>
                        <p class="card-text mb-4">
                            Hello <strong><?= htmlspecialchars($user->getDisplayName()) ?></strong>,<br>
                            You are not assigned to a section yet. Please contact the administrator to get assigned to a section.
                        </p>
                        
                        <div class="alert alert-warning mb-4">
                            <i class="bi bi-info-circle me-2"></i>
                            <strong>What this means:</strong><br>
                            You cannot access instructor features until an administrator assigns you to a section.
                        </div>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-center">
                            <a href="../logout.php" class="btn btn-outline-secondary">
                                <i class="bi bi-box-arrow-right me-2"></i>Logout
                            </a>
                            <button type="button" class="btn btn-primary" onclick="refreshPage()">
                                <i class="bi bi-arrow-clockwise me-2"></i>Check Again
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="card mt-4">
                    <div class="card-body">
                        <h6 class="card-title">
                            <i class="bi bi-telephone me-2"></i>Need Help?
                        </h6>
                        <p class="card-text small text-muted">
                            Contact your system administrator to get assigned to a section. 
                            You can also try logging in again after the administrator has made the assignment.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function refreshPage() {
            location.reload();
        }
        
        // Auto-refresh every 30 seconds to check if section has been assigned
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
