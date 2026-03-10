employees.php
<?php
/**
 * Employees Page (Admin)
 * Manage employees with full CRUD operations
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

requireRole('admin');

$page_title = 'Employees';
$page_description = 'Manage employees';

// Handle Add/Edit Employee
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] == 'add' || $_POST['action'] == 'edit') {
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
            
            $errors = [];
            if (empty($firstname)) $errors[] = "First name is required";
            if (empty($lastname)) $errors[] = "Last name is required";
            
            if (!empty($email) && !validateEmail($email)) {
                $errors[] = "Invalid email format";
            }
            
            if (empty($errors)) {
                if ($_POST['action'] == 'add') {
                    $stmt = $conn->prepare("
                        INSERT INTO employees (
                            firstname, lastname, middlename, email, contact,
                            department_id, section_id, position, date_hired, status
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $stmt->bind_param(
                        "sssssiisss",
                        $firstname, $lastname, $middlename, $email, $contact,
                        $department_id, $section_id, $position, $date_hired, $status
                    );
                    
                    if ($stmt->execute()) {
                        $employee_id = $stmt->insert_id;
                        logActivity('Add Employee', $employee_id, "Added employee: $lastname, $firstname");
                        $_SESSION['success'] = "Employee added successfully";
                    } else {
                        $_SESSION['error'] = "Database error: " . $conn->error;
                    }
                    $stmt->close();
                    
                } elseif ($_POST['action'] == 'edit' && isset($_POST['id'])) {
                    $id = (int)$_POST['id'];
                    
                    $stmt = $conn->prepare("
                        UPDATE employees SET 
                            firstname = ?, lastname = ?, middlename = ?,
                            email = ?, contact = ?, department_id = ?,
                            section_id = ?, position = ?, date_hired = ?,
                            status = ?, date_updated = NOW()
                        WHERE id = ?
                    ");
                    
                    $stmt->bind_param(
                        "sssssiisssi",
                        $firstname, $lastname, $middlename, $email, $contact,
                        $department_id, $section_id, $position, $date_hired,
                        $status, $id
                    );
                    
                    if ($stmt->execute()) {
                        logActivity('Edit Employee', $id, "Edited employee: $lastname, $firstname");
                        $_SESSION['success'] = "Employee updated successfully";
                    } else {
                        $_SESSION['error'] = "Error updating employee: " . $conn->error;
                    }
                    $stmt->close();
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
    $id = (int)$_GET['delete'];
    
    // Check if employee is assigned to any inventory
    $check = $conn->query("SELECT id FROM user_inventory WHERE user_id = $id LIMIT 1");
    if ($check && $check->num_rows > 0) {
        $_SESSION['error'] = "Cannot delete employee with assigned inventory items";
    } else {
        $conn->query("DELETE FROM employees WHERE id = $id");
        if ($conn->affected_rows > 0) {
            logActivity('Delete Employee', $id, "Deleted employee ID: $id");
            $_SESSION['success'] = "Employee deleted successfully";
        }
    }
    
    header('Location: ' . SITE_URL . '/admin/employees.php');
    exit();
}

// Handle AJAX request to get employee details
if (isset($_GET['get_employee']) && is_numeric($_GET['get_employee'])) {
    header('Content-Type: application/json');
    
    $id = (int)$_GET['get_employee'];
    $result = $conn->query("SELECT * FROM employees WHERE id = $id");
    
    if ($result && $row = $result->fetch_assoc()) {
        echo json_encode(['success' => true, 'employee' => $row]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Employee not found']);
    }
    exit;
}

// Get all employees with their departments and sections
$employees = $conn->query("
    SELECT e.*, d.name as department_name, s.name as section_name 
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN sections s ON e.section_id = s.id
    ORDER BY e.lastname, e.firstname
");

// Get departments for dropdown
$departments = $conn->query("SELECT * FROM departments ORDER BY name");

// Get sections for dropdown (will be filtered by department via JS)
$sections = $conn->query("SELECT s.*, d.name as department_name FROM sections s LEFT JOIN departments d ON s.department_id = d.id ORDER BY d.name, s.name");

include INCLUDE_PATH . '/header.php';
?>

<!-- Display Success/Error Messages -->
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

<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-user-tie"></i> Employees</h2>
        <button class="btn btn-primary" onclick="openEmployeeModal()">
            <i class="fas fa-plus"></i> Add Employee
        </button>
    </div>
    
    <div style="overflow-x: auto;">
        <table style="min-width: 1400px;">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Contact</th>
                    <th>Department</th>
                    <th>Section</th>
                    <th>Position</th>
                    <th>Date Hired</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($employees && $employees->num_rows > 0): ?>
                    <?php while($emp = $employees->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $emp['id']; ?></td>
                        <td>
                            <strong><?php echo htmlspecialchars($emp['lastname'] . ', ' . $emp['firstname'] . ' ' . ($emp['middlename'] ?? '')); ?></strong>
                        </td>
                        <td><?php echo htmlspecialchars($emp['email'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($emp['contact'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($emp['section_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($emp['position'] ?? 'N/A'); ?></td>
                        <td><?php echo $emp['date_hired'] ? date('M d, Y', strtotime($emp['date_hired'])) : 'N/A'; ?></td>
                        <td>
                            <?php if ($emp['status'] == 'Active'): ?>
                                <span class="badge badge-success">Active</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn edit" onclick="editEmployee(<?php echo $emp['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn view" onclick="viewEmployee(<?php echo $emp['id']; ?>)" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="?delete=<?php echo $emp['id']; ?>" 
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
                        <td colspan="10" class="text-center">
                            <i class="fas fa-users" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                            <br>
                            No employees found
                            <br>
                            <button class="btn btn-primary mt-3" onclick="openEmployeeModal()">
                                <i class="fas fa-plus"></i> Add Your First Employee
                            </button>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Employee Modal -->
<div id="employeeModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="modalTitle">Add Employee</h2>
            <span class="modal-close" onclick="closeEmployeeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="employeeForm">
                <input type="hidden" name="action" id="action" value="add">
                <input type="hidden" name="id" id="employee_id" value="">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="lastname">Last Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="lastname" name="lastname" required maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="firstname">First Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="firstname" name="firstname" required maxlength="100">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="middlename">Middle Name</label>
                    <input type="text" class="form-control" id="middlename" name="middlename" maxlength="100">
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" name="email" maxlength="150">
                    </div>
                    
                    <div class="form-group">
                        <label for="contact">Contact Number</label>
                        <input type="text" class="form-control" id="contact" name="contact" maxlength="50">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="department_id">Department</label>
                        <select class="form-control" id="department_id" name="department_id" onchange="loadSections()">
                            <option value="">-- Select Department --</option>
                            <?php if ($departments && $departments->num_rows > 0): 
                                mysqli_data_seek($departments, 0);
                                while($dept = $departments->fetch_assoc()): ?>
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
                        <input type="text" class="form-control" id="position" name="position" maxlength="100">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_hired">Date Hired</label>
                        <input type="date" class="form-control" id="date_hired" name="date_hired">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="status">Status</label>
                    <select class="form-control" id="status" name="status">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Save Employee</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEmployeeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Employee Modal -->
<div id="viewEmployeeModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2>Employee Details</h2>
            <span class="modal-close" onclick="closeViewEmployeeModal()">&times;</span>
        </div>
        <div class="modal-body" id="viewEmployeeContent">
            <!-- Content will be loaded dynamically -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeViewEmployeeModal()">Close</button>
        </div>
    </div>
</div>

<style>
/* Table styles */
.table-container {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.table-header h2 {
    color: #161E54;
    font-size: 24px;
    margin: 0;
}

.table-header h2 i {
    color: #F16D34;
    margin-right: 10px;
}

/* Table styles */
table {
    width: 100%;
    border-collapse: collapse;
}

th {
    text-align: left;
    padding: 12px;
    background-color: #f8f9fa;
    color: #161E54;
    font-weight: 600;
    border-bottom: 2px solid #BBE0EF;
}

td {
    padding: 12px;
    border-bottom: 1px solid #e0e0e0;
}

/* Action buttons */
.action-buttons {
    display: flex;
    gap: 5px;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 5px;
    color: white;
    text-decoration: none;
    transition: all 0.3s;
    border: none;
    cursor: pointer;
}

.action-btn.edit {
    background-color: #FF986A;
}

.action-btn.edit:hover {
    background-color: #f57c4a;
    transform: translateY(-2px);
    box-shadow: 0 3px 8px rgba(255,152,106,0.3);
}

.action-btn.view {
    background-color: #161E54;
}

.action-btn.view:hover {
    background-color: #0f1442;
    transform: translateY(-2px);
    box-shadow: 0 3px 8px rgba(22,30,84,0.3);
}

.action-btn.delete {
    background-color: #dc3545;
}

.action-btn.delete:hover {
    background-color: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 3px 8px rgba(220,53,69,0.3);
}

/* Badge styles */
.badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
}

