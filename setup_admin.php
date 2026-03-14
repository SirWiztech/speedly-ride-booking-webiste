<?php
session_start();
require_once 'SERVER/API/db-connect.php';

// Admin credentials
$email = 'edgematrix2026@gmail.com';
$username = 'edgematrix';
$password = 'edgematrix2026';
$hash = '$2y$10$scIaKbk.CrOy/3EgmiVbQO/.h5mCSgtOpJGTKdGt8BiRWdXdc.ZCq';
$full_name = 'Edge Matrix Admin';
$phone = '+2348000000000';

echo "<!DOCTYPE html>
<html>
<head>
    <title>Admin Setup</title>
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .container { background: white; border-radius: 20px; padding: 40px; max-width: 600px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); }
        h1 { color: #333; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin: 20px 0; }
        .info { background: #e7f3ff; color: #004085; padding: 15px; border-radius: 10px; margin: 20px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 15px; border-radius: 10px; margin: 20px 0; }
        .btn { background: #ff5e00; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; display: inline-block; margin-top: 20px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 10px; overflow-x: auto; }
    </style>
</head>
<body>
    <div class='container'>";

// Check if user already exists
$check = $conn->prepare("SELECT id, role FROM users WHERE email = ? OR username = ?");
$check->bind_param("ss", $email, $username);
$check->execute();
$result = $check->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    echo "<h1>⚠️ User Already Exists</h1>";
    echo "<div class='warning'>";
    echo "User found with ID: " . $user['id'] . "<br>";
    echo "Current role: " . $user['role'] . "<br>";
    echo "</div>";
    
    // Update to admin role
    $update = $conn->prepare("UPDATE users SET role = 'admin', password_hash = ?, is_verified = 1, is_active = 1 WHERE id = ?");
    $update->bind_param("ss", $hash, $user['id']);
    
    if ($update->execute()) {
        echo "<div class='success'>";
        echo "✅ User updated to ADMIN successfully!<br>";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "❌ Failed to update: " . $update->error . "<br>";
        echo "</div>";
    }
} else {
    // Create new admin user
    $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    
    $insert = $conn->prepare("INSERT INTO users (id, username, email, password_hash, phone_number, full_name, role, is_verified, is_active) VALUES (?, ?, ?, ?, ?, ?, 'admin', 1, 1)");
    $insert->bind_param("ssssss", $id, $username, $email, $hash, $phone, $full_name);
    
    if ($insert->execute()) {
        echo "<div class='success'>";
        echo "✅ Admin user created successfully!<br>";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "❌ Failed to create: " . $insert->error . "<br>";
        echo "</div>";
    }
}

// Verify the admin user
$verify = $conn->prepare("SELECT id, username, email, role FROM users WHERE email = ?");
$verify->bind_param("s", $email);
$verify->execute();
$verifyResult = $verify->get_result();

if ($verifyResult->num_rows > 0) {
    $admin = $verifyResult->fetch_assoc();
    
    echo "<h2>✅ Verification Successful</h2>";
    echo "<div class='info'>";
    echo "<strong>ID:</strong> " . $admin['id'] . "<br>";
    echo "<strong>Username:</strong> " . $admin['username'] . "<br>";
    echo "<strong>Email:</strong> " . $admin['email'] . "<br>";
    echo "<strong>Role:</strong> " . $admin['role'] . "<br>";
    echo "</div>";
    
    echo "<h3>Login Credentials</h3>";
    echo "<pre>";
    echo "Email:    edgematrix2026@gmail.com\n";
    echo "Password: edgematrix2026\n";
    echo "Role:     admin\n";
    echo "</pre>";
    
    // Test password verification
    $testHash = $conn->prepare("SELECT password_hash FROM users WHERE id = ?");
    $testHash->bind_param("s", $admin['id']);
    $testHash->execute();
    $hashResult = $testHash->get_result();
    $hashData = $hashResult->fetch_assoc();
    
    if (password_verify('edgematrix2026', $hashData['password_hash'])) {
        echo "<div class='success'>✅ Password verification test PASSED!</div>";
    } else {
        echo "<div class='warning'>❌ Password verification test FAILED!</div>";
    }
} else {
    echo "<div class='warning'>❌ Verification failed - user not found!</div>";
}

echo "<br><a href='admin_login.php' class='btn'>Go to Admin Login →</a>";
echo "</div></body></html>";
?>   