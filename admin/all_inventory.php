<?php
/**
 * All Inventory Page (Admin)
 * Complete inventory management system - view and manage all inventory items
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Include barcode library
require_once $root_path . '/vendor/autoload.php';
use Picqer\Barcode\BarcodeGeneratorPNG;

// Require admin role
requireRole('admin');

$page_title = 'All Inventory';
$page_description = 'View and manage all inventory items';

// Pagination variables
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? sanitize($_GET['category']) : '';

// Handle barcode generation via AJAX
if (isset($_GET['generate_barcode'])) {
header('Content-Type: application/json');

$barcode_value = $_GET['barcode_value'] ?? '';

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

for ($i = 0; $i < min($quantity, 10); $i++) {
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

// Handle Add Inventory Item
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
if ($_POST['action'] == 'add') {
$article_name = sanitize($_POST['article_name']);
$description = sanitize($_POST['description']);
$property_no = generatePropertyNo();
$uom = sanitize($_POST['uom']);
$quantity = floatval($_POST['quantity']);
$unit_value = floatval($_POST['unit_value']);
$equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 1;
$section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
$category = sanitize($_POST['category']);
$condition_text = sanitize($_POST['condition']);
$fund_cluster = sanitize($_POST['fund_cluster']);
$remarks = sanitize($_POST['remarks']);
$barcode_data = !empty($_POST['barcode_data']) ? sanitize($_POST['barcode_data']) : null;

// Validate
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
fund_cluster, remarks, created_by, date_added, barcode_data
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
");

$qty_property_card = $quantity;
$qty_physical_count = $quantity;
$created_by = $_SESSION['user_id'];

// FIXED: Corrected bind_param types - added 's' for barcode_data at the end
$stmt->bind_param(
"sssdddiiissssis",
$article_name, $description, $property_no, $uom,
$qty_property_card, $qty_physical_count, $unit_value,
$equipment_id, $section_id, $category, $condition_text,
$fund_cluster, $remarks, $created_by, $barcode_data
);

if ($stmt->execute()) {
$inventory_id = $stmt->insert_id;
logActivity('Add Inventory', $inventory_id, "Added new item: $article_name");
$_SESSION['success'] = "Inventory item added successfully. Property No: $property_no";
} else {
$_SESSION['error'] = "Database error: " . $conn->error;
}
$stmt->close();
} else {
$_SESSION['error'] = implode("<br>", $errors);
}

header('Location: ' . SITE_URL . '/admin/all_inventory.php');
exit();
}

// Handle Edit Inventory Item
elseif ($_POST['action'] == 'edit' && isset($_POST['id'])) {
$id = (int)$_POST['id'];
$article_name = sanitize($_POST['article_name']);
$description = sanitize($_POST['description']);
$uom = sanitize($_POST['uom']);
$quantity = floatval($_POST['quantity']);
$unit_value = floatval($_POST['unit_value']);
$equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 1;
$section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
$category = sanitize($_POST['category']);
$condition_text = sanitize($_POST['condition']);
$fund_cluster = sanitize($_POST['fund_cluster']);
$remarks = sanitize($_POST['remarks']);
$barcode_data = !empty($_POST['barcode_data']) ? sanitize($_POST['barcode_data']) : null;

// FIXED: Corrected bind_param types and count
$stmt = $conn->prepare("
UPDATE inventory SET 
article_name = ?, description = ?, uom = ?,
qty_physical_count = ?, unit_value = ?,
equipment_id = ?, section_id = ?, category = ?,
condition_text = ?, fund_cluster = ?, remarks = ?, 
barcode_data = ?, date_updated = NOW()
WHERE id = ?
");

$stmt->bind_param(
"sssddiisssssi",
$article_name, $description, $uom,
$quantity, $unit_value,
$equipment_id, $section_id, $category,
$condition_text, $fund_cluster, $remarks,
$barcode_data, $id
);

if ($stmt->execute()) {
logActivity('Edit Inventory', $id, "Edited item: $article_name");
$_SESSION['success'] = "Inventory item updated successfully";
} else {
$_SESSION['error'] = "Error updating item: " . $conn->error;
}
$stmt->close();

header('Location: ' . SITE_URL . '/admin/all_inventory.php');
exit();
}

// Handle Add Multiple Barcodes to Existing Item
elseif ($_POST['action'] == 'add_multiple_barcodes' && isset($_POST['parent_id'])) {
$parent_id = (int)$_POST['parent_id'];
$barcode_prefix = sanitize($_POST['barcode_prefix'] ?? 'INV');
$barcode_quantity = intval($_POST['barcode_quantity'] ?? 1);

// Get the original item details
$result = $conn->query("SELECT * FROM inventory WHERE id = $parent_id");
$original_item = $result->fetch_assoc();

if (!$original_item) {
$_SESSION['error'] = "Parent item not found";
header('Location: ' . SITE_URL . '/admin/all_inventory.php');
exit();
}

$errors = [];
if ($barcode_quantity < 1) {
$errors[] = "Number of barcodes must be at least 1";
}

if (empty($errors)) {
$conn->begin_transaction();

try {
$base_barcode = $barcode_prefix . date('Ymd');
$created_by = $_SESSION['user_id'];

// Find the highest existing suffix for this base property
$base_property = preg_replace('/-\d+$/', '', $original_item['property_no']);
$result = $conn->query("SELECT property_no FROM inventory WHERE property_no LIKE '$base_property-%' ORDER BY property_no DESC LIMIT 1");
$last_suffix = 0;
if ($result && $row = $result->fetch_assoc()) {
$last_suffix = intval(preg_replace('/.*-/', '', $row['property_no']));
}

for ($i = 0; $i < $barcode_quantity; $i++) {
$randomPart = rand(1000, 9999);
$barcode_data = $base_barcode . $randomPart . str_pad($last_suffix + $i + 1, 3, '0', STR_PAD_LEFT);

// Check if barcode already exists
$check = $conn->query("SELECT id FROM inventory WHERE barcode_data = '$barcode_data'");
if ($check && $check->num_rows > 0) {
throw new Exception("Barcode '$barcode_data' already exists in the system");
}

$stmt = $conn->prepare("
INSERT INTO inventory (
article_name, description, property_no, uom, 
qty_property_card, qty_physical_count, unit_value,
equipment_id, section_id, category, condition_text,
fund_cluster, remarks, created_by, date_added, barcode_data
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), ?)
");

$property_no_with_suffix = $base_property . '-' . ($last_suffix + $i + 1);

// FIXED: Corrected bind_param types
$stmt->bind_param(
"sssdddiiissssis",
$original_item['article_name'], 
$original_item['description'], 
$property_no_with_suffix, 
$original_item['uom'],
$original_item['qty_property_card'], 
$original_item['qty_physical_count'], 
$original_item['unit_value'],
$original_item['equipment_id'], 
$original_item['section_id'], 
$original_item['category'], 
$original_item['condition_text'],
$original_item['fund_cluster'], 
$original_item['remarks'], 
$created_by, 
$barcode_data
);

if (!$stmt->execute()) {
throw new Exception($conn->error);
}
$stmt->close();
}

$conn->commit();
logActivity('Add Multiple Barcodes', $parent_id, "Added $barcode_quantity barcode(s) to item: " . $original_item['article_name']);
$_SESSION['success'] = "$barcode_quantity new barcode items added successfully";

} catch (Exception $e) {
$conn->rollback();
$_SESSION['error'] = "Error: " . $e->getMessage();
}
} else {
$_SESSION['error'] = implode("<br>", $errors);
}

header('Location: ' . SITE_URL . '/admin/all_inventory.php');
exit();
}
}

// Handle Delete Inventory Item
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
$id = (int)$_GET['delete'];

// Check if item is issued
$check = $conn->query("SELECT id FROM equipment_issuance WHERE inventory_id = $id AND status = 'issued'");
if ($check && $check->num_rows > 0) {
$_SESSION['error'] = "Cannot delete item that is currently issued";
} else {
$conn->query("DELETE FROM inventory WHERE id = $id");
if ($conn->affected_rows > 0) {
logActivity('Delete Inventory', $id, "Deleted inventory item ID: $id");
$_SESSION['success'] = "Inventory item deleted successfully";
}
}

header('Location: ' . SITE_URL . '/admin/all_inventory.php');
exit();
}

// Build query for all inventory items
$query = "
SELECT i.*, e.name as equipment_name, s.name as section_name,
CASE WHEN EXISTS (
SELECT 1 FROM equipment_issuance 
WHERE inventory_id = i.id AND status = 'issued' 
LIMIT 1
) THEN 1 ELSE 0 END as is_issued,
CASE WHEN i.property_no LIKE '%-%' THEN 1 ELSE 0 END as is_multiple
FROM inventory i
LEFT JOIN equipment e ON i.equipment_id = e.id
LEFT JOIN sections s ON i.section_id = s.id
WHERE 1=1
";

if (!empty($search)) {
$search_escaped = $conn->real_escape_string($search);
$query .= " AND (i.article_name LIKE '%$search_escaped%' 
OR i.property_no LIKE '%$search_escaped%'
OR i.description LIKE '%$search_escaped%')";
}

if (!empty($category_filter)) {
$category_escaped = $conn->real_escape_string($category_filter);
$query .= " AND i.category = '$category_escaped'";
}

$query .= " ORDER BY i.date_added DESC";

// Get paginated results
$inventory_items = paginate($query, $page, $per_page);

// Get unique categories for filter
$categories = $conn->query("SELECT DISTINCT category FROM inventory WHERE category IS NOT NULL AND category != '' ORDER BY category");

// Get equipment types for dropdown
$equipment = $conn->query("SELECT * FROM equipment ORDER BY name");

// Get sections for dropdown
$sections = $conn->query("
SELECT s.*, d.name as department_name 
FROM sections s
LEFT JOIN departments d ON s.department_id = d.id
ORDER BY d.name, s.name
");

// Get item for editing if ID is provided
$edit_item = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
$edit_id = (int)$_GET['edit'];
$result = $conn->query("SELECT * FROM inventory WHERE id = $edit_id");
$edit_item = $result->fetch_assoc();
}

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

<!-- Statistics Cards for All Inventory -->
<div class="dashboard-cards">
<div class="card">
<div class="card-icon">
<i class="fas fa-boxes"></i>
</div>
<h3>Total Items</h3>
<?php
$result = $conn->query("SELECT COUNT(*) as count FROM inventory");
$total_items = $result ? $result->fetch_assoc()['count'] : 0;
?>
<div class="card-value"><?php echo $total_items; ?></div>
<div class="card-label">All inventory items</div>
</div>

<div class="card">
<div class="card-icon">
<i class="fas fa-hand-holding"></i>
</div>
<h3>Issued Items</h3>
<?php
$result = $conn->query("SELECT COUNT(DISTINCT inventory_id) as count FROM equipment_issuance WHERE status = 'issued'");
$issued_items = $result ? $result->fetch_assoc()['count'] : 0;
?>
<div class="card-value"><?php echo $issued_items; ?></div>
<div class="card-label">Currently issued</div>
</div>

<div class="card">
<div class="card-icon">
<i class="fas fa-exclamation-triangle"></i>
</div>
<h3>Low Stock</h3>
<?php
$result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE qty_physical_count <= 5");
$low_stock = $result ? $result->fetch_assoc()['count'] : 0;
?>
<div class="card-value <?php echo $low_stock > 0 ? 'text-warning' : ''; ?>"><?php echo $low_stock; ?></div>
<div class="card-label">Need reorder</div>
</div>

<div class="card">
<div class="card-icon">
<i class="fas fa-dollar-sign"></i>
</div>
<h3>Total Value</h3>
<?php
$result = $conn->query("SELECT SUM(unit_value * qty_physical_count) as total FROM inventory");
$total_value = $result ? $result->fetch_assoc()['total'] : 0;
?>
<div class="card-value"><?php echo formatCurrency($total_value ?? 0); ?></div>
<div class="card-label">Inventory value</div>
</div>
</div>

<!-- Quick Actions -->
<div class="table-container">
<div class="table-header">
<h2><i class="fas fa-bolt"></i> Quick Actions</h2>
</div>
<div style="display: flex; gap: 10px; flex-wrap: wrap;">
<button class="btn btn-primary" onclick="openAddModal()">
<i class="fas fa-plus-circle"></i> Add New Item
</button>
<a href="<?php echo SITE_URL; ?>/admin/issue_items.php" class="btn btn-primary">
<i class="fas fa-hand-holding"></i> Issue Items
</a>
<a href="<?php echo SITE_URL; ?>/admin/barcode_scanner.php" class="btn btn-primary">
<i class="fas fa-hand-holding"></i> SCAN BARCODE HERE 
</a>
<a href="<?php echo SITE_URL; ?>/admin/all_inventory.php?export=1" class="btn btn-secondary">
<i class="fas fa-download"></i> Export Inventory
</a>
<a href="<?php echo SITE_URL; ?>/admin/ppe_equipment.php" class="btn btn-secondary">
<i class="fas fa-shield-alt"></i> View PPE
</a>
<a href="<?php echo SITE_URL; ?>/admin/semi_expendable.php" class="btn btn-secondary">
<i class="fas fa-box-open"></i> View Semi-Expendable
</a>
</div>
</div>

<!-- Search and Filter -->
<div class="table-container">
<div class="table-header">
<h2><i class="fas fa-search"></i> Search & Filter Inventory</h2>
</div>
<form method="GET" action="" class="search-box" style="flex-wrap: wrap;">
<input type="text" name="search" placeholder="Search by article name, property no., or description..." 
value="<?php echo htmlspecialchars($search); ?>" style="flex: 2;">

<select name="category" style="padding: 12px; border: 1px solid var(--light); border-radius: 8px;">
<option value="">All Categories</option>
<?php if ($categories && $categories->num_rows > 0): 
mysqli_data_seek($categories, 0);
while($cat = $categories->fetch_assoc()): ?>
<option value="<?php echo htmlspecialchars($cat['category']); ?>" <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
<?php echo htmlspecialchars($cat['category']); ?>
</option>
<?php endwhile; endif; ?>
</select>

<button type="submit">
<i class="fas fa-search"></i> Search
</button>

<?php if ($search || $category_filter): ?>
<a href="<?php echo SITE_URL; ?>/admin/all_inventory.php" class="btn btn-secondary">
<i class="fas fa-times"></i> Clear Filters
</a>
<?php endif; ?>
</form>
</div>

<!-- Inventory Items Table -->
<div class="table-container">
<div class="table-header">
<h2><i class="fas fa-boxes"></i> All Inventory Items</h2>
<p>Showing <?php echo count($inventory_items['data']); ?> of <?php echo $inventory_items['total_rows']; ?> items</p>
</div>

<div style="overflow-x: auto;">
<table style="min-width: 1500px;">
<thead>
<tr>
<th>Article Name</th>
<th>Property No.</th>
<th style="min-width: 180px;">Barcode</th>
<th>Category</th>
<th>Quantity</th>
<th>Unit Value</th>
<th>Location</th>
<th>Condition</th>
<th>Status</th>
<th style="min-width: 150px;">Actions</th>
</tr>
</thead>
<tbody>
<?php if (count($inventory_items['data']) > 0): ?>
<?php foreach ($inventory_items['data'] as $item): ?>
<tr class="<?php echo $item['qty_physical_count'] <= 5 ? 'stock-alert-row' : ''; ?>">
<td>
<strong><?php echo htmlspecialchars($item['article_name']); ?></strong>
<?php if (!empty($item['description'])): ?>
<br><small><?php echo htmlspecialchars(substr($item['description'], 0, 50)) . '...'; ?></small>
<?php endif; ?>
</td>
<td><?php echo htmlspecialchars($item['property_no']); ?></td>

<!-- Barcode Column -->
<td style="text-align: center;">
<?php if (!empty($item['barcode_data'])): ?>
<div style="display: flex; flex-direction: column; align-items: center;">
<img src="generate_barcode.php?code=<?php echo urlencode($item['barcode_data']); ?>&width=150&height=40" 
alt="Barcode" 
style="max-width: 150px; height: auto; cursor: pointer; border: 1px solid #eee; padding: 5px; border-radius: 4px;"
onclick="showBarcodeModal('<?php echo $item['barcode_data']; ?>', '<?php echo htmlspecialchars(addslashes($item['article_name'])); ?>')"
onmouseover="this.style.borderColor='#F16D34'"
onmouseout="this.style.borderColor='#eee'">
<small style="font-family: monospace; font-size: 10px; color: #666; margin-top: 3px;">
<?php echo htmlspecialchars($item['barcode_data']); ?>
</small>
<div style="margin-top: 3px;">
<button class="btn btn-xs btn-secondary" 
onclick="printBarcode('<?php echo $item['barcode_data']; ?>', '<?php echo htmlspecialchars(addslashes($item['article_name'])); ?>')"
style="padding: 2px 8px; font-size: 11px;">
<i class="fas fa-print"></i>
</button>
</div>
</div>
<?php else: ?>
<span class="text-muted" style="font-style: italic;">No barcode</span>
<?php endif; ?>
</td>

<td>
<?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?>
<br><small><?php echo htmlspecialchars($item['equipment_name'] ?? 'N/A'); ?></small>
</td>
<td>
<?php echo $item['qty_physical_count'] . ' ' . $item['uom']; ?>
<?php if ($item['qty_physical_count'] <= 5): ?>
<br><span class="badge badge-warning">Low Stock</span>
<?php endif; ?>
</td>
<td><?php echo formatCurrency($item['unit_value']); ?></td>
<td><?php echo htmlspecialchars($item['section_name'] ?? 'N/A'); ?></td>
<td><?php echo htmlspecialchars($item['condition_text'] ?? 'Good'); ?></td>
<td>
<?php if ($item['is_issued'] > 0): ?>
<span class="badge badge-warning">Issued</span>
<?php else: ?>
<span class="badge badge-success">Available</span>
<?php endif; ?>
</td>
<td>
<div class="action-buttons">
<a href="?edit=<?php echo $item['id']; ?>" class="action-btn edit" title="Edit">
<i class="fas fa-edit"></i>
</a>
<button class="action-btn view" onclick="viewItem(<?php echo $item['id']; ?>)" title="View">
<i class="fas fa-eye"></i>
</button>
<?php if (!$item['is_multiple']): ?>
<button class="action-btn barcode" onclick="openAddBarcodeModal(<?php echo $item['id']; ?>, '<?php echo htmlspecialchars(addslashes($item['article_name'])); ?>')" title="Add Barcodes" style="background-color: #2196F3;">
<i class="fas fa-barcode"></i>
</button>
<?php endif; ?>
<?php if ($item['is_issued'] == 0): ?>
<a href="?delete=<?php echo $item['id']; ?>" 
class="action-btn delete" 
onclick="return confirm('Are you sure you want to delete this item?')"
title="Delete">
<i class="fas fa-trash"></i>
</a>
<?php endif; ?>
<a href="<?php echo SITE_URL; ?>/admin/issue_items.php?item=<?php echo $item['id']; ?>" 
class="action-btn success" title="Issue">
<i class="fas fa-hand-holding"></i>
</a>
</div>
</td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr>
<td colspan="10" class="text-center">
<i class="fas fa-boxes" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
<br>
No inventory items found
<br>
<button class="btn btn-primary mt-3" onclick="openAddModal()">
<i class="fas fa-plus"></i> Add Your First Item
</button>
</td>
</tr>
<?php endif; ?>
</tbody>
</table>
</div>

<!-- Pagination -->
<?php 
$pagination_url = '?page=';
if (!empty($search)) $pagination_url .= '&search=' . urlencode($search);
if (!empty($category_filter)) $pagination_url .= '&category=' . urlencode($category_filter);
echo displayPagination($inventory_items, $pagination_url); 
?>
</div>

<!-- Add/Edit Inventory Modal -->
<div id="inventoryModal" class="modal" style="display: <?php echo $edit_item ? 'block' : 'none'; ?>;">
<div class="modal-content" style="max-width: 800px; max-height: 90vh; display: flex; flex-direction: column;">
<div class="modal-header" style="flex-shrink: 0;">
<h2 id="modalTitle"><?php echo $edit_item ? 'Edit Inventory Item' : 'Add New Inventory Item'; ?></h2>
<span class="modal-close" onclick="closeModal()">&times;</span>
</div>
<div class="modal-body" style="overflow-y: auto; flex: 1; padding: 20px;">
<form method="POST" action="" id="inventoryForm">
<input type="hidden" name="action" value="<?php echo $edit_item ? 'edit' : 'add'; ?>">
<?php if ($edit_item): ?>
<input type="hidden" name="id" value="<?php echo $edit_item['id']; ?>">
<?php endif; ?>

<div class="form-group">
<label for="article_name">Article Name <span class="text-danger">*</span></label>
<input type="text" class="form-control" id="article_name" name="article_name" 
value="<?php echo $edit_item ? htmlspecialchars($edit_item['article_name']) : ''; ?>" 
required maxlength="255" placeholder="Enter item name">
</div>

<div class="form-group">
<label for="description">Description</label>
<textarea class="form-control" id="description" name="description" rows="2" 
placeholder="Enter detailed description"><?php echo $edit_item ? htmlspecialchars($edit_item['description']) : ''; ?></textarea>
</div>

<div class="form-row">
<div class="form-group">
<label for="category">Category <span class="text-danger">*</span></label>
<select class="form-control" id="category" name="category" required>
<option value="">-- Select Category --</option>
<option value="ICT Equipment" <?php echo ($edit_item && $edit_item['category'] == 'ICT Equipment') ? 'selected' : ''; ?>>ICT Equipment</option>
<option value="Medical Equipment" <?php echo ($edit_item && $edit_item['category'] == 'Medical Equipment') ? 'selected' : ''; ?>>Medical Equipment</option>
<option value="Office Supplies" <?php echo ($edit_item && $edit_item['category'] == 'Office Supplies') ? 'selected' : ''; ?>>Office Supplies</option>
<option value="PPE" <?php echo ($edit_item && $edit_item['category'] == 'PPE') ? 'selected' : ''; ?>>PPE</option>
<option value="Semi-Expendable" <?php echo ($edit_item && $edit_item['category'] == 'Semi-Expendable') ? 'selected' : ''; ?>>Semi-Expendable</option>
<option value="Furniture" <?php echo ($edit_item && $edit_item['category'] == 'Furniture') ? 'selected' : ''; ?>>Furniture</option>
<option value="Tools" <?php echo ($edit_item && $edit_item['category'] == 'Tools') ? 'selected' : ''; ?>>Tools</option>
<option value="Other" <?php echo ($edit_item && $edit_item['category'] == 'Other') ? 'selected' : ''; ?>>Other</option>
</select>
</div>

<div class="form-group">
<label for="uom">Unit of Measurement <span class="text-danger">*</span></label>
<select class="form-control" id="uom" name="uom" required>
<option value="">-- Select UOM --</option>
<option value="pcs" <?php echo ($edit_item && $edit_item['uom'] == 'pcs') ? 'selected' : ''; ?>>Pieces (pcs)</option>
<option value="box" <?php echo ($edit_item && $edit_item['uom'] == 'box') ? 'selected' : ''; ?>>Box</option>
<option value="unit" <?php echo ($edit_item && $edit_item['uom'] == 'unit') ? 'selected' : ''; ?>>Unit</option>
<option value="set" <?php echo ($edit_item && $edit_item['uom'] == 'set') ? 'selected' : ''; ?>>Set</option>
<option value="pair" <?php echo ($edit_item && $edit_item['uom'] == 'pair') ? 'selected' : ''; ?>>Pair</option>
<option value="meter" <?php echo ($edit_item && $edit_item['uom'] == 'meter') ? 'selected' : ''; ?>>Meter</option>
<option value="liter" <?php echo ($edit_item && $edit_item['uom'] == 'liter') ? 'selected' : ''; ?>>Liter</option>
<option value="kg" <?php echo ($edit_item && $edit_item['uom'] == 'kg') ? 'selected' : ''; ?>>Kilogram (kg)</option>
</select>
</div>
</div>

<div class="form-row">
<div class="form-group">
<label for="quantity">Quantity <span class="text-danger">*</span></label>
<input type="number" class="form-control" id="quantity" name="quantity" 
value="<?php echo $edit_item ? $edit_item['qty_physical_count'] : '1'; ?>" 
min="0.01" step="0.01" required>
</div>

<div class="form-group">
<label for="unit_value">Unit Value (₱) <span class="text-danger">*</span></label>
<input type="number" class="form-control" id="unit_value" name="unit_value" 
value="<?php echo $edit_item ? $edit_item['unit_value'] : ''; ?>" 
min="0.01" step="0.01" required placeholder="0.00">
</div>
</div>

<div class="form-row">
<div class="form-group">
<label for="total_value">Total Value</label>
<input type="text" class="form-control" id="total_value" readonly placeholder="₱0.00">
</div>

<div class="form-group">
<label for="equipment_id">Equipment Type</label>
<select class="form-control" id="equipment_id" name="equipment_id">
<option value="">-- Select Equipment Type --</option>
<?php if ($equipment && $equipment->num_rows > 0): 
mysqli_data_seek($equipment, 0); 
while($eq = $equipment->fetch_assoc()): ?>
<option value="<?php echo $eq['id']; ?>" 
<?php echo ($edit_item && $edit_item['equipment_id'] == $eq['id']) ? 'selected' : ''; ?>>
<?php echo htmlspecialchars($eq['name']); ?>
</option>
<?php endwhile; endif; ?>
</select>
</div>
</div>

<div class="form-row">
<div class="form-group">
<label for="section_id">Location (Section)</label>
<select class="form-control" id="section_id" name="section_id">
<option value="">-- Select Location --</option>
<?php if ($sections && $sections->num_rows > 0): 
mysqli_data_seek($sections, 0); 
while($sec = $sections->fetch_assoc()): ?>
<option value="<?php echo $sec['id']; ?>" 
<?php echo ($edit_item && $edit_item['section_id'] == $sec['id']) ? 'selected' : ''; ?>>
<?php echo htmlspecialchars(($sec['department_name'] ?? '') . ' - ' . $sec['name']); ?>
</option>
<?php endwhile; endif; ?>
</select>
</div>

<div class="form-group">
<label for="condition">Condition</label>
<select class="form-control" id="condition" name="condition">
<option value="New" <?php echo ($edit_item && $edit_item['condition_text'] == 'New') ? 'selected' : ''; ?>>New</option>
<option value="Good" <?php echo ($edit_item && $edit_item['condition_text'] == 'Good') ? 'selected' : ''; ?>>Good</option>
<option value="Fair" <?php echo ($edit_item && $edit_item['condition_text'] == 'Fair') ? 'selected' : ''; ?>>Fair</option>
<option value="Poor" <?php echo ($edit_item && $edit_item['condition_text'] == 'Poor') ? 'selected' : ''; ?>>Poor</option>
</select>
</div>
</div>

<div class="form-group">
<label for="fund_cluster">Fund Cluster</label>
<input type="text" class="form-control" id="fund_cluster" name="fund_cluster" 
value="<?php echo $edit_item ? htmlspecialchars($edit_item['fund_cluster']) : ''; ?>"
placeholder="e.g., General Fund">
</div>

<div class="form-group">
<label for="remarks">Remarks</label>
<textarea class="form-control" id="remarks" name="remarks" rows="2" 
placeholder="Any additional notes"><?php echo $edit_item ? htmlspecialchars($edit_item['remarks']) : ''; ?></textarea>
</div>

<!-- Barcode Field -->
<div class="form-group">
<label for="barcode_data">Barcode</label>
<div class="barcode-input-group">
<input type="text" class="form-control" id="barcode_data" name="barcode_data" 
value="<?php echo $edit_item ? htmlspecialchars($edit_item['barcode_data']) : ''; ?>"
placeholder="Enter or generate barcode">
<button type="button" class="btn btn-secondary" onclick="generateBarcodeForEdit()" style="margin-top: 5px;">
<i class="fas fa-sync-alt"></i> Generate Barcode
</button>
</div>
<div id="barcodePreview" class="barcode-preview" style="margin-top: 10px; text-align: center;"></div>
</div>

<div class="form-group" style="margin-top: 20px; padding-top: 10px; border-top: 1px solid var(--light);">
<button type="submit" class="btn btn-primary" style="min-width: 150px;">
<i class="fas fa-save"></i> <?php echo $edit_item ? 'Update Item' : 'Save Item'; ?>
</button>
<button type="button" class="btn btn-secondary" onclick="closeModal()" style="margin-left: 10px;">
<i class="fas fa-times"></i> Cancel
</button>
</div>
</form>
</div>
</div>
</div>

<!-- Add Multiple Barcodes Modal -->
<div id="addBarcodeModal" class="modal">
<div class="modal-content" style="max-width: 600px;">
<div class="modal-header">
<h2 id="addBarcodeModalTitle">Add Multiple Barcodes</h2>
<span class="modal-close" onclick="closeAddBarcodeModal()">&times;</span>
</div>
<div class="modal-body" style="padding: 20px;">
<form method="POST" action="" id="addBarcodeForm">
<input type="hidden" name="action" value="add_multiple_barcodes">
<input type="hidden" name="parent_id" id="parent_id" value="">

<div class="form-group">
<label for="barcode_prefix">Barcode Prefix</label>
<input type="text" class="form-control" id="barcode_prefix" name="barcode_prefix" value="INV" maxlength="10">
<small class="form-text text-muted">Prefix for the barcode (e.g., INV)</small>
</div>

<div class="form-group">
<label for="barcode_quantity">Number of Barcodes to Generate</label>
<input type="number" class="form-control" id="barcode_quantity" name="barcode_quantity" value="5" min="1" max="50">
</div>

<div class="form-group">
<button type="button" class="btn btn-secondary" onclick="previewBarcodes()">
<i class="fas fa-eye"></i> Preview Barcodes
</button>
</div>

<div id="barcodePreviewGrid" class="barcode-preview-grid"></div>

<div class="form-group" style="margin-top: 20px;">
<button type="submit" class="btn btn-primary">
<i class="fas fa-plus"></i> Add Barcodes
</button>
<button type="button" class="btn btn-secondary" onclick="closeAddBarcodeModal()">
<i class="fas fa-times"></i> Cancel
</button>
</div>
</form>
</div>
</div>
</div>

<!-- View Item Modal -->
<div id="viewModal" class="modal">
<div class="modal-content" style="max-width: 600px; max-height: 80vh; display: flex; flex-direction: column;">
<div class="modal-header" style="flex-shrink: 0;">
<h2>Item Details</h2>
<span class="modal-close" onclick="closeViewModal()">&times;</span>
</div>
<div class="modal-body" id="viewModalContent" style="overflow-y: auto; flex: 1; padding: 20px;"></div>
<div class="modal-footer" style="flex-shrink: 0; padding: 15px 20px; border-top: 1px solid var(--light); text-align: right;">
<button type="button" class="btn btn-secondary" onclick="closeViewModal()">Close</button>
</div>
</div>
</div>

<!-- Barcode Modal -->
<div id="barcodeModal" class="modal">
<div class="modal-content" style="max-width: 400px; text-align: center;">
<div class="modal-header">
<h2 id="barcodeModalTitle">Barcode</h2>
<span class="modal-close" onclick="closeBarcodeModal()">&times;</span>
</div>
<div class="modal-body" style="padding: 20px;">
<div id="barcodeModalImage"></div>
<div id="barcodeModalNumber" style="font-family: monospace; margin-top: 10px;"></div>
<div style="margin-top: 20px;">
<button class="btn btn-primary" onclick="printCurrentBarcode()">
<i class="fas fa-print"></i> Print
</button>
</div>
</div>
</div>
</div>

<style>
/* Barcode column styling */
.btn-xs {
padding: 2px 8px;
font-size: 11px;
border-radius: 3px;
background-color: #6c757d;
color: white;
border: none;
cursor: pointer;
}

