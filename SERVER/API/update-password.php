<?php
header('Content-Type: application/json');

// Include database connection
require_once __DIR__ . '/db-connect.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the request
error_log("=== Update Password Request Started ===");
error_log("Request method: " . $_SERVER["REQUEST_METHOD"]);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Check if this is from settings page (with session) or reset password (with token)
    session_start();
    
    $token = $_POST['token'] ?? '';
    $current_password = $_POST['current_password'] ?? '';
    $password = $_POST['password'] ?? $_POST['new_password'] ?? '';
    
    error_log("Token present: " . (!empty($token) ? 'yes' : 'no'));
    error_log("Current password present: " . (!empty($current_password) ? 'yes' : 'no'));
    
    // Validate inputs
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
        // CASE 1: Password reset via token (from email)
        if (!empty($token)) {
            error_log("Processing password reset via token");
            
            // Find user with valid token
            $stmt = $conn->prepare("SELECT id, email FROM users WHERE reset_token = ? AND reset_expires > NOW()");
            $stmt->bind_param("s", $token);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                // Check if token exists but expired
                $expiredStmt = $conn->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_expires <= NOW()");
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
            
            $user = $result->fetch_assoc();
            
            // Hash new password
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            // Update password and clear reset token
            $updateStmt = $conn->prepare("UPDATE users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
            $updateStmt->bind_param("ss", $hashedPassword, $user['id']);
            
            if ($updateStmt->execute()) {
                error_log("Password updated successfully for user: " . $user['id']);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Your password has been updated successfully!'
                ]);
            } else {
                throw new Exception("Failed to update password");
            }
        }
        
        // CASE 2: Password change from settings page (logged in)
        else if (!empty($current_password) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
            error_log("Processing password change from settings");
            
            $user_id = $_SESSION['user_id'];
            
            // Verify current password
            $stmt = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->bind_param("s", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'User not found'
                ]);
                exit;
            }
            
            $user = $result->fetch_assoc();
            
            // Verify current password
            if (!password_verify($current_password, $user['password_hash'])) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Current password is incorrect'
                ]);
                exit;
            }
            
            // Hash new password
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            // Update password
            $updateStmt = $conn->prepare("UPDATE users SET password_hash = ? WHERE id = ?");
            $updateStmt->bind_param("ss", $hashedPassword, $user_id);
            
            if ($updateStmt->execute()) {
                error_log("Password changed successfully for user: " . $user_id);
                
                // Log the activity
                $logId = bin2hex(random_bytes(16));
                $logStmt = $conn->prepare("INSERT INTO admin_activity_logs (id, admin_id, action, entity_type, entity_id, created_at) VALUES (?, ?, 'password_change', 'user', ?, NOW())");
                $logStmt->bind_param("sss", $logId, $user_id, $user_id);
                $logStmt->execute();
                
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Your password has been changed successfully!'
                ]);
            } else {
                throw new Exception("Failed to update password");
            }
        }
        
        // CASE 3: Invalid request
        else {
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid request. Please provide either a reset token or current password.'
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Update password error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to update password. Please try again.'
        ]);
    }
    
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}

error_log("=== Update Password Request Completed ===");
?>