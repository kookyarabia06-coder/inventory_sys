<?php
/**
 * Main Configuration File
 * Define base paths and constants
 */

// Define base path constants - use absolute paths
define('BASE_PATH', 'C:/xampp/htdocs/inventory_sys');
define('INCLUDE_PATH', BASE_PATH . '/includes');
define('CONFIG_PATH', BASE_PATH . '/config');
define('UPLOAD_PATH', BASE_PATH . '/uploads');
define('ASSET_PATH', BASE_PATH . '/assets');

// Site URL - hardcode for now to avoid issues
define('SITE_URL', 'http://localhost/inventory_sys');
define('ASSET_URL', SITE_URL . '/assets');

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    // Set secure session parameters
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.cookie_path', '/inventory_sys/');
    
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
?>