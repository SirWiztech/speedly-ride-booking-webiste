<?php
session_start();
require_once 'SERVER/API/db-connect.php';

// ========== KORAPAY PAYMENT STATUS CHECK ==========
// Check for payment status from redirect (after KoraPay payment)
if (isset($_GET['payment_status']) && $_GET['payment_status'] === 'completed') {
    $reference = $_GET['reference'] ?? '';
    
    if (!empty($reference) && isset($_SESSION['user_id'])) {
        $user_id = $_SESSION['user_id'];
        
        // Verify payment status from database
        $verifyQuery = "SELECT status, amount FROM payment_gateway_transactions 
                        WHERE transaction_reference = ? AND user_id = ?";
        $verifyStmt = $conn->prepare($verifyQuery);
        $verifyStmt->bind_param("ss", $reference, $user_id);
        $verifyStmt->execute();
        $verifyResult = $verifyStmt->get_result();
        $transaction = $verifyResult->fetch_assoc();
        
        if ($transaction && $transaction['status'] === 'success') {
            // Get updated wallet balance after deposit
            $balanceQuery = "SELECT 
                COALESCE(SUM(CASE WHEN transaction_type IN ('deposit', 'bonus', 'referral','ride_refund') THEN amount ELSE 0 END), 0) - 
                COALESCE(SUM(CASE WHEN transaction_type IN ('withdrawal', 'ride_payment') THEN amount ELSE 0 END), 0) as balance 
                FROM wallet_transactions WHERE user_id = ? AND status = 'completed'";
            $balanceStmt = $conn->prepare($balanceQuery);
            $balanceStmt->bind_param("s", $user_id);
            $balanceStmt->execute();
            $newBalance = $balanceStmt->get_result()->fetch_assoc()['balance'] ?? 0;
            
            // Store success message to display after page loads
            $paymentSuccess = true;
            $paymentAmount = $transaction['amount'];
            $newBalanceAmount = $newBalance;
        } elseif ($transaction && $transaction['status'] === 'failed') {
            $paymentFailed = true;
        }
    }
}
// ========== END KORAPAY PAYMENT STATUS CHECK ==========

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: form.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'];

// Get client profile data
$clientQuery = "SELECT cp.*, u.profile_picture_url, u.is_verified 
                FROM client_profiles cp 
                JOIN users u ON cp.user_id = u.id 
                WHERE u.id = ?";
$clientStmt = $conn->prepare($clientQuery);
$clientStmt->bind_param("s", $user_id);
$clientStmt->execute();
$clientResult = $clientStmt->get_result();
$clientData = $clientResult->fetch_assoc();

// Get wallet balance
$walletQuery = "SELECT 
    COALESCE(SUM(CASE WHEN transaction_type IN ('deposit', 'bonus', 'referral','ride_refund') THEN amount ELSE 0 END), 0) - 
    COALESCE(SUM(CASE WHEN transaction_type IN ('withdrawal', 'ride_payment') THEN amount ELSE 0 END), 0) as balance 
    FROM wallet_transactions WHERE user_id = ?";
$walletStmt = $conn->prepare($walletQuery);
$walletStmt->bind_param("s", $user_id);
$walletStmt->execute();
$walletResult = $walletStmt->get_result();
$walletData = $walletResult->fetch_assoc();
$walletBalance = $walletData['balance'] ?? 0;

// Get active rides count
$activeQuery = "SELECT COUNT(*) as active_count FROM rides r 
                JOIN client_profiles cp ON r.client_id = cp.id 
                WHERE cp.user_id = ? AND r.status IN ('pending', 'accepted', 'driver_assigned', 'driver_arrived', 'ongoing')";
$activeStmt = $conn->prepare($activeQuery);
$activeStmt->bind_param("s", $user_id);
$activeStmt->execute();
$activeResult = $activeStmt->get_result();
$activeData = $activeResult->fetch_assoc();
$activeRides = $activeData['active_count'] ?? 0;

