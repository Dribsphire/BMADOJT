<?php

namespace App\Services;

use PDO;
use App\Utils\Database;

class SectionAttendanceService
{
    private $pdo;
    
    public function __construct(PDO $pdo = null)
    {
        $this->pdo = $pdo ?: Database::getInstance();
    }
    
    /**
     * Get all students in instructor's sections with attendance data
     */
    public function getSectionStudents($instructorId, $filters = [])
    {
        $whereConditions = ["u.role = 'student'", "s.instructor_id = ?"];
        $params = [$instructorId];
        
        // Apply filters
        if (!empty($filters['section_id'])) {
            $whereConditions[] = "s.id = ?";
            $params[] = $filters['section_id'];
        }
        
        if (!empty($filters['status'])) {
            $whereConditions[] = "sp.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $whereConditions[] = "(u.school_id LIKE ? OR u.full_name LIKE ? OR sp.workplace_name LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "
            SELECT 
                u.id,
                u.school_id,
                u.full_name,
                u.email,
                sp.workplace_name,
                sp.status,
                s.section_name,
                s.section_code,
                COALESCE(att_stats.total_hours, 0) as total_hours,
                COALESCE(att_stats.completed_records, 0) as completed_records,
                COALESCE(att_stats.incomplete_records, 0) as incomplete_records,
                COALESCE(att_stats.missed_records, 0) as missed_records,
                COALESCE(att_stats.total_records, 0) as total_records,
                COALESCE(att_stats.completion_rate, 0) as completion_rate,
                COALESCE(att_stats.today_status, 'no_attendance') as today_status,
                COALESCE(att_stats.today_hours, 0) as today_hours,
                COALESCE(ftr_stats.pending_requests, 0) as pending_requests
            FROM users u
            INNER JOIN sections s ON u.section_id = s.id
            LEFT JOIN student_profiles sp ON u.id = sp.user_id
            LEFT JOIN (
                SELECT 
                    ar.student_id,
                    SUM(ar.hours_earned) as total_hours,
                    COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as completed_records,
                    COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NULL THEN 1 END) as incomplete_records,
                    COUNT(CASE WHEN ar.time_in IS NULL AND ar.time_out IS NULL THEN 1 END) as missed_records,
                    COUNT(*) as total_records,
                    ROUND(
                        (COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) * 100.0 / COUNT(*)), 2
                    ) as completion_rate,
                    CASE 
                        WHEN MAX(CASE WHEN DATE(ar.date) = CURDATE() THEN ar.time_in END) IS NOT NULL 
                             AND MAX(CASE WHEN DATE(ar.date) = CURDATE() THEN ar.time_out END) IS NOT NULL 
                        THEN 'completed'
                        WHEN MAX(CASE WHEN DATE(ar.date) = CURDATE() THEN ar.time_in END) IS NOT NULL 
                             AND MAX(CASE WHEN DATE(ar.date) = CURDATE() THEN ar.time_out END) IS NULL 
                        THEN 'incomplete'
                        WHEN MAX(CASE WHEN DATE(ar.date) = CURDATE() THEN ar.time_in END) IS NULL 
                             AND MAX(CASE WHEN DATE(ar.date) = CURDATE() THEN ar.time_out END) IS NULL 
                             AND MAX(CASE WHEN DATE(ar.date) = CURDATE() THEN 1 END) IS NOT NULL
                        THEN 'missed'
                        ELSE 'no_attendance'
                    END as today_status,
                    COALESCE(SUM(CASE WHEN DATE(ar.date) = CURDATE() THEN ar.hours_earned ELSE 0 END), 0) as today_hours
                FROM attendance_records ar
                GROUP BY ar.student_id
            ) att_stats ON u.id = att_stats.student_id
            LEFT JOIN (
                SELECT 
                    ftr.student_id,
                    COUNT(CASE WHEN ftr.status = 'pending' THEN 1 END) as pending_requests
                FROM forgot_timeout_requests ftr
                GROUP BY ftr.student_id
            ) ftr_stats ON u.id = ftr_stats.student_id
            WHERE $whereClause
            ORDER BY u.full_name
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get student's detailed attendance history
     */
    public function getStudentAttendanceHistory($studentId, $dateRange = null)
    {
        $whereConditions = ["ar.student_id = ?"];
        $params = [$studentId];
        
        if ($dateRange && isset($dateRange['start']) && isset($dateRange['end'])) {
            $whereConditions[] = "ar.date BETWEEN ? AND ?";
            $params[] = $dateRange['start'];
            $params[] = $dateRange['end'];
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "
            SELECT 
                ar.id,
                ar.date,
                ar.block_type,
                ar.time_in,
                ar.time_out,
                ar.hours_earned,
                ar.location_lat_in,
                ar.location_long_in,
                ar.location_lat_out,
                ar.location_long_out,
                ar.photo_path,
                ftr.id as forgot_timeout_request_id,
                ftr.status as forgot_timeout_status,
                ftr.instructor_response,
                ftr.created_at as request_created_at
            FROM attendance_records ar
            LEFT JOIN forgot_timeout_requests ftr ON ar.id = ftr.attendance_record_id
            WHERE $whereClause
            ORDER BY ar.date DESC, 
                CASE ar.block_type 
                    WHEN 'morning' THEN 1 
                    WHEN 'afternoon' THEN 2 
                    WHEN 'overtime' THEN 3 
                END
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get section attendance analytics
     */
    public function getSectionAnalytics($instructorId, $dateRange = null)
    {
        $whereConditions = ["s.instructor_id = ?"];
        $params = [$instructorId];
        
        if ($dateRange && isset($dateRange['start']) && isset($dateRange['end'])) {
            $whereConditions[] = "ar.date BETWEEN ? AND ?";
        }
        
        $whereClause = implode(' AND ', $whereConditions);
        
        $sql = "
            SELECT 
                s.id as section_id,
                s.section_name,
                s.section_code,
                COUNT(DISTINCT u.id) as total_students,
                COUNT(DISTINCT ar.student_id) as students_with_attendance,
                COALESCE(SUM(ar.hours_earned), 0) as total_section_hours,
                COALESCE(AVG(ar.hours_earned), 0) as avg_hours_per_student,
                COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as completed_attendance,
                COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NULL THEN 1 END) as incomplete_attendance,
                COUNT(CASE WHEN ar.time_in IS NULL AND ar.time_out IS NULL THEN 1 END) as missed_attendance,
                COUNT(*) as total_attendance_records,
                ROUND(
                    (COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) * 100.0 / COUNT(*)), 2
                ) as section_completion_rate
            FROM sections s
            LEFT JOIN users u ON s.id = u.section_id AND u.role = 'student'
            LEFT JOIN attendance_records ar ON u.id = ar.student_id" . 
            ($dateRange ? " AND ar.date BETWEEN ? AND ?" : "") . "
            WHERE $whereClause
            GROUP BY s.id, s.section_name, s.section_code
        ";
        
        if ($dateRange) {
            $params[] = $dateRange['start'];
            $params[] = $dateRange['end'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get students with low attendance (at-risk students)
     */
    public function getAtRiskStudents($instructorId, $threshold = 70)
    {
        $sql = "
            SELECT 
                u.id,
                u.school_id,
                u.full_name,
                sp.workplace_name,
                s.section_name,
                COALESCE(att_stats.total_hours, 0) as total_hours,
                COALESCE(att_stats.completion_rate, 0) as completion_rate,
                COALESCE(att_stats.missed_records, 0) as missed_records,
                COALESCE(ftr_stats.pending_requests, 0) as pending_requests
            FROM users u
            INNER JOIN sections s ON u.section_id = s.id
            LEFT JOIN student_profiles sp ON u.id = sp.user_id
            LEFT JOIN (
                SELECT 
                    ar.student_id,
                    SUM(ar.hours_earned) as total_hours,
                    ROUND(
                        (COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) * 100.0 / COUNT(*)), 2
                    ) as completion_rate,
                    COUNT(CASE WHEN ar.time_in IS NULL AND ar.time_out IS NULL THEN 1 END) as missed_records
                FROM attendance_records ar
                GROUP BY ar.student_id
            ) att_stats ON u.id = att_stats.student_id
            LEFT JOIN (
                SELECT 
                    ftr.student_id,
                    COUNT(CASE WHEN ftr.status = 'pending' THEN 1 END) as pending_requests
                FROM forgot_timeout_requests ftr
                GROUP BY ftr.student_id
            ) ftr_stats ON u.id = ftr_stats.student_id
            WHERE s.instructor_id = ? AND u.role = 'student'
            AND (att_stats.completion_rate < ? OR att_stats.completion_rate IS NULL)
            ORDER BY att_stats.completion_rate ASC, u.full_name
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$instructorId, $threshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get attendance trends by day/week
     */
    public function getAttendanceTrends($instructorId, $period = 'week')
    {
        $dateFormat = $period === 'week' ? '%Y-%u' : '%Y-%m-%d';
        $groupBy = $period === 'week' ? 'YEAR(ar.date), WEEK(ar.date)' : 'ar.date';
        
        $sql = "
            SELECT 
                DATE_FORMAT(ar.date, '$dateFormat') as period,
                COUNT(DISTINCT ar.student_id) as students_attended,
                COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 END) as completed_attendance,
                COUNT(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NULL THEN 1 END) as incomplete_attendance,
                COUNT(CASE WHEN ar.time_in IS NULL AND ar.time_out IS NULL THEN 1 END) as missed_attendance,
                COALESCE(SUM(ar.hours_earned), 0) as total_hours
            FROM attendance_records ar
            INNER JOIN users u ON ar.student_id = u.id
            INNER JOIN sections s ON u.section_id = s.id
            WHERE s.instructor_id = ?
            GROUP BY $groupBy
            ORDER BY ar.date DESC
            LIMIT 12
        ";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$instructorId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}