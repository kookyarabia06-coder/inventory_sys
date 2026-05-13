<?php
/**
 * Locations Page (Admin)
 * Manage buildings, departments, and sections
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

requireRole('admin' || 'superadmin' || 'supply');

$page_title = 'Locations';
$page_description = 'Manage buildings, departments, and sections';

// Function to get next department code
function getNextDepartmentCode($conn) {
    $stmt = $conn->prepare("SELECT MAX(CAST(code AS UNSIGNED)) as max_code FROM departments WHERE code IS NOT NULL AND code != ''");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    $next_code = ($row['max_code'] ?? 0) + 1;
    return str_pad($next_code, 3, '0', STR_PAD_LEFT);
}

// Function to check if department code exists
function departmentCodeExists($conn, $code, $exclude_id = null) {
    $sql = "SELECT id FROM departments WHERE code = ?";
    if ($exclude_id) {
        $sql .= " AND id != ?";
    }
    $stmt = $conn->prepare($sql);
    if ($exclude_id) {
        $stmt->bind_param("si", $code, $exclude_id);
    } else {
        $stmt->bind_param("s", $code);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    return $exists;
}

// Handle Building Actions
if (isset($_POST['building_action'])) {
    if ($_POST['building_action'] == 'add') {
        $name = sanitize($_POST['name']);
        $floors = intval($_POST['floors']);
        
        $stmt = $conn->prepare("INSERT INTO buildings (name, floor) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $floors);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Building added successfully";
            logActivity('Add Building', 0, "Added building: $name");
        } else {
            $_SESSION['error'] = "Error adding building: " . $conn->error;
        }
        $stmt->close();
        header('Location: ' . SITE_URL . '/admin/locations.php');
        exit();
    }
    
    elseif ($_POST['building_action'] == 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = sanitize($_POST['name']);
        $floors = intval($_POST['floors']);
        
        $stmt = $conn->prepare("UPDATE buildings SET name = ?, floor = ? WHERE id = ?");
        $stmt->bind_param("sii", $name, $floors, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Building updated successfully";
            logActivity('Edit Building', $id, "Updated building: $name");
        } else {
            $_SESSION['error'] = "Error updating building: " . $conn->error;
        }
        $stmt->close();
        header('Location: ' . SITE_URL . '/admin/locations.php');
        exit();
    }
}

// Handle Department Actions
if (isset($_POST['department_action'])) {
    if ($_POST['department_action'] == 'add') {
        $name = sanitize($_POST['name']);
        $building_id = !empty($_POST['building_id']) ? (int)$_POST['building_id'] : null;
        $code = getNextDepartmentCode($conn);
        
        $stmt = $conn->prepare("INSERT INTO departments (code, name, building_id) VALUES (?, ?, ?)");
        $stmt->bind_param("ssi", $code, $name, $building_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Department added successfully. Code: $code";
            logActivity('Add Department', 0, "Added department: $name (Code: $code)");
        } else {
            $_SESSION['error'] = "Error adding department: " . $conn->error;
        }
        $stmt->close();
        header('Location: ' . SITE_URL . '/admin/locations.php');
        exit();
    }
    
    elseif ($_POST['department_action'] == 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = sanitize($_POST['name']);
        $building_id = !empty($_POST['building_id']) ? (int)$_POST['building_id'] : null;
        $code = sanitize($_POST['code']);
        
        if (empty($code)) {
            $_SESSION['error'] = "Department code cannot be empty";
            header('Location: ' . SITE_URL . '/admin/locations.php');
            exit();
        }
        
        if (departmentCodeExists($conn, $code, $id)) {
            $_SESSION['error'] = "Department code '$code' already exists. Please use a different code.";
            header('Location: ' . SITE_URL . '/admin/locations.php');
            exit();
        }
        
        $stmt = $conn->prepare("UPDATE departments SET code = ?, name = ?, building_id = ? WHERE id = ?");
        $stmt->bind_param("ssii", $code, $name, $building_id, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Department updated successfully. Code: $code";
            logActivity('Edit Department', $id, "Updated department: $name (Code: $code)");
        } else {
            $_SESSION['error'] = "Error updating department: " . $conn->error;
        }
        $stmt->close();
        header('Location: ' . SITE_URL . '/admin/locations.php');
        exit();
    }
}

// Handle Section Actions
if (isset($_POST['section_action'])) {
    if ($_POST['section_action'] == 'add') {
        $name = sanitize($_POST['name']);
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        
        $stmt = $conn->prepare("INSERT INTO sections (name, department_id) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $department_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Section added successfully";
            logActivity('Add Section', 0, "Added section: $name");
        } else {
            $_SESSION['error'] = "Error adding section: " . $conn->error;
        }
        $stmt->close();
        header('Location: ' . SITE_URL . '/admin/locations.php');
        exit();
    }
    
    elseif ($_POST['section_action'] == 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = sanitize($_POST['name']);
        $department_id = !empty($_POST['department_id']) ? (int)$_POST['department_id'] : null;
        
        $stmt = $conn->prepare("UPDATE sections SET name = ?, department_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $name, $department_id, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Section updated successfully";
            logActivity('Edit Section', $id, "Updated section: $name");
        } else {
            $_SESSION['error'] = "Error updating section: " . $conn->error;
        }
        $stmt->close();
        header('Location: ' . SITE_URL . '/admin/locations.php');
        exit();
    }
}

// Handle Delete Actions
if (isset($_GET['delete'])) {
    $type = $_GET['type'] ?? '';
    $id = (int)$_GET['delete'];
    
    if ($type == 'building') {
        $check = $conn->query("SELECT id FROM departments WHERE building_id = $id");
        if ($check && $check->num_rows > 0) {
            $_SESSION['error'] = "Cannot delete building with existing departments";
        } else {
            $conn->query("DELETE FROM buildings WHERE id = $id");
            if ($conn->affected_rows > 0) {
                logActivity('Delete Building', $id, "Deleted building ID: $id");
                $_SESSION['success'] = "Building deleted successfully";
            }
        }
    }
    
    elseif ($type == 'department') {
        $check = $conn->query("SELECT id FROM sections WHERE department_id = $id");
        if ($check && $check->num_rows > 0) {
            $_SESSION['error'] = "Cannot delete department with existing sections";
        } else {
            $conn->query("DELETE FROM departments WHERE id = $id");
            if ($conn->affected_rows > 0) {
                logActivity('Delete Department', $id, "Deleted department ID: $id");
                $_SESSION['success'] = "Department deleted successfully";
            }
        }
    }
    
    elseif ($type == 'section') {
        $check = $conn->query("SELECT id FROM inventory WHERE section_id = $id LIMIT 1");
        if ($check && $check->num_rows > 0) {
            $_SESSION['error'] = "Cannot delete section that is used in inventory";
        } else {
            $conn->query("DELETE FROM sections WHERE id = $id");
            if ($conn->affected_rows > 0) {
                logActivity('Delete Section', $id, "Deleted section ID: $id");
                $_SESSION['success'] = "Section deleted successfully";
            }
        }
    }
    
    header('Location: ' . SITE_URL . '/admin/locations.php');
    exit();
}

// Refresh data after potential deletions
$buildings = $conn->query("SELECT * FROM buildings ORDER BY name");
$departments = $conn->query("
    SELECT d.*, b.name as building_name 
    FROM departments d
    LEFT JOIN buildings b ON d.building_id = b.id
    ORDER BY d.code ASC, d.name
");
$sections = $conn->query("
    SELECT s.*, d.name as department_name, d.code as department_code
    FROM sections s
    LEFT JOIN departments d ON s.department_id = d.id
    ORDER BY d.code ASC, s.name
");

include INCLUDE_PATH . '/header.php';
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
    --warning: #F59E0B;
}

body {
    background-color: var(--light);
    color: var(--text-primary);
}

/* Stats Grid - Vertical Layout */
.stats-grid {
    display: flex;
    flex-direction: column;
    gap: 25px;
    margin-bottom: 30px;
}

