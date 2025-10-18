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

// Create a simple map of attendance record IDs to request status
$requestStatusMap = [];
foreach ($existingRequests as $request) {
    if (isset($request['attendance_record_id'])) {
        $requestStatusMap[$request['attendance_record_id']] = $request['status'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Time-Out Request - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <link rel="icon" type="image/png" href="../images/CHMSU.png">
    <script type="text/javascript" src="../js/sidebarSlide.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --chmsu-green: #0ea539;
            --chmsu-light-green:rgb(14, 192, 64);
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
        
        /* Modern Missed Block Cards */
        .missed-block-card {
            border: 2px solid transparent;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .missed-block-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            border-color: var(--chmsu-green);
        }
        
        .missed-block-card.selected {
            border-color: var(--chmsu-green);
            box-shadow: 0 0 0 3px rgba(14, 165, 57, 0.2);
        }
        
        .missed-block-card .card-header {
            border-bottom: none;
            padding: 1rem;
        }
        
        .missed-block-card .card-body {
            padding: 1.25rem;
        }
        
        /* Disabled button styling */
        .missed-block-card button:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .missed-block-card button:disabled:hover {
            transform: none;
        }
        
        /* Modal Enhancements */
        .modal-content {
            border: none;
            box-shadow: 0 10px 30px rgba(128, 110, 110, 0.2);
        }
        
        .modal-header {
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .modal-body {
            padding: 2rem;
        }
        
        .modal-footer {
            border-top: 1px solid #dee2e6;
            padding: 1rem 2rem;
        }
        
        /* File Upload Enhancement */
        .file-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 0.5rem;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }
        
        .file-upload-area:hover {
            border-color: var(--chmsu-green);
            background: rgba(14, 165, 57, 0.05);
        }
        
        .file-upload-area.dragover {
            border-color: var(--chmsu-green);
            background: rgba(14, 165, 57, 0.1);
            transform: scale(1.02);
        }
        
        /* Block Type Colors */
        .bg-warning { background:rgb(247, 215, 35) !important; }
        .bg-info { background:rgb(96, 197, 212) !important; }
        .bg-dark { background:rgb(158, 160, 160) !important; }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .missed-block-card {
                margin-bottom: 1rem;
            }
            
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }
            
            .modal-body {
                padding: 1rem;
            }
            
            .modal-footer {
                padding: 0.75rem 1rem;
            }
        }
    </style>
</head>
<body>
    
    <?php include 'student-sidebar.php'; ?>
    <main>
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                    

                    <br>
                <div class="row">
                        <!-- Modern Missed Blocks Interface -->
                        <div class="col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                        <i class="bi bi-clock-history me-2"></i>Missed Time-Out Blocks
                                </h5>
                                    <p class="text-muted mb-0">Click on a block below to submit a forgot timeout request</p>
                            </div>
                            <div class="card-body">
                                <?php if (empty($attendanceRecords)): ?>
                                        <div class="text-center py-5">
                                            <div class="mb-4">
                                                <i class="bi bi-check-circle-fill text-success" style="font-size: 4rem;"></i>
                                            </div>
                                            <h4 class="text-success mb-3">All Caught Up!</h4>
                                        <p class="text-muted">All your recent attendance records have been completed with time-outs.</p>
                                            <div class="alert alert-success mt-3">
                                                <i class="bi bi-info-circle me-2"></i>
                                                <strong>Great job!</strong> You haven't missed any time-outs recently.
                                            </div>
                                    </div>
                                <?php else: ?>
                                        <div class="row g-3">
                                                <?php foreach ($attendanceRecords as $record): ?>
                                                        <?php 
                                                        $recordDate = DateTime::createFromFormat('Y-m-d', $record['date']);
                                                        $timeInDate = DateTime::createFromFormat('Y-m-d H:i:s', $record['time_in']);
                                                $isToday = $record['date'] === date('Y-m-d');
                                                $blockColors = [
                                                    'morning' => 'warning',
                                                    'afternoon' => 'info', 
                                                    'overtime' => 'dark'
                                                ];
                                                $blockIcons = [
                                                    'morning' => 'sun',
                                                    'afternoon' => 'sun-fill',
                                                    'overtime' => 'moon'
                                                ];
                                                ?>
                                                <div class="col-md-6 col-lg-4">
                                                    <div class="card missed-block-card h-100" 
                                                        data-record-id="<?= $record['id'] ?>"
                                                        data-date="<?= $record['date'] ?>"
                                                        data-block="<?= $record['block_type'] ?>"
                                                        data-time-in="<?= $record['time_in'] ?>"
                                                        style="cursor: pointer; transition: all 0.3s ease;">
                                                        <div class="card-header bg-<?= $blockColors[$record['block_type']] ?> text-white">
                                                            <div class="d-flex align-items-center justify-content-between">
                                                                <div>
                                                                    <h6 class="mb-0">
                                                                        <i class="bi bi-<?= $blockIcons[$record['block_type']] ?> me-2"></i>
                                                        <?= ucfirst($record['block_type']) ?> Block
                                                                    </h6>
                                                                    <small class="opacity-75">
                                                                        <?= $recordDate ? $recordDate->format('M j, Y') : $record['date'] ?>
                                                                    </small>
                                                                </div>
                                                                <span class="badge bg-white text-<?= $blockColors[$record['block_type']] ?>">
                                                                    <?= $isToday ? 'Today' : 'Past' ?>
                                                                </span>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="row g-2">
                                                                <div class="col-12">
                                                                    <div class="d-flex align-items-center mb-2">
                                                                        <i class="bi bi-clock me-2 text-success"></i>
                                                                        <small class="text-muted">Time In:</small>
                                                                    </div>
                                                                    <h6 class="text-success mb-3">
                                                                        <?= $timeInDate ? $timeInDate->format('g:i A') : $record['time_in'] ?>
                                                                    </h6>
                                        </div>
                                                                <div class="col-12">
                                                                    <div class="d-flex align-items-center mb-2">
                                                                        <i class="bi bi-clock-history me-2 text-danger"></i>
                                                                        <small class="text-muted">Time Out:</small>
                                        </div>
                                                                    <h6 class="text-danger mb-3">
                                                                        <i class="bi bi-x-circle me-1"></i>Missing
                                                                    </h6>
                                                </div>
                                            </div>
                                                            <div class="d-grid">
                                                                <?php 
                                                                $requestStatus = isset($requestStatusMap[$record['id']]) ? $requestStatusMap[$record['id']] : null;
                                                                if ($requestStatus === 'pending'): ?>
                                                                    <button class="btn btn-outline-warning btn-sm" disabled data-has-request="true">
                                                                        <i class="bi bi-clock me-1"></i>Pending Review
                                                                    </button>
                                                                <?php elseif ($requestStatus === 'approved'): ?>
                                                                    <button class="btn btn-outline-success btn-sm" disabled data-has-request="true">
                                                                        <i class="bi bi-check-circle me-1"></i>Approved
                                                                    </button>
                                                                <?php elseif ($requestStatus === 'rejected'): ?>
                                                                    <button class="btn btn-outline-danger btn-sm" disabled data-has-request="true">
                                                                        <i class="bi bi-x-circle me-1"></i>Rejected
                                                                    </button>
                                                                <?php else: ?>
                                                                    <button class="btn btn-outline-<?= $blockColors[$record['block_type']] ?> btn-sm" data-has-request="false">
                                                                        <i class="bi bi-plus-circle me-1"></i>Submit Request
                                                    </button>
                                                                <?php endif; ?>
                                                            </div>
                                                </div>
                                            </div>
                                        </div>
                                            <?php endforeach; ?>
                                        </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                        </div><br>

                    <!-- Request History -->
                        <div class="col-md-12">
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
    </main>
    <!-- Submit Request Modal -->
    <div class="modal fade" id="submitRequestModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title">
                        <i class="bi bi-pencil-square me-2"></i>Submit Forgot Time-Out Request
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="forgotTimeoutForm" enctype="multipart/form-data">
                        <input type="hidden" id="attendance_record" name="attendance_record_id">
                        
                        <!-- Selected Block Info -->
                        <div class="alert alert-info" id="selectedBlockInfo">
                            <h6 class="mb-2">
                                <i class="bi bi-calendar-check me-2"></i>Selected Block:
                            </h6>
                            <div id="blockDetails"></div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="reason" class="form-label">
                                        <i class="bi bi-chat-text me-1"></i>Reason for Forgot Time-Out
                                    </label>
                                    <textarea class="form-control" id="reason" name="reason" rows="4" 
                                              placeholder="Please explain why you forgot to time-out..." required></textarea>
                                    <div class="form-text">Provide a detailed explanation for your missed time-out.</div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="letter_file" class="form-label">
                                        <i class="bi bi-file-earmark me-1"></i>Explanation Letter
                                    </label>
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
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="submitRequest()">
                        <i class="bi bi-send me-1"></i>Submit Request
                    </button>
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
        // Modern Interface Variables
        let selectedBlock = null;
        const fileUploadArea = document.getElementById('fileUploadArea');
        const fileInput = document.getElementById('letter_file');
        const fileInfo = document.getElementById('fileInfo');
        const fileName = document.getElementById('fileName');
        const submitRequestModal = new bootstrap.Modal(document.getElementById('submitRequestModal'));

        // Initialize modern interface
        document.addEventListener('DOMContentLoaded', function() {
            initializeMissedBlockCards();
            initializeFileUpload();
        });

        // Initialize missed block cards
        function initializeMissedBlockCards() {
            const missedBlockCards = document.querySelectorAll('.missed-block-card');
            
            missedBlockCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Check if this card already has a request
                    const button = this.querySelector('button');
                    const hasRequest = button ? button.getAttribute('data-has-request') === 'true' : false;
                    
                    if (hasRequest) {
                        showErrorMessage('A request has already been submitted for this block.');
                        return;
                    }
                    selectBlock(this);
                });
            });
        }

        // Select a missed block
        function selectBlock(card) {
            // Remove previous selection
            document.querySelectorAll('.missed-block-card').forEach(c => {
                c.classList.remove('selected');
            });
            
            // Add selection to clicked card
            card.classList.add('selected');
            
            // Get block data
            selectedBlock = {
                recordId: card.dataset.recordId,
                date: card.dataset.date,
                block: card.dataset.block,
                timeIn: card.dataset.timeIn
            };
            
            // Update form
            updateRequestForm();
            
            // Show modal
            submitRequestModal.show();
        }

        // Update request form with selected block data
        function updateRequestForm() {
            if (!selectedBlock) return;
            
            // Set hidden input
            document.getElementById('attendance_record').value = selectedBlock.recordId;
            
            // Update block details display
            const blockDetails = document.getElementById('blockDetails');
            const date = new Date(selectedBlock.date).toLocaleDateString('en-US', { 
                month: 'long', 
                day: 'numeric', 
                year: 'numeric' 
            });
            const timeIn = new Date(selectedBlock.timeIn).toLocaleTimeString('en-US', { 
                hour: 'numeric', 
                minute: '2-digit',
                hour12: true 
            });
            
            blockDetails.innerHTML = `
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-calendar-date me-2"></i>
                    <strong>${date}</strong>
                </div>
                <div class="d-flex align-items-center mb-2">
                    <i class="bi bi-${getBlockIcon(selectedBlock.block)} me-2"></i>
                    <strong>${selectedBlock.block.charAt(0).toUpperCase() + selectedBlock.block.slice(1)} Block</strong>
                </div>
                <div class="d-flex align-items-center">
                    <i class="bi bi-clock me-2"></i>
                    <strong>Time In: ${timeIn}</strong>
                </div>
            `;
        }

        // Get block icon
        function getBlockIcon(block) {
            const icons = {
                'morning': 'sun',
                'afternoon': 'sun-fill',
                'overtime': 'moon'
            };
            return icons[block] || 'clock';
        }

        // Submit request function
        function submitRequest() {
            if (!selectedBlock) {
                showErrorMessage('Please select a missed block first.');
                return;
            }
            
            const form = document.getElementById('forgotTimeoutForm');
            const formData = new FormData(form);
            
            // Show loading state
            const submitBtn = document.querySelector('#submitRequestModal .btn-primary');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Submitting...';
            submitBtn.disabled = true;
            
            fetch('forgot_timeout_submit.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    // Show success message
                    showSuccessMessage('Request submitted successfully!');
                    
                    // Close modal
                    submitRequestModal.hide();
                    
                    // Clear form and selection
                    clearFormAndSelection();
                    
                    // Reload page after delay
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    showErrorMessage('Error: ' + result.error);
                }
            })
            .catch(error => {
                showErrorMessage('An error occurred while submitting the request.');
                console.error('Error:', error);
            })
            .finally(() => {
                // Reset button state
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            });
        }

        // Clear form and selection
        function clearFormAndSelection() {
            // Clear selection
            document.querySelectorAll('.missed-block-card').forEach(c => {
                c.classList.remove('selected');
            });
            
            // Clear form
            document.getElementById('forgotTimeoutForm').reset();
            clearFile();
            
            selectedBlock = null;
        }

        // Initialize file upload
        function initializeFileUpload() {
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
        }

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

        // Handle modal events
        document.getElementById('submitRequestModal').addEventListener('hidden.bs.modal', function() {
            // Clear form when modal is closed
            clearFormAndSelection();
        });

        // Show success message
        function showSuccessMessage(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-success alert-dismissible fade show position-fixed';
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                <i class="bi bi-check-circle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 5000);
        }

        // Show error message
        function showErrorMessage(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show position-fixed';
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                <i class="bi bi-exclamation-triangle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.parentNode.removeChild(alertDiv);
                }
            }, 5000);
        }

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
                            </div>
                            ${data.request.instructor_response ? `
                                <div class="mt-3">
                                    <h6>Instructor Response</h6>
                                    <div class="alert alert-info">
                                        ${data.request.instructor_response}
                                    </div>
                                </div>
                            ` : ''}
                            <div class="mt-3">
                                <h6>Letter Preview</h6>
                                <div class="border rounded p-3" style="height: 400px; overflow-y: auto;">
                                    <iframe src="forgot_timeout_submit.php?action=preview_letter&id=${data.request.id}" 
                                            width="100%" height="100%" 
                                            style="border: none; min-height: 350px;">
                                    </iframe>
                                </div>
                            </div>
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
