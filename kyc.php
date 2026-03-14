<?php
session_start();
require_once 'SERVER/API/db-connect.php';

// Check if user is logged in and is driver
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: index.php");
    exit;
}

// If user is not a driver, redirect to appropriate dashboard
if ($_SESSION['role'] !== 'driver') {
    if ($_SESSION['role'] === 'admin') {
        header("Location: admin_dashboard.php");
    } else {
        header("Location: client_dashboard.php");
    }
    exit;
}

$user_id = $_SESSION['user_id'];

// Get driver profile with user details
$stmt = $conn->prepare("
    SELECT u.*, dp.id as driver_profile_id, dp.license_number, dp.license_expiry, 
           dp.verification_status, dp.driver_status, dp.completed_rides
    FROM users u 
    LEFT JOIN driver_profiles dp ON u.id = dp.user_id 
    WHERE u.id = ?
");
$stmt->bind_param("s", $user_id);
$stmt->execute();
$driver = $stmt->get_result()->fetch_assoc();

if (!$driver || !isset($driver['driver_profile_id']) || empty($driver['driver_profile_id'])) {
    // Create driver profile if it doesn't exist
    $driver_profile_id = bin2hex(random_bytes(16));
    $license_number = 'PENDING_' . substr($user_id, 0, 8);
    $license_expiry = date('Y-m-d', strtotime('+1 year'));

    $stmt = $conn->prepare("INSERT INTO driver_profiles (id, user_id, license_number, license_expiry, driver_status, verification_status) VALUES (?, ?, ?, ?, 'offline', 'pending')");
    $stmt->bind_param("ssss", $driver_profile_id, $user_id, $license_number, $license_expiry);

    if ($stmt->execute()) {
        // Refresh driver data
        $stmt = $conn->prepare("
            SELECT u.*, dp.id as driver_profile_id, dp.license_number, dp.license_expiry, 
                   dp.verification_status, dp.driver_status, dp.completed_rides
            FROM users u 
            JOIN driver_profiles dp ON u.id = dp.user_id 
            WHERE u.id = ?
        ");
        $stmt->bind_param("s", $user_id);
        $stmt->execute();
        $driver = $stmt->get_result()->fetch_assoc();
    } else {
        error_log("Failed to create driver profile: " . $conn->error);
    }
}

// Check if already in approval queue
$pending_approval = false;
if (isset($driver['driver_profile_id'])) {
    $stmt = $conn->prepare("SELECT * FROM driver_approval_queue WHERE driver_id = ? AND status = 'pending'");
    $stmt->bind_param("s", $driver['driver_profile_id']);
    $stmt->execute();
    $pending_approval = $stmt->get_result()->fetch_assoc();
}

// Get existing documents
$existing_docs = [];
if (isset($driver['driver_profile_id'])) {
    $stmt = $conn->prepare("SELECT * FROM driver_kyc_documents WHERE driver_id = ? ORDER BY document_type");
    $stmt->bind_param("s", $driver['driver_profile_id']);
    $stmt->execute();
    $existing_docs_result = $stmt->get_result();
    while ($doc = $existing_docs_result->fetch_assoc()) {
        $existing_docs[$doc['document_type']] = $doc;
    }
}

// Get unread notifications count
$notificationCount = 0;
$notifQuery = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
$notifStmt = $conn->prepare($notifQuery);
if ($notifStmt) {
    $notifStmt->bind_param("s", $user_id);
    $notifStmt->execute();
    $notifResult = $notifStmt->get_result();
    $notificationCount = $notifResult->fetch_assoc()['count'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedly • Driver KYC Verification</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="./CSS/kyc.css">
    <!-- Tailwind for quick fixes -->
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body>
    <div class="dashboard-container">
        <!-- Mobile View -->
        <div class="mobile-view">
            <?php include_once './components/mobile-driver-nav.php'; ?>

            <div class="header">
                <div class="user-info">
                    <h1>KYC verification</h1>
                    <p><?php echo htmlspecialchars($driver['full_name'] ?? 'Driver'); ?> • Driver</p>
                </div>
                <button class="notification-btn" onclick="window.location.href='notifications.php'">
                    <i class="fas fa-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="notification-badge"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- Status Card -->
            <div class="kyc-status-card">
                <div>
                    <span class="status-badge" style="background: rgba(255,255,255,0.3); color: white;">
                        <?php if ($pending_approval): ?>
                            PENDING REVIEW
                        <?php elseif (isset($driver['verification_status']) && $driver['verification_status'] == 'approved'): ?>
                            VERIFIED
                        <?php elseif (isset($driver['verification_status']) && $driver['verification_status'] == 'rejected'): ?>
                            REJECTED
                        <?php else: ?>
                            NOT SUBMITTED
                        <?php endif; ?>
                    </span>
                    <h2 style="font-size: 24px; font-weight: 700; margin-top: 12px;">
                        <?php if (isset($driver['verification_status']) && $driver['verification_status'] == 'approved'): ?>
                            You are verified!
                        <?php else: ?>
                            Verify your identity
                        <?php endif; ?>
                    </h2>
                    <p style="opacity: 0.9; font-size: 14px; margin-top: 4px;">
                        <?php if (isset($driver['verification_status']) && $driver['verification_status'] == 'approved'): ?>
                            You can now accept rides and withdraw earnings.
                        <?php elseif (isset($driver['verification_status']) && $driver['verification_status'] == 'rejected'): ?>
                            Please re-upload your documents for verification.
                        <?php else: ?>
                            Complete KYC to unlock unlimited ride requests & withdrawals.
                        <?php endif; ?>
                    </p>
                </div>
                <div style="background: rgba(255,255,255,0.2); padding: 16px; border-radius: 16px;">
                    <i class="fas fa-id-card" style="font-size: 40px;"></i>
                </div>
            </div>

            <?php if (isset($driver['verification_status']) && $driver['verification_status'] == 'rejected'): ?>
                <div style="background: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; padding: 16px; margin: 16px; border-radius: 8px;">
                    <p style="font-weight: 600;">Verification Failed</p>
                    <p style="font-size: 14px;">Your KYC was rejected. Please upload correct documents and try again.</p>
                </div>
            <?php endif; ?>

            <?php if ($pending_approval): ?>
                <div style="background: #fef3c7; border-left: 4px solid #f59e0b; color: #92400e; padding: 16px; margin: 16px; border-radius: 8px;">
                    <p style="font-weight: 600;">KYC Pending Review</p>
                    <p style="font-size: 14px;">Your documents are being reviewed by our team. You'll be notified once approved.</p>
                </div>
            <?php endif; ?>

            <!-- Step Progress -->
            <div class="kyc-step-indicator">
                <div class="step <?php echo isset($existing_docs['drivers_license_front']) ? 'completed' : 'active'; ?>">
                    <div class="step-circle">1</div>
                    <span class="step-label">Document</span>
                </div>
                <div class="step <?php echo isset($existing_docs['selfie_with_id']) ? 'completed' : ''; ?>">
                    <div class="step-circle">2</div>
                    <span class="step-label">Selfie</span>
                </div>
                <div class="step <?php echo isset($existing_docs['insurance']) || isset($existing_docs['vehicle_registration']) ? 'completed' : ''; ?>">
                    <div class="step-circle">3</div>
                    <span class="step-label">Vehicle</span>
                </div>
                <div class="step">
                    <div class="step-circle">4</div>
                    <span class="step-label">Review</span>
                </div>
            </div>

            <!-- KYC Form -->
            <form id="kycForm" enctype="multipart/form-data" method="POST">
                <input type="hidden" name="driver_id" value="<?php echo $driver['driver_profile_id'] ?? ''; ?>">

                <!-- Personal Details -->
                <div class="kyc-form-card">
                    <div class="form-section-title">
                        <i class="fas fa-user-circle"></i> Personal information
                    </div>
                    <input type="text" class="input-field" name="full_name" value="<?php echo htmlspecialchars($driver['full_name'] ?? ''); ?>" readonly>
                    <input type="email" class="input-field" name="email" value="<?php echo htmlspecialchars($driver['email'] ?? ''); ?>" readonly>
                    <input type="tel" class="input-field" name="phone" value="<?php echo htmlspecialchars($driver['phone_number'] ?? ''); ?>" readonly>
                    <input type="date" class="input-field" name="date_of_birth" id="dateOfBirth" required>
                    <input type="text" class="input-field" name="license_number" value="<?php echo htmlspecialchars(isset($driver['license_number']) && $driver['license_number'] != 'PENDING' ? $driver['license_number'] : ''); ?>" placeholder="Driver license number" required>
                    <input type="date" class="input-field" name="license_expiry" id="licenseExpiry" value="<?php echo isset($driver['license_expiry']) && $driver['license_expiry'] != '0000-00-00' ? $driver['license_expiry'] : ''; ?>" required>
                </div>

                <!-- Document Upload -->
                <div class="kyc-form-card">
                    <div class="form-section-title">
                        <i class="fas fa-id-card"></i> Upload documents
                    </div>

                    <!-- License Front -->
                    <div class="upload-box <?php echo !isset($existing_docs['drivers_license_front']) ? 'required' : ''; ?>">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <span class="upload-text">Upload driver's license (front) *</span>
                        <span class="upload-hint">JPG, PNG or PDF • Max 10MB</span>
                        <input type="file" name="license_front" class="hidden-file-input" accept=".jpg,.jpeg,.png,.pdf">
                        <div class="validation-error" id="error-license_front">Please upload your license (front)</div>
                    </div>

                    <?php if (isset($existing_docs['drivers_license_front'])): ?>
                        <div class="document-preview" onclick="viewDocument('<?php echo $existing_docs['drivers_license_front']['document_url']; ?>', 'License Front')">
                            <div class="doc-icon"><i class="fas fa-id-card"></i></div>
                            <div class="doc-details">
                                <div class="doc-name">License Front</div>
                            </div>
                            <div class="doc-status">
                                <span class="status-badge <?php echo $existing_docs['drivers_license_front']['verification_status']; ?>">
                                    <?php echo ucfirst($existing_docs['drivers_license_front']['verification_status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- License Back (Optional but recommended) -->
                    <div class="upload-box mt-4">
                        <i class="fas fa-cloud-upload-alt upload-icon"></i>
                        <span class="upload-text">Upload driver's license (back) - Optional</span>
                        <span class="upload-hint">JPG, PNG or PDF • Max 10MB</span>
                        <input type="file" name="license_back" class="hidden-file-input" accept=".jpg,.jpeg,.png,.pdf">
                    </div>

                    <?php if (isset($existing_docs['drivers_license_back'])): ?>
                        <div class="document-preview" onclick="viewDocument('<?php echo $existing_docs['drivers_license_back']['document_url']; ?>', 'License Back')">
                            <div class="doc-icon"><i class="fas fa-id-card"></i></div>
                            <div class="doc-details">
                                <div class="doc-name">License Back</div>
                            </div>
                            <div class="doc-status">
                                <span class="status-badge <?php echo $existing_docs['drivers_license_back']['verification_status']; ?>">
                                    <?php echo ucfirst($existing_docs['drivers_license_back']['verification_status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Selfie -->
                    <div class="upload-box mt-4 <?php echo !isset($existing_docs['selfie_with_id']) ? 'required' : ''; ?>">
                        <i class="fas fa-camera upload-icon"></i>
                        <span class="upload-text">Upload selfie with ID *</span>
                        <span class="upload-hint">Hold ID next to your face</span>
                        <input type="file" name="selfie" class="hidden-file-input" accept=".jpg,.jpeg,.png">
                        <div class="validation-error" id="error-selfie">Please upload a selfie with your ID</div>
                    </div>

                    <?php if (isset($existing_docs['selfie_with_id'])): ?>
                        <div class="document-preview mt-2" onclick="viewDocument('<?php echo $existing_docs['selfie_with_id']['document_url']; ?>', 'Selfie with ID')">
                            <div class="doc-icon"><i class="fas fa-camera"></i></div>
                            <div class="doc-details">
                                <div class="doc-name">Selfie with ID</div>
                            </div>
                            <div class="doc-status">
                                <span class="status-badge <?php echo $existing_docs['selfie_with_id']['verification_status']; ?>">
                                    <?php echo ucfirst($existing_docs['selfie_with_id']['verification_status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Insurance (Optional) -->
                    <div class="upload-box mt-4">
                        <i class="fas fa-shield-alt upload-icon"></i>
                        <span class="upload-text">Insurance document - Optional</span>
                        <span class="upload-hint">JPG, PNG or PDF • Max 10MB</span>
                        <input type="file" name="insurance" class="hidden-file-input" accept=".jpg,.jpeg,.png,.pdf">
                    </div>

                    <?php if (isset($existing_docs['insurance'])): ?>
                        <div class="document-preview" onclick="viewDocument('<?php echo $existing_docs['insurance']['document_url']; ?>', 'Insurance')">
                            <div class="doc-icon"><i class="fas fa-shield-alt"></i></div>
                            <div class="doc-details">
                                <div class="doc-name">Insurance</div>
                            </div>
                            <div class="doc-status">
                                <span class="status-badge <?php echo $existing_docs['insurance']['verification_status']; ?>">
                                    <?php echo ucfirst($existing_docs['insurance']['verification_status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Vehicle Registration (Optional) -->
                    <div class="upload-box mt-4">
                        <i class="fas fa-car upload-icon"></i>
                        <span class="upload-text">Vehicle registration - Optional</span>
                        <span class="upload-hint">JPG, PNG or PDF • Max 10MB</span>
                        <input type="file" name="vehicle_registration" class="hidden-file-input" accept=".jpg,.jpeg,.png,.pdf">
                    </div>

                    <?php if (isset($existing_docs['vehicle_registration'])): ?>
                        <div class="document-preview" onclick="viewDocument('<?php echo $existing_docs['vehicle_registration']['document_url']; ?>', 'Vehicle Registration')">
                            <div class="doc-icon"><i class="fas fa-car"></i></div>
                            <div class="doc-details">
                                <div class="doc-name">Vehicle Registration</div>
                            </div>
                            <div class="doc-status">
                                <span class="status-badge <?php echo $existing_docs['vehicle_registration']['verification_status']; ?>">
                                    <?php echo ucfirst($existing_docs['vehicle_registration']['verification_status']); ?>
                                </span>
                            </div>
                        </div>
                    <?php endif; ?>

                    <?php if (!isset($existing_docs['selfie_with_id'])): ?>
                        <div style="background: #fff7ed; color: #9a3412; padding: 12px; border-radius: 12px; margin-top: 12px; font-size: 14px; display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <span>Selfie with ID is required for verification</span>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="kyc-submit-btn"
                    <?php echo ($pending_approval || (isset($driver['verification_status']) && $driver['verification_status'] == 'approved')) ? 'disabled' : ''; ?>>
                    <i class="fas fa-check-circle"></i>
                    <?php
                    if ($pending_approval) echo 'Pending Review';
                    elseif (isset($driver['verification_status']) && $driver['verification_status'] == 'approved') echo 'Already Verified';
                    elseif (isset($driver['verification_status']) && $driver['verification_status'] == 'rejected') echo 'Resubmit KYC';
                    else echo 'Submit KYC for review';
                    ?>
                </button>

                <div class="kyc-info-note">
                    <i class="fas fa-shield-alt" style="color: #ff5e00; margin-right: 8px;"></i>
                    Your data is encrypted and secure. Verification usually takes 5–10 minutes.
                </div>
            </form>
        </div>

        <!-- Desktop View - FIXED LAYOUT -->
        <div class="desktop-view">
            <div class="desktop-sidebar">
                <div class="logo">
                    <img src="./main-assets/logo-no-background.png" alt="Speedly Logo" class="h-42">
                </div>

                <!-- Desktop Navigation -->
                <?php require_once './components/desktop-driver-nav.php'; ?>

                <!-- User Profile -->
                <div class="user-profile" onclick="window.location.href='driver_profile.php'">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($driver['full_name'] ?? 'D', 0, 1)); ?>
                    </div>
                    <div class="profile-info">
                        <h3><?php echo htmlspecialchars($driver['full_name'] ?? 'Driver'); ?></h3>
                        <p id="desktopStatusText"><?php echo ($driver['verification_status'] ?? 'pending') == 'approved' ? 'Verified Driver' : 'Pending Verification'; ?></p>
                    </div>
                </div>
            </div>

            <div class="desktop-main">
                <!-- Header -->
                <div class="desktop-header">
                    <div>
                        <h1>Driver verification</h1>
                        <p>Complete your KYC to start earning with full benefits.</p>
                    </div>
                    <button class="notification-btn" onclick="window.location.href='notifications.php'">
                        <i class="fas fa-bell"></i>
                        <?php if ($notificationCount > 0): ?>
                            <span class="notification-badge"><?php echo $notificationCount; ?></span>
                        <?php endif; ?>
                    </button>
                </div>

                <!-- Status Card for Desktop -->
                <div class="kyc-status-card" style="margin: 0 0 24px 0;">
                    <div>
                        <span class="status-badge" style="background: rgba(255,255,255,0.3); color: white;">
                            <?php if ($pending_approval): ?>
                                PENDING REVIEW
                            <?php elseif (isset($driver['verification_status']) && $driver['verification_status'] == 'approved'): ?>
                                VERIFIED
                            <?php elseif (isset($driver['verification_status']) && $driver['verification_status'] == 'rejected'): ?>
                                REJECTED
                            <?php else: ?>
                                NOT SUBMITTED
                            <?php endif; ?>
                        </span>
                        <h2 style="font-size: 24px; font-weight: 700; margin-top: 12px;">
                            <?php if (isset($driver['verification_status']) && $driver['verification_status'] == 'approved'): ?>
                                You are verified!
                            <?php else: ?>
                                Verify your identity
                            <?php endif; ?>
                        </h2>
                        <p style="opacity: 0.9; font-size: 14px; margin-top: 4px;">
                            <?php if (isset($driver['verification_status']) && $driver['verification_status'] == 'approved'): ?>
                                You can now accept rides and withdraw earnings.
                            <?php elseif (isset($driver['verification_status']) && $driver['verification_status'] == 'rejected'): ?>
                                Please re-upload your documents for verification.
                            <?php else: ?>
                                Complete KYC to unlock unlimited ride requests & withdrawals.
                            <?php endif; ?>
                        </p>
                    </div>
                    <div style="background: rgba(255,255,255,0.2); padding: 16px; border-radius: 16px;">
                        <i class="fas fa-id-card" style="font-size: 40px;"></i>
                    </div>
                </div>

                <?php if (isset($driver['verification_status']) && $driver['verification_status'] == 'rejected'): ?>
                    <div style="background: #fee2e2; border-left: 4px solid #ef4444; color: #991b1b; padding: 16px; margin-bottom: 24px; border-radius: 8px;">
                        <p style="font-weight: 600;">Verification Failed</p>
                        <p style="font-size: 14px;">Your KYC was rejected. Please upload correct documents and try again.</p>
                    </div>
                <?php endif; ?>

                <?php if ($pending_approval): ?>
                    <div style="background: #fef3c7; border-left: 4px solid #f59e0b; color: #92400e; padding: 16px; margin-bottom: 24px; border-radius: 8px;">
                        <p style="font-weight: 600;">KYC Pending Review</p>
                        <p style="font-size: 14px;">Your documents are being reviewed by our team. You'll be notified once approved.</p>
                    </div>
                <?php endif; ?>

                <!-- Step Progress for Desktop -->
                <div class="kyc-step-indicator" style="margin: 0 0 24px 0;">
                    <div class="step <?php echo isset($existing_docs['drivers_license_front']) ? 'completed' : 'active'; ?>">
                        <div class="step-circle">1</div>
                        <span class="step-label">Document</span>
                    </div>
                    <div class="step <?php echo isset($existing_docs['selfie_with_id']) ? 'completed' : ''; ?>">
                        <div class="step-circle">2</div>
                        <span class="step-label">Selfie</span>
                    </div>
                    <div class="step <?php echo isset($existing_docs['insurance']) || isset($existing_docs['vehicle_registration']) ? 'completed' : ''; ?>">
                        <div class="step-circle">3</div>
                        <span class="step-label">Vehicle</span>
                    </div>
                    <div class="step">
                        <div class="step-circle">4</div>
                        <span class="step-label">Review</span>
                    </div>
                </div>

                <!-- Desktop KYC Form -->
                <form id="desktopKycForm" enctype="multipart/form-data" method="POST">
                    <input type="hidden" name="driver_id" value="<?php echo $driver['driver_profile_id'] ?? ''; ?>">

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">
                        <!-- Personal Details Column -->
                        <div class="kyc-form-card" style="margin: 0;">
                            <h2 style="font-size: 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-user-circle" style="color: #ff5e00;"></i> Identity details
                            </h2>
                            <input type="text" class="input-field" name="full_name" value="<?php echo htmlspecialchars($driver['full_name'] ?? ''); ?>" readonly>
                            <input type="email" class="input-field" name="email" value="<?php echo htmlspecialchars($driver['email'] ?? ''); ?>" readonly>
                            <input type="tel" class="input-field" name="phone" value="<?php echo htmlspecialchars($driver['phone_number'] ?? ''); ?>" readonly>
                            <input type="date" class="input-field" name="date_of_birth" id="desktopDateOfBirth" required>
                            <input type="text" class="input-field" name="license_number" placeholder="License number" value="<?php echo htmlspecialchars(isset($driver['license_number']) && $driver['license_number'] != 'PENDING' ? $driver['license_number'] : ''); ?>" required>
                            <input type="date" class="input-field" name="license_expiry" id="desktopLicenseExpiry" value="<?php echo isset($driver['license_expiry']) && $driver['license_expiry'] != '0000-00-00' ? $driver['license_expiry'] : ''; ?>" required>
                        </div>

                        <!-- Document Upload Column -->
                        <div class="kyc-form-card" style="margin: 0;">
                            <h2 style="font-size: 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 8px;">
                                <i class="fas fa-id-card" style="color: #ff5e00;"></i> Documents
                            </h2>

                            <!-- License Front -->
                            <div class="upload-box <?php echo !isset($existing_docs['drivers_license_front']) ? 'required' : ''; ?>" style="margin-bottom: 12px;">
                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                <span class="upload-text">License Front *</span>
                                <span class="upload-hint">JPG, PNG, PDF (Max 10MB)</span>
                                <input type="file" name="license_front" class="hidden-file-input" accept=".jpg,.jpeg,.png,.pdf">
                            </div>

                            <?php if (isset($existing_docs['drivers_license_front'])): ?>
                                <div class="document-preview mb-3" onclick="viewDocument('<?php echo $existing_docs['drivers_license_front']['document_url']; ?>', 'License Front')">
                                    <div class="doc-icon"><i class="fas fa-id-card"></i></div>
                                    <div class="doc-details">
                                        <div class="doc-name">License Front</div>
                                    </div>
                                    <div class="doc-status">
                                        <span class="status-badge <?php echo $existing_docs['drivers_license_front']['verification_status']; ?>">
                                            <?php echo ucfirst($existing_docs['drivers_license_front']['verification_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- License Back -->
                            <div class="upload-box mt-3">
                                <i class="fas fa-cloud-upload-alt upload-icon"></i>
                                <span class="upload-text">License Back (Optional)</span>
                                <span class="upload-hint">JPG, PNG, PDF (Max 10MB)</span>
                                <input type="file" name="license_back" class="hidden-file-input" accept=".jpg,.jpeg,.png,.pdf">
                            </div>

                            <?php if (isset($existing_docs['drivers_license_back'])): ?>
                                <div class="document-preview mt-2" onclick="viewDocument('<?php echo $existing_docs['drivers_license_back']['document_url']; ?>', 'License Back')">
                                    <div class="doc-icon"><i class="fas fa-id-card"></i></div>
                                    <div class="doc-details">
                                        <div class="doc-name">License Back</div>
                                    </div>
                                    <div class="doc-status">
                                        <span class="status-badge <?php echo $existing_docs['drivers_license_back']['verification_status']; ?>">
                                            <?php echo ucfirst($existing_docs['drivers_license_back']['verification_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Selfie -->
                            <div class="upload-box mt-3 <?php echo !isset($existing_docs['selfie_with_id']) ? 'required' : ''; ?>">
                                <i class="fas fa-camera upload-icon"></i>
                                <span class="upload-text">Selfie with ID *</span>
                                <span class="upload-hint">Hold ID next to your face</span>
                                <input type="file" name="selfie" class="hidden-file-input" accept=".jpg,.jpeg,.png">
                            </div>

                            <?php if (isset($existing_docs['selfie_with_id'])): ?>
                                <div class="document-preview mt-2" onclick="viewDocument('<?php echo $existing_docs['selfie_with_id']['document_url']; ?>', 'Selfie with ID')">
                                    <div class="doc-icon"><i class="fas fa-camera"></i></div>
                                    <div class="doc-details">
                                        <div class="doc-name">Selfie with ID</div>
                                    </div>
                                    <div class="doc-status">
                                        <span class="status-badge <?php echo $existing_docs['selfie_with_id']['verification_status']; ?>">
                                            <?php echo ucfirst($existing_docs['selfie_with_id']['verification_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Insurance -->
                            <div class="upload-box mt-3">
                                <i class="fas fa-shield-alt upload-icon"></i>
                                <span class="upload-text">Insurance (Optional)</span>
                                <span class="upload-hint">JPG, PNG, PDF (Max 10MB)</span>
                                <input type="file" name="insurance" class="hidden-file-input" accept=".jpg,.jpeg,.png,.pdf">
                            </div>

                            <?php if (isset($existing_docs['insurance'])): ?>
                                <div class="document-preview mt-2" onclick="viewDocument('<?php echo $existing_docs['insurance']['document_url']; ?>', 'Insurance')">
                                    <div class="doc-icon"><i class="fas fa-shield-alt"></i></div>
                                    <div class="doc-details">
                                        <div class="doc-name">Insurance</div>
                                    </div>
                                    <div class="doc-status">
                                        <span class="status-badge <?php echo $existing_docs['insurance']['verification_status']; ?>">
                                            <?php echo ucfirst($existing_docs['insurance']['verification_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Vehicle Registration -->
                            <div class="upload-box mt-3">
                                <i class="fas fa-car upload-icon"></i>
                                <span class="upload-text">Vehicle Registration (Optional)</span>
                                <span class="upload-hint">JPG, PNG, PDF (Max 10MB)</span>
                                <input type="file" name="vehicle_registration" class="hidden-file-input" accept=".jpg,.jpeg,.png,.pdf">
                            </div>

                            <?php if (isset($existing_docs['vehicle_registration'])): ?>
                                <div class="document-preview mt-2" onclick="viewDocument('<?php echo $existing_docs['vehicle_registration']['document_url']; ?>', 'Vehicle Registration')">
                                    <div class="doc-icon"><i class="fas fa-car"></i></div>
                                    <div class="doc-details">
                                        <div class="doc-name">Vehicle Registration</div>
                                    </div>
                                    <div class="doc-status">
                                        <span class="status-badge <?php echo $existing_docs['vehicle_registration']['verification_status']; ?>">
                                            <?php echo ucfirst($existing_docs['vehicle_registration']['verification_status']); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="kyc-submit-btn" style="width: 300px; margin: 0 auto; display: block;"
                        <?php echo ($pending_approval || (isset($driver['verification_status']) && $driver['verification_status'] == 'approved')) ? 'disabled' : ''; ?>>
                        <i class="fas fa-paper-plane"></i> 
                        <?php
                        if ($pending_approval) echo 'Pending Review';
                        elseif (isset($driver['verification_status']) && $driver['verification_status'] == 'approved') echo 'Already Verified';
                        elseif (isset($driver['verification_status']) && $driver['verification_status'] == 'rejected') echo 'Resubmit KYC';
                        else echo 'Submit KYC for review';
                        ?>
                    </button>

                    <div class="kyc-info-note" style="margin: 20px 0 0 0;">
                        <i class="fas fa-shield-alt" style="color: #ff5e00; margin-right: 8px;"></i>
                        Your data is encrypted and secure. Verification usually takes 5–10 minutes.
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Document Preview Modal -->
    <div class="modal" id="documentModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="text-xl font-bold" id="documentModalTitle">Document Preview</h3>
                <button class="close-btn" onclick="closeDocumentModal()">&times;</button>
            </div>
            <div id="documentContent" style="text-align: center;"></div>
        </div>
    </div>

    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Document viewing function
        function viewDocument(url, title) {
            const modal = document.getElementById('documentModal');
            const content = document.getElementById('documentContent');
            const modalTitle = document.getElementById('documentModalTitle');

            modalTitle.textContent = title;

            if (url.match(/\.(jpg|jpeg|png|gif|webp)$/i)) {
                content.innerHTML = `<img src="${url}" class="document-image" alt="Document">`;
            } else if (url.match(/\.pdf$/i)) {
                content.innerHTML = `
                <div style="padding: 32px;">
                    <i class="fas fa-file-pdf" style="font-size: 64px; color: #ef4444; margin-bottom: 16px;"></i>
                    <p style="margin-bottom: 16px;">PDF Document</p>
                    <a href="${url}" target="_blank" style="background: #3b82f6; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; display: inline-block;">
                        <i class="fas fa-external-link-alt" style="margin-right: 8px;"></i>Open PDF
                    </a>
                </div>
            `;
            } else {
                content.innerHTML = `
                <div style="padding: 32px;">
                    <i class="fas fa-file" style="font-size: 64px; color: #6b7280; margin-bottom: 16px;"></i>
                    <p style="margin-bottom: 16px;">Document</p>
                    <a href="${url}" target="_blank" style="background: #3b82f6; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; display: inline-block;">
                        <i class="fas fa-download" style="margin-right: 8px;"></i>Download
                    </a>
                </div>
            `;
            }

            modal.classList.add('show');
        }

        function closeDocumentModal() {
            document.getElementById('documentModal').classList.remove('show');
        }

        // File upload handling
        document.querySelectorAll('.upload-box').forEach(box => {
            const fileInput = box.querySelector('.hidden-file-input');
            if (!fileInput) return;

            box.addEventListener('click', function(e) {
                if (e.target === fileInput) return;
                fileInput.click();
            });

            fileInput.addEventListener('change', function() {
                if (this.files.length > 0) {
                    const fileName = this.files[0].name;

                    // Remove any existing file name span
                    const existingSpan = box.querySelector('.selected-file');
                    if (existingSpan) existingSpan.remove();

                    // Show selected file name
                    const fileNameSpan = document.createElement('span');
                    fileNameSpan.className = 'selected-file';
                    fileNameSpan.innerHTML = '<i class="fas fa-check-circle"></i> ' + fileName;
                    box.appendChild(fileNameSpan);

                    // Hide error message
                    const errorDiv = box.querySelector('.validation-error');
                    if (errorDiv) errorDiv.style.display = 'none';
                }
            });
        });

        // Form submission
        function handleFormSubmit(form, event) {
            event.preventDefault();

            const formData = new FormData(form);

            // Validate required fields
            let isValid = true;
            form.querySelectorAll('[required]').forEach(field => {
                if (!field.value && field.type !== 'file') {
                    isValid = false;
                }
            });

            // Check if files are selected for required uploads
            if (!formData.get('license_front') && !<?php echo isset($existing_docs['drivers_license_front']) ? 'true' : 'false'; ?>) {
                isValid = false;
                const errorDiv = document.getElementById('error-license_front');
                if (errorDiv) errorDiv.style.display = 'block';
            }
            
            if (!formData.get('selfie') && !<?php echo isset($existing_docs['selfie_with_id']) ? 'true' : 'false'; ?>) {
                isValid = false;
                const errorDiv = document.getElementById('error-selfie');
                if (errorDiv) errorDiv.style.display = 'block';
            }

            if (!isValid) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Fields',
                    text: 'Please fill in all required fields and upload required documents.',
                    confirmButtonColor: '#ff5e00'
                });
                return;
            }

            // Show loading
            Swal.fire({
                title: 'Submitting...',
                text: 'Please wait while we upload your documents',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });

            fetch('SERVER/API/submit_kyc.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: 'Your KYC has been submitted for review.',
                            confirmButtonColor: '#ff5e00'
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: data.message || 'Failed to submit KYC. Please try again.',
                            confirmButtonColor: '#ff5e00'
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'An error occurred. Please try again.',
                        confirmButtonColor: '#ff5e00'
                    });
                });
        }

        document.getElementById('kycForm')?.addEventListener('submit', function(e) {
            handleFormSubmit(this, e);
        });

        document.getElementById('desktopKycForm')?.addEventListener('submit', function(e) {
            handleFormSubmit(this, e);
        });

        // Close modal when clicking outside
        document.getElementById('documentModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeDocumentModal();
            }
        });

        // Responsive handling
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
    </script>
</body>
 
</html>