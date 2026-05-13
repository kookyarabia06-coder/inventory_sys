<?php
/**
 * Users Page (Admin)
 * View system users with pending approval management
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';
require_once INCLUDE_PATH . '/smtp_mailer.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

// Check role properly
$user_role = $_SESSION['role'] ?? '';
if ($user_role !== 'admin' && $user_role !== 'super_admin') {
    header('Location: ' . SITE_URL . '/dashboard.php?error=unauthorized');
    exit();
}

$page_title = 'Users';
$page_description = 'View and manage system users';

// ============================================
// USE THE EXISTING logAuditEvent FUNCTION FROM auth.php
// ============================================
// DO NOT redeclare logAudit() here - it's already in auth.php
// Use logAuditEvent() function instead

// Handle unlock account action
if (isset($_POST['unlock_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    $user_query = $conn->prepare("SELECT username, email, locked_until FROM users WHERE id = ?");
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $user_data = $user_query->get_result()->fetch_assoc();
    $user_query->close();
    
    if ($user_data && !empty($user_data['locked_until']) && strtotime($user_data['locked_until']) > time()) {
        $stmt = $conn->prepare("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute()) {
            $_SESSION['success'] = "Account for " . htmlspecialchars($user_data['username']) . " has been unlocked successfully.";
            
            // AUDIT: Manual unlock using existing function
            if (function_exists('logAuditEvent')) {
                logAuditEvent($conn, $_SESSION['user_id'], 'MANUAL_UNLOCK', 'SECURITY', 
                    "Manually unlocked account for user: " . $user_data['username'],
                    "Unlocked user: {$user_data['username']}", 'users', $user_id);
            }
            
            if (function_exists('logActivity')) {
                logActivity('Account Unlocked', $user_id, "Account for " . $user_data['username'] . " was manually unlocked by " . ($_SESSION['username'] ?? 'Admin'));
            }
        } else {
            $_SESSION['error'] = "Failed to unlock account.";
        }
        $stmt->close();
    } elseif ($user_data && (!empty($user_data['locked_until']) && strtotime($user_data['locked_until']) <= time())) {
        $conn->query("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = $user_id");
        $_SESSION['success'] = "Account for " . htmlspecialchars($user_data['username']) . " was already expired. Lock has been cleared.";
        
        // AUDIT: Lock cleared
        if (function_exists('logAuditEvent')) {
            logAuditEvent($conn, $_SESSION['user_id'], 'LOCK_CLEARED', 'SECURITY', 
                "Cleared expired lock for user: " . $user_data['username'],
                "User: {$user_data['username']}", 'users', $user_id);
        }
    } else {
        $_SESSION['error'] = "This account is not locked.";
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $user_id = (int)$_POST['user_id'];
    
    if ($action === 'approve') {
        $user_query = $conn->prepare("SELECT * FROM users WHERE id = ? AND status = 'pending'");
        $user_query->bind_param("i", $user_id);
        $user_query->execute();
        $user_result = $user_query->get_result();
        $user = $user_result->fetch_assoc();
        
        if (!$user) {
            $_SESSION['error'] = "User not found or already processed.";
            $user_query->close();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        $user_query->close();
        
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $email_sent = sendApprovalEmail($user);
            
            if ($email_sent) {
                $_SESSION['success'] = "User approved successfully. Approval email sent to " . $user['email'];
            } else {
                $_SESSION['success'] = "User approved successfully, but email notification failed to send. Please check SMTP settings.";
                error_log("Failed to send approval email to: " . $user['email'] . " for user: " . $user['username']);
            }
            
            // AUDIT: User approved
            if (function_exists('logAuditEvent')) {
                logAuditEvent($conn, $_SESSION['user_id'], 'USER_APPROVED', 'USER_MANAGEMENT', 
                    "User approved: " . $user['username'],
                    "Approved user: {$user['username']} | Email: {$user['email']} | Role: {$user['role']}", 'users', $user_id);
            }
            
            if (function_exists('logActivity')) {
                logActivity('User Approved', $user_id, "User " . $user['username'] . " was approved by " . ($_SESSION['username'] ?? 'Admin'));
            }
            
            if (function_exists('logUserApproval')) {
                logUserApproval($_SESSION['user_id'], $user_id, 'approved', $user);
            }
        } else {
            $_SESSION['error'] = "Failed to approve user.";
        }
        $stmt->close();
    } 
    elseif ($action === 'reject') {
        $user_query = $conn->prepare("SELECT * FROM users WHERE id = ? AND status = 'pending'");
        $user_query->bind_param("i", $user_id);
        $user_query->execute();
        $user_result = $user_query->get_result();
        $user = $user_result->fetch_assoc();
        
        if (!$user) {
            $_SESSION['error'] = "User not found or already processed.";
            $user_query->close();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        }
        $user_query->close();
        
        $email_sent = sendRejectionEmail($user);
        
        // AUDIT: User rejected (before deletion)
        if (function_exists('logAuditEvent')) {
            logAuditEvent($conn, $_SESSION['user_id'], 'USER_REJECTED', 'USER_MANAGEMENT', 
                "User registration rejected: " . $user['username'],
                "Rejected user: {$user['username']} | Email: {$user['email']} | Rejection email sent: " . ($email_sent ? 'Yes' : 'No'), 'users', $user_id);
        }
        
        $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND status = 'pending'");
        $delete_stmt->bind_param("i", $user_id);
        
        if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
            if ($email_sent) {
                $_SESSION['success'] = "User rejected and removed. Rejection email sent to " . $user['email'];
            } else {
                $_SESSION['success'] = "User rejected and removed, but email notification failed to send.";
                error_log("Failed to send rejection email to: " . $user['email'] . " for user: " . $user['username']);
            }
            
            if (function_exists('logActivity')) {
                logActivity('User Rejected', $user_id, "User " . $user['username'] . " was rejected by " . ($_SESSION['username'] ?? 'Admin'));
            }
            
            if (function_exists('logUserApproval')) {
                logUserApproval($_SESSION['user_id'], $user_id, 'rejected', $user);
            }
        } else {
            $_SESSION['error'] = "Failed to reject user.";
        }
        $delete_stmt->close();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle AJAX requests for user data
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_user') {
    header('Content-Type: application/json');
    $user_id = (int)$_GET['user_id'];
    $stmt = $conn->prepare("SELECT id, username, email, firstname, lastname, role, status, locked_until, login_attempts, created_at, avatar FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    echo json_encode($user);
    exit();
}

// Handle update user (edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $user_id = (int)$_POST['user_id'];
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $status = $_POST['status'];
    
    // Get old values for audit
    $old_query = $conn->prepare("SELECT firstname, lastname, email, role, status FROM users WHERE id = ?");
    $old_query->bind_param("i", $user_id);
    $old_query->execute();
    $old_data = $old_query->get_result()->fetch_assoc();
    $old_query->close();
    
    $stmt = $conn->prepare("UPDATE users SET firstname = ?, lastname = ?, email = ?, role = ?, status = ? WHERE id = ?");
    $stmt->bind_param("sssssi", $firstname, $lastname, $email, $role, $status, $user_id);
    
    if ($stmt->execute()) {
        // AUDIT: User updated
        $changes = [];
        if ($old_data['firstname'] != $firstname) $changes[] = "First name: {$old_data['firstname']} → $firstname";
        if ($old_data['lastname'] != $lastname) $changes[] = "Last name: {$old_data['lastname']} → $lastname";
        if ($old_data['email'] != $email) $changes[] = "Email: {$old_data['email']} → $email";
        if ($old_data['role'] != $role) $changes[] = "Role: {$old_data['role']} → $role";
        if ($old_data['status'] != $status) $changes[] = "Status: {$old_data['status']} → $status";
        
        $details = !empty($changes) ? implode(" | ", $changes) : "No changes made";
        
        if (function_exists('logAuditEvent')) {
            logAuditEvent($conn, $_SESSION['user_id'], 'USER_UPDATED', 'USER_MANAGEMENT', 
                "User updated: " . ($_SESSION['username'] ?? 'Unknown'),
                $details, 'users', $user_id);
        }
        
        if (function_exists('logUserUpdate')) {
            $new_data = array('firstname' => $firstname, 'lastname' => $lastname, 'email' => $email, 'role' => $role, 'status' => $status);
            logUserUpdate($_SESSION['user_id'], $user_id, $old_data, $new_data);
        }
        $_SESSION['success'] = "User updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update user.";
    }
    $stmt->close();
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle add new user via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $firstname = $_POST['firstname'];
    $lastname = $_POST['lastname'];
    $username = $_POST['username'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $status = $_POST['status'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    
    // Check if username exists
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $check->bind_param("s", $username);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Username already exists.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $check->close();
    
    // Check if email exists
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        $_SESSION['error'] = "Email already exists.";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    $check->close();
    
    $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, username, email, role, status, password, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssss", $firstname, $lastname, $username, $email, $role, $status, $password);
    
    if ($stmt->execute()) {
        $new_user_id = $stmt->insert_id;
        
        // AUDIT: New user added
        if (function_exists('logAuditEvent')) {
            logAuditEvent($conn, $_SESSION['user_id'], 'USER_ADDED', 'USER_MANAGEMENT', 
                "New user added: $username",
                "Added user: $username | Name: $firstname $lastname | Email: $email | Role: $role | Status: $status", 'users', $new_user_id);
        }
        
        $_SESSION['success'] = "User added successfully.";
    } else {
        $_SESSION['error'] = "Failed to add user.";
    }
    $stmt->close();
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Handle delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    $user_query = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $user_data = $user_query->get_result()->fetch_assoc();
    $user_query->close();
    
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot delete your own account.";
    } else {
        // AUDIT: User deletion (before deletion)
        if (function_exists('logAuditEvent')) {
            logAuditEvent($conn, $_SESSION['user_id'], 'USER_DELETED', 'USER_MANAGEMENT', 
                "User deleted: " . $user_data['username'],
                "Deleted user: {$user_data['username']} | Name: {$user_data['firstname']} {$user_data['lastname']} | Email: {$user_data['email']} | Role: {$user_data['role']}", 'users', $user_id);
        }
        
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            if (function_exists('logUserDeletion')) {
                logUserDeletion($_SESSION['user_id'], $user_id, $user_data);
            }
            $_SESSION['success'] = "User deleted successfully.";
        } else {
            $_SESSION['error'] = "Failed to delete user.";
        }
        $stmt->close();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Get filter parameters
$show_inactive = isset($_GET['show_inactive']) && $_GET['show_inactive'] == '1';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$active_query = "SELECT * FROM users WHERE status != 'pending'";
if (!$show_inactive) {
    $active_query .= " AND status = 'active'";
}
if (!empty($search)) {
    $search_param = "%$search%";
    $active_query .= " AND (username LIKE ? OR firstname LIKE ? OR lastname LIKE ? OR email LIKE ?)";
}
$active_query .= " ORDER BY CASE role WHEN 'super_admin' THEN 1 WHEN 'admin' THEN 2 ELSE 3 END, created_at DESC";

if (!empty($search)) {
    $stmt = $conn->prepare($active_query);
    $stmt->bind_param("ssss", $search_param, $search_param, $search_param, $search_param);
    $stmt->execute();
    $users = $stmt->get_result();
} else {
    $users = $conn->query($active_query);
}

$pending_query = "SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC";
$pending_users = $conn->query($pending_query);

include INCLUDE_PATH . '/header.php';
?>

<!-- REST OF THE HTML AND JAVASCRIPT REMAINS THE SAME AS THE PREVIOUS VERSION -->
<!-- The CSS, modals, and JavaScript sections stay identical -->

<style>
/* Keep all existing styles - they remain the same as before */
:root {
    --primary: #6B8CFF;
    --secondary: #8FB5FF;
    --accent: #F8B0C0;
    --accent-light: #FFD8E0;
    --success-light: #C5E8C5;
    --light: #F0F0F0;
    --white: #FFFFFF;
    --border-light: #E0E0E0;
    --text-primary: #3A3A3A;
    --text-secondary: #6B6B6B;
    --text-muted: #9E9E9E;
    --text-light: #FFFFFF;
    --success: #4CAF50;
    --danger: #f44336;
    --warning: #FF9800;
    --info: #8FB5FF;
}

