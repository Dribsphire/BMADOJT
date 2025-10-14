<?php

namespace App\Templates;

/**
 * Email Templates Class
 * Manages email templates for the OJT Route system
 */
class EmailTemplates
{
    /**
     * Get welcome email template
     */
    public function getWelcomeTemplate()
    {
        return [
            'subject' => 'Welcome to OJT Route System - {{site_name}}',
            'body' => '
                <h2>Welcome to {{site_name}}, {{user_name}}!</h2>
                <p>We are excited to have you join our OJT Route System. Your account has been successfully created.</p>
                
                <h3>Getting Started:</h3>
                <ul>
                    <li>Complete your profile information</li>
                    <li>Upload required documents</li>
                    <li>Check your attendance schedule</li>
                    <li>Review OJT guidelines</li>
                </ul>
                
                <p>If you have any questions, please contact your instructor or the system administrator.</p>
                
                <p>Best regards,<br>
                {{site_name}} Team<br>
                {{current_year}}</p>
            ',
            'is_html' => true
        ];
    }

    /**
     * Get password reset template
     */
    public function getPasswordResetTemplate()
    {
        return [
            'subject' => 'Password Reset Request - {{site_name}}',
            'body' => '
                <h2>Password Reset Request</h2>
                <p>Hello {{user_name}},</p>
                
                <p>You have requested to reset your password for your {{site_name}} account.</p>
                
                <p>To reset your password, please Login into the system and go to your profile page to reset your password.</p>
                
                <p>If you did not request this password reset, please ignore this email.</p>
                
                <p>This link will expire in 24 hours for security purposes.</p>
                
                <p>Best regards,<br>
                {{site_name}} Team</p>
            ',
            'is_html' => true
        ];
    }

    /**
     * Get attendance notification template
     */
    public function getAttendanceNotificationTemplate()
    {
        return [
            'subject' => 'Attendance Recorded - {{site_name}}',
            'body' => '
                <h2>Attendance Recorded Successfully</h2>
                <p>Hello {{user_name}},</p>
                
                <p>Your attendance has been recorded for {{attendance_date}}.</p>
                
                <h3>Attendance Details:</h3>
                <ul>
                    <li><strong>Date:</strong> {{attendance_date}}</li>
                    <li><strong>Time In:</strong> {{time_in}}</li>
                    <li><strong>Time Out:</strong> {{time_out}}</li>
                    <li><strong>Hours Earned:</strong> {{hours_earned}} hours</li>
                </ul>
                
                <p>Keep up the great work!</p>
                
                <p>Best regards,<br>
                {{site_name}} Team</p>
            ',
            'is_html' => true
        ];
    }

    /**
     * Get document submission template
     */
    public function getDocumentSubmissionTemplate()
    {
        return [
            'subject' => 'Document Submitted - {{site_name}}',
            'body' => '
                <h2>Document Submission Confirmation</h2>
                <p>Hello {{user_name}},</p>
                
                <p>Your document has been successfully submitted for review.</p>
                
                <h3>Submission Details:</h3>
                <ul>
                    <li><strong>Document:</strong> {{document_name}}</li>
                    <li><strong>Submission Date:</strong> {{submission_date}}</li>
                    <li><strong>Status:</strong> {{status}}</li>
                </ul>
                
                <p>Your instructor will review the document and provide feedback. You will be notified once the review is complete.</p>
                
                <p>Best regards,<br>
                {{site_name}} Team</p>
            ',
            'is_html' => true
        ];
    }

    /**
     * Get forgot timeout template
     */
    public function getForgotTimeoutTemplate()
    {
        return [
            'subject' => 'Forgot Time-out Request - {{site_name}}',
            'body' => '
                <h2>Forgot Time-out Request</h2>
                <p>Hello {{user_name}},</p>
                
                <p>You have submitted a request for a forgotten time-out.</p>
                
                <h3>Request Details:</h3>
                <ul>
                    <li><strong>Block Type:</strong> {{block_type}}</li>
                    <li><strong>Time In:</strong> {{time_in}}</li>
                    <li><strong>Date:</strong> {{request_date}}</li>
                    <li><strong>Status:</strong> Pending Instructor Review</li>
                </ul>
                
                <p>Your instructor will review your request and approve or reject it. You will be notified of the decision.</p>
                
                <p>Best regards,<br>
                {{site_name}} Team</p>
            ',
            'is_html' => true
        ];
    }

