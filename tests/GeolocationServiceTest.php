<?php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Services/GeolocationService.php';

use App\Services\GeolocationService;

/**
 * Unit tests for GeolocationService
 */
class GeolocationServiceTest
{
    private PDO $database;
    private GeolocationService $service;

    public function __construct()
    {
        // Setup test database connection
        $this->database = new PDO('sqlite::memory:');
        $this->service = new GeolocationService($this->database);
    }

    public function runAllTests(): void
    {
        echo "🧪 Running GeolocationService Tests...\n\n";

        $this->testCalculateDistance();
        $this->testCalculateDistanceEdgeCases();
        $this->testCoordinateValidation();
        $this->testIsWithinRadius();
        $this->testRealWorldScenarios();
        $this->testPerformance();

        echo "\n✅ All tests completed!\n";
    }

    /**
     * Test basic distance calculation accuracy
     */
    private function testCalculateDistance(): void
    {
        echo "📍 Testing distance calculation...\n";

        // Test case 1: Same coordinates
        $distance = GeolocationService::calculateDistance(0, 0, 0, 0);
        assert($distance === 0.0, "Same coordinates should return 0 distance");

        // Test case 2: Known distance (approximately 1 degree = ~111km)
        $distance = GeolocationService::calculateDistance(0, 0, 1, 0);
        $expected = 111320; // Approximately 111.32 km
        $tolerance = 1000; // 1km tolerance
        assert(abs($distance - $expected) < $tolerance, "1 degree latitude should be ~111km");

        // Test case 3: CHMSU coordinates to nearby location
        $chmsu_lat = 10.3157;
        $chmsu_lon = 123.8854;
        $nearby_lat = 10.3160;
        $nearby_lon = 123.8857;
        
        $distance = GeolocationService::calculateDistance($chmsu_lat, $chmsu_lon, $nearby_lat, $nearby_lon);
        assert($distance > 0 && $distance < 100, "Nearby locations should be close");

        echo "✅ Distance calculation tests passed\n";
    }

    /**
     * Test edge cases for distance calculation
     */
    private function testCalculateDistanceEdgeCases(): void
    {
        echo "🔍 Testing edge cases...\n";

        // Test antipodal points (opposite sides of Earth)
        $distance = GeolocationService::calculateDistance(0, 0, 0, 180);
        $expected = 20015000; // Half Earth's circumference
        $tolerance = 100000; // 100km tolerance
        assert(abs($distance - $expected) < $tolerance, "Antipodal points should be ~20,015km apart");

        // Test very close coordinates
        $distance = GeolocationService::calculateDistance(10.3157, 123.8854, 10.3158, 123.8855);
        assert($distance > 0 && $distance < 50, "Very close coordinates should be <50m apart");

        echo "✅ Edge cases tests passed\n";
    }

    /**
     * Test coordinate validation
     */
    private function testCoordinateValidation(): void
    {
        echo "🛡️ Testing coordinate validation...\n";

        $invalidCases = [
            [-91, 0, "Latitude too low"],
            [91, 0, "Latitude too high"],
            [0, -181, "Longitude too low"],
            [0, 181, "Longitude too high"],
            [-90.1, 0, "Latitude just below minimum"],
            [90.1, 0, "Latitude just above maximum"],
            [0, -180.1, "Longitude just below minimum"],
            [0, 180.1, "Longitude just above maximum"]
        ];

        foreach ($invalidCases as [$lat, $lon, $description]) {
            try {
                GeolocationService::calculateDistance($lat, $lon, 0, 0);
                assert(false, "Should throw exception for {$description}");
            } catch (InvalidArgumentException $e) {
                assert(strpos($e->getMessage(), 'Invalid') !== false, "Error message should mention 'Invalid'");
            }
        }

        // Test valid boundary cases
        $validCases = [
            [-90, 0, "Minimum latitude"],
            [90, 0, "Maximum latitude"],
            [0, -180, "Minimum longitude"],
            [0, 180, "Maximum longitude"]
        ];

        foreach ($validCases as [$lat, $lon, $description]) {
            try {
                $distance = GeolocationService::calculateDistance($lat, $lon, 0, 0);
                assert($distance >= 0, "Valid boundary case should work: {$description}");
            } catch (Exception $e) {
                assert(false, "Valid boundary case should not throw exception: {$description}");
            }
        }

        echo "✅ Coordinate validation tests passed\n";
    }

    /**
     * Test radius checking functionality
     */
    private function testIsWithinRadius(): void
    {
        echo "🎯 Testing radius checking...\n";

        // Test within radius (should be true)
        $within = GeolocationService::isWithinRadius(10.3157, 123.8854, 10.3158, 123.8855, 40);
        assert($within === true, "Close coordinates should be within 40m radius");

        // Test outside radius (should be false)
        $outside = GeolocationService::isWithinRadius(10.3157, 123.8854, 10.3200, 123.8900, 40);
        assert($outside === false, "Far coordinates should be outside 40m radius");

        // Test exact boundary (40m + 5m tolerance = 45m effective)
        $boundary = GeolocationService::isWithinRadius(10.3157, 123.8854, 10.3157, 123.8854, 40);
        assert($boundary === true, "Same coordinates should be within any radius");

        echo "✅ Radius checking tests passed\n";
    }

    /**
     * Test real-world scenarios
     */
    private function testRealWorldScenarios(): void
    {
        echo "🌍 Testing real-world scenarios...\n";

        // CHMSU main campus coordinates
        $chmsu_lat = 10.3157;
        $chmsu_lon = 123.8854;

        // Test various distances from CHMSU
        $testLocations = [
            ['lat' => 10.3157, 'lon' => 123.8854, 'expected' => true, 'desc' => 'Exact CHMSU location'],
            ['lat' => 10.3158, 'lon' => 123.8855, 'expected' => true, 'desc' => 'Very close to CHMSU'],
            ['lat' => 10.3159, 'lon' => 123.8856, 'expected' => true, 'desc' => 'Within 40m of CHMSU'],
            ['lat' => 10.3200, 'lon' => 123.8900, 'expected' => false, 'desc' => 'Far from CHMSU']
        ];

        foreach ($testLocations as $location) {
            $result = GeolocationService::isWithinRadius(
                $location['lat'], 
                $location['lon'], 
                $chmsu_lat, 
                $chmsu_lon, 
                40
            );
            assert($result === $location['expected'], 
                "Failed for {$location['desc']}: expected {$location['expected']}, got {$result}");
        }

        echo "✅ Real-world scenario tests passed\n";
    }

    /**
     * Test performance requirements
     */
    private function testPerformance(): void
    {
        echo "⚡ Testing performance...\n";

        $iterations = 1000;
        $startTime = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            GeolocationService::calculateDistance(
                10.3157 + ($i * 0.0001), 
                123.8854 + ($i * 0.0001), 
                10.3157, 
                123.8854
            );
        }

        $endTime = microtime(true);
        $totalTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $avgTime = $totalTime / $iterations;

        assert($avgTime < 1, "Average calculation time should be <1ms, got {$avgTime}ms");
        echo "✅ Performance test passed (avg: {$avgTime}ms per calculation)\n";
    }
}

// Run tests if called directly
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    $test = new GeolocationServiceTest();
    $test->runAllTests();
}
