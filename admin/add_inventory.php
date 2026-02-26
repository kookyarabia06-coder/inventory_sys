<?php
/**
 * Add Inventory Page (Admin)
 * Allows admin to add new inventory items with all fields
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Require admin role
requireRole('admin');

$page_title = 'Add Inventory';
$page_description = 'Add new items to inventory';

// Get equipment types for dropdown
$equipment = $conn->query("SELECT * FROM equipment ORDER BY name");

// Get sections for dropdown
$sections = $conn->query("
    SELECT s.*, d.name as department_name 
    FROM sections s
    LEFT JOIN departments d ON s.department_id = d.id
    ORDER BY d.name, s.name
");

// Get users for dropdown (for approved_by, verified_by, allocate_to)
$users = $conn->query("SELECT id, username, firstname, lastname FROM users WHERE status = 'active' ORDER BY firstname, lastname");

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $article_name = sanitize($_POST['article_name']);
    $description = sanitize($_POST['description']);
    $property_no = generatePropertyNo(); // Auto-generate property number
    $uom = sanitize($_POST['uom']);
    $quantity = floatval($_POST['quantity']);
    $unit_value = floatval($_POST['unit_value']);
    $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
    $section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
    $category = sanitize($_POST['category']);
    $type_equipment = sanitize($_POST['type_equipment']);
    $condition_text = sanitize($_POST['condition_text']);
    $fund_cluster = sanitize($_POST['fund_cluster']);
    $certified_correct = sanitize($_POST['certified_correct']);
    $approved_by = !empty($_POST['approved_by']) ? (int)$_POST['approved_by'] : null;
    $verified_by = !empty($_POST['verified_by']) ? (int)$_POST['verified_by'] : null;
    $allocate_to = !empty($_POST['allocate_to']) ? (int)$_POST['allocate_to'] : null;
    $remarks = sanitize($_POST['remarks']);
    
    // Validate required fields
    $errors = [];
    if (empty($article_name)) $errors[] = "Article name is required";
    if (empty($uom)) $errors[] = "Unit of measurement is required";
    if ($quantity <= 0) $errors[] = "Quantity must be greater than 0";
    if ($unit_value <= 0) $errors[] = "Unit value must be greater than 0";
    
    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO inventory (
                article_name, description, property_no, uom, 
                qty_property_card, qty_physical_count, unit_value,
                equipment_id, section_id, category, type_equipment, condition_text,
                fund_cluster, certified_correct, approved_by, verified_by,
                allocate_to, remarks, created_by, date_added, date_updated
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $qty_property_card = $quantity; // Initial quantity
        $qty_physical_count = $quantity;
        $created_by = $_SESSION['user_id'];
        
        $stmt->bind_param(
            "sssdddiiisssssiiiisi",
            $article_name, $description, $property_no, $uom,
            $qty_property_card, $qty_physical_count, $unit_value,
            $equipment_id, $section_id, $category, $type_equipment, $condition_text,
            $fund_cluster, $certified_correct, $approved_by, $verified_by,
            $allocate_to, $remarks, $created_by
        );
        
        if ($stmt->execute()) {
            $inventory_id = $stmt->insert_id;
            logActivity('Add Inventory', $inventory_id, "Added new item: $article_name");
            $_SESSION['success'] = "Inventory item added successfully. Property No: $property_no";
            header('Location: ' . SITE_URL . '/admin/dashboard.php');
            exit();
        } else {
            $errors[] = "Database error: " . $conn->error;
        }
        $stmt->close();
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

include INCLUDE_PATH . '/header.php';
?>

<div class="form-container">
    <h2><i class="fas fa-plus-circle"></i> Add New Inventory Item</h2>
    
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i>
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" action="" id="inventoryForm">
        <!-- Basic Information -->
        <div class="form-section">
            <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
            
            <div class="form-group">
                <label for="article_name">Article Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="article_name" name="article_name" 
                       value="<?php echo isset($_POST['article_name']) ? htmlspecialchars($_POST['article_name']) : ''; ?>" 
                       required maxlength="255" placeholder="Enter article name">
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" 
                          placeholder="Enter detailed description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>
        </div>
        
        <!-- Classification -->
        <div class="form-section">
            <h3><i class="fas fa-tags"></i> Classification</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="category">Category</label>
                    <select class="form-control" id="category" name="category">
                        <option value="">-- Select Category --</option>
                        <option value="ICT Equipment" <?php echo (isset($_POST['category']) && $_POST['category'] == 'ICT Equipment') ? 'selected' : ''; ?>>ICT Equipment</option>
                        <option value="Medical Equipment" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Medical Equipment') ? 'selected' : ''; ?>>Medical Equipment</option>
                        <option value="Office Supplies" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Office Supplies') ? 'selected' : ''; ?>>Office Supplies</option>
                        <option value="PPE" <?php echo (isset($_POST['category']) && $_POST['category'] == 'PPE') ? 'selected' : ''; ?>>PPE (Personal Protective Equipment)</option>
                        <option value="Semi-Expendable" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Semi-Expendable') ? 'selected' : ''; ?>>Semi-Expendable</option>
                        <option value="Furniture" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Furniture') ? 'selected' : ''; ?>>Furniture</option>
                        <option value="Tools" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Tools') ? 'selected' : ''; ?>>Tools</option>
                        <option value="Other" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="type_equipment">Type of Equipment</label>
                    <input type="text" class="form-control" id="type_equipment" name="type_equipment" 
                           value="<?php echo isset($_POST['type_equipment']) ? htmlspecialchars($_POST['type_equipment']) : ''; ?>"
                           placeholder="e.g., Heavy, Light, Medical">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="equipment_id">Equipment Type</label>
                    <select class="form-control" id="equipment_id" name="equipment_id">
                        <option value="">-- Select Equipment Type --</option>
                        <?php if ($equipment && $equipment->num_rows > 0): 
                            mysqli_data_seek($equipment, 0);
                            while($eq = $equipment->fetch_assoc()): ?>
                        <option value="<?php echo $eq['id']; ?>" 
                            <?php echo (isset($_POST['equipment_id']) && $_POST['equipment_id'] == $eq['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($eq['name']); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="section_id">Location (Section)</label>
                    <select class="form-control" id="section_id" name="section_id">
                        <option value="">-- Select Location --</option>
                        <?php if ($sections && $sections->num_rows > 0): 
                            mysqli_data_seek($sections, 0);
                            while($sec = $sections->fetch_assoc()): ?>
                        <option value="<?php echo $sec['id']; ?>" 
                            <?php echo (isset($_POST['section_id']) && $_POST['section_id'] == $sec['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(($sec['department_name'] ?? '') . ' - ' . $sec['name']); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Quantity and Value -->
        <div class="form-section">
            <h3><i class="fas fa-calculator"></i> Quantity and Value</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="uom">Unit of Measurement <span class="text-danger">*</span></label>
                    <select class="form-control" id="uom" name="uom" required>
                        <option value="">-- Select UOM --</option>
                        <option value="pcs" <?php echo (isset($_POST['uom']) && $_POST['uom'] == 'pcs') ? 'selected' : ''; ?>>Pieces (pcs)</option>
                        <option value="box" <?php echo (isset($_POST['uom']) && $_POST['uom'] == 'box') ? 'selected' : ''; ?>>Box</option>
                        <option value="unit" <?php echo (isset($_POST['uom']) && $_POST['uom'] == 'unit') ? 'selected' : ''; ?>>Unit</option>
                        <option value="set" <?php echo (isset($_POST['uom']) && $_POST['uom'] == 'set') ? 'selected' : ''; ?>>Set</option>
                        <option value="meter" <?php echo (isset($_POST['uom']) && $_POST['uom'] == 'meter') ? 'selected' : ''; ?>>Meter</option>
                        <option value="liter" <?php echo (isset($_POST['uom']) && $_POST['uom'] == 'liter') ? 'selected' : ''; ?>>Liter</option>
                        <option value="kg" <?php echo (isset($_POST['uom']) && $_POST['uom'] == 'kg') ? 'selected' : ''; ?>>Kilogram (kg)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="quantity" name="quantity" 
                           value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : '1'; ?>" 
                           min="0.01" step="0.01" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="unit_value">Unit Value (₱) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="unit_value" name="unit_value" 
                           value="<?php echo isset($_POST['unit_value']) ? htmlspecialchars($_POST['unit_value']) : ''; ?>" 
                           min="0.01" step="0.01" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label for="total_value">Total Value</label>
                    <input type="text" class="form-control" id="total_value" readonly placeholder="₱0.00">
                </div>
            </div>
            
            <div class="form-group">
                <label for="fund_cluster">Fund Cluster</label>
                <input type="text" class="form-control" id="fund_cluster" name="fund_cluster" 
                       value="<?php echo isset($_POST['fund_cluster']) ? htmlspecialchars($_POST['fund_cluster']) : ''; ?>"
                       placeholder="e.g., General Fund, Trust Fund">
            </div>
        </div>
        
        <!-- Condition and Certification -->
        <div class="form-section">
            <h3><i class="fas fa-clipboard-check"></i> Condition and Certification</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="condition_text">Condition</label>
                    <select class="form-control" id="condition_text" name="condition_text">
                        <option value="New" <?php echo (isset($_POST['condition_text']) && $_POST['condition_text'] == 'New') ? 'selected' : ''; ?>>New</option>
                        <option value="Good" <?php echo (isset($_POST['condition_text']) && $_POST['condition_text'] == 'Good') ? 'selected' : ''; ?>>Good</option>
                        <option value="Fair" <?php echo (isset($_POST['condition_text']) && $_POST['condition_text'] == 'Fair') ? 'selected' : ''; ?>>Fair</option>
                        <option value="Poor" <?php echo (isset($_POST['condition_text']) && $_POST['condition_text'] == 'Poor') ? 'selected' : ''; ?>>Poor</option>
                        <option value="For Repair" <?php echo (isset($_POST['condition_text']) && $_POST['condition_text'] == 'For Repair') ? 'selected' : ''; ?>>For Repair</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="certified_correct">Certified Correct By</label>
                    <input type="text" class="form-control" id="certified_correct" name="certified_correct" 
                           value="<?php echo isset($_POST['certified_correct']) ? htmlspecialchars($_POST['certified_correct']) : ''; ?>"
                           placeholder="Name of certifying officer">
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="approved_by">Approved By</label>
                    <select class="form-control" id="approved_by" name="approved_by">
                        <option value="">-- Select Approver --</option>
                        <?php if ($users && $users->num_rows > 0): 
                            mysqli_data_seek($users, 0);
                            while($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" 
                            <?php echo (isset($_POST['approved_by']) && $_POST['approved_by'] == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="verified_by">Verified By</label>
                    <select class="form-control" id="verified_by" name="verified_by">
                        <option value="">-- Select Verifier --</option>
                        <?php if ($users && $users->num_rows > 0): 
                            mysqli_data_seek($users, 0);
                            while($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" 
                            <?php echo (isset($_POST['verified_by']) && $_POST['verified_by'] == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Allocation and Remarks -->
        <div class="form-section">
            <h3><i class="fas fa-tasks"></i> Allocation and Remarks</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="allocate_to">Allocate To</label>
                    <select class="form-control" id="allocate_to" name="allocate_to">
                        <option value="">-- Select User --</option>
                        <?php if ($users && $users->num_rows > 0): 
                            mysqli_data_seek($users, 0);
                            while($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" 
                            <?php echo (isset($_POST['allocate_to']) && $_POST['allocate_to'] == $user['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                    <small class="form-text text-muted">Assign this item to a specific user</small>
                </div>
            </div>
            
            <div class="form-group">
                <label for="remarks">Remarks</label>
                <textarea class="form-control" id="remarks" name="remarks" rows="2" 
                          placeholder="Any additional notes"><?php echo isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : ''; ?></textarea>
            </div>
        </div>
        
        <!-- Date Information (Display Only) -->
        <div class="form-section">
            <h3><i class="fas fa-calendar-alt"></i> Date Information</h3>
            <div class="form-row">
                <div class="form-group">
                    <label>Date Added</label>
                    <input type="text" class="form-control" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly disabled>
                    <small class="form-text text-muted">Auto-generated on submission</small>
                </div>
                <div class="form-group">
                    <label>Date Updated</label>
                    <input type="text" class="form-control" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly disabled>
                    <small class="form-text text-muted">Auto-updated on changes</small>
                </div>
            </div>
        </div>
        
        <!-- Submit Buttons -->
        <div class="form-group" style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #BBE0EF;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Inventory Item
            </button>
            <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<style>
/* Additional styles for the enhanced form */
.form-section {
    background: #f8f9fa;
    padding: 20px;
    margin-bottom: 25px;
    border-radius: 10px;
    border-left: 4px solid #F16D34;
    box-shadow: 0 2px 5px rgba(0,0,0,0.05);
}

