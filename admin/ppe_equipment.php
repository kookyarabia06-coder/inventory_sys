<?php
/**
 * PPE Equipment Page (Admin)
 * Complete PPE management system with all inventory fields
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

require_once $root_path . '/vendor/autoload.php';
use Picqer\Barcode\BarcodeGeneratorPNG;

// Require admin role
requireRole('admin');

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'PPE Equipment';
$page_description = 'Manage Personal Protective Equipment';

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

// Handle Add PPE Item
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    if ($_POST['action'] == 'add') {
        $article_name = sanitize($_POST['article_name']);
        $description = sanitize($_POST['description']);
        $property_no = generatePropertyNo();
        $uom = sanitize($_POST['uom']);
        $quantity = floatval($_POST['quantity']);
        $unit_value = floatval($_POST['unit_value']);
        $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
        $section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
        $category = 'PPE'; // Auto-set to PPE
        $type_equipment = sanitize($_POST['type_equipment']);
        $condition_text = sanitize($_POST['condition_text']);
        $fund_cluster = sanitize($_POST['fund_cluster']);
        $certified_correct = sanitize($_POST['certified_correct']);
        $approved_by = !empty($_POST['approved_by']) ? (int)$_POST['approved_by'] : null;
        $verified_by = !empty($_POST['verified_by']) ? (int)$_POST['verified_by'] : null;
        $allocate_to = !empty($_POST['allocate_to']) ? (int)$_POST['allocate_to'] : null;
        $remarks = sanitize($_POST['remarks']);
        $barcode_data = sanitize($_POST['barcode_data'] ?? '');
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
                    allocate_to, remarks, barcode_data, created_by, date_added, date_updated
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $qty_property_card = $quantity;
            $qty_physical_count = $quantity;
            
            $stmt->bind_param(
                "sssdddiiisssssiiiisssi",
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
                $barcode_data,
                $created_by
            );
            
            if ($stmt->execute()) {
                $inventory_id = $stmt->insert_id;
                logActivity('Add PPE', $inventory_id, "Added new PPE item: $article_name");
                $_SESSION['success'] = "PPE item added successfully. Property No: $property_no";
            } else {
                $_SESSION['error'] = "Database error: " . $conn->error;
            }
            $stmt->close();
        } else {
            $_SESSION['error'] = implode("<br>", $errors);
        }
        
        header('Location: ' . SITE_URL . '/admin/ppe_equipment.php');
        exit();
    }
    
    // Handle Edit PPE Item
    elseif ($_POST['action'] == 'edit' && isset($_POST['id'])) {
        $id = (int)$_POST['id'];
        $article_name = sanitize($_POST['article_name']);
        $description = sanitize($_POST['description']);
        $uom = sanitize($_POST['uom']);
        $quantity = floatval($_POST['quantity']);
        $unit_value = floatval($_POST['unit_value']);
        $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
        $section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
        $type_equipment = sanitize($_POST['type_equipment']);
        $condition_text = sanitize($_POST['condition_text']);
        $fund_cluster = sanitize($_POST['fund_cluster']);
        $certified_correct = sanitize($_POST['certified_correct']);
        $approved_by = !empty($_POST['approved_by']) ? (int)$_POST['approved_by'] : null;
        $verified_by = !empty($_POST['verified_by']) ? (int)$_POST['verified_by'] : null;
        $allocate_to = !empty($_POST['allocate_to']) ? (int)$_POST['allocate_to'] : null;
        $remarks = sanitize($_POST['remarks']);
        $barcode_data = sanitize($_POST['barcode_data'] ?? '');
        
        $stmt = $conn->prepare("
            UPDATE inventory SET 
                article_name = ?, description = ?, uom = ?,
                qty_physical_count = ?, unit_value = ?,
                equipment_id = ?, section_id = ?, type_equipment = ?,
                condition_text = ?, fund_cluster = ?, certified_correct = ?,
                approved_by = ?, verified_by = ?, allocate_to = ?,
                remarks = ?, barcode_data = ?, date_updated = NOW()
            WHERE id = ?
        ");
        
        $stmt->bind_param(
            "sssddiissssiiisssi",
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
            $barcode_data,
            $id
        );
        
        if ($stmt->execute()) {
            logActivity('Edit PPE', $id, "Edited PPE item: $article_name");
            $_SESSION['success'] = "PPE item updated successfully";
        } else {
            $_SESSION['error'] = "Error updating item: " . $conn->error;
        }
        $stmt->close();
        
        header('Location: ' . SITE_URL . '/admin/ppe_equipment.php');
        exit();
    }
}

// Handle Delete PPE Item (with prepared statement)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // Verify CSRF token for GET requests (using a token in URL)
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $id = (int)$_GET['delete'];
    
    // Check if item is issued using prepared statement
    $stmt = $conn->prepare("SELECT id FROM equipment_issuance WHERE inventory_id = ? AND status = 'issued' LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $check = $stmt->get_result();
    
    if ($check && $check->num_rows > 0) {
        $_SESSION['error'] = "Cannot delete item that is currently issued";
    } else {
        $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            logActivity('Delete PPE', $id, "Deleted PPE item ID: $id");
            $_SESSION['success'] = "PPE item deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting item or item not found";
        }
        $stmt->close();
    }
    
    header('Location: ' . SITE_URL . '/admin/ppe_equipment.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query for PPE items only (using prepared statements for search)
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
    WHERE i.category = 'PPE' OR i.type_equipment = 'PPE'
";

$count_query = "SELECT COUNT(*) as total FROM inventory WHERE category = 'PPE' OR type_equipment = 'PPE'";

$params = [];
$types = "";

if ($search) {
    $query .= " AND (i.article_name LIKE ? OR i.property_no LIKE ? OR i.description LIKE ?)";
    $count_query .= " AND (article_name LIKE ? OR property_no LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params = [$search_term, $search_term, $search_term];
    $types = "sss";
}

$query .= " ORDER BY i.date_added DESC";

// Get total rows for pagination
$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_rows = $total_result->fetch_assoc()['total'];
$stmt->close();

// Calculate pagination
$offset = ($page - 1) * $per_page;
$query .= " LIMIT ? OFFSET ?";

// Get paginated results
$stmt = $conn->prepare($query);
if (!empty($params)) {
    // Merge search params with pagination params
    $all_params = array_merge($params, [$per_page, $offset]);
    $all_types = $types . "ii";
    $stmt->bind_param($all_types, ...$all_params);
} else {
    $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

$ppe_items = [];
while ($row = $result->fetch_assoc()) {
    $ppe_items[] = $row;
}
$stmt->close();

// Create pagination data structure
$pagination_data = [
    'data' => $ppe_items,
    'total_rows' => $total_rows,
    'per_page' => $per_page,
    'current_page' => $page,
    'total_pages' => ceil($total_rows / $per_page)
];

// Get item for editing if ID is provided
$edit_item = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->bind_param("i", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_item = $result->fetch_assoc();
    $stmt->close();
}

include INCLUDE_PATH . '/header.php';

// Barcode handler with improved validation
if (isset($_GET['generate_barcode'])) {
    header('Content-Type: application/json');

    $barcode_value = $_GET['barcode_value'] ?? '';

    if (empty($barcode_value)) {
        echo json_encode(['error' => 'Please provide barcode value']);
        exit;
    }

    // Validate barcode format (alphanumeric and basic punctuation only)
    if (!preg_match('/^[A-Za-z0-9\-_]+$/', $barcode_value)) {
        echo json_encode(['error' => 'Invalid barcode format. Use only letters, numbers, hyphens and underscores.']);
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
    $quantity = min(intval($_GET['quantity'] ?? 5), 50); // Limit to 50 max
    
    // Validate prefix
    if (!preg_match('/^[A-Za-z0-9\-_]+$/', $prefix)) {
        echo json_encode(['error' => 'Invalid prefix format']);
        exit;
    }
    
    $baseBarcode = $prefix . date('Ymd');

    try {
        $generator = new BarcodeGeneratorPNG();
        $barcodes = [];

        for ($i = 0; $i < min($quantity, 10); $i++) { // Preview only first 10
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
?>

<!-- Display Success/Error Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<!-- Statistics Cards for PPE -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-shield-alt"></i>
        </div>
        <h3>Total PPE Items</h3>
        <?php
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory WHERE category = 'PPE' OR type_equipment = 'PPE'");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_ppe = $result->fetch_assoc()['count'];
        $stmt->close();
        ?>
        <div class="card-value"><?php echo $total_ppe; ?></div>
        <div class="card-label">All PPE equipment</div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-hand-holding"></i>
        </div>
        <h3>Issued PPE</h3>
        <?php
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT ei.inventory_id) as count 
            FROM equipment_issuance ei
            JOIN inventory i ON ei.inventory_id = i.id
            WHERE (i.category = 'PPE' OR i.type_equipment = 'PPE') AND ei.status = 'issued'
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $issued_ppe = $result->fetch_assoc()['count'];
        $stmt->close();
        ?>
        <div class="card-value"><?php echo $issued_ppe; ?></div>
        <div class="card-label">Currently issued</div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3>Low Stock PPE</h3>
        <?php
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM inventory 
            WHERE (category = 'PPE' OR type_equipment = 'PPE') AND qty_physical_count <= 5
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $low_stock_ppe = $result->fetch_assoc()['count'];
        $stmt->close();
        ?>
        <div class="card-value <?php echo $low_stock_ppe > 0 ? 'text-warning' : ''; ?>"><?php echo $low_stock_ppe; ?></div>
        <div class="card-label">Need reorder</div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-dollar-sign"></i>
        </div>
        <h3>Total Value</h3>
        <?php
        $stmt = $conn->prepare("
            SELECT SUM(unit_value * qty_physical_count) as total 
            FROM inventory 
            WHERE category = 'PPE' OR type_equipment = 'PPE'
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_value = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        ?>
        <div class="card-value"><?php echo formatCurrency($total_value); ?></div>
        <div class="card-label">PPE inventory value</div>
    </div>
</div>

<!-- Quick Actions -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-bolt"></i> PPE Quick Actions</h2>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <button class="btn btn-primary" onclick="openAddModal()">
            <i class="fas fa-plus-circle"></i> Add New PPE
        </button>
        <a href="<?php echo SITE_URL; ?>/admin/issue_items.php?category=ppe" class="btn btn-primary">
            <i class="fas fa-hand-holding"></i> Issue PPE
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/ppe_equipment.php?export=1" class="btn btn-secondary">
            <i class="fas fa-download"></i> Export PPE List
        </a>
        <!-- Test button - remove after confirming it works -->
        <button class="btn btn-info" onclick="testBarcodeGenerator()" style="background-color: #17a2b8;">
            <i class="fas fa-flask"></i> Test Barcode
        </button>
    </div>
</div>

<!-- Search and Filter -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-search"></i> Search PPE Items</h2>
    </div>
    <form method="GET" action="" class="search-box">
        <input type="text" name="search" placeholder="Search by article name, property no., or description..." 
               value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">
            <i class="fas fa-search"></i> Search
        </button>
        <?php if ($search): ?>
        <a href="<?php echo SITE_URL; ?>/admin/ppe_equipment.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Clear
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- PPE Items Table -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-shield-alt"></i> PPE Equipment List</h2>
        <p>Showing <?php echo count($ppe_items); ?> of <?php echo $total_rows; ?> items</p>
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
                <th>Barcode</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($ppe_items) > 0): ?>
                <?php foreach ($ppe_items as $item): ?>
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
                        <?php if (!empty($item['barcode_data'])): ?>
                            <button class="btn-xs" onclick="showBarcodeModal('<?php echo htmlspecialchars($item['barcode_data']); ?>', '<?php echo htmlspecialchars($item['article_name']); ?>')">
                                <i class="fas fa-barcode"></i> View
                            </button>
                        <?php else: ?>
                            <span class="text-muted">No barcode</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <a href="?edit=<?php echo $item['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="action-btn edit" title="Edit">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button class="action-btn view" onclick="viewItem(<?php echo $item['id']; ?>)" title="View">
                                <i class="fas fa-eye"></i>
                            </button>
                            <?php if ($item['is_issued'] == 0): ?>
                            <a href="?delete=<?php echo $item['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                               class="action-btn delete" 
                               onclick="return confirm('Are you sure you want to delete this PPE item?')"
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
                        <i class="fas fa-shield-alt" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                        <br>
                        No PPE items found
                        <br>
                        <button class="btn btn-primary mt-3" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Your First PPE Item
                        </button>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php echo displayPagination($pagination_data, '?page=' . ($search ? '&search=' . urlencode($search) : '')); ?>
</div>

<!-- Add/Edit PPE Modal -->
<div id="ppeModal" class="modal" style="display: <?php echo $edit_item ? 'block' : 'none'; ?>;">
    <div class="modal-content" style="max-width: 900px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="flex-shrink: 0;">
            <h2 id="modalTitle"><?php echo $edit_item ? 'Edit PPE Item' : 'Add New PPE Item'; ?></h2>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        
        <!-- Scrollable body -->
        <div class="modal-body" style="overflow-y: auto; flex: 1; padding: 20px;">
            <form method="POST" action="" id="ppeForm">
                <input type="hidden" name="action" value="<?php echo $edit_item ? 'edit' : 'add'; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
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
                               required maxlength="255" placeholder="Enter PPE item name">
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
                                   placeholder="e.g., Respiratory, Protective, Safety">
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
                    
                    <div class="form-group">
                        <label for="barcode_data">Barcode</label>
                        <div class="barcode-input-group">
                            <input type="text" class="form-control" id="barcode_data" name="barcode_data" 
                                   value="<?php echo $edit_item ? htmlspecialchars($edit_item['barcode_data']) : ''; ?>"
                                   placeholder="Enter or generate barcode"
                                   pattern="[A-Za-z0-9\-_]+"
                                   title="Use only letters, numbers, hyphens and underscores">
                            <button type="button" class="btn btn-secondary" onclick="generateBarcodeForEdit()" style="margin-top: 5px;">
                                <i class="fas fa-sync-alt"></i> Generate Barcode
                            </button>
                        </div>
                        <div id="barcodePreview" class="barcode-preview" style="margin-top: 10px; text-align: center;"></div>
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
                        Category will be automatically set to <strong>PPE</strong>
                    </p>
                </div>
                
                <!-- Fixed button section at bottom of form -->
                <div class="form-group" style="margin-top: 20px; padding-top: 10px; border-top: 2px solid #BBE0EF;">
                    <button type="submit" class="btn btn-primary" style="min-width: 150px;">
                        <i class="fas fa-save"></i> <?php echo $edit_item ? 'Update PPE Item' : 'Save PPE Item'; ?>
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="margin-left: 10px;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Item Modal (Single version) -->
<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width: 700px; max-height: 80vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="flex-shrink: 0;">
            <h2>PPE Item Details</h2>
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
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="parent_id" id="parent_id" value="">
                
                <div class="form-group">
                    <label for="barcode_prefix">Barcode Prefix</label>
                    <input type="text" class="form-control" id="barcode_prefix" name="barcode_prefix" value="PPE" maxlength="10" pattern="[A-Za-z0-9\-_]+" title="Use only letters, numbers, hyphens and underscores">
                    <small class="form-text text-muted">Prefix for the barcode (e.g., PPE) - letters, numbers, hyphens, underscores only</small>
                </div>
                
                <div class="form-group">
                    <label for="barcode_quantity">Number of Barcodes to Generate (max 50)</label>
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

.btn-info {
    background-color: #17a2b8;
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 5px;
    cursor: pointer;
}

.btn-info:hover {
    background-color: #138496;
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

.loading-spinner {
    text-align: center;
    padding: 30px;
    color: #F16D34;
}

.loading-spinner i {
    margin-right: 8px;
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

/* Stock alert row */
.stock-alert-row {
    background-color: #fff3cd;
}

