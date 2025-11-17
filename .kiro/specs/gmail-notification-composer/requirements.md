# Requirements Document: Gmail-Style Notification Composer in Admin Users Page

## Introduction

This document outlines the requirements for adding a Gmail-style notification composer to the admin/users.php page. The feature will allow administrators to compose and send email notifications directly from the users management page through a modal interface, similar to Gmail's compose functionality.

## Requirements

### Requirement 1: Bell Button Integration

**User Story:** As an administrator, I want to see a bell button on the admin/users.php page, so that I can quickly access the notification composer without navigating to a separate page.

#### Acceptance Criteria

1. WHEN viewing the admin/users.php page THEN a bell icon button SHALL be visible in the page header
2. WHEN the bell button is clicked THEN a modal SHALL open with the notification composer
3. WHEN the modal is opened THEN the form SHALL be empty and ready for input
4. WHEN the modal is closed THEN any unsaved data SHALL be cleared
5. WHEN the modal is displayed THEN it SHALL have a Gmail-like appearance and layout

### Requirement 2: Recipient Selection (TO Field)

**User Story:** As an administrator, I want to select specific recipients or groups for my notification, so that I can target the right audience without including administrators.

#### Acceptance Criteria

1. WHEN composing a notification THEN a "TO:" field SHALL be displayed at the top
2. WHEN clicking the TO field THEN a dropdown SHALL show recipient options
3. WHEN selecting recipients THEN the following options SHALL be available:
   - Specific Students (multi-select)
   - Specific Instructors (multi-select)
   - All Students
   - All Instructors
   - All Students and Instructors
4. WHEN "Specific Students" is selected THEN a searchable list of students SHALL be displayed
5. WHEN "Specific Instructors" is selected THEN a searchable list of instructors SHALL be displayed
6. WHEN selecting recipients THEN administrators SHALL NOT be included in any option
7. WHEN recipients are selected THEN they SHALL be displayed as chips/tags in the TO field
8. WHEN a recipient chip is clicked THEN it SHALL be removable

### Requirement 3: Subject Field

**User Story:** As an administrator, I want to type a custom subject for my notification, so that recipients know what the email is about.

#### Acceptance Criteria

1. WHEN composing a notification THEN a "Subject:" field SHALL be displayed
2. WHEN typing in the subject field THEN the input SHALL be a single-line text field
3. WHEN the subject is empty THEN the form SHALL NOT be submittable
4. WHEN the subject exceeds 255 characters THEN a warning SHALL be displayed
5. WHEN the subject is entered THEN it SHALL be used as the email subject line

### Requirement 4: Message Body

**User Story:** As an administrator, I want to compose a message body with formatting options, so that I can communicate effectively with recipients.

#### Acceptance Criteria

1. WHEN composing a notification THEN a message body textarea SHALL be displayed
2. WHEN typing in the message body THEN it SHALL support multi-line text
3. WHEN the message body is empty THEN the form SHALL NOT be submittable
4. WHEN the message is entered THEN it SHALL be sent as HTML email
5. WHEN the message contains line breaks THEN they SHALL be preserved in the email

### Requirement 5: Send Functionality

**User Story:** As an administrator, I want to send the notification to selected recipients, so that they receive the email in their inbox.

#### Acceptance Criteria

1. WHEN all required fields are filled THEN a "Send" button SHALL be enabled
2. WHEN the Send button is clicked THEN the notification SHALL be sent to all selected recipients
3. WHEN sending is in progress THEN a loading indicator SHALL be displayed
4. WHEN sending is successful THEN a success message SHALL be displayed
5. WHEN sending fails THEN an error message SHALL be displayed with details
6. WHEN sending is complete THEN the modal SHALL close automatically
7. WHEN sending is complete THEN an activity log entry SHALL be created
8. WHEN sending to multiple recipients THEN each email SHALL be sent individually
9. WHEN sending fails for some recipients THEN a summary SHALL show success/failure counts

### Requirement 6: User Experience Enhancements

**User Story:** As an administrator, I want a smooth and intuitive experience when composing notifications, so that I can work efficiently.

#### Acceptance Criteria

1. WHEN the modal opens THEN the TO field SHALL be auto-focused
2. WHEN pressing Tab THEN focus SHALL move through fields in logical order
3. WHEN pressing Escape THEN the modal SHALL close
4. WHEN clicking outside the modal THEN it SHALL close with confirmation if data exists
5. WHEN the form has unsaved changes THEN a confirmation SHALL be shown before closing
6. WHEN recipient count exceeds 50 THEN a confirmation SHALL be required before sending
7. WHEN typing in search fields THEN results SHALL filter in real-time

### Requirement 7: Template Removal

**User Story:** As an administrator, I want a simplified notification system without template dropdowns, so that I can compose messages more naturally like Gmail.

#### Acceptance Criteria

1. WHEN composing a notification THEN NO template dropdown SHALL be displayed
2. WHEN composing a notification THEN NO template preview button SHALL be displayed
3. WHEN composing a notification THEN NO custom variables field SHALL be displayed
4. WHEN sending a notification THEN the system SHALL use the EmailService directly
5. WHEN sending a notification THEN NO template processing SHALL occur

### Requirement 8: Integration with Existing System

**User Story:** As a developer, I want the new notification composer to integrate seamlessly with existing email infrastructure, so that it uses proven, reliable code.

#### Acceptance Criteria

1. WHEN sending notifications THEN the existing EmailService SHALL be used
2. WHEN sending notifications THEN the existing authentication system SHALL be used
3. WHEN sending notifications THEN the existing AdminAccess checks SHALL be applied
4. WHEN sending notifications THEN activity logs SHALL be created using existing patterns
5. WHEN querying recipients THEN the existing users table SHALL be queried
6. WHEN the feature is added THEN NO breaking changes SHALL be made to existing code

### Requirement 9: Recipient Count Display

**User Story:** As an administrator, I want to see how many recipients will receive my notification, so that I can verify I'm sending to the right audience.

#### Acceptance Criteria

1. WHEN recipients are selected THEN a count SHALL be displayed
2. WHEN the count is displayed THEN it SHALL show the exact number of recipients
3. WHEN the count exceeds 0 THEN it SHALL be visible near the TO field
4. WHEN the count changes THEN it SHALL update in real-time
5. WHEN hovering over the count THEN a tooltip MAY show recipient details

### Requirement 10: Error Handling and Validation

**User Story:** As an administrator, I want clear error messages and validation, so that I know when something is wrong and how to fix it.

#### Acceptance Criteria

1. WHEN required fields are empty THEN the Send button SHALL be disabled
2. WHEN no recipients are selected THEN an error message SHALL be displayed
3. WHEN email sending fails THEN the specific error SHALL be shown
4. WHEN network errors occur THEN a user-friendly message SHALL be displayed
5. WHEN validation fails THEN the problematic field SHALL be highlighted
6. WHEN errors occur THEN the modal SHALL remain open for correction
