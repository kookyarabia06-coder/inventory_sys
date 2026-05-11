<?php
/**
 * Equipment Management Page (Admin)
 * Manage Type of Equipment and Equipment Sub-Types
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Database configuration
$host = 'localhost';
$dbname = 'inventory_sys';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// ========== AJAX ENDPOINT FOR FETCHING SUB-TYPE ==========
if (isset($_GET['get_subtype']) && is_numeric($_GET['get_subtype'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['get_subtype'];
    $stmt = $pdo->prepare("SELECT * FROM equipment_sub_type WHERE id = ?");
    $stmt->execute([$id]);
    $subtype = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($subtype) {
        echo json_encode(['success' => true, 'subtype' => $subtype]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Sub-type not found']);
    }
    exit;
}
// ==========================================================

// Check role (fixed)
requireRole('admin' || 'superadmin');

// Fix collation and recreate triggers
try {
    $pdo->exec("ALTER TABLE type_of_equipment CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("ALTER TABLE equipment_sub_type CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    $pdo->exec("DROP TRIGGER IF EXISTS trg_type_of_equipment_before_insert");
    $pdo->exec("DROP TRIGGER IF EXISTS trg_equipment_sub_type_before_insert");
    
    $pdo->exec("
    CREATE TRIGGER trg_type_of_equipment_before_insert 
    BEFORE INSERT ON type_of_equipment 
    FOR EACH ROW
    BEGIN
        DECLARE next_num INT;
        
        IF NEW.code IS NULL OR NEW.code = '' THEN
            SELECT COALESCE(MAX(CAST(code AS UNSIGNED)), 0) + 1 INTO next_num 
            FROM type_of_equipment;
            SET NEW.code = LPAD(next_num, 2, '0');
        END IF;
    END
    ");
    
    $pdo->exec("
    CREATE TRIGGER trg_equipment_sub_type_before_insert 
    BEFORE INSERT ON equipment_sub_type 
    FOR EACH ROW
    BEGIN
        DECLARE type_code VARCHAR(2);
        DECLARE next_sub_num INT;
        
        SELECT code INTO type_code 
        FROM type_of_equipment 
        WHERE id = NEW.type_of_equipment_id;
        
        SELECT COALESCE(MAX(CAST(SUBSTRING(code, 3) AS UNSIGNED)), 0) + 1 INTO next_sub_num
        FROM equipment_sub_type 
        WHERE CAST(code AS CHAR) LIKE CONCAT(CAST(type_code AS CHAR), '%');
        
        SET NEW.code = CONCAT(type_code, LPAD(next_sub_num, 2, '0'));
    END
    ");
    
} catch(PDOException $e) {
    // Triggers might already exist
}

// Handle Type of Equipment Actions
if (isset($_POST['type_action'])) {
    if ($_POST['type_action'] == 'add') {
        $name = sanitize($_POST['name']);
        $stmt = $pdo->prepare("INSERT INTO type_of_equipment (name) VALUES (?)");
        $stmt->execute([$name]);
        logActivity('Add Equipment Type', 0, "Added equipment type: $name");
        $_SESSION['success'] = "Equipment type added successfully";
        header('Location: ' . SITE_URL . '/admin/equipments.php');
        exit();
    }
    
    elseif ($_POST['type_action'] == 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = sanitize($_POST['name']);
        $stmt = $pdo->prepare("UPDATE type_of_equipment SET name = ? WHERE id = ?");
        $stmt->execute([$name, $id]);
        logActivity('Edit Equipment Type', $id, "Updated equipment type: $name");
        $_SESSION['success'] = "Equipment type updated successfully";
        header('Location: ' . SITE_URL . '/admin/equipments.php');
        exit();
    }
}

// Handle Equipment Sub-Type Actions
if (isset($_POST['subtype_action'])) {
    if ($_POST['subtype_action'] == 'add') {
        $name = sanitize($_POST['name']);
        $type_id = (int)$_POST['type_id'];
        $stmt = $pdo->prepare("INSERT INTO equipment_sub_type (name, type_of_equipment_id) VALUES (?, ?)");
        $stmt->execute([$name, $type_id]);
        logActivity('Add Equipment Sub-Type', 0, "Added sub-type: $name");
        $_SESSION['success'] = "Equipment sub-type added successfully";
        header('Location: ' . SITE_URL . '/admin/equipments.php');
        exit();
    }
    
    elseif ($_POST['subtype_action'] == 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $name = sanitize($_POST['name']);
        $type_id = (int)$_POST['type_id'];
        $stmt = $pdo->prepare("UPDATE equipment_sub_type SET name = ?, type_of_equipment_id = ? WHERE id = ?");
        $stmt->execute([$name, $type_id, $id]);
        logActivity('Edit Equipment Sub-Type', $id, "Updated sub-type: $name");
        $_SESSION['success'] = "Equipment sub-type updated successfully";
        header('Location: ' . SITE_URL . '/admin/equipments.php');
        exit();
    }
}

// Handle Delete Actions
if (isset($_GET['delete'])) {
    $type = $_GET['type'] ?? '';
    $id = (int)$_GET['delete'];
    
    if ($type == 'type') {
        // Check if type has sub-types
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_sub_type WHERE type_of_equipment_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $_SESSION['error'] = "Cannot delete: This equipment type has sub-types. Delete sub-types first.";
        } else {
            $stmt = $pdo->prepare("DELETE FROM type_of_equipment WHERE id = ?");
            $stmt->execute([$id]);
            logActivity('Delete Equipment Type', $id, "Deleted equipment type ID: $id");
            $_SESSION['success'] = "Equipment type deleted successfully";
        }
    }
    
    elseif ($type == 'subtype') {
        $stmt = $pdo->prepare("DELETE FROM equipment_sub_type WHERE id = ?");
        $stmt->execute([$id]);
        logActivity('Delete Equipment Sub-Type', $id, "Deleted sub-type ID: $id");
        $_SESSION['success'] = "Equipment sub-type deleted successfully";
    }
    
    header('Location: ' . SITE_URL . '/admin/equipments.php');
    exit();
}

// Fetch all data
$types = $pdo->query("SELECT * FROM type_of_equipment ORDER BY code")->fetchAll();
$subtypes = $pdo->query("
    SELECT s.*, t.name as type_name, t.code as type_code 
    FROM equipment_sub_type s 
    JOIN type_of_equipment t ON s.type_of_equipment_id = t.id 
    ORDER BY t.code, s.code
")->fetchAll();

$page_title = 'Equipment Management';
$page_description = 'Manage equipment types and sub-types';

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

<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-cubes"></i>
        </div>
        <h3>Equipment Types</h3>
        <div class="card-value"><?php echo count($types); ?></div>
        <div class="card-label">Total Categories</div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-microchip"></i>
        </div>
        <h3>Equipment Sub-Types</h3>
        <div class="card-value"><?php echo count($subtypes); ?></div>
        <div class="card-label">Total Sub-Categories</div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-tag"></i>
        </div>
        <h3>Generated Codes</h3>
        <div class="card-value"><?php echo count($types) + count($subtypes); ?></div>
        <div class="card-label">Total Equipment Codes</div>
    </div>
</div>

<!-- Equipment Types Section -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-cubes"></i> Equipment Types</h2>
        <button class="btn btn-primary" onclick="openTypeModal()">
            <i class="fas fa-plus"></i> Add Equipment Type
        </button>
    </div>
    
    <div style="overflow-x: auto;">
        <table style="min-width: 600px;">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Name</th>
                    <th>Sub-Types Count</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($types)): ?>
                    <?php foreach ($types as $type): 
                        // Count sub-types for this type
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_sub_type WHERE type_of_equipment_id = ?");
                        $stmt->execute([$type['id']]);
                        $subtype_count = $stmt->fetchColumn();
                    ?>
                    <tr>
                        <td><span class="badge-warning"><?php echo htmlspecialchars($type['code']); ?></span></td>
                        <td><strong><?php echo htmlspecialchars($type['name']); ?></strong></td>
                        <td><?php echo $subtype_count; ?> sub-type(s)</td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn edit" onclick="editType(<?php echo $type['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?php echo $type['id']; ?>&type=type" 
                                   class="action-btn delete" 
                                   onclick="return confirm('Are you sure you want to delete this equipment type? This will also delete all related sub-types.')"
                                   title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">
                            <i class="fas fa-cubes" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                            <br>
                            No equipment types found
                            <br>
                            <button class="btn btn-primary mt-3" onclick="openTypeModal()">
                                <i class="fas fa-plus"></i> Add Your First Equipment Type
                            </button>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Equipment Sub-Types Section -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-microchip"></i> Equipment Sub-Types</h2>
        <button class="btn btn-primary" onclick="openSubTypeModal()">
            <i class="fas fa-plus"></i> Add Sub-Type
        </button>
    </div>
    
    <div style="overflow-x: auto;">
        <table style="min-width: 800px;">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Parent Type</th>
                    <th>Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($subtypes)): ?>
                    <?php foreach ($subtypes as $subtype): ?>
                    <tr>
                        <td><span class="badge-warning"><?php echo htmlspecialchars($subtype['code']); ?></span></td>
                        <td><?php echo htmlspecialchars($subtype['type_name']); ?></td>
                        <td><strong><?php echo htmlspecialchars($subtype['name']); ?></strong></td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn edit" onclick="editSubType(<?php echo $subtype['id']; ?>)" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="?delete=<?php echo $subtype['id']; ?>&type=subtype" 
                                   class="action-btn delete" 
                                   onclick="return confirm('Are you sure you want to delete this sub-type?')"
                                   title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">
                            <i class="fas fa-microchip" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                            <br>
                            No equipment sub-types found
                            <br>
                            <button class="btn btn-primary mt-3" onclick="openSubTypeModal()">
                                <i class="fas fa-plus"></i> Add Your First Sub-Type
                            </button>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Equipment Type Modal -->
<div id="typeModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2 id="typeModalTitle">Add Equipment Type</h2>
            <span class="modal-close" onclick="closeTypeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="typeForm">
                <input type="hidden" name="type_action" id="type_action" value="add">
                <input type="hidden" name="id" id="type_id" value="">
                
                <div class="form-group">
                    <label for="type_name">Equipment Type Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="type_name" name="name" required maxlength="255" placeholder="e.g., Heavy Machinery, Electronics, Furniture">
                    <div class="form-text">This will automatically generate a 2-digit code (e.g., 01, 02, 03...)</div>
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Save Equipment Type</button>
                    <button type="button" class="btn btn-secondary" onclick="closeTypeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Equipment Sub-Type Modal -->
<div id="subtypeModal" class="modal">
    <div class="modal-content" style="max-width: 500px;">
        <div class="modal-header">
            <h2 id="subtypeModalTitle">Add Equipment Sub-Type</h2>
            <span class="modal-close" onclick="closeSubTypeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="subtypeForm">
                <input type="hidden" name="subtype_action" id="subtype_action" value="add">
                <input type="hidden" name="id" id="subtype_id" value="">
                
                <div class="form-group">
                    <label for="type_id">Parent Equipment Type <span class="text-danger">*</span></label>
                    <select class="form-control" id="type_id" name="type_id" required>
                        <option value="">-- Select Equipment Type --</option>
                        <?php foreach ($types as $type): ?>
                            <option value="<?php echo $type['id']; ?>">
                                <?php echo htmlspecialchars($type['code'] . ' - ' . $type['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="form-text">The sub-type code will be automatically generated (e.g., 01-01, 01-02...)</div>
                </div>
                
                <div class="form-group">
                    <label for="subtype_name">Sub-Type Name <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="subtype_name" name="name" required maxlength="255" placeholder="e.g., Excavator, Laptop, Office Chair">
                </div>
                
                <div class="form-group" style="margin-top: 20px;">
                    <button type="submit" class="btn btn-primary">Save Sub-Type</button>
                    <button type="button" class="btn btn-secondary" onclick="closeSubTypeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

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
    --info: #8FB5FF;
}

body {
    background-color: var(--light);
    color: var(--text-primary);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

/* Dashboard Cards */
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background: var(--white);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
    border-left: 4px solid var(--primary);
    transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(107, 140, 255, 0.15);
}

