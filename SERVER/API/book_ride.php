<?php
// Only start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('Content-Type: application/json');

require_once 'db-connect.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get POST data
$pickup_address = $_POST['pickup_address'] ?? '';
$pickup_lat = $_POST['pickup_lat'] ?? 0;
$pickup_lng = $_POST['pickup_lng'] ?? 0;
$pickup_place_id = $_POST['pickup_place_id'] ?? '';
$dest_address = $_POST['dest_address'] ?? '';
$dest_lat = $_POST['dest_lat'] ?? 0;
$dest_lng = $_POST['dest_lng'] ?? 0;
$dest_place_id = $_POST['dest_place_id'] ?? '';
$distance = $_POST['distance'] ?? 0;
$fare = $_POST['fare'] ?? 0;
$driver_id = $_POST['driver_id'] ?? ''; // This may be empty if user skipped driver selection
$frontend_ride_type = strtolower(trim($_POST['ride_type'] ?? 'economy'));
$payment_method = $_POST['payment_method'] ?? 'wallet';

// Validate ride type - only economy or comfort allowed
if ($frontend_ride_type !== 'economy' && $frontend_ride_type !== 'comfort') {
    $frontend_ride_type = 'economy'; // Default to economy if invalid
}

$ride_type = $frontend_ride_type;

// Validate required fields
if (!$pickup_address || !$dest_address || !$fare) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Start transaction
$conn->begin_transaction();

