<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../src/Utils/Database.php';
require_once '../../src/Controllers/DocumentDashboardController.php';
require_once '../../src/Middleware/AuthMiddleware.php';

use App\Controllers\DocumentDashboardController;
use App\Middleware\AuthMiddleware;

// Check authentication
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('instructor')) {
    $authMiddleware->redirectToUnauthorized();
}

$controller = new DocumentDashboardController();

// Get dashboard data
$dashboardData = $controller->getDashboardData($_SESSION['user_id']);
$analytics = $controller->getDocumentAnalytics($_SESSION['user_id']);
$monitoring = $controller->getDocumentMonitoring($_SESSION['user_id']);
$alerts = $controller->getDocumentAlerts($_SESSION['user_id']);

// Handle export requests
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportResult = $controller->exportDocumentData($_SESSION['user_id'], 'csv');
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $exportResult['filename'] . '"');
    echo $exportResult['data'];
    exit;
}

// Handle AJAX requests for real-time updates
if (isset($_GET['ajax']) && $_GET['ajax'] === 'updates') {
    header('Content-Type: application/json');
    echo json_encode($controller->getRealTimeUpdates($_SESSION['user_id']));
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Dashboard | OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <script type="text/javascript" src="../js/sidebarSlide.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
</head>
<body>
    <?php include 'teacher-sidebar.php'; ?>
    
    <main class="main-content">
        <?php include 'navigation-header.php'; ?>
        <br>
        <div class="container-fluid">
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2><i class="bi bi-graph-up me-2"></i>Document Dashboard</h2>
                        <div class="btn-group">
                            <a href="?export=csv" class="btn btn-outline-success">
                                <i class="bi bi-download me-1"></i>Export a CSV
                            </a>
                        </div>
                    </div>
                    
                    <!-- Alerts Section -->
                    <?php if ($alerts['alert_count'] > 0): ?>
                        <div class="alert alert-warning alert-dismissible fade show">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Alerts:</strong> You have <?= $alerts['alert_count'] ?> document alerts that need attention.
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Overview Cards -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="card-title"><?= $dashboardData['overview']['total_students'] ?></h4>
                                            <p class="card-text">Total Students</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="bi bi-people" style="font-size: 2rem;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="card-title"><?= $dashboardData['overview']['approved_documents'] ?></h4>
                                            <p class="card-text">Approved Documents</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="bi bi-check-circle" style="font-size: 2rem;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="card-title"><?= $dashboardData['overview']['pending_documents'] ?></h4>
                                            <p class="card-text">Pending Review</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="bi bi-clock" style="font-size: 2rem;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <h4 class="card-title"><?= $dashboardData['overview']['overdue_count'] ?></h4>
                                            <p class="card-text">Overdue Documents</p>
                                        </div>
                                        <div class="align-self-center">
                                            <i class="bi bi-exclamation-triangle" style="font-size: 2rem;"></i>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Performance Metrics -->
                    <div class="row mb-4">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="bi bi-graph-up me-2"></i>Student completion</h5>
                                </div>
                                <div class="card-body">
                                    <div class="progress mb-3">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?= $dashboardData['overview']['completion_rate'] ?>%">
                                            <?= $dashboardData['overview']['completion_rate'] ?>%
                                        </div>
                                    </div>
                                    <p class="text-muted mb-0">
                                        <?= $dashboardData['overview']['students_with_submissions'] ?> of 
                                        <?= $dashboardData['overview']['total_students'] ?> students have submitted documents
                                    </p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0"><i class="bi bi-check-circle me-2"></i>Approval</h5>
                                </div>
                                <div class="card-body">
                                    <div class="progress mb-3">
                                        <div class="progress-bar bg-success" role="progressbar" 
                                             style="width: <?= $dashboardData['overview']['approval_rate'] ?>%">
                                            <?= $dashboardData['overview']['approval_rate'] ?>%
                                        </div>
                                    </div>
                                    <p class="text-muted mb-0">
                                        <?= $dashboardData['overview']['approved_documents'] ?> of 
                                        <?= $dashboardData['overview']['total_submissions'] ?> submissions approved
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                
                    
                    <!-- At-Risk Students -->
                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="bi bi-exclamation-triangle me-2"></i>At-Risk Students
                                        <span class="badge bg-danger ms-2"><?= count($monitoring['at_risk_students']) ?></span>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($monitoring['at_risk_students'])): ?>
                                        <div class="text-center py-3">
                                            <i class="bi bi-check-circle text-success" style="font-size: 2rem;"></i>
                                            <p class="mt-2 text-muted">No at-risk students found!</p>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Student</th>
                                                        <th>Overdue</th>
                                                        <th>Rejected</th>
                                                        <th>Revision Required</th>
                                                        <th>Risk Level</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($monitoring['at_risk_students'] as $student): ?>
                                                        <?php
                                                        $totalIssues = $student['overdue_count'] + $student['rejected_count'] + $student['revision_count'];
                                                        $riskLevel = $totalIssues > 3 ? 'danger' : ($totalIssues > 1 ? 'warning' : 'info');
                                                        ?>
                                                        <tr>
                                                            <td>
                                                                <strong><?= htmlspecialchars($student['full_name']) ?></strong>
                                                                <br><small class="text-muted"><?= htmlspecialchars($student['email']) ?></small>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-danger"><?= $student['overdue_count'] ?></span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-danger"><?= $student['rejected_count'] ?></span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-warning"><?= $student['revision_count'] ?></span>
                                                            </td>
                                                            <td>
                                                                <span class="badge bg-<?= $riskLevel ?>">
                                                                    <?= $riskLevel === 'danger' ? 'High' : ($riskLevel === 'warning' ? 'Medium' : 'Low') ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <a href="review_documents.php?student=<?= $student['id'] ?>" 
                                                                   class="btn btn-sm btn-outline-primary">
                                                                    <i class="bi bi-eye me-1"></i>Review
                                                                </a>
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
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Chart.js configurations
        const submissionTrendsData = <?= json_encode($analytics['submission_trends']) ?>;
        const documentStatusData = {
            approved: <?= $dashboardData['overview']['approved_documents'] ?>,
            pending: <?= $dashboardData['overview']['pending_documents'] ?>,
            revision_required: <?= $dashboardData['overview']['revision_required'] ?>,
            rejected: <?= $dashboardData['overview']['rejected_documents'] ?>
        };

        // Submission Trends Chart
        const trendsCtx = document.getElementById('submissionTrendsChart').getContext('2d');
        new Chart(trendsCtx, {
            type: 'line',
            data: {
                labels: submissionTrendsData.map(item => item.submission_date),
                datasets: [{
                    label: 'Submissions',
                    data: submissionTrendsData.map(item => item.submissions_count),
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.2)',
                    tension: 0.1
                }, {
                    label: 'Approved',
                    data: submissionTrendsData.map(item => item.approved_count),
                    borderColor: 'rgb(54, 162, 235)',
                    backgroundColor: 'rgba(54, 162, 235, 0.2)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // Document Status Chart
        const statusCtx = document.getElementById('documentStatusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Approved', 'Pending', 'Revision Required', 'Rejected'],
                datasets: [{
                    data: [
                        documentStatusData.approved,
                        documentStatusData.pending,
                        documentStatusData.revision_required,
                        documentStatusData.rejected
                    ],
                    backgroundColor: [
                        '#28a745',
                        '#ffc107',
                        '#17a2b8',
                        '#dc3545'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false
            }
        });

    </script>
    
    <style>
        /* Ensure card text is white */
        .card.bg-primary .card-body,
        .card.bg-success .card-body,
        .card.bg-warning .card-body,
        .card.bg-danger .card-body,
        .card.bg-info .card-body {
            color: white !important;
        }
        
        .card.bg-primary .card-body h4,
        .card.bg-success .card-body h4,
        .card.bg-warning .card-body h4,
        .card.bg-danger .card-body h4,
        .card.bg-info .card-body h4 {
            color: white !important;
        }
        
        .card.bg-primary .card-body p,
        .card.bg-success .card-body p,
        .card.bg-warning .card-body p,
        .card.bg-danger .card-body p,
        .card.bg-info .card-body p {
            color: white !important;
        }
        
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        
        .timeline-marker {
            position: absolute;
            left: -30px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #dee2e6;
        }
        
        .timeline-content {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 3px solid #dee2e6;
        }
        
        .timeline-item:not(:last-child)::before {
            content: '';
            position: absolute;
            left: -25px;
            top: 17px;
            width: 2px;
            height: calc(100% + 20px);
            background: #dee2e6;
        }
    </style>
    
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
