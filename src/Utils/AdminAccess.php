<?php

namespace App\Utils;

use App\Middleware\AuthMiddleware;

/**
 * Admin Access Utility
 * OJT Route - Helper functions for admin access control
 */
class AdminAccess
{
    /**
     * Check if user has admin access (including acting as instructor)
     */
    public static function hasAdminAccess(): bool
    {
        $authMiddleware = new AuthMiddleware();
        
        // Check if user is authenticated
        if (!$authMiddleware->check()) {
            return false;
        }
        
        // Get current user
        $user = $authMiddleware->getCurrentUser();
        
        // Check if user is admin (regardless of acting role)
        $isAdmin = $user && $user->role === 'admin';
        
        // Check if admin is acting as instructor
        $isActingAsInstructor = isset($_SESSION['acting_role']) && 
                               $_SESSION['acting_role'] === 'instructor' && 
                               $user && $user->role === 'admin';
        
        return $isAdmin || $isActingAsInstructor;
    }
    
    /**
     * Require admin access or redirect to unauthorized
     */
    public static function requireAdminAccess(): void
    {
        if (!self::hasAdminAccess()) {
            $authMiddleware = new AuthMiddleware();
            $authMiddleware->redirectToUnauthorized();
        }
    }
    
    /**
     * Get current user with admin access check
     */
    public static function getCurrentUser()
    {
        $authMiddleware = new AuthMiddleware();
        
        if (!self::hasAdminAccess()) {
            $authMiddleware->redirectToUnauthorized();
        }
        
        return $authMiddleware->getCurrentUser();
    }
}
