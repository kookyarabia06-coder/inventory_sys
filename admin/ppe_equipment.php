<?php
// Add output buffering at the very top to prevent header warnings
ob_start();

/**
 * PPE Equipment Page (Admin)
 * Complete PPE management system with all inventory fields
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
requireRole('admin');

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'PPE Equipment';
$page_description = 'Manage Personal Protective Equipment';

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

// AJAX endpoint to preview property number format
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_property_preview' && isset($_GET['type_id']) && isset($_GET['sub_type_id'])) {
    header('Content-Type: application/json');
    $type_id = (int)$_GET['type_id'];
    $sub_type_id = (int)$_GET['sub_type_id'];
    $quantity = (int)($_GET['quantity'] ?? 1);
    
    // Get type code
    $type_query = $conn->prepare("SELECT code FROM type_of_equipment WHERE id = ?");
    $type_query->bind_param("i", $type_id);
    $type_query->execute();
    $type_result = $type_query->get_result();
    $type_code = $type_result->fetch_assoc()['code'] ?? '00';
    $type_query->close();
    
    // Get sub type code
    $subtype_query = $conn->prepare("SELECT code FROM equipment_sub_type WHERE id = ?");
    $subtype_query->bind_param("i", $sub_type_id);
    $subtype_query->execute();
    $subtype_result = $subtype_query->get_result();
    $subtype_code = $subtype_result->fetch_assoc()['code'] ?? '00';
    $subtype_query->close();
    
    $year = date('Y');
    $pattern_like = $year . '-' . $type_code . '-' . $subtype_code . '-%';
    
    // Get next sequence number
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
    $base_format = $year . '-' . $type_code . '-' . $subtype_code;
    
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
               CONCAT(ap.firstname, ' ', ap.lastname) as approver_name,
               CONCAT(vr.firstname, ' ', vr.lastname) as verifier_name,
               CONCAT(cr.firstname, ' ', cr.lastname) as created_by_name,
               (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') as is_issued,
               CASE WHEN i.property_no LIKE '%-%' THEN 1 ELSE 0 END as is_multiple
        FROM inventory i
        LEFT JOIN equipment e ON i.equipment_id = e.id
        LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
        LEFT JOIN equipment_sub_type est ON i.equipment_sub_type_id = est.id
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

// ============================================
// FUNCTION TO GENERATE PROPERTY NUMBER
// ============================================

function generatePropertyNumber($conn, $type_equipment_id, $equipment_sub_type_id, $sequence_number = null) {
    // Get equipment type code
    $type_query = $conn->prepare("SELECT code FROM type_of_equipment WHERE id = ?");
    $type_query->bind_param("i", $type_equipment_id);
    $type_query->execute();
    $type_result = $type_query->get_result();
    $type_code = $type_result->fetch_assoc()['code'] ?? '00';
    $type_query->close();
    
    // Get equipment sub type code
    $subtype_query = $conn->prepare("SELECT code FROM equipment_sub_type WHERE id = ?");
    $subtype_query->bind_param("i", $equipment_sub_type_id);
    $subtype_query->execute();
    $subtype_result = $subtype_query->get_result();
    $subtype_code = $subtype_result->fetch_assoc()['code'] ?? '00';
    $subtype_query->close();
    
    // Get current year
    $year = date('Y');
    
    // Build base property number pattern
    $base_pattern = $year . '-' . $type_code . '-' . $subtype_code;
    
    // If sequence number is provided (for multiple items), use it directly
    if ($sequence_number !== null) {
        return $base_pattern . '-' . str_pad($sequence_number, 4, '0', STR_PAD_LEFT);
    }
    
    // For single item, get the next sequence number
    $pattern_like = $year . '-' . $type_code . '-' . $subtype_code . '-%';
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
    $uom = sanitize($_POST['uom'] ?? '');
    $quantity = floatval($_POST['quantity'] ?? 0);
    $unit_value = floatval($_POST['unit_value'] ?? 0);
    $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
    $category = 'PPE';
    
    // Get type_equipment_id and equipment_sub_type_id
    $type_equipment_id = !empty($_POST['type_equipment_id']) ? (int)$_POST['type_equipment_id'] : null;
    $equipment_sub_type_id = !empty($_POST['equipment_sub_type_id']) ? (int)$_POST['equipment_sub_type_id'] : null;
    
    // Fund cluster
    $fund_cluster = sanitize($_POST['fund_cluster'] ?? '');
    
    // Supplier Information
    $supplier = sanitize($_POST['supplier'] ?? '');
    $ref_po_number = sanitize($_POST['ref_po_number'] ?? '');
    $delivery_date = !empty($_POST['delivery_date']) ? sanitize($_POST['delivery_date']) : null;
    
    // Set year_acquired
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
    if (empty($uom)) $errors[] = "Unit of measurement is required";
    if ($quantity <= 0) $errors[] = "Quantity must be greater than 0";
    if ($unit_value <= 0) $errors[] = "Unit value must be greater than 0";
    if (empty($type_equipment_id)) $errors[] = "Type of Equipment is required";
    if (empty($equipment_sub_type_id)) $errors[] = "Equipment Category is required";
    
    if (empty($errors)) {
        $conn->begin_transaction();
        $success_count = 0;
        
        try {
            if ($generate_multiple && $quantity > 1 && floor($quantity) == $quantity) {
                // For multiple items, generate sequential property numbers
                $type_query = $conn->prepare("SELECT code FROM type_of_equipment WHERE id = ?");
                $type_query->bind_param("i", $type_equipment_id);
                $type_query->execute();
                $type_result = $type_query->get_result();
                $type_code = $type_result->fetch_assoc()['code'] ?? '00';
                $type_query->close();
                
                $subtype_query = $conn->prepare("SELECT code FROM equipment_sub_type WHERE id = ?");
                $subtype_query->bind_param("i", $equipment_sub_type_id);
                $subtype_query->execute();
                $subtype_result = $subtype_query->get_result();
                $subtype_code = $subtype_result->fetch_assoc()['code'] ?? '00';
                $subtype_query->close();
                
                $year = date('Y');
                $pattern_like = $year . '-' . $type_code . '-' . $subtype_code . '-%';
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
                
                $base_barcode = $barcode_data ?: $type_code . '-' . $subtype_code . '-' . date('Ymd');
                
                for ($i = 1; $i <= $quantity; $i++) {
                    $property_no = $year . '-' . $type_code . '-' . $subtype_code . '-' . str_pad($start_seq + $i - 1, 4, '0', STR_PAD_LEFT);
                    $sequential_barcode = $base_barcode . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
                    
                    $stmt = $conn->prepare("
                        INSERT INTO inventory (
                            article_name, description, property_no, uom, 
                            qty_property_card, qty_physical_count, unit_value,
                            equipment_id, category, type_equipment_id, equipment_sub_type_id, condition_text,
                            fund_cluster, certified_correct, approved_by, verified_by,
                            supplier, ref_po_number, delivery_date,
                            remarks, barcode_data, created_by, year_acquired
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $qty_property_card = $quantity;
                    $qty_physical_count = $quantity;
                    
                    $stmt->bind_param(
                        "ssssdddiisiiissssssssii",
                        $article_name, $description, $property_no, $uom,
                        $qty_property_card, $qty_physical_count, $unit_value,
                        $equipment_id, $category, $type_equipment_id, $equipment_sub_type_id, $condition_text,
                        $fund_cluster, $certified_correct, $approved_by, $verified_by,
                        $supplier, $ref_po_number, $delivery_date,
                        $remarks, $sequential_barcode, $created_by, $year_acquired
                    );
                    
                    if ($stmt->execute()) {
                        $success_count++;
                    }
                    $stmt->close();
                }
                $conn->commit();
                $_SESSION['success'] = "$success_count PPE items added successfully. Property numbers: $year-$type_code-$subtype_code-XXXX";
            } else {
                // For single item
                $property_no = generatePropertyNumber($conn, $type_equipment_id, $equipment_sub_type_id);
                
                $stmt = $conn->prepare("
                    INSERT INTO inventory (
                        article_name, description, property_no, uom, 
                        qty_property_card, qty_physical_count, unit_value,
                        equipment_id, category, type_equipment_id, equipment_sub_type_id, condition_text,
                        fund_cluster, certified_correct, approved_by, verified_by,
                        supplier, ref_po_number, delivery_date,
                        remarks, barcode_data, created_by, year_acquired
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $qty_property_card = $quantity;
                $qty_physical_count = $quantity;
                
                $stmt->bind_param(
                    "ssssdddiisiiissssssssii",
                    $article_name, $description, $property_no, $uom,
                    $qty_property_card, $qty_physical_count, $unit_value,
                    $equipment_id, $category, $type_equipment_id, $equipment_sub_type_id, $condition_text,
                    $fund_cluster, $certified_correct, $approved_by, $verified_by,
                    $supplier, $ref_po_number, $delivery_date,
                    $remarks, $barcode_data, $created_by, $year_acquired
                );
                
                if ($stmt->execute()) {
                    $inventory_id = $stmt->insert_id;
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
    $uom = sanitize($_POST['uom'] ?? '');
    $quantity = floatval($_POST['quantity'] ?? 0);
    $unit_value = floatval($_POST['unit_value'] ?? 0);
    $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
    
    // Get type_equipment_id and equipment_sub_type_id
    $type_equipment_id = !empty($_POST['type_equipment_id']) ? (int)$_POST['type_equipment_id'] : null;
    $equipment_sub_type_id = !empty($_POST['equipment_sub_type_id']) ? (int)$_POST['equipment_sub_type_id'] : null;
    
    // Fund cluster
    $fund_cluster = sanitize($_POST['fund_cluster'] ?? '');
    
    // Supplier Information
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
    
    $stmt = $conn->prepare("
        UPDATE inventory SET 
            article_name = ?,
            description = ?,
            uom = ?,
            qty_physical_count = ?,
            unit_value = ?,
            equipment_id = ?,
            type_equipment_id = ?,
            equipment_sub_type_id = ?,
            condition_text = ?,
            fund_cluster = ?,
            certified_correct = ?,
            approved_by = ?,
            verified_by = ?,
            supplier = ?,
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
        "sssddi i i i s s s s s s s s s i",
        $article_name, $description, $uom, $quantity, $unit_value,
        $equipment_id, $type_equipment_id, $equipment_sub_type_id, $condition_text,
        $fund_cluster, $certified_correct, $approved_by, $verified_by,
        $supplier, $ref_po_number, $delivery_date,
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
    $prefix = $_GET['prefix'] ?? 'PPE';
    $quantity = min(max(intval($_GET['quantity'] ?? 1), 1), 100);
    $baseBarcode = $prefix . '-' . date('Ymd');
    $generator = new BarcodeGeneratorPNG();
    $barcodes = [];
    $preview_count = min($quantity, 10);
    for ($i = 1; $i <= $preview_count; $i++) {
        $barcodeValue = $baseBarcode . '-' . str_pad($i, 3, '0', STR_PAD_LEFT);
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
           CONCAT(ap.firstname, ' ', ap.lastname) as approver_name,
           CONCAT(vr.firstname, ' ', vr.lastname) as verifier_name,
           CONCAT(cr.firstname, ' ', cr.lastname) as created_by_name,
           (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') as is_issued,
           CASE WHEN i.property_no LIKE '%-%' THEN 1 ELSE 0 END as is_multiple
    FROM inventory i
    LEFT JOIN equipment e ON i.equipment_id = e.id
    LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
    LEFT JOIN equipment_sub_type est ON i.equipment_sub_type_id = est.id
    LEFT JOIN users ap ON i.approved_by = ap.id
    LEFT JOIN users vr ON i.verified_by = vr.id
    LEFT JOIN users cr ON i.created_by = cr.id
    WHERE 1=1
";

$count_query = "SELECT COUNT(*) as total FROM inventory i WHERE 1=1";

if ($search) {
    $query .= " AND (i.article_name LIKE ? OR i.property_no LIKE ? OR i.description LIKE ?)";
    $count_query .= " AND (article_name LIKE ? OR property_no LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params = [$search_term, $search_term, $search_term];
    $types = "sss";
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

$ppe_items = [];
while ($row = $result->fetch_assoc()) {
    $ppe_items[] = $row;
}
$stmt->close();

$counts = [];
foreach ($ppe_items as $r) {
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
foreach ($ppe_items as &$r) {
    $base = preg_replace('/-\d+$/', '', $r['property_no']);
    $r['total_qty'] = $counts[$base] ?? $r['qty_physical_count'];
}
unset($r);

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

tr.stock-alert-row {
    background-color: white;
}

tr.stock-alert-row:hover {
    background-color: #f0c0d0;
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
}

.form-row .form-group {
    flex: 1;
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

/* Loading spinner for dropdown */
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

