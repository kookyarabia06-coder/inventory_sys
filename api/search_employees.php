<?php
// api/search_employees.php
header('Content-Type: application/json');

require_once '../config.php';
require_once '../config/database.php';

$name = isset($_GET['name']) ? trim($_GET['name']) : '';
$department = isset($_GET['department']) ? trim($_GET['department']) : '';
$section = isset($_GET['section']) ? trim($_GET['section']) : '';
$position = isset($_GET['position']) ? trim($_GET['position']) : '';

$query = "SELECT e.id, e.firstname, e.lastname, e.position, d.name as department_name, s.name as section_name
          FROM employees e
          LEFT JOIN departments d ON e.department_id = d.id
          LEFT JOIN sections s ON e.section_id = s.id
          WHERE e.status = 'Active'";

$params = [];
$types = "";

if (!empty($name)) {
    $query .= " AND (e.firstname LIKE ? OR e.lastname LIKE ? OR CONCAT(e.firstname, ' ', e.lastname) LIKE ?)";
    $searchTerm = "%$name%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types .= "sss";
}

if (!empty($department)) {
    $query .= " AND d.name = ?";
    $params[] = $department;
    $types .= "s";
}

if (!empty($section)) {
    $query .= " AND s.name = ?";
    $params[] = $section;
    $types .= "s";
}

if (!empty($position)) {
    $query .= " AND e.position = ?";
    $params[] = $position;
    $types .= "s";
}

$query .= " ORDER BY e.lastname, e.firstname LIMIT 50";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$employees = [];
while ($row = $result->fetch_assoc()) {
    $employees[] = $row;
}
$stmt->close();

echo json_encode($employees);
?>