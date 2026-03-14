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

// Create user_settings table if it doesn't exist
$createTableQuery = "CREATE TABLE IF NOT EXISTS user_settings (
    id CHAR(36) PRIMARY KEY DEFAULT (UUID()),
    user_id CHAR(36) NOT NULL,
    dark_mode BOOLEAN DEFAULT 0,
    notifications_enabled BOOLEAN DEFAULT 1,
    email_notifications BOOLEAN DEFAULT 1,
    sms_notifications BOOLEAN DEFAULT 0,
    language VARCHAR(10) DEFAULT 'en',
    currency VARCHAR(10) DEFAULT 'NGN',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";
$conn->query($createTableQuery);

// Check if settings exist for user
$checkQuery = "SELECT id FROM user_settings WHERE user_id = ?";
$checkStmt = $conn->prepare($checkQuery);
$checkStmt->bind_param("s", $user_id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    // Update existing settings
    $updateFields = [];
    $params = [];
    $types = "";

    foreach ($input as $key => $value) {
        if (in_array($key, ['dark_mode', 'notifications_enabled', 'email_notifications', 'sms_notifications'])) {
            $updateFields[] = "$key = ?";
            $params[] = $value ? 1 : 0;
            $types .= "i";
        } elseif (in_array($key, ['language', 'currency'])) {
            $updateFields[] = "$key = ?";
            $params[] = $value;
            $types .= "s";
        }
    }

    if (!empty($updateFields)) {
        $updateQuery = "UPDATE user_settings SET " . implode(", ", $updateFields) . " WHERE user_id = ?";
        $params[] = $user_id;
        $types .= "s";

        $updateStmt = $conn->prepare($updateQuery);
        $updateStmt->bind_param($types, ...$params);
        
        if ($updateStmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Settings updated successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update settings']);
        }
    } else {
        echo json_encode(['success' => true, 'message' => 'No changes to update']);
    }
} else {
    // Insert new settings
    $insertFields = ['user_id'];
    $insertValues = ['?'];
    $params = [$user_id];
    $types = "s";

    foreach ($input as $key => $value) {
        if (in_array($key, ['dark_mode', 'notifications_enabled', 'email_notifications', 'sms_notifications'])) {
            $insertFields[] = $key;
            $insertValues[] = "?";
            $params[] = $value ? 1 : 0;
            $types .= "i";
        } elseif (in_array($key, ['language', 'currency'])) {
            $insertFields[] = $key;
            $insertValues[] = "?";
            $params[] = $value;
            $types .= "s";
        }
    }

    $insertQuery = "INSERT INTO user_settings (" . implode(", ", $insertFields) . ") VALUES (" . implode(", ", $insertValues) . ")";
    $insertStmt = $conn->prepare($insertQuery);
    $insertStmt->bind_param($types, ...$params);

    if ($insertStmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Settings saved successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to save settings']);
    }
}

$conn->close();
?>