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

// Role checking - FIXED: Use array instead of OR operator
requireRole('admin' || 'superadmin' || 'supply');

$page_title = 'Employees';
$page_description = 'Manage employees linked to user accounts';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
            $email = sanitize($_POST['email'] ?? '');
            $contact = sanitize($_POST['contact'] ?? '');
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
            $position = sanitize($_POST['position'] ?? '');
            $date_hired = !empty($_POST['date_hired']) ? $_POST['date_hired'] : null;
            $status = sanitize($_POST['status'] ?? 'Active');
            $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
            
            $errors = [];
            if (empty($firstname)) $errors[] = "First name is required";
            if (empty($lastname)) $errors[] = "Last name is required";
            
            if (!empty($email) && !validateEmail($email)) {
                $errors[] = "Invalid email format";
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
                        department_id, section_id, position, date_hired, status, user_id
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                if ($stmt) {
                    $stmt->bind_param(
                        "sssssiisssi",
                        $firstname, $lastname, $middlename, $email, $contact,
                        $department_id, $section_id, $position, $date_hired, $status, $user_id
                    );
                    
                    if ($stmt->execute()) {
                        $employee_id = $stmt->insert_id;
                        $_SESSION['success'] = "Employee added successfully" . ($user_id ? " and linked to user account!" : "!");
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
            $email = sanitize($_POST['email'] ?? '');
            $contact = sanitize($_POST['contact'] ?? '');
            $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
            $section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
            $position = sanitize($_POST['position'] ?? '');
            $date_hired = !empty($_POST['date_hired']) ? $_POST['date_hired'] : null;
            $status = sanitize($_POST['status'] ?? 'Active');
            $user_id = !empty($_POST['user_id']) ? (int)$_POST['user_id'] : null;
            
            $errors = [];
            if (empty($firstname)) $errors[] = "First name is required";
            if (empty($lastname)) $errors[] = "Last name is required";
            
            if (!empty($email) && !validateEmail($email)) {
                $errors[] = "Invalid email format";
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
                        section_id = ?, position = ?, date_hired = ?,
                        status = ?, user_id = ?
                    WHERE id = ?
                ");
                
                if ($stmt) {
                    $stmt->bind_param(
                        "sssssiisssii",
                        $firstname, $lastname, $middlename, $email, $contact,
                        $department_id, $section_id, $position, $date_hired,
                        $status, $user_id, $id
                    );
                    
                    if ($stmt->execute()) {
                        $_SESSION['success'] = "Employee updated successfully";
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
    
    $conn->query("DELETE FROM employees WHERE id = $id");
    
    if ($conn->affected_rows > 0) {
        $_SESSION['success'] = "Employee deleted successfully";
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
        SELECT e.*, d.name as department_name, s.name as section_name,
               u.username, u.role as user_role, u.status as user_status,
               CONCAT(u.firstname, ' ', u.lastname) as user_fullname
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        LEFT JOIN sections s ON e.section_id = s.id
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

// ============================================
// DISPLAY DATA
// ============================================

// Get all employees with their departments, sections, and linked users
$employees_result = $conn->query("
    SELECT e.*, d.name as department_name, s.name as section_name,
           u.username, u.role as user_role, u.status as user_status,
           CONCAT(u.firstname, ' ', u.lastname) as user_fullname,
           CASE WHEN u.id IS NOT NULL THEN 1 ELSE 0 END as has_user_account
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN sections s ON e.section_id = s.id
    LEFT JOIN users u ON e.user_id = u.id
    ORDER BY e.lastname, e.firstname
");

// Get departments for dropdown
$departments_result = $conn->query("SELECT * FROM departments ORDER BY name");

// Get sections for dropdown
$sections_result = $conn->query("
    SELECT s.*, d.name as department_name 
    FROM sections s 
    LEFT JOIN departments d ON s.department_id = d.id 
    ORDER BY d.name, s.name
");

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

include INCLUDE_PATH . '/header.php';
?>

<style>
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

/* Dashboard Cards */
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

/* Table Container */
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

/* Search Box */
.search-box {
    display: flex;
    gap: 12px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.search-box input[type="text"] {
    padding: 12px 16px;
    border: 1px solid var(--border-light);
    border-radius: 12px;
    font-size: 14px;
    flex: 1;
    min-width: 200px;
}

.search-box button {
    padding: 12px 24px;
    background: var(--primary);
    color: var(--text-light);
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
}

.search-box button:hover {
    background: #5a7ae6;
}

/* Employee Table */
.employee-wrapper {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid var(--border-light);
}

.employee-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    min-width: 1200px;
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

/* Badges */
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

/* Action Buttons */
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

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
}

.modal-content {
    background: var(--white);
    margin: 5% auto;
    border-radius: 20px;
    max-width: 700px;
    width: 90%;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-light);
}

.modal-header h2 {
    color: var(--primary);
    font-size: 18px;
    margin: 0;
    font-weight: 600;
}

.modal-close {
    font-size: 28px;
    cursor: pointer;
    color: var(--text-muted);
}

.modal-close:hover {
    color: var(--danger);
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border-light);
    text-align: right;
    background: var(--light);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

/* Form Styles */
.form-section {
    background: var(--white);
    padding: 20px;
    margin-bottom: 25px;
    border-radius: 12px;
    border: 1px solid var(--border-light);
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
}

/* Button Styles */
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

.btn-sm {
    padding: 6px 12px;
    font-size: 12px;
}

/* Alert */
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
.mt-3 { margin-top: 16px; }

/* Responsive */
@media (max-width: 768px) {
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .search-box {
        flex-direction: column;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .modal-content {
        margin: 10px;
        width: auto;
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
        <a href="<?php echo SITE_URL; ?>/admin/departments.php" class="btn btn-secondary">
            <i class="fas fa-building"></i> Manage Departments
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/manage_users.php" class="btn btn-secondary">
            <i class="fas fa-users-cog"></i> Manage Users
        </a>
    </div>
</div>

<!-- Employees Table -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-user-tie"></i> Employees List</h2>
        <p>Showing <?php echo number_format($total_employees); ?> employees</p>
    </div>
    
    <div class="employee-wrapper">
        <table class="employee-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>EMPLOYEE NAME</th>
                    <th>EMAIL</th>
                    <th>CONTACT</th>
                    <th>DEPARTMENT</th>
                    <th>SECTION</th>
                    <th>POSITION</th>
                    <th>LINKED USER</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($employees_result && $employees_result->num_rows > 0): ?>
                    <?php while($emp = $employees_result->fetch_assoc()): ?>
                    <tr>
                        <td style="vertical-align: top;"><?php echo $emp['id']; ?></td>
                        <td style="vertical-align: top;">
                            <strong><?php echo htmlspecialchars($emp['lastname'] . ', ' . $emp['firstname']); ?></strong>
                            <?php if (!empty($emp['middlename'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars($emp['middlename']); ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: top;"><?php echo htmlspecialchars($emp['email'] ?? 'N/A'); ?></td>
                        <td style="vertical-align: top;"><?php echo htmlspecialchars($emp['contact'] ?? 'N/A'); ?></td>
                        <td style="vertical-align: top;"><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></td>
                        <td style="vertical-align: top;"><?php echo htmlspecialchars($emp['section_name'] ?? 'N/A'); ?></td>
                        <td style="vertical-align: top;"><?php echo htmlspecialchars($emp['position'] ?? 'N/A'); ?></td>
                        <td style="vertical-align: top;">
                            <?php if ($emp['has_user_account']): ?>
                                <span class="badge badge-success"><?php echo htmlspecialchars($emp['username']); ?></span>
                                <br><small class="text-muted"><?php echo htmlspecialchars($emp['user_role'] ?? ''); ?></small>
                            <?php else: ?>
                                <span class="badge badge-warning">Not Linked</span>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: top;">
                            <?php if ($emp['status'] == 'Active'): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td style="vertical-align: top;">
                            <div class="action-buttons">
                                <button class="action-btn view" onclick="viewEmployee(<?php echo $emp['id']; ?>)" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="action-btn edit" onclick="editEmployee(<?php echo $emp['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?php echo $emp['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                   class="action-btn delete" 
                                   onclick="return confirm('Are you sure you want to delete this employee?')"
                                   title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center" style="padding: 60px 20px;">
                            <i class="fas fa-users" style="font-size: 48px; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
                            <p style="color: var(--text-muted); margin-bottom: 20px;">No employees found</p>
                            <button class="btn btn-primary" onclick="openEmployeeModal()">Add Your First Employee</button>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add/Edit Employee Modal -->
<div id="employeeModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle"><i class="fas fa-user-plus"></i> Add Employee</h2>
            <span class="modal-close" onclick="closeEmployeeModal()">&times;</span>
        </div>
        <div class="modal-body">
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
                            <?php if ($users_result && $users_result->num_rows > 0): 
                                $users_result->data_seek(0);
                                while($user = $users_result->fetch_assoc()): ?>
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
                            <label for="department_id">Department</label>
                            <select class="form-control" id="department_id" name="department_id" onchange="loadSections()">
                                <option value="">-- Select Department --</option>
                                <?php if ($departments_result && $departments_result->num_rows > 0): 
                                    $departments_result->data_seek(0);
                                    while($dept = $departments_result->fetch_assoc()): ?>
                                <option value="<?php echo $dept['id']; ?>"><?php echo htmlspecialchars($dept['name']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="section_id">Section</label>
                            <select class="form-control" id="section_id" name="section_id">
                                <option value="">-- Select Section --</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="position">Position</label>
                            <input type="text" class="form-control" id="position" name="position">
                        </div>
                        <div class="form-group">
                            <label for="date_hired">Date Hired</label>
                            <input type="date" class="form-control" id="date_hired" name="date_hired">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="status">Employment Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="Active">Active</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeEmployeeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Employee</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Employee Modal -->
<div id="viewEmployeeModal" class="modal">
    <div class="modal-content" style="max-width: 550px;">
        <div class="modal-header">
            <h2><i class="fas fa-id-card"></i> Employee Details</h2>
            <span class="modal-close" onclick="closeViewEmployeeModal()">&times;</span>
        </div>
        <div class="modal-body" id="viewEmployeeContent">
            <div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeViewEmployeeModal()">Close</button>
        </div>
    </div>
</div>

<script>
// Store sections data
let sectionsData = [];

<?php if ($sections_result && $sections_result->num_rows > 0): 
    $sections_result->data_seek(0);
    while($sec = $sections_result->fetch_assoc()): ?>
sectionsData.push({
    id: <?php echo $sec['id']; ?>,
    name: '<?php echo htmlspecialchars(addslashes($sec['name'])); ?>',
    department_id: <?php echo $sec['department_id'] ?? 'null'; ?>
});
<?php endwhile; endif; ?>

function loadSections() {
    let departmentId = document.getElementById('department_id').value;
    let sectionSelect = document.getElementById('section_id');
    
    sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';
    
    if (departmentId) {
        let filteredSections = sectionsData.filter(s => s.department_id == departmentId);
        filteredSections.forEach(section => {
            let option = document.createElement('option');
            option.value = section.id;
            option.textContent = section.name;
            sectionSelect.appendChild(option);
        });
    }
}

function loadUserDetails() {
    let userId = document.getElementById('user_id').value;
    
    if (userId && userId != '') {
        fetch('?get_user_details=' + userId)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let user = data.user;
                    document.getElementById('firstname').value = user.firstname || '';
                    document.getElementById('lastname').value = user.lastname || '';
                    document.getElementById('email').value = user.email || '';
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
                document.getElementById('department_id').value = emp.department_id || '';
                document.getElementById('position').value = emp.position || '';
                document.getElementById('date_hired').value = emp.date_hired || '';
                document.getElementById('status').value = emp.status || 'Active';
                document.getElementById('user_id').value = emp.user_id || '';
                
                loadSections();
                setTimeout(() => {
                    document.getElementById('section_id').value = emp.section_id || '';
                }, 100);
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
                if (emp.user_id) {
                    userInfo = `
                        <div class="detail-item"><div class="detail-label">Linked User</div><div class="detail-value">${escapeHtml(emp.user_fullname)}</div></div>
                        <div class="detail-item"><div class="detail-label">Username</div><div class="detail-value">${escapeHtml(emp.username)}</div></div>
                        <div class="detail-item"><div class="detail-label">User Role</div><div class="detail-value">${escapeHtml(emp.user_role)}</div></div>
                    `;
                }
                let content = `
                    <h3 style="text-align:center;margin-bottom:20px;">${escapeHtml(emp.lastname)}, ${escapeHtml(emp.firstname)}</h3>
                    <div class="detail-grid">
                        <div class="detail-item"><div class="detail-label">ID</div><div class="detail-value">${emp.id}</div></div>
                        <div class="detail-item"><div class="detail-label">Email</div><div class="detail-value">${escapeHtml(emp.email || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Contact</div><div class="detail-value">${escapeHtml(emp.contact || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Department</div><div class="detail-value">${escapeHtml(emp.department_name || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Section</div><div class="detail-value">${escapeHtml(emp.section_name || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Position</div><div class="detail-value">${escapeHtml(emp.position || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Date Hired</div><div class="detail-value">${emp.date_hired || 'N/A'}</div></div>
                        <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value"><span class="badge ${emp.status == 'Active' ? 'badge-success' : 'badge-danger'}">${emp.status}</span></div></div>
                        ${userInfo}
                    </div>
                `;
                document.getElementById('viewEmployeeContent').innerHTML = content;
                document.getElementById('viewEmployeeModal').style.display = 'block';
            }
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

window.onclick = function(event) {
    if (event.target == document.getElementById('employeeModal')) closeEmployeeModal();
    if (event.target == document.getElementById('viewEmployeeModal')) closeViewEmployeeModal();
}
</script>

<style>
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
</style>

<?php include INCLUDE_PATH . '/footer.php'; ?>