<?php
/**
 * Audit Trail Page (Super Admin)
 * Complete history of all system changes and user activities
 */

$page_title = 'Audit Trail - Complete Activity Log';
$page_description = 'Complete history of all system changes and user activities';

require_once '../includes/auth.php';
requireRole('super_admin');

// ============================================
// AJAX HANDLERS FOR USER AUDIT
// ============================================

// Get user details for modal
if (isset($_GET['get_user_details_ajax']) && is_numeric($_GET['get_user_details_ajax'])) {
    header('Content-Type: application/json');
    
    $user_id = (int)$_GET['get_user_details_ajax'];
    
    $query = "
        SELECT u.*, 
               CONCAT(u.firstname, ' ', u.lastname) as fullname,
               (SELECT CONCAT(e.firstname, ' ', e.lastname) FROM employees e WHERE e.user_id = u.id LIMIT 1) as employee_name,
               (SELECT e.position FROM employees e WHERE e.user_id = u.id LIMIT 1) as employee_position,
               (SELECT s.name FROM sections s WHERE s.id = (SELECT e.section_id FROM employees e WHERE e.user_id = u.id LIMIT 1)) as employee_section
        FROM users u
        WHERE u.id = ?
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $row = $result->fetch_assoc()) {
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $row['id'],
                'username' => $row['username'],
                'firstname' => $row['firstname'],
                'lastname' => $row['lastname'],
                'fullname' => $row['fullname'],
                'email' => $row['email'],
                'role' => $row['role'],
                'status' => $row['status'],
                'created_at' => $row['created_at'],
                'last_login' => $row['last_login'] ?? '—',
                'employee_info' => [
                    'employee_name' => $row['employee_name'],
                    'position' => $row['employee_position'],
                    'section_name' => $row['employee_section']
                ]
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    exit;
}

// Get user audit history
if (isset($_GET['get_user_audit_history']) && is_numeric($_GET['get_user_audit_history'])) {
    header('Content-Type: application/json');
    
    $user_id = (int)$_GET['get_user_audit_history'];
    
    $query = "
        SELECT at.*, 
               CONCAT(actor.firstname, ' ', actor.lastname) as actor_name
        FROM audit_trail at
        LEFT JOIN users actor ON at.user_id = actor.id
        WHERE at.record_id = ? AND at.table_name = 'users'
        ORDER BY at.created_at DESC
        LIMIT 100
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'history' => $history]);
    exit;
}
// Get user audit history
if (isset($_GET['get_user_audit_history']) && is_numeric($_GET['get_user_audit_history'])) {
    header('Content-Type: application/json');
    
    $user_id = (int)$_GET['get_user_audit_history'];
    
    $query = "
        SELECT at.*, 
               CONCAT(actor.firstname, ' ', actor.lastname) as actor_name
        FROM audit_trail at
        LEFT JOIN users actor ON at.user_id = actor.id
        WHERE at.record_id = ? AND at.table_name = 'users'
        ORDER BY at.created_at DESC
        LIMIT 100
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $history = [];
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'history' => $history]);
    exit;
}




