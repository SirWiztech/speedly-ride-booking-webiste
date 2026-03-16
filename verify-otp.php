<?php
// Start session
session_start();

// Get email from URL parameter
$email = isset($_GET['email']) ? urldecode($_GET['email']) : '';

// If no email in URL, check session
if (empty($email) && isset($_SESSION['pending_email'])) {
    $email = $_SESSION['pending_email'];
} elseif (empty($email)) {
    // If still no email, try to get from POST (for API calls)
    $email = $_POST['email'] ?? '';
}

// Store in session for later use
if (!empty($email)) {
    $_SESSION['pending_email'] = $email;
}

// Get any message from URL
$message = isset($_GET['message']) ? urldecode($_GET['message']) : '';
$messageType = isset($_GET['type']) ? $_GET['type'] : 'info';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <meta name="description" content="Verify your email address with OTP">
    <title>Verify OTP - Speedly</title>
    
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
    box-shadow: 0 20px 60px rgba(255, 94, 0, 0.3);
    width: 100%;
    max-width: 500px;
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

.email-display {
    background: #fff8f0;
    border-radius: 10px;
    padding: 15px;
    margin-bottom: 25px;
    text-align: center;
    border: 1px solid #ffe0b2;
}

.email-display label {
    display: block;
    color: #ff5e00;
    font-size: 12px;
    margin-bottom: 5px;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}

.email-display .email {
    color: #ff5e00;
    font-size: 16px;
    font-weight: 600;
    word-break: break-all;
}

.otp-container {
    display: flex;
    gap: 10px;
    justify-content: center;
    margin-bottom: 25px;
    flex-wrap: wrap;
}

.otp-box {
    width: 60px;
    height: 70px;
    border: 2px solid #ffe0b2;
    border-radius: 10px;
    font-size: 32px;
    font-weight: bold;
    text-align: center;
    color: #333;
    transition: all 0.3s ease;
    background: white;
}

.otp-box:focus {
    outline: none;
    border-color: #ff5e00;
    box-shadow: 0 0 0 3px rgba(255, 94, 0, 0.1);
    transform: scale(1.05);
}

.otp-box.error {
    border-color: #dc3545;
    animation: shake 0.5s ease;
}

@keyframes shake {
    0%, 100% { transform: translateX(0); }
    25% { transform: translateX(-5px); }
    75% { transform: translateX(5px); }
}

.timer {
    text-align: center;
    margin-bottom: 20px;
    font-size: 14px;
    color: #666;
}

.timer i {
    color: #ff5e00;
    margin-right: 5px;
}

.timer .time {
    font-weight: bold;
    color: #ff5e00;
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
    transition: all 0.3s ease;
    margin-bottom: 15px;
    position: relative;
    overflow: hidden;
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

.btn.loading {
    pointer-events: none;
    opacity: 0.8;
}

.btn.loading::after {
    content: '';
    position: absolute;
    width: 20px;
    height: 20px;
    top: 50%;
    left: 50%;
    margin-left: -10px;
    margin-top: -10px;
    border: 2px solid white;
    border-top-color: transparent;
    border-radius: 50%;
    animation: spinner 0.8s linear infinite;
}

@keyframes spinner {
    to { transform: rotate(360deg); }
}

.resend-section {
    text-align: center;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid #ffe0b2;
}

.resend-btn {
    background: none;
    border: none;
    color: #ff5e00;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: color 0.3s ease;
    padding: 10px 20px;
}

.resend-btn:hover:not(:disabled) {
    color: #ff8c3a;
    text-decoration: underline;
}

.resend-btn:disabled {
    color: #999;
    cursor: not-allowed;
}

.resend-btn i {
    margin-right: 5px;
}

.change-email {
    text-align: center;
    margin-top: 15px;
}

.change-email a {
    color: #ff8c3a;
    font-size: 13px;
    text-decoration: none;
    transition: color 0.3s ease;
}

.change-email a:hover {
    color: #ff5e00;
    text-decoration: underline;
}

.change-email i {
    margin-right: 5px;
}

.alert-container {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    max-width: 350px;
}

.alert {
    padding: 15px 20px;
    border-radius: 10px;
    background: white;
    box-shadow: 0 5px 15px rgba(255, 94, 0, 0.2);
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 10px;
    animation: slideInRight 0.3s ease;
    border-left: 4px solid;
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
    border-left-color: #28a745;
}

.alert-success i {
    color: #28a745;
}

.alert-error {
    border-left-color: #dc3545;
}

.alert-error i {
    color: #dc3545;
}

.alert-warning {
    border-left-color: #ffc107;
}

.alert-warning i {
    color: #ffc107;
}

.alert-info {
    border-left-color: #17a2b8;
}

.alert-info i {
    color: #17a2b8;
}

.alert i {
    font-size: 20px;
}

.modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 2000;
    justify-content: center;
    align-items: center;
}

