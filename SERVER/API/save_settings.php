<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

require_once __DIR__ . '/db-connect.php';

$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid input']);
    exit;
}

try {
    foreach ($input as $key => $value) {
        // Convert boolean to string
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        
        // Check if setting exists
        $check = $conn->prepare("SELECT id FROM system_settings WHERE setting_key = ?");
        $check->bind_param("s", $key);
        $check->execute();
        $result = $check->get_result();
        
        if ($result->num_rows > 0) {
            // Update
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ?, updated_at = NOW() WHERE setting_key = ?");
            $stmt->bind_param("sss", $value, $_SESSION['admin_id'], $key);
        } else {
            // Insert
            $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
            $stmt = $conn->prepare("INSERT INTO system_settings (id, setting_key, setting_value, setting_type, updated_by) VALUES (?, ?, ?, 'string', ?)");
            $stmt->bind_param("ssss", $id, $key, $value, $_SESSION['admin_id']);
        }
        
        $stmt->execute();
    }
    
    // Log activity
    $logId = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    $logStmt = $conn->prepare("INSERT INTO admin_activity_logs (id, admin_id, action, entity_type, created_at) VALUES (?, ?, 'update_settings', 'system', NOW())");
    $logStmt->bind_param("ss", $logId, $_SESSION['admin_id']);
    $logStmt->execute();
    
    echo json_encode(['status' => 'success', 'message' => 'Settings saved successfully']);
    
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
?>