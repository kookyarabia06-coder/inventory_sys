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

// Check role
$user_role = $_SESSION['role'] ?? '';
if (!in_array($user_role, ['admin', 'superadmin', 'supply'])) {
    header('Location: ' . SITE_URL . '/dashboard.php');
    exit;
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
    
    $type_query = $conn->prepare("SELECT code FROM type_of_equipment WHERE id = ?");
    $type_query->bind_param("i", $type_id);
    $type_query->execute();
    $type_result = $type_query->get_result();
    $type_code = $type_result->fetch_assoc()['code'] ?? '00';
    $type_query->close();
    
    $subtype_query = $conn->prepare("SELECT code FROM equipment_sub_type WHERE id = ?");
    $subtype_query->bind_param("i", $sub_type_id);
    $subtype_query->execute();
    $subtype_result = $subtype_query->get_result();
    $subtype_code = $subtype_result->fetch_assoc()['code'] ?? '00';
    $subtype_query->close();
    
    $year = date('Y');
    $pattern_like = $year . '-' . $type_code . '-' . $subtype_code . '-%';
    
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
        FROM semi_ppe i
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
               (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') as is_issued
        FROM semi_ppe i
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

// ============================================
// FUNCTION TO GENERATE PROPERTY NUMBER
// ============================================

function generatePropertyNumber($conn, $type_equipment_id, $equipment_sub_type_id, $sequence_number = null) {
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
    $base_pattern = $year . '-' . $type_code . '-' . $subtype_code;
    
    if ($sequence_number !== null) {
        return $base_pattern . '-' . str_pad($sequence_number, 4, '0', STR_PAD_LEFT);
    }
    
    $pattern_like = $year . '-' . $type_code . '-' . $subtype_code . '-%';
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
    $uom = sanitize($_POST['uom'] ?? '');
    $quantity = floatval($_POST['quantity'] ?? 0);
    $unit_value = floatval($_POST['unit_value'] ?? 0);
    $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
    $category = 'Semi-Expendable';
    
    $type_equipment_id = !empty($_POST['type_equipment_id']) ? (int)$_POST['type_equipment_id'] : null;
    $equipment_sub_type_id = !empty($_POST['equipment_sub_type_id']) ? (int)$_POST['equipment_sub_type_id'] : null;
    
    $fund_cluster = sanitize($_POST['fund_cluster'] ?? '');
    
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
        
        try {
            if ($generate_multiple && $quantity > 1 && floor($quantity) == $quantity) {
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
                    FROM semi_ppe 
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
                        INSERT INTO semi_ppe (
                            article_name, description, property_no, uom, 
                            qty_property_card, qty_physical_count, unit_value,
                            equipment_id, type_equipment_id, equipment_sub_type_id, 
                            condition_text, fund_cluster, certified_correct, 
                            approved_by, verified_by, supplier, ref_po_number, 
                            delivery_date, remarks, barcode_data, created_by, 
                            year_acquired, category
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $qty_property_card = 1;
                    $qty_physical_count = 1;
                    
                    $stmt->bind_param(
                        "ssssdddiiissssssssssiis",
                        $article_name, $description, $property_no, $uom,
                        $qty_property_card, $qty_physical_count, $unit_value,
                        $equipment_id, $type_equipment_id, $equipment_sub_type_id,
                        $condition_text, $fund_cluster, $certified_correct,
                        $approved_by, $verified_by, $supplier, $ref_po_number,
                        $delivery_date, $remarks, $sequential_barcode, $created_by,
                        $year_acquired, $category
                    );
                    $stmt->execute();
                    $stmt->close();
                }
                $conn->commit();
                $_SESSION['success'] = "$quantity items added successfully.";
            } else {
                $property_no = generatePropertyNumber($conn, $type_equipment_id, $equipment_sub_type_id);
                
                $stmt = $conn->prepare("
                    INSERT INTO semi_ppe (
                        article_name, description, property_no, uom, 
                        qty_property_card, qty_physical_count, unit_value,
                        equipment_id, type_equipment_id, equipment_sub_type_id, 
                        condition_text, fund_cluster, certified_correct, 
                        approved_by, verified_by, supplier, ref_po_number, 
                        delivery_date, remarks, barcode_data, created_by, 
                        year_acquired, category
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $qty_property_card = $quantity;
                $qty_physical_count = $quantity;
                
                $stmt->bind_param(
                    "ssssdddiiissssssssssiis",
                    $article_name, $description, $property_no, $uom,
                    $qty_property_card, $qty_physical_count, $unit_value,
                    $equipment_id, $type_equipment_id, $equipment_sub_type_id,
                    $condition_text, $fund_cluster, $certified_correct,
                    $approved_by, $verified_by, $supplier, $ref_po_number,
                    $delivery_date, $remarks, $barcode_data, $created_by,
                    $year_acquired, $category
                );
                
                if ($stmt->execute()) {
                    $conn->commit();
                    $_SESSION['success'] = "Item added successfully. Property No: $property_no, Quantity: $quantity $uom";
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
    $uom = sanitize($_POST['uom'] ?? '');
    $quantity = floatval($_POST['quantity'] ?? 0);
    $unit_value = floatval($_POST['unit_value'] ?? 0);
    $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
    
    $type_equipment_id = !empty($_POST['type_equipment_id']) ? (int)$_POST['type_equipment_id'] : null;
    $equipment_sub_type_id = !empty($_POST['equipment_sub_type_id']) ? (int)$_POST['equipment_sub_type_id'] : null;
    
    $fund_cluster = sanitize($_POST['fund_cluster'] ?? '');
    
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
        UPDATE semi_ppe SET 
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
    
    $stmt->bind_param(
        "ssssdddiiisssssssssi",
        $article_name, $description, $uom, $quantity, $unit_value,
        $equipment_id, $type_equipment_id, $equipment_sub_type_id,
        $condition_text, $fund_cluster, $certified_correct,
        $approved_by, $verified_by, $supplier, $ref_po_number,
        $delivery_date, $remarks, $barcode_data, $id
    );
    
    if ($stmt->execute()) {
        $_SESSION['success'] = "Item updated successfully";
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
            $_SESSION['success'] = "Item deleted successfully";
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

// ============================================
// DISPLAY DATA - SHOW EACH ITEM WITH ITS OWN BARCODE
// ============================================

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20; // Show 20 items per page
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';

// Query to get all items (NOT grouped, each item as separate row)
$query = "
    SELECT i.*, 
           e.name as equipment_name,
           toe.name as type_equipment_name,
           est.name as sub_type_name,
           CONCAT(ap.firstname, ' ', ap.lastname) as approver_name,
           CONCAT(vr.firstname, ' ', vr.lastname) as verifier_name,
           CONCAT(cr.firstname, ' ', cr.lastname) as created_by_name,
           (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') as is_issued
    FROM semi_ppe i
    LEFT JOIN equipment e ON i.equipment_id = e.id
    LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
    LEFT JOIN equipment_sub_type est ON i.equipment_sub_type_id = est.id
    LEFT JOIN users ap ON i.approved_by = ap.id
    LEFT JOIN users vr ON i.verified_by = vr.id
    LEFT JOIN users cr ON i.created_by = cr.id
";

// Count query
$count_query = "SELECT COUNT(*) as total FROM semi_ppe i";

if (!empty($search)) {
    $query .= " WHERE (i.article_name LIKE ? OR i.property_no LIKE ? OR i.description LIKE ?)";
    $count_query .= " WHERE (article_name LIKE ? OR property_no LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
}

$query .= " ORDER BY i.article_name ASC, i.property_no ASC LIMIT ? OFFSET ?";

// Get total count
if (!empty($search)) {
    $stmt = $conn->prepare($count_query);
    $stmt->bind_param("sss", $search_term, $search_term, $search_term);
} else {
    $stmt = $conn->prepare($count_query);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_rows = $total_result->fetch_assoc()['total'];
$stmt->close();

// Calculate pagination
$total_pages = ceil($total_rows / $per_page);
$offset = ($page - 1) * $per_page;

// Get items for current page
if (!empty($search)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssii", $search_term, $search_term, $search_term, $per_page, $offset);
} else {
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

$semi_items = [];
while ($row = $result->fetch_assoc()) {
    $semi_items[] = $row;
}
$stmt->close();

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
    --success: #4CAF50;
    --danger: #f44336;
    --info: #8FB5FF;
    --warning: #FF9800;
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

.text-warning {
    color: var(--warning) !important;
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

.search-box input[type="text"] {
    padding: 12px 15px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 14px;
    flex: 1;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.search-box input[type="text"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.search-box button {
    padding: 12px 24px;
    background: var(--primary);
    color: white;
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
    min-width: 1000px;
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
    vertical-align: middle;
}

tr:hover td {
    background-color: var(--light);
}

.article-name-cell strong {
    color: var(--text-primary);
    font-size: 14px;
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

.quantity-badge {
    background-color: var(--accent-light);
    color: var(--primary);
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: bold;
    display: inline-block;
}

.badge-warning {
    background-color: var(--secondary);
    color: white;
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
    color: white;
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
    color: white;
}

.btn-secondary:hover {
    background-color: #7a9fe6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(143, 181, 255, 0.3);
}

/* Pagination Styles */
.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.pagination a, .pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 35px;
    height: 35px;
    padding: 0 10px;
    border-radius: 6px;
    background: var(--white);
    color: var(--text-secondary);
    text-decoration: none;
    border: 1px solid var(--border-light);
    transition: all 0.2s;
}

.pagination a:hover {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination .active {
    background: var(--primary);
    color: white;
    border-color: var(--primary);
}

.pagination .disabled {
    opacity: 0.5;
    cursor: not-allowed;
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
    width: 90%;
    max-width: 900px;
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
    flex-shrink: 0;
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
    overflow-y: auto;
    flex: 1;
    max-height: calc(90vh - 150px);
}

.modal-footer {
    padding: 15px 25px;
    border-top: 1px solid var(--border-light);
    text-align: right;
    background: var(--light);
    border-radius: 0 0 12px 12px;
    flex-shrink: 0;
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

.loading {
    text-align: center;
    padding: 30px;
    color: var(--text-muted);
}

.loading i {
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

.text-center {
    text-align: center;
}

.mt-3 {
    margin-top: 15px;
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

.barcode-img {
    text-align: center;
}

.barcode-img img {
    max-width: 120px;
    height: auto;
    border: 1px solid #ddd;
    padding: 5px;
    border-radius: 4px;
    background: white;
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
    
    .pagination {
        gap: 3px;
    }
    
    .pagination a, .pagination span {
        min-width: 30px;
        height: 30px;
        font-size: 12px;
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

<!-- Statistics Cards -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon"><i class="fas fa-boxes"></i></div>
        <h3>Total Items</h3>
        <?php
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM semi_ppe");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_items = $result->fetch_assoc()['count'];
        $stmt->close();
        ?>
        <div class="card-value"><?php echo $total_items; ?></div>
        <div class="card-label">Total records</div>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-cubes"></i></div>
        <h3>Total Quantity</h3>
        <?php
        $stmt = $conn->prepare("SELECT SUM(qty_physical_count) as total FROM semi_ppe");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_qty = $result->fetch_assoc()['total'];
        $stmt->close();
        ?>
        <div class="card-value"><?php echo number_format($total_qty); ?></div>
        <div class="card-label">Total units in stock</div>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-hand-holding"></i></div>
        <h3>Issued Items</h3>
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
        <div class="card-icon"><i class="fas fa-dollar-sign"></i></div>
        <h3>Total Value</h3>
        <?php
        $stmt = $conn->prepare("SELECT SUM(unit_value * qty_physical_count) as total FROM semi_ppe");
        $stmt->execute();
        $result = $stmt->get_result();
        $total_value = $result->fetch_assoc()['total'] ?? 0;
        $stmt->close();
        ?>
        <div class="card-value">₱<?php echo number_format($total_value, 2); ?></div>
        <div class="card-label">Total inventory value</div>
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
        <a href="<?php echo SITE_URL; ?>/admin/issue_items.php?category=semi" class="btn btn-primary">
            <i class="fas fa-hand-holding"></i> Issue Item
        </a>
    </div>
</div>

<!-- Search -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-search"></i> Search Items</h2>
    </div>
    <form method="GET" action="" class="search-box">
        <input type="text" name="search" placeholder="Search by article name, property no., or description..." 
               value="<?php echo htmlspecialchars($search); ?>">
        <input type="hidden" name="page" value="1">
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

<!-- Semi-Expendable Items Table - EACH ITEM WITH ITS OWN BARCODE -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-boxes"></i> Semi-Expendable Items List</h2>
        <p>Showing <?php echo count($semi_items); ?> of <?php echo $total_rows; ?> items (Page <?php echo $page; ?> of <?php echo $total_pages; ?>)</p>
    </div>
    
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Article Name</th>
                    <th>Property No.</th>
                    <th>Category</th>
                    <th>Quantity</th>
                    <th>Unit Value</th>
                    <th>Supplier</th>
                    <th>Condition</th>
                    <th>Status</th>
                    <th>Barcode</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($semi_items) > 0): ?>
                    <?php $counter = $offset + 1; ?>
                    <?php foreach ($semi_items as $item): ?>
                    <tr>
                        <td><?php echo $counter++; ?></td>
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
                        <td>
                            <span class="quantity-badge"><?php echo $item['qty_physical_count'] . ' ' . $item['uom']; ?></span>
                        </td>
                        <td>₱<?php echo number_format($item['unit_value'], 2); ?></td>
                        <td><?php echo htmlspecialchars($item['supplier'] ?? 'N/A'); ?></td>
                        <td><?php echo htmlspecialchars($item['condition_text'] ?? 'Good'); ?></td>
                        <td>
                            <?php if ($item['is_issued'] > 0): ?>
                                <span class="badge-warning">Issued</span>
                            <?php else: ?>
                                <span class="badge-success">Available</span>
                            <?php endif; ?>
                        </td>
                        <td class="barcode-img">
                            <?php if (!empty($item['barcode_data'])): ?>
                                <img src="generate_barcode.php?code=<?php echo urlencode($item['barcode_data']); ?>&format=png&width=150&height=40" 
                                     alt="Barcode" 
                                     onerror="this.style.display='none'; this.parentNode.innerHTML='<?php echo htmlspecialchars(substr($item['barcode_data'], 0, 15)); ?>...';">
                                <br>
                                <small><?php echo htmlspecialchars(substr($item['barcode_data'], 0, 20)); ?></small>
                            <?php else: ?>
                                <span class="text-muted">No barcode</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn view" onclick="viewItem(<?php echo $item['id']; ?>)" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="?edit=<?php echo $item['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo $page > 1 ? '&page=' . $page : ''; ?>" class="action-btn edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($item['is_issued'] == 0): ?>
                                <a href="?delete=<?php echo $item['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
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
                        <td colspan="11" class="text-center">
                            <i class="fas fa-boxes" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                            <br>
                            No semi-expendable items found
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
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>">&laquo; First</a>
            <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">&lsaquo; Previous</a>
        <?php else: ?>
            <span class="disabled">&laquo; First</span>
            <span class="disabled">&lsaquo; Previous</span>
        <?php endif; ?>
        
        <?php
        $start_page = max(1, $page - 2);
        $end_page = min($total_pages, $page + 2);
        
        if ($start_page > 1) {
            echo '<span>...</span>';
        }
        
        for ($i = $start_page; $i <= $end_page; $i++):
        ?>
            <?php if ($i == $page): ?>
                <span class="active"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>
        
        <?php if ($end_page < $total_pages): ?>
            <span>...</span>
        <?php endif; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Next &rsaquo;</a>
            <a href="?page=<?php echo $total_pages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">Last &raquo;</a>
        <?php else: ?>
            <span class="disabled">Next &rsaquo;</span>
            <span class="disabled">Last &raquo;</span>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Add/Edit Semi-Expendable Modal (same as before) -->
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
                        <input type="text" class="form-control" id="article_name" name="article_name" required maxlength="255" placeholder="e.g., Bond Paper, Ballpen, Ink Cartridge">
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
                        </div>
                        <div class="form-group">
                            <label for="equipment_sub_type_id">Equipment Category <span class="text-danger">*</span></label>
                            <select class="form-control" id="equipment_sub_type_id" name="equipment_sub_type_id" required onchange="previewPropertyNumber();">
                                <option value="">-- First select Type of Equipment --</option>
                            </select>
                        </div>
                    </div>
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
                                <option value="ream">Ream</option>
                                <option value="box">Box</option>
                                <option value="unit">Unit</option>
                                <option value="set">Set</option>
                                <option value="pair">Pair</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="quantity">Quantity <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="quantity" name="quantity" value="1" min="1" step="1" required onchange="checkQuantityForMultipleBarcodes(); previewPropertyNumber();">
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
                                When checked, each item will have its own unique barcode
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
                            <label for="certified_correct">Certified Correct By</label>
                            <select class="form-control" id="certified_correct" name="certified_correct[]" multiple>
                                <?php if ($users): $users->data_seek(0); while($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                            <small class="form-text">Hold Ctrl to select multiple</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="approved_by">Approved By</label>
                            <select class="form-control" id="approved_by" name="approved_by[]" multiple>
                                <?php if ($users): $users->data_seek(0); while($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                            <small class="form-text">Hold Ctrl to select multiple</small>
                        </div>
                        
                        <div class="form-group">
                            <label for="verified_by">Verified By</label>
                            <select class="form-control" id="verified_by" name="verified_by[]" multiple>
                                <?php if ($users): $users->data_seek(0); while($user = $users->fetch_assoc()): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>
                                </option>
                                <?php endwhile; endif; ?>
                            </select>
                            <small class="form-text">Hold Ctrl to select multiple</small>
                        </div>
                    </div>
                </div>
                
                <!-- Barcode -->
                <div class="form-section">
                    <h3><i class="fas fa-barcode"></i> Barcode Information</h3>
                    <div class="form-group">
                        <label for="barcode_data">Barcode</label>
                        <input type="text" class="form-control" id="barcode_data" name="barcode_data" 
                               placeholder="Enter barcode value">
                        <button type="button" class="btn btn-secondary" onclick="generateBarcodeForEdit()" style="margin-top: 5px;">
                            <i class="fas fa-sync-alt"></i> Generate Barcode
                        </button>
                        <div id="barcodePreview" class="barcode-preview" style="margin-top: 10px; text-align: center;"></div>
                    </div>
                </div>
                
                <!-- Remarks -->
                <div class="form-section">
                    <h3><i class="fas fa-comment"></i> Remarks</h3>
                    <div class="form-group">
                        <textarea class="form-control" id="remarks" name="remarks" rows="2" placeholder="Any additional notes"></textarea>
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

<!-- View Item Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content" style="max-width: 700px; max-height: 80vh; display: flex; flex-direction: column;">
        <div class="modal-header" style="flex-shrink: 0;">
            <h2>Item Details</h2>
            <span class="modal-close" onclick="closeViewModal()">&times;</span>
        </div>
        <div class="modal-body" id="viewModalContent" style="overflow-y: auto; flex: 1; padding: 20px;">
            <div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Loading...</div>
        </div>
        <div class="modal-footer" style="flex-shrink: 0;">
            <button type="button" class="btn btn-secondary" onclick="closeViewModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<script>
// Store equipment sub types data
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
                subTypeSelect.innerHTML = '<option value="">-- No categories found --</option>';
                subTypeSelect.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            subTypeSelect.innerHTML = '<option value="">-- Error loading --</option>';
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
                var html = '<div style="background: #e8f4f8; padding: 10px; border-radius: 5px;">';
                html += '<i class="fas fa-qrcode" style="color: #6B8CFF;"></i> ';
                html += '<strong>Property Number Format:</strong> ';
                html += '<code>' + data.property_format + '</code>';
                if (data.is_multiple && data.sequences) {
                    html += '<br><small>Will generate: ' + data.sequences.join(', ') + '</small>';
                }
                html += '</div>';
                document.getElementById('propertyPreview').innerHTML = html;
            }
        })
        .catch(error => console.error('Error:', error));
}

function calculateTotal() {
    let quantity = parseFloat(document.getElementById('quantity').value) || 0;
    let unitValue = parseFloat(document.getElementById('unit_value').value) || 0;
    let total = quantity * unitValue;
    document.getElementById('total_value').value = '₱' + total.toFixed(2);
}

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

function previewMultipleBarcodes() {
    let quantity = parseInt(document.getElementById('quantity').value);
    if (isNaN(quantity) || quantity < 1) quantity = 1;
    
    let typeSelect = document.getElementById('type_equipment_id');
    let subTypeSelect = document.getElementById('equipment_sub_type_id');
    let typeCode = typeSelect.options[typeSelect.selectedIndex]?.text.split(' - ')[0] || 'SEMI';
    let subTypeCode = subTypeSelect.options[subTypeSelect.selectedIndex]?.text.split(' - ')[0] || '00';
    let prefix = typeCode + '-' + subTypeCode;
    
    let previewDiv = document.getElementById('multipleBarcodePreview');
    previewDiv.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Generating preview...</div>';
    
    fetch('?generate_multiple_preview=1&prefix=' + encodeURIComponent(prefix) + '&quantity=' + quantity)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let html = '<p style="margin-bottom: 10px; font-weight: bold;">Barcodes to be generated:</p>';
                if (data.barcodes && data.barcodes.length > 0) {
                    data.barcodes.forEach(barcode => {
                        html += `<div style="padding: 5px; border-bottom: 1px solid #eee;"><strong>#${barcode.index}:</strong> ${escapeHtml(barcode.value)}</div>`;
                    });
                    if (data.total > data.barcodes.length) {
                        html += `<p class="text-muted">... and ${data.total - data.barcodes.length} more</p>`;
                    }
                } else {
                    html += '<p class="text-muted">No barcodes to preview</p>';
                }
                previewDiv.innerHTML = html;
            } else {
                previewDiv.innerHTML = '<div class="alert alert-danger">Error generating preview</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            previewDiv.innerHTML = '<div class="alert alert-danger">Network error</div>';
        });
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
    document.getElementById('type_equipment_id').value = '';
    document.getElementById('equipment_sub_type_id').innerHTML = '<option value="">-- First select Type of Equipment --</option>';
    calculateTotal();
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

function generateBarcodeForEdit() {
    let barcodeValue = 'SEMI-' + new Date().getFullYear() + 
        String(new Date().getMonth() + 1).padStart(2, '0') + 
        String(new Date().getDate()).padStart(2, '0') + '-' + 
        Math.floor(1000 + Math.random() * 9000);
    document.getElementById('barcode_data').value = barcodeValue;
    
    let previewDiv = document.getElementById('barcodePreview');
    previewDiv.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Generating...</div>';
    
    fetch('generate_barcode.php?code=' + encodeURIComponent(barcodeValue) + '&format=png')
        .then(response => response.blob())
        .then(blob => {
            let url = URL.createObjectURL(blob);
            previewDiv.innerHTML = '<img src="' + url + '" alt="Barcode" style="max-width: 200px;">';
            URL.revokeObjectURL(url);
        })
        .catch(error => {
            console.error('Error:', error);
            previewDiv.innerHTML = '';
        });
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
                let statusBadge = item.is_issued > 0 ? 
                    '<span class="badge-warning">Issued</span>' : 
                    '<span class="badge-success">Available</span>';
                let barcodeHtml = '';
                if (item.barcode_data) {
                    barcodeHtml = `<div style="text-align: center;"><img src="generate_barcode.php?code=${encodeURIComponent(item.barcode_data)}&format=png" style="max-width: 300px;"><div class="detail-value">${escapeHtml(item.barcode_data)}</div></div>`;
                } else {
                    barcodeHtml = '<div class="detail-value">No barcode assigned</div>';
                }
                
                let html = `
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-info-circle"></i> Basic Information</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item"><div class="detail-label">Article Name</div><div class="detail-value">${escapeHtml(item.article_name)}</div></div>
                                <div class="detail-item"><div class="detail-label">Property Number</div><div class="detail-value">${escapeHtml(item.property_no)}</div></div>
                                <div class="detail-item"><div class="detail-label">Description</div><div class="detail-value">${escapeHtml(item.description || 'N/A')}</div></div>
                                <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value">${statusBadge}</div></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-tags"></i> Classification</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item"><div class="detail-label">Type of Equipment</div><div class="detail-value">${escapeHtml(item.type_equipment_name || 'N/A')}</div></div>
                                <div class="detail-item"><div class="detail-label">Equipment Category</div><div class="detail-value">${escapeHtml(item.sub_type_name || 'N/A')}</div></div>
                                <div class="detail-item"><div class="detail-label">Year Acquired</div><div class="detail-value">${escapeHtml(item.year_acquired)}</div></div>
                                <div class="detail-item"><div class="detail-label">Condition</div><div class="detail-value">${escapeHtml(item.condition_text)}</div></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-calculator"></i> Quantity and Value</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item"><div class="detail-label">Quantity</div><div class="detail-value">${item.qty_physical_count} ${escapeHtml(item.uom)}</div></div>
                                <div class="detail-item"><div class="detail-label">Unit Value</div><div class="detail-value">₱${parseFloat(item.unit_value).toFixed(2)}</div></div>
                                <div class="detail-item"><div class="detail-label">Total Value</div><div class="detail-value">₱${(item.qty_physical_count * item.unit_value).toFixed(2)}</div></div>
                                <div class="detail-item"><div class="detail-label">Fund Cluster</div><div class="detail-value">${escapeHtml(item.fund_cluster || 'N/A')}</div></div>
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
                                <div class="detail-item"><div class="detail-label">Certified Correct By</div><div class="detail-value">${escapeHtml(item.certified_correct_names || 'N/A')}</div></div>
                                <div class="detail-item"><div class="detail-label">Approved By</div><div class="detail-value">${escapeHtml(item.approved_by_names || 'N/A')}</div></div>
                                <div class="detail-item"><div class="detail-label">Verified By</div><div class="detail-value">${escapeHtml(item.verified_by_names || 'N/A')}</div></div>
                                <div class="detail-item"><div class="detail-label">Created By</div><div class="detail-value">${escapeHtml(item.created_by_name || 'N/A')}</div></div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-barcode"></i> Barcode</div>
                        <div class="detail-content">
                            ${barcodeHtml}
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <div class="detail-header"><i class="fas fa-calendar"></i> Dates</div>
                        <div class="detail-content">
                            <div class="detail-grid">
                                <div class="detail-item"><div class="detail-label">Date Added</div><div class="detail-value">${new Date(item.date_added).toLocaleString()}</div></div>
                                <div class="detail-item"><div class="detail-label">Last Updated</div><div class="detail-value">${item.date_updated ? new Date(item.date_updated).toLocaleString() : 'Never'}</div></div>
                            </div>
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
                content.innerHTML = '<div class="alert alert-danger">Error loading item details</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = '<div class="alert alert-danger">Error loading item details</div>';
        });
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
}

document.getElementById('quantity')?.addEventListener('input', calculateTotal);
document.getElementById('unit_value')?.addEventListener('input', calculateTotal);

<?php if (isset($_GET['edit'])): ?>
window.addEventListener('load', function() {
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
                        if (item.equipment_sub_type_id) {
                            document.getElementById('equipment_sub_type_id').value = item.equipment_sub_type_id;
                        }
                    }, 500);
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
                checkQuantityForMultipleBarcodes();
            }
        });
});
<?php endif; ?>
</script>

<?php
include INCLUDE_PATH . '/footer.php';
ob_end_flush();
?>