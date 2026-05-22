<?php
/**
 * Semi-Expendable Barcode Scanner Page
 * Scan barcodes to quickly find and manage Semi-Expendable items
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Disable error display for AJAX requests to prevent JSON corruption
if (isset($_GET['scan_barcode'])) {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Check database connection
if (!isset($conn) || !$conn) {
    error_log("Database connection failed in barcodescannerforsemi_expendable.php");
    if (isset($_GET['scan_barcode'])) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Database connection failed']);
        exit;
    }
    die("Database connection failed. Please try again later.");
}

// Include barcode library
require_once $root_path . '/vendor/autoload.php';
use Picqer\Barcode\BarcodeGeneratorPNG;



// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Semi-Expendable Barcode Scanner';
$page_description = 'Scan barcodes to quickly find Semi-Expendable items';

// Rate limiting configuration
$rate_limit_max = 30;
$rate_limit_window = 60;

// Handle barcode lookup via AJAX
if (isset($_GET['scan_barcode'])) {
    // Clean output buffers to ensure clean JSON
    ob_clean();
    header('Content-Type: application/json');
    
    try {
        // CSRF validation
        if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid security token']);
            exit;
        }
        
        // Rate limiting
        $client_ip = $_SERVER['REMOTE_ADDR'];
        $rate_key = 'semi_scan_attempts_' . md5($client_ip);
        
        if (!isset($_SESSION[$rate_key])) {
            $_SESSION[$rate_key] = [
                'count' => 0,
                'first_attempt' => time()
            ];
        }
        
        if (time() - $_SESSION[$rate_key]['first_attempt'] > $rate_limit_window) {
            $_SESSION[$rate_key] = [
                'count' => 0,
                'first_attempt' => time()
            ];
        }
        
        if ($_SESSION[$rate_key]['count'] >= $rate_limit_max) {
            http_response_code(429);
            echo json_encode(['error' => 'Rate limit exceeded. Please try again later.']);
            exit;
        }
        
        $_SESSION[$rate_key]['count']++;
        
        // Validate barcode input
        $barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';
        
        if (empty($barcode)) {
            echo json_encode(['error' => 'Please provide a barcode']);
            exit;
        }
        
        // Validate barcode format
        if (!preg_match('/^[A-Za-z0-9\-_\.\+]+$/', $barcode)) {
            echo json_encode(['error' => 'Invalid barcode format']);
            exit;
        }
        
        // Sanitize
        $barcode = sanitize($barcode);
        $barcode = trim($barcode);
        
        // Search in semi_ppe table - FIXED: removed non-existent columns
        $stmt = $conn->prepare("
            SELECT i.*, 
                   toe.name as type_equipment_name,
                   est.name as sub_type_name,
                   s.supplier_name,
                   CONCAT(ap.firstname, ' ', ap.lastname) as approver_name,
                   CONCAT(vr.firstname, ' ', vr.lastname) as verifier_name,
                   CONCAT(cr.firstname, ' ', cr.lastname) as created_by_name
            FROM semi_ppe i
            LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
            LEFT JOIN equipment_sub_type est ON i.equipment_sub_type_id = est.id
            LEFT JOIN supplier s ON i.supplier_id = s.id
            LEFT JOIN users ap ON i.approved_by = ap.id
            LEFT JOIN users vr ON i.verified_by = vr.id
            LEFT JOIN users cr ON i.created_by = cr.id
            WHERE i.barcode_data = ? OR i.property_no = ?
            LIMIT 1
        ");
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $stmt->bind_param("ss", $barcode, $barcode);
        
        if (!$stmt->execute()) {
            throw new Exception("Database execute error: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            // Check if this is a multiple item (4-digit suffix)
            $is_multiple = preg_match('/-\d{4}$/', $row['property_no']);
            
            // Decode UOM JSON for display
            $uom_display = $row['uom'];
            $big_unit_display = '';
            $small_unit_display = '';
            $quantity_display = $row['qty_physical_count'] ?? 0;
            
            if (!empty($row['uom'])) {
                if (strpos($row['uom'], '{') === 0 || strpos($row['uom'], '[') === 0) {
                    $uom_data = json_decode($row['uom'], true);
                    if ($uom_data && is_array($uom_data)) {
                        if (!empty($uom_data['big_quantity']) && !empty($uom_data['big_unit'])) {
                            $big_unit_display = $uom_data['big_quantity'] . ' ' . $uom_data['big_unit'];
                        }
                        if (!empty($uom_data['pieces_per_big_unit']) && !empty($uom_data['small_unit'])) {
                            $small_unit_display = $uom_data['pieces_per_big_unit'] . ' ' . $uom_data['small_unit'];
                        } elseif (!empty($uom_data['small_unit'])) {
                            $small_unit_display = $uom_data['small_unit'];
                        }
                        $quantity_display = $uom_data['total_pieces'] ?? $row['qty_physical_count'];
                        $uom_display = $uom_data['display'] ?? ($big_unit_display . ' × ' . $small_unit_display);
                    }
                }
            }
            
            // Calculate total quantity when part of a multiple set
            $total_qty = (float)($row['qty_physical_count'] ?? 0);
            if ($is_multiple) {
                $base = preg_replace('/-\d{4}$/', '', $row['property_no']);
                $sumStmt = $conn->prepare("SELECT SUM(qty_physical_count) as total FROM semi_ppe WHERE property_no LIKE CONCAT(?, '-%')");
                if ($sumStmt) {
                    $sumStmt->bind_param("s", $base);
                    if ($sumStmt->execute()) {
                        $sr = $sumStmt->get_result();
                        if ($sr && $srow = $sr->fetch_assoc()) {
                            $total_qty = floatval($srow['total']);
                        }
                    }
                    $sumStmt->close();
                }
            }
            
            // Get issued status
            $is_issued = false;
            $issue_stmt = $conn->prepare("SELECT id FROM equipment_issuance WHERE inventory_id = ? AND status = 'issued' LIMIT 1");
            if ($issue_stmt) {
                $issue_stmt->bind_param("i", $row['id']);
                if ($issue_stmt->execute()) {
                    $issue_result = $issue_stmt->get_result();
                    $is_issued = $issue_result && $issue_result->num_rows > 0;
                }
                $issue_stmt->close();
            }
            
            // Build location string from available data
            $location_parts = [];
            if (!empty($row['section'])) $location_parts[] = $row['section'];
            if (!empty($row['department'])) $location_parts[] = $row['department'];
            $location = !empty($location_parts) ? implode(' - ', $location_parts) : 'N/A';
            
            echo json_encode([
                'success' => true,
                'found' => true,
                'item' => [
                    'id' => (int)$row['id'],
                    'article_name' => htmlspecialchars($row['article_name'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'property_no' => htmlspecialchars($row['property_no'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'description' => htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'barcode_data' => htmlspecialchars($row['barcode_data'] ?? $row['property_no'], ENT_QUOTES, 'UTF-8'),
                    'category' => 'Semi-Expendable',
                    'type_equipment' => htmlspecialchars($row['type_equipment_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'equipment_name' => htmlspecialchars($row['sub_type_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'quantity' => (float)($row['qty_physical_count'] ?? 0),
                    'total_qty' => $total_qty,
                    'uom' => htmlspecialchars($uom_display, ENT_QUOTES, 'UTF-8'),
                    'big_unit_display' => htmlspecialchars($big_unit_display, ENT_QUOTES, 'UTF-8'),
                    'small_unit_display' => htmlspecialchars($small_unit_display, ENT_QUOTES, 'UTF-8'),
                    'unit_value' => (float)($row['unit_value'] ?? 0),
                    'location' => $location,
                    'building' => htmlspecialchars($row['building'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'condition_text' => htmlspecialchars($row['condition_text'] ?? 'Good', ENT_QUOTES, 'UTF-8'),
                    'fund_cluster' => htmlspecialchars($row['fund_cluster'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'certified_correct' => htmlspecialchars($row['certified_correct'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'approver_name' => htmlspecialchars($row['approver_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'verifier_name' => htmlspecialchars($row['verifier_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'allocatee_name' => 'N/A',
                    'created_by_name' => htmlspecialchars($row['created_by_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'supplier_name' => htmlspecialchars($row['supplier_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'is_issued' => $is_issued,
                    'is_multiple' => $is_multiple,
                    'date_added' => $row['date_added'] ?? null,
                    'date_updated' => $row['date_updated'] ?? null,
                    'remarks' => htmlspecialchars($row['remarks'] ?? 'N/A', ENT_QUOTES, 'UTF-8')
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'found' => false,
                'message' => 'No Semi-Expendable item found with this barcode',
                'barcode' => htmlspecialchars($barcode, ENT_QUOTES, 'UTF-8')
            ]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Semi-Expendable Barcode scanner error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'An internal server error occurred']);
    }
    exit;
}

// If we're not in AJAX mode, include the header and display the page
include INCLUDE_PATH . '/header.php';
?>

<!-- Include QuaggaJS for barcode scanning -->
<script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>

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

.scanner-container { padding: 20px; }
.scanner-header { text-align: center; margin-bottom: 30px; }
.scanner-header h2 { color: var(--primary); font-size: 28px; margin-bottom: 10px; }
.scanner-header h2 i { color: var(--accent); margin-right: 10px; }
.scanner-main { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
.scanner-left { background: var(--white); border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.scanner-right { background: var(--white); border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.scanner-card h3 { color: var(--primary); margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid var(--accent-light); }
.scanner-card h3 i { color: var(--accent); margin-right: 10px; }

/* Search bar styling */
.search-bar-wrapper { margin-bottom: 20px; }
.search-input-section { margin-bottom: 15px; }
.search-input-section label { display: block; margin-bottom: 8px; color: var(--primary); font-weight: 500; }
.search-input-section .input-group { display: flex; gap: 10px; }
.search-input-section .form-control { flex: 1; padding: 12px; border: 2px solid var(--border-light); border-radius: 8px; font-size: 16px; transition: all 0.3s; background: transparent; }
.search-input-section .form-control:focus { border-color: var(--primary); outline: none; box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1); }

