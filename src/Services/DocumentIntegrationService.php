<?php

namespace App\Services;

use App\Utils\Database;
use App\Utils\ActivityLogger;
use App\Services\EmailService;
use App\Services\AuthenticationService;
use PDO;
use Exception;

/**
 * Document Integration Service
 * Handles integration between document system and other system components
 */
class DocumentIntegrationService
{
    private PDO $pdo;
    private EmailService $emailService;
    private AuthenticationService $authService;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $this->emailService = new EmailService();
        $this->authService = new AuthenticationService();
    }

    /**
     * Integrate document system with attendance system
     */
    public function integrateWithAttendance(int $studentId): array
    {
        try {
            // Check if student has completed all required documents
            $compliance = $this->checkDocumentCompliance($studentId);
            
            if (!$compliance['is_compliant']) {
                return [
                    'can_attend' => false,
                    'reason' => 'Document compliance required',
                    'compliance_data' => $compliance,
                    'message' => "You must complete all required documents before accessing attendance features. {$compliance['missing_documents']} document(s) remaining."
                ];
            }

            return [
                'can_attend' => true,
                'compliance_data' => $compliance,
                'message' => 'All required documents completed'
            ];

        } catch (Exception $e) {
            $this->logError('integrateWithAttendance', $e->getMessage(), ['student_id' => $studentId]);
            return [
                'can_attend' => false,
                'reason' => 'System error',
                'message' => 'Unable to verify document compliance'
            ];
        }
    }

    /**
     * Integrate document system with notification system
     */
    public function integrateWithNotifications(int $documentId, string $event, array $data = []): bool
    {
        try {
            // Get document and related user information
            $stmt = $this->pdo->prepare("
                SELECT d.*, u.full_name as student_name, u.email as student_email,
                       i.full_name as instructor_name, i.email as instructor_email
                FROM documents d
                LEFT JOIN users u ON d.uploaded_for_section = u.section_id
                LEFT JOIN users i ON d.uploaded_by = i.id
                WHERE d.id = ?
            ");
            $stmt->execute([$documentId]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$document) {
                return false;
            }

            // Send appropriate notifications based on event
            switch ($event) {
                case 'document_uploaded':
                    return $this->sendDocumentUploadedNotification($document, $data);
                case 'document_approved':
                    return $this->sendDocumentApprovedNotification($document, $data);
                case 'document_rejected':
                    return $this->sendDocumentRejectedNotification($document, $data);
                case 'document_overdue':
                    return $this->sendDocumentOverdueNotification($document, $data);
                default:
                    return true;
            }

        } catch (Exception $e) {
            $this->logError('integrateWithNotifications', $e->getMessage(), [
                'document_id' => $documentId,
                'event' => $event,
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Integrate document system with user authentication
     */
    public function integrateWithAuthentication(int $userId, string $action): bool
    {
        try {
            // Check user permissions for document actions
            $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user) {
                return false;
            }

            // Validate action based on user role
            switch ($user['role']) {
                case 'admin':
                    return true; // Admin can do everything
                case 'instructor':
                    return in_array($action, ['view', 'upload', 'review', 'approve', 'reject']);
                case 'student':
                    return in_array($action, ['view', 'download', 'upload', 'submit']);
                default:
                    return false;
            }

        } catch (Exception $e) {
            $this->logError('integrateWithAuthentication', $e->getMessage(), [
                'user_id' => $userId,
                'action' => $action
            ]);
            return false;
        }
    }

    /**
     * Handle data integrity checks
     */
    public function checkDataIntegrity(): array
    {
        $issues = [];

        try {
            // Check for orphaned student documents
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as orphaned_count
                FROM student_documents sd
                LEFT JOIN documents d ON sd.document_id = d.id
                WHERE d.id IS NULL
            ");
            $orphanedCount = $stmt->fetchColumn();
            if ($orphanedCount > 0) {
                $issues[] = "Found $orphanedCount orphaned student documents";
            }

            // Check for orphaned documents
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as orphaned_count
                FROM documents d
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.uploaded_by IS NOT NULL AND u.id IS NULL
            ");
            $orphanedCount = $stmt->fetchColumn();
            if ($orphanedCount > 0) {
                $issues[] = "Found $orphanedCount documents with invalid uploader references";
            }

            // Check for invalid section references
            $stmt = $this->pdo->query("
                SELECT COUNT(*) as invalid_count
                FROM documents d
                LEFT JOIN users u ON d.uploaded_for_section = u.section_id
                WHERE d.uploaded_for_section IS NOT NULL AND u.section_id IS NULL
            ");
            $invalidCount = $stmt->fetchColumn();
            if ($invalidCount > 0) {
                $issues[] = "Found $invalidCount documents with invalid section references";
            }

            return [
                'integrity_check' => true,
                'issues_found' => count($issues),
                'issues' => $issues,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            $this->logError('checkDataIntegrity', $e->getMessage());
            return [
                'integrity_check' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Handle performance optimization
     */
    public function optimizePerformance(): array
    {
        $optimizations = [];

        try {
            // Add indexes for frequently queried columns
            $indexes = [
                'CREATE INDEX IF NOT EXISTS idx_documents_section ON documents(uploaded_for_section)',
                'CREATE INDEX IF NOT EXISTS idx_documents_type ON documents(document_type)',
                'CREATE INDEX IF NOT EXISTS idx_student_documents_student ON student_documents(student_id)',
                'CREATE INDEX IF NOT EXISTS idx_student_documents_status ON student_documents(status)',
                'CREATE INDEX IF NOT EXISTS idx_student_documents_updated ON student_documents(updated_at)'
            ];

            foreach ($indexes as $index) {
                try {
                    $this->pdo->exec($index);
                    $optimizations[] = "Index created successfully";
                } catch (Exception $e) {
                    $optimizations[] = "Index creation failed: " . $e->getMessage();
                }
            }

            // Analyze table performance
            $tables = ['documents', 'student_documents', 'users'];
            foreach ($tables as $table) {
                try {
                    $this->pdo->exec("ANALYZE TABLE $table");
                    $optimizations[] = "Table $table analyzed";
                } catch (Exception $e) {
                    $optimizations[] = "Table $table analysis failed: " . $e->getMessage();
                }
            }

            return [
                'optimization_complete' => true,
                'optimizations' => $optimizations,
                'timestamp' => date('Y-m-d H:i:s')
            ];

        } catch (Exception $e) {
            $this->logError('optimizePerformance', $e->getMessage());
            return [
                'optimization_complete' => false,
                'error' => $e->getMessage(),
                'timestamp' => date('Y-m-d H:i:s')
            ];
        }
    }

    /**
     * Handle security integration
     */
    public function handleSecurityIntegration(int $userId, string $action, array $context = []): bool
    {
        try {
            // Check user permissions
            if (!$this->integrateWithAuthentication($userId, $action)) {
                $this->logSecurityEvent('unauthorized_access', [
                    'user_id' => $userId,
                    'action' => $action,
                    'context' => $context
                ]);
                return false;
            }

            // Check for suspicious activity
            $suspiciousActivity = $this->checkSuspiciousActivity($userId, $action, $context);
            if ($suspiciousActivity['suspicious']) {
                $this->logSecurityEvent('suspicious_activity', [
                    'user_id' => $userId,
                    'action' => $action,
                    'context' => $context,
                    'reasons' => $suspiciousActivity['reasons']
                ]);
                return false;
            }

            return true;

        } catch (Exception $e) {
            $this->logError('handleSecurityIntegration', $e->getMessage(), [
                'user_id' => $userId,
                'action' => $action,
                'context' => $context
            ]);
            return false;
        }
    }

    /**
     * Check document compliance
     */
    private function checkDocumentCompliance(int $studentId): array
    {
        // Get total required documents (all required documents)
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as total_required
            FROM documents d
            WHERE d.is_required = 1
        ");
        $stmt->execute();
        $totalRequired = $stmt->fetchColumn();

        // Get approved required documents for student
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as approved_count
            FROM student_documents sd
            JOIN documents d ON sd.document_id = d.id
            WHERE sd.student_id = ? AND sd.status = 'approved' AND d.is_required = 1
        ");
        $stmt->execute([$studentId]);
        $approvedCount = $stmt->fetchColumn();

        $isCompliant = $approvedCount >= $totalRequired;
        $completionRate = $totalRequired > 0 ? round(($approvedCount / $totalRequired) * 100, 2) : 100;

        return [
            'is_compliant' => $isCompliant,
            'total_required' => $totalRequired,
            'approved_count' => $approvedCount,
            'completion_rate' => $completionRate,
            'missing_documents' => $totalRequired - $approvedCount
        ];
    }

    /**
     * Send document uploaded notification
     */
    private function sendDocumentUploadedNotification(array $document, array $data): bool
    {
        // Implementation for document uploaded notification
        return true;
    }

    /**
     * Send document approved notification
     */
    private function sendDocumentApprovedNotification(array $document, array $data): bool
    {
        // Implementation for document approved notification
        return true;
    }

    /**
     * Send document rejected notification
     */
    private function sendDocumentRejectedNotification(array $document, array $data): bool
    {
        // Implementation for document rejected notification
        return true;
    }

    /**
     * Send document overdue notification
     */
    private function sendDocumentOverdueNotification(array $document, array $data): bool
    {
        // Implementation for document overdue notification
        return true;
    }

    /**
     * Check for suspicious activity
     */
    private function checkSuspiciousActivity(int $userId, string $action, array $context): array
    {
        $reasons = [];

        // Check for rapid successive actions
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as action_count
            FROM activity_logs
            WHERE action = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
        ");
        $stmt->execute([$action]);
        $actionCount = $stmt->fetchColumn();

        if ($actionCount > 10) {
            $reasons[] = "Rapid successive actions detected";
        }

        return [
            'suspicious' => count($reasons) > 0,
            'reasons' => $reasons
        ];
    }

    /**
     * Log security event
     */
    private function logSecurityEvent(string $event, array $data): void
    {
        try {
            $activityLogger = new ActivityLogger();
            $activityLogger->logSecurityEvent($event, $data);
        } catch (Exception $e) {
            error_log("Security event logging failed: " . $e->getMessage());
        }
    }

    /**
     * Log error
     */
    private function logError(string $method, string $message, array $context = []): void
    {
        $logMessage = sprintf(
            "[%s] DocumentIntegrationService::%s: %s | Context: %s",
            date('Y-m-d H:i:s'),
            $method,
            $message,
            json_encode($context)
        );
        
        error_log($logMessage);
    }
}
