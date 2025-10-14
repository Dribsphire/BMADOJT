# Story 2.2: Instructor Document Template Upload

## Story
**As an** instructor,  
**I want** to upload custom document templates with optional deadlines,  
**so that** I can provide updated or section-specific document requirements to my students.

## Acceptance Criteria

### AC1: Template Upload Interface
- [x] Instructor has "Upload Template" button in document management interface
- [x] Upload form includes:
  - File selection (PDF, DOCX, DOC, TXT)
  - Document name (required)
  - Document type (dropdown: moa, endorsement, parental_consent, misdemeanor_penalty, ojt_plan, notarized_parental_consent, pledge_of_good_conduct)
  - Optional deadline date
  - Description/instructions (optional)

### AC2: File Validation
- [x] PDF, DOCX, DOC, TXT files accepted (max 10MB)
- [x] File name sanitized and unique
- [x] Duplicate file names handled (append timestamp)
- [x] File stored in `uploads/templates/` directory
- [x] Error handling for invalid files

### AC3: Database Storage
- [x] Document record created in `documents` table:
  - `document_name`: Instructor-provided name
  - `document_type`: Selected type
  - `file_path`: Path to uploaded file
  - `uploaded_by`: Instructor user ID
  - `uploaded_for_section`: Section ID
  - `deadline`: Optional deadline date
  - `description`: Optional instructions
  - `created_at`: Upload timestamp

### AC4: Student Notification
- [x] When instructor uploads template, system sends email notification to all students in instructor's section
- [x] Email includes:
  - Document name and type
  - Download link
  - Deadline (if set)
  - Instructions (if provided)
- [x] Email sent via PHPMailer

### AC5: Template Management
- [x] Instructor can view all uploaded templates
- [x] Instructor can see download statistics
- [x] Instructor can delete their own templates
- [x] Clear indication of template vs. student submissions

## Dev Notes
- Use existing FileUploadService for file handling
- Implement email notification system
- Consider template versioning (new upload replaces old)
- Add file size and type validation

## Testing
- [x] Upload form works correctly
- [x] File validation prevents invalid uploads
- [x] Database records created properly
- [x] Email notifications sent to students
- [x] File storage works correctly
- [x] Instructor can manage templates

## File List
- [x] `public/instructor/upload_template.php` (new)
- [x] `src/Controllers/DocumentController.php` (new)
- [x] `src/Services/DocumentService.php` (updated)
- [x] `src/Services/EmailService.php` (new)
- [x] `public/instructor/templates.php` (new)

## Change Log
- 2025-01-07: Story created for Epic 2 implementation
- 2025-01-07: Updated to follow Epic 1 pattern
- 2025-01-07: Implementation completed - All acceptance criteria met, all files created and tested
- 2025-01-07: Security fix - Instructor access control implemented, instructors can only see their own uploads + pre-loaded templates

## Status
Ready for Review
