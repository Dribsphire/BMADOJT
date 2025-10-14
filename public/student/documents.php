<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../src/Utils/Database.php';
require_once '../../src/Services/DocumentService.php';
require_once '../../src/Services/OverdueService.php';
require_once '../../src/Middleware/AuthMiddleware.php';
require_once '../../src/Services/AuthenticationService.php';

use App\Services\DocumentService;
use App\Services\OverdueService;
use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;

// Check authentication
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('student')) {
    $authMiddleware->redirectToUnauthorized();
}

// Get user profile
$pdo = App\Utils\Database::getInstance();
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Create User object for compliance check
$user = App\Models\User::fromArray($profile);

// Check compliance gates (but allow access to documents page even if not fully compliant)
// We only redirect if student has no profile at all
$pdo = App\Utils\Database::getInstance();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM student_profiles WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$hasProfile = $stmt->fetchColumn() > 0;

if (!$hasProfile) {
    // Student has no profile at all, redirect to profile setup
    header('Location: profile.php');
    exit;
}

// Get document service
$documentService = new DocumentService();
$overdueService = new OverdueService();

// Get student's section ID
$studentSectionId = $profile['section_id'];

// Check if student has been assigned to a section
if ($studentSectionId === null) {
    $error = "You have not been assigned to a section yet. Please contact your instructor or administrator.";
    $templates = [];
    $customDocuments = [];
    $overdueDocuments = [];
} else {
    // Get overdue documents for this student
    $overdueDocuments = $overdueService->getOverdueDocumentsForStudent($_SESSION['user_id']);

    // Get template documents for student's section (includes pre-loaded templates and instructor-uploaded templates for this section)
    $templates = $documentService->getDocumentsForSection($studentSectionId);

    // Get custom documents for student's section
    $customDocuments = $documentService->getCustomDocumentsForSection($studentSectionId);
}

// Get required document types
$requiredTypes = $documentService->getRequiredDocumentTypes();

