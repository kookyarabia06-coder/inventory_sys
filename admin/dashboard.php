<?php
/**
 * Admin Dashboard - Original Layout with Forms
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Require admin role
requireRole('admin');

$page_title = 'Admin Dashboard';
$page_description = 'Manage inventory and track items';

// Get low stock threshold from settings
$threshold_result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold'");
$threshold = 5; // default value
if ($threshold_result && $threshold_result->num_rows > 0) {
    $threshold = intval($threshold_result->fetch_assoc()['setting_value']);
}

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

// Issued items count
$result = $conn->query("SELECT COUNT(DISTINCT inventory_id) as count FROM equipment_issuance WHERE status IN ('issued', 'pending')");
$stats['issued_items'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Low stock items count
$result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE qty_physical_count <= $threshold");
$stats['low_stock'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Total value
$result = $conn->query("SELECT SUM(unit_value * qty_physical_count) as total FROM inventory");
if ($result && $result->num_rows > 0) {
    $stats['total_value'] = $result->fetch_assoc()['total'] ?? 0;
}

// Get distinct categories
$result = $conn->query("SELECT COUNT(DISTINCT category) as count FROM inventory WHERE category IS NOT NULL AND category != ''");
$stats['categories'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Sections count
$result = $conn->query("SELECT COUNT(*) as count FROM sections");
$stats['sections'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// ============================================
// RECENT INVENTORY ADDITIONS - GROUPED BY BATCH
// ============================================
// Group recent items by date_added (same date/time = same batch)
$recent_batches = $conn->query("
    SELECT 
        DATE_FORMAT(date_added, '%Y-%m-%d %H:%i:00') as batch_time,
        DATE_FORMAT(date_added, '%M %d, %Y at %h:%i %p') as batch_display,
        COUNT(*) as item_count,
        GROUP_CONCAT(id) as item_ids,
        MIN(date_added) as batch_date
    FROM inventory 
    GROUP BY DATE_FORMAT(date_added, '%Y-%m-%d %H:%i:00')
    ORDER BY batch_date DESC
    LIMIT 5
");

// Get actual items for each batch (for display)
$batch_items = [];
if ($recent_batches && $recent_batches->num_rows > 0) {
    while ($batch = $recent_batches->fetch_assoc()) {
        $item_ids = explode(',', $batch['item_ids']);
        $ids_string = implode(',', array_map('intval', $item_ids));
        
        $items_query = $conn->query("
            SELECT i.*, s.name as section_name, toe.name as type_equipment_name
            FROM inventory i
            LEFT JOIN sections s ON i.section_id = s.id
            LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
            WHERE i.id IN ($ids_string)
            ORDER BY i.id ASC
            LIMIT 5
        ");
        
        $items = [];
        if ($items_query && $items_query->num_rows > 0) {
            while ($item = $items_query->fetch_assoc()) {
                $items[] = $item;
            }
        }
        
        $batch_items[] = [
            'batch_time' => $batch['batch_time'],
            'batch_display' => $batch['batch_display'],
            'item_count' => $batch['item_count'],
            'items' => $items
        ];
    }
}

// Low stock items list
$low_stock_items = $conn->query("
    SELECT i.*, s.name as section_name, toe.name as type_equipment_name
    FROM inventory i
    LEFT JOIN sections s ON i.section_id = s.id
    LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id
    WHERE i.qty_physical_count <= $threshold 
    ORDER BY i.qty_physical_count ASC 
    LIMIT 10
");

include INCLUDE_PATH . '/header.php';
?>

<style>
:root {
    --primary: #6B8CFF;
    --secondary: #8FB5FF;
    --accent: #F8B0C0;
    --accent-light: #FFD8E0;
    --success-light: #C5E8C5;
    --light: #F5F7FA;
    --white: #FFFFFF;
    --border-light: #E2E8F0;
    --text-primary: #1E293B;
    --text-secondary: #475569;
    --text-muted: #94A3B8;
    --text-light: #FFFFFF;
    --success: #10B981;
    --danger: #EF4444;
    --warning: #F59E0B;
    --info: #3B82F6;
}

body {
    background-color: var(--light);
    color: var(--text-primary);
}

/* Dashboard Cards */
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background: var(--white);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    border-left: 4px solid var(--primary);
    transition: all 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
}