body {
    background-color: var(--light);
    color: var(--text-primary);
}

/* Modal Styles - Matching Settings.php */
.modal-overlay {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(3px);
}

.modal-container {
    background-color: var(--white);
    margin: 5% auto;
    padding: 25px;
    border-radius: 12px;
    width: 550px;
    max-width: 90%;
    box-shadow: 0 10px 30px rgba(107, 140, 255, 0.2);
    animation: modalSlideIn 0.3s;
}

/* Custom Delete Confirmation Modal */
.delete-modal-overlay {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(3px);
    align-items: center;
    justify-content: center;
}

.delete-modal-container {
    background-color: var(--white);
    border-radius: 16px;
    width: 450px;
    max-width: 90%;
    box-shadow: 0 20px 35px rgba(0,0,0,0.2);
    animation: modalSlideIn 0.2s;
    overflow: hidden;
}

.delete-modal-header {
    padding: 24px 24px 16px 24px;
    border-bottom: 1px solid var(--border-light);
}

.delete-modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: var(--danger);
}

.delete-modal-header h3 i {
    margin-right: 10px;
}

.success-modal-header {
    padding: 24px 24px 16px 24px;
    border-bottom: 1px solid var(--border-light);
}

.success-modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: var(--success);
}

.success-modal-header h3 i {
    margin-right: 10px;
}

