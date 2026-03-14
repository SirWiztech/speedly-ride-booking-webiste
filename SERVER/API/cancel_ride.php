<?php
// SERVER/API/cancel_ride.php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Cancel Ride Request Started ===");
error_log("Session data: " . json_encode($_SESSION));

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
    error_log("Using POST data: " . json_encode($input));
}

if (!isset($input['ride_id'])) {
    error_log("Ride ID not provided");
    echo json_encode(['success' => false, 'message' => 'Ride ID is required']);
    exit;
}

$ride_id = $input['ride_id'];
$reason = isset($input['reason']) ? $input['reason'] : 'No reason provided';

error_log("Cancelling ride ID: " . $ride_id . " with reason: " . $reason);

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'client';

error_log("User ID: " . $user_id . ", User Role: " . $user_role);

// Get driver profile ID if user is a driver
$driver_id = null;
$client_id = null;

if ($user_role === 'driver') {
    // User is a driver - get their driver profile ID
    $stmt = $conn->prepare("SELECT id FROM driver_profiles WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $driver = $result->fetch_assoc();
        $driver_id = $driver['id'];
        error_log("Driver profile found. Driver ID: " . $driver_id);
    } else {
        error_log("Driver profile not found for user_id: " . $user_id);
        echo json_encode(['success' => false, 'message' => 'Driver profile not found']);
        exit;
    }
} else {
    // User is a client - get their client profile ID
    $stmt = $conn->prepare("SELECT id FROM client_profiles WHERE user_id = ?");
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $client = $result->fetch_assoc();
        $client_id = $client['id'];
        error_log("Client profile found. Client ID: " . $client_id);
    } else {
        error_log("Client profile not found for user_id: " . $user_id);
        echo json_encode(['success' => false, 'message' => 'Client profile not found']);
        exit;
    }
}

// Begin transaction
$conn->begin_transaction();

try {
    // First, lock the ride row to prevent race conditions
    $lockStmt = $conn->prepare("
        SELECT id, status, driver_id, client_id 
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
    error_log("Ride locked. Status: " . $rideData['status'] . ", Driver ID: " . ($rideData['driver_id'] ?? 'null') . ", Client ID: " . $rideData['client_id']);
    
    // Check if ride can be cancelled based on status
    $cancellableStatuses = ['pending', 'accepted', 'driver_assigned'];
    if (!in_array($rideData['status'], $cancellableStatuses)) {
        throw new Exception("Ride cannot be cancelled in its current state: " . $rideData['status']);
    }
    
    // Verify that the user has permission to cancel this ride
    $cancelled_by = '';
    if ($user_role === 'driver') {
        // Driver cancelling - must be the assigned driver
        if ($rideData['driver_id'] !== $driver_id) {
            error_log("Driver " . $driver_id . " trying to cancel ride assigned to driver " . $rideData['driver_id']);
            throw new Exception("You are not authorized to cancel this ride");
        }
        $cancelled_by = 'driver';
        $new_status = 'cancelled_by_driver';
        $other_party_id = $rideData['client_id'];
        $other_party_role = 'client';
    } else {
        // Client cancelling - must be the client who booked the ride
        if ($rideData['client_id'] !== $client_id) {
            error_log("Client " . $client_id . " trying to cancel ride booked by client " . $rideData['client_id']);
            throw new Exception("You are not authorized to cancel this ride");
        }
        $cancelled_by = 'client';
        $new_status = 'cancelled_by_client';
        $other_party_id = $rideData['driver_id'];
        $other_party_role = 'driver';
    }
    
    // Update ride status to cancelled
    $updateStmt = $conn->prepare("
        UPDATE rides 
        SET status = ?, 
            updated_at = NOW() 
        WHERE id = ? AND status IN ('pending', 'accepted', 'driver_assigned')
    ");
    $updateStmt->bind_param("ss", $new_status, $ride_id);
    $updateStmt->execute();
    
    if ($updateStmt->affected_rows === 0) {
        throw new Exception("Failed to update ride status");
    }
    
    error_log("Ride status updated to " . $new_status . ". Affected rows: " . $updateStmt->affected_rows);
    
    // Log the cancellation
    $cancelLogStmt = $conn->prepare("
        INSERT INTO ride_cancellations (id, ride_id, cancelled_by, reason, cancelled_at) 
        VALUES (UUID(), ?, ?, ?, NOW())
    ");
    $cancelLogStmt->bind_param("sss", $ride_id, $cancelled_by, $reason);
    $cancelLogStmt->execute();
    
    error_log("Cancellation logged for ride: " . $ride_id);
    
    // Create notification for the other party
    if ($other_party_id) {
        // Get the user_id of the other party
        if ($other_party_role === 'client') {
            // Other party is client
            $userQuery = "SELECT user_id FROM client_profiles WHERE id = ?";
        } else {
            // Other party is driver
            $userQuery = "SELECT user_id FROM driver_profiles WHERE id = ?";
        }
        
        $userStmt = $conn->prepare($userQuery);
        $userStmt->bind_param("s", $other_party_id);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($userResult->num_rows > 0) {
            $userData = $userResult->fetch_assoc();
            $other_user_id = $userData['user_id'];
            
            $notifType = 'ride_update';
            $notifTitle = 'Ride Cancelled';
            $notifMsg = "Your ride has been cancelled by the " . $cancelled_by . ". Reason: " . $reason;
            $notif_id = bin2hex(random_bytes(16));
            
            $notifStmt = $conn->prepare("
                INSERT INTO notifications (id, user_id, type, title, message, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $notifStmt->bind_param("sssss", $notif_id, $other_user_id, $notifType, $notifTitle, $notifMsg);
            $notifStmt->execute();
            
            error_log("Cancellation notification sent to " . $other_party_role . ": " . $other_user_id);
        }
    }
    
    // If payment was made and ride is cancelled, process refund (if applicable)
    // This would depend on your business logic and payment provider
    if ($rideData['payment_status'] === 'paid' && in_array($rideData['status'], ['pending', 'accepted'])) {
        // TODO: Process refund logic here
        error_log("Ride was paid for. Refund may be processed based on cancellation policy.");
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Ride cancelled successfully',
        'ride_id' => $ride_id,
        'cancelled_by' => $cancelled_by,
        'new_status' => $new_status
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error cancelling ride: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

$conn->close();
error_log("=== Cancel Ride Request Completed ===");
?>   