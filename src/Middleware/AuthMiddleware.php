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
        
        // Check if user is acting in a different role
        if (isset($_SESSION['acting_role'])) {
            return $_SESSION['acting_role'] === $role;
        }
        
        // For admin role, also check if user is admin (even when not acting as admin)
        if ($role === 'admin') {
            $user = $this->getCurrentUser();
            return $user && $user->role === 'admin';
        }
        
        return $this->authService->hasRole($role);
    }
    
    /**
     * Require any of the specified roles
     */
    public function requireAnyRole(array $roles): bool
    {
        if (!$this->check()) {
            return false;
        }
        
        return $this->authService->hasAnyRole($roles);
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
