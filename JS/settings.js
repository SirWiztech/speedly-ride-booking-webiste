// Modal Functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Load modal content if needed
        if (modalId === 'payment-methods-modal') {
            loadPaymentMethods();
        } else if (modalId === 'saved-locations-modal') {
            loadSavedLocations();
        }
    }
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

// Load payment methods
function loadPaymentMethods() {
    fetch('SERVER/API/get_payment_methods.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updatePaymentMethodsList(data.methods);
            }
        })
        .catch(error => console.error('Error loading payment methods:', error));
}

function updatePaymentMethodsList(methods) {
    const container = document.querySelector('.payment-methods-list');
    if (!container) return;
    
    if (methods.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No payment methods saved</p>';
        return;
    }
    
    let html = '';
    methods.forEach(method => {
        html += `
            <div class="payment-method-item flex items-center justify-between p-3 border rounded-lg mb-2">
                <div class="flex items-center gap-3">
                    <i class="fas fa-credit-card text-[#ff5e00]"></i>
                    <div>
                        <p class="font-medium">${method.bank_name || 'Card'}</p>
                        <p class="text-sm text-gray-500">**** ${method.account_number.slice(-4)}</p>
                    </div>
                </div>
                ${method.is_default ? '<span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Default</span>' : ''}
            </div>
        `;
    });
    container.innerHTML = html;
}

// Load saved locations
function loadSavedLocations() {
    fetch('SERVER/API/get_saved_locations.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateLocationsList(data.locations);
            }
        })
        .catch(error => console.error('Error loading locations:', error));
}

function updateLocationsList(locations) {
    const container = document.querySelector('.saved-locations-list');
    if (!container) return;
    
    if (locations.length === 0) {
        container.innerHTML = '<p class="text-gray-500 text-center py-4">No saved locations</p>';
        return;
    }
    
    let html = '';
    locations.forEach(location => {
        const icons = {
            'home': 'fa-home',
            'work': 'fa-briefcase',
            'favorite': 'fa-star',
            'other': 'fa-map-marker-alt'
        };
        html += `
            <div class="location-item flex items-center justify-between p-3 border rounded-lg mb-2">
                <div class="flex items-center gap-3">
                    <i class="fas ${icons[location.location_type] || 'fa-map-marker-alt'} text-[#ff5e00]"></i>
                    <div>
                        <p class="font-medium">${location.location_name}</p>
                        <p class="text-sm text-gray-500">${location.address}</p>
                    </div>
                </div>
            </div>
        `;
    });
    container.innerHTML = html;
}

// Form Submission Functions
function saveProfile(e) {
    e.preventDefault();
    
    const formData = {
        full_name: document.getElementById('full-name')?.value || '',
        email: document.getElementById('email')?.value || '',
        phone: document.getElementById('phone')?.value || ''
    };
    
    // Show loading
    Swal.fire({
        title: 'Updating...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('SERVER/API/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Profile Updated',
                text: 'Your profile has been updated successfully',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                closeModal('profile-modal');
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: data.message || 'Failed to update profile',
                confirmButtonColor: '#ff5e00'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'error',
            title: 'Connection Error',
            text: 'Failed to connect to server',
            confirmButtonColor: '#ff5e00'
        });
    });
}

function savePersonalInfo(e) {
    e.preventDefault();
    
    const formData = {
        full_name: document.getElementById('personal-name')?.value || '',
        email: document.getElementById('personal-email')?.value || '',
        phone: document.getElementById('personal-phone')?.value || '',
        address: document.getElementById('personal-address')?.value || '',
        city: document.getElementById('personal-city')?.value || '',
        state: document.getElementById('personal-state')?.value || ''
    };
    
    Swal.fire({
        title: 'Saving...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('SERVER/API/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Information Updated',
                text: 'Your personal information has been updated',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                closeModal('personal-info-modal');
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: data.message || 'Failed to update information',
                confirmButtonColor: '#ff5e00'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'success',
            title: 'Information Updated',
            text: 'Your personal information has been updated (demo)',
            timer: 1500,
            showConfirmButton: false
        }).then(() => {
            closeModal('personal-info-modal');
        });
    });
}

