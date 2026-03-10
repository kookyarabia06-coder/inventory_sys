<?php
/**
 * Add Inventory Page (Admin)
 * Allows admin to add new inventory items with barcode generation
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Require admin role
requireRole('admin');

// Include the barcode library
require_once $root_path . '/vendor/autoload.php';
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorHTML;

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

// Handle barcode generation via AJAX
if (isset($_GET['generate_barcode'])) {
header('Content-Type: application/json');

$barcode_value = $_GET['barcode_value'] ?? '';
$barcode_type = $_GET['barcode_type'] ?? 'single';

if (empty($barcode_value)) {
echo json_encode(['error' => 'Please provide barcode value']);
exit;
}

try {
$generator = new BarcodeGeneratorPNG();
$barcode = base64_encode($generator->getBarcode($barcode_value, $generator::TYPE_CODE_128));

echo json_encode([
'success' => true,
'barcode' => $barcode,
'value' => $barcode_value
]);
} catch (Exception $e) {
echo json_encode(['error' => $e->getMessage()]);
}
exit;
}

// Handle multiple barcode generation preview
if (isset($_GET['generate_multiple_preview'])) {
header('Content-Type: application/json');

$prefix = $_GET['prefix'] ?? 'INV';
$quantity = intval($_GET['quantity'] ?? 5);
$baseBarcode = $prefix . date('Ymd');

try {
$generator = new BarcodeGeneratorPNG();
$barcodes = [];

for ($i = 0; $i < min($quantity, 10); $i++) { // Limit to 10 for preview
$randomPart = rand(1000, 9999);
$barcodeValue = $baseBarcode . $randomPart . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
$barcode = base64_encode($generator->getBarcode($barcodeValue, $generator::TYPE_CODE_128));

$barcodes[] = [
'value' => $barcodeValue,
'barcode' => $barcode,
'index' => $i + 1
];
}

echo json_encode([
'success' => true,
'barcodes' => $barcodes,
'total' => $quantity
]);
} catch (Exception $e) {
echo json_encode(['error' => $e->getMessage()]);
}
exit;
}

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

// Barcode related fields
$barcode_option = $_POST['barcode_option'] ?? 'single';
$barcode_prefix = sanitize($_POST['barcode_prefix'] ?? 'INV');
$barcode_quantity = intval($_POST['barcode_quantity'] ?? 1);

// Validate required fields
$errors = [];
if (empty($article_name)) $errors[] = "Article name is required";
if (empty($uom)) $errors[] = "Unit of measurement is required";
if ($quantity <= 0) $errors[] = "Quantity must be greater than 0";
if ($unit_value <= 0) $errors[] = "Unit value must be greater than 0";

// Validate barcode fields
if ($barcode_option == 'single' && empty($_POST['generated_barcode']) && empty($_POST['manual_barcode'])) {
$errors[] = "Please generate a barcode first or enter manually";
}

if ($barcode_option == 'multiple' && $barcode_quantity < 1) {
$errors[] = "Number of barcodes must be at least 1";
}

if (empty($errors)) {
// Begin transaction
$conn->begin_transaction();

try {
if ($barcode_option == 'single') {
// Single item with single barcode
$barcode_data = !empty($_POST['generated_barcode']) ? $_POST['generated_barcode'] : $_POST['manual_barcode'];
$barcode_data = sanitize($barcode_data);

// Check if barcode already exists
$check = $conn->query("SELECT id FROM inventory WHERE barcode_data = '$barcode_data'");
if ($check && $check->num_rows > 0) {
throw new Exception("Barcode '$barcode_data' already exists in the system");
}

$stmt = $conn->prepare("
INSERT INTO inventory (
article_name, description, property_no, uom, 
qty_property_card, qty_physical_count, unit_value,
equipment_id, section_id, category, type_equipment, condition_text,
fund_cluster, certified_correct, approved_by, verified_by,
allocate_to, remarks, created_by, date_added, date_updated,
barcode_data
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
");

$qty_property_card = $quantity;
$qty_physical_count = $quantity;
$created_by = $_SESSION['user_id'];

// Bind parameters - store all values in variables first
$stmt->bind_param(
"sssdddiiisssssiiiiss",
$article_name, 
$description, 
$property_no, 
$uom,
$qty_property_card, 
$qty_physical_count, 
$unit_value,
$equipment_id, 
$section_id, 
$category, 
$type_equipment, 
$condition_text,
$fund_cluster, 
$certified_correct, 
$approved_by, 
$verified_by,
$allocate_to, 
$remarks, 
$created_by, 
$barcode_data
);

if (!$stmt->execute()) {
throw new Exception($conn->error);
}

$inventory_id = $stmt->insert_id;
$stmt->close();

// If allocated to user, create user_inventory record
if ($allocate_to) {
$assign_stmt = $conn->prepare("
INSERT INTO user_inventory (user_id, inventory_id, quantity_assigned, status)
VALUES (?, ?, ?, 'active')
");
$assign_qty = $quantity;
$assign_stmt->bind_param("iid", $allocate_to, $inventory_id, $assign_qty);
$assign_stmt->execute();
$assign_stmt->close();
}

} else {
// Multiple items with sequential barcodes
$base_barcode = generateBarcodeNumber($barcode_prefix);
$created_by = $_SESSION['user_id'];

for ($i = 0; $i < $barcode_quantity; $i++) {
// Generate sequential barcode
$randomPart = rand(1000, 9999);
$barcode_data = $base_barcode . $randomPart . str_pad($i + 1, 3, '0', STR_PAD_LEFT);

// Check if barcode already exists
$check = $conn->query("SELECT id FROM inventory WHERE barcode_data = '$barcode_data'");
if ($check && $check->num_rows > 0) {
throw new Exception("Barcode '$barcode_data' already exists in the system");
}

// For multiple items, we create separate entries with quantity 1 each
$stmt = $conn->prepare("
INSERT INTO inventory (
article_name, description, property_no, uom, 
qty_property_card, qty_physical_count, unit_value,
equipment_id, section_id, category, type_equipment, condition_text,
fund_cluster, certified_correct, approved_by, verified_by,
allocate_to, remarks, created_by, date_added, date_updated,
barcode_data
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
");

$qty_per_item = 1; // Each item has quantity 1
$total_value = $unit_value; // Each item has individual unit value
$property_no_with_suffix = $property_no . '-' . ($i + 1);

// Bind parameters - store all values in variables first
$stmt->bind_param(
"sssdddiiisssssiiiiss",
$article_name, 
$description, 
$property_no_with_suffix, 
$uom,
$qty_per_item, 
$qty_per_item, 
$total_value,
$equipment_id, 
$section_id, 
$category, 
$type_equipment, 
$condition_text,
$fund_cluster, 
$certified_correct, 
$approved_by, 
$verified_by,
$allocate_to, 
$remarks, 
$created_by, 
$barcode_data
);

if (!$stmt->execute()) {
throw new Exception($conn->error);
}

$inventory_id = $stmt->insert_id;
$stmt->close();

// If allocated to user, create user_inventory record
if ($allocate_to) {
$assign_stmt = $conn->prepare("
INSERT INTO user_inventory (user_id, inventory_id, quantity_assigned, status)
VALUES (?, ?, ?, 'active')
");
$assign_qty = 1;
$assign_stmt->bind_param("iid", $allocate_to, $inventory_id, $assign_qty);
$assign_stmt->execute();
$assign_stmt->close();
}
}
}

// Commit transaction
$conn->commit();

logActivity('Add Inventory', $inventory_id ?? 0, "Added new item(s): $article_name with " . ($barcode_option == 'single' ? 'single' : $barcode_quantity . ' items') . " barcode(s)");
$_SESSION['success'] = "Inventory item(s) added successfully. " . ($barcode_option == 'multiple' ? "$barcode_quantity items created." : "");
header('Location: ' . SITE_URL . '/admin/dashboard.php');
exit();

} catch (Exception $e) {
$conn->rollback();
$errors[] = "Error: " . $e->getMessage();
}
}

if (!empty($errors)) {
$_SESSION['error'] = implode("<br>", $errors);
}
}

// Function to generate barcode number
function generateBarcodeNumber($prefix = 'INV') {
return $prefix . date('Ymd');
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
<option value="PPE" <?php echo (isset($_POST['category']) && $_POST['category'] == 'PPE') ? 'selected' : ''; ?>>PPE</option>
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
<!-- Barcode Section -->
<div class="form-section barcode-section">
<h3><i class="fas fa-barcode"></i> Barcode Generation</h3>

<div class="barcode-options">
<div class="form-row">
<div class="form-group">
<label>Barcode Option</label>
<div class="radio-group">
<label class="radio-inline">
<input type="radio" name="barcode_option" value="single" checked onclick="toggleBarcodeOption()"> Single Item
</label>
<label class="radio-inline">
<input type="radio" name="barcode_option" value="multiple" onclick="toggleBarcodeOption()"> Multiple Items (Sequential)
</label>
</div>
</div>
</div>

<!-- Single Barcode Options -->
<div id="singleBarcodeOptions">
<div class="form-row">
<div class="form-group">
<label for="barcode_prefix">Barcode Prefix</label>
<input type="text" class="form-control" id="barcode_prefix" name="barcode_prefix" value="INV" maxlength="10" placeholder="e.g., INV">
</div>
<div class="form-group">
<label>&nbsp;</label>
<button type="button" class="btn btn-secondary" onclick="generateSingleBarcode()">
<i class="fas fa-sync-alt"></i> Generate Barcode
</button>
</div>
</div>

<div class="form-row">
<div class="form-group" style="flex: 2;">
<label for="generated_barcode">Generated Barcode</label>
<div class="barcode-display">
<input type="text" class="form-control" id="generated_barcode" name="generated_barcode" readonly>
<div id="barcodeImage" class="barcode-image"></div>
</div>
</div>
<div class="form-group" style="flex: 1;">
<label for="manual_barcode">Or Enter Manually</label>
<input type="text" class="form-control" id="manual_barcode" name="manual_barcode" placeholder="Enter barcode manually">
</div>
</div>
</div>

<!-- Multiple Barcode Options -->
<div id="multipleBarcodeOptions" style="display: none;">
<div class="form-row">
<div class="form-group">
<label for="barcode_prefix_multi">Barcode Prefix</label>
<input type="text" class="form-control" id="barcode_prefix_multi" value="INV" maxlength="10">
</div>
<div class="form-group">
<label for="barcode_quantity">Number of Items</label>
<input type="number" class="form-control" id="barcode_quantity" value="5" min="1" max="100">
</div>
</div>

<div class="form-group">
<button type="button" class="btn btn-secondary" onclick="previewBarcodes()">
<i class="fas fa-eye"></i> Preview Barcodes
</button>
</div>

<div id="barcodePreview" class="barcode-preview-grid"></div>
</div>
</div>
</div>
<!-- Submit Buttons -->
<div class="form-group" style="margin-top: 30px; padding-top: 20px; border-top: 2px solid #BBE0EF;">
<button type="submit" class="btn btn-primary">
<i class="fas fa-save"></i> Save Inventory Item(s)
</button>
<a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="btn btn-secondary">
<i class="fas fa-times"></i> Cancel
</a>
</div>
</form>
</div>

<style>
/* Additional styles for barcode section */
.barcode-section {
background: #e8f4f8;
border-left: 4px solid #2196F3;
}

