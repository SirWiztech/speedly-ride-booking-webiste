<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SPEEDLY-LOGIN</title>
    <!-- SweetAlert2 for beautiful alerts -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="stylesheet" href="./CSS/form.css">
</head>

<body>
    <div id="container" class="container flex justify-center items-center flex-col">
        <div class="curved-shape"></div>
        <div class="curved-shape2"></div>

        <!-- Login Form -->
        <div class="form-box Login">
            <h2 class="animation" style="--D:0; --S:21">Login</h2>
            <form id="loginForm" method="POST" action="SERVER/API/sign-in.php">
                <div class="input-box animation" style="--D:1; --S:22">
                    <input type="text" name="username" id="loginUsername" required>
                    <label id="label">Username or Email</label>
                    <i class="bx bxs-user"></i>
                </div>
                <div class="input-box animation" style="--D:2; --S:23">
                    <input type="password" name="password" id="loginPassword" required>
                    <label id="label">Password</label>
                    <i class="bx bxs-lock-alt"></i>
                    <p style="float: left; font-size: small;">
                        <a href="forgot-password.php" style="color: #667eea; text-decoration: none;">Forgot Password?</a>
                    </p>
                </div>
                <br>
                <div class="input-box animation" style="--D:3; --S:24">
                    <button class="btn" type="submit" id="loginBtn">Login</button>
                </div>
                <div class="regi-link animation" style="--D:4; --S:25">
                    <p>Don't have an account? <a href="#" class="register-link">Sign Up</a></p>
                </div>
            </form>
        </div>

        <!-- comet 1 -->
        <div class="info-content Login">
            <div id="comet" class="comet-orbit-container">
                <div class="profile-img">
                    <img src="./main-assets/logo.png" alt="Profile">
                </div>
                <div class="comet-path">
                    <div class="comet"></div>
                </div>
            </div>

            <h2 id="animation3" class="animation" style="--D:0; --S:20; text-align: center;">
                👋 WELCOME BACK!
            </h2>

            <p id="animation4" class="animation" style="--D:1; --S:21">
                🚀 Access your dashboard and continue your journey with Speedly.
            </p>
        </div>

        <!-- Register form -->
        <div class="form-box Register" id="form-box1">
            <h2 class="animation" style="--li:17; --S:0;">Register</h2>
            <form id="registerForm" method="POST" action="SERVER/API/sign-up.php">
                <div style="display: flex; gap: 10px;">
                    <div class="input-box animation" style="--li:18; --S:1; flex: 1;">
                        <input type="text" name="fullname" id="regFullname" required>
                        <label>Fullname</label>
                        <i class="bx bxs-user"></i>
                    </div>
                    <div class="input-box animation" style="--li:18; --S:1; flex: 1;">
                        <input type="text" name="username" id="regUsername" required>
                        <label>Username</label>
                        <i class="bx bxs-user-circle"></i>
                    </div>
                </div>

                <div class="input-box animation" style="--li:19; --S:2;">
                    <input type="email" name="email" id="regEmail" required>
                    <label>Email</label>
                    <i class="bx bxs-envelope"></i>
                </div>

                <div class="input-box animation" style="--li:19; --S:2;">
                    <input type="password" name="password" id="regPassword" required>
                    <label>Password</label>
                    <i class="bx bxs-lock-alt"></i>
                </div>

                <div class="input-box animation" style="--li:19; --S:2;">
                    <select name="role" id="regRole" required>
                        <option value="" disabled selected>Select Role</option>
                        <option value="client">Customer</option>
                        <option value="driver">Driver</option>
                    </select>
                    <i class="bx bxs-briefcase"></i>
                </div>

                <!-- Hidden phone field - you can add this to your form or generate in JS -->
                <input type="hidden" name="phone" id="regPhone" value="+2340000000000">

                <div class="input-box animation terms-layout" style="--li:20; --S:3;">
                    <input type="checkbox" id="terms" required>
                    <span>I agree to the terms and conditions</span>
                </div>

                <div class="input-box animation" style="--li:20; --S:3;">
                    <button class="btn" type="submit" id="registerBtn">Register</button>
                </div>

                <div class="regi-link animation" style="--li:21; --S:4">
                    <p>Have an account? <a href="#" class="login-link">Sign In</a></p>
                </div>
            </form>
        </div>
        <!-- Comet 2 -->
        <div class="info-content Register" id="join-us">
            <div id="comet2" class=" comet-orbit-container" style="--li:22; --S:0;">
                <div class="profile-img">
                    <img src="./main-assets/logo.png" alt="Profile">
                </div>
                <div class="comet-path">
                    <div class="comet"></div>
                </div>
            </div>

            <h2 id="animation1" class="animation" style="--li:23; --S:0; text-align: center;">
                <i class='bx bxs-user-plus'></i> JOIN US!
            </h2>

            <p id="animation2" class="animation" style="--li:24; --S:1">
                <i class='bx bxs-rocket'></i> Create an account to start using all your features today.
            </p>
        </div>
    </div>

    <script src="./JS/form.js"></script>
    
</body>

</html>    