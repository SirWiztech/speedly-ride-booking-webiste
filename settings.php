<?php
session_start();
require_once 'SERVER/API/db-connect.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: form.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'];
$user_email = $_SESSION['email'] ?? '';
$user_role = $_SESSION['role'] ?? 'client';

// Get user data from database
$userQuery = "SELECT * FROM users WHERE id = ?";
$userStmt = $conn->prepare($userQuery);
$userStmt->bind_param("s", $user_id);
$userStmt->execute();
$userResult = $userStmt->get_result();
$userData = $userResult->fetch_assoc();

// Initialize variables to prevent warnings
$clientProfile = [];
$savedLocations = [];
$paymentMethods = [];

// Get client profile data only if user is client
if ($user_role == 'client') {
    $profileQuery = "SELECT * FROM client_profiles WHERE user_id = ?";
    $profileStmt = $conn->prepare($profileQuery);
    $profileStmt->bind_param("s", $user_id);
    $profileStmt->execute();
    $profileResult = $profileStmt->get_result();
    $clientProfile = $profileResult->fetch_assoc() ?: [];
    
    // Get saved locations
    $locationsQuery = "SELECT * FROM saved_locations WHERE user_id = ? ORDER BY is_default DESC, location_name ASC";
    $locationsStmt = $conn->prepare($locationsQuery);
    $locationsStmt->bind_param("s", $user_id);
    $locationsStmt->execute();
    $locationsResult = $locationsStmt->get_result();
    $savedLocations = $locationsResult->fetch_all(MYSQLI_ASSOC);
}

// Get payment methods for all users
$paymentQuery = "SELECT * FROM payment_methods WHERE user_id = ? ORDER BY is_default DESC";
$paymentStmt = $conn->prepare($paymentQuery);
$paymentStmt->bind_param("s", $user_id);
$paymentStmt->execute();
$paymentResult = $paymentStmt->get_result();
$paymentMethods = $paymentResult->fetch_all(MYSQLI_ASSOC);

// Get notification count (for badge)
$notifQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notifStmt = $conn->prepare($notifQuery);
$notifStmt->bind_param("s", $user_id);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
$notifData = $notifResult->fetch_assoc();
$notificationCount = $notifData['count'] ?? 0;

// Set safe values for display
$membershipTier = isset($clientProfile['membership_tier']) ? ucfirst($clientProfile['membership_tier']) : 'Basic';
$emergencyContactName = $clientProfile['emergency_contact_name'] ?? '';
$emergencyContactPhone = $clientProfile['emergency_contact_phone'] ?? '';
$homeAddress = $clientProfile['home_address'] ?? '';
$ridePreferences = isset($clientProfile['ride_preferences']) ? json_decode($clientProfile['ride_preferences'], true) : [];
$shareRideChecked = isset($ridePreferences['share_ride']) && $ridePreferences['share_ride'] ? 'checked' : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedly | Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/settings.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Tailwind CSS - Warning is safe to ignore in development -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<style>
.dashboard-container {
        width: 100%;
        max-width: 1700px;
        background-color: #fff;
        border-radius: 30px;
        box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
        overflow: hidden;
        position: relative;
        min-height: 700px;
    }


</style>

