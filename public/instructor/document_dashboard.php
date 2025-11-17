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
$user = $authMiddleware->getCurrentUser();
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
    $sectionIds = [0]; // Prevent SQL errors
}

// Build WHERE clause for sections
$sectionPlaceholders = implode(',', array_fill(0, count($sectionIds), '?'));
$sectionParams = $sectionIds;

// Get overall document statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT u.id) as total_students,
        COUNT(DISTINCT sd.id) as total_submissions,
        COUNT(DISTINCT CASE WHEN sd.status = 'approved' THEN sd.id END) as approved_documents,
        COUNT(DISTINCT CASE WHEN sd.status = 'pending' THEN sd.id END) as pending_documents,
        COUNT(DISTINCT CASE WHEN sd.status = 'revision_required' THEN sd.id END) as revision_required,
        COUNT(DISTINCT CASE WHEN sd.status = 'rejected' THEN sd.id END) as rejected_documents,
        COUNT(DISTINCT CASE WHEN d.deadline IS NOT NULL AND d.deadline < CURDATE() AND (sd.status IS NULL OR sd.status != 'approved') THEN u.id END) as overdue_count
    FROM users u
    LEFT JOIN sections s ON u.section_id = s.id
    LEFT JOIN documents d ON d.uploaded_for_section = u.section_id AND d.is_required = 1
    LEFT JOIN student_documents sd ON u.id = sd.student_id AND sd.document_id = d.id
    WHERE u.role = 'student' AND u.section_id IN ($sectionPlaceholders)
");
$stmt->execute($sectionParams);
$overallStats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get student document reports
$stmt = $pdo->prepare("
    SELECT 
        u.id,
        u.school_id,
        u.full_name,
        u.email,
        s.section_code,
        s.section_name,
        COUNT(DISTINCT CASE WHEN d.is_required = 1 THEN d.id END) as total_required_documents,
        COUNT(DISTINCT CASE WHEN sd.status = 'approved' AND d.is_required = 1 THEN sd.id END) as approved_documents,
        COUNT(DISTINCT CASE WHEN sd.status = 'pending' AND d.is_required = 1 THEN sd.id END) as pending_documents,
        COUNT(DISTINCT CASE WHEN sd.status = 'revision_required' AND d.is_required = 1 THEN sd.id END) as revision_required,
        COUNT(DISTINCT CASE WHEN sd.status = 'rejected' AND d.is_required = 1 THEN sd.id END) as rejected_documents,
        COUNT(DISTINCT CASE WHEN d.deadline IS NOT NULL AND d.deadline < CURDATE() AND (sd.status IS NULL OR sd.status != 'approved') THEN d.id END) as overdue_count
    FROM users u
    LEFT JOIN sections s ON u.section_id = s.id
    LEFT JOIN documents d ON d.uploaded_for_section = u.section_id AND d.is_required = 1
    LEFT JOIN student_documents sd ON u.id = sd.student_id AND sd.document_id = d.id
    WHERE u.role = 'student' AND u.section_id IN ($sectionPlaceholders)
    GROUP BY u.id, u.school_id, u.full_name, u.email, s.section_code, s.section_name
    ORDER BY s.section_code, u.full_name
");
$stmt->execute($sectionParams);
$studentReports = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Dashboard - OJT Route</title>
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
        .chart-container {
            position: relative;
            height: 300px;
        }
        .filter-card {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
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
                <i class="bi bi-files me-2"></i>Document Dashboard
            </h1>
            <div class="btn-toolbar mb-2 mb-md-0">
                <div class="btn-group me-2">
                    <a href="?export=csv" class="btn btn-outline-primary">
                        <i class="bi bi-download me-1"></i>Export CSV
                    </a>
                </div>
            </div>
        </div>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?= $overallStats['total_students'] ?></h3>
                        <p class="card-text" style="color: white;">Total Students</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card success">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?= $overallStats['approved_documents'] ?></h3>
                        <p class="card-text" style="color: white;">Approved Documents</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card warning">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?= $overallStats['pending_documents'] ?></h3>
                        <p class="card-text" style="color: white;">Pending Review</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card info">
                    <div class="card-body text-center">
                        <h3 class="card-title"><?= $overallStats['overdue_count'] ?></h3>
                        <p class="card-text" style="color: white;">Overdue Documents</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Document Reports -->
        <div id="document-reports">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="bi bi-people me-2"></i>Student Document Reports
                    </h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Section</th>
                                    <th>Required</th>
                                    <th>Approved</th>
                                    <th>Pending</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($studentReports as $student): 
                                    $total = (int)($student['total_required_documents'] ?? 0);
                                    $approved = (int)($student['approved_documents'] ?? 0);
                                    $pending = (int)($student['pending_documents'] ?? 0);
                                    $revision = (int)($student['revision_required'] ?? 0);
                                    $rejected = (int)($student['rejected_documents'] ?? 0);
                                    $overdue = (int)($student['overdue_count'] ?? 0);
                                    
                                    // Determine status
                                    if ($total === 0) {
                                        $status = 'No Requirements';
                                        $statusClass = 'badge bg-secondary';
                                    } else if ($approved === $total && $pending === 0 && $revision === 0 && $rejected === 0) {
                                        $status = 'Complete';
                                        $statusClass = 'badge bg-success';
                                    } else if ($revision > 0 || $rejected > 0) {
                                        $status = 'Needs Revision';
                                        $statusClass = 'badge bg-danger';
                                    } else if ($pending > 0) {
                                        $status = 'Pending Review';
                                        $statusClass = 'badge bg-warning';
                                    } else if ($overdue > 0) {
                                        $status = 'Overdue';
                                        $statusClass = 'badge bg-danger';
                                    } else {
                                        $status = 'In Progress';
                                        $statusClass = 'badge bg-info';
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <div>
                                                <strong><?= htmlspecialchars($student['full_name']) ?></strong>
                                                <br><small class="text-muted"><?= htmlspecialchars($student['school_id']) ?></small>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= htmlspecialchars($student['section_code'] ?? 'No Section') ?></span>
                                        </td>
                                        <td><?= $total ?></td>
                                        <td>
                                            <span class="badge bg-success"><?= $approved ?></span>
                                        </td>
                                        <td><?= $pending ?></td>
                                        <td><span class="<?= $statusClass ?>"><?= $status ?></span></td>
                                        <td>
                                            <a href="review_documents.php?student_id=<?= $student['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bootstrap JS -->
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>
