<?php
/**
 * Add Inventory Page (Admin)
 * Allows admin to add new inventory items with barcode generation
 * Auto-sequences barcodes when quantity > 1
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Require admin role
requireRole('admin' || 'superadmin');

// Include the barcode library
require_once $root_path . '/vendor/autoload.php';
use Picqer\Barcode\BarcodeGeneratorPNG;
use Picqer\Barcode\BarcodeGeneratorHTML;

$page_title = 'Add Inventory';
$page_description = 'Add new items to inventory';

// Get Type of Equipment for dropdown (from type_of_equipment table)
$type_of_equipment = $conn->query("SELECT * FROM type_of_equipment ORDER BY code, name");

// Get Equipment Sub-Type for dropdown (from equipment_sub_type table)
$equipment_sub_type = $conn->query("SELECT * FROM equipment_sub_type ORDER BY code, name");

// Get users for dropdown (for approved_by, verified_by)
$users = $conn->query("SELECT id, username, firstname, lastname FROM users WHERE status = 'active' ORDER BY firstname, lastname");

// Handle barcode generation via AJAX
if (isset($_GET['generate_barcode'])) {
    header('Content-Type: application/json');
    
    $barcode_value = $_GET['barcode_value'] ?? '';
    $barcode_type = $_GET['barcode_type'] ?? 'single';
    
    if (empty($barcode_value)) {
        echo json_encode(['error' => 'Please provide barcode value']);
        exit;
    }
    
    try {
        $generator = new BarcodeGeneratorPNG();
        $barcode = base64_encode($generator->getBarcode($barcode_value, $generator::TYPE_CODE_128));
        
        echo json_encode([
            'success' => true,
            'barcode' => $barcode,
            'value' => $barcode_value
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Handle multiple barcode generation preview
if (isset($_GET['generate_multiple_preview'])) {
    header('Content-Type: application/json');
    
    $prefix = $_GET['prefix'] ?? 'INV';
    $quantity = intval($_GET['quantity'] ?? 5);
    $baseBarcode = $prefix . date('Ymd');
    
    try {
        $generator = new BarcodeGeneratorPNG();
        $barcodes = [];
        
        for ($i = 0; $i < min($quantity, 10); $i++) {
            $randomPart = rand(1000, 9999);
            $barcodeValue = $baseBarcode . $randomPart . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
            $barcode = base64_encode($generator->getBarcode($barcodeValue, $generator::TYPE_CODE_128));
            
            $barcodes[] = [
                'value' => $barcodeValue,
                'barcode' => $barcode,
                'index' => $i + 1
            ];
        }
        
        echo json_encode([
            'success' => true,
            'barcodes' => $barcodes,
            'total' => $quantity
        ]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $article_name = sanitize($_POST['article_name']);
    $description = sanitize($_POST['description']);
    $property_no = generatePropertyNo();
    $uom = sanitize($_POST['uom']);
    $quantity = floatval($_POST['quantity']);
    $unit_value = floatval($_POST['unit_value']);
    $equipment_id = !empty($_POST['equipment_id']) ? (int)$_POST['equipment_id'] : null;
    $category = sanitize($_POST['category']);
    $type_equipment = sanitize($_POST['type_equipment']);
    $condition_text = sanitize($_POST['condition_text']);
    $fund_cluster = sanitize($_POST['fund_cluster']);
    
    // Handle multi-select for certified_correct
    $certified_correct_array = isset($_POST['certified_correct']) && is_array($_POST['certified_correct']) 
        ? array_filter(array_map('intval', $_POST['certified_correct'])) 
        : [];
    $certified_correct = !empty($certified_correct_array) ? json_encode($certified_correct_array) : null;
    
    // Handle multi-select for approved_by
    $approved_by_array = isset($_POST['approved_by']) && is_array($_POST['approved_by']) 
        ? array_filter(array_map('intval', $_POST['approved_by'])) 
        : [];
    $approved_by = !empty($approved_by_array) ? json_encode($approved_by_array) : null;
    
    // Handle multi-select for verified_by
    $verified_by_array = isset($_POST['verified_by']) && is_array($_POST['verified_by']) 
        ? array_filter(array_map('intval', $_POST['verified_by'])) 
        : [];
    $verified_by = !empty($verified_by_array) ? json_encode($verified_by_array) : null;
    
    $remarks = sanitize($_POST['remarks']);
    
    // Auto-detect if we should use multiple barcodes based on quantity
    $auto_multiple = ($quantity > 1 && floor($quantity) == $quantity);
    $barcode_option = $auto_multiple ? 'multiple' : ($_POST['barcode_option'] ?? 'single');
    $barcode_prefix = sanitize($_POST['barcode_prefix'] ?? 'INV');
    $barcode_quantity = $auto_multiple ? intval($quantity) : intval($_POST['barcode_quantity'] ?? 1);
    
    // Validate required fields
    $errors = [];
    if (empty($article_name)) $errors[] = "Article name is required";
    if (empty($uom)) $errors[] = "Unit of measurement is required";
    if ($quantity <= 0) $errors[] = "Quantity must be greater than 0";
    if ($unit_value <= 0) $errors[] = "Unit value must be greater than 0";
    
    // Validate barcode fields
    if (!$auto_multiple) {
        if ($barcode_option == 'single' && empty($_POST['generated_barcode']) && empty($_POST['manual_barcode'])) {
            $errors[] = "Please generate a barcode first or enter manually";
        }
        
        if ($barcode_option == 'multiple' && $barcode_quantity < 1) {
            $errors[] = "Number of barcodes must be at least 1";
        }
    }
    
    if (empty($errors)) {
        // Begin transaction
        $conn->begin_transaction();
        
        try {
            $created_by = $_SESSION['user_id'];
            $success_count = 0;
            $inventory_ids = [];
            $barcodes_created = [];
            
            if (($barcode_option == 'single' && !$auto_multiple) || ($quantity == 1)) {
                // Single item with single barcode
                $barcode_data = !empty($_POST['generated_barcode']) ? $_POST['generated_barcode'] : $_POST['manual_barcode'];
                $barcode_data = sanitize($barcode_data);
                
                // Check if barcode already exists
                $check = $conn->query("SELECT id FROM inventory WHERE barcode_data = '$barcode_data'");
                if ($check && $check->num_rows > 0) {
                    throw new Exception("Barcode '$barcode_data' already exists in the system");
                }
                
                $stmt = $conn->prepare("
                    INSERT INTO inventory (
                        article_name, description, property_no, uom, 
                        qty_property_card, qty_physical_count, unit_value,
                        equipment_id, category, type_equipment, condition_text,
                        fund_cluster, certified_correct, approved_by, verified_by,
                        remarks, created_by, date_added, date_updated,
                        barcode_data
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
                ");
                
                $qty_property_card = $quantity;
                $qty_physical_count = $quantity;
                
                $stmt->bind_param(
                    "sssdddiiisssssiiss",
                    $article_name, $description, $property_no, $uom,
                    $qty_property_card, $qty_physical_count, $unit_value,
                    $equipment_id, $category, $type_equipment, $condition_text,
                    $fund_cluster, $certified_correct, $approved_by, $verified_by,
                    $remarks, $created_by, $barcode_data
                );
                
                if (!$stmt->execute()) {
                    throw new Exception($conn->error);
                }
                
                $inventory_id = $stmt->insert_id;
                $inventory_ids[] = $inventory_id;
                $barcodes_created[] = $barcode_data;
                $stmt->close();
                
            } else {
                // Multiple items with sequential barcodes
                $base_barcode = generateBarcodeNumber($barcode_prefix);
                $success_count = 0;
                
                for ($i = 0; $i < $barcode_quantity; $i++) {
                    $randomPart = rand(1000, 9999);
                    $barcode_data = $base_barcode . $randomPart . str_pad($i + 1, 3, '0', STR_PAD_LEFT);
                    
                    // Check if barcode already exists
                    $check = $conn->query("SELECT id FROM inventory WHERE barcode_data = '$barcode_data'");
                    if ($check && $check->num_rows > 0) {
                        throw new Exception("Barcode '$barcode_data' already exists in the system");
                    }
                    
                    // For multiple items, create separate entries with quantity 1 each
                    $stmt = $conn->prepare("
                        INSERT INTO inventory (
                            article_name, description, property_no, uom, 
                            qty_property_card, qty_physical_count, unit_value,
                            equipment_id, category, type_equipment, condition_text,
                            fund_cluster, certified_correct, approved_by, verified_by,
                            remarks, created_by, date_added, date_updated,
                            barcode_data
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), ?)
                    ");
                    
                    $qty_per_item = 1;
                    $total_value = $unit_value;
                    $property_no_with_suffix = $property_no . '-' . ($i + 1);
                    
                    $stmt->bind_param(
                        "sssdddiiisssssiiss",
                        $article_name, $description, $property_no_with_suffix, $uom,
                        $qty_per_item, $qty_per_item, $total_value,
                        $equipment_id, $category, $type_equipment, $condition_text,
                        $fund_cluster, $certified_correct, $approved_by, $verified_by,
                        $remarks, $created_by, $barcode_data
                    );
                    
                    if (!$stmt->execute()) {
                        throw new Exception($conn->error);
                    }
                    
                    $inventory_id = $stmt->insert_id;
                    $inventory_ids[] = $inventory_id;
                    $barcodes_created[] = $barcode_data;
                    $success_count++;
                    $stmt->close();
                }
            }
            
            // Commit transaction
            $conn->commit();
            
            // ============================================
            // AUDIT TRAIL LOGGING
            // ============================================
            
            // Prepare inventory data for audit
            $inventory_data = [
                'article_name' => $article_name,
                'description' => $description,
                'property_no' => $property_no,
                'uom' => $uom,
                'quantity' => $quantity,
                'unit_value' => $unit_value,
                'total_value' => $quantity * $unit_value,
                'category' => $category,
                'type_equipment' => $type_equipment,
                'condition' => $condition_text,
                'fund_cluster' => $fund_cluster,
                'equipment_id' => $equipment_id,
                'barcode_option' => $barcode_option,
                'barcode_count' => count($barcodes_created),
                'barcodes' => $barcodes_created
            ];
            
            // Log to audit trail using the existing function
            if (function_exists('logDetailedActivity')) {
                $item_message = ($barcode_option == 'multiple' || $auto_multiple) ? "$barcode_quantity items created" : "single item created";
                $description = "Added new inventory item(s): $article_name - $item_message";
                
                logDetailedActivity(
                    'INSERT',
                    $_SESSION['user_id'],
                    $description,
                    'inventory',
                    $inventory_ids[0] ?? 0,
                    null,
                    $inventory_data,
                    ['barcodes_generated' => count($barcodes_created), 'total_quantity' => $quantity]
                );
            }
            
            // Also log using the old function for compatibility
            if (function_exists('logActivity')) {
                $item_message = ($barcode_option == 'multiple' || $auto_multiple) ? "$barcode_quantity items created" : "single item created";
                logActivity('Add Inventory', $inventory_ids[0] ?? 0, "Added new item(s): $article_name with $item_message");
            }
            
            // Set success message
            $success_message = "Inventory item(s) added successfully. ";
            if ($barcode_option == 'multiple' || $auto_multiple) {
                $success_message .= "$barcode_quantity items created with sequential barcodes.";
            } else {
                $success_message .= "Property No: $property_no";
            }
            $_SESSION['success'] = $success_message;
            
            header('Location: ' . SITE_URL . '/admin/dashboard.php');
            exit();
            
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Error: " . $e->getMessage();
            
            // Log error to audit trail
            if (function_exists('logDetailedActivity')) {
                logDetailedActivity(
                    'ERROR',
                    $_SESSION['user_id'],
                    "Failed to add inventory item: " . $e->getMessage(),
                    'inventory',
                    null,
                    null,
                    null,
                    ['article_name' => $article_name, 'quantity' => $quantity, 'error' => $e->getMessage()]
                );
            }
        }
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode("<br>", $errors);
    }
}

// Function to generate barcode number
function generateBarcodeNumber($prefix = 'INV') {
    return $prefix . date('Ymd');
}

include INCLUDE_PATH . '/header.php';
?>

<!-- Your existing CSS (unchanged) -->
<style>
/* Your existing CSS remains exactly the same - no changes */
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
    --warning: #FF9800;
    --info: #2196F3;
}

