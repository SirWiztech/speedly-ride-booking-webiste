<?php
// SERVER/API/get_pending_rides.php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Get Pending Rides Request Started ===");
error_log("Session data: " . json_encode($_SESSION));

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'client';

error_log("User ID: " . $user_id . ", Role: " . $user_role);

// If user is a driver, get their driver profile ID
$driver_id = null;
if ($user_role === 'driver') {
    $stmt = $conn->prepare("SELECT id FROM driver_profiles WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $driver = $result->fetch_assoc();
        $driver_id = $driver['id'];
        error_log("Driver profile ID: " . $driver_id);
    }
}

try {
    // Base query for pending rides
    $query = "SELECT 
                r.id,
                r.ride_number,
                r.pickup_address,
                r.pickup_latitude,
                r.pickup_longitude,
                r.destination_address,
                r.destination_latitude,
                r.destination_longitude,
                r.ride_type,
                r.distance_km,
                r.total_fare,
                r.status,
                r.created_at,
                r.driver_id,
                u.full_name as client_name,
                u.phone_number as client_phone,
                u.profile_picture_url as client_photo,
                cp.id as client_profile_id,
                cp.membership_tier,
                cp.total_rides as client_total_rides,
                cp.average_rating as client_rating,
                CASE 
                    WHEN r.driver_id IS NOT NULL AND r.driver_id != '' THEN 'private'
                    ELSE 'public'
                END as ride_visibility
              FROM rides r
              JOIN client_profiles cp ON r.client_id = cp.id
              JOIN users u ON cp.user_id = u.id
              WHERE r.status = 'pending'";
    
    // Add conditions based on user role
    if ($user_role === 'driver' && $driver_id) {
        // Drivers see:
        // 1. All public rides (driver_id IS NULL)
        // 2. Private rides assigned specifically to them
        $query .= " AND (r.driver_id IS NULL OR r.driver_id = ?)";
        
        // Order by: private rides first, then public, then by creation date
        $query .= " ORDER BY 
                    CASE 
                        WHEN r.driver_id = ? THEN 0  -- Private rides for this driver first
                        WHEN r.driver_id IS NULL THEN 1  -- Public rides second
                        ELSE 2  -- Other private rides last (shouldn't happen due to WHERE clause)
                    END,
                    r.created_at DESC";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $driver_id, $driver_id);
    } else if ($user_role === 'admin') {
        // Admins see all pending rides
        $query .= " ORDER BY 
                    CASE 
                        WHEN r.driver_id IS NOT NULL THEN 0  -- Private rides first
                        ELSE 1  -- Public rides second
                    END,
                    r.created_at DESC";
        
        $stmt = $conn->prepare($query);
    } else {
        // Clients see their own pending rides
        $clientQuery = "SELECT id FROM client_profiles WHERE user_id = ?";
        $clientStmt = $conn->prepare($clientQuery);
        $clientStmt->bind_param("s", $user_id);
        $clientStmt->execute();
        $clientResult = $clientStmt->get_result();
        
        if ($clientResult->num_rows === 0) {
            echo json_encode(['success' => true, 'rides' => [], 'count' => 0]);
            exit;
        }
        
        $clientData = $clientResult->fetch_assoc();
        $client_profile_id = $clientData['id'];
        
        $query .= " AND r.client_id = ? ORDER BY r.created_at DESC";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $client_profile_id);
    }
    
    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $rides = [];
    $now = time();
    
    while ($row = $result->fetch_assoc()) {
        // Calculate time elapsed
        $created_at = strtotime($row['created_at']);
        $time_elapsed = $now - $created_at;
        
        // Format time for display
        if ($time_elapsed < 60) {
            $time_display = 'Just now';
        } else if ($time_elapsed < 3600) {
            $minutes = floor($time_elapsed / 60);
            $time_display = $minutes . ' min' . ($minutes > 1 ? 's' : '') . ' ago';
        } else if ($time_elapsed < 86400) {
            $hours = floor($time_elapsed / 3600);
            $time_display = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } else {
            $days = floor($time_elapsed / 86400);
            $time_display = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
        
        // Calculate estimated duration based on distance (assuming average speed 30 km/h)
        $estimated_minutes = round(($row['distance_km'] ?? 5) / 30 * 60);
        
        // Get driver info if assigned
        $driver_info = null;
        if (!empty($row['driver_id'])) {
            $driverQuery = "SELECT 
                                u.full_name as driver_name,
                                u.phone_number as driver_phone,
                                dv.vehicle_model,
                                dv.vehicle_color,
                                dv.plate_number
                            FROM driver_profiles dp
                            JOIN users u ON dp.user_id = u.id
                            LEFT JOIN driver_vehicles dv ON dp.id = dv.driver_id AND dv.is_active = 1
                            WHERE dp.id = ?";
            $driverStmt = $conn->prepare($driverQuery);
            $driverStmt->bind_param("s", $row['driver_id']);
            $driverStmt->execute();
            $driverResult = $driverStmt->get_result();
            $driver_info = $driverResult->fetch_assoc();
        }
        
        $rides[] = [
            'id' => $row['id'],
            'ride_number' => $row['ride_number'],
            'pickup' => [
                'address' => $row['pickup_address'],
                'lat' => (float)$row['pickup_latitude'],
                'lng' => (float)$row['pickup_longitude']
            ],
            'destination' => [
                'address' => $row['destination_address'],
                'lat' => (float)$row['destination_latitude'],
                'lng' => (float)$row['destination_longitude']
            ],
            'client' => [
                'name' => $row['client_name'],
                'phone' => $row['client_phone'],
                'photo' => $row['client_photo'] ?? 'default-avatar.png',
                'membership_tier' => $row['membership_tier'],
                'total_rides' => (int)$row['client_total_rides'],
                'rating' => $row['client_rating'] ? (float)$row['client_rating'] : null
            ],
            'driver' => $driver_info,
            'ride_type' => $row['ride_type'],
            'distance' => (float)$row['distance_km'],
            'fare' => (float)$row['total_fare'],
            'status' => $row['status'],
            'visibility' => $row['ride_visibility'],
            'created_at' => $row['created_at'],
            'time_display' => $time_display,
            'estimated_duration' => $estimated_minutes,
            'estimated_duration_display' => $estimated_minutes . ' min',
            'is_private' => !empty($row['driver_id']),
            'assigned_to_me' => ($user_role === 'driver' && $driver_id && $row['driver_id'] === $driver_id)
        ];
    }
    
    // Get counts by type
    $private_count = count(array_filter($rides, function($r) { return $r['is_private']; }));
    $public_count = count(array_filter($rides, function($r) { return !$r['is_private']; }));
    
    error_log("Found " . count($rides) . " pending rides ($private_count private, $public_count public)");
    
    echo json_encode([
        'success' => true,
        'rides' => $rides,
        'count' => count($rides),
        'counts' => [
            'total' => count($rides),
            'private' => $private_count,
            'public' => $public_count
        ],
        'user_role' => $user_role,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    error_log("Error in get_pending_rides.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch pending rides: ' . $e->getMessage()
    ]);
}

$conn->close();
error_log("=== Get Pending Rides Request Completed ===");
?>   