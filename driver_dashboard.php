<?php
session_start();
require_once 'SERVER/API/db-connect.php';

// Check if user is logged in and is a driver
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true || $_SESSION['role'] !== 'driver') {
    header("Location: form.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'] ?? 'Driver';

// Get driver profile data
$driverQuery = "SELECT dp.*, u.profile_picture_url, u.is_verified, u.phone_number, u.email,
                (SELECT AVG(rating) FROM driver_ratings WHERE driver_id = dp.id) as avg_rating,
                (SELECT COUNT(*) FROM driver_ratings WHERE driver_id = dp.id) as total_reviews
                FROM driver_profiles dp 
                JOIN users u ON dp.user_id = u.id 
                WHERE u.id = ?";
$driverStmt = $conn->prepare($driverQuery);
if (!$driverStmt) {
    die("Error preparing driver query: " . $conn->error);
}
$driverStmt->bind_param("s", $user_id);
$driverStmt->execute();
$driverResult = $driverStmt->get_result();
$driverData = $driverResult->fetch_assoc();

// Check if driver profile exists
if (!$driverData) {
    // Redirect if no driver profile found
    header("Location: kyc.php");
    exit;
}

// Safely get driver_id with null checking
$driver_id = isset($driverData['id']) ? $driverData['id'] : null;

if (!$driver_id) {
    // If driver_id is still null, redirect to KYC
    header("Location: kyc.php");
    exit;
}

// Get today's earnings
$todayQuery = "SELECT COALESCE(SUM(driver_payout), 0) as today_earnings 
               FROM rides WHERE driver_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'";
$todayStmt = $conn->prepare($todayQuery);
if ($todayStmt) {
    $todayStmt->bind_param("s", $driver_id);
    $todayStmt->execute();
    $todayResult = $todayStmt->get_result();
    $todayData = $todayResult->fetch_assoc();
    $todayEarnings = $todayData['today_earnings'] ?? 0;
} else {
    $todayEarnings = 0;
}

// Get total earnings
$totalQuery = "SELECT COALESCE(SUM(driver_payout), 0) as total_earnings 
               FROM rides WHERE driver_id = ? AND status = 'completed'";
$totalStmt = $conn->prepare($totalQuery);
if ($totalStmt) {
    $totalStmt->bind_param("s", $driver_id);
    $totalStmt->execute();
    $totalResult = $totalStmt->get_result();
    $totalData = $totalResult->fetch_assoc();
    $totalEarnings = $totalData['total_earnings'] ?? 0;
} else {
    $totalEarnings = 0;
}

// Get total amount withdrawn
$withdrawnQuery = "SELECT COALESCE(SUM(amount), 0) as total_withdrawn 
                   FROM driver_withdrawals 
                   WHERE driver_id = ? AND status IN ('approved', 'paid')";
$withdrawnStmt = $conn->prepare($withdrawnQuery);
$totalWithdrawn = 0;
if ($withdrawnStmt) {
    $withdrawnStmt->bind_param("s", $driver_id);
    $withdrawnStmt->execute();
    $withdrawnResult = $withdrawnStmt->get_result();
    $withdrawnData = $withdrawnResult->fetch_assoc();
    $totalWithdrawn = $withdrawnData['total_withdrawn'] ?? 0;
}

// Calculate available balance (earnings - withdrawals)
$availableBalance = $totalEarnings - $totalWithdrawn;

// Get completed rides count
$completedQuery = "SELECT COUNT(*) as completed_rides FROM rides WHERE driver_id = ? AND status = 'completed'";
$completedStmt = $conn->prepare($completedQuery);
if ($completedStmt) {
    $completedStmt->bind_param("s", $driver_id);
    $completedStmt->execute();
    $completedResult = $completedStmt->get_result();
    $completedData = $completedResult->fetch_assoc();
    $completedRides = $completedData['completed_rides'] ?? 0;
} else {
    $completedRides = 0;
}

// Get today's rides count
$todayRidesQuery = "SELECT COUNT(*) as today_rides FROM rides WHERE driver_id = ? AND DATE(created_at) = CURDATE()";
$todayRidesStmt = $conn->prepare($todayRidesQuery);
if ($todayRidesStmt) {
    $todayRidesStmt->bind_param("s", $driver_id);
    $todayRidesStmt->execute();
    $todayRidesResult = $todayRidesStmt->get_result();
    $todayRidesData = $todayRidesResult->fetch_assoc();
    $todayRides = $todayRidesData['today_rides'] ?? 0;
} else {
    $todayRides = 0;
}

// Get current active ride (accepted but not completed)
$activeRideQuery = "SELECT r.*, 
                  u.full_name as client_name,
                  u.phone_number as client_phone,
                  u.profile_picture_url as client_photo
                  FROM rides r
                  JOIN client_profiles cp ON r.client_id = cp.id
                  JOIN users u ON cp.user_id = u.id
                  WHERE r.driver_id = ? AND r.status = 'accepted'
                  ORDER BY r.created_at DESC LIMIT 1";
$activeRideStmt = $conn->prepare($activeRideQuery);
if ($activeRideStmt) {
    $activeRideStmt->bind_param("s", $driver_id);
    $activeRideStmt->execute();
    $activeRideResult = $activeRideStmt->get_result();
    $activeRide = $activeRideResult->fetch_assoc();
} else {
    $activeRide = null;
}

// Get pending rides - show both public and private rides
$pendingRidesQuery = "SELECT r.*, 
                      u.full_name as client_name,
                      u.phone_number as client_phone,
                      u.profile_picture_url as client_photo,
                      r.distance_km,
                      r.total_fare,
                      r.ride_type,
                      r.pickup_address,
                      r.destination_address,
                      r.created_at,
                      CASE 
                          WHEN r.driver_id = ? THEN 'private'
                          ELSE 'public'
                      END as request_type
                      FROM rides r
                      LEFT JOIN client_profiles cp ON r.client_id = cp.id
                      LEFT JOIN users u ON cp.user_id = u.id
                      WHERE r.status = 'pending' 
                      AND (r.driver_id IS NULL OR r.driver_id = ?)
                      AND NOT EXISTS (
                          SELECT 1 FROM ride_declines rd 
                          WHERE rd.ride_id = r.id AND rd.driver_id = ?
                      )
                      ORDER BY 
                          CASE 
                              WHEN r.driver_id = ? THEN 0  -- Private rides show first
                              ELSE 1                        -- Public rides show after
                          END,
                          r.created_at DESC
                      LIMIT 10";

$pendingRidesStmt = $conn->prepare($pendingRidesQuery);
if ($pendingRidesStmt) {
    $pendingRidesStmt->bind_param("ssss", $driver_id, $driver_id, $driver_id, $driver_id);
    $pendingRidesStmt->execute();
    $pendingRidesResult = $pendingRidesStmt->get_result();
    $pendingRides = [];
    while ($ride = $pendingRidesResult->fetch_assoc()) {
        $pendingRides[] = $ride;
    }
    
    // Set $nextRide to the first available pending ride
    $nextRide = !empty($pendingRides) ? $pendingRides[0] : null;
} else {
    $pendingRides = [];
    $nextRide = null;
}

// Get recent rides
$recentRidesQuery = "SELECT r.*, 
                     u.full_name as client_name,
                     u.profile_picture_url as client_photo,
                     ROUND(r.total_fare * 0.2, 2) as platform_commission,
                     ROUND(r.total_fare * 0.8, 2) as net_earnings
                     FROM rides r
                     JOIN client_profiles cp ON r.client_id = cp.id
                     JOIN users u ON cp.user_id = u.id
                     WHERE r.driver_id = ?
                     ORDER BY r.created_at DESC LIMIT 5";
$recentRidesStmt = $conn->prepare($recentRidesQuery);
if ($recentRidesStmt) {
    $recentRidesStmt->bind_param("s", $driver_id);
    $recentRidesStmt->execute();
    $recentRidesResult = $recentRidesStmt->get_result();
} else {
    $recentRidesResult = null;
}

// Get detailed earnings stats
$statsQuery = "SELECT 
    COUNT(*) as total_rides,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_rides,
    SUM(CASE WHEN status LIKE 'cancelled%' THEN 1 ELSE 0 END) as cancelled_rides,
    COALESCE(SUM(total_fare), 0) as total_fare_amount,
    COALESCE(SUM(driver_payout), 0) as total_earnings,
    COALESCE(SUM(total_fare * 0.2), 0) as total_commission,
    COALESCE(AVG(CASE WHEN status = 'completed' THEN total_fare ELSE NULL END), 0) as avg_fare
    FROM rides WHERE driver_id = ?";
$statsStmt = $conn->prepare($statsQuery);
$stats = [
    'total_rides' => 0,
    'completed_rides' => 0,
    'cancelled_rides' => 0,
    'total_fare_amount' => 0,
    'total_earnings' => 0,
    'total_commission' => 0,
    'avg_fare' => 0
];

if ($statsStmt) {
    $statsStmt->bind_param("s", $driver_id);
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

// Get unread notifications count
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

// Get acceptance rate
$acceptanceRateQuery = "SELECT 
    (COUNT(CASE WHEN r.status = 'accepted' THEN 1 END) * 100.0 / NULLIF(COUNT(*), 0)) as acceptance_rate
    FROM ride_declines rd
    RIGHT JOIN rides r ON rd.ride_id = r.id
    WHERE r.driver_id = ?";
$acceptanceRateStmt = $conn->prepare($acceptanceRateQuery);
if ($acceptanceRateStmt) {
    $acceptanceRateStmt->bind_param("s", $driver_id);
    $acceptanceRateStmt->execute();
    $acceptanceRateResult = $acceptanceRateStmt->get_result();
    $acceptanceRateData = $acceptanceRateResult->fetch_assoc();
    $acceptance_rate = round($acceptanceRateData['acceptance_rate'] ?? 100, 1);
} else {
    $acceptance_rate = 100;
}

// Safe values with null coalescing
$driver_status = $driverData['driver_status'] ?? 'offline';
$verification_status = $driverData['verification_status'] ?? 'pending';
$avg_rating = round($driverData['avg_rating'] ?? 4.8, 1);
$total_reviews = $driverData['total_reviews'] ?? 0;
$phone_number = $driverData['phone_number'] ?? '';
$email = $driverData['email'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedly • Driver Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="./CSS/driver_dashboard.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .status-toggle-btn {
            padding: 12px 24px;
            border-radius: 50px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            width: 100%;
            justify-content: center;
        }
        .status-toggle-btn.online {
            background: #ef4444;
            color: white;
        }
        .status-toggle-btn.offline {
            background: #10b981;
            color: white;
        }
        .status-toggle-btn:hover {
            transform: scale(1.02);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .status-toggle-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }
        .status-badge.online {
            background: #d4edda;
            color: #155724;
        }
        .status-badge.offline {
            background: #f8f9fa;
            color: #6c757d;
        }
        .status-badge.accepted {
            background: #cff3ff;
            color: #0369a1;
        }
        .online-badge {
            display: flex;
            align-items: center;
            gap: 6px;
            background: #10b981;
            color: white;
            padding: 6px 12px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 500;
        }
        .pulse {
            width: 10px;
            height: 10px;
            background: white;
            border-radius: 50%;
            position: relative;
        }
        .pulse::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            background: white;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }
            50% {
                transform: scale(1.5);
                opacity: 0.5;
            }
            100% {
                transform: scale(1);
                opacity: 1;
            }
        }
        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        .animate-slide-in {
            animation: slideIn 0.3s ease-out;
        }
        .notification-toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 9999;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .rating-star {
            transition: all 0.2s;
            cursor: pointer;
        }
        .rating-star:hover {
            transform: scale(1.2);
            color: #fbbf24;
        }
        .progress-bar {
            transition: width 0.5s ease;
        }
        .active-ride-card {
            border-left: 4px solid #10b981;
            background: linear-gradient(to right, #f0fdf4, #ffffff);
        }
        .pending-ride-card {
            border-left: 4px solid #f97316;
            background: linear-gradient(to right, #fff7ed, #ffffff);
        }
        .private-ride-card {
            border-left: 4px solid #ff5e00;
            background: linear-gradient(to right, #fff2e5, #ffffff);
        }
        .ride-action-btn {
            transition: all 0.2s;
        }
        .ride-action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .countdown-timer {
            font-family: monospace;
            font-size: 1.25rem;
            font-weight: bold;
        }
        .timer-critical {
            color: #dc2626;
            animation: blink 1s infinite;
        }
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.5; }
        }
        .ride-type-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
            margin-left: 8px;
        }
        .private-ride-badge {
            background: #ff5e00;
            color: white;
        }
        .public-ride-badge {
            background: #4CAF50;
            color: white;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-top: 1rem;
        }
        .stat-item {
            background: #f9fafb;
            padding: 1rem;
            border-radius: 0.75rem;
            text-align: center;
        }
        .stat-label {
            font-size: 0.875rem;
            color: #6b7280;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #111827;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <!-- MOBILE DRIVER VIEW -->
        <div class="mobile-view">
            <!-- header -->
            <div class="header">
                <div class="user-info">
                    <h1 class="text-black">Welcome, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!</h1>
                    <p class="text-gray-500 flex items-center gap-2">
                        <span id="mobileStatusText" class="<?php echo $driver_status == 'online' ? 'text-green-600' : 'text-gray-600'; ?>">
                            <?php echo $driver_status == 'online' ? '● Online' : '○ Offline'; ?>
                        </span>
                        • <?php echo $todayRides; ?> rides today
                    </p>
                </div>
                <button class="notification-btn mb-10 text-black bg-[#ff5e00] rounded-2xl p-2 relative" onclick="checkNotifications()">
                    <i class="fas fa-bell text-white"></i>
                    <?php if ($notificationCount > 0): ?>
                    <span class="notification-badge"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- DRIVER TOTAL EARNINGS -->
            <div class="balance-section rounded-3xl bg-[#ff5e00] mx-4 p-5 text-white">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-lg font-medium opacity-90">Available Balance</h2>
                        <div class="balance-amount mt-1 text-2xl font-bold" id="totalEarnings">₦<?php echo number_format($availableBalance, 2); ?></div>
                        <p class="text-xs opacity-75 mt-1">Total Earnings: ₦<?php echo number_format($totalEarnings, 2); ?></p>
                    </div>
                    <i class="fas fa-wallet text-3xl"></i>
                </div>
                <div class="balance-change bg-white/20 rounded-2xl px-3 py-1.5 w-fit mt-2">
                    <i class="fas fa-arrow-up"></i>
                    <span class="font-medium" id="todayEarnings">+₦<?php echo number_format($todayEarnings, 2); ?> today</span>
                </div>
            </div>

            <!-- STATUS TOGGLE BUTTON -->
            <button id="mobileStatusToggle" class="status-toggle-btn mx-4 mt-4 <?php echo $driver_status; ?>" onclick="toggleDriverStatus()" <?php echo ($verification_status != 'approved') ? 'disabled' : ''; ?>>
                <i class="fas fa-power-off"></i>
                <span id="mobileStatusBtnText">Go <?php echo $driver_status == 'online' ? 'Offline' : 'Online'; ?></span>
            </button>
            <?php if ($verification_status != 'approved'): ?>
            <p class="text-xs text-red-500 mx-4 mt-1">Complete KYC to go online</p>
            <?php endif; ?>

            <!-- WITHDRAWAL BUTTON -->
            <button class="withdraw-btn bg-gray-800 hover:bg-gray-900 text-white flex items-center justify-center gap-3 mx-4 mt-4" onclick="withdrawFunds()">
                <i class="fas fa-hand-holding-usd"></i>
                <span class="font-semibold">Withdraw Earnings (₦<?php echo number_format($availableBalance, 2); ?>)</span>
            </button>

            <!-- NEXT UPCOMING RIDE / ACTIVE RIDE -->
            <?php if ($activeRide && isset($activeRide['id'])): ?>
            <!-- Active Ride Management -->
            <div class="upcoming-ride-card mx-4 mt-5 border-2 border-green-500 active-ride-card" id="activeRideCard">
                <div class="bg-green-500 text-white px-4 py-2 rounded-t-2xl flex justify-between items-center">
                    <span class="font-semibold"><i class="fas fa-check-circle mr-2"></i>ACTIVE RIDE</span>
                    <span class="text-sm bg-white text-green-700 px-3 py-1 rounded-full">● Live</span>
                </div>
                <div class="p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-bold text-lg flex items-center gap-2">
                                <i class="fas fa-map-pin text-[#ff5e00]"></i> 
                                <?php echo htmlspecialchars(substr($activeRide['pickup_address'] ?? 'Pickup location', 0, 30)); ?>
                            </h3>
                            <p class="text-gray-600 text-sm flex items-center gap-1 mt-1">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($activeRide['client_name'] ?? 'Client'); ?> • 
                                <i class="fas fa-phone ml-2"></i> <?php echo htmlspecialchars($activeRide['client_phone'] ?? 'N/A'); ?>
                            </p>
                            <p class="text-gray-500 text-xs mt-2">
                                <i class="fas fa-flag-checkered mr-1"></i> Destination: <?php echo htmlspecialchars(substr($activeRide['destination_address'] ?? 'Destination', 0, 40)); ?>
                            </p>
                            <p class="text-gray-500 text-xs mt-1">
                                <i class="fas fa-money-bill-wave mr-1"></i> Fare: ₦<?php echo number_format($activeRide['total_fare'] ?? 0, 2); ?> 
                                (You get: ₦<?php echo number_format($activeRide['driver_payout'] ?? 0, 2); ?>)
                            </p>
                        </div>
                        <div class="bg-green-100 text-green-800 p-3 rounded-xl text-center">
                            <span class="text-xs">PICKUP TIME</span>
                            <div class="font-bold"><?php echo date('h:i A', strtotime($activeRide['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <!-- Progress Bar -->
                    <div class="mt-4">
                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                            <span class="text-green-600 font-medium">✓ Accepted</span>
                            <span>En Route</span>
                            <span>Completed</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2.5">
                            <div class="bg-green-600 h-2.5 rounded-full progress-bar" style="width: 33%"></div>
                        </div>
                    </div>
                    
                    <!-- Ride Management Buttons -->
                    <div class="grid grid-cols-2 gap-3 mt-4">
                        <button class="bg-green-600 hover:bg-green-700 text-white py-3 px-4 rounded-xl font-semibold flex items-center justify-center gap-2 transition ride-action-btn" onclick="completeRide('<?php echo $activeRide['id']; ?>', '<?php echo $activeRide['driver_payout'] ?? 0; ?>')">
                            <i class="fas fa-check-circle"></i> Complete Ride
                        </button>
                        <button class="bg-red-600 hover:bg-red-700 text-white py-3 px-4 rounded-xl font-semibold flex items-center justify-center gap-2 transition ride-action-btn" onclick="cancelActiveRide('<?php echo $activeRide['id']; ?>')">
                            <i class="fas fa-times-circle"></i> Cancel Ride
                        </button>
                    </div>
                    
                    <!-- Navigation Buttons -->
                    <div class="flex gap-2 mt-3">
                        <button class="flex-1 bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg text-sm flex items-center justify-center gap-1 ride-action-btn" onclick="callClient('<?php echo $activeRide['client_phone'] ?? ''; ?>')">
                            <i class="fas fa-phone-alt"></i> Call Client
                        </button>
                        <button class="flex-1 bg-purple-600 hover:bg-purple-700 text-white py-2 rounded-lg text-sm flex items-center justify-center gap-1 ride-action-btn" onclick="navigateTo('<?php echo $activeRide['pickup_latitude'] ?? ''; ?>', '<?php echo $activeRide['pickup_longitude'] ?? ''; ?>')">
                            <i class="fas fa-map-marked-alt"></i> Navigate
                        </button>
                    </div>
                    
                    <!-- Status Update -->
                    <div class="mt-3 text-center">
                        <span class="inline-flex items-center gap-1 text-xs bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full">
                            <i class="fas fa-hourglass-half"></i> Waiting for pickup confirmation
                        </span>
                    </div>
                </div>
            </div>

            <?php elseif ($nextRide && isset($nextRide['id'])): ?>
            <!-- Pending Ride Request -->
            <div class="upcoming-ride-card mx-4 mt-5 border-2 <?php echo $nextRide['request_type'] == 'private' ? 'border-[#ff5e00] private-ride-card' : 'border-orange-500 pending-ride-card'; ?>" id="pendingRideCard" data-ride-id="<?php echo $nextRide['id']; ?>">
                <div class="<?php echo $nextRide['request_type'] == 'private' ? 'bg-[#ff5e00]' : 'bg-orange-500'; ?> text-white px-4 py-2 rounded-t-2xl flex justify-between items-center">
                    <span class="font-semibold">
                        <i class="fas fa-<?php echo $nextRide['request_type'] == 'private' ? 'user-tag' : 'clock'; ?> mr-2"></i>
                        <?php echo $nextRide['request_type'] == 'private' ? 'PRIVATE RIDE REQUEST' : 'NEW RIDE REQUEST'; ?>
                    </span>
                    <span class="text-sm bg-white <?php echo $nextRide['request_type'] == 'private' ? 'text-[#ff5e00]' : 'text-orange-700'; ?> px-3 py-1 rounded-full animate-pulse">Action required</span>
                </div>
                <div class="p-4">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="font-bold text-lg flex items-center gap-2">
                                <i class="fas fa-map-pin text-[#ff5e00]"></i> 
                                <?php echo htmlspecialchars(substr($nextRide['pickup_address'] ?? 'Pickup location', 0, 30)); ?>
                                <span class="ride-type-badge <?php echo $nextRide['request_type'] == 'private' ? 'private-ride-badge' : 'public-ride-badge'; ?>">
                                    <?php echo strtoupper($nextRide['request_type']); ?>
                                </span>
                            </h3>
                            <p class="text-gray-600 text-sm flex items-center gap-1 mt-1">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($nextRide['client_name'] ?? 'Client'); ?> • 
                                <span class="text-green-600">Est. arrival</span>
                            </p>
                            <p class="text-gray-500 text-xs mt-2">
                                <i class="fas fa-flag-checkered mr-1"></i> To: <?php echo htmlspecialchars(substr($nextRide['destination_address'] ?? 'Destination', 0, 40)); ?>
                            </p>
                            <p class="text-gray-500 text-xs mt-1">
                                <i class="fas fa-money-bill-wave mr-1"></i> Fare: ₦<?php echo number_format($nextRide['total_fare'] ?? 0, 2); ?>
                                (Est. <?php echo round($nextRide['distance_km'] ?? 0, 1); ?> km)
                            </p>
                        </div>
                        <div class="<?php echo $nextRide['request_type'] == 'private' ? 'bg-orange-100' : 'bg-orange-100'; ?> text-orange-800 p-3 rounded-xl text-center">
                            <span class="text-xs">REQUESTED</span>
                            <div class="font-bold"><?php echo date('h:i A', strtotime($nextRide['created_at'])); ?></div>
                        </div>
                    </div>
                    
                    <!-- Countdown Timer -->
                    <div class="mt-3 bg-yellow-50 p-3 rounded-lg text-center">
                        <p class="text-sm text-yellow-700 mb-1">
                            <i class="fas fa-hourglass-half mr-1"></i> 
                            Accept within:
                        </p>
                        <div id="countdownTimer" class="countdown-timer text-2xl font-bold text-orange-600" data-created="<?php echo strtotime($nextRide['created_at']); ?>">30</div>
                        <p class="text-xs text-gray-500 mt-1">Declining may affect acceptance rate</p>
                    </div>
                    
                    <!-- Accept/Decline Buttons -->
                    <div class="grid grid-cols-2 gap-3 mt-4">
                        <button class="bg-green-600 hover:bg-green-700 text-white py-3 px-4 rounded-xl font-semibold flex items-center justify-center gap-2 transition ride-action-btn" onclick="acceptRide('<?php echo $nextRide['id']; ?>')">
                            <i class="fas fa-check"></i> Accept Ride
                        </button>
                        <button class="bg-red-600 hover:bg-red-700 text-white py-3 px-4 rounded-xl font-semibold flex items-center justify-center gap-2 transition ride-action-btn" onclick="declineRide('<?php echo $nextRide['id']; ?>')">
                            <i class="fas fa-times"></i> Decline
                        </button>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <!-- No Rides Available -->
            <div class="upcoming-ride-card mx-4 mt-5 bg-gray-50" id="noRideCard">
                <div class="text-center py-6">
                    <i class="fas fa-clock text-5xl text-gray-300 mb-3"></i>
                    <p class="text-gray-600 font-medium">No active rides</p>
                    <p class="text-gray-500 text-sm mt-1">Go online to receive ride requests</p>
                    <?php if ($driver_status != 'online' && $verification_status == 'approved'): ?>
                    <button class="mt-3 bg-[#ff5e00] text-white px-6 py-2 rounded-xl font-medium hover:bg-[#e65500] transition" onclick="toggleDriverStatus()">
                        <i class="fas fa-power-off mr-2"></i> Go Online
                    </button>
                    <?php elseif ($verification_status != 'approved'): ?>
                    <button class="mt-3 bg-gray-400 text-white px-6 py-2 rounded-xl font-medium cursor-not-allowed" disabled>
                        <i class="fas fa-exclamation-triangle mr-2"></i> Complete KYC First
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- COMPLETED RIDES WITH RATING -->
            <div class="balance-section rounded-3xl bg-gray-100 mx-4 p-5 mt-3 text-black flex justify-between items-center">
                <div class="card-details">
                    <h2 class="font-bold text-gray-700">Completed Rides</h2>
                    <div class="text-3xl font-extrabold text-black" id="completedRides"><?php echo $completedRides; ?></div>
                    <div class="flex items-center gap-1 mt-1">
                        <span class="rating-stars">
                            <?php 
                            $fullStars = floor($avg_rating);
                            $halfStar = ($avg_rating - $fullStars) >= 0.5;
                            for ($i = 1; $i <= 5; $i++):
                                if ($i <= $fullStars): ?>
                                    <i class="fas fa-star text-yellow-400"></i>
                                <?php elseif ($halfStar && $i == $fullStars + 1): ?>
                                    <i class="fas fa-star-half-alt text-yellow-400"></i>
                                <?php else: ?>
                                    <i class="far fa-star text-yellow-400"></i>
                            <?php endif; endfor; ?>
                        </span>
                        <span class="text-sm font-semibold"><?php echo $avg_rating; ?> (<?php echo $total_reviews; ?> reviews)</span>
                    </div>
                </div>
                <button class="bg-[#ff5e00] text-white px-5 py-3 rounded-2xl font-semibold text-sm hover:bg-[#e65500] transition" onclick="showDetailedStats()">
                    View Stats
                </button>
            </div>

            <!-- QUICK ACTIONS -->
            <div class="quick-actions mt-4">
                <button class="action-btn" onclick="toggleDriverStatus()" <?php echo ($verification_status != 'approved') ? 'disabled' : ''; ?>>
                    <div class="action-icon"><i class="fas fa-toggle-<?php echo $driver_status == 'online' ? 'on' : 'off'; ?>"></i></div>
                    <span>Go <?php echo $driver_status == 'online' ? 'Offline' : 'Online'; ?></span>
                </button>
                <button class="action-btn" onclick="window.location.href='book_history.php'">
                    <div class="action-icon"><i class="fas fa-calendar-check"></i></div>
                    <span>History</span>
                </button>
                <button class="action-btn" onclick="showDetailedStats()">
                    <div class="action-icon"><i class="fas fa-chart-line"></i></div>
                    <span>Earnings</span>
                </button>
                <button class="action-btn" onclick="showSupport()">
                    <div class="action-icon"><i class="fas fa-headset"></i></div>
                    <span>Support</span>
                </button>
                <button class="action-btn" onclick="showFuelPrices()">
                    <div class="action-icon"><i class="fas fa-gas-pump"></i></div>
                    <span>Fuel</span>
                </button>
                <button class="action-btn" onclick="showSafetyTips()">
                    <div class="action-icon"><i class="fas fa-shield-alt"></i></div>
                    <span>Safety</span>
                </button>
                <button class="action-btn" onclick="showDetailedStats()">
                    <div class="action-icon"><i class="fas fa-tachometer-alt"></i></div>
                    <span>Insights</span>
                </button>
                <button class="action-btn" onclick="window.location.href='driver_settings.php'">
                    <div class="action-icon"><i class="fas fa-cog"></i></div>
                    <span>Settings</span>
                </button>
            </div>

            <!-- RECENT RIDES -->
            <div class="transactions-section">
                <div class="section-header">
                    <div class="section-title">🕒 Recent Rides</div>
                    <button class="see-all-btn" onclick="window.location.href='book_history.php'">See All</button>
                </div>
                <div class="transaction-list" id="recentRidesList">
                    <?php 
                    if ($recentRidesResult && $recentRidesResult->num_rows > 0):
                        while ($ride = $recentRidesResult->fetch_assoc()): 
                    ?>
                    <div class="transaction-item cursor-pointer hover:bg-gray-50 transition" onclick="viewRideDetails('<?php echo $ride['id']; ?>')">
                        <div class="transaction-info">
                            <div class="transaction-icon" style="background:#E8F5E9; color:#2E7D32;">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="transaction-details">
                                <h4 class="font-medium"><?php echo htmlspecialchars(substr($ride['pickup_address'] ?? 'Ride', 0, 20)); ?> → <?php echo htmlspecialchars(substr($ride['destination_address'] ?? 'Destination', 0, 15)); ?></h4>
                                <p class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($ride['created_at'])); ?> • <?php echo htmlspecialchars($ride['client_name'] ?? 'Client'); ?></p>
                                <p class="text-xs text-gray-400">Fare: ₦<?php echo number_format($ride['total_fare'] ?? 0); ?> (Commission: ₦<?php echo number_format($ride['platform_commission'] ?? 0); ?>)</p>
                            </div>
                        </div>
                        <div class="transaction-amount positive font-bold text-green-600">+₦<?php echo number_format($ride['net_earnings'] ?? $ride['driver_payout'] ?? 0); ?></div>
                    </div>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <div class="text-center py-6 text-gray-500">
                        <i class="fas fa-history text-3xl mb-2 opacity-50"></i>
                        <p>No recent rides</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bottom Navigation -->
            <?php require_once './components/mobile-driver-nav.php'; ?>
        </div>

        <!-- DESKTOP DRIVER VIEW -->
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
                        <p class="text-sm <?php echo $verification_status == 'approved' ? 'text-green-600' : 'text-orange-500'; ?>">
                            <?php echo $verification_status == 'approved' ? '✓ Verified Driver' : '⏳ Pending Verification'; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- MAIN DESKTOP CONTENT -->
            <div class="desktop-main">
                <div class="desktop-header">
                    <div class="desktop-title">
                        <h1 class="text-2xl font-bold">Ready to drive, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!</h1>
                        <p id="desktopStatusLine" class="flex items-center gap-2 mt-1">
                            <?php if ($driver_status == 'online'): ?>
                                <span class="inline-block w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                                <span class="text-green-600 font-medium">Online</span>
                            <?php else: ?>
                                <span class="inline-block w-2 h-2 bg-gray-400 rounded-full"></span>
                                <span class="text-gray-600">Offline</span>
                            <?php endif; ?>
                            • <?php echo $todayRides; ?> rides today
                        </p>
                    </div>
                    <div class="flex items-center gap-4">
                        <button class="notification-btn bg-gray-100 p-3 rounded-xl relative hover:bg-gray-200 transition" onclick="checkNotifications()">
                            <i class="fas fa-bell text-gray-700 text-xl"></i>
                            <?php if ($notificationCount > 0): ?>
                            <span class="notification-badge"><?php echo $notificationCount; ?></span>
                            <?php endif; ?>
                        </button>
                        <div class="online-badge">
                            <span class="pulse"></span>
                            <span><?php echo $todayRides; ?> rides today</span>
                        </div>
                    </div>
                </div>

                <!-- DESKTOP GRID -->
                <div class="desktop-grid">
                    <!-- TOTAL EARNINGS CARD -->
                    <div class="desktop-card balance-card flex flex-col bg-gradient-to-br from-[#ff5e00] to-[#ff8c3a]">
                        <div class="flex justify-between items-start">
                            <div>
                                <h2 class="text-2xl font-bold text-white [text-shadow:2px_2px_0_rgba(0,0,0,0.2)]">Available Balance</h2>
                                <div class="balance-amount text-white mt-2 text-4xl font-bold" id="desktopTotalEarnings">₦<?php echo number_format($availableBalance, 2); ?></div>
                                <p class="text-white/80 text-sm mt-1">Total Earnings: ₦<?php echo number_format($totalEarnings, 2); ?></p>
                            </div>
                            <span class="bg-white/30 px-3 py-1 rounded-full text-white text-sm">
                                <i class="fas fa-check-circle"></i> Available
                            </span>
                        </div>
                        <div class="balance-change bg-black/30 text-white rounded-xl px-4 py-2 w-fit mt-2">
                            <i class="fas fa-arrow-up"></i><span id="desktopTodayEarnings" class="ml-1">+₦<?php echo number_format($todayEarnings, 2); ?> (today)</span>
                        </div>
                        <button class="mt-5 bg-white text-[#ff5e00] font-bold py-3 px-4 rounded-xl flex items-center justify-center gap-2 hover:shadow-lg transition transform hover:scale-105" onclick="withdrawFunds()">
                            <i class="fas fa-hand-holding-usd"></i> Withdraw funds
                        </button>
                    </div>

                    <!-- STATUS TOGGLE CARD -->
                    <div class="desktop-card flex flex-col items-center justify-center">
                        <h2 class="text-xl font-bold mb-4">Driver Status</h2>
                        <div class="text-center mb-4">
                            <div class="text-5xl mb-2">
                                <?php if ($driver_status == 'online'): ?>
                                    <i class="fas fa-toggle-on text-green-500"></i>
                                <?php else: ?>
                                    <i class="fas fa-toggle-off text-gray-400"></i>
                                <?php endif; ?>
                            </div>
                            <div class="text-2xl font-bold mb-2" id="desktopStatusDisplay">
                                <?php echo ucfirst($driver_status); ?>
                            </div>
                            <span class="status-badge <?php echo $driver_status; ?>" id="desktopStatusBadge">
                                <?php echo $driver_status == 'online' ? '● ONLINE' : '○ OFFLINE'; ?>
                            </span>
                        </div>
                        <button id="desktopStatusToggle" class="status-toggle-btn <?php echo $driver_status; ?>" onclick="toggleDriverStatus()" <?php echo ($verification_status != 'approved') ? 'disabled' : ''; ?>>
                            <i class="fas fa-power-off"></i>
                            <span id="desktopStatusBtnText">Go <?php echo $driver_status == 'online' ? 'Offline' : 'Online'; ?></span>
                        </button>
                        <?php if ($verification_status != 'approved'): ?>
                        <p class="text-xs text-red-500 mt-2">Complete KYC to go online</p>
                        <?php endif; ?>
                    </div>

                    <!-- COMPLETED RIDES STATS -->
                    <div class="desktop-card flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between">
                                <h2 class="text-lg font-bold">Completed rides</h2>
                                <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-xs font-semibold">+<?php echo $todayRides; ?> today</span>
                            </div>
                            <div class="text-4xl font-extrabold mt-3" id="desktopCompletedRides"><?php echo $completedRides; ?></div>
                            <div class="flex items-center gap-2 mt-2">
                                <span class="rating-stars text-xl">
                                    <?php 
                                    for ($i = 1; $i <= 5; $i++):
                                        if ($i <= floor($avg_rating)): ?>
                                            <i class="fas fa-star text-yellow-400"></i>
                                        <?php elseif ($i == floor($avg_rating) + 1 && ($avg_rating - floor($avg_rating)) >= 0.5): ?>
                                            <i class="fas fa-star-half-alt text-yellow-400"></i>
                                        <?php else: ?>
                                            <i class="far fa-star text-yellow-400"></i>
                                    <?php endif; endfor; ?>
                                </span>
                                <span class="font-bold text-lg"><?php echo $avg_rating; ?></span>
                                <span class="text-gray-500 text-sm">(<?php echo $total_reviews; ?> reviews)</span>
                            </div>
                        </div>
                        <button class="bg-gray-100 hover:bg-gray-200 mt-5 py-3 rounded-xl font-medium transition" onclick="showDetailedStats()">
                            <i class="fas fa-chart-pie mr-2"></i> View detailed stats
                        </button>
                    </div>

                    <!-- ACTIVE RIDE / UPCOMING RIDE - DESKTOP -->
                    <?php if ($activeRide && isset($activeRide['id'])): ?>
                    <div class="desktop-card col-span-2 relative overflow-hidden border-2 border-green-500 active-ride-card" id="desktopActiveRide">
                        <div class="bg-green-500 text-white px-4 py-2 rounded-t-2xl flex justify-between items-center">
                            <span class="font-semibold"><i class="fas fa-check-circle mr-2"></i>ACTIVE RIDE IN PROGRESS</span>
                            <span class="text-sm bg-white text-green-700 px-3 py-1 rounded-full font-semibold">● Live</span>
                        </div>
                        <div class="p-6">
                            <div class="flex gap-6">
                                <div class="bg-green-50 p-4 rounded-xl">
                                    <i class="fas fa-map-marker-alt text-3xl text-green-600"></i>
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-bold text-xl"><?php echo htmlspecialchars($activeRide['pickup_address'] ?? 'Pickup location'); ?></h3>
                                    <div class="grid grid-cols-2 gap-4 mt-4">
                                        <div>
                                            <p class="text-gray-500 text-sm">Client</p>
                                            <p class="font-semibold flex items-center gap-2">
                                                <i class="fas fa-user text-[#ff5e00]"></i> <?php echo htmlspecialchars($activeRide['client_name'] ?? 'Client'); ?>
                                            </p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500 text-sm">Contact</p>
                                            <p class="font-semibold flex items-center gap-2">
                                                <i class="fas fa-phone text-[#ff5e00]"></i> <?php echo htmlspecialchars($activeRide['client_phone'] ?? 'N/A'); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <p class="text-gray-600 mt-3">
                                        <i class="fas fa-flag-checkered text-[#ff5e00] mr-2"></i> 
                                        <span class="font-medium">Destination:</span> <?php echo htmlspecialchars($activeRide['destination_address'] ?? 'Destination'); ?>
                                    </p>
                                    <div class="flex gap-4 mt-3">
                                        <span class="bg-gray-100 px-3 py-1 rounded-full text-sm">
                                            <i class="fas fa-money-bill-wave text-green-600 mr-1"></i> 
                                            Fare: ₦<?php echo number_format($activeRide['total_fare'] ?? 0, 2); ?>
                                        </span>
                                        <span class="bg-gray-100 px-3 py-1 rounded-full text-sm">
                                            <i class="fas fa-clock text-orange-600 mr-1"></i> 
                                            Started: <?php echo date('h:i A', strtotime($activeRide['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Progress Tracker -->
                            <div class="mt-6">
                                <div class="flex justify-between mb-2">
                                    <span class="text-sm font-medium text-green-600">✓ Accepted</span>
                                    <span class="text-sm text-gray-400">En Route</span>
                                    <span class="text-sm text-gray-400">Completed</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-3">
                                    <div class="bg-green-600 h-3 rounded-full progress-bar" style="width: 33%"></div>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="border-t mt-6 pt-4 flex gap-3">
                                <button class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl font-medium flex-1 flex items-center justify-center gap-2 transition ride-action-btn" onclick="completeRide('<?php echo $activeRide['id']; ?>', '<?php echo $activeRide['driver_payout'] ?? 0; ?>')">
                                    <i class="fas fa-check-circle"></i> Complete Ride
                                </button>
                                <button class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-xl font-medium flex-1 flex items-center justify-center gap-2 transition ride-action-btn" onclick="cancelActiveRide('<?php echo $activeRide['id']; ?>')">
                                    <i class="fas fa-times-circle"></i> Cancel Ride
                                </button>
                                <button class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl font-medium flex items-center justify-center gap-2 transition ride-action-btn" onclick="callClient('<?php echo $activeRide['client_phone'] ?? ''; ?>')">
                                    <i class="fas fa-phone-alt"></i>
                                </button>
                                <button class="bg-purple-600 hover:bg-purple-700 text-white px-6 py-3 rounded-xl font-medium flex items-center justify-center gap-2 transition ride-action-btn" onclick="navigateTo('<?php echo $activeRide['pickup_latitude'] ?? ''; ?>', '<?php echo $activeRide['pickup_longitude'] ?? ''; ?>')">
                                    <i class="fas fa-map-marked-alt"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <?php elseif ($nextRide && isset($nextRide['id'])): ?>
                    <!-- Pending Ride Request -->
                    <div class="desktop-card col-span-2 relative overflow-hidden border-2 <?php echo $nextRide['request_type'] == 'private' ? 'border-[#ff5e00] private-ride-card' : 'border-orange-500 pending-ride-card'; ?>" id="desktopPendingRide" data-ride-id="<?php echo $nextRide['id']; ?>">
                        <div class="<?php echo $nextRide['request_type'] == 'private' ? 'bg-[#ff5e00]' : 'bg-orange-500'; ?> text-white px-4 py-2 rounded-t-2xl flex justify-between items-center">
                            <span class="font-semibold">
                                <i class="fas fa-<?php echo $nextRide['request_type'] == 'private' ? 'user-tag' : 'clock'; ?> mr-2"></i>
                                <?php echo $nextRide['request_type'] == 'private' ? 'PRIVATE RIDE REQUEST' : 'NEW RIDE REQUEST'; ?>
                            </span>
                            <span class="text-sm bg-white <?php echo $nextRide['request_type'] == 'private' ? 'text-[#ff5e00]' : 'text-orange-700'; ?> px-3 py-1 rounded-full font-semibold animate-pulse">Action required</span>
                        </div>
                        <div class="p-6">
                            <div class="flex gap-6">
                                <div class="<?php echo $nextRide['request_type'] == 'private' ? 'bg-orange-50' : 'bg-orange-50'; ?> p-4 rounded-xl">
                                    <i class="fas fa-map-marker-alt text-3xl <?php echo $nextRide['request_type'] == 'private' ? 'text-[#ff5e00]' : 'text-orange-500'; ?>"></i>
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-bold text-xl">
                                        <?php echo htmlspecialchars($nextRide['pickup_address'] ?? 'Pickup location'); ?>
                                        <span class="ride-type-badge <?php echo $nextRide['request_type'] == 'private' ? 'private-ride-badge' : 'public-ride-badge'; ?>">
                                            <?php echo strtoupper($nextRide['request_type']); ?>
                                        </span>
                                    </h3>
                                    <div class="grid grid-cols-2 gap-4 mt-4">
                                        <div>
                                            <p class="text-gray-500 text-sm">Client</p>
                                            <p class="font-semibold"><i class="fas fa-user text-[#ff5e00] mr-2"></i><?php echo htmlspecialchars($nextRide['client_name'] ?? 'Client'); ?></p>
                                        </div>
                                        <div>
                                            <p class="text-gray-500 text-sm">Distance</p>
                                            <p class="font-semibold"><i class="fas fa-road text-[#ff5e00] mr-2"></i><?php echo round($nextRide['distance_km'] ?? 0, 1); ?> km</p>
                                        </div>
                                    </div>
                                    <p class="text-gray-500 mt-3"><i class="fas fa-flag-checkered mr-2"></i> To: <?php echo htmlspecialchars($nextRide['destination_address'] ?? 'Destination'); ?></p>
                                    <div class="flex gap-4 mt-3">
                                        <span class="bg-green-100 text-green-800 px-3 py-1 rounded-full text-sm font-semibold">
                                            <i class="fas fa-money-bill-wave mr-1"></i> ₦<?php echo number_format($nextRide['total_fare'] ?? 0, 2); ?>
                                        </span>
                                        <span class="bg-blue-100 text-blue-800 px-3 py-1 rounded-full text-sm">
                                            <i class="fas fa-clock mr-1"></i> Est. <?php echo round(($nextRide['distance_km'] ?? 0) / 30 * 60); ?> min
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Countdown Timer -->
                            <div class="mt-4 bg-yellow-50 p-3 rounded-lg text-center">
                               
                            </div>
                            
                            <div class="border-t mt-5 pt-4 flex gap-3">
                                <button class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl font-medium flex-1 flex items-center justify-center gap-2 transition ride-action-btn" onclick="acceptRide('<?php echo $nextRide['id']; ?>')">
                                    <i class="fas fa-check"></i> Accept Ride
                                </button>
                                <button class="bg-red-600 hover:bg-red-700 text-white px-6 py-3 rounded-xl font-medium flex-1 flex items-center justify-center gap-2 transition ride-action-btn" onclick="declineRide('<?php echo $nextRide['id']; ?>')">
                                    <i class="fas fa-times"></i> Decline
                                </button>
                            </div>
                        </div>
                    </div>

                    <?php else: ?>
                    <!-- No Rides Available -->
                    <div class="desktop-card col-span-2 flex items-center justify-center" id="desktopNoRide">
                        <div class="text-center py-8">
                            <i class="fas fa-clock text-6xl text-gray-300 mb-4"></i>
                            <h3 class="text-2xl font-bold text-gray-500">No Active Rides</h3>
                            <p class="text-gray-400 mt-2">Go online to start receiving ride requests</p>
                            <?php if ($driver_status != 'online' && $verification_status == 'approved'): ?>
                            <button class="mt-4 bg-[#ff5e00] text-white px-8 py-3 rounded-xl font-medium hover:bg-[#e65500] transition transform hover:scale-105" onclick="toggleDriverStatus()">
                                <i class="fas fa-power-off mr-2"></i> Go Online Now
                            </button>
                            <?php elseif ($verification_status != 'approved'): ?>
                            <button class="mt-4 bg-gray-400 text-white px-8 py-3 rounded-xl font-medium cursor-not-allowed" disabled>
                                <i class="fas fa-exclamation-triangle mr-2"></i> Complete KYC First
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- QUICK ACTIONS -->
                    <div class="desktop-card col-span-2">
                        <div class="card-header flex justify-between items-center mb-4">
                            <h2 class="text-xl font-bold">Driver Quick Actions</h2>
                            <span class="text-[#ff5e00] text-sm font-semibold">🚀 Boost your earnings</span>
                        </div>
                        <div class="grid grid-cols-4 gap-4">
                            <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-4 rounded-xl transition flex flex-col items-center gap-2" onclick="toggleDriverStatus()" <?php echo ($verification_status != 'approved') ? 'disabled' : ''; ?>>
                                <div class="desktop-action-icon text-2xl text-[#ff5e00]"><i class="fas fa-power-off"></i></div>
                                <span class="text-sm font-medium">Go <?php echo $driver_status == 'online' ? 'Offline' : 'Online'; ?></span>
                            </button>
                            <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-4 rounded-xl transition flex flex-col items-center gap-2" onclick="withdrawFunds()">
                                <div class="desktop-action-icon text-2xl text-[#ff5e00]"><i class="fas fa-hand-holding-usd"></i></div>
                                <span class="text-sm font-medium">Withdraw</span>
                            </button>
                            <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-4 rounded-xl transition flex flex-col items-center gap-2" onclick="showFuelPrices()">
                                <div class="desktop-action-icon text-2xl text-[#ff5e00]"><i class="fas fa-gas-pump"></i></div>
                                <span class="text-sm font-medium">Fuel</span>
                            </button>
                            <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-4 rounded-xl transition flex flex-col items-center gap-2" onclick="showSupport()">
                                <div class="desktop-action-icon text-2xl text-[#ff5e00]"><i class="fas fa-headset"></i></div>
                                <span class="text-sm font-medium">Support</span>
                            </button>
                            <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-4 rounded-xl transition flex flex-col items-center gap-2" onclick="showVehicleCheck()">
                                <div class="desktop-action-icon text-2xl text-[#ff5e00]"><i class="fas fa-tools"></i></div>
                                <span class="text-sm font-medium">Vehicle</span>
                            </button>
                            <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-4 rounded-xl transition flex flex-col items-center gap-2" onclick="showSafetyTips()">
                                <div class="desktop-action-icon text-2xl text-[#ff5e00]"><i class="fas fa-shield-alt"></i></div>
                                <span class="text-sm font-medium">Safety</span>
                            </button>
                            <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-4 rounded-xl transition flex flex-col items-center gap-2" onclick="showDetailedStats()">
                                <div class="desktop-action-icon text-2xl text-[#ff5e00]"><i class="fas fa-chart-bar"></i></div>
                                <span class="text-sm font-medium">My stats</span>
                            </button>
                            <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-4 rounded-xl transition flex flex-col items-center gap-2" onclick="window.location.href='driver_settings.php'">
                                <div class="desktop-action-icon text-2xl text-[#ff5e00]"><i class="fas fa-cog"></i></div>
                                <span class="text-sm font-medium">Settings</span>
                            </button>
                        </div>
                    </div>

                    <!-- RECENT TRIPS -->
                    <div class="desktop-card col-span-2">
                        <div class="card-header flex justify-between items-center mb-4">
                            <h2 class="text-xl font-bold">📋 Recent Trips</h2>
                            <button class="see-all-btn bg-gray-100 px-4 py-2 rounded-full text-[#ff5e00] font-medium hover:bg-gray-200 transition" onclick="window.location.href='book_history.php'">
                                See all →
                            </button>
                        </div>
                        <div class="desktop-transactions">
                            <div class="transaction-list" id="desktopRecentRidesList">
                                <?php 
                                if ($recentRidesResult && $recentRidesResult->num_rows > 0):
                                    $recentRidesResult->data_seek(0);
                                    while ($ride = $recentRidesResult->fetch_assoc()): 
                                ?>
                                <div class="transaction-item flex justify-between items-center p-3 hover:bg-gray-50 rounded-lg cursor-pointer transition" onclick="viewRideDetails('<?php echo $ride['id']; ?>')">
                                    <div class="transaction-info flex items-center gap-3">
                                        <div class="transaction-icon w-10 h-10 rounded-full bg-green-100 flex items-center justify-center text-green-600">
                                            <i class="fas fa-check"></i>
                                        </div>
                                        <div class="transaction-details">
                                            <h4 class="font-medium"><?php echo htmlspecialchars(substr($ride['pickup_address'] ?? 'Ride', 0, 25)); ?> → <?php echo htmlspecialchars(substr($ride['destination_address'] ?? 'Destination', 0, 20)); ?></h4>
                                            <p class="text-xs text-gray-500"><?php echo date('h:i A', strtotime($ride['created_at'])); ?> • <?php echo htmlspecialchars($ride['client_name'] ?? 'Client'); ?></p>
                                            <p class="text-xs text-gray-400">Commission: ₦<?php echo number_format($ride['platform_commission'] ?? 0, 2); ?></p>
                                        </div>
                                    </div>
                                    <div class="transaction-amount positive font-bold text-green-600">+₦<?php echo number_format($ride['net_earnings'] ?? $ride['driver_payout'] ?? 0); ?></div>
                                </div>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-history text-4xl mb-2 opacity-50"></i>
                                    <p>No recent trips</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- BOTTOM BANNER -->
                <div class="ride-booking-desktop mt-6 flex justify-between items-center bg-gradient-to-r from-[#ff5e00] to-[#ff8c3a] p-6 rounded-2xl text-white">
                    <div class="ride-info">
                        <h2 class="text-2xl font-bold">Drive more, earn more 🚀</h2>
                        <p class="max-w-lg opacity-90 mt-1">Complete 10 rides this weekend → ₦8,000 bonus. <?php echo $todayRides; ?> rides done.</p>
                        <div class="flex gap-6 mt-3">
                            <div><span class="stat-value font-bold text-xl">₦<?php echo $todayRides > 0 ? number_format($todayEarnings / $todayRides, 0) : 0; ?></span><span class="ml-1 text-sm opacity-90">avg/ride</span></div>
                            <div><span class="stat-value font-bold text-xl"><?php echo $acceptance_rate; ?>%</span><span class="ml-1 text-sm opacity-90">acceptance</span></div>
                        </div>
                    </div>
                    <button class="book-ride-btn-desktop bg-white text-[#ff5e00] px-8 py-4 rounded-xl text-lg font-bold hover:shadow-lg transition transform hover:scale-105" onclick="toggleDriverStatus()" <?php echo ($verification_status != 'approved') ? 'disabled' : ''; ?>>
                        <i class="fas fa-car mr-2"></i> <?php echo $driver_status == 'online' ? 'Go Offline' : 'Find rides'; ?>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
    // Store driver status globally
    let driverStatus = '<?php echo $driver_status; ?>';
    let verificationStatus = '<?php echo $verification_status; ?>';
    
    // Toggle driver status function
    function toggleDriverStatus() {
        if (verificationStatus !== 'approved') {
            Swal.fire({
                title: 'Verification Required',
                text: 'Please complete KYC verification before going online',
                icon: 'warning',
                confirmButtonColor: '#ff5e00'
            });
            return;
        }
        
        const newStatus = driverStatus === 'online' ? 'offline' : 'online';
        const action = newStatus === 'online' ? 'Go Online' : 'Go Offline';
        
        Swal.fire({
            title: action,
            text: `Are you sure you want to go ${newStatus}?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: newStatus === 'online' ? '#10b981' : '#ef4444',
            confirmButtonText: `Yes, go ${newStatus}`,
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // Send request to update status
                fetch('SERVER/API/toggle_driver_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ status: newStatus })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        driverStatus = newStatus;
                        updateStatusUI();
                        Swal.fire({
                            title: 'Success',
                            text: `You are now ${newStatus}`,
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        });
                        window.location.reload(true);
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Failed to update status',
                            icon: 'error',
                            confirmButtonColor: '#ff5e00'
                        });
                        window.location.reload(true);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to update status',
                        icon: 'error',
                        confirmButtonColor: '#ff5e00'
                    });
                });
            }
        });
    }
    
    // Update UI based on driver status
    function updateStatusUI() {
        const isOnline = driverStatus === 'online';
        
        // Update mobile elements
        const mobileToggle = document.getElementById('mobileStatusToggle');
        const mobileStatusText = document.getElementById('mobileStatusText');
        const mobileStatusBtnText = document.getElementById('mobileStatusBtnText');
        
        if (mobileToggle) {
            mobileToggle.className = `status-toggle-btn mx-4 mt-4 ${driverStatus}`;
        }
        if (mobileStatusText) {
            mobileStatusText.className = isOnline ? 'text-green-600' : 'text-gray-600';
            mobileStatusText.innerHTML = isOnline ? '● Online' : '○ Offline';
        }
        if (mobileStatusBtnText) {
            mobileStatusBtnText.textContent = `Go ${isOnline ? 'Offline' : 'Online'}`;
        }
        
        // Update desktop elements
        const desktopToggle = document.getElementById('desktopStatusToggle');
        const desktopStatusLine = document.getElementById('desktopStatusLine');
        const desktopStatusDisplay = document.getElementById('desktopStatusDisplay');
        const desktopStatusBadge = document.getElementById('desktopStatusBadge');
        const desktopStatusBtnText = document.getElementById('desktopStatusBtnText');
        
        if (desktopToggle) {
            desktopToggle.className = `status-toggle-btn ${driverStatus}`;
        }
        if (desktopStatusLine) {
            if (isOnline) {
                desktopStatusLine.innerHTML = `
                    <span class="inline-block w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                    <span class="text-green-600 font-medium">Online</span>
                    • High demand zone near you.
                `;
            } else {
                desktopStatusLine.innerHTML = `
                    <span class="inline-block w-2 h-2 bg-gray-400 rounded-full"></span>
                    <span class="text-gray-600">Offline</span>
                    • High demand zone near you.
                `;
            }
        }
        if (desktopStatusDisplay) {
            desktopStatusDisplay.textContent = driverStatus.charAt(0).toUpperCase() + driverStatus.slice(1);
        }
        if (desktopStatusBadge) {
            desktopStatusBadge.className = `status-badge ${driverStatus}`;
            desktopStatusBadge.innerHTML = isOnline ? '● ONLINE' : '○ OFFLINE';
        }
        if (desktopStatusBtnText) {
            desktopStatusBtnText.textContent = `Go ${isOnline ? 'Offline' : 'Online'}`;
        }
        
        // Update all action buttons with status toggle
        document.querySelectorAll('.action-btn .fa-toggle-on, .action-btn .fa-toggle-off').forEach(icon => {
            icon.className = `fas fa-toggle-${isOnline ? 'on' : 'off'}`;
        });
        
        document.querySelectorAll('.action-btn span').forEach(span => {
            if (span.textContent.includes('Online') || span.textContent.includes('Offline')) {
                span.textContent = `Go ${isOnline ? 'Offline' : 'Online'}`;
            }
        });
    }
    
    // Accept ride function
    function acceptRide(rideId) {
        Swal.fire({
            title: 'Accept Ride?',
            text: 'Are you sure you want to accept this ride?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Yes, accept',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('SERVER/API/accept_ride.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ ride_id: rideId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Success',
                            text: 'Ride accepted successfully!',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Failed to accept ride',
                            icon: 'error',
                            confirmButtonColor: '#ff5e00'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to accept ride',
                        icon: 'error',
                        confirmButtonColor: '#ff5e00'
                    });
                });
            }
        });
    }
    
    // Decline ride function
    function declineRide(rideId) {
        Swal.fire({
            title: 'Decline Ride?',
            text: 'Are you sure you want to decline this ride?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, decline',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('SERVER/API/decline_ride.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ ride_id: rideId, auto_decline: false })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Declined',
                            text: data.message || 'Ride declined',
                            icon: 'info',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Failed to decline ride',
                            icon: 'error',
                            confirmButtonColor: '#ff5e00'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to decline ride',
                        icon: 'error',
                        confirmButtonColor: '#ff5e00'
                    });
                });
            }
        });
    }
    
    // Complete ride function
    function completeRide(rideId, payout) {
        Swal.fire({
            title: 'Complete Ride?',
            html: `
                <p>Have you completed this ride?</p>
                <p class="mt-2 font-bold text-green-600">You will earn: ₦${parseFloat(payout).toLocaleString()}</p>
                <div class="mt-4">
                    <label class="block text-sm text-gray-600 mb-2">Rate the client (optional)</label>
                    <div class="flex justify-center gap-2 text-2xl rating-stars">
                        <i class="far fa-star rating-star" data-rating="1"></i>
                        <i class="far fa-star rating-star" data-rating="2"></i>
                        <i class="far fa-star rating-star" data-rating="3"></i>
                        <i class="far fa-star rating-star" data-rating="4"></i>
                        <i class="far fa-star rating-star" data-rating="5"></i>
                    </div>
                </div>
                <textarea id="review-comment" class="swal2-textarea mt-4" placeholder="Leave a comment (optional)"></textarea>
            `,
            showCancelButton: true,
            confirmButtonColor: '#10b981',
            confirmButtonText: 'Yes, complete',
            cancelButtonText: 'Cancel',
            didOpen: () => {
                let selectedRating = 0;
                document.querySelectorAll('.rating-star').forEach(star => {
                    star.addEventListener('mouseover', function() {
                        const rating = parseInt(this.dataset.rating);
                        document.querySelectorAll('.rating-star').forEach((s, index) => {
                            if (index < rating) {
                                s.className = 'fas fa-star rating-star text-yellow-400';
                            } else {
                                s.className = 'far fa-star rating-star';
                            }
                        });
                    });
                    
                    star.addEventListener('click', function() {
                        selectedRating = parseInt(this.dataset.rating);
                        document.querySelectorAll('.rating-star').forEach((s, index) => {
                            if (index < selectedRating) {
                                s.className = 'fas fa-star rating-star text-yellow-400';
                            } else {
                                s.className = 'far fa-star rating-star';
                            }
                        });
                    });
                });
                
                // Reset stars when mouse leaves
                document.querySelector('.rating-stars').addEventListener('mouseleave', function() {
                    document.querySelectorAll('.rating-star').forEach((s, index) => {
                        if (index < selectedRating) {
                            s.className = 'fas fa-star rating-star text-yellow-400';
                        } else {
                            s.className = 'far fa-star rating-star';
                        }
                    });
                });
            }
        }).then((result) => {
            if (result.isConfirmed) {
                const rating = document.querySelectorAll('.rating-star.fas').length;
                const comment = document.getElementById('review-comment')?.value || '';
                
                fetch('SERVER/API/complete_ride.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        ride_id: rideId, 
                        rating: rating > 0 ? rating : null,
                        comment: comment 
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Ride Completed!',
                            html: `
                                <p>Ride completed successfully</p>
                                <p class="mt-2 font-bold text-green-600">Earned: ₦${parseFloat(data.earnings || payout).toLocaleString()}</p>
                            `,
                            icon: 'success',
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Failed to complete ride',
                            icon: 'error',
                            confirmButtonColor: '#ff5e00'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to complete ride',
                        icon: 'error',
                        confirmButtonColor: '#ff5e00'
                    });
                });
            }
        });
    }
    
    // Cancel active ride function
    function cancelActiveRide(rideId) {
        Swal.fire({
            title: 'Cancel Ride?',
            text: 'Are you sure you want to cancel this ride? This may affect your acceptance rate.',
            icon: 'warning',
            input: 'select',
            inputOptions: {
                'emergency': 'Emergency',
                'vehicle_issue': 'Vehicle Issue',
                'traffic': 'Heavy Traffic',
                'client_no_show': 'Client Not at Pickup',
                'other': 'Other Reason'
            },
            inputPlaceholder: 'Select a reason',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Yes, cancel ride',
            cancelButtonText: 'No, keep ride',
            preConfirm: (reason) => {
                if (!reason) {
                    Swal.showValidationMessage('Please select a reason');
                    return false;
                }
                return reason;
            }
        }).then((result) => {
            if (result.isConfirmed) {
                fetch('SERVER/API/cancel_ride.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ 
                        ride_id: rideId,
                        reason: result.value
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            title: 'Cancelled',
                            text: 'Ride cancelled successfully',
                            icon: 'success',
                            timer: 1500,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Failed to cancel ride',
                            icon: 'error',
                            confirmButtonColor: '#ff5e00'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Error',
                        text: 'Failed to cancel ride',
                        icon: 'error',
                        confirmButtonColor: '#ff5e00'
                    });
                });
            }
        });
    }
    
    // Withdraw funds function
    function withdrawFunds() {
        const availableBalance = <?php echo $availableBalance; ?>;
        
        if (availableBalance < 1000) {
            Swal.fire({
                title: 'Insufficient Balance',
                text: 'Minimum withdrawal amount is ₦1,000',
                icon: 'warning',
                confirmButtonColor: '#ff5e00'
            });
            return;
        }
        
        Swal.fire({
            title: 'Withdraw Funds',
            html: `
                <p class="mb-4">Available balance: <strong>₦${availableBalance.toLocaleString()}</strong></p>
                <input type="number" id="withdraw-amount" class="swal2-input" placeholder="Enter amount" min="1000" max="${availableBalance}" step="100">
                <select id="bank-name" class="swal2-input">
                    <option value="">Select Bank</option>
                    <option value="Access Bank">Access Bank</option>
                    <option value="GTBank">GTBank</option>
                    <option value="First Bank">First Bank</option>
                    <option value="UBA">UBA</option>
                    <option value="Zenith">Zenith Bank</option>
                </select>
                <input type="text" id="account-number" class="swal2-input" placeholder="Account Number" maxlength="10">
                <input type="text" id="account-name" class="swal2-input" placeholder="Account Name">
            `,
            showCancelButton: true,
            confirmButtonText: 'Withdraw',
            confirmButtonColor: '#ff5e00',
            preConfirm: () => {
                const amount = parseFloat(document.getElementById('withdraw-amount').value);
                const bank = document.getElementById('bank-name').value;
                const account = document.getElementById('account-number').value;
                const name = document.getElementById('account-name').value;
                
                if (!amount || amount < 1000) {
                    Swal.showValidationMessage('Minimum withdrawal is ₦1,000');
                    return false;
                }
                if (amount > availableBalance) {
                    Swal.showValidationMessage('Insufficient balance');
                    return false;
                }
                if (!bank) {
                    Swal.showValidationMessage('Please select a bank');
                    return false;
                }
                if (!account || account.length !== 10 || !/^\d+$/.test(account)) {
                    Swal.showValidationMessage('Please enter a valid 10-digit account number');
                    return false;
                }
                if (!name) {
                    Swal.showValidationMessage('Please enter account name');
                    return false;
                }
                return { amount, bank, account, name };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                // Here you would typically send this data to your server
                Swal.fire({
                    title: 'Withdrawal Request Submitted',
                    html: `
                        <p>Amount: <strong>₦${result.value.amount.toLocaleString()}</strong></p>
                        <p>Bank: ${result.value.bank}</p>
                        <p>Account: ${result.value.account} (${result.value.name})</p>
                        <p class="mt-4 text-sm text-gray-500">Your withdrawal will be processed within 24-48 hours.</p>
                    `,
                    icon: 'success',
                    confirmButtonColor: '#ff5e00'
                });
            }
        });
    }
    
    // Check notifications function
    function checkNotifications() {
        Swal.fire({
            title: 'Notifications',
            html: '<p>🔔 No new notifications</p>',
            icon: 'info',
            confirmButtonColor: '#ff5e00'
        });
    }
    
    // Show detailed stats function
    function showDetailedStats() {
        const stats = <?php echo json_encode($stats); ?>;
        const avgRating = <?php echo $avg_rating; ?>;
        const acceptanceRate = <?php echo $acceptance_rate; ?>;
        
        Swal.fire({
            title: 'Detailed Statistics',
            html: `
                <div class="stats-grid">
                    <div class="stat-item">
                        <div class="stat-label">Total Rides</div>
                        <div class="stat-value">${stats.total_rides}</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Completed</div>
                        <div class="stat-value text-green-600">${stats.completed_rides}</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Cancelled</div>
                        <div class="stat-value text-red-600">${stats.cancelled_rides}</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Acceptance Rate</div>
                        <div class="stat-value">${acceptanceRate}%</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Total Fare</div>
                        <div class="stat-value">₦${stats.total_fare_amount.toLocaleString()}</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-label">Commission (20%)</div>
                        <div class="stat-value text-red-600">-₦${(stats.total_fare_amount * 0.2).toLocaleString()}</div>
                    </div>
                    <div class="stat-item col-span-2">
                        <div class="stat-label">Net Earnings</div>
                        <div class="stat-value text-green-600">₦${(stats.total_fare_amount * 0.8).toLocaleString()}</div>
                    </div>
                    <div class="stat-item col-span-2">
                        <div class="stat-label">Average Rating</div>
                        <div class="stat-value flex items-center justify-center gap-2">
                            <span>${avgRating}</span>
                            <span class="text-yellow-400">
                                ${'★'.repeat(Math.floor(avgRating))}${avgRating % 1 >= 0.5 ? '½' : ''}${'☆'.repeat(5 - Math.ceil(avgRating))}
                            </span>
                        </div>
                    </div>
                </div>
            `,
            confirmButtonColor: '#ff5e00',
            width: '600px'
        });
    }
    
    // Call client function
    function callClient(phone) {
        if (phone) {
            window.location.href = `tel:${phone}`;
        } else {
            Swal.fire({
                title: 'No Phone Number',
                text: 'Client phone number is not available',
                icon: 'info',
                confirmButtonColor: '#ff5e00'
            });
        }
    }
    
    // Navigate to location
    function navigateTo(lat, lng) {
        if (lat && lng) {
            window.open(`https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`, '_blank');
        } else {
            Swal.fire({
                title: 'Location Unavailable',
                text: 'Pickup location coordinates are not available',
                icon: 'info',
                confirmButtonColor: '#ff5e00'
            });
        }
    }
    
    // View ride details
    function viewRideDetails(rideId) {
        window.location.href = `generate_receipt.php?ride_id=${rideId}`;
    }
    
    // Show support
    function showSupport() {
        Swal.fire({
            title: 'Driver Support',
            html: `
                <p class="mb-2"><i class="fas fa-phone text-[#ff5e00] mr-2"></i> +234 800 123 4567</p>
                <p><i class="fas fa-envelope text-[#ff5e00] mr-2"></i> drivers@speedly.com</p>
                <p class="mt-4 text-sm text-gray-500">Available 24/7 for driver assistance</p>
            `,
            icon: 'info',
            confirmButtonColor: '#ff5e00'
        });
    }
    
    // Show fuel prices
    function showFuelPrices() {
        Swal.fire({
            title: 'Fuel Prices',
            html: `
                <p class="mb-2">PMS (Petrol): ₦650/L</p>
                <p class="mb-2">Diesel: ₦850/L</p>
                <p>Nearby stations offering discounts for Speedly drivers</p>
            `,
            icon: 'info',
            confirmButtonColor: '#ff5e00'
        });
    }
    
    // Show safety tips
    function showSafetyTips() {
        Swal.fire({
            title: 'Safety Tips',
            html: `
                <ul class="text-left list-disc pl-5">
                    <li class="mb-2">Always verify passenger identity</li>
                    <li class="mb-2">Keep your vehicle locked</li>
                    <li class="mb-2">Share trip status with emergency contacts</li>
                    <li class="mb-2">Use in-app emergency button if needed</li>
                    <li>Follow traffic rules at all times</li>
                </ul>
            `,
            icon: 'info',
            confirmButtonColor: '#ff5e00'
        });
    }
    
    // Show vehicle check
    function showVehicleCheck() {
        Swal.fire({
            title: 'Vehicle Checklist',
            html: `
                <ul class="text-left list-disc pl-5">
                    <li class="mb-2">✓ Tires pressure checked</li>
                    <li class="mb-2">✓ Fuel level sufficient</li>
                    <li class="mb-2">✓ Lights working</li>
                    <li class="mb-2">✓ Insurance valid</li>
                    <li>✓ Vehicle clean and ready</li>
                </ul>
                <p class="mt-4 text-green-600">Your vehicle is ready for rides!</p>
            `,
            icon: 'success',
            confirmButtonColor: '#ff5e00'
        });
    }
    
    // Show coming soon
    function showComingSoon(feature) {
        Swal.fire({
            title: feature,
            text: `${feature} feature coming soon!`,
            icon: 'info',
            confirmButtonColor: '#ff5e00'
        });
    }
    
    // Countdown timer for pending rides
    function startCountdown(element, createdTime) {
        const timerElement = document.getElementById(element);
        if (!timerElement) return;
        
        const created = parseInt(timerElement.dataset.created) * 1000;
        const expiryTime = created + (30 * 1000); // 30 seconds
        
        function updateTimer() {
            const now = new Date().getTime();
            const timeLeft = expiryTime - now;
            
            if (timeLeft <= 0) {
                timerElement.textContent = 'Expired';
                timerElement.classList.add('timer-critical');
                // Auto-decline after timeout
                const rideId = timerElement.closest('[data-ride-id]')?.dataset.rideId;
                if (rideId) {
                    declineRide(rideId);
                }
                return;
            }
            
            const seconds = Math.floor((timeLeft % (1000 * 60)) / 1000);
            timerElement.textContent = seconds + 's';
            
            if (seconds < 10) {
                timerElement.classList.add('timer-critical');
            }
            
            setTimeout(updateTimer, 1000);
        }
        
        updateTimer();
    }
    
    // Initialize on page load
    document.addEventListener('DOMContentLoaded', function() {
        // Start countdown for pending rides
        if (document.getElementById('countdownTimer')) {
            startCountdown('countdownTimer');
        }
        if (document.getElementById('desktopCountdownTimer')) {
            startCountdown('desktopCountdownTimer');
        }
        
        // Set up periodic refresh for ride requests (every 30 seconds)
        setInterval(function() {
            if (driverStatus === 'online') {
                // Silently refresh ride requests without reloading page
                fetch('SERVER/API/get_pending_rides.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.has_new_rides) {
                            // Show notification for new rides
                            Swal.fire({
                                title: 'New Ride Available',
                                text: 'A new ride request has arrived',
                                icon: 'info',
                                timer: 3000,
                                showConfirmButton: false
                            }).then(() => {
                                location.reload();
                            });
                        }
                    })
                    .catch(error => console.error('Error checking for new rides:', error));
            }
        }, 30000);
    });
    </script>
</body>
</html>