.card-icon {
    width: 50px;
    height: 50px;
    background: var(--accent-light);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
}

.card-icon i {
    font-size: 24px;
    color: var(--primary);
}

.card h3 {
    color: var(--text-secondary);
    font-size: 14px;
    margin-bottom: 5px;
    font-weight: 500;
}

.card .card-value {
    color: var(--primary);
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
}

.card .card-label {
    color: var(--text-muted);
    font-size: 12px;
}

/* Table Container */
.table-container {
    background: var(--white);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-light);
}

.table-header h2 {
    color: var(--primary);
    font-size: 18px;
    margin: 0;
}

.table-header h2 i {
    color: var(--accent);
    margin-right: 10px;
}

/* Table Styles */
table {
    width: 100%;
    border-collapse: collapse;
}

thead tr {
    background: linear-gradient(to right, var(--light), var(--white));
    border-bottom: 2px solid var(--accent-light);
}

th {
    padding: 15px 10px;
    text-align: left;
    color: var(--primary);
    font-weight: 600;
    font-size: 13px;
    white-space: nowrap;
}

td {
    padding: 15px 10px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
    font-size: 13px;
}

tr:hover {
    background-color: var(--light);
}

/* Badge Styles */
.badge-warning {
    background-color: var(--accent-light);
    color: var(--primary);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
    font-family: 'Courier New', monospace;
}