try {
    // Get client profile ID
    $clientQuery = "SELECT id FROM client_profiles WHERE user_id = ?";
    $clientStmt = $conn->prepare($clientQuery);
    $clientStmt->bind_param("s", $user_id);
    $clientStmt->execute();
    $clientResult = $clientStmt->get_result();
    
    if ($clientResult->num_rows == 0) {
        // Create client profile if not exists
        $client_profile_id = bin2hex(random_bytes(16));
        $createProfile = "INSERT INTO client_profiles (id, user_id, membership_tier, created_at) VALUES (?, ?, 'basic', NOW())";
        $createStmt = $conn->prepare($createProfile);
        $createStmt->bind_param("ss", $client_profile_id, $user_id);
        $createStmt->execute();
        $client_id = $client_profile_id;
    } else {
        $clientData = $clientResult->fetch_assoc();
        $client_id = $clientData['id'];
    }
    
    // Get current wallet balance
    $balanceQuery = "SELECT 
        COALESCE(SUM(CASE WHEN transaction_type IN ('deposit', 'bonus', 'referral', 'ride_refund') THEN amount ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN transaction_type IN ('withdrawal', 'ride_payment') THEN amount ELSE 0 END), 0) as balance 
        FROM wallet_transactions WHERE user_id = ? AND status = 'completed'";
    $balanceStmt = $conn->prepare($balanceQuery);
    $balanceStmt->bind_param("s", $user_id);
    $balanceStmt->execute();
    $balanceResult = $balanceStmt->get_result();
    $balanceData = $balanceResult->fetch_assoc();
    $current_balance = $balanceData['balance'] ?? 0;
    
    // Check if payment method is wallet and balance is sufficient
    if ($payment_method === 'wallet' && $current_balance < $fare) {
        echo json_encode([
            'success' => false,
            'insufficient_balance' => true,
            'current_balance' => $current_balance,
            'required_amount' => $fare,
            'shortage' => $fare - $current_balance,
            'redirect' => 'wallet.php'
        ]);
        exit;
    }
    
    // Generate unique ride number and ID
    $ride_number = 'SPD' . strtoupper(substr(uniqid(), -6)) . date('ymd');
    $ride_id = bin2hex(random_bytes(16));
    
    // Calculate platform commission (20%)
    $platform_commission = $fare * 0.2;
    $driver_payout = $fare - $platform_commission;
    
    // Determine ride visibility:
    // - If driver_id is provided, the ride is assigned to that specific driver (status = 'pending' but only visible to that driver)
    // - If no driver_id, the ride is public and any driver can accept (status = 'pending' and driver_id = NULL)
    $final_driver_id = !empty($driver_id) ? $driver_id : null;
    
    // Insert ride
    $rideQuery = "INSERT INTO rides (
        id, ride_number, client_id, driver_id, pickup_address, pickup_latitude, pickup_longitude,
        destination_address, destination_latitude, destination_longitude, ride_type,
        distance_km, total_fare, platform_commission, driver_payout, status,
        payment_status, created_at
    ) VALUES (
        ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW()
    )";
    
    $rideStmt = $conn->prepare($rideQuery);
    
    if (empty($final_driver_id)) {
        // Public ride - no driver assigned
        $rideStmt->bind_param(
            "ssssssssssddddd",
            $ride_id,
            $ride_number,
            $client_id,
            $pickup_address,
            $pickup_lat,
            $pickup_lng,
            $dest_address,
            $dest_lat,
            $dest_lng,
            $ride_type,
            $distance,
            $fare,
            $platform_commission,
            $driver_payout
        );
    } else {
        // Private ride - assigned to specific driver
        $rideStmt->bind_param(
            "ssssssssssddddd",
            $ride_id,
            $ride_number,
            $client_id,
            $final_driver_id,
            $pickup_address,
            $pickup_lat,
            $pickup_lng,
            $dest_address,
            $dest_lat,
            $dest_lng,
            $ride_type,
            $distance,
            $fare,
            $platform_commission,
            $driver_payout
        );
    }
    
    if (!$rideStmt->execute()) {
        throw new Exception("Failed to insert ride: " . $rideStmt->error);
    }
    
    // Process payment if using wallet
    if ($payment_method === 'wallet') {
        $balance_before = $current_balance;
        $balance_after = $current_balance - $fare;
        $transaction_id = bin2hex(random_bytes(16));
        
        $walletQuery = "INSERT INTO wallet_transactions (
            id, user_id, transaction_type, amount, balance_before, balance_after,
            reference, status, description, ride_id, created_at
        ) VALUES (
            ?, ?, 'ride_payment', ?, ?, ?, ?, 'completed', ?, ?, NOW()
        )";
        
        $reference = 'RIDE_' . uniqid() . '_' . date('YmdHis');
        $description = "Payment for ride #$ride_number";
        
        $walletStmt = $conn->prepare($walletQuery);
        $walletStmt->bind_param(
            "ssdddsss",
            $transaction_id,
            $user_id, 
            $fare, 
            $balance_before, 
            $balance_after, 
            $reference, 
            $description, 
            $ride_id
        );
        
        if (!$walletStmt->execute()) {
            throw new Exception("Failed to record wallet transaction: " . $walletStmt->error);
        }
        
        // Update ride payment status
        $updateRideQuery = "UPDATE rides SET payment_status = 'paid' WHERE id = ?";
        $updateStmt = $conn->prepare($updateRideQuery);
        $updateStmt->bind_param("s", $ride_id);
        
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update payment status: " . $updateStmt->error);
        }
    }
    
    // ========== FIXED NOTIFICATION SECTIONS ==========
    
    // Send notifications based on ride type
    if (!empty($driver_id)) {
        // Private ride - notify ONLY the selected driver
        $driverUserStmt = $conn->prepare("SELECT user_id FROM driver_profiles WHERE id = ?");
        $driverUserStmt->bind_param("s", $driver_id);
        $driverUserStmt->execute();
        $driverUserResult = $driverUserStmt->get_result();
        
        if ($driverUserResult->num_rows > 0) {
            $driverUser = $driverUserResult->fetch_assoc();
            $driver_user_id = $driverUser['user_id'];
            
            // Create notification for the selected driver - USING ALLOWED TYPE 'ride_update'
            $notifType = 'ride_update';
            $notifTitle = 'New Private Ride Request';
            $notifMsg = "🔔 New private ride request from " . substr($pickup_address, 0, 30) . "...";
            $notif_id = bin2hex(random_bytes(16));
            
            $notifStmt = $conn->prepare("
                INSERT INTO notifications (id, user_id, type, title, message, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $notifStmt->bind_param("sssss", $notif_id, $driver_user_id, $notifType, $notifTitle, $notifMsg);
            
            if (!$notifStmt->execute()) {
                error_log("Failed to create driver notification: " . $conn->error);
            } else {
                error_log("Private ride notification sent to driver: " . $driver_user_id);
            }
        }
    } else {
        // Public ride - notify nearby online drivers (within 20km)
        $nearbyDriversQuery = "SELECT dp.id, dp.user_id 
                              FROM driver_profiles dp
                              WHERE dp.driver_status = 'online' 
                              AND dp.verification_status = 'approved'
                              AND dp.current_latitude IS NOT NULL
                              AND dp.current_longitude IS NOT NULL
                              AND (6371 * ACOS(
                                  COS(RADIANS(?)) * COS(RADIANS(dp.current_latitude)) *
                                  COS(RADIANS(dp.current_longitude) - RADIANS(?)) +
                                  SIN(RADIANS(?)) * SIN(RADIANS(dp.current_latitude))
                              )) < 20
                              LIMIT 50";
        
        $nearbyStmt = $conn->prepare($nearbyDriversQuery);
        $nearbyStmt->bind_param("ddd", $pickup_lat, $pickup_lng, $pickup_lat);
        $nearbyStmt->execute();
        $nearbyResult = $nearbyStmt->get_result();
        
        $notificationCount = 0;
        while ($nearbyDriver = $nearbyResult->fetch_assoc()) {
            // USING ALLOWED TYPE 'ride_update'
            $notifType = 'ride_update';
            $notifTitle = 'New Ride Available';
            $notifMsg = "🚗 New ride request near you from " . substr($pickup_address, 0, 30) . "...";
            $notif_id = bin2hex(random_bytes(16));
            
            $notifStmt = $conn->prepare("
                INSERT INTO notifications (id, user_id, type, title, message, created_at) 
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $notifStmt->bind_param("sssss", $notif_id, $nearbyDriver['user_id'], $notifType, $notifTitle, $notifMsg);
            
            if ($notifStmt->execute()) {
                $notificationCount++;
            }
        }
        error_log("Created $notificationCount notifications for nearby drivers");
    }
    
    // ========== END FIXED NOTIFICATION SECTIONS ==========
    
    // Commit transaction
    $conn->commit();
    
    $response = [
        'success' => true,
        'ride_id' => $ride_id,
        'ride_number' => $ride_number,
        'new_balance' => $balance_after ?? $current_balance,
        'message' => 'Ride booked successfully!',
        'driver_assigned' => !empty($driver_id)
    ];
    
    if (!empty($driver_id)) {
        $response['message'] = 'Ride booked successfully! The selected driver has been notified.';
    } else {
        $response['message'] = 'Ride booked successfully! Nearby drivers have been notified.';
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Booking error: " . $e->getMessage());
    
    echo json_encode([
        'success' => false,
        'message' => 'Booking failed: ' . $e->getMessage()
    ]);
}

$conn->close();
?>  