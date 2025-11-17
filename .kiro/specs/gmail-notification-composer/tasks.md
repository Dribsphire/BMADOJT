# Implementation Plan: Gmail-Style Notification Composer

## Task Overview

This implementation plan breaks down the development of the Gmail-style notification composer into discrete, manageable coding tasks. Each task builds incrementally on previous work.

## Tasks

- [x] 1. Create API endpoint for getting users by role





  - Create `public/admin/api/get_users.php` file
  - Implement authentication and admin access checks
  - Add database query to fetch users by role (student or instructor)
  - Exclude admin users from results
  - Filter out users with NULL or empty emails
  - Return JSON response with user list (id, full_name, school_id, email)
  - Handle error cases and return appropriate HTTP status codes
  - _Requirements: 2.3, 2.4, 8.5_

- [x] 2. Enhance recipient count API endpoint





  - Modify `public/admin/api/get_recipient_count.php` to support new modes
  - Add support for `mode` parameter (all_students, all_instructors, all_users)
  - Update database queries to match new recipient selection logic
  - Ensure admin users are excluded from all counts
  - Return simplified JSON response with count and mode
  - _Requirements: 9.1, 9.2, 9.4_

- [ ] 3. Create notification sending API endpoint
  - Create `public/admin/api/send_notification.php` file
  - Implement authentication and admin access checks
  - Accept JSON request body with recipient_mode, recipient_ids, subject, message
  - Validate required fields (subject, message, recipients)
  - Implement `getRecipients()` helper function for all recipient modes
  - Loop through recipients and send emails using EmailService
  - Track success and failure counts
  - Log activity to activity_logs table
  - Return JSON response with success/failure counts
  - Handle exceptions and return error responses
  - _Requirements: 5.2, 5.3, 5.4, 5.5, 5.8, 5.9, 8.1, 8.4_

- [ ] 4. Add bell button to admin/users.php page
  - Open `public/admin/users.php` file
  - Add bell icon button to page header area
  - Style button with Bootstrap classes (btn btn-primary)
  - Add icon using Bootstrap Icons (bi-bell)
  - Add button text "Send Notification"
  - Assign ID `composeNotificationBtn` for JavaScript targeting
  - _Requirements: 1.1, 1.2_

- [ ] 5. Create notification modal HTML structure
  - Add modal HTML to `public/admin/users.php` before closing body tag
  - Create modal with ID `notificationModal`
  - Add modal header with "New Message" title and close button
  - Create TO field with recipient mode dropdown
  - Add recipient count display element
  - Create selected recipients display area for chips
  - Add specific recipients container (hidden by default) with search input
  - Create recipient list container for checkboxes
  - Add Subject input field with maxlength 255
  - Add Message textarea with 10 rows
  - Create modal footer with Cancel and Send buttons
  - _Requirements: 1.3, 2.1, 3.1, 4.1, 6.1_

- [ ] 6. Implement modal initialization JavaScript
  - Add JavaScript code to `public/admin/users.php` (or separate JS file)
  - Initialize global variables (selectedRecipientIds, allUsers)
  - Add event listener to bell button to open modal
  - Implement modal open handler with Bootstrap Modal API
  - Set focus to recipient mode dropdown when modal opens
  - _Requirements: 1.2, 1.3, 6.1_

- [ ] 7. Implement recipient mode selection logic
  - Add event listener to recipient mode dropdown
  - Handle mode change events
  - Show/hide specific recipients container based on mode
  - Call loadSpecificRecipients() for specific modes
  - Call updateRecipientCount() for all modes
  - Clear selectedRecipientIds when switching modes
  - Update selected recipients display
  - _Requirements: 2.2, 2.3, 2.4, 9.4_

- [ ] 8. Implement specific recipients loading and display
  - Create loadSpecificRecipients() async function
  - Fetch users from get_users.php API based on role
  - Store fetched users in allUsers global variable
  - Call renderRecipientList() to display checkboxes
  - Handle fetch errors gracefully
  - _Requirements: 2.4, 2.5_

- [ ] 9. Implement recipient list rendering with checkboxes
  - Create renderRecipientList() function
  - Clear existing recipient list container
  - Loop through users and create checkbox elements
  - Display user full name and school ID
  - Check boxes for already selected recipients
  - Add change event listeners to checkboxes
  - Update selectedRecipientIds array on checkbox change
  - Call updateSelectedRecipientsDisplay() and updateRecipientCount()
  - _Requirements: 2.4, 2.7_

