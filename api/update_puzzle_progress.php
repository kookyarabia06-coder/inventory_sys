<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['completed'])) {
    $completed = intval($_POST['completed']);
    $_SESSION['puzzles_completed'] = $completed;
    echo json_encode(['success' => true, 'completed' => $completed]);
} else {
    echo json_encode(['success' => false]);
}
?>