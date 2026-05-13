<?php
/**
 * Logout Page
 * Handles user logout with audit trail logging
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout event before destroying session
if (isset($_SESSION['user_id']) && isset($conn)) {
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? 'Unknown';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    // Direct insert for logout audit
    $sql = "INSERT INTO audit_trail (user_id, action, action_category, description, ip_address, user_agent, created_at) 
            VALUES (?, 'LOGOUT', 'AUTHENTICATION', ?, ?, ?, NOW())";
    
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $description = "User logged out successfully: $username";
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
        $stmt->bind_param("isss", $user_id, $description, $ip_address, $user_agent);
        $stmt->execute();
        $stmt->close();
        
        // Debug log
        error_log("LOGOUT logged for user: $username (ID: $user_id) from IP: $ip_address");
    } else {
        error_log("Failed to prepare logout audit statement: " . $conn->error);
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Clear remember me cookie if exists
if (isset($_COOKIE['remember_username'])) {
    setcookie('remember_username', '', time()-3600, '/');
}

// Redirect to login page
header('Location: ' . SITE_URL . '/login.php');
exit();
?>