<?php
ob_start();
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
    header('Content-Type: application/json');
    $issuance_id = (int)$_GET['id'];
    
    // First, determine which table the inventory item belongs to
    $check_source = $conn->query("
        SELECT 
            ei.inventory_id,
            CASE 
                WHEN i.id IS NOT NULL THEN 'inventory'
                WHEN s.id IS NOT NULL THEN 'semi_ppe'
                ELSE NULL
            END as source_table
        FROM equipment_issuance ei
        LEFT JOIN inventory i ON ei.inventory_id = i.id
        LEFT JOIN semi_ppe s ON ei.inventory_id = s.id
        WHERE ei.id = $issuance_id
    ");
    
    $source_info = $check_source->fetch_assoc();
    
    if (!$source_info || !$source_info['source_table']) {
        echo json_encode(['success' => false, 'message' => 'Issuance record not found']);
        exit;
    }
    
    // Build query based on source table
    if ($source_info['source_table'] == 'inventory') {
        $query = "
            SELECT 
                ei.id,
                ei.inventory_id,
                ei.issued_to,
                ei.issued_by,
                ei.signatory_id,
                ei.quantity_issued,
                ei.condition_on_issue,
                ei.remarks,
                ei.status,
                ei.issued_date,
                ei.actual_return,
                ei.condition_on_return,
                ei.issuance_barcode,
                ei.reissued_from_id,
                ei.reissued_to_id,
                ei.reissue_date,
                ei.original_issuance_barcode,
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
        ";
    } else {
        $query = "
            SELECT 
                ei.id,
                ei.inventory_id,
                ei.issued_to,
                ei.issued_by,
                ei.signatory_id,
                ei.quantity_issued,
                ei.condition_on_issue,
                ei.remarks,
                ei.status,
                ei.issued_date,
                ei.actual_return,
                ei.condition_on_return,
                ei.issuance_barcode,
                ei.reissued_from_id,
                ei.reissued_to_id,
                ei.reissue_date,
                ei.original_issuance_barcode,
                s.article_name, 
                s.description,
                s.property_no,
                s.big_unit,
                s.small_unit,
                s.unit_value,
                CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
                e.position as issued_to_position,
                CONCAT(issuer.firstname, ' ', issuer.lastname) as issued_by_name,
                sig.name as signatory_name,
                CONCAT(original_emp.firstname, ' ', original_emp.lastname) as reissued_from_name,
                CONCAT(reissued_emp.firstname, ' ', reissued_emp.lastname) as reissued_to_name
            FROM equipment_issuance ei 
            JOIN semi_ppe s ON ei.inventory_id = s.id 
            JOIN employees e ON ei.issued_to = e.id
            JOIN users issuer ON ei.issued_by = issuer.id
            LEFT JOIN signatories sig ON ei.signatory_id = sig.id
            LEFT JOIN equipment_issuance original_iss ON ei.reissued_from_id = original_iss.id
            LEFT JOIN employees original_emp ON original_iss.issued_to = original_emp.id
            LEFT JOIN employees reissued_emp ON ei.reissued_to_id = reissued_emp.id
            WHERE ei.id = $issuance_id
        ";
    }
    
    $result = $conn->query($query);
    
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
    // First try to get department code from the employee's department directly
    $emp_query = $conn->query("
        SELECT d.code as department_code 
        FROM employees e
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE e.id = " . intval($employee_id)
    );
    
    if ($emp_query && $emp_query->num_rows > 0) {
        $employee = $emp_query->fetch_assoc();
        if (!empty($employee['department_code'])) {
            return $employee['department_code'];
        }
    }
    
    // Fallback: get from section if department not directly assigned
    $section_query = $conn->query("
        SELECT d.code as department_code 
        FROM employees e
        LEFT JOIN sections s ON e.section_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE e.id = " . intval($employee_id)
    );
    
    if ($section_query && $section_query->num_rows > 0) {
        $employee = $section_query->fetch_assoc();
        if (!empty($employee['department_code'])) {
            return $employee['department_code'];
        }
    }
    
    // Default code if no department found
    return '000';
}

// Handle AJAX request to get all items issued to an employee
if (isset($_GET['get_employee_issuances']) && is_numeric($_GET['get_employee_issuances'])) {
    header('Content-Type: application/json');
    
    $employee_id = (int)$_GET['get_employee_issuances'];
    
    $result = $conn->query("
        SELECT 
            ei.id, 
            ei.inventory_id,
            ei.quantity_issued,
            ei.condition_on_issue,
            ei.remarks,
            ei.issued_date,
            ei.issuance_barcode,
            ei.status,
            i.article_name,
            i.description,
            i.property_no,
            i.big_unit,
            i.small_unit,
            i.unit_value,
            CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
            e.position as issued_to_position,
            CONCAT(issuer.firstname, ' ', issuer.lastname) as issued_by_name
        FROM equipment_issuance ei 
        JOIN inventory i ON ei.inventory_id = i.id 
        JOIN employees e ON ei.issued_to = e.id
        JOIN users issuer ON ei.issued_by = issuer.id
        WHERE ei.issued_to = $employee_id AND ei.status = 'issued'
        ORDER BY ei.issued_date DESC
    ");
    
    $items = [];
    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $items[] = $row;
        }
    }
    
    echo json_encode(['success' => true, 'items' => $items, 'employee_name' => $items[0]['issued_to_name'] ?? '']);
    exit;
}


