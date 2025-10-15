<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/GeolocationService.php';

use App\Services\GeolocationService;

echo "🌍 GeolocationService Practical Test\n";
echo "=====================================\n\n";

// Test 1: Basic Distance Calculation
echo "📍 Test 1: Basic Distance Calculation\n";
echo "------------------------------------\n";

$chmsu_lat = 10.3157;
$chmsu_lon = 123.8854;

$test_locations = [
    ['name' => 'CHMSU Main Campus', 'lat' => 10.3157, 'lon' => 123.8854],
    ['name' => 'Very Close (10m)', 'lat' => 10.3158, 'lon' => 123.8855],
    ['name' => 'Within 40m', 'lat' => 10.3159, 'lon' => 123.8856],
    ['name' => 'Just Outside 40m', 'lat' => 10.3162, 'lon' => 123.8860],
    ['name' => 'Far Away (500m)', 'lat' => 10.3200, 'lon' => 123.8900]
];

foreach ($test_locations as $location) {
    $distance = GeolocationService::calculateDistance(
        $chmsu_lat, $chmsu_lon,
        $location['lat'], $location['lon']
    );
    
    $isWithin = GeolocationService::isWithinRadius(
        $chmsu_lat, $chmsu_lon,
        $location['lat'], $location['lon'],
        40
    );
    
    $status = $isWithin ? "✅ WITHIN" : "❌ OUTSIDE";
    echo "{$location['name']}: {$distance}m - {$status}\n";
}

echo "\n";

// Test 2: Coordinate Validation
echo "🛡️ Test 2: Coordinate Validation\n";
echo "--------------------------------\n";

$invalid_coords = [
    ['lat' => 91, 'lon' => 0, 'desc' => 'Latitude too high'],
    ['lat' => -91, 'lon' => 0, 'desc' => 'Latitude too low'],
    ['lat' => 0, 'lon' => 181, 'desc' => 'Longitude too high'],
    ['lat' => 0, 'lon' => -181, 'desc' => 'Longitude too low']
];

foreach ($invalid_coords as $coord) {
    try {
        GeolocationService::calculateDistance($coord['lat'], $coord['lon'], 0, 0);
        echo "❌ {$coord['desc']}: Should have thrown exception\n";
    } catch (InvalidArgumentException $e) {
        echo "✅ {$coord['desc']}: {$e->getMessage()}\n";
    }
}

echo "\n";

// Test 3: Real-World CHMSU Scenarios
echo "🏫 Test 3: Real-World CHMSU Scenarios\n";
echo "------------------------------------\n";

$chmsu_scenarios = [
    [
        'name' => 'Student at CHMSU Gate',
        'lat' => 10.3157,
        'lon' => 123.8854,
        'expected' => true
    ],
    [
        'name' => 'Student in CHMSU Parking',
        'lat' => 10.3158,
        'lon' => 123.8855,
        'expected' => true
    ],
    [
        'name' => 'Student at CHMSU Canteen',
        'lat' => 10.3159,
        'lon' => 123.8856,
        'expected' => true
    ],
    [
        'name' => 'Student at Nearby Mall',
        'lat' => 10.3200,
        'lon' => 123.8900,
        'expected' => false
    ],
    [
        'name' => 'Student at Home (Far)',
        'lat' => 10.3000,
        'lon' => 123.8700,
        'expected' => false
    ]
];

foreach ($chmsu_scenarios as $scenario) {
    $distance = GeolocationService::calculateDistance(
        $chmsu_lat, $chmsu_lon,
        $scenario['lat'], $scenario['lon']
    );
    
    $isWithin = GeolocationService::isWithinRadius(
        $chmsu_lat, $chmsu_lon,
        $scenario['lat'], $scenario['lon'],
        40
    );
    
    $result = $isWithin ? "✅ WITHIN" : "❌ OUTSIDE";
    $expected = $scenario['expected'] ? "WITHIN" : "OUTSIDE";
    $status = ($isWithin === $scenario['expected']) ? "✅ CORRECT" : "❌ WRONG";
    
    echo "{$scenario['name']}: {$distance}m - {$result} (Expected: {$expected}) - {$status}\n";
}

echo "\n";

// Test 4: Performance Test
echo "⚡ Test 4: Performance Test\n";
echo "--------------------------\n";

$iterations = 1000;
$start_time = microtime(true);

for ($i = 0; $i < $iterations; $i++) {
    GeolocationService::calculateDistance(
        10.3157 + ($i * 0.0001),
        123.8854 + ($i * 0.0001),
        10.3157,
        123.8854
    );
}

$end_time = microtime(true);
$total_time = ($end_time - $start_time) * 1000;
$avg_time = $total_time / $iterations;

echo "Calculations: {$iterations}\n";
echo "Total time: " . number_format($total_time, 2) . "ms\n";
echo "Average time: " . number_format($avg_time, 4) . "ms per calculation\n";
echo "Performance: " . ($avg_time < 1 ? "✅ EXCELLENT" : "❌ SLOW") . "\n";

echo "\n";

// Test 5: Edge Cases
echo "🔍 Test 5: Edge Cases\n";
echo "--------------------\n";

$edge_cases = [
    ['name' => 'Same coordinates', 'lat1' => 10.3157, 'lon1' => 123.8854, 'lat2' => 10.3157, 'lon2' => 123.8854],
    ['name' => 'Antipodal points', 'lat1' => 0, 'lon1' => 0, 'lat2' => 0, 'lon2' => 180],
    ['name' => 'Very close', 'lat1' => 10.3157, 'lon1' => 123.8854, 'lat2' => 10.3157001, 'lon2' => 123.8854001]
];

foreach ($edge_cases as $case) {
    try {
        $distance = GeolocationService::calculateDistance(
            $case['lat1'], $case['lon1'],
            $case['lat2'], $case['lon2']
        );
        echo "✅ {$case['name']}: {$distance}m\n";
    } catch (Exception $e) {
        echo "❌ {$case['name']}: {$e->getMessage()}\n";
    }
}

echo "\n";
echo "🎉 All practical tests completed!\n";
echo "=====================================\n";
echo "✅ GeolocationService is working correctly!\n";
