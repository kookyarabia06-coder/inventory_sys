<?php
/**
 * Employees Page (Admin)
 * Manage employees with full CRUD operations - Linked to existing Users
 */

// Add output buffering to prevent header warnings
ob_start();

$root_path = dirname(__DIR__);
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Role checking
requireRole('admin', 'superadmin');

$page_title = 'Employees';
$page_description = 'Manage employees linked to user accounts';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Check if email already exists (excluding empty emails)
 */
function emailExists($conn, $email, $exclude_id = null) {
    // Skip check if email is empty
    if (empty($email)) {
        return false;
    }
    
    $sql = "SELECT id FROM employees WHERE email = ?";
    if ($exclude_id) {
        $sql .= " AND id != ?";
    }
    $stmt = $conn->prepare($sql);
    if ($exclude_id) {
        $stmt->bind_param("si", $email, $exclude_id);
    } else {
        $stmt->bind_param("s", $email);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// Handle Add/Edit Employee
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add') {
            $firstname = sanitize($_POST['firstname']);
            $lastname = sanitize($_POST['lastname']);
            $middlename = sanitize($_POST['middlename'] ?? '');
            $email = !empty($_POST['email']) ? sanitize($_POST['email']) : null;
            $contact = sanitize($_POST['contact'] ?? '');
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
            $position = sanitize($_POST['position'] ?? '');
            $status = sanitize($_POST['status'] ?? 'Active');
            $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
            
            $errors = [];
            if (empty($firstname)) $errors[] = "First name is required";
            if (empty($lastname)) $errors[] = "Last name is required";
            
            if (!empty($email) && !validateEmail($email)) {
                $errors[] = "Invalid email format";
            }
            
            // Check if email already exists
            if (!empty($email) && emailExists($conn, $email)) {
                $errors[] = "Email address already exists in the system";
            }
            
            // Check if user_id already linked to an employee
            if ($user_id) {
                $check = $conn->query("SELECT id FROM employees WHERE user_id = $user_id");
                if ($check && $check->num_rows > 0) {
                    $errors[] = "This user is already linked to an employee";
                }
            }
            
            if (empty($errors)) {
                $stmt = $conn->prepare("
                    INSERT INTO employees (
                        firstname, lastname, middlename, email, contact,
                        department_id, section_id, position, status, user_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt) {
                    $stmt->bind_param(
                        "sssssiisssi",
                        $firstname, $lastname, $middlename, $email, $contact,
                        $department_id, $section_id, $position, $status, $user_id
                    );
                    
                    if ($stmt->execute()) {
                        $employee_id = $stmt->insert_id;
                        $_SESSION['success'] = "Employee added successfully" . ($user_id ? " and linked to user account!" : "!");
                        logActivity('Add Employee', $employee_id, "Added employee: $lastname, $firstname");
                    } else {
                        $_SESSION['error'] = "Database error: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $_SESSION['error'] = "Failed to prepare statement: " . $conn->error;
                }
            } else {
                $_SESSION['error'] = implode("<br>", $errors);
            }
            
            header('Location: ' . SITE_URL . '/admin/employees.php');
            exit();
            
        } elseif ($_POST['action'] == 'edit' && isset($_POST['id'])) {
            $id = (int)$_POST['id'];
            
            $firstname = sanitize($_POST['firstname']);
            $lastname = sanitize($_POST['lastname']);
            $middlename = sanitize($_POST['middlename'] ?? '');
            $email = !empty($_POST['email']) ? sanitize($_POST['email']) : null;
            $contact = sanitize($_POST['contact'] ?? '');
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
            $position = sanitize($_POST['position'] ?? '');
            $status = sanitize($_POST['status'] ?? 'Active');
            $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
            
            $errors = [];
            if (empty($firstname)) $errors[] = "First name is required";
            if (empty($lastname)) $errors[] = "Last name is required";
            
            if (!empty($email) && !validateEmail($email)) {
                $errors[] = "Invalid email format";
            }
            
            // Check if email already exists for another employee
            if (!empty($email) && emailExists($conn, $email, $id)) {
                $errors[] = "Email address already exists in the system";
            }
            
            // Check if user_id already linked to another employee
            if ($user_id) {
                $check = $conn->query("SELECT id FROM employees WHERE user_id = $user_id AND id != $id");
                if ($check && $check->num_rows > 0) {
                    $errors[] = "This user is already linked to another employee";
                }
            }
            
            if (empty($errors)) {
                $stmt = $conn->prepare("
                    UPDATE employees SET 
                        firstname = ?, lastname = ?, middlename = ?,
                        email = ?, contact = ?, department_id = ?,
                        section_id = ?, position = ?,
                        status = ?, user_id = ?
                    WHERE id = ?
                ");
                
                if ($stmt) {
                    $stmt->bind_param(
                        "sssssiisssi",
                        $firstname, $lastname, $middlename, $email, $contact,
                        $department_id, $section_id, $position,
                        $status, $user_id, $id
                    );
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Employee updated successfully";
                        logActivity('Edit Employee', $id, "Updated employee: $lastname, $firstname");
                    } else {
                        $_SESSION['error'] = "Error updating employee: " . $stmt->error;
                    }
                    $stmt->close();
                } else {
                    $_SESSION['error'] = "Failed to prepare statement: " . $conn->error;
                }
            } else {
                $_SESSION['error'] = implode("<br>", $errors);
            }
            
            header('Location: ' . SITE_URL . '/admin/employees.php');
            exit();
        }
    }
}

// Handle Delete Employee
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $id = (int)$_GET['delete'];
    
    // Get employee name for logging before deletion
    $name_query = $conn->query("SELECT CONCAT(lastname, ', ', firstname) as name FROM employees WHERE id = $id");
    $employee_name = $name_query && $name_query->num_rows > 0 ? $name_query->fetch_assoc()['name'] : 'Unknown';
    
    $conn->query("DELETE FROM employees WHERE id = $id");
    
    if ($conn->affected_rows > 0) {
        $_SESSION['success'] = "Employee deleted successfully";
        logActivity('Delete Employee', $id, "Deleted employee: $employee_name");
    } else {
        $_SESSION['error'] = "Error deleting employee";
    }
    
    header('Location: ' . SITE_URL . '/admin/employees.php');
    exit();
}

