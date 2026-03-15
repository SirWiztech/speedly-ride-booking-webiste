<?php
// SERVER/API/send-reset-link.php
header('Content-Type: application/json');

// Include Composer's autoloader for PHPMailer
require __DIR__ . '/../../vendor/autoload.php';

// Include database connection
require_once __DIR__ . '/db-connect.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    
    $email = $_POST['email'] ?? '';
    
    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please enter a valid email address'
        ]);
        exit;
    }
    
    try {
        // Check if user exists
        $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        // For security, don't reveal if email exists
        if ($result->num_rows === 0) {
            echo json_encode([
                'status' => 'success',
                'message' => 'If your email exists in our system, you will receive a reset link'
            ]);
            exit;
        }
        
        $user = $result->fetch_assoc();
        
        // Generate a secure token (not just numeric OTP)
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Delete any existing tokens for this user
        $deleteStmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
        $deleteStmt->bind_param("s", $user['id']);
        $deleteStmt->execute();
        $deleteStmt->close();
        
        // Save new token
        $insertStmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
        $insertStmt->bind_param("sss", $user['id'], $token, $expires);
        
        if (!$insertStmt->execute()) {
            throw new Exception("Failed to save reset token");
        }
        
        // Create reset link
        $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/SPEEDLY/reset-password.php?token=" . $token;
        
        // Send email using PHPMailer
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ojimabojames@gmail.com';
        $mail->Password   = 'nlnnjiwsdxyvesuh'; // Your app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL for port 465
        $mail->Port       = 465;
        
        // SSL options for local development
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
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Reset Your Speedly Password';
        
        $mail->Body = "
        <div style='font-family: \"Segoe UI\", Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h2 style='color: #ff5e00; margin-top: 10px;'>Password Reset Request</h2>
            </div>
            <div style='background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%); padding: 30px; border-radius: 10px; text-align: center; box-shadow: 0 4px 15px rgba(255, 94, 0, 0.2);'>
                <p style='color: white; font-size: 18px; margin-bottom: 10px; font-weight: 500;'>Hello " . htmlspecialchars($user['full_name']) . ",</p>
                <p style='color: white; font-size: 16px; margin-bottom: 20px; opacity: 0.9;'>Click the button below to reset your password:</p>
                <a href='" . $resetLink . "' style='display: inline-block; background: white; color: #ff5e00; text-decoration: none; padding: 15px 30px; border-radius: 50px; font-weight: bold; font-size: 16px; margin: 20px 0; box-shadow: 0 4px 10px rgba(0,0,0,0.1);'>Reset Password</a>
                <p style='color: white; font-size: 14px; margin-top: 20px; opacity: 0.8;'>⏰ This link will expire in <strong>1 hour</strong></p>
            </div>
            <div style='margin-top: 25px; padding: 15px; background: #fff8f0; border-radius: 8px; border-left: 4px solid #ff5e00;'>
                <p style='margin: 0; color: #666; font-size: 13px;'>
                    <strong style='color: #ff5e00;'>🔒 Security Tip:</strong> If you didn't request this password reset, please ignore this email or contact support.
                </p>
            </div>
            <p style='text-align: center; color: #999; font-size: 12px; margin-top: 25px;'>
                &copy; " . date('Y') . " Speedly. All rights reserved.
            </p>
        </div>";
        
        $mail->AltBody = "Reset your password by clicking this link: " . $resetLink . " This link will expire in 1 hour.";
        
        $mail->send();
        
        echo json_encode([
            'status' => 'success',
            'message' => 'Password reset link has been sent to your email'
        ]);
        
    } catch (Exception $e) {
        error_log("Mail error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to send email. Please try again later.'
        ]);
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => 'An error occurred. Please try again.'
        ]);
    }
    
    // Close statements if they exist
    if (isset($stmt)) $stmt->close();
    if (isset($insertStmt)) $insertStmt->close();
    $conn->close();
    
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?>