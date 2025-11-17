# Design Document: Gmail-Style Notification Composer

## Overview

This feature adds a Gmail-style notification composer to the admin/users.php page, accessible via a bell icon button. The composer opens in a modal and allows administrators to send email notifications to specific students, instructors, or groups without using templates. The design emphasizes simplicity and familiarity by mimicking Gmail's compose interface.

## Architecture

### Component Structure

```
admin/users.php
├── Bell Icon Button (Header)
├── Notification Modal
│   ├── TO Field (Recipient Selector)
│   ├── Subject Field
│   ├── Message Body (Textarea)
│   └── Action Buttons (Send, Cancel)
└── JavaScript Handler
    ├── Modal Management
    ├── Recipient Selection
    ├── Form Validation
    └── AJAX Email Sending
```

### Data Flow

```
User clicks bell → Modal opens → User selects recipients
                                        ↓
                              User enters subject & message
                                        ↓
                              User clicks Send button
                                        ↓
                              JavaScript validates form
                                        ↓
                              AJAX POST to send_notification.php
                                        ↓
                              Backend processes recipients
                                        ↓
                              EmailService sends emails
                                        ↓
                              Activity logged
                                        ↓
                              Response returned to frontend
                                        ↓
                              Success/Error message displayed
```

## User Interface Design

### Modal Layout


```
┌─────────────────────────────────────────────────────┐
│  New Message                                    [X] │
├─────────────────────────────────────────────────────┤
│  TO: [Recipient Selector ▼]          [25 recipients]│
│      [Student 1 ×] [Student 2 ×] ...                │
├─────────────────────────────────────────────────────┤
│  Subject: [_________________________________]        │
├─────────────────────────────────────────────────────┤
│  ┌───────────────────────────────────────────────┐ │
│  │                                               │ │
│  │  Message body...                              │ │
│  │                                               │ │
│  │                                               │ │
│  │                                               │ │
│  └───────────────────────────────────────────────┘ │
├─────────────────────────────────────────────────────┤
│                              [Cancel]  [Send] 📧    │
└─────────────────────────────────────────────────────┘
```

### Bell Button Design

**Location**: Top right of admin/users.php page header
**Style**: Bootstrap icon button with notification bell icon
**Behavior**: Opens modal on click

```html
<button class="btn btn-primary" id="composeNotificationBtn">
    <i class="bi bi-bell"></i> Send Notification
</button>
```

### Recipient Selector Design

**Type**: Custom dropdown with multiple selection modes

**Options Structure**:
```
┌─────────────────────────────────┐
│ Quick Select                    │
├─────────────────────────────────┤
│ ○ All Students                  │
│ ○ All Instructors               │
│ ○ All Students and Instructors  │
├─────────────────────────────────┤
│ Specific Recipients             │
├─────────────────────────────────┤
│ ☐ Select Specific Students      │
│ ☐ Select Specific Instructors   │
└─────────────────────────────────┘
```

**When "Select Specific Students" is checked**:
```
┌─────────────────────────────────┐
│ Search students...              │
├─────────────────────────────────┤
│ ☐ John Doe (2021-001)          │
│ ☐ Jane Smith (2021-002)        │
│ ☐ Bob Johnson (2021-003)       │
│ ...                             │
└─────────────────────────────────┘
```

## Database Queries

### Get All Students (excluding admins)
```sql
SELECT id, email, full_name, school_id
FROM users
WHERE role = 'student' 
  AND email IS NOT NULL 
  AND email != ''
ORDER BY full_name
```

### Get All Instructors (excluding admins)
```sql
SELECT id, email, full_name, school_id
FROM users
WHERE role = 'instructor' 
  AND email IS NOT NULL 
  AND email != ''
ORDER BY full_name
```

### Get All Students and Instructors
```sql
SELECT id, email, full_name, school_id, role
FROM users
WHERE role IN ('student', 'instructor')
  AND email IS NOT NULL 
  AND email != ''
ORDER BY role, full_name
```

### Get Specific Users by IDs
```sql
SELECT id, email, full_name, school_id, role
FROM users
WHERE id IN (?, ?, ?, ...)
  AND email IS NOT NULL 
  AND email != ''
```

## Backend Implementation

### New API Endpoint: send_notification.php

**Location**: `public/admin/api/send_notification.php`

**Request Format**:
```json
{
  "recipient_mode": "all_students" | "all_instructors" | "all_users" | "specific",
  "recipient_ids": [1, 2, 3],
  "subject": "Important Announcement",
  "message": "This is the message body..."
}
```

**Response Format**:
```json
{
  "success": true,
  "sent_count": 25,
  "failed_count": 0,
  "message": "Notification sent successfully to 25 recipients"
}
```

