<?php
/**
 * Export Audit Trail to CSV
 */

require_once '../config.php';
require_once '../includes/auth.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'super_admin') {
    die('Unauthorized');
}

// Get filters from URL
$filter_action_category = isset($_GET['action_category']) ? $_GET['action_category'] : '';
$filter_action = isset($_GET['action']) ? $_GET['action'] : '';
$filter_table = isset($_GET['table']) ? $_GET['table'] : '';
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$conditions = [];
$params = [];
$types = '';

if (!empty($filter_action_category)) {
    $conditions[] = "action_category = ?";
    $params[] = $filter_action_category;
    $types .= 's';
}
if (!empty($filter_action)) {
    $conditions[] = "action = ?";
    $params[] = $filter_action;
    $types .= 's';
}
if (!empty($filter_table)) {
    $conditions[] = "table_name = ?";
    $params[] = $filter_table;
    $types .= 's';
}
if ($filter_user > 0) {
    $conditions[] = "user_id = ?";
    $params[] = $filter_user;
    $types .= 'i';
}
if (!empty($date_from)) {
    $conditions[] = "DATE(created_at) >= ?";
    $params[] = $date_from;
    $types .= 's';
}
if (!empty($date_to)) {
    $conditions[] = "DATE(created_at) <= ?";
    $params[] = $date_to;
    $types .= 's';
}
if (!empty($search)) {
    $conditions[] = "(action LIKE ? OR table_name LIKE ? OR description LIKE ? OR details LIKE ? OR ip_address LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param, $search_param]);
    $types .= 'sssss';
}

$where_clause = !empty($conditions) ? "WHERE " . implode(" AND ", $conditions) : "";

$query = "
    SELECT 
        at.id,
        at.created_at as timestamp,
        CONCAT(u.firstname, ' ', u.lastname) as user_name,
        u.username,
        at.action_category,
        at.action,
        at.table_name,
        at.record_id,
        at.description,
        at.ip_address,
        at.user_agent
    FROM audit_trail at
    LEFT JOIN users u ON at.user_id = u.id
    $where_clause
    ORDER BY at.created_at DESC
";

// Execute query
if (!empty($params)) {
    $stmt = $conn->prepare($query);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    $result = $conn->query($query);
}

// Set CSV headers
header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="audit_trail_' . date('Y-m-d_H-i-s') . '.csv"');

$output = fopen('php://output', 'w');

// Add UTF-8 BOM for Excel compatibility
fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

// Write headers
fputcsv($output, [
    'ID', 
    'Timestamp', 
    'User', 
    'Username', 
    'Category', 
    'Action', 
    'Table/Module', 
    'Record ID', 
    'Description', 
    'IP Address', 
    'User Agent'
]);

// Write data
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['id'],
            $row['timestamp'],
            $row['user_name'] ?? 'System',
            $row['username'] ?? '',
            $row['action_category'] ?? '',
            $row['action'],
            $row['table_name'] ?? '',
            $row['record_id'] ?? '',
            $row['description'] ?? '',
            $row['ip_address'] ?? '',
            $row['user_agent'] ?? ''
        ]);
    }
}

fclose($output);
?>