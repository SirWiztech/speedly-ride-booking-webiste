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
            u.email as driver_email,
            dv.vehicle_model,
            dv.vehicle_color,
            dv.plate_number,
            dv.vehicle_type,
            dv.passenger_capacity,
            COALESCE(dp.average_rating, 0) as driver_rating,
            dp.completed_rides as driver_total_rides,
            dr.rating as user_rating,
            dr.review as user_review,
            DATE_FORMAT(r.created_at, '%M %d, %Y') as formatted_date,
            DATE_FORMAT(r.created_at, '%h:%i %p') as formatted_time,
            DATE_FORMAT(r.created_at, '%Y-%m-%d') as date_only,
            DATE_FORMAT(r.created_at, '%H:%i:%s') as time_only,
            DATE_FORMAT(r.completed_at, '%M %d, %Y %h:%i %p') as completed_at_formatted,
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
            r.client_rating,
            r.client_review,
            r.driver_rating as driver_given_rating,
            r.driver_review as driver_given_review,
            cp.membership_tier as client_tier
            FROM rides r
            LEFT JOIN driver_profiles dp ON r.driver_id = dp.id
            LEFT JOIN users u ON dp.user_id = u.id
            LEFT JOIN driver_vehicles dv ON dp.id = dv.driver_id AND dv.is_active = 1
            LEFT JOIN driver_ratings dr ON r.id = dr.ride_id AND dr.user_id = ?
            JOIN client_profiles cp ON r.client_id = cp.id
            WHERE r.id = ? AND cp.user_id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $user_id, $ride_id, $user_id);
        
    } else if ($user_role == 'driver') {
        // Driver view - get ride with client details
        $query = "SELECT 
            r.*,
            u.full_name as client_name,
            u.phone_number as client_phone,
            u.email as client_email,
            u.profile_picture_url as client_photo,
            cp.membership_tier as client_tier,
            cp.total_rides as client_total_rides,
            COALESCE(cp.average_rating, 0) as client_rating,
            cp.emergency_contact_name,
            cp.emergency_contact_phone,
            cr.rating as user_rating,
            cr.review as user_review,
            DATE_FORMAT(r.created_at, '%M %d, %Y') as formatted_date,
            DATE_FORMAT(r.created_at, '%h:%i %p') as formatted_time,
            DATE_FORMAT(r.created_at, '%Y-%m-%d') as date_only,
            DATE_FORMAT(r.created_at, '%H:%i:%s') as time_only,
            DATE_FORMAT(r.completed_at, '%M %d, %Y %h:%i %p') as completed_at_formatted,
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
            r.client_rating,
            r.client_review,
            r.driver_rating,
            r.driver_review
            FROM rides r
            JOIN client_profiles cp ON r.client_id = cp.id
            JOIN users u ON cp.user_id = u.id
            LEFT JOIN client_ratings cr ON r.id = cr.ride_id AND cr.user_id = ?
            WHERE r.id = ? AND r.driver_id = (SELECT id FROM driver_profiles WHERE user_id = ?)";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sss", $user_id, $ride_id, $user_id);
        
    } else if ($user_role == 'admin') {
        // Admin view - see all details
        $query = "SELECT 
            r.*,
            u_driver.full_name as driver_name,
            u_driver.phone_number as driver_phone,
            u_driver.email as driver_email,
            u_client.full_name as client_name,
            u_client.phone_number as client_phone,
            u_client.email as client_email,
            dv.vehicle_model,
            dv.vehicle_color,
            dv.plate_number,
            dv.vehicle_type,
            COALESCE(dp.average_rating, 0) as driver_rating,
            dp.completed_rides as driver_total_rides,
            cp.membership_tier as client_tier,
            cp.total_rides as client_total_rides,
            COALESCE(cp.average_rating, 0) as client_rating,
            DATE_FORMAT(r.created_at, '%M %d, %Y') as formatted_date,
            DATE_FORMAT(r.created_at, '%h:%i %p') as formatted_time,
            DATE_FORMAT(r.completed_at, '%M %d, %Y %h:%i %p') as completed_at_formatted,
            ROUND(r.distance_km, 2) as distance_km,
            r.total_fare,
            r.platform_commission,
            r.driver_payout,
            r.ride_type,
            r.status,
            r.payment_status
            FROM rides r
            LEFT JOIN driver_profiles dp ON r.driver_id = dp.id
            LEFT JOIN users u_driver ON dp.user_id = u_driver.id
            LEFT JOIN driver_vehicles dv ON dp.id = dv.driver_id AND dv.is_active = 1
            JOIN client_profiles cp ON r.client_id = cp.id
            JOIN users u_client ON cp.user_id = u_client.id
            WHERE r.id = ?";
        
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $ride_id);
    }

    if (!$stmt) {
        throw new Exception("Failed to prepare query: " . $conn->error);
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

    // Format vehicle info for display
    if ($user_role == 'client' || $user_role == 'admin') {
        if (isset($ride['vehicle_color']) && isset($ride['vehicle_model'])) {
            $ride['vehicle_display'] = trim($ride['vehicle_color'] . ' ' . $ride['vehicle_model']);
            if (!empty($ride['plate_number'])) {
                $ride['vehicle_display'] .= ' • ' . $ride['plate_number'];
            }
        } else {
            $ride['vehicle_display'] = 'Vehicle not specified';
        }
    }

    // Ensure numeric values are properly typed
    $ride['distance_km'] = $ride['distance_km'] ? floatval($ride['distance_km']) : null;
    $ride['total_fare'] = $ride['total_fare'] ? floatval($ride['total_fare']) : 0;
    $ride['platform_commission'] = $ride['platform_commission'] ? floatval($ride['platform_commission']) : 0;
    $ride['driver_payout'] = $ride['driver_payout'] ? floatval($ride['driver_payout']) : 0;

    // Calculate trip duration if completed
    if ($ride['status'] === 'completed' && $ride['created_at'] && $ride['completed_at']) {
        $start = new DateTime($ride['created_at']);
        $end = new DateTime($ride['completed_at']);
        $interval = $start->diff($end);
        
        $minutes = ($interval->h * 60) + $interval->i;
        if ($minutes > 0) {
            $ride['trip_duration'] = $interval->format('%h hours %i minutes');
            $ride['trip_duration_minutes'] = $minutes;
        } else {
            $ride['trip_duration'] = 'Less than a minute';
            $ride['trip_duration_minutes'] = 0;
        }
    } else {
        $ride['trip_duration'] = null;
        $ride['trip_duration_minutes'] = null;
    }

    // Determine if user can rate (for completed rides without a rating)
    $can_rate = false;
    if ($user_role == 'client' && $ride['status'] === 'completed') {
        // Check if client has already rated this driver
        $checkQuery = "SELECT id FROM driver_ratings WHERE ride_id = ? AND user_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("ss", $ride_id, $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0 && !$ride['user_rating']) {
            $can_rate = true;
        }
    } else if ($user_role == 'driver' && $ride['status'] === 'completed') {
        // Check if driver has already rated this client
        $checkQuery = "SELECT id FROM client_ratings WHERE ride_id = ? AND user_id = ?";
        $checkStmt = $conn->prepare($checkQuery);
        $checkStmt->bind_param("ss", $ride_id, $user_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows === 0 && !$ride['user_rating']) {
            $can_rate = true;
        }
    }

    $ride['can_rate'] = $can_rate;

    // Add rating info based on role
    if ($user_role == 'client') {
        $ride['my_rating'] = $ride['user_rating'];
        $ride['my_review'] = $ride['user_review'];
        $ride['other_party_rating'] = $ride['driver_given_rating'] ?? null;
        $ride['other_party_review'] = $ride['driver_given_review'] ?? null;
        $ride['other_party_name'] = $ride['driver_name'] ?? 'Driver';
    } else if ($user_role == 'driver') {
        $ride['my_rating'] = $ride['driver_rating'];
        $ride['my_review'] = $ride['driver_review'];
        $ride['other_party_rating'] = $ride['client_rating'];
        $ride['other_party_review'] = $ride['client_review'];
        $ride['other_party_name'] = $ride['client_name'] ?? 'Client';
    }

    // Add map URLs for navigation
    if ($ride['pickup_latitude'] && $ride['pickup_longitude']) {
        $ride['pickup_map_url'] = "https://www.google.com/maps/search/?api=1&query=" . $ride['pickup_latitude'] . "," . $ride['pickup_longitude'];
        $ride['pickup_whatsapp_location'] = "https://maps.google.com/?q=" . $ride['pickup_latitude'] . "," . $ride['pickup_longitude'];
    }
    
    if ($ride['destination_latitude'] && $ride['destination_longitude']) {
        $ride['destination_map_url'] = "https://www.google.com/maps/search/?api=1&query=" . $ride['destination_latitude'] . "," . $ride['destination_longitude'];
        $ride['destination_whatsapp_location'] = "https://maps.google.com/?q=" . $ride['destination_latitude'] . "," . $ride['destination_longitude'];
    }

    // Add directions URL for the whole trip
    if ($ride['pickup_latitude'] && $ride['pickup_longitude'] && $ride['destination_latitude'] && $ride['destination_longitude']) {
        $ride['directions_url'] = "https://www.google.com/maps/dir/?api=1&origin=" . $ride['pickup_latitude'] . "," . $ride['pickup_longitude'] . "&destination=" . $ride['destination_latitude'] . "," . $ride['destination_longitude'];
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

    // Get payment status display
    $payment_display = [
        'pending' => 'Pending',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded'
    ];
    $ride['payment_status_display'] = $payment_display[$ride['payment_status']] ?? ucfirst($ride['payment_status']);

    error_log("Ride details prepared. Can rate: " . ($can_rate ? 'yes' : 'no'));

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