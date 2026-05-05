<?php
/**
 * Add User Page (Admin)
 * Create new system users
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

requireRole('admin');

$page_title = 'Add User';
$page_description = 'Create new system user account';

$errors = [];
$success = '';

// Pre-fill form data on error
$form_data = [
    'username' => '',
    'firstname' => '',
    'lastname' => '',
    'email' => '',
    'role' => 'user',
    'status' => 'active'
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $form_data = array_merge($form_data, $_POST);
    $required_fields = ['username', 'firstname', 'lastname', 'email', 'role'];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $errors[] = ucfirst($field) . ' is required';
        }
    }
    
    if (empty($errors)) {
        $username = trim(sanitize($_POST['username']));
        $firstname = trim(sanitize($_POST['firstname']));
        $lastname = trim(sanitize($_POST['lastname']));
        $email = trim(sanitize($_POST['email']));
        $role = sanitize($_POST['role']);
        $status = sanitize($_POST['status']);
        $password_raw = !empty($_POST['password']) ? trim($_POST['password']) : null;
        
        // Generate password if not provided
        if (empty($password_raw)) {
            $password_raw = substr(md5(uniqid(rand(), true)), 0, 12);
        }
        
        // Validate username (alphanumeric and underscores only)
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers, and underscores';
        }
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format';
        }
        
        // Validate role
        $allowed_roles = ['super_admin', 'admin', 'supply', 'user'];
        if (!in_array($role, $allowed_roles)) {
            $errors[] = 'Invalid role selected';
        }
        
        // Check if username exists
        $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
        if ($check && $check->num_rows > 0) {
            $errors[] = 'Username already exists';
        }
        
        // Check if email exists
        $check_email = $conn->query("SELECT id FROM users WHERE email = '$email'");
        if ($check_email && $check_email->num_rows > 0) {
            $errors[] = 'Email already registered';
        }
        
        // Password strength check
        if (strlen($password_raw) < 6) {
            $errors[] = 'Password must be at least 6 characters long';
        }
        
        if (empty($errors)) {
            $password_hash = password_hash($password_raw, PASSWORD_DEFAULT);
            
            $stmt = $conn->prepare("INSERT INTO users (username, password, firstname, lastname, email, role, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->bind_param('sssssss', $username, $password_hash, $firstname, $lastname, $email, $role, $status);
            
            if ($stmt->execute()) {
                $user_id = $stmt->insert_id;
                logActivity('Create User', $user_id, "Created user: $firstname $lastname ($username)");
                logAudit('CREATE', 'users', $user_id, null, json_encode([
                    'username' => $username,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'email' => $email,
                    'role' => $role,
                    'status' => $status
                ]));
                
                $_SESSION['success'] = "User created successfully. Password: <strong>$password_raw</strong> (Please share this with the user and ask them to change it after first login).";
                header('Location: ' . SITE_URL . '/admin/users.php');
                exit();
            } else {
                $errors[] = 'Error creating user: ' . $stmt->error;
            }
            $stmt->close();
        }
    }
}

include INCLUDE_PATH . '/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-user-plus"></i> Add New User</h2>
        <p>Create a new system user account</p>
    </div>

    <?php if (!empty($errors)):?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <strong>Please fix the following errors:</strong>
        <ul style="margin: 10px 0 0 20px;">
            <?php foreach ($errors as $error): ?>
            <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" action="" onsubmit="return validateForm()" id="addUserForm">
        <div class="form-row">
            <div class="form-group" style="flex: 1;">
                <label><i class="fas fa-user"></i> First Name *</label>
                <input type="text" name="firstname" class="form-control" value="<?php echo htmlspecialchars($form_data['firstname']); ?>" required placeholder="John">
            </div>
            <div class="form-group" style="flex: 1;">
                <label><i class="fas fa-user"></i> Last Name *</label>
                <input type="text" name="lastname" class="form-control" value="<?php echo htmlspecialchars($form_data['lastname']); ?>" required placeholder="Doe">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex: 1;">
                <label><i class="fas fa-user-tag"></i> Username *</label>
                <input type="text" name="username" class="form-control" value="<?php echo htmlspecialchars($form_data['username']); ?>" required placeholder="johndoe" pattern="[a-zA-Z0-9_]+" title="Letters, numbers, and underscores only">
                <small class="form-text">Letters, numbers, and underscores only</small>
            </div>
            <div class="form-group" style="flex: 1;">
                <label><i class="fas fa-envelope"></i> Email *</label>
                <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($form_data['email']); ?>" required placeholder="john@example.com">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group" style="flex: 1;">
                <label><i class="fas fa-lock"></i> Password</label>
                <input type="text" name="password" class="form-control" id="passwordInput" placeholder="Leave empty for auto-generated password" style="font-family: monospace;">
                <small class="form-text">Minimum 6 characters. Leave blank for auto-generated secure password.</small>
            </div>
            <div class="form-group" style="flex: 1;">
                <label><i class="fas fa-shield-alt"></i> Role *</label>
                <select name="role" class="form-control" required onchange="toggleRoleDescription()">
                    <option value="user" <?php echo $form_data['role'] == 'user' ? 'selected' : ''; ?>>End User - Regular user</option>
                    <option value="supply" <?php echo $form_data['role'] == 'supply' ? 'selected' : ''; ?>>Supply Officer - Can add inventory</option>
                    <option value="admin" <?php echo $form_data['role'] == 'admin' ? 'selected' : ''; ?>>Administrator - Full inventory control</option>
                    <option value="super_admin" <?php echo $form_data['role'] == 'super_admin' ? 'selected' : ''; ?>>Super Administrator - All system access</option>
                </select>
                <div id="roleDescription" style="margin-top: 8px; font-size: 12px; color: var(--text-muted); background: var(--light); padding: 10px; border-radius: 6px;">
                    <i class="fas fa-info-circle"></i> End User accounts can view assigned inventory items and submit requests.
                </div>
            </div>
        </div>

        <div class="form-group">
            <label><i class="fas fa-user-check"></i> Status</label>
            <select name="status" class="form-control">
                <option value="active" <?php echo $form_data['status'] == 'active' ? 'selected' : ''; ?>>Active - User can login immediately</option>
                <option value="inactive" <?php echo $form_data['status'] == 'inactive' ? 'selected' : ''; ?>>Inactive - User cannot login</option>
                <option value="pending" <?php echo $form_data['status'] == 'pending' ? 'selected' : ''; ?>>Pending Approval - Requires admin approval</option>
            </select>
        </div>

        <div class="modal-footer" style="margin-top: 20px; border-top: 1px solid var(--accent-light); padding-top: 20px;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-user-plus"></i> Create User Account
            </button>
            <a href="<?php echo SITE_URL; ?>/admin/users.php" class="btn btn-secondary">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
function toggleRoleDescription() {
    const role = document.querySelector('select[name="role"]').value;
    const desc = document.getElementById('roleDescription');
    const descriptions = {
        'super_admin': '<i class="fas fa-crown"></i> <strong>Super Administrator:</strong> Full system control, user management, audit trails, and all settings access.',
        'admin': '<i class="fas fa-tools"></i> <strong>Administrator:</strong> Full inventory control, can issue/return items, manage employees, equipment, and locations.',
        'supply': '<i class="fas fa-truck"></i> <strong>Supply Officer:</strong> Can add and manage inventory items, manage equipment types and PPE.',
        'user': '<i class="fas fa-user"></i> <strong>End User:</strong> Can view assigned inventory items, submit extension requests, view reports.'
    };
    desc.innerHTML = descriptions[role] || '';
}

function validateForm() {
    const password = document.getElementById('passwordInput').value.trim();
    if (password && password.length < 6) {
        alert('Password must be at least 6 characters long');
        return false;
    }
    return true;
}

toggleRoleDescription();
</script>

<style>
/* Additional styles for this page */
#passwordInput {
    font-family: monospace;
}

.alert ul {
    margin: 10px 0 0 20px;
}

.alert ul li {
    margin: 5px 0;
}
</style>

<?php include INCLUDE_PATH . '/footer.php'; ?>\n