/* Card Style */
.stat-chart {
    background: var(--white);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
    border-left: 4px solid var(--primary);
    transition: transform 0.2s, box-shadow 0.2s;
    width: 100%;
    overflow-x: auto;
}

.stat-chart:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(107, 140, 255, 0.15);
}

/* Table Header */
.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-light);
    flex-wrap: wrap;
    gap: 10px;
}

.table-header h3 {
    color: var(--primary);
    font-size: 18px;
    margin: 0;
}

.table-header h3 i {
    color: var(--accent);
    margin-right: 10px;
}

/* Table Styles */
.table-wrapper {
    overflow-x: auto;
    width: 100%;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 400px;
}

th {
    text-align: left;
    padding: 12px 15px;
    background: linear-gradient(to right, var(--light), var(--white));
    color: var(--primary);
    font-weight: 600;
    border-bottom: 2px solid var(--accent-light);
    font-size: 13px;
}

td {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
    font-size: 13px;
    vertical-align: middle;
}

tr:hover td {
    background-color: var(--light);
}

/* Code Badge */
.code-badge {
    display: inline-block;
    background: var(--primary);
    color: white;
    padding: 2px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    margin-right: 8px;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    color: var(--text-light);
    text-decoration: none;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.action-btn.edit {
    background-color: var(--secondary);
}

.action-btn.edit:hover {
    background-color: #7a9fe6;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(143, 181, 255, 0.3);
}

.action-btn.delete {
    background-color: var(--danger);
}

.action-btn.delete:hover {
    background-color: #e53935;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(244, 67, 54, 0.3);
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
    padding: 25px;
    border-radius: 12px;
    width: 500px;
    max-width: 90%;
    box-shadow: 0 10px 30px rgba(107, 140, 255, 0.2);
    animation: modalSlideIn 0.3s;
}

/* Custom Delete Confirmation Modal */
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
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-light);
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
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid var(--border-light);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Form Styles */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-primary);
    font-size: 14px;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 14px;
    color: var(--text-primary);
    transition: all 0.3s;
    background-color: var(--white);
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.form-control[readonly] {
    background-color: var(--light);
    cursor: not-allowed;
}

