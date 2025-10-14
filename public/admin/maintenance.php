<?php

/**
 * System Maintenance
 * OJT Route - System maintenance and health monitoring
 */

require_once '../../vendor/autoload.php';

use App\Controllers\MaintenanceController;

$controller = new MaintenanceController();

// Handle different actions
$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'action':
        $controller->action();
        break;
    default:
        $controller->index();
        break;
}

