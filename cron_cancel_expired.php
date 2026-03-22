<?php
// cron_cancel_expired.php - Run this via Windows Task Scheduler every 5 minutes
require_once 'SERVER/API/db-connect.php';

// Cancel all expired pending transactions
$updateQuery = "UPDATE payment_gateway_transactions 
                SET status = 'expired', 
                    gateway_response = JSON_OBJECT('message', 'Transaction expired due to timeout'),
                    updated_at = NOW()
                WHERE status = 'pending' 
                  AND expires_at < NOW()";

if ($conn->query($updateQuery)) {
    $affected = $conn->affected_rows;
    error_log("Cron: Cancelled $affected expired transactions");
    echo "Cancelled $affected expired transactions\n";
} else {
    error_log("Cron error: " . $conn->error);
    echo "Error: " . $conn->error . "\n";
}
?>