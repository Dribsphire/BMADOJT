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
                
                // Get workplace location for map
                $stmt = $pdo->prepare("
                    SELECT sp.workplace_latitude, sp.workplace_longitude, sp.workplace_name
                    FROM student_profiles sp
                    WHERE sp.user_id = ? AND sp.workplace_latitude IS NOT NULL AND sp.workplace_longitude IS NOT NULL
                ");
                $stmt->execute([$_SESSION['user_id']]);
                $workplace = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($workplace) {
                    $result['workplace'] = [
                        'latitude' => (float)$workplace['workplace_latitude'],
                        'longitude' => (float)$workplace['workplace_longitude'],
                        'name' => $workplace['workplace_name']
                    ];
                    $result['radius'] = 40; // Default radius in meters
                }
                
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
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
        
        /* Map Styles */
        #locationMap {
            height: 500px;
            width: 100%;
            border-radius: 8px;
            z-index: 1;
        }
        
        .map-container {
            position: relative;
            margin-bottom: 1rem;
        }
        
        .map-info {
            position: absolute;
            top: 10px;
            right: 10px;
            background: white;
            padding: 10px;
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            z-index: 1000;
            font-size: 0.85rem;
        }
        
        .map-info .badge {
            font-size: 0.75rem;
        }
        
        /* Attendance Cards Overlay */
        .attendance-cards-overlay {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 320px;
            max-height: calc(100% - 20px);
            overflow-y: auto;
            z-index: 1000;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            padding: 1rem;
        }
        
        .attendance-cards-overlay::-webkit-scrollbar {
            width: 6px;
        }
        
        .attendance-cards-overlay::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 3px;
        }
        
        .attendance-cards-overlay::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 3px;
        }
        
        .attendance-cards-overlay::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        
        .attendance-cards-overlay .attendance-card {
            margin-bottom: 1rem;
        }
        
        .attendance-cards-overlay .attendance-card:last-child {
            margin-bottom: 0;
        }
        
        @media (max-width: 768px) {
            .attendance-cards-overlay {
                position: relative;
                width: 100%;
                max-height: none;
                top: 0;
                left: 0;
                margin-top: 1rem;
                box-shadow: none;
                border-radius: 0;
                padding: 0.5rem;
            }
            
            #locationMap {
                height: 400px;
            }
        }
        
        .location-status-card {
            border-left: 4px solid;
        }
        
        .location-status-card.valid {
            border-left-color: #28a745;
        }
        
        .location-status-card.invalid {
            border-left-color: #dc3545;
        }
        
        .location-status-card.checking {
            border-left-color: #ffc107;
        }
        
        /* Modern Alert Styles */
        .location-alert {
            border: none;
            border-radius: 12px;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
        }
        
        .location-alert.success {
            background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
            color: #155724;
        }
        
        .location-alert.danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
            color: #721c24;
        }
        
        .location-alert.warning {
            background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
            color: #856404;
        }
        
        .location-alert.info {
            background: linear-gradient(135deg, #d1ecf1 0%, #bee5eb 100%);
            color: #0c5460;
        }
        
        .location-alert .alert-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .location-alert .alert-content {
            flex: 1;
            font-weight: 500;
        }
        
        .location-alert .alert-distance {
            font-weight: 600;
            font-size: 0.95rem;
            margin-top: 0.25rem;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include 'student-sidebar.php'; ?>
    
    <main>
        <!-- Location Status and Map -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card location-status-card checking" id="locationStatusCard">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                        <div id="locationStatus" class="location-alert warning mb-3">
                            <i class="bi bi-hourglass-split alert-icon"></i>
                            <div class="alert-content">
                                <div id="locationMessage">Checking your location...</div>
                                <div id="locationDistance" class="alert-distance" style="display: none;"></div>
                            </div>
                        </div>
                        </h6>
                    </div>
                    <div class="card-body">
                        
                        <br>
                        <!-- Map Container -->
                        <div class="map-container">
                            <div id="locationMap"></div>
                            <div class="map-info" id="mapInfo" style="display: none;">
                                <div class="mb-2">
                                    <strong>Distance:</strong> 
                                    <span id="distanceDisplay" class="badge bg-secondary">-</span>
                                </div>
                                <div>
                                    <strong>Status:</strong> 
                                    <span id="statusBadge" class="badge bg-warning">Checking...</span>
                                </div>
                            </div>
                            
                            <!-- Attendance Cards Overlay -->
                            <div class="attendance-cards-overlay">
                                <?php if (!isset($error_message)): ?>
                                    <!-- Current Time -->
                                    <div class="card mb-3">
                                        <div class="card-body text-center p-2">
                                            <small class="text-muted d-block mb-1">
                                                <i class="bi bi-calendar-event"></i> Current Time
                                            </small>
                                            <div class="time-display text-primary" id="current-time" style="font-size: 0.9rem;">
                                                <?= date('Y-m-d g:i:s A') ?>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Attendance Blocks -->
                                    <?php foreach ($attendanceStatus as $blockKey => $block): ?>
                                    <div class="card attendance-card block-<?= $blockKey ?> status-<?= $block['status'] ?>">
                                        <div class="card-header d-flex justify-content-between align-items-center p-2">
                                            <h6 class="mb-0" style="font-size: 0.85rem;">
                                                <i class="bi bi-<?= $block['icon'] ?> me-1"></i>
                                                <?= $block['name'] ?>
                                            </h6>
                                            <span class="badge bg-<?= $block['color'] ?>" style="font-size: 0.7rem;"><?= ucfirst($block['status']) ?></span>
                                        </div>
                                        <div class="card-body p-2">
                                            <div class="mb-2">
                                                <small class="text-muted" style="font-size: 0.7rem;">Time Range:</small><br>
                                                <strong style="font-size: 0.8rem;"><?= date('g:i A', strtotime($block['start_time'])) ?> - <?= date('g:i A', strtotime($block['end_time'])) ?></strong>
                                            </div>
                                            
                                            <?php if ($block['time_in']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted" style="font-size: 0.7rem;">Time In:</small><br>
                                                <strong class="text-primary" style="font-size: 0.8rem;"><?= date('g:i:s A', strtotime($block['time_in'])) ?></strong>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($block['time_out']): ?>
                                            <div class="mb-2">
                                                <small class="text-muted" style="font-size: 0.7rem;">Time Out:</small><br>
                                                <strong class="text-success" style="font-size: 0.8rem;"><?= date('g:i:s A', strtotime($block['time_out'])) ?></strong>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($block['total_hours'] > 0): ?>
                                            <div class="mb-2">
                                                <small class="text-muted" style="font-size: 0.7rem;">Hours:</small><br>
                                                <strong class="text-info" style="font-size: 0.8rem;"><?= number_format($block['total_hours'], 2) ?>h</strong>
                                            </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-grid gap-1 mt-2">
                                                <?php if ($block['can_time_in']): ?>
                                                <button class="btn btn-primary btn-sm btn-attendance" 
                                                        onclick="timeIn('<?= $blockKey ?>')"
                                                        style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                                    <i class="bi bi-play-circle me-1"></i>Time In
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($block['can_time_out']): ?>
                                                <button class="btn btn-success btn-sm btn-attendance" 
                                                        onclick="showTimeOutConfirmation('<?= $blockKey ?>')"
                                                        style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                                    <i class="bi bi-stop-circle me-1"></i>Time Out
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if (!$block['can_time_in'] && !$block['can_time_out']): ?>
                                                <button class="btn btn-secondary btn-sm btn-attendance" disabled
                                                        style="font-size: 0.75rem; padding: 0.25rem 0.5rem;">
                                                    <i class="bi bi-clock me-1"></i>
                                                    <?= $block['status'] === 'completed' ? 'Completed' : 'Not Available' ?>
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <?= htmlspecialchars($error_message) ?>
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
    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
        // Prevent double submissions
        let isProcessing = false;
        
        // Map variables
        let map = null;
        let currentMarker = null;
        let workplaceMarker = null;
        let radiusCircle = null;
        let currentLocation = null;
        let workplaceLocation = null;
        let isWithinRadius = false;
        
        // Initialize map
        function initMap() {
            // Create map centered on Philippines
            map = L.map('locationMap').setView([14.5995, 120.9842], 15);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);
        }
        
        // Update map with locations
        function updateMap(studentLat, studentLon, workplaceLat, workplaceLon, distance, valid, workplaceName) {
            if (!map) {
                initMap();
            }
            
            // Remove existing markers and circle
            if (currentMarker) map.removeLayer(currentMarker);
            if (workplaceMarker) map.removeLayer(workplaceMarker);
            if (radiusCircle) map.removeLayer(radiusCircle);
            
            // Add workplace marker
            if (workplaceLat && workplaceLon) {
                workplaceLocation = { lat: workplaceLat, lon: workplaceLon };
                
                // Create red icon for workplace
                const redIcon = L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="background-color: #dc3545; width: 30px; height: 30px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 30],
                    popupAnchor: [0, -30]
                });
                
                workplaceMarker = L.marker([workplaceLat, workplaceLon], { icon: redIcon }).addTo(map);
                workplaceMarker.bindPopup(`<strong>${workplaceName || 'Workplace'}</strong><br>Your designated workplace location`);
                
                // Add radius circle (40 meters)
                radiusCircle = L.circle([workplaceLat, workplaceLon], {
                    color: valid ? '#28a745' : '#dc3545',
                    fillColor: valid ? '#28a745' : '#dc3545',
                    fillOpacity: 0.2,
                    radius: 40,
                    weight: 2
                }).addTo(map);
            }
            
            // Add student's current location marker
            if (studentLat && studentLon) {
                currentLocation = { lat: studentLat, lon: studentLon };
                
                // Create colored icon based on validity
                const markerColor = valid ? '#28a745' : '#dc3545';
                const currentIcon = L.divIcon({
                    className: 'custom-marker',
                    html: `<div style="background-color: ${markerColor}; width: 30px; height: 30px; border-radius: 50% 50% 50% 0; transform: rotate(-45deg); border: 3px solid white; box-shadow: 0 2px 5px rgba(0,0,0,0.3);"></div>`,
                    iconSize: [30, 30],
                    iconAnchor: [15, 30],
                    popupAnchor: [0, -30]
                });
                
                currentMarker = L.marker([studentLat, studentLon], { icon: currentIcon }).addTo(map);
                currentMarker.bindPopup(`<strong>Your Current Location</strong><br>Distance: ${distance ? distance.toFixed(1) : 'N/A'}m`);
                
                // Fit map to show both markers
                if (workplaceLat && workplaceLon) {
                    const group = new L.featureGroup([currentMarker, workplaceMarker, radiusCircle]);
                    map.fitBounds(group.getBounds().pad(0.2));
                } else {
                    map.setView([studentLat, studentLon], 18);
                }
            }
            
            // Update map info
            const mapInfo = document.getElementById('mapInfo');
            const distanceDisplay = document.getElementById('distanceDisplay');
            const statusBadge = document.getElementById('statusBadge');
            
            if (distance !== null && distance !== undefined) {
                distanceDisplay.textContent = distance.toFixed(1) + 'm';
                mapInfo.style.display = 'block';
                
                if (valid) {
                    statusBadge.className = 'badge bg-success';
                    statusBadge.textContent = 'Within Radius';
                } else {
                    statusBadge.className = 'badge bg-danger';
                    statusBadge.textContent = 'Outside Radius';
                }
            }
        }
        
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
            
            // Update all current-time elements (in overlay and elsewhere)
            const timeElements = document.querySelectorAll('#current-time');
            timeElements.forEach(el => {
                el.textContent = timeString;
            });
        }
        
        setInterval(updateCurrentTime, 1000);
        
        // Check location on page load
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
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
            const distanceSpan = document.getElementById('locationDistance');
            const statusCard = document.getElementById('locationStatusCard');
            const checkBtn = document.querySelector('#locationStatusCard .btn');
            
            statusDiv.className = 'location-alert info';
            statusCard.className = 'card location-status-card checking';
            statusDiv.querySelector('.alert-icon').className = 'bi bi-hourglass-split alert-icon';
            messageSpan.textContent = 'Checking your location...';
            distanceSpan.style.display = 'none';
            if (checkBtn) {
                checkBtn.disabled = true;
                checkBtn.innerHTML = '<i class="bi bi-hourglass-split"></i> Checking...';
            }
            
            try {
                const location = await getCurrentLocation();
                
                const response = await fetch('attendance.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=check_location&latitude=${location.latitude}&longitude=${location.longitude}`
                });
                
                const result = await response.json();
                
                isWithinRadius = result.valid;
                
                // Extract distance from result
                const distance = result.distance || (result.message.match(/\((\d+\.?\d*)m\)/) ? parseFloat(result.message.match(/\((\d+\.?\d*)m\)/)[1]) : null);
                
                // Update status display
                if (result.valid) {
                    statusDiv.className = 'location-alert success';
                    statusCard.className = 'card location-status-card valid';
                    statusDiv.querySelector('.alert-icon').className = 'bi bi-check-circle-fill alert-icon';
                    messageSpan.textContent = 'Location verified. You are within the workplace radius.';
                    if (distance !== null) {
                        distanceSpan.textContent = `Distance: ${distance.toFixed(1)}m`;
                        distanceSpan.style.display = 'block';
                    }
                } else {
                    statusDiv.className = 'location-alert danger';
                    statusCard.className = 'card location-status-card invalid';
                    statusDiv.querySelector('.alert-icon').className = 'bi bi-exclamation-triangle-fill alert-icon';
                    messageSpan.textContent = 'Location is too far from the workplace radius.';
                    if (distance !== null) {
                        distanceSpan.textContent = `Distance: ${distance.toFixed(1)}m`;
                        distanceSpan.style.display = 'block';
                    }
                }
                
                // Update map if workplace location is available
                if (result.workplace) {
                    updateMap(
                        location.latitude,
                        location.longitude,
                        result.workplace.latitude,
                        result.workplace.longitude,
                        result.distance,
                        result.valid,
                        result.workplace.name
                    );
                } else {
                    // Just show student location if no workplace
                    updateMap(
                        location.latitude,
                        location.longitude,
                        null,
                        null,
                        result.distance,
                        result.valid,
                        null
                    );
                }
                
            } catch (error) {
                statusDiv.className = 'location-alert warning';
                statusCard.className = 'card location-status-card checking';
                statusDiv.querySelector('.alert-icon').className = 'bi bi-exclamation-triangle-fill alert-icon';
                messageSpan.textContent = `Location check failed: ${error.message}. Please try again.`;
                distanceSpan.style.display = 'none';
            } finally {
                checkBtn.disabled = false;
                checkBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> time in/out';
            }
        }

        // Time In function with photo capture
        async function timeIn(blockType) {
            if (isProcessing) {
                showAlert('warning', 'Please wait, processing previous request...');
                return;
            }
            
            // Check if location is verified first
            if (!isWithinRadius) {
                showAlert('danger', 'You must be within the workplace radius to time in. Please check your location.');
                checkLocation(); // Refresh location check
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