.warning-modal-header {
    padding: 24px 24px 16px 24px;
    border-bottom: 1px solid var(--border-light);
}

.warning-modal-header h3 {
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    color: var(--warning);
}

.warning-modal-header h3 i {
    margin-right: 10px;
}

.delete-modal-body {
    padding: 24px;
}

.delete-warning {
    text-align: center;
    margin-bottom: 20px;
}

.delete-warning i {
    font-size: 48px;
    margin-bottom: 12px;
}

.delete-warning .fa-exclamation-triangle,
.delete-warning .fa-times-circle,
.delete-warning .fa-trash-alt {
    color: var(--danger);
}

.delete-warning .fa-user-check {
    color: var(--success);
}

.delete-warning .fa-lock {
    color: var(--warning);
}

.delete-warning p {
    margin: 8px 0;
    font-size: 16px;
}

.delete-warning .warning-text {
    color: var(--text-secondary);
    font-size: 14px;
}

.delete-item-details {
    background-color: var(--light);
    border-radius: 12px;
    padding: 16px;
    margin-top: 16px;
}

.delete-item-details .detail-label {
    font-size: 12px;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 8px;
}

.delete-item-details .detail-name {
    font-size: 16px;
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.delete-item-details .detail-extra {
    font-size: 13px;
    color: var(--text-secondary);
}

.delete-modal-footer {
    padding: 16px 24px 24px 24px;
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    border-top: 1px solid var(--border-light);
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header-settings {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-light);
}

.modal-header-settings h3 {
    color: var(--primary);
    margin: 0;
    font-size: 20px;
}

.modal-header-settings h3 i {
    color: var(--accent);
    margin-right: 10px;
}

.modal-close {
    cursor: pointer;
    font-size: 28px;
    font-weight: bold;
    color: var(--text-muted);
    transition: color 0.2s;
}

.modal-close:hover {
    color: var(--accent);
}

.modal-footer {
    text-align: right;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid var(--border-light);
}

/* View User Avatar Styles */
.avatar-large {
    width: 100px;
    height: 100px;
    margin: 0 auto 15px auto;
    border-radius: 50%;
    overflow: hidden;
    background: linear-gradient(135deg, var(--primary), var(--secondary));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}

.avatar-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-placeholder i {
    font-size: 50px;
    color: white;
}

.avatar-text {
    font-size: 42px;
    font-weight: 600;
    color: white;
    text-transform: uppercase;
}

.avatar-status {
    position: absolute;
    bottom: 4px;
    right: 4px;
    width: 22px;
    height: 22px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
}

.avatar-status i {
    font-size: 11px !important;
}

.modal-user-name {
    text-align: center;
    margin-bottom: 20px;
}

.modal-user-name h2 {
    margin: 0 0 5px 0;
    font-size: 20px;
    font-weight: 600;
    color: var(--text-primary);
}

.modal-user-name p {
    margin: 0;
    font-size: 13px;
    color: var(--text-muted);
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
}

.info-card {
    background: var(--light);
    border-radius: 8px;
    padding: 12px 15px;
}

.info-card-full {
    grid-column: span 2;
}

.info-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 5px;
}

