<?php
/**
 * Manage Users Page (Super Admin)
 * CRUD operations for all users with pending registrations
 */

$page_title = 'Manage Users';
$page_description = 'Create, edit, and manage system users';

require_once '../includes/auth.php';
requireRole('super_admin');

// Helper function for time elapsed
function time_elapsed_string($datetime, $full = false) {
    if (empty($datetime)) return 'just now';
    
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    $weeks = floor($diff->d / 7);
    $days = $diff->d - ($weeks * 7);
    
    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    
    $has_value = false;
    foreach ($string as $k => &$v) {
        $value = $k === 'w' ? $weeks : ($k === 'd' ? $days : $diff->$k);
        if ($value) {
            $v = $value . ' ' . $v . ($value > 1 ? 's' : '');
            $has_value = true;
        } else {
            unset($string[$k]);
        }
    }
    
    if (!$has_value) {
        return 'just now';
    }
    
    if (!$full) {
        $string = array_slice($string, 0, 1);
    }
    return implode(', ', $string) . ' ago';
}

// Helper function to escape HTML
function escapeHtml($str) {
    if (!$str) return '';
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// ============================================
// HANDLE APPROVE/REJECT ACTIONS
// ============================================

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
            if (function_exists('sendApprovalEmail')) {
                $email_sent = sendApprovalEmail($user);
                if ($email_sent) {
                    $_SESSION['success'] = "User approved successfully. Approval email sent to {$user['email']}.";
                } else {
                    $_SESSION['success'] = "User approved successfully, but email notification failed to send.";
                }
            } else {
                $_SESSION['success'] = "User approved successfully.";
            }
            
            if (function_exists('logActivity')) {
                logActivity('User Approved', $user_id, "User {$user['username']} was approved by " . ($_SESSION['username'] ?? 'Admin'));
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
        
        if (function_exists('sendRejectionEmail')) {
            $email_sent = sendRejectionEmail($user);
        } else {
            $email_sent = false;
        }
        
        $delete_stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND status = 'pending'");
        $delete_stmt->bind_param("i", $user_id);
        
        if ($delete_stmt->execute() && $delete_stmt->affected_rows > 0) {
            if ($email_sent) {
                $_SESSION['success'] = "User rejected and removed. Rejection email sent to {$user['email']}.";
            } else {
                $_SESSION['success'] = "User rejected and removed, but email notification failed to send.";
            }
            
            if (function_exists('logActivity')) {
                logActivity('User Rejected', $user_id, "User {$user['username']} was rejected by " . ($_SESSION['username'] ?? 'Admin'));
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

// ============================================
// HANDLE AJAX REQUESTS
// ============================================

// Get user data for edit/view
if (isset($_GET['ajax']) && $_GET['ajax'] === 'get_user' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $user_id = (int)$_GET['id'];
    
    $query = "SELECT id, firstname, lastname, username, email, role, status, avatar, created_at FROM users WHERE id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    
    if ($user) {
        echo json_encode($user);
    } else {
        echo json_encode(['error' => 'User not found']);
    }
    exit;
}

// Save user (add/edit)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['ajax']) && $_GET['ajax'] === 'save_user') {
    header('Content-Type: application/json');
    
    $data = json_decode(file_get_contents('php://input'), true);
    if (!$data && !empty($_POST)) {
        $data = $_POST;
    }
    
    if (!$data) {
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    $user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
    $firstname = trim($data['firstname'] ?? '');
    $lastname = trim($data['lastname'] ?? '');
    $username = trim($data['username'] ?? '');
    $email = trim($data['email'] ?? '');
    $role = $data['role'] ?? 'user';
    $status = $data['status'] ?? 'active';
    $password = $data['password'] ?? '';
    
    // Validation
    if (empty($firstname) || empty($lastname) || empty($username) || empty($email)) {
        echo json_encode(['success' => false, 'message' => 'All fields are required']);
        exit;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // Check username
    $check = $conn->prepare("SELECT id FROM users WHERE username = ?" . ($user_id ? " AND id != ?" : ""));
    if ($user_id) {
        $check->bind_param("si", $username, $user_id);
    } else {
        $check->bind_param("s", $username);
    }
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        exit;
    }
    
    // Check email
    $check = $conn->prepare("SELECT id FROM users WHERE email = ?" . ($user_id ? " AND id != ?" : ""));
    if ($user_id) {
        $check->bind_param("si", $email, $user_id);
    } else {
        $check->bind_param("s", $email);
    }
    $check->execute();
    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already exists']);
        exit;
    }
    
    if ($user_id == 0 && empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Password is required for new users']);
        exit;
    }
    
    if (!empty($password) && strlen($password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    
    // Save
    if ($user_id > 0) {
        if (!empty($password)) {
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET firstname=?, lastname=?, username=?, email=?, role=?, status=?, password=? WHERE id=?");
            $stmt->bind_param("sssssssi", $firstname, $lastname, $username, $email, $role, $status, $hashed, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET firstname=?, lastname=?, username=?, email=?, role=?, status=? WHERE id=?");
            $stmt->bind_param("ssssssi", $firstname, $lastname, $username, $email, $role, $status, $user_id);
        }
        $msg = 'User updated successfully';
    } else {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, username, email, role, status, password, created_at) VALUES (?,?,?,?,?,?,?, NOW())");
        $stmt->bind_param("sssssss", $firstname, $lastname, $username, $email, $role, $status, $hashed);
        $msg = 'User created successfully';
    }
    
    if ($stmt->execute()) {
        if (function_exists('logUserUpdate') && $user_id > 0) {
            logUserUpdate($_SESSION['user_id'], $user_id, null, ['firstname' => $firstname, 'lastname' => $lastname, 'email' => $email, 'role' => $role, 'status' => $status]);
        } elseif (function_exists('logUserRegistration') && $user_id == 0) {
            logUserRegistration($conn->insert_id, ['username' => $username, 'email' => $email, 'firstname' => $firstname, 'lastname' => $lastname]);
        }
        echo json_encode(['success' => true, 'message' => $msg]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
    }
    exit;
}

// Delete user
if (isset($_GET['ajax']) && $_GET['ajax'] === 'delete_user' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $user_id = (int)$_GET['id'];
    
    if ($user_id == $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'You cannot delete your own account']);
        exit;
    }
    
    $user_query = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $user_query->bind_param("i", $user_id);
    $user_query->execute();
    $user_data = $user_query->get_result()->fetch_assoc();
    $user_query->close();
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    
    if ($stmt->execute()) {
        if (function_exists('logUserDeletion')) {
            logUserDeletion($_SESSION['user_id'], $user_id, $user_data);
        }
        echo json_encode(['success' => true, 'message' => 'User deleted successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to delete user']);
    }
    exit;
}

// Toggle user status
if (isset($_GET['ajax']) && $_GET['ajax'] === 'toggle_status' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $user_id = (int)$_GET['id'];
    
    $user = $conn->query("SELECT status, username FROM users WHERE id = $user_id")->fetch_assoc();
    if ($user) {
        $new_status = $user['status'] == 'active' ? 'inactive' : 'active';
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $user_id);
        
        if ($stmt->execute()) {
            if (function_exists('logUserUpdate')) {
                logUserUpdate($_SESSION['user_id'], $user_id, ['status' => $user['status']], ['status' => $new_status]);
            }
            echo json_encode(['success' => true, 'status' => $new_status, 'message' => 'Status updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update status']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'User not found']);
    }
    exit;
}

// Reset password
if (isset($_GET['ajax']) && $_GET['ajax'] === 'reset_password' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $user_id = (int)$_GET['id'];
    $new_password = $_GET['password'] ?? '';
    
    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
        exit;
    }
    
    $hashed = password_hash($new_password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed, $user_id);
    
    if ($stmt->execute()) {
        if (function_exists('logPasswordChange')) {
            logPasswordChange($user_id, $_SESSION['user_id']);
        }
        echo json_encode(['success' => true, 'message' => 'Password reset successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reset password']);
    }
    exit;
}

// ============================================
// REGULAR PAGE DISPLAY
// ============================================

// Get statistics
$stats = [];
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$stats['total_users'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE status = 'active'");
$stats['active_users'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'admin'");
$stats['admins'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'super_admin'");
$stats['super_admins'] = $result->fetch_assoc()['total'];

$result = $conn->query("SELECT COUNT(*) as total FROM users WHERE status = 'pending'");
$stats['pending_users'] = $result->fetch_assoc()['total'];

// Get all active/non-pending users
$users = $conn->query("
    SELECT * FROM users 
    WHERE status != 'pending'
    ORDER BY 
        CASE role 
            WHEN 'super_admin' THEN 1 
            WHEN 'admin' THEN 2 
            ELSE 3 
        END,
        created_at DESC
");

// Get pending users
$pending_users = $conn->query("
    SELECT * FROM users 
    WHERE status = 'pending' 
    ORDER BY created_at DESC
");

include '../includes/header.php';
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
    --warning: #FFB74D;
    --info: #8FB5FF;
}

body {
    background-color: var(--light);
    color: var(--text-primary);
}

/* Dashboard Cards */
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background: var(--white);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
    border-left: 4px solid var(--primary);
    transition: all 0.3s ease;
}

.card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(107, 140, 255, 0.15);
}

.card-icon {
    width: 45px;
    height: 45px;
    background: var(--accent-light);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
}

.card-icon i {
    font-size: 22px;
    color: var(--primary);
}

.card h3 {
    color: var(--text-secondary);
    font-size: 12px;
    margin-bottom: 8px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card .card-value {
    color: var(--primary);
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 5px;
}

.card .card-label {
    color: var(--text-muted);
    font-size: 11px;
}

/* Pending Table Specific Styles */
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

/* Table Container */
.table-container {
    background: var(--white);
    border-radius: 12px;
    padding: 25px;
    margin-bottom: 30px;
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
    overflow-x: auto;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--accent-light);
    flex-wrap: wrap;
    gap: 15px;
}

.table-header h2 {
    color: var(--primary);
    font-size: 18px;
    font-weight: 600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.table-header h2 i {
    color: var(--accent);
}

.search-box {
    display: flex;
    gap: 10px;
}

.search-box input {
    padding: 10px 12px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 14px;
    width: 250px;
    transition: all 0.3s;
}

.search-box input:focus {
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
    cursor: pointer;
    transition: all 0.3s;
    font-weight: 500;
}

.search-box button:hover {
    background-color: #e69eb0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(248, 176, 192, 0.3);
}

/* Table Styles */
table {
    width: 100%;
    border-collapse: collapse;
}

th {
    padding: 12px 10px;
    text-align: left;
    color: var(--primary);
    font-weight: 600;
    font-size: 12px;
    border-bottom: 2px solid var(--accent-light);
}

td {
    padding: 12px 10px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
    font-size: 13px;
}

tr:hover td {
    background-color: rgba(107, 140, 255, 0.04);
}

/* Badges */
.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
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
    color: #E65100;
}

.badge-info {
    background-color: #E3F2FD;
    color: #1976D2;
}

.badge-pending {
    background-color: #FFF9C4;
    color: #E65100;
}

/* Avatar */
.avatar-small {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    background: var(--accent-light);
    display: flex;
    align-items: center;
    justify-content: center;
}

.avatar-small i {
    font-size: 16px;
    color: var(--accent);
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    padding: 5px 10px;
    border-radius: 6px;
    border: none;
    color: white;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 11px;
    font-weight: 500;
}

.action-btn i {
    font-size: 11px;
}

.action-btn.view { background-color: var(--info); }
.action-btn.edit { background-color: var(--warning); }
.action-btn.delete { background-color: var(--danger); }
.action-btn.key { background-color: var(--secondary); }
.action-btn.toggle { background-color: var(--primary); }
.action-btn.approve { background-color: var(--success); }
.action-btn.reject { background-color: var(--danger); }

.action-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 8px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.3s;
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

.btn-secondary {
    background-color: var(--secondary);
    color: white;
}

.btn-secondary:hover {
    background-color: #7a9fe6;
    transform: translateY(-2px);
}

/* ============================================
   MODAL STYLES - MATCHING SETTINGS.PHP
   ============================================ */

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
    overflow-y: auto;
}

.modal-container {
    background-color: var(--white);
    margin: 5% auto;
    padding: 0;
    border-radius: 12px;
    width: 550px;
    max-width: 90%;
    box-shadow: 0 10px 30px rgba(107, 140, 255, 0.2);
    animation: modalSlideIn 0.3s;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    max-height: 85vh;
}

.modal-container.large {
    width: 700px;
}

.modal-container.small {
    width: 450px;
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
    padding: 20px 25px;
    border-bottom: 2px solid var(--accent-light);
    background: var(--white);
    flex-shrink: 0;
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

.modal-body-scroll {
    padding: 25px;
    overflow-y: auto;
    flex: 1;
}

.modal-footer-buttons {
    text-align: right;
    padding: 16px 25px;
    border-top: 1px solid var(--border-light);
    background: var(--light);
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    flex-shrink: 0;
}

/* Delete Modal Specific */
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
    overflow-y: auto;
}

.delete-modal-container {
    background-color: var(--white);
    border-radius: 16px;
    width: 450px;
    max-width: 90%;
    box-shadow: 0 20px 35px rgba(0,0,0,0.2);
    animation: modalSlideIn 0.2s;
    overflow: hidden;
    margin: 20px auto;
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

.delete-modal-body {
    padding: 24px;
    max-height: 60vh;
    overflow-y: auto;
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
.delete-warning .fa-times-circle {
    color: var(--danger);
}

.delete-warning .fa-user-check {
    color: var(--success);
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
    background: var(--white);
}

/* Success Modal Header */
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
    margin-right: 6px;
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

.form-row {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.form-row .form-group {
    flex: 1;
    margin-bottom: 0;
}

.text-muted {
    color: var(--text-muted);
    font-size: 12px;
    margin-top: 5px;
    display: block;
}

/* Modal Buttons */
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

.btn-modal-primary {
    background-color: var(--accent);
    color: var(--text-primary);
}

.btn-modal-primary:hover {
    background-color: #e69eb0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(248, 176, 192, 0.3);
}

/* View User Styles */
.view-avatar {
    text-align: center;
    margin-bottom: 20px;
}

.avatar-large {
    width: 100px;
    height: 100px;
    background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
    border: 4px solid var(--white);
}

.avatar-large i {
    font-size: 50px;
    color: var(--white);
}

.view-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.user-detail {
    background: var(--light);
    border-radius: 12px;
    padding: 14px 16px;
    transition: all 0.2s ease;
    border: 1px solid transparent;
}

.user-detail:hover {
    border-color: var(--primary);
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
}

.user-detail label {
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

.user-detail label i {
    font-size: 12px;
    color: var(--primary);
}

.user-detail p {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
    margin: 0;
    word-break: break-word;
}

/* Scrollbar Styling */
.modal-body-scroll::-webkit-scrollbar,
.delete-modal-body::-webkit-scrollbar {
    width: 6px;
}

.modal-body-scroll::-webkit-scrollbar-track,
.delete-modal-body::-webkit-scrollbar-track {
    background: var(--light);
    border-radius: 3px;
}

.modal-body-scroll::-webkit-scrollbar-thumb,
.delete-modal-body::-webkit-scrollbar-thumb {
    background: var(--primary);
    border-radius: 3px;
}

/* Responsive */
@media (max-width: 768px) {
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .table-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .search-box {
        width: 100%;
    }
    
    .search-box input {
        flex: 1;
    }
    
    .form-row {
        flex-direction: column;
        gap: 0;
    }
    
    .form-row .form-group {
        margin-bottom: 15px;
    }
    
    .view-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .action-buttons {
        flex-wrap: wrap;
    }
    
    .modal-container {
        width: 95%;
        margin: 10% auto;
    }
    
    .delete-modal-container {
        width: 95%;
    }
    
    .modal-header-settings {
        padding: 15px 20px;
    }
    
    .modal-body-scroll {
        padding: 20px;
    }
    
    .modal-footer-buttons {
        padding: 12px 20px;
        flex-direction: column-reverse;
    }
    
    .btn-modal {
        width: 100%;
        justify-content: center;
    }
    
    .delete-modal-footer {
        flex-direction: column-reverse;
    }
    
    .delete-modal-footer .btn-modal {
        width: 100%;
        justify-content: center;
    }
}
</style>

<!-- Display Success/Error Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success" style="background: var(--success-light); color: var(--success); padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid var(--success);">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger" style="background: #FFEBEE; color: var(--danger); padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid var(--danger);">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<!-- Dashboard Stats -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon"><i class="fas fa-users"></i></div>
        <h3>Total Users</h3>
        <div class="card-value"><?php echo $stats['total_users']; ?></div>
    </div>
    <div class="card">
        <div class="card-icon"><i class="fas fa-user-check"></i></div>
        <h3>Active Users</h3>
        <div class="card-value"><?php echo $stats['active_users']; ?></div>
    </div>
    <div class="card">
        <div class="card-icon"><i class="fas fa-user-clock"></i></div>
        <h3>Pending</h3>
        <div class="card-value"><?php echo $stats['pending_users']; ?></div>
    </div>
    <div class="card">
        <div class="card-icon"><i class="fas fa-user-shield"></i></div>
        <h3>Admins</h3>
        <div class="card-value"><?php echo $stats['admins']; ?></div>
    </div>
    <div class="card">
        <div class="card-icon"><i class="fas fa-crown"></i></div>
        <h3>Super Admins</h3>
        <div class="card-value"><?php echo $stats['super_admins']; ?></div>
    </div>
</div>

<!-- ============================================ -->
<!-- PENDING REGISTRATIONS SECTION -->
<!-- ============================================ -->
<div class="table-container pending-container">
    <div class="table-header">
        <h2><i class="fas fa-clock"></i> Pending Registrations</h2>
        <span style="background: var(--accent); color: var(--text-primary); border-radius: 20px; padding: 2px 10px; font-size: 12px;"><?php echo $pending_users ? $pending_users->num_rows : 0; ?> pending</span>
    </div>
    
    <?php if ($pending_users && $pending_users->num_rows > 0): ?>
        <div style="overflow-x: auto;">
            <table>
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
                        <td>
                            <?php echo date('M d, Y h:i A', strtotime($pending['created_at'])); ?>
                            <br><small class="text-muted"><?php echo time_elapsed_string($pending['created_at']); ?></small>
                        </td>
                        <td class="action-buttons">
                            <button onclick="openApproveModal(<?php echo $pending['id']; ?>, '<?php echo htmlspecialchars(addslashes($pending['firstname'] . ' ' . $pending['lastname'])); ?>', '<?php echo htmlspecialchars($pending['email']); ?>')" class="action-btn approve">
                                <i class="fas fa-check-circle"></i> Approve
                            </button>
                            <button onclick="openRejectModal(<?php echo $pending['id']; ?>, '<?php echo htmlspecialchars(addslashes($pending['firstname'] . ' ' . $pending['lastname'])); ?>', '<?php echo htmlspecialchars($pending['email']); ?>')" class="action-btn reject">
                                <i class="fas fa-times-circle"></i> Reject
                            </button>
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

<!-- Add User Button -->
<div style="margin-bottom: 20px;">
    <button class="btn btn-primary" onclick="openAddUserModal()">
        <i class="fas fa-plus"></i> Add New User
    </button>
</div>

<!-- Active Users Table -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-users"></i> System Users</h2>
        <div class="search-box">
            <input type="text" id="searchInput" placeholder="Search by username, name or email...">
            <button onclick="searchUsers()"><i class="fas fa-search"></i> Search</button>
        </div>
    </div>
    
    <div style="overflow-x: auto;">
        <table id="usersTable">
            <thead>
                <tr>
                    <th>ID</th>
                    <th></th>
                    <th>Username</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($user = $users->fetch_assoc()): ?>
                <tr id="user-row-<?php echo $user['id']; ?>">
                    <td><?php echo $user['id']; ?></td>
                    <td>
                        <div class="avatar-small">
                            <i class="fas fa-user"></i>
                        </div>
                    </td>
                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                    <td><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></td>
                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                    <td>
                        <span class="badge <?php echo $user['role'] == 'super_admin' ? 'badge-danger' : ($user['role'] == 'admin' ? 'badge-warning' : 'badge-info'); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                        </span>
                    </td>
                    <td>
                        <span class="badge <?php echo $user['status'] == 'active' ? 'badge-success' : ($user['status'] == 'pending' ? 'badge-pending' : 'badge-danger'); ?>" id="status-<?php echo $user['id']; ?>">
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </td>
                    <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn view" onclick="viewUser(<?php echo $user['id']; ?>)" title="View">
                                <i class="fas fa-eye"></i> View
                            </button>
                            <button class="action-btn edit" onclick="editUser(<?php echo $user['id']; ?>)" title="Edit">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="action-btn toggle" onclick="toggleStatus(<?php echo $user['id']; ?>, '<?php echo $user['status']; ?>')" title="Toggle Status">
                                <i class="fas fa-<?php echo $user['status'] == 'active' ? 'ban' : 'check'; ?>"></i> 
                                <?php echo $user['status'] == 'active' ? 'Disable' : 'Enable'; ?>
                            </button>
                            <button class="action-btn key" onclick="openResetModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')" title="Reset Password">
                                <i class="fas fa-key"></i> Reset PW
                            </button>
                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                            <button class="action-btn delete" onclick="openDeleteUserModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>')" title="Delete">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================ -->
<!-- MODALS -->
<!-- ============================================ -->

<!-- Delete User Modal -->
<div id="deleteUserModal" class="delete-modal-overlay">
    <div class="delete-modal-container">
        <div class="delete-modal-header">
            <h3><i class="fas fa-trash-alt"></i> Delete User</h3>
        </div>
        <div class="delete-modal-body">
            <div class="delete-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <p><strong>Are you absolutely sure?</strong></p>
                <p class="warning-text">This action cannot be undone.</p>
            </div>
            <div class="delete-item-details">
                <div class="detail-label">USER TO DELETE</div>
                <div class="detail-name" id="delete_username">-</div>
                <div class="detail-extra" id="delete_user_email">-</div>
            </div>
        </div>
        <div class="delete-modal-footer">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeDeleteUserModal()">Cancel</button>
            <a href="#" id="confirmDeleteUserBtn" class="btn-modal btn-modal-danger"><i class="fas fa-trash-alt"></i> Delete User</a>
        </div>
    </div>
</div>

<!-- Approve User Modal -->
<div id="approveModal" class="delete-modal-overlay">
    <div class="delete-modal-container">
        <div class="success-modal-header">
            <h3><i class="fas fa-check-circle"></i> Approve User</h3>
        </div>
        <div class="delete-modal-body">
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
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeApproveModal()">Cancel</button>
            <a href="#" id="confirmApproveBtn" class="btn-modal btn-modal-success"><i class="fas fa-check-circle"></i> Approve User</a>
        </div>
    </div>
</div>

<!-- Reject User Modal -->
<div id="rejectModal" class="delete-modal-overlay">
    <div class="delete-modal-container">
        <div class="delete-modal-header">
            <h3><i class="fas fa-times-circle"></i> Reject User</h3>
        </div>
        <div class="delete-modal-body">
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
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeRejectModal()">Cancel</button>
            <a href="#" id="confirmRejectBtn" class="btn-modal btn-modal-danger"><i class="fas fa-times-circle"></i> Reject User</a>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div id="resetModal" class="modal-overlay">
    <div class="modal-container small">
        <div class="modal-header-settings">
            <h3><i class="fas fa-key"></i> Reset Password</h3>
            <span class="modal-close" onclick="closeResetModal()">&times;</span>
        </div>
        <div class="modal-body-scroll">
            <p>Reset password for: <strong id="resetUsername"></strong></p>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> New Password</label>
                <input type="password" id="newPassword" class="form-control" placeholder="Enter new password (min 6 characters)">
            </div>
            <div class="form-group">
                <label><i class="fas fa-lock"></i> Confirm Password</label>
                <input type="password" id="confirmNewPassword" class="form-control" placeholder="Confirm new password">
            </div>
        </div>
        <div class="modal-footer-buttons">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeResetModal()">Cancel</button>
            <button type="button" class="btn-modal btn-modal-primary" onclick="submitResetPassword()"><i class="fas fa-key"></i> Reset Password</button>
        </div>
    </div>
</div>

<!-- Add/Edit User Modal -->
<div id="userModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header-settings">
            <h3 id="modalTitle"><i class="fas fa-user-plus"></i> Add New User</h3>
            <span class="modal-close" onclick="closeUserModal()">&times;</span>
        </div>
        <div class="modal-body-scroll">
            <form id="userForm">
                <input type="hidden" id="user_id" name="user_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> First Name *</label>
                        <input type="text" id="firstname" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-user"></i> Last Name *</label>
                        <input type="text" id="lastname" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-at"></i> Username *</label>
                        <input type="text" id="username" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-envelope"></i> Email *</label>
                        <input type="email" id="email" class="form-control" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label><i class="fas fa-tag"></i> Role *</label>
                        <select id="role" class="form-control">
                            <option value="user">Regular User</option>
                            <option value="supply">Supply Officer</option>
                            <option value="admin">Administrator</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-circle"></i> Status</label>
                        <select id="status" class="form-control">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label id="passwordLabel"><i class="fas fa-lock"></i> Password <span id="passwordRequired">*</span></label>
                    <input type="password" id="password" class="form-control">
                    <small class="text-muted" id="passwordHint">Leave blank to keep current password</small>
                </div>
                
                <div class="form-group" id="confirmGroup">
                    <label><i class="fas fa-lock"></i> Confirm Password</label>
                    <input type="password" id="confirm_password" class="form-control">
                </div>
            </form>
        </div>
        <div class="modal-footer-buttons">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeUserModal()">Cancel</button>
            <button type="button" class="btn-modal btn-modal-primary" onclick="saveUser()"><i class="fas fa-save"></i> Save User</button>
        </div>
    </div>
</div>

<!-- View User Modal -->
<div id="viewModal" class="modal-overlay">
    <div class="modal-container large">
        <div class="modal-header-settings">
            <h3><i class="fas fa-user-circle"></i> User Details</h3>
            <span class="modal-close" onclick="closeViewModal()">&times;</span>
        </div>
        <div class="modal-body-scroll" id="viewContent">
            <div class="text-center">Loading...</div>
        </div>
        <div class="modal-footer-buttons">
            <button type="button" class="btn-modal btn-modal-secondary" onclick="closeViewModal()">Close</button>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
let currentResetUserId = null;
let currentDeleteUserId = null;
let currentApproveUserId = null;
let currentRejectUserId = null;

// Search function
function searchUsers() {
    let input = document.getElementById('searchInput');
    let filter = input.value.toUpperCase();
    let table = document.getElementById('usersTable');
    let tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        let td = tr[i].getElementsByTagName('td');
        let found = false;
        for (let j = 2; j <= 4; j++) {
            if (td[j] && td[j].textContent.toUpperCase().indexOf(filter) > -1) {
                found = true;
                break;
            }
        }
        tr[i].style.display = found ? '' : 'none';
    }
}

// Real-time search
document.getElementById('searchInput').addEventListener('keyup', searchUsers);

// Delete User Modal Functions
function openDeleteUserModal(id, username, email) {
    currentDeleteUserId = id;
    document.getElementById('delete_username').innerText = username;
    document.getElementById('delete_user_email').innerText = email;
    document.getElementById('confirmDeleteUserBtn').href = '?ajax=delete_user&id=' + id;
    document.getElementById('deleteUserModal').style.display = 'flex';
}

function closeDeleteUserModal() {
    document.getElementById('deleteUserModal').style.display = 'none';
    currentDeleteUserId = null;
}

// Approve Modal Functions
function openApproveModal(id, fullname, email) {
    currentApproveUserId = id;
    document.getElementById('approve_username').innerText = fullname;
    document.getElementById('approve_email').innerText = email;
    document.getElementById('confirmApproveBtn').href = window.location.href + '?approve=' + id;
    document.getElementById('approveModal').style.display = 'flex';
}

function closeApproveModal() {
    document.getElementById('approveModal').style.display = 'none';
    currentApproveUserId = null;
}

// Reject Modal Functions
function openRejectModal(id, fullname, email) {
    currentRejectUserId = id;
    document.getElementById('reject_username').innerText = fullname;
    document.getElementById('reject_email').innerText = email;
    document.getElementById('confirmRejectBtn').href = window.location.href + '?reject=' + id;
    document.getElementById('rejectModal').style.display = 'flex';
}

function closeRejectModal() {
    document.getElementById('rejectModal').style.display = 'none';
    currentRejectUserId = null;
}

// Reset Password Modal Functions
function openResetModal(id, username) {
    currentResetUserId = id;
    document.getElementById('resetUsername').innerHTML = username;
    document.getElementById('newPassword').value = '';
    document.getElementById('confirmNewPassword').value = '';
    document.getElementById('resetModal').style.display = 'block';
}

function closeResetModal() {
    document.getElementById('resetModal').style.display = 'none';
    currentResetUserId = null;
}

function submitResetPassword() {
    const password = document.getElementById('newPassword').value;
    const confirm = document.getElementById('confirmNewPassword').value;
    
    if (!password) {
        Swal.fire('Error', 'Please enter a new password', 'error');
        return;
    }
    
    if (password.length < 6) {
        Swal.fire('Error', 'Password must be at least 6 characters', 'error');
        return;
    }
    
    if (password !== confirm) {
        Swal.fire('Error', 'Passwords do not match', 'error');
        return;
    }
    
    Swal.fire({ title: 'Resetting...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(window.location.href + '?ajax=reset_password&id=' + currentResetUserId + '&password=' + encodeURIComponent(password))
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.success) {
                Swal.fire('Success!', data.message, 'success');
                closeResetModal();
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        });
}

// Open Add User Modal
function openAddUserModal() {
    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-plus"></i> Add New User';
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('password').required = true;
    document.getElementById('passwordRequired').innerHTML = '*';
    document.getElementById('passwordHint').style.display = 'block';
    document.getElementById('confirmGroup').style.display = 'block';
    document.getElementById('userModal').style.display = 'block';
}

function closeUserModal() {
    document.getElementById('userModal').style.display = 'none';
}

// Edit User
function editUser(userId) {
    Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(window.location.href + '?ajax=get_user&id=' + userId)
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.error) {
                Swal.fire('Error', data.error, 'error');
                return;
            }
            
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-edit"></i> Edit User';
            document.getElementById('user_id').value = data.id;
            document.getElementById('firstname').value = data.firstname;
            document.getElementById('lastname').value = data.lastname;
            document.getElementById('username').value = data.username;
            document.getElementById('email').value = data.email;
            document.getElementById('role').value = data.role;
            document.getElementById('status').value = data.status;
            document.getElementById('password').value = '';
            document.getElementById('password').required = false;
            document.getElementById('passwordRequired').innerHTML = '';
            document.getElementById('passwordHint').style.display = 'block';
            document.getElementById('confirmGroup').style.display = 'block';
            document.getElementById('userModal').style.display = 'block';
        })
        .catch(error => {
            Swal.close();
            Swal.fire('Error', 'Failed to load user data', 'error');
        });
}

// View User
function viewUser(userId) {
    Swal.fire({ title: 'Loading...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(window.location.href + '?ajax=get_user&id=' + userId)
        .then(response => response.json())
        .then(data => {
            Swal.close();
            if (data.error) {
                Swal.fire('Error', data.error, 'error');
                return;
            }
            
            const roleNames = {
                'super_admin': 'Super Administrator',
                'admin': 'Administrator',
                'supply': 'Supply Officer',
                'user': 'Regular User'
            };
            
            const statusBadge = data.status === 'active' ? 'badge-success' : (data.status === 'pending' ? 'badge-pending' : 'badge-danger');
            const statusText = data.status.toUpperCase();
            
            const html = `
                <div class="view-avatar">
                    <div class="avatar-large">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <h3 style="margin-top: 15px; color: var(--primary);">${escapeHtml(data.firstname)} ${escapeHtml(data.lastname)}</h3>
                    <span class="badge ${statusBadge}" style="margin-top: 5px;">${statusText}</span>
                </div>
                <div class="view-grid">
                    <div class="user-detail">
                        <label><i class="fas fa-user"></i> Username</label>
                        <p>${escapeHtml(data.username)}</p>
                    </div>
                    <div class="user-detail">
                        <label><i class="fas fa-envelope"></i> Email</label>
                        <p>${escapeHtml(data.email)}</p>
                    </div>
                    <div class="user-detail">
                        <label><i class="fas fa-user-tag"></i> Role</label>
                        <p>${roleNames[data.role] || data.role}</p>
                    </div>
                    <div class="user-detail">
                        <label><i class="fas fa-calendar-alt"></i> Member Since</label>
                        <p>${new Date(data.created_at).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                    </div>
                </div>
            `;
            
            document.getElementById('viewContent').innerHTML = html;
            document.getElementById('viewModal').style.display = 'block';
        })
        .catch(error => {
            Swal.close();
            Swal.fire('Error', 'Failed to load user details', 'error');
        });
}

function closeViewModal() {
    document.getElementById('viewModal').style.display = 'none';
}

// Save User
function saveUser() {
    const userId = document.getElementById('user_id').value;
    const password = document.getElementById('password').value;
    const confirm = document.getElementById('confirm_password').value;
    
    if (!userId && !password) {
        Swal.fire('Error', 'Password is required for new users', 'error');
        return;
    }
    
    if (password && password !== confirm) {
        Swal.fire('Error', 'Passwords do not match', 'error');
        return;
    }
    
    if (password && password.length < 6) {
        Swal.fire('Error', 'Password must be at least 6 characters', 'error');
        return;
    }
    
    const data = {
        user_id: userId,
        firstname: document.getElementById('firstname').value,
        lastname: document.getElementById('lastname').value,
        username: document.getElementById('username').value,
        email: document.getElementById('email').value,
        role: document.getElementById('role').value,
        status: document.getElementById('status').value,
        password: password
    };
    
    Swal.fire({ title: 'Saving...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
    
    fetch(window.location.href + '?ajax=save_user', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire('Success!', data.message, 'success').then(() => location.reload());
        } else {
            Swal.fire('Error', data.message, 'error');
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire('Error', 'Failed to save user', 'error');
    });
}

// Toggle Status
function toggleStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'activate' : 'deactivate';
    
    Swal.fire({
        title: `${action} User?`,
        text: `Are you sure you want to ${action} this user?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: action === 'activate' ? '#4CAF50' : '#f44336',
        confirmButtonText: `Yes, ${action}!`
    }).then((result) => {
        if (result.isConfirmed) {
            Swal.fire({ title: 'Processing...', allowOutsideClick: false, didOpen: () => Swal.showLoading() });
            
            fetch(window.location.href + '?ajax=toggle_status&id=' + userId)
                .then(response => response.json())
                .then(data => {
                    Swal.close();
                    if (data.success) {
                        const statusSpan = document.getElementById('status-' + userId);
                        const toggleBtn = document.querySelector(`#user-row-${userId} .action-btn.toggle`);
                        
                        if (data.status === 'active') {
                            statusSpan.innerHTML = 'Active';
                            statusSpan.className = 'badge badge-success';
                            toggleBtn.innerHTML = '<i class="fas fa-ban"></i> Disable';
                        } else {
                            statusSpan.innerHTML = 'Inactive';
                            statusSpan.className = 'badge badge-danger';
                            toggleBtn.innerHTML = '<i class="fas fa-check"></i> Enable';
                        }
                        Swal.fire('Success!', data.message, 'success');
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                });
        }
    });
}

// Helper Functions
function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/[&<>]/g, function(m) {
        if (m === '&') return '&amp;';
        if (m === '<') return '&lt;';
        if (m === '>') return '&gt;';
        return m;
    });
}

// Close modals on escape
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeUserModal();
        closeViewModal();
        closeResetModal();
        closeDeleteUserModal();
        closeApproveModal();
        closeRejectModal();
    }
});

// Close modal on outside click
window.onclick = function(e) {
    if (e.target === document.getElementById('userModal')) closeUserModal();
    if (e.target === document.getElementById('viewModal')) closeViewModal();
    if (e.target === document.getElementById('resetModal')) closeResetModal();
    if (e.target === document.getElementById('deleteUserModal')) closeDeleteUserModal();
    if (e.target === document.getElementById('approveModal')) closeApproveModal();
    if (e.target === document.getElementById('rejectModal')) closeRejectModal();
}
</script>

<?php include '../includes/footer.php'; ?>