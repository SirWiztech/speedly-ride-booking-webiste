<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedly | My Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="./CSS/driver_profile.css">
</head>

<body>
    <div class="profile-container">
        <!-- Header -->
        <div class="profile-header">
            <h1>My Profile</h1>
            <p>Manage your personal information and preferences</p>
            <button class="back-btn" onclick="window.location.href='client_dashboard'">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </button>
        </div>

        <!-- Profile Content -->
        <div class="profile-content">
            <!-- Sidebar -->
            <div class="profile-sidebar">
                <!-- Profile Picture -->
                <div class="profile-pic-container">
                    <div class="profile-pic-placeholder" id="profilePicPlaceholder">
                        JD
                    </div>
                    <img src="" alt="Profile" class="profile-pic" id="profilePic" style="display: none;">
                    <label for="profileImage" class="upload-pic-btn">
                        <i class="fas fa-camera"></i>
                    </label>
                    <input type="file" id="profileImage" accept="image/*" style="display: none;">
                </div>

                <h3 style="margin-bottom: 5px;" id="displayName">John Doe</h3>
                <p style="color: #888; margin-bottom: 15px;" id="displayEmail">john.doe@example.com</p>

                <!-- Account Status -->
                <div class="status-badge active" id="accountStatus">
                    <i class="fas fa-check-circle"></i> Active Account
                </div>

                <!-- Stats -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <i class="fas fa-car"></i>
                        <div class="stat-value" id="totalRides">24</div>
                        <div class="stat-label">Total Rides</div>
                    </div>
                    <div class="stat-card">
                        <i class="fas fa-naira-sign"></i>
                        <div class="stat-value" id="totalSpent">₦45,800</div>
                        <div class="stat-label">Total Spent</div>
                    </div>
                </div>

                <!-- Member Since -->
                <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #eee;">
                    <p style="color: #888; font-size: 13px;">Member Since</p>
                    <p style="font-weight: 500;" id="memberSince">January 15, 2024</p>
                </div>
            </div>

            <!-- Main Content -->
            <div class="profile-main">
                <!-- Tabs -->
                <div class="profile-tabs">
                    <button class="tab-btn active" data-tab="view">View Profile</button>
                    <button class="tab-btn" data-tab="edit">Edit Profile</button>
                    <button class="tab-btn" data-tab="security">Security</button>
                    <button class="tab-btn" data-tab="history">Ride History</button>
                </div>

                <!-- Alert Container -->
                <div id="alertContainer"></div>

                <!-- View Profile Tab -->
                <div class="tab-content active" id="view-tab">
                    <h3 style="margin-bottom: 20px;">Personal Information</h3>
                    
                    <div class="info-row">
                        <div class="info-label">Full Name</div>
                        <div class="info-value" id="viewName">John Doe</div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Email Address</div>
                        <div class="info-value" id="viewEmail">john.doe@example.com</div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Phone Number</div>
                        <div class="info-value" id="viewPhone">+234 801 234 5678</div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Date Registered</div>
                        <div class="info-value" id="viewRegistered">January 15, 2024</div>
                    </div>
                    
                    <div class="info-row">
                        <div class="info-label">Account Status</div>
                        <div class="info-value">
                            <span class="status-badge active" style="margin: 0;">Active</span>
                        </div>
                    </div>

                    <div class="action-buttons">
                        <button class="btn btn-primary" onclick="switchTab('edit')">
                            <i class="fas fa-edit"></i> Edit Profile
                        </button>
                        <button class="btn btn-secondary" onclick="switchTab('security')">
                            <i class="fas fa-key"></i> Change Password
                        </button>
                    </div>
                </div>

                <!-- Edit Profile Tab -->
                <div class="tab-content" id="edit-tab">
                    <h3 style="margin-bottom: 20px;">Edit Profile</h3>
                    
                    <form id="editProfileForm">
                        <div class="form-group">
                            <label>Full Name</label>
                            <input type="text" class="form-control" id="editName" value="John Doe" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Email Address (Cannot be changed)</label>
                            <input type="email" class="form-control" id="editEmail" value="john.doe@example.com" disabled>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="tel" class="form-control" id="editPhone" value="+234 801 234 5678" required>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="switchTab('view')">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security Tab -->
                <div class="tab-content" id="security-tab">
                    <h3 style="margin-bottom: 20px;">Change Password</h3>
                    
                    <form id="changePasswordForm">
                        <div class="form-group">
                            <label>Current Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="currentPassword" required>
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('currentPassword')"></i>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label>New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="newPassword" required>
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('newPassword')"></i>
                            </div>
                            <small style="color: #888; font-size: 12px;">Minimum 8 characters with letters and numbers</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <div class="input-group">
                                <input type="password" class="form-control" id="confirmPassword" required>
                                <i class="fas fa-eye toggle-password" onclick="togglePassword('confirmPassword')"></i>
                            </div>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key"></i> Update Password
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="switchTab('view')">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                        </div>
                    </form>

                    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
                        <h4 style="margin-bottom: 15px;">Security Tips</h4>
                        <ul style="color: #666; font-size: 14px; line-height: 1.8; list-style-type: none;">
                            <li><i class="fas fa-check-circle" style="color: #28a745; margin-right: 10px;"></i> Use a strong, unique password</li>
                            <li><i class="fas fa-check-circle" style="color: #28a745; margin-right: 10px;"></i> Never share your password with anyone</li>
                            <li><i class="fas fa-check-circle" style="color: #28a745; margin-right: 10px;"></i> Change your password regularly</li>
                        </ul>
                    </div>
                </div>

                <!-- Ride History Tab -->
                <div class="tab-content" id="history-tab">
                    <h3 style="margin-bottom: 20px;">Ride History</h3>
                    
                    <table class="rides-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Pickup</th>
                                <th>Destination</th>
                                <th>Fare</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>May 15, 2024</td>
                                <td>Airport</td>
                                <td>City Center</td>
                                <td>₦4,500</td>
                                <td><span class="ride-status completed">Completed</span></td>
                            </tr>
                            <tr>
                                <td>May 14, 2024</td>
                                <td>Mall</td>
                                <td>University</td>
                                <td>₦3,200</td>
                                <td><span class="ride-status completed">Completed</span></td>
                            </tr>
                            <tr>
                                <td>May 12, 2024</td>
                                <td>Hotel</td>
                                <td>Airport</td>
                                <td>₦5,000</td>
                                <td><span class="ride-status completed">Completed</span></td>
                            </tr>
                            <tr>
                                <td>May 10, 2024</td>
                                <td>Office</td>
                                <td>Home</td>
                                <td>₦2,800</td>
                                <td><span class="ride-status cancelled">Cancelled</span></td>
                            </tr>
                            <tr>
                                <td>May 8, 2024</td>
                                <td>Restaurant</td>
                                <td>Cinema</td>
                                <td>₦1,500</td>
                                <td><span class="ride-status completed">Completed</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Change Password Success Modal -->
    <div class="modal" id="successModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-check-circle" style="color: #28a745;"></i> Success</h3>
                <button class="close-modal" onclick="closeModal('successModal')">&times;</button>
            </div>
            <div class="modal-body">
                <p>Your password has been changed successfully!</p>
            </div>
            <div style="text-align: right; margin-top: 20px;">
                <button class="btn btn-primary" onclick="closeModal('successModal')">OK</button>
            </div>
        </div>
    </div>

    <script src="./JS/client_profile.js"></script>
</body>

</html>   