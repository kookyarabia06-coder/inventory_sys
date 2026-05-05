<?php
/**
 * Get Audit Details API
 * Returns detailed information about a specific audit log entry
 */

header('Content-Type: application/json');
require_once '../config.php';
require_once '../includes/auth.php';

// Check if user is logged in and has super admin role
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_role = $_SESSION['role'] ?? '';
if ($user_role !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Access denied. Super Admin only.']);
    exit();
}

// Get the audit log ID
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'message' => 'Invalid ID']);
    exit();
}

// Get audit log details with user information
$stmt = $conn->prepare("
    SELECT at.*, 
           CONCAT(u.firstname, ' ', u.lastname) as user_name,
           u.username,
           u.email as user_email,
           u.role as user_role
    FROM audit_trail at
    LEFT JOIN users u ON at.user_id = u.id
    WHERE at.id = ?
");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result && $row = $result->fetch_assoc()) {
    echo json_encode(['success' => true, 'data' => $row]);
} else {
    echo json_encode(['success' => false, 'message' => 'Record not found']);
}
$stmt->close();
?>