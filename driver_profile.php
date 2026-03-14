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
$success_message = '';
$error_message = '';

// Get driver profile
$driverQuery = "SELECT dp.*, u.email, u.phone, u.fullname 
                FROM driver_profiles dp 
                JOIN users u ON dp.user_id = u.id 
                WHERE dp.user_id = ?";
$driverStmt = $conn->prepare($driverQuery);
$driverStmt->bind_param("s", $user_id);
$driverStmt->execute();
$driverResult = $driverStmt->get_result();
$driverData = $driverResult->fetch_assoc();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    $state = trim($_POST['state']);
    $vehicle_type = trim($_POST['vehicle_type']);
    $vehicle_model = trim($_POST['vehicle_model']);
    $vehicle_year = trim($_POST['vehicle_year']);
    $license_plate = trim($_POST['license_plate']);
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $account_name = trim($_POST['account_name']);
    
    // Update users table
    $updateUserQuery = "UPDATE users SET fullname = ?, phone = ? WHERE id = ?";
    $updateUserStmt = $conn->prepare($updateUserQuery);
    $updateUserStmt->bind_param("sss", $fullname, $phone, $user_id);
    
    if ($updateUserStmt->execute()) {
        // Update or insert driver profile
        if ($driverData) {
            // Update existing profile
            $updateProfileQuery = "UPDATE driver_profiles SET 
                                  address = ?, city = ?, state = ?, 
                                  vehicle_type = ?, vehicle_model = ?, vehicle_year = ?, 
                                  license_plate = ?, bank_name = ?, account_number = ?, 
                                  account_name = ?, updated_at = NOW() 
                                  WHERE user_id = ?";
            $updateProfileStmt = $conn->prepare($updateProfileQuery);
            $updateProfileStmt->bind_param("sssssssssss", 
                $address, $city, $state, 
                $vehicle_type, $vehicle_model, $vehicle_year, 
                $license_plate, $bank_name, $account_number, 
                $account_name, $user_id
            );
        } else {
            // Create new profile
            $insertProfileQuery = "INSERT INTO driver_profiles 
                                  (user_id, address, city, state, vehicle_type, 
                                   vehicle_model, vehicle_year, license_plate, 
                                   bank_name, account_number, account_name, created_at, updated_at) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())";
            $insertProfileStmt = $conn->prepare($insertProfileQuery);
            $insertProfileStmt->bind_param("sssssssssss", 
                $user_id, $address, $city, $state, $vehicle_type, 
                $vehicle_model, $vehicle_year, $license_plate, 
                $bank_name, $account_number, $account_name
            );
        }
        
        if (($driverData && $updateProfileStmt->execute()) || (!$driverData && $insertProfileStmt->execute())) {
            $_SESSION['fullname'] = $fullname;
            $success_message = "Profile updated successfully!";
            
            // Refresh driver data
            $driverStmt->execute();
            $driverResult = $driverStmt->get_result();
            $driverData = $driverResult->fetch_assoc();
        } else {
            $error_message = "Failed to update profile. Please try again.";
        }
    } else {
        $error_message = "Failed to update user information.";
    }
}

// Handle KYC document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_documents'])) {
    // Handle file uploads here
    $target_dir = "uploads/driver_docs/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $license_file = $target_dir . basename($_FILES["license_file"]["name"]);
    $id_file = $target_dir . basename($_FILES["id_file"]["name"]);
    
    if (move_uploaded_file($_FILES["license_file"]["tmp_name"], $license_file) && 
        move_uploaded_file($_FILES["id_file"]["tmp_name"], $id_file)) {
        
        $updateDocsQuery = "UPDATE driver_profiles SET 
                           license_document = ?, id_document = ?, 
                           kyc_status = 'pending', updated_at = NOW() 
                           WHERE user_id = ?";
        $updateDocsStmt = $conn->prepare($updateDocsQuery);
        $updateDocsStmt->bind_param("sss", $license_file, $id_file, $user_id);
        
        if ($updateDocsStmt->execute()) {
            $success_message = "Documents uploaded successfully! They will be reviewed shortly.";
            
            // Refresh driver data
            $driverStmt->execute();
            $driverResult = $driverStmt->get_result();
            $driverData = $driverResult->fetch_assoc();
        } else {
            $error_message = "Failed to update document information.";
        }
    } else {
        $error_message = "Failed to upload documents. Please try again.";
    }
}

