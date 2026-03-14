<?php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Submit KYC Request Started ===");

// Check if user is logged in and is driver
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

if ($_SESSION['role'] !== 'driver') {
    error_log("User is not a driver. Role: " . $_SESSION['role']);
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

$driver_user_id = $_SESSION['user_id'];

// Get driver profile ID
$stmt = $conn->prepare("SELECT id FROM driver_profiles WHERE user_id = ?");
$stmt->bind_param("s", $driver_user_id);
$stmt->execute();
$result = $stmt->get_result();
$driver = $result->fetch_assoc();

if (!$driver) {
    error_log("Driver profile not found for user_id: " . $driver_user_id);
    echo json_encode(['success' => false, 'message' => 'Driver profile not found']);
    exit;
}

$driver_id = $driver['id'];
error_log("Driver ID: " . $driver_id);

// Create upload directory if it doesn't exist
$upload_dir = '../uploads/kyc/';
$absolute_path = $_SERVER['DOCUMENT_ROOT'] . '/SPEEDLY/SERVER/uploads/kyc/';

if (!file_exists($absolute_path)) {
    mkdir($absolute_path, 0777, true);
    error_log("Created upload directory: " . $absolute_path);
}

$uploaded_files = [];
$errors = [];

// Handle file uploads
$document_types = [
    'license_front' => 'drivers_license_front',
    'license_back' => 'drivers_license_back',
    'selfie' => 'selfie_with_id',
    'insurance' => 'insurance',
    'vehicle_registration' => 'vehicle_registration'
];

foreach ($document_types as $field => $doc_type) {
    if (isset($_FILES[$field]) && $_FILES[$field]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$field];
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Validate file type
        $allowed_types = ['jpg', 'jpeg', 'png', 'pdf'];
        if (!in_array($extension, $allowed_types)) {
            $errors[] = "$field: Invalid file type. Allowed: " . implode(', ', $allowed_types);
            continue;
        }
        
        // Validate file size (max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            $errors[] = "$field: File too large. Max 10MB.";
            continue;
        }
        
        $filename = $doc_type . '_' . time() . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $filepath = $absolute_path . $filename;
        $db_path = 'SERVER/uploads/kyc/' . $filename;
        
        error_log("Uploading $field to: " . $filepath);
        
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Check if document already exists
            $check = $conn->prepare("SELECT id FROM driver_kyc_documents WHERE driver_id = ? AND document_type = ?");
            $check->bind_param("ss", $driver_id, $doc_type);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            
            if ($exists) {
                // Update existing
                $stmt = $conn->prepare("UPDATE driver_kyc_documents SET document_url = ?, verification_status = 'pending', updated_at = NOW() WHERE driver_id = ? AND document_type = ?");
                $stmt->bind_param("sss", $db_path, $driver_id, $doc_type);
            } else {
                // Insert new
                $doc_id = bin2hex(random_bytes(16));
                $stmt = $conn->prepare("INSERT INTO driver_kyc_documents (id, driver_id, document_type, document_url, verification_status, uploaded_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
                $stmt->bind_param("ssss", $doc_id, $driver_id, $doc_type, $db_path);
            }
            
            if ($stmt->execute()) {
                $uploaded_files[] = $field;
                error_log("Successfully uploaded $field");
            } else {
                $errors[] = "$field: Failed to save to database";
                error_log("Database error for $field: " . $stmt->error);
            }
        } else {
            $errors[] = "$field: Failed to upload file";
            error_log("Failed to move uploaded file for $field");
        }
    }
}

// Update driver profile with license info if provided
if (isset($_POST['license_number']) && !empty($_POST['license_number'])) {
    $license_number = $_POST['license_number'];
    $license_expiry = $_POST['license_expiry'] ?? null;
    
    $stmt = $conn->prepare("UPDATE driver_profiles SET license_number = ?, license_expiry = ? WHERE id = ?");
    $stmt->bind_param("sss", $license_number, $license_expiry, $driver_id);
    $stmt->execute();
    error_log("Updated license info for driver: " . $driver_id);
}

// Check if we have the required documents
$check_required = $conn->prepare("
    SELECT 
        COUNT(CASE WHEN document_type = 'drivers_license_front' THEN 1 END) as has_license,
        COUNT(CASE WHEN document_type = 'selfie_with_id' THEN 1 END) as has_selfie
    FROM driver_kyc_documents 
    WHERE driver_id = ?
");
$check_required->bind_param("s", $driver_id);
$check_required->execute();
$required = $check_required->get_result()->fetch_assoc();

error_log("Required docs - License: " . ($required['has_license'] ?? 0) . ", Selfie: " . ($required['has_selfie'] ?? 0));

// If both required docs are present, add to approval queue if not already there
if (($required['has_license'] ?? 0) > 0 && ($required['has_selfie'] ?? 0) > 0) {
    // Check if already in queue
    $check_queue = $conn->prepare("SELECT id FROM driver_approval_queue WHERE driver_id = ? AND status = 'pending'");
    $check_queue->bind_param("s", $driver_id);
    $check_queue->execute();
    $in_queue = $check_queue->get_result()->fetch_assoc();
    
    if (!$in_queue) {
        $queue_id = bin2hex(random_bytes(16));
        $stmt = $conn->prepare("INSERT INTO driver_approval_queue (id, driver_id, status, created_at) VALUES (?, ?, 'pending', NOW())");
        $stmt->bind_param("ss", $queue_id, $driver_id);
        $stmt->execute();
        error_log("Added driver to approval queue: " . $driver_id);
    }
}

if (empty($errors)) {
    $message = 'KYC submitted successfully';
    if (count($uploaded_files) > 0) {
        $message .= '. Uploaded: ' . implode(', ', $uploaded_files);
    }
    echo json_encode([
        'success' => true,
        'message' => $message,
        'uploaded' => $uploaded_files
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => 'Some files failed to upload: ' . implode(', ', $errors)
    ]);
}

error_log("=== Submit KYC Request Completed ===");
$conn->close();
?>  