<body>
    <!-- Dashboard Container -->
    <div class="dashboard-container">

        <!-- MOBILE VIEW -->
        <div class="mobile-view">

            <!-- Mobile Header -->
            <div class="mobile-header">
                <div class="user-info">
                    <h1>Settings</h1>
                    <p>Manage your account preferences</p>
                </div>
                <button class="notification-btn" onclick="checkNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge"><?php echo $notificationCount; ?></span>
                </button>
            </div>

            <!-- Mobile Settings Content -->
            <div class="mobile-settings-content">
                <!-- Profile Section -->
                <div class="mobile-profile-section">
                    <div class="mobile-profile-avatar">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        <button class="edit-btn" onclick="openModal('profile-modal')">
                            <i class="fas fa-pen"></i>
                        </button>
                    </div>
                    <div class="mobile-profile-name"><?php echo htmlspecialchars($user_name); ?></div>
                    <div class="mobile-profile-email"><?php echo htmlspecialchars($user_email); ?></div>
                    <div class="mobile-profile-tier"><?php echo ucfirst($user_role); ?> Member</div>
                </div>

                <!-- Account Settings -->
                <div class="mobile-settings-section">
                    <div class="mobile-section-header">
                        <i class="fas fa-user-cog"></i>
                        <h2>Account Settings</h2>
                    </div>
                    
                    <div class="mobile-settings-item" onclick="openModal('personal-info-modal')">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>Personal Information</h3>
                                <p>Name, email, phone, address</p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>

                    <div class="mobile-settings-item" onclick="openModal('credentials-modal')">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-key"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>Login & Security</h3>
                                <p>Password, 2FA, security</p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>

                    <div class="mobile-settings-item" onclick="openModal('payment-methods-modal')">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-credit-card"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>Payment Methods</h3>
                                <p><?php echo count($paymentMethods); ?> saved methods</p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </div>

                <!-- Ride Preferences - Only show for clients -->
                <?php if ($user_role == 'client'): ?>
                <div class="mobile-settings-section">
                    <div class="mobile-section-header">
                        <i class="fas fa-car"></i>
                        <h2>Ride Preferences</h2>
                    </div>
                    
                    <div class="mobile-settings-item" onclick="openModal('ride-settings-modal')">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-sliders-h"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>Ride Settings</h3>
                                <p>Default preferences, vehicle type</p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>

                    <div class="mobile-settings-item" onclick="openModal('saved-locations-modal')">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>Saved Locations</h3>
                                <p><?php echo count($savedLocations); ?> saved places</p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>

                    <div class="mobile-settings-item">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>Share Ride Details</h3>
                                <p>Auto-share trip with contacts</p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <label class="toggle-switch">
                                <input type="checkbox" id="share-ride-mobile" <?php echo $shareRideChecked; ?> onchange="toggleSetting('share_ride', this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Notifications -->
                <div class="mobile-settings-section">
                    <div class="mobile-section-header">
                        <i class="fas fa-bell"></i>
                        <h2>Notifications</h2>
                    </div>
                    
                    <div class="mobile-settings-item">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-envelope"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>Ride Updates</h3>
                                <p>Driver updates, ride status</p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <label class="toggle-switch">
                                <input type="checkbox" id="ride-updates-mobile" checked onchange="toggleNotification('ride_updates', this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="mobile-settings-item">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-bullhorn"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>Promotions & Offers</h3>
                                <p>Discounts, special offers</p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <label class="toggle-switch">
                                <input type="checkbox" id="promotions-mobile" onchange="toggleNotification('promotions', this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="mobile-settings-item">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>Safety Alerts</h3>
                                <p>Security notifications</p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <label class="toggle-switch">
                                <input type="checkbox" id="safety-mobile" checked onchange="toggleNotification('safety', this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Privacy & Security -->
                <div class="mobile-settings-section">
                    <div class="mobile-section-header">
                        <i class="fas fa-shield-alt"></i>
                        <h2>Privacy & Security</h2>
                    </div>
                    
                    <div class="mobile-settings-item" onclick="openModal('privacy-settings-modal')">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>Privacy Settings</h3>
                                <p>Data sharing, visibility</p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>

                    <div class="mobile-settings-item" onclick="openModal('emergency-contacts-modal')">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-phone-alt"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>Emergency Contacts</h3>
                                <p><?php echo $emergencyContactName ? '1 contact' : 'Add contact'; ?></p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>

                    <div class="mobile-settings-item" onclick="openModal('data-controls-modal')">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-database"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>Data Controls</h3>
                                <p>Download or delete your data</p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </div>

                <!-- Dark Mode Toggle -->
                <div class="mobile-dark-mode-toggle">
                    <div class="mobile-dark-mode-info">
                        <h3>Dark Mode</h3>
                        <p>Switch between light and dark theme</p>
                    </div>
                    <label class="toggle-switch">
                        <input type="checkbox" id="mobile-dark-mode-toggle" onchange="toggleDarkMode(this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </div>

                <!-- Support & About -->
                <div class="mobile-settings-section">
                    <div class="mobile-section-header">
                        <i class="fas fa-question-circle"></i>
                        <h2>Support & About</h2>
                    </div>
                    
                    <div class="mobile-settings-item" onclick="openModal('help-center-modal')">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-headset"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>Help Center</h3>
                                <p>FAQs, tutorials, support</p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>

                    <div class="mobile-settings-item" onclick="openModal('legal-modal')">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-balance-scale"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>Legal</h3>
                                <p>Terms, privacy policy</p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>

                    <div class="mobile-settings-item" onclick="openModal('about-modal')">
                        <div class="mobile-item-info">
                            <div class="mobile-item-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="mobile-item-details">
                                <h3>About Speedly</h3>
                                <p>Version 2.5.1</p>
                            </div>
                        </div>
                        <div class="mobile-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <button class="mobile-logout-btn" onclick="logout()">
                    <i class="fas fa-sign-out-alt"></i> Log Out
                </button>

                <button class="mobile-delete-account-btn" onclick="openModal('delete-account-modal')">
                    <i class="fas fa-trash-alt"></i> Delete Account
                </button>
            </div>

            <!-- Bottom Navigation -->
            <?php require_once 'components/mobile-nav.php'; ?>
        </div>

        <!-- DESKTOP VIEW -->
        <div class="desktop-view">
            <!-- Sidebar -->
            <div class="desktop-sidebar">
                <div class="desktop-logo">
                    <img src="main-assets/logo-no-background.png" alt="Speedly Logo" style="width: 100%; max-width: 150px;">
                </div>

                <!-- Desktop Navigation -->
                <?php require_once 'components/desktop-nav.php'; ?>

                <!-- User Profile -->
                <div class="desktop-user-profile" onclick="window.location.href='<?php echo $user_role; ?>_profile.php'">
                    <div class="desktop-profile-avatar-sidebar">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <div class="desktop-profile-info">
                        <h3><?php echo htmlspecialchars($user_name); ?></h3>
                        <p><?php echo ucfirst($user_role); ?> Member</p>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="desktop-main">
                <!-- Header -->
                <div class="desktop-header">
                    <div class="desktop-title">
                        <h1>Settings</h1>
                        <p>Manage your account preferences and security</p>
                    </div>
                    <button class="desktop-notification-btn" onclick="checkNotifications()">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge"><?php echo $notificationCount; ?></span>
                    </button>
                </div>

                <!-- Desktop Settings Grid -->
                <div class="desktop-settings-grid">
                    <!-- Profile Section -->
                    <div class="desktop-profile-section">
                        <div class="desktop-profile-avatar">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                            <button class="edit-btn" onclick="openModal('profile-modal')">
                                <i class="fas fa-pen"></i>
                            </button>
                        </div>
                        <div class="desktop-profile-info">
                            <div class="desktop-profile-name"><?php echo htmlspecialchars($user_name); ?></div>
                            <div class="desktop-profile-email"><?php echo htmlspecialchars($user_email); ?></div>
                            <div class="desktop-profile-tier"><?php echo ucfirst($user_role); ?> Member</div>
                        </div>
                        <div class="desktop-profile-actions">
                            <button class="desktop-profile-btn" onclick="openModal('personal-info-modal')">
                                <i class="fas fa-edit"></i> Edit Profile
                            </button>
                            <button class="desktop-profile-btn" onclick="openModal('credentials-modal')">
                                <i class="fas fa-key"></i> Security
                            </button>
                        </div>
                    </div>

                    <!-- Account Settings Card -->
                    <div class="desktop-settings-card">
                        <div class="desktop-card-header">
                            <i class="fas fa-user-cog"></i>
                            <h3>Account Settings</h3>
                        </div>
                        <div class="desktop-settings-list">
                            <div class="desktop-settings-item" onclick="openModal('personal-info-modal')">
                                <div class="desktop-item-label">
                                    <i class="fas fa-user"></i>
                                    <span>Personal Information</span>
                                </div>
                                <div class="desktop-item-action">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </div>
                            <div class="desktop-settings-item" onclick="openModal('credentials-modal')">
                                <div class="desktop-item-label">
                                    <i class="fas fa-key"></i>
                                    <span>Login & Security</span>
                                </div>
                                <div class="desktop-item-action">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </div>
                            <div class="desktop-settings-item" onclick="openModal('payment-methods-modal')">
                                <div class="desktop-item-label">
                                    <i class="fas fa-credit-card"></i>
                                    <span>Payment Methods</span>
                                </div>
                                <div class="desktop-item-action">
                                    <span style="color: #666; font-size: 14px;"><?php echo count($paymentMethods); ?> saved</span>
                                    <i class="fas fa-chevron-right" style="margin-left: 10px;"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ride Preferences Card - Only for clients -->
                    <?php if ($user_role == 'client'): ?>
                    <div class="desktop-settings-card">
                        <div class="desktop-card-header">
                            <i class="fas fa-car"></i>
                            <h3>Ride Preferences</h3>
                        </div>
                        <div class="desktop-settings-list">
                            <div class="desktop-settings-item" onclick="openModal('ride-settings-modal')">
                                <div class="desktop-item-label">
                                    <i class="fas fa-sliders-h"></i>
                                    <span>Default Settings</span>
                                </div>
                                <div class="desktop-item-action">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </div>
                            <div class="desktop-settings-item" onclick="openModal('saved-locations-modal')">
                                <div class="desktop-item-label">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span>Saved Locations</span>
                                </div>
                                <div class="desktop-item-action">
                                    <span style="color: #666; font-size: 14px;"><?php echo count($savedLocations); ?> places</span>
                                    <i class="fas fa-chevron-right" style="margin-left: 10px;"></i>
                                </div>
                            </div>
                            <div class="desktop-settings-item">
                                <div class="desktop-item-label">
                                    <i class="fas fa-users"></i>
                                    <span>Share Ride Details</span>
                                </div>
                                <div class="desktop-item-action">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="share-ride-desktop" <?php echo $shareRideChecked; ?> onchange="toggleSetting('share_ride', this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Notifications Card -->
                    <div class="desktop-settings-card">
                        <div class="desktop-card-header">
                            <i class="fas fa-bell"></i>
                            <h3>Notifications</h3>
                        </div>
                        <div class="desktop-settings-list">
                            <div class="desktop-settings-item">
                                <div class="desktop-item-label">
                                    <i class="fas fa-envelope"></i>
                                    <span>Ride Updates</span>
                                </div>
                                <div class="desktop-item-action">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="ride-updates-desktop" checked onchange="toggleNotification('ride_updates', this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="desktop-settings-item">
                                <div class="desktop-item-label">
                                    <i class="fas fa-bullhorn"></i>
                                    <span>Promotions</span>
                                </div>
                                <div class="desktop-item-action">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="promotions-desktop" onchange="toggleNotification('promotions', this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                            <div class="desktop-settings-item">
                                <div class="desktop-item-label">
                                    <i class="fas fa-shield-alt"></i>
                                    <span>Safety Alerts</span>
                                </div>
                                <div class="desktop-item-action">
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="safety-desktop" checked onchange="toggleNotification('safety', this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Privacy & Security Card -->
                    <div class="desktop-settings-card">
                        <div class="desktop-card-header">
                            <i class="fas fa-shield-alt"></i>
                            <h3>Privacy & Security</h3>
                        </div>
                        <div class="desktop-settings-list">
                            <div class="desktop-settings-item" onclick="openModal('privacy-settings-modal')">
                                <div class="desktop-item-label">
                                    <i class="fas fa-lock"></i>
                                    <span>Privacy Settings</span>
                                </div>
                                <div class="desktop-item-action">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </div>
                            <div class="desktop-settings-item" onclick="openModal('emergency-contacts-modal')">
                                <div class="desktop-item-label">
                                    <i class="fas fa-phone-alt"></i>
                                    <span>Emergency Contacts</span>
                                </div>
                                <div class="desktop-item-action">
                                    <span style="color: #666; font-size: 14px;"><?php echo $emergencyContactName ? '1' : '0'; ?></span>
                                    <i class="fas fa-chevron-right" style="margin-left: 10px;"></i>
                                </div>
                            </div>
                            <div class="desktop-settings-item" onclick="openModal('data-controls-modal')">
                                <div class="desktop-item-label">
                                    <i class="fas fa-database"></i>
                                    <span>Data Controls</span>
                                </div>
                                <div class="desktop-item-action">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- App Preferences Card -->
                    <div class="desktop-settings-card">
                        <div class="desktop-card-header">
                            <i class="fas fa-mobile-alt"></i>
                            <h3>App Preferences</h3>
                        </div>
                        <div class="desktop-settings-list">
                            <div class="desktop-settings-item" onclick="openModal('language-modal')">
                                <div class="desktop-item-label">
                                    <i class="fas fa-language"></i>
                                    <span>Language</span>
                                </div>
                                <div class="desktop-item-action">
                                    <span style="color: #666; font-size: 14px;">English</span>
                                </div>
                            </div>
                            <div class="desktop-settings-item" onclick="openModal('units-modal')">
                                <div class="desktop-item-label">
                                    <i class="fas fa-ruler"></i>
                                    <span>Units</span>
                                </div>
                                <div class="desktop-item-action">
                                    <span style="color: #666; font-size: 14px;">Kilometers</span>
                                </div>
                            </div>
                            <div class="desktop-settings-item" onclick="openModal('theme-modal')">
                                <div class="desktop-item-label">
                                    <i class="fas fa-palette"></i>
                                    <span>Theme</span>
                                </div>
                                <div class="desktop-item-action">
                                    <span style="color: #666; font-size: 14px;">Light</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Support & About Card -->
                    <div class="desktop-settings-card">
                        <div class="desktop-card-header">
                            <i class="fas fa-question-circle"></i>
                            <h3>Support & About</h3>
                        </div>
                        <div class="desktop-settings-list">
                            <div class="desktop-settings-item" onclick="openModal('help-center-modal')">
                                <div class="desktop-item-label">
                                    <i class="fas fa-headset"></i>
                                    <span>Help Center</span>
                                </div>
                                <div class="desktop-item-action">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </div>
                            <div class="desktop-settings-item" onclick="openModal('legal-modal')">
                                <div class="desktop-item-label">
                                    <i class="fas fa-balance-scale"></i>
                                    <span>Legal</span>
                                </div>
                                <div class="desktop-item-action">
                                    <i class="fas fa-chevron-right"></i>
                                </div>
                            </div>
                            <div class="desktop-settings-item" onclick="openModal('about-modal')">
                                <div class="desktop-item-label">
                                    <i class="fas fa-info-circle"></i>
                                    <span>About Speedly</span>
                                </div>
                                <div class="desktop-item-action">
                                    <span style="color: #666; font-size: 14px;">v2.5.1</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dark Mode Toggle -->
                    <div class="desktop-dark-mode-toggle">
                        <div class="desktop-dark-mode-info">
                            <h3>Dark Mode</h3>
                            <p>Switch between light and dark theme for better visibility</p>
                        </div>
                        <label class="toggle-switch">
                            <input type="checkbox" id="desktop-dark-mode-toggle" onchange="toggleDarkMode(this.checked)">
                            <span class="toggle-slider"></span>
                        </label>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="desktop-action-buttons">
                    <button class="desktop-logout-btn" onclick="logout()">
                        <i class="fas fa-sign-out-alt"></i> Log Out
                    </button>
                    <button class="desktop-delete-account-btn" onclick="openModal('delete-account-modal')">
                        <i class="fas fa-trash-alt"></i> Delete Account
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALS (Keep all your modal HTML here) -->
    <!-- ... -->
    
    <script src="JS/settings.js"></script>
</body>
</html>    