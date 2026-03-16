<?php
session_start();
require_once __DIR__ . '/SERVER/API/db-connect.php';

// Display session info
echo "<h1>KYC API Test</h1>";
echo "<h2>Session Information:</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check if admin is logged in
$isAdmin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
echo "<p>Admin logged in: " . ($isAdmin ? '✅ YES' : '❌ NO') . "</p>";

if ($isAdmin) {
    $admin_id = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 'Not set';
    echo "<p>Admin ID: " . $admin_id . "</p>";
}

// Get pending KYC documents
echo "<h2>Pending KYC Documents:</h2>";
$query = "
    SELECT dk.id, u.full_name, dk.document_type, dk.verification_status
    FROM driver_kyc_documents dk
    JOIN driver_profiles dp ON dk.driver_id = dp.id
    JOIN users u ON dp.user_id = u.id
    WHERE dk.verification_status = 'pending'
    LIMIT 5
";
$result = $conn->query($query);

if ($result && $result->num_rows > 0) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Driver</th><th>Document Type</th><th>Status</th><th>Action</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . substr($row['id'], 0, 8) . "...</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . $row['document_type'] . "</td>";
        echo "<td>" . $row['verification_status'] . "</td>";
        echo "<td><button onclick='testApprove(\"" . $row['id'] . "\")'>Test Approve</button></td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No pending KYC documents found.</p>";
}

// Get approved KYC documents
echo "<h2>Recently Approved KYC:</h2>";
$approved = $conn->query("
    SELECT dk.id, u.full_name, dk.document_type, dk.verified_at
    FROM driver_kyc_documents dk
    JOIN driver_profiles dp ON dk.driver_id = dp.id
    JOIN users u ON dp.user_id = u.id
    WHERE dk.verification_status = 'approved'
    ORDER BY dk.verified_at DESC
    LIMIT 5
");

if ($approved && $approved->num_rows > 0) {
    echo "<table border='1' cellpadding='8'>";
    echo "<tr><th>ID</th><th>Driver</th><th>Document Type</th><th>Approved At</th></tr>";
    while ($row = $approved->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . substr($row['id'], 0, 8) . "...</td>";
        echo "<td>" . htmlspecialchars($row['full_name']) . "</td>";
        echo "<td>" . $row['document_type'] . "</td>";
        echo "<td>" . $row['verified_at'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No approved KYC documents found.</p>";
}
?>

<script>
function testApprove(kycId) {
    if (!confirm('Test approve this KYC document?')) return;
    
    console.log('Testing approve for KYC ID:', kycId);
    
    fetch('SERVER/API/approve_kyc.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({
            kyc_id: kycId,
            action: 'approve'
        })
    })
    .then(response => response.json())
    .then(data => {
        console.log('Response:', data);
        alert(JSON.stringify(data, null, 2));
        if (data.success) {
            location.reload();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error: ' + error.message);
    });
}
</script>