.badge-success {
    background-color: var(--success-light);
    color: var(--success);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    border: none;
    color: var(--text-light);
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
    font-size: 14px;
}

.action-btn.edit { background-color: var(--secondary); }
.action-btn.delete { background-color: var(--danger); }

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
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
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
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

.btn-xs {
    padding: 4px 8px;
    font-size: 11px;
    border-radius: 4px;
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
    backdrop-filter: blur(3px);
}

.modal-content {
    background: var(--white);
    margin: 5% auto;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(107, 140, 255, 0.2);
    position: relative;
    animation: modalSlideIn 0.3s;
    width: 90%;
    max-width: 500px;
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

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 2px solid var(--accent-light);
}

.modal-header h2 {
    color: var(--primary);
    font-size: 20px;
    margin: 0;
}

.modal-close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: var(--text-muted);
    transition: color 0.2s;
}

.modal-close:hover {
    color: var(--accent);
}

.modal-body {
    padding: 25px;
}

/* Form Styles */
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
    border-radius: 8px;
    font-size: 14px;
    color: var(--text-primary);
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.form-text {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 5px;
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
}

.alert i {
    font-size: 18px;
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

/* Text Utilities */
.text-center {
    text-align: center;
}

.text-danger {
    color: var(--danger) !important;
}

.mt-3 {
    margin-top: 15px;
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-cards {
        grid-template-columns: 1fr;
    }
    
    .table-header {
        flex-direction: column;
        gap: 10px;
        align-items: flex-start;
    }
    
    .modal-content {
        margin: 20px;
        width: auto;
    }
    
    .action-buttons {
        justify-content: flex-start;
    }
}
</style>

<script>
// Store types data for reference
let typesData = [];

<?php foreach ($types as $type): ?>
typesData.push({
    id: <?php echo $type['id']; ?>,
    name: '<?php echo htmlspecialchars(addslashes($type['name'])); ?>',
    code: '<?php echo $type['code']; ?>'
});
<?php endforeach; ?>

// Type Modal Functions
function openTypeModal() {
    document.getElementById('typeModalTitle').textContent = 'Add Equipment Type';
    document.getElementById('type_action').value = 'add';
    document.getElementById('type_id').value = '';
    document.getElementById('type_name').value = '';
    document.getElementById('typeModal').style.display = 'block';
}

function editType(id) {
    // Find type by id
    let type = typesData.find(t => t.id == id);
    if (type) {
        document.getElementById('typeModalTitle').textContent = 'Edit Equipment Type';
        document.getElementById('type_action').value = 'edit';
        document.getElementById('type_id').value = type.id;
        document.getElementById('type_name').value = type.name;
        document.getElementById('typeModal').style.display = 'block';
    } else {
        alert('Error loading type details');
    }
}

function closeTypeModal() {
    document.getElementById('typeModal').style.display = 'none';
}

// Sub-Type Modal Functions
function openSubTypeModal() {
    document.getElementById('subtypeModalTitle').textContent = 'Add Equipment Sub-Type';
    document.getElementById('subtype_action').value = 'add';
    document.getElementById('subtype_id').value = '';
    document.getElementById('subtype_name').value = '';
    document.getElementById('type_id').value = '';
    document.getElementById('subtypeModal').style.display = 'block';
}

function editSubType(id) {
    // Fetch sub-type data via AJAX
    fetch(window.location.pathname + '?get_subtype=' + id)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                document.getElementById('subtypeModalTitle').textContent = 'Edit Equipment Sub-Type';
                document.getElementById('subtype_action').value = 'edit';
                document.getElementById('subtype_id').value = data.subtype.id;
                document.getElementById('subtype_name').value = data.subtype.name;
                document.getElementById('type_id').value = data.subtype.type_of_equipment_id;
                document.getElementById('subtypeModal').style.display = 'block';
            } else {
                alert('Error loading sub-type details: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading sub-type details. Please check the console for details.');
        });
}

function closeSubTypeModal() {
    document.getElementById('subtypeModal').style.display = 'none';
}

// Close modal when clicking outside
window.onclick = function(event) {
    let typeModal = document.getElementById('typeModal');
    let subtypeModal = document.getElementById('subtypeModal');
    
    if (event.target == typeModal) {
        closeTypeModal();
    }
    if (event.target == subtypeModal) {
        closeSubTypeModal();
    }
}
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>