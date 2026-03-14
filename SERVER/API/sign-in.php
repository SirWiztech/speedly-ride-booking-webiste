<?php
// Start session at the beginning
session_start();

header('Content-Type: application/json');

include 'db-connect.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    error_log("Login attempt for: " . $username);
    
    if (empty($username) || empty($password)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Username and password are required!'
        ]);
        exit;
    }
    
    try {
        // Find user - note: password_hash is the column name
        $sql = "SELECT * FROM users WHERE username = ? OR email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'User not found!'
            ]);
            exit;
        }
        
        $user = $result->fetch_assoc();
        
        // Check if user is active
        if (isset($user['is_active']) && $user['is_active'] == 0) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Your account has been deactivated. Please contact support.'
            ]);
            exit;
        }
        
        // ✅ Verify bcrypt password
        if (password_verify($password, $user['password_hash'])) {
            
            // Login successful - regenerate session ID for security
            session_regenerate_id(true);
            
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['fullname'] = $user['full_name'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Update last login
            $update_sql = "UPDATE users SET last_login = NOW() WHERE id = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("s", $user['id']);
            $update_stmt->execute();
            $update_stmt->close();
            
            // Determine redirect - CORRECTED PATHS (files are in root directory)
            if ($user['role'] == 'admin') {
                $redirect = 'admin_dashboard.php';  // File in root
            } elseif ($user['role'] == 'driver') {
                $redirect = 'driver_dashboard.php';  // File in root
            } else {
                $redirect = 'client_dashboard.php';  // File in root
            }
            
            // Optional: Store user role in cookie for frontend (not sensitive)
            setcookie('user_role', $user['role'], time() + 86400, '/', '', false, true); // 24 hours, httponly
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Welcome back, ' . htmlspecialchars($user['full_name']) . '!',
                'redirect' => $redirect,
                'user' => [
                    'id' => $user['id'],
                    'name' => $user['full_name'],
                    'role' => $user['role'],
                    'email' => $user['email']
                ]
            ]);
            
        } else {
            error_log("Password verification failed for: " . $username);
            echo json_encode([
                'status' => 'error',
                'message' => 'Incorrect password!'
            ]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Login failed. Please try again.'
        ]);
    }
    
    $conn->close();
    
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?>