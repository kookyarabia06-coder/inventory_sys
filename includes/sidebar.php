

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

// Get notification counts (if database connection exists)
$pending_users_count = 0;
$pending_issues_count = 0;

if (isset($conn) && $conn && ($user_role == 'super_admin' || $user_role == 'admin')) {
    // Check if functions exist before calling them
    if (function_exists('getPendingUsersCount')) {
        $pending_users_count = getPendingUsersCount($conn);
    } else {
        // Fallback query if function doesn't exist
        $query = "SELECT COUNT(*) as count FROM users WHERE status = 'pending'";
        $result = mysqli_query($conn, $query);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $pending_users_count = $row['count'];
        }
    }
    
    if (function_exists('getPendingIssuesCount')) {
        $pending_issues_count = getPendingIssuesCount($conn);
    } else {
        // Fallback query if function doesn't exist
        $query = "SELECT COUNT(*) as count FROM equipment_issuance WHERE status = 'pending'";
        $result = mysqli_query($conn, $query);
        if ($result) {
            $row = mysqli_fetch_assoc($result);
            $pending_issues_count = $row['count'];
        }
    }
}
?>

<div class="sidebar">
    <div class="sidebar-logo">
        <div>
            <img src="<?php echo ASSET_URL; ?>/icons/armmc.png" alt="Rodriguez Memorial Medical Center" style="width: 100px; height: auto; margin-bottom: 15px;">
        </div>
        <h2 class="logo-text">IMS</h2>
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
        
        <!-- SUPER ADMIN MENU (Same as Admin + Extra Items) -->
        <?php if ($user_role == 'super_admin'): ?>
        <li class="menu-category">
            <small> INVENTORY MANAGEMENT</small>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'all_inventory.php' ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i>
                <span>All Inventory</span>
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/report.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>Reports</span>
            </a>
        </li>
        
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
        <li class="menu-category">
            <small> BUILD UP MANAGEMENT</small>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/locations.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'locations.php' ? 'active' : ''; ?>">
                <i class="fas fa-map-marker-alt"></i>
                <span>Locations</span>
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
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-coins"></i>
                <span>Settings</span>
            </a>
        </li>
        <li class="menu-category">
            <small> USER MANAGEMENT</small>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/issue_items.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'issue_items.php' ? 'active' : ''; ?>">
                <i class="fas fa-hand-holding"></i>
                <span>Issue Items</span>
                <?php if ($pending_issues_count > 0): ?>
                    <span class="notification-badge"><?php echo $pending_issues_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        
        <!-- EXTRA SUPER ADMIN ONLY ITEMS -->
        <li class="menu-category">
            <small> SYSTEM MANAGEMENT</small>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/superadmin/manage_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users-cog"></i>
                <span>Manage User Roles</span>
                <?php if ($pending_users_count > 0): ?>
                    <span class="notification-badge"><?php echo $pending_users_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/superadmin/audit_trail.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'audit_trail.php' ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Audit Trail</span>
            </a>
        </li>
        <?php endif; ?>
        
        <!-- ADMIN MENU -->
        <?php if ($user_role == 'admin'): ?>
        <li class="menu-category">
            <small> INVENTORY MANAGEMENT</small>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'all_inventory.php' ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i>
                <span>All Inventory</span>
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/report.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'report.php' ? 'active' : ''; ?>">
                <i class="fas fa-file-alt"></i>
                <span>Reports</span>
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
        <li class="menu-category">
            <small> BUILD UP MANAGEMENT</small>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/locations.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'locations.php' ? 'active' : ''; ?>">
                <i class="fas fa-map-marker-alt"></i>
                <span>Locations</span>
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
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-coins"></i>
                <span>Settings</span>
            </a>
        </li>
        <li class="menu-category">
            <small> USER MANAGEMENT</small>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Users</span>
                <?php if ($pending_users_count > 0): ?>
                    <span class="notification-badge"><?php echo $pending_users_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li>
            <a href="<?php echo SITE_URL; ?>/admin/issue_items.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'issue_items.php' ? 'active' : ''; ?>">
                <i class="fas fa-hand-holding"></i>
                <span>Issue Items</span>
                <?php if ($pending_issues_count > 0): ?>
                    <span class="notification-badge"><?php echo $pending_issues_count; ?></span>
                <?php endif; ?>
            </a>
        </li>
        <?php endif; ?>
        
        <!-- SUPPLY OFFICER MENU -->
        <?php if ($user_role == 'supply'): ?>
        <li class="menu-category">
            <small><i class="fas fa-truck"></i> SUPPLY OFFICER</small>
        </li>
        
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/admin/all_inventory" class="<?php echo $current_page == 'all_inventory.php' ? 'active' : ''; ?>">
                <i class="fas fa-boxes"></i>
                <span>Inventory List</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/admin/ppe_equipment" class="<?php echo $current_page == 'ppe_equipment.php' ? 'active' : ''; ?>">
                <i class="fas fa-shield-alt"></i>
                <span>PPE Equipment</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/admin/semi_expendable" class="<?php echo $current_page == 'semi_expendable.php' ? 'active' : ''; ?>">
                <i class="fas fa-box-open"></i>
                <span>Semi-Expendable</span>
            </a>
        </li>
        <li>
            <a href="<?php echo $base_path; ?>/admin/equipments" class="<?php echo $current_page == 'equipments.php' ? 'active' : ''; ?>">
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
            <a href="<?php echo SITE_URL; ?>/logout" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </li>
        <?php endif; ?>
    </ul>
