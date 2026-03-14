<?php
// SERVER/API/get_nearby_drivers.php
header('Content-Type: application/json');
session_start();

require_once 'db-connect.php';

// Enable error logging
error_log("=== Get Nearby Drivers Request Started ===");

$lat = $_GET['lat'] ?? '';
$lng = $_GET['lng'] ?? '';
$ride_type = $_GET['ride_type'] ?? 'economy';

if (empty($lat) || empty($lng)) {
    error_log("Missing coordinates");
    echo json_encode(['success' => false, 'message' => 'Missing coordinates']);
    exit;
}

error_log("Searching for drivers near lat: $lat, lng: $lng, ride_type: $ride_type");

$drivers = [];

// First, get REAL drivers from database with NO LIMIT to show all available
$query = "SELECT 
    dp.id,
    u.full_name as driver_name,
    u.profile_picture_url,
    COALESCE(dp.average_rating, 4.5) as average_rating,
    dp.completed_rides,
    dp.current_latitude,
    dp.current_longitude,
    dp.driver_status,
    dp.last_location_update,
    dv.vehicle_model,
    dv.vehicle_color,
    dv.plate_number,
    dv.vehicle_type,
    (6371 * ACOS(
        COS(RADIANS(?)) * COS(RADIANS(dp.current_latitude)) *
        COS(RADIANS(dp.current_longitude) - RADIANS(?)) +
        SIN(RADIANS(?)) * SIN(RADIANS(dp.current_latitude))
    )) AS distance
    FROM driver_profiles dp
    JOIN users u ON dp.user_id = u.id
    LEFT JOIN driver_vehicles dv ON dp.id = dv.driver_id AND dv.is_active = 1
    WHERE dp.verification_status = 'approved'
    AND dp.driver_status IN ('online', 'on_ride')
    AND dp.current_latitude IS NOT NULL
    AND dp.current_longitude IS NOT NULL
    ORDER BY 
        CASE WHEN dp.driver_status = 'online' THEN 0 ELSE 1 END,
        distance ASC";

$stmt = $conn->prepare($query);
$stmt->bind_param("ddd", $lat, $lng, $lat);
$stmt->execute();
$result = $stmt->get_result();

$real_drivers_count = 0;
while ($row = $result->fetch_assoc()) {
    // Calculate distance - if null, set a reasonable default
    $distance = $row['distance'] ? round($row['distance'], 1) : rand(1, 20);
    
    // Format vehicle string
    $vehicle = 'Standard Vehicle';
    if (!empty($row['vehicle_color']) && !empty($row['vehicle_model'])) {
        $vehicle = $row['vehicle_color'] . ' ' . $row['vehicle_model'];
    } elseif (!empty($row['vehicle_model'])) {
        $vehicle = $row['vehicle_model'];
    } else {
        // Assign default vehicles based on driver name or ID
        $defaultVehicles = [
            'Toyota Camry',
            'Honda Accord',
            'Hyundai Elantra',
            'Kia Optima',
            'Nissan Altima',
            'Toyota Corolla',
            'Honda Civic',
            'Mercedes Benz C-Class',
            'BMW 3 Series',
            'Lexus ES'
        ];
        $vehicle = $defaultVehicles[array_rand($defaultVehicles)];
    }
    
    // Format plate number
    $plate = $row['plate_number'] ?? 'LAG' . rand(100, 999) . 'AB';
    
    // Determine vehicle type
    $type = $row['vehicle_type'] ?? 'economy';
    if (stripos($vehicle, 'Mercedes') !== false || 
        stripos($vehicle, 'BMW') !== false || 
        stripos($vehicle, 'Lexus') !== false ||
        stripos($vehicle, 'Range') !== false) {
        $type = 'comfort';
    }
    
    // Only include drivers that match the requested ride type or show all if economy
    if ($ride_type === 'comfort' && $type !== 'comfort') {
        continue; // Skip economy drivers when comfort is requested
    }
    
    $drivers[] = [
        'id' => $row['id'],
        'name' => $row['driver_name'],
        'photo' => $row['profile_picture_url'] ?? 'default-avatar.png',
        'rating' => round($row['average_rating'] ?? 4.5, 1),
        'rides' => $row['completed_rides'] ?? 0,
        'distance' => $distance,
        'vehicle' => $vehicle,
        'plate' => $plate,
        'type' => $type,
        'status' => $row['driver_status'] ?? 'offline',
        'is_real' => true
    ];
    
    $real_drivers_count++;
}

error_log("Found $real_drivers_count real drivers");

