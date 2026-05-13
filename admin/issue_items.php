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

// ========== HANDLE PAR PRINT ==========
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
               sig.name as signatory_name,
               sig.position as signatory_position,
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
        LEFT JOIN signatories sig ON ei.signatory_id = sig.id
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
        <!DOCTYPE html><html><head><title>PAR <?php echo $par_no; ?></title>
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
            .signature-box{width:30%;text-align:center}
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
            <div class="par-header"><span>Fund Cluster: <?php echo htmlspecialchars($fund_cluster); ?></span><span><?php echo $par_no; ?></span></div>
            <?php if(!empty($location_code) && $location_code != '000000'): ?>
            <div class="par-header" style="margin-top:-10px;font-size:11px"><span>Location Code: <?php echo htmlspecialchars($location_code); ?></span></div>
            <?php endif; ?>
            <table class="items-table">
                <thead><tr><th width="8%">Qty</th><th width="10%">Unit</th><th width="35%">Description</th><th width="20%">Property No.</th><th width="12%">Date Acquired</th><th width="15%" class="amount">Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($par_items as $item): ?>
                    <tr>
                        <td style="text-align:center"><?php echo $item['quantity']; ?></td>
                        <td style="text-align:center"><?php echo htmlspecialchars($item['unit_of_measure'] ?? 'pcs'); ?></td>
                        <td><strong><?php echo htmlspecialchars($item['article_name']); ?></strong><?php if(!empty($item['description'])) echo '<br><small>'.htmlspecialchars($item['description']).'</small>'; ?></td>
                        <td style="text-align:center"><?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?></td>
                        <td style="text-align:center"><?php echo date('m/d/y', strtotime($item['date_acquired'])); ?></td>
                        <td class="amount"><?php echo number_format($item['amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row"><td colspan="5" style="text-align:right"><strong>TOTAL</strong></td><td class="amount"><strong><?php echo number_format($total_amount, 2); ?></strong></td></tr>
                </tbody>
            </table>
            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line">
                        <?php if(!empty($first_item['signatory_name'])): ?>
                            <div class="signature-name"><?php echo htmlspecialchars($first_item['signatory_name']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="signature-title">Received from</div>
                    <?php if(!empty($first_item['signatory_position'])): ?>
                    <div style="font-size:10px"><?php echo htmlspecialchars($first_item['signatory_position']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="signature-box">
                    <div class="signature-line"><div class="signature-name"><?php echo htmlspecialchars($first_item['issued_to_name']); ?></div></div>
                    <div class="signature-title">End-User</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"><div class="signature-name"><?php echo htmlspecialchars($first_item['issued_by_name']); ?></div></div>
                    <div class="signature-title">Supply Officer</div>
                </div>
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
               sig.name as signatory_name,
               sig.position as signatory_position,
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
        LEFT JOIN signatories sig ON ei.signatory_id = sig.id
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
            .signature-box{width:30%;text-align:center}.signature-line{margin-top:40px;border-top:1px solid #000;padding-top:5px}
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
            <div class="par-header" style="margin-top:-10px;font-size:11px"><span>Location Code: <?php echo htmlspecialchars($location_code); ?></span></div>
            <?php endif; ?>
            <table class="items-table">
                <thead><tr><th>Qty</th><th>Unit</th><th>Description</th><th>Property No.</th><th>Date</th><th class="amount">Amount</th></tr></thead>
                <tbody>
                    <?php foreach($items as $item): ?>
                    <tr><td style="text-align:center"><?php echo number_format($item['quantity_issued'],0); ?></td>
                        <td style="text-align:center"><?php echo htmlspecialchars($item['unit_of_measure']??'LOT'); ?></td>
                        <td><strong><?php echo htmlspecialchars($item['article_name']); ?></strong><?php if(!empty($item['description'])) echo '<br><small>'.htmlspecialchars($item['description']).'</small>'; ?></td>
                        <td style="text-align:center"><?php echo htmlspecialchars($item['property_no']??'N/A'); ?></td>
                        <td style="text-align:center"><?php echo date('m/d/y',strtotime($item['issued_date'])); ?></td>
                        <td class="amount"><?php echo number_format($item['unit_value']*$item['quantity_issued'],2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <tr class="total-row"><td colspan="5" style="text-align:right"><strong>TOTAL</strong></td><td class="amount"><strong><?php echo number_format($total_amount,2); ?></strong></td></tr>
                </tbody>
            </table>
            <div class="signature-section">
                <div class="signature-box">
                    <div class="signature-line">
                        <?php if(!empty($first_item['signatory_name'])): ?>
                            <div class="signature-name"><?php echo htmlspecialchars($first_item['signatory_name']); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="signature-title">Received from</div>
                    <?php if(!empty($first_item['signatory_position'])): ?>
                    <div style="font-size:10px"><?php echo htmlspecialchars($first_item['signatory_position']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="signature-box">
                    <div class="signature-line"><div class="signature-name"><?php echo htmlspecialchars($first_item['issued_to_name']); ?></div></div>
                    <div class="signature-title">End-User</div>
                </div>
                <div class="signature-box">
                    <div class="signature-line"><div class="signature-name"><?php echo htmlspecialchars($first_item['issued_by_name']); ?></div></div>
                    <div class="signature-title">Supply Officer</div>
                </div>
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

// ============================================
// HANDLE ISSUANCE FORM SUBMISSION - UPDATED FOR SIGNATORY
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $is_reissue = isset($_POST['is_reissue']) && $_POST['is_reissue'] == '1';
    $original_issuance_id = $is_reissue ? (int)$_POST['original_issuance_id'] : null;
    $inventory_ids = isset($_POST['inventory_ids']) ? $_POST['inventory_ids'] : [];
    $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : [];
    $issued_to = (int)$_POST['issued_to'];
    $purpose = sanitize($_POST['purpose']);
    $condition = sanitize($_POST['condition']);
    $remarks = sanitize($_POST['remarks'] ?? '');
    $signatory_id = !empty($_POST['signatory_id']) ? (int)$_POST['signatory_id'] : NULL;
    
    $conn->begin_transaction();
    try {
        // For reissue - handle single item reissue
        if ($is_reissue && $original_issuance_id) {
            $original_issuance = $conn->query("
                SELECT ei.*, i.qty_physical_count, i.article_name, i.property_no, i.uom 
                FROM equipment_issuance ei 
                JOIN inventory i ON ei.inventory_id = i.id 
                WHERE ei.id = $original_issuance_id
            ")->fetch_assoc();
            
            if (!$original_issuance) throw new Exception("Original issuance not found");
            if ($original_issuance['status'] !== 'issued') throw new Exception("Item already returned, cannot reissue.");
            
            // Update old issuance as returned
            $conn->query("UPDATE equipment_issuance SET status='returned', actual_return=NOW(), condition_on_return='Good' WHERE id=$original_issuance_id");
            
            // Create new issuance with signatory_id
            $stmt = $conn->prepare("
                INSERT INTO equipment_issuance (
                    inventory_id, issued_to, issued_by, signatory_id, quantity_issued, purpose, 
                    condition_on_issue, remarks, status, issued_date
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'issued', NOW())
            ");
            $stmt->bind_param("iiiidsss", 
                $original_issuance['inventory_id'], 
                $issued_to, 
                $_SESSION['user_id'],
                $signatory_id, 
                $original_issuance['quantity_issued'], 
                $purpose, 
                $condition, 
                $remarks
            );
            $stmt->execute();
            $new_issuance_id = $stmt->insert_id;
            
            // Update inventory current holder
            $conn->query("UPDATE inventory SET current_holder=$issued_to WHERE id={$original_issuance['inventory_id']}");
            
            $stmt->close();
            $success_count = 1;
            
        } 
        // For new issuance - handle multiple items
        elseif (count($inventory_ids) > 0) {
            $success_count = 0;
            foreach ($inventory_ids as $index => $inventory_id) {
                $inventory_id = (int)$inventory_id;
                $requested_qty = isset($quantities[$index]) ? floatval($quantities[$index]) : 1;
                
                // Get inventory item details
                $selected_item = $conn->query("SELECT * FROM inventory WHERE id=$inventory_id")->fetch_assoc();
                if (!$selected_item) throw new Exception("Item not found");
                
                // Check available quantity
                if ($selected_item['qty_physical_count'] < $requested_qty) {
                    throw new Exception("Insufficient quantity for: " . $selected_item['article_name']);
                }
                
                // Update inventory quantity
                $new_quantity = $selected_item['qty_physical_count'] - $requested_qty;
                $conn->query("UPDATE inventory SET qty_physical_count=$new_quantity, current_holder=$issued_to WHERE id=$inventory_id");
                
                // Insert into equipment_issuance with signatory_id
                $stmt = $conn->prepare("
                    INSERT INTO equipment_issuance (
                        inventory_id, issued_to, issued_by, signatory_id, quantity_issued, purpose, 
                        condition_on_issue, remarks, status, issued_date
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'issued', NOW())
                ");
                $stmt->bind_param("iiiidsss", 
                    $inventory_id, 
                    $issued_to, 
                    $_SESSION['user_id'],
                    $signatory_id, 
                    $requested_qty, 
                    $purpose, 
                    $condition, 
                    $remarks
                );
                $stmt->execute();
                $issuance_id = $stmt->insert_id;
                $stmt->close();
                
                $success_count++;
            }
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

// ============================================
// HANDLE RETURN
// ============================================
if (isset($_GET['return']) && is_numeric($_GET['return'])) {
    $issuance_id = (int)$_GET['return'];
    $issuance = $conn->query("
        SELECT ei.*, i.qty_physical_count, i.article_name, i.property_no 
        FROM equipment_issuance ei 
        JOIN inventory i ON ei.inventory_id = i.id 
        WHERE ei.id=$issuance_id
    ")->fetch_assoc();
    
    if ($issuance) {
        $conn->begin_transaction();
        try {
            // Update issuance as returned
            $conn->query("
                UPDATE equipment_issuance 
                SET status='returned', actual_return=NOW(), condition_on_return='Good' 
                WHERE id=$issuance_id
            ");
            
            // Add quantity back to inventory
            $new_quantity = $issuance['qty_physical_count'] + $issuance['quantity_issued'];
            $conn->query("UPDATE inventory SET qty_physical_count=$new_quantity WHERE id={$issuance['inventory_id']}");
            
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

// ============================================
// CANCEL REISSUE
// ============================================
if (isset($_GET['cancel_reissue'])) {
    unset($_SESSION['reissue_from']);
    header('Location: ' . SITE_URL . '/admin/issue_items.php');
    exit();
}

// ============================================
// HANDLE REISSUE
// ============================================
if (isset($_GET['reissue']) && is_numeric($_GET['reissue'])) {
    $original_issuance_id = (int)$_GET['reissue'];
    $issuance_check = $conn->query("SELECT status FROM equipment_issuance WHERE id=$original_issuance_id")->fetch_assoc();
    if (!$issuance_check) { 
        $_SESSION['error']='Issuance not found.'; 
        header('Location: '.SITE_URL.'/admin/issue_items.php'); 
        exit(); 
    }
    if ($issuance_check['status']!=='issued') { 
        $_SESSION['error']='Item already returned.'; 
        header('Location: '.SITE_URL.'/admin/issue_items.php'); 
        exit(); 
    }
    $_SESSION['reissue_from'] = $original_issuance_id;
    header('Location: ' . SITE_URL . '/admin/issue_items.php');
    exit();
}

// ============================================
// GET DATA
// ============================================

// Get departments for filter
$departments_list = $conn->query("SELECT id, name, code FROM departments ORDER BY code, name");

// Get sections for filter
$sections_list = $conn->query("SELECT s.id, s.name, d.name as department_name FROM sections s LEFT JOIN departments d ON s.department_id = d.id ORDER BY d.name, s.name");

// Get positions for filter
$positions_list = $conn->query("SELECT DISTINCT position FROM employees WHERE position IS NOT NULL AND position != '' ORDER BY position");

// Get employees list
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

// Get current issuances
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
:root{--primary:#6B8CFF;--secondary:#8FB5FF;--accent:#F8B0C0;--accent-light:#FFD8E0;--success-light:#C5E8C5;--light:#F0F0F0;--white:#FFF;--border-light:#E0E0E0;--text-primary:#3A3A3A;--text-secondary:#6B6B6B;--text-muted:#9E9E9E;--success:#4CAF50;--danger:#f44336;--warning:#FF9800;--info:#2196F3}
body{background:var(--light);color:var(--text-primary)}
.barcode-search-section{background:linear-gradient(135deg,#6B8CFF,#8FB5FF);border-radius:12px;padding:20px;margin-bottom:25px;color:#fff}
.barcode-search-section h4{margin:0 0 10px;font-size:16px}
.barcode-search-box{display:flex;gap:10px;align-items:center;background:#fff;border-radius:50px;padding:5px 5px 5px 20px}
.barcode-search-box input{flex:1;border:none;padding:12px 0;font-size:16px;outline:none;background:transparent}
.barcode-search-box button{background:var(--accent);border:none;padding:10px 25px;border-radius:50px;color:var(--text-primary);font-weight:500;cursor:pointer;transition:all .2s}
.barcode-search-box button:hover{background:#e69eb0;transform:scale(1.02)}
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
.table-container{background:#fff;border-radius:12px;padding:20px;margin-bottom:25px;box-shadow:0 2px 8px rgba(0,0,0,.08);overflow-x:auto}
.table-header{margin-bottom:20px;padding-bottom:10px;border-bottom:2px solid var(--accent-light)}
table{width:100%;border-collapse:collapse}th,td{padding:12px 10px;text-align:left;border-bottom:1px solid var(--border-light)}th{background:#F0F0F0;font-weight:600}
.text-center{text-align:center}
.alert-warning{background:#FFF3E0;border-left:4px solid #FF9800;padding:15px;margin-bottom:20px}
.scanner-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:1500;align-items:center;justify-content:center}
.scanner-modal.show{display:flex}
.scanner-modal-content{background:#fff;border-radius:12px;width:90%;max-width:500px;box-shadow:0 5px 30px rgba(0,0,0,.3)}
.scanner-modal-header{display:flex;justify-content:space-between;align-items:center;padding:20px;background:#6B8CFF;color:#fff;border-radius:12px 12px 0 0}
.close-modal-btn{background:rgba(255,255,255,.2);border:none;color:#fff;font-size:24px;cursor:pointer;width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center}
.scanner-modal-body{padding:25px}.scanner-input-section{margin-bottom:20px}
.scanner-input-section input{width:100%;padding:14px;border:2px solid var(--border-light);border-radius:8px;font-size:16px;box-sizing:border-box}
.scanner-result-box{min-height:100px;padding:15px;background:#F8F9FA;border-radius:8px;text-align:center;color:var(--text-muted)}
.scanner-modal-footer{padding:15px 25px;border-top:1px solid var(--border-light);text-align:right}
.hardware-scanner-modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.8);z-index:3000;align-items:center;justify-content:center}
.hardware-scanner-modal.show{display:flex}
.hardware-scanner-content{background:#fff;border-radius:15px;width:90%;max-width:600px;max-height:90vh;overflow-y:auto;box-shadow:0 10px 50px rgba(0,0,0,.3);display:flex;flex-direction:column}
.hardware-scanner-header{background:#6B8CFF;color:#fff;padding:25px;border-radius:15px 15px 0 0;display:flex;justify-content:space-between;align-items:center}
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
.qty-btn{background:#6B8CFF;color:#fff;border:none;width:28px;height:28px;border-radius:50%;cursor:pointer;font-weight:bold}
.qty-btn:hover{background:#5a7ae6}
.qty-input{width:50px;text-align:center;padding:5px;border:1px solid #E0E0E0;border-radius:5px;font-weight:bold;font-size:13px}
.remove-scanned-item{background:#f44336;color:#fff;border:none;padding:5px 10px;border-radius:5px;cursor:pointer;font-size:12px;margin-left:8px}
.empty-scan-state{text-align:center;padding:40px 20px;color:#999}.empty-scan-state i{font-size:48px;margin-bottom:15px;color:#CCC}
.hardware-scanner-footer{padding:15px 25px;border-top:1px solid #E0E0E0;display:flex;gap:10px;justify-content:flex-end}
.btn-add-all-to-cart{background:#4CAF50;color:#fff;border:none;padding:12px 25px;border-radius:8px;cursor:pointer;font-weight:bold}
.btn-add-all-to-cart:hover{background:#388E3C}
.btn-add-all-to-cart:disabled{opacity:.5;cursor:not-allowed}
.btn-clear-scans{background:#6B8CFF;color:#fff;border:none;padding:12px 25px;border-radius:8px;cursor:pointer;font-weight:bold}
.btn-clear-scans:hover{background:#5a7ae6}
.btn-close-hardware-scanner{background:#757575;color:#fff;border:none;padding:12px 25px;border-radius:8px;cursor:pointer;font-weight:bold}
.btn-close-hardware-scanner:hover{background:#616161}
.user-location-badge{background:#E8F5E9;color:#2E7D32;padding:4px 10px;border-radius:20px;font-size:11px;margin-left:8px}
.user-department-badge{background:#E3F2FD;color:#1565C0;padding:4px 10px;border-radius:20px;font-size:11px;margin-left:8px}
@media(max-width:768px){.stats-grid{grid-template-columns:1fr}}

/* Employee Search Table Styles */
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
    letter-spacing: 0.5px;
}
.search-group input, .search-group select {
    width: 100%;
    padding: 8px 10px;
    border: 1px solid var(--border-light);
    border-radius: 6px;
    font-size: 13px;
}
.search-group select {
    background: #fff;
}
.btn-search-employee {
    background: var(--primary);
    color: white;
    border: none;
    padding: 8px 20px;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 500;
    display: flex;
    align-items: center;
    gap: 5px;
}
.btn-search-employee:hover {
    background: #5a7ae6;
}
.btn-clear-employee {
    background: #f5f5f5;
    border: 1px solid var(--border-light);
    padding: 8px 15px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
}
.btn-clear-employee:hover {
    background: #e0e0e0;
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
    text-transform: uppercase;
    border-bottom: 2px solid var(--accent-light);
}
.employee-results-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--border-light);
    vertical-align: middle;
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
.employee-name-result {
    font-weight: 600;
    color: var(--text-primary);
}
.employee-position-result {
    font-size: 12px;
    color: var(--text-secondary);
}
.employee-dept-result {
    font-size: 12px;
    color: var(--text-muted);
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
.select-employee-btn:hover {
    background: #45a049;
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
.selected-employee-info i {
    color: var(--success);
    font-size: 18px;
}
.selected-employee-info strong {
    color: var(--success);
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

/* Custom Confirmation Modal */
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
    animation: modalBounce 0.3s ease;
    box-shadow: 0 20px 40px rgba(0,0,0,0.3);
}
@keyframes modalBounce {
    0% { opacity: 0; transform: scale(0.8); }
    100% { opacity: 1; transform: scale(1); }
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
    line-height: 1.5;
}
.confirm-modal-buttons {
    display: flex;
    gap: 15px;
    justify-content: center;
}
.confirm-btn-cancel {
    background: #f5f5f5;
    color: #3A3A3A;
    border: none;
    padding: 10px 25px;
    border-radius: 40px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}
.confirm-btn-cancel:hover {
    background: #E0E0E0;
    transform: translateY(-2px);
}
.confirm-btn-confirm {
    background: linear-gradient(135deg, #6B8CFF 0%, #8FB5FF 100%);
    color: white;
    border: none;
    padding: 10px 25px;
    border-radius: 40px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s;
}
.confirm-btn-confirm:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(107,140,255,0.3);
}
</style>
<!-- barcode scanner -->
<div class="barcode-search-section">
    <h4><i class="fas fa-barcode"></i> Physical Barcode Scanner</h4>
    <div style="display:flex;gap:10px;margin-bottom:15px">
        <button type="button" onclick="openHardwareScannerModal()" class="btn-primary" style="flex:1;padding:12px;background:linear-gradient(135deg,#F8B0C0,#e69eb0);border:none;color:#fff"><i class="fas fa-barcode"></i> <strong>Open Hardware Scanner</strong></button>
        <button type="button" onclick="openScannerModal()" class="btn-primary" style="flex:1;padding:12px;background:linear-gradient(135deg,#6B8CFF,#8FB5FF);border:none;color:#fff"><i class="fas fa-camera"></i> Webcam / Manual</button>
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
<!-- hardwaremodal ung radio  -->
<div id="hardwareScannerModal" class="hardware-scanner-modal">
    <div class="hardware-scanner-content">
        <div class="hardware-scanner-header"><h2><i class="fas fa-barcode"></i> Hardware Barcode Scanner</h2><button type="button" class="close-btn" onclick="closeHardwareScannerModal()">&times;</button></div>
        <div class="hardware-scanner-body">
            <div class="scanner-instructions"><strong><i class="fas fa-info-circle"></i> Ready to Scan</strong> Click the input field below and scan barcodes. When done, click "Add to Cart".</div>
            <div class="scanner-input-container">
                <label><i class="fas fa-barcode"></i> Scan Barcode</label>
                <div class="scanner-input-wrapper"><i class="fas fa-barcode"></i><input type="text" id="hardwareScannerInput" class="hardware-scanner-input" placeholder="Place cursor here and scan..." autocomplete="off"></div>
            </div>
            <div class="scanned-items-list"><div id="hardwareScannedItemsContainer"><div class="empty-scan-state"><i class="fas fa-box"></i><p>No items scanned yet</p></div></div></div>
        </div>
        <div class="hardware-scanner-footer">
            <button type="button" class="btn-clear-scans" onclick="clearHardwareScans()" id="clearScansBtn" style="display:none"><i class="fas fa-trash"></i> Clear All</button>
            <button type="button" class="btn-add-all-to-cart" onclick="addHardwareScannedToCart()" id="addToCartBtn" disabled><i class="fas fa-cart-plus"></i> Add to Cart</button>
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
        
        <form method="POST" action="" id="issueForm">
            <?php if ($reissue_item): ?>
            <input type="hidden" name="is_reissue" value="1">
            <input type="hidden" name="original_issuance_id" value="<?php echo $reissue_from_id; ?>">
            <?php endif; ?>
            
            <!-- SELECTED ITEMS SECTION (TOP) -->
            <div class="form-group">
                <label><i class="fas fa-boxes"></i> Selected Items</label>
                <div class="selected-items-grid" id="selectedItemsContainer">
                    <div class="empty-cart" id="emptyCartMessage">
                        <i class="fas fa-barcode"></i>
                        <p>No items selected. Scan barcodes above.</p>
                    </div>
                    <div id="selectedItemsList"></div>
                    <div id="cartSummary" class="cart-summary" style="display:none"></div>
                </div>
            </div>
            
            <!-- ISSUE TO SECTION - SEARCH WITH BUTTON -->
            <div class="form-group">
                <label><i class="fas fa-user-tie"></i> Issue To:</label>
                
                <div class="employee-search-section">
                    <div class="employee-search-bar">
                        <div class="search-group">
                            <label>NAME</label>
                            <input type="text" id="search_employee_name" placeholder="Search by name...">
                        </div>
                        <div class="search-group">
                            <label>DEPARTMENT</label>
                            <select id="search_department">
                                <option value="">All Departments</option>
                                <?php if($departments_list && $departments_list->num_rows > 0): 
                                    $departments_list->data_seek(0);
                                    while($dept = $departments_list->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($dept['name']); ?>"><?php echo htmlspecialchars($dept['code'] . ' - ' . $dept['name']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="search-group">
                            <label>SECTION</label>
                            <select id="search_section">
                                <option value="">All Sections</option>
                                <?php if($sections_list && $sections_list->num_rows > 0): 
                                    $sections_list->data_seek(0);
                                    while($sec = $sections_list->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($sec['name']); ?>"><?php echo htmlspecialchars($sec['name']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="search-group">
                            <label>POSITION</label>
                            <select id="search_position">
                                <option value="">All Positions</option>
                                <?php if($positions_list && $positions_list->num_rows > 0): 
                                    $positions_list->data_seek(0);
                                    while($pos = $positions_list->fetch_assoc()): ?>
                                    <option value="<?php echo htmlspecialchars($pos['position']); ?>"><?php echo htmlspecialchars($pos['position']); ?></option>
                                <?php endwhile; endif; ?>
                            </select>
                        </div>
                        <div class="search-group" style="flex: 0.5;">
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
                    
                    <!-- Search Results Table -->
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
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <!-- Selected Employee Display -->
                <div id="selected_employee_display" style="display: none; margin-top: 10px; padding: 12px 15px; background: var(--success-light); border-radius: 6px;">
                    <i class="fas fa-user-check" style="color: var(--success); margin-right: 10px;"></i>
                    <span style="color: var(--success);">Selected: <strong id="selected_employee_name"></strong></span>
                </div>
                <input type="hidden" id="selected_employee_id" name="issued_to" value="">
            </div>
            
            <!-- SIGNATORY DROPDOWN -->
            <div class="form-group">
                <label for="signatory_id"><i class="fas fa-signature"></i> Signatory <small>(Optional)</small></label>
                <select name="signatory_id" id="signatory_id" class="form-control">
                    <option value="">-- Select Signatory (Optional) --</option>
                    <?php
                    // Fetch active signatories
                    $signatories_query = $conn->query("
                        SELECT s.*, 
                               CASE 
                                   WHEN s.employee_id IS NOT NULL THEN CONCAT(e.firstname, ' ', e.lastname)
                                   ELSE s.name 
                               END as display_name,
                               CASE 
                                   WHEN s.employee_id IS NOT NULL THEN e.position
                                   ELSE s.position 
                               END as display_position
                        FROM signatories s
                        LEFT JOIN employees e ON s.employee_id = e.id
                        WHERE s.is_active = 1
                        ORDER BY s.name ASC
                    ");
                    
                    if ($signatories_query && $signatories_query->num_rows > 0) {
                        while ($signatory = $signatories_query->fetch_assoc()) {
                            $display_text = htmlspecialchars($signatory['display_name']);
                            if (!empty($signatory['display_position'])) {
                                $display_text .= ' - ' . htmlspecialchars($signatory['display_position']);
                            }
                            echo '<option value="' . $signatory['id'] . '">' . $display_text . '</option>';
                        }
                    }
                    ?>
                </select>
                <small class="text-muted">Select a signatory for the issuance documents</small>
            </div>
            
            <div class="form-group">
                <label for="purpose"><i class="fas fa-clipboard"></i> Purpose</label>
                <input type="text" id="purpose" name="purpose" class="form-control" placeholder="Enter purpose of issuance" required>
            </div>
            
            <div class="form-group">
                <label>Condition</label>
                <select name="condition" class="form-control">
                    <option value="Serviceable">Serviceable</option>
                    <option value="Good">Good</option>
                    <option value="Fair">Fair</option>
                    <option value="Poor">Poor</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="Optional notes"></textarea>
            </div>
            
            <div class="form-group">
                <button type="button" class="btn-primary" id="submitBtn" onclick="showConfirmModal()">
                    <i class="fas fa-hand-holding"></i> <?php echo $reissue_item?'Reissue':'Issue Selected Items'; ?> (<span id="selectedCount">0</span>)
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Currently Issued Items Section -->
<div class="stat-chart">
    <h3><i class="fas fa-clipboard-list"></i> Currently Issued Items</h3>
    <div style="max-height:500px;overflow-y:auto">
        <table style="width:100%">
            <thead>
                <tr>
                    <th>Employee</th>
                    <th>Department / Section</th>
                    <th>Location Code</th>
                    <th>Items</th>
                    <th>Total Qty</th>
                    <th>Actions</th>
                </tr>
            </thead>
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
                        <a href="?return=<?php echo $item['id']; ?>" class="action-btn success" onclick="return confirmReturnItem(event, this)"><i class="fas fa-undo"></i></a>
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

<!-- Issuance History Table -->
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
            CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
            CONCAT(ub.firstname, ' ', ub.lastname) as issued_by_name,
            e.position,
            s.name as section_name,
            d.name as department_name
        FROM equipment_issuance ei 
        JOIN inventory i ON ei.inventory_id = i.id 
        JOIN employees e ON ei.issued_to = e.id
        LEFT JOIN sections s ON e.section_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        JOIN users ub ON ei.issued_by = ub.id 
        ORDER BY ei.issued_date DESC 
        LIMIT 50
    ");
    ?>
    <?php if($history && $history->num_rows > 0): ?>
    <div style="overflow-x: auto;">
        <table style="width: 100%; min-width: 1000px;">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Item</th>
                    <th>Property No.</th>
                    <th>Issued To</th>
                    <th>Department/Section</th>
                    <th>Issued By</th>
                    <th>Qty</th>
                    <th>Purpose</th>
                    <th>Status</th>
                    <th>Return Date</th>
                </tr>
            </thead>
            <tbody>
                <?php while($item = $history->fetch_assoc()): ?>
                <tr>
                    <td style="white-space: nowrap;"><?php echo date('M d, Y',strtotime($item['issued_date'])); ?></td>
                    <td><strong><?php echo htmlspecialchars($item['article_name']); ?></strong></td>
                    <td><code><?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?></code></td>
                    <td><?php echo htmlspecialchars($item['issued_to_name']); ?></td>
                    <td>
                        <?php 
                        $loc_parts = [];
                        if(!empty($item['department_name'])) $loc_parts[] = $item['department_name'];
                        if(!empty($item['section_name'])) $loc_parts[] = $item['section_name'];
                        echo !empty($loc_parts) ? htmlspecialchars(implode(' → ', $loc_parts)) : '-';
                        ?>
                    </td>
                    <td><?php echo htmlspecialchars($item['issued_by_name']); ?></td>
                    <td><?php echo $item['quantity_issued']; ?></td>
                    <td style="max-width: 200px;"><?php echo htmlspecialchars(substr($item['purpose'] ?? '', 0, 50)); ?></td>
                    <td>
                        <?php 
                        $status_color = $item['status'] == 'issued' ? '#FF9800' : ($item['status'] == 'returned' ? '#4CAF50' : '#f44336');
                        $status_bg = $item['status'] == 'issued' ? '#FFF3E0' : ($item['status'] == 'returned' ? '#E8F5E9' : '#FFEBEE');
                        echo '<span style="background:'.$status_bg.';color:'.$status_color.';padding:4px 10px;border-radius:20px;font-size:11px;font-weight:600">'.ucfirst($item['status']).'</span>';
                        ?>
                    </td>
                    <td><?php echo $item['actual_return'] ? date('M d, Y', strtotime($item['actual_return'])) : '—'; ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align: center; padding: 60px 20px;">
        <i class="fas fa-inbox" style="font-size: 64px; color: var(--text-muted); margin-bottom: 20px; display: block;"></i>
        <h4 style="color: var(--text-secondary); margin-bottom: 8px;">No issuance records found</h4>
        <p style="color: var(--text-muted); font-size: 13px;">When items are issued, they will appear here.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Custom Confirmation Modal -->
<div id="confirmModal" class="confirm-modal">
    <div class="confirm-modal-content">
        <div class="confirm-modal-icon">
            <i class="fas fa-question-circle"></i>
        </div>
        <h3>Confirm Issuance</h3>
        <p id="confirmModalMessage">Issue item(s) to this employee?</p>
        <div class="confirm-modal-buttons">
            <button class="confirm-btn-cancel" onclick="closeConfirmModal()">Cancel</button>
            <button class="confirm-btn-confirm" onclick="submitForm()">Confirm</button>
        </div>
    </div>
</div>

<script>
// Store inventory data
const inventoryData = <?php echo json_encode($inventory_data); ?>;
let cartItems = [];
let hardwareScannedItems = [];
let selectedEmployeeId = null;

// Search for employees
function searchEmployees() {
    const name = document.getElementById('search_employee_name').value.trim();
    const department = document.getElementById('search_department').value;
    const section = document.getElementById('search_section').value;
    const position = document.getElementById('search_position').value;
    
    let params = [];
    if (name) params.push('name=' + encodeURIComponent(name));
    if (department) params.push('department=' + encodeURIComponent(department));
    if (section) params.push('section=' + encodeURIComponent(section));
    if (position) params.push('position=' + encodeURIComponent(position));
    
    const queryString = params.join('&');
    
    fetch('<?php echo SITE_URL; ?>/api/search_employees.php?' + queryString)
        .then(response => response.json())
        .then(data => {
            displayEmployeeResults(data);
        })
        .catch(error => {
            console.error('Error:', error);
            const tbody = document.getElementById('employee_results_body');
            tbody.innerHTML = '<tr><td colspan="4" class="no-results"><i class="fas fa-exclamation-triangle"></i><p>Error loading employees</p></td></tr>';
        });
}

function displayEmployeeResults(employees) {
    const tbody = document.getElementById('employee_results_body');
    
    if (!employees || employees.length === 0) {
        tbody.innerHTML = '<tr><td colspan="4" class="no-results"><i class="fas fa-search"></i><p>No employees found</p></td></tr>';
        return;
    }
    
    let html = '';
    employees.forEach(emp => {
        const isSelected = (selectedEmployeeId == emp.id);
        const selectedClass = isSelected ? 'selected' : '';
        
        html += `
            <tr class="employee-result-row ${selectedClass}" onclick="selectEmployeeFromResult(${emp.id}, '${escapeHtml(emp.firstname + ' ' + emp.lastname)}', '${escapeHtml(emp.position || '')}', '${escapeHtml(emp.department_name || '')}')">
                <td class="employee-name-result">${escapeHtml(emp.lastname + ', ' + emp.firstname)}</td>
                <td class="employee-position-result">${escapeHtml(emp.position || '—')}</td>
                <td class="employee-dept-result">${escapeHtml(emp.department_name || '—')}</td>
                <td><button class="select-employee-btn" onclick="event.stopPropagation();selectEmployeeFromResult(${emp.id}, '${escapeHtml(emp.firstname + ' ' + emp.lastname)}', '${escapeHtml(emp.position || '')}', '${escapeHtml(emp.department_name || '')}')">Select</button></td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

function selectEmployeeFromResult(id, name, position, department) {
    selectedEmployeeId = id;
    document.getElementById('selected_employee_id').value = id;
    
    let displayText = name;
    if (position) displayText += ` - ${position}`;
    if (department) displayText += ` (${department})`;
    
    document.getElementById('selected_employee_name').innerHTML = displayText;
    document.getElementById('selected_employee_display').style.display = 'flex';
    
    // Update selected row styling
    document.querySelectorAll('.employee-result-row').forEach(row => {
        row.classList.remove('selected');
    });
    
    const rows = document.querySelectorAll('.employee-result-row');
    for (let row of rows) {
        if (row.cells && row.cells[0] && row.cells[0].innerText.includes(name.split(' ')[0])) {
            row.classList.add('selected');
            break;
        }
    }
}

function clearEmployeeSearch() {
    document.getElementById('search_employee_name').value = '';
    document.getElementById('search_department').value = '';
    document.getElementById('search_section').value = '';
    document.getElementById('search_position').value = '';
    
    const tbody = document.getElementById('employee_results_body');
    tbody.innerHTML = '<tr><td colspan="4" class="no-results"><i class="fas fa-search"></i><p>Click "Search" to find employees</p></td></tr>';
}

function showConfirmModal() {
    const employeeId = document.getElementById('selected_employee_id').value;
    const purposeField = document.getElementById('purpose');
    const cartCount = cartItems.length;
    
    if (!employeeId) {
        alert('Please search and select an employee to issue to');
        return false;
    }
    if (!purposeField.value.trim()) {
        alert('Please enter a purpose');
        purposeField.focus();
        return false;
    }
    if (cartCount === 0 && (!document.getElementById('selectedItemsList') || document.getElementById('selectedItemsList').innerHTML === '')) {
        alert('Please add at least one item');
        return false;
    }
    
    const employeeName = document.getElementById('selected_employee_name').innerHTML;
    const itemCount = cartCount > 0 ? cartCount : 1;
    
    document.getElementById('confirmModalMessage').innerHTML = `Issue ${itemCount} item(s) to ${employeeName}?`;
    document.getElementById('confirmModal').classList.add('show');
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('show');
}

function submitForm() {
    closeConfirmModal();
    const form = document.getElementById('issueForm');
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
    form.submit();
}

function confirmReturnItem(event, element) {
    event.preventDefault();
    const modal = document.getElementById('confirmModal');
    document.getElementById('confirmModalMessage').innerHTML = `Return this item?`;
    modal.classList.add('show');
    
    window.pendingReturnUrl = element.getAttribute('href');
    window.submitForm = function() {
        closeConfirmModal();
        window.location.href = window.pendingReturnUrl;
        window.submitForm = originalSubmitForm;
    };
    return false;
}

function originalSubmitForm() {
    closeConfirmModal();
    const form = document.getElementById('issueForm');
    const submitBtn = document.getElementById('submitBtn');
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner"></span> Processing...';
    form.submit();
}

function resetSubmitForm() {
    window.submitForm = originalSubmitForm;
}

function searchBarcode() {
    performBarcodeSearch(document.getElementById('barcode_input').value.trim(), 'barcode_input', 'barcode_result');
}

function openScannerModal() {
    const m = document.getElementById('scannerModal');
    if (m) {
        m.classList.add('show');
        setTimeout(() => document.getElementById('modal_barcode_input').focus(), 100);
    }
}

function closeScannerModal() {
    document.getElementById('scannerModal').classList.remove('show');
    document.getElementById('modal_barcode_result').innerHTML = '';
    document.getElementById('modal_barcode_input').value = '';
}

function openHardwareScannerModal() {
    const m = document.getElementById('hardwareScannerModal');
    if (m) {
        m.classList.add('show');
        hardwareScannedItems = [];
        updateHardwareScannedItemsDisplay();
        setTimeout(() => {
            const i = document.getElementById('hardwareScannerInput');
            if (i) i.focus();
        }, 100);
    }
}

function closeHardwareScannerModal() {
    document.getElementById('hardwareScannerModal').classList.remove('show');
}

function escapeHtml(s) {
    if (!s) return '';
    return s.replace(/[&<>]/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[m]));
}

function formatCurrency(a) {
    return '₱' + parseFloat(a || 0).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function performBarcodeSearch(barcode, inputId, resultId) {
    const rd = document.getElementById(resultId);
    const st = barcode.toLowerCase().trim();
    if (!st) {
        rd.innerHTML = '<div class="alert-warning" style="padding:10px">Please enter a barcode</div>';
        rd.classList.add('show');
        return;
    }
    
    let found = [];
    for (let id in inventoryData) {
        const it = inventoryData[id];
        const be = (it.barcode_data || '').toLowerCase().trim();
        const bn = be.replace(/\s+/g, '');
        const sn = st.replace(/\s+/g, '');
        const al = (it.article_name || '').toLowerCase();
        const pl = (it.property_no || '').toLowerCase();
        
        if (bn === sn || (be && be.includes(st)) || al.includes(st) || pl.includes(st)) {
            found.push(it);
        }
    }
    
    if (found.length > 0) {
        const gi = {};
        found.forEach(i => {
            if (!gi[i.article_name]) gi[i.article_name] = [];
            gi[i.article_name].push(i);
        });
        
        let h = '';
        let gIdx = 0;
        for (const an in gi) {
            const its = gi[an];
            const ta = its.reduce((s, i) => s + parseFloat(i.qty_physical_count || 0), 0);
            const fi = its[0];
            const aic = cartItems.find(i => its.some(si => si.id == i.id));
            window['variantGroup_' + gIdx] = its;
            h += `
                <div class="result-item" style="margin-bottom:15px">
                    <div class="result-info">
                        <h5>${escapeHtml(an)}</h5>
                        <p>Available: <strong>${ta.toFixed(2)} ${fi.uom || 'pcs'}</strong> | Value: ${formatCurrency(fi.unit_value)}</p>
                        <p style="font-size:12px;color:#666">${fi.category ? `Category: <strong>${escapeHtml(fi.category)}</strong>` : ''}${fi.type_equipment && fi.type_equipment !== fi.category ? ` | Type: <strong>${escapeHtml(fi.type_equipment)}</strong>` : ''}</p>
                    </div>
                    <div class="result-actions">
                        ${aic ? '<button class="btn-add-item" disabled>Already Added</button>' : `<button class="btn-add-item" onclick="openVariantSelector(${gIdx},'${resultId}')">Select Variant & Add</button>`}
                    </div>
                </div>
            `;
            gIdx++;
        }
        rd.innerHTML = h;
        rd.classList.add('show');
        document.getElementById(inputId).value = '';
    } else {
        rd.innerHTML = `<div class="result-item"><div class="result-info"><h5 style="color:#f44336">Item not found</h5><p>No item found for: <strong>${escapeHtml(barcode)}</strong></p></div></div>`;
        rd.classList.add('show');
    }
}

function openVariantSelector(gi, rid) {
    const its = window['variantGroup_' + gi];
    if (!its || !its.length) return;
    
    const vm = document.createElement('div');
    vm.id = 'vs_' + gi;
    vm.style.cssText = 'display:flex;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.6);z-index:2000;align-items:center;justify-content:center';
    
    let vh = `
        <div style="background:#fff;border-radius:12px;max-width:500px;width:90%;max-height:80vh;overflow-y:auto">
            <div style="padding:20px;background:linear-gradient(135deg,#6B8CFF,#8FB5FF);color:#fff;border-radius:12px 12px 0 0;display:flex;justify-content:space-between;align-items:center">
                <h3 style="margin:0">Select ${escapeHtml(its[0].article_name)}</h3>
                <button onclick="closeVariantModal(${gi})" style="background:rgba(255,255,255,.2);border:none;color:#fff;font-size:24px;cursor:pointer;width:36px;height:36px;border-radius:50%">&times;</button>
            </div>
            <div style="padding:20px">
    `;
    
    its.forEach((it, i) => {
        const q = parseFloat(it.qty_physical_count || 0);
        vh += `
            <div style="padding:12px;border:1px solid #E0E0E0;border-radius:8px;margin-bottom:10px">
                <div style="display:flex;justify-content:space-between">
                    <div>
                        <p><strong>Barcode:</strong> ${escapeHtml(it.barcode_data || 'N/A')}</p>
                        <p><strong>Property:</strong> ${escapeHtml(it.property_no || 'N/A')}</p>
                        <p><strong>Available:</strong> ${q.toFixed(2)} ${escapeHtml(it.uom || 'pcs')}</p>
                    </div>
                    <div style="text-align:right">
                        <input type="number" id="qty_${gi}_${i}" value="1" min="1" max="${q}" style="width:70px;padding:5px;border:1px solid #E0E0E0;border-radius:5px;margin-bottom:8px">
                        <button onclick="selectVariantAndAdd(${it.id},${gi},${i},'${rid}')" class="btn-primary" style="width:100%;padding:8px">Add</button>
                    </div>
                </div>
            </div>
        `;
    });
    
    vh += '</div></div>';
    vm.innerHTML = vh;
    document.body.appendChild(vm);
    vm.addEventListener('click', function(e) { if (e.target === vm) closeVariantModal(gi); });
}

function closeVariantModal(gi) {
    const vm = document.getElementById('vs_' + gi);
    if (vm) vm.remove();
}

function selectVariantAndAdd(id, gi, vi, rid) {
    const it = inventoryData[id];
    if (!it) return;
    const q = parseFloat(document.getElementById('qty_' + gi + '_' + vi)?.value || 1);
    const m = parseFloat(it.qty_physical_count || 0);
    if (q < 1 || q > m) {
        alert('Please enter 1 to ' + m);
        return;
    }
    addToCart(id, q);
    closeVariantModal(gi);
    document.getElementById(rid).classList.remove('show');
    document.getElementById(rid).innerHTML = '';
}

function addToCart(id, q = 1) {
    const it = inventoryData[id];
    if (!it) return;
    const ei = cartItems.findIndex(i => i.id == id);
    if (ei !== -1) {
        const nq = cartItems[ei].quantity + q;
        if (nq > it.qty_physical_count) {
            alert('Max available: ' + it.qty_physical_count);
            return;
        }
        cartItems[ei].quantity = nq;
    } else {
        if (q > it.qty_physical_count) {
            alert('Max available: ' + it.qty_physical_count);
            return;
        }
        cartItems.push({
            id: it.id,
            name: it.article_name,
            property_no: it.property_no,
            uom: it.uom,
            available_qty: it.qty_physical_count,
            unit_value: it.unit_value,
            quantity: q
        });
    }
    updateCartDisplay();
}

function removeFromCart(id) {
    cartItems = cartItems.filter(i => i.id != id);
    updateCartDisplay();
}

function updateCartQuantity(id, nq) {
    const ei = cartItems.findIndex(i => i.id == id);
    if (ei === -1) return;
    const it = inventoryData[id];
    nq = parseInt(nq);
    if (isNaN(nq) || nq < 1) nq = 1;
    if (nq > it.qty_physical_count) nq = it.qty_physical_count;
    cartItems[ei].quantity = nq;
    updateCartDisplay();
}

function updateCartDisplay() {
    const c = document.getElementById('selectedItemsList');
    const em = document.getElementById('emptyCartMessage');
    const sc = document.getElementById('selectedCount');
    const cs = document.getElementById('cartSummary');
    
    if (cartItems.length === 0) {
        if (em) em.style.display = 'block';
        if (c) c.innerHTML = '';
        if (sc) sc.innerText = '0';
        if (cs) cs.style.display = 'none';
        return;
    }
    
    if (em) em.style.display = 'none';
    let h = '';
    let ti = 0;
    let tv = 0;
    
    cartItems.forEach(item => {
        const it = item.unit_value * item.quantity;
        ti += item.quantity;
        tv += it;
        h += `
            <div class="selected-item-card">
                <div class="selected-item-info">
                    <div class="item-name">${escapeHtml(item.name)}</div>
                    <div class="item-property">Property: ${escapeHtml(item.property_no || 'N/A')}</div>
                    <div class="item-property">${formatCurrency(item.unit_value)} each</div>
                </div>
                <div class="selected-item-qty">
                    <input type="number" value="${item.quantity}" min="1" max="${item.available_qty}" onchange="updateCartQuantity(${item.id}, this.value)">
                    <span>${escapeHtml(item.uom || 'pcs')}</span>
                    <span style="font-weight:bold">${formatCurrency(it)}</span>
                </div>
                <button class="btn-remove-item" onclick="removeFromCart(${item.id})"><i class="fas fa-trash"></i></button>
            </div>
        `;
    });
    
    if (c) c.innerHTML = h;
    if (sc) sc.innerText = cartItems.length;
    if (cs) {
        cs.style.display = 'block';
        cs.innerHTML = `<strong>Total Items: ${ti}</strong> | <strong>Total Value: ${formatCurrency(tv)}</strong>`;
    }
    updateFormInputs();
}

function updateFormInputs() {
    document.querySelectorAll('input[name="inventory_ids[]"]').forEach(e => e.remove());
    document.querySelectorAll('input[name="quantities[]"]').forEach(e => e.remove());
    
    const f = document.getElementById('issueForm');
    cartItems.forEach(i => {
        let ii = document.createElement('input');
        ii.type = 'hidden';
        ii.name = 'inventory_ids[]';
        ii.value = i.id;
        f.appendChild(ii);
        
        let qi = document.createElement('input');
        qi.type = 'hidden';
        qi.name = 'quantities[]';
        qi.value = i.quantity;
        f.appendChild(qi);
    });
}

function viewIssuanceDetails(id) {
    let m = document.getElementById('dynamic-modal');
    if (!m) {
        m = document.createElement('div');
        m.id = 'dynamic-modal';
        m.style.cssText = 'display:none;position:fixed;z-index:1000;left:0;top:0;width:100%;height:100%;background:rgba(0,0,0,.5)';
        m.innerHTML = `<div style="background:#fff;margin:10% auto;width:500px;max-width:90%;border-radius:12px"><div style="padding:20px;border-bottom:2px solid #FFD8E0;display:flex;justify-content:space-between"><h2>Issuance Details</h2><span style="font-size:28px;cursor:pointer" onclick="document.getElementById('dynamic-modal').style.display='none'">&times;</span></div><div style="padding:20px" id="modal-body">Loading...</div></div>`;
        document.body.appendChild(m);
    }
    m.style.display = 'block';
    document.getElementById('modal-body').innerHTML = '<div style="text-align:center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    fetch('<?php echo SITE_URL; ?>/api/get_issuance_details.php?id=' + id)
        .then(r => r.json())
        .then(d => {
            if (d.error) {
                document.getElementById('modal-body').innerHTML = '<div style="color:red">' + d.error + '</div>';
                return;
            }
            document.getElementById('modal-body').innerHTML = `
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
                    <div><strong>Item:</strong><br>${escapeHtml(d.article_name || 'N/A')}</div>
                    <div><strong>Property No.:</strong><br>${escapeHtml(d.property_no || 'N/A')}</div>
                    <div><strong>Issued To:</strong><br>${escapeHtml(d.issued_to_name || 'N/A')}</div>
                    <div><strong>Department:</strong><br>${escapeHtml(d.department_name || 'N/A')}</div>
                    <div><strong>Section:</strong><br>${escapeHtml(d.section_name || 'N/A')}</div>
                    <div><strong>Quantity:</strong><br>${d.quantity_issued} ${escapeHtml(d.uom || 'pcs')}</div>
                    <div><strong>Purpose:</strong><br>${escapeHtml(d.purpose || 'N/A')}</div>
                    <div><strong>Condition:</strong><br>${escapeHtml(d.condition_on_issue || 'N/A')}</div>
                    <div><strong>Issued By:</strong><br>${escapeHtml(d.issued_by_name || 'N/A')}</div>
                    <div><strong>Issued Date:</strong><br>${new Date(d.issued_date).toLocaleDateString()}</div>
                    <div><strong>Remarks:</strong><br>${escapeHtml(d.remarks || '—')}</div>
                    <div><strong>Return Date:</strong><br>${d.actual_return ? new Date(d.actual_return).toLocaleDateString() : '—'}</div>
                </div>
            `;
        })
        .catch(() => {
            document.getElementById('modal-body').innerHTML = '<div style="color:red">Error loading</div>';
        });
}

function processHardwareBarcode(barcode) {
    const st = barcode.toLowerCase().trim();
    let fi = null;
    for (let id in inventoryData) {
        const it = inventoryData[id];
        const be = (it.barcode_data || '').toLowerCase().trim();
        const bn = be.replace(/[\s\-\.]/g, '');
        const sn = st.replace(/[\s\-\.]/g, '');
        const al = (it.article_name || '').toLowerCase();
        const pl = (it.property_no || '').toLowerCase();
        if (bn === sn || (be && be === st) || (be && be.includes(st)) || al.includes(st) || pl.includes(st)) {
            fi = it;
            break;
        }
    }
    
    if (fi) {
        const ei = hardwareScannedItems.findIndex(i => i.id == fi.id);
        if (ei !== -1) {
            const c = hardwareScannedItems[ei];
            const m = parseFloat(fi.qty_physical_count || 0);
            if (c.quantity < m) {
                c.quantity++;
                showScanSuccess(fi.article_name, c.quantity);
            } else {
                showScanWarning(fi.article_name, m);
            }
        } else {
            const a = parseFloat(fi.qty_physical_count || 0);
            if (a <= 0) {
                showScanWarning(fi.article_name, 0);
                return;
            }
            hardwareScannedItems.push({
                id: fi.id,
                name: fi.article_name,
                property_no: fi.property_no,
                barcode_data: fi.barcode_data,
                uom: fi.uom || 'pcs',
                available_qty: a,
                unit_value: fi.unit_value || 0,
                quantity: 1
            });
            showScanSuccess(fi.article_name, 1);
        }
        updateHardwareScannedItemsDisplay();
    } else {
        showScanError(barcode);
    }
}

function showScanSuccess(n, q) {
    const i = document.getElementById('hardwareScannerInput');
    if (!i) return;
    i.style.backgroundColor = '#C8E6C9';
    i.style.borderColor = '#4CAF50';
    i.placeholder = '✓ Added: ' + n + ' (Qty: ' + q + ')';
    setTimeout(() => {
        i.style.backgroundColor = '';
        i.style.borderColor = '#2196F3';
        i.placeholder = 'Place cursor here and scan...';
    }, 1500);
}

function showScanWarning(n, m) {
    const i = document.getElementById('hardwareScannerInput');
    if (!i) return;
    i.style.backgroundColor = '#FFF3E0';
    i.style.borderColor = '#FF9800';
    i.placeholder = m > 0 ? '⚠ Max: ' + n + ' (' + m + ')' : '⚠ Out of stock: ' + n;
    setTimeout(() => {
        i.style.backgroundColor = '';
        i.style.borderColor = '#2196F3';
        i.placeholder = 'Place cursor here and scan...';
    }, 2000);
}

function showScanError(b) {
    const i = document.getElementById('hardwareScannerInput');
    if (!i) return;
    i.style.backgroundColor = '#FFCDD2';
    i.style.borderColor = '#f44336';
    i.placeholder = '✗ Not found: ' + b;
    setTimeout(() => {
        i.style.backgroundColor = '';
        i.style.borderColor = '#2196F3';
        i.placeholder = 'Place cursor here and scan...';
    }, 2000);
}

function updateHardwareScannedItemsDisplay() {
    const c = document.getElementById('hardwareScannedItemsContainer');
    const ab = document.getElementById('addToCartBtn');
    const cb = document.getElementById('clearScansBtn');
    if (!c) return;
    
    if (hardwareScannedItems.length === 0) {
        c.innerHTML = '<div class="empty-scan-state"><i class="fas fa-box"></i><p>No items scanned yet</p></div>';
        if (ab) ab.disabled = true;
        if (cb) cb.style.display = 'none';
    } else {
        let h = '';
        let ti = 0;
        let tv = 0;
        hardwareScannedItems.forEach((item, idx) => {
            const it = item.quantity * item.unit_value;
            ti += item.quantity;
            tv += it;
            h += `
                <div class="scanned-item-card success">
                    <div class="scanned-item-info">
                        <div class="scanned-item-name"><i class="fas fa-check-circle"></i> ${escapeHtml(item.name)}</div>
                        <div class="scanned-item-details">Property: ${escapeHtml(item.property_no || 'N/A')} | Stock: ${item.available_qty} ${escapeHtml(item.uom || 'pcs')} | Value: ${formatCurrency(item.unit_value)}</div>
                    </div>
                    <div class="scanned-item-qty-controls">
                        <button class="qty-btn" onclick="decreaseHardwareQty(${idx})">−</button>
                        <input type="number" class="qty-input" value="${item.quantity}" min="1" max="${item.available_qty}" onchange="updateHardwareQty(${idx},this.value)">
                        <button class="qty-btn" onclick="increaseHardwareQty(${idx})">+</button>
                        <button class="remove-scanned-item" onclick="removeHardwareScannedItem(${idx})"><i class="fas fa-trash"></i> Remove</button>
                    </div>
                </div>
            `;
        });
        h += `<div style="margin-top:15px;padding:12px;background:#F5F5F5;border-radius:8px;text-align:right;font-weight:bold">Items: ${hardwareScannedItems.length} | Total Qty: ${ti} | Total Value: ${formatCurrency(tv)}</div>`;
        c.innerHTML = h;
        if (ab) ab.disabled = false;
        if (cb) cb.style.display = 'inline-block';
    }
}

function increaseHardwareQty(idx) {
    if (idx < hardwareScannedItems.length) {
        const it = hardwareScannedItems[idx];
        if (it.quantity < it.available_qty) {
            it.quantity++;
            updateHardwareScannedItemsDisplay();
        } else {
            showScanWarning(it.name, it.available_qty);
        }
    }
}

function decreaseHardwareQty(idx) {
    if (idx < hardwareScannedItems.length && hardwareScannedItems[idx].quantity > 1) {
        hardwareScannedItems[idx].quantity--;
        updateHardwareScannedItemsDisplay();
    }
}

function updateHardwareQty(idx, v) {
    if (idx < hardwareScannedItems.length) {
        const it = hardwareScannedItems[idx];
        const nq = parseInt(v);
        it.quantity = isNaN(nq) || nq < 1 ? 1 : (nq > it.available_qty ? it.available_qty : nq);
        updateHardwareScannedItemsDisplay();
    }
}

function removeHardwareScannedItem(idx) {
    if (confirm('Remove "' + hardwareScannedItems[idx].name + '"?')) {
        hardwareScannedItems.splice(idx, 1);
        updateHardwareScannedItemsDisplay();
    }
}

function clearHardwareScans() {
    if (hardwareScannedItems.length > 0 && confirm('Clear all ' + hardwareScannedItems.length + ' item(s)?')) {
        hardwareScannedItems = [];
        updateHardwareScannedItemsDisplay();
    }
}

function addHardwareScannedToCart() {
    if (hardwareScannedItems.length === 0) {
        alert('No items to add');
        return;
    }
    
    let ac = 0, sk = 0;
    hardwareScannedItems.forEach(item => {
        const ec = cartItems.find(i => i.id == item.id);
        if (ec) {
            const nq = Math.min(ec.quantity + item.quantity, item.available_qty);
            if (nq !== ec.quantity) {
                ec.quantity = nq;
                ac++;
            } else {
                sk++;
            }
        } else {
            addToCart(item.id, item.quantity);
            if (cartItems.find(i => i.id == item.id)) ac++;
            else sk++;
        }
    });
    
    closeHardwareScannerModal();
    let msg = ac + ' item(s) added to cart.';
    if (sk > 0) msg += '\n' + sk + ' item(s) skipped (max stock reached).';
    alert(msg);
    
    const sb = document.getElementById('submitBtn');
    if (sb) {
        sb.scrollIntoView({ behavior: 'smooth', block: 'center' });
        sb.style.boxShadow = '0 0 20px rgba(248,176,192,.8)';
        setTimeout(() => sb.style.boxShadow = '', 2000);
    }
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('barcode_input')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            searchBarcode();
        }
    });
    
    document.getElementById('modal_barcode_input')?.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            performBarcodeSearch(this.value.trim(), 'modal_barcode_input', 'modal_barcode_result');
        }
    });
    
    const hsi = document.getElementById('hardwareScannerInput');
    if (hsi) {
        hsi.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                const b = this.value.trim();
                if (b) {
                    processHardwareBarcode(b);
                    this.value = '';
                    this.focus();
                }
            }
        });
        hsi.addEventListener('paste', function() {
            setTimeout(() => {
                const v = this.value.trim();
                if (v && v.length > 2) {
                    const cb = v.replace(/\n/g, '').trim();
                    if (cb !== v) {
                        processHardwareBarcode(cb);
                        this.value = '';
                        this.focus();
                    }
                }
            }, 50);
        });
    }
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const hm = document.getElementById('hardwareScannerModal');
            if (hm && hm.classList.contains('show')) closeHardwareScannerModal();
            const sm = document.getElementById('scannerModal');
            if (sm && sm.classList.contains('show')) closeScannerModal();
        }
    });
    
    const hm = document.getElementById('hardwareScannerModal');
    if (hm) {
        hm.addEventListener('click', function(e) {
            if (e.target === hm) closeHardwareScannerModal();
        });
    }
    
    document.getElementById('barcode_input')?.focus();
    resetSubmitForm();
    window.searchEmployees = searchEmployees;
});
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>