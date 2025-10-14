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

// Check if submission IDs are provided
if (!isset($_POST['submission_ids']) || empty($_POST['submission_ids'])) {
    header('Location: review_documents.php?error=no_submissions_selected');
    exit;
}

$submissionIds = $_POST['submission_ids'];

// Validate that all IDs are numeric
foreach ($submissionIds as $id) {
    if (!is_numeric($id)) {
        header('Location: review_documents.php?error=invalid_submission_id');
        exit;
    }
}

try {
    // Convert to integers
    $submissionIds = array_map('intval', $submissionIds);
    
    // Perform bulk approval
    $result = $controller->bulkApproveDocuments($submissionIds, $_SESSION['user_id']);
    
    if ($result['success']) {
        header('Location: review_documents.php?success=' . urlencode($result['message']));
    } else {
        header('Location: review_documents.php?error=' . urlencode($result['message']));
    }
} catch (Exception $e) {
    header('Location: review_documents.php?error=' . urlencode($e->getMessage()));
}
exit;