// Check which documents student has submitted
$stmt = $pdo->prepare("
    SELECT sd.*, d.document_name, d.document_type, d.file_path as template_path
    FROM student_documents sd
    JOIN documents d ON sd.document_id = d.id
    WHERE sd.student_id = ?
    ORDER BY d.document_type
");
$stmt->execute([$_SESSION['user_id']]);
$studentDocuments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create a map of student documents by type
$studentDocsByType = [];
foreach ($studentDocuments as $doc) {
    $studentDocsByType[$doc['document_type']] = $doc;
}

// Calculate progress
$totalRequired = count($requiredTypes);
$approvedCount = 0;
$submittedCount = 0;

foreach ($requiredTypes as $type => $name) {
    if (isset($studentDocsByType[$type])) {
        $submittedCount++;
        if ($studentDocsByType[$type]['status'] === 'approved') {
            $approvedCount++;
        }
    }
}

$progressPercentage = $totalRequired > 0 ? ($approvedCount / $totalRequired) * 100 : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documents | OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
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
        
        .document-card {
            transition: transform 0.2s ease-in-out;
        }
        
        .document-card:hover {
            transform: translateY(-2px);
        }
        
        .status-badge {
            font-size: 0.8rem;
        }
        
        .progress-ring {
            width: 80px;
            height: 80px;
        }
        
        .progress-ring-circle {
            stroke: var(--chmsu-green);
            stroke-width: 4;
            fill: transparent;
            stroke-linecap: round;
            transform: rotate(-90deg);
            transform-origin: 50% 50%;
        }
        
        .progress-text {
            font-size: 1.2rem;
            font-weight: 600;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Sidebar -->
    <?php include 'student-sidebar.php'; ?>
    
    <!-- Main Content -->
    <main>
        <?php if (isset($error)): ?>
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Required Documents</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-download me-1"></i>Download All Templates
                    </button>
                </div>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (isset($_GET['error'])): ?>
            <?php
            $errorMessages = [
                'invalid_document' => 'Invalid document ID. Please select a valid document to submit.',
                'document_not_found' => 'Document not found. The document may have been removed.',
                'access_denied' => 'Access denied. You do not have permission to access this document.',
                'custom_document_submitted' => 'Document submitted successfully! Your instructor will review it.',
            ];
            $errorType = $_GET['error'];
            $errorMessage = $errorMessages[$errorType] ?? 'An unknown error occurred.';
            $isSuccess = $errorType === 'custom_document_submitted';
            ?>
            <div class="alert alert-<?= $isSuccess ? 'success' : 'danger' ?> alert-dismissible fade show" role="alert">
                <i class="bi bi-<?= $isSuccess ? 'check-circle' : 'exclamation-triangle' ?> me-2"></i>
                <strong><?= $isSuccess ? 'Success!' : 'Error:' ?></strong> <?= htmlspecialchars($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Progress Overview -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-file-text me-2"></i>Document Progress
                        </h5>
                        <div class="row align-items-center">
                            <div class="col-md-4 text-center">
                                <div class="progress-ring mx-auto">
                                    <svg class="progress-ring" viewBox="0 0 36 36">
                                        <path class="progress-ring-circle"
                                              stroke-dasharray="<?= $progressPercentage ?>, 100"
                                              d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"/>
                                    </svg>
                                    <div class="position-absolute top-50 start-50 translate-middle">
                                        <span class="progress-text text-success"><?= $approvedCount ?>/<?= $totalRequired ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-8">
                                <div class="row">
                                    <div class="col-4">
                                        <div class="text-center">
                                            <h3 class="text-success mb-0"><?= $approvedCount ?></h3>
                                            <small class="text-muted">Approved</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-center">
                                            <h3 class="text-warning mb-0"><?= $submittedCount - $approvedCount ?></h3>
                                            <small class="text-muted">Under Review</small>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-center">
                                            <h3 class="text-danger mb-0"><?= $totalRequired - $submittedCount ?></h3>
                                            <small class="text-muted">Not Submitted</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-body text-center">
                        <h5 class="card-title">Compliance Status</h5>
                        <?php if ($approvedCount >= $totalRequired): ?>
                            <div class="text-success">
                                <i class="bi bi-check-circle-fill" style="font-size: 3rem;"></i>
                                <h4 class="mt-2">Complete!</h4>
                                <p class="mb-0">All documents approved. You can now access attendance features.</p>
                            </div>
                        <?php else: ?>
                            <div class="text-warning">
                                <i class="bi bi-exclamation-triangle-fill" style="font-size: 3rem;"></i>
                                <h4 class="mt-2">Incomplete</h4>
                                <p class="mb-0"><?= $totalRequired - $approvedCount ?> documents still needed.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <?php if (!empty($customDocuments)): ?>
        <!-- Custom Documents Section -->
        <div class="mt-5">
            <h4 class="mb-3">
                <i class="bi bi-file-earmark-plus me-2"></i>Additional Documents
                <span class="badge bg-info"><?= count($customDocuments) ?></span>
            </h4>
            <p class="text-muted mb-4">Your instructor has uploaded additional documents for your section.</p>
            
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Document Name</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Deadline</th>
                            <th>Actions</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($customDocuments as $customDoc): ?>
                        <?php
                        // Check if student has submitted this custom document
                        $stmt = $pdo->prepare("
                            SELECT sd.*, d.document_name, d.document_type, d.file_path as template_path
                            FROM student_documents sd
                            JOIN documents d ON sd.document_id = d.id
                            WHERE sd.student_id = ? AND sd.document_id = ?
                        ");
                        $stmt->execute([$_SESSION['user_id'], $customDoc->id]);
                        $customSubmission = $stmt->fetch(PDO::FETCH_ASSOC);
                        
                        // Determine status
                        $status = 'not_submitted';
                        $statusClass = 'secondary';
                        $statusText = 'Not Submitted';
                        
                        // Check if custom document is overdue
                        $isOverdue = false;
                        $daysOverdue = 0;
                        if ($customDoc->deadline) {
                            $deadline = strtotime($customDoc->deadline);
                            $today = time();
                            if ($deadline < $today) {
                                $isOverdue = true;
                                $daysOverdue = floor(($today - $deadline) / (24 * 60 * 60));
                            }
                        }
                        
                        if ($customSubmission) {
                            switch ($customSubmission['status']) {
                                case 'approved':
                                    $status = 'approved';
                                    $statusClass = 'success';
                                    $statusText = 'Approved';
                                    break;
                                case 'pending':
                                    $status = 'pending';
                                    $statusClass = 'warning';
                                    $statusText = 'Pending Review';
                                    break;
                                case 'revision_required':
                                    $status = 'revision_required';
                                    $statusClass = 'info';
                                    $statusText = 'Revision Required';
                                    break;
                                case 'rejected':
                                    $status = 'rejected';
                                    $statusClass = 'danger';
                                    $statusText = 'Rejected';
                                    break;
                            }
                        }
                        
                        // Override status if custom document is overdue and not approved
                        if ($isOverdue && $status !== 'approved') {
                            $status = 'overdue';
                            $statusClass = 'danger';
                            $statusText = 'Overdue (' . $daysOverdue . ' days)';
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($customDoc->document_name) ?></strong>
                                <br><small class="text-muted">
                                    <i class="bi bi-person me-1"></i>Uploaded by: <?= htmlspecialchars($customDoc->uploaded_by_name) ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-<?= $customDoc->is_required ? 'danger' : 'secondary' ?>">
                                    <?= $customDoc->is_required ? 'Required' : 'Optional' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                                <?php if ($customSubmission && $customSubmission['instructor_feedback']): ?>
                                    <br><small class="text-muted mt-1 d-block">
                                        <i class="bi bi-chat-left-text me-1"></i>Feedback available
                                    </small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($customDoc->deadline): ?>
                                    <span class="text-<?= strtotime($customDoc->deadline) < time() ? 'danger' : 'success' ?>">
                                        <i class="bi bi-calendar me-1"></i><?= date('M d, Y', strtotime($customDoc->deadline)) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">No deadline</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group-vertical btn-group-sm" role="group">
                                    <a href="serve_file.php?id=<?= $customDoc->id ?>" 
                                       class="btn btn-outline-primary btn-sm mb-1">
                                        <i class="bi bi-download me-1"></i>Download Template
                                    </a>
                                    
                                    <?php if ($customSubmission): ?>
                                        <a href="view_submission.php?id=<?= $customSubmission['id'] ?>" 
                                           class="btn btn-outline-info btn-sm mb-1">
                                            <i class="bi bi-eye me-1"></i>View Submission
                                        </a>
                                        
                                        <?php if ($customSubmission['status'] === 'revision_required' || $customSubmission['status'] === 'rejected'): ?>
                                            <a href="resubmit_document.php?id=<?= $customSubmission['id'] ?>" 
                                               class="btn btn-warning btn-sm mb-1">
                                                <i class="bi bi-arrow-clockwise me-1"></i>Resubmit
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="submit_custom_document.php?id=<?= $customDoc->id ?>" 
                                           class="btn btn-success btn-sm">
                                            <i class="bi bi-upload me-1"></i>Submit Document
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($customDoc->description): ?>
                                    <small class="text-muted"><?= htmlspecialchars($customDoc->description) ?></small>
                                <?php else: ?>
                                    <span class="text-muted">No description</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Required Documents Table -->
        <div class="table-responsive">
            <table class="table table-striped table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>Document Type</th>
                        <th>Status</th>
                        <th>Template</th>
                        <th>Actions</th>
                        <th>Feedback</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($requiredTypes as $type => $name): ?>
                        <?php 
                        $studentDoc = $studentDocsByType[$type] ?? null;
                        
                        // Get ALL templates for this document type
                        $templatesForType = [];
                        foreach ($templates as $t) {
                            if ($t->document_type === $type) {
                                $templatesForType[] = $t;
                            }
                        }
                        
                        // If no templates found, show a row for this type
                        if (empty($templatesForType)) {
                            $templatesForType = [null];
                        }
                        
                        // Show a row for each template (or one row if no templates)
                        foreach ($templatesForType as $templateIndex => $template):
                        
                        $status = 'not_started';
                        $statusClass = 'secondary';
                        $statusText = 'Not Started';
                        
                        // Check if document is overdue
                        $isOverdue = false;
                        $daysOverdue = 0;
                        if ($template && $template->deadline) {
                            $deadline = strtotime($template->deadline);
                            $today = time();
                            if ($deadline < $today) {
                                $isOverdue = true;
                                $daysOverdue = floor(($today - $deadline) / (24 * 60 * 60));
                            }
                        }
                        
                        if ($studentDoc) {
                            switch ($studentDoc['status']) {
                                case 'approved':
                                    $status = 'approved';
                                    $statusClass = 'success';
                                    $statusText = 'Approved';
                                    break;
                                case 'pending':
                                    $status = 'pending';
                                    $statusClass = 'warning';
                                    $statusText = 'Under Review';
                                    break;
                                case 'revision_required':
                                    $status = 'revision_required';
                                    $statusClass = 'danger';
                                    $statusText = 'Needs Revision';
                                    break;
                                case 'rejected':
                                    $status = 'rejected';
                                    $statusClass = 'danger';
                                    $statusText = 'Rejected';
                                    break;
                            }
                        }
                        
                        // Override status if document is overdue and not approved
                        if ($isOverdue && $status !== 'approved') {
                            $status = 'overdue';
                            $statusClass = 'danger';
                            $statusText = 'Overdue (' . $daysOverdue . ' days)';
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($name) ?></strong>
                                <br><small class="text-muted"><?= $type ?></small>
                                <?php if (count($templatesForType) > 1): ?>
                                    <br><span class="badge bg-info"><?= count($templatesForType) ?> templates available</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-<?= $statusClass ?>"><?= $statusText ?></span>
                            </td>
                            <td>
                                <?php if ($template): ?>
                                    <div class="d-flex align-items-center">
                                        <i class="bi bi-download me-2 text-primary"></i>
                                        <div>
                                            <strong><?= htmlspecialchars($template->document_name) ?></strong>
                                            <br><small class="text-muted">
                                                <?= $template->uploaded_by != 1 ? 'Instructor Upload' : 'Pre-loaded Template' ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">
                                        <i class="bi bi-file-earmark me-1"></i>No template available
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="btn-group-vertical btn-group-sm" role="group">
                                    <?php if ($template): ?>
                                        <a href="serve_file.php?id=<?= $template->id ?>" 
                                           class="btn btn-outline-primary btn-sm mb-1">
                                            <i class="bi bi-download me-1"></i>Download Template
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($studentDoc): ?>
                                        <a href="view_submission.php?id=<?= $studentDoc['id'] ?>" 
                                           class="btn btn-outline-info btn-sm mb-1">
                                            <i class="bi bi-eye me-1"></i>View Submission
                                        </a>
                                        
                                        <?php if ($status === 'revision_required' || $status === 'rejected'): ?>
                                            <a href="resubmit_document.php?id=<?= $studentDoc['id'] ?>" 
                                               class="btn btn-warning btn-sm mb-1">
                                                <i class="bi bi-arrow-clockwise me-1"></i>Resubmit
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <a href="submit_document.php?type=<?= $type ?>" 
                                           class="btn btn-success btn-sm">
                                            <i class="bi bi-upload me-1"></i>Submit Document
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <?php if ($studentDoc && $studentDoc['instructor_feedback']): ?>
                                    <div class="alert alert-info alert-sm mb-0">
                                        <small>
                                            <strong>Instructor Feedback:</strong><br>
                                            <?= htmlspecialchars($studentDoc['instructor_feedback']) ?>
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <span class="text-muted">No feedback</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    // Save and restore scroll position
    (function() {
        const SCROLL_POSITION_KEY = 'documents_scroll_position';
        
        // Save scroll position before page unload
        window.addEventListener('beforeunload', function() {
            sessionStorage.setItem(SCROLL_POSITION_KEY, window.pageYOffset.toString());
        });
        
        // Restore scroll position after page load
        window.addEventListener('load', function() {
            const savedPosition = sessionStorage.getItem(SCROLL_POSITION_KEY);
            if (savedPosition) {
                // Small delay to ensure page is fully rendered
                setTimeout(function() {
                    window.scrollTo(0, parseInt(savedPosition));
                    // Clear the saved position after restoring
                    sessionStorage.removeItem(SCROLL_POSITION_KEY);
                }, 100);
            }
        });
        
        // Also save scroll position on scroll events (throttled)
        let scrollTimeout;
        window.addEventListener('scroll', function() {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(function() {
                sessionStorage.setItem(SCROLL_POSITION_KEY, window.pageYOffset.toString());
            }, 150);
        });
    })();
    </script>
</body>
</html>
