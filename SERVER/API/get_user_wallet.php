<?php
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

// Get recent transactions
$transQuery = "SELECT * FROM wallet_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 10";
$transStmt = $conn->prepare($transQuery);
$transStmt->bind_param("s", $user_id);
$transStmt->execute();
$transResult = $transStmt->get_result();

$transactions = [];
while ($row = $transResult->fetch_assoc()) {
    $transactions[] = [
        'id' => $row['id'],
        'type' => $row['transaction_type'],
        'amount' => floatval($row['amount']),
        'balance' => floatval($row['balance_after']),
        'description' => $row['description'],
        'date' => $row['created_at'],
        'status' => $row['status']
    ];
}

echo json_encode([
    'success' => true,
    'balance' => floatval($balance),
    'transactions' => $transactions
]);
?> 