<?php
/**
 * Issuance Barcode Scanner Page
 * Scan barcodes to quickly find and manage issued items
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
    error_log("Database connection failed in barcode_generator_issued.php");
    die("Database connection failed. Please try again later.");
}

// Include barcode library
require_once $root_path . '/vendor/autoload.php';
use Picqer\Barcode\BarcodeGeneratorPNG;

// Require admin role
requireRole('admin');

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Issuance Barcode Scanner';
$page_description = 'Scan barcodes to quickly find issued items';

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
        $rate_key = 'scan_attempts_' . md5($client_ip);
        
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

        error_log("Issuance Scanner: Searching for barcode: '" . $barcode . "'");

        if (empty($barcode)) {
            echo json_encode(['error' => 'Please provide a barcode']);
            exit;
        }
        
        // Validate barcode format (alphanumeric and common symbols only)
        if (!preg_match('/^[A-Za-z0-9\-_\.\+\s]+$/', $barcode)) {
            echo json_encode(['error' => 'Invalid barcode format']);
            exit;
        }
        
        // Sanitize and normalize input
        $barcode = sanitize($barcode);
        $barcode = trim($barcode);
        $barcode = preg_replace('/\s+/', '', $barcode);
        
        // Search for the barcode in inventory
        $stmt = $conn->prepare("
            SELECT i.*, 
                   e.name as equipment_name, 
                   s.name as section_name,
                   d.name as department_name, 
                   d.code as department_code,
                   b.name as building_name,
                   toe.name as type_equipment_name,
                   CONCAT(emp.firstname, ' ', emp.lastname) as current_holder_name
            FROM inventory i
            LEFT JOIN equipment e ON i.equipment_id = e.id  
            LEFT JOIN sections s ON i.section_id = s.id
            LEFT JOIN departments d ON s.department_id = d.id
            LEFT JOIN buildings b ON d.building_id = b.id
            LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
            LEFT JOIN employees emp ON i.current_holder = emp.id
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
            // Get issuance status
            $issuance_stmt = $conn->prepare("
                SELECT ei.*, 
                       CONCAT(e.firstname, ' ', e.lastname) as issued_to_name,
                       e.position as issued_to_position,
                       s.name as section_name,
                       d.name as department_name,
                       d.code as department_code
                FROM equipment_issuance ei
                JOIN employees e ON ei.issued_to = e.id
                LEFT JOIN sections s ON e.section_id = s.id
                LEFT JOIN departments d ON s.department_id = d.id
                WHERE ei.inventory_id = ? AND ei.status = 'issued'
                ORDER BY ei.issued_date DESC
                LIMIT 1
            ");
            
            $issuance_stmt->bind_param("i", $row['id']);
            $issuance_stmt->execute();
            $issuance_result = $issuance_stmt->get_result();
            $issuance = $issuance_result->fetch_assoc();
            
            $quantity = (float)($row['qty_physical_count'] ?? 0);
            
            // Build location string
            $location = '';
            if ($row['section_name']) {
                $location = htmlspecialchars($row['section_name'], ENT_QUOTES, 'UTF-8');
                if ($row['department_name']) {
                    $location .= ' - ' . htmlspecialchars($row['department_name'], ENT_QUOTES, 'UTF-8');
                }
            } else {
                $location = 'N/A';
            }
            
            // Build equipment type display
            $equipment_type = '';
            if ($row['type_equipment_name']) {
                $equipment_type = htmlspecialchars($row['type_equipment_name'], ENT_QUOTES, 'UTF-8');
            } else {
                $equipment_type = htmlspecialchars($row['category'] ?? 'Equipment', ENT_QUOTES, 'UTF-8');
            }
            
            $is_issued = ($issuance && $issuance['status'] == 'issued');
            
            echo json_encode([
                'success' => true,
                'found' => true,
                'item' => [
                    'id' => (int)$row['id'],
                    'article_name' => htmlspecialchars($row['article_name'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'property_no' => htmlspecialchars($row['property_no'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'description' => htmlspecialchars($row['description'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'barcode_data' => htmlspecialchars($row['barcode_data'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'category' => htmlspecialchars($row['category'] ?? 'Equipment', ENT_QUOTES, 'UTF-8'),
                    'type_equipment' => $equipment_type,
                    'quantity' => $quantity,
                    'big_unit' => htmlspecialchars($row['big_unit'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'small_unit' => htmlspecialchars($row['small_unit'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'unit_value' => (float)($row['unit_value'] ?? 0),
                    'location' => $location,
                    'building' => htmlspecialchars($row['building_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'condition_text' => htmlspecialchars($row['condition_text'] ?? 'Serviceable', ENT_QUOTES, 'UTF-8'),
                    'fund_cluster' => htmlspecialchars($row['fund_cluster'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'department_code' => htmlspecialchars($row['department_code'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'current_holder_name' => htmlspecialchars($row['current_holder_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'is_issued' => $is_issued,
                    'issuance' => $issuance ? [
                        'id' => $issuance['id'],
                        'issued_to_name' => htmlspecialchars($issuance['issued_to_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                        'issued_to_position' => htmlspecialchars($issuance['issued_to_position'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                        'quantity_issued' => $issuance['quantity_issued'],
                        'issued_date' => $issuance['issued_date'],
                        'purpose' => htmlspecialchars($issuance['purpose'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                        'condition_on_issue' => htmlspecialchars($issuance['condition_on_issue'] ?? 'Serviceable', ENT_QUOTES, 'UTF-8'),
                        'issuance_barcode' => htmlspecialchars($issuance['issuance_barcode'] ?? '', ENT_QUOTES, 'UTF-8'),
                        'section_name' => htmlspecialchars($issuance['section_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                        'department_name' => htmlspecialchars($issuance['department_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                        'department_code' => htmlspecialchars($issuance['department_code'] ?? 'N/A', ENT_QUOTES, 'UTF-8')
                    ] : null,
                    'date_added' => $row['date_added'] ?? null,
                    'date_updated' => $row['date_updated'] ?? null,
                    'remarks' => htmlspecialchars($row['remarks'] ?? 'N/A', ENT_QUOTES, 'UTF-8')
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'found' => false,
                'message' => 'No item found with this barcode',
                'barcode' => htmlspecialchars($barcode, ENT_QUOTES, 'UTF-8')
            ]);
        }
        
        $stmt->close();
        
    } catch (Exception $e) {
        error_log("Issuance Barcode scanner error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'An internal server error occurred']);
    }
    exit;
}

// Handle barcode generation
if (isset($_GET['generate_barcode']) && isset($_GET['code'])) {
    $code = sanitize($_GET['code']);
    $width = isset($_GET['width']) ? (int)$_GET['width'] : 300;
    $height = isset($_GET['height']) ? (int)$_GET['height'] : 80;
    
    $generator = new BarcodeGeneratorPNG();
    $barcode = $generator->getBarcode($code, BarcodeGeneratorPNG::TYPE_CODE_128, 2, 50);
    
    header('Content-Type: image/png');
    echo $barcode;
    exit;
}

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

.btn-danger {
    background-color: var(--danger);
    color: white;
}

.btn-danger:hover {
    background-color: #d32f2f;
    transform: translateY(-2px);
}

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

.error-message {
    background-color: #ffebee;
    color: var(--danger);
    padding: 12px;
    border-radius: 5px;
    margin: 10px 0;
    text-align: center;
}

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
    max-width: 600px;
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

/* Issuance specific styles */
.issuance-badge {
    display: inline-block;
    background-color: var(--primary);
    color: white;
    padding: 3px 10px;
    border-radius: 15px;
    font-size: 11px;
    font-weight: 600;
    margin-left: 10px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.issuance-detail-card {
    background: #E3F2FD;
    border-left-color: var(--primary);
}
</style>

<div class="scanner-container">
    <div class="scanner-header">
        <h2><i class="fas fa-barcode"></i> Issuance Barcode Scanner</h2>
        <p>Scan barcodes to instantly find issued items and view issuance details</p>
        <span class="issuance-badge"><i class="fas fa-hand-holding"></i> Issued Items Only</span>
    </div>

    <div class="scanner-main">
        <!-- Left Column - Scanner -->
        <div class="scanner-left">
            <div class="scanner-card">
                <h3><i class="fas fa-camera"></i> Scan Barcode</h3>
                
                <!-- Search Bar Wrapper -->
                <div class="search-bar-wrapper">
                    <div id="liveSearchSection" class="search-input-section">
                        <label for="live_barcode_input"><i class="fas fa-search"></i> Search by Barcode:</label>
                        <div class="input-group">
                            <input type="text" id="live_barcode_input" class="form-control" placeholder="Type barcode and press Enter...">
                            <button class="btn btn-primary" onclick="searchLiveBarcode()">
                                <i class="fas fa-search"></i> Find Item
                            </button>
                        </div>
                    </div>

                    <div id="fileSearchSection" class="search-input-section" style="display: none;">
                        <label for="file_barcode_input"><i class="fas fa-search"></i> Search by Barcode:</label>
                        <div class="input-group">
                            <input type="text" id="file_barcode_input" class="form-control" placeholder="Type barcode and press Enter...">
                            <button class="btn btn-primary" onclick="searchFileBarcode()">
                                <i class="fas fa-search"></i> Find Item
                            </button>
                        </div>
                    </div>

                    <div id="handheldSearchSection" class="search-input-section" style="display: none;">
                        <label for="handheld_barcode_input"><i class="fas fa-search"></i> Enter Barcode Manually:</label>
                        <div class="input-group">
                            <input type="text" id="handheld_barcode_input" class="form-control" placeholder="Type barcode and press Enter...">
                            <button class="btn btn-primary" onclick="searchHandheldBarcode()">
                                <i class="fas fa-search"></i> Find Item
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Scanner Type Selection -->
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
                        <p><i class="fas fa-info-circle"></i> Position the barcode in the center of the red line. The scanner will automatically detect barcodes.</p>
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

                <!-- Recent Scans -->
                <div class="recent-scans">
                    <h4><i class="fas fa-history"></i> Recent Scans</h4>
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
                <div class="scan-placeholder">
                    <i class="fas fa-barcode"></i>
                    <p>Scan a barcode to see item details and issuance information</p>
                </div>
            </div>

            <!-- Quick Actions for Scanned Item -->
            <div id="quickActions" class="quick-actions" style="display: none;">
                <h4><i class="fas fa-bolt"></i> Quick Actions</h4>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="goToIssuePage()">
                        <i class="fas fa-hand-holding"></i> Issue Item
                    </button>
                    <button class="btn btn-info" onclick="printCurrentItemBarcode()">
                        <i class="fas fa-print"></i> Print Barcode
                    </button>
                    <button class="btn btn-secondary" onclick="viewCurrentItem()">
                        <i class="fas fa-eye"></i> View Details
                    </button>
                    <?php if(isset($_SESSION['reissue_from']) || isset($_SESSION['reissue_from_returned'])): ?>
                    <button class="btn btn-success" onclick="continueReissue()">
                        <i class="fas fa-redo-alt"></i> Continue Reissue
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Item Details Modal -->
<div id="itemDetailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Item Details</h2>
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

<audio id="beep-sound" src="data:audio/wav;base64,UklGRlwAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YVQAAACAgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f38=" preload="auto"></audio>

<script>
let currentScannedItem = null;
let recentScans = [];
let scannerInitialized = false;
let activeStream = null;
let currentCamera = null;
let cameras = [];
let csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

// Function to set scanner type with button group
function setScannerType(type) {
    document.querySelectorAll('.scanner-type-btn').forEach(btn => {
        btn.classList.remove('active');
        if (btn.getAttribute('data-scanner') === type) {
            btn.classList.add('active');
        }
    });
    
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
        if (scannerInitialized) {
            startScanner();
        } else {
            initScanner();
        }
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
        setTimeout(function() {
            const input = document.getElementById('handheld_barcode_input');
            if (input) input.focus();
        }, 100);
    }
}

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