.info-label i {
    margin-right: 5px;
}

.info-value {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
}

/* Form Styles */
.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-primary);
    font-size: 14px;
}

.form-group label i {
    color: var(--accent);
    margin-right: 8px;
    width: 16px;
}

.form-control {
    width: 100%;
    padding: 10px 12px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 14px;
    color: var(--text-primary);
    transition: all 0.3s;
    background-color: var(--white);
    box-sizing: border-box;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

select.form-control {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236B6B6B'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 18px;
}

/* Buttons */
.btn-modal {
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-modal-primary {
    background-color: var(--accent);
    color: var(--text-primary);
}

.btn-modal-primary:hover {
    background-color: #e69eb0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(248, 176, 192, 0.3);
}

.btn-modal-secondary {
    background-color: #6c757d;
    color: var(--text-light);
}

.btn-modal-secondary:hover {
    background-color: #5a6268;
    transform: translateY(-2px);
}

.btn-modal-danger {
    background-color: var(--danger);
    color: var(--text-light);
}

.btn-modal-danger:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-modal-success {
    background-color: var(--success);
    color: var(--text-light);
}

.btn-modal-success:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

.btn-modal-warning {
    background-color: var(--warning);
    color: var(--text-light);
}

.btn-modal-warning:hover {
    opacity: 0.9;
    transform: translateY(-2px);
}

/* Badge Styles */
.badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.badge-success {
    background-color: var(--success-light);
    color: var(--success);
}

.badge-danger {
    background-color: #FFEBEE;
    color: var(--danger);
}

.badge-warning {
    background-color: #FFF3E0;
    color: var(--warning);
}

.badge-info {
    background-color: #E3F2FD;
    color: #1976D2;
}

.badge-locked {
    background-color: #FFEBEE;
    color: var(--danger);
}

/* Pending Container */
.pending-container {
    background: linear-gradient(135deg, #FFF9E6 0%, var(--white) 100%);
    border-left: 4px solid var(--warning);
}

.pending-row {
    background-color: #FFF9C4;
    animation: pulseWarning 2s infinite;
}

@keyframes pulseWarning {
    0% { background-color: #FFF9C4; }
    50% { background-color: #FFE082; }
    100% { background-color: #FFF9C4; }
}

.pending-row:hover {
    background-color: #FFE082 !important;
}

.locked-row {
    background-color: #FFEBEE;
    border-left: 3px solid var(--danger);
}

.locked-row:hover {
    background-color: #FFCDD2 !important;
}

/* Table Container */
.table-container {
    background: var(--white);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-light);
    flex-wrap: wrap;
    gap: 15px;
}

.table-header h2 {
    color: var(--primary);
    font-size: 20px;
    margin: 0;
}

.table-header h2 i {
    color: var(--accent);
    margin-right: 10px;
}

/* Search Box */
.search-box {
    display: flex;
    gap: 10px;
}

.search-box input[type="text"] {
    padding: 10px 12px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 14px;
    width: 250px;
    transition: all 0.3s;
}

.search-box input[type="text"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.search-box button {
    padding: 10px 24px;
    background: var(--accent);
    color: var(--text-primary);
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
}

.search-box button:hover {
    background-color: #e69eb0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(248, 176, 192, 0.3);
}

/* Table */
table {
    width: 100%;
    border-collapse: collapse;
}

thead tr {
    background: linear-gradient(to right, var(--light), var(--white));
    border-bottom: 2px solid var(--accent-light);
}

th {
    padding: 12px 10px;
    text-align: left;
    color: var(--primary);
    font-weight: 600;
    font-size: 13px;
    white-space: nowrap;
}

td {
    padding: 12px 10px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
    font-size: 13px;
}

tr:hover {
    background-color: var(--light);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    align-items: center;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 8px;
    border: none;
    color: var(--white);
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
    font-size: 12px;
    font-weight: 500;
}

.action-btn i {
    font-size: 12px;
}

.action-btn.edit { background-color: var(--secondary); }
.action-btn.view { background-color: var(--primary); }
.action-btn.delete { background-color: var(--danger); }
.action-btn.approve { background-color: var(--success); }
.action-btn.reject { background-color: var(--danger); }
.action-btn.unlock { background-color: var(--warning); }

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
}

/* General Button */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
    text-decoration: none;
}

.btn-primary {
    background-color: var(--accent);
    color: var(--text-primary);
}

.btn-primary:hover {
    background-color: #e69eb0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(248, 176, 192, 0.3);
}

/* Alerts */
.alert {
    padding: 15px 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
    border-left: 4px solid transparent;
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from { transform: translateY(-20px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.alert-success {
    background-color: var(--success-light);
    color: var(--success);
    border-left-color: var(--success);
}

.alert-danger {
    background-color: #FFEBEE;
    color: var(--danger);
    border-left-color: var(--danger);
}

.count-badge {
    background-color: var(--accent);
    color: var(--text-primary);
    border-radius: 20px;
    padding: 2px 8px;
    font-size: 12px;
    margin-left: 8px;
}

.text-muted { color: var(--text-muted); }
.text-center { text-align: center; }

/* Responsive */
@media (max-width: 768px) {
    .table-container { 
        padding: 20px; 
        overflow-x: auto; 
    }
    
    .action-buttons { 
        flex-direction: column; 
        width: 100%; 
    }
    
    .action-btn { 
        width: 100%; 
        justify-content: center; 
    }
    
    .table-header { 
        flex-direction: column; 
        align-items: flex-start; 
    }
    
    .search-box { 
        flex-direction: column; 
        width: 100%; 
    }
    
    .search-box input[type="text"] { 
        width: 100%; 
    }
    
    .search-box button { 
        width: 100%; 
    }
    
    .modal-container {
        margin: 20% auto;
        padding: 20px;
        width: 95%;
    }
    
    .delete-modal-container {
        width: 95%;
    }
    
    .info-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .info-card-full {
        grid-column: span 1;
    }
    
    .modal-footer {
        flex-direction: column-reverse;
    }
    
    .btn-modal {
        width: 100%;
        justify-content: center;
    }
    
    .delete-modal-footer {
        flex-direction: column-reverse;
    }
}
</style>

<!-- View User Modal -->
<div id="viewModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header-settings">
            <h3><i class="fas fa-user-circle"></i> User Details</h3>
            <span class="modal-close" onclick="closeModal('viewModal')">&times;</span>
        </div>
        <div>
            <div class="avatar-large" id="view_avatar_container">
                <img id="view_avatar_img" src="" alt="Avatar" style="display: none;">
                <div id="view_avatar_placeholder" class="avatar-placeholder">
                    <i class="fas fa-user-circle"></i>
                    <span class="avatar-text" style="display: none;"></span>
                </div>
                <div class="avatar-status">
                    <i class="fas fa-circle" id="view_status_icon"></i>
                </div>
            </div>
            <div class="modal-user-name">
                <h2 id="view_fullname">-</h2>
                <p id="view_username_display">@username</p>
            </div>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label"><i class="fas fa-envelope"></i> Email Address</div>
                    <div class="info-value" id="view_email">-</div>
                </div>
                <div class="info-card">
                    <div class="info-label"><i class="fas fa-tag"></i> Role</div>
                    <div class="info-value" id="view_role">-</div>
                </div>
                <div class="info-card">
                    <div class="info-label"><i class="fas fa-user"></i> First Name</div>
                    <div class="info-value" id="view_firstname">-</div>
                </div>
                <div class="info-card">
                    <div class="info-label"><i class="fas fa-user"></i> Last Name</div>
                    <div class="info-value" id="view_lastname">-</div>
                </div>
                <div class="info-card info-card-full">
                    <div class="info-label"><i class="fas fa-calendar-alt"></i> Member Since</div>
                    <div class="info-value" id="view_created">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div id="addUserModal" class="modal-overlay">
    <div class="modal-container">
        <form method="POST" action="" id="addUserForm">
            <div class="modal-header-settings">
                <h3><i class="fas fa-user-plus"></i> Add New User</h3>
                <span class="modal-close" onclick="closeModal('addUserModal')">&times;</span>
            </div>
            <div>
                <input type="hidden" name="add_user" value="1">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> First Name *</label>
                    <input type="text" name="firstname" id="add_firstname" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Last Name *</label>
                    <input type="text" name="lastname" id="add_lastname" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-at"></i> Username *</label>
                    <input type="text" name="username" id="add_username" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email *</label>
                    <input type="email" name="email" id="add_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Role *</label>
                    <select name="role" id="add_role" class="form-control">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-circle"></i> Status</label>
                    <select name="status" id="add_status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Password *</label>
                    <input type="password" name="password" id="add_password" class="form-control" required minlength="6">
                    <small class="text-muted">Minimum 6 characters</small>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-lock"></i> Confirm Password *</label>
                    <input type="password" id="add_confirm_password" class="form-control" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button type="submit" class="btn-modal btn-modal-primary" onclick="validateAddUserForm()">Add User</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal-overlay">
    <div class="modal-container">
        <form method="POST" action="" id="editForm">
            <div class="modal-header-settings">
                <h3><i class="fas fa-edit"></i> Edit User</h3>
                <span class="modal-close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <div>
                <input type="hidden" name="update_user" value="1">
                <input type="hidden" name="user_id" id="edit_user_id">
                <div class="form-group">
                    <label><i class="fas fa-user"></i> First Name</label>
                    <input type="text" name="firstname" id="edit_firstname" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-user"></i> Last Name</label>
                    <input type="text" name="lastname" id="edit_lastname" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-envelope"></i> Email Address</label>
                    <input type="email" name="email" id="edit_email" class="form-control" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-tag"></i> Role</label>
                    <select name="role" id="edit_role" class="form-control">
                        <option value="user">User</option>
                        <option value="admin">Admin</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-circle"></i> Status</label>
                    <select name="status" id="edit_status" class="form-control">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('editModal')">Cancel</button>
                <button type="submit" class="btn-modal btn-modal-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Modal -->
<div id="deleteModal" class="delete-modal-overlay">
    <div class="delete-modal-container">
        <form method="POST" action="" id="deleteForm">
            <div class="delete-modal-header">
                <h3><i class="fas fa-trash-alt"></i> Delete User</h3>
            </div>
            <div class="delete-modal-body">
                <input type="hidden" name="delete_user" value="1">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="delete-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p><strong>Are you absolutely sure?</strong></p>
                    <p class="warning-text">This action cannot be undone.</p>
                </div>
                <div class="delete-item-details">
                    <div class="detail-label">USER TO DELETE</div>
                    <div class="detail-name" id="delete_username">-</div>
                    <div class="detail-extra" id="delete_email">-</div>
                </div>
            </div>
            <div class="delete-modal-footer">
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                <button type="submit" class="btn-modal btn-modal-danger"><i class="fas fa-trash-alt"></i> Delete User</button>
            </div>
        </form>
    </div>
</div>

<!-- Unlock User Modal -->
<div id="unlockModal" class="delete-modal-overlay">
    <div class="delete-modal-container">
        <form method="POST" action="" id="unlockForm">
            <div class="warning-modal-header">
                <h3><i class="fas fa-unlock-alt"></i> Unlock Account</h3>
            </div>
            <div class="delete-modal-body">
                <input type="hidden" name="unlock_user" value="1">
                <input type="hidden" name="user_id" id="unlock_user_id">
                <div class="delete-warning">
                    <i class="fas fa-lock"></i>
                    <p><strong>Unlock User Account?</strong></p>
                    <p class="warning-text">This will reset login attempts and remove the lock.</p>
                </div>
                <div class="delete-item-details">
                    <div class="detail-label">USER TO UNLOCK</div>
                    <div class="detail-name" id="unlock_username">-</div>
                    <div class="detail-extra" id="unlock_email">-</div>
                    <div class="detail-extra" id="unlock_locked_until" style="margin-top: 8px; color: var(--warning);"></div>
                </div>
            </div>
            <div class="delete-modal-footer">
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('unlockModal')">Cancel</button>
                <button type="submit" class="btn-modal btn-modal-warning"><i class="fas fa-unlock-alt"></i> Unlock Account</button>
            </div>
        </form>
    </div>
</div>

<!-- Approve User Modal -->
<div id="approveModal" class="delete-modal-overlay">
    <div class="delete-modal-container">
        <form method="POST" action="" id="approveForm">
            <div class="success-modal-header">
                <h3><i class="fas fa-check-circle"></i> Approve User</h3>
            </div>
            <div class="delete-modal-body">
                <input type="hidden" name="action" value="approve">
                <input type="hidden" name="user_id" id="approve_user_id">
                <div class="delete-warning">
                    <i class="fas fa-user-check"></i>
                    <p><strong>Approve Registration?</strong></p>
                    <p class="warning-text">This user will be able to login immediately.<br>An approval email will be sent.</p>
                </div>
                <div class="delete-item-details">
                    <div class="detail-label">USER TO APPROVE</div>
                    <div class="detail-name" id="approve_username">-</div>
                    <div class="detail-extra" id="approve_email">-</div>
                </div>
            </div>
            <div class="delete-modal-footer">
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('approveModal')">Cancel</button>
                <button type="submit" class="btn-modal btn-modal-success"><i class="fas fa-check-circle"></i> Approve User</button>
            </div>
        </form>
    </div>
</div>

<!-- Reject User Modal -->
<div id="rejectModal" class="delete-modal-overlay">
    <div class="delete-modal-container">
        <form method="POST" action="" id="rejectForm">
            <div class="delete-modal-header">
                <h3><i class="fas fa-times-circle"></i> Reject User</h3>
            </div>
            <div class="delete-modal-body">
                <input type="hidden" name="action" value="reject">
                <input type="hidden" name="user_id" id="reject_user_id">
                <div class="delete-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <p><strong>Reject Registration?</strong></p>
                    <p class="warning-text">This action cannot be undone. The user will be removed.<br>A rejection email will be sent.</p>
                </div>
                <div class="delete-item-details">
                    <div class="detail-label">USER TO REJECT</div>
                    <div class="detail-name" id="reject_username">-</div>
                    <div class="detail-extra" id="reject_email">-</div>
                </div>
            </div>
            <div class="delete-modal-footer">
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('rejectModal')">Cancel</button>
                <button type="submit" class="btn-modal btn-modal-danger"><i class="fas fa-times-circle"></i> Reject User</button>
            </div>
        </form>
    </div>
</div>

<!-- Display Success/Error Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <span><?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></span>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <span><?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></span>
    </div>
<?php endif; ?>

<!-- PENDING REGISTRATIONS SECTION -->
<div class="table-container pending-container">
    <div class="table-header">
        <h2><i class="fas fa-clock"></i> Pending Registrations <span class="count-badge"><?php echo $pending_users ? $pending_users->num_rows : 0; ?></span></h2>
        <p><i class="fas fa-info-circle"></i> Users waiting for admin approval</p>
    </div>
    
    <?php if ($pending_users && $pending_users->num_rows > 0): ?>
        <div style="overflow-x: auto;">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Registered Date</th>
                        <th style="min-width: 280px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($pending = $pending_users->fetch_assoc()): ?>
                    <tr class="pending-row">
                        <td><?php echo $pending['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($pending['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($pending['firstname'] . ' ' . $pending['lastname']); ?></td>
                        <td><?php echo htmlspecialchars($pending['email']); ?></td>
                        <td><?php echo date('M d, Y h:i A', strtotime($pending['created_at'])); ?><br><small class="text-muted"><?php echo time_elapsed_string($pending['created_at']); ?></small></td>
                        <td class="action-buttons">
                            <button onclick="openApproveModal(<?php echo $pending['id']; ?>, '<?php echo htmlspecialchars(addslashes($pending['firstname'] . ' ' . $pending['lastname'])); ?>', '<?php echo htmlspecialchars($pending['email']); ?>')" class="action-btn approve"><i class="fas fa-check-circle"></i> Approve</button>
                            <button onclick="openRejectModal(<?php echo $pending['id']; ?>, '<?php echo htmlspecialchars(addslashes($pending['firstname'] . ' ' . $pending['lastname'])); ?>', '<?php echo htmlspecialchars($pending['email']); ?>')" class="action-btn reject"><i class="fas fa-times-circle"></i> Reject</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: var(--text-muted);">
            <i class="fas fa-inbox" style="font-size: 48px; margin-bottom: 15px; display: block;"></i>
            <p>No pending registrations</p>
        </div>
    <?php endif; ?>
</div>

<!-- ACTIVE USERS SECTION -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-users"></i> System Users</h2>
        <div>
            <form method="GET" action="" style="display: flex; gap: 10px;">
                <div class="search-box">
                    <input type="text" name="search" placeholder="Search by username, name or email..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit"><i class="fas fa-search"></i> Search</button>
                    <?php if ($search): ?>
                        <a href="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="btn" style="background: var(--light); color: var(--text-primary);"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
    
    <div style="margin-bottom: 20px;">
        <form method="GET" action="" id="showInactiveForm">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="show_inactive" value="1" onchange="document.getElementById('showInactiveForm').submit()" <?php echo $show_inactive ? 'checked' : ''; ?>>
                <span>Show Inactive Users</span>
            </label>
            <?php if ($search): ?>
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            <?php endif; ?>
        </form>
    </div>
    
    <button class="btn btn-primary" onclick="openAddUserModal()" style="margin-bottom: 20px;">
        <i class="fas fa-plus"></i> Add New User
    </button>
    
    <div style="overflow-x: auto;">
        <table class="table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Username</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="min-width: 300px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($users && $users->num_rows > 0): ?>
                    <?php while($user = $users->fetch_assoc()): 
                        $is_locked = !empty($user['locked_until']) && strtotime($user['locked_until']) > time();
                        $row_class = $is_locked ? 'locked-row' : '';
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td><?php echo $user['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                        <td><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <?php
                            $role_badges = array('super_admin' => 'badge-danger', 'admin' => 'badge-warning', 'user' => 'badge-success');
                            $badge_class = $role_badges[$user['role']] ?? 'badge-info';
                            ?>
                            <span class="badge <?php echo $badge_class; ?>"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
                          </td>
                        <td>
                            <?php if ($user['status'] == 'active'): ?>
                                <span class="badge badge-success">Active</span>
                            <?php elseif ($user['status'] == 'pending'): ?>
                                <span class="badge badge-warning">Pending</span>
                            <?php else: ?>
                                <span class="badge badge-danger">Inactive</span>
                            <?php endif; ?>
                            <?php if ($is_locked): ?>
                                <span class="badge badge-locked">Locked</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td class="action-buttons">
                            <button onclick="viewUser(<?php echo $user['id']; ?>)" class="action-btn view" title="View User"><i class="fas fa-eye"></i> View</button>
                            <button onclick="editUser(<?php echo $user['id']; ?>)" class="action-btn edit" title="Edit User"><i class="fas fa-edit"></i> Edit</button>
                            <?php if ($is_locked): ?>
                                <button onclick="unlockUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>', '<?php echo htmlspecialchars($user['locked_until']); ?>')" class="action-btn unlock" title="Unlock Account"><i class="fas fa-unlock-alt"></i> Unlock</button>
                            <?php endif; ?>
                            <?php if ($user['id'] != $_SESSION['user_id'] && ($user['role'] != 'super_admin' || $_SESSION['role'] == 'super_admin')): ?>
                                <button onclick="deleteUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>')" class="action-btn delete" title="Delete User"><i class="fas fa-trash"></i> Delete</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="text-center">No users found</span></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function openAddUserModal() {
    document.getElementById('addUserForm').reset();
    document.getElementById('addUserModal').style.display = 'block';
}

function validateAddUserForm() {
    var password = document.getElementById('add_password').value;
    var confirm = document.getElementById('add_confirm_password').value;
    
    if (password !== confirm) {
        alert('Passwords do not match!');
        return false;
    }
    
    if (password.length < 6) {
        alert('Password must be at least 6 characters!');
        return false;
    }
    
    document.getElementById('addUserForm').submit();
}

function viewUser(userId) {
    fetch('?ajax=get_user&user_id=' + userId)
        .then(response => response.json())
        .then(data => {
            var fullName = (data.firstname ? data.firstname : '') + ' ' + (data.lastname ? data.lastname : '');
            document.getElementById('view_fullname').innerHTML = fullName.trim() || data.username;
            document.getElementById('view_username_display').innerHTML = '@' + data.username;
            document.getElementById('view_firstname').innerHTML = data.firstname || '-';
            document.getElementById('view_lastname').innerHTML = data.lastname || '-';
            document.getElementById('view_email').innerHTML = data.email;
            document.getElementById('view_created').innerHTML = new Date(data.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' });
            
            var roleBadge = '';
            if (data.role === 'super_admin') roleBadge = '<span class="badge badge-danger">Super Admin</span>';
            else if (data.role === 'admin') roleBadge = '<span class="badge badge-warning">Admin</span>';
            else roleBadge = '<span class="badge badge-info">User</span>';
            document.getElementById('view_role').innerHTML = roleBadge;
            
            var avatarImg = document.getElementById('view_avatar_img');
            var avatarPlaceholder = document.getElementById('view_avatar_placeholder');
            var statusIcon = document.getElementById('view_status_icon');
            
            if (data.status === 'active') {
                statusIcon.style.color = '#4CAF50';
                statusIcon.title = 'Active';
            } else if (data.status === 'pending') {
                statusIcon.style.color = '#FF9800';
                statusIcon.title = 'Pending';
            } else {
                statusIcon.style.color = '#f44336';
                statusIcon.title = 'Inactive';
            }
            
            if (data.avatar && data.avatar !== '' && data.avatar !== null) {
                avatarImg.src = '<?php echo SITE_URL; ?>/uploads/avatars/' + data.avatar;
                avatarImg.style.display = 'block';
                avatarPlaceholder.style.display = 'none';
                avatarImg.onerror = function() {
                    avatarImg.style.display = 'none';
                    avatarPlaceholder.style.display = 'flex';
                    var avatarText = avatarPlaceholder.querySelector('.avatar-text');
                    var avatarIcon = avatarPlaceholder.querySelector('i');
                    if (data.firstname && data.firstname.length > 0) {
                        avatarText.style.display = 'block';
                        avatarText.innerHTML = data.firstname.charAt(0).toUpperCase();
                        avatarIcon.style.display = 'none';
                    } else {
                        avatarText.style.display = 'none';
                        avatarIcon.style.display = 'block';
                    }
                };
            } else {
                avatarImg.style.display = 'none';
                avatarPlaceholder.style.display = 'flex';
                var avatarText = avatarPlaceholder.querySelector('.avatar-text');
                var avatarIcon = avatarPlaceholder.querySelector('i');
                if (data.firstname && data.firstname.length > 0) {
                    avatarText.style.display = 'block';
                    avatarText.innerHTML = data.firstname.charAt(0).toUpperCase();
                    avatarIcon.style.display = 'none';
                } else if (data.username && data.username.length > 0) {
                    avatarText.style.display = 'block';
                    avatarText.innerHTML = data.username.charAt(0).toUpperCase();
                    avatarIcon.style.display = 'none';
                } else {
                    avatarText.style.display = 'none';
                    avatarIcon.style.display = 'block';
                }
            }
            document.getElementById('viewModal').style.display = 'block';
        });
}

function editUser(userId) {
    fetch('?ajax=get_user&user_id=' + userId)
        .then(response => response.json())
        .then(data => {
            document.getElementById('edit_user_id').value = data.id;
            document.getElementById('edit_firstname').value = data.firstname;
            document.getElementById('edit_lastname').value = data.lastname;
            document.getElementById('edit_email').value = data.email;
            document.getElementById('edit_role').value = data.role;
            document.getElementById('edit_status').value = data.status;
            document.getElementById('editModal').style.display = 'block';
        });
}

function deleteUserModal(userId, username, email) {
    document.getElementById('delete_user_id').value = userId;
    document.getElementById('delete_username').innerText = username;
    document.getElementById('delete_email').innerText = email;
    document.getElementById('deleteModal').style.display = 'flex';
}

function unlockUser(userId, username, email, lockedUntil) {
    document.getElementById('unlock_user_id').value = userId;
    document.getElementById('unlock_username').innerText = username;
    document.getElementById('unlock_email').innerText = email;
    var lockedDate = new Date(lockedUntil);
    document.getElementById('unlock_locked_until').innerHTML = '<i class="fas fa-clock"></i> Locked until: ' + lockedDate.toLocaleString();
    document.getElementById('unlockModal').style.display = 'flex';
}

function openApproveModal(userId, fullname, email) {
    document.getElementById('approve_user_id').value = userId;
    document.getElementById('approve_username').innerText = fullname;
    document.getElementById('approve_email').innerText = email;
    document.getElementById('approveModal').style.display = 'flex';
}

function openRejectModal(userId, fullname, email) {
    document.getElementById('reject_user_id').value = userId;
    document.getElementById('reject_username').innerText = fullname;
    document.getElementById('reject_email').innerText = email;
    document.getElementById('rejectModal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal-overlay')) {
        event.target.style.display = 'none';
    }
    if (event.target.classList.contains('delete-modal-overlay')) {
        event.target.style.display = 'none';
    }
}

setTimeout(function() {
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.opacity = '0';
            setTimeout(function() {
                if (alert.style.display !== 'none') {
                    alert.style.display = 'none';
                }
            }, 300);
        }, 4700);
    });
}, 1000);
</script>

<?php 
function time_elapsed_string($datetime, $full = false) {
    if (empty($datetime)) return 'just now';
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    $diff_array = array(
        'y' => $diff->y,
        'm' => $diff->m,
        'd' => $diff->d,
        'h' => $diff->h,
        'i' => $diff->i,
        's' => $diff->s
    );
    
    $diff_array['w'] = floor($diff_array['d'] / 7);
    $diff_array['d'] -= $diff_array['w'] * 7;
    
    $string = array('y' => 'year', 'm' => 'month', 'w' => 'week', 'd' => 'day', 'h' => 'hour', 'i' => 'minute', 's' => 'second');
    $has_value = false;
    foreach ($string as $k => &$v) {
        if ($diff_array[$k]) {
            $v = $diff_array[$k] . ' ' . $v . ($diff_array[$k] > 1 ? 's' : '');
            $has_value = true;
        } else {
            unset($string[$k]);
        }
    }
    if (!$has_value) return 'just now';
    if (!$full) $string = array_slice($string, 0, 1);
    return implode(', ', $string) . ' ago';
}

include INCLUDE_PATH . '/footer.php'; 
?>