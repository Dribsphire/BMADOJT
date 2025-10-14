<?php

/**
 * Role Switching Handler
 * OJT Route - Handle admin role switching to instructor mode
 */

require_once '../../vendor/autoload.php';

use App\Middleware\AuthMiddleware;

// Start session
session_start();

// Initialize authentication
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

// Handle role switching
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'switch_to_instructor':
            if ($user->section_id) {
                $_SESSION['acting_role'] = 'instructor';
                $_SESSION['original_role'] = 'admin';
                $_SESSION['success'] = 'Switched to instructor mode. You can now access instructor features.';
                header('Location: /bmadOJT/public/instructor/dashboard.php');
            } else {
                $_SESSION['error'] = 'You must be assigned to a section to switch to instructor mode.';
                header('Location: /bmadOJT/public/admin/dashboard.php');
            }
            break;
            
        case 'switch_back_to_admin':
            // Clear all acting role session variables
            unset($_SESSION['acting_role']);
            unset($_SESSION['original_role']);
            
            // Ensure we're back to admin role
            $_SESSION['role'] = 'admin';
            
            // Set success message
            $_SESSION['success'] = 'Switched back to admin mode.';
            
            // Debug: Log the session state
            error_log("Role switch back - Session state: user_id=" . ($_SESSION['user_id'] ?? 'NOT SET') . 
                     ", role=" . ($_SESSION['role'] ?? 'NOT SET') . 
                     ", acting_role=" . ($_SESSION['acting_role'] ?? 'NOT SET') . 
                     ", original_role=" . ($_SESSION['original_role'] ?? 'NOT SET'));
            
            // Force session write and restart
            session_write_close();
            session_start();
            
            // Add cache control headers to prevent caching
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Pragma: no-cache');
            header('Expires: 0');
            
            header('Location: /bmadOJT/public/admin/dashboard.php');
            break;
            
        default:
            $_SESSION['error'] = 'Invalid action.';
            header('Location: /bmadOJT/public/admin/dashboard.php');
            break;
    }
    exit;
}

// If not POST, redirect to dashboard
header('Location: /bmadOJT/public/admin/dashboard.php');
exit;
