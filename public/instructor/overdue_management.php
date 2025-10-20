<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../src/Utils/Database.php';
require_once '../../src/Controllers/OverdueController.php';
require_once '../../src/Middleware/AuthMiddleware.php';

use App\Controllers\OverdueController;
use App\Middleware\AuthMiddleware;

// Check authentication
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('instructor')) {
    $authMiddleware->redirectToUnauthorized();
}

$controller = new OverdueController();

// Get overdue documents for instructor
$overdueDocuments = $controller->getOverdueDocumentsForInstructor($_SESSION['user_id']);
$statistics = $controller->getOverdueStatistics();

// Handle overdue resolution
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resolve_overdue') {
    $documentId = (int)($_POST['document_id'] ?? 0);
    $studentId = (int)($_POST['student_id'] ?? 0);
    
    if ($documentId > 0 && $studentId > 0) {
        $result = $controller->markOverdueAsResolved($documentId, $studentId, $_SESSION['user_id']);
        if ($result['success']) {
            header('Location: overdue_management.php?success=overdue_resolved');
            exit;
        } else {
            $errorMessage = $result['message'];
        }
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
    <title>Overdue Management | OJT Route</title>
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
        
        .overdue-row {
            transition: background-color 0.2s ease-in-out;
        }
        
        .overdue-row:hover {
            background-color: #f8f9fa;
        }
        
        .overdue-row.urgent {
            border-left: 4px solid #dc3545;
        }
        
        .overdue-row.warning {
            border-left: 4px solid #ffc107;
        }
        
        .overdue-row.info {
            border-left: 4px solid #17a2b8;
        }
        
        .status-badge {
            font-size: 0.8rem;
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
    <?php include 'teacher-sidebar.php'; ?>
    
    <main>
        <?php include 'navigation-header.php'; ?>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">Overdue Documents Management</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <a href="review_documents.php" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i>Back to Review
                    </a>
                </div>
            </div>
        </div>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success alert-dismissible fade show">
                            <i class="bi bi-check-circle me-2"></i>
                            <?php if ($success === 'overdue_resolved'): ?>
                                Overdue document marked as resolved successfully!
                            <?php endif; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger alert-dismissible fade show">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Error:</strong> <?= htmlspecialchars($error) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    
        <!-- Overdue Documents List -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    <i class="bi bi-list-ul me-2"></i>Overdue Documents
                    <span class="badge bg-danger ms-2"><?= count($overdueDocuments) ?></span>
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($overdueDocuments)): ?>
                    <div class="text-center py-5">
                        <i class="bi bi-check-circle text-success" style="font-size: 3rem;"></i>
                        <h5 class="mt-3 text-success">No Overdue Documents</h5>
                        <p class="text-muted">All documents are up to date!</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="25%">Student</th>
                                    <th width="20%">Document</th>
                                    <th width="15%">Type</th>
                                    <th width="15%">Deadline</th>
                                    <th width="10%">Days Overdue</th>
                                    <th width="10%">Status</th>
                                    <th width="15%">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($overdueDocuments as $doc): ?>
                                    <?php
                                    $daysOverdue = floor((time() - strtotime($doc['deadline'])) / (24 * 60 * 60));
                                    $urgencyClass = $daysOverdue > 7 ? 'danger' : ($daysOverdue > 3 ? 'warning' : 'info');
                                    $rowClass = $daysOverdue > 7 ? 'urgent' : ($daysOverdue > 3 ? 'warning' : 'info');
                                    ?>
                                    <tr class="overdue-row <?= $rowClass ?>">
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($doc['student_name']) ?></strong>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($doc['student_email']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($doc['document_name']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark">
                                                <?= ucfirst(str_replace('_', ' ', $doc['document_type'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small class="text-muted">
                                                <i class="bi bi-calendar me-1"></i>
                                                <?= date('M j, Y', strtotime($doc['deadline'])) ?>
                                            </small>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $urgencyClass ?> status-badge">
                                                <?= $daysOverdue ?> day(s)
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?= $doc['submission_status'] === 'approved' ? 'success' : 'warning' ?> status-badge">
                                                <?= ucfirst($doc['submission_status'] ?? 'Not Submitted') ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <a href="document_review.php?id=<?= $doc['submission_id'] ?>" 
                                                   class="btn btn-outline-primary btn-sm" title="Review Document">
                                                    <i class="bi bi-eye"></i>
                                                </a>
                                                
                                                <?php if ($doc['submission_status'] !== 'approved'): ?>
                                                    <button type="button" class="btn btn-success btn-sm" 
                                                            onclick="resolveOverdue(<?= $doc['id'] ?>, <?= $doc['student_id'] ?>)" title="Resolve Overdue">
                                                        <i class="bi bi-check-circle"></i>
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
        function resolveOverdue(documentId, studentId) {
            if (confirm('Mark this overdue document as resolved?')) {
                // Create form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'overdue_management.php';
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'action';
                actionInput.value = 'resolve_overdue';
                form.appendChild(actionInput);
                
                const documentInput = document.createElement('input');
                documentInput.type = 'hidden';
                documentInput.name = 'document_id';
                documentInput.value = documentId;
                form.appendChild(documentInput);
                
                const studentInput = document.createElement('input');
                studentInput.type = 'hidden';
                studentInput.name = 'student_id';
                studentInput.value = studentId;
                form.appendChild(studentInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
    
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
</body>
</html>
