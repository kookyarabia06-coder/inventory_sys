<?php
/**
 * Locations Page (Admin)
 * Manage buildings, departments, and sections
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

requireRole('admin');

$page_title = 'Locations';
$page_description = 'Manage buildings, departments, and sections';

// Get all buildings
$buildings = $conn->query("SELECT * FROM buildings ORDER BY name");

include INCLUDE_PATH . '/header.php';
?>

<div class="stats-grid" style="grid-template-columns: repeat(3, 1fr);">
    <!-- Buildings -->
    <div class="stat-chart">
        <div class="table-header">
            <h3><i class="fas fa-building"></i> Buildings</h3>
            <button class="btn btn-sm btn-primary" onclick="addBuilding()">
                <i class="fas fa-plus"></i> Add
            </button>
        </div>
        
        <table style="width: 100%;">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Floors</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($buildings && $buildings->num_rows > 0): ?>
                    <?php while($b = $buildings->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($b['name']); ?></td>
                        <td><?php echo $b['floor']; ?></td>
                        <td>
                            <button class="action-btn edit" onclick="editBuilding(<?php echo $b['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">No buildings found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Departments -->
    <div class="stat-chart">
        <div class="table-header">
            <h3><i class="fas fa-sitemap"></i> Departments</h3>
            <button class="btn btn-sm btn-primary" onclick="addDepartment()">
                <i class="fas fa-plus"></i> Add
            </button>
        </div>
        
        <?php
        $departments = $conn->query("
            SELECT d.*, b.name as building_name 
            FROM departments d
            LEFT JOIN buildings b ON d.building_id = b.id
            ORDER BY b.name, d.name
        ");
        ?>
        
        <table style="width: 100%;">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Building</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($departments && $departments->num_rows > 0): ?>
                    <?php while($d = $departments->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($d['name']); ?></td>
                        <td><?php echo htmlspecialchars($d['building_name'] ?? 'N/A'); ?></td>
                        <td>
                            <button class="action-btn edit" onclick="editDepartment(<?php echo $d['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">No departments found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Sections -->
    <div class="stat-chart">
        <div class="table-header">
            <h3><i class="fas fa-layer-group"></i> Sections</h3>
            <button class="btn btn-sm btn-primary" onclick="addSection()">
                <i class="fas fa-plus"></i> Add
            </button>
        </div>
        
        <?php
        $sections = $conn->query("
            SELECT s.*, d.name as department_name 
            FROM sections s
            LEFT JOIN departments d ON s.department_id = d.id
            ORDER BY d.name, s.name
        ");
        ?>
        
        <table style="width: 100%;">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Department</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($sections && $sections->num_rows > 0): ?>
                    <?php while($s = $sections->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($s['name']); ?></td>
                        <td><?php echo htmlspecialchars($s['department_name'] ?? 'N/A'); ?></td>
                        <td>
                            <button class="action-btn edit" onclick="editSection(<?php echo $s['id']; ?>)">
                                <i class="fas fa-edit"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" class="text-center">No sections found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function addBuilding() {
    // Implement add building modal
    alert('Add building functionality');
}

function editBuilding(id) {
    alert('Edit building ' + id);
}

function addDepartment() {
    alert('Add department functionality');
}

function editDepartment(id) {
    alert('Edit department ' + id);
}

function addSection() {
    alert('Add section functionality');
}

function editSection(id) {
    alert('Edit section ' + id);
}
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>