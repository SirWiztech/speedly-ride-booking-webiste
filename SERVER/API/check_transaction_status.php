<?php
// SERVER/API/check_transaction_status.php
// This file checks the status of a pending payment transaction
// Used by payment_callback.php to poll for completion

header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/db-connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'success' => false, 
        'message' => 'Not logged in',
        'status' => 'error'
    ]);
    exit;
}

$user_id = $_SESSION['user_id'];
$reference = $_GET['reference'] ?? '';

// Validate reference
if (empty($reference)) {
    echo json_encode([
        'success' => false, 
        'message' => 'No transaction reference provided',
        'status' => 'error'
    ]);
    exit;
}

// Log the check for debugging (optional - comment out in production)
// error_log("Checking transaction status for reference: $reference, user: $user_id");

try {
    // ========== AUTO-CANCEL EXPIRED TRANSACTIONS ==========
    // This cancels all pending transactions that have expired
    $expireQuery = "UPDATE payment_gateway_transactions 
                    SET status = 'expired', 
                        gateway_response = '{\"message\": \"Transaction expired due to timeout\"}',
                        updated_at = NOW()
                    WHERE status = 'pending' 
                      AND expires_at IS NOT NULL 
                      AND expires_at < NOW()";
    
    if ($conn->query($expireQuery)) {
        $expiredCount = $conn->affected_rows;
        if ($expiredCount > 0) {
            error_log("Auto-cancelled $expiredCount expired transactions");
        }
    }
    // ========== END AUTO-CANCEL ==========

    // Query to get transaction status - optimized for speed
    $query = "SELECT 
                id, 
                user_id, 
                transaction_reference, 
                amount, 
                status,
                expires_at
              FROM payment_gateway_transactions 
              WHERE transaction_reference = ? AND user_id = ?
              LIMIT 1";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $reference, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $transaction = $result->fetch_assoc();

    if (!$transaction) {
        // Transaction not found in database
        echo json_encode([
            'success' => false, 
            'message' => 'Transaction not found',
            'status' => 'not_found'
        ]);
        exit;
    }

    // ========== CHECK IF TRANSACTION IS EXPIRED ==========
    if ($transaction['status'] === 'expired') {
        echo json_encode([
            'success' => true,
            'status' => 'expired',
            'message' => 'Payment session expired. Please initiate a new payment.',
            'reference' => $reference,
            'amount' => floatval($transaction['amount'])
        ]);
        exit;
    }
    
    // Also check if expiry time has passed but status not updated
    if ($transaction['status'] === 'pending' && !empty($transaction['expires_at'])) {
        $expiresAt = strtotime($transaction['expires_at']);
        $now = time();
        
        if ($now > $expiresAt) {
            // Update this specific transaction to expired
            $updateExpired = "UPDATE payment_gateway_transactions 
                              SET status = 'expired', 
                                  gateway_response = '{\"message\": \"Transaction expired\"}',
                                  updated_at = NOW()
                              WHERE id = ?";
            $updateStmt = $conn->prepare($updateExpired);
            $updateStmt->bind_param("s", $transaction['id']);
            $updateStmt->execute();
            
            echo json_encode([
                'success' => true,
                'status' => 'expired',
                'message' => 'Payment session expired. Please initiate a new payment.',
                'reference' => $reference,
                'amount' => floatval($transaction['amount'])
            ]);
            exit;
        }
    }
    // ========== END EXPIRY CHECK ==========

    // Get current wallet balance if transaction is successful (fast query)
    $newBalance = 0;
    if ($transaction['status'] === 'success') {
        $balanceQuery = "SELECT 
                            COALESCE(SUM(CASE WHEN transaction_type IN ('deposit', 'bonus', 'referral', 'ride_refund') THEN amount ELSE 0 END), 0) - 
                            COALESCE(SUM(CASE WHEN transaction_type IN ('withdrawal', 'ride_payment') THEN amount ELSE 0 END), 0) as balance 
                        FROM wallet_transactions 
                        WHERE user_id = ? AND status = 'completed'";
        $balanceStmt = $conn->prepare($balanceQuery);
        $balanceStmt->bind_param("s", $user_id);
        $balanceStmt->execute();
        $balanceResult = $balanceStmt->get_result();
        $balanceData = $balanceResult->fetch_assoc();
        $newBalance = $balanceData['balance'] ?? 0;
    }

    // Prepare response based on transaction status
    $response = [
        'success' => true,
        'reference' => $transaction['transaction_reference'],
        'status' => $transaction['status'],
        'amount' => floatval($transaction['amount'])
    ];
    
    // Add additional data for successful transactions
    if ($transaction['status'] === 'success') {
        $response['new_balance'] = $newBalance;
        $response['message'] = 'Payment completed successfully';
    } 
    elseif ($transaction['status'] === 'failed') {
        $response['message'] = 'Payment failed. Please try again.';
    } 
    elseif ($transaction['status'] === 'pending') {
        // Calculate time remaining if expires_at is set
        if ($transaction['expires_at']) {
            $expires = strtotime($transaction['expires_at']);
            $remaining = $expires - time();
            if ($remaining > 0) {
                $minutes = floor($remaining / 60);
                $seconds = $remaining % 60;
                $response['message'] = "Payment is being processed (expires in {$minutes}m {$seconds}s)";
            } else {
                $response['message'] = 'Payment is being processed';
            }
        } else {
            $response['message'] = 'Payment is being processed';
        }
    }
    
    echo json_encode($response);

} catch (Exception $e) {
    error_log("Error in check_transaction_status.php: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Database error',
        'status' => 'error'
    ]);
}
?>