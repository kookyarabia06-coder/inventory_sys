<?php
/**
 * Helper Functions
 * Common functions used throughout the system
 */

// Note: logActivity() and logAudit() are now only in auth.php
// They have been removed from here to avoid duplication

/**
 * Sanitize input data
 * @param string $data
 * @return string
 */
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8'));
}

/**
 * Get user full name by ID
 * @param int $user_id
 * @return string
 */
function getUserName($user_id) {
    global $conn;
    $user_id = (int)$user_id;
    $result = $conn->query("SELECT firstname, lastname FROM users WHERE id = $user_id");
    if ($result && $row = $result->fetch_assoc()) {
        return htmlspecialchars($row['firstname'] . ' ' . $row['lastname']);
    }
    return 'Unknown User';
}

/**
 * Get inventory item details
 * @param int $item_id
 * @return array|null
 */
function getInventoryItem($item_id) {
    global $conn;
    $item_id = (int)$item_id;
    $result = $conn->query("
        SELECT i.*, e.name as equipment_name, s.name as section_name 
        FROM inventory i 
        LEFT JOIN equipment e ON i.equipment_id = e.id 
        LEFT JOIN sections s ON i.section_id = s.id 
        WHERE i.id = $item_id
    ");
    return $result ? $result->fetch_assoc() : null;
}

/**
 * Format date
 * @param string $date
 * @param string $format
 * @return string
 */
function formatDate($date, $format = 'M d, Y') {
    if (!$date || $date == '0000-00-00' || $date == '0000-00-00 00:00:00') return 'N/A';
    $timestamp = strtotime($date);
    return $timestamp ? date($format, $timestamp) : 'N/A';
}

/**
 * Format datetime
 * @param string $datetime
 * @return string
 */
function formatDateTime($datetime) {
    return formatDate($datetime, 'M d, Y h:i A');
}

/**
 * Format currency
 * @param float $amount
 * @return string
 */
function formatCurrency($amount) {
    return '₱' . number_format($amount, 2);
}

/**
 * Get status badge HTML
 * @param string $status
 * @return string
 */
function getStatusBadge($status) {
    $badges = [
        'active' => 'badge-success',
        'inactive' => 'badge-danger',
        'issued' => 'badge-warning',
        'returned' => 'badge-info',
        'partial' => 'badge-primary',
        'pending' => 'badge-warning',
        'delivered' => 'badge-success',
        'packing' => 'badge-info',
        'available' => 'badge-success',
        'out of stock' => 'badge-danger',
        'low stock' => 'badge-warning'
    ];
    
    $status_key = strtolower(trim($status));
    $class = $badges[$status_key] ?? 'badge-secondary';
    return "<span class='badge $class'>" . ucfirst($status) . "</span>";
}

/**
 * Generate random string
 * @param int $length
 * @return string
 */
function generateRandomString($length = 10) {
    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

/**
 * Upload file
 * @param array $file $_FILES array
 * @param string $target_dir
 * @return string|false
 */
function uploadFile($file, $target_dir = 'uploads/') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Create directory if not exists
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx'];
    
    if (!in_array($file_extension, $allowed_extensions)) {
        return false;
    }
    
    $file_name = generateRandomString(20) . '.' . $file_extension;
    $target_file = $target_dir . $file_name;
    
    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        return $file_name;
    }
    
    return false;
}

/**
 * Pagination helper
 * @param string $query
 * @param int $page
 * @param int $per_page
 * @return array
 */
/**
 * Pagination helper
 * @param string $query
 * @param int $page
 * @param int $per_page
 * @return array
 */
