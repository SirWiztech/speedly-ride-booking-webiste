<?php
// SERVER/API/decline_ride.php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Decline Ride Request Started ===");
error_log("Session data: " . json_encode($_SESSION));
error_log("Raw input: " . file_get_contents('php://input'));

// Check if user is logged in and is a driver
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'driver') {
    error_log("User is not a driver. Role: " . ($_SESSION['role'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Only drivers can decline rides']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    // Try to get from POST if JSON decode failed
    $input = $_POST;
    error_log("Using POST data instead of JSON: " . json_encode($input));
}

if (!$input || !isset($input['ride_id'])) {
    error_log("Invalid request data");
    echo json_encode(['success' => false, 'message' => 'Invalid request data. Ride ID is required.']);
    exit;
}

$ride_id = $input['ride_id'];
$auto_decline = isset($input['auto_decline']) ? (bool)$input['auto_decline'] : false;

error_log("Processing decline for ride_id: " . $ride_id . ", auto: " . ($auto_decline ? 'yes' : 'no'));

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
    echo json_encode(['success' => false, 'message' => 'Your account is not verified.']);
    exit;
}

// Begin transaction
$conn->begin_transaction();

try {
    // Check if ride exists and is pending
    $checkStmt = $conn->prepare("
        SELECT r.id, r.status, r.driver_id, r.client_id,
               cp.user_id as client_user_id
        FROM rides r
        LEFT JOIN client_profiles cp ON r.client_id = cp.id
        WHERE r.id = ? AND r.status = 'pending'
        FOR UPDATE
    ");
    $checkStmt->bind_param("s", $ride_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        error_log("Ride not found or not pending: " . $ride_id);
        echo json_encode(['success' => false, 'message' => 'Ride is no longer available']);
        $conn->commit(); // Commit the transaction to release the lock
        exit;
    }

    $rideData = $checkResult->fetch_assoc();
    error_log("Ride found: " . json_encode(['id' => $rideData['id'], 'driver_id' => $rideData['driver_id']]));

    // Check if this is a private ride assigned to this driver
    if (!empty($rideData['driver_id']) && $rideData['driver_id'] !== $driver_id) {
        error_log("Private ride assigned to different driver: " . $rideData['driver_id']);
        echo json_encode(['success' => false, 'message' => 'This ride is assigned to another driver']);
        $conn->commit();
        exit;
    }

    // Log the decline (for analytics and tracking)
    $logStmt = $conn->prepare("
        INSERT INTO ride_declines (id, ride_id, driver_id, auto_decline, created_at) 
        VALUES (UUID(), ?, ?, ?, NOW())
    ");
    $auto_decline_int = $auto_decline ? 1 : 0;
    $logStmt->bind_param("ssi", $ride_id, $driver_id, $auto_decline_int);
    $logStmt->execute();
    
    error_log("Decline logged for ride_id: " . $ride_id . ", driver_id: " . $driver_id);

    // Update driver's acceptance rate (optional)
    // You can implement logic to track decline rate here
    
    // For private rides that were assigned specifically to this driver,
    // we might want to update the ride to make it public or notify the client
    if (!empty($rideData['driver_id']) && $rideData['driver_id'] === $driver_id) {
        // This was a private ride for this driver - they declined
        // Option 1: Remove driver assignment to make it public
        $updateStmt = $conn->prepare("
            UPDATE rides 
            SET driver_id = NULL,
                updated_at = NOW()
            WHERE id = ? AND driver_id = ?
        ");
        $updateStmt->bind_param("ss", $ride_id, $driver_id);
        $updateStmt->execute();
        
        if ($updateStmt->affected_rows > 0) {
            error_log("Private ride assignment removed. Ride is now public.");
            
            // Notify the client that their preferred driver declined
            if (isset($rideData['client_user_id'])) {
                $notifType = 'ride_update';
                $notifTitle = 'Driver Declined';
                $notifMsg = "Your preferred driver is not available. Your ride is now open to all nearby drivers.";
                $notifId = bin2hex(random_bytes(16));
                
                $notifStmt = $conn->prepare("
                    INSERT INTO notifications (id, user_id, type, title, message, created_at)
                    VALUES (?, ?, ?, ?, ?, NOW())
                ");
                $notifStmt->bind_param("sssss", $notifId, $rideData['client_user_id'], $notifType, $notifTitle, $notifMsg);
                $notifStmt->execute();
                error_log("Client notified of driver decline: " . $rideData['client_user_id']);
            }
        }
    }

    // Commit transaction
    $conn->commit();

    // Prepare response message
    $message = $auto_decline ? 'Ride request expired' : 'Ride declined successfully';
    if (!empty($rideData['driver_id']) && $rideData['driver_id'] === $driver_id) {
        $message = $auto_decline ? 'Private ride request expired' : 'Private ride declined';
    }

    echo json_encode([
        'success' => true,
        'message' => $message,
        'ride_id' => $ride_id,
        'auto_decline' => $auto_decline,
        'was_private' => !empty($rideData['driver_id'])
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error in decline_ride.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

$conn->close();
error_log("=== Decline Ride Request Completed ===");
?>   