/* Scanner Type Selector */
.scanner-type-selector { margin-bottom: 20px; }
.button-group { display: flex; gap: 10px; padding: 10px 0; flex-wrap: wrap; }
.scanner-type-btn { flex: 1; background: var(--light); border: 2px solid var(--border-light); border-radius: 30px; padding: 10px 15px; font-size: 14px; font-weight: 600; color: var(--text-secondary); cursor: pointer; transition: all 0.2s ease; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px; }
.scanner-type-btn i { font-size: 16px; }
.scanner-type-btn.active { background: var(--accent); border-color: var(--accent); color: var(--text-primary); box-shadow: 0 2px 8px rgba(248, 176, 192, 0.3); }
.scanner-type-btn:hover:not(.active) { background: var(--accent-light); border-color: var(--accent); transform: translateY(-2px); }

/* Camera Container */
.camera-container { background: #000; border-radius: 8px; overflow: hidden; margin: 10px 0; border: 2px solid var(--secondary); position: relative; }
#scanner-container { width: 100%; height: 300px; background: #000; }
.scanner-status { position: absolute; bottom: 10px; left: 10px; right: 10px; background: rgba(0,0,0,0.7); color: var(--text-light); padding: 5px 10px; border-radius: 5px; font-size: 12px; text-align: center; z-index: 10; }
.camera-controls { display: flex; gap: 10px; margin-top: 10px; align-items: center; }
.scanner-instructions { margin-top: 15px; padding: 10px; background: var(--accent-light); border-radius: 5px; color: var(--text-secondary); font-size: 13px; }

/* File Upload Area */
.file-upload-area { border: 3px dashed var(--border-light); border-radius: 10px; padding: 40px 20px; text-align: center; cursor: pointer; transition: all 0.3s; margin: 20px 0; }
.file-upload-area:hover { border-color: var(--primary); background: var(--accent-light); }
.file-upload-area i { font-size: 48px; color: var(--text-muted); margin-bottom: 10px; }
.image-preview { text-align: center; margin-top: 20px; }
.image-preview img { max-width: 100%; max-height: 200px; border-radius: 5px; margin-bottom: 10px; }

/* Recent Scans */
.recent-scans { margin-top: 30px; }
.recent-scans h4 { color: var(--primary); margin-bottom: 15px; }
.recent-scans-list { max-height: 200px; overflow-y: auto; border: 1px solid var(--border-light); border-radius: 8px; padding: 10px; }
.recent-scan-item { display: flex; justify-content: space-between; align-items: center; padding: 10px; border-bottom: 1px solid var(--border-light); cursor: pointer; transition: all 0.3s; }
.recent-scan-item:hover { background-color: var(--light); }
.recent-scan-item .item-name { font-weight: 500; color: var(--primary); }
.recent-scan-item .item-barcode { font-family: monospace; font-size: 12px; color: var(--accent); }

/* Scan Result */
.scan-result { min-height: 300px; }
.scan-placeholder { text-align: center; padding: 60px 20px; color: var(--text-muted); }
.scan-placeholder i { font-size: 64px; margin-bottom: 20px; color: var(--border-light); }
.found-item { animation: fadeIn 0.5s; }
@keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
.item-detail-card { background: var(--light); border-radius: 10px; padding: 20px; margin-bottom: 20px; border-left: 4px solid var(--accent); }
.item-detail-card h3 { color: var(--primary); margin-bottom: 15px; font-size: 20px; }
.item-detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-bottom: 20px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: 12px; color: var(--text-muted); margin-bottom: 3px; }
.detail-value { font-size: 16px; font-weight: 500; color: var(--primary); }
.detail-value.highlight { color: var(--accent); font-size: 18px; }
.barcode-preview { text-align: center; padding: 15px; background: var(--white); border-radius: 8px; margin: 15px 0; }
.barcode-preview img { max-width: 100%; height: auto; }
.status-badge { display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 12px; font-weight: 600; }
.status-badge.available { background-color: var(--success-light); color: var(--success); }
.status-badge.issued { background-color: var(--secondary); color: var(--text-primary); }

