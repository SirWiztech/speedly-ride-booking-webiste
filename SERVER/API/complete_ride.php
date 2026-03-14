<?php
// SERVER/API/complete_ride.php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Complete Ride Request Started ===");
error_log("Session data: " . json_encode($_SESSION));
error_log("Raw input: " . file_get_contents('php://input'));

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
$rating = isset($input['rating']) ? intval($input['rating']) : null;
$comment = isset($input['comment']) ? $input['comment'] : '';

error_log("Completing ride ID: " . $ride_id . " with rating: " . ($rating ?? 'none'));

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'client';

error_log("User ID: " . $user_id . ", User Role: " . $user_role);

// Get driver profile ID if user is a driver
$driver_id = null;
$driver_profile = null;

// First, check if this user has a driver profile
$stmt = $conn->prepare("SELECT id, driver_status FROM driver_profiles WHERE user_id = ?");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $driver_profile = $result->fetch_assoc();
    $driver_id = $driver_profile['id'];
    error_log("Driver profile found. Driver ID: " . $driver_id . ", Status: " . $driver_profile['driver_status']);
} else {
    error_log("No driver profile found for user_id: " . $user_id);
    
    // If user is not a driver, they cannot complete rides
    if ($user_role !== 'driver') {
        echo json_encode(['success' => false, 'message' => 'Only drivers can complete rides']);
        exit;
    }
}

// Begin transaction
$conn->begin_transaction();

try {
    // First, lock the ride row to prevent race conditions
    $lockStmt = $conn->prepare("
        SELECT r.*, cp.user_id as client_user_id, cp.id as client_profile_id
        FROM rides r 
        JOIN client_profiles cp ON r.client_id = cp.id 
        WHERE r.id = ? 
        FOR UPDATE
    ");
    $lockStmt->bind_param("s", $ride_id);
    $lockStmt->execute();
    $lockResult = $lockStmt->get_result();
    
    if ($lockResult->num_rows === 0) {
        throw new Exception("Ride not found");
    }
    
    $rideData = $lockResult->fetch_assoc();
    error_log("Ride locked. Status: " . $rideData['status'] . ", Driver ID: " . ($rideData['driver_id'] ?? 'null'));
    
    // Verify that this ride belongs to the driver and is in a completable state
    $completableStatuses = ['accepted', 'driver_arrived', 'ongoing'];
    
    if (!in_array($rideData['status'], $completableStatuses)) {
        error_log("Ride cannot be completed. Current status: " . $rideData['status']);
        throw new Exception("Ride cannot be completed in its current state: " . $rideData['status']);
    }
    
    // Verify that the driver is the one assigned to this ride
    if ($rideData['driver_id'] !== $driver_id) {
        error_log("Driver mismatch. Ride driver: " . ($rideData['driver_id'] ?? 'null') . ", Your driver ID: " . $driver_id);
        throw new Exception("You are not authorized to complete this ride");
    }
    
    // Update ride status to completed
    $updateStmt = $conn->prepare("
        UPDATE rides 
        SET status = 'completed', 
            completed_at = NOW(),
            updated_at = NOW()
        WHERE id = ? AND status IN ('accepted', 'driver_arrived', 'ongoing')
    ");
    $updateStmt->bind_param("s", $ride_id);
    $updateStmt->execute();
    
    if ($updateStmt->affected_rows === 0) {
        throw new Exception("Failed to update ride status. No rows affected.");
    }
    
    error_log("Ride status updated to completed. Affected rows: " . $updateStmt->affected_rows);
    
    // Add rating if provided (driver rating the client)
    if ($rating !== null && $rating >= 1 && $rating <= 5) {
        // Check if client_ratings table exists
        $checkTable = $conn->query("SHOW TABLES LIKE 'client_ratings'");
        if ($checkTable && $checkTable->num_rows > 0) {
            // Insert into client_ratings
            $ratingId = bin2hex(random_bytes(16));
            $ratingStmt = $conn->prepare("
                INSERT INTO client_ratings (id, ride_id, user_id, client_id, rating, review, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            $ratingStmt->bind_param("ssssis", $ratingId, $ride_id, $user_id, $rideData['client_profile_id'], $rating, $comment);
            
            if (!$ratingStmt->execute()) {
                error_log("Failed to insert client rating: " . $ratingStmt->error);
            } else {
                error_log("Client rating added");
            }
        } else {
            // Fallback: update rides table with driver's rating of client
            $ratingStmt = $conn->prepare("
                UPDATE rides 
                SET driver_rating = ?, driver_review = ?
                WHERE id = ?
            ");
            $ratingStmt->bind_param("iss", $rating, $comment, $ride_id);
            $ratingStmt->execute();
            error_log("Driver rating added to rides table");
        }
    }
    
    // Update driver's stats (completed rides count, earnings, etc.)
    $updateDriverStats = $conn->prepare("
        UPDATE driver_profiles 
        SET 
            completed_rides = completed_rides + 1,
            total_earnings = total_earnings + ?,
            updated_at = NOW()
        WHERE id = ?
    ");
    $driver_payout = $rideData['driver_payout'] ?? 0;
    $updateDriverStats->bind_param("ds", $driver_payout, $driver_id);
    $updateDriverStats->execute();
    error_log("Driver stats updated. Added " . $driver_payout . " to earnings");
    
    // Create notification for client that ride is completed
    $notifType = 'ride_update';
    $notifTitle = 'Ride Completed';
    $notifMsg = 'Your ride has been completed. Thank you for riding with Speedly! Please rate your driver.';
    $notifId = bin2hex(random_bytes(16));
    
    $notifStmt = $conn->prepare("
        INSERT INTO notifications (id, user_id, type, title, message, created_at)
        VALUES (?, ?, ?, ?, ?, NOW())
    ");
    $notifStmt->bind_param("sssss", $notifId, $rideData['client_user_id'], $notifType, $notifTitle, $notifMsg);
    
    if (!$notifStmt->execute()) {
        error_log("Failed to create client notification: " . $conn->error);
    } else {
        error_log("Notification sent to client: " . $rideData['client_user_id']);
    }
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Ride completed successfully',
        'ride_id' => $ride_id,
        'earnings' => $driver_payout
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error completing ride: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to complete ride: ' . $e->getMessage()]);
}

$conn->close();
error_log("=== Complete Ride Request Completed ===");
?>  