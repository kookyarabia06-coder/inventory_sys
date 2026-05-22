<?php
ob_start();
/**
 * Issue Items Page (Admin)
 * Handle item issuance and reissuance to employees
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Role checking
requireRole('admin');

$page_title = 'Issue Items';
$page_description = 'Issue inventory items to employees';

// ============================================
// DATABASE MIGRATIONS
// ============================================

$conn->query("ALTER TABLE equipment_issuance MODIFY COLUMN status ENUM('issued', 'returned', 'reissued') DEFAULT 'issued'");
$conn->query("ALTER TABLE equipment_issuance ADD COLUMN IF NOT EXISTS reissued_from_id INT NULL");
$conn->query("ALTER TABLE equipment_issuance ADD COLUMN IF NOT EXISTS reissued_to_id INT NULL");
$conn->query("ALTER TABLE equipment_issuance ADD COLUMN IF NOT EXISTS reissue_date DATETIME NULL");
$conn->query("ALTER TABLE equipment_issuance ADD COLUMN IF NOT EXISTS original_issuance_barcode VARCHAR(100) NULL");
$conn->query("ALTER TABLE inventory ADD COLUMN IF NOT EXISTS big_unit VARCHAR(50) NULL");
$conn->query("ALTER TABLE inventory ADD COLUMN IF NOT EXISTS small_unit VARCHAR(50) NULL");
$conn->query("ALTER TABLE equipment_issuance DROP COLUMN IF EXISTS purpose");

// ============================================
// AJAX HANDLER FOR VIEW MODAL
// ============================================
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_issuance') {
    $issuance_id = (int)$_GET['id'];
    $result = $conn->query("
        SELECT 
            ei.*, 
            i.article_name, 
            i.description,
            i.property_no,
            i.big_unit,
            i.small_unit,
            i.unit_value,
            CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
            e.position as issued_to_position,
            CONCAT(issuer.firstname, ' ', issuer.lastname) as issued_by_name,
            sig.name as signatory_name,
            CONCAT(original_emp.firstname, ' ', original_emp.lastname) as reissued_from_name,
            CONCAT(reissued_emp.firstname, ' ', reissued_emp.lastname) as reissued_to_name
        FROM equipment_issuance ei 
        JOIN inventory i ON ei.inventory_id = i.id 
        JOIN employees e ON ei.issued_to = e.id
        JOIN users issuer ON ei.issued_by = issuer.id
        LEFT JOIN signatories sig ON ei.signatory_id = sig.id
        LEFT JOIN equipment_issuance original_iss ON ei.reissued_from_id = original_iss.id
        LEFT JOIN employees original_emp ON original_iss.issued_to = original_emp.id
        LEFT JOIN employees reissued_emp ON ei.reissued_to_id = reissued_emp.id
        WHERE ei.id = $issuance_id
    ");
    
    if ($result && $result->num_rows > 0) {
        $item = $result->fetch_assoc();
        echo json_encode(['success' => true, 'data' => $item]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Issuance not found']);
    }
    exit();
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function getDepartmentCode($conn, $employee_id) {
    $emp_query = $conn->query("
        SELECT d.code as department_code 
        FROM employees e
        LEFT JOIN sections s ON e.section_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE e.id = " . intval($employee_id)
    );
    
    if ($emp_query && $emp_query->num_rows > 0) {
        $employee = $emp_query->fetch_assoc();
        if (!empty($employee['department_code'])) {
            return $employee['department_code'];
        }
    }
    return '000';
}

function removeDepartmentCodeFromPropertyNo($property_no) {
    if (empty($property_no)) return $property_no;
    $parts = explode('-', $property_no);
    $last_part = end($parts);
    if (strlen($last_part) == 3 && ctype_digit($last_part)) {
        array_pop($parts);
        return implode('-', $parts);
    }
    return $property_no;
}

function generatePropertyNumberWithDeptCode($conn, $inventory_id, $employee_id) {
    $inv_query = $conn->query("SELECT property_no FROM inventory WHERE id = " . intval($inventory_id));
    
    if (!$inv_query || $inv_query->num_rows == 0) {
        $date_prefix = date('Y-m-d');
        $random_num = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $dept_code = getDepartmentCode($conn, $employee_id);
        return $date_prefix . '-' . $random_num . '-' . $dept_code;
    }
    
    $item = $inv_query->fetch_assoc();
    $original_property_no = $item['property_no'];
    $dept_code = getDepartmentCode($conn, $employee_id);
    
    if (!empty($original_property_no)) {
        $parts = explode('-', $original_property_no);
        $last_part = end($parts);
        if (strlen($last_part) == 3 && ctype_digit($last_part)) {
            array_pop($parts);
            return implode('-', $parts) . '-' . $dept_code;
        } else {
            return $original_property_no . '-' . $dept_code;
        }
    } else {
        $date_prefix = date('Y-m-d');
        $random_num = str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        return $date_prefix . '-' . $random_num . '-' . $dept_code;
    }
}

// ============================================
// FORM HANDLERS
// ============================================

// New Issuance
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'new_issue') {
    $inventory_ids = isset($_POST['inventory_ids']) ? $_POST['inventory_ids'] : [];
    $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : [];
    $issued_to = (int)$_POST['issued_to'];
    $condition = sanitize($_POST['condition']);
    $remarks = sanitize($_POST['remarks'] ?? '');
    $signatory_id = !empty($_POST['signatory_id']) ? (int)$_POST['signatory_id'] : NULL;
    
    $valid_conditions = ['Serviceable', 'Non-Serviceable', 'For Condemn', 'Under Repair'];
    if (!in_array($condition, $valid_conditions)) {
        $_SESSION['error'] = "Invalid condition selected.";
        header('Location: ' . SITE_URL . '/admin/issue_items.php');
        exit();
    }
    
    $conn->begin_transaction();
    try {
        $success_count = 0;
        foreach ($inventory_ids as $index => $inventory_id) {
            $inventory_id = (int)$inventory_id;
            $requested_qty = isset($quantities[$index]) ? floatval($quantities[$index]) : 1;
            
            $selected_item = $conn->query("SELECT * FROM inventory WHERE id=$inventory_id")->fetch_assoc();
            if (!$selected_item) throw new Exception("Item not found");
            
            if ($selected_item['qty_physical_count'] < $requested_qty) {
                throw new Exception("Insufficient quantity for: " . $selected_item['article_name']);
            }
            
            $new_property_no = generatePropertyNumberWithDeptCode($conn, $inventory_id, $issued_to);
            $new_quantity = $selected_item['qty_physical_count'] - $requested_qty;
            $conn->query("UPDATE inventory SET qty_physical_count=$new_quantity, current_holder=$issued_to, property_no='$new_property_no', condition_text='$condition' WHERE id=$inventory_id");
            
            $issuance_barcode = $new_property_no;
            
            $stmt = $conn->prepare("
                INSERT INTO equipment_issuance (
                    inventory_id, issued_to, issued_by, signatory_id, quantity_issued, 
                    condition_on_issue, remarks, status, issued_date, issuance_barcode
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'issued', NOW(), ?)
            ");
            
            $stmt->bind_param("iiiidsss", 
                $inventory_id, 
                $issued_to, 
                $_SESSION['user_id'],
                $signatory_id, 
                $requested_qty, 
                $condition, 
                $remarks,
                $issuance_barcode
            );
            $stmt->execute();
            $stmt->close();
            
            $success_count++;
        }
        
        $conn->commit();
        $_SESSION['success'] = "$success_count item(s) issued successfully.";
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    header('Location: ' . SITE_URL . '/admin/issue_items.php');
    exit();
}

// Reissue from returned item
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'reissue') {
    $original_issuance_id = (int)$_POST['original_issuance_id'];
    $reissue_to = (int)$_POST['reissue_to'];
    $condition = sanitize($_POST['condition']);
    $remarks = sanitize($_POST['remarks'] ?? '');
    $signatory_id = !empty($_POST['signatory_id']) ? (int)$_POST['signatory_id'] : NULL;
    
    $valid_conditions = ['Serviceable', 'Non-Serviceable', 'For Condemn', 'Under Repair'];
    if (!in_array($condition, $valid_conditions)) {
        $_SESSION['error'] = "Invalid condition selected.";
        header('Location: ' . SITE_URL . '/admin/issue_items.php');
        exit();
    }
    
    $conn->begin_transaction();
    try {
        $original_issuance = $conn->query("
            SELECT ei.*, i.qty_physical_count, i.article_name, i.property_no 
            FROM equipment_issuance ei 
            JOIN inventory i ON ei.inventory_id = i.id 
            WHERE ei.id = $original_issuance_id AND ei.status = 'returned'
        ")->fetch_assoc();
        
        if (!$original_issuance) throw new Exception("Original issuance not found or not returned.");
        
        $new_property_no = generatePropertyNumberWithDeptCode($conn, $original_issuance['inventory_id'], $reissue_to);
        $conn->query("UPDATE inventory SET property_no = '$new_property_no', current_holder=$reissue_to, condition_text='$condition' WHERE id = {$original_issuance['inventory_id']}");
        
        $issuance_barcode = $new_property_no;
        
        $stmt = $conn->prepare("
            INSERT INTO equipment_issuance (
                inventory_id, issued_to, issued_by, signatory_id, quantity_issued, 
                condition_on_issue, remarks, status, issued_date, issuance_barcode,
                reissued_from_id, original_issuance_barcode
            ) VALUES (?, ?, ?, ?, ?, ?, ?, 'issued', NOW(), ?, ?, ?)
        ");
        
        $stmt->bind_param("iiiidssssi", 
            $original_issuance['inventory_id'], 
            $reissue_to, 
            $_SESSION['user_id'],
            $signatory_id, 
            $original_issuance['quantity_issued'], 
            $condition, 
            $remarks,
            $issuance_barcode,
            $original_issuance_id,
            $original_issuance['issuance_barcode']
        );
        $stmt->execute();
        $stmt->close();
        
        $conn->query("UPDATE equipment_issuance SET status='reissued', reissued_to_id=$reissue_to, reissue_date=NOW() WHERE id=$original_issuance_id");
        
        $conn->commit();
        $_SESSION['success'] = "Item reissued successfully.";
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    unset($_SESSION['reissue_from_returned']);
    header('Location: ' . SITE_URL . '/admin/issue_items.php');
    exit();
}

// Return item with condition
if (isset($_POST['action']) && $_POST['action'] == 'return_item') {
    $issuance_id = (int)$_POST['issuance_id'];
    $condition_on_return = sanitize($_POST['condition_on_return']);
    
    $valid_conditions = ['Serviceable', 'Non-Serviceable', 'For Condemn', 'Under Repair'];
    if (!in_array($condition_on_return, $valid_conditions)) {
        echo json_encode(['success' => false, 'message' => 'Invalid condition selected.']);
        exit();
    }
    
    $issuance = $conn->query("
        SELECT ei.*, i.qty_physical_count, i.property_no
        FROM equipment_issuance ei 
        JOIN inventory i ON ei.inventory_id = i.id 
        WHERE ei.id=$issuance_id AND ei.status = 'issued'
    ")->fetch_assoc();
    
    if ($issuance) {
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE equipment_issuance SET status='returned', actual_return=NOW(), condition_on_return='$condition_on_return' WHERE id=$issuance_id");
            $new_quantity = $issuance['qty_physical_count'] + $issuance['quantity_issued'];
            $property_no_without_dept = removeDepartmentCodeFromPropertyNo($issuance['property_no']);
            $conn->query("UPDATE inventory SET qty_physical_count=$new_quantity, current_holder=NULL, property_no='$property_no_without_dept' WHERE id={$issuance['inventory_id']}");
            $conn->commit();
            echo json_encode(['success' => true, 'message' => 'Item returned successfully. Department code removed from property number.']);
        } catch (Exception $e) {
            $conn->rollback();
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Issuance not found or already returned.']);
    }
    exit();
}

// Reissue from returned item in history
if (isset($_GET['reissue_returned']) && is_numeric($_GET['reissue_returned'])) {
    $original_issuance_id = (int)$_GET['reissue_returned'];
    $check = $conn->query("SELECT status FROM equipment_issuance WHERE id=$original_issuance_id")->fetch_assoc();
    
    if (!$check) {
        $_SESSION['error'] = 'Issuance not found.';
    } elseif ($check['status'] !== 'returned') {
        $_SESSION['error'] = 'Only returned items can be reissued.';
    } else {
        $_SESSION['reissue_from_returned'] = $original_issuance_id;
    }
    
    header('Location: ' . SITE_URL . '/admin/issue_items.php');
    exit();
}

// Cancel reissue
if (isset($_GET['cancel_reissue'])) {
    unset($_SESSION['reissue_from_returned']);
    header('Location: ' . SITE_URL . '/admin/issue_items.php');
    exit();
}

// Print PAR
if (isset($_GET['print_par']) && is_numeric($_GET['print_par'])) {
    $issuance_id = (int)$_GET['print_par'];
    $par_query = $conn->query("
        SELECT ei.*, i.article_name, i.description, i.property_no, i.big_unit, i.small_unit, i.unit_value,
               CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
               CONCAT(issuer.firstname, ' ', issuer.lastname) as issued_by_name,
               sig.name as signatory_name
        FROM equipment_issuance ei
        JOIN inventory i ON ei.inventory_id = i.id
        JOIN employees e ON ei.issued_to = e.id
        JOIN users issuer ON ei.issued_by = issuer.id
        LEFT JOIN signatories sig ON ei.signatory_id = sig.id
        WHERE ei.id = $issuance_id
    ");
    
    if ($par_query && $par_query->num_rows > 0) {
        $item = $par_query->fetch_assoc();
        $unit_display = !empty($item['big_unit']) ? $item['big_unit'] : ($item['small_unit'] ?? 'pcs');
        $total_amount = $item['unit_value'] * $item['quantity_issued'];
        ?>
        <!DOCTYPE html><html><head><title>PAR</title><style>body{font-family:'Times New Roman',serif;padding:20px}.par-container{max-width:800px;margin:0 auto}.items-table{width:100%;border-collapse:collapse}.items-table th,.items-table td{border:1px solid #000;padding:8px;text-align:left}</style></head><body><div class="par-container"><h2 style="text-align:center">PROPERTY ACKNOWLEDGMENT RECEIPT</h2><p><strong>Item:</strong> <?php echo htmlspecialchars($item['article_name']); ?></p><p><strong>Property No.:</strong> <?php echo htmlspecialchars($item['property_no']); ?></p><p><strong>Quantity:</strong> <?php echo $item['quantity_issued'] . ' ' . $unit_display; ?></p><p><strong>Issued To:</strong> <?php echo htmlspecialchars($item['issued_to_name']); ?></p><p><strong>Issued By:</strong> <?php echo htmlspecialchars($item['issued_by_name']); ?></p><p><strong>Signatory:</strong> <?php echo htmlspecialchars($item['signatory_name'] ?? 'N/A'); ?></p><p><strong>Date:</strong> <?php echo date('F d, Y', strtotime($item['issued_date'])); ?></p><p><strong>Total Amount:</strong> ₱<?php echo number_format($total_amount, 2); ?></p></div></body></html>
        <?php
        exit;
    } else {
        $_SESSION['error'] = 'Issuance record not found.';
        header('Location: ' . SITE_URL . '/admin/issue_items.php');
        exit;
    }
}

// ============================================
// GET DATA
// ============================================

$departments_list = $conn->query("SELECT id, name, code FROM departments ORDER BY code, name");
$sections_list = $conn->query("SELECT s.id, s.name, d.name as department_name FROM sections s LEFT JOIN departments d ON s.department_id = d.id ORDER BY d.name, s.name");
$positions_list = $conn->query("SELECT DISTINCT position FROM employees WHERE position IS NOT NULL AND position != '' ORDER BY position");

// Get all employees for search - FIXED to include department
$all_employees = [];
$emp_result = $conn->query("
    SELECT e.id, e.firstname, e.lastname, e.position, 
           d.name as department_name, 
           d.code as department_code,
           s.name as section_name
    FROM employees e
    LEFT JOIN sections s ON e.section_id = s.id
    LEFT JOIN departments d ON s.department_id = d.id
    WHERE e.status = 'Active'
    ORDER BY e.lastname, e.firstname
");
if ($emp_result) {
    while ($emp = $emp_result->fetch_assoc()) {
        $all_employees[] = $emp;
    }
}

$current_issuances = $conn->query("
    SELECT ei.id, ei.inventory_id, ei.quantity_issued, ei.condition_on_issue,
           ei.remarks, ei.issued_date, ei.status, ei.issuance_barcode,
           i.article_name, i.property_no, i.big_unit, i.small_unit,
           CONCAT(e.firstname, ' ', e.lastname) as issued_to_name
    FROM equipment_issuance ei 
    INNER JOIN inventory i ON ei.inventory_id = i.id 
    INNER JOIN employees e ON ei.issued_to = e.id
    WHERE ei.status = 'issued'
    ORDER BY ei.issued_date DESC
");

$inventory_items = [];
$inv_result = $conn->query("SELECT * FROM inventory WHERE qty_physical_count > 0 ORDER BY article_name");
if ($inv_result) {
    while ($item = $inv_result->fetch_assoc()) {
        $inventory_items[] = $item;
    }
}

$signatories = $conn->query("SELECT * FROM signatories WHERE is_active = 1 ORDER BY name ASC");

$reissue_item = null; 
$reissue_from_id = null;
if (isset($_SESSION['reissue_from_returned'])) {
    $reissue_from_id = (int)$_SESSION['reissue_from_returned'];
    $reissue_item = $conn->query("
        SELECT ei.*, i.article_name, i.property_no, i.big_unit, i.small_unit, i.qty_physical_count as available_stock,
               CONCAT(e.firstname, ' ', e.lastname) as issued_to_name
        FROM equipment_issuance ei 
        JOIN inventory i ON ei.inventory_id=i.id 
        JOIN employees e ON ei.issued_to = e.id        
        WHERE ei.id=$reissue_from_id AND ei.status = 'returned'
    ")->fetch_assoc();
    if (!$reissue_item) unset($_SESSION['reissue_from_returned']);
}

include INCLUDE_PATH . '/header.php';
?>

<style>
:root {
    --primary: #6B8CFF;
    --secondary: #8FB5FF;
    --accent: #F8B0C0;
    --accent-light: #FFD8E0;
    --success-light: #C5E8C5;
    --light: #F8F9FA;
    --white: #FFFFFF;
    --border-light: #E0E0E0;
    --text-primary: #2C3E50;
    --text-secondary: #6B6B6B;
    --text-muted: #9E9E9E;
    --success: #4CAF50;
    --danger: #f44336;
    --warning: #FF9800;
    --info: #2196F3;
}

body {
    background: var(--light);
    color: var(--text-primary);
    font-family: 'Segoe UI', Arial, sans-serif;
}

/* Property Search Section */
.property-search-section {
    background: #fff;
    border-radius: 12px;
    padding: 15px;
    margin-bottom: 20px;
    border: 1px solid var(--border-light);
}

