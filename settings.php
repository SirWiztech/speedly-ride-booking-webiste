<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

// Initialize variables
$clientProfile = [];
$savedLocations = [];
$paymentMethods = [];
$userSettings = [
    'dark_mode' => 0,
    'notifications_enabled' => 1,
    'email_notifications' => 1,
    'sms_notifications' => 0,
    'language' => 'en',
    'currency' => 'NGN'
];

// Get user settings
$settingsQuery = "SELECT * FROM user_settings WHERE user_id = ?";
$settingsStmt = $conn->prepare($settingsQuery);
if ($settingsStmt) {
    $settingsStmt->bind_param("s", $user_id);
    $settingsStmt->execute();
    $settingsResult = $settingsStmt->get_result();
    $dbSettings = $settingsResult->fetch_assoc();
    if ($dbSettings) {
        $userSettings = array_merge($userSettings, $dbSettings);
    }
}

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

// Get notification count
$notifQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notifStmt = $conn->prepare($notifQuery);
$notifStmt->bind_param("s", $user_id);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
$notifData = $notifResult->fetch_assoc();
$notificationCount = $notifData['count'] ?? 0;

// Safe values for display
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
    <style>
        /* Critical fixes for desktop layout */
        .dashboard-container {
            width: 100%;
            max-width: 1700px;
            background-color: #fff;
            border-radius: 30px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            position: relative;
            min-height: 700px;
            overflow: visible !important;
        }
        
        @media (min-width: 1024px) {
            .mobile-view {
                display: none !important;
            }
            
            .desktop-view {
                display: flex !important;
                flex-direction: row !important;
                min-height: 800px;
                width: 100%;
            }
            
            .desktop-sidebar {
                width: 280px !important;
                background-color: #fff;
                padding: 30px 20px;
                border-right: 1px solid #eee;
                flex-shrink: 0;
            }
            
            .desktop-main {
                flex: 1 !important;
                padding: 30px;
                background-color: #fafafa;
                overflow-y: auto;
            }
        }
    </style>
