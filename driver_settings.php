<?php
session_start();
require_once 'SERVER/API/db-connect.php';

// Check if user is logged in and is a driver
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'driver') {
    header("Location: form.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'];
$user_email = $_SESSION['email'] ?? '';

// Get driver profile data - REMOVED u.address which doesn't exist
$driverQuery = "SELECT dp.*, 
                u.profile_picture_url,
                u.phone_number,
                u.email,
                u.full_name,
                u.date_of_birth,
                u.gender,
                dv.id as vehicle_id, 
                dv.vehicle_model, 
                dv.vehicle_year, 
                dv.vehicle_color, 
                dv.plate_number, 
                dv.vehicle_type, 
                dv.passenger_capacity,
                dv.insurance_expiry, 
                dv.road_worthiness_expiry,
                dv.is_active as vehicle_active
                FROM driver_profiles dp 
                JOIN users u ON dp.user_id = u.id 
                LEFT JOIN driver_vehicles dv ON dp.id = dv.driver_id AND dv.is_active = 1
                WHERE u.id = ?";
                
$driverStmt = $conn->prepare($driverQuery);
if (!$driverStmt) {
    die("Error preparing driver query: " . $conn->error);
}
$driverStmt->bind_param("s", $user_id);
$driverStmt->execute();
$driverResult = $driverStmt->get_result();
$driverData = $driverResult->fetch_assoc();

if (!$driverData) {
    // Redirect if no driver profile found
    header("Location: kyc.php");
    exit;
}
$driver_id = $driverData['id'];

// Get bank details for withdrawals
$bankDetails = null;
$bankQuery = "SELECT * FROM driver_bank_details WHERE driver_id = ? AND is_default = 1";
$bankStmt = $conn->prepare($bankQuery);
if ($bankStmt) {
    $bankStmt->bind_param("s", $driver_id);
    $bankStmt->execute();
    $bankResult = $bankStmt->get_result();
    $bankDetails = $bankResult->fetch_assoc();
}

// Get all bank accounts
$allBankAccounts = [];
$allBankQuery = "SELECT * FROM driver_bank_details WHERE driver_id = ? ORDER BY is_default DESC, created_at DESC";
$allBankStmt = $conn->prepare($allBankQuery);
if ($allBankStmt) {
    $allBankStmt->bind_param("s", $driver_id);
    $allBankStmt->execute();
    $allBankResult = $allBankStmt->get_result();
    while ($bank = $allBankResult->fetch_assoc()) {
        $allBankAccounts[] = $bank;
    }
}

// Get notification count
$notificationCount = 0;
$notifQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notifStmt = $conn->prepare($notifQuery);
if ($notifStmt) {
    $notifStmt->bind_param("s", $user_id);
    $notifStmt->execute();
    $notifResult = $notifStmt->get_result();
    $notifData = $notifResult->fetch_assoc();
    $notificationCount = $notifData['count'] ?? 0;
}

// Get driver schedule
$schedule = [];
$scheduleQuery = "SELECT * FROM driver_schedule WHERE driver_id = ? ORDER BY day_of_week";
$scheduleStmt = $conn->prepare($scheduleQuery);
if ($scheduleStmt) {
    $scheduleStmt->bind_param("s", $driver_id);
    $scheduleStmt->execute();
    $scheduleResult = $scheduleStmt->get_result();
    while ($row = $scheduleResult->fetch_assoc()) {
        $schedule[$row['day_of_week']] = $row;
    }
}

// Get user preferences/notifications settings
$notificationSettings = [
    'ride_requests' => true,
    'earnings_notif' => true,
    'sound_alerts' => true,
    'promotions' => false
];

// Days of week for schedule
$daysOfWeek = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Safe values with null coalescing
$license_number = $driverData['license_number'] ?? '';
$license_expiry = $driverData['license_expiry'] ?? '';
$vehicle_model = $driverData['vehicle_model'] ?? '';
$vehicle_year = $driverData['vehicle_year'] ?? '';
$vehicle_color = $driverData['vehicle_color'] ?? '';
$plate_number = $driverData['plate_number'] ?? '';
$vehicle_type = $driverData['vehicle_type'] ?? 'economy';
$passenger_capacity = $driverData['passenger_capacity'] ?? 4;
$insurance_expiry = $driverData['insurance_expiry'] ?? '';
$road_worthiness_expiry = $driverData['road_worthiness_expiry'] ?? '';
$verification_status = $driverData['verification_status'] ?? 'pending';
$driver_status = $driverData['driver_status'] ?? 'offline';
$created_at = $driverData['created_at'] ?? date('Y-m-d H:i:s');
$phone_number = $driverData['phone_number'] ?? '';
$date_of_birth = $driverData['date_of_birth'] ?? '';
$gender = $driverData['gender'] ?? 'prefer-not-to-say';
$profile_picture = $driverData['profile_picture_url'] ?? '';

// Get today's earnings for display
$todayEarnings = 0;
$todayQuery = "SELECT COALESCE(SUM(driver_payout), 0) as today_earnings 
               FROM rides WHERE driver_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'";
$todayStmt = $conn->prepare($todayQuery);
if ($todayStmt) {
    $todayStmt->bind_param("s", $driver_id);
    $todayStmt->execute();
    $todayResult = $todayStmt->get_result();
    $todayData = $todayResult->fetch_assoc();
    $todayEarnings = $todayData['today_earnings'] ?? 0;
}

// Get total earnings
$totalEarnings = 0;
$totalQuery = "SELECT COALESCE(SUM(driver_payout), 0) as total_earnings 
               FROM rides WHERE driver_id = ? AND status = 'completed'";
$totalStmt = $conn->prepare($totalQuery);
if ($totalStmt) {
    $totalStmt->bind_param("s", $driver_id);
    $totalStmt->execute();
    $totalResult = $totalStmt->get_result();
    $totalData = $totalResult->fetch_assoc();
    $totalEarnings = $totalData['total_earnings'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedly • Driver Settings</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link src='stylesheet' href="./CSS/client_dashboard.css" >
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel='stylesheet' href="./CSS/driver_dashboard.css" >
    
    <style>
        /* Keep all your existing CSS styles here - they're fine */
        * {
            font-family: 'Poppins', sans-serif;
        }
        body {
            background: #f5f7fa;
        }
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .3s;
            border-radius: 24px;
        }
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 2px;
            bottom: 2px;
            background-color: white;
            transition: .3s;
            border-radius: 50%;
        }
        input:checked + .toggle-slider {
            background-color: #ff5e00;
        }
        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }
        .settings-section {
            background: white;
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }
        .settings-section:hover {
            box-shadow: 0 8px 25px rgba(255,94,0,0.1);
        }
        .settings-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }
        .settings-header i {
            font-size: 24px;
            color: #ff5e00;
            background: #fff2e5;
            padding: 10px;
            border-radius: 12px;
        }
        .settings-header h2 {
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        .settings-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
            cursor: pointer;
            transition: all 0.2s;
        }
        .settings-item:hover {
            background: #f9f9f9;
            padding-left: 10px;
            padding-right: 10px;
            border-radius: 10px;
        }
        .settings-item:last-child {
            border-bottom: none;
        }
        .settings-item-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .settings-item-icon {
            width: 45px;
            height: 45px;
            background: #fff2e5;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ff5e00;
            font-size: 20px;
        }
        .settings-item-details h3 {
            font-weight: 600;
            margin-bottom: 4px;
            font-size: 15px;
            color: #333;
        }
        .settings-item-details p {
            font-size: 13px;
            color: #888;
        }
        .settings-item-action {
            color: #999;
        }
        .settings-item-value {
            font-size: 13px;
            color: #ff5e00;
            font-weight: 500;
            margin-right: 10px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(255,94,0,0.3);
        }
        .btn-secondary {
            background: #f5f5f5;
            color: #666;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }
        .btn-secondary:hover {
            background: #e8e8e8;
        }
        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 14px;
        }
        .btn-danger:hover {
            background: #fecaca;
        }
        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-verified {
            background: #d4edda;
            color: #155724;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        .status-online {
            background: #d4edda;
            color: #155724;
        }
        .status-offline {
            background: #f8f9fa;
            color: #6c757d;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
            backdrop-filter: blur(5px);
        }
        .modal.show {
            display: flex;
        }
        .modal-content {
            background: white;
            border-radius: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }
        @keyframes modalSlideIn {
            from {
                transform: translateY(-50px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        .modal-header {
            padding: 20px 24px;
            border-bottom: 1px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            background: white;
            border-radius: 24px 24px 0 0;
        }
        .modal-header h3 {
            font-size: 20px;
            font-weight: 700;
            color: #333;
        }
        .modal-header button {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #999;
            transition: color 0.3s;
        }
        .modal-header button:hover {
            color: #ff5e00;
        }
        .modal-body {
            padding: 24px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 500;
            color: #555;
            margin-bottom: 8px;
        }
        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e0e0e0;
            border-radius: 12px;
            font-size: 14px;
            transition: all 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #ff5e00;
            box-shadow: 0 0 0 3px rgba(255,94,0,0.1);
        }
        .form-control[readonly] {
            background: #f9f9f9;
            cursor: not-allowed;
        }
        .modal-actions {
            display: flex;
            gap: 12px;
            margin-top: 24px;
        }
        .bank-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 16px;
            margin-bottom: 15px;
            position: relative;
            cursor: pointer;
            transition: transform 0.3s;
        }
        .bank-card:hover {
            transform: translateY(-3px);
        }
        .bank-card.default {
            border: 3px solid #ffd700;
        }
        .bank-card .default-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ffd700;
            color: #333;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }
        .bank-card .bank-name {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        .bank-card .account-number {
            font-size: 16px;
            letter-spacing: 2px;
            margin-bottom: 5px;
        }
        .bank-card .account-name {
            font-size: 14px;
            opacity: 0.9;
        }
        .notification-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            border-radius: 12px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            animation: slideInRight 0.3s ease;
        }
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        .quick-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 15px;
            border-radius: 16px;
            text-align: center;
        }
        .stat-card .stat-value {
            font-size: 24px;
            font-weight: 700;
            color: #ff5e00;
        }
        .stat-card .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        .profile-avatar-large {
            width: 100px;
            height: 100px;
            background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 40px;
            font-weight: 600;
            margin: 0 auto 20px;
            border: 4px solid white;
            box-shadow: 0 5px 20px rgba(255,94,0,0.3);
        }
        .upload-area {
            border: 2px dashed #ff5e00;
            padding: 20px;
            text-align: center;
            border-radius: 12px;
            background: #fff2e5;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            background: #ffe5d5;
        }
        .upload-area i {
            font-size: 40px;
            color: #ff5e00;
            margin-bottom: 10px;
        }
        .hidden-input {
            display: none;
        }
    </style>