.form-section h3 {
    color: #161E54;
    margin-bottom: 20px;
    font-size: 18px;
    padding-bottom: 10px;
    border-bottom: 1px solid #BBE0EF;
}

.form-section h3 i {
    color: #F16D34;
    margin-right: 10px;
}

.form-text.text-muted {
    color: #FF986A !important;
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

/* Make readonly fields look different */
.form-control[readonly] {
    background-color: #BBE0EF;
    color: #161E54;
    cursor: not-allowed;
    opacity: 0.7;
}

.form-control[disabled] {
    background-color: #BBE0EF;
    color: #161E54;
    cursor: not-allowed;
    opacity: 0.7;
}
</style>

<script>
// Calculate total value
document.getElementById('quantity').addEventListener('input', calculateTotal);
document.getElementById('unit_value').addEventListener('input', calculateTotal);

function calculateTotal() {
    let quantity = parseFloat(document.getElementById('quantity').value) || 0;
    let unitValue = parseFloat(document.getElementById('unit_value').value) || 0;
    let total = quantity * unitValue;
    document.getElementById('total_value').value = '₱' + total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Form validation
document.getElementById('inventoryForm').addEventListener('submit', function(e) {
    let quantity = parseFloat(document.getElementById('quantity').value);
    let unitValue = parseFloat(document.getElementById('unit_value').value);
    let articleName = document.getElementById('article_name').value.trim();
    
    if (articleName === '') {
        e.preventDefault();
        alert('Please enter an article name');
        return false;
    }
    
    if (quantity <= 0) {
        e.preventDefault();
        alert('Quantity must be greater than 0');
        return false;
    }
    
    if (unitValue <= 0) {
        e.preventDefault();
        alert('Unit value must be greater than 0');
        return false;
    }
});

// Auto-calculate on page load if values exist
window.addEventListener('load', function() {
    calculateTotal();
});
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>