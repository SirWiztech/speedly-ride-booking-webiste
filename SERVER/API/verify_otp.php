<?php
header('Content-Type: application/json');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database connection - FIXED PATH
require_once __DIR__ . '/db-connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = $_POST['email'] ?? '';
    $otp = $_POST['otp'] ?? '';
    
    // Validate inputs
    if (empty($email) || empty($otp)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Email and OTP are required'
        ]);
        exit;
    }
    
    try {
        // Get user by email
        $userStmt = $conn->prepare("SELECT id, full_name, role FROM users WHERE email = ?");
        $userStmt->bind_param("s", $email);
        $userStmt->execute();
        $userResult = $userStmt->get_result();
        
        if ($userResult->num_rows === 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'User not found'
            ]);
            exit;
        }
        
        $user = $userResult->fetch_assoc();
        $userId = $user['id'];
        
        // Check if OTP exists and is valid
        $otpStmt = $conn->prepare("
            SELECT id, expires_at 
            FROM password_resets 
            WHERE user_id = ? AND token = ? AND used_at IS NULL 
            ORDER BY created_at DESC LIMIT 1
        ");
        $otpStmt->bind_param("ss", $userId, $otp);
        $otpStmt->execute();
        $otpResult = $otpStmt->get_result();
        
        if ($otpResult->num_rows === 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid OTP code'
            ]);
            exit;
        }
        
        $otpData = $otpResult->fetch_assoc();
        
        // Check if OTP is expired
        $expiryTime = strtotime($otpData['expires_at']);
        $currentTime = time();
        
        if ($currentTime > $expiryTime) {
            echo json_encode([
                'status' => 'error',
                'message' => 'OTP has expired. Please request a new one.'
            ]);
            exit;
        }
        
        // Mark OTP as used
        $updateStmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?");
        $updateStmt->bind_param("s", $otpData['id']);
        $updateStmt->execute();
        
        // Mark user as verified
        $verifyStmt = $conn->prepare("UPDATE users SET is_verified = 1, email_verified_at = NOW() WHERE id = ?");
        $verifyStmt->bind_param("s", $userId);
        $verifyStmt->execute();
        
        // Set session variables
        $_SESSION['user_id'] = $userId;
        $_SESSION['email'] = $email;
        $_SESSION['fullname'] = $user['full_name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['logged_in'] = true;
        $_SESSION['verified'] = true;
        
        // Determine dashboard redirect
        if ($user['role'] == 'driver') {
            $dashboard = '/SPEEDLY/driver_dashboard.php';
        } else {
            $dashboard = '/SPEEDLY/client_dashboard.php';
        }
        
        echo json_encode([
            'status' => 'success',
            'message' => 'OTP verified successfully!',
            'redirect' => $dashboard,
            'user' => [
                'id' => $userId,
                'name' => $user['full_name'],
                'role' => $user['role']
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("OTP Verification error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Verification failed: ' . $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?>