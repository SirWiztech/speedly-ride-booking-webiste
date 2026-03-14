<?php
// Start session
session_start();

// Get token from URL
$token = $_GET['token'] ?? '';

// If no token, redirect to forgot password
if (empty($token)) {
    header("Location: forgot-password.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Speedly</title>
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            color: #667eea;
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
            color: #667eea;
            font-size: 18px;
        }

        .input-group .toggle-password {
            position: absolute;
            right: 15px;
            color: #999;
            cursor: pointer;
            font-size: 18px;
        }

        .input-group input {
            width: 100%;
            padding: 15px 45px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s ease;
        }

        .input-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
        }

        .btn:active {
            transform: translateY(0);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .requirements {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 12px;
        }

        .requirements h4 {
            color: #333;
            margin-bottom: 10px;
            font-size: 14px;
        }

        .requirements ul {
            list-style: none;
            padding: 0;
        }

        .requirements li {
            color: #666;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .requirements li.valid {
            color: #28a745;
        }

        .requirements li.invalid {
            color: #dc3545;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fas fa-key"></i>
            <h2>Create New Password</h2>
            <p>Enter your new password below</p>
        </div>

        <form id="resetPasswordForm">
            <input type="hidden" name="token" id="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
                <label for="password">New Password</label>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" placeholder="Enter new password" required minlength="8">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('password')"></i>
                </div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password')"></i>
                </div>
            </div>

            <div class="requirements">
                <h4>Password Requirements:</h4>
                <ul>
                    <li id="length" class="invalid"><i class="fas fa-times-circle"></i> At least 8 characters</li>
                    <li id="uppercase" class="invalid"><i class="fas fa-times-circle"></i> At least 1 uppercase letter</li>
                    <li id="lowercase" class="invalid"><i class="fas fa-times-circle"></i> At least 1 lowercase letter</li>
                    <li id="number" class="invalid"><i class="fas fa-times-circle"></i> At least 1 number</li>
                    <li id="match" class="invalid"><i class="fas fa-times-circle"></i> Passwords match</li>
                </ul>
            </div>

            <button type="submit" class="btn" id="submitBtn">
                <i class="fas fa-sync-alt" style="margin-right: 10px;"></i>Reset Password
            </button>
        </form>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling;
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');

        function validatePassword() {
            const value = password.value;
            
            // Check requirements
            document.getElementById('length').className = value.length >= 8 ? 'valid' : 'invalid';
            document.getElementById('length').innerHTML = `<i class="fas fa-${value.length >= 8 ? 'check-circle' : 'times-circle'}"></i> At least 8 characters`;
            
            document.getElementById('uppercase').className = /[A-Z]/.test(value) ? 'valid' : 'invalid';
            document.getElementById('uppercase').innerHTML = `<i class="fas fa-${/[A-Z]/.test(value) ? 'check-circle' : 'times-circle'}"></i> At least 1 uppercase letter`;
            
            document.getElementById('lowercase').className = /[a-z]/.test(value) ? 'valid' : 'invalid';
            document.getElementById('lowercase').innerHTML = `<i class="fas fa-${/[a-z]/.test(value) ? 'check-circle' : 'times-circle'}"></i> At least 1 lowercase letter`;
            
            document.getElementById('number').className = /[0-9]/.test(value) ? 'valid' : 'invalid';
            document.getElementById('number').innerHTML = `<i class="fas fa-${/[0-9]/.test(value) ? 'check-circle' : 'times-circle'}"></i> At least 1 number`;
            
            const match = value === confirmPassword.value && value !== '';
            document.getElementById('match').className = match ? 'valid' : 'invalid';
            document.getElementById('match').innerHTML = `<i class="fas fa-${match ? 'check-circle' : 'times-circle'}"></i> Passwords match`;
        }

        password.addEventListener('input', validatePassword);
        confirmPassword.addEventListener('input', validatePassword);

        document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const token = document.getElementById('token').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const submitBtn = document.getElementById('submitBtn');
            
            if (password !== confirmPassword) {
                Swal.fire({
                    icon: 'error',
                    title: 'Passwords Do Not Match',
                    text: 'Please make sure both passwords are the same',
                    confirmButtonColor: '#667eea'
                });
                return;
            }
            
            if (password.length < 8) {
                Swal.fire({
                    icon: 'error',
                    title: 'Weak Password',
                    text: 'Password must be at least 8 characters long',
                    confirmButtonColor: '#667eea'
                });
                return;
            }
            
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
            
            const formData = new FormData();
            formData.append('token', token);
            formData.append('password', password);
            
            fetch('SERVER/API/update-password.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: 'Password Updated!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = 'form.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Update Failed',
                        text: data.message,
                        confirmButtonColor: '#667eea'
                    });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-sync-alt" style="margin-right: 10px;"></i>Reset Password';
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Please check your internet connection',
                    confirmButtonColor: '#667eea'
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-sync-alt" style="margin-right: 10px;"></i>Reset Password';
            });
        });
    </script>
</body>
</html>  