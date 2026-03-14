<?php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Toggle Driver Status Request Started ===");
error_log("Session data: " . json_encode($_SESSION));

// Check if user is logged in and is a driver
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    error_log("User is not a driver. Role: " . ($_SESSION['role'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Only drivers can change status']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
    error_log("Using POST data: " . json_encode($input));
}

if (!isset($input['status'])) {
    error_log("Status not provided");
    echo json_encode(['success' => false, 'message' => 'Status is required']);
    exit;
}

$new_status = $input['status'];
$user_id = $_SESSION['user_id'];

// Validate status
if (!in_array($new_status, ['online', 'offline'])) {
    error_log("Invalid status: " . $new_status);
    echo json_encode(['success' => false, 'message' => 'Invalid status value']);
    exit;
}

error_log("User ID: " . $user_id . ", New Status: " . $new_status);

// Get driver profile
$stmt = $conn->prepare("SELECT id, driver_status, verification_status FROM driver_profiles WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error_log("Driver profile not found for user_id: " . $user_id);
    echo json_encode(['success' => false, 'message' => 'Driver profile not found']);
    exit;
}

$driver = $result->fetch_assoc();
$driver_id = $driver['id'];
$current_status = $driver['driver_status'];
$verification_status = $driver['verification_status'];

error_log("Driver ID: " . $driver_id . ", Current Status: " . $current_status . ", Verification: " . $verification_status);

// Check if driver is verified
if ($verification_status !== 'approved') {
    error_log("Driver not verified. Status: " . $verification_status);
    echo json_encode(['success' => false, 'message' => 'Your account is not verified. Please complete KYC first.']);
    exit;
}

// Don't update if status is the same
if ($current_status === $new_status) {
    error_log("Status already set to: " . $new_status);
    echo json_encode(['success' => true, 'message' => 'Status already ' . $new_status, 'status' => $new_status]);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Update driver status
    $updateStmt = $conn->prepare("UPDATE driver_profiles SET driver_status = ?, last_location_update = NOW() WHERE id = ?");
    $updateStmt->bind_param("ss", $new_status, $driver_id);
    $updateStmt->execute();
    
    if ($updateStmt->affected_rows === 0) {
        throw new Exception("Failed to update status");
    }
    
    error_log("Driver status updated to: " . $new_status . ". Affected rows: " . $updateStmt->affected_rows);
    
    // Log the status change in admin activity logs (if table exists)
    try {
        $log_id = bin2hex(random_bytes(16));
        $action = 'status_change';
        $old_values = json_encode(['driver_status' => $current_status]);
        $new_values = json_encode(['driver_status' => $new_status]);
        
        $logStmt = $conn->prepare("INSERT INTO admin_activity_logs (id, admin_id, action, entity_type, entity_id, old_values, new_values, created_at) VALUES (?, ?, ?, 'driver', ?, ?, ?, NOW())");
        $logStmt->bind_param("ssssss", $log_id, $user_id, $action, $driver_id, $old_values, $new_values);
        $logStmt->execute();
        error_log("Status change logged");
    } catch (Exception $e) {
        // Log table might not exist - ignore
        error_log("Could not log status change: " . $e->getMessage());
    }
    
    // Create notification for the driver (optional)
    if ($new_status === 'online') {
        try {
            $notif_id = bin2hex(random_bytes(16));
            $notifType = 'system';
            $notifTitle = 'You are now Online';
            $notifMsg = 'You are now online and can receive ride requests. Stay safe and drive carefully!';
            
            $notifStmt = $conn->prepare("INSERT INTO notifications (id, user_id, type, title, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
            $notifStmt->bind_param("sssss", $notif_id, $user_id, $notifType, $notifTitle, $notifMsg);
            $notifStmt->execute();
        } catch (Exception $e) {
            error_log("Could not create notification: " . $e->getMessage());
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Status updated to ' . $new_status,
        'status' => $new_status,
        'previous_status' => $current_status
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error updating driver status: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to update status: ' . $e->getMessage()]);
}

$conn->close();
error_log("=== Toggle Driver Status Request Completed ===");
?>