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

$controller = new DocumentController();

// Handle delete request
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $templateId = (int) $_GET['delete'];
    if ($controller->deleteTemplate($templateId)) {
        header('Location: templates.php?success=template_deleted');
        exit;
    } else {
        header('Location: templates.php?error=delete_failed');
        exit;
    }
}

// Get templates and statistics
$templates = $controller->getInstructorTemplates();
$statistics = $controller->getTemplateStatistics();

// Get messages
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';

// Get required document types for modal
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
    <title>Document Templates | OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <script type="text/javascript" src="../js/sidebarSlide.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --chmsu-green: #0ea539;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }

        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
            transition: background-color 0.2s ease-in-out;
        }
        
        .stat-card {
            background: var(--chmsu-green);
            color: white;
        }
        .stat-label {
            color: white;
        }
        
        /* Fixed position alert styling */
        .alert-fixed {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
            max-width: 500px;
            animation: slideInRight 0.3s ease-out;
        }
        
        .alert-fixed.fade-out {
            animation: fadeOut 0.5s ease-in forwards;
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes fadeOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(100%);
            }
        }
    </style>
</head>
<body class="bg-light">
    <?php include 'teacher-sidebar.php'; ?>
    
    <main>
    <?php include 'navigation-header.php'; ?>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Pre-required OJT documents templates</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadTemplateModal">
                    <i class="bi bi-plus me-1" style="color: white;"></i>Upload Template
                </button>
            </div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-fixed alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i>
                <?php if ($success === 'template_deleted'): ?>
                    Template deleted successfully!
                <?php elseif ($success === 'template_uploaded'): ?>
                    Template uploaded successfully!
                <?php endif; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= $statistics['total_templates'] ?? 0 ?></h3>
                        <p class="stat-label">Total Templates</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= count($templates) ?></h3>
                        <p class="stat-label">Active Templates</p>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3">
                <div class="card stat-card">
                    <div class="card-body text-center">
                        <h3 class="stat-number"><?= array_sum(array_column($statistics['download_stats'] ?? [], 'download_count')) ?></h3>
                        <p class="stat-label">Total Downloads</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Templates Table -->
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
            <?php if (empty($templates)): ?>
                    <div class="text-center py-5">
                            <i class="bi bi-file-earmark-text" style="font-size: 4rem; color: #6c757d;"></i>
                            <h4 class="mt-3">No Templates Uploaded</h4>
                            <p class="text-muted">Upload your first document template to get started.</p>
                        <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadTemplateModal">
                                <i class="bi bi-plus me-1"></i>Upload Template
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light border-bottom">
                                <tr>
                                    <th class="fw-semibold py-3 px-4">Document Name</th>
                                    <th class="fw-semibold py-3 px-4">Type</th>
                                    <th class="fw-semibold py-3 px-4">Status</th>
                                    <th class="fw-semibold py-3 px-4">Deadline</th>
                                    <th class="fw-semibold py-3 px-4">Uploaded</th>
                                    <th class="fw-semibold py-3 px-4 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $template): 
                                    // Get download count for this template
                                    $downloadCount = 0;
                                    foreach ($statistics['download_stats'] ?? [] as $stat) {
                                        if ($stat['document_name'] === $template->document_name) {
                                            $downloadCount = $stat['download_count'] ?? 0;
                                            break;
                                        }
                                    }
                                ?>
                                    <tr class="border-bottom">
                                        <td class="px-4 py-3">
                                            <div class="fw-semibold text-dark">
                                                <?= htmlspecialchars($template->document_name) ?>
                </div>
                                            <?php if ($template->uploaded_by === 1 && $template->uploaded_for_section === null): ?>
                                                <small class="text-muted">
                                                    <i class="bi bi-shield-check me-1"></i>System Template
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <span class="badge bg-primary bg-gradient">
                                                <?= ucfirst(str_replace('_', ' ', $template->document_type)) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if ($template->hasDeadline() && $template->isOverdue()): ?>
                                                <span class="badge bg-danger">
                                                    <i class="bi bi-exclamation-triangle me-1"></i>Overdue
                                                </span>
                                            <?php elseif ($template->hasDeadline()): ?>
                                                <span class="badge bg-warning">
                                                    <i class="bi bi-clock me-1"></i><?= $template->getDaysUntilDeadline() ?> days left
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-success">
                                                    <i class="bi bi-check-circle me-1"></i>Active
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <?php if ($template->hasDeadline()): ?>
                                                <div class="text-dark">
                                                    <i class="bi bi-calendar-event me-1"></i>
                                                    <?= date('M j, Y', strtotime($template->deadline)) ?>
                                    </div>
                                            <?php else: ?>
                                                <span class="text-muted">No deadline</span>
                                    <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="text-muted">
                                                <i class="bi bi-calendar3 me-1"></i>
                                                <?= date('M j, Y', strtotime($template->created_at)) ?>
                                    </div>
                                            <small class="text-muted">
                                                <i class="bi bi-download me-1"></i><?= $downloadCount ?> downloads
                                            </small>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="btn-group" role="group">
                                                <a href="download_template.php?id=<?= $template->id ?>" 
                                                   class="btn btn-sm btn-outline-primary border-0 shadow-sm" 
                                                   title="Download Template"
                                                   style="border-radius: 6px 0 0 6px;">
                                                    <i class="bi bi-download"></i>
                                                </a>
                                                <?php if ($template->uploaded_by === $_SESSION['user_id']): ?>
                                                    <a href="edit_template.php?id=<?= $template->id ?>" 
                                                       class="btn btn-sm btn-outline-info border-0 shadow-sm" 
                                                       title="Edit Template">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <a href="?delete=<?= $template->id ?>" 
                                                       onclick="return confirm('Are you sure you want to delete this template?')"
                                                       class="btn btn-sm btn-outline-danger border-0 shadow-sm" 
                                                       title="Delete Template"
                                                       style="border-radius: 0 6px 6px 0;">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <span class="btn btn-sm btn-outline-secondary border-0 shadow-sm disabled" 
                                                          title="System Template (Cannot edit)"
                                                          style="border-radius: 0 6px 6px 0;">
                                                        <i class="bi bi-lock"></i>
                                                    </span>
                                <?php endif; ?>
                                </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
            <?php endif; ?>
            </div>
        </div>

       
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutModalLabel">
                        <i class="bi bi-box-arrow-right me-2"></i>Confirm Logout
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to logout from OJT Route?</p>
                    <p class="text-muted small">You will need to login again to access the system.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-1"></i>Cancel
                    </button>
                    <a href="../logout.php" class="btn btn-danger">
                        <i class="bi bi-box-arrow-right me-1"></i>Yes, Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Upload Template Modal -->
    <div class="modal fade" id="uploadTemplateModal" tabindex="-1" aria-labelledby="uploadTemplateModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="uploadTemplateModalLabel">
                        <i class="bi bi-upload me-2"></i>Upload Document Template
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="upload_template.php" enctype="multipart/form-data" id="uploadTemplateForm">
                    <div class="modal-body">
                        <?php if ($error && strpos($error, 'upload') !== false): ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Upload Failed:</strong> <?= htmlspecialchars($error) ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal_document_type" class="form-label">Document Type *</label>
                                <select class="form-select" id="modal_document_type" name="document_type" required>
                                    <option value="">Select document type...</option>
                                    <?php foreach ($requiredTypes as $type => $name): ?>
                                        <option value="<?= $type ?>"><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="modal_document_name" class="form-label">Document Name *</label>
                                <input type="text" class="form-control" id="modal_document_name" name="document_name" 
                                       placeholder="e.g., MOA Template 2024" required>
                                <div class="form-text" id="modal_document_name_help">
                                    Enter a descriptive name for this document
                                </div>
                            </div>
                        </div>
                        
                        <div class="row" id="modal_custom_document_fields" style="display: none;">
                            <div class="col-md-12 mb-3">
                                <label for="modal_is_required" class="form-label">Document Requirement</label>
                                <select class="form-select" id="modal_is_required" name="is_required">
                                    <option value="0">Optional Document</option>
                                    <option value="1">Required Document</option>
                                </select>
                                <div class="form-text">Choose if this document is required for students</div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="modal_deadline" class="form-label">Deadline (Optional)</label>
                                <input type="date" class="form-control" id="modal_deadline" name="deadline">
                                <div class="form-text">Leave empty if no deadline</div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="modal_template_file" class="form-label">Template File(s) *</label>
                                <input type="file" class="form-control" id="modal_template_file" name="template_file[]" 
                                       accept=".pdf,.docx,.doc,.txt" multiple required>
                                <div class="form-text">PDF, DOCX, DOC, or TXT files only. Max 10MB per file. You can select multiple files.</div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="modal_description" class="form-label">Instructions (Optional)</label>
                            <textarea class="form-control" id="modal_description" name="description" rows="3" 
                                      placeholder="Provide specific instructions for students on how to complete this document..."></textarea>
                        </div>
                        
                        <!-- File Upload Area -->
                        <div class="upload-area mb-3" id="modal_uploadArea" style="border: 2px dashed #dee2e6; border-radius: 8px; padding: 2rem; text-align: center; cursor: pointer; transition: all 0.3s ease;">
                            <i class="bi bi-cloud-upload" style="font-size: 3rem; color: #6c757d;"></i>
                            <h5 class="mt-3">Drag & Drop Files Here</h5>
                            <p class="text-muted mb-0">or click to select files (multiple files supported)</p>
                        </div>
                        
                        <!-- Selected Files Display -->
                        <div id="modal_selectedFiles" class="mb-3" style="display: none;">
                            <label class="form-label">Selected Files:</label>
                            <div id="modal_filesList" class="list-group"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-success" id="modal_confirmUpload">
                            <i class="bi bi-upload me-1"></i>Upload Template
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
        .upload-area:hover {
            border-color: #0ea539 !important;
            background-color: #f8f9fa;
        }
        
        .upload-area.dragover {
            border-color: #0ea539 !important;
            background-color: #e8f5e8;
        }
    </style>
    
    <script>
    // Auto-dismiss success alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
        const successAlert = document.querySelector('.alert-success.alert-fixed');
        if (successAlert) {
            setTimeout(function() {
                successAlert.classList.add('fade-out');
                setTimeout(function() {
                    successAlert.remove();
                }, 500); // Remove after fade animation completes
            }, 5000); // Auto-dismiss after 5 seconds
        }

        // Handle document type change in modal
        const modalDocumentType = document.getElementById('modal_document_type');
        if (modalDocumentType) {
            modalDocumentType.addEventListener('change', function() {
                const customFields = document.getElementById('modal_custom_document_fields');
                
                if (this.value === 'other') {
                    customFields.style.display = 'block';
                } else {
                    customFields.style.display = 'none';
                }
            });
        }

        // Drag and drop functionality for modal
        const modalUploadArea = document.getElementById('modal_uploadArea');
        const modalFileInput = document.getElementById('modal_template_file');
        const modalSelectedFiles = document.getElementById('modal_selectedFiles');
        const modalFilesList = document.getElementById('modal_filesList');
        
        if (modalUploadArea && modalFileInput) {
            modalUploadArea.addEventListener('click', () => modalFileInput.click());

            modalUploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                e.stopPropagation();
                modalUploadArea.classList.add('dragover');
            });

            modalUploadArea.addEventListener('dragleave', (e) => {
                e.preventDefault();
                e.stopPropagation();
                modalUploadArea.classList.remove('dragover');
            });

            modalUploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                e.stopPropagation();
                modalUploadArea.classList.remove('dragover');
                
                const files = Array.from(e.dataTransfer.files);
                if (files.length > 0) {
                    // Filter valid files
                    const validFiles = files.filter(file => {
                        const ext = file.name.split('.').pop().toLowerCase();
                        return ['pdf', 'docx', 'doc', 'txt'].includes(ext);
                    });
                    
                    if (validFiles.length === 0) {
                        alert('No valid files. Only PDF, DOCX, DOC, and TXT files are allowed.');
                        return;
                    }
                    
                    // Add files to input
                    const dt = new DataTransfer();
                    const currentFiles = Array.from(modalFileInput.files || []);
                    
                    // Combine existing and new files
                    [...currentFiles, ...validFiles].forEach(file => {
                        dt.items.add(file);
                    });
                    
                    modalFileInput.files = dt.files;
                    updateModalFileDisplay();
                }
            });

            modalFileInput.addEventListener('change', () => {
                updateModalFileDisplay();
            });
        }

        function updateModalFileDisplay() {
            const files = Array.from(modalFileInput.files || []);
            
            if (files.length === 0) {
                modalSelectedFiles.style.display = 'none';
                modalUploadArea.innerHTML = `
                    <i class="bi bi-cloud-upload" style="font-size: 3rem; color: #6c757d;"></i>
                    <h5 class="mt-3">Drag & Drop Files Here</h5>
                    <p class="text-muted mb-0">or click to select files (multiple files supported)</p>
                `;
                return;
            }
            
            // Update upload area
            modalUploadArea.innerHTML = `
                <i class="bi bi-file-earmark-check" style="font-size: 3rem; color: #0ea539;"></i>
                <h5 class="mt-3">${files.length} file(s) selected</h5>
                <p class="text-muted">Click to add more files</p>
            `;
            
            // Display file list
            modalFilesList.innerHTML = '';
            let totalSize = 0;
            
            files.forEach((file, index) => {
                totalSize += file.size;
                const fileItem = document.createElement('div');
                fileItem.className = 'list-group-item d-flex justify-content-between align-items-center';
                fileItem.innerHTML = `
                    <div class="d-flex align-items-center">
                        <i class="bi bi-file-earmark me-2" style="font-size: 1.5rem;"></i>
                        <div>
                            <div class="fw-semibold">${file.name}</div>
                            <small class="text-muted">${(file.size / 1024 / 1024).toFixed(2)} MB</small>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeModalFile(${index})">
                        <i class="bi bi-x"></i>
                    </button>
                `;
                modalFilesList.appendChild(fileItem);
            });
            
            // Add total size
            const totalItem = document.createElement('div');
            totalItem.className = 'list-group-item bg-light';
            totalItem.innerHTML = `
                <div class="d-flex justify-content-between">
                    <span class="fw-semibold">Total:</span>
                    <span class="fw-semibold">${files.length} file(s) - ${(totalSize / 1024 / 1024).toFixed(2)} MB</span>
                </div>
            `;
            modalFilesList.appendChild(totalItem);
            
            modalSelectedFiles.style.display = 'block';
        }

        window.removeModalFile = function(index) {
            const files = Array.from(modalFileInput.files || []);
            if (index >= 0 && index < files.length) {
                const dt = new DataTransfer();
                files.forEach((file, i) => {
                    if (i !== index) {
                        dt.items.add(file);
                    }
                });
                modalFileInput.files = dt.files;
                updateModalFileDisplay();
            }
        };

        window.clearModalFile = function() {
            modalFileInput.value = '';
            updateModalFileDisplay();
        };

        // Confirmation handler for modal upload
        const modalConfirmUpload = document.getElementById('modal_confirmUpload');
        if (modalConfirmUpload) {
            modalConfirmUpload.addEventListener('click', function() {
                const files = Array.from(modalFileInput.files || []);
                const documentName = document.getElementById('modal_document_name').value.trim();
                const documentType = modalDocumentType.value;

                if (files.length === 0) {
                    alert('Please select at least one file to upload');
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

                // Validate all files
                for (let i = 0; i < files.length; i++) {
                    const file = files[i];
                    if (file.size > 10 * 1024 * 1024) {
                        alert(`File "${file.name}" exceeds 10MB limit. Please remove it or select a smaller file.`);
                        return;
                    }
                    
                    const ext = file.name.split('.').pop().toLowerCase();
                    if (!['pdf', 'docx', 'doc', 'txt'].includes(ext)) {
                        alert(`File "${file.name}" has an invalid extension. Only PDF, DOCX, DOC, and TXT files are allowed.`);
                        return;
                    }
                }

                // Show loading state
                this.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Uploading...';
                this.disabled = true;

                // Submit the form
                document.getElementById('uploadTemplateForm').submit();
            });
        }

        // Reset modal when closed
        const uploadModal = document.getElementById('uploadTemplateModal');
        if (uploadModal) {
            uploadModal.addEventListener('hidden.bs.modal', function() {
                // Reset form
                document.getElementById('uploadTemplateForm').reset();
                // Reset file display
                updateModalFileDisplay();
                if (document.getElementById('modal_custom_document_fields')) {
                    document.getElementById('modal_custom_document_fields').style.display = 'none';
                }
                if (modalConfirmUpload) {
                    modalConfirmUpload.innerHTML = '<i class="bi bi-upload me-1"></i>Upload Template';
                    modalConfirmUpload.disabled = false;
                }
            });
        }
    });
    </script>
</body>
</html>
