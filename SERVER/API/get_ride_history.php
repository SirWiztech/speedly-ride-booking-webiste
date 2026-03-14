<?php
// SERVER/API/get_ride_history.php
header('Content-Type: application/json');
session_start();

require_once 'db-connect.php';

// Enable error logging
error_log("=== Get Ride History Request Started ===");
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));

if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'client';
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

error_log("User ID: " . $user_id . ", Role: " . $user_role);
error_log("Filters - Limit: $limit, Offset: $offset, Status: $status_filter");

try {
    if ($user_role == 'client') {
        // Get client profile id
        $clientQuery = "SELECT id FROM client_profiles WHERE user_id = ?";
        $clientStmt = $conn->prepare($clientQuery);
        $clientStmt->bind_param("s", $user_id);
        $clientStmt->execute();
        $clientResult = $clientStmt->get_result();
        
        if ($clientResult->num_rows === 0) {
            error_log("Client profile not found for user: " . $user_id);
            echo json_encode(['success' => true, 'rides' => [], 'count' => 0, 'total_count' => 0]);
            exit;
        }
        
        $clientData = $clientResult->fetch_assoc();
        $client_profile_id = $clientData['id'];
        
        // Build query for client ride history
        $query = "SELECT 
            r.id,
            r.ride_number,
            r.pickup_address,
            r.destination_address,
            r.distance_km,
            r.total_fare,
            r.status,
            r.payment_status,
            r.ride_type,
            r.created_at,
            r.completed_at,
            r.client_rating,
            r.client_review,
            r.driver_rating,
            r.driver_review,
            u.full_name as driver_name,
            u.profile_picture_url as driver_photo,
            dv.vehicle_model,
            dv.vehicle_color,
            dv.plate_number,
            dr.rating as user_rating,
            dr.review as user_review,
            DATE_FORMAT(r.created_at, '%Y-%m-%d') as date,
            DATE_FORMAT(r.created_at, '%h:%i %p') as time,
            DATE_FORMAT(r.created_at, '%M %d, %Y') as formatted_date
            FROM rides r
            LEFT JOIN driver_profiles dp ON r.driver_id = dp.id
            LEFT JOIN users u ON dp.user_id = u.id
            LEFT JOIN driver_vehicles dv ON dp.id = dv.driver_id AND dv.is_active = 1
            LEFT JOIN driver_ratings dr ON r.id = dr.ride_id AND dr.user_id = ?
            WHERE r.client_id = ?";
        
        $params = [$user_id, $client_profile_id];
        $types = "ss";
        
    } else if ($user_role == 'driver') {
        // Get driver profile id
        $driverQuery = "SELECT id FROM driver_profiles WHERE user_id = ?";
        $driverStmt = $conn->prepare($driverQuery);
        $driverStmt->bind_param("s", $user_id);
        $driverStmt->execute();
        $driverResult = $driverStmt->get_result();
        
        if ($driverResult->num_rows === 0) {
            error_log("Driver profile not found for user: " . $user_id);
            echo json_encode(['success' => true, 'rides' => [], 'count' => 0, 'total_count' => 0]);
            exit;
        }
        
        $driverData = $driverResult->fetch_assoc();
        $driver_profile_id = $driverData['id'];
        
        // Build query for driver ride history
        $query = "SELECT 
            r.id,
            r.ride_number,
            r.pickup_address,
            r.destination_address,
            r.distance_km,
            r.total_fare,
            r.platform_commission,
            r.driver_payout,
            r.status,
            r.payment_status,
            r.ride_type,
            r.created_at,
            r.completed_at,
            r.client_rating,
            r.client_review,
            r.driver_rating,
            r.driver_review,
            u.full_name as client_name,
            u.profile_picture_url as client_photo,
            cp.membership_tier as client_tier,
            cr.rating as user_rating,
            cr.review as user_review,
            DATE_FORMAT(r.created_at, '%Y-%m-%d') as date,
            DATE_FORMAT(r.created_at, '%h:%i %p') as time,
            DATE_FORMAT(r.created_at, '%M %d, %Y') as formatted_date
            FROM rides r
            JOIN client_profiles cp ON r.client_id = cp.id
            JOIN users u ON cp.user_id = u.id
            LEFT JOIN client_ratings cr ON r.id = cr.ride_id AND cr.user_id = ?
            WHERE r.driver_id = ?";
        
        $params = [$user_id, $driver_profile_id];
        $types = "ss";
        
    } else if ($user_role == 'admin') {
        // Admin can see all rides
        $query = "SELECT 
            r.id,
            r.ride_number,
            r.pickup_address,
            r.destination_address,
            r.distance_km,
            r.total_fare,
            r.platform_commission,
            r.driver_payout,
            r.status,
            r.payment_status,
            r.ride_type,
            r.created_at,
            r.completed_at,
            u_client.full_name as client_name,
            u_driver.full_name as driver_name,
            DATE_FORMAT(r.created_at, '%Y-%m-%d') as date,
            DATE_FORMAT(r.created_at, '%h:%i %p') as time,
            DATE_FORMAT(r.created_at, '%M %d, %Y') as formatted_date
            FROM rides r
            JOIN client_profiles cp ON r.client_id = cp.id
            JOIN users u_client ON cp.user_id = u_client.id
            LEFT JOIN driver_profiles dp ON r.driver_id = dp.id
            LEFT JOIN users u_driver ON dp.user_id = u_driver.id";
        
        $params = [];
        $types = "";
    }

    // Add status filter
    if ($status_filter !== 'all') {
        $query .= " AND r.status = ?";
        $params[] = $status_filter;
        $types .= "s";
    }

    // Add date filters
    if ($date_from) {
        $query .= " AND DATE(r.created_at) >= ?";
        $params[] = $date_from;
        $types .= "s";
    }
    
    if ($date_to) {
        $query .= " AND DATE(r.created_at) <= ?";
        $params[] = $date_to;
        $types .= "s";
    }

    // Get total count for pagination
    $countQuery = str_replace(
        "SELECT 
            r.id,
            r.ride_number,
            r.pickup_address,
            r.destination_address,
            r.distance_km,
            r.total_fare,
            r.platform_commission,
            r.driver_payout,
            r.status,
            r.payment_status,
            r.ride_type,
            r.created_at,
            r.completed_at,
            u_client.full_name as client_name,
            u_driver.full_name as driver_name,
            DATE_FORMAT(r.created_at, '%Y-%m-%d') as date,
            DATE_FORMAT(r.created_at, '%h:%i %p') as time,
            DATE_FORMAT(r.created_at, '%M %d, %Y') as formatted_date",
        "SELECT COUNT(*) as total",
        $query
    );
    
    $countStmt = $conn->prepare($countQuery);
    if (!empty($params)) {
        $countStmt->bind_param($types, ...$params);
    }
    $countStmt->execute();
    $countResult = $countStmt->get_result();
    $total_count = $countResult->fetch_assoc()['total'];

    // Add sorting and pagination
    $query .= " ORDER BY r.created_at DESC LIMIT ? OFFSET ?";
    $params[] = $limit;
    $params[] = $offset;
    $types .= "ii";

    // Prepare and execute main query
    $stmt = $conn->prepare($query);
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();

    $rides = [];
    while ($row = $result->fetch_assoc()) {
        // Format vehicle info for display
        if (isset($row['vehicle_color']) && isset($row['vehicle_model'])) {
            $row['vehicle_display'] = trim($row['vehicle_color'] . ' ' . $row['vehicle_model']);
            if (!empty($row['plate_number'])) {
                $row['vehicle_display'] .= ' • ' . $row['plate_number'];
            }
        }

        // Ensure numeric values
        $row['distance_km'] = $row['distance_km'] ? floatval($row['distance_km']) : null;
        $row['total_fare'] = $row['total_fare'] ? floatval($row['total_fare']) : null;
        
        // Add status badge info
        $status_colors = [
            'pending' => '#FF9800',
            'accepted' => '#2196F3',
            'driver_assigned' => '#2196F3',
            'driver_arrived' => '#9C27B0',
            'ongoing' => '#FF5722',
            'completed' => '#4CAF50',
            'cancelled_by_client' => '#F44336',
            'cancelled_by_driver' => '#F44336',
            'cancelled_by_admin' => '#F44336'
        ];
        
        $row['status_color'] = $status_colors[$row['status']] ?? '#9E9E9E';
        $row['status_display'] = str_replace('_', ' ', ucwords($row['status'] ?? 'unknown'));
        
        // Determine if user can rate this ride
        $can_rate = false;
        if ($user_role == 'client' && $row['status'] === 'completed') {
            $checkQuery = "SELECT id FROM driver_ratings WHERE ride_id = ? AND user_id = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("ss", $row['id'], $user_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $can_rate = ($checkResult->num_rows === 0);
        } else if ($user_role == 'driver' && $row['status'] === 'completed') {
            $checkQuery = "SELECT id FROM client_ratings WHERE ride_id = ? AND user_id = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("ss", $row['id'], $user_id);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $can_rate = ($checkResult->num_rows === 0);
        }
        
        $row['can_rate'] = $can_rate;
        
        // Add rating info
        if ($user_role == 'client') {
            $row['my_rating'] = $row['user_rating'] ?? $row['client_rating'];
            $row['my_review'] = $row['user_review'] ?? $row['client_review'];
            $row['driver_rating_given'] = $row['user_rating'] !== null;
        } else if ($user_role == 'driver') {
            $row['my_rating'] = $row['driver_rating'];
            $row['my_review'] = $row['driver_review'];
            $row['client_rating_given'] = $row['user_rating'] !== null;
        }
        
        $rides[] = $row;
    }

    // Calculate statistics
    $stats = [
        'total_spent' => 0,
        'total_earned' => 0,
        'completed_count' => 0,
        'cancelled_count' => 0
    ];
    
    foreach ($rides as $ride) {
        if ($user_role == 'client') {
            if ($ride['status'] === 'completed') {
                $stats['total_spent'] += $ride['total_fare'] ?? 0;
                $stats['completed_count']++;
            } else if (strpos($ride['status'], 'cancelled') !== false) {
                $stats['cancelled_count']++;
            }
        } else if ($user_role == 'driver') {
            if ($ride['status'] === 'completed') {
                $stats['total_earned'] += $ride['driver_payout'] ?? 0;
                $stats['completed_count']++;
            } else if (strpos($ride['status'], 'cancelled') !== false) {
                $stats['cancelled_count']++;
            }
        }
    }

    error_log("Found " . count($rides) . " rides for user");

    echo json_encode([
        'success' => true,
        'rides' => $rides,
        'count' => count($rides),
        'total_count' => (int)$total_count,
        'limit' => $limit,
        'offset' => $offset,
        'has_more' => ($offset + count($rides)) < $total_count,
        'statistics' => $stats,
        'user_role' => $user_role
    ]);

} catch (Exception $e) {
    error_log("Error in get_ride_history.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch ride history: ' . $e->getMessage()
    ]);
}

$conn->close();
error_log("=== Get Ride History Request Completed ===");
?>    