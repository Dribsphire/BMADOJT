<?php

namespace App\Services;

use App\Models\User;
use App\Utils\Database;

/**
 * Authentication Service
 * OJT Route - User authentication and session management
 */
class AuthenticationService
{
    private const SESSION_TIMEOUT = 1800; // 30 minutes
    
    /**
     * Authenticate user with school ID and password
     */
    public function authenticate(string $schoolId, string $password): ?User
    {
        $user = User::findBySchoolId($schoolId);
        
        if (!$user) {
            return null;
        }
        
        if (!$user->verifyPassword($password)) {
            return null;
        }
        
        // Check compliance gates
        if (!$this->checkComplianceGates($user)) {
            return null;
        }
        
        return $user;
    }
    
    /**
     * Check compliance gates for user access
     */
    public function checkComplianceGates(User $user): bool
    {
        // Instructor must have assigned section
        if ($user->isInstructor() && !$user->section_id) {
            // Store the user in session for no-section page
            $this->startSession($user);
            header('Location: instructor/no-section.php');
            exit;
        }
        
        // Student must have complete profile and documents
        if ($user->isStudent()) {
            if (!$this->checkStudentCompliance($user)) {
                // Store the user in session for incomplete profile page
                $this->startSession($user);
                header('Location: student/profile.php');
                exit;
            }
        }
        
        return true;
    }
    
    /**
     * Check compliance gates without redirecting (for use in pages)
     */
    public function isCompliant(User $user): bool
    {
        // Instructor must have assigned section
        if ($user->isInstructor() && !$user->section_id) {
            return false;
        }
        
        // Student must have complete profile and documents
        if ($user->isStudent()) {
            return $this->checkStudentCompliance($user);
        }
        
        return true;
    }
    
    /**
     * Check student compliance (profile + documents)
     */
    private function checkStudentCompliance(User $user): bool
    {
        $pdo = Database::getInstance();
        
        // Check if student has profile
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM student_profiles WHERE user_id = ?");
        $stmt->execute([$user->id]);
        $hasProfile = $stmt->fetchColumn() > 0;
        
        if (!$hasProfile) {
            return false;
        }
        
        // Check if student has all 7 required documents approved
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM student_documents sd
            JOIN documents d ON sd.document_id = d.id
            WHERE sd.student_id = ? 
            AND sd.status = 'approved'
            AND d.document_type IN ('moa', 'endorsement', 'parental_consent', 'misdemeanor_penalty', 'ojt_plan', 'notarized_consent', 'pledge')
        ");
        $stmt->execute([$user->id]);
        $approvedDocuments = $stmt->fetchColumn();
        
        return $approvedDocuments >= 7;
    }
    
    /**
     * Start user session
     */
    public function startSession(User $user): void
    {
        session_start();
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Set session data
        $_SESSION['user_id'] = $user->id;
        $_SESSION['school_id'] = $user->school_id;
        $_SESSION['email'] = $user->email;
        $_SESSION['full_name'] = $user->full_name;
        $_SESSION['role'] = $user->role;
        $_SESSION['section_id'] = $user->section_id;
        $_SESSION['login_time'] = time();
        
        // Set session timeout
        $_SESSION['timeout'] = time() + self::SESSION_TIMEOUT;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn(): bool
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['timeout']) && time() > $_SESSION['timeout']) {
            $this->logout();
            return false;
        }
        
        // Extend session timeout
        $_SESSION['timeout'] = time() + self::SESSION_TIMEOUT;
        
        return true;
    }
    
    /**
     * Get current logged-in user
     */
    public function getCurrentUser(): ?User
    {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return User::findById($_SESSION['user_id']);
    }
    
    /**
     * Logout user
     */
    public function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Clear session data
        $_SESSION = [];
        
        // Destroy session
        session_destroy();
    }
    
    /**
     * Check if user has required role
     */
    public function hasRole(string $role): bool
    {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return $_SESSION['role'] === $role;
    }
    
    /**
     * Check if user has any of the required roles
     */
    public function hasAnyRole(array $roles): bool
    {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        return in_array($_SESSION['role'], $roles);
    }
    
    /**
     * Get session timeout remaining time
     */
    public function getSessionTimeRemaining(): int
    {
        if (!$this->isLoggedIn()) {
            return 0;
        }
        
        return max(0, $_SESSION['timeout'] - time());
    }
}
