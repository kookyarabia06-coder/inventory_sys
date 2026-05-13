<?php
/**
 * User Profile Page
 * Common for all user roles - view and edit profile
 */

$page_title = 'My Profile';
$page_description = 'View and manage your profile information';

require_once 'includes/auth.php';
requireLogin();

$user_id = $_SESSION['user_id'];
$user = getCurrentUser();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstname = sanitize($_POST['firstname']);
    $lastname = sanitize($_POST['lastname']);
    $email = sanitize($_POST['email']);
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    $errors = [];
    $updates = [];
    
    // Update basic info using prepared statement
    if (!empty($firstname) && !empty($lastname) && !empty($email)) {
        $stmt = $conn->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ? WHERE id = ?");
        $stmt->bind_param("sssi", $firstname, $lastname, $email, $user_id);
        if ($stmt->execute()) {
            $updates[] = "Profile information updated";
        }
        $stmt->close();
    }
    
    // Handle password change
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        // Verify current password using prepared statement
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $db_password = $result->fetch_assoc()['password'];
        $stmt->close();
        
        if (password_verify($current_password, $db_password)) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    $stmt->execute();
                    $stmt->close();
                    $updates[] = "Password changed successfully";
                } else {
                    $errors[] = "New password must be at least 6 characters long";
                }
            } else {
                $errors[] = "New passwords do not match";
            }
        } else {
            $errors[] = "Current password is incorrect";
        }
    }
    
    // Handle avatar upload
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] == UPLOAD_ERR_OK) {
        $avatar = uploadFile($_FILES['avatar'], 'uploads/avatars/');
        if ($avatar) {
            $stmt = $conn->prepare("UPDATE users SET avatar = ? WHERE id = ?");
            $stmt->bind_param("si", $avatar, $user_id);
            $stmt->execute();
            $stmt->close();
            $updates[] = "Avatar updated successfully";
        }
    }
    
    if (!empty($updates)) {
        $_SESSION['success'] = implode(". ", $updates);
        logActivity('Profile Update', $user_id, 'Updated profile information');
    }
    
    if (!empty($errors)) {
        $_SESSION['error'] = implode(". ", $errors);
    }
    
    // Refresh user data
    header('Location: /profile');
    exit();
}

// Get user statistics using prepared statements
$stats = [];

// Items currently issued
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM user_inventory WHERE user_id = ? AND status = 'active'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['issued'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Total items ever issued
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM equipment_issuance WHERE issued_to = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['total_issued'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Items returned
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM equipment_issuance WHERE issued_to = ? AND status = 'returned'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stats['returned'] = $stmt->get_result()->fetch_assoc()['count'];
$stmt->close();

// Member since
$member_since = date('F Y', strtotime($user['created_at']));

include 'includes/header.php';
?>

<style>
/* Profile Header */
.profile-header {
    background: white;
    border-radius: 15px;
    padding: 30px;
    text-align: center;
    margin-bottom: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.profile-avatar {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    margin: 0 auto 20px;
    overflow: hidden;
    background: #f0f0f0;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 4px solid #6B8CFF;
}

.profile-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-avatar i {
    font-size: 60px;
    color: #ccc;
}

.profile-name {
    font-size: 24px;
    font-weight: 700;
    color: #333;
    margin-bottom: 10px;
}

.profile-role {
    margin-bottom: 20px;
}

.profile-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 20px;
    margin-top: 25px;
    padding-top: 25px;
    border-top: 1px solid #eee;
}

.profile-stat {
    text-align: center;
}

.profile-stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #6B8CFF;
}

.profile-stat-label {
    font-size: 12px;
    color: #999;
    margin-top: 5px;
}

/* Tabs */
.tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    background: white;
    border-radius: 10px;
    padding: 5px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.tab {
    flex: 1;
    padding: 12px 20px;
    text-align: center;
    cursor: pointer;
    border-radius: 8px;
    font-weight: 500;
    transition: all 0.3s;
    color: #666;
}

.tab:hover {
    background: #f0f0f0;
}

.tab.active {
    background: #6B8CFF;
    color: white;
}

/* Tab Content */
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

/* Form Styles */
.form-container {
    background: white;
    border-radius: 15px;
    padding: 30px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: #333;
}

.form-control {
    width: 100%;
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.3s;
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: #6B8CFF;
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.form-control:disabled {
    background: #f5f5f5;
    cursor: not-allowed;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 20px;
}

/* Avatar Upload */
.image-upload {
    border: 2px dashed #ccc;
    border-radius: 10px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: all 0.3s;
    margin-bottom: 10px;
}

.image-upload:hover {
    border-color: #6B8CFF;
    background: #f8f9ff;
}

.image-upload i {
    font-size: 32px;
    color: #ccc;
    margin-bottom: 10px;
}

.image-upload p {
    font-size: 13px;
    color: #999;
    margin: 0;
}

#avatar_preview {
    text-align: center;
    margin-top: 15px;
}

#avatar_preview img {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid #6B8CFF;
}

