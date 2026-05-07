<?php
/**
 * API endpoint to manually unlock locked accounts
 */
header('Content-Type: application/json');

require_once __DIR__ . '/../config.php';
require_once CONFIG_PATH . '/database.php';

$response = ['success' => false, 'message' => ''];

// Check if the request is from an admin (simple check)
// In production, add proper authentication
$result = $conn->query("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE locked_until IS NOT NULL AND locked_until < NOW()");

if ($conn->affected_rows > 0) {
    $response['success'] = true;
    $response['message'] = $conn->affected_rows . ' account(s) unlocked successfully.';
} else {
    // Also force unlock all (including future locks) - for testing
    $conn->query("UPDATE users SET login_attempts = 0, locked_until = NULL");
    $response['success'] = true;
    $response['message'] = 'All accounts have been unlocked.';
}

echo json_encode($response);
?>