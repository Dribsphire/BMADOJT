<?php

namespace App\Middleware;

use App\Utils\Database;
use App\Utils\ActivityLogger;
use App\Services\DocumentIntegrationService;
use PDO;
use Exception;

/**
 * Attendance Middleware
 * Handles attendance system integration with other system components
 */
class AttendanceMiddleware
{
    private PDO $pdo;
    private DocumentIntegrationService $documentService;
    private ActivityLogger $logger;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $this->documentService = new DocumentIntegrationService();
        $this->logger = new ActivityLogger();
    }

    /**
     * Check if student can access attendance features
     * AC1: Document Compliance Integration
     */
    public function checkAttendanceAccess(int $studentId): array
    {
        try {
            // Check document compliance
            $complianceCheck = $this->documentService->integrateWithAttendance($studentId);
            
            if (!$complianceCheck['can_attend']) {
                $this->logger->logActivity('attendance_blocked', 'Document compliance required for attendance access', (int)$studentId);
                
                return [
                    'can_access' => false,
                    'reason' => 'document_compliance',
                    'message' => $complianceCheck['message'],
                    'compliance_data' => $complianceCheck['compliance_data'] ?? null,
                    'redirect_url' => 'documents.php'
                ];
            }

            // Check if student has active OJT period
            $ojtStatus = $this->checkOJTStatus($studentId);
            if (!$ojtStatus['active']) {
                return [
                    'can_access' => false,
                    'reason' => 'ojt_inactive',
                    'message' => 'Your OJT period is not currently active',
                    'redirect_url' => 'dashboard.php'
                ];
            }

            // Check if student is in good standing
            $standing = $this->checkStudentStanding($studentId);
            if (!$standing['good_standing']) {
                return [
                    'can_access' => false,
                    'reason' => 'poor_standing',
                    'message' => $standing['message'],
                    'redirect_url' => 'profile.php'
                ];
            }

            return [
                'can_access' => true,
                'compliance_data' => $complianceCheck['compliance_data'],
                'ojt_status' => $ojtStatus,
                'standing' => $standing
            ];

        } catch (Exception $e) {
            $this->logger->logActivity($studentId, 'attendance_access_error', 'Error checking attendance access: ' . $e->getMessage());
            
            return [
                'can_access' => false,
                'reason' => 'system_error',
                'message' => 'Unable to verify attendance access. Please try again.',
                'redirect_url' => 'dashboard.php'
            ];
        }
    }

    /**
     * Check OJT status for student
     */
    private function checkOJTStatus(int $studentId): array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT 
                    sp.ojt_start_date,
                    sp.status,
                    DATEDIFF(CURDATE(), sp.ojt_start_date) as days_since_start
                FROM student_profiles sp
                WHERE sp.user_id = ?
            ");
            $stmt->execute([$studentId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$profile) {
                return [
                    'active' => false,
                    'message' => 'Student profile not found'
                ];
            }

            $isActive = $profile['status'] === 'on_track' && 
                       $profile['ojt_start_date'] <= date('Y-m-d');

            return [
                'active' => $isActive,
                'start_date' => $profile['ojt_start_date'],
                'status' => $profile['status'],
                'days_since_start' => $profile['days_since_start']
            ];

        } catch (Exception $e) {
            return [
                'active' => false,
                'message' => 'Unable to check OJT status'
            ];
        }
    }

    /**
     * Check student standing
     */
    private function checkStudentStanding(int $studentId): array
    {
        try {
            // Check for any disciplinary actions
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as violation_count
                FROM activity_logs
                WHERE user_id = ? AND action LIKE '%violation%'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute([$studentId]);
            $violations = $stmt->fetchColumn();

            // Only check for violations, not attendance compliance rate
            $goodStanding = $violations == 0;

            return [
                'good_standing' => $goodStanding,
                'violation_count' => $violations,
                'compliance_rate' => 100, // Always consider 100% for access purposes
                'message' => $goodStanding ? 'Student in good standing' : 
                    'Student has disciplinary issues'
            ];

        } catch (Exception $e) {
            return [
                'good_standing' => false,
                'message' => 'Unable to check student standing'
            ];
        }
    }

    /**
     * Validate attendance session
     * AC2: User Authentication Integration
     */
    public function validateAttendanceSession(int $userId): array
    {
        try {
            // Check if user is authenticated
            if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $userId) {
                return [
                    'valid' => false,
                    'reason' => 'session_invalid',
                    'message' => 'Session expired. Please log in again.',
                    'redirect_url' => 'login.php'
                ];
            }

            // Check session timeout (2 hours for attendance)
            $sessionTimeout = 7200; // 2 hours
            if (isset($_SESSION['last_activity']) && 
                (time() - $_SESSION['last_activity']) > $sessionTimeout) {
                
                session_destroy();
                return [
                    'valid' => false,
                    'reason' => 'session_timeout',
                    'message' => 'Session expired due to inactivity. Please log in again.',
                    'redirect_url' => 'login.php'
                ];
            }

            // Update last activity
            $_SESSION['last_activity'] = time();

            // Check user role
            if ($_SESSION['role'] !== 'student') {
                return [
                    'valid' => false,
                    'reason' => 'invalid_role',
                    'message' => 'Access denied. Students only.',
                    'redirect_url' => 'dashboard.php'
                ];
            }

            return [
                'valid' => true,
                'user_id' => $userId,
                'role' => $_SESSION['role']
            ];

        } catch (Exception $e) {
            return [
                'valid' => false,
                'reason' => 'system_error',
                'message' => 'Unable to validate session'
            ];
        }
    }

    /**
     * Handle attendance transaction
     * AC3: Database Integration
     */
    public function handleAttendanceTransaction(callable $transactionCallback): array
    {
        try {
            $this->pdo->beginTransaction();

            $result = $transactionCallback($this->pdo);

            if ($result['success']) {
                $this->pdo->commit();
                $this->logger->logActivity(
                    'attendance_transaction_success', 
                    'Attendance transaction completed successfully',
                    (int)$_SESSION['user_id']
                );
            } else {
                $this->pdo->rollBack();
                $this->logger->logActivity(
                    'attendance_transaction_failed', 
                    'Attendance transaction failed: ' . $result['message'],
                    (int)$_SESSION['user_id']
                );
            }

            return $result;

        } catch (Exception $e) {
            $this->pdo->rollBack();
            $this->logger->logActivity(
                'attendance_transaction_error', 
                'Attendance transaction error: ' . $e->getMessage(),
                (int)$_SESSION['user_id']
            );
            
            return [
                'success' => false,
                'message' => 'Database transaction failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Check concurrent access
     * AC3: Database Integration
     */
    public function checkConcurrentAccess(int $studentId, string $action): array
    {
        try {
            // Check if student is already performing attendance action
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as active_sessions
                FROM attendance_records
                WHERE student_id = ? 
                AND date = CURDATE() 
                AND block_type = ?
                AND time_in IS NOT NULL 
                AND time_out IS NULL
            ");
            $stmt->execute([$studentId, $action]);
            $activeSessions = $stmt->fetchColumn();

            if ($activeSessions > 0) {
                return [
                    'allowed' => false,
                    'reason' => 'concurrent_access',
                    'message' => 'You already have an active ' . $action . ' session for today'
                ];
            }

            return [
                'allowed' => true,
                'message' => 'No concurrent access detected'
            ];

        } catch (Exception $e) {
            return [
                'allowed' => false,
                'reason' => 'system_error',
                'message' => 'Unable to check concurrent access'
            ];
        }
    }

    /**
     * Log attendance system activity
     * AC5: Error Handling and Logging
     */
    public function logAttendanceActivity(int $userId, string $action, array $data = []): void
    {
        try {
            $this->logger->logActivity('attendance_' . $action, json_encode($data), $userId);
        } catch (Exception $e) {
            // Log to system log if activity logger fails
            error_log("Attendance logging failed: " . $e->getMessage());
        }
    }

    /**
     * Handle attendance errors gracefully
     * AC5: Error Handling and Logging
     */
    public function handleAttendanceError(Exception $e, string $context = ''): array
    {
        $errorId = uniqid('att_error_');
        
        // Log error details
        $this->logger->logActivity(
            $_SESSION['user_id'] ?? 0, 
            'attendance_error', 
            "Error ID: {$errorId}, Context: {$context}, Message: " . $e->getMessage()
        );

        // Log to system log
        error_log("Attendance Error [{$errorId}]: {$context} - " . $e->getMessage());

        return [
            'success' => false,
            'error_id' => $errorId,
            'message' => 'An error occurred while processing your request. Please try again.',
            'support_message' => 'If the problem persists, contact support with Error ID: ' . $errorId
        ];
    }
}
