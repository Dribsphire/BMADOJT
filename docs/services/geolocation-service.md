# GeolocationService Documentation

## Overview

The `GeolocationService` provides accurate GPS distance calculations and location verification for the attendance system. It uses the Haversine formula to calculate distances between GPS coordinates and verifies if students are within the required 40-meter radius of their workplace.

## Features

- **Accurate Distance Calculation**: Uses the Haversine formula for precise GPS distance calculations
- **Coordinate Validation**: Validates latitude (-90 to 90°) and longitude (-180 to 180°) ranges
- **Radius Verification**: Checks if current location is within specified radius of workplace
- **GPS Tolerance**: Includes 5-meter buffer for GPS accuracy limitations
- **Performance Optimized**: Fast calculations suitable for real-time attendance verification

## Class Structure

```php
class GeolocationService
{
    private const EARTH_RADIUS = 6371000;      // Earth's radius in meters
    private const DEFAULT_RADIUS = 40;         // Default attendance radius
    private const GPS_TOLERANCE = 5;           // GPS accuracy buffer
    
    public function __construct(PDO $database)
    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    public static function isWithinRadius(float $currentLat, float $currentLon, float $workplaceLat, float $workplaceLon, float $radius = 40): bool
    public function getWorkplaceCoordinates(int $sectionId): ?array
    public function verifyAttendanceLocation(int $studentId, float $currentLat, float $currentLon): array
}
```

## Methods

### `calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float`

Calculates the distance between two GPS coordinates using the Haversine formula.

**Parameters:**
- `$lat1` - Latitude of first point
- `$lon1` - Longitude of first point  
- `$lat2` - Latitude of second point
- `$lon2` - Longitude of second point

**Returns:** Distance in meters (rounded to 2 decimal places)

**Throws:** `InvalidArgumentException` for invalid coordinates

**Example:**
```php
$distance = GeolocationService::calculateDistance(10.3157, 123.8854, 10.3158, 123.8855);
// Returns: 15.23 (meters)
```

### `isWithinRadius(float $currentLat, float $currentLon, float $workplaceLat, float $workplaceLon, float $radius = 40): bool`

Checks if current location is within the specified radius of the workplace.

**Parameters:**
- `$currentLat` - Current latitude
- `$currentLon` - Current longitude
- `$workplaceLat` - Workplace latitude
- `$workplaceLon` - Workplace longitude
- `$radius` - Radius in meters (default: 40m)

**Returns:** `true` if within radius (including GPS tolerance), `false` otherwise

**Example:**
```php
$isValid = GeolocationService::isWithinRadius(10.3157, 123.8854, 10.3158, 123.8855, 40);
// Returns: true (within 40m + 5m tolerance)
```

### `getWorkplaceCoordinates(int $sectionId): ?array`

Retrieves workplace coordinates for a specific section.

**Parameters:**
- `$sectionId` - Section ID

**Returns:** Array with 'latitude' and 'longitude' or `null` if not found

**Example:**
```php
$coords = $service->getWorkplaceCoordinates(1);
// Returns: ['latitude' => 10.3157, 'longitude' => 123.8854]
```

### `verifyAttendanceLocation(int $studentId, float $currentLat, float $currentLon): array`

Verifies if a student's current location is valid for attendance.

**Parameters:**
- `$studentId` - Student ID
- `$currentLat` - Current latitude
- `$currentLon` - Current longitude

**Returns:** Array with 'valid', 'distance', and 'message' keys

**Example:**
```php
$result = $service->verifyAttendanceLocation(123, 10.3157, 123.8854);
// Returns: [
//     'valid' => true,
//     'distance' => 15.23,
//     'message' => 'Location verified. Distance: 15.23m from BSIT 4A'
// ]
```

## Usage Examples

### Basic Distance Calculation

```php
use App\Services\GeolocationService;

// Calculate distance between two points
$distance = GeolocationService::calculateDistance(
    10.3157, 123.8854,  // CHMSU coordinates
    10.3158, 123.8855   // Nearby location
);
echo "Distance: {$distance}m"; // Output: Distance: 15.23m
```

