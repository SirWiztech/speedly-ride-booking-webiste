<?php
session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

require_once __DIR__ . '/SERVER/API/db-connect.php';

// Get real statistics from database
$stats = [];

// Total users
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'client'");
$stats['total_users'] = $result->fetch_assoc()['count'];

// Total drivers
$result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'driver'");
$stats['total_drivers'] = $result->fetch_assoc()['count'];

// Active rides
$result = $conn->query("SELECT COUNT(*) as count FROM rides WHERE status IN ('accepted', 'driver_assigned', 'driver_arrived', 'ongoing')");
$stats['active_rides'] = $result->fetch_assoc()['count'];

// Completed rides
$result = $conn->query("SELECT COUNT(*) as count FROM rides WHERE status = 'completed'");
$stats['completed_rides'] = $result->fetch_assoc()['count'];

// Total revenue
$result = $conn->query("SELECT SUM(total_fare) as total FROM rides WHERE payment_status = 'paid'");
$stats['total_revenue'] = $result->fetch_assoc()['total'] ?? 0;

// Pending withdrawals
$result = $conn->query("SELECT SUM(amount) as total FROM driver_withdrawals WHERE status = 'pending'");
$stats['pending_withdrawals'] = $result->fetch_assoc()['total'] ?? 0;
$result = $conn->query("SELECT COUNT(*) as count FROM driver_withdrawals WHERE status = 'pending'");
$stats['pending_count'] = $result->fetch_assoc()['count'];

