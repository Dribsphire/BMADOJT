<?php
/**
 * Bulk action endpoint for forgot timeout requests
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

// Get bulk action parameters
$bulkAction = $_POST['bulk_action'] ?? '';
$requestIdsJson = $_POST['request_ids'] ?? '';
$response = $_POST['response'] ?? '';

if (!$bulkAction || !$requestIdsJson || !in_array($bulkAction, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid parameters']);
    exit;
}

$requestIds = json_decode($requestIdsJson, true);
if (!is_array($requestIds) || empty($requestIds)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request IDs']);
    exit;
}

// Initialize services
$pdo = Database::getInstance();
$reviewService = new ForgotTimeoutReviewService($pdo);

$processedCount = 0;
$errors = [];

try {
    $pdo->beginTransaction();
    
    foreach ($requestIds as $requestId) {
        try {
            // Get request details first
            $request = $reviewService->getRequestDetails($requestId, $user->id);
            if (!$request) {
                $errors[] = "Request ID $requestId not found";
                continue;
            }

            // Check if request is still pending
            if ($request['status'] !== 'pending') {
                $errors[] = "Request ID $requestId is no longer pending";
                continue;
            }

            if ($bulkAction === 'approve') {
                // Calculate proper time_out and hours_earned based on block type
                $timeIn = new DateTime($request['time_in']);
                $attendanceDate = $timeIn->format('Y-m-d');
                
                // Determine block end time based on block type
                $blockEndTime = match($request['block_type']) {
                    'morning' => new DateTime($attendanceDate . ' 12:00:00'),
                    'afternoon' => new DateTime($attendanceDate . ' 18:00:00'),
                    'overtime' => new DateTime($attendanceDate . ' 20:00:00'),
                    default => new DateTime($attendanceDate . ' 12:00:00') // fallback to morning
                };
                
                // Calculate hours from time_in to block end time
                $hoursWorked = $timeIn->diff($blockEndTime);
                $totalMinutes = ($hoursWorked->h * 60) + $hoursWorked->i;
                $hoursEarned = round($totalMinutes / 60, 2);
                
                // Update attendance record with calculated time_out and hours
                $stmt = $pdo->prepare("
                    UPDATE attendance_records 
                    SET time_out = ?,
                        hours_earned = ?
                    WHERE id = ?
                ");
                
                $timeOutFormatted = $blockEndTime->format('Y-m-d H:i:s');
                $result = $stmt->execute([
                    $timeOutFormatted,
                    $hoursEarned,
                    $request['attendance_record_id']
                ]);
                
                if (!$result) {
                    $errors[] = "Failed to update attendance record for request ID $requestId";
                    continue;
                }

                // Update student profile total hours
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
                    $errors[] = "Failed to update student profile for request ID $requestId";
                    continue;
                }
            }

            // Update request status
            $success = $reviewService->updateRequestStatus($requestId, $user->id, $bulkAction . 'd', $response);
            
            if (!$success) {
                $errors[] = "Failed to update request status for request ID $requestId";
                continue;
            }
            
            $processedCount++;
            
        } catch (Exception $e) {
            $errors[] = "Error processing request ID $requestId: " . $e->getMessage();
            continue;
        }
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'processed_count' => $processedCount,
        'total_requests' => count($requestIds),
        'errors' => $errors
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    error_log("ForgotTimeoutBulkAction error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Internal server error']);
}
?>
