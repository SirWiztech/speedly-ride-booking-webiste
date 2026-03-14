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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="description" content="Reset your Speedly account password">
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
            transition: color 0.3s ease;
        }

        .input-group .toggle-password {
            position: absolute;
            right: 15px;
            color: #999;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .input-group .toggle-password:hover {
            color: #ff5e00;
            transform: scale(1.1);
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
            border-color: #ff5e00;
            box-shadow: 0 0 0 3px rgba(255, 94, 0, 0.1);
        }

        .input-group input.error {
            border-color: #dc3545;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .password-strength {
            margin-top: 8px;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            overflow: hidden;
        }

        .strength-bar {
            height: 100%;
            width: 0;
            transition: width 0.3s ease, background 0.3s ease;
        }

        .strength-text {
            font-size: 12px;
            margin-top: 5px;
            text-align: right;
            color: #666;
            font-weight: 500;
        }

        .show-all-container {
            text-align: right;
            margin: 10px 0 15px;
            padding: 5px 0;
        }

        .show-all-container label {
            font-size: 13px;
            color: #666;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: color 0.3s ease;
        }

        .show-all-container label:hover {
            color: #ff5e00;
        }

        .show-all-container input[type="checkbox"] {
            accent-color: #ff5e00;
            width: 16px;
            height: 16px;
            cursor: pointer;
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
            transform: none;
            box-shadow: none;
        }

        .requirements {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            font-size: 12px;
            border: 1px solid #e0e0e0;
        }

        .requirements h4 {
            color: #333;
            margin-bottom: 15px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .requirements h4 i {
            color: #ff5e00;
            font-size: 16px;
        }

        .requirements ul {
            list-style: none;
            padding: 0;
        }

        .requirements li {
            color: #666;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: color 0.3s ease;
        }

        .requirements li i {
            font-size: 14px;
            width: 16px;
        }

        .requirements li.valid {
            color: #28a745;
        }

        .requirements li.valid i {
            color: #28a745;
        }

        .requirements li.invalid {
            color: #dc3545;
        }

        .requirements li.invalid i {
            color: #dc3545;
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
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .links a:hover {
            color: #ff5e00;
            text-decoration: underline;
        }

        .alert {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 20px;
            border-radius: 10px;
            background: white;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1000;
            animation: slideInRight 0.3s ease;
            max-width: 350px;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .alert-success {
            border-left: 4px solid #28a745;
        }

        .alert-error {
            border-left: 4px solid #dc3545;
        }

        .alert-warning {
            border-left: 4px solid #ffc107;
        }

        .alert-info {
            border-left: 4px solid #17a2b8;
        }

        .alert i {
            font-size: 20px;
        }

        .alert-success i {
            color: #28a745;
        }

        .alert-error i {
            color: #dc3545;
        }

        .alert-warning i {
            color: #ffc107;
        }

        .alert-info i {
            color: #17a2b8;
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
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('password')" title="Show password"></i>
                </div>
                <div class="password-strength">
                    <div class="strength-bar" id="strengthBar"></div>
                </div>
                <div class="strength-text" id="strengthText"></div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password')" title="Show password"></i>
                </div>
            </div>

            <div class="show-all-container">
                <label>
                    <input type="checkbox" id="showAllPasswords">
                    <i class="fas fa-eye"></i> Show all passwords
                </label>
            </div>

            <div class="requirements">
                <h4><i class="fas fa-shield-alt"></i> Password Requirements:</h4>
                <ul>
                    <li id="length" class="invalid"><i class="fas fa-times-circle"></i> At least 8 characters</li>
                    <li id="uppercase" class="invalid"><i class="fas fa-times-circle"></i> At least 1 uppercase letter</li>
                    <li id="lowercase" class="invalid"><i class="fas fa-times-circle"></i> At least 1 lowercase letter</li>
                    <li id="number" class="invalid"><i class="fas fa-times-circle"></i> At least 1 number</li>
                    <li id="special" class="invalid"><i class="fas fa-times-circle"></i> At least 1 special character (!@#$%^&*)</li>
                    <li id="match" class="invalid"><i class="fas fa-times-circle"></i> Passwords match</li>
                </ul>
            </div>

            <button type="submit" class="btn" id="submitBtn">
                <i class="fas fa-sync-alt" style="margin-right: 10px;"></i>Reset Password
            </button>

            <div class="links">
                <a href="form.php"><i class="fas fa-arrow-left"></i> Back to Login</a>
            </div>
        </form>
    </div>

    <!-- Alert container for notifications -->
    <div id="alertContainer"></div>

    <script>
        // Toggle password visibility with animation
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling;
            
            // Add animation
            icon.style.transform = 'scale(0.9)';
            setTimeout(() => {
                icon.style.transform = 'scale(1)';
            }, 100);
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
                icon.title = 'Hide password';
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
                icon.title = 'Show password';
            }
        }

        // Show/Hide all passwords
        document.getElementById('showAllPasswords').addEventListener('change', function(e) {
            const passwordField = document.getElementById('password');
            const confirmField = document.getElementById('confirm_password');
            const type = e.target.checked ? 'text' : 'password';
            
            passwordField.type = type;
            confirmField.type = type;
            
            // Update icons
            document.querySelectorAll('.toggle-password').forEach(icon => {
                if (type === 'text') {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                    icon.title = 'Hide password';
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                    icon.title = 'Show password';
                }
            });
        });

        // Password strength checker
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        const strengthBar = document.getElementById('strengthBar');
        const strengthText = document.getElementById('strengthText');

        function checkPasswordStrength() {
            const value = password.value;
            let strength = 0;
            
            // Check requirements
            const hasLength = value.length >= 8;
            const hasUppercase = /[A-Z]/.test(value);
            const hasLowercase = /[a-z]/.test(value);
            const hasNumber = /[0-9]/.test(value);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(value);
            
            // Update requirement icons
            updateRequirement('length', hasLength, 'At least 8 characters');
            updateRequirement('uppercase', hasUppercase, 'At least 1 uppercase letter');
            updateRequirement('lowercase', hasLowercase, 'At least 1 lowercase letter');
            updateRequirement('number', hasNumber, 'At least 1 number');
            updateRequirement('special', hasSpecial, 'At least 1 special character');
            
            // Calculate strength
            if (hasLength) strength += 20;
            if (hasUppercase) strength += 20;
            if (hasLowercase) strength += 20;
            if (hasNumber) strength += 20;
            if (hasSpecial) strength += 20;
            
            // Update strength bar
            strengthBar.style.width = strength + '%';
            
            if (strength < 40) {
                strengthBar.style.background = '#dc3545';
                strengthText.textContent = 'Weak';
                strengthText.style.color = '#dc3545';
            } else if (strength < 80) {
                strengthBar.style.background = '#ffc107';
                strengthText.textContent = 'Medium';
                strengthText.style.color = '#ffc107';
            } else {
                strengthBar.style.background = '#28a745';
                strengthText.textContent = 'Strong';
                strengthText.style.color = '#28a745';
            }
        }

        function updateRequirement(id, isValid, text) {
            const element = document.getElementById(id);
            element.className = isValid ? 'valid' : 'invalid';
            element.innerHTML = `<i class="fas fa-${isValid ? 'check-circle' : 'times-circle'}"></i> ${text}`;
        }

        // Check if passwords match
        function checkPasswordMatch() {
            const match = password.value === confirmPassword.value && password.value !== '';
            updateRequirement('match', match, 'Passwords match');
            return match;
        }

        password.addEventListener('input', function() {
            checkPasswordStrength();
            checkPasswordMatch();
        });

        confirmPassword.addEventListener('input', checkPasswordMatch);

        // Show alert function
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            alertContainer.innerHTML = '';
            
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            
            const icon = type === 'success' ? 'fa-check-circle' :
                        type === 'error' ? 'fa-exclamation-circle' :
                        type === 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle';
            
            alert.innerHTML = `
                <i class="fas ${icon}"></i>
                <span>${message}</span>
            `;
            
            alertContainer.appendChild(alert);
            
            setTimeout(() => {
                alert.remove();
            }, 5000);
        }

        // Form submission
        document.getElementById('resetPasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const passwordValue = document.getElementById('password').value;
            const confirmPasswordValue = document.getElementById('confirm_password').value;
            const token = document.getElementById('token').value;
            const submitBtn = document.getElementById('submitBtn');
            
            // Check if token exists
            if (!token) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Reset Link',
                    text: 'The password reset link is invalid. Please request a new one.',
                    confirmButtonColor: '#ff5e00'
                }).then(() => {
                    window.location.href = 'forgot-password.php';
                });
                return;
            }
            
            // Validate passwords match
            if (passwordValue !== confirmPasswordValue) {
                Swal.fire({
                    icon: 'error',
                    title: 'Passwords Do Not Match',
                    text: 'Please make sure both passwords are the same.',
                    confirmButtonColor: '#ff5e00'
                });
                
                // Highlight fields
                document.getElementById('password').classList.add('error');
                document.getElementById('confirm_password').classList.add('error');
                
                setTimeout(() => {
                    document.getElementById('password').classList.remove('error');
                    document.getElementById('confirm_password').classList.remove('error');
                }, 500);
                
                return;
            }
            
            // Validate password strength
            const hasLength = passwordValue.length >= 8;
            const hasUppercase = /[A-Z]/.test(passwordValue);
            const hasLowercase = /[a-z]/.test(passwordValue);
            const hasNumber = /[0-9]/.test(passwordValue);
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(passwordValue);
            
            if (!hasLength || !hasUppercase || !hasLowercase || !hasNumber || !hasSpecial) {
                Swal.fire({
                    icon: 'error',
                    title: 'Weak Password',
                    html: 'Please meet all password requirements:<br>' +
                          (hasLength ? '✅' : '❌') + ' At least 8 characters<br>' +
                          (hasUppercase ? '✅' : '❌') + ' At least 1 uppercase letter<br>' +
                          (hasLowercase ? '✅' : '❌') + ' At least 1 lowercase letter<br>' +
                          (hasNumber ? '✅' : '❌') + ' At least 1 number<br>' +
                          (hasSpecial ? '✅' : '❌') + ' At least 1 special character',
                    confirmButtonColor: '#ff5e00'
                });
                
                document.getElementById('password').classList.add('error');
                setTimeout(() => {
                    document.getElementById('password').classList.remove('error');
                }, 500);
                
                return;
            }
            
            // Disable button
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Resetting Password...';
            
            // Send AJAX request
            const formData = new FormData();
            formData.append('token', token);
            formData.append('password', passwordValue);
            
            fetch('SERVER/API/update-password.php', {
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
                        title: 'Password Reset Successful!',
                        html: data.message,
                        timer: 3000,
                        showConfirmButton: true,
                        confirmButtonColor: '#ff5e00',
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = 'form.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Reset Failed',
                        text: data.message,
                        confirmButtonColor: '#ff5e00'
                    });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-sync-alt" style="margin-right: 10px;"></i>Reset Password';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Connection Error',
                    text: 'Unable to connect to the server. Please check your internet connection and try again.',
                    confirmButtonColor: '#ff5e00'
                });
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-sync-alt" style="margin-right: 10px;"></i>Reset Password';
            });
        });

        // Handle enter key
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
                e.preventDefault();
                document.getElementById('submitBtn').click();
            }
        });

        // Clean up on page unload
        window.addEventListener('beforeunload', function() {
            // Clear any sensitive data from memory
            document.getElementById('password').value = '';
            document.getElementById('confirm_password').value = '';
        });
    </script>
</body>
</html>  