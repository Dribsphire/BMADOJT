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
</head>
<body>
    <?php include 'teacher-sidebar.php'; ?>
    
    <main>
        <?php include 'navigation-header.php'; ?>
        
        <!-- Main Content -->
        <div class="container-fluid py-4">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2></i>Overdue Documents Management</h2>
                        <a href="review_documents.php" class="btn btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i>Back to Review
                        </a>
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
                    
                    
                    <!-- Overdue Documents Table -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="bi bi-list-ul me-2"></i>Overdue Documents
                                <span class="badge bg-danger ms-2"><?= count($overdueDocuments) ?></span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($overdueDocuments)): ?>
                                <div class="text-center py-5">
                                    <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                                    <h4 class="mt-3 text-success">No Overdue Documents</h4>
                                    <p class="text-muted">All documents are up to date!</p>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead class="table-dark">
                                            <tr>
                                                <th>Student</th>
                                                <th>Document</th>
                                                <th>Type</th>
                                                <th>Deadline</th>
                                                <th>Days Overdue</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($overdueDocuments as $doc): ?>
                                                <?php
                                                $daysOverdue = floor((time() - strtotime($doc['deadline'])) / (24 * 60 * 60));
                                                $urgencyClass = $daysOverdue > 7 ? 'danger' : ($daysOverdue > 3 ? 'warning' : 'info');
                                                ?>
                                                <tr class="table-<?= $urgencyClass ?>">
                                                    <td>
                                                        <div>
                                                            <strong><?= htmlspecialchars($doc['student_name']) ?></strong>
                                                            <br><small class="text-muted"><?= htmlspecialchars($doc['student_email']) ?></small>
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
                                                        <span class="text-<?= $urgencyClass ?>">
                                                            <i class="bi bi-calendar me-1"></i>
                                                            <?= date('M j, Y', strtotime($doc['deadline'])) ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $urgencyClass ?>">
                                                            <?= $daysOverdue ?> day(s)
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <span class="badge bg-<?= $doc['submission_status'] === 'approved' ? 'success' : 'warning' ?>">
                                                            <?= ucfirst($doc['submission_status'] ?? 'Not Submitted') ?>
                                                        </span>
                                                    </td>
                                                    <td>
                                                        <div class="btn-group-vertical btn-group-sm" role="group">
                                                            <a href="document_review.php?id=<?= $doc['submission_id'] ?>" 
                                                               class="btn btn-outline-primary btn-sm mb-1">
                                                                <i class="bi bi-eye me-1"></i>Review
                                                            </a>
                                                            
                                                            <?php if ($doc['submission_status'] !== 'approved'): ?>
                                                                <form method="POST" class="d-inline">
                                                                    <input type="hidden" name="action" value="resolve_overdue">
                                                                    <input type="hidden" name="document_id" value="<?= $doc['id'] ?>">
                                                                    <input type="hidden" name="student_id" value="<?= $doc['student_id'] ?>">
                                                                    <button type="submit" class="btn btn-success btn-sm mb-1" 
                                                                            onclick="return confirm('Mark this overdue document as resolved?')">
                                                                        <i class="bi bi-check-circle me-1"></i>Resolve
                                                                    </button>
                                                                </form>
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
                </div>
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
    
    <style>
        :root {
            --chmsu-green: #0ea539;
            --chmsu-green-light: #34d399;
            --chmsu-green-dark: #059669;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
    </style>
</body>
</html>
