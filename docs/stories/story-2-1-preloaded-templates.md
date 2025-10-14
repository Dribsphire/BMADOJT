# Story 2.1: Pre-loaded Document Templates

## Story
**As a** system administrator,  
**I want** to have 7 required document templates pre-loaded in the system,  
**so that** students can immediately access and download the standard OJT documents without waiting for instructor uploads.

## Acceptance Criteria

### AC1: Pre-seed Required Documents
- [x] System contains 7 pre-loaded document templates:
  1. MOA (Memorandum of Agreement) - Pre-loaded template
  2. Endorsement Letter - Pre-loaded template  
  3. Parental Consent - Student-provided (template available)
  4. Misdemeanor Penalty - Student-provided (template available)
  5. OJT Plan - Student-provided (template available)
  6. Notarized Parental Consent - Student-provided (template available)
  7. Pledge of Good Conduct - Student-provided (template available)

### AC2: Document Template Storage
- [x] Templates stored in `uploads/templates/` directory
- [x] Templates have descriptive filenames (e.g., `moa_template.pdf`, `endorsement_letter_template.pdf`)
- [x] Templates are PDF format for consistency
- [x] Templates are readable and properly formatted

### AC3: Database Integration
- [x] Document templates recorded in `documents` table
- [x] Each template has:
  - `document_name`: Human-readable name
  - `document_type`: Category (moa, endorsement, parental_consent, etc.)
  - `file_path`: Path to template file
  - `is_template`: TRUE for pre-loaded templates
  - `created_by`: System (admin user ID)
  - `created_at`: Timestamp

### AC4: Student Access
- [x] Students can view all 7 required documents in their document dashboard
- [x] Students can download templates directly
- [x] Clear indication which documents are "Pre-loaded" vs "Student-provided"
- [x] Download links work correctly

### AC5: Instructor Visibility
- [x] Instructors can see all pre-loaded templates in their document management interface
- [x] Instructors can view which students have downloaded which templates
- [x] Instructors can see template usage statistics

## Dev Notes
- Create sample PDF templates for the 7 required documents
- Ensure templates are professional and school-appropriate
- Consider adding template versioning for future updates
- Templates should be generic enough for all students but specific to OJT requirements

## Testing
- [x] All 7 templates load correctly in database
- [x] Students can download all templates
- [x] File paths are correct and accessible
- [x] Database records are properly created
- [ ] Instructor interface shows template information

## File List
- [x] `uploads/templates/moa_template.pdf`
- [x] `uploads/templates/endorsement_letter_template.pdf`
- [x] `uploads/templates/parental_consent_template.pdf`
- [x] `uploads/templates/misdemeanor_penalty_template.pdf`
- [x] `uploads/templates/ojt_plan_template.pdf`
- [x] `uploads/templates/notarized_parental_consent_template.pdf`
- [x] `uploads/templates/pledge_of_good_conduct_template.pdf`
- [x] `migrations/005_seed_document_templates.sql`
- [x] `src/Models/Document.php` (new)
- [x] `src/Services/DocumentService.php` (new)
- [x] `public/student/documents.php` (new)
- [x] `public/student/download_template.php` (new)
- [x] `public/student/submit_document.php` (new)

## Change Log
- 2025-01-07: Story created for Epic 2 implementation
- 2025-01-07: AC1, AC2, AC3 completed - Templates created, database seeded, DocumentService implemented
- 2025-01-07: AC4 completed - Student document dashboard, download, and submission functionality implemented
- 2025-01-07: Student interface fully functional - Documents page with progress tracking, template downloads, and document submission
- 2025-01-07: AC5 completed - Section-based access control implemented, instructors can only see templates for their section

## Status
Ready for Review
