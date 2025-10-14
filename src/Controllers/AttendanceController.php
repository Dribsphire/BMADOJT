<?php

namespace App\Controllers;

use App\Services\AttendanceService;
use App\Services\GeolocationService;
use App\Utils\Database;
use Exception;

/**
 * AttendanceController - Handles attendance-related requests
 * 
 * Manages student attendance tracking, time-in/out operations,
 * and compliance verification for the OJT system.
 */
class AttendanceController
{
    private AttendanceService $attendanceService;
    private GeolocationService $geolocationService;

    public function __construct()
    {
        $database = Database::getInstance();
        $this->attendanceService = new AttendanceService($database);
        $this->geolocationService = new GeolocationService($database);
        // Set timezone to Philippines (UTC+08:00)
        date_default_timezone_set('Asia/Manila');
    }

    /**
     * Display attendance page for student
     * 
     * @return array Data for attendance page rendering
     */
    public function showAttendancePage(): array
    {
        $studentId = $_SESSION['user_id'] ?? null;
        
        if (!$studentId) {
            return [
                'error' => 'Authentication required',
                'redirect' => '/login.php'
            ];
        }

        try {
            // Check document compliance
            $compliance = $this->attendanceService->checkDocumentCompliance($studentId);
            
            if (!$compliance['compliant']) {
                return [
                    'error' => 'Document compliance required',
                    'compliance' => $compliance,
                    'redirect' => '/student/documents.php'
                ];
            }

            // Get current date and attendance status
            $today = date('Y-m-d');
            $attendanceStatus = $this->attendanceService->getStudentAttendanceStatus($studentId, $today);
            $dailySummary = $this->attendanceService->getDailySummary($studentId, $today);
            $timeInfo = $this->attendanceService->getCurrentTimeInfo();

            return [
                'success' => true,
                'attendance_status' => $attendanceStatus,
                'daily_summary' => $dailySummary,
                'time_info' => $timeInfo,
                'compliance' => $compliance
            ];

        } catch (Exception $e) {
            return [
                'error' => 'Failed to load attendance data: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle time-in request
     * 
     * @return array JSON response for AJAX request
     */
    public function handleTimeIn(): array
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Invalid request method'];
        }

        $studentId = $_SESSION['user_id'] ?? null;
        $blockType = $_POST['block_type'] ?? null;
        $latitude = (float)($_POST['latitude'] ?? 0);
        $longitude = (float)($_POST['longitude'] ?? 0);
        $photoData = $_POST['photo_data'] ?? null;

        if (!$studentId || !$blockType) {
            return ['success' => false, 'message' => 'Missing required parameters'];
        }

        try {
            // Verify location if coordinates provided
            if ($latitude && $longitude) {
                $locationResult = $this->geolocationService->verifyAttendanceLocation($studentId, $latitude, $longitude);
                
                if (!$locationResult['valid']) {
                    return [
                        'success' => false,
                        'message' => 'Location verification failed: ' . $locationResult['message']
                    ];
                }
            }

            // Record time-in
            $result = $this->attendanceService->recordTimeIn($studentId, $blockType, $latitude, $longitude, $photoData);
            
            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error processing time-in: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Handle time-out request
     * 
     * @return array JSON response for AJAX request
     */
    public function handleTimeOut(): array
    {
        if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return ['success' => false, 'message' => 'Invalid request method'];
        }

        $studentId = $_SESSION['user_id'] ?? null;
        $blockType = $_POST['block_type'] ?? null;
        $latitude = (float)($_POST['latitude'] ?? 0);
        $longitude = (float)($_POST['longitude'] ?? 0);

        if (!$studentId || !$blockType) {
            return ['success' => false, 'message' => 'Missing required parameters'];
        }

        try {
            // Verify location if coordinates provided
            if ($latitude && $longitude) {
                $locationResult = $this->geolocationService->verifyAttendanceLocation($studentId, $latitude, $longitude);
                
                if (!$locationResult['valid']) {
                    return [
                        'success' => false,
                        'message' => 'Location verification failed: ' . $locationResult['message']
                    ];
                }
            }

            // Record time-out
            $result = $this->attendanceService->recordTimeOut($studentId, $blockType, $latitude, $longitude);
            
            return $result;

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error processing time-out: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get attendance status for AJAX requests
     * 
     * @return array JSON response with current status
     */
    public function getAttendanceStatus(): array
    {
        $studentId = $_SESSION['user_id'] ?? null;
        
        if (!$studentId) {
            return ['success' => false, 'message' => 'Authentication required'];
        }

        try {
            $today = date('Y-m-d');
            $attendanceStatus = $this->attendanceService->getStudentAttendanceStatus($studentId, $today);
            $dailySummary = $this->attendanceService->getDailySummary($studentId, $today);
            $timeInfo = $this->attendanceService->getCurrentTimeInfo();

            return [
                'success' => true,
                'attendance_status' => $attendanceStatus,
                'daily_summary' => $dailySummary,
                'time_info' => $timeInfo
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error fetching attendance status: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check document compliance status
     * 
     * @return array JSON response with compliance status
     */
    public function checkCompliance(): array
    {
        $studentId = $_SESSION['user_id'] ?? null;
        
        if (!$studentId) {
            return ['success' => false, 'message' => 'Authentication required'];
        }

        try {
            $compliance = $this->attendanceService->checkDocumentCompliance($studentId);
            
            return [
                'success' => true,
                'compliance' => $compliance
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error checking compliance: ' . $e->getMessage()
            ];
        }
    }
}
