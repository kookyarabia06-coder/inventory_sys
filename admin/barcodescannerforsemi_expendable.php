<?php
/**
 * Semi-Expendable Barcode Scanner Page
 * Scan barcodes to quickly find and manage Semi-Expendable items
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
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
    die("Database connection failed. Please try again later.");
}

// Include barcode library
require_once $root_path . '/vendor/autoload.php';
use Picqer\Barcode\BarcodeGeneratorPNG;

// Require admin role
requireRole('admin' || 'superadmin' || 'supply');

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Semi-Expendable Barcode Scanner';
$page_description = 'Scan barcodes to quickly find Semi-Expendable items';

// Rate limiting configuration
$rate_limit_max = 30; // Max requests per minute
$rate_limit_window = 60; // Time window in seconds

// Handle barcode lookup via AJAX
if (isset($_GET['scan_barcode'])) {
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
        
        // Reset counter if window expired
        if (time() - $_SESSION[$rate_key]['first_attempt'] > $rate_limit_window) {
            $_SESSION[$rate_key] = [
                'count' => 0,
                'first_attempt' => time()
            ];
        }
        
        // Check rate limit
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
        if (!preg_match('/^[A-Za-z0-9\-_\.\+\s]+$/', $barcode)) {
            echo json_encode(['error' => 'Invalid barcode format']);
            exit;
        }
        
        // Sanitize and normalize input
        $barcode = sanitize($barcode);
        $barcode = trim($barcode); // Remove leading/trailing whitespace
        $barcode = preg_replace('/\s+/', '', $barcode); // Remove all internal whitespace
        
        // Search for the barcode in Semi-Expendable inventory with prepared statement
        $stmt = $conn->prepare("
            SELECT i.*, e.name as equipment_name, s.name as section_name,
                   d.name as department_name, b.name as building_name,
                   CONCAT(ap.firstname, ' ', ap.lastname) as approver_name,
                   CONCAT(vr.firstname, ' ', vr.lastname) as verifier_name,
                   CONCAT(al.firstname, ' ', al.lastname) as allocatee_name,
                   CONCAT(cr.firstname, ' ', cr.lastname) as created_by_name
            FROM inventory i
            LEFT JOIN equipment e ON i.equipment_id = e.id
            LEFT JOIN sections s ON i.section_id = s.id
            LEFT JOIN departments d ON s.department_id = d.id
            LEFT JOIN buildings b ON d.building_id = b.id
            LEFT JOIN users ap ON i.approved_by = ap.id
            LEFT JOIN users vr ON i.verified_by = vr.id
            LEFT JOIN users al ON i.allocate_to = al.id
            LEFT JOIN users cr ON i.created_by = cr.id
            WHERE TRIM(REPLACE(REPLACE(REPLACE(i.barcode_data, ' ', ''), '\t', ''), '\n', '')) = TRIM(REPLACE(REPLACE(REPLACE(?, ' ', ''), '\t', ''), '\n', ''))
            AND (i.category = 'Semi-Expendable' OR i.type_equipment = 'Semi-Expendable')
            LIMIT 1
        ");
        
        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }
        
        $stmt->bind_param("s", $barcode);
        
        if (!$stmt->execute()) {
            throw new Exception("Database execute error: " . $stmt->error);
        }
        
        $result = $stmt->get_result();
        
        if ($result && $row = $result->fetch_assoc()) {
            // Check if this is a multiple item
            $is_multiple = preg_match('/-\d{3}$/', $row['property_no']);
            
            // calculate total quantity when part of a multiple set
            $total_qty = (float)($row['qty_physical_count'] ?? 0);
            if ($is_multiple) {
                $base = preg_replace('/-\d{3}$/', '', $row['property_no']);
                $sumStmt = $conn->prepare("SELECT SUM(qty_physical_count) as total FROM inventory WHERE property_no LIKE CONCAT(?, '-%')");
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
            
            // Get issued status safely
            $is_issued = checkIfIssued($conn, $row['id']);
            
            echo json_encode([
                'success' => true,
                'found' => true,
                'item' => [
                    'id' => (int)$row['id'],
                    'article_name' => htmlspecialchars($row['article_name'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'property_no' => htmlspecialchars($row['property_no'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'description' => htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'barcode_data' => htmlspecialchars($row['barcode_data'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'category' => htmlspecialchars($row['category'] ?? 'Semi-Expendable', ENT_QUOTES, 'UTF-8'),
                    'type_equipment' => htmlspecialchars($row['type_equipment'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'equipment_name' => htmlspecialchars($row['equipment_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'quantity' => (float)($row['qty_physical_count'] ?? 0),
                    'total_qty' => $total_qty,
                    'uom' => htmlspecialchars($row['uom'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'unit_value' => (float)($row['unit_value'] ?? 0),
                    'location' => $row['section_name'] ? htmlspecialchars($row['section_name'], ENT_QUOTES, 'UTF-8') . ($row['department_name'] ? ' - ' . htmlspecialchars($row['department_name'], ENT_QUOTES, 'UTF-8') : '') : 'N/A',
                    'building' => htmlspecialchars($row['building_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'condition_text' => htmlspecialchars($row['condition_text'] ?? 'Good', ENT_QUOTES, 'UTF-8'),
                    'fund_cluster' => htmlspecialchars($row['fund_cluster'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'certified_correct' => htmlspecialchars($row['certified_correct'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'approver_name' => htmlspecialchars($row['approver_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'verifier_name' => htmlspecialchars($row['verifier_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'allocatee_name' => htmlspecialchars($row['allocatee_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'created_by_name' => htmlspecialchars($row['created_by_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
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

// Helper function to check if item is issued
function checkIfIssued($conn, $item_id) {
    if (!$conn || !is_numeric($item_id)) {
        return false;
    }
    
    $stmt = $conn->prepare("SELECT id FROM equipment_issuance WHERE inventory_id = ? AND status = 'issued' LIMIT 1");
    
    if (!$stmt) {
        error_log("Failed to prepare issued check: " . $conn->error);
        return false;
    }
    
    $stmt->bind_param("i", $item_id);
    
    if (!$stmt->execute()) {
        error_log("Failed to execute issued check: " . $stmt->error);
        $stmt->close();
        return false;
    }
    
    $result = $stmt->get_result();
    $found = $result && $result->num_rows > 0;
    $stmt->close();
    
    return $found;
}

include INCLUDE_PATH . '/header.php';
?>

<!-- Include QuaggaJS for barcode scanning -->
<script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>

<style>
:root {
    --primary: #6B8CFF;        /* Deeper Periwinkle - Main brand color */
    --secondary: #8FB5FF;       /* Medium Blue - Secondary elements */
    --accent: #F8B0C0;          /* Muted Pink - Highlights, buttons */
    --accent-light: #FFD8E0;    /* Light Pink - Soft highlights */
    --success-light: #C5E8C5;   /* Muted Mint - Success backgrounds */
    --light: #F0F0F0;           /* Light Gray - Page background */
    --white: #FFFFFF;           /* White - Cards, containers */
    --border-light: #E0E0E0;    /* Light Gray for borders */
    --text-primary: #3A3A3A;    /* Dark gray for main text */
    --text-secondary: #6B6B6B;  /* Medium gray for secondary text */
    --text-muted: #9E9E9E;      /* Light gray for muted text */
    --text-light: #FFFFFF;      /* White text for dark backgrounds */
    --success: #4CAF50;
    --danger: #f44336;
    --info: #8FB5FF;
}

.scanner-container {
    padding: 20px;
}

