<?php
/**
 * Header Template
 */

// Ensure we have access to config constants
if (!defined('BASE_PATH')) {
    require_once dirname(__DIR__) . '/config.php';
}

require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Get current user
$currentUser = getCurrentUser();

// Get system settings
$settings = [];
if (isset($conn)) {
    $result = $conn->query("SELECT setting_key, setting_value FROM system_settings");
    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
}

// Set default page title if not set
if (!isset($page_title)) {
    $page_title = $settings['system_name'] ?? 'Inventory System';
}
if (!isset($page_description)) {
    $page_description = '';
}

// Get unread notifications count (if user is logged in)
$unread_count = 0;
$notifications = [];
if (isset($_SESSION['user_id'])) {
    // You can implement notifications table later
    // For now, just set to 0
    $unread_count = 0;
    
    // Optional: Get low stock notifications for admin/supply
    if (isset($currentUser) && in_array($currentUser['role'] ?? '', ['super_admin', 'admin', 'supply'])) {
        $result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE qty_physical_count <= 5");
        if ($result) {
            $low_stock_count = $result->fetch_assoc()['count'];
            if ($low_stock_count > 0) {
                $notifications[] = [
                    'type' => 'warning',
                    'message' => "$low_stock_count items are low in stock",
                    'icon' => 'exclamation-triangle',
                    'link' => SITE_URL . '/inventory?filter=low_stock'
                ];
            }
        }
    }
    
    // Get pending issuances for admin
    if (isset($currentUser) && in_array($currentUser['role'] ?? '', ['super_admin', 'admin'])) {
        $result = $conn->query("SELECT COUNT(*) as count FROM equipment_issuance WHERE status = 'issued' AND expected_return < CURDATE()");
        if ($result) {
            $overdue_count = $result->fetch_assoc()['count'];
            if ($overdue_count > 0) {
                $notifications[] = [
                    'type' => 'danger',
                    'message' => "$overdue_count items are overdue",
                    'icon' => 'clock',
                    'link' => SITE_URL . '/admin/issue_items'
                ];
            }
        }
    }
}


