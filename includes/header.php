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

// Role names for display
$role_names = [
    'super_admin' => 'Super Administrator',
    'admin' => 'Administrator',
    'supply' => 'Supply Officer',
    'user' => 'End User'
];

// Get current date and time
date_default_timezone_set('Asia/Manila');
$current_date_full = date('l, F j, Y'); // Monday, May 4, 2026
$current_time = date('h:i A'); // 01:45 PM

// Debug: Check if CSS file exists
$css_file = ASSET_PATH . '/css/style.css';
$css_url = ASSET_URL . '/css/style.css';
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
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- jQuery (for AJAX) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Select2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    
    <!-- QuaggaJS for barcode scanning -->
    <script src="https://cdn.jsdelivr.net/npm/quagga@0.12.1/dist/quagga.min.js"></script>
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="<?php echo $css_url; ?>?v=<?php echo time(); ?>">
    
    <!-- FAVICON - Try multiple paths -->
    <link rel="icon" type="image/png" href="<?php echo ASSET_URL; ?>/icons/armmc.png">
    <link rel="icon" type="image/png" href="<?php echo ASSET_URL; ?>/icons/armmc.png">
    <link rel="icon" type="image/png" href="<?php echo ASSET_URL; ?>/icons/armmc.png">
    <link rel="apple-touch-icon" href="<?php echo ASSET_URL; ?>/icons/armmc.png">
    <link rel="shortcut icon" href="<?php echo ASSET_URL; ?>/icons/armmc.png">
    
    <style>
        /* Reset and Base Styles */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background-color: #F5F7FA;
            overflow-x: hidden;
        }
        
        .container-fluid {
            display: flex;
            min-height: 100vh;
        }
        
        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 20px 30px;
            transition: all 0.3s;
        }
        
        /* Header */
        .header {
            background: white;
            border-radius: 16px;
            padding: 20px 30px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            position: relative;
            z-index: 100;
        }
        
        .header-title h1 {
            font-size: 24px;
            font-weight: 600;
            color: #2C3E50;
            margin-bottom: 5px;
        }
        
        .header-title p {
            font-size: 14px;
            color: #7F8C8D;
        }
        
        .header-user {
            display: flex;
            align-items: center;
            gap: 25px;
        }
        
        /* DateTime Styles - No background color */
        .datetime-container {
            text-align: right;
            padding: 8px 0;
            min-width: 220px;
        }
        
        .date-full {
            font-size: 13px;
            font-weight: 500;
            color: #6B8CFF;
            margin-bottom: 4px;
            letter-spacing: 0.3px;
        }
        
        .time-display {
            font-size: 18px;
            font-weight: 700;
            color: #2C3E50;
            font-family: 'Inter', monospace;
        }
        
        .date-full i, .time-display i {
            margin-right: 6px;
            font-size: 12px;
            color: #6B8CFF;
        }
        
        .user-info {
            text-align: right;
        }
        
        .user-info h4 {
            font-size: 14px;
            font-weight: 600;
            color: #2C3E50;
            margin-bottom: 2px;
        }
        
        .user-info p {
            font-size: 12px;
            color: #7F8C8D;
        }
        
        .user-info-link {
            text-decoration: none;
            transition: opacity 0.2s;
        }
        
        .user-info-link:hover {
            opacity: 0.8;
        }
        
        /* Profile Dropdown */
        .profile-dropdown-wrapper {
            position: relative;
        }
        
        .user-avatar {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            width: 45px;
            height: 45px;
            border-radius: 50%;
            overflow: hidden;
            background: linear-gradient(135deg, #6B8CFF, #8FB5FF);
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .user-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .user-avatar i {
            font-size: 22px;
            color: white;
        }
        
        .user-avatar:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 12px rgba(107, 140, 255, 0.4);
        }
        
        .profile-dropdown {
            position: fixed;
            top: 70px;
            right: 20px;
            width: 280px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            border: 1px solid #E0E0E0;
            z-index: 99999;
            display: none;
            overflow: hidden;
        }
        
        .profile-dropdown.show {
            display: block;
            animation: slideDown 0.2s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .profile-dropdown-header {
            padding: 20px;
            background: linear-gradient(135deg, #6B8CFF, #8FB5FF);
            color: white;
            text-align: center;
        }
        
        .profile-dropdown-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            margin: 0 auto 10px;
            overflow: hidden;
            background: white;
            border: 3px solid white;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .profile-dropdown-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .profile-dropdown-avatar i {
            font-size: 36px;
            color: #6B8CFF;
            line-height: 60px;
        }
        
        .profile-dropdown-header h4 {
            margin: 0 0 5px;
            font-size: 16px;
        }
        
        .profile-dropdown-header p {
            margin: 0;
            font-size: 11px;
            opacity: 0.9;
            word-break: break-all;
        }
        
        .profile-dropdown-menu {
            padding: 8px 0;
        }
        
        .profile-dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 20px;
            color: #333;
            text-decoration: none;
            transition: background-color 0.2s;
            cursor: pointer;
        }
        
        .profile-dropdown-item:hover {
            background-color: #F8F9FA;
        }
        
        .profile-dropdown-item i {
            width: 20px;
            font-size: 16px;
            color: #6B8CFF;
        }
        
        .profile-dropdown-divider {
            height: 1px;
            background-color: #E0E0E0;
            margin: 8px 0;
        }
        
        .profile-dropdown-item.logout {
            color: #EF4444;
        }
        
        .profile-dropdown-item.logout i {
            color: #EF4444;
        }
        
        /* Alert Messages */
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background-color: #E8F5E9;
            color: #2E7D32;
            border-left: 4px solid #4CAF50;
        }
        
        .alert-danger {
            background-color: #FFEBEE;
            color: #C62828;
            border-left: 4px solid #F44336;
        }
        
        .alert-warning {
            background-color: #FFF3E0;
            color: #E65100;
            border-left: 4px solid #FF9800;
        }
        
        .alert-info {
            background-color: #E3F2FD;
            color: #1565C0;
            border-left: 4px solid #2196F3;
        }
        
        /* Profile Modal */
        .profile-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            z-index: 999999;
            align-items: center;
            justify-content: center;
        }
        
        .profile-modal.show {
            display: flex;
        }
        
        .profile-modal-content {
            background: white;
            border-radius: 16px;
            width: 90%;
            max-width: 500px;
            max-height: 85vh;
            overflow-y: auto;
            animation: modalSlideIn 0.3s ease;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .profile-modal-header {
            padding: 20px;
            border-bottom: 1px solid #E0E0E0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #6B8CFF, #8FB5FF);
            color: white;
            border-radius: 16px 16px 0 0;
        }
        
        .profile-modal-header h3 {
            margin: 0;
            font-size: 18px;
        }
        
        .profile-modal-header .close-modal {
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            font-size: 20px;
            cursor: pointer;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
        }
        
        .profile-modal-header .close-modal:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .profile-modal-body {
            padding: 20px;
        }
        
        .avatar-upload-section {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .current-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #6B8CFF;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            margin-bottom: 15px;
            cursor: pointer;
        }
        
        .current-avatar-placeholder {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: #F0F0F0;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
            color: #6B8CFF;
            border: 3px solid #6B8CFF;
            cursor: pointer;
        }
        
        .avatar-upload-btn {
            background: #6B8CFF;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            font-size: 13px;
            margin-top: 10px;
            transition: all 0.2s;
        }
        
        .avatar-upload-btn:hover {
            background: #5a7ae6;
            transform: translateY(-1px);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 13px;
        }
        
        .form-group input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.2s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #6B8CFF;
            box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
        }
        
        .form-group input:disabled {
            background: #F5F5F5;
            color: #999;
        }
        
        .profile-modal-footer {
            padding: 15px 20px;
            border-top: 1px solid #E0E0E0;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .btn-save {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-save:hover {
            background: #388E3C;
            transform: translateY(-1px);
        }
        
        .btn-cancel {
            background: #f44336;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-cancel:hover {
            background: #d32f2f;
            transform: translateY(-1px);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .header {
                flex-direction: column;
                gap: 15px;
            }
            
            .header-title {
                text-align: center;
            }
            
            .datetime-container {
                text-align: center;
                width: 100%;
            }
            
            .profile-dropdown {
                right: 10px;
                top: 60px;
            }
        }
    </style>