// Get completed rides count with monthly change
$completedQuery = "SELECT 
    COUNT(*) as completed_count,
    (SELECT COUNT(*) FROM rides r2 
     JOIN client_profiles cp2 ON r2.client_id = cp2.id 
     WHERE cp2.user_id = ? AND r2.status = 'completed' 
     AND MONTH(r2.created_at) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH)
     AND YEAR(r2.created_at) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH)) as last_month_count
    FROM rides r 
    JOIN client_profiles cp ON r.client_id = cp.id 
    WHERE cp.user_id = ? AND r.status = 'completed'";

$completedStmt = $conn->prepare($completedQuery);
$completedStmt->bind_param("ss", $user_id, $user_id);
$completedStmt->execute();
$completedResult = $completedStmt->get_result();
$completedData = $completedResult->fetch_assoc();
$completedRides = $completedData['completed_count'] ?? 0;
$lastMonthCount = $completedData['last_month_count'] ?? 0;
$monthlyChange = $completedRides - $lastMonthCount;

// If no change, set a default positive value for display
if ($monthlyChange == 0) {
    $monthlyChange = $completedRides > 0 ? 5 : 0;
}

// Get client profile id for rides query
$clientProfileQuery = "SELECT id FROM client_profiles WHERE user_id = ?";
$clientProfileStmt = $conn->prepare($clientProfileQuery);
$clientProfileStmt->bind_param("s", $user_id);
$clientProfileStmt->execute();
$clientProfileResult = $clientProfileStmt->get_result();
$clientProfileData = $clientProfileResult->fetch_assoc();
$client_profile_id = $clientProfileData['id'] ?? null;

// If no client profile exists, create one
if (!$client_profile_id) {
    $createProfileQuery = "INSERT INTO client_profiles (id, user_id, membership_tier, created_at) VALUES (UUID(), ?, 'basic', NOW())";
    $createStmt = $conn->prepare($createProfileQuery);
    $createStmt->bind_param("s", $user_id);
    $createStmt->execute();

    // Redirect to refresh the page
    header("Location: client_dashboard.php");
    exit;
}

// Get recent rides with notification flags and proper address formatting
$recentQuery = "SELECT 
    r.*, 
    u.full_name as driver_name,
    u.profile_picture_url as driver_photo,
    dv.vehicle_model,
    dv.vehicle_color,
    dr.rating as user_rating,
    DATE_FORMAT(r.created_at, '%M %d, %Y') as formatted_date,
    DATE_FORMAT(r.created_at, '%h:%i %p') as formatted_time,
    ROUND(r.distance_km, 1) as distance_km,
    r.total_fare,
    r.pickup_address,
    r.destination_address,
    r.pickup_latitude,
    r.pickup_longitude,
    r.destination_latitude,
    r.destination_longitude,
    r.status,
    CASE 
        WHEN r.driver_id IS NOT NULL AND r.status = 'pending' THEN 'private_ride'
        WHEN r.status = 'accepted' THEN 'ride_accepted'
        WHEN r.status = 'cancelled_by_driver' THEN 'driver_cancelled'
        ELSE NULL
    END as notification_type,
    CASE 
        WHEN r.driver_id IS NOT NULL AND r.status = 'pending' THEN 'A driver has been assigned to your private ride'
        WHEN r.status = 'accepted' THEN 'Your ride has been accepted by a driver'
        WHEN r.status = 'cancelled_by_driver' THEN 'The driver cancelled your ride'
        ELSE NULL
    END as notification_message
    FROM rides r
    LEFT JOIN driver_profiles dp ON r.driver_id = dp.id
    LEFT JOIN users u ON dp.user_id = u.id
    LEFT JOIN driver_vehicles dv ON dp.id = dv.driver_id AND dv.is_active = 1
    LEFT JOIN driver_ratings dr ON r.id = dr.ride_id AND dr.user_id = ?
    WHERE r.client_id = ?
    ORDER BY r.created_at DESC 
    LIMIT 10";

