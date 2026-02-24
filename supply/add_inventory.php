<?php
/**
 * Add Inventory Page (Supply Officer)
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Require supply role
requireRole('supply');

$currentUser = getCurrentUser();
$user_id = (int)$currentUser['id'];

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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $article_name = sanitize($_POST['article_name']);
    $description = sanitize($_POST['description']);
    $property_no = 'PROP-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6)); // Auto-generate property number
    $uom = sanitize($_POST['uom']);
    $quantity = floatval($_POST['quantity']);
    $unit_value = floatval($_POST['unit_value']);
    $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 1;
    $section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
    $category = sanitize($_POST['category']);
    $condition_text = sanitize($_POST['condition']);
    $fund_cluster = sanitize($_POST['fund_cluster']);
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
                equipment_id, section_id, category, condition_text,
                fund_cluster, remarks, created_by, date_added
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $qty_property_card = $quantity; // Initial quantity
        $qty_physical_count = $quantity;
        
        $stmt->bind_param(
            "sssdddiiissssi",
            $article_name, $description, $property_no, $uom,
            $qty_property_card, $qty_physical_count, $unit_value,
            $equipment_id, $section_id, $category, $condition_text,
            $fund_cluster, $remarks, $user_id
        );
        
        if ($stmt->execute()) {
            $inventory_id = $stmt->insert_id;
            logActivity('Add Inventory', $inventory_id, "Added new item: $article_name");
            $_SESSION['success'] = "Inventory item added successfully. Property No: $property_no";
            header('Location: ' . SITE_URL . '/supply/dashboard');
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
    <h2 style="margin-bottom: 20px;">Add New Inventory Item</h2>
    
    <form method="POST" action="" id="inventoryForm">
        <div class="form-group">
            <label for="article_name">Article Name *</label>
            <input type="text" class="form-control" id="article_name" name="article_name" 
                   value="<?php echo isset($_POST['article_name']) ? htmlspecialchars($_POST['article_name']) : ''; ?>" 
                   required maxlength="255">
        </div>
        
        <div class="form-group">
            <label for="description">Description</label>
            <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="category">Category</label>
                <select class="form-control" id="category" name="category">
                    <option value="">-- Select Category --</option>
                    <option value="ICT Equipment" <?php echo (isset($_POST['category']) && $_POST['category'] == 'ICT Equipment') ? 'selected' : ''; ?>>ICT Equipment</option>
                    <option value="Medical Equipment" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Medical Equipment') ? 'selected' : ''; ?>>Medical Equipment</option>
                    <option value="Office Supplies" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Office Supplies') ? 'selected' : ''; ?>>Office Supplies</option>
                    <option value="Furniture" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Furniture') ? 'selected' : ''; ?>>Furniture</option>
                    <option value="Tools" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Tools') ? 'selected' : ''; ?>>Tools</option>
                    <option value="Other" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="equipment_id">Equipment Type</label>
                <select class="form-control" id="equipment_id" name="equipment_id">
                    <option value="">-- Select Equipment Type --</option>
                    <?php if ($equipment): while($eq = $equipment->fetch_assoc()): ?>
                    <option value="<?php echo $eq['id']; ?>" 
                        <?php echo (isset($_POST['equipment_id']) && $_POST['equipment_id'] == $eq['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($eq['name']); ?>
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="uom">Unit of Measurement *</label>
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
                <label for="quantity">Quantity *</label>
                <input type="number" class="form-control" id="quantity" name="quantity" 
                       value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : '1'; ?>" 
                       min="0.01" step="0.01" required>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="unit_value">Unit Value (₱) *</label>
                <input type="number" class="form-control" id="unit_value" name="unit_value" 
                       value="<?php echo isset($_POST['unit_value']) ? htmlspecialchars($_POST['unit_value']) : ''; ?>" 
                       min="0.01" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label for="total_value">Total Value</label>
                <input type="text" class="form-control" id="total_value" readonly>
            </div>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label for="section_id">Location (Section)</label>
                <select class="form-control" id="section_id" name="section_id">
                    <option value="">-- Select Location --</option>
                    <?php if ($sections): while($sec = $sections->fetch_assoc()): ?>
                    <option value="<?php echo $sec['id']; ?>" 
                        <?php echo (isset($_POST['section_id']) && $_POST['section_id'] == $sec['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($sec['department_name'] . ' - ' . $sec['name']); ?>
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="condition">Condition</label>
                <select class="form-control" id="condition" name="condition">
                    <option value="New" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'New') ? 'selected' : ''; ?>>New</option>
                    <option value="Good" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'Good') ? 'selected' : ''; ?>>Good</option>
                    <option value="Fair" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'Fair') ? 'selected' : ''; ?>>Fair</option>
                    <option value="Poor" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'Poor') ? 'selected' : ''; ?>>Poor</option>
                    <option value="For Repair" <?php echo (isset($_POST['condition']) && $_POST['condition'] == 'For Repair') ? 'selected' : ''; ?>>For Repair</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label for="fund_cluster">Fund Cluster</label>
            <input type="text" class="form-control" id="fund_cluster" name="fund_cluster" 
                   value="<?php echo isset($_POST['fund_cluster']) ? htmlspecialchars($_POST['fund_cluster']) : ''; ?>">
        </div>
        
        <div class="form-group">
            <label for="remarks">Remarks</label>
            <textarea class="form-control" id="remarks" name="remarks" rows="2"><?php echo isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : ''; ?></textarea>
        </div>
        
        <div class="form-group">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Inventory Item
            </button>
            <a href="<?php echo SITE_URL; ?>/supply/dashboard" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

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
    
    if (quantity <= 0) {
        e.preventDefault();
        alert('Quantity must be greater than 0');
    } else if (unitValue <= 0) {
        e.preventDefault();
        alert('Unit value must be greater than 0');
    }
});
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>