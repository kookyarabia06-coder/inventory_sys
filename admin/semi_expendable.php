<?php
// Add output buffering at the very top to prevent header warnings
ob_start();

/**
 * Semi-Expendable Items Page (Admin)
 * Complete Semi-Expendable management system with all inventory fields
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

require_once $root_path . '/vendor/autoload.php';
use Picqer\Barcode\BarcodeGeneratorPNG;

/// Require admin role
if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'superadmin', 'supply'])) {
    requireRole('admin' || 'superadmin' || 'supply');
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Semi-Expendable Items';
$page_description = 'Manage Semi-Expendable Inventory';

// Get type of equipment for dropdown
$type_of_equipment = $conn->query("SELECT id, code, name FROM type_of_equipment ORDER BY name");

// Get equipment sub types for dropdown
$equipment_sub_types = $conn->query("
    SELECT est.id, est.code, est.name, est.type_of_equipment_id, toe.name as type_name
    FROM equipment_sub_type est
    LEFT JOIN type_of_equipment toe ON est.type_of_equipment_id = toe.id
    ORDER BY toe.name, est.name
");

// Get users for dropdown
$users = $conn->query("SELECT id, username, firstname, lastname FROM users WHERE status = 'active' ORDER BY firstname, lastname");

$fund_clusters = $conn->query("SELECT id, code, name FROM fund_cluster WHERE status = 'active' ORDER BY name");

// Get suppliers from supplier table
$suppliers = $conn->query("SELECT id, supplier_id, supplier_name FROM supplier WHERE status = 'active' ORDER BY supplier_name");

// JavaScript data
$equipment_sub_type_options = [];
$equipment_sub_types_data = [];
while ($row = $equipment_sub_types->fetch_assoc()) {
    $type_id = $row['type_of_equipment_id'];
    if (!isset($equipment_sub_type_options[$type_id])) {
        $equipment_sub_type_options[$type_id] = [];
    }
    $equipment_sub_type_options[$type_id][] = $row;
    $equipment_sub_types_data[$row['id']] = $row;
}
$equipment_sub_types->data_seek(0);

// ============================================
// HELPER FUNCTION FOR INSERT (UPDATED - removed uom)
// ============================================
function insertSemiItem($conn, $data) {
    // Updated columns - removed uom, added separate UOM fields
    $columns = [
        'article_name', 'description', 'property_no',
        'big_unit', 'small_unit', 'big_quantity', 'pieces_per_big_unit',
        'qty_property_card', 'qty_physical_count', 'unit_value',
        'condition_text', 'remarks', 'certified_correct',
        'approved_by', 'verified_by', 'ref_po_number',
        'delivery_date', 'fund_cluster', 'supplier_id',
        'equipment_id', 'equipment_sub_type_id', 'type_equipment_id',
        'category', 'barcode_data', 'created_by', 'year_acquired'
    ];
    
    // Build the SQL
    $placeholders = implode(',', array_fill(0, count($columns), '?'));
    $sql = "INSERT INTO semi_ppe (" . implode(',', $columns) . ") VALUES ($placeholders)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Build values array in the correct order
    $values = [];
    foreach ($columns as $col) {
        $values[] = $data[$col] ?? '';
    }
    
    // Build type string dynamically
    $types = '';
    foreach ($values as $v) {
        if (is_int($v) || (is_numeric($v) && strpos((string)$v, '.') === false)) {
            $types .= 'i';
        } elseif (is_float($v) || (is_numeric($v) && strpos((string)$v, '.') !== false)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    
    // Bind parameters dynamically
    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < count($values); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $values[$i];
        $bind_names[] = &$$bind_name;
    }
    
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $stmt->close();
    return true;
}

// ============================================
// HELPER FUNCTION FOR UPDATE (UPDATED - removed uom)
// ============================================
function updateSemiItem($conn, $id, $data) {
    $columns = [
        'article_name', 'description',
        'big_unit', 'small_unit', 'big_quantity', 'pieces_per_big_unit',
        'qty_property_card', 'qty_physical_count', 'unit_value',
        'condition_text', 'remarks', 'certified_correct',
        'approved_by', 'verified_by', 'ref_po_number',
        'delivery_date', 'fund_cluster', 'supplier_id',
        'equipment_id', 'equipment_sub_type_id', 'type_equipment_id',
        'category', 'barcode_data', 'year_acquired'
    ];
    
    $set_parts = [];
    foreach ($columns as $col) {
        $set_parts[] = "$col = ?";
    }
    $set_parts[] = "date_updated = NOW()";
    
    $sql = "UPDATE semi_ppe SET " . implode(',', $set_parts) . " WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    // Build values array
    $values = [];
    foreach ($columns as $col) {
        $values[] = $data[$col] ?? '';
    }
    $values[] = $id;
    
    // Build type string
    $types = '';
    foreach ($values as $v) {
        if (is_int($v) || (is_numeric($v) && strpos((string)$v, '.') === false)) {
            $types .= 'i';
        } elseif (is_float($v) || (is_numeric($v) && strpos((string)$v, '.') !== false)) {
            $types .= 'd';
        } else {
            $types .= 's';
        }
    }
    
    // Bind parameters
    $bind_names = [];
    $bind_names[] = $types;
    for ($i = 0; $i < count($values); $i++) {
        $bind_name = 'bind' . $i;
        $$bind_name = $values[$i];
        $bind_names[] = &$$bind_name;
    }
    
    call_user_func_array([$stmt, 'bind_param'], $bind_names);
    
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }
    
    $stmt->close();
    return true;
}

// AJAX endpoints
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_sub_types' && isset($_GET['type_id'])) {
    header('Content-Type: application/json');
    $type_id = (int)$_GET['type_id'];
    
    $stmt = $conn->prepare("SELECT id, code, name FROM equipment_sub_type WHERE type_of_equipment_id = ? ORDER BY name");
    $stmt->bind_param("i", $type_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sub_types = [];
    while ($row = $result->fetch_assoc()) {
        $sub_types[] = $row;
    }
    $stmt->close();
    
    echo json_encode(['success' => true, 'data' => $sub_types]);
    exit;
}

// AJAX endpoint to preview property number format
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_property_preview' && isset($_GET['type_id']) && isset($_GET['sub_type_id'])) {
    header('Content-Type: application/json');
    $type_id = (int)$_GET['type_id'];
    $sub_type_id = (int)$_GET['sub_type_id'];
    $quantity = (int)($_GET['quantity'] ?? 1);
    
    $year = date('Y');
    $month = str_pad(date('m'), 2, '0', STR_PAD_LEFT);
    $day = str_pad(date('d'), 2, '0', STR_PAD_LEFT);
    $base_format = $year . '-' . $month . '-' . $day;
    
    $pattern_like = $base_format . '-%';
    $seq_query = $conn->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(property_no, '-', -1) AS UNSIGNED)) as max_seq 
        FROM semi_ppe 
        WHERE property_no LIKE ?
    ");
    $seq_query->bind_param("s", $pattern_like);
    $seq_query->execute();
    $seq_result = $seq_query->get_result();
    $max_seq = $seq_result->fetch_assoc()['max_seq'] ?? 0;
    $seq_query->close();
    
    $next_seq = $max_seq + 1;
    
    $response = [
        'success' => true,
        'property_format' => $base_format . '-XXXX (next: ' . str_pad($next_seq, 4, '0', STR_PAD_LEFT) . ')',
        'is_multiple' => $quantity > 1 && floor($quantity) == $quantity
    ];
    
    if ($response['is_multiple']) {
        $sequences = [];
        for ($i = 0; $i < min($quantity, 5); $i++) {
            $sequences[] = $base_format . '-' . str_pad($next_seq + $i, 4, '0', STR_PAD_LEFT);
        }
        if ($quantity > 5) {
            $sequences[] = '...';
        }
        $response['sequences'] = $sequences;
    }
    
    echo json_encode($response);
    exit;
}

// AJAX endpoint to check if property number exists
if (isset($_GET['ajax']) && $_GET['ajax'] == 'check_property_number' && isset($_GET['property_no'])) {
    header('Content-Type: application/json');
    $property_no = sanitize($_GET['property_no']);
    
    if (empty($property_no)) {
        echo json_encode(['exists' => false, 'error' => 'No property number provided']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT id FROM semi_ppe WHERE property_no = ? LIMIT 1");
    $stmt->bind_param("s", $property_no);
    $stmt->execute();
    $result = $stmt->get_result();
    $exists = $result->num_rows > 0;
    $stmt->close();
    
    echo json_encode(['exists' => $exists]);
    exit;
}

// Get multiple items for barcode view
if (isset($_GET['get_multiple_items'])) {
    header('Content-Type: application/json');
    $property_no = $_GET['property_no'] ?? '';
    
    if (empty($property_no)) {
        echo json_encode(['error' => 'No property number provided']);
        exit;
    }
    
    $base_property = $property_no;
    
    $stmt = $conn->prepare("
        SELECT i.id, i.property_no, i.article_name, i.barcode_data, 
               i.qty_physical_count as quantity, i.unit_value
        FROM semi_ppe i
        WHERE i.property_no LIKE CONCAT(?, '-%')
        ORDER BY i.property_no
    ");
    $like_pattern = $base_property;
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
            'quantity' => $row['quantity'],
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

// Get single item details for view modal
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_item' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("
        SELECT i.*, 
               e.name as equipment_name, 
               toe.name as type_equipment_name,
               toe.code as type_equipment_code,
               est.name as sub_type_name,
               est.code as sub_type_code,
               s.supplier_name,
               CONCAT(ap.firstname, ' ', ap.lastname) as approver_name,
               CONCAT(vr.firstname, ' ', vr.lastname) as verifier_name,
               CONCAT(cr.firstname, ' ', cr.lastname) as created_by_name,
               (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') as is_issued,
               CASE WHEN i.property_no LIKE '%-%' THEN 1 ELSE 0 END as is_multiple
        FROM semi_ppe i
        LEFT JOIN equipment e ON i.equipment_id = e.id
        LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
        LEFT JOIN equipment_sub_type est ON i.equipment_sub_type_id = est.id
        LEFT JOIN supplier s ON i.supplier_id = s.id
        LEFT JOIN users ap ON i.approved_by = ap.id
        LEFT JOIN users vr ON i.verified_by = vr.id
        LEFT JOIN users cr ON i.created_by = cr.id
        WHERE i.id = ?
        LIMIT 1
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    
    if ($item) {
        if ($item['certified_correct']) {
            $certified_ids = json_decode($item['certified_correct'], true);
            if (is_array($certified_ids)) {
                $certified_names = [];
                $name_stmt = $conn->prepare("SELECT CONCAT(firstname, ' ', lastname) as name FROM users WHERE id = ?");
                foreach ($certified_ids as $uid) {
                    $name_stmt->bind_param("i", $uid);
                    $name_stmt->execute();
                    $name_result = $name_stmt->get_result();
                    if ($name_row = $name_result->fetch_assoc()) {
                        $certified_names[] = $name_row['name'];
                    }
                }
                $name_stmt->close();
                $item['certified_correct_names'] = implode(', ', $certified_names);
            }
        }
        
        if ($item['approved_by']) {
            $approved_ids = json_decode($item['approved_by'], true);
            if (is_array($approved_ids)) {
                $approved_names = [];
                $name_stmt = $conn->prepare("SELECT CONCAT(firstname, ' ', lastname) as name FROM users WHERE id = ?");
                foreach ($approved_ids as $uid) {
                    $name_stmt->bind_param("i", $uid);
                    $name_stmt->execute();
                    $name_result = $name_stmt->get_result();
                    if ($name_row = $name_result->fetch_assoc()) {
                        $approved_names[] = $name_row['name'];
                    }
                }
                $name_stmt->close();
                $item['approved_by_names'] = implode(', ', $approved_names);
            }
        }
        
        if ($item['verified_by']) {
            $verified_ids = json_decode($item['verified_by'], true);
            if (is_array($verified_ids)) {
                $verified_names = [];
                $name_stmt = $conn->prepare("SELECT CONCAT(firstname, ' ', lastname) as name FROM users WHERE id = ?");
                foreach ($verified_ids as $uid) {
                    $name_stmt->bind_param("i", $uid);
                    $name_stmt->execute();
                    $name_result = $name_stmt->get_result();
                    if ($name_row = $name_result->fetch_assoc()) {
                        $verified_names[] = $name_row['name'];
                    }
                }
                $name_stmt->close();
                $item['verified_by_names'] = implode(', ', $verified_names);
            }
        }
        
        echo json_encode(['success' => true, 'data' => $item]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
    }
    exit;
}

// Get item for edit modal
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_edit_item' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    
    $stmt = $conn->prepare("SELECT * FROM semi_ppe WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    
    if ($item) {
        if ($item['certified_correct']) {
            $item['certified_correct_array'] = json_decode($item['certified_correct'], true);
        }
        if ($item['approved_by']) {
            $item['approved_by_array'] = json_decode($item['approved_by'], true);
        }
        if ($item['verified_by']) {
            $item['verified_by_array'] = json_decode($item['verified_by'], true);
        }
        echo json_encode(['success' => true, 'data' => $item]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found']);
    }
    exit;
}
// Get ALL barcodes for items sharing same base property number
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_all_barcodes' && isset($_GET['property_no'])) {
    header('Content-Type: application/json');
    $property_no = sanitize($_GET['property_no']);
    
    // Extract base (remove everything after the LAST dash)
    $last_dash = strrpos($property_no, '-');
    $base = ($last_dash !== false) ? substr($property_no, 0, $last_dash) : $property_no;
    
    // Get ALL items with this base property number (exact match or with suffix)
    $stmt = $conn->prepare("
        SELECT id, property_no, article_name, barcode_data, 
               pieces_per_big_unit, small_unit, big_unit, qty_physical_count
        FROM semi_ppe 
        WHERE property_no = ? OR property_no LIKE CONCAT(?, '-%')
        ORDER BY property_no
    ");
    $stmt->bind_param("ss", $base, $base);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'property_no' => $row['property_no'],
            'article_name' => $row['article_name'],
            'barcode_data' => $row['barcode_data'] ?: $row['property_no'],
            'pieces_per_big_unit' => (int)$row['pieces_per_big_unit'],
            'small_unit' => $row['small_unit'],
            'big_unit' => $row['big_unit'],
            'quantity' => (int)$row['qty_physical_count']
        ];
    }
    $stmt->close();
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'count' => count($items),
        'base_property' => $base
    ]);
    exit;
}


// ============================================
// FUNCTION TO GENERATE PROPERTY NUMBER
// ============================================

function generatePropertyNumber($conn, $type_equipment_id, $equipment_sub_type_id, $sequence_number = null) {
    $year = date('Y');
    $month = str_pad(date('m'), 2, '0', STR_PAD_LEFT);
    $day = str_pad(date('d'), 2, '0', STR_PAD_LEFT);
    $base_pattern = $year . '-' . $month . '-' . $day;
    
    if ($sequence_number !== null) {
        return $base_pattern . '-' . str_pad($sequence_number, 4, '0', STR_PAD_LEFT);
    }
    
    $pattern_like = $base_pattern . '-%';
    $seq_query = $conn->prepare("
        SELECT MAX(CAST(SUBSTRING_INDEX(property_no, '-', -1) AS UNSIGNED)) as max_seq 
        FROM semi_ppe 
        WHERE property_no LIKE ?
    ");
    $seq_query->bind_param("s", $pattern_like);
    $seq_query->execute();
    $seq_result = $seq_query->get_result();
    $max_seq = $seq_result->fetch_assoc()['max_seq'] ?? 0;
    $seq_query->close();
    
    $next_seq = $max_seq + 1;
    
    return $base_pattern . '-' . str_pad($next_seq, 4, '0', STR_PAD_LEFT);
}

// ============================================
// FORM HANDLERS - UPDATED (removed uom JSON)
// ============================================

// Handle Add Semi-Expendable Item
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $article_name = sanitize($_POST['article_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    // Get UOM values directly (no JSON)
    $big_unit = sanitize($_POST['big_unit'] ?? '');
    $big_quantity = floatval($_POST['big_quantity'] ?? 0);
    $small_unit = sanitize($_POST['small_unit'] ?? '');
    $pieces_per_big_unit = floatval($_POST['pieces_per_big_unit'] ?? 1);
    $total_quantity = $big_quantity * $pieces_per_big_unit;
    
    $unit_value = floatval($_POST['unit_value'] ?? 0);
    $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
    $category = 'Semi-Expendable';
    
    $type_equipment_id = !empty($_POST['type_equipment_id']) ? (int)$_POST['type_equipment_id'] : 0;
    $equipment_sub_type_id = !empty($_POST['equipment_sub_type_id']) ? (int)$_POST['equipment_sub_type_id'] : 0;
    
    $fund_cluster = sanitize($_POST['fund_cluster'] ?? '');
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    $ref_po_number = sanitize($_POST['ref_po_number'] ?? '');
    $delivery_date = !empty($_POST['delivery_date']) ? sanitize($_POST['delivery_date']) : null;
    $year_acquired = date('Y');
    $condition_text = sanitize($_POST['condition_text'] ?? 'Serviceable');
    
    $certified_correct_array = isset($_POST['certified_correct']) && is_array($_POST['certified_correct']) 
        ? array_filter(array_map('intval', $_POST['certified_correct'])) : [];
    $certified_correct = !empty($certified_correct_array) ? json_encode(array_values($certified_correct_array)) : '';
    
    $approved_by_array = isset($_POST['approved_by']) && is_array($_POST['approved_by']) 
        ? array_filter(array_map('intval', $_POST['approved_by'])) : [];
    $approved_by = !empty($approved_by_array) ? json_encode(array_values($approved_by_array)) : '';
    
    $verified_by_array = isset($_POST['verified_by']) && is_array($_POST['verified_by']) 
        ? array_filter(array_map('intval', $_POST['verified_by'])) : [];
    $verified_by = !empty($verified_by_array) ? json_encode(array_values($verified_by_array)) : '';
    
    $remarks = sanitize($_POST['remarks'] ?? '');
    $barcode_data = sanitize($_POST['barcode_data'] ?? '');
    $created_by = $_SESSION['user_id'];
    $generate_multiple = isset($_POST['generate_multiple_barcodes']) && $_POST['generate_multiple_barcodes'] == '1';
    
    $errors = [];
    if (empty($article_name)) $errors[] = "Article name is required";
    if (empty($big_unit)) $errors[] = "Big unit is required";
    if (empty($small_unit)) $errors[] = "Small unit is required";
    if ($big_quantity <= 0) $errors[] = "Big quantity must be greater than 0";
    if ($pieces_per_big_unit <= 0) $errors[] = "Pieces per big unit must be greater than 0";
    if ($unit_value <= 0) $errors[] = "Unit value must be greater than 0";
    if (empty($type_equipment_id)) $errors[] = "Type of Equipment is required";
    if (empty($equipment_sub_type_id)) $errors[] = "Equipment Category is required";
    
    if (empty($errors)) {
        $conn->begin_transaction();
        
        try {
            $property_option = $_POST['property_option'] ?? 'auto';
            $manual_property_no = sanitize($_POST['property_number'] ?? '');
            
            if ($property_option == 'custom' && !empty($manual_property_no)) {
                // Custom property number
                $property_no = $manual_property_no;
                
                $check_stmt = $conn->prepare("SELECT id FROM semi_ppe WHERE property_no = ?");
                $check_stmt->bind_param("s", $property_no);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    throw new Exception("Property number already exists: $property_no");
                }
                $check_stmt->close();
                
                if (empty($barcode_data)) {
                    $barcode_data = $property_no;
                }
                
                // Prepare data for insert (no uom JSON)
                $insertData = [
                    'article_name' => $article_name,
                    'description' => $description,
                    'property_no' => $property_no,
                    'big_unit' => $big_unit,
                    'small_unit' => $small_unit,
                    'big_quantity' => $big_quantity,
                    'pieces_per_big_unit' => $pieces_per_big_unit,
                    'qty_property_card' => $total_quantity,
                    'qty_physical_count' => $total_quantity,
                    'unit_value' => $unit_value,
                    'condition_text' => $condition_text,
                    'remarks' => $remarks,
                    'certified_correct' => $certified_correct,
                    'approved_by' => $approved_by,
                    'verified_by' => $verified_by,
                    'ref_po_number' => $ref_po_number,
                    'delivery_date' => $delivery_date,
                    'fund_cluster' => $fund_cluster,
                    'supplier_id' => $supplier_id,
                    'equipment_id' => $equipment_id,
                    'equipment_sub_type_id' => $equipment_sub_type_id,
                    'type_equipment_id' => $type_equipment_id,
                    'category' => $category,
                    'barcode_data' => $barcode_data,
                    'created_by' => $created_by,
                    'year_acquired' => $year_acquired
                ];
                
                insertSemiItem($conn, $insertData);
                $conn->commit();
                $_SESSION['success'] = "Semi-Expendable item added successfully. Property No: $property_no";
                
            } elseif ($generate_multiple && $big_quantity > 1 && floor($big_quantity) == $big_quantity) {
                // MULTIPLE ITEMS MODE - Create one row per BIG UNIT (not per piece)
                $year = date('Y');
                $month = str_pad(date('m'), 2, '0', STR_PAD_LEFT);
                $day = str_pad(date('d'), 2, '0', STR_PAD_LEFT);
                $base_format = $year . '-' . $month . '-' . $day;
                
                $pattern_like = $base_format . '-%';
                $seq_query = $conn->prepare("
                    SELECT MAX(CAST(SUBSTRING_INDEX(property_no, '-', -1) AS UNSIGNED)) as max_seq 
                    FROM semi_ppe 
                    WHERE property_no LIKE ?
                ");
                $seq_query->bind_param("s", $pattern_like);
                $seq_query->execute();
                $seq_result = $seq_query->get_result();
                $start_seq = ($seq_result->fetch_assoc()['max_seq'] ?? 0) + 1;
                $seq_query->close();
                
                // Create one row per BIG UNIT (not per piece)
                $number_of_rows = $big_quantity;
                $success_count = 0;
                
                for ($i = 1; $i <= $number_of_rows; $i++) {
                    $property_no = $base_format . '-' . str_pad($start_seq + $i - 1, 4, '0', STR_PAD_LEFT);
                    $barcode_value = $property_no;
                    
                    $insertData = [
                        'article_name' => $article_name,
                        'description' => $description,
                        'property_no' => $property_no,
                        'big_unit' => $big_unit,
                        'small_unit' => $small_unit,
                        'big_quantity' => 1,  // Each row represents 1 big unit
                        'pieces_per_big_unit' => $pieces_per_big_unit,  // Keep original pieces per big unit
                        'qty_property_card' => $pieces_per_big_unit,  // Total pieces in this row = pieces per big unit
                        'qty_physical_count' => $pieces_per_big_unit,
                        'unit_value' => $unit_value,
                        'condition_text' => $condition_text,
                        'remarks' => $remarks,
                        'certified_correct' => $certified_correct,
                        'approved_by' => $approved_by,
                        'verified_by' => $verified_by,
                        'ref_po_number' => $ref_po_number,
                        'delivery_date' => $delivery_date,
                        'fund_cluster' => $fund_cluster,
                        'supplier_id' => $supplier_id,
                        'equipment_id' => $equipment_id,
                        'equipment_sub_type_id' => $equipment_sub_type_id,
                        'type_equipment_id' => $type_equipment_id,
                        'category' => $category,
                        'barcode_data' => $barcode_value,
                        'created_by' => $created_by,
                        'year_acquired' => $year_acquired
                    ];
                    
                    insertSemiItem($conn, $insertData);
                    $success_count++;
                }
                $conn->commit();
                $_SESSION['success'] = "$success_count Semi-Expendable items added successfully. (Each = 1 $big_unit with $pieces_per_big_unit $small_unit)";
                
            } else {
                // SINGLE ITEM MODE - Auto-generate property number
                $property_no = generatePropertyNumber($conn, $type_equipment_id, $equipment_sub_type_id);
                
                if (empty($barcode_data)) {
                    $barcode_data = $property_no;
                }
                
                $insertData = [
                    'article_name' => $article_name,
                    'description' => $description,
                    'property_no' => $property_no,
                    'big_unit' => $big_unit,
                    'small_unit' => $small_unit,
                    'big_quantity' => $big_quantity,
                    'pieces_per_big_unit' => $pieces_per_big_unit,
                    'qty_property_card' => $total_quantity,
                    'qty_physical_count' => $total_quantity,
                    'unit_value' => $unit_value,
                    'condition_text' => $condition_text,
                    'remarks' => $remarks,
                    'certified_correct' => $certified_correct,
                    'approved_by' => $approved_by,
                    'verified_by' => $verified_by,
                    'ref_po_number' => $ref_po_number,
                    'delivery_date' => $delivery_date,
                    'fund_cluster' => $fund_cluster,
                    'supplier_id' => $supplier_id,
                    'equipment_id' => $equipment_id,
                    'equipment_sub_type_id' => $equipment_sub_type_id,
                    'type_equipment_id' => $type_equipment_id,
                    'category' => $category,
                    'barcode_data' => $barcode_data,
                    'created_by' => $created_by,
                    'year_acquired' => $year_acquired
                ];
                
                insertSemiItem($conn, $insertData);
                $conn->commit();
                $_SESSION['success'] = "Semi-Expendable item added successfully. Property No: $property_no";
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

// Handle Edit Semi-Expendable Item (UPDATED - removed uom JSON)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit' && isset($_POST['id'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $id = (int)$_POST['id'];
    $article_name = sanitize($_POST['article_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    // Get UOM values directly (no JSON)
    $big_unit = sanitize($_POST['big_unit'] ?? '');
    $big_quantity = floatval($_POST['big_quantity'] ?? 0);
    $small_unit = sanitize($_POST['small_unit'] ?? '');
    $pieces_per_big_unit = floatval($_POST['pieces_per_big_unit'] ?? 1);
    $total_quantity = $big_quantity * $pieces_per_big_unit;
    
    $unit_value = floatval($_POST['unit_value'] ?? 0);
    $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
    $type_equipment_id = !empty($_POST['type_equipment_id']) ? (int)$_POST['type_equipment_id'] : 0;
    $equipment_sub_type_id = !empty($_POST['equipment_sub_type_id']) ? (int)$_POST['equipment_sub_type_id'] : 0;
    $fund_cluster = sanitize($_POST['fund_cluster'] ?? '');
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : 0;
    $ref_po_number = sanitize($_POST['ref_po_number'] ?? '');
    $delivery_date = !empty($_POST['delivery_date']) ? sanitize($_POST['delivery_date']) : null;
    $condition_text = sanitize($_POST['condition_text'] ?? 'Serviceable');
    $category = 'Semi-Expendable';
    
    $certified_correct_array = isset($_POST['certified_correct']) && is_array($_POST['certified_correct']) 
        ? array_filter(array_map('intval', $_POST['certified_correct'])) : [];
    $certified_correct = !empty($certified_correct_array) ? json_encode(array_values($certified_correct_array)) : '';
    
    $approved_by_array = isset($_POST['approved_by']) && is_array($_POST['approved_by']) 
        ? array_filter(array_map('intval', $_POST['approved_by'])) : [];
    $approved_by = !empty($approved_by_array) ? json_encode(array_values($approved_by_array)) : '';
    
    $verified_by_array = isset($_POST['verified_by']) && is_array($_POST['verified_by']) 
        ? array_filter(array_map('intval', $_POST['verified_by'])) : [];
    $verified_by = !empty($verified_by_array) ? json_encode(array_values($verified_by_array)) : '';
    
    $remarks = sanitize($_POST['remarks'] ?? '');
    $barcode_data = sanitize($_POST['barcode_data'] ?? '');
    $year_acquired = date('Y');
    
    try {
        $updateData = [
            'article_name' => $article_name,
            'description' => $description,
            'big_unit' => $big_unit,
            'small_unit' => $small_unit,
            'big_quantity' => $big_quantity,
            'pieces_per_big_unit' => $pieces_per_big_unit,
            'qty_property_card' => $total_quantity,
            'qty_physical_count' => $total_quantity,
            'unit_value' => $unit_value,
            'condition_text' => $condition_text,
            'remarks' => $remarks,
            'certified_correct' => $certified_correct,
            'approved_by' => $approved_by,
            'verified_by' => $verified_by,
            'ref_po_number' => $ref_po_number,
            'delivery_date' => $delivery_date,
            'fund_cluster' => $fund_cluster,
            'supplier_id' => $supplier_id,
            'equipment_id' => $equipment_id,
            'equipment_sub_type_id' => $equipment_sub_type_id,
            'type_equipment_id' => $type_equipment_id,
            'category' => $category,
            'barcode_data' => $barcode_data,
            'year_acquired' => $year_acquired
        ];
        
        updateSemiItem($conn, $id, $updateData);
        $_SESSION['success'] = "Semi-Expendable item updated successfully";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating item: " . $e->getMessage();
    }
    
    header('Location: ' . SITE_URL . '/admin/semi_expendable.php');
    exit();
}

// Handle Delete Semi-Expendable Item
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die('Invalid CSRF token');
    }
    
    $id = (int)$_GET['delete'];
    
    $stmt = $conn->prepare("SELECT id FROM equipment_issuance WHERE inventory_id = ? AND status = 'issued' LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $check = $stmt->get_result();
    
    if ($check && $check->num_rows > 0) {
        $_SESSION['error'] = "Cannot delete item that is currently issued";
    } else {
        $stmt = $conn->prepare("DELETE FROM semi_ppe WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $_SESSION['success'] = "Semi-Expendable item deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting item or item not found";
        }
        $stmt->close();
    }
    
    header('Location: ' . SITE_URL . '/admin/semi_expendable.php');
    exit();
}

// Barcode handler
if (isset($_GET['generate_barcode'])) {
    ob_clean();
    header('Content-Type: application/json');
    $barcode_value = $_GET['barcode_value'] ?? '';
    if (empty($barcode_value)) {
        echo json_encode(['error' => 'Please provide barcode value']);
        exit;
    }
    try {
        $generator = new BarcodeGeneratorPNG();
        $barcode = base64_encode($generator->getBarcode($barcode_value, $generator::TYPE_CODE_128));
        echo json_encode(['success' => true, 'barcode' => $barcode, 'value' => $barcode_value]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Multiple barcode preview
if (isset($_GET['generate_multiple_preview'])) {
    ob_clean();
    header('Content-Type: application/json');
    $prefix = $_GET['prefix'] ?? 'SEMI';
    $quantity = min(max(intval($_GET['quantity'] ?? 1), 1), 100);
    $baseBarcode = $prefix . '-' . date('Y-m-d');
    $generator = new BarcodeGeneratorPNG();
    $barcodes = [];
    $preview_count = min($quantity, 10);
    for ($i = 1; $i <= $preview_count; $i++) {
        $barcodeValue = $baseBarcode . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);
        $barcode = base64_encode($generator->getBarcode($barcodeValue, $generator::TYPE_CODE_128));
        $barcodes[] = ['value' => $barcodeValue, 'barcode' => $barcode, 'index' => $i];
    }
    echo json_encode(['success' => true, 'barcodes' => $barcodes, 'total' => $quantity]);
    exit;
}

// ============================================
// DISPLAY DATA (UPDATED - using direct columns)
// ============================================

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 999999;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$query = "
    SELECT i.*, e.name as equipment_name,
           toe.name as type_equipment_name,
           est.name as sub_type_name,
           s.supplier_name,
           CONCAT(ap.firstname, ' ', ap.lastname) as approver_name,
           CONCAT(vr.firstname, ' ', vr.lastname) as verifier_name,
           CONCAT(cr.firstname, ' ', cr.lastname) as created_by_name,
           (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') as is_issued,
           CASE WHEN i.property_no LIKE '%-%' THEN 1 ELSE 0 END as is_multiple
    FROM semi_ppe i
    LEFT JOIN equipment e ON i.equipment_id = e.id
    LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
    LEFT JOIN equipment_sub_type est ON i.equipment_sub_type_id = est.id
    LEFT JOIN supplier s ON i.supplier_id = s.id
    LEFT JOIN users ap ON i.approved_by = ap.id
    LEFT JOIN users vr ON i.verified_by = vr.id
    LEFT JOIN users cr ON i.created_by = cr.id
";

$count_query = "SELECT COUNT(*) as total FROM semi_ppe";

if ($search) {
    $query .= " WHERE (i.article_name LIKE ? OR i.property_no LIKE ? OR i.description LIKE ? OR s.supplier_name LIKE ?)";
    $count_query .= " WHERE (article_name LIKE ? OR property_no LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    
    $main_params = [$search_term, $search_term, $search_term, $search_term];
    $main_types = "ssss";
    
    $count_params = [$search_term, $search_term, $search_term];
    $count_types = "sss";
} else {
    $main_params = [];
    $main_types = "";
    $count_params = [];
    $count_types = "";
}

$query .= " ORDER BY i.date_added DESC";

// count 
$stmt = $conn->prepare($count_query);
if (!empty($count_params)) {
    $stmt->bind_param($count_types, ...$count_params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_rows = $total_result->fetch_assoc()['total'];
$stmt->close();

$offset = ($page - 1) * $per_page;
$query .= " LIMIT ? OFFSET ?";

// Execute main query
$stmt = $conn->prepare($query);
if (!empty($main_params)) {
    $all_params = array_merge($main_params, [$per_page, $offset]);
    $all_types = $main_types . "ii";
    $stmt->bind_param($all_types, ...$all_params);
} else {
    $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

$semi_items = [];
while ($row = $result->fetch_assoc()) {
    // Direct display from columns (no JSON parsing needed)
    $row['big_unit_display'] = !empty($row['big_quantity']) && !empty($row['big_unit']) 
        ? number_format($row['big_quantity'], 0) . ' ' . $row['big_unit'] 
        : '—';
    
    $row['small_unit_display'] = !empty($row['pieces_per_big_unit']) && !empty($row['small_unit']) 
        ? number_format($row['pieces_per_big_unit'], 0) . ' ' . $row['small_unit'] 
        : '—';
    
    $row['quantity_display'] = $row['qty_physical_count'] ?? 0;
    
    $semi_items[] = $row;
}
$stmt->close();

// Group items by base property number
$grouped_items = [];
$shown = [];

foreach ($semi_items as $item) {
    $base = preg_replace('/-\d+$/', '', $item['property_no']);
    
    if (!isset($shown[$base])) {
        $shown[$base] = true;
        $grouped_items[] = $item;
    }
}

$semi_items = $grouped_items;

$pagination_data = [
    'data' => $semi_items,
    'total_rows' => $total_rows,
    'per_page' => $per_page,
    'current_page' => $page,
    'total_pages' => ceil($total_rows / $per_page)
];

include INCLUDE_PATH . '/header.php';
?>

<!-- CSS STYLES (UPDATED: Enhanced View Modal from PPE + Fixed Property Number Radio) -->
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
}

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

/* Add New button in table header */
.btn-add-new {
    background-color: var(--accent);
    color: var(--text-primary);
    padding: 8px 16px;
    border-radius: 8px;
    text-decoration: none;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s;
    border: none;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-add-new:hover {
    background-color: #e69eb0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(248, 176, 192, 0.3);
}

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

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1200px;
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
    background-color: var(--secondary);
    color: var(--text-light);
    border: none;
    cursor: pointer;
}

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
    max-width: 900px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
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