.property-search-box {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.property-search-box input {
    flex: 1;
    padding: 12px 15px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 14px;
    min-width: 200px;
}

.property-search-box button {
    background: var(--primary);
    color: white;
    border: none;
    padding: 12px 20px;
    border-radius: 8px;
    cursor: pointer;
}

.property-search-box button:hover {
    background: #5a7ae6;
}

.property-search-result {
    margin-top: 15px;
    display: none;
    max-height: 400px;
    overflow-y: auto;
}

.property-search-result.show {
    display: block;
}

.result-property-card {
    background: var(--light);
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
    border: 1px solid var(--border-light);
}

.result-property-info h5 {
    margin: 0 0 5px;
    color: var(--primary);
}

.result-property-info p {
    margin: 0;
    font-size: 12px;
    color: var(--text-muted);
}

.btn-add-property {
    background: var(--success);
    color: white;
    border: none;
    padding: 8px 16px;
    border-radius: 6px;
    cursor: pointer;
}

.btn-add-property:hover {
    background: #45a049;
}

/* Barcode Scanner Section */
.barcode-search-section {
    background: linear-gradient(135deg, #6B8CFF, #8FB5FF);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    color: #fff;
}

.barcode-search-section h4 {
    margin: 0 0 10px;
    font-size: 16px;
}

.barcode-search-box {
    display: flex;
    gap: 10px;
    align-items: center;
    background: #fff;
    border-radius: 50px;
    padding: 5px 5px 5px 20px;
}

.barcode-search-box input {
    flex: 1;
    border: none;
    padding: 12px 0;
    font-size: 16px;
    outline: none;
    background: transparent;
}

.barcode-search-box button {
    background: var(--accent);
    border: none;
    padding: 10px 25px;
    border-radius: 50px;
    color: var(--text-primary);
    font-weight: 500;
    cursor: pointer;
}

.barcode-search-result {
    background: rgba(255,255,255,.15);
    border-radius: 10px;
    padding: 15px;
    margin-top: 15px;
    display: none;
}

.barcode-search-result.show {
    display: block;
}

.result-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 15px;
    background: rgba(255,255,255,.1);
    padding: 12px;
    border-radius: 8px;
    margin-bottom: 10px;
}

.result-info h5 {
    margin: 0 0 5px;
    font-size: 16px;
}

.result-info p {
    margin: 0;
    font-size: 13px;
    opacity: .9;
}

.btn-add-item {
    background: #fff;
    color: var(--primary);
    border: none;
    padding: 8px 20px;
    border-radius: 6px;
    font-weight: 500;
    cursor: pointer;
}

/* Selected Items */
.selected-items-grid {
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    background: var(--white);
}

.selected-item-card {
    background: #fff;
    border: 1px solid var(--border-light);
    border-radius: 10px;
    padding: 12px;
    margin-bottom: 10px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.selected-item-info {
    flex: 2;
}

.item-name {
    font-weight: 600;
    color: var(--primary);
}

.item-property {
    font-size: 11px;
    color: var(--text-muted);
    font-family: monospace;
}

.selected-item-qty {
    display: flex;
    align-items: center;
    gap: 10px;
}

.selected-item-qty input {
    width: 80px;
    padding: 6px 10px;
    border: 1px solid var(--border-light);
    border-radius: 6px;
    text-align: center;
}

.btn-remove-item {
    background: var(--danger);
    color: #fff;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    cursor: pointer;
}

.empty-cart {
    text-align: center;
    padding: 40px;
    color: var(--text-muted);
}

.empty-cart i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: .5;
}

.cart-summary {
    margin-top: 15px;
    padding-top: 15px;
    border-top: 2px solid var(--accent-light);
    text-align: right;
    font-weight: bold;
    color: var(--primary);
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
    color: #fff;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
}

.action-btn.view {
    background: #6B8CFF;
}

.action-btn.print {
    background: #2c3e50;
}

.return-btn {
    background: #FF9800;
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 11px;
}

.reissue-btn {
    background: linear-gradient(135deg, #6B8CFF, #8FB5FF);
    color: white;
    border: none;
    padding: 6px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 11px;
    text-decoration: none;
    display: inline-block;
}

/* Form Elements */
.issue-form-container {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
}

.issue-form-container h3 {
    margin: 0 0 20px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-light);
    color: var(--primary);
    font-size: 18px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-primary);
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

select.form-control {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%236B6B6B' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.btn-primary {
    background: var(--accent);
    color: var(--text-primary);
    border: none;
    padding: 12px 24px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: 600;
    font-size: 14px;
}

.btn-primary:hover {
    background: #e69eb0;
    transform: translateY(-1px);
}

.btn-secondary {
    background: #6c757d;
    color: white;
    border: none;
    padding: 8px 15px;
    border-radius: 6px;
    cursor: pointer;
}

.alert-warning {
    background: #FFF3E0;
    border-left: 4px solid #FF9800;
    padding: 15px;
    margin-bottom: 20px;
    border-radius: 8px;
}

/* Table Styles */
.table-container {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
    overflow-x: auto;
}

.table-header {
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-light);
}

