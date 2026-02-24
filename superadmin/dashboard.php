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
require_once INCLUDE_PATH . '/functions.php'; // Add this for helper functions

// Require super admin role
requireRole('super_admin');

$page_title = 'Super Admin Dashboard';
$page_description = 'Overview of system-wide statistics and activities';

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

// Get low stock alerts
$low_stock = $conn->query("
    SELECT i.*, s.name as section_name 
    FROM inventory i
    LEFT JOIN sections s ON i.section_id = s.id
    WHERE i.qty_physical_count <= 5 
    ORDER BY i.qty_physical_count ASC 
    LIMIT 10
");

include INCLUDE_PATH . '/header.php';
?>

<!-- Statistics Cards -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-users"></i>
        </div>
        <h3>Total Users</h3>
        <div class="card-value"><?php echo $stats['users']['total_users'] ?? 0; ?></div>
        <div class="card-label">
            <span class="badge badge-info">SA: <?php echo $stats['users']['super_admins'] ?? 0; ?></span>
            <span class="badge badge-primary">A: <?php echo $stats['users']['admins'] ?? 0; ?></span>
            <span class="badge badge-success">U: <?php echo $stats['users']['regular_users'] ?? 0; ?></span>
        </div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <h3>Inventory Items</h3>
        <div class="card-value"><?php echo number_format($stats['inventory']['total_items'] ?? 0); ?></div>
        <div class="card-label">Total Quantity: <?php echo number_format($stats['inventory']['total_quantity'] ?? 0, 2); ?></div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-php"></i>
        </div>
        <h3>PHP Version</h3>
        <div class="card-value"><?php echo substr($system_health['php_version'], 0, 3); ?></div>
        <div class="card-label"><?php echo $system_health['server_software']; ?></div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-database"></i>
        </div>
        <h3>Database Size</h3>
        <div class="card-value"><?php echo $system_health['database_size']; ?></div>
        <div class="card-label">Last Backup: <?php echo $system_health['last_backup']; ?></div>
    </div>
</div>

<!-- Quick Actions - FIXED PATHS -->
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

<!-- Main Content Grid -->
<div class="stats-grid">
    <!-- Recent Activities -->
    <div class="stat-chart">
        <h3><i class="fas fa-history"></i> Recent Activities</h3>
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
    
    <!-- Low Stock Alerts -->
    <div class="stat-chart">
        <h3><i class="fas fa-exclamation-triangle text-warning"></i> Low Stock Alerts</h3>
        <?php if ($low_stock && $low_stock->num_rows > 0): ?>
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>Article</th>
                        <th>Quantity</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($item = $low_stock->fetch_assoc()): ?>
                    <tr class="<?php echo $item['qty_physical_count'] <= 2 ? 'stock-alert-row' : ''; ?>">
                        <td>
                            <?php echo htmlspecialchars($item['article_name']); ?>
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

<!-- System Health -->
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
            <p>Storage Used: <?php echo $system_health['database_size']; // Placeholder ?></p>
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

<script>
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