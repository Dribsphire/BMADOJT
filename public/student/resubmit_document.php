<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../src/Utils/Database.php';
require_once '../../src/Controllers/StudentDocumentController.php';
require_once '../../src/Middleware/AuthMiddleware.php';

use App\Controllers\StudentDocumentController;
use App\Middleware\AuthMiddleware;

// Check authentication
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('student')) {
    $authMiddleware->redirectToUnauthorized();
}

// Get submission ID
$submissionId = $_GET['id'] ?? 0;

if (!$submissionId) {
    header('Location: documents.php?error=invalid_submission');
    exit;
}

$controller = new StudentDocumentController();

// Get submission details
$pdo = App\Utils\Database::getInstance();
$stmt = $pdo->prepare("
    SELECT sd.*, d.document_name, d.document_type, d.file_path as template_path,
           sd.reviewed_at, sd.instructor_feedback
    FROM student_documents sd
    JOIN documents d ON sd.document_id = d.id
    WHERE sd.id = ? AND sd.student_id = ?
");
$stmt->execute([$submissionId, $_SESSION['user_id']]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$submission) {
    header('Location: documents.php?error=submission_not_found');
    exit;
}

// Check if resubmission is allowed
if (!in_array($submission['status'], ['revision_required', 'rejected'])) {
    header('Location: documents.php?error=resubmission_not_allowed');
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document_file'])) {
    $result = $controller->resubmitDocument(
        $_SESSION['user_id'],
        $submissionId,
        $_FILES['document_file']
    );
    
    if ($result['success']) {
        header('Location: view_submission.php?id=' . $submissionId . '&success=document_resubmitted');
        exit;
    } else {
        $errorMessage = $result['message'];
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
    <title>Resubmit Document | OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --chmsu-green: #0ea539;
        }
        
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
            cursor: pointer;
        }
        
        .upload-area:hover {
            border-color: var(--chmsu-green);
            background-color: #f8f9fa;
        }
        
        .upload-area.dragover {
            border-color: var(--chmsu-green);
            background-color: #e8f5e8;
        }
        
        .file-info {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .feedback-box {
            background-color: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 1rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Sidebar -->
    <?php include 'student-sidebar.php'; ?>
    
    <!-- Main Content -->
    <main>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Resubmit Document</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <a href="view_submission.php?id=<?= $submissionId ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Submission
                    </a>
                </div>
            </div>
        </div>

        <!-- Success Message -->
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-arrow-clockwise me-2"></i>Resubmit <?= htmlspecialchars($submission['document_name']) ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <!-- Instructor Feedback -->
                        <?php if ($submission['instructor_feedback']): ?>
                        <div class="feedback-box">
                            <h6 class="text-danger mb-2">
                                <i class="bi bi-exclamation-triangle me-1"></i>Instructor Feedback
                            </h6>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($submission['instructor_feedback'])) ?></p>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" id="resubmitForm">
                            <div class="upload-area" id="uploadArea">
                                <i class="bi bi-cloud-upload" style="font-size: 3rem; color: #6c757d;"></i>
                                <h5 class="mt-3">Drop your revised file here or click to browse</h5>
                                <p class="text-muted">Supported formats: PDF, DOCX, DOC, TXT (Max 10MB)</p>
                                <input type="file" name="document_file" id="fileInput" class="d-none" accept=".pdf,.docx,.doc,.txt" required>
                            </div>
                            
                            <div id="fileInfo" class="file-info d-none">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-file-earmark me-2"></i>
                                    <div class="flex-grow-1">
                                        <div class="fw-bold" id="fileName"></div>
                                        <small class="text-muted" id="fileSize"></small>
                                    </div>
                                    <button type="button" class="btn btn-sm btn-outline-danger" id="removeFile">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-warning" id="submitBtn" disabled>
                                    <i class="bi bi-arrow-clockwise me-1"></i>Resubmit Document
                                </button>
                                <a href="view_submission.php?id=<?= $submissionId ?>" class="btn btn-outline-secondary ms-2">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-info-circle me-2"></i>Resubmission Guidelines
                        </h6>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                <strong>Address Feedback:</strong> Make sure to address all instructor feedback
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                <strong>File Format:</strong> PDF, DOCX, DOC, or TXT
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                <strong>File Size:</strong> Maximum 10MB
                            </li>
                            <li class="mb-2">
                                <i class="bi bi-check-circle text-success me-2"></i>
                                <strong>Quality:</strong> Clear, readable text
                            </li>
                        </ul>
                        
                        <div class="alert alert-warning mt-3">
                            <small>
                                <i class="bi bi-lightbulb me-1"></i>
                                <strong>Note:</strong> This will replace your previous submission. Make sure your revised document addresses all feedback.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload handling
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const fileSize = document.getElementById('fileSize');
        const removeFile = document.getElementById('removeFile');
        const submitBtn = document.getElementById('submitBtn');

        // Click to upload
        uploadArea.addEventListener('click', () => fileInput.click());

        // File input change
        fileInput.addEventListener('change', handleFileSelect);

        // Drag and drop
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                handleFileSelect();
            }
        });

        // Remove file
        removeFile.addEventListener('click', () => {
            fileInput.value = '';
            fileInfo.classList.add('d-none');
            submitBtn.disabled = true;
        });

        function handleFileSelect() {
            const file = fileInput.files[0];
            if (file) {
                fileName.textContent = file.name;
                fileSize.textContent = formatFileSize(file.size);
                fileInfo.classList.remove('d-none');
                submitBtn.disabled = false;
            }
        }

        function formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>
