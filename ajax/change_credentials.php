<?php
// api/change_credentials.php
session_start();
header('Content-Type: application/json');

require_once dirname(__DIR__) . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Check if user is logged in and is super admin
if (!isLoggedIn() || $_SESSION['role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$user_id = $data['user_id'] ?? 0;
$new_password = $data['new_password'] ?? '';
$reason = $data['reason'] ?? '';

if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID is required']);
    exit;
}

if (empty($new_password)) {
    echo json_encode(['success' => false, 'message' => 'New password is required']);
    exit;
}

if (strlen($new_password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

// Get user details before update for audit trail
$user_query = "SELECT firstname, lastname, username, email, role FROM users WHERE id = ?";
$stmt = $conn->prepare($user_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found']);
    exit;
}

// Hash the new password
$hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

// Update the password
$update_query = "UPDATE users SET password = ? WHERE id = ?";
$stmt = $conn->prepare($update_query);
$stmt->bind_param("si", $hashed_password, $user_id);

if ($stmt->execute()) {
    // Log the credential change
    $log_details = "Password changed for user: " . $user['username'] . " (" . $user['firstname'] . " " . $user['lastname'] . ")";
    if (!empty($reason)) {
        $log_details .= " - Reason: " . $reason;
    }
    
    logAudit('UPDATE', 'users', $user_id, null, json_encode(['password' => '[CHANGED]']));
    logActivity('Change Credentials', $user_id, $log_details);
    
    echo json_encode([
        'success' => true, 
        'message' => 'Password changed successfully for ' . $user['firstname'] . ' ' . $user['lastname']
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}
?>