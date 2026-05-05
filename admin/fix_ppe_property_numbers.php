<?php
// fix_ppe_property_numbers.php
ob_start();

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
    header('Location: ' . SITE_URL . '/login.php');
    exit;
}

// Check if user has admin or super_admin role
if (!in_array($_SESSION['user_role'], ['admin', 'super_admin'])) {
    die('Access denied. Admin privileges required.');
}

$page_title = 'Fix PPE Property Numbers';
include INCLUDE_PATH . '/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-wrench"></i> Fix PPE Property Numbers</h2>
        <p>Convert old property number format to new structured format</p>
    </div>
    
    <?php
    $action = $_GET['action'] ?? '';
    $fix_type = $_GET['fix_type'] ?? '';
    $message = '';
    $message_type = 'success';
    
    if ($action == 'fix') {
        
        if ($fix_type == 'all') {
            // Fix all PPE items
            $updated = 0;
            $skipped = 0;
            $errors = [];
            
            // Get all PPE items that don't have structured property numbers
            $result = $conn->query("
                SELECT id, article_name, property_no, type_equipment_id, equipment_sub_type_id, date_acquired, date_added, serial_number
                FROM inventory 
                WHERE category = 'PPE' OR type_equipment = 'PPE'
                ORDER BY id
            ");
            
            if ($result && $result->num_rows > 0) {
                echo "<div class='alert alert-info'>Processing " . $result->num_rows . " items...</div>";
                echo "<div style='max-height: 400px; overflow-y: auto; margin-top: 15px;'>";
                
                while ($item = $result->fetch_assoc()) {
                    // Check if already has structured format (YYYY-XX-XXXX-XXX or YYYY-XX-XXXX-XXX-DDD)
                    if (preg_match('/^\d{4}-\d{2}-\d{4}-\d{3}(?:-\d{3})?$/', $item['property_no'])) {
                        $skipped++;
                        echo "<div style='color: #666; padding: 5px; border-bottom: 1px solid #eee; font-size: 12px;'>
                                ⏭️ Skipped: {$item['article_name']} (already correct format: {$item['property_no']})
                              </div>";
                        continue;
                    }
                    
                    // Generate new structured property number
                    $year = !empty($item['date_acquired']) && $item['date_acquired'] != '0000-00-00' 
                        ? date('Y', strtotime($item['date_acquired'])) 
                        : date('Y', strtotime($item['date_added']));
                    
                    // Get equipment type code
                    $type_code = '00';
                    if ($item['type_equipment_id']) {
                        $type_stmt = $conn->prepare("SELECT code FROM type_of_equipment WHERE id = ?");
                        $type_stmt->bind_param("i", $item['type_equipment_id']);
                        $type_stmt->execute();
                        $type_result = $type_stmt->get_result();
                        if ($type_row = $type_result->fetch_assoc()) {
                            $type_code = str_pad($type_row['code'], 2, '0', STR_PAD_LEFT);
                        }
                        $type_stmt->close();
                    }
                    
                    // Get equipment sub-type code
                    $sub_type_code = '0000';
                    if ($item['equipment_sub_type_id']) {
                        $sub_stmt = $conn->prepare("SELECT code FROM equipment_sub_type WHERE id = ?");
                        $sub_stmt->bind_param("i", $item['equipment_sub_type_id']);
                        $sub_stmt->execute();
                        $sub_result = $sub_stmt->get_result();
                        if ($sub_row = $sub_result->fetch_assoc()) {
                            $sub_type_code = str_pad($sub_row['code'], 4, '0', STR_PAD_LEFT);
                        }
                        $sub_stmt->close();
                    }
                    
                    // Use serial number or ID
                    $serial = !empty($item['serial_number']) 
                        ? str_pad($item['serial_number'], 3, '0', STR_PAD_LEFT)
                        : str_pad($item['id'], 3, '0', STR_PAD_LEFT);
                    
                    $new_property_no = "{$year}-{$type_code}-{$sub_type_code}-{$serial}";
                    
                    // Generate barcode from property number
                    $new_barcode = 'PPE-' . $new_property_no;
                    
                    $update = $conn->prepare("
                        UPDATE inventory 
                        SET property_no = ?, barcode_data = ? 
                        WHERE id = ?
                    ");
                    $update->bind_param("ssi", $new_property_no, $new_barcode, $item['id']);
                    
                    if ($update->execute()) {
                        $updated++;
                        echo "<div style='background: #d4edda; color: #155724; padding: 8px; margin: 5px 0; border-radius: 4px; font-size: 12px;'>
                                <strong>✓ Fixed:</strong> {$item['article_name']}<br>
                                &nbsp;&nbsp;Old: {$item['property_no']}<br>
                                &nbsp;&nbsp;New: {$new_property_no}<br>
                                &nbsp;&nbsp;Barcode: {$new_barcode}
                              </div>";
                    } else {
                        $errors[] = "Failed to update ID {$item['id']}: " . $conn->error;
                        echo "<div style='background: #f8d7da; color: #721c24; padding: 8px; margin: 5px 0; border-radius: 4px; font-size: 12px;'>
                                <strong>✗ Error:</strong> {$item['article_name']} - {$conn->error}
                              </div>";
                    }
                    $update->close();
                    
                    // Flush output to show progress
                    ob_flush();
                    flush();
                }
                
                echo "</div>";
            }
            
            $message = "Fixed $updated items. Skipped $skipped items (already in correct format).";
            if (!empty($errors)) {
                $message .= "<br>Errors: " . implode("<br>", $errors);
                $message_type = 'danger';
            }
            
        } elseif ($fix_type == 'single' && isset($_GET['id'])) {
            // Fix a single item
            $id = (int)$_GET['id'];
            
            $stmt = $conn->prepare("
                SELECT id, article_name, property_no, type_equipment_id, equipment_sub_type_id, date_acquired, date_added, serial_number
                FROM inventory 
                WHERE id = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $item = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            
            if ($item) {
                $year = !empty($item['date_acquired']) && $item['date_acquired'] != '0000-00-00' 
                    ? date('Y', strtotime($item['date_acquired'])) 
                    : date('Y', strtotime($item['date_added']));
                
                // Get equipment type code
                $type_code = '00';
                if ($item['type_equipment_id']) {
                    $type_stmt = $conn->prepare("SELECT code FROM type_of_equipment WHERE id = ?");
                    $type_stmt->bind_param("i", $item['type_equipment_id']);
                    $type_stmt->execute();
                    $type_result = $type_stmt->get_result();
                    if ($type_row = $type_result->fetch_assoc()) {
                        $type_code = str_pad($type_row['code'], 2, '0', STR_PAD_LEFT);
                    }
                    $type_stmt->close();
                }
                
                // Get equipment sub-type code
                $sub_type_code = '0000';
                if ($item['equipment_sub_type_id']) {
                    $sub_stmt = $conn->prepare("SELECT code FROM equipment_sub_type WHERE id = ?");
                    $sub_stmt->bind_param("i", $item['equipment_sub_type_id']);
                    $sub_stmt->execute();
                    $sub_result = $sub_stmt->get_result();
                    if ($sub_row = $sub_result->fetch_assoc()) {
                        $sub_type_code = str_pad($sub_row['code'], 4, '0', STR_PAD_LEFT);
                    }
                    $sub_stmt->close();
                }
                
                $serial = !empty($item['serial_number']) 
                    ? str_pad($item['serial_number'], 3, '0', STR_PAD_LEFT)
                    : str_pad($item['id'], 3, '0', STR_PAD_LEFT);
                
                $new_property_no = "{$year}-{$type_code}-{$sub_type_code}-{$serial}";
                $new_barcode = 'PPE-' . $new_property_no;
                
                $update = $conn->prepare("
                    UPDATE inventory 
                    SET property_no = ?, barcode_data = ? 
                    WHERE id = ?
                ");
                $update->bind_param("ssi", $new_property_no, $new_barcode, $id);
                
                if ($update->execute()) {
                    $message = "✓ Fixed item: {$item['article_name']}<br>
                                Old property: <code>{$item['property_no']}</code><br>
                                New property: <code>{$new_property_no}</code><br>
                                New barcode: <code>{$new_barcode}</code>";
                } else {
                    $message = "Error: " . $conn->error;
                    $message_type = 'danger';
                }
                $update->close();
            } else {
                $message = "Item not found!";
                $message_type = 'danger';
            }
        }
    }
    
    // Get counts for display - FIXED: Use proper REGEXP for MySQL
    $counts = [];
    $result = $conn->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN property_no REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{4}-[0-9]{3}' THEN 1 ELSE 0 END) as structured,
            SUM(CASE WHEN property_no NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{4}-[0-9]{3}' THEN 1 ELSE 0 END) as old_format,
            SUM(CASE WHEN barcode_data IS NULL OR barcode_data = '' THEN 1 ELSE 0 END) as no_barcode
        FROM inventory 
        WHERE category = 'PPE' OR type_equipment = 'PPE'
    ");
    if ($result) {
        $counts = $result->fetch_assoc();
    } else {
        $counts = ['total' => 0, 'structured' => 0, 'old_format' => 0, 'no_barcode' => 0];
    }
    ?>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo $message; ?>
        </div>
    <?php endif; ?>

    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <div class="card" style="background: var(--white); padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h4 style="margin: 0 0 10px 0; color: var(--text-secondary);">Total PPE Items</h4>
            <div style="font-size: 32px; font-weight: bold; color: var(--primary);"><?php echo $counts['total']; ?></div>
        </div>
        <div class="card" style="background: var(--white); padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h4 style="margin: 0 0 10px 0; color: var(--text-secondary);">Correct Format</h4>
            <div style="font-size: 32px; font-weight: bold; color: #4CAF50;"><?php echo $counts['structured']; ?></div>
            <small>YYYY-XX-XXXX-XXX</small>
        </div>
        <div class="card" style="background: var(--white); padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h4 style="margin: 0 0 10px 0; color: var(--text-secondary);">Old Format (Needs Fix)</h4>
            <div style="font-size: 32px; font-weight: bold; color: #f44336;"><?php echo $counts['old_format']; ?></div>
        </div>
        <div class="card" style="background: var(--white); padding: 15px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <h4 style="margin: 0 0 10px 0; color: var(--text-secondary);">Missing Barcodes</h4>
            <div style="font-size: 32px; font-weight: bold; color: #FF9800;"><?php echo $counts['no_barcode']; ?></div>
        </div>
    </div>

    <?php if ($counts['old_format'] > 0): ?>
    <div class="alert alert-warning" style="background: #FFF3E0; border-left: 4px solid #FF9800; padding: 15px; margin-bottom: 20px;">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>Warning:</strong> Found <?php echo $counts['old_format']; ?> items with old property number format.
        Running the fix will convert them to the new structured format.
    </div>
    <?php endif; ?>

    <div class="form-section" style="margin-bottom: 20px; background: var(--white); padding: 20px; border-radius: 8px;">
        <h3><i class="fas fa-tools"></i> Fix Options</h3>
        
        <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top: 15px;">
            <a href="?action=fix&fix_type=all" class="btn btn-primary" style="background: var(--primary); color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;" 
               onclick="return confirm('⚠️ WARNING: This will fix ALL PPE items. This action cannot be undone! Continue?')">
                <i class="fas fa-globe"></i> Fix All PPE Items
            </a>
            
            <div>
                <form method="GET" action="" style="display: inline-flex; gap: 10px; align-items: center;">
                    <input type="hidden" name="action" value="fix">
                    <input type="hidden" name="fix_type" value="single">
                    <input type="number" name="id" placeholder="Enter Item ID" required 
                           style="padding: 8px 12px; border: 1px solid var(--border-light); border-radius: 6px;">
                    <button type="submit" class="btn btn-secondary" style="background: var(--secondary); color: white; padding: 8px 16px; border-radius: 6px; border: none; cursor: pointer;">
                        <i class="fas fa-single"></i> Fix Single Item
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php if ($counts['old_format'] > 0): ?>
    <div class="form-section" style="background: var(--white); padding: 20px; border-radius: 8px;">
        <h3><i class="fas fa-list"></i> Items Needing Fix (Old Format) - First 50 items</h3>
        <div style="overflow-x: auto; max-height: 500px; margin-top: 15px;">
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: var(--light);">
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid var(--border-light);">ID</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid var(--border-light);">Article Name</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid var(--border-light);">Current Property No</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid var(--border-light);">Barcode</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid var(--border-light);">Type Info</th>
                        <th style="padding: 10px; text-align: left; border-bottom: 2px solid var(--border-light);">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $result = $conn->query("
                        SELECT i.id, i.article_name, i.property_no, i.barcode_data, 
                               i.type_equipment_id, i.equipment_sub_type_id,
                               toe.name as type_name, est.name as sub_type_name
                        FROM inventory i
                        LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
                        LEFT JOIN equipment_sub_type est ON i.equipment_sub_type_id = est.id
                        WHERE (i.category = 'PPE' OR i.type_equipment = 'PPE')
                        AND i.property_no NOT REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{4}-[0-9]{3}'
                        ORDER BY i.id
                        LIMIT 50
                    ");
                    
                    if ($result && $result->num_rows > 0):
                        while ($item = $result->fetch_assoc()):
                    ?>
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-light);"><?php echo $item['id']; ?></td>
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-light);"><?php echo htmlspecialchars($item['article_name']); ?></td>
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-light); font-family: monospace;"><?php echo htmlspecialchars($item['property_no']); ?></td>
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-light); font-family: monospace;">
                            <?php echo !empty($item['barcode_data']) ? htmlspecialchars($item['barcode_data']) : '<span style="color: #f44336;">Missing</span>'; ?>
                        </td>
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-light); font-size: 12px;">
                            Type: <?php echo $item['type_name'] ?: '<span style="color: #FF9800;">Not set</span>'; ?><br>
                            Sub-Type: <?php echo $item['sub_type_name'] ?: '<span style="color: #FF9800;">Not set</span>'; ?>
                        </td>
                        <td style="padding: 8px; border-bottom: 1px solid var(--border-light);">
                            <a href="?action=fix&fix_type=single&id=<?php echo $item['id']; ?>" 
                               class="btn btn-xs" style="background: var(--primary); color: white; padding: 4px 8px; border-radius: 4px; text-decoration: none; font-size: 11px;"
                               onclick="return confirm('Fix this item?')">
                                <i class="fas fa-wrench"></i> Fix
                            </a>
                        </td>
                    </tr>
                    <?php 
                        endwhile;
                    endif;
                    ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="form-section" style="background: var(--white); padding: 20px; border-radius: 8px; margin-top: 20px;">
        <h3><i class="fas fa-info-circle"></i> Property Number Format</h3>
        <p>The correct format for PPE items is:</p>
        <code style="display: block; background: var(--light); padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace;">
            YYYY - TT - ST - SN - DDD (optional, added on issuance)
        </code>
        <ul style="margin-top: 10px;">
            <li><strong>YYYY</strong> = Year of acquisition (from date_acquired or date_added)</li>
            <li><strong>TT</strong> = Type of Equipment code (2 digits from type_of_equipment table)</li>
            <li><strong>ST</strong> = Equipment Sub-Type code (4 digits from equipment_sub_type table)</li>
            <li><strong>SN</strong> = Serial Number (3 digits, auto-generated from ID or custom)</li>
            <li><strong>DDD</strong> = Department Code (3 digits, added when item is issued)</li>
        </ul>
        <p><strong>Example:</strong> <code>2026-04-0401-001</code> = Year 2026, Type 04 (Machinery), Sub-Type 0401 (Office Equipment), Serial 001</p>
        <p><strong>With department:</strong> <code>2026-04-0401-001-002</code> = Issued to department with code 002</p>
    </div>
</div>

<style>
.btn-xs:hover {
    opacity: 0.8;
}
.alert {
    padding: 12px 15px;
    border-radius: 6px;
    margin-bottom: 15px;
}
.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}
.alert-danger {
    background: #f8d7da;
    color: #721c24;
    border-left: 4px solid #dc3545;
}
.alert-warning {
    background: #fff3cd;
    color: #856404;
    border-left: 4px solid #ffc107;
}
.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border-left: 4px solid #17a2b8;
}
code {
    background: #f4f4f4;
    padding: 2px 6px;
    border-radius: 4px;
    font-family: monospace;
}
</style>

<?php
include INCLUDE_PATH . '/footer.php';
ob_end_flush();
?>