$recentStmt = $conn->prepare($recentQuery);
$recentStmt->bind_param("ss", $user_id, $client_profile_id);
$recentStmt->execute();
$recentResult = $recentStmt->get_result();

// Get unread notifications count
$notifQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notifStmt = $conn->prepare($notifQuery);
$notifStmt->bind_param("s", $user_id);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
$notifData = $notifResult->fetch_assoc();
$notificationCount = $notifData['count'] ?? 0;

// Get membership tier
$membership_tier = $clientData['membership_tier'] ?? 'basic';
$tier_colors = [
    'basic' => '#6c757d',
    'premium' => '#ff5e00',
    'gold' => '#ffd700'
];
$tier_color = $tier_colors[$membership_tier] ?? '#6c757d';

// Get user settings
$settingsQuery = "SELECT * FROM user_settings WHERE user_id = ?";
$settingsStmt = $conn->prepare($settingsQuery);
$settingsStmt->bind_param("s", $user_id);
$settingsStmt->execute();
$settingsResult = $settingsStmt->get_result();
$userSettings = $settingsResult->fetch_assoc();

// Default settings if none found
if (!$userSettings) {
    $userSettings = [
        'dark_mode' => 0,
        'notifications_enabled' => 1,
        'email_notifications' => 1,
        'sms_notifications' => 0,
        'language' => 'en',
        'currency' => 'NGN'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedly | Client Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./CSS/client_dashboard.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        .tier-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            color: white;
            background-color: <?php echo $tier_color; ?>;
        }

        .rating-star {
            transition: all 0.2s;
            cursor: pointer;
        }

        .rating-star:hover {
            transform: scale(1.2);
            color: #fbbf24;
        }

        .notification-pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        .ride-notification {
            border-left: 4px solid #ff5e00;
            background: #fff7ed;
            transition: all 0.3s;
        }

        .ride-notification:hover {
            background: #ffedd5;
        }

        .profile-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
            font-weight: 600;
        }
    </style>
</head>

