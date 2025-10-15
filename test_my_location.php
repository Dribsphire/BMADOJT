<?php

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/src/Services/GeolocationService.php';

use App\Services\GeolocationService;

echo "🌍 Test Your Own Location\n";
echo "==========================\n\n";

// CHMSU coordinates (workplace)
$chmsu_lat = 10.3157;
$chmsu_lon = 123.8854;

echo "📍 CHMSU Workplace: {$chmsu_lat}, {$chmsu_lon}\n";
echo "🎯 Required: Within 40m radius for attendance\n\n";

// Example: Test with your current location
// Replace these with your actual GPS coordinates
$my_lat = 10.3158;  // Replace with your latitude
$my_lon = 123.8855; // Replace with your longitude

echo "🧪 Testing your location:\n";
echo "------------------------\n";

try {
    $distance = GeolocationService::calculateDistance(
        $chmsu_lat, $chmsu_lon,
        $my_lat, $my_lon
    );
    
    $isWithin = GeolocationService::isWithinRadius(
        $chmsu_lat, $chmsu_lon,
        $my_lat, $my_lon,
        40
    );
    
    echo "📍 Your Location: {$my_lat}, {$my_lon}\n";
    echo "📏 Distance from CHMSU: " . number_format($distance, 2) . "m\n";
    echo "🎯 Within 40m radius: " . ($isWithin ? "✅ YES" : "❌ NO") . "\n";
    echo "📱 Can attend: " . ($isWithin ? "✅ YES" : "❌ NO") . "\n";
    
    if ($isWithin) {
        echo "\n🎉 Great! You're within the attendance radius!\n";
    } else {
        echo "\n⚠️ You're outside the attendance radius. Move closer to CHMSU.\n";
    }
    
} catch (InvalidArgumentException $e) {
    echo "❌ Invalid coordinates: {$e->getMessage()}\n";
    echo "💡 Make sure your coordinates are valid:\n";
    echo "   - Latitude: -90 to 90 degrees\n";
    echo "   - Longitude: -180 to 180 degrees\n";
}

echo "\n";
echo "💡 To test with your real location:\n";
echo "1. Get your GPS coordinates from Google Maps\n";
echo "2. Edit this file and replace the coordinates above\n";
echo "3. Run: php test_my_location.php\n";
