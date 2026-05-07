<?php
/**
 * Authentication and Authorization Handler
 * Manages user sessions, login, and access control
 * THIS VERSION WORKS WITH ANY FOLDER NAME - NO HARDCODED PATHS
 */

// Get the absolute path to the root directory dynamically
$root_path = dirname(__DIR__);

// Load configuration first - with error checking
if (file_exists($root_path . '/config.php')) {
    require_once $root_path . '/config.php';
} else {
    die('Configuration file not found. Please check your installation.');
}

// Now load database using absolute path
if (defined('CONFIG_PATH') && file_exists(CONFIG_PATH . '/database.php')) {
    require_once CONFIG_PATH . '/database.php';
} elseif (file_exists($root_path . '/config/database.php')) {
    require_once $root_path . '/config/database.php';
} else {
    die('Database configuration not found.');
}

// Load functions - with fallback
if (defined('INCLUDE_PATH') && file_exists(INCLUDE_PATH . '/functions.php')) {
    require_once INCLUDE_PATH . '/functions.php';
} elseif (file_exists($root_path . '/includes/functions.php')) {
    require_once $root_path . '/includes/functions.php';
}

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user data
 * @return array|null
 */
function getCurrentUser() {
    global $conn;
    if (isLoggedIn() && isset($conn) && $conn) {
        $user_id = (int)$_SESSION['user_id'];
        $result = $conn->query("SELECT * FROM users WHERE id = $user_id AND status = 'active'");
        if ($result && $result->num_rows > 0) {
            return $result->fetch_assoc();
        } else {
            // User not found or inactive - clear session
            logout();
        }
    }
    return null;
}

/**
 * Logout function
 */
function logout() {
    // Clear all session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
}

/**
 * Check if user has specific role
 * @param string $role
 * @return bool
 */
function hasRole($role) {
    if (!isLoggedIn()) return false;
    $user = getCurrentUser();
    return $user && $user['role'] === $role;
}

/**
 * Check if user has any of the allowed roles
 * @param array $roles
 * @return bool
 */
function hasAnyRole($roles) {
    if (!isLoggedIn()) return false;
    $user = getCurrentUser();
    return $user && in_array($user['role'], $roles);
}

/**
 * Require login - redirect if not logged in
 */
function requireLogin() {
    if (!isLoggedIn()) {
        logout();
        header('Location: ' . SITE_URL . '/login.php');
        exit();
    }
}

/**
 * Require specific role - redirect if not authorized
 * @param string|array $roles
 */
function requireRole($roles) {
    requireLogin();
    $roles = is_array($roles) ? $roles : [$roles];
    
    $user = getCurrentUser();
    if (!$user || !in_array($user['role'], $roles)) {
        // Log unauthorized access attempt
        if (function_exists('logActivity')) {
            logActivity('Unauthorized Access', $_SESSION['user_id'] ?? 0, 
                       "Attempted to access page requiring roles: " . implode(', ', $roles));
        }
        
        // Redirect to appropriate dashboard
        if ($user) {
            header('Location: ' . getDashboardUrl());
        } else {
            header('Location: ' . SITE_URL . '/login.php');
        }
        exit();
    }
}

/**
 * Get user's role name in readable format
 * @return string
 */
function getUserRoleName() {
    $user = getCurrentUser();
    if (!$user) return 'Guest';
    
    $roles = [
        'superadmin' => 'Super Administrator',
        'admin' => 'Administrator',
        'supply' => 'Supply Officer',
        'user' => 'End User'
    ];
    
    return $roles[$user['role']] ?? ucfirst($user['role']);
}

/**
 * Check if current user is super admin
 * @return bool
 */
function isSuperAdmin() {
    return hasRole('superadmin');
}

/**
 * Check if current user is admin
 * @return bool
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Check if current user is supply officer
 * @return bool
 */
function isSupply() {
    return hasRole('supply');
}

/**
 * Check if current user is regular user
 * @return bool
 */
function isUser() {
    return hasRole('user');
}

/**
 * Get redirect URL based on user role
 * @return string
 */
function getDashboardUrl() {
    $user = getCurrentUser();
    if (!$user) return SITE_URL . '/login.php';
    
    switch ($user['role']) {
        case 'superadmin':
            return SITE_URL . '/superadmin/dashboard.php';
        case 'admin':
            return SITE_URL . '/admin/dashboard.php';
        case 'supply':
            return SITE_URL . '/supply/dashboard.php';
        case 'user':
        default:
            return SITE_URL . '/user/dashboard.php';
    }
}

/**
 * Generate CSRF token
 * @return string
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token
 * @return bool
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Log user activity
 * @param string $action
 * @param int $item_id
 * @param string $details
 */
function logActivity($action, $item_id = null, $details = '') {
    global $conn;
    
    if (!isset($conn) || !$conn) {
        return;
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    $date_created = date('Y-m-d H:i:s');
    
    // Check if table exists before inserting
    $table_check = $conn->query("SHOW TABLES LIKE 'activity_log'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO activity_log (user_id, action, item_id, details, date_created) VALUES (?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("isiss", $user_id, $action, $item_id, $details, $date_created);
            $stmt->execute();
            $stmt->close();
        }
    }
}

/**
 * Log audit trail
 * @param string $action
 * @param string $table_name
 * @param int $record_id
 * @param string $old_value
 * @param string $new_value
 */
function logAudit($action, $table_name, $record_id, $old_value = null, $new_value = null) {
    global $conn;
    
    if (!isset($conn) || !$conn) {
        return;
    }
    
    $user_id = $_SESSION['user_id'] ?? null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Check if table exists before inserting
    $table_check = $conn->query("SHOW TABLES LIKE 'audit_trail'");
    if ($table_check && $table_check->num_rows > 0) {
        $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, action, table_name, record_id, old_value, new_value, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ississs", $user_id, $action, $table_name, $record_id, $old_value, $new_value, $ip_address);
            $stmt->execute();
            $stmt->close();
        }
    }
}
?>