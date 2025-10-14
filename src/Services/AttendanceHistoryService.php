<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Attendance History Service
 * Handles student attendance history and hours tracking
 */
class AttendanceHistoryService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get attendance history for a student
     */
    public function getAttendanceHistory(
        int $studentId, 
        string $dateRange = 'all', 
        int $limit = 20, 
        int $offset = 0
    ): array {
        try {
            $whereClause = "WHERE ar.student_id = ?";
            $params = [$studentId];

            // Add date range filter
            switch ($dateRange) {
                case 'week':
                    $whereClause .= " AND ar.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
                    break;
                case 'month':
                    $whereClause .= " AND ar.date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)";
                    break;
                case 'semester':
                    $whereClause .= " AND ar.date >= DATE_SUB(CURDATE(), INTERVAL 120 DAY)";
                    break;
                // 'all' - no additional filter
            }

            $stmt = $this->pdo->prepare("
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
                    ar.created_at,
                    ar.updated_at,
                    ftr.id as forgot_timeout_request_id,
                    ftr.status as forgot_timeout_status,
                    ftr.instructor_response
                FROM attendance_records ar
                LEFT JOIN forgot_timeout_requests ftr ON ar.id = ftr.attendance_record_id
                {$whereClause}
                ORDER BY ar.date DESC, ar.time_in DESC
                LIMIT ? OFFSET ?
            ");

            $params[] = $limit;
            $params[] = $offset;

            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("AttendanceHistoryService::getAttendanceHistory error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get attendance statistics for a student
     */
    public function getAttendanceStats(int $studentId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_records,
                    SUM(CASE WHEN time_in IS NOT NULL AND time_out IS NOT NULL THEN 1 ELSE 0 END) as completed_records,
                    SUM(CASE WHEN time_in IS NOT NULL AND time_out IS NULL THEN 1 ELSE 0 END) as incomplete_records,
                    SUM(CASE WHEN time_in IS NULL THEN 1 ELSE 0 END) as missed_records,
                    COALESCE(SUM(hours_earned), 0) as total_hours,
                    COALESCE(SUM(CASE WHEN block_type = 'morning' THEN hours_earned ELSE 0 END), 0) as morning_hours,
                    COALESCE(SUM(CASE WHEN block_type = 'afternoon' THEN hours_earned ELSE 0 END), 0) as afternoon_hours,
                    COALESCE(SUM(CASE WHEN block_type = 'overtime' THEN hours_earned ELSE 0 END), 0) as overtime_hours
                FROM attendance_records 
                WHERE student_id = ?
            ");

            $stmt->execute([$studentId]);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Calculate additional metrics
            $stats['completion_rate'] = $stats['total_records'] > 0 
                ? round(($stats['completed_records'] / $stats['total_records']) * 100, 1) 
                : 0;

            $stats['required_hours'] = 600; // OJT requirement
            $stats['hours_progress'] = $stats['total_hours'] > 0 
                ? round(($stats['total_hours'] / $stats['required_hours']) * 100, 1) 
                : 0;

            return $stats;

        } catch (Exception $e) {
            error_log("AttendanceHistoryService::getAttendanceStats error: " . $e->getMessage());
            return [
                'total_records' => 0,
                'completed_records' => 0,
                'incomplete_records' => 0,
                'missed_records' => 0,
                'total_hours' => 0,
                'morning_hours' => 0,
                'afternoon_hours' => 0,
                'overtime_hours' => 0,
                'completion_rate' => 0,
                'required_hours' => 600,
                'hours_progress' => 0
            ];
        }
    }

    /**
     * Get weekly hours breakdown
     */
    public function getWeeklyHours(int $studentId, int $weeks = 4): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    YEARWEEK(ar.date) as week_number,
                    MIN(ar.date) as week_start,
                    MAX(ar.date) as week_end,
                    COALESCE(SUM(ar.hours_earned), 0) as weekly_hours,
                    COUNT(*) as total_days,
                    SUM(CASE WHEN ar.time_in IS NOT NULL AND ar.time_out IS NOT NULL THEN 1 ELSE 0 END) as completed_days
                FROM attendance_records ar
                WHERE ar.student_id = ? 
                AND ar.date >= DATE_SUB(CURDATE(), INTERVAL ? WEEK)
                GROUP BY YEARWEEK(ar.date)
                ORDER BY week_number DESC
                LIMIT ?
            ");

            $stmt->execute([$studentId, $weeks, $weeks]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("AttendanceHistoryService::getWeeklyHours error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get detailed attendance record
     */
    public function getAttendanceDetail(int $recordId, int $studentId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    ar.*,
                    ftr.id as forgot_timeout_request_id,
                    ftr.status as forgot_timeout_status,
                    ftr.instructor_response,
                    ftr.created_at as request_created_at
                FROM attendance_records ar
                LEFT JOIN forgot_timeout_requests ftr ON ar.id = ftr.attendance_record_id
                WHERE ar.id = ? AND ar.student_id = ?
            ");

            $stmt->execute([$recordId, $studentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;

        } catch (Exception $e) {
            error_log("AttendanceHistoryService::getAttendanceDetail error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get attendance status for a record
     */
    public function getAttendanceStatus(array $record): string
    {
        if ($record['time_in'] === null) {
            return 'missed';
        }
        
        if ($record['time_out'] === null) {
            if ($record['forgot_timeout_request_id'] && $record['forgot_timeout_status'] === 'pending') {
                return 'pending_request';
            }
            return 'incomplete';
        }
        
        return 'completed';
    }

    /**
     * Get status color class
     */
    public function getStatusColorClass(string $status): string
    {
        return match($status) {
            'completed' => 'success',
            'incomplete' => 'warning',
            'missed' => 'danger',
            'pending_request' => 'info',
            default => 'secondary'
        };
    }

    /**
     * Get status display text
     */
    public function getStatusDisplayText(string $status): string
    {
        return match($status) {
            'completed' => 'Completed',
            'incomplete' => 'Time-in Only',
            'missed' => 'Missed',
            'pending_request' => 'Pending Request',
            default => 'Unknown'
        };
    }
}
