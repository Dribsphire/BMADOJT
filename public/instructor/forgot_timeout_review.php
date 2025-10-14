<?php
/**
 * Instructor Forgot Timeout Review Page
 * OJT Route - Forgot Timeout Review System
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Services\ForgotTimeoutReviewService;
use App\Utils\Database;

// Start session
session_start();

// Set timezone to Philippines (UTC+08:00)
date_default_timezone_set('Asia/Manila');

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication
if (!$authMiddleware->requireRole('instructor')) {
    header('Location: ../login.php');
    exit;
}

// Get current user
$user = $authService->getCurrentUser();
if (!$user) {
    header('Location: ../login.php');
    exit;
}

// Initialize services
$pdo = Database::getInstance();
$reviewService = new ForgotTimeoutReviewService($pdo);

// Get filter parameters
$status = $_GET['status'] ?? 'all';
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 20;
$offset = ($page - 1) * $limit;

// Get requests and statistics
$requests = $reviewService->getRequestsForInstructor($user->id, $status, $limit, $offset);
$stats = $reviewService->getRequestStats($user->id);

// Get total count for pagination
$totalRequests = $reviewService->getRequestsForInstructor($user->id, $status, 1000, 0);
$totalPages = ceil(count($totalRequests) / $limit);

// Get instructor's sections
$stmt = $pdo->prepare("
    SELECT DISTINCT s.id, s.section_name, s.section_code
    FROM sections s
    JOIN users u ON s.id = u.section_id
    WHERE u.id = ?
");
$stmt->execute([$user->id]);
$sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Timeout Review - OJT Route</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../css/style.css" rel="stylesheet">
    <link href="../css/sidebarstyle.css" rel="stylesheet">
</head>
<style>
    :root {
        --chmsu-green: #0ea539;
    }
    body {
        font-family: 'Poppins', sans-serif;
        background-color: #f8f9fa;
    }
</style>
<body>
    <?php include 'teacher-sidebar.php'; ?>
    
    <main>
        <?php include 'navigation-header.php'; ?>
        <!-- Main Content -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>Forgot Timeout Review
                        </h2>
                        <div class="d-flex gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="refreshRequests()">
                                <i class="bi bi-arrow-clockwise me-1"></i>Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-body">
                            <form method="GET" class="row g-3">
                                <div class="col-md-4">
                                    <label for="status" class="form-label" style="font-size: 13px;">Filter by Status</label>
                                    <select class="form-select" id="status" name="status" style="font-size: 13px;">
                                        <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All Requests</option>
                                        <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="approved" <?= $status === 'approved' ? 'selected' : '' ?>>Approved</option>
                                        <option value="rejected" <?= $status === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="section" class="form-label" style="font-size: 13px;">Section</label>
                                    <select class="form-select" id="section" name="section" style="font-size: 13px;">
                                        <option value="">All Sections</option>
                                        <?php foreach ($sections as $section): ?>
                                        <option value="<?= $section['id'] ?>" <?= ($_GET['section'] ?? '') === $section['id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($section['section_name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary me-2" style="font-size: 13px;">
                                        <i class="bi bi-funnel me-1" style="color: white;"></i>
                                    </button>
                                    <a href="forgot_timeout_review.php" class="btn btn-outline-secondary" style="font-size: 13px;">
                                        <i class="bi bi-arrow-clockwise me-1" style="color: white;"></i>
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Requests List -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="bi bi-list-ul me-2"></i>Forgot Timeout Requests
                                    <span class="badge bg-primary ms-2"><?= count($requests) ?></span>
                                </h5>
                                <div class="bulk-actions d-none" id="bulkActions">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-success btn-sm" onclick="bulkApprove()">
                                            <i class="bi bi-check-all me-1"></i>Approve Selected
                                        </button>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="bulkReject()">
                                            <i class="bi bi-x-square me-1"></i>Reject Selected
                                        </button>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="clearSelection()">
                                            <i class="bi bi-x me-1"></i>Clear
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <?php if (empty($requests)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                                    <h5>No Requests Found</h5>
                                    <p class="text-muted">
                                        <?php if ($status === 'all'): ?>
                                            No forgot timeout requests found for your sections.
                                        <?php else: ?>
                                            No <?= ucfirst($status) ?> requests found.
                                        <?php endif; ?>
                                    </p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-hover mb-0">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="50">
                                                    <input type="checkbox" id="selectAll" onchange="toggleSelectAll()">
                                                </th>
                                                <th>Student</th>
                                                <th>Date & Block</th>
                                                <th>Request Date</th>
                                                <th>Status</th>
                                                <th>Section</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($requests as $request): ?>
                                            <tr>
                                                <td>
                                                    <?php if ($request['status'] === 'pending'): ?>
                                                    <input type="checkbox" class="request-checkbox" value="<?= $request['id'] ?>" onchange="updateBulkActions()">
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= htmlspecialchars($request['student_name']) ?></strong>
                                                        <br>
                                                        <small class="text-muted"><?= htmlspecialchars($request['school_id']) ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <strong><?= date('M j, Y', strtotime($request['attendance_date'])) ?></strong>
                                                        <br>
                                                        <span class="badge bg-info"><?= ucfirst($request['block_type']) ?></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div>
                                                        <?= date('M j, Y', strtotime($request['request_date'])) ?>
                                                        <br>
                                                        <small class="text-muted"><?= date('g:i A', strtotime($request['created_at'])) ?></small>
                                                    </div>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = match($request['status'] ?: 'pending') {
                                                        'pending' => 'bg-warning',
                                                        'approved' => 'bg-success',
                                                        'rejected' => 'bg-danger',
                                                        default => 'bg-secondary'
                                                    };
                                                    ?>
                                                    <span class="badge <?= $statusClass ?>">
                                                        <?= ucfirst($request['status'] ?: 'pending') ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="text-muted"><?= htmlspecialchars($request['section_name'] ?? 'N/A') ?></span>
                                                </td>
                                                <td>
                                                    <button class="btn btn-outline-primary btn-sm" 
                                                            onclick="viewRequest(<?= $request['id'] ?>)"
                                                            title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <nav aria-label="Requests pagination">
                        <ul class="pagination justify-content-center">
                            <?php if ($page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page - 1 ?>&status=<?= $status ?>">Previous</a>
                            </li>
                            <?php endif; ?>
                            
                            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?page=<?= $i ?>&status=<?= $status ?>"><?= $i ?></a>
                            </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $totalPages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=<?= $page + 1 ?>&status=<?= $status ?>">Next</a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Request Details Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1" aria-labelledby="requestModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-md">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="requestModalLabel">Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="requestModalBody">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/sidebarSlide.js"></script>
    <script>
        function refreshRequests() {
            location.reload();
        }

        function viewRequest(requestId) {
            // Show loading in modal
            document.getElementById('requestModalBody').innerHTML = `
                <div class="text-center py-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p class="mt-2">Loading request details...</p>
                </div>
            `;
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('requestModal'));
            modal.show();

            // Fetch request details
            fetch(`forgot_timeout_details.php?id=${requestId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayRequestDetails(data.request);
                    } else {
                        document.getElementById('requestModalBody').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                Error: ${data.error}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('requestModalBody').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            Error loading request details: ${error.message}
                        </div>
                    `;
                });
        }

        function getBlockEndTime(blockType, date) {
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const dateStr = `${year}-${month}-${day}`;
            
            switch(blockType) {
                case 'morning':
                    return new Date(dateStr + ' 12:00:00');
                case 'afternoon':
                    return new Date(dateStr + ' 18:00:00');
                case 'overtime':
                    return new Date(dateStr + ' 20:00:00');
                default:
                    return new Date(dateStr + ' 12:00:00');
            }
        }

        function calculateHours(timeIn, blockEndTime) {
            const diffMs = blockEndTime - timeIn;
            const diffMinutes = Math.floor(diffMs / (1000 * 60));
            const hours = Math.round((diffMinutes / 60) * 100) / 100;
            return hours.toFixed(2);
        }

        function displayRequestDetails(request) {
            const statusClass = request.status === 'pending' ? 'warning' : 
                               request.status === 'approved' ? 'success' : 'danger';
            
            const timeIn = new Date(request.time_in);
            const timeInFormatted = timeIn.toLocaleString('en-US', {
                hour: '2-digit',
                minute: '2-digit'
            });
            
            const attendanceDate = new Date(request.attendance_date);
            const dateFormatted = attendanceDate.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });

            // Calculate hours if approved
            let hoursPreview = '';
            if (request.status === 'pending') {
                const blockEndTime = getBlockEndTime(request.block_type, attendanceDate);
                const hoursWorked = calculateHours(timeIn, blockEndTime);
                hoursPreview = `
                    <div class="alert alert-info py-2 mb-2" style="font-size: 0.85rem;">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>If approved:</strong> Student will receive <strong>${hoursWorked} hours</strong> for this ${request.block_type} block.
                    </div>
                `;
            }

            document.getElementById('requestModalBody').innerHTML = `
                <div class="text-center mb-3">
                    <h6 class="mb-1">${request.student_name}</h6>
                    <p class="text-muted mb-1" style="font-size: 0.85rem;">${request.school_id} • ${request.section_name || 'No Section'}</p>
                    <span class="badge bg-${statusClass}">${(request.status || 'pending').charAt(0).toUpperCase() + (request.status || 'pending').slice(1)}</span>
                </div>

                <div class="row g-2">
                    <div class="col-4">
                        <div class="card border-0 bg-light">
                            <div class="card-body text-center py-2">
                                <i class="bi bi-calendar-date fs-5 text-primary mb-1"></i>
                                <h6 class="card-title mb-1" style="font-size: 0.8rem;">Date</h6>
                                <p class="card-text fw-bold mb-0" style="font-size: 0.9rem;">${dateFormatted}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card border-0 bg-light">
                            <div class="card-body text-center py-2">
                                <i class="bi bi-clock fs-5 text-success mb-1"></i>
                                <h6 class="card-title mb-1" style="font-size: 0.8rem;">Time In</h6>
                                <p class="card-text fw-bold mb-0" style="font-size: 0.9rem;">${timeInFormatted}</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="card border-0 bg-light">
                            <div class="card-body text-center py-2">
                                <i class="bi bi-layers fs-5 text-warning mb-1"></i>
                                <h6 class="card-title mb-1" style="font-size: 0.8rem;">Block Type</h6>
                                <p class="card-text fw-bold mb-0 text-capitalize" style="font-size: 0.9rem;">${request.block_type}</p>
                            </div>
                        </div>
                    </div>
                </div>

                ${hoursPreview}

                <div class="row">
                    <div class="col-12">
                        <div class="d-flex gap-2 mb-2">
                            <a href="forgot_timeout_letter.php?id=${request.id}" class="btn btn-outline-primary btn-sm" target="_blank">
                                <i class="bi bi-download me-1"></i>Letter
                            </a>
                            <button class="btn btn-outline-secondary btn-sm" onclick="previewLetter(${request.id})">
                                <i class="bi bi-eye me-1"></i>Preview
                            </button>
                        </div>
                    </div>
                </div>

                ${request.instructor_response ? `
                <div class="alert alert-info py-2 mb-2" style="font-size: 0.85rem;">
                    <i class="bi bi-chat-left-text me-1"></i>
                    <strong>Response:</strong> ${request.instructor_response}
                </div>
                ` : ''}

                ${request.status === 'pending' ? `
                <div class="mt-3">
                    <div class="mb-2">
                        <label for="instructorResponse" class="form-label" style="font-size: 0.9rem;">Response/Feedback</label>
                        <textarea class="form-control form-control-sm" id="instructorResponse" name="response" rows="2" 
                                  placeholder="Add feedback..."></textarea>
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-success btn-sm" onclick="submitAction(${request.id}, 'approve')">
                            <i class="bi bi-check me-1"></i>Approve
                        </button>
                        <button type="button" class="btn btn-danger btn-sm" onclick="submitAction(${request.id}, 'reject')">
                            <i class="bi bi-x me-1"></i>Reject
                        </button>
                    </div>
                </div>
                ` : ''}
            `;
        }

        function previewLetter(requestId) {
            const letterUrl = `forgot_timeout_letter.php?id=${requestId}`;
            window.open(letterUrl, '_blank');
        }

        function submitAction(requestId, action) {
            const response = document.getElementById('instructorResponse').value;
            const actionText = action === 'approve' ? 'approve' : 'reject';
            
            if (!confirm(`Are you sure you want to ${actionText} this request?`)) {
                return;
            }

            // Show loading
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Processing...';
            button.disabled = true;

            // Submit action
            const formData = new FormData();
            formData.append('request_id', requestId);
            formData.append('action', action);
            formData.append('response', response);

            fetch('forgot_timeout_action.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Close modal and refresh page
                    bootstrap.Modal.getInstance(document.getElementById('requestModal')).hide();
                    location.reload();
                } else {
                    alert('Error: ' + data.error);
                    button.innerHTML = originalText;
                    button.disabled = false;
                }
            })
            .catch(error => {
                alert('Error: ' + error.message);
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }

        // Bulk Actions Functions
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.request-checkbox');
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll.checked;
            });
            
            updateBulkActions();
        }

        function updateBulkActions() {
            const checkboxes = document.querySelectorAll('.request-checkbox:checked');
            const bulkActions = document.getElementById('bulkActions');
            
            if (checkboxes.length > 0) {
                bulkActions.classList.remove('d-none');
            } else {
                bulkActions.classList.add('d-none');
            }
        }

        function clearSelection() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.request-checkbox');
            
            selectAll.checked = false;
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            updateBulkActions();
        }

        function bulkApprove() {
            const checkboxes = document.querySelectorAll('.request-checkbox:checked');
            const requestIds = Array.from(checkboxes).map(cb => cb.value);
            
            if (requestIds.length === 0) {
                alert('Please select at least one request to approve.');
                return;
            }
            
            if (!confirm(`Are you sure you want to approve ${requestIds.length} request(s)?`)) {
                return;
            }
            
            performBulkAction(requestIds, 'approve');
        }

        function bulkReject() {
            const checkboxes = document.querySelectorAll('.request-checkbox:checked');
            const requestIds = Array.from(checkboxes).map(cb => cb.value);
            
            if (requestIds.length === 0) {
                alert('Please select at least one request to reject.');
                return;
            }
            
            if (!confirm(`Are you sure you want to reject ${requestIds.length} request(s)?`)) {
                return;
            }
            
            performBulkAction(requestIds, 'reject');
        }

        function performBulkAction(requestIds, action) {
            const formData = new FormData();
            formData.append('bulk_action', action);
            formData.append('request_ids', JSON.stringify(requestIds));
            formData.append('response', 'Bulk ' + action + 'd');

            fetch('forgot_timeout_bulk_action.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Successfully ${action}d ${data.processed_count} request(s).`);
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Unknown error occurred'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while processing the bulk action.');
            });
        }

    </script>
</body>
</html>
