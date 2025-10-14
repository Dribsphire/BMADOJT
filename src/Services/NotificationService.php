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
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as count
                FROM attendance_records ar
                INNER JOIN users u ON ar.student_id = u.id
                INNER JOIN sections s ON u.section_id = s.id
                WHERE s.instructor_id = ? 
                AND ar.time_in IS NOT NULL
                AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $stmt->execute([$instructorId]);
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
            
            if ($lastVisit) {
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM attendance_records ar
                    INNER JOIN users u ON ar.student_id = u.id
                    INNER JOIN sections s ON u.section_id = s.id
                    WHERE s.instructor_id = ? 
                    AND ar.time_in IS NOT NULL
                    AND ar.created_at > ?
                ");
                $stmt->execute([$instructorId, $lastVisit]);
            } else {
                // If no last visit, get records from last 7 days
                $stmt = $this->pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM attendance_records ar
                    INNER JOIN users u ON ar.student_id = u.id
                    INNER JOIN sections s ON u.section_id = s.id
                    WHERE s.instructor_id = ? 
                    AND ar.time_in IS NOT NULL
                    AND ar.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                ");
                $stmt->execute([$instructorId]);
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
}