</head>
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
                    <?php if ($notificationCount > 0): ?>
                    <span class="notification-badge"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
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
                                <input type="checkbox" id="ride-updates-mobile" <?php echo $userSettings['notifications_enabled'] ? 'checked' : ''; ?> onchange="toggleNotification('notifications_enabled', this.checked)">
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
                                <input type="checkbox" id="promotions-mobile" <?php echo $userSettings['email_notifications'] ? 'checked' : ''; ?> onchange="toggleNotification('email_notifications', this.checked)">
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
                                <input type="checkbox" id="safety-mobile" <?php echo $userSettings['sms_notifications'] ? 'checked' : ''; ?> onchange="toggleNotification('sms_notifications', this.checked)">
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
                        <input type="checkbox" id="mobile-dark-mode-toggle" <?php echo $userSettings['dark_mode'] ? 'checked' : ''; ?> onchange="toggleDarkMode(this.checked)">
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
                    <img src="main-assets/logo-no-background.png" alt="Speedly Logo">
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
                        <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge"><?php echo $notificationCount; ?></span>
                        <?php endif; ?>
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
                                        <input type="checkbox" id="ride-updates-desktop" <?php echo $userSettings['notifications_enabled'] ? 'checked' : ''; ?> onchange="toggleNotification('notifications_enabled', this.checked)">
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
                                        <input type="checkbox" id="promotions-desktop" <?php echo $userSettings['email_notifications'] ? 'checked' : ''; ?> onchange="toggleNotification('email_notifications', this.checked)">
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
                                        <input type="checkbox" id="safety-desktop" <?php echo $userSettings['sms_notifications'] ? 'checked' : ''; ?> onchange="toggleNotification('sms_notifications', this.checked)">
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
                                    <span style="color: #666; font-size: 14px;"><?php echo strtoupper($userSettings['language']); ?></span>
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
                                    <span style="color: #666; font-size: 14px;"><?php echo $userSettings['dark_mode'] ? 'Dark' : 'Light'; ?></span>
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
                            <input type="checkbox" id="desktop-dark-mode-toggle" <?php echo $userSettings['dark_mode'] ? 'checked' : ''; ?> onchange="toggleDarkMode(this.checked)">
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

    <!-- MODALS -->

    <!-- Profile Modal -->
    <div id="profile-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Edit Profile</h2>
                <button class="modal-close-btn" onclick="closeModal('profile-modal')">&times;</button>
            </div>
            <form id="profile-form" onsubmit="saveProfile(event)">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="full-name" value="<?php echo htmlspecialchars($user_name); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" id="email" value="<?php echo htmlspecialchars($user_email); ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="phone" value="<?php echo htmlspecialchars($userData['phone_number'] ?? ''); ?>" required>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('profile-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Personal Info Modal -->
    <div id="personal-info-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Personal Information</h2>
                <button class="modal-close-btn" onclick="closeModal('personal-info-modal')">&times;</button>
            </div>
            <form id="personal-info-form" onsubmit="savePersonalInfo(event)">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" id="personal-name" value="<?php echo htmlspecialchars($user_name); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" id="personal-email" value="<?php echo htmlspecialchars($user_email); ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="personal-phone" value="<?php echo htmlspecialchars($userData['phone_number'] ?? ''); ?>" required>
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <textarea id="personal-address" rows="3"><?php echo htmlspecialchars($homeAddress); ?></textarea>
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" id="personal-city" value="">
                </div>
                <div class="form-group">
                    <label>State</label>
                    <input type="text" id="personal-state" value="">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('personal-info-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Credentials Modal -->
    <div id="credentials-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Change Password</h2>
                <button class="modal-close-btn" onclick="closeModal('credentials-modal')">&times;</button>
            </div>
            <form id="credentials-form" onsubmit="updateCredentials(event)">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" id="current-password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" id="new-password" required minlength="8">
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" id="confirm-password" required>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('credentials-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Payment Methods Modal -->
    <div id="payment-methods-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Payment Methods</h2>
                <button class="modal-close-btn" onclick="closeModal('payment-methods-modal')">&times;</button>
            </div>
            <div class="payment-methods-list" style="margin-bottom: 20px; max-height: 300px; overflow-y: auto;">
                <?php if (count($paymentMethods) > 0): ?>
                    <?php foreach ($paymentMethods as $method): ?>
                    <div class="payment-method-item" data-id="<?php echo $method['id']; ?>">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-credit-card" style="color: #ff5e00;"></i>
                            <div>
                                <p class="font-medium"><?php echo htmlspecialchars($method['bank_name'] ?? 'Card'); ?></p>
                                <p class="text-sm text-gray-500">**** <?php echo substr($method['account_number'] ?? '', -4); ?></p>
                            </div>
                        </div>
                        <?php if ($method['is_default']): ?>
                        <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Default</span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-gray-500 py-4">No payment methods saved</p>
                <?php endif; ?>
            </div>
            <button class="btn btn-primary" style="width: 100%;" onclick="addPaymentMethod()">
                <i class="fas fa-plus"></i> Add New Payment Method
            </button>
        </div>
    </div>

    <!-- Saved Locations Modal -->
    <div id="saved-locations-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Saved Locations</h2>
                <button class="modal-close-btn" onclick="closeModal('saved-locations-modal')">&times;</button>
            </div>
            <div class="saved-locations-list" style="margin-bottom: 20px; max-height: 300px; overflow-y: auto;">
                <?php if (count($savedLocations) > 0): ?>
                    <?php foreach ($savedLocations as $location): ?>
                    <div class="location-item">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-map-marker-alt" style="color: #ff5e00;"></i>
                            <div>
                                <p class="font-medium"><?php echo htmlspecialchars($location['location_name']); ?></p>
                                <p class="text-sm text-gray-500"><?php echo htmlspecialchars($location['address']); ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-center text-gray-500 py-4">No saved locations</p>
                <?php endif; ?>
            </div>
            <button class="btn btn-primary" style="width: 100%;" onclick="addSavedLocation()">
                <i class="fas fa-plus"></i> Add New Location
            </button>
        </div>
    </div>

    <!-- Emergency Contacts Modal -->
    <div id="emergency-contacts-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Emergency Contacts</h2>
                <button class="modal-close-btn" onclick="closeModal('emergency-contacts-modal')">&times;</button>
            </div>
            <form onsubmit="saveEmergencyContact(event)">
                <div class="form-group">
                    <label>Contact Name</label>
                    <input type="text" id="emergency-name" value="<?php echo htmlspecialchars($emergencyContactName); ?>">
                </div>
                <div class="form-group">
                    <label>Contact Phone</label>
                    <input type="tel" id="emergency-phone" value="<?php echo htmlspecialchars($emergencyContactPhone); ?>">
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('emergency-contacts-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Contact</button>
                </div>
            </form>
        </div>
    </div>

    <!-- About Modal -->
    <div id="about-modal" class="modal">
        <div class="modal-content" style="text-align: center;">
            <div class="modal-header">
                <h2>About Speedly</h2>
                <button class="modal-close-btn" onclick="closeModal('about-modal')">&times;</button>
            </div>
            <div style="padding: 20px;">
                <img src="main-assets/logo-no-background.png" alt="Speedly Logo" style="max-width: 150px; margin: 0 auto 20px;">
                <h3 style="font-size: 20px; margin-bottom: 10px;">Speedly</h3>
                <p style="color: #666; margin-bottom: 15px;">Version 2.5.1</p>
                <p style="color: #888; font-size: 14px; margin-bottom: 20px;">© 2026 Speedly. All rights reserved.</p>
                <button class="btn btn-primary" onclick="closeModal('about-modal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div id="delete-account-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="color: #ff4757;">Delete Account</h2>
                <button class="modal-close-btn" onclick="closeModal('delete-account-modal')">&times;</button>
            </div>
            <div style="padding: 20px;">
                <p style="margin-bottom: 20px; color: #666;">This action is permanent and cannot be undone. All your data will be permanently deleted.</p>
                <div class="form-group">
                    <label>Type "DELETE" to confirm</label>
                    <input type="text" id="delete-confirm" placeholder="DELETE">
                </div>
                <div class="form-actions">
                    <button class="btn btn-secondary" onclick="closeModal('delete-account-modal')">Cancel</button>
                    <button class="btn" style="background: #ff4757; color: white;" onclick="deleteAccount()">Delete Account</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Ride Settings Modal -->
    <div id="ride-settings-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Ride Settings</h2>
                <button class="modal-close-btn" onclick="closeModal('ride-settings-modal')">&times;</button>
            </div>
            <form onsubmit="saveRideSettings(event)">
                <div class="form-group">
                    <label>Default Ride Type</label>
                    <select id="default-ride-type">
                        <option value="economy">Economy</option>
                        <option value="comfort">Comfort</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Default Payment Method</label>
                    <select id="default-payment">
                        <option value="">Select payment method</option>
                        <?php foreach ($paymentMethods as $method): ?>
                        <option value="<?php echo $method['id']; ?>" <?php echo $method['is_default'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($method['bank_name'] ?? 'Card'); ?> - **** <?php echo substr($method['account_number'] ?? '', -4); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('ride-settings-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Privacy Settings Modal -->
    <div id="privacy-settings-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Privacy Settings</h2>
                <button class="modal-close-btn" onclick="closeModal('privacy-settings-modal')">&times;</button>
            </div>
            <form onsubmit="savePrivacySettings(event)">
                <div class="form-group">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="share-location" checked>
                        <span>Share location for better ride experience</span>
                    </label>
                </div>
                <div class="form-group">
                    <label class="flex items-center gap-2">
                        <input type="checkbox" id="data-collection" checked>
                        <span>Allow anonymous data collection</span>
                    </label>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('privacy-settings-modal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Controls Modal -->
    <div id="data-controls-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Data Controls</h2>
                <button class="modal-close-btn" onclick="closeModal('data-controls-modal')">&times;</button>
            </div>
            <div style="padding: 10px;">
                <button class="btn btn-secondary" style="width: 100%; margin-bottom: 15px;" onclick="downloadData()">
                    <i class="fas fa-download"></i> Download My Data
                </button>
                <button class="btn" style="width: 100%; background: #ff4757; color: white;" onclick="openModal('delete-account-modal')">
                    <i class="fas fa-trash"></i> Delete My Data
                </button>
            </div>
        </div>
    </div>

    <!-- Help Center Modal -->
    <div id="help-center-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Help Center</h2>
                <button class="modal-close-btn" onclick="closeModal('help-center-modal')">&times;</button>
            </div>
            <div style="padding: 10px;">
                <div class="help-item" style="padding: 15px; border-bottom: 1px solid #eee; cursor: pointer;" onclick="showHelpArticle('faq')">
                    <h4 style="font-weight: 600;">📚 Frequently Asked Questions</h4>
                    <p style="color: #666; font-size: 13px;">Find answers to common questions</p>
                </div>
                <div class="help-item" style="padding: 15px; border-bottom: 1px solid #eee; cursor: pointer;" onclick="showHelpArticle('contact')">
                    <h4 style="font-weight: 600;">📞 Contact Support</h4>
                    <p style="color: #666; font-size: 13px;">support@speedly.com | +234 800 123 4567</p>
                </div>
                <div class="help-item" style="padding: 15px; cursor: pointer;" onclick="showHelpArticle('guide')">
                    <h4 style="font-weight: 600;">📖 User Guide</h4>
                    <p style="color: #666; font-size: 13px;">Learn how to use Speedly</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Legal Modal -->
    <div id="legal-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Legal</h2>
                <button class="modal-close-btn" onclick="closeModal('legal-modal')">&times;</button>
            </div>
            <div style="padding: 10px;">
                <div class="legal-item" style="padding: 15px; border-bottom: 1px solid #eee; cursor: pointer;" onclick="showLegalDocument('terms')">
                    <h4 style="font-weight: 600;">Terms of Service</h4>
                </div>
                <div class="legal-item" style="padding: 15px; border-bottom: 1px solid #eee; cursor: pointer;" onclick="showLegalDocument('privacy')">
                    <h4 style="font-weight: 600;">Privacy Policy</h4>
                </div>
                <div class="legal-item" style="padding: 15px; cursor: pointer;" onclick="showLegalDocument('licenses')">
                    <h4 style="font-weight: 600;">Licenses</h4>
                </div>
            </div>
        </div>
    </div>

    <!-- Language Modal -->
    <div id="language-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Select Language</h2>
                <button class="modal-close-btn" onclick="closeModal('language-modal')">&times;</button>
            </div>
            <div style="padding: 10px;">
                <div class="language-item" style="padding: 15px; border-bottom: 1px solid #eee; cursor: pointer;" onclick="setLanguage('en')">
                    <div class="flex items-center justify-between">
                        <span>🇬🇧 English</span>
                        <?php if ($userSettings['language'] == 'en'): ?>
                        <i class="fas fa-check" style="color: #ff5e00;"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="language-item" style="padding: 15px; border-bottom: 1px solid #eee; cursor: pointer;" onclick="setLanguage('fr')">
                    <div class="flex items-center justify-between">
                        <span>🇫🇷 Français</span>
                        <?php if ($userSettings['language'] == 'fr'): ?>
                        <i class="fas fa-check" style="color: #ff5e00;"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="language-item" style="padding: 15px; cursor: pointer;" onclick="setLanguage('es')">
                    <div class="flex items-center justify-between">
                        <span>🇪🇸 Español</span>
                        <?php if ($userSettings['language'] == 'es'): ?>
                        <i class="fas fa-check" style="color: #ff5e00;"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Units Modal -->
    <div id="units-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Distance Units</h2>
                <button class="modal-close-btn" onclick="closeModal('units-modal')">&times;</button>
            </div>
            <div style="padding: 10px;">
                <div class="unit-item" style="padding: 15px; border-bottom: 1px solid #eee; cursor: pointer;" onclick="setUnits('km')">
                    <div class="flex items-center justify-between">
                        <span>Kilometers (km)</span>
                        <i class="fas fa-check" style="color: #ff5e00;"></i>
                    </div>
                </div>
                <div class="unit-item" style="padding: 15px; cursor: pointer;" onclick="setUnits('mi')">
                    <div class="flex items-center justify-between">
                        <span>Miles (mi)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Theme Modal -->
    <div id="theme-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Theme</h2>
                <button class="modal-close-btn" onclick="closeModal('theme-modal')">&times;</button>
            </div>
            <div style="padding: 10px;">
                <div class="theme-item" style="padding: 15px; border-bottom: 1px solid #eee; cursor: pointer;" onclick="toggleDarkMode(false)">
                    <div class="flex items-center justify-between">
                        <span>☀️ Light Mode</span>
                        <?php if (!$userSettings['dark_mode']): ?>
                        <i class="fas fa-check" style="color: #ff5e00;"></i>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="theme-item" style="padding: 15px; cursor: pointer;" onclick="toggleDarkMode(true)">
                    <div class="flex items-center justify-between">
                        <span>🌙 Dark Mode</span>
                        <?php if ($userSettings['dark_mode']): ?>
                        <i class="fas fa-check" style="color: #ff5e00;"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="JS/settings.js"></script>
</body>
</html>