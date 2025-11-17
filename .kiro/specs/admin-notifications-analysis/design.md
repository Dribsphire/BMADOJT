# Design Document: Admin Notifications System Analysis

## Overview

The Admin Notifications system (`public/admin/notifications.php`) is a comprehensive email notification management interface that allows administrators to send bulk emails to students, instructors, or all users in the OJT Route system. The system supports both template-based and custom emails, with variable substitution, recipient counting, and email testing capabilities.

## Architecture

### High-Level Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Admin Notifications Page                  │
│                  (notifications.php)                         │
└───────────────────────┬─────────────────────────────────────┘
                        │
        ┌───────────────┼───────────────┐
        │               │               │
        ▼               ▼               ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│ Auth Layer   │ │ Email Layer  │ │  API Layer   │
│              │ │              │ │              │
│ - AuthSvc    │ │ - EmailSvc   │ │ - Recipient  │
│ - AuthMW     │ │ - Templates  │ │   Count API  │
│ - AdminAccess│ │              │ │ - Template   │
│              │ │              │ │   Preview API│
└──────┬───────┘ └──────┬───────┘ └──────┬───────┘
       │                │                │
       └────────────────┼────────────────┘
                        │
                        ▼
                ┌───────────────┐
                │   Database    │
                │               │
                │ - users       │
                │ - activity_   │
                │   logs        │
                │ - email_queue │
                └───────────────┘
```

### Component Breakdown

#### 1. **Main Page Component** (`public/admin/notifications.php`)
- **Purpose**: User interface for sending notifications
- **Responsibilities**:
  - Render notification form
  - Handle form submissions
  - Display success/error messages
  - Manage recipient selection
  - Provide template preview and test email functionality

#### 2. **Authentication Layer**
- **AuthenticationService** (`src/Services/AuthenticationService.php`)
  - Session management
  - User authentication
  - Session timeout handling (30 minutes)
  - Compliance gate checking
  
- **AuthMiddleware** (`src/Middleware/AuthMiddleware.php`)
  - Request authentication verification
  - Role-based access control
  - Redirect handling for unauthorized access
  
- **AdminAccess** (`src/Utils/AdminAccess.php`)
  - Admin-specific access control
  - Support for "acting as instructor" role
  - Centralized admin authorization

#### 3. **Email Layer**
- **EmailService** (`src/Services/EmailService.php`)
  - PHPMailer integration
  - SMTP configuration (Gmail)
  - Generic email sending
  - Template-based email sending
  - Email logging
  
- **EmailTemplates** (`src/Templates/EmailTemplates.php`)
  - 8 predefined email templates
  - Variable substitution system
  - Template retrieval methods

#### 4. **API Layer**
- **get_recipient_count.php** (`public/admin/api/get_recipient_count.php`)
  - Returns count of recipients by type
  - Provides role breakdown for "all" type
  
- **get_template_preview.php** (`public/admin/api/get_template_preview.php`)
  - Returns template preview with sample data
  - Lists available template variables

#### 5. **Database Layer**
- **Database** (`src/Utils/Database.php`)
  - PDO singleton pattern
  - Environment variable configuration
  - Connection pooling

## Database Schema

### Tables Used by Notifications System

#### 1. **users** Table
```sql
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    school_id VARCHAR(20) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    full_name VARCHAR(255) NOT NULL,
    role ENUM('student', 'instructor', 'admin') NOT NULL,
    section_id INT UNSIGNED NULL,
    profile_picture VARCHAR(500) NULL,
    gender ENUM('male', 'female', 'non-binary') NULL,
    contact VARCHAR(20) NULL,
    facebook_name VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_school_id (school_id),
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_section_id (section_id)
)
```

**Columns Used:**
- `id`: User identifier (recipient ID)
- `email`: Recipient email address
- `full_name`: Recipient name for personalization
- `role`: Filter recipients by role (student/instructor/admin)
- `school_id`: User identification
- `section_id`: Section association

#### 2. **activity_logs** Table
```sql
CREATE TABLE activity_logs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at),
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)
```

**Usage:**
- Logs notification sending activities
- Action: `'admin_notification_sent'`
- Description format: `"Sent notification to {type}: {success_count} successful, {failure_count} failed"`

#### 3. **email_queue** Table (Optional/Future Use)
```sql
CREATE TABLE email_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    recipient_email VARCHAR(255) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body TEXT NOT NULL,
    status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    error_message TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL,
    
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_recipient_email (recipient_email)
)
```

**Note:** Currently not used by notifications.php but available for asynchronous email processing.

## Data Flow

### 1. Notification Sending Flow

```
User fills form → Submit → POST to notifications.php
                              │
                              ├─ Validate authentication
                              ├─ Validate admin access
                              ├─ Extract form data
                              │
                              ├─ Get recipients by type
                              │  └─ Query users table
                              │
                              ├─ For each recipient:
                              │  ├─ Prepare variables
                              │  ├─ Send email (template or custom)
                              │  │  └─ EmailService::sendEmail()
                              │  │     └─ PHPMailer::send()
                              │  └─ Track success/failure
                              │
                              ├─ Log activity
                              │  └─ INSERT INTO activity_logs
                              │
                              └─ Display results
