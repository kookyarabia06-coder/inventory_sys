<?php
/**
 * Admin Dashboard
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php'; // Add this for helper functions

// Require admin role
requireRole('admin');

$page_title = 'Admin Dashboard';
$page_description = 'Manage inventory and track items';

// Initialize stats array
$stats = [];

// Total inventory
$result = $conn->query("SELECT 
    COUNT(*) as total_items,
    SUM(qty_physical_count) as total_qty,
    SUM(unit_value * qty_physical_count) as total_value
FROM inventory");
if ($result) {
    $stats['inventory'] = $result->fetch_assoc();
} else {
    $stats['inventory'] = ['total_items' => 0, 'total_qty' => 0, 'total_value' => 0];
}

// Pending issues
$result = $conn->query("SELECT COUNT(*) as count FROM equipment_issuance WHERE status = 'issued'");
$stats['pending_issues'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Low stock items
$result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE qty_physical_count <= 5");
$stats['low_stock'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Recent issuances
$recent_issues = $conn->query("
    SELECT ei.*, 
           i.article_name,
           CONCAT(u.firstname, ' ', u.lastname) as issued_to_name
    FROM equipment_issuance ei
    JOIN inventory i ON ei.inventory_id = i.id
    JOIN users u ON ei.issued_to = u.id
    ORDER BY ei.issued_date DESC
    LIMIT 10
");

// Low stock items list
$low_stock_items = $conn->query("
    SELECT i.*, s.name as section_name 
    FROM inventory i
    LEFT JOIN sections s ON i.section_id = s.id
    WHERE i.qty_physical_count <= 5 
    ORDER BY i.qty_physical_count ASC 
    LIMIT 10
");

include INCLUDE_PATH . '/header.php';
?>

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

body {
    background-color: var(--light);
    color: var(--text-primary);
}

/* Dashboard Cards */
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background: var(--white);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
    border-left: 4px solid var(--primary);
    transition: transform 0.2s, box-shadow 0.2s;
}

.card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(107, 140, 255, 0.15);
}

.card-icon {
    width: 50px;
    height: 50px;
    background: var(--accent-light);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 15px;
}

.card-icon i {
    font-size: 24px;
    color: var(--primary);
}

.card h3 {
    color: var(--text-secondary);
    font-size: 14px;
    margin-bottom: 5px;
    font-weight: 500;
}

.card .card-value {
    color: var(--primary);
    font-size: 28px;
    font-weight: bold;
    margin-bottom: 5px;
}

.card .card-label {
    color: var(--text-muted);
    font-size: 12px;
}

/* Table Container */
.table-container {
    background: var(--white);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-light);
}

.table-header h2 {
    color: var(--primary);
    font-size: 18px;
    margin: 0;
}

.table-header h2 i {
    color: var(--accent);
    margin-right: 10px;
}

.table-header p {
    color: var(--text-muted);
    font-size: 14px;
    margin: 0;
}

