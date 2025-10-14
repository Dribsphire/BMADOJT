<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../src/Utils/Database.php';
require_once '../../src/Controllers/InstructorDocumentController.php';
require_once '../../src/Middleware/AuthMiddleware.php';

use App\Controllers\InstructorDocumentController;
use App\Middleware\AuthMiddleware;

// Check authentication
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('instructor')) {
    $authMiddleware->redirectToUnauthorized();
}

$controller = new InstructorDocumentController();

// Get submission ID
$submissionId = $_GET['id'] ?? 0;

if (!$submissionId) {
    header('Location: review_documents.php?error=invalid_submission');
    exit;
}

// Debug: Check session and instructor data
error_log("Document Review Debug - Submission ID: " . $submissionId);
error_log("Document Review Debug - Session User ID: " . ($_SESSION['user_id'] ?? 'NOT SET'));
error_log("Document Review Debug - Session Role: " . ($_SESSION['role'] ?? 'NOT SET'));

// Get submission details
$pdo = App\Utils\Database::getInstance();
$stmt = $pdo->prepare("
    SELECT sd.*, d.document_name, d.document_type, d.file_path as template_path,
           u.full_name as student_name, u.email as student_email, u.section_id
    FROM student_documents sd
    JOIN documents d ON sd.document_id = d.id
    JOIN users u ON sd.student_id = u.id
    WHERE sd.id = ? AND u.section_id = (SELECT section_id FROM users WHERE id = ?)
");
$stmt->execute([$submissionId, $_SESSION['user_id']]);
$submission = $stmt->fetch(PDO::FETCH_ASSOC);

// Debug: Log query result
error_log("Document Review Debug - Query result: " . ($submission ? 'FOUND' : 'NOT FOUND'));
if ($submission) {
    error_log("Document Review Debug - Student: " . $submission['student_name']);
    error_log("Document Review Debug - Student Section: " . $submission['section_id']);
} else {
    error_log("Document Review Debug - No submission found for ID: " . $submissionId);
}

if (!$submission) {
    header('Location: review_documents.php?error=submission_not_found');
    exit;
}

// Handle review actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $feedback = $_POST['feedback'] ?? '';
    
    try {
        switch ($action) {
            case 'approve':
                $result = $controller->approveDocument($submissionId, $_SESSION['user_id'], $feedback);
                if ($result['success']) {
                    header('Location: review_documents.php?success=document_approved');
                    exit;
                } else {
                    $errorMessage = $result['message'];
                }
                break;
                
            case 'request_revision':
                if (empty($feedback)) {
                    $errorMessage = 'Feedback is required when requesting revision';
                } else {
                    $result = $controller->requestRevision($submissionId, $_SESSION['user_id'], $feedback);
                    if ($result['success']) {
                        header('Location: review_documents.php?success=revision_requested');
                        exit;
                    } else {
                        $errorMessage = $result['message'];
                    }
                }
                break;
                
            case 'reject':
                if (empty($feedback)) {
                    $errorMessage = 'Feedback is required when rejecting document';
                } else {
                    $result = $controller->rejectDocument($submissionId, $_SESSION['user_id'], $feedback);
                    if ($result['success']) {
                        header('Location: review_documents.php?success=document_rejected');
                        exit;
                    } else {
                        $errorMessage = $result['message'];
                    }
                }
                break;
        }
    } catch (Exception $e) {
        $errorMessage = $e->getMessage();
    }
}