<!-- Statistics Cards for PPE -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon"><i class="fas fa-shield-alt"></i></div>
        <h3>Total PPE Items</h3>
        <?php
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM inventory i
            LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
            WHERE i.category = 'PPE' OR toe.name LIKE '%PPE%'
        ");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_ppe = $result->fetch_assoc()['count'];
        $stmt->close();
        ?>
        <div class="card-value"><?php echo $total_ppe; ?></div>
        <div class="card-label">All PPE equipment</div>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-hand-holding"></i></div>
        <h3>Issued PPE</h3>
        <?php
        $stmt = $conn->prepare("
            SELECT COUNT(DISTINCT ei.inventory_id) as count 
            FROM equipment_issuance ei
            JOIN inventory i ON ei.inventory_id = i.id
            LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
            WHERE (i.category = 'PPE' OR toe.name LIKE '%PPE%') AND ei.status = 'issued'
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
        <h3>Low Stock PPE</h3>
        <?php
        $stmt = $conn->prepare("
            SELECT COUNT(*) as count 
            FROM inventory i
            LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
            WHERE (i.category = 'PPE' OR toe.name LIKE '%PPE%') AND i.qty_physical_count <= 5
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
        <div class="card-icon"><i class="fas fa-dollar-sign"></i></div>
        <h3>Total Value</h3>
        <?php
        $stmt = $conn->prepare("
            SELECT SUM(i.unit_value * i.qty_physical_count) as total 
            FROM inventory i
            LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
            WHERE i.category = 'PPE' OR toe.name LIKE '%PPE%'
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
        <?php
            $uniqueCount = 0;
            $seen = [];
            foreach ($ppe_items as $it) {
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
                <th>Supplier</th>
                <th>PO Number</th>
                <th>Fund Cluster</th>
                <th>Condition</th>
                <th>Status</th>
                <th>Barcode</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($ppe_items) > 0): ?>
                <?php
                $shown = [];
                foreach ($ppe_items as $item):
                    $base = preg_replace('/-\d+$/', '', $item['property_no']);
                    if (isset($shown[$base])) continue;
                    $shown[$base] = true;
                ?>
                <tr class="<?php echo $item['qty_physical_count'] <= 5 ? 'stock-alert-row' : ''; ?>">
                    <td>
                        <strong><?php echo htmlspecialchars($item['article_name']); ?></strong>
                        <?php if ($item['description']): ?>
                        <br><small><?php echo htmlspecialchars(substr($item['description'], 0, 50) . (strlen($item['description']) > 50 ? '...' : '')); ?></small>
                        <?php endif; ?>
                    </div>
                    </td>
                    <td><?php echo htmlspecialchars($item['property_no']); ?></div>
                    <td>
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
                    </div>
                    <td><?php echo $item['total_qty'] . ' ' . $item['uom']; ?></div>
                    <td><?php echo formatCurrency($item['unit_value']); ?></div>
                    <td><?php echo htmlspecialchars($item['supplier'] ?? 'N/A'); ?></div>
                    <td><?php echo htmlspecialchars($item['ref_po_number'] ?? 'N/A'); ?></div>
                    <td><?php echo htmlspecialchars($item['fund_cluster'] ?? 'N/A'); ?></div>
                    <td><?php echo htmlspecialchars($item['condition_text'] ?? 'Good'); ?></div>
                    <td>
                        <?php if ($item['is_issued'] > 0): ?>
                            <span class="badge-warning">Issued</span>
                        <?php else: ?>
                            <span class="badge-success">Available</span>
                        <?php endif; ?>
                    </div>
                    <td>
                        <?php if (!empty($item['barcode_data'])): ?>
                            <button class="btn-xs" onclick="showBarcodeModal('<?php echo htmlspecialchars($item['barcode_data']); ?>', '<?php echo htmlspecialchars($item['article_name']); ?>')">
                                <i class="fas fa-barcode"></i> View
                            </button>
                        <?php else: ?>
                            <span class="text-muted">No barcode</span>
                        <?php endif; ?>
                        <?php if ($item['is_multiple']):
                            $baseProperty = preg_replace('/-\d+$/', '', $item['property_no']);
                            $multipleCount = isset($counts[$baseProperty]) ? $counts[$baseProperty] : 1;
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
                    </div>
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
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="12" class="text-center">
                        <i class="fas fa-shield-alt" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                        <br>
                        No PPE items found
                        <br>
                        <button class="btn btn-primary mt-3" onclick="openAddModal()">
                            <i class="fas fa-plus"></i> Add Your First PPE Item
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php echo displayPagination($pagination_data, '?page=' . ($search ? '&search=' . urlencode($search) : '')); ?>
</div>

<!-- Sticky SCAN BARCODE Button -->
<div class="sticky-scan-button-container">
    <a href="<?php echo SITE_URL; ?>/admin/barcodescannerforppe.php" class="sticky-scan-button">
        <i class="fas fa-camera"></i> SCAN BARCODE
    </a>
</div>

<!-- Add/Edit PPE Modal -->
<div id="ppeModal" class="modal" style="display: <?php echo isset($_GET['edit']) ? 'block' : 'none'; ?>;">
    <div class="modal-content" style="max-width: 900px; max-height: 90vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="flex-shrink: 0;">
            <h2 id="modalTitle"><?php echo isset($_GET['edit']) ? 'Edit PPE Item' : 'Add New PPE Item'; ?></h2>
            <span class="modal-close" onclick="closeModal()">&times;</span>
        </div>
        
        <div class="modal-body" style="overflow-y: auto; flex: 1; padding: 20px;">
            <form method="POST" action="" id="ppeForm">
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
                            <label for="supplier">Supplier</label>
                            <input type="text" class="form-control" id="supplier" name="supplier" placeholder="Enter supplier name">
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
                
                <!-- Quantity and Value -->
                <div class="form-section">
                    <h3><i class="fas fa-calculator"></i> Quantity and Value</h3>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="uom">Unit of Measurement <span class="text-danger">*</span></label>
                            <select class="form-control" id="uom" name="uom" required>
                                <option value="">-- Select UOM --</option>
                                <option value="pcs">Pieces (pcs)</option>
                                <option value="box">Box</option>
                                <option value="unit">Unit</option>
                                <option value="set">Set</option>
                                <option value="pair">Pair</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="0.01" step="0.01" required onchange="checkQuantityForMultipleBarcodes(); previewPropertyNumber();">
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
                                When checked, each item will have its own unique barcode (e.g., PPE-YYYYMMDD-001, PPE-YYYYMMDD-002, etc.)
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
                            <input type="number" class="form-control" id="unit_value" name="unit_value" min="0.01" step="0.01" required placeholder="0.00">
                        </div>
                        <div class="form-group">
                            <label for="total_value">Total Value</label>
                            <input type="text" class="form-control" id="total_value" readonly placeholder="₱0.00">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="fund_cluster">Fund Cluster</label>
                        <select class="form-control" id="fund_cluster" name="fund_cluster">
                            <option value="">-- Select Fund Cluster --</option>
                            <option value="Regular Agency Funds (RAF)">Regular Agency Funds (RAF)</option>
                            <option value="Internally Generated Funds (IGF)">Internally Generated Funds (IGF)</option>
                            <option value="FUND 151">FUND 151</option>
                            <option value="Trust Receipts">Trust Receipts</option>
                        </select>
                        <small class="form-text text-muted">Select the appropriate fund cluster for this item</small>
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
                            <select class="form-control" id="certified_correct" name="certified_correct[]" multiple>
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
                            <select class="form-control" id="approved_by" name="approved_by[]" multiple>
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
                            <select class="form-control" id="verified_by" name="verified_by[]" multiple>
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
                        <small class="form-text text-muted">For multiple items, this will be the base barcode (e.g., PPE-20260310 will become PPE-20260310-001, PPE-20260310-002, etc.)</small>
                    </div>
                </div>
                
                <!-- Date Information (Display Only) -->
                <div class="form-section">
                    <h3><i class="fas fa-calendar-alt"></i> Date Information</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Date Added</label>
                            <input type="text" class="form-control" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly disabled>
                        </div>
                        <div class="form-group">
                            <label>Date Updated</label>
                            <input type="text" class="form-control" value="<?php echo date('Y-m-d H:i:s'); ?>" readonly disabled>
                        </div>
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

<!-- View PPE Modal -->
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
// Store equipment sub types data for JavaScript
var equipmentSubTypes = <?php echo json_encode($equipment_sub_type_options); ?>;

// Function to load equipment sub types based on selected type
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
    
    var timestamp = new Date().getTime();
    var url = '?ajax=get_sub_types&type_id=' + encodeURIComponent(typeId) + '&t=' + timestamp;
    
    fetch(url)
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
                subTypeSelect.innerHTML = '<option value="">-- No categories found for this type --</option>';
                subTypeSelect.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error loading sub types:', error);
            subTypeSelect.innerHTML = '<option value="">-- Error loading categories --</option>';
            subTypeSelect.disabled = false;
        });
}

