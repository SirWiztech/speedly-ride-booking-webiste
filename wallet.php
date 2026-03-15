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

// Get wallet balance - FIXED to include ride_refund
$walletQuery = "SELECT 
    COALESCE(SUM(CASE WHEN transaction_type IN ('deposit', 'bonus', 'referral', 'ride_refund') THEN amount ELSE 0 END), 0) - 
    COALESCE(SUM(CASE WHEN transaction_type IN ('withdrawal', 'ride_payment') THEN amount ELSE 0 END), 0) as balance 
    FROM wallet_transactions WHERE user_id = ?";
$walletStmt = $conn->prepare($walletQuery);
$walletStmt->bind_param("s", $user_id);
$walletStmt->execute();
$walletResult = $walletStmt->get_result();
$walletData = $walletResult->fetch_assoc();
$walletBalance = $walletData['balance'] ?? 0;

// Get recent transactions with ride details
$transQuery = "SELECT wt.*, 
               r.ride_number,
               r.pickup_address,
               r.destination_address
               FROM wallet_transactions wt
               LEFT JOIN rides r ON wt.ride_id = r.id
               WHERE wt.user_id = ? 
               ORDER BY wt.created_at DESC 
               LIMIT 20";
$transStmt = $conn->prepare($transQuery);
$transStmt->bind_param("s", $user_id);
$transStmt->execute();
$transResult = $transStmt->get_result();

