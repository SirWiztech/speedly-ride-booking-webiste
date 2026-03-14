<?php
$host = 'localhost';
$username = 'root';
$password = '';
$database = 'speedly';

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]));
}

// Set charset to handle special characters
$conn->set_charset("utf8mb4");

// Set timezone
date_default_timezone_set('Africa/Lagos');
?>