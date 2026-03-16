<?php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Approve KYC Request Started ===");
error_log("Session data: " . json_encode($_SESSION));

// Check if admin is logged in - FIXED: Check for admin_logged_in instead of logged_in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    error_log("Admin not logged in. Session: " . json_encode($_SESSION));
    echo json_encode(['success' => false, 'message' => 'Please login as admin first']);
    exit;
}

// Get admin ID from session
$admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
if (!$admin_id) {
    error_log("Admin ID not found in session");
    echo json_encode(['success' => false, 'message' => 'Admin ID not found']);
    exit;
}

error_log("Admin ID: " . $admin_id);

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    $input = $_POST;
}

if (!$input || !isset($input['kyc_id'])) {
    error_log("KYC ID not provided. Input: " . json_encode($input));
    echo json_encode(['success' => false, 'message' => 'KYC document ID is required']);
    exit;
}

$kyc_id = $input['kyc_id'];
$action = $input['action'] ?? 'approve'; // Default to approve
$reason = $input['reason'] ?? '';

error_log("Processing KYC ID: " . $kyc_id . ", Action: " . $action);

// Begin transaction
$conn->begin_transaction();

try {
    // Get KYC document details
    $stmt = $conn->prepare("
        SELECT dk.*, dp.id as driver_profile_id, dp.user_id as driver_user_id
        FROM driver_kyc_documents dk
        JOIN driver_profiles dp ON dk.driver_id = dp.id
        WHERE dk.id = ?
    ");
    $stmt->bind_param("s", $kyc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("KYC document not found");
    }
    
    $kyc = $result->fetch_assoc();
    $driver_profile_id = $kyc['driver_profile_id'];
    $driver_user_id = $kyc['driver_user_id'];
    
    error_log("Found KYC document for driver profile: " . $driver_profile_id . ", user: " . $driver_user_id);
    
    if ($action === 'approve') {
        // Update the specific KYC document
        $updateStmt = $conn->prepare("
            UPDATE driver_kyc_documents 
            SET verification_status = 'approved', 
                verified_by = ?, 
                verified_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->bind_param("ss", $admin_id, $kyc_id);
        $updateStmt->execute();
        
        // Check if all required documents are approved
        $checkStmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_docs,
                SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) as approved_docs
            FROM driver_kyc_documents 
            WHERE driver_id = ?
        ");
        $checkStmt->bind_param("s", $driver_profile_id);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkData = $checkResult->fetch_assoc();
        
        error_log("Driver has " . $checkData['approved_docs'] . " approved out of " . $checkData['total_docs'] . " documents");
        
        // If all documents are approved, update driver profile and approval queue
        if ($checkData['total_docs'] > 0 && $checkData['approved_docs'] == $checkData['total_docs']) {
            // Update driver profile verification status
            $profileStmt = $conn->prepare("
                UPDATE driver_profiles 
                SET verification_status = 'approved', 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $profileStmt->bind_param("s", $driver_profile_id);
            $profileStmt->execute();
            
            // Update approval queue
            $queueStmt = $conn->prepare("
                UPDATE driver_approval_queue 
                SET status = 'approved', 
                    reviewed_by = ?, 
                    reviewed_at = NOW() 
                WHERE driver_id = ? AND status = 'pending'
            ");
            $queueStmt->bind_param("ss", $admin_id, $driver_profile_id);
            $queueStmt->execute();
            
            error_log("Driver fully approved. Profile updated.");
            
            // Create notification for driver
            $notif_id = bin2hex(random_bytes(16));
            $notif_title = 'KYC Approved';
            $notif_msg = 'Your KYC verification has been approved. You can now go online and start accepting rides.';
            $notifStmt = $conn->prepare("
                INSERT INTO notifications (id, user_id, type, title, message, created_at) 
                VALUES (?, ?, 'system', ?, ?, NOW())
            ");
            $notifStmt->bind_param("ssss", $notif_id, $driver_user_id, $notif_title, $notif_msg);
            $notifStmt->execute();
        }
        
        $message = 'KYC document approved successfully';
        
    } else if ($action === 'reject') {
        if (empty($reason)) {
            throw new Exception("Rejection reason is required");
        }
        
        // Update the KYC document
        $updateStmt = $conn->prepare("
            UPDATE driver_kyc_documents 
            SET verification_status = 'rejected', 
                verified_by = ?, 
                verified_at = NOW(),
                rejection_reason = ?
            WHERE id = ?
        ");
        $updateStmt->bind_param("sss", $admin_id, $reason, $kyc_id);
        $updateStmt->execute();
        
        // Update driver profile status to rejected
        $profileStmt = $conn->prepare("
            UPDATE driver_profiles 
            SET verification_status = 'rejected', 
                updated_at = NOW() 
            WHERE id = ?
        ");
        $profileStmt->bind_param("s", $driver_profile_id);
        $profileStmt->execute();
        
        // Update approval queue
        $queueStmt = $conn->prepare("
            UPDATE driver_approval_queue 
            SET status = 'rejected', 
                reviewed_by = ?, 
                reviewed_at = NOW() 
            WHERE driver_id = ? AND status = 'pending'
        ");
        $queueStmt->bind_param("ss", $admin_id, $driver_profile_id);
        $queueStmt->execute();
        
        // Create notification for driver
        $notif_id = bin2hex(random_bytes(16));
        $notif_title = 'KYC Rejected';
        $notif_msg = 'Your KYC verification was rejected. Reason: ' . $reason;
        $notifStmt = $conn->prepare("
            INSERT INTO notifications (id, user_id, type, title, message, created_at) 
            VALUES (?, ?, 'system', ?, ?, NOW())
        ");
        $notifStmt->bind_param("ssss", $notif_id, $driver_user_id, $notif_title, $notif_msg);
        $notifStmt->execute();
        
        $message = 'KYC document rejected';
    }
    
    // Log admin activity
    $log_id = bin2hex(random_bytes(16));
    $log_action = $action === 'approve' ? 'approve_kyc' : 'reject_kyc';
    $logStmt = $conn->prepare("
        INSERT INTO admin_activity_logs (id, admin_id, action, entity_type, entity_id, created_at) 
        VALUES (?, ?, ?, 'kyc_document', ?, NOW())
    ");
    $logStmt->bind_param("ssss", $log_id, $admin_id, $log_action, $kyc_id);
    $logStmt->execute();
    
    // Commit transaction
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    error_log("Error in approve_kyc.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
error_log("=== Approve KYC Request Completed ===");
?>