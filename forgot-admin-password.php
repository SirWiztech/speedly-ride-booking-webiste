<?php
session_start();
require_once 'SERVER/API/db-connect.php';

// If already logged in as admin, redirect
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true && $_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard.php");
    exit;
}

$message = '';
$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error = 'Please enter your email address';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address';
    } else {
        // Check if admin exists with this email
        $stmt = $conn->prepare("SELECT id, full_name FROM users WHERE email = ? AND role = 'admin'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            $updateStmt->bind_param("sss", $token, $expires, $user['id']);
            
            if ($updateStmt->execute()) {
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . "/SPEEDLY/reset-password.php?token=" . $token;
                $message = "Password reset instructions have been sent to your email.";
                
                // Demo link for localhost
                if (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false) {
                    $message .= "<br><small><a href='$resetLink'>Click here to reset (demo)</a></small>";
                }
            } else {
                $error = 'Failed to process request. Please try again.';
            }
        } else {
            // Don't reveal that email doesn't exist
            $message = "If an admin account exists with this email, you will receive reset instructions.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedly | Admin Forgot Password</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body {
            background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            width: 100%;
            max-width: 450px;
            background: white;
            border-radius: 30px;
            box-shadow: 0 30px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%);
            padding: 40px 30px;
            text-align: center;
        }
        .header h1 { color: white; font-size: 28px; margin-bottom: 5px; }
        .header p { color: rgba(255,255,255,0.9); font-size: 14px; }
        .content { padding: 40px 30px; }
        .message {
            background: #d4edda; color: #155724; padding: 15px; border-radius: 10px;
            margin-bottom: 20px; font-size: 14px; display: <?php echo $message ? 'block' : 'none'; ?>;
        }
        .error {
            background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px;
            margin-bottom: 20px; font-size: 14px; display: <?php echo $error ? 'block' : 'none'; ?>;
        }
        .input-group {
            margin-bottom: 25px; position: relative;
        }
        .input-group i {
            position: absolute; left: 15px; top: 50%; transform: translateY(-50%);
            color: #ff5e00; font-size: 18px;
        }
        .input-field {
            width: 100%; padding: 16px 20px 16px 50px;
            border: 2px solid #e0e0e0; border-radius: 15px;
            font-size: 15px; transition: all 0.3s;
        }
        .input-field:focus {
            outline: none; border-color: #ff5e00; box-shadow: 0 5px 20px rgba(255,94,0,0.2);
        }
        .btn {
            width: 100%; padding: 16px;
            background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%);
            color: white; border: none; border-radius: 15px;
            font-size: 16px; font-weight: 600; cursor: pointer;
            display: flex; align-items: center; justify-content: center; gap: 10px;
            transition: all 0.3s; margin-bottom: 20px;
        }
        .btn:hover { transform: translateY(-3px); box-shadow: 0 10px 25px rgba(255,94,0,0.4); }
        .back-link { text-align: center; margin-top: 20px; }
        .back-link a {
            color: #666; text-decoration: none; font-size: 14px;
            display: inline-flex; align-items: center; gap: 8px;
        }
        .back-link a:hover { color: #ff5e00; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Admin Password Reset</h1>
            <p>Reset your admin account password</p>
        </div>
        <div class="content">
            <?php if ($message): ?>
                <div class="message"><i class="fas fa-check-circle"></i> <?php echo $message; ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="error"><i class="fas fa-exclamation-circle"></i> <?php echo $error; ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" class="input-field" name="email" placeholder="Admin Email" required>
                </div>
                <button type="submit" class="btn">
                    <span>Send Reset Link</span>
                    <i class="fas fa-paper-plane"></i>
                </button>
                <div class="back-link">
                    <a href="admin_login.php"><i class="fas fa-arrow-left"></i> Back to Admin Login</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>  