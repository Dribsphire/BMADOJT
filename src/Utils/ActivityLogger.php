<?php

namespace App\Utils;

use App\Utils\Database;
use PDO;

/**
 * Activity Logger Utility
 * Provides safe activity log insertion with validation
 */
class ActivityLogger
{
    private PDO $pdo;
    
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    
    /**
     * Safely log an activity with proper user_id validation
     */
    public function logActivity(string $action, string $description, ?int $userId = null): bool
    {
        try {
            // Get user_id from parameter, session, or default to admin
            $userId = $userId ?? $_SESSION['user_id'] ?? 1;
            
            // Validate that the user exists
            if (!$this->validateUserId($userId)) {
                error_log("ActivityLogger: Invalid user_id {$userId} for action '{$action}'. Using admin fallback.");
                $userId = 1; // Fallback to admin
            }
            
            // Insert the activity log
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            
            return $stmt->execute([$userId, $action, $description]);
            
        } catch (\Exception $e) {
            error_log("ActivityLogger: Failed to log activity '{$action}': " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Validate that a user_id exists in the database
     */
    private function validateUserId(int $userId): bool
    {
        try {
            $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            return $stmt->fetchColumn() > 0;
        } catch (\Exception $e) {
            error_log("ActivityLogger: Error validating user_id {$userId}: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Log activity with automatic user detection
     */
    public function logActivityAuto(string $action, string $description): bool
    {
        return $this->logActivity($action, $description);
    }
    
    /**
     * Log security events
     */
    public function logSecurityEvent(string $event, array $data): bool
    {
        $description = json_encode([
            'event' => $event,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
        return $this->logActivity('security_event', $description);
    }
    
    /**
     * Log errors
     */
    public function logError(string $errorMessage, ?int $userId = null): bool
    {
        return $this->logActivity('error', $errorMessage, $userId);
    }
}
