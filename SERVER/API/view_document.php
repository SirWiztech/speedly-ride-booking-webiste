<?php
session_start();

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('HTTP/1.0 403 Forbidden');
    echo 'Access denied';
    exit;
}

$file = $_GET['file'] ?? '';
$kyc_id = $_GET['kyc_id'] ?? '';

if (empty($file) && empty($kyc_id)) {
    header('HTTP/1.0 400 Bad Request');
    echo 'No file specified';
    exit;
}

require_once __DIR__ . '/db-connect.php';

// If kyc_id is provided, get the document URL from database
if (!empty($kyc_id)) {
    $stmt = $conn->prepare("SELECT document_url FROM driver_kyc_documents WHERE id = ?");
    $stmt->bind_param("s", $kyc_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        header('HTTP/1.0 404 Not Found');
        echo 'Document not found';
        exit;
    }
    
    $doc = $result->fetch_assoc();
    $file = $doc['document_url'];
}

// Clean the file path
$file = str_replace(['../', './'], '', $file);
$file = basename($file); // Security: only get the filename

// Construct the full path
$base_path = $_SERVER['DOCUMENT_ROOT'] . '/SPEEDLY/SERVER/uploads/kyc/';
$full_path = $base_path . $file;

// Check if file exists
if (!file_exists($full_path)) {
    header('HTTP/1.0 404 Not Found');
    echo 'File not found at: ' . $full_path;
    exit;
}

// Get file extension
$ext = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));

// Set appropriate content type
$content_types = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'pdf' => 'application/pdf'
];

$content_type = $content_types[$ext] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($full_path));
header('Content-Disposition: inline; filename="' . $file . '"');

// Output the file
readfile($full_path);
exit;
?>