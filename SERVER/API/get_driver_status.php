<?php
// SERVER/API/get_driver_status.php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Get Driver Status Request Started ===");
error_log("Session data: " . json_encode($_SESSION));

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'client';

error_log("User ID: " . $user_id . ", Role: " . $user_role);

// Get driver status
$stmt = $conn->prepare("SELECT id, driver_status, verification_status, is_available, current_latitude, current_longitude, last_location_update FROM driver_profiles WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $driver = $result->fetch_assoc();
    
    // Get today's ride count for this driver
    $todayRidesQuery = "SELECT COUNT(*) as today_rides FROM rides WHERE driver_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'";
    $todayStmt = $conn->prepare($todayRidesQuery);
    $todayStmt->bind_param("s", $driver['id']);
    $todayStmt->execute();
    $todayResult = $todayStmt->get_result();
    $todayData = $todayResult->fetch_assoc();
    $today_rides = $todayData['today_rides'] ?? 0;
    
    // Get any active ride
    $activeQuery = "SELECT id, status, pickup_address, destination_address FROM rides WHERE driver_id = ? AND status IN ('accepted', 'driver_arrived', 'ongoing') LIMIT 1";
    $activeStmt = $conn->prepare($activeQuery);
    $activeStmt->bind_param("s", $driver['id']);
    $activeStmt->execute();
    $activeResult = $activeStmt->get_result();
    $active_ride = $activeResult->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'status' => $driver['driver_status'],
        'verification' => $driver['verification_status'],
        'is_available' => (bool)$driver['is_available'],
        'location' => [
            'lat' => $driver['current_latitude'],
            'lng' => $driver['current_longitude'],
            'last_update' => $driver['last_location_update']
        ],
        'today_rides' => $today_rides,
        'active_ride' => $active_ride,
        'role' => $user_role
    ]);
} else {
    error_log("Driver profile not found for user_id: " . $user_id);
    
    // Check if user exists but is not a driver
    $userCheck = $conn->prepare("SELECT role FROM users WHERE id = ?");
    $userCheck->bind_param("s", $user_id);
    $userCheck->execute();
    $userResult = $userCheck->get_result();
    
    if ($userResult->num_rows > 0) {
        $user = $userResult->fetch_assoc();
        echo json_encode([
            'success' => false,
            'message' => 'User is not a driver',
            'user_role' => $user['role']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Driver profile not found'
        ]);
    }
}

$conn->close();
error_log("=== Get Driver Status Request Completed ===");
?>  