</head>
<body>
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
                    <!-- Date and Time Display - No background color -->
                    <div class="datetime-container">
                        <div class="date-full">
                            <i class="far fa-calendar-alt"></i> <?php echo $current_date_full; ?>
                        </div>
                        <div class="time-display" id="liveTime">
                            <i class="far fa-clock"></i> <?php echo $current_time; ?>
                        </div>
                    </div>
                    
                    <!-- User Info -->
                    <a href="<?php echo SITE_URL; ?>" class="user-info-link">
                        <div class="user-info">
                            <h4><?php echo htmlspecialchars(($currentUser['firstname'] ?? '') . ' ' . ($currentUser['lastname'] ?? 'User')); ?></h4>
                            <p><?php echo $role_names[$currentUser['role'] ?? ''] ?? ucfirst(str_replace('_', ' ', $currentUser['role'] ?? 'Guest')); ?></p>
                        </div>
                    </a>
                    
                    <!-- Profile Dropdown -->
                    <div class="profile-dropdown-wrapper">
                        <div class="user-avatar" id="profileAvatarBtn">
                            <?php if (!empty($currentUser['avatar']) && file_exists(UPLOAD_PATH . '/avatars/' . $currentUser['avatar'])): ?>
                                <img src="<?php echo SITE_URL; ?>/uploads/avatars/<?php echo $currentUser['avatar']; ?>" alt="Avatar">
                            <?php else: ?>
                                <i class="fas fa-user"></i>
                            <?php endif; ?>
                        </div>
                        
                        <div class="profile-dropdown" id="profileDropdown">
                            <div class="profile-dropdown-header">
                                <div class="profile-dropdown-avatar">
                                    <?php if (!empty($currentUser['avatar']) && file_exists(UPLOAD_PATH . '/avatars/' . $currentUser['avatar'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/avatars/<?php echo $currentUser['avatar']; ?>" alt="Avatar">
                                    <?php else: ?>
                                        <i class="fas fa-user"></i>
                                    <?php endif; ?>
                                </div>
                                <h4><?php echo htmlspecialchars(($currentUser['firstname'] ?? '') . ' ' . ($currentUser['lastname'] ?? 'User')); ?></h4>
                                <p><?php echo htmlspecialchars($currentUser['email'] ?? ''); ?></p>
                            </div>
                            <div class="profile-dropdown-menu">
                                <a href="<?php echo SITE_URL; ?>/profile.php" class="profile-dropdown-item">
                                    <i class="fas fa-user-circle"></i>
                                    <span>My Profile</span>
                                </a>
                                <a href="#" class="profile-dropdown-item" id="editProfileBtn">
                                    <i class="fas fa-edit"></i>
                                    <span>Edit Profile</span>
                                </a>
                                <a href="<?php echo SITE_URL; ?>/change_password.php" class="profile-dropdown-item">
                                    <i class="fas fa-lock"></i>
                                    <span>Change Password</span>
                                </a>
                                <div class="profile-dropdown-divider"></div>
                                <a href="<?php echo SITE_URL; ?>/logout.php" class="profile-dropdown-item logout">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Alert Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['warning'])): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo $_SESSION['warning']; unset($_SESSION['warning']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['info'])): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i>
                    <?php echo $_SESSION['info']; unset($_SESSION['info']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Profile Edit Modal -->
            <div id="profileModal" class="profile-modal">
                <div class="profile-modal-content">
                    <div class="profile-modal-header">
                        <h3><i class="fas fa-user-circle"></i> Edit Profile</h3>
                        <button class="close-modal" onclick="closeProfileModal()">&times;</button>
                    </div>
                    <form id="profileForm" enctype="multipart/form-data">
                        <div class="profile-modal-body">
                            <div class="avatar-upload-section">
                                <div id="avatarPreview" onclick="document.getElementById('avatarInput').click()" style="cursor: pointer;">
                                    <?php if (!empty($currentUser['avatar']) && file_exists(UPLOAD_PATH . '/avatars/' . $currentUser['avatar'])): ?>
                                        <img src="<?php echo SITE_URL; ?>/uploads/avatars/<?php echo $currentUser['avatar']; ?>" class="current-avatar" id="previewImg">
                                    <?php else: ?>
                                        <div class="current-avatar-placeholder" id="previewPlaceholder">
                                            <i class="fas fa-user"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" id="avatarInput" name="avatar" accept="image/*" style="display: none;" onchange="previewAvatar(this)">
                                <div>
                                    <button type="button" class="avatar-upload-btn" onclick="document.getElementById('avatarInput').click()">
                                        <i class="fas fa-camera"></i> Change Avatar
                                    </button>
                                </div>
                                <p style="font-size: 11px; color: #999; margin-top: 8px;">Click the image or button to upload (JPG, PNG, GIF, max 2MB)</p>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> First Name</label>
                                <input type="text" name="firstname" value="<?php echo htmlspecialchars($currentUser['firstname'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-user"></i> Last Name</label>
                                <input type="text" name="lastname" value="<?php echo htmlspecialchars($currentUser['lastname'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-envelope"></i> Email</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-user-tag"></i> Username</label>
                                <input type="text" value="<?php echo htmlspecialchars($currentUser['username'] ?? ''); ?>" disabled>
                                <small style="color: #999;">Username cannot be changed</small>
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> New Password (leave blank to keep current)</label>
                                <input type="password" name="new_password" placeholder="Enter new password">
                            </div>
                            
                            <div class="form-group">
                                <label><i class="fas fa-lock"></i> Confirm New Password</label>
                                <input type="password" name="confirm_password" placeholder="Confirm new password">
                            </div>
                        </div>
                        <div class="profile-modal-footer">
                            <button type="button" class="btn-cancel" onclick="closeProfileModal()">Cancel</button>
                            <button type="submit" class="btn-save"><i class="fas fa-save"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- JavaScript -->
            <script>
                // Live Time Update Function
                function updateLiveTime() {
                    const now = new Date();
                    let hours = now.getHours();
                    const minutes = now.getMinutes().toString().padStart(2, '0');
                    const ampm = hours >= 12 ? 'PM' : 'AM';
                    hours = hours % 12;
                    hours = hours ? hours : 12;
                    const timeString = hours + ':' + minutes + ' ' + ampm;
                    const timeElement = document.getElementById('liveTime');
                    if (timeElement) {
                        timeElement.innerHTML = '<i class="far fa-clock"></i> ' + timeString;
                    }
                }
                
                // Update time every minute
                setInterval(updateLiveTime, 60000);
                updateLiveTime(); // Initial call
                
                // Profile Dropdown
                const profileAvatarBtn = document.getElementById('profileAvatarBtn');
                const profileDropdown = document.getElementById('profileDropdown');
                
                if (profileAvatarBtn) {
                    profileAvatarBtn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        e.preventDefault();
                        profileDropdown.classList.toggle('show');
                        
                        // Calculate position
                        if (profileDropdown.classList.contains('show')) {
                            const rect = profileAvatarBtn.getBoundingClientRect();
                            profileDropdown.style.position = 'fixed';
                            profileDropdown.style.top = (rect.bottom + 5) + 'px';
                            profileDropdown.style.right = (window.innerWidth - rect.right) + 'px';
                        }
                    });
                }
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (profileDropdown && profileAvatarBtn && !profileAvatarBtn.contains(e.target) && !profileDropdown.contains(e.target)) {
                        profileDropdown.classList.remove('show');
                    }
                });
                
                // Profile Modal Functions
                function openProfileModal() {
                    document.getElementById('profileModal').classList.add('show');
                }
                
                function closeProfileModal() {
                    document.getElementById('profileModal').classList.remove('show');
                }
                
                // Edit Profile button in dropdown
                document.getElementById('editProfileBtn')?.addEventListener('click', function(e) {
                    e.preventDefault();
                    profileDropdown.classList.remove('show');
                    openProfileModal();
                });
                
                // Preview avatar before upload
                function previewAvatar(input) {
                    if (input.files && input.files[0]) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            const previewImg = document.getElementById('previewImg');
                            const previewPlaceholder = document.getElementById('previewPlaceholder');
                            
                            if (previewImg) {
                                previewImg.src = e.target.result;
                            } else if (previewPlaceholder) {
                                const newImg = document.createElement('img');
                                newImg.id = 'previewImg';
                                newImg.className = 'current-avatar';
                                newImg.src = e.target.result;
                                previewPlaceholder.parentNode.replaceChild(newImg, previewPlaceholder);
                            } else {
                                const avatarPreview = document.getElementById('avatarPreview');
                                const newImg = document.createElement('img');
                                newImg.id = 'previewImg';
                                newImg.className = 'current-avatar';
                                newImg.src = e.target.result;
                                avatarPreview.innerHTML = '';
                                avatarPreview.appendChild(newImg);
                            }
                        };
                        reader.readAsDataURL(input.files[0]);
                    }
                }
                
                // Profile form submission
                $('#profileForm').on('submit', function(e) {
                    e.preventDefault();
                    
                    const formData = new FormData(this);
                    
                    Swal.fire({
                        title: 'Updating Profile...',
                        text: 'Please wait',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    $.ajax({
                        url: '<?php echo SITE_URL; ?>/ajax/update_profile.php',
                        type: 'POST',
                        data: formData,
                        processData: false,
                        contentType: false,
                        dataType: 'json',
                        success: function(response) {
                            if (response.success) {
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Profile Updated!',
                                    text: response.message,
                                    timer: 2000,
                                    showConfirmButton: false
                                }).then(() => {
                                    location.reload();
                                });
                            } else {
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Update Failed',
                                    text: response.message
                                });
                            }
                        },
                        error: function() {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: 'An error occurred while updating your profile.'
                            });
                        }
                    });
                });
                
                // Close modal on escape key
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') closeProfileModal();
                });
                
                // Close modal when clicking outside
                window.onclick = function(e) {
                    const modal = document.getElementById('profileModal');
                    if (e.target === modal) closeProfileModal();
                };
            </script>