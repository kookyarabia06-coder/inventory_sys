<?php
/**
 * Helper Functions
 * Common functions used throughout the system
 */

// Note: logActivity() and logAudit() are now only in auth.php
// They have been removed from here to avoid duplication

/**
 * ============================================
 * AUDIT TRAIL LOGGING FUNCTIONS
 * ============================================
 */

/**
 * Enhanced Logging Function - Track ALL user actions
 * @param string $action The action performed (LOGIN, LOGOUT, INSERT, UPDATE, DELETE, etc.)
 * @param int $user_id User ID who performed the action
 * @param string $description Human readable description
 * @param string $table_name Database table affected
 * @param int $record_id ID of affected record
 * @param array|null $old_values Old values before change (for updates)
 * @param array|null $new_values New values after change (for updates)
 * @param array|null $additional_details Extra details to store
 * @return bool Success or failure
 */
function logDetailedActivity($action, $user_id, $description, $table_name = null, $record_id = null, $old_values = null, $new_values = null, $additional_details = null) {
    global $conn;
    
    if (!$conn || $conn->connect_error) {
        return false;
    }
    
    // Determine action category
    $action_category = getActionCategory($action);
    
    // Get IP address (handling proxies)
    $ip_address = getUserIP();
    
    // Get user agent
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;
    if ($user_agent && strlen($user_agent) > 500) {
        $user_agent = substr($user_agent, 0, 500);
    }
    
    // Prepare old/new values as JSON
    $old_json = $old_values ? json_encode($old_values) : null;
    $new_json = $new_values ? json_encode($new_values) : null;
    $details_json = $additional_details ? json_encode($additional_details) : null;
    
    $stmt = $conn->prepare("
        INSERT INTO audit_trail (
            user_id, action, action_category, table_name, record_id, 
            description, old_value, new_value, details, ip_address, user_agent, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");
    
    if (!$stmt) {
        error_log("Audit prepare failed: " . $conn->error);
        return false;
    }
    
    // Handle null user_id
    $user_id = $user_id ?: null;
    
    $stmt->bind_param("isssissssss", 
        $user_id, $action, $action_category, $table_name, $record_id,
        $description, $old_json, $new_json, $details_json, $ip_address, $user_agent
    );
    
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Audit execute failed: " . $stmt->error);
    }
    
    $stmt->close();
    return $result;
}

/**
 * Get action category based on action type
 */
function getActionCategory($action) {
    $auth_actions = ['LOGIN', 'LOGOUT', 'LOGIN_ATTEMPT', 'FAILED_LOGIN', 'PASSWORD_CHANGE', 'ACCOUNT_LOCKED', 'ACCOUNT_UNLOCKED'];
    $user_actions = ['NEW_USER', 'UPDATE_USER', 'DELETE_USER', 'USER_APPROVED', 'USER_REJECTED', 'PROFILE_UPDATE', 'UPDATE_CREDENTIALS'];
    $equipment_actions = ['ADD_EQUIPMENT', 'UPDATE_EQUIPMENT', 'DELETE_EQUIPMENT', 'RESTORE_EQUIPMENT'];
    $issuance_actions = ['ISSUE_EQUIPMENT', 'RETURN_EQUIPMENT', 'EXTEND_DUE_DATE'];
    $crud_actions = ['INSERT', 'UPDATE', 'DELETE', 'BULK_INSERT', 'BULK_UPDATE', 'BULK_DELETE'];
    $report_actions = ['PRINT_REPORT', 'EXPORT_DATA', 'GENERATE_REPORT'];
    $security_actions = ['FAILED_LOGIN', 'ACCOUNT_LOCKED', 'ACCOUNT_UNLOCKED', 'SUSPICIOUS_ACTIVITY'];
    
    if (in_array($action, $auth_actions)) return 'AUTHENTICATION';
    if (in_array($action, $user_actions)) return 'USER_MANAGEMENT';
    if (in_array($action, $equipment_actions)) return 'EQUIPMENT';
    if (in_array($action, $issuance_actions)) return 'ISSUANCE';
    if (in_array($action, $crud_actions)) return 'CRUD';
    if (in_array($action, $report_actions)) return 'REPORTS';
    if (in_array($action, $security_actions)) return 'SECURITY';
    
    return 'SYSTEM';
}

/**
 * Log user login
 */
function logLogin($user_id, $success = true, $username = null) {
    $action = $success ? 'LOGIN' : 'FAILED_LOGIN';
    $description = $success ? "User logged in successfully" : "Failed login attempt for user: " . ($username ?? 'Unknown');
    return logDetailedActivity($action, $success ? $user_id : null, $description, 'users', $success ? $user_id : null);
}

/**
 * Log user logout
 */
function logLogout($user_id) {
    return logDetailedActivity('LOGOUT', $user_id, "User logged out", 'users', $user_id);
}

/**
 * Log account lock
 */
function logAccountLocked($user_id, $attempts) {
    return logDetailedActivity('ACCOUNT_LOCKED', $user_id, "Account locked after {$attempts} failed login attempts", 'users', $user_id, null, null, ['failed_attempts' => $attempts]);
}

/**
 * Log account unlock
 */
function logAccountUnlocked($user_id, $unlocked_by = null) {
    $description = $unlocked_by ? "Account unlocked by administrator" : "Account automatically unlocked after lockout period";
    return logDetailedActivity('ACCOUNT_UNLOCKED', $user_id, $description, 'users', $user_id);
}

/**
 * Log password change
 */
function logPasswordChange($user_id, $changed_by = null) {
    $description = $changed_by ? "Password changed by administrator (User ID: {$changed_by})" : "User changed their own password";
    return logDetailedActivity('PASSWORD_CHANGE', $user_id, $description, 'users', $user_id);
}

/**
 * Log profile update
 */
function logProfileUpdate($user_id, $edited_by = null, $changed_fields = []) {
    $description = $edited_by ? "Profile updated by administrator" : "User updated their own profile";
    return logDetailedActivity('PROFILE_UPDATE', $user_id, $description, 'users', $user_id, null, null, ['edited_by' => $edited_by, 'changed_fields' => $changed_fields]);
}

/**
 * Log user approval/rejection
 */
function logUserApproval($admin_id, $user_id, $status = 'approved', $user_data = null) {
    $action = $status === 'approved' ? 'USER_APPROVED' : 'USER_REJECTED';
    $description = $status === 'approved' ? "User account approved by administrator" : "User account rejected by administrator";
    return logDetailedActivity($action, $admin_id, $description, 'users', $user_id, null, $user_data);
}

/**
 * Log user creation (registration)
 */
function logUserRegistration($user_id, $user_data) {
    return logDetailedActivity('NEW_USER', $user_id, "New user registered (pending approval)", 'users', $user_id, null, $user_data);
}

/**
 * Log user update (edit)
 */
function logUserUpdate($editor_id, $user_id, $old_data, $new_data) {
    return logDetailedActivity('UPDATE_USER', $editor_id, "User information updated", 'users', $user_id, $old_data, $new_data);
}

/**
 * Log user deletion
 */
function logUserDeletion($deleted_by, $user_id, $user_data) {
    return logDetailedActivity('DELETE_USER', $deleted_by, "User account deleted", 'users', $user_id, $user_data, null);
}

/**
 * Log equipment action (add, update, delete)
 */
function logEquipmentAction($action, $user_id, $equipment_id, $equipment_data, $old_data = null) {
    $action_map = [
        'add' => 'ADD_EQUIPMENT',
        'update' => 'UPDATE_EQUIPMENT', 
        'delete' => 'DELETE_EQUIPMENT',
        'restore' => 'RESTORE_EQUIPMENT'
    ];
    
    $db_action = $action_map[$action] ?? strtoupper($action);
    $description = ucfirst($action) . " equipment";
    
    if ($action === 'update') {
        return logDetailedActivity($db_action, $user_id, $description, 'equipment', $equipment_id, $old_data, $equipment_data);
    }
    
    return logDetailedActivity($db_action, $user_id, $description, 'equipment', $equipment_id, null, $equipment_data);
}

/**
 * Log equipment issuance/return
 */
function logIssuanceAction($action, $user_id, $issuance_id, $equipment_id, $data = []) {
    $action_map = [
        'issue' => 'ISSUE_EQUIPMENT',
        'return' => 'RETURN_EQUIPMENT',
        'extend' => 'EXTEND_DUE_DATE'
    ];
    
    $db_action = $action_map[$action] ?? strtoupper($action);
    $description = ucfirst($action) . " equipment";
    
    return logDetailedActivity($db_action, $user_id, $description, 'issuance', $issuance_id, null, null, array_merge($data, ['equipment_id' => $equipment_id]));
}

/**
 * Log CRUD operation
 */
function logCRUDOperation($operation, $user_id, $table_name, $record_id, $old_values = null, $new_values = null) {
    $action = strtoupper($operation);
    $description = ucfirst($operation) . " record in {$table_name}";
    return logDetailedActivity($action, $user_id, $description, $table_name, $record_id, $old_values, $new_values);
}

/**
 * Log report action (print/export)
 */
function logReportAction($action, $user_id, $report_type, $params = []) {
    $action_map = [
        'print' => 'PRINT_REPORT',
        'export' => 'EXPORT_DATA',
        'generate' => 'GENERATE_REPORT'
    ];
    
    $db_action = $action_map[$action] ?? strtoupper($action);
    $description = ucfirst($action) . " {$report_type} report";
    
    return logDetailedActivity($db_action, $user_id, $description, 'reports', null, null, null, $params);
}

/**
 * Log bulk action (multiple records)
 */
function logBulkAction($action, $user_id, $table_name, $record_ids, $details = []) {
    $db_action = 'BULK_' . strtoupper($action);
    $description = "Bulk {$action} on " . count($record_ids) . " records in {$table_name}";
    
    return logDetailedActivity($db_action, $user_id, $description, $table_name, null, null, null, array_merge($details, ['record_ids' => $record_ids]));
}

/**
 * Log suspicious activity
 */
function logSuspiciousActivity($user_id, $activity_type, $details = []) {
    return logDetailedActivity('SUSPICIOUS_ACTIVITY', $user_id, "Suspicious activity detected: {$activity_type}", null, null, null, null, $details);
}

/**
 * Get audit trail count for dashboard
 */
function getAuditStats($days = 30) {
    global $conn;
    
    $stats = [];
    $today = date('Y-m-d');
    
    // Today's activities
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN action = 'LOGIN' THEN 1 ELSE 0 END) as logins,
            SUM(CASE WHEN action = 'FAILED_LOGIN' THEN 1 ELSE 0 END) as failed_logins,
            SUM(CASE WHEN action = 'ACCOUNT_LOCKED' THEN 1 ELSE 0 END) as locks,
            SUM(CASE WHEN action_category = 'EQUIPMENT' THEN 1 ELSE 0 END) as equipment_changes,
            SUM(CASE WHEN action IN ('ISSUE_EQUIPMENT', 'RETURN_EQUIPMENT') THEN 1 ELSE 0 END) as issuances
        FROM audit_trail 
        WHERE DATE(created_at) = '$today'
    ");
    if ($result) {
        $stats['today'] = $result->fetch_assoc();
    } else {
        $stats['today'] = ['total' => 0, 'logins' => 0, 'failed_logins' => 0, 'locks' => 0, 'equipment_changes' => 0, 'issuances' => 0];
    }
    
    // Last 7 days trend
    $result = $conn->query("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count
        FROM audit_trail 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stats['trend'] = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats['trend'][] = $row;
        }
    }
    
    // Top active users
    $result = $conn->query("
        SELECT 
            u.username,
            u.firstname,
            u.lastname,
            COUNT(*) as activity_count
        FROM audit_trail at
        JOIN users u ON at.user_id = u.id
        WHERE at.created_at >= DATE_SUB(NOW(), INTERVAL $days DAY)
        GROUP BY at.user_id
        ORDER BY activity_count DESC
        LIMIT 10
    ");
    $stats['top_users'] = [];
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $stats['top_users'][] = $row;
        }
    }
    
    return $stats;
}

