<?php
/**
 * API: Request Extension
 * Allows users to request extension for issued items
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
$issuance_id = isset($input['issuance_id']) ? (int)$input['issuance_id'] : 0;
$new_return_date = $input['new_return_date'];
$reason = sanitize($input['reason']);

// Validate input
if (!$issuance_id || empty($new_return_date) || empty($reason)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit();
}

// Verify that the issuance belongs to this user
$check = $conn->query("
    SELECT id FROM equipment_issuance 
    WHERE id = $issuance_id AND issued_to = $user_id AND status = 'issued'
");

if ($check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid issuance or not authorized']);
    exit();
}

// Create extension request (you might want a separate table for this)
$date_created = date('Y-m-d H:i:s');
$details = "Extension requested to $new_return_date. Reason: $reason";

$stmt = $conn->prepare("
    INSERT INTO activity_log (user_id, action, item_id, details, date_created) 
    VALUES (?, 'Request Extension', ?, ?, ?)
");
$stmt->bind_param("iiss", $user_id, $issuance_id, $details, $date_created);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Extension request submitted successfully']);
} else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $conn->error]);
}

$stmt->close();
?>