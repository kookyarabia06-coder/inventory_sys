<?php
// Add output buffering at the very top to prevent header warnings
ob_start();

/**
 * Semi-Expendable Items Page (Admin)
 * Complete Semi-Expendable management system with all inventory fields
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

/// Require admin role
if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'superadmin', 'supply'])) {
    requireRole('admin');
}

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Semi-Expendable Items';
$page_description = 'Manage Semi-Expendable Inventory';

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

// AJAX endpoint to get sub types
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
    $month = date('m');
    $day = date('d');
    $base_format = $year . '-' . $month . '-' . $day;
    
    // Get next sequence number for TODAY
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


// Get multiple items for barcode view
if (isset($_GET['get_multiple_items'])) {
    header('Content-Type: application/json');
    $property_no = $_GET['property_no'] ?? '';
    
    if (empty($property_no)) {
        echo json_encode(['error' => 'No property number provided']);
        exit;
    }
    
    $base_property = preg_replace('/-\d+$/', '', $property_no);
    $stmt = $conn->prepare("
        SELECT i.*, e.name as equipment_name, s.supplier_name
        FROM semi_ppe i
        LEFT JOIN equipment e ON i.equipment_id = e.id
        LEFT JOIN supplier s ON i.supplier_id = s.id
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
               s.business_add as supplier_business_add,
               s.email as supplier_email,
               s.website as supplier_website,
               s.tin as supplier_tin,
               s.contact_person as supplier_contact_person,
               s.contact_no as supplier_contact_no,
               s.terms as supplier_terms,
               s.manufacturer as supplier_manufacturer,
               s.vat_condition as supplier_vat_condition,
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
        // Format multi-select values
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
        
        // Decode UOM JSON for view modal
        if ($item['uom'] && strpos($item['uom'], '{') !== false) {
            $uom_data = json_decode($item['uom'], true);
            if ($uom_data) {
                $item['display_uom'] = $uom_data['display'] ?? $item['uom'];
                $item['big_unit'] = $uom_data['big_unit'] ?? '';
                $item['big_quantity'] = $uom_data['big_quantity'] ?? 0;
                $item['small_unit'] = $uom_data['small_unit'] ?? '';
                $item['pieces_per_big_unit'] = $uom_data['pieces_per_big_unit'] ?? 1;
            } else {
                $item['display_uom'] = $item['uom'];
            }
        } else {
            $item['display_uom'] = $item['uom'];
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
        // Parse UOM JSON if stored as JSON
        if ($item['uom'] && strpos($item['uom'], '{') !== false) {
            $uom_data = json_decode($item['uom'], true);
            if ($uom_data) {
                $item['big_unit'] = $uom_data['big_unit'] ?? '';
                $item['big_quantity'] = $uom_data['big_quantity'] ?? 0;
                $item['pieces_per_big_unit'] = $uom_data['pieces_per_big_unit'] ?? 1;
                $item['small_unit'] = $uom_data['small_unit'] ?? '';
                $item['total_pieces'] = $uom_data['total_pieces'] ?? $item['qty_physical_count'];
                $item['display_uom'] = $uom_data['display'] ?? '';
            }
        }
        
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

// ============================================
// FUNCTION TO GENERATE PROPERTY NUMBER
// ============================================

function generatePropertyNumber($conn, $type_equipment_id, $equipment_sub_type_id, $sequence_number = null) {
    // Get current date
    $year = date('Y');
    $month = date('m');
    $day = date('d');
    
    // Build base pattern: YYYY-MM-DD
    $base_pattern = $year . '-' . $month . '-' . $day;
    
    // If sequence number is provided (for multiple items), use it directly
    if ($sequence_number !== null) {
        return $base_pattern . '-' . str_pad($sequence_number, 4, '0', STR_PAD_LEFT);
    }
    
    // For single item, get the next sequence number for TODAY
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
// FORM HANDLERS
// ============================================
// Handle Add Semi-Expendable Item
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $article_name = sanitize($_POST['article_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    // Compound UOM fields
    $big_unit = sanitize($_POST['big_unit'] ?? '');
    $big_quantity = floatval($_POST['big_quantity'] ?? 0);
    $small_unit = sanitize($_POST['small_unit'] ?? '');
    $pieces_per_big_unit = floatval($_POST['pieces_per_big_unit'] ?? 1);
    $total_quantity = $big_quantity * $pieces_per_big_unit;
    
    // Store UOM as JSON
    $uom_json = json_encode([
        'big_unit' => $big_unit,
        'big_quantity' => $big_quantity,
        'small_unit' => $small_unit,
        'pieces_per_big_unit' => $pieces_per_big_unit,
        'total_pieces' => $total_quantity,
        'display' => $big_quantity . ' ' . $big_unit . ' × ' . $pieces_per_big_unit . ' ' . $small_unit . ' = ' . $total_quantity . ' ' . $small_unit
    ]);
    
    $unit_value = floatval($_POST['unit_value'] ?? 0);
    $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : 0;
    $category = 'Semi-Expendable';
    
    $type_equipment_id = !empty($_POST['type_equipment_id']) ? (int)$_POST['type_equipment_id'] : 0;
    $equipment_sub_type_id = !empty($_POST['equipment_sub_type_id']) ? (int)$_POST['equipment_sub_type_id'] : 0;
    
    $fund_cluster = sanitize($_POST['fund_cluster'] ?? '');
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
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
    $barcode_data = sanitize($_POST['barcode_data'] ?? '');
    $created_by = $_SESSION['user_id'];
    $generate_multiple = isset($_POST['generate_multiple_barcodes']) && $_POST['generate_multiple_barcodes'] == '1';
    
    $errors = [];
    if (empty($article_name)) $errors[] = "Article name is required";
    if (empty($big_unit)) $errors[] = "Big unit is required";
    if (empty($small_unit)) $errors[] = "Small unit is required";
    if ($big_quantity <= 0) $errors[] = "Quantity must be greater than 0";
    if ($pieces_per_big_unit <= 0) $errors[] = "Pieces per big unit must be greater than 0";
    if ($unit_value <= 0) $errors[] = "Unit value must be greater than 0";
    if (empty($type_equipment_id)) $errors[] = "Type of Equipment is required";
    if (empty($equipment_sub_type_id)) $errors[] = "Equipment Category is required";
    
    if (empty($errors)) {
        $conn->begin_transaction();
        $success_count = 0;
        
        try {
            if ($generate_multiple && $total_quantity > 1 && floor($total_quantity) == $total_quantity) {
                // Multiple items - one barcode per piece
                $year = date('Y');
                $month = date('m');
                $day = date('d');
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
                
                $base_barcode = !empty($barcode_data) ? $barcode_data : $big_unit . '-' . $small_unit . '-' . date('Ymd');
                
                for ($i = 1; $i <= $total_quantity; $i++) {
                    $property_no = $base_format . '-' . str_pad($start_seq + $i - 1, 4, '0', STR_PAD_LEFT);
                    $sequential_barcode = $base_barcode . '-' . str_pad($i, 4, '0', STR_PAD_LEFT);
                    
                    $stmt = $conn->prepare("
                        INSERT INTO semi_ppe (
                            article_name, description, property_no, uom, 
                            qty_property_card, qty_physical_count, unit_value,
                            equipment_id, type_equipment_id, equipment_sub_type_id, 
                            condition_text, fund_cluster, certified_correct, 
                            approved_by, verified_by, supplier_id, ref_po_number, 
                            delivery_date, remarks, barcode_data, created_by, 
                            year_acquired, category
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $single_qty = 1;
                    
                    $stmt->bind_param(
                        "ssssdddiiisssssiissisiii",
                        $article_name, // s
                        $description, // s
                        $property_no, // s
                        $uom_json, // s
                        $single_qty, // d
                        $single_qty, // d
                        $unit_value, // d
                        $equipment_id, // i
                        $type_equipment_id, // s
                        $equipment_sub_type_id, // i
                        $condition_text, // 
                        $fund_cluster,
                        $certified_correct,
                        $approved_by,
                        $verified_by,
                        $supplier_id,
                        $ref_po_number,
                        $delivery_date,
                        $remarks,
                        $sequential_barcode,
                        $created_by,
                        $year_acquired,
                        $category
                    );
                    
                    if ($stmt->execute()) {
                        $success_count++;
                    }
                    $stmt->close();
                }
                $conn->commit();
                $_SESSION['success'] = "$success_count Semi-Expendable items added successfully.";
            } else {
                // Single item
                $property_no = generatePropertyNumber($conn, $type_equipment_id, $equipment_sub_type_id);
                
                if (empty($barcode_data)) {
                    $barcode_data = $property_no;
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO semi_ppe (
                        article_name, description, property_no, uom, 
                        qty_property_card, qty_physical_count, unit_value,
                        equipment_id, type_equipment_id, equipment_sub_type_id, 
                        condition_text, fund_cluster, certified_correct, 
                        approved_by, verified_by, supplier_id, ref_po_number, 
                        delivery_date, remarks, barcode_data, created_by, 
                        year_acquired, category
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $stmt->bind_param(
                    "ssssdddiiisssssiissisiii",
                    $article_name,
                    $description,
                    $property_no,
                    $uom_json,
                    $total_quantity,
                    $total_quantity,
                    $unit_value,
                    $equipment_id,
                    $type_equipment_id,
                    $equipment_sub_type_id,
                    $condition_text,
                    $fund_cluster,
                    $certified_correct,
                    $approved_by,
                    $verified_by,
                    $supplier_id,
                    $ref_po_number,
                    $delivery_date,
                    $remarks,
                    $barcode_data,
                    $created_by,
                    $year_acquired,
                    $category
                );
                
                if ($stmt->execute()) {
                    $conn->commit();
                    $_SESSION['success'] = "Semi-Expendable item added successfully. Property No: $property_no";
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
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit' && isset($_POST['id'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
    
    $id = (int)$_POST['id'];
    $article_name = sanitize($_POST['article_name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    // Compound UOM fields
    $big_unit = sanitize($_POST['big_unit'] ?? '');
    $big_quantity = floatval($_POST['big_quantity'] ?? 0);
    $small_unit = sanitize($_POST['small_unit'] ?? '');
    $pieces_per_big_unit = floatval($_POST['pieces_per_big_unit'] ?? 1);
    $total_quantity = $big_quantity * $pieces_per_big_unit;
    
    $uom_json = json_encode([
        'big_unit' => $big_unit,
        'big_quantity' => $big_quantity,
        'small_unit' => $small_unit,
        'pieces_per_big_unit' => $pieces_per_big_unit,
        'total_pieces' => $total_quantity,
        'display' => $big_quantity . ' ' . $big_unit . ' × ' . $pieces_per_big_unit . ' ' . $small_unit . ' = ' . $total_quantity . ' ' . $small_unit
    ]);
    
    $unit_value = floatval($_POST['unit_value'] ?? 0);
    $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
    
    $type_equipment_id = !empty($_POST['type_equipment_id']) ? (int)$_POST['type_equipment_id'] : null;
    $equipment_sub_type_id = !empty($_POST['equipment_sub_type_id']) ? (int)$_POST['equipment_sub_type_id'] : null;
    
    $fund_cluster = sanitize($_POST['fund_cluster'] ?? '');
    $supplier_id = !empty($_POST['supplier_id']) ? (int)$_POST['supplier_id'] : null;
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
    
    $stmt = $conn->prepare("
        UPDATE semi_ppe SET 
            article_name = ?,
            description = ?,
            uom = ?,
            qty_physical_count = ?,
            qty_property_card = ?,
            unit_value = ?,
            equipment_id = ?,
            type_equipment_id = ?,
            equipment_sub_type_id = ?,
            condition_text = ?,
            fund_cluster = ?,
            certified_correct = ?,
            approved_by = ?,
            verified_by = ?,
            supplier_id = ?,
            ref_po_number = ?,
            delivery_date = ?,
            remarks = ?,
            barcode_data = ?,
            date_updated = NOW()
        WHERE id = ?
    ");
    
    if (!$stmt) {
        die("Prepare failed: " . $conn->error);
    }
    
    $stmt->bind_param(
        "sssdddiiissssssiissisii",
        $article_name,
        $description,
        $uom_json,
        $total_quantity,
        $total_quantity,
        $unit_value,
        $equipment_id,
        $type_equipment_id,
        $equipment_sub_type_id,
        $condition_text,
        $fund_cluster,
        $certified_correct,
        $approved_by,
        $verified_by,
        $supplier_id,
        $ref_po_number,
        $delivery_date,
        $remarks,
        $barcode_data,
        $id
    );
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Semi-Expendable item updated successfully";
    } else {
        $_SESSION['error'] = "Error updating item: " . $stmt->error;
    }
    $stmt->close();
    
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

// Barcode handler for semi-expendable
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
    $params = [$search_term, $search_term, $search_term, $search_term];
    $types = "ssss";
} else {
    $params = [];
    $types = "";
}

$query .= " ORDER BY i.date_added DESC";

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_rows = $total_result->fetch_assoc()['total'];
$stmt->close();

$offset = ($page - 1) * $per_page;
$query .= " LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!empty($params)) {
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
    // Decode UOM JSON for display
    $row['big_unit_display'] = '';
    $row['small_unit_display'] = '';
    $row['quantity_display'] = $row['qty_physical_count'] ?? 0;
    
    if (!empty($row['uom'])) {
        if (strpos($row['uom'], '{') === 0 || strpos($row['uom'], '[') === 0) {
            $uom_data = json_decode($row['uom'], true);
            if ($uom_data && is_array($uom_data)) {
                // Format Big Unit display: "2 Box"
                if (!empty($uom_data['big_quantity']) && !empty($uom_data['big_unit'])) {
                    $row['big_unit_display'] = $uom_data['big_quantity'] . ' ' . $uom_data['big_unit'];
                }
                // Format Small Unit display: "10 Piece" or just the unit
                if (!empty($uom_data['pieces_per_big_unit']) && !empty($uom_data['small_unit'])) {
                    $row['small_unit_display'] = $uom_data['pieces_per_big_unit'] . ' ' . $uom_data['small_unit'];
                } elseif (!empty($uom_data['small_unit'])) {
                    $row['small_unit_display'] = $uom_data['small_unit'];
                }
                // Store total quantity
                $row['quantity_display'] = $uom_data['total_pieces'] ?? $row['qty_physical_count'];
                // Store for edit modal
                $row['big_unit'] = $uom_data['big_unit'] ?? '';
                $row['big_quantity'] = $uom_data['big_quantity'] ?? 0;
                $row['small_unit'] = $uom_data['small_unit'] ?? '';
                $row['pieces_per_big_unit'] = $uom_data['pieces_per_big_unit'] ?? 1;
            }
        }
    }
    $semi_items[] = $row;
}
$stmt->close();

$counts = [];
foreach ($semi_items as $r) {
    $base = preg_replace('/-\d+$/', '', $r['property_no']);
    if (!isset($counts[$base])) {
        $counts[$base] = 0;
    }
    if (!empty($r['is_multiple'])) {
        $counts[$base] += 1;
    } else {
        $counts[$base] += floatval($r['quantity_display']);
    }
}
foreach ($semi_items as &$r) {
    $base = preg_replace('/-\d+$/', '', $r['property_no']);
    $r['total_qty'] = $counts[$base] ?? $r['quantity_display'];
}
unset($r);

$pagination_data = [
    'data' => $semi_items,
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
    flex-wrap: wrap;
    gap: 10px;
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

.table-wrapper {
    overflow-x: auto;
    width: 100%;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1500px;
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
    white-space: normal;
    word-wrap: break-word;
    max-width: 250px;
}

tr:hover td {
    background-color: var(--light);
}

tr.stock-alert-row {
    background-color: #FFF3E0;
}
tr.stock-alert-row:hover td {
    background-color: #ffe0b2;
}

.article-name-cell strong {
    color: var(--text-primary);
}
.article-name-cell small {
    font-size: 11px;
    color: var(--text-muted);
    display: block;
    margin-top: 4px;
}

.category-cell {
    line-height: 1.4;
}
.category-cell small {
    font-size: 11px;
    color: var(--text-muted);
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
    padding: 6px 12px;
    font-size: 11px;
    border-radius: 4px;
    background-color: var(--secondary);
    color: var(--text-light);
    border: none;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
    display: inline-block;
    margin: 2px;
}

.btn-xs:hover {
    background-color: #7a9fe6;
    transform: translateY(-1px);
}

.btn-xs i {
    margin-right: 4px;
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
    flex-wrap: wrap;
}

.form-row .form-group {
    flex: 1;
    min-width: 150px;
}

.form-text {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 5px;
}

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

.table-container {
    position: relative;
    overflow: visible !important;
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

.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.dropdown-loading {
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="%236B8CFF" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 2a10 10 0 1 0 10 10"/></svg>');
    background-repeat: no-repeat;
    background-position: right 10px center;
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

<!-- Statistics Cards for Semi-Expendable -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon"><i class="fas fa-boxes"></i></div>
        <h3>Total Semi-Expendable</h3>
        <?php
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM semi_ppe");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_semi = $result->fetch_assoc()['count'];
        $stmt->close();
        ?>
        <div class="card-value"><?php echo $total_semi; ?></div>
        <div class="card-label">All semi-expendable items</div>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-hand-holding"></i></div>
        <h3>Issued Semi-Expendable</h3>
        <?php
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT ei.inventory_id) as count 
            FROM equipment_issuance ei
            JOIN semi_ppe i ON ei.inventory_id = i.id
            WHERE ei.status = 'issued'
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
        <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h3>Low Stock Semi-Expendable</h3>
        <?php
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM semi_ppe WHERE qty_physical_count <= 5");
        $stmt->execute();
        $result = $stmt->get_result();
        $low_stock_semi = $result->fetch_assoc()['count'];
        $stmt->close();
        ?>
        <div class="card-value <?php echo $low_stock_semi > 0 ? 'text-warning' : ''; ?>"><?php echo $low_stock_semi; ?></div>
        <div class="card-label">Need reorder</div>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-dollar-sign"></i></div>
        <h3>Total Value</h3>
        <?php
        $stmt = $conn->prepare("SELECT SUM(unit_value * qty_physical_count) as total FROM semi_ppe");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_value = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        ?>
        <div class="card-value"><?php echo formatCurrency($total_value); ?></div>
        <div class="card-label">Semi-expendable inventory value</div>
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
            <i class="fas fa-hand-holding"></i> Issue Semi-Expendable
        </a>
    </div>
</div>

<!-- Search and Filter -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-search"></i> Search Semi-Expendable Items</h2>
    </div>
    <form method="GET" action="" class="search-box">
        <input type="text" name="search" placeholder="Search by article name, property no., description, or supplier..." 
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
        <h2><i class="fas fa-boxes"></i> Semi-Expendable Items List</h2>
        <?php
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
    
    <div class="table-wrapper">
        <table class="settings-table">
            <thead>
                <tr>
                    <th>Article Name</th>
                    <th>Property No.</th>
                    <th>Category/Type</th>
                    <th>Big Unit (Container)</th>
                    <th>Small Unit (Item)</th>
                    <th>Total Quantity</th>
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
                    <?php
                    $shown = [];
                    foreach ($semi_items as $item):
                        $base = preg_replace('/-\d+$/', '', $item['property_no']);
                        if (isset($shown[$base])) continue;
                        $shown[$base] = true;
                    ?>
                    <tr class="<?php echo $item['qty_physical_count'] <= 5 ? 'stock-alert-row' : ''; ?>">
                        <td class="article-name-cell">
                            <strong><?php echo htmlspecialchars($item['article_name']); ?></strong>
                            <?php if ($item['description']): ?>
                            <br><small><?php echo htmlspecialchars(substr($item['description'], 0, 50) . (strlen($item['description']) > 50 ? '...' : '')); ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($item['property_no']); ?></td>
                        <td class="category-cell">
                            <?php 
                            if ($item['type_equipment_name']):
                                echo htmlspecialchars($item['type_equipment_name']);
                                if ($item['sub_type_name']):
                                    echo '<br><small>' . htmlspecialchars($item['sub_type_name']) . '</small>';
                                endif;
                            else:
                                echo htmlspecialchars($item['category']);
                            endif;
                            ?>
                        </td>
                        <td><?php echo !empty($item['big_unit_display']) ? htmlspecialchars($item['big_unit_display']) : '—'; ?></td>
                        <td><?php echo !empty($item['small_unit_display']) ? htmlspecialchars($item['small_unit_display']) : '—'; ?></td>
                        <td><strong><?php echo number_format($item['quantity_display'], 0); ?></strong></td>
                        <td><?php echo formatCurrency($item['unit_value']); ?></td>
                        <td><?php echo formatCurrency($item['unit_value'] * $item['quantity_display']); ?></td>
                        <td><?php echo htmlspecialchars($item['supplier_name'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($item['fund_cluster'] ?? 'N/A'); ?></td>
                        <td>
                            <?php if ($item['is_issued'] > 0): ?>
                                <span class="badge-warning">Issued</span>
                            <?php else: ?>
                                <span class="badge-success">Available</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if (!empty($item['barcode_data'])): ?>
                                <button class="btn-xs" onclick="showBarcodeModal('<?php echo htmlspecialchars($item['barcode_data']); ?>', '<?php echo htmlspecialchars($item['article_name']); ?>')" title="View Barcode">
                                    <i class="fas fa-qrcode"></i> View
                                </button>
                                <?php if ($item['is_multiple']): ?>
                                    <button class="btn-xs" onclick="viewAllBarcodes('<?php echo htmlspecialchars($item['property_no']); ?>', '<?php echo htmlspecialchars($item['article_name']); ?>')" title="View All Barcodes" style="background-color: var(--primary);">
                                        <i class="fas fa-layer-group"></i> All
                                    </button>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
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
                        <td colspan="13" class="text-center">
                            <i class="fas fa-boxes" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
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
    </div>
    
    <!-- Pagination -->
    <?php echo displayPagination($pagination_data, '?page=' . ($search ? '&search=' . urlencode($search) : '')); ?>
</div>

<!-- Sticky SCAN BARCODE Button -->
<div class="sticky-scan-button-container">
    <a href="<?php echo SITE_URL; ?>/admin/barcodescannerforsemi_expendable.php" class="sticky-scan-button">
        <i class="fas fa-camera"></i> SCAN BARCODE
    </a>
</div>

<!-- Add/Edit Semi-Expendable Modal -->
<div id="semiModal" class="modal" style="display: <?php echo isset($_GET['edit']) ? 'block' : 'none'; ?>;">
    <div class="modal-content" style="max-width: 900px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="flex-shrink: 0;">
            <h2 id="modalTitle"><?php echo isset($_GET['edit']) ? 'Edit Semi-Expendable Item' : 'Add New Semi-Expendable Item'; ?></h2>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        
        <div class="modal-body" style="overflow-y: auto; flex: 1; padding: 20px;">
            <form method="POST" action="" id="semiForm">
                <input type="hidden" name="action" value="<?php echo isset($_GET['edit']) ? 'edit' : 'add'; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" name="generate_multiple_barcodes" id="generate_multiple_barcodes" value="0">
                <?php if (isset($_GET['edit'])): ?>
                <input type="hidden" name="id" id="editId" value="<?php echo (int)$_GET['edit']; ?>">
                <?php else: ?>
                <input type="hidden" name="id" id="editId" value="">
                <?php endif; ?>
                
                <!-- Basic Information -->
                <div class="form-section">
                    <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
                    <div class="form-group">
                        <label for="article_name">Article Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="article_name" name="article_name" required maxlength="255" placeholder="Enter item name">
                    </div>
                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="2" placeholder="Enter detailed description"></textarea>
                    </div>
                </div>
                
                <!-- Classification -->
                <div class="form-section">
                    <h3><i class="fas fa-tags"></i> Classification</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="type_equipment_id">Type of Equipment <span class="text-danger">*</span></label>
                            <select class="form-control" id="type_equipment_id" name="type_equipment_id" required onchange="loadEquipmentSubTypes(); previewPropertyNumber();">
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
                            <small class="form-text text-muted">Select the main equipment type</small>
                        </div>
                        <div class="form-group">
                            <label for="equipment_sub_type_id">Equipment Category <span class="text-danger">*</span></label>
                            <select class="form-control" id="equipment_sub_type_id" name="equipment_sub_type_id" required onchange="previewPropertyNumber();">
                                <option value="">-- First select Type of Equipment --</option>
                            </select>
                            <small class="form-text text-muted">Select the specific equipment sub-category</small>
                        </div>
                    </div>
                    
                    <!-- Property Number Preview -->
                    <div id="propertyPreview" class="form-group" style="margin-top: 10px;"></div>
                </div>
                
                <!-- Supplier Information -->
                <div class="form-section">
                    <h3><i class="fas fa-truck"></i> Supplier Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="supplier_id">Supplier</label>
                            <select class="form-control" id="supplier_id" name="supplier_id">
                                <option value="">-- Select Supplier --</option>
                                <?php 
                                if ($suppliers && $suppliers->num_rows > 0):
                                    $suppliers->data_seek(0);
                                    while($supp = $suppliers->fetch_assoc()): 
                                ?>
                                <option value="<?php echo $supp['id']; ?>">
                                    <?php echo htmlspecialchars($supp['supplier_id'] . ' - ' . $supp['supplier_name']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                            <small class="form-text text-muted">Select supplier from the list. <a href="<?php echo SITE_URL; ?>/admin/system_settings.php">Manage suppliers</a></small>
                        </div>
                        <div class="form-group">
                            <label for="ref_po_number">Reference PO Number</label>
                            <input type="text" class="form-control" id="ref_po_number" name="ref_po_number" placeholder="Enter PO number">
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
                    
                    <!-- Big Unit Section -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="big_unit">Big Unit (Container) <span class="text-danger">*</span></label>
                            <select class="form-control" id="big_unit" name="big_unit" required onchange="calculateCompoundTotal()">
                                <option value="">-- Select Big Unit --</option>
                                <option value="Box">Box</option>
                                <option value="Pack">Pack</option>
                                <option value="Case">Case</option>
                                <option value="Carton">Carton</option>
                                <option value="Bundle">Bundle</option>
                                <option value="Roll">Roll</option>
                                <option value="Set">Set</option>
                                <option value="Ream">Ream</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="big_quantity">Number of Big Units <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="big_quantity" name="big_quantity" value="1" min="1" step="1" onchange="calculateCompoundTotal()">
                        </div>
                    </div>
                    
                    <!-- Conversion Rate -->
                    <div class="form-row">
                        <div class="form-group">
                            <label for="small_unit">Small Unit (Individual Item) <span class="text-danger">*</span></label>
                            <select class="form-control" id="small_unit" name="small_unit" required onchange="calculateCompoundTotal()">
                                <option value="">-- Select Small Unit --</option>
                                <option value="Piece">Piece(s)</option>
                                <option value="Unit">Unit(s)</option>
                                <option value="Each">Each</option>
                            </select>
                            <small class="form-text text-muted">The individual items inside the big unit</small>
                        </div>
                        <div class="form-group">
                            <label for="pieces_per_big_unit">Number of Small Units per 1 Big Unit <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="pieces_per_big_unit" name="pieces_per_big_unit" value="1" min="1" step="1" onchange="calculateCompoundTotal()">
                            <small class="form-text text-muted">Example: 1 Box = 10 Pieces</small>
                        </div>
                    </div>
                    
                    <!-- Total Quantity Display (Read-only) -->
                    <div class="form-group">
                        <label for="total_quantity_display">Total Quantity (in Small Units) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="total_quantity_display" readonly style="background: #e8f4f8; font-weight: bold;">
                        <input type="hidden" id="quantity" name="quantity" value="0">
                        <small class="form-text text-muted">Total = Big Units × Pieces per Big Unit</small>
                    </div>
                    
                    <!-- Multiple Barcodes Option -->
                    <div id="multipleBarcodeOption" class="form-group" style="display: none; margin-top: 15px; padding: 15px; background: #e8f4f8; border-radius: 8px; border-left: 4px solid #F16D34;">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="multiple_barcodes" onchange="toggleMultipleBarcodes()">
                            <label class="form-check-label" for="multiple_barcodes">
                                <strong>Generate individual barcodes for each <span id="smallUnitNameLabel">small unit</span> (<span id="itemCountDisplay">0</span> items)</strong>
                            </label>
                            <small class="form-text text-muted" style="display: block; margin-top: 5px;">
                                When checked, each individual <span id="smallUnitNameText">piece</span> will have its own unique barcode
                            </small>
                        </div>
                        <div id="barcodePreviewContainer" style="margin-top: 15px; display: none;">
                            <label>Barcode Preview (first 10 items):</label>
                            <div id="multipleBarcodePreview" class="barcode-preview-grid" style="max-height: 300px; overflow-y: auto;"></div>
                        </div>
                    </div>
                </div>
                
                <!-- Unit Value and Total Value -->
                <div class="form-section">
                    <h3><i class="fas fa-dollar-sign"></i> Value Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="unit_value">Unit Value (₱) per Small Unit <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="unit_value" name="unit_value" min="0.01" step="0.01" required placeholder="0.00" onchange="calculateTotal()">
                            <small class="form-text text-muted">Value per 1 piece/unit</small>
                        </div>
                        <div class="form-group">
                            <label for="total_value">Total Value</label>
                            <input type="text" class="form-control" id="total_value" readonly placeholder="₱0.00">
                            <small class="form-text text-muted">Total Value = Total Quantity × Unit Value</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="fund_cluster">Fund Cluster</label>
                        <select class="form-control" id="fund_cluster" name="fund_cluster">
                            <option value="">-- Select Fund Cluster --</option>
                            <?php 
                            if ($fund_clusters): 
                                $fund_clusters->data_seek(0);
                                while($fund = $fund_clusters->fetch_assoc()): 
                            ?>
                            <option value="<?php echo htmlspecialchars($fund['name']); ?>">
                                <?php echo htmlspecialchars($fund['name']); ?>
                            </option>
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
                                <option value="For Disposal">For Disposal</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="certified_correct">Certified Correct By (Multi-Select)</label>
                            <select class="form-control" id="certified_correct" name="certified_correct[]" multiple size="2">
                                <?php if ($users): $users->data_seek(0); while($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                            <small class="form-text text-muted">Hold Ctrl to select multiple users</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="approved_by">Approved By (Multi-Select)</label>
                            <select class="form-control" id="approved_by" name="approved_by[]" multiple size="2">
                                <?php if ($users): $users->data_seek(0); while($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                            <small class="form-text text-muted">Hold Ctrl to select multiple users</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="verified_by">Verified By (Multi-Select)</label>
                            <select class="form-control" id="verified_by" name="verified_by[]" multiple size="2">
                                <?php if ($users): $users->data_seek(0); while($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                            <small class="form-text text-muted">Hold Ctrl to select multiple users</small>
                        </div>
                    </div>
                </div>
                
                <!-- Remarks and Barcode -->
                <div class="form-section">
                    <h3><i class="fas fa-tasks"></i> Additional Information</h3>
                    
                    <div class="form-group">
                        <label for="remarks">Remarks</label>
                        <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="Any additional notes"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="barcode_data">Barcode</label>
                        <div class="barcode-input-group">
                            <input type="text" class="form-control" id="barcode_data" name="barcode_data" 
                                   placeholder="Enter or generate barcode (for multiple items, this will be the base)"
                                   pattern="[A-Za-z0-9\-_]+"
                                   title="Use only letters, numbers, hyphens and underscores">
                            <button type="button" class="btn btn-secondary" onclick="generateBarcodeForEdit()" style="margin-top: 5px;">
                                <i class="fas fa-sync-alt"></i> Generate Barcode
                            </button>
                        </div>
                        <div id="barcodePreview" class="barcode-preview" style="margin-top: 10px; text-align: center;"></div>
                        <small class="form-text text-muted">For multiple items, this will be the base barcode</small>
                    </div>
                </div>
                
                <div class="form-group" style="margin-top: 20px; padding-top: 10px; border-top: 2px solid #BBE0EF;">
                    <button type="submit" class="btn btn-primary" style="min-width: 150px;">
                        <i class="fas fa-save"></i> <?php echo isset($_GET['edit']) ? 'Update Item' : 'Save Item'; ?>
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="margin-left: 10px;">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- View Semi-Expendable Modal -->
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

<script>
// Print Physical Count Report for Semi-Expendable
function printPhysicalCountReport(itemId) {
    fetch('?ajax=get_item&id=' + itemId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let item = data.data;
                let currentDate = new Date().toLocaleDateString('en-PH', {
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
                
                let printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Physical Count Report - ${escapeHtml(item.article_name)}</title>
                        <style>
                            * { margin: 0; padding: 0; box-sizing: border-box; }
                            body { font-family: 'Arial', sans-serif; padding: 40px; background: white; color: #333; }
                            .report-container { max-width: 1200px; margin: 0 auto; background: white; }
                            .report-header { text-align: center; margin-bottom: 30px; padding-bottom: 20px; border-bottom: 3px solid #6B8CFF; }
                            .report-header h1 { font-size: 24px; color: #2c3e50; margin-bottom: 10px; text-transform: uppercase; }
                            .report-header h2 { font-size: 18px; color: #34495e; margin-bottom: 5px; }
                            .report-header p { font-size: 14px; color: #7f8c8d; margin-top: 10px; }
                            .report-date { text-align: right; margin-bottom: 20px; font-size: 12px; color: #7f8c8d; }
                            .report-table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 13px; }
                            .report-table th { background-color: #6B8CFF; color: white; padding: 12px; text-align: left; border: 1px solid #ddd; font-weight: 600; }
                            .report-table td { border: 1px solid #ddd; padding: 10px; vertical-align: top; }
                            .footer-section { margin-top: 50px; border-top: 1px solid #ddd; padding-top: 20px; }
                            .signature-area { display: flex; justify-content: space-between; margin-top: 40px; padding: 0 20px; }
                            .signature-box { text-align: center; width: 250px; }
                            .signature-line { margin-top: 50px; border-top: 1px solid #000; width: 100%; }
                            .signature-name { font-weight: bold; margin-top: 5px; }
                            .signature-title { font-size: 11px; color: #7f8c8d; margin-top: 5px; }
                            @media print { body { padding: 20px; } }
                        </style>
                    </head>
                    <body>
                        <div class="report-container">
                            <div class="report-header">
                                <h1>REPORT ON THE PHYSICAL COUNT</h1>
                                <h2>Semi-Expendable Property</h2>
                                <p>As of ${currentDate}</p>
                            </div>
                            <div class="report-date">Date Printed: ${currentDate}</div>
                            <table class="report-table">
                                <thead><tr><th>Fund Cluster</th><th>Article Name</th><th>Property Number</th><th>Big Unit</th><th>Small Unit</th><th>Unit Value</th><th>Quantity</th><th>Physical Count</th><th>Remark</th></tr></thead>
                                <tbody><tr><td>${escapeHtml(item.fund_cluster || 'N/A')}</div><div class="detail-value">${escapeHtml(item.article_name)}</div><div class="detail-value">${escapeHtml(item.property_no)}</div><div class="detail-value">${escapeHtml(item.big_unit ? item.big_quantity + ' ' + item.big_unit : 'N/A')}</div><div class="detail-value">${escapeHtml(item.pieces_per_big_unit ? item.pieces_per_big_unit + ' ' + item.small_unit : 'N/A')}</div><div class="detail-value">₱${parseFloat(item.unit_value).toFixed(2)}</div><div class="detail-value">${item.qty_physical_count}</div><div class="detail-value">${item.qty_physical_count}</div><div class="detail-value">${escapeHtml(item.remarks || 'No remarks')}</div></tr></tbody>
                            </table>
                            <div class="signature-area">
                                <div class="signature-box"><div class="signature-line"></div><div class="signature-name">${escapeHtml(item.certified_correct_names || '_________________')}</div><div class="signature-title">Certified Correct By</div></div>
                                <div class="signature-box"><div class="signature-line"></div><div class="signature-name">${escapeHtml(item.approved_by_names || '_________________')}</div><div class="signature-title">Approved By</div></div>
                                <div class="signature-box"><div class="signature-line"></div><div class="signature-name">${escapeHtml(item.verified_by_names || '_________________')}</div><div class="signature-title">Verified By</div></div>
                            </div>
                        </div>
                        <script>window.onload = function() { setTimeout(function() { window.print(); setTimeout(function() { window.close(); }, 500); }, 300); }<\/script>
                    </body>
                    </html>
                `);
                printWindow.document.close();
            } else {
                alert('Error loading item details for report');
            }
        })
        .catch(error => alert('An error occurred while generating the report'));
}

// Store equipment sub types data for JavaScript
var equipmentSubTypes = <?php echo json_encode($equipment_sub_type_options); ?>;

function loadEquipmentSubTypes() {
    var typeId = document.getElementById('type_equipment_id').value;
    var subTypeSelect = document.getElementById('equipment_sub_type_id');
    
    subTypeSelect.innerHTML = '<option value="">-- Select Equipment Category --</option>';
    
    if (!typeId) {
        subTypeSelect.innerHTML = '<option value="">-- First select Type of Equipment --</option>';
        return;
    }
    
    subTypeSelect.disabled = true;
    subTypeSelect.innerHTML = '<option value="">Loading categories...</option>';
    
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
                subTypeSelect.innerHTML = '<option value="">-- No categories found --</option>';
                subTypeSelect.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error loading sub types:', error);
            subTypeSelect.innerHTML = '<option value="">-- Error loading categories --</option>';
            subTypeSelect.disabled = false;
        });
}

function previewPropertyNumber() {
    var typeId = document.getElementById('type_equipment_id').value;
    var subTypeId = document.getElementById('equipment_sub_type_id').value;
    var quantity = parseFloat(document.getElementById('quantity').value) || 1;
    
    if (!typeId || !subTypeId) {
        document.getElementById('propertyPreview').innerHTML = '';
        return;
    }
    
    fetch('?ajax=get_property_preview&type_id=' + typeId + '&sub_type_id=' + subTypeId + '&quantity=' + quantity)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var html = '<div style="background: #e8f4f8; padding: 10px; border-radius: 5px; margin-top: 10px;">';
                html += '<i class="fas fa-qrcode"></i> <strong>Property Number Format:</strong><br>';
                html += '<code>' + data.property_format + '</code>';
                if (data.is_multiple && data.sequences) {
                    html += '<br><small>Will generate: ' + data.sequences.join(', ') + '</small>';
                }
                html += '</div>';
                document.getElementById('propertyPreview').innerHTML = html;
            }
        })
        .catch(error => document.getElementById('propertyPreview').innerHTML = '');
}

// Calculate compound total from Big Unit × Small Unit conversion
function calculateCompoundTotal() {
    let bigQty = parseFloat(document.getElementById('big_quantity').value) || 0;
    let piecesPerBigUnit = parseFloat(document.getElementById('pieces_per_big_unit').value) || 1;
    
    let totalPieces = bigQty * piecesPerBigUnit;
    
    let bigUnit = document.getElementById('big_unit').value;
    let smallUnit = document.getElementById('small_unit').value;
    let smallUnitName = smallUnit || 'pieces';
    
    document.getElementById('total_quantity_display').value = totalPieces.toLocaleString() + ' ' + smallUnitName;
    document.getElementById('quantity').value = totalPieces;
    
    document.getElementById('smallUnitNameLabel').textContent = smallUnitName.toLowerCase();
    document.getElementById('smallUnitNameText').textContent = smallUnitName.toLowerCase();
    
    let isMultiple = totalPieces > 1;
    let isEdit = document.getElementById('editId').value != '';
    let multipleOption = document.getElementById('multipleBarcodeOption');
    
    if (isMultiple && !isEdit) {
        multipleOption.style.display = 'block';
        document.getElementById('itemCountDisplay').textContent = totalPieces.toLocaleString();
        
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
    let totalPieces = parseInt(document.getElementById('quantity').value) || 0;
    
    if (isChecked && totalPieces > 0) {
        document.getElementById('generate_multiple_barcodes').value = '1';
        document.getElementById('barcodePreviewContainer').style.display = 'block';
        previewMultipleBarcodes();
    } else {
        document.getElementById('generate_multiple_barcodes').value = '0';
        document.getElementById('barcodePreviewContainer').style.display = 'none';
    }
}

function previewMultipleBarcodes() {
    let totalPieces = parseInt(document.getElementById('quantity').value) || 1;
    let bigUnit = document.getElementById('big_unit').value || 'ITEM';
    let smallUnit = document.getElementById('small_unit').value || 'PC';
    let prefix = bigUnit.substring(0, 2).toUpperCase() + '-' + smallUnit.substring(0, 2).toUpperCase();
    
    let previewDiv = document.getElementById('multipleBarcodePreview');
    previewDiv.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Generating preview...</div>';
    
    fetch('?generate_multiple_preview=1&prefix=' + encodeURIComponent(prefix) + '&quantity=' + totalPieces)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<p style="margin-bottom: 10px; font-weight: bold;">Barcodes to be generated for ' + totalPieces.toLocaleString() + ' items:</p>';
                html += '<div style="max-height: 300px; overflow-y: auto;">';
                
                if (data.barcodes && data.barcodes.length > 0) {
                    data.barcodes.forEach(barcode => {
                        html += `<div style="display: flex; gap: 10px; padding: 8px; border-bottom: 1px solid #eee;">
                            <span style="min-width: 50px; font-weight: bold; color: #6B8CFF;">#${barcode.index}</span>
                            <span style="font-family: monospace;">${escapeHtml(barcode.value)}</span>
                        </div>`;
                    });
                    if (data.total > data.barcodes.length) {
                        html += `<p class="text-muted" style="margin-top: 10px;">... and ${(data.total - data.barcodes.length).toLocaleString()} more items</p>`;
                    }
                }
                html += '</div>';
                previewDiv.innerHTML = html;
            } else {
                previewDiv.innerHTML = '<div class="alert alert-danger">Error generating preview</div>';
            }
        })
        .catch(error => previewDiv.innerHTML = '<div class="alert alert-danger">Network error</div>');
}

function calculateTotal() {
    let totalPieces = parseFloat(document.getElementById('quantity').value) || 0;
    let unitValue = parseFloat(document.getElementById('unit_value').value) || 0;
    let total = totalPieces * unitValue;
    document.getElementById('total_value').value = '₱' + total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New Semi-Expendable Item';
    document.getElementById('semiForm').reset();
    document.querySelector('#semiForm input[name="action"]').value = 'add';
    document.getElementById('semiModal').style.display = 'block';
    document.getElementById('barcodePreview').innerHTML = '';
    document.getElementById('multipleBarcodeOption').style.display = 'none';
    document.getElementById('barcodePreviewContainer').style.display = 'none';
    document.getElementById('generate_multiple_barcodes').value = '0';
    document.getElementById('propertyPreview').innerHTML = '';
    
    document.getElementById('big_quantity').value = 1;
    document.getElementById('pieces_per_big_unit').value = 1;
    document.getElementById('total_quantity_display').value = '';
    document.getElementById('quantity').value = 0;
    
    var typeSelect = document.getElementById('type_equipment_id');
    if (typeSelect) typeSelect.value = '';
    var subTypeSelect = document.getElementById('equipment_sub_type_id');
    if (subTypeSelect) subTypeSelect.innerHTML = '<option value="">-- First select Type of Equipment --</option>';
    
    calculateCompoundTotal();
}

function closeModal() {
    document.getElementById('semiModal').style.display = 'none';
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

function closeAllBarcodesModal() {
    document.getElementById('viewAllBarcodesModal').style.display = 'none';
}

function generateBarcodeForEdit() {
    let bigUnit = document.getElementById('big_unit').value || 'ITEM';
    let smallUnit = document.getElementById('small_unit').value || 'PC';
    let prefix = bigUnit.substring(0, 2).toUpperCase() + '-' + smallUnit.substring(0, 2).toUpperCase();
    let date = new Date();
    let dateStr = date.getFullYear() + String(date.getMonth() + 1).padStart(2, '0') + String(date.getDate()).padStart(2, '0');
    let random = Math.floor(1000 + Math.random() * 9000);
    let barcodeValue = prefix + '-' + dateStr + '-' + random;
    document.getElementById('barcode_data').value = barcodeValue;
    
    let previewDiv = document.getElementById('barcodePreview');
    previewDiv.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Generating...</div>';
    
    fetch('generate_barcode.php?code=' + encodeURIComponent(barcodeValue) + '&format=png')
    .then(response => response.blob())
    .then(blob => {
        let url = URL.createObjectURL(blob);
        previewDiv.innerHTML = '<img src="' + url + '" alt="Barcode" style="max-width: 200px; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">';
    })
    .catch(error => previewDiv.innerHTML = '');
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
                let statusBadge = item.is_issued > 0 ? '<span class="badge-warning">Issued</span>' : '<span class="badge-success">Available</span>';
                
                let bigUnitDisplay = (item.big_quantity && item.big_unit) ? item.big_quantity + ' ' + item.big_unit : 'N/A';
                let smallUnitDisplay = (item.pieces_per_big_unit && item.small_unit) ? item.pieces_per_big_unit + ' ' + item.small_unit : 'N/A';
                
                let html = `
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-info-circle"></i> Basic Information</div><div class="detail-content"><strong>Article Name:</strong> ${escapeHtml(item.article_name)}<br><strong>Property No:</strong> ${escapeHtml(item.property_no)}<br><strong>Description:</strong> ${escapeHtml(item.description || 'N/A')}<br><strong>Status:</strong> ${statusBadge}</div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-tags"></i> Classification</div><div class="detail-content"><strong>Type:</strong> ${escapeHtml(item.type_equipment_name || 'N/A')}<br><strong>Category:</strong> ${escapeHtml(item.sub_type_name || 'N/A')}<br><strong>Condition:</strong> ${escapeHtml(item.condition_text)}</div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-truck"></i> Supplier</div><div class="detail-content"><strong>Supplier:</strong> ${escapeHtml(item.supplier_name || 'N/A')}<br><strong>PO Number:</strong> ${escapeHtml(item.ref_po_number || 'N/A')}<br><strong>Delivery Date:</strong> ${item.delivery_date || 'N/A'}</div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-calculator"></i> Quantity & Value</div><div class="detail-content"><strong>Big Unit:</strong> ${escapeHtml(bigUnitDisplay)}<br><strong>Small Unit:</strong> ${escapeHtml(smallUnitDisplay)}<br><strong>Total Quantity:</strong> ${item.qty_physical_count}<br><strong>Unit Value:</strong> ₱${parseFloat(item.unit_value).toFixed(2)}<br><strong>Total Value:</strong> ₱${(item.qty_physical_count * item.unit_value).toFixed(2)}<br><strong>Fund Cluster:</strong> ${escapeHtml(item.fund_cluster || 'N/A')}</div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-users"></i> Personnel</div><div class="detail-content"><strong>Certified By:</strong> ${escapeHtml(item.certified_correct_names || 'N/A')}<br><strong>Approved By:</strong> ${escapeHtml(item.approved_by_names || 'N/A')}<br><strong>Verified By:</strong> ${escapeHtml(item.verified_by_names || 'N/A')}</div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-calendar"></i> Dates</div><div class="detail-content"><strong>Added:</strong> ${new Date(item.date_added).toLocaleString()}<br><strong>Updated:</strong> ${item.date_updated ? new Date(item.date_updated).toLocaleString() : 'Never'}</div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-comment"></i> Remarks</div><div class="detail-content">${escapeHtml(item.remarks || 'No remarks')}</div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-print"></i> Report</div><div class="detail-content"><button class="btn btn-primary" onclick="printPhysicalCountReport(${item.id})"><i class="fas fa-print"></i> Print Report</button></div></div>
                `;
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error loading item</div>';
            }
        })
        .catch(error => content.innerHTML = '<div class="alert alert-danger">Error loading item</div>');
}

function viewAllBarcodes(propertyNo, itemName) {
    document.getElementById('allBarcodesModalTitle').textContent = 'All Barcodes - ' + escapeHtml(itemName);
    document.getElementById('allBarcodesContent').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Loading...</div>';
    document.getElementById('viewAllBarcodesModal').style.display = 'block';
    
    fetch('?get_multiple_items=1&property_no=' + encodeURIComponent(propertyNo))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.items.length > 0) {
                let html = `<p><strong>Found ${data.count} items:</strong></p><div class="all-barcodes-grid">`;
                data.items.forEach((item, index) => {
                    html += `
                        <div class="barcode-item-card">
                            <div class="item-property">Item ${index + 1}: ${escapeHtml(item.property_no)}</div>
                            <div class="barcode-image"><img src="generate_barcode.php?code=${encodeURIComponent(item.barcode_data)}&width=250&height=60" alt="Barcode"></div>
                            <div class="barcode-value">${escapeHtml(item.barcode_data)}</div>
                            <div><button class="btn-xs" onclick="showBarcodeModal('${item.barcode_data}', '${escapeHtml(item.article_name)}')">View</button> <button class="btn-xs" onclick="printBarcode('${item.barcode_data}', '${escapeHtml(item.article_name)}')">Print</button></div>
                        </div>
                    `;
                });
                html += '</div>';
                document.getElementById('allBarcodesContent').innerHTML = html;
            } else {
                document.getElementById('allBarcodesContent').innerHTML = '<div class="alert alert-warning">No related items found</div>';
            }
        })
        .catch(error => document.getElementById('allBarcodesContent').innerHTML = '<div class="alert alert-danger">Error loading barcodes</div>');
}

function printAllBarcodes() {
    let content = document.getElementById('allBarcodesContent').innerHTML;
    let title = document.getElementById('allBarcodesModalTitle').textContent;
    let printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <html><head><title>${escapeHtml(title)}</title>
        <style>body{font-family:Arial;padding:20px}.barcodes-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px}.barcode-item-card{border:2px solid #6B8CFF;padding:15px;text-align:center;border-radius:8px}</style>
        </head><body><div class="barcodes-grid">${content.replace(/<button.*?<\/button>/g, '')}</div>
        <script>window.onload=function(){window.print();setTimeout(function(){window.close()},500)}<\/script></body></html>
    `);
    printWindow.document.close();
}

function showBarcodeModal(barcodeData, itemName) {
    document.getElementById('barcodeModalTitle').textContent = 'Barcode - ' + escapeHtml(itemName);
    document.getElementById('barcodeModalImage').innerHTML = '<img src="generate_barcode.php?code=' + encodeURIComponent(barcodeData) + '&format=png&width=300&height=80" alt="Barcode" style="max-width:100%;border:1px solid #ddd;padding:10px;border-radius:5px;">';
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
    printWindow.document.write(`
        <html><head><title>Print Barcode</title>
        <style>body{text-align:center;padding:20px}.barcode-container{padding:30px;border:1px dashed #6B8CFF;border-radius:10px}</style>
        </head><body><div class="barcode-container"><div class="semi-label">Semi-Expendable</div>
        <img src="generate_barcode.php?code=${encodeURIComponent(barcodeData)}&width=400&height=100" alt="Barcode">
        <div class="item-name">${escapeHtml(itemName)}</div>
        <div class="barcode-number">${escapeHtml(barcodeData)}</div></div>
        <script>window.onload=function(){setTimeout(function(){window.print();window.close()},500)}<\/script></body></html>
    `);
    printWindow.document.close();
}

function escapeHtml(text) {
    if (!text) return '';
    let div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

window.onclick = function(event) {
    if (event.target == document.getElementById('semiModal')) closeModal();
    if (event.target == document.getElementById('viewModal')) closeViewModal();
    if (event.target == document.getElementById('barcodeModal')) closeBarcodeModal();
    if (event.target == document.getElementById('viewAllBarcodesModal')) closeAllBarcodesModal();
}

window.addEventListener('load', function() {
    <?php if (isset($_GET['edit'])): ?>
    let editId = <?php echo (int)$_GET['edit']; ?>;
    fetch('?ajax=get_edit_item&id=' + editId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let item = data.data;
                document.getElementById('article_name').value = item.article_name;
                document.getElementById('description').value = item.description || '';
                
                if (item.type_equipment_id) {
                    document.getElementById('type_equipment_id').value = item.type_equipment_id;
                    loadEquipmentSubTypes();
                    setTimeout(() => {
                        document.getElementById('equipment_sub_type_id').value = item.equipment_sub_type_id;
                    }, 500);
                }
                
                document.getElementById('big_unit').value = item.big_unit || '';
                document.getElementById('big_quantity').value = item.big_quantity || 1;
                document.getElementById('small_unit').value = item.small_unit || '';
                document.getElementById('pieces_per_big_unit').value = item.pieces_per_big_unit || 1;
                document.getElementById('unit_value').value = item.unit_value;
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




