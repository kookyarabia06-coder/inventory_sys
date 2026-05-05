
<?php
/**
 * Semi-Expendable Page (Admin)
 * Complete semi-expendable management system with all inventory fields
 * Supports multiple barcode generation for quantity > 1
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
requireRole('admin' || 'supply');

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Semi-Expendable Items';
$page_description = 'Manage semi-expendable inventory';

// ============================================
// AJAX HANDLERS - MUST BE BEFORE ANY HTML OUTPUT
// ============================================

// Handle AJAX request to get multiple items
if (isset($_GET['get_multiple_items'])) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    $property_no = $_GET['property_no'] ?? '';
    
    if (empty($property_no)) {
        echo json_encode(['error' => 'No property number provided']);
        exit;
    }
    
    // Extract base property number
    $base_property = preg_replace('/-\d+$/', '', $property_no);
    
    // Get all items with this base property
    $stmt = $conn->prepare("
        SELECT i.*, e.name as equipment_name
        FROM inventory i
        LEFT JOIN equipment e ON i.equipment_id = e.id
        WHERE i.property_no LIKE ? 
        ORDER BY i.property_no
    ");
    
    $like_pattern = $base_property . '%';
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'id' => $row['id'],
            'property_no' => $row['property_no'],
            'article_name' => $row['article_name'],
            'barcode_data' => $row['barcode_data'],
            'quantity' => $row['qty_physical_count'],
            'uom' => $row['uom'],
            'unit_value' => $row['unit_value']
        ];
    }
    
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'count' => count($items),
        'base_property' => $base_property
    ]);
    exit;
}

// Barcode handler with improved validation
if (isset($_GET['generate_barcode'])) {
    // Clear any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');

    $barcode_value = $_GET['barcode_value'] ?? '';

    if (empty($barcode_value)) {
        echo json_encode(['error' => 'Please provide barcode value']);
        exit;
    }

    // Validate barcode format
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

// Handle multiple barcode generation preview with improved error handling
if (isset($_GET['generate_multiple_preview'])) {
    // Clear any output buffers to prevent HTML from being sent
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    header('Content-Type: application/json');
    
    // Set up error handling
    $response = [
        'success' => false,
        'error' => null,
        'debug' => []
    ];
    
    try {
        $prefix = $_GET['prefix'] ?? 'SEMI';
        $quantity = isset($_GET['quantity']) ? intval($_GET['quantity']) : 5;
        
        $response['debug']['prefix'] = $prefix;
        $response['debug']['quantity'] = $quantity;
        
        // Validate quantity
        if ($quantity < 1) $quantity = 1;
        if ($quantity > 50) $quantity = 50;
        
        // Validate prefix
        if (!preg_match('/^[A-Za-z0-9\-_]+$/', $prefix)) {
            throw new Exception('Invalid prefix format. Use only letters, numbers, hyphens and underscores.');
        }
        
        // Check if class exists
        if (!class_exists('Picqer\Barcode\BarcodeGeneratorPNG')) {
            throw new Exception('Picqer\Barcode\BarcodeGeneratorPNG class not found. Please check vendor/autoload.php');
        }
        
        // Check GD extension
        if (!extension_loaded('gd')) {
            throw new Exception('GD extension is not loaded. Please enable it in php.ini');
        }
        
        $baseBarcode = $prefix . '-' . date('Ymd');
        $response['debug']['base'] = $baseBarcode;
        
        $generator = new BarcodeGeneratorPNG();
        $barcodes = [];

        $preview_count = min($quantity, 10);
        for ($i = 1; $i <= $preview_count; $i++) {
            $randomPart = rand(1000, 9999);
            $barcodeValue = $baseBarcode . '-' . $randomPart . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
            
            try {
                $barcode = $generator->getBarcode($barcodeValue, $generator::TYPE_CODE_128);
                $barcode_base64 = base64_encode($barcode);
                
                $barcodes[] = [
                    'value' => $barcodeValue,
                    'barcode' => $barcode_base64,
                    'index' => $i
                ];
            } catch (Exception $e) {
                $barcodes[] = [
                    'value' => $barcodeValue,
                    'barcode' => null,
                    'index' => $i,
                    'error' => $e->getMessage()
                ];
            }
        }

        $response['success'] = true;
        $response['barcodes'] = $barcodes;
        $response['total'] = $quantity;
        $response['preview_count'] = $preview_count;
        
    } catch (Exception $e) {
        $response['error'] = $e->getMessage();
        $response['debug']['exception'] = get_class($e);
        $response['debug']['file'] = $e->getFile();
        $response['debug']['line'] = $e->getLine();
    }
    
    // Ensure no whitespace before output
    echo json_encode($response);
    exit;
}

// ============================================
// END OF AJAX HANDLERS
// ============================================

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

// Handle Add Semi-Expendable Item with multiple barcodes
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    if ($_POST['action'] == 'add') {
        $article_name = sanitize($_POST['article_name']);
        $description = sanitize($_POST['description']);
        $property_no_base = generatePropertyNo();
        $uom = sanitize($_POST['uom']);
        $quantity = floatval($_POST['quantity']);
        $unit_value = floatval($_POST['unit_value']);
        $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
        $section_id = !empty($_POST['section_id']) ? (int)$_POST['section_id'] : null;
        $category = 'Semi-Expendable';
        $type_equipment = sanitize($_POST['type_equipment']);
        $condition_text = sanitize($_POST['condition_text']);
        $fund_cluster = sanitize($_POST['fund_cluster']);
        // Handle multi-select for certified_correct
        $certified_correct_array = isset($_POST['certified_correct']) && is_array($_POST['certified_correct']) 
            ? array_filter(array_map('intval', $_POST['certified_correct'])) 
            : [];
        $certified_correct = !empty($certified_correct_array) ? json_encode($certified_correct_array) : null;
        // Handle multi-select for approved_by
        $approved_by_array = isset($_POST['approved_by']) && is_array($_POST['approved_by']) 
            ? array_filter(array_map('intval', $_POST['approved_by'])) 
            : [];
        $approved_by = !empty($approved_by_array) ? json_encode($approved_by_array) : null;
        // Handle multi-select for verified_by
        $verified_by_array = isset($_POST['verified_by']) && is_array($_POST['verified_by']) 
            ? array_filter(array_map('intval', $_POST['verified_by'])) 
            : [];
        $verified_by = !empty($verified_by_array) ? json_encode($verified_by_array) : null;
        $allocate_to = !empty($_POST['allocate_to']) ? (int)$_POST['allocate_to'] : null;
        $remarks = sanitize($_POST['remarks']);
        $barcode_data = sanitize($_POST['barcode_data'] ?? '');
        $created_by = $_SESSION['user_id'];
        $generate_multiple = isset($_POST['generate_multiple_barcodes']) && $_POST['generate_multiple_barcodes'] == '1';
        
        // Validate
        $errors = [];
        if (empty($article_name)) $errors[] = "Article name is required";
        if (empty($uom)) $errors[] = "Unit of measurement is required";
        if ($quantity <= 0) $errors[] = "Quantity must be greater than 0";
        if ($unit_value <= 0) $errors[] = "Unit value must be greater than 0";
        
        if (empty($errors)) {
            // Start transaction
            $conn->begin_transaction();
            
            try {
                // Check if quantity is a whole number (integer) for multiple barcodes
                if ($generate_multiple && $quantity > 1 && floor($quantity) == $quantity) {
                    // Generate multiple barcodes based on quantity
                    $base_barcode = $barcode_data ?: 'SEMI-' . date('Ymd');
                    $success_count = 0;
                    
                    for ($i = 1; $i <= $quantity; $i++) {
                        // Generate sequential property number
                        $property_no = $property_no_base . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                        
                        // Generate sequential barcode
                        $sequential_barcode = $base_barcode . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                        
                        $stmt = $conn->prepare("
                            INSERT INTO inventory (
                                article_name, description, property_no, uom, 
                                qty_property_card, qty_physical_count, unit_value,
                                equipment_id, section_id, category, type_equipment, condition_text,
                                fund_cluster, certified_correct, approved_by, verified_by,
                                allocate_to, remarks, barcode_data, created_by, date_added, date_updated
                            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                        ");
                        
                        // keep the entered quantity on each record rather than defaulting to 1
                        $qty_property_card = $quantity;
                        $qty_physical_count = $quantity;
                        
                        $stmt->bind_param(
                            "ssssddiiisssssiiissi",
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
                            $sequential_barcode,
                            $created_by
                        );
                        
                        if ($stmt->execute()) {
                            $success_count++;
                            logActivity('Add Semi-Expendable Multiple', $stmt->insert_id, "Added semi-expendable item {$i}/{$quantity}: $article_name");
                        }
                        $stmt->close();
                    }
                    
                    $conn->commit();
                    $_SESSION['success'] = "$success_count semi-expendable items added successfully with sequential barcodes.";
                    
                } else {
                    // Single item insertion
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
                        "ssssddiiisssssiiissi",
                        $article_name,
                        $description,
                        $property_no_base,
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
                        $conn->commit();
                        logActivity('Add Semi-Expendable', $inventory_id, "Added new semi-expendable item: $article_name");
                        $_SESSION['success'] = "Semi-expendable item added successfully. Property No: $property_no_base";
                    } else {
                        throw new Exception($conn->error);
                    }
                    $stmt->close();
                }
                
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Database error: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = implode("<br>", $errors);
        }
        
        header('Location: ' . SITE_URL . '/admin/semi_expendable.php');
        exit();
    }
    
    // Handle Edit Semi-Expendable Item
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
        // Handle multi-select for certified_correct
        $certified_correct_array = isset($_POST['certified_correct']) && is_array($_POST['certified_correct']) 
            ? array_filter(array_map('intval', $_POST['certified_correct'])) 
            : [];
        $certified_correct = !empty($certified_correct_array) ? json_encode($certified_correct_array) : null;
        // Handle multi-select for approved_by
        $approved_by_array = isset($_POST['approved_by']) && is_array($_POST['approved_by']) 
            ? array_filter(array_map('intval', $_POST['approved_by'])) 
            : [];
        $approved_by = !empty($approved_by_array) ? json_encode($approved_by_array) : null;
        // Handle multi-select for verified_by
        $verified_by_array = isset($_POST['verified_by']) && is_array($_POST['verified_by']) 
            ? array_filter(array_map('intval', $_POST['verified_by'])) 
            : [];
        $verified_by = !empty($verified_by_array) ? json_encode($verified_by_array) : null;
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
            "sssddiissssiiissi",
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
            logActivity('Edit Semi-Expendable', $id, "Edited semi-expendable item: $article_name");
            $_SESSION['success'] = "Semi-expendable item updated successfully";
        } else {
            $_SESSION['error'] = "Error updating item: " . $conn->error;
        }
        $stmt->close();
        
        header('Location: ' . SITE_URL . '/admin/semi_expendable.php');
        exit();
    }
}

// Handle Delete Semi-Expendable Item (with prepared statement)
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    // Verify CSRF token for GET requests
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
            logActivity('Delete Semi-Expendable', $id, "Deleted semi-expendable item ID: $id");
            $_SESSION['success'] = "Semi-expendable item deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting item or item not found";
        }
        $stmt->close();
    }
    
    header('Location: ' . SITE_URL . '/admin/semi_expendable.php');
    exit();
}

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 999999;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Build query for Semi-Expendable items only (using prepared statements for search)
$query = "
    SELECT i.*, e.name as equipment_name, s.name as section_name,
           CONCAT(ap.firstname, ' ', ap.lastname) as approver_name,
           CONCAT(vr.firstname, ' ', vr.lastname) as verifier_name,
           CONCAT(al.firstname, ' ', al.lastname) as allocatee_name,
           CONCAT(cr.firstname, ' ', cr.lastname) as created_by_name,
           (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') as is_issued,
           CASE WHEN i.property_no LIKE '%-%' THEN 1 ELSE 0 END as is_multiple
    FROM inventory i
    LEFT JOIN equipment e ON i.equipment_id = e.id
    LEFT JOIN sections s ON i.section_id = s.id
    LEFT JOIN users ap ON i.approved_by = ap.id
    LEFT JOIN users vr ON i.verified_by = vr.id
    LEFT JOIN users al ON i.allocate_to = al.id
    LEFT JOIN users cr ON i.created_by = cr.id
    WHERE i.category = 'Semi-Expendable' OR i.type_equipment = 'Semi-Expendable'
";
$count_query = "SELECT COUNT(*) as total FROM inventory WHERE category = 'Semi-Expendable' OR type_equipment = 'Semi-Expendable'";

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

$semi_items = [];
while ($row = $result->fetch_assoc()) {
    $semi_items[] = $row;
}
$stmt->close();

// calculate total quantity per base property (for multiple sets)
$counts = [];
foreach ($semi_items as $r) {
    $base = preg_replace('/-\d+$/', '', $r['property_no']);
    if (!isset($counts[$base])) {
        $counts[$base] = 0;
    }
    if (!empty($r['is_multiple'])) {
        $counts[$base] += 1;
    } else {
        $counts[$base] += floatval($r['qty_physical_count']);
    }
}
foreach ($semi_items as &$r) {
    $base = preg_replace('/-\d+$/', '', $r['property_no']);
    $r['total_qty'] = $counts[$base] ?? $r['qty_physical_count'];
}
unset($r);

// Create pagination data structure
$pagination_data = [
    'data' => $semi_items,
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

// NOW include the header - after all AJAX handlers
include INCLUDE_PATH . '/header.php';
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

<!-- The rest of your HTML code continues here... -->
<!-- Statistics Cards, Quick Actions, Search, Table, Modals, etc. -->

<!-- Statistics Cards for Semi-Expendable -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-box-open"></i>
        </div>
        <h3>Total Semi-Expendable</h3>
        <?php
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory WHERE category = 'Semi-Expendable' OR type_equipment = 'Semi-Expendable'");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_semi = $result->fetch_assoc()['count'];
        $stmt->close();
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
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT ei.inventory_id) as count 
            FROM equipment_issuance ei
            JOIN inventory i ON ei.inventory_id = i.id
            WHERE (i.category = 'Semi-Expendable' OR i.type_equipment = 'Semi-Expendable') AND ei.status = 'issued'
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $issued_semi = $result->fetch_assoc()['count'];
        $stmt->close();
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
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM inventory 
            WHERE (category = 'Semi-Expendable' OR type_equipment = 'Semi-Expendable') AND qty_physical_count <= 5
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $low_stock_semi = $result->fetch_assoc()['count'];
        $stmt->close();
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
        $stmt = $conn->prepare("
            SELECT SUM(unit_value * qty_physical_count) as total 
            FROM inventory 
            WHERE category = 'Semi-Expendable' OR type_equipment = 'Semi-Expendable'
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_value = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
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
        <!-- Test button -->
        <button class="btn btn-info" onclick="testBarcodeGenerator()" style="background-color: #17a2b8;">
            <i class="fas fa-flask"></i> Test Barcode
        </button>
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
        <?php
            // compute unique items by base property (to match table logic below)
            $uniqueCount = 0;
            $seen = [];
            foreach ($semi_items as $it) {
                $base = preg_replace('/-\d+$/', '', $it['property_no']);
                if (!isset($seen[$base])) {
                    $seen[$base] = true;
                    $uniqueCount++;
                }
            }
        ?>
        <p>Showing <?php echo $uniqueCount; ?> of <?php echo $total_rows; ?> items</p>
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
            <?php if (count($semi_items) > 0): ?>
                
                <?php
                // display only one entry per base property number when multiples exist
                $shown = [];
                foreach ($semi_items as $item):
                    $base = preg_replace('/-\d+$/', '', $item['property_no']);
                    if (isset($shown[$base])) continue;
                    $shown[$base] = true;
            ?>
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
                        <?php
                            // if part of a multiple set, show aggregated quantity
                           
                            echo $item['qty_physical_count'] . ' ' . $item['uom'];
                        
                        ?>
                       
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
                         <!--  Show "View All Barcodes" button only if it's a multiple item -->
                     <!--  Show "View All Barcodes" button only if it's a multiple item -->
    <?php if ($item['is_multiple']):
        // Determine if this is part of a multiple set with more than one item
        // For multiple items, we need to check if there are actually multiple records
        // The property_no will have a suffix like -001, -002, etc.
        
        // Count how many items with the same base property number exist
        $baseProperty = preg_replace('/-\d+$/', '', $item['property_no']);
        
        // We need to query or use the already available data to determine count
        // Since we already have $counts array from earlier in the code, we can use that
        $multipleCount = isset($counts[$baseProperty]) ? $counts[$baseProperty] : 1;
        
        // Enable button only if there is more than 1 item in this set
        $hasMultipleItems = $multipleCount > 1;
    ?>
        <button class="action-btn"
                style="background-color: pink; <?php echo $hasMultipleItems ? '' : 'opacity:0.6;cursor:not-allowed;'; ?>"
                <?php echo $hasMultipleItems ? "onclick=\"viewAllBarcodes('{$item['property_no']}', '" . htmlspecialchars($item['article_name']) . "')\"" : ''; ?>
                title="<?php echo $hasMultipleItems ? 'View All Barcodes' : 'Only 1 item in this set'; ?>"
                <?php echo $hasMultipleItems ? '' : 'disabled'; ?>>
            <i class="fas fa-layer-group"></i>
        </button>
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
                    <td colspan="10" class="text-center">
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
    <?php echo displayPagination($pagination_data, '?page=' . ($search ? '&search=' . urlencode($search) : '')); ?>
</div>

<!-- Sticky SCAN BARCODE Button (Bottom Right of Table) -->
    <div class="sticky-scan-button-container">
        <a href="<?php echo SITE_URL; ?>/admin/barcodescannerforsemi_expendable.php" 
           class="sticky-scan-button">
            <i class="fas fa-camera"></i> 
            <span>SCAN BARCODE</span>
        </a>
    </div>
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
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="generate_multiple_barcodes" id="generate_multiple_barcodes" value="0">
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
                                   min="0.01" step="0.01" required onchange="checkQuantityForMultipleBarcodes()">
                        </div>
                    </div>
                    
                    <!-- Multiple Barcodes Option -->
                    <div id="multipleBarcodeOption" class="form-group" style="display: none; margin-top: 15px; padding: 15px; background: #e8f4f8; border-radius: 8px; border-left: 4px solid #F16D34;">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="multiple_barcodes" onchange="toggleMultipleBarcodes()">
                            <label class="form-check-label" for="multiple_barcodes">
                                <strong>Generate individual barcodes for each item (<span id="itemCountDisplay">1</span> items)</strong>
                            </label>
                            <small class="form-text text-muted" style="display: block; margin-top: 5px;">
                                When checked, each item will have its own unique barcode (e.g., SEMI-YYYYMMDD-001, SEMI-YYYYMMDD-002, etc.)
                            </small>
                        </div>
                        
                        <div id="barcodePreviewContainer" style="margin-top: 15px; display: none;">
                            <label>Barcode Preview:</label>
                            <div id="multipleBarcodePreview" class="barcode-preview-grid" style="max-height: 300px; overflow-y: auto;"></div>
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
                                <option value="Serviceable" <?php echo ($edit_item && $edit_item['condition_text'] == 'Serviceable') ? 'selected' : ''; ?>>Serviceable</option>
                                <option value="Non-Serviceable" <?php echo ($edit_item && $edit_item['condition_text'] == 'Non-Serviceable') ? 'selected' : ''; ?>>Non-Serviceable</option>
                                <option value="For Condemn" <?php echo ($edit_item && $edit_item['condition_text'] == 'For Condemn') ? 'selected' : ''; ?>>For Condemn</option>
                                <option value="Under Repair" <?php echo ($edit_item && $edit_item['condition_text'] == 'Under Repair') ? 'selected' : ''; ?>>Under Repair</option>
                                <option value="For Disposal" <?php echo ($edit_item && $edit_item['condition_text'] == 'For Disposal') ? 'selected' : ''; ?>>For Disposal</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="certified_correct">Certified Correct By (Multi-Select)</label>
                            <?php 
                            $certified_selected = [];
                            if ($edit_item && !empty($edit_item['certified_correct'])) {
                                $certified_data = json_decode($edit_item['certified_correct'], true);
                                $certified_selected = is_array($certified_data) ? $certified_data : [];
                            }
                            ?>
                            <select class="form-control searchable-select" id="certified_correct" name="certified_correct[]" multiple>
                                <?php if ($users): mysqli_data_seek($users, 0); while($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo in_array($user['id'], $certified_selected) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                            <small class="form-text text-muted">Search and select multiple users</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="approved_by">Approved By (Multi-Select)</label>
                            <?php 
                            $approved_selected = [];
                            if ($edit_item && !empty($edit_item['approved_by'])) {
                                $approved_data = json_decode($edit_item['approved_by'], true);
                                $approved_selected = is_array($approved_data) ? $approved_data : [];
                            }
                            ?>
                            <select class="form-control searchable-select" id="approved_by" name="approved_by[]" multiple>
                                <?php if ($users): mysqli_data_seek($users, 0); while($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo in_array($user['id'], $approved_selected) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                            <small class="form-text text-muted">Search and select multiple users</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="verified_by">Verified By (Multi-Select)</label>
                            <?php 
                            $verified_selected = [];
                            if ($edit_item && !empty($edit_item['verified_by'])) {
                                $verified_data = json_decode($edit_item['verified_by'], true);
                                $verified_selected = is_array($verified_data) ? $verified_data : [];
                            }
                            ?>
                            <select class="form-control searchable-select" id="verified_by" name="verified_by[]" multiple>
                                <?php if ($users): mysqli_data_seek($users, 0); while($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>" 
                                    <?php echo in_array($user['id'], $verified_selected) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                            <small class="form-text text-muted">Search and select multiple users</small>
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
                                   placeholder="Enter or generate barcode (for multiple items, this will be the base)"
                                   pattern="[A-Za-z0-9\-_]+"
                                   title="Use only letters, numbers, hyphens and underscores">
                            <button type="button" class="btn btn-secondary" onclick="generateBarcodeForEdit()" style="margin-top: 5px;">
                                <i class="fas fa-sync-alt"></i> Generate Barcode
                            </button>
                        </div>
                        <div id="barcodePreview" class="barcode-preview" style="margin-top: 10px; text-align: center;"></div>
                        <small class="form-text text-muted">For multiple items, this will be the base barcode (e.g., SEMI-20260310 will become SEMI-20260310-001, SEMI-20260310-002, etc.)</small>
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

<!-- View Item Modal (Updated with Barcode Display and Multiple Set Info) -->
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

<!-- View All Barcodes Modal -->
<div id="viewAllBarcodesModal" class="modal">
    <div class="modal-content" style="max-width: 900px; max-height: 85vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="flex-shrink: 0;">
            <h2 id="allBarcodesModalTitle">All Barcodes</h2>
            <span class="modal-close" onclick="closeAllBarcodesModal()">&times;</span>
        </div>
        <div class="modal-body" id="allBarcodesContent" style="overflow-y: auto; flex: 1; padding: 20px;">
            <!-- Content will be loaded dynamically -->
        </div>
        <div class="modal-footer" style="flex-shrink: 0; padding: 15px 20px; border-top: 1px solid #BBE0EF; text-align: right;">
            <button type="button" class="btn btn-primary" onclick="printAllBarcodes()">
                <i class="fas fa-print"></i> Print All
            </button>
            <button type="button" class="btn btn-secondary" onclick="closeAllBarcodesModal()">Close</button>
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
                    <input type="text" class="form-control" id="barcode_prefix" name="barcode_prefix" value="SEMI" maxlength="10" pattern="[A-Za-z0-9\-_]+" title="Use only letters, numbers, hyphens and underscores">
                    <small class="form-text text-muted">Prefix for the barcode (e.g., SEMI) - letters, numbers, hyphens, underscores only</small>
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
:root {
    --primary: #6B8CFF;        /* Deeper Periwinkle - Main brand color */
    --secondary: #8FB5FF;       /* Medium Blue - Secondary elements */
    --accent: #F8B0C0;          /* Muted Pink - Highlights, buttons */
    --accent-light: #FFD8E0;    /* Light Pink - Soft highlights */
    --success-light: #C5E8C5;   /* Muted Mint - Success backgrounds */
    --light: #F0F0F0;           /* Light Gray - Page background */
    --white: #FFFFFF;           /* White - Cards, containers */
    --border-light: #E0E0E0;    /* Light Gray for borders */
    --text-primary: #3A3A3A;    /* Dark gray for main text */
    --text-secondary: #6B6B6B;  /* Medium gray for secondary text */
    --text-muted: #9E9E9E;      /* Light gray for muted text */
    --text-light: #FFFFFF;      /* White text for dark backgrounds */
    --success: #4CAF50;
    --danger: #f44336;
    --info: #8FB5FF;
}

