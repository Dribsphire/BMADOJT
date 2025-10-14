<?php
/**
 * AJAX endpoint for forgot timeout request actions (approve/reject)
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Services\ForgotTimeoutReviewService;
use App\Utils\Database;

// Start session
session_start();

// Set timezone to Philippines (UTC+08:00)
date_default_timezone_set('Asia/Manila');

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication
if (!$authMiddleware->requireRole('instructor')) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get current user
$user = $authService->getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get request parameters
$requestId = (int)($_POST['request_id'] ?? 0);
$action = $_POST['action'] ?? '';
$response = $_POST['response'] ?? '';

if (!$requestId || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

// Initialize services
$pdo = Database::getInstance();
$reviewService = new ForgotTimeoutReviewService($pdo);

try {
    // Debug logging
    error_log("ForgotTimeoutAction: Starting approval process for request ID: $requestId");
    error_log("ForgotTimeoutAction: User ID: " . $user->id);
    error_log("ForgotTimeoutAction: Action: $action");
    
    // Get request details first
    $request = $reviewService->getRequestDetails($requestId, $user->id);
    if (!$request) {
        error_log("ForgotTimeoutAction: Request not found for ID: $requestId");
        http_response_code(404);
        echo json_encode(['error' => 'Request not found']);
        exit;
    }

    error_log("ForgotTimeoutAction: Request found - Status: " . $request['status']);
    error_log("ForgotTimeoutAction: Request details: " . json_encode($request));

    // Check if request is still pending
    if ($request['status'] !== 'pending') {
        error_log("ForgotTimeoutAction: Request is no longer pending - Status: " . $request['status']);
        http_response_code(400);
        echo json_encode(['error' => 'Request is no longer pending']);
        exit;
    }

    // Start transaction
    $pdo->beginTransaction();

    if ($action === 'approve') {
        error_log("ForgotTimeoutAction: Processing approval for attendance record ID: " . $request['attendance_record_id']);
        
        // Calculate proper time_out and hours_earned based on block type
        $timeIn = new DateTime($request['time_in']);
        $attendanceDate = $timeIn->format('Y-m-d');
        
        error_log("ForgotTimeoutAction: Time in: " . $request['time_in']);
        error_log("ForgotTimeoutAction: Block type: " . $request['block_type']);
        
        // Determine block end time based on block type
        $blockEndTime = match($request['block_type']) {
            'morning' => new DateTime($attendanceDate . ' 12:00:00'),
            'afternoon' => new DateTime($attendanceDate . ' 18:00:00'),
            'overtime' => new DateTime($attendanceDate . ' 20:00:00'),
            default => new DateTime($attendanceDate . ' 12:00:00') // fallback to morning
        };
        
        error_log("ForgotTimeoutAction: Block end time: " . $blockEndTime->format('Y-m-d H:i:s'));
        
        // Calculate hours from time_in to block end time
        $hoursWorked = $timeIn->diff($blockEndTime);
        $totalMinutes = ($hoursWorked->h * 60) + $hoursWorked->i;
        $hoursEarned = round($totalMinutes / 60, 2);
        
        error_log("ForgotTimeoutAction: Hours earned: $hoursEarned");
        
        // Update attendance record with calculated time_out and hours
        $stmt = $pdo->prepare("
            UPDATE attendance_records 
            SET time_out = ?,
                hours_earned = ?
            WHERE id = ?
        ");
        
        $timeOutFormatted = $blockEndTime->format('Y-m-d H:i:s');
        error_log("ForgotTimeoutAction: Updating attendance record with time_out: $timeOutFormatted, hours_earned: $hoursEarned");
        
        $result = $stmt->execute([
            $timeOutFormatted,
            $hoursEarned,
            $request['attendance_record_id']
        ]);
        
        if (!$result) {
            error_log("ForgotTimeoutAction: Failed to update attendance record");
            throw new Exception("Failed to update attendance record");
        }
        
        error_log("ForgotTimeoutAction: Attendance record updated successfully");

        // Update student profile total hours
        error_log("ForgotTimeoutAction: Updating student profile for student ID: " . $request['student_id']);
        
        $stmt = $pdo->prepare("
            UPDATE student_profiles 
            SET total_hours_accumulated = (
                SELECT COALESCE(SUM(hours_earned), 0) 
                FROM attendance_records 
                WHERE student_id = ? AND time_in IS NOT NULL AND time_out IS NOT NULL
            ),
            updated_at = NOW()
            WHERE user_id = ?
        ");
        
        $result = $stmt->execute([$request['student_id'], $request['student_id']]);
        
        if (!$result) {
            error_log("ForgotTimeoutAction: Failed to update student profile");
            throw new Exception("Failed to update student profile");
        }
        
        error_log("ForgotTimeoutAction: Student profile updated successfully");

    } elseif ($action === 'reject') {
        error_log("ForgotTimeoutAction: Processing rejection");
        // For rejection, we don't update the attendance record
        // Just update the request status
    }

    // Update request status
    error_log("ForgotTimeoutAction: Updating request status to: " . $action . 'd');
    $success = $reviewService->updateRequestStatus($requestId, $user->id, $action . 'd', $response);
    
    if (!$success) {
        error_log("ForgotTimeoutAction: Failed to update request status");
        throw new Exception("Failed to update request status");
    }
    
    error_log("ForgotTimeoutAction: Request status updated successfully");

    if ($success) {
        $pdo->commit();
        echo json_encode([
            'success' => true,
            'message' => 'Request ' . $action . 'd successfully'
        ]);
    } else {
        $pdo->rollBack();
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update request']);
    }

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("ForgotTimeoutAction error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