/**
 * ============================================
 * PROPERTY NUMBER MANAGEMENT FUNCTIONS
 * ============================================
 */

/**
 * Fix existing property numbers by ensuring they follow the correct format
 * This function scans all inventory items and updates property numbers
 * to include the location code prefix (BB-DD-SSS format)
 * 
 * @param mysqli $conn Database connection
 * @return int Number of records updated
 */
function fixExistingPropertyNumbers($conn) {
    $updated_count = 0;
    
    // Get all inventory items with property numbers
    $query = "SELECT id, property_no, current_holder FROM inventory WHERE property_no IS NOT NULL AND property_no != ''";
    $result = $conn->query($query);
    
    if ($result && $result->num_rows > 0) {
        while ($item = $result->fetch_assoc()) {
            $old_property_no = $item['property_no'];
            $new_property_no = $old_property_no;
            
            // Check if property number already has location prefix (XX-XX-XXX format)
            // Format should be: BB-DD-SSS-xxxxx (Building-Department-Section)
            if (!preg_match('/^\d{2}-\d{2}-\d{3}-/', $old_property_no)) {
                // Get user's location code if assigned
                $location_code = '';
                if ($item['current_holder'] > 0) {
                    $user_query = $conn->query("
                        SELECT CONCAT(
                            LPAD(COALESCE(b.id, 0), 2, '0'), '-',
                            LPAD(COALESCE(d.id, 0), 2, '0'), '-',
                            LPAD(COALESCE(s.id, 0), 3, '0')
                        ) as location_code
                        FROM users u
                        LEFT JOIN employees e ON u.employee_id = e.id
                        LEFT JOIN sections s ON e.section_id = s.id
                        LEFT JOIN departments d ON s.department_id = d.id
                        LEFT JOIN buildings b ON d.building_id = b.id
                        WHERE u.id = {$item['current_holder']}
                    ");
                    if ($user_query && $user_query->num_rows > 0) {
                        $loc = $user_query->fetch_assoc();
                        $location_code = $loc['location_code'];
                    }
                }
                
                // If no location code found, use default '00-00-000'
                if (empty($location_code) || $location_code == '0-0-0') {
                    $location_code = '00-00-000';
                }
                
                // Remove any existing location prefix that might be incomplete
                $original_number = preg_replace('/^[\d-]+-/', '', $old_property_no);
                if ($original_number == $old_property_no) {
                    $original_number = $old_property_no;
                }
                
                $new_property_no = $location_code . '-' . $original_number;
                
                // Update the database
                $update_query = $conn->prepare("UPDATE inventory SET property_no = ? WHERE id = ?");
                $update_query->bind_param("si", $new_property_no, $item['id']);
                if ($update_query->execute()) {
                    $updated_count++;
                }
                $update_query->close();
            }
        }
    }
    
    return $updated_count;
}

/**
 * Update property number when item is issued to a user
 * Adds the user's location code prefix to the property number
 * 
 * @param mysqli $conn Database connection
 * @param int $inventory_id Inventory item ID
 * @param int $user_id User ID the item is being issued to
 * @return bool Success status
 */
function updatePropertyNumberOnIssuance($conn, $inventory_id, $user_id) {
    // Get user's location code
    $location_query = $conn->query("
        SELECT CONCAT(
            LPAD(COALESCE(b.id, 0), 2, '0'), '-',
            LPAD(COALESCE(d.id, 0), 2, '0'), '-',
            LPAD(COALESCE(s.id, 0), 3, '0')
        ) as location_code
        FROM users u
        LEFT JOIN employees e ON u.employee_id = e.id
        LEFT JOIN sections s ON e.section_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN buildings b ON d.building_id = b.id
        WHERE u.id = $user_id
    ");
    
    if ($location_query && $location_query->num_rows > 0) {
        $loc = $location_query->fetch_assoc();
        $location_code = $loc['location_code'];
        
        if (!empty($location_code) && $location_code != '0-0-0') {
            // Get current property number
            $item_query = $conn->query("SELECT property_no FROM inventory WHERE id = $inventory_id");
            if ($item_query && $item_query->num_rows > 0) {
                $item = $item_query->fetch_assoc();
                $current_property = $item['property_no'];
                
                // Check if property number already has location prefix
                if (!preg_match('/^\d{2}-\d{2}-\d{3}-/', $current_property)) {
                    $new_property = $location_code . '-' . $current_property;
                    $conn->query("UPDATE inventory SET property_no = '$new_property' WHERE id = $inventory_id");
                    return true;
                }
            }
        }
    }
    
    return false;
}

/**
 * Restore original property number when item is returned
 * Removes the location code prefix
 * 
 * @param mysqli $conn Database connection
 * @param int $inventory_id Inventory item ID
 * @return bool Success status
 */
function restorePropertyNumberOnReturn($conn, $inventory_id) {
    // Get current property number
    $item_query = $conn->query("SELECT property_no FROM inventory WHERE id = $inventory_id");
    if ($item_query && $item_query->num_rows > 0) {
        $item = $item_query->fetch_assoc();
        $current_property = $item['property_no'];
        
        // Remove location prefix (XX-XX-XXX-)
        $restored_property = preg_replace('/^\d{2}-\d{2}-\d{3}-/', '', $current_property);
        
        if ($restored_property != $current_property) {
            $conn->query("UPDATE inventory SET property_no = '$restored_property' WHERE id = $inventory_id");
            return true;
        }
    }
    
    return false;
}

/**
 * ============================================
 * ORIGINAL FUNCTIONS BELOW
 * ============================================
 */

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
 * @param array $params
 * @param string $types
 * @return array
 */
function paginate($query, $page, $per_page, $params = [], $types = '') {
    global $conn;
    
    $page = max(1, (int)$page);
    $per_page = max(1, (int)$per_page);
    $offset = ($page - 1) * $per_page;
    
    // Get total count
    $count_query = preg_replace('/ORDER BY .*$/i', '', $query);
    $count_query = preg_replace('/SELECT.*?FROM/i', 'SELECT COUNT(*) as total FROM', $count_query, 1);
    
    $total_rows = 0;
    if (!empty($params) && !empty($types)) {
        $stmt = $conn->prepare($count_query);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            $total_row = $result->fetch_assoc();
            $total_rows = $total_row['total'] ?? 0;
            $stmt->close();
        }
    } else {
        $result = $conn->query($count_query);
        if ($result) {
            $total_row = $result->fetch_assoc();
            $total_rows = $total_row['total'] ?? 0;
        }
    }
    
    $total_pages = $total_rows > 0 ? ceil($total_rows / $per_page) : 0;
    
    // Add LIMIT to original query
    $query .= " LIMIT $offset, $per_page";
    
    $data = [];
    if (!empty($params) && !empty($types)) {
        $stmt = $conn->prepare($query);
        if ($stmt) {
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
            $stmt->close();
        }
    } else {
        $result = $conn->query($query);
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $data[] = $row;
            }
        }
    }
    
    return [
        'data' => $data,
        'current_page' => $page,
        'per_page' => $per_page,
        'total_rows' => $total_rows,
        'total_pages' => $total_pages,
        'has_previous' => $page > 1,
        'has_next' => $page < $total_pages,
        'from' => $offset + 1,
        'to' => min($offset + $per_page, $total_rows)
    ];
}

