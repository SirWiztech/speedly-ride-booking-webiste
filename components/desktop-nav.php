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

    <!-- Desktop Navigation -->
<div class="desktop-nav">
    <a href="client_dashboard.php" class="desktop-nav-item <?php echo ($current_page == 'client_dashboard.php') ? 'active' : ''; ?>">
        <i class="fas fa-home desktop-nav-icon"></i>
        <span>Dashboard</span>
    </a>
    <a href="book-ride.php" class="desktop-nav-item <?php echo ($current_page == 'book-ride.php') ? 'active' : ''; ?>">
        <i class="fas fa-car desktop-nav-icon"></i>
        <span>Book Ride</span>
    </a>
    <a href="ride_history.php" class="desktop-nav-item <?php echo ($current_page == 'ride_history.php') ? 'active' : ''; ?>">
        <i class="fas fa-history desktop-nav-icon"></i>
        <span>Ride History</span>
    </a>
    <a href="wallet.php" class="desktop-nav-item <?php echo ($current_page == 'wallet.php') ? 'active' : ''; ?>">
        <i class="fas fa-wallet desktop-nav-icon"></i>
        <span>Wallet</span>
    </a>
    <a href="location.php" class="desktop-nav-item <?php echo ($current_page == 'location.php') ? 'active' : ''; ?>">
        <i class="fas fa-map-marker-alt desktop-nav-icon"></i>
        <span>Locations</span>
    </a>
    <a href="settings.php" class="desktop-nav-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
        <i class="fas fa-cog desktop-nav-icon"></i>
        <span>Settings</span>
    </a>
</div>

    
</body>