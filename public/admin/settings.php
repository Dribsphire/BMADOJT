<?php

/**
 * System Settings
 * OJT Route - Admin system configuration
 */

require_once '../../vendor/autoload.php';

use App\Controllers\SystemConfigController;

$controller = new SystemConfigController();

// Handle different actions
$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'update':
        $controller->update();
        break;
    case 'reset':
        $controller->reset();
        break;
    case 'test_email':
        $controller->testEmail();
        break;
    default:
        $controller->index();
        break;
}

