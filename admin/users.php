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

// Handle unlock account action
if (isset($_POST['unlock_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    // Get user data before unlocking for audit
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
            
            // Log to audit trail
            if (function_exists('logAuditEvent')) {
                logAuditEvent($conn, $_SESSION['user_id'], 'MANUAL_UNLOCK', 'SECURITY', "Manually unlocked account for user: " . $user_data['username']);
            }
            
            if (function_exists('logActivity')) {
                logActivity('Account Unlocked', $user_id, "Account for " . $user_data['username'] . " was manually unlocked by " . ($_SESSION['username'] ?? 'Admin'));
            }
        } else {
            $_SESSION['error'] = "Failed to unlock account.";
        }
        $stmt->close();
    } elseif ($user_data && (!empty($user_data['locked_until']) && strtotime($user_data['locked_until']) <= time())) {
        // Lock has already expired, just clear it
        $conn->query("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = $user_id");
        $_SESSION['success'] = "Account for " . htmlspecialchars($user_data['username']) . " was already expired. Lock has been cleared.";
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
        // FIRST: Get user details before updating
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
        
        // SECOND: Update user status to active
        $stmt = $conn->prepare("UPDATE users SET status = 'active' WHERE id = ? AND status = 'pending'");
        $stmt->bind_param("i", $user_id);
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            // THIRD: Send approval email
            $email_sent = sendApprovalEmail($user);
            
            if ($email_sent) {
                $_SESSION['success'] = "User approved successfully. Approval email sent to " . $user['email'];
            } else {
                $_SESSION['success'] = "User approved successfully, but email notification failed to send. Please check SMTP settings.";
                error_log("Failed to send approval email to: " . $user['email'] . " for user: " . $user['username']);
            }
            
            if (function_exists('logActivity')) {
                logActivity('User Approved', $user_id, "User " . $user['username'] . " was approved by " . ($_SESSION['username'] ?? 'Admin'));
            }
            
            // Log to audit trail
            if (function_exists('logUserApproval')) {
                logUserApproval($_SESSION['user_id'], $user_id, 'approved', $user);
            }
        } else {
            $_SESSION['error'] = "Failed to approve user.";
        }
        $stmt->close();
    } 
    elseif ($action === 'reject') {
        // FIRST: Get user details before deletion
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
        
        // SECOND: Send rejection email
        $email_sent = sendRejectionEmail($user);
        
        // THIRD: Delete the user
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

// Handle update user
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
        // Log to audit trail
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

// Handle delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $user_id = (int)$_POST['user_id'];
    
    // Get user data before deletion for audit
    $user_query = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $user_data = $user_query->get_result()->fetch_assoc();
    $user_query->close();
    
    // Check if user is trying to delete themselves
    if ($user_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot delete your own account.";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        if ($stmt->execute()) {
            // Log to audit trail
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

// Build query for active users
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

// Get pending users
$pending_query = "SELECT * FROM users WHERE status = 'pending' ORDER BY created_at DESC";
$pending_users = $conn->query($pending_query);

include INCLUDE_PATH . '/header.php';
?>

<style>
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

/* Modal Styles */
.modal {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.6);
    backdrop-filter: blur(8px);
    animation: modalOverlayFade 0.3s ease;
}

@keyframes modalOverlayFade {
    from { opacity: 0; backdrop-filter: blur(0px); }
    to { opacity: 1; backdrop-filter: blur(8px); }
}

.modal-content {
    background: var(--white);
    margin: 3% auto;
    width: 600px;
    max-width: 90%;
    border-radius: 24px;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    animation: modalSlideUp 0.4s cubic-bezier(0.34, 1.2, 0.64, 1);
    overflow: hidden;
}

@keyframes modalSlideUp {
    from { transform: translateY(60px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    padding: 30px 28px 20px 28px;
    position: relative;
    text-align: center;
}

.modal-header h3 {
    color: var(--white);
    margin: 0;
    font-size: 20px;
    font-weight: 600;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
}

.modal-header h3 i {
    font-size: 20px;
    background: rgba(255, 255, 255, 0.2);
    padding: 8px;
    border-radius: 50%;
}

.modal-close {
    position: absolute;
    right: 20px;
    top: 20px;
    color: var(--white);
    font-size: 24px;
    font-weight: normal;
    cursor: pointer;
    transition: all 0.2s ease;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.1);
}

.modal-close:hover {
    background: rgba(255, 255, 255, 0.2);
    transform: rotate(90deg);
}

.modal-avatar-wrapper {
    display: flex;
    justify-content: center;
    margin-top: 10px;
    margin-bottom: 5px;
}

.avatar-large {
    width: 120px;
    height: 120px;
    background: linear-gradient(135deg, var(--white) 0%, var(--accent-light) 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    border: 4px solid var(--white);
    position: relative;
    overflow: hidden;
}

.avatar-large img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.avatar-large .avatar-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
}

.avatar-placeholder i {
    font-size: 55px;
    color: var(--white);
}

.avatar-placeholder .avatar-text {
    font-size: 48px;
    font-weight: 700;
    color: var(--white);
    text-transform: uppercase;
}

.avatar-status {
    position: absolute;
    bottom: 8px;
    right: 8px;
    width: 28px;
    height: 28px;
    background: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.avatar-status i {
    font-size: 14px !important;
}

.status-active { color: var(--success); }
.status-inactive { color: var(--danger); }
.status-pending { color: var(--warning); }
.status-locked { color: #f44336; }

.modal-user-name {
    text-align: center;
    margin-top: 15px;
    margin-bottom: 5px;
}

.modal-user-name h2 {
    color: var(--white);
    font-size: 22px;
    font-weight: 600;
    margin: 0;
}

.modal-user-name p {
    color: rgba(255, 255, 255, 0.9);
    font-size: 13px;
    margin: 5px 0 0;
}

.modal-body {
    padding: 28px;
    max-height: 60vh;
    overflow-y: auto;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-bottom: 0;
}

.info-card {
    background: var(--light);
    border-radius: 14px;
    padding: 14px 16px;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.info-card:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
}

.info-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    gap: 6px;
}

.info-label i {
    font-size: 12px;
    color: var(--primary);
}

.info-value {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
    word-break: break-word;
}

.info-value .badge {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.info-card-full {
    grid-column: span 2;
    background: var(--light);
    border-radius: 14px;
    padding: 14px 16px;
}

.form-group {
    margin-bottom: 20px;
}

.form-group label {
    display: block;
    margin-bottom: 8px;
    color: var(--text-secondary);
    font-weight: 500;
    font-size: 13px;
}

.form-group label i {
    color: var(--primary);
    margin-right: 6px;
    width: 16px;
}

.form-control {
    width: 100%;
    padding: 12px 16px;
    border: 2px solid var(--border-light);
    border-radius: 12px;
    font-size: 14px;
    transition: all 0.2s ease;
    background: var(--white);
}

.form-control:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 4px rgba(107, 140, 255, 0.1);
}

select.form-control {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236B6B6B'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'%3E%3C/path%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 20px;
}

.modal-footer {
    padding: 20px 28px;
    border-top: 1px solid var(--border-light);
    background: var(--light);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

.btn-modal {
    padding: 10px 24px;
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-modal-primary {
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    color: var(--white);
}

.btn-modal-primary:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(107, 140, 255, 0.4);
}

.btn-modal-secondary {
    background: var(--white);
    color: var(--text-primary);
    border: 1px solid var(--border-light);
}

.btn-modal-secondary:hover {
    background: var(--light);
    border-color: var(--primary);
}

.btn-modal-danger {
    background: linear-gradient(135deg, var(--danger) 0%, #d32f2f 100%);
    color: var(--white);
}

.btn-modal-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(244, 67, 54, 0.4);
}

.btn-modal-warning {
    background: linear-gradient(135deg, var(--warning) 0%, #f57c00 100%);
    color: var(--white);
}

.btn-modal-warning:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 14px rgba(255, 152, 0, 0.4);
}

.delete-warning {
    text-align: center;
    margin-bottom: 20px;
}

.delete-icon {
    width: 64px;
    height: 64px;
    background: rgba(244, 67, 54, 0.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 16px;
}

.delete-icon i {
    font-size: 32px;
    color: var(--danger);
}

.badge-success {
    background: #E8F5E9;
    color: #2E7D32;
}

.badge-danger {
    background: #FFEBEE;
    color: #C62828;
}

.badge-warning {
    background: #FFF3E0;
    color: #E65100;
}

.badge-info {
    background: #E3F2FD;
    color: #1976D2;
}

.badge-locked {
    background: #FFEBEE;
    color: #f44336;
}

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
    border-left: 3px solid #f44336;
}

.locked-row:hover {
    background-color: #FFCDD2 !important;
}

.table-container {
    background: var(--white);
    border-radius: 12px;
    padding: 20px;
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
    font-size: 18px;
    margin: 0;
}

.table-header h2 i {
    color: var(--accent);
    margin-right: 10px;
}

.search-box {
    display: flex;
    gap: 10px;
}

.search-box input[type="text"] {
    padding: 12px 15px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 14px;
    width: 250px;
    transition: border-color 0.2s, box-shadow 0.2s;
}

.search-box input[type="text"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.search-box button {
    padding: 12px 24px;
    background: var(--primary);
    color: var(--text-light);
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.search-box button:hover {
    background: var(--secondary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.3);
}

table {
    width: 100%;
    border-collapse: collapse;
}

thead tr {
    background: linear-gradient(to right, var(--light), var(--white));
    border-bottom: 2px solid var(--accent-light);
}

th {
    padding: 15px 10px;
    text-align: left;
    color: var(--primary);
    font-weight: 600;
    font-size: 13px;
    white-space: nowrap;
}

td {
    padding: 15px 10px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
    font-size: 13px;
}

tr:hover {
    background-color: var(--light);
}

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
    padding: 6px 12px;
    border-radius: 8px;
    border: none;
    color: var(--white);
    text-decoration: none;
    transition: all 0.3s;
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

.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
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

@media (max-width: 768px) {
    .table-container { padding: 15px; overflow-x: auto; }
    .action-buttons { flex-direction: column; width: 100%; }
    .action-btn { width: 100%; justify-content: center; }
    .table-header { flex-direction: column; align-items: flex-start; }
    .search-box { flex-direction: column; width: 100%; }
    .search-box input[type="text"] { width: 100%; }
    .search-box button { width: 100%; }
    .modal-content { width: 95%; margin: 5% auto; }
    .info-grid { grid-template-columns: 1fr; gap: 12px; }
    .info-card-full { grid-column: span 1; }
}
</style>

<!-- View User Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span class="modal-close" onclick="closeModal('viewModal')">&times;</span>
            <div class="modal-avatar-wrapper">
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
            </div>
            <div class="modal-user-name">
                <h2 id="view_fullname">-</h2>
                <p id="view_username_display">@username</p>
            </div>
        </div>
        <div class="modal-body">
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
                <div class="info-card-full">
                    <div class="info-label"><i class="fas fa-calendar-alt"></i> Member Since</div>
                    <div class="info-value" id="view_created">-</div>
                </div>
            </div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('viewModal')">
                <i class="fas fa-times"></i> Close
            </button>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div id="editModal" class="modal">
    <div class="modal-content">
        <form method="POST" action="" id="editForm">
            <div class="modal-header">
                <h3><i class="fas fa-edit"></i> Edit User</h3>
                <span class="modal-close" onclick="closeModal('editModal')">&times;</span>
            </div>
            <div class="modal-body">
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
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('editModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-modal btn-modal-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Delete User Modal -->
<div id="deleteModal" class="modal">
    <div class="modal-content">
        <form method="POST" action="" id="deleteForm">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--danger) 0%, #d32f2f 100%);">
                <h3><i class="fas fa-trash-alt"></i> Delete User</h3>
                <span class="modal-close" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" name="delete_user" value="1">
                <input type="hidden" name="user_id" id="delete_user_id">
                <div class="delete-warning">
                    <div class="delete-icon">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <p style="font-size: 16px; font-weight: 500; margin-bottom: 8px;">Are you absolutely sure?</p>
                    <p style="color: var(--text-muted); font-size: 13px;">This action cannot be undone.</p>
                </div>
                <div class="info-card" style="margin-top: 16px;">
                    <div class="info-label"><i class="fas fa-user"></i> User to delete</div>
                    <div class="info-value">
                        <strong id="delete_username">-</strong><br>
                        <small id="delete_email" style="color: var(--text-muted);">-</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('deleteModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-modal btn-modal-danger">
                    <i class="fas fa-trash"></i> Delete User
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Unlock User Modal -->
<div id="unlockModal" class="modal">
    <div class="modal-content">
        <form method="POST" action="" id="unlockForm">
            <div class="modal-header" style="background: linear-gradient(135deg, var(--warning) 0%, #f57c00 100%);">
                <h3><i class="fas fa-unlock-alt"></i> Unlock Account</h3>
                <span class="modal-close" onclick="closeModal('unlockModal')">&times;</span>
            </div>
            <div class="modal-body">
                <input type="hidden" name="unlock_user" value="1">
                <input type="hidden" name="user_id" id="unlock_user_id">
                <div class="delete-warning">
                    <div class="delete-icon" style="background: rgba(255, 152, 0, 0.1);">
                        <i class="fas fa-lock" style="color: var(--warning);"></i>
                    </div>
                    <p style="font-size: 16px; font-weight: 500; margin-bottom: 8px;">Unlock User Account</p>
                    <p style="color: var(--text-muted); font-size: 13px;">This will reset login attempts and remove the lock.</p>
                </div>
                <div class="info-card" style="margin-top: 16px;">
                    <div class="info-label"><i class="fas fa-user"></i> User to unlock</div>
                    <div class="info-value">
                        <strong id="unlock_username">-</strong><br>
                        <small id="unlock_email" style="color: var(--text-muted);">-</small>
                        <div id="unlock_locked_until" style="margin-top: 8px; font-size: 12px; color: var(--warning);"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-modal btn-modal-secondary" onclick="closeModal('unlockModal')">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-modal btn-modal-warning">
                    <i class="fas fa-unlock-alt"></i> Unlock Account
                </button>
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
                            <form method="POST" action="" style="display: inline-block;" onsubmit="return confirmApprove('<?php echo addslashes($pending['firstname']); ?>')">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="user_id" value="<?php echo $pending['id']; ?>">
                                <button type="submit" class="action-btn approve"><i class="fas fa-check-circle"></i> Approve</button>
                            </form>
                            <form method="POST" action="" style="display: inline-block;" onsubmit="return confirmReject('<?php echo addslashes($pending['firstname']); ?>')">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="user_id" value="<?php echo $pending['id']; ?>">
                                <button type="submit" class="action-btn reject"><i class="fas fa-times-circle"></i> Reject</button>
                            </form>
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
    
    <button class="btn btn-primary" onclick="addUser()" style="margin-bottom: 20px;">
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
                    <tr><td colspan="8" class="text-center">No users found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function addUser() {
    window.location.href = 'add_user.php';
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
    document.getElementById('deleteModal').style.display = 'block';
}

function unlockUser(userId, username, email, lockedUntil) {
    document.getElementById('unlock_user_id').value = userId;
    document.getElementById('unlock_username').innerText = username;
    document.getElementById('unlock_email').innerText = email;
    var lockedDate = new Date(lockedUntil);
    document.getElementById('unlock_locked_until').innerHTML = '<i class="fas fa-clock"></i> Locked until: ' + lockedDate.toLocaleString();
    document.getElementById('unlockModal').style.display = 'block';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function confirmApprove(firstname) {
    return confirm('Approve ' + firstname + '\'s registration? They will be able to login immediately. An approval email will be sent to the user.');
}

function confirmReject(firstname) {
    return confirm('Reject ' + firstname + '\'s registration? This action cannot be undone. The user will be removed from the system. A rejection email will be sent to the user.');
}

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
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