function performBarcodeSearch(barcode) {
    if (!validateBarcode(barcode)) {
        alert('Invalid barcode format. Please use only letters, numbers, and common symbols.');
        return;
    }
    
    document.getElementById('scanResult').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Searching for item...</div>';
    
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

function clearAllSearchInputs() {
    const liveInput = document.getElementById('live_barcode_input');
    const fileInput = document.getElementById('file_barcode_input');
    const handheldInput = document.getElementById('handheld_barcode_input');
    if (liveInput) liveInput.value = '';
    if (fileInput) fileInput.value = '';
    if (handheldInput) handheldInput.value = '';
}

document.addEventListener('DOMContentLoaded', function() {
    try {
        recentScans = JSON.parse(localStorage.getItem('issuanceRecentScans') || '[]');
        if (!Array.isArray(recentScans)) {
            recentScans = [];
        }
    } catch (e) {
        console.error('Error loading recent scans:', e);
        recentScans = [];
    }
    
    displayRecentScans();
    
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
    
    document.getElementById('fileUploadArea').addEventListener('click', function() {
        document.getElementById('barcodeImage').click();
    });
    
    document.getElementById('barcodeImage').addEventListener('change', handleImageUpload);
    
    document.getElementById('fileUploadArea').addEventListener('dragover', function(e) {
        e.preventDefault();
        this.style.borderColor = '#F16D34';
        this.style.background = '#fff5f0';
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
    
    setTimeout(function() {
        startScanner();
        const liveInput = document.getElementById('live_barcode_input');
        if (liveInput) liveInput.focus();
    }, 500);
});

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
                    Quagga.ImageDebug.drawPath(result.box, {x: 0, y: 1}, drawingCtx, {color: "#F16D34", lineWidth: 3});
                }
                
                if (result.codeResult && result.codeResult.code) {
                    drawingCtx.font = "24px Arial";
                    drawingCtx.fillStyle = "#F16D34";
                    drawingCtx.fillText(result.codeResult.code, 20, 40);
                }
            }
        });
        
        Quagga.onDetected(function(result) {
            let code = result.codeResult.code;
            if (code) {
                document.getElementById('beep-sound').play().catch(e => console.log('Audio play failed:', e));
                stopScanner();
                
                const liveInput = document.getElementById('live_barcode_input');
                if (liveInput) {
                    liveInput.value = code;
                    searchLiveBarcode();
                } else {
                    performBarcodeSearch(code);
                }
                
                document.getElementById('scanner-status').textContent = 'Barcode detected: ' + code;
                
                setTimeout(() => {
                    if (document.querySelector('.scanner-type-btn.active').getAttribute('data-scanner') === 'live') {
                        startScanner();
                    }
                }, 2000);
            }
        });
    });
}

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

