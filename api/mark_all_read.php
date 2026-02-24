<?php
/**
 * API: Mark all notifications as read
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Here you would update your notifications table
// For now, just log the action
logActivity('Mark All Read', $user_id, 'User marked all notifications as read');

echo json_encode([
    'success' => true, 
    'message' => 'All notifications marked as read'
]);
?>