- [ ] 10. Implement recipient search functionality
  - Add input event listener to recipient search field
  - Filter allUsers array based on search term
  - Search in both full_name and school_id fields
  - Call renderRecipientList() with filtered results
  - Implement case-insensitive search
  - _Requirements: 2.4, 6.7_

- [ ] 11. Implement recipient count display
  - Create updateRecipientCount() async function
  - Handle different modes (specific, all_students, all_instructors, all_users)
  - For specific mode, show selectedRecipientIds.length
  - For other modes, fetch count from API
  - Update recipient count element text
  - Format count text as "X recipients"
  - _Requirements: 9.1, 9.2, 9.3, 9.4_

- [ ] 12. Implement selected recipients chips display
  - Create updateSelectedRecipientsDisplay() function
  - Clear selected recipients container
  - Filter allUsers to get selected users
  - Create badge/chip elements for each selected user
  - Add remove icon (X) to each chip
  - Implement removeRecipient() function for chip removal
  - Update checkbox state when chip removed
  - Update recipient count after removal
  - _Requirements: 2.7, 2.8_

- [ ] 13. Implement form validation
  - Add validation for recipient mode selection
  - Add validation for subject field (required, not empty)
  - Add validation for message field (required, not empty)
  - Add validation for specific mode (at least one recipient selected)
  - Display alert messages for validation failures
  - Disable send button when form is invalid (optional enhancement)
  - _Requirements: 3.2, 4.2, 5.1, 10.1, 10.2, 10.5_

- [ ] 14. Implement send notification functionality
  - Add click event listener to Send button
  - Gather form data (mode, subject, message, recipient IDs)
  - Perform client-side validation
  - Show confirmation dialog for large recipient counts (>50)
  - Disable send button and show loading spinner
  - Make AJAX POST request to send_notification.php
  - Send JSON payload with all notification data
  - Handle successful response (show success message, close modal)
  - Handle error response (show error message, keep modal open)
  - Re-enable send button after completion
  - _Requirements: 5.1, 5.2, 5.3, 5.4, 5.5, 5.6, 6.6, 10.3, 10.4_

- [ ] 15. Implement form reset functionality
  - Create resetForm() function
  - Reset all form fields to default values
  - Clear selectedRecipientIds array
  - Hide specific recipients container
  - Clear selected recipients chips display
  - Reset recipient count to "0 recipients"
  - Call resetForm() after successful send
  - _Requirements: 1.4, 5.6_

- [ ] 16. Add custom CSS styling
  - Add CSS for recipient chips (badges)
  - Style chip remove icons with hover effects
  - Style recipient list container with background color
  - Add hover effects to recipient list items
  - Style recipient count badge
  - Set modal max-width to 700px
  - Add border styling to recipient list
  - _Requirements: 1.5, 6.3_

- [ ] 17. Implement modal close confirmation
  - Add event listener for modal close events
  - Check if form has unsaved changes
  - Show confirmation dialog if data exists
  - Allow close without confirmation if form is empty
  - Implement Escape key handler for modal close
  - _Requirements: 6.4, 6.5_

- [ ] 18. Add error handling and user feedback
  - Implement try-catch blocks for all async operations
  - Display user-friendly error messages for network failures
  - Handle API error responses gracefully
  - Show loading indicators during async operations
  - Highlight problematic fields when validation fails
  - Log errors to console for debugging
  - _Requirements: 10.3, 10.4, 10.5, 10.6_

- [ ] 19. Test complete notification flow
  - Test bell button opens modal correctly
  - Test all recipient mode selections
  - Test specific student selection with search
  - Test specific instructor selection with search
  - Test recipient chips display and removal
  - Test recipient count accuracy for all modes
  - Test form validation for all required fields
  - Test successful email sending to various recipient groups
  - Test error handling for failed sends
  - Test activity log creation
  - Test modal close and form reset
  - Verify admins are excluded from all recipient options
  - _Requirements: All requirements_

- [ ] 20. Add documentation and comments
  - Add code comments to JavaScript functions
  - Document API endpoint parameters and responses
  - Add inline comments for complex logic
  - Update any existing documentation about notification system
  - _Requirements: 8.6_
