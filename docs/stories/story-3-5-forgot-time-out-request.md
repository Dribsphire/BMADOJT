# Story 3.5: Forgot Time-Out Request

## Story
**As a** student,  
**I want** to submit a forgot time-out request with a letter upload,  
**so that** I can get approval for attendance hours when I forgot to time-out.

## Acceptance Criteria

### AC1: Forgot Time-Out Form
- [x] Create forgot time-out request form
- [x] Select attendance record that needs time-out
- [x] Upload explanation letter (PDF/DOCX)
- [x] Provide reason for forgot time-out
- [x] Set request date and time

### AC2: Letter Upload
- [x] Upload explanation letter file
- [x] Validate file format (PDF, DOCX, DOC)
- [x] Maximum file size 5MB
- [x] Generate unique filename
- [x] Store in uploads/letters/ directory

### AC3: Request Submission
- [x] Create record in forgot_timeout_requests table:
  - student_id, attendance_record_id, request_date
  - block_type, letter_file_path, status
- [x] Set initial status to "Pending"
- [x] Send notification to instructor
- [x] Confirmation message to student

### AC4: Request Validation
- [x] Validate attendance record exists and belongs to student
- [x] Check if time-out is missing (time_out is NULL)
- [x] Prevent duplicate requests for same attendance record
- [x] Validate file upload success

### AC5: Student Interface
- [x] Show list of submitted requests
- [x] Display request status (Pending, Approved, Rejected)
- [x] Show instructor feedback if available
- [x] Allow viewing of uploaded letter

## Dev Notes
- Follow existing file upload patterns
- Use existing form validation patterns
- Integrate with notification system (Epic 4)
- Ensure mobile-friendly file upload

## Testing
- [x] Form submission works correctly
- [x] File upload validation functional
- [x] Database recording accurate
- [x] Request validation prevents duplicates
- [x] Student interface displays correctly
- [x] Mobile responsive design verified

## File List
- `public/student/forgot_timeout.php` (new)
- `public/student/forgot_timeout_submit.php` (new)
- `src/Controllers/ForgotTimeoutController.php` (new)
- `src/Services/ForgotTimeoutService.php` (new)
- `uploads/letters/` (new directory)
- `public/student/student-sidebar.php` (updated - added forgot timeout link)

## Change Log
- 2025-01-07: Story created for Epic 3 implementation
- 2025-01-07: Story 3.5 implementation completed
  - Created ForgotTimeoutService with comprehensive business logic
  - Created ForgotTimeoutController with API endpoints for form submission and data retrieval
  - Created student forgot timeout form interface with drag-and-drop file upload
  - Created form submission handler with file validation and database integration
  - Added forgot timeout link to student sidebar navigation
  - Implemented request validation to prevent duplicates and ensure data integrity
  - Added student interface to view request status and download uploaded letters
  - Implemented comprehensive file upload validation (PDF, DOCX, DOC, 5MB max)
  - Added responsive design and mobile-friendly interface

## Status
Ready for Review