.table-header h2 {
    color: var(--primary);
    font-size: 18px;
    margin: 0;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 12px 10px;
    text-align: left;
    border-bottom: 1px solid var(--border-light);
}

th {
    background: #F0F0F0;
    font-weight: 600;
    color: var(--text-primary);
}

.text-center {
    text-align: center;
}

.text-muted {
    color: var(--text-muted);
}

/* Barcode Image */
.barcode-img {
    max-width: 100px;
    height: auto;
    border: 1px solid var(--border-light);
    padding: 5px;
    border-radius: 6px;
    background: var(--white);
    cursor: pointer;
}

/* Employee Search */
.employee-search-section {
    border: 1px solid var(--border-light);
    border-radius: 8px;
    background: #fff;
    overflow: hidden;
}

.employee-search-bar {
    display: flex;
    gap: 10px;
    padding: 15px;
    background: var(--light);
    border-bottom: 1px solid var(--border-light);
    flex-wrap: wrap;
    align-items: flex-end;
}

.search-group {
    flex: 1;
    min-width: 150px;
}

.search-group label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 5px;
    text-transform: uppercase;
}

.search-group input,
.search-group select {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid var(--border-light);
    border-radius: 6px;
    font-size: 13px;
    box-sizing: border-box;
}

.btn-search-employee {
    background: var(--primary);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 6px;
    cursor: pointer;
}

.btn-clear-employee {
    background: #f5f5f5;
    border: 1px solid var(--border-light);
    padding: 8px 15px;
    border-radius: 6px;
    cursor: pointer;
}

.employee-results-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}

.employee-results-table th {
    background: var(--light);
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    color: var(--primary);
    font-size: 11px;
    border-bottom: 2px solid var(--accent-light);
}

.employee-results-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border-light);
}

.employee-result-row {
    cursor: pointer;
    transition: background 0.2s;
}

.employee-result-row:hover {
    background-color: var(--light);
}

.employee-result-row.selected {
    background-color: var(--accent-light);
}

.select-employee-btn {
    background: var(--success);
    color: white;
    border: none;
    padding: 5px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
}

.selected-employee-info {
    margin-top: 10px;
    padding: 12px 15px;
    background: var(--success-light);
    border-radius: 6px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.no-results {
    text-align: center;
    padding: 40px;
    color: var(--text-muted);
}

.no-results i {
    font-size: 48px;
    margin-bottom: 10px;
    display: block;
}

/* Status/Condition Badge */
.condition-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.condition-Serviceable {
    background: #C8E6C9;
    color: #2E7D32;
}

.condition-Non-Serviceable {
    background: #FFCDD2;
    color: #C62828;
}

.condition-For-Condemn {
    background: #FFF3E0;
    color: #E65100;
}

.condition-Under-RePair {
    background: #BBDEFB;
    color: #1565C0;
}

.issue-status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.issue-status-issued {
    background: #FFF3E0;
    color: #FF9800;
}

.issue-status-reissued {
    background: #E3F2FD;
    color: #2196F3;
}

.issue-status-returned {
    background: #E8F5E9;
    color: #4CAF50;
}

.property-dept-code {
    font-size: 10px;
    background: var(--accent-light);
    padding: 2px 6px;
    border-radius: 12px;
    margin-left: 5px;
}

/* Reissue Form */
.reissue-form-container {
    background: #fff;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
    border: 2px solid var(--primary);
}

.reissue-form-container h3 {
    color: var(--primary);
    margin: 0 0 15px 0;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-light);
}

.reissue-item-details {
    background: var(--light);
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.reissue-item-details p {
    margin: 5px 0;
}

/* Return Modal */
.return-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    justify-content: center;
    align-items: center;
}

.return-modal.show {
    display: flex;
}

.return-modal-content {
    background: white;
    border-radius: 16px;
    width: 450px;
    max-width: 90%;
    padding: 25px;
}

.return-modal-header {
    border-bottom: 2px solid var(--accent-light);
    padding-bottom: 15px;
    margin-bottom: 20px;
}

.return-modal-header h3 {
    color: var(--primary);
    margin: 0;
}

.return-modal-body {
    margin-bottom: 20px;
}

.return-modal-footer {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    border-top: 1px solid var(--border-light);
    padding-top: 20px;
}

.return-condition-select {
    width: 100%;
    padding: 12px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 14px;
    margin-top: 10px;
}

/* View Modal */
.view-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10002;
    justify-content: center;
    align-items: center;
}

.view-modal.show {
    display: flex;
}

.view-modal-content {
    background: white;
    border-radius: 16px;
    width: 600px;
    max-width: 90%;
    max-height: 80vh;
    overflow-y: auto;
}

.view-modal-header {
    border-bottom: 2px solid var(--accent-light);
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.view-modal-header h3 {
    color: var(--primary);
    margin: 0;
}

.view-modal-close {
    cursor: pointer;
    font-size: 24px;
    color: var(--text-muted);
}

.view-modal-body {
    padding: 20px;
}

.view-detail-row {
    display: flex;
    margin-bottom: 12px;
    border-bottom: 1px solid var(--border-light);
    padding-bottom: 8px;
}

.view-detail-label {
    width: 35%;
    font-weight: 600;
    color: var(--text-secondary);
}

.view-detail-value {
    width: 65%;
    color: var(--text-primary);
}

/* Barcode Modal */
.barcode-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10003;
    justify-content: center;
    align-items: center;
}

.barcode-modal.show {
    display: flex;
}

.barcode-modal-content {
    background: white;
    border-radius: 16px;
    width: 500px;
    max-width: 90%;
    text-align: center;
}

