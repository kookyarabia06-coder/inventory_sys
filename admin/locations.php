<?php
/**
 * Locations Page (Admin)
 * Manage buildings, departments, and sections
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

requireRole('admin');

$page_title = 'Locations';
$page_description = 'Manage buildings, departments, and sections';

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
        
        $stmt = $conn->prepare("INSERT INTO departments (name, building_id) VALUES (?, ?)");
        $stmt->bind_param("si", $name, $building_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Department added successfully";
            logActivity('Add Department', 0, "Added department: $name");
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
        
        $stmt = $conn->prepare("UPDATE departments SET name = ?, building_id = ? WHERE id = ?");
        $stmt->bind_param("sii", $name, $building_id, $id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Department updated successfully";
            logActivity('Edit Department', $id, "Updated department: $name");
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
        // Check if building has departments
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
        // Check if department has sections
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
        // Check if section is used in inventory
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

// Get all buildings for dropdowns
$buildings = $conn->query("SELECT * FROM buildings ORDER BY name");

// Get all departments with building names
$departments = $conn->query("
    SELECT d.*, b.name as building_name 
    FROM departments d
    LEFT JOIN buildings b ON d.building_id = b.id
    ORDER BY b.name, d.name
");

// Get all sections with department names
$sections = $conn->query("
    SELECT s.*, d.name as department_name 
    FROM sections s
    LEFT JOIN departments d ON s.department_id = d.id
    ORDER BY d.name, s.name
");

include INCLUDE_PATH . '/header.php';
?>

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

<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr); gap: 20px;">
    <!-- Buildings -->
    <div class="stat-chart">
        <div class="table-header">
            <h3><i class="fas fa-building"></i> Buildings</h3>
            <button class="btn btn-sm btn-primary" onclick="openBuildingModal()">
                <i class="fas fa-plus"></i> Add
            </button>
        </div>
        
        <div style="max-height: 400px; overflow-y: auto;">
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Floors</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($buildings && $buildings->num_rows > 0): ?>
                        <?php while($b = $buildings->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($b['name']); ?></td>
                            <td><?php echo $b['floor']; ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn edit" onclick="editBuilding(<?php echo $b['id']; ?>, '<?php echo htmlspecialchars($b['name']); ?>', <?php echo $b['floor']; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?php echo $b['id']; ?>&type=building" 
                                       class="action-btn delete" 
                                       onclick="return confirm('Are you sure you want to delete this building?')"
                                       title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center">No buildings found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Departments -->
    <div class="stat-chart">
        <div class="table-header">
            <h3><i class="fas fa-sitemap"></i> Departments</h3>
            <button class="btn btn-sm btn-primary" onclick="openDepartmentModal()">
                <i class="fas fa-plus"></i> Add
            </button>
        </div>
        
        <div style="max-height: 400px; overflow-y: auto;">
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Building</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($departments && $departments->num_rows > 0): ?>
                        <?php while($d = $departments->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($d['name']); ?></td>
                            <td><?php echo htmlspecialchars($d['building_name'] ?? 'N/A'); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn edit" onclick="editDepartment(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars($d['name']); ?>', <?php echo $d['building_id'] ?? 'null'; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?php echo $d['id']; ?>&type=department" 
                                       class="action-btn delete" 
                                       onclick="return confirm('Are you sure you want to delete this department?')"
                                       title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center">No departments found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Sections -->
    <div class="stat-chart">
        <div class="table-header">
            <h3><i class="fas fa-layer-group"></i> Sections</h3>
            <button class="btn btn-sm btn-primary" onclick="openSectionModal()">
                <i class="fas fa-plus"></i> Add
            </button>
        </div>
        
        <div style="max-height: 400px; overflow-y: auto;">
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($sections && $sections->num_rows > 0): ?>
                        <?php while($s = $sections->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($s['name']); ?></td>
                            <td><?php echo htmlspecialchars($s['department_name'] ?? 'N/A'); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <button class="action-btn edit" onclick="editSection(<?php echo $s['id']; ?>, '<?php echo htmlspecialchars($s['name']); ?>', <?php echo $s['department_id'] ?? 'null'; ?>)" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <a href="?delete=<?php echo $s['id']; ?>&type=section" 
                                       class="action-btn delete" 
                                       onclick="return confirm('Are you sure you want to delete this section?')"
                                       title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center">No sections found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Building Modal -->
<div id="buildingModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2 id="buildingModalTitle">Add Building</h2>
            <span class="modal-close" onclick="closeBuildingModal()">&times;</span>
        </div>
        <div class="modal-body">
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
                
                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Save Building</button>
                    <button type="button" class="btn btn-secondary" onclick="closeBuildingModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Department Modal -->
<div id="departmentModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2 id="departmentModalTitle">Add Department</h2>
            <span class="modal-close" onclick="closeDepartmentModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="departmentForm">
                <input type="hidden" name="department_action" id="department_action" value="add">
                <input type="hidden" name="id" id="department_id" value="">
                
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
                
                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Save Department</button>
                    <button type="button" class="btn btn-secondary" onclick="closeDepartmentModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Section Modal -->
<div id="sectionModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2 id="sectionModalTitle">Add Section</h2>
            <span class="modal-close" onclick="closeSectionModal()">&times;</span>
        </div>
        <div class="modal-body">
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
                        <option value="<?php echo $d['id']; ?>"><?php echo htmlspecialchars($d['name'] . ($d['building_name'] ? ' (' . $d['building_name'] . ')' : '')); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Save Section</button>
                    <button type="button" class="btn btn-secondary" onclick="closeSectionModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Stats grid layout */
