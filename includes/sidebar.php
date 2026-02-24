<?php
/**
 * Sidebar Navigation Template
 * Dynamic menu based on user role
 */

if (!isset($currentUser)) {
    $currentUser = getCurrentUser();
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_role = $currentUser['role'] ?? '';
$base_path = SITE_URL; // Use SITE_URL constant
?>

<div class="sidebar">
    <div class="sidebar-logo">
        <i class="fas fa-cubes" style="font-size: 40px; margin-bottom: 10px;"></i>
        <h2>IMS</h2>
        <p>Inventory Management System</p>
    </div>
    
    <ul class="sidebar-menu">
        <!-- Dashboard - Common for all with role-specific link -->
        <?php if ($user_role): ?>
        <li>
            <a href="<?php 
                // Set correct dashboard path based on role
                $dashboard_path = '';
                switch ($user_role) {
                    case 'super_admin':
                        $dashboard_path = '/superadmin/dashboard';
                        break;
                    case 'admin':
                        $dashboard_path = '/admin/dashboard';
                        break;
                    case 'supply':
                        $dashboard_path = '/supply/dashboard';
                        break;
                    case 'user':
                        $dashboard_path = '/user/dashboard';
                        break;
                    default:
                        $dashboard_path = '/dashboard';
                }
                echo $base_path . $dashboard_path;
            ?>" class="<?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
        </li>
        <?php endif; ?>
        
        <!-- SUPER ADMIN MENU -->
        <?php if ($user_role == 'super_admin'): ?>
        <li class="menu-category">
            <small><i class="fas fa-crown"></i> SUPER ADMIN</small>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/superadmin/all_inventory" class="<?php echo $current_page == 'all_inventory.php' ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i>
                <span>All Inventory</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/superadmin/manage_users" class="<?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i>
                <span>Manage Users</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/superadmin/audit_trail" class="<?php echo $current_page == 'audit_trail.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Audit Trail</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/superadmin/activity_log" class="<?php echo $current_page == 'activity_log.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Activity Log</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/superadmin/reports" class="<?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>Reports</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/superadmin/system_settings" class="<?php echo $current_page == 'system_settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <span>System Settings</span>
            </a>
        </li>
        <?php endif; ?>
        
       <!-- ADMIN MENU -->
<?php if ($user_role == 'admin'): ?>

<li>
    <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'all_inventory.php' ? 'active' : ''; ?>">
        <i class="fas fa-boxes"></i>
        <span>All Inventory</span>
    </a>
</li>
<li>
    <a href="<?php echo SITE_URL; ?>/admin/add_inventory.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'add_inventory.php' ? 'active' : ''; ?>">
        <i class="fas fa-plus-circle"></i>
        <span>Add Inventory</span>
    </a>
</li>
<li>
    <a href="<?php echo SITE_URL; ?>/admin/issue_items.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'issue_items.php' ? 'active' : ''; ?>">
        <i class="fas fa-hand-holding"></i>
        <span>Issue Items</span>
    </a>
</li>
<li>
    <a href="<?php echo SITE_URL; ?>/admin/ppe_equipment.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'ppe_equipment.php' ? 'active' : ''; ?>">
        <i class="fas fa-shield-alt"></i>
        <span>PPE Equipment</span>
    </a>
</li>
<li>
    <a href="<?php echo SITE_URL; ?>/admin/semi_expendable.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'semi_expendable.php' ? 'active' : ''; ?>">
        <i class="fas fa-box-open"></i>
        <span>Semi-Expendable</span>
    </a>
</li>
<li>
    <a href="<?php echo SITE_URL; ?>/admin/locations.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'locations.php' ? 'active' : ''; ?>">
        <i class="fas fa-map-marker-alt"></i>
        <span>Locations</span>
    </a>
</li>
<li>
    <a href="<?php echo SITE_URL; ?>/admin/users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
        <i class="fas fa-users"></i>
        <span>Users</span>
    </a>
</li>
<li>
    <a href="<?php echo SITE_URL; ?>/admin/employees.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'employees.php' ? 'active' : ''; ?>">
        <i class="fas fa-user-tie"></i>
        <span>Employees</span>
    </a>
</li>
<li>
    <a href="<?php echo SITE_URL; ?>/admin/equipments.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'equipments.php' ? 'active' : ''; ?>">
        <i class="fas fa-laptop"></i>
        <span>Equipments</span>
    </a>
</li>
<?php endif; ?>
        
        <!-- SUPPLY OFFICER MENU -->
        <?php if ($user_role == 'supply'): ?>
        <li class="menu-category">
            <small><i class="fas fa-truck"></i> SUPPLY OFFICER</small>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/supply/add_inventory" class="<?php echo $current_page == 'add_inventory.php' ? 'active' : ''; ?>">
                <i class="fas fa-plus-circle"></i>
                <span>Add Inventory</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/supply/inventory_list" class="<?php echo $current_page == 'inventory_list.php' ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i>
                <span>Inventory List</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/supply/ppe_equipment" class="<?php echo $current_page == 'ppe_equipment.php' ? 'active' : ''; ?>">
                <i class="fas fa-shield-alt"></i>
                <span>PPE Equipment</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/supply/semi_expendable" class="<?php echo $current_page == 'semi_expendable.php' ? 'active' : ''; ?>">
                <i class="fas fa-box-open"></i>
                <span>Semi-Expendable</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/supply/equipment" class="<?php echo $current_page == 'equipment.php' ? 'active' : ''; ?>">
                <i class="fas fa-laptop"></i>
                <span>Equipment Types</span>
            </a>
        </li>
        <?php endif; ?>
        
        <!-- USER/END-USER MENU -->
        <?php if ($user_role == 'user'): ?>
        <li class="menu-category">
            <small><i class="fas fa-user"></i> USER</small>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/user/my_issued_items" class="<?php echo $current_page == 'my_issued_items.php' ? 'active' : ''; ?>">
                <i class="fas fa-clipboard-list"></i>
                <span>My Issued Items</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/user/view_inventory" class="<?php echo $current_page == 'view_inventory.php' ? 'active' : ''; ?>">
                <i class="fas fa-search"></i>
                <span>View Inventory</span>
            </a>
        </li>
        <?php endif; ?>
        
        <!-- Common Menu Items for all logged-in users -->
<?php if ($user_role): ?>
<li class="menu-category">
    <small><i class="fas fa-link"></i> GENERAL</small>
</li>
<li>
    <a href="<?php echo SITE_URL; ?>/profile" class="<?php echo basename($_SERVER['PHP_SELF']) == 'profile.php' ? 'active' : ''; ?>">
        <i class="fas fa-user-circle"></i>
        <span>My Profile</span>
    </a>
</li>
<li>
    <a href="<?php echo SITE_URL; ?>/logout" class="logout-btn">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
    </a>
</li>
<?php endif; ?>
    
    <div class="sidebar-footer">
        <p>&copy; <?php echo date('Y'); ?> IMS</p>
        <p>v1.0.0</p>
    </div>
</div>