.badge-success {
    background-color: #28a745;
    color: white;
}

.badge-danger {
    background-color: #dc3545;
    color: white;
}

/* Modal styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: white;
    margin: 5% auto;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    position: relative;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid #eee;
}

.modal-header h2 {
    color: #161E54;
    margin: 0;
}

.modal-close {
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    color: #999;
}

.modal-close:hover {
    color: #F16D34;
}

.modal-footer {
    margin-top: 20px;
    padding-top: 10px;
    border-top: 1px solid #eee;
    text-align: right;
}

/* Form styles */
.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.form-row .form-group {
    flex: 1;
}

.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
    color: #161E54;
    font-weight: 500;
}

.form-control {
    width: 100%;
    padding: 10px;
    border: 1px solid #ddd;
    border-radius: 5px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: #F16D34;
    box-shadow: 0 0 0 2px rgba(241,109,52,0.1);
}

.text-danger {
    color: #dc3545;
}

/* Button styles */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
}

.btn-primary {
    background-color: #F16D34;
    color: white;
}

.btn-primary:hover {
    background-color: #d55a2a;
    transform: translateY(-2px);
    box-shadow: 0 3px 8px rgba(241,109,52,0.3);
}

.btn-secondary {
    background-color: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background-color: #5a6268;
    transform: translateY(-2px);
    box-shadow: 0 3px 8px rgba(108,117,125,0.3);
}