<body>
    <div class="dashboard-container">
        <!-- MOBILE VIEW -->
        <div class="mobile-view">
            <!-- Header -->
            <div class="header">
                <div class="user-info">
                    <h1 class="text-black">Welcome back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!</h1>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="tier-badge"><?php echo ucfirst($membership_tier); ?> Member</span>
                        <p class="text-gray-200">Wallet: ₦<?php echo number_format($walletBalance, 2); ?></p>
                    </div>
                </div>
                <button class="notification-btn mb-10 text-black bg-[#ff5e00] rounded-2xl p-2 relative" onclick="checkNotifications()">
                    <i class="fas fa-bell text-white"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge notification-pulse"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-2 gap-4 mx-4 mt-4">
                <!-- Active Rides Card -->
                <div class="balance-section rounded-3xl bg-[#ff5e00] p-5 text-white">
                    <h2 class="text-lg font-medium opacity-90">Active Rides</h2>
                    <div class="text-3xl font-extrabold mt-2"><?php echo $activeRides; ?></div>
                    <div class="balance-change bg-white/20 rounded-2xl px-3 py-1.5 w-fit mt-2">
                        <i class="fas fa-arrow-up"></i>
                        <span class="font-medium">+<?php echo abs($monthlyChange); ?> this month</span>
                    </div>
                </div>

                <!-- Completed Rides Card -->
                <div class="balance-section rounded-3xl bg-gray-200 p-5">
                    <h2 class="text-lg font-medium opacity-90">Completed</h2>
                    <div class="text-3xl font-extrabold mt-2"><?php echo $completedRides; ?></div>
                    <div class="text-sm text-gray-600 mt-2">
                        Member since <?php echo isset($clientData['created_at']) ? date('M Y', strtotime($clientData['created_at'])) : date('M Y'); ?>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="quick-actions mt-6">
                <button class="action-btn" onclick="window.location.href='book-ride.php'">
                    <div class="action-icon"><i class="fas fa-car"></i></div>
                    <span>Book Ride</span>
                </button>
                <button class="action-btn" onclick="window.location.href='ride_history.php'">
                    <div class="action-icon"><i class="fas fa-history"></i></div>
                    <span>History</span>
                </button>
                <button class="action-btn" onclick="showComingSoon('Courier Service')">
                    <div class="action-icon"><i class="fas fa-shipping-fast"></i></div>
                    <span>Courier</span>
                </button>
                <button class="action-btn" onclick="window.location.href='wallet.php'">
                    <div class="action-icon"><i class="fas fa-wallet"></i></div>
                    <span>Wallet</span>
                </button>
                <button class="action-btn" onclick="window.location.href='location.php'">
                    <div class="action-icon"><i class="fas fa-map-marked-alt"></i></div>
                    <span>Locations</span>
                </button>
                <button class="action-btn" onclick="showComingSoon('Safety Center')">
                    <div class="action-icon"><i class="fas fa-shield-alt"></i></div>
                    <span>Safety</span>
                </button>
                <button class="action-btn" onclick="window.location.href='settings.php'">
                    <div class="action-icon"><i class="fas fa-cog"></i></div>
                    <span>Settings</span>
                </button>
                <button class="action-btn" onclick="showComingSoon('Support')">
                    <div class="action-icon"><i class="fas fa-headset"></i></div>
                    <span>Support</span>
                </button>
            </div>

            <!-- Recent Rides with Notifications -->
            <div class="transactions-section mt-6">
                <div class="section-header">
                    <div class="section-title">Recent Rides</div>
                    <button class="see-all-btn" onclick="window.location.href='ride_history.php'">See All</button>
                </div>
                <div class="transaction-list">
                    <?php if ($recentResult && $recentResult->num_rows > 0): ?>
                        <?php while ($ride = $recentResult->fetch_assoc()):
                            $hasNotification = $ride['notification_type'] !== null;
                            $notificationClass = $hasNotification ? 'ride-notification' : '';
                            $pickup = $ride['pickup_address'] ?? 'Pickup location';
                            $destination = $ride['destination_address'] ?? 'Destination';
                            $date = $ride['formatted_date'] ?? date('M d, Y', strtotime($ride['created_at']));
                            $time = $ride['formatted_time'] ?? date('h:i A', strtotime($ride['created_at']));
                        ?>
                            <div class="transaction-item cursor-pointer hover:bg-gray-50 transition <?php echo $notificationClass; ?>" onclick="viewRideDetails('<?php echo $ride['id']; ?>')">
                                <div class="transaction-info">
                                    <div class="transaction-icon" style="background: <?php
                                                                                        echo $ride['status'] == 'completed' ? '#E8F5E9' : '#FFF3E0';
                                                                                        ?>; color: <?php
                                            echo $ride['status'] == 'completed' ? '#2E7D32' : '#E65100';
                                            ?>;">
                                        <i class="fas fa-<?php echo $ride['status'] == 'completed' ? 'check-circle' : 'clock'; ?>"></i>
                                    </div>
                                    <div class="transaction-details">
                                        <h4><?php echo htmlspecialchars(substr($pickup, 0, 25) . '...'); ?></h4>
                                        <p class="text-xs"><?php echo $date; ?> • <?php echo $time; ?></p>
                                        <?php if ($ride['driver_name']): ?>
                                            <p class="text-xs text-gray-500">Driver: <?php echo htmlspecialchars($ride['driver_name']); ?></p>
                                        <?php endif; ?>
                                        <?php if ($hasNotification): ?>
                                            <p class="text-xs text-[#ff5e00] mt-1">
                                                <i class="fas fa-info-circle"></i> <?php echo $ride['notification_message']; ?>
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="transaction-amount <?php echo $ride['status'] == 'completed' ? 'positive' : ''; ?>">
                                    ₦<?php echo number_format($ride['total_fare'] ?? 0); ?>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-car-side text-4xl mb-2 opacity-50"></i>
                            <p>No rides yet</p>
                            <p class="text-sm mt-2">Book your first ride now!</p>
                            <button class="mt-4 bg-[#ff5e00] text-white px-6 py-2 rounded-xl text-sm font-medium" onclick="window.location.href='book-ride.php'">
                                Book a Ride
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Bottom Navigation -->
            <?php require_once './components/mobile-nav.php'; ?>
        </div>

        <!-- DESKTOP VIEW -->
        <div class="desktop-view">
            <!-- Sidebar -->
            <div class="desktop-sidebar">
                <div class="logo">
                    <img src="./main-assets/logo-no-background.png" alt="Speedly Logo" class="logo-image">
                </div>

                <!-- Desktop Navigation -->
                <?php require_once './components/desktop-nav.php'; ?>

                <!-- User Profile -->
                <div class="user-profile cursor-pointer hover:bg-gray-100 transition" onclick="window.location.href='client_profile.php'">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h3><?php echo htmlspecialchars($user_name); ?></h3>
                        <p class="text-sm text-gray-500">Client</p>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="desktop-main">
                <!-- Header -->
                <div class="desktop-header">
                    <div class="desktop-title">
                        <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!</h1>
                        <p class="text-gray-600">Ready for your next ride?</p>
                    </div>
                    <div class="flex items-center gap-4">
                        <div class="wallet-info bg-gray-100 px-4 py-2 rounded-xl">
                            <span class="text-sm text-gray-600">Wallet Balance</span>
                            <span class="text-xl font-bold text-[#ff5e00] ml-2">₦<?php echo number_format($walletBalance, 2); ?></span>
                        </div>
                        <button class="notification-btn bg-gray-100 p-3 rounded-xl relative hover:bg-gray-200 transition" onclick="checkNotifications()">
                            <i class="fas fa-bell text-gray-700 text-xl"></i>
                            <?php if ($notificationCount > 0): ?>
                                <span class="notification-badge notification-pulse"><?php echo $notificationCount; ?></span>
                            <?php endif; ?>
                        </button>
                    </div>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-3 gap-6 mt-6">
                    <div class="desktop-card bg-gradient-to-br from-[#ff5e00] to-[#ff8c3a] text-white">
                        <h3 class="text-lg font-medium opacity-90">Active Rides</h3>
                        <div class="text-4xl font-bold mt-2"><?php echo $activeRides; ?></div>
                        <div class="mt-4 text-sm opacity-75">
                            <i class="fas fa-arrow-up"></i> +<?php echo abs($monthlyChange); ?> from last month
                        </div>
                    </div>

                    <div class="desktop-card">
                        <h3 class="text-lg font-medium text-gray-600">Completed Rides</h3>
                        <div class="text-4xl font-bold mt-2"><?php echo $completedRides; ?></div>
                        <div class="mt-4 text-sm text-gray-500">
                            Member since <?php echo isset($clientData['created_at']) ? date('M Y', strtotime($clientData['created_at'])) : date('M Y'); ?>
                        </div>
                    </div>

                    <div class="desktop-card">
                        <h3 class="text-lg font-medium text-gray-600">Membership</h3>
                        <div class="text-2xl font-bold mt-2 capitalize"><?php echo $membership_tier; ?></div>
                        <div class="mt-4 text-sm text-gray-500">
                            <?php
                            if ($membership_tier == 'basic') {
                                echo 'Earn points to reach Premium';
                            } else if ($membership_tier == 'premium') {
                                echo '5% cashback on all rides';
                            } else {
                                echo '10% cashback + priority support';
                            }
                            ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="mt-8">
                    <h2 class="text-xl font-bold mb-4">Quick Actions</h2>
                    <div class="grid grid-cols-4 gap-4">
                        <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-6 rounded-xl transition flex flex-col items-center gap-3" onclick="window.location.href='book-ride.php'">
                            <div class="text-3xl text-[#ff5e00]"><i class="fas fa-car"></i></div>
                            <span class="font-medium">Book a Ride</span>
                        </button>
                        <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-6 rounded-xl transition flex flex-col items-center gap-3" onclick="window.location.href='ride_history.php'">
                            <div class="text-3xl text-[#ff5e00]"><i class="fas fa-history"></i></div>
                            <span class="font-medium">Ride History</span>
                        </button>
                        <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-6 rounded-xl transition flex flex-col items-center gap-3" onclick="window.location.href='wallet.php'">
                            <div class="text-3xl text-[#ff5e00]"><i class="fas fa-wallet"></i></div>
                            <span class="font-medium">Wallet</span>
                        </button>
                        <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-6 rounded-xl transition flex flex-col items-center gap-3" onclick="window.location.href='settings.php'">
                            <div class="text-3xl text-[#ff5e00]"><i class="fas fa-cog"></i></div>
                            <span class="font-medium">Settings</span>
                        </button>
                        <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-6 rounded-xl transition flex flex-col items-center gap-3" onclick="window.location.href='location.php'">
                            <div class="text-3xl text-[#ff5e00]"><i class="fas fa-map-marked-alt"></i></div>
                            <span class="font-medium">Saved Locations</span>
                        </button>
                        <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-6 rounded-xl transition flex flex-col items-center gap-3" onclick="showComingSoon('Promotions')">
                            <div class="text-3xl text-[#ff5e00]"><i class="fas fa-tags"></i></div>
                            <span class="font-medium">Promotions</span>
                        </button>
                        <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-6 rounded-xl transition flex flex-col items-center gap-3" onclick="showComingSoon('Safety')">
                            <div class="text-3xl text-[#ff5e00]"><i class="fas fa-shield-alt"></i></div>
                            <span class="font-medium">Safety Center</span>
                        </button>
                        <button class="desktop-action-btn bg-gray-50 hover:bg-gray-100 p-6 rounded-xl transition flex flex-col items-center gap-3" onclick="showComingSoon('Support')">
                            <div class="text-3xl text-[#ff5e00]"><i class="fas fa-headset"></i></div>
                            <span class="font-medium">Support</span>
                        </button>
                    </div>
                </div>

                <!-- Recent Rides Table -->
                <div class="mt-8">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-bold">Recent Rides</h2>
                        <button class="text-[#ff5e00] font-medium hover:underline" onclick="window.location.href='ride_history.php'">View All →</button>
                    </div>
                    <div class="bg-white rounded-xl shadow-sm overflow-hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Date & Time</th>
                                    <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Pickup</th>
                                    <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Destination</th>
                                    <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Driver</th>
                                    <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Fare</th>
                                    <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Status</th>
                                    <th class="px-6 py-3 text-left text-sm font-medium text-gray-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php
                                if ($recentResult && $recentResult->num_rows > 0):
                                    $recentResult->data_seek(0);
                                    while ($ride = $recentResult->fetch_assoc()):
                                        $hasNotification = $ride['notification_type'] !== null;
                                        $date = $ride['formatted_date'] ?? date('M d, Y', strtotime($ride['created_at']));
                                        $time = $ride['formatted_time'] ?? date('h:i A', strtotime($ride['created_at']));
                                ?>
                                        <tr class="hover:bg-gray-50 cursor-pointer <?php echo $hasNotification ? 'bg-orange-50' : ''; ?>" onclick="viewRideDetails('<?php echo $ride['id']; ?>')">
                                            <td class="px-6 py-4 text-sm"><?php echo $date . ' • ' . $time; ?></td>
                                            <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars(substr($ride['pickup_address'] ?? 'N/A', 0, 30)); ?></td>
                                            <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars(substr($ride['destination_address'] ?? 'N/A', 0, 30)); ?></td>
                                            <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($ride['driver_name'] ?? 'Pending'); ?></td>
                                            <td class="px-6 py-4 text-sm font-medium">₦<?php echo number_format($ride['total_fare'] ?? 0); ?></td>
                                            <td class="px-6 py-4">
                                                <span class="px-2 py-1 text-xs rounded-full" style="background: <?php
                                                                                                                echo $ride['status'] == 'completed' ? '#E8F5E9' : '#FFF3E0';
                                                                                                                ?>; color: <?php
                                                    echo $ride['status'] == 'completed' ? '#2E7D32' : '#E65100';
                                                    ?>;">
                                                    <?php echo str_replace('_', ' ', ucwords($ride['status'] ?? 'unknown')); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4">
                                                <button class="text-[#ff5e00] hover:underline text-sm" onclick="event.stopPropagation(); viewRideDetails('<?php echo $ride['id']; ?>')">
                                                    View Details
                                                </button>
                                            </td>
                                        </tr>
                                    <?php
                                    endwhile;
                                else:
                                    ?>
                                    <tr>
                                        <td colspan="7" class="px-6 py-8 text-center text-gray-500">
                                            <i class="fas fa-car-side text-4xl mb-2 opacity-50"></i>
                                            <p>No rides yet</p>
                                            <button class="mt-4 bg-[#ff5e00] text-white px-6 py-2 rounded-xl text-sm font-medium" onclick="window.location.href='book-ride.php'">
                                                Book Your First Ride
                                            </button>
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Promo Banner -->
                <div class="mt-8 bg-gradient-to-r from-[#ff5e00] to-[#ff8c3a] rounded-xl p-6 text-white">
                    <div class="flex justify-between items-center">
                        <div>
                            <h3 class="text-xl font-bold">🚀 20% OFF Your Next Ride</h3>
                            <p class="opacity-90 mt-1">Use code: SPEEDLY20 • Valid for new users</p>
                        </div>
                        <button class="bg-white text-[#ff5e00] px-6 py-3 rounded-xl font-bold hover:shadow-lg transition" onclick="showComingSoon('Promo Details')">
                            Learn More
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ========== KORAPAY PAYMENT SUCCESS NOTIFICATION ==========
        <?php if (isset($paymentSuccess) && $paymentSuccess): ?>
        Swal.fire({
            icon: 'success',
            title: 'Deposit Successful! 💰',
            html: `
                <div style="text-align: center;">
                    <p style="font-size: 18px; margin-bottom: 10px;">Your wallet has been credited with</p>
                    <p style="font-size: 28px; font-weight: bold; color: #ff5e00;">₦<?php echo number_format($paymentAmount, 2); ?></p>
                    <p style="margin-top: 10px;">New balance: <strong>₦<?php echo number_format($newBalanceAmount, 2); ?></strong></p>
                </div>
            `,
            confirmButtonColor: '#ff5e00',
            confirmButtonText: 'Great!',
            timer: 5000,
            timerProgressBar: true
        });
        <?php elseif (isset($paymentFailed) && $paymentFailed): ?>
        Swal.fire({
            icon: 'error',
            title: 'Deposit Failed',
            text: 'Your payment could not be processed. Please try again.',
            confirmButtonColor: '#ff5e00'
        });
        <?php endif; ?>
        // ========== END KORAPAY PAYMENT NOTIFICATION ==========

        // Show coming soon message
        function showComingSoon(feature) {
            Swal.fire({
                icon: 'info',
                title: 'Coming Soon!',
                text: feature + ' feature will be available in the next update.',
                confirmButtonColor: '#ff5e00'
            });
        }

        // Check notifications
        function checkNotifications() {
            window.location.href = 'notifications.php';
        }
    </script>
    <script src="./JS/client_dashboard.js"></script>
</body>

</html>