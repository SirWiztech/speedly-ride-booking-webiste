<?php
header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request
error_log("=== Update Password Request Started ===");
error_log("Request method: " . $_SERVER["REQUEST_METHOD"]);

// Include database connection
require_once __DIR__ . '/db-connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? $_POST['new_password'] ?? '';
    
    error_log("Token received: " . ($token ? 'yes' : 'no'));
    error_log("Password received: " . ($password ? 'yes' : 'no'));
    
    // Validate inputs
    if (empty($token)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Reset token is required'
        ]);
        exit;
    }
    
    if (empty($password)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'New password is required'
        ]);
        exit;
    }
    
    if (strlen($password) < 8) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Password must be at least 8 characters'
        ]);
        exit;
    }
    
    try {
        // First, check if the token exists and is valid
        $stmt = $conn->prepare("SELECT user_id, expires_at FROM password_resets WHERE token = ? AND used_at IS NULL");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();
        
        error_log("Token query executed, rows found: " . $result->num_rows);
        
        if ($result->num_rows === 0) {
            // Check if token exists but is expired
            $expiredStmt = $conn->prepare("SELECT user_id FROM password_resets WHERE token = ? AND expires_at <= NOW()");
            $expiredStmt->bind_param("s", $token);
            $expiredStmt->execute();
            $expiredResult = $expiredStmt->get_result();
            
            if ($expiredResult->num_rows > 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Reset link has expired. Please request a new one.'
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Invalid reset link. Please request a new one.'
                ]);
            }
            exit;
        }
        
        $resetData = $result->fetch_assoc();
        $user_id = $resetData['user_id'];
        
        // Check if token is expired
        $expiryTime = strtotime($resetData['expires_at']);
        $currentTime = time();
        
        if ($currentTime > $expiryTime) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Reset link has expired. Please request a new one.'
            ]);
            exit;
        }
        
        error_log("Token valid for user: " . $user_id);
        
        // Hash new password
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
        
        // Update password
        $updateStmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
        $updateStmt->bind_param("ss", $hashedPassword, $user_id);
        
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update password: " . $updateStmt->error);
        }
        
        error_log("Password updated successfully for user: " . $user_id);
        
        // Mark token as used
        $useStmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE token = ?");
        $useStmt->bind_param("s", $token);
        $useStmt->execute();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Your password has been updated successfully!'
        ]);
        
    } catch (Exception $e) {
        error_log("Update password error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update password: ' . $e->getMessage()
        ]);
    }
    
    if (isset($stmt)) $stmt->close();
    if (isset($updateStmt)) $updateStmt->close();
    if (isset($useStmt)) $useStmt->close();
    $conn->close();
    
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}

error_log("=== Update Password Request Completed ===");
?>