.scanner-header {
    text-align: center;
    margin-bottom: 30px;
}

.scanner-header h2 {
    color: var(--primary);
    font-size: 28px;
    margin-bottom: 10px;
}

.scanner-header h2 i {
    color: var(--accent);
    margin-right: 10px;
}

.scanner-main {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

/* Left Column Styles */
.scanner-left {
    background: var(--white);
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.scanner-card h3 {
    color: var(--primary);
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-light);
}

.scanner-card h3 i {
    color: var(--accent);
    margin-right: 10px;
}

/* Search bar styling - No background, at top */
.search-bar-wrapper {
    margin-bottom: 20px;
}
.search-input-section {
    margin-bottom: 15px;
}
.search-input-section label {
    display: block;
    margin-bottom: 8px;
    color: var(--primary);
    font-weight: 500;
}
.search-input-section .input-group {
    display: flex;
    gap: 10px;
}
.search-input-section .form-control {
    flex: 1;
    padding: 12px;
    border: 2px solid var(--border-light);
    border-radius: 8px;
    font-size: 16px;
    transition: all 0.3s;
    background: transparent;
}
.search-input-section .form-control:focus {
    border-color: var(--primary);
    outline: none;
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

/* Scanner Type Selector as Button Group */
.scanner-type-selector {
    margin-bottom: 20px;
}
.button-group {
    display: flex;
    gap: 10px;
    padding: 10px 0;
    flex-wrap: wrap;
}
.scanner-type-btn {
    flex: 1;
    background: var(--light);
    border: 2px solid var(--border-light);
    border-radius: 30px;
    padding: 10px 15px;
    font-size: 14px;
    font-weight: 600;
    color: var(--text-secondary);
    cursor: pointer;
    transition: all 0.2s ease;
    text-align: center;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}
.scanner-type-btn i {
    font-size: 16px;
}
.scanner-type-btn.active {
    background: var(--accent);
    border-color: var(--accent);
    color: var(--text-primary);
    box-shadow: 0 2px 8px rgba(248, 176, 192, 0.3);
}
.scanner-type-btn:hover:not(.active) {
    background: var(--accent-light);
    border-color: var(--accent);
    transform: translateY(-2px);
}

.camera-container {
    background: #000;
    border-radius: 8px;
    overflow: hidden;
    margin: 10px 0;
    border: 2px solid var(--secondary);
    position: relative;
}

#scanner-container {
    width: 100%;
    height: 300px;
    background: #000;
}

.scanner-status {
    position: absolute;
    bottom: 10px;
    left: 10px;
    right: 10px;
    background: rgba(0,0,0,0.7);
    color: var(--text-light);
    padding: 5px 10px;
    border-radius: 5px;
    font-size: 12px;
    text-align: center;
    z-index: 10;
}

.camera-controls {
    display: flex;
    gap: 10px;
    margin-top: 10px;
    align-items: center;
}

.scanner-instructions {
    margin-top: 15px;
    padding: 10px;
    background: var(--accent-light);
    border-radius: 5px;
    color: var(--text-secondary);
    font-size: 13px;
}

.scanner-instructions i {
    color: var(--accent);
}

/* File Upload Area */
.file-upload-area {
    border: 3px dashed var(--border-light);
    border-radius: 10px;
    padding: 40px 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    margin: 20px 0;
}

.file-upload-area:hover {
    border-color: var(--primary);
    background: var(--accent-light);
}

.file-upload-area i {
    font-size: 48px;
    color: var(--text-muted);
    margin-bottom: 10px;
}

.file-upload-area p {
    color: var(--text-secondary);
    margin-bottom: 15px;
}

.image-preview {
    text-align: center;
    margin-top: 20px;
}

.image-preview img {
    max-width: 100%;
    max-height: 200px;
    border-radius: 5px;
    margin-bottom: 10px;
}

/* Recent Scans */
.recent-scans {
    margin-top: 30px;
}

.recent-scans h4 {
    color: var(--primary);
    margin-bottom: 15px;
}

.recent-scans-list {
    max-height: 200px;
    overflow-y: auto;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    padding: 10px;
}

.recent-scan-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px;
    border-bottom: 1px solid var(--border-light);
    cursor: pointer;
    transition: all 0.3s;
}

.recent-scan-item:last-child {
    border-bottom: none;
}

.recent-scan-item:hover {
    background-color: var(--light);
}

.recent-scan-item .item-name {
    font-weight: 500;
    color: var(--primary);
}

.recent-scan-item .item-barcode {
    font-family: monospace;
    font-size: 12px;
    color: var(--accent);
}

.recent-scan-item .scan-time {
    font-size: 11px;
    color: var(--text-muted);
}

/* Right Column Styles */
.scanner-right {
    background: var(--white);
    border-radius: 10px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.scan-result {
    min-height: 300px;
}

.scan-placeholder {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.scan-placeholder i {
    font-size: 64px;
    margin-bottom: 20px;
    color: var(--border-light);
}

.scan-placeholder p {
    font-size: 16px;
}

/* Found Item Styles */
.found-item {
    animation: fadeIn 0.5s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.item-detail-card {
    background: var(--light);
    border-radius: 10px;
    padding: 20px;
    margin-bottom: 20px;
    border-left: 4px solid var(--accent);
}

.item-detail-card h3 {
    color: var(--primary);
    margin-bottom: 15px;
    font-size: 20px;
}

.item-detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
    margin-bottom: 20px;
}

.detail-item {
    display: flex;
    flex-direction: column;
}

.detail-label {
    font-size: 12px;
    color: var(--text-muted);
    margin-bottom: 3px;
}

.detail-value {
    font-size: 16px;
    font-weight: 500;
    color: var(--primary);
}

.detail-value.highlight {
    color: var(--accent);
    font-size: 18px;
}

.barcode-preview {
    text-align: center;
    padding: 15px;
    background: var(--white);
    border-radius: 8px;
    margin: 15px 0;
}

.barcode-preview img {
    max-width: 100%;
    height: auto;
}

.status-badge {
    display: inline-block;
    padding: 5px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.status-badge.available {
    background-color: var(--success-light);
    color: var(--success);
}

.status-badge.issued {
    background-color: var(--secondary);
    color: var(--text-primary);
}

/* Quick Actions */
.quick-actions {
    margin-top: 20px;
    padding-top: 20px;
    border-top: 2px solid var(--accent-light);
}

.quick-actions h4 {
    color: var(--primary);
    margin-bottom: 15px;
}

.quick-actions .action-buttons {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 10px;
}

/* Not Found Styles */
.not-found {
    text-align: center;
    padding: 40px;
    color: var(--danger);
}

.not-found i {
    font-size: 48px;
    margin-bottom: 15px;
}

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
}

.modal-content {
    background-color: var(--white);
    margin: 10% auto;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.2);
    position: relative;
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border-light);
}

.modal-header h2 {
    color: var(--primary);
}

.modal-close {
    font-size: 24px;
    font-weight: bold;
    cursor: pointer;
    color: var(--text-muted);
}

.modal-close:hover {
    color: var(--accent);
}

.modal-footer {
    margin-top: 20px;
    padding-top: 10px;
    border-top: 1px solid var(--border-light);
    text-align: right;
}

/* Button Styles */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.3s;
}

.btn-sm {
    padding: 5px 10px;
    font-size: 12px;
}

.btn-primary {
    background-color: var(--accent);
    color: var(--text-primary);
}

