<?php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$user_id = $_SESSION['user_id'];

$query = "UPDATE notifications SET is_read = 1 WHERE user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("s", $user_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Notifications marked as read']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update notifications']);
}

$conn->close();
?>