<?php

namespace App\Controllers;

use App\Services\ForgotTimeoutService;
use App\Middleware\AuthMiddleware;
use App\Utils\ActivityLogger;

class ForgotTimeoutController
{
    private $forgotTimeoutService;
    private $activityLogger;

    public function __construct()
    {
        $this->forgotTimeoutService = new ForgotTimeoutService();
        $this->activityLogger = new ActivityLogger();
    }

    /**
     * Get attendance records without timeout for current student
     */
    public function getAttendanceRecordsWithoutTimeout(): void
    {
        try {
            $authMiddleware = new AuthMiddleware();
            $user = $authMiddleware->getCurrentUser();
            
            if (!$user || $user->role !== 'student') {
                $this->sendJsonResponse(['error' => 'Unauthorized'], 401);
                return;
            }

            $records = $this->forgotTimeoutService->getAttendanceRecordsWithoutTimeout($user->id);
            
            $this->sendJsonResponse([
                'success' => true,
                'records' => $records
            ]);
            
        } catch (Exception $e) {
            error_log("ForgotTimeoutController::getAttendanceRecordsWithoutTimeout error: " . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Failed to fetch attendance records'], 500);
        }
    }

    /**
     * Submit a new forgot timeout request
     */
    public function submitRequest(): void
    {
        try {
            $authMiddleware = new AuthMiddleware();
            $user = $authMiddleware->getCurrentUser();
            
            if (!$user || $user->role !== 'student') {
                $this->sendJsonResponse(['error' => 'Unauthorized'], 401);
                return;
            }

            // Validate required fields
            $requiredFields = ['attendance_record_id', 'reason'];
            foreach ($requiredFields as $field) {
                if (empty($_POST[$field])) {
                    $this->sendJsonResponse(['error' => "Field {$field} is required"], 400);
                    return;
                }
            }

            $attendanceRecordId = (int) $_POST['attendance_record_id'];
            $reason = trim($_POST['reason']);

            // Validate attendance record belongs to student
            $attendanceRecord = $this->forgotTimeoutService->getAttendanceRecord($attendanceRecordId, $user->id);
            if (!$attendanceRecord) {
                $this->sendJsonResponse(['error' => 'Attendance record not found or does not belong to you'], 400);
                return;
            }

            // Check if time-out is missing
            if ($attendanceRecord['time_out'] !== null) {
                $this->sendJsonResponse(['error' => 'This attendance record already has a time-out'], 400);
                return;
            }

            // Check if request already exists
            if ($this->forgotTimeoutService->requestExists($attendanceRecordId)) {
                $this->sendJsonResponse(['error' => 'A request for this attendance record already exists'], 400);
                return;
            }

            // Handle file upload
            if (!isset($_FILES['letter_file']) || $_FILES['letter_file']['error'] !== UPLOAD_ERR_OK) {
                $this->sendJsonResponse(['error' => 'Letter file is required'], 400);
                return;
            }

            $file = $_FILES['letter_file'];
            $validationErrors = $this->forgotTimeoutService->validateFileUpload($file);
            
            if (!empty($validationErrors)) {
                $this->sendJsonResponse(['error' => implode(', ', $validationErrors)], 400);
                return;
            }

            // Generate unique filename and upload
            $uniqueFilename = $this->forgotTimeoutService->generateUniqueFilename($file['name'], $user->id);
            $uploadDir = __DIR__ . '/../../uploads/letters/';
            
            // Create directory if it doesn't exist
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            $uploadPath = $uploadDir . $uniqueFilename;
            
            if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $this->sendJsonResponse(['error' => 'Failed to upload file'], 500);
                return;
            }

            // Create request record
            $requestData = [
                'student_id' => $user->id,
                'attendance_record_id' => $attendanceRecordId,
                'request_date' => date('Y-m-d'),
                'block_type' => $attendanceRecord['block_type'],
                'letter_file_path' => 'uploads/letters/' . $uniqueFilename
            ];

            if ($this->forgotTimeoutService->createRequest($requestData)) {
                // Log activity
                $this->activityLogger->logActivity(
                    'forgot_timeout_request_submitted',
                    "Student submitted forgot timeout request for attendance record {$attendanceRecordId}"
                );

                $this->sendJsonResponse([
                    'success' => true,
                    'message' => 'Request submitted successfully'
                ]);
            } else {
                // Clean up uploaded file if database insert failed
                if (file_exists($uploadPath)) {
                    unlink($uploadPath);
                }
                $this->sendJsonResponse(['error' => 'Failed to create request'], 500);
            }
            
        } catch (Exception $e) {
            error_log("ForgotTimeoutController::submitRequest error: " . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Failed to submit request'], 500);
        }
    }

    /**
     * Get student's forgot timeout requests
     */
    public function getStudentRequests(): void
    {
        try {
            $authMiddleware = new AuthMiddleware();
            $user = $authMiddleware->getCurrentUser();
            
            if (!$user || $user->role !== 'student') {
                $this->sendJsonResponse(['error' => 'Unauthorized'], 401);
                return;
            }

            $requests = $this->forgotTimeoutService->getStudentRequests($user->id);
            $stats = $this->forgotTimeoutService->getRequestStats($user->id);
            
            $this->sendJsonResponse([
                'success' => true,
                'requests' => $requests,
                'stats' => $stats
            ]);
            
        } catch (Exception $e) {
            error_log("ForgotTimeoutController::getStudentRequests error: " . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Failed to fetch requests'], 500);
        }
    }

    /**
     * Get request details for viewing
     */
    public function getRequestDetails(int $requestId): void
    {
        try {
            $authMiddleware = new AuthMiddleware();
            $user = $authMiddleware->getCurrentUser();
            
            if (!$user || $user->role !== 'student') {
                $this->sendJsonResponse(['error' => 'Unauthorized'], 401);
                return;
            }

            $requests = $this->forgotTimeoutService->getStudentRequests($user->id);
            $request = null;
            
            foreach ($requests as $req) {
                if ($req['id'] == $requestId) {
                    $request = $req;
                    break;
                }
            }
            
            if (!$request) {
                $this->sendJsonResponse(['error' => 'Request not found'], 404);
                return;
            }
            
            $this->sendJsonResponse([
                'success' => true,
                'request' => $request
            ]);
            
        } catch (Exception $e) {
            error_log("ForgotTimeoutController::getRequestDetails error: " . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Failed to fetch request details'], 500);
        }
    }

    /**
     * Download letter file
     */
    public function downloadLetter(int $requestId): void
    {
        try {
            $authMiddleware = new AuthMiddleware();
            $user = $authMiddleware->getCurrentUser();
            
            if (!$user || $user->role !== 'student') {
                $this->sendJsonResponse(['error' => 'Unauthorized'], 401);
                return;
            }

            $requests = $this->forgotTimeoutService->getStudentRequests($user->id);
            $request = null;
            
            foreach ($requests as $req) {
                if ($req['id'] == $requestId) {
                    $request = $req;
                    break;
                }
            }
            
            if (!$request) {
                $this->sendJsonResponse(['error' => 'Request not found'], 404);
                return;
            }

            $filePath = __DIR__ . '/../../' . $request['letter_file_path'];
            
            if (!file_exists($filePath)) {
                $this->sendJsonResponse(['error' => 'File not found'], 404);
                return;
            }

            // Set appropriate headers for file download
            $filename = basename($filePath);
            $mimeType = mime_content_type($filePath);
            
            header('Content-Type: ' . $mimeType);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filePath));
            
            readfile($filePath);
            exit;
            
        } catch (Exception $e) {
            error_log("ForgotTimeoutController::downloadLetter error: " . $e->getMessage());
            $this->sendJsonResponse(['error' => 'Failed to download file'], 500);
        }
    }

    /**
     * Send JSON response
     */
    private function sendJsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }
}
