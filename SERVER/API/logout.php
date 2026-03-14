<?php
session_start();

// Check if this is an AJAX request
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
          strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy the session
session_destroy();

// Clear remember me cookie if exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

// Determine redirect based on referrer or role
$redirect = 'form.php';
if (isset($_SERVER['HTTP_REFERER'])) {
    if (strpos($_SERVER['HTTP_REFERER'], 'admin') !== false) {
        $redirect = 'admin_login.php';
    }
}

if ($isAjax) {
    // Return JSON for AJAX requests
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'Logged out successfully',
        'redirect' => $redirect
    ]);
} else {
    // Regular redirect for direct access
    header("Location: $redirect");
}
exit;
?>   