function handleImageUpload(e) {
    let file = e.target.files[0];
    if (file) {
        handleImageFile(file);
    }
}

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

function resetImageUpload() {
    document.getElementById('imagePreview').style.display = 'none';
    document.getElementById('fileUploadArea').style.display = 'block';
    document.getElementById('barcodeImage').value = '';
    document.getElementById('previewImg').src = '';
    const fileInput = document.getElementById('file_barcode_input');
    if (fileInput) fileInput.focus();
}

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

function validateBarcode(barcode) {
    return /^[A-Za-z0-9\-_\.\+\s]+$/.test(barcode);
}

function displayScannedItem(item) {
    currentScannedItem = item;
    
    let unitDisplay = '';
    if (item.big_unit && item.small_unit) {
        unitDisplay = `${escapeHtml(item.big_unit)} / ${escapeHtml(item.small_unit)}`;
    } else if (item.big_unit) {
        unitDisplay = escapeHtml(item.big_unit);
    } else if (item.small_unit) {
        unitDisplay = escapeHtml(item.small_unit);
    } else {
        unitDisplay = 'pcs';
    }
    
    let quantityDisplay = `${item.quantity} ${unitDisplay}`;
    
    let issuanceHtml = '';
    if (item.is_issued && item.issuance) {
        issuanceHtml = `
            <div class="item-detail-card issuance-detail-card" style="margin-top: 15px;">
                <h3><i class="fas fa-hand-holding"></i> Issuance Details</h3>
                <div class="item-detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Issued To</span>
                        <span class="detail-value">${escapeHtml(item.issuance.issued_to_name)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Position</span>
                        <span class="detail-value">${escapeHtml(item.issuance.issued_to_position || 'N/A')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Department</span>
                        <span class="detail-value">${escapeHtml(item.issuance.department_name || 'N/A')} (${escapeHtml(item.issuance.department_code || 'N/A')})</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Quantity Issued</span>
                        <span class="detail-value highlight">${item.issuance.quantity_issued} ${unitDisplay}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Issued Date</span>
                        <span class="detail-value">${new Date(item.issuance.issued_date).toLocaleDateString()}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Condition</span>
                        <span class="detail-value">${escapeHtml(item.issuance.condition_on_issue)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Issuance Barcode</span>
                        <span class="detail-value">${escapeHtml(item.issuance.issuance_barcode || 'N/A')}</span>
                    </div>
                </div>
            </div>
        `;
    }
    
    let html = `
        <div class="found-item">
            <div class="item-detail-card">
                <h3>${escapeHtml(item.article_name)} <span class="issuance-badge">${item.is_issued ? 'ISSUED' : 'AVAILABLE'}</span></h3>
                
                <div class="item-detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Property No.</span>
                        <span class="detail-value">${escapeHtml(item.property_no)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Equipment Type</span>
                        <span class="detail-value">${escapeHtml(item.type_equipment || 'Equipment')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Category</span>
                        <span class="detail-value">${escapeHtml(item.category || 'N/A')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Quantity</span>
                        <span class="detail-value highlight">${quantityDisplay}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Unit Value</span>
                        <span class="detail-value">${formatCurrency(item.unit_value)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Total Value</span>
                        <span class="detail-value">${formatCurrency(item.unit_value * item.quantity)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Location</span>
                        <span class="detail-value">${escapeHtml(item.location)}</span>
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
                                ${item.is_issued ? 'Currently Issued' : 'Available'}
                            </span>
                        </span>
                    </div>
                    ${item.current_holder_name !== 'N/A' ? `
                    <div class="detail-item">
                        <span class="detail-label">Current Holder</span>
                        <span class="detail-value">${escapeHtml(item.current_holder_name)}</span>
                    </div>
                    ` : ''}
                </div>
                
                ${item.description ? `
                <div style="margin: 15px 0; padding: 10px; background: white; border-radius: 5px;">
                    <strong>Description:</strong> ${escapeHtml(item.description)}
                </div>
                ` : ''}
                
                <div class="barcode-preview">
                    <img src="?generate_barcode=1&code=${encodeURIComponent(item.barcode_data)}&width=300&height=80" 
                         alt="Barcode" onerror="this.style.display='none'">
                    <div style="font-family: monospace; font-size: 14px; color: var(--primary); margin-top: 10px;">${escapeHtml(item.barcode_data)}</div>
                </div>
            </div>
            ${issuanceHtml}
        </div>
    `;
    
    document.getElementById('scanResult').innerHTML = html;
    document.getElementById('quickActions').style.display = 'block';
}

