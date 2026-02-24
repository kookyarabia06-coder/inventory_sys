<?php
/**
 * API: Submit Item Request
 */

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

// Get POST data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit();
}

$item_id = $input['item_id'];
$user_id = $_SESSION['user_id'];
$quantity = floatval($input['quantity']);
$purpose = sanitize($input['purpose']);
$expected_return = $input['expected_return'];
$remarks = sanitize($input['remarks'] ?? '');

// Check item availability
$item = $conn->query("SELECT * FROM inventory WHERE id = $item_id")->fetch_assoc();

if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Item not found']);
    exit();
}

if ($item['qty_physical_count'] < $quantity) {
    echo json_encode(['success' => false, 'message' => 'Insufficient quantity']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    // Create issuance record
    $stmt = $conn->prepare("
        INSERT INTO equipment_issuance 
        (inventory_id, issued_to, issued_by, quantity_issued, purpose, expected_return, remarks, status) 
        VALUES (?, ?, ?, ?, ?, ?, ?, 'issued')
    ");
    $stmt->bind_param("iiidsss", $item_id, $user_id, $user_id, $quantity, $purpose, $expected_return, $remarks);
    $stmt->execute();
    $issuance_id = $stmt->insert_id;
    
    // Update inventory quantity
    $new_quantity = $item['qty_physical_count'] - $quantity;
    $conn->query("UPDATE inventory SET qty_physical_count = $new_quantity WHERE id = $item_id");
    
    // Add to user inventory
    $stmt = $conn->prepare("
        INSERT INTO user_inventory (user_id, inventory_id, issuance_id, quantity_assigned, status) 
        VALUES (?, ?, ?, ?, 'active')
    ");
    $stmt->bind_param("iiid", $user_id, $item_id, $issuance_id, $quantity);
    $stmt->execute();
    
    // Log activity
    logActivity('Item Request', $item_id, "User requested $quantity of item ID: $item_id");
    
    $conn->commit();
    
    echo json_encode(['success' => true, 'message' => 'Request submitted successfully']);
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>