<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'Semi-Expendable Barcode Scanner';
$page_description = 'Scan barcodes to quickly find Semi-Expendable items';

// Handle barcode lookup via AJAX
if (isset($_GET['scan_barcode'])) {
    header('Content-Type: application/json');
    
    try {
        // CSRF validation
        if (!isset($_GET['csrf_token']) || $_GET['csrf_token'] !== $_SESSION['csrf_token']) {
            echo json_encode(['error' => 'Invalid security token']);
            exit;
        }
        
        // Get barcode
        $barcode = isset($_GET['barcode']) ? trim($_GET['barcode']) : '';
        
        if (empty($barcode)) {
            echo json_encode(['error' => 'Please provide a barcode']);
            exit;
        }
        
        // FIRST: Search in equipment_issuance table (issued items)
        $issued_query = "
            SELECT 
                ei.id as issuance_id,
                ei.inventory_id as id,
                ei.issuance_barcode as property_no,
                ei.quantity_issued as qty_physical_count,
                ei.condition_on_issue as condition_text,
                ei.remarks,
                ei.issued_date,
                ei.status,
                s.article_name,
                s.description,
                s.big_unit,
                s.big_quantity,
                s.small_unit,
                s.pieces_per_big_unit,
                s.unit_value,
                s.fund_cluster,
                s.barcode_data,
                CONCAT(emp.firstname, ' ', emp.lastname) as issued_to_name,
                'issued_item' as source_table
            FROM equipment_issuance ei
            JOIN semi_ppe s ON ei.inventory_id = s.id
            LEFT JOIN employees emp ON ei.issued_to = emp.id
            WHERE ei.issuance_barcode = ?
            LIMIT 1
        ";
        
        $stmt = $conn->prepare($issued_query);
        $stmt->bind_param("s", $barcode);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        
        // If not found in issued items, search in semi_ppe table
        if (!$row) {
            $semi_query = "
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
                    condition_text,
                    fund_cluster,
                    remarks,
                    'semi_ppe' as source_table,
                    NULL as issued_to_name,
                    NULL as issued_date,
                    NULL as status
                FROM semi_ppe 
                WHERE barcode_data = ? OR property_no = ?
                LIMIT 1
            ";
            
            $stmt = $conn->prepare($semi_query);
            $stmt->bind_param("ss", $barcode, $barcode);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();
        }
        
        if ($row) {
            // Calculate display values
            $big_unit_display = !empty($row['big_quantity']) && !empty($row['big_unit']) 
                ? $row['big_quantity'] . ' ' . $row['big_unit'] 
                : '—';
            $small_unit_display = !empty($row['pieces_per_big_unit']) && !empty($row['small_unit']) 
                ? $row['pieces_per_big_unit'] . ' ' . $row['small_unit'] 
                : '—';
            $total_qty = (float)($row['qty_physical_count'] ?? 0);
            
            // Determine if item is issued
            $is_issued = ($row['source_table'] === 'issued_item');
            
            // Get the issued property number (if issued)
            $issued_property_no = $is_issued ? $row['property_no'] : null;
            $original_property_no = !$is_issued ? $row['property_no'] : null;
            
            echo json_encode([
                'success' => true,
                'found' => true,
                'item' => [
                    'id' => (int)$row['id'],
                    'article_name' => htmlspecialchars($row['article_name'] ?? ''),
                    'original_property_no' => htmlspecialchars($original_property_no ?? $row['property_no']),
                    'issued_property_no' => htmlspecialchars($issued_property_no ?? ''),
                    'description' => htmlspecialchars($row['description'] ?? ''),
                    'barcode_data' => htmlspecialchars($row['barcode_data'] ?? $row['property_no']),
                    'big_unit_display' => $big_unit_display,
                    'small_unit_display' => $small_unit_display,
                    'quantity' => (float)$row['qty_physical_count'],
                    'total_qty' => $total_qty,
                    'unit_value' => (float)$row['unit_value'],
                    'condition_text' => htmlspecialchars($row['condition_text'] ?? 'Good'),
                    'fund_cluster' => htmlspecialchars($row['fund_cluster'] ?? 'N/A'),
                    'is_issued' => $is_issued,
                    'source_table' => $row['source_table'],
                    'remarks' => htmlspecialchars($row['remarks'] ?? ''),
                    'uom' => $small_unit_display,
                    'issued_to_name' => $row['issued_to_name'] ?? null,
                    'issued_date' => $row['issued_date'] ?? null,
                    'issuance_status' => $row['status'] ?? null
                ]
            ]);
        } else {
            echo json_encode([
                'success' => true,
                'found' => false,
                'message' => 'No item found with this barcode',
                'barcode' => htmlspecialchars($barcode)
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Error: ' . $e->getMessage()]);
    }
    exit;
}

// If not AJAX, include header
include INCLUDE_PATH . '/header.php';
?>

<!-- Scanner Page -->
<style>
.scanner-container { padding: 20px; max-width: 1200px; margin: 0 auto; }
.scanner-header { text-align: center; margin-bottom: 30px; }
.scanner-header h2 { color: #6B8CFF; }
.scanner-main { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
.scanner-left, .scanner-right { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.search-input-section { margin-bottom: 20px; }
.search-input-section label { display: block; margin-bottom: 8px; font-weight: bold; color: #6B8CFF; }
.input-group { display: flex; gap: 10px; }
.form-control { flex: 1; padding: 12px; border: 2px solid #ddd; border-radius: 8px; font-size: 16px; }
.btn-primary { background: #F8B0C0; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-weight: bold; }
.scan-result { min-height: 300px; }
.scan-placeholder { text-align: center; padding: 60px 20px; color: #999; }
.item-detail-card { background: #f5f5f5; border-radius: 10px; padding: 20px; border-left: 4px solid #F8B0C0; }
.item-detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
.detail-item { display: flex; flex-direction: column; }
.detail-label { font-size: 12px; color: #999; }
.detail-value { font-size: 16px; font-weight: 500; color: #6B8CFF; }
.detail-value.highlight { color: #F8B0C0; }
.barcode-preview { text-align: center; margin-top: 20px; padding: 15px; background: white; border-radius: 8px; }
.status-badge { display: inline-block; padding: 5px 10px; border-radius: 20px; font-size: 12px; }
.status-badge.available { background: #C5E8C5; color: #4CAF50; }
.status-badge.issued { background: #FFD8E0; color: #F8B0C0; }
.error-message { background: #ffebee; color: #f44336; padding: 20px; text-align: center; border-radius: 8px; }
.not-found { text-align: center; padding: 40px; color: #f44336; }
.btn-secondary { background: #6c757d; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; margin-top: 10px; }
.loading { text-align: center; padding: 40px; }
.found-item { animation: fadeIn 0.5s; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.quick-actions { margin-top: 20px; padding-top: 20px; border-top: 2px solid #FFD8E0; }
.action-buttons { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 15px; }
.btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; }
.btn-success { background: #C5E8C5; color: #4CAF50; }
.btn-info { background: #8FB5FF; color: white; }
.btn-warning { background: #FFD8E0; color: #F8B0C0; }
.issued-info { background: #FFF8E1; padding: 15px; border-radius: 8px; margin-bottom: 15px; border-left: 4px solid #FF9800; }
.issued-info-title { font-weight: bold; color: #FF9800; margin-bottom: 10px; }
@media (max-width: 768px) { .scanner-main { grid-template-columns: 1fr; } }
</style>

<div class="scanner-container">
    <div class="scanner-header">
        <h2><i class="fas fa-box-open"></i> Semi-Expendable Barcode Scanner</h2>
        <p>Scan barcodes to instantly find and manage Semi-Expendable items</p>
    </div>

    <div class="scanner-main">
        <!-- Left Column -->
        <div class="scanner-left">
            <div class="search-input-section">
                <label><i class="fas fa-search"></i> Enter Barcode:</label>
                <div class="input-group">
                    <input type="text" id="barcode_input" class="form-control" placeholder="Type barcode and press Enter..." autofocus>
                    <button class="btn-primary" onclick="searchBarcode()"><i class="fas fa-search"></i> Find</button>
                </div>
            </div>
            <div class="info-text" style="margin-top: 20px; padding: 15px; background: #F0F0F0; border-radius: 8px;">
                <small><i class="fas fa-info-circle"></i> You can scan:</small><br>
                <small>• Original item barcode (from semi_ppe table)</small><br>
                <small>• Issued item barcode (from equipment_issuance table)</small>
            </div>
        </div>

        <!-- Right Column -->
        <div class="scanner-right">
            <div id="scanResult" class="scan-result">
                <div class="scan-placeholder"><i class="fas fa-box-open"></i><p>Scan a Semi-Expendable barcode to see item details</p></div>
            </div>
            <div id="quickActions" class="quick-actions" style="display: none;">
                <h4>Quick Actions</h4>
                <div class="action-buttons">
                    <button class="btn btn-warning" onclick="editCurrentItem()"><i class="fas fa-edit"></i> Edit Item</button>
                    <button class="btn btn-success" onclick="issueCurrentItem()"><i class="fas fa-hand-holding"></i> Issue Item</button>
                    <button class="btn btn-info" onclick="printCurrentItemBarcode()"><i class="fas fa-print"></i> Print Barcode</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentScannedItem = null;
let csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';

function searchBarcode() {
    const barcode = document.getElementById('barcode_input').value.trim();
    if (!barcode) {
        alert('Please enter a barcode');
        return;
    }
    
    document.getElementById('scanResult').innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i><br>Searching...</div>';
    
    fetch('?scan_barcode=1&barcode=' + encodeURIComponent(barcode) + '&csrf_token=' + encodeURIComponent(csrfToken))
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.found) {
                    displayItem(data.item);
                } else {
                    displayNotFound(data.barcode);
                }
            } else {
                showError(data.error || 'Unknown error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showError('Error scanning barcode');
        });
}

function displayItem(item) {
    currentScannedItem = item;
    
    let issuedInfo = '';
    if (item.is_issued && item.issued_to_name) {
        issuedInfo = `
            <div class="issued-info">
                <div class="issued-info-title"><i class="fas fa-info-circle"></i> Issued Item Information</div>
                <div class="item-detail-grid">
                    <div class="detail-item">
                        <span class="detail-label">Issued To</span>
                        <span class="detail-value">${escapeHtml(item.issued_to_name)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Issued Date</span>
                        <span class="detail-value">${escapeHtml(item.issued_date ? new Date(item.issued_date).toLocaleDateString() : 'N/A')}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Issued Property No.</span>
                        <span class="detail-value highlight">${escapeHtml(item.issued_property_no || item.original_property_no)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Original Property No.</span>
                        <span class="detail-value">${escapeHtml(item.original_property_no)}</span>
                    </div>
                    <div class="detail-item">
                        <span class="detail-label">Issuance Status</span>
                        <span class="detail-value">${escapeHtml(item.issuance_status || 'Issued')}</span>
                    </div>
                </div>
            </div>
        `;
    }
    
    let html = `<div class="found-item">
        <div class="item-detail-card">
            <h3>${escapeHtml(item.article_name)} <span style="background:#F8B0C0;padding:3px 10px;border-radius:15px;font-size:12px;">Semi-Expendable</span></h3>
            ${issuedInfo}
            <div class="item-detail-grid">
                <div class="detail-item"><span class="detail-label">Property No.</span><span class="detail-value">${escapeHtml(item.is_issued ? item.issued_property_no : item.original_property_no)}</span></div>
                <div class="detail-item"><span class="detail-label">Big Unit</span><span class="detail-value">${escapeHtml(item.big_unit_display)}</span></div>
                <div class="detail-item"><span class="detail-label">Small Unit</span><span class="detail-value">${escapeHtml(item.small_unit_display)}</span></div>
                <div class="detail-item"><span class="detail-label">Total Quantity</span><span class="detail-value"><strong>${item.total_qty || item.quantity}</strong></span></div>
                <div class="detail-item"><span class="detail-label">Unit Value</span><span class="detail-value">₱${parseFloat(item.unit_value).toFixed(2)}</span></div>
                <div class="detail-item"><span class="detail-label">Total Value</span><span class="detail-value">₱${(item.unit_value * (item.total_qty || item.quantity)).toFixed(2)}</span></div>
                <div class="detail-item"><span class="detail-label">Fund Cluster</span><span class="detail-value">${escapeHtml(item.fund_cluster)}</span></div>
                <div class="detail-item"><span class="detail-label">Condition</span><span class="detail-value">${escapeHtml(item.condition_text)}</span></div>
                <div class="detail-item"><span class="detail-label">Status</span><span class="detail-value"><span class="status-badge ${item.is_issued ? 'issued' : 'available'}">${item.is_issued ? 'Issued' : 'Available'}</span></span></div>
            </div>
            ${item.description ? `<div style="margin-top:15px;"><strong>Description:</strong><br>${escapeHtml(item.description)}</div>` : ''}
            <div class="barcode-preview">
                <img src="barcode_generator_issued.php?code=${encodeURIComponent(item.barcode_data)}&width=300&height=60" alt="Barcode" onerror="this.style.display='none'">
                <div style="font-family:monospace;margin-top:10px;">${escapeHtml(item.barcode_data)}</div>
            </div>
        </div>
    </div>`;
    
    document.getElementById('scanResult').innerHTML = html;
    document.getElementById('quickActions').style.display = 'block';
    document.getElementById('barcode_input').value = '';
    document.getElementById('barcode_input').focus();
}

function displayNotFound(barcode) {
    document.getElementById('scanResult').innerHTML = `<div class="not-found"><i class="fas fa-box-open"></i><h3>Item Not Found</h3><p>No Semi-Expendable item found with barcode: <strong>${escapeHtml(barcode)}</strong></p><button class="btn-secondary" onclick="resetScanner()">Try Again</button></div>`;
    document.getElementById('quickActions').style.display = 'none';
}

function showError(message) {
    document.getElementById('scanResult').innerHTML = `<div class="error-message"><i class="fas fa-exclamation-circle"></i><p>${escapeHtml(message)}</p><button class="btn-secondary" onclick="resetScanner()">Try Again</button></div>`;
    document.getElementById('quickActions').style.display = 'none';
}

function resetScanner() {
    document.getElementById('scanResult').innerHTML = '<div class="scan-placeholder"><i class="fas fa-box-open"></i><p>Scan a Semi-Expendable barcode to see item details</p></div>';
    document.getElementById('barcode_input').value = '';
    document.getElementById('barcode_input').focus();
}

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
        let printWindow = window.open('', '_blank');
        let barcodeToPrint = currentScannedItem.is_issued ? currentScannedItem.issued_property_no : currentScannedItem.barcode_data;
        printWindow.document.write(`
            <html>
            <head><title>Print Barcode</title>
            <style>
                body{text-align:center;padding:40px;font-family:Arial}
                .barcode-container{padding:30px;border:1px dashed #6B8CFF;border-radius:10px}
                .semi-label{background:#F8B0C0;padding:5px 15px;border-radius:20px;display:inline-block;margin-bottom:15px}
            </style>
            </head>
            <body>
                <div class="barcode-container">
                    <div class="semi-label">Semi-Expendable</div>
                    <img src="barcode_generator_issued.php?code=${encodeURIComponent(barcodeToPrint)}&width=400&height=100" alt="Barcode">
                    <div style="margin-top:15px;font-weight:bold">${escapeHtml(currentScannedItem.article_name)}</div>
                    <div style="font-family:monospace;margin-top:10px">${escapeHtml(barcodeToPrint)}</div>
                    ${currentScannedItem.is_issued ? `<div style="margin-top:10px;font-size:12px;color:#FF9800;">Issued to: ${escapeHtml(currentScannedItem.issued_to_name)}</div>` : ''}
                </div>
                <script>window.onload=function(){setTimeout(function(){window.print();window.close()},500)}<\/script>
            </body>
            </html>
        `);
        printWindow.document.close();
    }
}

function escapeHtml(text) {
    if (!text) return '';
    let div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.getElementById('barcode_input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        searchBarcode();
    }
});

document.getElementById('barcode_input').focus();
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>