/* Search Box */
.search-box {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

.search-box input[type="text"],
.search-box select {
    padding: 12px 15px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 14px;
    flex: 1;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.search-box input[type="text"]:focus,
.search-box select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.search-box button {
    padding: 12px 24px;
    background: var(--primary);
    color: var(--text-light);
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.search-box button:hover {
    background: #5a7ae6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.3);
}

/* Table Styles */
table {
    width: 100%;
    border-collapse: collapse;
}

thead tr {
    background: linear-gradient(to right, var(--light), var(--white));
    border-bottom: 2px solid var(--accent-light);
}

th {
    padding: 15px 10px;
    text-align: left;
    color: var(--primary);
    font-weight: 600;
    font-size: 13px;
    white-space: nowrap;
}

td {
    padding: 15px 10px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
    font-size: 13px;
}

tr:hover {
    background-color: var(--light);
}

tr.stock-alert-row {
    background-color: white;
}

tr.stock-alert-row:hover {
     background-color: #f0c0d0;
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
    color: var(--text-light);
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
    font-size: 14px;
}

.action-btn.edit { background-color: var(--secondary); }
.action-btn.view { background-color: var(--primary); }
.action-btn.delete { background-color: var(--danger); }
.action-btn.success { background-color: var(--success); }

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.action-btn:disabled {
    opacity: 0.6;
    cursor: not-allowed;
    transform: none;
}

/* Badges */
.badge-warning {
    background-color: var(--secondary);
    color: var(--text-primary);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.badge-success {
    background-color: var(--success-light);
    color: var(--success);
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
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

.btn-info {
    background-color: var(--info);
    color: var(--text-light);
}

.btn-info:hover {
    background-color: #7a9fe6;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(143, 181, 255, 0.3);
}

.btn-xs {
    padding: 4px 8px;
    font-size: 11px;
    border-radius: 4px;
    background-color: var(--secondary);
    color: var(--text-light);
    border: none;
    cursor: pointer;
}

.btn-xs:hover {
    background-color: #7a9fe6;
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
    backdrop-filter: blur(3px);
}

.modal-content {
    background: var(--white);
    margin: 5% auto;
    border-radius: 12px;
    box-shadow: 0 10px 30px rgba(107, 140, 255, 0.2);
    position: relative;
    animation: modalSlideIn 0.3s;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 25px;
    border-bottom: 2px solid var(--accent-light);
}

.modal-header h2 {
    color: var(--primary);
    font-size: 20px;
    margin: 0;
}

.modal-close {
    font-size: 28px;
    font-weight: bold;
    cursor: pointer;
    color: var(--text-muted);
    transition: color 0.2s;
}

.modal-close:hover {
    color: var(--accent);
}

.modal-body {
    padding: 25px;
    max-height: calc(90vh - 150px);
    overflow-y: auto;
}

.modal-footer {
    padding: 15px 25px;
    border-top: 1px solid var(--border-light);
    text-align: right;
    background: var(--light);
    border-radius: 0 0 12px 12px;
}

/* Form Section */
.form-section {
    background: var(--white);
    padding: 20px;
    margin-bottom: 25px;
    border-radius: 10px;
    border-left: 4px solid var(--primary);
    box-shadow: 0 2px 8px rgba(107, 140, 255, 0.1);
}

.form-section h3 {
    color: var(--primary);
    margin-bottom: 20px;
    font-size: 16px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--accent-light);
}

.form-section h3 i {
    color: var(--accent);
    margin-right: 10px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
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
    transition: border-color 0.2s, box-shadow 0.2s;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.form-control[readonly], .form-control[disabled] {
    background-color: var(--light);
    color: var(--text-secondary);
    cursor: not-allowed;
}

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-row .form-group {
    flex: 1;
}

.form-text {
    font-size: 12px;
    color: var(--text-muted);
    margin-top: 5px;
}

/* Barcode Preview Grid */
.barcode-preview-grid,
.all-barcodes-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
    gap: 15px;
    margin: 15px 0;
    padding: 15px;
    background: var(--light);
    border-radius: 8px;
    max-height: 400px;
    overflow-y: auto;
}

.barcode-preview-card,
.barcode-item-card {
    background: var(--white);
    border: 1px solid var(--border-light);
    border-radius: 8px;
    padding: 15px;
    text-align: center;
    transition: all 0.2s;
}

.barcode-preview-card:hover,
.barcode-item-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.15);
    border-color: var(--primary);
}

.barcode-preview-card .item-number,
.barcode-item-card .item-property {
    font-weight: bold;
    color: var(--primary);
    margin-bottom: 10px;
    font-size: 13px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--accent-light);
}

.barcode-img {
    margin: 10px 0;
    padding: 10px;
    background: var(--white);
}

.barcode-img img {
    max-width: 100%;
    height: auto;
}

.barcode-value {
    font-family: monospace;
    font-size: 11px;
    color: var(--text-secondary);
    margin-top: 8px;
    word-break: break-all;
}

/* Barcode Detail Item */
.barcode-detail-item {
    margin: 15px 0;
    padding: 20px;
    background: var(--light);
    border-radius: 8px;
    border-left: 4px solid var(--accent);
}

.barcode-detail-item .barcode-label {
    font-weight: bold;
    color: var(--primary);
    margin-bottom: 10px;
}

.barcode-detail-item .barcode-image {
    text-align: center;
    padding: 15px;
    background: var(--white);
    border-radius: 5px;
}

.barcode-detail-item .barcode-image img {
    max-width: 100%;
    height: auto;
    max-height: 60px;
}

.barcode-detail-item .barcode-value {
    font-family: monospace;
    text-align: center;
    margin-top: 10px;
    color: var(--accent);
}

/* Alert Styles */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-left: 4px solid transparent;
}