.barcode-modal-header {
    border-bottom: 2px solid var(--accent-light);
    padding: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.barcode-modal-header h3 {
    color: var(--primary);
    margin: 0;
}

.barcode-modal-close {
    cursor: pointer;
    font-size: 24px;
    color: var(--text-muted);
}

.barcode-modal-body {
    padding: 30px;
}

.barcode-image {
    margin-bottom: 20px;
}

.barcode-value {
    font-family: monospace;
    font-size: 14px;
    background: var(--light);
    padding: 10px;
    border-radius: 8px;
    word-break: break-all;
}

.barcode-modal-footer {
    padding: 20px;
    border-top: 1px solid var(--border-light);
    display: flex;
    gap: 10px;
    justify-content: center;
}

/* Confirm Modal */
.confirm-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 10000;
    justify-content: center;
    align-items: center;
}

.confirm-modal.show {
    display: flex;
}

.confirm-modal-content {
    background: white;
    border-radius: 16px;
    width: 400px;
    max-width: 90%;
    padding: 25px;
    text-align: center;
}

.confirm-modal-icon {
    font-size: 60px;
    margin-bottom: 15px;
    color: #FF9800;
}

.confirm-modal h3 {
    color: #3A3A3A;
    font-size: 20px;
    margin-bottom: 10px;
}

.confirm-modal p {
    color: #6B6B6B;
    font-size: 14px;
    margin-bottom: 25px;
}

.confirm-modal-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
}

.confirm-btn-cancel {
    background: #f5f5f5;
    border: none;
    padding: 10px 25px;
    border-radius: 40px;
    cursor: pointer;
}