.card-icon {
    width: 48px;
    height: 48px;
    background: var(--accent-light);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
}

.card-icon i {
    font-size: 22px;
    color: var(--primary);
}

.card h3 {
    color: var(--text-secondary);
    font-size: 13px;
    margin-bottom: 8px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card .card-value {
    color: var(--primary);
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 5px;
}

.card .card-label {
    color: var(--text-muted);
    font-size: 12px;
}

.card .card-link {
    display: inline-block;
    margin-top: 10px;
    color: var(--secondary);
    font-size: 12px;
    text-decoration: none;
}

.card .card-link:hover {
    color: var(--primary);
    text-decoration: underline;
}

.text-warning {
    color: var(--warning) !important;
}

.text-success {
    color: var(--success) !important;
}

/* Stats Grid - Original Left/Right Layout */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(450px, 1fr));
    gap: 25px;
    margin-bottom: 30px;
}

.stat-chart {
    background: var(--white);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.stat-chart h3 {
    color: var(--primary);
    font-size: 16px;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--accent-light);
}

.stat-chart h3 i {
    margin-right: 10px;
    color: var(--accent);
}

/* Table Styles */
.stat-chart table {
    width: 100%;
    border-collapse: collapse;
}

.stat-chart th {
    padding: 12px 12px;
    text-align: left;
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--accent-light);
    background: var(--light);
}

.stat-chart td {
    padding: 12px 12px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
    font-size: 13px;
    vertical-align: middle;
}

.stat-chart tr:hover {
    background-color: var(--light);
}

/* Batch Card Styles */
.batch-card {
    border: 1px solid var(--border-light);
    border-radius: 12px;
    margin-bottom: 20px;
    overflow: hidden;
}