// Print grouped items for an employee
if (isset($_GET['print_grouped']) && is_numeric($_GET['print_grouped'])) {
    $employee_id = (int)$_GET['print_grouped'];
    
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
            d.name as department_name,
            d.code as department_code
        FROM equipment_issuance ei 
        JOIN inventory i ON ei.inventory_id = i.id 
        JOIN employees e ON ei.issued_to = e.id
        JOIN users issuer ON ei.issued_by = issuer.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE ei.issued_to = $employee_id AND ei.status = 'issued'
        ORDER BY ei.issued_date DESC
    ");
    
    if ($result && $result->num_rows > 0) {
        $items = [];
        $employee_name = '';
        $issued_by = '';
        $issued_date = '';
        
        while($row = $result->fetch_assoc()) {
            $items[] = $row;
            $employee_name = $row['issued_to_name'];
            $issued_by = $row['issued_by_name'];
            $issued_date = $row['issued_date'];
        }
        
        $total_amount = 0;
        foreach($items as $item) {
            $total_amount += $item['unit_value'] * $item['quantity_issued'];
        }
        
        $current_date = date('F d, Y');
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <title>Inventory Custodian Slip - <?php echo htmlspecialchars($employee_name); ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: 'Times New Roman', Times, serif; padding: 30px; background: white; }
                .ics-container { max-width: 1200px; margin: 0 auto; border: 1px solid #000; padding: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h1 { font-size: 18px; font-weight: bold; text-transform: uppercase; }
                .entity-name { font-size: 12px; margin-top: 5px; }
                .fund-cluster { text-align: right; font-size: 12px; margin-bottom: 15px; }
                table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 11px; }
                th, td { border: 1px solid #000; padding: 6px; vertical-align: top; }
                th { background: #f0f0f0; font-weight: bold; text-align: center; }
                .signature-section { margin-top: 30px; display: flex; flex-wrap: wrap; justify-content: space-between; }
                .signature-box { width: 45%; margin-top: 20px; }
                .signature-line { margin-top: 40px; border-top: 1px solid #000; width: 100%; padding-top: 5px; }
                .signature-name { font-weight: bold; }
                .footer-note { margin-top: 30px; font-size: 10px; font-style: italic; text-align: center; border-top: 1px solid #000; padding-top: 10px; }
                @media print { body { padding: 0; margin: 0; } .no-print { display: none; } .ics-container { border: none; padding: 0; } }
                .btn-print { display: inline-block; padding: 10px 20px; margin: 10px; background: #6B8CFF; color: white; border: none; border-radius: 5px; cursor: pointer; }
                .btn-close { background: #6c757d; }
                .button-container { text-align: center; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class="button-container no-print">
                <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                <button class="btn-print btn-close" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
            </div>
            <div class="ics-container">
                <div class="header">
                    <h1>INVENTORY CUSTODIAN SLIP</h1>
                    <div class="entity-name">'AMANG' RODRIGUEZ MEMORIAL MEDICAL CENTER</div>
                </div>
                
                
                <table>
                    <thead>
                        <tr>
                            <th>Qty</th>
                            <th>Unit</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Property No.</th>
                            <th>Est. Useful Life</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($items as $item): 
                            $unit_display = !empty($item['big_unit']) ? $item['big_unit'] : ($item['small_unit'] ?? 'pcs');
                            $amount = $item['unit_value'] * $item['quantity_issued'];
                        ?>
                        <tr>
                            <td style="text-align: center;"><?php echo $item['quantity_issued']; ?></td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($unit_display); ?></td>
                            <td style="text-align: right;">₱<?php echo number_format($amount, 2); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($item['article_name']); ?></strong><br>
                                <small><?php echo nl2br(htmlspecialchars(substr($item['description'] ?? '', 0, 100))); ?></small>
                            </td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($item['property_no']); ?></td>
                            <td style="text-align: center;">3 years</td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background: #f9f9f9;">
                            <td colspan="2" style="text-align: right;"><strong>TOTAL:</strong></td>
                            <td style="text-align: right;"><strong>₱<?php echo number_format($total_amount, 2); ?></strong></td>
                            <td colspan="3"></td>
                        </tr>
                    </tbody>
                </table>
                
                <div class="signature-section">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-name"><?php echo htmlspecialchars($employee_name); ?></div>
                        <div>Signature Over Printed Name</div>
                        <div style="margin-top: 10px;">Recipient/End-User</div>
                        <div>Position/Office</div>
                        <div><?php echo $current_date; ?></div>
                        <div>Date</div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-name"><?php echo htmlspecialchars($issued_by); ?></div>
                        <div>Signature Over Printed Name</div>
                        <div style="margin-top: 10px;">SAO-Materials Management/HOPSS</div>
                        <div>Position/Office</div>
                        <div><?php echo $current_date; ?></div>
                        <div>Date</div>
                    </div>
                </div>
                
                <div class="footer-note">
                    <strong>Original Copy 2</strong> - Supply and/or Property Division/Unit Recipient or end-user of the inventory
                </div>
            </div>
            <script>
                window.onload = function() { setTimeout(function() { window.print(); }, 500); }
            </script>
        </body>
        </html>
        <?php
        exit;
    } else {
        $_SESSION['error'] = 'No items found for this employee.';
        header('Location: ' . SITE_URL . '/admin/issue_items.php');
        exit;
    }
}

function removeDepartmentCodeFromPropertyNo($property_no) {
    if (empty($property_no)) return $property_no;
    $parts = explode('-', $property_no);
    $last_part = end($parts);
    // check the property number if the number is have ano 1-99
    if (strlen($last_part) == 3 && ctype_digit($last_part)) {
        array_pop($parts);
        return implode('-', $parts);
    }
    return $property_no;
}

function generatePropertyNumberWithDeptCode($conn, $inventory_id, $employee_id, $table_name = 'inventory') {
    // Get the item details from the correct table
    $inv_query = $conn->query("SELECT property_no, article_name FROM $table_name WHERE id = " . intval($inventory_id));
    
    if (!$inv_query || $inv_query->num_rows == 0) {
        // No existing property number, generate a new one
        $date_prefix = date('Y-m-d');
        $random_num = str_pad(mt_rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $dept_code = getDepartmentCode($conn, $employee_id);
        return $date_prefix . '-' . $random_num . '-' . $dept_code;
    }
    
    $item = $inv_query->fetch_assoc();
    $original_property_no = $item['property_no'];
    $dept_code = getDepartmentCode($conn, $employee_id);
    
    if (!empty($original_property_no)) {
        // Check if property number already has a department code (last 3 digits)
        $parts = explode('-', $original_property_no);
        $last_part = end($parts);
        
        // Check if last part is a 3-digit department code (between 001 and 999)
        if (preg_match('/^\d{3}$/', $last_part)) {
            // Already has department code, replace it
            array_pop($parts);
            $base_property = implode('-', $parts);
            return $base_property . '-' . $dept_code;
        } else {
            // No department code, append it
            return $original_property_no . '-' . $dept_code;
        }
    } else {
        // No property number exists, create a new one
        $date_prefix = date('Y-m-d');
        // Get the next sequence number for this date
        $seq_query = $conn->query("
            SELECT MAX(CAST(SUBSTRING_INDEX(property_no, '-', -2) AS UNSIGNED)) as max_seq 
            FROM $table_name 
            WHERE property_no LIKE '$date_prefix-%'
        ");
        $seq_result = $seq_query->fetch_assoc();
        $next_seq = ($seq_result['max_seq'] ?? 0) + 1;
        $seq_num = str_pad($next_seq, 4, '0', STR_PAD_LEFT);
        
        return $date_prefix . '-' . $seq_num . '-' . $dept_code;
    }
}

// ============================================
// FORM HANDLERS
// ============================================

// New Issuance - Supports both inventory and semi_ppe
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'new_issue') {
    $inventory_ids = isset($_POST['inventory_ids']) ? $_POST['inventory_ids'] : [];
    $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : [];
    $item_types = isset($_POST['item_type']) ? $_POST['item_type'] : [];
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
            $item_type = isset($item_types[$index]) ? $item_types[$index] : 'inventory';
            
            // Determine which table to use
            $table_name = ($item_type == 'semi_ppe') ? 'semi_ppe' : 'inventory';
            
            // Lock the row for update to prevent race conditions
            $selected_item = $conn->query("SELECT * FROM $table_name WHERE id=$inventory_id FOR UPDATE")->fetch_assoc();
            if (!$selected_item) throw new Exception("Item not found in $table_name");
            
            if ($selected_item['qty_physical_count'] < $requested_qty) {
                throw new Exception("Insufficient quantity for: " . $selected_item['article_name'] . " (Available: " . $selected_item['qty_physical_count'] . ", Requested: " . $requested_qty . ")");
            }
            
            // ============================================
            // FIX: Generate sequential property numbers for EACH unit
            // ============================================
            
            $dept_code = getDepartmentCode($conn, $issued_to);
            $date_prefix = date('Y-m-d');
            
            // Get the next available sequence number for this date
            $seq_query = $conn->query("
                SELECT MAX(CAST(SUBSTRING_INDEX(issuance_barcode, '-', -2) AS UNSIGNED)) as max_seq 
                FROM equipment_issuance 
                WHERE issuance_barcode LIKE '$date_prefix-%'
            ");
            $seq_result = $seq_query->fetch_assoc();
            $next_seq = intval($seq_result['max_seq'] ?? 0) + 1;
            
            // Array to store all generated property numbers for this item
            $generated_property_numbers = [];
            
            // Generate ONE property number for EACH unit issued
            for ($i = 0; $i < $requested_qty; $i++) {
                $seq_num = str_pad($next_seq + $i, 4, '0', STR_PAD_LEFT);
                $new_property_no = $date_prefix . '-' . $seq_num . '-' . $dept_code;
                $generated_property_numbers[] = $new_property_no;
            }
            
            // Calculate new quantity
            $new_quantity = $selected_item['qty_physical_count'] - $requested_qty;
            
            // For the first unit, update the inventory table's property_no
            // For subsequent units, we only track via issuance records
            $first_property_no = $generated_property_numbers[0];
            
            // Update the appropriate table
            if ($item_type == 'inventory') {
                $update_sql = "UPDATE inventory SET 
                    qty_physical_count = $new_quantity, 
                    current_holder = $issued_to, 
                    property_no = '$first_property_no', 
                    condition_text = '$condition' 
                    WHERE id = $inventory_id";
            } else {
                $update_sql = "UPDATE semi_ppe SET 
                    qty_physical_count = $new_quantity, 
                    current_holder = $issued_to 
                    WHERE id = $inventory_id";
            }
            
            if (!$conn->query($update_sql)) {
                throw new Exception("Failed to update $table_name: " . $conn->error);
            }
            
            // Insert a SEPARATE issuance record for EACH unit
            foreach ($generated_property_numbers as $property_no) {
                $stmt = $conn->prepare("
                    INSERT INTO equipment_issuance (
                        inventory_id, issued_to, issued_by, signatory_id, quantity_issued, 
                        condition_on_issue, remarks, status, issued_date, issuance_barcode
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'issued', NOW(), ?)
                ");
                
                if (!$stmt) {
                    throw new Exception("Prepare failed: " . $conn->error);
                }
                
                $single_qty = 1;  // Each record represents 1 unit
                
                $stmt->bind_param("iiiidsss", 
                    $inventory_id, 
                    $issued_to, 
                    $_SESSION['user_id'],
                    $signatory_id, 
                    $single_qty, 
                    $condition, 
                    $remarks,
                    $property_no
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Failed to insert issuance: " . $stmt->error);
                }
                $stmt->close();
                
                $success_count++;
            }
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

// Reissue from returned item - Handle both inventory and semi_ppe
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
        // First check which table the item belongs to
        $original_issuance = $conn->query("
            SELECT ei.*, 
                   CASE 
                       WHEN i.id IS NOT NULL THEN 'inventory'
                       WHEN s.id IS NOT NULL THEN 'semi_ppe'
                   END as item_type,
                   COALESCE(i.qty_physical_count, s.qty_physical_count) as qty_physical_count,
                   COALESCE(i.article_name, s.article_name) as article_name,
                   COALESCE(i.property_no, s.property_no) as property_no,
                   i.condition_on_return as inv_condition,
                   s.condition_text as semi_condition
            FROM equipment_issuance ei 
            LEFT JOIN inventory i ON ei.inventory_id = i.id 
            LEFT JOIN semi_ppe s ON ei.inventory_id = s.id 
            WHERE ei.id = $original_issuance_id AND ei.status = 'returned'
        ")->fetch_assoc();
        
        if (!$original_issuance) throw new Exception("Original issuance not found or not returned.");
        
        $table_name = ($original_issuance['item_type'] == 'semi_ppe') ? 'semi_ppe' : 'inventory';
        
        if ($original_issuance['item_type'] == 'inventory') {
            $new_property_no = generatePropertyNumberWithDeptCode($conn, $original_issuance['inventory_id'], $reissue_to);
            $conn->query("UPDATE inventory SET property_no = '$new_property_no', current_holder=$reissue_to, condition_text='$condition' WHERE id = {$original_issuance['inventory_id']}");
            $issuance_barcode = $new_property_no;
        } else {
            // For semi-expendable items
            $issuance_barcode = !empty($original_issuance['property_no']) ? $original_issuance['property_no'] : 'SEMI-' . date('Ymd') . '-' . str_pad($original_issuance['inventory_id'], 5, '0', STR_PAD_LEFT);
            $conn->query("UPDATE semi_ppe SET current_holder=$reissue_to WHERE id = {$original_issuance['inventory_id']}");
        }
        
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

// Return item with condition - Handle both inventory and semi_ppe
if (isset($_POST['action']) && $_POST['action'] == 'return_item') {
    // Disable error display to ensure clean JSON output
    error_reporting(0);
    
    header('Content-Type: application/json');
    
    $issuance_id = (int)$_POST['issuance_id'];
    $condition_on_return = sanitize($_POST['condition_on_return']);
    
    $valid_conditions = ['Serviceable', 'Non-Serviceable', 'For Condemn', 'Under Repair'];
    if (!in_array($condition_on_return, $valid_conditions)) {
        echo json_encode(['success' => false, 'message' => 'Invalid condition selected.']);
        exit();
    }
    
    // Get issuance details and determine item type
    $issuance = $conn->query("
        SELECT ei.*, 
               CASE 
                   WHEN i.id IS NOT NULL THEN 'inventory'
                   WHEN s.id IS NOT NULL THEN 'semi_ppe'
               END as item_type,
               COALESCE(i.qty_physical_count, s.qty_physical_count) as qty_physical_count,
               COALESCE(i.property_no, s.property_no) as property_no
        FROM equipment_issuance ei 
        LEFT JOIN inventory i ON ei.inventory_id = i.id 
        LEFT JOIN semi_ppe s ON ei.inventory_id = s.id 
        WHERE ei.id=$issuance_id AND ei.status = 'issued'
    ")->fetch_assoc();
    
    if (!$issuance) {
        echo json_encode(['success' => false, 'message' => 'Issuance not found or already returned.']);
        exit();
    }
    
    $conn->begin_transaction();
    try {
        // Update issuance record
        $conn->query("UPDATE equipment_issuance SET 
            status='returned', 
            actual_return=NOW(), 
            condition_on_return='$condition_on_return' 
            WHERE id=$issuance_id");
        
        // Calculate new quantity
        $new_quantity = $issuance['qty_physical_count'] + $issuance['quantity_issued'];
        
        if ($issuance['item_type'] == 'inventory') {
            $property_no_without_dept = removeDepartmentCodeFromPropertyNo($issuance['property_no']);
            $conn->query("UPDATE inventory SET 
                qty_physical_count=$new_quantity, 
                current_holder=NULL, 
                property_no='$property_no_without_dept' 
                WHERE id={$issuance['inventory_id']}");
        } else {
            $conn->query("UPDATE semi_ppe SET 
                qty_physical_count=$new_quantity, 
                current_holder=NULL 
                WHERE id={$issuance['inventory_id']}");
        }
        
        $conn->commit();
        echo json_encode(['success' => true, 'message' => 'Item returned successfully.']);
        
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
    exit();
}


// Reissue from returned item in history
if (isset($_GET['reissue_returned']) && is_numeric($_GET['reissue_returned'])) {
    $original_issuance_id = (int)$_GET['reissue_returned'];
    $check = $conn->query("SELECT status, condition_on_return FROM equipment_issuance WHERE id=$original_issuance_id")->fetch_assoc();
    
    if (!$check) {
        $_SESSION['error'] = 'Issuance not found.';
    } elseif ($check['status'] !== 'returned') {
        $_SESSION['error'] = 'Only returned items can be reissued.';
    } elseif (in_array($check['condition_on_return'], ['Non-Serviceable', 'For Condemn'])) {
        $_SESSION['error'] = 'Items returned as "Non-Serviceable" or "For Condemn" cannot be reissued.';
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

// Print Inventory Custodian Slip
if (isset($_GET['print_par']) && is_numeric($_GET['print_par'])) {
    $issuance_id = (int)$_GET['print_par'];
    
    // First, determine which table the item belongs to
    $check_source = $conn->query("
        SELECT 
            ei.inventory_id,
            CASE 
                WHEN i.id IS NOT NULL THEN 'inventory'
                WHEN s.id IS NOT NULL THEN 'semi_ppe'
                ELSE NULL
            END as source_table
        FROM equipment_issuance ei
        LEFT JOIN inventory i ON ei.inventory_id = i.id
        LEFT JOIN semi_ppe s ON ei.inventory_id = s.id
        WHERE ei.id = $issuance_id
    ");
    
    $source_info = $check_source->fetch_assoc();
    
    if (!$source_info || !$source_info['source_table']) {
        $_SESSION['error'] = 'Issuance record not found.';
        header('Location: ' . SITE_URL . '/admin/issue_items.php');
        exit;
    }
    
    // Build query based on source table
    if ($source_info['source_table'] == 'inventory') {
        $par_query = $conn->query("
            SELECT ei.*, i.article_name, i.description, i.property_no, i.big_unit, i.small_unit, i.unit_value,
                   CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
                   e.position as issued_to_position,
                   CONCAT(issuer.firstname, ' ', issuer.lastname) as issued_by_name,
                   sig.name as signatory_name,
                   d.name as department_name,
                   d.code as department_code
            FROM equipment_issuance ei
            JOIN inventory i ON ei.inventory_id = i.id
            JOIN employees e ON ei.issued_to = e.id
            JOIN users issuer ON ei.issued_by = issuer.id
            LEFT JOIN signatories sig ON ei.signatory_id = sig.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE ei.id = $issuance_id
        ");
    } else {
$par_query = $conn->query("
    SELECT ei.*, s.article_name, s.description, ei.issuance_barcode as property_no, s.big_unit, s.small_unit, s.unit_value,
           CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
           e.position as issued_to_position,
           CONCAT(issuer.firstname, ' ', issuer.lastname) as issued_by_name,
           sig.name as signatory_name,
           d.name as department_name,
           d.code as department_code
    FROM equipment_issuance ei
    JOIN semi_ppe s ON ei.inventory_id = s.id
            JOIN employees e ON ei.issued_to = e.id
            JOIN users issuer ON ei.issued_by = issuer.id
            LEFT JOIN signatories sig ON ei.signatory_id = sig.id
            LEFT JOIN departments d ON e.department_id = d.id
            WHERE ei.id = $issuance_id
        ");
    }
    
    if ($par_query && $par_query->num_rows > 0) {
        $item = $par_query->fetch_assoc();
        $unit_display = !empty($item['big_unit']) ? $item['big_unit'] : ($item['small_unit'] ?? 'pcs');
        $total_amount = $item['unit_value'] * $item['quantity_issued'];
        $current_date = date('F d, Y');
        ?>
        <!-- Keep the existing HTML for printing here -->
        <!DOCTYPE html>
        <html>
        <head>
            <title>Inventory Custodian Slip</title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: 'Times New Roman', Times, serif;
                    padding: 30px;
                    background: white;
                }
                .ics-container {
                    max-width: 1100px;
                    margin: 0 auto;
                    border: 1px solid #000;
                    padding: 20px;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                }
                .header h1 {
                    font-size: 16px;
                    font-weight: bold;
                    text-transform: uppercase;
                    letter-spacing: 1px;
                }
                .header h2 {
                    font-size: 14px;
                    font-weight: normal;
                }
                .entity-name {
                    font-size: 12px;
                    margin-top: 5px;
                }
                .fund-cluster {
                    text-align: right;
                    font-size: 12px;
                    margin-bottom: 15px;
                }
                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 15px 0;
                    font-size: 12px;
                }
                th, td {
                    border: 1px solid #000;
                    padding: 8px;
                    vertical-align: top;
                }
                th {
                    background: #f0f0f0;
                    font-weight: bold;
                    text-align: center;
                }
                .specs-section {
                    margin: 15px 0;
                }
                .specs-title {
                    font-weight: bold;
                    margin-bottom: 5px;
                }
                .specs-content {
                    padding-left: 20px;
                }
                .details-row {
                    display: flex;
                    flex-wrap: wrap;
                    margin: 10px 0;
                }
                .details-item {
                    flex: 1;
                    min-width: 200px;
                    margin-bottom: 10px;
                }
                .details-label {
                    font-weight: bold;
                }
                .signature-section {
                    margin-top: 30px;
                    display: flex;
                    flex-wrap: wrap;
                    justify-content: space-between;
                }
                .signature-box {
                    width: 45%;
                    margin-top: 20px;
                }
                .signature-line {
                    margin-top: 40px;
                    border-top: 1px solid #000;
                    width: 100%;
                    padding-top: 5px;
                }
                .signature-name {
                    font-weight: bold;
                }
                .footer-note {
                    margin-top: 30px;
                    font-size: 10px;
                    font-style: italic;
                    text-align: center;
                    border-top: 1px solid #000;
                    padding-top: 10px;
                }
                @media print {
                    body {
                        padding: 0;
                        margin: 0;
                    }
                    .no-print {
                        display: none;
                    }
                    .ics-container {
                        border: none;
                        padding: 0;
                    }
                }
                .btn-print {
                    display: inline-block;
                    padding: 10px 20px;
                    margin: 10px;
                    background: #6B8CFF;
                    color: white;
                    border: none;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 14px;
                }
                .btn-close {
                    background: #6c757d;
                }
                .button-container {
                    text-align: center;
                    margin-bottom: 20px;
                }
            </style>
        </head>
        <body>
            <div class="button-container no-print">
                <button class="btn-print" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                <button class="btn-print btn-close" onclick="window.close()"><i class="fas fa-times"></i> Close</button>
            </div>
            <div class="ics-container">
                <div class="header">
                    <h1>INVENTORY CUSTODIAN SLIP</h1>
                    <div class="entity-name">
                        'AMANG' RODRIGUEZ MEMORIAL MEDICAL CENTER
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th style="width: 10%">Quantity</th>
                            <th style="width: 10%">Unit</th>
                            <th style="width: 15%">Amount</th>
                            <th style="width: 35%">Description</th>
                            <th style="width: 20%">Inventory Item No.</th>
                            <th style="width: 10%">Estimated Useful Life</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="text-align: center;"><?php echo $item['quantity_issued']; ?></td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($unit_display); ?></td>
                            <td style="text-align: right;">₱<?php echo number_format($total_amount, 2); ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($item['article_name']); ?></strong><br>
                                <small><?php echo nl2br(htmlspecialchars($item['description'] ?? 'N/A')); ?></small>
                            </td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($item['property_no']); ?></td>
                            <td style="text-align: center;">3 years</td>
                        </tr>
                    </tbody>
                </table>
                
                <?php if (!empty($item['description'])): ?>
                <div class="specs-section">
                    <div class="specs-title">Specs:</div>
                    <div class="specs-content">
                        <?php echo nl2br(htmlspecialchars($item['description'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="details-row">
                    <div class="details-item">
                        <span class="details-label">Delivery Date:</span> 
                        <?php echo date('m/d/Y', strtotime($item['issued_date'])); ?>
                    </div>
                    <div class="details-item">
                        <span class="details-label">Supplier:</span> N/A
                    </div>
                </div>
                
                <div class="details-row">
                    <div class="details-item">
                        <span class="details-label">Ref:</span> PO24-12-263/SI7703
                    </div>
                </div>
                
                <div class="signature-section">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-name"><?php echo htmlspecialchars($item['issued_to_name']); ?></div>
                        <div>Signature Over Printed Name</div>
                        <div style="margin-top: 10px;"><?php echo htmlspecialchars($item['issued_to_position'] ?? 'Recipient'); ?></div>
                        <div>Position/Office</div>
                        <div><?php echo $current_date; ?></div>
                        <div>Date</div>
                    </div>
                    
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-name"><?php echo htmlspecialchars($item['issued_by_name']); ?></div>
                        <div>Signature Over Printed Name</div>
                        <div style="margin-top: 10px;">SAO-Materials Management/HOPSS</div>
                        <div>Position/Office</div>
                        <div><?php echo $current_date; ?></div>
                        <div>Date</div>
                    </div>
                </div>
                
                <div class="signature-section">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-name"><?php echo htmlspecialchars($item['signatory_name'] ?? '________________________'); ?></div>
                        <div>Signature Over Printed Name</div>
                        <div>Chairperson-Anesthesia Dept./Medical</div>
                        <div>Position/Office</div>
                        <div><?php echo $current_date; ?></div>
                        <div>Date</div>
                    </div>
                    
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-name">________________________</div>
                        <div>Signature Over Printed Name</div>
                        <div>Supply and/or Property Division/Unit</div>
                        <div>Position/Office</div>
                        <div><?php echo $current_date; ?></div>
                        <div>Date</div>
                    </div>
                </div>
                
                <div class="footer-note">
                    <strong>Original Copy 2</strong> - Supply and/or Property Division/Unit Recipient or end-user of the inventory
                </div>
            </div>
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                    }, 500);
                }
            </script>
        </body>
        </html>
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
    SELECT 
        e.id, 
        e.firstname, 
        e.lastname, 
        e.position,
        e.department_id,
        e.section_id,
        COALESCE(
            d.name,
            (SELECT d2.name FROM departments d2 WHERE d2.id = e.department_id),
            (SELECT d3.name FROM sections s2 LEFT JOIN departments d3 ON s2.department_id = d3.id WHERE s2.id = e.section_id),
            'No Department Assigned'
        ) as department_name,
        COALESCE(
            d.code,
            (SELECT d2.code FROM departments d2 WHERE d2.id = e.department_id),
            '000'
        ) as department_code,
        s.name as section_name
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN sections s ON e.section_id = s.id
    WHERE e.status = 'Active'
    ORDER BY e.lastname, e.firstname
");

if ($emp_result) {
    while ($emp = $emp_result->fetch_assoc()) {
        $all_employees[] = $emp;
    }
}

$current_issuances = $conn->query("
    SELECT 
        ei.id, 
        ei.inventory_id, 
        ei.quantity_issued, 
        ei.condition_on_issue,
        ei.remarks, 
        ei.issued_date, 
        ei.status, 
        ei.issuance_barcode,
        i.article_name, 
        i.property_no, 
        i.big_unit, 
        i.small_unit,
        CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
        'inventory' as item_type
    FROM equipment_issuance ei 
    INNER JOIN inventory i ON ei.inventory_id = i.id 
    INNER JOIN employees e ON ei.issued_to = e.id
    WHERE ei.status = 'issued'
    
    UNION ALL
    
    SELECT 
        ei.id, 
        ei.inventory_id, 
        ei.quantity_issued, 
        ei.condition_on_issue,
        ei.remarks, 
        ei.issued_date, 
        ei.status, 
        ei.issuance_barcode,
        s.article_name, 
        s.property_no, 
        s.big_unit, 
        s.small_unit,
        CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
        'semi_ppe' as item_type
    FROM equipment_issuance ei 
    INNER JOIN semi_ppe s ON ei.inventory_id = s.id 
    INNER JOIN employees e ON ei.issued_to = e.id
    WHERE ei.status = 'issued'
    
    ORDER BY issued_date DESC
");

$inventory_items = [];
$inv_result = $conn->query("
    SELECT 
        id, 
        article_name, 
        description,
        property_no, 
        barcode_data,
        qty_physical_count,
        big_unit, 
        big_quantity,
        small_unit, 
        pieces_per_big_unit,
        unit_value,
        'inventory' as item_type
    FROM inventory 
    WHERE qty_physical_count > 0 
    
    UNION ALL
    
    SELECT 
        id, 
        article_name, 
        description,
        property_no, 
        barcode_data,
        qty_physical_count,
        big_unit, 
        big_quantity,
        small_unit, 
        pieces_per_big_unit,
        unit_value,
        'semi_ppe' as item_type
    FROM semi_ppe 
    WHERE qty_physical_count > 0
    
    ORDER BY article_name
");
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
    if ($reissue_item && in_array($reissue_item['condition_on_return'], ['Non-Serviceable', 'For Condemn'])) {
        unset($_SESSION['reissue_from_returned']);
        $reissue_item = null;
        $_SESSION['error'] = 'Items returned as "Non-Serviceable" or "For Condemn" cannot be reissued.';
    }
    
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


/* Employee Items Table inside Modal */
.employee-items-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.employee-items-table th,
.employee-items-table td {
    padding: 10px 8px;
    border-bottom: 1px solid var(--border-light);
    text-align: left;
    vertical-align: middle;
}

.employee-items-table th {
    background: var(--light);
    font-weight: 600;
    color: var(--text-primary);
    position: sticky;
    top: 0;
}

.employee-items-table .text-center {
    text-align: center;
}

.employee-items-table .text-right {
    text-align: right;
}

.total-row {
    background: var(--accent-light);
    font-weight: bold;
}

.total-row td {
    border-top: 2px solid var(--primary);
}


.btn-barcode-sm {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 4px 8px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 11px;
    text-decoration: none;
}

.btn-barcode-sm:hover {
    background: #5a7ae6;
}


.modal-overlay {
    display: none;
    position: fixed;
    z-index: 10000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(3px);
    justify-content: center;
    align-items: center;
}

.modal-overlay.show {
    display: flex;
}

.modal-container {
    background-color: var(--white);
    margin: 5% auto;
    padding: 0;
    border-radius: 12px;
    width: 900px;
    max-width: 90%;
    box-shadow: 0 10px 30px rgba(107, 140, 255, 0.2);
    animation: modalSlideIn 0.3s;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
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
                <div id="reissue_employee_results_container" style="max-height:300px;overflow-y:auto">
    <table class="employee-results-table" style="width:100%">
        <thead>
            <tr>
                <th>Name</th>
                <th>Position</th>
                <th>Department</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody id="reissue_employee_results_body">
            <tr><td colspan="4" class="no-results" style="text-align:center;padding:40px">Type to search</td></tr>
        </tbody>
    </table>
</div>
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

<!-- ++++++++++++++++++++++++++++++++++++++++++++++++++++++ -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-users"></i> Issued Items by Employee (MMulti Item)</h2>
        <p>Click "View Items" to see all items issued to an employee</p>
    </div>
    <?php
    // Get grouped issuances by employee
    $grouped_issuances = $conn->query("
        SELECT 
            ei.issued_to,
            CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
            e.position as issued_to_position,
            COUNT(ei.id) as total_items,
            SUM(ei.quantity_issued) as total_quantity,
            SUM(i.unit_value * ei.quantity_issued) as total_value,
            MAX(ei.issued_date) as last_issue_date
        FROM equipment_issuance ei 
        JOIN inventory i ON ei.inventory_id = i.id 
        JOIN employees e ON ei.issued_to = e.id
        WHERE ei.status = 'issued'
        GROUP BY ei.issued_to, e.firstname, e.lastname, e.position
        ORDER BY e.lastname ASC
    ");
    ?>

    <?php
// Debug - Check if there are any issued items
$check_issued = $conn->query("SELECT COUNT(*) as count FROM equipment_issuance WHERE status = 'issued'");
$issued_count = $check_issued->fetch_assoc()['count'];
?>
<!-- Debug info (remove after testing) -->
<div style="background: #f0f0f0; padding: 10px; margin: 10px; border-radius: 5px; font-size: 12px;">
    <strong>Debug:</strong> Total issued items: <?php echo $issued_count; ?>
</div>
    <?php if($grouped_issuances && $grouped_issuances->num_rows > 0): ?>
    <div style="overflow-x: auto;">
        <table style="width: 100%; min-width: 800px;">
            <thead>
                <tr>
                    <th>Employee Name</th>
                    <th>Position</th>
                    <th class="text-center">Total Items</th>
                    <th class="text-center">Total Quantity</th>
                    <th class="text-right">Total Value</th>
                    <th>Last Issue Date</th>
                    <th class="text-center">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while($group = $grouped_issuances->fetch_assoc()): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($group['issued_to_name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($group['issued_to_position'] ?? 'N/A'); ?></td>
                    <td class="text-center"><?php echo $group['total_items']; ?></td>
                    <td class="text-center"><?php echo $group['total_quantity']; ?></td>
                    <td class="text-right">₱<?php echo number_format($group['total_value'], 2); ?></td>
                    <td><?php echo date('M d, Y', strtotime($group['last_issue_date'])); ?></td>
                    <td class="text-center">
                        <div class="action-buttons" style="justify-content: center;">
                           <button class="action-btn view" onclick="testEmployeeIssuances(<?php echo $group['issued_to']; ?>, '<?php echo htmlspecialchars(addslashes($group['issued_to_name'])); ?>')" title="View All Items">
    <i class="fas fa-eye"></i>
</button>
                            <a href="?print_grouped=<?php echo $group['issued_to']; ?>" class="action-btn print" target="_blank" title="Print All Items">
                                <i class="fas fa-print"></i>
                            </a>
                            <!-- RETURN BUTTON -->
        <button class="action-btn return" onclick="openBatchReturnModal(<?php echo $group['issued_to']; ?>, '<?php echo htmlspecialchars(addslashes($group['issued_to_name'])); ?>')" title="Return Multiple Items" style="background: #FF9800;">
            <i class="fas fa-undo-alt"></i> Return
        </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:40px">
        <i class="fas fa-users" style="font-size: 48px; color: #ccc; margin-bottom: 10px; display: block;"></i>
        <p>No items currently issued to any employee</p>
    </div>
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
            ei.issuance_barcode as property_no, 
            i.big_unit, 
            i.small_unit,
            CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
            CONCAT(ub.firstname, ' ', ub.lastname) as issued_by_name,
            CONCAT(original_emp.firstname, ' ', original_emp.lastname) as reissued_from_name,
            CONCAT(reissued_to_emp.firstname, ' ', reissued_to_emp.lastname) as reissued_to_name,
            'inventory' as item_type
        FROM equipment_issuance ei 
        JOIN inventory i ON ei.inventory_id = i.id 
        JOIN employees e ON ei.issued_to = e.id
        JOIN users ub ON ei.issued_by = ub.id 
        LEFT JOIN equipment_issuance original_iss ON ei.reissued_from_id = original_iss.id
        LEFT JOIN employees original_emp ON original_iss.issued_to = original_emp.id
        LEFT JOIN equipment_issuance reissued_iss ON ei.id = reissued_iss.reissued_from_id
        LEFT JOIN employees reissued_to_emp ON reissued_iss.issued_to = reissued_to_emp.id
        
        UNION ALL
        
        SELECT 
            ei.*, 
            s.article_name, 
            ei.issuance_barcode as property_no, 
            s.big_unit, 
            s.small_unit,
            CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
            CONCAT(ub.firstname, ' ', ub.lastname) as issued_by_name,
            CONCAT(original_emp.firstname, ' ', original_emp.lastname) as reissued_from_name,
            CONCAT(reissued_to_emp.firstname, ' ', reissued_to_emp.lastname) as reissued_to_name,
            'semi_ppe' as item_type
        FROM equipment_issuance ei 
        JOIN semi_ppe s ON ei.inventory_id = s.id 
        JOIN employees e ON ei.issued_to = e.id
        JOIN users ub ON ei.issued_by = ub.id 
        LEFT JOIN equipment_issuance original_iss ON ei.reissued_from_id = original_iss.id
        LEFT JOIN employees original_emp ON original_iss.issued_to = original_emp.id
        LEFT JOIN equipment_issuance reissued_iss ON ei.id = reissued_iss.reissued_from_id
        LEFT JOIN employees reissued_to_emp ON reissued_iss.issued_to = reissued_to_emp.id
        
        ORDER BY issued_date DESC LIMIT 100
    ");
    ?>
    <?php if($history && $history->num_rows > 0): ?>
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
                    <th>Issued By</th>
                    <th>Reissued From</th>
                    <th>Issued To</th>
                    <th>Condition</th>
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
                    $reissued_from = !empty($item['reissued_from_name']) ? $item['reissued_from_name'] : '—';
                    $issued_to = !empty($item['issued_to_name']) ? $item['issued_to_name'] : '—';
                ?>
                <tr>
                    <td style="text-align: center;">
                        <?php if(!empty($item['issuance_barcode'])): ?>
                        <img src="<?php echo SITE_URL; ?>/admin/barcode_generator_issued.php?code=<?php echo urlencode($item['issuance_barcode']); ?>&width=80&height=30" 
                             class="barcode-img" 
                             onclick="showBarcodeModal('<?php echo htmlspecialchars($item['issuance_barcode']); ?>', '<?php echo $safe_name; ?>')"
                             style="cursor: pointer;">
                        <br><small><?php echo htmlspecialchars(substr($item['issuance_barcode'], 0, 15)); ?>...</small>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td style="white-space: nowrap;"><?php echo date('M d, Y', strtotime($item['issued_date'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($item['article_name']); ?></strong></td>
                    <td><code><?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?></code></td>
                    <td class="text-center"><?php echo $item['quantity_issued']; ?></td>
                    <td><?php echo htmlspecialchars($unit_display); ?></td>
                    <td><?php echo htmlspecialchars($item['issued_by_name']); ?></td>
                    <td><?php echo htmlspecialchars($reissued_from); ?></td>
                    <td><?php echo htmlspecialchars($issued_to); ?></td>
                    <td><span class="condition-badge <?php echo $condition_class; ?>"><?php echo htmlspecialchars($item['condition_on_issue'] ?? 'Serviceable'); ?></span></td>
                    <td><span class="issue-status-badge <?php echo $status_class; ?>"><?php echo ucfirst($item['status']); ?></span></td>
                    <td><?php echo $item['actual_return'] ? date('M d, Y', strtotime($item['actual_return'])) : '—'; ?></td>
                    <td>
                        <div class="action-buttons">
                            <a href="?print_par=<?php echo $item['id']; ?>" class="action-btn print" target="_blank" title="Print">
                                <i class="fas fa-print"></i>
                            </a>

                             <button class="return-btn" onclick="openReturnModal(<?php echo $item['id']; ?>, '<?php echo addslashes($item['article_name']); ?>')" title="Return Item">
            <i class="fas fa-undo-alt"></i> Return
        </button>

                            <?php if($item['status'] == 'returned'): ?>
                                <?php 
                                $non_reissuable_conditions = ['Non-Serviceable', 'For Condemn'];
                                $can_reissue = !in_array($item['condition_on_return'], $non_reissuable_conditions);
                                ?>
                                <?php if($can_reissue): ?>
                                    <a href="?reissue_returned=<?php echo $item['id']; ?>" class="reissue-btn" onclick="return confirmReissueReturned(event, this)" title="Reissue">
                                        <i class="fas fa-redo-alt"></i> Reissue
                                    </a>
                                <?php else: ?>
                                    <span class="reissue-btn" style="background:#ccc; cursor: not-allowed;" title="Cannot reissue - Item is <?php echo $item['condition_on_return']; ?>">
                                        <i class="fas fa-ban"></i> Cannot Reissue
                                    </span>
                                <?php endif; ?>
                            <?php endif; ?>
                            <button class="action-btn view" onclick="viewIssuanceDetails(<?php echo $item['id']; ?>)" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align:center;padding:60px">
        <i class="fas fa-inbox" style="font-size:64px;color:#ccc"></i>
        <p>No issuance records found</p>
    </div>
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
<!-- Employee Items Modal (for grouped view) -->
<div id="employeeItemsModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 900px;">
        <div class="modal-header-settings">
            <h3><i class="fas fa-boxes"></i> Items Issued to: <span id="modal_employee_name"></span></h3>
            <span class="modal-close" onclick="closeEmployeeItemsModal()">&times;</span>
        </div>
        <div class="modal-scroll-content" id="employee_items_content">
            <div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading items...</div>
        </div>
        <div class="modal-footer-buttons">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeEmployeeItemsModal()">Close</button>
            <a href="#" id="print_all_items_btn" class="btn-modal" style="background-color: var(--accent); color: var(--text-primary);" target="_blank">
                <i class="fas fa-print"></i> Print All
            </a>
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


function viewEmployeeIssuances(employeeId, employeeName) {
    console.log('viewEmployeeIssuances called with:', employeeId, employeeName);
    
    const modal = document.getElementById('employeeItemsModal');
    const modalEmployeeName = document.getElementById('modal_employee_name');
    const printBtn = document.getElementById('print_all_items_btn');
    const contentDiv = document.getElementById('employee_items_content');
    
    if (!modal) {
        console.error('Modal not found!');
        alert('Modal not found. Please refresh the page.');
        return;
    }
    
    if (modalEmployeeName) modalEmployeeName.innerHTML = employeeName;
    if (printBtn) printBtn.href = '?print_grouped=' + employeeId;
    
    modal.style.display = 'flex';
    modal.style.visibility = 'visible';
    
    if (contentDiv) {
        contentDiv.innerHTML = '<div style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading items...</div>';
    }
    
    // Store for batch return
    window.currentReturnEmployeeId = employeeId;
    window.currentReturnEmployeeName = employeeName;
    window.currentReturnItems = [];
    
    fetch('?get_employee_issuances=' + employeeId)
        .then(response => response.json())
        .then(data => {
            console.log('Data received:', data);
            
            if (!contentDiv) return;
            
            if (data.success && data.items && data.items.length > 0) {
                window.currentReturnItems = data.items;
                
                let html = `
                    <div style="margin-bottom: 15px; padding: 10px; background: #f5f5f5; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="select_all_return_items" onchange="toggleSelectAllReturnItems()">
                            <strong>Select All Items</strong>
                        </label>
                        <button onclick="submitBatchReturn()" id="batchReturnBtn" style="background: #FF9800; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">
                            <i class="fas fa-undo-alt"></i> Return Selected Items
                        </button>
                    </div>
                    <div style="overflow-x: auto; max-height: 500px; overflow-y: auto;">
                        <table style="width:100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr style="background: #f5f5f5; position: sticky; top: 0;">
                                    <th style="padding: 10px; border-bottom: 2px solid #ddd; width: 40px;">Select</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Item Name</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Property No.</th>
                                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Qty</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Unit</th>
                                    <th style="padding: 10px; text-align: right; border-bottom: 2px solid #ddd;">Unit Value</th>
                                    <th style="padding: 10px; text-align: right; border-bottom: 2px solid #ddd;">Total</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Return Condition</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                let totalValue = 0;
                data.items.forEach((item, index) => {
                    const unitDisplay = item.big_unit ? item.big_unit : (item.small_unit || 'pcs');
                    const itemTotal = parseFloat(item.unit_value) * parseFloat(item.quantity_issued);
                    totalValue += itemTotal;
                    
                    html += `
                        <tr style="border-bottom: 1px solid #eee;" data-item-id="${item.id}" data-item-index="${index}">
                            <td style="padding: 8px; text-align: center;">
                                <input type="checkbox" class="return_item_checkbox" data-id="${item.id}" data-index="${index}" onchange="updateReturnItemSelection(${index}, this.checked)">
                            </td>
                            <td style="padding: 8px;"><strong>${escapeHtml(item.article_name)}</strong></td>
                            <td style="padding: 8px;"><code>${escapeHtml(item.issuance_barcode || item.property_no || 'N/A')}</code></td>
                            <td style="padding: 8px; text-align: center;">${item.quantity_issued}</td>
                            <td style="padding: 8px;">${escapeHtml(unitDisplay)}</td>
                            <td style="padding: 8px; text-align: right;">₱${parseFloat(item.unit_value).toFixed(2)}</td>
                            <td style="padding: 8px; text-align: right;">₱${itemTotal.toFixed(2)}</td>
                            <td style="padding: 8px;">
                                <select class="return_condition_select" data-id="${item.id}" data-index="${index}" disabled style="padding: 6px; border-radius: 4px; border: 1px solid #ddd; width: 100%; font-size: 11px;">
                                    <option value="">-- Select --</option>
                                    <option value="Serviceable">Serviceable - Returns to stock</option>
                                    <option value="Non-Serviceable">Non-Serviceable - Cannot reissue</option>
                                    <option value="For Condemn">For Condemn - Cannot reissue</option>
                                    <option value="Under Repair">Under Repair - Can reissue</option>
                                </select>
                             </td>
                        </tr>
                    `;
                });
                
                html += `
                                <tr style="background: #FFD8E0; font-weight: bold;">
                                    <td colspan="6" style="padding: 10px; text-align: right;"><strong>GRAND TOTAL:</strong></td>
                                    <td style="padding: 10px; text-align: right;"><strong>₱${totalValue.toFixed(2)}</strong></td>
                                    <td></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div id="batch_return_warning" style="margin-top: 15px; padding: 10px; background: #FFF3E0; border-left: 4px solid #FF9800; border-radius: 4px; display: none;">
                        <i class="fas fa-exclamation-triangle"></i> <span id="batch_return_warning_text"></span>
                    </div>
                `;
                contentDiv.innerHTML = html;
            } else {
                contentDiv.innerHTML = '<div style="padding:40px;text-align:center;color:#999;">No items found for this employee.</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (contentDiv) {
                contentDiv.innerHTML = '<div style="padding:40px;text-align:center;color:red;">Error loading items. Please try again.</div>';
            }
        });
}







// Store selected items for batch return
let selectedReturnItems = [];

function toggleSelectAllReturnItems() {
    const selectAllCheckbox = document.getElementById('select_all_return_items');
    const checkboxes = document.querySelectorAll('.return_item_checkbox');
    const isChecked = selectAllCheckbox.checked;
    
    checkboxes.forEach((checkbox, idx) => {
        checkbox.checked = isChecked;
        const index = parseInt(checkbox.getAttribute('data-index'));
        updateReturnItemSelection(index, isChecked);
    });
}

function updateReturnItemSelection(index, isSelected) {
    const conditionSelect = document.querySelector(`.return_condition_select[data-index="${index}"]`);
    
    if (conditionSelect) {
        conditionSelect.disabled = !isSelected;
        if (!isSelected) {
            conditionSelect.value = '';
            // Remove from selected array
            selectedReturnItems = selectedReturnItems.filter(item => item.index !== index);
        } else {
            // Add to selected array
            const existing = selectedReturnItems.find(item => item.index === index);
            if (!existing) {
                selectedReturnItems.push({
                    index: index,
                    id: conditionSelect.getAttribute('data-id'),
                    condition: ''
                });
            }
        }
    }
    
    updateBatchReturnWarning();
}





// Store selected items for batch return - use global variable
let selectedReturnItemsGlobal = [];
let currentReturnItemsGlobal = [];
let currentReturnEmployeeIdGlobal = null;

function openBatchReturnModal(employeeId, employeeName) {
    // Reset global variables
    selectedReturnItemsGlobal = [];
    currentReturnItemsGlobal = [];
    currentReturnEmployeeIdGlobal = employeeId;
    
    const modal = document.getElementById('employeeItemsModal');
    const modalEmployeeName = document.getElementById('modal_employee_name');
    const contentDiv = document.getElementById('employee_items_content');
    
    if (!modal) {
        console.error('Modal not found!');
        alert('Modal not found. Please refresh the page.');
        return;
    }
    
    if (modalEmployeeName) modalEmployeeName.innerHTML = employeeName;
    
    modal.style.display = 'flex';
    modal.style.visibility = 'visible';
    
    if (contentDiv) {
        contentDiv.innerHTML = '<div style="text-align:center;padding:40px;"><i class="fas fa-spinner fa-spin"></i> Loading items...</div>';
    }
    
    fetch('?get_employee_issuances=' + employeeId)
        .then(response => response.json())
        .then(data => {
            if (!contentDiv) return;
            
            if (data.success && data.items && data.items.length > 0) {
                currentReturnItemsGlobal = data.items;
                
                let html = `
                    <div style="margin-bottom: 15px; padding: 10px; background: #f5f5f5; border-radius: 8px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                        <label style="display: flex; align-items: center; gap: 8px;">
                            <input type="checkbox" id="select_all_return_items" onchange="toggleSelectAllReturnItems()">
                            <strong>Select All Items</strong>
                        </label>
                        <button onclick="submitBatchReturn()" id="batchReturnBtn" style="background: #FF9800; color: white; border: none; padding: 8px 16px; border-radius: 6px; cursor: pointer;">
                            <i class="fas fa-undo-alt"></i> Return Selected Items
                        </button>
                    </div>
                    <div style="overflow-x: auto; max-height: 500px; overflow-y: auto;">
                        <table style="width:100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr style="background: #f5f5f5; position: sticky; top: 0;">
                                    <th style="padding: 10px; border-bottom: 2px solid #ddd; width: 40px;">Select</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Item Name</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Property No.</th>
                                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #ddd;">Qty</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Unit</th>
                                    <th style="padding: 10px; text-align: right; border-bottom: 2px solid #ddd;">Unit Value</th>
                                    <th style="padding: 10px; text-align: right; border-bottom: 2px solid #ddd;">Total</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #ddd;">Return Condition</th>
                                 </table>
                            </thead>
                            <tbody>
                `;
                
                let totalValue = 0;
                data.items.forEach((item, index) => {
                    const unitDisplay = item.big_unit ? item.big_unit : (item.small_unit || 'pcs');
                    const itemTotal = parseFloat(item.unit_value) * parseFloat(item.quantity_issued);
                    totalValue += itemTotal;
                    
                    html += `
                        <tr style="border-bottom: 1px solid #eee;">
                            <td style="padding: 8px; text-align: center;">
                                <input type="checkbox" class="return_item_checkbox" data-id="${item.id}" data-index="${index}" onchange="updateReturnItemSelection(this, ${index})">
                              </div>
                            <td style="padding: 8px;"><strong>${escapeHtml(item.article_name)}</strong></div>
                            <td style="padding: 8px;"><code>${escapeHtml(item.issuance_barcode || item.property_no || 'N/A')}</code></div>
                            <td style="padding: 8px; text-align: center;">${item.quantity_issued}</div>
                            <td style="padding: 8px;">${escapeHtml(unitDisplay)}</div>
                            <td style="padding: 8px; text-align: right;">₱${parseFloat(item.unit_value).toFixed(2)}</div>
                            <td style="padding: 8px; text-align: right;">₱${itemTotal.toFixed(2)}</div>
                            <td style="padding: 8px;">
                                <select class="return_condition_select" data-id="${item.id}" data-index="${index}" disabled style="padding: 6px; border-radius: 4px; border: 1px solid #ddd; width: 100%; font-size: 11px;" onchange="updateReturnCondition(this, ${index})">
                                    <option value="">-- Select --</option>
                                    <option value="Serviceable">Serviceable - Returns to stock</option>
                                    <option value="Non-Serviceable">Non-Serviceable - Cannot reissue</option>
                                    <option value="For Condemn">For Condemn - Cannot reissue</option>
                                    <option value="Under Repair">Under Repair - Can reissue</option>
                                </select>
                              </div>
                          </div>
                    `;
                });
                
                html += `
                                <tr style="background: #FFD8E0; font-weight: bold;">
                                    <td colspan="6" style="padding: 10px; text-align: right;"><strong>GRAND TOTAL:</strong></div>
                                    <td style="padding: 10px; text-align: right;"><strong>₱${totalValue.toFixed(2)}</strong></div>
                                    <td></div>
                                  </tr>
                            </tbody>
                          </table>
                    </div>
                    <div id="batch_return_warning" style="margin-top: 15px; padding: 10px; background: #FFF3E0; border-left: 4px solid #FF9800; border-radius: 4px; display: none;">
                        <i class="fas fa-exclamation-triangle"></i> <span id="batch_return_warning_text"></span>
                    </div>
                `;
                contentDiv.innerHTML = html;
            } else {
                contentDiv.innerHTML = '<div style="padding:40px;text-align:center;color:#999;">No items found for this employee.</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            if (contentDiv) {
                contentDiv.innerHTML = '<div style="padding:40px;text-align:center;color:red;">Error loading items. Please try again.</div>';
            }
        });
}

function toggleSelectAllReturnItems() {
    const selectAllCheckbox = document.getElementById('select_all_return_items');
    const checkboxes = document.querySelectorAll('.return_item_checkbox');
    const isChecked = selectAllCheckbox.checked;
    
    checkboxes.forEach((checkbox) => {
        checkbox.checked = isChecked;
        const index = parseInt(checkbox.getAttribute('data-index'));
        const conditionSelect = document.querySelector(`.return_condition_select[data-index="${index}"]`);
        
        if (conditionSelect) {
            conditionSelect.disabled = !isChecked;
            if (!isChecked) {
                conditionSelect.value = '';
                // Remove from selected array
                selectedReturnItemsGlobal = selectedReturnItemsGlobal.filter(item => item.index !== index);
            } else {
                // Add to selected array if not already there
                const existing = selectedReturnItemsGlobal.find(item => item.index === index);
                if (!existing) {
                    selectedReturnItemsGlobal.push({
                        index: index,
                        id: conditionSelect.getAttribute('data-id'),
                        condition: ''
                    });
                }
            }
        }
    });
    
    updateBatchReturnWarning();
}

function updateReturnItemSelection(checkbox, index) {
    const isSelected = checkbox.checked;
    const conditionSelect = document.querySelector(`.return_condition_select[data-index="${index}"]`);
    
    if (conditionSelect) {
        conditionSelect.disabled = !isSelected;
        if (!isSelected) {
            conditionSelect.value = '';
            // Remove from selected array
            selectedReturnItemsGlobal = selectedReturnItemsGlobal.filter(item => item.index !== index);
        } else {
            // Add to selected array
            const existing = selectedReturnItemsGlobal.find(item => item.index === index);
            if (!existing) {
                selectedReturnItemsGlobal.push({
                    index: index,
                    id: conditionSelect.getAttribute('data-id'),
                    condition: ''
                });
            }
        }
    }
    
    updateBatchReturnWarning();
}

function updateReturnCondition(select, index) {
    const condition = select.value;
    const itemIndex = selectedReturnItemsGlobal.findIndex(item => item.index === index);
    
    if (itemIndex !== -1) {
        selectedReturnItemsGlobal[itemIndex].condition = condition;
    } else {
        selectedReturnItemsGlobal.push({
            index: index,
            id: select.getAttribute('data-id'),
            condition: condition
        });
    }
    
    updateBatchReturnWarning();
}

function updateBatchReturnWarning() {
    const warningDiv = document.getElementById('batch_return_warning');
    const warningText = document.getElementById('batch_return_warning_text');
    
    if (!warningDiv) return;
    
    const selectedCount = selectedReturnItemsGlobal.length;
    const missingCondition = selectedReturnItemsGlobal.filter(item => !item.condition);
    const nonReissuable = selectedReturnItemsGlobal.filter(item => item.condition === 'Non-Serviceable' || item.condition === 'For Condemn');
    
    if (selectedCount === 0) {
        warningDiv.style.display = 'none';
        const btn = document.getElementById('batchReturnBtn');
        if (btn) btn.disabled = false;
    } else if (missingCondition.length > 0) {
        warningDiv.style.display = 'block';
        warningText.innerHTML = `⚠️ Please select return condition for ${missingCondition.length} item(s).`;
        const btn = document.getElementById('batchReturnBtn');
        if (btn) btn.disabled = true;
    } else {
        warningDiv.style.display = 'block';
        if (nonReissuable.length > 0) {
            warningText.innerHTML = `⚠️ ${nonReissuable.length} item(s) marked as "${nonReissuable.map(i => i.condition).join(', ')}" will NOT be available for reissue.`;
        } else {
            warningText.innerHTML = `✅ ${selectedCount} item(s) ready to return.`;
        }
        const btn = document.getElementById('batchReturnBtn');
        if (btn) btn.disabled = false;
    }
}

function submitBatchReturn() {
    const itemsToReturn = selectedReturnItemsGlobal.filter(item => item.condition);
    
    if (itemsToReturn.length === 0) {
        alert('Please select at least one item and set its return condition.');
        return;
    }
    
    // Get item details for confirmation message
    let confirmMessage = `Return the following ${itemsToReturn.length} item(s)?\n\n`;
    itemsToReturn.forEach(item => {
        const itemData = currentReturnItemsGlobal.find(i => i.id == item.id);
        if (itemData) {
            confirmMessage += `- ${itemData.article_name} (${item.condition})\n`;
        }
    });
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    const batchReturnBtn = document.getElementById('batchReturnBtn');
    batchReturnBtn.disabled = true;
    batchReturnBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    let processed = 0;
    let successCount = 0;
    let errors = [];
    
    itemsToReturn.forEach(item => {
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=return_item&issuance_id=' + item.id + '&condition_on_return=' + encodeURIComponent(item.condition)
        })
        .then(response => response.json())
        .then(data => {
            processed++;
            if (data.success) {
                successCount++;
            } else {
                const itemData = currentReturnItemsGlobal.find(i => i.id == item.id);
                errors.push(`${itemData ? itemData.article_name : item.id}: ${data.message}`);
            }
            
            if (processed === itemsToReturn.length) {
                if (errors.length > 0) {
                    alert(`Returned ${successCount} of ${itemsToReturn.length} items.\nErrors:\n${errors.join('\n')}`);
                } else {
                    alert(`Successfully returned ${successCount} item(s).`);
                }
                location.reload();
            }
        })
        .catch(error => {
            processed++;
            const itemData = currentReturnItemsGlobal.find(i => i.id == item.id);
            errors.push(`${itemData ? itemData.article_name : item.id}: Network error`);
            if (processed === itemsToReturn.length) {
                alert(`Returned ${successCount} of ${itemsToReturn.length} items.\nErrors:\n${errors.join('\n')}`);
                location.reload();
            }
        });
    });
}











function updateBatchReturnCondition(index, condition) {
    const itemIndex = selectedReturnItems.findIndex(item => item.index === index);
    if (itemIndex !== -1) {
        selectedReturnItems[itemIndex].condition = condition;
    } else {
        selectedReturnItems.push({
            index: index,
            id: document.querySelector(`.return_condition_select[data-index="${index}"]`).getAttribute('data-id'),
            condition: condition
        });
    }
    updateBatchReturnWarning();
}

function updateBatchReturnWarning() {
    const warningDiv = document.getElementById('batch_return_warning');
    const warningText = document.getElementById('batch_return_warning_text');
    
    if (!warningDiv) return;
    
    const selectedCount = selectedReturnItems.length;
    const missingCondition = selectedReturnItems.filter(item => !item.condition);
    const nonReissuable = selectedReturnItems.filter(item => item.condition === 'Non-Serviceable' || item.condition === 'For Condemn');
    
    if (selectedCount === 0) {
        warningDiv.style.display = 'none';
        document.getElementById('batchReturnBtn').disabled = false;
    } else if (missingCondition.length > 0) {
        warningDiv.style.display = 'block';
        warningText.innerHTML = `⚠️ Please select return condition for ${missingCondition.length} item(s).`;
        document.getElementById('batchReturnBtn').disabled = true;
    } else {
        warningDiv.style.display = 'block';
        if (nonReissuable.length > 0) {
            warningText.innerHTML = `⚠️ ${nonReissuable.length} item(s) marked as "${nonReissuable.map(i => i.condition).join(', ')}" will NOT be available for reissue.`;
        } else {
            warningText.innerHTML = `✅ ${selectedCount} item(s) ready to return.`;
        }
        document.getElementById('batchReturnBtn').disabled = false;
    }
}

// Add event listener for condition change
function attachConditionChangeListeners() {
    document.querySelectorAll('.return_condition_select').forEach(select => {
        select.removeEventListener('change', handleConditionChange);
        select.addEventListener('change', handleConditionChange);
    });
}

function handleConditionChange(e) {
    const index = parseInt(e.target.getAttribute('data-index'));
    const condition = e.target.value;
    updateBatchReturnCondition(index, condition);
}

function submitBatchReturn() {
    const itemsToReturn = selectedReturnItems.filter(item => item.condition);
    
    if (itemsToReturn.length === 0) {
        alert('Please select at least one item and set its return condition.');
        return;
    }
    
    // Get item details for confirmation message
    let confirmMessage = `Return the following ${itemsToReturn.length} item(s)?\n\n`;
    itemsToReturn.forEach(item => {
        const itemData = window.currentReturnItems.find(i => i.id == item.id);
        if (itemData) {
            confirmMessage += `- ${itemData.article_name} (${item.condition})\n`;
        }
    });
    
    if (!confirm(confirmMessage)) {
        return;
    }
    
    const batchReturnBtn = document.getElementById('batchReturnBtn');
    batchReturnBtn.disabled = true;
    batchReturnBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    let processed = 0;
    let successCount = 0;
    let errors = [];
    
    itemsToReturn.forEach(item => {
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'action=return_item&issuance_id=' + item.id + '&condition_on_return=' + encodeURIComponent(item.condition)
        })
        .then(response => response.text())
        .then(text => {
            try {
                const data = JSON.parse(text);
                processed++;
                if (data.success) {
                    successCount++;
                } else {
                    const itemData = window.currentReturnItems.find(i => i.id == item.id);
                    errors.push(`${itemData ? itemData.article_name : item.id}: ${data.message}`);
                }
            } catch(e) {
                processed++;
                const itemData = window.currentReturnItems.find(i => i.id == item.id);
                errors.push(`${itemData ? itemData.article_name : item.id}: Invalid server response`);
            }
            
            if (processed === itemsToReturn.length) {
                if (errors.length > 0) {
                    alert(`Returned ${successCount} of ${itemsToReturn.length} items.\nErrors:\n${errors.join('\n')}`);
                } else {
                    alert(`Successfully returned ${successCount} item(s).`);
                }
                location.reload();
            }
        })
        .catch(error => {
            processed++;
            const itemData = window.currentReturnItems.find(i => i.id == item.id);
            errors.push(`${itemData ? itemData.article_name : item.id}: Network error`);
            if (processed === itemsToReturn.length) {
                alert(`Returned ${successCount} of ${itemsToReturn.length} items.\nErrors:\n${errors.join('\n')}`);
                location.reload();
            }
        });
    });
}


function closeEmployeeItemsModal() {
    document.getElementById('employeeItemsModal').style.display = 'none';
    selectedReturnItems = [];
    window.currentReturnItems = [];
}





// Test function to check if button click is working
function testEmployeeIssuances(employeeId, employeeName) {
    alert('Button clicked! Employee ID: ' + employeeId + ', Name: ' + employeeName);
    viewEmployeeIssuances(employeeId, employeeName);
}


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
        tbody.innerHTML = '<tr><td colspan="4" class="no-results" style="text-align:center;padding:40px">No employees found</td></tr>';
        return;
    }
    let html = '';
    employees.forEach(emp => {
        const isSelected = (selectedReissueEmployeeId == emp.id);
        const deptDisplay = emp.department_name && emp.department_name !== '—' ? emp.department_name : '—';
        html += `<tr class="employee-result-row ${isSelected ? 'selected' : ''}" onclick="selectReissueEmployee(${emp.id}, '${escapeHtml(emp.firstname + ' ' + emp.lastname)}', '${escapeHtml(emp.position || '')}', '${escapeHtml(deptDisplay)}')">
            <td>${escapeHtml(emp.lastname + ', ' + emp.firstname)}</td>
            <td>${escapeHtml(emp.position || '—')}</td>
            <td>${escapeHtml(deptDisplay)}</td>
            <td><button class="select-employee-btn" onclick="event.stopPropagation();selectReissueEmployee(${emp.id}, '${escapeHtml(emp.firstname + ' ' + emp.lastname)}', '${escapeHtml(emp.position || '')}', '${escapeHtml(deptDisplay)}')">Select</button></td>
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
    const itemTypeFilter = document.getElementById('item_type_filter')?.value || 'all';
    const resultDiv = document.getElementById('property_search_result');
    
    if (!searchTerm) { 
        resultDiv.innerHTML = '<div class="alert-warning">Please enter search term</div>'; 
        resultDiv.classList.add('show'); 
        return; 
    }
    
    let found = inventoryData.filter(item => {
        // Apply item type filter first
        if (itemTypeFilter !== 'all' && item.item_type !== itemTypeFilter) {
            return false;
        }
        
        const propertyNo = (item.property_no || '').toLowerCase();
        const articleName = (item.article_name || '').toLowerCase();
        
        // Search both property number and article name
        return propertyNo.includes(searchTerm) || articleName.includes(searchTerm);
    });
    
    // Also try partial matches if no results found
    if (found.length === 0 && searchTerm.length > 2) {
        found = inventoryData.filter(item => {
            if (itemTypeFilter !== 'all' && item.item_type !== itemTypeFilter) {
                return false;
            }
            const propertyNo = (item.property_no || '').toLowerCase();
            const articleName = (item.article_name || '').toLowerCase();
            return propertyNo.includes(searchTerm) || articleName.includes(searchTerm);
        });
    }
    
    if (found.length > 0) {
        let html = `<div style="padding: 8px; background: var(--accent-light); margin-bottom: 10px; border-radius: 8px;">
                        <strong>Found ${found.length} item(s)</strong>
                    </div>`;
        found.forEach(item => {
            const isInCart = cartItems.some(cartItem => cartItem.id == item.id);
            const unitDisplay = item.big_unit ? item.big_unit : (item.small_unit || 'pcs');
            const itemTypeLabel = item.item_type === 'semi_ppe' ? 'Semi-Expendable' : 'Inventory';
            html += `<div class="result-property-card">
                <div class="result-property-info">
                    <h5>${escapeHtml(item.article_name)}</h5>
                    <p>Property: <strong>${escapeHtml(item.property_no || 'N/A')}</strong> | Available: ${item.qty_physical_count} ${escapeHtml(unitDisplay)}</p>
                    <small class="text-muted">Type: ${itemTypeLabel}</small>
                </div>
                <div>${isInCart ? '<button disabled style="background:#ccc">In Cart</button>' : `<button class="btn-add-property" onclick="addToCart(${item.id}, 1)">Issue Item</button>`}</div>
            </div>`;
        });
        resultDiv.innerHTML = html;
        resultDiv.classList.add('show');
    } else {
        resultDiv.innerHTML = `<div class="result-property-card">
            <div class="result-property-info">
                <h5>No items found for: "${escapeHtml(searchTerm)}"</h5>
                <p>Try searching by property number or article name</p>
            </div>
        </div>`;
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
    
    // Search on barcode_data OR property_no (exact match on either)
    const found = inventoryData.filter(item => {
        const itemBarcode = (item.barcode_data || '').toLowerCase();
        const itemPropertyNo = (item.property_no || '').toLowerCase();
        const searchTerm = barcode.toLowerCase();
        
        // Check both barcode_data AND property_no
        return itemBarcode === searchTerm || itemPropertyNo === searchTerm;
    });
    
    if (found.length > 0) {
        let html = '';
        found.forEach(item => {
            const isInCart = cartItems.some(cartItem => cartItem.id == item.id);
            
            // Calculate correct display values
            const bigQuantity = item.big_quantity || 1;
            const piecesPerBig = item.pieces_per_big_unit || 1;
            const totalQuantity = bigQuantity * piecesPerBig;
            const bigUnitDisplay = `${bigQuantity} ${item.big_unit || 'Unit'}`;
            const smallUnitDisplay = `${piecesPerBig} ${item.small_unit || 'Piece'}`;
            const itemTypeLabel = item.item_type === 'semi_ppe' ? 'Semi-Expendable' : 'Inventory';
            const displayBarcode = item.barcode_data || item.property_no || 'N/A';
            
            html += `<div class="result-item" style="background: rgba(255,255,255,0.15); border-radius: 12px; margin-bottom: 15px;">
                <div class="result-info" style="flex: 2;">
                    <h5 style="margin: 0 0 10px 0; font-size: 16px;">${escapeHtml(item.article_name)}</h5>
                    <table style="width: 100%; font-size: 13px;">
                        <tr>
                            <td style="padding: 4px 0;"><strong>Property No:</strong></td>
                            <td style="padding: 4px 0;"><code>${escapeHtml(item.property_no || 'N/A')}</code></td>
                            <td style="padding: 4px 0;"><strong>Barcode:</strong></td>
                            <td style="padding: 4px 0;"><code>${escapeHtml(displayBarcode)}</code></td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0;"><strong>Big Unit:</strong></td>
                            <td style="padding: 4px 0;">${escapeHtml(bigUnitDisplay)}</td>
                            <td style="padding: 4px 0;"><strong>Small Unit:</strong></td>
                            <td style="padding: 4px 0;">${escapeHtml(smallUnitDisplay)}</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0;"><strong>Total Quantity:</strong></td>
                            <td style="padding: 4px 0;"><strong style="color: #4CAF50;">${totalQuantity} ${escapeHtml(item.small_unit || 'pieces')}</strong></td>
                            <td style="padding: 4px 0;"><strong>Unit Value:</strong></td>
                            <td style="padding: 4px 0;">₱${parseFloat(item.unit_value).toFixed(2)}</td>
                        </tr>
                        <tr>
                            <td style="padding: 4px 0;"><strong>Total Value:</strong></td>
                            <td style="padding: 4px 0;">₱${(totalQuantity * parseFloat(item.unit_value)).toFixed(2)}</td>
                            <td style="padding: 4px 0;"><strong>Type:</strong></td>
                            <td style="padding: 4px 0;">${itemTypeLabel}</td>
                        </tr>
                    </table>
                </div>
                <div style="margin-top: 10px;">
                    ${isInCart ? 
                        '<button disabled style="background:#ccc; padding:8px 16px; border:none; border-radius:6px;">In Cart</button>' : 
                        `<button class="btn-add-item" onclick="addToCart(${item.id}, 1)" style="background:#4CAF50; color:white; border:none; padding:8px 16px; border-radius:6px; cursor:pointer;">
                            <i class="fas fa-cart-plus"></i> Issue me asdgbasdgaksd
                        </button>`
                    }
                </div>
            </div>`;
        });
        resultDiv.innerHTML = html;
        resultDiv.classList.add('show');
        document.getElementById('barcode_input').value = '';
    } else {
        resultDiv.innerHTML = `<div class="result-item" style="background: rgba(0,0,0,0.2); border-radius: 12px; padding: 20px; text-align: center;">
            <div class="result-info">
                <h5><i class="fas fa-exclamation-triangle"></i> Item Not Found</h5>
                <p>No item found with barcode: <strong>"${escapeHtml(barcode)}"</strong></p>
                <p>Please check the barcode and try again.</p>
            </div>
        </div>`;
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
            id: item.id, 
            name: item.article_name, 
            property_no: item.property_no,
            barcode_data: item.barcode_data,  // ADD THIS LINE
            big_unit: item.big_unit, 
            small_unit: item.small_unit,
            available_qty: item.qty_physical_count, 
            unit_value: item.unit_value, 
            quantity: (qty || 1),
            item_type: item.item_type 
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
    document.querySelectorAll('input[name="item_type[]"]').forEach(e => e.remove());
    
    const form = document.getElementById('issueForm');
    cartItems.forEach(item => {
        let ii = document.createElement('input');
        ii.type = 'hidden'; ii.name = 'inventory_ids[]'; ii.value = item.id;
        form.appendChild(ii);
        
        let qi = document.createElement('input');
        qi.type = 'hidden'; qi.name = 'quantities[]'; qi.value = item.quantity;
        form.appendChild(qi);
        
        let ti = document.createElement('input');
        ti.type = 'hidden'; ti.name = 'item_type[]'; ti.value = item.item_type;
        form.appendChild(ti);
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
    if (!condition) { 
        alert('Please select a condition'); 
        return; 
    }
    
    // Show loading state
    const confirmBtn = document.querySelector('#returnModal .confirm-btn-confirm');
    const originalText = confirmBtn.innerHTML;
    confirmBtn.disabled = true;
    confirmBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    fetch(window.location.href, {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'action=return_item&issuance_id=' + currentReturnId + '&condition_on_return=' + encodeURIComponent(condition)
    })
    .then(response => response.text())  // First get as text to debug
    .then(text => {
        console.log('Raw response:', text);  // Debug: see what's returned
        
        try {
            const data = JSON.parse(text);
            alert(data.message);
            if (data.success) {
                location.reload();
            } else {
                confirmBtn.disabled = false;
                confirmBtn.innerHTML = originalText;
            }
        } catch(e) {
            console.error('JSON parse error:', e);
            alert('Error: Invalid response from server. Check console for details.');
            confirmBtn.disabled = false;
            confirmBtn.innerHTML = originalText;
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        alert('Error: ' + error);
        confirmBtn.disabled = false;
        confirmBtn.innerHTML = originalText;
    });
    
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
            console.log('Response:', data);
            
            if (data.success) {
                const item = data.data;
                const unitDisplay = item.big_unit ? item.big_unit : (item.small_unit || 'pcs');
                const conditionClass = 'condition-' + (item.condition_on_issue || 'Serviceable').replace(/ /g, '');
                const statusClass = 'issue-status-' + item.status;
                // Use issuance_barcode for property number display
                const displayPropertyNo = item.issuance_barcode || item.property_no || 'N/A';
                
                body.innerHTML = `
                    <div class="view-detail-row">
                        <div class="view-detail-label">Item Name:</div>
                        <div class="view-detail-value"><strong>${escapeHtml(item.article_name)}</strong></div>
                    </div>
                    <div class="view-detail-row">
                        <div class="view-detail-label">Property No.:</div>
                        <div class="view-detail-value"><code>${escapeHtml(displayPropertyNo)}</code></div>
                    </div>
                    <div class="view-detail-row">
                        <div class="view-detail-label">Description:</div>
                        <div class="view-detail-value">${escapeHtml(item.description || 'N/A')}</div>
                    </div>
                    <div class="view-detail-row">
                        <div class="view-detail-label">Quantity:</div>
                        <div class="view-detail-value">${item.quantity_issued} ${escapeHtml(unitDisplay)}</div>
                    </div>
                    <div class="view-detail-row">
                        <div class="view-detail-label">Unit Value:</div>
                        <div class="view-detail-value">₱${parseFloat(item.unit_value).toFixed(2)}</div>
                    </div>
                    <div class="view-detail-row">
                        <div class="view-detail-label">Total Value:</div>
                        <div class="view-detail-value">₱${(item.quantity_issued * item.unit_value).toFixed(2)}</div>
                    </div>
                    <div class="view-detail-row">
                        <div class="view-detail-label">Issued To:</div>
                        <div class="view-detail-value">${escapeHtml(item.issued_to_name)}${item.issued_to_position ? ' - ' + escapeHtml(item.issued_to_position) : ''}</div>
                    </div>
                    <div class="view-detail-row">
                        <div class="view-detail-label">Condition on Issue:</div>
                        <div class="view-detail-value"><span class="condition-badge ${conditionClass}">${escapeHtml(item.condition_on_issue || 'Serviceable')}</span></div>
                    </div>
                    <div class="view-detail-row">
                        <div class="view-detail-label">Issued By:</div>
                        <div class="view-detail-value">${escapeHtml(item.issued_by_name)}</div>
                    </div>
                    <div class="view-detail-row">
                        <div class="view-detail-label">Issued Date:</div>
                        <div class="view-detail-value">${new Date(item.issued_date).toLocaleString()}</div>
                    </div>
                    <div class="view-detail-row">
                        <div class="view-detail-label">Issuance Barcode:</div>
                        <div class="view-detail-value"><code>${escapeHtml(item.issuance_barcode || 'N/A')}</code></div>
                    </div>
                    <div class="view-detail-row">
                        <div class="view-detail-label">Signatory:</div>
                        <div class="view-detail-value">${escapeHtml(item.signatory_name || 'N/A')}</div>
                    </div>
                    <div class="view-detail-row">
                        <div class="view-detail-label">Status:</div>
                        <div class="view-detail-value"><span class="issue-status-badge ${statusClass}">${escapeHtml(item.status)}</span></div>
                    </div>
                    ${item.actual_return ? `<div class="view-detail-row">
                        <div class="view-detail-label">Return Date:</div>
                        <div class="view-detail-value">${new Date(item.actual_return).toLocaleString()}</div>
                    </div>` : ''}
                    ${item.condition_on_return ? `<div class="view-detail-row">
                        <div class="view-detail-label">Condition on Return:</div>
                        <div class="view-detail-value">${escapeHtml(item.condition_on_return)}</div>
                    </div>` : ''}
                    ${item.reissued_from_name ? `<div class="view-detail-row">
                        <div class="view-detail-label">Reissued From:</div>
                        <div class="view-detail-value">${escapeHtml(item.reissued_from_name)}</div>
                    </div>` : ''}
                    ${item.reissued_to_name ? `<div class="view-detail-row">
                        <div class="view-detail-label">Reissued To:</div>
                        <div class="view-detail-value">${escapeHtml(item.reissued_to_name)}</div>
                    </div>` : ''}
                    ${item.remarks ? `<div class="view-detail-row">
                        <div class="view-detail-label">Remarks:</div>
                        <div class="view-detail-value">${escapeHtml(item.remarks)}</div>
                    </div>` : ''}
                `;
            } else {
                body.innerHTML = '<div style="color:red;text-align:center;padding:20px">' + (data.message || 'Error loading details') + '</div>';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            body.innerHTML = '<div style="color:red;text-align:center;padding:20px">Error loading details: ' + error.message + '</div>';
        });
}

// ============================================
// BARCODE MODAL FUNCTIONS - FIXED
// ============================================

function showBarcodeModal(barcode, itemName) {
    document.getElementById('barcodeModalTitle').innerHTML = itemName;
    document.getElementById('barcodeModalImage').innerHTML = `<img src="<?php echo SITE_URL; ?>/admin/barcode_generator_issued.php?code=${encodeURIComponent(barcode)}&width=200&height=50&t=${Date.now()}" style="border:1px solid #ddd;padding:8px;border-radius:5px;max-width:100%">`;
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

    // Close modals when clicking outside
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
    <?php if($reissue_item): ?>
    searchReissueEmployees();
    setTimeout(function() {
        if(document.getElementById('reissue_employee_results_body').innerHTML.includes('Type to search')) {
            searchReissueEmployees();
        }
    }, 100);
<?php endif; ?>
});
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>