function paginate($query, $page = 1, $per_page = 10) {
    global $conn;
    
    $page = max(1, (int)$page);
    $per_page = max(1, (int)$per_page);
    $offset = ($page - 1) * $per_page;
    
    // Get total count - improved version that handles subqueries
    // First, try to wrap the original query as a subquery
    $count_query = "SELECT COUNT(*) as total FROM ($query) as count_table";
    
    // If that fails, try a simpler approach
    $result = $conn->query($count_query);
    
    // If the wrapped query fails, fall back to original method
    if (!$result) {
        // Clear any error
        if ($conn->error) {
            // Reset connection error
        }
        
        // Fallback: try to extract count from original query
        $count_query = preg_replace('/SELECT.*?FROM/is', 'SELECT COUNT(*) as total FROM', $query);
        $count_query = preg_replace('/ORDER BY.*$/is', '', $count_query);
        $count_query = preg_replace('/LIMIT.*$/is', '', $count_query);
        $count_query = preg_replace('/GROUP BY.*?(\s|$)/is', '', $count_query);
        
        // Remove any subqueries in SELECT that might cause issues
        $count_query = preg_replace('/\(SELECT.*?\)/is', '0', $count_query);
        
        $result = $conn->query($count_query);
    }
    
    $total_rows = $result ? $result->fetch_assoc()['total'] : 0;
    $total_pages = ceil($total_rows / $per_page);
    
    // Get paginated data
    $paginated_query = $query . " LIMIT $offset, $per_page";
    $result = $conn->query($paginated_query);
    
    $data = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
    }
    
    return [
        'data' => $data,
        'current_page' => $page,
        'per_page' => $per_page,
        'total_rows' => $total_rows,
        'total_pages' => $total_pages,
        'has_previous' => $page > 1,
        'has_next' => $page < $total_pages
    ];
}

/**
 * Display pagination links
 * @param array $pagination
 * @param string $url
 * @return string
 */
function displayPagination($pagination, $url = '?page=') {
    if ($pagination['total_pages'] <= 1) {
        return '';
    }
    
    $html = '<div class="pagination" style="display: flex; justify-content: center; gap: 5px; margin-top: 20px;">';
    
    // Previous button
    if ($pagination['has_previous']) {
        $html .= '<a href="' . $url . ($pagination['current_page'] - 1) . '" class="btn btn-sm btn-secondary">« Previous</a>';
    }
    
    // Page numbers
    for ($i = 1; $i <= $pagination['total_pages']; $i++) {
        if ($i == $pagination['current_page']) {
            $html .= '<span class="btn btn-sm btn-primary" style="cursor: default;">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $url . $i . '" class="btn btn-sm btn-secondary">' . $i . '</a>';
        }
    }
    
    // Next button
    if ($pagination['has_next']) {
        $html .= '<a href="' . $url . ($pagination['current_page'] + 1) . '" class="btn btn-sm btn-secondary">Next »</a>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Get system setting
 * @param string $key
 * @param string $default
 * @return string
 */
function getSetting($key, $default = '') {
    global $conn;
    $key = sanitize($key);
    $result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = '$key'");
    if ($result && $row = $result->fetch_assoc()) {
        return $row['setting_value'];
    }
    return $default;
}

/**
 * Format file size
 * @param int $bytes
 * @return string
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Get time ago
 * @param string $datetime
 * @return string
 */
function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return $diff . ' seconds ago';
    } elseif ($diff < 3600) {
        return round($diff / 60) . ' minutes ago';
    } elseif ($diff < 86400) {
        return round($diff / 3600) . ' hours ago';
    } elseif ($diff < 604800) {
        return round($diff / 86400) . ' days ago';
    } elseif ($diff < 2592000) {
        return round($diff / 604800) . ' weeks ago';
    } elseif ($diff < 31536000) {
        return round($diff / 2592000) . ' months ago';
    } else {
        return round($diff / 31536000) . ' years ago';
    }
}

/**
 * Generate property number
 * @return string
 */
function generatePropertyNo() {
    global $conn;
    $year = date('Y');
    $result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE property_no LIKE '$year-%'");
    $count = $result->fetch_assoc()['count'] + 1;
    return $year . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
}

/**
 * Validate email
 * @param string $email
 * @return bool
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Send notification (placeholder - implement your notification system)
 * @param int $user_id
 * @param string $subject
 * @param string $message
 * @return bool
 */
function sendNotification($user_id, $subject, $message) {
    // Implement your notification system here (email, SMS, etc.)
    // For now, just log it
    if (function_exists('logActivity')) {
        logActivity('Notification', $user_id, "$subject: $message");
    }
    return true;
}