// Get recent withdrawals
$recentWithdrawals = $conn->query("
    SELECT dw.*, u.full_name as driver_name 
    FROM driver_withdrawals dw 
    JOIN driver_profiles dp ON dw.driver_id = dp.id 
    JOIN users u ON dp.user_id = u.id 
    ORDER BY dw.created_at DESC LIMIT 5
");

// Get top performers
$topPerformers = $conn->query("
    SELECT u.full_name, 
           COUNT(r.id) as ride_count, 
           AVG(dr.rating) as avg_rating,
           SUM(r.driver_payout) as total_earnings
    FROM driver_profiles dp
    JOIN users u ON dp.user_id = u.id
    LEFT JOIN rides r ON dp.id = r.driver_id AND r.status = 'completed'
    LEFT JOIN driver_ratings dr ON r.id = dr.ride_id
    GROUP BY dp.id
    ORDER BY ride_count DESC
    LIMIT 5
");

// Get recent users
$recentUsers = $conn->query("
    SELECT id, full_name, email, phone_number, role, created_at, is_verified 
    FROM users 
    ORDER BY created_at DESC 
    LIMIT 10
");

// Get recent drivers with KYC status
$recentDrivers = $conn->query("
    SELECT u.id, u.full_name, u.email, u.phone_number, u.created_at,
           dp.verification_status, dp.driver_status,
           COUNT(dv.id) as vehicle_count
    FROM users u
    JOIN driver_profiles dp ON u.id = dp.user_id
    LEFT JOIN driver_vehicles dv ON dp.id = dv.driver_id
    WHERE u.role = 'driver'
    GROUP BY u.id
    ORDER BY u.created_at DESC
    LIMIT 10
");

// Get recent rides
$recentRides = $conn->query("
    SELECT r.*, 
           c.full_name as client_name,
           d.full_name as driver_name
    FROM rides r
    LEFT JOIN client_profiles cp ON r.client_id = cp.id
    LEFT JOIN users c ON cp.user_id = c.id
    LEFT JOIN driver_profiles dp ON r.driver_id = dp.id
    LEFT JOIN users d ON dp.user_id = d.id
    ORDER BY r.created_at DESC
    LIMIT 10
");

// Get pending KYC approvals
$pendingKyc = $conn->query("
    SELECT dk.*, u.full_name, u.email
    FROM driver_kyc_documents dk
    JOIN driver_profiles dp ON dk.driver_id = dp.id
    JOIN users u ON dp.user_id = u.id
    WHERE dk.verification_status = 'pending'
    ORDER BY dk.created_at DESC
");

// Get recent disputes
$recentDisputes = $conn->query("
    SELECT d.*, 
           u1.full_name as raiser_name,
           u2.full_name as against_name
    FROM disputes d
    LEFT JOIN users u1 ON d.raised_by = u1.id
    LEFT JOIN users u2 ON d.raised_against = u2.id
    ORDER BY d.created_at DESC
    LIMIT 5
");

// Get system settings
$settings = [];
$settingsResult = $conn->query("SELECT setting_key, setting_value FROM system_settings");
while ($row = $settingsResult->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

// Get daily revenue for chart (last 7 days)
$revenueData = [];
$revenueLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $revenueLabels[] = date('D', strtotime($date));
    
    $result = $conn->query("SELECT SUM(total_fare) as total FROM rides WHERE DATE(created_at) = '$date' AND payment_status = 'paid'");
    $revenueData[] = $result->fetch_assoc()['total'] ?? 0;
}

// Get activity logs
$activityLogs = $conn->query("
    SELECT al.*, u.full_name as admin_name,
           INET_NTOA(INET_ATON(al.ip_address)) as ip_formatted
    FROM admin_activity_logs al
    JOIN users u ON al.admin_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 10
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=yes">
    <title>Speedly | Admin Dashboard</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Boxicons -->
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="stylesheet" href="./CSS/admin_dashbboard.css">
    <style>
        .document-preview {
            width: 100px;
            height: 60px;
            object-fit: cover;
            border-radius: 5px;
            cursor: pointer;
            border: 1px solid #e0e0e0;
        }
        .dispute-message {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 10px;
            margin-bottom: 10px;
        }
        .dispute-message.admin {
            background: #fff3e0;
            border-left: 4px solid #ff5e00;
        }
        .badge {
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
        }
        .badge-urgent { background: #ffebee; color: #c62828; }
        .badge-high { background: #fff3e0; color: #ef6c00; }
        .badge-medium { background: #e8f5e9; color: #2e7d32; }
        .badge-low { background: #e3f2fd; color: #1565c0; }
        .message-count {
            background: #ff5e00;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        <div class="desktop-view">
            <!-- SIDEBAR -->
            <div class="desktop-sidebar">
                <div class="logo-container" style="padding: 0 16px 20px;">
                    <img src="./main-assets/logo-no-background.png" alt="Speedly" class="logo-image" style="max-width: 140px;">
                </div>

                <div class="desktop-nav">
                    <a href="#" style="text-decoration: none;" class="desktop-nav-item active" data-page="dashboard"><i class="fas fa-home desktop-nav-icon"></i><span>Dashboard</span></a>
                    <a href="#" style="text-decoration: none;" class="desktop-nav-item" data-page="users"><i class="fas fa-users desktop-nav-icon"></i><span>Users</span></a>
                    <a href="#" style="text-decoration: none;" class="desktop-nav-item" data-page="drivers"><i class="fas fa-id-card desktop-nav-icon"></i><span>Drivers</span></a>
                    <a href="#" style="text-decoration: none;" class="desktop-nav-item" data-page="rides"><i class="fas fa-car desktop-nav-icon"></i><span>Rides</span></a>
                    <a href="#" style="text-decoration: none;" class="desktop-nav-item" data-page="payments"><i class="fas fa-credit-card desktop-nav-icon"></i><span>Payments</span></a>
                    <a href="#" style="text-decoration: none;" class="desktop-nav-item" data-page="wallets"><i class="fas fa-wallet desktop-nav-icon"></i><span>Wallets</span></a>
                    <a href="#" style="text-decoration: none;" class="desktop-nav-item" data-page="kyc"><i class="fas fa-file-alt desktop-nav-icon"></i><span>KYC Approvals</span></a>
                    <a href="#" style="text-decoration: none;" class="desktop-nav-item" data-page="disputes"><i class="fas fa-exclamation-triangle desktop-nav-icon"></i><span>Disputes</span></a>
                    <a href="#" style="text-decoration: none;" class="desktop-nav-item" data-page="reports"><i class="fas fa-chart-line desktop-nav-icon"></i><span>Reports</span></a>
                    <a href="#" style="text-decoration: none;" class="desktop-nav-item" data-page="settings"><i class="fas fa-cog desktop-nav-icon"></i><span>Settings</span></a>
                    <a href="#" style="text-decoration: none;" class="desktop-nav-item" data-page="activity"><i class="fas fa-history desktop-nav-icon"></i><span>Activity Log</span></a>
                </div>
                
                <div class="user-profile">
                    <div class="profile-avatar"><?php echo strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)); ?></div>
                    <div class="profile-info">
                        <h3><?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin User'); ?></h3>
                        <p>Super Administrator</p>
                    </div>
                    <button class="logout-btn" id="logoutBtn" onclick="confirmLogout()"><i class="fas fa-sign-out-alt"></i></button>
                </div>
            </div>

            <!-- MAIN CONTENT -->
            <div class="desktop-main" id="mainContent">
                <div class="desktop-header">
                    <div class="desktop-title" id="pageTitle">
                        <h1>Dashboard Overview</h1>
                        <p>Welcome back, <?php echo htmlspecialchars($_SESSION['admin_name'] ?? 'Admin'); ?>! Here's what's happening today.</p>
                    </div>
                    <div class="desktop-actions">
                        <div class="session-timer" id="sessionTimer"><i class="fas fa-clock"></i><span id="timerDisplay">Session: 29:59</span></div>
                        <button class="notification-btn" id="notificationBtn"><i class="fas fa-bell"></i><span class="notification-badge" id="notificationBadge">0</span></button>
                    </div>
                </div>

                <!-- DASHBOARD PAGE -->
                <div id="dashboard-page" class="page active-page">
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon users-icon"><i class="fas fa-users"></i></div>
                            <div class="stat-details">
                                <h3>Total Users</h3>
                                <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                                <div class="stat-change positive"><i class="fas fa-arrow-up"></i> Active</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon drivers-icon"><i class="fas fa-id-card"></i></div>
                            <div class="stat-details">
                                <h3>Total Drivers</h3>
                                <div class="stat-value"><?php echo number_format($stats['total_drivers']); ?></div>
                                <div class="stat-change positive"><i class="fas fa-arrow-up"></i> Registered</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon active-rides-icon"><i class="fas fa-car"></i></div>
                            <div class="stat-details">
                                <h3>Active Rides</h3>
                                <div class="stat-value"><?php echo $stats['active_rides']; ?></div>
                                <div class="stat-change">Currently on road</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon completed-icon"><i class="fas fa-check-circle"></i></div>
                            <div class="stat-details">
                                <h3>Completed Rides</h3>
                                <div class="stat-value"><?php echo number_format($stats['completed_rides']); ?></div>
                                <div class="stat-change positive">All time</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon revenue-icon"><i class="fas fa-naira-sign"></i></div>
                            <div class="stat-details">
                                <h3>Total Revenue</h3>
                                <div class="stat-value">₦<?php echo number_format($stats['total_revenue']); ?></div>
                                <div class="stat-change positive">+22% this month</div>
                            </div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-icon pending-icon"><i class="fas fa-clock"></i></div>
                            <div class="stat-details">
                                <h3>Pending Withdrawals</h3>
                                <div class="stat-value">₦<?php echo number_format($stats['pending_withdrawals']); ?></div>
                                <div class="stat-change negative"><?php echo $stats['pending_count']; ?> requests</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="dashboard-grid">
                        <div class="desktop-card large">
                            <div class="card-header">
                                <h2>Revenue Overview (Last 7 Days)</h2>
                                <div class="chart-filters">
                                    <button class="filter-btn active" onclick="filterChart('daily')">Daily</button>
                                    <button class="filter-btn" onclick="filterChart('weekly')">Weekly</button>
                                    <button class="filter-btn" onclick="filterChart('monthly')">Monthly</button>
                                </div>
                            </div>
                            <div class="chart-container">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                        
                        <div class="desktop-card">
                            <div class="card-header">
                                <h2>Recent Withdrawals</h2>
                                <button class="see-all-btn filter-btn" onclick="showAllWithdrawals()">View All</button>
                            </div>
                            <div class="withdrawal-list" id="recentWithdrawals">
                                <?php while ($withdrawal = $recentWithdrawals->fetch_assoc()): ?>
                                <div class="withdrawal-item">
                                    <div class="withdrawal-info">
                                        <h4><?php echo htmlspecialchars($withdrawal['driver_name']); ?></h4>
                                        <p>Requested: ₦<?php echo number_format($withdrawal['amount']); ?></p>
                                    </div>
                                    <span class="status-badge <?php echo $withdrawal['status']; ?>"><?php echo ucfirst($withdrawal['status']); ?></span>
                                </div>
                                <?php endwhile; ?>
                                <?php if ($recentWithdrawals->num_rows == 0): ?>
                                <div class="withdrawal-item">
                                    <div class="withdrawal-info">
                                        <p>No recent withdrawals</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="desktop-card">
                            <div class="card-header">
                                <h2>Top Performers</h2>
                            </div>
                            <div class="performer-list" id="topPerformers">
                                <?php 
                                $rank = 1;
                                while ($performer = $topPerformers->fetch_assoc()): 
                                ?>
                                <div class="performer-item">
                                    <div class="performer-rank"><?php echo $rank++; ?></div>
                                    <div class="performer-info">
                                        <h4><?php echo htmlspecialchars($performer['full_name']); ?></h4>
                                        <p><?php echo $performer['ride_count'] ?? 0; ?> rides • <?php echo number_format($performer['avg_rating'] ?? 0, 1); ?> ★</p>
                                    </div>
                                    <div class="performer-earnings">₦<?php echo number_format($performer['total_earnings'] ?? 0); ?></div>
                                </div>
                                <?php endwhile; ?>
                                <?php if ($topPerformers->num_rows == 0): ?>
                                <div class="performer-item">
                                    <div class="performer-info">
                                        <p>No data available</p>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- USERS PAGE -->
                <div id="users-page" class="page">
                    <div class="management-header">
                        <h2>User Management</h2>
                        <div class="search-bar">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search users..." id="userSearch" onkeyup="searchTable('usersTableBody', this.value)">
                        </div>
                        <div class="filter-tabs">
                            <button class="filter-tab active" data-filter="all" onclick="filterUsers('all')">All</button>
                            <button class="filter-tab" data-filter="verified" onclick="filterUsers('verified')">Verified</button>
                            <button class="filter-tab" data-filter="unverified" onclick="filterUsers('unverified')">Unverified</button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Joined</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="usersTableBody">
                                <?php while ($user = $recentUsers->fetch_assoc()): ?>
                                <tr data-user-id="<?php echo $user['id']; ?>" data-status="<?php echo $user['is_verified'] ? 'verified' : 'unverified'; ?>">
                                    <td><?php echo substr($user['id'], 0, 8); ?>...</td>
                                    <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['phone_number']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $user['is_verified'] ? 'approved' : 'pending'; ?>">
                                            <?php echo $user['is_verified'] ? 'Verified' : 'Unverified'; ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <button class="action-icon-btn view-btn" onclick="viewUser('<?php echo $user['id']; ?>')"><i class="fas fa-eye"></i></button>
                                        <button class="action-icon-btn suspend-btn" onclick="toggleUserStatus('<?php echo $user['id']; ?>')"><i class="fas fa-ban"></i></button>
                                        <button class="action-icon-btn delete-btn" onclick="deleteUser('<?php echo $user['id']; ?>')"><i class="fas fa-trash"></i></button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- DRIVERS PAGE -->
                <div id="drivers-page" class="page">
                    <div class="management-header">
                        <h2>Driver Management</h2>
                        <div class="search-bar">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search drivers..." id="driverSearch" onkeyup="searchTable('driversTableBody', this.value)">
                        </div>
                        <div class="filter-tabs">
                            <button class="filter-tab active" data-filter="all" onclick="filterDrivers('all')">All</button>
                            <button class="filter-tab" data-filter="pending" onclick="filterDrivers('pending')">Pending</button>
                            <button class="filter-tab" data-filter="approved" onclick="filterDrivers('approved')">Approved</button>
                            <button class="filter-tab" data-filter="suspended" onclick="filterDrivers('suspended')">Suspended</button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Vehicles</th>
                                    <th>KYC Status</th>
                                    <th>Driver Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="driversTableBody">
                                <?php while ($driver = $recentDrivers->fetch_assoc()): ?>
                                <tr data-driver-id="<?php echo $driver['id']; ?>" data-status="<?php echo $driver['verification_status']; ?>">
                                    <td><?php echo substr($driver['id'], 0, 8); ?>...</td>
                                    <td><?php echo htmlspecialchars($driver['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($driver['email']); ?></td>
                                    <td><?php echo htmlspecialchars($driver['phone_number']); ?></td>
                                    <td><?php echo $driver['vehicle_count']; ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $driver['verification_status']; ?>">
                                            <?php echo ucfirst($driver['verification_status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $driver['driver_status']; ?>">
                                            <?php echo ucfirst($driver['driver_status']); ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <button class="action-icon-btn view-btn" onclick="viewDriver('<?php echo $driver['id']; ?>')"><i class="fas fa-eye"></i></button>
                                        <button class="action-icon-btn approve-btn" onclick="approveDriver('<?php echo $driver['id']; ?>')"><i class="fas fa-check"></i></button>
                                        <button class="action-icon-btn suspend-btn" onclick="suspendDriver('<?php echo $driver['id']; ?>')"><i class="fas fa-ban"></i></button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- RIDES PAGE -->
                <div id="rides-page" class="page">
                    <div class="management-header">
                        <h2>Ride Management</h2>
                        <div class="search-bar">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search rides..." id="rideSearch" onkeyup="searchTable('ridesTableBody', this.value)">
                        </div>
                        <div class="filter-tabs">
                            <button class="filter-tab active" data-filter="all" onclick="filterRides('all')">All</button>
                            <button class="filter-tab" data-filter="pending" onclick="filterRides('pending')">Pending</button>
                            <button class="filter-tab" data-filter="ongoing" onclick="filterRides('ongoing')">Ongoing</button>
                            <button class="filter-tab" data-filter="completed" onclick="filterRides('completed')">Completed</button>
                            <button class="filter-tab" data-filter="cancelled" onclick="filterRides('cancelled')">Cancelled</button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Ride #</th>
                                    <th>Client</th>
                                    <th>Driver</th>
                                    <th>Pickup</th>
                                    <th>Destination</th>
                                    <th>Fare</th>
                                    <th>Status</th>
                                    <th>Payment</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="ridesTableBody">
                                <?php while ($ride = $recentRides->fetch_assoc()): ?>
                                <tr data-ride-id="<?php echo $ride['id']; ?>" data-status="<?php echo $ride['status']; ?>">
                                    <td><?php echo $ride['ride_number']; ?></td>
                                    <td><?php echo htmlspecialchars($ride['client_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($ride['driver_name'] ?? 'Unassigned'); ?></td>
                                    <td><?php echo substr($ride['pickup_address'], 0, 20); ?>...</td>
                                    <td><?php echo substr($ride['destination_address'], 0, 20); ?>...</td>
                                    <td>₦<?php echo number_format($ride['total_fare']); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo str_replace('_', '-', $ride['status']); ?>">
                                            <?php echo str_replace('_', ' ', $ride['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status-badge <?php echo $ride['payment_status']; ?>">
                                            <?php echo ucfirst($ride['payment_status']); ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <button class="action-icon-btn view-btn" onclick="viewRide('<?php echo $ride['id']; ?>')"><i class="fas fa-eye"></i></button>
                                        <button class="action-icon-btn" onclick="cancelRide('<?php echo $ride['id']; ?>')"><i class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- PAYMENTS PAGE -->
                <div id="payments-page" class="page">
                    <div class="management-header">
                        <h2>Payment Transactions</h2>
                        <div class="search-bar">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search transactions..." id="paymentSearch" onkeyup="filterPayments(this.value)">
                        </div>
                        <div class="filter-tabs">
                            <button class="filter-tab active" onclick="filterPayments('all')">All</button>
                            <button class="filter-tab" onclick="filterPayments('success')">Success</button>
                            <button class="filter-tab" onclick="filterPayments('pending')">Pending</button>
                            <button class="filter-tab" onclick="filterPayments('failed')">Failed</button>
                        </div>
                    </div>
                    
                    <div class="payment-summary" id="paymentStats">
                        <!-- Will be populated by JavaScript -->
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>User</th>
                                    <th>Amount</th>
                                    <th>Method</th>
                                    <th>Ride #</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="paymentsTableBody">
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- WALLETS PAGE -->
                <div id="wallets-page" class="page">
                    <div class="management-header">
                        <h2>Wallet Management</h2>
                    </div>
                    
                    <div class="wallet-summary" id="walletStats">
                        <!-- Will be populated by JavaScript -->
                    </div>
                    
                    <div class="desktop-card" style="margin-bottom: 25px;">
                        <div class="card-header">
                            <h3>Pending Withdrawals</h3>
                            <select id="withdrawalStatus" onchange="filterWithdrawals(this.value)" style="padding: 8px; border-radius: 10px; border: 1px solid #e0e0e0;">
                                <option value="all">All</option>
                                <option value="pending">Pending</option>
                                <option value="approved">Approved</option>
                                <option value="paid">Paid</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Driver</th>
                                        <th>Amount</th>
                                        <th>Bank</th>
                                        <th>Account</th>
                                        <th>Requested</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="withdrawalsTableBody">
                                    <!-- Will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <div class="desktop-card">
                        <div class="card-header">
                            <h3>Recent Transactions</h3>
                        </div>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>User</th>
                                        <th>Type</th>
                                        <th>Amount</th>
                                        <th>Reference</th>
                                        <th>Description</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody id="transactionsTableBody">
                                    <!-- Will be populated by JavaScript -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- KYC APPROVALS PAGE -->
                <div id="kyc-page" class="page">
                    <div class="management-header">
                        <h2>KYC Document Approvals</h2>
                        <div class="filter-tabs">
                            <button class="filter-tab active" data-filter="all" onclick="filterKyc('all')">All</button>
                            <button class="filter-tab" data-filter="pending" onclick="filterKyc('pending')">Pending</button>
                            <button class="filter-tab" data-filter="approved" onclick="filterKyc('approved')">Approved</button>
                            <button class="filter-tab" data-filter="rejected" onclick="filterKyc('rejected')">Rejected</button>
                        </div>
                    </div>
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Driver</th>
                                    <th>Document Preview</th>
                                    <th>Document Type</th>
                                    <th>Uploaded</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="kycTableBody">
                                <?php while ($kyc = $pendingKyc->fetch_assoc()): ?>
                                <tr data-kyc-id="<?php echo $kyc['id']; ?>" data-status="<?php echo $kyc['verification_status']; ?>">
                                    <td><?php echo htmlspecialchars($kyc['full_name']); ?></td>
                                    <td>
                                        <img src="SERVER/API/view_document.php?kyc_id=<?php echo $kyc['id']; ?>" 
                                             class="document-preview" 
                                             onclick="viewKYCDocument('<?php echo $kyc['id']; ?>')"
                                             onerror="this.src='main-assets/default-document.png'">
                                    </td>
                                    <td><?php echo str_replace('_', ' ', $kyc['document_type']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', strtotime($kyc['created_at'])); ?></td>
                                    <td>
                                        <span class="status-badge <?php echo $kyc['verification_status']; ?>">
                                            <?php echo ucfirst($kyc['verification_status']); ?>
                                        </span>
                                    </td>
                                    <td class="actions-cell">
                                        <button class="action-icon-btn view-btn" onclick="viewKYCDocument('<?php echo $kyc['id']; ?>')"><i class="fas fa-eye"></i></button>
                                        <button class="action-icon-btn approve-btn" onclick="approveKYC('<?php echo $kyc['id']; ?>')"><i class="fas fa-check"></i></button>
                                        <button class="action-icon-btn reject-btn" onclick="rejectKYC('<?php echo $kyc['id']; ?>')"><i class="fas fa-times"></i></button>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                                <?php if ($pendingKyc->num_rows == 0): ?>
                                <tr>
                                    <td colspan="6" style="text-align: center;">No pending KYC approvals</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- DISPUTES PAGE -->
                <div id="disputes-page" class="page">
                    <div class="management-header">
                        <h2>Dispute Management</h2>
                        <div class="filter-tabs">
                            <button class="filter-tab active" onclick="filterDisputes('all')">All</button>
                            <button class="filter-tab" onclick="filterDisputes('open')">Open</button>
                            <button class="filter-tab" onclick="filterDisputes('investigating')">Investigating</button>
                            <button class="filter-tab" onclick="filterDisputes('resolved')">Resolved</button>
                        </div>
                    </div>
                    
                    <div class="stats-grid" style="margin-bottom: 25px;" id="disputeStats">
                        <!-- Will be populated by JavaScript -->
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Dispute #</th>
                                    <th>Ride #</th>
                                    <th>Raised By</th>
                                    <th>Against</th>
                                    <th>Type</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Messages</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="disputesTableBody">
                                <!-- Will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- REPORTS PAGE -->
                <div id="reports-page" class="page">
                    <div class="management-header">
                        <h2>Reports & Analytics</h2>
                        <div class="filter-tabs">
                            <select id="reportPeriod" style="padding: 10px; border-radius: 10px; border: 1px solid #e0e0e0;" onchange="loadReport()">
                                <option value="daily">Daily</option>
                                <option value="weekly">Weekly</option>
                                <option value="monthly">Monthly</option>
                                <option value="yearly">Yearly</option>
                            </select>
                            <button class="export-btn" onclick="exportReport()"><i class="fas fa-download"></i> Export</button>
                            <button class="print-btn" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                        </div>
                    </div>
                    
                    <div class="reports-grid" id="reportContent">
                        <!-- Will be populated by JavaScript -->
                    </div>
                </div>

                <!-- ACTIVITY LOG PAGE -->
                <div id="activity-page" class="page">
                    <div class="management-header">
                        <h2>Admin Activity Log</h2>
                        <div class="search-bar">
                            <i class="fas fa-search"></i>
                            <input type="text" placeholder="Search logs..." id="activitySearch" onkeyup="filterActivityLogs(this.value)">
                        </div>
                    </div>
                    
                    <div class="stats-grid" style="margin-bottom: 25px;" id="activityStats">
                        <!-- Will be populated by JavaScript -->
                    </div>
                    
                    <div class="table-container">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Admin</th>
                                    <th>Action</th>
                                    <th>Entity Type</th>
                                    <th>Entity ID</th>
                                    <th>IP Address</th>
                                    <th>Changes</th>
                                </tr>
                            </thead>
                            <tbody id="activityTableBody">
                                <?php while ($log = $activityLogs->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($log['admin_name']); ?></td>
                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td><?php echo htmlspecialchars($log['entity_type']); ?></td>
                                    <td><?php echo $log['entity_id'] ? substr($log['entity_id'], 0, 8) . '...' : 'N/A'; ?></td>
                                    <td>
                                        <?php 
                                        $ip = $log['ip_address'] ?? $log['ip_formatted'] ?? 'N/A';
                                        if ($ip && $ip !== 'N/A') {
                                            echo '<i class="fas fa-network-wired" style="color: #ff5e00; margin-right: 5px;"></i>' . htmlspecialchars($ip);
                                        } else {
                                            echo 'N/A';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($log['old_values'] && $log['new_values']): ?>
                                        <button class="action-icon-btn" onclick="viewChanges('<?php echo $log['id']; ?>')" title="View Changes">
                                            <i class="fas fa-code-branch"></i>
                                        </button>
                                        <?php else: ?>
                                        -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SETTINGS PAGE -->
                <div id="settings-page" class="page">
                    <div class="management-header">
                        <h2>System Settings</h2>
                    </div>
                    <div class="settings-grid">
                        <div class="desktop-card">
                            <h3>Fare Settings</h3>
                            <div class="settings-form">
                                <div class="form-group">
                                    <label>Base Fare (₦)</label>
                                    <input type="number" id="baseFare" class="settings-input" value="<?php echo $settings['base_fare'] ?? 500; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Rate per KM (₦)</label>
                                    <input type="number" id="ratePerKm" class="settings-input" value="<?php echo $settings['rate_per_km'] ?? 150; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Surge Multiplier</label>
                                    <input type="number" step="0.1" id="surgeMultiplier" class="settings-input" value="<?php echo $settings['surge_multiplier'] ?? 1.5; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Platform Commission (%)</label>
                                    <input type="number" id="commission" class="settings-input" value="<?php echo $settings['platform_commission'] ?? 20; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="desktop-card">
                            <h3>Currency Settings</h3>
                            <div class="settings-form">
                                <div class="form-group">
                                    <label>Currency Symbol</label>
                                    <input type="text" id="currencySymbol" class="settings-input" value="<?php echo $settings['currency_symbol'] ?? '₦'; ?>">
                                </div>
                                <div class="form-group">
                                    <label>Currency Code</label>
                                    <input type="text" id="currencyCode" class="settings-input" value="<?php echo $settings['currency_code'] ?? 'NGN'; ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="desktop-card">
                            <h3>System Settings</h3>
                            <div class="settings-form">
                                <div class="form-group checkbox-group">
                                    <label>
                                        <input type="checkbox" id="surgePricing" <?php echo ($settings['enable_surge_pricing'] ?? 'true') == 'true' ? 'checked' : ''; ?>>
                                        Enable Surge Pricing
                                    </label>
                                </div>
                                <div class="form-group checkbox-group">
                                    <label>
                                        <input type="checkbox" id="driverApproval" <?php echo ($settings['require_driver_approval'] ?? 'true') == 'true' ? 'checked' : ''; ?>>
                                        Require Driver Approval
                                    </label>
                                </div>
                                <div class="form-group checkbox-group">
                                    <label>
                                        <input type="checkbox" id="maintenanceMode" <?php echo ($settings['maintenance_mode'] ?? 'false') == 'true' ? 'checked' : ''; ?>>
                                        Maintenance Mode
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label>Session Timeout (minutes)</label>
                                    <input type="number" id="sessionTimeout" class="settings-input" value="<?php echo $settings['session_timeout'] ?? 30; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                    <button class="save-settings-btn" onclick="saveSettings()">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal" id="userModal">
        <div class="modal-content">
            <h3>User Details</h3>
            <div id="userDetails"></div>
            <div class="modal-buttons">
                <button class="cancel-btn" onclick="closeModal('userModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Driver Details Modal -->
    <div class="modal" id="driverModal">
        <div class="modal-content">
            <h3>Driver Details</h3>
            <div id="driverDetails"></div>
            <div class="modal-buttons">
                <button class="cancel-btn" onclick="closeModal('driverModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Ride Details Modal -->
    <div class="modal" id="rideModal">
        <div class="modal-content">
            <h3>Ride Details</h3>
            <div id="rideDetails"></div>
            <div class="modal-buttons">
                <button class="cancel-btn" onclick="closeModal('rideModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Document Viewer Modal -->
    <div class="modal" id="documentModal">
        <div class="modal-content" style="max-width: 800px;">
            <h3>Document Viewer</h3>
            <div id="documentViewer" style="min-height: 400px; text-align: center;">
                <img id="documentImage" src="" alt="Document" style="max-width: 100%; max-height: 500px;">
            </div>
            <div class="modal-buttons">
                <button class="cancel-btn" onclick="closeModal('documentModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- Dispute Messages Modal -->
    <div class="modal" id="disputeModal">
        <div class="modal-content" style="max-width: 600px;">
            <h3>Dispute Messages</h3>
            <div id="disputeMessages" style="max-height: 400px; overflow-y: auto; margin-bottom: 20px;"></div>
            <textarea id="newDisputeMessage" placeholder="Type your message..." style="width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 10px; margin-bottom: 10px;"></textarea>
            <div class="modal-buttons">
                <button class="cancel-btn" onclick="closeModal('disputeModal')">Close</button>
                <button class="save-btn" onclick="sendDisputeMessage()">Send Message</button>
            </div>
        </div>
    </div>

    <!-- Changes Viewer Modal -->
    <div class="modal" id="changesModal">
        <div class="modal-content" style="max-width: 600px;">
            <h3>Changes</h3>
            <div id="changesContent"></div>
            <div class="modal-buttons">
                <button class="cancel-btn" onclick="closeModal('changesModal')">Close</button>
            </div>
        </div>
    </div>

    <script src="./JS/admin_dashboard.js"></script>
</body>
</html>
revenue