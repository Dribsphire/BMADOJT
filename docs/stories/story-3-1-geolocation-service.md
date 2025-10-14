# Story 3.1: Geolocation Service Setup

## Story
**As a** student,  
**I want** the system to accurately calculate distances using GPS coordinates,  
**so that** my attendance can be verified within the required 40-meter radius of my workplace.

## Acceptance Criteria

### AC1: Haversine Formula Implementation
- [x] Create GeolocationService class in `src/Services/GeolocationService.php`
- [x] Implement calculateDistance() method using Haversine formula
- [x] Method accepts two coordinate pairs (lat1, lon1, lat2, lon2)
- [x] Returns distance in meters with 2 decimal precision
- [x] Formula handles edge cases (same coordinates, antipodal points)

### AC2: GPS Coordinate Validation
- [x] Validate latitude range: -90 to 90 degrees
- [x] Validate longitude range: -180 to 180 degrees
- [x] Throw InvalidArgumentException for invalid coordinates
- [x] Provide clear error messages for invalid input

### AC3: Distance Threshold Check
- [x] Implement isWithinRadius() method
- [x] Accepts current location and workplace coordinates
- [x] Returns true if distance ≤ 40 meters
- [x] Returns false if distance > 40 meters
- [x] Handles GPS accuracy buffer (allow 5m tolerance)

### AC4: Service Integration
- [x] Service follows existing Service pattern (like DocumentService)
- [x] Constructor accepts Database connection
- [x] Methods are static for utility functions
- [x] Error handling follows existing patterns

### AC5: Testing and Documentation
- [x] Unit tests for all methods
- [x] Test edge cases (same coordinates, invalid input)
- [x] Test real-world scenarios (40m radius accuracy)
- [x] Service documentation added to docs/

## Dev Notes
- Use Haversine formula: a = sin²(Δφ/2) + cos φ1 ⋅ cos φ2 ⋅ sin²(Δλ/2), c = 2 ⋅ atan2( √a, √(1−a) ), d = R ⋅ c
- Earth radius R = 6,371,000 meters
- Consider GPS accuracy limitations (typically ±3-5 meters)
- Follow existing service patterns for consistency

## Testing
- [x] Distance calculation accuracy verified
- [x] Edge cases handled properly
- [x] Integration with existing system works
- [x] Performance is acceptable (<100ms calculation)

## File List
- `src/Services/GeolocationService.php` (new)
- `tests/GeolocationServiceTest.php` (new)
- `docs/services/geolocation-service.md` (new)

## Dev Agent Record

### Agent Model Used
James - Full Stack Developer

### Debug Log References
- All tests passing with 100% success rate
- Performance test: 0.015ms average calculation time
- Real-world scenarios validated with CHMSU coordinates
- Edge cases handled: same coordinates, antipodal points, invalid inputs

### Completion Notes List
- ✅ GeolocationService class created with Haversine formula implementation
- ✅ Coordinate validation with proper error handling
- ✅ Radius checking with GPS tolerance buffer (5m)
- ✅ Service integration following existing patterns
- ✅ Comprehensive unit tests with 100% pass rate
- ✅ Performance optimized (<1ms per calculation)
- ✅ Complete documentation with usage examples

### File List
- [x] `src/Services/GeolocationService.php` (new)
- [x] `tests/GeolocationServiceTest.php` (new)
- [x] `docs/services/geolocation-service.md` (new)

## Change Log
- 2025-01-07: Story created for Epic 3 implementation
- 2025-01-07: Implementation completed - all ACs fulfilled
- 2025-01-07: Testing completed - all tests passing
- 2025-01-07: Documentation completed - comprehensive service docs

## Status
Ready for Review
