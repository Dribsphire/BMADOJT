<?php

/**
 * Get Template Preview API
 * Returns template preview content
 */

require_once '../../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Utils\AdminAccess;
use App\Templates\EmailTemplates;

// Start session
session_start();

// Initialize authentication
$authMiddleware = new AuthMiddleware();

// Check authentication and authorization
if (!$authMiddleware->check()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check admin access
try {
    AdminAccess::requireAdminAccess();
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

// Get template name from request
$template_name = $_GET['template'] ?? '';

if (empty($template_name) || $template_name === 'custom') {
    http_response_code(400);
    echo json_encode(['error' => 'Template name required']);
    exit;
}

try {
    $templates = new EmailTemplates();
    
    // Get template based on name
    $template = null;
    switch ($template_name) {
        case 'welcome':
            $template = $templates->getWelcomeTemplate();
            break;
        case 'password_reset':
            $template = $templates->getPasswordResetTemplate();
            break;
        case 'attendance_notification':
            $template = $templates->getAttendanceNotificationTemplate();
            break;
        case 'document_submission':
            $template = $templates->getDocumentSubmissionTemplate();
            break;
        case 'forgot_timeout':
            $template = $templates->getForgotTimeoutTemplate();
            break;
        case 'instructor_notification':
            $template = $templates->getInstructorNotificationTemplate();
            break;
        case 'system_announcement':
            $template = $templates->getSystemAnnouncementTemplate();
            break;
        case 'compliance_reminder':
            $template = $templates->getComplianceReminderTemplate();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid template name']);
            exit;
    }
    
    if (!$template) {
        http_response_code(404);
        echo json_encode(['error' => 'Template not found']);
        exit;
    }
    
    // Get template variables
    $variables = $templates->getTemplateVariables($template_name);
    
    // Sample variables for preview
    $sample_variables = [
        'user_name' => 'John Doe',
        'site_name' => 'OJT Route System',
        'current_year' => date('Y'),
        'site_url' => 'http://localhost/bmadOJT',
        'login_url' => 'http://localhost/bmadOJT/login.php',
        'support_email' => 'support@chmsu.edu.ph',
        'attendance_date' => date('Y-m-d'),
        'time_in' => '08:00 AM',
        'time_out' => '05:00 PM',
        'hours_earned' => '8.0',
        'document_name' => 'OJT Plan',
        'submission_date' => date('Y-m-d'),
        'status' => 'Pending Review',
        'block_type' => 'Morning',
        'request_date' => date('Y-m-d'),
        'instructor_name' => 'Dr. Smith',
        'student_name' => 'Jane Doe',
        'action_type' => 'Document Submission',
        'details' => 'Student submitted OJT Plan for review',
        'announcement_title' => 'System Maintenance',
        'announcement_content' => 'The system will be under maintenance on Sunday.',
        'effective_date' => date('Y-m-d', strtotime('+1 day')),
        'missing_documents' => '<ul><li>OJT Plan</li><li>Parent Consent</li></ul>',
        'deadline' => date('Y-m-d', strtotime('+7 days')),
        'completion_url' => 'http://localhost/bmadOJT/student/documents.php'
    ];
    
    // Replace variables in template
    $preview_content = $templates->replaceVariables($template['body'], $sample_variables);
    
    echo json_encode([
        'success' => true,
        'template' => [
            'name' => $template_name,
            'subject' => $template['subject'],
            'body' => $preview_content,
            'is_html' => $template['is_html']
        ],
        'variables' => $variables,
        'sample_variables' => $sample_variables
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Template error: ' . $e->getMessage()]);
}
