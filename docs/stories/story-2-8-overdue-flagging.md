# Story 2.8: Overdue Flagging

## Story
**As an** instructor,  
**I want** to see which documents are overdue,  
**so that** I can identify students who need attention and follow up on missing submissions.

## Acceptance Criteria

### AC1: Overdue Detection
- [x] Automatically detect overdue documents
- [x] Compare submission deadlines with current date
- [x] Flag documents past deadline
- [x] Handle timezone considerations
- [x] Update overdue status in real-time

### AC2: Overdue Display
- [x] Highlight overdue documents in red
- [x] Show overdue indicators in dashboards
- [x] Display overdue count and list
- [x] Sort by overdue status
- [x] Filter by overdue documents

### AC3: Overdue Notifications
- [x] Send overdue notifications to students
- [x] Notify instructors of overdue documents
- [x] Include overdue details in notifications
- [x] Handle notification failures
- [x] Track notification delivery

### AC4: Overdue Management
- [x] Track overdue statistics
- [x] Generate overdue reports
- [x] Support overdue analytics
- [x] Handle overdue escalations
- [x] Manage overdue policies

### AC5: Overdue Resolution
- [x] Mark overdue documents as resolved
- [x] Handle overdue document submissions
- [x] Update overdue status
- [x] Track overdue resolution
- [x] Support overdue follow-up

## Dev Notes
- Use existing notification system
- Implement efficient overdue detection
- Ensure proper timezone handling
- Consider performance for large datasets
- Maintain overdue audit trail

## Testing
- [x] Overdue detection works correctly
- [x] Overdue display functional
- [x] Notifications sent properly
- [x] Management features work
- [x] Resolution process functional
- [x] Performance acceptable

## File List
- [x] `src/Services/OverdueService.php` (new)
- [x] `src/Controllers/OverdueController.php` (new)
- [x] `cron/check_overdue_documents.php` (new)
- [x] Updated `public/student/documents.php` for overdue display
- [x] Updated `src/Services/EmailService.php` for overdue notifications

## Change Log
- 2025-01-07: Story created for Epic 2 implementation
- 2025-01-07: OverdueService and OverdueController implemented
- 2025-01-07: Student documents page updated with overdue highlighting
- 2025-01-07: Email notifications for overdue documents implemented
- 2025-01-07: Cron job for automated overdue checking created
- 2025-01-07: All acceptance criteria completed and tested

## Status
Ready for Review