.btn-primary:hover {
    background-color: #e69eb0;
    transform: translateY(-2px);
    box-shadow: 0 3px 8px rgba(248, 176, 192, 0.3);
}

.btn-success {
    background-color: var(--success-light);
    color: var(--success);
}

.btn-success:hover {
    background-color: #b5d8b5;
    transform: translateY(-2px);
    box-shadow: 0 3px 8px rgba(76, 175, 80, 0.2);
}

.btn-info {
    background-color: var(--info);
    color: var(--text-light);
}

.btn-info:hover {
    background-color: #7a9fe6;
    transform: translateY(-2px);
    box-shadow: 0 3px 8px rgba(143, 181, 255, 0.3);
}

.btn-secondary {
    background-color: var(--secondary);
    color: var(--text-light);
}

.btn-secondary:hover {
    background-color: #7a9fe6;
    transform: translateY(-2px);
    box-shadow: 0 3px 8px rgba(143, 181, 255, 0.3);
}

/* Form controls */
.form-control {
    padding: 10px;
    border: 1px solid var(--border-light);
    border-radius: 5px;
    font-size: 14px;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px rgba(107, 140, 255, 0.1);
}

/* Loading indicator */
.loading {
    text-align: center;
    padding: 40px;
    color: var(--text-muted);
}

.loading i {
    font-size: 32px;
    margin-bottom: 10px;
    color: var(--primary);
}

/* Error message */
.error-message {
    background-color: #ffebee;
    color: var(--danger);
    padding: 12px;
    border-radius: 5px;
    margin: 10px 0;
    text-align: center;
}

/* PPE Badge */
.ppe-badge {
    display: inline-block;
    background-color: var(--accent);
    color: var(--text-primary);
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.ppe-badge i {
    margin-right: 5px;
    font-size: 10px;
}

/* Responsive */
@media (max-width: 768px) {
    .scanner-main {
        grid-template-columns: 1fr;
    }
    
    .camera-controls {
        flex-direction: column;
    }
    
    .action-buttons {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="scanner-container">
    <div class="scanner-header">
        <h2><i class="fas fa-box-open"></i> Semi-Expendable Barcode Scanner</h2>
        <p>Scan barcodes to instantly find and manage Semi-Expendable items</p>
        <span class="semi-badge">Semi-Expendable Only</span>
    </div>

    <div class="scanner-main">
        <!-- Left Column - Scanner -->
        <div class="scanner-left">
            <div class="scanner-card">
                <h3><i class="fas fa-camera"></i> Scan Semi-Expendable Barcode</h3>
                
                <!-- Search Bar Wrapper - At the top, before buttons -->
                <div class="search-bar-wrapper">
                    <!-- Search Bar for Live Camera Section -->
                    <div id="liveSearchSection" class="search-input-section">
                        <label for="live_barcode_input"><i class="fas fa-search"></i> Search by Barcode:</label>
                        <div class="input-group">
                            <input type="text" id="live_barcode_input" class="form-control" placeholder="Type barcode and press Enter...">
                            <button class="btn btn-primary" onclick="searchLiveBarcode()">
                                <i class="fas fa-search"></i> Find
                            </button>
                        </div>
                    </div>

                    <!-- Search Bar for Upload Image Section -->
                    <div id="fileSearchSection" class="search-input-section" style="display: none;">
                        <label for="file_barcode_input"><i class="fas fa-search"></i> Search by Barcode:</label>
                        <div class="input-group">
                            <input type="text" id="file_barcode_input" class="form-control" placeholder="Type barcode and press Enter...">
                            <button class="btn btn-primary" onclick="searchFileBarcode()">
                                <i class="fas fa-search"></i> Find
                            </button>
                        </div>
                    </div>

                    <!-- Search Bar for Handheld Scanner Section -->
                    <div id="handheldSearchSection" class="search-input-section" style="display: none;">
                        <label for="handheld_barcode_input"><i class="fas fa-search"></i> Enter Barcode Manually:</label>
                        <div class="input-group">
                            <input type="text" id="handheld_barcode_input" class="form-control" placeholder="Type barcode and press Enter...">
                            <button class="btn btn-primary" onclick="searchHandheldBarcode()">
                                <i class="fas fa-search"></i> Find
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Scanner Type Selection as Button Group -->
                <div class="scanner-type-selector">
                    <div class="button-group">
                        <button type="button" class="scanner-type-btn active" data-scanner="live" onclick="setScannerType('live')">
                            <i class="fas fa-video"></i> Live Camera
                        </button>
                        <button type="button" class="scanner-type-btn" data-scanner="file" onclick="setScannerType('file')">
                            <i class="fas fa-upload"></i> Upload Image
                        </button>
                        <button type="button" class="scanner-type-btn" data-scanner="handheld" onclick="setScannerType('handheld')">
                            <i class="fas fa-keyboard"></i> Handheld Scanner
                        </button>
                    </div>
                </div>

                <!-- Live Camera Scanner -->
                <div id="liveScannerSection" class="camera-section">
                    <div class="camera-container">
                        <div id="scanner-container" style="width: 100%; height: 300px; background: #000; position: relative;"></div>
                        <div id="scanner-status" class="scanner-status">Starting camera...</div>
                    </div>
                    <div class="camera-controls">
                        <button class="btn btn-secondary" onclick="startScanner()" id="startScannerBtn" style="display: none;">
                            <i class="fas fa-play"></i> Start Scanner
                        </button>
                        <button class="btn btn-secondary" onclick="stopScanner()" id="stopScannerBtn">
                            <i class="fas fa-stop"></i> Stop Scanner
                        </button>
                        <select id="camera-select" class="form-control" onchange="switchCamera()">
                            <option value="">Select Camera</option>
                        </select>
                    </div>
                    <div class="scanner-instructions">
                        <p><i class="fas fa-info-circle"></i> Position the barcode in the center of the red line. The scanner will automatically detect Semi-Expendable barcodes.</p>
                    </div>
                </div>

                <!-- File Upload Scanner -->
                <div id="fileScannerSection" class="file-section" style="display: none;">
                    <div class="file-upload-area" id="fileUploadArea">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop a barcode image or click to upload</p>
                        <input type="file" id="barcodeImage" accept="image/*" style="display: none;">
                        <button class="btn btn-secondary" onclick="document.getElementById('barcodeImage').click()">
                            <i class="fas fa-folder-open"></i> Browse
                        </button>
                    </div>
                    <div id="imagePreview" class="image-preview" style="display: none;">
                        <img id="previewImg" src="" alt="Preview">
                        <div style="display: flex; gap: 10px; justify-content: center;">
                            <button class="btn btn-primary" onclick="scanImageFile()">
                                <i class="fas fa-search"></i> Scan Image
                            </button>
                            <button class="btn btn-secondary" onclick="resetImageUpload()">
                                <i class="fas fa-undo"></i> Upload Another
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Recent Semi-Expendable Scans -->
                <div class="recent-scans">
                    <h4><i class="fas fa-history"></i> Recent Semi-Expendable Scans</h4>
                    <div id="recentScansList" class="recent-scans-list">
                        <!-- Recent scans will appear here -->
                    </div>
                    <button class="btn btn-sm btn-secondary" onclick="clearRecentScans()" style="margin-top: 10px; width: 100%;">
                        <i class="fas fa-trash"></i> Clear History
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Column - Results -->
        <div class="scanner-right">
            <div id="scanResult" class="scan-result">
                <!-- Scan results will appear here -->
                <div class="scan-placeholder">
                    <i class="fas fa-box-open"></i>
                    <p>Scan a Semi-Expendable barcode to see item details</p>
                </div>
            </div>

            <!-- Quick Actions for Scanned Semi-Expendable Item -->
            <div id="quickActions" class="quick-actions" style="display: none;">
                <h4><i class="fas fa-bolt"></i> Semi-Expendable Quick Actions</h4>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="editCurrentItem()">
                        <i class="fas fa-edit"></i> Edit Item
                    </button>
                    <button class="btn btn-success" onclick="issueCurrentItem()">
                        <i class="fas fa-hand-holding"></i> Issue Item
                    </button>
                    <button class="btn btn-info" onclick="printCurrentItemBarcode()">
                        <i class="fas fa-print"></i> Print Barcode
                    </button>
                    <button class="btn btn-secondary" onclick="viewCurrentItem()">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Item Details Modal -->
<div id="itemDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Semi-Expendable Item Details</h2>
            <span class="modal-close" onclick="closeItemDetailsModal()">&times;</span>
        </div>
        <div class="modal-body" id="itemDetailsContent">
            <!-- Details will be loaded here -->
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeItemDetailsModal()">Close</button>
        </div>
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

// Function to set scanner type with button group
function setScannerType(type) {
    // Update active state on buttons
    document.querySelectorAll('.scanner-type-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-scanner') === type) {
            btn.classList.add('active');
        }
    });
    
    // Call existing toggle function
    toggleScannerType(type);
}

