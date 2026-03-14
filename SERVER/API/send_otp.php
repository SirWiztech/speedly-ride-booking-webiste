<?php
header('Content-Type: application/json');

// Allow CORS for development (remove in production)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

// Include Composer's autoloader
require __DIR__ . '/../../vendor/autoload.php';

// Include database connection
require_once __DIR__ . '/db-connect.php';

// Use PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = $_POST['email'] ?? '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Valid email is required'
        ]);
        exit;
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        echo json_encode([
            'status' => 'success',
            'message' => 'If your email exists in our system, you will receive an OTP'
        ]);
        exit;
    }

    $user = $result->fetch_assoc();

    // Generate 6-digit OTP
    $otp = sprintf("%06d", mt_rand(1, 999999));
    $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));

    // Delete any existing OTPs for this user
    $deleteStmt = $conn->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $deleteStmt->bind_param("s", $user['id']);
    $deleteStmt->execute();
    $deleteStmt->close();

    // Save new OTP
    $insertStmt = $conn->prepare("INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)");
    $insertStmt->bind_param("sss", $user['id'], $otp, $expires);
    $insertStmt->execute();
    $insertStmt->close();

    // Send email using PHPMailer
    $mail = new PHPMailer(true);

    try {
        // Server settings - Using port 465 as 587 was blocked
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ojimabojames@gmail.com';
        $mail->Password   = 'nlnnjiwsdxyvesuh'; // App password
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
        $mail->setFrom('noreply@speedly.com', 'Speedly');
        $mail->addAddress($email, $user['full_name']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Speedly Verification Code';
        $mail->Body    = "
        <div style='font-family: \"Segoe UI\", Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.05);'>
            <div style='text-align: center; margin-bottom: 20px;'>
                <h2 style='color: #ff5e00; margin-top: 10px;'>Email Verification</h2>
            </div>
            <div style='background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%); padding: 30px; border-radius: 10px; text-align: center; box-shadow: 0 4px 15px rgba(255, 94, 0, 0.2);'>
                <p style='color: white; font-size: 18px; margin-bottom: 10px; font-weight: 500;'>Hello " . htmlspecialchars($user['full_name']) . ",</p>
                <p style='color: white; font-size: 16px; margin-bottom: 20px; opacity: 0.9;'>Your verification code is:</p>
                <div style='background: white; padding: 20px 30px; border-radius: 10px; display: inline-block; box-shadow: 0 4px 10px rgba(0,0,0,0.1);'>
                    <h1 style='font-size: 48px; letter-spacing: 10px; margin: 0; color: #ff5e00; font-weight: 700;'>" . $otp . "</h1>
                </div>
                <p style='color: white; font-size: 15px; margin-top: 25px; opacity: 0.95;'>⏰ This code will expire in <strong>10 minutes</strong></p>
            </div>
            <div style='margin-top: 25px; padding: 15px; background: #fff8f0; border-radius: 8px; border-left: 4px solid #ff5e00;'>
                <p style='margin: 0; color: #666; font-size: 13px;'>
                    <strong style='color: #ff5e00;'>🔒 Security Tip:</strong> Never share this code with anyone. Our team will never ask for your verification code.
                </p>
            </div>
            <p style='text-align: center; color: #999; font-size: 12px; margin-top: 25px;'>
                If you didn't request this verification, please ignore this email or contact support.
            </p>
            <hr style='border: none; border-top: 1px solid #eee; margin: 20px 0;'>
            <p style='text-align: center; color: #999; font-size: 11px;'>
                &copy; " . date('Y') . " Speedly. All rights reserved.
            </p>
        </div>";

        $mail->AltBody = "Your verification code is: " . $otp . ". This code will expire in 10 minutes.";

        $mail->send();

        echo json_encode([
            'status' => 'success',
            'message' => 'OTP sent successfully to your email'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Failed to send email. Error: ' . $mail->ErrorInfo
        ]);
    }

    $stmt->close();
    $conn->close();
} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method'
    ]);
}
?> 