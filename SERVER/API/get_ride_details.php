<?php
// SERVER/API/get_ride_details.php
header('Content-Type: application/json');
session_start();

require_once 'db-connect.php';

// Enable error logging
error_log("=== Get Ride Details Request Started ===");
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));

if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$ride_id = $_GET['ride_id'] ?? '';

if (empty($ride_id)) {
    error_log("Ride ID not provided");
    echo json_encode(['success' => false, 'message' => 'Ride ID required']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'client';

error_log("Fetching ride details for ID: " . $ride_id . ", User role: " . $user_role);

try {
    if ($user_role == 'client') {
        // Client view - get ride with driver details
        $query = "SELECT 
            r.*,
            u.full_name as driver_name,
            u.phone_number as driver_phone,
            u.profile_picture_url as driver_photo,
            dv.vehicle_model,
            dv.vehicle_color,
            dv.plate_number,
            dv.vehicle_type,
            dp.average_rating as driver_rating,
            dp.completed_rides as driver_total_rides,
            dr.rating as user_rating,
            dr.review as user_review,
            DATE_FORMAT(r.created_at, '%M %d, %Y') as formatted_date,
            DATE_FORMAT(r.created_at, '%h:%i %p') as formatted_time,
            ROUND(r.distance_km, 2) as distance_km,
            r.total_fare,
            r.platform_commission,
            r.driver_payout,
            r.ride_type,
            r.status,
            r.payment_status,
            r.pickup_address,
            r.destination_address,
            r.pickup_latitude,
            r.pickup_longitude,
            r.destination_latitude,
            r.destination_longitude,
            r.created_at
            FROM rides r
            LEFT JOIN driver_profiles dp ON r.driver_id = dp.id
            LEFT JOIN users u ON dp.user_id = u.id
            LEFT JOIN driver_vehicles dv ON dp.id = dv.driver_id AND dv.is_active = 1
            LEFT JOIN driver_ratings dr ON r.id = dr.ride_id AND dr.user_id = ?
            WHERE r.id = ? AND r.client_id = (SELECT id FROM client_profiles WHERE user_id = ?)";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare query: " . $conn->error);
        }
        $stmt->bind_param("sss", $user_id, $ride_id, $user_id);
        
    } else if ($user_role == 'driver') {
        // Driver view - get ride with client details
        $query = "SELECT 
            r.*,
            u.full_name as client_name,
            u.phone_number as client_phone,
            u.profile_picture_url as client_photo,
            cp.membership_tier as client_tier,
            cp.total_rides as client_total_rides,
            cp.average_rating as client_rating,
            cr.rating as user_rating,
            cr.review as user_review,
            DATE_FORMAT(r.created_at, '%M %d, %Y') as formatted_date,
            DATE_FORMAT(r.created_at, '%h:%i %p') as formatted_time,
            ROUND(r.distance_km, 2) as distance_km,
            r.total_fare,
            r.platform_commission,
            r.driver_payout,
            r.ride_type,
            r.status,
            r.payment_status,
            r.pickup_address,
            r.destination_address,
            r.pickup_latitude,
            r.pickup_longitude,
            r.destination_latitude,
            r.destination_longitude,
            r.created_at
            FROM rides r
            JOIN client_profiles cp ON r.client_id = cp.id
            JOIN users u ON cp.user_id = u.id
            LEFT JOIN client_ratings cr ON r.id = cr.ride_id AND cr.user_id = ?
            WHERE r.id = ? AND r.driver_id = (SELECT id FROM driver_profiles WHERE user_id = ?)";
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Failed to prepare query: " . $conn->error);
        }
        $stmt->bind_param("sss", $user_id, $ride_id, $user_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        error_log("Ride not found for ID: " . $ride_id);
        echo json_encode(['success' => false, 'message' => 'Ride not found']);
        exit;
    }

    $ride = $result->fetch_assoc();
    error_log("Ride found. Status: " . ($ride['status'] ?? 'unknown'));

    // Format vehicle info
    if (isset($ride['vehicle_color']) && isset($ride['vehicle_model'])) {
        $ride['vehicle_display'] = trim($ride['vehicle_color'] . ' ' . $ride['vehicle_model']);
        if (!empty($ride['plate_number'])) {
            $ride['vehicle_display'] .= ' • ' . $ride['plate_number'];
        }
    } else {
        $ride['vehicle_display'] = 'Vehicle not specified';
    }

    // Ensure numeric values are properly typed
    $ride['distance_km'] = $ride['distance_km'] ? floatval($ride['distance_km']) : 0;
    $ride['total_fare'] = $ride['total_fare'] ? floatval($ride['total_fare']) : 0;
    $ride['platform_commission'] = $ride['platform_commission'] ? floatval($ride['platform_commission']) : 0;
    $ride['driver_payout'] = $ride['driver_payout'] ? floatval($ride['driver_payout']) : 0;

    // Format date if not already formatted
    if (empty($ride['formatted_date']) && isset($ride['created_at'])) {
        $ride['formatted_date'] = date('M d, Y', strtotime($ride['created_at']));
    }
    if (empty($ride['formatted_time']) && isset($ride['created_at'])) {
        $ride['formatted_time'] = date('h:i A', strtotime($ride['created_at']));
    }

    // Add status display text
    $status_display = [
        'pending' => 'Pending',
        'accepted' => 'Accepted',
        'driver_assigned' => 'Driver Assigned',
        'driver_arrived' => 'Driver Arrived',
        'ongoing' => 'Ongoing',
        'completed' => 'Completed',
        'cancelled_by_client' => 'Cancelled by You',
        'cancelled_by_driver' => 'Cancelled by Driver',
        'cancelled_by_admin' => 'Cancelled'
    ];
    
    $ride['status_display'] = $status_display[$ride['status']] ?? ucfirst(str_replace('_', ' ', $ride['status']));

    // Determine if user can rate (for completed rides without a rating)
    $can_rate = false;
    if ($user_role == 'client' && $ride['status'] === 'completed') {
        // Check if client has already rated this driver
        $checkQuery = "SELECT id FROM driver_ratings WHERE ride_id = ? AND user_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("ss", $ride_id, $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $can_rate = ($checkResult->num_rows === 0);
    }
    
    $ride['can_rate'] = $can_rate;

    echo json_encode([
        'success' => true,
        'ride' => $ride,
        'user_role' => $user_role
    ]);

} catch (Exception $e) {
    error_log("Error in get_ride_details.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load ride details: ' . $e->getMessage()
    ]);
}

$conn->close();
error_log("=== Get Ride Details Request Completed ===");
?>