// Handle AJAX request to get employee details
if (isset($_GET['get_employee']) && is_numeric($_GET['get_employee'])) {
    header('Content-Type: application/json');
    
    $id = (int)$_GET['get_employee'];
    $result = $conn->query("
        SELECT e.*, 
               d.name as department_name, d.code as department_code,
               s.name as section_name, s.id as section_id,
               a.name as area_name, a.code as area_code, a.id as division_id,
               u.username, u.role as user_role, u.status as user_status,
               CONCAT(u.firstname, ' ', u.lastname) as user_fullname
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN sections s ON e.section_id = s.id
        LEFT JOIN areas a ON d.area_id = a.id
        LEFT JOIN users u ON e.user_id = u.id
        WHERE e.id = $id
    ");
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['success' => true, 'employee' => $row]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Employee not found']);
    }
    exit;
}

// Handle AJAX request to get user details when selected
if (isset($_GET['get_user_details']) && is_numeric($_GET['get_user_details'])) {
    header('Content-Type: application/json');
    
    $id = (int)$_GET['get_user_details'];
    $result = $conn->query("
        SELECT id, username, firstname, lastname, email, role, status 
        FROM users 
        WHERE id = $id
    ");
    
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['success' => true, 'user' => $row]);
    } else {
        echo json_encode(['success' => false, 'error' => 'User not found']);
    }
    exit;
}

// Handle AJAX request to get sections by department
if (isset($_GET['get_sections_by_department']) && is_numeric($_GET['get_sections_by_department'])) {
    header('Content-Type: application/json');
    
    $department_id = (int)$_GET['get_sections_by_department'];
    
    $result = $conn->query("
        SELECT s.id, s.name
        FROM sections s
        WHERE s.department_id = $department_id
        ORDER BY s.name
    ");
    
    $sections = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $sections[] = $row;
        }
    }
    echo json_encode(['success' => true, 'sections' => $sections]);
    exit;
}