// Toggle scanner type
function toggleScannerType(type) {
    if (type === 'live') {
        document.getElementById('liveScannerSection').style.display = 'block';
        document.getElementById('fileScannerSection').style.display = 'none';
        document.getElementById('liveSearchSection').style.display = 'block';
        document.getElementById('fileSearchSection').style.display = 'none';
        document.getElementById('handheldSearchSection').style.display = 'none';
        // Start camera if not already running
        if (scannerInitialized) {
            startScanner();
        } else {
            initScanner();
        }
        // Focus on live barcode input
        setTimeout(function() {
            const input = document.getElementById('live_barcode_input');
            if (input) input.focus();
        }, 100);
    } else if (type === 'file') {
        document.getElementById('liveScannerSection').style.display = 'none';
        document.getElementById('fileScannerSection').style.display = 'block';
        document.getElementById('liveSearchSection').style.display = 'none';
        document.getElementById('fileSearchSection').style.display = 'block';
        document.getElementById('handheldSearchSection').style.display = 'none';
        stopScanner();
        // Focus on file barcode input
        setTimeout(function() {
            const input = document.getElementById('file_barcode_input');
            if (input) input.focus();
        }, 100);
    } else if (type === 'handheld') {
        document.getElementById('liveScannerSection').style.display = 'none';
        document.getElementById('fileScannerSection').style.display = 'none';
        document.getElementById('liveSearchSection').style.display = 'none';
        document.getElementById('fileSearchSection').style.display = 'none';
        document.getElementById('handheldSearchSection').style.display = 'block';
        stopScanner();
        // Focus on handheld barcode input
        setTimeout(function() {
            const input = document.getElementById('handheld_barcode_input');
            if (input) input.focus();
        }, 100);
    }
}

// Search functions for each section
function searchLiveBarcode() {
    const barcode = document.getElementById('live_barcode_input').value.trim();
    if (barcode) {
        performBarcodeSearch(barcode);
    } else {
        alert('Please enter a barcode');
    }
}

function searchFileBarcode() {
    const barcode = document.getElementById('file_barcode_input').value.trim();
    if (barcode) {
        performBarcodeSearch(barcode);
    } else {
        alert('Please enter a barcode');
    }
}

function searchHandheldBarcode() {
    const barcode = document.getElementById('handheld_barcode_input').value.trim();
    if (barcode) {
        performBarcodeSearch(barcode);
    } else {
        alert('Please enter a barcode');
    }
}

// Main search function
function performBarcodeSearch(barcode) {
    if (!validateBarcode(barcode)) {
        alert('Invalid barcode format. Please use only letters, numbers, and common symbols.');
        return;
    }
    
    document.getElementById('scanResult').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Searching for Semi-Expendable item...</div>';
    
    fetch('?scan_barcode=1&barcode=' + encodeURIComponent(barcode) + '&csrf_token=' + encodeURIComponent(csrfToken))
        .then(response => {
            if (!response.ok) {
                if (response.status === 429) {
                    throw new Error('Rate limit exceeded. Please wait a moment.');
                }
                throw new Error('Server error: ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                if (data.found) {
                    displayScannedItem(data.item);
                    addToRecentScans(data.item);
                    // Clear the search input after successful scan
                    clearAllSearchInputs();
                } else {
                    displayNotFound(data.barcode || barcode);
                    clearAllSearchInputs();
                }
            } else {
                showError(data.error || 'Unknown error occurred');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError(error.message || 'Error scanning barcode');
        });
}

// Clear all search inputs
function clearAllSearchInputs() {
    const liveInput = document.getElementById('live_barcode_input');
    const fileInput = document.getElementById('file_barcode_input');
    const handheldInput = document.getElementById('handheld_barcode_input');
    if (liveInput) liveInput.value = '';
    if (fileInput) fileInput.value = '';
    if (handheldInput) handheldInput.value = '';
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Load recent scans from localStorage
    try {
        recentScans = JSON.parse(localStorage.getItem('semiRecentScans') || '[]');
        if (!Array.isArray(recentScans)) {
            recentScans = [];
        }
    } catch (e) {
        console.error('Error loading recent scans:', e);
        recentScans = [];
    }
    
    displayRecentScans();
    
    // Setup enter key handlers for search inputs
    const liveInput = document.getElementById('live_barcode_input');
    const fileInput = document.getElementById('file_barcode_input');
    const handheldInput = document.getElementById('handheld_barcode_input');
    
    if (liveInput) {
        liveInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchLiveBarcode();
            }
        });
    }
    
    if (fileInput) {
        fileInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchFileBarcode();
            }
        });
    }
    
    if (handheldInput) {
        handheldInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                searchHandheldBarcode();
            }
        });
    }
    
    // File upload area click handler
    document.getElementById('fileUploadArea').addEventListener('click', function() {
        document.getElementById('barcodeImage').click();
    });
    
    // File input change handler
    document.getElementById('barcodeImage').addEventListener('change', handleImageUpload);
    
    // Drag and drop handlers
    document.getElementById('fileUploadArea').addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = '#17a2b8';
        this.style.background = '#e6f7ff';
    });
    
    document.getElementById('fileUploadArea').addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.style.borderColor = '#ccc';
        this.style.background = 'transparent';
    });
    
    document.getElementById('fileUploadArea').addEventListener('drop', function(e) {
        e.preventDefault();
        this.style.borderColor = '#ccc';
        this.style.background = 'transparent';
        
        let file = e.dataTransfer.files[0];
        if (file && file.type.startsWith('image/')) {
            handleImageFile(file);
        }
    });
    
    // Auto-start camera after a delay
    setTimeout(function() {
        startScanner();
        // Focus on live barcode input
        const liveInput = document.getElementById('live_barcode_input');
        if (liveInput) liveInput.focus();
    }, 500);
});

// Start scanner
function startScanner() {
    if (!scannerInitialized) {
        initScanner();
    } else {
        try {
            Quagga.start();
        } catch (e) {
            console.error('Error starting scanner:', e);
            document.getElementById('scanner-status').textContent = 'Error starting scanner';
        }
    }
    
    document.getElementById('startScannerBtn').style.display = 'none';
    document.getElementById('stopScannerBtn').style.display = 'inline-block';
    document.getElementById('scanner-status').textContent = 'Scanner running - position barcode in view';
}

