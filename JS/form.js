// Form toggle functionality
const container = document.querySelector('.container');
const registerLink = document.querySelector('.register-link');
const loginLink = document.querySelector('.login-link');

if (registerLink) {
    registerLink.addEventListener('click', (e) => {
        e.preventDefault();
        container.classList.add('active');
        document.getElementById('join-us').style.display = 'flex';
        document.getElementById('comet').style.display = 'none';
        document.getElementById('comet2').style.display = 'flex';
        document.getElementById('comet').classList.remove('animation');
    });
}

if (loginLink) {
    loginLink.addEventListener('click', (e) => {
        e.preventDefault();
        container.classList.remove('active');
        document.getElementById('join-us').style.display = 'none';
        document.getElementById('comet').style.display = 'flex';
        document.getElementById('comet2').style.display = 'none';
        document.getElementById('comet').classList.add('animation');
    });
}

// Password strength checker for registration
function checkPasswordStrength(password) {
    let strength = 0;
    const requirements = {
        length: password.length >= 6,
        uppercase: /[A-Z]/.test(password),
        lowercase: /[a-z]/.test(password),
        number: /[0-9]/.test(password),
        special: /[!@#$%^&*]/.test(password)
    };
    
    // Count satisfied requirements
    strength = Object.values(requirements).filter(Boolean).length;
    
    return {
        score: strength,
        requirements: requirements,
        text: strength <= 2 ? 'Weak' : strength <= 3 ? 'Medium' : 'Strong',
        color: strength <= 2 ? '#dc3545' : strength <= 3 ? '#ffc107' : '#28a745'
    };
}

// Add phone input field if it doesn't exist
document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    if (registerForm && !document.getElementById('regPhone')) {
        // Create hidden phone input
        const phoneInput = document.createElement('input');
        phoneInput.type = 'hidden';
        phoneInput.id = 'regPhone';
        phoneInput.name = 'phone';
        registerForm.appendChild(phoneInput);
    }
});

// Handle Login Form
const loginForm = document.getElementById('loginForm');
if (loginForm) {
    loginForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const username = document.getElementById('loginUsername').value;
        const password = document.getElementById('loginPassword').value;
        const loginBtn = document.getElementById('loginBtn');

        if (!username || !password) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please enter both username and password',
                confirmButtonColor: '#667eea'
            });
            return;
        }

        // Show loading
        loginBtn.disabled = true;
        loginBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Logging in...';

        // Create form data
        const formData = new FormData();
        formData.append('username', username);
        formData.append('password', password);

        // Send AJAX request
        fetch('SERVER/API/sign-in.php', {
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
                    title: 'Login Successful!',
                    text: data.message,
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = data.redirect;
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Login Failed',
                    text: data.message,
                    confirmButtonColor: '#667eea'
                });
                loginBtn.disabled = false;
                loginBtn.innerHTML = 'Login';
            }
        })
        .catch(error => {
            console.error('Login error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Connection Error',
                text: 'Unable to connect to the server. Please try again.',
                confirmButtonColor: '#667eea'
            });
            loginBtn.disabled = false;
            loginBtn.innerHTML = 'Login';
        });
    });
}

