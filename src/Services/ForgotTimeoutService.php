<?php

namespace App\Services;

use App\Utils\Database;
use PDO;
use Exception;

class ForgotTimeoutService
{
    private $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Get attendance records for a student that are missing time-out
     */
    public function getAttendanceRecordsWithoutTimeout(int $studentId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    ar.id,
                    ar.date,
                    ar.block_type,
                    ar.time_in,
                    ar.time_out,
                    ar.hours_earned
                FROM attendance_records ar
                WHERE ar.student_id = ? 
                AND ar.time_in IS NOT NULL 
                AND ar.time_out IS NULL
                ORDER BY ar.date DESC, ar.block_type
            ");
            
            $stmt->execute([$studentId]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Filter records based on "dead time" logic
            $forgottenRecords = [];
            $currentTime = new \DateTime();
            $currentDate = $currentTime->format('Y-m-d');
            $currentHour = (int)$currentTime->format('H');
            
            foreach ($records as $record) {
                $recordDate = $record['date'];
                $blockType = $record['block_type'];
                $timeIn = new \DateTime($record['time_in']);
                $timeInHour = (int)$timeIn->format('H');
                
                $shouldShow = false;
                
                // For today's records, check if we're past the "dead time"
                if ($recordDate === $currentDate) {
                    switch ($blockType) {
                        case 'morning':
                            // Morning block: 8am-12pm, dead time is after 12pm
                            if ($currentHour >= 12) {
                                $shouldShow = true;
                            }
                            break;
                            
                        case 'afternoon':
                            // Afternoon block: 12pm-6pm, dead time is after 6pm
                            if ($currentHour >= 18) {
                                $shouldShow = true;
                            }
                            break;
                            
                        case 'overtime':
                            // Overtime block: 6pm-8pm, dead time is after 8pm
                            if ($currentHour >= 20) {
                                $shouldShow = true;
                            }
                            break;
                    }
                } else {
                    // For past dates, always show (they're definitely forgotten)
                    $shouldShow = true;
                }
                
                if ($shouldShow) {
                    $forgottenRecords[] = $record;
                }
            }
            
            return $forgottenRecords;
            
        } catch (Exception $e) {
            error_log("ForgotTimeoutService::getAttendanceRecordsWithoutTimeout error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Check if a request already exists for an attendance record
     */
    public function requestExists(int $attendanceRecordId): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) 
                FROM forgot_timeout_requests 
                WHERE attendance_record_id = ? 
                AND status IN ('pending', 'approved')
            ");
            
            $stmt->execute([$attendanceRecordId]);
            return (int) $stmt->fetchColumn() > 0;
            
        } catch (Exception $e) {
            error_log("ForgotTimeoutService::requestExists error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a new forgot timeout request
     */
    public function createRequest(array $data): bool
    {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO forgot_timeout_requests 
                (student_id, attendance_record_id, request_date, block_type, letter_file_path, status)
                VALUES (?, ?, ?, ?, ?, 'pending')
            ");
            
            return $stmt->execute([
                $data['student_id'],
                $data['attendance_record_id'],
                $data['request_date'],
                $data['block_type'],
                $data['letter_file_path']
            ]);
            
        } catch (Exception $e) {
            error_log("ForgotTimeoutService::createRequest error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all requests for a student
     */
    public function getStudentRequests(int $studentId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    ftr.id,
                    ftr.attendance_record_id,
                    ftr.request_date,
                    ftr.block_type,
                    ftr.letter_file_path,
                    ftr.status,
                    ftr.instructor_response,
                    ftr.created_at,
                    ftr.reviewed_at,
                    ar.date as attendance_date,
                    ar.time_in,
                    ar.time_out
                FROM forgot_timeout_requests ftr
                JOIN attendance_records ar ON ftr.attendance_record_id = ar.id
                WHERE ftr.student_id = ?
                ORDER BY ftr.created_at DESC
            ");
            
            $stmt->execute([$studentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch (Exception $e) {
            error_log("ForgotTimeoutService::getStudentRequests error: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Get attendance record details for validation
     */
    public function getAttendanceRecord(int $attendanceRecordId, int $studentId): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    id,
                    student_id,
                    date,
                    block_type,
                    time_in,
                    time_out
                FROM attendance_records 
                WHERE id = ? AND student_id = ?
            ");
            
            $stmt->execute([$attendanceRecordId, $studentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $result ?: null;
            
        } catch (Exception $e) {
            error_log("ForgotTimeoutService::getAttendanceRecord error: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Validate file upload
     */
    public function validateFileUpload(array $file): array
    {
        $errors = [];
        
        // Check if file was uploaded
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            $errors[] = "No file was uploaded.";
            return $errors;
        }
        
        // Check file size (5MB max)
        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = "File size must be less than 5MB.";
        }
        
        // Check file type
        $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword'];
        $fileType = mime_content_type($file['tmp_name']);
        
        if (!in_array($fileType, $allowedTypes)) {
            $errors[] = "Only PDF, DOCX, and DOC files are allowed.";
        }
        
        // Check file extension
        $allowedExtensions = ['pdf', 'docx', 'doc'];
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedExtensions)) {
            $errors[] = "File must have .pdf, .docx, or .doc extension.";
        }
        
        return $errors;
    }

    /**
     * Generate unique filename for uploaded file
     */
    public function generateUniqueFilename(string $originalName, int $studentId): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $timestamp = time();
        return "student_{$studentId}_forgot_timeout_{$timestamp}.{$extension}";
    }

    /**
     * Get request statistics for student
     */
    public function getRequestStats(int $studentId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM forgot_timeout_requests 
                WHERE student_id = ?
                GROUP BY status
            ");
            
            $stmt->execute([$studentId]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $stats = [
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0,
                'total' => 0
            ];
            
            foreach ($results as $row) {
                $stats[$row['status']] = (int) $row['count'];
                $stats['total'] += (int) $row['count'];
            }
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("ForgotTimeoutService::getRequestStats error: " . $e->getMessage());
            return [
                'pending' => 0,
                'approved' => 0,
                'rejected' => 0,
                'total' => 0
            ];
        }
    }
}