select.form-control {
    cursor: pointer;
    background-color: var(--white);
}

.text-danger {
    color: var(--danger) !important;
}

.text-muted {
    color: var(--text-muted) !important;
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 13px;
}

.btn-primary {
    background-color: var(--accent);
    color: var(--text-primary);
}

.btn-primary:hover {
    background-color: #e69eb0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(248, 176, 192, 0.3);
}

.btn-secondary {
    background-color: var(--secondary);
    color: var(--text-light);
}

.btn-secondary:hover {
    background-color: #7a9fe6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(143, 181, 255, 0.3);
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

.btn-modal-danger {
    background-color: var(--danger);
    color: var(--text-light);
}

.btn-modal-danger:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

/* Alert Styles */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-left: 4px solid transparent;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.alert-success {
    background-color: var(--success-light);
    color: var(--success);
    border-left-color: var(--success);
}

.alert-danger {
    background-color: #ffebee;
    color: var(--danger);
    border-left-color: var(--danger);
}

.text-center {
    text-align: center;
    color: var(--text-muted);
    padding: 40px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .modal-container {
        margin: 20% auto;
        width: 95%;
    }
    
    .delete-modal-container {
        width: 95%;
    }
    
    .action-buttons {
        flex-wrap: wrap;
    }
    
    th, td {
        padding: 8px 10px;
        font-size: 12px;
    }
    
    .table-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .code-badge {
        font-size: 10px;
        padding: 2px 6px;
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

@media (max-width: 480px) {
    .stat-chart {
        padding: 15px;
    }
    
    .btn-sm {
        padding: 4px 10px;
        font-size: 12px;
    }
    
    .table-header h3 {
        font-size: 16px;
    }
}

/* Scrollbar Styling */
.delete-modal-body::-webkit-scrollbar {
    width: 6px;
}

.delete-modal-body::-webkit-scrollbar-track {
    background: var(--light);
    border-radius: 3px;
}

.delete-modal-body::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

.delete-modal-body::-webkit-scrollbar-thumb:hover {
    background: var(--secondary);
}
</style>

<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php 
        echo $_SESSION['success'];
        unset($_SESSION['success']);
        ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php 
        echo $_SESSION['error'];
        unset($_SESSION['error']);
        ?>
    </div>
<?php endif; ?>

<div class="stats-grid">
    <!-- Buildings Section -->
    <div class="stat-chart">
        <div class="table-header">
            <h3><i class="fas fa-building"></i> Buildings</h3>
            <button class="btn btn-sm btn-primary" onclick="openBuildingModal()">
                <i class="fas fa-plus"></i> Add Building
            </button>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width: 50%">Name</th>
                        <th style="width: 25%">Floors</th>
                        <th style="width: 25%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($buildings && $buildings->num_rows > 0): ?>
                        <?php while($b = $buildings->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($b['name']); ?></strong></span>
                            <td><?php echo $b['floor']; ?> floor(s)</span>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn edit" onclick="editBuilding(<?php echo $b['id']; ?>, '<?php echo htmlspecialchars(addslashes($b['name'])); ?>', <?php echo $b['floor']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" onclick="openDeleteBuildingModal(<?php echo $b['id']; ?>, '<?php echo htmlspecialchars(addslashes($b['name'])); ?>')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </span>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center">
                                <i class="fas fa-building" style="font-size: 48px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                No buildings found
                                <br>
                                <button class="btn btn-primary mt-3" onclick="openBuildingModal()" style="margin-top: 10px;">Add Your First Building</button>
                            </span>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Departments Section -->
    <div class="stat-chart">
        <div class="table-header">
            <h3><i class="fas fa-sitemap"></i> Departments</h3>
            <button class="btn btn-sm btn-primary" onclick="openDepartmentModal()">
                <i class="fas fa-plus"></i> Add Department
            </button>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width: 20%">Code</th>
                        <th style="width: 40%">Name</th>
                        <th style="width: 25%">Building</th>
                        <th style="width: 15%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($departments && $departments->num_rows > 0): ?>
                        <?php while($d = $departments->fetch_assoc()): ?>
                        <tr>
                            <td><span class="code-badge"><?php echo htmlspecialchars($d['code']); ?></span></span>
                            <td><strong><?php echo htmlspecialchars($d['name']); ?></strong></span>
                            <td><?php echo htmlspecialchars($d['building_name'] ?? '—'); ?></span>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn edit" onclick="editDepartment(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars(addslashes($d['name'])); ?>', '<?php echo htmlspecialchars($d['code']); ?>', <?php echo $d['building_id'] ?? 'null'; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" onclick="openDeleteDepartmentModal(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars(addslashes($d['name'])); ?>')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </span>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center">
                                <i class="fas fa-sitemap" style="font-size: 48px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                No departments found
                                <br>
                                <button class="btn btn-primary mt-3" onclick="openDepartmentModal()" style="margin-top: 10px;">Add Your First Department</button>
                            </span>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Sections Section -->
    <div class="stat-chart">
        <div class="table-header">
            <h3><i class="fas fa-layer-group"></i> Sections</h3>
            <button class="btn btn-sm btn-primary" onclick="openSectionModal()">
                <i class="fas fa-plus"></i> Add Section
            </button>
        </div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th style="width: 35%">Name</th>
                        <th style="width: 45%">Department</th>
                        <th style="width: 20%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sections && $sections->num_rows > 0): ?>
                        <?php while($s = $sections->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?php echo htmlspecialchars($s['name']); ?></strong></span>
                            <td>
                                <?php echo htmlspecialchars($s['department_name'] ?? '—'); ?>
                                <?php if ($s['department_code']): ?>
                                    <span class="code-badge"><?php echo htmlspecialchars($s['department_code']); ?></span>
                                <?php endif; ?>
                            </span>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn edit" onclick="editSection(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['name'])); ?>', <?php echo $s['department_id'] ?? 'null'; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="action-btn delete" onclick="openDeleteSectionModal(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['name'])); ?>')" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </span>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center">
                                <i class="fas fa-layer-group" style="font-size: 48px; color: #ccc; margin-bottom: 10px; display: block;"></i>
                                No sections found
                                <br>
                                <button class="btn btn-primary mt-3" onclick="openSectionModal()" style="margin-top: 10px;">Add Your First Section</button>
                            </span>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Delete Building Confirmation Modal -->
<div id="deleteBuildingModal" class="delete-modal-overlay">
    <div class="delete-modal-container">
        <div class="delete-modal-header">
            <h3><i class="fas fa-trash-alt"></i> Delete Building</h3>
        </div>
        <div class="delete-modal-body">
            <input type="hidden" id="delete_building_id">
            <div class="delete-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <p><strong>Are you absolutely sure?</strong></p>
                <p class="warning-text">This action cannot be undone.</p>
            </div>
            <div class="delete-item-details">
                <div class="detail-label">BUILDING TO DELETE</div>
                <div class="detail-name" id="delete_building_name">-</div>
                <div class="detail-extra">This will permanently remove the building record.</div>
            </div>
        </div>
        <div class="delete-modal-footer">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeDeleteBuildingModal()">Cancel</button>
            <a href="#" id="confirmDeleteBuildingBtn" class="btn-modal btn-modal-danger"><i class="fas fa-trash-alt"></i> Delete Building</a>
        </div>
    </div>
</div>

<!-- Delete Department Confirmation Modal -->
<div id="deleteDepartmentModal" class="delete-modal-overlay">
    <div class="delete-modal-container">
        <div class="delete-modal-header">
            <h3><i class="fas fa-trash-alt"></i> Delete Department</h3>
        </div>
        <div class="delete-modal-body">
            <input type="hidden" id="delete_department_id">
            <div class="delete-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <p><strong>Are you absolutely sure?</strong></p>
                <p class="warning-text">This action cannot be undone.</p>
            </div>
            <div class="delete-item-details">
                <div class="detail-label">DEPARTMENT TO DELETE</div>
                <div class="detail-name" id="delete_department_name">-</div>
                <div class="detail-extra">This will permanently remove the department record.</div>
            </div>
        </div>
        <div class="delete-modal-footer">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeDeleteDepartmentModal()">Cancel</button>
            <a href="#" id="confirmDeleteDepartmentBtn" class="btn-modal btn-modal-danger"><i class="fas fa-trash-alt"></i> Delete Department</a>
        </div>
    </div>
</div>

<!-- Delete Section Confirmation Modal -->
<div id="deleteSectionModal" class="delete-modal-overlay">
    <div class="delete-modal-container">
        <div class="delete-modal-header">
            <h3><i class="fas fa-trash-alt"></i> Delete Section</h3>
        </div>
        <div class="delete-modal-body">
            <input type="hidden" id="delete_section_id">
            <div class="delete-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <p><strong>Are you absolutely sure?</strong></p>
                <p class="warning-text">This action cannot be undone.</p>
            </div>
            <div class="delete-item-details">
                <div class="detail-label">SECTION TO DELETE</div>
                <div class="detail-name" id="delete_section_name">-</div>
                <div class="detail-extra">This will permanently remove the section record.</div>
            </div>
        </div>
        <div class="delete-modal-footer">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeDeleteSectionModal()">Cancel</button>
            <a href="#" id="confirmDeleteSectionBtn" class="btn-modal btn-modal-danger"><i class="fas fa-trash-alt"></i> Delete Section</a>
        </div>
    </div>
</div>

<!-- Building Modal -->
<div id="buildingModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header-settings">
            <h3 id="buildingModalTitle"><i class="fas fa-building"></i> Add Building</h3>
            <span class="modal-close" onclick="closeBuildingModal()">&times;</span>
        </div>
        <div style="padding: 0 25px 25px 25px;">
            <form method="POST" action="" id="buildingForm">
                <input type="hidden" name="building_action" id="building_action" value="add">
                <input type="hidden" name="id" id="building_id" value="">
                
                <div class="form-group">
                    <label for="building_name">Building Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="building_name" name="name" required maxlength="255" placeholder="Enter building name">
                </div>
                
                <div class="form-group">
                    <label for="building_floors">Number of Floors</label>
                    <input type="number" class="form-control" id="building_floors" name="floors" value="1" min="1" max="100">
                </div>
                
                <div class="modal-footer-buttons">
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="closeBuildingModal()">Cancel</button>
                    <button type="submit" class="btn-modal" style="background-color: var(--accent); color: var(--text-primary);">Save Building</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Department Modal -->
<div id="departmentModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header-settings">
            <h3 id="departmentModalTitle"><i class="fas fa-sitemap"></i> Add Department</h3>
            <span class="modal-close" onclick="closeDepartmentModal()">&times;</span>
        </div>
        <div style="padding: 0 25px 25px 25px;">
            <form method="POST" action="" id="departmentForm">
                <input type="hidden" name="department_action" id="department_action" value="add">
                <input type="hidden" name="id" id="department_id" value="">
                
                <div class="form-group">
                    <label for="department_code">Department Code</label>
                    <input type="text" class="form-control" id="department_code" name="code" readonly>
                    <small class="text-muted">Code is auto-generated and cannot be changed when adding</small>
                </div>
                
                <div class="form-group">
                    <label for="department_name">Department Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="department_name" name="name" required maxlength="255" placeholder="Enter department name">
                </div>
                
                <div class="form-group">
                    <label for="department_building">Building</label>
                    <select class="form-control" id="department_building" name="building_id">
                        <option value="">-- Select Building --</option>
                        <?php 
                        $buildings->data_seek(0);
                        while($b = $buildings->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $b['id']; ?>"><?php echo htmlspecialchars($b['name']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="modal-footer-buttons">
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="closeDepartmentModal()">Cancel</button>
                    <button type="submit" class="btn-modal" style="background-color: var(--accent); color: var(--text-primary);">Save Department</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Section Modal -->
<div id="sectionModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header-settings">
            <h3 id="sectionModalTitle"><i class="fas fa-layer-group"></i> Add Section</h3>
            <span class="modal-close" onclick="closeSectionModal()">&times;</span>
        </div>
        <div style="padding: 0 25px 25px 25px;">
            <form method="POST" action="" id="sectionForm">
                <input type="hidden" name="section_action" id="section_action" value="add">
                <input type="hidden" name="id" id="section_id" value="">
                
                <div class="form-group">
                    <label for="section_name">Section Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="section_name" name="name" required maxlength="255" placeholder="Enter section name">
                </div>
                
                <div class="form-group">
                    <label for="section_department">Department</label>
                    <select class="form-control" id="section_department" name="department_id">
                        <option value="">-- Select Department --</option>
                        <?php 
                        $departments->data_seek(0);
                        while($d = $departments->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $d['id']; ?>">
                            <?php echo '[' . htmlspecialchars($d['code']) . '] ' . htmlspecialchars($d['name'] . ($d['building_name'] ? ' (' . $d['building_name'] . ')' : '')); ?>
                        </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="modal-footer-buttons">
                    <button type="button" class="btn-modal btn-modal-secondary" onclick="closeSectionModal()">Cancel</button>
                    <button type="submit" class="btn-modal" style="background-color: var(--accent); color: var(--text-primary);">Save Section</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Delete Building Modal Functions
function openDeleteBuildingModal(id, name) {
    document.getElementById('delete_building_id').value = id;
    document.getElementById('delete_building_name').innerText = name;
    document.getElementById('confirmDeleteBuildingBtn').href = '?delete=' + id + '&type=building';
    document.getElementById('deleteBuildingModal').style.display = 'flex';
}

function closeDeleteBuildingModal() {
    document.getElementById('deleteBuildingModal').style.display = 'none';
}

// Delete Department Modal Functions
function openDeleteDepartmentModal(id, name) {
    document.getElementById('delete_department_id').value = id;
    document.getElementById('delete_department_name').innerText = name;
    document.getElementById('confirmDeleteDepartmentBtn').href = '?delete=' + id + '&type=department';
    document.getElementById('deleteDepartmentModal').style.display = 'flex';
}

function closeDeleteDepartmentModal() {
    document.getElementById('deleteDepartmentModal').style.display = 'none';
}

// Delete Section Modal Functions
function openDeleteSectionModal(id, name) {
    document.getElementById('delete_section_id').value = id;
    document.getElementById('delete_section_name').innerText = name;
    document.getElementById('confirmDeleteSectionBtn').href = '?delete=' + id + '&type=section';
    document.getElementById('deleteSectionModal').style.display = 'flex';
}

function closeDeleteSectionModal() {
    document.getElementById('deleteSectionModal').style.display = 'none';
}

// Building Modal Functions
function openBuildingModal() {
    document.getElementById('buildingModalTitle').innerHTML = '<i class="fas fa-building"></i> Add Building';
    document.getElementById('building_action').value = 'add';
    document.getElementById('building_id').value = '';
    document.getElementById('building_name').value = '';
    document.getElementById('building_floors').value = '1';
    document.getElementById('buildingModal').style.display = 'block';
}

function editBuilding(id, name, floors) {
    document.getElementById('buildingModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Building';
    document.getElementById('building_action').value = 'edit';
    document.getElementById('building_id').value = id;
    document.getElementById('building_name').value = name;
    document.getElementById('building_floors').value = floors;
    document.getElementById('buildingModal').style.display = 'block';
}

function closeBuildingModal() {
    document.getElementById('buildingModal').style.display = 'none';
}

// Department Modal Functions
function openDepartmentModal() {
    document.getElementById('departmentModalTitle').innerHTML = '<i class="fas fa-sitemap"></i> Add Department';
    document.getElementById('department_action').value = 'add';
    document.getElementById('department_id').value = '';
    document.getElementById('department_code').readOnly = true;
    document.getElementById('department_name').value = '';
    document.getElementById('department_building').value = '';
    
    <?php $next_code = getNextDepartmentCode($conn); ?>
    document.getElementById('department_code').value = '<?php echo $next_code; ?>';
    
    document.getElementById('departmentModal').style.display = 'block';
}

function editDepartment(id, name, code, buildingId) {
    document.getElementById('departmentModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Department';
    document.getElementById('department_action').value = 'edit';
    document.getElementById('department_id').value = id;
    document.getElementById('department_code').value = code;
    document.getElementById('department_code').readOnly = false;
    document.getElementById('department_name').value = name;
    document.getElementById('department_building').value = buildingId || '';
    document.getElementById('departmentModal').style.display = 'block';
}

function closeDepartmentModal() {
    document.getElementById('departmentModal').style.display = 'none';
}

// Section Modal Functions
function openSectionModal() {
    document.getElementById('sectionModalTitle').innerHTML = '<i class="fas fa-layer-group"></i> Add Section';
    document.getElementById('section_action').value = 'add';
    document.getElementById('section_id').value = '';
    document.getElementById('section_name').value = '';
    document.getElementById('section_department').value = '';
    document.getElementById('sectionModal').style.display = 'block';
}

function editSection(id, name, departmentId) {
    document.getElementById('sectionModalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Section';
    document.getElementById('section_action').value = 'edit';
    document.getElementById('section_id').value = id;
    document.getElementById('section_name').value = name;
    document.getElementById('section_department').value = departmentId || '';
    document.getElementById('sectionModal').style.display = 'block';
}

function closeSectionModal() {
    document.getElementById('sectionModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    let buildingModal = document.getElementById('buildingModal');
    let departmentModal = document.getElementById('departmentModal');
    let sectionModal = document.getElementById('sectionModal');
    let deleteBuildingModal = document.getElementById('deleteBuildingModal');
    let deleteDepartmentModal = document.getElementById('deleteDepartmentModal');
    let deleteSectionModal = document.getElementById('deleteSectionModal');
    
    if (event.target == buildingModal) {
        closeBuildingModal();
    }
    if (event.target == departmentModal) {
        closeDepartmentModal();
    }
    if (event.target == sectionModal) {
        closeSectionModal();
    }
    if (event.target == deleteBuildingModal) {
        closeDeleteBuildingModal();
    }
    if (event.target == deleteDepartmentModal) {
        closeDeleteDepartmentModal();
    }
    if (event.target == deleteSectionModal) {
        closeDeleteSectionModal();
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