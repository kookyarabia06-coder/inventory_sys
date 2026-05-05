<?php
/**
 * Supply Officer Dashboard
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Require supply role
requireRole('supply');

$currentUser = getCurrentUser();
if (!$currentUser) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

$page_title = 'Supply Officer Dashboard';
$page_description = 'Manage inventory and supplies';

include INCLUDE_PATH . '/header.php';
?>

<div class="profile-header">
    <h1>Welcome, <?php echo htmlspecialchars($currentUser['firstname'] . ' ' . $currentUser['lastname']); ?>!</h1>
    <p>Supply Officer Dashboard - Manage inventory and supplies</p>
</div>

<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <h3>Total Items</h3>
        <?php
        $result = $conn->query("SELECT COUNT(*) as count FROM inventory");
        $total = $result->fetch_assoc()['count'] ?? 0;
        ?>
        <div class="card-value"><?php echo $total; ?></div>
        
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h2>Quick Actions</h2>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="<?php echo SITE_URL; ?>/admin/add_inventory.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Inventory
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php" class="btn btn-secondary">
            <i class="fas fa-list"></i> Inventory List
        </a>

   
    </div>
</div>

<?php include INCLUDE_PATH . '/footer.php'; ?>