.batch-header {
    background: linear-gradient(135deg, var(--accent-light) 0%, #ffe4ec 100%);
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    cursor: pointer;
    transition: all 0.2s;
}

.batch-header:hover {
    background: linear-gradient(135deg, #ffe0e8 0%, #ffd8e4 100%);
}

.batch-header strong {
    color: var(--primary);
    font-size: 14px;
}

.batch-header .badge {
    background: var(--primary);
    color: white;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
}

.batch-content {
    padding: 0;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.batch-content.expanded {
    max-height: 500px;
    overflow-y: auto;
}

.batch-content table {
    width: 100%;
    margin: 0;
}

.batch-content table td {
    padding: 10px 12px;
    vertical-align: middle;
}

.batch-toggle-icon {
    transition: transform 0.2s;
}

.batch-toggle-icon.rotated {
    transform: rotate(90deg);
}

/* Badges */
.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.badge-danger {
    background-color: #FEF2F2;
    color: var(--danger);
}

.badge-warning {
    background-color: #FEF3C7;
    color: var(--warning);
}

.badge-success {
    background-color: #ECFDF5;
    color: var(--success);
}

.badge-info {
    background-color: #EFF6FF;
    color: var(--info);
}

.badge-primary {
    background-color: var(--primary);
    color: white;
}

/* Condition Badge */
.condition-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.condition-good { background: #DBEAFE; color: #2563EB; }
.condition-new { background: #D1FAE5; color: #059669; }
.condition-fair { background: #FEF3C7; color: #D97706; }
.condition-poor { background: #FEE2E2; color: #DC2626; }
.condition-serviceable { background: #D1FAE5; color: #059669; }

/* Property Number */
.property-no {
    font-family: monospace;
    font-size: 12px;
    font-weight: 500;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    color: var(--text-light);
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
    font-size: 13px;
}

.action-btn.view { background-color: var(--primary); }
.action-btn.edit { background-color: var(--warning); }
.action-btn.delete { background-color: var(--danger); }

.action-btn:hover {
    transform: translateY(-2px);
    filter: brightness(0.95);
}

/* Form Styles for Low Stock Panel */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 600;
    color: var(--text-primary);
    font-size: 13px;
}

.form-group label i {
    margin-right: 8px;
    color: var(--primary);
}

.form-control {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid var(--border-light);
    border-radius: 10px;
    font-size: 14px;
    transition: all 0.2s;
    background: var(--white);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.form-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 10px;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 16px;
    border: none;
    border-radius: 10px;
    font-size: 12px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent) 0%, #e69eb0 100%);
    color: var(--text-primary);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(248, 176, 192, 0.4);
}

.btn-secondary {
    background-color: var(--secondary);
    color: var(--text-light);
}

.btn-secondary:hover {
    background-color: #7a9fe6;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 11px;
}

/* Alert */
.alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-left: 4px solid transparent;
    font-size: 13px;
}

.alert-success { background-color: #ECFDF5; color: #059669; border-left-color: #10B981; }
.alert-danger { background-color: #FEF2F2; color: #DC2626; border-left-color: #EF4444; }

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(3px);
    overflow-y: auto;
}

.modal-container {
    background-color: var(--white);
    margin: 5% auto;
    padding: 0;
    border-radius: 12px;
    width: 800px;
    max-width: 90%;
    box-shadow: 0 10px 30px rgba(107, 140, 255, 0.2);
    animation: modalSlideIn 0.3s;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
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

.modal-header h3 {
    color: var(--primary);
    margin: 0;
    font-size: 20px;
}

.modal-header h3 i {
    color: var(--accent);
    margin-right: 10px;
}

.modal-close {
    cursor: pointer;
    font-size: 28px;
    font-weight: bold;
    color: var(--text-muted);
    transition: color 0.2s;
}

.modal-close:hover {
    color: var(--accent);
}

.modal-body-scroll {
    padding: 25px;
    overflow-y: auto;
    flex: 1;
}

.modal-footer-buttons {
    text-align: right;
    padding: 16px 25px;
    border-top: 1px solid var(--border-light);
    background: var(--light);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
}

/* Detail View Styles */
.detail-section {
    margin-bottom: 24px;
    border: 1px solid var(--border-light);
    border-radius: 12px;
    overflow: hidden;
}

.detail-header {
    background: var(--light);
    padding: 12px 16px;
    font-weight: 600;
    color: var(--primary);
    border-bottom: 1px solid var(--border-light);
}

.detail-header i {
    margin-right: 8px;
}

.detail-content {
    padding: 16px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.detail-item {
    padding: 8px 0;
}

.detail-label {
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 4px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    color: var(--text-primary);
    font-size: 14px;
    word-break: break-word;
}

/* Quantity Badge */
.quantity-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.quantity-critical {
    background: #FEE2E2;
    color: #DC2626;
}

.quantity-warning {
    background: #FEF3C7;
    color: #D97706;
}

.quantity-normal {
    background: #D1FAE5;
    color: #059669;
}

/* Status Badge */
.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
}

.status-issued {
    background: #FEF3C7;
    color: #D97706;
}

.status-available {
    background: #D1FAE5;
    color: #059669;
}

/* Search Result Item */
.search-result-item {
    padding: 12px;
    border-bottom: 1px solid var(--border-light);
    cursor: pointer;
    transition: background 0.2s;
}

.search-result-item:hover {
    background: var(--light);
}

.search-result-item:last-child {
    border-bottom: none;
}

.search-result-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.search-result-meta {
    font-size: 11px;
    color: var(--text-muted);
}

/* Helper function to format unit display */
.unit-display {
    font-size: 11px;
    color: var(--text-muted);
}

/* Text Utilities */
.text-center {
    text-align: center;
}

.text-muted {
    color: var(--text-muted) !important;
}

.mt-3 {
    margin-top: 16px;
}

.mb-2 {
    margin-bottom: 8px;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .stat-chart table,
    .stat-chart thead,
    .stat-chart tbody,
    .stat-chart th,
    .stat-chart td,
    .stat-chart tr {
        display: block;
    }
    
    .stat-chart thead tr {
        display: none;
    }
    
    .stat-chart tr {
        margin-bottom: 15px;
        border: 1px solid var(--border-light);
        border-radius: 8px;
        padding: 10px;
    }
    
    .stat-chart td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px;
        border-bottom: 1px solid var(--border-light);
    }
    
    .stat-chart td:before {
        content: attr(data-label);
        font-weight: 600;
        color: var(--text-primary);
        margin-right: 10px;
    }
    
    .modal-container {
        margin: 10% auto;
        width: 95%;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
        gap: 8px;
    }
    
    .form-row {
        grid-template-columns: 1fr;
    }
}

/* Helper function to format units */
.format-units {
    display: inline-flex;
    flex-direction: column;
    font-size: 11px;
}
</style>

<!-- Display Success/Error Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon"><i class="fas fa-boxes"></i></div>
        <h3>Total Items</h3>
        <div class="card-value"><?php echo number_format($stats['inventory']['total_items'] ?? 0); ?></div>
        <div class="card-label">Different inventory items</div>
        <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php" class="card-link">View All →</a>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-hand-holding"></i></div>
        <h3>Issued Items</h3>
        <div class="card-value"><?php echo number_format($stats['issued_items']); ?></div>
        <div class="card-label">Currently issued</div>
        <a href="<?php echo SITE_URL; ?>/admin/issued_items.php" class="card-link">View Issued →</a>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h3>Low Stock Items</h3>
        <div class="card-value <?php echo $stats['low_stock'] > 0 ? 'text-warning' : 'text-success'; ?>">
            <?php echo number_format($stats['low_stock']); ?>
        </div>
        <div class="card-label">Below <?php echo $threshold; ?> units</div>
        <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php?low_stock=1" class="card-link">View Alerts →</a>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-dollar-sign"></i></div>
        <h3>Total Value</h3>
        <div class="card-value">₱<?php echo number_format($stats['total_value'], 2); ?></div>
        <div class="card-label">Inventory value</div>
    </div>
</div>

<!-- Stats Grid - Left/Right Layout (Original Style) -->
<div class="stats-grid">
    <!-- Low Stock Alerts Panel (Left Side) -->
    <div class="stat-chart">
        <h3><i class="fas fa-exclamation-triangle text-warning"></i> Low Stock Alert Settings</h3>
        <form id="lowStockForm" method="GET" action="<?php echo SITE_URL; ?>/admin/all_inventory.php">
            <div class="form-group">
                <label><i class="fas fa-sliders-h"></i> Stock Threshold Value</label>
                <div class="form-row">
                    <input type="number" class="form-control" name="threshold" value="<?php echo $threshold; ?>" min="1" step="1">
                    <button type="submit" class="btn btn-primary">Apply Threshold</button>
                </div>
                <small class="text-muted">Items with quantity below this value will appear as low stock alerts</small>
            </div>
        </form>
        
        <div style="margin-top: 20px;">
            <label><i class="fas fa-list"></i> Current Low Stock Items (<?php echo $stats['low_stock']; ?>)</label>
            <div style="max-height: 300px; overflow-y: auto; border: 1px solid var(--border-light); border-radius: 10px; margin-top: 10px;">
                <?php if ($low_stock_items && $low_stock_items->num_rows > 0): ?>
                    <?php while($item = $low_stock_items->fetch_assoc()): ?>
                    <div class="search-result-item" onclick="viewItem(<?php echo $item['id']; ?>)">
                        <div class="search-result-name">
                            <?php echo htmlspecialchars($item['article_name']); ?>
                            <span class="badge <?php echo $item['qty_physical_count'] <= 2 ? 'badge-danger' : 'badge-warning'; ?>" style="float: right;">
                                Qty: <?php echo $item['qty_physical_count']; ?>
                            </span>
                        </div>
                        <div class="search-result-meta">
                            <?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?> | 
                            <?php echo htmlspecialchars($item['section_name'] ?? 'N/A'); ?> | 
                            <?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?>
                            <?php if (!empty($item['big_unit']) || !empty($item['small_unit'])): ?>
                            <br><small class="unit-display">
                                Unit: <?php echo htmlspecialchars($item['big_unit'] ?? ''); ?><?php echo (!empty($item['big_unit']) && !empty($item['small_unit'])) ? ' / ' : ''; ?><?php echo htmlspecialchars($item['small_unit'] ?? ''); ?>
                            </small>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    <?php if ($stats['low_stock'] > 10): ?>
                    <div class="text-center" style="padding: 10px;">
                        <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php?low_stock=1" class="btn btn-secondary btn-sm">View All <?php echo $stats['low_stock']; ?> Items →</a>
                    </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="text-center text-success" style="padding: 20px;">
                        <i class="fas fa-check-circle"></i> All items have sufficient stock (above <?php echo $threshold; ?> units)
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Recent Inventory Additions - Grouped by Batch (Right Side) -->
    <div class="stat-chart">
        <h3><i class="fas fa-plus-circle"></i> Recent Inventory Additions (By Batch)</h3>
        <?php if (!empty($batch_items)): ?>
            <?php foreach ($batch_items as $index => $batch): ?>
            <div class="batch-card">
                <div class="batch-header" onclick="toggleBatch(<?php echo $index; ?>)">
                    <div>
                        <i class="fas fa-chevron-right batch-toggle-icon" id="batch-icon-<?php echo $index; ?>"></i>
                        <strong><?php echo htmlspecialchars($batch['batch_display']); ?></strong>
                    </div>
                    <span class="badge badge-primary"><?php echo $batch['item_count']; ?> item(s)</span>
                </div>
                <div class="batch-content" id="batch-content-<?php echo $index; ?>">
                    <div style="overflow-x: auto;">
                        <table style="min-width: 700px;">
                            <thead>
                                <tr>
                                    <th>Item Name</th>
                                    <th>Property No.</th>
                                    <th>Qty & Unit</th>
                                    <th>Location</th>
                                    <th>Condition</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($batch['items'] as $item): 
                                    $condition = strtolower($item['condition_text'] ?? 'good');
                                    $condition_class = 'condition-good';
                                    if ($condition == 'new') $condition_class = 'condition-new';
                                    elseif ($condition == 'good') $condition_class = 'condition-good';
                                    elseif ($condition == 'fair') $condition_class = 'condition-fair';
                                    elseif ($condition == 'poor') $condition_class = 'condition-poor';
                                    elseif ($condition == 'serviceable' || $condition == 'servicable') $condition_class = 'condition-serviceable';
                                    
                                    // Format unit display
                                    $unit_display = '';
                                    if (!empty($item['big_unit']) && !empty($item['small_unit'])) {
                                        $unit_display = '<small class="unit-display">(' . htmlspecialchars($item['big_unit']) . ' / ' . htmlspecialchars($item['small_unit']) . ')</small>';
                                    } elseif (!empty($item['big_unit'])) {
                                        $unit_display = '<small class="unit-display">(' . htmlspecialchars($item['big_unit']) . ')</small>';
                                    } elseif (!empty($item['small_unit'])) {
                                        $unit_display = '<small class="unit-display">(' . htmlspecialchars($item['small_unit']) . ')</small>';
                                    }
                                ?>
                                <tr>
                                    <td data-label="Item Name">
                                        <strong><?php echo htmlspecialchars($item['article_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?></small>
                                    </td>
                                    <td data-label="Property No" class="property-no"><?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?></td>
                                    <td data-label="Qty">
                                        <?php echo number_format($item['qty_physical_count']); ?> 
                                        <?php echo $unit_display; ?>
                                    </td>
                                    <td data-label="Location"><?php echo htmlspecialchars($item['section_name'] ?? 'N/A'); ?></td>
                                    <td data-label="Condition">
                                        <span class="condition-badge <?php echo $condition_class; ?>">
                                            <?php echo htmlspecialchars($item['condition_text'] ?? 'Good'); ?>
                                        </span>
                                    </td>
                                    <td data-label="Action">
                                        <div class="action-buttons">
                                            <button onclick="viewItem(<?php echo $item['id']; ?>)" class="action-btn view" title="View">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="text-center mt-3">
                <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php" class="btn btn-secondary btn-sm">
                    View All Inventory (<?php echo number_format($stats['inventory']['total_items'] ?? 0); ?> items) →
                </a>
            </div>
        <?php else: ?>
            <p class="text-center text-muted">
                <i class="fas fa-inbox"></i> No inventory items found
            </p>
            <div class="text-center mt-3">
                <a href="<?php echo SITE_URL; ?>/admin/add_inventory.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Your First Item
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- View Item Modal -->
<div id="viewModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 800px;">
        <div class="modal-header">
            <h3><i class="fas fa-info-circle"></i> Item Details</h3>
            <span class="modal-close" onclick="closeModal('viewModal')">&times;</span>
        </div>
        <div class="modal-body-scroll" id="viewModalContent">
            <div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
        <div class="modal-footer-buttons">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<script>
// View Item Function
function viewItem(id) {
    let modal = document.getElementById('viewModal');
    let content = document.getElementById('viewModalContent');
    modal.style.display = 'block';
    content.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    fetch('<?php echo SITE_URL; ?>/admin/all_inventory.php?ajax=get_item&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let item = data.data;
                let statusBadge = item.is_issued > 0 ? '<span class="status-badge status-issued">Issued</span>' : '<span class="status-badge status-available">Available</span>';
                
                let conditionText = item.condition_text || 'Good';
                let conditionLower = conditionText.toLowerCase();
                let conditionClass = 'condition-good';
                if (conditionLower == 'new') conditionClass = 'condition-new';
                else if (conditionLower == 'good') conditionClass = 'condition-good';
                else if (conditionLower == 'fair') conditionClass = 'condition-fair';
                else if (conditionLower == 'poor') conditionClass = 'condition-poor';
                else if (conditionLower == 'serviceable' || conditionLower == 'servicable') conditionClass = 'condition-serviceable';
                
                let quantityClass = 'quantity-normal';
                let qty = item.qty_physical_count;
                let threshold = <?php echo $threshold; ?>;
                let critical = <?php echo max(1, floor($threshold / 2)); ?>;
                if (qty <= critical) quantityClass = 'quantity-critical';
                else if (qty <= threshold) quantityClass = 'quantity-warning';
                
                // Format unit display
                let unitDisplay = '';
                if (item.big_unit && item.small_unit) {
                    unitDisplay = item.big_unit + ' / ' + item.small_unit;
                } else if (item.big_unit) {
                    unitDisplay = item.big_unit;
                } else if (item.small_unit) {
                    unitDisplay = item.small_unit;
                } else {
                    unitDisplay = 'N/A';
                }
                
                let html = `
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-info-circle"></i> Basic Information</div><div class="detail-content"><div class="detail-grid">
                        <div class="detail-item"><div class="detail-label">Article Name</div><div class="detail-value"><strong>${escapeHtml(item.article_name)}</strong></div></div>
                        <div class="detail-item"><div class="detail-label">Property Number</div><div class="detail-value">${escapeHtml(item.property_no || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Description</div><div class="detail-value">${escapeHtml(item.description || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value">${statusBadge}</div></div>
                    </div></div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-tags"></i> Classification</div><div class="detail-content"><div class="detail-grid">
                        <div class="detail-item"><div class="detail-label">Category</div><div class="detail-value">${escapeHtml(item.category || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Type of Equipment</div><div class="detail-value">${escapeHtml(item.type_equipment_name || item.type_equipment || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Equipment Type</div><div class="detail-value">${escapeHtml(item.equipment_name || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Location</div><div class="detail-value">${escapeHtml(item.section_name || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Condition</div><div class="detail-value"><span class="condition-badge ${conditionClass}">${escapeHtml(conditionText)}</span></div></div>
                    </div></div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-calculator"></i> Quantity and Value</div><div class="detail-content"><div class="detail-grid">
                        <div class="detail-item"><div class="detail-label">Quantity</div><div class="detail-value"><span class="quantity-badge ${quantityClass}">${item.qty_physical_count}</span> <small class="text-muted">(${escapeHtml(unitDisplay)})</small></div></div>
                        <div class="detail-item"><div class="detail-label">Unit Value</div><div class="detail-value">₱${parseFloat(item.unit_value).toFixed(2)}</div></div>
                        <div class="detail-item"><div class="detail-label">Total Value</div><div class="detail-value">₱${(item.qty_physical_count * item.unit_value).toFixed(2)}</div></div>
                        <div class="detail-item"><div class="detail-label">Fund Cluster</div><div class="detail-value">${escapeHtml(item.fund_cluster || 'N/A')}</div></div>
                    </div></div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-calendar"></i> Dates</div><div class="detail-content"><div class="detail-grid">
                        <div class="detail-item"><div class="detail-label">Date Added</div><div class="detail-value">${new Date(item.date_added).toLocaleString()}</div></div>
                        <div class="detail-item"><div class="detail-label">Last Updated</div><div class="detail-value">${item.date_updated ? new Date(item.date_updated).toLocaleString() : 'Never'}</div></div>
                    </div></div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-barcode"></i> Barcode</div><div class="detail-content">
                        ${item.barcode_data ? `
                        <div class="detail-item"><div class="detail-label">Barcode Value</div><div class="detail-value">${escapeHtml(item.barcode_data)}</div></div>
                        <div style="text-align: center; margin-top: 15px;">
                            <img src="<?php echo SITE_URL; ?>/admin/generate_barcode.php?code=${encodeURIComponent(item.barcode_data)}&width=300&height=70" style="max-width: 100%; border: 1px solid #E2E8F0; padding: 10px; border-radius: 8px;">
                        </div>
                        ` : '<div class="detail-value">No barcode assigned</div>'}
                    </div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-comment"></i> Remarks</div><div class="detail-content"><div class="detail-value">${escapeHtml(item.remarks || 'No remarks')}</div></div></div>
                `;
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error loading item: ' + (data.message || 'Unknown error') + '</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading item details</div>';
        });
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function escapeHtml(text) {
    if (!text) return '';
    let div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Toggle batch content
function toggleBatch(index) {
    const content = document.getElementById('batch-content-' + index);
    const icon = document.getElementById('batch-icon-' + index);
    
    if (content.classList.contains('expanded')) {
        content.classList.remove('expanded');
        icon.classList.remove('rotated');
        icon.style.transform = 'rotate(0deg)';
    } else {
        content.classList.add('expanded');
        icon.classList.add('rotated');
        icon.style.transform = 'rotate(90deg)';
    }
}

// Auto-expand the first batch by default
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('batch-content-0')) {
        toggleBatch(0);
    }
});

// Close modal when clicking outside
window.onclick = function(event) {
    let viewModal = document.getElementById('viewModal');
    if (event.target == viewModal) {
        closeModal('viewModal');
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('viewModal');
    }
});
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>