</div>

<style>
/* Sidebar Logo Animation - Spinning Circle */
.sidebar-logo {
    text-align: center;
    padding: 20px 0;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 20px;
}

.logo-animation {
    display: inline-block;
    width: 80px;
    height: 80px;
    position: relative;
    animation: spinCircle 2s linear infinite;
}

.logo-animation i {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 40px;
    color: #fff;
    filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.5));
}

/* Spinning circle effect */
.logo-animation::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    border: 3px solid transparent;
    border-top: 3px solid #fff;
    border-right: 3px solid rgba(255, 255, 255, 0.5);
    border-radius: 50%;
    animation: spinCircle 2s linear infinite;
}

.logo-animation::after {
    content: '';
    position: absolute;
    top: 5px;
    left: 5px;
    width: calc(100% - 10px);
    height: calc(100% - 10px);
    border: 3px solid transparent;
    border-bottom: 3px solid #fff;
    border-left: 3px solid rgba(255, 255, 255, 0.5);
    border-radius: 50%;
    animation: spinCircle 3s linear infinite reverse;
}

.logo-animation:hover {
    animation-play-state: paused;
}

.logo-animation:hover::before,
.logo-animation:hover::after {
    animation-play-state: paused;
}

.logo-animation:hover i {
    animation: none;
    transform: translate(-50%, -50%) scale(1.1);
    filter: drop-shadow(0 0 20px rgba(255, 255, 255, 0.8));
}

/* Spinning animation */
@keyframes spinCircle {
    from {
        transform: rotate(0deg);
    }
    to {
        transform: rotate(360deg);
    }
}

.logo-text {
    animation: fadeInUp 0.8s ease-out;
    margin: 15px 0 5px 0;
    font-size: 24px;
    font-weight: bold;
    color: #fff;
}

.sidebar-logo p {
    animation: fadeInUp 0.8s ease-out 0.2s both;
    font-size: 12px;
    opacity: 0.8;
    color: #fff;
}

/* Fade In Up Animation */
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Optional: Add pulsing effect to the center icon */
@keyframes pulseCenter {
    0%, 100% {
        filter: drop-shadow(0 0 5px rgba(255, 255, 255, 0.3));
    }
    50% {
        filter: drop-shadow(0 0 15px rgba(255, 255, 255, 0.7));
    }
}

.logo-animation i {
    animation: pulseCenter 2s ease-in-out infinite;
}

/* ========== NOTIFICATION BADGE - ABOVE THE TEXT ========== */
.notification-badge {
    background: #ffe0e7;
    color: #d43f6b;
    border-radius: 50%;
    padding: 2px 6px;
    font-size: 10px;
    font-weight: 700;
    min-width: 18px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    line-height: 1;
    box-shadow: none;
    border: none;
    position: absolute;
    right: 15px;
    top: 50%;
    transform: translateY(-50%);
}

/* Sidebar menu item styling */
.sidebar-menu li a {
    display: flex;
    align-items: center;
    gap: 12px;
    position: relative;
    padding: 10px 15px;
    text-decoration: none;
}

.sidebar-menu li a i {
    width: 20px;
    text-align: center;
    flex-shrink: 0;
}

.sidebar-menu li a span {
    flex: 1;
}

/* Hover effect */
.sidebar-menu li a:hover .notification-badge {
    background: #ffd0db;
    transform: translateY(-50%) scale(1.05);
}
</style>
