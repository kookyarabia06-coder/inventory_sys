<?php
/**
 * Employees Page (Admin)
 * Manage employees
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

requireRole('admin');

$page_title = 'Employees';
$page_description = 'Manage employees';

// Get all employees
$employees = $conn->query("
    SELECT e.*, d.name as department_name, s.name as section_name 
    FROM employees e
    LEFT JOIN departments d ON e.department_id = d.id
    LEFT JOIN sections s ON e.section_id = s.id
    ORDER BY e.lastname, e.firstname
");

include INCLUDE_PATH . '/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-user-tie"></i> Employees</h2>
        <button class="btn btn-primary" onclick="addEmployee()">
            <i class="fas fa-plus"></i> Add Employee
        </button>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Contact</th>
                <th>Department</th>
                <th>Section</th>
                <th>Position</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($employees && $employees->num_rows > 0): ?>
                <?php while($emp = $employees->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $emp['id']; ?></td>
                    <td>
                        <strong><?php echo htmlspecialchars($emp['lastname'] . ', ' . $emp['firstname'] . ' ' . ($emp['middlename'] ?? '')); ?></strong>
                    </td>
                    <td><?php echo htmlspecialchars($emp['email']); ?></td>
                    <td><?php echo htmlspecialchars($emp['contact']); ?></td>
                    <td><?php echo htmlspecialchars($emp['department_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($emp['section_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($emp['position']); ?></td>
                    <td>
                        <?php if ($emp['status'] == 'Active'): ?>
                            <span class="badge badge-success">Active</span>
                        <?php else: ?>
                            <span class="badge badge-danger">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn edit" onclick="editEmployee(<?php echo $emp['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn view" onclick="viewEmployee(<?php echo $emp['id']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="9" class="text-center">No employees found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function addEmployee() {
    alert('Add employee functionality');
}

function editEmployee(id) {
    alert('Edit employee ' + id);
}

function viewEmployee(id) {
    alert('View employee ' + id);
}
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>