<?php
/**
 * Admin Dashboard
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
            SELECT i.*, s.name as section_name
            FROM inventory i
            LEFT JOIN sections s ON i.section_id = s.id
            WHERE i.id IN ($ids_string)
            ORDER BY i.id DESC
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
    SELECT i.*, s.name as section_name
    FROM inventory i
    LEFT JOIN sections s ON i.section_id = s.id
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

/* Stats Grid */
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
    padding: 12px 8px;
    text-align: left;
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 1px solid var(--border-light);
}

.stat-chart td {
    padding: 12px 8px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
    font-size: 13px;
    vertical-align: top;
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

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 6px;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    border: none;
    color: var(--text-light);
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
    font-size: 12px;
}

.action-btn.view { background-color: var(--primary); }
.action-btn.edit { background-color: var(--secondary); }

.action-btn:hover {
    transform: translateY(-2px);
    filter: brightness(0.95);
}

/* Button Styles */
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

/* Property Number */
.property-no {
    font-family: monospace;
    font-size: 12px;
    font-weight: 500;
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

<div class="stats-grid">
    <!-- Low Stock Alerts -->
    <div class="stat-chart">
        <h3><i class="fas fa-exclamation-triangle text-warning"></i> Low Stock Alerts (Threshold: <?php echo $threshold; ?> units)</h3>
        <?php if ($low_stock_items && $low_stock_items->num_rows > 0): ?>
            <table>
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Property No.</th>
                        <th>Current Qty</th>
                        <th>Location</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($item = $low_stock_items->fetch_assoc()): ?>
                    <tr>
                        <td data-label="Item Name">
                            <strong><?php echo htmlspecialchars($item['article_name']); ?></strong>
                            <br><small class="text-muted"><?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?></small>
                        </td>
                        <td data-label="Property No" class="property-no"><?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?></td>
                        <td data-label="Current Qty">
                            <span class="badge <?php echo $item['qty_physical_count'] <= 2 ? 'badge-danger' : 'badge-warning'; ?>">
                                <?php echo $item['qty_physical_count']; ?> <?php echo htmlspecialchars($item['uom']); ?>
                            </span>
                        </td>
                        <td data-label="Location"><?php echo htmlspecialchars($item['section_name'] ?? 'N/A'); ?></td>
                        <td data-label="Action">
                            <div class="action-buttons">
                                <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php?edit=<?php echo $item['id']; ?>" class="action-btn edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </td>
                    </td>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div class="text-center mt-3">
                <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php?low_stock=1" class="btn btn-secondary btn-sm">
                    View All Low Stock Items (<?php echo $stats['low_stock']; ?>) →
                </a>
            </div>
        <?php else: ?>
            <p class="text-center text-success">
                <i class="fas fa-check-circle"></i> All items have sufficient stock (above <?php echo $threshold; ?> units)
            </p>
        <?php endif; ?>
    </div>
    
    <!-- Recent Inventory Additions - GROUPED BY BATCH -->
    <div class="stat-chart">
        <h3><i class="fas fa-plus-circle"></i> Recent Inventory Additions (By Batch)</h3>
        <?php if (!empty($batch_items)): ?>
            <?php foreach ($batch_items as $index => $batch): ?>
            <div class="batch-card">
                <div class="batch-header" onclick="toggleBatch(<?php echo $index; ?>)">
                    <div>
                        <i class="fas fa-clock batch-toggle-icon" id="batch-icon-<?php echo $index; ?>"></i>
                        <strong><?php echo htmlspecialchars($batch['batch_display']); ?></strong>
                    </div>
                    <span class="badge badge-primary"><?php echo $batch['item_count']; ?> item(s)</span>
                </div>
                <div class="batch-content" id="batch-content-<?php echo $index; ?>">
                    <table>
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Property No.</th>
                                <th>Qty</th>
                                <th>Location</th>
                                <th>Condition</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($batch['items'] as $item): ?>
                            <tr>
                                <td data-label="Item Name">
                                    <strong><?php echo htmlspecialchars($item['article_name']); ?></strong>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($item['category'] ?? 'Uncategorized'); ?></small>
                                </td>
                                <td data-label="Property No" class="property-no"><?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?></td>
                                <td data-label="Qty"><?php echo $item['qty_physical_count']; ?> <?php echo htmlspecialchars($item['uom']); ?></td>
                                <td data-label="Location"><?php echo htmlspecialchars($item['section_name'] ?? 'N/A'); ?></td>
                                <td data-label="Condition">
                                    <?php
                                    $condition = strtolower($item['condition_text'] ?? 'good');
                                    $condition_class = 'condition-good';
                                    if ($condition == 'new') $condition_class = 'condition-new';
                                    elseif ($condition == 'good') $condition_class = 'condition-good';
                                    elseif ($condition == 'fair') $condition_class = 'condition-fair';
                                    elseif ($condition == 'poor') $condition_class = 'condition-poor';
                                    ?>
                                    <span class="condition-badge <?php echo $condition_class; ?>">
                                        <?php echo htmlspecialchars($item['condition_text'] ?? 'Good'); ?>
                                    </span>
                                </td>
                                <td data-label="Action">
                                    <div class="action-buttons">
                                        <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php?view=<?php echo $item['id']; ?>" class="action-btn view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php?edit=<?php echo $item['id']; ?>" class="action-btn edit" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
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

<script>
// Toggle batch content
function toggleBatch(index) {
    const content = document.getElementById('batch-content-' + index);
    const icon = document.getElementById('batch-icon-' + index);
    
    if (content.classList.contains('expanded')) {
        content.classList.remove('expanded');
        icon.classList.remove('rotated');
    } else {
        content.classList.add('expanded');
        icon.classList.add('rotated');
    }
}

// Auto-expand the first batch by default
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('batch-content-0')) {
        toggleBatch(0);
    }
});
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>