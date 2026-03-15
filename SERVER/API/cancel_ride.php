<?php
// SERVER/API/cancel_ride.php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Cancel Ride Request Started ===");

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
}

if (!isset($input['ride_id'])) {
    error_log("Ride ID not provided");
    echo json_encode(['success' => false, 'message' => 'Ride ID is required']);
    exit;
}

$ride_id = $input['ride_id'];
$reason = isset($input['reason']) ? $input['reason'] : 'Cancelled by user';

error_log("Cancelling ride ID: " . $ride_id . " with reason: " . $reason);

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'client';

// Begin transaction
$conn->begin_transaction();

try {
    // Get ride details with proper user IDs
    $rideQuery = "SELECT r.*, 
                  cp.user_id as client_user_id,
                  dp.user_id as driver_user_id
                  FROM rides r
                  LEFT JOIN client_profiles cp ON r.client_id = cp.id
                  LEFT JOIN driver_profiles dp ON r.driver_id = dp.id
                  WHERE r.id = ? FOR UPDATE";
    
    $rideStmt = $conn->prepare($rideQuery);
    $rideStmt->bind_param("s", $ride_id);
    $rideStmt->execute();
    $rideResult = $rideStmt->get_result();
    
    if ($rideResult->num_rows === 0) {
        throw new Exception("Ride not found");
    }
    
    $rideData = $rideResult->fetch_assoc();
    
    // Check if ride can be cancelled
    $cancellableStatuses = ['pending', 'accepted', 'driver_assigned'];
    if (!in_array($rideData['status'], $cancellableStatuses)) {
        throw new Exception("Ride cannot be cancelled in its current state");
    }
    
    // Determine who is cancelling and set the cancelled_by user_id
    $cancelled_by_user_id = null;
    $new_status = '';
    $other_party_id = null;
    
    if ($user_role === 'driver') {
        // Driver cancelling - verify they are the assigned driver
        $driverQuery = "SELECT id FROM driver_profiles WHERE user_id = ?";
        $driverStmt = $conn->prepare($driverQuery);
        $driverStmt->bind_param("s", $user_id);
        $driverStmt->execute();
        $driverResult = $driverStmt->get_result();
        $driverData = $driverResult->fetch_assoc();
        
        if (!$driverData || $driverData['id'] !== $rideData['driver_id']) {
            throw new Exception("You are not authorized to cancel this ride");
        }
        
        $cancelled_by_user_id = $user_id; // This is the actual user_id from session
        $new_status = 'cancelled_by_driver';
        $other_party_id = $rideData['client_user_id'];
    } else {
        // Client cancelling - verify they are the client
        $clientQuery = "SELECT id FROM client_profiles WHERE user_id = ?";
        $clientStmt = $conn->prepare($clientQuery);
        $clientStmt->bind_param("s", $user_id);
        $clientStmt->execute();
        $clientResult = $clientStmt->get_result();
        $clientData = $clientResult->fetch_assoc();
        
        if (!$clientData || $clientData['id'] !== $rideData['client_id']) {
            throw new Exception("You are not authorized to cancel this ride");
        }
        
        $cancelled_by_user_id = $user_id; // This is the actual user_id from session
        $new_status = 'cancelled_by_client';
        $other_party_id = $rideData['driver_user_id'];
    }
    
    // Update ride status
    $updateStmt = $conn->prepare("UPDATE rides SET status = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param("ss", $new_status, $ride_id);
    $updateStmt->execute();
    
    if ($updateStmt->affected_rows === 0) {
        throw new Exception("Failed to update ride status");
    }
    
    // Log cancellation with the CORRECT user_id (not a string like 'client')
    $logId = bin2hex(random_bytes(16));
    $logStmt = $conn->prepare("INSERT INTO ride_cancellations (id, ride_id, cancelled_by, reason, cancelled_at) VALUES (?, ?, ?, ?, NOW())");
    $logStmt->bind_param("ssss", $logId, $ride_id, $cancelled_by_user_id, $reason);
    
    if (!$logStmt->execute()) {
        error_log("Failed to insert cancellation log: " . $conn->error);
        throw new Exception("Failed to log cancellation: " . $conn->error);
    }
    
    // Create notification for other party
    if ($other_party_id) {
        $notifId = bin2hex(random_bytes(16));
        $notifType = 'ride_update';
        $notifTitle = 'Ride Cancelled';
        $cancelled_by_text = ($user_role === 'driver') ? 'driver' : 'client';
        $notifMsg = "Your ride has been cancelled by the " . $cancelled_by_text . ". Reason: " . $reason;
        
        $notifStmt = $conn->prepare("INSERT INTO notifications (id, user_id, type, title, message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $notifStmt->bind_param("sssss", $notifId, $other_party_id, $notifType, $notifTitle, $notifMsg);
        
        if (!$notifStmt->execute()) {
            error_log("Failed to create notification: " . $conn->error);
        }
    }
    
    // If payment was made via wallet, process refund
    if ($rideData['payment_status'] === 'paid' && $rideData['client_user_id']) {
        // Get user's wallet balance
        $balanceQuery = "SELECT 
            COALESCE(SUM(CASE WHEN transaction_type IN ('deposit', 'bonus', 'referral', 'ride_refund') THEN amount ELSE 0 END), 0) - 
            COALESCE(SUM(CASE WHEN transaction_type IN ('withdrawal', 'ride_payment') THEN amount ELSE 0 END), 0) as balance 
            FROM wallet_transactions WHERE user_id = ? AND status = 'completed'";
        
        $balanceStmt = $conn->prepare($balanceQuery);
        $balanceStmt->bind_param("s", $rideData['client_user_id']);
        $balanceStmt->execute();
        $balanceResult = $balanceStmt->get_result();
        $balanceData = $balanceResult->fetch_assoc();
        $current_balance = $balanceData['balance'] ?? 0;
        
        // Process refund
        $transaction_id = bin2hex(random_bytes(16));
        $refundAmount = $rideData['total_fare'];
        $balance_after = $current_balance + $refundAmount;
        
        $refundStmt = $conn->prepare("INSERT INTO wallet_transactions (
            id, user_id, transaction_type, amount, balance_before, balance_after,
            reference, status, description, ride_id, created_at
        ) VALUES (?, ?, 'ride_refund', ?, ?, ?, ?, 'completed', ?, ?, NOW())");
        
        $reference = 'REFUND_' . uniqid();
        $description = "Refund for cancelled ride #" . ($rideData['ride_number'] ?? $ride_id);
        
        $refundStmt->bind_param("ssddssss", 
            $transaction_id, 
            $rideData['client_user_id'], 
            $refundAmount, 
            $current_balance, 
            $balance_after, 
            $reference, 
            $description, 
            $ride_id
        );
        
        if (!$refundStmt->execute()) {
            error_log("Failed to process refund: " . $conn->error);
        } else {
            error_log("Refund processed for client: " . $rideData['client_user_id']);
        }
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Ride cancelled successfully',
        'ride_id' => $ride_id,
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