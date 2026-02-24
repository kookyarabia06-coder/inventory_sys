<?php
/**
 * API: Get Item Details
 * Returns detailed information about an inventory item
 */

header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/auth.php';

// Check if user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$item_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$item_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Item ID required']);
    exit();
}

$query = "
    SELECT 
        i.*,
        e.name as equipment_name,
        e.category as equipment_category,
        s.name as section_name,
        d.name as department_name,
        b.name as building_name,
        CONCAT(u.firstname, ' ', u.lastname) as current_holder_name
    FROM inventory i
    LEFT JOIN equipment e ON i.equipment_id = e.id
    LEFT JOIN sections s ON i.section_id = s.id
    LEFT JOIN departments d ON s.department_id = d.id
    LEFT JOIN buildings b ON d.building_id = b.id
    LEFT JOIN users u ON i.current_holder = u.id
    WHERE i.id = $item_id
";

$result = $conn->query($query);

if ($result && $row = $result->fetch_assoc()) {
    // Format numeric values
    $row['unit_value'] = floatval($row['unit_value']);
    $row['qty_physical_count'] = floatval($row['qty_physical_count']);
    $row['qty_property_card'] = floatval($row['qty_property_card']);
    
    echo json_encode($row);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Item not found']);
}
?>