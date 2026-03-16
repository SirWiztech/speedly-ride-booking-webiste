<?php
// SERVER/API/send-reset-link.php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if vendor autoload exists
$vendorPath = __DIR__ . '/../../vendor/autoload.php';
if (!file_exists($vendorPath)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Vendor autoload not found. Please run: composer require phpmailer/phpmailer'
    ]);
    exit;
}

require_once $vendorPath;
require_once __DIR__ . '/db-connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

error_log("=== Send Reset Link Request Started ===");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed. Use POST.']);
    exit;
}

$email = $_POST['email'] ?? '';

error_log("Email received: " . $email);

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['status' => 'error', 'message' => 'Please enter a valid email address']);
    exit;
}

try {
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
    if (!$stmt) {
        throw new Exception("Database error: " . $conn->error);
    }
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    // Don't reveal if email exists
    if ($result->num_rows === 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'If your email exists in our system, you will receive a reset link'
        ]);
        exit;
    }
    
    $user = $result->fetch_assoc();
    error_log("User found: " . $user['id']);
    
    // Generate token
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Delete old tokens
    $deleteStmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
    if (!$deleteStmt) throw new Exception("Delete prepare error: " . $conn->error);
    $deleteStmt->bind_param("s", $user['id']);
    $deleteStmt->execute();
    $deleteStmt->close();
    
    // Save new token
    $reset_id = bin2hex(random_bytes(16));
    $insertStmt = $conn->prepare("INSERT INTO password_resets (id, user_id, token, expires_at) VALUES (?, ?, ?, ?)");
    if (!$insertStmt) throw new Exception("Insert prepare error: " . $conn->error);
    $insertStmt->bind_param("ssss", $reset_id, $user['id'], $token, $expires);
    
    if (!$insertStmt->execute()) {
        throw new Exception("Failed to save reset token: " . $insertStmt->error);
    }
    
    error_log("Reset token saved with ID: " . $reset_id);
    
    // Create reset link
    $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/SPEEDLY/reset-password.php?token=" . $token;
    
    // Send email using PHPMailer - NOW USING PORT 465 WITH SSL
    $mail = new PHPMailer(true);
    
    // Server settings - USING PORT 465 WITH SSL (since 587 is blocked)
    $mail->SMTPDebug = SMTP::DEBUG_OFF; // Set to DEBUG_SERVER for debugging
    $mail->isSMTP();
    $mail->Host       = 'smtp.gmail.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'ojimabojames@gmail.com';
    $mail->Password   = 'sewp zxyw putm cvht'; // Your app password
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL for port 465
    $mail->Port       = 465; // Using port 465 since 587 is blocked
    
    // SSL options
    $mail->SMTPOptions = array(
        'ssl' => array(
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        )
    );
    
    // Recipients
    $mail->setFrom('noreply@speedly.com', 'Speedly Support');
    $mail->addAddress($email, $user['full_name']);
    
    // Content
    $mail->isHTML(true);
    $mail->Subject = 'Reset Your Speedly Password';
    $mail->Body = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>Password Reset</title>
    </head>
    <body style='font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4;'>
        <div style='max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <img src='http://" . $_SERVER['HTTP_HOST'] . "/SPEEDLY/main-assets/logo-no-background.png' alt='Speedly' style='max-width: 150px;'>
            </div>
            
            <h2 style='color: #333; margin-bottom: 20px;'>Password Reset Request</h2>
            
            <p style='color: #666; line-height: 1.6; margin-bottom: 20px;'>
                Hello <strong>" . htmlspecialchars($user['full_name']) . "</strong>,<br><br>
                We received a request to reset your password for your Speedly account. 
                Click the button below to create a new password:
            </p>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='" . $resetLink . "' 
                   style='display: inline-block; background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%); 
                          color: white; text-decoration: none; padding: 15px 40px; 
                          border-radius: 50px; font-weight: bold; font-size: 16px;
                          box-shadow: 0 4px 15px rgba(255,94,0,0.3);'>
                    Reset Password
                </a>
            </div>
            
            <p style='color: #666; line-height: 1.6; margin-bottom: 20px;'>
                Or copy this link to your browser:<br>
                <a href='" . $resetLink . "' style='color: #ff5e00; word-break: break-all;'>" . $resetLink . "</a>
            </p>
            
            <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                <p style='color: #666; font-size: 14px; margin: 0;'>
                    <strong>⏰ This link will expire in 1 hour</strong><br>
                    If you didn't request this password reset, please ignore this email.
                </p>
            </div>
            
            <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
            
            <p style='text-align: center; color: #999; font-size: 12px;'>
                &copy; " . date('Y') . " Speedly. All rights reserved.
            </p>
        </div>
    </body>
    </html>
    ";
    
    $mail->AltBody = "Reset your password here: " . $resetLink;
    
    if (!$mail->send()) {
        throw new Exception("Mailer Error: " . $mail->ErrorInfo);
    }
    
    echo json_encode([
        'status' => 'success',
        'message' => 'Password reset link has been sent to your email'
    ]);
    
} catch (Exception $e) {
    error_log("Error in send-reset-link.php: " . $e->getMessage());
    
    // Development fallback - since email is failing, show the link
    if (isset($resetLink)) {
        echo json_encode([
            'status' => 'success',
            'message' => 'Email sending failed. Use this link to reset (development mode)',
            'reset_link' => $resetLink,
            'dev_mode' => true
        ]);
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to process request: ' . $e->getMessage()
        ]);
    }
}

$conn->close();
error_log("=== Send Reset Link Request Completed ===");
?>