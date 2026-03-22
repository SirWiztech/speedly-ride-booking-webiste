<?php
// SERVER/API/initiate_korapay_payment.php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/db-connect.php';
require_once __DIR__ . '/korapay_config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'client';

// Get user details
$userQuery = "SELECT full_name, email, phone_number FROM users WHERE id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("s", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$amount = isset($input['amount']) ? floatval($input['amount']) : 0;

// Validate amount
if ($amount < 100) {
    echo json_encode(['success' => false, 'message' => 'Minimum deposit amount is ₦100']);
    exit;
}

// Generate unique reference
$reference = 'SPD-' . strtoupper(uniqid()) . '-' . date('YmdHis');

// Initialize KoraPay
$korapay = new KoraPayConfig($conn);
$baseUrl = $korapay->getBaseUrl();
$headers = $korapay->getHeaders(true);

// YOUR CLOUDFLARE TUNNEL URL - UPDATE WITH CORRECT PATH
$tunnelUrl = 'https://best-temp-appearing-synthetic.trycloudflare.com';

// Redirect based on user role
$redirectPage = ($user_role === 'driver') ? 'driver_dashboard.php' : 'client_dashboard.php';

// Generate a unique session ID for this payment (for faster callback)
$payment_session_id = bin2hex(random_bytes(16));

// Store transaction first
$insertQuery = "INSERT INTO payment_gateway_transactions 
                (id, user_id, transaction_reference, amount, currency, status, payment_method, created_at, expires_at) 
                VALUES (UUID(), ?, ?, ?, 'NGN', 'pending', 'korapay', NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE))";
$insertStmt = $conn->prepare($insertQuery);
$insertStmt->bind_param("ssd", $user_id, $reference, $amount);
$insertStmt->execute();

// Insert payment session for faster callback verification
$sessionQuery = "INSERT INTO payment_sessions (id, transaction_reference, session_id, user_id, expires_at) 
                 VALUES (UUID(), ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 MINUTE))";
$sessionStmt = $conn->prepare($sessionQuery);
$sessionStmt->bind_param("sss", $reference, $payment_session_id, $user_id);
$sessionStmt->execute();

// Prepare KoraPay payload
$payload = [
    'amount' => $amount,
    'currency' => 'NGN',
    'reference' => $reference,
    'redirect_url' => $tunnelUrl . '/SPEEDLY/payment_callback.php?session=' . $payment_session_id . '&reference=' . $reference,
    'notification_url' => $tunnelUrl . '/SPEEDLY/SERVER/API/korapay_webhook.php',
    'customer' => [
        'name' => $user['full_name'],
        'email' => $user['email']
    ],
    'merchant_bears_cost' => true
];

// Initialize cURL for KoraPay
$ch = curl_init($baseUrl . '/charges/initialize');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Parse KoraPay response
$responseData = json_decode($response, true);

if ($httpCode == 200 && isset($responseData['status']) && $responseData['status'] === true) {
    $checkoutUrl = $responseData['data']['checkout_url'];
    $gatewayReference = $responseData['data']['reference'] ?? null;
    
    // Update transaction with gateway reference
    $updateQuery = "UPDATE payment_gateway_transactions 
                    SET gateway_reference = ? 
                    WHERE transaction_reference = ?";
    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param("ss", $gatewayReference, $reference);
    $updateStmt->execute();
    
    echo json_encode([
        'success' => true,
        'checkout_url' => $checkoutUrl,
        'reference' => $reference,
        'message' => 'Payment initiated successfully'
    ]);
} else {
    error_log("KoraPay Initiation Error: " . $response);
    echo json_encode([
        'success' => false,
        'message' => $responseData['message'] ?? 'Failed to initiate payment',
        'error' => $responseData
    ]);
}
?>