.alert i {
    font-size: 18px;
}

.alert-success {
    background-color: var(--success-light);
    color: var(--success);
    border-left-color: var(--success);
}

.alert-danger {
    background-color: #ffebee;
    color: var(--danger);
    border-left-color: var(--danger);
}

/* Loading Indicator */
.loading,
.loading-spinner {
    text-align: center;
    padding: 30px;
    color: var(--text-muted);
}

.loading i,
.loading-spinner i {
    font-size: 32px;
    margin-bottom: 15px;
    color: var(--primary);
}

/* Text Utilities */
.text-muted {
    color: var(--text-muted) !important;
}

.text-danger {
    color: var(--danger) !important;
}

.text-info {
    color: var(--info) !important;
}

.text-center {
    text-align: center;
}

.mt-3 {
    margin-top: 15px;
}

/* Form Check */
.form-check {
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.form-check-input {
    width: 18px;
    height: 18px;
    cursor: pointer;
    accent-color: var(--accent);
}

.form-check-label {
    font-size: 14px;
    cursor: pointer;
}

/* Pagination Styles */
.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 30px;
}

.pagination a,
.pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 35px;
    height: 35px;
    padding: 0 8px;
    border-radius: 6px;
    background: var(--white);
    color: var(--text-secondary);
    text-decoration: none;
    border: 1px solid var(--border-light);
    transition: all 0.2s;
}

.pagination a:hover {
    background: var(--primary);
    color: var(--text-light);
    border-color: var(--primary);
}

.pagination .active {
    background: var(--primary);
    color: var(--text-light);
    border-color: var(--primary);
}