/**
 * Display pagination links
 * @param array $pagination
 * @param string $url
 * @return string
 */
function displayPagination($pagination, $url = '?page=') {
    if (($pagination['total_pages'] ?? 0) <= 1) {
        return '';
    }

    $html = '<div class="pagination" style="display: flex; justify-content: center; gap: 5px; margin-top: 20px;">';

    // Previous button
    if ($pagination['has_previous'] ?? false) {
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
    if ($pagination['has_next'] ?? false) {
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
    
    // Handle multiple IPs (take first one)
    if (strpos($ipaddress, ',') !== false) {
        $ipaddress = explode(',', $ipaddress)[0];
    }
    
    return trim($ipaddress);
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

/**
 * ============================================
 * NOTIFICATION SYSTEM FUNCTIONS
 * ============================================
 */

/**
 * Get user notifications from database
 * 
 * @param int $user_id User ID
 * @param int $limit Limit of notifications to fetch
 * @return array
 */
function getUserNotifications($user_id, $limit = 10) {
    global $conn;
    
    // Check if notifications table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($table_check->num_rows == 0) {
        return [];
    }
    
    $query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    return $notifications;
}

/**
 * Get unread notifications count for a user
 * 
 * @param int $user_id User ID
 * @return int
 */
function getUnreadNotificationsCount($user_id) {
    global $conn;
    
    // Check if notifications table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($table_check->num_rows == 0) {
        return 0;
    }
    
    $query = "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    
    return $row['count'] ?? 0;
}

/**
 * Mark a specific notification as read
 * 
 * @param int $notification_id Notification ID
 * @param int $user_id User ID
 * @return bool
 */
function markSingleNotificationAsRead($notification_id, $user_id) {
    global $conn;
    
    $query = "UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $notification_id, $user_id);
    return $stmt->execute();
}

/**
 * Mark all notifications as read for a user
 * 
 * @param int $user_id User ID
 * @return bool
 */
function markAllNotificationsAsRead($user_id) {
    global $conn;
    
    $query = "UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

/**
 * Send notification to admin users
 * 
 * @param string $type Notification type (user_registration, low_stock, overdue, etc.)
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string|null $link Optional link to redirect to
 * @return bool
 */
function sendAdminNotification($type, $title, $message, $link = null) {
    global $conn;
    
    // Check if notifications table exists, if not, create it
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($table_check->num_rows == 0) {
        createNotificationsTable();
    }
    
    // Get all admin users (super_admin and admin roles)
    $adminQuery = "SELECT id FROM users WHERE role IN ('super_admin', 'admin')";
    $adminResult = $conn->query($adminQuery);
    
    if ($adminResult && $adminResult->num_rows > 0) {
        $success = true;
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        
        while ($admin = $adminResult->fetch_assoc()) {
            $stmt->bind_param("issss", $admin['id'], $type, $title, $message, $link);
            if (!$stmt->execute()) {
                $success = false;
            }
        }
        $stmt->close();
        return $success;
    }
    
    return false;
}

/**
 * Send notification to a specific user
 * 
 * @param int $user_id User ID to send notification to
 * @param string $type Notification type
 * @param string $title Notification title
 * @param string $message Notification message
 * @param string|null $link Optional link
 * @return bool
 */
function sendUserNotification($user_id, $type, $title, $message, $link = null) {
    global $conn;
    
    // Check if notifications table exists
    $table_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    if ($table_check->num_rows == 0) {
        createNotificationsTable();
    }
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
    return $stmt->execute();
}

/**
 * Create notifications table if it doesn't exist
 */
function createNotificationsTable() {
    global $conn;
    
    $sql = "CREATE TABLE IF NOT EXISTS notifications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type VARCHAR(50) NOT NULL,
        title VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        link VARCHAR(255) DEFAULT NULL,
        is_read TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_is_read (is_read),
        INDEX idx_created_at (created_at),
        INDEX idx_type (type),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($sql);
}

/**
 * Delete old notifications (older than specified days)
 * 
 * @param int $days Days to keep (default 90 days)
 * @return bool
 */
function deleteOldNotifications($days = 90) {
    global $conn;
    
    $query = "DELETE FROM notifications WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $days);
    return $stmt->execute();
}

/**
 * Get notification by ID
 * 
 * @param int $notification_id Notification ID
 * @return array|null
 */
function getNotificationById($notification_id) {
    global $conn;
    
    $query = "SELECT * FROM notifications WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $notification_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    return $result->fetch_assoc();
}

/**
 * Get all notifications for a user with pagination
 * 
 * @param int $user_id User ID
 * @param int $page Page number
 * @param int $per_page Items per page
 * @return array
 */
function getAllUserNotifications($user_id, $page = 1, $per_page = 20) {
    global $conn;
    
    $offset = ($page - 1) * $per_page;
    
    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM notifications WHERE user_id = ?";
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $total = $result->fetch_assoc()['total'];
    
    // Get paginated notifications
    $query = "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("iii", $user_id, $per_page, $offset);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    
    return [
        'notifications' => $notifications,
        'total' => $total,
        'current_page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
    ];
}

/**
 * Convert timestamp to time ago string for notifications
 * 
 * @param string $datetime
 * @return string
 */
function timeAgoShort($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        return $minutes . 'm';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . 'h';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . 'd';
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        return $weeks . 'w';
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        return $months . 'mo';
    } else {
        return date('M j, Y', $time);
    }
}

?>