<?php
/**
 * Barcode Scanner Page (Admin)
 * Scan barcodes to quickly find and manage inventory items
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
    error_log("Database connection failed in barcode_scanner.php");
    die("Database connection failed. Please try again later.");
}

// Include barcode library
require_once $root_path . '/vendor/autoload.php';
use Picqer\Barcode\BarcodeGeneratorPNG;

// Require admin role
requireRole(['admin', 'supply']);

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Barcode Scanner';
$page_description = 'Scan barcodes to quickly find inventory items';

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
        $barcode = trim($barcode); // Remove leading/trailing whitespace
        $barcode = preg_replace('/\s+/', '', $barcode); // Remove all internal whitespace
        
        // Search for the barcode in inventory with prepared statement
        $stmt = $conn->prepare("
            SELECT i.*, e.name as equipment_name, s.name as section_name,
                   d.name as department_name, b.name as building_name
            FROM inventory i
            LEFT JOIN equipment e ON i.equipment_id = e.id
            LEFT JOIN sections s ON i.section_id = s.id
            LEFT JOIN departments d ON s.department_id = d.id
            LEFT JOIN buildings b ON d.building_id = b.id
            WHERE TRIM(REPLACE(REPLACE(REPLACE(i.barcode_data, ' ', ''), '\t', ''), '\n', '')) = TRIM(REPLACE(REPLACE(REPLACE(?, ' ', ''), '\t', ''), '\n', ''))
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
            // determine if this row belongs to a multiple set
            $is_multiple = preg_match('/-\d+$/', $row['property_no']);
            $total_qty = (int)($row['qty_physical_count'] ?? 0);
            if ($is_multiple) {
                $base = preg_replace('/-\d+$/', '', $row['property_no']);
                $sumStmt = $conn->prepare("SELECT SUM(qty_physical_count) as total FROM inventory WHERE property_no LIKE CONCAT(?, '-%')");
                if ($sumStmt) {
                    $sumStmt->bind_param("s", $base);
                    if ($sumStmt->execute()) {
                        $sr = $sumStmt->get_result();
                        if ($sr && $srow = $sr->fetch_assoc()) {
                            $total_qty = (int)$srow['total'];
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
                    'barcode_data' => htmlspecialchars($row['barcode_data'] ?? '', ENT_QUOTES, 'UTF-8'),
                    'category' => htmlspecialchars($row['category'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'equipment_name' => htmlspecialchars($row['equipment_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'quantity' => (int)($row['qty_physical_count'] ?? 0),
                    'total_qty' => $total_qty,
                    'uom' => htmlspecialchars($row['uom'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'unit_value' => (float)($row['unit_value'] ?? 0),
                    'location' => $row['section_name'] ? htmlspecialchars($row['section_name'], ENT_QUOTES, 'UTF-8') . ($row['department_name'] ? ' - ' . htmlspecialchars($row['department_name'], ENT_QUOTES, 'UTF-8') : '') : 'N/A',
                    'building' => htmlspecialchars($row['building_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'condition_text' => htmlspecialchars($row['condition_text'] ?? 'Good', ENT_QUOTES, 'UTF-8'),
                    'is_issued' => $is_issued,
                    'is_multiple' => $is_multiple
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
        error_log("Barcode scanner error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'An internal server error occurred']);
    }
    exit;
}

// Handle live status updates via AJAX
if (isset($_GET['get_item_status'])) {
    header('Content-Type: application/json');

    try {
        // CSRF validation
        if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
            http_response_code(403);
            echo json_encode(['error' => 'Invalid security token']);
            exit;
        }

        $item_id = isset($_GET['item_id']) ? (int)$_GET['item_id'] : 0;

        if (!$item_id) {
            echo json_encode(['error' => 'Invalid item ID']);
            exit;
        }

        // Get current item status from database
        $stmt = $conn->prepare("
            SELECT i.*, e.name as equipment_name, s.name as section_name,
                   d.name as department_name, b.name as building_name
            FROM inventory i
            LEFT JOIN equipment e ON i.equipment_id = e.id
            LEFT JOIN sections s ON i.section_id = s.id
            LEFT JOIN departments d ON s.department_id = d.id
            LEFT JOIN buildings b ON d.building_id = b.id
            WHERE i.id = ?
            LIMIT 1
        ");

        if (!$stmt) {
            throw new Exception("Database prepare error: " . $conn->error);
        }

        $stmt->bind_param("i", $item_id);

        if (!$stmt->execute()) {
            throw new Exception("Database execute error: " . $stmt->error);
        }

        $result = $stmt->get_result();

        if ($result && $row = $result->fetch_assoc()) {
            $is_multiple = preg_match('/-\d+$/', $row['property_no']);
            $total_qty = (int)($row['qty_physical_count'] ?? 0);

            if ($is_multiple) {
                $base = preg_replace('/-\d+$/', '', $row['property_no']);
                $sumStmt = $conn->prepare("SELECT SUM(qty_physical_count) as total FROM inventory WHERE property_no LIKE CONCAT(?, '-%')");
                if ($sumStmt) {
                    $sumStmt->bind_param("s", $base);
                    if ($sumStmt->execute()) {
                        $sr = $sumStmt->get_result();
                        if ($sr && $srow = $sr->fetch_assoc()) {
                            $total_qty = (int)$srow['total'];
                        }
                    }
                    $sumStmt->close();
                }
            }

            $is_issued = checkIfIssued($conn, $row['id']);

            echo json_encode([
                'success' => true,
                'item' => [
                    'id' => (int)$row['id'],
                    'quantity' => (int)($row['qty_physical_count'] ?? 0),
                    'total_qty' => $total_qty,
                    'location' => $row['section_name'] ? htmlspecialchars($row['section_name'], ENT_QUOTES, 'UTF-8') . ($row['department_name'] ? ' - ' . htmlspecialchars($row['department_name'], ENT_QUOTES, 'UTF-8') : '') : 'N/A',
                    'building' => htmlspecialchars($row['building_name'] ?? 'N/A', ENT_QUOTES, 'UTF-8'),
                    'condition_text' => htmlspecialchars($row['condition_text'] ?? 'Good', ENT_QUOTES, 'UTF-8'),
                    'is_issued' => $is_issued
                ]
            ]);
        } else {
            echo json_encode(['error' => 'Item not found']);
        }

        $stmt->close();

    } catch (Exception $e) {
        error_log("Get item status error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'An internal server error occurred']);
    }
    exit;
}

// Helper function to check if item is issued (with prepared statement)
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

<!-- Scanner Interface -->
<div class="scanner-container">
    <div class="scanner-header">
        <h2><i class="fas fa-barcode"></i> Barcode Scanner</h2>
        <p>Scan a barcode to instantly find and manage inventory items</p>
    </div>

    <div class="scanner-main">
        <!-- Left Column - Scanner -->
        <div class="scanner-left">
            <div class="scanner-card">
                <h3><i class="fas fa-camera"></i> Scan Barcode</h3>
                
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
                        <select id="camera-select" class="form-control" style="width: auto; flex: 1;" onchange="switchCamera()">
                            <option value="">Select Camera</option>
                        </select>
                    </div>
                    <div class="scanner-instructions">
                        <p><i class="fas fa-info-circle"></i> Position the barcode in the center of the red line for best results</p>
                    </div>
                </div>

                <!-- File Upload Scanner -->
                <div id="fileScannerSection" class="file-section" style="display: none;">
                    <div class="file-upload-area" id="fileUploadArea">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Drag & drop an image or click to upload</p>
                        <input type="file" id="barcodeImage" accept="image/*" style="display: none;">
                        <button class="btn btn-secondary" onclick="document.getElementById('barcodeImage').click()">
                            <i class="fas fa-folder-open"></i> Browse
                        </button>
                    </div>
                    <div id="imagePreview" class="image-preview" style="display: none;">
                        <img id="previewImg" src="" alt="Preview">
                        <button class="btn btn-primary" onclick="scanImageFile()">
                            <i class="fas fa-search"></i> Scan Image
                        </button>
                        <button class="btn btn-secondary" onclick="resetImageUpload()" style="margin-top: 10px;">
                            <i class="fas fa-undo"></i> Upload Another
                        </button>
                    </div>
                </div>

                <!-- Recent Scans -->
                <div class="recent-scans">
                    <h4><i class="fas fa-history"></i> Recent Scans</h4>
                    <div id="recentScansList" class="recent-scans-list">
                        <!-- Recent scans will appear here -->
                    </div>
                    <button class="btn btn-sm btn-secondary" onclick="clearRecentScans()" style="margin-top: 10px;">
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
                    <i class="fas fa-barcode"></i>
                    <p>Scan a barcode to see item details</p>
                </div>
            </div>

            <!-- Quick Actions for Scanned Item -->
            <div id="quickActions" class="quick-actions" style="display: none;">
                <h4><i class="fas fa-bolt"></i> Quick Actions</h4>
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
    <div class="modal-content" style="max-width: 600px;">
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

<!-- Success Sound (for when barcode is detected) -->
<audio id="beep-sound" src="data:audio/wav;base64,UklGRlwAAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YVQAAACAgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f3+AgICAf39/f39/f38=" preload="auto"></audio>

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


<script>
let currentScannedItem = null;
let recentScans = [];
let scannerInitialized = false;
let activeStream = null;
let currentCamera = null;
let cameras = [];
let csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
let statusPollingInterval = null;
let statusPollingEnabled = false;

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
    
    document.getElementById('scanResult').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Searching...</div>';
    
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
    // Load recent scans from localStorage with error handling
    try {
        recentScans = JSON.parse(localStorage.getItem('recentScans') || '[]');
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
    
    // Auto-initialize camera on page load
    setTimeout(() => {
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
    // Get available cameras
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
            ],
            debug: {
                showCanvas: true,
                showPatches: true,
                showFoundPatches: true,
                showSkeleton: true,
                showLabels: true,
                showPatchLabels: true,
                showRemainingPatchLabels: true,
                boxFromPatches: {
                    showTransformed: true,
                    showTransformedBox: true,
                    showBB: true
                }
            }
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
        
        // Draw a line in the middle for better UX
        Quagga.onProcessed(function(result) {
            let drawingCtx = Quagga.canvas.ctx.overlay;
            let drawingCanvas = Quagga.canvas.dom.overlay;
            
            if (result) {
                if (result.boxes) {
                    drawingCtx.clearRect(0, 0, parseInt(drawingCanvas.getAttribute("width")), parseInt(drawingCanvas.getAttribute("height")));
                    result.boxes.filter(function(box) {
                        return box !== result.box;
                    }).forEach(function(box) {
                        Quagga.ImageDebug.drawPath(box, {x: 0, y: 1}, drawingCtx, {color: "green", lineWidth: 2});
                    });
                }
                
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
        
        // Handle detected barcodes
        Quagga.onDetected(function(result) {
            let code = result.codeResult.code;
            if (code) {
                // Play beep sound
                document.getElementById('beep-sound').play().catch(e => console.log('Audio play failed:', e));
                
                // Stop scanner temporarily to prevent multiple scans
                stopScanner();
                
                // Set the value in the live search input and perform search
                const liveInput = document.getElementById('live_barcode_input');
                if (liveInput) {
                    liveInput.value = code;
                    searchLiveBarcode();
                } else {
                    performBarcodeSearch(code);
                }
                
                // Show success flash
                document.getElementById('scanner-status').textContent = 'Barcode detected: ' + code;
                
                // Restart scanner after 2 seconds
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

// Handle image file for barcode scanning
function handleImageFile(file) {
    // Validate file size (max 5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('Image too large. Please choose an image under 5MB.');
        return;
    }
    
    // Validate file type
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

// Scan barcode from uploaded image
function scanImageFile() {
    let img = document.getElementById('previewImg');
    if (!img.src) return;
    
    // Show loading
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
        // Remove loading indicator
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
            
            // Play beep sound
            document.getElementById('beep-sound').play().catch(e => console.log('Audio play failed:', e));
        } else {
            alert('No barcode found in the image. Please try another image.');
        }
    });
}

// Validate barcode format
function validateBarcode(barcode) {
    // Allow alphanumeric, hyphens, underscores, dots, plus signs, and spaces
    return /^[A-Za-z0-9\-_\.\+\s]+$/.test(barcode);
}

// Display error message
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

// Display scanned item
function displayScannedItem(item) {
    currentScannedItem = item;
    
    let html = `
        <div class="found-item">
            <div class="item-detail-card">
                <h3>${escapeHtml(item.article_name)}</h3>
                
                <div class="item-detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Property No.</span>
                        <span class="detail-value">${escapeHtml(item.property_no)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Category</span>
                        <span class="detail-value">${escapeHtml(item.category || 'N/A')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Equipment</span>
                        <span class="detail-value">${escapeHtml(item.equipment_name || 'N/A')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Quantity</span>
                        <span class="detail-value highlight">${item.total_qty !== undefined ? item.total_qty : item.quantity} ${escapeHtml(item.uom)}</span>
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
                            <span class="status-badge" style="background-color: #17a2b8; color: white;">
                                Multiple Item Set
                            </span>
                        </span>
                    </div>
                    ` : ''}
                </div>
                
                <div class="barcode-preview">
                    <img src="generate_barcode.php?code=${encodeURIComponent(item.barcode_data)}&width=300&height=80" 
                         alt="Barcode" onerror="this.style.display='none'">
                    <div style="font-family: monospace; margin-top: 5px;">${escapeHtml(item.barcode_data)}</div>
                </div>
                
                ${item.is_multiple ? `
                <div style="margin-top: 15px; padding: 10px; background: #e8f4f8; border-radius: 5px;">
                    <i class="fas fa-info-circle" style="color: #2196F3;"></i>
                    <small> This item is part of a multiple item set. Use the "Add Barcodes" button in the main inventory to add more items to this set.</small>
                </div>
                ` : ''}
            </div>
        </div>
    `;
    
    document.getElementById('scanResult').innerHTML = html;
    document.getElementById('quickActions').style.display = 'block';

    // Start polling for live status updates
    startStatusPolling(item.id);
}

// Display not found
function displayNotFound(barcode) {
    let html = `
        <div class="not-found">
            <i class="fas fa-exclamation-circle"></i>
            <h3>Item Not Found</h3>
            <p>No item found with barcode: <strong>${escapeHtml(barcode)}</strong></p>
            <div style="margin-top: 20px;">
                <button class="btn btn-primary" onclick="openAddModalWithBarcode('${escapeHtml(barcode)}')">
                    <i class="fas fa-plus"></i> Add New Item with this Barcode
                </button>
                <button class="btn btn-secondary" onclick="resetScanner()" style="margin-left: 10px;">
                    <i class="fas fa-redo"></i> Scan Again
                </button>
            </div>
        </div>
    `;
    
    document.getElementById('scanResult').innerHTML = html;
    document.getElementById('quickActions').style.display = 'none';
}

// Reset scanner
function resetScanner() {
    document.getElementById('scanResult').innerHTML = `
        <div class="scan-placeholder">
            <i class="fas fa-barcode"></i>
            <p>Scan a barcode to see item details</p>
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

    // Stop polling when scanner is reset
    stopStatusPolling();
}

// Start polling for live status updates
function startStatusPolling(itemId) {
    // Stop any existing polling
    stopStatusPolling();

    statusPollingEnabled = true;
    console.log('Starting status polling for item ID:', itemId);

    // Poll every 3 seconds for status updates
    statusPollingInterval = setInterval(() => {
        if (!statusPollingEnabled) {
            clearInterval(statusPollingInterval);
            return;
        }

        console.log('Polling for item status update...');
        fetch(`?get_item_status=1&item_id=${itemId}&csrf_token=${encodeURIComponent(csrfToken)}`)
            .then(response => {
                if (!response.ok) {
                    console.error('Polling failed:', response.status);
                    return null;
                }
                return response.json();
            })
            .then(data => {
                if (data && data.success && data.item && currentScannedItem) {
                    console.log('Received item status:', data.item);
                    updateItemDisplayIfChanged(data.item);
                } else if (data && data.error) {
                    console.error('Poll error:', data.error);
                }
            })
            .catch(error => console.error('Status polling error:', error));
    }, 3000); // Poll every 3 seconds
}

// Stop polling for status updates
function stopStatusPolling() {
    statusPollingEnabled = false;
    if (statusPollingInterval) {
        clearInterval(statusPollingInterval);
        statusPollingInterval = null;
    }
}

// Update item display if data has changed
function updateItemDisplayIfChanged(newData) {
    if (!currentScannedItem) return;

    let hasChanges = false;
    let changedFields = [];

    // Check for quantity changes
    if (newData.total_qty !== currentScannedItem.total_qty ||
        newData.quantity !== currentScannedItem.quantity) {
        hasChanges = true;
        changedFields.push('quantity');
        console.log('Quantity changed from', currentScannedItem.total_qty, 'to', newData.total_qty);
        currentScannedItem.quantity = newData.quantity;
        currentScannedItem.total_qty = newData.total_qty;
    }

    // Check for location changes
    if (newData.location !== currentScannedItem.location) {
        hasChanges = true;
        changedFields.push('location');
        console.log('Location changed from', currentScannedItem.location, 'to', newData.location);
        currentScannedItem.location = newData.location;
    }

    // Check for condition changes
    if (newData.condition_text !== currentScannedItem.condition_text) {
        hasChanges = true;
        changedFields.push('condition');
        console.log('Condition changed from', currentScannedItem.condition_text, 'to', newData.condition_text);
        currentScannedItem.condition_text = newData.condition_text;
    }

    // Check for issuance status changes
    if (newData.is_issued !== currentScannedItem.is_issued) {
        hasChanges = true;
        changedFields.push('status');
        console.log('Status changed from', currentScannedItem.is_issued, 'to', newData.is_issued);
        currentScannedItem.is_issued = newData.is_issued;
    }

    // Update UI if changes detected
    if (hasChanges) {
        console.log('Updating fields:', changedFields);
        updateLiveFields(changedFields);
        showUpdateNotification(changedFields);
    }
}

// Update specific fields in the display
function updateLiveFields(changedFields) {
    const detailItems = document.querySelectorAll('.detail-item');

    detailItems.forEach(item => {
        const label = item.querySelector('.detail-label');
        if (!label) return;

        const labelText = label.textContent.toLowerCase().trim();

        if (changedFields.includes('quantity') && labelText.includes('quantity')) {
            const valueSpan = item.querySelector('.detail-value.highlight');
            if (valueSpan) {
                const newQty = currentScannedItem.total_qty !== undefined ?
                               currentScannedItem.total_qty : currentScannedItem.quantity;
                valueSpan.textContent = `${newQty} ${escapeHtml(currentScannedItem.uom)}`;
                highlightChange(item);
                console.log('Updated quantity to:', newQty);
            }
        }

        if (changedFields.includes('location') && labelText.includes('location')) {
            const valueSpan = item.querySelector('.detail-value');
            if (valueSpan) {
                valueSpan.textContent = escapeHtml(currentScannedItem.location);
                highlightChange(item);
                console.log('Updated location to:', currentScannedItem.location);
            }
        }

        if (changedFields.includes('condition') && labelText.includes('condition')) {
            const valueSpan = item.querySelector('.detail-value');
            if (valueSpan) {
                valueSpan.textContent = escapeHtml(currentScannedItem.condition_text);
                highlightChange(item);
                console.log('Updated condition to:', currentScannedItem.condition_text);
            }
        }

        if (changedFields.includes('status') && labelText.includes('status')) {
            const statusBadge = item.querySelector('.detail-value .status-badge');
            if (statusBadge) {
                statusBadge.textContent = currentScannedItem.is_issued ? 'Issued' : 'Available';
                statusBadge.style.backgroundColor = currentScannedItem.is_issued ? '#dc3545' : '#28a745';
                statusBadge.style.color = 'white';
                highlightChange(item);
                console.log('Updated status to:', currentScannedItem.is_issued ? 'Issued' : 'Available');
            }
        }
    });
}

// Highlight changed fields with animation
function highlightChange(element) {
    element.style.transition = 'background-color 0.3s ease';
    element.style.backgroundColor = '#fff3cd';
    setTimeout(() => {
        element.style.transition = 'background-color 0.5s ease';
        element.style.backgroundColor = 'transparent';
    }, 100);
}

// Show notification of updated fields
function showUpdateNotification(changedFields) {
    const fieldNames = changedFields.map(f => {
        const names = {
            'quantity': 'Quantity',
            'location': 'Location',
            'condition': 'Condition',
            'status': 'Status'
        };
        return names[f] || f;
    }).join(', ');

    const notification = document.createElement('div');
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: #17a2b8;
        color: white;
        padding: 15px 20px;
        border-radius: 5px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
        z-index: 9999;
        animation: slideIn 0.3s ease;
        font-size: 14px;
    `;
    notification.innerHTML = `<i class="fas fa-sync-alt"></i> ${fieldNames} updated`;
    document.body.appendChild(notification);

    setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease';
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}


// Add to recent scans
function addToRecentScans(item) {
    // Check if already exists
    let existingIndex = recentScans.findIndex(scan => scan.id === item.id);
    if (existingIndex !== -1) {
        recentScans.splice(existingIndex, 1);
    }
    
    // Add to beginning of array
    recentScans.unshift({
        id: item.id,
        name: item.article_name,
        barcode: item.barcode_data,
        time: new Date().toLocaleTimeString()
    });
    
    // Keep only last 10 scans
    if (recentScans.length > 10) {
        recentScans.pop();
    }
    
    // Save to localStorage
    try {
        localStorage.setItem('recentScans', JSON.stringify(recentScans));
    } catch (e) {
        console.error('Error saving recent scans:', e);
    }
    
    // Display updated list
    displayRecentScans();
}

// Display recent scans
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

// Rescan a barcode from history
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
    if (confirm('Clear recent scan history?')) {
        recentScans = [];
        try {
            localStorage.setItem('recentScans', JSON.stringify(recentScans));
        } catch (e) {
            console.error('Error clearing recent scans:', e);
        }
        displayRecentScans();
    }
}

// Quick action functions
function editCurrentItem() {
    if (currentScannedItem) {
        window.location.href = 'add_inventory.php?id=' + currentScannedItem.id;
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

// Open add modal with pre-filled barcode
function openAddModalWithBarcode(barcode) {
    window.location.href = 'add_inventory.php?barcode=' + encodeURIComponent(barcode);
}

// View item details in modal
function viewItemDetails(itemId) {
    // Show loading
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
                <div style="margin-bottom: 20px;">
                    <h3>${escapeHtml(data.article_name || '')}</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr><td style="padding: 8px 0;"><strong>Property No:</strong>NonNullConnector<td>${escapeHtml(data.property_no || 'N/A')}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Description:</strong>NonNullConnector<td>${escapeHtml(data.description || 'N/A')}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Category:</strong>NonNullConnector<td>${escapeHtml(data.category || 'N/A')}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Equipment Type:</strong>NonNullConnector<td>${escapeHtml(data.equipment_name || 'N/A')}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Quantity:</strong>NonNullConnector<td>${escapeHtml(data.qty_physical_count || '0')} ${escapeHtml(data.uom || '')}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Unit Value:</strong>NonNullConnector<td>${formatCurrency(data.unit_value)}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Total Value:</strong>NonNullConnector<td>${formatCurrency((data.unit_value || 0) * (data.qty_physical_count || 0))}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Location:</strong>NonNullConnector<td>${escapeHtml(data.section_name || 'N/A')}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Condition:</strong>NonNullConnector<td>${escapeHtml(data.condition_text || 'Good')}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Fund Cluster:</strong>NonNullConnector<td>${escapeHtml(data.fund_cluster || 'N/A')}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Date Added:</strong>NonNullConnector<td>${formatDate(data.date_added)}ERC20</tr>
                        <tr><td style="padding: 8px 0;"><strong>Remarks:</strong>NonNullConnector<td>${escapeHtml(data.remarks || 'N/A')}ERC20</tr>
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

// Print barcode function
function printBarcode(barcodeData, itemName) {
    let printWindow = window.open('', '_blank');
    let timestamp = new Date().getTime();
    
    printWindow.document.write(`
        <html>
        <head>
            <title>Print Barcode - ${escapeHtml(itemName)}</title>
            <style>
                body { text-align: center; font-family: Arial, sans-serif; margin: 0; padding: 20px; background: white; }
                .barcode-container { margin: 20px auto; padding: 20px; border: 1px dashed #6B8CFF; border-radius: 10px; }
                .barcode-img { max-width: 100%; height: auto; margin-bottom: 15px; }
                .item-name { margin-top: 15px; font-size: 16px; font-weight: bold; color: #3A3A3A; }
                .barcode-number { font-family: monospace; font-size: 14px; margin-top: 10px; color: #6B8CFF; }
                @media print { body { margin: 0; padding: 10px; } .barcode-container { border: none; } }
            </style>
        </head>
        <body>
            <div class="barcode-container">
                <img src="generate_barcode.php?code=${encodeURIComponent(barcodeData)}&width=400&height=100&t=${timestamp}" 
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
        return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
    } catch (e) {
        return 'N/A';
    }
}

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (Quagga) {
        try {
            Quagga.stop();
        } catch (e) {
            // Ignore errors during unload
        }
    }
});

// Close modals when clicking outside
window.onclick = function(event) {
    let itemDetailsModal = document.getElementById('itemDetailsModal');
    if (event.target == itemDetailsModal) {
        closeItemDetailsModal();
    }
}
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>