```

### 2. Recipient Count Flow

```
User selects recipient type → JavaScript onChange event
                                │
                                └─ AJAX GET request
                                   └─ api/get_recipient_count.php
                                      │
                                      ├─ Validate auth
                                      ├─ Query users table
                                      │  └─ COUNT(*) WHERE role = ?
                                      │
                                      └─ Return JSON
                                         {
                                           success: true,
                                           count: 25,
                                           type: "students",
                                           details: {...}
                                         }
```

### 3. Template Preview Flow

```
User clicks "Preview Template" → JavaScript function
                                  │
                                  └─ AJAX GET request
                                     └─ api/get_template_preview.php
                                        │
                                        ├─ Validate auth
                                        ├─ Load template
                                        │  └─ EmailTemplates::get{Type}Template()
                                        │
                                        ├─ Replace with sample variables
                                        │  └─ EmailTemplates::replaceVariables()
                                        │
                                        └─ Return JSON
                                           {
                                             success: true,
                                             template: {
                                               name: "welcome",
                                               subject: "...",
                                               body: "..."
                                             },
                                             variables: [...]
                                           }
```

## Email Templates

### Available Templates

1. **welcome** - Welcome Email
   - Variables: `user_name`, `site_name`, `current_year`, `site_url`
   
2. **password_reset** - Password Reset
   - Variables: `user_name`, `site_name`, `reset_url`, `current_year`
   
3. **attendance_notification** - Attendance Notification
   - Variables: `user_name`, `attendance_date`, `time_in`, `time_out`, `hours_earned`, `site_name`
   
4. **document_submission** - Document Submission
   - Variables: `user_name`, `document_name`, `submission_date`, `status`, `site_name`
   
5. **forgot_timeout** - Forgot Timeout
   - Variables: `user_name`, `block_type`, `time_in`, `request_date`, `site_name`
   
6. **instructor_notification** - Instructor Notification
   - Variables: `instructor_name`, `student_name`, `action_type`, `details`, `current_date`, `site_name`
   
7. **system_announcement** - System Announcement
   - Variables: `user_name`, `announcement_title`, `announcement_content`, `effective_date`, `site_name`
   
8. **compliance_reminder** - Compliance Reminder
   - Variables: `user_name`, `missing_documents`, `deadline`, `completion_url`, `site_name`

### Template Variable System

Templates use double curly brace syntax: `{{variable_name}}`

**Default Variables (always available):**
- `user_name`: Recipient's full name
- `site_name`: "OJT Route System"
- `current_year`: Current year
- `site_url`: Application URL

**Custom Variables:**
- Can be passed as JSON in the form
- Merged with default variables
- Example: `{"announcement_title": "Holiday Notice"}`

## Email Configuration

### SMTP Settings (PHPMailer)

```php
Host: smtp.gmail.com
Port: 587
Encryption: STARTTLS
Username: coloradomanuel.002@gmail.com
Password: zusd ysgn phlf sgkl (App Password)
From: coloradomanuel.002@gmail.com
From Name: OJT Route System
```

### Email Sending Process

1. **Configuration**: PHPMailer configured with SMTP settings
2. **Recipient Setup**: Clear previous addresses, add new recipient
3. **Content Setup**: Set subject, body, HTML flag
4. **Attachments**: Optional attachments can be added
5. **Send**: PHPMailer::send() executes
6. **Logging**: Success/failure logged to activity_logs

## User Interface Components

### Form Fields

1. **Recipients** (dropdown)
   - Options: All Students, All Instructors, All Users
   - Triggers recipient count update
   
2. **Email Type** (dropdown)
   - 9 options (8 templates + custom)
   - Enables template preview
   
3. **Subject** (text input)
   - Required field
   - Used for custom emails
   
4. **Message** (textarea)
   - Required field
   - 8 rows
   - Used for custom emails or template override
   
5. **Custom Variables** (textarea)
   - Optional JSON input
   - 3 rows
   - Format: `{"key": "value"}`

### Interactive Features

1. **Recipient Count Display**
   - Real-time AJAX update
   - Shows total count
   - Shows breakdown by role (for "all" type)
   
2. **Template Preview**
   - Modal popup
   - Shows subject and body
   - Lists available variables
   - Uses sample data
   
3. **Test Email**
   - Modal popup
   - Send test to specific email
   - Separate form submission
   
4. **Form Validation**
   - Client-side validation
   - Confirmation dialog before sending
   - Required field checks

### UI Styling

- **Framework**: Bootstrap 5.3.0
- **Icons**: Bootstrap Icons 1.10.0
- **Custom CSS**: Notification card hover effects, template preview styling
- **Color Scheme**: CHMSU Green (`#0ea539`)

