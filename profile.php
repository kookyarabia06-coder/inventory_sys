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
    
    // Update basic info
    if (!empty($firstname) && !empty($lastname) && !empty($email)) {
        $conn->query("UPDATE users SET firstname = '$firstname', lastname = '$lastname', email = '$email' WHERE id = $user_id");
        $updates[] = "Profile information updated";
    }
    
    // Handle password change
    if (!empty($current_password) || !empty($new_password) || !empty($confirm_password)) {
        // Verify current password
        $result = $conn->query("SELECT password FROM users WHERE id = $user_id");
        $db_password = $result->fetch_assoc()['password'];
        
        if (password_verify($current_password, $db_password)) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $conn->query("UPDATE users SET password = '$hashed_password' WHERE id = $user_id");
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
            $conn->query("UPDATE users SET avatar = '$avatar' WHERE id = $user_id");
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

// Get user statistics
$stats = [];

// Items currently issued
$stats['issued'] = $conn->query("
    SELECT COUNT(*) as count 
    FROM user_inventory 
    WHERE user_id = $user_id AND status = 'active'
")->fetch_assoc()['count'];

// Total items ever issued
$stats['total_issued'] = $conn->query("
    SELECT COUNT(*) as count 
    FROM equipment_issuance 
    WHERE issued_to = $user_id
")->fetch_assoc()['count'];

// Items returned
$stats['returned'] = $conn->query("
    SELECT COUNT(*) as count 
    FROM equipment_issuance 
    WHERE issued_to = $user_id AND status = 'returned'
")->fetch_assoc()['count'];

// Member since
$member_since = date('F Y', strtotime($user['created_at']));

include 'includes/header.php';
?>

<div class="profile-header">
    <div class="profile-avatar">
        <?php if ($user['avatar'] && file_exists('uploads/avatars/' . $user['avatar'])): ?>
            <img src="/uploads/avatars/<?php echo $user['avatar']; ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
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
    <div class="tab active" onclick="showTab('profile')">Profile Information</div>
    <div class="tab" onclick="showTab('activity')">Activity Log</div>
    <div class="tab" onclick="showTab('issued')">Issued Items</div>
</div>

<!-- Profile Information Tab -->
<div id="profile-tab" class="tab-content active">
    <div class="form-container">
        <h2 style="margin-bottom: 20px;">Edit Profile</h2>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group">
                <label for="avatar">Profile Picture</label>
                <div class="image-upload" onclick="document.getElementById('avatar').click();">
                    <i class="fas fa-camera"></i>
                    <p>Click to upload new avatar</p>
                </div>
                <input type="file" id="avatar" name="avatar" accept="image/*" style="display: none;" onchange="previewAvatar(this)">
                <div id="avatar_preview" style="margin-top: 10px; text-align: center;">
                    <?php if ($user['avatar'] && file_exists('uploads/avatars/' . $user['avatar'])): ?>
                        <img src="/uploads/avatars/<?php echo $user['avatar']; ?>" style="max-width: 100px; max-height: 100px; border-radius: 50%;">
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
                    <input type="password" class="form-control" id="new_password" name="new_password">
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password">
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

<!-- Activity Log Tab -->
<div id="activity-tab" class="tab-content">
    <div class="table-container">
        <div class="table-header">
            <h2>Your Recent Activity</h2>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Action</th>
                    <th>Item</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $activities = $conn->query("
                    SELECT al.*, i.article_name 
                    FROM activity_log al
                    LEFT JOIN inventory i ON al.item_id = i.id
                    WHERE al.user_id = $user_id
                    ORDER BY al.date_created DESC
                    LIMIT 50
                ");
                
                if ($activities->num_rows > 0):
                    while($activity = $activities->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo date('M d, Y h:i A', strtotime($activity['date_created'])); ?></td>
                    <td>
                        <span class="badge badge-info"><?php echo htmlspecialchars($activity['action']); ?></span>
                    </td>
                    <td><?php echo htmlspecialchars($activity['article_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($activity['details']); ?></td>
                </tr>
                <?php 
                    endwhile;
                else:
                ?>
                <tr>
                    <td colspan="4" class="text-center">No activity found</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Issued Items Tab -->
<div id="issued-tab" class="tab-content">
    <div class="table-container">
        <div class="table-header">
            <h2>Items Issued to You</h2>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Property No.</th>
                    <th>Issued By</th>
                    <th>Issue Date</th>
                    <th>Return Date</th>
                    <th>Quantity</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $issued_items = $conn->query("
                    SELECT ei.*, 
                           i.article_name, i.property_no, i.uom,
                           CONCAT(u.firstname, ' ', u.lastname) as issued_by_name
                    FROM equipment_issuance ei
                    JOIN inventory i ON ei.inventory_id = i.id
                    JOIN users u ON ei.issued_by = u.id
                    WHERE ei.issued_to = $user_id
                    ORDER BY ei.issued_date DESC
                ");
                
                if ($issued_items->num_rows > 0):
                    while($item = $issued_items->fetch_assoc()):
                ?>
                <tr>
                    <td><?php echo htmlspecialchars($item['article_name']); ?></td>
                    <td><?php echo htmlspecialchars($item['property_no']); ?></td>
                    <td><?php echo htmlspecialchars($item['issued_by_name']); ?></td>
                    <td><?php echo date('M d, Y', strtotime($item['issued_date'])); ?></td>
                    <td>
                        <?php 
                        if ($item['actual_return']) {
                            echo date('M d, Y', strtotime($item['actual_return']));
                        } else {
                            echo 'Not returned';
                        }
                        ?>
                    </td>
                    <td><?php echo $item['quantity_issued'] . ' ' . $item['uom']; ?></td>
                    <td><?php echo getStatusBadge($item['status']); ?></td>
                </tr>
                <?php 
                    endwhile;
                else:
                ?>
                <tr>
                    <td colspan="7" class="text-center">No items issued yet</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function showTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all tabs
    document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Show selected tab
    document.getElementById(tabName + '-tab').classList.add('active');
    
    // Add active class to clicked tab
    event.target.classList.add('active');
}

function previewAvatar(input) {
    if (input.files && input.files[0]) {
        let reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('avatar_preview').innerHTML = 
                '<img src="' + e.target.result + '" style="max-width: 100px; max-height: 100px; border-radius: 50%;">';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

// Form validation
document.querySelector('form').addEventListener('submit', function(e) {
    let newPass = document.getElementById('new_password').value;
    let confirmPass = document.getElementById('confirm_password').value;
    
    if (newPass || confirmPass) {
        if (newPass.length < 6 && newPass.length > 0) {
            e.preventDefault();
            alert('New password must be at least 6 characters long');
        } else if (newPass !== confirmPass) {
            e.preventDefault();
            alert('New passwords do not match');
        }
    }
});
</script>

<?php include 'includes/footer.php'; ?>