// Stop scanner
function stopScanner() {
    if (Quagga) {
        try {
            Quagga.stop();
        } catch (e) {
            console.error('Error stopping scanner:', e);
        }
    }
    
    document.getElementById('startScannerBtn').style.display = 'inline-block';
    document.getElementById('stopScannerBtn').style.display = 'none';
    document.getElementById('scanner-status').textContent = 'Scanner stopped';
}

// Initialize Quagga scanner
function initScanner() {
    Quagga.CameraAccess.enumerateVideoDevices()
        .then(function(devices) {
            cameras = devices.filter(device => device.kind === 'videoinput');
            
            if (cameras.length === 0) {
                document.getElementById('scanner-status').textContent = 'No cameras found';
                return;
            }
            
            let select = document.getElementById('camera-select');
            select.innerHTML = '<option value="">Select Camera</option>';
            
            cameras.forEach((camera, index) => {
                let option = document.createElement('option');
                option.value = camera.deviceId;
                option.text = camera.label || `Camera ${index + 1}`;
                select.appendChild(option);
            });
            
            if (cameras.length > 0) {
                startScannerWithCamera(cameras[0].deviceId);
            }
        })
        .catch(function(err) {
            console.error('Camera enumeration error:', err);
            document.getElementById('scanner-status').textContent = 'Camera access denied or not available';
        });
}

// Start scanner with specific camera
function startScannerWithCamera(deviceId) {
    Quagga.init({
        inputStream: {
            name: "Live",
            type: "LiveStream",
            target: document.querySelector('#scanner-container'),
            constraints: {
                width: 640,
                height: 480,
                facingMode: "environment",
                deviceId: deviceId
            },
        },
        decoder: {
            readers: [
                "code_128_reader",
                "ean_reader",
                "ean_8_reader",
                "code_39_reader",
                "code_39_vin_reader",
                "codabar_reader",
                "upc_reader",
                "upc_e_reader",
                "i2of5_reader"
            ]
        },
        locate: true,
        numOfWorkers: 4,
        frequency: 10
    }, function(err) {
        if (err) {
            console.error('Quagga init error:', err);
            document.getElementById('scanner-status').textContent = 'Error initializing scanner: ' + (err.message || 'Unknown error');
            return;
        }
        
        scannerInitialized = true;
        Quagga.start();
        
        Quagga.onProcessed(function(result) {
            let drawingCtx = Quagga.canvas.ctx.overlay;
            let drawingCanvas = Quagga.canvas.dom.overlay;
            
            if (result) {
                if (result.box) {
                    Quagga.ImageDebug.drawPath(result.box, {x: 0, y: 1}, drawingCtx, {color: "#17a2b8", lineWidth: 3});
                }
                
                if (result.codeResult && result.codeResult.code) {
                    drawingCtx.font = "24px Arial";
                    drawingCtx.fillStyle = "#17a2b8";
                    drawingCtx.fillText(result.codeResult.code, 20, 40);
                }
            }
        });
        
        Quagga.onDetected(function(result) {
            let code = result.codeResult.code;
            if (code) {
                document.getElementById('beep-sound').play().catch(e => console.log('Audio play failed:', e));
                
                stopScanner();
                
                // Set the value in the live search input and perform search
                const liveInput = document.getElementById('live_barcode_input');
                if (liveInput) {
                    liveInput.value = code;
                    searchLiveBarcode();
                } else {
                    performBarcodeSearch(code);
                }
                
                document.getElementById('scanner-status').textContent = 'Semi-Expendable Barcode detected: ' + code;
                
                setTimeout(() => {
                    if (document.querySelector('.scanner-type-btn.active').getAttribute('data-scanner') === 'live') {
                        startScanner();
                    }
                }, 2000);
            }
        });
    });
}

// Switch camera
function switchCamera() {
    let select = document.getElementById('camera-select');
    let deviceId = select.value;
    
    if (deviceId) {
        stopScanner();
        setTimeout(() => {
            startScannerWithCamera(deviceId);
            startScanner();
        }, 500);
    }
}

// Handle image upload
function handleImageUpload(e) {
    let file = e.target.files[0];
    if (file) {
        handleImageFile(file);
    }
}

// Handle image file
function handleImageFile(file) {
    if (file.size > 5 * 1024 * 1024) {
        alert('Image too large. Please choose an image under 5MB.');
        return;
    }
    
    if (!file.type.match('image.*')) {
        alert('Please select a valid image file.');
        return;
    }
    
    let reader = new FileReader();
    
    reader.onload = function(e) {
        let img = document.getElementById('previewImg');
        img.src = e.target.result;
        document.getElementById('imagePreview').style.display = 'block';
        document.getElementById('fileUploadArea').style.display = 'none';
    };
    
    reader.onerror = function() {
        alert('Error reading file. Please try again.');
    };
    
    reader.readAsDataURL(file);
}

// Reset image upload
function resetImageUpload() {
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('fileUploadArea').style.display = 'block';
    document.getElementById('barcodeImage').value = '';
    document.getElementById('previewImg').src = '';
    // Focus back on file search input
    const fileInput = document.getElementById('file_barcode_input');
    if (fileInput) fileInput.focus();
}

// Scan image file
function scanImageFile() {
    let img = document.getElementById('previewImg');
    if (!img.src) return;
    
    let loadingDiv = document.createElement('div');
    loadingDiv.className = 'loading';
    loadingDiv.innerHTML = '<i class="fas fa-spinner fa-spin"></i><br>Scanning image...';
    document.getElementById('imagePreview').appendChild(loadingDiv);
    
    Quagga.decodeSingle({
        decoder: {
            readers: [
                "code_128_reader",
                "ean_reader",
                "ean_8_reader",
                "code_39_reader",
                "code_39_vin_reader",
                "codabar_reader",
                "upc_reader",
                "upc_e_reader",
                "i2of5_reader"
            ]
        },
        locate: true,
        src: img.src
    }, function(result) {
        if (loadingDiv && loadingDiv.remove) {
            loadingDiv.remove();
        }
        
        if (result && result.codeResult) {
            let code = result.codeResult.code;
            // Set the value in the file search input and perform search
            const fileInput = document.getElementById('file_barcode_input');
            if (fileInput) {
                fileInput.value = code;
                searchFileBarcode();
            } else {
                performBarcodeSearch(code);
            }
            document.getElementById('beep-sound').play().catch(e => console.log('Audio play failed:', e));
        } else {
            alert('No barcode found in the image. Please try another image.');
        }
    });
}

// Validate barcode format
function validateBarcode(barcode) {
    return /^[A-Za-z0-9\-_\.\+\s]+$/.test(barcode);
}

