// OTP Input Handling
const otpInputs = document.querySelectorAll('.otp-box');
let timerInterval;
let timeLeft = 300; // 5 minutes in seconds
let canResend = false;

// Get email from URL or localStorage
document.addEventListener('DOMContentLoaded', function () {
    // Check if email is in URL
    const urlParams = new URLSearchParams(window.location.search);
    const emailFromUrl = urlParams.get('email');

    console.log('Email from URL:', emailFromUrl);

    if (emailFromUrl && emailFromUrl !== 'null' && emailFromUrl !== 'undefined') {
        // Decode the email
        const decodedEmail = decodeURIComponent(emailFromUrl);
        document.getElementById('emailAddress').textContent = decodedEmail;
        localStorage.setItem('verify_email', decodedEmail);
        
        // Show a message that OTP is being sent
        showAlert('info', 'Sending verification code to your email...');
        
        // Automatically request OTP when page loads with email
        setTimeout(() => {
            autoRequestOTP(decodedEmail);
        }, 1000);
    } else {
        // Try to get from localStorage
        const savedEmail = localStorage.getItem('verify_email');
        if (savedEmail && savedEmail !== 'null' && savedEmail !== 'undefined') {
            document.getElementById('emailAddress').textContent = savedEmail;
            
            // Show a message that OTP is being sent
            showAlert('info', 'Sending verification code to your email...');
            
            // Auto request OTP
            setTimeout(() => {
                autoRequestOTP(savedEmail);
            }, 1000);
        } else {
            // If no email found, show error and redirect after delay
            console.error('No email found in URL or localStorage');
            showAlert('error', 'No email address found. Redirecting to login...');
            setTimeout(() => {
                window.location.href = 'form.php';
            }, 3000);
        }
    }

    startTimer();
});

