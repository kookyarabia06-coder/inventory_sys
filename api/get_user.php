<?php
/**
 * Get User API Endpoint
 * Retrieves user data for editing
 */

require_once '../config.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDE_PATH . '/functions.php';
require_once INCLUDE_PATH . '/auth.php';

// Only allow super admin
requireRole('super_admin');

// Set JSON response header
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    $user_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($user_id === 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid user ID']);
        exit;
    }
    
    $user = $conn->query("SELECT id, firstname, lastname, username, email, role, status FROM users WHERE id = $user_id")->fetch_assoc();
    
    if (!$user) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'User not found']);
        exit;
    }
    
    echo json_encode($user);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