// Get payment methods
$paymentQuery = "SELECT * FROM payment_methods WHERE user_id = ? ORDER BY is_default DESC";
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
                    <?php if ($transResult && $transResult->num_rows > 0): ?>
                        <?php 
                        $transResult->data_seek(0);
                        while ($trans = $transResult->fetch_assoc()): 
                            // FIXED: Include ride_refund as positive transaction
                            $isPositive = in_array($trans['transaction_type'], ['deposit', 'bonus', 'referral', 'ride_refund']);
                            // Generate a proper display ID
                            $displayId = '';
                            if (!empty($trans['ride_number'])) {
                                $displayId = 'Ride #' . $trans['ride_number'];
                            } elseif (!empty($trans['reference'])) {
                                $displayId = $trans['reference'];
                            } else {
                                // Use last 8 characters of UUID as transaction ID
                                $displayId = 'TXN-' . substr($trans['id'], -8);
                            }
                            
                            // Prepare transaction data for onclick
                            $transactionData = [
                                'id' => $trans['id'],
                                'display_id' => $displayId,
                                'type' => $trans['transaction_type'],
                                'type_display' => ucfirst(str_replace('_', ' ', $trans['transaction_type'])),
                                'amount' => floatval($trans['amount']),
                                'formatted_amount' => '₦' . number_format($trans['amount'], 2),
                                'date' => date('M d, Y h:i A', strtotime($trans['created_at'])),
                                'status' => $trans['status'],
                                'reference' => $trans['reference'] ?? 'N/A',
                                'description' => $trans['description'] ?? '',
                                'balance_before' => floatval($trans['balance_before'] ?? 0),
                                'balance_after' => floatval($trans['balance_after'] ?? 0),
                                'is_credit' => $isPositive
                            ];
                        ?>
                        <div class="transaction-item" onclick='viewTransaction(<?php echo json_encode($transactionData); ?>)'>
                            <div class="transaction-info">
                                <div class="transaction-icon <?php echo $isPositive ? 'topup-icon' : 'transfer-icon'; ?>">
                                    <i class="fas fa-<?php echo $isPositive ? 'plus' : 'minus'; ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <h4><?php echo ucfirst(str_replace('_', ' ', $trans['transaction_type'])); ?></h4>
                                    <p><?php echo date('M d, h:i A', strtotime($trans['created_at'])); ?></p>
                                    <p class="text-xs text-gray-400"><?php echo $displayId; ?></p>
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

        <!-- DESKTOP VIEW - FIXED LAYOUT -->
        <div class="desktop-view">
            <!-- Sidebar - Fixed width -->
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

            <!-- Main Content - Flexes to fill remaining space -->
            <div class="desktop-main">
                <!-- Header -->
                <div class="desktop-header">
                    <div class="desktop-title">
                        <h1>Wallet</h1>
                        <p>Manage your funds and transactions</p>
                    </div>
                    <div class="desktop-actions">
                        <button class="notification-btn" onclick="checkNotifications()">
                            <i class="fas fa-bell"></i>
                        </button>
                        <button class="add-money-btn" onclick="addFunds()">Add Money</button>
                    </div>
                </div>

                <!-- Dashboard Grid - 2 columns for cards -->
                <div class="desktop-grid">
                    <!-- Balance Card - Spans 1 column -->
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

                    <!-- Payment Methods Card - Spans 1 column -->
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

                    <!-- Quick Actions - Spans 2 columns -->
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

                    <!-- Recent Transactions - Spans 2 columns -->
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
                                if ($transResult && $transResult->num_rows > 0): 
                                    while ($trans = $transResult->fetch_assoc()): 
                                        // FIXED: Include ride_refund as positive transaction
                                        $isPositive = in_array($trans['transaction_type'], ['deposit', 'bonus', 'referral', 'ride_refund']);
                                        $displayId = '';
                                        if (!empty($trans['ride_number'])) {
                                            $displayId = 'Ride #' . $trans['ride_number'];
                                        } elseif (!empty($trans['reference'])) {
                                            $displayId = $trans['reference'];
                                        } else {
                                            $displayId = 'TXN-' . substr($trans['id'], -8);
                                        }
                                        
                                        $transactionData = [
                                            'id' => $trans['id'],
                                            'display_id' => $displayId,
                                            'type' => $trans['transaction_type'],
                                            'type_display' => ucfirst(str_replace('_', ' ', $trans['transaction_type'])),
                                            'amount' => floatval($trans['amount']),
                                            'formatted_amount' => '₦' . number_format($trans['amount'], 2),
                                            'date' => date('M d, Y h:i A', strtotime($trans['created_at'])),
                                            'status' => $trans['status'],
                                            'reference' => $trans['reference'] ?? 'N/A',
                                            'description' => $trans['description'] ?? '',
                                            'balance_before' => floatval($trans['balance_before'] ?? 0),
                                            'balance_after' => floatval($trans['balance_after'] ?? 0),
                                            'is_credit' => $isPositive
                                        ];
                                ?>
                                <div class="transaction-item" onclick='viewTransaction(<?php echo json_encode($transactionData); ?>)'>
                                    <div class="transaction-info">
                                        <div class="transaction-icon <?php echo $isPositive ? 'topup-icon' : 'transfer-icon'; ?>">
                                            <i class="fas fa-<?php echo $isPositive ? 'plus' : 'minus'; ?>"></i>
                                        </div>
                                        <div class="transaction-details">
                                            <h4><?php echo ucfirst(str_replace('_', ' ', $trans['transaction_type'])); ?></h4>
                                            <p><?php echo date('M d, h:i A', strtotime($trans['created_at'])); ?></p>
                                            <p class="text-xs text-gray-400"><?php echo $displayId; ?></p>
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

    <script>
    // ========== WALLET FUNCTIONS ==========
    
    // View transaction details
    function viewTransaction(transaction) {
        console.log('Viewing transaction:', transaction);
        
        // Build HTML for transaction details
        const amountClass = transaction.is_credit ? 'positive' : 'negative';
        const amountPrefix = transaction.is_credit ? '+' : '-';
        
        const html = `
            <div style="text-align: left; padding: 10px;">
                <div style="background: #f8f9fa; padding: 20px; border-radius: 10px; margin-bottom: 15px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                        <span style="font-size: 18px; font-weight: bold;">Transaction Details</span>
                        <span style="background: ${transaction.status === 'completed' ? '#4CAF50' : '#FF9800'}; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;">
                            ${transaction.status.toUpperCase()}
                        </span>
                    </div>
                    
                    <div style="margin-bottom: 20px; text-align: center;">
                        <span style="font-size: 32px; font-weight: bold; color: ${transaction.is_credit ? '#4CAF50' : '#f44336'};">
                            ${amountPrefix}${transaction.formatted_amount}
                        </span>
                    </div>
                    
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px 0; color: #666;">Transaction ID</td>
                            <td style="padding: 10px 0; text-align: right; font-weight: 500;">${transaction.display_id || transaction.id}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px 0; color: #666;">Type</td>
                            <td style="padding: 10px 0; text-align: right; font-weight: 500;">${transaction.type_display}</td>
                        </tr>
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px 0; color: #666;">Date & Time</td>
                            <td style="padding: 10px 0; text-align: right; font-weight: 500;">${transaction.date}</td>
                        </tr>
                        ${transaction.reference && transaction.reference !== 'N/A' ? `
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px 0; color: #666;">Reference</td>
                            <td style="padding: 10px 0; text-align: right; font-weight: 500;">${transaction.reference}</td>
                        </tr>
                        ` : ''}
                        ${transaction.description ? `
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px 0; color: #666;">Description</td>
                            <td style="padding: 10px 0; text-align: right; font-weight: 500;">${transaction.description}</td>
                        </tr>
                        ` : ''}
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 10px 0; color: #666;">Balance Before</td>
                            <td style="padding: 10px 0; text-align: right; font-weight: 500;">₦${transaction.balance_before.toLocaleString()}</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px 0; color: #666;">Balance After</td>
                            <td style="padding: 10px 0; text-align: right; font-weight: 500; color: #ff5e00;">₦${transaction.balance_after.toLocaleString()}</td>
                        </tr>
                    </table>
                </div>
            </div>
        `;
        
        Swal.fire({
            title: 'Transaction Details',
            html: html,
            confirmButtonColor: '#ff5e00',
            confirmButtonText: 'Close',
            width: '500px'
        });
    }

    // Add funds to wallet
    function addFunds() {
        Swal.fire({
            title: 'Add Funds to Wallet',
            html: `
                <input type="number" id="amount" class="swal2-input" placeholder="Enter amount" min="100" step="100">
                <select id="paymentMethod" class="swal2-input">
                    <option value="bank_transfer">Bank Transfer</option>
                    <option value="card">Credit/Debit Card</option>
                </select>
                <div style="margin-top: 10px; text-align: left; font-size: 13px; color: #666;">
                    <p><i class="fas fa-info-circle" style="color: #ff5e00;"></i> Minimum deposit: ₦100</p>
                    <p><i class="fas fa-clock" style="color: #ff5e00;"></i> Funds are added instantly</p>
                </div>
            `,
            confirmButtonText: 'Add Funds',
            confirmButtonColor: '#ff5e00',
            showCancelButton: true,
            preConfirm: () => {
                const amount = document.getElementById('amount').value;
                if (!amount || amount < 100) {
                    Swal.showValidationMessage('Please enter a valid amount (minimum ₦100)');
                    return false;
                }
                return { amount: parseFloat(amount) };
            }
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Funds Added!',
                    html: `
                        <p>₦${result.value.amount.toLocaleString()} has been added to your wallet</p>
                        <p style="font-size: 13px; color: #666; margin-top: 10px;">Transaction ID: TXN-${Math.random().toString(36).substr(2, 9).toUpperCase()}</p>
                    `,
                    timer: 3000,
                    showConfirmButton: true,
                    confirmButtonColor: '#ff5e00'
                }).then(() => {
                    location.reload();
                });
            }
        });
    }

    // Withdraw funds
    function withdrawFunds(balance) {
        if (balance < 1000) {
            Swal.fire({
                icon: 'warning',
                title: 'Insufficient Balance',
                text: 'Minimum withdrawal amount is ₦1,000',
                confirmButtonColor: '#ff5e00'
            });
            return;
        }
        
        Swal.fire({
            title: 'Withdraw Funds',
            html: `
                <p style="margin-bottom: 15px;">Available balance: <strong>₦${balance.toLocaleString()}</strong></p>
                <input type="number" id="withdraw-amount" class="swal2-input" placeholder="Enter amount" min="1000" max="${balance}" step="100">
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
                <div style="margin-top: 10px; text-align: left; font-size: 13px; color: #666;">
                    <p><i class="fas fa-clock" style="color: #ff5e00;"></i> Withdrawals are processed within 24-48 hours</p>
                </div>
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
                if (amount > balance) {
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
                Swal.fire({
                    icon: 'success',
                    title: 'Withdrawal Request Submitted',
                    html: `
                        <p>Amount: <strong>₦${result.value.amount.toLocaleString()}</strong></p>
                        <p>Bank: ${result.value.bank}</p>
                        <p>Account: ${result.value.account} (${result.value.name})</p>
                        <p style="margin-top: 15px; font-size: 13px; color: #666;">Your withdrawal will be processed within 24-48 hours.</p>
                    `,
                    confirmButtonColor: '#ff5e00'
                });
            }
        });
    }

    // Add payment method
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
                    location.reload();
                });
            }
        });
    }

    // Show payment options
    function showPaymentOptions(methodId, methodType) {
        Swal.fire({
            title: 'Payment Method Options',
            html: `
                <div style="text-align: left;">
                    <button onclick="setDefaultPayment('${methodId}')" style="width: 100%; padding: 12px; margin-bottom: 10px; background: #f5f5f5; border: none; border-radius: 8px; cursor: pointer;">
                        <i class="fas fa-check-circle" style="color: #ff5e00; margin-right: 10px;"></i> Set as Default
                    </button>
                    <button onclick="removePaymentMethod('${methodId}')" style="width: 100%; padding: 12px; background: #fee2e2; border: none; border-radius: 8px; color: #dc2626; cursor: pointer;">
                        <i class="fas fa-trash" style="margin-right: 10px;"></i> Remove Method
                    </button>
                </div>
            `,
            showConfirmButton: false,
            showCloseButton: true
        });
    }

    // Set default payment method
    function setDefaultPayment(methodId) {
        Swal.fire({
            icon: 'success',
            title: 'Default Set',
            text: 'Payment method set as default',
            timer: 1500,
            showConfirmButton: false
        }).then(() => {
            location.reload();
        });
    }

    // Remove payment method
    function removePaymentMethod(methodId) {
        Swal.fire({
            title: 'Remove Payment Method?',
            text: 'Are you sure you want to remove this payment method?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Yes, Remove',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    icon: 'success',
                    title: 'Removed',
                    text: 'Payment method removed successfully',
                    timer: 1500,
                    showConfirmButton: false
                }).then(() => {
                    location.reload();
                });
            }
        });
    }

    // Check notifications
    function checkNotifications() {
        Swal.fire({
            icon: 'info',
            title: 'Notifications',
            html: `
                <div style="text-align: left;">
                    <p>🔔 No new notifications</p>
                </div>
            `,
            confirmButtonColor: '#ff5e00'
        });
    }

    // Show coming soon message
    function showComingSoon(feature) {
        Swal.fire({
            icon: 'info',
            title: 'Coming Soon!',
            text: `${feature} feature will be available in the next update.`,
            confirmButtonColor: '#ff5e00'
        });
    }

    // Responsive view switcher
    function checkScreenSize() {
        const mobileView = document.querySelector('.mobile-view');
        const desktopView = document.querySelector('.desktop-view');
        
        if (window.innerWidth >= 1024) {
            if (mobileView) mobileView.style.display = 'none';
            if (desktopView) desktopView.style.display = 'flex';
        } else {
            if (mobileView) mobileView.style.display = 'block';
            if (desktopView) desktopView.style.display = 'none';
        }
    }

    // Initialize
    document.addEventListener('DOMContentLoaded', function() {
        checkScreenSize();
        window.addEventListener('resize', checkScreenSize);
    });
    </script>
</body>

</html>