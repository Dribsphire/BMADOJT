<?php
// Start output buffering to prevent HTML output before JSON
ob_start();

// Temporarily enable error reporting for debugging
if (isset($_POST['action'])) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('log_errors', 1);
}

session_start();

// Set timezone to Philippines (UTC+08:00)
date_default_timezone_set('Asia/Manila');

require_once '../../vendor/autoload.php';

use App\Controllers\AttendanceController;
use App\Middleware\AuthMiddleware;
use App\Middleware\AttendanceMiddleware;
use App\Services\AttendanceIntegrationService;
use App\Services\GeolocationService;

// Check authentication
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('student')) {
    $authMiddleware->redirectToUnauthorized();
}

// Check attendance access with integration
$attendanceMiddleware = new AttendanceMiddleware();
$attendanceIntegration = new AttendanceIntegrationService();

$accessCheck = $attendanceMiddleware->checkAttendanceAccess($_SESSION['user_id']);
if (!$accessCheck['can_access']) {
    // Log the access denial
    $attendanceMiddleware->logAttendanceActivity((int)$_SESSION['user_id'], 'access_denied', [
        'reason' => $accessCheck['reason'],
        'message' => $accessCheck['message']
    ]);
    
    // For AJAX requests, return JSON instead of redirecting
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'message' => $accessCheck['message'],
            'redirect' => $accessCheck['reason'] === 'document_compliance' 
                ? 'documents.php?compliance_required=1&message=' . urlencode($accessCheck['message'])
                : ($accessCheck['redirect_url'] ?? 'dashboard.php')
        ]);
        exit;
    }
    
    // For regular page requests, redirect
    if ($accessCheck['reason'] === 'document_compliance') {
        $_SESSION['error'] = $accessCheck['message'];
        header('Location: documents.php?compliance_required=1');
    } else {
        header('Location: ' . ($accessCheck['redirect_url'] ?? 'dashboard.php'));
    }
    exit;
}

