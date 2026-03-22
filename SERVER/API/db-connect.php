<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'speedly_new';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// Set charset to handle special characters
$conn->set_charset("utf8mb4");

// Set timezone
date_default_timezone_set('Africa/Lagos');

// Set session cookie parameters to work across the entire domain
if (session_status() === PHP_SESSION_NONE) {
    // Set cookie path to root to ensure session persists across all subdirectories
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',  // Empty means current domain
        'secure' => false,  // Set to true if using HTTPS (you are, so set to true)
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
}
?>