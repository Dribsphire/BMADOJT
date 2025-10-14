<?php
session_start();
require_once '../../vendor/autoload.php';
require_once '../../src/Utils/Database.php';
require_once '../../src/Controllers/InstructorDocumentController.php';
require_once '../../src/Middleware/AuthMiddleware.php';

use App\Controllers\InstructorDocumentController;
use App\Middleware\AuthMiddleware;

// Check authentication
$authMiddleware = new AuthMiddleware();
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

if (!$authMiddleware->requireRole('instructor')) {
    $authMiddleware->redirectToUnauthorized();
}

$controller = new InstructorDocumentController();

// Check if submission ID and feedback are provided
if (!isset($_POST['submission_id']) || !is_numeric($_POST['submission_id'])) {
    header('Location: review_documents.php?error=invalid_submission');
    exit;
}

if (!isset($_POST['feedback']) || empty(trim($_POST['feedback']))) {
    header('Location: review_documents.php?error=feedback_required');
    exit;
}

$submissionId = (int)$_POST['submission_id'];
$feedback = trim($_POST['feedback']);

try {
    // Request revision
    $result = $controller->requestRevision($submissionId, $_SESSION['user_id'], $feedback);
    
    if ($result['success']) {
        header('Location: review_documents.php?success=revision_requested');
    } else {
        header('Location: review_documents.php?error=' . urlencode($result['message']));
    }
} catch (Exception $e) {
    header('Location: review_documents.php?error=' . urlencode($e->getMessage()));
}
exit;
