<?php
header('Content-Type: application/json');

// Allow CORS for development
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Include database connection
require_once __DIR__ . '/db-connect.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'] ?? '';
    $otp = $_POST['otp'] ?? '';

    if (empty($email) || empty($otp)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Email and OTP are required'
        ]);
        exit;
    }

    // Get user ID from email
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
    $user_id = $user['id'];

    // Verify OTP
    $otpStmt = $conn->prepare("SELECT * FROM password_resets WHERE user_id = ? AND token = ? AND expires_at > NOW() AND used_at IS NULL");
    $otpStmt->bind_param("ss", $user_id, $otp);
    $otpStmt->execute();
    $otpResult = $otpStmt->get_result();

    if ($otpResult->num_rows === 0) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid or expired OTP'
        ]);
        exit;
    }

    // Mark OTP as used
    $updateStmt = $conn->prepare("UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND token = ?");
    $updateStmt->bind_param("ss", $user_id, $otp);
    $updateStmt->execute();

    // Update user verification status
    $verifyStmt = $conn->prepare("UPDATE users SET is_verified = 1, email_verified_at = NOW() WHERE id = ?");
    $verifyStmt->bind_param("s", $user_id);
    $verifyStmt->execute();

    // Start session
    session_start();
    $_SESSION['user_id'] = $user_id;
    $_SESSION['email'] = $email;
    $_SESSION['fullname'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['logged_in'] = true;

    // Determine redirect based on role
    $redirect = ($user['role'] == 'driver') ? 'driver_dashboard.php' : 'client_dashboard.php';

    echo json_encode([
        'status' => 'success',
        'message' => 'OTP verified successfully',
        'user' => [
            'id' => $user_id,
            'name' => $user['full_name'],
            'role' => $user['role']
        ],
        'redirect' => $redirect
    ]);

    $userStmt->close();
    $otpStmt->close();
    $updateStmt->close();
    $verifyStmt->close();
    $conn->close();

} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?>  