// Handle AJAX request to get departments by division
if (isset($_GET['get_departments_by_division']) && is_numeric($_GET['get_departments_by_division'])) {
    header('Content-Type: application/json');
    
    $division_id = (int)$_GET['get_departments_by_division'];
    
    // Get departments that belong to this division (area)
    $result = $conn->prepare("
        SELECT d.id, d.code, d.name
        FROM departments d
        WHERE d.area_id = ?
        ORDER BY d.code, d.name
    ");
    $result->bind_param("i", $division_id);
    $result->execute();
    $dept_result = $result->get_result();
    
    $departments = [];
    if ($dept_result && $dept_result->num_rows > 0) {
        while($row = $dept_result->fetch_assoc()) {
            $departments[] = $row;
        }
    }
    $result->close();
    
    echo json_encode(['success' => true, 'departments' => $departments]);
    exit;
}

// ============================================
// DISPLAY DATA WITH PAGINATION AND SEARCH
// ============================================

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 15;
$offset = ($page - 1) * $per_page;

// Search filters
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$department_filter = isset($_GET['department_filter']) ? (int)$_GET['department_filter'] : '';
$section_filter = isset($_GET['section_filter']) ? (int)$_GET['section_filter'] : '';
$status_filter = isset($_GET['status_filter']) ? sanitize($_GET['status_filter']) : '';
$position_filter = isset($_GET['position_filter']) ? sanitize($_GET['position_filter']) : '';

// Get departments for dropdown
$departments_result = $conn->query("SELECT id, name, code FROM departments ORDER BY code, name");

// Get sections for dropdown
$sections_for_filter = [];
if ($department_filter) {
    $sections_filter_query = $conn->prepare("SELECT id, name FROM sections WHERE department_id = ? ORDER BY name");
    $sections_filter_query->bind_param("i", $department_filter);
    $sections_filter_query->execute();
    $sections_filter_result = $sections_filter_query->get_result();
    while($sec = $sections_filter_result->fetch_assoc()) {
        $sections_for_filter[] = $sec;
    }
    $sections_filter_query->close();
} else {
    $sections_filter_result = $conn->query("SELECT id, name FROM sections ORDER BY name");
    if ($sections_filter_result && $sections_filter_result->num_rows > 0) {
        while($sec = $sections_filter_result->fetch_assoc()) {
            $sections_for_filter[] = $sec;
        }
    }
}

// Get unique positions for filter dropdown
$positions_result = $conn->query("SELECT DISTINCT position FROM employees WHERE position IS NOT NULL AND position != '' ORDER BY position");

// Get users NOT yet linked to any employee for dropdown
$users_result = $conn->query("
    SELECT u.* 
    FROM users u 
    LEFT JOIN employees e ON u.id = e.user_id 
    WHERE e.id IS NULL OR e.user_id IS NULL
    ORDER BY u.lastname, u.firstname
");

// Calculate statistics
$total_employees = 0;
$active_employees = 0;
$employees_linked = 0;
$total_departments = 0;

$count_result = $conn->query("SELECT COUNT(*) as count FROM employees");
if ($count_result && $count_result->num_rows > 0) {
    $total_employees = $count_result->fetch_assoc()['count'];
}

$active_result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE status = 'Active'");
if ($active_result && $active_result->num_rows > 0) {
    $active_employees = $active_result->fetch_assoc()['count'];
}

$linked_result = $conn->query("SELECT COUNT(*) as count FROM employees WHERE user_id IS NOT NULL");
if ($linked_result && $linked_result->num_rows > 0) {
    $employees_linked = $linked_result->fetch_assoc()['count'];
}

$dept_count_result = $conn->query("SELECT COUNT(*) as count FROM departments");
if ($dept_count_result && $dept_count_result->num_rows > 0) {
    $total_departments = $dept_count_result->fetch_assoc()['count'];
}

// Build query with search filters
$where_clause = "";
$search_params = [];
$types = "";

// Text search condition
if (!empty($search)) {
    $where_clause .= " (e.lastname LIKE ? OR e.firstname LIKE ? OR e.email LIKE ? OR e.position LIKE ? OR d.name LIKE ?)";
    $search_term = "%$search%";
    $search_params = array_merge($search_params, [$search_term, $search_term, $search_term, $search_term, $search_term]);
    $types .= "sssss";
}

// Date range filter
if (!empty($date_from)) {
    if (!empty($where_clause)) $where_clause .= " AND";
    $where_clause .= " DATE(e.date_added) >= ?";
    $search_params[] = $date_from;
    $types .= "s";
}

if (!empty($date_to)) {
    if (!empty($where_clause)) $where_clause .= " AND";
    $where_clause .= " DATE(e.date_added) <= ?";
    $search_params[] = $date_to;
    $types .= "s";
}

// Department filter
if (!empty($department_filter)) {
    if (!empty($where_clause)) $where_clause .= " AND";
    $where_clause .= " e.department_id = ?";
    $search_params[] = $department_filter;
    $types .= "i";
}

// Section filter
if (!empty($section_filter)) {
    if (!empty($where_clause)) $where_clause .= " AND";
    $where_clause .= " e.section_id = ?";
    $search_params[] = $section_filter;
    $types .= "i";
}

// Status filter
if (!empty($status_filter)) {
    if (!empty($where_clause)) $where_clause .= " AND";
    $where_clause .= " e.status = ?";
    $search_params[] = $status_filter;
    $types .= "s";
}

// Position filter
if (!empty($position_filter)) {
    if (!empty($where_clause)) $where_clause .= " AND";
    $where_clause .= " e.position = ?";
    $search_params[] = $position_filter;
    $types .= "s";
}

// Add WHERE prefix if conditions exist
if (!empty($where_clause)) {
    $where_clause = " WHERE " . $where_clause;
}

// Count total rows for pagination
$count_sql = "
    SELECT COUNT(*) as total 
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN sections s ON e.section_id = s.id
    LEFT JOIN users u ON e.user_id = u.id
    $where_clause
";

$count_stmt = $conn->prepare($count_sql);
if (!empty($search_params)) {
    $count_stmt->bind_param($types, ...$search_params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$total_rows = $count_result->fetch_assoc()['total'];
$count_stmt->close();

$total_pages = ceil($total_rows / $per_page);

// Get employees with search and pagination
$sql = "
    SELECT e.*, 
           d.name as department_name, d.code as department_code,
           s.name as section_name,
           a.name as area_name, a.code as area_code,
           u.username, u.role as user_role, u.status as user_status,
           CONCAT(u.firstname, ' ', u.lastname) as user_fullname,
           CASE WHEN u.id IS NOT NULL THEN 1 ELSE 0 END as has_user_account
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN sections s ON e.section_id = s.id
    LEFT JOIN areas a ON d.area_id = a.id
    LEFT JOIN users u ON e.user_id = u.id
    $where_clause
    ORDER BY e.lastname ASC, e.firstname ASC
    LIMIT ? OFFSET ?
";

$stmt = $conn->prepare($sql);
$bind_params = array_merge($search_params, [$per_page, $offset]);
$bind_types = $types . "ii";

if (!empty($search_params)) {
    $stmt->bind_param($bind_types, ...$bind_params);
} else {
    $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$employees_result = $stmt->get_result();
$stmt->close();

include INCLUDE_PATH . '/header.php';
?>

<style>
/* CSS remains the same as your original file */
* {
    box-sizing: border-box;
}

:root {
    --primary: #6B8CFF;
    --secondary: #8FB5FF;
    --accent: #F8B0C0;
    --accent-light: #FFD8E0;
    --success-light: #C5E8C5;
    --light: #F5F7FA;
    --white: #FFFFFF;
    --border-light: #E2E8F0;
    --text-primary: #1E293B;
    --text-secondary: #475569;
    --text-muted: #94A3B8;
    --success: #10B981;
    --danger: #EF4444;
    --warning: #F59E0B;
    --info: #3B82F6;
}

body {
    background-color: var(--light);
    color: var(--text-primary);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background: var(--white);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    border-left: 4px solid var(--primary);
    transition: all 0.2s ease;
}

.card-icon {
    width: 48px;
    height: 48px;
    background: var(--accent-light);
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
    font-size: 13px;
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
    font-size: 12px;
}

.table-container {
    background: var(--white);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--accent-light);
    flex-wrap: wrap;
    gap: 15px;
}

.table-header h2 {
    color: var(--primary);
    font-size: 18px;
    margin: 0;
    font-weight: 600;
}

.table-header h2 i {
    color: var(--accent);
    margin-right: 10px;
}

.table-header p {
    color: var(--text-muted);
    font-size: 13px;
    margin: 0;
}

.advanced-search-box {
    padding: 0;
    margin-bottom: 20px;
}

.search-row {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    margin-bottom: 15px;
    align-items: flex-end;
}

.search-group {
    flex: 1;
    min-width: 180px;
}

.search-group label {
    display: block;
    margin-bottom: 6px;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.search-group input,
.search-group select {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border-light);
    border-radius: 10px;
    font-size: 14px;
    background: var(--white);
}

.search-group input:focus,
.search-group select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.search-actions {
    display: flex;
    gap: 10px;
    align-items: flex-end;
}

.btn-search {
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px 24px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 500;
}

.btn-search:hover {
    background: #5a7ae6;
}

.btn-clear {
    background: var(--text-muted);
    color: white;
    border: none;
    padding: 10px 24px;
    border-radius: 10px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-clear:hover {
    background: #7f8c8d;
}

.employee-wrapper {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid var(--border-light);
}

.employee-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    min-width: 1000px;
}

.employee-table thead {
    background: var(--light);
}

.employee-table th {
    padding: 14px 12px;
    text-align: left;
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--accent-light);
}

.employee-table td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
    vertical-align: middle;
}

.employee-table tbody tr:hover {
    background-color: var(--light);
}

.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.badge-success {
    background-color: #D1FAE5;
    color: #059669;
}

.badge-danger {
    background-color: #FEE2E2;
    color: #DC2626;
}

.badge-warning {
    background-color: #FEF3C7;
    color: #D97706;
}

.badge-info {
    background-color: #DBEAFE;
    color: #2563EB;
}

.action-buttons {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    color: var(--text-light);
    text-decoration: none;
    cursor: pointer;
    font-size: 13px;
}

.action-btn.view { background-color: var(--primary); }
.action-btn.edit { background-color: var(--secondary); }
.action-btn.delete { background-color: var(--danger); }

.action-btn:hover {
    transform: translateY(-2px);
    filter: brightness(0.95);
}

.pagination {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin-top: 25px;
    flex-wrap: wrap;
}

.pagination a, .pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 12px;
    border-radius: 8px;
    background: var(--white);
    color: var(--text-secondary);
    text-decoration: none;
    border: 1px solid var(--border-light);
    font-size: 13px;
}

.pagination a:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination .active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination .disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Modal Styles */
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
    width: 700px;
    max-width: 90%;
    box-shadow: 0 10px 30px rgba(107, 140, 255, 0.2);
    animation: modalSlideIn 0.3s;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.modal-scroll-content {
    padding: 0 25px;
    overflow-y: auto;
    flex: 1;
}

.modal-body-scroll {
    padding: 25px;
    overflow-y: auto;
    flex: 1;
}

.delete-modal-overlay {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(3px);
    align-items: center;
    justify-content: center;
    overflow-y: auto;
}

.delete-modal-container {
    background-color: var(--white);
    border-radius: 16px;
    width: 450px;
    max-width: 90%;
    box-shadow: 0 20px 35px rgba(0,0,0,0.2);
    animation: modalSlideIn 0.2s;
    overflow: hidden;
    margin: 20px auto;
}

.delete-modal-header {
    padding: 24px 24px 16px 24px;
    border-bottom: 1px solid var(--border-light);
}

.delete-modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: var(--danger);
}