// Get notification count
$notifQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notifStmt = $conn->prepare($notifQuery);
$notifStmt->bind_param("s", $user_id);
$notifStmt->execute();
$notifResult = $notifStmt->get_result();
$notifData = $notifResult->fetch_assoc();
$notificationCount = $notifData['count'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedly • Driver Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="./CSS/driver_dashboard.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-100">
    <div class="dashboard-container">
        <!-- Mobile View -->
        <div class="mobile-view">
            <!-- Mobile Header -->
            <div class="header">
                <div class="user-info">
                    <h1>Profile</h1>
                    <p><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>'s information</p>
                </div>
                <button class="notification-btn bg-[#ff5e00] rounded-2xl p-2 relative" onclick="checkNotifications()">
                    <i class="fas fa-bell text-white"></i>
                    <span class="notification-badge"><?php echo $notificationCount; ?></span>
                </button>
            </div>

            <!-- Profile Content -->
            <div class="p-4 pb-20">
                <!-- Profile Header -->
                <div class="bg-gradient-to-r from-[#ff5e00] to-[#ff8c3a] p-6 rounded-2xl text-white mb-6">
                    <div class="flex items-center gap-4">
                        <div class="w-20 h-20 bg-white/20 rounded-full flex items-center justify-center text-3xl font-bold">
                            <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold"><?php echo htmlspecialchars($user_name); ?></h2>
                            <p class="opacity-90">Driver</p>
                            <?php if ($driverData && $driverData['kyc_status']): ?>
                                <span class="inline-block mt-2 px-3 py-1 bg-white/20 rounded-full text-sm">
                                    KYC: <?php echo ucfirst($driverData['kyc_status']); ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="flex mb-4 bg-gray-200 rounded-lg p-1">
                    <button onclick="showTab('personal')" id="personal-tab" class="flex-1 py-2 text-sm font-medium rounded-lg transition-all duration-300 bg-white shadow">Personal</button>
                    <button onclick="showTab('vehicle')" id="vehicle-tab" class="flex-1 py-2 text-sm font-medium rounded-lg transition-all duration-300">Vehicle</button>
                    <button onclick="showTab('bank')" id="bank-tab" class="flex-1 py-2 text-sm font-medium rounded-lg transition-all duration-300">Bank</button>
                    <button onclick="showTab('kyc')" id="kyc-tab" class="flex-1 py-2 text-sm font-medium rounded-lg transition-all duration-300">KYC</button>
                </div>

                <!-- Personal Info Form -->
                <form id="personal-form" method="POST" action="">
                    <div class="bg-white rounded-xl p-4 space-y-4">
                        <div>
                            <label class="block text-gray-600 text-sm mb-2">Full Name</label>
                            <input type="text" name="fullname" value="<?php echo htmlspecialchars($user_name); ?>" 
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                        </div>
                        <div>
                            <label class="block text-gray-600 text-sm mb-2">Email</label>
                            <input type="email" value="<?php echo htmlspecialchars($driverData['email'] ?? ''); ?>" readonly disabled
                                   class="w-full p-3 border border-gray-300 rounded-lg bg-gray-100">
                        </div>
                        <div>
                            <label class="block text-gray-600 text-sm mb-2">Phone Number</label>
                            <input type="tel" name="phone" value="<?php echo htmlspecialchars($driverData['phone'] ?? ''); ?>" 
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                        </div>
                        <div>
                            <label class="block text-gray-600 text-sm mb-2">Address</label>
                            <input type="text" name="address" value="<?php echo htmlspecialchars($driverData['address'] ?? ''); ?>" 
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-gray-600 text-sm mb-2">City</label>
                                <input type="text" name="city" value="<?php echo htmlspecialchars($driverData['city'] ?? ''); ?>" 
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                            </div>
                            <div>
                                <label class="block text-gray-600 text-sm mb-2">State</label>
                                <input type="text" name="state" value="<?php echo htmlspecialchars($driverData['state'] ?? ''); ?>" 
                                       class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                            </div>
                        </div>
                    </div>
                </form>

                <!-- Vehicle Info Form -->
                <form id="vehicle-form" method="POST" action="" class="hidden">
                    <div class="bg-white rounded-xl p-4 space-y-4">
                        <div>
                            <label class="block text-gray-600 text-sm mb-2">Vehicle Type</label>
                            <select name="vehicle_type" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                                <option value="">Select Vehicle Type</option>
                                <option value="sedan" <?php echo ($driverData['vehicle_type'] ?? '') == 'sedan' ? 'selected' : ''; ?>>Sedan</option>
                                <option value="suv" <?php echo ($driverData['vehicle_type'] ?? '') == 'suv' ? 'selected' : ''; ?>>SUV</option>
                                <option value="hatchback" <?php echo ($driverData['vehicle_type'] ?? '') == 'hatchback' ? 'selected' : ''; ?>>Hatchback</option>
                                <option value="mpv" <?php echo ($driverData['vehicle_type'] ?? '') == 'mpv' ? 'selected' : ''; ?>>MPV</option>
                                <option value="luxury" <?php echo ($driverData['vehicle_type'] ?? '') == 'luxury' ? 'selected' : ''; ?>>Luxury</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-600 text-sm mb-2">Vehicle Model</label>
                            <input type="text" name="vehicle_model" value="<?php echo htmlspecialchars($driverData['vehicle_model'] ?? ''); ?>" 
                                   placeholder="e.g., Toyota Camry" 
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                        </div>
                        <div>
                            <label class="block text-gray-600 text-sm mb-2">Vehicle Year</label>
                            <input type="number" name="vehicle_year" value="<?php echo htmlspecialchars($driverData['vehicle_year'] ?? ''); ?>" 
                                   min="2000" max="2025" 
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                        </div>
                        <div>
                            <label class="block text-gray-600 text-sm mb-2">License Plate</label>
                            <input type="text" name="license_plate" value="<?php echo htmlspecialchars($driverData['license_plate'] ?? ''); ?>" 
                                   placeholder="ABC-123-XY" 
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                        </div>
                    </div>
                </form>

                <!-- Bank Info Form -->
                <form id="bank-form" method="POST" action="" class="hidden">
                    <div class="bg-white rounded-xl p-4 space-y-4">
                        <div>
                            <label class="block text-gray-600 text-sm mb-2">Bank Name</label>
                            <select name="bank_name" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                                <option value="">Select Bank</option>
                                <option value="Access Bank" <?php echo ($driverData['bank_name'] ?? '') == 'Access Bank' ? 'selected' : ''; ?>>Access Bank</option>
                                <option value="GTBank" <?php echo ($driverData['bank_name'] ?? '') == 'GTBank' ? 'selected' : ''; ?>>GTBank</option>
                                <option value="First Bank" <?php echo ($driverData['bank_name'] ?? '') == 'First Bank' ? 'selected' : ''; ?>>First Bank</option>
                                <option value="UBA" <?php echo ($driverData['bank_name'] ?? '') == 'UBA' ? 'selected' : ''; ?>>UBA</option>
                                <option value="Zenith" <?php echo ($driverData['bank_name'] ?? '') == 'Zenith' ? 'selected' : ''; ?>>Zenith Bank</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-gray-600 text-sm mb-2">Account Number</label>
                            <input type="text" name="account_number" value="<?php echo htmlspecialchars($driverData['account_number'] ?? ''); ?>" 
                                   maxlength="10" pattern="\d{10}" 
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                        </div>
                        <div>
                            <label class="block text-gray-600 text-sm mb-2">Account Name</label>
                            <input type="text" name="account_name" value="<?php echo htmlspecialchars($driverData['account_name'] ?? ''); ?>" 
                                   class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                        </div>
                    </div>
                </form>

                <!-- KYC Upload Form -->
                <form id="kyc-form" method="POST" action="" enctype="multipart/form-data" class="hidden">
                    <div class="bg-white rounded-xl p-4 space-y-4">
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <i class="fas fa-id-card text-4xl text-gray-400 mb-3"></i>
                            <p class="text-gray-600 mb-2">Upload Driver's License</p>
                            <input type="file" name="license_file" accept="image/*,.pdf" class="w-full text-sm">
                        </div>
                        <div class="border-2 border-dashed border-gray-300 rounded-lg p-6 text-center">
                            <i class="fas fa-passport text-4xl text-gray-400 mb-3"></i>
                            <p class="text-gray-600 mb-2">Upload Government ID</p>
                            <input type="file" name="id_file" accept="image/*,.pdf" class="w-full text-sm">
                        </div>
                        <p class="text-xs text-gray-500">Accepted formats: JPG, PNG, PDF (Max size: 5MB)</p>
                    </div>
                </form>

                <!-- Save Button -->
                <div class="mt-6">
                    <button onclick="saveProfile()" class="w-full bg-gradient-to-r from-[#ff5e00] to-[#ff8c3a] text-white py-3 rounded-xl font-semibold">
                        <i class="fas fa-save mr-2"></i>Save Changes
                    </button>
                </div>
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
                <?php require_once './components/desktop-driver-nav.php'; ?>
                <div class="user-profile" onclick="window.location.href='driver_profile.php'">
                    <div class="profile-avatar"><?php echo strtoupper(substr($user_name, 0, 1)); ?></div>
                    <div class="profile-info">
                        <h3><?php echo htmlspecialchars($user_name); ?></h3>
                        <p>Driver</p>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="desktop-main">
                <div class="desktop-header">
                    <div class="desktop-title">
                        <h1>Driver Profile</h1>
                        <p>Manage your personal information and documents</p>
                    </div>
                    <button class="notification-btn bg-gray-100 p-3 rounded-xl relative" onclick="checkNotifications()">
                        <i class="fas fa-bell text-gray-700 text-xl"></i>
                        <span class="notification-badge"><?php echo $notificationCount; ?></span>
                    </button>
                </div>

                <!-- Profile Content -->
                <div class="bg-white rounded-xl shadow-sm p-8">
                    <!-- Desktop Tabs -->
                    <div class="flex mb-8 border-b">
                        <button onclick="showTab('personal')" id="desktop-personal-tab" class="px-6 py-3 font-medium border-b-2 border-[#ff5e00] text-[#ff5e00]">Personal Info</button>
                        <button onclick="showTab('vehicle')" id="desktop-vehicle-tab" class="px-6 py-3 font-medium text-gray-600">Vehicle Info</button>
                        <button onclick="showTab('bank')" id="desktop-bank-tab" class="px-6 py-3 font-medium text-gray-600">Bank Details</button>
                        <button onclick="showTab('kyc')" id="desktop-kyc-tab" class="px-6 py-3 font-medium text-gray-600">KYC Documents</button>
                    </div>

                    <!-- All Forms (Desktop) -->
                    <form method="POST" action="" enctype="multipart/form-data" id="desktop-profile-form">
                        <!-- Personal Info Section -->
                        <div id="desktop-personal-section">
                            <h2 class="text-xl font-semibold mb-6">Personal Information</h2>
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-gray-600 mb-2">Full Name</label>
                                    <input type="text" name="fullname" value="<?php echo htmlspecialchars($user_name); ?>" 
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                                </div>
                                <div>
                                    <label class="block text-gray-600 mb-2">Email</label>
                                    <input type="email" value="<?php echo htmlspecialchars($driverData['email'] ?? ''); ?>" readonly disabled
                                           class="w-full p-3 border border-gray-300 rounded-lg bg-gray-100">
                                </div>
                                <div>
                                    <label class="block text-gray-600 mb-2">Phone Number</label>
                                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($driverData['phone'] ?? ''); ?>" 
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                                </div>
                                <div>
                                    <label class="block text-gray-600 mb-2">Address</label>
                                    <input type="text" name="address" value="<?php echo htmlspecialchars($driverData['address'] ?? ''); ?>" 
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                                </div>
                                <div>
                                    <label class="block text-gray-600 mb-2">City</label>
                                    <input type="text" name="city" value="<?php echo htmlspecialchars($driverData['city'] ?? ''); ?>" 
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                                </div>
                                <div>
                                    <label class="block text-gray-600 mb-2">State</label>
                                    <input type="text" name="state" value="<?php echo htmlspecialchars($driverData['state'] ?? ''); ?>" 
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                                </div>
                            </div>
                        </div>

                        <!-- Vehicle Info Section -->
                        <div id="desktop-vehicle-section" class="hidden">
                            <h2 class="text-xl font-semibold mb-6">Vehicle Information</h2>
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-gray-600 mb-2">Vehicle Type</label>
                                    <select name="vehicle_type" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                                        <option value="">Select Vehicle Type</option>
                                        <option value="sedan" <?php echo ($driverData['vehicle_type'] ?? '') == 'sedan' ? 'selected' : ''; ?>>Sedan</option>
                                        <option value="suv" <?php echo ($driverData['vehicle_type'] ?? '') == 'suv' ? 'selected' : ''; ?>>SUV</option>
                                        <option value="hatchback" <?php echo ($driverData['vehicle_type'] ?? '') == 'hatchback' ? 'selected' : ''; ?>>Hatchback</option>
                                        <option value="mpv" <?php echo ($driverData['vehicle_type'] ?? '') == 'mpv' ? 'selected' : ''; ?>>MPV</option>
                                        <option value="luxury" <?php echo ($driverData['vehicle_type'] ?? '') == 'luxury' ? 'selected' : ''; ?>>Luxury</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-600 mb-2">Vehicle Model</label>
                                    <input type="text" name="vehicle_model" value="<?php echo htmlspecialchars($driverData['vehicle_model'] ?? ''); ?>" 
                                           placeholder="e.g., Toyota Camry" 
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                                </div>
                                <div>
                                    <label class="block text-gray-600 mb-2">Vehicle Year</label>
                                    <input type="number" name="vehicle_year" value="<?php echo htmlspecialchars($driverData['vehicle_year'] ?? ''); ?>" 
                                           min="2000" max="2025" 
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                                </div>
                                <div>
                                    <label class="block text-gray-600 mb-2">License Plate</label>
                                    <input type="text" name="license_plate" value="<?php echo htmlspecialchars($driverData['license_plate'] ?? ''); ?>" 
                                           placeholder="ABC-123-XY" 
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                                </div>
                            </div>
                        </div>

                        <!-- Bank Details Section -->
                        <div id="desktop-bank-section" class="hidden">
                            <h2 class="text-xl font-semibold mb-6">Bank Details</h2>
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-gray-600 mb-2">Bank Name</label>
                                    <select name="bank_name" class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                                        <option value="">Select Bank</option>
                                        <option value="Access Bank" <?php echo ($driverData['bank_name'] ?? '') == 'Access Bank' ? 'selected' : ''; ?>>Access Bank</option>
                                        <option value="GTBank" <?php echo ($driverData['bank_name'] ?? '') == 'GTBank' ? 'selected' : ''; ?>>GTBank</option>
                                        <option value="First Bank" <?php echo ($driverData['bank_name'] ?? '') == 'First Bank' ? 'selected' : ''; ?>>First Bank</option>
                                        <option value="UBA" <?php echo ($driverData['bank_name'] ?? '') == 'UBA' ? 'selected' : ''; ?>>UBA</option>
                                        <option value="Zenith" <?php echo ($driverData['bank_name'] ?? '') == 'Zenith' ? 'selected' : ''; ?>>Zenith Bank</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-gray-600 mb-2">Account Number</label>
                                    <input type="text" name="account_number" value="<?php echo htmlspecialchars($driverData['account_number'] ?? ''); ?>" 
                                           maxlength="10" pattern="\d{10}" 
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-gray-600 mb-2">Account Name</label>
                                    <input type="text" name="account_name" value="<?php echo htmlspecialchars($driverData['account_name'] ?? ''); ?>" 
                                           class="w-full p-3 border border-gray-300 rounded-lg focus:outline-none focus:border-[#ff5e00]">
                                </div>
                            </div>
                        </div>

                        <!-- KYC Documents Section -->
                        <div id="desktop-kyc-section" class="hidden">
                            <h2 class="text-xl font-semibold mb-6">KYC Documents</h2>
                            <div class="grid grid-cols-2 gap-6">
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                                    <i class="fas fa-id-card text-5xl text-gray-400 mb-4"></i>
                                    <p class="text-gray-600 mb-3">Upload Driver's License</p>
                                    <input type="file" name="license_file" accept="image/*,.pdf" class="text-sm">
                                </div>
                                <div class="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center">
                                    <i class="fas fa-passport text-5xl text-gray-400 mb-4"></i>
                                    <p class="text-gray-600 mb-3">Upload Government ID</p>
                                    <input type="file" name="id_file" accept="image/*,.pdf" class="text-sm">
                                </div>
                            </div>
                            <p class="text-sm text-gray-500 mt-4">Accepted formats: JPG, PNG, PDF (Max size: 5MB per file)</p>
                            
                            <?php if (isset($driverData['kyc_status']) && $driverData['kyc_status'] == 'approved'): ?>
                                <div class="mt-4 p-4 bg-green-100 text-green-700 rounded-lg">
                                    <i class="fas fa-check-circle mr-2"></i>Your KYC documents have been approved!
                                </div>
                            <?php elseif (isset($driverData['kyc_status']) && $driverData['kyc_status'] == 'pending'): ?>
                                <div class="mt-4 p-4 bg-yellow-100 text-yellow-700 rounded-lg">
                                    <i class="fas fa-clock mr-2"></i>Your KYC documents are pending review.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="mt-8 flex gap-4">
                            <button type="submit" name="update_profile" class="px-8 py-3 bg-[#ff5e00] text-white rounded-xl font-semibold hover:bg-[#e55500] transition-colors">
                                <i class="fas fa-save mr-2"></i>Save Changes
                            </button>
                            <?php if (!isset($driverData['kyc_status']) || $driverData['kyc_status'] == 'rejected'): ?>
                            <button type="submit" name="upload_documents" class="px-8 py-3 bg-blue-600 text-white rounded-xl font-semibold hover:bg-blue-700 transition-colors">
                                <i class="fas fa-upload mr-2"></i>Upload Documents
                            </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
    function showTab(tab) {
        // Mobile tabs
        const mobileForms = {
            personal: document.getElementById('personal-form'),
            vehicle: document.getElementById('vehicle-form'),
            bank: document.getElementById('bank-form'),
            kyc: document.getElementById('kyc-form')
        };
        
        const mobileTabs = {
            personal: document.getElementById('personal-tab'),
            vehicle: document.getElementById('vehicle-tab'),
            bank: document.getElementById('bank-tab'),
            kyc: document.getElementById('kyc-tab')
        };
        
        // Desktop tabs
        const desktopSections = {
            personal: document.getElementById('desktop-personal-section'),
            vehicle: document.getElementById('desktop-vehicle-section'),
            bank: document.getElementById('desktop-bank-section'),
            kyc: document.getElementById('desktop-kyc-section')
        };
        
        const desktopTabs = {
            personal: document.getElementById('desktop-personal-tab'),
            vehicle: document.getElementById('desktop-vehicle-tab'),
            bank: document.getElementById('desktop-bank-tab'),
            kyc: document.getElementById('desktop-kyc-tab')
        };
        
        // Update mobile
        Object.keys(mobileForms).forEach(key => {
            if (mobileForms[key]) {
                if (key === tab) {
                    mobileForms[key].classList.remove('hidden');
                    if (mobileTabs[key]) {
                        mobileTabs[key].classList.add('bg-white', 'shadow');
                    }
                } else {
                    mobileForms[key].classList.add('hidden');
                    if (mobileTabs[key]) {
                        mobileTabs[key].classList.remove('bg-white', 'shadow');
                    }
                }
            }
        });
        
        // Update desktop
        Object.keys(desktopSections).forEach(key => {
            if (desktopSections[key]) {
                if (key === tab) {
                    desktopSections[key].classList.remove('hidden');
                    if (desktopTabs[key]) {
                        desktopTabs[key].classList.add('border-[#ff5e00]', 'text-[#ff5e00]');
                        desktopTabs[key].classList.remove('text-gray-600');
                    }
                } else {
                    desktopSections[key].classList.add('hidden');
                    if (desktopTabs[key]) {
                        desktopTabs[key].classList.remove('border-[#ff5e00]', 'text-[#ff5e00]');
                        desktopTabs[key].classList.add('text-gray-600');
                    }
                }
            }
        });
    }

    function saveProfile() {
        // Determine which form is currently visible and submit it
        const activeTab = document.querySelector('.bg-white.shadow')?.id.replace('-tab', '') || 'personal';
        
        if (activeTab === 'kyc') {
            document.getElementById('kyc-form').submit();
        } else {
            document.getElementById(activeTab + '-form').submit();
        }
    }

    function checkNotifications() {
        Swal.fire({
            title: 'Notifications',
            html: '<p>🔔 Your profile was viewed 10 times today</p><p>💰 New ride request available</p>',
            icon: 'info',
            confirmButtonColor: '#ff5e00'
        });
    }

    // Show success/error messages
    <?php if ($success_message): ?>
    Swal.fire({
        title: 'Success',
        text: '<?php echo $success_message; ?>',
        icon: 'success',
        confirmButtonColor: '#ff5e00'
    });
    <?php endif; ?>

    <?php if ($error_message): ?>
    Swal.fire({
        title: 'Error',
        text: '<?php echo $error_message; ?>',
        icon: 'error',
        confirmButtonColor: '#ff5e00'
    });
    <?php endif; ?>
    </script>
</body>
</html>     