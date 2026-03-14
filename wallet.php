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
$user_role = $_SESSION['role'] ?? 'client';

// Get wallet balance
$walletQuery = "SELECT 
    COALESCE(SUM(CASE WHEN transaction_type IN ('deposit', 'bonus', 'referral') THEN amount ELSE 0 END), 0) - 
    COALESCE(SUM(CASE WHEN transaction_type IN ('withdrawal', 'ride_payment') THEN amount ELSE 0 END), 0) as balance 
    FROM wallet_transactions WHERE user_id = ?";
$walletStmt = $conn->prepare($walletQuery);
$walletStmt->bind_param("s", $user_id);
$walletStmt->execute();
$walletResult = $walletStmt->get_result();
$walletData = $walletResult->fetch_assoc();
$walletBalance = $walletData['balance'] ?? 0;

// Get recent transactions
$transQuery = "SELECT * FROM wallet_transactions 
               WHERE user_id = ? 
               ORDER BY created_at DESC 
               LIMIT 10";
$transStmt = $conn->prepare($transQuery);
$transStmt->bind_param("s", $user_id);
$transStmt->execute();
$transResult = $transStmt->get_result();

// Get payment methods
$paymentQuery = "SELECT * FROM payment_methods WHERE user_id = ?";
$paymentStmt = $conn->prepare($paymentQuery);
$paymentStmt->bind_param("s", $user_id);
$paymentStmt->execute();
$paymentResult = $paymentStmt->get_result();
$hasPaymentMethods = $paymentResult->num_rows > 0;

// Get user stats
if ($user_role == 'client') {
    $statsQuery = "SELECT COUNT(*) as ride_count FROM rides r 
                   JOIN client_profiles cp ON r.client_id = cp.id 
                   WHERE cp.user_id = ?";
} else {
    $statsQuery = "SELECT COUNT(*) as ride_count FROM rides WHERE driver_id = 
                   (SELECT id FROM driver_profiles WHERE user_id = ?)";
}
$statsStmt = $conn->prepare($statsQuery);
$statsStmt->bind_param("s", $user_id);
$statsStmt->execute();
$statsResult = $statsStmt->get_result();
$statsData = $statsResult->fetch_assoc();
$rideCount = $statsData['ride_count'] ?? 0;
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Speedly | Wallet</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="./CSS/wallet.css" />
</head>

