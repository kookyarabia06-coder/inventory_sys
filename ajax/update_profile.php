<?php
// ajax/update_profile.php
session_start();
require_once dirname(__DIR__) . '/config.php';
require_once INCLUDE_PATH . '/functions.php';
require_once INCLUDE_PATH . '/auth.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'You must be logged in to update your profile.']);
    exit;
}

$user_id = $_SESSION['user_id'];
$response = ['success' => false, 'message' => ''];

// Get current user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    echo json_encode(['success' => false, 'message' => 'User not found.']);
    exit;
}

// Update basic info
$firstname = sanitize($_POST['firstname'] ?? '');
$lastname = sanitize($_POST['lastname'] ?? '');
$email = sanitize($_POST['email'] ?? '');
$new_password = $_POST['new_password'] ?? '';
$confirm_password = $_POST['confirm_password'] ?? '';

$update_fields = [];
$params = [];
$types = "";

if (!empty($firstname)) {
    $update_fields[] = "firstname = ?";
    $params[] = $firstname;
    $types .= "s";
}

if (!empty($lastname)) {
    $update_fields[] = "lastname = ?";
    $params[] = $lastname;
    $types .= "s";
}

if (!empty($email)) {
    // Check if email is already taken by another user
    $check_email = "SELECT id FROM users WHERE email = ? AND id != ?";
    $check_stmt = $conn->prepare($check_email);
    $check_stmt->bind_param("si", $email, $user_id);
    $check_stmt->execute();
    if ($check_stmt->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email address is already used by another account.']);
        exit;
    }
    $update_fields[] = "email = ?";
    $params[] = $email;
    $types .= "s";
}

// Update password if provided
if (!empty($new_password)) {
    if (strlen($new_password) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }
    if ($new_password !== $confirm_password) {
        echo json_encode(['success' => false, 'message' => 'Passwords do not match.']);
        exit;
    }
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $update_fields[] = "password = ?";
    $params[] = $hashed_password;
    $types .= "s";
}

// Handle avatar upload
$avatar_updated = false;
if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/jpg'];
    $file_type = $_FILES['avatar']['type'];
    $file_size = $_FILES['avatar']['size'];
    
    if (!in_array($file_type, $allowed_types)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, PNG, and GIF images are allowed.']);
        exit;
    }
    
    if ($file_size > 2 * 1024 * 1024) { // 2MB max
        echo json_encode(['success' => false, 'message' => 'File size must be less than 2MB.']);
        exit;
    }
    
    // Create avatars directory if not exists
    $avatar_dir = UPLOAD_PATH . '/avatars/';
    if (!file_exists($avatar_dir)) {
        mkdir($avatar_dir, 0777, true);
    }
    
    // Delete old avatar if exists
    if (!empty($user['avatar']) && file_exists($avatar_dir . $user['avatar'])) {
        unlink($avatar_dir . $user['avatar']);
    }
    
    // Generate new filename
    $extension = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
    $new_filename = 'avatar_' . $user_id . '_' . time() . '.' . $extension;
    $upload_path = $avatar_dir . $new_filename;
    
    if (move_uploaded_file($_FILES['avatar']['tmp_name'], $upload_path)) {
        $update_fields[] = "avatar = ?";
        $params[] = $new_filename;
        $types .= "s";
        $avatar_updated = true;
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload avatar.']);
        exit;
    }
}

// Execute update
if (!empty($update_fields)) {
    $params[] = $user_id;
    $types .= "i";
    $update_sql = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param($types, ...$params);
    
    if ($update_stmt->execute()) {
        // Log the activity
        logActivity($user_id, 'Profile Update', 'Updated profile information');
        
        echo json_encode([
            'success' => true, 
            'message' => 'Profile updated successfully!'
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update profile: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No changes to update.']);
}
?>