/* Quick Actions */
.quick-actions { margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--accent-light); }
.quick-actions h4 { color: var(--primary); margin-bottom: 15px; }
.quick-actions .action-buttons { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 10px; }

/* Not Found */
.not-found { text-align: center; padding: 40px; color: var(--danger); }
.not-found i { font-size: 48px; margin-bottom: 15px; }

/* Modal */
.modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); }
.modal-content { background-color: var(--white); margin: 5% auto; padding: 20px; border-radius: 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.2); max-width: 700px; width: 90%; position: relative; }
.modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border-light); }
.modal-header h2 { color: var(--primary); margin: 0; }
.modal-close { font-size: 28px; font-weight: bold; cursor: pointer; color: var(--text-muted); }
.modal-close:hover { color: var(--accent); }
.modal-footer { margin-top: 20px; padding-top: 10px; border-top: 1px solid var(--border-light); text-align: right; }

/* Buttons */
.btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 14px; transition: all 0.3s; }
.btn-sm { padding: 5px 10px; font-size: 12px; }
.btn-primary { background-color: var(--accent); color: var(--text-primary); }
.btn-primary:hover { background-color: #e69eb0; transform: translateY(-2px); }
.btn-success { background-color: var(--success-light); color: var(--success); }
.btn-success:hover { background-color: #b5d8b5; transform: translateY(-2px); }
.btn-info { background-color: var(--info); color: var(--text-light); }
.btn-info:hover { background-color: #7a9fe6; transform: translateY(-2px); }
.btn-secondary { background-color: var(--secondary); color: var(--text-light); }
.btn-secondary:hover { background-color: #7a9fe6; transform: translateY(-2px); }

/* Loading */
.loading { text-align: center; padding: 40px; color: var(--text-muted); }
.loading i { font-size: 32px; margin-bottom: 10px; color: var(--primary); }

/* Error */
.error-message { background-color: #ffebee; color: var(--danger); padding: 12px; border-radius: 5px; margin: 10px 0; text-align: center; }

/* Responsive */
@media (max-width: 768px) {
    .scanner-main { grid-template-columns: 1fr; }
    .camera-controls { flex-direction: column; }
    .action-buttons { grid-template-columns: 1fr; }
    .item-detail-grid { grid-template-columns: 1fr; }
}
</style>

<div class="scanner-container">
    <div class="scanner-header">
        <h2><i class="fas fa-box-open"></i> Semi-Expendable Barcode Scanner</h2>
        <p>Scan barcodes to instantly find and manage Semi-Expendable items</p>
    </div>

    <div class="scanner-main">
        <!-- Left Column - Scanner -->
        <div class="scanner-left">
            <div class="scanner-card">
                <h3><i class="fas fa-camera"></i> Scan Semi-Expendable Barcode</h3>
                
                <!-- Search Bar Wrapper -->
                <div class="search-bar-wrapper">
                    <div id="liveSearchSection" class="search-input-section">
                        <label for="live_barcode_input"><i class="fas fa-search"></i> Search by Barcode:</label>
                        <div class="input-group">
                            <input type="text" id="live_barcode_input" class="form-control" placeholder="Type barcode and press Enter...">
                            <button class="btn btn-primary" onclick="searchLiveBarcode()"><i class="fas fa-search"></i> Find</button>
                        </div>
                    </div>
                    <div id="fileSearchSection" class="search-input-section" style="display: none;">
                        <label for="file_barcode_input"><i class="fas fa-search"></i> Search by Barcode:</label>
                        <div class="input-group">
                            <input type="text" id="file_barcode_input" class="form-control" placeholder="Type barcode and press Enter...">
                            <button class="btn btn-primary" onclick="searchFileBarcode()"><i class="fas fa-search"></i> Find</button>
                        </div>
                    </div>
                    <div id="handheldSearchSection" class="search-input-section" style="display: none;">
                        <label for="handheld_barcode_input"><i class="fas fa-search"></i> Enter Barcode Manually:</label>
                        <div class="input-group">
                            <input type="text" id="handheld_barcode_input" class="form-control" placeholder="Type barcode and press Enter...">
                            <button class="btn btn-primary" onclick="searchHandheldBarcode()"><i class="fas fa-search"></i> Find</button>
                        </div>
                    </div>
                </div>

                <!-- Scanner Type Selection -->
                <div class="scanner-type-selector">
                    <div class="button-group">
                        <button type="button" class="scanner-type-btn active" data-scanner="live" onclick="setScannerType('live')"><i class="fas fa-video"></i> Live Camera</button>
                        <button type="button" class="scanner-type-btn" data-scanner="file" onclick="setScannerType('file')"><i class="fas fa-upload"></i> Upload Image</button>
                        <button type="button" class="scanner-type-btn" data-scanner="handheld" onclick="setScannerType('handheld')"><i class="fas fa-keyboard"></i> Handheld Scanner</button>
                    </div>
                </div>

                <!-- Live Camera Scanner -->
                <div id="liveScannerSection" class="camera-section">
                    <div class="camera-container">
                        <div id="scanner-container" style="width: 100%; height: 300px; background: #000; position: relative;"></div>
                        <div id="scanner-status" class="scanner-status">Starting camera...</div>
                    </div>
                    <div class="camera-controls">
                        <button class="btn btn-secondary" onclick="startScanner()" id="startScannerBtn" style="display: none;"><i class="fas fa-play"></i> Start Scanner</button>
                        <button class="btn btn-secondary" onclick="stopScanner()" id="stopScannerBtn"><i class="fas fa-stop"></i> Stop Scanner</button>
                        <select id="camera-select" class="form-control" onchange="switchCamera()"><option value="">Select Camera</option></select>
                    </div>
                    <div class="scanner-instructions"><p><i class="fas fa-info-circle"></i> Position the barcode in the center. The scanner will automatically detect barcodes.</p></div>
                </div>

                <!-- File Upload Scanner -->
                <div id="fileScannerSection" class="file-section" style="display: none;">
                    <div class="file-upload-area" id="fileUploadArea">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop a barcode image or click to upload</p>
                        <input type="file" id="barcodeImage" accept="image/*" style="display: none;">
                        <button class="btn btn-secondary" onclick="document.getElementById('barcodeImage').click()"><i class="fas fa-folder-open"></i> Browse</button>
                    </div>
                    <div id="imagePreview" class="image-preview" style="display: none;">
                        <img id="previewImg" src="" alt="Preview">
                        <div style="display: flex; gap: 10px; justify-content: center;">
                            <button class="btn btn-primary" onclick="scanImageFile()"><i class="fas fa-search"></i> Scan Image</button>
                            <button class="btn btn-secondary" onclick="resetImageUpload()"><i class="fas fa-undo"></i> Upload Another</button>
                        </div>
                    </div>
                </div>

                <!-- Recent Scans -->
                <div class="recent-scans">
                    <h4><i class="fas fa-history"></i> Recent Semi-Expendable Scans</h4>
                    <div id="recentScansList" class="recent-scans-list"></div>
                    <button class="btn btn-sm btn-secondary" onclick="clearRecentScans()" style="margin-top: 10px; width: 100%;"><i class="fas fa-trash"></i> Clear History</button>
                </div>
            </div>
        </div>

        <!-- Right Column - Results -->
        <div class="scanner-right">
            <div id="scanResult" class="scan-result">
                <div class="scan-placeholder"><i class="fas fa-box-open"></i><p>Scan a Semi-Expendable barcode to see item details</p></div>
            </div>
            <div id="quickActions" class="quick-actions" style="display: none;">
                <h4><i class="fas fa-bolt"></i> Quick Actions</h4>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="editCurrentItem()"><i class="fas fa-edit"></i> Edit Item</button>
                    <button class="btn btn-success" onclick="issueCurrentItem()"><i class="fas fa-hand-holding"></i> Issue Item</button>
                    <button class="btn btn-info" onclick="printCurrentItemBarcode()"><i class="fas fa-print"></i> Print Barcode</button>
                    <button class="btn btn-secondary" onclick="viewCurrentItem()"><i class="fas fa-eye"></i> View Details</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Item Details Modal -->
<div id="itemDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header"><h2>Semi-Expendable Item Details</h2><span class="modal-close" onclick="closeItemDetailsModal()">&times;</span></div>
        <div class="modal-body" id="itemDetailsContent"></div>
        <div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeItemDetailsModal()">Close</button></div>
    </div>
</div>

<!-- Success Sound -->
<audio id="beep-sound" src="data:audio/wav;base64,UklGRlwAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YVQAAACAgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f38=" preload="auto"></audio>

<script>
let currentScannedItem = null;
let recentScans = [];
let scannerInitialized = false;
let cameras = [];
let csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

function setScannerType(type) {
    document.querySelectorAll('.scanner-type-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-scanner') === type) btn.classList.add('active');
    });
    toggleScannerType(type);
}

function toggleScannerType(type) {
    if (type === 'live') {
        document.getElementById('liveScannerSection').style.display = 'block';
        document.getElementById('fileScannerSection').style.display = 'none';
        document.getElementById('liveSearchSection').style.display = 'block';
        document.getElementById('fileSearchSection').style.display = 'none';
        document.getElementById('handheldSearchSection').style.display = 'none';
        if (scannerInitialized) startScanner(); else initScanner();
        setTimeout(() => { const input = document.getElementById('live_barcode_input'); if (input) input.focus(); }, 100);
    } else if (type === 'file') {
        document.getElementById('liveScannerSection').style.display = 'none';
        document.getElementById('fileScannerSection').style.display = 'block';
        document.getElementById('liveSearchSection').style.display = 'none';
        document.getElementById('fileSearchSection').style.display = 'block';
        document.getElementById('handheldSearchSection').style.display = 'none';
        stopScanner();
        setTimeout(() => { const input = document.getElementById('file_barcode_input'); if (input) input.focus(); }, 100);
    } else if (type === 'handheld') {
        document.getElementById('liveScannerSection').style.display = 'none';
        document.getElementById('fileScannerSection').style.display = 'none';
        document.getElementById('liveSearchSection').style.display = 'none';
        document.getElementById('fileSearchSection').style.display = 'none';
        document.getElementById('handheldSearchSection').style.display = 'block';
        stopScanner();
        setTimeout(() => { const input = document.getElementById('handheld_barcode_input'); if (input) input.focus(); }, 100);
    }
}

function searchLiveBarcode() { const barcode = document.getElementById('live_barcode_input').value.trim(); if (barcode) performBarcodeSearch(barcode); else alert('Please enter a barcode'); }
function searchFileBarcode() { const barcode = document.getElementById('file_barcode_input').value.trim(); if (barcode) performBarcodeSearch(barcode); else alert('Please enter a barcode'); }
function searchHandheldBarcode() { const barcode = document.getElementById('handheld_barcode_input').value.trim(); if (barcode) performBarcodeSearch(barcode); else alert('Please enter a barcode'); }

function performBarcodeSearch(barcode) {
    if (!/^[A-Za-z0-9\-_\.\+]+$/.test(barcode)) { alert('Invalid barcode format'); return; }
    document.getElementById('scanResult').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Searching...</div>';
    fetch('?scan_barcode=1&barcode=' + encodeURIComponent(barcode) + '&csrf_token=' + encodeURIComponent(csrfToken))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.found) { displayScannedItem(data.item); addToRecentScans(data.item); clearAllSearchInputs(); }
                else { displayNotFound(data.barcode || barcode); clearAllSearchInputs(); }
            } else { showError(data.error || 'Unknown error'); }
        })
        .catch(error => { console.error('Error:', error); showError('Error scanning barcode'); });
}

