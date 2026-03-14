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

// Get driver profile
$driverQuery = "SELECT id FROM driver_profiles WHERE user_id = ?";
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
    $_SESSION['error_message'] = "Please complete your driver profile setup first.";
    header("Location: kyc.php");
    exit;
}

// Safely access driver data
$driver_id = $driverData['id'];

// Initialize variables
$recentRides = null;
$withdrawResult = null;
$todayEarnings = 0;
$weekEarnings = 0;
$totalEarnings = 0;
$walletBalance = 0;
$totalWithdrawn = 0;

// Calculate total earnings from completed rides
$totalEarningsQuery = "SELECT COALESCE(SUM(driver_payout), 0) as total_earnings 
                       FROM rides 
                       WHERE driver_id = ? AND status = 'completed'";
$totalEarningsStmt = $conn->prepare($totalEarningsQuery);
if ($totalEarningsStmt) {
    $totalEarningsStmt->bind_param("s", $driver_id);
    $totalEarningsStmt->execute();
    $totalEarningsResult = $totalEarningsStmt->get_result();
    $totalEarningsData = $totalEarningsResult->fetch_assoc();
    $totalEarnings = $totalEarningsData['total_earnings'] ?? 0;
}

// Calculate total amount withdrawn
$totalWithdrawnQuery = "SELECT COALESCE(SUM(amount), 0) as total_withdrawn 
                        FROM driver_withdrawals 
                        WHERE driver_id = ? AND status IN ('approved', 'paid')";
$totalWithdrawnStmt = $conn->prepare($totalWithdrawnQuery);
if ($totalWithdrawnStmt) {
    $totalWithdrawnStmt->bind_param("s", $driver_id);
    $totalWithdrawnStmt->execute();
    $totalWithdrawnResult = $totalWithdrawnStmt->get_result();
    $totalWithdrawnData = $totalWithdrawnResult->fetch_assoc();
    $totalWithdrawn = $totalWithdrawnData['total_withdrawn'] ?? 0;
}

// Calculate wallet balance (earnings - withdrawals)
$walletBalance = $totalEarnings - $totalWithdrawn;

// Get recent completed rides (earnings)
$ridesQuery = "SELECT 
                r.id,
                r.total_fare,
                r.driver_payout,
                r.created_at,
                r.status,
                r.ride_number,
                r.pickup_address,
                r.destination_address,
                u.full_name as client_name
               FROM rides r
               LEFT JOIN client_profiles cp ON r.client_id = cp.id
               LEFT JOIN users u ON cp.user_id = u.id
               WHERE r.driver_id = ? AND r.status = 'completed'
               ORDER BY r.created_at DESC 
               LIMIT 20";
$ridesStmt = $conn->prepare($ridesQuery);
if ($ridesStmt) {
    $ridesStmt->bind_param("s", $driver_id);
    $ridesStmt->execute();
    $recentRides = $ridesStmt->get_result();
}

// Get withdrawal history
$withdrawQuery = "SELECT * FROM driver_withdrawals WHERE driver_id = ? ORDER BY created_at DESC LIMIT 10";
$withdrawStmt = $conn->prepare($withdrawQuery);
if ($withdrawStmt) {
    $withdrawStmt->bind_param("s", $driver_id);
    $withdrawStmt->execute();
    $withdrawResult = $withdrawStmt->get_result();
}

// Get today's earnings from rides
$todayQuery = "SELECT COALESCE(SUM(driver_payout), 0) as today_earnings 
               FROM rides 
               WHERE driver_id = ? AND DATE(created_at) = CURDATE() AND status = 'completed'";
$todayStmt = $conn->prepare($todayQuery);
if ($todayStmt) {
    $todayStmt->bind_param("s", $driver_id);
    $todayStmt->execute();
    $todayResult = $todayStmt->get_result();
    $todayData = $todayResult->fetch_assoc();
    $todayEarnings = $todayData['today_earnings'] ?? 0;
}

// Get this week's earnings from rides
$weekQuery = "SELECT COALESCE(SUM(driver_payout), 0) as week_earnings 
              FROM rides 
              WHERE driver_id = ? AND YEARWEEK(created_at) = YEARWEEK(NOW()) AND status = 'completed'";
$weekStmt = $conn->prepare($weekQuery);
if ($weekStmt) {
    $weekStmt->bind_param("s", $driver_id);
    $weekStmt->execute();
    $weekResult = $weekStmt->get_result();
    $weekData = $weekResult->fetch_assoc();
    $weekEarnings = $weekData['week_earnings'] ?? 0;
}