.stock-alert-row:hover {
    background-color: #ffe69c;
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
    document.getElementById('modalTitle').textContent = 'Add New PPE Item';
    document.getElementById('ppeForm').reset();
    document.querySelector('#ppeForm input[name="action"]').value = 'add';
    document.getElementById('ppeModal').style.display = 'block';
    document.getElementById('barcodePreview').innerHTML = '';
    calculateTotal();
}

// Close Modal
function closeModal() {
    document.getElementById('ppeModal').style.display = 'none';
    // Remove edit parameter from URL without page reload
    let url = new URL(window.location.href);
    url.searchParams.delete('edit');
    window.history.replaceState({}, document.title, url.toString());
}

// Close View Modal
function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

// Close Barcode Modal
function closeBarcodeModal() {
    document.getElementById('barcodeModal').style.display = 'none';
}

// Close Add Barcode Modal
function closeAddBarcodeModal() {
    document.getElementById('addBarcodeModal').style.display = 'none';
}

// View Item Details
function viewItem(itemId) {
    // Show loading
    document.getElementById('viewModalContent').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Loading item details...</div>';
    document.getElementById('viewModal').style.display = 'block';
    
    fetch('<?php echo SITE_URL; ?>/api/get_item_details.php?id=' + itemId)
        .then(response => response.json())
        .then(data => {
            let content = `
                <div style="margin-bottom: 20px;">
                    <h3>${escapeHtml(data.article_name)}</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 8px 0;"><strong>Property No:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.property_no || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Description:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.description || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Category:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.category)}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Type of Equipment:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.type_equipment || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Equipment Type:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.equipment_name || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Quantity:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.qty_physical_count)} ${escapeHtml(data.uom)}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Unit Value:</strong></td><td style="padding: 8px 0;">${formatCurrency(data.unit_value)}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Total Value:</strong></td><td style="padding: 8px 0;">${formatCurrency(data.unit_value * data.qty_physical_count)}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Fund Cluster:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.fund_cluster || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Location:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.section_name || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Condition:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.condition_text || 'Good')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Certified Correct By:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.certified_correct || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Approved By:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.approver_name || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Verified By:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.verifier_name || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Allocated To:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.allocatee_name || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Barcode:</strong></td><td style="padding: 8px 0;">${data.barcode_data ? escapeHtml(data.barcode_data) : 'N/A'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Date Added:</strong></td><td style="padding: 8px 0;">${formatDate(data.date_added)}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Date Updated:</strong></td><td style="padding: 8px 0;">${data.date_updated ? formatDate(data.date_updated) : 'Never'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Remarks:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.remarks || 'N/A')}</td></tr>
                    </table>
                </div>
            `;
            document.getElementById('viewModalContent').innerHTML = content;
        })
        .catch(error => {
            document.getElementById('viewModalContent').innerHTML = '<div class="alert alert-danger">Error loading item details</div>';
            console.error('Error:', error);
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

// Simple HTML escape function
function escapeHtml(text) {
    if (!text) return text;
    let div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Auto-calculate on page load if editing
window.addEventListener('load', function() {
    <?php if ($edit_item): ?>
    calculateTotal();
    <?php endif; ?>
});

// Close modal when clicking outside
window.onclick = function(event) {
    let ppeModal = document.getElementById('ppeModal');
    let viewModal = document.getElementById('viewModal');
    let barcodeModal = document.getElementById('barcodeModal');
    let addBarcodeModal = document.getElementById('addBarcodeModal');
    
    if (event.target == ppeModal) {
        closeModal();
    }
    if (event.target == viewModal) {
        closeViewModal();
    }
    if (event.target == barcodeModal) {
        closeBarcodeModal();
    }
    if (event.target == addBarcodeModal) {
        closeAddBarcodeModal();
    }
}

// Barcode Functions
function generateBarcodeForEdit() {
    let prefix = 'PPE';
    let date = new Date();
    let dateStr = date.getFullYear() + 
                  String(date.getMonth() + 1).padStart(2, '0') + 
                  String(date.getDate()).padStart(2, '0');
    let randomNum = Math.floor(1000 + Math.random() * 9000);
    let barcodeValue = prefix + '-' + dateStr + '-' + randomNum;
    
    document.getElementById('barcode_data').value = barcodeValue;
    
    // Show loading
    let previewDiv = document.getElementById('barcodePreview');
    previewDiv.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Generating...</div>';
    
    // Generate barcode image preview using the new generator
    fetch('generate_barcodeppe.php?code=' + encodeURIComponent(barcodeValue) + '&format=png')
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.blob();
    })
    .then(blob => {
        // Create object URL from blob
        let url = URL.createObjectURL(blob);
        previewDiv.innerHTML = '<img src="' + url + '" alt="Barcode" style="max-width: 200px; border: 1px solid #ddd; padding: 10px; border-radius: 5px;" onload="URL.revokeObjectURL(\'' + url + '\')">';
    })
    .catch(error => {
        console.error('Error:', error);
        // Fallback to HTML format
        previewDiv.innerHTML = '<iframe src="generate_barcodeppe.php?code=' + encodeURIComponent(barcodeValue) + '&format=html" style="width: 100%; height: 100px; border: none;"></iframe>';
    });
}

function previewBarcodes() {
    let prefix = document.getElementById('barcode_prefix').value;
    let quantity = parseInt(document.getElementById('barcode_quantity').value) || 5;
    
    // Validate prefix
    if (!/^[A-Za-z0-9\-_]+$/.test(prefix)) {
        alert('Prefix can only contain letters, numbers, hyphens and underscores');
        return;
    }
    
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
            if (data.total > 10) {
                html += '<div class="text-muted" style="grid-column: 1/-1; text-align: center;">... and ' + (data.total - 10) + ' more</div>';
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

// Barcode Modal Functions - Updated to use generate_barcodeppe.php
function showBarcodeModal(barcodeData, itemName) {
    document.getElementById('barcodeModalTitle').textContent = 'Barcode - ' + escapeHtml(itemName);
    
    // Show loading indicator
    document.getElementById('barcodeModalImage').innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading barcode...</div>';
    
    // Try PNG format first with timestamp to prevent caching
    let timestamp = new Date().getTime();
    let imageUrl = 'generate_barcodeppe.php?code=' + encodeURIComponent(barcodeData) + '&format=png&width=300&height=80&t=' + timestamp;
    
    // Create image element to test loading
    let img = new Image();
    img.onload = function() {
        // PNG loaded successfully
        document.getElementById('barcodeModalImage').innerHTML = '<img src="' + imageUrl + '" alt="Barcode" style="max-width: 100%; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">';
    };
    
    img.onerror = function() {
        // If PNG fails, try HTML format as fallback
        document.getElementById('barcodeModalImage').innerHTML = '<iframe src="generate_barcodeppe.php?code=' + encodeURIComponent(barcodeData) + '&format=html" style="width: 100%; height: 150px; border: none; border-radius: 5px;"></iframe>';
    };
    
    img.src = imageUrl;
    
    document.getElementById('barcodeModalNumber').textContent = barcodeData;
    document.getElementById('barcodeModal').style.display = 'block';
}

function printCurrentBarcode() {
    let barcodeData = document.getElementById('barcodeModalNumber').textContent;
    let itemName = document.getElementById('barcodeModalTitle').textContent.replace('Barcode - ', '');
    printBarcode(barcodeData, itemName);
}

function printBarcode(barcodeData, itemName) {
    let printWindow = window.open('', '_blank');
    let timestamp = new Date().getTime();
    
    printWindow.document.write(`
    <html>
    <head>
        <title>Print Barcode - ${escapeHtml(itemName)}</title>
        <style>
            body { 
                text-align: center; 
                font-family: Arial, sans-serif; 
                margin: 0; 
                padding: 20px; 
                background: white;
            }
            .barcode-container { 
                margin: 20px auto; 
                padding: 30px; 
                max-width: 400px;
                border: 1px dashed #ccc;
                border-radius: 10px;
                background: white;
            }
            .barcode-img { 
                max-width: 100%; 
                height: auto; 
                margin-bottom: 15px;
            }
            .item-name { 
                margin-top: 15px; 
                font-size: 16px; 
                font-weight: bold;
                color: #333;
            }
            .barcode-number { 
                font-family: monospace; 
                font-size: 14px; 
                margin-top: 10px;
                color: #666;
                letter-spacing: 1px;
            }
            .ppe-label {
                background: #F16D34;
                color: white;
                padding: 5px 15px;
                border-radius: 20px;
                display: inline-block;
                font-size: 14px;
                margin-bottom: 15px;
            }
            @media print {
                body { margin: 0; padding: 0; }
                .barcode-container { border: none; padding: 10px; }
            }
        </style>
    </head>
    <body>
        <div class="barcode-container">
            <div class="ppe-label">PPE Equipment</div>
            <img src="generate_barcodeppe.php?code=${encodeURIComponent(barcodeData)}&format=png&width=400&height=100&t=${timestamp}" 
                 class="barcode-img" 
                 alt="Barcode"
                 onerror="this.onerror=null; this.style.display='none'; this.parentNode.innerHTML += '<div style=\'font-family: monospace; font-size: 24px; letter-spacing: 5px; margin: 20px; border: 1px solid #ddd; padding: 20px;\'>' + '${escapeHtml(barcodeData)}' + '</div>';">
            <div class="item-name">${escapeHtml(itemName)}</div>
            <div class="barcode-number">${escapeHtml(barcodeData)}</div>
        </div>
        <script>
            // Auto-print when loaded
            window.onload = function() { 
                setTimeout(function() { 
                    window.print(); 
                    setTimeout(function() { window.close(); }, 500);
                }, 500);
            }
        <\/script>
    </body>
    </html>
    `);
    printWindow.document.close();
}

// Test function for barcode generator
function testBarcodeGenerator() {
    let testValue = 'PPE-TEST-' + new Date().getTime();
    window.open('generate_barcodeppe.php?code=' + testValue + '&format=html', '_blank');
}
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>