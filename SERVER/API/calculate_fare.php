<?php
header('Content-Type: application/json');

require_once 'db-connect.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$pickup_lat = $_POST['pickup_lat'] ?? 0;
$pickup_lng = $_POST['pickup_lng'] ?? 0;
$dest_lat = $_POST['dest_lat'] ?? 0;
$dest_lng = $_POST['dest_lng'] ?? 0;
$ride_type = $_POST['ride_type'] ?? 'economy';

if (!$pickup_lat || !$pickup_lng || !$dest_lat || !$dest_lng) {
    echo json_encode(['success' => false, 'message' => 'Missing coordinates']);
    exit;
}

// Calculate distance using Haversine formula
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371; // Earth's radius in km
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c;
}

$distance = calculateDistance($pickup_lat, $pickup_lng, $dest_lat, $dest_lng);
$distance = max($distance, 1); // Minimum 1km

// Get rates from system settings or use defaults
$base_fare = 500;
$rate_per_km = ($ride_type === 'comfort') ? 1500 : 1000;

$fare = ($distance * $rate_per_km) + $base_fare;

echo json_encode([
    'success' => true,
    'distance' => round($distance, 2),
    'base_fare' => $base_fare,
    'rate_per_km' => $rate_per_km,
    'fare' => round($fare)
]);
?>