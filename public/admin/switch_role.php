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

// Allow both admin role and admin acting as instructor
$user = $authMiddleware->getCurrentUser();
$isAdmin = $user && $user->role === 'admin';
$isActingAsInstructor = isset($_SESSION['acting_role']) && $_SESSION['acting_role'] === 'instructor' && $isAdmin;

if (!$isAdmin) {
    $authMiddleware->redirectToUnauthorized();
}

// Handle role switching
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'switch_to_instructor':
            // Check if admin has section assigned (check both ways)
            $pdo = \App\Utils\Database::getInstance();
            
            // Check if admin has section_id in users table
            $hasSectionId = !empty($user->section_id);
            
            // Also check instructor_sections junction table
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM instructor_sections WHERE instructor_id = ?");
            $stmt->execute([$user->id]);
            $hasJunctionSection = $stmt->fetchColumn() > 0;
            
            if ($hasSectionId || $hasJunctionSection) {
                $_SESSION['acting_role'] = 'instructor';
                $_SESSION['original_role'] = 'admin';
                $_SESSION['role'] = 'instructor'; // Temporarily set role to instructor for navigation
                $_SESSION['success'] = 'Switched to instructor mode. You can now access instructor features.';
                
                // Determine redirect location
                $redirect = $_POST['redirect'] ?? '../instructor/dashboard.php';
                header('Location: ' . $redirect);
            } else {
                $_SESSION['error'] = 'You must be assigned to a section to switch to instructor mode.';
                $redirect = $_POST['redirect'] ?? 'dashboard.php';
                header('Location: ' . $redirect);
            }
            break;
            
        case 'switch_back_to_admin':
            // Clear all acting role session variables
            unset($_SESSION['acting_role']);
            unset($_SESSION['original_role']);
            
            // Ensure we're back to admin role - get from database
            $user = $authMiddleware->getCurrentUser();
            if ($user && $user->role === 'admin') {
                // Set session role back to admin
                $_SESSION['role'] = 'admin';
                
                // Set success message
                $_SESSION['success'] = 'Switched back to admin mode.';
                
                // Debug: Log the session state
                error_log("Role switch back - Session state: user_id=" . ($_SESSION['user_id'] ?? 'NOT SET') . 
                         ", role=" . ($_SESSION['role'] ?? 'NOT SET') . 
                         ", acting_role=" . ($_SESSION['acting_role'] ?? 'NOT SET') . 
                         ", original_role=" . ($_SESSION['original_role'] ?? 'NOT SET'));
                
                // Force session write
                session_write_close();
                
                // Add cache control headers to prevent caching
                header('Cache-Control: no-cache, no-store, must-revalidate');
                header('Pragma: no-cache');
                header('Expires: 0');
                
                // Determine redirect location
                $redirect = $_POST['redirect'] ?? 'dashboard.php';
                header('Location: ' . $redirect);
            } else {
                $_SESSION['error'] = 'Unable to switch back to admin mode. Please contact system administrator.';
                $redirect = $_POST['redirect'] ?? 'dashboard.php';
                header('Location: ' . $redirect);
            }
            break;
            
        default:
            $_SESSION['error'] = 'Invalid action.';
            $redirect = $_POST['redirect'] ?? 'dashboard.php';
            header('Location: ' . $redirect);
            break;
    }
    exit;
}

// If not POST, redirect based on current role
if ($isActingAsInstructor) {
    header('Location: ../instructor/dashboard.php');
} else {
    header('Location: dashboard.php');
}
exit;
