<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/db-connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        echo json_encode(['status' => 'error', 'message' => 'Username and password required']);
        exit;
    }
    
    try {
        // Check if user exists with admin role
        $stmt = $conn->prepare("SELECT id, username, full_name, password_hash, role FROM users WHERE (username = ? OR email = ?) AND role = 'admin'");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
            exit;
        }
        
        $admin = $result->fetch_assoc();
        
        // Verify password
        if (password_verify($password, $admin['password_hash'])) {
            // Set session
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['admin_username'] = $admin['username'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_logged_in'] = true;
            
            // Update last login
            $updateStmt = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
            $updateStmt->bind_param("s", $admin['id']);
            $updateStmt->execute();
            
            // Log activity
            logAdminActivity($conn, $admin['id'], 'login', 'Admin logged in');
            
            echo json_encode([
                'status' => 'success',
                'message' => 'Login successful',
                'admin' => [
                    'id' => $admin['id'],
                    'name' => $admin['full_name'],
                    'username' => $admin['username']
                ]
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
        }
        
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Login failed: ' . $e->getMessage()]);
    }
}

function logAdminActivity($conn, $adminId, $action, $details) {
    $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    
    $stmt = $conn->prepare("INSERT INTO admin_activity_logs (id, admin_id, action, entity_type, ip_address, created_at) VALUES (?, ?, ?, 'system', ?, NOW())");
    $stmt->bind_param("ssss", $id, $adminId, $action, $ip);
    $stmt->execute();
}
?>