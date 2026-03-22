<?php
// test_api.php - Test the KoraPay payment initiation API
// Place this in your root directory: C:\wamp64\www\SPEEDLY\test_api.php

session_start();
require_once 'SERVER/API/db-connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo "<h2 style='color:red'>Not logged in!</h2>";
    echo "<p>Please <a href='form.php'>login first</a> to test the API.</p>";
    
    // Try to get a test user for debugging
    $testUserQuery = "SELECT id, full_name, email FROM users WHERE role = 'client' LIMIT 1";
    $testUserResult = $conn->query($testUserQuery);
    if ($testUser = $testUserResult->fetch_assoc()) {
        echo "<p>Alternatively, you can use this test user:</p>";
        echo "<pre>";
        echo "User ID: " . $testUser['id'] . "\n";
        echo "Name: " . $testUser['full_name'] . "\n";
        echo "Email: " . $testUser['email'] . "\n";
        echo "</pre>";
        echo "<p>You need to log in as this user to test.</p>";
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'];
$user_role = $_SESSION['role'] ?? 'client';

echo "<!DOCTYPE html>
<html>
<head>
    <title>KoraPay API Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 900px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #ff5e00; }
        h2 { color: #333; margin-top: 20px; }
        .success { color: green; background: #e8f5e9; padding: 10px; border-radius: 5px; }
        .error { color: red; background: #ffebee; padding: 10px; border-radius: 5px; }
        pre { background: #f5f5f5; padding: 10px; border-radius: 5px; overflow-x: auto; }
        button { background: #ff5e00; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer; font-size: 16px; margin-top: 10px; }
        button:hover { background: #e05500; }
        .info { background: #e3f2fd; padding: 10px; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🔧 KoraPay API Test</h1>
    
    <div class='info'>
        <strong>Session Info:</strong><br>
        User ID: $user_id<br>
        User Name: $user_name<br>
        User Role: $user_role
    </div>";

// Get user email
$userQuery = "SELECT email FROM users WHERE id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("s", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$user = $userResult->fetch_assoc();

echo "<div class='info'>";
echo "<strong>User Email:</strong> " . ($user['email'] ?? '<span style="color:red">NOT FOUND! User has no email.</span>') . "<br>";
echo "</div>";

// Check if email exists
if (empty($user['email'])) {
    echo "<div class='error'>❌ ERROR: User has no email address. Please update the user record.</div>";
    echo "<p>Run this SQL to fix:</p>";
    echo "<pre>UPDATE users SET email = 'test" . time() . "@example.com' WHERE id = '$user_id';</pre>";
    exit;
}

// Handle manual test submission
$amount = isset($_POST['amount']) ? floatval($_POST['amount']) : 0;
$testMode = isset($_POST['test_mode']) ? $_POST['test_mode'] : 'api';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $amount > 0) {
    echo "<h2>📡 Testing Payment Initiation</h2>";
    
    if ($testMode === 'api') {
        // Test the actual API
        $reference = 'TEST-' . strtoupper(uniqid()) . '-' . date('YmdHis');
        $tunnelUrl = 'https://best-temp-appearing-synthetic.trycloudflare.com';
        
        $payload = [
            'amount' => $amount,
            'currency' => 'NGN',
            'reference' => $reference,
            'redirect_url' => $tunnelUrl . '/SPEEDLY/payment_processing.php?reference=' . $reference . '&amount=' . $amount,
            'notification_url' => $tunnelUrl . '/SPEEDLY/SERVER/API/korapay_webhook.php',
            'customer' => [
                'name' => $user_name,
                'email' => $user['email']
            ],
            'merchant_bears_cost' => true
        ];
        
        echo "<p><strong>Sending to KoraPay API:</strong></p>";
        echo "<pre>" . json_encode($payload, JSON_PRETTY_PRINT) . "</pre>";
        
        // Initialize KoraPay config
        require_once 'SERVER/API/korapay_config.php';
        $korapay = new KoraPayConfig($conn);
        $baseUrl = $korapay->getBaseUrl();
        $headers = $korapay->getHeaders(true);
        
        $ch = curl_init($baseUrl . '/charges/initialize');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        echo "<p><strong>HTTP Code:</strong> $httpCode</p>";
        if ($curlError) {
            echo "<div class='error'><strong>cURL Error:</strong> $curlError</div>";
        }
        
        $responseData = json_decode($response, true);
        echo "<p><strong>Response:</strong></p>";
        echo "<pre>" . htmlspecialchars(json_encode($responseData, JSON_PRETTY_PRINT)) . "</pre>";
        
        if ($httpCode == 200 && isset($responseData['status']) && $responseData['status'] === true) {
            echo "<div class='success'>✅ Payment initiated successfully!</div>";
            echo "<p><strong>Checkout URL:</strong> <a href='" . $responseData['data']['checkout_url'] . "' target='_blank'>" . $responseData['data']['checkout_url'] . "</a></p>";
            
            // Store in database
            $insertQuery = "INSERT INTO payment_gateway_transactions 
                            (id, user_id, transaction_reference, gateway_reference, amount, currency, status, payment_method, created_at) 
                            VALUES (UUID(), ?, ?, ?, ?, 'NGN', 'pending', 'korapay', NOW())";
            $insertStmt = $conn->prepare($insertQuery);
            $insertStmt->bind_param("sssd", $user_id, $reference, $responseData['data']['reference'], $amount);
            $insertStmt->execute();
            
            echo "<p><strong>Transaction stored with reference:</strong> $reference</p>";
            echo "<a href='" . $responseData['data']['checkout_url'] . "' class='button' target='_blank' style='display:inline-block; background:#ff5e00; color:white; padding:10px 20px; text-decoration:none; border-radius:5px; margin-top:10px;'>Proceed to Payment →</a>";
        } else {
            echo "<div class='error'>❌ Payment initiation failed: " . ($responseData['message'] ?? 'Unknown error') . "</div>";
        }
    } else {
        // Direct wallet update test
        echo "<div class='info'>Testing direct wallet update...</div>";
        
        $currentBalance = getWalletBalance($conn, $user_id);
        $newBalance = $currentBalance + $amount;
        
        $insertQuery = "INSERT INTO wallet_transactions 
                        (id, user_id, transaction_type, amount, balance_before, balance_after, reference, status, description, created_at) 
                        VALUES (UUID(), ?, 'deposit', ?, ?, ?, 'TEST-DIRECT-" . time() . "', 'completed', 'Direct test deposit', NOW())";
        $insertStmt = $conn->prepare($insertQuery);
        $insertStmt->bind_param("sdd", $user_id, $amount, $currentBalance, $newBalance);
        
        if ($insertStmt->execute()) {
            echo "<div class='success'>✅ Wallet updated directly! Added ₦" . number_format($amount, 2) . "</div>";
            echo "<p>New balance: ₦" . number_format($newBalance, 2) . "</p>";
        } else {
            echo "<div class='error'>❌ Failed to update wallet: " . $conn->error . "</div>";
        }
    }
}

// Get current wallet balance
function getWalletBalance($conn, $user_id) {
    $balanceQuery = "SELECT 
        COALESCE(SUM(CASE WHEN transaction_type IN ('deposit', 'bonus', 'referral', 'ride_refund') THEN amount ELSE 0 END), 0) - 
        COALESCE(SUM(CASE WHEN transaction_type IN ('withdrawal', 'ride_payment') THEN amount ELSE 0 END), 0) as balance 
        FROM wallet_transactions WHERE user_id = ? AND status = 'completed'";
    $balanceStmt = $conn->prepare($balanceQuery);
    $balanceStmt->bind_param("s", $user_id);
    $balanceStmt->execute();
    $result = $balanceStmt->get_result();
    $data = $result->fetch_assoc();
    return $data['balance'] ?? 0;
}

$currentBalance = getWalletBalance($conn, $user_id);

echo "<h2>💰 Current Wallet Balance</h2>";
echo "<div class='info' style='font-size:24px; font-weight:bold; color:#ff5e00;'>₦" . number_format($currentBalance, 2) . "</div>";

echo "<h2>🧪 Test Payment Initiation</h2>";
echo "<form method='POST'>";
echo "<label>Amount (₦):</label>";
echo "<input type='number' name='amount' value='500' min='100' step='100' required style='padding:8px; margin:10px 0; width:200px;'>";
echo "<br>";
echo "<label>Test Mode:</label><br>";
echo "<input type='radio' name='test_mode' value='api' checked> Test Real API (KoraPay)<br>";
echo "<input type='radio' name='test_mode' value='direct'> Test Direct Wallet Update<br>";
echo "<button type='submit'>Test Payment</button>";
echo "</form>";

// Check if payment_gateway_transactions table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'payment_gateway_transactions'");
if ($tableCheck->num_rows == 0) {
    echo "<div class='error'>⚠️ Warning: payment_gateway_transactions table does not exist!</div>";
}

echo "<h2>📋 Recent Transactions</h2>";
$recentQuery = "SELECT * FROM payment_gateway_transactions WHERE user_id = ? ORDER BY created_at DESC LIMIT 5";
$recentStmt = $conn->prepare($recentQuery);
$recentStmt->bind_param("s", $user_id);
$recentStmt->execute();
$recentResult = $recentStmt->get_result();

if ($recentResult->num_rows > 0) {
    echo "<table border='1' cellpadding='8' style='width:100%; border-collapse: collapse;'>";
    echo "<tr style='background:#f5f5f5;'><th>Reference</th><th>Amount</th><th>Status</th><th>Created</th></tr>";
    while ($row = $recentResult->fetch_assoc()) {
        $statusColor = $row['status'] == 'success' ? 'green' : ($row['status'] == 'pending' ? 'orange' : 'red');
        echo "<tr>";
        echo "<td>" . substr($row['transaction_reference'], 0, 20) . "...</td>";
        echo "<td>₦" . number_format($row['amount'], 2) . "</td>";
        echo "<td style='color:$statusColor; font-weight:bold;'>" . strtoupper($row['status']) . "</td>";
        echo "<td>" . $row['created_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No recent transactions.</p>";
}

echo "<h2>🔧 Quick Fixes</h2>";
echo "<ul>";
echo "<li><a href='wallet.php'>Go to Wallet</a></li>";
echo "<li><a href='client_dashboard.php'>Go to Dashboard</a></li>";
echo "<li><a href='SERVER/API/korapay_webhook.php'>Test Webhook Endpoint</a></li>";
echo "</ul>";

echo "</div></body></html>";
?>