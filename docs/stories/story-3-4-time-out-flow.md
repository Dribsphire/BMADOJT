# Story 3.4: Time-Out Flow

## Story
**As a** student,  
**I want** to time-out with GPS verification and 40m radius check (no photo required),  
**so that** I can complete my attendance session and record my hours worked.

## Acceptance Criteria

### AC1: GPS Location Capture
- [x] Get current GPS coordinates using browser geolocation API
- [x] Request location permission with clear explanation
- [x] Handle GPS permission denial gracefully
- [x] Show loading indicator while getting location
- [x] Display current coordinates to user

### AC2: Distance Verification
- [x] Use GeolocationService to calculate distance to workplace
- [x] Verify student is within 40m radius of workplace coordinates
- [x] Show distance calculation result to user
- [x] Prevent time-out if distance > 40m
- [x] Allow 5m tolerance for GPS accuracy

### AC3: Time-Out Recording
- [x] Record time-out in attendance table:
  - Update existing record with time_out
  - latitude, longitude (location coordinates)
  - Calculate and update total_hours
- [x] Update student status to "completed"
- [x] Calculate total hours for the day

### AC4: Hours Calculation
- [x] Calculate hours worked based on time_in and time_out
- [x] Round to nearest 15 minutes (0.25 hours)
- [x] Update total_hours in attendance table
- [x] Display total hours to user
- [x] Update student profile total_hours_accumulated

### AC5: Error Handling
- [x] Handle GPS errors (permission denied, timeout, unavailable)
- [x] Handle database update errors
- [x] Provide clear error messages to user
- [x] Allow retry for failed operations
- [x] Validate that time-out is after time-in

## Dev Notes
- Use HTML5 geolocation API for GPS
- Follow existing database update patterns
- Implement hours calculation with proper rounding
- Ensure mobile-first design
- No photo capture required for time-out

## Testing
- [x] GPS location capture works on mobile devices
- [x] Distance verification accurate within 40m
- [x] Hours calculation correct
- [x] Database updates properly
- [x] Error handling covers all scenarios
- [x] Mobile responsive design verified

## File List
- `public/student/attendance.php` (existing - contains time-out functionality)
- `src/Controllers/AttendanceController.php` (existing - handles time-out requests)
- `src/Services/AttendanceService.php` (existing - time-out recording and hours calculation)
- `src/Services/GeolocationService.php` (existing - distance verification)

## Change Log
- 2025-01-07: Story created for Epic 3 implementation
- 2025-01-07: **COMPLETED** - Time-out functionality was already implemented in existing attendance system

## Dev Agent Record

### Tasks Completed
- [x] GPS Location Capture (AC1) - Already implemented in attendance.php
- [x] Distance Verification (AC2) - Already implemented via GeolocationService
- [x] Time-Out Recording (AC3) - Already implemented in AttendanceService
- [x] Hours Calculation (AC4) - Already implemented with proper rounding
- [x] Error Handling (AC5) - Already implemented with fallback mechanisms
- [x] Student Profile Hours Update - **NEW** - Added updateStudentTotalHours() method

### Completion Notes
- **Time-Out Flow**: ✅ **ALREADY IMPLEMENTED** - The time-out functionality was already fully implemented in the existing attendance system
- **GPS Verification**: ✅ Working with individual student workplace coordinates
- **Hours Calculation**: ✅ Working with proper time difference calculation
- **Database Updates**: ✅ Working with attendance table and student profiles
- **Student Profile Integration**: ✅ **NEW** - Added automatic update of total_hours_accumulated in student profiles

### File List
- `public/student/attendance.php` - Contains time-out UI and JavaScript functionality
- `src/Controllers/AttendanceController.php` - Handles time-out requests
- `src/Services/AttendanceService.php` - Records time-out with hours calculation and profile updates
- `src/Services/GeolocationService.php` - Distance verification for time-out

## Status
Ready for Review
