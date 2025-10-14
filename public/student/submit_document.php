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

// Get document type
$documentType = $_GET['type'] ?? '';

$requiredTypes = [
    'moa' => 'MOA (Memorandum of Agreement)',
    'endorsement' => 'Endorsement Letter',
    'parental_consent' => 'Parental Consent',
    'misdemeanor_penalty' => 'Misdemeanor Penalty',
    'ojt_plan' => 'OJT Plan',
    'notarized_consent' => 'Notarized Parental Consent',
    'pledge' => 'Pledge of Good Conduct'
];

if (!isset($requiredTypes[$documentType])) {
    header('Location: documents.php?error=invalid_type');
    exit;
}

$documentName = $requiredTypes[$documentType];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document_file'])) {
    try {
        $pdo = App\Utils\Database::getInstance();
        
        // Get student's section ID
        $stmt = $pdo->prepare("SELECT section_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        $studentSectionId = $student['section_id'];
        
        // Get template document for student's section
        $documentService = new DocumentService();
        $templates = $documentService->getDocumentsForSection($studentSectionId);
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
        
        // Additional MIME type validation (but don't rely on it exclusively)
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        // Log the MIME type for debugging but don't fail on it
        error_log("Student document upload - Extension: $extension, MIME: $mimeType, File: " . $file['name']);
        
        // Check file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File size must be less than 10MB');
        }
        
        // Create upload directory
        $uploadDir = '../../uploads/student_documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'student_' . $_SESSION['user_id'] . '_' . $documentType . '_' . time() . '.' . $extension;
        $filePath = $uploadDir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        // Check if student already has a submission for this document type
        $stmt = $pdo->prepare("
            SELECT id FROM student_documents 
            WHERE student_id = ? AND document_id = ?
        ");
        $stmt->execute([$_SESSION['user_id'], $template->id]);
        $existingSubmission = $stmt->fetch();
        
        if ($existingSubmission) {
            // Update existing submission
            $stmt = $pdo->prepare("
                UPDATE student_documents 
                SET submission_file_path = ?, status = 'pending', submitted_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$filePath, $existingSubmission['id']]);
        } else {
            // Create new submission
            $stmt = $pdo->prepare("
                INSERT INTO student_documents (student_id, document_id, submission_file_path, status, submitted_at)
                VALUES (?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$_SESSION['user_id'], $template->id, $filePath]);
        }
        
        // Log activity
        $stmt = $pdo->prepare("
            INSERT INTO activity_logs (user_id, action, description) 
            VALUES (?, 'submit_document', ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            "Submitted document: {$documentName}"
        ]);
        
        header('Location: documents.php?success=document_submitted');
        exit;
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Submit Document | OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="bg-light">
    <!-- Sidebar -->
    <?php include 'student-sidebar.php'; ?>
    
    <!-- Main Content -->
    <main>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Submit Document</h1>
            <a href="documents.php" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i>Back to Documents
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-upload me-2"></i><?= htmlspecialchars($documentName) ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="document_file" class="form-label">Select Document File</label>
                                <input type="file" class="form-control" id="document_file" name="document_file" 
                                       accept=".pdf,.docx,.doc,.txt" required>
                                <div class="form-text">
                                    <i class="bi bi-info-circle me-1"></i>
                                    Allowed formats: PDF, DOCX, DOC, TXT. Maximum file size: 10MB
                                </div>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6><i class="bi bi-lightbulb me-2"></i>Submission Guidelines:</h6>
                                <ul class="mb-0">
                                    <li>Ensure your document is complete and properly filled out</li>
                                    <li>Check that all required signatures are present</li>
                                    <li>Verify that the document is clear and readable</li>
                                    <li>Your instructor will review and approve/reject your submission</li>
                                </ul>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="documents.php" class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="submit" class="btn btn-success">
                                    <i class="bi bi-upload me-1"></i>Submit Document
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
