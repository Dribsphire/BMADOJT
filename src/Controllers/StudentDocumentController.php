<?php

namespace App\Controllers;

use App\Services\StudentDocumentService;
use App\Services\DocumentService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;
use PDO;

/**
 * Student Document Controller
 * Handles student document operations
 */
class StudentDocumentController
{
    private StudentDocumentService $studentDocumentService;
    private DocumentService $documentService;
    private AuthMiddleware $authMiddleware;
    private PDO $pdo;

    public function __construct()
    {
        $this->studentDocumentService = new StudentDocumentService();
        $this->documentService = new DocumentService();
        $this->authMiddleware = new AuthMiddleware();
        $this->pdo = Database::getInstance();
    }

    /**
     * Get student's document dashboard data
     */
    public function getDashboardData(int $studentId): array
    {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('student')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        // Get student's section
        $stmt = $this->pdo->prepare("SELECT section_id FROM users WHERE id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$student || !$student['section_id']) {
            return [];
        }

        // Get templates for student's section
        $templates = $this->documentService->getDocumentsForSection($student['section_id']);
        
        // Get required document types
        $requiredTypes = $this->documentService->getRequiredDocumentTypes();
        
        // Get student's submitted documents
        $studentDocuments = $this->studentDocumentService->getStudentDocuments($studentId);
        
        // Calculate progress
        $progress = $this->studentDocumentService->calculateProgress($studentId, $requiredTypes);
        
        return [
            'templates' => $templates,
            'requiredTypes' => $requiredTypes,
            'studentDocuments' => $studentDocuments,
            'progress' => $progress
        ];
    }

    /**
     * Download template
     */
    public function downloadTemplate(int $templateId, int $studentId): bool
    {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('student')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        // Get template
        $template = $this->documentService->getDocumentById($templateId);
        
        if (!$template || !$template->isTemplate()) {
            return false;
        }

        // Check if student can access this template
        $stmt = $this->pdo->prepare("SELECT section_id FROM users WHERE id = ?");
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);

        // Allow access if: template is pre-loaded (uploaded_for_section IS NULL) OR template is for student's section
        if ($template->uploaded_for_section !== null && $template->uploaded_for_section != $student['section_id']) {
            return false;
        }

        // Log download activity
        $this->studentDocumentService->logDownloadActivity($studentId, $templateId);
        
        return true;
    }

    /**
     * Submit document
     */
    public function submitDocument(int $studentId, string $documentType, array $file): array
    {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('student')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        try {
            // Validate document type
            $requiredTypes = $this->documentService->getRequiredDocumentTypes();
            if (!isset($requiredTypes[$documentType])) {
                throw new Exception('Invalid document type');
            }

            // Get student's section and find template
            $stmt = $this->pdo->prepare("SELECT section_id FROM users WHERE id = ?");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            $templates = $this->documentService->getDocumentsForSection($student['section_id']);
            $template = null;
            
            foreach ($templates as $t) {
                if ($t->document_type === $documentType) {
                    $template = $t;
                    break;
                }
            }

            if (!$template) {
                throw new Exception('Template not found for this document type');
            }

            // Submit the document
            $result = $this->studentDocumentService->submitDocument(
                $studentId,
                $template->id,
                $file,
                $documentType
            );

            return [
                'success' => true,
                'message' => 'Document submitted successfully',
                'data' => $result
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get submission history
     */
    public function getSubmissionHistory(int $studentId): array
    {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('student')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        return $this->studentDocumentService->getSubmissionHistory($studentId);
    }

    /**
     * Resubmit document
     */
    public function resubmitDocument(int $studentId, int $submissionId, array $file): array
    {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('student')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        try {
            $result = $this->studentDocumentService->resubmitDocument($studentId, $submissionId, $file);
            
            return [
                'success' => true,
                'message' => 'Document resubmitted successfully',
                'data' => $result
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
