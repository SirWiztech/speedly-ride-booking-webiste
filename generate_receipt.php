<?php
session_start();
require_once 'SERVER/API/db-connect.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: form.php");
    exit;
}

$ride_id = $_GET['ride_id'] ?? '';
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['fullname'];
$user_role = $_SESSION['role'] ?? 'client';

// If no ride ID provided, redirect to ride history
if (empty($ride_id)) {
    header("Location: ride_history.php");
    exit;
}

// Fetch ride data from database
$rideQuery = "SELECT r.*, 
              u.full_name as client_name,
              u.email as client_email,
              u.phone_number as client_phone,
              dr.full_name as driver_name,
              dr.email as driver_email,
              dr.phone_number as driver_phone,
              dv.vehicle_model,
              dv.vehicle_color,
              dv.plate_number,
              dv.vehicle_type
              FROM rides r
              LEFT JOIN client_profiles cp ON r.client_id = cp.id
              LEFT JOIN users u ON cp.user_id = u.id
              LEFT JOIN driver_profiles dp ON r.driver_id = dp.id
              LEFT JOIN users dr ON dp.user_id = dr.id
              LEFT JOIN driver_vehicles dv ON dp.id = dv.driver_id
              WHERE r.id = ?";

$rideStmt = $conn->prepare($rideQuery);
$rideStmt->bind_param("s", $ride_id);
$rideStmt->execute();
$rideResult = $rideStmt->get_result();

if ($rideResult->num_rows == 0) {
    header("Location: ride_history.php");
    exit;
}

$ride = $rideResult->fetch_assoc();

// Get payment transaction
$paymentQuery = "SELECT * FROM wallet_transactions WHERE ride_id = ?";
$paymentStmt = $conn->prepare($paymentQuery);
$paymentStmt->bind_param("s", $ride_id);
$paymentStmt->execute();
$paymentResult = $paymentStmt->get_result();
$payment = $paymentResult->fetch_assoc();

// Get current wallet balance
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

// Calculate fare breakdown
$base_fare = 500;
$distance_fare = ($ride['distance_km'] ?? 0) * 1000;
$service_fee = ($ride['total_fare'] ?? 0) * 0.05;
$platform_commission = ($ride['total_fare'] ?? 0) * 0.2;
$driver_payout = ($ride['total_fare'] ?? 0) - $platform_commission;

