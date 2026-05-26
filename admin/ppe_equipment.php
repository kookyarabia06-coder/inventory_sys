<?php
ob_start();

/**
 * PPE Equipment Page (Admin)
 * With YYYY-MM-DD-SEQ Property Number Format + Custom Option
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
requireRole('admin');

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'PPE Equipment';
$page_description = 'Manage Personal Protective Equipment';

// FIRST: Update existing records to have category = 'PPE'
$conn->query("UPDATE inventory SET category = 'PPE' WHERE category = '0' OR category = '' OR category IS NULL");
// Also update small_unit where it's 0 or NULL
$conn->query("UPDATE inventory SET small_unit = 'Piece' WHERE small_unit = '0' OR small_unit = '' OR small_unit IS NULL");
// Update big_unit where it's 0 or NULL
$conn->query("UPDATE inventory SET big_unit = 'Box' WHERE big_unit = '0' OR big_unit = '' OR big_unit IS NULL");

// Get type of equipment for dropdown (from type_of_equipment table)
$type_of_equipment = $conn->query("SELECT id, code, name FROM type_of_equipment ORDER BY name");

// Get equipment sub types for dropdown (from equipment_sub_type table)
$equipment_sub_types = $conn->query("
    SELECT est.id, est.code, est.name, est.type_of_equipment_id, toe.name as type_name
    FROM equipment_sub_type est
    LEFT JOIN type_of_equipment toe ON est.type_of_equipment_id = toe.id
    ORDER BY toe.name, est.name
");

// Get users for dropdown
$users = $conn->query("SELECT id, username, firstname, lastname FROM users WHERE status = 'active' ORDER BY firstname, lastname");

// Get fund clusters for dropdown (from fund_cluster table)
$fund_clusters = $conn->query("SELECT id, code, name FROM fund_cluster WHERE status = 'active' ORDER BY name");
// Get suppliers for dropdown (from supplier table)
$suppliers_list = $conn->query("SELECT id, supplier_id, supplier_name FROM supplier WHERE status = 'active' ORDER BY supplier_name");

// Build equipment sub type options array for JavaScript
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
// AJAX HANDLERS
// ============================================

// AJAX endpoint to get equipment sub types by type ID
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

// AJAX endpoint to preview property number format (YYYY-MM-DD-SEQ)
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
        FROM inventory 
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

// AJAX endpoint to check if property number exists (for custom property numbers)
if (isset($_GET['ajax']) && $_GET['ajax'] == 'check_property_number' && isset($_GET['property_no'])) {
    header('Content-Type: application/json');
    $property_no = sanitize($_GET['property_no']);
    
    if (empty($property_no)) {
        echo json_encode(['exists' => false, 'error' => 'No property number provided']);
        exit;
    }
    
    $stmt = $conn->prepare("SELECT id FROM inventory WHERE property_no = ? LIMIT 1");
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
    
    $items = [];
    $article_name = '';
    
    $stmt = $conn->prepare("
        SELECT i.id, i.property_no, i.article_name, i.barcode_data, 
               i.qty_physical_count as quantity, i.unit_value,
               i.description, i.condition_text,
               e.name as equipment_name
        FROM inventory i
        LEFT JOIN equipment e ON i.equipment_id = e.id
        WHERE i.property_no = ?
        LIMIT 1
    ");
    $stmt->bind_param("s", $property_no);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $article_name = $row['article_name'];
        
        if (empty($row['barcode_data'])) {
            $row['barcode_data'] = $row['property_no'];
        }
        $items[] = [
            'id' => $row['id'],
            'property_no' => $row['property_no'],
            'article_name' => $row['article_name'],
            'barcode_data' => $row['barcode_data'],
            'quantity' => $row['quantity'],
            'unit_value' => $row['unit_value'],
            'description' => $row['description'] ?? '',
            'condition_text' => $row['condition_text'] ?? 'Serviceable',
            'equipment_name' => $row['equipment_name'] ?? ''
        ];
    }
    $stmt->close();
    
    if (!empty($article_name)) {
        $stmt2 = $conn->prepare("
            SELECT i.id, i.property_no, i.article_name, i.barcode_data, 
                   i.qty_physical_count as quantity, i.unit_value,
                   i.description, i.condition_text,
                   e.name as equipment_name
            FROM inventory i
            LEFT JOIN equipment e ON i.equipment_id = e.id
            WHERE i.article_name = ? AND i.property_no != ?
            ORDER BY CAST(SUBSTRING_INDEX(i.property_no, '-', -1) AS UNSIGNED)
        ");
        $stmt2->bind_param("ss", $article_name, $property_no);
        $stmt2->execute();
        $result2 = $stmt2->get_result();
        
        while ($row2 = $result2->fetch_assoc()) {
            if (empty($row2['barcode_data'])) {
                $row2['barcode_data'] = $row2['property_no'];
            }
            $items[] = [
                'id' => $row2['id'],
                'property_no' => $row2['property_no'],
                'article_name' => $row2['article_name'],
                'barcode_data' => $row2['barcode_data'],
                'quantity' => $row2['quantity'],
                'unit_value' => $row2['unit_value'],
                'description' => $row2['description'] ?? '',
                'condition_text' => $row2['condition_text'] ?? 'Serviceable',
                'equipment_name' => $row2['equipment_name'] ?? ''
            ];
        }
        $stmt2->close();
    }
    
    $base_pattern = '';
    if (count($items) > 0 && preg_match('/^(.+)-(\d{4})$/', $items[0]['property_no'], $m)) {
        $base_pattern = $m[1];
    } elseif (preg_match('/^(.+)-(\d{4})$/', $property_no, $m)) {
        $base_pattern = $m[1];
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'count' => count($items),
        'base_pattern' => $base_pattern,
        'article_name' => $article_name,
        'original_property' => $property_no
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
               fc.name as fund_cluster_name,
               CONCAT(ap.firstname, ' ', ap.lastname) as approver_name,
               CONCAT(vr.firstname, ' ', vr.lastname) as verifier_name,
               CONCAT(cr.firstname, ' ', cr.lastname) as created_by_name,
               (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') as is_issued,
               CASE WHEN i.property_no LIKE '%-%' THEN 1 ELSE 0 END as is_multiple
        FROM inventory i
        LEFT JOIN equipment e ON i.equipment_id = e.id
        LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
        LEFT JOIN equipment_sub_type est ON i.equipment_sub_type_id = est.id
        LEFT JOIN fund_cluster fc ON i.fund_cluster_id = fc.id
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
    
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
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
// Get ALL barcodes for items sharing same base property number (for PPE)
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_all_barcodes' && isset($_GET['property_no'])) {
    header('Content-Type: application/json');
    $property_no = sanitize($_GET['property_no']);
    
    // Extract base (remove everything after the LAST dash)
    $last_dash = strrpos($property_no, '-');
    $base = ($last_dash !== false) ? substr($property_no, 0, $last_dash) : $property_no;
    
    // Get ALL items with this base property number
    $stmt = $conn->prepare("
        SELECT id, property_no, article_name, barcode_data, 
               big_quantity, small_unit, big_unit, qty_physical_count,
               pieces_per_big_unit
        FROM inventory 
        WHERE (property_no = ? OR property_no LIKE CONCAT(?, '-%')) AND category = 'PPE'
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
            'big_quantity' => (int)$row['big_quantity'],
            'pieces_per_big_unit' => (int)$row['pieces_per_big_unit'],  // ADD THIS
            'small_unit' => $row['small_unit'],
            'big_unit' => $row['big_unit']
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
// FUNCTION TO GENERATE PROPERTY NUMBER (YYYY-MM-DD-SEQ)
// ============================================

function generatePropertyNumber($conn, $type_equipment_id = null, $equipment_sub_type_id = null, $sequence_number = null) {
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
        FROM inventory 
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
// FORM HANDLERS
// ============================================

// Handle Add PPE Item
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $article_name = sanitize($_POST['article_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    // Get UOM values directly
    $big_unit = sanitize($_POST['big_unit'] ?? '');
    $big_quantity = floatval($_POST['big_quantity'] ?? 0);
    $small_unit = sanitize($_POST['small_unit'] ?? '');
    $pieces_per_big_unit = floatval($_POST['pieces_per_big_unit'] ?? 1);
    $total_quantity = $big_quantity * $pieces_per_big_unit;
    
    $unit_value = floatval($_POST['unit_value'] ?? 0);
    $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
    $category = 'PPE';
    
    $type_equipment_id = !empty($_POST['type_equipment_id']) ? (int)$_POST['type_equipment_id'] : null;
    $equipment_sub_type_id = !empty($_POST['equipment_sub_type_id']) ? (int)$_POST['equipment_sub_type_id'] : null;
    
    // Quantity Tracking
    $qty_property_card = !empty($_POST['qty_property_card']) ? floatval($_POST['qty_property_card']) : $total_quantity;
    $qty_physical_count = !empty($_POST['qty_physical_count']) ? floatval($_POST['qty_physical_count']) : $total_quantity;
    
    $fund_cluster = !empty($_POST['fund_cluster']) ? (int)$_POST['fund_cluster'] : null;
    $supplier = sanitize($_POST['supplier'] ?? '');
    $ref_po_number = sanitize($_POST['ref_po_number'] ?? '');
    $delivery_date = !empty($_POST['delivery_date']) ? sanitize($_POST['delivery_date']) : null;
    $year_acquired = date('Y');
    $condition_text = sanitize($_POST['condition_text'] ?? 'Serviceable');
    
    $certified_correct_array = isset($_POST['certified_correct']) && is_array($_POST['certified_correct']) 
        ? array_filter(array_map('intval', $_POST['certified_correct'])) : [];
    $certified_correct = !empty($certified_correct_array) ? json_encode(array_values($certified_correct_array)) : null;
    
    $approved_by_array = isset($_POST['approved_by']) && is_array($_POST['approved_by']) 
        ? array_filter(array_map('intval', $_POST['approved_by'])) : [];
    $approved_by = !empty($approved_by_array) ? json_encode(array_values($approved_by_array)) : null;
    
    $verified_by_array = isset($_POST['verified_by']) && is_array($_POST['verified_by']) 
        ? array_filter(array_map('intval', $_POST['verified_by'])) : [];
    $verified_by = !empty($verified_by_array) ? json_encode(array_values($verified_by_array)) : null;
    
    $remarks = sanitize($_POST['remarks'] ?? '');
    $barcode_data_input = sanitize($_POST['barcode_data'] ?? '');
    $created_by = $_SESSION['user_id'];
    $generate_multiple = isset($_POST['generate_multiple_barcodes']) && $_POST['generate_multiple_barcodes'] == '1';
    
    // Get property number option
    $property_option = $_POST['property_option'] ?? 'auto';
    $manual_property_no = sanitize($_POST['property_number'] ?? '');
    
    $errors = [];
    if (empty($article_name)) $errors[] = "Article name is required";
    if (empty($big_unit)) $errors[] = "Big unit is required";
    if (empty($small_unit)) $errors[] = "Small unit is required";
    if ($big_quantity <= 0) $errors[] = "Big quantity must be greater than 0";
    if ($pieces_per_big_unit <= 0) $errors[] = "Pieces per big unit must be greater than 0";
    if ($unit_value <= 0) $errors[] = "Unit value must be greater than 0";
    if (empty($type_equipment_id)) $errors[] = "Type of Equipment is required";
    if (empty($equipment_sub_type_id)) $errors[] = "Equipment Category is required";
    
    if ($property_option == 'custom' && empty($manual_property_no)) {
        $errors[] = "Custom property number is required";
    }
    
    if (empty($errors)) {
        $conn->begin_transaction();
        $success_count = 0;
        $property_numbers = [];
        
        try {
            $date_str = date('Ymd');
            
            if ($generate_multiple && $big_quantity > 1 && floor($big_quantity) == $big_quantity) {
                // MULTIPLE ITEMS MODE
                $year = date('Y');
                $month = str_pad(date('m'), 2, '0', STR_PAD_LEFT);
                $day = str_pad(date('d'), 2, '0', STR_PAD_LEFT);
                $base_format = $year . '-' . $month . '-' . $day;
                
                $pattern_like = $base_format . '-%';
                $seq_query = $conn->prepare("
                    SELECT MAX(CAST(SUBSTRING_INDEX(property_no, '-', -1) AS UNSIGNED)) as max_seq 
                    FROM inventory 
                    WHERE property_no LIKE ?
                ");
                $seq_query->bind_param("s", $pattern_like);
                $seq_query->execute();
                $seq_result = $seq_query->get_result();
                $start_seq = ($seq_result->fetch_assoc()['max_seq'] ?? 0) + 1;
                $seq_query->close();
                
                for ($i = 0; $i < $big_quantity; $i++) {
                    $property_no = $base_format . '-' . str_pad($start_seq + $i, 4, '0', STR_PAD_LEFT);
                    
                    // Generate barcode
                    $sequential_barcode = 'PPE-' . $date_str . '-' . str_pad($start_seq + $i, 4, '0', STR_PAD_LEFT);
                    
                    $checkStmt = $conn->prepare("SELECT id FROM inventory WHERE barcode_data = ? LIMIT 1");
                    $checkStmt->bind_param("s", $sequential_barcode);
                    $checkStmt->execute();
                    if ($checkStmt->get_result()->num_rows > 0) {
                        $sequential_barcode .= '-' . rand(100, 999);
                    }
                    $checkStmt->close();
                    
                    $stmt = $conn->prepare("
                        INSERT INTO inventory (
                            article_name, description, property_no, 
                            qty_property_card, qty_physical_count, unit_value,
                            equipment_id, category, type_equipment_id, equipment_sub_type_id, condition_text,
                            fund_cluster_id, certified_correct, approved_by, verified_by,
                            supplier, ref_po_number, delivery_date,
                            big_unit, big_quantity, small_unit, pieces_per_big_unit,
                            remarks, barcode_data, created_by, year_acquired
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $item_qty_property_card = $pieces_per_big_unit;
                    $item_qty_physical_count = $pieces_per_big_unit;
                    
                    $stmt->bind_param(
                        "sssdddiiisiiissssssddssssi",
                        $article_name,
                        $description,
                        $property_no,
                        $item_qty_property_card,
                        $item_qty_physical_count,
                        $unit_value,
                        $equipment_id,
                        $category,
                        $type_equipment_id,
                        $equipment_sub_type_id,
                        $condition_text,
                        $fund_cluster,
                        $certified_correct,
                        $approved_by,
                        $verified_by,
                        $supplier,
                        $ref_po_number,
                        $delivery_date,
                        $big_unit,
                        $big_quantity,
                        $small_unit,
                        $pieces_per_big_unit,
                        $remarks,
                        $sequential_barcode,
                        $created_by,
                        $year_acquired
                    );
                    
                    if ($stmt->execute()) {
                        $success_count++;
                        $property_numbers[] = $property_no;
                    } else {
                        throw new Exception("Failed to insert item $i: " . $stmt->error);
                    }
                    $stmt->close();
                }
                
                $conn->commit();
                $_SESSION['success'] = "$success_count PPE items added successfully.";
                
            } elseif ($property_option == 'custom' && !empty($manual_property_no)) {
                // CUSTOM PROPERTY NUMBER MODE
                $property_no = $manual_property_no;
                
                // Check if property number already exists
                $check_stmt = $conn->prepare("SELECT id FROM inventory WHERE property_no = ?");
                $check_stmt->bind_param("s", $property_no);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    throw new Exception("Property number already exists: $property_no");
                }
                $check_stmt->close();
                
                // Generate barcode
                if (!empty($barcode_data_input)) {
                    $barcode_data = $barcode_data_input;
                } else {
                    $barcode_data = 'PPE-' . $date_str . '-' . substr($property_no, -4);
                }
                
                $checkStmt = $conn->prepare("SELECT id FROM inventory WHERE barcode_data = ? LIMIT 1");
                $checkStmt->bind_param("s", $barcode_data);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows > 0) {
                    $barcode_data .= '-' . rand(100, 999);
                }
                $checkStmt->close();
                
                $stmt = $conn->prepare("
                    INSERT INTO inventory (
                        article_name, description, property_no, 
                        qty_property_card, qty_physical_count, unit_value,
                        equipment_id, category, type_equipment_id, equipment_sub_type_id, condition_text,
                        fund_cluster_id, certified_correct, approved_by, verified_by,
                        supplier, ref_po_number, delivery_date,
                        big_unit, big_quantity, small_unit, pieces_per_big_unit,
                        remarks, barcode_data, created_by, year_acquired
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param(
                    "sssdddiiisiiissssssddssssi",
                    $article_name,
                    $description,
                    $property_no,
                    $qty_property_card,
                    $qty_physical_count,
                    $unit_value,
                    $equipment_id,
                    $category,
                    $type_equipment_id,
                    $equipment_sub_type_id,
                    $condition_text,
                    $fund_cluster,
                    $certified_correct,
                    $approved_by,
                    $verified_by,
                    $supplier,
                    $ref_po_number,
                    $delivery_date,
                    $big_unit,
                    $big_quantity,
                    $small_unit,
                    $pieces_per_big_unit,
                    $remarks,
                    $barcode_data,
                    $created_by,
                    $year_acquired
                );
                
                if ($stmt->execute()) {
                    $conn->commit();
                    $_SESSION['success'] = "PPE item added successfully. Property No: $property_no";
                } else {
                    throw new Exception($conn->error);
                }
                $stmt->close();
                
            } else {
                // AUTO-GENERATE PROPERTY NUMBER
                $property_no = generatePropertyNumber($conn);
                
                // Generate barcode
                $last_four = substr($property_no, -4);
                if (!empty($barcode_data_input)) {
                    $barcode_data = $barcode_data_input;
                } else {
                    $barcode_data = 'PPE-' . $date_str . '-' . $last_four;
                }
                
                $checkStmt = $conn->prepare("SELECT id FROM inventory WHERE barcode_data = ? LIMIT 1");
                $checkStmt->bind_param("s", $barcode_data);
                $checkStmt->execute();
                if ($checkStmt->get_result()->num_rows > 0) {
                    $barcode_data .= '-' . rand(100, 999);
                }
                $checkStmt->close();
                
                $stmt = $conn->prepare("
                    INSERT INTO inventory (
                        article_name, description, property_no, 
                        qty_property_card, qty_physical_count, unit_value,
                        equipment_id, category, type_equipment_id, equipment_sub_type_id, condition_text,
                        fund_cluster_id, certified_correct, approved_by, verified_by,
                        supplier, ref_po_number, delivery_date,
                        big_unit, big_quantity, small_unit, pieces_per_big_unit,
                        remarks, barcode_data, created_by, year_acquired
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param(
                    "sssdddiiisiiissssssddssssi",
                    $article_name,
                    $description,
                    $property_no,
                    $qty_property_card,
                    $qty_physical_count,
                    $unit_value,
                    $equipment_id,
                    $category,
                    $type_equipment_id,
                    $equipment_sub_type_id,
                    $condition_text,
                    $fund_cluster,
                    $certified_correct,
                    $approved_by,
                    $verified_by,
                    $supplier,
                    $ref_po_number,
                    $delivery_date,
                    $big_unit,
                    $big_quantity,
                    $small_unit,
                    $pieces_per_big_unit,
                    $remarks,
                    $barcode_data,
                    $created_by,
                    $year_acquired
                );
                
                if ($stmt->execute()) {
                    $conn->commit();
                    $_SESSION['success'] = "PPE item added successfully. Property No: $property_no";
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
    
    header('Location: ' . SITE_URL . '/admin/ppe_equipment.php');
    exit();
}

// Handle Edit PPE Item
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit' && isset($_POST['id'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $id = (int)$_POST['id'];
    $article_name = sanitize($_POST['article_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $unit_value = floatval($_POST['unit_value'] ?? 0);
    $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
    $type_equipment_id = !empty($_POST['type_equipment_id']) ? (int)$_POST['type_equipment_id'] : null;
    $equipment_sub_type_id = !empty($_POST['equipment_sub_type_id']) ? (int)$_POST['equipment_sub_type_id'] : null;
    $fund_cluster = !empty($_POST['fund_cluster']) ? (int)$_POST['fund_cluster'] : null;
    $supplier = sanitize($_POST['supplier'] ?? '');
    $ref_po_number = sanitize($_POST['ref_po_number'] ?? '');
    $delivery_date = !empty($_POST['delivery_date']) ? sanitize($_POST['delivery_date']) : null;
    $condition_text = sanitize($_POST['condition_text'] ?? 'Serviceable');
    
    $certified_correct_array = isset($_POST['certified_correct']) && is_array($_POST['certified_correct']) 
        ? array_filter(array_map('intval', $_POST['certified_correct'])) : [];
    $certified_correct = !empty($certified_correct_array) ? json_encode(array_values($certified_correct_array)) : null;
    
    $approved_by_array = isset($_POST['approved_by']) && is_array($_POST['approved_by']) 
        ? array_filter(array_map('intval', $_POST['approved_by'])) : [];
    $approved_by = !empty($approved_by_array) ? json_encode(array_values($approved_by_array)) : null;
    
    $verified_by_array = isset($_POST['verified_by']) && is_array($_POST['verified_by']) 
        ? array_filter(array_map('intval', $_POST['verified_by'])) : [];
    $verified_by = !empty($verified_by_array) ? json_encode(array_values($verified_by_array)) : null;
    
    $remarks = sanitize($_POST['remarks'] ?? '');
    $barcode_data = sanitize($_POST['barcode_data'] ?? '');
    
    // Inventory Fields
    $big_unit = sanitize($_POST['big_unit'] ?? '');
    $big_quantity = floatval($_POST['big_quantity'] ?? 0);
    $small_unit = sanitize($_POST['small_unit'] ?? '');
    $pieces_per_big_unit = floatval($_POST['pieces_per_big_unit'] ?? 0);
    
    // Quantity Tracking
    $qty_physical_count = floatval($_POST['qty_physical_count'] ?? 0);
    $qty_property_card = floatval($_POST['qty_property_card'] ?? $qty_physical_count);
    
    $stmt = $conn->prepare("
        UPDATE inventory SET 
            article_name = ?, description = ?, qty_physical_count = ?, qty_property_card = ?,
            unit_value = ?, equipment_id = ?, type_equipment_id = ?, equipment_sub_type_id = ?,
            condition_text = ?, fund_cluster_id = ?, certified_correct = ?, approved_by = ?,
            verified_by = ?, supplier = ?, ref_po_number = ?, delivery_date = ?,
            big_unit = ?, big_quantity = ?, small_unit = ?, pieces_per_big_unit = ?,
            remarks = ?, barcode_data = ?, date_updated = NOW()
        WHERE id = ?
    ");
    
    $stmt->bind_param(
        "ssdddiiiisiiissssssddsssi",
        $article_name, $description, $qty_physical_count, $qty_property_card, $unit_value,
        $equipment_id, $type_equipment_id, $equipment_sub_type_id, $condition_text,
        $fund_cluster, $certified_correct, $approved_by, $verified_by,
        $supplier, $ref_po_number, $delivery_date,
        $big_unit, $big_quantity, $small_unit, $pieces_per_big_unit,
        $remarks, $barcode_data, $id
    );
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "PPE item updated successfully";
    } else {
        $_SESSION['error'] = "Error updating item: " . $stmt->error;
    }
    $stmt->close();
    
    header('Location: ' . SITE_URL . '/admin/ppe_equipment.php');
    exit();
}

// Handle Delete PPE Item
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
        $stmt = $conn->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt->bind_param("i", $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $_SESSION['success'] = "PPE item deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting item or item not found";
        }
        $stmt->close();
    }
    
    header('Location: ' . SITE_URL . '/admin/ppe_equipment.php');
    exit();
}

if (isset($_GET['generate_barcode'])) {
    $barcode_value = $_GET['barcode_value'] ?? '';
    if (empty($barcode_value)) {
        if (isset($_GET['code'])) {
            $barcode_value = $_GET['code'];
        } else {
            die('No barcode value provided');
        }
    }
    try {
        $generator = new BarcodeGeneratorPNG();
        $barcode = $generator->getBarcode($barcode_value, $generator::TYPE_CODE_128);
        header('Content-Type: image/png');
        echo $barcode;
    } catch (Exception $e) {
        die('Error generating barcode');
    }
    exit;
}

// Multiple barcode preview
if (isset($_GET['generate_multiple_preview'])) {
    ob_clean();
    header('Content-Type: application/json');
    $prefix = $_GET['prefix'] ?? 'PPE';
    $quantity = min(max(intval($_GET['quantity'] ?? 1), 1), 100);
    $baseBarcode = $prefix . '-' . date('Ymd');
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
// DISPLAY DATA
// ============================================

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

$query = "
    SELECT i.*, e.name as equipment_name,
           toe.name as type_equipment_name,
           est.name as sub_type_name,
           fc.name as fund_cluster_name,
           CONCAT(ap.firstname, ' ', ap.lastname) as approver_name,
           CONCAT(vr.firstname, ' ', vr.lastname) as verifier_name,
           CONCAT(cr.firstname, ' ', cr.lastname) as created_by_name,
           (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') as is_issued,
           CASE WHEN i.property_no LIKE '%-%' THEN 1 ELSE 0 END as is_multiple
    FROM inventory i
    LEFT JOIN equipment e ON i.equipment_id = e.id
    LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
    LEFT JOIN equipment_sub_type est ON i.equipment_sub_type_id = est.id
    LEFT JOIN fund_cluster fc ON i.fund_cluster_id = fc.id
    LEFT JOIN users ap ON i.approved_by = ap.id
    LEFT JOIN users vr ON i.verified_by = vr.id
    LEFT JOIN users cr ON i.created_by = cr.id
    WHERE i.category = 'PPE'
";

$count_query = "SELECT COUNT(*) as total FROM inventory WHERE category = 'PPE'";

if ($search) {
    $query .= " AND (i.article_name LIKE ? OR i.property_no LIKE ? OR i.description LIKE ? OR i.supplier LIKE ?)";
    $count_query .= " AND (article_name LIKE ? OR property_no LIKE ? OR description LIKE ? OR supplier LIKE ?)";
    $search_term = "%$search%";
    
    $main_params = [$search_term, $search_term, $search_term, $search_term];
    $main_types = "ssss";
    
    $count_params = [$search_term, $search_term, $search_term, $search_term];
    $count_types = "ssss";
} else {
    $main_params = [];
    $main_types = "";
    $count_params = [];
    $count_types = "";
}

$query .= " ORDER BY i.date_added DESC";

$stmt = $conn->prepare($count_query);
if (!empty($count_params)) {
    $stmt->bind_param($count_types, ...$count_params);
}
$stmt->execute();
$total_rows = $stmt->get_result()->fetch_assoc()['total'];
$stmt->close();

$offset = ($page - 1) * $per_page;
$query .= " LIMIT ? OFFSET ?";

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

$ppe_items = [];
while ($row = $result->fetch_assoc()) {
    // Format display values
    $row['big_unit_display'] = !empty($row['big_quantity']) && !empty($row['big_unit']) && $row['big_unit'] != '0'
        ? number_format($row['big_quantity'], 0) . ' ' . $row['big_unit'] 
        : '—';
    
    $row['small_unit_display'] = !empty($row['pieces_per_big_unit']) && !empty($row['small_unit']) && $row['small_unit'] != '0'
        ? number_format($row['pieces_per_big_unit'], 0) . ' ' . $row['small_unit'] 
        : (!empty($row['small_unit']) && $row['small_unit'] != '0' ? $row['small_unit'] : '—');
    
    $row['quantity_display'] = $row['qty_physical_count'] ?? 0;
    
    $ppe_items[] = $row;
}
$stmt->close();

$pagination_data = [
    'data' => $ppe_items,
    'total_rows' => $total_rows,
    'per_page' => $per_page,
    'current_page' => $page,
    'total_pages' => ceil($total_rows / $per_page)
];

include INCLUDE_PATH . '/header.php';
?>

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

.detail-section {
    margin-bottom: 20px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    overflow: hidden;
}

.detail-header {
    background: var(--light);
    padding: 12px 16px;
    font-weight: 600;
    color: var(--primary);
    border-bottom: 1px solid var(--border-light);
}

.detail-header i {
    color: var(--accent);
    margin-right: 8px;
}

.detail-content {
    padding: 16px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.detail-item {
    border-bottom: 1px dashed var(--border-light);
    padding: 8px 0;
}

.detail-label {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 5px;
    font-size: 11px;
    text-transform: uppercase;
}

.detail-value {
    color: var(--text-secondary);
    font-size: 13px;
    word-break: break-word;
}

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
    
    .detail-grid {
        grid-template-columns: 1fr;
    }
}
</style>

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
        <div class="card-icon"><i class="fas fa-shield-alt"></i></div>
        <h3>Total PPE Items</h3>
        <?php
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory WHERE category = 'PPE'");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_ppe = $result->fetch_assoc()['count'];
        $stmt->close();
        ?>
        <div class="card-value"><?php echo $total_ppe; ?></div>
        <div class="card-label">All equipment</div>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-hand-holding"></i></div>
        <h3>Issued Items</h3>
        <?php
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT ei.inventory_id) as count 
            FROM equipment_issuance ei
            JOIN inventory i ON ei.inventory_id = i.id
            WHERE ei.status = 'issued' AND i.category = 'PPE'
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
        <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h3>Low Stock</h3>
        <?php
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM inventory WHERE qty_physical_count <= 5 AND category = 'PPE'");
        $stmt->execute();
        $result = $stmt->get_result();
        $low_stock_ppe = $result->fetch_assoc()['count'];
        $stmt->close();
        ?>
        <div class="card-value <?php echo $low_stock_ppe > 0 ? 'text-warning' : ''; ?>"><?php echo $low_stock_ppe; ?></div>
        <div class="card-label">Need reorder</div>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-dollar-sign"></i></div>
        <h3>Total Value</h3>
        <?php
        $stmt = $conn->prepare("SELECT SUM(unit_value * qty_physical_count) as total FROM inventory WHERE category = 'PPE'");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_value = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        ?>
        <div class="card-value">₱<?php echo number_format($total_value, 2); ?></div>
        <div class="card-label">Inventory value</div>
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
    </div>
</div>

<!-- Search and Filter -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-search"></i> Search PPE Items</h2>
    </div>
    <form method="GET" action="" class="search-box">
        <input type="text" name="search" placeholder="Search by article name, property no., description, or supplier..." 
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
    
    <div style="overflow-x: auto;">
        <table style="width: 100%; min-width: 1200px;">
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
                <?php if (count($ppe_items) > 0): ?>
                    <?php foreach ($ppe_items as $item): ?>
                    <tr>
                        <td style="padding: 15px 10px; vertical-align: top;">
                            <strong><?php echo htmlspecialchars($item['article_name']); ?></strong>
                            <?php if (!empty($item['description'])): ?>
                            <br><small><?php echo htmlspecialchars(substr($item['description'], 0, 50)); ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="padding: 15px 10px; vertical-align: top;"><?php echo htmlspecialchars($item['property_no']); ?></td>
                        <td style="padding: 15px 10px; vertical-align: top;"><?php echo htmlspecialchars($item['type_equipment_name'] ?? $item['category']); ?></td>
                        <td style="padding: 15px 10px; vertical-align: top;"><?php echo $item['big_unit_display']; ?></td>
                        <td style="padding: 15px 10px; vertical-align: top;"><?php echo $item['small_unit_display']; ?></td>
                        <td style="padding: 15px 10px; vertical-align: top;"><strong><?php echo number_format($item['quantity_display'], 0); ?></strong></td>
                        <td style="padding: 15px 10px; vertical-align: top;">₱<?php echo number_format($item['unit_value'], 2); ?></td>
                        <td style="padding: 15px 10px; vertical-align: top;">₱<?php echo number_format($item['unit_value'] * $item['quantity_display'], 2); ?></td>
                        <td style="padding: 15px 10px; vertical-align: top;"><?php echo htmlspecialchars($item['supplier'] ?? 'N/A'); ?></td>
                        <td style="padding: 15px 10px; vertical-align: top;"><?php echo htmlspecialchars($item['fund_cluster_name'] ?? 'N/A'); ?></td>
                        <td style="padding: 15px 10px; vertical-align: top;">
                            <?php if ($item['is_issued'] > 0): ?>
                                <span class="badge-warning">Issued</span>
                            <?php else: ?>
                                <span class="badge-success">Available</span>
                            <?php endif; ?>
                        </td>
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
                                <button class="action-btn edit" onclick="openEditModal(<?php echo $item['id']; ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="action-btn view" onclick="viewItem(<?php echo $item['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <?php if ($item['is_issued'] == 0): ?>
                                <a href="?delete=<?php echo $item['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                   class="action-btn delete" 
                                   onclick="return confirm('Are you sure you want to delete this PPE item?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                                <a href="<?php echo SITE_URL; ?>/admin/issue_items.php?item=<?php echo $item['id']; ?>" 
                                   class="action-btn success">
                                    <i class="fas fa-hand-holding"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="13" class="text-center">
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
    </div>
    
    <?php echo displayPagination($pagination_data, '?page=' . ($search ? '&search=' . urlencode($search) : '')); ?>
</div>

<!-- Sticky SCAN BARCODE Button -->
<div class="sticky-scan-button-container">
    <a href="<?php echo SITE_URL; ?>/admin/barcodescannerforppe.php" class="sticky-scan-button">
        <i class="fas fa-camera"></i> SCAN BARCODE
    </a>
</div>

<!-- Add/Edit PPE Modal -->
<div id="ppeModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add New PPE Item</h2>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <form method="POST" action="" id="ppeForm">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="generate_multiple_barcodes" id="generate_multiple_barcodes" value="0">
                <input type="hidden" name="id" id="editId" value="">
                
                <!-- Basic Information -->
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                    <div class="form-group">
                        <label for="article_name">Article Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="article_name" name="article_name" required maxlength="255">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2"></textarea>
                    </div>
                    
                    <!-- Property Number Options -->
                    <div class="form-group">
                        <label>Property Number</label>
                        <div style="margin-bottom: 15px;">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="property_option" id="property_auto" value="auto" checked onchange="togglePropertyOption()">
                                <label class="form-check-label" for="property_auto"><strong>Auto-Generate (YYYY-MM-DD-XXXX)</strong></label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="property_option" id="property_custom" value="custom" onchange="togglePropertyOption()">
                                <label class="form-check-label" for="property_custom"><strong>Custom</strong></label>
                            </div>
                        </div>
                        
                        <div id="autoGenerateSection">
                            <div id="propertyPreviewAuto" class="form-text" style="padding: 10px; background: #e8f4f8; border-radius: 5px; margin-bottom: 10px; display: none;">
                                <i class="fas fa-qrcode"></i> <strong>Auto-generated Format:</strong><br>
                                <span id="autoPropertyPreviewText"></span>
                            </div>
                        </div>
                        <div id="customPropertySection" style="display: none;">
                            <div class="form-row">
                                <div class="form-group" style="flex: 3;">
                                    <input type="text" class="form-control" id="property_number" name="property_number" placeholder="Enter custom property number">
                                </div>
                                <div class="form-group" style="flex: 1;">
                                    <button type="button" class="btn btn-secondary" onclick="previewManualPropertyNumber()">
                                        <i class="fas fa-eye"></i> Check
                                    </button>
                                </div>
                            </div>
                            <div id="manualPropertyPreview" class="form-text"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Classification -->
                <div class="form-section">
                    <h3><i class="fas fa-tags"></i> Classification</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="type_equipment_id">Type of Equipment <span class="text-danger">*</span></label>
                            <select class="form-control" id="type_equipment_id" name="type_equipment_id" required onchange="loadEquipmentSubTypes(); previewAutoPropertyNumber();">
                                <option value="">-- Select Type of Equipment --</option>
                                <?php 
                                $type_of_equipment->data_seek(0);
                                while($type = $type_of_equipment->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $type['id']; ?>">
                                    <?php echo htmlspecialchars($type['code'] . ' - ' . $type['name']); ?>
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="equipment_sub_type_id">Equipment Category <span class="text-danger">*</span></label>
                            <select class="form-control" id="equipment_sub_type_id" name="equipment_sub_type_id" required onchange="previewAutoPropertyNumber();">
                                <option value="">-- First select Type of Equipment --</option>
                            </select>
                        </div>
                    </div>
                </div>
                
<!-- Supplier Information -->
<div class="form-section">
    <h3><i class="fas fa-truck"></i> Supplier Information</h3>
    <div class="form-row">
        <div class="form-group">
            <label for="supplier">Supplier</label>
            <select class="form-control" id="supplier" name="supplier">
                <option value="">-- Select Supplier --</option>
                <?php 
                if ($suppliers_list && $suppliers_list->num_rows > 0):
                    $suppliers_list->data_seek(0);
                    while($supp = $suppliers_list->fetch_assoc()): 
                ?>
                <option value="<?php echo htmlspecialchars($supp['supplier_name']); ?>">
                    <?php echo htmlspecialchars($supp['supplier_name']); ?>
                </option>
                <?php 
                    endwhile; 
                endif; 
                ?>
            </select>
        </div>
        <div class="form-group">
            <label for="ref_po_number">Reference PO Number</label>
            <input type="text" class="form-control" id="ref_po_number" name="ref_po_number">
        </div>
    </div>
    <div class="form-group">
        <label for="delivery_date">Delivery Date</label>
        <input type="date" class="form-control" id="delivery_date" name="delivery_date">
    </div>
</div>
                
                <!-- Quantity and Unit of Measure -->
                <div class="form-section">
                    <h3><i class="fas fa-calculator"></i> Quantity and Unit of Measure</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="big_unit">Big Unit <span class="text-danger">*</span></label>
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
                                <option value="Bag">Bag</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="big_quantity">Number of Big Units <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="big_quantity" name="big_quantity" value="1" min="1" step="1" onchange="calculateCompoundTotal()">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="small_unit">Small Unit <span class="text-danger">*</span></label>
                            <select class="form-control" id="small_unit" name="small_unit" required onchange="calculateCompoundTotal()">
                                <option value="">-- Select --</option>
                                <option value="Piece">Piece(s)</option>
                                <option value="Unit">Unit(s)</option>
                                <option value="Each">Each</option>
                                <option value="Meter">Meter(s)</option>
                                <option value="Kilogram">Kilogram(s)</option>
                                <option value="Liter">Liter(s)</option>
                                <option value="Pair">Pair(s)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="pieces_per_big_unit">Units per Big Unit <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="pieces_per_big_unit" name="pieces_per_big_unit" value="1" min="1" step="1" onchange="calculateCompoundTotal()">
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="total_quantity_display">Total Quantity</label>
                        <input type="text" class="form-control" id="total_quantity_display" readonly>
                        <input type="hidden" id="quantity_hidden" name="quantity" value="0">
                    </div>
                    
                    <div id="multipleBarcodeOption" style="display: none; margin-top: 15px; padding: 15px; background: #e8f4f8; border-radius: 8px;">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="multiple_barcodes" onchange="toggleMultipleBarcodes()">
                            <label class="form-check-label" for="multiple_barcodes">
                                <strong>Generate individual barcodes for each big unit</strong>
                            </label>
                            <small class="form-text text-muted">Example: If you have 2 Boxes with 12 pieces each, this will create 2 separate items (1 box = 12 pieces per item)</small>
                        </div>
                        <div id="barcodePreviewContainer" style="display: none;">
                            <div id="multipleBarcodePreview"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Value Information -->
                <div class="form-section">
                    <h3><i class="fas fa-dollar-sign"></i> Value Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="unit_value">Unit Value per Small Unit <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="unit_value" name="unit_value" min="0.01" step="0.01" required onchange="calculateTotal()">
                        </div>
                        <div class="form-group">
                            <label for="total_value">Total Value</label>
                            <input type="text" class="form-control" id="total_value" readonly>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="fund_cluster">Fund Cluster</label>
                        <select class="form-control" id="fund_cluster" name="fund_cluster">
                            <option value="">-- Select Fund Cluster --</option>
                            <?php if ($fund_clusters): $fund_clusters->data_seek(0); while($fund = $fund_clusters->fetch_assoc()): ?>
                            <option value="<?php echo $fund['id']; ?>"><?php echo htmlspecialchars($fund['name']); ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Condition and Certification -->
                <div class="form-section">
                    <h3><i class="fas fa-clipboard-check"></i> Condition and Certification</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="condition_text">Condition</label>
                            <select class="form-control" id="condition_text" name="condition_text">
                                <option value="Serviceable">Serviceable</option>
                                <option value="Non-Serviceable">Non-Serviceable</option>
                                <option value="For Condemn">For Condemn</option>
                                <option value="Under Repair">Under Repair</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="certified_correct">Certified Correct By (Multi-Select)</label>
                            <select class="form-control" id="certified_correct" name="certified_correct[]" multiple size="2">
                                <?php if ($users): $users->data_seek(0); while($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="approved_by">Approved By (Multi-Select)</label>
                            <select class="form-control" id="approved_by" name="approved_by[]" multiple size="2">
                                <?php if ($users): $users->data_seek(0); while($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="verified_by">Verified By (Multi-Select)</label>
                            <select class="form-control" id="verified_by" name="verified_by[]" multiple size="2">
                                <?php if ($users): $users->data_seek(0); while($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="form-section">
                    <h3><i class="fas fa-tasks"></i> Additional Information</h3>
                    <div class="form-group">
                        <label for="remarks">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="barcode_data">Barcode</label>
                        <input type="text" class="form-control" id="barcode_data" name="barcode_data" placeholder="Enter or generate barcode">
                        <button type="button" class="btn btn-secondary" onclick="generateBarcodeForEdit()" style="margin-top: 5px;">
                            <i class="fas fa-sync-alt"></i> Generate Barcode
                        </button>
                        <div id="barcodePreview" class="barcode-preview" style="margin-top: 10px; text-align: center;"></div>
                    </div>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Item
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>PPE Item Details</h2>
            <span class="modal-close" onclick="closeViewModal()">&times;</span>
        </div>
        <div class="modal-body" id="viewModalContent">
            <div class="loading">Loading...</div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<!-- Barcode Modal -->
<!--- proto -->
<div id="barcodeModal" class="modal">
    <div class="modal-content" style="max-width: 700px; width: 90%;">
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
            <button class="btn btn-primary" onclick="printCurrentBarcode()">
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
    subTypeSelect.innerHTML = '<option value="">-- Select Equipment Category --</option>';
    if (!typeId) { 
        subTypeSelect.innerHTML = '<option value="">-- First select Type --</option>'; 
        return; 
    }
    
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
            } else {
                subTypeSelect.innerHTML = '<option value="">-- No categories --</option>';
            }
        })
        .catch(error => { 
            subTypeSelect.innerHTML = '<option value="">-- Error --</option>'; 
        });
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
        previewAutoPropertyNumber();
    } else {
        autoSection.style.display = 'none';
        customSection.style.display = 'block';
        document.getElementById('propertyPreviewAuto').style.display = 'none';
        if (!editId) propInput.focus();
    }
}

function previewAutoPropertyNumber() {
    if (document.getElementById('property_auto').checked) {
        var bigQty = parseFloat(document.getElementById('big_quantity').value) || 1;
        
        fetch('?ajax=get_property_preview&type_id=0&sub_type_id=0&quantity=' + bigQty)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById('autoPropertyPreviewText').innerHTML = '<code>' + data.property_format + '</code>';
                    document.getElementById('propertyPreviewAuto').style.display = 'block';
                }
            }).catch(() => document.getElementById('propertyPreviewAuto').style.display = 'none');
    }
}

function previewManualPropertyNumber() {
    var manual = document.getElementById('property_number').value.trim();
    var preview = document.getElementById('manualPropertyPreview');
    if (manual) {
        fetch('?ajax=check_property_number&property_no=' + encodeURIComponent(manual))
            .then(response => response.json())
            .then(data => {
                if (data.exists) preview.innerHTML = '<span style="color:#f44336;">Already exists!</span>';
                else preview.innerHTML = '<span style="color:#4CAF50;">Available: ' + escapeHtml(manual) + '</span>';
            }).catch(() => preview.innerHTML = '<span>Checking...</span>');
    } else preview.innerHTML = '';
}

function calculateCompoundTotal() {
    let bigQty = parseFloat(document.getElementById('big_quantity').value) || 0;
    let pieces = parseFloat(document.getElementById('pieces_per_big_unit').value) || 1;
    let total = bigQty * pieces;
    let smallUnit = document.getElementById('small_unit').value || 'pieces';
    let bigUnit = document.getElementById('big_unit').value || '';
    
    let displayText = bigQty + ' ' + bigUnit + ' × ' + pieces + ' ' + smallUnit + ' = ' + total + ' ' + smallUnit;
    document.getElementById('total_quantity_display').value = displayText;
    document.getElementById('quantity_hidden').value = total;
    
    // Show/hide multiple barcode option
    let isMultiple = bigQty > 1 && !document.getElementById('editId').value;
    let multipleOption = document.getElementById('multipleBarcodeOption');
    if (isMultiple) {
        multipleOption.style.display = 'block';
        if (document.getElementById('multiple_barcodes') && document.getElementById('multiple_barcodes').checked) { 
            document.getElementById('generate_multiple_barcodes').value = '1'; 
            document.getElementById('barcodePreviewContainer').style.display = 'block'; 
            previewMultipleBarcodes(); 
        }
    } else { 
        multipleOption.style.display = 'none'; 
        if (document.getElementById('multiple_barcodes')) {
            document.getElementById('multiple_barcodes').checked = false; 
        }
        document.getElementById('barcodePreviewContainer').style.display = 'none'; 
        document.getElementById('generate_multiple_barcodes').value = '0'; 
    }
    calculateTotal();
    previewAutoPropertyNumber();
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
    let prefix = 'PPE';
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
    let qty = parseFloat(document.getElementById('quantity_hidden').value) || 0;
    let unit = parseFloat(document.getElementById('unit_value').value) || 0;
    document.getElementById('total_value').value = '₱' + (qty * unit).toFixed(2);
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New PPE Item';
    document.getElementById('ppeForm').reset();
    document.getElementById('editId').value = '';
    document.querySelector('input[name="action"]').value = 'add';
    document.getElementById('ppeModal').style.display = 'block';
    document.getElementById('property_auto').checked = true;
    document.getElementById('property_number').value = '';
    document.getElementById('manualPropertyPreview').innerHTML = '';
    document.getElementById('propertyPreviewAuto').style.display = 'none';
    togglePropertyOption();
    document.getElementById('big_quantity').value = 1;
    document.getElementById('pieces_per_big_unit').value = 1;
    document.getElementById('type_equipment_id').value = '';
    document.getElementById('equipment_sub_type_id').innerHTML = '<option value="">-- First select Type --</option>';
    calculateCompoundTotal();
}

function openEditModal(id) {
    closeModal();
    
    fetch('?ajax=get_edit_item&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let item = data.data;
                
                document.getElementById('modalTitle').textContent = 'Edit PPE Item';
                document.getElementById('editId').value = item.id;
                document.querySelector('input[name="action"]').value = 'edit';
                
                document.getElementById('article_name').value = item.article_name || '';
                document.getElementById('description').value = item.description || '';
                
                if (item.property_no) {
                    document.getElementById('property_custom').checked = true;
                    document.getElementById('property_number').value = item.property_no;
                    togglePropertyOption();
                    document.getElementById('property_number').setAttribute('readonly', true);
                } else {
                    document.getElementById('property_auto').checked = true;
                    togglePropertyOption();
                    document.getElementById('property_number').removeAttribute('readonly');
                }
                
                if (item.type_equipment_id) {
                    document.getElementById('type_equipment_id').value = item.type_equipment_id;
                    loadEquipmentSubTypes();
                    setTimeout(() => {
                        if (item.equipment_sub_type_id) {
                            document.getElementById('equipment_sub_type_id').value = item.equipment_sub_type_id;
                        }
                    }, 500);
                }
                
                document.getElementById('big_unit').value = item.big_unit || '';
                document.getElementById('big_quantity').value = item.big_quantity || 1;
                document.getElementById('small_unit').value = item.small_unit || '';
                document.getElementById('pieces_per_big_unit').value = item.pieces_per_big_unit || 1;
                document.getElementById('unit_value').value = item.unit_value || 0;
                document.getElementById('fund_cluster').value = item.fund_cluster_id || '';
                
var supplierSelect = document.getElementById('supplier');
if (supplierSelect) {
    supplierSelect.value = item.supplier || '';
}
                document.getElementById('ref_po_number').value = item.ref_po_number || '';
                document.getElementById('delivery_date').value = item.delivery_date || '';
                document.getElementById('condition_text').value = item.condition_text || 'Serviceable';
                document.getElementById('remarks').value = item.remarks || '';
                document.getElementById('barcode_data').value = item.barcode_data || '';
                
                if (item.certified_correct_array && item.certified_correct_array.length) {
                    let certSelect = document.getElementById('certified_correct');
                    Array.from(certSelect.options).forEach(opt => {
                        if (item.certified_correct_array.includes(parseInt(opt.value))) {
                            opt.selected = true;
                        }
                    });
                }
                
                if (item.approved_by_array && item.approved_by_array.length) {
                    let appSelect = document.getElementById('approved_by');
                    Array.from(appSelect.options).forEach(opt => {
                        if (item.approved_by_array.includes(parseInt(opt.value))) {
                            opt.selected = true;
                        }
                    });
                }
                
                if (item.verified_by_array && item.verified_by_array.length) {
                    let verSelect = document.getElementById('verified_by');
                    Array.from(verSelect.options).forEach(opt => {
                        if (item.verified_by_array.includes(parseInt(opt.value))) {
                            opt.selected = true;
                        }
                    });
                }
                
                calculateCompoundTotal();
                document.getElementById('ppeModal').style.display = 'block';
            } else {
                alert('Error loading item data');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading item data');
        });
}

function closeModal() { 
    document.getElementById('ppeModal').style.display = 'none'; 
    document.getElementById('ppeForm').reset();
    document.getElementById('editId').value = '';
    document.querySelector('input[name="action"]').value = 'add';
    let propNumber = document.getElementById('property_number');
    if (propNumber) propNumber.removeAttribute('readonly');
    let url = new URL(window.location.href); 
    url.searchParams.delete('edit'); 
    window.history.replaceState({}, document.title, url.toString()); 
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

function closeBarcodeModal() {
    document.getElementById('barcodeModal').style.display = 'none';
}
function generateBarcodeForEdit() {
    let date = new Date();
    let dateStr = date.getFullYear() + '-' + String(date.getMonth()+1).padStart(2,'0') + '-' + String(date.getDate()).padStart(2,'0');
    let random = Math.floor(1000 + Math.random() * 9000);
    let barcode = 'PPE-' + dateStr + '-' + random;
    document.getElementById('barcode_data').value = barcode;
    
    let preview = document.getElementById('barcodePreview');
    let imgUrl = '?generate_barcode=1&barcode_value=' + encodeURIComponent(barcode);
    preview.innerHTML = '<img src="' + imgUrl + '" style="max-width:200px;border:1px solid #ddd;padding:10px;border-radius:5px;">';
}

function viewItem(id) {
    let modal = document.getElementById('viewModal');
    let content = document.getElementById('viewModalContent');
    modal.style.display = 'block';
    content.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Loading...</div>';
    
    fetch('?ajax=get_item&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let item = data.data;
                let bigDisplay = (item.big_quantity && item.big_unit && item.big_unit != '0') ? item.big_quantity + ' ' + item.big_unit : 'N/A';
                let smallDisplay = (item.pieces_per_big_unit && item.small_unit && item.small_unit != '0') ? item.pieces_per_big_unit + ' ' + item.small_unit : (item.small_unit && item.small_unit != '0' ? item.small_unit : 'N/A');
                
                let html = `
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-info-circle"></i> Basic Information</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item"><div class="detail-label">Article Name</div><div class="detail-value">${escapeHtml(item.article_name)}</div></div>
                                <div class="detail-item"><div class="detail-label">Property No.</div><div class="detail-value">${escapeHtml(item.property_no)}</div></div>
                                <div class="detail-item"><div class="detail-label">Description</div><div class="detail-value">${escapeHtml(item.description || 'N/A')}</div></div>
                                <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value">${item.is_issued ? 'Issued' : 'Available'}</div></div>
                            </div>
                        </div>
                    </div>
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-tags"></i> Classification</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item"><div class="detail-label">Type</div><div class="detail-value">${escapeHtml(item.type_equipment_name || 'N/A')}</div></div>
                                <div class="detail-item"><div class="detail-label">Category</div><div class="detail-value">${escapeHtml(item.sub_type_name || 'N/A')}</div></div>
                                <div class="detail-item"><div class="detail-label">Condition</div><div class="detail-value">${escapeHtml(item.condition_text)}</div></div>
                            </div>
                        </div>
                    </div>
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-calculator"></i> Quantity & Value</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item"><div class="detail-label">Big Unit</div><div class="detail-value">${escapeHtml(bigDisplay)}</div></div>
                                <div class="detail-item"><div class="detail-label">Small Unit</div><div class="detail-value">${escapeHtml(smallDisplay)}</div></div>
                                <div class="detail-item"><div class="detail-label">Total Quantity</div><div class="detail-value">${item.qty_physical_count}</div></div>
                                <div class="detail-item"><div class="detail-label">Unit Value</div><div class="detail-value">₱${parseFloat(item.unit_value).toFixed(2)}</div></div>
                                <div class="detail-item"><div class="detail-label">Total Value</div><div class="detail-value">₱${(item.qty_physical_count * item.unit_value).toFixed(2)}</div></div>
                                <div class="detail-item"><div class="detail-label">Fund Cluster</div><div class="detail-value">${escapeHtml(item.fund_cluster_name || 'N/A')}</div></div>
                            </div>
                        </div>
                    </div>
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-truck"></i> Supplier Information</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item"><div class="detail-label">Supplier</div><div class="detail-value">${escapeHtml(item.supplier || 'N/A')}</div></div>
                                <div class="detail-item"><div class="detail-label">PO Number</div><div class="detail-value">${escapeHtml(item.ref_po_number || 'N/A')}</div></div>
                                <div class="detail-item"><div class="detail-label">Delivery Date</div><div class="detail-value">${item.delivery_date || 'N/A'}</div></div>
                            </div>
                        </div>
                    </div>
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-users"></i> Personnel</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item"><div class="detail-label">Certified Correct</div><div class="detail-value">${escapeHtml(item.certified_correct_names || 'N/A')}</div></div>
                                <div class="detail-item"><div class="detail-label">Approved By</div><div class="detail-value">${escapeHtml(item.approved_by_names || 'N/A')}</div></div>
                                <div class="detail-item"><div class="detail-label">Verified By</div><div class="detail-value">${escapeHtml(item.verified_by_names || 'N/A')}</div></div>
                            </div>
                        </div>
                    </div>
                `;
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error loading item</div>';
            }
        })
        .catch(() => content.innerHTML = '<div class="alert alert-danger">Error loading item</div>');
}

function escapeHtml(text) {
    if (!text) return '';
    let div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showBarcodeModal(barcode, name, propertyNo) {
    if (propertyNo) {
        let lastDash = propertyNo.lastIndexOf('-');
        let baseProperty = lastDash !== -1 ? propertyNo.substring(0, lastDash) : propertyNo;
        
        fetch('?ajax=get_all_barcodes&property_no=' + encodeURIComponent(baseProperty))
            .then(response => response.json())
            .then(data => {
                if (data.success && data.count > 1) {
                    window.currentBarcodeItems = data.items;
                    window.currentBarcodeGroupName = name;
                    
                    let content = '<div style="max-height: 500px; overflow-y: auto;">';
                    data.items.forEach(function(item, index) {
                        let printCopies = item.pieces_per_big_unit || 1;  // CHANGED: use pieces_per_big_unit
                        let copiesText = printCopies > 1 ? ` (${printCopies} copies - one per ${item.small_unit})` : '';
                        
                        content += `
                            <div style="border:1px solid #ddd; border-radius:8px; padding:15px; margin-bottom:15px; text-align:center;">
                                <div style="background:#6B8CFF; color:white; display:inline-block; padding:3px 12px; border-radius:20px; margin-bottom:10px;">
                                    Item ${index + 1} of ${data.count}
                                </div>
                                <h4>${escapeHtml(item.article_name)}</h4>
                                <div><strong>Property No:</strong> ${escapeHtml(item.property_no)}</div>
                                <div><strong>Unit:</strong> ${escapeHtml(item.big_unit)} (${item.big_quantity}) / ${escapeHtml(item.small_unit)} (${item.pieces_per_big_unit} per ${item.big_unit})</div>
                                <div><strong>Will print:</strong> ${printCopies} ${escapeHtml(item.small_unit)}${copiesText}</div>
                                <div style="margin:10px 0;">
                                    <img src="?generate_barcode=1&barcode_value=${encodeURIComponent(item.barcode_data)}" 
                                         style="border:1px solid #ddd; padding:5px; max-width:250px;">
                                </div>
                                <div><small>${escapeHtml(item.barcode_data)}</small></div>
                                <button class="btn-xs" onclick="printSingleBarcode('${escapeHtml(item.barcode_data)}', '${escapeHtml(item.article_name)}', ${item.pieces_per_big_unit})" style="margin-top:10px;">
                                    <i class="fas fa-print"></i> Print (${printCopies} ${item.small_unit}s)
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
                    showSingleBarcode(item.barcode_data, item.article_name, item.pieces_per_big_unit);  // CHANGED
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
                <img src="?generate_barcode=1&barcode_value=${encodeURIComponent(barcode)}" 
                     style="max-width:100%;border:1px solid #ddd;padding:10px;border-radius:5px;">
            </div>
            <div style="font-family:monospace;">${escapeHtml(barcode)}</div>
            ${copies > 1 ? `<div style="margin-top:10px;color:#6B8CFF;">Will print ${copies} copies (one per Small Unit)</div>` : ''}
        </div>
    `;
    document.getElementById('barcodeModalNumber').innerHTML = barcode;
    document.getElementById('barcodeModal').style.display = 'block';
    
    window.currentSingleBarcode = { barcode: barcode, name: name, copies: copies };
}
function printSingleBarcode(barcode, name, copies = 1) {
    let win = window.open('', '_blank');
    let htmlContent = '<html><head><title>Print Barcode</title><style>';
    htmlContent += 'body{text-align:center;padding:20px;font-family:Arial,sans-serif}';
    htmlContent += '.barcode-card{border:1px dashed #6B8CFF;border-radius:10px;padding:20px;margin-bottom:20px;page-break-after:avoid;break-inside:avoid}';
    htmlContent += '.ppe-label{background:#6B8CFF;padding:5px 15px;border-radius:20px;display:inline-block;margin-bottom:15px;color:white}';
    htmlContent += '.item-name{font-size:14px;font-weight:bold;margin:10px 0}';
    htmlContent += '.barcode-number{font-family:monospace;font-size:12px;margin-top:10px}';
    htmlContent += '@media print{.barcode-card{break-inside:avoid}body{padding:0}}';
    htmlContent += '</style></head><body>';
    
    // Generate copies based on pieces_per_big_unit (Small Unit quantity)
    for (let i = 1; i <= copies; i++) {
        htmlContent += `
            <div class="barcode-card">
                <div class="ppe-label">PPE EQUIPMENT</div>
                <img src="?generate_barcode=1&barcode_value=${encodeURIComponent(barcode)}" alt="Barcode">
                <div class="item-name">${escapeHtml(name)}</div>
                <div class="barcode-number">${escapeHtml(barcode)}</div>
                ${copies > 1 ? `<div style="font-size:10px;color:#999;margin-top:5px;">Item ${i} of ${copies}</div>` : ''}
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
    
    if (!items || items.length === 0) {
        alert('No barcodes to print');
        return;
    }
    
    let win = window.open('', '_blank');
    let htmlContent = '<html><head><title>Print All Barcodes</title><style>';
    htmlContent += 'body{font-family:Arial,sans-serif;padding:20px}';
    htmlContent += '.barcode-card{border:1px solid #ddd;border-radius:12px;padding:20px;margin-bottom:30px;text-align:center;page-break-after:avoid;break-inside:avoid}';
    htmlContent += '.ppe-label{background:#6B8CFF;display:inline-block;padding:5px 15px;border-radius:20px;margin-bottom:10px;color:white}';
    htmlContent += '.item-name{font-size:14px;font-weight:bold;margin:10px 0}';
    htmlContent += '.barcode-number{font-family:monospace;font-size:12px;margin-top:10px}';
    htmlContent += '.copy-label{font-size:10px;color:#999;margin-top:5px}';
    htmlContent += '@media print{.barcode-card{break-inside:avoid}}';
    htmlContent += '</style></head><body>';
    htmlContent += '<h2 style="text-align:center;">PPE Barcodes</h2>';
    
    // Generate barcodes for each item, with copies based on pieces_per_big_unit
    items.forEach(function(item, itemIndex) {
        let copies = item.pieces_per_big_unit || 1;  // CHANGED: use pieces_per_big_unit
        
        for (let copy = 1; copy <= copies; copy++) {
            htmlContent += `
                <div class="barcode-card">
                    <div class="ppe-label">PPE EQUIPMENT</div>
                    <div style="margin-bottom:5px;font-size:12px;color:#666;">${escapeHtml(item.article_name)}</div>
                    <div style="margin-bottom:5px;font-size:11px;color:#888;">Property: ${escapeHtml(item.property_no)}</div>
                    <img src="?generate_barcode=1&barcode_value=${encodeURIComponent(item.barcode_data)}" alt="Barcode">
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
</script>

<?php
include INCLUDE_PATH . '/footer.php';
ob_end_flush();
?>