// Handle Register Form
const registerForm = document.getElementById('registerForm');
if (registerForm) {
    
    // Add password strength indicator
    const passwordInput = document.getElementById('regPassword');
    if (passwordInput) {
        const strengthDiv = document.createElement('div');
        strengthDiv.className = 'password-strength';
        strengthDiv.style.cssText = 'margin-top: 5px; height: 4px; background: #e0e0e0; border-radius: 2px; overflow: hidden;';
        
        const strengthBar = document.createElement('div');
        strengthBar.className = 'strength-bar';
        strengthBar.style.cssText = 'height: 100%; width: 0; transition: all 0.3s ease;';
        
        strengthDiv.appendChild(strengthBar);
        passwordInput.parentNode.appendChild(strengthDiv);
        
        const strengthText = document.createElement('small');
        strengthText.className = 'strength-text';
        strengthText.style.cssText = 'display: block; text-align: right; font-size: 12px; margin-top: 2px;';
        passwordInput.parentNode.appendChild(strengthText);
        
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            const result = checkPasswordStrength(password);
            
            // Update strength bar
            const width = (result.score / 5) * 100;
            strengthBar.style.width = width + '%';
            strengthBar.style.backgroundColor = result.color;
            strengthText.textContent = 'Password Strength: ' + result.text;
            strengthText.style.color = result.color;
        });
    }
    
    registerForm.addEventListener('submit', function(e) {
        e.preventDefault();

        const fullname = document.getElementById('regFullname').value;
        const username = document.getElementById('regUsername').value;
        const email = document.getElementById('regEmail').value;
        const password = document.getElementById('regPassword').value;
        const role = document.getElementById('regRole').value;
        const terms = document.getElementById('terms').checked;
        const registerBtn = document.getElementById('registerBtn');

        // Validate all fields
        if (!fullname || !username || !email || !password || !role) {
            Swal.fire({
                icon: 'warning',
                title: 'Missing Information',
                text: 'Please fill in all fields',
                confirmButtonColor: '#667eea'
            });
            return;
        }

        // Validate email format
        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailPattern.test(email)) {
            Swal.fire({
                icon: 'warning',
                title: 'Invalid Email',
                text: 'Please enter a valid email address',
                confirmButtonColor: '#667eea'
            });
            return;
        }

        if (!terms) {
            Swal.fire({
                icon: 'warning',
                title: 'Terms & Conditions',
                text: 'You must agree to the terms and conditions',
                confirmButtonColor: '#667eea'
            });
            return;
        }

        // Check password strength
        const passwordStrength = checkPasswordStrength(password);
        if (passwordStrength.score < 3) {
            Swal.fire({
                icon: 'warning',
                title: 'Weak Password',
                text: 'Please use a stronger password with uppercase, lowercase, and numbers',
                confirmButtonColor: '#667eea'
            });
            return;
        }

        // Generate a random phone number (in production, add phone field to form)
        const phone = '+234' + Math.floor(Math.random() * 1000000000).toString().padStart(9, '0');
        
        // Set the phone value
        const phoneInput = document.getElementById('regPhone');
        if (phoneInput) {
            phoneInput.value = phone;
        }

        // Show loading
        registerBtn.disabled = true;
        registerBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Registering...';

        // Create form data
        const formData = new FormData(registerForm);
        
        // Add phone if not in form
        if (!formData.has('phone')) {
            formData.append('phone', phone);
        }

        // Log form data for debugging
        console.log('Submitting registration form with:');
        for (let pair of formData.entries()) {
            console.log(pair[0] + ': ' + pair[1]);
        }

        // Send AJAX request
        fetch('SERVER/API/sign-up.php', {
            method: 'POST',
            body: formData
        })
        .then(response => {
            console.log('Response status:', response.status);
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            console.log('Registration response:', data);
            
            if (data.status === 'success') {
                // Show success message
                Swal.fire({
                    icon: 'success',
                    title: 'Registration Successful!',
                    html: 'Please check your email for the verification code.',
                    timer: 3000,
                    showConfirmButton: true,
                    confirmButtonColor: '#667eea'
                }).then(() => {
                    // Redirect to OTP verification page
                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        // Fallback redirect with email
                        window.location.href = '/SPEEDLY/verify-otp.php?email=' + encodeURIComponent(email);
                    }
                });
            } else {
                // Show error message
                Swal.fire({
                    icon: 'error',
                    title: 'Registration Failed',
                    text: data.message || 'An error occurred during registration',
                    confirmButtonColor: '#667eea'
                });
                
                // Reset button
                registerBtn.disabled = false;
                registerBtn.innerHTML = 'Register';
            }
        })
        .catch(error => {
            console.error('Registration error:', error);
            Swal.fire({
                icon: 'error',
                title: 'Connection Error',
                text: error.message || 'Unable to connect to the server. Please try again.',
                confirmButtonColor: '#667eea'
            });
            
            // Reset button
            registerBtn.disabled = false;
            registerBtn.innerHTML = 'Register';
        });
    });
}

// Add real-time validation for registration form
document.addEventListener('DOMContentLoaded', function() {
    
    // Email validation on blur
    const emailInput = document.getElementById('regEmail');
    if (emailInput) {
        emailInput.addEventListener('blur', function() {
            const email = this.value;
            const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailPattern.test(email)) {
                this.style.borderColor = '#dc3545';
                showTooltip(this, 'Please enter a valid email address');
            } else {
                this.style.borderColor = '#28a745';
                hideTooltip(this);
            }
        });
    }
    
    // Password match validation if confirm password exists
    const confirmPassword = document.getElementById('regConfirmPassword');
    if (confirmPassword && passwordInput) {
        confirmPassword.addEventListener('input', function() {
            const password = passwordInput.value;
            const confirm = this.value;
            
            if (confirm && password !== confirm) {
                this.style.borderColor = '#dc3545';
                showTooltip(this, 'Passwords do not match');
            } else if (confirm) {
                this.style.borderColor = '#28a745';
                hideTooltip(this);
            }
        });
    }
});

// Helper function to show tooltip
function showTooltip(element, message) {
    let tooltip = element.nextElementSibling;
    if (!tooltip || !tooltip.classList.contains('tooltip')) {
        tooltip = document.createElement('span');
        tooltip.className = 'tooltip';
        tooltip.style.cssText = 'position: absolute; background: #dc3545; color: white; padding: 5px 10px; border-radius: 5px; font-size: 12px; bottom: 100%; left: 0; margin-bottom: 5px; white-space: nowrap;';
        element.parentNode.style.position = 'relative';
        element.parentNode.appendChild(tooltip);
    }
    tooltip.textContent = message;
    tooltip.style.display = 'block';
}

function hideTooltip(element) {
    const tooltip = element.nextElementSibling;
    if (tooltip && tooltip.classList.contains('tooltip')) {
        tooltip.style.display = 'none';
    }
}

// Toggle password visibility (if you have eye icons)
function togglePassword(inputId, icon) {
    const input = document.getElementById(inputId);
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bx-hide');
        icon.classList.add('bx-show');
    } else {
        input.type = 'password';
        icon.classList.remove('bx-show');
        icon.classList.add('bx-hide');
    }
}

// Export functions if using modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        togglePassword,
        checkPasswordStrength
    };
}