/* Alert styles */
.alert {
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.alert-success {
    background-color: #d4edda;
    color: #155724;
    border: 1px solid #c3e6cb;
}

.alert-danger {
    background-color: #f8d7da;
    color: #721c24;
    border: 1px solid #f5c6cb;
}

.alert i {
    margin-right: 10px;
}

.text-center {
    text-align: center;
    color: #999;
    padding: 20px;
}

.mt-3 {
    margin-top: 15px;
}
</style>

<script>
// Store sections data for filtering
let sectionsData = [];

<?php if ($sections && $sections->num_rows > 0): 
    mysqli_data_seek($sections, 0);
    while($sec = $sections->fetch_assoc()): ?>
sectionsData.push({
    id: <?php echo $sec['id']; ?>,
    name: '<?php echo htmlspecialchars(addslashes($sec['name'])); ?>',
    department_id: <?php echo $sec['department_id'] ?? 'null'; ?>
});
<?php endwhile; endif; ?>

// Load sections based on selected department
function loadSections() {
    let departmentId = document.getElementById('department_id').value;
    let sectionSelect = document.getElementById('section_id');
    
    // Clear current options
    sectionSelect.innerHTML = '<option value="">-- Select Section --</option>';
    
    // Filter sections by department
    let filteredSections = sectionsData.filter(s => s.department_id == departmentId);
    
    filteredSections.forEach(section => {
        let option = document.createElement('option');
        option.value = section.id;
        option.textContent = section.name;
        sectionSelect.appendChild(option);
    });
}

// Open modal for adding employee
function openEmployeeModal() {
    document.getElementById('modalTitle').textContent = 'Add Employee';
    document.getElementById('action').value = 'add';
    document.getElementById('employee_id').value = '';
    document.getElementById('employeeForm').reset();
    document.getElementById('employeeModal').style.display = 'block';
}

// Close employee modal
function closeEmployeeModal() {
    document.getElementById('employeeModal').style.display = 'none';
}

// Edit employee
function editEmployee(id) {
    fetch('?get_employee=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let emp = data.employee;
                
                document.getElementById('modalTitle').textContent = 'Edit Employee';
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
                
                // Load sections for the selected department
                loadSections();
                
                // Set section after sections are loaded
                setTimeout(() => {
                    document.getElementById('section_id').value = emp.section_id || '';
                }, 100);
                
                document.getElementById('employeeModal').style.display = 'block';
            } else {
                alert('Error loading employee details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading employee details');
        });
}

// View employee details
function viewEmployee(id) {
    fetch('?get_employee=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let emp = data.employee;
                let content = `
                    <div style="text-align: center; margin-bottom: 20px;">
                        <i class="fas fa-user-tie" style="font-size: 64px; color: #F16D34;"></i>
                        <h3>${emp.lastname}, ${emp.firstname} ${emp.middlename || ''}</h3>
                    </div>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 8px 0;"><strong>Employee ID:</strong></td><td>${emp.id}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Email:</strong></td><td>${emp.email || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Contact:</strong></td><td>${emp.contact || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Department:</strong></td><td>${emp.department_name || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Section:</strong></td><td>${emp.section_name || 'N/A'}</td></tr>
                        <tr><td style>                        <tr><td style="padding: 8px 0;"><strong>Position:</strong></td><td>${emp.position || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Date Hired:</strong></td><td>${emp.date_hired ? formatDate(emp.date_hired) : 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Status:</strong></td><td>
                            <span class="badge ${emp.status == 'Active' ? 'badge-success' : 'badge-danger'}">${emp.status || 'N/A'}</span>
                        </td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Date Created:</strong></td><td>${formatDate(emp.date_created)}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Last Updated:</strong></td><td>${emp.date_updated ? formatDate(emp.date_updated) : 'N/A'}</td></tr>
                    </table>
                `;
                document.getElementById('viewEmployeeContent').innerHTML = content;
                document.getElementById('viewEmployeeModal').style.display = 'block';
            } else {
                alert('Error loading employee details');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading employee details');
        });
}

// Close view employee modal
function closeViewEmployeeModal() {
    document.getElementById('viewEmployeeModal').style.display = 'none';
}

// Format date function
function formatDate(dateString) {
    if (!dateString) return 'N/A';
    let date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

// Close modal when clicking outside
window.onclick = function(event) {
    let employeeModal = document.getElementById('employeeModal');
    let viewEmployeeModal = document.getElementById('viewEmployeeModal');
    
    if (event.target == employeeModal) {
        closeEmployeeModal();
    }
    if (event.target == viewEmployeeModal) {
        closeViewEmployeeModal();
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Any initialization code can go here
});
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>