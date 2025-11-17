<?php

namespace App\Middleware;

use App\Services\AuthenticationService;

/**
 * Authentication Middleware
 * OJT Route - Request authentication and authorization
 */
class AuthMiddleware
{
    private AuthenticationService $authService;
    
    public function __construct()
    {
        $this->authService = new AuthenticationService();
    }
    
    /**
     * Check if user is authenticated
     */
    public function check(): bool
    {
        return $this->authService->isLoggedIn();
    }
    
    /**
     * Require specific role
     */
    public function requireRole(string $role): bool
    {
        if (!$this->check()) {
            return false;
        }
        
        // Get current user to check actual role in database
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        // Check if user is acting in a different role
        if (isset($_SESSION['acting_role'])) {
            return $_SESSION['acting_role'] === $role;
        }
        
        // For admin role, always allow if user's actual role is admin
        if ($role === 'admin') {
            return $user->role === 'admin';
        }
        
        // For other roles, check if user's actual role matches
        return $user->role === $role;
    }
    
    /**
     * Require any of the specified roles
     */
    public function requireAnyRole(array $roles): bool
    {
        if (!$this->check()) {
            return false;
        }
        
        // Get current user to check actual role in database
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }
        
        // Check if user is acting in a different role
        if (isset($_SESSION['acting_role'])) {
            return in_array($_SESSION['acting_role'], $roles);
        }
        
        // Check if user's actual role matches any of the required roles
        return in_array($user->role, $roles);
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser()
    {
        return $this->authService->getCurrentUser();
    }
    
    /**
     * Redirect to login if not authenticated
     */
    public function redirectToLogin(): void
    {
        // Check if we're in a subdirectory
        $currentPath = $_SERVER['REQUEST_URI'];
        if (strpos($currentPath, '/admin/') !== false || strpos($currentPath, '/instructor/') !== false || strpos($currentPath, '/student/') !== false) {
            header('Location: ../login.php');
        } else {
            header('Location: login.php');
        }
        exit;
    }
    
    /**
     * Redirect to unauthorized page
     */
    public function redirectToUnauthorized(): void
    {
        // Check if we're in a subdirectory
        $currentPath = $_SERVER['REQUEST_URI'];
        if (strpos($currentPath, '/admin/') !== false || strpos($currentPath, '/instructor/') !== false || strpos($currentPath, '/student/') !== false) {
            header('Location: ../unauthorized.php');
        } else {
            header('Location: unauthorized.php');
        }
        exit;
    }
    
    /**
     * Handle authentication failure
     */
    public function handleAuthFailure(): void
    {
        if (!$this->check()) {
            $this->redirectToLogin();
        }
    }
    
    /**
     * Handle authorization failure
     */
    public function handleAuthorizationFailure(string $requiredRole): void
    {
        if (!$this->requireRole($requiredRole)) {
            $this->redirectToUnauthorized();
        }
    }
}
