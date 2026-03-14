<?php
// SERVER/API/accept_ride.php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Accept Ride Request Started ===");
error_log("Session data: " . json_encode($_SESSION));

// Check if user is logged in and is a driver
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    error_log("User is not a driver. Role: " . ($_SESSION['role'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Only drivers can accept rides']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

if (!$input || !isset($input['ride_id'])) {
    error_log("Invalid request data");
    echo json_encode(['success' => false, 'message' => 'Invalid request data. Ride ID is required.']);
    exit;
}

$ride_id = $input['ride_id'];
error_log("Processing ride acceptance for ride_id: " . $ride_id);

// Get driver profile ID
$stmt = $conn->prepare("SELECT id, driver_status, verification_status FROM driver_profiles WHERE user_id = ?");
$stmt->bind_param("s", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error_log("Driver profile not found for user_id: " . $_SESSION['user_id']);
    echo json_encode(['success' => false, 'message' => 'Driver profile not found']);
    exit;
}

$driver = $result->fetch_assoc();
$driver_id = $driver['id'];
$driver_status = $driver['driver_status'];
$verification_status = $driver['verification_status'];

error_log("Driver ID: " . $driver_id . ", Status: " . $driver_status . ", Verification: " . $verification_status);

// Check if driver is verified
if ($verification_status !== 'approved') {
    error_log("Driver not verified. Status: " . $verification_status);
    echo json_encode(['success' => false, 'message' => 'Your account is not verified. Please complete KYC first.']);
    exit;
}

// Check if driver is online
if ($driver_status !== 'online') {
    error_log("Driver not online. Status: " . $driver_status);
    echo json_encode(['success' => false, 'message' => 'You need to go online to accept rides']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Lock the ride row for update
    $lockStmt = $conn->prepare("
        SELECT id, status, driver_id 
        FROM rides 
        WHERE id = ? 
        FOR UPDATE
    ");
    $lockStmt->bind_param("s", $ride_id);
    $lockStmt->execute();
    $lockResult = $lockStmt->get_result();
    
    if ($lockResult->num_rows === 0) {
        throw new Exception("Ride not found");
    }
    
    $rideData = $lockResult->fetch_assoc();
    error_log("Ride locked. Status: " . $rideData['status'] . ", Assigned Driver ID: " . ($rideData['driver_id'] ?? 'null'));
    
    // Check if ride is pending
    if ($rideData['status'] !== 'pending') {
        throw new Exception("This ride is no longer available (status: " . $rideData['status'] . ")");
    }
    
    // Check if this is a private ride (has a specific driver assigned)
    if (!empty($rideData['driver_id'])) {
        // This is a private ride - only the assigned driver can accept
        if ($rideData['driver_id'] !== $driver_id) {
            error_log("Private ride - wrong driver. Assigned to: " . $rideData['driver_id'] . ", Attempting driver: " . $driver_id);
            throw new Exception("This is a private ride assigned to another driver");
        }
        error_log("Private ride - correct driver accepting");
    } else {
        // This is a public ride - any driver can accept
        error_log("Public ride - any driver can accept");
        // For public rides, set the driver_id
        $updateDriverStmt = $conn->prepare("
            UPDATE rides 
            SET driver_id = ?
            WHERE id = ? AND driver_id IS NULL
        ");
        $updateDriverStmt->bind_param("ss", $driver_id, $ride_id);
        $updateDriverStmt->execute();
    }
    
    // Get client user_id for notification
    $clientStmt = $conn->prepare("
        SELECT cp.user_id as client_user_id 
        FROM rides r
        JOIN client_profiles cp ON r.client_id = cp.id 
        WHERE r.id = ?
    ");
    $clientStmt->bind_param("s", $ride_id);
    $clientStmt->execute();
    $clientResult = $clientStmt->get_result();
    $clientData = $clientResult->fetch_assoc();
    $client_user_id = $clientData['client_user_id'] ?? null;
    
    // Update ride status to accepted
    $updateStmt = $conn->prepare("
        UPDATE rides 
        SET status = 'accepted', 
            updated_at = NOW() 
        WHERE id = ? AND status = 'pending'
    ");
    $updateStmt->bind_param("s", $ride_id);
    $updateStmt->execute();
    
    if ($updateStmt->affected_rows === 0) {
        throw new Exception("Failed to update ride status");
    }
    
    error_log("Ride updated successfully to accepted status");
    
    // Create notification for client
    if ($client_user_id) {
        $notifType = 'ride_update';
        $notifTitle = 'Ride Accepted';
        $notifMsg = "Your ride has been accepted by a driver. They are on the way to pick you up.";
        $notif_id = bin2hex(random_bytes(16));
        
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (id, user_id, type, title, message, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $notifStmt->bind_param("sssss", $notif_id, $client_user_id, $notifType, $notifTitle, $notifMsg);
        
        if (!$notifStmt->execute()) {
            error_log("Failed to create notification: " . $conn->error);
        } else {
            error_log("Notification created for client: " . $client_user_id);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Ride accepted successfully',
        'ride_id' => $ride_id,
        'driver_id' => $driver_id,
        'ride_type' => !empty($rideData['driver_id']) ? 'private' : 'public'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error accepting ride: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
error_log("=== Accept Ride Request Completed ===");
?>  