/**
 * Get user IP address
 * @return string
 */
function getUserIP() {
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if(isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if(isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if(isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if(isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    
    return $ipaddress;
}

/**
 * Check if string starts with
 * @param string $haystack
 * @param string $needle
 * @return bool
 */
function startsWith($haystack, $needle) {
    return substr($haystack, 0, strlen($needle)) === $needle;
}

/**
 * Check if string ends with
 * @param string $haystack
 * @param string $needle
 * @return bool
 */
function endsWith($haystack, $needle) {
    $length = strlen($needle);
    return $length === 0 || substr($haystack, -$length) === $needle;
}

/**
 * Truncate text
 * @param string $text
 * @param int $length
 * @param string $suffix
 * @return string
 */
function truncateText($text, $length = 100, $suffix = '...') {
    if (strlen($text) <= $length) {
        return $text;
    }
    return substr($text, 0, $length) . $suffix;
}

/**
 * Get month name
 * @param int $month
 * @return string
 */
function getMonthName($month) {
    $months = [
        1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
        5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
        9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
    ];
    return $months[$month] ?? 'Unknown';
}

/**
 * Get day name
 * @param int $day
 * @return string
 */
function getDayName($day) {
    $days = [
        1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday', 4 => 'Thursday',
        5 => 'Friday', 6 => 'Saturday', 7 => 'Sunday'
    ];
    return $days[$day] ?? 'Unknown';
}

/**
 * Convert number to words
 * @param float $number
 * @return string
 */
function numberToWords($number) {
    $f = new NumberFormatter("en", NumberFormatter::SPELLOUT);
    return $f->format($number);
}

/**
 * Get file extension
 * @param string $filename
 * @return string
 */
function getFileExtension($filename) {
    return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
}

/**
 * Check if request is AJAX
 * @return bool
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
}

/**
 * Return JSON response
 * @param mixed $data
 * @param int $status_code
 */
function jsonResponse($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit();
}

/**
 * Redirect with message
 * @param string $url
 * @param string $message
 * @param string $type
 */
function redirectWithMessage($url, $message, $type = 'success') {
    $_SESSION[$type] = $message;
    header('Location: ' . $url);
    exit();
}

/**
 * Get all users for dropdown
 * @param string $role_filter
 * @return array
 */
function getUsersForDropdown($role_filter = '') {
    global $conn;
    $sql = "SELECT id, username, firstname, lastname, role FROM users WHERE status = 'active'";
    if (!empty($role_filter)) {
        $sql .= " AND role = '$role_filter'";
    }
    $sql .= " ORDER BY firstname, lastname";
    
    $result = $conn->query($sql);
    $users = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    return $users;
}

/**
 * Get locations for dropdown
 * @return array
 */
function getLocationsForDropdown() {
    global $conn;
    $result = $conn->query("
        SELECT s.id, s.name as section_name, d.name as department_name, b.name as building_name
        FROM sections s
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN buildings b ON d.building_id = b.id
        ORDER BY b.name, d.name, s.name
    ");
    
    $locations = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $locations[] = $row;
        }
    }
    return $locations;
}

/**
 * Get equipment types for dropdown
 * @return array
 */
function getEquipmentForDropdown() {
    global $conn;
    $result = $conn->query("SELECT id, name, category FROM equipment ORDER BY name");
    
    $equipment = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $equipment[] = $row;
        }
    }
    return $equipment;
}

/**
 * Calculate total inventory value
 * @return float
 */
function getTotalInventoryValue() {
    global $conn;
    $result = $conn->query("SELECT SUM(unit_value * qty_physical_count) as total FROM inventory");
    return $result ? $result->fetch_assoc()['total'] ?? 0 : 0;
}

/**
 * Get low stock items
 * @param int $threshold
 * @return array
 */
function getLowStockItems($threshold = 5) {
    global $conn;
    $result = $conn->query("
        SELECT i.*, s.name as section_name 
        FROM inventory i
        LEFT JOIN sections s ON i.section_id = s.id
        WHERE i.qty_physical_count <= $threshold
        ORDER BY i.qty_physical_count ASC
    ");
    
    $items = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    return $items;
}
?>