<?php
// SERVER/API/rate_driver.php
header('Content-Type: application/json');
session_start();

require_once 'db-connect.php';

// Enable error logging
error_log("=== Rate Driver Request Started ===");
error_log("POST data: " . json_encode($_POST));
error_log("Session user_id: " . ($_SESSION['user_id'] ?? 'not set'));

if (!isset($_SESSION['user_id'])) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("Invalid request method: " . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$ride_id = $_POST['ride_id'] ?? '';
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$review = $_POST['review'] ?? '';

error_log("Rating ride ID: " . $ride_id . " with rating: " . $rating);

if (empty($ride_id) || $rating < 1 || $rating > 5) {
    error_log("Invalid rating data");
    echo json_encode(['success' => false, 'message' => 'Invalid rating data']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // First, get the client profile ID for this user
    $clientQuery = "SELECT id FROM client_profiles WHERE user_id = ?";
    $clientStmt = $conn->prepare($clientQuery);
    $clientStmt->bind_param("s", $user_id);
    $clientStmt->execute();
    $clientResult = $clientStmt->get_result();

    if ($clientResult->num_rows === 0) {
        error_log("Client profile not found for user: " . $user_id);
        echo json_encode(['success' => false, 'message' => 'Client profile not found']);
        exit;
    }

    $clientData = $clientResult->fetch_assoc();
    $client_profile_id = $clientData['id'];
    error_log("Client profile ID: " . $client_profile_id);

    // Check if the ride exists and belongs to this client
    $rideQuery = "SELECT r.*, r.driver_id, r.status, r.client_id 
                  FROM rides r 
                  WHERE r.id = ? AND r.client_id = ?";
    $rideStmt = $conn->prepare($rideQuery);
    $rideStmt->bind_param("ss", $ride_id, $client_profile_id);
    $rideStmt->execute();
    $rideResult = $rideStmt->get_result();

    error_log("Ride query executed. Found rows: " . $rideResult->num_rows);

    if ($rideResult->num_rows === 0) {
        error_log("Ride not found for ID: " . $ride_id . " and client_profile_id: " . $client_profile_id);
        echo json_encode(['success' => false, 'message' => 'Ride not found']);
        exit;
    }

    $ride = $rideResult->fetch_assoc();
    error_log("Ride found. Status: " . $ride['status'] . ", Driver ID: " . ($ride['driver_id'] ?? 'none'));

    // Check if ride is completed
    if ($ride['status'] !== 'completed') {
        error_log("Ride is not completed. Current status: " . $ride['status']);
        echo json_encode(['success' => false, 'message' => 'Ride is not completed yet']);
        exit;
    }

    if (empty($ride['driver_id'])) {
        error_log("No driver assigned to this ride");
        echo json_encode(['success' => false, 'message' => 'No driver was assigned to this ride']);
        exit;
    }

    // Check if already rated
    $checkQuery = "SELECT id FROM driver_ratings WHERE ride_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("s", $ride_id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows > 0) {
        error_log("Ride already rated");
        echo json_encode(['success' => false, 'message' => 'You have already rated this ride']);
        exit;
    }

    // Begin transaction
    $conn->begin_transaction();

    // Insert rating into driver_ratings table
    $ratingId = bin2hex(random_bytes(16));
    $ratingQuery = "INSERT INTO driver_ratings (id, ride_id, user_id, driver_id, rating, review, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())";
    $ratingStmt = $conn->prepare($ratingQuery);
    $ratingStmt->bind_param("ssssis", $ratingId, $ride_id, $user_id, $ride['driver_id'], $rating, $review);

    if (!$ratingStmt->execute()) {
        throw new Exception("Failed to insert rating: " . $ratingStmt->error);
    }

    error_log("Rating inserted successfully with ID: " . $ratingId);

    // Update the rides table with client rating
    $updateRideStmt = $conn->prepare("
        UPDATE rides SET client_rating = ?, client_review = ? WHERE id = ?
    ");
    $updateRideStmt->bind_param("iss", $rating, $review, $ride_id);
    
    if (!$updateRideStmt->execute()) {
        error_log("Warning: Failed to update rides table: " . $updateRideStmt->error);
        // Don't throw exception, this is not critical
    }

    // Update driver's average rating in driver_profiles
    $avgQuery = "UPDATE driver_profiles dp 
                 SET dp.average_rating = (
                     SELECT AVG(rating) FROM driver_ratings WHERE driver_id = ?
                 ),
                 dp.total_reviews = (
                     SELECT COUNT(*) FROM driver_ratings WHERE driver_id = ?
                 )
                 WHERE dp.id = ?";
    $avgStmt = $conn->prepare($avgQuery);
    $avgStmt->bind_param("sss", $ride['driver_id'], $ride['driver_id'], $ride['driver_id']);
    
    if (!$avgStmt->execute()) {
        error_log("Warning: Failed to update driver average rating: " . $avgStmt->error);
        // Don't throw exception, this is not critical
    } else {
        error_log("Driver average rating updated for driver: " . $ride['driver_id']);
    }

    // Create a notification for the driver about the new rating
    $driverUserQuery = "SELECT user_id FROM driver_profiles WHERE id = ?";
    $driverUserStmt = $conn->prepare($driverUserQuery);
    $driverUserStmt->bind_param("s", $ride['driver_id']);
    $driverUserStmt->execute();
    $driverUserResult = $driverUserStmt->get_result();
    
    if ($driverUserResult->num_rows > 0) {
        $driverUserData = $driverUserResult->fetch_assoc();
        $driver_user_id = $driverUserData['user_id'];
        
        $notifType = 'ride_update';
        $notifTitle = 'New Rating Received';
        $notifMsg = "You received a " . $rating . "-star rating from a passenger!" . ($review ? " Review: " . $review : "");
        $notifId = bin2hex(random_bytes(16));
        
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (id, user_id, type, title, message, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $notifStmt->bind_param("sssss", $notifId, $driver_user_id, $notifType, $notifTitle, $notifMsg);
        $notifStmt->execute();
        error_log("Rating notification sent to driver: " . $driver_user_id);
    }

    // Commit transaction
    $conn->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'Rating submitted successfully',
        'rating_id' => $ratingId,
        'new_average' => null // Will be refreshed on next page load
    ]);

} catch (Exception $e) {
    $conn->rollback();
    error_log("Error submitting rating: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Failed to submit rating: ' . $e->getMessage()]);
}

$conn->close();
error_log("=== Rate Driver Request Completed ===");
?>   