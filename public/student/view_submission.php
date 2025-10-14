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

// Get submission history for this document type
$stmt = $pdo->prepare("
    SELECT sd.*
    FROM student_documents sd
    WHERE sd.student_id = ? AND sd.document_id = ?
    ORDER BY sd.submitted_at DESC
");
$stmt->execute([$_SESSION['user_id'], $submission['document_id']]);
$submissionHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Submission | OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --chmsu-green: #0ea539;
        }
        
        .status-badge {
            font-size: 0.9rem;
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
        .timeline-marker.submitted { background-color: #17a2b8; }
    </style>
</head>
<body class="bg-light">
    <!-- Sidebar -->
    <?php include 'student-sidebar.php'; ?>
    
    <!-- Main Content -->
    <main>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Submission Details</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <a href="documents.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Documents
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
            <!-- Current Submission Details -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-file-earmark-text me-2"></i><?= htmlspecialchars($submission['document_name']) ?>
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Document Type:</strong>
                                <span class="text-muted"><?= ucfirst(str_replace('_', ' ', $submission['document_type'])) ?></span>
                            </div>
                            <div class="col-md-6">
                                <strong>Status:</strong>
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
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Submitted:</strong>
                                <span class="text-muted"><?= date('M j, Y g:i A', strtotime($submission['submitted_at'])) ?></span>
                            </div>
                            <?php if ($submission['reviewed_at']): ?>
                            <div class="col-md-6">
                                <strong>Reviewed:</strong>
                                <span class="text-muted"><?= date('M j, Y g:i A', strtotime($submission['reviewed_at'])) ?></span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($submission['instructor_feedback']): ?>
                        <div class="mb-3">
                            <strong>Instructor Feedback:</strong>
                            <div class="alert alert-info mt-2">
                                <?= nl2br(htmlspecialchars($submission['instructor_feedback'])) ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                            <?php if (file_exists($submission['submission_file_path'])): ?>
                            <a href="serve_file.php?path=<?= urlencode($submission['submission_file_path']) ?>" 
                               class="btn btn-primary">
                                <i class="bi bi-download me-1"></i>Download Submission
                            </a>
                            <?php endif; ?>
                            
                            <?php if ($submission['status'] === 'revision_required' || $submission['status'] === 'rejected'): ?>
                            <a href="resubmit_document.php?id=<?= $submission['id'] ?>" 
                               class="btn btn-warning">
                                <i class="bi bi-arrow-clockwise me-1"></i>Resubmit
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Submission History -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="bi bi-clock-history me-2"></i>Submission History
                        </h6>
                    </div>
                    <div class="card-body">
                        <?php if (count($submissionHistory) > 1): ?>
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
                        <?php else: ?>
                            <p class="text-muted text-center">No previous submissions</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
