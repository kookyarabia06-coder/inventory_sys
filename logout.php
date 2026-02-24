<?php
/**
 * Logout Handler
 */

// Load configuration first
require_once 'config.php';

// Load required files
require_once CONFIG_PATH . '/database.php';
require_once INCLUDE_PATH . '/functions.php';
require_once INCLUDE_PATH . '/auth.php';

// Log activity if user was logged in
if (isset($_SESSION['user_id'])) {
    logActivity('Logout', $_SESSION['user_id'], 'User logged out');
}

// Destroy session
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Finally destroy the session
session_destroy();

// Redirect to login
header('Location: ' . SITE_URL . '/login');
exit();
?>