function clearAllSearchInputs() {
    const inputs = ['live_barcode_input', 'file_barcode_input', 'handheld_barcode_input'];
    inputs.forEach(id => { const input = document.getElementById(id); if (input) input.value = ''; });
}

document.addEventListener('DOMContentLoaded', function() {
    try { recentScans = JSON.parse(localStorage.getItem('semiRecentScans') || '[]'); if (!Array.isArray(recentScans)) recentScans = []; } catch(e) { recentScans = []; }
    displayRecentScans();
    ['live_barcode_input', 'file_barcode_input', 'handheld_barcode_input'].forEach(id => {
        const input = document.getElementById(id);
        if (input) input.addEventListener('keypress', e => { if (e.key === 'Enter') { if (id === 'live_barcode_input') searchLiveBarcode(); else if (id === 'file_barcode_input') searchFileBarcode(); else if (id === 'handheld_barcode_input') searchHandheldBarcode(); } });
    });
    document.getElementById('fileUploadArea').addEventListener('click', () => document.getElementById('barcodeImage').click());
    document.getElementById('barcodeImage').addEventListener('change', handleImageUpload);
    document.getElementById('fileUploadArea').addEventListener('dragover', e => { e.preventDefault(); e.target.style.borderColor = '#17a2b8'; e.target.style.background = '#e6f7ff'; });
    document.getElementById('fileUploadArea').addEventListener('dragleave', e => { e.preventDefault(); e.target.style.borderColor = '#ccc'; e.target.style.background = 'transparent'; });
    document.getElementById('fileUploadArea').addEventListener('drop', e => { e.preventDefault(); e.target.style.borderColor = '#ccc'; e.target.style.background = 'transparent'; let file = e.dataTransfer.files[0]; if (file && file.type.startsWith('image/')) handleImageFile(file); });
    setTimeout(() => { startScanner(); const input = document.getElementById('live_barcode_input'); if (input) input.focus(); }, 500);
});

