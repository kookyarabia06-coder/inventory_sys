<?php
/**
 * Super Admin Dashboard
 * Overview of entire system with statistics and management tools
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Require super admin role
requireRole('super_admin');

$page_title = 'Super Admin Dashboard';
$page_description = 'Overview of system-wide statistics and activities';

// Get low stock threshold from settings
$threshold_result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold'");
$threshold = 5; // default value
if ($threshold_result && $threshold_result->num_rows > 0) {
    $threshold = intval($threshold_result->fetch_assoc()['setting_value']);
}

// Get statistics
$stats = [];

// Total users count
$result = $conn->query("SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role = 'super_admin' THEN 1 ELSE 0 END) as super_admins,
    SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admins,
    SUM(CASE WHEN role = 'user' THEN 1 ELSE 0 END) as regular_users
FROM users WHERE status = 'active'");
$stats['users'] = $result->fetch_assoc();

// Total inventory count
$result = $conn->query("SELECT 
    COUNT(*) as total_items,
    SUM(qty_physical_count) as total_quantity,
    SUM(unit_value * qty_physical_count) as total_value
FROM inventory");
$stats['inventory'] = $result->fetch_assoc();

// Recent activities
$recent_activities = $conn->query("
    SELECT al.*, u.username, u.firstname, u.lastname 
    FROM activity_log al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.date_created DESC 
    LIMIT 10
");

// System health
$system_health = [
    'database_size' => getDatabaseSize(),
    'total_files' => count(glob(UPLOAD_PATH . '/*')),
    'last_backup' => file_exists(BASE_PATH . '/backups/latest_backup.sql') ? date('Y-m-d H:i', filemtime(BASE_PATH . '/backups/latest_backup.sql')) : 'Never',
    'php_version' => phpversion(),
    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'
];

// Get low stock items list (using threshold from settings)
$low_stock_items = $conn->query("
    SELECT i.*, s.name as section_name 
    FROM inventory i
    LEFT JOIN sections s ON i.section_id = s.id
    WHERE i.qty_physical_count <= $threshold 
    ORDER BY i.qty_physical_count ASC 
    LIMIT 10
");

// ============================================
// RECENT INVENTORY ADDITIONS - GROUPED BY BATCH
// ============================================
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

// Get actual items for each batch
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

include INCLUDE_PATH . '/header.php';
?>

<style>
/* Additional styles for batch cards and improved tables */
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

/* Badge Styles */
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

.badge-info {
    background-color: #EFF6FF;
    color: var(--info);
}

.badge-primary {
    background-color: var(--primary);
    color: white;
}