// Display scanned item
function displayScannedItem(item) {
    currentScannedItem = item;
    
    let html = `
        <div class="found-item">
            <div class="item-detail-card">
                <h3>${escapeHtml(item.article_name)} <span class="semi-badge">Semi-Expendable</span></h3>
                
                <div class="item-detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Property No.</span>
                        <span class="detail-value">${escapeHtml(item.property_no)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Type</span>
                        <span class="detail-value">${escapeHtml(item.type_equipment || 'N/A')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Equipment</span>
                        <span class="detail-value">${escapeHtml(item.equipment_name || 'N/A')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Quantity</span>
                        <span class="detail-value highlight">${item.quantity} ${escapeHtml(item.uom)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Unit Value</span>
                        <span class="detail-value">${formatCurrency(item.unit_value)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Location</span>
                        <span class="detail-value">${escapeHtml(item.location)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Building</span>
                        <span class="detail-value">${escapeHtml(item.building)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Condition</span>
                        <span class="detail-value">${escapeHtml(item.condition_text)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Fund Cluster</span>
                        <span class="detail-value">${escapeHtml(item.fund_cluster || 'N/A')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Status</span>
                        <span class="detail-value">
                            <span class="status-badge ${item.is_issued ? 'issued' : 'available'}">
                                ${item.is_issued ? 'Issued' : 'Available'}
                            </span>
                        </span>
                    </div>
                    ${item.is_multiple ? `
                    <div class="detail-item">
                        <span class="detail-label">Item Type</span>
                        <span class="detail-value">
                            <span class="status-badge multiple">Multiple Set Item</span>
                        </span>
                    </div>
                    ` : ''}
                </div>
                
                ${item.description ? `
                <div style="margin: 15px 0; padding: 10px; background: white; border-radius: 5px;">
                    <strong>Description:</strong> ${escapeHtml(item.description)}
                </div>
                ` : ''}
                
                <div class="barcode-preview">
                    <img src="generate_barcodeppe.php?code=${encodeURIComponent(item.barcode_data)}&format=png&width=300&height=80" 
                         alt="Barcode" onerror="this.style.display='none'">
                    <div style="font-family: monospace; font-size: 14px; color: var(--semi-secondary);">${escapeHtml(item.barcode_data)}</div>
                </div>
                
                ${item.is_multiple ? `
                <div style="margin-top: 15px; padding: 10px; background: #d1ecf1; border-radius: 5px;">
                    <i class="fas fa-info-circle" style="color: #17a2b8;"></i>
                    <small> This item is part of a multiple item set. The barcode above is unique to this specific item.</small>
                </div>
                ` : ''}
            </div>
        </div>
    `;
    
    document.getElementById('scanResult').innerHTML = html;
    document.getElementById('quickActions').style.display = 'block';
}

// Display not found
function displayNotFound(barcode) {
    let html = `
        <div class="not-found">
            <i class="fas fa-box-open"></i>
            <h3>Semi-Expendable Item Not Found</h3>
            <p>No Semi-Expendable item found with barcode: <strong>${escapeHtml(barcode)}</strong></p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button class="btn btn-primary" onclick="openAddSemiWithBarcode('${escapeHtml(barcode)}')">
                    <i class="fas fa-plus"></i> Add New Semi-Expendable
                </button>
                <button class="btn btn-secondary" onclick="resetScanner()">
                    <i class="fas fa-redo"></i> Scan Again
                </button>
            </div>
        </div>
    `;
    
    document.getElementById('scanResult').innerHTML = html;
    document.getElementById('quickActions').style.display = 'none';
}

// Show error
function showError(message) {
    document.getElementById('scanResult').innerHTML = `
        <div class="error-message">
            <i class="fas fa-exclamation-circle"></i>
            <p>${escapeHtml(message)}</p>
            <button class="btn btn-secondary" onclick="resetScanner()" style="margin-top: 10px;">
                <i class="fas fa-redo"></i> Try Again
            </button>
        </div>
    `;
}

// Reset scanner
function resetScanner() {
    document.getElementById('scanResult').innerHTML = `
        <div class="scan-placeholder">
            <i class="fas fa-box-open"></i>
            <p>Scan a Semi-Expendable barcode to see item details</p>
        </div>
    `;
    clearAllSearchInputs();
    // Focus on the active section's input
    const activeType = document.querySelector('.scanner-type-btn.active').getAttribute('data-scanner');
    if (activeType === 'live') {
        const input = document.getElementById('live_barcode_input');
        if (input) input.focus();
    } else if (activeType === 'file') {
        const input = document.getElementById('file_barcode_input');
        if (input) input.focus();
    } else if (activeType === 'handheld') {
        const input = document.getElementById('handheld_barcode_input');
        if (input) input.focus();
    }
}

// Add to recent scans
function addToRecentScans(item) {
    let existingIndex = recentScans.findIndex(scan => scan.id === item.id);
    if (existingIndex !== -1) {
        recentScans.splice(existingIndex, 1);
    }
    
    recentScans.unshift({
        id: item.id,
        name: item.article_name,
        barcode: item.barcode_data,
        time: new Date().toLocaleTimeString()
    });
    
    if (recentScans.length > 10) {
        recentScans.pop();
    }
    
    try {
        localStorage.setItem('semiRecentScans', JSON.stringify(recentScans));
    } catch (e) {
        console.error('Error saving recent scans:', e);
    }
    
    displayRecentScans();
}

// Display recent scans
function displayRecentScans() {
    let html = '';
    
    if (!recentScans || recentScans.length === 0) {
        html = '<div class="text-muted" style="padding: 10px; text-align: center;">No recent Semi-Expendable scans</div>';
    } else {
        recentScans.forEach(scan => {
            html += `
                <div class="recent-scan-item" onclick="rescanBarcode('${escapeHtml(scan.barcode)}')">
                    <div>
                        <div class="item-name">${escapeHtml(scan.name)}</div>
                        <div class="item-barcode">${escapeHtml(scan.barcode)}</div>
                    </div>
                    <div class="scan-time">${escapeHtml(scan.time)}</div>
                </div>
            `;
        });
    }
    
    document.getElementById('recentScansList').innerHTML = html;
}

// Rescan barcode
function rescanBarcode(barcode) {
    // Set the value in the active section's input and perform search
    const activeType = document.querySelector('.scanner-type-btn.active').getAttribute('data-scanner');
    if (activeType === 'live') {
        const input = document.getElementById('live_barcode_input');
        if (input) {
            input.value = barcode;
            searchLiveBarcode();
        }
    } else if (activeType === 'file') {
        const input = document.getElementById('file_barcode_input');
        if (input) {
            input.value = barcode;
            searchFileBarcode();
        }
    } else if (activeType === 'handheld') {
        const input = document.getElementById('handheld_barcode_input');
        if (input) {
            input.value = barcode;
            searchHandheldBarcode();
        }
    } else {
        performBarcodeSearch(barcode);
    }
}

// Clear recent scans
function clearRecentScans() {
    if (confirm('Clear recent Semi-Expendable scan history?')) {
        recentScans = [];
        try {
            localStorage.setItem('semiRecentScans', JSON.stringify(recentScans));
        } catch (e) {
            console.error('Error clearing recent scans:', e);
        }
        displayRecentScans();
    }
}

// Quick action functions
function editCurrentItem() {
    if (currentScannedItem) {
        window.location.href = 'semi_expendable.php?edit=' + currentScannedItem.id + '&csrf_token=' + encodeURIComponent(csrfToken);
    }
}

function issueCurrentItem() {
    if (currentScannedItem) {
        window.location.href = 'issue_items.php?item=' + currentScannedItem.id;
    }
}

function printCurrentItemBarcode() {
    if (currentScannedItem) {
        printBarcode(currentScannedItem.barcode_data, currentScannedItem.article_name);
    }
}

function viewCurrentItem() {
    if (currentScannedItem) {
        viewItemDetails(currentScannedItem.id);
    }
}

// Open add Semi-Expendable with barcode
function openAddSemiWithBarcode(barcode) {
    window.location.href = 'semi_expendable.php?barcode=' + encodeURIComponent(barcode);
}

