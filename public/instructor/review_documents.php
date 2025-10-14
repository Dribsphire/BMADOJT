<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../src/Utils/Database.php';
require_once '../../src/Controllers/InstructorDocumentController.php';
require_once '../../src/Services/OverdueService.php';
require_once '../../src/Middleware/AuthMiddleware.php';

use App\Controllers\InstructorDocumentController;
use App\Services\OverdueService;
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
$overdueService = new OverdueService();

// Get filter parameters
$studentFilter = $_GET['student'] ?? '';
$documentTypeFilter = $_GET['document_type'] ?? '';
$statusFilter = $_GET['status'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$sortBy = $_GET['sort_by'] ?? 'submitted_at';
$sortOrder = $_GET['sort_order'] ?? 'DESC';

// Get instructor's section
$pdo = App\Utils\Database::getInstance();
$stmt = $pdo->prepare("SELECT section_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$instructor = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$instructor || !$instructor['section_id']) {
    header('Location: no-section.php');
    exit;
}

// Get submissions for review
$submissions = $controller->getSubmissionsForReview(
    $instructor['section_id'],
    $studentFilter,
    $documentTypeFilter,
    $statusFilter,
    $dateFrom,
    $dateTo,
    $sortBy,
    $sortOrder
);

// Get statistics
$stats = $controller->getReviewStatistics($instructor['section_id']);

// Get messages
$success = $_GET['success'] ?? '';
$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Documents | OJT Route</title>
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
        
        .submission-row {
            transition: background-color 0.2s ease-in-out;
        }
        
        .submission-row:hover {
            background-color: #f8f9fa;
        }
        
        .submission-row.pending {
            border-left: 4px solid #ffc107;
        }
        
        .submission-row.approved {
            border-left: 4px solid #28a745;
        }
        
        .submission-row.revision_required {
            border-left: 4px solid #dc3545;
        }
        
        .submission-row.rejected {
            border-left: 4px solid #dc3545;
        }
        
        .submission-checkbox:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .submission-row.approved .submission-checkbox:disabled {
            background-color: #e9ecef;
        }
        
        .status-badge {
            font-size: 0.8rem;
        }
        
        .filter-card {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
        }
        
        .stats-card {
            background: var(--chmsu-green);
            color: white;
        }
        small{
            color: white;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Sidebar -->
    <?php include 'teacher-sidebar.php'; ?>
    
    <!-- Main Content -->
    <main>
        <?php include 'navigation-header.php'; ?>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Student Requirements</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-sm btn-success" id="bulkApproveBtn" disabled>
                        <i class="bi bi-check-circle me-1"></i>Bulk Approve
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="clearFilters()">
                        <i class="bi bi-arrow-clockwise me-1"></i>Clear Filters
                    </button>
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


        <!-- Filters -->
        <div class="card filter-card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <label for="student" class="form-label">Student</label>
                        <input type="text" class="form-control" id="student" name="student" style="font-size: 13px;"
                               value="<?= htmlspecialchars($studentFilter) ?>" placeholder="Search by student name">
                    </div>
                    <div class="col-md-2">
                        <label for="document_type" class="form-label" style="font-size: 13px;">Type</label>
                        <select class="form-select" id="document_type" name="document_type" style="font-size: 13px;">
                            <option value="">All Types</option>
                            <option value="moa" <?= $documentTypeFilter === 'moa' ? 'selected' : '' ?>>MOA</option>
                            <option value="endorsement" <?= $documentTypeFilter === 'endorsement' ? 'selected' : '' ?>>Endorsement</option>
                            <option value="parental_consent" <?= $documentTypeFilter === 'parental_consent' ? 'selected' : '' ?>>Parental Consent</option>
                            <option value="misdemeanor_penalty" <?= $documentTypeFilter === 'misdemeanor_penalty' ? 'selected' : '' ?>>Misdemeanor Penalty</option>
                            <option value="ojt_plan" <?= $documentTypeFilter === 'ojt_plan' ? 'selected' : '' ?>>OJT Plan</option>
                            <option value="notarized_consent" <?= $documentTypeFilter === 'notarized_consent' ? 'selected' : '' ?>>Notarized Consent</option>
                            <option value="pledge" <?= $documentTypeFilter === 'pledge' ? 'selected' : '' ?>>Pledge</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="status" class="form-label" style="font-size: 13px;">Status</label>
                        <select class="form-select" id="status" name="status" style="font-size: 13px;">
                            <option value="">All Status</option>
                            <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                            <option value="revision_required" <?= $statusFilter === 'revision_required' ? 'selected' : '' ?>>Needs Revision</option>
                            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label for="sort_by" class="form-label" style="font-size: 13px;">Sort</label>
                        <select class="form-select" id="sort_by" name="sort_by" style="font-size: 13px;">
                            <option value="submitted_at" <?= $sortBy === 'submitted_at' ? 'selected' : '' ?>>Date</option>
                            <option value="student_name" <?= $sortBy === 'student_name' ? 'selected' : '' ?>>Student</option>
                            <option value="document_type" <?= $sortBy === 'document_type' ? 'selected' : '' ?>>Type</option>
                            <option value="status" <?= $sortBy === 'status' ? 'selected' : '' ?>>Status</option>
                        </select>
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100" style="background-color: var(--chmsu-green); border-color: var(--chmsu-green); font-size: 13px;">
                            <i class="bi bi-search me-1" style="color: white;"></i>
                        </button>
                    </div>
                    <div class="col-md-1">
                        <a href="review_documents.php" class="btn btn-outline-secondary w-100" style="font-size: 13px;">
                            <i class="bi bi-x-circle me-1"></i>
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Submissions List -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-ul me-2"></i>Document Submissions
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($submissions)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 3rem; color: #6c757d;"></i>
                        <h5 class="mt-3">No submissions found</h5>
                        <p class="text-muted">No student document submissions match your current filters.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="5%">
                                        <input type="checkbox" id="selectAll" class="form-check-input">
                                    </th>
                                    <th width="20%">Student</th>
                                    <th width="15%">Document Type</th>
                                    <th width="12%">Status</th>
                                    <th width="15%">Submitted</th>
                                    <th width="20%">Feedback</th>
                                    <th width="13%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($submissions as $submission): ?>
                                    <?php
                                    // Check if document is overdue
                                    $isOverdue = false;
                                    $daysOverdue = 0;
                                    if (isset($submission['deadline']) && $submission['deadline']) {
                                        $deadline = strtotime($submission['deadline']);
                                        $today = time();
                                        if ($deadline < $today && $submission['status'] !== 'approved') {
                                            $isOverdue = true;
                                            $daysOverdue = floor(($today - $deadline) / (24 * 60 * 60));
                                        }
                                    }
                                    
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
                                    
                                    // Override status if overdue
                                    if ($isOverdue) {
                                        $statusClass = 'danger';
                                        $statusText = 'Overdue (' . $daysOverdue . ' days)';
                                    }
                                    ?>
                                    <tr class="submission-row <?= $submission['status'] ?> <?= $isOverdue ? 'table-danger' : '' ?>">
                                        <td>
                                            <input class="form-check-input submission-checkbox" type="checkbox" 
                                                   value="<?= $submission['id'] ?>" id="submission_<?= $submission['id'] ?>"
                                                   <?= $submission['status'] === 'approved' ? 'disabled' : '' ?>>
                                        </td>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($submission['student_name']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($submission['student_email']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?= ucfirst(str_replace('_', ' ', $submission['document_type'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $statusClass ?> status-badge"><?= $statusText ?></span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <?= date('M j, Y', strtotime($submission['submitted_at'])) ?><br>
                                                <?= date('g:i A', strtotime($submission['submitted_at'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <?php if ($submission['instructor_feedback']): ?>
                                                <small class="text-muted">
                                                    <?= htmlspecialchars(substr($submission['instructor_feedback'], 0, 50)) ?><?= strlen($submission['instructor_feedback']) > 50 ? '...' : '' ?>
                                                </small>
                                            <?php else: ?>
                                                <small class="text-muted">No feedback</small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="document_review.php?id=<?= $submission['id'] ?>" 
                                                   class="btn btn-outline-primary btn-sm" title="Review Document">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                
                                                <?php if ($submission['status'] === 'pending'): ?>
                                                    <button type="button" class="btn btn-success btn-sm" 
                                                            onclick="quickApprove(<?= $submission['id'] ?>)" title="Quick Approve">
                                                        <i class="bi bi-check"></i>
                                                    </button>
                                                    <button type="button" class="btn btn-warning btn-sm" 
                                                            onclick="requestRevision(<?= $submission['id'] ?>)" title="Request Revision">
                                                        <i class="bi bi-arrow-clockwise"></i>
                                                    </button>
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
    <script>
        // Bulk selection handling
        const checkboxes = document.querySelectorAll('.submission-checkbox');
        const selectAllCheckbox = document.getElementById('selectAll');
        const bulkApproveBtn = document.getElementById('bulkApproveBtn');
        
        // Select All functionality
        selectAllCheckbox.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                // Only select checkboxes for documents that are not already approved
                const row = checkbox.closest('tr');
                if (row && !row.classList.contains('approved')) {
                    checkbox.checked = this.checked;
                }
            });
            updateBulkButton();
        });
        
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                updateBulkButton();
                updateSelectAllState();
            });
        });
        
        function updateSelectAllState() {
            const checkedBoxes = document.querySelectorAll('.submission-checkbox:checked');
            // Only count non-approved documents for select all logic
            const nonApprovedCheckboxes = Array.from(checkboxes).filter(checkbox => {
                const row = checkbox.closest('tr');
                return row && !row.classList.contains('approved');
            });
            const checkedNonApproved = Array.from(checkedBoxes).filter(checkbox => {
                const row = checkbox.closest('tr');
                return row && !row.classList.contains('approved');
            });
            
            if (checkedNonApproved.length === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (checkedNonApproved.length === nonApprovedCheckboxes.length) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
            }
        }
        
        function updateBulkButton() {
            const checkedBoxes = document.querySelectorAll('.submission-checkbox:checked');
            // Only count non-approved documents for bulk approve
            const checkedNonApproved = Array.from(checkedBoxes).filter(checkbox => {
                const row = checkbox.closest('tr');
                return row && !row.classList.contains('approved');
            });
            bulkApproveBtn.disabled = checkedNonApproved.length === 0;
            bulkApproveBtn.textContent = `Bulk Approve (${checkedNonApproved.length})`;
        }
        
        // Bulk approve functionality
        bulkApproveBtn.addEventListener('click', function() {
            const checkedBoxes = document.querySelectorAll('.submission-checkbox:checked');
            // Only process non-approved documents
            const nonApprovedSubmissions = Array.from(checkedBoxes).filter(checkbox => {
                const row = checkbox.closest('tr');
                return row && !row.classList.contains('approved');
            });
            const submissionIds = nonApprovedSubmissions.map(cb => cb.value);
            
            if (submissionIds.length === 0) {
                alert('No non-approved documents selected for bulk approval.');
                return;
            }
            
            if (confirm(`Approve ${submissionIds.length} submissions?`)) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'bulk_approve.php';
                
                submissionIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'submission_ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                form.submit();
            }
        });
        
        // Quick actions
        function quickApprove(submissionId) {
            if (confirm('Approve this document?')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'quick_approve.php';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'submission_id';
                input.value = submissionId;
                form.appendChild(input);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function requestRevision(submissionId) {
            const feedback = prompt('Please provide feedback for revision:');
            if (feedback) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'quick_revision.php';
                
                const idInput = document.createElement('input');
                idInput.type = 'hidden';
                idInput.name = 'submission_id';
                idInput.value = submissionId;
                form.appendChild(idInput);
                
                const feedbackInput = document.createElement('input');
                feedbackInput.type = 'hidden';
                feedbackInput.name = 'feedback';
                feedbackInput.value = feedback;
                form.appendChild(feedbackInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function clearFilters() {
            document.getElementById('student').value = '';
            document.getElementById('document_type').value = '';
            document.getElementById('status').value = '';
            document.getElementById('date_from').value = '';
            document.getElementById('date_to').value = '';
            document.getElementById('sort_by').value = 'submitted_at';
        }
    </script>
</body>
</html>
