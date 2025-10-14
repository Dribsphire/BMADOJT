<?php

/**
 * Student Forgot Time-Out Request Page
 * OJT Route - Forgot Time-Out Request System
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Services\ForgotTimeoutService;

// Start session
session_start();

// Set timezone to Philippines (UTC+08:00)
date_default_timezone_set('Asia/Manila');

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication and authorization
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('student')) {
    $authMiddleware->redirectToUnauthorized();
}

// Get current user
$user = $authMiddleware->getCurrentUser();

// Initialize forgot timeout service
$forgotTimeoutService = new ForgotTimeoutService();

// Get attendance records without timeout
$attendanceRecords = $forgotTimeoutService->getAttendanceRecordsWithoutTimeout($user->id);

// Get request statistics
$stats = $forgotTimeoutService->getRequestStats($user->id);

// Get existing requests
$existingRequests = $forgotTimeoutService->getStudentRequests($user->id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Time-Out Request - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <script type="text/javascript" src="../js/sidebarSlide.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --chmsu-green: #2d5016;
            --chmsu-light-green: #4a7c59;
        }
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        
        .navbar-brand {
            font-weight: bold;
            color: var(--chmsu-green) !important;
        }
        
        .btn-primary {
            background-color: var(--chmsu-green);
            border-color: var(--chmsu-green);
        }
        
        .btn-primary:hover {
            background-color: var(--chmsu-light-green);
            border-color: var(--chmsu-light-green);
        }
        
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .stats-card {
            background: linear-gradient(135deg, var(--chmsu-green), var(--chmsu-light-green));
            color: white;
        }
        
        .form-control:focus {
            border-color: var(--chmsu-green);
            box-shadow: 0 0 0 0.2rem rgba(45, 80, 22, 0.25);
        }
        
        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 0.5rem;
            padding: 2rem;
            text-align: center;
            transition: border-color 0.3s;
        }
        
        .file-upload-area:hover {
            border-color: var(--chmsu-green);
        }
        
        .file-upload-area.dragover {
            border-color: var(--chmsu-green);
            background-color: rgba(45, 80, 22, 0.05);
        }
        
        .request-item {
            border-left: 4px solid #dee2e6;
            transition: all 0.3s;
        }
        
        .request-item.pending {
            border-left-color: #ffc107;
        }
        
        .request-item.approved {
            border-left-color: #28a745;
        }
        
        .request-item.rejected {
            border-left-color: #dc3545;
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body>
    
    <?php include 'student-sidebar.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div>
                        <h2 class="mb-1">Forgot Time-Out Request</h2>
                        <p class="text-muted mb-0">Submit a request for approval when you forgot to time-out</p>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="bi bi-clock-history fs-1 mb-2"></i>
                                <h4 class="mb-1"><?= $stats['pending'] ?></h4>
                                <small>Pending Requests</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="bi bi-check-circle fs-1 mb-2"></i>
                                <h4 class="mb-1"><?= $stats['approved'] ?></h4>
                                <small>Approved</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="bi bi-x-circle fs-1 mb-2"></i>
                                <h4 class="mb-1"><?= $stats['rejected'] ?></h4>
                                <small>Rejected</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card stats-card">
                            <div class="card-body text-center">
                                <i class="bi bi-list-ul fs-1 mb-2"></i>
                                <h4 class="mb-1"><?= $stats['total'] ?></h4>
                                <small>Total Requests</small>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- New Request Form -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-plus-circle me-2"></i>Submit New Request
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($attendanceRecords)): ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-check-circle fs-1 text-success mb-3"></i>
                                        <h5>No Missing Time-Outs</h5>
                                        <p class="text-muted">All your recent attendance records have been completed with time-outs.</p>
                                    </div>
                                <?php else: ?>
                                    <form id="forgotTimeoutForm" enctype="multipart/form-data">
                                        <div class="mb-3">
                                            <label for="attendance_record" class="form-label">Select Attendance Record</label>
                                            <select class="form-select" id="attendance_record" name="attendance_record_id" required>
                                                <option value="">Choose an attendance record...</option>
                                                <?php foreach ($attendanceRecords as $record): ?>
                                                    <option value="<?= $record['id'] ?>" 
                                                            data-date="<?= $record['date'] ?>" 
                                                            data-block="<?= $record['block_type'] ?>"
                                                            data-time-in="<?= $record['time_in'] ?>">
                                                        <?php 
                                                        $recordDate = DateTime::createFromFormat('Y-m-d', $record['date']);
                                                        $timeInDate = DateTime::createFromFormat('Y-m-d H:i:s', $record['time_in']);
                                                        echo $recordDate ? $recordDate->format('M j, Y') : $record['date'];
                                                        ?> - 
                                                        <?= ucfirst($record['block_type']) ?> Block
                                                        (Time-in: <?= $timeInDate ? $timeInDate->format('g:i A') : $record['time_in'] ?>)
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="reason" class="form-label">Reason for Forgot Time-Out</label>
                                            <textarea class="form-control" id="reason" name="reason" rows="3" 
                                                      placeholder="Please explain why you forgot to time-out..." required></textarea>
                                        </div>

                                        <div class="mb-3">
                                            <label for="letter_file" class="form-label">Explanation Letter</label>
                                            <div class="file-upload-area" id="fileUploadArea">
                                                <i class="bi bi-cloud-upload fs-1 text-muted mb-3"></i>
                                                <h6>Upload Explanation Letter</h6>
                                                <p class="text-muted mb-3">Drag and drop your letter here or click to browse</p>
                                                <input type="file" class="form-control d-none" id="letter_file" name="letter_file" 
                                                       accept=".pdf,.docx,.doc" required>
                                                <button type="button" class="btn btn-outline-primary" onclick="document.getElementById('letter_file').click()">
                                                    <i class="bi bi-upload me-1"></i>Choose File
                                                </button>
                                                <div class="mt-2">
                                                    <small class="text-muted">Accepted formats: PDF, DOCX, DOC (Max: 5MB)</small>
                                                </div>
                                            </div>
                                            <div id="fileInfo" class="mt-2" style="display: none;">
                                                <div class="alert alert-info">
                                                    <i class="bi bi-file-earmark me-2"></i>
                                                    <span id="fileName"></span>
                                                    <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="clearFile()">
                                                        <i class="bi bi-x"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="bi bi-send me-2"></i>Submit Request
                                            </button>
                                        </div>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Request History -->
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="bi bi-clock-history me-2"></i>Request History
                                </h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($existingRequests)): ?>
                                    <div class="text-center py-4">
                                        <i class="bi bi-inbox fs-1 text-muted mb-3"></i>
                                        <h6>No Requests Yet</h6>
                                        <p class="text-muted">Your submitted requests will appear here.</p>
                                    </div>
                                <?php else: ?>
                                    <div class="list-group list-group-flush">
                                        <?php foreach ($existingRequests as $request): ?>
                                            <div class="list-group-item request-item <?= $request['status'] ?>">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div class="flex-grow-1">
                                                        <div class="d-flex align-items-center mb-2">
                                                            <h6 class="mb-0 me-2">
                                                                <?php 
                                                                $requestDate = DateTime::createFromFormat('Y-m-d', $request['attendance_date']);
                                                                echo $requestDate ? $requestDate->format('M j, Y') : $request['attendance_date'];
                                                                ?> - 
                                                                <?= ucfirst($request['block_type']) ?> Block
                                                            </h6>
                                                            <span class="badge status-badge bg-<?= $request['status'] === 'pending' ? 'warning' : ($request['status'] === 'approved' ? 'success' : 'danger') ?>">
                                                                <?= ucfirst($request['status']) ?>
                                                            </span>
                                                        </div>
                                                        <small class="text-muted">
                                                            Submitted: <?= date('M j, Y g:i A', strtotime($request['created_at'])) ?>
                                                        </small>
                                                        <?php if ($request['reviewed_at']): ?>
                                                            <br><small class="text-muted">
                                                                Reviewed: <?= date('M j, Y g:i A', strtotime($request['reviewed_at'])) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                        <?php if ($request['instructor_response']): ?>
                                                            <div class="mt-2">
                                                                <strong>Instructor Response:</strong>
                                                                <p class="mb-0 mt-1"><?= htmlspecialchars($request['instructor_response']) ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div class="btn-group-vertical">
                                                        <button class="btn btn-sm btn-outline-primary" 
                                                                onclick="viewRequest(<?= $request['id'] ?>)">
                                                            <i class="bi bi-eye"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary" 
                                                                onclick="downloadLetter(<?= $request['id'] ?>)">
                                                            <i class="bi bi-download"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Request Details Modal -->
    <div class="modal fade" id="requestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Request Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="requestDetails">
                    <!-- Request details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // File upload handling
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('letter_file');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');

        // Drag and drop functionality
        fileUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUploadArea.classList.add('dragover');
        });

        fileUploadArea.addEventListener('dragleave', () => {
            fileUploadArea.classList.remove('dragover');
        });

        fileUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                showFileInfo();
            }
        });

        fileInput.addEventListener('change', showFileInfo);

        function showFileInfo() {
            const file = fileInput.files[0];
            if (file) {
                fileName.textContent = file.name;
                fileInfo.style.display = 'block';
            }
        }

        function clearFile() {
            fileInput.value = '';
            fileInfo.style.display = 'none';
        }

        // Form submission
        document.getElementById('forgotTimeoutForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            
            try {
                const response = await fetch('forgot_timeout_submit.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Request submitted successfully!');
                    location.reload();
                } else {
                    alert('Error: ' + result.error);
                }
            } catch (error) {
                alert('An error occurred while submitting the request.');
                console.error('Error:', error);
            }
        });

        // View request details
        function viewRequest(requestId) {
            fetch(`forgot_timeout_submit.php?action=get_request&id=${requestId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('requestDetails').innerHTML = `
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Request Information</h6>
                                    <p><strong>Date:</strong> ${data.request.attendance_date}</p>
                                    <p><strong>Block Type:</strong> ${data.request.block_type}</p>
                                    <p><strong>Status:</strong> <span class="badge bg-${data.request.status === 'pending' ? 'warning' : (data.request.status === 'approved' ? 'success' : 'danger')}">${data.request.status}</span></p>
                                    <p><strong>Submitted:</strong> ${new Date(data.request.created_at).toLocaleString()}</p>
                                </div>
                                <div class="col-md-6">
                                    <h6>Actions</h6>
                                    <button class="btn btn-outline-primary btn-sm" onclick="downloadLetter(${data.request.id})">
                                        <i class="bi bi-download me-1"></i>Download Letter
                                    </button>
                                </div>
                            </div>
                            ${data.request.instructor_response ? `
                                <div class="mt-3">
                                    <h6>Instructor Response</h6>
                                    <div class="alert alert-info">
                                        ${data.request.instructor_response}
                                    </div>
                                </div>
                            ` : ''}
                        `;
                        
                        new bootstrap.Modal(document.getElementById('requestModal')).show();
                    } else {
                        alert('Error loading request details');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while loading request details');
                });
        }

        // Download letter
        function downloadLetter(requestId) {
            window.open(`forgot_timeout_submit.php?action=download_letter&id=${requestId}`, '_blank');
        }
    </script>
</body>
</html>