</head>
<body>
    <!-- REST OF YOUR HTML CONTENT (keep everything exactly as is) -->
    <!-- Your existing HTML structure continues here... -->
    
    <!-- Dashboard Container -->
    <div class="dashboard-container">
        <!-- Mobile View -->
        <div class="mobile-view block lg:hidden">
            <!-- Mobile Header -->
            <div class="bg-white p-4 flex justify-between items-center shadow-sm sticky top-0 z-10">
                <div>
                    <h1 class="text-xl font-bold">Settings</h1>
                    <p class="text-sm text-gray-500">Manage your driver account</p>
                </div>
                <button class="w-10 h-10 bg-[#ff5e00] rounded-full flex items-center justify-center relative" onclick="checkNotifications()">
                    <i class="fas fa-bell text-white"></i>
                    <?php if ($notificationCount > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- Quick Stats -->
            <div class="px-4 mt-4">
                <div class="quick-stats">
                    <div class="stat-card">
                        <div class="stat-value">₦<?php echo number_format($todayEarnings); ?></div>
                        <div class="stat-label">Today</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value">₦<?php echo number_format($totalEarnings); ?></div>
                        <div class="stat-label">Total</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-value" id="mobileStatusText"><?php echo $driver_status == 'online' ? '🟢' : '⚫'; ?></div>
                        <div class="stat-label"><?php echo ucfirst($driver_status); ?></div>
                    </div>
                </div>
            </div>

            <!-- Profile Summary -->
            <div class="bg-white m-4 p-6 rounded-xl shadow-sm">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-gradient-to-r from-[#ff5e00] to-[#ff8c3a] rounded-full flex items-center justify-center text-white text-2xl font-bold">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <div class="flex-1">
                        <h2 class="font-bold text-lg"><?php echo htmlspecialchars($user_name); ?></h2>
                        <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user_email); ?></p>
                        <div class="flex items-center gap-2 mt-2">
                            <span class="status-badge <?php echo $verification_status == 'approved' ? 'status-verified' : 'status-pending'; ?>">
                                <?php echo $verification_status == 'approved' ? '✓ Verified' : '⏳ Pending'; ?>
                            </span>
                            <span class="status-badge <?php echo $driver_status == 'online' ? 'status-online' : 'status-offline'; ?>" id="mobileStatusBadge">
                                <?php echo $driver_status == 'online' ? '🟢 Online' : '⚫ Offline'; ?>
                            </span>
                        </div>
                    </div>
                    <button class="text-[#ff5e00]" onclick="openModal('driver-profile-modal')">
                        <i class="fas fa-edit text-xl"></i>
                    </button>
                </div>
            </div>

            <!-- Settings Sections -->
            <div class="px-4 pb-24">
                <!-- Driver Account Settings -->
                <div class="settings-section">
                    <div class="settings-header">
                        <i class="fas fa-id-card"></i>
                        <h2>Driver Account</h2>
                    </div>
                    <div class="settings-item" onclick="openModal('driver-profile-modal')">
                        <div class="settings-item-info">
                            <div class="settings-item-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div class="settings-item-details">
                                <h3>Driver Profile</h3>
                                <p>Name, email, phone, photo</p>
                            </div>
                        </div>
                        <div class="settings-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                    <div class="settings-item" onclick="openModal('license-modal')">
                        <div class="settings-item-info">
                            <div class="settings-item-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="settings-item-details">
                                <h3>License Information</h3>
                                <p><?php echo $license_number ? 'License #' . substr($license_number, -4) : 'Not added'; ?></p>
                            </div>
                        </div>
                        <div class="settings-item-value"><?php echo $license_expiry ? date('M Y', strtotime($license_expiry)) : ''; ?></div>
                        <div class="settings-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                    <div class="settings-item" onclick="openModal('bank-details-modal')">
                        <div class="settings-item-info">
                            <div class="settings-item-icon">
                                <i class="fas fa-university"></i>
                            </div>
                            <div class="settings-item-details">
                                <h3>Bank Details</h3>
                                <p><?php echo $bankDetails ? $bankDetails['bank_name'] : 'Add bank for withdrawals'; ?></p>
                            </div>
                        </div>
                        <?php if ($bankDetails): ?>
                        <div class="settings-item-value">••••<?php echo substr($bankDetails['account_number'], -4); ?></div>
                        <?php endif; ?>
                        <div class="settings-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </div>

                <!-- Vehicle Settings -->
                <div class="settings-section">
                    <div class="settings-header">
                        <i class="fas fa-car"></i>
                        <h2>Vehicle Information</h2>
                    </div>
                    <div class="settings-item" onclick="openModal('vehicle-modal')">
                        <div class="settings-item-info">
                            <div class="settings-item-icon">
                                <i class="fas fa-truck"></i>
                            </div>
                            <div class="settings-item-details">
                                <h3>Vehicle Details</h3>
                                <p>
                                    <?php if ($vehicle_model): ?>
                                        <?php echo $vehicle_model . ' • ' . $plate_number; ?>
                                    <?php else: ?>
                                        Add your vehicle information
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="settings-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                    <div class="settings-item" onclick="openModal('insurance-modal')">
                        <div class="settings-item-info">
                            <div class="settings-item-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <div class="settings-item-details">
                                <h3>Insurance</h3>
                                <p><?php echo $insurance_expiry ? 'Expires: ' . date('M Y', strtotime($insurance_expiry)) : 'Not added'; ?></p>
                            </div>
                        </div>
                        <div class="settings-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </div>

                <!-- Driving Preferences -->
                <div class="settings-section">
                    <div class="settings-header">
                        <i class="fas fa-sliders-h"></i>
                        <h2>Driving Preferences</h2>
                    </div>
                    <div class="settings-item" onclick="openModal('schedule-modal')">
                        <div class="settings-item-info">
                            <div class="settings-item-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="settings-item-details">
                                <h3>Work Schedule</h3>
                                <p>Set your available hours</p>
                            </div>
                        </div>
                        <div class="settings-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                    <div class="settings-item" onclick="openModal('earnings-modal')">
                        <div class="settings-item-info">
                            <div class="settings-item-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="settings-item-details">
                                <h3>Earnings Settings</h3>
                                <p>Auto-withdrawal, notifications</p>
                            </div>
                        </div>
                        <div class="settings-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </div>

                <!-- Driver Notifications -->
                <div class="settings-section">
                    <div class="settings-header">
                        <i class="fas fa-bell"></i>
                        <h2>Driver Notifications</h2>
                    </div>
                    <div class="settings-item">
                        <div class="settings-item-info">
                            <div class="settings-item-icon">
                                <i class="fas fa-car"></i>
                            </div>
                            <div class="settings-item-details">
                                <h3>New Ride Requests</h3>
                                <p>Get notified of new rides</p>
                            </div>
                        </div>
                        <div class="settings-item-action">
                            <label class="toggle-switch">
                                <input type="checkbox" id="ride-requests" <?php echo $notificationSettings['ride_requests'] ? 'checked' : ''; ?> onchange="toggleSetting('ride_requests', this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="settings-item">
                        <div class="settings-item-info">
                            <div class="settings-item-icon">
                                <i class="fas fa-money-bill"></i>
                            </div>
                            <div class="settings-item-details">
                                <h3>Earnings Updates</h3>
                                <p>Payment notifications</p>
                            </div>
                        </div>
                        <div class="settings-item-action">
                            <label class="toggle-switch">
                                <input type="checkbox" id="earnings-notif" <?php echo $notificationSettings['earnings_notif'] ? 'checked' : ''; ?> onchange="toggleSetting('earnings_notif', this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                    <div class="settings-item">
                        <div class="settings-item-info">
                            <div class="settings-item-icon">
                                <i class="fas fa-volume-up"></i>
                            </div>
                            <div class="settings-item-details">
                                <h3>Sound Alerts</h3>
                                <p>Play sound for new rides</p>
                            </div>
                        </div>
                        <div class="settings-item-action">
                            <label class="toggle-switch">
                                <input type="checkbox" id="sound-alerts" <?php echo $notificationSettings['sound_alerts'] ? 'checked' : ''; ?> onchange="toggleSetting('sound_alerts', this.checked)">
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Driver Support -->
                <div class="settings-section">
                    <div class="settings-header">
                        <i class="fas fa-question-circle"></i>
                        <h2>Driver Support</h2>
                    </div>
                    <div class="settings-item" onclick="openModal('help-modal')">
                        <div class="settings-item-info">
                            <div class="settings-item-icon">
                                <i class="fas fa-headset"></i>
                            </div>
                            <div class="settings-item-details">
                                <h3>Driver Help Center</h3>
                                <p>FAQs, tutorials, driver support</p>
                            </div>
                        </div>
                        <div class="settings-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                    <div class="settings-item" onclick="openModal('about-modal')">
                        <div class="settings-item-info">
                            <div class="settings-item-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="settings-item-details">
                                <h3>About Speedly Driver</h3>
                                <p>Version 2.5.1 • Driver app</p>
                            </div>
                        </div>
                        <div class="settings-item-action">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <button class="w-full bg-red-500 text-white py-4 rounded-xl font-semibold mb-3 hover:bg-red-600 transition" onclick="logout()">
                    <i class="fas fa-sign-out-alt mr-2"></i> Log Out
                </button>
                <button class="w-full bg-gray-200 text-gray-700 py-4 rounded-xl font-semibold hover:bg-gray-300 transition" onclick="openModal('delete-modal')">
                    <i class="fas fa-trash-alt mr-2"></i> Delete Account
                </button>
            </div>

            <!-- Bottom Navigation -->
            <?php require_once './components/mobile-driver-nav.php'; ?>
        </div>

        <!-- Desktop View -->
        <div class="desktop-view hidden lg:block">
            <div class="flex min-h-screen">
                <!-- Sidebar -->
                <div class="w-72 bg-white shadow-xl fixed h-full">
                    <div class="p-6">
                        <img src="./main-assets/logo-no-background.png" alt="Speedly Logo" class="h-42 mb-8">
                        
                        <!-- Desktop Navigation -->
                        <?php require_once './components/desktop-driver-nav.php'; ?>

                        <!-- User Profile -->
                        <div class="absolute bottom-6 left-6 right-6">
                            <div class="flex items-center gap-3 p-4 bg-gray-50 rounded-xl cursor-pointer hover:bg-gray-100 transition" onclick="window.location.href='driver_profile.php'">
                                <div class="w-12 h-12 bg-gradient-to-r from-[#ff5e00] to-[#ff8c3a] rounded-full flex items-center justify-center text-white text-xl font-bold">
                                    <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-semibold"><?php echo htmlspecialchars($user_name); ?></h3>
                                    <p class="text-sm text-gray-500" id="desktopStatusText"><?php echo $verification_status == 'approved' ? 'Verified Driver' : 'Pending Verification'; ?></p>
                                </div>
                                <span class="status-badge <?php echo $driver_status == 'online' ? 'status-online' : 'status-offline'; ?>" id="desktopStatusBadge">
                                    <?php echo $driver_status == 'online' ? '● ONLINE' : '○ OFFLINE'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="flex-1 ml-72 p-8">
                    <!-- Header -->
                    <div class="flex justify-between items-center mb-8">
                        <div>
                            <h1 class="text-3xl font-bold">Driver Settings</h1>
                            <p class="text-gray-500 mt-1">Manage your driver account, vehicle, and preferences</p>
                        </div>
                        <button class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center relative hover:bg-gray-200 transition" onclick="checkNotifications()">
                            <i class="fas fa-bell text-gray-700 text-xl"></i>
                            <?php if ($notificationCount > 0): ?>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center"><?php echo $notificationCount; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>

                    <!-- Driver Status Card -->
                    <div class="bg-gradient-to-r from-[#ff5e00] to-[#ff8c3a] rounded-2xl p-8 mb-8 text-white">
                        <div class="flex items-center gap-8">
                            <div class="w-24 h-24 bg-white/20 rounded-full flex items-center justify-center text-4xl font-bold backdrop-blur">
                                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                            </div>
                            <div class="flex-1">
                                <h2 class="text-3xl font-bold"><?php echo htmlspecialchars($user_name); ?></h2>
                                <p class="text-white/90 mt-1"><?php echo htmlspecialchars($user_email); ?> • <?php echo htmlspecialchars($phone_number); ?></p>
                                <div class="flex items-center gap-3 mt-4">
                                    <span class="bg-white/20 px-4 py-2 rounded-full text-sm font-semibold backdrop-blur">
                                        <?php echo $verification_status == 'approved' ? '✓ Verified Driver' : '⏳ Pending Verification'; ?>
                                    </span>
                                    <span class="bg-white/20 px-4 py-2 rounded-full text-sm font-semibold backdrop-blur flex items-center gap-2" id="desktopStatusDisplay">
                                        <?php if ($driver_status == 'online'): ?>
                                            <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                                            Online
                                        <?php else: ?>
                                            <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                                            Offline
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <button class="bg-white text-[#ff5e00] px-6 py-3 rounded-xl font-semibold hover:shadow-lg transition" onclick="openModal('driver-profile-modal')">
                                <i class="fas fa-edit mr-2"></i> Edit Profile
                            </button>
                        </div>
                    </div>

                    <!-- Driver Settings Grid -->
                    <div class="grid grid-cols-3 gap-6">
                        <!-- Driver Account -->
                        <div class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center text-[#ff5e00]">
                                    <i class="fas fa-id-card text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold">Driver Account</h3>
                            </div>
                            <div class="space-y-2">
                                <button class="w-full flex items-center justify-between p-3 hover:bg-gray-50 rounded-xl transition group" onclick="openModal('driver-profile-modal')">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-user text-gray-400 w-5 group-hover:text-[#ff5e00]"></i>
                                        <span>Driver Profile</span>
                                    </div>
                                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-[#ff5e00]"></i>
                                </button>
                                <button class="w-full flex items-center justify-between p-3 hover:bg-gray-50 rounded-xl transition group" onclick="openModal('license-modal')">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-id-card text-gray-400 w-5 group-hover:text-[#ff5e00]"></i>
                                        <span>License Info</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-sm text-gray-500"><?php echo $license_number ? 'Added' : 'Not added'; ?></span>
                                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-[#ff5e00]"></i>
                                    </div>
                                </button>
                                <button class="w-full flex items-center justify-between p-3 hover:bg-gray-50 rounded-xl transition group" onclick="openModal('bank-details-modal')">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-university text-gray-400 w-5 group-hover:text-[#ff5e00]"></i>
                                        <span>Bank Details</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <?php if ($bankDetails): ?>
                                            <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">Saved</span>
                                        <?php endif; ?>
                                        <i class="fas fa-chevron-right text-gray-400 group-hover:text-[#ff5e00]"></i>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Vehicle -->
                        <div class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center text-[#ff5e00]">
                                    <i class="fas fa-car text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold">Vehicle</h3>
                            </div>
                            <div class="space-y-2">
                                <button class="w-full flex items-center justify-between p-3 hover:bg-gray-50 rounded-xl transition group" onclick="openModal('vehicle-modal')">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-truck text-gray-400 w-5 group-hover:text-[#ff5e00]"></i>
                                        <span>Vehicle Details</span>
                                    </div>
                                    <?php if ($vehicle_model): ?>
                                        <span class="text-sm text-gray-500"><?php echo $plate_number; ?></span>
                                    <?php endif; ?>
                                </button>
                                <button class="w-full flex items-center justify-between p-3 hover:bg-gray-50 rounded-xl transition group" onclick="openModal('insurance-modal')">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-shield-alt text-gray-400 w-5 group-hover:text-[#ff5e00]"></i>
                                        <span>Insurance</span>
                                    </div>
                                    <?php if ($insurance_expiry): ?>
                                        <span class="text-sm text-gray-500"><?php echo date('M Y', strtotime($insurance_expiry)); ?></span>
                                    <?php endif; ?>
                                </button>
                            </div>
                        </div>

                        <!-- Driving Preferences -->
                        <div class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center text-[#ff5e00]">
                                    <i class="fas fa-sliders-h text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold">Driving Preferences</h3>
                            </div>
                            <div class="space-y-2">
                                <button class="w-full flex items-center justify-between p-3 hover:bg-gray-50 rounded-xl transition group" onclick="openModal('schedule-modal')">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-clock text-gray-400 w-5 group-hover:text-[#ff5e00]"></i>
                                        <span>Work Schedule</span>
                                    </div>
                                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-[#ff5e00]"></i>
                                </button>
                                <button class="w-full flex items-center justify-between p-3 hover:bg-gray-50 rounded-xl transition group" onclick="openModal('earnings-modal')">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-chart-line text-gray-400 w-5 group-hover:text-[#ff5e00]"></i>
                                        <span>Earnings Settings</span>
                                    </div>
                                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-[#ff5e00]"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Driver Notifications -->
                        <div class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center text-[#ff5e00]">
                                    <i class="fas fa-bell text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold">Notifications</h3>
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-2">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-car text-gray-400 w-5"></i>
                                        <span class="text-sm">New Ride Requests</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="desktop-ride-requests" <?php echo $notificationSettings['ride_requests'] ? 'checked' : ''; ?> onchange="toggleSetting('ride_requests', this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between p-2">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-money-bill text-gray-400 w-5"></i>
                                        <span class="text-sm">Earnings Updates</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="desktop-earnings" <?php echo $notificationSettings['earnings_notif'] ? 'checked' : ''; ?> onchange="toggleSetting('earnings_notif', this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                                <div class="flex items-center justify-between p-2">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-volume-up text-gray-400 w-5"></i>
                                        <span class="text-sm">Sound Alerts</span>
                                    </div>
                                    <label class="toggle-switch">
                                        <input type="checkbox" id="desktop-sound" <?php echo $notificationSettings['sound_alerts'] ? 'checked' : ''; ?> onchange="toggleSetting('sound_alerts', this.checked)">
                                        <span class="toggle-slider"></span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <!-- Driver Support -->
                        <div class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center text-[#ff5e00]">
                                    <i class="fas fa-question-circle text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold">Driver Support</h3>
                            </div>
                            <div class="space-y-2">
                                <button class="w-full flex items-center justify-between p-3 hover:bg-gray-50 rounded-xl transition group" onclick="openModal('help-modal')">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-headset text-gray-400 w-5 group-hover:text-[#ff5e00]"></i>
                                        <span>Help Center</span>
                                    </div>
                                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-[#ff5e00]"></i>
                                </button>
                                <button class="w-full flex items-center justify-between p-3 hover:bg-gray-50 rounded-xl transition group" onclick="openModal('about-modal')">
                                    <div class="flex items-center gap-3">
                                        <i class="fas fa-info-circle text-gray-400 w-5 group-hover:text-[#ff5e00]"></i>
                                        <span>About Speedly</span>
                                    </div>
                                    <span class="text-sm text-gray-400">v2.5.1</span>
                                </button>
                            </div>
                        </div>

                        <!-- Danger Zone -->
                        <div class="bg-white rounded-xl shadow-sm p-6 hover:shadow-md transition col-span-3 mt-4 border-2 border-red-100">
                            <div class="flex items-center gap-3 mb-6">
                                <div class="w-12 h-12 bg-red-100 rounded-xl flex items-center justify-center text-red-600">
                                    <i class="fas fa-exclamation-triangle text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-semibold text-red-600">Danger Zone</h3>
                            </div>
                            <div class="flex gap-4">
                                <button class="px-6 py-3 border-2 border-gray-300 rounded-xl font-medium hover:bg-gray-50 transition flex items-center gap-2" onclick="logout()">
                                    <i class="fas fa-sign-out-alt"></i>
                                    Log Out
                                </button>
                                <button class="px-6 py-3 bg-red-50 text-red-600 rounded-xl font-medium hover:bg-red-100 transition flex items-center gap-2" onclick="openModal('delete-modal')">
                                    <i class="fas fa-trash-alt"></i>
                                    Delete Account
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODALS (keep all your existing modals) -->

    <!-- Driver Profile Modal -->
    <div id="driver-profile-modal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Driver Profile</h3>
                <button onclick="closeModal('driver-profile-modal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="driver-profile-form" onsubmit="saveDriverProfile(event)">
                    <div class="text-center mb-6">
                        <div class="profile-avatar-large">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <button type="button" class="text-sm text-[#ff5e00] font-medium" onclick="changePhoto()">
                            <i class="fas fa-camera mr-1"></i> Change Photo
                        </button>
                    </div>
                    <div class="form-group">
                        <label>Full Name</label>
                        <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_name); ?>" id="profile-name" required>
                    </div>
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_email); ?>" id="profile-email" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="tel" class="form-control" value="<?php echo htmlspecialchars($phone_number); ?>" id="profile-phone" required>
                    </div>
                    <div class="form-group">
                        <label>Date of Birth</label>
                        <input type="date" class="form-control" value="<?php echo $date_of_birth; ?>" id="profile-dob">
                    </div>
                    <div class="form-group">
                        <label>Gender</label>
                        <select class="form-control" id="profile-gender">
                            <option value="male" <?php echo $gender == 'male' ? 'selected' : ''; ?>>Male</option>
                            <option value="female" <?php echo $gender == 'female' ? 'selected' : ''; ?>>Female</option>
                            <option value="other" <?php echo $gender == 'other' ? 'selected' : ''; ?>>Other</option>
                            <option value="prefer-not-to-say" <?php echo $gender == 'prefer-not-to-say' ? 'selected' : ''; ?>>Prefer not to say</option>
                        </select>
                    </div>
                    <!-- Address field removed from form since it's not in database -->
                    <div class="modal-actions">
                        <button type="button" class="btn-secondary flex-1" onclick="closeModal('driver-profile-modal')">Cancel</button>
                        <button type="submit" class="btn-primary flex-1">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Keep all other modals exactly as they are (license-modal, bank-details-modal, vehicle-modal, etc.) -->
    
    <!-- Scripts (keep your existing scripts) -->
    <script>
    // Modal functions
    function openModal(modalId) {
        document.getElementById(modalId).classList.add('show');
        document.body.style.overflow = 'hidden';
    }

    function closeModal(modalId) {
        document.getElementById(modalId).classList.remove('show');
        document.body.style.overflow = 'auto';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('show');
            document.body.style.overflow = 'auto';
        }
    }

    // Toggle settings
    function toggleSetting(setting, value) {
        // Here you would make an API call to save the setting
        console.log(`Setting ${setting} to ${value}`);
        
        // Show success message
        showNotification(
            `${setting.replace('_', ' ')} has been ${value ? 'enabled' : 'disabled'}`,
            'success'
        );
    }

    // Save functions
    function saveDriverProfile(event) {
        event.preventDefault();
        
        const formData = {
            name: document.getElementById('profile-name').value,
            email: document.getElementById('profile-email').value,
            phone: document.getElementById('profile-phone').value,
            dob: document.getElementById('profile-dob').value,
            gender: document.getElementById('profile-gender').value
            // address removed
        };
        
        // Here you would make an API call to save the profile
        console.log('Saving profile:', formData);
        
        closeModal('driver-profile-modal');
        showNotification('Profile updated successfully', 'success');
    }

    function saveLicense(event) {
        event.preventDefault();
        
        const licenseData = {
            number: document.getElementById('license-number').value,
            expiry: document.getElementById('license-expiry').value
        };
        
        console.log('Saving license:', licenseData);
        
        closeModal('license-modal');
        showNotification('License information saved', 'success');
    }

    function saveBankDetails(event) {
        event.preventDefault();
        
        const bankData = {
            bank_name: document.getElementById('bank-name').value,
            account_number: document.getElementById('account-number').value,
            account_name: document.getElementById('account-name').value,
            set_default: document.getElementById('set-default').checked
        };
        
        console.log('Saving bank details:', bankData);
        
        closeModal('bank-details-modal');
        showNotification('Bank details saved successfully', 'success');
    }

    function saveVehicle(event) {
        event.preventDefault();
        
        const vehicleData = {
            model: document.getElementById('vehicle-model').value,
            year: document.getElementById('vehicle-year').value,
            color: document.getElementById('vehicle-color').value,
            plate: document.getElementById('plate-number').value,
            type: document.getElementById('vehicle-type').value,
            capacity: document.getElementById('passenger-capacity').value
        };
        
        console.log('Saving vehicle:', vehicleData);
        
        closeModal('vehicle-modal');
        showNotification('Vehicle information saved', 'success');
    }

    function saveInsurance(event) {
        event.preventDefault();
        
        const insuranceData = {
            expiry: document.getElementById('insurance-expiry').value,
            road_worthiness: document.getElementById('road-worthiness-expiry').value
        };
        
        console.log('Saving insurance:', insuranceData);
        
        closeModal('insurance-modal');
        showNotification('Insurance documents saved', 'success');
    }

    function saveSchedule(event) {
        event.preventDefault();
        
        const scheduleData = {};
        for (let i = 0; i < 7; i++) {
            scheduleData[i] = {
                start: document.getElementById(`start-${i}`).value,
                end: document.getElementById(`end-${i}`).value
            };
        }
        
        console.log('Saving schedule:', scheduleData);
        
        closeModal('schedule-modal');
        showNotification('Work schedule saved', 'success');
    }

    function saveEarningsSettings(event) {
        event.preventDefault();
        
        const settings = {
            auto_withdrawal: document.getElementById('auto-withdrawal').checked,
            threshold: document.getElementById('withdrawal-threshold').value,
            default_bank: document.getElementById('default-bank').value
        };
        
        console.log('Saving earnings settings:', settings);
        
        closeModal('earnings-modal');
        showNotification('Earnings settings saved', 'success');
    }

    // Set default bank
    function setDefaultBank(bankId) {
        Swal.fire({
            title: 'Set as Default?',
            text: 'Make this your default bank account for withdrawals',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ff5e00',
            confirmButtonText: 'Yes, set as default'
        }).then((result) => {
            if (result.isConfirmed) {
                // Here you would make an API call to set default bank
                console.log('Setting default bank:', bankId);
                showNotification('Default bank updated', 'success');
                setTimeout(() => location.reload(), 1500);
            }
        });
    }

    // Change photo
    function changePhoto() {
        // Create a file input dynamically
        const input = document.createElement('input');
        input.type = 'file';
        input.accept = 'image/*';
        input.onchange = function(e) {
            const file = e.target.files[0];
            if (file) {
                // Here you would upload the file
                console.log('Uploading photo:', file.name);
                showNotification('Photo uploaded successfully', 'success');
            }
        };
        input.click();
    }

    // Show help article
    function showHelpArticle(article) {
        closeModal('help-modal');
        Swal.fire({
            title: 'Help Article',
            text: `Opening ${article} help article...`,
            icon: 'info',
            confirmButtonColor: '#ff5e00'
        });
    }

    // Check notifications
    function checkNotifications() {
        Swal.fire({
            title: 'Notifications',
            html: `
                <div class="text-left">
                    <p class="mb-2">🔔 New ride request nearby</p>
                    <p class="mb-2">💰 Bonus earned: ₦2,500</p>
                    <p>📊 Weekly earnings report available</p>
                </div>
            `,
            icon: 'info',
            confirmButtonColor: '#ff5e00'
        });
    }

    // Logout
    function logout() {
        Swal.fire({
            title: 'Log Out',
            text: 'Are you sure you want to log out?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ff5e00',
            confirmButtonText: 'Yes, log out'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'SERVER/API/logout.php';
            }
        });
    }

    // Delete account
    function deleteAccount() {
        const confirmText = document.getElementById('delete-confirm')?.value;
        if (confirmText !== 'DELETE') {
            Swal.fire({
                icon: 'error',
                title: 'Incorrect Confirmation',
                text: 'Please type "DELETE" to confirm account deletion'
            });
            return;
        }
        
        Swal.fire({
            title: 'Processing...',
            text: 'Deleting your account',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        // Simulate API call
        setTimeout(() => {
            Swal.fire({
                icon: 'success',
                title: 'Account Deleted',
                text: 'Your driver account has been deleted',
                confirmButtonColor: '#ff5e00'
            }).then(() => {
                window.location.href = 'form.php';
            });
        }, 2000);
    }

    // Show notification
    function showNotification(message, type = 'info') {
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            info: '#3b82f6',
            warning: '#f59e0b'
        };
        
        const notification = document.createElement('div');
        notification.className = 'notification-toast';
        notification.style.backgroundColor = colors[type];
        notification.innerHTML = `
            <div class="flex items-center gap-3">
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
            </div>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
    }

    // Load driver status
    function loadDriverStatus() {
        fetch('SERVER/API/get_driver_status.php')
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update mobile status
                    const mobileBadge = document.getElementById('mobileStatusBadge');
                    const mobileText = document.getElementById('mobileStatusText');
                    
                    if (mobileBadge) {
                        mobileBadge.className = `status-badge ${data.status === 'online' ? 'status-online' : 'status-offline'}`;
                        mobileBadge.textContent = data.status === 'online' ? '🟢 Online' : '⚫ Offline';
                    }
                    
                    if (mobileText) {
                        mobileText.textContent = data.status === 'online' ? '🟢' : '⚫';
                    }
                    
                    // Update desktop status
                    const desktopBadge = document.getElementById('desktopStatusBadge');
                    const desktopStatus = document.getElementById('desktopStatusDisplay');
                    const desktopText = document.getElementById('desktopStatusText');
                    
                    if (desktopBadge) {
                        desktopBadge.className = `status-badge ${data.status === 'online' ? 'status-online' : 'status-offline'}`;
                        desktopBadge.textContent = data.status === 'online' ? '● ONLINE' : '○ OFFLINE';
                    }
                    
                    if (desktopStatus) {
                        if (data.status === 'online') {
                            desktopStatus.innerHTML = `
                                <span class="w-2 h-2 bg-green-400 rounded-full animate-pulse"></span>
                                Online
                            `;
                        } else {
                            desktopStatus.innerHTML = `
                                <span class="w-2 h-2 bg-gray-400 rounded-full"></span>
                                Offline
                            `;
                        }
                    }
                }
            })
            .catch(error => console.error('Error loading status:', error));
    }

    // Load status on page load
    document.addEventListener('DOMContentLoaded', function() {
        loadDriverStatus();
        
        // Refresh status every 30 seconds
        setInterval(loadDriverStatus, 30000);
    });

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.modal.show').forEach(modal => {
                modal.classList.remove('show');
            });
            document.body.style.overflow = 'auto';
        }
    });
    </script>
</body>
</html>