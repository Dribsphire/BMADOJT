<?php

/**
 * Front Controller
 * OJT Route - Main entry point
 */

require_once '../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;

// Start session
session_start();

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check if user is logged in
if (!$authMiddleware->check()) {
    header('Location: login.php');
    exit;
}

// Get current user
$user = $authMiddleware->getCurrentUser();

if ($user) {
    // Route based on user role
    switch ($user->role) {
        case 'admin':
            header('Location: admin/dashboard.php');
            break;
        case 'instructor':
            header('Location: instructor/dashboard.php');
            break;
        case 'student':
            header('Location: student/dashboard.php');
            break;
        default:
            header('Location: login.php');
            break;
    }
} else {
    header('Location: login.php');
}
