<?php
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
        
        // Generate secure token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store token in database
        $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $updateStmt->bind_param("sss", $token, $expires, $user['id']);
        
        if (!$updateStmt->execute()) {
            throw new Exception("Failed to update reset token");
        }
        
        // Create reset link
        $resetLink = "http://localhost/SPEEDLY/reset-password.php?token=" . $token;
        
        // Send email using PHPMailer
        $mail = new PHPMailer(true);
        
        // Server settings - Using port 465 as it worked in send_otp.php
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ojimabojames@gmail.com'; // Your Gmail
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
        
        // Email content with modern design
        $mail->isHTML(true);
        $mail->Subject = 'Reset Your Speedly Password';
        
        // Stylish email template with orange theme
        $mail->Body = "
        <div style='font-family: \"Segoe UI\", Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h2 style='color: #ff5e00; margin-top: 10px;'>Password Reset Request</h2>
            </div>
            <div style='background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%); padding: 30px; border-radius: 10px; text-align: center; box-shadow: 0 4px 15px rgba(255, 94, 0, 0.2);'>
                <p style='color: white; font-size: 18px; margin-bottom: 10px; font-weight: 500;'>Hello " . htmlspecialchars($user['full_name']) . ",</p>
                <p style='color: white; font-size: 16px; margin-bottom: 20px; opacity: 0.9;'>We received a request to reset your password.</p>
                <a href='" . $resetLink . "' style='background: white; color: #ff5e00; padding: 15px 30px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 16px; display: inline-block; box-shadow: 0 4px 10px rgba(0,0,0,0.1);'>Reset Your Password</a>
                <p style='color: white; font-size: 14px; margin-top: 25px; opacity: 0.95;'>⏰ This link will expire in <strong>1 hour</strong></p>
            </div>
            <div style='margin-top: 25px; padding: 15px; background: #fff8f0; border-radius: 8px; border-left: 4px solid #ff5e00;'>
                <p style='margin: 0; color: #666; font-size: 13px;'>
                    <strong style='color: #ff5e00;'>🔒 Security Tip:</strong> If you didn't request this password reset, please ignore this email or contact support immediately.
                </p>
            </div>
            <p style='text-align: center; color: #999; font-size: 12px; margin-top: 25px;'>
                For security reasons, this link can only be used once and will expire after 1 hour.
            </p>
            <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='text-align: center; color: #999; font-size: 11px;'>
                &copy; " . date('Y') . " Speedly. All rights reserved.
            </p>
        </div>";
        
        $mail->AltBody = "Hello " . $user['full_name'] . ",\n\nWe received a request to reset your password. Please click the link below to reset your password:\n\n" . $resetLink . "\n\nThis link will expire in 1 hour.\n\nIf you didn't request this, please ignore this email.\n\nThank you,\nSpeedly Team";
        
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
    if (isset($updateStmt)) $updateStmt->close();
    $conn->close();
    
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?>