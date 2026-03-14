document.addEventListener('DOMContentLoaded', function () {
    // Elements
    const togglePassword = document.getElementById('togglePassword');
    const passwordInput = document.getElementById('password');
    const loginForm = document.getElementById('adminLoginForm');
    const loginBtn = document.getElementById('loginBtn');
    const errorMessage = document.getElementById('errorMessage');
    const successMessage = document.getElementById('successMessage');
    const errorText = document.getElementById('errorText');
    const usernameInput = document.getElementById('username');

    // Password visibility toggle
    if (togglePassword && passwordInput) {
        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            this.classList.toggle('fa-eye');
            this.classList.toggle('fa-eye-slash');
        });
    }

    // Login form submission
    if (loginForm && loginBtn) {
        loginForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const username = usernameInput ? usernameInput.value.trim() : '';
            const password = passwordInput ? passwordInput.value : '';
            const rememberMe = document.getElementById('rememberMe') ? document.getElementById('rememberMe').checked : false;

            // Validation
            if (!username || !password) {
                showError('Please enter both username and password');
                return;
            }

            // Hide any previous messages
            if (errorMessage) errorMessage.style.display = 'none';
            if (successMessage) successMessage.style.display = 'none';

            // Show loading state
            loginBtn.classList.add('loading');
            loginBtn.disabled = true;

            // Create form data
            const formData = new FormData();
            formData.append('username', username);
            formData.append('password', password);
            formData.append('remember', rememberMe ? '1' : '0');

            // Send AJAX request
            fetch('admin_login.php', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loginBtn.classList.remove('loading');
                loginBtn.disabled = false;

                if (data.success) {
                    if (successMessage) {
                        successMessage.style.display = 'flex';
                    }
                    
                    setTimeout(() => {
                        window.location.href = data.redirect || 'admin_dashboard.php';
                    }, 1500);
                } else {
                    showError(data.message || 'Invalid username or password');
                    
                    if (passwordInput) {
                        passwordInput.value = '';
                        passwordInput.focus();
                    }

                    if (loginForm) {
                        loginForm.style.animation = 'shake 0.5s ease-in-out';
                        setTimeout(() => {
                            loginForm.style.animation = '';
                        }, 500);
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                loginBtn.classList.remove('loading');
                loginBtn.disabled = false;
                showError('Connection error. Please try again.');
            });
        });
    }

    function showError(message) {
        if (errorText) errorText.textContent = message;
        if (errorMessage) {
            errorMessage.style.display = 'flex';
            
            setTimeout(() => {
                errorMessage.style.display = 'none';
            }, 5000);
        }
    }

    // Input validation - optional
    if (usernameInput) {
        usernameInput.addEventListener('input', function () {
            // No restrictions needed
        });
    }

    // Prevent form resubmission on page refresh
    if (window.history.replaceState) {
        window.history.replaceState(null, null, window.location.href);
    }

    // Check for logout parameter
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('logout') === 'true') {
        showSuccessMessage('Logged out successfully');
    }

    function showSuccessMessage(message) {
        if (loginForm) {
            const successMsg = document.createElement('div');
            successMsg.className = 'success-message show';
            successMsg.innerHTML = `<i class="fas fa-check-circle"></i><span>${message}</span>`;
            loginForm.insertBefore(successMsg, loginForm.firstChild);

            setTimeout(() => {
                successMsg.remove();
            }, 3000);
        }
    }
});

// Add CSS animation
const style = document.createElement('style');
style.textContent = `
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
        20%, 40%, 60%, 80% { transform: translateX(5px); }
    }
`;
document.head.appendChild(style);