/* Table Styles */
.table-container {
    background: white;
    border-radius: 15px;
    padding: 20px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    overflow-x: auto;
}

.table-header {
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid #FFD8E0;
}

.table-header h2 {
    color: #6B8CFF;
    font-size: 18px;
    margin: 0;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 900px;
}

th {
    padding: 12px 10px;
    text-align: left;
    background: #f8f9ff;
    color: #6B8CFF;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid #e0e0e0;
}

td {
    padding: 12px 10px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 13px;
    color: #555;
}

tr:hover td {
    background: #fafbff;
}

.text-center {
    text-align: center;
}

.text-muted {
    color: #999;
}

/* Badges */
.badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.badge-danger {
    background: #ffebee;
    color: #f44336;
}

.badge-warning {
    background: #fff3e0;
    color: #ff9800;
}

.badge-success {
    background: #e8f5e9;
    color: #4caf50;
}

.badge-info {
    background: #e3f2fd;
    color: #2196f3;
}

.badge-secondary {
    background: #f0f0f0;
    color: #666;
}

/* Button */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 12px 24px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
}

.btn-primary {
    background: #F8B0C0;
    color: #333;
}

.btn-primary:hover {
    background: #e69eb0;
    transform: translateY(-2px);
}

/* Responsive */
@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
    
    .profile-stats {
        grid-template-columns: repeat(2, 1fr);
    }
    
    table {
        min-width: 600px;
    }
}
</style>

<div class="profile-header">
    <div class="profile-avatar">
        <?php if ($user['avatar'] && file_exists('uploads/avatars/' . $user['avatar'])): ?>
            <img src="uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
        <?php else: ?>
            <i class="fas fa-user"></i>
        <?php endif; ?>
    </div>
    <div class="profile-name"><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></div>
    <div class="profile-role">
        <span class="badge <?php 
            echo $user['role'] == 'super_admin' ? 'badge-danger' : 
                ($user['role'] == 'admin' ? 'badge-warning' : 'badge-success'); 
        ?>" style="font-size: 14px; padding: 8px 20px;">
            <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
        </span>
    </div>
    
    <div class="profile-stats">
        <div class="profile-stat">
            <div class="profile-stat-value"><?php echo $stats['issued']; ?></div>
            <div class="profile-stat-label">Currently Issued</div>
        </div>
        <div class="profile-stat">
            <div class="profile-stat-value"><?php echo $stats['total_issued']; ?></div>
            <div class="profile-stat-label">Total Issued</div>
        </div>
        <div class="profile-stat">
            <div class="profile-stat-value"><?php echo $stats['returned']; ?></div>
            <div class="profile-stat-label">Returned</div>
        </div>
        <div class="profile-stat">
            <div class="profile-stat-value"><?php echo $member_since; ?></div>
            <div class="profile-stat-label">Member Since</div>
        </div>
    </div>
</div>

<!-- Profile Tabs -->
<div class="tabs">
    <div class="tab active" data-tab="profile">Profile Information</div>
    <div class="tab" data-tab="issued">Issued Items</div>
</div>

<!-- Profile Information Tab -->
<div id="profile-tab" class="tab-content active">
    <div class="form-container">
        <h2 style="margin-bottom: 20px;">Edit Profile</h2>
        
        <form method="POST" action="" enctype="multipart/form-data" id="profileForm">
            <div class="form-group">
                <label for="avatar">Profile Picture</label>
                <div class="image-upload" onclick="document.getElementById('avatar').click();">
                    <i class="fas fa-camera"></i>
                    <p>Click to upload new avatar (Max 2MB, JPG/PNG)</p>
                </div>
                <input type="file" id="avatar" name="avatar" accept="image/*" style="display: none;" onchange="previewAvatar(this)">
                <div id="avatar_preview">
                    <?php if ($user['avatar'] && file_exists('uploads/avatars/' . $user['avatar'])): ?>
                        <img src="uploads/avatars/<?php echo htmlspecialchars($user['avatar']); ?>" alt="Avatar">
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="firstname">First Name *</label>
                    <input type="text" class="form-control" id="firstname" name="firstname" 
                           value="<?php echo htmlspecialchars($user['firstname']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="lastname">Last Name *</label>
                    <input type="text" class="form-control" id="lastname" name="lastname" 
                           value="<?php echo htmlspecialchars($user['lastname']); ?>" required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                <small class="text-muted">Username cannot be changed</small>
            </div>
            
            <div class="form-group">
                <label for="email">Email Address *</label>
                <input type="email" class="form-control" id="email" name="email" 
                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
            </div>
            
            <h3 style="margin: 30px 0 20px;">Change Password</h3>
            <p class="text-muted" style="margin-bottom: 20px;">Leave blank if you don't want to change your password</p>
            
            <div class="form-group">
                <label for="current_password">Current Password</label>
                <input type="password" class="form-control" id="current_password" name="current_password">
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" class="form-control" id="new_password" name="new_password" minlength="6">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6">
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>


