<?php
// Start output buffering as early as possible
ob_start();

require_once '../../vendor/autoload.php';

use App\Services\DocumentService;
use App\Services\OverdueService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;

// Authentication
$auth = new AuthMiddleware();
if (!$auth->check() || !$auth->requireRole('student')) {
    $auth->redirectToLogin();
}

// Get data
$pdo = Database::getInstance();

// Helper functions for weekly/monthly reports (needed for both POST and normal page loads)
if (!function_exists('getCurrentWeekPeriod')) {
    function getCurrentWeekPeriod() {
        $now = new DateTime();
        return $now->format('o') . '-W' . str_pad($now->format('W'), 2, '0', STR_PAD_LEFT);
    }
}

if (!function_exists('getCurrentMonthPeriod')) {
    function getCurrentMonthPeriod() {
        return date('Y-m');
    }
}

if (!function_exists('canSubmitExcuseDocument')) {
    function canSubmitExcuseDocument($pdo, $studentId) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'student_reports'");
            if ($stmt->rowCount() === 0) {
                return ['canSubmit' => false, 'message' => 'Reports system not initialized. Please contact administrator.'];
            }
        } catch (Exception $e) {
            return ['canSubmit' => false, 'message' => 'Reports system not available. Please contact administrator.'];
        }
        
        // Check if excuse is in the report_type enum
        $stmt = $pdo->query("SHOW COLUMNS FROM student_reports WHERE Field = 'report_type'");
        $column = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($column && strpos($column['Type'], 'excuse') === false) {
            return ['canSubmit' => false, 'message' => 'Excuse document feature not available. Please contact administrator.'];
        }
        
        // Excuse documents can be submitted multiple times (one per date)
        return [
            'canSubmit' => true, 
            'message' => 'Submit an excuse document for a specific date.'
        ];
    }
}

if (!function_exists('canSubmitWeeklyReport')) {
    function canSubmitWeeklyReport($pdo, $studentId) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'student_reports'");
            if ($stmt->rowCount() === 0) {
                return ['canSubmit' => false, 'message' => 'Reports system not initialized. Please contact administrator.'];
            }
        } catch (Exception $e) {
            return ['canSubmit' => false, 'message' => 'Reports system not available. Please contact administrator.'];
        }
        
        $weekPeriod = getCurrentWeekPeriod();
        $now = new DateTime();
        $dayOfWeek = (int)$now->format('N');
        $monday = clone $now;
        $monday->modify('-' . ($dayOfWeek - 1) . ' days');
        $monday->setTime(0, 0, 0);
        $sunday = clone $monday;
        $sunday->modify('+6 days');
        $sunday->setTime(23, 59, 59);
        
        $stmt = $pdo->prepare("
            SELECT id, submitted_at, status 
            FROM student_reports 
            WHERE student_id = ? 
            AND report_type = 'weekly'
            AND report_period = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId, $weekPeriod]);
        $existingSubmission = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingSubmission) {
            $submittedDate = new DateTime($existingSubmission['submitted_at']);
            $status = $existingSubmission['status'];
            
            if ($status === 'revision_required' || $status === 'rejected') {
                return [
                    'canSubmit' => true, 
                    'message' => 'You can resubmit your weekly report for this week (Week of ' . $monday->format('M d') . ' - ' . $sunday->format('M d, Y') . ').',
                    'submittedDate' => $submittedDate->format('Y-m-d H:i:s'),
                    'reportPeriod' => $weekPeriod,
                    'existingReportId' => $existingSubmission['id']
                ];
            } else {
                return [
                    'canSubmit' => false, 
                    'message' => 'Weekly report of ' . $monday->format('M d') . ' - ' . $sunday->format('M d, Y') . '',
                    'submittedDate' => $submittedDate->format('Y-m-d H:i:s'),
                    'reportPeriod' => $weekPeriod
                ];
            }
        }
        
        return [
            'canSubmit' => true, 
            'message' => 'You can submit your weekly report for this week (Week of ' . $monday->format('M d') . ' - ' . $sunday->format('M d, Y') . ').',
            'weekStart' => $monday->format('Y-m-d'),
            'weekEnd' => $sunday->format('Y-m-d'),
            'reportPeriod' => $weekPeriod
        ];
    }
}

if (!function_exists('canSubmitMonthlyReport')) {
    function canSubmitMonthlyReport($pdo, $studentId) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE 'student_reports'");
            if ($stmt->rowCount() === 0) {
                return ['canSubmit' => false, 'message' => 'Reports system not initialized. Please contact administrator.'];
            }
        } catch (Exception $e) {
            return ['canSubmit' => false, 'message' => 'Reports system not available. Please contact administrator.'];
        }
        
        $monthPeriod = getCurrentMonthPeriod();
        $now = new DateTime();
        
        $stmt = $pdo->prepare("
            SELECT id, submitted_at, status 
            FROM student_reports 
            WHERE student_id = ? 
            AND report_type = 'monthly'
            AND report_period = ?
            LIMIT 1
        ");
        $stmt->execute([$studentId, $monthPeriod]);
        $existingSubmission = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingSubmission) {
            $submittedDate = new DateTime($existingSubmission['submitted_at']);
            $status = $existingSubmission['status'];
            
            if ($status === 'revision_required' || $status === 'rejected') {
                return [
                    'canSubmit' => true, 
                    'message' => 'You can resubmit your monthly report for ' . $now->format('F Y') . '.',
                    'submittedDate' => $submittedDate->format('Y-m-d H:i:s'),
                    'reportPeriod' => $monthPeriod,
                    'existingReportId' => $existingSubmission['id']
                ];
            } else {
                return [
                    'canSubmit' => false, 
                    'message' => 'Monthly report for ' . $now->format('F Y') . '',
                    'submittedDate' => $submittedDate->format('Y-m-d H:i:s'),
                    'reportPeriod' => $monthPeriod
                ];
            }
        }
        
        return [
            'canSubmit' => true, 
            'message' => 'You can submit your monthly report for ' . $now->format('F Y') . '.',
            'monthStart' => $now->format('Y-m-01'),
            'monthEnd' => $now->format('Y-m-t'),
            'reportPeriod' => $monthPeriod
        ];
    }
}

