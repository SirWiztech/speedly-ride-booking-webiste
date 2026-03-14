<?php
// SERVER/API/get_pending_kyc.php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Get Pending KYC Request Started ===");

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    error_log("User is not admin. Role: " . ($_SESSION['role'] ?? 'not set'));
    echo json_encode(['success' => false, 'message' => 'Admin access required']);
    exit;
}

$admin_id = $_SESSION['user_id'];
error_log("Admin ID: " . $admin_id);

try {
    // Get pending KYC submissions with driver details
    $query = "SELECT 
                daq.id as queue_id,
                daq.status as approval_status,
                daq.created_at as submitted_at,
                daq.review_notes,
                u.id as user_id,
                u.full_name,
                u.email,
                u.phone_number,
                u.profile_picture_url,
                u.created_at as user_created_at,
                dp.id as driver_id,
                dp.license_number,
                dp.license_expiry,
                dp.verification_status,
                dp.completed_rides,
                dp.average_rating,
                dp.created_at as driver_created_at
              FROM driver_approval_queue daq
              JOIN driver_profiles dp ON daq.driver_id = dp.id
              JOIN users u ON dp.user_id = u.id
              WHERE daq.status = 'pending'
              ORDER BY daq.created_at ASC";

    $result = $conn->query($query);

    if (!$result) {
        throw new Exception("Database error: " . $conn->error);
    }

    $pending_kyc = [];

    while ($row = $result->fetch_assoc()) {
        // Get document URLs for this driver
        $docs_query = "SELECT 
                        id,
                        document_type, 
                        document_url, 
                        verification_status as doc_status,
                        rejection_reason,
                        uploaded_at,
                        verified_at
                      FROM driver_kyc_documents 
                      WHERE driver_id = ?
                      ORDER BY 
                        CASE document_type
                            WHEN 'drivers_license_front' THEN 1
                            WHEN 'drivers_license_back' THEN 2
                            WHEN 'selfie_with_id' THEN 3
                            WHEN 'vehicle_registration' THEN 4
                            WHEN 'insurance' THEN 5
                            WHEN 'road_worthiness' THEN 6
                            ELSE 7
                        END";
        
        $stmt = $conn->prepare($docs_query);
        $stmt->bind_param("s", $row['driver_id']);
        $stmt->execute();
        $docs_result = $stmt->get_result();
        
        $documents = [];
        $document_types_found = [];
        
        while ($doc = $docs_result->fetch_assoc()) {
            // Clean up the document URL path
            $doc_url = $doc['document_url'];
            
            // Remove any ../ or ./ from the path
            $doc_url = str_replace(['../', './'], '', $doc_url);
            
            // Ensure it has the correct base path
            if (strpos($doc_url, 'SERVER/uploads/') === 0) {
                // Already correct format
            } elseif (strpos($doc_url, 'uploads/') === 0) {
                $doc_url = 'SERVER/' . $doc_url;
            } else {
                $doc_url = 'SERVER/uploads/kyc/' . basename($doc_url);
            }
            
            // Get the base URL for absolute paths
            $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $host = $_SERVER['HTTP_HOST'];
            $base_url = $protocol . $host . '/SPEEDLY/';
            
            $documents[] = [
                'id' => $doc['id'],
                'type' => $doc['document_type'],
                'url' => $base_url . $doc_url,
                'status' => $doc['doc_status'],
                'rejection_reason' => $doc['rejection_reason'],
                'uploaded_at' => $doc['uploaded_at'],
                'verified_at' => $doc['verified_at']
            ];
            
            $document_types_found[] = $doc['document_type'];
        }
        
        // Define required document types
        $required_docs = [
            'drivers_license_front',
            'drivers_license_back',
            'selfie_with_id',
            'vehicle_registration',
            'insurance',
            'road_worthiness'
        ];
        
        // Check which documents are missing
        $missing_docs = array_diff($required_docs, $document_types_found);
        
        $row['documents'] = $documents;
        $row['missing_documents'] = array_values($missing_docs);
        $row['document_count'] = count($documents);
        
        // Format dates for display
        $row['submitted_at_formatted'] = date('M d, Y h:i A', strtotime($row['submitted_at']));
        $row['user_created_at_formatted'] = date('M d, Y', strtotime($row['user_created_at']));
        $row['license_expiry_formatted'] = date('M d, Y', strtotime($row['license_expiry']));
        
        // Check if license is expired
        $row['license_expired'] = strtotime($row['license_expiry']) < time();
        
        $pending_kyc[] = $row;
    }

    // Get statistics
    $statsQuery = "SELECT 
                    COUNT(*) as total_pending,
                    COUNT(DISTINCT driver_id) as unique_drivers,
                    MIN(created_at) as oldest_submission,
                    MAX(created_at) as newest_submission
                   FROM driver_approval_queue 
                   WHERE status = 'pending'";
    
    $statsResult = $conn->query($statsQuery);
    $stats = $statsResult->fetch_assoc();

    error_log("Found " . count($pending_kyc) . " pending KYC submissions");

    echo json_encode([
        'success' => true,
        'data' => $pending_kyc,
        'statistics' => [
            'total_pending' => (int)$stats['total_pending'],
            'unique_drivers' => (int)$stats['unique_drivers'],
            'oldest_submission' => $stats['oldest_submission'],
            'newest_submission' => $stats['newest_submission']
        ],
        'count' => count($pending_kyc)
    ]);

} catch (Exception $e) {
    error_log("Error in get_pending_kyc.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch pending KYC: ' . $e->getMessage()
    ]);
}

$conn->close();
error_log("=== Get Pending KYC Request Completed ===");
?>  