.modal-footer {
    padding: 15px 25px;
    border-top: 1px solid var(--border-light);
    text-align: right;
    background: var(--light);
    border-radius: 0 0 12px 12px;
}

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

.text-muted {
    color: var(--text-muted) !important;
}

.text-danger {
    color: var(--danger) !important;
}

.text-center {
    text-align: center;
}

.mt-3 {
    margin-top: 15px;
}

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

.sticky-scan-button-container {
    position: sticky;
    bottom: 30px;
    display: flex;
    justify-content: flex-end;
    padding-right: 20px;
    padding-bottom: 20px;
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
    transition: all 0.3s ease;
    letter-spacing: 0.5px;
}

.sticky-scan-button i {
    font-size: 20px;
}

.sticky-scan-button:hover {
    transform: translateY(-5px) scale(1.05);
    box-shadow: 0 20px 40px rgba(248, 176, 192, 0.8);
}

.barcode-preview-grid {
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

/* ============================================
   VIEW MODAL ENHANCED STYLES (from PPE)
   ============================================ */

/* View Modal specific container */
.view-modal-container {
    padding: 0;
}

/* Section cards with shadow and hover effect */
.view-modal-container .detail-section {
    background: var(--white);
    border-radius: 16px;
    margin-bottom: 24px;
    box-shadow: 0 2px 8px rgba(107, 140, 255, 0.08);
    transition: box-shadow 0.2s ease;
    overflow: hidden;
}

.view-modal-container .detail-section:hover {
    box-shadow: 0 4px 16px rgba(107, 140, 255, 0.12);
}

/* Section headers with gradient accent */
.view-modal-container .detail-header {
    background: linear-gradient(135deg, var(--light) 0%, var(--white) 100%);
    padding: 16px 20px;
    border-bottom: 2px solid var(--accent-light);
    display: flex;
    align-items: center;
    gap: 10px;
}

.view-modal-container .detail-header i {
    font-size: 18px;
    color: var(--primary);
    background: rgba(107, 140, 255, 0.1);
    padding: 8px;
    border-radius: 12px;
    width: 34px;
    height: 34px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.view-modal-container .detail-header h3 {
    margin: 0;
    font-size: 15px;
    font-weight: 600;
    color: var(--primary);
    letter-spacing: 0.3px;
}

/* Content area padding */
.view-modal-container .detail-content {
    padding: 20px;
}

/* Grid layout for details - 2 columns on desktop */
.view-modal-container .detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px 24px;
}

/* Individual detail item styling */
.view-modal-container .detail-item {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 8px 0;
    border-bottom: 1px dashed var(--border-light);
}

/* Label styling - small, uppercase, muted */
.view-modal-container .detail-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 4px;
}