// Get submission history for this document
$stmt = $pdo->prepare("
    SELECT sd.*
    FROM student_documents sd
    WHERE sd.student_id = ? AND sd.document_id = ?
    ORDER BY sd.submitted_at DESC
");
$stmt->execute([$submission['student_id'], $submission['document_id']]);
$submissionHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get messages
$success = $_GET['success'] ?? '';
$error = $errorMessage ?? $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Document | OJT Route</title>
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
        
        .document-viewer {
            height: 600px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .student-info {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
        }
        
        .status-badge {
            font-size: 0.9rem;
        }
        
        .action-buttons {
            position: sticky;
            top: 20px;
        }
        
        .feedback-box {
            background-color: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 1rem;
            margin: 1rem 0;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            margin-bottom: 1.5rem;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0.5rem;
            top: 0;
            bottom: -1.5rem;
            width: 2px;
            background-color: #dee2e6;
        }
        
        .timeline-item:last-child::before {
            display: none;
        }
        
        .timeline-marker {
            position: absolute;
            left: 0;
            top: 0.25rem;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #dee2e6;
        }
        
        .timeline-marker.approved { background-color: #28a745; }
        .timeline-marker.pending { background-color: #ffc107; }
        .timeline-marker.revision_required { background-color: #dc3545; }
        .timeline-marker.rejected { background-color: #dc3545; }
    </style>
</head>
<body class="bg-light">
    <!-- Sidebar -->
    <?php include 'teacher-sidebar.php'; ?>
    
    <!-- Main Content -->
    <main>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Review Document</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <a href="review_documents.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Review List
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
            <!-- Document Viewer -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-file-earmark-text me-2"></i><?= htmlspecialchars($submission['document_name']) ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="document-viewer">
                            <?php 
                            // Check if file exists
                            $filePath = __DIR__ . '/../../uploads/student_documents/' . basename($submission['submission_file_path']);
                            $fileExists = file_exists($filePath);
                            
                            if ($fileExists): ?>
                                <?php
                                $fileExtension = strtolower(pathinfo($submission['submission_file_path'], PATHINFO_EXTENSION));
                                $viewUrl = '../view_document.php?id=' . $submission['id'];
                                
                                if ($fileExtension === 'pdf'): ?>
                                    <iframe src="<?= htmlspecialchars($viewUrl) ?>" 
                                            width="100%" height="100%" style="border: none;">
                                        <p>Your browser does not support PDFs. 
                                           <a href="<?= htmlspecialchars($viewUrl) ?>" target="_blank">Download the PDF</a>.
                                        </p>
                                    </iframe>
                                <?php else: ?>
                                    <div class="d-flex align-items-center justify-content-center h-100">
                                        <div class="text-center">
                                            <i class="bi bi-file-earmark" style="font-size: 4rem; color: #6c757d;"></i>
                                            <h5 class="mt-3">Document Preview Not Available</h5>
                                            <p class="text-muted">This file type cannot be previewed in the browser.</p>
                                            <a href="<?= htmlspecialchars($viewUrl) ?>" 
                                               class="btn btn-primary" download>
                                                <i class="bi bi-download me-1"></i>Download Document
                                            </a>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="d-flex align-items-center justify-content-center h-100">
                                    <div class="text-center">
                                        <i class="bi bi-exclamation-triangle" style="font-size: 4rem; color: #dc3545;"></i>
                                        <h5 class="mt-3">File Not Found</h5>
                                        <p class="text-muted">The submitted document file could not be located.</p>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mt-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>Document Type:</strong>
                                    <span class="text-muted"><?= ucfirst(str_replace('_', ' ', $submission['document_type'])) ?></span>
                                </div>
                                <div class="col-md-6">
                                    <strong>Submitted:</strong>
                                    <span class="text-muted"><?= date('M j, Y g:i A', strtotime($submission['submitted_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Review Actions -->
            <div class="col-md-4">
                <!-- Student Information -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-person me-2"></i>Student Information
                        </h6>
                    </div>
                    <div class="card-body student-info">
                        <h6><?= htmlspecialchars($submission['student_name']) ?></h6>
                        <p class="text-muted mb-2"><?= htmlspecialchars($submission['student_email']) ?></p>
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-primary">Section <?= $submission['section_id'] ?></span>
                            <?php
                            $statusClass = match($submission['status']) {
                                'approved' => 'success',
                                'pending' => 'warning',
                                'revision_required' => 'danger',
                                'rejected' => 'danger',
                                default => 'secondary'
                            };
                            $statusText = match($submission['status']) {
                                'approved' => 'Approved',
                                'pending' => 'Under Review',
                                'revision_required' => 'Needs Revision',
                                'rejected' => 'Rejected',
                                default => ucfirst($submission['status'])
                            };
                            ?>
                            <span class="badge bg-<?= $statusClass ?> status-badge"><?= $statusText ?></span>
                        </div>
                    </div>
                </div>
                
                <!-- Review Actions -->
                <?php if ($submission['status'] === 'pending'): ?>
                <div class="card action-buttons">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-clipboard-check me-2"></i>Review Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="reviewForm">
                            <div class="mb-3">
                                <label for="feedback" class="form-label">Feedback/Comments</label>
                                <textarea class="form-control" id="feedback" name="feedback" rows="4" 
                                          placeholder="Provide feedback for the student..."><?= htmlspecialchars($submission['instructor_feedback'] ?? '') ?></textarea>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" name="action" value="approve" class="btn btn-success">
                                    <i class="bi bi-check-circle me-1"></i>Approve Document
                                </button>
                                <button type="submit" name="action" value="request_revision" class="btn btn-warning">
                                    <i class="bi bi-arrow-clockwise me-1"></i>Request Revision
                                </button>
                                <button type="submit" name="action" value="reject" class="btn btn-danger">
                                    <i class="bi bi-x-circle me-1"></i>Reject Document
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-info-circle me-2"></i>Review Status
                        </h6>
                    </div>
                    <div class="card-body">
                        <p class="text-muted">This document has already been reviewed.</p>
                        <?php if ($submission['instructor_feedback']): ?>
                        <div class="feedback-box">
                            <h6 class="text-danger mb-2">Previous Feedback:</h6>
                            <p class="mb-0"><?= nl2br(htmlspecialchars($submission['instructor_feedback'])) ?></p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Submission History -->
                <?php if (count($submissionHistory) > 1): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-clock-history me-2"></i>Submission History
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <?php foreach ($submissionHistory as $index => $history): ?>
                            <div class="timeline-item">
                                <div class="timeline-marker <?= $history['status'] ?>"></div>
                                <div class="timeline-content">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <strong><?= ucfirst(str_replace('_', ' ', $history['status'])) ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <?= date('M j, Y g:i A', strtotime($history['submitted_at'])) ?>
                                            </small>
                                        </div>
                                        <?php if ($index === 0): ?>
                                        <span class="badge bg-primary">Current</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($history['instructor_feedback']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">Feedback:</small>
                                        <div class="alert alert-sm alert-light mt-1">
                                            <?= htmlspecialchars($history['instructor_feedback']) ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.getElementById('reviewForm').addEventListener('submit', function(e) {
            const action = e.submitter.value;
            const feedback = document.getElementById('feedback').value.trim();
            
            if ((action === 'request_revision' || action === 'reject') && !feedback) {
                e.preventDefault();
                alert('Feedback is required when requesting revision or rejecting a document.');
                document.getElementById('feedback').focus();
                return false;
            }
            
            if (action === 'approve') {
                if (!confirm('Are you sure you want to approve this document?')) {
                    e.preventDefault();
                    return false;
                }
            } else if (action === 'request_revision') {
                if (!confirm('Are you sure you want to request revision for this document?')) {
                    e.preventDefault();
                    return false;
                }
            } else if (action === 'reject') {
                if (!confirm('Are you sure you want to reject this document?')) {
                    e.preventDefault();
                    return false;
                }
            }
        });
    </script>
</body>
</html>
