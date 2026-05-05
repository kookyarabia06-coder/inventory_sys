<?php
ob_start();
/**
 * Issue Items Page (Admin)
 * Handle item issuance to employees
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

// ========== HANDLE PAR PRINT FIRST (BEFORE ANY OUTPUT) ==========
if (isset($_GET['print_par']) && is_numeric($_GET['print_par'])) {
    $issuance_id = (int)$_GET['print_par'];
    $user_query = $conn->query("SELECT issued_to FROM equipment_issuance WHERE id = $issuance_id");
    if (!$user_query || $user_query->num_rows == 0) {
        $_SESSION['error'] = 'Issuance record not found.';
        header('Location: ' . SITE_URL . '/admin/issue_items.php');
        exit;
    }
    $employee_id = $user_query->fetch_assoc()['issued_to'];
    
    $par_query = $conn->query("
        SELECT ei.*, i.id as inventory_item_id, i.article_name, i.description,
               i.property_no, i.uom as unit_of_measure, i.unit_value, i.fund_cluster,
               i.category, i.type_equipment, i.condition_text, i.date_added as date_acquired,
               e.name as equipment_name,
               CONCAT(emp.firstname, ' ', emp.lastname) as issued_to_name,
               CONCAT(issuer.firstname, ' ', issuer.lastname) as issued_by_name,
               s.name as section_name, d.name as department_name, b.name as building_name,
               emp.position,
               CONCAT(
                   LPAD(COALESCE(b.id, 0), 2, '0'),
                   LPAD(COALESCE(d.id, 0), 2, '0'),
                   LPAD(COALESCE(s.id, 0), 2, '0')
               ) as location_code
        FROM equipment_issuance ei
        JOIN inventory i ON ei.inventory_id = i.id
        LEFT JOIN equipment e ON i.equipment_id = e.id
        JOIN employees emp ON ei.issued_to = emp.id
        JOIN users issuer ON ei.issued_by = issuer.id
        LEFT JOIN sections s ON i.section_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN buildings b ON d.building_id = b.id
        WHERE ei.issued_to = $employee_id AND ei.status = 'issued'
        ORDER BY i.property_no
    ");
    
    if ($par_query && $par_query->num_rows > 0) {
        $items = $par_query->fetch_all(MYSQLI_ASSOC);
        $first_item = $items[0];
        $par_items = [];
        $total_amount = 0;
        foreach ($items as $item) {
            $par_items[] = [
                'article_name' => $item['article_name'],
                'description' => $item['description'],
                'property_no' => $item['property_no'],
                'unit_of_measure' => $item['unit_of_measure'],
                'unit_value' => $item['unit_value'],
                'quantity' => $item['quantity_issued'],
                'date_acquired' => $item['date_acquired'] ?? $item['issued_date'],
                'amount' => $item['unit_value'] * $item['quantity_issued']
            ];
            $total_amount += $item['unit_value'] * $item['quantity_issued'];
        }
        
        $par_no = "PAR No.: " . date('Y-m-') . sprintf('%03d', $employee_id);
        $entity_name = "'AMANG' RODRIGUEZ MEMORIAL MEDICAL CENTER";
        $fund_cluster = $first_item['fund_cluster'] ?? "RAF";
        $location_code = $first_item['location_code'] ?? '';
        
        header('Content-Type: text/html; charset=utf-8');
        ?>
        <!DOCTYPE html><html><head>
        <title>PAR <?php echo $par_no; ?></title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:'Times New Roman',serif;background:#fff;padding:20px;font-size:12px}
            .par-container{max-width:1100px;margin:0 auto;padding:20px}
            @media print{body{padding:0}.par-container{padding:.5in}.no-print{display:none}}
            .header{text-align:center;margin-bottom:20px}
            .entity-name{font-size:14px;font-weight:bold;text-transform:uppercase}
            .title{text-align:center;font-size:16px;font-weight:bold;text-decoration:underline;margin:20px 0 10px}
            .par-header{display:flex;justify-content:space-between;margin:15px 0;font-weight:bold}
            .items-table{width:100%;border-collapse:collapse;margin:15px 0;font-size:11px}
            .items-table th,.items-table td{border:1px solid #000;padding:8px 5px}
            .items-table th{background:#f0f0f0;font-weight:bold;text-align:center}
            .items-table td.amount,.items-table th.amount{text-align:right}
            .total-row{font-weight:bold;background:#f9f9f9}
            .signature-section{margin-top:30px;display:flex;justify-content:space-between}
            .signature-box{width:45%;text-align:center}
            .signature-line{margin-top:40px;border-top:1px solid #000;width:100%;padding-top:5px}
            .signature-name{font-weight:bold;font-size:12px}
            .signature-title{font-size:10px;margin-top:5px}
            .footer-note{margin-top:30px;font-size:9px;text-align:center;border-top:1px solid #ccc;padding-top:10px}
            .print-button{text-align:center;margin-bottom:20px}
            .btn-print{background:#2c3e50;color:white;border:none;padding:10px 20px;font-size:14px;cursor:pointer;border-radius:5px;margin:0 5px}
        </style>
        </head><body>
        <div class="print-button no-print">
            <button onclick="window.print()" class="btn-print">🖨️ Print</button>
            <button onclick="window.close()" class="btn-print" style="background:#6c757d">Close</button>
        </div>
        <div class="par-container">
            <div class="header"><div class="entity-name"><?php echo htmlspecialchars($entity_name); ?></div></div>
            <div class="title">PROPERTY ACKNOWLEDGMENT RECEIPT</div>
            <div class="par-header">
                <span>Fund Cluster: <?php echo htmlspecialchars($fund_cluster); ?></span>
                <span><?php echo $par_no; ?></span>
            </div>
            <?php if(!empty($location_code) && $location_code != '000000'): ?>
            <div class="par-header" style="margin-top:-10px;font-size:11px">
                <span>Location Code: <?php echo htmlspecialchars($location_code); ?></span>
            </div>
            <?php endif; ?>
            <table class="items-table">
                <thead><tr><th width="8%">Qty</th><th width="10%">Unit</th><th width="35%">Description</th><th width="20%">Property No.</th><th width="12%">Date Acquired</th><th width="15%" class="amount">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($par_items as $item): ?>
                    <tr>
                        <td class="center"><?php echo $item['quantity']; ?></td>
                        <td class="center"><?php echo htmlspecialchars($item['unit_of_measure'] ?? 'pcs'); ?></td>
                        <td><strong><?php echo htmlspecialchars($item['article_name']); ?></strong><?php if(!empty($item['description'])) echo '<br><small>'.htmlspecialchars($item['description']).'</small>'; ?></td>
                        <td class="center"><?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?></td>
                        <td class="center"><?php echo date('m/d/y', strtotime($item['date_acquired'])); ?></td>
                        <td class="amount"><?php echo number_format($item['amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row"><td colspan="5" style="text-align:right"><strong>TOTAL</strong></td><td class="amount"><strong><?php echo number_format($total_amount, 2); ?></strong></td></tr>
                </tbody>
            </table>
            <div class="signature-section">
                <div class="signature-box"><div class="signature-line"><div class="signature-name"><?php echo htmlspecialchars($first_item['issued_to_name']); ?></div></div><div class="signature-title">End-User</div></div>
                <div class="signature-box"><div class="signature-line"><div class="signature-name"><?php echo htmlspecialchars($first_item['issued_by_name']); ?></div></div><div class="signature-title">Supply Officer</div></div>
            </div>
            <div class="footer-note">Generated on <?php echo date('F d, Y h:i A'); ?></div>
        </div>
        </body></html>
        <?php
        exit;
    } else {
        $_SESSION['error'] = 'Issuance record not found.';
        header('Location: ' . SITE_URL . '/admin/issue_items.php');
        exit;
    }
}

// Handle printing all issued items for an employee (Bulk PAR)
if (isset($_GET['print_user_par']) && is_numeric($_GET['print_user_par'])) {
    $employee_id = (int)$_GET['print_user_par'];
    $par_query = $conn->query("
        SELECT ei.*, i.article_name, i.description, i.property_no, i.uom as unit_of_measure,
               i.unit_value, i.date_added as date_acquired, i.category, e.name as equipment_name,
               CONCAT(emp.firstname, ' ', emp.lastname) as issued_to_name,
               CONCAT(issuer.firstname, ' ', issuer.lastname) as issued_by_name,
               CONCAT(
                   LPAD(COALESCE(b.id, 0), 2, '0'),
                   LPAD(COALESCE(d.id, 0), 2, '0'),
                   LPAD(COALESCE(s.id, 0), 2, '0')
               ) as location_code
        FROM equipment_issuance ei
        JOIN inventory i ON ei.inventory_id = i.id
        LEFT JOIN equipment e ON i.equipment_id = e.id
        JOIN employees emp ON ei.issued_to = emp.id
        JOIN users issuer ON ei.issued_by = issuer.id
        LEFT JOIN sections s ON e.section_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN buildings b ON d.building_id = b.id
        WHERE ei.issued_to = $employee_id AND ei.status = 'issued'
        ORDER BY ei.issued_date DESC
    ");
    
    if ($par_query && $par_query->num_rows > 0) {
        $items = $par_query->fetch_all(MYSQLI_ASSOC);
        $first_item = $items[0];
        $total_amount = 0;
        foreach ($items as $item) $total_amount += $item['unit_value'] * $item['quantity_issued'];
        $location_code = $first_item['location_code'] ?? '';
        
        header('Content-Type: text/html; charset=utf-8');
        ?><!DOCTYPE html><html><head><title>PAR</title>
        <style>
            *{margin:0;padding:0}body{font-family:'Times New Roman',serif;padding:20px;font-size:12px}
            .par-container{max-width:900px;margin:0 auto;padding:20px}
            @media print{body{padding:0}.par-container{padding:.5in}.no-print{display:none}}
            .entity-name{font-size:14px;font-weight:bold;text-transform:uppercase;text-align:center}
            .title{text-align:center;font-size:16px;font-weight:bold;text-decoration:underline;margin:20px 0}
            .par-header{display:flex;justify-content:space-between;margin:15px 0;font-weight:bold}
            .items-table{width:100%;border-collapse:collapse;margin:15px 0;font-size:11px}
            .items-table th,.items-table td{border:1px solid #000;padding:8px 5px}
            .items-table th{background:#f0f0f0;text-align:center}
            .items-table td.amount{text-align:right}.total-row{font-weight:bold;background:#f9f9f9}
            .signature-section{margin-top:30px;display:flex;justify-content:space-between}
            .signature-box{width:45%;text-align:center}.signature-line{margin-top:40px;border-top:1px solid #000;padding-top:5px}
            .signature-name{font-weight:bold;font-size:12px}.signature-title{font-size:10px;margin-top:5px}
            .footer-note{margin-top:30px;font-size:9px;text-align:center;border-top:1px solid #ccc;padding-top:10px}
            .btn-print{background:#2c3e50;color:white;border:none;padding:10px 20px;cursor:pointer;border-radius:5px}
        </style></head><body>
        <div class="no-print" style="text-align:center;margin-bottom:20px">
            <button onclick="window.print()" class="btn-print">🖨️ Print</button>
            <button onclick="window.close()" class="btn-print" style="background:#6c757d">Close</button>
        </div>
        <div class="par-container">
            <div class="entity-name">'AMANG' RODRIGUEZ MEMORIAL MEDICAL CENTER</div>
            <div class="title">PROPERTY ACKNOWLEDGMENT RECEIPT</div>
            <div class="par-header"><span>Fund Cluster: RAF</span><span>PAR No.: <?php echo date('Y-m-').sprintf('%03d',$employee_id); ?></span></div>
            <?php if(!empty($location_code) && $location_code != '000000'): ?>
            <div class="par-header" style="margin-top:-10px;font-size:11px">
                <span>Location Code: <?php echo htmlspecialchars($location_code); ?></span>
            </div>
            <?php endif; ?>
            <table class="items-table">
                <thead><tr><th>Qty</th><th>Unit</th><th>Description</th><th>Property No.</th><th>Date</th><th class="amount">Amount</th></tr></thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                    <tr><td class="center"><?php echo number_format($item['quantity_issued'],0); ?></td>
                        <td class="center"><?php echo htmlspecialchars($item['unit_of_measure']??'LOT'); ?></td>
                        <td><strong><?php echo htmlspecialchars($item['article_name']); ?></strong><?php if(!empty($item['description'])) echo '<br><small>'.htmlspecialchars($item['description']).'</small>'; ?></td>
                        <td class="center"><?php echo htmlspecialchars($item['property_no']??'N/A'); ?></td>
                        <td class="center"><?php echo date('m/d/y',strtotime($item['issued_date'])); ?></td>
                        <td class="amount"><?php echo number_format($item['unit_value']*$item['quantity_issued'],2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row"><td colspan="5" style="text-align:right"><strong>TOTAL</strong></td><td class="amount"><strong><?php echo number_format($total_amount,2); ?></strong></td></tr>
                </tbody>
            </table>
            <div class="signature-section">
                <div class="signature-box"><div class="signature-line"><div class="signature-name"><?php echo htmlspecialchars($first_item['issued_to_name']); ?></div></div><div class="signature-title">End-User</div></div>
                <div class="signature-box"><div class="signature-line"><div class="signature-name"><?php echo htmlspecialchars($first_item['issued_by_name']); ?></div></div><div class="signature-title">Supply Officer</div></div>
            </div>
            <div class="footer-note">Generated on <?php echo date('F d, Y h:i A'); ?></div>
        </div></body></html>
        <?php
        exit;
    } else {
        $_SESSION['error'] = 'No active issued items found.';
        header('Location: ' . SITE_URL . '/admin/issue_items.php');
        exit;
    }
}

$page_title = 'Issue Items';
$page_description = 'Issue inventory items to employees';

// Handle issuance form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $is_reissue = isset($_POST['is_reissue']) && $_POST['is_reissue'] == '1';
    $original_issuance_id = $is_reissue ? (int)$_POST['original_issuance_id'] : null;
    $inventory_ids = isset($_POST['inventory_ids']) ? $_POST['inventory_ids'] : [];
    $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : [];
    $issued_to = (int)$_POST['issued_to']; // This is employee ID now
    $purpose = sanitize($_POST['purpose']);
    $condition = sanitize($_POST['condition']);
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    $conn->begin_transaction();
    try {
        if ($is_reissue && $original_issuance_id) {
            $original_issuance = $conn->query("SELECT ei.*, i.qty_physical_count FROM equipment_issuance ei JOIN inventory i ON ei.inventory_id = i.id WHERE ei.id = $original_issuance_id")->fetch_assoc();
            if (!$original_issuance) throw new Exception("Original issuance not found");
            if ($original_issuance['status'] !== 'issued') throw new Exception("Item already returned, cannot reissue.");
            
            $conn->query("UPDATE equipment_issuance SET status='returned', actual_return=NOW(), condition_on_return='Good' WHERE id=$original_issuance_id");
            $conn->query("UPDATE user_inventory SET status='returned' WHERE issuance_id=$original_issuance_id");
            
            $stmt = $conn->prepare("INSERT INTO equipment_issuance (inventory_id,issued_to,issued_by,quantity_issued,purpose,condition_on_issue,remarks,status) VALUES (?,?,?,?,?,?,?,'issued')");
            $stmt->bind_param("iiidsss", $original_issuance['inventory_id'], $issued_to, $_SESSION['user_id'], $original_issuance['quantity_issued'], $purpose, $condition, $remarks);
            $stmt->execute();
            $new_issuance_id = $stmt->insert_id;
            
            $conn->query("UPDATE inventory SET current_holder=$issued_to WHERE id={$original_issuance['inventory_id']}");
            $stmt2 = $conn->prepare("INSERT IGNORE INTO user_inventory (user_id,inventory_id,issuance_id,quantity_assigned,status) VALUES (?,?,?,?,'active')");
            $stmt2->bind_param("iiid", $issued_to, $original_issuance['inventory_id'], $new_issuance_id, $original_issuance['quantity_issued']);
            $stmt2->execute();
            logActivity('Reissue Item', $original_issuance['inventory_id'], "Reissued to employee ID: $issued_to");
            $success_count = 1;
        } elseif (count($inventory_ids) > 0) {
            $success_count = 0;
            foreach ($inventory_ids as $index => $inventory_id) {
                $inventory_id = (int)$inventory_id;
                $requested_qty = isset($quantities[$index]) ? floatval($quantities[$index]) : 1;
                $selected_item = $conn->query("SELECT * FROM inventory WHERE id=$inventory_id")->fetch_assoc();
                if (!$selected_item) throw new Exception("Item not found");
                if ($selected_item['qty_physical_count'] < $requested_qty) throw new Exception("Insufficient quantity: ".$selected_item['article_name']);
                
                $new_quantity = $selected_item['qty_physical_count'] - $requested_qty;
                $conn->query("UPDATE inventory SET qty_physical_count=$new_quantity, current_holder=$issued_to WHERE id=$inventory_id");
                
                $stmt = $conn->prepare("INSERT INTO equipment_issuance (inventory_id,issued_to,issued_by,quantity_issued,purpose,condition_on_issue,remarks,status) VALUES (?,?,?,?,?,?,?,'issued')");
                $stmt->bind_param("iiidsss", $inventory_id, $issued_to, $_SESSION['user_id'], $requested_qty, $purpose, $condition, $remarks);
                $stmt->execute();
                $issuance_id = $stmt->insert_id;
                $stmt2 = $conn->prepare("INSERT IGNORE INTO user_inventory (user_id,inventory_id,issuance_id,quantity_assigned,status) VALUES (?,?,?,?,'active')");
                $stmt2->bind_param("iiid", $issued_to, $inventory_id, $issuance_id, $requested_qty);
                $stmt2->execute();
                $success_count++;
            }
            logActivity('Issue Multiple Items', 0, "Issued $success_count item(s) to employee ID: $issued_to");
        } else {
            throw new Exception("No items selected");
        }
        $conn->commit();
        $_SESSION['success'] = $is_reissue ? "Item reissued successfully" : "$success_count item(s) issued successfully";
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    unset($_SESSION['reissue_from']);
    header('Location: ' . SITE_URL . '/admin/issue_items.php');
    exit();
}

// Handle return
if (isset($_GET['return']) && is_numeric($_GET['return'])) {
    $issuance_id = (int)$_GET['return'];
    $issuance = $conn->query("SELECT ei.*, i.qty_physical_count FROM equipment_issuance ei JOIN inventory i ON ei.inventory_id=i.id WHERE ei.id=$issuance_id")->fetch_assoc();
    if ($issuance) {
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE equipment_issuance SET status='returned', actual_return=NOW(), condition_on_return='Good' WHERE id=$issuance_id");
            $new_quantity = $issuance['qty_physical_count'] + $issuance['quantity_issued'];
            $conn->query("UPDATE inventory SET qty_physical_count=$new_quantity WHERE id={$issuance['inventory_id']}");
            $conn->query("UPDATE user_inventory SET status='returned' WHERE issuance_id=$issuance_id");
            logActivity('Return Item', $issuance['inventory_id'], "Returned {$issuance['quantity_issued']} items");
            $conn->commit();
            $_SESSION['success'] = "Item returned successfully";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
    }
    header('Location: ' . SITE_URL . '/admin/issue_items.php');
    exit();
}

// Cancel reissue
if (isset($_GET['cancel_reissue'])) {
    unset($_SESSION['reissue_from']);
    header('Location: ' . SITE_URL . '/admin/issue_items.php');
    exit();
}

// Handle reissue
if (isset($_GET['reissue']) && is_numeric($_GET['reissue'])) {
    $original_issuance_id = (int)$_GET['reissue'];
    $issuance_check = $conn->query("SELECT status FROM equipment_issuance WHERE id=$original_issuance_id")->fetch_assoc();
    if (!$issuance_check) { $_SESSION['error']='Issuance not found.'; header('Location: '.SITE_URL.'/admin/issue_items.php'); exit(); }
    if ($issuance_check['status']!=='issued') { $_SESSION['error']='Item already returned.'; header('Location: '.SITE_URL.'/admin/issue_items.php'); exit(); }
    $_SESSION['reissue_from'] = $original_issuance_id;
    header('Location: ' . SITE_URL . '/admin/issue_items.php?show_reissue=1');
    exit();
}

// ============================================
// GET EMPLOYEES DIRECTLY (NOT USERS)
// ============================================

// Get employees for dropdown - directly from employees table
$employees_list = $conn->query("
    SELECT e.*, d.name as department_name, s.name as section_name, b.name as building_name,
           CONCAT(
               LPAD(COALESCE(b.id, 0), 2, '0'),
               LPAD(COALESCE(d.id, 0), 2, '0'),
               LPAD(COALESCE(s.id, 0), 2, '0')
           ) as location_code
    FROM employees e
    LEFT JOIN sections s ON e.section_id = s.id
    LEFT JOIN departments d ON s.department_id = d.id
    LEFT JOIN buildings b ON d.building_id = b.id
    WHERE e.status = 'Active'
    ORDER BY e.lastname, e.firstname
");

// Get current issuances with employee names
$issuances = $conn->query("
    SELECT 
        ei.*, 
        i.article_name, 
        i.property_no, 
        i.uom,
        CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
        e.id as employee_id,
        e.position,
        s.name as section_name,
        d.name as department_name,
        b.name as building_name,
        CONCAT(
            LPAD(COALESCE(b.id, 0), 2, '0'),
            LPAD(COALESCE(d.id, 0), 2, '0'),
            LPAD(COALESCE(s.id, 0), 2, '0')
        ) as location_code
    FROM equipment_issuance ei 
    JOIN inventory i ON ei.inventory_id = i.id 
    JOIN employees e ON ei.issued_to = e.id
    LEFT JOIN sections s ON e.section_id = s.id
    LEFT JOIN departments d ON s.department_id = d.id
    LEFT JOIN buildings b ON d.building_id = b.id
    WHERE ei.status = 'issued' 
    ORDER BY e.lastname, e.firstname, ei.issued_date DESC
");

// Group issuances by employee
$employee_issuances = [];
if ($issuances && $issuances->num_rows > 0) {
    while($issue = $issuances->fetch_assoc()) {
        $eid = $issue['employee_id'];
        if (!isset($employee_issuances[$eid])) {
            $location_parts = [];
            if (!empty($issue['building_name'])) $location_parts[] = $issue['building_name'];
            if (!empty($issue['department_name'])) $location_parts[] = $issue['department_name'];
            if (!empty($issue['section_name'])) $location_parts[] = $issue['section_name'];
            $location_string = !empty($location_parts) ? implode(' → ', $location_parts) : '';
            
            $employee_issuances[$eid] = [
                'employee_name' => $issue['issued_to_name'], 
                'position' => $issue['position'],
                'location_code' => $issue['location_code'],
                'location_string' => $location_string,
                'items' => []
            ];
        }
        $employee_issuances[$eid]['items'][] = $issue;
    }
}

// Reissue details
$reissue_item = null; 
$reissue_from_id = null;
if (isset($_SESSION['reissue_from'])) {
    $reissue_from_id = (int)$_SESSION['reissue_from'];
    $reissue_item = $conn->query("
        SELECT ei.*, i.article_name, i.property_no, i.uom, i.qty_physical_count as available_stock, 
               CONCAT(e.firstname, ' ', e.lastname) as issued_to_name, 
               e.id as original_employee_id,
               e.position as original_position,
               s.name as original_section,
               d.name as original_department
        FROM equipment_issuance ei 
        JOIN inventory i ON ei.inventory_id=i.id 
        JOIN employees e ON ei.issued_to = e.id
        LEFT JOIN sections s ON e.section_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE ei.id=$reissue_from_id
    ")->fetch_assoc();
    if (!$reissue_item) unset($_SESSION['reissue_from']);
}

// Get inventory items for barcode scanning
$inventory_items_result = $conn->query("SELECT * FROM inventory WHERE qty_physical_count > 0 ORDER BY article_name");
$inventory_data = [];
if ($inventory_items_result) {
    while ($item = $inventory_items_result->fetch_assoc()) {
        $inventory_data[$item['id']] = $item;
    }
}

include INCLUDE_PATH . '/header.php';
?>

<style>
:root{--primary:#2196F3;--secondary:#64B5F6;--accent:#42A5F5;--accent-light:#90CAF9;--success-light:#C8E6C9;--light:#F0F0F0;--white:#FFF;--border-light:#E0E0E0;--text-primary:#3A3A3A;--text-secondary:#6B6B6B;--text-muted:#9E9E9E;--success:#4CAF50;--danger:#f44336;--warning:#FF9800;--info:#2196F3}
body{background:var(--light);color:var(--text-primary)}
.barcode-search-section{background:linear-gradient(135deg,#6B8CFF,#8FB5FF);border-radius:12px;padding:20px;margin-bottom:25px;color:#fff}
.barcode-search-section h4{margin:0 0 10px;font-size:16px}
.barcode-search-box{display:flex;gap:10px;align-items:center;background:#fff;border-radius:50px;padding:5px 5px 5px 20px}
.barcode-search-box input{flex:1;border:none;padding:12px 0;font-size:16px;outline:none;background:transparent}
.barcode-search-box button{background:var(--accent);border:none;padding:10px 25px;border-radius:50px;color:var(--text-primary);font-weight:500;cursor:pointer;transition:all .2s}
.barcode-search-box button:hover{background:#1E88E5;transform:scale(1.02)}
.barcode-search-result{background:rgba(255,255,255,.15);border-radius:10px;padding:15px;margin-top:15px;display:none}
.barcode-search-result.show{display:block;animation:fadeIn .3s}
@keyframes fadeIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}
.result-item{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:15px}
.result-info h5{margin:0 0 5px;font-size:16px}.result-info p{margin:0;font-size:13px;opacity:.9}
.result-actions{display:flex;gap:10px}
.btn-add-item{background:#fff;color:var(--primary);border:none;padding:8px 20px;border-radius:6px;font-weight:500;cursor:pointer}
.selected-items-grid{max-height:400px;overflow-y:auto}
.selected-item-card{background:#fff;border:1px solid var(--border-light);border-radius:10px;padding:12px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.selected-item-info{flex:2}.item-name{font-weight:600}.item-property{font-size:11px;color:var(--text-muted);font-family:monospace}
.selected-item-qty{display:flex;align-items:center;gap:10px}
.selected-item-qty input{width:80px;padding:6px 10px;border:1px solid var(--border-light);border-radius:6px;text-align:center}
.btn-remove-item{background:var(--danger);color:#fff;border:none;width:32px;height:32px;border-radius:6px;cursor:pointer}
.empty-cart{text-align:center;padding:40px;color:var(--text-muted)}.empty-cart i{font-size:48px;margin-bottom:15px;opacity:.5}
.cart-summary{margin-top:15px;padding-top:15px;border-top:2px solid var(--accent-light);text-align:right;font-weight:bold;color:var(--primary)}
.action-buttons{display:flex;gap:5px;flex-wrap:wrap}
.action-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;border:none;color:#fff;text-decoration:none;cursor:pointer;font-size:14px}
.action-btn.edit{background:#8FB5FF}.action-btn.view{background:#6B8CFF}.action-btn.success{background:#4CAF50}
.action-btn:hover{transform:translateY(-2px);box-shadow:0 4px 8px rgba(0,0,0,.15)}
.badge-warning{background:#8FB5FF;color:#3A3A3A;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.badge-success{background:#C5E8C5;color:#4CAF50;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600;display:inline-block}
.btn-primary{background:var(--accent);color:var(--text-primary);border:none;padding:8px 16px;border-radius:6px;cursor:pointer}
.btn-secondary{background:var(--secondary);color:#fff;border:none;padding:8px 16px;border-radius:6px;cursor:pointer;text-decoration:none;display:inline-block}
.form-group{margin-bottom:20px}.form-group label{display:block;margin-bottom:8px;font-weight:500}
.form-control{width:100%;padding:10px 12px;border:1px solid var(--border-light);border-radius:8px;font-size:14px;box-sizing:border-box}
.stat-chart{background:#fff;border-radius:12px;padding:20px;margin-bottom:25px;box-shadow:0 2px 8px rgba(0,0,0,.08)}
.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(500px,1fr));gap:20px}
.table-container{background:#fff;border-radius:12px;padding:20px;margin-bottom:25px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow-x:auto}
.table-header{margin-bottom:20px;padding-bottom:10px;border-bottom:2px solid var(--accent-light)}
table{width:100%;border-collapse:collapse}th,td{padding:12px 10px;text-align:left;border-bottom:1px solid var(--border-light)}th{background:#F0F0F0;font-weight:600}
.text-center{text-align:center}
.alert-warning{background:#FFF3E0;border-left:4px solid #FF9800;padding:15px;margin-bottom:20px}
.scanner-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:1500;align-items:center;justify-content:center}
.scanner-modal.show{display:flex}
.scanner-modal-content{background:#fff;border-radius:12px;width:90%;max-width:500px;box-shadow:0 5px 30px rgba(0,0,0,.3)}
.scanner-modal-header{display:flex;justify-content:space-between;align-items:center;padding:20px;background:#2196F3;color:#fff;border-radius:12px 12px 0 0}
.close-modal-btn{background:rgba(255,255,255,.2);border:none;color:#fff;font-size:24px;cursor:pointer;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center}
.scanner-modal-body{padding:25px}.scanner-input-section{margin-bottom:20px}
.scanner-input-section input{width:100%;padding:14px;border:2px solid var(--border-light);border-radius:8px;font-size:16px;box-sizing:border-box}
.scanner-result-box{min-height:100px;padding:15px;background:#F8F9FA;border-radius:8px;text-align:center;color:var(--text-muted)}
.scanner-modal-footer{padding:15px 25px;border-top:1px solid var(--border-light);text-align:right}
.hardware-scanner-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.8);z-index:3000;align-items:center;justify-content:center}
.hardware-scanner-modal.show{display:flex}
.hardware-scanner-content{background:#fff;border-radius:15px;width:90%;max-width:600px;max-height:90vh;overflow-y:auto;box-shadow:0 10px 50px rgba(0,0,0,.3);display:flex;flex-direction:column}
.hardware-scanner-header{background:#2196F3;color:#fff;padding:25px;border-radius:15px 15px 0 0;display:flex;justify-content:space-between;align-items:center}
.hardware-scanner-header h2{margin:0;font-size:20px}
.hardware-scanner-header .close-btn{background:rgba(255,255,255,.2);border:none;color:#fff;font-size:28px;cursor:pointer;width:40px;height:40px;border-radius:50%}
.hardware-scanner-body{padding:25px;flex:1;overflow-y:auto}
.scanner-instructions{background:#E3F2FD;border-left:4px solid #2196F3;padding:15px;border-radius:8px;margin-bottom:20px;font-size:14px;color:#0D47A1}
.scanner-input-wrapper{position:relative;display:flex;align-items:center}
.scanner-input-wrapper i{position:absolute;left:12px;color:#2196F3;font-size:18px;z-index:1}
.hardware-scanner-input{width:100%;padding:15px 15px 15px 45px;border:2px solid #2196F3;border-radius:10px;font-size:16px;font-weight:bold;background:#F5F9FF;box-shadow:0 2px 8px rgba(33,150,243,.1);box-sizing:border-box}
.hardware-scanner-input:focus{outline:none;border-color:#1976D2}
.scanned-items-list{margin-top:20px;flex:1;overflow-y:auto}
.scanned-item-card{background:#F5F5F5;border:1px solid #E0E0E0;border-radius:10px;padding:12px;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center}
.scanned-item-card.success{background:#E8F5E9;border-color:#4CAF50}
.scanned-item-info{flex:1}.scanned-item-name{font-weight:bold;font-size:14px;color:#333}.scanned-item-details{font-size:12px;color:#666}
.scanned-item-qty-controls{display:flex;align-items:center;gap:8px;margin-left:10px}
.qty-btn{background:#2196F3;color:#fff;border:none;width:28px;height:28px;border-radius:50%;cursor:pointer;font-weight:bold}
.qty-btn:hover{background:#1976D2}
.qty-input{width:50px;text-align:center;padding:5px;border:1px solid #E0E0E0;border-radius:5px;font-weight:bold;font-size:13px}
.remove-scanned-item{background:#f44336;color:#fff;border:none;padding:5px 10px;border-radius:5px;cursor:pointer;font-size:12px;margin-left:8px}
.empty-scan-state{text-align:center;padding:40px 20px;color:#999}.empty-scan-state i{font-size:48px;margin-bottom:15px;color:#CCC}
.hardware-scanner-footer{padding:15px 25px;border-top:1px solid #E0E0E0;display:flex;gap:10px;justify-content:flex-end}
.btn-add-all-to-cart{background:#4CAF50;color:#fff;border:none;padding:12px 25px;border-radius:8px;cursor:pointer;font-weight:bold}
.btn-add-all-to-cart:hover{background:#388E3C}
.btn-add-all-to-cart:disabled{opacity:.5;cursor:not-allowed}
.btn-clear-scans{background:#2196F3;color:#fff;border:none;padding:12px 25px;border-radius:8px;cursor:pointer;font-weight:bold}
.btn-clear-scans:hover{background:#1976D2}
.btn-close-hardware-scanner{background:#757575;color:#fff;border:none;padding:12px 25px;border-radius:8px;cursor:pointer;font-weight:bold}
.btn-close-hardware-scanner:hover{background:#616161}
.user-location-badge{background:#E8F5E9;color:#2E7D32;padding:4px 10px;border-radius:20px;font-size:11px;margin-left:8px}
.user-department-badge{background:#E3F2FD;color:#1565C0;padding:4px 10px;border-radius:20px;font-size:11px;margin-left:8px}
@media(max-width:768px){.stats-grid{grid-template-columns:1fr}}
</style>

<div class="barcode-search-section">
    <h4><i class="fas fa-barcode"></i> Physical Barcode Scanner</h4>
    <div style="display:flex;gap:10px;margin-bottom:15px">
        <button type="button" onclick="openHardwareScannerModal()" class="btn-primary" style="flex:1;padding:12px;background:linear-gradient(135deg,#eea5d6,#eea5d6);border:none;color:#fff"><i class="fas fa-barcode"></i> <strong>Open Hardware Scanner</strong></button>
        <button type="button" onclick="openScannerModal()" class="btn-primary" style="flex:1;padding:12px"><i class="fas fa-camera"></i> Webcam / Manual</button>
        <div style="background:rgba(255,255,255,.2);padding:10px 15px;border-radius:8px;font-size:13px"><i class="fas fa-check-circle"></i> Scanner Ready</div>
    </div>
    <div class="barcode-search-box">
        <i class="fas fa-search"></i>
        <input type="text" id="barcode_input" placeholder="Place cursor here and scan barcode..." autocomplete="off" spellcheck="false">
        <button type="button" onclick="searchBarcode()"><i class="fas fa-barcode"></i> Search</button>
    </div>
    <div id="barcode_result" class="barcode-search-result"></div>
</div>

<div id="scannerModal" class="scanner-modal">
    <div class="scanner-modal-content">
        <div class="scanner-modal-header"><h3><i class="fas fa-barcode"></i> Barcode Scanner</h3><button type="button" class="close-modal-btn" onclick="closeScannerModal()">&times;</button></div>
        <div class="scanner-modal-body">
            <div class="scanner-input-section">
                <label>Enter Barcode Manually</label>
                <input type="text" id="modal_barcode_input" placeholder="Type barcode or article name..." autocomplete="off">
                <small>Press Enter to search</small>
            </div>
            <div id="modal_barcode_result" class="scanner-result-box"></div>
        </div>
        <div class="scanner-modal-footer"><button type="button" onclick="closeScannerModal()" class="btn-secondary">Close</button></div>
    </div>
</div>

<div id="hardwareScannerModal" class="hardware-scanner-modal">
    <div class="hardware-scanner-content">
        <div class="hardware-scanner-header"><h2><i class="fas fa-barcode"></i> Hardware Barcode Scanner</h2><button type="button" class="close-btn" onclick="closeHardwareScannerModal()">&times;</button></div>
        <div class="hardware-scanner-body">
            <div class="scanner-instructions"><strong><i class="fas fa-info-circle"></i> Ready to Scan</strong>Click the input field below and scan barcodes. When done, click "Issue Items".</div>
            <div class="scanner-input-container">
                <label><i class="fas fa-barcode"></i> Scan Barcode</label>
                <div class="scanner-input-wrapper"><i class="fas fa-barcode"></i><input type="text" id="hardwareScannerInput" class="hardware-scanner-input" placeholder="Place cursor here and scan..." autocomplete="off"></div>
            </div>
            <div class="scanned-items-list"><div id="hardwareScannedItemsContainer"><div class="empty-scan-state"><i class="fas fa-box"></i><p>No items scanned yet</p></div></div></div>
        </div>
        <div class="hardware-scanner-footer">
            <button type="button" class="btn-clear-scans" onclick="clearHardwareScans()" id="clearScansBtn" style="display:none"><i class="fas fa-trash"></i> Clear All</button>
            <button type="button" class="btn-add-all-to-cart" onclick="issueHardwareScannedItems()" id="issueItemBtn" disabled><i class="fas fa-hand-holding"></i> Issue Items</button>
            <button type="button" class="btn-close-hardware-scanner" onclick="closeHardwareScannerModal()">Close</button>
        </div>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-chart">
        <h3><i class="fas fa-hand-holding"></i> <?php echo $reissue_item ? 'Reissue Item' : 'Issue New Item'; ?></h3>
        <?php if ($reissue_item): ?>
        <div class="alert-warning"><strong>Important:</strong> Reissuing from <strong><?php echo htmlspecialchars($reissue_item['issued_to_name']); ?></strong>
        <?php if(!empty($reissue_item['original_position'])) echo ' - ' . htmlspecialchars($reissue_item['original_position']); ?>
        <?php if(!empty($reissue_item['original_department'])) echo ' (' . htmlspecialchars($reissue_item['original_department']) . ')'; ?>
        </div>
        <?php endif; ?>
        <form method="POST" action="" onsubmit="return validateIssueForm()" id="issueForm">
            <?php if ($reissue_item): ?>
            <input type="hidden" name="is_reissue" value="1">
            <input type="hidden" name="original_issuance_id" value="<?php echo $reissue_from_id; ?>">
            <?php endif; ?>
            <?php if (!$reissue_item): ?>
            <div class="form-group"><label>Selected Items</label><div class="selected-items-grid" id="selectedItemsContainer"><div class="empty-cart" id="emptyCartMessage"><i class="fas fa-barcode"></i><p>No items selected</p></div><div id="selectedItemsList"></div><div id="cartSummary" class="cart-summary" style="display:none"></div></div></div>
            <?php endif; ?>
            <div class="form-group"><label>Select Employee *</label>
                <select name="issued_to" id="issued_to" class="form-control" required style="font-size:14px;padding:12px">
                    <option value="">-- Select Employee --</option>
                    <?php if ($employees_list && $employees_list->num_rows > 0): 
                        $employees_list->data_seek(0); 
                        while($emp = $employees_list->fetch_assoc()): 
                            $display_name = htmlspecialchars($emp['firstname'] . ' ' . $emp['lastname']);
                            if(!empty($emp['position'])) {
                                $display_name .= ' - ' . htmlspecialchars($emp['position']);
                            }
                            if(!empty($emp['department_name'])) {
                                $display_name .= ' (' . htmlspecialchars($emp['department_name']) . ')';
                            }
                    ?>
                    <option value="<?php echo $emp['id']; ?>" 
                            data-location-code="<?php echo $emp['location_code']; ?>" 
                            data-department="<?php echo htmlspecialchars($emp['department_name'] ?? ''); ?>"
                            data-position="<?php echo htmlspecialchars($emp['position'] ?? ''); ?>"
                            <?php echo ($reissue_item && $reissue_item['original_employee_id']==$emp['id'])?'disabled':''; ?>>
                        <?php echo $display_name; ?>
                    </option>
                    <?php endwhile; endif; ?>
                </select>
            </div>
            <div class="form-group"><label>Condition</label><select name="condition" class="form-control"><option value="Serviceable" >Serviceable</option><option value="Non-Serviceable" >Non-Serviceable</option><option value="For Condemn" >For Condemn</option><option value="Under Repair" >Under Repair</option><option value="For Disposal" >For Disposal</option></select></div>
            <div class="form-group"><label>Purpose *</label><textarea name="purpose" id="purpose" class="form-control" rows="3" required placeholder="Reason for issuing"></textarea></div>
            <div class="form-group"><label>Remarks</label><textarea name="remarks" class="form-control" rows="2" placeholder="Optional notes"></textarea></div>
            <div class="form-group"><button type="submit" class="btn-primary" id="submitBtn"><i class="fas fa-hand-holding"></i> <?php echo $reissue_item?'Reissue':'Issue Selected Items (<span id="selectedCount">0</span>)'; ?></button></div>
        </form>
    </div>
    
    <div class="stat-chart">
        <h3><i class="fas fa-clipboard-list"></i> Currently Issued Items</h3>
        <div style="max-height:500px;overflow-y:auto">
            <table style="width:100%">
                <thead><tr><th>Employee</th><th>Department / Section</th><th>Location Code</th><th>Items</th><th>Total Qty</th><th>Actions</th></tr></thead>
                <tbody>
                    <?php if(!empty($employee_issuances)): foreach($employee_issuances as $eid=>$edata): $ti=0;$tq=0;foreach($edata['items'] as $it){$ti++;$tq+=$it['quantity_issued'];} ?>
                    <tr style="background:#f8f9fa"><td colspan="6" style="padding:15px;border-bottom:2px solid #6B8CFF">
                        <strong style="color:#6B8CFF;font-size:16px"><i class="fas fa-user"></i> <?php echo htmlspecialchars($edata['employee_name']); ?></strong>
                        <?php if(!empty($edata['position'])): ?>
                        <span class="user-department-badge"><i class="fas fa-briefcase"></i> <?php echo htmlspecialchars($edata['position']); ?></span>
                        <?php endif; ?>
                        <?php if(!empty($edata['location_code']) && $edata['location_code'] != '000000'): ?>
                        <span class="user-location-badge"><i class="fas fa-location-dot"></i> LOC: <?php echo htmlspecialchars($edata['location_code']); ?></span>
                        <?php endif; ?>
                        <?php if(!empty($edata['location_string'])): ?>
                        <span class="user-department-badge"><i class="fas fa-building"></i> <?php echo htmlspecialchars($edata['location_string']); ?></span>
                        <?php endif; ?>
                        <span style="float:right;background:#6B8CFF;color:#fff;padding:4px 12px;border-radius:20px;font-size:12px"><?php echo $ti; ?> item(s) • <?php echo $tq; ?> total qty</span>
                    </td></tr>
                    <?php foreach($edata['items'] as $item): ?>
                    <tr style="background:#fafafa">
                        <td style="padding-left:30px"><strong><?php echo htmlspecialchars($item['article_name']); ?></strong><br><small><?php echo htmlspecialchars($item['property_no']??'N/A'); ?></small></td>
                        <td class="text-muted" style="font-size:11px">
                            <?php 
                            $loc_parts = [];
                            if(!empty($item['building_name'])) $loc_parts[] = $item['building_name'];
                            if(!empty($item['department_name'])) $loc_parts[] = $item['department_name'];
                            if(!empty($item['section_name'])) $loc_parts[] = $item['section_name'];
                            echo !empty($loc_parts) ? htmlspecialchars(implode(' → ', $loc_parts)) : '-';
                            ?>
                        </td>
                        <td class="text-muted" style="font-size:11px"><?php echo htmlspecialchars($item['location_code'] ?? '-'); ?></td>
                        <td><?php echo $item['quantity_issued'].' '.htmlspecialchars($item['uom']??'pcs'); ?></td>
                        <td><?php echo date('M d, Y',strtotime($item['issued_date'])); ?></td>
                        <td><div class="action-buttons">
                            <a href="?print_par=<?php echo $item['id']; ?>" class="action-btn" style="background:#2c3e50" target="_blank" title="Print PAR"><i class="fas fa-file-signature"></i></a>
                            <a href="?return=<?php echo $item['id']; ?>" class="action-btn success" onclick="return confirm('Return this item?')"><i class="fas fa-undo"></i></a>
                            <?php if($item['status']==='issued'): ?>
                            <a href="?reissue=<?php echo $item['id']; ?>" class="action-btn edit" title="Reissue"><i class="fas fa-redo"></i></a>
                            <?php else: ?>
                            <span class="action-btn" style="background:#ccc;cursor:not-allowed"><i class="fas fa-redo"></i></span>
                            <?php endif; ?>
                            <button class="action-btn view" onclick="viewIssuanceDetails(<?php echo $item['id']; ?>)"><i class="fas fa-eye"></i></button>
                        </div></td>
                     </tr>
                    <?php endforeach; endforeach; else: ?>
                    <tr><td colspan="6" class="text-center">No items currently issued</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="table-container">
    <div class="table-header"><h2><i class="fas fa-history"></i> Issuance History</h2></div>
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Property No.</th>
                <th>Issued To (Employee)</th>
                <th>Department/Section</th>
                <th>Issued By</th>
                <th>Quantity</th>
                <th>Purpose</th>
                <th>Status</th>
                <th>Return Date</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $history = $conn->query("
                SELECT 
                    ei.*, 
                    i.article_name, 
                    i.property_no,
                    CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
                    CONCAT(ub.firstname, ' ', ub.lastname) as issued_by_name,
                    e.position,
                    s.name as section_name,
                    d.name as department_name,
                    CONCAT(
                        LPAD(COALESCE(b.id, 0), 2, '0'),
                        LPAD(COALESCE(d.id, 0), 2, '0'),
                        LPAD(COALESCE(s.id, 0), 2, '0')
                    ) as location_code
                FROM equipment_issuance ei 
                JOIN inventory i ON ei.inventory_id = i.id 
                JOIN employees e ON ei.issued_to = e.id
                LEFT JOIN sections s ON e.section_id = s.id
                LEFT JOIN departments d ON s.department_id = d.id
                LEFT JOIN buildings b ON d.building_id = b.id
                JOIN users ub ON ei.issued_by = ub.id 
                ORDER BY ei.issued_date DESC 
                LIMIT 50
            ");
            if($history && $history->num_rows > 0):
                while($item = $history->fetch_assoc()):
            ?>
            <tr>
                <td><?php echo date('M d, Y',strtotime($item['issued_date'])); ?></td>
                <td><strong><?php echo htmlspecialchars($item['article_name']); ?></strong></td>
                <td><small><?php echo htmlspecialchars($item['property_no']??'N/A'); ?></small></td>
                <td><?php echo htmlspecialchars($item['issued_to_name']); ?></td>
                <td>
                    <?php 
                    $loc_parts = [];
                    if(!empty($item['department_name'])) $loc_parts[] = $item['department_name'];
                    if(!empty($item['section_name'])) $loc_parts[] = $item['section_name'];
                    echo !empty($loc_parts) ? htmlspecialchars(implode(' → ', $loc_parts)) : '-';
                    if(!empty($item['position'])) echo '<br><small>'.htmlspecialchars($item['position']).'</small>';
                    ?>
                </td>
                <td><?php echo htmlspecialchars($item['issued_by_name']); ?></td>
                <td><?php echo $item['quantity_issued']; ?></td>
                <td><?php echo htmlspecialchars(substr($item['purpose']??'',0,30)); ?></td>
                <td><?php echo getStatusBadge($item['status']); ?></td>
                <td><?php echo $item['actual_return']?date('M d, Y',strtotime($item['actual_return'])):'—'; ?></td>
            </tr>
            <?php endwhile; else: ?>
            <tr><td colspan="10" class="text-center"><i class="fas fa-inbox" style="font-size:40px;color:#ccc;display:block;margin-bottom:10px"></i>No issuance records found</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
const inventoryData = <?php echo json_encode($inventory_data); ?>;
let cartItems=[],hardwareScannedItems=[],isScannerActive=false;

function searchBarcode(){performBarcodeSearch(document.getElementById('barcode_input').value.trim(),'barcode_input','barcode_result')}
function openScannerModal(){const m=document.getElementById('scannerModal');if(m){m.classList.add('show');setTimeout(()=>document.getElementById('modal_barcode_input').focus(),100)}}
function closeScannerModal(){document.getElementById('scannerModal').classList.remove('show');document.getElementById('modal_barcode_result').innerHTML='';document.getElementById('modal_barcode_input').value=''}
function openHardwareScannerModal(){const m=document.getElementById('hardwareScannerModal');if(m){m.classList.add('show');hardwareScannedItems=[];updateHardwareScannedItemsDisplay();setTimeout(()=>{const i=document.getElementById('hardwareScannerInput');if(i)i.focus()},100)}}
function closeHardwareScannerModal(){document.getElementById('hardwareScannerModal').classList.remove('show')}

function escapeHtml(s){if(!s)return'';return s.replace(/[&<>]/g,m=>({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]))}
function formatCurrency(a){return'₱'+parseFloat(a||0).toFixed(2).replace(/\d(?=(\d{3})+\.)/g,'$&,')}

function performBarcodeSearch(barcode,inputId,resultId){
    const rd=document.getElementById(resultId),st=barcode.toLowerCase().trim();
    if(!st){rd.innerHTML='<div class="alert-warning" style="padding:10px">Please enter a barcode</div>';rd.classList.add('show');return}
    let found=[];
    for(let id in inventoryData){
        const it=inventoryData[id],be=(it.barcode_data||'').toLowerCase().trim(),bn=be.replace(/\s+/g,''),sn=st.replace(/\s+/g,''),al=(it.article_name||'').toLowerCase(),pl=(it.property_no||'').toLowerCase();
        if(bn===sn||(be&&be.includes(st))||al.includes(st)||pl.includes(st))found.push(it);
    }
    if(found.length>0){
        const gi={};found.forEach(i=>{if(!gi[i.article_name])gi[i.article_name]=[];gi[i.article_name].push(i)});
        let h='',gIdx=0;
        for(const an in gi){
            const its=gi[an],ta=its.reduce((s,i)=>s+parseFloat(i.qty_physical_count||0),0),fi=its[0],aic=cartItems.find(i=>its.some(si=>si.id==i.id));
            window['variantGroup_'+gIdx]=its;
            h+=`<div class="result-item" style="margin-bottom:15px"><div class="result-info"><h5>${escapeHtml(an)}</h5><p>Available: <strong>${ta.toFixed(2)} ${fi.uom||'pcs'}</strong> | Value: ${formatCurrency(fi.unit_value)}</p><p style="font-size:12px;color:#666">${fi.category?`Category: <strong>${escapeHtml(fi.category)}</strong>`:''}${fi.type_equipment&&fi.type_equipment!==fi.category?` | Type: <strong>${escapeHtml(fi.type_equipment)}</strong>`:''}</p></div><div class="result-actions">${aic?'<button class="btn-add-item" disabled>Already Added</button>':`<button class="btn-add-item" onclick="openVariantSelector(${gIdx},'${resultId}')">Select Variant & Add</button>`}</div></div>`;
            gIdx++;
        }
        rd.innerHTML=h;rd.classList.add('show');document.getElementById(inputId).value='';
    }else{
        rd.innerHTML=`<div class="result-item"><div class="result-info"><h5 style="color:#f44336">Item not found</h5><p>No item found for: <strong>${escapeHtml(barcode)}</strong></p></div></div>`;
        rd.classList.add('show');
    }
}

function openVariantSelector(gi,rid){
    const its=window['variantGroup_'+gi];if(!its||!its.length)return;
    const vm=document.createElement('div');vm.id='vs_'+gi;vm.style.cssText='display:flex;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:2000;align-items:center;justify-content:center';
    let vh=`<div style="background:#fff;border-radius:12px;max-width:500px;width:90%;max-height:80vh;overflow-y:auto"><div style="padding:20px;background:linear-gradient(135deg,#6B8CFF,#8FB5FF);color:#fff;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center"><h3 style="margin:0">Select ${escapeHtml(its[0].article_name)}</h3><button onclick="closeVariantModal(${gi})" style="background:rgba(255,255,255,.2);border:none;color:#fff;font-size:24px;cursor:pointer;width:36px;height:36px;border-radius:50%">&times;</button></div><div style="padding:20px">`;
    its.forEach((it,i)=>{const q=parseFloat(it.qty_physical_count||0);vh+=`<div style="padding:12px;border:1px solid #E0E0E0;border-radius:8px;margin-bottom:10px"><div style="display:flex;justify-content:space-between"><div><p><strong>Barcode:</strong> ${escapeHtml(it.barcode_data||'N/A')}</p><p><strong>Property:</strong> ${escapeHtml(it.property_no||'N/A')}</p><p><strong>Available:</strong> ${q.toFixed(2)} ${escapeHtml(it.uom||'pcs')}</p></div><div style="text-align:right"><input type="number" id="qty_${gi}_${i}" value="1" min="1" max="${q}" style="width:70px;padding:5px;border:1px solid #E0E0E0;border-radius:5px;margin-bottom:8px"><button onclick="selectVariantAndAdd(${it.id},${gi},${i},'${rid}')" class="btn-primary" style="width:100%;padding:8px">Add</button></div></div></div>`});
    vh+='</div></div>';vm.innerHTML=vh;document.body.appendChild(vm);
    vm.addEventListener('click',function(e){if(e.target===vm)closeVariantModal(gi)});
}

function closeVariantModal(gi){const vm=document.getElementById('vs_'+gi);if(vm)vm.remove()}
function selectVariantAndAdd(id,gi,vi,rid){const it=inventoryData[id];if(!it)return;const q=parseFloat(document.getElementById('qty_'+gi+'_'+vi)?.value||1),m=parseFloat(it.qty_physical_count||0);if(q<1||q>m){alert('Please enter 1 to '+m);return}addToCart(id,q);closeVariantModal(gi);document.getElementById(rid).classList.remove('show');document.getElementById(rid).innerHTML=''}

function addToCart(id,q=1){const it=inventoryData[id];if(!it)return;const ei=cartItems.findIndex(i=>i.id==id);if(ei!==-1){const nq=cartItems[ei].quantity+q;if(nq>it.qty_physical_count){alert('Max available: '+it.qty_physical_count);return}cartItems[ei].quantity=nq}else{if(q>it.qty_physical_count){alert('Max available: '+it.qty_physical_count);return}cartItems.push({id:it.id,name:it.article_name,property_no:it.property_no,uom:it.uom,available_qty:it.qty_physical_count,unit_value:it.unit_value,quantity:q})}updateCartDisplay()}
function removeFromCart(id){cartItems=cartItems.filter(i=>i.id!=id);updateCartDisplay()}
function updateCartQuantity(id,nq){const ei=cartItems.findIndex(i=>i.id==id);if(ei===-1)return;const it=inventoryData[id];nq=parseInt(nq);if(isNaN(nq)||nq<1)nq=1;if(nq>it.qty_physical_count)nq=it.qty_physical_count;cartItems[ei].quantity=nq;updateCartDisplay()}

function updateCartDisplay(){
    const c=document.getElementById('selectedItemsList'),em=document.getElementById('emptyCartMessage'),sc=document.getElementById('selectedCount'),cs=document.getElementById('cartSummary');
    if(cartItems.length===0){em.style.display='block';c.innerHTML='';if(sc)sc.innerText='0';cs.style.display='none';return}
    em.style.display='none';let h='',ti=0,tv=0;
    cartItems.forEach(item=>{const it=item.unit_value*item.quantity;ti+=item.quantity;tv+=it;h+=`<div class="selected-item-card"><div class="selected-item-info"><div class="item-name">${escapeHtml(item.name)}</div><div class="item-property">Property: ${escapeHtml(item.property_no||'N/A')}</div><div class="item-property">${formatCurrency(item.unit_value)} each</div></div><div class="selected-item-qty"><input type="number" value="${item.quantity}" min="1" max="${item.available_qty}" onchange="updateCartQuantity(${item.id},this.value)"><span>${escapeHtml(item.uom||'pcs')}</span><span style="font-weight:bold">${formatCurrency(it)}</span></div><button class="btn-remove-item" onclick="removeFromCart(${item.id})"><i class="fas fa-trash"></i></button></div>`});
    c.innerHTML=h;if(sc)sc.innerText=cartItems.length;cs.style.display='block';cs.innerHTML=`<strong>Total Items: ${ti}</strong> | <strong>Total Value: ${formatCurrency(tv)}</strong>`;updateFormInputs()
}

function updateFormInputs(){document.querySelectorAll('input[name="inventory_ids[]"]').forEach(e=>e.remove());document.querySelectorAll('input[name="quantities[]"]').forEach(e=>e.remove());const f=document.getElementById('issueForm');cartItems.forEach(i=>{let ii=document.createElement('input');ii.type='hidden';ii.name='inventory_ids[]';ii.value=i.id;f.appendChild(ii);let qi=document.createElement('input');qi.type='hidden';qi.name='quantities[]';qi.value=i.quantity;f.appendChild(qi)})}

function validateIssueForm(){
    if(!document.getElementById('issued_to').value){alert('Select an employee');return false}
    if(!document.getElementById('purpose').value.trim()){alert('Enter purpose');return false}
    if(cartItems.length===0){alert('Add at least one item');return false}
    return confirm('Issue '+cartItems.length+' item(s) to this employee?')
}

function viewIssuanceDetails(id){
    let m=document.getElementById('dynamic-modal');
    if(!m){m=document.createElement('div');m.id='dynamic-modal';m.style.cssText='display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.5)';m.innerHTML=`<div style="background:#fff;margin:10% auto;width:500px;max-width:90%;border-radius:12px"><div style="padding:20px;border-bottom:2px solid #FFD8E0;display:flex;justify-content:space-between"><h2>Issuance Details</h2><span style="font-size:28px;cursor:pointer" onclick="document.getElementById('dynamic-modal').style.display='none'">&times;</span></div><div style="padding:20px" id="modal-body">Loading...</div></div>`;document.body.appendChild(m)}
    m.style.display='block';document.getElementById('modal-body').innerHTML='<div style="text-align:center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    fetch('<?php echo SITE_URL; ?>/api/get_issuance_details.php?id='+id).then(r=>r.json()).then(d=>{if(d.error){document.getElementById('modal-body').innerHTML='<div style="color:red">'+d.error+'</div>';return}document.getElementById('modal-body').innerHTML=`<table style="width:100%"><tr><td><strong>Item:</strong>20%<td>20%<td>${escapeHtml(d.article_name||'N/A')}20%</tr>20%<tr>20%<td><strong>Property No.:</strong>20%</td>20%<td>${escapeHtml(d.property_no||'N/A')}20%</td>20%</tr>20%<tr>20%<td><strong>Issued To:</strong>20%</td>20%<td>${escapeHtml(d.issued_to_name||'N/A')}20%</td>20%</tr>20%<tr>20%<td><strong>Department:</strong>20%</td>20%<td>${escapeHtml(d.department_name||'N/A')}20%</td>20%</tr>20%<table>20%</table>`}).catch(()=>{document.getElementById('modal-body').innerHTML='<div style="color:red">Error loading</div>'})
}

function processHardwareBarcode(barcode){const st=barcode.toLowerCase().trim();let fi=null;for(let id in inventoryData){const it=inventoryData[id],be=(it.barcode_data||'').toLowerCase().trim(),bn=be.replace(/[\s\-\.]/g,''),sn=st.replace(/[\s\-\.]/g,''),al=(it.article_name||'').toLowerCase(),pl=(it.property_no||'').toLowerCase();if(bn===sn||(be&&be===st)||(be&&be.includes(st))||al.includes(st)||pl.includes(st)){fi=it;break}}
if(fi){const ei=hardwareScannedItems.findIndex(i=>i.id==fi.id);if(ei!==-1){const c=hardwareScannedItems[ei],m=parseFloat(fi.qty_physical_count||0);if(c.quantity<m){c.quantity++;showScanSuccess(fi.article_name,c.quantity)}else showScanWarning(fi.article_name,m)}else{const a=parseFloat(fi.qty_physical_count||0);if(a<=0){showScanWarning(fi.article_name,0);return}hardwareScannedItems.push({id:fi.id,name:fi.article_name,property_no:fi.property_no,barcode_data:fi.barcode_data,uom:fi.uom||'pcs',available_qty:a,unit_value:fi.unit_value||0,quantity:1});showScanSuccess(fi.article_name,1)}updateHardwareScannedItemsDisplay()}else showScanError(barcode)}

function showScanSuccess(n,q){const i=document.getElementById('hardwareScannerInput');if(!i)return;i.style.backgroundColor='#C8E6C9';i.style.borderColor='#4CAF50';i.placeholder='✓ Added: '+n+' (Qty: '+q+')';setTimeout(()=>{i.style.backgroundColor='';i.style.borderColor='#FF6B35';i.placeholder='Place cursor here and scan...'},1500)}
function showScanWarning(n,m){const i=document.getElementById('hardwareScannerInput');if(!i)return;i.style.backgroundColor='#FFF3E0';i.style.borderColor='#FF9800';i.placeholder=m>0?'⚠ Max: '+n+' ('+m+')':'⚠ Out of stock: '+n;setTimeout(()=>{i.style.backgroundColor='';i.style.borderColor='#FF6B35';i.placeholder='Place cursor here and scan...'},2000)}
function showScanError(b){const i=document.getElementById('hardwareScannerInput');if(!i)return;i.style.backgroundColor='#FFCDD2';i.style.borderColor='#f44336';i.placeholder='✗ Not found: '+b;setTimeout(()=>{i.style.backgroundColor='';i.style.borderColor='#FF6B35';i.placeholder='Place cursor here and scan...'},2000)}

function updateHardwareScannedItemsDisplay(){const c=document.getElementById('hardwareScannedItemsContainer'),ib=document.getElementById('issueItemBtn'),cb=document.getElementById('clearScansBtn');if(!c)return;if(hardwareScannedItems.length===0){c.innerHTML='<div class="empty-scan-state"><i class="fas fa-box"></i><p>No items scanned yet</p></div>';if(ib)ib.disabled=true;if(cb)cb.style.display='none'}else{let h='',ti=0,tv=0;hardwareScannedItems.forEach((item,idx)=>{const it=item.quantity*item.unit_value;ti+=item.quantity;tv+=it;h+=`<div class="scanned-item-card success"><div class="scanned-item-info"><div class="scanned-item-name"><i class="fas fa-check-circle"></i> ${escapeHtml(item.name)}</div><div class="scanned-item-details">Property: ${escapeHtml(item.property_no||'N/A')} | Stock: ${item.available_qty} ${escapeHtml(item.uom||'pcs')} | Value: ${formatCurrency(item.unit_value)}</div></div><div class="scanned-item-qty-controls"><button class="qty-btn" onclick="decreaseHardwareQty(${idx})">−</button><input type="number" class="qty-input" value="${item.quantity}" min="1" max="${item.available_qty}" onchange="updateHardwareQty(${idx},this.value)"><button class="qty-btn" onclick="increaseHardwareQty(${idx})">+</button><button class="remove-scanned-item" onclick="removeHardwareScannedItem(${idx})"><i class="fas fa-trash"></i> Remove</button></div></div>`});h+=`<div style="margin-top:15px;padding:12px;background:#F5F5F5;border-radius:8px;text-align:right;font-weight:bold">Items: ${hardwareScannedItems.length} | Total Qty: ${ti} | Total Value: ${formatCurrency(tv)}</div>`;c.innerHTML=h;if(ib)ib.disabled=false;if(cb)cb.style.display='inline-block'}}
function increaseHardwareQty(idx){if(idx<hardwareScannedItems.length){const it=hardwareScannedItems[idx];if(it.quantity<it.available_qty){it.quantity++;updateHardwareScannedItemsDisplay()}else showScanWarning(it.name,it.available_qty)}}
function decreaseHardwareQty(idx){if(idx<hardwareScannedItems.length&&hardwareScannedItems[idx].quantity>1){hardwareScannedItems[idx].quantity--;updateHardwareScannedItemsDisplay()}}
function updateHardwareQty(idx,v){if(idx<hardwareScannedItems.length){const it=hardwareScannedItems[idx],nq=parseInt(v);it.quantity=isNaN(nq)||nq<1?1:nq>it.available_qty?it.available_qty:nq;updateHardwareScannedItemsDisplay()}}
function removeHardwareScannedItem(idx){if(confirm('Remove "'+hardwareScannedItems[idx].name+'"?')){hardwareScannedItems.splice(idx,1);updateHardwareScannedItemsDisplay()}}
function clearHardwareScans(){if(hardwareScannedItems.length>0&&confirm('Clear all '+hardwareScannedItems.length+' item(s)?')){hardwareScannedItems=[];updateHardwareScannedItemsDisplay()}}

function issueHardwareScannedItems(){
    if(hardwareScannedItems.length===0){alert('No items to issue');return}
    const isl=document.getElementById('issued_to');if(!isl||!isl.value){alert('Please select an employee first');closeHardwareScannerModal();if(isl)isl.focus();return}
    const pt=document.getElementById('purpose');if(!pt||!pt.value.trim()){alert('Please enter a purpose');closeHardwareScannerModal();if(pt)pt.focus();return}
    let ac=0,sk=0;hardwareScannedItems.forEach(item=>{const ec=cartItems.find(i=>i.id==item.id);if(ec){ec.quantity=Math.min(ec.quantity+item.quantity,item.available_qty);ac++}else{const ol=cartItems.length;addToCart(item.id,item.quantity);if(cartItems.length>ol)ac++;else sk++}});
    closeHardwareScannerModal();
    let msg=ac+' item(s) added.';if(sk>0)msg+='\n'+sk+' skipped.';msg+='\n\nClick "Issue Selected Items" to complete.';alert(msg);
    const sb=document.querySelector('#issueForm button[type="submit"]');if(sb){sb.scrollIntoView({behavior:'smooth',block:'center'});sb.style.boxShadow='0 0 20px rgba(248,176,192,.8)';setTimeout(()=>sb.style.boxShadow='',2000)}
}

document.addEventListener('DOMContentLoaded',function(){
    document.getElementById('barcode_input')?.addEventListener('keypress',function(e){if(e.key==='Enter'){e.preventDefault();searchBarcode()}});
    document.getElementById('modal_barcode_input')?.addEventListener('keypress',function(e){if(e.key==='Enter'){e.preventDefault();performBarcodeSearch(this.value.trim(),'modal_barcode_input','modal_barcode_result')}});
    const hsi=document.getElementById('hardwareScannerInput');if(hsi){hsi.addEventListener('keypress',function(e){if(e.key==='Enter'){e.preventDefault();const b=this.value.trim();if(b){processHardwareBarcode(b);this.value='';this.focus()}}});hsi.addEventListener('paste',function(){setTimeout(()=>{const v=this.value.trim();if(v&&v.length>2){const cb=v.replace(/\n/g,'').trim();if(cb!==v){processHardwareBarcode(cb);this.value='';this.focus()}}},50)})}
    document.addEventListener('keydown',function(e){if(e.key==='Escape'){const hm=document.getElementById('hardwareScannerModal');if(hm&&hm.classList.contains('show'))closeHardwareScannerModal();const sm=document.getElementById('scannerModal');if(sm&&sm.classList.contains('show'))closeScannerModal()}});
    const hm=document.getElementById('hardwareScannerModal');if(hm)hm.addEventListener('click',function(e){if(e.target===hm)closeHardwareScannerModal()});
    document.getElementById('barcode_input')?.focus();
});
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>