// Add mock drivers for better selection if we have fewer than 15 drivers
if (count($drivers) < 15) {
    $mock_needed = 15 - count($drivers);
    error_log("Adding $mock_needed mock drivers");
    
    // Luxury/Comfort drivers
    $luxuryDrivers = [
        ['name' => 'David Okonkwo', 'vehicle' => 'Black Mercedes Benz E-Class', 'plate' => 'LAG001LK', 'type' => 'comfort', 'rating' => 4.9, 'rides' => 3421],
        ['name' => 'Chioma Nnamdi', 'vehicle' => 'White Lexus RX350', 'plate' => 'LAG002LX', 'type' => 'comfort', 'rating' => 5.0, 'rides' => 2189],
        ['name' => 'Emeka Okafor', 'vehicle' => 'Silver BMW 5 Series', 'plate' => 'LAG003BM', 'type' => 'comfort', 'rating' => 4.8, 'rides' => 1567],
        ['name' => 'Amara Igwe', 'vehicle' => 'Blue Range Rover Sport', 'plate' => 'LAG004RR', 'type' => 'comfort', 'rating' => 4.9, 'rides' => 892],
        ['name' => 'Femi Adeleke', 'vehicle' => 'Black Audi Q7', 'plate' => 'LAG005AQ', 'type' => 'comfort', 'rating' => 4.7, 'rides' => 2341],
    ];
    
    // Economy drivers
    $economyDrivers = [
        ['name' => 'Musa Danjuma', 'vehicle' => 'Silver Toyota Corolla', 'plate' => 'LAG101EC', 'type' => 'economy', 'rating' => 4.7, 'rides' => 2345],
        ['name' => 'Funke Adebayo', 'vehicle' => 'Red Honda Civic', 'plate' => 'LAG102EC', 'type' => 'economy', 'rating' => 4.8, 'rides' => 1789],
        ['name' => 'Chidi Obi', 'vehicle' => 'White Hyundai Elantra', 'plate' => 'LAG103EC', 'type' => 'economy', 'rating' => 4.6, 'rides' => 3456],
        ['name' => 'Ngozi Eze', 'vehicle' => 'Blue Nissan Sentra', 'plate' => 'LAG104EC', 'type' => 'economy', 'rating' => 4.9, 'rides' => 2891],
        ['name' => 'Tunde Balogun', 'vehicle' => 'Grey Kia Forte', 'plate' => 'LAG105EC', 'type' => 'economy', 'rating' => 4.7, 'rides' => 1562],
        ['name' => 'Ifeanyi Okoro', 'vehicle' => 'Black Toyota Camry', 'plate' => 'LAG106EC', 'type' => 'economy', 'rating' => 4.8, 'rides' => 2783],
        ['name' => 'Blessing John', 'vehicle' => 'Silver Honda Accord', 'plate' => 'LAG107EC', 'type' => 'economy', 'rating' => 4.9, 'rides' => 1987],
        ['name' => 'Kenneth Nwachukwu', 'vehicle' => 'White Kia Optima', 'plate' => 'LAG108EC', 'type' => 'economy', 'rating' => 4.6, 'rides' => 1234],
    ];
    
    // Add mock drivers based on ride type
    for ($i = 0; $i < $mock_needed; $i++) {
        if ($ride_type === 'comfort') {
            // Prioritize luxury drivers for comfort requests
            $mock = $luxuryDrivers[array_rand($luxuryDrivers)];
        } else {
            // Mix of economy and luxury for economy requests
            if ($i % 4 == 0 && !empty($luxuryDrivers)) { // Every 4th driver is luxury
                $mock = $luxuryDrivers[array_rand($luxuryDrivers)];
            } else {
                $mock = $economyDrivers[array_rand($economyDrivers)];
            }
        }
        
        $drivers[] = [
            'id' => 'mock_' . uniqid() . '_' . $i,
            'name' => $mock['name'],
            'photo' => 'default-avatar.png',
            'rating' => $mock['rating'],
            'rides' => $mock['rides'],
            'distance' => round(rand(1, 15) + (rand(0, 9) / 10), 1),
            'vehicle' => $mock['vehicle'],
            'plate' => $mock['plate'],
            'type' => $mock['type'],
            'status' => 'online',
            'is_real' => false
        ];
    }
}

// Remove duplicates by ID (in case of ID conflicts)
$uniqueDrivers = [];
$seen = [];
foreach ($drivers as $driver) {
    if (!in_array($driver['id'], $seen)) {
        $seen[] = $driver['id'];
        $uniqueDrivers[] = $driver;
    }
}

// Sort drivers: online first, then by distance
usort($uniqueDrivers, function($a, $b) {
    if ($a['status'] === 'online' && $b['status'] !== 'online') return -1;
    if ($a['status'] !== 'online' && $b['status'] === 'online') return 1;
    return $a['distance'] - $b['distance'];
});

// Limit to 25 drivers maximum for performance
$uniqueDrivers = array_slice($uniqueDrivers, 0, 25);

$online_count = count(array_filter($uniqueDrivers, function($d) { 
    return $d['status'] === 'online' || $d['status'] === 'on_ride'; 
}));

error_log("Returning " . count($uniqueDrivers) . " drivers ($online_count online, " . (count($uniqueDrivers) - $online_count) . " offline)");

echo json_encode([
    'success' => true,
    'drivers' => $uniqueDrivers,
    'count' => count($uniqueDrivers),
    'real_drivers' => $real_drivers_count,
    'online_count' => $online_count,
    'filters' => [
        'ride_type' => $ride_type,
        'latitude' => $lat,
        'longitude' => $lng
    ],
    'message' => 'Found ' . count($uniqueDrivers) . ' drivers near you'
]);

$conn->close();
error_log("=== Get Nearby Drivers Request Completed ===");
?>  