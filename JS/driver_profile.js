
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

        // Availability toggle
        function setAvailability(status) {
            const availableBtn = document.querySelector('.availability-option.available');
            const offlineBtn = document.querySelector('.availability-option.offline');
            
            if (status === 'available') {
                availableBtn.classList.add('active');
                offlineBtn.classList.remove('active');
                showAlert('success', 'You are now available for rides');
            } else {
                offlineBtn.classList.add('active');
                availableBtn.classList.remove('active');
                showAlert('info', 'You are now offline');
            }
        }

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

        // Vehicle form submission
        document.getElementById('vehicleForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const carModel = document.getElementById('carModel').value;
            const plateNumber = document.getElementById('plateNumber').value;
            const licenseNumber = document.getElementById('licenseNumber').value;
            
            // Update display values
            document.getElementById('viewCarType').textContent = carModel;
            document.getElementById('viewPlate').textContent = plateNumber;
            document.getElementById('viewLicense').textContent = licenseNumber;
            document.getElementById('vehiclePlate').textContent = plateNumber;
            document.getElementById('vehicleLicense').textContent = licenseNumber;
            
            showAlert('success', 'Vehicle information updated successfully!');
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
            
            // Clear form
            this.reset();
            
            // Show success modal
            document.getElementById('successMessage').textContent = 'Password changed successfully!';
            document.getElementById('successModal').classList.add('show');
            
            // Switch back to view tab after modal closes
        });

        // Withdrawal request
        function requestWithdrawal() {
            document.getElementById('withdrawalModal').classList.add('show');
        }

        // Withdrawal form submission
        document.getElementById('withdrawalForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const amount = document.getElementById('withdrawalAmount').value;
            
            if (amount > 45800) {
                showAlert('error', 'Insufficient balance');
                return;
            }
            
            closeModal('withdrawalModal');
            document.getElementById('successMessage').textContent = 'Withdrawal request submitted successfully!';
            document.getElementById('successModal').classList.add('show');
        });

        // Show alert function
        function showAlert(type, message) {
            const alertContainer = document.getElementById('alertContainer');
            
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            
            const icon = type === 'success' ? 'fa-check-circle' : 
                        type === 'error' ? 'fa-exclamation-circle' : 'fa-info-circle';
            
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

        // Load driver data (simulated)
        function loadDriverData() {
            // In real app, fetch from server
            const driverData = {
                name: 'Michael Okafor',
                email: 'michael.o@example.com',
                phone: '+234 805 678 9012',
                registered: 'January 10, 2024',
                rides: 128,
                earnings: '₦425,000',
                wallet: '₦45,800',
                carType: 'Toyota Camry 2020',
                plate: 'ABC-123-XY',
                license: 'DL-2024-001234'
            };
            
            // Populate all fields
            document.getElementById('displayName').textContent = driverData.name;
            document.getElementById('displayEmail').textContent = driverData.email;
            document.getElementById('viewName').textContent = driverData.name;
            document.getElementById('viewEmail').textContent = driverData.email;
            document.getElementById('viewPhone').textContent = driverData.phone;
            document.getElementById('viewRegistered').textContent = driverData.registered;
            document.getElementById('viewCarType').textContent = driverData.carType;
            document.getElementById('viewPlate').textContent = driverData.plate;
            document.getElementById('viewLicense').textContent = driverData.license;
            document.getElementById('vehiclePlate').textContent = driverData.plate;
            document.getElementById('vehicleLicense').textContent = driverData.license;
            document.getElementById('totalEarnings').textContent = driverData.earnings;
            document.getElementById('totalRides').textContent = driverData.rides;
            document.getElementById('walletBalance').textContent = driverData.wallet;
            document.getElementById('memberSince').textContent = driverData.registered;
            document.getElementById('editName').value = driverData.name;
            document.getElementById('editEmail').value = driverData.email;
            document.getElementById('editPhone').value = driverData.phone;
            document.getElementById('carModel').value = driverData.carType;
            document.getElementById('plateNumber').value = driverData.plate;
            document.getElementById('licenseNumber').value = driverData.license;
            
            // Set placeholder initials
            const initials = driverData.name.split(' ').map(n => n[0]).join('').toUpperCase();
            profilePicPlaceholder.textContent = initials;
        }

        // Initialize on load
        loadDriverData();
    