.confirm-btn-confirm {
    background: linear-gradient(135deg, #6B8CFF 0%, #8FB5FF 100%);
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 40px;
    cursor: pointer;
}

/* Sticky Scan Button */
.sticky-scan-button-container {
    position: fixed;
    bottom: 30px;
    right: 30px;
    z-index: 1000;
}

.sticky-scan-button {
    padding: 16px 32px;
    background: linear-gradient(135deg, var(--accent) 0%, #e69eb0 100%);
    color: var(--text-primary);
    font-weight: bold;
    font-size: 16px;
    border-radius: 60px;
    border: none;
    cursor: pointer;
    box-shadow: 0 10px 30px rgba(248,176,192,0.6);
    display: flex;
    align-items: center;
    gap: 10px;
    text-decoration: none;
}

.sticky-scan-button i {
    font-size: 20px;
}

.sticky-scan-button:hover {
    transform: translateY(-5px) scale(1.05);
    box-shadow: 0 20px 40px rgba(248,176,192,0.8);
}

/* Alert Messages */
.alert-success {
    background: #ECFDF5;
    color: #059669;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #10B981;
}

.alert-danger {
    background: #FEF2F2;
    color: #DC2626;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    border-left: 4px solid #EF4444;
}

/* Responsive */
@media (max-width: 768px) {
    .employee-search-bar {
        flex-direction: column;
        align-items: stretch;
    }
    
    .search-group {
        width: 100%;
    }
    
    .property-search-box {
        flex-direction: column;
    }
    
    .property-search-box input {
        width: 100%;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .barcode-search-box {
        flex-direction: column;
        border-radius: 12px;
        padding: 15px;
    }
    
    .barcode-search-box input {
        width: 100%;
        border: 1px solid #ddd;
        padding: 10px;
        border-radius: 8px;
    }
    
    .selected-item-card {
        flex-direction: column;
        text-align: center;
    }
    
    .selected-item-qty {
        justify-content: center;
    }
    
    .sticky-scan-button-container {
        bottom: 20px;
        right: 20px;
    }
    
    .sticky-scan-button {
        padding: 12px 24px;
        font-size: 14px;
    }
    
    .sticky-scan-button i {
        font-size: 16px;
    }
}
</style>

<!-- Display Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<!-- ============================================ -->
<!-- REISSUE FORM -->
<!-- ============================================ -->
<?php if ($reissue_item): ?>
<div class="reissue-form-container">
    <h3><i class="fas fa-redo-alt"></i> Reissue Returned Item</h3>
    <div class="reissue-item-details">
        <p><strong>Item:</strong> <?php echo htmlspecialchars($reissue_item['article_name']); ?></p>
        <p><strong>Property No.:</strong> <?php echo htmlspecialchars($reissue_item['property_no']); ?></p>
        <p><strong>Previous Holder:</strong> <?php echo htmlspecialchars($reissue_item['issued_to_name']); ?></p>
        <p><strong>Quantity:</strong> <?php echo $reissue_item['quantity_issued']; ?></p>
    </div>
    <form method="POST" action="" id="reissueForm">
        <input type="hidden" name="action" value="reissue">
        <input type="hidden" name="original_issuance_id" value="<?php echo $reissue_from_id; ?>">
        
        <div class="form-group">
            <label>Reissue To: <span class="text-danger">*</span></label>
            <div class="employee-search-section">
                <div class="employee-search-bar">
                    <div class="search-group"><label>NAME</label><input type="text" id="reissue_search_employee_name" placeholder="Search by name..." onkeyup="searchReissueEmployees()"></div>
                    <div class="search-group"><label>DEPARTMENT</label><select id="reissue_search_department" onchange="searchReissueEmployees()"><option value="">All</option><?php if($departments_list) while($dept = $departments_list->fetch_assoc()) echo '<option value="'.htmlspecialchars($dept['name']).'">'.htmlspecialchars($dept['code'].' - '.$dept['name']).'</option>'; ?></select></div>
                    <div class="search-group"><label>SECTION</label><select id="reissue_search_section" onchange="searchReissueEmployees()"><option value="">All</option><?php if($sections_list) while($sec = $sections_list->fetch_assoc()) echo '<option value="'.htmlspecialchars($sec['name']).'">'.htmlspecialchars($sec['name']).'</option>'; ?></select></div>
                    <div class="search-group"><label>POSITION</label><select id="reissue_search_position" onchange="searchReissueEmployees()"><option value="">All</option><?php if($positions_list) while($pos = $positions_list->fetch_assoc()) echo '<option value="'.htmlspecialchars($pos['position']).'">'.htmlspecialchars($pos['position']).'</option>'; ?></select></div>
                    <div class="search-group" style="flex:0.3;"><label>&nbsp;</label><button type="button" class="btn-clear-employee" onclick="clearReissueEmployeeSearch()">Clear</button></div>
                </div>
                <div id="reissue_employee_results_container" style="max-height:300px;overflow-y:auto"><table class="employee-results-table"><thead><tr><th>Name</th><th>Position</th><th>Department</th><th>Action</th></tr></thead><tbody id="reissue_employee_results_body"><tr><td colspan="4" class="no-results">Type to search</div></div></tbody>}</div>
            </div>
            <div id="selected_reissue_employee_display" style="display:none;margin-top:10px;padding:12px;background:var(--success-light);border-radius:8px"><i class="fas fa-user-check"></i> Selected: <strong id="selected_reissue_employee_name"></strong></div>
            <input type="hidden" id="selected_reissue_employee_id" name="reissue_to" value="">
        </div>
        
        <div class="form-group">
            <label>Condition <span class="text-danger">*</span></label>
            <select name="condition" id="reissue_condition" class="form-control" required>
                <option value="">-- Select --</option>
                <option value="Serviceable">Serviceable</option>
                <option value="Non-Serviceable">Non-Serviceable</option>
                <option value="For Condemn">For Condemn</option>
                <option value="Under Repair">Under Repair</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Signatory</label>
            <select name="signatory_id" id="reissue_signatory_id" class="form-control">
                <option value="">-- Optional --</option>
                <?php if($signatories) while($sig = $signatories->fetch_assoc()) echo '<option value="'.$sig['id'].'">'.htmlspecialchars($sig['name']).'</option>'; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Remarks</label>
            <textarea name="remarks" id="reissue_remarks" class="form-control" rows="2"></textarea>
        </div>
        
        <div class="form-group">
            <button type="button" class="btn-primary" onclick="confirmReissueSubmit()">Confirm Reissue</button>
            <a href="?cancel_reissue=1" class="btn-secondary" style="display:inline-block;padding:12px 24px;background:#6c757d;color:white;text-decoration:none;border-radius:8px;margin-left:10px">Cancel</a>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- ============================================ -->
<!-- NEW ISSUANCE FORM -->
<!-- ============================================ -->
<div class="issue-form-container">
    <h3><i class="fas fa-hand-holding"></i> Issue New Item</h3>
    
    <!-- Property Search Section -->
    <div class="property-search-section">
        <h4>Search Items by Property Number or Article Name</h4>
        <div class="property-search-box">
            <input type="text" id="property_search_input" placeholder="Property number or article name...">
            <button type="button" onclick="searchByProperty()">Search</button>
            <button type="button" onclick="clearPropertySearch()" style="background:#6c757d">Clear</button>
        </div>
        <div id="property_search_result" class="property-search-result"></div>
    </div>

    <div class="barcode-search-section">
        <h4>Search by Barcode</h4>
        <div class="barcode-search-box">
            <i class="fas fa-search"></i>
            <input type="text" id="barcode_input" placeholder="Enter property number or barcode..." autocomplete="off">
            <button type="button" onclick="searchBarcode()">Search</button>
        </div>
        <div id="barcode_result" class="barcode-search-result"></div>
    </div>
    
    <form method="POST" action="" id="issueForm">
        <input type="hidden" name="action" value="new_issue">
        
        <div class="form-group">
            <label>Selected Items</label>
            <div class="selected-items-grid">
                <div class="empty-cart" id="emptyCartMessage"><i class="fas fa-box"></i><p>No items selected. Search above to add items.</p></div>
                <div id="selectedItemsList"></div>
                <div id="cartSummary" class="cart-summary" style="display:none"></div>
            </div>
        </div>
        
        <!-- ISSUE TO SECTION -->
        <div class="form-group">
            <label><i class="fas fa-user-tie"></i> Issue To: <span class="text-danger">*</span></label>
            
            <div class="employee-search-section">
                <div class="employee-search-bar">
                    <div class="search-group">
                        <label>NAME</label>
                        <input type="text" id="search_employee_name" placeholder="Search by name..." onkeyup="searchEmployees()">
                    </div>
                    <div class="search-group">
                        <label>DEPARTMENT</label>
                        <select id="search_department" onchange="searchEmployees()">
                            <option value="">All Departments</option>
                            <?php if($departments_list) while($dept = $departments_list->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($dept['name']); ?>"><?php echo htmlspecialchars($dept['code'] . ' - ' . $dept['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="search-group">
                        <label>SECTION</label>
                        <select id="search_section" onchange="searchEmployees()">
                            <option value="">All Sections</option>
                            <?php if($sections_list) while($sec = $sections_list->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($sec['name']); ?>"><?php echo htmlspecialchars($sec['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="search-group">
                        <label>POSITION</label>
                        <select id="search_position" onchange="searchEmployees()">
                            <option value="">All Positions</option>
                            <?php if($positions_list) while($pos = $positions_list->fetch_assoc()): ?>
                            <option value="<?php echo htmlspecialchars($pos['position']); ?>"><?php echo htmlspecialchars($pos['position']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="search-group" style="flex: 0.3;">
                        <label>&nbsp;</label>
                        <button type="button" class="btn-search-employee" onclick="searchEmployees()">
                            <i class="fas fa-search"></i> Search
                        </button>
                    </div>
                    <div class="search-group" style="flex: 0.3;">
                        <label>&nbsp;</label>
                        <button type="button" class="btn-clear-employee" onclick="clearEmployeeSearch()">
                            <i class="fas fa-times"></i> Clear
                        </button>
                    </div>
                </div>
                
                <div id="employee_results_container" style="max-height: 300px; overflow-y: auto;">
                    <table class="employee-results-table" style="width: 100%;">
                        <thead>
                            <tr>
                                <th>Employee Name</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody id="employee_results_body">
                            <tr>
                                <td colspan="4" class="no-results">
                                    <i class="fas fa-search"></i>
                                    <p>Click "Search" to find employees</p>
                                </div>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div id="selected_employee_display" style="display: none; margin-top: 10px; padding: 12px 15px; background: var(--success-light); border-radius: 8px;">
                <i class="fas fa-user-check"></i> Selected: <strong id="selected_employee_name"></strong>
            </div>
            <input type="hidden" id="selected_employee_id" name="issued_to" value="">
        </div>
        
        <div class="form-group">
            <label>Condition <span class="text-danger">*</span></label>
            <select name="condition" id="condition" class="form-control" required>
                <option value="">-- Select --</option>
                <option value="Serviceable">Serviceable</option>
                <option value="Non-Serviceable">Non-Serviceable</option>
                <option value="For Condemn">For Condemn</option>
                <option value="Under Repair">Under Repair</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Signatory</label>
            <select name="signatory_id" id="signatory_id" class="form-control">
                <option value="">-- Optional --</option>
                <?php if($signatories) while($sig = $signatories->fetch_assoc()) echo '<option value="'.$sig['id'].'">'.htmlspecialchars($sig['name']).'</option>'; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Remarks</label>
            <textarea name="remarks" id="remarks" class="form-control" rows="2" placeholder="Optional notes"></textarea>
        </div>
        
        <div class="form-group">
            <button type="button" class="btn-primary" id="submitBtn" onclick="showConfirmModal()">Issue Selected Items (<span id="selectedCount">0</span>)</button>
        </div>
    </form>
</div>

<!-- ============================================ -->
<!-- CURRENTLY ISSUED ITEMS -->
<!-- ============================================ -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-clipboard-list"></i> Currently Issued Items</h2>
    </div>
    <?php if($current_issuances && $current_issuances->num_rows > 0): ?>
    <div style="overflow-x: auto;">
        <table style="width: 100%; min-width: 1200px;">
            <thead>
                <tr>
                    <th>Barcode</th>
                    <th>Date</th>
                    <th>Item Name</th>
                    <th>Property No.</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Issued To</th>
                    <th>Condition</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($item = $current_issuances->fetch_assoc()): 
                    $unit_display = !empty($item['big_unit']) ? $item['big_unit'] : ($item['small_unit'] ?? 'pcs');
                    $condition_class = 'condition-' . str_replace(' ', '', $item['condition_on_issue'] ?? 'Serviceable');
                    $safe_name = addslashes($item['article_name']);
                ?>
                <tr>
                    <td>
                        <?php if(!empty($item['issuance_barcode'])): ?>
                        <img src="<?php echo SITE_URL; ?>/admin/barcode_generator_issued.php?code=<?php echo urlencode($item['issuance_barcode']); ?>&width=150&height=50" class="barcode-img" onclick="showBarcodeModal('<?php echo htmlspecialchars($item['issuance_barcode']); ?>', '<?php echo $safe_name; ?>')">
                        <br><small><?php echo htmlspecialchars($item['issuance_barcode']); ?></small>
                        <?php else: ?>—<?php endif; ?>
                    </div>
                    <td style="white-space: nowrap;"><?php echo date('M d, Y', strtotime($item['issued_date'])); ?></div>
                    <td><strong><?php echo htmlspecialchars($item['article_name']); ?></strong></div>
                    <td><code><?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?></code></div>
                    <td><?php echo $item['quantity_issued']; ?></div>
                    <td><?php echo htmlspecialchars($unit_display); ?></div>
                    <td><?php echo htmlspecialchars($item['issued_to_name']); ?></div>
                    <td><span class="condition-badge <?php echo $condition_class; ?>"><?php echo htmlspecialchars($item['condition_on_issue'] ?? 'Serviceable'); ?></span></div>
                    <td>
                        <div class="action-buttons">
                            <a href="?print_par=<?php echo $item['id']; ?>" class="action-btn print" target="_blank"><i class="fas fa-print"></i></a>
                            <button class="return-btn" onclick="openReturnModal(<?php echo $item['id']; ?>, '<?php echo $safe_name; ?>')"><i class="fas fa-undo"></i> Return</button>
                            <button class="action-btn view" onclick="viewIssuanceDetails(<?php echo $item['id']; ?>)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:60px"><i class="fas fa-inbox" style="font-size:64px;color:#ccc"></i><p>No items currently issued</p></div>
    <?php endif; ?>
</div>

<!-- ============================================ -->
<!-- ISSUANCE HISTORY -->
<!-- ============================================ -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-history"></i> Issuance History</h2>
    </div>
    <?php
    $history = $conn->query("
        SELECT 
            ei.*, 
            i.article_name, 
            i.property_no, 
            i.big_unit, 
            i.small_unit,
            CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
            CONCAT(ub.firstname, ' ', ub.lastname) as issued_by_name,
            CONCAT(original_emp.firstname, ' ', original_emp.lastname) as reissued_from_name,
            CONCAT(reissued_to_emp.firstname, ' ', reissued_to_emp.lastname) as reissued_to_name
        FROM equipment_issuance ei 
        JOIN inventory i ON ei.inventory_id = i.id 
        JOIN employees e ON ei.issued_to = e.id
        JOIN users ub ON ei.issued_by = ub.id 
        LEFT JOIN equipment_issuance original_iss ON ei.reissued_from_id = original_iss.id
        LEFT JOIN employees original_emp ON original_iss.issued_to = original_emp.id
        LEFT JOIN equipment_issuance reissued_iss ON ei.id = reissued_iss.reissued_from_id
        LEFT JOIN employees reissued_to_emp ON reissued_iss.issued_to = reissued_to_emp.id
        ORDER BY ei.issued_date DESC LIMIT 100
    ");
    ?>
    <?php if($history && $history->num_rows > 0): ?>
    <div style="overflow-x: auto;">
        <table style="width: 100%; min-width: 1400px;">
            <thead>
                <tr>
                    <th>Barcode</th>
                    <th>Date</th>
                    <th>Item Name</th>
                    <th>Property No.</th>
                    <th>Qty</th>
                    <th>Unit</th>
                    <th>Issued To</th>
                    <th>Condition</th>
                    <th>Issued By</th>
                    <th>Reissued From</th>
                    <th>Reissued To</th>
                    <th>Status</th>
                    <th>Return Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($item = $history->fetch_assoc()): 
                    $unit_display = !empty($item['big_unit']) ? $item['big_unit'] : ($item['small_unit'] ?? 'pcs');
                    $condition_class = 'condition-' . str_replace(' ', '', $item['condition_on_issue'] ?? 'Serviceable');
                    $status_class = 'issue-status-' . $item['status'];
                    $safe_name = addslashes($item['article_name']);
                ?>
                <tr>
                    <td>
                        <?php if(!empty($item['issuance_barcode'])): ?>
                        <img src="<?php echo SITE_URL; ?>/admin/barcode_generator_issued.php?code=<?php echo urlencode($item['issuance_barcode']); ?>&width=100&height=30" class="barcode-img" onclick="showBarcodeModal('<?php echo htmlspecialchars($item['issuance_barcode']); ?>', '<?php echo $safe_name; ?>')">
                        <br><small><?php echo htmlspecialchars(substr($item['issuance_barcode'], 0, 15)); ?>...</small>
                        <?php else: ?>—<?php endif; ?>
                    </div>
                    <td style="white-space: nowrap;"><?php echo date('M d, Y', strtotime($item['issued_date'])); ?></div>
                    <td><strong><?php echo htmlspecialchars($item['article_name']); ?></strong></div>
                    <td><code><?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?></code></div>
                    <td><?php echo $item['quantity_issued']; ?></div>
                    <td><?php echo htmlspecialchars($unit_display); ?></div>
                    <td><?php echo htmlspecialchars($item['issued_to_name']); ?></div>
                    <td><span class="condition-badge <?php echo $condition_class; ?>"><?php echo htmlspecialchars($item['condition_on_issue'] ?? 'Serviceable'); ?></span></div>
                    <td><?php echo htmlspecialchars($item['issued_by_name']); ?></div>
                    <td>
                        <?php 
                        if(!empty($item['reissued_from_name']) && $item['reissued_from_name'] != '—') {
                            echo '<span style="color:#FF9800;">' . htmlspecialchars($item['reissued_from_name']) . '</span>';
                        } else {
                            echo '—';
                        }
                        ?>
                    </div>
                    <td>
                        <?php 
                        if(!empty($item['reissued_to_name']) && $item['reissued_to_name'] != '—') {
                            echo '<span style="color:#4CAF50;">' . htmlspecialchars($item['reissued_to_name']) . '</span>';
                        } else {
                            echo '—';
                        }
                        ?>
                    </div>
                    <td><span class="issue-status-badge <?php echo $status_class; ?>"><?php echo ucfirst($item['status']); ?></span></div>
                    <td><?php echo $item['actual_return'] ? date('M d, Y', strtotime($item['actual_return'])) : '—'; ?></div>
                    <td>
                        <div class="action-buttons">
                            <a href="?print_par=<?php echo $item['id']; ?>" class="action-btn print" target="_blank"><i class="fas fa-print"></i></a>
                            <?php if($item['status'] == 'returned'): ?>
                            <a href="?reissue_returned=<?php echo $item['id']; ?>" class="reissue-btn" onclick="return confirmReissueReturned(event, this)"><i class="fas fa-redo-alt"></i> Reissue</a>
                            <?php endif; ?>
                            <button class="action-btn view" onclick="viewIssuanceDetails(<?php echo $item['id']; ?>)"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:60px"><i class="fas fa-inbox" style="font-size:64px;color:#ccc"></i><p>No issuance records found</p></div>
    <?php endif; ?>
</div>

<!-- Sticky Scan Button -->
<div class="sticky-scan-button-container">
    <a href="<?php echo SITE_URL; ?>/admin/barcode_generator_issued.php" target="_blank" class="sticky-scan-button">
        <i class="fas fa-camera"></i> SCAN BARCODE
    </a>
</div>

<!-- Return Modal -->
<div id="returnModal" class="return-modal">
    <div class="return-modal-content">
        <div class="return-modal-header"><h3>Return Item</h3></div>
        <div class="return-modal-body">
            <p><strong>Item:</strong> <span id="return_item_name"></span></p>
            <p><strong>Note:</strong> Department code will be removed from property number.</p>
            <label>Condition on Return: <span class="text-danger">*</span></label>
            <select id="return_condition" class="return-condition-select">
                <option value="">-- Select --</option>
                <option value="Serviceable">Serviceable</option>
                <option value="Non-Serviceable">Non-Serviceable</option>
                <option value="For Condemn">For Condemn</option>
                <option value="Under Repair">Under Repair</option>
            </select>
        </div>
        <div class="return-modal-footer">
            <button class="confirm-btn-cancel" onclick="closeReturnModal()">Cancel</button>
            <button class="confirm-btn-confirm" onclick="submitReturn()">Confirm Return</button>
        </div>
    </div>
</div>

<!-- View Modal -->
<div id="viewModal" class="view-modal">
    <div class="view-modal-content">
        <div class="view-modal-header">
            <h3><i class="fas fa-info-circle"></i> Issuance Details</h3>
            <span class="view-modal-close" onclick="closeViewModal()">&times;</span>
        </div>
        <div class="view-modal-body" id="viewModalBody">
            <div style="text-align:center;padding:20px"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
    </div>
</div>

<!-- Barcode Modal -->
<div id="barcodeModal" class="barcode-modal">
    <div class="barcode-modal-content">
        <div class="barcode-modal-header">
            <h3><i class="fas fa-barcode"></i> <span id="barcodeModalTitle">Barcode</span></h3>
            <span class="barcode-modal-close" onclick="closeBarcodeModal()">&times;</span>
        </div>
        <div class="barcode-modal-body">
            <div class="barcode-image" id="barcodeModalImage"></div>
            <div class="barcode-value" id="barcodeModalValue"></div>
        </div>
        <div class="barcode-modal-footer">
            <button class="btn-primary" onclick="printBarcodeFromModal()"><i class="fas fa-print"></i> Print Barcode</button>
            <button class="btn-secondary" onclick="closeBarcodeModal()">Close</button>
        </div>
    </div>
</div>

<!-- Confirm Modals -->
<div id="confirmModal" class="confirm-modal"><div class="confirm-modal-content"><div class="confirm-modal-icon"><i class="fas fa-question-circle"></i></div><h3>Confirm Issuance</h3><p id="confirmModalMessage"></p><div class="confirm-modal-buttons"><button class="confirm-btn-cancel" onclick="closeConfirmModal()">Cancel</button><button class="confirm-btn-confirm" onclick="submitForm()">Confirm</button></div></div></div>
<div id="confirmReissueModal" class="confirm-modal"><div class="confirm-modal-content"><div class="confirm-modal-icon"><i class="fas fa-redo-alt"></i></div><h3>Confirm Reissue</h3><p id="confirmReissueModalMessage"></p><div class="confirm-modal-buttons"><button class="confirm-btn-cancel" onclick="closeConfirmReissueModal()">Cancel</button><button class="confirm-btn-confirm" onclick="submitReissueForm()">Confirm</button></div></div></div>

<script>
// Store data
const inventoryData = <?php echo json_encode($inventory_items); ?>;
const allEmployees = <?php echo json_encode($all_employees); ?>;
let cartItems = [];
let selectedEmployeeId = null;
let selectedReissueEmployeeId = null;
let currentReturnId = null;

// ============================================
// EMPLOYEE SEARCH FUNCTIONS - FIXED
// ============================================

function searchEmployees() {
    const name = document.getElementById('search_employee_name').value.toLowerCase().trim();
    const department = document.getElementById('search_department').value;
    const section = document.getElementById('search_section').value;
    const position = document.getElementById('search_position').value;
    
    let filtered = allEmployees.filter(emp => {
        const fullName = (emp.firstname + ' ' + emp.lastname).toLowerCase();
        if (name && !fullName.includes(name)) return false;
        if (department && emp.department_name !== department) return false;
        if (section && emp.section_name !== section) return false;
        if (position && emp.position !== position) return false;
        return true;
    });
    displayEmployeeResults(filtered);
}

function displayEmployeeResults(employees) {
    const tbody = document.getElementById('employee_results_body');
    if (employees.length === 0) {
        tbody.innerHTML = '</table><td colspan="4" class="no-results">No employees found</div></div>';
        return;
    }
    let html = '';
    employees.forEach(emp => {
        const isSelected = (selectedEmployeeId == emp.id);
        const deptDisplay = emp.department_name && emp.department_name !== '—' ? emp.department_name : '—';
        html += `<tr class="employee-result-row ${isSelected ? 'selected' : ''}" onclick="selectEmployee(${emp.id}, '${escapeHtml(emp.firstname + ' ' + emp.lastname)}', '${escapeHtml(emp.position || '')}', '${escapeHtml(deptDisplay)}')">
            <td>${escapeHtml(emp.lastname + ', ' + emp.firstname)}</div>
            <td>${escapeHtml(emp.position || '—')}</div>
            <td>${escapeHtml(deptDisplay)}</div>
            <td><button class="select-employee-btn" onclick="event.stopPropagation();selectEmployee(${emp.id}, '${escapeHtml(emp.firstname + ' ' + emp.lastname)}', '${escapeHtml(emp.position || '')}', '${escapeHtml(deptDisplay)}')">Select</button></div>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function selectEmployee(id, name, position, department) {
    selectedEmployeeId = id;
    document.getElementById('selected_employee_id').value = id;
    let displayText = name;
    if (position) displayText += ` - ${position}`;
    if (department && department !== '—') displayText += ` (${department})`;
    document.getElementById('selected_employee_name').innerHTML = displayText;
    document.getElementById('selected_employee_display').style.display = 'flex';
    document.querySelectorAll('#employee_results_body .employee-result-row').forEach(row => row.classList.remove('selected'));
    if (event && event.target) {
        const row = event.target.closest('.employee-result-row');
        if (row) row.classList.add('selected');
    }
}

function clearEmployeeSearch() {
    document.getElementById('search_employee_name').value = '';
    document.getElementById('search_department').value = '';
    document.getElementById('search_section').value = '';
    document.getElementById('search_position').value = '';
    document.getElementById('employee_results_body').innerHTML = '<tr><td colspan="4" class="no-results">Type to search</div></div>';
}

// ============================================
// REISSUE EMPLOYEE SEARCH
// ============================================

function searchReissueEmployees() {
    const name = document.getElementById('reissue_search_employee_name').value.toLowerCase().trim();
    const department = document.getElementById('reissue_search_department').value;
    const section = document.getElementById('reissue_search_section').value;
    const position = document.getElementById('reissue_search_position').value;
    
    let filtered = allEmployees.filter(emp => {
        const fullName = (emp.firstname + ' ' + emp.lastname).toLowerCase();
        if (name && !fullName.includes(name)) return false;
        if (department && emp.department_name !== department) return false;
        if (section && emp.section_name !== section) return false;
        if (position && emp.position !== position) return false;
        return true;
    });
    displayReissueEmployeeResults(filtered);
}

function displayReissueEmployeeResults(employees) {
    const tbody = document.getElementById('reissue_employee_results_body');
    if (employees.length === 0) {
        tbody.innerHTML = '<td><td colspan="4" class="no-results">No employees found</div></div>';
        return;
    }
    let html = '';
    employees.forEach(emp => {
        const isSelected = (selectedReissueEmployeeId == emp.id);
        const deptDisplay = emp.department_name && emp.department_name !== '—' ? emp.department_name : '—';
        html += `<tr class="employee-result-row ${isSelected ? 'selected' : ''}" onclick="selectReissueEmployee(${emp.id}, '${escapeHtml(emp.firstname + ' ' + emp.lastname)}', '${escapeHtml(emp.position || '')}', '${escapeHtml(deptDisplay)}')">
            <td>${escapeHtml(emp.lastname + ', ' + emp.firstname)}</div>
            <td>${escapeHtml(emp.position || '—')}</div>
            <td>${escapeHtml(deptDisplay)}</div>
            <td><button class="select-employee-btn" onclick="event.stopPropagation();selectReissueEmployee(${emp.id}, '${escapeHtml(emp.firstname + ' ' + emp.lastname)}', '${escapeHtml(emp.position || '')}', '${escapeHtml(deptDisplay)}')">Select</button></div>
        </tr>`;
    });
    tbody.innerHTML = html;
}

function selectReissueEmployee(id, name, position, department) {
    selectedReissueEmployeeId = id;
    document.getElementById('selected_reissue_employee_id').value = id;
    let displayText = name;
    if (position) displayText += ` - ${position}`;
    if (department && department !== '—') displayText += ` (${department})`;
    document.getElementById('selected_reissue_employee_name').innerHTML = displayText;
    document.getElementById('selected_reissue_employee_display').style.display = 'flex';
    document.querySelectorAll('#reissue_employee_results_body .employee-result-row').forEach(row => row.classList.remove('selected'));
    if (event && event.target) {
        const row = event.target.closest('.employee-result-row');
        if (row) row.classList.add('selected');
    }
}

function clearReissueEmployeeSearch() {
    document.getElementById('reissue_search_employee_name').value = '';
    document.getElementById('reissue_search_department').value = '';
    document.getElementById('reissue_search_section').value = '';
    document.getElementById('reissue_search_position').value = '';
    document.getElementById('reissue_employee_results_body').innerHTML = '<tr><td colspan="4" class="no-results">Type to search</div></div>';
}

// ============================================
// SEARCH & CART FUNCTIONS
// ============================================

function searchByProperty() {
    const searchTerm = document.getElementById('property_search_input').value.toLowerCase().trim();
    const resultDiv = document.getElementById('property_search_result');
    if (!searchTerm) { resultDiv.innerHTML = '<div class="alert-warning">Please enter search term</div>'; resultDiv.classList.add('show'); return; }
    
    const found = inventoryData.filter(item => {
        const propertyNo = (item.property_no || '').toLowerCase();
        const articleName = (item.article_name || '').toLowerCase();
        return propertyNo.includes(searchTerm) || articleName.includes(searchTerm);
    });
    
    if (found.length > 0) {
        let html = '';
        found.forEach(item => {
            const isInCart = cartItems.some(cartItem => cartItem.id == item.id);
            const unitDisplay = item.big_unit ? item.big_unit : (item.small_unit || 'pcs');
            html += `<div class="result-property-card">
                <div class="result-property-info">
                    <h5>${escapeHtml(item.article_name)}</h5>
                    <p>Property: <strong>${escapeHtml(item.property_no || 'N/A')}</strong> | Available: ${item.qty_physical_count} ${escapeHtml(unitDisplay)}</p>
                </div>
                <div>${isInCart ? '<button disabled style="background:#ccc">In Cart</button>' : `<button class="btn-add-property" onclick="addToCart(${item.id}, 1)">Add to Cart</button>`}</div>
            </div>`;
        });
        resultDiv.innerHTML = html;
        resultDiv.classList.add('show');
    } else {
        resultDiv.innerHTML = `<div class="result-property-card"><h5>No items found for: ${escapeHtml(searchTerm)}</h5></div>`;
        resultDiv.classList.add('show');
    }
}

function clearPropertySearch() {
    document.getElementById('property_search_input').value = '';
    document.getElementById('property_search_result').innerHTML = '';
    document.getElementById('property_search_result').classList.remove('show');
}

function searchBarcode() {
    const barcode = document.getElementById('barcode_input').value.trim();
    const resultDiv = document.getElementById('barcode_result');
    
    if (!barcode) {
        resultDiv.innerHTML = '<div class="alert-warning">Please enter or scan barcode</div>';
        resultDiv.classList.add('show');
        return;
    }
    
    const found = inventoryData.filter(item => {
        const propertyNo = (item.property_no || '').toLowerCase();
        const articleName = (item.article_name || '').toLowerCase();
        return propertyNo.includes(barcode.toLowerCase()) || articleName.includes(barcode.toLowerCase());
    });
    
    if (found.length > 0) {
        let html = '';
        found.forEach(item => {
            const isInCart = cartItems.some(cartItem => cartItem.id == item.id);
            const unitDisplay = item.big_unit ? item.big_unit : (item.small_unit || 'pcs');
            html += `<div class="result-item">
                <div class="result-info">
                    <h5>${escapeHtml(item.article_name)}</h5>
                    <p>Property: <strong>${escapeHtml(item.property_no || 'N/A')}</strong> | Available: ${item.qty_physical_count} ${escapeHtml(unitDisplay)}</p>
                </div>
                <div>${isInCart ? '<button disabled>In Cart</button>' : `<button class="btn-add-item" onclick="addToCart(${item.id}, 1)">Add to Cart</button>`}</div>
            </div>`;
        });
        resultDiv.innerHTML = html;
        resultDiv.classList.add('show');
        document.getElementById('barcode_input').value = '';
    } else {
        resultDiv.innerHTML = `<div class="result-item"><h5>No item found for: ${escapeHtml(barcode)}</h5></div>`;
        resultDiv.classList.add('show');
    }
}

function addToCart(id, qty) {
    const item = inventoryData.find(i => i.id == id);
    if (!item) return;
    const existing = cartItems.find(i => i.id == id);
    if (existing) {
        const newQty = existing.quantity + (qty || 1);
        if (newQty <= item.qty_physical_count) existing.quantity = newQty;
        else alert('Max available: ' + item.qty_physical_count);
    } else {
        cartItems.push({
            id: item.id, name: item.article_name, property_no: item.property_no,
            big_unit: item.big_unit, small_unit: item.small_unit,
            available_qty: item.qty_physical_count, unit_value: item.unit_value, quantity: (qty || 1)
        });
    }
    updateCartDisplay();
    clearPropertySearch();
    document.getElementById('barcode_result').innerHTML = '';
    document.getElementById('barcode_result').classList.remove('show');
}

function removeFromCart(id) {
    cartItems = cartItems.filter(i => i.id != id);
    updateCartDisplay();
}

function updateCartQuantity(id, newQty) {
    const item = cartItems.find(i => i.id == id);
    if (item) {
        newQty = parseInt(newQty) || 1;
        if (newQty > item.available_qty) newQty = item.available_qty;
        item.quantity = newQty;
        updateCartDisplay();
    }
}

function updateCartDisplay() {
    const container = document.getElementById('selectedItemsList');
    const emptyMsg = document.getElementById('emptyCartMessage');
    const countSpan = document.getElementById('selectedCount');
    const summary = document.getElementById('cartSummary');
    
    if (cartItems.length === 0) {
        emptyMsg.style.display = 'block';
        container.innerHTML = '';
        countSpan.innerText = '0';
        summary.style.display = 'none';
        return;
    }
    emptyMsg.style.display = 'none';
    let html = '', totalQty = 0, totalValue = 0;
    cartItems.forEach(item => {
        const unitDisplay = item.big_unit ? item.big_unit : (item.small_unit || 'pcs');
        const itemTotal = item.unit_value * item.quantity;
        totalQty += item.quantity;
        totalValue += itemTotal;
        html += `<div class="selected-item-card">
            <div class="selected-item-info">
                <div class="item-name">${escapeHtml(item.name)}</div>
                <div class="item-property">Property: ${escapeHtml(item.property_no || 'N/A')}</div>
            </div>
            <div class="selected-item-qty">
                <input type="number" value="${item.quantity}" min="1" max="${item.available_qty}" onchange="updateCartQuantity(${item.id}, this.value)">
                <span>${escapeHtml(unitDisplay)}</span>
                <span>₱${itemTotal.toFixed(2)}</span>
            </div>
            <button class="btn-remove-item" onclick="removeFromCart(${item.id})"><i class="fas fa-trash"></i></button>
        </div>`;
    });
    container.innerHTML = html;
    countSpan.innerText = cartItems.length;
    summary.style.display = 'block';
    summary.innerHTML = `<strong>Total Items: ${totalQty}</strong> | <strong>Total Value: ₱${totalValue.toFixed(2)}</strong>`;
    updateFormInputs();
}

function updateFormInputs() {
    document.querySelectorAll('input[name="inventory_ids[]"]').forEach(e => e.remove());
    document.querySelectorAll('input[name="quantities[]"]').forEach(e => e.remove());
    const form = document.getElementById('issueForm');
    cartItems.forEach(item => {
        let ii = document.createElement('input');
        ii.type = 'hidden'; ii.name = 'inventory_ids[]'; ii.value = item.id;
        form.appendChild(ii);
        let qi = document.createElement('input');
        qi.type = 'hidden'; qi.name = 'quantities[]'; qi.value = item.quantity;
        form.appendChild(qi);
    });
}

// ============================================
// CONFIRMATION FUNCTIONS
// ============================================

function showConfirmModal() {
    if (!selectedEmployeeId) { alert('Please select an employee'); return; }
    const condition = document.getElementById('condition').value;
    if (!condition) { alert('Please select a condition'); return; }
    if (cartItems.length === 0) { alert('Please add items to issue'); return; }
    document.getElementById('confirmModalMessage').innerHTML = `Issue ${cartItems.length} item(s) to ${document.getElementById('selected_employee_name').innerHTML}?<br><small>Condition: ${condition}</small>`;
    document.getElementById('confirmModal').classList.add('show');
}

function closeConfirmModal() { document.getElementById('confirmModal').classList.remove('show'); }
function submitForm() { closeConfirmModal(); document.getElementById('issueForm').submit(); }

function confirmReissueSubmit() {
    if (!selectedReissueEmployeeId) { alert('Please select an employee'); return; }
    const condition = document.getElementById('reissue_condition').value;
    if (!condition) { alert('Please select a condition'); return; }
    document.getElementById('confirmReissueModalMessage').innerHTML = `Reissue to ${document.getElementById('selected_reissue_employee_name').innerHTML}?<br><small>Condition: ${condition}</small>`;
    document.getElementById('confirmReissueModal').classList.add('show');
}

function closeConfirmReissueModal() { document.getElementById('confirmReissueModal').classList.remove('show'); }
function submitReissueForm() { closeConfirmReissueModal(); document.getElementById('reissueForm').submit(); }

function confirmReissueReturned(event, element) {
    event.preventDefault();
    if (confirm('Reissue this returned item?')) window.location.href = element.getAttribute('href');
    return false;
}

// ============================================
// RETURN MODAL FUNCTIONS
// ============================================

function openReturnModal(issuanceId, itemName) {
    currentReturnId = issuanceId;
    document.getElementById('return_item_name').innerHTML = itemName;
    document.getElementById('return_condition').value = '';
    document.getElementById('returnModal').classList.add('show');
}

function closeReturnModal() { document.getElementById('returnModal').classList.remove('show'); }

function submitReturn() {
    const condition = document.getElementById('return_condition').value;
    if (!condition) { alert('Please select a condition'); return; }
    fetch('', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'action=return_item&issuance_id=' + currentReturnId + '&condition_on_return=' + encodeURIComponent(condition)
    })
    .then(response => response.json())
    .then(data => { alert(data.message); if(data.success) location.reload(); })
    .catch(error => alert('Error: ' + error));
    closeReturnModal();
}

// ============================================
// VIEW MODAL FUNCTIONS
// ============================================

function viewIssuanceDetails(id) {
    const modal = document.getElementById('viewModal');
    const body = document.getElementById('viewModalBody');
    modal.classList.add('show');
    body.innerHTML = '<div style="text-align:center;padding:20px"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    fetch('?ajax=get_issuance&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const item = data.data;
                const unitDisplay = item.big_unit ? item.big_unit : (item.small_unit || 'pcs');
                const conditionClass = 'condition-' + (item.condition_on_issue || 'Serviceable').replace(/ /g, '');
                const statusClass = 'issue-status-' + item.status;
                
                body.innerHTML = `
                    <div class="view-detail-row"><div class="view-detail-label">Item Name:</div><div class="view-detail-value"><strong>${escapeHtml(item.article_name)}</strong></div></div>
                    <div class="view-detail-row"><div class="view-detail-label">Property No.:</div><div class="view-detail-value"><code>${escapeHtml(item.property_no)}</code></div></div>
                    <div class="view-detail-row"><div class="view-detail-label">Description:</div><div class="view-detail-value">${escapeHtml(item.description || 'N/A')}</div></div>
                    <div class="view-detail-row"><div class="view-detail-label">Quantity:</div><div class="view-detail-value">${item.quantity_issued} ${escapeHtml(unitDisplay)}</div></div>
                    <div class="view-detail-row"><div class="view-detail-label">Unit Value:</div><div class="view-detail-value">₱${parseFloat(item.unit_value).toFixed(2)}</div></div>
                    <div class="view-detail-row"><div class="view-detail-label">Total Value:</div><div class="view-detail-value">₱${(item.quantity_issued * item.unit_value).toFixed(2)}</div></div>
                    <div class="view-detail-row"><div class="view-detail-label">Issued To:</div><div class="view-detail-value">${escapeHtml(item.issued_to_name)}${item.issued_to_position ? ' - ' + escapeHtml(item.issued_to_position) : ''}</div></div>
                    <div class="view-detail-row"><div class="view-detail-label">Condition:</div><div class="view-detail-value"><span class="condition-badge ${conditionClass}">${escapeHtml(item.condition_on_issue || 'Serviceable')}</span></div></div>
                    <div class="view-detail-row"><div class="view-detail-label">Issued By:</div><div class="view-detail-value">${escapeHtml(item.issued_by_name)}</div></div>
                    <div class="view-detail-row"><div class="view-detail-label">Issued Date:</div><div class="view-detail-value">${new Date(item.issued_date).toLocaleString()}</div></div>
                    <div class="view-detail-row"><div class="view-detail-label">Signatory:</div><div class="view-detail-value">${escapeHtml(item.signatory_name || 'N/A')}</div></div>
                    <div class="view-detail-row"><div class="view-detail-label">Status:</div><div class="view-detail-value"><span class="issue-status-badge ${statusClass}">${escapeHtml(item.status)}</span></div></div>
                    ${item.actual_return ? `<div class="view-detail-row"><div class="view-detail-label">Return Date:</div><div class="view-detail-value">${new Date(item.actual_return).toLocaleString()}</div></div>` : ''}
                    ${item.condition_on_return ? `<div class="view-detail-row"><div class="view-detail-label">Condition on Return:</div><div class="view-detail-value">${escapeHtml(item.condition_on_return)}</div></div>` : ''}
                    ${item.reissued_from_name ? `<div class="view-detail-row"><div class="view-detail-label">Reissued From:</div><div class="view-detail-value">${escapeHtml(item.reissued_from_name)}</div></div>` : ''}
                    ${item.reissued_to_name ? `<div class="view-detail-row"><div class="view-detail-label">Reissued To:</div><div class="view-detail-value">${escapeHtml(item.reissued_to_name)}</div></div>` : ''}
                    ${item.remarks ? `<div class="view-detail-row"><div class="view-detail-label">Remarks:</div><div class="view-detail-value">${escapeHtml(item.remarks)}</div></div>` : ''}
                `;
            } else {
                body.innerHTML = '<div style="color:red;text-align:center;padding:20px">Error loading details</div>';
            }
        })
        .catch(() => {
            body.innerHTML = '<div style="color:red;text-align:center;padding:20px">Error loading details</div>';
        });
}

function closeViewModal() {
    document.getElementById('viewModal').classList.remove('show');
}

// ============================================
// BARCODE MODAL FUNCTIONS - FIXED
// ============================================

function showBarcodeModal(barcode, itemName) {
    document.getElementById('barcodeModalTitle').innerHTML = itemName;
    document.getElementById('barcodeModalImage').innerHTML = `<img src="<?php echo SITE_URL; ?>/admin/barcode_generator_issued.php?code=${encodeURIComponent(barcode)}&width=300&height=80&t=${Date.now()}" style="border:1px solid #ddd;padding:10px;border-radius:8px">`;
    document.getElementById('barcodeModalValue').innerHTML = barcode;
    document.getElementById('barcodeModal').classList.add('show');
}

function closeBarcodeModal() {
    document.getElementById('barcodeModal').classList.remove('show');
}

function printBarcodeFromModal() {
    const barcode = document.getElementById('barcodeModalValue').innerText;
    const itemName = document.getElementById('barcodeModalTitle').innerText;
    
    // Create a print window with the barcode
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Print Barcode</title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { 
                    font-family: 'Segoe UI', Arial, sans-serif; 
                    text-align: center; 
                    padding: 40px; 
                    background: white;
                }
                .barcode-container { 
                    max-width: 500px; 
                    margin: 0 auto; 
                    padding: 30px;
                    border: 1px dashed #6B8CFF;
                    border-radius: 16px;
                }
                .barcode-img { 
                    max-width: 100%; 
                    height: auto; 
                    margin: 20px 0;
                }
                .item-name { 
                    font-size: 18px; 
                    font-weight: bold; 
                    color: #2C3E50;
                    margin-bottom: 10px;
                }
                .barcode-number { 
                    font-family: monospace; 
                    font-size: 14px; 
                    margin-top: 15px; 
                    color: #6B8CFF;
                    word-break: break-all;
                }
                .buttons {
                    margin-top: 30px;
                    display: flex;
                    gap: 15px;
                    justify-content: center;
                }
                button {
                    padding: 10px 20px;
                    border: none;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 14px;
                }
                .btn-print {
                    background: #6B8CFF;
                    color: white;
                }
                .btn-close {
                    background: #6c757d;
                    color: white;
                }
                @media print {
                    .buttons { display: none; }
                    body { padding: 20px; }
                    .barcode-container { border: none; padding: 0; }
                }
            </style>
        </head>
        <body>
            <div class="barcode-container">
                <div class="item-name">${escapeHtml(itemName)}</div>
                <img src="<?php echo SITE_URL; ?>/admin/barcode_generator_issued.php?code=${encodeURIComponent(barcode)}&width=400&height=100&t=${Date.now()}" class="barcode-img" alt="Barcode">
                <div class="barcode-number">${escapeHtml(barcode)}</div>
            </div>
            <div class="buttons">
                <button class="btn-print" onclick="window.print()">🖨️ Print</button>
                <button class="btn-close" onclick="window.close()">Close</button>
            </div>
            <script>
                // Auto print when window loads
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                    }, 500);
                }
            <\/script>
        </body>
        </html>
    `);
    printWindow.document.close();
}

// ============================================
// OTHER FUNCTIONS
// ============================================

function escapeHtml(s) { 
    if (!s) return ''; 
    return String(s).replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m])); 
}

// ============================================
// EVENT LISTENERS
// ============================================

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('property_search_input')?.addEventListener('keypress', e => { 
        if (e.key === 'Enter') { e.preventDefault(); searchByProperty(); } 
    });
    
    document.getElementById('barcode_input')?.addEventListener('keypress', e => { 
        if (e.key === 'Enter') { e.preventDefault(); searchBarcode(); } 
    });
    
    document.addEventListener('keydown', e => { 
        if (e.key === 'Escape') { 
            closeReturnModal();
            closeViewModal();
            closeBarcodeModal();
        } 
    });
    
    document.getElementById('viewModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeViewModal();
    });
    document.getElementById('barcodeModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeBarcodeModal();
    });
    document.getElementById('returnModal')?.addEventListener('click', function(e) {
        if (e.target === this) closeReturnModal();
    });
    
    searchEmployees();
    <?php if($reissue_item): ?>searchReissueEmployees();<?php endif; ?>
});
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>