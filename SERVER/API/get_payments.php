<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/db-connect.php';

// Enable error logging
error_log("=== Get Payments Request Started ===");

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Get payment statistics
    $statsQuery = "SELECT 
                    COUNT(*) as total_transactions,
                    COALESCE(SUM(CASE WHEN status = 'success' THEN amount ELSE 0 END), 0) as total_revenue,
                    COALESCE(SUM(CASE WHEN status = 'pending' THEN amount ELSE 0 END), 0) as pending_amount,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count
                   FROM payment_transactions";
    
    $statsResult = $conn->query($statsQuery);
    $stats = $statsResult->fetch_assoc();

    // Get recent payments with user details
    $paymentsQuery = "SELECT 
                        pt.*,
                        u.full_name as user_name,
                        u.email as user_email,
                        r.ride_number,
                        r.pickup_address,
                        r.destination_address
                      FROM payment_transactions pt
                      LEFT JOIN users u ON pt.user_id = u.id
                      LEFT JOIN rides r ON pt.ride_id = r.id
                      ORDER BY pt.created_at DESC
                      LIMIT 50";
    
    $paymentsResult = $conn->query($paymentsQuery);
    
    $payments = [];
    while ($row = $paymentsResult->fetch_assoc()) {
        $payments[] = [
            'id' => $row['id'],
            'reference' => $row['transaction_reference'],
            'user_name' => $row['user_name'] ?? 'Unknown',
            'user_email' => $row['user_email'] ?? '',
            'amount' => floatval($row['amount']),
            'commission' => floatval($row['commission'] ?? 0),
            'currency' => $row['currency'] ?? 'NGN',
            'payment_method' => $row['payment_method'] ?? 'card',
            'status' => $row['status'],
            'ride_number' => $row['ride_number'] ?? null,
            'pickup' => $row['pickup_address'] ?? null,
            'destination' => $row['destination_address'] ?? null,
            'created_at' => $row['created_at'],
            'formatted_date' => date('M d, Y h:i A', strtotime($row['created_at']))
        ];
    }

    echo json_encode([
        'success' => true,
        'statistics' => [
            'total_transactions' => (int)$stats['total_transactions'],
            'total_revenue' => floatval($stats['total_revenue']),
            'pending_amount' => floatval($stats['pending_amount']),
            'pending_count' => (int)$stats['pending_count']
        ],
        'payments' => $payments
    ]);

} catch (Exception $e) {
    error_log("Error in get_payments.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load payments: ' . $e->getMessage()
    ]);
}
?>