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
        $showArchived = isset($_GET['show_archived']) && $_GET['show_archived'] === '1';
        
        // Get all users with pagination, search, and filters
        $page = (int) ($_GET['page'] ?? 1);
        $limit = 20;
        $offset = ($page - 1) * $limit;
        
        $users = $this->userService->getAllUsers($limit, $offset, $search, $role, $section, '', $showArchived);
        $totalUsers = $this->userService->getTotalUsers($search, $role, $section, '', $showArchived);
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
     * Handle password change
     */
    public function changePassword(): void
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
        
        // Debug: Log all POST data
        error_log('UserController::changePassword() - POST data: ' . print_r($_POST, true));
        error_log('UserController::changePassword() - Raw user_id: ' . ($_POST['user_id'] ?? 'NOT SET'));
        
        $userId = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        error_log('UserController::changePassword() - Parsed userId: ' . $userId . ', Password length: ' . strlen($newPassword));
        
        if ($userId <= 0) {
            $rawUserId = $_POST['user_id'] ?? 'not set';
            $_SESSION['error'] = 'Invalid user ID. Received: "' . htmlspecialchars($rawUserId) . '" (parsed as: ' . $userId . ')';
            error_log('UserController::changePassword() - ERROR: Invalid user ID. Raw: ' . $rawUserId . ', Parsed: ' . $userId);
            header('Location: users.php');
            exit;
        }
        
        if (empty($newPassword)) {
            $_SESSION['error'] = 'New password is required.';
            header('Location: users.php');
            exit;
        }
        
        if (strlen($newPassword) < 8) {
            $_SESSION['error'] = 'Password must be at least 8 characters long.';
            header('Location: users.php');
            exit;
        }
        
        if ($newPassword !== $confirmPassword) {
            $_SESSION['error'] = 'Passwords do not match.';
            header('Location: users.php');
            exit;
        }
        
        try {
            $pdo = Database::getInstance();
            $user = $this->authMiddleware->getCurrentUser();
            
            // Verify user exists and is not an admin
            $stmt = $pdo->prepare("SELECT id, role, school_id, full_name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $targetUser = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$targetUser) {
                $_SESSION['error'] = 'User not found.';
            } elseif ($targetUser['role'] === 'admin') {
                $_SESSION['error'] = 'Cannot change admin password through this interface.';
            } else {
                // Update password
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
                $stmt->execute([$hashedPassword, $userId]);
                
                // Log activity
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
                $stmt->execute([
                    $user->id,
                    'password_reset',
                    "Admin reset password for user {$targetUser['school_id']} ({$targetUser['full_name']})"
                ]);
                
                $_SESSION['success'] = "Password changed successfully for {$targetUser['full_name']} ({$targetUser['school_id']}).";
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error changing password: ' . $e->getMessage();
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
    
    /**
     * Handle archiving all users (except admins)
     */
    public function archiveAll(): void
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
        
        $user = $this->authMiddleware->getCurrentUser();
        
        try {
            $result = $this->userService->archiveAllUsers($user->id);
            
            if ($result['success']) {
                // Log activity
                $pdo = Database::getInstance();
                $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description) VALUES (?, ?, ?)");
                $stmt->execute([
                    $user->id,
                    'archive_all_users',
                    "Admin archived {$result['count']} users (all non-admin users)"
                ]);
                
                $_SESSION['success'] = $result['message'];
            } else {
                $_SESSION['error'] = $result['message'];
            }
        } catch (\Exception $e) {
            $_SESSION['error'] = 'Error archiving users: ' . $e->getMessage();
        }
        
        header('Location: users.php');
        exit;
    }
}
