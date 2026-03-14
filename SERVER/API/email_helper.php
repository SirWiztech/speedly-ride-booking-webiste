<?php
// Email helper functions - include this in files that need to send emails

require __DIR__ . '/../../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

function sendOTPEmail($to, $name, $otp) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'ojimabojames@gmail.com'; // REPLACE WITH YOUR GMAIL
        $mail->Password   = 'nlnn jiws dxyv esuh';     // REPLACE WITH YOUR APP PASSWORD
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        
        // Recipients
        $mail->setFrom('noreply@speedly.com', 'Speedly');
        $mail->addAddress($to, $name);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Your Speedly Verification Code';
        $mail->Body    = "<h1>Your OTP is: $otp</h1><p>This code expires in 10 minutes.</p>";
        $mail->AltBody = "Your OTP is: $otp. Expires in 10 minutes.";
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $mail->ErrorInfo];
    }
}
?>  