.btn-xs:hover {
background-color: #5a6268;
}

.btn-primary {
background-color: #F16D34;
color: white;
border: none;
padding: 8px 16px;
border-radius: 5px;
cursor: pointer;
}

.btn-primary:hover {
background-color: #d55a2a;
}

.btn-secondary {
background-color: #6c757d;
color: white;
border: none;
padding: 8px 16px;
border-radius: 5px;
cursor: pointer;
}

.btn-secondary:hover {
background-color: #5a6268;
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

.modal-close {
font-size: 24px;
font-weight: bold;
cursor: pointer;
color: #999;
}

.modal-close:hover {
color: #F16D34;
}

/* Badge styles */
.badge-warning {
background-color: #ffc107;
color: #212529;
padding: 3px 8px;
border-radius: 12px;
font-size: 11px;
font-weight: 600;
}

.badge-success {
background-color: #28a745;
color: white;
padding: 3px 8px;
border-radius: 12px;
font-size: 11px;
font-weight: 600;
}

.text-muted {
color: #6c757d;
font-style: italic;
}

/* Action buttons */
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
border-radius: 5px;
color: white;
text-decoration: none;
transition: all 0.3s;
}

.action-btn.edit { background-color: #FF986A; }
.action-btn.view { background-color: #161E54; }
.action-btn.delete { background-color: #dc3545; }
.action-btn.success { background-color: #28a745; }
.action-btn.barcode { background-color: #2196F3; }

.action-btn:hover {
transform: translateY(-2px);
box-shadow: 0 3px 8px rgba(0,0,0,0.2);
}

/* Barcode preview grid */
.barcode-preview-grid {
display: grid;
grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
gap: 15px;
margin-top: 20px;
max-height: 400px;
overflow-y: auto;
padding: 15px;
background: #f8f9fa;
border-radius: 8px;
}

.barcode-preview-card {
background: white;
border: 1px solid #e0e0e0;
border-radius: 8px;
padding: 15px;
text-align: center;
}

.barcode-preview-card .item-number {
font-size: 14px;
font-weight: bold;
color: #2196F3;
margin-bottom: 10px;
}

.barcode-preview-card .barcode-img img {
max-width: 100%;
height: auto;
}

.barcode-preview-card .barcode-value {
font-family: monospace;
font-size: 11px;
margin-top: 8px;
word-break: break-all;
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
</style>

<script>
// Calculate total value
document.getElementById('quantity')?.addEventListener('input', calculateTotal);
document.getElementById('unit_value')?.addEventListener('input', calculateTotal);

function calculateTotal() {
let quantity = parseFloat(document.getElementById('quantity').value) || 0;
let unitValue = parseFloat(document.getElementById('unit_value').value) || 0;
let total = quantity * unitValue;
document.getElementById('total_value').value = '₱' + total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Open Add Modal
function openAddModal() {
document.getElementById('modalTitle').textContent = 'Add New Inventory Item';
document.getElementById('inventoryForm').reset();
document.querySelector('#inventoryForm input[name="action"]').value = 'add';
document.getElementById('inventoryModal').style.display = 'block';
calculateTotal();
}

// Close Modal
function closeModal() {
document.getElementById('inventoryModal').style.display = 'none';
window.location.href = '<?php echo SITE_URL; ?>/admin/all_inventory.php';
}

// Close View Modal
function closeViewModal() {
document.getElementById('viewModal').style.display = 'none';
}

// Generate barcode for edit form
function generateBarcodeForEdit() {
let prefix = 'INV';
let barcodeValue = prefix + getFormattedDate() + Math.floor(1000 + Math.random() * 9000);

document.getElementById('barcode_data').value = barcodeValue;

// Generate barcode image preview
fetch('?generate_barcode=1&barcode_value=' + encodeURIComponent(barcodeValue))
.then(response => response.json())
.then(data => {
if (data.success) {
document.getElementById('barcodePreview').innerHTML = '<img src="data:image/png;base64,' + data.barcode + '" alt="Barcode" style="max-width: 200px;">';
}
})
.catch(error => {
console.error('Error:', error);
});
}

// Open Add Barcode Modal
function openAddBarcodeModal(itemId, itemName) {
document.getElementById('parent_id').value = itemId;
document.getElementById('addBarcodeModalTitle').textContent = 'Add Barcodes to ' + itemName;
document.getElementById('addBarcodeModal').style.display = 'block';
}

// Close Add Barcode Modal
function closeAddBarcodeModal() {
document.getElementById('addBarcodeModal').style.display = 'none';
document.getElementById('barcodePreviewGrid').innerHTML = '';
}

// Preview barcodes
function previewBarcodes() {
let prefix = document.getElementById('barcode_prefix').value;
let quantity = parseInt(document.getElementById('barcode_quantity').value) || 5;

let preview = document.getElementById('barcodePreviewGrid');
preview.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Generating barcodes...</div>';

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

// Barcode Modal Functions
function showBarcodeModal(barcodeData, itemName) {
document.getElementById('barcodeModalTitle').textContent = 'Barcode - ' + itemName;
document.getElementById('barcodeModalImage').innerHTML = '<img src="generate_barcode.php?code=' + encodeURIComponent(barcodeData) + '&width=300&height=80" alt="Barcode" style="max-width: 100%;">';
document.getElementById('barcodeModalNumber').textContent = barcodeData;
document.getElementById('barcodeModal').style.display = 'block';
}

function closeBarcodeModal() {
document.getElementById('barcodeModal').style.display = 'none';
}

function printCurrentBarcode() {
let barcodeData = document.getElementById('barcodeModalNumber').textContent;
let itemName = document.getElementById('barcodeModalTitle').textContent.replace('Barcode - ', '');
printBarcode(barcodeData, itemName);
}

function printBarcode(barcodeData, itemName) {
let printWindow = window.open('', '_blank');
printWindow.document.write(`
<html>
<head>
<title>Print Barcode - ${itemName}</title>
<style>
body { text-align: center; font-family: Arial, sans-serif; margin: 0; padding: 20px; }
.barcode-container { margin: 20px auto; padding: 20px; }
.barcode-img { max-width: 100%; height: auto; }
.item-name { margin-top: 10px; font-size: 14px; font-weight: bold; }
.barcode-number { font-family: monospace; font-size: 12px; margin-top: 5px; }
@media print {
body { margin: 0; padding: 10px; }
}
</style>
</head>
<body>
<div class="barcode-container">
<img src="generate_barcode.php?code=${encodeURIComponent(barcodeData)}&width=400&height=100" 
class="barcode-img" alt="Barcode">
<div class="item-name">${itemName}</div>
<div class="barcode-number">${barcodeData}</div>
</div>
<script>
window.onload = function() { window.print(); window.close(); }
<\/script>
</body>
</html>
`);
printWindow.document.close();
}

// View Item Details
function viewItem(itemId) {
fetch('<?php echo SITE_URL; ?>/api/get_item_details.php?id=' + itemId)
.then(response => response.json())
.then(data => {
let content = `
<div style="margin-bottom: 20px;">
<h3>${data.article_name}</h3>

${data.barcode_data ? `
<div style="text-align: center; margin: 20px 0; padding: 20px; background: #f8f9fa; border-radius: 5px;">
<strong>Barcode:</strong><br>
<img src="generate_barcode.php?code=${encodeURIComponent(data.barcode_data)}&width=300&height=80" 
alt="Barcode" style="max-width: 100%; height: auto; margin: 10px 0;">
<br>
<span style="font-family: monospace; font-size: 16px;">${data.barcode_data}</span>
<br>
<button class="btn btn-primary" onclick="printBarcode('${data.barcode_data}', '${data.article_name}')" style="margin-top: 10px;">
<i class="fas fa-print"></i> Print Barcode
</button>
</div>
` : ''}

<table style="width: 100%; border-collapse: collapse;">
<tr><td><strong>Property No:</strong></td><td>${data.property_no || 'N/A'}</td></tr>
<tr><td><strong>Description:</strong></td><td>${data.description || 'N/A'}</td></tr>
<tr><td><strong>Category:</strong></td><td>${data.category || 'N/A'}</td></tr>
<tr><td><strong>Equipment Type:</strong></td><td>${data.equipment_name || 'N/A'}</td></tr>
<tr><td><strong>Quantity:</strong></td><td>${data.qty_physical_count} ${data.uom}</td></tr>
<tr><td><strong>Unit Value:</strong></td><td>${formatCurrency(data.unit_value)}</td></tr>
<tr><td><strong>Total Value:</strong></td><td>${formatCurrency(data.unit_value * data.qty_physical_count)}</td></tr>
<tr><td><strong>Location:</strong></td><td>${data.section_name || 'N/A'}</td></tr>
<tr><td><strong>Condition:</strong></td><td>${data.condition_text || 'Good'}</td></tr>
<tr><td><strong>Fund Cluster:</strong></td><td>${data.fund_cluster || 'N/A'}</td></tr>
<tr><td><strong>Date Added:</strong></td><td>${formatDate(data.date_added)}</td></tr>
<tr><td><strong>Remarks:</strong></td><td>${data.remarks || 'N/A'}</td></tr>
</table>
</div>
`;
document.getElementById('viewModalContent').innerHTML = content;
document.getElementById('viewModal').style.display = 'block';
})
.catch(error => {
alert('Error loading item details');
});
}

function formatCurrency(amount) {
return '₱' + parseFloat(amount || 0).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function formatDate(dateString) {
if (!dateString) return 'N/A';
let date = new Date(dateString);
return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
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

// Auto-calculate on page load if editing
window.addEventListener('load', function() {
<?php if ($edit_item): ?>
calculateTotal();
if (document.getElementById('barcode_data').value) {
generateBarcodeForEdit();
}
<?php endif; ?>
});

// Close modal when clicking outside
window.onclick = function(event) {
let inventoryModal = document.getElementById('inventoryModal');
let viewModal = document.getElementById('viewModal');
let barcodeModal = document.getElementById('barcodeModal');
let addBarcodeModal = document.getElementById('addBarcodeModal');

if (event.target == inventoryModal) closeModal();
if (event.target == viewModal) closeViewModal();
if (event.target == barcodeModal) closeBarcodeModal();
if (event.target == addBarcodeModal) closeAddBarcodeModal();
}
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>