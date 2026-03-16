<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/db-connect.php';

$userId = $_GET['user_id'] ?? '';

if (empty($userId)) {
    echo json_encode(['status' => 'error', 'message' => 'User ID required']);
    exit;
}

$stmt = $conn->prepare("SELECT id, full_name, email, phone_number, role, is_active, is_verified, created_at FROM users WHERE id = ?");
$stmt->bind_param("s", $userId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo json_encode(['status' => 'error', 'message' => 'User not found']);
    exit;
}

$user = $result->fetch_assoc();
echo json_encode(['status' => 'success', 'user' => $user]);
?>