// Format ride number
$ride_number = $ride['ride_number'] ?? 'SPD' . strtoupper(substr($ride_id, -8));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Speedly • Ride Receipt</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- html2pdf library for PDF generation -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }
        
        .receipt-container {
            max-width: 800px;
            margin: 30px auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .receipt-header {
            background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .logo-wrapper {
            display: flex;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .logo-image {
            height: 60px;
            width: auto;
            filter: brightness(0) invert(1);
        }
        
        .receipt-header h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 5px;
            letter-spacing: 1px;
        }
        
        .receipt-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .receipt-content {
            padding: 40px;
        }
        
        .receipt-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #eee;
        }
        
        .receipt-section-title {
            font-size: 16px;
            font-weight: 600;
            color: #ff5e00;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .receipt-section-title i {
            font-size: 18px;
        }
        
        .receipt-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 15px;
        }
        
        .receipt-label {
            color: #666;
            font-weight: 400;
        }
        
        .receipt-value {
            font-weight: 600;
            color: #333;
            text-align: right;
            max-width: 60%;
        }
        
        .receipt-total {
            background: #fff8f3;
            padding: 20px;
            border-radius: 15px;
            margin-top: 20px;
            border: 2px solid #ff5e00;
        }
        
        .receipt-total .receipt-row {
            font-size: 16px;
            margin-bottom: 12px;
        }
        
        .receipt-total .grand-total {
            font-size: 22px;
            font-weight: 700;
            color: #ff5e00;
            border-top: 2px solid #ff5e00;
            padding-top: 15px;
            margin-top: 15px;
        }
        
        .qr-code {
            text-align: center;
            margin: 30px 0;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 15px;
        }
        
        .qr-code img {
            width: 120px;
            height: 120px;
        }
        
        .action-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 15px 30px;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(255, 94, 0, 0.2);
        }
        
        .btn-secondary {
            background: #f8f9fa;
            color: #666;
            border: 1px solid #ddd;
        }
        
        .btn-secondary:hover {
            background: #e9ecef;
            transform: translateY(-3px);
        }
        
        .btn i {
            font-size: 18px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 6px 16px;
            border-radius: 50px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-paid {
            background: #d4edda;
            color: #155724;
        }
        
        .status-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .driver-info-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-top: 15px;
        }
        
        .driver-info-row {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .driver-avatar {
            width: 50px;
            height: 50px;
            background: #ff5e00;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
            font-weight: 600;
        }
        
        @media print {
            .action-buttons, .no-print {
                display: none !important;
            }
            
            .receipt-container {
                box-shadow: none;
                margin: 0;
                border: 1px solid #ddd;
            }
        }
        
        @media (max-width: 768px) {
            .receipt-content {
                padding: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .receipt-row {
                flex-direction: column;
                gap: 5px;
            }
            
            .receipt-value {
                text-align: left;
                max-width: 100%;
            }
        }

        .thank-you-message {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: linear-gradient(135deg, #fff5f0 0%, #fff 100%);
            border-radius: 15px;
        }
        
        .thank-you-message p {
            color: #ff5e00;
            font-size: 16px;
            font-weight: 500;
        }
        
        .thank-you-message i {
            color: #ff5e00;
            font-size: 24px;
            margin-bottom: 10px;
        }
    </style>
</head>
<body class="bg-gray-100 p-4">
    <div class="receipt-container" id="receipt-content">
        <!-- Header with Logo -->
        <div class="receipt-header">
            <div class="logo-wrapper">
                <img src="./main-assets/logo-no-background.png" alt="Speedly" class="logo-image">
            </div>
            <h1>RIDE RECEIPT</h1>
            <p>Your trusted ride partner</p>
        </div>
        
        <!-- Content -->
        <div class="receipt-content">
            <!-- Receipt Info -->
            <div class="receipt-section">
                <div class="receipt-section-title">
                    <i class="fas fa-receipt"></i> Receipt Information
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Receipt Number:</span>
                    <span class="receipt-value">#<?php echo $ride_number; ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Date & Time:</span>
                    <span class="receipt-value"><?php echo date('d M Y, h:i A', strtotime($ride['created_at'])); ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Payment Method:</span>
                    <span class="receipt-value">Speedly Wallet</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Transaction ID:</span>
                    <span class="receipt-value"><?php echo $payment['reference'] ?? 'WAL-' . strtoupper(substr($ride_id, -8)); ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Payment Status:</span>
                    <span class="receipt-value">
                        <span class="status-badge status-paid">✓ PAID</span>
                    </span>
                </div>
            </div>
            
            <!-- Ride Details -->
            <div class="receipt-section">
                <div class="receipt-section-title">
                    <i class="fas fa-map-marker-alt"></i> Ride Details
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Ride Type:</span>
                    <span class="receipt-value"><?php echo ucfirst($ride['ride_type'] ?? 'Economy'); ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Pickup Location:</span>
                    <span class="receipt-value"><?php echo $ride['pickup_address']; ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Destination:</span>
                    <span class="receipt-value"><?php echo $ride['destination_address']; ?></span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Distance:</span>
                    <span class="receipt-value"><?php echo number_format($ride['distance_km'] ?? 0, 1); ?> km</span>
                </div>
                <div class="receipt-row">
                    <span class="receipt-label">Duration:</span>
                    <span class="receipt-value"><?php echo floor(($ride['distance_km'] ?? 0) * 2); ?> mins</span>
                </div>
            </div>
            
            <!-- Driver Details -->
            <div class="receipt-section">
                <div class="receipt-section-title">
                    <i class="fas fa-user-tie"></i> Driver Details
                </div>
                <div class="driver-info-card">
                    <div class="driver-info-row">
                        <div class="driver-avatar">
                            <?php echo strtoupper(substr($ride['driver_name'] ?? 'D', 0, 1)); ?>
                        </div>
                        <div style="flex: 1;">
                            <div style="font-weight: 600; font-size: 18px;"><?php echo $ride['driver_name'] ?? 'Driver'; ?></div>
                            <div style="color: #666; font-size: 14px; margin-top: 5px;">
                                <i class="fas fa-star" style="color: #FFC107;"></i> 4.8 • Driver
                            </div>
                        </div>
                    </div>
                    <div class="receipt-row" style="margin-top: 15px;">
                        <span class="receipt-label">Vehicle:</span>
                        <span class="receipt-value">
                            <?php 
                            echo ($ride['vehicle_model'] ?? 'Vehicle') . ' • ' . ($ride['vehicle_color'] ?? '');
                            ?>
                        </span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">Plate Number:</span>
                        <span class="receipt-value"><?php echo $ride['plate_number'] ?? 'LAG-123-AB'; ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Fare Breakdown -->
            <div class="receipt-section">
                <div class="receipt-section-title">
                    <i class="fas fa-calculator"></i> Fare Breakdown
                </div>
                <div class="receipt-total">
                    <div class="receipt-row">
                        <span class="receipt-label">Base Fare:</span>
                        <span class="receipt-value">₦<?php echo number_format($base_fare, 2); ?></span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">Distance Fare (<?php echo number_format($ride['distance_km'] ?? 0, 1); ?> km):</span>
                        <span class="receipt-value">₦<?php echo number_format($distance_fare, 2); ?></span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">Time Fare:</span>
                        <span class="receipt-value">₦0.00</span>
                    </div>
                    <div class="receipt-row">
                        <span class="receipt-label">Service Fee (5%):</span>
                        <span class="receipt-value">₦<?php echo number_format($service_fee, 2); ?></span>
                    </div>
                    <div class="receipt-row grand-total">
                        <span class="receipt-label">Total Amount:</span>
                        <span class="receipt-value">₦<?php echo number_format($ride['total_fare'] ?? 0, 2); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- QR Code (for verification) -->
            <div class="qr-code">
                <?php 
                $qr_data = "SPEEDLY RIDE\n" .
                           "Receipt: " . $ride_number . "\n" .
                           "Amount: ₦" . number_format($ride['total_fare'] ?? 0, 2) . "\n" .
                           "Date: " . date('Y-m-d H:i:s', strtotime($ride['created_at']));
                ?>
                <img src="https://api.qrserver.com/v1/create-qr-code/?size=120x120&data=<?php echo urlencode($qr_data); ?>" alt="QR Code">
                <p class="text-xs text-gray-500 mt-2">Scan to verify receipt authenticity</p>
            </div>
            
            <!-- Thank You Message -->
            <div class="thank-you-message">
                <i class="fas fa-heart"></i>
                <p>Thank you for choosing Speedly!</p>
                <p class="text-xs text-gray-500 mt-2">We appreciate your business and look forward to serving you again.</p>
            </div>
            
            <!-- Footer -->
            <div class="text-center text-gray-400 text-xs mt-5 pt-5 border-t border-gray-200">
                <p>For any inquiries, contact support@speedly.com or call +234 800 000 0000</p>
                <p class="mt-2">© <?php echo date('Y'); ?> Speedly. All rights reserved.</p>
                <p class="mt-1">This is a computer generated receipt. No signature required.</p>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="action-buttons no-print">
        <button class="btn btn-secondary" onclick="window.print()">
            <i class="fas fa-print"></i> Print Receipt
        </button>
        <button class="btn btn-secondary" onclick="downloadPDF()">
            <i class="fas fa-download"></i> Download PDF
        </button>
        <button class="btn btn-secondary" onclick="shareWhatsApp()">
            <i class="fab fa-whatsapp"></i> Share
        </button>
        <button class="btn btn-primary" onclick="window.location.href='ride_history.php'">
            <i class="fas fa-history"></i> Ride History
        </button>
        <button class="btn btn-primary" onclick="window.location.href='book-ride.php'">
            <i class="fas fa-car"></i> Book Another Ride
        </button>
    </div>
    
    <script>
        function downloadPDF() {
            const element = document.getElementById('receipt-content');
            const opt = {
                margin:        [0.5, 0.5, 0.5, 0.5],
                filename:     'speedly_receipt_<?php echo $ride_number; ?>.pdf',
                image:        { type: 'jpeg', quality: 0.98 },
                html2canvas:  { scale: 2, logging: true, dpi: 192, letterRendering: true },
                jsPDF:        { unit: 'in', format: 'a4', orientation: 'portrait' }
            };
            
            // Show loading
            Swal.fire({
                title: 'Generating PDF...',
                text: 'Please wait',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
            
            // Generate PDF
            html2pdf().set(opt).from(element).save().then(() => {
                Swal.close();
                Swal.fire({
                    icon: 'success',
                    title: 'PDF Generated!',
                    text: 'Your receipt has been downloaded.',
                    timer: 2000,
                    showConfirmButton: false
                });
            });
        }
        
        // Share via WhatsApp
        function shareWhatsApp() {
            const text = `🚗 *SPEEDLY RIDE RECEIPT*\n\n` +
                        `━━━━━━━━━━━━━━━━━━━━━\n\n` +
                        `*Receipt:* #<?php echo $ride_number; ?>\n` +
                        `*Date:* <?php echo date('d M Y, h:i A', strtotime($ride['created_at'])); ?>\n` +
                        `*From:* <?php echo $ride['pickup_address']; ?>\n` +
                        `*To:* <?php echo $ride['destination_address']; ?>\n` +
                        `*Amount:* ₦<?php echo number_format($ride['total_fare'] ?? 0, 2); ?>\n` +
                        `*Driver:* <?php echo $ride['driver_name'] ?? 'Driver'; ?>\n` +
                        `*Vehicle:* <?php echo ($ride['vehicle_model'] ?? 'Vehicle') . ' - ' . ($ride['plate_number'] ?? 'LAG-123-AB'); ?>\n\n` +
                        `━━━━━━━━━━━━━━━━━━━━━\n\n` +
                        `Thank you for riding with Speedly! 🚀`;
            
            window.open(`https://wa.me/?text=${encodeURIComponent(text)}`, '_blank');
        }
        
        // Email receipt
        function emailReceipt() {
            const subject = `Speedly Ride Receipt #<?php echo $ride_number; ?>`;
            const body = `Dear <?php echo $user_name; ?>,\n\n` +
                        `Thank you for riding with Speedly. Your ride receipt is attached.\n\n` +
                        `Ride Details:\n` +
                        `━━━━━━━━━━━━━━━━\n` +
                        `Receipt: #<?php echo $ride_number; ?>\n` +
                        `Date: <?php echo date('d M Y, h:i A', strtotime($ride['created_at'])); ?>\n` +
                        `From: <?php echo $ride['pickup_address']; ?>\n` +
                        `To: <?php echo $ride['destination_address']; ?>\n` +
                        `Amount: ₦<?php echo number_format($ride['total_fare'] ?? 0, 2); ?>\n` +
                        `Driver: <?php echo $ride['driver_name'] ?? 'Driver'; ?>\n\n` +
                        `━━━━━━━━━━━━━━━━\n\n` +
                        `Best regards,\n` +
                        `Speedly Team`;
            
            window.location.href = `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
        }
    </script>
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>    