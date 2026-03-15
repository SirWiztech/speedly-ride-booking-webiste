<?php
// SERVER/API/get_transaction.php
header('Content-Type: application/json');
session_start();

require_once 'db-connect.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$transaction_id = $_GET['transaction_id'] ?? '';

if (empty($transaction_id)) {
    echo json_encode(['success' => false, 'message' => 'Transaction ID required']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    // Get transaction details
    $query = "SELECT wt.*, 
              r.ride_number,
              r.pickup_address,
              r.destination_address
              FROM wallet_transactions wt
              LEFT JOIN rides r ON wt.ride_id = r.id
              WHERE wt.id = ? AND wt.user_id = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ss", $transaction_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
        exit;
    }
    
    $transaction = $result->fetch_assoc();
    
    // Format the response
    $type_display = [
        'deposit' => 'Deposit',
        'withdrawal' => 'Withdrawal',
        'ride_payment' => 'Ride Payment',
        'ride_refund' => 'Ride Refund',
        'bonus' => 'Bonus',
        'referral' => 'Referral Bonus'
    ];
    
    $response = [
        'success' => true,
        'transaction' => [
            'id' => $transaction['id'],
            'user_id' => $transaction['user_id'],
            'transaction_type' => $transaction['transaction_type'],
            'type_display' => $type_display[$transaction['transaction_type']] ?? ucfirst($transaction['transaction_type']),
            'amount' => floatval($transaction['amount']),
            'balance_before' => floatval($transaction['balance_before']),
            'balance_after' => floatval($transaction['balance_after']),
            'reference' => $transaction['reference'],
            'status' => $transaction['status'],
            'description' => $transaction['description'],
            'ride_id' => $transaction['ride_id'],
            'ride_number' => $transaction['ride_number'],
            'pickup_address' => $transaction['pickup_address'],
            'destination_address' => $transaction['destination_address'],
            'created_at' => $transaction['created_at'],
            'formatted_date' => date('M d, Y h:i A', strtotime($transaction['created_at']))
        ]
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error fetching transaction: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load transaction details'
    ]);
}

$conn->close();
?>