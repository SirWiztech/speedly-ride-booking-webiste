<?php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Approve KYC Request Started ===");

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

if ($_SESSION['role'] !== 'admin') {
    error_log("User is not admin. Role: " . $_SESSION['role']);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Admin only.']);
    exit;
}

$admin_id = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['driver_id']) || !isset($data['action'])) {
    error_log("Invalid input: " . file_get_contents('php://input'));
    echo json_encode(['success' => false, 'message' => 'Invalid input. Driver ID and action required.']);
    exit;
}

$driver_id = $data['driver_id'];
$action = $data['action'];
$reason = isset($data['reason']) ? $data['reason'] : '';

if (!in_array($action, ['approve', 'reject'])) {
    error_log("Invalid action: " . $action);
    echo json_encode(['success' => false, 'message' => 'Invalid action. Must be approve or reject.']);
    exit;
}

error_log("Processing KYC " . $action . " for driver_id: " . $driver_id . " by admin: " . $admin_id);

// Begin transaction
$conn->begin_transaction();

try {
    if ($action === 'approve') {
        // Update driver profile verification status
        $stmt = $conn->prepare("UPDATE driver_profiles SET verification_status = 'approved', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("s", $driver_id);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Driver profile not found");
        }
        
        // Update all driver documents status
        $stmt = $conn->prepare("UPDATE driver_kyc_documents SET verification_status = 'approved', verified_by = ?, verified_at = NOW() WHERE driver_id = ?");
        $stmt->bind_param("ss", $admin_id, $driver_id);
        $stmt->execute();
        
        // Update approval queue
        $stmt = $conn->prepare("UPDATE driver_approval_queue SET status = 'approved', reviewed_by = ?, reviewed_at = NOW() WHERE driver_id = ? AND status = 'pending'");
        $stmt->bind_param("ss", $admin_id, $driver_id);
        $stmt->execute();
        
        // Get user_id from driver_profile
        $stmt = $conn->prepare("SELECT user_id FROM driver_profiles WHERE id = ?");
        $stmt->bind_param("s", $driver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $driver = $result->fetch_assoc();
        $user_id = $driver['user_id'];
        
        // Create notification for driver
        $notif_id = bin2hex(random_bytes(16));
        $title = 'KYC Approved';
        $message = 'Your KYC verification has been approved. You can now go online and start accepting rides.';
        $stmt = $conn->prepare("INSERT INTO notifications (id, user_id, type, title, message, created_at) VALUES (?, ?, 'system', ?, ?, NOW())");
        $stmt->bind_param("ssss", $notif_id, $user_id, $title, $message);
        $stmt->execute();
        
        error_log("KYC approved successfully for driver: " . $driver_id);
        
    } elseif ($action === 'reject') {
        // Validate rejection reason
        if (empty($reason)) {
            throw new Exception("Rejection reason is required");
        }
        
        // Update driver profile verification status
        $stmt = $conn->prepare("UPDATE driver_profiles SET verification_status = 'rejected', updated_at = NOW() WHERE id = ?");
        $stmt->bind_param("s", $driver_id);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception("Driver profile not found");
        }
        
        // Update all driver documents status
        $stmt = $conn->prepare("UPDATE driver_kyc_documents SET verification_status = 'rejected', verified_by = ?, verified_at = NOW(), rejection_reason = ? WHERE driver_id = ?");
        $stmt->bind_param("sss", $admin_id, $reason, $driver_id);
        $stmt->execute();
        
        // Update approval queue
        $stmt = $conn->prepare("UPDATE driver_approval_queue SET status = 'rejected', reviewed_by = ?, reviewed_at = NOW() WHERE driver_id = ? AND status = 'pending'");
        $stmt->bind_param("ss", $admin_id, $driver_id);
        $stmt->execute();
        
        // Get user_id from driver_profile
        $stmt = $conn->prepare("SELECT user_id FROM driver_profiles WHERE id = ?");
        $stmt->bind_param("s", $driver_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $driver = $result->fetch_assoc();
        $user_id = $driver['user_id'];
        
        // Create notification for driver
        $notif_id = bin2hex(random_bytes(16));
        $title = 'KYC Rejected';
        $message = 'Your KYC verification was rejected. Reason: ' . $reason;
        $stmt = $conn->prepare("INSERT INTO notifications (id, user_id, type, title, message, created_at) VALUES (?, ?, 'system', ?, ?, NOW())");
        $stmt->bind_param("ssss", $notif_id, $user_id, $title, $message);
        $stmt->execute();
        
        error_log("KYC rejected for driver: " . $driver_id . ". Reason: " . $reason);
    }
    
    // Log admin activity
    $log_id = bin2hex(random_bytes(16));
    $action_desc = $action === 'approve' ? 'approved' : 'rejected';
    $log_stmt = $conn->prepare("INSERT INTO admin_activity_logs (id, admin_id, action, entity_type, entity_id, old_values, new_values, created_at) VALUES (?, ?, ?, 'driver', ?, '{}', ?, NOW())");
    $new_values = json_encode(['verification_status' => $action === 'approve' ? 'approved' : 'rejected', 'reason' => $reason]);
    $log_stmt->bind_param("sssss", $log_id, $admin_id, $action_desc, $driver_id, $new_values);
    $log_stmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'KYC ' . ($action === 'approve' ? 'approved' : 'rejected') . ' successfully'
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error processing KYC: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error processing request: ' . $e->getMessage()
    ]);
}

error_log("=== Approve KYC Request Completed ===");
$conn->close();
?> 