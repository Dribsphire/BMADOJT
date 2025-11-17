<?php

/**
 * User Management Dashboard
 * OJT Route - Admin user management interface
 */

require_once '../../vendor/autoload.php';

use App\Controllers\UserController;

// Start session
session_start();

// Initialize controller
$controller = new UserController();

// Handle different actions
$action = $_GET['action'] ?? 'index';

switch ($action) {
    case 'bulk_register':
        $controller->bulkRegister();
        break;
    case 'register':
        $controller->register();
        break;
    case 'assign_section':
        $controller->assignSection();
        break;
    case 'change_password':
        $controller->changePassword();
        break;
    case 'delete':
        $controller->delete();
        break;
    case 'archive_all':
        $controller->archiveAll();
        break;
    default:
        $controller->index();
        break;
}

