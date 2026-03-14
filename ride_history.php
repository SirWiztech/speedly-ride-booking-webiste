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

// Initialize variables
$stats = [];
$rides = [];
$lastRide = null;

// Get user statistics based on role
if ($user_role == 'client') {
    // Client statistics
    $statsQuery = "SELECT 
        COUNT(*) as total_rides,
        COALESCE(SUM(total_fare), 0) as total_spent,
        COALESCE(AVG(dr.rating), 0) as avg_rating_given,
        COUNT(CASE WHEN r.status = 'completed' THEN 1 END) as completed_rides,
        COUNT(CASE WHEN r.status IN ('pending', 'driver_assigned', 'driver_arrived', 'ongoing') THEN 1 END) as upcoming_rides
        FROM rides r
        JOIN client_profiles cp ON r.client_id = cp.id
        LEFT JOIN driver_ratings dr ON r.id = dr.ride_id AND dr.user_id = ?
        WHERE cp.user_id = ?";
    
    $statsStmt = $conn->prepare($statsQuery);
    if ($statsStmt) {
        $statsStmt->bind_param("ss", $user_id, $user_id);
        $statsStmt->execute();
        $statsResult = $statsStmt->get_result();
        $stats = $statsResult->fetch_assoc();
    }
    
    // Get recent rides for client
    $ridesQuery = "SELECT 
        r.*,
        u.full_name as driver_name,
        u.profile_picture_url as driver_photo,
        dv.vehicle_model,
        dv.vehicle_color,
        dv.plate_number,
        dr.rating as user_rating,
        dr.review as user_review,
        DATE_FORMAT(r.created_at, '%M %d, %Y') as formatted_date,
        DATE_FORMAT(r.created_at, '%h:%i %p') as formatted_time
        FROM rides r
        LEFT JOIN driver_profiles dp ON r.driver_id = dp.id
        LEFT JOIN users u ON dp.user_id = u.id
        LEFT JOIN driver_vehicles dv ON dp.id = dv.driver_id AND dv.is_active = 1
        LEFT JOIN driver_ratings dr ON r.id = dr.ride_id AND dr.user_id = ?
        WHERE r.client_id = (SELECT id FROM client_profiles WHERE user_id = ?)
        ORDER BY r.created_at DESC";
    
    $ridesStmt = $conn->prepare($ridesQuery);
    if ($ridesStmt) {
        $ridesStmt->bind_param("ss", $user_id, $user_id);
        $ridesStmt->execute();
        $ridesResult = $ridesStmt->get_result();
    } else {
        $ridesResult = null;
    }
    
} else {
    // Driver statistics
    $statsQuery = "SELECT 
        COUNT(*) as total_rides,
        COALESCE(SUM(driver_payout), 0) as total_earned,
        (SELECT COALESCE(AVG(rating), 0) FROM driver_ratings WHERE driver_id = (SELECT id FROM driver_profiles WHERE user_id = ?)) as avg_rating,
        COUNT(CASE WHEN r.status = 'completed' THEN 1 END) as completed_rides,
        COUNT(CASE WHEN r.status IN ('pending', 'driver_assigned') THEN 1 END) as upcoming_rides
        FROM rides r
        WHERE r.driver_id = (SELECT id FROM driver_profiles WHERE user_id = ?)";
    
    $statsStmt = $conn->prepare($statsQuery);
    if ($statsStmt) {
        $statsStmt->bind_param("ss", $user_id, $user_id);
        $statsStmt->execute();
        $statsResult = $statsStmt->get_result();
        $stats = $statsResult->fetch_assoc();
    }
    
    // Get recent rides for driver
    $ridesQuery = "SELECT 
        r.*,
        u.full_name as client_name,
        u.profile_picture_url as client_photo,
        DATE_FORMAT(r.created_at, '%M %d, %Y') as formatted_date,
        DATE_FORMAT(r.created_at, '%h:%i %p') as formatted_time
        FROM rides r
        JOIN client_profiles cp ON r.client_id = cp.id
        JOIN users u ON cp.user_id = u.id
        WHERE r.driver_id = (SELECT id FROM driver_profiles WHERE user_id = ?)
        ORDER BY r.created_at DESC";
    
    $ridesStmt = $conn->prepare($ridesQuery);
    if ($ridesStmt) {
        $ridesStmt->bind_param("s", $user_id);
        $ridesStmt->execute();
        $ridesResult = $ridesStmt->get_result();
    } else {
        $ridesResult = null;
    }
}

// Get last ride
if ($ridesResult && $ridesResult->num_rows > 0) {
    $ridesResult->data_seek(0);
    $lastRide = $ridesResult->fetch_assoc();
    $ridesResult->data_seek(0); // Reset pointer for the loop
}

