<?php
// SERVER/API/get_user_wallet.php
header('Content-Type: application/json');
session_start();

require_once 'db-connect.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Get current balance
$balanceQuery = "SELECT 
    COALESCE(SUM(CASE WHEN transaction_type IN ('deposit', 'bonus', 'referral', 'ride_refund') THEN amount ELSE 0 END), 0) - 
    COALESCE(SUM(CASE WHEN transaction_type IN ('withdrawal', 'ride_payment') THEN amount ELSE 0 END), 0) as balance 
    FROM wallet_transactions WHERE user_id = ? AND status = 'completed'";

$balanceStmt = $conn->prepare($balanceQuery);
$balanceStmt->bind_param("s", $user_id);
$balanceStmt->execute();
$balanceResult = $balanceStmt->get_result();
$balanceData = $balanceResult->fetch_assoc();
$balance = $balanceData['balance'] ?? 0;

// Get recent transactions with ride details
$transQuery = "SELECT 
    wt.*,
    r.ride_number,
    r.pickup_address,
    r.destination_address
    FROM wallet_transactions wt
    LEFT JOIN rides r ON wt.ride_id = r.id
    WHERE wt.user_id = ? 
    ORDER BY wt.created_at DESC 
    LIMIT 20";

$transStmt = $conn->prepare($transQuery);
$transStmt->bind_param("s", $user_id);
$transStmt->execute();
$transResult = $transStmt->get_result();

$transactions = [];
while ($row = $transResult->fetch_assoc()) {
    // Format transaction type for display
    $type_display = [
        'deposit' => 'Deposit',
        'withdrawal' => 'Withdrawal',
        'ride_payment' => 'Ride Payment',
        'ride_refund' => 'Ride Refund',
        'bonus' => 'Bonus',
        'referral' => 'Referral Bonus'
    ];
    
    // Generate a proper transaction ID for display
    $display_id = $row['ride_number'] ?? 'TXN' . substr($row['id'], 0, 8);
    
    $transactions[] = [
        'id' => $row['id'],
        'display_id' => $display_id,
        'type' => $row['transaction_type'],
        'type_display' => $type_display[$row['transaction_type']] ?? ucfirst($row['transaction_type']),
        'amount' => floatval($row['amount']),
        'balance_before' => floatval($row['balance_before']),
        'balance_after' => floatval($row['balance_after']),
        'description' => $row['description'],
        'reference' => $row['reference'],
        'date' => $row['created_at'],
        'formatted_date' => date('M d, Y h:i A', strtotime($row['created_at'])),
        'status' => $row['status'],
        'ride_id' => $row['ride_id'],
        'ride_number' => $row['ride_number'],
        'pickup' => $row['pickup_address'],
        'destination' => $row['destination_address']
    ];
}

echo json_encode([
    'success' => true,
    'balance' => floatval($balance),
    'transactions' => $transactions
]);
?>