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
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Update user profile
$updateFields = [];
$params = [];
$types = "";

if (isset($input['full_name'])) {
    $updateFields[] = "full_name = ?";
    $params[] = $input['full_name'];
    $types .= "s";
    $_SESSION['fullname'] = $input['full_name'];
}

if (isset($input['email'])) {
    $updateFields[] = "email = ?";
    $params[] = $input['email'];
    $types .= "s";
    $_SESSION['email'] = $input['email'];
}

if (isset($input['phone'])) {
    $updateFields[] = "phone_number = ?";
    $params[] = $input['phone'];
    $types .= "s";
}

if (!empty($updateFields)) {
    $updateQuery = "UPDATE users SET " . implode(", ", $updateFields) . " WHERE id = ?";
    $params[] = $user_id;
    $types .= "s";

    $updateStmt = $conn->prepare($updateQuery);
    $updateStmt->bind_param($types, ...$params);
    
    if ($updateStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Profile updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile']);
    }
} else {
    echo json_encode(['success' => true, 'message' => 'No changes to update']);
}

$conn->close();
?>