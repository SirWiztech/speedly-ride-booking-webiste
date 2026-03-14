const container = document.querySelector('.container');
const registerLink = document.querySelector('.register-link');
const loginLink = document.querySelector('.login-link');

registerLink.addEventListener('click', (e) => {
    e.preventDefault();
    container.classList.add('active');
    document.getElementById('join-us').style.display = 'flex';
    document.getElementById('comet').style.display = 'none';
    document.getElementById('comet2').style.display = 'flex';
     document.getElementById('comet').classList.remove('animation');
});

loginLink.addEventListener('click', (e) => {
    e.preventDefault();
    container.classList.remove('active');
    document.getElementById('join-us').style.display = 'none';
    document.getElementById('comet').style.display = 'flex';
    document.getElementById('comet2').style.display = 'none';
    document.getElementById('comet').classList.add('animation');
});

// Prevent default form submission and use AJAX with SweetAlert
document.addEventListener('DOMContentLoaded', function () {

    // Handle Login Form
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', function (e) {
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
                .then(response => response.json())
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
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Please check your internet connection',
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
        registerForm.addEventListener('submit', function (e) {
            e.preventDefault();

            const fullname = document.getElementById('regFullname').value;
            const username = document.getElementById('regUsername').value;
            const email = document.getElementById('regEmail').value;
            const password = document.getElementById('regPassword').value;
            const role = document.getElementById('regRole').value;
            const terms = document.getElementById('terms').checked;
            const registerBtn = document.getElementById('registerBtn');

            // Validate
            if (!fullname || !username || !email || !password || !role) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please fill in all fields',
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

            if (password.length < 6) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Weak Password',
                    text: 'Password must be at least 6 characters long',
                    confirmButtonColor: '#667eea'
                });
                return;
            }

            // Generate a random phone number for demo (in production, add phone field to form)
            const phone = '+234' + Math.floor(Math.random() * 10000000000).toString().padStart(10, '0');
            document.getElementById('regPhone').value = phone;

            // Show loading
            registerBtn.disabled = true;
            registerBtn.innerHTML = '<i class="bx bx-loader-alt bx-spin"></i> Registering...';

            // Create form data
            const formData = new FormData(registerForm);

            // Send AJAX request
            fetch('SERVER/API/sign-up.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        Swal.fire({
                            icon: 'success',
                            title: 'Registration Successful!',
                            html: data.message,
                            timer: 3000,
                            showConfirmButton: true,
                            confirmButtonColor: '#667eea'
                        }).then(() => {
                            window.location.href = data.redirect || 'form.html';
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Registration Failed',
                            text: data.message,
                            confirmButtonColor: '#667eea'
                        });
                        registerBtn.disabled = false;
                        registerBtn.innerHTML = 'Register';
                    }
                })
                .catch(error => {
                    Swal.fire({
                        icon: 'error',
                        title: 'Connection Error',
                        text: 'Please check your internet connection',
                        confirmButtonColor: '#667eea'
                    });
                    registerBtn.disabled = false;
                    registerBtn.innerHTML = 'Register';
                });
        });
    }

    // Toggle between login and register forms (your existing form.js handles this)
});