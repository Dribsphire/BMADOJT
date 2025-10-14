<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../src/Utils/Database.php';
require_once '../../src/Services/DocumentService.php';
require_once '../../src/Services/FileUploadService.php';
require_once '../../src/Middleware/AuthMiddleware.php';

use App\Services\DocumentService;
use App\Services\FileUploadService;
use App\Middleware\AuthMiddleware;

// Check authentication
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('student')) {
    $authMiddleware->redirectToUnauthorized();
}

// Get document ID
$documentId = (int)($_GET['id'] ?? 0);

if ($documentId <= 0) {
    header('Location: documents.php?error=invalid_document');
    exit;
}

$pdo = App\Utils\Database::getInstance();
$documentService = new DocumentService();

// Get document details
$document = $documentService->getDocumentById($documentId);

if (!$document) {
    header('Location: documents.php?error=document_not_found');
    exit;
}

// Check if student has access to this document
$stmt = $pdo->prepare("SELECT section_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student || $document->uploaded_for_section != $student['section_id']) {
    header('Location: documents.php?error=access_denied');
    exit;
}

// Check if student has already submitted this document
$stmt = $pdo->prepare("
    SELECT sd.*, d.document_name, d.document_type, d.file_path as template_path
    FROM student_documents sd
    JOIN documents d ON sd.document_id = d.id
    WHERE sd.student_id = ? AND sd.document_id = ?
");
$stmt->execute([$_SESSION['user_id'], $documentId]);
$existingSubmission = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document_file'])) {
    try {
        // Validate file
        $file = $_FILES['document_file'];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload failed');
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
        
        // Create upload directory
        $uploadDir = __DIR__ . '/../../uploads/student_documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $filename = 'student_' . $_SESSION['user_id'] . '_custom_' . $documentId . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        // If there's an existing submission, update it; otherwise create new
        if ($existingSubmission) {
            // Update existing submission
            $stmt = $pdo->prepare("
                UPDATE student_documents 
                SET submission_file_path = ?, status = 'pending', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute(['uploads/student_documents/' . $filename, $existingSubmission['id']]);
            $submissionId = $existingSubmission['id'];
        } else {
            // Create new submission
            $stmt = $pdo->prepare("
                INSERT INTO student_documents (student_id, document_id, submission_file_path, status, submitted_at, created_at, updated_at)
                VALUES (?, ?, ?, 'pending', NOW(), NOW(), NOW())
            ");
            $stmt->execute([
                $_SESSION['user_id'],
                $documentId,
                'uploads/student_documents/' . $filename
            ]);
            $submissionId = $pdo->lastInsertId();
        }
        
        // Log activity
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description) 
            VALUES (?, 'submit_custom_document', ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "Submitted custom document: {$document->document_name}"
        ]);
        
        header('Location: documents.php?success=custom_document_submitted');
        exit;
        
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Get messages
$success = $_GET['success'] ?? '';
$error = $errorMessage ?? $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Custom Document | OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <?php include 'student-sidebar.php'; ?>
    
    <main class="main-content">
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="bi bi-file-earmark-plus me-2"></i>Submit Custom Document</h2>
                        <a href="documents.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back to Documents
                        </a>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Document Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Document Name:</strong><br>
                                    <?= htmlspecialchars($document->document_name) ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Document Type:</strong><br>
                                    <span class="badge bg-<?= $document->is_required ? 'danger' : 'secondary' ?>">
                                        <?= $document->is_required ? 'Required' : 'Optional' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <?php if ($document->deadline): ?>
                            <div class="row mt-2">
                                <div class="col-md-6">
                                    <strong>Deadline:</strong><br>
                                    <span class="text-<?= strtotime($document->deadline) < time() ? 'danger' : 'success' ?>">
                                        <i class="bi bi-calendar me-1"></i><?= date('M d, Y', strtotime($document->deadline)) ?>
                                    </span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($document->description): ?>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <strong>Instructions:</strong><br>
                                    <div class="alert alert-info">
                                        <?= htmlspecialchars($document->description) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($existingSubmission): ?>
                            <div class="row mt-2">
                                <div class="col-12">
                                    <div class="alert alert-warning">
                                        <i class="bi bi-exclamation-triangle me-2"></i>
                                        <strong>Previous Submission:</strong> You have already submitted this document. 
                                        Uploading a new file will replace your previous submission.
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="mb-0">Upload Document</h5>
                        </div>
                        <div class="card-body">
                            <?php if ($error): ?>
                                <div class="alert alert-danger">
                                    <i class="bi bi-exclamation-triangle me-2"></i>
                                    <strong>Upload Failed:</strong> <?= htmlspecialchars($error) ?>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success">
                                    <i class="bi bi-check-circle me-2"></i>Document submitted successfully!
                                </div>
                            <?php endif; ?>
                            
                            <form method="POST" enctype="multipart/form-data">
                                <div class="mb-3">
                                    <label for="document_file" class="form-label">Select Document File *</label>
                                    <input type="file" class="form-control" id="document_file" name="document_file" 
                                           accept=".pdf,.docx,.doc,.txt" required>
                                    <div class="form-text">PDF, DOCX, DOC, or TXT files only. Max 10MB</div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <h6><i class="bi bi-lightbulb me-2"></i>Submission Guidelines:</h6>
                                    <ul class="mb-0">
                                        <li>Ensure your document is complete and properly formatted</li>
                                        <li>Follow any specific instructions provided by your instructor</li>
                                        <li>Your instructor will review and approve/reject your submission</li>
                                        <li>You will receive email notifications about the status</li>
                                    </ul>
                                </div>
                                
                                <div class="d-flex gap-2">
                                    <button type="submit" class="btn btn-success">
                                        <i class="bi bi-upload me-1"></i>Submit Document
                                    </button>
                                    <a href="documents.php" class="btn btn-secondary">Cancel</a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