/* Value styling - clean and readable */
.view-modal-container .detail-value {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
    word-break: break-word;
    line-height: 1.4;
}

/* Status badges inside view modal */
.view-modal-container .status-badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.view-modal-container .status-badge.issued {
    background: var(--accent-light);
    color: var(--accent);
}

.view-modal-container .status-badge.available {
    background: var(--success-light);
    color: var(--success);
}

/* Property number highlight */
.view-modal-container .property-number {
    font-family: 'Courier New', monospace;
    font-size: 14px;
    font-weight: 600;
    background: var(--light);
    padding: 4px 10px;
    border-radius: 6px;
    display: inline-block;
    letter-spacing: 0.5px;
}

/* Value highlight (monetary) */
.view-modal-container .value-highlight {
    font-size: 16px;
    font-weight: 700;
    color: var(--primary);
}

/* Loading state */
.view-modal-loading {
    text-align: center;
    padding: 60px 20px;
}

.view-modal-loading i {
    font-size: 48px;
    color: var(--primary);
    margin-bottom: 16px;
}

.view-modal-loading p {
    color: var(--text-muted);
    font-size: 14px;
}

/* Error state */
.view-modal-error {
    text-align: center;
    padding: 40px 20px;
    background: #fff5f5;
    border-radius: 12px;
    color: var(--danger);
}