body {
    background-color: var(--light);
    color: var(--text-primary);
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

.table-header p {
    color: var(--text-muted);
    font-size: 14px;
    margin: 0;
}

/* Search Box */
.search-box {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.search-box input[type="text"],
.search-box select {
    padding: 12px 15px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 14px;
    flex: 1;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.search-box input[type="text"]:focus,
.search-box select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.search-box button {
    padding: 12px 24px;
    background: var(--primary);
    color: var(--text-light);
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.search-box button:hover {
    background: #5a7ae6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.3);
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

tr.stock-alert-row {
    background-color: white;
}

tr.stock-alert-row:hover {
     background-color: #f0c0d0;
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
.action-btn.view { background-color: var(--primary); }
.action-btn.delete { background-color: var(--danger); }
.action-btn.success { background-color: var(--success); }

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Badges */
.badge-warning {
    background-color: var(--secondary);
    color: var(--text-primary);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
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

.btn-info {
    background-color: var(--info);
    color: var(--text-light);
}

.btn-info:hover {
    background-color: #7a9fe6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(143, 181, 255, 0.3);
}

.btn-xs {
    padding: 4px 8px;
    font-size: 11px;
    border-radius: 4px;
    background-color: var(--secondary);
    color: var(--text-light);
    border: none;
    cursor: pointer;
}

.btn-xs:hover {
    background-color: #7a9fe6;
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
    max-height: calc(90vh - 150px);
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 25px;
    border-top: 1px solid var(--border-light);
    text-align: right;
    background: var(--light);
    border-radius: 0 0 12px 12px;
}

/* Form Section */
.form-section {
    background: var(--white);
    padding: 20px;
    margin-bottom: 25px;
    border-radius: 10px;
    border-left: 4px solid var(--primary);
    box-shadow: 0 2px 8px rgba(107, 140, 255, 0.1);
}

.form-section h3 {
    color: var(--primary);
    margin-bottom: 20px;
    font-size: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--accent-light);
}

.form-section h3 i {
    color: var(--accent);
    margin-right: 10px;
}

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

.form-control[readonly], .form-control[disabled] {
    background-color: var(--light);
    color: var(--text-secondary);
    cursor: not-allowed;
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-row .form-group {
    flex: 1;
}

.form-text {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 5px;
}

/* Barcode Preview Grid */
.barcode-preview-grid,
.all-barcodes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin: 15px 0;
    padding: 15px;
    background: var(--light);
    border-radius: 8px;
    max-height: 400px;
    overflow-y: auto;
}

.barcode-preview-card,
.barcode-item-card {
    background: var(--white);
    border: 1px solid var(--border-light);
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    transition: all 0.2s;
}

.barcode-preview-card:hover,
.barcode-item-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.15);
    border-color: var(--primary);
}

.barcode-preview-card .item-number,
.barcode-item-card .item-property {
    font-weight: bold;
    color: var(--primary);
    margin-bottom: 10px;
    font-size: 13px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--accent-light);
}

.barcode-img {
    margin: 10px 0;
    padding: 10px;
    background: var(--white);
}

.barcode-img img {
    max-width: 100%;
    height: auto;
}

.barcode-value {
    font-family: monospace;
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 8px;
    word-break: break-all;
}

/* Barcode Detail Item */
.barcode-detail-item {
    margin: 15px 0;
    padding: 20px;
    background: var(--light);
    border-radius: 8px;
    border-left: 4px solid var(--accent);
}

.barcode-detail-item .barcode-label {
    font-weight: bold;
    color: var(--primary);
    margin-bottom: 10px;
}

.barcode-detail-item .barcode-image {
    text-align: center;
    padding: 15px;
    background: var(--white);
    border-radius: 5px;
}

.barcode-detail-item .barcode-image img {
    max-width: 100%;
    height: auto;
    max-height: 60px;
}

.barcode-detail-item .barcode-value {
    font-family: monospace;
    text-align: center;
    margin-top: 10px;
    color: var(--accent);
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

/* Loading Indicator */
.loading,
.loading-spinner {
    text-align: center;
    padding: 30px;
    color: var(--text-muted);
}

.loading i,
.loading-spinner i {
    font-size: 32px;
    margin-bottom: 15px;
    color: var(--primary);
}

/* Text Utilities */
.text-muted {
    color: var(--text-muted) !important;
}

.text-danger {
    color: var(--danger) !important;
}

.text-info {
    color: var(--info) !important;
}

.text-center {
    text-align: center;
}

.mt-3 {
    margin-top: 15px;
}

/* Form Check */
.form-check {
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-check-input {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--accent);
}

.form-check-label {
    font-size: 14px;
    cursor: pointer;
}

/* Pagination Styles */
.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 30px;
}

.pagination a,
.pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 35px;
    height: 35px;
    padding: 0 8px;
    border-radius: 6px;
    background: var(--white);
    color: var(--text-secondary);
    text-decoration: none;
    border: 1px solid var(--border-light);
    transition: all 0.2s;
}

.pagination a:hover {
    background: var(--primary);
    color: var(--text-light);
    border-color: var(--primary);
}

.pagination .active {
    background: var(--primary);
    color: var(--text-light);
    border-color: var(--primary);
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-cards {
        grid-template-columns: 1fr;
    }
    
    .search-box {
        flex-direction: column;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .action-buttons {
        justify-content: flex-start;
    }
    
    .modal-content {
        margin: 20px;
        width: auto;
    }
    
    .barcode-preview-grid,
    .all-barcodes-grid {
        grid-template-columns: 1fr;
    }
}
/* Sticky SCAN BARCODE Button - Bottom Right of Table */
.sticky-scan-button-container {
    position: sticky;
    bottom: 30px;
    display: flex;
    justify-content: flex-end;
    margin-top: -50px;
    padding-right: 20px;
    padding-bottom: 20px;
    pointer-events: none;
    z-index: 100;
}

.sticky-scan-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 16px 32px;
    background: linear-gradient(135deg, var(--accent) 0%, #e69eb0 100%);
    color: var(--text-primary);
    font-weight: bold;
    font-size: 16px;
    border-radius: 60px;
    text-decoration: none;
    box-shadow: 0 10px 30px rgba(248, 176, 192, 0.6);
    border: 2px solid white;
    pointer-events: auto;
    transition: all 0.3s ease;
    animation: pulse-sticky-table 2s infinite;
    letter-spacing: 0.5px;
    backdrop-filter: blur(5px);
    border: 1px solid rgba(255, 255, 255, 0.3);
}

.sticky-scan-button i {
    font-size: 20px;
    filter: drop-shadow(0 2px 4px rgba(0,0,0,0.1));
}

.sticky-scan-button:hover {
    transform: translateY(-5px) scale(1.05);
    box-shadow: 0 20px 40px rgba(248, 176, 192, 0.8);
    background: linear-gradient(135deg, #e69eb0 0%, var(--accent) 100%);
    border-color: var(--primary);
}

@keyframes pulse-sticky-table {
    0% {
        box-shadow: 0 10px 30px rgba(248, 176, 192, 0.6);
        transform: scale(1);
    }
    50% {
        box-shadow: 0 15px 40px rgba(248, 176, 192, 0.9);
        transform: scale(1.05);
    }
    100% {
        box-shadow: 0 10px 30px rgba(248, 176, 192, 0.6);
        transform: scale(1);
    }
}

/* Add a subtle glow effect */
.sticky-scan-button::after {
    content: '';
    position: absolute;
    top: -2px;
    left: -2px;
    right: -2px;
    bottom: -2px;
    background: linear-gradient(135deg, var(--accent-light), var(--accent));
    border-radius: 60px;
    z-index: -1;
    opacity: 0;
    transition: opacity 0.3s ease;
}

.sticky-scan-button:hover::after {
    opacity: 0.5;
}

/* For mobile responsiveness */
@media (max-width: 768px) {
    .sticky-scan-button-container {
        bottom: 20px;
        padding-right: 10px;
        padding-bottom: 10px;
    }
    
    .sticky-scan-button {
        padding: 12px 24px;
        font-size: 14px;
        gap: 8px;
    }
    
    .sticky-scan-button i {
        font-size: 16px;
    }
}

/* Ensure button stays within table container */
.table-container {
    position: relative;
    overflow: visible !important;
}

/* Remove the old SCAN BARCODE button from Quick Actions */
/* Find this section in Quick Actions and either remove or comment out the SCAN BARCODE button */
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
    document.getElementById('barcodePreview').innerHTML = '';
    document.getElementById('multipleBarcodeOption').style.display = 'none';
    document.getElementById('barcodePreviewContainer').style.display = 'none';
    document.getElementById('generate_multiple_barcodes').value = '0';
    document.getElementById('itemCountDisplay').textContent = '1';
    calculateTotal();
}

// Close Modal
function closeModal() {
    document.getElementById('semiModal').style.display = 'none';
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

// Close All Barcodes Modal
function closeAllBarcodesModal() {
    document.getElementById('viewAllBarcodesModal').style.display = 'none';
}

// Check quantity for multiple barcodes option
function checkQuantityForMultipleBarcodes() {
    let quantity = parseFloat(document.getElementById('quantity').value);
    let multipleOption = document.getElementById('multipleBarcodeOption');
    let isInteger = Number.isInteger(quantity) && quantity > 1;
    let isEdit = <?php echo $edit_item ? 'true' : 'false'; ?>;
    
    if (isInteger && !isEdit) { // Only show for new items
        multipleOption.style.display = 'block';
        // Update the label text
        document.getElementById('itemCountDisplay').textContent = quantity;
    } else {
        multipleOption.style.display = 'none';
        document.getElementById('multiple_barcodes').checked = false;
        document.getElementById('barcodePreviewContainer').style.display = 'none';
        document.getElementById('generate_multiple_barcodes').value = '0';
    }
}

// Toggle multiple barcodes generation
function toggleMultipleBarcodes() {
    let isChecked = document.getElementById('multiple_barcodes').checked;
    let previewContainer = document.getElementById('barcodePreviewContainer');
    let generateField = document.getElementById('generate_multiple_barcodes');
    
    generateField.value = isChecked ? '1' : '0';
    
    if (isChecked) {
        previewContainer.style.display = 'block';
        previewMultipleBarcodes();
    } else {
        previewContainer.style.display = 'none';
    }
}

// Preview multiple barcodes based on quantity
function previewMultipleBarcodes() {
    let quantity = parseInt(document.getElementById('quantity').value);
    if (isNaN(quantity) || quantity < 1) {
        quantity = 1;
    }
    
    let prefix = 'SEMI';
    let previewDiv = document.getElementById('multipleBarcodePreview');
    
    previewDiv.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Generating preview...</div>';
    
    // Add timestamp to prevent caching
    let timestamp = new Date().getTime();
    let url = '?generate_multiple_preview=1&prefix=' + encodeURIComponent(prefix) + '&quantity=' + quantity + '&t=' + timestamp;
    
    console.log('Fetching from URL:', url);
    
    fetch(url)
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers.get('content-type'));
            
            // Check if response is OK
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            
            // Check content type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                // If not JSON, get the text to see what's being returned
                return response.text().then(text => {
                    console.error('Received non-JSON response. First 500 characters:');
                    console.error(text.substring(0, 500));
                    
                    // Try to extract error message from HTML
                    let errorMatch = text.match(/<b>(?:Fatal error|Warning|Parse error)<\/b>:\s*([^<]+)/i);
                    let errorMsg = errorMatch ? errorMatch[1] : 'Unknown error';
                    
                    throw new Error('Server returned HTML instead of JSON. Error: ' + errorMsg);
                });
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Preview data received:', data);
            
            if (data.success) {
                let html = '<p style="margin-bottom: 10px; font-weight: bold;">Barcodes to be generated:</p>';
                
                if (data.barcodes && data.barcodes.length > 0) {
                    data.barcodes.forEach(barcode => {
                        html += `
                            <div style="display: flex; align-items: center; gap: 10px; padding: 5px; border-bottom: 1px solid #eee;">
                                <span style="min-width: 30px; font-weight: bold;">#${barcode.index}:</span>
                                <span style="font-family: monospace; flex: 1;">${escapeHtml(barcode.value)}</span>
                            </div>
                        `;
                    });
                    
                    if (data.total > data.barcodes.length) {
                        html += `<p class="text-muted" style="margin-top: 10px;">... and ${data.total - data.barcodes.length} more items</p>`;
                    }
                } else {
                    html += '<p class="text-muted">No barcodes to preview</p>';
                }
                
                previewDiv.innerHTML = html;
            } else {
                let errorMsg = data.error || 'Unknown error occurred';
                previewDiv.innerHTML = `<div class="alert alert-danger">Error generating preview: ${escapeHtml(errorMsg)}</div>`;
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            previewDiv.innerHTML = `<div class="alert alert-danger">Network error: ${escapeHtml(error.message)}</div>`;
        });
}

// View Item Details - UPDATED WITH CREATED BY FIELD AND MULTIPLE SET INFO
function viewItem(itemId) {
    // Show loading
    document.getElementById('viewModalContent').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Loading item details...</div>';
    document.getElementById('viewModal').style.display = 'block';
    
    // Add timestamp to prevent caching
    let timestamp = new Date().getTime();
    let url = '<?php echo SITE_URL; ?>/api/get_item_details.php?id=' + itemId + '&t=' + timestamp;
    
    console.log('Fetching from URL:', url);
    
    fetch(url)
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers.get('content-type'));
            
            // Check if response is OK
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            
            // Check content type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                // If not JSON, get the text to see what's being returned
                return response.text().then(text => {
                    console.error('Received non-JSON response:', text.substring(0, 200));
                    throw new Error('Server returned non-JSON response. Check console for details.');
                });
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            
            if (data.error) {
                document.getElementById('viewModalContent').innerHTML = '<div class="alert alert-danger">Error: ' + escapeHtml(data.error) + '</div>';
                return;
            }
            
            // Check if this is part of a multiple set by looking for similar property numbers
            let isMultipleSet = data.property_no && data.property_no.includes('-');
            
            let content = `
                <div style="margin-bottom: 20px;">
                    <h3>${escapeHtml(data.article_name)}</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 8px 0;"><strong>Property No:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.property_no || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Description:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.description || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Category:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.category)}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Type of Equipment:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.type_equipment || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Equipment Type:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.equipment_name || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Quantity:</strong
                        td><td style="padding: 8px 0;">${escapeHtml((data.total_qty !== undefined ? data.total_qty : data.qty_physical_count))} ${escapeHtml(data.uom)}</td></tr>
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
                        <tr><td style="padding: 8px 0;"><strong>Created By:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.created_by_name || 'N/A')}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Date Added:</strong></td><td style="padding: 8px 0;">${formatDate(data.date_added)}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Date Updated:</strong></td><td style="padding: 8px 0;">${data.date_updated ? formatDate(data.date_updated) : 'Never'}</td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Remarks:</strong></td><td style="padding: 8px 0;">${escapeHtml(data.remarks || 'N/A')}</td></tr>
                    </table>
                    
                    <div style="margin-top: 25px; border-top: 2px solid #BBE0EF; padding-top: 15px;">
                        <h4 style="color: #F16D34; margin-bottom: 15px;"><i class="fas fa-barcode"></i> Barcode Information</h4>
                        
                        ${data.barcode_data ? `
                            <div class="barcode-detail-item">
                                <div class="barcode-label">Barcode:</div>
                                <div class="barcode-image">
                                    <img src="generate_barcodeppe.php?code=${encodeURIComponent(data.barcode_data)}&format=png&width=300&height=60" 
                                         alt="Barcode" 
                                         onerror="this.style.display='none'; this.parentNode.innerHTML += '<div style=\'font-family: monospace; padding: 10px; background: #f0f0f0;\'>' + escapeHtml('${data.barcode_data}') + '</div>';">
                                </div>
                                <div class="barcode-value">${escapeHtml(data.barcode_data)}</div>
                            </div>
                        ` : '<p class="text-muted">No barcode assigned to this item.</p>'}
                        
                        ${isMultipleSet ? `
                            <div style="margin-top: 15px; padding: 10px; background: #e8f4f8; border-radius: 5px;">
                                <i class="fas fa-info-circle" style="color: #F16D34;"></i>
                                <small> This item is part of a multiple set. <button class="btn btn-xs btn-info" onclick="viewAllBarcodes('${data.property_no}', '${escapeHtml(data.article_name)}')">View All Barcodes</button></small>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
            document.getElementById('viewModalContent').innerHTML = content;
        })
        .catch(error => {
            console.error('Error details:', error);
            document.getElementById('viewModalContent').innerHTML = '<div class="alert alert-danger">Error loading item details: ' + escapeHtml(error.message) + '<br><br>Check browser console for more details.</div>';
        });
}

