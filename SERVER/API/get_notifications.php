<?php
session_start();
require_once 'db-connect.php';

header('Content-Type: application/json');

// Enable error logging
error_log("=== Get Notifications Request Started ===");

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    error_log("User not logged in");
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? 'client';

error_log("Fetching notifications for user: " . $user_id . ", role: " . $user_role);

try {
    // Get unread notifications
    $query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        // Format the data
        $row['created_at_formatted'] = date('M d, h:i A', strtotime($row['created_at']));
        $row['time_ago'] = timeAgo($row['created_at']);
        $notifications[] = $row;
    }
    
    $unreadCount = count(array_filter($notifications, function($n) { return $n['is_read'] == 0; }));
    
    error_log("Found " . count($notifications) . " notifications, " . $unreadCount . " unread");
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications,
        'count' => count($notifications),
        'unread_count' => $unreadCount,
        'user_id' => $user_id
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching notifications: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load notifications',
        'error' => $e->getMessage()
    ]);
}

$conn->close();
error_log("=== Get Notifications Request Completed ===");

// Helper function to format time ago
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M d, Y', $time);
    }
}
?>