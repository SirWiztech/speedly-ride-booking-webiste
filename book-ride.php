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

// Get saved locations
$locationsQuery = "SELECT * FROM saved_locations WHERE user_id = ?";
$locationsStmt = $conn->prepare($locationsQuery);
$locationsStmt->bind_param("s", $user_id);
$locationsStmt->execute();
$locationsResult = $locationsStmt->get_result();

// Check if user has a client profile
$profileQuery = "SELECT id FROM client_profiles WHERE user_id = ?";
$profileStmt = $conn->prepare($profileQuery);
$profileStmt->bind_param("s", $user_id);
$profileStmt->execute();
$profileResult = $profileStmt->get_result();

if ($profileResult->num_rows == 0) {
    // Create client profile if not exists
    $createProfile = "INSERT INTO client_profiles (id, user_id, membership_tier, created_at) VALUES (UUID(), ?, 'basic', NOW())";
    $createStmt = $conn->prepare($createProfile);
    $createStmt->bind_param("s", $user_id);
    $createStmt->execute();
}

// Google Maps API Key
$googleMapsApiKey = 'AIzaSyB1tM_s2w8JWfnIoUTAzJNpbblU-eZiC30';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Speedly | Book a Ride with Map</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="CSS/book-ride.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- Google Maps API with Places and Geometry libraries -->
    <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo $googleMapsApiKey; ?>&libraries=places,geometry,marker&v=weekly"></script>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Map Location Picker Styles */
        .map-picker-container {
            position: relative;
            width: 100%;
            height: 450px;
            border-radius: 20px;
            overflow: hidden;
            margin-bottom: 20px;
            border: 3px solid #ff5e00;
            box-shadow: 0 10px 30px rgba(255, 94, 0, 0.2);
        }

        #map-mobile,
        #map-desktop {
            width: 100%;
            height: 100%;
        }

        .map-overlay {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            display: flex;
            gap: 10px;
            z-index: 10;
            flex-wrap: wrap;
        }

        .mode-selector {
            display: flex;
            background: white;
            border-radius: 50px;
            padding: 5px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
            flex: 1;
            max-width: 300px;
        }

        .mode-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 45px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            background: transparent;
            color: #666;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .mode-btn.pickup.active {
            background: #4CAF50;
            color: white;
        }

        .mode-btn.destination.active {
            background: #F44336;
            color: white;
        }

        .search-box {
            flex: 2;
            min-width: 250px;
            background: white;
            border-radius: 50px;
            padding: 5px 20px;
            display: flex;
            align-items: center;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        }

        .search-box i {
            color: #ff5e00;
            margin-right: 10px;
        }

        .search-box input {
            flex: 1;
            border: none;
            outline: none;
            font-size: 14px;
            padding: 12px 0;
            background: transparent;
        }

        .location-status {
            position: absolute;
            bottom: 20px;
            left: 20px;
            right: 20px;
            display: flex;
            gap: 15px;
            z-index: 10;
            flex-wrap: wrap;
        }

        .location-card {
            background: white;
            border-radius: 16px;
            padding: 15px;
            flex: 1;
            min-width: 250px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            border-left: 5px solid #4CAF50;
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.95);
        }

        .location-card.destination {
            border-left-color: #F44336;
        }

        .location-card .label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .location-card .address {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            word-break: break-word;
            line-height: 1.4;
        }

        .location-card .coords {
            font-size: 11px;
            color: #999;
            font-family: monospace;
            margin-bottom: 12px;
        }

        .location-card .actions {
            display: flex;
            gap: 10px;
        }

        .location-card .actions button {
            flex: 1;
            padding: 8px;
            border: none;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-clear {
            background: #f5f5f5;
            color: #666;
        }

        .btn-clear:hover {
            background: #eee;
        }

        .btn-confirm {
            background: #ff5e00;
            color: white;
        }

        .btn-confirm:hover {
            background: #e65500;
            transform: translateY(-2px);
        }

        .btn-confirm:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .saved-locations-section {
            margin-top: 25px;
            padding: 0 5px;
        }

        .saved-locations-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 15px;
        }

        .saved-location-chip {
            background: #f8f8f8;
            padding: 15px 12px;
            border-radius: 12px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 2px solid transparent;
        }

        .saved-location-chip:hover {
            border-color: #ff5e00;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255, 94, 0, 0.15);
        }

        .saved-location-chip i {
            color: #ff5e00;
            font-size: 24px;
            margin-bottom: 8px;
        }

        .saved-location-chip .name {
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 4px;
        }

        .saved-location-chip .address {
            font-size: 11px;
            color: #666;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .center-my-location {
            position: absolute;
            bottom: 100px;
            right: 20px;
            background: white;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
            cursor: pointer;
            z-index: 10;
            transition: all 0.3s;
            border: none;
            color: #ff5e00;
            font-size: 20px;
        }

        .center-my-location:hover {
            background: #ff5e00;
            color: white;
            transform: scale(1.1);
        }

        .location-marker-pulse {
            width: 20px;
            height: 20px;
            background: #ff5e00;
            border-radius: 50%;
            position: relative;
        }

        .location-marker-pulse::after {
            content: '';
            position: absolute;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: #ff5e00;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
            opacity: 0.5;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 0.5;
            }

            100% {
                transform: scale(2.5);
                opacity: 0;
            }
        }

        .gps-permission-prompt {
            background: rgba(0, 0, 0, 0.8);
            color: white;
            padding: 15px;
            border-radius: 12px;
            position: absolute;
            top: 80px;
            left: 20px;
            right: 20px;
            z-index: 20;
            display: flex;
            align-items: center;
            justify-content: space-between;
            backdrop-filter: blur(10px);
        }

        .gps-permission-prompt button {
            background: #ff5e00;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
        }

        .desktop-location-panel {
            background: #f9f9f9;
            border-radius: 20px;
            padding: 20px;
            height: fit-content;
        }

        .coordinate-badge {
            background: white;
            padding: 12px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }

        .coordinate-badge .label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
        }

        .coordinate-badge .value {
            font-size: 16px;
            font-weight: 600;
            color: #333;
            font-family: monospace;
        }

        @media (max-width: 768px) {
            .map-picker-container {
                height: 400px;
            }

            .map-overlay {
                flex-direction: column;
                top: 10px;
                left: 10px;
                right: 10px;
            }

            .mode-selector {
                max-width: 100%;
            }

            .search-box {
                width: 100%;
            }

            .location-status {
                flex-direction: column;
                bottom: 10px;
                left: 10px;
                right: 10px;
            }

            .location-card {
                min-width: auto;
            }

            .center-my-location {
                bottom: 120px;
                right: 15px;
                width: 45px;
                height: 45px;
                font-size: 18px;
            }
        }

        /* Hide original location inputs */
        .location-inputs {
            display: none;
        }

        /* Loading overlay */
        .map-loading {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.8);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 100;
            backdrop-filter: blur(3px);
        }

        .map-loading-spinner {
            width: 50px;
            height: 50px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #ff5e00;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        /* Desktop action buttons */
        .action-buttons-desktop {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }

        .back-btn-desktop {
            flex: 1;
            background-color: #f5f5f5;
            color: #666;
            border: none;
            padding: 15px 25px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .back-btn-desktop:hover {
            background-color: #eee;
            transform: translateY(-2px);
        }

        .book-ride-btn-desktop {
            flex: 2;
            background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%);
            color: white;
            border: none;
            padding: 15px 25px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }

        .book-ride-btn-desktop:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 94, 0, 0.2);
        }

        .book-ride-btn-desktop:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .insufficient-balance {
            color: #dc3545;
            font-weight: 600;
        }
        
        .wallet-balance {
            font-size: 18px;
            font-weight: 700;
            color: #28a745;
        }

        /* Driver Selection Styles */
        .skip-driver-btn {
            width: 100%;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border: 2px dashed #ff5e00;
            color: #ff5e00;
            padding: 16px;
            border-radius: 16px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin: 20px 0;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
        }

        .skip-driver-btn:hover {
            background: #fff5f0;
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 94, 0, 0.2);
            border-style: solid;
        }

        .skip-driver-btn i {
            font-size: 20px;
            animation: slideRight 1.5s infinite;
        }

        @keyframes slideRight {
            0%, 100% { transform: translateX(0); }
            50% { transform: translateX(5px); }
        }

        .driver-card-mobile,
        .driver-card-desktop {
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .driver-card-mobile.selected,
        .driver-card-desktop.selected {
            border: 3px solid #ff5e00;
            box-shadow: 0 10px 30px rgba(255, 94, 0, 0.3);
            transform: scale(1.02);
            background: linear-gradient(135deg, #fff5f0 0%, #ffffff 100%);
        }

        .driver-card-mobile.selected::before,
        .driver-card-desktop.selected::before {
            content: '✓ SELECTED';
            position: absolute;
            top: 10px;
            right: 10px;
            background: #ff5e00;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            box-shadow: 0 2px 8px rgba(255, 94, 0, 0.3);
        }

        .ride-type-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 11px;
            font-weight: 600;
            margin-left: 10px;
            text-transform: uppercase;
        }

        .private-ride-badge {
            background: #ff5e00;
            color: white;
            box-shadow: 0 2px 8px rgba(255, 94, 0, 0.3);
        }

        .public-ride-badge {
            background: #4CAF50;
            color: white;
            box-shadow: 0 2px 8px rgba(76, 175, 80, 0.3);
        }

        .driver-info h4 {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .driver-info h4 .fa-lock {
            color: #ff5e00;
            font-size: 12px;
        }

        .driver-info h4 .fa-globe {
            color: #4CAF50;
            font-size: 12px;
        }

        .driver-selection-info {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 15px;
            border-radius: 12px;
            margin: 15px 0;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .driver-selection-info i {
            color: #2196F3;
            font-size: 24px;
        }

        .driver-selection-info .info-text {
            flex: 1;
        }

        .driver-selection-info .info-text strong {
            color: #1976D2;
            display: block;
            margin-bottom: 5px;
        }

        .driver-selection-info .info-text p {
            color: #555;
            margin: 0;
            line-height: 1.5;
        }

        .driver-card-desktop {
            position: relative;
            padding: 20px;
            border: 2px solid #eee;
            border-radius: 16px;
            background: white;
            cursor: pointer;
            transition: all 0.3s;
        }

        .driver-card-desktop.selected {
            border-color: #ff5e00;
            background: #fff5f0;
        }

        .driver-card-desktop.selected::after {
            content: '✓ Selected Driver';
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: #ff5e00;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .driver-card-mobile {
            position: relative;
            padding: 15px;
            border: 2px solid #eee;
            border-radius: 16px;
            background: white;
            margin-bottom: 12px;
            cursor: pointer;
        }

        .driver-card-mobile.selected {
            border-color: #ff5e00;
            background: #fff5f0;
        }

        .driver-card-mobile.selected::after {
            content: '✓ Selected';
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: #ff5e00;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        /* Driver card layout fixes */
        .drivers-list {
            max-height: 400px;
            overflow-y: auto;
            padding: 5px;
        }
        
        .drivers-grid-desktop {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            max-height: 500px;
            overflow-y: auto;
            padding: 5px;
        }
        
        .driver-card-mobile .driver-info,
        .driver-card-desktop .driver-info-desktop {
            cursor: pointer;
        }

        .dashboard-container {
    width: 100%;
    max-width: 1700px;
    background-color: #fff;
    border-radius: 30px;
    box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
    overflow: hidden;
    position: relative;
    min-height: 700px;
}

    </style>
</head>

<body>
    <!-- Dashboard Container -->
    <div class="dashboard-container">

        <!-- MOBILE VIEW -->
        <div class="mobile-view">
            <!-- Header -->
            <div class="header">
                <div class="user-info">
                    <h1>Book a Ride</h1>
                    <p class="wallet-balance">Wallet: ₦<?php echo number_format($walletBalance, 2); ?></p>
                </div>
                <button class="notification-btn" onclick="checkNotifications()">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
            </div>

            <!-- Booking Steps -->
            <div class="booking-steps-mobile">
                <div class="step-mobile active" data-step="1">
                    <div class="step-number">1</div>
                    <h3>Location</h3>
                </div>
                <div class="step-mobile" data-step="2">
                    <div class="step-number">2</div>
                    <h3>Plan</h3>
                </div>
                <div class="step-mobile" data-step="3">
                    <div class="step-number">3</div>
                    <h3>Driver</h3>
                </div>
                <div class="step-mobile" data-step="4">
                    <div class="step-number">4</div>
                    <h3>Payment</h3>
                </div>
            </div>

            <!-- Step 1: Location Selection with Map -->
            <div class="booking-section active" id="step1-mobile">
                <h2 class="text-lg font-semibold mb-3 flex items-center">
                    <i class="fas fa-map-marker-alt text-[#ff5e00] mr-2"></i>
                    Tap on Map to Select Locations
                </h2>
                <p class="text-gray-600 text-sm mb-4">
                    <span class="text-green-600 font-medium">● Pickup mode</span> /
                    <span class="text-red-600 font-medium">● Destination mode</span>
                </p>

                <!-- Hidden inputs to store location data -->
                <input type="hidden" id="pickup-lat-mobile">
                <input type="hidden" id="pickup-lng-mobile">
                <input type="hidden" id="pickup-address-mobile">
                <input type="hidden" id="pickup-place-id-mobile">
                <input type="hidden" id="destination-lat-mobile">
                <input type="hidden" id="destination-lng-mobile">
                <input type="hidden" id="destination-address-mobile">
                <input type="hidden" id="destination-place-id-mobile">

                <!-- MAP LOCATION PICKER -->
                <div class="map-picker-container">
                    <div id="map-mobile"></div>

                    <!-- GPS Permission Prompt -->
                    <div id="gps-prompt-mobile" class="gps-permission-prompt" style="display: none;">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-location-arrow text-orange-500"></i>
                            <span>Allow location for better accuracy</span>
                        </div>
                        <button onclick="requestLocationPermission('mobile')">Enable</button>
                    </div>

                    <!-- Map Overlay Controls -->
                    <div class="map-overlay">
                        <div class="mode-selector">
                            <button class="mode-btn pickup active" id="mobile-pickup-mode" onclick="setMode('mobile', 'pickup')">
                                <i class="fas fa-circle text-green-600"></i> Pickup
                            </button>
                            <button class="mode-btn destination" id="mobile-dest-mode" onclick="setMode('mobile', 'destination')">
                                <i class="fas fa-map-marker-alt text-red-600"></i> Destination
                            </button>
                        </div>

                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" id="mobile-search" placeholder="Search for a location..." autocomplete="off">
                        </div>
                    </div>

                    <!-- Center on My Location Button -->
                    <button class="center-my-location" onclick="centerOnUser('mobile')" title="Center on my location">
                        <i class="fas fa-crosshairs"></i>
                    </button>

                    <!-- Location Status Cards -->
                    <div class="location-status">
                        <div class="location-card" id="mobile-pickup-card" style="display: none;">
                            <div class="label">
                                <i class="fas fa-circle text-green-600"></i> PICKUP LOCATION
                            </div>
                            <div class="address" id="mobile-pickup-address">-</div>
                            <div class="coords" id="mobile-pickup-coords">-</div>
                            <div class="actions">
                                <button class="btn-clear" onclick="clearLocation('mobile', 'pickup')">Clear</button>
                                <button class="btn-confirm" onclick="confirmLocation('mobile', 'pickup')" id="mobile-confirm-pickup">Confirm</button>
                            </div>
                        </div>

                        <div class="location-card destination" id="mobile-dest-card" style="display: none;">
                            <div class="label">
                                <i class="fas fa-map-marker-alt text-red-600"></i> DESTINATION
                            </div>
                            <div class="address" id="mobile-dest-address">-</div>
                            <div class="coords" id="mobile-dest-coords">-</div>
                            <div class="actions">
                                <button class="btn-clear" onclick="clearLocation('mobile', 'destination')">Clear</button>
                                <button class="btn-confirm" onclick="confirmLocation('mobile', 'destination')" id="mobile-confirm-dest">Confirm</button>
                            </div>
                        </div>
                    </div>

                    <!-- Loading Overlay -->
                    <div id="map-loading-mobile" class="map-loading" style="display: none;">
                        <div class="map-loading-spinner"></div>
                    </div>
                </div>

                <!-- Saved Locations -->
                <?php if ($locationsResult && $locationsResult->num_rows > 0): ?>
                    <div class="saved-locations-section">
                        <h3 class="text-base font-semibold mb-3">Your Saved Locations</h3>
                        <div class="saved-locations-grid" id="saved-locations-mobile">
                            <?php
                            if ($locationsResult) {
                                $locationsResult->data_seek(0);
                                while ($location = $locationsResult->fetch_assoc()):
                            ?>
                                <div class="saved-location-chip" onclick="useSavedLocation('<?php echo htmlspecialchars($location['address'] ?? ''); ?>', <?php echo $location['latitude'] ?? 0; ?>, <?php echo $location['longitude'] ?? 0; ?>, '<?php echo $location['location_type'] ?? ''; ?>', 'mobile')">
                                    <i class="fas fa-<?php echo ($location['location_type'] ?? '') == 'home' ? 'home' : (($location['location_type'] ?? '') == 'work' ? 'building' : 'map-pin'); ?>"></i>
                                    <div class="name"><?php echo htmlspecialchars($location['location_name'] ?? ''); ?></div>
                                    <div class="address"><?php echo htmlspecialchars(substr($location['address'] ?? '', 0, 20)) . '...'; ?></div>
                                </div>
                            <?php 
                                endwhile;
                            } 
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Popular Locations -->
                <div class="saved-locations-section">
                    <h3 class="text-base font-semibold mb-3">Popular Locations</h3>
                    <div class="saved-locations-grid" id="mobile-popular-locations"></div>
                </div>
            </div>

            <!-- Step 2: Choose Plan -->
            <div class="booking-section" id="step2-mobile">
                <h2 class="text-lg font-semibold mb-3 flex items-center">
                    <i class="fas fa-car text-[#ff5e00] mr-2"></i>
                    Choose Your Ride
                </h2>
                <p class="text-gray-600 text-sm mb-4">Select the perfect ride for your journey</p>

                <div class="ride-plans">
                    <div class="plan-card-mobile" data-plan="economy" onclick="selectPlan('mobile', 'economy', this)">
                        <div class="plan-header">
                            <div class="plan-icon"><i class="fas fa-car"></i></div>
                            <div>
                                <div class="plan-title">Economy</div>
                                <div class="plan-price">₦1,000 per km</div>
                            </div>
                        </div>
                        <ul class="plan-features">
                            <li><i class="fas fa-check"></i> Affordable rates</li>
                            <li><i class="fas fa-check"></i> 4 Seater cars</li>
                            <li><i class="fas fa-check"></i> Standard comfort</li>
                            <li><i class="fas fa-check"></i> Air conditioned</li>
                        </ul>
                    </div>

                    <div class="plan-card-mobile" data-plan="comfort" onclick="selectPlan('mobile', 'comfort', this)">
                        <div class="plan-header">
                            <div class="plan-icon"><i class="fas fa-car-side"></i></div>
                            <div>
                                <div class="plan-title">Comfort</div>
                                <div class="plan-price">₦1,500 per km</div>
                            </div>
                        </div>
                        <ul class="plan-features">
                            <li><i class="fas fa-check"></i> Extra legroom</li>
                            <li><i class="fas fa-check"></i> Professional drivers</li>
                            <li><i class="fas fa-check"></i> Premium vehicles</li>
                            <li><i class="fas fa-check"></i> Free water</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Step 3: Select Driver -->
            <div class="booking-section" id="step3-mobile">
                <h2 class="text-lg font-semibold mb-3 flex items-center">
                    <i class="fas fa-user-tie text-[#ff5e00] mr-2"></i>
                    Available Drivers
                </h2>
                <p class="text-gray-600 text-sm mb-4" id="driver-status-mobile">Select pickup and destination to see drivers</p>

                <div class="drivers-list" id="drivers-list-mobile">
                    <div class="no-drivers">📍 Select pickup and destination first</div>
                </div>
            </div>

            <!-- Step 4: Payment -->
            <div class="booking-section" id="step4-mobile">
                <h2 class="text-lg font-semibold mb-3 flex items-center">
                    <i class="fas fa-credit-card text-[#ff5e00] mr-2"></i>
                    Payment Method
                </h2>
                <p class="text-gray-600 text-sm mb-4">Select your preferred payment method</p>

                <div class="payment-options">
                    <div class="payment-option" data-payment="wallet" onclick="selectPayment('mobile', 'wallet', this)">
                        <i class="fas fa-wallet"></i>
                        <h4>Speedly Wallet</h4>
                        <p>Balance: ₦<?php echo number_format($walletBalance, 2); ?></p>
                    </div>
                    <div class="payment-option" data-payment="card" onclick="selectPayment('mobile', 'card', this)">
                        <i class="fas fa-credit-card"></i>
                        <h4>Card</h4>
                        <p>Pay with card</p>
                    </div>
                </div>

                <div class="fare-summary" id="fare-summary-mobile" style="display: none;">
                    <h3>Fare Summary</h3>
                    <div class="fare-item">
                        <span>Distance</span>
                        <span id="distance-mobile">0 km</span>
                    </div>
                    <div class="fare-item">
                        <span>Rate per km</span>
                        <span id="rate-mobile">₦1,000</span>
                    </div>
                    <div class="fare-item">
                        <span>Base fare</span>
                        <span id="base-fare-mobile">₦0</span>
                    </div>
                    <div class="fare-item total">
                        <span>Total Amount</span>
                        <span id="total-fare-mobile">₦0</span>
                    </div>
                    <div class="fare-item">
                        <span class="insufficient-balance" id="balance-warning-mobile" style="display: none;">
                            ⚠️ Insufficient balance
                        </span>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="back-btn" onclick="goBackMobile()">
                    <i class="fas fa-arrow-left"></i>
                    Back
                </button>
                <button class="book-ride-btn-mobile" id="mobile-action-btn" onclick="handleMobileNext()" disabled>
                    <i class="fas fa-arrow-right"></i>
                    <span>Set Pickup & Destination</span>
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
                    <img src="main-assets/logo-no-background.png" alt="Speedly Logo" class="logo-image">
                </div>

                <!-- Desktop Navigation -->
                <?php require_once './components/desktop-nav.php'; ?>

                <!-- User Profile -->
                <div class="user-profile" onclick="window.location.href='client_profile.php'">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h3><?php echo htmlspecialchars($user_name); ?></h3>
                        <p>Premium Member</p>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="desktop-main">
                <!-- Header -->
                <div class="desktop-header">
                    <div class="desktop-title">
                        <h1>Book a Ride</h1>
                        <p class="wallet-balance">Wallet: ₦<?php echo number_format($walletBalance, 2); ?></p>
                    </div>
                    <button class="notification-btn" onclick="checkNotifications()">
                        <i class="fas fa-bell"></i>
                        <span class="notification-badge">3</span>
                    </button>
                </div>

                <!-- Booking Steps -->
                <div class="booking-steps-desktop">
                    <div class="step-desktop active" data-step="1">
                        <div class="step-icon"><i class="fas fa-map-marker-alt"></i></div>
                        <h3>Select Location</h3>
                        <p>Choose pickup & destination on map</p>
                    </div>
                    <div class="step-desktop" data-step="2">
                        <div class="step-icon"><i class="fas fa-car"></i></div>
                        <h3>Choose Plan</h3>
                        <p>Select your ride type</p>
                    </div>
                    <div class="step-desktop" data-step="3">
                        <div class="step-icon"><i class="fas fa-user-tie"></i></div>
                        <h3>Select Driver</h3>
                        <p>Choose your preferred driver</p>
                    </div>
                    <div class="step-desktop" data-step="4">
                        <div class="step-icon"><i class="fas fa-credit-card"></i></div>
                        <h3>Make Payment</h3>
                        <p>Pay securely</p>
                    </div>
                </div>

                <!-- Desktop Layout with Map -->
                <div class="flex gap-6 mt-5" id="desktop-map-panel">
                    <!-- Left Column - Map -->
                    <div class="flex-2 w-2/3">
                        <div class="map-picker-container" style="height: 550px;">
                            <div id="map-desktop"></div>

                            <!-- GPS Permission Prompt -->
                            <div id="gps-prompt-desktop" class="gps-permission-prompt" style="display: none;">
                                <div class="flex items-center gap-2">
                                    <i class="fas fa-location-arrow text-orange-500"></i>
                                    <span>Allow location for better accuracy</span>
                                </div>
                                <button onclick="requestLocationPermission('desktop')">Enable</button>
                            </div>

                            <!-- Map Overlay Controls -->
                            <div class="map-overlay">
                                <div class="mode-selector">
                                    <button class="mode-btn pickup active" id="desktop-pickup-mode" onclick="setMode('desktop', 'pickup')">
                                        <i class="fas fa-circle text-green-600"></i> Pickup
                                    </button>
                                    <button class="mode-btn destination" id="desktop-dest-mode" onclick="setMode('desktop', 'destination')">
                                        <i class="fas fa-map-marker-alt text-red-600"></i> Destination
                                    </button>
                                </div>

                                <div class="search-box">
                                    <i class="fas fa-search"></i>
                                    <input type="text" id="desktop-search" placeholder="Search for a location..." autocomplete="off">
                                </div>
                            </div>

                            <!-- Center on My Location Button -->
                            <button class="center-my-location" onclick="centerOnUser('desktop')" title="Center on my location">
                                <i class="fas fa-crosshairs"></i>
                            </button>

                            <!-- Hidden inputs for desktop -->
                            <input type="hidden" id="desktop-pickup-lat">
                            <input type="hidden" id="desktop-pickup-lng">
                            <input type="hidden" id="desktop-pickup-address">
                            <input type="hidden" id="desktop-pickup-place-id">
                            <input type="hidden" id="desktop-dest-lat">
                            <input type="hidden" id="desktop-dest-lng">
                            <input type="hidden" id="desktop-dest-address">
                            <input type="hidden" id="desktop-dest-place-id">

                            <!-- Loading Overlay -->
                            <div id="map-loading-desktop" class="map-loading" style="display: none;">
                                <div class="map-loading-spinner"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column - Location Details & Controls -->
                    <div class="flex-1 w-1/3">
                        <!-- Desktop Location Panel -->
                        <div class="desktop-location-panel">
                            <h3 class="font-semibold text-lg mb-4">📍 Selected Locations</h3>

                            <!-- Pickup Card -->
                            <div class="location-card" id="desktop-pickup-card" style="margin-bottom: 15px; display: none;">
                                <div class="label">
                                    <i class="fas fa-circle text-green-600"></i> PICKUP LOCATION
                                </div>
                                <div class="address" id="desktop-pickup-address">-</div>
                                <div class="coords" id="desktop-pickup-coords">-</div>
                                <div class="actions">
                                    <button class="btn-clear" onclick="clearLocation('desktop', 'pickup')">Clear</button>
                                    <button class="btn-confirm" onclick="confirmLocation('desktop', 'pickup')" id="desktop-confirm-pickup">Confirm</button>
                                </div>
                            </div>

                            <!-- Destination Card -->
                            <div class="location-card destination" id="desktop-dest-card" style="margin-bottom: 20px; display: none;">
                                <div class="label">
                                    <i class="fas fa-map-marker-alt text-red-600"></i> DESTINATION
                                </div>
                                <div class="address" id="desktop-dest-address">-</div>
                                <div class="coords" id="desktop-dest-coords">-</div>
                                <div class="actions">
                                    <button class="btn-clear" onclick="clearLocation('desktop', 'destination')">Clear</button>
                                    <button class="btn-confirm" onclick="confirmLocation('desktop', 'destination')" id="desktop-confirm-dest">Confirm</button>
                                </div>
                            </div>

                            <!-- Live Coordinates -->
                            <div class="grid grid-cols-2 gap-3 mb-4">
                                <div class="coordinate-badge">
                                    <div class="label">LATITUDE</div>
                                    <div class="value" id="desktop-current-lat">--</div>
                                </div>
                                <div class="coordinate-badge">
                                    <div class="label">LONGITUDE</div>
                                    <div class="value" id="desktop-current-lng">--</div>
                                </div>
                            </div>

                            <!-- Saved Locations -->
                            <?php if ($locationsResult && $locationsResult->num_rows > 0): ?>
                                <h3 class="font-semibold text-base mt-4 mb-2">Saved Locations</h3>
                                <div class="grid grid-cols-2 gap-2 mb-4">
                                    <?php 
                                    if ($locationsResult) {
                                        $locationsResult->data_seek(0);
                                        while ($location = $locationsResult->fetch_assoc()): 
                                    ?>
                                        <div class="saved-location-chip p-3" onclick="useSavedLocation('<?php echo htmlspecialchars($location['address'] ?? ''); ?>', <?php echo $location['latitude'] ?? 0; ?>, <?php echo $location['longitude'] ?? 0; ?>, '<?php echo $location['location_type'] ?? ''; ?>', 'desktop')">
                                            <i class="fas fa-<?php echo ($location['location_type'] ?? '') == 'home' ? 'home' : (($location['location_type'] ?? '') == 'work' ? 'building' : 'map-pin'); ?>"></i>
                                            <div class="name text-xs"><?php echo htmlspecialchars($location['location_name'] ?? ''); ?></div>
                                        </div>
                                    <?php 
                                        endwhile;
                                    } 
                                    ?>
                                </div>
                            <?php endif; ?>

                            <!-- Popular Locations -->
                            <h3 class="font-semibold text-base mt-4 mb-2">Popular Locations</h3>
                            <div class="grid grid-cols-2 gap-2" id="desktop-popular-locations"></div>

                            <!-- Continue Button -->
                            <button class="w-full bg-[#ff5e00] hover:bg-[#e65500] text-white py-4 px-4 rounded-xl font-bold transition-all mt-5 flex items-center justify-center gap-2" id="desktop-continue-btn" onclick="continueToNextStep()" disabled>
                                <i class="fas fa-arrow-right"></i> CONTINUE TO PLAN
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Desktop Booking Sections (Hidden initially) -->
                <div id="step2-desktop" class="booking-section-desktop" style="display: none;">
                    <h2 class="text-xl font-semibold mb-4">Choose Your Ride</h2>
                    <div class="plans-grid-desktop">
                        <div class="plan-card-desktop" data-plan="economy" onclick="selectPlan('desktop', 'economy', this)">
                            <div class="plan-icon-desktop"><i class="fas fa-car"></i></div>
                            <h3>Economy</h3>
                            <ul class="plan-features-desktop">
                                <li><i class="fas fa-check"></i> Affordable rates</li>
                                <li><i class="fas fa-check"></i> 4 Seater cars</li>
                                <li><i class="fas fa-check"></i> Standard comfort</li>
                            </ul>
                            <div class="plan-price-desktop">₦1,000 <span>/km</span></div>
                        </div>
                        <div class="plan-card-desktop" data-plan="comfort" onclick="selectPlan('desktop', 'comfort', this)">
                            <div class="plan-icon-desktop"><i class="fas fa-car-side"></i></div>
                            <h3>Comfort</h3>
                            <ul class="plan-features-desktop">
                                <li><i class="fas fa-check"></i> Extra legroom</li>
                                <li><i class="fas fa-check"></i> Professional drivers</li>
                                <li><i class="fas fa-check"></i> Premium vehicles</li>
                            </ul>
                            <div class="plan-price-desktop">₦1,500 <span>/km</span></div>
                        </div>
                    </div>
                    
                    <!-- Navigation buttons for step 2 -->
                    <div class="action-buttons-desktop mt-6">
                        <button class="back-btn-desktop" onclick="goBackDesktop()">
                            <i class="fas fa-arrow-left"></i> Back to Map
                        </button>
                        <button class="book-ride-btn-desktop" id="desktop-plan-next" onclick="nextStepDesktop()" disabled>
                            <i class="fas fa-arrow-right"></i> Continue to Drivers
                        </button>
                    </div>
                </div>

                <div id="step3-desktop" class="booking-section-desktop" style="display: none;">
                    <h2 class="text-xl font-semibold mb-4">Available Drivers</h2>
                    <p class="text-gray-600 mb-4" id="driver-status-desktop">Select a driver to continue</p>
                    <div class="drivers-grid-desktop" id="drivers-list-desktop"></div>
                    
                    <!-- Navigation buttons for step 3 -->
                    <div class="action-buttons-desktop mt-6">
                        <button class="back-btn-desktop" onclick="goBackDesktop()">
                            <i class="fas fa-arrow-left"></i> Back to Plans
                        </button>
                        <button class="book-ride-btn-desktop" id="desktop-driver-next" onclick="nextStepDesktop()" disabled>
                            <i class="fas fa-arrow-right"></i> Continue to Payment
                        </button>
                    </div>
                </div>

                <div id="step4-desktop" class="booking-section-desktop" style="display: none;">
                    <h2 class="text-xl font-semibold mb-4">Payment Method</h2>
                    <div class="payment-grid-desktop">
                        <div class="payment-card-desktop" data-payment="wallet" onclick="selectPayment('desktop', 'wallet', this)">
                            <i class="fas fa-wallet"></i>
                            <h4>Speedly Wallet</h4>
                            <p>Balance: ₦<?php echo number_format($walletBalance, 2); ?></p>
                        </div>
                        <div class="payment-card-desktop" data-payment="card" onclick="selectPayment('desktop', 'card', this)">
                            <i class="fas fa-credit-card"></i>
                            <h4>Card</h4>
                            <p>Pay with card</p>
                        </div>
                    </div>

                    <div class="fare-summary-desktop" id="fare-summary-desktop" style="display: none;">
                        <h3>Fare Summary</h3>
                        <div class="fare-item-desktop">
                            <span>Distance</span>
                            <span id="distance-desktop">0 km</span>
                        </div>
                        <div class="fare-item-desktop">
                            <span>Rate per km</span>
                            <span id="rate-desktop">₦1,000</span>
                        </div>
                        <div class="fare-item-desktop">
                            <span>Base fare</span>
                            <span id="base-fare-desktop">₦0</span>
                        </div>
                        <div class="fare-item-desktop total">
                            <span>Total Amount</span>
                            <span id="total-fare-desktop">₦0</span>
                        </div>
                        <div class="fare-item-desktop">
                            <span class="insufficient-balance" id="balance-warning-desktop" style="display: none;">
                                ⚠️ Insufficient balance
                            </span>
                        </div>
                    </div>

                    <div class="action-buttons-desktop mt-6">
                        <button class="back-btn-desktop" onclick="goBackDesktop()">
                            <i class="fas fa-arrow-left"></i> Back to Drivers
                        </button>
                        <button class="book-ride-btn-desktop" id="desktop-book-btn" onclick="confirmDesktopBooking()" disabled>
                            <i class="fas fa-check"></i> Confirm & Book Ride
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // ==================== GLOBAL VARIABLES ====================
        let mobileMap, desktopMap;
        let mobilePickupMarker, mobileDestMarker;
        let desktopPickupMarker, desktopDestMarker;
        let mobileMode = 'pickup';
        let desktopMode = 'pickup';
        let userLocation = null;
        let watchId = null;

        // Wallet balance from PHP
        const walletBalance = <?php echo $walletBalance; ?>;

        // Booking data objects
        let mobileBooking = {
            pickup: '',
            destination: '',
            pickupLat: null,
            pickupLng: null,
            pickupPlaceId: null,
            destLat: null,
            destLng: null,
            destPlaceId: null,
            plan: '',
            driverId: '',
            driverSelected: false,
            payment: '',
            distance: 0,
            fare: 0
        };

        let desktopBooking = {
            pickup: '',
            destination: '',
            pickupLat: null,
            pickupLng: null,
            pickupPlaceId: null,
            destLat: null,
            destLng: null,
            destPlaceId: null,
            plan: '',
            driverId: '',
            driverSelected: false,
            payment: '',
            distance: 0,
            fare: 0
        };

        // Step variables
        let mobileStep = 1;
        let desktopStep = 1;

        // Popular locations in Nigeria
        const popularLocations = [{
                name: 'Lagos Airport',
                lat: 6.5774,
                lng: 3.3211,
                address: 'Murtala Muhammed International Airport, Lagos',
                icon: 'plane'
            },
            {
                name: 'Victoria Island',
                lat: 6.4281,
                lng: 3.4219,
                address: 'Victoria Island, Lagos, Nigeria',
                icon: 'building'
            },
            {
                name: 'Lekki Phase 1',
                lat: 6.4484,
                lng: 3.4719,
                address: 'Lekki Phase 1, Lagos, Nigeria',
                icon: 'map-pin'
            },
            {
                name: 'Ikeja City Mall',
                lat: 6.6018,
                lng: 3.3515,
                address: 'Ikeja City Mall, Lagos, Nigeria',
                icon: 'shopping-cart'
            },
            {
                name: 'Ajah',
                lat: 6.4700,
                lng: 3.5730,
                address: 'Ajah, Lagos, Nigeria',
                icon: 'market'
            },
            {
                name: 'Maryland Mall',
                lat: 6.5794,
                lng: 3.3622,
                address: 'Maryland Mall, Lagos, Nigeria',
                icon: 'store'
            }
        ];

        // ==================== INITIALIZE MAPS ====================
        document.addEventListener('DOMContentLoaded', function() {
            // Wait for Google Maps to load
            if (typeof google !== 'undefined') {
                initMaps();
            } else {
                // If Google Maps isn't loaded yet, wait for it
                window.addEventListener('load', function() {
                    if (typeof google !== 'undefined') {
                        initMaps();
                    }
                });
            }
            
            setupPopularLocations();
            checkGeolocationPermission();

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

            checkScreenSize();
            window.addEventListener('resize', checkScreenSize);
            
            // Initialize step displays
            updateMobileStep(1);
            
            // Add skip buttons after a delay to ensure drivers are loaded
            setTimeout(addSkipButtons, 1000);
        });

        function initMaps() {
            // Default center (Lagos, Nigeria)
            const defaultCenter = {
                lat: 6.5244,
                lng: 3.3792
            };

            // Mobile Map
            const mobileMapEl = document.getElementById('map-mobile');
            if (mobileMapEl && typeof google !== 'undefined') {
                mobileMap = new google.maps.Map(mobileMapEl, {
                    center: defaultCenter,
                    zoom: 13,
                    mapTypeId: google.maps.MapTypeId.ROADMAP,
                    mapTypeControl: false,
                    streetViewControl: false,
                    fullscreenControl: true,
                    zoomControl: true,
                    styles: [{
                            featureType: "poi",
                            elementType: "labels",
                            stylers: [{
                                visibility: "on"
                            }]
                        },
                        {
                            featureType: "road",
                            elementType: "labels",
                            stylers: [{
                                visibility: "on"
                            }]
                        }
                    ]
                });

                // Add click listener
                mobileMap.addListener('click', function(e) {
                    handleMapClick(e.latLng, 'mobile');
                });

                // Setup search
                setupSearch('mobile');
            }

            // Desktop Map
            const desktopMapEl = document.getElementById('map-desktop');
            if (desktopMapEl && typeof google !== 'undefined') {
                desktopMap = new google.maps.Map(desktopMapEl, {
                    center: defaultCenter,
                    zoom: 13,
                    mapTypeId: google.maps.MapTypeId.ROADMAP,
                    mapTypeControl: true,
                    streetViewControl: true,
                    fullscreenControl: true,
                    zoomControl: true
                });

                // Add click listener
                desktopMap.addListener('click', function(e) {
                    handleMapClick(e.latLng, 'desktop');
                });

                // Setup search
                setupSearch('desktop');
            }
        }

        // ==================== HELPER FUNCTION TO GET LOCATION NAME FROM COORDINATES ====================
        function getLocationNameFromCoords(lat, lng) {
            // Check if coordinates are in Lagos area
            if (lat > 6.35 && lat < 6.70 && lng > 3.25 && lng < 3.60) {
                // Try to determine specific area in Lagos
                if (lat > 6.57 && lat < 6.62 && lng > 3.31 && lng < 3.38) {
                    return `Ikeja Area, Lagos (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
                } else if (lat > 6.42 && lat < 6.48 && lng > 3.40 && lng < 3.48) {
                    return `Victoria Island Area, Lagos (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
                } else if (lat > 6.43 && lat < 6.48 && lng > 3.52 && lng < 3.58) {
                    return `Lekki Area, Lagos (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
                } else if (lat > 6.49 && lat < 6.54 && lng > 3.35 && lng < 3.40) {
                    return `Mainland Area, Lagos (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
                } else {
                    return `Lagos Area (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
                }
            }
            // Check if in Nigeria but outside Lagos
            else if (lat > 4 && lat < 14 && lng > 2 && lng < 15) {
                return `Nigeria (${lat.toFixed(4)}, ${lng.toFixed(4)})`;
            }
            // Generic fallback
            else {
                return `Location at ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
            }
        }

        // ==================== MAP CLICK HANDLER ====================
        function handleMapClick(latLng, view) {
            const lat = latLng.lat();
            const lng = latLng.lng();
            const mode = view === 'mobile' ? mobileMode : desktopMode;

            showLoading(view);

            // Try to get address from Google Geocoder
            if (typeof google !== 'undefined') {
                const geocoder = new google.maps.Geocoder();
                
                geocoder.geocode({
                    location: { lat, lng }
                }, (results, status) => {
                    hideLoading(view);
                    
                    let address = '';
                    let placeId = '';

                    if (status === 'OK' && results && results[0]) {
                        address = results[0].formatted_address;
                        placeId = results[0].place_id;
                        console.log('✅ Address found:', address);
                    } else {
                        // Fallback: Use our helper function
                        address = getLocationNameFromCoords(lat, lng);
                        console.log('⚠️ Using fallback address:', address);
                        
                        // Only show warning if it's not a simple coordinates fallback
                        if (!address.includes('Area') && !address.includes('Nigeria')) {
                            Swal.fire({
                                icon: 'info',
                                title: 'Location Selected',
                                text: 'Using coordinates. Search for better accuracy.',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        }
                    }

                    // Update marker based on mode
                    if (mode === 'pickup') {
                        if (view === 'mobile') {
                            updatePickupMarker('mobile', lat, lng, address, placeId);
                        } else {
                            updatePickupMarker('desktop', lat, lng, address, placeId);
                        }
                    } else {
                        if (view === 'mobile') {
                            updateDestMarker('mobile', lat, lng, address, placeId);
                        } else {
                            updateDestMarker('desktop', lat, lng, address, placeId);
                        }
                    }

                    // Update live coordinates display
                    if (view === 'desktop') {
                        const latEl = document.getElementById('desktop-current-lat');
                        const lngEl = document.getElementById('desktop-current-lng');
                        if (latEl) latEl.textContent = lat.toFixed(6);
                        if (lngEl) lngEl.textContent = lng.toFixed(6);
                    }
                });
            } else {
                hideLoading(view);
                Swal.fire('Error', 'Google Maps failed to load', 'error');
            }
        }

        // ==================== MARKER FUNCTIONS ====================
        function updatePickupMarker(view, lat, lng, address, placeId) {
            if (typeof google === 'undefined') return;
            
            const map = view === 'mobile' ? mobileMap : desktopMap;
            const booking = view === 'mobile' ? mobileBooking : desktopBooking;

            // Remove existing pickup marker
            if (view === 'mobile' && mobilePickupMarker) {
                mobilePickupMarker.setMap(null);
            } else if (view === 'desktop' && desktopPickupMarker) {
                desktopPickupMarker.setMap(null);
            }

            // Create new marker
            const marker = new google.maps.Marker({
                position: { lat, lng },
                map: map,
                title: address || 'Pickup Location',
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 12,
                    fillColor: '#4CAF50',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 3,
                    labelOrigin: new google.maps.Point(0, -10)
                },
                label: {
                    text: 'P',
                    color: 'white',
                    fontSize: '12px',
                    fontWeight: 'bold'
                },
                animation: google.maps.Animation.DROP
            });

            // Add info window with address
            const infoWindow = new google.maps.InfoWindow({
                content: `<div style="padding: 5px; max-width: 200px;"><strong>Pickup:</strong> ${address || 'Location selected'}</div>`
            });
            
            marker.addListener('click', () => {
                infoWindow.open(map, marker);
            });

            if (view === 'mobile') {
                mobilePickupMarker = marker;
            } else {
                desktopPickupMarker = marker;
            }

            // Update booking data
            booking.pickup = address || `Location at ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
            booking.pickupLat = lat;
            booking.pickupLng = lng;
            booking.pickupPlaceId = placeId;

            // Update hidden inputs
            const latInput = document.getElementById(`${view}-pickup-lat`);
            const lngInput = document.getElementById(`${view}-pickup-lng`);
            const addrInput = document.getElementById(`${view}-pickup-address`);
            const placeInput = document.getElementById(`${view}-pickup-place-id`);
            
            if (latInput) latInput.value = lat;
            if (lngInput) lngInput.value = lng;
            if (addrInput) addrInput.value = booking.pickup;
            if (placeInput) placeInput.value = placeId || '';

            // Update UI card
            const card = document.getElementById(`${view}-pickup-card`);
            const addressEl = document.getElementById(`${view}-pickup-address`);
            const coordsEl = document.getElementById(`${view}-pickup-coords`);

            if (addressEl) addressEl.textContent = booking.pickup;
            if (coordsEl) coordsEl.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            if (card) card.style.display = 'block';

            // Enable confirm button
            const confirmBtn = document.getElementById(`${view}-confirm-pickup`);
            if (confirmBtn) confirmBtn.disabled = false;

            // If both locations are set, calculate fare
            if (booking.pickupLat && booking.destLat) {
                calculateFare(view);
            }

            updateButtonState(view);
        }

        function updateDestMarker(view, lat, lng, address, placeId) {
            if (typeof google === 'undefined') return;
            
            const map = view === 'mobile' ? mobileMap : desktopMap;
            const booking = view === 'mobile' ? mobileBooking : desktopBooking;

            // Remove existing destination marker
            if (view === 'mobile' && mobileDestMarker) {
                mobileDestMarker.setMap(null);
            } else if (view === 'desktop' && desktopDestMarker) {
                desktopDestMarker.setMap(null);
            }

            // Create new marker
            const marker = new google.maps.Marker({
                position: { lat, lng },
                map: map,
                title: address || 'Destination',
                icon: {
                    path: google.maps.SymbolPath.CIRCLE,
                    scale: 12,
                    fillColor: '#F44336',
                    fillOpacity: 1,
                    strokeColor: '#ffffff',
                    strokeWeight: 3,
                    labelOrigin: new google.maps.Point(0, -10)
                },
                label: {
                    text: 'D',
                    color: 'white',
                    fontSize: '12px',
                    fontWeight: 'bold'
                },
                animation: google.maps.Animation.DROP
            });

            // Add info window with address
            const infoWindow = new google.maps.InfoWindow({
                content: `<div style="padding: 5px; max-width: 200px;"><strong>Destination:</strong> ${address || 'Location selected'}</div>`
            });
            
            marker.addListener('click', () => {
                infoWindow.open(map, marker);
            });

            if (view === 'mobile') {
                mobileDestMarker = marker;
            } else {
                desktopDestMarker = marker;
            }

            // Update booking data
            booking.destination = address || `Location at ${lat.toFixed(4)}, ${lng.toFixed(4)}`;
            booking.destLat = lat;
            booking.destLng = lng;
            booking.destPlaceId = placeId;

            // Update hidden inputs
            const latInput = document.getElementById(`${view}-dest-lat`);
            const lngInput = document.getElementById(`${view}-dest-lng`);
            const addrInput = document.getElementById(`${view}-dest-address`);
            const placeInput = document.getElementById(`${view}-dest-place-id`);
            
            if (latInput) latInput.value = lat;
            if (lngInput) lngInput.value = lng;
            if (addrInput) addrInput.value = booking.destination;
            if (placeInput) placeInput.value = placeId || '';

            // Update UI card
            const card = document.getElementById(`${view}-dest-card`);
            const addressEl = document.getElementById(`${view}-dest-address`);
            const coordsEl = document.getElementById(`${view}-dest-coords`);

            if (addressEl) addressEl.textContent = booking.destination;
            if (coordsEl) coordsEl.textContent = `${lat.toFixed(6)}, ${lng.toFixed(6)}`;
            if (card) card.style.display = 'block';

            // Enable confirm button
            const confirmBtn = document.getElementById(`${view}-confirm-dest`);
            if (confirmBtn) confirmBtn.disabled = false;

            // If both locations are set, calculate fare
            if (booking.pickupLat && booking.destLat) {
                calculateFare(view);
            }

            updateButtonState(view);
        }

        // ==================== CONFIRM LOCATION ====================
        function confirmLocation(view, type) {
            const booking = view === 'mobile' ? mobileBooking : desktopBooking;

            if (type === 'pickup') {
                Swal.fire({
                    icon: 'success',
                    title: 'Pickup Location Confirmed',
                    text: booking.pickup ? booking.pickup.substring(0, 50) + '...' : 'Location confirmed',
                    timer: 1500,
                    showConfirmButton: false
                });

                // Auto-switch to destination mode
                setMode(view, 'destination');
            } else {
                Swal.fire({
                    icon: 'success',
                    title: 'Destination Confirmed',
                    text: booking.destination ? booking.destination.substring(0, 50) + '...' : 'Location confirmed',
                    timer: 1500,
                    showConfirmButton: false
                });

                // Calculate route if both points exist
                if (booking.pickupLat && booking.destLat) {
                    drawRoute(view);
                }
            }

            updateButtonState(view);
        }

        function clearLocation(view, type) {
            const booking = view === 'mobile' ? mobileBooking : desktopBooking;

            if (type === 'pickup') {
                if (view === 'mobile' && mobilePickupMarker) {
                    mobilePickupMarker.setMap(null);
                    mobilePickupMarker = null;
                } else if (view === 'desktop' && desktopPickupMarker) {
                    desktopPickupMarker.setMap(null);
                    desktopPickupMarker = null;
                }

                booking.pickup = '';
                booking.pickupLat = null;
                booking.pickupLng = null;
                booking.pickupPlaceId = null;

                const card = document.getElementById(`${view}-pickup-card`);
                if (card) card.style.display = 'none';
                
                const latInput = document.getElementById(`${view}-pickup-lat`);
                const lngInput = document.getElementById(`${view}-pickup-lng`);
                const addrInput = document.getElementById(`${view}-pickup-address`);
                
                if (latInput) latInput.value = '';
                if (lngInput) lngInput.value = '';
                if (addrInput) addrInput.value = '';
            } else {
                if (view === 'mobile' && mobileDestMarker) {
                    mobileDestMarker.setMap(null);
                    mobileDestMarker = null;
                } else if (view === 'desktop' && desktopDestMarker) {
                    desktopDestMarker.setMap(null);
                    desktopDestMarker = null;
                }

                booking.destination = '';
                booking.destLat = null;
                booking.destLng = null;
                booking.destPlaceId = null;

                const card = document.getElementById(`${view}-dest-card`);
                if (card) card.style.display = 'none';
                
                const latInput = document.getElementById(`${view}-dest-lat`);
                const lngInput = document.getElementById(`${view}-dest-lng`);
                const addrInput = document.getElementById(`${view}-dest-address`);
                
                if (latInput) latInput.value = '';
                if (lngInput) lngInput.value = '';
                if (addrInput) addrInput.value = '';
            }

            // Hide fare summary
            if (view === 'mobile') {
                const fareSummary = document.getElementById('fare-summary-mobile');
                if (fareSummary) fareSummary.style.display = 'none';
            } else {
                const fareSummary = document.getElementById('fare-summary-desktop');
                if (fareSummary) fareSummary.style.display = 'none';
            }

            updateButtonState(view);
        }

        // ==================== SET MODE ====================
        function setMode(view, mode) {
            if (view === 'mobile') {
                mobileMode = mode;
                const pickupBtn = document.getElementById('mobile-pickup-mode');
                const destBtn = document.getElementById('mobile-dest-mode');
                if (pickupBtn) pickupBtn.classList.toggle('active', mode === 'pickup');
                if (destBtn) destBtn.classList.toggle('active', mode === 'destination');
            } else {
                desktopMode = mode;
                const pickupBtn = document.getElementById('desktop-pickup-mode');
                const destBtn = document.getElementById('desktop-dest-mode');
                if (pickupBtn) pickupBtn.classList.toggle('active', mode === 'pickup');
                if (destBtn) destBtn.classList.toggle('active', mode === 'destination');
            }
        }

        // ==================== SEARCH SETUP ====================
        function setupSearch(view) {
            const searchInput = document.getElementById(`${view}-search`);
            if (!searchInput || typeof google === 'undefined') return;

            const map = view === 'mobile' ? mobileMap : desktopMap;

            // Create autocomplete
            const autocomplete = new google.maps.places.Autocomplete(searchInput, {
                componentRestrictions: {
                    country: 'ng'
                },
                fields: ['place_id', 'geometry', 'formatted_address', 'name']
            });

            autocomplete.addListener('place_changed', () => {
                const place = autocomplete.getPlace();

                if (!place || !place.geometry) {
                    Swal.fire('Invalid Location', 'Please select from suggestions', 'warning');
                    return;
                }

                const location = place.geometry.location;
                const lat = location.lat();
                const lng = location.lng();
                const mode = view === 'mobile' ? mobileMode : desktopMode;

                map.setCenter(location);
                map.setZoom(16);

                // Use the formatted address from the place
                const address = place.formatted_address || place.name || `${lat.toFixed(4)}, ${lng.toFixed(4)}`;

                if (mode === 'pickup') {
                    updatePickupMarker(view, lat, lng, address, place.place_id);
                } else {
                    updateDestMarker(view, lat, lng, address, place.place_id);
                }
                
                // Clear the search input
                searchInput.value = '';
            });
        }

        // ==================== USE SAVED LOCATION ====================
        function useSavedLocation(address, lat, lng, type, view) {
            const mode = view === 'mobile' ? mobileMode : desktopMode;
            const map = view === 'mobile' ? mobileMap : desktopMap;

            if (map) {
                map.setCenter({
                    lat: parseFloat(lat),
                    lng: parseFloat(lng)
                });
                map.setZoom(16);
            }

            // Determine if this should be pickup or destination based on current mode
            if (mode === 'pickup') {
                updatePickupMarker(view, parseFloat(lat), parseFloat(lng), address, '');
            } else {
                updateDestMarker(view, parseFloat(lat), parseFloat(lng), address, '');
            }
        }

        // ==================== SETUP POPULAR LOCATIONS ====================
        function setupPopularLocations() {
            const mobileContainer = document.getElementById('mobile-popular-locations');
            const desktopContainer = document.getElementById('desktop-popular-locations');

            popularLocations.forEach(loc => {
                // Mobile
                if (mobileContainer) {
                    const chip = document.createElement('div');
                    chip.className = 'saved-location-chip';
                    chip.innerHTML = `
                        <i class="fas fa-${loc.icon}"></i>
                        <div class="name">${loc.name}</div>
                        <div class="address">${loc.address.substring(0, 20)}...</div>
                    `;
                    chip.onclick = () => useSavedLocation(loc.address, loc.lat, loc.lng, 'popular', 'mobile');
                    mobileContainer.appendChild(chip);
                }

                // Desktop
                if (desktopContainer) {
                    const chip = document.createElement('div');
                    chip.className = 'saved-location-chip p-2';
                    chip.innerHTML = `
                        <i class="fas fa-${loc.icon}"></i>
                        <div class="name text-xs">${loc.name}</div>
                    `;
                    chip.onclick = () => useSavedLocation(loc.address, loc.lat, loc.lng, 'popular', 'desktop');
                    desktopContainer.appendChild(chip);
                }
            });
        }

        // ==================== GPS FUNCTIONS ====================
        function checkGeolocationPermission() {
            if (!navigator.geolocation) return;

            if (navigator.permissions) {
                navigator.permissions.query({
                    name: 'geolocation'
                }).then((result) => {
                    if (result.state === 'granted') {
                        startWatchingPosition();
                    } else if (result.state === 'prompt') {
                        const mobilePrompt = document.getElementById('gps-prompt-mobile');
                        const desktopPrompt = document.getElementById('gps-prompt-desktop');
                        if (mobilePrompt) mobilePrompt.style.display = 'flex';
                        if (desktopPrompt) desktopPrompt.style.display = 'flex';
                    }

                    result.addEventListener('change', () => {
                        if (result.state === 'granted') {
                            const mobilePrompt = document.getElementById('gps-prompt-mobile');
                            const desktopPrompt = document.getElementById('gps-prompt-desktop');
                            if (mobilePrompt) mobilePrompt.style.display = 'none';
                            if (desktopPrompt) desktopPrompt.style.display = 'none';
                            startWatchingPosition();
                        }
                    });
                });
            }
        }

        function requestLocationPermission(view) {
            const mobilePrompt = document.getElementById('gps-prompt-mobile');
            const desktopPrompt = document.getElementById('gps-prompt-desktop');
            if (mobilePrompt) mobilePrompt.style.display = 'none';
            if (desktopPrompt) desktopPrompt.style.display = 'none';

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    userLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                    startWatchingPosition();

                    // Center map on user location
                    if (view === 'mobile' && mobileMap) {
                        mobileMap.setCenter(userLocation);
                        mobileMap.setZoom(16);
                    } else if (view === 'desktop' && desktopMap) {
                        desktopMap.setCenter(userLocation);
                        desktopMap.setZoom(16);
                    }
                },
                (error) => {
                    console.log('Location permission denied');
                }
            );
        }

        function startWatchingPosition() {
            if (watchId) navigator.geolocation.clearWatch(watchId);

            watchId = navigator.geolocation.watchPosition(
                (position) => {
                    userLocation = {
                        lat: position.coords.latitude,
                        lng: position.coords.longitude
                    };
                },
                (error) => {}, {
                    enableHighAccuracy: true,
                    maximumAge: 0,
                    timeout: 10000
                }
            );
        }

        function centerOnUser(view) {
            if (!userLocation) {
                requestLocationPermission(view);
                return;
            }

            const map = view === 'mobile' ? mobileMap : desktopMap;
            if (map) {
                map.setCenter(userLocation);
                map.setZoom(16);
            }
        }

        // ==================== FARE CALCULATION ====================
        function calculateFare(view) {
            const booking = view === 'mobile' ? mobileBooking : desktopBooking;

            if (!booking.pickupLat || !booking.pickupLng || !booking.destLat || !booking.destLng) {
                return;
            }

            // Calculate distance using Haversine formula
            const distance = calculateDistance(booking.pickupLat, booking.pickupLng, booking.destLat, booking.destLng);
            const rate = booking.plan === 'economy' ? 1000 : 1500;
            const baseFare = 500;
            const fare = (distance * rate) + baseFare;

            booking.distance = distance;
            booking.fare = fare;

            if (view === 'mobile') {
                const distanceEl = document.getElementById('distance-mobile');
                const rateEl = document.getElementById('rate-mobile');
                const baseFareEl = document.getElementById('base-fare-mobile');
                const totalFareEl = document.getElementById('total-fare-mobile');
                const fareSummary = document.getElementById('fare-summary-mobile');
                const balanceWarning = document.getElementById('balance-warning-mobile');
                
                if (distanceEl) distanceEl.textContent = distance.toFixed(1) + ' km';
                if (rateEl) rateEl.textContent = '₦' + rate.toLocaleString();
                if (baseFareEl) baseFareEl.textContent = '₦' + baseFare.toLocaleString();
                if (totalFareEl) totalFareEl.textContent = '₦' + fare.toLocaleString();
                if (fareSummary) fareSummary.style.display = 'block';
                
                // Check if wallet balance is sufficient
                if (balanceWarning) {
                    if (fare > walletBalance && booking.payment === 'wallet') {
                        balanceWarning.style.display = 'block';
                    } else {
                        balanceWarning.style.display = 'none';
                    }
                }
            } else {
                const distanceEl = document.getElementById('distance-desktop');
                const rateEl = document.getElementById('rate-desktop');
                const baseFareEl = document.getElementById('base-fare-desktop');
                const totalFareEl = document.getElementById('total-fare-desktop');
                const fareSummary = document.getElementById('fare-summary-desktop');
                const balanceWarning = document.getElementById('balance-warning-desktop');
                
                if (distanceEl) distanceEl.textContent = distance.toFixed(1) + ' km';
                if (rateEl) rateEl.textContent = '₦' + rate.toLocaleString();
                if (baseFareEl) baseFareEl.textContent = '₦' + baseFare.toLocaleString();
                if (totalFareEl) totalFareEl.textContent = '₦' + fare.toLocaleString();
                if (fareSummary) fareSummary.style.display = 'block';
                
                // Check if wallet balance is sufficient
                if (balanceWarning) {
                    if (fare > walletBalance && booking.payment === 'wallet') {
                        balanceWarning.style.display = 'block';
                    } else {
                        balanceWarning.style.display = 'none';
                    }
                }
            }

            // Find nearby drivers
            findNearbyDrivers(booking.pickupLat, booking.pickupLng, booking.plan || 'economy', view);
        }

        function calculateDistance(lat1, lon1, lat2, lon2) {
            // Haversine formula to calculate distance in km
            const R = 6371; // Radius of the earth in km
            const dLat = deg2rad(lat2 - lat1);
            const dLon = deg2rad(lon2 - lon1);
            const a = 
                Math.sin(dLat/2) * Math.sin(dLat/2) +
                Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) * 
                Math.sin(dLon/2) * Math.sin(dLon/2); 
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a)); 
            const distance = R * c; // Distance in km
            return Math.max(distance, 1); // Minimum 1km
        }

        function deg2rad(deg) {
            return deg * (Math.PI/180);
        }

        // ==================== DRAW ROUTE ====================
        function drawRoute(view) {
            if (typeof google === 'undefined') return;
            
            const booking = view === 'mobile' ? mobileBooking : desktopBooking;
            const map = view === 'mobile' ? mobileMap : desktopMap;

            if (!booking.pickupLat || !booking.destLat || !map) return;

            const directionsService = new google.maps.DirectionsService();
            const directionsRenderer = new google.maps.DirectionsRenderer({
                map: map,
                suppressMarkers: true,
                polylineOptions: {
                    strokeColor: '#ff5e00',
                    strokeWeight: 5,
                    strokeOpacity: 0.7
                }
            });

            directionsService.route({
                origin: {
                    lat: booking.pickupLat,
                    lng: booking.pickupLng
                },
                destination: {
                    lat: booking.destLat,
                    lng: booking.destLng
                },
                travelMode: google.maps.TravelMode.DRIVING
            }, (result, status) => {
                if (status === 'OK') {
                    directionsRenderer.setDirections(result);
                }
            });
        }

        // ==================== FIND NEARBY DRIVERS ====================
        function findNearbyDrivers(lat, lng, rideType, view) {
            fetch(`SERVER/API/get_nearby_drivers.php?lat=${lat}&lng=${lng}&ride_type=${rideType}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.drivers && data.drivers.length > 0) {
                        displayDrivers(data.drivers, view);
                        if (view === 'mobile') {
                            document.getElementById('driver-status-mobile').textContent = `${data.drivers.length} drivers available nearby`;
                        } else {
                            document.getElementById('driver-status-desktop').textContent = `${data.drivers.length} drivers available`;
                        }
                    } else {
                        const container = view === 'mobile' ? 
                            document.getElementById('drivers-list-mobile') : 
                            document.getElementById('drivers-list-desktop');
                        
                        if (container) {
                            container.innerHTML = '<div class="no-drivers">🚫 No drivers available nearby</div>';
                        }
                        
                        if (view === 'mobile') {
                            document.getElementById('driver-status-mobile').textContent = 'No drivers available';
                        } else {
                            document.getElementById('driver-status-desktop').textContent = 'No drivers available';
                        }
                    }
                })
                .catch(error => {
                    console.error('Error fetching drivers:', error);
                    const container = view === 'mobile' ? 
                        document.getElementById('drivers-list-mobile') : 
                        document.getElementById('drivers-list-desktop');
                    
                    if (container) {
                        container.innerHTML = '<div class="no-drivers">⚠️ Error loading drivers</div>';
                    }
                });
        }

        function displayDrivers(drivers, view) {
            const container = view === 'mobile' ?
                document.getElementById('drivers-list-mobile') :
                document.getElementById('drivers-list-desktop');

            if (!container) return;

            container.innerHTML = '';

            drivers.forEach(driver => {
                const stars = '★'.repeat(Math.floor(driver.rating)) + '☆'.repeat(5 - Math.floor(driver.rating));
                const div = document.createElement('div');
                div.className = view === 'mobile' ? 'driver-card-mobile' : 'driver-card-desktop';
                div.setAttribute('data-driver-id', driver.id);

                div.innerHTML = view === 'mobile' ? `
                    <div class="driver-info" onclick="selectDriver('${driver.id}', 'mobile', this)">
                        <div class="driver-avatar">${driver.name.charAt(0)}</div>
                        <div class="driver-details">
                            <h4>${driver.name} <span class="ride-type-badge ${driver.type === 'comfort' ? 'private-ride-badge' : 'public-ride-badge'}">${driver.type}</span></h4>
                            <div class="driver-rating">${stars} <span>${driver.rating}</span></div>
                            <div class="text-sm"><i class="fas fa-car"></i> ${driver.vehicle}</div>
                        </div>
                    </div>
                    <div class="driver-stats">
                        <div class="stat-item"><span class="stat-value">${driver.distance}</span><span class="stat-label">km away</span></div>
                        <div class="stat-item"><span class="stat-value">${driver.rating}</span><span class="stat-label">rating</span></div>
                        <div class="stat-item"><span class="stat-value">${driver.rides}+</span><span class="stat-label">rides</span></div>
                    </div>
                ` : `
                    <div class="driver-info-desktop" onclick="selectDriver('${driver.id}', 'desktop', this)">
                        <div class="driver-avatar-desktop">${driver.name.charAt(0)}</div>
                        <div class="driver-details-desktop">
                            <h4>${driver.name} <span class="ride-type-badge ${driver.type === 'comfort' ? 'private-ride-badge' : 'public-ride-badge'}">${driver.type}</span></h4>
                            <div class="driver-rating-desktop">${stars} <span>${driver.rating} (${driver.rides}+ rides)</span></div>
                            <div class="driver-car-desktop"><i class="fas fa-car"></i> ${driver.vehicle} • ${driver.plate}</div>
                        </div>
                    </div>
                    <div class="driver-stats-desktop">
                        <div class="stat-item-desktop"><span class="stat-value-desktop">${driver.distance}</span><span class="stat-label-desktop">km away</span></div>
                        <div class="stat-item-desktop"><span class="stat-value-desktop">${driver.rating}</span><span class="stat-label-desktop">rating</span></div>
                        <div class="stat-item-desktop"><span class="stat-value-desktop">${driver.rides}+</span><span class="stat-label-desktop">rides</span></div>
                    </div>
                `;

                container.appendChild(div);
            });

            if (view === 'mobile') {
                const statusEl = document.getElementById('driver-status-mobile');
                if (statusEl) statusEl.textContent = `${drivers.length} drivers available nearby`;
            } else {
                const statusEl = document.getElementById('driver-status-desktop');
                if (statusEl) statusEl.textContent = `${drivers.length} drivers available`;
            }
            
            addSkipButtons();
        }

        // ==================== SELECT DRIVER ====================
        function selectDriver(driverId, view, element) {
            const card = element.closest(view === 'mobile' ? '.driver-card-mobile' : '.driver-card-desktop');
            if (!card) return;
            
            if (view === 'mobile') {
                document.querySelectorAll('#drivers-list-mobile .driver-card-mobile').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                mobileBooking.driverId = driverId;
                mobileBooking.driverSelected = true;
                
                Swal.fire({
                    icon: 'info',
                    title: 'Private Ride',
                    html: '<p>This driver will be <strong>privately notified</strong> of your ride.</p><p class="text-sm text-gray-500 mt-2">Only this driver can accept this ride.</p>',
                    timer: 3000,
                    showConfirmButton: false,
                    position: 'top-end',
                    toast: true
                });
                
                updateMobileButtonState();
            } else {
                document.querySelectorAll('#drivers-list-desktop .driver-card-desktop').forEach(c => c.classList.remove('selected'));
                card.classList.add('selected');
                desktopBooking.driverId = driverId;
                desktopBooking.driverSelected = true;
                
                Swal.fire({
                    icon: 'info',
                    title: 'Private Ride',
                    html: '<p>This driver will be <strong>privately notified</strong> of your ride.</p><p class="text-sm text-gray-500 mt-2">Only this driver can accept this ride.</p>',
                    timer: 3000,
                    showConfirmButton: false,
                    position: 'top-end',
                    toast: true
                });
                
                updateDesktopButtonState();
            }
        }

        function skipDriverSelection(view) {
            if (view === 'mobile') {
                document.querySelectorAll('#drivers-list-mobile .driver-card-mobile').forEach(c => c.classList.remove('selected'));
                mobileBooking.driverId = '';
                mobileBooking.driverSelected = false;
                
                Swal.fire({
                    icon: 'info',
                    title: 'Public Ride',
                    html: '<p>Your ride will be <strong>visible to all nearby drivers</strong>.</p><p class="text-sm text-gray-500 mt-2">Any available driver can accept this ride.</p>',
                    timer: 3000,
                    showConfirmButton: false,
                    position: 'top-end',
                    toast: true
                });
                
                updateMobileButtonState();
            } else {
                document.querySelectorAll('#drivers-list-desktop .driver-card-desktop').forEach(c => c.classList.remove('selected'));
                desktopBooking.driverId = '';
                desktopBooking.driverSelected = false;
                
                Swal.fire({
                    icon: 'info',
                    title: 'Public Ride',
                    html: '<p>Your ride will be <strong>visible to all nearby drivers</strong>.</p><p class="text-sm text-gray-500 mt-2">Any available driver can accept this ride.</p>',
                    timer: 3000,
                    showConfirmButton: false,
                    position: 'top-end',
                    toast: true
                });
                
                updateDesktopButtonState();
            }
        }

        function addSkipButtons() {
            // Mobile
            const mobileDriverStep = document.getElementById('step3-mobile');
            if (mobileDriverStep && !document.getElementById('skip-driver-mobile')) {
                if (!document.querySelector('.driver-selection-info')) {
                    const infoDiv = document.createElement('div');
                    infoDiv.className = 'driver-selection-info';
                    infoDiv.innerHTML = `
                        <i class="fas fa-info-circle"></i>
                        <div class="info-text">
                            <strong>Choose a specific driver or let anyone accept</strong>
                            <p>Select a driver below for a private ride, or click "Skip" to make this ride public.</p>
                        </div>
                    `;
                    mobileDriverStep.insertBefore(infoDiv, mobileDriverStep.querySelector('.drivers-list'));
                }
                
                const skipBtn = document.createElement('button');
                skipBtn.id = 'skip-driver-mobile';
                skipBtn.className = 'skip-driver-btn';
                skipBtn.innerHTML = '<i class="fas fa-globe"></i> Skip - Make Ride Public (Any Driver Can Accept)';
                skipBtn.onclick = () => skipDriverSelection('mobile');
                
                mobileDriverStep.querySelector('.drivers-list').after(skipBtn);
            }
            
            // Desktop
            const desktopDriverStep = document.getElementById('step3-desktop');
            if (desktopDriverStep && !document.getElementById('skip-driver-desktop')) {
                if (!document.querySelector('.driver-selection-info')) {
                    const infoDiv = document.createElement('div');
                    infoDiv.className = 'driver-selection-info';
                    infoDiv.innerHTML = `
                        <i class="fas fa-info-circle"></i>
                        <div class="info-text">
                            <strong>Ride Privacy Options</strong>
                            <p>• Select a driver below for a <span class="private-ride-badge">PRIVATE RIDE</span> (only they can accept)<br>
                            • Click "Skip" for a <span class="public-ride-badge">PUBLIC RIDE</span> (any driver can accept)</p>
                        </div>
                    `;
                    desktopDriverStep.insertBefore(infoDiv, desktopDriverStep.querySelector('.drivers-grid-desktop'));
                }
                
                const skipBtn = document.createElement('button');
                skipBtn.id = 'skip-driver-desktop';
                skipBtn.className = 'skip-driver-btn';
                skipBtn.innerHTML = '<i class="fas fa-globe"></i> Skip - Make Ride Public (Any Driver Can Accept)';
                skipBtn.onclick = () => skipDriverSelection('desktop');
                
                desktopDriverStep.querySelector('.drivers-grid-desktop').after(skipBtn);
            }
        }

        // ==================== SELECT PLAN ====================
        function selectPlan(view, plan, element) {
            const booking = view === 'mobile' ? mobileBooking : desktopBooking;

            if (view === 'mobile') {
                document.querySelectorAll('.plan-card-mobile').forEach(c => c.classList.remove('selected'));
                element.classList.add('selected');
            } else {
                document.querySelectorAll('.plan-card-desktop').forEach(c => c.classList.remove('selected'));
                element.classList.add('selected');
            }

            booking.plan = plan;

            if (booking.pickupLat && booking.destLat) {
                calculateFare(view);
            }

            updateButtonState(view);
        }

        // ==================== SELECT PAYMENT ====================
        function selectPayment(view, payment, element) {
            const booking = view === 'mobile' ? mobileBooking : desktopBooking;

            if (view === 'mobile') {
                document.querySelectorAll('.payment-option').forEach(c => c.classList.remove('selected'));
                element.classList.add('selected');
            } else {
                document.querySelectorAll('.payment-card-desktop').forEach(c => c.classList.remove('selected'));
                element.classList.add('selected');
            }

            booking.payment = payment;
            
            if (booking.pickupLat && booking.destLat) {
                calculateFare(view);
            }
            
            updateButtonState(view);
        }

        // ==================== CHECK BALANCE SUFFICIENCY ====================
        function isBalanceSufficient(view) {
            const booking = view === 'mobile' ? mobileBooking : desktopBooking;
            
            if (booking.payment === 'card') {
                return true;
            }
            
            return walletBalance >= booking.fare;
        }

        // ==================== UPDATE BUTTON STATE ====================
        function updateButtonState(view) {
            if (view === 'mobile') {
                updateMobileButtonState();
            } else {
                updateDesktopButtonState();
            }
        }

        function updateMobileButtonState() {
            const actionBtn = document.getElementById('mobile-action-btn');
            if (!actionBtn) return;
            
            switch(mobileStep) {
                case 1:
                    actionBtn.disabled = !(mobileBooking.pickupLat && mobileBooking.destLat);
                    break;
                case 2:
                    actionBtn.disabled = !mobileBooking.plan;
                    break;
                case 3:
                    actionBtn.disabled = false;
                    actionBtn.innerHTML = '<i class="fas fa-arrow-right"></i><span>Continue to Payment</span>';
                    break;
                case 4:
                    if (!mobileBooking.payment) {
                        actionBtn.disabled = true;
                    } else if (mobileBooking.payment === 'wallet' && mobileBooking.fare > walletBalance) {
                        actionBtn.disabled = true;
                        actionBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i><span>Insufficient Balance</span>';
                    } else {
                        actionBtn.disabled = false;
                        actionBtn.innerHTML = '<i class="fas fa-check"></i><span>Confirm & Book Ride</span>';
                    }
                    break;
            }
        }

        function updateDesktopButtonState() {
            const continueBtn = document.getElementById('desktop-continue-btn');
            const planNextBtn = document.getElementById('desktop-plan-next');
            const driverNextBtn = document.getElementById('desktop-driver-next');
            const bookBtn = document.getElementById('desktop-book-btn');
            
            if (continueBtn) {
                continueBtn.disabled = !(desktopBooking.pickupLat && desktopBooking.destLat);
            }
            
            if (planNextBtn) {
                planNextBtn.disabled = !desktopBooking.plan;
            }
            
            if (driverNextBtn) {
                driverNextBtn.disabled = false;
            }
            
            if (bookBtn) {
                if (!desktopBooking.payment) {
                    bookBtn.disabled = true;
                } else if (desktopBooking.payment === 'wallet' && desktopBooking.fare > walletBalance) {
                    bookBtn.disabled = true;
                    bookBtn.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Insufficient Balance';
                } else {
                    bookBtn.disabled = false;
                    bookBtn.innerHTML = '<i class="fas fa-check"></i> Confirm & Book Ride';
                }
            }
        }

        // ==================== MOBILE STEP NAVIGATION ====================
        function handleMobileNext() {
            if (mobileStep === 4) {
                confirmMobileBooking();
            } else {
                nextStepMobile();
            }
        }

        function nextStepMobile() {
            if (mobileStep === 1 && (!mobileBooking.pickupLat || !mobileBooking.destLat)) {
                Swal.fire('Incomplete', 'Please select both pickup and destination', 'warning');
                return;
            }
            if (mobileStep === 2 && !mobileBooking.plan) {
                Swal.fire('Incomplete', 'Please select a ride plan', 'warning');
                return;
            }

            if (mobileStep < 4) {
                mobileStep++;
                updateMobileStep(mobileStep);
            }
        }

        function updateMobileStep(step) {
            document.querySelectorAll('.booking-section').forEach(s => s.classList.remove('active'));
            const stepEl = document.getElementById(`step${step}-mobile`);
            if (stepEl) stepEl.classList.add('active');

            document.querySelectorAll('.step-mobile').forEach(s => s.classList.remove('active'));
            const stepIndicator = document.querySelector(`.step-mobile[data-step="${step}"]`);
            if (stepIndicator) stepIndicator.classList.add('active');

            const actionBtn = document.getElementById('mobile-action-btn');
            if (actionBtn) {
                const text = actionBtn.querySelector('span');
                const icon = actionBtn.querySelector('i');

                if (step === 1) {
                    if (text) text.textContent = 'Set Pickup & Destination';
                    if (icon) icon.className = 'fas fa-arrow-right';
                } else if (step === 2) {
                    if (text) text.textContent = 'Choose Ride Plan';
                    if (icon) icon.className = 'fas fa-car';
                } else if (step === 3) {
                    if (text) text.textContent = 'Select Driver (Optional)';
                    if (icon) icon.className = 'fas fa-user-tie';
                } else if (step === 4) {
                    if (text) text.textContent = 'Make Payment';
                    if (icon) icon.className = 'fas fa-credit-card';
                }
            }

            updateMobileButtonState();
        }

        function goBackMobile() {
            if (mobileStep > 1) {
                mobileStep--;
                updateMobileStep(mobileStep);
            }
        }

        // ==================== DESKTOP STEP NAVIGATION ====================
        function continueToNextStep() {
            if (!desktopBooking.pickupLat || !desktopBooking.destLat) {
                Swal.fire('Incomplete', 'Please select both pickup and destination', 'warning');
                return;
            }

            const mapPanel = document.getElementById('desktop-map-panel');
            if (mapPanel) mapPanel.style.display = 'none';
            
            const step2 = document.getElementById('step2-desktop');
            const step3 = document.getElementById('step3-desktop');
            const step4 = document.getElementById('step4-desktop');
            
            if (step2) step2.style.display = 'block';
            if (step3) step3.style.display = 'none';
            if (step4) step4.style.display = 'none';
            
            document.querySelectorAll('.step-desktop').forEach((s, i) => {
                s.classList.toggle('active', i === 1);
            });
            
            desktopStep = 2;
            updateDesktopButtonState();
        }

        function nextStepDesktop() {
            if (desktopStep === 2) {
                if (!desktopBooking.plan) {
                    Swal.fire('Incomplete', 'Please select a ride plan', 'warning');
                    return;
                }
                
                const step2 = document.getElementById('step2-desktop');
                const step3 = document.getElementById('step3-desktop');
                const step4 = document.getElementById('step4-desktop');
                
                if (step2) step2.style.display = 'none';
                if (step3) step3.style.display = 'block';
                if (step4) step4.style.display = 'none';
                
                document.querySelectorAll('.step-desktop').forEach((s, i) => {
                    s.classList.toggle('active', i === 2);
                });
                
                desktopStep = 3;
                
                if (desktopBooking.pickupLat) {
                    findNearbyDrivers(desktopBooking.pickupLat, desktopBooking.pickupLng, desktopBooking.plan || 'economy', 'desktop');
                }
            } else if (desktopStep === 3) {
                const step2 = document.getElementById('step2-desktop');
                const step3 = document.getElementById('step3-desktop');
                const step4 = document.getElementById('step4-desktop');
                
                if (step2) step2.style.display = 'none';
                if (step3) step3.style.display = 'none';
                if (step4) step4.style.display = 'block';
                
                document.querySelectorAll('.step-desktop').forEach((s, i) => {
                    s.classList.toggle('active', i === 3);
                });
                
                desktopStep = 4;
                
                if (desktopBooking.pickupLat && desktopBooking.destLat) {
                    calculateFare('desktop');
                }
            }
            
            updateDesktopButtonState();
        }

        function goBackDesktop() {
            if (desktopStep === 2) {
                const mapPanel = document.getElementById('desktop-map-panel');
                if (mapPanel) mapPanel.style.display = 'flex';
                
                const step2 = document.getElementById('step2-desktop');
                const step3 = document.getElementById('step3-desktop');
                const step4 = document.getElementById('step4-desktop');
                
                if (step2) step2.style.display = 'none';
                if (step3) step3.style.display = 'none';
                if (step4) step4.style.display = 'none';
                
                document.querySelectorAll('.step-desktop').forEach((s, i) => {
                    s.classList.toggle('active', i === 0);
                });
                
                desktopStep = 1;
            } else if (desktopStep === 3) {
                const step2 = document.getElementById('step2-desktop');
                const step3 = document.getElementById('step3-desktop');
                const step4 = document.getElementById('step4-desktop');
                
                if (step2) step2.style.display = 'block';
                if (step3) step3.style.display = 'none';
                if (step4) step4.style.display = 'none';
                
                document.querySelectorAll('.step-desktop').forEach((s, i) => {
                    s.classList.toggle('active', i === 1);
                });
                
                desktopStep = 2;
            } else if (desktopStep === 4) {
                const step2 = document.getElementById('step2-desktop');
                const step3 = document.getElementById('step3-desktop');
                const step4 = document.getElementById('step4-desktop');
                
                if (step2) step2.style.display = 'none';
                if (step3) step3.style.display = 'block';
                if (step4) step4.style.display = 'none';
                
                document.querySelectorAll('.step-desktop').forEach((s, i) => {
                    s.classList.toggle('active', i === 2);
                });
                
                desktopStep = 3;
            }
            
            updateDesktopButtonState();
        }

        // ==================== CONFIRM BOOKING ====================
        function confirmDesktopBooking() {
            if (!desktopBooking.pickup || !desktopBooking.destination || !desktopBooking.plan || !desktopBooking.payment) {
                Swal.fire('Incomplete', 'Please complete all steps', 'warning');
                return;
            }

            if (desktopBooking.payment === 'wallet' && desktopBooking.fare > walletBalance) {
                Swal.fire({
                    icon: 'error',
                    title: 'Insufficient Balance',
                    html: `Your wallet balance (₦${walletBalance.toLocaleString()}) is insufficient for this ride (₦${desktopBooking.fare.toLocaleString()}).<br><br>Please top up your wallet to continue.`,
                    showCancelButton: true,
                    confirmButtonText: '💳 Add Funds',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#ff5e00'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'wallet.php';
                    }
                });
                return;
            }
            
            let confirmMessage = '';
            let confirmIcon = 'question';
            
            if (desktopBooking.driverId) {
                confirmMessage = 'This will be a PRIVATE ride sent only to the selected driver. Continue?';
                confirmIcon = 'lock';
            } else {
                confirmMessage = 'This will be a PUBLIC ride visible to all nearby drivers. Continue?';
                confirmIcon = 'globe';
            }
            
            Swal.fire({
                title: 'Confirm Booking',
                text: confirmMessage,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#ff5e00',
                confirmButtonText: 'Yes, Book Now',
                cancelButtonText: 'Cancel',
                iconHtml: `<i class="fas fa-${confirmIcon}"></i>`
            }).then((result) => {
                if (result.isConfirmed) {
                    processDesktopBooking();
                }
            });
        }

        function processDesktopBooking() {
            Swal.fire({
                title: 'Booking your ride...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => Swal.showLoading()
            });

            const formData = new FormData();
            formData.append('pickup_address', desktopBooking.pickup);
            formData.append('pickup_lat', desktopBooking.pickupLat);
            formData.append('pickup_lng', desktopBooking.pickupLng);
            formData.append('pickup_place_id', desktopBooking.pickupPlaceId || '');
            formData.append('dest_address', desktopBooking.destination);
            formData.append('dest_lat', desktopBooking.destLat);
            formData.append('dest_lng', desktopBooking.destLng);
            formData.append('dest_place_id', desktopBooking.destPlaceId || '');
            formData.append('distance', desktopBooking.distance);
            formData.append('fare', desktopBooking.fare);
            formData.append('driver_id', desktopBooking.driverId || '');
            formData.append('ride_type', desktopBooking.plan);
            formData.append('payment_method', desktopBooking.payment);

            fetch('SERVER/API/book_ride.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();

                if (data.success) {
                    let message = '';
                    if (data.driver_assigned) {
                        message = 'Your selected driver has been notified and will respond shortly.';
                    } else {
                        message = 'Nearby drivers have been notified and will respond shortly.';
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Ride Booked Successfully!',
                        html: `
                            <div style="text-align: left;">
                                <p><strong>Ride Number:</strong> #${data.ride_number}</p>
                                <p><strong>Amount Paid:</strong> ₦${desktopBooking.fare.toLocaleString()}</p>
                                <p><strong>New Balance:</strong> ₦${data.new_balance.toLocaleString()}</p>
                                <p class="mt-2 text-sm">${message}</p>
                            </div>
                        `,
                        confirmButtonColor: '#ff5e00',
                        confirmButtonText: '📄 View Receipt'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = `generate_receipt.php?ride_id=${data.ride_id}`;
                        } else {
                            window.location.href = 'ride_history.php';
                        }
                    });
                } else if (data.insufficient_balance) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Insufficient Balance',
                        html: `Your wallet balance (₦${data.current_balance.toLocaleString()}) is insufficient.<br>Required: ₦${data.required_amount.toLocaleString()}<br>Shortage: ₦${data.shortage.toLocaleString()}`,
                        showCancelButton: true,
                        confirmButtonText: '💳 Add Funds',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#ff5e00'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = 'wallet.php';
                        }
                    });
                } else {
                    Swal.fire('Booking Failed', data.message || 'An error occurred', 'error');
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Booking error:', error);
                Swal.fire('Error', 'Failed to book ride. Please try again.', 'error');
            });
        }

        function confirmMobileBooking() {
            if (!mobileBooking.pickup || !mobileBooking.destination || !mobileBooking.plan || !mobileBooking.payment) {
                Swal.fire({
                    icon: 'error',
                    title: 'Incomplete Booking',
                    text: 'Please complete all steps',
                    confirmButtonColor: '#ff5e00'
                });
                return;
            }
            
            if (!mobileBooking.pickupLat || !mobileBooking.pickupLng || !mobileBooking.destLat || !mobileBooking.destLng) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Locations',
                    text: 'Please select valid locations from the suggestions',
                    confirmButtonColor: '#ff5e00'
                });
                return;
            }
            
            if (mobileBooking.payment === 'wallet' && mobileBooking.fare > walletBalance) {
                Swal.fire({
                    icon: 'error',
                    title: 'Insufficient Balance',
                    html: `Your wallet balance (₦${walletBalance.toLocaleString()}) is insufficient for this ride (₦${mobileBooking.fare.toLocaleString()}).<br><br>Please top up your wallet to continue.`,
                    showCancelButton: true,
                    confirmButtonText: '💳 Add Funds',
                    cancelButtonText: 'Cancel',
                    confirmButtonColor: '#ff5e00'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'wallet.php';
                    }
                });
                return;
            }
            
            let confirmMessage = '';
            let confirmIcon = 'question';
            
            if (mobileBooking.driverId) {
                confirmMessage = 'This will be a PRIVATE ride sent only to the selected driver. Continue?';
                confirmIcon = 'lock';
            } else {
                confirmMessage = 'This will be a PUBLIC ride visible to all nearby drivers. Continue?';
                confirmIcon = 'globe';
            }
            
            Swal.fire({
                title: 'Confirm Booking',
                text: confirmMessage,
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#ff5e00',
                confirmButtonText: 'Yes, Book Now',
                cancelButtonText: 'Cancel',
                iconHtml: `<i class="fas fa-${confirmIcon}"></i>`
            }).then((result) => {
                if (result.isConfirmed) {
                    processMobileBooking();
                }
            });
        }

        function processMobileBooking() {
            Swal.fire({
                title: 'Booking your ride...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            const formData = new FormData();
            formData.append('pickup_address', mobileBooking.pickup);
            formData.append('pickup_lat', mobileBooking.pickupLat);
            formData.append('pickup_lng', mobileBooking.pickupLng);
            formData.append('pickup_place_id', mobileBooking.pickupPlaceId || '');
            formData.append('dest_address', mobileBooking.destination);
            formData.append('dest_lat', mobileBooking.destLat);
            formData.append('dest_lng', mobileBooking.destLng);
            formData.append('dest_place_id', mobileBooking.destPlaceId || '');
            formData.append('distance', mobileBooking.distance);
            formData.append('fare', mobileBooking.fare);
            formData.append('driver_id', mobileBooking.driverId || '');
            formData.append('ride_type', mobileBooking.plan);
            formData.append('payment_method', mobileBooking.payment);

            fetch('SERVER/API/book_ride.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                Swal.close();

                if (data.success) {
                    let message = '';
                    if (data.driver_assigned) {
                        message = 'Your selected driver has been notified and will respond shortly.';
                    } else {
                        message = 'Nearby drivers have been notified and will respond shortly.';
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Ride Booked!',
                        html: `
                            <p><strong>Ride #${data.ride_number}</strong></p>
                            <p>${message}</p>
                            <p>New Balance: ₦${data.new_balance.toLocaleString()}</p>
                        `,
                        confirmButtonColor: '#ff5e00'
                    }).then(() => {
                        window.location.href = 'ride_history.php';
                    });
                } else if (data.insufficient_balance) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Insufficient Balance',
                        html: `
                            <p>Current Balance: ₦${data.current_balance.toLocaleString()}</p>
                            <p>Required: ₦${data.required_amount.toLocaleString()}</p>
                            <p>Shortage: ₦${data.shortage.toLocaleString()}</p>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'Add Funds',
                        cancelButtonText: 'Cancel',
                        confirmButtonColor: '#ff5e00'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            window.location.href = data.redirect;
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Booking Failed',
                        text: data.message,
                        confirmButtonColor: '#ff5e00'
                    });
                }
            })
            .catch(error => {
                Swal.close();
                console.error('Error:', error);
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Failed to book ride',
                    confirmButtonColor: '#ff5e00'
                });
            });
        }

        // ==================== UTILITY FUNCTIONS ====================
        function showLoading(view) {
            const loader = document.getElementById(`map-loading-${view}`);
            if (loader) loader.style.display = 'flex';
        }

        function hideLoading(view) {
            const loader = document.getElementById(`map-loading-${view}`);
            if (loader) loader.style.display = 'none';
        }

        function checkNotifications() {
            Swal.fire({
                icon: 'info',
                title: 'Notifications',
                html: '<p>🚗 20% off your next ride</p><p>💰 Add funds get 10% bonus</p>',
                confirmButtonColor: '#ff5e00'
            });
        }
    </script>
</body>
</html>   