// Debug: Check if CSS file exists
$css_file = ASSET_PATH . '/css/style.css';
$css_url = ASSET_URL . '/css/style.css';
$css_exists = file_exists($css_file);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo $settings['system_name'] ?? 'IIMS'; ?></title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $css_url; ?>?v=<?php echo time(); ?>">
    
     <!-- IIMS CUBES ICON - Using new color palette -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 640 640'%3E%3Cpath fill='%231A3263' d='M348 62.7C330.7 52.7 309.3 52.7 292 62.7L207.8 111.3C190.5 121.3 179.8 139.8 179.8 159.8L179.8 261.7L91.5 312.7C74.2 322.7 63.5 341.2 63.5 361.2L63.5 458.5C63.5 478.5 74.2 497 91.5 507L175.8 555.6C193.1 565.6 214.5 565.6 231.8 555.6L320.1 504.6L408.4 555.6C425.7 565.6 447.1 565.6 464.4 555.6L548.5 507C565.8 497 576.5 478.5 576.5 458.5L576.5 361.2C576.5 341.2 565.8 322.7 548.5 312.7L460.2 261.7L460.2 159.8C460.2 139.8 449.5 121.3 432.2 111.3L348 62.7zM296 356.6L296 463.1L207.7 514.1C206.5 514.8 205.1 515.2 203.7 515.2L203.7 409.9L296 356.6zM527.4 357.2C528.1 358.4 528.5 359.8 528.5 361.2L528.5 458.5C528.5 461.4 527 464 524.5 465.4L440.2 514C439 514.7 437.6 515.1 436.2 515.1L436.2 409.8L527.4 357.2zM412.3 159.8L412.3 261.7L320 315L320 208.5L411.2 155.9C411.9 157.1 412.3 158.5 412.3 159.9z'/%3E%3C/svg%3E">
    <link rel="shortcut icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 640 640'%3E%3Cpath fill='%231A3263' d='M348 62.7C330.7 52.7 309.3 52.7 292 62.7L207.8 111.3C190.5 121.3 179.8 139.8 179.8 159.8L179.8 261.7L91.5 312.7C74.2 322.7 63.5 341.2 63.5 361.2L63.5 458.5C63.5 478.5 74.2 497 91.5 507L175.8 555.6C193.1 565.6 214.5 565.6 231.8 555.6L320.1 504.6L408.4 555.6C425.7 565.6 447.1 565.6 464.4 555.6L548.5 507C565.8 497 576.5 478.5 576.5 458.5L576.5 361.2C576.5 341.2 565.8 322.7 548.5 312.7L460.2 261.7L460.2 159.8C460.2 139.8 449.5 121.3 432.2 111.3L348 62.7zM296 356.6L296 463.1L207.7 514.1C206.5 514.8 205.1 515.2 203.7 515.2L203.7 409.9L296 356.6zM527.4 357.2C528.1 358.4 528.5 359.8 528.5 361.2L528.5 458.5C528.5 461.4 527 464 524.5 465.4L440.2 514C439 514.7 437.6 515.1 436.2 515.1L436.2 409.8L527.4 357.2zM412.3 159.8L412.3 261.7L320 315L320 208.5L411.2 155.9C411.9 157.1 412.3 158.5 412.3 159.9z'/%3E%3C/svg%3E">
    
    <!-- Add pop animation to the favicon -->
    <style>
        /* This makes the browser tab icon pop */
        link[rel="icon"] {
            animation: faviconPop 3s infinite;
        }
        
        @keyframes faviconPop {
            0%, 100% { 
                transform: scale(1); 
            }
            50% { 
                transform: scale(1.2); 
            }
        }
        
        /* Add glow effect with new accent color */
        link[rel="icon"] {
            filter: drop-shadow(0 0 5px #FAB95B);
            transition: all 0.3s ease;
        }
        
    </style>
    
   
</head>
<body>
    <?php if (isset($_GET['debug'])): ?>
    <div class="debug-info">
        <strong>Debug Info:</strong><br>
        SITE_URL: <?php echo SITE_URL; ?><br>
        ASSET_URL: <?php echo ASSET_URL; ?><br>
        CSS URL: <?php echo $css_url; ?><br>
        CSS Exists: <?php echo $css_exists ? 'Yes' : 'No'; ?><br>
        Base Path: <?php echo BASE_PATH; ?><br>
        Current Page: <?php echo basename($_SERVER['PHP_SELF']); ?><br>
        User Role: <?php echo $currentUser['role'] ?? 'Not logged in'; ?><br>
        Unread Count: <?php echo $unread_count; ?>
    </div>
    <?php endif; ?>
    
    <div class="container-fluid">
        <!-- Include Sidebar -->
        <?php include INCLUDE_PATH . '/sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="main-content">
            <!-- Header -->
            <div class="header">
                <div class="header-title">
                    <h1><?php echo htmlspecialchars($page_title); ?></h1>
                    <?php if ($page_description): ?>
                        <p><?php echo htmlspecialchars($page_description); ?></p>
                    <?php endif; ?>
                </div>
                <div class="header-user">
                    <!-- Notification Pop Icon -->
                    <?php if (isset($_SESSION['user_id'])): ?>
                    <div class="header-actions">
                        <!-- Notifications Dropdown -->
                        <div class="pop-icon notification-wrapper" id="notificationIcon">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_count > 0 || !empty($notifications)): ?>
                            <span class="notification-badge"><?php echo count($notifications) ?: $unread_count; ?></span>
                            <?php endif; ?>
                            
                            <!-- Notifications Dropdown Content -->
                            <div class="notification-dropdown" id="notificationDropdown">
                                <div class="notification-header">
                                    <h4>Notifications</h4>
                                    <span class="mark-read" onclick="markAllRead()">Mark all as read</span>
                                </div>
                                <div class="notification-list">
                                    <?php if (empty($notifications)): ?>
                                    <div class="notification-empty">
                                        <i class="fas fa-bell-slash"></i>
                                        <p>No new notifications</p>
                                    </div>
                                    <?php else: ?>
                                        <?php foreach ($notifications as $notif): ?>
                                        <a href="<?php echo $notif['link']; ?>" class="notification-item <?php echo $notif['type']; ?>">
                                            <div class="notification-icon">
                                                <i class="fas fa-<?php echo $notif['icon']; ?>"></i>
                                            </div>
                                            <div class="notification-content">
                                                <p><?php echo $notif['message']; ?></p>
                                                <small>Just now</small>
                                            </div>
                                        </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-footer">
                                    <a href="<?php echo SITE_URL; ?>/notifications">View All</a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Messages Pop Icon -->
                        <div class="pop-icon" data-tooltip="Messages">
                            <i class="fas fa-envelope"></i>
                            <span class="notification-badge">0</span>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="user-info">
                        <h4><?php echo htmlspecialchars(($currentUser['firstname'] ?? '') . ' ' . ($currentUser['lastname'] ?? 'User')); ?></h4>
                        <p>
                            <?php 
                            $role_names = [
                                'super_admin' => 'Super Administrator',
                                'admin' => 'Administrator',
                                'supply' => 'Supply Officer',
                                'user' => 'End User'
                            ];
                            echo $role_names[$currentUser['role'] ?? ''] ?? ucfirst(str_replace('_', ' ', $currentUser['role'] ?? 'Guest')); 
                            ?>
                        </p>
                    </div>
                    <div class="user-avatar">
                        <?php if (!empty($currentUser['avatar']) && file_exists(UPLOAD_PATH . '/avatars/' . $currentUser['avatar'])): ?>
                            <img src="<?php echo SITE_URL; ?>/uploads/avatars/<?php echo $currentUser['avatar']; ?>" alt="Avatar">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php 
                    echo $_SESSION['success'];
                    unset($_SESSION['success']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php 
                    echo $_SESSION['error'];
                    unset($_SESSION['error']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['warning'])): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php 
                    echo $_SESSION['warning'];
                    unset($_SESSION['warning']);
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['info'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <?php 
                    echo $_SESSION['info'];
                    unset($_SESSION['info']);
                    ?>
                </div>
            <?php endif; ?>
            
            <!-- Content Start -->