<body>
    <!-- Dashboard Container -->
    <div class="dashboard-container">
        <!-- MOBILE VIEW -->
        <div class="mobile-view">
            <!-- Header -->
            <div class="header">
                <div class="user-info">
                    <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!</h1>
                    <p>Manage your funds and transactions</p>
                </div>
                <button class="notification-btn bg-[#ff5e00] rounded-2xl p-1" onclick="checkNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
            </div>

            <!-- Balance Card -->
            <div class="mobile-balance-card">
                <div class="balance-card-header">
                    <h2>Total Balance</h2>
                    <?php if ($rideCount > 10): ?>
                    <div class="reward-badge whitespace-nowrap" style="font-size: 70%; background-color: green;">
                        <i class="fas fa-gift"></i>
                        <span>Reward Available</span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="balance-amount">₦<?php echo number_format($walletBalance, 2); ?></div>
                <div class="balance-change bg-black rounded-2xl p-1 pl-4">
                    <i class="fas fa-arrow-up"></i>
                    <span>Current balance</span>
                </div>
            </div>

            <!-- Payment Methods -->
            <div class="mobile-payment-methods">
                <div class="section-header">
                    <h3 class="section-title">Payment Methods</h3>
                    <button class="see-all-btn" onclick="addPaymentMethod()">+ Add New</button>
                </div>
                
                <div class="mobile-payment-list">
                    <?php if ($hasPaymentMethods): ?>
                        <?php while ($method = $paymentResult->fetch_assoc()): ?>
                        <div class="mobile-payment-item <?php echo $method['is_default'] ? 'selected' : ''; ?>" data-id="<?php echo $method['id']; ?>">
                            <div class="payment-select">
                                <div class="payment-radio">
                                    <div class="radio-dot"></div>
                                </div>
                            </div>
                            <div class="payment-icon <?php echo $method['method_type'] == 'bank_transfer' ? 'transfer-icon' : 'card-icon'; ?>">
                                <i class="fas fa-<?php echo $method['method_type'] == 'bank_transfer' ? 'exchange-alt' : 'credit-card'; ?>"></i>
                            </div>
                            <div class="payment-details">
                                <h4><?php echo ucfirst(str_replace('_', ' ', $method['method_type'])); ?></h4>
                                <p><?php echo $method['account_last4'] ? '**** ' . $method['account_last4'] : ''; ?></p>
                            </div>
                            <div class="payment-action" onclick="showPaymentOptions('<?php echo $method['id']; ?>', '<?php echo $method['method_type']; ?>')">
                                <i class="fas fa-ellipsis-v"></i>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-gray-500">No payment methods added yet</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="mobile-quick-actions-grid">
                <div class="section-header">
                    <h3 class="section-title">Quick Actions</h3>
                </div>
                <div class="quick-actions-grid">
                    <button class="mobile-action-btn" onclick="addFunds()">
                        <div class="mobile-action-icon add-wallet-icon">
                            <i class="fas fa-wallet"></i>
                        </div>
                        <span>Add to Wallet</span>
                    </button>

                    <button class="mobile-action-btn" onclick="showComingSoon('Refunds')">
                        <div class="mobile-action-icon refunds-icon">
                            <i class="fas fa-undo-alt"></i>
                        </div>
                        <span>Refunds</span>
                    </button>

                    <button class="mobile-action-btn" onclick="showComingSoon('Pending')">
                        <div class="mobile-action-icon pending-icon">
                            <i class="fas fa-clock"></i>
                        </div>
                        <span>Pending</span>
                    </button>

                    <button class="mobile-action-btn" onclick="showComingSoon('Support')">
                        <div class="mobile-action-icon support-icon">
                            <i class="fas fa-headset"></i>
                        </div>
                        <span>Support</span>
                    </button>

                    <button class="mobile-action-btn" onclick="withdrawFunds(<?php echo $walletBalance; ?>)">
                        <div class="mobile-action-icon withdrawal-icon">
                            <i class="fas fa-money-check-alt"></i>
                        </div>
                        <span>Withdraw</span>
                    </button>

                    <button class="mobile-action-btn" onclick="addPaymentMethod()">
                        <div class="mobile-action-icon payment-methods-icon">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <span>Methods</span>
                    </button>

                    <button class="mobile-action-btn" onclick="window.location.href='ride_history.php'">
                        <div class="mobile-action-icon history-icon">
                            <i class="fas fa-history"></i>
                        </div>
                        <span>History</span>
                    </button>

                    <button class="mobile-action-btn" onclick="showComingSoon('Promo Codes')">
                        <div class="mobile-action-icon promo-icon">
                            <i class="fas fa-tag"></i>
                        </div>
                        <span>Promo Codes</span>
                    </button>
                </div>
            </div>

            <!-- Recent Transactions -->
            <div class="transactions-section">
                <div class="section-header">
                    <div class="section-title">Recent Transactions</div>
                    <button class="see-all-btn" onclick="window.location.href='ride_history.php'">See All</button>
                </div>
                <div class="transaction-list">
                    <?php if ($transResult->num_rows > 0): ?>
                        <?php while ($trans = $transResult->fetch_assoc()): 
                            $isPositive = in_array($trans['transaction_type'], ['deposit', 'bonus', 'referral']);
                        ?>
                        <div class="transaction-item" onclick="viewTransaction('<?php echo $trans['id']; ?>')">
                            <div class="transaction-info">
                                <div class="transaction-icon <?php echo $isPositive ? 'topup-icon' : 'transfer-icon'; ?>">
                                    <i class="fas fa-<?php echo $isPositive ? 'plus' : 'minus'; ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <h4><?php echo ucfirst($trans['transaction_type']); ?></h4>
                                    <p><?php echo date('M d, h:i A', strtotime($trans['created_at'])); ?></p>
                                </div>
                            </div>
                            <div class="transaction-amount <?php echo $isPositive ? 'positive' : 'negative'; ?>">
                                <?php echo $isPositive ? '+' : '-'; ?>₦<?php echo number_format($trans['amount'], 2); ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-4 text-gray-500">No transactions yet</div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Ride Booking Section (Mobile) -->
            <div class="mobile-ride-booking">
                <div class="mobile-ride-info">
                    <h3>Ready for your next ride?</h3>
                    <p>Book a ride instantly and enjoy our premium service.</p>
                    <div class="mobile-ride-stats">
                        <div class="mobile-stat-item">
                            <div class="mobile-stat-value"><?php echo $rideCount; ?></div>
                            <div class="mobile-stat-label">Rides</div>
                        </div>
                        <div class="mobile-stat-item">
                            <div class="mobile-stat-value">4.9</div>
                            <div class="mobile-stat-label">Rating</div>
                        </div>
                        <div class="mobile-stat-item">
                            <div class="mobile-stat-value">₦<?php echo number_format($walletBalance); ?></div>
                            <div class="mobile-stat-label">Balance</div>
                        </div>
                    </div>
                </div>
                <button class="mobile-book-ride-btn" onclick="window.location.href='book-ride.php'">
                    <i class="fas fa-car"></i>
                    <span>Book a Ride Now</span>
                </button>
            </div>

            <!-- Bottom Navigation -->
            <?php require_once './components/mobile-nav.php'; ?>
        </div>

        <!-- DESKTOP VIEW -->
        <div class="desktop-view">
            <!-- Sidebar -->
            <div class="desktop-sidebar">
                <div class="logo">
                    <img src="./main-assets/logo-no-background.png" alt="Speedly Logo" class="logo-image" />
                </div>

                <!-- Desktop Navigation -->
                <?php require_once './components/desktop-nav.php'; ?>

                <!-- User Profile -->
                <div class="user-profile" onclick="window.location.href='<?php echo $user_role; ?>_profile.php'">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <div class="profile-info">
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
                        <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>!</h1>
                        <p>Manage your wallet and transactions</p>
                    </div>
                    <div class="desktop-actions">
                        <button class="notification-btn" onclick="checkNotifications()">
                            <i class="fas fa-bell"></i>
                        </button>
                        <button class="add-money-btn" onclick="addFunds()">Add Money</button>
                    </div>
                </div>

                <!-- Dashboard Grid -->
                <div class="desktop-grid">
                    <!-- Balance Card -->
                    <div class="desktop-card balance-card">
                        <div class="card-header">
                            <h2>Total Balance</h2>
                            <?php if ($rideCount > 10): ?>
                            <div class="reward-badge">
                                <i class="fas fa-gift"></i>
                                <span>Reward Available</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="balance-amount">₦<?php echo number_format($walletBalance, 2); ?></div>
                        <div class="balance-change">
                            <i class="fas fa-arrow-up"></i>
                            <span>Current balance</span>
                        </div>
                    </div>

                    <div class="desktop-card payment-methods-card">
                        <div class="card-header">
                            <h2>Payment Methods</h2>
                            <button class="see-all-btn" onclick="addPaymentMethod()">+ Add New</button>
                        </div>

                        <div class="payment-methods-list">
                            <?php 
                            // Reset payment result pointer
                            $paymentResult->data_seek(0);
                            if ($paymentResult->num_rows > 0): 
                                while ($method = $paymentResult->fetch_assoc()): 
                            ?>
                            <div class="payment-method-item <?php echo $method['is_default'] ? 'selected' : ''; ?>" data-id="<?php echo $method['id']; ?>">
                                <div class="payment-method-select">
                                    <div class="payment-radio">
                                        <div class="radio-dot"></div>
                                    </div>
                                </div>
                                <div class="payment-method-icon <?php echo $method['method_type'] == 'bank_transfer' ? 'transfer-icon' : 'card-icon'; ?>">
                                    <i class="fas fa-<?php echo $method['method_type'] == 'bank_transfer' ? 'exchange-alt' : 'credit-card'; ?>"></i>
                                </div>
                                <div class="payment-method-details">
                                    <h4><?php echo ucfirst(str_replace('_', ' ', $method['method_type'])); ?></h4>
                                    <p><?php echo $method['account_last4'] ? '**** ' . $method['account_last4'] : ''; ?></p>
                                </div>
                                <div class="payment-method-action" onclick="showPaymentOptions('<?php echo $method['id']; ?>', '<?php echo $method['method_type']; ?>')">
                                    <i class="fas fa-ellipsis-v"></i>
                                </div>
                            </div>
                            <?php 
                                endwhile;
                            else: 
                            ?>
                            <div class="text-center py-4 text-gray-500">No payment methods added yet</div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="desktop-card large">
                        <div class="card-header">
                            <h2>Quick Actions</h2>
                        </div>
                        <div class="desktop-quick-actions">
                            <button class="desktop-action-btn" onclick="addFunds()">
                                <div class="desktop-action-icon add-wallet-icon">
                                    <i class="fas fa-wallet"></i>
                                </div>
                                <span>Add to Wallet</span>
                            </button>

                            <button class="desktop-action-btn" onclick="showComingSoon('Refunds')">
                                <div class="desktop-action-icon refunds-icon">
                                    <i class="fas fa-undo-alt"></i>
                                </div>
                                <span>Refunds</span>
                            </button>

                            <button class="desktop-action-btn" onclick="showComingSoon('Pending Payment')">
                                <div class="desktop-action-icon pending-icon">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <span>Pending Payment</span>
                            </button>

                            <button class="desktop-action-btn" onclick="showComingSoon('Contact Support')">
                                <div class="desktop-action-icon support-icon">
                                    <i class="fas fa-headset"></i>
                                </div>
                                <span>Contact Support</span>
                            </button>

                            <button class="desktop-action-btn" onclick="withdrawFunds(<?php echo $walletBalance; ?>)">
                                <div class="desktop-action-icon withdrawal-icon">
                                    <i class="fas fa-money-check-alt"></i>
                                </div>
                                <span>Request Withdrawal</span>
                            </button>

                            <button class="desktop-action-btn" onclick="addPaymentMethod()">
                                <div class="desktop-action-icon payment-methods-icon">
                                    <i class="fas fa-credit-card"></i>
                                </div>
                                <span>Payment Methods</span>
                            </button>

                            <button class="desktop-action-btn" onclick="window.location.href='ride_history.php'">
                                <div class="desktop-action-icon history-icon">
                                    <i class="fas fa-history"></i>
                                </div>
                                <span>Transaction History</span>
                            </button>

                            <button class="desktop-action-btn" onclick="showComingSoon('Promo Codes')">
                                <div class="desktop-action-icon promo-icon">
                                    <i class="fas fa-tag"></i>
                                </div>
                                <span>Promo Codes</span>
                            </button>
                        </div>
                    </div>

                    <!-- Recent Transactions -->
                    <div class="desktop-card large">
                        <div class="card-header">
                            <h2>Recent Transactions</h2>
                            <button class="see-all-btn" onclick="window.location.href='ride_history.php'">See All</button>
                        </div>
                        <div class="desktop-transactions">
                            <div class="transaction-list">
                                <?php 
                                // Reset transaction result pointer
                                $transResult->data_seek(0);
                                if ($transResult->num_rows > 0): 
                                    while ($trans = $transResult->fetch_assoc()): 
                                        $isPositive = in_array($trans['transaction_type'], ['deposit', 'bonus', 'referral']);
                                ?>
                                <div class="transaction-item" onclick="viewTransaction('<?php echo $trans['id']; ?>')">
                                    <div class="transaction-info">
                                        <div class="transaction-icon <?php echo $isPositive ? 'topup-icon' : 'transfer-icon'; ?>">
                                            <i class="fas fa-<?php echo $isPositive ? 'plus' : 'minus'; ?>"></i>
                                        </div>
                                        <div class="transaction-details">
                                            <h4><?php echo ucfirst($trans['transaction_type']); ?></h4>
                                            <p><?php echo date('M d, h:i A', strtotime($trans['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <div class="transaction-amount <?php echo $isPositive ? 'positive' : 'negative'; ?>">
                                        <?php echo $isPositive ? '+' : '-'; ?>₦<?php echo number_format($trans['amount'], 2); ?>
                                    </div>
                                </div>
                                <?php 
                                    endwhile;
                                else: 
                                ?>
                                <div class="text-center py-4 text-gray-500">No transactions yet</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ride Booking Section -->
                <div class="ride-booking-desktop">
                    <div class="ride-info">
                        <h2>Ready for your next ride?</h2>
                        <p>
                            Book a ride instantly and enjoy our premium service with safety
                            measures and comfortable vehicles.
                        </p>
                        <div class="ride-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $rideCount; ?></div>
                                <div class="stat-label">Rides Taken</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">4.9</div>
                                <div class="stat-label">Avg. Rating</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">₦<?php echo number_format($walletBalance); ?></div>
                                <div class="stat-label">Balance</div>
                            </div>
                        </div>
                    </div>
                    <button class="book-ride-btn-desktop" onclick="window.location.href='book-ride.php'">
                        <i class="fas fa-car"></i>
                        <span>Book a Ride Now</span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="./JS/wallet.js"></script>
</body>

</html>  