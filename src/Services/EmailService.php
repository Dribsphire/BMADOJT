<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
use App\Utils\Database;
use PDO;

class EmailService
{
    private PHPMailer $mailer;
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::getInstance();
        $this->mailer = new PHPMailer(true);
        $this->configureMailer();
    }

    /**
     * Configure PHPMailer with SMTP settings
     */
    private function configureMailer(): void
    {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = 'smtp.gmail.com';
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = 'coloradomanuel.002@gmail.com'; // Replace with actual email
            $this->mailer->Password = 'zusd ysgn phlf sgkl'; // Replace with actual password
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = 587;

            // Recipients
            $this->mailer->setFrom('coloradomanuel.002@gmail.com', 'OJT Route System');
            $this->mailer->isHTML(true);
        } catch (Exception $e) {
            error_log("Email configuration failed: " . $e->getMessage());
        }
    }

    /**
     * Send template upload notification to students
     */
    public function sendTemplateUploadNotification(
        int $instructorId,
        int $sectionId,
        string $documentName,
        string $documentType,
        ?string $deadline,
        ?string $description
    ): bool {
        try {
            // Get instructor info
            $stmt = $this->pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
            $stmt->execute([$instructorId]);
            $instructor = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get students in section
            $stmt = $this->pdo->prepare("
                SELECT u.id, u.full_name, u.email 
                FROM users u 
                WHERE u.section_id = ? AND u.role = 'student'
            ");
            $stmt->execute([$sectionId]);
            $students = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($students)) {
                return true; // No students to notify
            }

            // Prepare email content
            $subject = "New Document Template Available - {$documentName}";
            $deadlineText = $deadline ? "Deadline: " . date('F j, Y', strtotime($deadline)) : "No deadline set";
            $descriptionText = $description ? "<p><strong>Instructions:</strong><br>" . nl2br(htmlspecialchars($description)) . "</p>" : "";

            $body = "
                <h2>New Document Template Available</h2>
                <p>Hello,</p>
                <p>Your instructor <strong>{$instructor['full_name']}</strong> has uploaded a new document template:</p>
                
                <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                    <h3>{$documentName}</h3>
                    <p><strong>Type:</strong> " . ucfirst(str_replace('_', ' ', $documentType)) . "</p>
                    <p><strong>{$deadlineText}</strong></p>
                    {$descriptionText}
                </div>
                
                <p>Please log in to your OJT Route account to download and complete this document.</p>
                
                <div style='margin: 20px 0;'>
                    <a href='https://ojtroute.ccs-chmsualijis.com/bmadOJT/public/student/documents.php' 
                       style='background: #0ea539; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        View Documents
                    </a>
                </div>
                
                <p>Best regards,<br>OJT Route System</p>
            ";

            // Send to each student
            $successCount = 0;
            foreach ($students as $student) {
                try {
                    $this->mailer->clearAddresses();
                    $this->mailer->addAddress($student['email'], $student['full_name']);
                    $this->mailer->Subject = $subject;
                    $this->mailer->Body = $body;
                    
                    if ($this->mailer->send()) {
                        $successCount++;
                        
                        // Log email sent
                        $this->logEmailSent($instructorId, $student['id'], 'template_upload', $documentName);
                    }
                } catch (Exception $e) {
                    error_log("Failed to send email to {$student['email']}: " . $e->getMessage());
                }
            }

            return $successCount > 0;

        } catch (Exception $e) {
            error_log("Email notification failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send document status change notification
     */
    public function sendDocumentStatusNotification(
        int $studentId,
        string $documentName,
        string $status,
        ?string $feedback = null
    ): bool {
        try {
            // Get student info
            $stmt = $this->pdo->prepare("SELECT full_name, email FROM users WHERE id = ?");
            $stmt->execute([$studentId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

            $subject = "Document Status Update - {$documentName}";
            
            $statusText = match($status) {
                'approved' => 'Approved ✅',
                'revision_required' => 'Needs Revision ⚠️',
                'rejected' => 'Rejected ❌',
                default => ucfirst($status)
            };

            $body = "
                <h2>Document Status Update</h2>
                <p>Hello {$student['full_name']},</p>
                <p>Your document <strong>{$documentName}</strong> has been reviewed.</p>
                
                <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;'>
                    <h3>Status: {$statusText}</h3>
                    " . ($feedback ? "<p><strong>Feedback:</strong><br>" . nl2br(htmlspecialchars($feedback)) . "</p>" : "") . "
                </div>
                
                <p>Please log in to your OJT Route account to view the full details.</p>
                
                <div style='margin: 20px 0;'>
                    <a href='https://ojtroute.ccs-chmsualijis.com/bmadOJT/public/student/documents.php' 
                       style='background: #0ea539; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>
                        View Documents
                    </a>
                </div>
                
                <p>Best regards,<br>OJT Route System</p>
            ";

            $this->mailer->clearAddresses();
            $this->mailer->addAddress($student['email'], $student['full_name']);
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;

            if ($this->mailer->send()) {
                $this->logEmailSent(0, $studentId, 'document_status', $documentName);
                return true;
            }

            return false;

        } catch (Exception $e) {
            error_log("Document status email failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Log email sent to database
     */
    private function logEmailSent(int $fromUserId, int $toUserId, string $type, string $description): void
    {
        try {
            // Validate that the fromUserId exists in users table
            $stmt = $this->pdo->prepare("SELECT id FROM users WHERE id = ?");
            $stmt->execute([$fromUserId]);
            if (!$stmt->fetch()) {
                error_log("EmailService: User ID {$fromUserId} not found in users table, skipping email log");
                return;
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action, description) 
                VALUES (?, 'email_sent', ?)
            ");
            $stmt->execute([
                $fromUserId,
                "Email sent to user {$toUserId}: {$type} - {$description}"
            ]);
        } catch (Exception $e) {
            error_log("Failed to log email: " . $e->getMessage());
        }
    }

    /**
     * Send overdue notification
     */
    public function sendOverdueNotification(string $studentEmail, string $studentName, string $documentName, string $deadline): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($studentEmail, $studentName);
            $this->mailer->Subject = 'Overdue Document: ' . $documentName;
            
            $deadlineFormatted = date('M j, Y', strtotime($deadline));
            $daysOverdue = floor((time() - strtotime($deadline)) / (24 * 60 * 60));
            
            $this->mailer->Body = "
                <h2>Document Overdue Notice</h2>
                <p>Dear {$studentName},</p>
                <p>This is to inform you that the following document is overdue:</p>
                <ul>
                    <li><strong>Document:</strong> {$documentName}</li>
                    <li><strong>Deadline:</strong> {$deadlineFormatted}</li>
                    <li><strong>Days Overdue:</strong> {$daysOverdue} day(s)</li>
                </ul>
                <p>Please submit this document as soon as possible to avoid any delays in your OJT process.</p>
                <p>You can access your documents and submit them through the OJT Route system.</p>
                <p>If you have any questions, please contact your instructor.</p>
                <br>
                <p>Best regards,<br>OJT Route System</p>
            ";
            
            $this->mailer->isHTML(true);
            
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("Overdue notification sent to {$studentEmail} for document: {$documentName}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Failed to send overdue notification to {$studentEmail}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send overdue summary to instructor
     */
    public function sendOverdueSummaryToInstructor(string $instructorEmail, string $instructorName, array $overdueDocuments): bool
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($instructorEmail, $instructorName);
            $this->mailer->Subject = 'Overdue Documents Summary - ' . count($overdueDocuments) . ' documents';
            
            $html = "<h2>Overdue Documents Summary</h2>";
            $html .= "<p>Dear {$instructorName},</p>";
            $html .= "<p>You have " . count($overdueDocuments) . " overdue document(s) in your section:</p>";
            $html .= "<table border='1' style='border-collapse: collapse; width: 100%;'>";
            $html .= "<tr><th>Student</th><th>Document</th><th>Deadline</th><th>Days Overdue</th></tr>";
            
            foreach ($overdueDocuments as $doc) {
                $daysOverdue = floor((time() - strtotime($doc['deadline'])) / (24 * 60 * 60));
                $html .= "<tr>";
                $html .= "<td>{$doc['student_name']}</td>";
                $html .= "<td>{$doc['document_name']}</td>";
                $html .= "<td>" . date('M j, Y', strtotime($doc['deadline'])) . "</td>";
                $html .= "<td>{$daysOverdue}</td>";
                $html .= "</tr>";
            }
            
            $html .= "</table>";
            $html .= "<p>Please follow up with these students to ensure timely submission.</p>";
            $html .= "<p>Best regards,<br>OJT Route System</p>";
            
            $this->mailer->Body = $html;
            $this->mailer->isHTML(true);
            
            $result = $this->mailer->send();
            
            if ($result) {
                error_log("Overdue summary sent to instructor {$instructorEmail}");
            }
            
            return $result;
            
        } catch (Exception $e) {
            error_log("Failed to send overdue summary to instructor {$instructorEmail}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Test email configuration
     */
    public function testEmailConfiguration(): array
    {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress('test@example.com', 'Test User');
            $this->mailer->Subject = 'Test Email';
            $this->mailer->Body = 'This is a test email from OJT Route System.';
            
            // Don't actually send, just test configuration
            $this->mailer->preSend();
            
            return [
                'success' => true,
                'message' => 'Email configuration is valid'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Send a generic email
     */
    public function sendEmail(string $to, string $subject, string $body, array $attachments = [], bool $isHtml = true): array
    {
        try {
            // Clear previous recipients
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Set recipient
            $this->mailer->addAddress($to);
            
            // Set content
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $body;
            $this->mailer->isHTML($isHtml);
            
            // Add attachments if any
            foreach ($attachments as $attachment) {
                if (isset($attachment['path']) && isset($attachment['name'])) {
                    $this->mailer->addAttachment($attachment['path'], $attachment['name']);
                }
            }
            
            // Send email
            $result = $this->mailer->send();
            
            if ($result) {
                return [
                    'success' => true,
                    'message' => 'Email sent successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Failed to send email'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Send email using a template with variables
     */
    public function sendTemplateEmail(string $to, string $templateName, array $variables = []): array
    {
        try {
            // Load template
            $templates = new \App\Templates\EmailTemplates();
            $template = null;
            
            switch ($templateName) {
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
                    throw new Exception("Template '{$templateName}' not found");
            }
            
            if (!$template) {
                throw new Exception("Template '{$templateName}' not found");
            }
            
            // Replace variables in subject and body
            $subject = $templates->replaceVariables($template['subject'], $variables);
            $body = $templates->replaceVariables($template['body'], $variables);
            
            // Send email
            return $this->sendEmail($to, $subject, $body, [], $template['is_html']);
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}