.modal.show {
    display: flex;
}

.modal-content {
    background: white;
    border-radius: 20px;
    padding: 30px;
    max-width: 400px;
    width: 90%;
    animation: modalSlideIn 0.3s ease;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-content h3 {
    color: #333;
    margin-bottom: 20px;
    font-size: 20px;
}

.modal-content input {
    width: 100%;
    padding: 12px;
    border: 2px solid #ffe0b2;
    border-radius: 10px;
    font-size: 16px;
    margin-bottom: 20px;
    transition: border-color 0.3s ease;
}

.modal-content input:focus {
    outline: none;
    border-color: #ff5e00;
}

.modal-buttons {
    display: flex;
    gap: 10px;
}

.modal-buttons button {
    flex: 1;
    padding: 12px;
    border: none;
    border-radius: 10px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
}

.modal-buttons .cancel-btn {
    background: #f8f9fa;
    color: #666;
}

.modal-buttons .cancel-btn:hover {
    background: #e0e0e0;
}

.modal-buttons .save-btn {
    background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%);
    color: white;
}

.modal-buttons .save-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(255, 94, 0, 0.4);
}

@media (max-width: 480px) {
    .container {
        padding: 30px 20px;
    }

    .otp-box {
        width: 45px;
        height: 55px;
        font-size: 24px;
    }
}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <i class="fas fa-shield-alt"></i>
            <h2>Verify Your Email</h2>
            <p>We've sent a 6-digit verification code to your email address</p>
        </div>

        <?php if (empty($email)): ?>
        <div class="email-display" style="background: #fff3cd; border-color: #ffc107;">
            <i class="fas fa-exclamation-triangle" style="color: #856404; font-size: 24px; margin-bottom: 10px;"></i>
            <p style="color: #856404;">No email address provided. Please enter your email below.</p>
        </div>
        
        <div class="form-group" style="margin-bottom: 20px;">
            <input type="email" id="manualEmail" placeholder="Enter your email address" style="width: 100%; padding: 15px; border: 2px solid #e0e0e0; border-radius: 10px; font-size: 16px;">
        </div>
        
        <button class="btn" onclick="setManualEmail()">
            <i class="fas fa-arrow-right"></i> Continue
        </button>
        <?php else: ?>
        
        <div class="email-display">
            <label>Verification email</label>
            <div class="email" id="emailAddress"><?php echo htmlspecialchars($email); ?></div>
        </div>

        <div class="otp-container" id="otpContainer">
            <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
            <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
            <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
            <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
            <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
            <input type="text" class="otp-box" maxlength="1" pattern="[0-9]" inputmode="numeric">
        </div>

        <div class="timer">
            <i class="fas fa-hourglass-half"></i>
            <span>Code expires in: <span class="time" id="timer">05:00</span></span>
        </div>

        <button class="btn" id="verifyBtn" onclick="verifyOTP()">
            <i class="fas fa-check-circle" style="margin-right: 10px;"></i>Verify Email
        </button>

        <div class="resend-section">
            <button class="resend-btn" id="resendBtn" onclick="resendOTP()" disabled>
                <i class="fas fa-redo-alt"></i>
                <span id="resendText">Resend Code (05:00)</span>
            </button>
        </div>

        <div class="change-email">
            <a href="#" onclick="showChangeEmailModal()">
                <i class="fas fa-envelope"></i>
                Change email address
            </a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Change Email Modal -->
    <div class="modal" id="changeEmailModal">
        <div class="modal-content">
            <h3>Change Email Address</h3>
            <input type="email" id="newEmail" placeholder="Enter new email address" value="<?php echo htmlspecialchars($email); ?>">
            <div class="modal-buttons">
                <button class="cancel-btn" onclick="closeModal('changeEmailModal')">Cancel</button>
                <button class="save-btn" onclick="updateEmail()">Update</button>
            </div>
        </div>
    </div>

    <!-- Alert Container -->
    <div class="alert-container" id="alertContainer"></div>

    <?php if (!empty($message)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showAlert('<?php echo $messageType; ?>', '<?php echo addslashes($message); ?>');
        });
    </script>
    <?php endif; ?>

    <script>
        // Get email from PHP
        const userEmail = '<?php echo addslashes($email); ?>';
        
        // OTP Input Handling
        let otpInputs = [];
        let timerInterval;
        let timeLeft = 300; // 5 minutes in seconds
        let canResend = false;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing OTP page...');
            
            otpInputs = document.querySelectorAll('.otp-box');
            
            if (otpInputs.length > 0) {
                // Focus first input
                otpInputs[0].focus();
                
                // Auto-request OTP when page loads
                if (userEmail && userEmail !== '') {
                    setTimeout(() => {
                        autoRequestOTP(userEmail);
                    }, 1000);
                }
                
                startTimer();
                setupOtpInputs();
            }
        });

        // Setup OTP inputs
        function setupOtpInputs() {
            otpInputs.forEach((input, index) => {
                input.addEventListener('input', function() {
                    // Only allow numbers
                    this.value = this.value.replace(/[^0-9]/g, '');

                    if (this.value.length === 1) {
                        if (index < otpInputs.length - 1) {
                            otpInputs[index + 1].focus();
                        }
                    }

                    // Remove error class when user starts typing
                    this.classList.remove('error');
                });

                input.addEventListener('keydown', function(e) {
                    if (e.key === 'Backspace' && !this.value && index > 0) {
                        otpInputs[index - 1].focus();
                    }
                });

                // Handle paste
                input.addEventListener('paste', function(e) {
                    e.preventDefault();
                    const pasteData = e.clipboardData.getData('text').replace(/[^0-9]/g, '');

                    if (pasteData.length === 6) {
                        pasteData.split('').forEach((char, i) => {
                            if (otpInputs[i]) {
                                otpInputs[i].value = char;
                            }
                        });
                        otpInputs[5].focus();
                    }
                });
            });
        }

        // Timer Function
        function startTimer() {
            const timerElement = document.getElementById('timer');
            const resendBtn = document.getElementById('resendBtn');
            const resendText = document.getElementById('resendText');

            if (!timerElement || !resendBtn || !resendText) return;

            // Clear any existing timer
            if (timerInterval) {
                clearInterval(timerInterval);
            }

            timerInterval = setInterval(() => {
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    canResend = true;
                    if (resendBtn) {
                        resendBtn.disabled = false;
                        resendText.textContent = 'Resend Code';
                    }
                    showAlert('warning', 'OTP expired. Please request a new code.');
                } else {
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;
                    if (timerElement) {
                        timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    }
                    if (resendText) {
                        resendText.textContent = `Resend Code (${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')})`;
                    }
                    
                    timeLeft--;

                    // Warning when 1 minute left
                    if (timeLeft === 60) {
                        showAlert('warning', 'Less than 1 minute remaining!');
                    }
                }
            }, 1000);
        }

        // Set manual email
        function setManualEmail() {
            const email = document.getElementById('manualEmail').value;
            if (!email || !email.includes('@')) {
                showAlert('error', 'Please enter a valid email address');
                return;
            }
            
            window.location.href = 'verify-otp.php?email=' + encodeURIComponent(email);
        }

        // Auto-request OTP
        function autoRequestOTP(email) {
            console.log('Auto-requesting OTP for:', email);
            
            const resendBtn = document.getElementById('resendBtn');
            if (!resendBtn) return;
            
            resendBtn.disabled = true;
            resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span id="resendText">Sending OTP...</span>';
            
            const formData = new FormData();
            formData.append('email', email);
            
            fetch('SERVER/API/send_otp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Send OTP response:', data);
                
                if (data.status === 'success') {
                    showAlert('success', 'OTP sent to your email! Please check your inbox.');
                    
                    timeLeft = 300;
                    canResend = false;
                    
                    resendBtn.disabled = true;
                    resendBtn.innerHTML = '<i class="fas fa-redo-alt"></i> <span id="resendText">Resend Code (05:00)</span>';
                    
                    clearInterval(timerInterval);
                    startTimer();
                } else {
                    showAlert('error', data.message || 'Failed to send OTP');
                    resendBtn.disabled = false;
                    resendBtn.innerHTML = '<i class="fas fa-redo-alt"></i> <span id="resendText">Resend Code</span>';
                }
            })
            .catch(error => {
                console.error('Error sending OTP:', error);
                showAlert('error', 'Connection error. Please check if the server is running.');
                resendBtn.disabled = false;
                resendBtn.innerHTML = '<i class="fas fa-redo-alt"></i> <span id="resendText">Resend Code</span>';
            });
        }

        // Verify OTP
        function verifyOTP() {
            const verifyBtn = document.getElementById('verifyBtn');
            const otpCode = Array.from(otpInputs).map(input => input.value).join('');
            const email = document.getElementById('emailAddress')?.textContent || userEmail;

            if (!email || email === '') {
                showAlert('error', 'Email not found. Please refresh the page.');
                return;
            }

            if (otpCode.length < 6) {
                otpInputs.forEach(input => {
                    if (!input.value) input.classList.add('error');
                });
                showAlert('error', 'Please enter the complete 6-digit code');
                return;
            }

            // Show loading
            verifyBtn.classList.add('loading');
            verifyBtn.disabled = true;

            const formData = new FormData();
            formData.append('email', email);
            formData.append('otp', otpCode);

            fetch('SERVER/API/verify_otp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Verify OTP response:', data);
                
                verifyBtn.classList.remove('loading');
                verifyBtn.disabled = false;

                if (data.status === 'success') {
                    // Clear session
                    sessionStorage.removeItem('pending_email');
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Verified!',
                        text: 'Your email has been verified successfully!',
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        window.location.href = data.redirect || 'form.php';
                    });
                } else {
                    showAlert('error', data.message);
                    otpInputs.forEach(input => {
                        input.classList.add('error');
                        input.value = '';
                    });
                    otpInputs[0].focus();
                }
            })
            .catch(error => {
                console.error('Error:', error);
                verifyBtn.classList.remove('loading');
                verifyBtn.disabled = false;
                showAlert('error', 'Connection error. Please check if the server is running.');
            });
        }

        // Resend OTP
        function resendOTP() {
            if (!canResend) {
                showAlert('warning', 'Please wait for the timer to expire');
                return;
            }

            const resendBtn = document.getElementById('resendBtn');
            const email = document.getElementById('emailAddress')?.textContent || userEmail;

            if (!email || email === '') {
                showAlert('error', 'Email not found. Please refresh the page.');
                return;
            }

            resendBtn.disabled = true;
            resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span id="resendText">Sending...</span>';

            const formData = new FormData();
            formData.append('email', email);

            fetch('SERVER/API/send_otp.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Network response was not ok: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                if (data.status === 'success') {
                    timeLeft = 300;
                    canResend = false;

                    resendBtn.disabled = true;
                    resendBtn.innerHTML = '<i class="fas fa-redo-alt"></i> <span id="resendText">Resend Code (05:00)</span>';

                    otpInputs.forEach(input => {
                        input.value = '';
                        input.classList.remove('error');
                    });
                    otpInputs[0].focus();

                    clearInterval(timerInterval);
                    startTimer();

                    showAlert('success', 'New OTP has been sent to your email');
                } else {
                    showAlert('error', data.message);
                    resendBtn.disabled = false;
                    resendBtn.innerHTML = '<i class="fas fa-redo-alt"></i> <span id="resendText">Resend Code</span>';
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('error', 'Connection error. Please check if the server is running.');
                resendBtn.disabled = false;
                resendBtn.innerHTML = '<i class="fas fa-redo-alt"></i> <span id="resendText">Resend Code</span>';
            });
        }

        // Show Alert
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            if (!alertContainer) return;

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
                if (alertContainer.contains(alert)) {
                    alert.remove();
                }
            }, 4000);
        }

        // Modal Functions
        function showChangeEmailModal() {
            const modal = document.getElementById('changeEmailModal');
            const newEmailInput = document.getElementById('newEmail');
            const currentEmail = document.getElementById('emailAddress')?.textContent || userEmail;
            
            if (newEmailInput) {
                newEmailInput.value = currentEmail;
            }
            
            if (modal) {
                modal.classList.add('show');
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('show');
            }
        }

        function updateEmail() {
            const newEmail = document.getElementById('newEmail').value;
            
            if (!newEmail || !newEmail.includes('@')) {
                showAlert('error', 'Please enter a valid email address');
                return;
            }
            
            const emailDisplay = document.getElementById('emailAddress');
            if (emailDisplay) {
                emailDisplay.textContent = newEmail;
            }
            
            closeModal('changeEmailModal');
            showAlert('success', 'Email updated successfully');

            timeLeft = 300;
            canResend = false;
            clearInterval(timerInterval);
            
            otpInputs.forEach(input => {
                input.value = '';
            });
            
            if (otpInputs.length > 0) {
                otpInputs[0].focus();
            }
            
            autoRequestOTP(newEmail);
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                }
            });
        });

        // Handle enter key
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && document.activeElement?.classList.contains('otp-box')) {
                e.preventDefault();
                verifyOTP();
            }
        });

        // Handle page unload
        window.addEventListener('beforeunload', function() {
            if (timerInterval) {
                clearInterval(timerInterval);
            }
        });
    </script>
</body>
</html>