function startScanner() { if (!scannerInitialized) initScanner(); else { try { Quagga.start(); } catch(e) { console.error(e); } } document.getElementById('startScannerBtn').style.display = 'none'; document.getElementById('stopScannerBtn').style.display = 'inline-block'; document.getElementById('scanner-status').textContent = 'Scanner running'; }
function stopScanner() { if (Quagga) try { Quagga.stop(); } catch(e) {} document.getElementById('startScannerBtn').style.display = 'inline-block'; document.getElementById('stopScannerBtn').style.display = 'none'; document.getElementById('scanner-status').textContent = 'Scanner stopped'; }

function initScanner() {
    Quagga.CameraAccess.enumerateVideoDevices().then(devices => {
        cameras = devices.filter(device => device.kind === 'videoinput');
        if (cameras.length === 0) { document.getElementById('scanner-status').textContent = 'No cameras found'; return; }
        let select = document.getElementById('camera-select');
        select.innerHTML = '<option value="">Select Camera</option>';
        cameras.forEach((camera, index) => { let option = document.createElement('option'); option.value = camera.deviceId; option.text = camera.label || `Camera ${index + 1}`; select.appendChild(option); });
        if (cameras.length > 0) startScannerWithCamera(cameras[0].deviceId);
    }).catch(err => { console.error(err); document.getElementById('scanner-status').textContent = 'Camera access denied'; });
}