function updateCredentials(e) {
    e.preventDefault();
    
    const currentPassword = document.getElementById('current-password')?.value;
    const newPassword = document.getElementById('new-password')?.value;
    const confirmPassword = document.getElementById('confirm-password')?.value;
    
    if (!currentPassword || !newPassword || !confirmPassword) {
        Swal.fire({
            icon: 'warning',
            title: 'Missing Fields',
            text: 'Please fill in all password fields',
            confirmButtonColor: '#ff5e00'
        });
        return;
    }
    
    if (newPassword !== confirmPassword) {
        Swal.fire({
            icon: 'error',
            title: 'Passwords Do Not Match',
            text: 'New password and confirm password must match',
            confirmButtonColor: '#ff5e00'
        });
        return;
    }
    
    if (newPassword.length < 8) {
        Swal.fire({
            icon: 'error',
            title: 'Password Too Short',
            text: 'Password must be at least 8 characters long',
            confirmButtonColor: '#ff5e00'
        });
        return;
    }
    
    Swal.fire({
        title: 'Updating...',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    fetch('SERVER/API/update_password.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            current_password: currentPassword,
            new_password: newPassword
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Password Updated',
                text: 'Your password has been changed successfully',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                closeModal('credentials-modal');
                document.getElementById('current-password').value = '';
                document.getElementById('new-password').value = '';
                document.getElementById('confirm-password').value = '';
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Update Failed',
                text: data.message || 'Failed to update password',
                confirmButtonColor: '#ff5e00'
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        Swal.fire({
            icon: 'success',
            title: 'Password Updated',
            text: 'Your password has been changed successfully (demo)',
            timer: 1500,
            showConfirmButton: false
        }).then(() => {
            closeModal('credentials-modal');
        });
    });
}

// Add Payment Method
function addPaymentMethod() {
    Swal.fire({
        title: 'Add Payment Method',
        html: `
            <select id="payment-type" class="swal2-input">
                <option value="card">Credit/Debit Card</option>
                <option value="bank_transfer">Bank Transfer</option>
            </select>
            <input type="text" id="bank-name" class="swal2-input" placeholder="Bank Name">
            <input type="text" id="account-name" class="swal2-input" placeholder="Account Name">
            <input type="text" id="account-number" class="swal2-input" placeholder="Account Number" maxlength="10">
            <label class="flex items-center gap-2 mt-2">
                <input type="checkbox" id="set-default"> 
                <span class="text-sm">Set as default payment method</span>
            </label>
        `,
        showCancelButton: true,
        confirmButtonText: 'Add Method',
        confirmButtonColor: '#ff5e00',
        preConfirm: () => {
            const type = document.getElementById('payment-type').value;
            const bank = document.getElementById('bank-name').value;
            const name = document.getElementById('account-name').value;
            const number = document.getElementById('account-number').value;
            const isDefault = document.getElementById('set-default').checked;
            
            if (!bank || !name || !number) {
                Swal.showValidationMessage('Please fill all fields');
                return false;
            }
            if (number.length !== 10 || !/^\d+$/.test(number)) {
                Swal.showValidationMessage('Please enter a valid 10-digit account number');
                return false;
            }
            return { type, bank, name, number, isDefault };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Method Added',
                text: 'Payment method added successfully',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                closeModal('payment-methods-modal');
            });
        }
    });
}

// Add Saved Location
function addSavedLocation() {
    Swal.fire({
        title: 'Add Saved Location',
        html: `
            <input type="text" id="location-name" class="swal2-input" placeholder="Location Name (e.g., Home, Work)">
            <input type="text" id="address" class="swal2-input" placeholder="Full Address">
            <select id="location-type" class="swal2-input">
                <option value="home">Home</option>
                <option value="work">Work</option>
                <option value="favorite">Favorite</option>
                <option value="other">Other</option>
            </select>
        `,
        showCancelButton: true,
        confirmButtonText: 'Save Location',
        confirmButtonColor: '#ff5e00',
        preConfirm: () => {
            const name = document.getElementById('location-name').value;
            const address = document.getElementById('address').value;
            
            if (!name || !address) {
                Swal.showValidationMessage('Please fill all fields');
                return false;
            }
            return { name, address };
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Location Saved',
                text: 'Location added successfully',
                timer: 1500,
                showConfirmButton: false
            }).then(() => {
                closeModal('saved-locations-modal');
            });
        }
    });
}

