<?php
/**
 * Users Page (Admin)
 * View system users (admin can view but not edit super admins)
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

requireRole('admin');

$page_title = 'Users';
$page_description = 'View system users';

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

include INCLUDE_PATH . '/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-users"></i> System Users</h2>
        <br>
          <?php if ($users && $users->num_rows > 0): ?>
        <label>
    <input type="username" name="search" placeholder="Search by username..." value="<?php echo isset($_POST['search']) ? htmlspecialchars($_POST['search']) : ''; ?>"
    filter_name="username" filter_type="like" oninput="filterTable(this)">
</label>
<?php endif; ?>
    </div>
    <button class="btn btn-primary" onclick="addUser()">
            <i class="fas fa-plus"></i> Add User
        </button>
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Username</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Created</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($users && $users->num_rows > 0): ?>
                <?php while($user = $users->fetch_assoc()): ?>
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
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">No users found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>



<form action="" method="POST">
<label class="colspan">
    <input type="checkbox" name="show_inactive" onchange="this.form.submit()" <?php if (isset($_POST['show_inactive'])) echo 'checked'; ?>>
    Show Inactive Users
</label>




         
</form>


<script>
    function addUser() {
      window.location.href = 'add_user.php';
    }
</script>


<?php include INCLUDE_PATH . '/footer.php'; ?>