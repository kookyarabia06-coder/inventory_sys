<?php
/**
 * API: Get Issuance Details
 * Returns details about a specific issuance
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

$issuance_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$issuance_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Issuance ID required']);
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Build query based on user role
$query = "
    SELECT 
        ei.*,
        i.article_name,
        i.property_no,
        i.uom,
        i.unit_value,
        CONCAT(u.firstname, ' ', u.lastname) as issued_to_name,
        CONCAT(ub.firstname, ' ', ub.lastname) as issued_by_name
    FROM equipment_issuance ei
    JOIN inventory i ON ei.inventory_id = i.id
    JOIN users u ON ei.issued_to = u.id
    JOIN users ub ON ei.issued_by = ub.id
    WHERE ei.id = $issuance_id
";

// For regular users, ensure they only see their own issuances
if ($user_role == 'user') {
    $query .= " AND ei.issued_to = $user_id";
}

$result = $conn->query($query);

if ($result && $row = $result->fetch_assoc()) {
    // Format dates
    $row['issued_date'] = date('Y-m-d', strtotime($row['issued_date']));
    $row['expected_return'] = date('Y-m-d', strtotime($row['expected_return']));
    if ($row['actual_return']) {
        $row['actual_return'] = date('Y-m-d', strtotime($row['actual_return']));
    }
    
    // Format numbers
    $row['quantity_issued'] = floatval($row['quantity_issued']);
    $row['unit_value'] = floatval($row['unit_value']);
    
    echo json_encode($row);
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Issuance not found']);
}
?>