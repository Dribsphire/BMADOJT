<?php

/**
 * Admin Notifications Page
 * OJT Route - Admin notification management system
 */

require_once '../../vendor/autoload.php';

use App\Services\AuthenticationService;
use App\Middleware\AuthMiddleware;
use App\Utils\Database;
use App\Utils\AdminAccess;
use App\Services\EmailService;
use App\Templates\EmailTemplates;

// Start session
session_start();
date_default_timezone_set('Asia/Manila');

// Initialize authentication
$authService = new AuthenticationService();
$authMiddleware = new AuthMiddleware();

// Check authentication and authorization
if (!$authMiddleware->check()) {
    $authMiddleware->redirectToLogin();
}

// Check admin access
AdminAccess::requireAdminAccess();

// Get current user
$user = $authMiddleware->getCurrentUser();

// Initialize database
$pdo = Database::getInstance();

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'send_notification') {
            $emailService = new EmailService();
            $templates = new EmailTemplates();
            
            $recipient_type = $_POST['recipient_type'];
            $template_name = $_POST['template_name'];
            $subject = $_POST['subject'];
            $message = $_POST['message'];
            $custom_variables = json_decode($_POST['custom_variables'] ?? '{}', true);
            
            // Ensure custom_variables is an array
            if (!is_array($custom_variables)) {
                $custom_variables = [];
            }
            
            // Get recipients based on type
            $recipients = getRecipientsByType($pdo, $recipient_type);
            
            if (empty($recipients)) {
                throw new Exception('No recipients found for the selected type.');
            }
            
            $results = [];
            $success_count = 0;
            $failure_count = 0;
            
            foreach ($recipients as $recipient) {
                try {
                    // Prepare variables for template
                    $variables = array_merge([
                        'user_name' => $recipient['full_name'],
                        'site_name' => 'OJT Route System',
                        'current_year' => date('Y'),
                        'site_url' => 'http://localhost/bmadOJT'
                    ], $custom_variables);
                    
                    // Send email using template or custom message
                    if ($template_name && $template_name !== 'custom') {
                        $result = $emailService->sendTemplateEmail(
                            $recipient['email'],
                            $template_name,
                            $variables
                        );
                    } else {
                        $result = $emailService->sendEmail(
                            $recipient['email'],
                            $subject,
                            $message,
                            [],
                            true
                        );
                    }
                    
                    if ($result['success']) {
                        $success_count++;
                    } else {
                        $failure_count++;
                    }
                    
                    $results[] = [
                        'recipient' => $recipient['email'],
                        'name' => $recipient['full_name'],
                        'result' => $result
                    ];
                    
                } catch (Exception $e) {
                    $failure_count++;
                    $results[] = [
                        'recipient' => $recipient['email'],
                        'name' => $recipient['full_name'],
                        'result' => ['success' => false, 'message' => $e->getMessage()]
                    ];
                }
            }
            
            // Log the notification activity
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description, created_at)
                VALUES (?, 'admin_notification_sent', ?, NOW())
            ");
            $stmt->execute([
                $user->id,
                "Sent notification to {$recipient_type}: {$success_count} successful, {$failure_count} failed"
            ]);
            
            $success_message = "Notification sent successfully! {$success_count} emails sent, {$failure_count} failed.";
            
        } elseif ($_POST['action'] === 'test_email') {
            $emailService = new EmailService();
            
            $test_email = $_POST['test_email'];
            $subject = $_POST['test_subject'];
            $message = $_POST['test_message'];
            
            $result = $emailService->sendEmail($test_email, $subject, $message, [], true);
            
            if ($result['success']) {
                $success_message = "Test email sent successfully to {$test_email}";
            } else {
                $error_message = "Test email failed: " . $result['message'];
            }
        }
        
    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get recipients for dropdown