// Format numbers
$totalRides = number_format($stats['total_rides'] ?? 0);
$avgRating = number_format($stats['avg_rating_given'] ?? $stats['avg_rating'] ?? 4.9, 1);
$totalSpent = $stats['total_spent'] ?? $stats['total_earned'] ?? 0;
$upcomingRides = $stats['upcoming_rides'] ?? 0;
$completedRides = $stats['completed_rides'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedly | Ride History</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./CSS/ride_history.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body>
    <!-- Dashboard Container -->
    <div class="dashboard-container">

        <!-- MOBILE VIEW -->
        <div class="mobile-view">

            <!-- Ride History Header -->
            <div class="header">
                <div class="user-info">
                    <h1>Ride History</h1>
                    <p>Welcome back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></p>
                </div>
                <button class="notification-btn" onclick="checkNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
            </div>

            <!-- Ride History Stats -->
            <div class="balance-section">
                <div class="balance-amount"><?php echo $totalRides; ?> Rides</div>
                <div class="balance-change">
                    <i class="fas fa-star" style="color: #FFC107;"></i>
                    <span>Avg. Rating: <?php echo $avgRating; ?></span>
                </div>
            </div>

            <!-- Ride History List -->
            <div class="transactions-section">
                <div class="section-header">
                    <div class="section-title">Recent Rides</div>
                    <button class="see-all-btn" onclick="window.location.href='#all'">See All</button>
                </div>
                <div class="transaction-list">
                    <?php if ($ridesResult && $ridesResult->num_rows > 0): ?>
                        <?php while ($ride = $ridesResult->fetch_assoc()): 
                            $date = date('M d, Y', strtotime($ride['created_at']));
                            $time = date('h:i A', strtotime($ride['created_at']));
                            $fare = $ride['total_fare'] ?? 0;
                            $status = $ride['status'] ?? 'pending';
                            
                            // Set icon and color based on ride type
                            $icon = 'fa-car';
                            $bgColor = 'rgba(33, 150, 243, 0.1)';
                            $iconColor = '#2196F3';
                            
                            if ($ride['ride_type'] == 'comfort') {
                                $bgColor = 'rgba(156, 39, 176, 0.1)';
                                $iconColor = '#9C27B0';
                            }
                            
                            // Get location names
                            $pickup = $ride['pickup_address'] ?? 'Pickup location';
                            $destination = $ride['destination_address'] ?? 'Destination';
                            $pickup_short = strlen($pickup) > 15 ? substr($pickup, 0, 15) . '...' : $pickup;
                            $dest_short = strlen($destination) > 15 ? substr($destination, 0, 15) . '...' : $destination;
                        ?>
                        <div class="transaction-item" onclick="viewRideDetails('<?php echo $ride['id']; ?>')">
                            <div class="transaction-info">
                                <div class="transaction-icon" style="background-color: <?php echo $bgColor; ?>; color: <?php echo $iconColor; ?>;">
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <div class="transaction-details">
                                    <h4><?php echo $pickup_short; ?> → <?php echo $dest_short; ?></h4>
                                    <p><?php echo $date; ?> • <?php echo $time; ?></p>
                                </div>
                            </div>
                            <div class="transaction-amount <?php echo $status == 'completed' ? 'positive' : ''; ?>">
                                ₦<?php echo number_format($fare); ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="text-center py-8 text-gray-500">
                            <i class="fas fa-history text-4xl mb-3 opacity-30"></i>
                            <p>No ride history yet</p>
                            <button class="mt-3 text-[#ff5e00] font-medium" onclick="window.location.href='book-ride.php'">Book your first ride</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Book Ride Button -->
            <button class="ride-booking-btn" onclick="window.location.href='book-ride.php'">
                <i class="fas fa-car"></i>
                <span>Book New Ride</span>
            </button>

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
                        <h1>Ride History</h1>
                        <p>Track all your past and upcoming rides</p>
                    </div>
                    <div class="desktop-actions">
                        <button class="notification-btn" onclick="checkNotifications()">
                            <i class="fas fa-bell"></i>
                        </button>
                        <button class="add-money-btn" onclick="window.location.href='book-ride.php'">Book Ride</button>
                    </div>
                </div>

                <!-- Dashboard Grid -->
                <div class="desktop-grid">
                    <!-- Stats Card -->
                    <div class="desktop-card balance-card">
                        <div class="card-header">
                            <h2><?php echo $user_role == 'client' ? 'Total Rides' : 'Total Trips'; ?></h2>
                            <?php if ($completedRides > 10): ?>
                            <div class="reward-badge">
                                <i class="fas fa-gift"></i>
                                <span>Reward Available</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="balance-amount"><?php echo $totalRides; ?></div>
                        <div class="balance-change">
                            <i class="fas fa-star" style="color: #FFC107;"></i>
                            <span>Average Rating: <?php echo $avgRating; ?></span>
                        </div>
                    </div>

                    <!-- Last Ride Summary -->
                    <div class="desktop-card">
                        <div class="card-header">
                            <h2>Last Ride</h2>
                            <?php if ($lastRide): ?>
                            <button class="see-all-btn" onclick="viewRideDetails('<?php echo $lastRide['id']; ?>')">Details</button>
                            <?php endif; ?>
                        </div>
                        <div class="card-display">
                            <div class="card-visuals"></div>
                            <div class="card-details">
                                <?php if ($lastRide): ?>
                                <div class="card-number">
                                    <?php 
                                    $pickup = $lastRide['pickup_address'] ?? 'Ride';
                                    echo strlen($pickup) > 20 ? substr($pickup, 0, 20) . '...' : $pickup;
                                    ?>
                                </div>
                                <div class="card-expiry">
                                    <?php echo date('M d, h:i A', strtotime($lastRide['created_at'])); ?>
                                </div>
                                <?php else: ?>
                                <div class="card-number">No rides yet</div>
                                <div class="card-expiry">Book your first ride</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Ride History List -->
                    <div class="desktop-card large">
                        <div class="card-header">
                            <h2>Recent Rides</h2>
                            <button class="see-all-btn" onclick="window.location.href='#all'">View All</button>
                        </div>
                        <div class="desktop-transactions">
                            <div class="transaction-list">
                                <?php 
                                if ($ridesResult && $ridesResult->num_rows > 0) {
                                    $ridesResult->data_seek(0);
                                    $displayCount = 0;
                                    while ($ride = $ridesResult->fetch_assoc()): 
                                        if ($displayCount++ >= 10) break;
                                        $date = date('M d, h:i A', strtotime($ride['created_at']));
                                        $fare = $ride['total_fare'] ?? 0;
                                        $status = $ride['status'] ?? 'pending';
                                        
                                        // Get location names
                                        $pickup = $ride['pickup_address'] ?? 'Pickup';
                                        $destination = $ride['destination_address'] ?? 'Destination';
                                        $pickup_short = strlen($pickup) > 20 ? substr($pickup, 0, 20) . '...' : $pickup;
                                        $dest_short = strlen($destination) > 20 ? substr($destination, 0, 20) . '...' : $destination;
                                        
                                        // Set icon based on ride type
                                        $icon = 'fa-car';
                                        $bgColor = '#E3F2FD';
                                        $iconColor = '#2196F3';
                                        
                                        if ($ride['ride_type'] == 'comfort') {
                                            $bgColor = '#F3E5F5';
                                            $iconColor = '#9C27B0';
                                        }
                                ?>
                                <div class="transaction-item" onclick="viewRideDetails('<?php echo $ride['id']; ?>')">
                                    <div class="transaction-info">
                                        <div class="transaction-icon" style="background: <?php echo $bgColor; ?>; color: <?php echo $iconColor; ?>;">
                                            <i class="fas <?php echo $icon; ?>"></i>
                                        </div>
                                        <div class="transaction-details">
                                            <h4><?php echo $pickup_short; ?> → <?php echo $dest_short; ?></h4>
                                            <p><?php echo $date; ?></p>
                                        </div>
                                    </div>
                                    <div class="transaction-amount <?php echo $status == 'completed' ? 'positive' : ''; ?>">
                                        ₦<?php echo number_format($fare); ?>
                                    </div>
                                </div>
                                <?php 
                                    endwhile;
                                } else { 
                                ?>
                                <div class="text-center py-4 text-gray-500">
                                    <p>No rides found</p>
                                </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>

                    <!-- Ride Stats Summary -->
                    <div class="desktop-card large">
                        <div class="card-header">
                            <h2>Ride Statistics</h2>
                        </div>
                        <div class="ride-stats" style="padding: 20px;">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $totalRides; ?></div>
                                <div class="stat-label">Total <?php echo $user_role == 'client' ? 'Rides' : 'Trips'; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $completedRides; ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $upcomingRides; ?></div>
                                <div class="stat-label">Upcoming</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">₦<?php echo number_format($totalSpent); ?></div>
                                <div class="stat-label"><?php echo $user_role == 'client' ? 'Total Spent' : 'Total Earned'; ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Ride Booking Section -->
                <div class="ride-booking-desktop">
                    <div class="ride-info">
                        <h2><?php echo $user_role == 'client' ? 'Need another ride?' : 'Ready for more trips?'; ?></h2>
                        <p>Book a ride instantly and enjoy our premium service with safety measures and comfortable vehicles.</p>
                        <div class="ride-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $totalRides; ?></div>
                                <div class="stat-label"><?php echo $user_role == 'client' ? 'Rides Taken' : 'Trips Done'; ?></div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?php echo $avgRating; ?></div>
                                <div class="stat-label">Avg. Rating</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value">₦<?php echo number_format($totalSpent); ?></div>
                                <div class="stat-label"><?php echo $user_role == 'client' ? 'Spent' : 'Earned'; ?></div>
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

    <script src="./JS/ride_history.js"></script>
</body>
</html>   