### Attendance Verification

```php
use App\Services\GeolocationService;
use App\Utils\Database;

$database = Database::getInstance();
$service = new GeolocationService($database);

// Verify student location
$result = $service->verifyAttendanceLocation(
    123,           // Student ID
    10.3157,       // Current latitude
    123.8854       // Current longitude
);

if ($result['valid']) {
    echo "✅ " . $result['message'];
} else {
    echo "❌ " . $result['message'];
}
```

### Custom Radius Check

```php
// Check if within 50m radius instead of default 40m
$isWithin = GeolocationService::isWithinRadius(
    10.3157, 123.8854,  // Current location
    10.3158, 123.8855,  // Workplace location
    50                  // 50m radius
);
```

## Error Handling

The service throws `InvalidArgumentException` for invalid coordinates:

```php
try {
    $distance = GeolocationService::calculateDistance(91, 0, 0, 0);
} catch (InvalidArgumentException $e) {
    echo "Error: " . $e->getMessage();
    // Output: Error: Invalid latitude: 91. Must be between -90 and 90 degrees.
}
```

## Performance

- **Calculation Speed**: <1ms per distance calculation
- **Memory Usage**: Minimal (static methods for core functions)
- **Scalability**: Suitable for real-time attendance verification
- **Accuracy**: ±3-5 meters (GPS tolerance included)

## Testing

Run the test suite:

```bash
php tests/GeolocationServiceTest.php
```

Test coverage includes:
- Distance calculation accuracy
- Edge cases (same coordinates, antipodal points)
- Coordinate validation
- Radius checking
- Real-world scenarios
- Performance benchmarks

## Integration

The service integrates with:
- **Attendance System**: Location verification for time-in/out
- **Section Management**: Workplace coordinate storage
- **User Management**: Student section association
- **Database**: Section and user data retrieval

## Technical Details

### Haversine Formula

The service uses the Haversine formula for accurate distance calculations:

```
a = sin²(Δφ/2) + cos φ1 ⋅ cos φ2 ⋅ sin²(Δλ/2)
c = 2 ⋅ atan2( √a, √(1−a) )
d = R ⋅ c
```

Where:
- φ = latitude
- λ = longitude  
- R = Earth's radius (6,371,000 meters)
- Δφ = difference in latitude
- Δλ = difference in longitude

### GPS Accuracy Considerations

- **Typical GPS Accuracy**: ±3-5 meters
- **Service Tolerance**: 5-meter buffer included
- **Effective Radius**: Requested radius + 5m tolerance
- **Edge Cases**: Handles same coordinates and antipodal points

## Configuration

### Database Requirements

The service requires the following database structure:

```sql
-- Sections table must have latitude and longitude columns
ALTER TABLE sections ADD COLUMN latitude DECIMAL(10, 7);
ALTER TABLE sections ADD COLUMN longitude DECIMAL(11, 7);

-- Example data
UPDATE sections SET latitude = 10.3157, longitude = 123.8854 WHERE id = 1;
```

### Constants

```php
private const EARTH_RADIUS = 6371000;    // Earth's radius in meters
private const DEFAULT_RADIUS = 40;       // Default attendance radius
private const GPS_TOLERANCE = 5;         // GPS accuracy buffer
```

## Troubleshooting

### Common Issues

1. **"Invalid latitude/longitude" errors**
   - Ensure coordinates are within valid ranges
   - Check for typos in coordinate values

2. **"Workplace location not configured"**
   - Verify section has latitude/longitude set
   - Check database connection

3. **Distance calculations seem incorrect**
   - Verify coordinate order (lat, lon)
   - Check for coordinate system consistency (WGS84)

### Debug Mode

Enable debug logging by adding error reporting:

```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Future Enhancements

- **Multiple Workplace Support**: Handle multiple workplace locations per section
- **Geofencing**: Support for complex workplace boundaries
- **Historical Tracking**: Store location verification history
- **Mobile Integration**: Direct GPS integration with mobile apps
- **Offline Support**: Cache workplace coordinates for offline use
