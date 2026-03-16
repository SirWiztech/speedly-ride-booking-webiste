<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Speedly | Admin Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Animated background elements */
        body::before {
            content: '';
            position: absolute;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -100px;
            right: -100px;
            animation: float 20s infinite ease-in-out;
        }

        body::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            bottom: -50px;
            left: -50px;
            animation: float 15s infinite ease-in-out reverse;
        }

        @keyframes float {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            33% { transform: translate(30px, -30px) rotate(120deg); }
            66% { transform: translate(-20px, 20px) rotate(240deg); }
        }

        .login-container {
            background: white;
            border-radius: 30px;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 460px;
            padding: 40px;
            position: relative;
            z-index: 10;
            animation: slideIn 0.6s ease-out;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
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

        .login-header {
            text-align: center;
            margin-bottom: 35px;
        }

        .logo-wrapper {
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }

        .logo-wrapper::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #ff5e00, #ff8c3a);
            border-radius: 50%;
            top: 5px;
            left: 5px;
            z-index: -1;
            opacity: 0.3;
            filter: blur(10px);
        }

        .logo-image {
            max-width: 160px;
            height: auto;
            transition: transform 0.3s ease;
        }

        .logo-image:hover {
            transform: scale(1.05);
        }

        .login-header h1 {
            color: #333;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #ff5e00, #ff8c3a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .login-header p {
            color: #666;
            font-size: 14px;
            font-weight: 300;
        }

        .login-form {
            margin-top: 20px;
        }

        .error-message {
            background: #fee;
            border-left: 4px solid #ff3b3b;
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 10px;
            animation: shake 0.5s ease;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .error-message i {
            color: #ff3b3b;
            font-size: 18px;
        }

        .error-message span {
            color: #d63031;
            font-size: 13px;
            font-weight: 500;
        }

        .success-message {
            background: #d4edda;
            border-left: 4px solid #28a745;
            border-radius: 10px;
            padding: 12px 15px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .success-message i {
            color: #28a745;
            font-size: 18px;
        }

        .success-message span {
            color: #155724;
            font-size: 13px;
            font-weight: 500;
        }

        .input-group {
            position: relative;
            margin-bottom: 20px;
        }

        .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #ff8c3a;
            font-size: 18px;
            transition: color 0.3s ease;
            z-index: 1;
        }

        .input-field {
            width: 100%;
            padding: 16px 20px 16px 45px;
            border: 2px solid #f0f0f0;
            border-radius: 15px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            transition: all 0.3s ease;
            background: #fafafa;
        }

        .input-field:focus {
            outline: none;
            border-color: #ff5e00;
            background: white;
            box-shadow: 0 10px 20px rgba(255, 94, 0, 0.1);
            transform: translateY(-2px);
        }

        .input-field:focus + .input-icon {
            color: #ff5e00;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #999;
            cursor: pointer;
            font-size: 18px;
            transition: all 0.3s ease;
            z-index: 1;
        }

        .password-toggle:hover {
            color: #ff5e00;
            transform: translateY(-50%) scale(1.1);
        }

        .login-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
        }

        .remember-me {
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            color: #666;
            font-size: 14px;
            transition: color 0.3s ease;
        }

        .remember-me:hover {
            color: #ff5e00;
        }

        .remember-me input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
            accent-color: #ff5e00;
        }

        .forgot-password {
            color: #ff8c3a;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
        }

        .forgot-password::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 2px;
            background: #ff5e00;
            bottom: -2px;
            left: 0;
            transform: scaleX(0);
            transition: transform 0.3s ease;
            transform-origin: right;
        }

        .forgot-password:hover {
            color: #ff5e00;
        }

        .forgot-password:hover::after {
            transform: scaleX(1);
            transform-origin: left;
        }

        .login-btn {
            width: 100%;
            padding: 16px;
            background: linear-gradient(135deg, #ff5e00, #ff8c3a);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            position: relative;
            overflow: hidden;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            transform: translate(-50%, -50%);
            transition: width 0.6s, height 0.6s;
        }

        .login-btn:hover::before {
            width: 300px;
            height: 300px;
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(255, 94, 0, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .login-btn .btn-text {
            position: relative;
            z-index: 1;
        }

        .login-btn i {
            position: relative;
            z-index: 1;
            transition: transform 0.3s ease;
        }

        .login-btn:hover i {
            transform: translateX(5px);
        }

        .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-top: 3px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            position: relative;
            z-index: 1;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .login-btn.loading .btn-text,
        .login-btn.loading i {
            display: none;
        }

        .login-btn.loading .spinner {
            display: inline-block;
        }

        .security-badge {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 25px;
            color: #999;
            font-size: 12px;
            padding: 10px;
            border-top: 1px solid #f0f0f0;
        }

        .security-badge i {
            color: #ff8c3a;
            font-size: 14px;
        }

        .back-link {
            text-align: center;
            margin-top: 20px;
        }

        .back-link a {
            color: #666;
            text-decoration: none;
            font-size: 14px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .back-link a:hover {
            color: #ff5e00;
            gap: 12px;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 2000;
            backdrop-filter: blur(5px);
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 30px;
            width: 90%;
            max-width: 420px;
            padding: 35px;
            animation: modalSlideUp 0.4s ease-out;
            box-shadow: 0 30px 60px rgba(0, 0, 0, 0.3);
        }

        @keyframes modalSlideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .modal-header h3 {
            color: #333;
            font-size: 22px;
            font-weight: 600;
            background: linear-gradient(135deg, #ff5e00, #ff8c3a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .close-modal {
            background: none;
            border: none;
            font-size: 30px;
            cursor: pointer;
            color: #999;
            transition: all 0.3s ease;
            line-height: 1;
        }

        .close-modal:hover {
            color: #ff5e00;
            transform: rotate(90deg);
        }

        .modal-body p {
            color: #666;
            margin-bottom: 20px;
            font-size: 14px;
            line-height: 1.6;
        }

        .modal-input {
            width: 100%;
            padding: 15px;
            border: 2px solid #f0f0f0;
            border-radius: 15px;
            font-size: 14px;
            font-family: 'Poppins', sans-serif;
            margin-bottom: 20px;
            transition: all 0.3s ease;
        }

        .modal-input:focus {
            outline: none;
            border-color: #ff5e00;
            box-shadow: 0 5px 15px rgba(255, 94, 0, 0.1);
        }

        .modal-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #ff5e00, #ff8c3a);
            color: white;
            border: none;
            border-radius: 15px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .modal-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 94, 0, 0.3);
        }

        .modal-btn:active {
            transform: translateY(0);
        }

        /* Responsive Design */
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            .login-header h1 {
                font-size: 24px;
            }

            .logo-image {
                max-width: 130px;
            }

            .login-options {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }

        /* Loading animation for button */
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        .login-btn:disabled {
            animation: pulse 1.5s infinite;
        }

        /* Input autofill styles */
        input:-webkit-autofill,
        input:-webkit-autofill:hover,
        input:-webkit-autofill:focus {
            -webkit-box-shadow: 0 0 0px 1000px white inset;
            box-shadow: 0 0 0px 1000px white inset;
            -webkit-text-fill-color: #333;
            transition: background-color 5000s ease-in-out 0s;
        }
    </style>
</head>

<body>
    <div class="login-container">
        <!-- Header with Logo -->
        <div class="login-header">
            <div class="logo-wrapper">
                <img src="./main-assets/logo-no-background.png" alt="Speedly" class="logo-image">
            </div>
            <h1>Admin Dashboard</h1>
            <p>Sign in to manage your platform</p>
        </div>

        <!-- Login Form -->
        <div class="login-form">
            <!-- Error Message -->
            <div class="error-message" id="errorMessage">
                <i class="fas fa-exclamation-circle"></i>
                <span id="errorText">Invalid username or password</span>
            </div>

            <!-- Success Message -->
            <div class="success-message" id="successMessage">
                <i class="fas fa-check-circle"></i>
                <span>Login successful! Redirecting...</span>
            </div>

            <form id="adminLoginForm">
                <!-- Username Input -->
                <div class="input-group">
                    <i class="fas fa-user input-icon"></i>
                    <input type="text" class="input-field" id="username" name="username" placeholder="Username" required
                        autocomplete="off" autofocus>
                </div>

                <!-- Password Input -->
                <div class="input-group">
                    <i class="fas fa-lock input-icon"></i>
                    <input type="password" class="input-field" id="password" name="password" placeholder="Password"
                        required>
                    <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                </div>

                <!-- Login Options -->
                <div class="login-options">
                    <label class="remember-me">
                        <input type="checkbox" id="rememberMe">
                        <span>Remember me</span>
                    </label>
                    <a href="#" class="forgot-password" id="forgotPassword">Forgot Password?</a>
                </div>

                <!-- Login Button -->
                <button type="submit" class="login-btn" id="loginBtn">
                    <span class="btn-text">Sign In</span>
                    <span class="spinner"></span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </form>

            <!-- Security Badge -->
            <div class="security-badge">
                <i class="fas fa-shield-alt"></i>
                <span>Secure 256-bit SSL encrypted connection</span>
            </div>

            <!-- Back to Website Link -->
            <div class="back-link">
                <a href="index.php">
                    <i class="fas fa-arrow-left"></i>
                    Back to Website
                </a>
            </div>
        </div>
    </div>

    <!-- Forgot Password Modal -->
    <div class="modal" id="forgotModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reset Password</h3>
                <button class="close-modal" id="closeModal">&times;</button>
            </div>
            <div class="modal-body">
                <p>Enter your email address and we'll send you instructions to reset your password.</p>
                <input type="email" id="resetEmail" class="modal-input" placeholder="Email address">
                <button class="modal-btn" id="sendResetLink">
                    <span>Send Reset Link</span>
                </button>
            </div>
        </div>
    </div>

    <script>
        // Password toggle functionality
        const togglePassword = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');

        togglePassword.addEventListener('click', function() {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });

        // Form submission
        const loginForm = document.getElementById('adminLoginForm');
        const loginBtn = document.getElementById('loginBtn');
        const errorMessage = document.getElementById('errorMessage');
        const successMessage = document.getElementById('successMessage');
        const errorText = document.getElementById('errorText');

        loginForm.addEventListener('submit', async function(e) {
            e.preventDefault();

            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;

            // Hide previous messages
            errorMessage.style.display = 'none';
            successMessage.style.display = 'none';

            // Validate inputs
            if (!username || !password) {
                showError('Please enter both username and password');
                return;
            }

            // Show loading state
            loginBtn.classList.add('loading');
            loginBtn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('username', username);
                formData.append('password', password);
                formData.append('remember', document.getElementById('rememberMe').checked);

                const response = await fetch('SERVER/API/admin-login.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'success') {
                    // Show success message
                    successMessage.style.display = 'flex';
                    
                    // Redirect to dashboard
                    setTimeout(() => {
                        window.location.href = 'admin_dashboard.php';
                    }, 1500);
                } else {
                    showError(data.message || 'Invalid credentials');
                }
            } catch (error) {
                showError('Connection error. Please try again.');
                console.error('Login error:', error);
            } finally {
                // Remove loading state
                loginBtn.classList.remove('loading');
                loginBtn.disabled = false;
            }
        });

        function showError(message) {
            errorText.textContent = message;
            errorMessage.style.display = 'flex';
            
            // Auto hide after 5 seconds
            setTimeout(() => {
                errorMessage.style.display = 'none';
            }, 5000);
        }

        // Forgot password modal
        const forgotBtn = document.getElementById('forgotPassword');
        const forgotModal = document.getElementById('forgotModal');
        const closeModal = document.getElementById('closeModal');
        const sendResetBtn = document.getElementById('sendResetLink');

        forgotBtn.addEventListener('click', function(e) {
            e.preventDefault();
            forgotModal.classList.add('show');
        });

        closeModal.addEventListener('click', function() {
            forgotModal.classList.remove('show');
        });

        window.addEventListener('click', function(e) {
            if (e.target === forgotModal) {
                forgotModal.classList.remove('show');
            }
        });

        sendResetBtn.addEventListener('click', function() {
            const email = document.getElementById('resetEmail').value;
            
            if (!email || !email.includes('@')) {
                alert('Please enter a valid email address');
                return;
            }

            // Show loading
            sendResetBtn.innerHTML = '<span>Sending...</span>';
            sendResetBtn.disabled = true;

            // Simulate API call (replace with actual)
            setTimeout(() => {
                alert('Reset link sent to your email!');
                forgotModal.classList.remove('show');
                sendResetBtn.innerHTML = '<span>Send Reset Link</span>';
                sendResetBtn.disabled = false;
                document.getElementById('resetEmail').value = '';
            }, 1500);
        });

        // Handle Enter key
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && document.activeElement.tagName !== 'BUTTON') {
                e.preventDefault();
                loginForm.dispatchEvent(new Event('submit'));
            }
        });

        // Add floating label effect
        const inputs = document.querySelectorAll('.input-field');
        inputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.classList.add('focused');
            });
            
            input.addEventListener('blur', function() {
                if (!this.value) {
                    this.parentElement.classList.remove('focused');
                }
            });
        });
    </script>
</body>

</html>