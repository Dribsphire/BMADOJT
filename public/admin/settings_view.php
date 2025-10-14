<?php
/**
 * System Settings View
 * OJT Route - Admin system configuration interface
 */

// Get configuration values
$emailConfig = $emailConfig ?? [];
$geolocationConfig = $geolocationConfig ?? [];
$fileUploadConfig = $fileUploadConfig ?? [];
$systemConfig = $systemConfig ?? [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - OJT Route</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .settings-card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            border-radius: 0.5rem;
        }
        .settings-header {
            background: linear-gradient(135deg, #0ea539 0%, #10B981 100%);
            color: white;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        .nav-pills .nav-link {
            color: #6c757d;
            border-radius: 0.5rem;
        }
        .nav-pills .nav-link.active {
            background-color: #0ea539;
            color: white;
        }
        .form-label {
            font-weight: 600;
            color: #495057;
        }
        .config-section {
            display: none;
        }
        .config-section.active {
            display: block;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="dashboard.php">
                <i class="fas fa-cogs me-2"></i>OJT Route
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">
                    <i class="fas fa-home me-1"></i>Dashboard
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
                <div class="card settings-card">
                    <div class="card-header settings-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h4 class="mb-0">
                                    <i class="fas fa-cogs me-2"></i>System Settings
                                </h4>
                                <p class="mb-0 mt-1">Configure system-wide settings and preferences</p>
                            </div>
                            <div>
                                <span class="badge bg-light text-dark">
                                    <i class="fas fa-server me-1"></i>System Status: Online
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Settings Navigation -->
        <div class="row mb-4">
            <div class="col-12">
                <ul class="nav nav-pills nav-fill" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="email-tab" data-bs-toggle="pill" data-bs-target="#email" type="button" role="tab">
                            <i class="fas fa-envelope me-2"></i>Email Settings
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="geolocation-tab" data-bs-toggle="pill" data-bs-target="#geolocation" type="button" role="tab">
                            <i class="fas fa-map-marker-alt me-2"></i>Geolocation
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="file-upload-tab" data-bs-toggle="pill" data-bs-target="#file-upload" type="button" role="tab">
                            <i class="fas fa-upload me-2"></i>File Upload
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="system-tab" data-bs-toggle="pill" data-bs-target="#system" type="button" role="tab">
                            <i class="fas fa-cog me-2"></i>System
                        </button>
                    </li>
                </ul>
            </div>
        </div>

        <!-- Settings Content -->
        <div class="tab-content" id="settingsTabContent">
            <!-- Email Settings -->
            <div class="tab-pane fade show active" id="email" role="tabpanel">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-envelope me-2"></i>Email Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="settings.php?action=update">
                            <input type="hidden" name="category" value="email">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email_smtp_host" class="form-label">SMTP Host</label>
                                        <input type="text" class="form-control" id="email_smtp_host" name="email_smtp_host" 
                                               value="<?= htmlspecialchars($emailConfig['email_smtp_host'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email_smtp_port" class="form-label">SMTP Port</label>
                                        <input type="number" class="form-control" id="email_smtp_port" name="email_smtp_port" 
                                               value="<?= htmlspecialchars($emailConfig['email_smtp_port'] ?? '587') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email_smtp_username" class="form-label">SMTP Username</label>
                                        <input type="text" class="form-control" id="email_smtp_username" name="email_smtp_username" 
                                               value="<?= htmlspecialchars($emailConfig['email_smtp_username'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email_smtp_password" class="form-label">SMTP Password</label>
                                        <input type="password" class="form-control" id="email_smtp_password" name="email_smtp_password" 
                                               value="<?= htmlspecialchars($emailConfig['email_smtp_password'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email_from_address" class="form-label">From Email Address</label>
                                        <input type="email" class="form-control" id="email_from_address" name="email_from_address" 
                                               value="<?= htmlspecialchars($emailConfig['email_from_address'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email_from_name" class="form-label">From Name</label>
                                        <input type="text" class="form-control" id="email_from_name" name="email_from_name" 
                                               value="<?= htmlspecialchars($emailConfig['email_from_name'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="email_queue_enabled" name="email_queue_enabled" 
                                               <?= ($emailConfig['email_queue_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="email_queue_enabled">
                                            Enable Email Queue
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="email_queue_interval" class="form-label">Queue Processing Interval (minutes)</label>
                                        <input type="number" class="form-control" id="email_queue_interval" name="email_queue_interval" 
                                               value="<?= htmlspecialchars($emailConfig['email_queue_interval'] ?? '5') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save Email Settings
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="testEmail()">
                                    <i class="fas fa-paper-plane me-2"></i>Test Email
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Geolocation Settings -->
            <div class="tab-pane fade" id="geolocation" role="tabpanel">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-map-marker-alt me-2"></i>Geolocation Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="settings.php?action=update">
                            <input type="hidden" name="category" value="geolocation">
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="geolocation_enabled" name="geolocation_enabled" 
                                       <?= ($geolocationConfig['geolocation_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="geolocation_enabled">
                                    Enable Geolocation Features
                                </label>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="geofence_radius" class="form-label">Geofence Radius (meters)</label>
                                        <input type="number" class="form-control" id="geofence_radius" name="geofence_radius" 
                                               value="<?= htmlspecialchars($geolocationConfig['geofence_radius'] ?? '40') ?>">
                                        <div class="form-text">Default: 40 meters</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="gps_accuracy_threshold" class="form-label">GPS Accuracy Threshold (meters)</label>
                                        <input type="number" class="form-control" id="gps_accuracy_threshold" name="gps_accuracy_threshold" 
                                               value="<?= htmlspecialchars($geolocationConfig['gps_accuracy_threshold'] ?? '20') ?>">
                                        <div class="form-text">Maximum GPS accuracy to accept</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="location_timeout" class="form-label">Location Timeout (seconds)</label>
                                        <input type="number" class="form-control" id="location_timeout" name="location_timeout" 
                                               value="<?= htmlspecialchars($geolocationConfig['location_timeout'] ?? '30') ?>">
                                        <div class="form-text">Maximum time to wait for location</div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Geolocation Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- File Upload Settings -->
            <div class="tab-pane fade" id="file-upload" role="tabpanel">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-upload me-2"></i>File Upload Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="settings.php?action=update">
                            <input type="hidden" name="category" value="file_upload">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="file_upload_max_size" class="form-label">Maximum File Size (bytes)</label>
                                        <input type="number" class="form-control" id="file_upload_max_size" name="file_upload_max_size" 
                                               value="<?= htmlspecialchars($fileUploadConfig['file_upload_max_size'] ?? '10485760') ?>">
                                        <div class="form-text">10MB = 10485760 bytes</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="file_upload_allowed_types" class="form-label">Allowed File Types</label>
                                        <input type="text" class="form-control" id="file_upload_allowed_types" name="file_upload_allowed_types" 
                                               value="<?= htmlspecialchars($fileUploadConfig['file_upload_allowed_types'] ?? '') ?>">
                                        <div class="form-text">Comma-separated: pdf,doc,docx,jpg,jpeg,png</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="image_compression_enabled" name="image_compression_enabled" 
                                       <?= ($fileUploadConfig['image_compression_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="image_compression_enabled">
                                    Enable Automatic Image Compression
                                </label>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="image_compression_quality" class="form-label">Image Compression Quality</label>
                                        <input type="range" class="form-range" id="image_compression_quality" name="image_compression_quality" 
                                               min="1" max="100" value="<?= htmlspecialchars($fileUploadConfig['image_compression_quality'] ?? '80') ?>">
                                        <div class="form-text">Quality: <span id="quality-value"><?= htmlspecialchars($fileUploadConfig['image_compression_quality'] ?? '80') ?></span>%</div>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save File Upload Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- System Settings -->
            <div class="tab-pane fade" id="system" role="tabpanel">
                <div class="card settings-card">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i class="fas fa-cog me-2"></i>System Configuration
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="settings.php?action=update">
                            <input type="hidden" name="category" value="system">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="system_name" class="form-label">System Name</label>
                                        <input type="text" class="form-control" id="system_name" name="system_name" 
                                               value="<?= htmlspecialchars($systemConfig['system_name'] ?? 'OJT Route') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="ojt_required_hours" class="form-label">Required OJT Hours</label>
                                        <input type="number" class="form-control" id="ojt_required_hours" name="ojt_required_hours" 
                                               value="<?= htmlspecialchars($systemConfig['ojt_required_hours'] ?? '600') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="session_timeout" class="form-label">Session Timeout (seconds)</label>
                                        <input type="number" class="form-control" id="session_timeout" name="session_timeout" 
                                               value="<?= htmlspecialchars($systemConfig['session_timeout'] ?? '1800') ?>">
                                        <div class="form-text">30 minutes = 1800 seconds</div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="password_min_length" class="form-label">Minimum Password Length</label>
                                        <input type="number" class="form-control" id="password_min_length" name="password_min_length" 
                                               value="<?= htmlspecialchars($systemConfig['password_min_length'] ?? '8') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode" 
                                       <?= ($systemConfig['maintenance_mode'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="maintenance_mode">
                                    Enable Maintenance Mode
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="attendance_blocks_enabled" name="attendance_blocks_enabled" 
                                       <?= ($systemConfig['attendance_blocks_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="attendance_blocks_enabled">
                                    Enable Attendance Blocks (Morning/Afternoon)
                                </label>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="overtime_enabled" name="overtime_enabled" 
                                       <?= ($systemConfig['overtime_enabled'] ?? '0') === '1' ? 'checked' : '' ?>>
                                <label class="form-check-label" for="overtime_enabled">
                                    Enable Overtime Attendance Block
                                </label>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Save System Settings
                                </button>
                                <button type="button" class="btn btn-outline-warning" onclick="resetToDefaults()">
                                    <i class="fas fa-undo me-2"></i>Reset to Defaults
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Update quality value display
        document.getElementById('image_compression_quality').addEventListener('input', function() {
            document.getElementById('quality-value').textContent = this.value;
        });
        
        // Test email function
        function testEmail() {
            if (confirm('This will send a test email to verify the configuration. Continue?')) {
                window.location.href = 'settings.php?action=test_email';
            }
        }
        
        // Reset to defaults function
        function resetToDefaults() {
            if (confirm('This will reset all configuration to default values. This action cannot be undone. Continue?')) {
                window.location.href = 'settings.php?action=reset';
            }
        }
        
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