.badge-success {
    background-color: #D1FAE5;
    color: #059669;
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

/* Stock Alert Row */
.stock-alert-row {
    background-color: #FEF3C7;
}

.stock-alert-row:hover {
    background-color: #FDE68A !important;
}

/* Property Number */
.property-no {
    font-family: monospace;
    font-size: 12px;
    font-weight: 500;
}

/* Activity List */
.activity-list {
    max-height: 400px;
    overflow-y: auto;
}

.activity-item {
    display: flex;
    gap: 15px;
    padding: 12px 0;
    border-bottom: 1px solid var(--border-light);
}

.activity-icon {
    width: 40px;
    height: 40px;
    background: var(--accent-light);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.activity-icon i {
    color: var(--primary);
    font-size: 16px;
}

.activity-content {
    flex: 1;
}

.activity-title {
    font-size: 13px;
    margin-bottom: 4px;
}

.activity-time {
    font-size: 11px;
    color: var(--text-muted);
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 10px;
    font-size: 13px;
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

.btn-success {
    background-color: var(--success);
    color: white;
}

.btn-success:hover {
    background-color: #059669;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 11px;
}

/* Progress Bar */
.progress {
    background: var(--border-light);
    border-radius: 10px;
    height: 8px;
    overflow: hidden;
}

.progress-bar {
    background: var(--primary);
    height: 100%;
    border-radius: 10px;
}

/* Text Utilities */
.text-center {
    text-align: center;
}

.text-success {
    color: var(--success) !important;
}

.text-warning {
    color: var(--warning) !important;
}

.text-muted {
    color: var(--text-muted) !important;
}

.mt-3 {
    margin-top: 16px;
}

/* Loading Overlay */
.loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid var(--white);
    border-top-color: var(--primary);
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 768px) {
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

<!-- Statistics Cards -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-users"></i>
        </div>
        <h3>Total Users</h3>
        <div class="card-value">
            <?php echo $stats['users']['total_users'] ?? 0; ?>
            <span class="badges">
                <span class="badge badge-info">SA: <?php echo $stats['users']['super_admins'] ?? 0; ?></span>
                <span class="badge badge-primary">A: <?php echo $stats['users']['admins'] ?? 0; ?></span>
                <span class="badge badge-success">U: <?php echo $stats['users']['regular_users'] ?? 0; ?></span>
            </span>
        </div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <h3>Inventory Items</h3>
        <div class="card-value"><?php echo number_format($stats['inventory']['total_items'] ?? 0); ?></div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-php"></i>
        </div>
        <h3>PHP Version</h3>
        <div class="card-value"><?php echo substr($system_health['php_version'], 0, 3); ?></div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-database"></i>
        </div>
        <h3>Database Size</h3>
        <div class="card-value"><?php echo $system_health['database_size']; ?></div>
    </div>
</div>

<!-- Quick Actions -->
<div class="table-container">
    <div class="table-header">
        <h2>Quick Actions</h2>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="<?php echo SITE_URL; ?>/superadmin/manage_users.php" class="btn btn-primary">
            <i class="fas fa-users-cog"></i> Manage Users
        </a>
        <a href="<?php echo SITE_URL; ?>/superadmin/all_inventory.php" class="btn btn-primary">
            <i class="fas fa-boxes"></i> View All Inventory
        </a>
        <a href="<?php echo SITE_URL; ?>/superadmin/audit_trail.php" class="btn btn-primary">
            <i class="fas fa-history"></i> Audit Trail
        </a>
        <a href="<?php echo SITE_URL; ?>/superadmin/reports.php" class="btn btn-primary">
            <i class="fas fa-file-alt"></i> Generate Reports
        </a>
        <a href="<?php echo SITE_URL; ?>/superadmin/system_settings.php" class="btn btn-primary">
            <i class="fas fa-cog"></i> System Settings
        </a>
        <button class="btn btn-success" onclick="backupDatabase()">
            <i class="fas fa-database"></i> Backup Now
        </button>
    </div>
</div>

<!-- Main Content Grid - UPDATED Low Stock and Recent Inventory -->
<div class="stats-grid">
    <!-- Low Stock Alerts - UPDATED -->
    <div class="stat-chart">
        <h3><i class="fas fa-exclamation-triangle text-warning"></i> Low Stock Alerts (Threshold: <?php echo $threshold; ?> units)</h3>
        <?php if ($low_stock_items && $low_stock_items->num_rows > 0): ?>
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>Item Name</th>
                        <th>Property No.</th>
                        <th>Current Qty</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($item = $low_stock_items->fetch_assoc()): ?>
                    <tr class="<?php echo $item['qty_physical_count'] <= 2 ? 'stock-alert-row' : ''; ?>">
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
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <div class="text-center mt-3">
                <a href="<?php echo SITE_URL; ?>/superadmin/all_inventory.php?low_stock=1" class="btn btn-secondary btn-sm">
                    View All Low Stock Items →
                </a>
            </div>
        <?php else: ?>
            <p class="text-center text-success">
                <i class="fas fa-check-circle"></i> All items have sufficient stock (above <?php echo $threshold; ?> units)
            </p>
        <?php endif; ?>
    </div>
    
    <!-- Recent Inventory Additions - UPDATED with Batch Grouping -->
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
                    <table>
                        <thead>
                            <tr>
                                <th>Item Name</th>
                                <th>Property No.</th>
                                <th>Qty</th>
                                <th>Location</th>
                                <th>Condition</th>
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
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
            <div class="text-center mt-3">
                <a href="<?php echo SITE_URL; ?>/superadmin/all_inventory.php" class="btn btn-secondary btn-sm">
                    View All Inventory (<?php echo number_format($stats['inventory']['total_items'] ?? 0); ?> items) →
                </a>
            </div>
        <?php else: ?>
            <p class="text-center text-muted">
                <i class="fas fa-inbox"></i> No inventory items found
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- System Health (unchanged) -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-heartbeat"></i> System Health</h2>
        <button class="btn btn-sm btn-secondary" onclick="refreshHealth()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px;">
        <div>
            <h4>Database</h4>
            <p>Size: <?php echo $system_health['database_size']; ?></p>
            <p>Tables: <?php echo count(getAllTables()); ?></p>
            <div class="progress" style="margin-top: 10px;">
                <div class="progress-bar" style="width: 75%;"></div>
            </div>
        </div>
        
        <div>
            <h4>Uploads</h4>
            <p>Total Files: <?php echo $system_health['total_files']; ?></p>
            <p>Storage Used: <?php echo $system_health['database_size']; ?></p>
        </div>
        
        <div>
            <h4>Performance</h4>
            <p>Memory Usage: <?php echo formatBytes(memory_get_usage()); ?></p>
            <p>Peak: <?php echo formatBytes(memory_get_peak_usage()); ?></p>
        </div>
        
        <div>
            <h4>Security</h4>
            <p>Last Login: <?php echo getLastLoginTime(); ?></p>
            <p>Failed Attempts: <?php echo getFailedLogins(); ?></p>
        </div>
    </div>
</div>

<!-- Recent Activities (unchanged) -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-history"></i> Recent Activities</h2>
    </div>
    <div class="activity-list">
        <?php if ($recent_activities && $recent_activities->num_rows > 0): ?>
            <?php while($activity = $recent_activities->fetch_assoc()): ?>
            <div class="activity-item">
                <div class="activity-icon">
                    <?php
                    $icons = [
                        'Login' => 'fa-sign-in-alt',
                        'Logout' => 'fa-sign-out-alt',
                        'Add' => 'fa-plus',
                        'Edit' => 'fa-edit',
                        'Delete' => 'fa-trash',
                        'Issue' => 'fa-hand-holding'
                    ];
                    $icon = $icons[$activity['action']] ?? 'fa-circle';
                    ?>
                    <i class="fas <?php echo $icon; ?>"></i>
                </div>
                <div class="activity-content">
                    <div class="activity-title">
                        <strong><?php echo htmlspecialchars(($activity['firstname'] ?? '') . ' ' . ($activity['lastname'] ?? 'System')); ?></strong>
                        <?php echo htmlspecialchars($activity['action']); ?>
                        <?php if($activity['details']): ?>
                            <small>- <?php echo htmlspecialchars($activity['details']); ?></small>
                        <?php endif; ?>
                    </div>
                    <div class="activity-time">
                        <i class="far fa-clock"></i> 
                        <?php echo date('M d, Y h:i A', strtotime($activity['date_created'])); ?>
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <p class="text-center">No recent activities</p>
        <?php endif; ?>
    </div>
</div>

<script>
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

function backupDatabase() {
    if(confirm('Create a database backup now? This may take a few moments.')) {
        showLoading();
        
        fetch('<?php echo SITE_URL; ?>/api/backup_database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            hideLoading();
            if(data.success) {
                alert('Backup created successfully: ' + data.filename);
            } else {
                alert('Error creating backup: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(error => {
            hideLoading();
            alert('Error creating backup: ' + error.message);
        });
    }
}

function refreshHealth() {
    showLoading();
    location.reload();
}

function showLoading() {
    const loader = document.createElement('div');
    loader.className = 'loading-overlay';
    loader.innerHTML = '<div class="spinner"></div>';
    loader.id = 'loading-overlay';
    document.body.appendChild(loader);
}

function hideLoading() {
    const loader = document.getElementById('loading-overlay');
    if(loader) {
        loader.remove();
    }
}

// Auto-expand the first batch by default
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('batch-content-0')) {
        toggleBatch(0);
    }
});
</script>

<?php
/**
 * Helper functions for dashboard
 */

function getDatabaseSize() {
    global $conn;
    if(!defined('DB_NAME')) {
        return 'N/A';
    }
    $result = $conn->query("SELECT SUM(data_length + index_length) as size 
                           FROM information_schema.tables 
                           WHERE table_schema = '" . DB_NAME . "'");
    if($result && $row = $result->fetch_assoc()) {
        return formatBytes($row['size'] ?? 0);
    }
    return 'N/A';
}

function getAllTables() {
    global $conn;
    $result = $conn->query("SHOW TABLES");
    $tables = [];
    if($result) {
        while($row = $result->fetch_array()) {
            $tables[] = $row[0];
        }
    }
    return $tables;
}

function getLastLoginTime() {
    global $conn;
    $user_id = $_SESSION['user_id'] ?? 0;
    if(!$user_id) return 'N/A';
    
    $result = $conn->query("SELECT date_created FROM activity_log 
                           WHERE user_id = $user_id AND action = 'Login' 
                           ORDER BY date_created DESC LIMIT 1,1");
    if($result && $row = $result->fetch_assoc()) {
        return date('M d, Y h:i A', strtotime($row['date_created']));
    }
    return 'First login';
}

function getFailedLogins() {
    global $conn;
    $result = $conn->query("SELECT COUNT(*) as count FROM activity_log 
                           WHERE action = 'Failed Login' AND date_created > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    if($result && $row = $result->fetch_assoc()) {
        return $row['count'];
    }
    return 0;
}

function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, $precision) . ' ' . $units[$pow];
}

include INCLUDE_PATH . '/footer.php';
?>