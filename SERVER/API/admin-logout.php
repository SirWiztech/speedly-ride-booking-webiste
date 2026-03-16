<?php
session_start();

if (isset($_SESSION['admin_id'])) {
    // Log activity
    require_once __DIR__ . '/db-connect.php';
    $id = vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex(random_bytes(16)), 4));
    $stmt = $conn->prepare("INSERT INTO admin_activity_logs (id, admin_id, action, entity_type, created_at) VALUES (?, ?, 'logout', 'system', NOW())");
    $stmt->bind_param("ss", $id, $_SESSION['admin_id']);
    $stmt->execute();
}

session_destroy();
header('Location: /SPEEDLY/admin_login.php');
exit;
?>