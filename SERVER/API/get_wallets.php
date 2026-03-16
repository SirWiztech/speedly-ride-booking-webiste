<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/db-connect.php';

// Enable error logging
error_log("=== Get Wallets Request Started ===");

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Get wallet statistics
    $statsQuery = "SELECT 
                    COUNT(DISTINCT user_id) as active_wallets,
                    COALESCE(SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END), 0) as total_deposits,
                    COALESCE(SUM(CASE WHEN transaction_type = 'withdrawal' THEN amount ELSE 0 END), 0) as total_withdrawals,
                    COALESCE(SUM(CASE WHEN transaction_type = 'ride_payment' THEN amount ELSE 0 END), 0) as total_payments
                   FROM wallet_transactions WHERE status = 'completed'";
    
    $statsResult = $conn->query($statsQuery);
    $stats = $statsResult->fetch_assoc();

    // Get pending withdrawals
    $withdrawalsQuery = "SELECT 
                            dw.*,
                            u.full_name as driver_name,
                            u.email as driver_email,
                            u.phone_number as driver_phone,
                            dp.id as driver_profile_id
                          FROM driver_withdrawals dw
                          JOIN driver_profiles dp ON dw.driver_id = dp.id
                          JOIN users u ON dp.user_id = u.id
                          WHERE dw.status = 'pending'
                          ORDER BY dw.created_at DESC";
    
    $withdrawalsResult = $conn->query($withdrawalsQuery);
    
    $withdrawals = [];
    while ($row = $withdrawalsResult->fetch_assoc()) {
        $withdrawals[] = [
            'id' => $row['id'],
            'driver_name' => $row['driver_name'],
            'driver_email' => $row['driver_email'],
            'driver_phone' => $row['driver_phone'],
            'amount' => floatval($row['amount']),
            'bank_name' => $row['bank_name'],
            'account_number' => substr($row['account_number'], -4),
            'account_name' => $row['account_name'],
            'status' => $row['status'],
            'created_at' => $row['created_at'],
            'formatted_date' => date('M d, Y', strtotime($row['created_at']))
        ];
    }

    // Get recent wallet transactions
    $transactionsQuery = "SELECT 
                            wt.*,
                            u.full_name as user_name,
                            u.role as user_role,
                            r.ride_number
                          FROM wallet_transactions wt
                          JOIN users u ON wt.user_id = u.id
                          LEFT JOIN rides r ON wt.ride_id = r.id
                          WHERE wt.status = 'completed'
                          ORDER BY wt.created_at DESC
                          LIMIT 50";
    
    $transactionsResult = $conn->query($transactionsQuery);
    
    $transactions = [];
    while ($row = $transactionsResult->fetch_assoc()) {
        $transactions[] = [
            'id' => $row['id'],
            'user_name' => $row['user_name'],
            'user_role' => $row['user_role'],
            'type' => $row['transaction_type'],
            'amount' => floatval($row['amount']),
            'balance_before' => floatval($row['balance_before']),
            'balance_after' => floatval($row['balance_after']),
            'reference' => $row['reference'],
            'description' => $row['description'],
            'ride_number' => $row['ride_number'],
            'created_at' => $row['created_at'],
            'formatted_date' => date('M d, Y h:i A', strtotime($row['created_at']))
        ];
    }

    echo json_encode([
        'success' => true,
        'statistics' => [
            'active_wallets' => (int)$stats['active_wallets'],
            'total_deposits' => floatval($stats['total_deposits']),
            'total_withdrawals' => floatval($stats['total_withdrawals']),
            'total_payments' => floatval($stats['total_payments']),
            'net_balance' => floatval($stats['total_deposits'] - $stats['total_withdrawals'])
        ],
        'pending_withdrawals' => $withdrawals,
        'recent_transactions' => $transactions
    ]);

} catch (Exception $e) {
    error_log("Error in get_wallets.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load wallets: ' . $e->getMessage()
    ]);
}
?>