function getRecipientsByType($pdo, $type) {
    switch ($type) {
        case 'students':
            $stmt = $pdo->query("
                SELECT id, email, full_name, school_id, section_id
                FROM users 
                WHERE role = 'student' AND email IS NOT NULL
                ORDER BY full_name
            ");
            break;
        case 'instructors':
            $stmt = $pdo->query("
                SELECT id, email, full_name, school_id, section_id
                FROM users 
                WHERE role = 'instructor' AND email IS NOT NULL
                ORDER BY full_name
            ");
            break;
        case 'all':
            $stmt = $pdo->query("
                SELECT id, email, full_name, school_id, section_id, role
                FROM users 
                WHERE email IS NOT NULL
                ORDER BY role, full_name
            ");
            break;
        default:
            return [];
    }
    
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get notification history
$stmt = $pdo->prepare("
    SELECT al.*, u.full_name as admin_name
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    WHERE al.action = 'admin_notification_sent'
    ORDER BY al.created_at DESC
    LIMIT 20
");
$stmt->execute();
$notification_history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get email templates
$templates = new EmailTemplates();
$available_templates = [
    'custom' => 'Custom Message',
    'welcome' => 'Welcome Email',
    'password_reset' => 'Password Reset',
    'attendance_notification' => 'Attendance Notification',
    'document_submission' => 'Document Submission',
    'forgot_timeout' => 'Forgot Timeout',
    'instructor_notification' => 'Instructor Notification',
    'system_announcement' => 'System Announcement',
    'compliance_reminder' => 'Compliance Reminder'
];

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Notifications - OJT Route</title>
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
        :root {
            --chmsu-green: #0ea539;
        }
        .notification-card {
            transition: all 0.3s ease;
            border: 1px solid #dee2e6;
        }
        .notification-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .template-preview {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            background-color: #f8f9fa;
        }
        .recipient-count {
            font-size: 0.875rem;
            color: #6c757d;
        }
        .notification-history {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <main>
        <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
            <h1 class="h2">
                <i class="bi bi-bell me-2"></i>Admin Notifications
            </h1>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle me-2"></i><?= htmlspecialchars($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-triangle me-2"></i><?= htmlspecialchars($error_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <!-- Send Notification Form -->
            <div class="col-md-8">
                <div class="card notification-card mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="bi bi-send me-2"></i>Send Notification
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="notificationForm">
                            <input type="hidden" name="action" value="send_notification">
                            
                            <!-- Recipient Selection -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="recipient_type" class="form-label">Recipients</label>
                                    <select class="form-select" id="recipient_type" name="recipient_type" required>
                                        <option value="">Select recipient type</option>
                                        <option value="students">All Students</option>
                                        <option value="instructors">All Instructors</option>
                                        <option value="all">All Users</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Recipient Count</label>
                                    <div class="recipient-count" id="recipient_count">
                                        Select a recipient type to see count
                                    </div>
                                </div>
                            </div>

                            <!-- Template Selection -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="template_name" class="form-label">Email type</label>
                                    <select class="form-select" id="template_name" name="template_name">
                                        <?php foreach ($available_templates as $key => $name): ?>
                                            <option value="<?= $key ?>"><?= $name ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Template Preview</label>
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="previewTemplate()">
                                        <i class="bi bi-eye me-1"></i>Preview Template
                                    </button>
                                </div>
                            </div>

                            <!-- Subject -->
                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject</label>
                                <input type="text" class="form-control" id="subject" name="subject" 
                                       placeholder="Enter email subject" required>
                            </div>

                            <!-- Message -->
                            <div class="mb-3">
                                <label for="message" class="form-label">Message</label>
                                <textarea class="form-control" id="message" name="message" rows="8" 
                                          placeholder="Enter your message here..." required></textarea>
                            </div>

                            <!-- Custom Variables -->
                            <div class="mb-3">
                                <label for="custom_variables" class="form-label">Custom Variables (JSON)</label>
                                <textarea class="form-control" id="custom_variables" name="custom_variables" rows="3" 
                                          placeholder='{"variable_name": "value"}'></textarea>
                                <div class="form-text">Enter JSON format for custom template variables</div>
                            </div>

                            <!-- Submit Button -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <button type="button" class="btn btn-outline-secondary me-md-2" onclick="testEmail()">
                                    <i class="bi bi-envelope me-1"></i>Test Email
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-send me-1"></i>Send Notification
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Quick Actions & History -->
            <div class="col-md-4">
                <!-- Quick Actions -->
                <div class="card notification-card mb-4">
                    <div class="card-header bg-success text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-lightning me-2"></i>Quick Actions
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary btn-sm" onclick="quickNotification('students', 'compliance_reminder')">
                                <i class="bi bi-people me-1"></i>Remind Students About Documents
                            </button>
                            <button class="btn btn-outline-warning btn-sm" onclick="quickNotification('instructors', 'instructor_notification')">
                                <i class="bi bi-person-badge me-1"></i>Notify Instructors
                            </button>
                            <button class="btn btn-outline-info btn-sm" onclick="quickNotification('all', 'system_announcement')">
                                <i class="bi bi-megaphone me-1"></i>System Announcement
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Notification History -->
                <div class="card notification-card">
                    <div class="card-header bg-secondary text-white">
                        <h6 class="mb-0">
                            <i class="bi bi-clock-history me-2"></i>Recent Notifications
                        </h6>
                    </div>
                    <div class="card-body notification-history">
                        <?php if (empty($notification_history)): ?>
                            <p class="text-muted">No notifications sent yet.</p>
                        <?php else: ?>
                            <?php foreach ($notification_history as $notification): ?>
                                <div class="border-bottom pb-2 mb-2">
                                    <div class="d-flex justify-content-between">
                                        <small class="text-muted">
                                            <?= date('M j, Y g:i A', strtotime($notification['created_at'])) ?>
                                        </small>
                                        <small class="text-success">
                                            <i class="bi bi-check-circle me-1"></i>Sent
                                        </small>
                                    </div>
                                    <div class="fw-bold"><?= htmlspecialchars($notification['description']) ?></div>
                                    <small class="text-muted">by <?= htmlspecialchars($notification['admin_name']) ?></small>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Test Email Modal -->
    <div class="modal fade" id="testEmailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Test Email</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="test_email">
                        <div class="mb-3">
                            <label for="test_email" class="form-label">Test Email Address</label>
                            <input type="email" class="form-control" id="test_email" name="test_email" required>
                        </div>
                        <div class="mb-3">
                            <label for="test_subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="test_subject" name="test_subject" required>
                        </div>
                        <div class="mb-3">
                            <label for="test_message" class="form-label">Message</label>
                            <textarea class="form-control" id="test_message" name="test_message" rows="4" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Send Test Email</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Template Preview Modal -->
    <div class="modal fade" id="templatePreviewModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Template Preview</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="template_preview_content" class="template-preview">
                        Select a template to preview
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Minimal Modal Fix -->
    <link rel="stylesheet" href="../css/minimal-modal-fix.css">
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../js/minimal-modal-fix.js"></script>
    
    <script>
        // Update recipient count when type changes
        document.getElementById('recipient_type').addEventListener('change', function() {
            const type = this.value;
            const countElement = document.getElementById('recipient_count');
            
            if (type) {
                countElement.textContent = 'Loading...';
                
                // Fetch actual count from server
                fetch(`api/get_recipient_count.php?type=${type}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            let countText = `${data.count} recipients`;
                            if (data.details && data.details.by_role) {
                                const roleDetails = data.details.by_role.map(role => 
                                    `${role.role}: ${role.count}`
                                ).join(', ');
                                countText += ` (${roleDetails})`;
                            }
                            countElement.textContent = countText;
                        } else {
                            countElement.textContent = 'Error loading count';
                        }
                    })
                    .catch(error => {
                        countElement.textContent = 'Error loading count';
                        console.error('Error:', error);
                    });
            } else {
                countElement.textContent = 'Select a recipient type to see count';
            }
        });

        // Quick notification function
        function quickNotification(recipientType, templateName) {
            document.getElementById('recipient_type').value = recipientType;
            document.getElementById('template_name').value = templateName;
            
            // Update recipient count
            document.getElementById('recipient_type').dispatchEvent(new Event('change'));
        }

        // Test email function
        function testEmail() {
            const modal = new bootstrap.Modal(document.getElementById('testEmailModal'));
            modal.show();
        }

        // Preview template function
        function previewTemplate() {
            const templateName = document.getElementById('template_name').value;
            if (templateName && templateName !== 'custom') {
                const previewContent = document.getElementById('template_preview_content');
                previewContent.innerHTML = 'Loading template preview...';
                
                // Fetch template from server
                fetch(`api/get_template_preview.php?template=${templateName}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            previewContent.innerHTML = `
                                <h6>Template: ${data.template.name}</h6>
                                <p><strong>Subject:</strong> ${data.template.subject}</p>
                                <hr>
                                <div style="border: 1px solid #ddd; padding: 10px; background: white;">
                                    ${data.template.body}
                                </div>
                                <hr>
                                <p><strong>Available Variables:</strong></p>
                                <ul>
                                    ${data.variables.map(variable => `<li>{{${variable}}}</li>`).join('')}
                                </ul>
                            `;
                        } else {
                            previewContent.innerHTML = `<p class="text-danger">Error: ${data.error}</p>`;
                        }
                    })
                    .catch(error => {
                        previewContent.innerHTML = `<p class="text-danger">Error loading template: ${error.message}</p>`;
                    });
                
                const modal = new bootstrap.Modal(document.getElementById('templatePreviewModal'));
                modal.show();
            } else {
                alert('Please select a template to preview.');
            }
        }

        // Form validation
        document.getElementById('notificationForm').addEventListener('submit', function(e) {
            const recipientType = document.getElementById('recipient_type').value;
            const subject = document.getElementById('subject').value;
            const message = document.getElementById('message').value;
            
            if (!recipientType || !subject || !message) {
                e.preventDefault();
                alert('Please fill in all required fields.');
                return;
            }
            
            if (!confirm(`Are you sure you want to send this notification to ${recipientType}?`)) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
