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

// Check if submission ID is provided
if (!isset($_POST['submission_id']) || !is_numeric($_POST['submission_id'])) {
    header('Location: review_documents.php?error=invalid_submission');
    exit;
}

$submissionId = (int)$_POST['submission_id'];

try {
    // Approve the document
    $result = $controller->approveDocument($submissionId, $_SESSION['user_id'], '');
    
    if ($result['success']) {
        header('Location: review_documents.php?success=document_approved');
    } else {
        header('Location: review_documents.php?error=' . urlencode($result['message']));
    }
} catch (Exception $e) {
    header('Location: review_documents.php?error=' . urlencode($e->getMessage()));
}
exit;
