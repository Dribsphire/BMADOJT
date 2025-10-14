<?php

namespace App\Services;

use PDO;
use DateTime;
use Exception;

/**
 * AttendanceService - Handles attendance tracking and management
 * 
 * Provides functionality for student attendance tracking, block management,
 * and compliance verification for the OJT attendance system.
 */
class AttendanceService
{
    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
        // Set timezone to Philippines (UTC+08:00)
        date_default_timezone_set('Asia/Manila');
    }

    /**
     * Get attendance blocks configuration
     * 
     * @return array Array of attendance blocks with time ranges
     */
    public function getAttendanceBlocks(): array
    {
        return [
            'morning' => [
                'name' => 'Morning Block',
                'start_time' => '06:00:00',
                'end_time' => '12:00:00',
                'color' => 'warning',
                'icon' => 'sun'
            ],
            'afternoon' => [
                'name' => 'Afternoon Block',
                'start_time' => '12:00:00',
                'end_time' => '18:00:00',
                'color' => 'warning',
                'icon' => 'sun'
            ],
            'evening' => [
                'name' => 'Evening Block',
                'start_time' => '18:00:00',
                'end_time' => '00:00:00',
                'color' => 'info',
                'icon' => 'moon'
            ]
        ];
    }

    /**
     * Get student's attendance status for a specific date
     * 
     * @param int $studentId Student ID
     * @param string $date Date in Y-m-d format
     * @return array Attendance status for each block
     */
    public function getStudentAttendanceStatus(int $studentId, string $date): array
    {
        $stmt = $this->database->prepare("
            SELECT 
                block_type,
                time_in,
                time_out,
                hours_earned,
                CASE 
                    WHEN time_in IS NOT NULL AND time_out IS NOT NULL THEN 'completed'
                    WHEN time_in IS NOT NULL AND time_out IS NULL THEN 'time_in'
                    ELSE 'pending'
                END as status
            FROM attendance_records 
            WHERE student_id = ? AND date = ?
            ORDER BY block_type, created_at
        ");
        
        $stmt->execute([$studentId, $date]);
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Initialize blocks
        $blocks = $this->getAttendanceBlocks();
        $status = [];
        
        foreach ($blocks as $blockKey => $blockInfo) {
            $status[$blockKey] = [
                'name' => $blockInfo['name'],
                'start_time' => $blockInfo['start_time'],
                'end_time' => $blockInfo['end_time'],
                'color' => $blockInfo['color'],
                'icon' => $blockInfo['icon'],
                'status' => 'not_started',
                'time_in' => null,
                'time_out' => null,
                'total_hours' => 0,
                'can_time_in' => false,
                'can_time_out' => false
            ];
        }
        
        // Update with actual attendance data
        foreach ($attendance as $record) {
            $blockKey = $record['block_type'];
            if (isset($status[$blockKey])) {
                $status[$blockKey]['status'] = $record['status'];
                $status[$blockKey]['time_in'] = $record['time_in'];
                $status[$blockKey]['time_out'] = $record['time_out'];
                $status[$blockKey]['total_hours'] = $record['hours_earned'];
            }
        }
        
        // Determine current block and permissions
        $currentTime = new DateTime();
        $currentHour = (int)$currentTime->format('H');
        
        foreach ($status as $blockKey => &$block) {
            $startHour = (int)substr($block['start_time'], 0, 2);
            $endHour = (int)substr($block['end_time'], 0, 2);
            
            // Handle evening block (crosses midnight)
            if ($blockKey === 'evening') {
                $endHour = 24;
            }
            
            $isCurrentBlock = $currentHour >= $startHour && $currentHour < $endHour;
            
            // Determine permissions
            if ($isCurrentBlock) {
                if ($block['status'] === 'not_started') {
                    $block['can_time_in'] = true;
                } elseif ($block['status'] === 'time_in') {
                    $block['can_time_out'] = true;
                }
            }
        }
        
        return $status;
    }

    /**
     * Check if student has completed all required documents
     * 
     * @param int $studentId Student ID
     * @return array Compliance status with details
     */
    public function checkDocumentCompliance(int $studentId): array
    {
        // Get student's section
        $stmt = $this->database->prepare("SELECT section_id FROM users WHERE id = ?");
        $stmt->execute([$studentId]);
        $user = $stmt->fetch();
        $sectionId = $user['section_id'] ?? null;
        
        // Get all required document types
        $stmt = $this->database->prepare("
            SELECT DISTINCT document_type 
            FROM documents 
            WHERE is_required = 1 AND uploaded_for_section IS NULL
        ");
        $stmt->execute();
        $requiredTypes = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $totalRequired = count($requiredTypes);
        $approvedCount = 0;
        $pendingDocuments = [];
        
        // Check if student has approved documents for each required type
        foreach ($requiredTypes as $docType) {
            $stmt = $this->database->prepare("
                SELECT 
                    d.id,
                    d.document_name,
                    d.document_type,
                    sd.status,
                    sd.reviewed_at as approved_at
                FROM documents d
                LEFT JOIN student_documents sd ON d.id = sd.document_id AND sd.student_id = ?
                WHERE d.document_type = ? 
                AND d.is_required = 1 
                AND (d.uploaded_for_section IS NULL OR d.uploaded_for_section = ?)
                ORDER BY d.uploaded_for_section DESC, d.id DESC
                LIMIT 1
            ");
            $stmt->execute([$studentId, $docType, $sectionId]);
            $doc = $stmt->fetch();
            
            if ($doc && $doc['status'] === 'approved') {
                $approvedCount++;
            } else {
                $pendingDocuments[] = $doc ?: [
                    'document_name' => ucfirst(str_replace('_', ' ', $docType)) . ' Template',
                    'document_type' => $docType,
                    'status' => 'Not submitted'
                ];
            }
        }
        
        $isCompliant = $approvedCount === $totalRequired;
        $compliancePercentage = $totalRequired > 0 ? round(($approvedCount / $totalRequired) * 100, 1) : 0;
        
        return [
            'compliant' => $isCompliant,
            'required_count' => $totalRequired,
            'approved_count' => $approvedCount,
            'pending_count' => count($pendingDocuments),
            'compliance_percentage' => $compliancePercentage,
            'pending_documents' => $pendingDocuments
        ];
    }

    /**
     * Record student time-in for a specific block
     * 
     * @param int $studentId Student ID
     * @param string $blockType Block type (morning, afternoon, evening)
     * @param float $latitude GPS latitude
     * @param float $longitude GPS longitude
     * @param string|null $photoData Base64 photo data (optional)
     * @return array Result with success status and message
     */
    public function recordTimeIn(int $studentId, string $blockType, float $latitude, float $longitude, ?string $photoData = null): array
    {
        try {
            // Log the attempt for debugging
            error_log("AttendanceService::recordTimeIn - Student: $studentId, Block: $blockType, Lat: $latitude, Lng: $longitude");
            // Check if already timed in for this block today
            $today = date('Y-m-d');
            $stmt = $this->database->prepare("
                SELECT id, time_in, time_out,
                       CASE 
                           WHEN time_in IS NOT NULL AND time_out IS NOT NULL THEN 'completed'
                           WHEN time_in IS NOT NULL AND time_out IS NULL THEN 'time_in'
                           ELSE 'pending'
                       END as status
                FROM attendance_records 
                WHERE student_id = ? AND block_type = ? AND date = ?
            ");
            $stmt->execute([$studentId, $blockType, $today]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existing) {
                if ($existing['status'] === 'time_in') {
                    return [
                        'success' => false,
                        'message' => 'You have already timed in for this block today.'
                    ];
                } elseif ($existing['status'] === 'completed') {
                    return [
                        'success' => false,
                        'message' => 'You have already completed this block today.'
                    ];
                }
            }
            
            // Process photo if provided
            $photoPath = null;
            if ($photoData) {
                $photoPath = $this->savePhoto($studentId, $blockType, $photoData);
                if (!$photoPath) {
                    return [
                        'success' => false,
                        'message' => 'Failed to save photo. Please try again.'
                    ];
                }
            }
            
            // Record time-in with additional duplicate check
            $stmt = $this->database->prepare("
                INSERT INTO attendance_records (student_id, block_type, date, time_in, location_lat_in, location_long_in, photo_path)
                VALUES (?, ?, CURDATE(), NOW(), ?, ?, ?)
            ");
            
            $result = $stmt->execute([$studentId, $blockType, $latitude, $longitude, $photoPath]);
            
            // Double-check for duplicates after insertion
            if ($result) {
                $stmt = $this->database->prepare("
                    SELECT COUNT(*) as count 
                    FROM attendance_records 
                    WHERE student_id = ? AND block_type = ? AND date = CURDATE()
                ");
                $stmt->execute([$studentId, $blockType]);
                $count = $stmt->fetchColumn();
                
                if ($count > 1) {
                    // Remove the duplicate record
                    $stmt = $this->database->prepare("
                        DELETE FROM attendance_records 
                        WHERE student_id = ? AND block_type = ? AND date = CURDATE() 
                        ORDER BY created_at DESC 
                        LIMIT ?
                    ");
                    $stmt->execute([$studentId, $blockType, $count - 1]);
                }
            }
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Time-in recorded successfully for ' . ucfirst($blockType) . ' block.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to record time-in. Please try again.'
                ];
            }
            
        } catch (Exception $e) {
            error_log("AttendanceService::recordTimeIn ERROR - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return [
                'success' => false,
                'message' => 'Error recording time-in: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Record student time-out for a specific block
     * 
     * @param int $studentId Student ID
     * @param string $blockType Block type (morning, afternoon, evening)
     * @param float $latitude GPS latitude
     * @param float $longitude GPS longitude
     * @return array Result with success status and message
     */
    public function recordTimeOut(int $studentId, string $blockType, float $latitude, float $longitude): array
    {
        try {
            // Find the time-in record for this block today
            $today = date('Y-m-d');
            $stmt = $this->database->prepare("
                SELECT id, time_in FROM attendance_records 
                WHERE student_id = ? AND block_type = ? AND date = ? AND time_in IS NOT NULL AND time_out IS NULL
            ");
            $stmt->execute([$studentId, $blockType, $today]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$record) {
                return [
                    'success' => false,
                    'message' => 'No time-in record found for this block. Please time in first.'
                ];
            }
            
            // Calculate total hours
            $timeIn = new DateTime($record['time_in']);
            $timeOut = new DateTime();
            $totalHours = $timeOut->diff($timeIn)->h + ($timeOut->diff($timeIn)->i / 60);
            
            // Update the record
            $stmt = $this->database->prepare("
                UPDATE attendance_records 
                SET time_out = NOW(), hours_earned = ?
                WHERE id = ?
            ");
            
            $result = $stmt->execute([$totalHours, $record['id']]);
            
            if ($result) {
                // Update student profile total_hours_accumulated
                $this->updateStudentTotalHours($studentId);
                
                return [
                    'success' => true,
                    'message' => 'Time-out recorded successfully. Total hours: ' . number_format($totalHours, 2) . ' hours.',
                    'total_hours' => $totalHours
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to record time-out. Please try again.'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error recording time-out: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update student's total hours accumulated in their profile
     * 
     * @param int $studentId Student ID
     * @return void
     */
    private function updateStudentTotalHours(int $studentId): void
    {
        try {
            // Calculate total hours from all completed attendance records
            $stmt = $this->database->prepare("
                SELECT COALESCE(SUM(hours_earned), 0) as total_hours
                FROM attendance_records 
                WHERE student_id = ? AND time_in IS NOT NULL AND time_out IS NOT NULL
            ");
            $stmt->execute([$studentId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $totalHours = $result['total_hours'] ?? 0;
            
            // Update student profile
            $stmt = $this->database->prepare("
                UPDATE student_profiles 
                SET total_hours_accumulated = ?, updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$totalHours, $studentId]);
            
        } catch (Exception $e) {
            // Log error but don't fail the time-out process
            error_log("Error updating student total hours: " . $e->getMessage());
        }
    }

    /**
     * Get student's daily attendance summary
     * 
     * @param int $studentId Student ID
     * @param string $date Date in Y-m-d format
     * @return array Daily summary with totals
     */
    public function getDailySummary(int $studentId, string $date): array
    {
        $stmt = $this->database->prepare("
            SELECT 
                COUNT(*) as total_blocks,
                SUM(hours_earned) as total_hours,
                COUNT(CASE WHEN time_in IS NOT NULL AND time_out IS NOT NULL THEN 1 END) as completed_blocks
            FROM attendance_records 
            WHERE student_id = ? AND date = ?
        ");
        
        $stmt->execute([$studentId, $date]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'date' => $date,
            'total_blocks' => (int)$summary['total_blocks'],
            'completed_blocks' => (int)$summary['completed_blocks'],
            'total_hours' => round((float)$summary['total_hours'], 2),
            'completion_percentage' => $summary['total_blocks'] > 0 
                ? round(($summary['completed_blocks'] / $summary['total_blocks']) * 100, 1) 
                : 0
        ];
    }

    /**
     * Get current time and determine active block
     * 
     * @return array Current time info and active block
     */
    public function getCurrentTimeInfo(): array
    {
        $now = new DateTime();
        $currentHour = (int)$now->format('H');
        $currentTime = $now->format('H:i:s');
        $currentDate = $now->format('Y-m-d');
        
        $activeBlock = null;
        $blocks = $this->getAttendanceBlocks();
        
        foreach ($blocks as $blockKey => $blockInfo) {
            $startHour = (int)substr($blockInfo['start_time'], 0, 2);
            $endHour = (int)substr($blockInfo['end_time'], 0, 2);
            
            // Handle evening block (crosses midnight)
            if ($blockKey === 'evening') {
                $endHour = 24;
            }
            
            if ($currentHour >= $startHour && $currentHour < $endHour) {
                $activeBlock = $blockKey;
                break;
            }
        }
        
        return [
            'current_time' => $currentTime,
            'current_date' => $currentDate,
            'current_hour' => $currentHour,
            'active_block' => $activeBlock
        ];
    }

    /**
     * Save photo from base64 data
     * 
     * @param int $studentId Student ID
     * @param string $blockType Block type
     * @param string $photoData Base64 photo data
     * @return string|null Photo file path or null on failure
     */
    private function savePhoto(int $studentId, string $blockType, string $photoData): ?string
    {
        try {
            // Validate base64 data
            if (!preg_match('/^data:image\/(jpeg|jpg|png);base64,/', $photoData)) {
                return null;
            }

            // Extract image data
            $imageData = substr($photoData, strpos($photoData, ',') + 1);
            $imageData = base64_decode($imageData);
            
            if ($imageData === false) {
                return null;
            }

            // Generate unique filename
            $timestamp = time();
            $filename = "student_{$studentId}_{$blockType}_{$timestamp}.jpg";
            $uploadDir = __DIR__ . '/../../uploads/attendance_photos/';
            
            // Ensure upload directory exists
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filePath = $uploadDir . $filename;
            
            // Save file
            if (file_put_contents($filePath, $imageData) === false) {
                return null;
            }

            // Return relative path for database storage
            return "uploads/attendance_photos/{$filename}";
            
        } catch (Exception $e) {
            error_log("Photo save error: " . $e->getMessage());
            return null;
        }
    }
}