// Check if audit_trail table exists, if not create it
$table_check = $conn->query("SHOW TABLES LIKE 'audit_trail'");
if (!$table_check || $table_check->num_rows == 0) {
    $create_table = "
    CREATE TABLE IF NOT EXISTS audit_trail (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NULL,
        action VARCHAR(50) NOT NULL,
        action_category VARCHAR(30) NOT NULL,
        table_name VARCHAR(50) NULL,
        record_id INT NULL,
        description TEXT NULL,
        old_value JSON NULL,
        new_value JSON NULL,
        details JSON NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_action (action),
        INDEX idx_action_category (action_category),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    $conn->query($create_table);
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;

// Filters
$filter_action_category = isset($_GET['action_category']) ? sanitize($_GET['action_category']) : '';
$filter_action = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$filter_table = isset($_GET['table']) ? sanitize($_GET['table']) : '';
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query
$conditions = [];
$params = [];
$types = '';

if ($filter_action_category) {
    $conditions[] = "at.action_category = ?";
    $params[] = $filter_action_category;
    $types .= 's';
}
if ($filter_action) {
    $conditions[] = "at.action = ?";
    $params[] = $filter_action;
    $types .= 's';
}
if ($filter_table) {
    $conditions[] = "at.table_name = ?";
    $params[] = $filter_table;
    $types .= 's';
}
if ($filter_user) {
    $conditions[] = "at.user_id = ?";
    $params[] = $filter_user;
    $types .= 'i';
}
if ($date_from) {
    $conditions[] = "DATE(at.created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if ($date_to) {
    $conditions[] = "DATE(at.created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}
if ($search) {
    $conditions[] = "(at.action LIKE ? OR at.table_name LIKE ? OR at.description LIKE ? OR at.details LIKE ? OR at.ip_address LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'sssss';
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$query = "
    SELECT at.*, 
           CONCAT(u.firstname, ' ', u.lastname) as user_name,
           u.username,
           u.role as user_role
    FROM audit_trail at
    LEFT JOIN users u ON at.user_id = u.id
    $where_clause
    ORDER BY at.created_at DESC
";

// Get paginated results
$audit_logs = paginate($query, $page, $per_page, $params, $types);

// Get statistics with error handling
$today = date('Y-m-d');
$stats_query = "
    SELECT 
        COALESCE(COUNT(*), 0) as total_activities,
        COALESCE(SUM(CASE WHEN action = 'LOGIN' AND DATE(created_at) = '$today' THEN 1 ELSE 0 END), 0) as today_logins,
        COALESCE(SUM(CASE WHEN action = 'FAILED_LOGIN' AND DATE(created_at) = '$today' THEN 1 ELSE 0 END), 0) as today_failed_logins,
        COALESCE(SUM(CASE WHEN action = 'ACCOUNT_LOCKED' AND DATE(created_at) = '$today' THEN 1 ELSE 0 END), 0) as today_locks,
        COALESCE(SUM(CASE WHEN action_category = 'EQUIPMENT' AND DATE(created_at) = '$today' THEN 1 ELSE 0 END), 0) as today_equipment,
        COALESCE(SUM(CASE WHEN action IN ('ISSUE_EQUIPMENT', 'RETURN_EQUIPMENT') AND DATE(created_at) = '$today' THEN 1 ELSE 0 END), 0) as today_issuance,
        COALESCE(SUM(CASE WHEN action = 'PRINT_REPORT' AND DATE(created_at) = '$today' THEN 1 ELSE 0 END), 0) as today_reports,
        COALESCE(SUM(CASE WHEN action IN ('NEW_USER', 'UPDATE_USER', 'DELETE_USER') AND DATE(created_at) = '$today' THEN 1 ELSE 0 END), 0) as today_user_changes,
        COALESCE(SUM(CASE WHEN action = 'LOGIN' THEN 1 ELSE 0 END), 0) as total_logins,
        COALESCE(SUM(CASE WHEN action = 'FAILED_LOGIN' THEN 1 ELSE 0 END), 0) as total_failed_logins,
        COALESCE(SUM(CASE WHEN action = 'ACCOUNT_LOCKED' THEN 1 ELSE 0 END), 0) as total_locks,
        COALESCE(SUM(CASE WHEN action = 'INSERT' THEN 1 ELSE 0 END), 0) as total_inserts,
        COALESCE(SUM(CASE WHEN action = 'UPDATE' THEN 1 ELSE 0 END), 0) as total_updates,
        COALESCE(SUM(CASE WHEN action = 'DELETE' THEN 1 ELSE 0 END), 0) as total_deletes
    FROM audit_trail
";

$stats_result = $conn->query($stats_query);

// Initialize stats with default values
$stats = [
    'total_activities' => 0,
    'today_logins' => 0,
    'today_failed_logins' => 0,
    'today_locks' => 0,
    'today_equipment' => 0,
    'today_issuance' => 0,
    'today_reports' => 0,
    'today_user_changes' => 0,
    'total_logins' => 0,
    'total_failed_logins' => 0,
    'total_locks' => 0,
    'total_inserts' => 0,
    'total_updates' => 0,
    'total_deletes' => 0
];

if ($stats_result && $stats_result->num_rows > 0) {
    $stats = array_merge($stats, $stats_result->fetch_assoc());
}

// Get distinct values for filters
$action_categories = $conn->query("SELECT DISTINCT action_category FROM audit_trail WHERE action_category IS NOT NULL ORDER BY action_category");
$actions = $conn->query("SELECT DISTINCT action, action_category FROM audit_trail ORDER BY action_category, action");
$tables = $conn->query("SELECT DISTINCT table_name FROM audit_trail WHERE table_name IS NOT NULL AND table_name != '' ORDER BY table_name");
$users_list = $conn->query("SELECT id, username, firstname, lastname FROM users ORDER BY username");

// Build filter query string
function buildFilterQuery() {
    $params = [];
    if (!empty($_GET['action_category'])) $params[] = 'action_category=' . urlencode($_GET['action_category']);
    if (!empty($_GET['action'])) $params[] = 'action=' . urlencode($_GET['action']);
    if (!empty($_GET['table'])) $params[] = 'table=' . urlencode($_GET['table']);
    if (!empty($_GET['user_id'])) $params[] = 'user_id=' . urlencode($_GET['user_id']);
    if (!empty($_GET['date_from'])) $params[] = 'date_from=' . urlencode($_GET['date_from']);
    if (!empty($_GET['date_to'])) $params[] = 'date_to=' . urlencode($_GET['date_to']);
    if (!empty($_GET['search'])) $params[] = 'search=' . urlencode($_GET['search']);
    return !empty($params) ? '&' . implode('&', $params) : '';
}

include '../includes/header.php';
?>

<style>
:root {
    --primary: #6B8CFF;
    --secondary: #8FB5FF;
    --accent: #F8B0C0;
    --accent-light: #FFD8E0;
    --success-light: #C5E8C5;
    --light: #F0F0F0;
    --white: #FFFFFF;
    --border-light: #E0E0E0;
    --text-primary: #3A3A3A;
    --text-secondary: #6B6B6B;
    --text-muted: #9E9E9E;
    --text-light: #FFFFFF;
    --success: #4CAF50;
    --danger: #f44336;
    --warning: #FFB74D;
    --info: #8FB5FF;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background-color: var(--light);
    color: var(--text-primary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

/* Dashboard Cards */
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background: var(--white);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 2px 8px rgba(107, 140, 255, 0.08);
    border-left: 4px solid var(--primary);
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(107, 140, 255, 0.12);
}

.card-icon {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, var(--accent-light) 0%, var(--white) 100%);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
}

.card-icon i {
    font-size: 22px;
    color: var(--primary);
}

.card h3 {
    color: var(--text-secondary);
    font-size: 12px;
    margin-bottom: 8px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card .card-value {
    color: var(--primary);
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 5px;
}

.card .card-label {
    color: var(--text-muted);
    font-size: 11px;
}

/* Filter Section */
.filter-section {
    background: var(--white);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(107, 140, 255, 0.08);
}

.filter-section h3 {
    color: var(--primary);
    margin-bottom: 20px;
    font-size: 16px;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
}

.filter-section h3 i {
    color: var(--accent);
    font-size: 18px;
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 12px;
}

.form-group label i {
    color: var(--primary);
    margin-right: 6px;
    width: 16px;
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border-light);
    border-radius: 10px;
    font-size: 13px;
    transition: all 0.2s ease;
    background: var(--white);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

select.form-control {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236B6B6B'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 18px;
}

.filter-actions {
    display: flex;
    gap: 10px;
    margin-top: 10px;
}

/* Search Box */
.search-box {
    display: flex;
    gap: 10px;
    margin-top: 20px;
    padding-top: 20px;
    border-top: 1px solid var(--border-light);
}

.search-box input {
    flex: 1;
}

/* Table Container */
.table-container {
    background: var(--white);
    border-radius: 16px;
    padding: 24px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(107, 140, 255, 0.08);
    overflow-x: auto;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--accent-light);
    flex-wrap: wrap;
    gap: 15px;
}

.table-header h2 {
    color: var(--primary);
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-header h2 i {
    color: var(--accent);
    font-size: 20px;
}

.table-actions {
    display: flex;
    gap: 10px;
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 8px 16px;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    text-decoration: none;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 11px;
}

.btn-primary {
    background: var(--primary);
    color: var(--white);
}

.btn-primary:hover {
    background: #5a7ae6;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.3);
}

.btn-secondary {
    background: var(--secondary);
    color: var(--white);
}

.btn-secondary:hover {
    background: #7a9fe6;
    transform: translateY(-1px);
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--border-light);
    color: var(--text-secondary);
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* Table Styles */
table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1000px;
}

thead tr {
    background: linear-gradient(135deg, var(--light) 0%, var(--white) 100%);
    border-bottom: 2px solid var(--accent-light);
}

th {
    padding: 14px 12px;
    text-align: left;
    color: var(--primary);
    font-weight: 600;
    font-size: 12px;
    white-space: nowrap;
}

td {
    padding: 12px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
    font-size: 12px;
    vertical-align: middle;
}

tr:hover {
    background-color: rgba(107, 140, 255, 0.04);
}

/* Badges */
.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    white-space: nowrap;
}

.badge-success { background: var(--success-light); color: var(--success); }
.badge-danger { background: #FFEBEE; color: var(--danger); }
.badge-warning { background: #FFF3E0; color: #E65100; }
.badge-info { background: #E3F2FD; color: #1976D2; }
.badge-secondary { background: #F5F5F5; color: var(--text-secondary); }
.badge-primary { background: rgba(107, 140, 255, 0.15); color: var(--primary); }

/* View Details Button */
.view-details-btn {
    background: transparent;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    padding: 6px 12px;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.2s;
    color: var(--primary);
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.view-details-btn:hover {
    background: var(--primary);
    color: var(--white);
    border-color: var(--primary);
}

/* ============================================
   MODAL STYLES - MATCHING SETTINGS.PHP
   ============================================ */

.modal-overlay {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(3px);
    overflow-y: auto;
}

.modal-container {
    background-color: var(--white);
    margin: 5% auto;
    padding: 0;
    border-radius: 12px;
    width: 850px;
    max-width: 90%;
    box-shadow: 0 10px 30px rgba(107, 140, 255, 0.2);
    animation: modalSlideIn 0.3s;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    max-height: 85vh;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header-settings {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 2px solid var(--accent-light);
    background: var(--white);
    flex-shrink: 0;
}

.modal-header-settings h3 {
    color: var(--primary);
    margin: 0;
    font-size: 20px;
}

.modal-header-settings h3 i {
    color: var(--accent);
    margin-right: 10px;
}

.modal-close {
    cursor: pointer;
    font-size: 28px;
    font-weight: bold;
    color: var(--text-muted);
    transition: color 0.2s;
}

.modal-close:hover {
    color: var(--accent);
}

.modal-body-scroll {
    padding: 25px;
    overflow-y: auto;
    flex: 1;
}

.modal-footer-buttons {
    text-align: right;
    padding: 16px 25px;
    border-top: 1px solid var(--border-light);
    background: var(--light);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-shrink: 0;
}

/* Detail Sections */
.detail-section {
    margin-bottom: 24px;
    border: 1px solid var(--border-light);
    border-radius: 12px;
    overflow: hidden;
}

.detail-header {
    background: var(--light);
    padding: 12px 16px;
    font-weight: 600;
    color: var(--primary);
    border-bottom: 1px solid var(--border-light);
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.detail-header i {
    color: var(--accent);
}

.detail-content {
    padding: 16px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.detail-item {
    padding: 8px 0;
    border-bottom: 1px dashed var(--border-light);
}

.detail-item:last-child {
    border-bottom: none;
}

.detail-label {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    color: var(--text-secondary);
    font-size: 13px;
    word-break: break-word;
}

.detail-value code {
    background: var(--light);
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
    font-size: 12px;
}

pre {
    background: #1e1e1e;
    color: #d4d4d4;
    padding: 12px;
    border-radius: 8px;
    overflow-x: auto;
    font-size: 11px;
    font-family: 'Courier New', monospace;
    margin: 0;
}

/* Modal Buttons */
.btn-modal {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-modal-secondary {
    background-color: #6c757d;
    color: var(--text-light);
}

.btn-modal-secondary:hover {
    background-color: #5a6268;
    transform: translateY(-2px);
}

.btn-modal-primary {
    background-color: var(--accent);
    color: var(--text-primary);
}

.btn-modal-primary:hover {
    background-color: #e69eb0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(248, 176, 192, 0.3);
}

/* Scrollbar Styling */
.modal-body-scroll::-webkit-scrollbar {
    width: 6px;
}

.modal-body-scroll::-webkit-scrollbar-track {
    background: var(--light);
    border-radius: 3px;
}

.modal-body-scroll::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 24px;
    flex-wrap: wrap;
}

.pagination a, .pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border-radius: 10px;
    background: var(--white);
    color: var(--text-secondary);
    text-decoration: none;
    border: 1px solid var(--border-light);
    font-size: 13px;
    transition: all 0.2s;
}

.pagination a:hover {
    background: var(--primary);
    color: var(--white);
    border-color: var(--primary);
    transform: translateY(-1px);
}

.pagination .active {
    background: var(--primary);
    color: var(--white);
    border-color: var(--primary);
}

/* Utilities */
.text-center { text-align: center; }
.text-muted { color: var(--text-muted); }
.text-danger { color: var(--danger); }
.text-success { color: var(--success); }
.text-warning { color: #E65100; }

/* Responsive */
@media (max-width: 768px) {
    .dashboard-cards { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .filter-grid { grid-template-columns: 1fr; }
    .search-box { flex-direction: column; }
    .search-box button { width: 100%; }
    .table-actions { flex-direction: column; width: 100%; }
    .table-actions .btn { width: 100%; justify-content: center; }
    .detail-grid { grid-template-columns: 1fr; gap: 12px; }
    .modal-container { width: 95%; margin: 10% auto; }
    .modal-body-scroll { padding: 16px; }
    .modal-header-settings { padding: 15px 20px; }
    .modal-footer-buttons { padding: 12px 20px; }
}
</style>

<!-- Statistics Dashboard -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon"><i class="fas fa-sign-in-alt"></i></div>
        <h3>Today's Logins</h3>
        <div class="card-value"><?php echo number_format($stats['today_logins'] ?? 0); ?></div>
        <div class="card-label">Successful logins</div>
    </div>
    <div class="card">
        <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h3>Failed Logins</h3>
        <div class="card-value"><?php echo number_format($stats['today_failed_logins'] ?? 0); ?></div>
        <div class="card-label">Today / Total: <?php echo number_format($stats['total_failed_logins'] ?? 0); ?></div>
    </div>
    <div class="card">
        <div class="card-icon"><i class="fas fa-lock"></i></div>
        <h3>Account Locks</h3>
        <div class="card-value"><?php echo number_format($stats['today_locks'] ?? 0); ?></div>
        <div class="card-label">Today / Total: <?php echo number_format($stats['total_locks'] ?? 0); ?></div>
    </div>
    <div class="card">
        <div class="card-icon"><i class="fas fa-laptop"></i></div>
        <h3>Equipment</h3>
        <div class="card-value"><?php echo number_format($stats['today_equipment'] ?? 0); ?></div>
        <div class="card-label">Changes today</div>
    </div>
    <div class="card">
        <div class="card-icon"><i class="fas fa-exchange-alt"></i></div>
        <h3>Issuance</h3>
        <div class="card-value"><?php echo number_format($stats['today_issuance'] ?? 0); ?></div>
        <div class="card-label">Issued/Returned today</div>
    </div>
    <div class="card">
        <div class="card-icon"><i class="fas fa-users"></i></div>
        <h3>User Changes</h3>
        <div class="card-value"><?php echo number_format($stats['today_user_changes'] ?? 0); ?></div>
        <div class="card-label">Add/Update/Delete today</div>
    </div>
</div>

<!-- Advanced Filters -->
<div class="filter-section">
    <h3><i class="fas fa-filter"></i> Advanced Filters</h3>
    <form method="GET" action="">
        <div class="filter-grid">
            <div class="form-group">
                <label><i class="fas fa-tag"></i> Action Category</label>
                <select name="action_category" class="form-control">
                    <option value="">All Categories</option>
                    <?php if ($action_categories && $action_categories->num_rows > 0): ?>
                    <?php while($cat = $action_categories->fetch_assoc()): ?>
                    <option value="<?php echo $cat['action_category']; ?>" <?php echo $filter_action_category == $cat['action_category'] ? 'selected' : ''; ?>>
                        <?php echo ucfirst(str_replace('_', ' ', $cat['action_category'])); ?>
                    </option>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-cog"></i> Action</label>
                <select name="action" class="form-control">
                    <option value="">All Actions</option>
                    <?php if ($actions && $actions->num_rows > 0): ?>
                    <?php while($action = $actions->fetch_assoc()): ?>
                    <option value="<?php echo $action['action']; ?>" <?php echo $filter_action == $action['action'] ? 'selected' : ''; ?>>
                        <?php echo $action['action']; ?>
                    </option>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-table"></i> Table/Module</label>
                <select name="table" class="form-control">
                    <option value="">All Tables</option>
                    <?php if ($tables && $tables->num_rows > 0): ?>
                    <?php while($table = $tables->fetch_assoc()): ?>
                    <option value="<?php echo $table['table_name']; ?>" <?php echo $filter_table == $table['table_name'] ? 'selected' : ''; ?>>
                        <?php echo ucfirst(str_replace('_', ' ', $table['table_name'])); ?>
                    </option>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-user"></i> User</label>
                <select name="user_id" class="form-control">
                    <option value="">All Users</option>
                    <?php if ($users_list && $users_list->num_rows > 0): ?>
                    <?php while($user = $users_list->fetch_assoc()): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                    </option>
                    <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
            </div>
            <div class="form-group">
                <label><i class="fas fa-calendar"></i> Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
            </div>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Apply Filters</button>
            <a href="?" class="btn btn-outline"><i class="fas fa-times"></i> Clear All</a>
        </div>
    </form>
    
    <!-- Search Box -->
    <div class="search-box">
        <form method="GET" action="" style="display: flex; gap: 10px; width: 100%;">
            <input type="text" name="search" class="form-control" placeholder="🔍 Search actions, tables, descriptions, IP addresses..." value="<?php echo htmlspecialchars($search); ?>">
            <?php foreach(['action_category', 'action', 'table', 'user_id', 'date_from', 'date_to'] as $param): ?>
                <?php if(!empty($_GET[$param])): ?>
                    <input type="hidden" name="<?php echo $param; ?>" value="<?php echo htmlspecialchars($_GET[$param]); ?>">
                <?php endif; ?>
            <?php endforeach; ?>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Search</button>
        </form>
    </div>
</div>

<!-- ============================================ -->
<!-- USER ACCOUNT AUDIT TABLE -->
<!-- ============================================ -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-users-cog"></i> User Account Audit Log</h2>
        <div class="table-actions">
            <div style="position: relative;">
                <input type="text" id="userAuditSearch" class="form-control" placeholder="🔍 Search by name, username, email, department..." style="width: 320px; padding-right: 35px;">
                <i class="fas fa-search" style="position: absolute; right: 12px; top: 12px; color: var(--text-muted);"></i>
            </div>
        </div>
    </div>
    
    <?php
    // Get user account audit logs (NEW_USER, UPDATE_USER, DELETE_USER, status changes)
    $user_audit_query = "
        SELECT at.*, 
               CONCAT(u.firstname, ' ', u.lastname) as user_name,
               u.username,
               u.email,
               u.role,
               u.status as user_current_status,
               -- For actions performed BY users
               CONCAT(actor.firstname, ' ', actor.lastname) as actor_name,
               actor.username as actor_username,
               actor.role as actor_role
        FROM audit_trail at
        LEFT JOIN users u ON at.record_id = u.id AND at.table_name = 'users'
        LEFT JOIN users actor ON at.user_id = actor.id
        WHERE at.table_name = 'users' 
           OR at.action IN ('NEW_USER', 'UPDATE_USER', 'DELETE_USER', 'USER_STATUS_CHANGE', 'ACCOUNT_LOCKED', 'PASSWORD_CHANGE', 'LOGIN', 'LOGOUT', 'FAILED_LOGIN')
        ORDER BY at.created_at DESC
        LIMIT 500
    ";
    
    $user_audit_result = $conn->query($user_audit_query);
    $user_audit_logs = [];
    if ($user_audit_result && $user_audit_result->num_rows > 0) {
        while($row = $user_audit_result->fetch_assoc()) {
            $user_audit_logs[] = $row;
        }
    }
    
    // Get unique users for the table (group by user_id)
    $unique_users = [];
    foreach ($user_audit_logs as $log) {
        if ($log['record_id'] && !isset($unique_users[$log['record_id']])) {
            $unique_users[$log['record_id']] = [
                'id' => $log['record_id'],
                'name' => $log['user_name'],
                'username' => $log['username'],
                'email' => $log['email'],
                'role' => $log['role'],
                'status' => $log['user_current_status']
            ];
        }
    }
    ?>
    
    <div style="overflow-x: auto;">
        <table class="user-audit-table" id="userAuditTable" style="width: 100%; min-width: 900px;">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last Activity</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="userAuditTableBody">
                <?php if (!empty($unique_users)): ?>
                    <?php foreach ($unique_users as $user_id => $user): 
                        // Get latest activity for this user
                        $latest_log = null;
                        $latest_action = '';
                        $latest_date = '';
                        foreach ($user_audit_logs as $log) {
                            if ($log['record_id'] == $user_id) {
                                if (!$latest_log || strtotime($log['created_at']) > strtotime($latest_log['created_at'])) {
                                    $latest_log = $log;
                                    $latest_action = $log['action'];
                                    $latest_date = $log['created_at'];
                                }
                            }
                        }
                        
                        $status_class = ($user['status'] == 'Active') ? 'badge-success' : 'badge-danger';
                        $status_text = $user['status'] ?? 'Unknown';
                        $role_class = '';
                        if ($user['role'] == 'superadmin') $role_class = 'badge-warning';
                        elseif ($user['role'] == 'admin') $role_class = 'badge-primary';
                        else $role_class = 'badge-secondary';
                        
                        // Get action icon
                        $action_icon = '📋';
                        if ($latest_action == 'NEW_USER') $action_icon = '➕';
                        elseif ($latest_action == 'UPDATE_USER') $action_icon = '✏️';
                        elseif ($latest_action == 'DELETE_USER') $action_icon = '🗑️';
                        elseif ($latest_action == 'ACCOUNT_LOCKED') $action_icon = '🔒';
                        elseif ($latest_action == 'PASSWORD_CHANGE') $action_icon = '🔑';
                        elseif ($latest_action == 'LOGIN') $action_icon = '🔐';
                        elseif ($latest_action == 'LOGOUT') $action_icon = '🚪';
                        elseif ($latest_action == 'FAILED_LOGIN') $action_icon = '❌';
                    ?>
                    <tr data-user-id="<?php echo $user_id; ?>" data-user-name="<?php echo htmlspecialchars($user['name'] ?? ''); ?>">
                        <td>
                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="width: 32px; height: 32px; background: var(--accent-light); border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                    <i class="fas fa-user" style="font-size: 14px; color: var(--primary);"></i>
                                </div>
                                <div>
                                    <strong><?php echo htmlspecialchars($user['name'] ?? 'Unknown'); ?></strong>
                                    <?php if ($latest_action): ?>
                                        <br><small class="text-muted"><?php echo $action_icon; ?> <?php echo ucfirst(str_replace('_', ' ', $latest_action)); ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <td><code><?php echo htmlspecialchars($user['username'] ?? '—'); ?></code></div>
                        <td><?php echo !empty($user['email']) ? htmlspecialchars($user['email']) : '<span class="text-muted">—</span>'; ?></div>
                        <td><span class="badge <?php echo $role_class; ?>"><?php echo htmlspecialchars($user['role'] ?? 'user'); ?></span></div>
                        <td><span class="badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span></div>
                        <td style="white-space: nowrap;">
                            <?php if ($latest_date): ?>
                                <?php echo date('Y-m-d H:i:s', strtotime($latest_date)); ?>
                                <br><small class="text-muted"><?php echo time_ago(strtotime($latest_date)); ?></small>
                            <?php else: ?>—<?php endif; ?>
                        </div>
                        <td>
                            <button class="view-details-btn" onclick="viewUserDetails(<?php echo $user_id; ?>, '<?php echo htmlspecialchars(addslashes($user['name'] ?? '')); ?>')">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="view-details-btn" onclick="viewUserAuditHistory(<?php echo $user_id; ?>, '<?php echo htmlspecialchars(addslashes($user['name'] ?? '')); ?>')" style="margin-left: 5px;">
                                <i class="fas fa-history"></i> History
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="text-center" style="padding: 60px;">
                            <i class="fas fa-users" style="font-size: 48px; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
                            <p style="color: var(--text-muted);">No user account audit logs found</p>
                            <small>User account activities will appear here when users are created, updated, or deleted.</small>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- User Details Mini Modal -->
<div id="userDetailsModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 500px;">
        <div class="modal-header-settings">
            <h3><i class="fas fa-user-circle"></i> User Account Details</h3>
            <span class="modal-close" onclick="closeUserDetailsModal()">&times;</span>
        </div>
        <div class="modal-body-scroll" id="userDetailsContent">
            <div class="text-center" style="padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px;"></i>
                <p style="margin-top: 16px;">Loading user details...</p>
            </div>
        </div>
        <div class="modal-footer-buttons">
            <button class="btn-modal btn-modal-secondary" onclick="closeUserDetailsModal()">Close</button>
        </div>
    </div>
</div>

<!-- User Audit History Modal -->
<div id="userAuditHistoryModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 700px;">
        <div class="modal-header-settings">
            <h3><i class="fas fa-history"></i> User Audit History: <span id="historyUserName"></span></h3>
            <span class="modal-close" onclick="closeUserAuditHistoryModal()">&times;</span>
        </div>
        <div class="modal-body-scroll" id="userAuditHistoryContent">
            <div class="text-center" style="padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px;"></i>
                <p style="margin-top: 16px;">Loading audit history...</p>
            </div>
        </div>
        <div class="modal-footer-buttons">
            <button class="btn-modal btn-modal-secondary" onclick="closeUserAuditHistoryModal()">Close</button>
        </div>
    </div>
</div>

<style>
.user-audit-table tbody tr {
    transition: background 0.2s;
}
.user-audit-table tbody tr:hover {
    background-color: rgba(107, 140, 255, 0.04);
}
#userAuditSearch {
    padding: 10px 35px 10px 15px;
    border-radius: 10px;
    border: 1px solid var(--border-light);
    font-size: 13px;
    width: 100%;
}
#userAuditSearch:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}
.user-detail-row {
    display: flex;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-light);
}
.user-detail-label {
    width: 35%;
    font-weight: 600;
    color: var(--text-secondary);
}
.user-detail-value {
    width: 65%;
    color: var(--text-primary);
}
.audit-history-item {
    padding: 12px;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    gap: 15px;
    align-items: flex-start;
}
.audit-history-icon {
    width: 36px;
    height: 36px;
    background: var(--light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.audit-history-content {
    flex: 1;
}
.audit-history-action {
    font-weight: 600;
    margin-bottom: 5px;
}
.audit-history-date {
    font-size: 11px;
    color: var(--text-muted);
    margin-bottom: 5px;
}
.audit-history-desc {
    font-size: 12px;
    color: var(--text-secondary);
}
</style>

<script>
// User Audit Table Search Functionality
const searchInput = document.getElementById('userAuditSearch');
if (searchInput) {
    searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase();
        const table = document.getElementById('userAuditTable');
        if (!table) return;
        const tbody = table.getElementsByTagName('tbody')[0];
        if (!tbody) return;
        const rows = tbody.getElementsByTagName('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const row = rows[i];
            const cells = row.getElementsByTagName('td');
            if (cells.length === 0) continue;
            
            const userName = (cells[0]?.innerText || '').toLowerCase();
            const username = (cells[1]?.innerText || '').toLowerCase();
            const email = (cells[2]?.innerText || '').toLowerCase();
            
            if (userName.includes(searchTerm) || username.includes(searchTerm) || email.includes(searchTerm)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        }
    });
}

// View User Details (Mini Modal)
function viewUserDetails(userId, userName) {
    const modal = document.getElementById('userDetailsModal');
    const content = document.getElementById('userDetailsContent');
    
    if (!modal || !content) return;
    
    modal.style.display = 'block';
    content.innerHTML = '<div class="text-center" style="padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 32px;"></i><p style="margin-top: 16px;">Loading user details...</p></div>';
    
    fetch('?get_user_details_ajax=' + userId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const user = data.user;
                const statusClass = user.status === 'Active' ? 'badge-success' : 'badge-danger';
                const roleClass = user.role === 'superadmin' ? 'badge-warning' : (user.role === 'admin' ? 'badge-primary' : 'badge-secondary');
                
                let html = `
                    <div style="text-align: center; margin-bottom: 20px;">
                        <div style="width: 70px; height: 70px; background: var(--accent-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 15px;">
                            <i class="fas fa-user" style="font-size: 32px; color: var(--primary);"></i>
                        </div>
                        <h3 style="margin: 0;">${escapeHtml(user.fullname)}</h3>
                        <p style="color: var(--text-muted); margin-top: 5px;">@${escapeHtml(user.username)}</p>
                    </div>
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-info-circle"></i> Account Information</div>
                        <div class="detail-content">
                            <div class="user-detail-row">
                                <div class="user-detail-label">Full Name:</div>
                                <div class="user-detail-value">${escapeHtml(user.fullname)}</div>
                            </div>
                            <div class="user-detail-row">
                                <div class="user-detail-label">Username:</div>
                                <div class="user-detail-value"><code>${escapeHtml(user.username)}</code></div>
                            </div>
                            <div class="user-detail-row">
                                <div class="user-detail-label">Email:</div>
                                <div class="user-detail-value">${escapeHtml(user.email || '—')}</div>
                            </div>
                            <div class="user-detail-row">
                                <div class="user-detail-label">Role:</div>
                                <div class="user-detail-value"><span class="badge ${roleClass}">${escapeHtml(user.role)}</span></div>
                            </div>
                            <div class="user-detail-row">
                                <div class="user-detail-label">Status:</div>
                                <div class="user-detail-value"><span class="badge ${statusClass}">${escapeHtml(user.status)}</span></div>
                            </div>
                            <div class="user-detail-row">
                                <div class="user-detail-label">Date Created:</div>
                                <div class="user-detail-value">${escapeHtml(user.created_at || '—')}</div>
                            </div>
                            <div class="user-detail-row">
                                <div class="user-detail-label">Last Login:</div>
                                <div class="user-detail-value">${escapeHtml(user.last_login || '—')}</div>
                            </div>
                        </div>
                    </div>
                `;
                
                if (user.employee_info && user.employee_info.employee_name) {
                    html += `
                        <div class="detail-section">
                            <div class="detail-header"><i class="fas fa-briefcase"></i> Employee Information</div>
                            <div class="detail-content">
                                <div class="user-detail-row">
                                    <div class="user-detail-label">Employee Name:</div>
                                    <div class="user-detail-value">${escapeHtml(user.employee_info.employee_name)}</div>
                                </div>
                                <div class="user-detail-row">
                                    <div class="user-detail-label">Position:</div>
                                    <div class="user-detail-value">${escapeHtml(user.employee_info.position || '—')}</div>
                                </div>
                                <div class="user-detail-row">
                                    <div class="user-detail-label">Section:</div>
                                    <div class="user-detail-value">${escapeHtml(user.employee_info.section_name || '—')}</div>
                                </div>
                            </div>
                        </div>
                    `;
                }
                
                content.innerHTML = html;
            } else {
                content.innerHTML = `<div class="text-center" style="padding: 40px; color: var(--danger);"><i class="fas fa-exclamation-triangle" style="font-size: 32px;"></i><p style="margin-top: 16px;">Error: ${escapeHtml(data.message)}</p></div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `<div class="text-center" style="padding: 40px; color: var(--danger);"><i class="fas fa-exclamation-triangle" style="font-size: 32px;"></i><p style="margin-top: 16px;">Error loading user details</p></div>`;
        });
}

// View User Audit History
function viewUserAuditHistory(userId, userName) {
    const modal = document.getElementById('userAuditHistoryModal');
    const content = document.getElementById('userAuditHistoryContent');
    const userNameSpan = document.getElementById('historyUserName');
    
    if (!modal || !content || !userNameSpan) return;
    
    userNameSpan.innerHTML = escapeHtml(userName);
    modal.style.display = 'block';
    content.innerHTML = '<div class="text-center" style="padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 32px;"></i><p style="margin-top: 16px;">Loading audit history...</p></div>';
    
    fetch('?get_user_audit_history=' + userId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.history.length > 0) {
                let html = '';
                data.history.forEach(item => {
                    let icon = '📋';
                    let actionText = item.action;
                    if (item.action === 'NEW_USER') { icon = '➕'; actionText = 'Account Created'; }
                    else if (item.action === 'UPDATE_USER') { icon = '✏️'; actionText = 'Account Updated'; }
                    else if (item.action === 'DELETE_USER') { icon = '🗑️'; actionText = 'Account Deleted'; }
                    else if (item.action === 'ACCOUNT_LOCKED') { icon = '🔒'; actionText = 'Account Locked'; }
                    else if (item.action === 'PASSWORD_CHANGE') { icon = '🔑'; actionText = 'Password Changed'; }
                    else if (item.action === 'LOGIN') { icon = '🔐'; actionText = 'Logged In'; }
                    else if (item.action === 'LOGOUT') { icon = '🚪'; actionText = 'Logged Out'; }
                    else if (item.action === 'FAILED_LOGIN') { icon = '❌'; actionText = 'Failed Login Attempt'; }
                    
                    html += `
                        <div class="audit-history-item">
                            <div class="audit-history-icon">
                                <span style="font-size: 18px;">${icon}</span>
                            </div>
                            <div class="audit-history-content">
                                <div class="audit-history-action">${escapeHtml(actionText)}</div>
                                <div class="audit-history-date">${escapeHtml(item.created_at)} | IP: ${escapeHtml(item.ip_address || '—')}</div>
                                <div class="audit-history-desc">${escapeHtml(item.description || 'No description')}</div>
                                ${item.actor_name ? `<div class="audit-history-desc" style="margin-top: 5px;"><small>By: ${escapeHtml(item.actor_name)}</small></div>` : ''}
                            </div>
                        </div>
                    `;
                });
                content.innerHTML = html;
            } else {
                content.innerHTML = `<div class="text-center" style="padding: 40px;"><i class="fas fa-inbox" style="font-size: 48px; color: var(--text-muted);"></i><p style="margin-top: 16px;">No audit history found for this user.</p></div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `<div class="text-center" style="padding: 40px; color: var(--danger);"><i class="fas fa-exclamation-triangle" style="font-size: 32px;"></i><p style="margin-top: 16px;">Error loading audit history</p></div>`;
        });
}

function closeUserDetailsModal() {
    const modal = document.getElementById('userDetailsModal');
    if (modal) modal.style.display = 'none';
}

function closeUserAuditHistoryModal() {
    const modal = document.getElementById('userAuditHistoryModal');
    if (modal) modal.style.display = 'none';
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>










<!-- Audit Trail Table -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-history"></i> Activity Log</h2>
        <div class="table-actions">
            <button class="btn btn-outline" onclick="exportAuditTrail()"><i class="fas fa-download"></i> Export CSV</button>
            <button class="btn btn-outline" onclick="printAuditTrail()"><i class="fas fa-print"></i> Print</button>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>User</th>
                <th>Category</th>
                <th>Action</th>
                <th>Table</th>
                <th>Description</th>
                <th>IP Address</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($audit_logs['data']) && count($audit_logs['data']) > 0): ?>
                <?php foreach ($audit_logs['data'] as $log): ?>
                <tr>
                    <td style="white-space: nowrap;">
                        <?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?>
                        <br><small class="text-muted"><?php echo time_ago(strtotime($log['created_at'])); ?></small>
                    </td>
                    <td>
                        <?php if ($log['user_name']): ?>
                            <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                            <br><small class="text-muted"><?php echo htmlspecialchars($log['username']); ?></small>
                        <?php else: ?>
                            <em class="text-muted">System</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $cat_colors = [
                            'AUTHENTICATION' => 'info',
                            'USER_MANAGEMENT' => 'success',
                            'EQUIPMENT' => 'warning',
                            'ISSUANCE' => 'secondary',
                            'CRUD' => 'primary',
                            'REPORTS' => 'info',
                            'SECURITY' => 'danger',
                            'SYSTEM' => 'secondary'
                        ];
                        $color = $cat_colors[$log['action_category']] ?? 'secondary';
                        ?>
                        <span class="badge badge-<?php echo $color; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $log['action_category'] ?? 'OTHER')); ?>
                        </span>
                    </td>
                    <td>
                        <?php
                        $action_icons = [
                            'LOGIN' => '🔐',
                            'LOGOUT' => '🚪',
                            'FAILED_LOGIN' => '❌',
                            'ACCOUNT_LOCKED' => '🔒',
                            'PASSWORD_CHANGE' => '🔑',
                            'PROFILE_UPDATE' => '✏️',
                            'NEW_USER' => '👤+',
                            'UPDATE_USER' => '✏️👤',
                            'DELETE_USER' => '🗑️👤',
                            'INSERT' => '➕',
                            'UPDATE' => '✏️',
                            'DELETE' => '🗑️',
                            'ISSUE_EQUIPMENT' => '📤',
                            'RETURN_EQUIPMENT' => '📥',
                            'PRINT_REPORT' => '🖨️'
                        ];
                        $icon = $action_icons[$log['action']] ?? '📋';
                        ?>
                        <span class="badge badge-secondary"><?php echo $icon; ?> <?php echo htmlspecialchars($log['action']); ?></span>
                    </td>
                    <td>
                        <?php if ($log['table_name']): ?>
                            <span class="badge badge-secondary"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $log['table_name']))); ?></span>
                            <?php if ($log['record_id']): ?>
                                <br><small class="text-muted">ID: <?php echo $log['record_id']; ?></small>
                            <?php endif; ?>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td style="max-width: 280px;">
                        <?php 
                        $desc = htmlspecialchars($log['description'] ?? '');
                        echo strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc;
                        ?>
                    <td>
                    <td><code><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></code></td>
                    <td>
                        <button class="view-details-btn" onclick="viewDetails(<?php echo $log['id']; ?>)">
                            <i class="fas fa-eye"></i> View
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" class="text-center" style="padding: 60px;">
                        <i class="fas fa-inbox" style="font-size: 64px; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
                        <h4 style="color: var(--text-secondary);">No audit records found</h4>
                        <p style="color: var(--text-muted); font-size: 13px; margin-top: 8px;">Activities will appear here once users start interacting with the system.</p>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if (isset($audit_logs['total_pages']) && $audit_logs['total_pages'] > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1<?php echo buildFilterQuery(); ?>"><i class="fas fa-angle-double-left"></i></a>
            <a href="?page=<?php echo $page-1; ?><?php echo buildFilterQuery(); ?>"><i class="fas fa-angle-left"></i></a>
        <?php endif; ?>
        
        <?php
        $start = max(1, $page - 2);
        $end = min($audit_logs['total_pages'], $page + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
            <?php if ($i == $page): ?>
                <span class="active"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?><?php echo buildFilterQuery(); ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $audit_logs['total_pages']): ?>
            <a href="?page=<?php echo $page+1; ?><?php echo buildFilterQuery(); ?>"><i class="fas fa-angle-right"></i></a>
            <a href="?page=<?php echo $audit_logs['total_pages']; ?><?php echo buildFilterQuery(); ?>"><i class="fas fa-angle-double-right"></i></a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    
    <div class="text-muted" style="margin-top: 20px; text-align: center; font-size: 12px;">
        <i class="fas fa-chart-line"></i> Showing <?php echo count($audit_logs['data'] ?? []); ?> of <?php echo number_format($audit_logs['total'] ?? 0); ?> records
    </div>
</div>

<!-- Details Modal - Updated to match settings.php style -->
<div id="detailsModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header-settings">
            <h3><i class="fas fa-info-circle"></i> Activity Details</h3>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body-scroll" id="modalBody">
            <div class="text-center" style="padding: 40px;">
                <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--primary);"></i>
                <p style="margin-top: 16px; color: var(--text-muted);">Loading details...</p>
            </div>
        </div>
        <div class="modal-footer-buttons">
            <button class="btn-modal btn-modal-secondary" onclick="closeModal()">Close</button>
        </div>
    </div>
</div>

<script>
function viewDetails(id) {
    const modal = document.getElementById('detailsModal');
    const body = document.getElementById('modalBody');
    modal.style.display = 'block';
    body.innerHTML = '<div class="text-center" style="padding: 40px;"><i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--primary);"></i><p style="margin-top: 16px;">Loading details...</p></div>';
    
    fetch('../api/get_audit_details.php?id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = `
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-info-circle"></i> Basic Information</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-label"><i class="far fa-calendar-alt"></i> Timestamp</div>
                                    <div class="detail-value">${escapeHtml(data.data.created_at)}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-user"></i> User</div>
                                    <div class="detail-value">${escapeHtml(data.data.user_name || 'System')} ${data.data.username ? '(' + escapeHtml(data.data.username) + ')' : ''}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-tag"></i> Action Category</div>
                                    <div class="detail-value"><span class="badge badge-primary">${escapeHtml(data.data.action_category || 'N/A')}</span></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-cog"></i> Action</div>
                                    <div class="detail-value"><span class="badge badge-secondary">${escapeHtml(data.data.action)}</span></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-table"></i> Table/Module</div>
                                    <div class="detail-value">${escapeHtml(data.data.table_name || 'N/A')}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-hashtag"></i> Record ID</div>
                                    <div class="detail-value">${escapeHtml(data.data.record_id || 'N/A')}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-network-wired"></i> IP Address</div>
                                    <div class="detail-value"><code>${escapeHtml(data.data.ip_address || 'N/A')}</code></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label"><i class="fas fa-globe"></i> User Agent</div>
                                    <div class="detail-value"><small>${escapeHtml(data.data.user_agent || 'N/A')}</small></div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                if (data.data.description) {
                    html += `
                        <div class="detail-section">
                            <div class="detail-header"><i class="fas fa-align-left"></i> Description</div>
                            <div class="detail-content">
                                <p>${escapeHtml(data.data.description)}</p>
                            </div>
                        </div>
                    `;
                }
                
                if (data.data.old_value || data.data.new_value) {
                    html += `
                        <div class="detail-section">
                            <div class="detail-header"><i class="fas fa-code-branch"></i> Data Changes</div>
                            <div class="detail-content">
                                <div class="detail-grid">
                    `;
                    if (data.data.old_value) {
                        try {
                            let oldVal = JSON.parse(data.data.old_value);
                            html += `<div class="detail-item"><div class="detail-label text-danger">Old Values</div><div class="detail-value"><pre>${JSON.stringify(oldVal, null, 2)}</pre></div></div>`;
                        } catch(e) {
                            html += `<div class="detail-item"><div class="detail-label text-danger">Old Values</div><div class="detail-value"><pre>${escapeHtml(data.data.old_value)}</pre></div></div>`;
                        }
                    }
                    if (data.data.new_value) {
                        try {
                            let newVal = JSON.parse(data.data.new_value);
                            html += `<div class="detail-item"><div class="detail-label text-success">New Values</div><div class="detail-value"><pre>${JSON.stringify(newVal, null, 2)}</pre></div></div>`;
                        } catch(e) {
                            html += `<div class="detail-item"><div class="detail-label text-success">New Values</div><div class="detail-value"><pre>${escapeHtml(data.data.new_value)}</pre></div></div>`;
                        }
                    }
                    html += `</div></div></div>`;
                }
                
                if (data.data.details) {
                    html += `
                        <div class="detail-section">
                            <div class="detail-header"><i class="fas fa-info-circle"></i> Additional Details</div>
                            <div class="detail-content">
                                <pre>${escapeHtml(data.data.details)}</pre>
                            </div>
                        </div>
                    `;
                }
                
                body.innerHTML = html;
            } else {
                body.innerHTML = `<div class="text-center" style="padding: 40px;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; color: var(--danger);"></i><p style="margin-top: 16px; color: var(--danger);">Error: ${escapeHtml(data.message)}</p></div>`;
            }
        })
        .catch(error => {
            body.innerHTML = `<div class="text-center" style="padding: 40px;"><i class="fas fa-exclamation-triangle" style="font-size: 32px; color: var(--danger);"></i><p style="margin-top: 16px; color: var(--danger);">Error loading details</p></div>`;
        });
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function closeModal() {
    document.getElementById('detailsModal').style.display = 'none';
}

function exportAuditTrail() {
    let params = new URLSearchParams(window.location.search);
    window.location.href = '../api/export_audit.php?' + params.toString();
}

function printAuditTrail() {
    let printContent = document.querySelector('.table-container').cloneNode(true);
    let printWindow = window.open('', '_blank');
    printWindow.document.write('<html><head><title>Audit Trail Report</title>');
    printWindow.document.write('<style>');
    printWindow.document.write('body { font-family: Arial, sans-serif; margin: 20px; }');
    printWindow.document.write('table { width: 100%; border-collapse: collapse; }');
    printWindow.document.write('th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }');
    printWindow.document.write('th { background-color: #f2f2f2; }');
    printWindow.document.write('.badge { display: inline-block; padding: 2px 6px; border-radius: 4px; font-size: 11px; background: #f0f0f0; }');
    printWindow.document.write('</style>');
    printWindow.document.write('</head><body>');
    printWindow.document.write('<h2>Audit Trail Report</h2>');
    printWindow.document.write('<p>Generated: ' + new Date().toLocaleString() + '</p>');
    printWindow.document.write(printContent.innerHTML);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}

// Close modal on escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeModal();
});

// Close modal when clicking outside
window.onclick = function(e) {
    const modal = document.getElementById('detailsModal');
    if (e.target === modal) closeModal();
}
</script>

<?php 
function time_ago($timestamp) {
    $time_ago = time() - $timestamp;
    if ($time_ago < 60) return $time_ago . ' seconds ago';
    if ($time_ago < 3600) return floor($time_ago / 60) . ' minutes ago';
    if ($time_ago < 86400) return floor($time_ago / 3600) . ' hours ago';
    if ($time_ago < 2592000) return floor($time_ago / 86400) . ' days ago';
    return date('Y-m-d', $timestamp);
}

include '../includes/footer.php'; 
?>