// Function to preview property number format
function previewPropertyNumber() {
    var typeId = document.getElementById('type_equipment_id').value;
    var subTypeId = document.getElementById('equipment_sub_type_id').value;
    var quantity = parseFloat(document.getElementById('quantity').value) || 1;
    
    if (!typeId || !subTypeId) {
        document.getElementById('propertyPreview').innerHTML = '';
        return;
    }
    
    document.getElementById('propertyPreview').innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading property number format...</div>';
    
    var timestamp = new Date().getTime();
    var url = '?ajax=get_property_preview&type_id=' + typeId + '&sub_type_id=' + subTypeId + '&quantity=' + quantity + '&t=' + timestamp;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                var html = '<div style="background: #e8f4f8; padding: 10px; border-radius: 5px; margin-top: 10px;">';
                html += '<i class="fas fa-qrcode" style="color: #6B8CFF; margin-right: 5px;"></i>';
                html += '<strong>Property Number Format:</strong><br>';
                html += '<code style="font-size: 14px; background: #fff; padding: 5px; border-radius: 3px;">' + data.property_format + '</code>';
                if (data.is_multiple && data.sequences) {
                    html += '<br><small class="text-muted">Will generate: ' + data.sequences.join(', ') + '</small>';
                }
                html += '</div>';
                document.getElementById('propertyPreview').innerHTML = html;
            } else {
                document.getElementById('propertyPreview').innerHTML = '<div class="alert alert-danger">Error loading property format</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('propertyPreview').innerHTML = '';
        });
}