/* Responsive: single column on mobile */
@media (max-width: 640px) {
    .view-modal-container .detail-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .view-modal-container .detail-content {
        padding: 16px;
    }
}

/* Optional: Print styles for view modal */
@media print {
    .view-modal-container .detail-section {
        break-inside: avoid;
        page-break-inside: avoid;
        box-shadow: none;
        border: 1px solid #ddd;
    }
    
    .view-modal-container .detail-header {
        background: #f5f5f5;
    }
    
    .modal-footer, .modal-close {
        display: none;
    }
}

/* Additional polish for status/info pills */
.info-pill {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--light);
    padding: 6px 12px;
    border-radius: 30px;
    font-size: 12px;
    color: var(--text-secondary);
}

.info-pill i {
    font-size: 12px;
    color: var(--primary);
}

/* ============================================
   FIXED PROPERTY NUMBER RADIO BUTTONS - NO WRAPPING ISSUES
   ============================================ */

/* Property Number Radio Group */
.property-radio-group {
    background: var(--light);
    padding: 15px;
    border-radius: 10px;
    margin-bottom: 15px;
}

.property-radio-group .form-check {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    margin-bottom: 12px;
    padding: 10px 12px;
    border-radius: 8px;
    transition: background 0.2s;
}

.property-radio-group .form-check:last-child {
    margin-bottom: 0;
}

.property-radio-group .form-check:hover {
    background: rgba(107, 140, 255, 0.08);
}

.property-radio-group .form-check-input {
    width: 18px;
    height: 18px;
    margin-top: 2px;
    flex-shrink: 0;
    cursor: pointer;
    accent-color: var(--primary);
}

.property-radio-group .form-check-label {
    flex: 1;
    cursor: pointer;
    line-height: 1.4;
}

.property-radio-group .form-check-label strong {
    display: block;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.property-radio-group .form-check-label small {
    display: block;
    font-size: 11px;
    font-weight: normal;
    color: var(--text-muted);
    line-height: 1.3;
    margin-top: 2px;
}

/* Auto Generate Section */
#autoGenerateSection {
    margin-top: 15px;
}

.property-preview {
    background: linear-gradient(135deg, #e8f4f8 0%, #ddecf4 100%);
    border-left: 4px solid var(--primary);
    padding: 12px 15px;
    border-radius: 8px;
    margin-top: 10px;
}

.property-preview i {
    color: var(--primary);
    margin-right: 8px;
}

.property-preview strong {
    color: var(--primary);
    font-size: 12px;
    display: block;
    margin-bottom: 5px;
}

.property-preview code {
    font-size: 13px;
    font-weight: 600;
    color: var(--primary);
    background: rgba(107, 140, 255, 0.1);
    padding: 2px 6px;
    border-radius: 4px;
}

/* Custom Property Section */
#customPropertySection {
    margin-top: 15px;
}

.custom-property-input-group {
    display: flex;
    gap: 10px;
    align-items: center;
}

.custom-property-input-group .form-group {
    flex: 3;
    margin-bottom: 0;
}

.custom-property-input-group .form-group:last-child {
    flex: 1;
}

.custom-property-input-group input {
    background: var(--white);
    border: 2px solid var(--border-light);
    padding: 10px 12px;
    font-family: monospace;
    font-size: 14px;
    width: 100%;
}

.custom-property-input-group input:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
    outline: none;
}

.custom-property-input-group button {
    width: 100%;
    white-space: nowrap;
    padding: 10px 12px;
}

#manualPropertyPreview {
    margin-top: 10px;
    padding: 8px 12px;
    border-radius: 6px;
    font-size: 12px;
}

#manualPropertyPreview span {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

/* Responsive */
@media (max-width: 640px) {
    .custom-property-input-group {
        flex-direction: column;
    }
    
    .custom-property-input-group .form-group {
        width: 100%;
    }
    
    .custom-property-input-group button {
        width: 100%;
    }
    
    .property-radio-group .form-check {
        padding: 8px 10px;
    }
    
    .property-radio-group .form-check-label strong {
        font-size: 13px;
    }
    
    .property-radio-group .form-check-label small {
        font-size: 10px;
    }
}

/* Responsive adjustments */
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
}
</style>

