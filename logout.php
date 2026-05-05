<?php
/**
 * Logout Page - Only clear session, KEEP verification cookie
 */
session_start();
require_once __DIR__ . '/config.php';

// Clear all session variables
$_SESSION = array();

// Destroy session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 3600, '/');
}

// Destroy session
session_destroy();

// DO NOT clear verification_status cookie - keep it for 1 hour
// DO NOT clear remember_username cookie - keep it for auto-fill

// Redirect to login page
header('Location: ' . SITE_URL . '/login.php');
exit();
?>