**Error Response**:
```json
{
  "success": false,
  "error": "No recipients selected",
  "details": "..."
}
```



### Backend Processing Logic

```php
// 1. Validate authentication and admin access
AdminAccess::requireAdminAccess();

// 2. Extract and validate request data
$recipient_mode = $_POST['recipient_mode'];
$recipient_ids = json_decode($_POST['recipient_ids'] ?? '[]', true);
$subject = trim($_POST['subject']);
$message = trim($_POST['message']);

// 3. Validate required fields
if (empty($subject) || empty($message)) {
    throw new Exception('Subject and message are required');
}

// 4. Get recipients based on mode
$recipients = getRecipients($pdo, $recipient_mode, $recipient_ids);

// 5. Send emails to each recipient
$emailService = new EmailService();
$success_count = 0;
$failure_count = 0;

foreach ($recipients as $recipient) {
    $result = $emailService->sendEmail(
        $recipient['email'],
        $subject,
        nl2br(htmlspecialchars($message)),
        [],
        true
    );
    
    if ($result['success']) {
        $success_count++;
    } else {
        $failure_count++;
    }
}

// 6. Log activity
$stmt = $pdo->prepare("
    INSERT INTO activity_logs (user_id, action, description, created_at)
    VALUES (?, 'notification_sent', ?, NOW())
");
$stmt->execute([
    $current_user->id,
    "Sent notification: {$success_count} successful, {$failure_count} failed"
]);

// 7. Return response
return [
    'success' => true,
    'sent_count' => $success_count,
    'failed_count' => $failure_count
];
```

### Helper Function: getRecipients()

```php
function getRecipients($pdo, $mode, $recipient_ids = []) {
    switch ($mode) {
        case 'all_students':
            $stmt = $pdo->query("
                SELECT id, email, full_name, school_id
                FROM users 
                WHERE role = 'student' 
                  AND email IS NOT NULL 
                  AND email != ''
                ORDER BY full_name
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        case 'all_instructors':
            $stmt = $pdo->query("
                SELECT id, email, full_name, school_id
                FROM users 
                WHERE role = 'instructor' 
                  AND email IS NOT NULL 
                  AND email != ''
                ORDER BY full_name
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        case 'all_users':
            $stmt = $pdo->query("
                SELECT id, email, full_name, school_id, role
                FROM users 
                WHERE role IN ('student', 'instructor')
                  AND email IS NOT NULL 
                  AND email != ''
                ORDER BY role, full_name
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        case 'specific':
            if (empty($recipient_ids)) {
                return [];
            }
            $placeholders = str_repeat('?,', count($recipient_ids) - 1) . '?';
            $stmt = $pdo->prepare("
                SELECT id, email, full_name, school_id, role
                FROM users 
                WHERE id IN ($placeholders)
                  AND role IN ('student', 'instructor')
                  AND email IS NOT NULL 
                  AND email != ''
            ");
            $stmt->execute($recipient_ids);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        default:
            return [];
    }
}
```

## Frontend Implementation

### Modal HTML Structure

```html
<!-- Notification Composer Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-envelope"></i> New Message
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="notificationForm">
                    <!-- TO Field -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">TO:</label>
                        <div class="input-group">
                            <select class="form-select" id="recipientMode">
                                <option value="">Select recipients...</option>
                                <option value="all_students">All Students</option>
                                <option value="all_instructors">All Instructors</option>
                                <option value="all_users">All Students and Instructors</option>
                                <option value="specific_students">Specific Students</option>
                                <option value="specific_instructors">Specific Instructors</option>
                            </select>
                            <span class="input-group-text" id="recipientCount">
                                0 recipients
                            </span>
                        </div>
                        
                        <!-- Selected Recipients Display -->
                        <div id="selectedRecipients" class="mt-2"></div>
                        
                        <!-- Specific Recipients Selector -->
                        <div id="specificRecipientsContainer" class="mt-2" style="display: none;">
                            <input type="text" class="form-control mb-2" 
                                   id="recipientSearch" 
                                   placeholder="Search...">
                            <div id="recipientList" class="border rounded p-2" 
                                 style="max-height: 200px; overflow-y: auto;">
                                <!-- Checkboxes populated by JavaScript -->
                            </div>
                        </div>
                    </div>
                    
                    <!-- Subject Field -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Subject:</label>
                        <input type="text" class="form-control" 
                               id="notificationSubject" 
                               placeholder="Enter subject"
                               maxlength="255" required>
                    </div>
                    
                    <!-- Message Body -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">Message:</label>
                        <textarea class="form-control" 
                                  id="notificationMessage" 
                                  rows="10" 
                                  placeholder="Type your message here..."
                                  required></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Cancel
                </button>
                <button type="button" class="btn btn-primary" id="sendNotificationBtn">
                    <i class="bi bi-send"></i> Send
                </button>
            </div>
        </div>
    </div>
</div>
```