function startScannerWithCamera(deviceId) {
    Quagga.init({ inputStream: { name: "Live", type: "LiveStream", target: document.querySelector('#scanner-container'), constraints: { width: 640, height: 480, facingMode: "environment", deviceId: deviceId } }, decoder: { readers: ["code_128_reader", "ean_reader", "ean_8_reader", "code_39_reader", "codabar_reader", "upc_reader", "upc_e_reader", "i2of5_reader"] }, locate: true, numOfWorkers: 4, frequency: 10 }, function(err) {
        if (err) { console.error(err); document.getElementById('scanner-status').textContent = 'Error initializing scanner'; return; }
        scannerInitialized = true;
        Quagga.start();
        Quagga.onProcessed(result => { if (result && result.codeResult && result.codeResult.code) { let ctx = Quagga.canvas.ctx.overlay; ctx.font = "24px Arial"; ctx.fillStyle = "#17a2b8"; ctx.fillText(result.codeResult.code, 20, 40); } });
        Quagga.onDetected(result => { let code = result.codeResult.code; if (code) { document.getElementById('beep-sound').play().catch(e=>{}); stopScanner(); const input = document.getElementById('live_barcode_input'); if (input) { input.value = code; searchLiveBarcode(); } else performBarcodeSearch(code); document.getElementById('scanner-status').textContent = 'Detected: ' + code; setTimeout(() => { if (document.querySelector('.scanner-type-btn.active').getAttribute('data-scanner') === 'live') startScanner(); }, 2000); } });
    });
}

