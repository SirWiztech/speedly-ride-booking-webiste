<?php
session_start();
require_once 'SERVER/API/db-connect.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: form.php");
    exit;
}

// Check if user has driver role
if ($_SESSION['role'] !== 'driver') {
    if ($_SESSION['role'] === 'client') {
        header("Location: ride_history.php");
    } else {
        header("Location: admin_dashboard.php");
    }
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'];

// Get driver profile
$driverQuery = "SELECT id FROM driver_profiles WHERE user_id = ?";
$driverStmt = $conn->prepare($driverQuery);
if (!$driverStmt) {
    die("Database error. Please try again later.");
}
$driverStmt->bind_param("s", $user_id);
$driverStmt->execute();
$driverResult = $driverStmt->get_result();
$driverData = $driverResult->fetch_assoc();

// If no driver profile found, create one
if (!$driverData) {
    // Check if user exists and has driver role
    $userCheckQuery = "SELECT role FROM users WHERE id = ?";
    $userCheckStmt = $conn->prepare($userCheckQuery);
    $userCheckStmt->bind_param("s", $user_id);
    $userCheckStmt->execute();
    $userCheckResult = $userCheckStmt->get_result();
    $userData = $userCheckResult->fetch_assoc();
    
    if ($userData && $userData['role'] === 'driver') {
        // Create a basic driver profile
        $createProfileQuery = "INSERT INTO driver_profiles (id, user_id, license_number, license_expiry, driver_status, verification_status, created_at) 
                               VALUES (UUID(), ?, 'PENDING', DATE_ADD(NOW(), INTERVAL 1 YEAR), 'offline', 'pending', NOW())";
        $createStmt = $conn->prepare($createProfileQuery);
        $createStmt->bind_param("s", $user_id);
        $createStmt->execute();
        
        // Fetch the new profile
        $driverStmt->execute();
        $driverResult = $driverStmt->get_result();
        $driverData = $driverResult->fetch_assoc();
    } else {
        header("Location: form.php?error=not_driver");
        exit;
    }
}

$driver_id = $driverData['id'];

// Get all rides for this driver including declined ones
$ridesQuery = "SELECT r.*, 
               u.full_name as client_name,
               u.profile_picture_url as client_photo,
               DATE_FORMAT(r.created_at, '%M %d, %Y') as formatted_date,
               DATE_FORMAT(r.created_at, '%h:%i %p') as formatted_time,
               ROUND(r.total_fare * 0.2, 2) as platform_commission,
               ROUND(r.total_fare * 0.8, 2) as net_earnings,
               (SELECT COUNT(*) FROM ride_declines rd WHERE rd.ride_id = r.id AND rd.driver_id = ?) as was_declined,
               (SELECT created_at FROM ride_declines rd WHERE rd.ride_id = r.id AND rd.driver_id = ?) as declined_at
               FROM rides r
               JOIN client_profiles cp ON r.client_id = cp.id
               JOIN users u ON cp.user_id = u.id
               WHERE r.driver_id = ? 
               ORDER BY r.created_at DESC";
$ridesStmt = $conn->prepare($ridesQuery);
$ridesResult = null;
if ($ridesStmt) {
    $ridesStmt->bind_param("sss", $driver_id, $driver_id, $driver_id);
    $ridesStmt->execute();
    $ridesResult = $ridesStmt->get_result();
}

// Get declined rides that were never accepted
$declinedRidesQuery = "SELECT r.*, 
                      u.full_name as client_name,
                      u.profile_picture_url as client_photo,
                      DATE_FORMAT(r.created_at, '%M %d, %Y') as formatted_date,
                      DATE_FORMAT(r.created_at, '%h:%i %p') as formatted_time,
                      ROUND(r.total_fare * 0.2, 2) as platform_commission,
                      ROUND(r.total_fare * 0.8, 2) as net_earnings,
                      rd.created_at as declined_at,
                      rd.auto_decline
                      FROM ride_declines rd
                      JOIN rides r ON rd.ride_id = r.id
                      JOIN client_profiles cp ON r.client_id = cp.id
                      JOIN users u ON cp.user_id = u.id
                      WHERE rd.driver_id = ? 
                      ORDER BY rd.created_at DESC";
$declinedStmt = $conn->prepare($declinedRidesQuery);
$declinedRidesResult = null;
if ($declinedStmt) {
    $declinedStmt->bind_param("s", $driver_id);
    $declinedStmt->execute();
    $declinedRidesResult = $declinedStmt->get_result();
}

// Get statistics with commission breakdown
$statsQuery = "SELECT 
    COUNT(DISTINCT r.id) as total_rides,
    SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as completed_rides,
    SUM(CASE WHEN r.status LIKE 'cancelled%' THEN 1 ELSE 0 END) as cancelled_rides,
    COALESCE(SUM(CASE WHEN r.status = 'completed' THEN r.total_fare ELSE 0 END), 0) as total_fare_amount,
    COALESCE(SUM(CASE WHEN r.status = 'completed' THEN r.driver_payout ELSE 0 END), 0) as total_earnings,
    COALESCE(SUM(CASE WHEN r.status = 'completed' THEN r.total_fare * 0.2 ELSE 0 END), 0) as total_commission,
    COALESCE(AVG(CASE WHEN r.status = 'completed' THEN r.total_fare ELSE NULL END), 0) as avg_fare,
    (SELECT COUNT(*) FROM ride_declines WHERE driver_id = ?) as declined_count
    FROM rides r
    WHERE r.driver_id = ?";
$statsStmt = $conn->prepare($statsQuery);
$stats = [
    'total_rides' => 0,
    'completed_rides' => 0,
    'cancelled_rides' => 0,
    'total_fare_amount' => 0,
    'total_earnings' => 0,
    'total_commission' => 0,
    'avg_fare' => 0,
    'declined_count' => 0
];

if ($statsStmt) {
    $statsStmt->bind_param("ss", $driver_id, $driver_id);
    $statsStmt->execute();
    $statsResult = $statsStmt->get_result();
    $stats = $statsResult->fetch_assoc();
    // Ensure values are not null
    foreach ($stats as $key => $value) {
        if ($value === null) {
            $stats[$key] = 0;
        }
    }
}

// Calculate derived stats
$total_commission = $stats['total_fare_amount'] * 0.2;
$driver_total_earnings = $stats['total_fare_amount'] * 0.8;

// Get notification count
$notifQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notifStmt = $conn->prepare($notifQuery);
$notificationCount = 0;
if ($notifStmt) {
    $notifStmt->bind_param("s", $user_id);
    $notifStmt->execute();
    $notifResult = $notifStmt->get_result();
    $notifData = $notifResult->fetch_assoc();
    $notificationCount = $notifData['count'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedly • Book History</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="./CSS/driver_dashboard.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .commission-badge {
            background: #f3f4f6;
            color: #6b7280;
            font-size: 0.7rem;
            padding: 2px 6px;
            border-radius: 12px;
            display: inline-block;
        }
        .tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
        }
        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 5px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
        }
        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }
        .declined-ride {
            border-left: 4px solid #9ca3af;
            background: linear-gradient(to right, #f9fafb, #ffffff);
        }
        .badge-declined {
            background: #6b7280;
            color: white;
        }
        .tab-container {
            display: flex;
            border-bottom: 2px solid #e5e7eb;
            margin-bottom: 20px;
        }
        .tab {
            padding: 10px 20px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        .tab.active {
            color: #ff5e00;
            border-bottom: 2px solid #ff5e00;
            margin-bottom: -2px;
        }
        .tab:hover {
            color: #ff5e00;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Dashboard Container -->
    <div class="dashboard-container">
        <!-- Mobile View -->
        <div class="mobile-view">
            <!-- Mobile Header -->
            <div class="header">
                <div class="user-info">
                    <h1>Book History</h1>
                    <p>Welcome, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></p>
                </div>
                <button class="notification-btn bg-[#ff5e00] rounded-2xl p-2 relative" onclick="checkNotifications()">
                    <i class="fas fa-bell text-white"></i>
                    <?php if ($notificationCount > 0): ?>
                    <span class="notification-badge"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 gap-3 p-4">
                <div class="bg-white p-4 rounded-xl shadow-sm">
                    <div class="text-gray-500 text-sm">Completed</div>
                    <div class="text-2xl font-bold text-green-600"><?php echo $stats['completed_rides'] ?? 0; ?></div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm">
                    <div class="text-gray-500 text-sm">Cancelled</div>
                    <div class="text-2xl font-bold text-red-600"><?php echo $stats['cancelled_rides'] ?? 0; ?></div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm">
                    <div class="text-gray-500 text-sm">Declined</div>
                    <div class="text-2xl font-bold text-gray-600"><?php echo $stats['declined_count'] ?? 0; ?></div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm">
                    <div class="text-gray-500 text-sm">You Earned</div>
                    <div class="text-2xl font-bold text-[#ff5e00]">₦<?php echo number_format($stats['total_earnings'] ?? 0); ?></div>
                    <div class="text-xs text-gray-400 mt-1">After 20% commission</div>
                </div>
            </div>

            <!-- Commission Summary -->
            <div class="bg-gradient-to-r from-gray-700 to-gray-900 mx-4 p-4 rounded-xl text-white mb-2">
                <div class="flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <i class="fas fa-percent bg-white/20 p-2 rounded-full"></i>
                        <div>
                            <div class="text-sm opacity-90">Total Fares</div>
                            <div class="font-bold">₦<?php echo number_format($stats['total_fare_amount'] ?? 0); ?></div>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm opacity-90">Platform Commission (20%)</div>
                        <div class="font-bold text-yellow-300">- ₦<?php echo number_format($stats['total_fare_amount'] * 0.2, 2); ?></div>
                    </div>
                </div>
            </div>

            <!-- Tabs -->
            <div class="tab-container mx-4 mt-4">
                <div class="tab active" onclick="switchTab('accepted')">Accepted Rides</div>
                <div class="tab" onclick="switchTab('declined')">Declined Rides</div>
            </div>

            <!-- Accepted Rides List -->
            <div id="accepted-rides" class="px-4 pb-20">
                <h2 class="font-semibold mb-3">Your Accepted Rides</h2>
                <?php if ($ridesResult && $ridesResult->num_rows > 0): ?>
                    <?php while ($ride = $ridesResult->fetch_assoc()): 
                        $driver_payout = $ride['driver_payout'] ?? ($ride['total_fare'] * 0.8);
                        $commission = $ride['platform_commission'] ?? ($ride['total_fare'] * 0.2);
                    ?>
                    <div class="bg-white p-4 rounded-xl shadow-sm mb-3">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="text-xs text-gray-500"><?php echo $ride['formatted_date'] ?? date('M d, Y', strtotime($ride['created_at'])); ?></span>
                                <div class="font-semibold mt-1"><?php echo htmlspecialchars(substr($ride['pickup_address'] ?? 'Pickup', 0, 30)); ?></div>
                                <div class="text-sm text-gray-500 flex items-center gap-1 mt-1">
                                    <i class="fas fa-arrow-down text-xs"></i>
                                    <?php echo htmlspecialchars(substr($ride['destination_address'] ?? 'Destination', 0, 30)); ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <?php if ($ride['status'] == 'completed'): ?>
                                    <span class="font-bold text-green-600">+₦<?php echo number_format($driver_payout); ?></span>
                                <?php elseif ($ride['status'] == 'pending' || $ride['status'] == 'accepted'): ?>
                                    <span class="font-bold text-orange-500">₦<?php echo number_format($ride['total_fare'] ?? 0); ?></span>
                                <?php else: ?>
                                    <span class="font-bold text-gray-500">₦<?php echo number_format($ride['total_fare'] ?? 0); ?></span>
                                <?php endif; ?>
                                <div class="text-xs text-gray-400 flex items-center justify-end gap-1 mt-1">
                                    <span class="tooltip">
                                        <i class="fas fa-info-circle"></i>
                                        <span class="tooltiptext">Total fare: ₦<?php echo number_format($ride['total_fare'] ?? 0); ?><br>Commission (20%): -₦<?php echo number_format($commission); ?></span>
                                    </span>
                                    <span>You get 80%</span>
                                </div>
                                <div class="text-xs mt-2">
                                    <?php if ($ride['status'] == 'completed'): ?>
                                        <span class="bg-green-100 text-green-700 px-2 py-1 rounded-full">Completed</span>
                                    <?php elseif ($ride['status'] == 'pending'): ?>
                                        <span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded-full">Pending</span>
                                    <?php elseif ($ride['status'] == 'accepted'): ?>
                                        <span class="bg-blue-100 text-blue-700 px-2 py-1 rounded-full">Accepted</span>
                                    <?php elseif ($ride['status'] == 'cancelled_by_client'): ?>
                                        <span class="bg-red-100 text-red-700 px-2 py-1 rounded-full">Cancelled by Client</span>
                                    <?php elseif ($ride['status'] == 'cancelled_by_driver'): ?>
                                        <span class="bg-red-100 text-red-700 px-2 py-1 rounded-full">Cancelled by You</span>
                                    <?php elseif (strpos($ride['status'], 'cancelled') !== false): ?>
                                        <span class="bg-red-100 text-red-700 px-2 py-1 rounded-full">Cancelled</span>
                                    <?php else: ?>
                                        <span class="bg-gray-100 text-gray-700 px-2 py-1 rounded-full"><?php echo ucfirst(str_replace('_', ' ', $ride['status'])); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <div class="border-t mt-3 pt-3 flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                    <?php echo strtoupper(substr($ride['client_name'] ?? 'C', 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="text-sm font-medium"><?php echo htmlspecialchars($ride['client_name'] ?? 'Client'); ?></div>
                                    <div class="text-xs text-gray-500">ID: #<?php echo substr($ride['ride_number'] ?? $ride['id'], -8); ?></div>
                                </div>
                            </div>
                            <button class="text-[#ff5e00] text-sm" onclick="viewDetails('<?php echo $ride['id']; ?>')">
                                Details <i class="fas fa-chevron-right text-xs"></i>
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="bg-white p-8 rounded-xl text-center">
                        <i class="fas fa-history text-4xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">No accepted rides yet</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Declined Rides List -->
            <div id="declined-rides" class="px-4 pb-20" style="display: none;">
                <h2 class="font-semibold mb-3">Rides You Declined</h2>
                <?php if ($declinedRidesResult && $declinedRidesResult->num_rows > 0): ?>
                    <?php while ($ride = $declinedRidesResult->fetch_assoc()): 
                        $declined_time = strtotime($ride['declined_at']);
                        $created_time = strtotime($ride['created_at']);
                        $response_time = $declined_time - $created_time;
                    ?>
                    <div class="bg-white p-4 rounded-xl shadow-sm mb-3 declined-ride">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="text-xs text-gray-500"><?php echo date('M d, Y', $created_time); ?></span>
                                <div class="font-semibold mt-1"><?php echo htmlspecialchars(substr($ride['pickup_address'] ?? 'Pickup', 0, 30)); ?></div>
                                <div class="text-sm text-gray-500 flex items-center gap-1 mt-1">
                                    <i class="fas fa-arrow-down text-xs"></i>
                                    <?php echo htmlspecialchars(substr($ride['destination_address'] ?? 'Destination', 0, 30)); ?>
                                </div>
                                <div class="text-xs text-gray-400 mt-2">
                                    <i class="fas fa-clock mr-1"></i> 
                                    <?php echo $ride['auto_decline'] ? 'Auto-declined after timeout' : 'Manually declined'; ?>
                                    <?php if ($response_time > 0): ?>
                                        • Responded in <?php echo floor($response_time / 1000); ?>s
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-gray-500">₦<?php echo number_format($ride['total_fare'] ?? 0); ?></span>
                                <div class="text-xs mt-2">
                                    <span class="bg-gray-200 text-gray-700 px-2 py-1 rounded-full">Declined</span>
                                </div>
                                <div class="text-xs text-gray-400 mt-2">
                                    <?php echo date('h:i A', $declined_time); ?>
                                </div>
                            </div>
                        </div>
                        <div class="border-t mt-3 pt-3 flex justify-between items-center">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                    <?php echo strtoupper(substr($ride['client_name'] ?? 'C', 0, 1)); ?>
                                </div>
                                <div>
                                    <div class="text-sm font-medium"><?php echo htmlspecialchars($ride['client_name'] ?? 'Client'); ?></div>
                                    <div class="text-xs text-gray-500">ID: #<?php echo substr($ride['ride_number'] ?? $ride['id'], -8); ?></div>
                                </div>
                            </div>
                            <button class="text-gray-500 text-sm" onclick="viewRideDetails('<?php echo $ride['id']; ?>')">
                                View <i class="fas fa-chevron-right text-xs"></i>
                            </button>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="bg-white p-8 rounded-xl text-center">
                        <i class="fas fa-times-circle text-4xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">No declined rides</p>
                        <p class="text-sm text-gray-400 mt-1">Rides you decline will appear here</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Bottom Navigation -->
            <?php require_once './components/mobile-driver-nav.php'; ?>
        </div>

        <!-- Desktop View -->
        <div class="desktop-view">
            <!-- Sidebar -->
            <div class="desktop-sidebar">
                <div class="logo">
                    <img src="./main-assets/logo-no-background.png" alt="Speedly Logo" class="logo-image">
                </div>

                <!-- Desktop Navigation -->
                <?php require_once './components/desktop-driver-nav.php'; ?>

                <!-- User Profile -->
                <div class="user-profile cursor-pointer hover:bg-gray-100 transition" onclick="window.location.href='driver_profile.php'">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h3 class="font-semibold"><?php echo htmlspecialchars($user_name); ?></h3>
                        <p class="text-sm text-gray-500">Driver</p>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="desktop-main">
                <!-- Header -->
                <div class="desktop-header">
                    <div class="desktop-title">
                        <h1 class="text-2xl font-bold">Book History</h1>
                        <p class="text-gray-500">View all your rides including declined ones</p>
                    </div>
                    <button class="notification-btn bg-gray-100 p-3 rounded-xl relative hover:bg-gray-200 transition" onclick="checkNotifications()">
                        <i class="fas fa-bell text-gray-700 text-xl"></i>
                        <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge"><?php echo $notificationCount; ?></span>
                        <?php endif; ?>
                    </button>
                </div>

                <!-- Stats Cards with Commission Breakdown -->
                <div class="grid grid-cols-5 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-xl shadow-sm">
                        <div class="text-gray-500 text-sm mb-1">Completed</div>
                        <div class="text-3xl font-bold text-green-600"><?php echo $stats['completed_rides'] ?? 0; ?></div>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm">
                        <div class="text-gray-500 text-sm mb-1">Cancelled</div>
                        <div class="text-3xl font-bold text-red-600"><?php echo $stats['cancelled_rides'] ?? 0; ?></div>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm">
                        <div class="text-gray-500 text-sm mb-1">Declined</div>
                        <div class="text-3xl font-bold text-gray-600"><?php echo $stats['declined_count'] ?? 0; ?></div>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm">
                        <div class="text-gray-500 text-sm mb-1">Total Fares</div>
                        <div class="text-3xl font-bold">₦<?php echo number_format($stats['total_fare_amount'] ?? 0); ?></div>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm">
                        <div class="text-gray-500 text-sm mb-1">You Earned</div>
                        <div class="text-3xl font-bold text-[#ff5e00]">₦<?php echo number_format($stats['total_earnings'] ?? 0); ?></div>
                        <div class="text-xs text-gray-400 mt-1">After 20% commission</div>
                    </div>
                </div>

                <!-- Commission Summary Card -->
                <div class="bg-gradient-to-r from-[#ff5e00] to-[#ff8c3a] p-6 rounded-2xl text-white mb-8">
                    <div class="flex justify-between items-center">
                        <div class="flex items-center gap-4">
                            <div class="bg-white/20 p-3 rounded-xl">
                                <i class="fas fa-chart-pie text-2xl"></i>
                            </div>
                            <div>
                                <h3 class="text-lg font-semibold">Earnings Breakdown</h3>
                                <p class="text-sm opacity-90">Platform commission: 20% on all completed rides</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-8">
                            <div class="text-center">
                                <div class="text-sm opacity-80">Total Fares</div>
                                <div class="text-xl font-bold">₦<?php echo number_format($stats['total_fare_amount'] ?? 0); ?></div>
                            </div>
                            <div class="text-center">
                                <div class="text-sm opacity-80">Commission (20%)</div>
                                <div class="text-xl font-bold text-yellow-300">₦<?php echo number_format($stats['total_fare_amount'] * 0.2, 2); ?></div>
                            </div>
                            <div class="text-center">
                                <div class="text-sm opacity-80">Your Earnings</div>
                                <div class="text-xl font-bold">₦<?php echo number_format($stats['total_earnings'] ?? 0); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="border-b border-gray-200 mb-6">
                    <nav class="flex gap-8">
                        <button class="tab-desktop pb-4 px-1 font-medium text-[#ff5e00] border-b-2 border-[#ff5e00]" onclick="switchDesktopTab('accepted')">
                            Accepted Rides
                        </button>
                        <button class="tab-desktop pb-4 px-1 font-medium text-gray-500 hover:text-[#ff5e00]" onclick="switchDesktopTab('declined')">
                            Declined Rides
                        </button>
                    </nav>
                </div>

                <!-- Accepted Rides Table -->
                <div id="desktop-accepted-rides" class="bg-white rounded-xl shadow-sm overflow-hidden">
                    <div class="p-6 border-b">
                        <h2 class="text-xl font-semibold">Accepted Rides</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Date & Time</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Ride Details</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Client</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Total Fare</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Commission (20%)</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Your Earnings</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Status</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($ridesResult && $ridesResult->num_rows > 0):
                                    $ridesResult->data_seek(0);
                                    while ($ride = $ridesResult->fetch_assoc()): 
                                        $total_fare = $ride['total_fare'] ?? 0;
                                        $commission = $ride['platform_commission'] ?? ($total_fare * 0.2);
                                        $driver_payout = $ride['driver_payout'] ?? ($total_fare * 0.8);
                                ?>
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="font-medium"><?php echo $ride['formatted_date'] ?? date('M d, Y', strtotime($ride['created_at'])); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo $ride['formatted_time'] ?? date('h:i A', strtotime($ride['created_at'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium"><?php echo htmlspecialchars(substr($ride['pickup_address'] ?? 'Pickup', 0, 30)); ?></div>
                                        <div class="text-sm text-gray-500 mt-1">→ <?php echo htmlspecialchars(substr($ride['destination_address'] ?? 'Destination', 0, 30)); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                                <?php echo strtoupper(substr($ride['client_name'] ?? 'C', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="font-medium"><?php echo htmlspecialchars($ride['client_name'] ?? 'Client'); ?></div>
                                                <div class="text-xs text-gray-500">ID: #<?php echo substr($ride['id'], -8); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium">₦<?php echo number_format($total_fare, 2); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-red-600 font-medium">-₦<?php echo number_format($commission, 2); ?></div>
                                        <div class="text-xs text-gray-400">20% platform fee</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($ride['status'] == 'completed'): ?>
                                            <div class="font-bold text-green-600">+₦<?php echo number_format($driver_payout, 2); ?></div>
                                        <?php elseif ($ride['status'] == 'pending' || $ride['status'] == 'accepted'): ?>
                                            <div class="font-medium text-orange-500">₦<?php echo number_format($driver_payout, 2); ?> (pending)</div>
                                        <?php else: ?>
                                            <div class="font-medium text-gray-500">₦<?php echo number_format($driver_payout, 2); ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($ride['status'] == 'completed'): ?>
                                            <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-medium">Completed</span>
                                        <?php elseif ($ride['status'] == 'pending'): ?>
                                            <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-medium">Pending</span>
                                        <?php elseif ($ride['status'] == 'accepted'): ?>
                                            <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-medium">Accepted</span>
                                        <?php elseif ($ride['status'] == 'cancelled_by_client'): ?>
                                            <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-medium">Cancelled by Client</span>
                                        <?php elseif ($ride['status'] == 'cancelled_by_driver'): ?>
                                            <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-medium">Cancelled by You</span>
                                        <?php elseif (strpos($ride['status'], 'cancelled') !== false): ?>
                                            <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-medium">Cancelled</span>
                                        <?php else: ?>
                                            <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-xs font-medium"><?php echo ucfirst(str_replace('_', ' ', $ride['status'])); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <button class="text-[#ff5e00] hover:underline" onclick="viewDetails('<?php echo $ride['id']; ?>')">
                                            View Details
                                        </button>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                <tr>
                                    <td colspan="8" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-history text-4xl mb-3 opacity-30"></i>
                                        <p>No accepted rides yet</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Declined Rides Table -->
                <div id="desktop-declined-rides" class="bg-white rounded-xl shadow-sm overflow-hidden" style="display: none;">
                    <div class="p-6 border-b">
                        <h2 class="text-xl font-semibold">Declined Rides</h2>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Date & Time</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Ride Details</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Client</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Fare</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Declined At</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Type</th>
                                    <th class="px-6 py-4 text-left text-sm font-semibold text-gray-600">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($declinedRidesResult && $declinedRidesResult->num_rows > 0):
                                    $declinedRidesResult->data_seek(0);
                                    while ($ride = $declinedRidesResult->fetch_assoc()): 
                                        $created_time = strtotime($ride['created_at']);
                                        $declined_time = strtotime($ride['declined_at']);
                                        $response_time = $declined_time - $created_time;
                                ?>
                                <tr class="border-t hover:bg-gray-50">
                                    <td class="px-6 py-4">
                                        <div class="font-medium"><?php echo date('M d, Y', $created_time); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo date('h:i A', $created_time); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium"><?php echo htmlspecialchars(substr($ride['pickup_address'] ?? 'Pickup', 0, 30)); ?></div>
                                        <div class="text-sm text-gray-500 mt-1">→ <?php echo htmlspecialchars(substr($ride['destination_address'] ?? 'Destination', 0, 30)); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 bg-gray-200 rounded-full flex items-center justify-center">
                                                <?php echo strtoupper(substr($ride['client_name'] ?? 'C', 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="font-medium"><?php echo htmlspecialchars($ride['client_name'] ?? 'Client'); ?></div>
                                                <div class="text-xs text-gray-500">ID: #<?php echo substr($ride['id'], -8); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-600">₦<?php echo number_format($ride['total_fare'] ?? 0, 2); ?></div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="text-sm"><?php echo date('h:i A', $declined_time); ?></div>
                                        <div class="text-xs text-gray-500">
                                            <?php echo $response_time > 0 ? floor($response_time / 1000) . 's response time' : ''; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if ($ride['auto_decline']): ?>
                                            <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-medium">Auto-declined</span>
                                        <?php else: ?>
                                            <span class="bg-gray-200 text-gray-700 px-3 py-1 rounded-full text-xs font-medium">Manual</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4">
                                        <button class="text-gray-500 hover:underline" onclick="viewRideDetails('<?php echo $ride['id']; ?>')">
                                            View
                                        </button>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                <tr>
                                    <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                        <i class="fas fa-times-circle text-4xl mb-3 opacity-30"></i>
                                        <p>No declined rides</p>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function checkNotifications() {
        Swal.fire({
            title: 'Notifications',
            html: '<p>🔔 No new notifications</p>',
            icon: 'info',
            confirmButtonColor: '#ff5e00'
        });
    }

    function viewDetails(rideId) {
        window.location.href = 'generate_receipt.php?ride_id=' + rideId;
    }

    function viewRideDetails(rideId) {
        window.location.href = 'generate_receipt.php?ride_id=' + rideId;
    }

    // Tab switching for mobile
    function switchTab(tab) {
        const acceptedTab = document.querySelector('.tab:first-child');
        const declinedTab = document.querySelector('.tab:last-child');
        const acceptedDiv = document.getElementById('accepted-rides');
        const declinedDiv = document.getElementById('declined-rides');
        
        if (tab === 'accepted') {
            acceptedTab.classList.add('active');
            declinedTab.classList.remove('active');
            acceptedDiv.style.display = 'block';
            declinedDiv.style.display = 'none';
        } else {
            acceptedTab.classList.remove('active');
            declinedTab.classList.add('active');
            acceptedDiv.style.display = 'none';
            declinedDiv.style.display = 'block';
        }
    }

    // Tab switching for desktop
    function switchDesktopTab(tab) {
        const tabs = document.querySelectorAll('.tab-desktop');
        const acceptedDiv = document.getElementById('desktop-accepted-rides');
        const declinedDiv = document.getElementById('desktop-declined-rides');
        
        tabs.forEach(t => {
            t.classList.remove('text-[#ff5e00]', 'border-b-2', 'border-[#ff5e00]');
            t.classList.add('text-gray-500');
        });
        
        if (tab === 'accepted') {
            tabs[0].classList.add('text-[#ff5e00]', 'border-b-2', 'border-[#ff5e00]');
            tabs[0].classList.remove('text-gray-500');
            acceptedDiv.style.display = 'block';
            declinedDiv.style.display = 'none';
        } else {
            tabs[1].classList.add('text-[#ff5e00]', 'border-b-2', 'border-[#ff5e00]');
            tabs[1].classList.remove('text-gray-500');
            acceptedDiv.style.display = 'none';
            declinedDiv.style.display = 'block';
        }
    }

    // Set active tab on load
    document.addEventListener('DOMContentLoaded', function() {
        // Mobile tabs default to accepted
        // Desktop tabs default to accepted
    });
    </script>
</body>
</html>