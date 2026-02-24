<?php
/**
 * Equipment Types Page (Admin)
 * Manage equipment types/categories
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

requireRole('admin');

$page_title = 'Equipment Types';
$page_description = 'Manage equipment types and categories';

// Get all equipment types
$equipment = $conn->query("SELECT * FROM equipment ORDER BY name");

include INCLUDE_PATH . '/header.php';
?>

<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-laptop"></i> Equipment Types</h2>
        <button class="btn btn-primary" onclick="addEquipment()">
            <i class="fas fa-plus"></i> Add Equipment Type
        </button>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Category</th>
                <th>Description</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($equipment && $equipment->num_rows > 0): ?>
                <?php while($eq = $equipment->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $eq['id']; ?></td>
                    <td><strong><?php echo htmlspecialchars($eq['name']); ?></strong></td>
                    <td><?php echo htmlspecialchars($eq['category']); ?></td>
                    <td><?php echo htmlspecialchars($eq['description'] ?? ''); ?></td>
                    <td>
                        <div class="action-buttons">
                            <button class="action-btn edit" onclick="editEquipment(<?php echo $eq['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="action-btn view" onclick="viewEquipment(<?php echo $eq['id']; ?>)">
                                <i class="fas fa-eye"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" class="text-center">No equipment types found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function addEquipment() {
    alert('Add equipment type functionality');
}

function editEquipment(id) {
    alert('Edit equipment ' + id);
}

function viewEquipment(id) {
    alert('View equipment ' + id);
}
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>