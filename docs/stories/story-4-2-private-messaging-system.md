# Story 4.2: Private Messaging System

## Story
**As a** student or instructor,  
**I want** to send and receive private messages with read receipts,  
**so that** I can communicate directly and track message status.

## Acceptance Criteria

### AC1: Message Interface
- [x] Create messaging interface for students and instructors
- [x] Display conversation list with latest messages
- [x] Show unread message count
- [x] Implement message composition interface
- [x] Support message threading

### AC2: Message Sending
- [x] Send messages between students and instructors
- [x] Validate message content and length
- [x] Support text messages with formatting
- [x] Handle message delivery errors
- [x] Store messages in database

### AC3: Message Receiving
- [x] Display received messages in conversation view
- [x] Show message timestamps and sender information
- [x] Support message history and pagination
- [x] Handle message display errors
- [x] Support message search

### AC4: Read Receipts
- [x] Track message read status
- [x] Update read status when message is viewed
- [x] Display read receipts to sender
- [x] Show read timestamps
- [x] Handle read receipt errors

### AC5: Message Management
- [x] Delete messages (soft delete)
- [x] Archive conversations
- [x] Mark messages as read/unread
- [x] Support message forwarding
- [x] Handle message cleanup

## Dev Notes
- Use existing database messages table
- Follow existing UI patterns
- Implement real-time updates with JavaScript
- Ensure mobile-responsive design
- Handle concurrent message access

## Testing
- [x] Message sending works correctly
- [x] Read receipts function properly
- [x] Message history displays correctly
- [x] Mobile interface responsive
- [x] Error handling comprehensive
- [x] Performance acceptable

## File List
- `public/student/messages.php` (new)
- `public/instructor/messages.php` (new)
- `src/Controllers/MessageController.php` (new)
- `src/Services/MessageService.php` (new)
- `public/js/messaging.js` (new)

## Change Log
- 2025-01-07: Story created for Epic 4 implementation
- 2025-01-07: Story 4.2 implementation completed
  - Created MessageService with full messaging functionality
  - Created MessageController with API endpoints
  - Created student and instructor messaging interfaces
  - Created JavaScript messaging system with real-time updates
  - Added messaging links to both student and instructor sidebars
  - Implemented read receipts, message search, and conversation management
  - Added comprehensive error handling and mobile-responsive design

## Status
Ready for Review