// Handle AJAX requests first
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Clear all output buffers and suppress all output
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // Set proper headers
    header('Content-Type: application/json');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
    
    // Ensure no output before JSON
    ob_start();
    
    try {
        // Validate session for AJAX requests
        $sessionCheck = $attendanceMiddleware->validateAttendanceSession($_SESSION['user_id']);
        if (!$sessionCheck['valid']) {
            echo json_encode([
                'success' => false,
                'message' => $sessionCheck['message'],
                'redirect' => $sessionCheck['redirect_url'] ?? 'login.php'
            ]);
            exit;
        }

        // Check concurrent access
        $concurrentCheck = $attendanceMiddleware->checkConcurrentAccess($_SESSION['user_id'], $_POST['action']);
        if (!$concurrentCheck['allowed']) {
            echo json_encode([
                'success' => false,
                'message' => $concurrentCheck['message']
            ]);
            exit;
        }

        $controller = new AttendanceController();
        
        switch ($_POST['action']) {
            case 'time_in':
                $result = $attendanceMiddleware->handleAttendanceTransaction(function($pdo) use ($controller) {
                    return $controller->handleTimeIn();
                });
                // Clear any output buffer before sending JSON
                while (ob_get_level()) {
                    ob_end_clean();
                }
                echo json_encode($result);
                break;
                
            case 'time_out':
                $result = $attendanceMiddleware->handleAttendanceTransaction(function($pdo) use ($controller) {
                    return $controller->handleTimeOut();
                });
                // Clear any output buffer before sending JSON
                while (ob_get_level()) {
                    ob_end_clean();
                }
                echo json_encode($result);
                break;
                
            case 'check_location':
                // Check location radius
                $latitude = floatval($_POST['latitude'] ?? 0);
                $longitude = floatval($_POST['longitude'] ?? 0);
                
                $pdo = App\Utils\Database::getInstance();
                $geolocationService = new GeolocationService($pdo);
                $result = $geolocationService->verifyAttendanceLocation($_SESSION['user_id'], $latitude, $longitude);
                
                // Clear any output buffer before sending JSON
                while (ob_get_level()) {
                    ob_end_clean();
                }
                echo json_encode($result);
                break;
                
            default:
                // Clear any output buffer before sending JSON
                while (ob_get_level()) {
                    ob_end_clean();
                }
                echo json_encode(['success' => false, 'message' => 'Invalid action']);
        }
        
        // Log the action
        $attendanceMiddleware->logAttendanceActivity((int)$_SESSION['user_id'], $_POST['action'], [
            'timestamp' => date('Y-m-d H:i:s'),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
        
    } catch (Exception $e) {
        // Clear any output buffer before sending JSON
        while (ob_get_level()) {
            ob_end_clean();
        }
        $errorResult = [
            'success' => false,
            'message' => 'Server error: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
        echo json_encode($errorResult);
    } catch (Error $e) {
        // Clear any output buffer before sending JSON
        while (ob_get_level()) {
            ob_end_clean();
        }
        $errorResult = [
            'success' => false,
            'message' => 'Fatal error: ' . $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
        echo json_encode($errorResult);
    }
    exit;
}

$controller = new AttendanceController();
$data = $controller->showAttendancePage();

// Check for missing time-outs (forgot timeout notifications)
use App\Services\ForgotTimeoutService;
$forgotTimeoutService = new ForgotTimeoutService();
$missingTimeouts = $forgotTimeoutService->getAttendanceRecordsWithoutTimeout($_SESSION['user_id']);
$missingTimeoutCount = count($missingTimeouts);

// Handle compliance redirect
if (isset($data['error']) && $data['error'] === 'Document compliance required') {
    header('Location: documents.php?compliance_required=1');
    exit;
}

// Handle authentication redirect
if (isset($data['redirect'])) {
    header('Location: ' . $data['redirect']);
    exit;
}

// Handle errors
if (isset($data['error'])) {
    $error_message = $data['error'];
} else {
    $attendanceStatus = $data['attendance_status'];
    $dailySummary = $data['daily_summary'];
    $timeInfo = $data['time_info'];
    $compliance = $data['compliance'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance - OJT Management System</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <link rel="icon" type="image/png" href="../images/CHMSU.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --chmsu-green: #0ea539;
            --chmsu-dark: #0d8a32;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        
        .attendance-card {
            transition: all 0.3s ease;
            border: 2px solid transparent;
        }
        
        .attendance-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .block-morning {
            border-left: 4px solid #0d6efd;
        }
        
        .block-afternoon {
            border-left: 4px solid #ffc107;
        }
        
        .block-overtime {
            border-left: 4px solid #0dcaf0;
        }
        
        .status-not-started {
            background-color: #f8f9fa;
            color: #6c757d;
        }
        
        .status-time-in {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .status-completed {
            background-color: #e8f5e8;
            color: #2e7d32;
        }
        
        .time-display {
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .progress-ring {
            width: 60px;
            height: 60px;
        }
        
        .progress-ring circle {
            fill: transparent;
            stroke-width: 4;
        }
        
        .progress-ring .progress-circle {
            stroke: var(--chmsu-green);
            stroke-linecap: round;
            transition: stroke-dasharray 0.3s ease;
        }
        
        .progress-ring .background-circle {
            stroke: #e9ecef;
        }
        
        .btn-attendance {
            min-width: 120px;
            font-weight: 500;
        }
        
        .compliance-badge {
            font-size: 0.8rem;
        }
        
        @media (max-width: 768px) {
            .attendance-card {
                margin-bottom: 1rem;
            }
            
            .btn-attendance {
                width: 100%;
                margin-bottom: 0.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'student-sidebar.php'; ?>
    
    <main>
        <!-- Location Status -->
        <div class="row mb-3">
            <div class="col-12">
                <div id="locationStatus" class="alert alert-warning">
                    <i class="bi bi-geo-alt me-2"></i>
                    <span id="locationMessage">Checking your location...</span>
                    <button class="btn btn-sm btn-outline-primary ms-2" onclick="checkLocation()">
                        <i class="bi bi-arrow-clockwise"></i> Check Location
                    </button>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">
                <i class="bi bi-clock-history me-2"></i>Attendance
            </h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <button type="button" class="btn btn-outline-secondary" onclick="refreshStatus()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php else: ?>

        <!-- Current Time and Date -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body text-center">
                        <h5 class="card-title">
                            <i class="bi bi-calendar-event me-2"></i>Current Date & Time
                        </h5>
                        <div class="time-display text-primary" id="current-time">
                            <?= date('Y-m-d g:i:s A') ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Blocks -->
        <div class="row">
            <?php foreach ($attendanceStatus as $blockKey => $block): ?>
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="card attendance-card block-<?= $blockKey ?> status-<?= $block['status'] ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="bi bi-<?= $block['icon'] ?> me-2"></i>
                            <?= $block['name'] ?>
                        </h6>
                        <span class="badge bg-<?= $block['color'] ?>"><?= ucfirst($block['status']) ?></span>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <small class="text-muted">Time Range:</small><br>
                            <strong><?= date('g:i A', strtotime($block['start_time'])) ?> - <?= date('g:i A', strtotime($block['end_time'])) ?></strong>
                        </div>
                        
                        <?php if ($block['time_in']): ?>
                        <div class="mb-2">
                            <small class="text-muted">Time In:</small><br>
                            <strong class="text-primary"><?= date('g:i:s A', strtotime($block['time_in'])) ?></strong>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($block['time_out']): ?>
                        <div class="mb-2">
                            <small class="text-muted">Time Out:</small><br>
                            <strong class="text-success"><?= date('g:i:s A', strtotime($block['time_out'])) ?></strong>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($block['total_hours'] > 0): ?>
                        <div class="mb-3">
                            <small class="text-muted">Total Hours:</small><br>
                            <strong class="text-info"><?= number_format($block['total_hours'], 2) ?> hours</strong>
                        </div>
                        <?php endif; ?>
                        
                        <div class="d-grid gap-2">
                            <?php if ($block['can_time_in']): ?>
                            <button class="btn btn-primary btn-attendance" 
                                    onclick="timeIn('<?= $blockKey ?>')">
                                <i class="bi bi-play-circle me-2"></i>Time In
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($block['can_time_out']): ?>
                            <button class="btn btn-success btn-attendance" 
                                    onclick="showTimeOutConfirmation('<?= $blockKey ?>')">
                                <i class="bi bi-stop-circle me-2"></i>Time Out
                            </button>
                            <?php endif; ?>
                            
                            <?php if (!$block['can_time_in'] && !$block['can_time_out']): ?>
                            <button class="btn btn-secondary btn-attendance" disabled>
                                <i class="bi bi-clock me-2"></i>
                                <?= $block['status'] === 'completed' ? 'Completed' : 'Not Available' ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>



        <?php endif; ?>
    </main>

    <!-- Loading Modal -->
    <div class="modal fade" id="loadingModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-body text-center">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <div class="mt-2">Processing...</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Time Out Confirmation Modal -->
    <div class="modal fade" id="timeOutConfirmModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-question-circle me-2 text-warning"></i>Confirm Time Out
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="text-center mb-3">
                        <i class="bi bi-stop-circle text-success" style="font-size: 3rem;"></i>
                    </div>
                    <p class="text-center mb-3">
                        Are you sure you want to <strong>Time Out</strong> from your current work block?
                    </p>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2"></i>
                        <strong>Note:</strong> This action will record your time out and cannot be undone. 
                        Make sure you are at your designated workplace location.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-success" id="confirmTimeOutBtn">
                        <i class="bi bi-stop-circle me-2"></i>Yes, Time Out
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/photo-capture.js"></script>
    <script src="js/photo-modal.js"></script>
    <script>
        // Prevent double submissions
        let isProcessing = false;
        
        // Update current time every second
        function updateCurrentTime() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            const displayHours = hours % 12 || 12;
            const timeString = `${year}-${month}-${day} ${displayHours}:${minutes}:${seconds} ${ampm}`;
            document.getElementById('current-time').textContent = timeString;
        }
        
        setInterval(updateCurrentTime, 1000);
        
        // Check location on page load
        document.addEventListener('DOMContentLoaded', function() {
            checkLocation();
        });

        // Get current GPS location
        function getCurrentLocation() {
            return new Promise((resolve, reject) => {
                if (!navigator.geolocation) {
                    reject(new Error('Geolocation is not supported by this browser.'));
                    return;
                }
                
                navigator.geolocation.getCurrentPosition(
                    (position) => {
                        resolve({
                            latitude: position.coords.latitude,
                            longitude: position.coords.longitude
                        });
                    },
                    (error) => {
                        reject(error);
                    },
                    {
                        enableHighAccuracy: true,
                        timeout: 10000,
                        maximumAge: 0
                    }
                );
            });
        }

        // Check location status
        async function checkLocation() {
            const statusDiv = document.getElementById('locationStatus');
            const messageSpan = document.getElementById('locationMessage');
            const checkBtn = statusDiv.querySelector('button');
            
            statusDiv.className = 'alert alert-info';
            messageSpan.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Checking your location...';
            checkBtn.disabled = true;
            checkBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Checking...';
            
            try {
                const location = await getCurrentLocation();
                
                const response = await fetch('attendance.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=check_location&latitude=${location.latitude}&longitude=${location.longitude}`
                });
                
                const result = await response.json();
                
                if (result.valid) {
                    statusDiv.className = 'alert alert-success';
                    messageSpan.innerHTML = `<i class="bi bi-check-circle me-1"></i>${result.message}`;
                } else {
                    statusDiv.className = 'alert alert-danger';
                    messageSpan.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>${result.message}`;
                }
            } catch (error) {
                statusDiv.className = 'alert alert-warning';
                messageSpan.innerHTML = `<i class="bi bi-exclamation-triangle me-1"></i>Location check failed. Please try again.`;
            } finally {
                checkBtn.disabled = false;
                checkBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Check Location';
            }
        }

        // Time In function with photo capture
        async function timeIn(blockType) {
            if (isProcessing) {
                showAlert('warning', 'Please wait, processing previous request...');
                return;
            }
            
            // Show photo capture modal
            const photoModal = new PhotoModal();
            photoModal.show(blockType, async (photoData) => {
                await processTimeIn(blockType, photoData);
            }, () => {
                // User cancelled photo capture
                showAlert('info', 'Time-in cancelled');
            });
        }

        // Process time-in with photo data
        async function processTimeIn(blockType, photoData) {
            if (isProcessing) {
                showAlert('warning', 'Please wait, processing previous request...');
                return;
            }
            
            isProcessing = true;
            const modal = new bootstrap.Modal(document.getElementById('loadingModal'));
            modal.show();
            
            try {
                const gpsLocation = await getCurrentLocation();
                
                const formData = new FormData();
                formData.append('action', 'time_in');
                formData.append('block_type', blockType);
                formData.append('latitude', gpsLocation.latitude);
                formData.append('longitude', gpsLocation.longitude);
                
                // Add photo data if available
                if (photoData) {
                    formData.append('photo_data', photoData);
                }
                
                const response = await fetch('attendance.php', {
                    method: 'POST',
                    body: formData
                });
                
                let result;
                try {
                    const responseText = await response.text();
                    console.log('Raw response text:', responseText);
                    result = JSON.parse(responseText);
                    console.log('Time-in response:', result);
                } catch (jsonError) {
                    console.error('JSON parsing error:', jsonError);
                    console.log('Raw response text:', await response.text());
                    modal.hide();
                    showAlert('danger', 'Server returned invalid response. Please try again.');
                    isProcessing = false;
                    return;
                }
                
                modal.hide();
                
                if (result.success) {
                    const message = photoData ? 
                        result.message + ' (Photo captured)' : 
                        result.message + ' (No photo)';
                    showAlert('success', message);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert('danger', result.message);
                }
                
                isProcessing = false;
                
            } catch (error) {
                modal.hide();
                console.error('Time-in error:', error);
                
                // Show specific error message based on error type
                if (error.name === 'GeolocationPositionError') {
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            showAlert('danger', 'Location access denied. Please enable location services and try again.');
                            break;
                        case error.POSITION_UNAVAILABLE:
                            showAlert('danger', 'Location information unavailable. Please check your GPS settings and try again.');
                            break;
                        case error.TIMEOUT:
                            showAlert('danger', 'Location request timed out. Please try again.');
                            break;
                        default:
                            showAlert('danger', 'Failed to get location. Please enable location services and try again.');
                    }
                    } else {
                    showAlert('danger', 'Failed to record attendance. Please try again.');
                }
                
                isProcessing = false;
            }
        }

        // Time Out function
        async function timeOut(blockType) {
            const modal = new bootstrap.Modal(document.getElementById('loadingModal'));
            modal.show();
            
            try {
                const gpsLocation = await getCurrentLocation();
                
                const formData = new FormData();
                formData.append('action', 'time_out');
                formData.append('block_type', blockType);
                formData.append('latitude', gpsLocation.latitude);
                formData.append('longitude', gpsLocation.longitude);
                
                const response = await fetch('attendance.php', {
                    method: 'POST',
                    body: formData
                });
                
                let result;
                try {
                    const responseText = await response.text();
                    console.log('Raw response text:', responseText);
                    result = JSON.parse(responseText);
                    console.log('Time-out response:', result);
                } catch (jsonError) {
                    console.error('JSON parsing error:', jsonError);
                    console.log('Raw response text:', await response.text());
                    modal.hide();
                    showAlert('danger', 'Server returned invalid response. Please try again.');
                    return;
                }
                
                modal.hide();
                
                if (result.success) {
                    showAlert('success', result.message);
                    setTimeout(() => window.location.reload(), 1500);
                } else {
                    showAlert('warning', result.message);
                    // Refresh page to show current status even if time-out failed
                    setTimeout(() => window.location.reload(), 2000);
                }
                
            } catch (error) {
                modal.hide();
                console.error('Time-out error:', error);
                
                // Show specific error message based on error type
                if (error.name === 'GeolocationPositionError') {
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            showAlert('danger', 'Location access denied. Please enable location services and try again.');
                            break;
                        case error.POSITION_UNAVAILABLE:
                            showAlert('danger', 'Location information unavailable. Please check your GPS settings and try again.');
                            break;
                        case error.TIMEOUT:
                            showAlert('danger', 'Location request timed out. Please try again.');
                            break;
                        default:
                            showAlert('danger', 'Failed to get location. Please enable location services and try again.');
                    }
                    } else {
                    showAlert('danger', 'Failed to record attendance. Please try again.');
                }
            }
        }

        // Refresh status
        function refreshStatus() {
            window.location.reload();
        }

        // Show alert
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                <i class="bi bi-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.querySelector('main').insertBefore(alertDiv, document.querySelector('main').firstChild);
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Handle AJAX requests
        if (window.location.search.includes('ajax=1')) {
            // Handle AJAX requests for status updates
            document.addEventListener('DOMContentLoaded', function() {
                fetch('attendance.php?action=get_status')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Update page with new data
                            console.log('Status updated:', data);
                        }
                    })
                    .catch(error => console.error('Error:', error));
            });
        }

        // Global variable to store the current block type for time out
        let currentTimeOutBlockType = null;

        // Show time out confirmation modal
        function showTimeOutConfirmation(blockType) {
            currentTimeOutBlockType = blockType;
            const modal = new bootstrap.Modal(document.getElementById('timeOutConfirmModal'));
            modal.show();
        }

        // Handle confirmed time out
        document.getElementById('confirmTimeOutBtn').addEventListener('click', function() {
            if (currentTimeOutBlockType) {
                // Hide the confirmation modal
                const confirmModal = bootstrap.Modal.getInstance(document.getElementById('timeOutConfirmModal'));
                confirmModal.hide();
                
                // Call the original timeOut function
                timeOut(currentTimeOutBlockType);
                currentTimeOutBlockType = null;
            }
        });
    </script>
</body>
</html>

<?php
// Handle AJAX status requests
if (isset($_GET['action']) && $_GET['action'] === 'get_status') {
    header('Content-Type: application/json');
    echo json_encode($controller->getAttendanceStatus());
    exit;
}
?>