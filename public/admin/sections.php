<?php

/**
 * Section Management Dashboard
 * OJT Route - Admin section management interface
 */

require_once '../../vendor/autoload.php';

use App\Controllers\SectionController;

// Start session
session_start();

// Initialize controller
$controller = new SectionController();

// Handle different actions
$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'create':
        $controller->create();
        break;
    case 'update':
        $controller->update();
        break;
    case 'delete':
        $controller->delete();
        break;
    case 'assign_instructor':
        $controller->assignInstructor();
        break;
    case 'students':
        $controller->getStudents();
        break;
    default:
        $controller->index();
        break;
}