.delete-modal-header h3 i {
    margin-right: 10px;
}

.delete-modal-body {
    padding: 24px;
    max-height: 60vh;
    overflow-y: auto;
}

.delete-warning {
    text-align: center;
    margin-bottom: 20px;
}

.delete-warning i {
    font-size: 48px;
    margin-bottom: 12px;
}

.delete-warning .fa-exclamation-triangle {
    color: var(--danger);
}

.delete-warning p {
    margin: 8px 0;
    font-size: 16px;
}

.delete-warning .warning-text {
    color: var(--text-secondary);
    font-size: 14px;
}

.delete-item-details {
    background-color: var(--light);
    border-radius: 12px;
    padding: 16px;
    margin-top: 16px;
}

.delete-item-details .detail-label {
    font-size: 12px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.delete-item-details .detail-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.delete-item-details .detail-extra {
    font-size: 13px;
    color: var(--text-secondary);
}

.delete-modal-footer {
    padding: 16px 24px 24px 24px;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    border-top: 1px solid var(--border-light);
    background: var(--white);
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

.form-section {
    background: var(--white);
    padding: 20px;
    margin-bottom: 20px;
    border-radius: 12px;
    border: 1px solid var(--border-light);
}

.form-section:last-child {
    margin-bottom: 0;
}

.form-section h3 {
    color: var(--primary);
    margin-bottom: 20px;
    font-size: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--accent-light);
}

.form-section h3 i {
    color: var(--accent);
    margin-right: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border-light);
    border-radius: 10px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-row .form-group {
    flex: 1;
    margin-bottom: 0;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent) 0%, #e69eb0 100%);
    color: var(--text-primary);
}

.btn-secondary {
    background-color: var(--secondary);
    color: var(--text-light);
}

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

.btn-modal-danger {
    background-color: var(--danger);
    color: var(--text-light);
}

.btn-modal-danger:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.detail-item {
    padding: 8px 0;
    border-bottom: 1px solid var(--border-light);
}

.detail-label {
    font-weight: 600;
    color: var(--text-muted);
    font-size: 11px;
    text-transform: uppercase;
    margin-bottom: 4px;
}

.detail-value {
    color: var(--text-primary);
    font-size: 14px;
}

.alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-left: 4px solid transparent;
    font-size: 13px;
}

