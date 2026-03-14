<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedly | Admin Login</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="./CSS/admin_login.css">
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
    <div class="modal" id="forgotModal"
        style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); align-items: center; justify-content: center; z-index: 1000;">
        <div
            style="background: white; border-radius: 20px; width: 90%; max-width: 400px; padding: 30px; animation: slideUp 0.3s ease-out;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="color: #333; font-size: 20px;">Reset Password</h3>
                <button id="closeModal"
                    style="background: none; border: none; font-size: 24px; cursor: pointer; color: #999;">&times;</button>
            </div>
            <p style="color: #666; margin-bottom: 20px; font-size: 14px;">Enter your email address and we'll send you
                instructions to reset your password.</p>
            <input type="email" placeholder="Email address"
                style="width: 100%; padding: 12px 15px; border: 2px solid #eee; border-radius: 10px; margin-bottom: 20px; font-size: 14px;">
            <button
                style="width: 100%; padding: 12px; background: #ff5e00; color: white; border: none; border-radius: 10px; font-weight: 600; cursor: pointer;">Send
                Reset Link</button>
        </div>
    </div>

    <script src="./JS/admin_login.js"></script>
</body>

</html>   