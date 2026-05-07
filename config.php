<?php
/**
 * Main Configuration File
 * Define base paths and constants
 */

// Define base path constants - use __DIR__ for root
define('BASE_PATH', __DIR__);
define('INCLUDE_PATH', BASE_PATH . '/includes');
define('CONFIG_PATH', BASE_PATH . '/config');
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('ASSET_PATH', BASE_PATH . '/assets');

// Detect the folder name dynamically
$folder_name = basename(BASE_PATH);

// Site URL - Build dynamically
$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];

// For asset URLs - use the full path
define('SITE_URL', $protocol . $host . '/' . $folder_name);
define('ASSET_URL', SITE_URL . '/assets');

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.cookie_path', '/' . $folder_name . '/');
    
    session_start();
}

// Error reporting (turn off in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Manila');

// Session timeout (30 minutes)
define('SESSION_TIMEOUT', 1800);

// Check session timeout
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    header('Location: ' . SITE_URL . '/login.php?timeout=1');
    exit();
}
$_SESSION['last_activity'] = time();

// ============================================
// EMAIL & SMTP CONFIGURATION
// ============================================

// System Settings
define('SITE_NAME', 'IMS');

// Email Configuration
define('SUPPORT_EMAIL', 'veripoolresort@gmail.com');
define('ADMIN_EMAIL', 'veripoolresort@gmail.com');
define('NOREPLY_EMAIL', 'veripoolresort@gmail.com');

// SMTP Configuration (Gmail)
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'veripoolresort@gmail.com');
define('SMTP_PASS', 'vxoxgejvdrubhwpz');
define('SMTP_SECURE', 'tls');
define('SMTP_AUTH', true);

// Remove spaces from app password if needed
define('SMTP_PASS_CLEAN', str_replace(' ', '', SMTP_PASS));
?>