// Auto-focus next input
otpInputs.forEach((input, index) => {
    input.addEventListener('input', function () {
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

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Backspace' && !this.value && index > 0) {
            otpInputs[index - 1].focus();
        }
    });

    // Handle paste
    input.addEventListener('paste', function (e) {
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

// Timer Function
function startTimer() {
    const resendBtn = document.getElementById('resendBtn');
    const resendText = document.getElementById('resendText');

    // Clear any existing timer
    if (timerInterval) {
        clearInterval(timerInterval);
    }

    timerInterval = setInterval(() => {
        if (timeLeft <= 0) {
            clearInterval(timerInterval);
            canResend = true;
            resendBtn.disabled = false;
            resendText.textContent = 'Resend Code';
            showAlert('warning', 'OTP expired. Please request a new code.');
        } else {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            resendText.textContent = `Resend Code (${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')})`;
            
            timeLeft--;

            // Warning when 1 minute left
            if (timeLeft === 60) {
                showAlert('warning', 'Less than 1 minute remaining!');
            }
        }
    }, 1000);
}

// Auto-request OTP function
function autoRequestOTP(email) {
    console.log('Auto-requesting OTP for:', email);
    
    // Show loading on resend button
    const resendBtn = document.getElementById('resendBtn');
    resendBtn.disabled = true;
    resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending OTP...';
    
    // Create form data
    const formData = new FormData();
    formData.append('email', email);
    
    fetch('/SERVER/API/send_otp.php', {
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
            showAlert('success', 'OTP sent to your email! Please check your inbox.');
            
            // Reset timer
            timeLeft = 300;
            canResend = false;
            
            // Reset button
            resendBtn.disabled = true;
            resendBtn.innerHTML = '<i class="fas fa-redo-alt"></i> <span id="resendText">Resend Code (05:00)</span>';
            
            // Clear and restart timer
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
    const email = document.getElementById('emailAddress').textContent;

    // Check if email is valid
    if (!email || email === 'Loading...' || email === '') {
        showAlert('error', 'Email not found. Please go back and try again.');
        setTimeout(() => {
            window.location.href = 'form.php';
        }, 2000);
        return;
    }

    // Check if all fields are filled
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

    // Create form data
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
        verifyBtn.classList.remove('loading');
        verifyBtn.disabled = false;

        if (data.status === 'success') {
            // Store user role for redirect
            localStorage.setItem('user_role', data.user.role);
            
            // Clear the email from localStorage after successful verification
            localStorage.removeItem('verify_email');
            
            // Show success message
            Swal.fire({
                icon: 'success',
                title: 'Verified!',
                text: 'Your email has been verified successfully!',
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                // Redirect to dashboard
                window.location.href = data.redirect;
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
        verifyBtn.classList.remove('loading');
        verifyBtn.disabled = false;
        showAlert('error', 'Connection error. Please check if the server is running.');
        console.error('Error:', error);
    });
}

// Resend OTP
function resendOTP() {
    if (!canResend) {
        showAlert('warning', 'Please wait for the timer to expire');
        return;
    }

    const resendBtn = document.getElementById('resendBtn');
    const email = document.getElementById('emailAddress').textContent;

    // Check if email is valid
    if (!email || email === 'Loading...' || email === '') {
        showAlert('error', 'Email not found. Please refresh the page.');
        return;
    }

    // Show loading on button
    resendBtn.disabled = true;
    resendBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';

    // Create form data
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
            // Reset timer
            timeLeft = 300;
            canResend = false;

            // Reset button
            resendBtn.disabled = true;
            resendBtn.innerHTML = '<i class="fas fa-redo-alt"></i> <span id="resendText">Resend Code (05:00)</span>';

            // Clear OTP inputs
            otpInputs.forEach(input => {
                input.value = '';
                input.classList.remove('error');
            });
            otpInputs[0].focus();

            // Clear and restart timer
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
        showAlert('error', 'Connection error. Please check if the server is running.');
        resendBtn.disabled = false;
        resendBtn.innerHTML = '<i class="fas fa-redo-alt"></i> <span id="resendText">Resend Code</span>';
    });
}

// Show Alert
function showAlert(type, message) {
    const alertContainer = document.getElementById('alertContainer');

    // Clear any existing alerts
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

    // Auto hide after 4 seconds
    setTimeout(() => {
        if (alertContainer.contains(alert)) {
            alert.remove();
        }
    }, 4000);
}

// Modal Functions
function showChangeEmailModal() {
    const modal = document.getElementById('changeNumberModal');
    if (modal) {
        modal.classList.add('show');
        
        // Pre-fill with current email
        const currentEmail = document.getElementById('emailAddress').textContent;
        document.getElementById('newPhoneNumber').value = currentEmail;
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.classList.remove('show');
    }
}

function updatePhoneNumber() {
    const newEmail = document.getElementById('newPhoneNumber').value;
    
    // Basic email validation
    if (!newEmail || !newEmail.includes('@')) {
        showAlert('error', 'Please enter a valid email address');
        return;
    }
    
    document.getElementById('emailAddress').textContent = newEmail;
    localStorage.setItem('verify_email', newEmail);
    closeModal('changeNumberModal');
    showAlert('success', 'Email updated successfully');

    // Reset OTP and request new one
    timeLeft = 300;
    canResend = false;
    clearInterval(timerInterval);
    
    // Clear inputs
    otpInputs.forEach(input => {
        input.value = '';
    });
    otpInputs[0].focus();
    
    // Auto-request new OTP
    autoRequestOTP(newEmail);
}

// Redirect function
function redirectToDashboard() {
    // Get role from localStorage
    const role = localStorage.getItem('user_role') || 'client';
    
    if (role === 'driver') {
        window.location.href = '/SPEEDLY/driver_dashboard.php';
    } else {
        window.location.href = '/SPEEDLY/client_dashboard.php';
    }
}

// Close modal when clicking outside
window.addEventListener('click', function (e) {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        if (e.target === modal) {
            modal.classList.remove('show');
        }
    });
});

// Handle enter key
document.addEventListener('keypress', function (e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        verifyOTP();
    }
});

// Handle page unload to clean up
window.addEventListener('beforeunload', function() {
    if (timerInterval) {
        clearInterval(timerInterval);
    }
});


