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

    <script>
// Toggle password visibility function
window.togglePassword = function(fieldId) {
    const field = document.getElementById(fieldId);
    if (!field) return;
    
    const type = field.getAttribute('type') === 'password' ? 'text' : 'password';
    field.setAttribute('type', type);
    
    // Toggle icon
    const icon = event.target;
    icon.classList.toggle('fa-eye');
    icon.classList.toggle('fa-eye-slash');
}

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing form...');
    
    // Check if form exists
    const resetForm = document.getElementById('resetPasswordForm');
    if (!resetForm) {
        console.error('Reset password form not found!');
        return;
    }
    
    // Show all passwords checkbox
    const showAllCheckbox = document.getElementById('showAllPasswords');
    if (showAllCheckbox) {
        showAllCheckbox.addEventListener('change', function() {
            const passwordField = document.getElementById('password');
            const confirmField = document.getElementById('confirm_password');
            const type = this.checked ? 'text' : 'password';
            
            if (passwordField) passwordField.setAttribute('type', type);
            if (confirmField) confirmField.setAttribute('type', type);
            
            // Update toggle icons
            document.querySelectorAll('.toggle-password').forEach(icon => {
                if (type === 'text') {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    }

    // Password strength checker
    const passwordField = document.getElementById('password');
    if (passwordField) {
        passwordField.addEventListener('input', function() {
            const password = this.value;
            const strengthBar = document.getElementById('strengthBar');
            const strengthText = document.getElementById('strengthText');
            
            if (!strengthBar || !strengthText) return;
            
            // Check requirements
            const hasLength = password.length >= 8;
            const hasUpper = /[A-Z]/.test(password);
            const hasLower = /[a-z]/.test(password);
            const hasNumber = /[0-9]/.test(password);
            const hasSpecial = /[!@#$%^&*]/.test(password);
            
            // Update requirement icons
            const lengthEl = document.getElementById('length');
            const upperEl = document.getElementById('uppercase');
            const lowerEl = document.getElementById('lowercase');
            const numberEl = document.getElementById('number');
            const specialEl = document.getElementById('special');
            
            if (lengthEl) {
                lengthEl.className = hasLength ? 'valid' : 'invalid';
                lengthEl.innerHTML = `<i class="fas ${hasLength ? 'fa-check-circle' : 'fa-times-circle'}"></i> At least 8 characters`;
            }
            
            if (upperEl) {
                upperEl.className = hasUpper ? 'valid' : 'invalid';
                upperEl.innerHTML = `<i class="fas ${hasUpper ? 'fa-check-circle' : 'fa-times-circle'}"></i> At least 1 uppercase letter`;
            }
            
            if (lowerEl) {
                lowerEl.className = hasLower ? 'valid' : 'invalid';
                lowerEl.innerHTML = `<i class="fas ${hasLower ? 'fa-check-circle' : 'fa-times-circle'}"></i> At least 1 lowercase letter`;
            }
            
            if (numberEl) {
                numberEl.className = hasNumber ? 'valid' : 'invalid';
                numberEl.innerHTML = `<i class="fas ${hasNumber ? 'fa-check-circle' : 'fa-times-circle'}"></i> At least 1 number`;
            }
            
            if (specialEl) {
                specialEl.className = hasSpecial ? 'valid' : 'invalid';
                specialEl.innerHTML = `<i class="fas ${hasSpecial ? 'fa-check-circle' : 'fa-times-circle'}"></i> At least 1 special character (!@#$%^&*)`;
            }
            
            // Calculate strength
            const strength = [hasLength, hasUpper, hasLower, hasNumber, hasSpecial].filter(Boolean).length;
            
            // Update strength bar
            const width = (strength / 5) * 100;
            strengthBar.style.width = width + '%';
            
            let color = '#dc3545';
            let text = 'Very Weak';
            
            if (strength === 5) {
                color = '#28a745';
                text = 'Strong';
            } else if (strength === 4) {
                color = '#ffc107';
                text = 'Good';
            } else if (strength === 3) {
                color = '#ff8c3a';
                text = 'Fair';
            }
            
            strengthBar.style.backgroundColor = color;
            strengthText.textContent = 'Password Strength: ' + text;
            strengthText.style.color = color;
        });
    }

    // Check password match
    const confirmField = document.getElementById('confirm_password');
    if (confirmField) {
        confirmField.addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirm = this.value;
            const matchElement = document.getElementById('match');
            
            if (!matchElement) return;
            
            if (password === confirm && password !== '') {
                matchElement.className = 'valid';
                matchElement.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
            } else {
                matchElement.className = 'invalid';
                matchElement.innerHTML = '<i class="fas fa-times-circle"></i> Passwords match';
            }
        });
    }

    // Form submission
    resetForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;
        const token = document.getElementById('token').value;
        const submitBtn = document.getElementById('submitBtn');
        
        // Validate password
        if (password.length < 8) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Password',
                text: 'Password must be at least 8 characters long',
                confirmButtonColor: '#ff5e00'
            });
            return;
        }
        
        // Check if passwords match
        if (password !== confirmPassword) {
            Swal.fire({
                icon: 'warning',
                title: 'Passwords Do Not Match',
                text: 'Please make sure your passwords match',
                confirmButtonColor: '#ff5e00'
            });
            return;
        }
        
        // Disable button
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        
        // Create FormData
        const formData = new FormData();
        formData.append('token', token);
        formData.append('password', password);
        
        console.log('Sending request with token:', token);
        
        // Send AJAX request to update-password.php
        fetch('SERVER/API/update-password.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            return response.json();
        })
        .then(data => {
            console.log('Response data:', data);
            
            if (data.status === 'success') {
                Swal.fire({
                    icon: 'success',
                    title: 'Password Updated!',
                    text: 'Your password has been successfully reset. You can now login with your new password.',
                    confirmButtonColor: '#ff5e00'
                }).then(() => {
                    window.location.href = 'form.php';
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: data.message || 'Failed to update password',
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
                text: error.message || 'Unable to connect to the server. Please try again.',
                confirmButtonColor: '#ff5e00'
            });
            submitBtn.disabled = false;
            submitBtn.innerHTML = '<i class="fas fa-sync-alt" style="margin-right: 10px;"></i>Reset Password';
        });
    });
});
</script>
</body>
</html>