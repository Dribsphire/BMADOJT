<?php

namespace App\Services;

use App\Utils\Database;
use PDO;
use Exception;

/**
 * Workplace Edit Request Service
 * Handles workplace information edit requests from students
 */
class WorkplaceEditRequestService
{
    private PDO $pdo;
    
    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }
    
    /**
     * Submit a workplace edit request
     */
    public function submitRequest(int $studentId, string $reason = ''): array
    {
        try {
            // Check if there's already a pending request
            $stmt = $this->pdo->prepare("
                SELECT workplace_edit_request_status 
                FROM student_profiles 
                WHERE user_id = ? AND workplace_edit_request_status = 'pending'
            ");
            $stmt->execute([$studentId]);
            
            if ($stmt->fetch()) {
                return [
                    'success' => false,
                    'message' => 'You already have a pending workplace edit request.'
                ];
            }
            
            // Submit new request
            $stmt = $this->pdo->prepare("
                UPDATE student_profiles 
                SET workplace_edit_request_status = 'pending',
                    workplace_edit_request_date = NOW(),
                    workplace_edit_request_reason = ?
                WHERE user_id = ?
            ");
            
            $stmt->execute([$reason, $studentId]);
            
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Workplace edit request submitted successfully.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to submit request. Please try again.'
                ];
            }
            
        } catch (Exception $e) {
            error_log("WorkplaceEditRequestService::submitRequest error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while submitting your request.'
            ];
        }
    }
    
    /**
     * Get pending requests for instructor's section
     */
    public function getPendingRequestsForInstructor(int $instructorId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    sp.*,
                    u.full_name as student_name,
                    u.school_id,
                    u.email as student_email,
                    s.section_name
                FROM student_profiles sp
                JOIN users u ON sp.user_id = u.id
                JOIN sections s ON u.section_id = s.id
                JOIN users instructor ON s.id = instructor.section_id
                WHERE instructor.id = ? 
                AND sp.workplace_edit_request_status = 'pending'
                ORDER BY sp.workplace_edit_request_date ASC
            ");
            
            $stmt->execute([$instructorId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("WorkplaceEditRequestService::getPendingRequestsForInstructor error: " . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get request status for student
     */
    public function getRequestStatus(int $studentId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    workplace_edit_request_status,
                    workplace_edit_request_date,
                    workplace_edit_request_reason,
                    workplace_edit_approved_at,
                    workplace_edit_hours_decision
                FROM student_profiles 
                WHERE user_id = ?
            ");
            
            $stmt->execute([$studentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: [
                'workplace_edit_request_status' => 'none',
                'workplace_edit_request_date' => null,
                'workplace_edit_request_reason' => null,
                'workplace_edit_approved_at' => null,
                'workplace_edit_hours_decision' => null
            ];
            
        } catch (Exception $e) {
            error_log("WorkplaceEditRequestService::getRequestStatus error: " . $e->getMessage());
            return [
                'workplace_edit_request_status' => 'none',
                'workplace_edit_request_date' => null,
                'workplace_edit_request_reason' => null,
                'workplace_edit_approved_at' => null,
                'workplace_edit_hours_decision' => null
            ];
        }
    }
    
    /**
     * Approve workplace edit request
     */
    public function approveRequest(int $studentId, int $instructorId, string $hoursDecision): array
    {
        try {
            $this->pdo->beginTransaction();
            
            // Update request status
            $stmt = $this->pdo->prepare("
                UPDATE student_profiles 
                SET workplace_edit_request_status = 'approved',
                    workplace_edit_approved_by = ?,
                    workplace_edit_approved_at = NOW(),
                    workplace_edit_hours_decision = ?,
                    workplace_location_locked = 0
                WHERE user_id = ? AND workplace_edit_request_status = 'pending'
            ");
            
            $stmt->execute([$instructorId, $hoursDecision, $studentId]);
            
            if ($stmt->rowCount() === 0) {
                $this->pdo->rollBack();
                return [
                    'success' => false,
                    'message' => 'Request not found or already processed.'
                ];
            }
            
            // If hours decision is 'reset', reset accumulated hours
            if ($hoursDecision === 'reset') {
                $stmt = $this->pdo->prepare("
                    UPDATE student_profiles 
                    SET total_hours_accumulated = 0 
                    WHERE user_id = ?
                ");
                $stmt->execute([$studentId]);
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => 'Workplace edit request approved successfully.'
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("WorkplaceEditRequestService::approveRequest error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while approving the request.'
            ];
        }
    }
    
    /**
     * Deny workplace edit request
     */
    public function denyRequest(int $studentId, int $instructorId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE student_profiles 
                SET workplace_edit_request_status = 'denied',
                    workplace_edit_approved_by = ?,
                    workplace_edit_approved_at = NOW()
                WHERE user_id = ? AND workplace_edit_request_status = 'pending'
            ");
            
            $stmt->execute([$instructorId, $studentId]);
            
            if ($stmt->rowCount() > 0) {
                return [
                    'success' => true,
                    'message' => 'Workplace edit request denied.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Request not found or already processed.'
                ];
            }
            
        } catch (Exception $e) {
            error_log("WorkplaceEditRequestService::denyRequest error: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'An error occurred while denying the request.'
            ];
        }
    }
    
    /**
     * Get count of pending requests for instructor
     */
    public function getPendingRequestsCount(int $instructorId): int
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM student_profiles sp
                JOIN users u ON sp.user_id = u.id
                JOIN sections s ON u.section_id = s.id
                JOIN users instructor ON s.id = instructor.section_id
                WHERE instructor.id = ? 
                AND sp.workplace_edit_request_status = 'pending'
            ");
            
            $stmt->execute([$instructorId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return (int) $result['count'];
            
        } catch (Exception $e) {
            error_log("WorkplaceEditRequestService::getPendingRequestsCount error: " . $e->getMessage());
            return 0;
        }
    }
}
