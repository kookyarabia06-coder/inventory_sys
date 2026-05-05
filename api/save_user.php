<?php
/**
 * Save User API Endpoint
 * Handles creating and updating users with audit trail logging
 */

require_once '../config.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDE_PATH . '/functions.php';
require_once INCLUDE_PATH . '/auth.php';

// Only allow super admin
requireRole('super_admin');

// Set JSON response header
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

try {
    // Get POST data
    $user_id = isset($_POST['user_id']) && $_POST['user_id'] !== '' ? (int)$_POST['user_id'] : 0;
    $firstname = sanitize($_POST['firstname'] ?? '');
    $lastname = sanitize($_POST['lastname'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $role = sanitize($_POST['role'] ?? 'user');
    $status = sanitize($_POST['status'] ?? 'active');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($firstname) || empty($lastname) || empty($username) || empty($email)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Missing required fields']);
        exit;
    }
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid email format']);
        exit;
    }
    
    // If creating new user
    if ($user_id === 0) {
        // Check if username exists
        $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
        if ($check && $check->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username already exists']);
            exit;
        }
        
        // Check if email exists
        $check = $conn->query("SELECT id FROM users WHERE email = '$email'");
        if ($check && $check->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email already registered']);
            exit;
        }
        
        // Password is required for new users
        if (empty($password)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Password is required for new users']);
            exit;
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $created_at = date('Y-m-d H:i:s');
        
        // Create user
        $stmt = $conn->prepare("
            INSERT INTO users (firstname, lastname, username, email, password, role, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("ssssssss", $firstname, $lastname, $username, $email, $hashed_password, $role, $status, $created_at);
        
        if ($stmt->execute()) {
            $new_user_id = $stmt->insert_id;
            
            // Log to audit trail - NEW_USER action
            $user_info = json_encode([
                'username' => $username,
                'email' => $email,
                'role' => $role,
                'status' => $status,
                'firstname' => $firstname,
                'lastname' => $lastname,
                'created_by' => $_SESSION['user_id'],
                'created_at' => $created_at
            ]);
            logAudit('NEW_USER', 'users', $new_user_id, null, $user_info);
            
            echo json_encode([
                'success' => true,
                'message' => 'User created successfully',
                'user_id' => $new_user_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to create user: ' . $conn->error]);
        }
        $stmt->close();
    } else {
        // Update existing user
        $user = $conn->query("SELECT * FROM users WHERE id = $user_id")->fetch_assoc();
        
        if (!$user) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'User not found']);
            exit;
        }
        
        // Check if username is taken by another user
        $check = $conn->query("SELECT id FROM users WHERE username = '$username' AND id != $user_id");
        if ($check && $check->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Username already taken']);
            exit;
        }
        
        // Check if email is taken by another user
        $check = $conn->query("SELECT id FROM users WHERE email = '$email' AND id != $user_id");
        if ($check && $check->num_rows > 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Email already registered']);
            exit;
        }
        
        // Prepare update query
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("
                UPDATE users 
                SET firstname = ?, lastname = ?, username = ?, email = ?, password = ?, role = ?, status = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("sssssssi", $firstname, $lastname, $username, $email, $hashed_password, $role, $status, $user_id);
        } else {
            $stmt = $conn->prepare("
                UPDATE users 
                SET firstname = ?, lastname = ?, username = ?, email = ?, role = ?, status = ? 
                WHERE id = ?
            ");
            $stmt->bind_param("ssssssi", $firstname, $lastname, $username, $email, $role, $status, $user_id);
        }
        
        if ($stmt->execute()) {
            // Log changes to audit trail
            $changes = [];
            if ($user['firstname'] !== $firstname) $changes['firstname'] = ['old' => $user['firstname'], 'new' => $firstname];
            if ($user['lastname'] !== $lastname) $changes['lastname'] = ['old' => $user['lastname'], 'new' => $lastname];
            if ($user['username'] !== $username) $changes['username'] = ['old' => $user['username'], 'new' => $username];
            if ($user['email'] !== $email) $changes['email'] = ['old' => $user['email'], 'new' => $email];
            if ($user['role'] !== $role) $changes['role'] = ['old' => $user['role'], 'new' => $role];
            if ($user['status'] !== $status) $changes['status'] = ['old' => $user['status'], 'new' => $status];
            if (!empty($password)) $changes['password'] = ['old' => '***', 'new' => '***'];
            
            if (!empty($changes)) {
                logAudit('UPDATE', 'users', $user_id, json_encode($user), json_encode($changes));
            }
            
            echo json_encode([
                'success' => true,
                'message' => 'User updated successfully',
                'user_id' => $user_id
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => 'Failed to update user: ' . $conn->error]);
        }
        $stmt->close();
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>
