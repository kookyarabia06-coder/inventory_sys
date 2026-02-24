<?php
/**
 * API: Report Issue
 * Allows users to report issues with items
 */

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

$user_id = $_SESSION['user_id'];
$item_id = isset($input['item_id']) ? (int)$input['item_id'] : null;
$issue_type = sanitize($input['issue_type']);
$description = sanitize($input['description']);

// Validate input
if (empty($issue_type) || empty($description)) {
    echo json_encode(['success' => false, 'message' => 'Issue type and description are required']);
    exit();
}

// Create issue report (you might want to create a separate table for this)
$date_created = date('Y-m-d H:i:s');

$stmt = $conn->prepare("
    INSERT INTO activity_log (user_id, action, item_id, details, date_created) 
    VALUES (?, 'Report Issue', ?, ?, ?)
");
$stmt->bind_param("iiss", $user_id, $item_id, $description, $date_created);

if ($stmt->execute()) {
    // Log to audit trail as well
    logAudit('REPORT_ISSUE', 'inventory', $item_id, null, "Issue Type: $issue_type, Description: $description");
    
    echo json_encode([
        'success' => true, 
        'message' => 'Issue reported successfully',
        'ticket_id' => $stmt->insert_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
?>