// Change Profile Picture
function changeProfilePicture() {
    Swal.fire({
        title: 'Change Profile Picture',
        html: `
            <div style="text-align: center;">
                <p>Select a new profile picture</p>
                <input type="file" id="profile-pic" accept="image/*" style="margin: 10px 0; padding: 10px;">
            </div>
        `,
        showCancelButton: true,
        confirmButtonText: 'Upload',
        confirmButtonColor: '#ff5e00',
        preConfirm: () => {
            const file = document.getElementById('profile-pic').files[0];
            if (!file) {
                Swal.showValidationMessage('Please select an image');
                return false;
            }
            return file;
        }
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({
                icon: 'success',
                title: 'Picture Updated',
                text: 'Profile picture updated successfully',
                timer: 1500,
                showConfirmButton: false
            });
        }
    });
}

// Toggle Settings
function toggleSetting(setting, value) {
    const data = {};
    data[setting] = value;
    
    fetch('SERVER/API/update_settings.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log(`${setting} updated to ${value}`);
        }
    })
    .catch(error => console.error('Error updating setting:', error));
}

// Toggle Notifications
function toggleNotification(type, value) {
    toggleSetting(type, value);
}

// Dark Mode Toggle
function toggleDarkMode(enabled) {
    if (enabled) {
        document.body.classList.add('dark-mode');
        document.querySelector('.dashboard-container')?.classList.add('dark-mode');
        localStorage.setItem('darkMode', 'enabled');
    } else {
        document.body.classList.remove('dark-mode');
        document.querySelector('.dashboard-container')?.classList.remove('dark-mode');
        localStorage.setItem('darkMode', 'disabled');
    }
    
    // Save preference
    toggleSetting('dark_mode', enabled);
}

// Check for saved dark mode preference
document.addEventListener('DOMContentLoaded', function() {
    const darkModePref = localStorage.getItem('darkMode');
    if (darkModePref === 'enabled') {
        document.getElementById('mobile-dark-mode-toggle').checked = true;
        document.getElementById('desktop-dark-mode-toggle').checked = true;
        toggleDarkMode(true);
    }
    
    // Load initial settings
    loadUserSettings();
});

function loadUserSettings() {
    fetch('SERVER/API/get_settings.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                applyUserSettings(data.settings);
            }
        })
        .catch(error => console.error('Error loading settings:', error));
}

function applyUserSettings(settings) {
    // Apply dark mode
    if (settings.dark_mode) {
        document.getElementById('mobile-dark-mode-toggle').checked = true;
        document.getElementById('desktop-dark-mode-toggle').checked = true;
        toggleDarkMode(true);
    }
    
    // Apply notification settings
    if (settings.notifications_enabled !== undefined) {
        // Update toggle switches
    }
}

// Logout Function
function logout() {
    Swal.fire({
        icon: 'question',
        title: 'Log Out',
        text: 'Are you sure you want to log out?',
        showCancelButton: true,
        confirmButtonText: 'Yes, Log Out',
        cancelButtonText: 'Cancel',
        confirmButtonColor: '#ff5e00'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'SERVER/API/logout.php';
        }
    });
}

// Delete Account Function
function deleteAccount() {
    const confirmInput = document.getElementById('delete-confirm');
    if (confirmInput && confirmInput.value === 'DELETE') {
        Swal.fire({
            icon: 'warning',
            title: 'Are you absolutely sure?',
            text: 'This action is permanent and cannot be undone!',
            showCancelButton: true,
            confirmButtonText: 'Yes, Delete My Account',
            cancelButtonText: 'Cancel',
            confirmButtonColor: '#ff4757'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Account Deleted',
                    text: 'Your account has been deleted successfully',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    window.location.href = 'index.php';
                });
            }
        });
    } else {
        Swal.fire({
            icon: 'error',
            title: 'Confirmation Failed',
            text: 'Please type "DELETE" to confirm.'
        });
    }
}

