<?php

namespace App\Services;

use InvalidArgumentException;
use PDO;

/**
 * GeolocationService - Handles GPS coordinate calculations and distance verification
 * 
 * Provides accurate distance calculations using the Haversine formula for
 * attendance verification within specified radius of workplace locations.
 */
class GeolocationService
{
    /**
     * Earth's radius in meters
     */
    private const EARTH_RADIUS = 6371000;

    /**
     * Default attendance radius in meters
     */
    private const DEFAULT_RADIUS = 40;

    /**
     * GPS accuracy tolerance in meters
     */
    private const GPS_TOLERANCE = 5;

    private PDO $database;

    public function __construct(PDO $database)
    {
        $this->database = $database;
        // Set timezone to Philippines (UTC+08:00)
        date_default_timezone_set('Asia/Manila');
    }

    /**
     * Calculate distance between two GPS coordinates using Haversine formula
     * 
     * @param float $lat1 Latitude of first point
     * @param float $lon1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lon2 Longitude of second point
     * @return float Distance in meters (rounded to 2 decimal places)
     * @throws InvalidArgumentException for invalid coordinates
     */
    public static function calculateDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        // Validate coordinates
        self::validateCoordinates($lat1, $lon1);
        self::validateCoordinates($lat2, $lon2);

        // Handle edge case: same coordinates
        if ($lat1 === $lat2 && $lon1 === $lon2) {
            return 0.0;
        }

        // Convert degrees to radians
        $lat1Rad = deg2rad($lat1);
        $lon1Rad = deg2rad($lon1);
        $lat2Rad = deg2rad($lat2);
        $lon2Rad = deg2rad($lon2);

        // Calculate differences
        $deltaLat = $lat2Rad - $lat1Rad;
        $deltaLon = $lon2Rad - $lon1Rad;

        // Haversine formula
        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1Rad) * cos($lat2Rad) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = self::EARTH_RADIUS * $c;

        return round($distance, 2);
    }

    /**
     * Check if current location is within specified radius of workplace
     * 
     * @param float $currentLat Current latitude
     * @param float $currentLon Current longitude
     * @param float $workplaceLat Workplace latitude
     * @param float $workplaceLon Workplace longitude
     * @param float $radius Radius in meters (default: 40m)
     * @return bool True if within radius (including GPS tolerance)
     */
    public static function isWithinRadius(
        float $currentLat, 
        float $currentLon, 
        float $workplaceLat, 
        float $workplaceLon, 
        float $radius = self::DEFAULT_RADIUS
    ): bool {
        $distance = self::calculateDistance($currentLat, $currentLon, $workplaceLat, $workplaceLon);
        
        // Add GPS tolerance buffer
        $effectiveRadius = $radius + self::GPS_TOLERANCE;
        
        return $distance <= $effectiveRadius;
    }

    /**
     * Validate GPS coordinates
     * 
     * @param float $lat Latitude
     * @param float $lon Longitude
     * @throws InvalidArgumentException for invalid coordinates
     */
    private static function validateCoordinates(float $lat, float $lon): void
    {
        if ($lat < -90 || $lat > 90) {
            throw new InvalidArgumentException(
                "Invalid latitude: {$lat}. Must be between -90 and 90 degrees."
            );
        }

        if ($lon < -180 || $lon > 180) {
            throw new InvalidArgumentException(
                "Invalid longitude: {$lon}. Must be between -180 and 180 degrees."
            );
        }
    }

    /**
     * Get workplace coordinates for a student
     * 
     * @param int $studentId Student ID
     * @return array|null Array with 'latitude', 'longitude', and 'workplace_name' or null if not found
     */
    public function getWorkplaceCoordinates(int $studentId): ?array
    {
        $stmt = $this->database->prepare("
            SELECT workplace_latitude as latitude, workplace_longitude as longitude, workplace_name
            FROM student_profiles 
            WHERE user_id = ? 
            AND workplace_latitude IS NOT NULL AND workplace_longitude IS NOT NULL
        ");
        
        $stmt->execute([$studentId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ?: null;
    }

    /**
     * Verify attendance location for a student
     * 
     * @param int $studentId Student ID
     * @param float $currentLat Current latitude
     * @param float $currentLon Current longitude
     * @return array Result with 'valid', 'distance', and 'message'
     */
    public function verifyAttendanceLocation(int $studentId, float $currentLat, float $currentLon): array
    {
        // Check if coordinates are fallback coordinates (0,0) - this indicates GPS error
        if ($currentLat == 0 && $currentLon == 0) {
            return [
                'valid' => false,
                'distance' => null,
                'message' => 'GPS location not available. Please enable location services and try again.'
            ];
        }
        
        // Get student's individual workplace coordinates from student_profiles
        $stmt = $this->database->prepare("
            SELECT sp.workplace_latitude, sp.workplace_longitude, sp.workplace_name, s.section_name
            FROM student_profiles sp
            JOIN users u ON sp.user_id = u.id
            JOIN sections s ON u.section_id = s.id
            WHERE sp.user_id = ? AND u.role = 'student'
        ");
        
        $stmt->execute([$studentId]);
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$student || !$student['workplace_latitude'] || !$student['workplace_longitude']) {
            return [
                'valid' => false,
                'distance' => null,
                'message' => 'Workplace location not configured. Please contact your instructor to set up your workplace location.'
            ];
        }

        $distance = self::calculateDistance(
            $currentLat, 
            $currentLon, 
            (float)$student['workplace_latitude'], 
            (float)$student['workplace_longitude']
        );

        $isWithinRadius = self::isWithinRadius(
            $currentLat, 
            $currentLon, 
            (float)$student['workplace_latitude'], 
            (float)$student['workplace_longitude']
        );

        return [
            'valid' => $isWithinRadius,
            'distance' => $distance,
            'message' => $isWithinRadius 
                ? "Location verified. Distance: {$distance}m from {$student['workplace_name']}"
                : "Location too far. Distance: {$distance}m (required: ≤40m from {$student['workplace_name']})"
        ];
    }
}