<!-- Display Success/Error Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>
<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="dashboard-cards">
    <div class="card"><div class="card-icon"><i class="fas fa-boxes"></i></div><h3>Total Semi-Expendable</h3>
        <?php $stmt = $conn->prepare("SELECT COUNT(*) as count FROM semi_ppe"); $stmt->execute(); $result = $stmt->get_result(); $total_semi = $result->fetch_assoc()['count']; $stmt->close(); ?>
        <div class="card-value"><?php echo $total_semi; ?></div><div class="card-label">All items</div>
    </div>
    <div class="card"><div class="card-icon"><i class="fas fa-hand-holding"></i></div><h3>Issued</h3>
        <?php $stmt = $conn->prepare("SELECT COUNT(DISTINCT ei.inventory_id) as count FROM equipment_issuance ei JOIN semi_ppe i ON ei.inventory_id = i.id WHERE ei.status = 'issued'"); $stmt->execute(); $result = $stmt->get_result(); $issued_semi = $result->fetch_assoc()['count']; $stmt->close(); ?>
        <div class="card-value"><?php echo $issued_semi; ?></div><div class="card-label">Currently issued</div>
    </div>
    <div class="card"><div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div><h3>Low Stock</h3>
        <?php $stmt = $conn->prepare("SELECT COUNT(*) as count FROM semi_ppe WHERE qty_physical_count <= 5"); $stmt->execute(); $result = $stmt->get_result(); $low_stock_semi = $result->fetch_assoc()['count']; $stmt->close(); ?>
        <div class="card-value"><?php echo $low_stock_semi; ?></div><div class="card-label">Need reorder</div>
    </div>
    <div class="card"><div class="card-icon"><i class="fas fa-dollar-sign"></i></div><h3>Total Value</h3>
        <?php $stmt = $conn->prepare("SELECT SUM(unit_value * qty_physical_count) as total FROM semi_ppe"); $stmt->execute(); $result = $stmt->get_result(); $total_value = $result->fetch_assoc()['total'] ?? 0; $stmt->close(); ?>
        <div class="card-value"><?php echo formatCurrency($total_value); ?></div><div class="card-label">Inventory value</div>
    </div>
</div>

<!-- Search -->
<div class="table-container">
    <div class="table-header"><h2><i class="fas fa-search"></i> Search Items</h2></div>
    <form method="GET" action="" class="search-box">
        <input type="text" name="search" placeholder="Search by article name, property no., description, or supplier..." value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit"><i class="fas fa-search"></i> Search</button>
        <?php if ($search): ?><a href="<?php echo SITE_URL; ?>/admin/semi_expendable.php" class="btn btn-secondary"><i class="fas fa-times"></i> Clear</a><?php endif; ?>
    </form>
</div>

<!-- Items Table (with Add New button in header) -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-boxes"></i> Semi-Expendable Items List</h2>
        <div>
            <button class="btn-add-new" onclick="openAddModal()">
                <i class="fas fa-plus-circle"></i> Add New
            </button>
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Article Name</th>
                    <th>Property No.</th>
                    <th>Category/Type</th>
                    <th>Big Unit</th>
                    <th>Small Unit</th>
                    <th>Total Qty</th>
                    <th>Unit Value</th>
                    <th>Total Value</th>
                    <th>Supplier</th>
                    <th>Fund Cluster</th>
                    <th>Status</th>
                    <th>Barcode</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($semi_items) > 0): ?>
                    <?php foreach ($semi_items as $item): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['article_name']); ?></strong><?php if ($item['description']): ?><br><small><?php echo htmlspecialchars(substr($item['description'], 0, 50)); ?></small><?php endif; ?></td>
                        <td><?php echo htmlspecialchars($item['property_no']); ?></td>
                        <td><?php echo htmlspecialchars($item['type_equipment_name'] ?? $item['category']); ?></td>
                        <td><?php echo $item['big_unit_display']; ?></td>
                        <td><?php echo $item['small_unit_display']; ?></td>
                        <td><strong><?php echo number_format($item['quantity_display'], 0); ?></strong></td>
                        <td><?php echo formatCurrency($item['unit_value']); ?></td>
                        <td><?php echo formatCurrency($item['unit_value'] * $item['quantity_display']); ?></td>
                        <td><?php echo htmlspecialchars($item['supplier_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($item['fund_cluster'] ?? 'N/A'); ?></td>
                        <td><?php echo $item['is_issued'] > 0 ? '<span class="badge-warning">Issued</span>' : '<span class="badge-success">Available</span>'; ?></td>
                        <td class="text-center">
                            <?php if (!empty($item['barcode_data'])): ?>
                                <button class="btn-xs" onclick="showBarcodeModal('<?php echo htmlspecialchars($item['barcode_data']); ?>', '<?php echo htmlspecialchars($item['article_name']); ?>', '<?php echo htmlspecialchars($item['property_no']); ?>')">
                                    <i class="fas fa-qrcode"></i> View
                                </button>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <a href="?edit=<?php echo $item['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="action-btn edit"><i class="fas fa-edit"></i></a>
                                <button class="action-btn view" onclick="viewItem(<?php echo $item['id']; ?>)"><i class="fas fa-eye"></i></button>
                                <?php if ($item['is_issued'] == 0): ?>
                                    <a href="?delete=<?php echo $item['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" class="action-btn delete" onclick="return confirm('Are you sure?')"><i class="fas fa-trash"></i></a>
                                <?php endif; ?>
                                <a href="<?php echo SITE_URL; ?>/admin/issue_items.php?item=<?php echo $item['id']; ?>" class="action-btn success"><i class="fas fa-hand-holding"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="13" class="text-center">
                            No items found<br>
                            <button class="btn btn-primary mt-3" onclick="openAddModal()">
                                <i class="fas fa-plus"></i> Add Your First Item
                            </button>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php echo displayPagination($pagination_data, '?page=' . ($search ? '&search=' . urlencode($search) : '')); ?>
</div>

<!-- Sticky SCAN BARCODE Button -->
<div class="sticky-scan-button-container"><a href="<?php echo SITE_URL; ?>/admin/barcodescannerforsemi_expendable.php" class="sticky-scan-button"><i class="fas fa-camera"></i> SCAN BARCODE</a></div>

<!-- Add/Edit Modal -->
<div id="semiModal" class="modal" style="display: <?php echo isset($_GET['edit']) ? 'block' : 'none'; ?>;">
    <div class="modal-content">
        <div class="modal-header"><h2 id="modalTitle"><?php echo isset($_GET['edit']) ? 'Edit Item' : 'Add New Item'; ?></h2><span class="modal-close" onclick="closeModal()">&times;</span></div>
        <div class="modal-body">
            <form method="POST" action="" id="semiForm">
                <input type="hidden" name="action" value="<?php echo isset($_GET['edit']) ? 'edit' : 'add'; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="generate_multiple_barcodes" id="generate_multiple_barcodes" value="0">
                <?php if (isset($_GET['edit'])): ?><input type="hidden" name="id" id="editId" value="<?php echo (int)$_GET['edit']; ?>"><?php else: ?><input type="hidden" name="id" id="editId" value=""><?php endif; ?>
                
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                    <div class="form-group"><label for="article_name">Article Name <span class="text-danger">*</span></label><input type="text" class="form-control" id="article_name" name="article_name" required></div>
                     <div class="form-group"><label for="description">Description</label><textarea class="form-control" id="description" name="description" rows="2"></textarea></div>
                    <div class="form-group">
                        <label>Property Number</label>
                        <div class="property-radio-group">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="property_option" id="property_auto" value="auto" checked onchange="togglePropertyOption()">
                                <label class="form-check-label" for="property_auto">
                                    <strong>Auto-Generate</strong>
                                    <small>YYYY-MM-DD-XXXX format with sequential number</small>
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="property_option" id="property_custom" value="custom" onchange="togglePropertyOption()">
                                <label class="form-check-label" for="property_custom">
                                    <strong>Custom</strong>
                                    <small>Enter your own property number</small>
                                </label>
                            </div>
                        </div>
                        
                        <div id="autoGenerateSection">
                            <div class="property-preview">
                                <i class="fas fa-qrcode"></i>
                                <strong>Auto-generated Format:</strong>
                                <code id="autoPropertyPreviewText">-- Select Type and Category first --</code>
                            </div>
                        </div>
                        <div id="customPropertySection" style="display: none;">
                            <div class="custom-property-input-group">
                                <div class="form-group">
                                    <input type="text" class="form-control" id="property_number" name="property_number" placeholder="e.g., SEMI-2024-001">
                                </div>
                                <div class="form-group">
                                    <button type="button" class="btn btn-secondary" onclick="previewManualPropertyNumber()" style="width: 100%;">
                                        <i class="fas fa-eye"></i> Check
                                    </button>
                                </div>
                            </div>
                            <div id="manualPropertyPreview" class="form-text"></div>
                        </div>
                    </div>
                    
                    
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-tags"></i> Classification</h3>
                    <div class="form-row">
                        <div class="form-group"><label for="type_equipment_id">Type of Equipment <span class="text-danger">*</span></label><select class="form-control" id="type_equipment_id" name="type_equipment_id" required onchange="loadEquipmentSubTypes(); previewPropertyNumber();"><option value="">-- Select --</option><?php $type_of_equipment->data_seek(0); while($type = $type_of_equipment->fetch_assoc()): ?><option value="<?php echo $type['id']; ?>"><?php echo htmlspecialchars($type['code'] . ' - ' . $type['name']); ?></option><?php endwhile; ?></select></div>
                        <div class="form-group"><label for="equipment_sub_type_id">Equipment Category <span class="text-danger">*</span></label><select class="form-control" id="equipment_sub_type_id" name="equipment_sub_type_id" required onchange="previewPropertyNumber();"><option value="">-- First select Type --</option></select></div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-truck"></i> Supplier Information</h3>
                    <div class="form-row">
                        <div class="form-group"><label for="supplier_id">Supplier</label><select class="form-control" id="supplier_id" name="supplier_id"><option value="">-- Select --</option><?php if ($suppliers && $suppliers->num_rows > 0): $suppliers->data_seek(0); while($supp = $suppliers->fetch_assoc()): ?><option value="<?php echo $supp['id']; ?>"><?php echo htmlspecialchars($supp['supplier_name']); ?></option><?php endwhile; endif; ?></select></div>
                        <div class="form-group"><label for="ref_po_number">PO Number</label><input type="text" class="form-control" id="ref_po_number" name="ref_po_number"></div>
                    </div>
                    <div class="form-group"><label for="delivery_date">Delivery Date</label><input type="date" class="form-control" id="delivery_date" name="delivery_date"></div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-calculator"></i> Quantity and Unit of Measure</h3>
                    <div class="form-row">
                        <div class="form-group"><label for="big_unit">Big Unit <span class="text-danger">*</span></label>
                            <select class="form-control" id="big_unit" name="big_unit" required onchange="calculateCompoundTotal()">
                                <option value="">-- Select --</option>
                                <option value="Box">Box</option>
                                <option value="Pack">Pack</option>
                                <option value="Case">Case</option>
                                <option value="Carton">Carton</option>
                                <option value="Bundle">Bundle</option>
                                <option value="Roll">Roll</option>
                                <option value="Set">Set</option>
                                <option value="Ream">Ream</option>
                                <option value="Bottle">Bottle</option>
                                <option value="Can">Can</option>
                            </select>
                        </div>
                        <div class="form-group"><label for="big_quantity">Number of Big Units <span class="text-danger">*</span></label><input type="number" class="form-control" id="big_quantity" name="big_quantity" value="1" min="1" step="1" onchange="calculateCompoundTotal()"></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="small_unit">Small Unit <span class="text-danger">*</span></label>
                            <select class="form-control" id="small_unit" name="small_unit" required onchange="calculateCompoundTotal()">
                                <option value="">-- Select --</option>
                                <option value="Piece">Piece(s)</option>
                                <option value="Unit">Unit(s)</option>
                                <option value="Each">Each</option>
                                <option value="Meter">Meter(s)</option>
                                <option value="Kilogram">Kilogram(s)</option>
                                <option value="Liter">Liter(s)</option>
                            </select>
                        </div>
                        <div class="form-group"><label for="pieces_per_big_unit">Units per Big Unit <span class="text-danger">*</span></label><input type="number" class="form-control" id="pieces_per_big_unit" name="pieces_per_big_unit" value="1" min="1" step="1" onchange="calculateCompoundTotal()"></div>
                    </div>
                    <div class="form-group"><label for="total_quantity_display">Total Quantity</label><input type="text" class="form-control" id="total_quantity_display" readonly><input type="hidden" id="quantity" name="quantity" value="0"></div>
                    <div id="multipleBarcodeOption" style="display: none; margin-top: 15px; padding: 15px; background: #e8f4f8; border-radius: 8px;">
                        <div class="form-check"><input type="checkbox" class="form-check-input" id="multiple_barcodes" onchange="toggleMultipleBarcodes()"><label class="form-check-label" for="multiple_barcodes"><strong>Generate individual barcodes for each big unit</strong></label><br><small class="text-muted">Example: If you have 2 Boxes with 12 pieces each, this will create 2 separate items (1 box = 12 pieces per item)</small></div>
                        <div id="barcodePreviewContainer" style="display: none;"><div id="multipleBarcodePreview"></div></div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-dollar-sign"></i> Value Information</h3>
                    <div class="form-row">
                        <div class="form-group"><label for="unit_value">Unit Value per Small Unit <span class="text-danger">*</span></label><input type="number" class="form-control" id="unit_value" name="unit_value" min="0.01" step="0.01" required onchange="calculateTotal()"></div>
                        <div class="form-group"><label for="total_value">Total Value</label><input type="text" class="form-control" id="total_value" readonly></div>
                    </div>
                    <div class="form-group"><label for="fund_cluster">Fund Cluster</label><select class="form-control" id="fund_cluster" name="fund_cluster"><option value="">-- Select --</option><?php if ($fund_clusters): $fund_clusters->data_seek(0); while($fund = $fund_clusters->fetch_assoc()): ?><option value="<?php echo htmlspecialchars($fund['name']); ?>"><?php echo htmlspecialchars($fund['name']); ?></option><?php endwhile; endif; ?></select></div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-clipboard-check"></i> Certification</h3>
                    <div class="form-row">
                        <div class="form-group"><label for="condition_text">Condition</label><select class="form-control" id="condition_text" name="condition_text"><option value="Serviceable">Serviceable</option><option value="Non-Serviceable">Non-Serviceable</option><option value="For Condemn">For Condemn</option></select></div>
                        <div class="form-group"><label for="certified_correct">Certified Correct By</label><select class="form-control" id="certified_correct" name="certified_correct[]" multiple size="2"><?php if ($users): $users->data_seek(0); while($user = $users->fetch_assoc()): ?><option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></option><?php endwhile; endif; ?></select></div>
                    </div>
                    <div class="form-row">
                        <div class="form-group"><label for="approved_by">Approved By</label><select class="form-control" id="approved_by" name="approved_by[]" multiple size="2"><?php if ($users): $users->data_seek(0); while($user = $users->fetch_assoc()): ?><option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></option><?php endwhile; endif; ?></select></div>
                        <div class="form-group"><label for="verified_by">Verified By</label><select class="form-control" id="verified_by" name="verified_by[]" multiple size="2"><?php if ($users): $users->data_seek(0); while($user = $users->fetch_assoc()): ?><option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></option><?php endwhile; endif; ?></select></div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3><i class="fas fa-tasks"></i> Additional Information</h3>
                    <div class="form-group"><label for="remarks">Remarks</label><textarea class="form-control" id="remarks" name="remarks" rows="2"></textarea></div>
                    <div class="form-group"><label for="barcode_data">Barcode</label><input type="text" class="form-control" id="barcode_data" name="barcode_data" placeholder="Enter or generate barcode"><button type="button" class="btn btn-secondary" onclick="generateBarcodeForEdit()" style="margin-top: 5px;"><i class="fas fa-sync-alt"></i> Generate</button><div id="barcodePreview" class="barcode-preview" style="margin-top: 10px; text-align: center;"></div></div>
                </div>
                
                <div class="form-group"><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?php echo isset($_GET['edit']) ? 'Update' : 'Save'; ?></button><button type="button" class="btn btn-secondary" onclick="closeModal()"><i class="fas fa-times"></i> Cancel</button></div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal (UPDATED with enhanced styling) -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Semi-Expendable Item Details</h2>
            <span class="modal-close" onclick="closeViewModal()">&times;</span>
        </div>
        <div class="modal-body" id="viewModalContent">
            <div class="view-modal-loading">
                <i class="fas fa-spinner fa-pulse"></i>
                <p>Loading item details...</p>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<!-- Barcode Modal -->
<div id="barcodeModal" class="modal">
    <div class="modal-content" style="max-width: 900px; width: 90%;">
        <div class="modal-header">
            <h2 id="barcodeModalTitle">Barcode</h2>
            <span class="modal-close" onclick="closeBarcodeModal()">&times;</span>
        </div>
        <div class="modal-body" id="barcodeModalBody" style="max-height: 70vh; overflow-y: auto;">
            <div id="barcodeModalImage"></div>
            <div id="barcodeModalNumber" style="font-family: monospace; margin-top: 10px;"></div>
        </div>
        <div class="modal-footer" style="padding: 15px 25px; border-top: 1px solid #e0e0e0; display: flex; gap: 10px; justify-content: flex-end;">
            <button class="btn btn-secondary" onclick="closeBarcodeModal()">Close</button>
           <button class="btn btn-primary" onclick="printCurrentBarcode()" id="printBarcodeBtn">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
</div>

<script>
var equipmentSubTypes = <?php echo json_encode($equipment_sub_type_options); ?>;

function loadEquipmentSubTypes() {
    var typeId = document.getElementById('type_equipment_id').value;
    var subTypeSelect = document.getElementById('equipment_sub_type_id');
    subTypeSelect.innerHTML = '<option value="">-- Select --</option>';
    if (!typeId) { subTypeSelect.innerHTML = '<option value="">-- First select Type --</option>'; return; }
    subTypeSelect.disabled = true;
    subTypeSelect.innerHTML = '<option value="">Loading...</option>';
    fetch('?ajax=get_sub_types&type_id=' + encodeURIComponent(typeId))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                data.data.forEach(function(subType) {
                    var option = document.createElement('option');
                    option.value = subType.id;
                    option.textContent = subType.code + ' - ' + subType.name;
                    subTypeSelect.appendChild(option);
                });
                subTypeSelect.disabled = false;
            } else {
                subTypeSelect.innerHTML = '<option value="">-- No categories --</option>';
                subTypeSelect.disabled = false;
            }
        })
        .catch(error => { subTypeSelect.innerHTML = '<option value="">-- Error --</option>'; subTypeSelect.disabled = false; });
}

