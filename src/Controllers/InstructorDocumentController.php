<?php

namespace App\Controllers;

use App\Services\InstructorDocumentService;
use App\Services\EmailService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;
use PDO;

/**
 * Instructor Document Controller
 * Handles instructor document review operations
 */
class InstructorDocumentController
{
    private InstructorDocumentService $instructorDocumentService;
    private EmailService $emailService;
    private AuthMiddleware $authMiddleware;
    private PDO $pdo;

    public function __construct()
    {
        $this->instructorDocumentService = new InstructorDocumentService();
        $this->emailService = new EmailService();
        $this->authMiddleware = new AuthMiddleware();
        $this->pdo = Database::getInstance();
    }

    /**
     * Get submissions for review
     */
    public function getSubmissionsForReview(
        int $sectionId,
        string $studentFilter = '',
        string $documentTypeFilter = '',
        string $statusFilter = '',
        string $dateFrom = '',
        string $dateTo = '',
        string $sortBy = 'submitted_at',
        string $sortOrder = 'DESC'
    ): array {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('instructor')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        return $this->instructorDocumentService->getSubmissionsForReview(
            $sectionId,
            $studentFilter,
            $documentTypeFilter,
            $statusFilter,
            $dateFrom,
            $dateTo,
            $sortBy,
            $sortOrder
        );
    }

    /**
     * Get review statistics
     */
    public function getReviewStatistics(int $sectionId): array
    {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('instructor')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        return $this->instructorDocumentService->getReviewStatistics($sectionId);
    }

