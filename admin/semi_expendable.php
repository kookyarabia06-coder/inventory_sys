<?php
/**
 * Semi-Expendable Page (Admin)
 * Complete semi-expendable management system with all inventory fields
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Require admin role
requireRole('admin');

$page_title = 'Semi-Expendable Items';
$page_description = 'Manage semi-expendable inventory';

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

// Handle Add Semi-Expendable Item
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add') {
        $article_name = sanitize($_POST['article_name']);
        $description = sanitize($_POST['description']);
        $property_no = generatePropertyNo();
        $uom = sanitize($_POST['uom']);
        $quantity = floatval($_POST['quantity']);
        $unit_value = floatval($_POST['unit_value']);
        $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
        $section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
        $category = 'Semi-Expendable'; // Auto-set to Semi-Expendable
        $type_equipment = sanitize($_POST['type_equipment']);
        $condition_text = sanitize($_POST['condition_text']);
        $fund_cluster = sanitize($_POST['fund_cluster']);
        $certified_correct = sanitize($_POST['certified_correct']);
        $approved_by = !empty($_POST['approved_by']) ? (int)$_POST['approved_by'] : null;
        $verified_by = !empty($_POST['verified_by']) ? (int)$_POST['verified_by'] : null;
        $allocate_to = !empty($_POST['allocate_to']) ? (int)$_POST['allocate_to'] : null;
        $remarks = sanitize($_POST['remarks']);
        $created_by = $_SESSION['user_id'];
        
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
                    equipment_id, section_id, category, type_equipment, condition_text,
                    fund_cluster, certified_correct, approved_by, verified_by,
                    allocate_to, remarks, created_by, date_added, date_updated
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $qty_property_card = $quantity;
            $qty_physical_count = $quantity;
            
            // 19 parameters total
            $stmt->bind_param(
                "sssdddiiisssssiiiisi",
                $article_name,           // s
                $description,             // s
                $property_no,              // s
                $uom,                      // s
                $qty_property_card,        // d
                $qty_physical_count,       // d
                $unit_value,                // d
                $equipment_id,              // i
                $section_id,                // i
                $category,                  // s
                $type_equipment,            // s
                $condition_text,            // s
                $fund_cluster,              // s
                $certified_correct,         // s
                $approved_by,                // i
                $verified_by,                // i
                $allocate_to,                // i
                $remarks,                    // s
                $created_by                  // i
            );
            
            if ($stmt->execute()) {
                $inventory_id = $stmt->insert_id;
                logActivity('Add Semi-Expendable', $inventory_id, "Added new semi-expendable item: $article_name");
                $_SESSION['success'] = "Semi-expendable item added successfully. Property No: $property_no";
            } else {
                $_SESSION['error'] = "Database error: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = implode("<br>", $errors);
        }
        
        header('Location: ' . SITE_URL . '/admin/semi_expendable.php');
        exit();
    }
    
    // Handle Edit Semi-Expendable Item
    // In the ADD section, make sure the type string is correct:
$stmt->bind_param(
    "sssdddiiisssssiiiisi",  // 19 characters
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
    $created_by
);

// In the EDIT section:
$stmt->bind_param(
    "sssddiissssiiiisi",  // 17 characters
    $article_name,
    $description,
    $uom,
    $quantity,
    $unit_value,
    $equipment_id,
    $section_id,
    $type_equipment,
    $condition_text,
    $fund_cluster,
    $certified_correct,
    $approved_by,
    $verified_by,
    $allocate_to,
    $remarks,
    $id
);
        if ($stmt->execute()) {
            logActivity('Edit Semi-Expendable', $id, "Edited semi-expendable item: $article_name");
            $_SESSION['success'] = "Semi-expendable item updated successfully";
        } else {
            $_SESSION['error'] = "Error updating item: " . $conn->error;
        }
        $stmt->close();
        
        header('Location: ' . SITE_URL . '/admin/semi_expendable.php');
        exit();
    }


// Handle Delete Semi-Expendable Item
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    
    // Check if item is issued
    $check = $conn->query("SELECT id FROM equipment_issuance WHERE inventory_id = $id AND status = 'issued'");
    if ($check && $check->num_rows > 0) {
        $_SESSION['error'] = "Cannot delete item that is currently issued";
    } else {
        $conn->query("DELETE FROM inventory WHERE id = $id");
        if ($conn->affected_rows > 0) {
            logActivity('Delete Semi-Expendable', $id, "Deleted semi-expendable item ID: $id");
            $_SESSION['success'] = "Semi-expendable item deleted successfully";
        }
    }
    
    header('Location: ' . SITE_URL . '/admin/semi_expendable.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query for Semi-Expendable items only
$query = "
    SELECT i.*, e.name as equipment_name, s.name as section_name,
           CONCAT(ap.firstname, ' ', ap.lastname) as approver_name,
           CONCAT(vr.firstname, ' ', vr.lastname) as verifier_name,
           CONCAT(al.firstname, ' ', al.lastname) as allocatee_name,
           (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') as is_issued
    FROM inventory i
    LEFT JOIN equipment e ON i.equipment_id = e.id
    LEFT JOIN sections s ON i.section_id = s.id
    LEFT JOIN users ap ON i.approved_by = ap.id
    LEFT JOIN users vr ON i.verified_by = vr.id
    LEFT JOIN users al ON i.allocate_to = al.id
    WHERE i.category = 'Semi-Expendable' OR i.type_equipment = 'Semi-Expendable'
";

if ($search) {
    $query .= " AND (i.article_name LIKE '%$search%' 
                     OR i.property_no LIKE '%$search%'
                     OR i.description LIKE '%$search%')";
}

$query .= " ORDER BY i.date_added DESC";

// Get paginated results
$semi_items = paginate($query, $page, $per_page);

// Get item for editing if ID is provided
$edit_item = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $result = $conn->query("SELECT * FROM inventory WHERE id = $edit_id");
    $edit_item = $result->fetch_assoc();
}

include INCLUDE_PATH . '/header.php';
?>

<!-- Statistics Cards for Semi-Expendable -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-box-open"></i>
        </div>
        <h3>Total Semi-Expendable</h3>
        <?php
        $result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE category = 'Semi-Expendable' OR type_equipment = 'Semi-Expendable'");
        $total_semi = $result->fetch_assoc()['count'];
        ?>
        <div class="card-value"><?php echo $total_semi; ?></div>
        <div class="card-label">All semi-expendable items</div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-hand-holding"></i>
        </div>
        <h3>Issued Items</h3>
        <?php
        $result = $conn->query("
            SELECT COUNT(DISTINCT ei.inventory_id) as count 
            FROM equipment_issuance ei
            JOIN inventory i ON ei.inventory_id = i.id
            WHERE (i.category = 'Semi-Expendable' OR i.type_equipment = 'Semi-Expendable') AND ei.status = 'issued'
        ");
        $issued_semi = $result->fetch_assoc()['count'];
        ?>
        <div class="card-value"><?php echo $issued_semi; ?></div>
        <div class="card-label">Currently issued</div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3>Low Stock</h3>
        <?php
        $result = $conn->query("
            SELECT COUNT(*) as count 
            FROM inventory 
            WHERE (category = 'Semi-Expendable' OR type_equipment = 'Semi-Expendable') AND qty_physical_count <= 5
        ");
        $low_stock_semi = $result->fetch_assoc()['count'];
        ?>
        <div class="card-value <?php echo $low_stock_semi > 0 ? 'text-warning' : ''; ?>"><?php echo $low_stock_semi; ?></div>
        <div class="card-label">Need reorder</div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <h3>Total Value</h3>
        <?php
        $result = $conn->query("
            SELECT SUM(unit_value * qty_physical_count) as total 
            FROM inventory 
            WHERE category = 'Semi-Expendable' OR type_equipment = 'Semi-Expendable'
        ");
        $total_value = $result->fetch_assoc()['total'] ?? 0;
        ?>
        <div class="card-value"><?php echo formatCurrency($total_value); ?></div>
        <div class="card-label">Inventory value</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-bolt"></i> Semi-Expendable Quick Actions</h2>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus-circle"></i> Add New Semi-Expendable
        </button>
        <a href="<?php echo SITE_URL; ?>/admin/issue_items.php?category=semi" class="btn btn-primary">
            <i class="fas fa-hand-holding"></i> Issue Items
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/semi_expendable.php?export=1" class="btn btn-secondary">
            <i class="fas fa-download"></i> Export List
        </a>
    </div>
</div>

<!-- Search and Filter -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-search"></i> Search Semi-Expendable Items</h2>
    </div>
    <form method="GET" action="" class="search-box">
        <input type="text" name="search" placeholder="Search by article name, property no., or description..." 
               value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">
            <i class="fas fa-search"></i> Search
        </button>
        <?php if ($search): ?>
        <a href="<?php echo SITE_URL; ?>/admin/semi_expendable.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Clear
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Semi-Expendable Items Table -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-box-open"></i> Semi-Expendable Items List</h2>
        <p>Showing <?php echo count($semi_items['data']); ?> of <?php echo $semi_items['total_rows']; ?> items</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Article Name</th>
                <th>Property No.</th>
                <th>Category/Type</th>
                <th>Quantity</th>
                <th>Unit Value</th>
                <th>Location</th>
                <th>Condition</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($semi_items['data']) > 0): ?>
                <?php foreach ($semi_items['data'] as $item): ?>
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
                        <br><small><?php echo htmlspecialchars($item['type_equipment'] ?? $item['equipment_name']); ?></small>
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
                               onclick="return confirm('Are you sure you want to delete this semi-expendable item?')"
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
                        <i class="fas fa-box-open" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                        <br>
                        No semi-expendable items found
                        <br>
                        <button class="btn btn-primary mt-3" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Your First Semi-Expendable Item
                        </button>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php echo displayPagination($semi_items, '?page=' . ($search ? '&search=' . urlencode($search) : '')); ?>
</div>

<!-- Add/Edit Semi-Expendable Modal -->
<div id="semiModal" class="modal" style="display: <?php echo $edit_item ? 'block' : 'none'; ?>;">
    <div class="modal-content" style="max-width: 900px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="flex-shrink: 0;">
            <h2 id="modalTitle"><?php echo $edit_item ? 'Edit Semi-Expendable Item' : 'Add New Semi-Expendable Item'; ?></h2>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        
        <!-- Scrollable body -->
        <div class="modal-body" style="overflow-y: auto; flex: 1; padding: 20px;">
            <form method="POST" action="" id="semiForm">
                <input type="hidden" name="action" value="<?php echo $edit_item ? 'edit' : 'add'; ?>">
                <?php if ($edit_item): ?>
                <input type="hidden" name="id" value="<?php echo $edit_item['id']; ?>">
                <?php endif; ?>
                
                <!-- Basic Information -->
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                    
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
                </div>
                
                <!-- Classification -->
                <div class="form-section">
                    <h3><i class="fas fa-tags"></i> Classification</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="type_equipment">Type of Equipment</label>
                            <input type="text" class="form-control" id="type_equipment" name="type_equipment" 
                                   value="<?php echo $edit_item ? htmlspecialchars($edit_item['type_equipment']) : ''; ?>"
                                   placeholder="e.g., Office, Medical, Laboratory">
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
                </div>
                
                <!-- Quantity and Value -->
                <div class="form-section">
                    <h3><i class="fas fa-calculator"></i> Quantity and Value</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="uom">Unit of Measurement <span class="text-danger">*</span></label>
                            <select class="form-control" id="uom" name="uom" required>
                                <option value="">-- Select UOM --</option>
                                <option value="pcs" <?php echo ($edit_item && $edit_item['uom'] == 'pcs') ? 'selected' : ''; ?>>Pieces (pcs)</option>
                                <option value="box" <?php echo ($edit_item && $edit_item['uom'] == 'box') ? 'selected' : ''; ?>>Box</option>
                                <option value="unit" <?php echo ($edit_item && $edit_item['uom'] == 'unit') ? 'selected' : ''; ?>>Unit</option>
                                <option value="set" <?php echo ($edit_item && $edit_item['uom'] == 'set') ? 'selected' : ''; ?>>Set</option>
                                <option value="pair" <?php echo ($edit_item && $edit_item['uom'] == 'pair') ? 'selected' : ''; ?>>Pair</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="quantity">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity" name="quantity" 
                                   value="<?php echo $edit_item ? $edit_item['qty_physical_count'] : '1'; ?>" 
                                   min="0.01" step="0.01" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="unit_value">Unit Value (₱) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="unit_value" name="unit_value" 
                                   value="<?php echo $edit_item ? $edit_item['unit_value'] : ''; ?>" 
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
                               value="<?php echo $edit_item ? htmlspecialchars($edit_item['fund_cluster']) : ''; ?>"
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
                                <option value="New" <?php echo ($edit_item && $edit_item['condition_text'] == 'New') ? 'selected' : ''; ?>>New</option>
                                <option value="Good" <?php echo ($edit_item && $edit_item['condition_text'] == 'Good') ? 'selected' : ''; ?>>Good</option>
                                <option value="Fair" <?php echo ($edit_item && $edit_item['condition_text'] == 'Fair') ? 'selected' : ''; ?>>Fair</option>
                                <option value="Poor" <?php echo ($edit_item && $edit_item['condition_text'] == 'Poor') ? 'selected' : ''; ?>>Poor</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="certified_correct">Certified Correct By</label>
                            <input type="text" class="form-control" id="certified_correct" name="certified_correct" 
                                   value="<?php echo $edit_item ? htmlspecialchars($edit_item['certified_correct']) : ''; ?>"
                                   placeholder="Name of certifying officer">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="approved_by">Approved By</label>
                            <select class="form-control" id="approved_by" name="approved_by">
                                <option value="">-- Select Approver --</option>
                                <?php if ($users): mysqli_data_seek($users, 0); while($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo ($edit_item && $edit_item['approved_by'] == $user['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="verified_by">Verified By</label>
                            <select class="form-control" id="verified_by" name="verified_by">
                                <option value="">-- Select Verifier --</option>
                                <?php if ($users): mysqli_data_seek($users, 0); while($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo ($edit_item && $edit_item['verified_by'] == $user['id']) ? 'selected' : ''; ?>>
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
                    
                    <div class="form-group">
                        <label for="allocate_to">Allocate To</label>
                        <select class="form-control" id="allocate_to" name="allocate_to">
                            <option value="">-- Select User --</option>
                            <?php if ($users): mysqli_data_seek($users, 0); while($user = $users->fetch_assoc()): ?>
                            <option value="<?php echo $user['id']; ?>" 
                                <?php echo ($edit_item && $edit_item['allocate_to'] == $user['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                            </option>
                            <?php endwhile; endif; ?>
                        </select>
                        <small class="form-text text-muted">Assign this item to a specific user</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="remarks">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2" 
                                  placeholder="Any additional notes"><?php echo $edit_item ? htmlspecialchars($edit_item['remarks']) : ''; ?></textarea>
                    </div>
                </div>
                
                <!-- Date Information (Display Only) -->
                <div class="form-section">
                    <h3><i class="fas fa-calendar-alt"></i> Date Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date Added</label>
                            <input type="text" class="form-control" value="<?php echo $edit_item ? date('Y-m-d H:i:s', strtotime($edit_item['date_added'])) : date('Y-m-d H:i:s'); ?>" readonly disabled>
                        </div>
                        <div class="form-group">
                            <label>Date Updated</label>
                            <input type="text" class="form-control" value="<?php echo $edit_item ? date('Y-m-d H:i:s', strtotime($edit_item['date_updated'] ?? 'now')) : date('Y-m-d H:i:s'); ?>" readonly disabled>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <p class="text-info">
                        <i class="fas fa-info-circle"></i> 
                        Category will be automatically set to <strong>Semi-Expendable</strong>
                    </p>
                </div>
                
                <!-- Fixed button section at bottom of form -->
                <div class="form-group" style="margin-top: 20px; padding-top: 10px; border-top: 2px solid #BBE0EF;">
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
    <div class="modal-content" style="max-width: 700px; max-height: 80vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="flex-shrink: 0;">
            <h2>Semi-Expendable Item Details</h2>
            <span class="modal-close" onclick="closeViewModal()">&times;</span>
        </div>
        <div class="modal-body" id="viewModalContent" style="overflow-y: auto; flex: 1; padding: 20px;">
            <!-- Content will be loaded via AJAX -->
        </div>
        <div class="modal-footer" style="flex-shrink: 0; padding: 15px 20px; border-top: 1px solid #BBE0EF; text-align: right;">
            <button type="button" class="btn btn-secondary" onclick="closeViewModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
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

.form-control[readonly], .form-control[disabled] {
    background-color: #BBE0EF;
    color: #161E54;
    cursor: not-allowed;
    opacity: 0.7;
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
    document.getElementById('modalTitle').textContent = 'Add New Semi-Expendable Item';
    document.getElementById('semiForm').reset();
    document.querySelector('#semiForm input[name="action"]').value = 'add';
    document.getElementById('semiModal').style.display = 'block';
    calculateTotal();
}

// Close Modal
function closeModal() {
    document.getElementById('semiModal').style.display = 'none';
    window.location.href = '<?php echo SITE_URL; ?>/admin/semi_expendable.php';
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
                        <tr><td style="padding: 8px 0;"><strong>Type of Equipment:</strong></td><td style="padding: 8px 0;">${data.type_equipment || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Equipment Type:</strong></td><td style="padding: 8px 0;">${data.equipment_name || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Quantity:</strong></td><td style="padding: 8px 0;">${data.qty_physical_count} ${data.uom}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Unit Value:</strong></td><td style="padding: 8px 0;">${formatCurrency(data.unit_value)}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Total Value:</strong></td><td style="padding: 8px 0;">${formatCurrency(data.unit_value * data.qty_physical_count)}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Fund Cluster:</strong></td><td style="padding: 8px 0;">${data.fund_cluster || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Location:</strong></td><td style="padding: 8px 0;">${data.section_name || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Condition:</strong></td><td style="padding: 8px 0;">${data.condition_text || 'Good'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Certified Correct By:</strong></td><td style="padding: 8px 0;">${data.certified_correct || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Approved By:</strong></td><td style="padding: 8px 0;">${data.approver_name || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Verified By:</strong></td><td style="padding: 8px 0;">${data.verifier_name || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Allocated To:</strong></td><td style="padding: 8px 0;">${data.allocatee_name || 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Date Added:</strong></td><td style="padding: 8px 0;">${formatDate(data.date_added)}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Date Updated:</strong></td><td style="padding: 8px 0;">${data.date_updated ? formatDate(data.date_updated) : 'Never'}</td></tr>
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
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// Auto-calculate on page load if editing
window.addEventListener('load', function() {
    <?php if ($edit_item): ?>
    calculateTotal();
    <?php endif; ?>
});

// Close modal when clicking outside
window.onclick = function(event) {
    let semiModal = document.getElementById('semiModal');
    let viewModal = document.getElementById('viewModal');
    
    if (event.target == semiModal) {
        closeModal();
    }
    if (event.target == viewModal) {
        closeViewModal();
    }
}
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>