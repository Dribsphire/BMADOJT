<?php

namespace App\Services;

use PDO;
use Exception;

/**
 * Forgot Timeout Review Service
 * Handles instructor review of forgot timeout requests
 */
class ForgotTimeoutReviewService
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get forgot timeout requests for instructor's sections
     */
    public function getRequestsForInstructor(int $instructorId, string $status = 'all', int $limit = 50, int $offset = 0): array
    {
        try {
            $whereClause = "WHERE u.section_id IN (
                SELECT section_id FROM users WHERE id = ?
            )";
            $params = [$instructorId];

            if ($status !== 'all') {
                $whereClause .= " AND ftr.status = ?";
                $params[] = $status;
            }

            $stmt = $this->pdo->prepare("
                SELECT 
                    ftr.id,
                    ftr.student_id,
                    ftr.attendance_record_id,
                    ftr.request_date,
                    ftr.block_type,
                    ftr.letter_file_path,
                    ftr.status,
                    ftr.instructor_response,
                    ftr.created_at,
                    ftr.reviewed_at,
                    u.full_name as student_name,
                    u.school_id,
                    s.section_name,
                    ar.date as attendance_date,
                    ar.time_in,
                    ar.time_out,
                    ar.hours_earned
                FROM forgot_timeout_requests ftr
                JOIN users u ON ftr.student_id = u.id
                LEFT JOIN sections s ON u.section_id = s.id
                LEFT JOIN attendance_records ar ON ftr.attendance_record_id = ar.id
                {$whereClause}
                ORDER BY ftr.created_at DESC
                LIMIT ? OFFSET ?
            ");

            $params[] = $limit;
            $params[] = $offset;

            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("ForgotTimeoutReviewService::getRequestsForInstructor error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get request statistics for instructor
     */
    public function getRequestStats(int $instructorId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    COUNT(*) as total_requests,
                    SUM(CASE WHEN ftr.status = 'pending' THEN 1 ELSE 0 END) as pending_requests,
                    SUM(CASE WHEN ftr.status = 'approved' THEN 1 ELSE 0 END) as approved_requests,
                    SUM(CASE WHEN ftr.status = 'rejected' THEN 1 ELSE 0 END) as rejected_requests
                FROM forgot_timeout_requests ftr
                JOIN users u ON ftr.student_id = u.id
                WHERE u.section_id IN (
                    SELECT section_id FROM users WHERE id = ?
                )
            ");

            $stmt->execute([$instructorId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("ForgotTimeoutReviewService::getRequestStats error: " . $e->getMessage());
            return [
                'total_requests' => 0,
                'pending_requests' => 0,
                'approved_requests' => 0,
                'rejected_requests' => 0
            ];
        }
    }

    /**
     * Get single request details
     */
    public function getRequestDetails(int $requestId, int $instructorId): ?array
    {
        try {
            // First check if instructor has a section
            $stmt = $this->pdo->prepare("SELECT section_id FROM users WHERE id = ?");
            $stmt->execute([$instructorId]);
            $instructorSection = $stmt->fetchColumn();
            
            if ($instructorSection) {
                // Instructor has a section - use section-based validation
                $stmt = $this->pdo->prepare("
                    SELECT 
                        ftr.*,
                        u.full_name as student_name,
                        u.school_id,
                        u.email as student_email,
                        s.section_name,
                        ar.date as attendance_date,
                        ar.time_in,
                        ar.time_out,
                        ar.hours_earned,
                        ar.block_type as attendance_block_type,
                        ar.location_lat_in,
                        ar.location_long_in,
                        ar.location_lat_out,
                        ar.location_long_out
                    FROM forgot_timeout_requests ftr
                    JOIN users u ON ftr.student_id = u.id
                    LEFT JOIN sections s ON u.section_id = s.id
                    LEFT JOIN attendance_records ar ON ftr.attendance_record_id = ar.id
                    WHERE ftr.id = ? 
                    AND u.section_id = ?
                ");
                $stmt->execute([$requestId, $instructorSection]);
            } else {
                // Instructor has no section - allow access to any request (admin access)
                $stmt = $this->pdo->prepare("
                    SELECT 
                        ftr.*,
                        u.full_name as student_name,
                        u.school_id,
                        u.email as student_email,
                        s.section_name,
                        ar.date as attendance_date,
                        ar.time_in,
                        ar.time_out,
                        ar.hours_earned,
                        ar.block_type as attendance_block_type,
                        ar.location_lat_in,
                        ar.location_long_in,
                        ar.location_lat_out,
                        ar.location_long_out
                    FROM forgot_timeout_requests ftr
                    JOIN users u ON ftr.student_id = u.id
                    LEFT JOIN sections s ON u.section_id = s.id
                    LEFT JOIN attendance_records ar ON ftr.attendance_record_id = ar.id
                    WHERE ftr.id = ?
                ");
                $stmt->execute([$requestId]);
            }

            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;

        } catch (Exception $e) {
            error_log("ForgotTimeoutReviewService::getRequestDetails error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Update request status and add instructor response
     */
    public function updateRequestStatus(int $requestId, int $instructorId, string $status, string $response = ''): bool
    {
        try {
            // First check if instructor has a section
            $stmt = $this->pdo->prepare("SELECT section_id FROM users WHERE id = ?");
            $stmt->execute([$instructorId]);
            $instructorSection = $stmt->fetchColumn();
            
            if ($instructorSection) {
                // Instructor has a section - use section-based validation
                $stmt = $this->pdo->prepare("
                    UPDATE forgot_timeout_requests 
                    SET status = ?, 
                        instructor_response = ?, 
                        reviewed_at = NOW()
                    WHERE id = ? 
                    AND student_id IN (
                        SELECT u.id FROM users u 
                        WHERE u.section_id = ?
                    )
                ");
                return $stmt->execute([$status, $response, $requestId, $instructorSection]);
            } else {
                // Instructor has no section - allow update for any request (admin access)
                $stmt = $this->pdo->prepare("
                    UPDATE forgot_timeout_requests 
                    SET status = ?, 
                        instructor_response = ?, 
                        reviewed_at = NOW()
                    WHERE id = ?
                ");
                return $stmt->execute([$status, $response, $requestId]);
            }

        } catch (Exception $e) {
            error_log("ForgotTimeoutReviewService::updateRequestStatus error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get letter file path for download
     */
    public function getLetterFilePath(int $requestId, int $instructorId): ?string
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT ftr.letter_file_path
                FROM forgot_timeout_requests ftr
                JOIN users u ON ftr.student_id = u.id
                WHERE ftr.id = ? 
                AND u.section_id IN (
                    SELECT section_id FROM users WHERE id = ?
                )
            ");

            $stmt->execute([$requestId, $instructorId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ? $result['letter_file_path'] : null;

        } catch (Exception $e) {
            error_log("ForgotTimeoutReviewService::getLetterFilePath error: " . $e->getMessage());
            return null;
        }
    }
}
