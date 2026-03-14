<?php
// Helper script to delete setup_admin.php
// Run this after setting up admin

echo "<!DOCTYPE html>
<html>
<head>
    <title>Delete Setup File</title>
    <style>
        body { font-family: 'Poppins', sans-serif; background: linear-gradient(135deg, #ff5e00 0%, #ff8c3a 100%); min-height: 100vh; display: flex; justify-content: center; align-items: center; padding: 20px; }
        .container { background: white; border-radius: 20px; padding: 40px; max-width: 600px; box-shadow: 0 20px 40px rgba(0,0,0,0.2); text-align: center; }
        h1 { color: #333; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; padding: 15px; border-radius: 10px; margin: 20px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 15px; border-radius: 10px; margin: 20px 0; }
        .btn { background: #ff5e00; color: white; padding: 12px 24px; text-decoration: none; border-radius: 8px; display: inline-block; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>";

if (file_exists('setup_admin.php')) {
    if (unlink('setup_admin.php')) {
        echo "<h1>✅ Security Action Completed</h1>";
        echo "<div class='success'>";
        echo "setup_admin.php has been successfully deleted!<br>";
        echo "Your system is now secure.";
        echo "</div>";
    } else {
        echo "<h1>❌ Error</h1>";
        echo "<div class='error'>";
        echo "Could not delete setup_admin.php. Please delete it manually via FTP or file manager.";
        echo "</div>";
    }
} else {
    echo "<h1>✅ Already Secure</h1>";
    echo "<div class='success'>";
    echo "setup_admin.php does not exist or was already deleted.<br>";
    echo "Your system is secure.";
    echo "</div>";
}

echo "<br><a href='admin_login.php' class='btn'>Go to Admin Login →</a>";
echo "</div></body></html>";
?>    