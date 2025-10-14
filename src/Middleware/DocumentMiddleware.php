<?php

namespace App\Middleware;

use App\Utils\Database;
use App\Utils\ActivityLogger;
use PDO;
use Exception;

/**
 * Document Middleware
 * Handles document system integration with other system components
 */
class DocumentMiddleware
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Check document compliance for attendance access
     */
    public function checkDocumentCompliance(int $studentId): array
    {
        try {
            // Get required documents for student's section
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as total_required
                FROM documents d
                JOIN users u ON d.uploaded_for_section = u.section_id
                WHERE u.id = ? AND d.is_required = 1
            ");
            $stmt->execute([$studentId]);
            $totalRequired = $stmt->fetchColumn();

            // Get approved documents for student
            $stmt = $this->pdo->prepare("
                SELECT COUNT(*) as approved_count
                FROM student_documents sd
                JOIN documents d ON sd.document_id = d.id
                WHERE sd.student_id = ? AND sd.status = 'approved'
            ");
            $stmt->execute([$studentId]);
            $approvedCount = $stmt->fetchColumn();

            $isCompliant = $approvedCount >= $totalRequired;
            $completionRate = $totalRequired > 0 ? round(($approvedCount / $totalRequired) * 100, 2) : 100;

            return [
                'compliant' => $isCompliant,
                'required_count' => $totalRequired,
                'approved_count' => $approvedCount,
                'compliance_percentage' => $completionRate,
                'pending_count' => $totalRequired - $approvedCount
            ];

        } catch (Exception $e) {
            $this->logError('checkDocumentCompliance', $e->getMessage(), ['student_id' => $studentId]);
            return [
                'compliant' => false,
                'required_count' => 0,
                'approved_count' => 0,
                'completion_rate' => 0,
                'missing_documents' => 0,
                'error' => 'Unable to check compliance'
            ];
        }
    }

    /**
     * Validate document access permissions
     */
    public function validateDocumentAccess(int $userId, int $documentId, string $action = 'view'): bool
    {
        try {
            // Get user role and section
            $stmt = $this->pdo->prepare("SELECT role, section_id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return false;
            }

            // Get document details
            $stmt = $this->pdo->prepare("
                SELECT uploaded_by, uploaded_for_section, document_type 
                FROM documents WHERE id = ?
            ");
            $stmt->execute([$documentId]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$document) {
                return false;
            }

            // Admin can access all documents
            if ($user['role'] === 'admin') {
                return true;
            }

            // Instructor can access documents for their section
            if ($user['role'] === 'instructor' && $document['uploaded_for_section'] == $user['section_id']) {
                return true;
            }

            // Student can access documents for their section
            if ($user['role'] === 'student' && $document['uploaded_for_section'] == $user['section_id']) {
                return true;
            }

            // Check if it's a pre-loaded template (available to all)
            if ($document['uploaded_for_section'] === null) {
                return true;
            }

            return false;

        } catch (Exception $e) {
            $this->logError('validateDocumentAccess', $e->getMessage(), [
                'user_id' => $userId,
                'document_id' => $documentId,
                'action' => $action
            ]);
            return false;
        }
    }

    /**
     * Handle document system integration with notifications
     */
    public function handleDocumentNotification(int $documentId, string $event, array $data = []): bool
    {
        try {
            // Get document details
            $stmt = $this->pdo->prepare("
                SELECT d.*, u.full_name as instructor_name, u.email as instructor_email
                FROM documents d
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.id = ?
            ");
            $stmt->execute([$documentId]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$document) {
                return false;
            }

            // Log the notification event
            $this->logActivity('document_notification', [
                'document_id' => $documentId,
                'event' => $event,
                'data' => $data
            ]);

            // Handle different notification events
            switch ($event) {
                case 'document_uploaded':
                    return $this->handleDocumentUploaded($document, $data);
                case 'document_approved':
                    return $this->handleDocumentApproved($document, $data);
                case 'document_rejected':
                    return $this->handleDocumentRejected($document, $data);
                case 'document_overdue':
                    return $this->handleDocumentOverdue($document, $data);
                default:
                    return true;
            }

        } catch (Exception $e) {
            $this->logError('handleDocumentNotification', $e->getMessage(), [
                'document_id' => $documentId,
                'event' => $event,
                'data' => $data
            ]);
            return false;
        }
    }

    /**
     * Handle file upload integration
     */
    public function handleFileUpload(string $filePath, array $fileInfo, int $userId): array
    {
        try {
            // Validate file
            $validation = $this->validateFileUpload($fileInfo);
            if (!$validation['valid']) {
                return $validation;
            }

            // Check storage space
            $storageCheck = $this->checkStorageSpace($filePath);
            if (!$storageCheck['sufficient']) {
                return [
                    'success' => false,
                    'error' => 'Insufficient storage space',
                    'details' => $storageCheck
                ];
            }

            // Log file upload
            $this->logActivity('file_upload', [
                'user_id' => $userId,
                'file_path' => $filePath,
                'file_size' => $fileInfo['size'],
                'file_type' => $fileInfo['type']
            ]);

            return [
                'success' => true,
                'file_path' => $filePath,
                'file_size' => $fileInfo['size'],
                'file_type' => $fileInfo['type']
            ];

        } catch (Exception $e) {
            $this->logError('handleFileUpload', $e->getMessage(), [
                'file_path' => $filePath,
                'user_id' => $userId,
                'file_info' => $fileInfo
            ]);
            return [
                'success' => false,
                'error' => 'File upload failed',
                'details' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle system integration errors
     */
    public function handleIntegrationError(string $component, Exception $error, array $context = []): void
    {
        $this->logError('integration_error', $error->getMessage(), [
            'component' => $component,
            'context' => $context,
            'trace' => $error->getTraceAsString()
        ]);
    }

    /**
     * Validate file upload
     */
    private function validateFileUpload(array $fileInfo): array
    {
        $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/msword', 'text/plain'];
        $allowedExtensions = ['pdf', 'docx', 'doc', 'txt'];
        $maxSize = 10 * 1024 * 1024; // 10MB

        // Check file size
        if ($fileInfo['size'] > $maxSize) {
            return [
                'valid' => false,
                'error' => 'File size exceeds maximum allowed size (10MB)'
            ];
        }

        // Check file type
        $extension = strtolower(pathinfo($fileInfo['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $allowedExtensions)) {
            return [
                'valid' => false,
                'error' => 'File type not allowed. Allowed types: PDF, DOCX, DOC, TXT'
            ];
        }

        return [
            'valid' => true,
            'extension' => $extension,
            'size' => $fileInfo['size']
        ];
    }

    /**
     * Check storage space
     */
    private function checkStorageSpace(string $filePath): array
    {
        $uploadDir = dirname($filePath);
        
        // Check if directory exists, if not create it
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $freeSpace = disk_free_space($uploadDir);
        $totalSpace = disk_total_space($uploadDir);
        
        if ($freeSpace === false || $totalSpace === false) {
            return [
                'sufficient' => true, // Assume sufficient if we can't check
                'free_space' => 0,
                'total_space' => 0,
                'usage_percentage' => 0
            ];
        }
        
        $usedSpace = $totalSpace - $freeSpace;
        $usagePercentage = $totalSpace > 0 ? ($usedSpace / $totalSpace) * 100 : 0;

        return [
            'sufficient' => $freeSpace > (100 * 1024 * 1024), // At least 100MB free
            'free_space' => $freeSpace,
            'total_space' => $totalSpace,
            'usage_percentage' => $usagePercentage
        ];
    }

    /**
     * Handle document uploaded notification
     */
    private function handleDocumentUploaded(array $document, array $data): bool
    {
        // Implementation for document uploaded notification
        return true;
    }

    /**
     * Handle document approved notification
     */
    private function handleDocumentApproved(array $document, array $data): bool
    {
        // Implementation for document approved notification
        return true;
    }

    /**
     * Handle document rejected notification
     */
    private function handleDocumentRejected(array $document, array $data): bool
    {
        // Implementation for document rejected notification
        return true;
    }

    /**
     * Handle document overdue notification
     */
    private function handleDocumentOverdue(array $document, array $data): bool
    {
        // Implementation for document overdue notification
        return true;
    }

    /**
     * Log activity
     */
    private function logActivity(string $action, array $data): void
    {
        try {
            $activityLogger = new ActivityLogger();
            $activityLogger->logActivity($action, json_encode($data));
        } catch (Exception $e) {
            error_log("Failed to log activity: " . $e->getMessage());
        }
    }

    /**
     * Log error
     */
    private function logError(string $method, string $message, array $context = []): void
    {
        $logMessage = sprintf(
            "[%s] %s: %s | Context: %s",
            date('Y-m-d H:i:s'),
            $method,
            $message,
            json_encode($context)
        );
        
        error_log($logMessage);
        
        // Also log to database if possible
        try {
            $activityLogger = new ActivityLogger();
            $activityLogger->logError($logMessage);
        } catch (Exception $e) {
            // If database logging fails, just use error_log
        }
    }
}