.barcode-options {
background: white;
padding: 15px;
border-radius: 5px;
margin-top: 10px;
}

.radio-group {
display: flex;
gap: 20px;
margin-top: 5px;
}

.radio-inline {
display: flex;
align-items: center;
gap: 5px;
cursor: pointer;
}

.barcode-display {
display: flex;
flex-direction: column;
gap: 10px;
}

.barcode-image {
text-align: center;
padding: 10px;
background: white;
border: 1px solid #ddd;
border-radius: 5px;
min-height: 80px;
display: flex;
align-items: center;
justify-content: center;
}

.barcode-image img {
max-width: 100%;
height: auto;
}

.barcode-preview-grid {
margin-top: 20px;
display: grid;
grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
gap: 20px;
max-height: 500px;
overflow-y: auto;
padding: 15px;
background: #f8f9fa;
border-radius: 8px;
border: 1px solid #e0e0e0;
}

.barcode-preview-card {
background: white;
border: 1px solid #e0e0e0;
border-radius: 8px;
padding: 15px;
text-align: center;
transition: all 0.3s;
box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.barcode-preview-card:hover {
transform: translateY(-2px);
box-shadow: 0 4px 8px rgba(0,0,0,0.1);
border-color: #2196F3;
}

.barcode-preview-card .item-number {
font-size: 14px;
font-weight: bold;
color: #2196F3;
margin-bottom: 10px;
padding-bottom: 5px;
border-bottom: 1px solid #eee;
}

.barcode-preview-card .barcode-img {
margin: 10px 0;
padding: 10px;
background: white;
}

.barcode-preview-card .barcode-img img {
max-width: 100%;
height: auto;
}

.barcode-preview-card .barcode-value {
font-family: monospace;
font-size: 12px;
color: #333;
margin-top: 8px;
padding: 5px;
background: #f8f9fa;
border-radius: 4px;
}

/* Form section styles */
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

.form-row {
display: flex;
gap: 20px;
margin-bottom: 15px;
}

.form-row .form-group {
flex: 1;
}

.form-text.text-muted {
color: #FF986A !important;
font-size: 12px;
margin-top: 5px;
display: block;
}

.form-control[readonly] {
background-color: #BBE0EF;
color: #161E54;
cursor: not-allowed;
opacity: 0.7;
}

.btn-secondary {
background-color: #2196F3;
color: white;
border: none;
padding: 8px 16px;
border-radius: 5px;
cursor: pointer;
font-size: 14px;
}

.btn-secondary:hover {
background-color: #1976D2;
}

.btn-primary {
background-color: #F16D34;
color: white;
border: none;
padding: 10px 20px;
border-radius: 5px;
cursor: pointer;
font-size: 16px;
}

.btn-primary:hover {
background-color: #d55a2a;
}

.text-danger {
color: #dc3545;
}

/* Loading indicator */
.loading {
text-align: center;
padding: 30px;
color: #666;
}

.loading i {
font-size: 24px;
margin-bottom: 10px;
color: #2196F3;
}
</style>

<script>
// Toggle between single and multiple barcode options
function toggleBarcodeOption() {
let singleOption = document.getElementById('singleBarcodeOptions');
let multipleOption = document.getElementById('multipleBarcodeOptions');
let singleRadio = document.querySelector('input[name="barcode_option"][value="single"]');

if (singleRadio.checked) {
singleOption.style.display = 'block';
multipleOption.style.display = 'none';
} else {
singleOption.style.display = 'none';
multipleOption.style.display = 'block';
}
}

// Generate single barcode
function generateSingleBarcode() {
let prefix = document.getElementById('barcode_prefix').value;
let barcodeValue = prefix + getFormattedDate() + Math.floor(1000 + Math.random() * 9000);

document.getElementById('generated_barcode').value = barcodeValue;
document.getElementById('manual_barcode').value = ''; // Clear manual entry

// Generate barcode image via AJAX
fetch('?generate_barcode=1&barcode_value=' + encodeURIComponent(barcodeValue))
.then(response => response.json())
.then(data => {
if (data.success) {
let barcodeImage = document.getElementById('barcodeImage');
barcodeImage.innerHTML = '<img src="data:image/png;base64,' + data.barcode + '" alt="Barcode" style="max-width: 100%;">';
} else {
alert('Error generating barcode: ' + data.error);
}
})
.catch(error => {
console.error('Error:', error);
alert('Error generating barcode');
});
}

// Preview multiple barcodes with actual images
function previewBarcodes() {
let prefix = document.getElementById('barcode_prefix_multi').value;
let quantity = parseInt(document.getElementById('barcode_quantity').value) || 5;

let preview = document.getElementById('barcodePreview');
preview.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Generating barcodes...</div>';

// Fetch multiple barcodes via AJAX
fetch('?generate_multiple_preview=1&prefix=' + encodeURIComponent(prefix) + '&quantity=' + quantity)
.then(response => response.json())
.then(data => {
if (data.success) {
let html = '';

data.barcodes.forEach(barcode => {
html += `
<div class="barcode-preview-card">
<div class="item-number">Item ${barcode.index}</div>
<div class="barcode-img">
<img src="data:image/png;base64,${barcode.barcode}" alt="Barcode">
</div>
<div class="barcode-value">${barcode.value}</div>
</div>
`;
});

if (data.total > 10) {
html += `<div style="grid-column: 1/-1; text-align: center; padding: 10px; color: #666;">
<i class="fas fa-info-circle"></i> Showing first 10 of ${data.total} barcodes
</div>`;
}

preview.innerHTML = html;
} else {
preview.innerHTML = '<div class="alert alert-danger">Error: ' + data.error + '</div>';
}
})
.catch(error => {
console.error('Error:', error);
preview.innerHTML = '<div class="alert alert-danger">Error generating barcodes</div>';
});
}

// Helper function to get formatted date
function getFormattedDate() {
let date = new Date();
return date.getFullYear() + 
str_pad(date.getMonth() + 1, 2, '0', 'left') + 
str_pad(date.getDate(), 2, '0', 'left');
}

// Helper function to pad strings
function str_pad(string, length, pad, direction) {
string = String(string);
while (string.length < length) {
if (direction === 'left') {
string = pad + string;
} else {
string = string + pad;
}
}
return string;
}

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
let barcodeOption = document.querySelector('input[name="barcode_option"]:checked').value;

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

if (barcodeOption === 'single') {
let generatedBarcode = document.getElementById('generated_barcode').value;
let manualBarcode = document.getElementById('manual_barcode').value;

if (!generatedBarcode && !manualBarcode) {
e.preventDefault();
alert('Please generate a barcode or enter one manually');
return false;
}
}
});

// Auto-calculate on page load if values exist
window.addEventListener('load', function() {
calculateTotal();
// Generate initial barcode
generateSingleBarcode();
});
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>