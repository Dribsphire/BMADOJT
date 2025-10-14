<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../src/Controllers/DocumentController.php';
require_once '../../src/Middleware/AuthMiddleware.php';

use App\Controllers\DocumentController;
use App\Middleware\AuthMiddleware;

// Check authentication
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('instructor')) {
    $authMiddleware->redirectToUnauthorized();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $controller = new DocumentController();
    $controller->uploadTemplate();
}

// Get error/success messages
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

// Get required document types
$requiredTypes = [
    'moa' => 'MOA (Memorandum of Agreement)',
    'endorsement' => 'Endorsement Letter',
    'parental_consent' => 'Parental Consent',
    'misdemeanor_penalty' => 'Misdemeanor Penalty',
    'ojt_plan' => 'OJT Plan',
    'notarized_consent' => 'Notarized Parental Consent',
    'pledge' => 'Pledge of Good Conduct',
    'other' => 'Others (Custom Document)'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Template | OJT Route</title>
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
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .upload-area:hover {
            border-color: var(--chmsu-green);
            background-color: #f8f9fa;
        }
        
        .upload-area.dragover {
            border-color: var(--chmsu-green);
            background-color: #e8f5e8;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Sidebar -->
    <?php include 'teacher-sidebar.php'; ?>
    
    <!-- Main Content -->
    <main>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Upload Document Template</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="templates.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>Back to Templates
                </a>
            </div>
        </div>

        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-upload me-2"></i>Template Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if ($error): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Upload Failed:</strong> <?= htmlspecialchars($error) ?>
                                <br><small>Check the error logs for more details.</small>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($success): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>Template uploaded successfully!
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="document_type" class="form-label">Document Type *</label>
                                    <select class="form-select" id="document_type" name="document_type" required>
                                        <option value="">Select document type...</option>
                                        <?php foreach ($requiredTypes as $type => $name): ?>
                                            <option value="<?= $type ?>"><?= htmlspecialchars($name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="document_name" class="form-label">Document Name *</label>
                                    <input type="text" class="form-control" id="document_name" name="document_name" 
                                           placeholder="e.g., MOA Template 2024" required>
                                    <div class="form-text" id="document_name_help">
                                        Enter a descriptive name for this document
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row" id="custom_document_fields" style="display: none;">
                                <div class="col-md-6 mb-3">
                                    <label for="custom_document_name" class="form-label">Custom Document Name *</label>
                                    <input type="text" class="form-control" id="custom_document_name" name="custom_document_name" 
                                           placeholder="e.g., Company Policy Document">
                                    <div class="form-text">Enter a specific name for your custom document</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="is_required" class="form-label">Document Requirement</label>
                                    <select class="form-select" id="is_required" name="is_required">
                                        <option value="0">Optional Document</option>
                                        <option value="1">Required Document</option>
                                    </select>
                                    <div class="form-text">Choose if this document is required for students</div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="deadline" class="form-label">Deadline (Optional)</label>
                                    <input type="date" class="form-control" id="deadline" name="deadline">
                                    <div class="form-text">Leave empty if no deadline</div>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="template_file" class="form-label">Template File *</label>
                                    <input type="file" class="form-control" id="template_file" name="template_file" 
                                           accept=".pdf,.docx,.doc,.txt" required>
                                    <div class="form-text">PDF, DOCX, DOC, or TXT files only. Max 10MB</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Instructions (Optional)</label>
                                <textarea class="form-control" id="description" name="description" rows="3" 
                                          placeholder="Provide specific instructions for students on how to complete this document..."></textarea>
                            </div>
                            
                            <!-- File Upload Area -->
                            <div class="upload-area mb-4" id="uploadArea">
                                <i class="bi bi-cloud-upload" style="font-size: 3rem; color: #6c757d;"></i>
                                <h5 class="mt-3">Drag & Drop File Here</h5>
                                <p class="text-muted">or click to select file</p>
                            </div>
                            
                            <div class="alert alert-info">
                                <h6><i class="bi bi-lightbulb me-2"></i>Upload Guidelines:</h6>
                                <ul class="mb-0">
                                    <li>Ensure the template is properly formatted and professional</li>
                                    <li>Include all necessary fields and instructions</li>
                                    <li>Students will receive email notifications when you upload templates</li>
                                    <li>Set deadlines to encourage timely completion</li>
                                </ul>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="templates.php" class="btn btn-secondary me-md-2">Cancel</a>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadConfirmModal">
                                    <i class="bi bi-upload me-1"></i>Upload Template
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Upload Confirmation Modal -->
    <div class="modal fade" id="uploadConfirmModal" tabindex="-1" aria-labelledby="uploadConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadConfirmModalLabel">
                        <i class="bi bi-exclamation-triangle me-2"></i>Confirm Template Upload
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to upload this template?</p>
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle me-2"></i>What happens next:</h6>
                        <ul class="mb-0">
                            <li>Template will be stored in the system</li>
                            <li>All students in your section will receive email notifications</li>
                            <li>Students can download and complete the template</li>
                            <li>You can manage the template from the Templates page</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="confirmUpload">
                        <i class="bi bi-upload me-1"></i>Yes, Upload Template
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle document type change
        document.getElementById('document_type').addEventListener('change', function() {
            const customFields = document.getElementById('custom_document_fields');
            const documentNameHelp = document.getElementById('document_name_help');
            const customDocumentName = document.getElementById('custom_document_name');
            
            if (this.value === 'other') {
                customFields.style.display = 'block';
                customDocumentName.required = true;
                documentNameHelp.textContent = 'Enter a descriptive name for this document';
            } else {
                customFields.style.display = 'none';
                customDocumentName.required = false;
                documentNameHelp.textContent = 'Enter a descriptive name for this document';
            }
        });

        // Drag and drop functionality
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('template_file');

        uploadArea.addEventListener('click', () => fileInput.click());

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
                // Create a new FileList-like object
                const dt = new DataTransfer();
                dt.items.add(files[0]);
                fileInput.files = dt.files;
                updateFileDisplay();
            }
        });

        // Handle file input change
        fileInput.addEventListener('change', (e) => {
            updateFileDisplay();
        });

        function updateFileDisplay() {
            const file = fileInput.files[0];
            if (file) {
                uploadArea.innerHTML = `
                    <i class="bi bi-file-earmark-check" style="font-size: 3rem; color: var(--chmsu-green);"></i>
                    <h5 class="mt-3">${file.name}</h5>
                    <p class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</p>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearFile()">
                        Change File
                    </button>
                `;
            }
        }

        function clearFile() {
            fileInput.value = '';
            uploadArea.innerHTML = `
                <i class="bi bi-cloud-upload" style="font-size: 3rem; color: #6c757d;"></i>
                <h5 class="mt-3">Drag & Drop File Here</h5>
                <p class="text-muted">or click to select file</p>
            `;
        }

        // Form validation
        document.getElementById('uploadForm').addEventListener('submit', function(e) {
            const file = document.getElementById('template_file').files[0];
            if (!file) {
                e.preventDefault();
                alert('Please select a file to upload');
                return;
            }
            
            if (file.size > 10 * 1024 * 1024) {
                e.preventDefault();
                alert('File size must be less than 10MB');
                return;
            }
        });

        // Confirmation modal handler
        document.getElementById('confirmUpload').addEventListener('click', function() {
            // Validate form before submitting
            const file = document.getElementById('template_file').files[0];
            const documentName = document.getElementById('document_name').value.trim();
            const documentType = document.getElementById('document_type').value;

            if (!file) {
                alert('Please select a file to upload');
                return;
            }

            if (!documentName) {
                alert('Please enter a document name');
                return;
            }

            if (!documentType) {
                alert('Please select a document type');
                return;
            }

            if (file.size > 10 * 1024 * 1024) {
                alert('File size must be less than 10MB');
                return;
            }

            // Show loading state
            this.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Uploading...';
            this.disabled = true;

            // Submit the form
            document.getElementById('uploadForm').submit();
        });
    </script>
</body>
</html>
