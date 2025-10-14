<?php
/**
 * Student Incomplete Profile Page
 * Shown when student's profile or documents are incomplete
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Utils\Database;

// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: /bmadOJT/public/login.php');
    exit;
}

$pdo = Database::getInstance();
$authService = new AuthenticationService();

// Get student profile
$stmt = $pdo->prepare("
    SELECT u.*, sp.* 
    FROM users u 
    LEFT JOIN student_profiles sp ON u.id = sp.user_id 
    WHERE u.id = ?
");
$stmt->execute([$_SESSION['user_id']]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Check compliance status
$user = App\Models\User::fromArray($profile);
$isCompliant = $authService->isCompliant($user);

// If student is now compliant, redirect to dashboard
if ($isCompliant) {
    header('Location: /bmadOJT/public/student/dashboard.php');
    exit;
}

// Check what's missing
$missingWorkplace = !$profile['workplace_latitude'] || !$profile['workplace_longitude'];
$missingDocuments = false;

// Check required documents (7 total)
$stmt = $pdo->prepare("
    SELECT COUNT(*) as approved_count 
    FROM student_documents sd 
    WHERE sd.student_id = ? AND sd.status = 'approved'
");
$stmt->execute([$_SESSION['user_id']]);
$docCount = $stmt->fetch(PDO::FETCH_ASSOC);
$missingDocuments = $docCount['approved_count'] < 7;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - OJT Route</title>
    <link rel="stylesheet" href="../css/style.css">
    <link rel="stylesheet" href="../css/sidebarstyle.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
        <style>
            body {
                font-family: 'Poppins', sans-serif;
                background-color: #f8f9fa;
            }
        </style>
<body class="bg-light">
            <!-- Sidebar -->
            <?php include 'student-sidebar.php'; ?>
            
            <!-- Main Content -->
            <main>
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Complete Your Profile</h1>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($_SESSION['error']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i>
                        <?= htmlspecialchars($_SESSION['success']) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['success']); ?>
                <?php endif; ?>

                <!-- Profile Completion Status -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-person-check me-2"></i>
                                    Profile Completion Status
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <!-- Workplace Information -->
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <?php if ($missingWorkplace): ?>
                                                <i class="bi bi-x-circle text-danger me-3 fs-4"></i>
                                                <div>
                                                    <h6 class="mb-1 text-danger">Workplace Information Missing</h6>
                                                    <small class="text-muted">You need to set your workplace location</small>
                                                </div>
                                            <?php else: ?>
                                                <i class="bi bi-check-circle text-success me-3 fs-4"></i>
                                                <div>
                                                    <h6 class="mb-1 text-success">Workplace Information Complete</h6>
                                                    <small class="text-muted">Your workplace location is set</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <!-- Required Documents -->
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center mb-3">
                                            <?php if ($missingDocuments): ?>
                                                <i class="bi bi-x-circle text-danger me-3 fs-4"></i>
                                                <div>
                                                    <h6 class="mb-1 text-danger">Required Documents Missing</h6>
                                                    <small class="text-muted">You need <?= 7 - $docCount['approved_count'] ?> more approved documents</small>
                                                </div>
                                            <?php else: ?>
                                                <i class="bi bi-check-circle text-success me-3 fs-4"></i>
                                                <div>
                                                    <h6 class="mb-1 text-success">Required Documents Complete</h6>
                                                    <small class="text-muted">All 7 required documents are approved</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Progress Bar -->
                                <div class="progress mb-3" style="height: 20px;">
                                    <?php 
                                    $completed = 0;
                                    if (!$missingWorkplace) $completed++;
                                    if (!$missingDocuments) $completed++;
                                    $progress = ($completed / 2) * 100;
                                    ?>
                                    <div class="progress-bar bg-success" role="progressbar" 
                                         style="width: <?= $progress ?>%" 
                                         aria-valuenow="<?= $progress ?>" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                        <?= round($progress) ?>% Complete
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="d-flex gap-2">
                                    <?php if ($missingWorkplace): ?>
                                        <a href="profile.php" class="btn btn-primary">
                                            <i class="bi bi-geo-alt me-2"></i>
                                            Set Workplace Location
                                        </a>
                                    <?php endif; ?>

                                    <?php if ($missingDocuments): ?>
                                        <a href="documents.php" class="btn btn-outline-primary">
                                            <i class="bi bi-file-earmark-text me-2"></i>
                                            View Documents
                                        </a>
                                    <?php endif; ?>

                                    <a href="dashboard.php" class="btn btn-outline-secondary">
                                        <i class="bi bi-arrow-left me-2"></i>
                                        Back to Dashboard
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Help Information -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-question-circle me-2"></i>
                                    Need Help?
                                </h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-3">To complete your profile, you need to:</p>
                                <ul class="mb-3">
                                    <li><strong>Set your workplace location:</strong> Click "Set Workplace Location" to add your workplace coordinates using the map interface.</li>
                                    <li><strong>Submit required documents:</strong> Upload and get approval for all 7 required documents (MOA, Endorsement Letter, Parental Consent, etc.).</li>
                                </ul>
                                <p class="mb-0">
                                    <strong>Contact your instructor</strong> if you need assistance with any of these requirements.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
