<?php
header('Content-Type: application/json');
session_start();

require_once __DIR__ . '/db-connect.php';

// Enable error logging
error_log("=== Get Activity Logs Request Started ===");

// Check if user is logged in and is admin
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

try {
    // Get activity logs with IP addresses
    $query = "SELECT 
                al.*,
                u.full_name as admin_name,
                u.email as admin_email,
                INET_NTOA(UNHEX(HEX(INET_ATON(al.ip_address)))) as ip_formatted
              FROM admin_activity_logs al
              JOIN users u ON al.admin_id = u.id
              ORDER BY al.created_at DESC
              LIMIT 100";
    
    $result = $conn->query($query);
    
    $logs = [];
    while ($row = $result->fetch_assoc()) {
        // Format old and new values if they exist
        $old_values = $row['old_values'] ? json_decode($row['old_values'], true) : null;
        $new_values = $row['new_values'] ? json_decode($row['new_values'], true) : null;
        
        // Get IP address - try multiple sources
        $ip = $row['ip_address'] ?? $row['ip_formatted'] ?? null;
        
        // If still no IP, try to get from session or generate a placeholder
        if (!$ip) {
            $ip = '127.0.0.1'; // Default localhost
        }
        
        $logs[] = [
            'id' => $row['id'],
            'admin_name' => $row['admin_name'],
            'admin_email' => $row['admin_email'],
            'action' => $row['action'],
            'entity_type' => $row['entity_type'],
            'entity_id' => $row['entity_id'],
            'old_values' => $old_values,
            'new_values' => $new_values,
            'ip_address' => $ip,
            'created_at' => $row['created_at'],
            'formatted_date' => date('M d, Y h:i:s A', strtotime($row['created_at']))
        ];
    }

    // Get summary statistics
    $statsQuery = "SELECT 
                    COUNT(*) as total_logs,
                    COUNT(DISTINCT admin_id) as active_admins,
                    MAX(created_at) as last_activity,
                    COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_activities
                   FROM admin_activity_logs";
    
    $statsResult = $conn->query($statsQuery);
    $stats = $statsResult->fetch_assoc();

    echo json_encode([
        'success' => true,
        'statistics' => [
            'total_logs' => (int)$stats['total_logs'],
            'active_admins' => (int)$stats['active_admins'],
            'last_activity' => $stats['last_activity'],
            'today_activities' => (int)$stats['today_activities']
        ],
        'logs' => $logs
    ]);

} catch (Exception $e) {
    error_log("Error in get_activity_logs.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load activity logs: ' . $e->getMessage()
    ]);
}
?>