.stats-grid {
    display: grid;
    gap: 20px;
    margin-bottom: 30px;
}

.stat-chart {
    background: white;
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.table-header h3 {
    color: #161E54;
    font-size: 18px;
    margin: 0;
}

.table-header h3 i {
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
    padding: 10px;
    background-color: #f8f9fa;
    color: #161E54;
    font-weight: 600;
    border-bottom: 2px solid #BBE0EF;
}

td {
    padding: 10px;
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
    width: 28px;
    height: 28px;
    border-radius: 4px;
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

.action-btn.delete {
    background-color: #dc3545;
}

.action-btn.delete:hover {
    background-color: #c82333;
    transform: translateY(-2px);
    box-shadow: 0 3px 8px rgba(220,53,69,0.3);
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
    margin: 10% auto;
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

/* Form styles */
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
    padding: 8px 16px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 13px;
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
</style>

<script>
// Building Modal Functions
function openBuildingModal() {
    document.getElementById('buildingModalTitle').textContent = 'Add Building';
    document.getElementById('building_action').value = 'add';
    document.getElementById('building_id').value = '';
    document.getElementById('building_name').value = '';
    document.getElementById('building_floors').value = '1';
    document.getElementById('buildingModal').style.display = 'block';
}

function editBuilding(id, name, floors) {
    document.getElementById('buildingModalTitle').textContent = 'Edit Building';
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
    document.getElementById('departmentModalTitle').textContent = 'Add Department';
    document.getElementById('department_action').value = 'add';
    document.getElementById('department_id').value = '';
    document.getElementById('department_name').value = '';
    document.getElementById('department_building').value = '';
    document.getElementById('departmentModal').style.display = 'block';
}

function editDepartment(id, name, buildingId) {
    document.getElementById('departmentModalTitle').textContent = 'Edit Department';
    document.getElementById('department_action').value = 'edit';
    document.getElementById('department_id').value = id;
    document.getElementById('department_name').value = name;
    document.getElementById('department_building').value = buildingId || '';
    document.getElementById('departmentModal').style.display = 'block';
}

function closeDepartmentModal() {
    document.getElementById('departmentModal').style.display = 'none';
}

// Section Modal Functions
function openSectionModal() {
    document.getElementById('sectionModalTitle').textContent = 'Add Section';
    document.getElementById('section_action').value = 'add';
    document.getElementById('section_id').value = '';
    document.getElementById('section_name').value = '';
    document.getElementById('section_department').value = '';
    document.getElementById('sectionModal').style.display = 'block';
}

function editSection(id, name, departmentId) {
    document.getElementById('sectionModalTitle').textContent = 'Edit Section';
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
    
    if (event.target == buildingModal) {
        closeBuildingModal();
    }
    if (event.target == departmentModal) {
        closeDepartmentModal();
    }
    if (event.target == sectionModal) {
        closeSectionModal();
    }
}
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>