function togglePropertyOption() {
    var autoSection = document.getElementById('autoGenerateSection');
    var customSection = document.getElementById('customPropertySection');
    var propInput = document.getElementById('property_number');
    var editId = document.getElementById('editId').value;
    if (document.getElementById('property_auto').checked) {
        autoSection.style.display = 'block';
        customSection.style.display = 'none';
        if (!editId) propInput.value = '';
        if (document.getElementById('type_equipment_id').value && document.getElementById('equipment_sub_type_id').value) previewPropertyNumber();
    } else {
        autoSection.style.display = 'none';
        customSection.style.display = 'block';
        if (!editId) propInput.focus();
    }
}

function previewPropertyNumber() {
    if (document.getElementById('property_auto').checked) {
        var typeId = document.getElementById('type_equipment_id').value;
        var subTypeId = document.getElementById('equipment_sub_type_id').value;
        if (!typeId || !subTypeId) { 
            document.getElementById('autoPropertyPreviewText').innerHTML = '-- Select Type and Category first --';
            return; 
        }
        fetch('?ajax=get_property_preview&type_id=' + typeId + '&sub_type_id=' + subTypeId + '&quantity=' + (parseFloat(document.getElementById('quantity').value) || 1))
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('autoPropertyPreviewText').innerHTML = '<code>' + data.property_format + '</code>';
                }
            }).catch(() => {
                document.getElementById('autoPropertyPreviewText').innerHTML = '-- Error generating preview --';
            });
    }
}

function previewManualPropertyNumber() {
    var manual = document.getElementById('property_number').value.trim();
    var preview = document.getElementById('manualPropertyPreview');
    if (manual) {
        fetch('?ajax=check_property_number&property_no=' + encodeURIComponent(manual))
            .then(response => response.json())
            .then(data => {
                if (data.exists) preview.innerHTML = '<span style="color:#f44336;"><i class="fas fa-times-circle"></i> Already exists!</span>';
                else preview.innerHTML = '<span style="color:#4CAF50;"><i class="fas fa-check-circle"></i> Available: ' + escapeHtml(manual) + '</span>';
            }).catch(() => preview.innerHTML = '<span>Checking...</span>');
    } else preview.innerHTML = '';
}

function calculateCompoundTotal() {
    let bigQty = parseFloat(document.getElementById('big_quantity').value) || 0;
    let pieces = parseFloat(document.getElementById('pieces_per_big_unit').value) || 1;
    let total = bigQty * pieces;
    let smallUnit = document.getElementById('small_unit').value || 'pieces';
    let bigUnit = document.getElementById('big_unit').value || '';
    
    // Display the calculation
    let displayText = bigQty + ' ' + bigUnit + ' × ' + pieces + ' ' + smallUnit + ' = ' + total + ' ' + smallUnit;
    document.getElementById('total_quantity_display').value = displayText;
    document.getElementById('quantity').value = total;
    
    // Show/hide multiple barcode option based on big_quantity (not total)
    let isMultiple = bigQty > 1 && !document.getElementById('editId').value;
    let multipleOption = document.getElementById('multipleBarcodeOption');
    if (isMultiple) {
        multipleOption.style.display = 'block';
        if (document.getElementById('multiple_barcodes').checked) { 
            document.getElementById('generate_multiple_barcodes').value = '1'; 
            document.getElementById('barcodePreviewContainer').style.display = 'block'; 
            previewMultipleBarcodes(); 
        }
    } else { 
        multipleOption.style.display = 'none'; 
        document.getElementById('multiple_barcodes').checked = false; 
        document.getElementById('barcodePreviewContainer').style.display = 'none'; 
        document.getElementById('generate_multiple_barcodes').value = '0'; 
    }
    calculateTotal();
    previewPropertyNumber();
}

function toggleMultipleBarcodes() {
    let isChecked = document.getElementById('multiple_barcodes').checked;
    let bigQty = parseFloat(document.getElementById('big_quantity').value) || 0;
    if (isChecked && bigQty > 0) { 
        document.getElementById('generate_multiple_barcodes').value = '1'; 
        document.getElementById('barcodePreviewContainer').style.display = 'block'; 
        previewMultipleBarcodes(); 
    } else { 
        document.getElementById('generate_multiple_barcodes').value = '0'; 
        document.getElementById('barcodePreviewContainer').style.display = 'none'; 
    }
}