body {
    background-color: var(--light);
    color: var(--text-primary);
}

/* Form container */
.form-container {
    max-width: 2000px;
    margin: 0 auto;
    padding: 20px;
}

.form-container h2 {
    color: var(--primary);
    margin-bottom: 30px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-light);
    font-size: 24px;
}

.form-container h2 i {
    color: var(--accent);
    margin-right: 10px;
}

/* Form section styles */
.form-section {
    background: var(--white);
    padding: 25px;
    margin-bottom: 25px;
    border-radius: 12px;
    border-left: 4px solid var(--primary);
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
    transition: transform 0.2s, box-shadow 0.2s;
}

.form-section:hover {
    box-shadow: 0 6px 16px rgba(107, 140, 255, 0.15);
}

.form-section h3 {
    color: var(--primary);
    margin-bottom: 20px;
    font-size: 18px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--accent-light);
}

.form-section h3 i {
    color: var(--accent);
    margin-right: 10px;
}

/* Form groups */
.form-group {
    margin-bottom: 15px;
}

.form-group label {
    display: block;
    margin-bottom: 5px;
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
    background-color: var(--white);
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.form-control[readonly] {
    background-color: var(--light);
    color: var(--text-secondary);
    cursor: not-allowed;
    opacity: 0.7;
}

select.form-control {
    cursor: pointer;
}

textarea.form-control {
    resize: vertical;
    min-height: 80px;
}

/* Form rows */
.form-row {
    display: flex;
    gap: 20px;
    margin-bottom: 15px;
}

.form-row .form-group {
    flex: 1;
}

/* Form text */
.form-text {
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

.form-text.text-muted {
    color: var(--text-muted) !important;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.btn i {
    font-size: 16px;
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

/* Barcode section */
.barcode-section {
    background: linear-gradient(to right, var(--white), var(--accent-light));
    border-left: 4px solid var(--accent);
}

.barcode-options {
    background: var(--white);
    padding: 20px;
    border-radius: 10px;
    margin-top: 15px;
    border: 1px solid var(--border-light);
}

/* Radio group */
.radio-group {
    display: flex;
    gap: 25px;
    margin-top: 8px;
}

.radio-inline {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    color: var(--text-primary);
}

.radio-inline input[type="radio"] {
    accent-color: var(--accent);
    width: 16px;
    height: 16px;
    margin: 0;
}

/* Barcode display */
.barcode-display {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.barcode-image {
    text-align: center;
    padding: 15px;
    background: var(--white);
    border: 2px dashed var(--secondary);
    border-radius: 10px;
    min-height: 100px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: border-color 0.2s;
}

.barcode-image:hover {
    border-color: var(--primary);
}

.barcode-image img {
    max-width: 100%;
    height: auto;
}

/* Barcode preview grid */
.barcode-preview-grid {
    margin-top: 20px;
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
    max-height: 500px;
    overflow-y: auto;
    padding: 20px;
    background: var(--light);
    border-radius: 12px;
    border: 1px solid var(--border-light);
}

.barcode-preview-card {
    background: var(--white);
    border: 1px solid var(--border-light);
    border-radius: 10px;
    padding: 15px;
    text-align: center;
    transition: all 0.2s;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.barcode-preview-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(107, 140, 255, 0.15);
    border-color: var(--primary);
}

.barcode-preview-card .item-number {
    font-size: 14px;
    font-weight: bold;
    color: var(--primary);
    margin-bottom: 10px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--accent-light);
}

.barcode-preview-card .barcode-img {
    margin: 12px 0;
    padding: 10px;
    background: var(--white);
}

.barcode-preview-card .barcode-img img {
    max-width: 100%;
    height: auto;
}

.barcode-preview-card .barcode-value {
    font-family: monospace;
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 8px;
    padding: 6px;
    background: var(--light);
    border-radius: 6px;
}

/* Alert styles */
.alert {
    padding: 15px 20px;
    border-radius: 10px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border: none;
}

.alert i {
    font-size: 18px;
}

.alert-danger {
    background-color: #ffebee;
    color: var(--danger);
    border-left: 4px solid var(--danger);
}

.alert-info {
    background-color: var(--accent-light);
    color: var(--primary);
    border-left: 4px solid var(--accent);
}

.alert-success {
    background-color: var(--success-light);
    color: var(--success);
    border-left: 4px solid var(--success);
}

/* Loading indicator */
.loading {
    text-align: center;
    padding: 40px;
    color: var(--text-secondary);
}

.loading i {
    font-size: 32px;
    margin-bottom: 15px;
    color: var(--primary);
}

/* Text utilities */
.text-danger {
    color: var(--danger);
}

/* Submit section */
.form-group:last-child {
    margin-top: 30px;
    padding-top: 20px;
    display: flex;
    gap: 15px;
}

/* Responsive design */
@media (max-width: 768px) {
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .radio-group {
        flex-direction: column;
        gap: 10px;
    }
    
    .barcode-preview-grid {
        grid-template-columns: 1fr;
    }
    
    .form-group:last-child {
        flex-direction: column;
    }
    
    .btn {
        width: 100%;
    }
}
</style>

<div class="form-container">
    <h2><i class="fas fa-plus-circle"></i> Add New Inventory Item</h2>

    <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php 
        echo $_SESSION['error'];
        unset($_SESSION['error']);
        ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="" id="inventoryForm">
        
        <!-- Basic Information -->
        <div class="form-section">
            <h3><i class="fas fa-info-circle"></i> Basic Information</h3>
            
            <div class="form-group">
                <label for="article_name">Article Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="article_name" name="article_name" 
                value="<?php echo isset($_POST['article_name']) ? htmlspecialchars($_POST['article_name']) : ''; ?>" 
                required maxlength="255" placeholder="Enter article name">
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea class="form-control" id="description" name="description" rows="3" 
                placeholder="Enter detailed description"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
            </div>
        </div>
        
        <!-- Classification -->
        <div class="form-section">
            <h3><i class="fas fa-tags"></i> Classification</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="category">Category</label>
                    <select class="form-control" id="category" name="category">
                        <option value="">-- Select Category --</option>
                        <option value="ICT Equipment" <?php echo (isset($_POST['category']) && $_POST['category'] == 'ICT Equipment') ? 'selected' : ''; ?>>ICT Equipment</option>
                        <option value="Medical Equipment" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Medical Equipment') ? 'selected' : ''; ?>>Medical Equipment</option>
                        <option value="Office Supplies" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Office Supplies') ? 'selected' : ''; ?>>Office Supplies</option>
                        <option value="PPE" <?php echo (isset($_POST['category']) && $_POST['category'] == 'PPE') ? 'selected' : ''; ?>>PPE</option>
                        <option value="Semi-Expendable" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Semi-Expendable') ? 'selected' : ''; ?>>Semi-Expendable</option>
                        <option value="Furniture" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Furniture') ? 'selected' : ''; ?>>Furniture</option>
                        <option value="Tools" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Tools') ? 'selected' : ''; ?>>Tools</option>
                        <option value="Other" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Other') ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="type_equipment">Type of Equipment</label>
                    <select class="form-control" id="type_equipment" name="type_equipment">
                        <option value="">-- Select Type of Equipment --</option>
                        <?php if ($type_of_equipment && $type_of_equipment->num_rows > 0): 
                        mysqli_data_seek($type_of_equipment, 0);
                        while($toe = $type_of_equipment->fetch_assoc()): ?>
                        <option value="<?php echo $toe['id']; ?>" 
                        <?php echo (isset($_POST['type_equipment']) && $_POST['type_equipment'] == $toe['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($toe['code'] . ' - ' . $toe['name']); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="equipment_id">Equipment Type</label>
                    <select class="form-control" id="equipment_id" name="equipment_id">
                        <option value="">-- Select Equipment Type --</option>
                        <?php if ($equipment_sub_type && $equipment_sub_type->num_rows > 0): 
                        mysqli_data_seek($equipment_sub_type, 0);
                        while($est = $equipment_sub_type->fetch_assoc()): ?>
                        <option value="<?php echo $est['id']; ?>" 
                        <?php echo (isset($_POST['equipment_id']) && $_POST['equipment_id'] == $est['id']) ? 'selected' : ''; ?>
                        data-type-of-equipment="<?php echo $est['type_of_equipment_id']; ?>">
                        <?php echo htmlspecialchars($est['code'] . ' - ' . $est['name']); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                </div>
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
                        <option value="pcs" <?php echo (isset($_POST['uom']) && $_POST['uom'] == 'pcs') ? 'selected' : ''; ?>>Pieces (pcs)</option>
                        <option value="box" <?php echo (isset($_POST['uom']) && $_POST['uom'] == 'box') ? 'selected' : ''; ?>>Box</option>
                        <option value="unit" <?php echo (isset($_POST['uom']) && $_POST['uom'] == 'unit') ? 'selected' : ''; ?>>Unit</option>
                        <option value="set" <?php echo (isset($_POST['uom']) && $_POST['uom'] == 'set') ? 'selected' : ''; ?>>Set</option>
                        <option value="meter" <?php echo (isset($_POST['uom']) && $_POST['uom'] == 'meter') ? 'selected' : ''; ?>>Meter</option>
                        <option value="liter" <?php echo (isset($_POST['uom']) && $_POST['uom'] == 'liter') ? 'selected' : ''; ?>>Liter</option>
                        <option value="kg" <?php echo (isset($_POST['uom']) && $_POST['uom'] == 'kg') ? 'selected' : ''; ?>>Kilogram (kg)</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="quantity" name="quantity" 
                    value="<?php echo isset($_POST['quantity']) ? htmlspecialchars($_POST['quantity']) : '1'; ?>" 
                    min="0.01" step="0.01" required onchange="checkQuantityForAutoMultiple()" onkeyup="checkQuantityForAutoMultiple()">
                    <small class="form-text text-muted" id="quantityMessage"></small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="unit_value">Unit Value (₱) <span class="text-danger">*</span></label>
                    <input type="number" class="form-control" id="unit_value" name="unit_value" 
                    value="<?php echo isset($_POST['unit_value']) ? htmlspecialchars($_POST['unit_value']) : ''; ?>" 
                    min="0.01" step="0.01" required placeholder="0.00">
                </div>
                
                <div class="form-group">
                    <label for="total_value">Total Value</label>
                    <input type="text" class="form-control" id="total_value" readonly placeholder="₱0.00">
                </div>
            </div>
            
            <div class="form-group">
                <label for="fund_cluster">Fund Cluster</label>
                <input type="text" class="form-control" id="fund_cluster" name="fund_cluster" 
                value="<?php echo isset($_POST['fund_cluster']) ? htmlspecialchars($_POST['fund_cluster']) : ''; ?>"
                placeholder="e.g., General Fund, Trust Fund">
            </div>
        </div>
        
        <!-- Condition and Certification -->
        <div class="form-section">
            <h3><i class="fas fa-clipboard-check"></i> Condition and Certification</h3>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="condition_text">Condition</label>
                    <select class="form-control" id="condition_text" name="condition_text">
                        <option value="New" <?php echo (isset($_POST['condition_text']) && $_POST['condition_text'] == 'New') ? 'selected' : ''; ?>>New</option>
                        <option value="Good" <?php echo (isset($_POST['condition_text']) && $_POST['condition_text'] == 'Good') ? 'selected' : ''; ?>>Good</option>
                        <option value="Fair" <?php echo (isset($_POST['condition_text']) && $_POST['condition_text'] == 'Fair') ? 'selected' : ''; ?>>Fair</option>
                        <option value="Poor" <?php echo (isset($_POST['condition_text']) && $_POST['condition_text'] == 'Poor') ? 'selected' : ''; ?>>Poor</option>
                        <option value="For Repair" <?php echo (isset($_POST['condition_text']) && $_POST['condition_text'] == 'For Repair') ? 'selected' : ''; ?>>For Repair</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="certified_correct">Certified Correct By (Multi-Select)</label>
                    <?php 
                    $certified_selected = [];
                    if (!empty($_POST['certified_correct']) && is_array($_POST['certified_correct'])) {
                        $certified_selected = $_POST['certified_correct'];
                    }
                    ?>
                    <select class="form-control" id="certified_correct" name="certified_correct[]" multiple size="5">
                        <?php if ($users && $users->num_rows > 0): 
                        mysqli_data_seek($users, 0);
                        while($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" 
                        <?php echo in_array($user['id'], $certified_selected) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                    <small class="form-text text-muted">Hold Ctrl (Cmd on Mac) to select multiple users</small>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="approved_by">Approved By (Multi-Select)</label>
                    <?php 
                    $approved_selected = [];
                    if (!empty($_POST['approved_by']) && is_array($_POST['approved_by'])) {
                        $approved_selected = $_POST['approved_by'];
                    }
                    ?>
                    <select class="form-control" id="approved_by" name="approved_by[]" multiple size="5">
                        <?php if ($users && $users->num_rows > 0): 
                        mysqli_data_seek($users, 0);
                        while($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" 
                        <?php echo in_array($user['id'], $approved_selected) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                    <small class="form-text text-muted">Hold Ctrl (Cmd on Mac) to select multiple users</small>
                </div>
                
                <div class="form-group">
                    <label for="verified_by">Verified By (Multi-Select)</label>
                    <?php 
                    $verified_selected = [];
                    if (!empty($_POST['verified_by']) && is_array($_POST['verified_by'])) {
                        $verified_selected = $_POST['verified_by'];
                    }
                    ?>
                    <select class="form-control" id="verified_by" name="verified_by[]" multiple size="5">
                        <?php if ($users && $users->num_rows > 0): 
                        mysqli_data_seek($users, 0);
                        while($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>" 
                        <?php echo in_array($user['id'], $verified_selected) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                        </option>
                        <?php endwhile; endif; ?>
                    </select>
                    <small class="form-text text-muted">Hold Ctrl (Cmd on Mac) to select multiple users</small>
                </div>
            </div>
        </div>
        
        <!-- Remarks Only -->
        <div class="form-section">
            <h3><i class="fas fa-comment"></i> Remarks</h3>
            
            <div class="form-group">
                <label for="remarks">Remarks</label>
                <textarea class="form-control" id="remarks" name="remarks" rows="3" 
                placeholder="Any additional notes or comments"><?php echo isset($_POST['remarks']) ? htmlspecialchars($_POST['remarks']) : ''; ?></textarea>
            </div>
        </div>
        
        <!-- Barcode Section -->
        <div class="form-section barcode-section">
            <h3><i class="fas fa-barcode"></i> Barcode Generation</h3>
            <div id="barcodeModeIndicator" class="alert alert-info" style="display: none; margin-bottom: 15px;"></div>
            
            <div class="barcode-options">
                <div class="form-row">
                    <div class="form-group">
                        <label>Barcode Option</label>
                        <div class="radio-group">
                            <label class="radio-inline">
                                <input type="radio" name="barcode_option" id="barcode_single" value="single" checked onclick="toggleBarcodeOption()"> Single Item
                            </label>
                            <label class="radio-inline">
                                <input type="radio" name="barcode_option" id="barcode_multiple" value="multiple" onclick="toggleBarcodeOption()"> Multiple Items (Sequential)
                            </label>
                        </div>
                    </div>
                </div>
                
                <!-- Single Barcode Options -->
                <div id="singleBarcodeOptions">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="barcode_prefix">Barcode Prefix</label>
                            <input type="text" class="form-control" id="barcode_prefix" name="barcode_prefix" value="INV" maxlength="10" placeholder="e.g., INV">
                        </div>
                        <div class="form-group">
                            <label>&nbsp;</label>
                            <button type="button" class="btn btn-secondary" onclick="generateSingleBarcode()">
                                <i class="fas fa-sync-alt"></i> Generate Barcode
                            </button>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group" style="flex: 2;">
                            <label for="generated_barcode">Generated Barcode</label>
                            <div class="barcode-display">
                                <input type="text" class="form-control" id="generated_barcode" name="generated_barcode" readonly>
                                <div id="barcodeImage" class="barcode-image"></div>
                            </div>
                        </div>
                        <div class="form-group" style="flex: 1;">
                            <label for="manual_barcode">Or Enter Manually</label>
                            <input type="text" class="form-control" id="manual_barcode" name="manual_barcode" placeholder="Enter barcode manually">
                        </div>
                    </div>
                </div>
                
                <!-- Multiple Barcode Options -->
                <div id="multipleBarcodeOptions" style="display: none;">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="barcode_prefix_multi">Barcode Prefix</label>
                            <input type="text" class="form-control" id="barcode_prefix_multi" name="barcode_prefix" value="INV" maxlength="10">
                        </div>
                        <div class="form-group">
                            <label for="barcode_quantity">Number of Items</label>
                            <input type="number" class="form-control" id="barcode_quantity" name="barcode_quantity" value="5" min="1" max="100">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" class="btn btn-secondary" onclick="previewBarcodes()">
                            <i class="fas fa-eye"></i> Preview Barcodes
                        </button>
                    </div>
                    
                    <div id="barcodePreview" class="barcode-preview-grid"></div>
                </div>
            </div>
        </div>
        
        <!-- Submit Buttons -->
        <div class="form-group">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save Inventory Item(s)
            </button>
            <a href="<?php echo SITE_URL; ?>/admin/dashboard.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
// Check quantity for auto multiple barcode mode
function checkQuantityForAutoMultiple() {
    let quantity = parseFloat(document.getElementById('quantity').value);
    let singleRadio = document.getElementById('barcode_single');
    let multipleRadio = document.getElementById('barcode_multiple');
    let singleOptions = document.getElementById('singleBarcodeOptions');
    let multipleOptions = document.getElementById('multipleBarcodeOptions');
    let modeIndicator = document.getElementById('barcodeModeIndicator');
    let quantityMessage = document.getElementById('quantityMessage');
    
    // Check if quantity is a whole number and greater than 1
    let isInteger = Number.isInteger(quantity) && quantity > 1;
    
    if (isInteger) {
        // Auto-switch to multiple mode
        multipleRadio.checked = true;
        singleRadio.checked = false;
        singleOptions.style.display = 'none';
        multipleOptions.style.display = 'block';
        
        // Update the quantity field in multiple options
        document.getElementById('barcode_quantity').value = quantity;
        
        // Show indicator message
        modeIndicator.style.display = 'block';
        modeIndicator.innerHTML = '<i class="fas fa-info-circle"></i> Auto-switched to Multiple Items mode because quantity is ' + quantity + '. Each item will have its own unique barcode.';
        
        // Auto-preview barcodes
        setTimeout(previewBarcodes, 100);
    } else {
        // Switch back to single mode if quantity <= 1
        if (quantity <= 1) {
            singleRadio.checked = true;
            multipleRadio.checked = false;
            singleOptions.style.display = 'block';
            multipleOptions.style.display = 'none';
            modeIndicator.style.display = 'none';
            
            // Generate a single barcode
            setTimeout(generateSingleBarcode, 100);
        }
    }
}

// Toggle between single and multiple barcode options
function toggleBarcodeOption() {
    let singleOption = document.getElementById('singleBarcodeOptions');
    let multipleOption = document.getElementById('multipleBarcodeOptions');
    let singleRadio = document.querySelector('input[name="barcode_option"][value="single"]');
    let modeIndicator = document.getElementById('barcodeModeIndicator');
    
    if (singleRadio.checked) {
        singleOption.style.display = 'block';
        multipleOption.style.display = 'none';
        modeIndicator.style.display = 'none';
    } else {
        singleOption.style.display = 'none';
        multipleOption.style.display = 'block';
    }
}

// Generate single barcode
function generateSingleBarcode() {
    let prefix = document.getElementById('barcode_prefix').value;
    let barcodeValue = prefix + getFormattedDate() + Math.floor(1000 + Math.random() * 9000);
    
    document.getElementById('generated_barcode').value = barcodeValue;
    document.getElementById('manual_barcode').value = '';
    
    // Generate barcode image via AJAX
    fetch('?generate_barcode=1&barcode_value=' + encodeURIComponent(barcodeValue))
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let barcodeImage = document.getElementById('barcodeImage');
            barcodeImage.innerHTML = '<img src="data:image/png;base64,' + data.barcode + '" alt="Barcode" style="max-width: 100%;">';
        } else {
            alert('Error generating barcode: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error generating barcode');
    });
}

// Preview multiple barcodes with actual images
function previewBarcodes() {
    let prefix = document.getElementById('barcode_prefix_multi') ? document.getElementById('barcode_prefix_multi').value : document.getElementById('barcode_prefix').value;
    let quantity = parseInt(document.getElementById('barcode_quantity').value) || 5;
    
    let preview = document.getElementById('barcodePreview');
    preview.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Generating barcodes...</div>';
    
    // Fetch multiple barcodes via AJAX
    fetch('?generate_multiple_preview=1&prefix=' + encodeURIComponent(prefix) + '&quantity=' + quantity)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let html = '';
            
            data.barcodes.forEach(barcode => {
                html += `
                    <div class="barcode-preview-card">
                        <div class="item-number">Item ${barcode.index}</div>
                        <div class="barcode-value" style="font-family: monospace; font-size: 13px; padding: 10px; background: var(--light); border-radius: 5px; margin: 10px 0;">${barcode.value}</div>
                    </div>
                `;
            });
            
            if (data.total > 10) {
                html += `<div style="grid-column: 1/-1; text-align: center; padding: 10px; color: var(--text-muted);">
                    <i class="fas fa-info-circle"></i> Showing first 10 of ${data.total} barcodes
                </div>`;
            }
            
            preview.innerHTML = html;
        } else {
            preview.innerHTML = '<div class="alert alert-danger">Error: ' + data.error + '</div>';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        preview.innerHTML = '<div class="alert alert-danger">Error generating barcodes</div>';
    });
}

// Helper function to get formatted date
function getFormattedDate() {
    let date = new Date();
    return date.getFullYear() + 
        str_pad(date.getMonth() + 1, 2, '0', 'left') + 
        str_pad(date.getDate(), 2, '0', 'left');
}

// Helper function to pad strings
function str_pad(string, length, pad, direction) {
    string = String(string);
    while (string.length < length) {
        if (direction === 'left') {
            string = pad + string;
        } else {
            string = string + pad;
        }
    }
    return string;
}

// Calculate total value
document.getElementById('quantity').addEventListener('input', calculateTotal);
document.getElementById('unit_value').addEventListener('input', calculateTotal);

function calculateTotal() {
    let quantity = parseFloat(document.getElementById('quantity').value) || 0;
    let unitValue = parseFloat(document.getElementById('unit_value').value) || 0;
    let total = quantity * unitValue;
    document.getElementById('total_value').value = '₱' + total.toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

// Form validation
document.getElementById('inventoryForm').addEventListener('submit', function(e) {
    let quantity = parseFloat(document.getElementById('quantity').value);
    let unitValue = parseFloat(document.getElementById('unit_value').value);
    let articleName = document.getElementById('article_name').value.trim();
    let barcodeOption = document.querySelector('input[name="barcode_option"]:checked').value;
    let isInteger = Number.isInteger(quantity) && quantity > 1;
    
    if (articleName === '') {
        e.preventDefault();
        alert('Please enter an article name');
        return false;
    }
    
    if (quantity <= 0) {
        e.preventDefault();
        alert('Quantity must be greater than 0');
        return false;
    }
    
    if (unitValue <= 0) {
        e.preventDefault();
        alert('Unit value must be greater than 0');
        return false;
    }
    
    // For auto multiple mode, we don't need to validate barcode fields
    if (!isInteger) {
        if (barcodeOption === 'single') {
            let generatedBarcode = document.getElementById('generated_barcode').value;
            let manualBarcode = document.getElementById('manual_barcode').value;
            
            if (!generatedBarcode && !manualBarcode) {
                e.preventDefault();
                alert('Please generate a barcode or enter one manually');
                return false;
            }
        }
    }
});

// Auto-calculate on page load if values exist
window.addEventListener('load', function() {
    calculateTotal();
    // Generate initial barcode
    generateSingleBarcode();
    // Check initial quantity
    checkQuantityForAutoMultiple();
});

// Cascading dropdown: Filter Equipment Type based on Type of Equipment
document.getElementById('type_equipment').addEventListener('change', function() {
    var selectedTypeId = this.value;
    var equipmentSelect = document.getElementById('equipment_id');
    var options = equipmentSelect.querySelectorAll('option');
    
    options.forEach(function(option) {
        if (option.value === '') return;
        var typeOfEquipmentId = option.getAttribute('data-type-of-equipment');
        if (selectedTypeId === '' || typeOfEquipmentId == selectedTypeId) {
            option.style.display = '';
        } else {
            option.style.display = 'none';
        }
    });
    
    if (selectedTypeId !== '') {
        var selectedOption = equipmentSelect.options[equipmentSelect.selectedIndex];
        if (selectedOption && selectedOption.value !== '') {
            var selectedTypeOfEquipment = selectedOption.getAttribute('data-type-of-equipment');
            if (selectedTypeOfEquipment != selectedTypeId) {
                equipmentSelect.value = '';
            }
        }
    }
});

// Initial filter on page load
window.addEventListener('load', function() {
    var typeEquipmentSelect = document.getElementById('type_equipment');
    if (typeEquipmentSelect) {
        typeEquipmentSelect.dispatchEvent(new Event('change'));
    }
});
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>