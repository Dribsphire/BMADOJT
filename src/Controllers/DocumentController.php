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

            // Handle file upload(s)
            if (!isset($_FILES['template_file'])) {
                throw new Exception('No file was uploaded');
            }
            
            $files = $_FILES['template_file'];
            
            // Normalize single file to array format
            if (!is_array($files['name'])) {
                $files = [
                    'name' => [$files['name']],
                    'type' => [$files['type']],
                    'tmp_name' => [$files['tmp_name']],
                    'error' => [$files['error']],
                    'size' => [$files['size']]
                ];
            }
            
            $uploadedCount = 0;
            $errors = [];
            $allowedExtensions = ['pdf', 'docx', 'doc', 'txt'];
            
            // Get instructor's section (once for all files)
            $stmt = $this->pdo->prepare("SELECT section_id FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $instructor = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$instructor || !$instructor['section_id']) {
                throw new Exception('Instructor must be assigned to a section');
            }
            
            // Create upload directory
            $uploadDir = __DIR__ . '/../../uploads/templates/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Process each file
            for ($i = 0; $i < count($files['name']); $i++) {
                if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                    $errorMessages = [
                        UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize (' . ini_get('upload_max_filesize') . ')',
                        UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                        UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                        UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
                    ];
                    
                    $errorMessage = $errorMessages[$files['error'][$i]] ?? 'Unknown upload error (code: ' . $files['error'][$i] . ')';
                    $errors[] = $files['name'][$i] . ': ' . $errorMessage;
                    continue;
                }
                
                $file = [
                    'name' => $files['name'][$i],
                    'type' => $files['type'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'error' => $files['error'][$i],
                    'size' => $files['size'][$i]
                ];
                
                // Validate file extension
                $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                if (!in_array($extension, $allowedExtensions)) {
                    $errors[] = $file['name'] . ': Invalid file extension. Only PDF, DOCX, DOC, and TXT files are allowed.';
                    continue;
                }
                
                // Validate file size
                if ($file['size'] > 10 * 1024 * 1024) { // 10MB
                    $errors[] = $file['name'] . ': File size exceeds 10MB limit.';
                    continue;
                }
                
                // Generate unique filename
                $filename = 'instructor_' . $_SESSION['user_id'] . '_' . $documentType . '_' . time() . '_' . $i . '.' . $extension;
                $filePath = $uploadDir . $filename;
                
                // Move uploaded file
                if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                    $errors[] = $file['name'] . ': Failed to save uploaded file.';
                    continue;
                }
                
                // Create document name for this file (append number if multiple files)
                $fileDocumentName = count($files['name']) > 1 
                    ? $documentName . ' (' . ($i + 1) . ')'
                    : $documentName;
                
                // Create document record
                try {
                    $documentId = $this->documentService->createDocument(
                        $fileDocumentName,
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
                        $fileDocumentName,
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
                        "Uploaded template: {$fileDocumentName}"
                    ]);
                    
                    $uploadedCount++;
                } catch (\Exception $e) {
                    $errors[] = $file['name'] . ': ' . $e->getMessage();
                    // Clean up uploaded file if document creation failed
                    if (file_exists($filePath)) {
                        @unlink($filePath);
                    }
                }
            }
            
            // Report results
            if ($uploadedCount === 0) {
                throw new Exception('No files were uploaded successfully. Errors: ' . implode('; ', $errors));
            }
            
            if (count($errors) > 0) {
                // Some files failed, but some succeeded
                $_SESSION['warning'] = $uploadedCount . ' file(s) uploaded successfully. Some files failed: ' . implode('; ', $errors);
            }

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