.alert-success { background-color: #ECFDF5; color: #059669; border-left-color: #10B981; }
.alert-danger { background-color: #FEF2F2; color: #DC2626; border-left-color: #EF4444; }

.text-center { text-align: center; }
.text-muted { color: var(--text-muted) !important; }
.text-danger { color: var(--danger) !important; }
.mt-3 { margin-top: 16px; }

/* Scrollbar Styling */
.modal-scroll-content::-webkit-scrollbar,
.modal-body-scroll::-webkit-scrollbar,
.delete-modal-body::-webkit-scrollbar {
    width: 6px;
}

.modal-scroll-content::-webkit-scrollbar-track,
.modal-body-scroll::-webkit-scrollbar-track,
.delete-modal-body::-webkit-scrollbar-track {
    background: var(--light);
    border-radius: 3px;
}

.modal-scroll-content::-webkit-scrollbar-thumb,
.modal-body-scroll::-webkit-scrollbar-thumb,
.delete-modal-body::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

.modal-scroll-content::-webkit-scrollbar-thumb:hover,
.modal-body-scroll::-webkit-scrollbar-thumb:hover,
.delete-modal-body::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}

@media (max-width: 768px) {
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .search-row {
        flex-direction: column;
    }
    
    .search-group {
        width: 100%;
    }
    
    .search-actions {
        flex-direction: column;
        width: 100%;
    }
    
    .search-actions button,
    .search-actions a {
        width: 100%;
        text-align: center;
        justify-content: center;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .form-row .form-group {
        margin-bottom: 15px;
    }
    
    .modal-container {
        margin: 10% auto;
        width: 95%;
    }
    
    .delete-modal-container {
        width: 95%;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .delete-modal-footer {
        flex-direction: column-reverse;
    }
    
    .delete-modal-footer .btn-modal {
        width: 100%;
        justify-content: center;
    }
    
    .modal-footer-buttons {
        flex-direction: column-reverse;
    }
    
    .modal-footer-buttons .btn-modal {
        width: 100%;
        justify-content: center;
    }
}
</style>

<!-- Display Success/Error Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon"><i class="fas fa-users"></i></div>
        <h3>Total Employees</h3>
        <div class="card-value"><?php echo number_format($total_employees); ?></div>
        <div class="card-label">All employees</div>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-user-check"></i></div>
        <h3>Active Employees</h3>
        <div class="card-value"><?php echo number_format($active_employees); ?></div>
        <div class="card-label">Currently active</div>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-link"></i></div>
        <h3>Linked to Users</h3>
        <div class="card-value"><?php echo number_format($employees_linked); ?></div>
        <div class="card-label">Has user account linked</div>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-building"></i></div>
        <h3>Departments</h3>
        <div class="card-value"><?php echo number_format($total_departments); ?></div>
        <div class="card-label">Total departments</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-bolt"></i> Quick Actions</h2>
    </div>
    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
        <button class="btn btn-primary" onclick="openEmployeeModal()">
            <i class="fas fa-plus-circle"></i> Add New Employee
        </button>
        <a href="<?php echo SITE_URL; ?>/admin/locations.php" class="btn btn-secondary">
            <i class="fas fa-building"></i> Manage Departments
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn btn-secondary">
            <i class="fas fa-users-cog"></i> Manage Users
        </a>
    </div>
</div>

<!-- Advanced Search Bar -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-search"></i> Advanced Search</h2>
    </div>
    
    <form method="GET" action="" class="advanced-search-box" id="searchForm">
        <div class="search-row">
            <div class="search-group" style="flex: 2;">
                <label><i class="fas fa-user"></i> Name / Email / Position</label>
                <input type="text" name="search" placeholder="Search by name, email, position, or department..." 
                       value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="search-group">
                <label><i class="fas fa-building"></i> Department</label>
                <select name="department_filter" id="department_filter" onchange="this.form.submit()">
                    <option value="">-- All Departments --</option>
                    <?php 
                    $dept_filter = $conn->query("SELECT id, code, name FROM departments ORDER BY code, name");
                    if ($dept_filter && $dept_filter->num_rows > 0):
                        while($dept = $dept_filter->fetch_assoc()): ?>
                    <option value="<?php echo $dept['id']; ?>" <?php echo $department_filter == $dept['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($dept['code'] . ' - ' . $dept['name']); ?>
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
        </div>
        
        <div class="search-row">
            <div class="search-group">
                <label><i class="fas fa-layer-group"></i> Section</label>
                <select name="section_filter" id="section_filter">
                    <option value="">-- All Sections --</option>
                    <?php foreach ($sections_for_filter as $section): ?>
                    <option value="<?php echo $section['id']; ?>" <?php echo $section_filter == $section['id'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($section['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="search-group">
                <label><i class="fas fa-briefcase"></i> Position</label>
                <select name="position_filter">
                    <option value="">-- All Positions --</option>
                    <?php 
                    $pos_filter = $conn->query("SELECT DISTINCT position FROM employees WHERE position IS NOT NULL AND position != '' ORDER BY position");
                    if ($pos_filter && $pos_filter->num_rows > 0):
                        while($pos = $pos_filter->fetch_assoc()): ?>
                    <option value="<?php echo htmlspecialchars($pos['position']); ?>" <?php echo $position_filter == $pos['position'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($pos['position']); ?>
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="search-group">
                <label><i class="fas fa-flag-checkered"></i> Status</label>
                <select name="status_filter">
                    <option value="">-- All Status --</option>
                    <option value="Active" <?php echo $status_filter == 'Active' ? 'selected' : ''; ?>>Active</option>
                    <option value="Inactive" <?php echo $status_filter == 'Inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
            <div class="search-group">
                <label><i class="fas fa-calendar-alt"></i> Date Added (From)</label>
                <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
            </div>
            <div class="search-group">
                <label><i class="fas fa-calendar-alt"></i> Date Added (To)</label>
                <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
            </div>
        </div>
        
        <div class="search-row">
            <div class="search-actions" style="margin-left: auto;">
                <button type="submit" class="btn-search">
                    <i class="fas fa-search"></i> Search
                </button>
                <?php if ($search || $date_from || $date_to || $department_filter || $section_filter || $status_filter || $position_filter): ?>
                <a href="<?php echo SITE_URL; ?>/admin/employees.php" class="btn-clear">
                    <i class="fas fa-times"></i> Clear All
                </a>
                <?php endif; ?>
            </div>
        </div>
    </form>
</div>

<!-- Employees Table -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-user-tie"></i> Employees List</h2>
        <p>Showing <?php echo number_format($employees_result->num_rows); ?> of <?php echo number_format($total_rows); ?> employees</p>
    </div>
    
    <div class="employee-wrapper">
        <table class="employee-table">
            <thead>
                <tr>
                    <th>EMPLOYEE NAME</th>
                    <th>EMAIL</th>
                    <th>CONTACT</th>
                    <th>DEPARTMENT</th>
                    <th>SECTION</th>
                    <th>AREA/DIVISION</th>
                    <th>POSITION</th>
                    <th>LINKED USER</th>
                    <th>STATUS</th>
                    <th>DATE ADDED</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($employees_result && $employees_result->num_rows > 0): ?>
                    <?php while($emp = $employees_result->fetch_assoc()): ?>
                    <tr>
                        <td style="font-weight: 500;">
                            <?php 
                            $full_name = htmlspecialchars($emp['lastname'] . ', ' . $emp['firstname']);
                            echo $full_name;
                            if (!empty($emp['middlename'])):
                                echo '<br><small class="text-muted">' . htmlspecialchars($emp['middlename']) . '</small>';
                            endif;
                            ?>
                        </td>
                        <td><?php echo !empty($emp['email']) ? htmlspecialchars($emp['email']) : '<span class="text-muted">—</span>'; ?></td>
                        <td><?php echo !empty($emp['contact']) ? htmlspecialchars($emp['contact']) : '<span class="text-muted">—</span>'; ?></td>
                        <td><?php echo !empty($emp['department_name']) ? htmlspecialchars($emp['department_name']) : '<span class="text-muted">—</span>'; ?></td>
                        <td><?php echo !empty($emp['section_name']) ? htmlspecialchars($emp['section_name']) : '<span class="text-muted">—</span>'; ?></td>
                        <td>
                            <?php if (!empty($emp['area_name'])): ?>
                                <span class="badge badge-info">
                                    <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($emp['area_name']); ?>
                                </span>
                                <?php if (!empty($emp['area_code'])): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($emp['area_code']); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                         </td>
                        <td><?php echo !empty($emp['position']) ? htmlspecialchars($emp['position']) : '<span class="text-muted">—</span>'; ?></td>
                        <td>
                            <?php if ($emp['has_user_account'] && !empty($emp['username'])): ?>
                                <span class="badge badge-success"><?php echo htmlspecialchars($emp['username']); ?></span>
                                <?php if (!empty($emp['user_role'])): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($emp['user_role']); ?></small>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge badge-warning">Not Linked</span>
                            <?php endif; ?>
                         </td>
                        <td>
                            <?php if ($emp['status'] == 'Active'): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                         </td>
                        <td>
                            <small><?php echo isset($emp['date_added']) && $emp['date_added'] != '0000-00-00' ? date('Y-m-d', strtotime($emp['date_added'])) : '<span class="text-muted">—</span>'; ?></small>
                         </td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn view" onclick="viewEmployee(<?php echo $emp['id']; ?>)" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="action-btn edit" onclick="editEmployee(<?php echo $emp['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn delete" onclick="openDeleteEmployeeModal(<?php echo $emp['id']; ?>, '<?php echo htmlspecialchars(addslashes($emp['lastname'] . ', ' . $emp['firstname'])); ?>')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11" class="text-center" style="padding: 60px 20px;">
                            <i class="fas fa-users" style="font-size: 48px; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
                            <?php if ($search || $date_from || $date_to || $department_filter || $section_filter || $status_filter || $position_filter): ?>
                                <p style="color: var(--text-muted); margin-bottom: 20px;">No employees found matching your search criteria</p>
                                <a href="<?php echo SITE_URL; ?>/admin/employees.php" class="btn btn-primary">Clear All Filters</a>
                            <?php else: ?>
                                <p style="color: var(--text-muted); margin-bottom: 20px;">No employees found</p>
                                <button class="btn btn-primary" onclick="openEmployeeModal()">Add Your First Employee</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php
        $query_params = array_filter([
            'search' => $search,
            'date_from' => $date_from,
            'date_to' => $date_to,
            'department_filter' => $department_filter,
            'section_filter' => $section_filter,
            'status_filter' => $status_filter,
            'position_filter' => $position_filter
        ]);
        $query_string = http_build_query($query_params);
        ?>
        
        <?php if ($page > 1): ?>
            <a href="?page=1<?php echo $query_string ? '&' . $query_string : ''; ?>">&laquo; First</a>
            <a href="?page=<?php echo $page - 1; ?><?php echo $query_string ? '&' . $query_string : ''; ?>">&lsaquo; Previous</a>
        <?php else: ?>
            <span class="disabled">&laquo; First</span>
            <span class="disabled">&lsaquo; Previous</span>
        <?php endif; ?>
        
        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
            <?php if ($i == $page): ?>
                <span class="active"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?><?php echo $query_string ? '&' . $query_string : ''; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?><?php echo $query_string ? '&' . $query_string : ''; ?>">Next &rsaquo;</a>
            <a href="?page=<?php echo $total_pages; ?><?php echo $query_string ? '&' . $query_string : ''; ?>">Last &raquo;</a>
        <?php else: ?>
            <span class="disabled">Next &rsaquo;</span>
            <span class="disabled">Last &raquo;</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Delete Employee Confirmation Modal -->
<div id="deleteEmployeeModal" class="delete-modal-overlay">
    <div class="delete-modal-container">
        <div class="delete-modal-header">
            <h3><i class="fas fa-trash-alt"></i> Delete Employee</h3>
        </div>
        <div class="delete-modal-body">
            <input type="hidden" id="delete_employee_id">
            <div class="delete-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <p><strong>Are you absolutely sure?</strong></p>
                <p class="warning-text">This action cannot be undone.</p>
            </div>
            <div class="delete-item-details">
                <div class="detail-label">EMPLOYEE TO DELETE</div>
                <div class="detail-name" id="delete_employee_name">-</div>
                <div class="detail-extra">This will permanently remove the employee record.</div>
            </div>
        </div>
        <div class="delete-modal-footer">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeDeleteEmployeeModal()">Cancel</button>
            <a href="#" id="confirmDeleteEmployeeBtn" class="btn-modal btn-modal-danger"><i class="fas fa-trash-alt"></i> Delete Employee</a>
        </div>
    </div>
</div>

<!-- Add/Edit Employee Modal -->
<div id="employeeModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header-settings">
            <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Add Employee</h3>
            <span class="modal-close" onclick="closeEmployeeModal()">&times;</span>
        </div>
        <div class="modal-scroll-content">
            <form method="POST" action="" id="employeeForm">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="employee_id" value="">
                
                <!-- Link to Existing User Account -->
                <div class="form-section">
                    <h3><i class="fas fa-user-circle"></i> Link to User Account</h3>
                    <div class="form-group">
                        <label for="user_id">Select User (Optional)</label>
                        <select class="form-control" id="user_id" name="user_id" onchange="loadUserDetails()">
                            <option value="">-- Select User to Link --</option>
                            <?php 
                            $users_dropdown = $conn->query("
                                SELECT u.* 
                                FROM users u 
                                LEFT JOIN employees e ON u.id = e.user_id 
                                WHERE e.id IS NULL OR e.user_id IS NULL
                                ORDER BY u.lastname, u.firstname
                            ");
                            if ($users_dropdown && $users_dropdown->num_rows > 0): 
                                while($user = $users_dropdown->fetch_assoc()): ?>
                            <option value="<?php echo $user['id']; ?>">
                                <?php echo htmlspecialchars($user['lastname'] . ', ' . $user['firstname'] . ' (' . $user['username'] . ')'); ?>
                            </option>
                            <?php endwhile; endif; ?>
                        </select>
                        <small class="text-muted">Select a user to link to this employee</small>
                    </div>
                </div>
                
                <!-- Personal Information -->
                <div class="form-section">
                    <h3><i class="fas fa-user"></i> Personal Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lastname">Last Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="lastname" name="lastname" required>
                        </div>
                        <div class="form-group">
                            <label for="firstname">First Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="firstname" name="firstname" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="middlename">Middle Name</label>
                        <input type="text" class="form-control" id="middlename" name="middlename">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="form-group">
                            <label for="contact">Contact Number</label>
                            <input type="text" class="form-control" id="contact" name="contact">
                        </div>
                    </div>
                </div>
                
                <!-- Work Information -->
                <div class="form-section">
                    <h3><i class="fas fa-briefcase"></i> Work Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="division_id">Area / Division</label>
                            <select class="form-control" id="division_id" name="division_id" onchange="loadDepartmentsByDivision()">
                                <option value="">-- Select Division --</option>
                                <?php 
                                $divisions_list = $conn->query("SELECT id, code, name FROM areas ORDER BY code, name");
                                if ($divisions_list && $divisions_list->num_rows > 0):
                                    while($div = $divisions_list->fetch_assoc()): ?>
                                <option value="<?php echo $div['id']; ?>">
                                    [<?php echo htmlspecialchars($div['code']); ?>] <?php echo htmlspecialchars($div['name']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                            <small class="text-muted">Select division/area to filter departments</small>
                        </div>
                        <div class="form-group">
                            <label for="department_id">Department <span class="text-danger">*</span></label>
                            <select class="form-control" id="department_id" name="department_id" onchange="loadSectionsByDepartment()">
                                <option value="">-- Select Department --</option>
                                <?php 
                                $dept_dropdown = $conn->query("SELECT id, code, name FROM departments ORDER BY code, name");
                                if ($dept_dropdown && $dept_dropdown->num_rows > 0): 
                                    while($dept = $dept_dropdown->fetch_assoc()): ?>
                                <option value="<?php echo $dept['id']; ?>">
                                    <?php echo htmlspecialchars($dept['code']); ?> - <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="section_id">Section</label>
                        <select class="form-control" id="section_id" name="section_id">
                            <option value="">-- Select Department first --</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="position">Position</label>
                        <input type="text" class="form-control" id="position" name="position" placeholder="e.g., Manager, Staff, Supervisor">
                    </div>
                    <div class="form-group">
                        <label for="status">Employment Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer-buttons">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeEmployeeModal()">Cancel</button>
            <button type="submit" form="employeeForm" class="btn-modal" style="background-color: var(--accent); color: var(--text-primary);"><i class="fas fa-save"></i> Save Employee</button>
        </div>
    </div>
</div>

<!-- View Employee Modal -->
<div id="viewEmployeeModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 550px;">
        <div class="modal-header-settings">
            <h3><i class="fas fa-id-card"></i> Employee Details</h3>
            <span class="modal-close" onclick="closeViewEmployeeModal()">&times;</span>
        </div>
        <div class="modal-body-scroll" id="viewEmployeeContent">
            <div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
        <div class="modal-footer-buttons">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeViewEmployeeModal()">Close</button>
        </div>
    </div>
</div>

<script>
// Delete Employee Modal Functions
function openDeleteEmployeeModal(id, name) {
    document.getElementById('delete_employee_id').value = id;
    document.getElementById('delete_employee_name').innerText = name;
    document.getElementById('confirmDeleteEmployeeBtn').href = '?delete=' + id + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>';
    document.getElementById('deleteEmployeeModal').style.display = 'flex';
}

function closeDeleteEmployeeModal() {
    document.getElementById('deleteEmployeeModal').style.display = 'none';
}

// Load departments based on selected division
function loadDepartmentsByDivision() {
    let divisionId = document.getElementById('division_id').value;
    let departmentSelect = document.getElementById('department_id');
    let sectionSelect = document.getElementById('section_id');
    
    if (!divisionId) {
        // Reset to all departments
        fetch('<?php echo SITE_URL; ?>/admin/ajax_get_all_departments.php')
            .then(response => response.json())
            .then(data => {
                if (data.success && data.departments) {
                    let options = '<option value="">-- Select Department --</option>';
                    data.departments.forEach(dept => {
                        options += `<option value="${dept.id}">[${escapeHtml(dept.code)}] ${escapeHtml(dept.name)}</option>`;
                    });
                    departmentSelect.innerHTML = options;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                departmentSelect.innerHTML = '<option value="">-- Select Department --</option>';
            });
        sectionSelect.innerHTML = '<option value="">-- Select Department first --</option>';
        return;
    }
    
    departmentSelect.innerHTML = '<option value="">Loading departments...</option>';
    sectionSelect.innerHTML = '<option value="">-- Select Department first --</option>';
    
    fetch('?get_departments_by_division=' + divisionId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.departments && data.departments.length > 0) {
                let options = '<option value="">-- Select Department --</option>';
                data.departments.forEach(dept => {
                    options += `<option value="${dept.id}">[${escapeHtml(dept.code)}] ${escapeHtml(dept.name)}</option>`;
                });
                departmentSelect.innerHTML = options;
            } else {
                departmentSelect.innerHTML = '<option value="">-- No departments found for this division --</option>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            departmentSelect.innerHTML = '<option value="">-- Error loading departments --</option>';
        });
}

// Load sections based on selected department
function loadSectionsByDepartment() {
    let departmentId = document.getElementById('department_id').value;
    let sectionSelect = document.getElementById('section_id');
    
    if (!departmentId) {
        sectionSelect.innerHTML = '<option value="">-- First Select Department --</option>';
        return;
    }
    
    sectionSelect.innerHTML = '<option value="">Loading sections...</option>';
    
    fetch('?get_sections_by_department=' + departmentId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.sections && data.sections.length > 0) {
                let options = '<option value="">-- Select Section --</option>';
                data.sections.forEach(section => {
                    options += `<option value="${section.id}">${escapeHtml(section.name)}</option>`;
                });
                sectionSelect.innerHTML = options;
            } else {
                sectionSelect.innerHTML = '<option value="">-- No sections available for this department --</option>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            sectionSelect.innerHTML = '<option value="">-- Error loading sections --</option>';
        });
}

// Load user details when a user is selected
function loadUserDetails() {
    let userId = document.getElementById('user_id').value;
    
    if (userId && userId != '') {
        fetch('?get_user_details=' + userId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let user = data.user;
                    if (!document.getElementById('firstname').value) {
                        document.getElementById('firstname').value = user.firstname || '';
                    }
                    if (!document.getElementById('lastname').value) {
                        document.getElementById('lastname').value = user.lastname || '';
                    }
                    if (!document.getElementById('email').value) {
                        document.getElementById('email').value = user.email || '';
                    }
                }
            })
            .catch(error => console.error('Error:', error));
    }
}

function openEmployeeModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add Employee';
    document.getElementById('action').value = 'add';
    document.getElementById('employee_id').value = '';
    document.getElementById('employeeForm').reset();
    
    // Reset department and section selects
    let departmentSelect = document.getElementById('department_id');
    departmentSelect.innerHTML = '<option value="">-- Select Department --</option>';
    <?php 
    $dept_dropdown = $conn->query("SELECT id, code, name FROM departments ORDER BY code, name");
    if ($dept_dropdown && $dept_dropdown->num_rows > 0): 
        while($dept = $dept_dropdown->fetch_assoc()): ?>
    departmentSelect.innerHTML += `<option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['code']); ?> - <?php echo htmlspecialchars($dept['name']); ?></option>`;
    <?php endwhile; endif; ?>
    
    document.getElementById('section_id').innerHTML = '<option value="">-- Select Department first --</option>';
    document.getElementById('employeeModal').style.display = 'block';
}

function closeEmployeeModal() {
    document.getElementById('employeeModal').style.display = 'none';
}

function editEmployee(id) {
    fetch('?get_employee=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let emp = data.employee;
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit"></i> Edit Employee';
                document.getElementById('action').value = 'edit';
                document.getElementById('employee_id').value = emp.id;
                document.getElementById('lastname').value = emp.lastname;
                document.getElementById('firstname').value = emp.firstname;
                document.getElementById('middlename').value = emp.middlename || '';
                document.getElementById('email').value = emp.email || '';
                document.getElementById('contact').value = emp.contact || '';
                document.getElementById('position').value = emp.position || '';
                document.getElementById('status').value = emp.status || 'Active';
                document.getElementById('user_id').value = emp.user_id || '';
                
                // Set department
                if (emp.department_id) {
                    document.getElementById('department_id').value = emp.department_id;
                    loadSectionsByDepartment();
                    setTimeout(() => {
                        if (emp.section_id) {
                            document.getElementById('section_id').value = emp.section_id;
                        }
                    }, 500);
                }
                
                document.getElementById('employeeModal').style.display = 'block';
            }
        })
        .catch(error => console.error('Error:', error));
}

function viewEmployee(id) {
    fetch('?get_employee=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let emp = data.employee;
                let userInfo = '';
                let areaDisplay = '';
                
                if (emp.area_name) {
                    areaDisplay = `<div class="detail-item"><div class="detail-label">Area/Division</div><div class="detail-value"><span class="badge badge-info">${escapeHtml(emp.area_name)}</span>${emp.area_code ? '<br><small>' + escapeHtml(emp.area_code) + '</small>' : ''}</div></div>`;
                }
                
                if (emp.user_id) {
                    userInfo = `
                        <div class="detail-item"><div class="detail-label">Linked User</div><div class="detail-value">${escapeHtml(emp.user_fullname)}</div></div>
                        <div class="detail-item"><div class="detail-label">Username</div><div class="detail-value">${escapeHtml(emp.username)}</div></div>
                        <div class="detail-item"><div class="detail-label">User Role</div><div class="detail-value">${escapeHtml(emp.user_role)}</div></div>
                    `;
                } else {
                    userInfo = `<div class="detail-item"><div class="detail-label">Linked User</div><div class="detail-value"><span class="badge badge-warning">Not linked to any user account</span></div></div>`;
                }
                
                let departmentDisplay = emp.department_name || '—';
                let sectionDisplay = emp.section_name || '—';
                
                let content = `
                    <div style="text-align:center;margin-bottom:20px;">
                        <div style="width: 80px;height: 80px;background: var(--accent-light);border-radius: 50%;display: flex;align-items: center;justify-content: center;margin: 0 auto 15px;">
                            <i class="fas fa-user-tie" style="font-size: 40px; color: var(--primary);"></i>
                        </div>
                        <h3 style="margin:0;color: var(--text-primary);">${escapeHtml(emp.lastname)}, ${escapeHtml(emp.firstname)}</h3>
                        ${emp.middlename ? `<p style="margin:5px 0 0;color: var(--text-muted);">${escapeHtml(emp.middlename)}</p>` : ''}
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item"><div class="detail-label">Email</div><div class="detail-value">${escapeHtml(emp.email || '—')}</div></div>
                        <div class="detail-item"><div class="detail-label">Contact</div><div class="detail-value">${escapeHtml(emp.contact || '—')}</div></div>
                        <div class="detail-item"><div class="detail-label">Department</div><div class="detail-value">${escapeHtml(departmentDisplay)}</div></div>
                        ${areaDisplay}
                        <div class="detail-item"><div class="detail-label">Section</div><div class="detail-value">${escapeHtml(sectionDisplay)}</div></div>
                        <div class="detail-item"><div class="detail-label">Position</div><div class="detail-value">${escapeHtml(emp.position || '—')}</div></div>
                        <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value"><span class="badge ${emp.status == 'Active' ? 'badge-success' : 'badge-danger'}">${emp.status}</span></div></div>
                        ${userInfo}
                    </div>
                `;
                document.getElementById('viewEmployeeContent').innerHTML = content;
                document.getElementById('viewEmployeeModal').style.display = 'block';
            } else {
                document.getElementById('viewEmployeeContent').innerHTML = '<div class="alert alert-danger">Error loading employee details</div>';
                document.getElementById('viewEmployeeModal').style.display = 'block';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('viewEmployeeContent').innerHTML = '<div class="alert alert-danger">Error loading employee details</div>';
            document.getElementById('viewEmployeeModal').style.display = 'block';
        });
}

function closeViewEmployeeModal() {
    document.getElementById('viewEmployeeModal').style.display = 'none';
}

function escapeHtml(text) {
    if (!text) return '';
    let div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Close modal when clicking outside
window.onclick = function(event) {
    let employeeModal = document.getElementById('employeeModal');
    let viewEmployeeModal = document.getElementById('viewEmployeeModal');
    let deleteEmployeeModal = document.getElementById('deleteEmployeeModal');
    
    if (event.target == employeeModal) {
        closeEmployeeModal();
    }
    if (event.target == viewEmployeeModal) {
        closeViewEmployeeModal();
    }
    if (event.target == deleteEmployeeModal) {
        closeDeleteEmployeeModal();
    }
}

// Auto-hide alerts after 5 seconds
setTimeout(function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() {
                if (alert.style.display !== 'none') {
                    alert.style.display = 'none';
                }
            }, 300);
        }, 4700);
    });
}, 1000);
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>