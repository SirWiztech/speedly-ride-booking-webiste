
        // Tab switching
        const tabBtns = document.querySelectorAll('.tab-btn');
        const tabContents = document.querySelectorAll('.tab-content');

        function switchTab(tabId) {
            // Remove active class from all tabs and contents
            tabBtns.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            // Add active class to selected tab
            document.querySelector(`[data-tab="${tabId}"]`).classList.add('active');
            document.getElementById(`${tabId}-tab`).classList.add('active');
        }

        tabBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                const tabId = btn.getAttribute('data-tab');
                switchTab(tabId);
            });
        });

        // Toggle password visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = event.target;
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Profile picture upload
        const profileImage = document.getElementById('profileImage');
        const profilePic = document.getElementById('profilePic');
        const profilePicPlaceholder = document.getElementById('profilePicPlaceholder');

        profileImage.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    profilePic.src = e.target.result;
                    profilePic.style.display = 'block';
                    profilePicPlaceholder.style.display = 'none';
                    
                    showAlert('success', 'Profile picture updated successfully!');
                }
                
                reader.readAsDataURL(file);
            }
        });

        // Edit profile form submission
        document.getElementById('editProfileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const name = document.getElementById('editName').value;
            const phone = document.getElementById('editPhone').value;
            
            // Update display values
            document.getElementById('displayName').textContent = name;
            document.getElementById('viewName').textContent = name;
            document.getElementById('viewPhone').textContent = phone;
            
            // Update placeholder initials
            const initials = name.split(' ').map(n => n[0]).join('').toUpperCase();
            profilePicPlaceholder.textContent = initials;
            
            showAlert('success', 'Profile updated successfully!');
            
            // Switch back to view tab
            setTimeout(() => switchTab('view'), 1500);
        });

        // Change password form submission
        document.getElementById('changePasswordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const currentPassword = document.getElementById('currentPassword').value;
            const newPassword = document.getElementById('newPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            // Simple validation
            if (newPassword.length < 8) {
                showAlert('error', 'Password must be at least 8 characters long');
                return;
            }
            
            if (newPassword !== confirmPassword) {
                showAlert('error', 'Passwords do not match');
                return;
            }
            
            // In real app, verify current password with server
            
            // Clear form
            this.reset();
            
            // Show success modal
            document.getElementById('successModal').classList.add('show');
            
            // Switch back to view tab after modal closes
        });

        // Show alert function
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            
            const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
            
            alert.innerHTML = `
                <i class="fas ${icon}"></i>
                <span>${message}</span>
            `;
            
            alertContainer.innerHTML = '';
            alertContainer.appendChild(alert);
            
            // Auto hide after 3 seconds
            setTimeout(() => {
                alert.remove();
            }, 3000);
        }

        // Modal functions
        function closeModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
            switchTab('view');
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(e) {
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                if (e.target === modal) {
                    modal.classList.remove('show');
                    switchTab('view');
                }
            });
        });

        // Load user data (simulated)
        function loadUserData() {
            // In real app, fetch from server
            const userData = {
                name: 'John Doe',
                email: 'john.doe@example.com',
                phone: '+234 801 234 5678',
                registered: 'January 15, 2024',
                rides: 24,
                spent: '₦45,800',
                status: 'active'
            };
            
            // Populate all fields
            document.getElementById('displayName').textContent = userData.name;
            document.getElementById('displayEmail').textContent = userData.email;
            document.getElementById('viewName').textContent = userData.name;
            document.getElementById('viewEmail').textContent = userData.email;
            document.getElementById('viewPhone').textContent = userData.phone;
            document.getElementById('viewRegistered').textContent = userData.registered;
            document.getElementById('totalRides').textContent = userData.rides;
            document.getElementById('totalSpent').textContent = userData.spent;
            document.getElementById('memberSince').textContent = userData.registered;
            document.getElementById('editName').value = userData.name;
            document.getElementById('editEmail').value = userData.email;
            document.getElementById('editPhone').value = userData.phone;
            
            // Set placeholder initials
            const initials = userData.name.split(' ').map(n => n[0]).join('').toUpperCase();
            profilePicPlaceholder.textContent = initials;
        }

        // Initialize on load
        loadUserData();
    