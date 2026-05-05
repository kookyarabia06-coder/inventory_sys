<?php
// api/save_user.php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

$root_path = dirname(__DIR__);
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Check if user is logged in and is super admin
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Please login']);
    exit;
}

if ($_SESSION['role'] !== 'super_admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin access required']);
    exit;
}

// Get POST data (JSON or form data)
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// If JSON decoding failed, try to get from $_POST
if (!$data && !empty($_POST)) {
    $data = $_POST;
}

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid data received']);
    exit;
}

$user_id = isset($data['user_id']) ? (int)$data['user_id'] : 0;
$firstname = trim($data['firstname'] ?? '');
$lastname = trim($data['lastname'] ?? '');
$username = trim($data['username'] ?? '');
$email = trim($data['email'] ?? '');
$role = $data['role'] ?? 'user';
$status = $data['status'] ?? 'active';
$password = $data['password'] ?? '';

// Validation
if (empty($firstname) || empty($lastname) || empty($username) || empty($email)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Invalid email format']);
    exit;
}

// Check if username already exists
$check_query = "SELECT id FROM users WHERE username = ?";
if ($user_id > 0) {
    $check_query .= " AND id != ?";
}
$stmt = $conn->prepare($check_query);
if ($user_id > 0) {
    $stmt->bind_param("si", $username, $user_id);
} else {
    $stmt->bind_param("s", $username);
}
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Username already exists']);
    exit;
}

// Check if email already exists
$check_query = "SELECT id FROM users WHERE email = ?";
if ($user_id > 0) {
    $check_query .= " AND id != ?";
}
$stmt = $conn->prepare($check_query);
if ($user_id > 0) {
    $stmt->bind_param("si", $email, $user_id);
} else {
    $stmt->bind_param("s", $email);
}
$stmt->execute();
if ($stmt->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Email already exists']);
    exit;
}

// For new user, password is required
if ($user_id == 0 && empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Password is required for new users']);
    exit;
}

// Validate password length if provided
if (!empty($password) && strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters']);
    exit;
}

// Get old user data for audit trail (if editing)
$old_data = null;
if ($user_id > 0) {
    $old_query = "SELECT firstname, lastname, username, email, role, status FROM users WHERE id = ?";
    $stmt = $conn->prepare($old_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $old_data = $stmt->get_result()->fetch_assoc();
}

// Save user
if ($user_id > 0) {
    // Update existing user
    if (!empty($password)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $query = "UPDATE users SET firstname = ?, lastname = ?, username = ?, email = ?, role = ?, status = ?, password = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("sssssssi", $firstname, $lastname, $username, $email, $role, $status, $hashed_password, $user_id);
    } else {
        $query = "UPDATE users SET firstname = ?, lastname = ?, username = ?, email = ?, role = ?, status = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ssssssi", $firstname, $lastname, $username, $email, $role, $status, $user_id);
    }
    
    if ($stmt->execute()) {
        // Log audit trail (if functions exist)
        if (function_exists('logAudit')) {
            $new_data = [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'status' => $status
            ];
            logAudit('UPDATE', 'users', $user_id, json_encode($old_data), json_encode($new_data));
        }
        if (function_exists('logActivity')) {
            logActivity('Update User', $user_id, "Updated user: $username");
        }
        
        echo json_encode(['success' => true, 'message' => 'User updated successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update user: ' . $conn->error]);
    }
} else {
    // Create new user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $query = "INSERT INTO users (firstname, lastname, username, email, role, status, password, created_at) 
              VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("sssssss", $firstname, $lastname, $username, $email, $role, $status, $hashed_password);
    
    if ($stmt->execute()) {
        $new_user_id = $conn->insert_id;
        
        if (function_exists('logAudit')) {
            $new_data = [
                'firstname' => $firstname,
                'lastname' => $lastname,
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'status' => $status
            ];
            logAudit('INSERT', 'users', $new_user_id, null, json_encode($new_data));
        }
        if (function_exists('logActivity')) {
            logActivity('Create User', $new_user_id, "Created new user: $username");
        }
        
        echo json_encode(['success' => true, 'message' => 'User created successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . $conn->error]);
    }
}
?>