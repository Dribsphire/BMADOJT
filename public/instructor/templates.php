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

        .template-card {
            transition: transform 0.2s ease-in-out;
            
        }
        
        .template-card:hover {
            transform: translateY(-2px);
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
            <h1 class="h2">Document Templates</h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <a href="upload_template.php" class="btn btn-success">
                    <i class="bi bi-plus me-1"></i>Upload Template
                </a>
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

        <!-- Templates List -->
        <div class="row">
            <?php if (empty($templates)): ?>
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="bi bi-file-earmark-text" style="font-size: 4rem; color: #6c757d;"></i>
                            <h4 class="mt-3">No Templates Uploaded</h4>
                            <p class="text-muted">Upload your first document template to get started.</p>
                            <a href="upload_template.php" class="btn btn-success">
                                <i class="bi bi-plus me-1"></i>Upload Template
                            </a>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($templates as $template): ?>
                    <div class="col-md-6 col-lg-4 mb-4">
                        <div class="card template-card h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h6 class="card-title mb-0"><?= htmlspecialchars($template->document_name) ?></h6>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="download_template.php?id=<?= $template->id ?>">
                                                <i class="bi bi-download me-2"></i>Download
                                            </a></li>
                                            <?php if ($template->uploaded_by === $_SESSION['user_id']): ?>
                                                <li><a class="dropdown-item" href="edit_template.php?id=<?= $template->id ?>">
                                                    <i class="bi bi-pencil me-2"></i>Edit
                                                </a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="?delete=<?= $template->id ?>" 
                                                       onclick="return confirm('Are you sure you want to delete this template?')">
                                                    <i class="bi bi-trash me-2"></i>Delete
                                                </a></li>
                                            <?php else: ?>
                                                <li><span class="dropdown-item-text text-muted">
                                                    <i class="bi bi-lock me-2"></i>System Template
                                                </span></li>
                                            <?php endif; ?>
                                        </ul>
                                    </div>
                                </div>
                                
                                <p class="card-text">
                                    <span class="badge bg-primary"><?= ucfirst(str_replace('_', ' ', $template->document_type)) ?></span>
                                    <?php if ($template->uploaded_by === 1 && $template->uploaded_for_section === null): ?>
                                        <span class="badge bg-info">System Template</span>
                                    <?php endif; ?>
                                    <?php if ($template->hasDeadline()): ?>
                                        <span class="badge bg-warning">Deadline: <?= date('M j, Y', strtotime($template->deadline)) ?></span>
                                    <?php endif; ?>
                                </p>
                                
                                <p class="card-text text-muted small">
                                    <i class="bi bi-calendar me-1"></i>
                                    Uploaded <?= date('M j, Y', strtotime($template->created_at)) ?>
                                </p>
                                
                                <?php if ($template->hasDeadline() && $template->isOverdue()): ?>
                                    <div class="alert alert-danger alert-sm">
                                        <i class="bi bi-exclamation-triangle me-1"></i>
                                        Deadline has passed
                                    </div>
                                <?php elseif ($template->hasDeadline()): ?>
                                    <div class="alert alert-info alert-sm">
                                        <i class="bi bi-clock me-1"></i>
                                        <?= $template->getDaysUntilDeadline() ?> days remaining
                                    </div>
                                <?php endif; ?>
                                
                                <div class="d-grid gap-2">
                                    <a href="download_template.php?id=<?= $template->id ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-download me-1"></i>Download Template
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Download Statistics -->
        <?php if (!empty($statistics['download_stats'])): ?>
            <div class="row mt-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0">
                                <i class="bi bi-graph-up me-2"></i>Download Statistics
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Template Name</th>
                                            <th>Downloads</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($statistics['download_stats'] as $stat): ?>
                                            <tr>
                                                <td><?= htmlspecialchars($stat['document_name']) ?></td>
                                                <td>
                                                    <span class="badge bg-primary"><?= $stat['download_count'] ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($stat['download_count'] > 0): ?>
                                                        <span class="text-success">
                                                            <i class="bi bi-check-circle me-1"></i>Active
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="text-muted">
                                                            <i class="bi bi-dash-circle me-1"></i>No downloads
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
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
    });
    </script>
</body>
</html>
