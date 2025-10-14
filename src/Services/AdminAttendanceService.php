<?php

namespace App\Services;

use App\Utils\Database;
use PDO;

class AdminAttendanceService
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get system-wide attendance statistics
     */
    public function getSystemStats($dateFrom = null, $dateTo = null, $sectionId = null, $instructorId = null)
    {
        $whereConditions = ["ar.time_in IS NOT NULL"];
        $params = [];

        if ($dateFrom && $dateTo) {
            $whereConditions[] = "DATE(ar.date) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }

        if ($sectionId) {
            $whereConditions[] = "u.section_id = ?";
            $params[] = $sectionId;
        }

        if ($instructorId) {
            $whereConditions[] = "s.instructor_id = ?";
            $params[] = $instructorId;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT ar.student_id) as total_students,
                COUNT(ar.id) as total_attendance_records,
                COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as completed_attendance,
                COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NULL THEN 1 END) as incomplete_attendance,
                ROUND(AVG(ar.hours_earned), 2) as avg_hours_per_record,
                SUM(ar.hours_earned) as total_hours_earned,
                ROUND(COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) * 100.0 / COUNT(ar.id), 2) as completion_rate
            FROM attendance_records ar
            INNER JOIN users u ON ar.student_id = u.id
            LEFT JOIN sections s ON u.section_id = s.id
            WHERE {$whereClause}
        ");
        $stmt->execute($params);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get attendance trends over time
     */
    public function getAttendanceTrends($days = 30, $dateFrom = null, $dateTo = null)
    {
        $whereConditions = ["ar.time_in IS NOT NULL"];
        $params = [];

        if ($dateFrom && $dateTo) {
            $whereConditions[] = "DATE(ar.date) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        } else {
            $whereConditions[] = "ar.date >= DATE_SUB(CURDATE(), INTERVAL ? DAY)";
            $params[] = $days;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $stmt = $this->pdo->prepare("
            SELECT 
                DATE(ar.date) as attendance_date,
                COUNT(DISTINCT ar.student_id) as students_attended,
                COUNT(ar.id) as total_records,
                COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as completed_records,
                SUM(ar.hours_earned) as total_hours,
                ROUND(AVG(ar.hours_earned), 2) as avg_hours_per_student
            FROM attendance_records ar
            INNER JOIN users u ON ar.student_id = u.id
            LEFT JOIN sections s ON u.section_id = s.id
            WHERE {$whereClause}
            GROUP BY DATE(ar.date)
            ORDER BY attendance_date DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get section performance metrics
     */
    public function getSectionPerformance($dateFrom = null, $dateTo = null)
    {
        $whereConditions = ["ar.time_in IS NOT NULL"];
        $params = [];

        if ($dateFrom && $dateTo) {
            $whereConditions[] = "DATE(ar.date) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $stmt = $this->pdo->prepare("
            SELECT 
                s.id,
                s.section_name,
                s.section_code,
                u.full_name as instructor_name,
                COUNT(DISTINCT ar.student_id) as students_with_attendance,
                COUNT(ar.id) as total_records,
                COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as completed_records,
                ROUND(COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) * 100.0 / COUNT(ar.id), 2) as completion_rate,
                SUM(ar.hours_earned) as total_hours,
                ROUND(AVG(ar.hours_earned), 2) as avg_hours_per_student
            FROM sections s
            LEFT JOIN users u ON s.instructor_id = u.id
            LEFT JOIN users stu ON stu.section_id = s.id AND stu.role = 'student'
            LEFT JOIN attendance_records ar ON stu.id = ar.student_id AND {$whereClause}
            GROUP BY s.id, s.section_name, s.section_code, u.full_name
            ORDER BY completion_rate DESC, total_hours DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get student performance metrics
     */
    public function getStudentPerformance($dateFrom = null, $dateTo = null, $sectionId = null)
    {
        $whereConditions = ["ar.time_in IS NOT NULL"];
        $params = [];

        if ($dateFrom && $dateTo) {
            $whereConditions[] = "DATE(ar.date) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }

        if ($sectionId) {
            $whereConditions[] = "u.section_id = ?";
            $params[] = $sectionId;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $stmt = $this->pdo->prepare("
            SELECT 
                u.id,
                u.school_id,
                u.full_name,
                s.section_name,
                COUNT(ar.id) as total_records,
                COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as completed_records,
                COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NULL THEN 1 END) as incomplete_records,
                ROUND(COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) * 100.0 / COUNT(ar.id), 2) as completion_rate,
                SUM(ar.hours_earned) as total_hours,
                ROUND(AVG(ar.hours_earned), 2) as avg_hours_per_record,
                MAX(ar.date) as last_attendance_date
            FROM users u
            INNER JOIN sections s ON u.section_id = s.id
            LEFT JOIN attendance_records ar ON u.id = ar.student_id AND {$whereClause}
            WHERE u.role = 'student'
            GROUP BY u.id, u.school_id, u.full_name, s.section_name
            HAVING total_records > 0
            ORDER BY completion_rate DESC, total_hours DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get instructor effectiveness metrics
     */
    public function getInstructorEffectiveness($dateFrom = null, $dateTo = null)
    {
        $whereConditions = ["ar.time_in IS NOT NULL"];
        $params = [];

        if ($dateFrom && $dateTo) {
            $whereConditions[] = "DATE(ar.date) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $stmt = $this->pdo->prepare("
            SELECT 
                inst.id,
                inst.full_name,
                inst.school_id,
                COUNT(DISTINCT s.id) as sections_managed,
                COUNT(DISTINCT ar.student_id) as students_under_supervision,
                COUNT(ar.id) as total_records,
                COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as completed_records,
                ROUND(COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) * 100.0 / COUNT(ar.id), 2) as completion_rate,
                SUM(ar.hours_earned) as total_hours_supervised,
                ROUND(AVG(ar.hours_earned), 2) as avg_hours_per_student
            FROM users inst
            INNER JOIN sections s ON inst.id = s.instructor_id
            INNER JOIN users stu ON stu.section_id = s.id AND stu.role = 'student'
            INNER JOIN attendance_records ar ON stu.id = ar.student_id AND {$whereClause}
            WHERE inst.role = 'instructor'
            GROUP BY inst.id, inst.full_name, inst.school_id
            ORDER BY completion_rate DESC, total_hours_supervised DESC
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get compliance analysis
     */
    public function getComplianceAnalysis($dateFrom = null, $dateTo = null)
    {
        $whereConditions = ["ar.time_in IS NOT NULL"];
        $params = [];

        if ($dateFrom && $dateTo) {
            $whereConditions[] = "DATE(ar.date) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }

        $whereClause = implode(' AND ', $whereConditions);

        $stmt = $this->pdo->prepare("
            SELECT 
                'Overall' as category,
                COUNT(DISTINCT ar.student_id) as total_students,
                COUNT(ar.id) as total_records,
                COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as compliant_records,
                ROUND(COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) * 100.0 / COUNT(ar.id), 2) as compliance_rate
            FROM attendance_records ar
            INNER JOIN users u ON ar.student_id = u.id
            WHERE {$whereClause}
            
            UNION ALL
            
            SELECT 
                'By Section' as category,
                COUNT(DISTINCT ar.student_id) as total_students,
                COUNT(ar.id) as total_records,
                COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as compliant_records,
                ROUND(COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) * 100.0 / COUNT(ar.id), 2) as compliance_rate
            FROM attendance_records ar
            INNER JOIN users u ON ar.student_id = u.id
            INNER JOIN sections s ON u.section_id = s.id
            WHERE {$whereClause}
            GROUP BY s.id
        ");
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get system usage statistics
     */
    public function getSystemUsageStats($dateFrom = null, $dateTo = null)
    {
        $whereConditions = [];
        $params = [];

        if ($dateFrom && $dateTo) {
            $whereConditions[] = "DATE(created_at) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }

        $whereClause = $whereConditions ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        // Get user registration stats
        $stmt = $this->pdo->prepare("
            SELECT 
                role,
                COUNT(*) as count,
                DATE(created_at) as registration_date
            FROM users 
            {$whereClause}
            GROUP BY role, DATE(created_at)
            ORDER BY registration_date DESC
        ");
        $stmt->execute($params);
        $userStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get activity logs
        $stmt = $this->pdo->prepare("
            SELECT 
                action,
                COUNT(*) as count,
                DATE(created_at) as activity_date
            FROM activity_logs 
            {$whereClause}
            GROUP BY action, DATE(created_at)
            ORDER BY activity_date DESC
        ");
        $stmt->execute($params);
        $activityStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'user_registrations' => $userStats,
            'activity_logs' => $activityStats
        ];
    }

    /**
     * Get attendance patterns analysis
     */
    public function getAttendancePatterns($dateFrom = null, $dateTo = null)
    {
        $whereConditions = ["ar.time_in IS NOT NULL"];
        $params = [];

        if ($dateFrom && $dateTo) {
            $whereConditions[] = "DATE(ar.date) BETWEEN ? AND ?";
            $params[] = $dateFrom;
            $params[] = $dateTo;
        }

        $whereClause = implode(' AND ', $whereConditions);

        // Get attendance by day of week
        $stmt = $this->pdo->prepare("
            SELECT 
                DAYNAME(ar.date) as day_of_week,
                DAYOFWEEK(ar.date) as day_number,
                COUNT(ar.id) as attendance_count,
                COUNT(DISTINCT ar.student_id) as unique_students,
                AVG(ar.hours_earned) as avg_hours
            FROM attendance_records ar
            INNER JOIN users u ON ar.student_id = u.id
            WHERE {$whereClause}
            GROUP BY DAYOFWEEK(ar.date), DAYNAME(ar.date)
            ORDER BY day_number
        ");
        $stmt->execute($params);
        $dayPatterns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Get attendance by block type
        $stmt = $this->pdo->prepare("
            SELECT 
                ar.block_type,
                COUNT(ar.id) as attendance_count,
                COUNT(DISTINCT ar.student_id) as unique_students,
                AVG(ar.hours_earned) as avg_hours,
                SUM(ar.hours_earned) as total_hours
            FROM attendance_records ar
            INNER JOIN users u ON ar.student_id = u.id
            WHERE {$whereClause}
            GROUP BY ar.block_type
            ORDER BY total_hours DESC
        ");
        $stmt->execute($params);
        $blockPatterns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'day_of_week' => $dayPatterns,
            'block_type' => $blockPatterns
        ];
    }
}
