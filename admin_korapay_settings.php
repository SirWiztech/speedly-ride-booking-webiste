<?php
// admin_korapay_settings.php
session_start();
require_once 'SERVER/API/db-connect.php';

// Check admin access
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: admin_login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $secret_key = $_POST['secret_key'] ?? '';
    $public_key = $_POST['public_key'] ?? '';
    $environment = $_POST['environment'] ?? 'sandbox';
    $webhook_secret = $_POST['webhook_secret'] ?? '';
    
    // Update settings
    $updateQuery = "INSERT INTO system_settings (id, setting_key, setting_value, setting_type, description, updated_at) 
                    VALUES (UUID(), 'korapay_secret_key', ?, 'string', 'KoraPay Secret Key', NOW())
                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ss", $secret_key, $secret_key);
    $stmt->execute();
    
    $updateQuery = "INSERT INTO system_settings (id, setting_key, setting_value, setting_type, description, updated_at) 
                    VALUES (UUID(), 'korapay_public_key', ?, 'string', 'KoraPay Public Key', NOW())
                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ss", $public_key, $public_key);
    $stmt->execute();
    
    $updateQuery = "INSERT INTO system_settings (id, setting_key, setting_value, setting_type, description, updated_at) 
                    VALUES (UUID(), 'korapay_environment', ?, 'string', 'KoraPay Environment', NOW())
                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ss", $environment, $environment);
    $stmt->execute();
    
    $updateQuery = "INSERT INTO system_settings (id, setting_key, setting_value, setting_type, description, updated_at) 
                    VALUES (UUID(), 'korapay_webhook_secret', ?, 'string', 'KoraPay Webhook Secret', NOW())
                    ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()";
    $stmt = $conn->prepare($updateQuery);
    $stmt->bind_param("ss", $webhook_secret, $webhook_secret);
    $stmt->execute();
    
    $success = true;
}

// Get current settings
$settings = [];
$query = "SELECT setting_key, setting_value FROM system_settings 
          WHERE setting_key IN ('korapay_secret_key', 'korapay_public_key', 'korapay_environment', 'korapay_webhook_secret')";
$result = $conn->query($query);
while ($row = $result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KoraPay Settings - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <div class="bg-white rounded-lg shadow-md p-6">
                <h1 class="text-2xl font-bold mb-6">KoraPay Payment Settings</h1>
                
                <?php if (isset($success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    Settings saved successfully!
                </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Environment</label>
                        <select name="environment" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                            <option value="sandbox" <?php echo ($settings['korapay_environment'] ?? '') == 'sandbox' ? 'selected' : ''; ?>>Sandbox (Test)</option>
                            <option value="live" <?php echo ($settings['korapay_environment'] ?? '') == 'live' ? 'selected' : ''; ?>>Live (Production)</option>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Use Sandbox for testing, Live for real transactions</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Secret Key</label>
                        <input type="password" name="secret_key" value="<?php echo htmlspecialchars($settings['korapay_secret_key'] ?? ''); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <p class="text-sm text-gray-500 mt-1">Your KoraPay Secret Key (starts with sk_)</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Public Key</label>
                        <input type="text" name="public_key" value="<?php echo htmlspecialchars($settings['korapay_public_key'] ?? ''); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <p class="text-sm text-gray-500 mt-1">Your KoraPay Public Key (starts with pk_)</p>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Webhook Secret (Optional)</label>
                        <input type="password" name="webhook_secret" value="<?php echo htmlspecialchars($settings['korapay_webhook_secret'] ?? ''); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-orange-500">
                        <p class="text-sm text-gray-500 mt-1">Used to verify webhook signatures for security</p>
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2">Webhook URL</label>
                        <input type="text" value="https://yourdomain.com/SERVER/API/korapay_webhook.php" readonly
                               class="w-full px-3 py-2 border border-gray-300 rounded-md bg-gray-100">
                        <p class="text-sm text-gray-500 mt-1">Configure this URL in your KoraPay dashboard for webhook notifications</p>
                    </div>
                    
                    <div class="flex justify-end">
                        <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white font-bold py-2 px-6 rounded-md transition duration-300">
                            Save Settings
                        </button>
                    </div>
                </form>
                
                <div class="mt-8 pt-6 border-t">
                    <h3 class="font-bold mb-3">Test Mode Information</h3>
                    <div class="bg-yellow-50 border border-yellow-200 rounded-md p-4">
                        <p class="text-sm text-yellow-800 mb-2">
                            <i class="fas fa-info-circle mr-2"></i>
                            <strong>Test Card Details (Sandbox Mode):</strong>
                        </p>
                        <ul class="text-sm text-gray-700 space-y-1 ml-6 list-disc">
                            <li>Card Number: 4111111111111111</li>
                            <li>Expiry: Any future date</li>
                            <li>CVV: Any 3 digits</li>
                            <li>OTP: 123456 (if required)</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>