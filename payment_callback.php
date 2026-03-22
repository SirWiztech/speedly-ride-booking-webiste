<?php
// payment_callback.php - Instant redirect after KoraPay payment
session_start();

require_once 'SERVER/API/db-connect.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: form.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$payment_session_id = $_GET['session'] ?? '';
$reference = $_GET['reference'] ?? '';

if (empty($payment_session_id) || empty($reference)) {
    // No session data, redirect to wallet
    header("Location: wallet.php");
    exit;
}

// Verify payment session from database
$query = "SELECT pgt.id, pgt.amount, pgt.status, pgt.transaction_reference
          FROM payment_gateway_transactions pgt
          INNER JOIN payment_sessions ps ON pgt.transaction_reference = ps.transaction_reference
          WHERE ps.session_id = ? AND pgt.user_id = ? AND pgt.transaction_reference = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("sss", $payment_session_id, $user_id, $reference);
$stmt->execute();
$result = $stmt->get_result();
$transaction = $result->fetch_assoc();

if (!$transaction) {
    // Transaction not found or invalid session
    header("Location: wallet.php");
    exit;
}

// If webhook already processed (status = success), redirect directly to wallet with success
if ($transaction['status'] === 'success') {
    header("Location: wallet.php?payment_status=completed&reference=" . $reference);
    exit;
}

// If status is pending, show processing page with fast polling
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Processing Payment - Speedly</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .processing-container {
            background: white;
            border-radius: 30px;
            padding: 50px;
            text-align: center;
            max-width: 450px;
            width: 90%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            animation: fadeIn 0.3s ease-out;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .spinner {
            width: 60px;
            height: 60px;
            margin: 0 auto 25px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid #ff5e00;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .success-icon {
            width: 60px;
            height: 60px;
            background: #4CAF50;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            animation: popIn 0.3s ease-out;
        }
        
        .success-icon i {
            font-size: 30px;
            color: white;
        }
        
        @keyframes popIn {
            0% {
                transform: scale(0);
            }
            80% {
                transform: scale(1.1);
            }
            100% {
                transform: scale(1);
            }
        }
        
        .hidden {
            display: none;
        }
        
        h2 {
            color: #333;
            font-size: 22px;
            margin-bottom: 8px;
        }
        
        .amount {
            font-size: 28px;
            font-weight: bold;
            color: #ff5e00;
            margin: 12px 0;
        }
        
        .status-text {
            color: #666;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .reference {
            font-size: 11px;
            color: #999;
            margin-top: 15px;
            word-break: break-all;
        }
        
        .error-icon {
            width: 60px;
            height: 60px;
            background: #f44336;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        
        .error-icon i {
            font-size: 30px;
            color: white;
        }
    </style>
</head>
<body>
    <div class="processing-container" id="processingState">
        <div class="spinner"></div>
        <h2>Processing Payment</h2>
        <div class="amount">₦<?php echo number_format($transaction['amount'], 2); ?></div>
        <p class="status-text">Verifying your payment...</p>
        <div class="reference">Ref: <?php echo htmlspecialchars(substr($reference, 0, 20)); ?>...</div>
    </div>
    
    <div class="processing-container hidden" id="successState">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        <h2>Payment Successful!</h2>
        <div class="amount" id="successAmount">₦<?php echo number_format($transaction['amount'], 2); ?></div>
        <p class="status-text">Redirecting to wallet...</p>
    </div>
    
    <div class="processing-container hidden" id="errorState">
        <div class="error-icon">
            <i class="fas fa-times"></i>
        </div>
        <h2>Payment Failed</h2>
        <p class="status-text" id="errorMessage">Your payment could not be processed</p>
        <button onclick="window.location.href='wallet.php'">Back to Wallet</button>
    </div>
    
    <script>
        const reference = '<?php echo $reference; ?>';
        let attempts = 0;
        let maxAttempts = 20; // 20 attempts = 10 seconds max
        let interval;
        
        // Function to check transaction status
        function checkTransactionStatus() {
            attempts++;
            
            fetch('SERVER/API/check_transaction_status.php?reference=' + encodeURIComponent(reference))
                .then(response => response.json())
                .then(data => {
                    console.log('Status check ' + attempts + ':', data.status);
                    
                    if (data.status === 'success') {
                        // Transaction completed - redirect IMMEDIATELY
                        clearInterval(interval);
                        
                        // Show success briefly then redirect
                        document.getElementById('processingState').classList.add('hidden');
                        const successState = document.getElementById('successState');
                        successState.classList.remove('hidden');
                        
                        // Update success amount if available
                        if (data.amount) {
                            document.getElementById('successAmount').innerHTML = '₦' + data.amount.toLocaleString();
                        }
                        
                        // Redirect to wallet after 500ms
                        setTimeout(() => {
                            window.location.href = 'wallet.php?payment_status=completed&reference=' + reference;
                        }, 500);
                        
                    } else if (data.status === 'failed') {
                        // Transaction failed
                        clearInterval(interval);
                        document.getElementById('processingState').classList.add('hidden');
                        const errorState = document.getElementById('errorState');
                        errorState.classList.remove('hidden');
                        
                        if (data.failure_reason) {
                            document.getElementById('errorMessage').innerText = data.failure_reason;
                        }
                        
                    } else if (data.status === 'expired') {
                        // Transaction expired
                        clearInterval(interval);
                        document.getElementById('processingState').classList.add('hidden');
                        document.getElementById('errorState').classList.remove('hidden');
                        document.getElementById('errorMessage').innerText = 'Payment session expired. Please try again.';
                        
                    } else if (attempts >= maxAttempts) {
                        // Timeout - redirect to wallet
                        clearInterval(interval);
                        window.location.href = 'wallet.php';
                    }
                })
                .catch(error => {
                    console.error('Error checking status:', error);
                    
                    if (attempts >= maxAttempts) {
                        clearInterval(interval);
                        window.location.href = 'wallet.php';
                    }
                });
        }
        
        // Start polling every 500ms (faster response)
        interval = setInterval(checkTransactionStatus, 500);
        
        // Check immediately
        setTimeout(checkTransactionStatus, 100);
        
        // Fallback: redirect after 15 seconds if still waiting
        setTimeout(() => {
            if (document.getElementById('processingState') && 
                !document.getElementById('processingState').classList.contains('hidden')) {
                clearInterval(interval);
                window.location.href = 'wallet.php';
            }
        }, 15000);
    </script>
</body>
</html>