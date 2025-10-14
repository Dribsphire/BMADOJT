<?php
/**
 * System Maintenance View
 * OJT Route - System maintenance and health monitoring interface
 */

// Get system health data
$health = $health ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Maintenance - OJT Route</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .health-card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 0.5rem;
        }
        .health-header {
            background: linear-gradient(135deg, #0ea539 0%, #10B981 100%);
            color: white;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .status-healthy {
            color: #10B981;
        }
        .status-warning {
            color: #F59E0B;
        }
        .status-error {
            color: #EF4444;
        }
        .component-card {
            transition: transform 0.2s;
        }
        .component-card:hover {
            transform: translateY(-2px);
        }
        .maintenance-actions {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1.5rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-tools me-2"></i>OJT Route
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home me-1"></i>Dashboard
                </a>
                <a class="nav-link" href="settings.php">
                    <i class="fas fa-cogs me-1"></i>Settings
                </a>
                <a class="nav-link" href="../logout.php">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card health-card">
                    <div class="card-header health-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0">
                                    <i class="fas fa-heartbeat me-2"></i>System Health Monitor
                                </h4>
                                <p class="mb-0 mt-1">Monitor system health and perform maintenance tasks</p>
                            </div>
                            <div>
                                <span class="badge bg-light text-dark fs-6">
                                    <i class="fas fa-clock me-1"></i>Last Updated: <?= $health['timestamp'] ?? 'Never' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overall Status -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card health-card">
                    <div class="card-body text-center">
                        <h5 class="card-title">
                            <i class="fas fa-shield-alt me-2"></i>Overall System Status
                        </h5>
                        <div class="display-4 mb-3">
                            <?php
                            $overallStatus = $health['overall'] ?? 'unknown';
                            switch ($overallStatus) {
                                case 'healthy':
                                    echo '<i class="fas fa-check-circle text-success"></i>';
                                    break;
                                case 'warning':
                                    echo '<i class="fas fa-exclamation-triangle text-warning"></i>';
                                    break;
                                case 'error':
                                    echo '<i class="fas fa-times-circle text-danger"></i>';
                                    break;
                                default:
                                    echo '<i class="fas fa-question-circle text-secondary"></i>';
                            }
                            ?>
                        </div>
                        <h3 class="text-uppercase fw-bold">
                            <?php
                            switch ($overallStatus) {
                                case 'healthy':
                                    echo '<span class="text-success">System Healthy</span>';
                                    break;
                                case 'warning':
                                    echo '<span class="text-warning">Needs Attention</span>';
                                    break;
                                case 'error':
                                    echo '<span class="text-danger">System Issues</span>';
                                    break;
                                default:
                                    echo '<span class="text-secondary">Unknown Status</span>';
                            }
                            ?>
                        </h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Component Health -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3">
                    <i class="fas fa-cogs me-2"></i>Component Health
                </h5>
            </div>
        </div>

        <div class="row">
            <?php
            $components = $health['components'] ?? [];
            $componentNames = [
                'database' => ['icon' => 'fas fa-database', 'name' => 'Database'],
                'storage' => ['icon' => 'fas fa-hdd', 'name' => 'Storage'],
                'email' => ['icon' => 'fas fa-envelope', 'name' => 'Email System'],
                'users' => ['icon' => 'fas fa-users', 'name' => 'User Data'],
                'attendance' => ['icon' => 'fas fa-clock', 'name' => 'Attendance'],
                'documents' => ['icon' => 'fas fa-file-alt', 'name' => 'Documents']
            ];
            
            foreach ($componentNames as $key => $info):
                $component = $components[$key] ?? [];
                $status = $component['status'] ?? 'unknown';
                $message = $component['message'] ?? 'No information available';
                $details = $component['details'] ?? [];
            ?>
            <div class="col-md-6 col-lg-4 mb-3">
                <div class="card component-card health-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <div class="me-3">
                                <i class="<?= $info['icon'] ?> fa-2x 
                                    <?php
                                    switch ($status) {
                                        case 'healthy':
                                            echo 'text-success';
                                            break;
                                        case 'warning':
                                            echo 'text-warning';
                                            break;
                                        case 'error':
                                            echo 'text-danger';
                                            break;
                                        default:
                                            echo 'text-secondary';
                                    }
                                    ?>">
                                </i>
                            </div>
                            <div>
                                <h6 class="card-title mb-0"><?= $info['name'] ?></h6>
                                <small class="text-muted">
                                    <?php
                                    switch ($status) {
                                        case 'healthy':
                                            echo '<span class="text-success">Healthy</span>';
                                            break;
                                        case 'warning':
                                            echo '<span class="text-warning">Warning</span>';
                                            break;
                                        case 'error':
                                            echo '<span class="text-danger">Error</span>';
                                            break;
                                        default:
                                            echo '<span class="text-secondary">Unknown</span>';
                                    }
                                    ?>
                                </small>
                            </div>
                        </div>
                        <p class="card-text small"><?= htmlspecialchars($message) ?></p>
                        
                        <?php if (!empty($details)): ?>
                        <div class="mt-2">
                            <small class="text-muted">
                                <?php foreach ($details as $key => $value): ?>
                                    <div><strong><?= ucfirst(str_replace('_', ' ', $key)) ?>:</strong> <?= htmlspecialchars($value) ?></div>
                                <?php endforeach; ?>
                            </small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Maintenance Actions -->
        <div class="row">
            <div class="col-12">
                <div class="maintenance-actions">
                    <h5 class="mb-3">
                        <i class="fas fa-wrench me-2"></i>Maintenance Actions
                    </h5>
                    <p class="text-muted mb-4">Perform system maintenance tasks to keep the system running smoothly.</p>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-broom me-2"></i>Clean Up Old Data
                                    </h6>
                                    <p class="card-text small text-muted">
                                        Remove old email queue entries, activity logs, and expired sessions.
                                    </p>
                                    <form method="POST" action="maintenance.php?action=action" class="d-inline">
                                        <input type="hidden" name="action" value="cleanup">
                                        <button type="submit" class="btn btn-outline-primary btn-sm" 
                                                onclick="return confirm('This will clean up old data. Continue?')">
                                            <i class="fas fa-play me-1"></i>Run Cleanup
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <div class="card">
                                <div class="card-body">
                                    <h6 class="card-title">
                                        <i class="fas fa-tachometer-alt me-2"></i>Optimize Database
                                    </h6>
                                    <p class="card-text small text-muted">
                                        Optimize database tables to improve performance and reduce storage usage.
                                    </p>
                                    <form method="POST" action="maintenance.php?action=action" class="d-inline">
                                        <input type="hidden" name="action" value="optimize">
                                        <button type="submit" class="btn btn-outline-success btn-sm"
                                                onclick="return confirm('This will optimize the database. Continue?')">
                                            <i class="fas fa-play me-1"></i>Optimize Database
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Auto-refresh every 5 minutes
        setTimeout(function() {
            location.reload();
        }, 300000);
        
        // Show success/error messages
        <?php if (isset($_SESSION['success'])): ?>
            alert('<?= addslashes($_SESSION['success']) ?>');
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            alert('<?= addslashes($_SESSION['error']) ?>');
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
    </script>
</body>
</html>

