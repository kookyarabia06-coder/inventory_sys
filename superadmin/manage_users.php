<?php
/**
 * Manage Users Page (Super Admin)
 * CRUD operations for all users
 */

$page_title = 'Manage Users';
$page_description = 'Create, edit, and manage system users';

require_once '../includes/auth.php';
requireRole('super_admin');

// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $user_id = (int)$_GET['delete'];
    
    // Don't allow deleting yourself
    if ($user_id != $_SESSION['user_id']) {
        $user = $conn->query("SELECT username FROM users WHERE id = $user_id")->fetch_assoc();
        
        $conn->query("DELETE FROM users WHERE id = $user_id");
        
        if ($conn->affected_rows > 0) {
            logActivity('Delete User', $user_id, "Deleted user: " . $user['username']);
            logAudit('DELETE', 'users', $user_id, null, "User deleted");
            $_SESSION['success'] = "User deleted successfully";
        }
    } else {
        $_SESSION['error'] = "You cannot delete your own account";
    }
    
    header('Location: /superadmin/manage_users');
    exit();
}

// Handle status toggle
if (isset($_GET['toggle']) && is_numeric($_GET['toggle'])) {
    $user_id = (int)$_GET['toggle'];
    
    $user = $conn->query("SELECT status FROM users WHERE id = $user_id")->fetch_assoc();
    $new_status = $user['status'] == 'active' ? 'inactive' : 'active';
    
    $conn->query("UPDATE users SET status = '$new_status' WHERE id = $user_id");
    
    logActivity('Toggle User Status', $user_id, "User status changed to $new_status");
    $_SESSION['success'] = "User status updated successfully";
    
    header('Location: /superadmin/manage_users');
    exit();
}

// Get all users
$users = $conn->query("
    SELECT * FROM users 
    ORDER BY 
        CASE role 
            WHEN 'super_admin' THEN 1 
            WHEN 'admin' THEN 2 
            ELSE 3 
        END,
        created_at DESC
");

include '../includes/header.php';
?>

<!-- Add User Button -->
<div style="margin-bottom: 20px;">
    <button class="btn btn-primary" onclick="openUserModal()">
        <i class="fas fa-plus"></i> Add New User
    </button>
</div>

<!-- Users Table -->
<div class="table-container">
    <div class="table-header">
        <h2>System Users</h2>
        <div class="search-box" style="width: 300px;">
            <input type="text" id="searchUsers" placeholder="Search users...">
            <button onclick="searchTable('searchUsers', 'usersTable')">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
    
    <table id="usersTable">
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($user = $users->fetch_assoc()): ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($user['username']); ?></strong>
                </td>
                <td><?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td>
                    <?php
                    $role_badges = [
                        'super_admin' => 'badge-danger',
                        'admin' => 'badge-warning',
                        'user' => 'badge-success'
                    ];
                    $badge_class = $role_badges[$user['role']] ?? 'badge-secondary';
                    ?>
                    <span class="badge <?php echo $badge_class; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                    </span>
                </td>
                <td>
                    <?php if ($user['status'] == 'active'): ?>
                        <span class="badge badge-success">Active</span>
                    <?php else: ?>
                        <span class="badge badge-danger">Inactive</span>
                    <?php endif; ?>
                </td>
                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                <td>
                    <div class="action-buttons">
                        <button class="action-btn edit" onclick="editUser(<?php echo $user['id']; ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="?toggle=<?php echo $user['id']; ?>" class="action-btn <?php echo $user['status'] == 'active' ? 'btn-warning' : 'btn-success'; ?>">
                            <i class="fas fa-<?php echo $user['status'] == 'active' ? 'ban' : 'check'; ?>"></i>
                        </a>
                        <?php if ($user['id'] != $_SESSION['user_id']): ?>
                        <a href="?delete=<?php echo $user['id']; ?>" 
                           class="action-btn delete" 
                           onclick="return confirm('Are you sure you want to delete this user?')">
                            <i class="fas fa-trash"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

<!-- User Modal -->
<div id="userModal" class="modal">
    <div class="modal-content" style="max-width: 600px;">
        <div class="modal-header">
            <h2 id="modalTitle">Add New User</h2>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="userForm" method="POST" action="/api/save_user.php">
                <input type="hidden" id="user_id" name="user_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstname">First Name *</label>
                        <input type="text" class="form-control" id="firstname" name="firstname" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="lastname">Last Name *</label>
                        <input type="text" class="form-control" id="lastname" name="lastname" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="username">Username *</label>
                        <input type="text" class="form-control" id="username" name="username" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" class="form-control" id="email" name="email" required>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="role">Role *</label>
                        <select class="form-control" id="role" name="role" required>
                            <option value="user">Regular User</option>
                            <option value="admin">Admin</option>
                            <option value="super_admin">Super Admin</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="status">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group" id="passwordGroup">
                    <label for="password">Password *</label>
                    <input type="password" class="form-control" id="password" name="password">
                    <small class="text-muted">Leave blank to keep current password when editing</small>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">Save User</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openUserModal() {
    document.getElementById('modalTitle').textContent = 'Add New User';
    document.getElementById('userForm').reset();
    document.getElementById('user_id').value = '';
    document.getElementById('password').required = true;
    document.getElementById('passwordGroup').querySelector('small').style.display = 'none';
    document.getElementById('userModal').style.display = 'block';
}

function editUser(userId) {
    // Load user data via AJAX
    ajaxRequest('/api/get_user.php?id=' + userId, 'GET', null, function(err, response) {
        if (!err && response) {
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('user_id').value = response.id;
            document.getElementById('firstname').value = response.firstname;
            document.getElementById('lastname').value = response.lastname;
            document.getElementById('username').value = response.username;
            document.getElementById('email').value = response.email;
            document.getElementById('role').value = response.role;
            document.getElementById('status').value = response.status;
            document.getElementById('password').required = false;
            document.getElementById('passwordGroup').querySelector('small').style.display = 'block';
            document.getElementById('userModal').style.display = 'block';
        }
    });
}

function closeModal() {
    document.getElementById('userModal').style.display = 'none';
}

// Handle form submission
document.getElementById('userForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    let formData = new FormData(this);
    
    ajaxRequest('/api/save_user.php', 'POST', Object.fromEntries(formData), function(err, response) {
        if (!err && response.success) {
            alert('User saved successfully!');
            location.reload();
        } else {
            alert('Error: ' + (err ? err.message : response.message));
        }
    });
});

function searchTable(inputId, tableId) {
    let input = document.getElementById(inputId);
    let filter = input.value.toUpperCase();
    let table = document.getElementById(tableId);
    let tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        let tdArray = tr[i].getElementsByTagName('td');
        let found = false;
        for (let j = 0; j < tdArray.length - 1; j++) {
            if (tdArray[j]) {
                let txtValue = tdArray[j].textContent || tdArray[j].innerText;
                if (txtValue.toUpperCase().indexOf(filter) > -1) {
                    found = true;
                    break;
                }
            }
        }
        tr[i].style.display = found ? '' : 'none';
    }
}
</script>

<?php include '../includes/footer.php'; ?>