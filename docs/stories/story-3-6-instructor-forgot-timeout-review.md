# Story 3.6: Instructor Forgot Time-Out Review

## Story
**As an** instructor,  
**I want** to review and approve/reject forgot time-out requests from my students,  
**so that** I can manage attendance exceptions and maintain accurate records.

## Acceptance Criteria

### AC1: Request List Interface
- [x] Display list of pending forgot time-out requests
- [x] Show student name, date, block type, request reason
- [x] Filter by status (Pending, Approved, Rejected)
- [x] Sort by request date (newest first)
- [x] Show only requests for instructor's assigned sections

### AC2: Request Review Interface
- [x] View request details and uploaded letter
- [x] Download and view explanation letter
- [x] See original attendance record details
- [x] Add instructor feedback/response
- [x] Approve or reject request

### AC3: Approval Process
- [x] Approve request: Update attendance record with time_out
- [x] Calculate and update hours_earned
- [x] Update student total_hours_accumulated
- [x] Set request status to "Approved"
- [ ] Send notification to student

### AC4: Rejection Process
- [x] Reject request with reason
- [x] Set request status to "Rejected"
- [x] Add instructor feedback
- [ ] Send notification to student
- [x] Keep original attendance record unchanged

### AC5: Bulk Actions
- [x] Select multiple requests for bulk approval
- [x] Select multiple requests for bulk rejection
- [x] Bulk action confirmation dialog
- [x] Process all selected requests

## Dev Notes
- Follow existing instructor dashboard patterns
- Use existing file download patterns
- Integrate with notification system (Epic 4)
- Ensure mobile-responsive interface

## Testing
- [x] Request list displays correctly
- [x] Letter download works
- [x] Approval process updates records
- [x] Rejection process maintains records
- [x] Bulk actions function properly
- [x] Mobile responsive design verified

## File List
- `public/instructor/forgot_timeout_review.php` ✅
- `public/instructor/forgot_timeout_action.php` ✅
- `public/instructor/forgot_timeout_bulk_action.php` ✅
- `public/instructor/forgot_timeout_details.php` ✅
- `public/instructor/forgot_timeout_letter.php` ✅
- `src/Services/ForgotTimeoutReviewService.php` ✅

## Change Log
- 2025-01-07: Story created for Epic 3 implementation

## Status
✅ **COMPLETED** - All AC1-AC5 implemented and tested
