<?php

namespace App\Controllers;

use App\Services\DocumentService;
use App\Services\EmailService;
use App\Services\FileUploadService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;
use PDO;
use Exception;

class DocumentController
{
    private DocumentService $documentService;
    private EmailService $emailService;
    private FileUploadService $fileUploadService;
    private AuthMiddleware $authMiddleware;
    private PDO $pdo;

    public function __construct()
    {
        $this->documentService = new DocumentService();
        $this->emailService = new EmailService();
        $this->fileUploadService = new FileUploadService();
        $this->authMiddleware = new AuthMiddleware();
        $this->pdo = Database::getInstance();
    }

    /**
     * Handle template upload
     */
    public function uploadTemplate(): void
    {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('instructor')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: upload_template.php?error=invalid_method');
            exit;
        }

        try {
            // Validate input
            $documentName = trim($_POST['document_name'] ?? '');
            $documentType = $_POST['document_type'] ?? '';
            $deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
            $description = trim($_POST['description'] ?? '');
            
            // Handle custom document fields
            $customDocumentName = trim($_POST['custom_document_name'] ?? '');
            $isRequired = isset($_POST['is_required']) ? (int)$_POST['is_required'] : 0;

            if (empty($documentName) || empty($documentType)) {
                throw new Exception('Document name and type are required');
            }

            // Validate document type
            $allowedTypes = [
                'moa', 'endorsement', 'parental_consent', 'misdemeanor_penalty',
                'ojt_plan', 'notarized_consent', 'pledge', 'other'
            ];

            if (!in_array($documentType, $allowedTypes)) {
                throw new Exception('Invalid document type');
            }
            
            // For custom documents, use custom name if provided
            if ($documentType === 'other' && !empty($customDocumentName)) {
                $documentName = $customDocumentName;
            }

            // Handle file upload
            if (!isset($_FILES['template_file'])) {
                throw new Exception('No file was uploaded');
            }
            
            $file = $_FILES['template_file'];
            
            // Check upload error with specific messages
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorMessages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
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

            // Validate file - use extension as primary validation method
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExtensions = ['pdf', 'docx', 'doc', 'txt'];
            
            if (!in_array($extension, $allowedExtensions)) {
                throw new Exception('Only PDF, DOCX, DOC, and TXT files are allowed. File extension: ' . $extension);
            }
            
            // Additional MIME type validation (but don't rely on it exclusively)
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            // Log the MIME type for debugging but don't fail on it
            error_log("File upload - Extension: $extension, MIME: $mimeType, File: " . $file['name']);

            if ($file['size'] > 10 * 1024 * 1024) { // 10MB
                throw new Exception('File size must be less than 10MB');
            }

            // Create upload directory
            $uploadDir = __DIR__ . '/../../uploads/templates/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'instructor_' . $_SESSION['user_id'] . '_' . $documentType . '_' . time() . '.' . $extension;
            $filePath = $uploadDir . $filename;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception('Failed to save uploaded file');
            }

            // Get instructor's section
            $stmt = $this->pdo->prepare("SELECT section_id FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $instructor = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$instructor || !$instructor['section_id']) {
                throw new Exception('Instructor must be assigned to a section');
            }

            // Create document record
            $documentId = $this->documentService->createDocument(
                $documentName,
                $documentType,
                'uploads/templates/' . $filename,
                $_SESSION['user_id'],
                $instructor['section_id'],
                $deadline,
                (bool)$isRequired
            );

            // Update description if provided
            if (!empty($description)) {
                $stmt = $this->pdo->prepare("
                    UPDATE documents 
                    SET description = ? 
                    WHERE id = ?
                ");
                $stmt->execute([$description, $documentId]);
            }

            // Send email notification to students
            $this->emailService->sendTemplateUploadNotification(
                $_SESSION['user_id'],
                $instructor['section_id'],
                $documentName,
                $documentType,
                $deadline,
                $description
            );

            // Log activity
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description) 
                VALUES (?, 'upload_template', ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                "Uploaded template: {$documentName}"
            ]);

            header('Location: templates.php?success=template_uploaded');
            exit;

        } catch (Exception $e) {
            // Log the error for debugging
            error_log("Template upload error: " . $e->getMessage());
            error_log("Stack trace: " . $e->getTraceAsString());
            
            header('Location: upload_template.php?error=' . urlencode($e->getMessage()));
            exit;
        }
    }

    /**
     * Get templates for instructor
     */
    public function getInstructorTemplates(): array
    {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('instructor')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        // Get instructor's section
        $stmt = $this->pdo->prepare("SELECT section_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $instructor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$instructor || !$instructor['section_id']) {
            return [];
        }

        // Get templates for this instructor (only their own uploads + pre-loaded templates)
        return $this->documentService->getDocumentsForInstructor($_SESSION['user_id'], $instructor['section_id']);
    }

    /**
     * Delete template
     */
    public function deleteTemplate(int $templateId): bool
    {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('instructor')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        // Get template
        $template = $this->documentService->getDocumentById($templateId);

        if (!$template) {
            return false;
        }

        // Check if instructor owns this template
        if ($template->uploaded_by !== $_SESSION['user_id']) {
            return false;
        }

        // Prevent deletion of system templates (preloaded templates)
        if ($template->uploaded_by === 1 && $template->uploaded_for_section === null) {
            return false;
        }

        // Delete template
        $result = $this->documentService->deleteDocument($templateId);

        if ($result) {
            // Log activity
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description) 
                VALUES (?, 'delete_template', ?)
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                "Deleted template: {$template->document_name}"
            ]);
        }

        return $result;
    }

    /**
     * Get template statistics
     */
    public function getTemplateStatistics(): array
    {
        // Check authentication
        if (!$this->authMiddleware->check()) {
            $this->authMiddleware->redirectToLogin();
        }

        if (!$this->authMiddleware->requireRole('instructor')) {
            $this->authMiddleware->redirectToUnauthorized();
        }

        // Get instructor's section
        $stmt = $this->pdo->prepare("SELECT section_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $instructor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$instructor || !$instructor['section_id']) {
            return [];
        }

        $sectionId = $instructor['section_id'];

        // Get template statistics
        $stats = [];

        // Total templates uploaded by this instructor
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) as count 
            FROM documents 
            WHERE uploaded_by = ? AND uploaded_for_section = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $sectionId]);
        $stats['total_templates'] = $stmt->fetchColumn();

        // Download statistics
        $stmt = $this->pdo->prepare("
            SELECT d.document_name, COUNT(al.id) as download_count
            FROM documents d
            LEFT JOIN activity_logs al ON al.description LIKE CONCAT('%Downloaded template: ', d.document_name, '%')
            WHERE d.uploaded_by = ? AND d.uploaded_for_section = ?
            GROUP BY d.id, d.document_name
            ORDER BY download_count DESC
        ");
        $stmt->execute([$_SESSION['user_id'], $sectionId]);
        $stats['download_stats'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }
}
