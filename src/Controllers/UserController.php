<?php

namespace App\Controllers;

use App\Models\User;
use App\Models\Section;
use App\Services\UserService;
use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;

/**
 * User Controller
 * OJT Route - User management operations
 */
class UserController
{
    private UserService $userService;
    private AuthMiddleware $authMiddleware;
    
    public function __construct()
    {
        $this->userService = new UserService();
        $this->authMiddleware = new AuthMiddleware();
    }
    
    /**
     * Display user management dashboard
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
        
        // Get user statistics
        $stats = $this->userService->getUserStatistics();
        
        // Get search and filter parameters
        $search = $_GET['search'] ?? '';
        $role = $_GET['role'] ?? '';
        $section = $_GET['section'] ?? '';
        $status = $_GET['status'] ?? '';
        
        // Get all users with pagination, search, and filters
        $page = (int) ($_GET['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $users = $this->userService->getAllUsers($limit, $offset, $search, $role, $section, $status);
        $totalUsers = $this->userService->getTotalUsers($search, $role, $section, $status);
        $totalPages = ceil($totalUsers / $limit);
        
        // Get all sections
        $sections = $this->userService->getAllSections();
        
        // Include the view
        include __DIR__ . '/../../public/admin/users_view.php';
    }
    
    /**
     * Handle CSV bulk registration
     */
    public function bulkRegister(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: users.php');
            exit;
        }
        
        $userType = $_POST['user_type'] ?? '';
        $csvFile = $_FILES['csv_file'] ?? null;
        
        if (empty($userType) || !$csvFile || $csvFile['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['error'] = 'Please select a valid CSV file and user type.';
            header('Location: users.php');
            exit;
        }
        
        try {
            $result = $this->userService->processBulkRegistration($userType, $csvFile);
            
            if ($result['success']) {
                $message = "Successfully registered {$result['count']} {$userType}s.";
                if (!empty($result['errors'])) {
                    $message .= " Errors: " . implode(', ', $result['errors']);
                }
                $_SESSION['success'] = $message;
            } else {
                $_SESSION['error'] = $result['message'];
                if (!empty($result['errors'])) {
                    $_SESSION['error'] .= " Errors: " . implode(', ', $result['errors']);
                }
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error processing CSV: ' . $e->getMessage();
        }
        
        header('Location: users.php');
        exit;
    }
    
    /**
     * Handle manual user registration
     */
    public function register(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: users.php');
            exit;
        }
        
        $userData = [
            'school_id' => trim($_POST['school_id'] ?? ''),
            'email' => trim($_POST['email'] ?? ''),
            'full_name' => trim($_POST['full_name'] ?? ''),
            'role' => $_POST['role'] ?? '',
            'section_id' => !empty($_POST['section_id']) ? (int) $_POST['section_id'] : null,
            'gender' => $_POST['gender'] ?? null,
            'contact' => trim($_POST['contact'] ?? ''),
            'facebook_name' => trim($_POST['facebook_name'] ?? ''),
            'password' => $_POST['password'] ?? ''
        ];
        
        try {
            $result = $this->userService->createUser($userData);
            
            if ($result['success']) {
                $_SESSION['success'] = "User {$userData['full_name']} registered successfully.";
            } else {
                $_SESSION['error'] = $result['message'];
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error creating user: ' . $e->getMessage();
        }
        
        header('Location: users.php');
        exit;
    }
    
    /**
     * Handle section assignment
     */
    public function assignSection(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: users.php');
            exit;
        }
        
        $userId = (int) ($_POST['user_id'] ?? 0);
        $sectionId = !empty($_POST['section_id']) ? (int) $_POST['section_id'] : null;
        
        try {
            $result = $this->userService->assignSection($userId, $sectionId);
            
            if ($result['success']) {
                $_SESSION['success'] = 'Section assignment updated successfully.';
            } else {
                $_SESSION['error'] = $result['message'];
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error updating section: ' . $e->getMessage();
        }
        
        header('Location: users.php');
        exit;
    }
    
    /**
     * Handle user deletion
     */
    public function delete(): void
    {
        // Check authentication and authorization
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }
        
        if (!$this->authMiddleware->requireRole('admin')) {
            $this->authMiddleware->redirectToUnauthorized();
        }
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: users.php');
            exit;
        }
        
        $userId = (int) ($_POST['user_id'] ?? 0);
        
        if ($userId <= 0) {
            $_SESSION['error'] = 'Invalid user ID.';
            header('Location: users.php');
            exit;
        }
        
        try {
            $result = $this->userService->deleteUser($userId);
            
            if ($result['success']) {
                $_SESSION['success'] = 'User deleted successfully.';
            } else {
                $_SESSION['error'] = $result['message'];
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error deleting user: ' . $e->getMessage();
        }
        
        header('Location: users.php');
        exit;
    }
}