function displayNotFound(barcode) {
    let html = `
        <div class="not-found">
            <i class="fas fa-barcode"></i>
            <h3>Item Not Found</h3>
            <p>No item found with barcode: <strong>${escapeHtml(barcode)}</strong></p>
            <div style="display: flex; gap: 10px; justify-content: center;">
                <button class="btn btn-primary" onclick="goToIssuePageWithBarcode('${escapeHtml(barcode)}')">
                    <i class="fas fa-plus"></i> Issue New Item
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

function resetScanner() {
    document.getElementById('scanResult').innerHTML = `
        <div class="scan-placeholder">
            <i class="fas fa-barcode"></i>
            <p>Scan a barcode to see item details and issuance information</p>
        </div>
    `;
    document.getElementById('quickActions').style.display = 'none';
    clearAllSearchInputs();
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
        localStorage.setItem('issuanceRecentScans', JSON.stringify(recentScans));
    } catch (e) {
        console.error('Error saving recent scans:', e);
    }
    
    displayRecentScans();
}

function displayRecentScans() {
    let html = '';
    
    if (!recentScans || recentScans.length === 0) {
        html = '<div class="text-muted" style="padding: 10px; text-align: center;">No recent scans</div>';
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

function rescanBarcode(barcode) {
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

function clearRecentScans() {
    if (confirm('Clear recent scan history?')) {
        recentScans = [];
        try {
            localStorage.setItem('issuanceRecentScans', JSON.stringify(recentScans));
        } catch (e) {
            console.error('Error clearing recent scans:', e);
        }
        displayRecentScans();
    }
}

function goToIssuePage() {
    if (currentScannedItem) {
        window.location.href = 'issue_items.php?barcode=' + encodeURIComponent(currentScannedItem.barcode_data);
    }
}

function goToIssuePageWithBarcode(barcode) {
    window.location.href = 'issue_items.php?barcode=' + encodeURIComponent(barcode);
}

function continueReissue() {
    window.location.href = 'issue_items.php';
}

function printCurrentItemBarcode() {
    if (currentScannedItem) {
        printBarcode(currentScannedItem.barcode_data, currentScannedItem.article_name, currentScannedItem.fund_cluster);
    }
}

function viewCurrentItem() {
    if (currentScannedItem) {
        viewItemDetails(currentScannedItem.id);
    }
}

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
            let unitDisplay = '';
            if (data.big_unit && data.small_unit) {
                unitDisplay = `${escapeHtml(data.big_unit)} / ${escapeHtml(data.small_unit)}`;
            } else if (data.big_unit) {
                unitDisplay = escapeHtml(data.big_unit);
            } else if (data.small_unit) {
                unitDisplay = escapeHtml(data.small_unit);
            } else {
                unitDisplay = 'pcs';
            }
            
            let content = `
                <div>
                    <h3 style="color: var(--primary); margin-bottom: 20px;">${escapeHtml(data.article_name || '')}</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 8px 0;"><strong>Property No:</strong>ERC20<td style="color:#333;">${escapeHtml(data.property_no || 'N/A')}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Description:</strong>ERC20<td style="color:#333;">${escapeHtml(data.description || 'N/A')}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Equipment Type:</strong>ERC20<td style="color:#333;">${escapeHtml(data.type_equipment || 'Equipment')}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Quantity:</strong>ERC20<td style="color:#F16D34; font-weight:bold;">${escapeHtml(data.qty_physical_count || '0')} ${unitDisplay}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Unit Value:</strong>ERC20<td style="color:#333;">${formatCurrency(data.unit_value)}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Location:</strong>ERC20<td style="color:#333;">${escapeHtml(data.section_name || 'N/A')}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Condition:</strong>ERC20<td style="color:#333;">${escapeHtml(data.condition_text || 'Serviceable')}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Barcode:</strong>ERC20<td style="font-family: monospace;">${escapeHtml(data.barcode_data || 'N/A')}ERC20</tr>
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

function closeItemDetailsModal() {
    document.getElementById('itemDetailsModal').style.display = 'none';
}

function printBarcode(barcodeData, itemName, fundCluster) {
    let printWindow = window.open('', '_blank');
    let timestamp = new Date().getTime();

    let fundClusterHtml = '';
    if (fundCluster && fundCluster !== 'N/A' && fundCluster.trim() !== '') {
        fundClusterHtml = `<div class="fund-cluster">Fund Cluster: <strong>${escapeHtml(fundCluster)}</strong></div>`;
    }

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
                .fund-cluster { margin-top: 15px; padding: 8px 15px; background: #f0f4ff; border: 1px solid #6B8CFF; border-radius: 6px; font-size: 13px; color: #3A3A3A; }
                @media print { body { margin: 0; padding: 10px; } .barcode-container { border: none; } }
            </style>
        </head>
        <body>
            <div class="barcode-container">
                <div class="item-name">${escapeHtml(itemName)}</div>
                ${fundClusterHtml}
                <img src="?generate_barcode=1&code=${encodeURIComponent(barcodeData)}&width=400&height=100&t=${timestamp}"
                     class="barcode-img" alt="Barcode" onerror="this.style.display='none'">
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

window.addEventListener('beforeunload', function() {
    if (Quagga) {
        try {
            Quagga.stop();
        } catch (e) {}
    }
});

window.onclick = function(event) {
    let itemDetailsModal = document.getElementById('itemDetailsModal');
    if (event.target == itemDetailsModal) {
        closeItemDetailsModal();
    }
}
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>