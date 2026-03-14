<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Speedly</title>
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }

        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 450px;
            padding: 40px;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .header i {
            font-size: 60px;
            color: #ff5e00;
            margin-bottom: 15px;
        }

        .header h2 {
            color: #333;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .header p {
            color: #666;
            font-size: 14px;
            line-height: 1.6;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #555;
            font-weight: 500;
            font-size: 14px;
        }

        .input-group {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group i {
            position: absolute;
            left: 15px;
            color: #ff5e00;
            font-size: 18px;
        }

        .input-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            outline: none;
            border-color: #ff5e00;
            box-shadow: 0 0 0 3px rgba(255, 94, 0, 0.1);
        }

        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 94, 0, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .links {
            text-align: center;
            margin-top: 20px;
        }

        .links a {
            color: #ff8c3a;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .links a:hover {
            color: #ff5e00;
            text-decoration: underline;
        }

        .security-note {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 12px;
            color: #666;
            text-align: center;
        }

        .security-note i {
            color: #28a745;
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fas fa-lock"></i>
            <h2>Forgot Password?</h2>
            <p>Don't worry! Enter your email address and we'll send you a secure link to reset your password.</p>
        </div>

        <!-- UPDATED: Changed action from send_otp.php to send-reset-link.php -->
        <form id="forgotPasswordForm" action="SERVER/API/send-reset-link.php" method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required>
                </div>
            </div>

            <button type="submit" class="btn" id="submitBtn">
                <i class="fas fa-paper-plane" style="margin-right: 10px;"></i>Send Reset Link
            </button>

            <div class="links">
                <a href="form.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>

            <div class="security-note">
                <i class="fas fa-shield-alt"></i>
                For your security, this link will expire in 1 hour
            </div>
        </form>
    </div>

    <script>
        document.getElementById('forgotPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const email = document.getElementById('email').value;
            const submitBtn = document.getElementById('submitBtn');
            
            // Validate email
            if (!email) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Email Required',
                    text: 'Please enter your email address',
                    confirmButtonColor: '#ff5e00'
                });
                return;
            }
            
            // Email format validation
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailPattern.test(email)) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Email',
                    text: 'Please enter a valid email address',
                    confirmButtonColor: '#ff5e00'
                });
                return;
            }
            
            // Disable button to prevent double submission
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
            
            // Send AJAX request
            const formData = new FormData();
            formData.append('email', email);
            
            // UPDATED: Changed from send_otp.php to send-reset-link.php
            fetch('SERVER/API/send-reset-link.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok');
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Reset Link Sent!',
                        html: data.message,
                        timer: 3000,
                        showConfirmButton: true,
                        confirmButtonColor: '#ff5e00'
                    }).then(() => {
                        // UPDATED: Redirect to login page instead of verify-otp
                        window.location.href = 'form.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Oops...',
                        text: data.message,
                        confirmButtonColor: '#ff5e00'
                    });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane" style="margin-right: 10px;"></i>Send Reset Link';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Please check your internet connection and try again.',
                    confirmButtonColor: '#ff5e00'
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-paper-plane" style="margin-right: 10px;"></i>Send Reset Link';
            });
        });
    </script>
</body>
</html>     