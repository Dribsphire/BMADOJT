<?php

/**
 * Clear Session
 * OJT Route - Clear session to fix redirect loops
 */

session_start();

// Clear all session data
$_SESSION = [];

// Destroy the session
session_destroy();

echo "✅ Session cleared successfully!<br>\n";
echo "<a href='login.php'>Go to Login</a><br>\n";
echo "<a href='index.php'>Go to Index</a><br>\n";