## Security Considerations

### Authentication & Authorization

1. **Session-based authentication**
   - 30-minute timeout
   - Session regeneration on login
   
2. **Multi-layer authorization**
   - AuthMiddleware::check()
   - AdminAccess::requireAdminAccess()
   - Supports "acting as instructor" role
   
3. **API endpoint protection**
   - All API endpoints check authentication
   - Return 401 for unauthenticated
   - Return 403 for unauthorized

### Input Validation

1. **Server-side validation**
   - Required field checks
   - Recipient type validation
   - Template name validation
   
2. **SQL Injection Prevention**
   - PDO prepared statements
   - Parameter binding
   
3. **XSS Prevention**
   - `htmlspecialchars()` on output
   - HTML email sanitization

### Email Security

1. **SMTP Authentication**
   - Secure SMTP connection
   - App-specific password
   
2. **Rate Limiting** (Not implemented)
   - Consider adding rate limiting for bulk sends
   
3. **Email Validation**
   - Only sends to users with valid email addresses
   - Filters out NULL and empty emails

## Error Handling

### Exception Handling

```php
try {
    // Email sending logic
} catch (Exception $e) {
    $error_message = "Error: " . $e->getMessage();
}
```

### Error Display

- Success messages: Green alert with checkmark icon
- Error messages: Red alert with warning icon
- Dismissible alerts with Bootstrap

### Logging

- All notification activities logged to `activity_logs`
- Email failures tracked in results array
- Error messages logged to PHP error log

## Integration Points

### External Libraries

1. **PHPMailer** (via Composer)
   - Email sending functionality
   - SMTP support
   
2. **Bootstrap 5.3.0** (CDN)
   - UI framework
   - Modal components
   
3. **Bootstrap Icons 1.10.0** (CDN)
   - Icon library

### Internal Dependencies

1. **Services**
   - AuthenticationService
   - EmailService
   
2. **Middleware**
   - AuthMiddleware
   
3. **Utils**
   - Database
   - AdminAccess
   
4. **Templates**
   - EmailTemplates
   
5. **Models**
   - User (indirectly via AuthenticationService)

### JavaScript Dependencies

1. **Bootstrap Bundle JS** (CDN)
   - Modal functionality
   - Alert dismissal
   
2. **Custom JavaScript**
   - Recipient count AJAX
   - Template preview AJAX
   - Form validation
   - Test email modal

### File Dependencies

1. **Sidebar** (`sidebar.php`)
   - Admin navigation
   
2. **CSS Files**
   - `../css/style.css`
   - `../css/sidebarstyle.css`
   - `../css/minimal-modal-fix.css`
   
3. **JS Files**
   - `../js/sidebarSlide.js`
   - `../js/minimal-modal-fix.js`

## API Endpoints

### 1. Get Recipient Count

**Endpoint**: `api/get_recipient_count.php`

**Method**: GET