<!-- Issued Items Tab -->
<div id="issued-tab" class="tab-content">
    <div class="table-container">
        <div class="table-header" style="display: flex; justify-content: space-between; align-items: center;">
            <h2>Items Issued to You</h2>
            <button type="button" class="btn btn-primary" onclick="printIssuedItems()" style="padding: 8px 16px;">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
        
        <div id="printable-items" style="overflow-x: auto;">
            <table>
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Property No.</th>
                        <th>Issued By</th>
                        <th>Reissued From</th>
                        <th>Reissued By</th>
                        <th>Issue Date</th>
                        <th>Return Date</th>
                        <th>Quantity</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Simple query that only joins tables we know exist
                    $stmt = $conn->prepare("
                        SELECT ei.*, 
                               i.article_name, i.property_no, i.uom,
                               CONCAT(u.firstname, ' ', u.lastname) as issued_by_name
                        FROM equipment_issuance ei
                        JOIN inventory i ON ei.inventory_id = i.id
                        JOIN users u ON ei.issued_by = u.id
                        WHERE ei.issued_to = ?
                        ORDER BY ei.issued_date DESC
                    ");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $issued_items = $stmt->get_result();
                    
                    // Debug: Check if query returns results
                    $num_rows = $issued_items ? $issued_items->num_rows : 0;
                    
                    if ($num_rows > 0):
                        while($item = $issued_items->fetch_assoc()):
                            // Get status badge
                            $status_badge = '';
                            $status = strtolower($item['status'] ?? 'issued');
                            if ($status === 'issued') {
                                $status_badge = '<span class="badge badge-warning">Issued</span>';
                            } elseif ($status === 'returned') {
                                $status_badge = '<span class="badge badge-success">Returned</span>';
                            } elseif ($status === 'cancelled') {
                                $status_badge = '<span class="badge badge-danger">Cancelled</span>';
                            } else {
                                $status_badge = '<span class="badge badge-secondary">' . htmlspecialchars($status) . '</span>';
                            }
                    ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($item['article_name']); ?></strong></td>
                        <td><code><?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?></code></td>
                        <td><?php echo htmlspecialchars($item['issued_by_name']); ?></td>
                        <td><span class="text-muted">-</span></td>
                        <td><span class="text-muted">-</span></td>
                        <td><?php echo date('M d, Y', strtotime($item['issued_date'])); ?></td>
                        <td>
                            <?php 
                            if ($item['actual_return']) {
                                echo date('M d, Y', strtotime($item['actual_return']));
                            } elseif ($item['status'] === 'returned') {
                                echo '<span class="badge badge-success">Returned</span>';
                            } else {
                                echo '<span class="badge badge-warning">Not returned</span>';
                            }
                            ?>
                        </td>
                        <td><?php echo $item['quantity_issued'] . ' ' . htmlspecialchars($item['uom'] ?? 'pcs'); ?></td>
                        <td><?php echo $status_badge; ?></td>
                    </tr>
                    <?php 
                        endwhile;
                    else:
                    ?>
                    <tr>
                        <td colspan="9" class="text-center" style="padding: 40px;">
                            <i class="fas fa-box-open" style="font-size: 48px; color: #ccc; display: block; margin-bottom: 15px;"></i>
                            <p>No items issued yet</p>
                            <small>Debug: Found <?php echo $num_rows; ?> records for user ID <?php echo $user_id; ?></small>
                        </td>
                    </tr>
                    <?php 
                    endif;
                    $stmt->close();
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
// Print function for issued items - Receipt style (without Fund Cluster)
function printIssuedItems() {
    // Get the current user name
    const userName = '<?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?>';
    
    // Get all issued items data from the table
    const tableRows = document.querySelectorAll('#issued-tab table tbody tr');
    let itemsHtml = '';
    let itemCount = 0;
    
    // Loop through visible rows (skip the "no items" row)
    tableRows.forEach((row) => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 8 && !row.querySelector('td[colspan]')) {
            itemCount++;
            itemsHtml += `
                <tr>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">${itemCount}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;">${cells[0]?.innerText || ''}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;">${cells[1]?.innerText || ''}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;">${cells[2]?.innerText || ''}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd;">${cells[5]?.innerText || ''}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">${cells[7]?.innerText || ''}</td>
                    <td style="padding: 10px; border-bottom: 1px solid #ddd; text-align: center;">${cells[8]?.innerText || ''}</td>
                </tr>
            `;
        }
    });
    
    // Today's date
    const today = new Date();
    const formattedDate = today.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    // Generate random receipt number
    const receiptNo = 'YB-920A ' + Math.floor(Math.random() * 1000000);
    
    // Create the receipt HTML (styled like your image - NO FUND CLUSTER)
    const printHtml = `
        <!DOCTYPE html>
        <html>
        <head>
            <title>PROPERTY RECEIPT - ${userName}</title>
            <meta charset="UTF-8">
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                
                body {
                    font-family: 'Courier New', Courier, monospace;
                    padding: 40px 20px;
                    background: white;
                }
                
                .receipt {
                    max-width: 800px;
                    margin: 0 auto;
                    background: white;
                }
                
                /* Header Section */
                .header {
                    text-align: center;
                    margin-bottom: 30px;
                    border-bottom: 2px solid #000;
                    padding-bottom: 15px;
                }
                
                .hospital-name {
                    font-size: 14px;
                    font-weight: bold;
                    letter-spacing: 1px;
                    margin-bottom: 5px;
                }
                
                .hospital-address {
                    font-size: 11px;
                    margin-bottom: 3px;
                }
                
                .hospital-sub {
                    font-size: 10px;
                }
                
                .receipt-title {
                    font-size: 18px;
                    font-weight: bold;
                    margin: 15px 0 5px;
                    text-transform: uppercase;
                    text-align: center;
                }
                
                .receipt-number {
                    font-size: 12px;
                    margin-bottom: 10px;
                    text-align: center;
                }
                
                /* Property Card Section (like in the image) */
                .property-card {
                    border: 2px solid #000;
                    margin: 20px 0;
                    font-size: 12px;
                }
                
                .property-card-header {
                    background: #000;
                    color: white;
                    padding: 8px;
                    text-align: center;
                    font-weight: bold;
                    letter-spacing: 2px;
                }
                
                .property-row {
                    display: flex;
                    border-bottom: 1px solid #000;
                }
                
                .property-label {
                    width: 140px;
                    padding: 8px;
                    font-weight: bold;
                    border-right: 1px solid #000;
                    background: #f5f5f5;
                }
                
                .property-value {
                    flex: 1;
                    padding: 8px;
                }
                
                /* Items Table */
                .items-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin: 20px 0;
                    font-size: 11px;
                }
                
                .items-table th {
                    border: 1px solid #000;
                    padding: 8px;
                    text-align: center;
                    font-weight: bold;
                    background: #f5f5f5;
                }
                
                .items-table td {
                    border: 1px solid #000;
                    padding: 8px;
                }
                
                /* Issued To Info */
                .issued-info {
                    margin: 15px 0;
                    padding: 10px;
                    border: 1px dashed #999;
                    font-size: 12px;
                }
                
                /* Signatures */
                .signatures {
                    margin-top: 40px;
                    display: flex;
                    justify-content: space-between;
                }
                
                .signature-box {
                    text-align: center;
                    width: 45%;
                }
                
                .signature-line {
                    border-top: 1px solid #000;
                    margin-top: 40px;
                    margin-bottom: 8px;
                    width: 100%;
                }
                
                .signature-label {
                    font-size: 10px;
                }
                
                .signature-name {
                    font-weight: bold;
                    margin-top: 5px;
                }
                
                /* REV MARKS Section */
                .rev-marks {
                    margin-top: 30px;
                    font-size: 10px;
                    border-top: 1px solid #ccc;
                    padding-top: 15px;
                }
                
                .rev-marks-line {
                    margin-top: 10px;
                    letter-spacing: 2px;
                }
                
                /* Footer */
                .footer {
                    margin-top: 30px;
                    text-align: center;
                    font-size: 9px;
                    border-top: 1px solid #ccc;
                    padding-top: 15px;
                }
                
                hr {
                    margin: 15px 0;
                }
                
                .text-center {
                    text-align: center;
                }
                
                .bold {
                    font-weight: bold;
                }
            </style>
        </head>
        <body>
            <div class="receipt">
                <!-- HEADER -->
                <div class="header">
                    <div class="hospital-name">'AMANG' RODRIGUEZ MEMORIAL MEDICAL CENTER</div>
                    <div class="hospital-address">Sumulong Highway, Sto. Niño, Marikina City</div>
                    <div class="hospital-sub">(Ministry of Health - Zanzibar)</div>
                </div>
                
                <!-- RECEIPT TITLE -->
                <div class="receipt-title">PROPERTY RECEIPT</div>
                <div class="receipt-number">Receipt No.: ${receiptNo}</div>
                
                
                
                <!-- ISSUED TO INFO (Fund Owner section REMOVED as requested) -->
                <div class="issued-info">
                    <div><strong>Issued To:</strong> ${userName}</div>
                    <div><strong>Date Issued:</strong> ${formattedDate}</div>
                </div>
                
                <!-- ITEMS TABLE -->
                <table class="items-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Item Description</th>
                            <th>Property No.</th>
                            <th>Issued By</th>
                            <th>Issue Date</th>
                            <th>Qty</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${itemsHtml || '<tr><td colspan="7" style="text-align: center; padding: 20px;">No items issued to this employee</td></tr>'}
                    </tbody>
                </table>
                
                <!-- SIGNATURES -->
                <div class="signatures">
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-label">Received by:</div>
                        <div class="signature-name">${userName}</div>
                        <div style="font-size: 9px;">(Signature over Printed Name)</div>
                    </div>
                    <div class="signature-box">
                        <div class="signature-line"></div>
                        <div class="signature-label">Issued by:</div>
                        <div class="signature-name">_________________________________</div>
                        <div style="font-size: 9px;">(Authorized Property Officer)</div>
                    </div>
                </div>
                
              
            
            <script>
                // Auto-trigger print
                window.onload = function() {
                    window.print();
                    setTimeout(function() { window.close(); }, 1000);
                }
            <\/script>
        </body>
        </html>
    `;
    
    // Open print window
    const printWindow = window.open('', '_blank', 'width=900,height=700,scrollbars=yes');
    printWindow.document.write(printHtml);
    printWindow.document.close();
}

