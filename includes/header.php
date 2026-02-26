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
    
     <!-- IIMS CUBES ICON - Updated with professional palette -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 640 640'%3E%3Cpath fill='%236B8CFF' d='M348 62.7C330.7 52.7 309.3 52.7 292 62.7L207.8 111.3C190.5 121.3 179.8 139.8 179.8 159.8L179.8 261.7L91.5 312.7C74.2 322.7 63.5 341.2 63.5 361.2L63.5 458.5C63.5 478.5 74.2 497 91.5 507L175.8 555.6C193.1 565.6 214.5 565.6 231.8 555.6L320.1 504.6L408.4 555.6C425.7 565.6 447.1 565.6 464.4 555.6L548.5 507C565.8 497 576.5 478.5 576.5 458.5L576.5 361.2C576.5 341.2 565.8 322.7 548.5 312.7L460.2 261.7L460.2 159.8C460.2 139.8 449.5 121.3 432.2 111.3L348 62.7zM296 356.6L296 463.1L207.7 514.1C206.5 514.8 205.1 515.2 203.7 515.2L203.7 409.9L296 356.6zM527.4 357.2C528.1 358.4 528.5 359.8 528.5 361.2L528.5 458.5C528.5 461.4 527 464 524.5 465.4L440.2 514C439 514.7 437.6 515.1 436.2 515.1L436.2 409.8L527.4 357.2zM412.3 159.8L412.3 261.7L320 315L320 208.5L411.2 155.9C411.9 157.1 412.3 158.5 412.3 159.9z'/%3E%3C/svg%3E">
    <link rel="shortcut icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 640 640'%3E%3Cpath fill='%236B8CFF' d='M348 62.7C330.7 52.7 309.3 52.7 292 62.7L207.8 111.3C190.5 121.3 179.8 139.8 179.8 159.8L179.8 261.7L91.5 312.7C74.2 322.7 63.5 341.2 63.5 361.2L63.5 458.5C63.5 478.5 74.2 497 91.5 507L175.8 555.6C193.1 565.6 214.5 565.6 231.8 555.6L320.1 504.6L408.4 555.6C425.7 565.6 447.1 565.6 464.4 555.6L548.5 507C565.8 497 576.5 478.5 576.5 458.5L576.5 361.2C576.5 341.2 565.8 322.7 548.5 312.7L460.2 261.7L460.2 159.8C460.2 139.8 449.5 121.3 432.2 111.3L348 62.7zM296 356.6L296 463.1L207.7 514.1C206.5 514.8 205.1 515.2 203.7 515.2L203.7 409.9L296 356.6zM527.4 357.2C528.1 358.4 528.5 359.8 528.5 361.2L528.5 458.5C528.5 461.4 527 464 524.5 465.4L440.2 514C439 514.7 437.6 515.1 436.2 515.1L436.2 409.8L527.4 357.2zM412.3 159.8L412.3 261.7L320 315L320 208.5L411.2 155.9C411.9 157.1 412.3 158.5 412.3 159.9z'/%3E%3C/svg%3E">
    
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
            filter: drop-shadow(0 0 5px #F8B0C0);
            transition: all 0.3s ease;
        }
        
        /* Pop Icon Custom Styles - Professional Palette */
        .pop-icon {
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background-color: #F0F0F0;
            color: #3A3A3A;
            transition: all 0.2s ease;
            margin: 0 4px;
        }
        
        .pop-icon i {
            font-size: 16px;
            color: #6B6B6B;
            transition: all 0.2s ease;
        }
        
        .pop-icon:hover {
            background-color: #6B8CFF;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(107, 140, 255, 0.2);
        }
        
        .pop-icon:hover i {
            color: white;
        }
        
        .pop-icon:active {
            transform: scale(0.95);
        }
        
        /* Notification Wrapper */
        .notification-wrapper {
            position: relative;
        }
        
        .notification-badge {
            position: absolute;
            top: -4px;
            right: -4px;
            background-color: #F8B0C0;
            color: white;
            font-size: 10px;
            font-weight: 600;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 4px;
            border: 2px solid white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Notification Dropdown */
        .notification-dropdown {
            position: absolute;
            top: 45px;
            right: -10px;
            width: 340px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            border: 1px solid #E0E0E0;
            z-index: 1000;
            display: none;
            overflow: hidden;
        }
        
        .notification-dropdown.show {
            display: block;
            animation: slideDown 0.2s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-8px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid #E0E0E0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background-color: #F8F9FA;
        }
        
        .notification-header h4 {
            font-size: 14px;
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #3A3A3A;
        }
        
        .notification-header h4 i {
            color: #6B8CFF;
            font-size: 16px;
        }
        
        .notification-header .mark-read {
            font-size: 12px;
            cursor: pointer;
            color: #6B8CFF;
            transition: color 0.2s;
            padding: 4px 8px;
            border-radius: 4px;
            background: transparent;
        }
        
        .notification-header .mark-read:hover {
            color: #8FB5FF;
            background-color: rgba(107, 140, 255, 0.05);
        }
        
        .notification-list {
            max-height: 350px;
            overflow-y: auto;
        }
        
        .notification-item {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            border-bottom: 1px solid #F0F0F0;
            text-decoration: none;
            color: #3A3A3A;
            transition: background-color 0.2s;
            position: relative;
        }
        
        .notification-item:hover {
            background-color: #F8F9FA;
        }
        
        .notification-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 3px;
            background: transparent;
        }
        
        .notification-item.warning::before {
            background-color: #F8B0C0;
        }
        
        .notification-item.danger::before {
            background-color: #f44336;
        }
        
        .notification-item.success::before {
            background-color: #4CAF50;
        }
        
        .notification-item.info::before {
            background-color: #8FB5FF;
        }
        
        .notification-icon {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #F0F0F0;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            color: #6B8CFF;
            flex-shrink: 0;
        }
        
        .notification-item.warning .notification-icon {
            background-color: #FFF3E0;
            color: #F57C00;
        }
        
        .notification-item.danger .notification-icon {
            background-color: #FFEBEE;
            color: #C62828;
        }
        
        .notification-item.success .notification-icon {
            background-color: #E8F5E9;
            color: #2E7D32;
        }
        
        .notification-item.info .notification-icon {
            background-color: #E3F2FD;
            color: #1976D2;
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-content p {
            font-size: 13px;
            margin-bottom: 4px;
            line-height: 1.4;
            font-weight: 500;
            color: #3A3A3A;
        }
        
        .notification-content small {
            font-size: 11px;
            color: #9E9E9E;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .notification-content small i {
            font-size: 10px;
        }
        
        .notification-empty {
            padding: 40px 20px;
            text-align: center;
            color: #9E9E9E;
        }
        
        .notification-empty i {
            font-size: 40px;
            margin-bottom: 12px;
            color: #E0E0E0;
        }
        
        .notification-empty p {
            font-size: 13px;
            color: #9E9E9E;
        }
        
        .notification-footer {
            padding: 12px 20px;
            border-top: 1px solid #E0E0E0;
            text-align: center;
            background-color: #F8F9FA;
        }
        
        .notification-footer a {
            color: #6B8CFF;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: color 0.2s;
            display: inline-block;
            padding: 4px 12px;
            border-radius: 16px;
        }
        
        .notification-footer a:hover {
            color: #8FB5FF;
            background-color: rgba(107, 140, 255, 0.05);
        }
        
        /* Header Actions */
        .header-actions {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-right: 20px;
        }
        
        .header-actions .pop-icon {
            background-color: #F0F0F0;
            width: 38px;
            height: 38px;
        }
        
        .header-actions .pop-icon i {
            color: #6B6B6B;
        }
        
        .header-actions .pop-icon:hover {
            background-color: #6B8CFF;
        }
        
        .header-actions .pop-icon:hover i {
            color: white;
        }
        
        /* Tooltip for pop icons */
        .pop-icon[data-tooltip] {
            position: relative;
        }
        
        .pop-icon[data-tooltip]::before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 120%;
            left: 50%;
            transform: translateX(-50%);
            background-color: #2C3E50;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 11px;
            white-space: nowrap;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            pointer-events: none;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            font-weight: 400;
            letter-spacing: 0.3px;
        }
        
        .pop-icon[data-tooltip]::after {
            content: '';
            position: absolute;
            bottom: 110%;
            left: 50%;
            transform: translateX(-50%);
            border-width: 4px;
            border-style: solid;
            border-color: #2C3E50 transparent transparent transparent;
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            pointer-events: none;
        }
        
        .pop-icon[data-tooltip]:hover::before,
        .pop-icon[data-tooltip]:hover::after {
            opacity: 1;
            visibility: visible;
            bottom: 130%;
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
                        <div class="pop-icon notification-wrapper" id="notificationIcon" data-tooltip="Notifications">
                            <i class="fas fa-bell"></i>
                            <?php if ($unread_count > 0 || !empty($notifications)): ?>
                            <span class="notification-badge"><?php echo count($notifications) ?: $unread_count; ?></span>
                            <?php endif; ?>
                            
                            <!-- Notifications Dropdown Content -->
                            <div class="notification-dropdown" id="notificationDropdown">
                                <div class="notification-header">
                                    <h4><i class="fas fa-bell"></i> Notifications</h4>
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
                                                <small><i class="far fa-clock"></i> Just now</small>
                                            </div>
                                        </a>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                                <div class="notification-footer">
                                    <a href="<?php echo SITE_URL; ?>/notifications">View All Notifications</a>
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