### JavaScript Implementation



```javascript
// Global variables
let selectedRecipientIds = [];
let allUsers = [];

// Initialize modal
document.getElementById('composeNotificationBtn').addEventListener('click', function() {
    const modal = new bootstrap.Modal(document.getElementById('notificationModal'));
    modal.show();
    document.getElementById('recipientMode').focus();
});

// Handle recipient mode change
document.getElementById('recipientMode').addEventListener('change', function() {
    const mode = this.value;
    const specificContainer = document.getElementById('specificRecipientsContainer');
    
    if (mode === 'specific_students' || mode === 'specific_instructors') {
        specificContainer.style.display = 'block';
        loadSpecificRecipients(mode);
    } else {
        specificContainer.style.display = 'none';
        selectedRecipientIds = [];
        updateRecipientCount(mode);
    }
    
    updateSelectedRecipientsDisplay();
});

// Load specific recipients
async function loadSpecificRecipients(mode) {
    const role = mode === 'specific_students' ? 'student' : 'instructor';
    
    try {
        const response = await fetch(`api/get_users.php?role=${role}`);
        const data = await response.json();
        
        if (data.success) {
            allUsers = data.users;
            renderRecipientList(allUsers);
        }
    } catch (error) {
        console.error('Error loading recipients:', error);
    }
}

// Render recipient list with checkboxes
function renderRecipientList(users) {
    const listContainer = document.getElementById('recipientList');
    listContainer.innerHTML = '';
    
    users.forEach(user => {
        const div = document.createElement('div');
        div.className = 'form-check';
        div.innerHTML = `
            <input class="form-check-input" type="checkbox" 
                   value="${user.id}" 
                   id="user_${user.id}"
                   ${selectedRecipientIds.includes(user.id) ? 'checked' : ''}>
            <label class="form-check-label" for="user_${user.id}">
                ${user.full_name} (${user.school_id})
            </label>
        `;
        
        div.querySelector('input').addEventListener('change', function() {
            if (this.checked) {
                selectedRecipientIds.push(parseInt(this.value));
            } else {
                selectedRecipientIds = selectedRecipientIds.filter(
                    id => id !== parseInt(this.value)
                );
            }
            updateSelectedRecipientsDisplay();
            updateRecipientCount('specific');
        });
        
        listContainer.appendChild(div);
    });
}

// Search functionality
document.getElementById('recipientSearch').addEventListener('input', function() {
    const searchTerm = this.value.toLowerCase();
    const filteredUsers = allUsers.filter(user => 
        user.full_name.toLowerCase().includes(searchTerm) ||
        user.school_id.toLowerCase().includes(searchTerm)
    );
    renderRecipientList(filteredUsers);
});

// Update recipient count
async function updateRecipientCount(mode) {
    const countElement = document.getElementById('recipientCount');
    
    if (mode === 'specific') {
        countElement.textContent = `${selectedRecipientIds.length} recipients`;
        return;
    }
    
    if (!mode) {
        countElement.textContent = '0 recipients';
        return;
    }
    
    try {
        const response = await fetch(`api/get_recipient_count.php?mode=${mode}`);
        const data = await response.json();
        
        if (data.success) {
            countElement.textContent = `${data.count} recipients`;
        }
    } catch (error) {
        console.error('Error getting count:', error);
    }
}

// Update selected recipients display (chips)
function updateSelectedRecipientsDisplay() {
    const container = document.getElementById('selectedRecipients');
    
    if (selectedRecipientIds.length === 0) {
        container.innerHTML = '';
        return;
    }
    
    const selectedUsers = allUsers.filter(user => 
        selectedRecipientIds.includes(user.id)
    );
    
    container.innerHTML = selectedUsers.map(user => `
        <span class="badge bg-primary me-1 mb-1">
            ${user.full_name}
            <i class="bi bi-x-circle ms-1" 
               style="cursor: pointer;" 
               onclick="removeRecipient(${user.id})"></i>
        </span>
    `).join('');
}

// Remove recipient
function removeRecipient(userId) {
    selectedRecipientIds = selectedRecipientIds.filter(id => id !== userId);
    document.getElementById(`user_${userId}`).checked = false;
    updateSelectedRecipientsDisplay();
    updateRecipientCount('specific');
}

// Send notification
document.getElementById('sendNotificationBtn').addEventListener('click', async function() {
    const mode = document.getElementById('recipientMode').value;
    const subject = document.getElementById('notificationSubject').value.trim();
    const message = document.getElementById('notificationMessage').value.trim();
    
    // Validation
    if (!mode) {
        alert('Please select recipients');
        return;
    }
    
    if (!subject) {
        alert('Please enter a subject');
        return;
    }
    
    if (!message) {
        alert('Please enter a message');
        return;
    }
    
    if (mode.startsWith('specific_') && selectedRecipientIds.length === 0) {
        alert('Please select at least one recipient');
        return;
    }
    
    // Confirmation for large sends
    const count = await getRecipientCount(mode);
    if (count > 50) {
        if (!confirm(`You are about to send this notification to ${count} recipients. Continue?`)) {
            return;
        }
    }
    
    // Disable button and show loading
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Sending...';
    
    try {
        const response = await fetch('api/send_notification.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({
                recipient_mode: mode.replace('specific_', ''),
                recipient_ids: selectedRecipientIds,
                subject: subject,
                message: message
            })
        });
        
        const data = await response.json();
        
        if (data.success) {
            alert(`Success! Sent to ${data.sent_count} recipients.`);
            bootstrap.Modal.getInstance(document.getElementById('notificationModal')).hide();
            resetForm();
        } else {
            alert('Error: ' + data.error);
        }
    } catch (error) {
        alert('Error sending notification: ' + error.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-send"></i> Send';
    }
});

// Reset form
function resetForm() {
    document.getElementById('notificationForm').reset();
    selectedRecipientIds = [];
    document.getElementById('specificRecipientsContainer').style.display = 'none';
    document.getElementById('selectedRecipients').innerHTML = '';
    document.getElementById('recipientCount').textContent = '0 recipients';
}

// Helper function to get recipient count
async function getRecipientCount(mode) {
    if (mode === 'specific') {
        return selectedRecipientIds.length;
    }
    
    try {
        const response = await fetch(`api/get_recipient_count.php?mode=${mode}`);
        const data = await response.json();
        return data.success ? data.count : 0;
    } catch (error) {
        return 0;
    }
}
```

