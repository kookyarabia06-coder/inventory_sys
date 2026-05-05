<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config.php';
require_once CONFIG_PATH . '/database.php';

$username = $_GET['username'] ?? '';

if (empty($username)) {
    echo json_encode(['available' => false, 'error' => 'Username required']);
    exit;
}

$stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

echo json_encode(['available' => $result->num_rows === 0]);
?>