// View item details
function viewItemDetails(itemId) {
    document.getElementById('itemDetailsContent').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Loading...</div>';
    document.getElementById('itemDetailsModal').style.display = 'block';
    
    fetch('<?php echo SITE_URL; ?>/api/get_item_details.php?id=' + itemId + '&csrf_token=' + encodeURIComponent(csrfToken))
        .then(response => {
            if (!response.ok) {
                throw new Error('Failed to load item details');
            }
            return response.json();
        })
        .then(data => {
            let content = `
                <div>
                    <h3 style="color: var(--semi-secondary); margin-bottom: 20px;">${escapeHtml(data.article_name || '')}</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 8px 0;"><strong>Property No:</strong></td><td>${escapeHtml(data.property_no || 'N/A')}</div></td></tr>
                        <tr><td style="padding: 8px 0;"><strong>Description:</strong>NonNullConnector<td>${escapeHtml(data.description || 'N/A')}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Category:</strong>NonNullConnector<td>${escapeHtml(data.category || 'N/A')}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Type:</strong>NonNullConnector<td>${escapeHtml(data.type_equipment || 'N/A')}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Equipment:</strong>NonNullConnector<td>${escapeHtml(data.equipment_name || 'N/A')}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Quantity:</strong>NonNullConnector<td>${escapeHtml((data.total_qty !== undefined ? data.total_qty : data.qty_physical_count) || '0')} ${escapeHtml(data.uom || '')}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Unit Value:</strong>NonNullConnector<td>${formatCurrency(data.unit_value)}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Total Value:</strong>NonNullConnector<td>${formatCurrency((data.unit_value || 0) * (data.qty_physical_count || 0))}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Fund Cluster:</strong>NonNullConnector<td>${escapeHtml(data.fund_cluster || 'N/A')}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Location:</strong>NonNullConnector<td>${escapeHtml(data.section_name || 'N/A')}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Condition:</strong>NonNullConnector<td>${escapeHtml(data.condition_text || 'Good')}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Certified Correct By:</strong>NonNullConnector<td>${escapeHtml(data.certified_correct || 'N/A')}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Approved By:</strong>NonNullConnector<td>${escapeHtml(data.approver_name || 'N/A')}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Verified By:</strong>NonNullConnector<td>${escapeHtml(data.verifier_name || 'N/A')}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Allocated To:</strong>NonNullConnector<td>${escapeHtml(data.allocatee_name || 'N/A')}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Created By:</strong>NonNullConnector<td>${escapeHtml(data.created_by_name || 'N/A')}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Barcode:</strong>NonNullConnector<td style="font-family: monospace;">${escapeHtml(data.barcode_data || 'N/A')}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Date Added:</strong>NonNullConnector<td>${formatDate(data.date_added)}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Date Updated:</strong>NonNullConnector<td>${formatDate(data.date_updated)}</div>ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Remarks:</strong>NonNullConnector<td>${escapeHtml(data.remarks || 'N/A')}</div>ERC20</tr>
                    </table>
                </div>
            `;
            document.getElementById('itemDetailsContent').innerHTML = content;
        })
        .catch(error => {
            console.error('Error:', error);
            document.getElementById('itemDetailsContent').innerHTML = '<div class="error-message">Error loading item details</div>';
        });
}

// Close item details modal
function closeItemDetailsModal() {
    document.getElementById('itemDetailsModal').style.display = 'none';
}