function previewMultipleBarcodes() {
    let bigQty = parseFloat(document.getElementById('big_quantity').value) || 1;
    let bigUnit = document.getElementById('big_unit').value || 'ITEM';
    let smallUnit = document.getElementById('small_unit').value || 'PC';
    let piecesPerBig = parseFloat(document.getElementById('pieces_per_big_unit').value) || 1;
    let prefix = bigUnit.substring(0,2).toUpperCase() + '-' + smallUnit.substring(0,2).toUpperCase();
    let preview = document.getElementById('multipleBarcodePreview');
    preview.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Generating...</div>';
    fetch('?generate_multiple_preview=1&prefix=' + encodeURIComponent(prefix) + '&quantity=' + bigQty)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<p>Will create <strong>' + bigQty.toLocaleString() + '</strong> separate items:</p>';
                html += '<div style="background:#f5f5f5;padding:10px;border-radius:5px;margin-top:10px;">';
                html += '<p><strong>Each item will be:</strong><br>';
                html += '• 1 ' + bigUnit + '<br>';
                html += '• ' + piecesPerBig + ' ' + smallUnit + '<br>';
                html += '• Total value: ₱' + (piecesPerBig * (parseFloat(document.getElementById('unit_value').value) || 0)).toFixed(2) + '</p>';
                html += '</div>';
                html += '<p><strong>Property numbers preview:</strong></p><div>';
                data.barcodes.forEach(b => { html += '<div style="padding:5px;"><span style="color:#6B8CFF;">#' + b.index + '</span> <span style="font-family:monospace;">' + escapeHtml(b.value) + '</span></div>'; });
                if (data.total > data.barcodes.length) html += '<p>... and ' + (data.total - data.barcodes.length).toLocaleString() + ' more</p>';
                html += '</div>';
                preview.innerHTML = html;
            } else preview.innerHTML = '<div class="alert alert-danger">Error</div>';
        }).catch(() => preview.innerHTML = '<div class="alert alert-danger">Network error</div>');
}

function calculateTotal() {
    let qty = parseFloat(document.getElementById('quantity').value) || 0;
    let unit = parseFloat(document.getElementById('unit_value').value) || 0;
    document.getElementById('total_value').value = '₱' + (qty * unit).toFixed(2);
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Semi-Expendable Item';
    document.getElementById('semiForm').reset();
    document.getElementById('semiModal').style.display = 'block';
    document.getElementById('property_auto').checked = true;
    document.getElementById('property_number').value = '';
    document.getElementById('property_number').removeAttribute('readonly');
    document.getElementById('manualPropertyPreview').innerHTML = '';
    document.getElementById('autoPropertyPreviewText').innerHTML = '-- Select Type and Category first --';
    togglePropertyOption();
    document.getElementById('big_quantity').value = 1;
    document.getElementById('pieces_per_big_unit').value = 1;
    document.getElementById('type_equipment_id').value = '';
    document.getElementById('equipment_sub_type_id').innerHTML = '<option value="">-- First select Type --</option>';
    document.getElementById('barcodePreview').innerHTML = '';
    calculateCompoundTotal();
}

function closeModal() { 
    document.getElementById('semiModal').style.display = 'none'; 
    let url = new URL(window.location.href); 
    url.searchParams.delete('edit'); 
    window.history.replaceState({}, document.title, url.toString()); 
}

function generateBarcodeForEdit() {
    let bigUnit = document.getElementById('big_unit').value || 'ITEM';
    let smallUnit = document.getElementById('small_unit').value || 'PC';
    let prefix = bigUnit.substring(0,2).toUpperCase() + '-' + smallUnit.substring(0,2).toUpperCase();
    let date = new Date();
    let dateStr = date.getFullYear() + '-' + String(date.getMonth()+1).padStart(2,'0') + '-' + String(date.getDate()).padStart(2,'0');
    let random = Math.floor(1000 + Math.random() * 9000);
    let barcode = prefix + '-' + dateStr + '-' + random;
    document.getElementById('barcode_data').value = barcode;
    let preview = document.getElementById('barcodePreview');
    preview.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Generating...</div>';
    fetch('generate_barcode.php?code=' + encodeURIComponent(barcode) + '&format=png').then(r => r.blob()).then(blob => { 
        let url = URL.createObjectURL(blob); 
        preview.innerHTML = '<img src="' + url + '" style="max-width:200px;border:1px solid #ddd;padding:10px;border-radius:5px;">'; 
    }).catch(() => preview.innerHTML = '');
}

// ============================================
// UPDATED viewItem() function with enhanced styling (from PPE)
// ============================================

