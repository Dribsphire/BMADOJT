<?php

/**
 * Debug Authentication
 * OJT Route - Debug authentication issues
 */

require_once '../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;

// Start session
session_start();

echo "<h2>Authentication Debug</h2>\n";

// Check session data
echo "<h3>Session Data:</h3>\n";
echo "<pre>";
print_r($_SESSION);
echo "</pre>\n";

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication status
echo "<h3>Authentication Status:</h3>\n";
echo "Is logged in: " . ($authMiddleware->check() ? 'Yes' : 'No') . "<br>\n";

// Get current user
$user = $authMiddleware->getCurrentUser();
if ($user) {
    echo "Current user: " . $user->getDisplayName() . " (Role: " . $user->role . ")<br>\n";
} else {
    echo "No current user<br>\n";
}

// Check session timeout
if (isset($_SESSION['timeout'])) {
    echo "Session timeout: " . date('Y-m-d H:i:s', $_SESSION['timeout']) . "<br>\n";
    echo "Current time: " . date('Y-m-d H:i:s', time()) . "<br>\n";
    echo "Session expired: " . (time() > $_SESSION['timeout'] ? 'Yes' : 'No') . "<br>\n";
}

echo "<h3>Request Info:</h3>\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "<br>\n";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "<br>\n";

echo "<br><a href='login.php'>Go to Login</a><br>\n";
echo "<a href='index.php'>Go to Index</a><br>\n";