/* Responsive Design */
@media (max-width: 768px) {
    .dashboard-cards {
        grid-template-columns: 1fr;
    }
    
    .search-box {
        flex-direction: column;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .action-buttons {
        justify-content: flex-start;
    }
    
    .modal-content {
        margin: 20px;
        width: auto;
    }
    
    .barcode-preview-grid,
    .all-barcodes-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Statistics Cards -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <h3>Total Items</h3>
        <div class="card-value"><?php echo number_format($stats['inventory']['total_items'] ?? 0); ?></div>
        
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-hand-holding"></i>
        </div>
        <h3>Issued Items</h3>
        <div class="card-value"><?php echo $stats['pending_issues']; ?></div>
       
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3>Low Stock</h3>
        <div class="card-value text-warning"><?php echo $stats['low_stock']; ?></div>
       
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-peso-sign"></i>
        </div>
        <h3>Total Value</h3>
        <div class="card-value"><?php echo formatCurrency($stats['inventory']['total_value'] ?? 0); ?></div>
        
    </div>
</div>



<div class="stats-grid">
    <!-- Recent Issuances -->
    <div class="stat-chart">
        <h3><i class="fas fa-hand-holding"></i> Recent Issuances</h3>
        <table style="width: 100%;">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Issued To</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent_issues && $recent_issues->num_rows > 0): ?>
                    <?php while($issue = $recent_issues->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($issue['article_name']); ?></td>
                        <td><?php echo htmlspecialchars($issue['issued_to_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($issue['issued_date'])); ?></td>
                        <td><?php echo getStatusBadge($issue['status']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">No recent issuances</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Low Stock Alerts -->
    <div class="stat-chart">
        <h3><i class="fas fa-exclamation-triangle text-warning"></i> Low Stock Alerts</h3>
        <?php if ($low_stock_items && $low_stock_items->num_rows > 0): ?>
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($item = $low_stock_items->fetch_assoc()): ?>
                    <tr class="<?php echo $item['qty_physical_count'] <= 2 ? 'stock-alert-row' : ''; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($item['article_name']); ?></strong>
                            <br><small><?php echo htmlspecialchars($item['property_no'] ?? ''); ?></small>
                        </td>
                        <td>
                            <span class="badge badge-danger"><?php echo $item['qty_physical_count']; ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($item['section_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-center text-success">
                <i class="fas fa-check-circle"></i> All items have sufficient stock
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Orders -->
<div class="table-container">
    <div class="table-header">
        <h2>Recent Orders</h2>
        <a href="<?php echo SITE_URL; ?>/admin/issue_items.php" class="btn btn-sm btn-primary">View All</a>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>SI No</th>
                <th>Product Name</th>
                <th>P. Price</th>
                <th>Quantity</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sample_orders = $conn->query("
                SELECT ei.*, i.article_name, i.unit_value,
                       CONCAT(u.firstname, ' ', u.lastname) as customer
                FROM equipment_issuance ei
                JOIN inventory i ON ei.inventory_id = i.id
                JOIN users u ON ei.issued_to = u.id
                ORDER BY ei.issued_date DESC
                LIMIT 5
            ");
            
            if ($sample_orders && $sample_orders->num_rows > 0):
                $count = 1;
                while($order = $sample_orders->fetch_assoc()):
            ?>
            <tr>
                <td><?php echo str_pad($count++, 2, '0', STR_PAD_LEFT); ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($order['article_name']); ?></strong>
                    <br><small><?php echo htmlspecialchars($order['customer']); ?></small>
                </td>
                <td><?php echo formatCurrency($order['unit_value']); ?></td>
                <td><?php echo $order['quantity_issued']; ?></td>
                <td><?php echo formatCurrency($order['unit_value'] * $order['quantity_issued']); ?></td>
                <td>COO</td>
                <td><?php echo getStatusBadge($order['status']); ?></td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="viewOrder(<?php echo $order['id']; ?>)">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
            <?php 
                endwhile;
            else:
            ?>
            <tr>
                <td colspan="8" class="text-center">No orders found</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>


<script>
function viewOrder(orderId) {
    ajaxRequest('<?php echo SITE_URL; ?>/api/get_order_details.php?id=' + orderId, 'GET', null, function(err, response) {
        if (!err && response) {
            let content = `
                <div style="margin-bottom: 20px;">
                    <h3>Order #${response.id}</h3>
                    <table style="width: 100%;">
                        <tr><td><strong>Item:</strong></td><td>${response.article_name}</td></tr>
                        <tr><td><strong>Customer:</strong></td><td>${response.customer}</td></tr>
                        <tr><td><strong>Quantity:</strong></td><td>${response.quantity_issued}</td></tr>
                        <tr><td><strong>Unit Price:</strong></td><td>${formatCurrency(response.unit_value)}</td></tr>
                        <tr><td><strong>Total:</strong></td><td>${formatCurrency(response.unit_value * response.quantity_issued)}</td></tr>
                        <tr><td><strong>Issue Date:</strong></td><td>${formatDate(response.issued_date)}</td></tr>
                        <tr><td><strong>Status:</strong></td><td>${response.status}</td></tr>
                        <tr><td><strong>Purpose:</strong></td><td>${response.purpose || 'N/A'}</td></tr>
                    </table>
                </div>
            `;
            showModal('Order Details', content);
        } else {
            alert('Error loading order details');
        }
    });
}

// Helper functions (if not already defined)
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    let date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function showModal(title, content) {
    // Create modal if it doesn't exist
    let modal = document.getElementById('dynamic-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'dynamic-modal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modal-title"></h2>
                    <span class="modal-close" onclick="document.getElementById('dynamic-modal').style.display='none'">&times;</span>
                </div>
                <div class="modal-body" id="modal-body"></div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-body').innerHTML = content;
    modal.style.display = 'block';
}

function ajaxRequest(url, method, data, callback) {
    let xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    let response = JSON.parse(xhr.responseText);
                    callback(null, response);
                } catch (e) {
                    callback(e, null);
                }
            } else {
                callback(new Error('Request failed with status ' + xhr.status), null);
            }
        }
    };
    
    xhr.send(data ? JSON.stringify(data) : null);
}
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>