// Calculate total value
document.getElementById('quantity')?.addEventListener('input', calculateTotal);
document.getElementById('unit_value')?.addEventListener('input', calculateTotal);

function calculateTotal() {
    let quantity = parseFloat(document.getElementById('quantity').value) || 0;
    let unitValue = parseFloat(document.getElementById('unit_value').value) || 0;
    let total = quantity * unitValue;
    document.getElementById('total_value').value = '₱' + total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Check quantity for multiple barcodes option
function checkQuantityForMultipleBarcodes() {
    let quantity = parseFloat(document.getElementById('quantity').value);
    let multipleOption = document.getElementById('multipleBarcodeOption');
    let isInteger = Number.isInteger(quantity) && quantity > 1;
    let isEdit = document.getElementById('editId').value != '';
    
    if (isInteger && !isEdit) {
        multipleOption.style.display = 'block';
        document.getElementById('itemCountDisplay').textContent = quantity;
        previewPropertyNumber();
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
    
    let typeSelect = document.getElementById('type_equipment_id');
    let subTypeSelect = document.getElementById('equipment_sub_type_id');
    let typeCode = typeSelect.options[typeSelect.selectedIndex]?.text.split(' - ')[0] || 'PPE';
    let subTypeCode = subTypeSelect.options[subTypeSelect.selectedIndex]?.text.split(' - ')[0] || '00';
    let prefix = typeCode + '-' + subTypeCode;
    
    let previewDiv = document.getElementById('multipleBarcodePreview');
    
    previewDiv.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Generating preview...</div>';
    
    let timestamp = new Date().getTime();
    let url = '?generate_multiple_preview=1&prefix=' + encodeURIComponent(prefix) + '&quantity=' + quantity + '&t=' + timestamp;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
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
                previewDiv.innerHTML = `<div class="alert alert-danger">Error generating preview: ${escapeHtml(data.error || 'Unknown error')}</div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            previewDiv.innerHTML = `<div class="alert alert-danger">Network error: ${escapeHtml(error.message)}</div>`;
        });
}

// Open Add Modal
function openAddModal() {
    document.getElementById('modalTitle').textContent = 'Add New PPE Item';
    document.getElementById('ppeForm').reset();
    document.querySelector('#ppeForm input[name="action"]').value = 'add';
    document.getElementById('ppeModal').style.display = 'block';
    document.getElementById('barcodePreview').innerHTML = '';
    document.getElementById('multipleBarcodeOption').style.display = 'none';
    document.getElementById('barcodePreviewContainer').style.display = 'none';
    document.getElementById('generate_multiple_barcodes').value = '0';
    document.getElementById('itemCountDisplay').textContent = '1';
    document.getElementById('propertyPreview').innerHTML = '';
    
    var typeSelect = document.getElementById('type_equipment_id');
    if (typeSelect) typeSelect.value = '';
    var subTypeSelect = document.getElementById('equipment_sub_type_id');
    if (subTypeSelect) subTypeSelect.innerHTML = '<option value="">-- First select Type of Equipment --</option>';
    
    calculateTotal();
}

// Close Modal
function closeModal() {
    document.getElementById('ppeModal').style.display = 'none';
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

// Close All Barcodes Modal
function closeAllBarcodesModal() {
    document.getElementById('viewAllBarcodesModal').style.display = 'none';
}

// Generate Barcode for edit
function generateBarcodeForEdit() {
    let typeSelect = document.getElementById('type_equipment_id');
    let subTypeSelect = document.getElementById('equipment_sub_type_id');
    let typeCode = typeSelect.options[typeSelect.selectedIndex]?.text.split(' - ')[0] || 'PPE';
    let subTypeCode = subTypeSelect.options[subTypeSelect.selectedIndex]?.text.split(' - ')[0] || '00';
    let prefix = typeCode + '-' + subTypeCode;
    let date = new Date();
    let dateStr = date.getFullYear() + String(date.getMonth() + 1).padStart(2, '0') + String(date.getDate()).padStart(2, '0');
    let random = Math.floor(1000 + Math.random() * 9000);
    let barcodeValue = prefix + '-' + dateStr + '-' + random;
    document.getElementById('barcode_data').value = barcodeValue;
    
    let previewDiv = document.getElementById('barcodePreview');
    previewDiv.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Generating...</div>';
    
    fetch('generate_barcodeppe.php?code=' + encodeURIComponent(barcodeValue) + '&format=png')
    .then(response => response.blob())
    .then(blob => {
        let url = URL.createObjectURL(blob);
        previewDiv.innerHTML = '<img src="' + url + '" alt="Barcode" style="max-width: 200px; border: 1px solid #ddd; padding: 10px; border-radius: 5px;" onload="URL.revokeObjectURL(\'' + url + '\')">';
    })
    .catch(error => {
        console.error('Error:', error);
        previewDiv.innerHTML = '';
    });
}

// View Item
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
                let statusBadge = item.is_issued > 0 ? 
                    '<span class="badge-warning">Issued</span>' : 
                    '<span class="badge-success">Available</span>';
                
                let html = `
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-info-circle"></i> Basic Information</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-label">Article Name</div>
                                    <div class="detail-value">${escapeHtml(item.article_name)}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Property Number</div>
                                    <div class="detail-value">${escapeHtml(item.property_no || 'N/A')}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Description</div>
                                    <div class="detail-value">${escapeHtml(item.description || 'N/A')}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Status</div>
                                    <div class="detail-value">${statusBadge}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-tags"></i> Classification</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-label">Type of Equipment</div>
                                    <div class="detail-value">${escapeHtml(item.type_equipment_name || 'N/A')}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Equipment Category</div>
                                    <div class="detail-value">${escapeHtml(item.sub_type_name || 'N/A')}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Year Acquired</div>
                                    <div class="detail-value">${escapeHtml(item.year_acquired || 'N/A')}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Condition</div>
                                    <div class="detail-value">${escapeHtml(item.condition_text || 'Good')}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-truck"></i> Supplier Information</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-label">Supplier</div>
                                    <div class="detail-value">${escapeHtml(item.supplier || 'N/A')}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Reference PO Number</div>
                                    <div class="detail-value">${escapeHtml(item.ref_po_number || 'N/A')}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Delivery Date</div>
                                    <div class="detail-value">${item.delivery_date ? new Date(item.delivery_date).toLocaleDateString() : 'N/A'}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-calculator"></i> Quantity and Value</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-label">Quantity</div>
                                    <div class="detail-value">${item.qty_physical_count} ${escapeHtml(item.uom)}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Unit Value</div>
                                    <div class="detail-value">₱${parseFloat(item.unit_value).toFixed(2)}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Total Value</div>
                                    <div class="detail-value">₱${(item.qty_physical_count * item.unit_value).toFixed(2)}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Fund Cluster</div>
                                    <div class="detail-value">${escapeHtml(item.fund_cluster || 'N/A')}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-users"></i> Personnel</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-label">Certified Correct By</div>
                                    <div class="detail-value">${escapeHtml(item.certified_correct_names || 'N/A')}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Approved By</div>
                                    <div class="detail-value">${escapeHtml(item.approved_by_names || 'N/A')}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Verified By</div>
                                    <div class="detail-value">${escapeHtml(item.verified_by_names || 'N/A')}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Created By</div>
                                    <div class="detail-value">${escapeHtml(item.created_by_name || 'N/A')}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-calendar"></i> Dates</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <div class="detail-label">Date Added</div>
                                    <div class="detail-value">${new Date(item.date_added).toLocaleString()}</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Last Updated</div>
                                    <div class="detail-value">${item.date_updated ? new Date(item.date_updated).toLocaleString() : 'Never'}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-barcode"></i> Barcode Information</div>
                        <div class="detail-content">
                            ${item.barcode_data ? `
                            <div class="barcode-detail-item">
                                <div class="barcode-label">Barcode:</div>
                                <div class="barcode-image">
                                    <img src="generate_barcodeppe.php?code=${encodeURIComponent(item.barcode_data)}&format=png&width=300&height=60" 
                                         alt="Barcode" 
                                         onerror="this.style.display='none'; this.parentNode.innerHTML += '<div style=\\'font-family: monospace; padding: 10px; background: #f0f0f0;\\'>' + escapeHtml('${item.barcode_data}') + '</div>';">
                                </div>
                                <div class="barcode-value">${escapeHtml(item.barcode_data)}</div>
                            </div>
                            ` : '<p class="text-muted">No barcode assigned to this item.</p>'}
                            ${item.is_multiple ? `
                            <div style="margin-top: 15px; padding: 10px; background: #e8f4f8; border-radius: 5px;">
                                <i class="fas fa-info-circle" style="color: #2196F3;"></i>
                                <small> This item is part of a multiple set. <button class="btn btn-xs btn-info" onclick="viewAllBarcodes('${item.property_no}', '${escapeHtml(item.article_name)}')">View All Barcodes</button></small>
                            </div>
                            ` : ''}
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-comment"></i> Remarks</div>
                        <div class="detail-content">
                            <div class="detail-value">${escapeHtml(item.remarks || 'No remarks')}</div>
                        </div>
                    </div>
                `;
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error loading item: ' + (data.message || 'Unknown error') + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = '<div class="alert alert-danger">Error loading item details. Please try again.</div>';
        });
}

// View all barcodes for a multiple set
function viewAllBarcodes(propertyNo, itemName) {
    document.getElementById('allBarcodesModalTitle').textContent = 'All Barcodes - ' + escapeHtml(itemName);
    document.getElementById('allBarcodesContent').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Loading barcodes...</div>';
    document.getElementById('viewAllBarcodesModal').style.display = 'block';
    
    let timestamp = new Date().getTime();
    let url = '?get_multiple_items=1&property_no=' + encodeURIComponent(propertyNo) + '&t=' + timestamp;
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
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
                                <img src="generate_barcodeppe.php?code=${encodeURIComponent(item.barcode_data)}&width=250&height=60" 
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
            console.error('Error:', error);
            document.getElementById('allBarcodesContent').innerHTML = '<div class="alert alert-danger">Error loading barcodes: ' + escapeHtml(error.message) + '</div>';
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
                    --primary: #6B8CFF;
                    --accent: #F8B0C0;
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
                .item-property { font-weight: bold; color: var(--primary); margin-bottom: 10px; }
                .barcode-value { font-family: monospace; color: var(--text-primary); margin-top: 5px; font-size: 12px; }
                .item-actions { display: none; }
                @media print {
                    body { margin: 0; padding: 10px; }
                    .barcode-item-card { border: 1px solid #000; }
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

// Show Barcode Modal
function showBarcodeModal(barcodeData, itemName) {
    document.getElementById('barcodeModalTitle').textContent = 'Barcode - ' + escapeHtml(itemName);
    
    document.getElementById('barcodeModalImage').innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading barcode...</div>';
    
    let timestamp = new Date().getTime();
    let imageUrl = 'generate_barcodeppe.php?code=' + encodeURIComponent(barcodeData) + '&format=png&width=300&height=80&t=' + timestamp;
    
    let img = new Image();
    img.onload = function() {
        document.getElementById('barcodeModalImage').innerHTML = '<img src="' + imageUrl + '" alt="Barcode" style="max-width: 100%; border: 1px solid #ddd; padding: 10px; border-radius: 5px;">';
    };
    img.onerror = function() {
        document.getElementById('barcodeModalImage').innerHTML = '<iframe src="generate_barcodeppe.php?code=' + encodeURIComponent(barcodeData) + '&format=html" style="width: 100%; height: 150px; border: none; border-radius: 5px;"></iframe>';
    };
    img.src = imageUrl;
    
    document.getElementById('barcodeModalNumber').textContent = barcodeData;
    document.getElementById('barcodeModal').style.display = 'block';
}

// Print current barcode
function printCurrentBarcode() {
    let barcodeData = document.getElementById('barcodeModalNumber').textContent;
    let itemName = document.getElementById('barcodeModalTitle').textContent.replace('Barcode - ', '');
    printBarcode(barcodeData, itemName);
}

// Print barcode function
function printBarcode(barcodeData, itemName) {
    let printWindow = window.open('', '_blank');
    let timestamp = new Date().getTime();
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Print Barcode - ${escapeHtml(itemName)}</title>
            <style>
                body { text-align: center; font-family: Arial, sans-serif; margin: 0; padding: 20px; background: white; }
                .barcode-container { margin: 20px auto; padding: 30px; max-width: 400px; border: 1px dashed #6B8CFF; border-radius: 10px; }
                .barcode-img { max-width: 100%; height: auto; margin-bottom: 15px; }
                .item-name { margin-top: 15px; font-size: 16px; font-weight: bold; color: #3A3A3A; }
                .barcode-number { font-family: monospace; font-size: 14px; margin-top: 10px; color: #6B8CFF; }
                .ppe-label { background: #6B8CFF; color: white; padding: 5px 15px; border-radius: 20px; display: inline-block; font-size: 14px; margin-bottom: 15px; }
                @media print { body { margin: 0; padding: 10px; } .barcode-container { border: none; } }
            </style>
        </head>
        <body>
            <div class="barcode-container">
                <div class="ppe-label">PPE Equipment</div>
                <img src="generate_barcodeppe.php?code=${encodeURIComponent(barcodeData)}&format=png&width=400&height=100&t=${timestamp}" 
                     class="barcode-img" alt="Barcode" onerror="this.style.display='none'">
                <div class="item-name">${escapeHtml(itemName)}</div>
                <div class="barcode-number">${escapeHtml(barcodeData)}</div>
            </div>
            <script>
                window.onload = function() { 
                    setTimeout(function() { window.print(); window.close(); }, 500);
                }
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// Helper Functions
function closeModal() {
    document.getElementById('ppeModal').style.display = 'none';
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

function escapeHtml(text) {
    if (!text) return '';
    let div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatCurrency(amount) {
    if (amount === undefined || amount === null) return '₱0.00';
    return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Close modals when clicking outside
window.onclick = function(event) {
    let ppeModal = document.getElementById('ppeModal');
    let viewModal = document.getElementById('viewModal');
    let barcodeModal = document.getElementById('barcodeModal');
    let allBarcodesModal = document.getElementById('viewAllBarcodesModal');
    
    if (event.target == ppeModal) {
        closeModal();
    }
    if (event.target == viewModal) {
        closeViewModal();
    }
    if (event.target == barcodeModal) {
        closeBarcodeModal();
    }
    if (event.target == allBarcodesModal) {
        closeAllBarcodesModal();
    }
}

// Auto-calculate on page load if editing
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
                    let typeSelect = document.getElementById('type_equipment_id');
                    typeSelect.value = item.type_equipment_id;
                    loadEquipmentSubTypesAndSelect(item.equipment_sub_type_id);
                }
                
                document.getElementById('supplier').value = item.supplier || '';
                document.getElementById('ref_po_number').value = item.ref_po_number || '';
                document.getElementById('delivery_date').value = item.delivery_date || '';
                document.getElementById('uom').value = item.uom;
                document.getElementById('quantity').value = item.qty_physical_count;
                document.getElementById('unit_value').value = item.unit_value;
                document.getElementById('fund_cluster').value = item.fund_cluster || '';
                document.getElementById('condition_text').value = item.condition_text || 'Serviceable';
                document.getElementById('remarks').value = item.remarks || '';
                document.getElementById('barcode_data').value = item.barcode_data || '';
                
                if (item.certified_correct_array) {
                    let certifiedSelect = document.getElementById('certified_correct');
                    for(let opt of certifiedSelect.options) {
                        opt.selected = item.certified_correct_array.includes(parseInt(opt.value));
                    }
                }
                if (item.approved_by_array) {
                    let approvedSelect = document.getElementById('approved_by');
                    for(let opt of approvedSelect.options) {
                        opt.selected = item.approved_by_array.includes(parseInt(opt.value));
                    }
                }
                if (item.verified_by_array) {
                    let verifiedSelect = document.getElementById('verified_by');
                    for(let opt of verifiedSelect.options) {
                        opt.selected = item.verified_by_array.includes(parseInt(opt.value));
                    }
                }
                
                calculateTotal();
            }
        })
        .catch(error => console.error('Error loading edit item:', error));
    <?php endif; ?>
});

// Helper function to load sub types and select a specific value
function loadEquipmentSubTypesAndSelect(selectedSubTypeId) {
    var typeId = document.getElementById('type_equipment_id').value;
    var subTypeSelect = document.getElementById('equipment_sub_type_id');
    
    if (!typeId) return;
    
    var url = '?ajax=get_sub_types&type_id=' + encodeURIComponent(typeId);
    
    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data.length > 0) {
                subTypeSelect.innerHTML = '<option value="">-- Select Equipment Category --</option>';
                data.data.forEach(function(subType) {
                    var option = document.createElement('option');
                    option.value = subType.id;
                    option.textContent = subType.code + ' - ' + subType.name;
                    subTypeSelect.appendChild(option);
                });
                if (selectedSubTypeId) {
                    subTypeSelect.value = selectedSubTypeId;
                }
                subTypeSelect.disabled = false;
                previewPropertyNumber();
            }
        })
        .catch(error => {
            console.error('Error loading sub types:', error);
        });
}

// Edit item function for action buttons
function editItem(id) {
    window.location.href = '?edit=' + id + '&csrf_token=<?php echo $_SESSION['csrf_token']; ?>';
}

// Issue item function for action buttons
function issueItem(id) {
    window.location.href = 'issue_items.php?item=' + id;
}
</script>

<?php
include INCLUDE_PATH . '/footer.php';
ob_end_flush();
?>