// Handle document submission FIRST (before any other output)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'submit_document') {
    // Suppress error display for clean JSON response
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    
    try {
        $documentId = $_POST['documentId'] ?? '';
        
        if (empty($documentId)) {
            throw new Exception('Document ID is required');
        }
        
        // Get user section (needed for regular documents)
        $stmt = $pdo->prepare("SELECT section_id FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $sectionId = $stmt->fetchColumn();
        
        // Check if this is a weekly, monthly, or excuse report submission
        $isWeeklyReport = false;
        $isMonthlyReport = false;
        $isExcuseDocument = false;
        $reportType = null;
        $reportPeriod = null;
        $excuseDate = null;
        
        // Check if student_reports table exists
        $stmt = $pdo->query("SHOW TABLES LIKE 'student_reports'");
        $reportsTableExists = $stmt->rowCount() > 0;
        
        if (!$reportsTableExists) {
            throw new Exception('Reports system not initialized. Please contact administrator.');
        }
        
        // If documentId is a string (report type), it's a weekly, monthly, or excuse report
        if (!is_numeric($documentId)) {
            if ($documentId === 'weekly_report' || $documentId === 'weekly') {
                $isWeeklyReport = true;
                $reportType = 'weekly';
                $reportPeriod = getCurrentWeekPeriod();
            } elseif ($documentId === 'monthly_report' || $documentId === 'monthly') {
                $isMonthlyReport = true;
                $reportType = 'monthly';
                $reportPeriod = getCurrentMonthPeriod();
            } elseif ($documentId === 'excuse_document' || $documentId === 'excuse') {
                $isExcuseDocument = true;
                $reportType = 'excuse';
                // For excuse documents, use the excuse date as the period
                $excuseDate = $_POST['excuseDate'] ?? null;
                if (empty($excuseDate)) {
                    throw new Exception('Excuse date is required');
                }
                // Validate date format
                $dateObj = DateTime::createFromFormat('Y-m-d', $excuseDate);
                if (!$dateObj || $dateObj->format('Y-m-d') !== $excuseDate) {
                    throw new Exception('Invalid excuse date format');
                }
                $reportPeriod = $excuseDate; // Use date as period for excuse documents
            } else {
                // Regular document - find the actual document ID
                $documentType = $documentId;
                if ($sectionId) {
                    $stmt = $pdo->prepare("SELECT id, document_type FROM documents WHERE document_type = ? AND (uploaded_for_section = ? OR uploaded_for_section IS NULL) AND uploaded_by = 1 ORDER BY uploaded_for_section DESC LIMIT 1");
                    $stmt->execute([$documentType, $sectionId]);
                } else {
                    $stmt = $pdo->prepare("SELECT id, document_type FROM documents WHERE document_type = ? AND uploaded_for_section IS NULL AND uploaded_by = 1 LIMIT 1");
                    $stmt->execute([$documentType]);
                }
                $doc = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$doc) {
                    throw new Exception('Document template not found. Please contact your instructor.');
                }
                $documentId = $doc['id'];
            }
        } else {
            // Numeric ID - verify the document exists (for regular documents)
            $stmt = $pdo->prepare("SELECT id, document_type FROM documents WHERE id = ?");
            $stmt->execute([$documentId]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$doc) {
                throw new Exception('Document not found. Please contact your instructor.');
            }
        }
        
        // Validate weekly report submission (only one per week)
        if ($isWeeklyReport) {
            $weeklyStatus = canSubmitWeeklyReport($pdo, $_SESSION['user_id']);
            if (!$weeklyStatus['canSubmit']) {
                throw new Exception($weeklyStatus['message']);
            }
            $reportPeriod = $weeklyStatus['reportPeriod'];
        }
        
        // Validate monthly report submission (only one per month)
        if ($isMonthlyReport) {
            $monthlyStatus = canSubmitMonthlyReport($pdo, $_SESSION['user_id']);
            if (!$monthlyStatus['canSubmit']) {
                throw new Exception($monthlyStatus['message']);
            }
            $reportPeriod = $monthlyStatus['reportPeriod'];
        }
        
        // Validate excuse document submission
        if ($isExcuseDocument) {
            $excuseStatus = canSubmitExcuseDocument($pdo, $_SESSION['user_id']);
            if (!$excuseStatus['canSubmit']) {
                throw new Exception($excuseStatus['message']);
            }
            // Check if student already submitted an excuse for this date
            $stmt = $pdo->prepare("
                SELECT id, status 
                FROM student_reports 
                WHERE student_id = ? 
                AND report_type = 'excuse'
                AND excuse_date = ?
                LIMIT 1
            ");
            $stmt->execute([$_SESSION['user_id'], $excuseDate]);
            $existingExcuse = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingExcuse) {
                $status = $existingExcuse['status'];
                if ($status === 'revision_required' || $status === 'rejected') {
                    // Allow resubmission
                } else {
                    throw new Exception('You have already submitted an excuse document for ' . date('F d, Y', strtotime($excuseDate)) . '.');
                }
            }
        }
        
        // Check if file was uploaded
        if (!isset($_FILES['documentFile']) || $_FILES['documentFile']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please select a file to upload');
        }
        
        $file = $_FILES['documentFile'];
        
        // Validate file size (10MB limit)
        if ($file['size'] > 10 * 1024 * 1024) {
            throw new Exception('File size must be less than 10MB');
        }
        
        // Validate file type
        $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
        $fileType = mime_content_type($file['tmp_name']);
        if (!in_array($fileType, $allowedTypes)) {
            throw new Exception('Only PDF, DOC, and DOCX files are allowed');
        }
        
        // Create upload directory if it doesn't exist
        $uploadDir = '../../uploads/student_documents/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        // Generate unique filename
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        if ($isWeeklyReport || $isMonthlyReport || $isExcuseDocument) {
            $fileName = 'report_' . $reportType . '_' . $_SESSION['user_id'] . '_' . $reportPeriod . '_' . time() . '.' . $fileExtension;
        } else {
            $fileName = 'submission_' . $_SESSION['user_id'] . '_' . $documentId . '_' . time() . '.' . $fileExtension;
        }
        $filePath = $uploadDir . $fileName;
        
        // Store relative path for database (from web root)
        $relativeFilePath = 'uploads/student_documents/' . $fileName;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('Failed to save uploaded file');
        }
        
        // Handle weekly/monthly/excuse reports separately (using student_reports table)
        if ($isWeeklyReport || $isMonthlyReport || $isExcuseDocument) {
            // Check if report already exists for this period
            $stmt = $pdo->prepare("
                SELECT id, status FROM student_reports 
                WHERE student_id = ? AND report_type = ? AND report_period = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $reportType, $reportPeriod]);
            $existingReport = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingReport) {
                // Report already exists - check if resubmission is allowed
                $currentStatus = $existingReport['status'];
                
                if ($currentStatus === 'approved') {
                    throw new Exception('This report has already been approved and cannot be resubmitted.');
                } elseif ($currentStatus === 'pending') {
                    throw new Exception('This report is already submitted and pending instructor review. Please wait for feedback before resubmitting.');
                } elseif ($currentStatus === 'revision_required') {
                    // Allow resubmission for revision required reports
                    if ($isExcuseDocument) {
                        $stmt = $pdo->prepare("
                            UPDATE student_reports 
                            SET file_path = ?, 
                                excuse_date = ?,
                                status = 'pending', 
                                submitted_at = NOW(), 
                                updated_at = NOW(),
                                instructor_feedback = NULL
                            WHERE id = ?
                        ");
                        $stmt->execute([$relativeFilePath, $excuseDate, $existingReport['id']]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE student_reports 
                            SET file_path = ?, 
                                status = 'pending', 
                                submitted_at = NOW(), 
                                updated_at = NOW(),
                                instructor_feedback = NULL
                            WHERE id = ?
                        ");
                        $stmt->execute([$relativeFilePath, $existingReport['id']]);
                    }
                } else {
                    // For rejected reports, allow resubmission
                    if ($isExcuseDocument) {
                        $stmt = $pdo->prepare("
                            UPDATE student_reports 
                            SET file_path = ?, 
                                excuse_date = ?,
                                status = 'pending', 
                                submitted_at = NOW(), 
                                updated_at = NOW(),
                                instructor_feedback = NULL
                            WHERE id = ?
                        ");
                        $stmt->execute([$relativeFilePath, $excuseDate, $existingReport['id']]);
                    } else {
                        $stmt = $pdo->prepare("
                            UPDATE student_reports 
                            SET file_path = ?, 
                                status = 'pending', 
                                submitted_at = NOW(), 
                                updated_at = NOW(),
                                instructor_feedback = NULL
                            WHERE id = ?
                        ");
                        $stmt->execute([$relativeFilePath, $existingReport['id']]);
                    }
                }
            } else {
                // New report submission - set to pending for instructor review
                if ($isExcuseDocument) {
                    $stmt = $pdo->prepare("
                        INSERT INTO student_reports (student_id, report_type, report_period, file_path, excuse_date, status, submitted_at, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, 'pending', NOW(), NOW(), NOW())
                    ");
                    $stmt->execute([$_SESSION['user_id'], $reportType, $reportPeriod, $relativeFilePath, $excuseDate]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO student_reports (student_id, report_type, report_period, file_path, status, submitted_at, reviewed_at, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, 'approved', NOW(), NOW(), NOW(), NOW())
                    ");
                    $stmt->execute([$_SESSION['user_id'], $reportType, $reportPeriod, $relativeFilePath]);
                }
            }
        } else {
            // Regular document submission (using student_documents table)
            // Check if document already exists and handle revision workflow
            $stmt = $pdo->prepare("
                SELECT id, status FROM student_documents 
                WHERE student_id = ? AND document_id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $documentId]);
            $existingSubmission = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingSubmission) {
                // Document already exists - check if resubmission is allowed
                $currentStatus = $existingSubmission['status'];
                
                if ($currentStatus === 'approved') {
                    throw new Exception('This document has already been approved and cannot be resubmitted.');
                } elseif ($currentStatus === 'pending') {
                    throw new Exception('This document is already submitted and pending instructor review. Please wait for feedback before resubmitting.');
                } elseif ($currentStatus === 'revision_required') {
                    // Allow resubmission for revision required documents
                    $stmt = $pdo->prepare("
                        UPDATE student_documents 
                        SET submission_file_path = ?, 
                            status = 'pending', 
                            submitted_at = NOW(), 
                            updated_at = NOW(),
                            instructor_feedback = NULL
                        WHERE id = ?
                    ");
                    $stmt->execute([$filePath, $existingSubmission['id']]);
                } else {
                    // For rejected documents, allow resubmission
                    $stmt = $pdo->prepare("
                        UPDATE student_documents 
                        SET submission_file_path = ?, 
                            status = 'pending', 
                            submitted_at = NOW(), 
                            updated_at = NOW(),
                            instructor_feedback = NULL
                        WHERE id = ?
                    ");
                    $stmt->execute([$filePath, $existingSubmission['id']]);
                }
            } else {
                // New submission
                $stmt = $pdo->prepare("
                    INSERT INTO student_documents (student_id, document_id, submission_file_path, status, submitted_at, reviewed_at, created_at, updated_at) 
                    VALUES (?, ?, ?, 'pending', NOW(), NULL, NOW(), NOW())
                ");
                $stmt->execute([$_SESSION['user_id'], $documentId, $filePath]);
            }
        }
        
        // Return success response
        // Clear any output buffer completely
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Check if headers have already been sent (this would cause issues)
        if (headers_sent($file, $line)) {
            error_log("Headers already sent in $file on line $line");
        }
        
        // Set headers
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        http_response_code(200);
        
        $successMessage = 'Document submitted successfully.';
        if ($isWeeklyReport || $isMonthlyReport || $isExcuseDocument) {
            if ($isExcuseDocument) {
                if (isset($existingReport) && ($existingReport['status'] === 'revision_required' || $existingReport['status'] === 'rejected')) {
                    $successMessage = 'Excuse document resubmitted successfully. It is now pending instructor review.';
                } else {
                    $successMessage = 'Excuse document submitted successfully. It is now pending instructor review.';
                }
            } else {
                $reportType = $isWeeklyReport ? 'weekly' : 'monthly';
                if (isset($existingReport) && ($existingReport['status'] === 'revision_required' || $existingReport['status'] === 'rejected')) {
                    $successMessage = ucfirst($reportType) . ' report resubmitted successfully and has been auto-approved!';
                } else {
                    $successMessage = ucfirst($reportType) . ' report submitted successfully and has been auto-approved!';
                }
            }
        } else {
            if (isset($existingSubmission) && $existingSubmission['status'] === 'revision_required') {
                $successMessage = 'Document resubmitted successfully. It is now pending instructor review.';
            } elseif (isset($existingSubmission) && $existingSubmission['status'] === 'rejected') {
                $successMessage = 'Document resubmitted successfully. It is now pending instructor review.';
            } else {
                $successMessage = 'Document submitted successfully. It is now pending instructor review.';
            }
        }
        
        echo json_encode(['success' => true, 'message' => $successMessage], JSON_UNESCAPED_UNICODE);
        exit;
        
    } catch (Exception $e) {
        // Clear any output buffer completely
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        // Check if headers have already been sent
        if (headers_sent($file, $line)) {
            error_log("Headers already sent in $file on line $line");
        }
        
        // Set headers
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, must-revalidate');
        http_response_code(200);
        
        echo json_encode(['success' => false, 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Continue with normal page load...
$docService = new DocumentService();
$overdueService = new OverdueService();

// Check profile
$stmt = $pdo->prepare("SELECT COUNT(*) FROM student_profiles WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
if (!$stmt->fetchColumn()) {
    header('Location: profile.php');
    exit;
}

// Get user section
$stmt = $pdo->prepare("SELECT section_id FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$sectionId = $stmt->fetchColumn();

if (!$sectionId) {
    $error = "You have not been assigned to a section yet. Please contact your instructor.";
    $templates = $customDocs = $overdueDocs = [];
} else {
    $templates = $docService->getDocumentsForSection($sectionId);
    $customDocs = $docService->getCustomDocumentsForSection($sectionId);
    $overdueDocs = $overdueService->getOverdueDocumentsForStudent($_SESSION['user_id']);
}

// Check if student_reports table exists (for weekly/monthly reports)
$reportsTableExists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'student_reports'");
    $reportsTableExists = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $reportsTableExists = false;
}

// Weekly, monthly, and excuse reports are now handled separately via student_reports table
// No need to fetch from documents table
// Helper functions are defined earlier in the file (lines 22-154)

// Check weekly, monthly, and excuse report submission eligibility (using new student_reports table)
$weeklyReportStatus = null;
$monthlyReportStatus = null;
$excuseReportStatus = null;
if ($reportsTableExists) {
    $weeklyReportStatus = canSubmitWeeklyReport($pdo, $_SESSION['user_id']);
    $monthlyReportStatus = canSubmitMonthlyReport($pdo, $_SESSION['user_id']);
    $excuseReportStatus = canSubmitExcuseDocument($pdo, $_SESSION['user_id']);
}

// Get student submissions
$stmt = $pdo->prepare("
    SELECT sd.*, d.document_name, d.document_type, d.file_path as template_path
    FROM student_documents sd
    JOIN documents d ON sd.document_id = d.id
    WHERE sd.student_id = ?
    ORDER BY d.document_type
");
$stmt->execute([$_SESSION['user_id']]);
$submissions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Create submission map
$submissionMap = [];
foreach ($submissions as $sub) {
    $submissionMap[$sub['document_type']] = $sub;
}

// Calculate progress
$requiredTypes = $docService->getRequiredDocumentTypes();
$totalRequired = count($requiredTypes);
$approvedCount = $submittedCount = 0;

foreach ($requiredTypes as $type => $name) {
    if (isset($submissionMap[$type])) {
        $submittedCount++;
        if ($submissionMap[$type]['status'] === 'approved') {
            $approvedCount++;
        }
    }
}

$progressPercentage = $totalRequired > 0 ? ($approvedCount / $totalRequired) * 100 : 0;

// Get all documents for display
$allDocuments = [];

// Add pre-loaded templates
foreach ($requiredTypes as $type => $name) {
    $studentDoc = $submissionMap[$type] ?? null;
    $status = $studentDoc ? $studentDoc['status'] : 'not_started';
    $statusText = match($status) {
        'approved' => 'Completed',
        'pending' => 'Sent',
        'revision_required' => 'Suggest Edits',
        'rejected' => 'Expired',
        default => 'Draft'
    };
    
    // Get template file path, deadline, upload date, and ID from database
    $templatePath = null;
    $templateDeadline = null;
    $templateUploadDate = null;
    $templateId = null;
    $stmt = $pdo->prepare("SELECT id, file_path, deadline, created_at FROM documents WHERE document_type = ? AND (uploaded_for_section = ? OR uploaded_for_section IS NULL) AND uploaded_by = 1 LIMIT 1");
    $stmt->execute([$type, $sectionId]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($template) {
        $templateId = $template['id'];
        $templatePath = $template['file_path'];
        $templateDeadline = $template['deadline'];
        $templateUploadDate = $template['created_at'];
        // Debug: Log the file path
        error_log("Template file path for $type: " . $templatePath);
        // Check if file actually exists
        $testPaths = [
            $templatePath,
            '../../' . $templatePath,
            '../' . $templatePath
        ];
        foreach ($testPaths as $testPath) {
            if (file_exists($testPath)) {
                error_log("File found at: " . $testPath);
                break;
            }
        }
    }
    
    // Check if document is recently uploaded (within last 1 day)
    $isNew = false;
    if ($templateUploadDate) {
        $uploadDate = new DateTime($templateUploadDate);
        $sevenDaysAgo = new DateTime('-1 day');
        $isNew = $uploadDate > $sevenDaysAgo;
    }
    
    $allDocuments[] = [
        'id' => $templateId ?? $type, // Use actual document ID if available, otherwise fallback to type
        'documentType' => $type, // Store document type separately for reference
        'name' => $name,
        'type' => 'Pre-loaded Template',
        'status' => $status,
        'statusText' => $statusText,
        'created' => $studentDoc ? $studentDoc['created_at'] : date('Y-m-d H:i:s'),
        'lastActivity' => $studentDoc ? $studentDoc['updated_at'] : date('Y-m-d H:i:s'),
        'isTemplate' => true,
        'filePath' => $templatePath,
        'deadline' => $templateDeadline,
        'uploadDate' => $templateUploadDate,
        'isNew' => $isNew,
        'instructor_feedback' => $studentDoc ? $studentDoc['instructor_feedback'] : null
    ];
}

// Weekly and monthly reports are handled via separate modal submission
// They are not displayed in the main documents table

// Add custom documents
foreach ($customDocs as $customDoc) {
    $stmt = $pdo->prepare("SELECT * FROM student_documents WHERE student_id = ? AND document_id = ?");
    $stmt->execute([$_SESSION['user_id'], $customDoc->id]);
    $customSubmission = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $status = $customSubmission ? $customSubmission['status'] : 'not_started';
    $statusText = match($status) {
        'approved' => 'Completed',
        'pending' => 'Sent',
        'revision_required' => 'Suggest Edits',
        'rejected' => 'Expired',
        default => 'Draft'
    };
    
    // Check if custom document is recently uploaded (within last 7 days)
    $isNewCustom = false;
    if ($customDoc->created_at) {
        $uploadDate = new DateTime($customDoc->created_at);
        $sevenDaysAgo = new DateTime('-7 days');
        $isNewCustom = $uploadDate > $sevenDaysAgo;
    }
    
    $allDocuments[] = [
        'id' => $customDoc->id,
        'name' => $customDoc->document_name,
        'type' => 'Additional Requirement',
        'status' => $status,
        'statusText' => $statusText,
        'created' => $customSubmission ? $customSubmission['created_at'] : date('Y-m-d H:i:s'),
        'lastActivity' => $customSubmission ? $customSubmission['updated_at'] : date('Y-m-d H:i:s'),
        'isTemplate' => false,
        'filePath' => $customSubmission ? ($customSubmission['submission_file_path'] ?? null) : $customDoc->file_path,
        'deadline' => $customDoc->deadline,
        'uploadDate' => $customDoc->created_at,
        'isNew' => $isNewCustom,
        'instructor_feedback' => $customSubmission ? $customSubmission['instructor_feedback'] : null
    ];
}

// Sort documents by upload date (newest first)
usort($allDocuments, function($a, $b) {
    $dateA = $a['uploadDate'] ? new DateTime($a['uploadDate']) : new DateTime($a['created']);
    $dateB = $b['uploadDate'] ? new DateTime($b['uploadDate']) : new DateTime($b['created']);
    return $dateB <=> $dateA; // Descending order (newest first)
});
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Documents - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <link rel="icon" type="image/png" href="../images/CHMSU.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --chmsu-green: #0ea539;
            --chmsu-light: #34d399;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        
        .nav-pills .nav-link {
            border-radius: 20px;
            padding: 8px 16px;
            margin-right: 8px;
            border: 1px solid #e9ecef;
            background: white;
            color: #6c757d;
        }
        
        .nav-pills .nav-link.active {
            background: var(--chmsu-green);
            border-color: var(--chmsu-green);
            color: white;
        }
        
        .nav-pills .nav-link:hover {
            background: #f8f9fa;
            border-color: var(--chmsu-green);
            color: var(--chmsu-green);
        }
        
        .document-card {
            border: 1px solid #e9ecef;
            border-radius: 8px;
            transition: all 0.2s ease;
            background: white;
        }
        
        .document-card:hover {
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .status-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
        }
        
        .status-draft { background: #6c757d; color: white; }
        .status-sent { background: #0dcaf0; color: white; }
        .status-completed { background: #198754; color: white; }
        .status-expired { background: #dc3545; color: white; }
        .status-suggest-edits { background: #fd7e14; color: white; }
        
        
        .table th {
            border-top: none;
            font-weight: 600;
            color: #6c757d;
            font-size: 0.875rem;
        }
        
        .table td {
            vertical-align: middle;
            border-top: 1px solid #f1f3f4;
        }
        
        .btn-group .btn {
            border-radius: 6px;
        }
        
        .btn-group .btn.active {
            background: var(--chmsu-green);
            border-color: var(--chmsu-green);
            color: white;
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
        }
        
        .progress-ring .background-circle {
            stroke: #e9ecef;
        }
        
        /* Mobile Responsive Styles */
        @media (max-width: 768px) {
            /* Hide elements on mobile */
            .nav-pills {
                display: none !important;
            }
            
            .dropdown {
                display: none !important;
            }
            
            .btn-success[style*="font-size: 13px"] {
                display: none !important;
            }
            
            /* Mobile table container */
            .table-responsive {
                border: none;
                box-shadow: none;
            }
            
            /* Mobile table styling */
            .table {
                margin-bottom: 0;
                font-size: 0.875rem;
            }
            
            .table thead {
                display: none;
            }
            
            .table tbody tr {
                display: block;
                margin-bottom: 1rem;
                background: white;
                border: 1px solid #e9ecef;
                border-radius: 8px;
                padding: 1rem;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            
            .table tbody td {
                display: block;
                border: none;
                padding: 0.25rem 0;
                text-align: left !important;
            }
            
            /* Mobile document card layout */
            .table tbody td:first-child {
                border-bottom: 1px solid #f1f3f4;
                padding-bottom: 0.75rem;
                margin-bottom: 0.75rem;
            }
            
            /* Mobile document name styling */
            .table tbody td:first-child .fw-semibold::before {
                content: "📄 ";
                margin-right: 0.25rem;
            }
            
            /* Document name and new badge */
            .table tbody td:first-child .d-flex {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .table tbody td:first-child .fw-semibold {
                font-size: 1rem;
            font-weight: 600;
                color: #333;
                margin-bottom: 0.25rem;
            }
            
            .table tbody td:first-child small {
                font-size: 0.75rem;
                color: #6c757d;
            }
            
            /* Status and type row */
            .table tbody td:nth-child(3),
            .table tbody td:nth-child(4) {
                display: inline-block;
                margin-right: 0.5rem;
                margin-bottom: 0.5rem;
            }
            
            /* Date row */
            .table tbody td:nth-child(2) {
                font-size: 0.8rem;
                color: #6c757d;
                margin-bottom: 0.5rem;
            }
            
            
            /* Actions row */
            .table tbody td:last-child {
                text-align: center;
                margin-top: 0.75rem;
                padding-top: 0.75rem;
                border-top: 1px solid #f1f3f4;
            }
            
            /* Mobile action button */
            .table tbody td:last-child .btn {
                width: 100%;
                font-size: 0.875rem;
                padding: 0.5rem 1rem;
            }
            
            /* Mobile header adjustments */
            .container-fluid {
                padding: 1rem 0.75rem;
            }
            
            .d-flex.justify-content-between {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .d-flex.gap-2 {
                width: 100%;
                justify-content: center;
            }
            
            /* Mobile search bar */
            .input-group {
                max-width: 100% !important;
                width: 100%;
            }
            
            .input-group .form-control {
                font-size: 0.875rem;
            }
            
            /* Mobile modal adjustments */
            .modal-dialog {
                margin: 0.5rem;
                max-width: calc(100% - 1rem);
            }
            
            .modal-body {
                padding: 1rem;
            }
            
            .modal-body .row {
                margin-bottom: 1rem;
            }
            
            .modal-body .col-md-6 {
                margin-bottom: 1rem;
            }
            
            /* Mobile document preview */
            .modal-body .border.rounded {
                height: 300px !important;
            }
            
            /* Mobile modal header */
            .modal-header h5 {
                font-size: 1.1rem;
            }
            
            /* Mobile modal actions */
            .modal-body .d-grid .btn {
                font-size: 0.9rem;
                padding: 0.75rem 1rem;
            }
            
            /* Mobile button adjustments */
            .btn-group .btn {
                font-size: 0.8rem;
                padding: 0.375rem 0.75rem;
            }
            
            /* Status badges mobile */
            .status-badge {
                font-size: 0.7rem;
                padding: 0.25rem 0.5rem;
            }
            
            /* Badge adjustments */
            .badge {
                font-size: 0.7rem;
                padding: 0.25rem 0.5rem;
            }
            
            /* Mobile labels */
            .mobile-label {
                display: block;
                font-size: 0.75rem;
                font-weight: 600;
                color: #6c757d;
                margin-bottom: 0.25rem;
            }
        }
        
        /* Extra small screens */
        @media (max-width: 480px) {
            .container-fluid {
                padding: 0.5rem;
            }
            
            .table tbody tr {
                padding: 0.75rem;
                margin-bottom: 0.75rem;
            }
            
            .table tbody td:first-child .fw-semibold {
                font-size: 0.9rem;
            }
            
            .table tbody td:first-child small {
                font-size: 0.7rem;
            }
            
            .table tbody td:nth-child(2) {
                font-size: 0.75rem;
            }
            
            .btn {
                font-size: 0.8rem;
                padding: 0.375rem 0.5rem;
            }
            
            .status-badge {
                font-size: 0.65rem;
                padding: 0.2rem 0.4rem;
            }
            
            .badge {
                font-size: 0.65rem;
                padding: 0.2rem 0.4rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'student-sidebar.php'; ?>
    
    <main>
        <div class="container-fluid py-4">
            <!-- Header Section -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-1">My Documents</h1>
                    <p class="text-muted mb-0 d-none d-md-block">Manage your OJT documents and submissions</p>
                    <p class="text-muted mb-0 d-md-none">OJT Documents</p>
        </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-secondary" onclick="downloadAllCompleted()">
                        <i class="bi bi-download me-1"></i><span class="d-none d-sm-inline">Download All</span><span class="d-sm-none">All</span>
                    </button>
            </div>
        </div>

            <!-- Weekly, Monthly & Excuse Report Submission Section -->
        <div class="row mb-4">
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="card border-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="bi bi-calendar-week me-2 text-success"></i>Weekly Report
                                    </h6>
                                    <p class="text-muted mb-0 small">
                                        <?php if ($weeklyReportStatus): ?>
                                            <?= htmlspecialchars($weeklyReportStatus['message']) ?>
                                        <?php else: ?>
                                            Submit your weekly report
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php 
                                $weeklyCanSubmit = $weeklyReportStatus && ($weeklyReportStatus['canSubmit'] ?? false);
                                if ($weeklyCanSubmit): ?>
                                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#reportSubmissionModal" onclick="openReportModal('weekly')">
                                        <i class="bi bi-upload me-1"></i>Submit
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled title="You have already submitted a report for this period">
                                        <i class="bi bi-check-circle me-1"></i>Submitted
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <div class="card border-success">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="bi bi-calendar-month me-2 text-success"></i>Monthly Report
                                    </h6>
                                    <p class="text-muted mb-0 small">
                                        <?php if ($monthlyReportStatus): ?>
                                            <?= htmlspecialchars($monthlyReportStatus['message']) ?>
                                        <?php else: ?>
                                            Submit your monthly report
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php 
                                $monthlyCanSubmit = $monthlyReportStatus && ($monthlyReportStatus['canSubmit'] ?? false);
                                if ($monthlyCanSubmit): ?>
                                    <button class="btn btn-success btn-sm" data-bs-toggle="modal" data-bs-target="#reportSubmissionModal" onclick="openReportModal('monthly')">
                                        <i class="bi bi-upload me-1"></i>Submit
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled title="You have already submitted a report for this period">
                                        <i class="bi bi-check-circle me-1"></i>Submitted
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-warning">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">
                                        <i class="bi bi-file-earmark-medical me-2 text-warning"></i>Excuse Document
                                    </h6>
                                    <p class="text-muted mb-0 small">
                                        <?php if ($excuseReportStatus): ?>
                                            <?= htmlspecialchars($excuseReportStatus['message']) ?>
                                        <?php else: ?>
                                            Submit an excuse document for absence
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <?php 
                                $excuseCanSubmit = $excuseReportStatus && ($excuseReportStatus['canSubmit'] ?? false);
                                if ($excuseCanSubmit): ?>
                                    <button class="btn btn-warning btn-sm" data-bs-toggle="modal" data-bs-target="#reportSubmissionModal" onclick="openReportModal('excuse')">
                                        <i class="bi bi-upload me-1"></i>Submit
                                    </button>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled title="Excuse document submission not available">
                                        <i class="bi bi-x-circle me-1"></i>Unavailable
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Document Status Tabs -->
            <div class="mb-4">
                <ul class="nav nav-pills" id="documentTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="all-tab" data-bs-toggle="pill" data-bs-target="#all" type="button" role="tab">
                            <i class="bi bi-files me-1"></i>All Documents
                    </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="for-approval-tab" data-bs-toggle="pill" data-bs-target="#for-approval" type="button" role="tab">
                            <i class="bi bi-clock me-1"></i>For Approval
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="completed-tab" data-bs-toggle="pill" data-bs-target="#completed" type="button" role="tab">
                            <i class="bi bi-check-circle me-1"></i>Completed
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="revision-tab" data-bs-toggle="pill" data-bs-target="#revision" type="button" role="tab">
                            <i class="bi bi-arrow-clockwise me-1"></i>Revision or Suggest Edit
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="expired-tab" data-bs-toggle="pill" data-bs-target="#expired" type="button" role="tab">
                            <i class="bi bi-x-circle me-1"></i>Expired/Declined
                        </button>
                    </li>
                </ul>
                        </div>

            <!-- Document Controls -->
        <div class="row mb-4">
                <div class="col-12">
                    <div class="d-flex gap-2 align-items-center justify-content-between">
                        <div class="dropdown">
                            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="bi bi-sort-down me-1"></i>Sort by Name
                            </button>
                            <button class="btn btn-success" style="font-size: 13px;">
                                <i class="bi bi-folder-plus me-1" style="color: white;"></i>My Portfolio
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="#">Name</a></li>
                                <li><a class="dropdown-item" href="#">Date</a></li>
                                <li><a class="dropdown-item" href="#">Status</a></li>
                            </ul>
                    </div>

                        <div class="d-flex gap-2 align-items-center">
                        <div class="input-group" style="max-width: 300px;">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" class="form-control" placeholder="Search documents..." id="searchInput">
                            <button class="btn btn-outline-secondary" type="button" id="clearSearchBtn" onclick="clearSearch()">
                                <i class="bi bi-x"></i>
                            </button>
                </div>

            <!-- Mobile search info -->
            <div class="d-md-none text-muted small">
                <i class="bi bi-info-circle me-1"></i>Tap documents to view details
            </div>

                            </div>
                </div>
            </div>
        </div>
        
            <!-- Document List -->
            <div class="card">
                <div class="card-body p-0">
            <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                        <tr>
                                    <th>Name</th>
                                    <th>Date uploaded</th>
                            <th>Status</th>
                            <th>Type</th>
                                    <th width="120">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                                <?php foreach ($allDocuments as $doc): ?>
                        <tr>
                            <td>
                                        <div class="d-flex align-items-center">
                                            <i class="bi bi-file-earmark-text me-2 text-primary"></i>
                                            <div>
                                                <div class="fw-semibold d-flex align-items-center gap-2">
                                                    <?= htmlspecialchars($doc['name']) ?>
                                                    <?php if ($doc['isNew']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="bi bi-star-fill me-1" style="color: white;"></i>New
                                </span>
                                <?php endif; ?>
                                                </div>
                                                <small class="text-muted">Created: <?= date('M j, Y, g:i A', strtotime($doc['created'])) ?></small>
                                            </div>
                                        </div>
                            </td>
                            <td>
                                        <small class="text-muted"><?= date('M j, Y, g:i A', strtotime($doc['lastActivity'])) ?></small>
                                        <span class="mobile-label d-md-none">Last Activity: </span>
                            </td>
                            <td>
                                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $doc['statusText'])) ?>">
                                            <?= $doc['statusText'] ?>
                                    </span>
                                    <span class="mobile-label d-md-none">Status: </span>
                            </td>
                            <td>
                                        <span class="badge bg-<?= ($doc['isTemplate'] ?? false) ? 'primary' : (($doc['isReport'] ?? false) ? 'warning' : 'info') ?>">
                                            <?= htmlspecialchars($doc['type']) ?>
                                    </span>
                                        <?php if (isset($doc['isReport']) && $doc['isReport'] && isset($doc['canSubmit']) && !$doc['canSubmit']): ?>
                                            <br><small class="text-danger mt-1 d-block"><?= htmlspecialchars($doc['submitMessage'] ?? '') ?></small>
                                <?php endif; ?>
                                        <span class="mobile-label d-md-none">Type: </span>
                            </td>
                            <td>
                                        <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#documentModal" onclick="loadDocument('<?= $doc['id'] ?>', '<?= htmlspecialchars($doc['name']) ?>', '<?= $doc['status'] ?>', '<?= $doc['filePath'] ?? '' ?>', '<?= $doc['deadline'] ?? '' ?>', '<?= htmlspecialchars($doc['instructor_feedback'] ?? '') ?>', '<?= isset($doc['isReport']) && $doc['isReport'] ? $doc['reportType'] : '' ?>', <?= isset($doc['canSubmit']) && $doc['canSubmit'] ? 'true' : 'false' ?>, '<?= htmlspecialchars($doc['submitMessage'] ?? '') ?>')">
                                <i class="bi bi-eye"></i> View
                            </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Mobile empty state -->
                <?php if (empty($allDocuments)): ?>
                <div class="d-md-none text-center py-5">
                    <i class="bi bi-file-earmark-text" style="font-size: 3rem; color: #6c757d;"></i>
                    <h5 class="mt-3 text-muted">No Documents Found</h5>
                    <p class="text-muted">No documents are available at the moment.</p>
        </div>
        <?php endif; ?>
            </div>
        </div>
                                    </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, setting up event listeners...');
            
            // View toggle functionality
            const listView = document.getElementById('listView');
            const gridView = document.getElementById('gridView');
            
            if (listView) {
                listView.addEventListener('click', function() {
                    this.classList.add('active');
                    if (gridView) gridView.classList.remove('active');
                });
            }
            
            if (gridView) {
                gridView.addEventListener('click', function() {
                    this.classList.add('active');
                    if (listView) listView.classList.remove('active');
                });
            }
        
            // Search functionality
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    filterDocuments();
                });
                
                // Add Enter key support
                searchInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        filterDocuments();
                    }
                });
            }
            
            // Clear search functionality
            window.clearSearch = function() {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    searchInput.value = '';
                    filterDocuments();
                }
            };
            
            // Tab filtering functionality using both Bootstrap and click events
            console.log('Setting up tab listeners...');
            const tabButtons = document.querySelectorAll('#documentTabs button[data-bs-toggle="pill"]');
            console.log('Found tab buttons:', tabButtons.length);
            
            tabButtons.forEach((button, index) => {
                console.log(`Tab ${index}:`, button.textContent.trim(), 'Target:', button.getAttribute('data-bs-target'));
                
                // Bootstrap tab event
                button.addEventListener('shown.bs.tab', function() {
                    console.log('Bootstrap tab event triggered:', this.textContent.trim());
                    const targetTab = this.getAttribute('data-bs-target');
                    console.log('Target tab:', targetTab);
                    filterDocumentsByTab(targetTab);
                });
                
                // Fallback click event
                button.addEventListener('click', function() {
                    console.log('Click event triggered:', this.textContent.trim());
                    const targetTab = this.getAttribute('data-bs-target');
                    console.log('Target tab:', targetTab);
                    // Small delay to ensure Bootstrap has processed the tab change
                    setTimeout(() => {
                        filterDocumentsByTab(targetTab);
                    }, 100);
                });
            });
        
            function filterDocuments() {
                const searchInput = document.getElementById('searchInput');
                const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
                const activeTab = document.querySelector('#documentTabs .nav-link.active');
                const targetTab = activeTab ? activeTab.getAttribute('data-bs-target') : '#all';
                
                console.log('Search term:', searchTerm);
                filterDocumentsByTab(targetTab, searchTerm);
            }
            
            function filterDocumentsByTab(targetTab, searchTerm = '') {
                console.log('=== FILTERING FUNCTION CALLED ===');
                console.log('Filtering by tab:', targetTab, 'Search term:', searchTerm);
                const rows = document.querySelectorAll('tbody tr');
                console.log('Found rows:', rows.length);
                
                if (rows.length === 0) {
                    console.log('ERROR: No table rows found!');
                    return;
                }
                
                // First, let's see all status values in the table
                console.log('=== ALL STATUS VALUES FOUND ===');
                rows.forEach((row, index) => {
                    const statusBadge = row.querySelector('.status-badge');
                    const statusText = statusBadge ? statusBadge.textContent.trim() : '';
                    console.log(`Row ${index} status: "${statusText}"`);
                });
                console.log('=== END STATUS VALUES ===');
                
                let visibleCount = 0;
                rows.forEach((row, index) => {
                    const statusBadge = row.querySelector('.status-badge');
                    const documentName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    const documentType = row.querySelector('td:nth-child(2) small').textContent.toLowerCase();
                    
                    let showRow = false;
                    const statusText = statusBadge ? statusBadge.textContent.trim() : '';
                    
                    console.log(`Row ${index}:`);
                    console.log(`  - Status Badge:`, statusBadge);
                    console.log(`  - Status Badge HTML:`, statusBadge ? statusBadge.outerHTML : 'null');
                    console.log(`  - Status Text: "${statusText}"`);
                    console.log(`  - Status Text Length: ${statusText.length}`);
                    console.log(`  - Document Name: "${documentName}"`);
                    console.log(`  - Document Type: "${documentType}"`);
                    
                    // Filter by tab based on actual database status values
                    switch(targetTab) {
                        case '#all':
                            showRow = true;
                            console.log(`  - All Documents: Show=true`);
                                    break;
                        case '#for-approval':
                            // Database: 'pending' -> Display: 'Sent' or 'Draft'
                            showRow = statusText === 'Sent' || statusText === 'Draft';
                            console.log(`  - For Approval: Status="${statusText}", Show=${showRow}`);
                                    break;
                        case '#completed':
                            // Database: 'approved' -> Display: 'Completed'
                            showRow = statusText === 'Completed';
                            console.log(`  - Completed: Status="${statusText}", Show=${showRow}`);
                                    break;
                        case '#revision':
                            // Database: 'revision_required' -> Display: 'Suggest Edits'
                            showRow = statusText === 'Suggest Edits' || 
                                     statusText === 'Suggest Edit' ||
                                     statusText.toLowerCase().includes('suggest') ||
                                     statusText.toLowerCase().includes('revision');
                            console.log(`  - Revision: Status="${statusText}", Show=${showRow}`);
                            break;
                        case '#expired':
                            // Database: 'rejected' -> Display: 'Expired'
                            showRow = statusText === 'Expired';
                            console.log(`  - Expired: Status="${statusText}", Show=${showRow}`);
                                    break;
                            }
                    
                    // Apply search filter
                    if (showRow && searchTerm) {
                        // Search in multiple fields
                        const searchableText = [
                            documentName,
                            documentType,
                            statusText.toLowerCase()
                        ].join(' ');
                        
                        showRow = searchableText.includes(searchTerm);
                        console.log(`  - Search filter applied: "${searchTerm}" in "${searchableText}" = ${showRow}`);
                    }
                    
                    if (showRow) visibleCount++;
                    console.log(`  - Final Show=${showRow}`);
                    row.style.display = showRow ? '' : 'none';
                });
                
                console.log(`=== FILTERING END - ${visibleCount} documents visible ===`);
            }
            
            
            // Initialize with "All Documents" tab active
            filterDocumentsByTab('#all');
        });
        
        // Open Report Submission Modal
        function openReportModal(reportType) {
            const modal = document.getElementById('reportSubmissionModal');
            const modalHeader = document.getElementById('reportModalHeader');
            const modalTitle = document.getElementById('reportSubmissionModalLabel');
            const reportTypeInput = document.getElementById('reportType');
            const messageText = document.getElementById('reportSubmissionMessageText');
            const submitBtn = document.getElementById('submitReportBtn');
            const fileInput = document.getElementById('reportFileInput');
            
            // Set report type
            reportTypeInput.value = reportType;
            
            // Show/hide excuse date field
            const excuseDateField = document.getElementById('excuseDateField');
            if (excuseDateField) {
                if (reportType === 'excuse') {
                    excuseDateField.style.display = 'block';
                } else {
                    excuseDateField.style.display = 'none';
                }
            }
            
            // Update modal appearance based on report type
            if (reportType === 'weekly') {
                modalHeader.className = 'modal-header bg-success text-white';
                modalTitle.innerHTML = '<i class="bi bi-calendar-week me-2"></i>Submit Weekly Report';
                submitBtn.className = 'btn btn-success';
                messageText.textContent = 'Please select a file to upload for your weekly report. You can only submit once per week (Monday to Sunday).';
            } else if (reportType === 'monthly') {
                modalHeader.className = 'modal-header bg-success text-white';
                modalTitle.innerHTML = '<i class="bi bi-calendar-month me-2"></i>Submit Monthly Report';
                submitBtn.className = 'btn btn-success';
                messageText.textContent = 'Please select a file to upload for your monthly report. You can only submit once per calendar month.';
            } else if (reportType === 'excuse') {
                modalHeader.className = 'modal-header bg-warning text-dark';
                modalTitle.innerHTML = '<i class="bi bi-file-earmark-medical me-2"></i>Submit Excuse Document';
                submitBtn.className = 'btn btn-warning';
                messageText.textContent = 'Please select a file and the date for which you are submitting this excuse document.';
            }
            
            // Reset form
            fileInput.value = '';
            const excuseDateInput = document.getElementById('excuseDateInput');
            if (excuseDateInput) {
                excuseDateInput.value = '';
            }
            document.getElementById('reportSubmissionStatus').style.display = 'none';
            submitBtn.disabled = false;
        }
        
        // Submit Report
        function submitReport() {
            const reportType = document.getElementById('reportType').value;
            const fileInput = document.getElementById('reportFileInput');
            const submitBtn = document.getElementById('submitReportBtn');
            const statusDiv = document.getElementById('reportSubmissionStatus');
            const statusAlert = document.getElementById('reportStatusAlert');
            
            // Validate file
            if (!fileInput.files || fileInput.files.length === 0) {
                statusDiv.style.display = 'block';
                statusAlert.className = 'alert alert-danger';
                statusAlert.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Please select a file to upload.';
                return;
            }
            
            const file = fileInput.files[0];
            
            // Validate file size (10MB limit)
            if (file.size > 10 * 1024 * 1024) {
                statusDiv.style.display = 'block';
                statusAlert.className = 'alert alert-danger';
                statusAlert.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>File size must be less than 10MB.';
                return;
            }
            
            // Validate file type
            const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            // Check by extension as well since mime type might not be reliable
            const fileExtension = file.name.split('.').pop().toLowerCase();
            if (!['pdf', 'doc', 'docx'].includes(fileExtension)) {
                statusDiv.style.display = 'block';
                statusAlert.className = 'alert alert-danger';
                statusAlert.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Only PDF, DOC, and DOCX files are allowed.';
                return;
            }
            
            // Validate excuse date if excuse document
            if (reportType === 'excuse') {
                const excuseDateInput = document.getElementById('excuseDateInput');
                if (!excuseDateInput || !excuseDateInput.value) {
                    statusDiv.style.display = 'block';
                    statusAlert.className = 'alert alert-danger';
                    statusAlert.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Please select the date for which you are submitting this excuse.';
                    return;
                }
            }
            
            // Prepare form data
            const formData = new FormData();
            formData.append('documentFile', file);
            formData.append('action', 'submit_document');
            if (reportType === 'weekly') {
                formData.append('documentId', 'weekly_report');
            } else if (reportType === 'monthly') {
                formData.append('documentId', 'monthly_report');
            } else if (reportType === 'excuse') {
                formData.append('documentId', 'excuse_document');
                formData.append('excuseDate', document.getElementById('excuseDateInput').value);
            }
            
            // Show loading state
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Submitting...';
            statusDiv.style.display = 'block';
            statusAlert.className = 'alert alert-info';
            statusAlert.innerHTML = '<i class="bi bi-hourglass-split me-2"></i>Uploading and processing your report...';
            
            // Submit the form
            fetch('documents.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                // Check if response is OK
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                // Check content type
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    // If not JSON, get text and log it
                    return response.text().then(text => {
                        console.error('Non-JSON response:', text);
                        throw new Error('Server returned non-JSON response');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data && data.success) {
                    statusAlert.className = 'alert alert-success';
                    statusAlert.innerHTML = '<i class="bi bi-check-circle me-2"></i>' + (data.message || 'Report submitted successfully!');
                    submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Submitted';
                    
                    // Reload page after 2 seconds
                    setTimeout(() => {
                        location.reload();
                    }, 2000);
                } else {
                    statusAlert.className = 'alert alert-danger';
                    statusAlert.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>' + (data?.message || 'Error submitting report. Please try again.');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Submit Report';
                }
            })
            .catch(error => {
                console.error('Submission error:', error);
                statusAlert.className = 'alert alert-danger';
                statusAlert.innerHTML = '<i class="bi bi-exclamation-triangle me-2"></i>Error submitting report: ' + (error.message || 'Please try again or refresh the page.');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Submit Report';
            });
        }
        
        // Load document in modal
    function loadDocument(id, name, status, filePath, deadline, instructorFeedback = '', reportType = '', canSubmit = true, submitMessage = '') {
        document.getElementById('modalDocumentName').textContent = name;
        document.getElementById('modalDocumentStatus').textContent = status;
        document.getElementById('modalDocumentStatus').className = 'badge bg-' + getStatusColor(status);
        
        // Store file path and document ID for download/submission
        window.currentDocumentFilePath = filePath;
        window.currentDocumentId = id;
        window.isReport = reportType !== '';
        window.canSubmitReport = canSubmit === true || canSubmit === 'true';
        window.reportSubmitMessage = submitMessage;
        
        // Update download button state (reports don't have templates)
        const downloadBtn = document.getElementById('downloadTemplateBtn');
        if (window.isReport) {
            downloadBtn.disabled = true;
            downloadBtn.innerHTML = '<i class="bi bi-info-circle me-1"></i>No Template Available';
            downloadBtn.style.display = 'none'; // Hide download button for reports
        } else if (filePath && filePath !== '') {
            downloadBtn.disabled = false;
            downloadBtn.innerHTML = '<i class="bi bi-download me-1"></i>Download Template';
            downloadBtn.style.display = 'block';
        } else {
            downloadBtn.disabled = true;
            downloadBtn.innerHTML = '<i class="bi bi-exclamation-triangle me-1"></i>No Template Available';
            downloadBtn.style.display = 'block';
        }
        
        // Update submit button state based on document status
        const submitBtn = document.getElementById('submitDocumentBtn');
        console.log('Document status:', status); // Debug log
        
        // Handle reports differently
        if (window.isReport) {
            if (!window.canSubmitReport) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-x-circle me-1"></i>Cannot Submit';
                submitBtn.className = 'btn btn-danger disabled';
                submitBtn.title = window.reportSubmitMessage;
            } else if (status === 'Completed' || status === 'completed' || status === 'Approved' || status === 'approved') {
                // Report already submitted for this period
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Already Submitted';
                submitBtn.className = 'btn btn-success disabled';
            } else {
                // Can submit report
                const reportLabel = reportType === 'weekly' ? 'Weekly' : 'Monthly';
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Submit ' + reportLabel + ' Report';
                submitBtn.className = 'btn btn-success';
            }
        } else {
            // Handle regular documents
            if (status === 'Completed' || status === 'completed' || status === 'Approved' || status === 'approved') {
                // Document is already approved - no resubmission allowed
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-check-circle me-1"></i>Already Completed';
                submitBtn.className = 'btn btn-success disabled';
                console.log('Button disabled for completed document');
            } else if (status === 'Sent' || status === 'sent' || status === 'Pending' || status === 'pending') {
                // Document is already submitted and pending - no resubmission allowed
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="bi bi-clock me-1"></i>Pending Review';
                submitBtn.className = 'btn btn-warning disabled';
                console.log('Button disabled for pending document');
            } else if (status === 'Suggest Edits' || status === 'suggest_edits' || status === 'Revision Required' || status === 'revision_required') {
                // Document needs revision - allow resubmission
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-arrow-clockwise me-1"></i>Resubmit Document';
                submitBtn.className = 'btn btn-warning';
                console.log('Button enabled for revision required document');
            } else if (status === 'Expired' || status === 'expired' || status === 'Rejected' || status === 'rejected') {
                // Document was rejected - allow resubmission
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Submit Document';
                submitBtn.className = 'btn btn-danger';
                console.log('Button enabled for rejected document');
            } else {
                // Draft or not started - allow submission
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Submit Document';
                submitBtn.className = 'btn btn-success';
                console.log('Button enabled for draft document');
            }
        }
        
        // Display deadline
        const deadlineElement = document.getElementById('modalDocumentDeadline');
        if (deadline && deadline !== '') {
            const deadlineDate = new Date(deadline);
            const now = new Date();
            const isOverdue = deadlineDate < now;
            
            deadlineElement.innerHTML = `
                <span class="${isOverdue ? 'text-danger' : 'text-success'}">
                    <i class="bi bi-calendar-event me-1"></i>
                    ${deadlineDate.toLocaleDateString()} ${deadlineDate.toLocaleTimeString()}
                    ${isOverdue ? '<small class="text-danger">(Overdue)</small>' : ''}
                                    </span>
            `;
        } else {
            deadlineElement.innerHTML = '<span class="text-muted">No deadline set</span>';
        }
        
        // Display instructor feedback if available
        const feedbackSection = document.getElementById('instructorFeedbackSection');
        const feedbackElement = document.getElementById('instructorFeedback');
        
        if (instructorFeedback && instructorFeedback.trim() !== '') {
            feedbackElement.innerHTML = `<i class="bi bi-chat-dots me-2"></i>${instructorFeedback}`;
            feedbackSection.style.display = 'block';
        } else {
            feedbackSection.style.display = 'none';
        }
            
            // Load document preview
            const previewDiv = document.getElementById('documentPreview');
            if (filePath) {
                const fileExtension = filePath.split('.').pop().toLowerCase();
                
                if (fileExtension === 'pdf') {
                    previewDiv.innerHTML = `
                        <iframe src="view_document.php?file=${encodeURIComponent(filePath)}" 
                                width="100%" height="100%" style="border: none;">
                        </iframe>
                    `;
                } else {
                    previewDiv.innerHTML = `
                        <div class="text-center">
                            <i class="bi bi-file-earmark-text" style="font-size: 3rem;"></i>
                            <p class="mt-2">Preview not available for this file type</p>
                            <a href="view_document.php?file=${encodeURIComponent(filePath)}" class="btn btn-primary" target="_blank">
                                <i class="bi bi-download me-1"></i>Download File
                            </a>
                                </div>
                    `;
                }
            } else {
                previewDiv.innerHTML = `
                    <div class="text-center text-muted">
                        <i class="bi bi-file-earmark-text" style="font-size: 3rem;"></i>
                        <p class="mt-2">No template file available</p>
                                    </div>
                `;
            }
        }
        
        function getStatusColor(status) {
            switch(status) {
                case 'approved': return 'success';
                case 'pending': return 'warning';
                case 'revision_required': return 'danger';
                case 'rejected': return 'danger';
                default: return 'secondary';
        }
    }
    
    // Download template function
    function downloadTemplate() {
        if (window.currentDocumentFilePath && window.currentDocumentFilePath !== '') {
            // Create a temporary link to download the file
            const link = document.createElement('a');
            link.href = 'view_document.php?file=' + encodeURIComponent(window.currentDocumentFilePath);
            link.download = window.currentDocumentFilePath.split('/').pop(); // Get filename
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        } else {
            alert('No template file available for download.');
        }
    }
    
    // Download all completed documents function
    function downloadAllCompleted() {
        // Get all completed documents from the table
        const rows = document.querySelectorAll('tbody tr');
        const completedDocuments = [];
        
        rows.forEach(row => {
            const statusBadge = row.querySelector('.status-badge');
            const statusText = statusBadge ? statusBadge.textContent.trim() : '';
            
            if (statusText === 'Completed') {
                const documentName = row.querySelector('td:nth-child(2)').textContent.trim();
                const viewButton = row.querySelector('button[data-bs-toggle="modal"]');
                
                if (viewButton) {
                    const onclickAttr = viewButton.getAttribute('onclick');
                    if (onclickAttr) {
                        // Extract file path from onclick attribute
                        const filePathMatch = onclickAttr.match(/loadDocument\([^,]+,\s*[^,]+,\s*[^,]+,\s*'([^']+)'/);
                        if (filePathMatch && filePathMatch[1]) {
                            completedDocuments.push({
                                name: documentName,
                                filePath: filePathMatch[1]
                            });
                        }
                    }
                }
            }
        });
        
        if (completedDocuments.length === 0) {
            alert('No completed documents found to download.');
            return;
        }
        
        // Show confirmation
        const confirmMessage = `Found ${completedDocuments.length} completed document(s). Do you want to download all of them?\n\nDocuments:\n${completedDocuments.map(doc => `• ${doc.name}`).join('\n')}`;
        
        if (!confirm(confirmMessage)) {
            return;
        }
        
        // Download each document with a small delay to avoid browser blocking
        completedDocuments.forEach((doc, index) => {
            setTimeout(() => {
                const link = document.createElement('a');
                link.href = 'view_document.php?file=' + encodeURIComponent(doc.filePath);
                link.download = doc.name.replace(/[^a-zA-Z0-9.-]/g, '_') + '.pdf'; // Clean filename
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }, index * 500); // 500ms delay between downloads
        });
        
        // Show success message
        setTimeout(() => {
            alert(`Downloading ${completedDocuments.length} completed document(s). Please check your downloads folder.`);
        }, completedDocuments.length * 500);
    }
    
    // Select and submit document function
    function selectAndSubmitDocument() {
        // Check if submit button is disabled
        const submitBtn = document.getElementById('submitDocumentBtn');
        if (submitBtn.disabled) {
            // Check if this is a report that can't be submitted
            if (window.isReport && !window.canSubmitReport) {
                alert(window.reportSubmitMessage || 'You cannot submit this report at this time.');
            } else {
                const currentStatus = document.getElementById('modalDocumentStatus').textContent.trim();
                if (currentStatus === 'Already Completed' || currentStatus === 'Already Submitted') {
                    alert('This document has already been completed. You cannot submit it again.');
                } else if (currentStatus === 'Pending Review') {
                    alert('This document is already submitted and pending instructor review. Please wait for the instructor\'s feedback before resubmitting.');
                } else if (currentStatus === 'Cannot Submit') {
                    alert(window.reportSubmitMessage || 'You cannot submit this report at this time.');
                }
            }
            return;
        }
        
        // For reports, check if submission is allowed
        if (window.isReport && !window.canSubmitReport) {
            alert(window.reportSubmitMessage || 'You cannot submit this report at this time.');
            return;
        }
        
        // Check document status for additional validation
        const currentStatus = document.getElementById('modalDocumentStatus').textContent.trim();
        if (currentStatus === 'Already Completed' || currentStatus === 'Already Submitted' || currentStatus === 'Pending Review') {
            alert('This document cannot be submitted at this time. Please check the document status.');
            return;
        }
        
        // Create a hidden file input
        const fileInput = document.createElement('input');
        fileInput.type = 'file';
        fileInput.accept = '.pdf,.doc,.docx';
        fileInput.style.display = 'none';
        
        // Add to document
        document.body.appendChild(fileInput);
        
        // Trigger file picker
        fileInput.click();
        
        // Handle file selection
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            
            if (!file) {
                document.body.removeChild(fileInput);
                return;
            }
            
            // Validate file size (10MB limit)
            if (file.size > 10 * 1024 * 1024) {
                alert('File size must be less than 10MB.');
                document.body.removeChild(fileInput);
                return;
            }
            
            // Show confirmation in the current modal
            showSubmissionConfirmation(file);
            
            // Clean up
            document.body.removeChild(fileInput);
        });
    }
    
    // Show submission confirmation
    function showSubmissionConfirmation(file) {
        // Update the modal content to show confirmation
        const modalBody = document.querySelector('#documentModal .modal-body');
        const originalContent = modalBody.innerHTML;
        
        modalBody.innerHTML = `
            <div class="text-center">
                <div class="mb-4">
                    <i class="bi bi-file-earmark-check text-success" style="font-size: 4rem;"></i>
                </div>
                <h5>Confirm Document Submission</h5>
                <div class="alert alert-info">
                    <strong>File:</strong> ${file.name}<br>
                    <strong>Size:</strong> ${(file.size / 1024 / 1024).toFixed(2)} MB<br>
                    <strong>Type:</strong> ${file.type}
                </div>
                <p class="text-muted">Are you sure you want to submit this document?</p>
            </div>
        `;
        
        // Update modal footer
        const modalFooter = document.querySelector('#documentModal .modal-footer');
        if (!modalFooter) {
            // Create footer if it doesn't exist
            const modalContent = document.querySelector('#documentModal .modal-content');
            modalContent.innerHTML += `
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="cancelSubmission()">Cancel</button>
                    <button type="button" class="btn btn-success" onclick="confirmSubmission('${file.name}', '${file.size}', '${file.type}')">
                        <i class="bi bi-check-circle me-1"></i>Confirm Submission
                    </button>
                </div>
            `;
        } else {
            modalFooter.innerHTML = `
                <button type="button" class="btn btn-secondary" onclick="cancelSubmission()">Cancel</button>
                <button type="button" class="btn btn-success" onclick="confirmSubmission('${file.name}', '${file.size}', '${file.type}')">
                    <i class="bi bi-check-circle me-1"></i>Confirm Submission
                </button>
            `;
        }
        
        // Store the file for submission
        window.selectedFile = file;
    }
    
    // Cancel submission and restore original content
    function cancelSubmission() {
        // Restore original modal content
        location.reload();
    }
    
    // Confirm and submit the document
    function confirmSubmission(fileName, fileSize, fileType) {
        if (!window.selectedFile) {
            alert('No file selected.');
            return;
        }
        
        // Create form data
        const formData = new FormData();
        formData.append('documentFile', window.selectedFile);
        formData.append('documentId', window.currentDocumentId);
        formData.append('action', 'submit_document');
        
        // Show loading state
        const confirmBtn = document.querySelector('#documentModal .btn-success');
        const originalText = confirmBtn.innerHTML;
        confirmBtn.disabled = true;
        confirmBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Submitting...';
        
        // Submit the form
        fetch('documents.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Document submitted successfully!');
                // Close modal and refresh page
                bootstrap.Modal.getInstance(document.getElementById('documentModal')).hide();
                location.reload();
            } else {
                alert('Error submitting document: ' + (data.message || 'Unknown error'));
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalText;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error submitting document. Please try again.');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = originalText;
        });
    }
    </script>

    <!-- Document Modal -->
    <div class="modal fade" id="documentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="bi bi-file-earmark-text me-2"></i>Document Preview
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <h6>Document Info</h6>
                                <p><strong>Name:</strong> <span id="modalDocumentName"></span></p>
                                <p><strong>Status:</strong> <span id="modalDocumentStatus" class="badge"></span></p>
                                <p><strong>Deadline:</strong> <span id="modalDocumentDeadline"></span></p>
                                <div id="instructorFeedbackSection" style="display: none;">
                                    <p><strong>Instructor Feedback:</strong></p>
                                    <div id="instructorFeedback" class="alert alert-warning"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>Actions</h6>
                                <div class="d-grid gap-2">
                                    <button class="btn btn-primary" id="downloadTemplateBtn" onclick="downloadTemplate()">
                                        <i class="bi bi-download me-1"></i>Download Template
                                    </button>
                                    <button class="btn btn-success" id="submitDocumentBtn" onclick="selectAndSubmitDocument()">
                                        <i class="bi bi-upload me-1"></i>Submit Document
                                    </button>
                                </div>
                            </div>
                    </div>
                    
                    <!-- Document Preview Section -->
                    <div class="row">
                        <div class="col-12">
                            <h6>Document Preview</h6>
                            <div class="border rounded p-3" style="height: 400px; background-color: #f8f9fa;">
                                <div id="documentPreview" class="d-flex align-items-center justify-content-center h-100">
                                    <div class="text-center text-muted">
                                        <i class="bi bi-file-earmark-text" style="font-size: 3rem;"></i>
                                        <p class="mt-2">Document preview will appear here</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Report Submission Modal -->
    <div class="modal fade" id="reportSubmissionModal" tabindex="-1" aria-labelledby="reportSubmissionModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white" id="reportModalHeader">
                    <h5 class="modal-title" id="reportSubmissionModalLabel">
                        <i class="bi bi-calendar-week me-2"></i>Submit Weekly Report
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="reportSubmissionMessage" class="alert alert-info mb-3" role="alert">
                        <i class="bi bi-info-circle me-2"></i>
                        <span id="reportSubmissionMessageText">Please select a file to upload for your weekly report.</span>
                    </div>
                    <form id="reportSubmissionForm">
                        <input type="hidden" id="reportType" name="reportType" value="">
                        <div id="excuseDateField" class="mb-3" style="display: none;">
                            <label for="excuseDateInput" class="form-label">Date of Absence <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="excuseDateInput" name="excuseDate" required>
                            <small class="form-text text-muted">Select the date for which you are submitting this excuse document.</small>
                        </div>
                        <div class="mb-3">
                            <label for="reportFileInput" class="form-label">Select Report File</label>
                            <input type="file" class="form-control" id="reportFileInput" name="reportFile" accept=".pdf,.doc,.docx" required>
                            <small class="form-text text-muted">Accepted formats: PDF, DOC, DOCX (Max 10MB)</small>
                        </div>
                        <div id="reportSubmissionStatus" class="mb-3" style="display: none;">
                            <div class="alert" id="reportStatusAlert" role="alert"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" id="submitReportBtn" onclick="submitReport()">
                        <i class="bi bi-upload me-1"></i>Submit Report
                    </button>
                </div>
            </div>
        </div>
    </div>

</body>
</html>