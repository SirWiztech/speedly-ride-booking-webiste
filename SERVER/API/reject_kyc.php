<?php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Please login as admin first']);
    exit;
}

$admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? null;
if (!$admin_id) {
    echo json_encode(['success' => false, 'message' => 'Admin ID not found']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['kyc_id']) || !isset($input['reason'])) {
    echo json_encode(['success' => false, 'message' => 'KYC ID and reason are required']);
    exit;
}

$kyc_id = $input['kyc_id'];
$reason = $input['reason'];

// Reuse the same logic from approve_kyc.php with action='reject'
// You can include the same code or call approve_kyc.php with action=reject
?>