    /**
     * Get instructor notification template
     */
    public function getInstructorNotificationTemplate()
    {
        return [
            'subject' => 'Instructor Notification - {{site_name}}',
            'body' => '
                <h2>Instructor Notification</h2>
                <p>Hello {{instructor_name}},</p>
                
                <p>You have received a new notification regarding your students.</p>
                
                <h3>Notification Details:</h3>
                <ul>
                    <li><strong>Student:</strong> {{student_name}}</li>
                    <li><strong>Action:</strong> {{action_type}}</li>
                    <li><strong>Details:</strong> {{details}}</li>
                    <li><strong>Date:</strong> {{current_date}}</li>
                </ul>
                
                <p>Please review and take appropriate action.</p>
                
                <p>Best regards,<br>
                {{site_name}} Team</p>
            ',
            'is_html' => true
        ];
    }

    /**
     * Get system announcement template
     */
    public function getSystemAnnouncementTemplate()
    {
        return [
            'subject' => 'System Announcement - {{site_name}}',
            'body' => '
                <h2>System Announcement</h2>
                <p>Hello {{user_name}},</p>
                
                <h3>{{announcement_title}}</h3>
                <p>{{announcement_content}}</p>
                
                <p><strong>Effective Date:</strong> {{effective_date}}</p>
                
                <p>Please take note of this announcement and plan accordingly.</p>
                
                <p>If you have any questions, please contact the system administrator.</p>
                
                <p>Best regards,<br>
                {{site_name}} Team</p>
            ',
            'is_html' => true
        ];
    }

    /**
     * Get compliance reminder template
     */
    public function getComplianceReminderTemplate()
    {
        return [
            'subject' => 'Document Compliance Reminder - {{site_name}}',
            'body' => '
                <h2>Document Compliance Reminder</h2>
                <p>Hello {{user_name}},</p>
                
                <p>This is a friendly reminder that you have missing or incomplete documents required for your OJT.</p>
                
                <h3>Missing Documents:</h3>
                {{missing_documents}}
                
                <p><strong>Deadline:</strong> {{deadline}}</p>
                
                <p>Please submit the required documents as soon as possible to avoid any delays in your OJT completion.</p>
                
                <p>You can submit your documents here: <a href="{{completion_url}}">{{completion_url}}</a></p>
                
                <p>If you have any questions, please contact your instructor.</p>
                
                <p>Best regards,<br>
                {{site_name}} Team</p>
            ',
            'is_html' => true
        ];
    }

    /**
     * Get template variables for a specific template
     */
    public function getTemplateVariables($template_name)
    {
        $variables = [
            'welcome' => ['user_name', 'site_name', 'current_year', 'site_url'],
            'password_reset' => ['user_name', 'site_name', 'reset_url', 'current_year'],
            'attendance_notification' => ['user_name', 'attendance_date', 'time_in', 'time_out', 'hours_earned', 'site_name'],
            'document_submission' => ['user_name', 'document_name', 'submission_date', 'status', 'site_name'],
            'forgot_timeout' => ['user_name', 'block_type', 'time_in', 'request_date', 'site_name'],
            'instructor_notification' => ['instructor_name', 'student_name', 'action_type', 'details', 'current_date', 'site_name'],
            'system_announcement' => ['user_name', 'announcement_title', 'announcement_content', 'effective_date', 'site_name'],
            'compliance_reminder' => ['user_name', 'missing_documents', 'deadline', 'completion_url', 'site_name']
        ];

        return $variables[$template_name] ?? [];
    }

    /**
     * Replace variables in template content
     */
    public function replaceVariables($content, $variables)
    {
        foreach ($variables as $key => $value) {
            $content = str_replace('{{' . $key . '}}', $value, $content);
        }
        return $content;
    }
}
