<?php
// SERVER/API/korapay_webhook.php
header('Content-Type: application/json');

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/korapay_config.php';

// Log file for debugging
$logFile = __DIR__ . '/webhook_log.txt';

// Get the raw POST data
$rawInput = file_get_contents('php://input');
$webhookData = json_decode($rawInput, true);

// Log the webhook
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Webhook received\n", FILE_APPEND);
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Data: " . $rawInput . "\n", FILE_APPEND);

// Process webhook based on event type
$event = $webhookData['event'] ?? '';
$data = $webhookData['data'] ?? [];

// Try to send immediate response if fastcgi is available
if (function_exists('fastcgi_finish_request')) {
    // Send 200 OK immediately
    http_response_code(200);
    echo json_encode(['status' => true, 'message' => 'Webhook received']);
    fastcgi_finish_request();
    
    // Now continue processing in background
    if ($event === 'charge.success' || $event === 'transfer.success') {
        processSuccessfulPayment($conn, $data, $logFile);
    } elseif ($event === 'charge.failed' || $event === 'transfer.failed') {
        processFailedPayment($conn, $data, $logFile);
    } else {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Unhandled event: $event\n", FILE_APPEND);
    }
} else {
    // FastCGI not available, process normally but quickly
    if ($event === 'charge.success' || $event === 'transfer.success') {
        processSuccessfulPayment($conn, $data, $logFile);
    } elseif ($event === 'charge.failed' || $event === 'transfer.failed') {
        processFailedPayment($conn, $data, $logFile);
    } else {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Unhandled event: $event\n", FILE_APPEND);
    }
    
    http_response_code(200);
    echo json_encode(['status' => true, 'message' => 'Webhook received']);
}

/**
 * Process successful payment
 */
function processSuccessfulPayment($conn, $data, $logFile) {
    // Get the amount that was actually paid (customer paid amount)
    $customerPaidAmount = $data['amount'] ?? 0;
    
    // Get the fee charged by KoraPay
    $fee = $data['fee'] ?? 0;
    
    // The amount that should be credited to wallet (if merchant bears fee, credit full amount)
    $creditAmount = $customerPaidAmount; // Since we set merchant_bears_cost = true
    
    $reference = $data['reference'] ?? '';
    $gatewayReference = $data['payment_reference'] ?? $data['transaction_reference'] ?? '';
    $paymentMethod = $data['payment_method'] ?? 'bank_transfer';
    
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Processing: ref=$reference, paid=$customerPaidAmount, fee=$fee, credit=$creditAmount\n", FILE_APPEND);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Find the transaction in our database
        $query = "SELECT id, user_id, amount, status FROM payment_gateway_transactions 
                  WHERE transaction_reference = ? OR gateway_reference = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ss", $reference, $reference);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaction = $result->fetch_assoc();
        
        if (!$transaction) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Transaction not found for: $reference\n", FILE_APPEND);
            $conn->rollback();
            return;
        }
        
        // Check if already processed
        if ($transaction['status'] === 'success') {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Already processed: $reference\n", FILE_APPEND);
            $conn->rollback();
            return;
        }
        
        // Update payment_gateway_transactions
        $updateQuery = "UPDATE payment_gateway_transactions 
                        SET status = 'success', 
                            gateway_reference = COALESCE(gateway_reference, ?),
                            payment_method = ?,
                            gateway_response = ?,
                            webhook_received = 1,
                            webhook_data = ?,
                            updated_at = NOW()
                        WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $gatewayResponse = json_encode($data);
        $webhookData = json_encode($data);
        $updateStmt->bind_param("sssss", $gatewayReference, $paymentMethod, $gatewayResponse, $webhookData, $transaction['id']);
        $updateStmt->execute();
        
        // Get current wallet balance
        $balanceQuery = "SELECT 
                            COALESCE(SUM(CASE WHEN transaction_type IN ('deposit', 'bonus', 'referral', 'ride_refund') THEN amount ELSE 0 END), 0) - 
                            COALESCE(SUM(CASE WHEN transaction_type IN ('withdrawal', 'ride_payment') THEN amount ELSE 0 END), 0) as balance 
                        FROM wallet_transactions 
                        WHERE user_id = ? AND status = 'completed'";
        $balanceStmt = $conn->prepare($balanceQuery);
        $balanceStmt->bind_param("s", $transaction['user_id']);
        $balanceStmt->execute();
        $balanceResult = $balanceStmt->get_result();
        $balanceData = $balanceResult->fetch_assoc();
        $currentBalance = $balanceData['balance'] ?? 0;
        $newBalance = $currentBalance + $creditAmount;
        
        // Insert wallet transaction (credit the full amount the customer intended to deposit)
        $walletQuery = "INSERT INTO wallet_transactions 
                        (id, user_id, transaction_type, amount, balance_before, balance_after, reference, status, description, ride_id, created_at) 
                        VALUES (UUID(), ?, 'deposit', ?, ?, ?, ?, 'completed', ?, NULL, NOW())";
        $walletStmt = $conn->prepare($walletQuery);
        $description = "Wallet deposit via KoraPay - Reference: $reference (Fee: ₦" . number_format($fee, 2) . ")";
        $walletStmt->bind_param("sddsss", $transaction['user_id'], $creditAmount, $currentBalance, $newBalance, $reference, $description);
        $walletStmt->execute();
        
        // Create notification for user
        $notificationQuery = "INSERT INTO notifications 
                              (id, user_id, type, title, message, is_read, created_at) 
                              VALUES (UUID(), ?, 'payment', 'Deposit Successful', 
                                      'Your deposit of ₦" . number_format($creditAmount, 2) . " has been successful. New balance: ₦" . number_format($newBalance, 2) . "', 
                                      0, NOW())";
        $notificationStmt = $conn->prepare($notificationQuery);
        $notificationStmt->bind_param("s", $transaction['user_id']);
        $notificationStmt->execute();
        
        $conn->commit();
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - ✅ Payment processed! User: " . $transaction['user_id'] . ", Amount: $creditAmount\n", FILE_APPEND);
        
    } catch (Exception $e) {
        $conn->rollback();
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - ❌ Error: " . $e->getMessage() . "\n", FILE_APPEND);
    }
}

/**
 * Process failed payment
 */
function processFailedPayment($conn, $data, $logFile) {
    $reference = $data['reference'] ?? '';
    $reason = $data['message'] ?? 'Payment failed';
    
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Failed payment: $reference - $reason\n", FILE_APPEND);
    
    // Find and update transaction
    $query = "SELECT id, status FROM payment_gateway_transactions 
              WHERE transaction_reference = ? OR gateway_reference = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $reference, $reference);
    $stmt->execute();
    $result = $stmt->get_result();
    $transaction = $result->fetch_assoc();
    
    if ($transaction && $transaction['status'] !== 'failed') {
        $updateQuery = "UPDATE payment_gateway_transactions 
                        SET status = 'failed', 
                            gateway_response = ?,
                            webhook_received = 1,
                            webhook_data = ?,
                            updated_at = NOW()
                        WHERE id = ?";
        $updateStmt = $conn->prepare($updateQuery);
        $gatewayResponse = json_encode($data);
        $webhookData = json_encode($data);
        $updateStmt->bind_param("sss", $gatewayResponse, $webhookData, $transaction['id']);
        $updateStmt->execute();
        
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Marked as failed\n", FILE_APPEND);
    }
}
?>