function switchCamera() { let select = document.getElementById('camera-select'); let deviceId = select.value; if (deviceId) { stopScanner(); setTimeout(() => { startScannerWithCamera(deviceId); startScanner(); }, 500); } }
function handleImageUpload(e) { let file = e.target.files[0]; if (file) handleImageFile(file); }
function handleImageFile(file) { if (file.size > 5*1024*1024) { alert('Image too large'); return; } if (!file.type.match('image.*')) { alert('Please select an image'); return; } let reader = new FileReader(); reader.onload = e => { document.getElementById('previewImg').src = e.target.result; document.getElementById('imagePreview').style.display = 'block'; document.getElementById('fileUploadArea').style.display = 'none'; }; reader.readAsDataURL(file); }
function resetImageUpload() { document.getElementById('imagePreview').style.display = 'none'; document.getElementById('fileUploadArea').style.display = 'block'; document.getElementById('barcodeImage').value = ''; document.getElementById('previewImg').src = ''; const input = document.getElementById('file_barcode_input'); if (input) input.focus(); }
function scanImageFile() { let img = document.getElementById('previewImg'); if (!img.src) return; let loadingDiv = document.createElement('div'); loadingDiv.className = 'loading'; loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i><br>Scanning...'; document.getElementById('imagePreview').appendChild(loadingDiv); Quagga.decodeSingle({ decoder: { readers: ["code_128_reader","ean_reader","ean_8_reader","code_39_reader","codabar_reader","upc_reader","upc_e_reader","i2of5_reader"] }, locate: true, src: img.src }, result => { if (loadingDiv) loadingDiv.remove(); if (result && result.codeResult) { let code = result.codeResult.code; const input = document.getElementById('file_barcode_input'); if (input) { input.value = code; searchFileBarcode(); } else performBarcodeSearch(code); document.getElementById('beep-sound').play().catch(e=>{}); } else { alert('No barcode found'); } }); }

function displayScannedItem(item) {
    currentScannedItem = item;
    let bigUnitDisplay = item.big_unit_display || '—';
    let smallUnitDisplay = item.small_unit_display || '—';
    let html = `<div class="found-item"><div class="item-detail-card"><h3>${escapeHtml(item.article_name)} <span style="background:var(--accent);padding:3px 10px;border-radius:15px;font-size:12px;">Semi-Expendable</span></h3>
        <div class="item-detail-grid">
            <div class="detail-item"><span class="detail-label">Property No.</span><span class="detail-value">${escapeHtml(item.property_no)}</span></div>
            <div class="detail-item"><span class="detail-label">Type</span><span class="detail-value">${escapeHtml(item.type_equipment)}</span></div>
            <div class="detail-item"><span class="detail-label">Category</span><span class="detail-value">${escapeHtml(item.equipment_name)}</span></div>
            <div class="detail-item"><span class="detail-label">Big Unit</span><span class="detail-value">${escapeHtml(bigUnitDisplay)}</span></div>
            <div class="detail-item"><span class="detail-label">Small Unit</span><span class="detail-value">${escapeHtml(smallUnitDisplay)}</span></div>
            <div class="detail-item"><span class="detail-label">Total Quantity</span><span class="detail-value highlight">${item.total_qty || item.quantity} ${escapeHtml(item.uom)}</span></div>
            <div class="detail-item"><span class="detail-label">Unit Value</span><span class="detail-value">${formatCurrency(item.unit_value)}</span></div>
            <div class="detail-item"><span class="detail-label">Total Value</span><span class="detail-value">${formatCurrency((item.unit_value || 0) * (item.total_qty || item.quantity))}</span></div>
            <div class="detail-item"><span class="detail-label">Supplier</span><span class="detail-value">${escapeHtml(item.supplier_name || 'N/A')}</span></div>
            <div class="detail-item"><span class="detail-label">Fund Cluster</span><span class="detail-value">${escapeHtml(item.fund_cluster)}</span></div>
            <div class="detail-item"><span class="detail-label">Condition</span><span class="detail-value">${escapeHtml(item.condition_text)}</span></div>
            <div class="detail-item"><span class="detail-label">Status</span><span class="detail-value"><span class="status-badge ${item.is_issued ? 'issued' : 'available'}">${item.is_issued ? 'Issued' : 'Available'}</span></span></div>
        </div>`;
    if (item.description) html += `<div style="margin:15px 0;padding:10px;background:white;border-radius:5px;"><strong>Description:</strong> ${escapeHtml(item.description)}</div>`;
    html += `<div class="barcode-preview"><img src="generate_barcodeppe.php?code=${encodeURIComponent(item.barcode_data)}&format=png&width=300&height=80" alt="Barcode" onerror="this.style.display='none'"><div style="font-family:monospace;font-size:14px;margin-top:10px;">${escapeHtml(item.barcode_data)}</div></div></div></div>`;
    document.getElementById('scanResult').innerHTML = html;
    document.getElementById('quickActions').style.display = 'block';
}

function displayNotFound(barcode) {
    document.getElementById('scanResult').innerHTML = `<div class="not-found"><i class="fas fa-box-open"></i><h3>Item Not Found</h3><p>No Semi-Expendable item found with barcode: <strong>${escapeHtml(barcode)}</strong></p><div style="display:flex;gap:10px;justify-content:center;"><button class="btn btn-primary" onclick="openAddSemiWithBarcode('${escapeHtml(barcode)}')"><i class="fas fa-plus"></i> Add New</button><button class="btn btn-secondary" onclick="resetScanner()"><i class="fas fa-redo"></i> Scan Again</button></div></div>`;
    document.getElementById('quickActions').style.display = 'none';
}

function showError(message) { document.getElementById('scanResult').innerHTML = `<div class="error-message"><i class="fas fa-exclamation-circle"></i><p>${escapeHtml(message)}</p><button class="btn btn-secondary" onclick="resetScanner()">Try Again</button></div>`; }
function resetScanner() { document.getElementById('scanResult').innerHTML = '<div class="scan-placeholder"><i class="fas fa-box-open"></i><p>Scan a Semi-Expendable barcode to see item details</p></div>'; clearAllSearchInputs(); const activeType = document.querySelector('.scanner-type-btn.active').getAttribute('data-scanner'); if (activeType === 'live') { const input = document.getElementById('live_barcode_input'); if (input) input.focus(); } else if (activeType === 'file') { const input = document.getElementById('file_barcode_input'); if (input) input.focus(); } else if (activeType === 'handheld') { const input = document.getElementById('handheld_barcode_input'); if (input) input.focus(); } }
function addToRecentScans(item) { let existingIndex = recentScans.findIndex(scan => scan.id === item.id); if (existingIndex !== -1) recentScans.splice(existingIndex, 1); recentScans.unshift({ id: item.id, name: item.article_name, barcode: item.barcode_data, time: new Date().toLocaleTimeString() }); if (recentScans.length > 10) recentScans.pop(); try { localStorage.setItem('semiRecentScans', JSON.stringify(recentScans)); } catch(e) {} displayRecentScans(); }
function displayRecentScans() { let html = ''; if (!recentScans.length) html = '<div class="text-muted" style="padding:10px;text-align:center;">No recent scans</div>'; else recentScans.forEach(scan => { html += `<div class="recent-scan-item" onclick="rescanBarcode('${escapeHtml(scan.barcode)}')"><div><div class="item-name">${escapeHtml(scan.name)}</div><div class="item-barcode">${escapeHtml(scan.barcode)}</div></div><div class="scan-time">${escapeHtml(scan.time)}</div></div>`; }); document.getElementById('recentScansList').innerHTML = html; }
function rescanBarcode(barcode) { const activeType = document.querySelector('.scanner-type-btn.active').getAttribute('data-scanner'); if (activeType === 'live') { const input = document.getElementById('live_barcode_input'); if (input) { input.value = barcode; searchLiveBarcode(); } } else if (activeType === 'file') { const input = document.getElementById('file_barcode_input'); if (input) { input.value = barcode; searchFileBarcode(); } } else if (activeType === 'handheld') { const input = document.getElementById('handheld_barcode_input'); if (input) { input.value = barcode; searchHandheldBarcode(); } } else performBarcodeSearch(barcode); }
function clearRecentScans() { if (confirm('Clear recent scan history?')) { recentScans = []; localStorage.setItem('semiRecentScans', JSON.stringify(recentScans)); displayRecentScans(); } }
function editCurrentItem() { if (currentScannedItem) window.location.href = 'semi_expendable.php?edit=' + currentScannedItem.id + '&csrf_token=' + encodeURIComponent(csrfToken); }
function issueCurrentItem() { if (currentScannedItem) window.location.href = 'issue_items.php?item=' + currentScannedItem.id; }
function printCurrentItemBarcode() { if (currentScannedItem) printBarcode(currentScannedItem.barcode_data, currentScannedItem.article_name); }
function viewCurrentItem() { if (currentScannedItem) viewItemDetails(currentScannedItem.id); }
function openAddSemiWithBarcode(barcode) { window.location.href = 'semi_expendable.php?barcode=' + encodeURIComponent(barcode); }
function viewItemDetails(itemId) { document.getElementById('itemDetailsContent').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Loading...</div>'; document.getElementById('itemDetailsModal').style.display = 'block'; fetch('?scan_barcode=1&barcode=' + encodeURIComponent(itemId) + '&csrf_token=' + encodeURIComponent(csrfToken)).then(r=>r.json()).then(data=>{ if(data.success && data.found){ let item=data.item; let content=`<h3>${escapeHtml(item.article_name)}</h3><table style="width:100%">${[['Property No',item.property_no],['Description',item.description],['Type',item.type_equipment],['Category',item.equipment_name],['Quantity',`${item.total_qty||item.quantity} ${item.uom}`],['Unit Value',formatCurrency(item.unit_value)],['Fund Cluster',item.fund_cluster],['Condition',item.condition_text],['Supplier',item.supplier_name],['Remarks',item.remarks]].map(([l,v])=>`<tr><td style="padding:8px 0"><strong>${l}:</strong></td><td>${escapeHtml(v||'N/A')}</td></tr>`).join('')}</table>`; document.getElementById('itemDetailsContent').innerHTML=content; } else document.getElementById('itemDetailsContent').innerHTML='<div class="error-message">Item not found</div>'; }).catch(()=>document.getElementById('itemDetailsContent').innerHTML='<div class="error-message">Error loading details</div>'); }
function closeItemDetailsModal() { document.getElementById('itemDetailsModal').style.display = 'none'; }
function printBarcode(barcodeData, itemName) { let printWindow = window.open('', '_blank'); printWindow.document.write(`<html><head><title>Print Barcode</title><style>body{text-align:center;padding:20px;font-family:Arial}.barcode-container{padding:30px;border:1px dashed #6B8CFF;border-radius:10px}.semi-label{background:#F8B0C0;color:#3A3A3A;padding:5px 15px;border-radius:20px;display:inline-block;margin-bottom:15px}</style></head><body><div class="barcode-container"><div class="semi-label">Semi-Expendable</div><img src="generate_barcodeppe.php?code=${encodeURIComponent(barcodeData)}&format=png&width=400&height=100" alt="Barcode"><div style="margin-top:15px;font-weight:bold">${escapeHtml(itemName)}</div><div style="font-family:monospace;margin-top:10px">${escapeHtml(barcodeData)}</div></div><script>window.onload=function(){setTimeout(function(){window.print();window.close()},500)}<\/script></body></html>`); printWindow.document.close(); }
function escapeHtml(text) { if (!text) return ''; let div = document.createElement('div'); div.textContent = text; return div.innerHTML; }
function formatCurrency(amount) { if (amount === undefined || amount === null) return '₱0.00'; return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,'); }
window.addEventListener('beforeunload', function() { if (Quagga) try { Quagga.stop(); } catch(e) {} });
window.onclick = function(event) { let modal = document.getElementById('itemDetailsModal'); if (event.target == modal) closeItemDetailsModal(); }
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>