function viewItem(id) {
    let modal = document.getElementById('viewModal');
    let content = document.getElementById('viewModalContent');
    modal.style.display = 'block';
    content.innerHTML = '<div class="view-modal-loading"><i class="fas fa-spinner fa-pulse"></i><p>Loading item details...</p></div>';
    
    fetch('?ajax=get_item&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let item = data.data;
                let bigDisplay = (item.big_quantity && item.big_unit && item.big_unit != '0') 
                    ? Number(item.big_quantity).toLocaleString() + ' ' + item.big_unit 
                    : 'N/A';
                let smallDisplay = (item.pieces_per_big_unit && item.small_unit && item.small_unit != '0') 
                    ? Number(item.pieces_per_big_unit).toLocaleString() + ' ' + item.small_unit 
                    : (item.small_unit && item.small_unit != '0' ? item.small_unit : 'N/A');
                
                let statusHtml = item.is_issued 
                    ? '<span class="status-badge issued"><i class="fas fa-hand-holding"></i> Issued</span>'
                    : '<span class="status-badge available"><i class="fas fa-check-circle"></i> Available</span>';
                
                let html = `
                    <div class="view-modal-container">
                        <!-- Basic Information -->
                        <div class="detail-section">
                            <div class="detail-header">
                                <i class="fas fa-info-circle"></i>
                                <h3>Basic Information</h3>
                            </div>
                            <div class="detail-content">
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <div class="detail-label">Article Name</div>
                                        <div class="detail-value"><strong>${escapeHtml(item.article_name)}</strong></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Property No.</div>
                                        <div class="detail-value"><span class="property-number">${escapeHtml(item.property_no)}</span></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Description</div>
                                        <div class="detail-value">${escapeHtml(item.description) || '<em class="text-muted">No description</em>'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Status</div>
                                        <div class="detail-value">${statusHtml}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Classification -->
                        <div class="detail-section">
                            <div class="detail-header">
                                <i class="fas fa-tags"></i>
                                <h3>Classification</h3>
                            </div>
                            <div class="detail-content">
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <div class="detail-label">Type of Equipment</div>
                                        <div class="detail-value">${escapeHtml(item.type_equipment_name) || 'N/A'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Equipment Category</div>
                                        <div class="detail-value">${escapeHtml(item.sub_type_name) || 'N/A'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Condition</div>
                                        <div class="detail-value">${escapeHtml(item.condition_text) || 'Serviceable'}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quantity & Value -->
                        <div class="detail-section">
                            <div class="detail-header">
                                <i class="fas fa-calculator"></i>
                                <h3>Quantity & Value</h3>
                            </div>
                            <div class="detail-content">
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <div class="detail-label">Big Unit</div>
                                        <div class="detail-value">${escapeHtml(bigDisplay)}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Small Unit</div>
                                        <div class="detail-value">${escapeHtml(smallDisplay)}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Total Quantity</div>
                                        <div class="detail-value"><span class="info-pill"><i class="fas fa-boxes"></i> ${Number(item.qty_physical_count).toLocaleString()} ${escapeHtml(item.small_unit)}</span></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Unit Value</div>
                                        <div class="detail-value">₱${Number(item.unit_value).toFixed(2)}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Total Value</div>
                                        <div class="detail-value"><span class="value-highlight">₱${(item.qty_physical_count * item.unit_value).toFixed(2)}</span></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Fund Cluster</div>
                                        <div class="detail-value">${escapeHtml(item.fund_cluster) || 'N/A'}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Supplier Information -->
                        <div class="detail-section">
                            <div class="detail-header">
                                <i class="fas fa-truck"></i>
                                <h3>Supplier Information</h3>
                            </div>
                            <div class="detail-content">
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <div class="detail-label">Supplier</div>
                                        <div class="detail-value">${escapeHtml(item.supplier_name) || 'N/A'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">PO Number</div>
                                        <div class="detail-value">${escapeHtml(item.ref_po_number) || 'N/A'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Delivery Date</div>
                                        <div class="detail-value">${item.delivery_date || 'N/A'}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Personnel -->
                        <div class="detail-section">
                            <div class="detail-header">
                                <i class="fas fa-users"></i>
                                <h3>Personnel</h3>
                            </div>
                            <div class="detail-content">
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <div class="detail-label">Certified Correct By</div>
                                        <div class="detail-value">${escapeHtml(item.certified_correct_names) || 'N/A'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Approved By</div>
                                        <div class="detail-value">${escapeHtml(item.approved_by_names) || 'N/A'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Verified By</div>
                                        <div class="detail-value">${escapeHtml(item.verified_by_names) || 'N/A'}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Additional Information -->
                        ${item.remarks ? `
                        <div class="detail-section">
                            <div class="detail-header">
                                <i class="fas fa-sticky-note"></i>
                                <h3>Remarks</h3>
                            </div>
                            <div class="detail-content">
                                <div class="detail-value">${escapeHtml(item.remarks)}</div>
                            </div>
                        </div>
                        ` : ''}
                        
                        <!-- Created Info -->
                        <div class="detail-section">
                            <div class="detail-header">
                                <i class="fas fa-clock"></i>
                                <h3>System Information</h3>
                            </div>
                            <div class="detail-content">
                                <div class="detail-grid">
                                    <div class="detail-item">
                                        <div class="detail-label">Created By</div>
                                        <div class="detail-value">${escapeHtml(item.created_by_name) || 'N/A'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Date Added</div>
                                        <div class="detail-value">${item.date_added ? new Date(item.date_added).toLocaleString() : 'N/A'}</div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Year Acquired</div>
                                        <div class="detail-value">${item.year_acquired || 'N/A'}</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="view-modal-error"><i class="fas fa-exclamation-triangle"></i><p>Error loading item details</p></div>';
            }
        })
        .catch(() => {
            content.innerHTML = '<div class="view-modal-error"><i class="fas fa-exclamation-triangle"></i><p>Network error loading item</p></div>';
        });
}

//-------------------------------------------------------------------------------------------------------------
function showBarcodeModal(barcode, name, propertyNo) {
    if (propertyNo) {
        // Extract base (remove everything after the last dash)
        let lastDash = propertyNo.lastIndexOf('-');
        let baseProperty = lastDash !== -1 ? propertyNo.substring(0, lastDash) : propertyNo;
        
        fetch('?ajax=get_all_barcodes&property_no=' + encodeURIComponent(baseProperty))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.count > 0) {
                    // Store the items data globally for printing
                    window.currentBarcodeItems = data.items;
                    window.currentBarcodeGroupName = name;
                    
                    // Build display
                    let content = '<div style="max-height: 500px; overflow-y: auto;">';
                    data.items.forEach(function(item, index) {
                        let printCopies = item.pieces_per_big_unit || 1;
                        let copiesText = printCopies > 1 ? ` (${printCopies} copies - one per ${item.small_unit})` : '';
                        
                        content += `
                            <div style="border:1px solid #ddd; border-radius:8px; padding:15px; margin-bottom:15px; text-align:center;">
                                <div style="background:#F8B0C0; display:inline-block; padding:3px 12px; border-radius:20px; margin-bottom:10px;">
                                    Item ${index + 1} of ${data.count}
                                </div>
                                <h4>${escapeHtml(item.article_name)}</h4>
                                <div><strong>Property No:</strong> ${escapeHtml(item.property_no)}</div>
                                <div><strong>Unit:</strong> ${escapeHtml(item.big_unit)} / ${escapeHtml(item.small_unit)}</div>
                                <div><strong>Pieces per Big Unit:</strong> ${item.pieces_per_big_unit} ${escapeHtml(item.small_unit)}${copiesText}</div>
                                <div style="margin:10px 0;">
                                    <img src="generate_barcode.php?code=${encodeURIComponent(item.barcode_data)}&width=250&height=60" 
                                         style="border:1px solid #ddd; padding:5px;">
                                </div>
                                <div><small>${escapeHtml(item.barcode_data)}</small></div>
                                <button class="btn-xs" onclick="printSingleBarcode('${escapeHtml(item.barcode_data)}', '${escapeHtml(item.article_name)}', ${item.pieces_per_big_unit})" style="margin-top:10px;">
                                    <i class="fas fa-print"></i> Print (${item.pieces_per_big_unit} copies)
                                </button>
                            </div>
                        `;
                    });
                    content += '</div>';
                    
                    document.getElementById('barcodeModalTitle').textContent = 'Barcodes - ' + escapeHtml(name) + ' (' + data.count + ' items)';
                    document.getElementById('barcodeModalImage').innerHTML = content;
                    document.getElementById('barcodeModalNumber').innerHTML = '';
                    document.getElementById('barcodeModal').style.display = 'block';
                } else if (data.success && data.count === 1) {
                    let item = data.items[0];
                    showSingleBarcode(item.barcode_data, item.article_name, item.pieces_per_big_unit);
                } else {
                    showSingleBarcode(barcode, name, 1);
                }
            })
            .catch(() => showSingleBarcode(barcode, name, 1));
    } else {
        showSingleBarcode(barcode, name, 1);
    }
}

function showSingleBarcode(barcode, name, copies = 1) {
    document.getElementById('barcodeModalTitle').textContent = 'Barcode - ' + escapeHtml(name);
    document.getElementById('barcodeModalImage').innerHTML = `
        <div style="text-align:center;">
            <div style="margin:10px 0;">
                <img src="generate_barcode.php?code=${encodeURIComponent(barcode)}&width=300&height=80" 
                     style="max-width:100%;border:1px solid #ddd;padding:10px;border-radius:5px;">
            </div>
            <div style="font-family:monospace;">${escapeHtml(barcode)}</div>
            ${copies > 1 ? `<div style="margin-top:10px;color:#6B8CFF;">Will print ${copies} copies (one per ${copies} pieces)</div>` : ''}
        </div>
    `;
    document.getElementById('barcodeModalNumber').innerHTML = barcode;
    document.getElementById('barcodeModal').style.display = 'block';
    
    // Store for printing
    window.currentSingleBarcode = { barcode: barcode, name: name, copies: copies };
}

function printSingleBarcode(barcode, name, copies = 1) {
    let win = window.open('', '_blank');
    let htmlContent = '<html><head><title>Print Barcode</title><style>';
    htmlContent += 'body{text-align:center;padding:20px;font-family:Arial,sans-serif}';
    htmlContent += '.barcode-card{border:1px dashed #6B8CFF;border-radius:10px;padding:20px;margin-bottom:20px;page-break-after:avoid;break-inside:avoid}';
    htmlContent += '.semi-label{background:#F8B0C0;padding:5px 15px;border-radius:20px;display:inline-block;margin-bottom:15px}';
    htmlContent += '.item-name{font-size:14px;font-weight:bold;margin:10px 0}';
    htmlContent += '.barcode-number{font-family:monospace;font-size:12px;margin-top:10px}';
    htmlContent += '@media print{.barcode-card{break-inside:avoid}body{padding:0}}';
    htmlContent += '</style></head><body>';
    
    // Generate the requested number of copies
    for (let i = 1; i <= copies; i++) {
        htmlContent += `
            <div class="barcode-card">
                <div class="semi-label">SEMI-EXPENDABLE</div>
                <img src="generate_barcode.php?code=${encodeURIComponent(barcode)}&width=350&height=80" alt="Barcode">
                <div class="item-name">${escapeHtml(name)}</div>
                <div class="barcode-number">${escapeHtml(barcode)}</div>
                ${copies > 1 ? `<div style="font-size:10px;color:#999;margin-top:5px;">Copy ${i} of ${copies}</div>` : ''}
            </div>
        `;
    }
    
    htmlContent += '<script>window.onload=function(){setTimeout(function(){window.print();window.close()},500)}<\/script>';
    htmlContent += '</body></html>';
    
    win.document.write(htmlContent);
    win.document.close();
}

function printAllBarcodesInModal() {
    let items = window.currentBarcodeItems;
    let title = window.currentBarcodeGroupName;
    
    if (!items || items.length === 0) {
        alert('No barcodes to print');
        return;
    }
    
    let win = window.open('', '_blank');
    let htmlContent = '<html><head><title>Print Barcodes - ' + escapeHtml(title) + '</title><style>';
    htmlContent += 'body{font-family:Arial,sans-serif;padding:20px}';
    htmlContent += '.barcode-card{border:1px solid #ddd;border-radius:12px;padding:20px;margin-bottom:30px;text-align:center;page-break-after:avoid;break-inside:avoid}';
    htmlContent += '.semi-label{background:#F8B0C0;display:inline-block;padding:5px 15px;border-radius:20px;margin-bottom:10px}';
    htmlContent += '.item-name{font-size:14px;font-weight:bold;margin:10px 0}';
    htmlContent += '.barcode-number{font-family:monospace;font-size:12px;margin-top:10px}';
    htmlContent += '.copy-label{font-size:10px;color:#999;margin-top:5px}';
    htmlContent += '@media print{.barcode-card{break-inside:avoid}}';
    htmlContent += '</style></head><body>';
    htmlContent += '<h2 style="text-align:center;">Barcodes - ' + escapeHtml(title) + '</h2>';
    
    // Generate barcodes for each item, with copies based on pieces_per_big_unit
    items.forEach(function(item, itemIndex) {
        let copies = item.pieces_per_big_unit || 1;
        
        for (let copy = 1; copy <= copies; copy++) {
            htmlContent += `
                <div class="barcode-card">
                    <div class="semi-label">SEMI-EXPENDABLE</div>
                    <div style="margin-bottom:5px;font-size:12px;color:#666;">${escapeHtml(item.article_name)}</div>
                    <div style="margin-bottom:5px;font-size:11px;color:#888;">Property: ${escapeHtml(item.property_no)}</div>
                    <img src="generate_barcode.php?code=${encodeURIComponent(item.barcode_data)}&width=350&height=80" alt="Barcode">
                    <div class="barcode-number">${escapeHtml(item.barcode_data)}</div>
                    ${copies > 1 ? `<div class="copy-label">${escapeHtml(item.small_unit || 'Piece')} ${copy} of ${copies}</div>` : ''}
                </div>
            `;
        }
    });
    
    htmlContent += '<script>window.onload=function(){setTimeout(function(){window.print();window.close()},500)}<\/script>';
    htmlContent += '</body></html>';
    
    win.document.write(htmlContent);
    win.document.close();
}

function printCurrentBarcode() {
    let title = document.getElementById('barcodeModalTitle').textContent;
    if (title.startsWith('Barcodes -')) {
        printAllBarcodesInModal();
    } else {
        let barcode = document.getElementById('barcodeModalNumber').textContent;
        let name = title.replace('Barcode - ', '');
        let copies = (window.currentSingleBarcode && window.currentSingleBarcode.copies) || 1;
        printSingleBarcode(barcode, name, copies);
    }
}
function printBarcode(barcode, name) {
    let win = window.open('', '_blank');
    win.document.write(`<html><head><title>Print Barcode</title><style>body{text-align:center;padding:20px}.barcode-container{padding:30px;border:1px dashed #6B8CFF;border-radius:10px}.semi-label{background:#F8B0C0;padding:5px 15px;border-radius:20px;display:inline-block;margin-bottom:15px}</style></head><body><div class="barcode-container"><div class="semi-label">Semi-Expendable</div><img src="generate_barcode.php?code=${encodeURIComponent(barcode)}&width=400&height=100" alt="Barcode"><div class="item-name">${escapeHtml(name)}</div><div class="barcode-number">${escapeHtml(barcode)}</div></div><script>window.onload=function(){setTimeout(function(){window.print();window.close()},500)}<\/script></body></html>`);
    win.document.close();
}

function closeViewModal() { document.getElementById('viewModal').style.display = 'none'; }
function closeBarcodeModal() { document.getElementById('barcodeModal').style.display = 'none'; }

function escapeHtml(text) { 
    if (!text) return ''; 
    let div = document.createElement('div'); 
    div.textContent = text; 
    return div.innerHTML; 
}

window.onclick = function(e) {
    if (e.target == document.getElementById('semiModal')) closeModal();
    if (e.target == document.getElementById('viewModal')) closeViewModal();
    if (e.target == document.getElementById('barcodeModal')) closeBarcodeModal();
}

window.addEventListener('load', function() {
    <?php if (isset($_GET['edit'])): ?>
    let editId = <?php echo (int)$_GET['edit']; ?>;
    fetch('?ajax=get_edit_item&id=' + editId).then(r => r.json()).then(data => {
        if (data.success) {
            let item = data.data;
            document.getElementById('article_name').value = item.article_name || '';
            document.getElementById('description').value = item.description || '';
            if (item.type_equipment_id) {
                document.getElementById('type_equipment_id').value = item.type_equipment_id;
                loadEquipmentSubTypes();
                setTimeout(() => { 
                    if (item.equipment_sub_type_id) {
                        document.getElementById('equipment_sub_type_id').value = item.equipment_sub_type_id; 
                    }
                }, 500);
            }
            if (item.property_no) {
                document.getElementById('property_custom').checked = true;
                document.getElementById('property_number').value = item.property_no;
                document.getElementById('property_number').setAttribute('readonly', true);
                togglePropertyOption();
            } else { 
                document.getElementById('property_auto').checked = true;
                togglePropertyOption();
            }
            document.getElementById('big_unit').value = item.big_unit || '';
            document.getElementById('big_quantity').value = item.big_quantity || 1;
            document.getElementById('small_unit').value = item.small_unit || '';
            document.getElementById('pieces_per_big_unit').value = item.pieces_per_big_unit || 1;
            document.getElementById('unit_value').value = item.unit_value || 0;
            document.getElementById('fund_cluster').value = item.fund_cluster || '';
            document.getElementById('supplier_id').value = item.supplier_id || '';
            document.getElementById('ref_po_number').value = item.ref_po_number || '';
            document.getElementById('delivery_date').value = item.delivery_date || '';
            document.getElementById('condition_text').value = item.condition_text || 'Serviceable';
            document.getElementById('remarks').value = item.remarks || '';
            document.getElementById('barcode_data').value = item.barcode_data || '';
            calculateCompoundTotal();
        }
    });
    <?php endif; ?>
});
</script>

<?php
include INCLUDE_PATH . '/footer.php';
ob_end_flush();
?>