## Additional API Endpoints Needed

### 1. Get Users by Role

**Endpoint**: `public/admin/api/get_users.php`

**Parameters**: `role` (student or instructor)

**Response**:
```json
{
  "success": true,
  "users": [
    {
      "id": 1,
      "full_name": "John Doe",
      "school_id": "2021-001",
      "email": "john@example.com"
    }
  ]
}
```

### 2. Get Recipient Count (Enhanced)

**Endpoint**: `public/admin/api/get_recipient_count.php` (modify existing)

**Parameters**: `mode` (all_students, all_instructors, all_users)

**Response**:
```json
{
  "success": true,
  "count": 25,
  "mode": "all_students"
}
```

## Styling

### Custom CSS

```css
/* Recipient chips */
#selectedRecipients .badge {
    font-size: 0.9rem;
    padding: 0.5rem 0.75rem;
}

#selectedRecipients .badge i {
    cursor: pointer;
    opacity: 0.7;
}

#selectedRecipients .badge i:hover {
    opacity: 1;
}

/* Recipient list */
#recipientList {
    background-color: #f8f9fa;
}

#recipientList .form-check {
    padding: 0.5rem;
    border-bottom: 1px solid #dee2e6;
}

#recipientList .form-check:last-child {
    border-bottom: none;
}

#recipientList .form-check:hover {
    background-color: #e9ecef;
}

/* Modal sizing */
#notificationModal .modal-lg {
    max-width: 700px;
}

/* Recipient count badge */
#recipientCount {
    background-color: #e9ecef;
    color: #495057;
    font-weight: 500;
}
```

## Security Considerations

1. **Authentication**: All API endpoints check admin access
2. **Input Sanitization**: Subject and message sanitized before sending
3. **SQL Injection Prevention**: Prepared statements for all queries
4. **XSS Prevention**: HTML special chars escaped in output
5. **CSRF Protection**: Consider adding CSRF tokens to forms
6. **Rate Limiting**: Consider limiting emails per hour per admin

## Testing Checklist

- [ ] Bell button appears on admin/users.php
- [ ] Modal opens when bell button clicked
- [ ] All recipient modes work correctly
- [ ] Specific student selection works
- [ ] Specific instructor selection works
- [ ] Search functionality filters correctly
- [ ] Recipient chips display and remove correctly
- [ ] Recipient count updates accurately
- [ ] Subject field validation works
- [ ] Message field validation works
- [ ] Send button disabled when form invalid
- [ ] Emails sent successfully to all recipients
- [ ] Activity log created correctly
- [ ] Success message displayed
- [ ] Error handling works for failures
- [ ] Modal closes after successful send
- [ ] Form resets after send

## Migration from Old System

This new system will coexist with the existing `admin/notifications.php` page. The old page can remain for template-based notifications if needed, or can be deprecated once this new system is proven stable.

**Recommendation**: Keep both systems initially, then deprecate the old one after user feedback.