// View all barcodes for a multiple set
function viewAllBarcodes(propertyNo, itemName) {
    document.getElementById('allBarcodesModalTitle').textContent = 'All Barcodes - ' + escapeHtml(itemName);
    document.getElementById('allBarcodesContent').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Loading barcodes...</div>';
    document.getElementById('viewAllBarcodesModal').style.display = 'block';
    
    // Add timestamp to prevent caching
    let timestamp = new Date().getTime();
    let url = '?get_multiple_items=1&property_no=' + encodeURIComponent(propertyNo) + '&t=' + timestamp;
    
    console.log('Fetching from URL:', url);
    
    fetch(url)
        .then(response => {
            console.log('Response status:', response.status);
            console.log('Response headers:', response.headers.get('content-type'));
            
            // Check if response is OK
            if (!response.ok) {
                throw new Error('HTTP error! status: ' + response.status);
            }
            
            // Check content type
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                // If not JSON, get the text to see what's being returned
                return response.text().then(text => {
                    console.error('Received non-JSON response:', text.substring(0, 200));
                    throw new Error('Server returned non-JSON response. Check console for details.');
                });
            }
            
            return response.json();
        })
        .then(data => {
            console.log('Data received:', data);
            
            if (data.error) {
                document.getElementById('allBarcodesContent').innerHTML = '<div class="alert alert-danger">Error: ' + escapeHtml(data.error) + '</div>';
                return;
            }
            
            if (data.success && data.items.length > 0) {
                let html = `
                    <p><strong>Found ${data.count} items in this set:</strong></p>
                    <div class="all-barcodes-grid">
                `;
                
                data.items.forEach((item, index) => {
                    html += `
                        <div class="barcode-item-card">
                            <div class="item-property">Item ${index + 1}: ${escapeHtml(item.property_no)}</div>
                            <div class="barcode-image" style="text-align: center; margin: 10px 0;">
                                <img src="generate_barcode.php?code=${encodeURIComponent(item.barcode_data)}&width=250&height=60" 
                                     alt="Barcode" style="max-width: 100%; height: auto; border: 1px solid #ddd; padding: 5px; border-radius: 4px;">
                            </div>
                            <div class="barcode-value" style="font-family: monospace; font-size: 13px; padding: 10px; background: var(--light); border-radius: 5px; margin: 10px 0;">${escapeHtml(item.barcode_data)}</div>
                            <div class="item-actions">
                                <button class="btn-xs" onclick="showBarcodeModal('${item.barcode_data}', '${escapeHtml(item.article_name)} - ${escapeHtml(item.property_no)}')">
                                    <i class="fas fa-search"></i> View
                                </button>
                                <button class="btn-xs" onclick="printBarcode('${item.barcode_data}', '${escapeHtml(item.article_name)} - ${escapeHtml(item.property_no)}')">
                                    <i class="fas fa-print"></i> Print
                                </button>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div>';
                document.getElementById('allBarcodesContent').innerHTML = html;
            } else {
                document.getElementById('allBarcodesContent').innerHTML = '<div class="alert alert-warning">No related items found.</div>';
            }
        })
        .catch(error => {
            console.error('Error details:', error);
            document.getElementById('allBarcodesContent').innerHTML = '<div class="alert alert-danger">Error loading barcodes: ' + escapeHtml(error.message) + '<br><br>Check browser console for more details.</div>';
        });
}

// Print all barcodes in the modal
function printAllBarcodes() {
    let content = document.getElementById('allBarcodesContent').innerHTML;
    let title = document.getElementById('allBarcodesModalTitle').textContent;
    
    let printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html>
        <head>
            <title>${escapeHtml(title)}</title>
            <style>
                :root {
                    --primary: #F16D34;
                    --accent: #FF9100;
                    --text-primary: #3A3A3A;
                }
                body { font-family: Arial, sans-serif; padding: 20px; }
                .print-header { text-align: center; margin-bottom: 30px; }
                .print-header h2 { color: var(--primary); }
                .barcodes-grid { 
                    display: grid; 
                    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); 
                    gap: 20px; 
                }
                .barcode-item { 
                    border: 2px solid var(--primary); 
                    padding: 15px; 
                    text-align: center; 
                    border-radius: 8px;
                    page-break-inside: avoid;
                    background: #fff;
                }
                .barcode-item-card {
                    border: 2px solid var(--primary); 
                    padding: 15px; 
                    text-align: center; 
                    border-radius: 8px;
                    page-break-inside: avoid;
                    background: #fff;
                }
                .barcode-image { margin: 10px 0; }
                .barcode-image img { max-width: 100%; height: auto; border: 1px solid #ddd; padding: 5px; border-radius: 4px; }
                .barcode-item img, .barcode-item-card img { max-width: 100%; height: auto; }
                .item-property, .item-property-text { font-weight: bold; color: var(--primary); margin-bottom: 10px; }
                .barcode-value { font-family: monospace; color: var(--text-primary); margin-top: 5px; font-size: 12px; }
                .item-actions { display: none; }
                @media print {
                    body { margin: 0; padding: 10px; }
                    .barcode-item, .barcode-item-card { border: 1px solid #000; }
                    .item-actions { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="print-header">
                <h2>${escapeHtml(title)}</h2>
            </div>
            <div class="barcodes-grid">
                ${content.replace(/<button.*?<\/button>/g, '').replace(/<div class="item-actions">[\s\S]*?<\/div>/g, '')}
            </div>
            <script>
                window.onload = function() { window.print(); setTimeout(function() { window.close(); }, 500); }
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
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
    let semiModal = document.getElementById('semiModal');
    let viewModal = document.getElementById('viewModal');
    let barcodeModal = document.getElementById('barcodeModal');
    let addBarcodeModal = document.getElementById('addBarcodeModal');
    let allBarcodesModal = document.getElementById('viewAllBarcodesModal');
    
    if (event.target == semiModal) {
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
    if (event.target == allBarcodesModal) {
        closeAllBarcodesModal();
    }
}

// Barcode Functions
function generateBarcodeForEdit() {
    let prefix = 'SEMI';
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
    
    // Generate barcode image preview using the barcode generator
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
                    <div class="barcode-value" style="font-family: monospace; font-size: 13px; padding: 10px; background: var(--light); border-radius: 5px; margin: 10px 0;">${barcode.value}</div>
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

// Barcode Modal Functions
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
            .semi-label {
                background: #17a2b8;
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
            <div class="semi-label">Semi-Expendable</div>
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
    let testValue = 'SEMI-TEST-' + new Date().getTime();
    window.open('generate_barcodeppe.php?code=' + testValue + '&format=html', '_blank');
}

// Initialize Select2 for searchable multi-select fields
document.addEventListener('DOMContentLoaded', function() {
    $('.searchable-select').select2({
        placeholder: 'Search and select users...',
        allowClear: true,
        width: '100%',
        theme: 'bootstrap-5'
    });
});
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>
