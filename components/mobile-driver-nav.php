<?php
// Get the current page filename
$current_page = basename($_SERVER['PHP_SELF']);
?>


<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../CSS/client_dashboard.css">
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>


<body>
    <!-- Bottom Navigation -->
    
<!-- Bottom Navigation -->
<div class="bottom-nav">
    <a href="driver_dashboard.php" class="nav-item <?php echo ($current_page == 'driver_dashboard.php') ? 'active' : ''; ?>">
        <i class="fas fa-home nav-icon"></i>
        <span>Home</span>
    </a>
    <a href="book-ride.php" class="nav-item <?php echo ($current_page == 'book-ride.php') ? 'active' : ''; ?>">
        <i class="fas fa-car nav-icon"></i>
        <span>Book ride</span>
    </a>
    <a href="driver_wallet.php" class="nav-item <?php echo ($current_page == 'driver_wallet.php') ? 'active' : ''; ?>">
        <i class="fas fa-wallet nav-icon"></i>
        <span>Wallet</span>
    </a>
    <a href="driver_location.php" class="nav-item <?php echo ($current_page == 'driver_location.php') ? 'active' : ''; ?>">
        <i class="fas fa-map-marker-alt nav-icon"></i>
        <span>Location</span>
    </a>
    <a href="kyc.php" class="nav-item <?php echo ($current_page == 'kyc.php') ? 'active' : ''; ?>">
        <i class="fas fa-id-card nav-icon"></i>
        <span>Kyc</span>
    </a>
    <a href="driver_settings.php" class="nav-item <?php echo ($current_page == 'driver_settings') ? 'active' : ''; ?>">
        <i class="fas fa-user nav-icon"></i>
        <span>Settings</span>
    </a>
</div>

    
</body>