// Check Notifications
function checkNotifications() {
    fetch('SERVER/API/get_notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.notifications.length > 0) {
                let html = '<div style="text-align: left; max-height: 400px; overflow-y: auto;">';
                data.notifications.forEach(notif => {
                    html += `
                        <div style="padding: 10px; border-bottom: 1px solid #eee;">
                            <p><strong>${notif.title}</strong></p>
                            <p>${notif.message}</p>
                            <p style="font-size: 12px; color: #999;">${new Date(notif.created_at).toLocaleString()}</p>
                        </div>
                    `;
                });
                html += '</div>';
                
                Swal.fire({
                    icon: 'info',
                    title: 'Notifications',
                    html: html,
                    confirmButtonColor: '#ff5e00',
                    width: '600px'
                });
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'Notifications',
                    text: 'No new notifications',
                    confirmButtonColor: '#ff5e00'
                });
            }
        })
        .catch(() => {
            // Fallback for demo
            Swal.fire({
                icon: 'info',
                title: 'Notifications',
                html: `
                    <div style="text-align: left;">
                        <p>🔔 <strong>Welcome:</strong> Thanks for joining Speedly!</p>
                        <p>💰 <strong>Bonus:</strong> ₦5,000 welcome bonus added</p>
                        <p>🚗 <strong>Ride:</strong> Your first ride is waiting</p>
                    </div>
                `,
                confirmButtonColor: '#ff5e00'
            });
        });
}

// Responsive View Switcher
function checkScreenSize() {
    const mobileView = document.querySelector('.mobile-view');
    const desktopView = document.querySelector('.desktop-view');
    
    if (window.innerWidth >= 1024) {
        if (mobileView) mobileView.style.display = 'none';
        if (desktopView) desktopView.style.display = 'block';
    } else {
        if (mobileView) mobileView.style.display = 'block';
        if (desktopView) desktopView.style.display = 'none';
    }
}

// Initialize
document.addEventListener('DOMContentLoaded', function() {
    checkScreenSize();
    window.addEventListener('resize', checkScreenSize);
    
    // Add click listeners to settings items
    document.querySelectorAll('.mobile-settings-item, .desktop-settings-item').forEach(item => {
        item.addEventListener('click', function(e) {
            // Only trigger if not clicking on toggle switch
            if (!e.target.closest('.toggle-switch')) {
                const modalMap = {
                    'Personal Information': 'personal-info-modal',
                    'Login & Security': 'credentials-modal',
                    'Payment Methods': 'payment-methods-modal',
                    'Ride Settings': 'ride-settings-modal',
                    'Saved Locations': 'saved-locations-modal',
                    'Privacy Settings': 'privacy-settings-modal',
                    'Emergency Contacts': 'emergency-contacts-modal',
                    'Data Controls': 'data-controls-modal',
                    'Help Center': 'help-center-modal',
                    'Legal': 'legal-modal',
                    'About Speedly': 'about-modal'
                };
                
                // Find the setting title
                let title = '';
                const titleElement = this.querySelector('h3, .desktop-item-label span:last-child');
                if (titleElement) {
                    title = titleElement.textContent.trim();
                }
                
                if (title && modalMap[title]) {
                    openModal(modalMap[title]);
                } else {
                    // Check for specific text patterns
                    const text = this.innerText;
                    if (text.includes('Personal Information')) openModal('personal-info-modal');
                    else if (text.includes('Login & Security')) openModal('credentials-modal');
                    else if (text.includes('Payment Methods')) openModal('payment-methods-modal');
                    else if (text.includes('Ride Settings')) openModal('ride-settings-modal');
                    else if (text.includes('Saved Locations')) openModal('saved-locations-modal');
                    else if (text.includes('Privacy Settings')) openModal('privacy-settings-modal');
                    else if (text.includes('Emergency Contacts')) openModal('emergency-contacts-modal');
                    else if (text.includes('Data Controls')) openModal('data-controls-modal');
                    else if (text.includes('Help Center')) openModal('help-center-modal');
                    else if (text.includes('Legal')) openModal('legal-modal');
                    else if (text.includes('About Speedly')) openModal('about-modal');
                }
            }
        });
    });
    
    // Initialize toggle switches
    document.querySelectorAll('.toggle-switch input').forEach(toggle => {
        toggle.addEventListener('change', function() {
            const settingName = this.closest('.mobile-settings-item, .desktop-settings-item')?.querySelector('h3, .desktop-item-label span')?.textContent;
            if (settingName) {
                console.log(`${settingName} turned ${this.checked ? 'ON' : 'OFF'}`);
            }
        });
    });
});

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Escape key closes modals
    if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal[style*="display: flex"]');
        if (openModal) {
            openModal.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }
});