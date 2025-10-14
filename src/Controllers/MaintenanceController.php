<?php

namespace App\Controllers;

use App\Services\SystemMaintenanceService;
use App\Middleware\AuthMiddleware;

/**
 * System Maintenance Controller
 * OJT Route - System maintenance and health monitoring
 */
class MaintenanceController
{
    private SystemMaintenanceService $maintenanceService;
    private AuthMiddleware $authMiddleware;
    
    public function __construct()
    {
        $this->maintenanceService = new SystemMaintenanceService();
        $this->authMiddleware = new AuthMiddleware();
    }
    
    /**
     * Display system health dashboard
     */
    public function index(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        $user = $this->authMiddleware->getCurrentUser();
        
        // Get system health
        $health = $this->maintenanceService->getSystemHealth();
        
        // Include the view
        include __DIR__ . '/../../public/admin/maintenance.php';
    }
    
    /**
     * Handle maintenance actions
     */
    public function action(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: maintenance.php');
            exit;
        }
        
        $action = $_POST['action'] ?? '';
        
        try {
            switch ($action) {
                case 'cleanup':
                    $result = $this->maintenanceService->cleanupOldData();
                    if ($result['success']) {
                        $_SESSION['success'] = $result['message'];
                    } else {
                        $_SESSION['error'] = $result['message'];
                    }
                    break;
                    
                case 'optimize':
                    $result = $this->maintenanceService->optimizeDatabase();
                    if ($result['success']) {
                        $_SESSION['success'] = $result['message'];
                    } else {
                        $_SESSION['error'] = $result['message'];
                    }
                    break;
                    
                default:
                    throw new \Exception('Invalid maintenance action');
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Maintenance action failed: ' . $e->getMessage();
        }
        
        header('Location: maintenance.php');
        exit;
    }
}

