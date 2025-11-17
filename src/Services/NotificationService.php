<?php

namespace App\Services;

use App\Utils\Database;

class NotificationService
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Get the count of new attendance records for an instructor
     * New records are those created in the last 24 hours
     */
    public function getNewAttendanceCount($instructorId)
    {
        try {
            // Check both instructor_sections junction table and old section_id way
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM attendance_records ar
                INNER JOIN users u ON ar.student_id = u.id
                INNER JOIN sections s ON u.section_id = s.id
                LEFT JOIN instructor_sections is_rel ON is_rel.section_id = s.id
                WHERE (
                    is_rel.instructor_id = ? 
                    OR s.instructor_id = ?
                    OR (SELECT section_id FROM users WHERE id = ? AND role = 'instructor') = s.id
                )
                AND ar.time_in IS NOT NULL
                AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute([$instructorId, $instructorId, $instructorId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            return (int)$result['count'];
        } catch (\Exception $e) {
            // Log error and return 0 to prevent breaking the UI
            error_log("NotificationService error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get the count of new attendance records for an instructor (alternative method)
     * New records are those created since the last time the instructor visited the attendance page
     */
    public function getNewAttendanceCountSinceLastVisit($instructorId)
    {
        try {
            // Get the last visit timestamp from session or database
            $lastVisit = $_SESSION['last_attendance_visit'] ?? null;
            
            // Check both instructor_sections junction table and old section_id way
            if ($lastVisit) {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM attendance_records ar
                    INNER JOIN users u ON ar.student_id = u.id
                    INNER JOIN sections s ON u.section_id = s.id
                    LEFT JOIN instructor_sections is_rel ON is_rel.section_id = s.id
                    WHERE (
                        is_rel.instructor_id = ? 
                        OR s.instructor_id = ?
                        OR (SELECT section_id FROM users WHERE id = ? AND role = 'instructor') = s.id
                    )
                    AND ar.time_in IS NOT NULL
                    AND ar.created_at > ?
                ");
                $stmt->execute([$instructorId, $instructorId, $instructorId, $lastVisit]);
            } else {
                // If no last visit, get records from last 7 days
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM attendance_records ar
                    INNER JOIN users u ON ar.student_id = u.id
                    INNER JOIN sections s ON u.section_id = s.id
                    LEFT JOIN instructor_sections is_rel ON is_rel.section_id = s.id
                    WHERE (
                        is_rel.instructor_id = ? 
                        OR s.instructor_id = ?
                        OR (SELECT section_id FROM users WHERE id = ? AND role = 'instructor') = s.id
                    )
                    AND ar.time_in IS NOT NULL
                    AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ");
                $stmt->execute([$instructorId, $instructorId, $instructorId]);
            }
            
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            return (int)$result['count'];
        } catch (\Exception $e) {
            error_log("NotificationService error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Mark attendance page as visited (reset notification count)
     */
    public function markAttendancePageVisited($instructorId)
    {
        $_SESSION['last_attendance_visit'] = date('Y-m-d H:i:s');
    }

    /**
     * Get the count of pending documents submitted by students for an instructor
     * Counts all pending documents (not distinct students) from instructor's assigned sections
     */
    public function getPendingDocumentsCount($instructorId)
    {
        try {
            // First, check if instructor has any assigned sections
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM instructor_sections 
                WHERE instructor_id = ?
            ");
            $stmt->execute([$instructorId]);
            $hasSections = $stmt->fetchColumn() > 0;
            
            if (!$hasSections) {
                // Fallback: check if instructor has section_id in users table
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) 
                    FROM users 
                    WHERE id = ? AND role = 'instructor' AND section_id IS NOT NULL
                ");
                $stmt->execute([$instructorId]);
                $hasSections = $stmt->fetchColumn() > 0;
                
                if (!$hasSections) {
                    return 0;
                }
            }
            
            // Count all pending documents submitted by students from instructor's assigned sections
            // Check both instructor_sections junction table and old section_id way
            $stmt = $this->pdo->prepare("
                SELECT COUNT(sd.id) as count
                FROM student_documents sd
                INNER JOIN users u ON sd.student_id = u.id AND u.role = 'student'
                LEFT JOIN instructor_sections is_rel ON is_rel.section_id = u.section_id
                WHERE (
                    is_rel.instructor_id = ? 
                    OR (SELECT section_id FROM users WHERE id = ? AND role = 'instructor') = u.section_id
                )
                AND sd.status = 'pending'
            ");
            $stmt->execute([$instructorId, $instructorId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $count = (int)($result['count'] ?? 0);
            
            // Debug logging
            error_log("NotificationService::getPendingDocumentsCount - Instructor ID: $instructorId, Document Count: $count");
            
            return $count;
        } catch (\Exception $e) {
            // Log error and return 0 to prevent breaking the UI
            error_log("NotificationService::getPendingDocumentsCount error: " . $e->getMessage());
            error_log("NotificationService::getPendingDocumentsCount stack trace: " . $e->getTraceAsString());
            return 0;
        }
    }

    /**
     * Get the count of students with overdue documents for an instructor
     * Counts distinct students who have overdue documents from instructor's assigned sections
     */
    public function getOverdueDocumentsCount($instructorId)
    {
        try {
            // Check both instructor_sections junction table and old section_id way
            $stmt = $this->pdo->prepare("
                SELECT COUNT(DISTINCT u.id) as count
                FROM documents d
                INNER JOIN users u ON d.uploaded_for_section = u.section_id
                LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = u.id
                LEFT JOIN instructor_sections is_rel ON is_rel.section_id = u.section_id
                WHERE (
                    is_rel.instructor_id = ? 
                    OR (SELECT section_id FROM users WHERE id = ? AND role = 'instructor') = u.section_id
                )
                AND d.deadline IS NOT NULL 
                AND d.deadline < CURDATE()
                AND (sd.id IS NULL OR sd.status IN ('pending', 'revision_required', 'rejected'))
                AND u.role = 'student'
            ");
            $stmt->execute([$instructorId, $instructorId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $count = (int)($result['count'] ?? 0);
            
            // Debug logging
            error_log("NotificationService::getOverdueDocumentsCount - Instructor ID: $instructorId, Student Count: $count");
            
            return $count;
        } catch (\Exception $e) {
            error_log("NotificationService::getOverdueDocumentsCount error: " . $e->getMessage());
            return 0;
        }
    }

    /**
     * Get the count of pending forgot timeout requests for an instructor
     * Counts pending requests from students in instructor's assigned sections
     */
    public function getPendingForgotTimeoutCount($instructorId)
    {
        try {
            // Check both instructor_sections junction table and old section_id way
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM forgot_timeout_requests ftr
                INNER JOIN users u ON ftr.student_id = u.id
                LEFT JOIN instructor_sections is_rel ON is_rel.section_id = u.section_id
                WHERE (
                    is_rel.instructor_id = ? 
                    OR (SELECT section_id FROM users WHERE id = ? AND role = 'instructor') = u.section_id
                )
                AND ftr.status = 'pending'
                AND u.role = 'student'
            ");
            $stmt->execute([$instructorId, $instructorId]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $count = (int)($result['count'] ?? 0);
            
            // Debug logging
            error_log("NotificationService::getPendingForgotTimeoutCount - Instructor ID: $instructorId, Count: $count");
            
            return $count;
        } catch (\Exception $e) {
            error_log("NotificationService::getPendingForgotTimeoutCount error: " . $e->getMessage());
            return 0;
        }
    }
}
