<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../src/Utils/Database.php';
require_once '../../src/Middleware/AuthMiddleware.php';

use App\Middleware\AuthMiddleware;
use App\Utils\Database;

// Check authentication
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('instructor')) {
    $authMiddleware->redirectToUnauthorized();
}

// Get current user
$instructorId = $_SESSION['user_id'];

// Initialize database
$pdo = Database::getInstance();

// Get instructor's sections (supporting both junction table and old method)
$stmt = $pdo->prepare("
    SELECT DISTINCT s.id, s.section_code, s.section_name
    FROM sections s
    LEFT JOIN instructor_sections is_rel ON s.id = is_rel.section_id
    WHERE (is_rel.instructor_id = ? OR s.instructor_id = ? OR (SELECT section_id FROM users WHERE id = ? AND role = 'instructor') = s.id)
");
$stmt->execute([$instructorId, $instructorId, $instructorId]);
$instructorSections = $stmt->fetchAll(PDO::FETCH_ASSOC);
$sectionIds = array_column($instructorSections, 'id');

if (empty($sectionIds)) {
    die("No section assigned to this instructor. Please contact the administrator.");
}

// Build WHERE clause for sections
$sectionPlaceholders = implode(',', array_fill(0, count($sectionIds), '?'));
$sectionParams = $sectionIds;

// Get all required documents
$stmt = $pdo->query("SELECT id, document_name FROM documents WHERE is_required = 1 ORDER BY document_name");
$documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all students in the instructor's sections
$stmt = $pdo->prepare("
    SELECT id, school_id, full_name, section_id
    FROM users 
    WHERE section_id IN ($sectionPlaceholders) AND role = 'student'
    ORDER BY full_name
");
$stmt->execute($sectionParams);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all submitted documents for these students (for monthly view)
$submittedDocs = [];
$monthlyStats = [];
if (!empty($students)) {
    $studentIds = array_column($students, 'id');
    $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
    
    $stmt = $pdo->prepare("
        SELECT student_id, document_id, status, DATE(submitted_at) as submission_date
        FROM student_documents 
        WHERE student_id IN ($studentPlaceholders)
    ");
    $stmt->execute($studentIds);
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $submittedDocs[$row['student_id']][$row['document_id']] = $row['status'];
        
        // Group by month for monthly stats
        if ($row['submission_date']) {
            $month = date('Y-m', strtotime($row['submission_date']));
            if (!isset($monthlyStats[$month])) {
                $monthlyStats[$month] = ['total' => 0, 'approved' => 0, 'pending' => 0, 'rejected' => 0];
            }
            $monthlyStats[$month]['total']++;
            if ($row['status'] === 'approved') $monthlyStats[$month]['approved']++;
            elseif ($row['status'] === 'pending') $monthlyStats[$month]['pending']++;
            elseif ($row['status'] === 'rejected') $monthlyStats[$month]['rejected']++;
        }
    }
}

// Get weekly and monthly reports from student_reports table
$studentReports = [];
$weeklyReports = [];
$monthlyReports = [];

// Check if student_reports table exists
$tableExists = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'student_reports'");
    $tableExists = $stmt->rowCount() > 0;
} catch (Exception $e) {
    $tableExists = false;
}

if ($tableExists && !empty($students)) {
    $studentIds = array_column($students, 'id');
    $studentPlaceholders = implode(',', array_fill(0, count($studentIds), '?'));
    
    // Get all student reports (weekly and monthly)
    $stmt = $pdo->prepare("
        SELECT 
            sr.student_id,
            sr.report_type,
            sr.report_period,
            sr.status,
            sr.submitted_at
        FROM student_reports sr
        INNER JOIN users u ON sr.student_id = u.id
        WHERE sr.student_id IN ($studentPlaceholders)
        AND sr.report_type IN ('weekly', 'monthly')
        AND u.section_id IN ($sectionPlaceholders)
        ORDER BY sr.student_id, sr.report_type, sr.report_period
    ");
    $stmt->execute(array_merge($studentIds, $sectionParams));
    $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Organize reports by student
    foreach ($reports as $report) {
        $studentId = $report['student_id'];
        $reportType = $report['report_type'];
        $period = $report['report_period'];
        $status = $report['status'];
        
        if ($reportType === 'weekly') {
            $weeklyReports[$studentId][$period] = $status;
        } elseif ($reportType === 'monthly') {
            $monthlyReports[$studentId][$period] = $status;
        }
    }
    
    // Generate list of weeks (assuming 8 weeks OJT period)
    // Get OJT start date from first student's profile to determine weeks
    $stmt = $pdo->prepare("
        SELECT MIN(sp.ojt_start_date) as ojt_start
        FROM users u
        LEFT JOIN student_profiles sp ON u.id = sp.user_id
        WHERE u.id IN ($studentPlaceholders)
        AND u.section_id IN ($sectionPlaceholders)
        AND sp.ojt_start_date IS NOT NULL
    ");
    $stmt->execute(array_merge($studentIds, $sectionParams));
    $ojtStart = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $weeks = [];
    if ($ojtStart && $ojtStart['ojt_start']) {
        $startDate = new DateTime($ojtStart['ojt_start']);
        $endDate = clone $startDate;
        $endDate->modify('+56 days'); // 8 weeks = 56 days
        
        // First, collect all weeks and group them by month
        $weeksByMonth = [];
        $currentDate = clone $startDate;
        $weekCount = 0;
        
        while ($currentDate <= $endDate && $weekCount < 8) {
            $year = (int)$currentDate->format('Y');
            $weekNum = (int)$currentDate->format('W');
            $monthName = $currentDate->format('F');
            $monthYear = $currentDate->format('Y-m');
            $weekKey = sprintf('%d-W%02d', $year, $weekNum);
            
            if (!isset($weeksByMonth[$monthYear])) {
                $weeksByMonth[$monthYear] = [
                    'month' => $monthName,
                    'year' => $year,
                    'weeks' => []
                ];
            }
            
            $weeksByMonth[$monthYear]['weeks'][] = [
                'key' => $weekKey,
                'date' => clone $currentDate
            ];
            
            // Move to next week (7 days forward)
            $currentDate->modify('+7 days');
            $weekCount++;
        }
        
        // Sort months chronologically (oldest first)
        ksort($weeksByMonth);
        
        // Build final weeks array, grouped by month
        foreach ($weeksByMonth as $monthYear => $monthData) {
            // Sort weeks within month chronologically
            usort($monthData['weeks'], function($a, $b) {
                return $a['date'] <=> $b['date'];
            });
            
            // Add weeks with week numbers within the month
            $weekInMonth = 1;
            foreach ($monthData['weeks'] as $weekData) {
                $weeks[] = [
                    'number' => count($weeks) + 1,
                    'key' => $weekData['key'],
                    'label' => "Week $weekInMonth of " . $monthData['month'],
                    'month' => $monthData['month'],
                    'monthYear' => $monthYear
                ];
                $weekInMonth++;
            }
        }
    } else {
        // Fallback: generate weeks from current date backwards, grouped by month
        $currentDate = new DateTime();
        $weeksByMonth = [];
        
        for ($i = 0; $i < 8; $i++) {
            $weekDate = clone $currentDate;
            $weekDate->modify('-' . ($i * 7) . ' days');
            $year = (int)$weekDate->format('Y');
            $weekNum = (int)$weekDate->format('W');
            $monthName = $weekDate->format('F');
            $monthYear = $weekDate->format('Y-m');
            $weekKey = sprintf('%d-W%02d', $year, $weekNum);
            
            if (!isset($weeksByMonth[$monthYear])) {
                $weeksByMonth[$monthYear] = [
                    'month' => $monthName,
                    'year' => $year,
                    'weeks' => []
                ];
            }
            
            $weeksByMonth[$monthYear]['weeks'][] = [
                'key' => $weekKey,
                'date' => clone $weekDate
            ];
        }
        
        // Sort months chronologically (oldest first)
        ksort($weeksByMonth);
        
        foreach ($weeksByMonth as $monthYear => $monthData) {
            // Sort weeks within month chronologically
            usort($monthData['weeks'], function($a, $b) {
                return $a['date'] <=> $b['date'];
            });
            
            // Add weeks to final array with week numbers within month
            $weekInMonth = 1;
            foreach ($monthData['weeks'] as $weekData) {
                $weeks[] = [
                    'number' => count($weeks) + 1,
                    'key' => $weekData['key'],
                    'label' => "Week $weekInMonth of " . $monthData['month'],
                    'month' => $monthData['month'],
                    'monthYear' => $monthYear
                ];
                $weekInMonth++;
            }
        }
    }
}

// Get section info for display
$sectionInfo = [];
foreach ($instructorSections as $section) {
    $sectionInfo[$section['id']] = $section;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Reports - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <script type="text/javascript" src="../js/sidebarSlide.js" defer></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
        }
        .stats-card {
            background:#0ea539;
            color: white;
            border: none;
        }
        .stats-card.success {
            background:#0ea539 ;
        }
        .stats-card.warning {
            background:#0ea539;
        }
        .stats-card.info {
            background:#0ea539;
        }
        .document-header {
            writing-mode: vertical-rl;
            transform: rotate(180deg);
            white-space: nowrap;
            text-align: center;
            padding: 10px 5px;
            font-size: 12px;
            width: 30px;
        }
        .student-name {
            position: sticky;
            left: 0;
            background: white;
            z-index: 1;
        }
        .table thead th {
            position: sticky;
            top: 0;
            background: #f8f9fa;
            z-index: 2;
        }
        .status-approved {
            color: #198754;
        }
        .status-pending {
            color: #ffc107;
        }
        .status-rejected {
            color: #dc3545;
        }
        .status-revision_required {
            color: #fd7e14;
        }
        .report-tab {
            cursor: pointer;
        }
        .report-content {
            display: none;
        }
        .report-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <?php include 'teacher-sidebar.php'; ?>
    
    <main>
        <?php include 'navigation-header.php'; ?>
        <br>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">
                <i class="bi bi-files me-2"></i>Document Reports
            </h1>
        </div>

        <!-- Tab Buttons -->
        <div class="row g-2 mb-3">
            <div class="col">
                <button type="button" class="btn btn-primary w-100 report-tab active" data-tab="monthly-tab">
                    <i class="bi bi-calendar-month me-1"></i>Document Reports
                </button>
            </div>
            <div class="col">
                <button type="button" class="btn btn-outline-primary w-100 report-tab" data-tab="weekly-tab">
                    <i class="bi bi-calendar-week me-1"></i>Weekly and Monthly Reports
                </button>
            </div>
        </div>

        <!-- Monthly Reports Tab -->
        <div id="monthly-tab" class="report-content active">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-month me-2"></i>Document Submission Status
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th class="student-name">Student</th>
                                    <?php foreach ($documents as $doc): ?>
                                        <th class="text-center">
                                            <div class="document-header">
                                                <?= htmlspecialchars($doc['document_name']) ?>
                                            </div>
                                        </th>
                                    <?php endforeach; ?>
                                    <th class="text-center">
                                        <div class="document-header">
                                            Completion
                                        </div>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($students)): ?>
                                    <tr>
                                        <td colspan="<?= count($documents) + 2 ?>" class="text-center">No students found in your section.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($students as $student): 
                                        $studentId = $student['id'];
                                        $submitted = $submittedDocs[$studentId] ?? [];
                                        $completedCount = 0;
                                    ?>
                                        <tr>
                                            <td class="student-name">
                                                <div>
                                                    <strong><?= htmlspecialchars($student['full_name']) ?></strong>
                                                    <?php if (!empty($student['school_id'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($student['school_id']) ?></small>
                                                    <?php endif; ?>
                                                    <?php if (isset($sectionInfo[$student['section_id']])): ?>
                                                        <br><span class="badge bg-info"><?= htmlspecialchars($sectionInfo[$student['section_id']]['section_code']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <?php foreach ($documents as $doc): 
                                                $status = $submitted[$doc['id']] ?? null;
                                                if ($status === 'approved') $completedCount++;
                                            ?>
                                                <td class="text-center align-middle">
                                                    <?php if ($status): ?>
                                                        <?php
                                                        $iconClass = 'bi-check-circle-fill';
                                                        $statusClass = 'status-approved';
                                                        if ($status === 'pending') {
                                                            $iconClass = 'bi-hourglass-split';
                                                            $statusClass = 'status-pending';
                                                        } elseif ($status === 'rejected') {
                                                            $iconClass = 'bi-x-circle-fill';
                                                            $statusClass = 'status-rejected';
                                                        } elseif ($status === 'revision_required') {
                                                            $iconClass = 'bi-exclamation-triangle-fill';
                                                            $statusClass = 'status-revision_required';
                                                        }
                                                        ?>
                                                        <i class="bi <?= $iconClass ?> <?= $statusClass ?>" 
                                                        title="<?= ucfirst(str_replace('_', ' ', $status)) ?>"
                                                        data-bs-toggle="tooltip"></i>
                                                    <?php else: ?>
                                                        <i class="bi bi-dash-circle text-muted" title="Not Submitted"></i>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                            
                                            <td class="text-center align-middle">
                                                <?php 
                                                    $completion = count($documents) > 0 
                                                        ? round(($completedCount / count($documents)) * 100) 
                                                        : 0;
                                                ?>
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar <?= $completion == 100 ? 'bg-success' : ($completion >= 50 ? 'bg-warning' : 'bg-danger') ?>" 
                                                        role="progressbar" 
                                                        style="width: <?= $completion ?>%" 
                                                        aria-valuenow="<?= $completion ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="100">
                                                        <?= $completion ?>%
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        </div>

        <!-- Weekly Reports Tab -->
        <div id="weekly-tab" class="report-content">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-calendar-week me-2"></i>Weekly and Monthly Reports Checklist
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (empty($students)): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-inbox" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="text-muted mt-3">No students found in your section.</p>
                        </div>
                    <?php elseif (!$tableExists): ?>
                        <div class="text-center py-5">
                            <i class="bi bi-exclamation-triangle" style="font-size: 3rem; color: #ffc107;"></i>
                            <p class="text-muted mt-3">Student reports system not initialized.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead>
                                    <tr>
                                        <th class="student-name" style="position: sticky; left: 0; background: #f8f9fa; z-index: 3;">Student</th>
                                        <?php if (!empty($weeks)): ?>
                                            <?php foreach ($weeks as $week): ?>
                                                <th class="text-center" style="min-width: 80px;">
                                                    <div class="document-header">
                                                        <?= htmlspecialchars($week['label']) ?>
                                                    </div>
                                                </th>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                        <th class="text-center">
                                            <div class="document-header">
                                                Monthly Report
                                            </div>
                                        </th>
                                        <th class="text-center">
                                            <div class="document-header">
                                                Completion
                                            </div>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($students as $student): 
                                        $studentId = $student['id'];
                                        $studentWeekly = $weeklyReports[$studentId] ?? [];
                                        $studentMonthly = $monthlyReports[$studentId] ?? [];
                                        
                                        // Count approved reports
                                        $approvedWeekly = 0;
                                        $approvedMonthly = 0;
                                        
                                        if (!empty($weeks)) {
                                            foreach ($weeks as $week) {
                                                if (isset($studentWeekly[$week['key']]) && $studentWeekly[$week['key']] === 'approved') {
                                                    $approvedWeekly++;
                                                }
                                            }
                                        }
                                        
                                        // Count monthly reports (check all months)
                                        foreach ($studentMonthly as $status) {
                                            if ($status === 'approved') {
                                                $approvedMonthly++;
                                            }
                                        }
                                        
                                        // Calculate completion
                                        $totalRequired = (!empty($weeks) ? count($weeks) : 0) + 1; // 8 weeks + 1 monthly
                                        $totalApproved = $approvedWeekly + $approvedMonthly;
                                        $completion = $totalRequired > 0 ? round(($totalApproved / $totalRequired) * 100) : 0;
                                    ?>
                                        <tr>
                                            <td class="student-name" style="position: sticky; left: 0; background: white; z-index: 2;">
                                                <div>
                                                    <strong><?= htmlspecialchars($student['full_name']) ?></strong>
                                                    <?php if (!empty($student['school_id'])): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($student['school_id']) ?></small>
                                                    <?php endif; ?>
                                                    <?php if (isset($sectionInfo[$student['section_id']])): ?>
                                                        <br><span class="badge bg-info"><?= htmlspecialchars($sectionInfo[$student['section_id']]['section_code']) ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            
                                            <?php if (!empty($weeks)): ?>
                                                <?php foreach ($weeks as $week): 
                                                    $weekStatus = $studentWeekly[$week['key']] ?? null;
                                                ?>
                                                    <td class="text-center align-middle">
                                                        <?php if ($weekStatus): ?>
                                                            <?php
                                                            $iconClass = 'bi-check-circle-fill';
                                                            $statusClass = 'status-approved';
                                                            if ($weekStatus === 'pending') {
                                                                $iconClass = 'bi-hourglass-split';
                                                                $statusClass = 'status-pending';
                                                            } elseif ($weekStatus === 'rejected') {
                                                                $iconClass = 'bi-x-circle-fill';
                                                                $statusClass = 'status-rejected';
                                                            } elseif ($weekStatus === 'revision_required') {
                                                                $iconClass = 'bi-exclamation-triangle-fill';
                                                                $statusClass = 'status-revision_required';
                                                            }
                                                            ?>
                                                            <i class="bi <?= $iconClass ?> <?= $statusClass ?>" 
                                                            title="<?= ucfirst(str_replace('_', ' ', $weekStatus)) ?>"
                                                            data-bs-toggle="tooltip"></i>
                                                        <?php else: ?>
                                                            <i class="bi bi-dash-circle text-muted" title="Not Submitted"></i>
                                                        <?php endif; ?>
                                                    </td>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                            
                                            <!-- Monthly Report Column -->
                                            <td class="text-center align-middle">
                                                <?php 
                                                // Get all monthly reports with their periods and statuses
                                                $monthlyReportsList = [];
                                                foreach ($studentMonthly as $period => $status) {
                                                    // Parse period (format: "2024-01" for January 2024)
                                                    if (preg_match('/^(\d{4})-(\d{2})$/', $period, $matches)) {
                                                        $year = $matches[1];
                                                        $monthNum = (int)$matches[2];
                                                        $monthName = date('F', mktime(0, 0, 0, $monthNum, 1)); // Full month name
                                                        $monthlyReportsList[] = [
                                                            'period' => $period,
                                                            'month' => $monthName,
                                                            'year' => $year,
                                                            'status' => $status
                                                        ];
                                                    }
                                                }
                                                
                                                // Sort by period (chronologically)
                                                usort($monthlyReportsList, function($a, $b) {
                                                    return strcmp($a['period'], $b['period']);
                                                });
                                                
                                                if (!empty($monthlyReportsList)): 
                                                    // Show all monthly reports with their months
                                                    foreach ($monthlyReportsList as $report):
                                                        $iconClass = 'bi-check-circle-fill';
                                                        $statusClass = 'status-approved';
                                                        if ($report['status'] === 'pending') {
                                                            $iconClass = 'bi-hourglass-split';
                                                            $statusClass = 'status-pending';
                                                        } elseif ($report['status'] === 'rejected') {
                                                            $iconClass = 'bi-x-circle-fill';
                                                            $statusClass = 'status-rejected';
                                                        } elseif ($report['status'] === 'revision_required') {
                                                            $iconClass = 'bi-exclamation-triangle-fill';
                                                            $statusClass = 'status-revision_required';
                                                        }
                                                ?>
                                                    <div class="mb-1">
                                                        <i class="bi <?= $iconClass ?> <?= $statusClass ?>" 
                                                        title="<?= htmlspecialchars($report['month'] . ' ' . $report['year'] . ' - ' . ucfirst(str_replace('_', ' ', $report['status']))) ?>"
                                                        data-bs-toggle="tooltip"></i>
                                                        <small class="d-block text-muted" style="font-size: 0.7rem;">
                                                            <?= htmlspecialchars($report['month']) ?>
                                                        </small>
                                                    </div>
                                                <?php 
                                                    endforeach;
                                                else: 
                                                ?>
                                                    <i class="bi bi-dash-circle text-muted" title="Not Submitted"></i>
                                                <?php endif; ?>
                                            </td>
                                            
                                            <!-- Completion Column -->
                                            <td class="text-center align-middle">
                                                <div class="progress" style="height: 20px;">
                                                    <div class="progress-bar <?= $completion == 100 ? 'bg-success' : ($completion >= 50 ? 'bg-warning' : 'bg-danger') ?>" 
                                                        role="progressbar" 
                                                        style="width: <?= $completion ?>%" 
                                                        aria-valuenow="<?= $completion ?>" 
                                                        aria-valuemin="0" 
                                                        aria-valuemax="100">
                                                        <?= $completion ?>%
                                                    </div>
                                                </div>
                                                <small class="text-muted d-block mt-1">
                                                    <?= $totalApproved ?>/<?= $totalRequired ?> Approved
                                                </small>
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

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        
        <script>
            // Tab switching
            document.addEventListener('DOMContentLoaded', function() {
                const tabButtons = document.querySelectorAll('.report-tab');
                const tabContents = document.querySelectorAll('.report-content');
                
                tabButtons.forEach(tab => {
                    tab.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        const tabId = this.getAttribute('data-tab');
                        
                        // Update button styles
                        tabButtons.forEach(t => {
                            t.classList.remove('btn-primary', 'active');
                            t.classList.add('btn-outline-primary');
                        });
                        this.classList.remove('btn-outline-primary');
                        this.classList.add('btn-primary', 'active');
                        
                        // Show/hide tab contents
                        tabContents.forEach(content => {
                            content.classList.remove('active');
                        });
                        document.getElementById(tabId).classList.add('active');
                    });
                });
                
                // Enable tooltips
                var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
                var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                    return new bootstrap.Tooltip(tooltipTriggerEl);
                });
            });
        </script>
    </body>
</html>
