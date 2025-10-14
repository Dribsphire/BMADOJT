<?php

namespace App\Services;

use App\Utils\Database;
use PDO;

/**
 * Student Document Service
 * Handles student document operations and business logic
 */
class StudentDocumentService
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
    }

    /**
     * Get student's submitted documents
     */
    public function getStudentDocuments(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sd.*, d.document_name, d.document_type, d.file_path as template_path
            FROM student_documents sd
            JOIN documents d ON sd.document_id = d.id
            WHERE sd.student_id = ?
            ORDER BY d.document_type
        ");
        $stmt->execute([$studentId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate student's document progress
     */
    public function calculateProgress(int $studentId, array $requiredTypes): array
    {
        $studentDocuments = $this->getStudentDocuments($studentId);
        
        // Create a map of student documents by type
        $studentDocsByType = [];
        foreach ($studentDocuments as $doc) {
            $studentDocsByType[$doc['document_type']] = $doc;
        }

        $totalRequired = count($requiredTypes);
        $approvedCount = 0;
        $submittedCount = 0;
        $pendingCount = 0;
        $revisionRequiredCount = 0;

        foreach ($requiredTypes as $type => $name) {
            if (isset($studentDocsByType[$type])) {
                $submittedCount++;
                switch ($studentDocsByType[$type]['status']) {
                    case 'approved':
                        $approvedCount++;
                        break;
                    case 'pending':
                        $pendingCount++;
                        break;
                    case 'revision_required':
                        $revisionRequiredCount++;
                        break;
                }
            }
        }

        $progressPercentage = $totalRequired > 0 ? ($approvedCount / $totalRequired) * 100 : 0;

        return [
            'total_required' => $totalRequired,
            'approved' => $approvedCount,
            'submitted' => $submittedCount,
            'pending' => $pendingCount,
            'revision_required' => $revisionRequiredCount,
            'not_submitted' => $totalRequired - $submittedCount,
            'progress_percentage' => $progressPercentage,
            'is_complete' => $approvedCount >= $totalRequired
        ];
    }

    /**
     * Submit a document
     */
    public function submitDocument(int $studentId, int $documentId, array $file, string $documentType): array
    {
        try {
            // Validate file
            $this->validateFile($file);

            // Create upload directory
            $uploadDir = __DIR__ . '/../../uploads/student_documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'student_' . $studentId . '_' . $documentType . '_' . time() . '.' . $extension;
            $filePath = $uploadDir . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }

            // Check if student already has a submission for this document
            $stmt = $this->pdo->prepare("
                SELECT id FROM student_documents 
                WHERE student_id = ? AND document_id = ?
            ");
            $stmt->execute([$studentId, $documentId]);
            $existingSubmission = $stmt->fetch();

            if ($existingSubmission) {
                // Update existing submission
                $stmt = $this->pdo->prepare("
                    UPDATE student_documents 
                    SET submission_file_path = ?, status = 'pending', submitted_at = NOW(), updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$filePath, $existingSubmission['id']]);
                $submissionId = $existingSubmission['id'];
            } else {
                // Create new submission
                $stmt = $this->pdo->prepare("
                    INSERT INTO student_documents (student_id, document_id, submission_file_path, status, submitted_at)
                    VALUES (?, ?, ?, 'pending', NOW())
                ");
                $stmt->execute([$studentId, $documentId, $filePath]);
                $submissionId = $this->pdo->lastInsertId();
            }

            // Log activity
            $this->logActivity($studentId, 'submit_document', "Submitted document: {$documentType}");

            return [
                'submission_id' => $submissionId,
                'file_path' => $filePath,
                'status' => 'pending'
            ];

        } catch (Exception $e) {
            throw new Exception('Document submission failed: ' . $e->getMessage());
        }
    }

    /**
     * Resubmit a document
     */
    public function resubmitDocument(int $studentId, int $submissionId, array $file): array
    {
        try {
            // Validate file
            $this->validateFile($file);

            // Verify submission belongs to student
            $stmt = $this->pdo->prepare("
                SELECT id FROM student_documents 
                WHERE id = ? AND student_id = ?
            ");
            $stmt->execute([$submissionId, $studentId]);
            $submission = $stmt->fetch();

            if (!$submission) {
                throw new Exception('Submission not found or access denied');
            }

            // Create upload directory
            $uploadDir = __DIR__ . '/../../uploads/student_documents/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'student_' . $studentId . '_resubmit_' . $submissionId . '_' . time() . '.' . $extension;
            $filePath = $uploadDir . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }

            // Update submission
            $stmt = $this->pdo->prepare("
                UPDATE student_documents 
                SET submission_file_path = ?, status = 'pending', submitted_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$filePath, $submissionId]);

            // Log activity
            $this->logActivity($studentId, 'resubmit_document', "Resubmitted document (ID: {$submissionId})");

            return [
                'submission_id' => $submissionId,
                'file_path' => $filePath,
                'status' => 'pending'
            ];

        } catch (Exception $e) {
            throw new Exception('Document resubmission failed: ' . $e->getMessage());
        }
    }

    /**
     * Get submission history
     */
    public function getSubmissionHistory(int $studentId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT sd.*, d.document_name, d.document_type, u.full_name as instructor_name
            FROM student_documents sd
            JOIN documents d ON sd.document_id = d.id
            LEFT JOIN users u ON sd.reviewed_by = u.id
            WHERE sd.student_id = ?
            ORDER BY sd.submitted_at DESC
        ");
        $stmt->execute([$studentId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Log download activity
     */
    public function logDownloadActivity(int $studentId, int $templateId): void
    {
        $stmt = $this->pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description) 
            VALUES (?, 'download_template', ?)
        ");
        $stmt->execute([
            $studentId,
            "Downloaded template (ID: {$templateId})"
        ]);
    }

    /**
     * Validate uploaded file
     */
    private function validateFile(array $file): void
    {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            
            $errorMessage = $errorMessages[$file['error']] ?? 'Unknown upload error (code: ' . $file['error'] . ')';
            throw new Exception('File upload failed: ' . $errorMessage);
        }

        // Check file type - use extension as primary validation method
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['pdf', 'docx', 'doc', 'txt'];
        
        if (!in_array($extension, $allowedExtensions)) {
            throw new Exception('Only PDF, DOCX, DOC, and TXT files are allowed. File extension: ' . $extension);
        }

        // Check file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File size must be less than 10MB');
        }
    }

    /**
     * Log activity
     */
    private function logActivity(int $userId, string $action, string $description): void
    {
        try {
            // Validate that the user exists
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            if (!$stmt->fetch()) {
                error_log("StudentDocumentService: User ID {$userId} not found in users table, skipping activity log");
                return;
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $action, $description]);
        } catch (Exception $e) {
            error_log("StudentDocumentService: Failed to log activity: " . $e->getMessage());
        }
    }
}