// Tab switching function - single parameter version
function showTab(tabName) {
    console.log('Switching to tab:', tabName);
    
    // Hide all tab contents
    document.querySelectorAll('.tab-content').forEach(function(tab) {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tab buttons
    document.querySelectorAll('.tab').forEach(function(tab) {
        tab.classList.remove('active');
    });
    
    // Show selected tab content
    var tabContent = document.getElementById(tabName + '-tab');
    if (tabContent) {
        tabContent.classList.add('active');
    }
    
    // Add active class to the correct tab button
    var tabButton = document.querySelector('.tab[data-tab="' + tabName + '"]');
    if (tabButton) {
        tabButton.classList.add('active');
    }
}

// Avatar preview function
function previewAvatar(input) {
    if (input.files && input.files[0]) {
        // Check file size (max 2MB)
        if (input.files[0].size > 2 * 1024 * 1024) {
            alert('Image too large. Please choose an image under 2MB.');
            input.value = '';
            return;
        }
        
        // Check file type
        if (!input.files[0].type.match('image.*')) {
            alert('Please select a valid image file (JPG, PNG, GIF).');
            input.value = '';
            return;
        }
        
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatar_preview').innerHTML = 
                '<img src="' + e.target.result + '" alt="Preview">';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

// Initialize everything when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Profile page initialized');
    
    // Set up tab click handlers
    document.querySelectorAll('.tab').forEach(function(tab) {
        tab.addEventListener('click', function() {
            var tabName = this.getAttribute('data-tab');
            console.log('Tab clicked:', tabName);
            if (tabName) {
                showTab(tabName);
            }
        });
    });
    
    // Set up form validation
    var profileForm = document.getElementById('profileForm');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            var newPass = document.getElementById('new_password').value;
            var confirmPass = document.getElementById('confirm_password').value;
            
            // Only validate if user is trying to change password
            if (newPass || confirmPass) {
                if (newPass.length > 0 && newPass.length < 6) {
                    e.preventDefault();
                    alert('New password must be at least 6 characters long');
                    return false;
                }
                if (newPass !== confirmPass) {
                    e.preventDefault();
                    alert('New passwords do not match');
                    return false;
                }
            }
        });
    }
    
    // Show profile tab by default
    showTab('profile');
});
</script>

<?php include 'includes/footer.php'; ?>