    /**
     * Approve document
     */
    public function approveDocument(int $submissionId, int $instructorId, string $feedback = ''): array
    {
        try {
            // Check authentication
            if (!$this->authMiddleware->check()) {
                $this->authMiddleware->redirectToLogin();
            }

            if (!$this->authMiddleware->requireRole('instructor')) {
                $this->authMiddleware->redirectToUnauthorized();
            }

            // Update submission status
            $stmt = $this->pdo->prepare("
                UPDATE student_documents 
                SET status = 'approved', 
                    instructor_feedback = ?, 
                    reviewed_at = NOW(), 
                    updated_at = NOW()
                WHERE id = ? AND student_id IN (
                    SELECT id FROM users WHERE section_id = (
                        SELECT section_id FROM users WHERE id = ?
                    )
                )
            ");
            $stmt->execute([$feedback, $submissionId, $instructorId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Submission not found or access denied');
            }

            // Get submission details for email notification
            $stmt = $this->pdo->prepare("
                SELECT sd.*, d.document_name, d.document_type, u.full_name as student_name, u.email as student_email
                FROM student_documents sd
                JOIN documents d ON sd.document_id = d.id
                JOIN users u ON sd.student_id = u.id
                WHERE sd.id = ?
            ");
            $stmt->execute([$submissionId]);
            $submission = $stmt->fetch(PDO::FETCH_ASSOC);

            // Send email notification
            if ($submission) {
                $this->emailService->sendDocumentStatusNotification(
                    $submission['student_id'],
                    $submission['document_name'],
                    'approved',
                    $feedback
                );
            }

            // Log activity
            $this->logActivity($instructorId, 'approve_document', "Approved document submission ID: {$submissionId}");

            return [
                'success' => true,
                'message' => 'Document approved successfully'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Request revision
     */
    public function requestRevision(int $submissionId, int $instructorId, string $feedback): array
    {
        try {
            // Check authentication
            if (!$this->authMiddleware->check()) {
                $this->authMiddleware->redirectToLogin();
            }

            if (!$this->authMiddleware->requireRole('instructor')) {
                $this->authMiddleware->redirectToUnauthorized();
            }

            if (empty($feedback)) {
                throw new Exception('Feedback is required when requesting revision');
            }

            // Update submission status
            $stmt = $this->pdo->prepare("
                UPDATE student_documents 
                SET status = 'revision_required', 
                    instructor_feedback = ?, 
                    reviewed_at = NOW(), 
                    updated_at = NOW()
                WHERE id = ? AND student_id IN (
                    SELECT id FROM users WHERE section_id = (
                        SELECT section_id FROM users WHERE id = ?
                    )
                )
            ");
            $stmt->execute([$feedback, $submissionId, $instructorId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Submission not found or access denied');
            }

            // Get submission details for email notification
            $stmt = $this->pdo->prepare("
                SELECT sd.*, d.document_name, d.document_type, u.full_name as student_name, u.email as student_email
                FROM student_documents sd
                JOIN documents d ON sd.document_id = d.id
                JOIN users u ON sd.student_id = u.id
                WHERE sd.id = ?
            ");
            $stmt->execute([$submissionId]);
            $submission = $stmt->fetch(PDO::FETCH_ASSOC);

            // Send email notification
            if ($submission) {
                $this->emailService->sendDocumentStatusNotification(
                    $submission['student_id'],
                    $submission['document_name'],
                    'revision_required',
                    $feedback
                );
            }

            // Log activity
            $this->logActivity($instructorId, 'request_revision', "Requested revision for submission ID: {$submissionId}");

            return [
                'success' => true,
                'message' => 'Revision requested successfully'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Reject document
     */
    public function rejectDocument(int $submissionId, int $instructorId, string $feedback): array
    {
        try {
            // Check authentication
            if (!$this->authMiddleware->check()) {
                $this->authMiddleware->redirectToLogin();
            }

            if (!$this->authMiddleware->requireRole('instructor')) {
                $this->authMiddleware->redirectToUnauthorized();
            }

            if (empty($feedback)) {
                throw new Exception('Feedback is required when rejecting document');
            }

            // Update submission status
            $stmt = $this->pdo->prepare("
                UPDATE student_documents 
                SET status = 'rejected', 
                    instructor_feedback = ?, 
                    reviewed_at = NOW(), 
                    updated_at = NOW()
                WHERE id = ? AND student_id IN (
                    SELECT id FROM users WHERE section_id = (
                        SELECT section_id FROM users WHERE id = ?
                    )
                )
            ");
            $stmt->execute([$feedback, $submissionId, $instructorId]);

            if ($stmt->rowCount() === 0) {
                throw new Exception('Submission not found or access denied');
            }

            // Get submission details for email notification
            $stmt = $this->pdo->prepare("
                SELECT sd.*, d.document_name, d.document_type, u.full_name as student_name, u.email as student_email
                FROM student_documents sd
                JOIN documents d ON sd.document_id = d.id
                JOIN users u ON sd.student_id = u.id
                WHERE sd.id = ?
            ");
            $stmt->execute([$submissionId]);
            $submission = $stmt->fetch(PDO::FETCH_ASSOC);

            // Send email notification
            if ($submission) {
                $this->emailService->sendDocumentStatusNotification(
                    $submission['student_id'],
                    $submission['document_name'],
                    'rejected',
                    $feedback
                );
            }

            // Log activity
            $this->logActivity($instructorId, 'reject_document', "Rejected document submission ID: {$submissionId}");

            return [
                'success' => true,
                'message' => 'Document rejected successfully'
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Bulk approve documents
     */
    public function bulkApproveDocuments(array $submissionIds, int $instructorId): array
    {
        try {
            // Check authentication
            if (!$this->authMiddleware->check()) {
                $this->authMiddleware->redirectToLogin();
            }

            if (!$this->authMiddleware->requireRole('instructor')) {
                $this->authMiddleware->redirectToUnauthorized();
            }

            if (empty($submissionIds)) {
                throw new Exception('No submissions selected');
            }

            $placeholders = str_repeat('?,', count($submissionIds) - 1) . '?';
            
            // Update submissions status
            $stmt = $this->pdo->prepare("
                UPDATE student_documents 
                SET status = 'approved', 
                    reviewed_at = NOW(), 
                    updated_at = NOW()
                WHERE id IN ($placeholders) AND student_id IN (
                    SELECT id FROM users WHERE section_id = (
                        SELECT section_id FROM users WHERE id = ?
                    )
                )
            ");
            $params = array_merge($submissionIds, [$instructorId]);
            $stmt->execute($params);

            $approvedCount = $stmt->rowCount();

            // Send email notifications for approved documents
            if ($approvedCount > 0) {
                $stmt = $this->pdo->prepare("
                    SELECT sd.student_id, d.document_name
                    FROM student_documents sd
                    JOIN documents d ON sd.document_id = d.id
                    WHERE sd.id IN ($placeholders) AND sd.status = 'approved'
                ");
                $stmt->execute($submissionIds);
                $approvedSubmissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($approvedSubmissions as $submission) {
                    $this->emailService->sendDocumentStatusNotification(
                        $submission['student_id'],
                        $submission['document_name'],
                        'approved',
                        'Your document has been approved.'
                    );
                }
            }

            // Log activity
            $this->logActivity($instructorId, 'bulk_approve', "Bulk approved {$approvedCount} documents");

            return [
                'success' => true,
                'message' => "Successfully approved {$approvedCount} documents"
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Log activity
     */
    private function logActivity(int $userId, string $action, string $description): void
    {
        try {
            // Ensure userId is a valid integer and exists in users table
            $userId = (int) $userId;
            
            // Check if user exists before logging
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            if (!$stmt->fetch()) {
                error_log("ActivityLogger: User ID {$userId} not found in users table");
                return; // Skip logging if user doesn't exist
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$userId, $action, $description]);
        } catch (Exception $e) {
            error_log("ActivityLogger error: " . $e->getMessage());
            // Don't throw exception to prevent breaking the main operation
        }
    }
}
