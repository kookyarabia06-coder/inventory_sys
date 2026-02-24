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

// Require admin role
requireRole('admin');

$page_title = 'All Inventory';
$page_description = 'View and manage all inventory items';

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
                    fund_cluster, remarks, created_by, date_added
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $qty_property_card = $quantity;
            $qty_physical_count = $quantity;
            $created_by = $_SESSION['user_id'];
            
            $stmt->bind_param(
                "sssdddiiissssi",
                $article_name, $description, $property_no, $uom,
                $qty_property_card, $qty_physical_count, $unit_value,
                $equipment_id, $section_id, $category, $condition_text,
                $fund_cluster, $remarks, $created_by
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
        
        $stmt = $conn->prepare("
            UPDATE inventory SET 
                article_name = ?, description = ?, uom = ?,
                qty_physical_count = ?, unit_value = ?,
                equipment_id = ?, section_id = ?, category = ?,
                condition_text = ?, fund_cluster = ?, remarks = ?, 
                date_updated = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param(
            "sssddiissssi",
            $article_name, $description, $uom,
            $quantity, $unit_value,
            $equipment_id, $section_id, $category,
            $condition_text, $fund_cluster, $remarks, $id
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

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$category_filter = isset($_GET['category']) ? sanitize($_GET['category']) : '';

// Build query for all inventory items
$query = "
    SELECT i.*, e.name as equipment_name, s.name as section_name,
           (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') as is_issued
    FROM inventory i
    LEFT JOIN equipment e ON i.equipment_id = e.id
    LEFT JOIN sections s ON i.section_id = s.id
    WHERE 1=1
";

if ($search) {
    $query .= " AND (i.article_name LIKE '%$search%' 
                     OR i.property_no LIKE '%$search%'
                     OR i.description LIKE '%$search%')";
}

if ($category_filter) {
    $query .= " AND i.category = '$category_filter'";
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

<!-- Statistics Cards for All Inventory -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <h3>Total Items</h3>
        <?php
        $result = $conn->query("SELECT COUNT(*) as count FROM inventory");
        $total_items = $result->fetch_assoc()['count'];
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
        $issued_items = $result->fetch_assoc()['count'];
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
        $low_stock = $result->fetch_assoc()['count'];
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
        $total_value = $result->fetch_assoc()['total'] ?? 0;
        ?>
        <div class="card-value"><?php echo formatCurrency($total_value); ?></div>
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
            <?php if ($categories): while($cat = $categories->fetch_assoc()): ?>
            <option value="<?php echo $cat['category']; ?>" <?php echo $category_filter == $cat['category'] ? 'selected' : ''; ?>>
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
    
    <table>
        <thead>
            <tr>
                <th>Article Name</th>
                <th>Property No.</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>Unit Value</th>
                <th>Location</th>
                <th>Condition</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($inventory_items['data']) > 0): ?>
                <?php foreach ($inventory_items['data'] as $item): ?>
                <tr class="<?php echo $item['qty_physical_count'] <= 5 ? 'stock-alert-row' : ''; ?>">
                    <td>
                        <strong><?php echo htmlspecialchars($item['article_name']); ?></strong>
                        <?php if ($item['description']): ?>
                        <br><small><?php echo htmlspecialchars(substr($item['description'], 0, 50)) . '...'; ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($item['property_no']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($item['category']); ?>
                        <br><small><?php echo htmlspecialchars($item['equipment_name']); ?></small>
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
                    <td colspan="9" class="text-center">
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
    
    <!-- Pagination -->
    <?php 
    $pagination_url = '?page=';
    if ($search) $pagination_url .= '&search=' . urlencode($search);
    if ($category_filter) $pagination_url .= '&category=' . urlencode($category_filter);
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
        
        <!-- Scrollable body -->
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
                            <option value="PPE" <?php echo ($edit_item && $edit_item['category'] == 'PPE') ? 'selected' : ''; ?>>PPE (Personal Protective Equipment)</option>
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
                            <?php if ($equipment): mysqli_data_seek($equipment, 0); while($eq = $equipment->fetch_assoc()): ?>
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
                            <?php if ($sections): mysqli_data_seek($sections, 0); while($sec = $sections->fetch_assoc()): ?>
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
                
                <!-- Fixed button section at bottom of form -->
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

<!-- View Item Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width: 600px; max-height: 80vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="flex-shrink: 0;">
            <h2>Item Details</h2>
            <span class="modal-close" onclick="closeViewModal()">&times;</span>
        </div>
        <div class="modal-body" id="viewModalContent" style="overflow-y: auto; flex: 1; padding: 20px;">
            <!-- Content will be loaded via AJAX -->
        </div>
        <div class="modal-footer" style="flex-shrink: 0; padding: 15px 20px; border-top: 1px solid var(--light); text-align: right;">
            <button type="button" class="btn btn-secondary" onclick="closeViewModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

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

// View Item Details
function viewItem(itemId) {
    fetch('<?php echo SITE_URL; ?>/api/get_item_details.php?id=' + itemId)
        .then(response => response.json())
        .then(data => {
            let content = `
                <div style="margin-bottom: 20px;">
                    <h3>${data.article_name}</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 8px 0;"><strong>Property No:</strong></td><td style="padding: 8px 0;">${data.property_no || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Description:</strong></td><td style="padding: 8px 0;">${data.description || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Category:</strong></td><td style="padding: 8px 0;">${data.category}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Equipment Type:</strong></td><td style="padding: 8px 0;">${data.equipment_name || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Quantity:</strong></td><td style="padding: 8px 0;">${data.qty_physical_count} ${data.uom}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Unit Value:</strong></td><td style="padding: 8px 0;">${formatCurrency(data.unit_value)}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Total Value:</strong></td><td style="padding: 8px 0;">${formatCurrency(data.unit_value * data.qty_physical_count)}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Location:</strong></td><td style="padding: 8px 0;">${data.section_name || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Condition:</strong></td><td style="padding: 8px 0;">${data.condition_text || 'Good'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Fund Cluster:</strong></td><td style="padding: 8px 0;">${data.fund_cluster || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Date Added:</strong></td><td style="padding: 8px 0;">${formatDate(data.date_added)}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Remarks:</strong></td><td style="padding: 8px 0;">${data.remarks || 'N/A'}</td></tr>
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

// Auto-calculate on page load if editing
window.addEventListener('load', function() {
    <?php if ($edit_item): ?>
    calculateTotal();
    <?php endif; ?>
});

// Close modal when clicking outside
window.onclick = function(event) {
    let inventoryModal = document.getElementById('inventoryModal');
    let viewModal = document.getElementById('viewModal');
    
    if (event.target == inventoryModal) {
        closeModal();
    }
    if (event.target == viewModal) {
        closeViewModal();
    }
}
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>