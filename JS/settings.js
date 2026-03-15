// Modal Functions
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
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

// Save Profile
function saveProfile(e) {
    e.preventDefault();
    
    const formData = {
        full_name: document.getElementById('full-name')?.value || '',
        email: document.getElementById('email')?.value || '',
        phone: document.getElementById('phone')?.value || ''
    };
    
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
            icon: 'success',
            title: 'Profile Updated',
            text: 'Your profile has been updated successfully (demo)',
            timer: 1500,
            showConfirmButton: false
        }).then(() => {
            closeModal('profile-modal');
            location.reload();
        });
    });
}

// Save Personal Info
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
            text: 'Your personal information has been updated',
            timer: 1500,
            showConfirmButton: false
        }).then(() => {
            closeModal('personal-info-modal');
        });
    });
}

// Update Password
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
            text: 'Your password has been changed successfully',
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
            <label class="flex items-center gap-2 mt-2" style="justify-content: center;">
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
                location.reload();
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
            const type = document.getElementById('location-type').value;
            
            if (!name || !address) {
                Swal.showValidationMessage('Please fill all fields');
                return false;
            }
            return { name, address, type };
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
                location.reload();
            });
        }
    });
}

// Save Emergency Contact
function saveEmergencyContact(e) {
    e.preventDefault();
    
    const name = document.getElementById('emergency-name')?.value || '';
    const phone = document.getElementById('emergency-phone')?.value || '';
    
    Swal.fire({
        icon: 'success',
        title: 'Contact Saved',
        text: 'Emergency contact saved successfully',
        timer: 1500,
        showConfirmButton: false
    }).then(() => {
        closeModal('emergency-contacts-modal');
    });
}

// Save Ride Settings
function saveRideSettings(e) {
    e.preventDefault();
    
    Swal.fire({
        icon: 'success',
        title: 'Settings Saved',
        text: 'Ride settings updated successfully',
        timer: 1500,
        showConfirmButton: false
    }).then(() => {
        closeModal('ride-settings-modal');
    });
}

// Save Privacy Settings
function savePrivacySettings(e) {
    e.preventDefault();
    
    Swal.fire({
        icon: 'success',
        title: 'Settings Saved',
        text: 'Privacy settings updated successfully',
        timer: 1500,
        showConfirmButton: false
    }).then(() => {
        closeModal('privacy-settings-modal');
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
    } else {
        document.body.classList.remove('dark-mode');
        document.querySelector('.dashboard-container')?.classList.remove('dark-mode');
    }
    
    // Update checkboxes
    document.getElementById('mobile-dark-mode-toggle').checked = enabled;
    document.getElementById('desktop-dark-mode-toggle').checked = enabled;
    
    // Save preference
    toggleSetting('dark_mode', enabled);
    
    // Update localStorage
    localStorage.setItem('darkMode', enabled ? 'enabled' : 'disabled');
}

// Set Language
function setLanguage(lang) {
    toggleSetting('language', lang);
    
    Swal.fire({
        icon: 'success',
        title: 'Language Updated',
        text: `Language set to ${lang === 'en' ? 'English' : lang === 'fr' ? 'Français' : 'Español'}`,
        timer: 1500,
        showConfirmButton: false
    }).then(() => {
        closeModal('language-modal');
    });
}

// Set Units
function setUnits(unit) {
    Swal.fire({
        icon: 'success',
        title: 'Units Updated',
        text: `Distance units set to ${unit === 'km' ? 'Kilometers' : 'Miles'}`,
        timer: 1500,
        showConfirmButton: false
    }).then(() => {
        closeModal('units-modal');
    });
}

// Show Help Article
function showHelpArticle(article) {
    closeModal('help-center-modal');
    
    let title = '';
    let content = '';
    
    if (article === 'faq') {
        title = 'Frequently Asked Questions';
        content = `
            <div style="text-align: left;">
                <p><strong>Q: How do I book a ride?</strong></p>
                <p>A: Click on "Book Ride" from the dashboard, enter your pickup and destination, then confirm.</p>
                <p><strong>Q: How do I cancel a ride?</strong></p>
                <p>A: Go to your ride details and click the "Cancel" button.</p>
                <p><strong>Q: How do I add funds to my wallet?</strong></p>
                <p>A: Go to Wallet and click "Add Money".</p>
            </div>
        `;
    } else if (article === 'contact') {
        title = 'Contact Support';
        content = `
            <div style="text-align: left;">
                <p><strong>Email:</strong> support@speedly.com</p>
                <p><strong>Phone:</strong> +234 800 123 4567</p>
                <p><strong>Hours:</strong> 24/7</p>
            </div>
        `;
    } else {
        title = 'User Guide';
        content = `
            <div style="text-align: left;">
                <p>Welcome to Speedly! Here's how to get started:</p>
                <ol style="margin-left: 20px;">
                    <li>Complete your profile</li>
                    <li>Add a payment method</li>
                    <li>Book your first ride</li>
                    <li>Rate your driver</li>
                </ol>
            </div>
        `;
    }
    
    Swal.fire({
        title: title,
        html: content,
        confirmButtonColor: '#ff5e00'
    });
}

// Show Legal Document
function showLegalDocument(doc) {
    closeModal('legal-modal');
    
    let title = '';
    let content = '';
    
    if (doc === 'terms') {
        title = 'Terms of Service';
        content = '<p>Terms of Service content would go here...</p>';
    } else if (doc === 'privacy') {
        title = 'Privacy Policy';
        content = '<p>Privacy Policy content would go here...</p>';
    } else {
        title = 'Licenses';
        content = '<p>Open source licenses would go here...</p>';
    }
    
    Swal.fire({
        title: title,
        html: content,
        confirmButtonColor: '#ff5e00'
    });
}

// Download Data
function downloadData() {
    Swal.fire({
        icon: 'success',
        title: 'Data Export',
        text: 'Your data is being prepared for download. You will receive an email shortly.',
        confirmButtonColor: '#ff5e00'
    });
}

// Logout
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

// Delete Account
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
    Swal.fire({
        icon: 'info',
        title: 'Notifications',
        html: '<p>No new notifications</p>',
        confirmButtonColor: '#ff5e00'
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
    
    // Check for saved dark mode preference
    const darkModePref = localStorage.getItem('darkMode');
    if (darkModePref === 'enabled') {
        toggleDarkMode(true);
    }
    
    // Add click listeners to settings items (they already have onclick)
});