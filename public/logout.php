<?php

/**
 * Logout Page
 * OJT Route - User logout
 */

require_once '../vendor/autoload.php';

use App\Services\AuthenticationService;

// Start session
session_start();

// Initialize authentication
$authService = new AuthenticationService();

// Logout user
$authService->logout();

// Redirect to login page with success message
header('Location: login.php?logout=success');
exit;