// Print barcode
function printBarcode(barcodeData, itemName) {
    let printWindow = window.open('', '_blank');
    let timestamp = new Date().getTime();
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Print Semi-Expendable Barcode - ${escapeHtml(itemName)}</title>
            <style>
                body { text-align: center; font-family: Arial, sans-serif; margin: 0; padding: 20px; background: white; }
                .barcode-container { margin: 20px auto; padding: 30px; max-width: 400px; border: 1px dashed #17a2b8; border-radius: 10px; }
                .barcode-img { max-width: 100%; height: auto; margin-bottom: 15px; }
                .item-name { margin-top: 15px; font-size: 16px; font-weight: bold; color: #161E54; }
                .barcode-number { font-family: monospace; font-size: 14px; margin-top: 10px; color: #17a2b8; }
                .semi-label { background: #17a2b8; color: white; padding: 5px 15px; border-radius: 20px; display: inline-block; font-size: 14px; margin-bottom: 15px; }
                @media print { body { margin: 0; padding: 10px; } .barcode-container { border: none; } }
            </style>
        </head>
        <body>
            <div class="barcode-container">
                <div class="semi-label">Semi-Expendable</div>
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

// Helper functions
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

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    try {
        let date = new Date(dateString);
        if (isNaN(date.getTime())) return 'N/A';
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        return 'N/A';
    }
}

// Clean up
window.addEventListener('beforeunload', function() {
    if (Quagga) {
        try {
            Quagga.stop();
        } catch (e) {}
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    let itemDetailsModal = document.getElementById('itemDetailsModal');
    if (event.target == itemDetailsModal) {
        closeItemDetailsModal();
    }
}

// ===== HARDWARE BARCODE SCANNER MODAL FUNCTIONS FOR SEMI-EXPENDABLE =====
let hardwareScannedSemiItems = [];

function openHardwareScannerModal() {
    const modal = document.getElementById('hardwareScannerModal');
    if (modal) {
        modal.classList.add('show');
        hardwareScannedSemiItems = [];
        updateHardwareScannedItemsDisplay();
        
        setTimeout(function() {
            const input = document.getElementById('hardwareScannerInput');
            if (input) input.focus();
        }, 100);
    }
}

function closeHardwareScannerModal() {
    const modal = document.getElementById('hardwareScannerModal');
    if (modal) {
        modal.classList.remove('show');
    }
}

function processHardwareBarcodeSemi(barcode) {
    const searchTerm = barcode.toLowerCase().trim();
    
    fetch(`?scan_barcode=1&barcode=${encodeURIComponent(barcode)}&csrf_token=${document.querySelector('[name="csrf_token"]')?.value || ''}`)
        .then(response => response.json())
        .then(data => {
            if (data.item) {
                const existingIndex = hardwareScannedSemiItems.findIndex(i => i.id == data.item.id);
                if (existingIndex !== -1) {
                    hardwareScannedSemiItems[existingIndex].count++;
                } else {
                    hardwareScannedSemiItems.push({
                        id: data.item.id,
                        name: data.item.article_name || data.item.name,
                        barcode_data: barcode,
                        quantity: data.item.quantity_on_hand || 0,
                        unit_value: data.item.unit_value || 0,
                        count: 1
                    });
                }
                updateHardwareScannedItemsDisplay();
                showScanSuccessSemi();
            } else if (data.error) {
                showScanErrorSemi(barcode);
            }
        })
        .catch(error => {
            console.error('Scan error:', error);
            showScanErrorSemi(barcode);
        });
}

function showScanSuccessSemi() {
    const input = document.getElementById('hardwareScannerInput');
    if (!input) return;
    const originalBg = input.style.backgroundColor;
    input.style.backgroundColor = '#C8E6C9';
    input.style.borderColor = '#4CAF50';
    
    setTimeout(function() {
        input.style.backgroundColor = originalBg;
        input.style.borderColor = '#FF6B35';
    }, 500);
}

function showScanErrorSemi(barcode) {
    const input = document.getElementById('hardwareScannerInput');
    if (!input) return;
    const originalBg = input.style.backgroundColor;
    input.style.backgroundColor = '#FFCDD2';
    input.style.borderColor = '#f44336';
    
    setTimeout(function() {
        input.style.backgroundColor = originalBg;
        input.style.borderColor = '#FF6B35';
    }, 2000);
}

function updateHardwareScannedItemsDisplay() {
    const container = document.getElementById('hardwareScannedItemsContainer');
    const clearScansBtn = document.getElementById('clearScansBtn');
    
    if (!container) return;
    
    if (hardwareScannedSemiItems.length === 0) {
        container.innerHTML = `
            <div class="empty-scan-state">
                <i class="fas fa-box"></i>
                <p>No items scanned yet</p>
                <small>Start scanning Semi-Expendable items to see them here</small>
            </div>
        `;
        if (clearScansBtn) clearScansBtn.style.display = 'none';
    } else {
        let html = '<div id="hardwareScannedItemsList">';
        
        hardwareScannedSemiItems.forEach((item, index) => {
            html += `
                <div class="scanned-item-card success">
                    <div class="scanned-item-info">
                        <div class="scanned-item-name">
                            <i class="fas fa-check-circle"></i> ${escapeHtml(item.name)}
                        </div>
                        <div class="scanned-item-details">
                            Barcode: ${escapeHtml(item.barcode_data || 'N/A')} | 
                            Available: ${item.quantity}
                        </div>
                    </div>
                    <div class="scanned-item-qty-controls">
                        <button type="button" class="remove-scanned-item" onclick="removeHardwareScannedItemSemi(${index})">
                            <i class="fas fa-trash"></i> Remove
                        </button>
                    </div>
                </div>
            `;
        });
        
        html += `
            <div style="margin-top: 15px; padding: 12px; background: #F5F5F5; border-radius: 8px; text-align: right; font-weight: bold; color: #333;">
                Total Items Scanned: ${hardwareScannedSemiItems.length}
            </div>
        </div>`;
        
        container.innerHTML = html;
        if (clearScansBtn) clearScansBtn.style.display = 'inline-block';
    }
}

function removeHardwareScannedItemSemi(index) {
    hardwareScannedSemiItems.splice(index, 1);
    updateHardwareScannedItemsDisplay();
}

function clearHardwareScans() {
    if (confirm('Are you sure you want to clear all scanned items?')) {
        hardwareScannedSemiItems = [];
        updateHardwareScannedItemsDisplay();
    }
}
</script>

<!-- ===== HARDWARE SCANNER MODAL HTML FOR SEMI-EXPENDABLE ===== -->
<style>
.hardware-scanner-modal {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.8);
    z-index: 3000;
    align-items: center;
    justify-content: center;
}

.hardware-scanner-modal.show {
    display: flex;
}

.hardware-scanner-content {
    background: white;
    border-radius: 15px;
    width: 90%;
    max-width: 600px;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 50px rgba(0, 0, 0, 0.3);
    display: flex;
    flex-direction: column;
}

.hardware-scanner-header {
    background: linear-gradient(135deg, #FF6B35 0%, #FF8C42 100%);
    color: white;
    padding: 25px;
    border-radius: 15px 15px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-shrink: 0;
}

.hardware-scanner-header h2 {
    margin: 0;
    font-size: 20px;
    font-weight: bold;
}

.hardware-scanner-header .close-btn {
    background: rgba(255, 255, 255, 0.2);
    border: none;
    color: white;
    font-size: 28px;
    cursor: pointer;
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.2s;
}

.hardware-scanner-header .close-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}

.hardware-scanner-body {
    padding: 25px;
    flex: 1;
    overflow-y: auto;
    display: flex;
    flex-direction: column;
}

.scanner-instructions {
    background: #E8F4FD;
    border-left: 4px solid #2196F3;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-size: 14px;
    color: #0D47A1;
}

.scanner-instructions strong {
    display: block;
    margin-bottom: 8px;
    font-size: 15px;
}

.scanner-input-container {
    margin-bottom: 20px;
}

.scanner-input-container label {
    display: block;
    font-weight: bold;
    margin-bottom: 10px;
    color: #333;
    font-size: 14px;
}

.scanner-input-wrapper {
    position: relative;
    display: flex;
    align-items: center;
}

.scanner-input-wrapper i {
    position: absolute;
    left: 12px;
    color: #FF6B35;
    font-size: 18px;
}

.hardware-scanner-input {
    width: 100%;
    padding: 15px 15px 15px 45px;
    border: 2px solid #FF6B35;
    border-radius: 10px;
    font-size: 16px;
    font-weight: bold;
    background: #FFF8F5;
    color: #333;
    box-shadow: 0 2px 8px rgba(255, 107, 53, 0.1);
    transition: all 0.2s;
}

.hardware-scanner-input:focus {
    outline: none;
    border-color: #FF8C42;
    box-shadow: 0 2px 12px rgba(255, 107, 53, 0.2);
}

.scanned-items-list {
    margin-top: 20px;
    flex: 1;
    overflow-y: auto;
}

.empty-scan-state {
    text-align: center;
    padding: 40px 20px;
    color: #999;
}

.empty-scan-state i {
    font-size: 48px;
    margin-bottom: 15px;
    color: #CCC;
}

.empty-scan-state p {
    font-size: 16px;
    font-weight: bold;
    margin: 10px 0;
}

.hardware-scanner-footer {
    padding: 15px 25px;
    border-top: 1px solid #E0E0E0;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
    flex-shrink: 0;
}

.btn-clear-scans {
    background: #FF6B35;
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    font-size: 14px;
    transition: all 0.2s;
}

.btn-clear-scans:hover {
    background: #FF8C42;
}

.btn-close-hardware-scanner {
    background: #757575;
    color: white;
    border: none;
    padding: 12px 25px;
    border-radius: 8px;
    cursor: pointer;
    font-weight: bold;
    font-size: 14px;
    transition: all 0.2s;
}

.btn-close-hardware-scanner:hover {
    background: #616161;
}
</style>

<div id="hardwareScannerModal" class="hardware-scanner-modal">
    <div class="hardware-scanner-content">
        <div class="hardware-scanner-header">
            <h2><i class="fas fa-barcode"></i> Hardware Barcode Scanner</h2>
            <button type="button" class="close-btn" onclick="closeHardwareScannerModal()">&times;</button>
        </div>
        
        <div class="hardware-scanner-body">
            <div class="scanner-instructions">
                <strong><i class="fas fa-info-circle"></i> Ready to Scan</strong>
                Click the input field below and scan Semi-Expendable barcodes with your device. Items will be added to the list as you scan. When done, close the modal to continue.
            </div>
            
            <div class="scanner-input-container">
                <label><i class="fas fa-barcode"></i> Scan Barcode</label>
                <div class="scanner-input-wrapper">
                    <i class="fas fa-barcode"></i>
                    <input type="text" id="hardwareScannerInput" class="hardware-scanner-input" placeholder="Place cursor here and scan..." autocomplete="off" autofocus>
                </div>
            </div>
            
            <div class="scanned-items-list">
                <div id="hardwareScannedItemsContainer">
                    <div class="empty-scan-state">
                        <i class="fas fa-box"></i>
                        <p>No items scanned yet</p>
                        <small>Start scanning Semi-Expendable items to see them here</small>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="hardware-scanner-footer">
            <button type="button" class="btn-clear-scans" onclick="clearHardwareScans()" id="clearScansBtn" style="display: none;">
                <i class="fas fa-trash"></i> Clear All
            </button>
            <button type="button" class="btn-close-hardware-scanner" onclick="closeHardwareScannerModal()">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<?php include INCLUDE_PATH . '/footer.php'; ?>