**Parameters**:
- `type` (required): "students" | "instructors" | "all"

**Response**:
```json
{
  "success": true,
  "count": 25,
  "type": "students",
  "details": {
    "by_role": [
      {"role": "student", "count": 20},
      {"role": "instructor", "count": 5}
    ]
  }
}
```

**Error Responses**:
- 401: Unauthorized (not logged in)
- 403: Admin access required
- 400: Invalid recipient type
- 500: Database error

### 2. Get Template Preview

**Endpoint**: `api/get_template_preview.php`

**Method**: GET

**Parameters**:
- `template` (required): Template name

**Response**:
```json
{
  "success": true,
  "template": {
    "name": "welcome",
    "subject": "Welcome to OJT Route System",
    "body": "<h2>Welcome...</h2>",
    "is_html": true
  },
  "variables": ["user_name", "site_name", "current_year"],
  "sample_variables": {
    "user_name": "John Doe",
    "site_name": "OJT Route System",
    ...
  }
}
```

**Error Responses**:
- 401: Unauthorized
- 403: Admin access required
- 400: Invalid template name
- 404: Template not found
- 500: Template error

## Performance Considerations

### Current Implementation

1. **Synchronous Email Sending**
   - Emails sent in loop
   - Blocks until all emails sent
   - Can timeout for large recipient lists
   
2. **No Pagination**
   - All recipients loaded at once
   - Memory usage increases with user count
   
3. **No Caching**
   - Recipient counts queried on each request
   - Templates loaded on each preview

### Potential Improvements

1. **Asynchronous Email Queue**
   - Use `email_queue` table
   - Background worker process
   - Better timeout handling
   
2. **Batch Processing**
   - Send emails in batches
   - Progress indicator
   - Resume capability
   
3. **Caching**
   - Cache recipient counts
   - Cache template content
   - Redis/Memcached integration

## Testing Strategy

### Manual Testing Checklist

1. **Authentication**
   - [ ] Non-admin cannot access page
   - [ ] Admin can access page
   - [ ] Session timeout redirects to login
   
2. **Recipient Selection**
   - [ ] Count updates for students
   - [ ] Count updates for instructors
   - [ ] Count updates for all users
   - [ ] Count shows role breakdown
   
3. **Template System**
   - [ ] All 8 templates preview correctly
   - [ ] Variables are replaced
   - [ ] Custom message works
   
4. **Email Sending**
   - [ ] Email sent to students
   - [ ] Email sent to instructors
   - [ ] Email sent to all users
   - [ ] Success message displayed
   - [ ] Activity logged
   
5. **Test Email**
   - [ ] Test email modal opens
   - [ ] Test email sends successfully
   - [ ] Error handling works
   
6. **Error Handling**
   - [ ] Invalid recipient type handled
   - [ ] Empty recipient list handled
   - [ ] Email failure handled gracefully
   - [ ] JSON parse errors handled

### Automated Testing Opportunities

1. **Unit Tests**
   - EmailService methods
   - EmailTemplates variable replacement
   - Recipient query functions
   
2. **Integration Tests**
   - Full notification sending flow
   - API endpoint responses
   - Database logging
   
3. **UI Tests**
   - Form validation
   - AJAX interactions
   - Modal functionality

## Maintenance Notes

### Configuration Updates

1. **SMTP Settings**: Update in `EmailService::configureMailer()`
2. **Session Timeout**: Update in `AuthenticationService::SESSION_TIMEOUT`
3. **Database Credentials**: Update in `.env` file

### Adding New Templates

1. Add method to `EmailTemplates` class
2. Add case to `EmailService::sendTemplateEmail()`
3. Add case to `get_template_preview.php`
4. Add option to `$available_templates` array
5. Update template variables list

### Common Issues

1. **Email not sending**: Check SMTP credentials, firewall rules
2. **Timeout on large sends**: Implement queue system
3. **Template variables not replacing**: Check variable name spelling
4. **Recipient count wrong**: Check email field NULL/empty values

## Conclusion

The Admin Notifications system is a well-structured, feature-rich email management interface with proper authentication, template support, and user-friendly features. The architecture follows good separation of concerns with distinct layers for authentication, email handling, and data access. The system could benefit from asynchronous email processing for better scalability and performance with large recipient lists.