// Get notification count
$notifQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notifStmt = $conn->prepare($notifQuery);
if ($notifStmt) {
    $notifStmt->bind_param("s", $user_id);
    $notifStmt->execute();
    $notifResult = $notifStmt->get_result();
    $notifData = $notifResult->fetch_assoc();
    $notificationCount = $notifData['count'] ?? 0;
} else {
    $notificationCount = 0;
}

// Get any session messages
$success_message = $_SESSION['success_message'] ?? '';
$error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['success_message'], $_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedly • Driver Wallet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="./CSS/driver_dashboard.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Add any additional styles if needed */
        .transaction-item:hover {
            background-color: #f9fafb;
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
                    <h1>Wallet</h1>
                    <p><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>'s earnings</p>
                </div>
                <button class="notification-btn bg-[#ff5e00] rounded-2xl p-2 relative" onclick="checkNotifications()">
                    <i class="fas fa-bell text-white"></i>
                    <?php if ($notificationCount > 0): ?>
                    <span class="notification-badge"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- Balance Card -->
            <div class="bg-gradient-to-r from-[#ff5e00] to-[#ff8c3a] m-4 p-6 rounded-2xl text-white">
                <div class="flex justify-between items-start">
                    <div>
                        <p class="text-sm opacity-90">Available Balance</p>
                        <p class="text-3xl font-bold mt-1">₦<?php echo number_format($walletBalance, 2); ?></p>
                        <p class="text-xs opacity-75 mt-1">Total Lifetime Earnings: ₦<?php echo number_format($totalEarnings, 2); ?></p>
                    </div>
                    <i class="fas fa-wallet text-3xl opacity-80"></i>
                </div>
                <div class="mt-4 flex gap-3">
                    <button class="flex-1 bg-white text-[#ff5e00] py-3 rounded-xl font-semibold" onclick="withdrawFunds()">
                        <i class="fas fa-hand-holding-usd mr-2"></i> Withdraw
                    </button>
                    <button class="flex-1 bg-white/20 py-3 rounded-xl font-semibold" onclick="viewHistory()">
                        <i class="fas fa-history mr-2"></i> History
                    </button>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="grid grid-cols-3 gap-3 px-4">
                <div class="bg-white p-4 rounded-xl shadow-sm">
                    <div class="text-gray-500 text-xs">Today</div>
                    <div class="font-bold text-sm mt-1">₦<?php echo number_format($todayEarnings); ?></div>
                    <div class="text-xs <?php echo $todayEarnings > 0 ? 'text-green-600' : 'text-gray-400'; ?>">
                        <?php echo $todayEarnings > 0 ? 'Active' : 'No rides'; ?>
                    </div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm">
                    <div class="text-gray-500 text-xs">This Week</div>
                    <div class="font-bold text-sm mt-1">₦<?php echo number_format($weekEarnings); ?></div>
                    <div class="text-xs text-gray-400">Weekly total</div>
                </div>
                <div class="bg-white p-4 rounded-xl shadow-sm">
                    <div class="text-gray-500 text-xs">Total</div>
                    <div class="font-bold text-sm mt-1">₦<?php echo number_format($totalEarnings); ?></div>
                    <div class="text-xs text-gray-400">Lifetime</div>
                </div>
            </div>

            <!-- Recent Earnings from Rides -->
            <div class="px-4 mt-6 pb-20">
                <h2 class="font-semibold mb-3">Recent Ride Earnings</h2>
                <?php if ($recentRides && $recentRides->num_rows > 0): ?>
                    <?php while ($ride = $recentRides->fetch_assoc()): ?>
                    <div class="bg-white p-4 rounded-xl shadow-sm mb-3 transaction-item">
                        <div class="flex justify-between items-start">
                            <div>
                                <span class="text-xs text-gray-500"><?php echo date('M d, h:i A', strtotime($ride['created_at'])); ?></span>
                                <div class="font-medium mt-1">Ride #<?php echo substr($ride['ride_number'] ?? '', -8); ?></div>
                                <div class="text-sm text-gray-500">
                                    <?php echo htmlspecialchars(substr($ride['pickup_address'] ?? 'Pickup', 0, 20)); ?>...
                                </div>
                                <?php if ($ride['client_name']): ?>
                                <div class="text-xs text-gray-400 mt-1">Client: <?php echo htmlspecialchars($ride['client_name']); ?></div>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <span class="font-bold text-green-600">+₦<?php echo number_format($ride['driver_payout'] ?? 0, 2); ?></span>
                                <div class="text-xs text-gray-400 mt-1">Fare: ₦<?php echo number_format($ride['total_fare'] ?? 0, 2); ?></div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="bg-white p-8 rounded-xl text-center">
                        <i class="fas fa-coins text-4xl text-gray-300 mb-3"></i>
                        <p class="text-gray-500">No earnings yet</p>
                        <p class="text-sm text-gray-400 mt-1">Complete rides to see earnings here</p>
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
                        <h1 class="text-2xl font-bold">Driver Wallet</h1>
                        <p class="text-gray-500">Manage your earnings and withdrawals</p>
                    </div>
                    <button class="notification-btn bg-gray-100 p-3 rounded-xl relative hover:bg-gray-200 transition" onclick="checkNotifications()">
                        <i class="fas fa-bell text-gray-700 text-xl"></i>
                        <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge"><?php echo $notificationCount; ?></span>
                        <?php endif; ?>
                    </button>
                </div>

                <!-- Balance Card -->
                <div class="bg-gradient-to-r from-[#ff5e00] to-[#ff8c3a] p-8 rounded-2xl text-white mb-8">
                    <div class="flex justify-between items-start">
                        <div>
                            <p class="text-sm opacity-90">Available Balance</p>
                            <p class="text-4xl font-bold mt-2">₦<?php echo number_format($walletBalance, 2); ?></p>
                            <p class="text-sm opacity-75 mt-1">Total Lifetime Earnings: ₦<?php echo number_format($totalEarnings, 2); ?></p>
                        </div>
                        <div class="bg-white/20 p-4 rounded-xl">
                            <i class="fas fa-wallet text-3xl"></i>
                        </div>
                    </div>
                    <div class="mt-6 flex gap-4">
                        <button class="bg-white text-[#ff5e00] px-8 py-3 rounded-xl font-semibold hover:shadow-lg transition" onclick="withdrawFunds()">
                            <i class="fas fa-hand-holding-usd mr-2"></i> Withdraw Funds
                        </button>
                        <button class="bg-white/20 px-8 py-3 rounded-xl font-semibold hover:bg-white/30 transition" onclick="viewHistory()">
                            <i class="fas fa-history mr-2"></i> Transaction History
                        </button>
                    </div>
                </div>

                <!-- Stats Grid -->
                <div class="grid grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-xl shadow-sm">
                        <div class="text-gray-500 mb-2">Today's Earnings</div>
                        <div class="text-2xl font-bold">₦<?php echo number_format($todayEarnings, 2); ?></div>
                        <div class="text-sm mt-2 <?php echo $todayEarnings > 0 ? 'text-green-600' : 'text-gray-400'; ?>">
                            <?php echo $todayEarnings > 0 ? '✓ Active today' : 'No rides yet today'; ?>
                        </div>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm">
                        <div class="text-gray-500 mb-2">This Week</div>
                        <div class="text-2xl font-bold">₦<?php echo number_format($weekEarnings, 2); ?></div>
                        <div class="text-sm text-gray-500 mt-2">Weekly total</div>
                    </div>
                    <div class="bg-white p-6 rounded-xl shadow-sm">
                        <div class="text-gray-500 mb-2">Total Earnings</div>
                        <div class="text-2xl font-bold">₦<?php echo number_format($totalEarnings, 2); ?></div>
                        <div class="text-sm text-gray-500 mt-2">Lifetime earnings</div>
                    </div>
                </div>

                <!-- Two Column Layout -->
                <div class="grid grid-cols-2 gap-6">
                    <!-- Recent Ride Earnings -->
                    <div class="bg-white rounded-xl shadow-sm">
                        <div class="p-6 border-b">
                            <h2 class="text-xl font-semibold">Recent Ride Earnings</h2>
                        </div>
                        <div class="p-4 max-h-96 overflow-y-auto">
                            <?php 
                            if ($recentRides && $recentRides->num_rows > 0):
                                $recentRides->data_seek(0);
                                while ($ride = $recentRides->fetch_assoc()): 
                            ?>
                                <div class="flex justify-between items-center py-3 border-b last:border-0 hover:bg-gray-50 px-2 rounded transition">
                                    <div>
                                        <div class="font-medium">Ride #<?php echo substr($ride['ride_number'] ?? '', -8); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo date('M d, h:i A', strtotime($ride['created_at'])); ?></div>
                                        <div class="text-xs text-gray-400 mt-1">
                                            <?php echo htmlspecialchars(substr($ride['pickup_address'] ?? '', 0, 30)); ?>...
                                        </div>
                                    </div>
                                    <div class="text-right">
                                        <div class="font-bold text-green-600">+₦<?php echo number_format($ride['driver_payout'] ?? 0, 2); ?></div>
                                        <div class="text-xs text-gray-400">Fare: ₦<?php echo number_format($ride['total_fare'] ?? 0, 2); ?></div>
                                    </div>
                                </div>
                            <?php 
                                endwhile;
                            else: 
                            ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-coins text-4xl mb-3 opacity-30"></i>
                                    <p>No earnings yet</p>
                                    <p class="text-sm mt-2">Complete rides to see earnings here</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Withdrawal History -->
                    <div class="bg-white rounded-xl shadow-sm">
                        <div class="p-6 border-b">
                            <h2 class="text-xl font-semibold">Withdrawal History</h2>
                        </div>
                        <div class="p-4 max-h-96 overflow-y-auto">
                            <?php if ($withdrawResult && $withdrawResult->num_rows > 0): ?>
                                <?php while ($withdraw = $withdrawResult->fetch_assoc()): ?>
                                <div class="flex justify-between items-center py-3 border-b last:border-0 hover:bg-gray-50 px-2 rounded transition">
                                    <div>
                                        <div class="font-medium">₦<?php echo number_format($withdraw['amount'], 2); ?></div>
                                        <div class="text-sm text-gray-500"><?php echo date('M d, Y', strtotime($withdraw['created_at'])); ?></div>
                                        <div class="text-xs text-gray-400"><?php echo $withdraw['bank_name'] ?? ''; ?></div>
                                    </div>
                                    <div>
                                        <?php if ($withdraw['status'] == 'pending'): ?>
                                            <span class="bg-yellow-100 text-yellow-700 px-3 py-1 rounded-full text-xs font-medium">Pending</span>
                                        <?php elseif ($withdraw['status'] == 'approved'): ?>
                                            <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full text-xs font-medium">Approved</span>
                                        <?php elseif ($withdraw['status'] == 'paid'): ?>
                                            <span class="bg-green-100 text-green-700 px-3 py-1 rounded-full text-xs font-medium">Paid</span>
                                        <?php elseif ($withdraw['status'] == 'rejected'): ?>
                                            <span class="bg-red-100 text-red-700 px-3 py-1 rounded-full text-xs font-medium">Rejected</span>
                                        <?php else: ?>
                                            <span class="bg-gray-100 text-gray-700 px-3 py-1 rounded-full text-xs font-medium"><?php echo ucfirst($withdraw['status']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-8 text-gray-500">
                                    <i class="fas fa-history text-4xl mb-3 opacity-30"></i>
                                    <p>No withdrawals yet</p>
                                    <p class="text-sm mt-2">Your withdrawal history will appear here</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    // Display success/error messages if they exist
    <?php if ($success_message): ?>
    Swal.fire({
        title: 'Success',
        text: '<?php echo addslashes($success_message); ?>',
        icon: 'success',
        confirmButtonColor: '#ff5e00'
    });
    <?php endif; ?>

    <?php if ($error_message): ?>
    Swal.fire({
        title: 'Error',
        text: '<?php echo addslashes($error_message); ?>',
        icon: 'error',
        confirmButtonColor: '#ff5e00'
    });
    <?php endif; ?>

    function checkNotifications() {
        Swal.fire({
            title: 'Notifications',
            html: '<p>🔔 No new notifications</p>',
            icon: 'info',
            confirmButtonColor: '#ff5e00'
        });
    }

    function withdrawFunds() {
        <?php if ($walletBalance < 1000): ?>
        Swal.fire({
            title: 'Insufficient Balance',
            text: 'Minimum withdrawal amount is ₦1,000. Your current balance is ₦<?php echo number_format($walletBalance, 2); ?>',
            icon: 'warning',
            confirmButtonColor: '#ff5e00'
        });
        return;
        <?php endif; ?>

        Swal.fire({
            title: 'Withdraw Funds',
            html: `
                <p class="mb-4">Available balance: <strong>₦<?php echo number_format($walletBalance, 2); ?></strong></p>
                <input type="number" id="withdraw-amount" class="swal2-input" placeholder="Enter amount" min="1000" max="<?php echo $walletBalance; ?>" step="100">
                <select id="bank-name" class="swal2-input">
                    <option value="">Select Bank</option>
                    <option value="Access Bank">Access Bank</option>
                    <option value="GTBank">GTBank</option>
                    <option value="First Bank">First Bank</option>
                    <option value="UBA">UBA</option>
                    <option value="Zenith">Zenith Bank</option>
                    <option value="Fidelity">Fidelity Bank</option>
                    <option value="Union Bank">Union Bank</option>
                </select>
                <input type="text" id="account-number" class="swal2-input" placeholder="Account Number" maxlength="10" pattern="[0-9]{10}">
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
                if (amount > <?php echo $walletBalance; ?>) {
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

    function viewHistory() {
        window.location.href = 'book_history.php';
    }
    </script>   
</body>  
</html>