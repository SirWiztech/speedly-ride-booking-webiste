<?php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Update Driver Status Request Started ===");

// Check if user is logged in and is driver
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

if ($_SESSION['role'] !== 'driver') {
    error_log("User is not a driver. Role: " . $_SESSION['role']);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$user_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['status'])) {
    error_log("Invalid input: " . file_get_contents('php://input'));
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

$new_status = $data['status'];
$valid_statuses = ['online', 'offline', 'on_ride'];

if (!in_array($new_status, $valid_statuses)) {
    error_log("Invalid status: " . $new_status);
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

// Get driver profile ID and current status
$stmt = $conn->prepare("SELECT id, driver_status, verification_status FROM driver_profiles WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();

if (!$driver) {
    error_log("Driver profile not found for user_id: " . $user_id);
    echo json_encode(['success' => false, 'message' => 'Driver profile not found']);
    exit;
}

$driver_id = $driver['id'];
$old_status = $driver['driver_status'];
$verification_status = $driver['verification_status'];

error_log("Driver ID: " . $driver_id . ", Current status: " . $old_status . ", New status: " . $new_status);

// Check if driver is verified (can only go online if verified)
if ($new_status === 'online' && $verification_status !== 'approved') {
    error_log("Driver not verified. Status: " . $verification_status);
    echo json_encode(['success' => false, 'message' => 'Your account is not verified. Please complete KYC first.']);
    exit;
}

// Update driver status
$stmt = $conn->prepare("UPDATE driver_profiles SET driver_status = ?, last_location_update = NOW() WHERE id = ?");
$stmt->bind_param("ss", $new_status, $driver_id);

if ($stmt->execute()) {
    error_log("Status updated successfully");
    
    // Log the status change
    $log_id = bin2hex(random_bytes(16));
    $old_values = json_encode(['driver_status' => $old_status]);
    $new_values = json_encode(['driver_status' => $new_status]);
    
    // Check if admin_activity_logs table exists
    $tableCheck = $conn->query("SHOW TABLES LIKE 'admin_activity_logs'");
    if ($tableCheck->num_rows > 0) {
        $log_stmt = $conn->prepare("INSERT INTO admin_activity_logs (id, admin_id, action, entity_type, entity_id, old_values, new_values) VALUES (?, ?, 'status_change', 'driver', ?, ?, ?)");
        $log_stmt->bind_param("sssss", $log_id, $_SESSION['user_id'], $driver_id, $old_values, $new_values);
        $log_stmt->execute();
    }
    
    // If going online, create a notification
    if ($new_status === 'online') {
        $notif_id = bin2hex(random_bytes(16));
        $notif_stmt = $conn->prepare("INSERT INTO notifications (id, user_id, type, title, message, created_at) VALUES (?, ?, 'system', 'Status Update', 'You are now online and can receive ride requests', NOW())");
        $notif_stmt->bind_param("ss", $notif_id, $user_id);
        $notif_stmt->execute();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'status' => $new_status
    ]);
} else {
    error_log("Failed to update status: " . $conn->error);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to update status: ' . $conn->error
    ]);
}

error_log("=== Update Driver Status Request Completed ===");
$conn->close();
?>  