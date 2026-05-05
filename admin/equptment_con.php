<?php
session_start();
$host = 'localhost';
$dbname = 'inventory_sys';
$username = 'root';
$password = '';

$root_path = dirname(__DIR__);
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';


try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle Add/Edit/Delete for Type of Equipment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['type_action'])) {
        if ($_POST['type_action'] == 'add_type') {
            $code = trim($_POST['type_code']);
            $name = trim($_POST['type_name']);
            
            $stmt = $pdo->prepare("INSERT INTO type_of_equipment (code, name) VALUES (?, ?)");
            $stmt->execute([$code, $name]);
            $_SESSION['message'] = "Type of Equipment added successfully";
            
        } elseif ($_POST['type_action'] == 'edit_type') {
            $id = $_POST['type_id'];
            $code = trim($_POST['type_code']);
            $name = trim($_POST['type_name']);
            
            $stmt = $pdo->prepare("UPDATE type_of_equipment SET code = ?, name = ? WHERE id = ?");
            $stmt->execute([$code, $name, $id]);
            $_SESSION['message'] = "Type of Equipment updated successfully";
            
        } elseif ($_POST['type_action'] == 'delete_type') {
            $id = $_POST['type_id'];
            
            // Check if has sub-types
            $check = $pdo->prepare("SELECT COUNT(*) FROM equipment_sub_type WHERE type_of_equipment_id = ?");
            $check->execute([$id]);
            if ($check->fetchColumn() > 0) {
                $_SESSION['error'] = "Cannot delete - this type has sub-types assigned";
            } else {
                $stmt = $pdo->prepare("DELETE FROM type_of_equipment WHERE id = ?");
                $stmt->execute([$id]);
                $_SESSION['message'] = "Type of Equipment deleted successfully";
            }
        }
    }
    
    // Handle Add/Edit/Delete for Equipment Sub-Type
    if (isset($_POST['subtype_action'])) {
        if ($_POST['subtype_action'] == 'add_subtype') {
            $code = trim($_POST['subtype_code']);
            $name = trim($_POST['subtype_name']);
            $type_id = $_POST['type_of_equipment_id'];
            
            $stmt = $pdo->prepare("INSERT INTO equipment_sub_type (code, name, type_of_equipment_id) VALUES (?, ?, ?)");
            $stmt->execute([$code, $name, $type_id]);
            $_SESSION['message'] = "Equipment Sub-Type added successfully";
            
        } elseif ($_POST['subtype_action'] == 'edit_subtype') {
            $id = $_POST['subtype_id'];
            $code = trim($_POST['subtype_code']);
            $name = trim($_POST['subtype_name']);
            $type_id = $_POST['type_of_equipment_id'];
            
            $stmt = $pdo->prepare("UPDATE equipment_sub_type SET code = ?, name = ?, type_of_equipment_id = ? WHERE id = ?");
            $stmt->execute([$code, $name, $type_id, $id]);
            $_SESSION['message'] = "Equipment Sub-Type updated successfully";
            
        } elseif ($_POST['subtype_action'] == 'delete_subtype') {
            $id = $_POST['subtype_id'];
            
            $stmt = $pdo->prepare("DELETE FROM equipment_sub_type WHERE id = ?");
            $stmt->execute([$id]);
            $_SESSION['message'] = "Equipment Sub-Type deleted successfully";
        }
    }
    
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Fetch all type_of_equipment
$types = $pdo->query("SELECT * FROM type_of_equipment ORDER BY code")->fetchAll(PDO::FETCH_ASSOC);

// Fetch all equipment_sub_type with parent type name
$subtypes = $pdo->query("
    SELECT s.*, t.code as parent_code, t.name as parent_name 
    FROM equipment_sub_type s
    LEFT JOIN type_of_equipment t ON s.type_of_equipment_id = t.id
    ORDER BY t.code, s.code
")->fetchAll(PDO::FETCH_ASSOC);



include INCLUDE_PATH . '/header.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Equipment Classification Management</title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Main CSS -->
    <link rel="stylesheet" href="../assets/css/style.css">
    
    <!-- Equipment Type CSS -->
    <link rel="stylesheet" href="../assets/css/equipment_type.css">

</head>
<?include INCLUDE_PATH . '/sidebar.php'; ?>
<body>
    <div class="container">
        <div class="header">
            <div>
                <h1> Equipment Classification Management</h1>
                <p>Manage Type of Equipment and Equipment Sub-Type</p>
            </div>
        </div>
        
        <div class="cards-container">
            <!-- Type of Equipment Card -->
            <div class="card">
                <div class="card-header">
                    <h2>Type of Equipment</h2>
                    <button class="btn-add" onclick="openTypeModal('add')">+ Add New</button>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Sub-Types Count</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($types as $type): ?>
                            <?php
                            $countStmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_sub_type WHERE type_of_equipment_id = ?");
                            $countStmt->execute([$type['id']]);
                            $subCount = $countStmt->fetchColumn();
                            ?>
                            <tr>
                                <td><span class="badge badge-primary"><?= htmlspecialchars($type['code']) ?></span></td>
                                <td><?= htmlspecialchars($type['name']) ?></td>
                                <td><?= $subCount ?></td>
                                <td>
                                    <button class="btn-edit" onclick="editType(<?= htmlspecialchars(json_encode($type)) ?>)">Edit</button>
                                    <button class="btn-delete" onclick="deleteType(<?= $type['id'] ?>, '<?= htmlspecialchars($type['name']) ?>')">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($types)): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 40px;">No Type of Equipment found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Equipment Sub-Type Card -->
            <div class="card">
                <div class="card-header">
                    <h2>🔧 Equipment Sub-Type</h2>
                    <button class="btn-add" onclick="openSubTypeModal('add')">+ Add New</button>
                </div>
                <div class="table-wrapper">
                    <table>
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Parent Type</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subtypes as $subtype): ?>
                            <tr>
                                <td><span class="badge badge-primary"><?= htmlspecialchars($subtype['code']) ?></span></td>
                                <td><?= htmlspecialchars($subtype['name']) ?></td>
                                <td><?= htmlspecialchars($subtype['parent_code'] ?? '-') ?> - <?= htmlspecialchars($subtype['parent_name'] ?? 'No Parent') ?></td>
                                <td>
                                    <button class="btn-edit" onclick="editSubType(<?= htmlspecialchars(json_encode($subtype)) ?>)">Edit</button>
                                    <button class="btn-delete" onclick="deleteSubType(<?= $subtype['id'] ?>, '<?= htmlspecialchars($subtype['name']) ?>')">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            <?php if (empty($subtypes)): ?>
                            <tr><td colspan="4" style="text-align: center; padding: 40px;">No Equipment Sub-Type found</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal for Type of Equipment -->
    <div id="typeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="typeModalTitle">Add Type of Equipment</h2>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="type_action" id="type_action" value="add_type">
                    <input type="hidden" name="type_id" id="type_id" value="">
                    
                    <div class="form-group">
                        <label>Code *</label>
                        <input type="text" name="type_code" id="type_code" required maxlength="2" placeholder="e.g., 01, 02, 03">
                        <div class="text-muted">2-digit code for the equipment type</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" name="type_name" id="type_name" required placeholder="e.g., Land, Buildings">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeTypeModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Modal for Equipment Sub-Type -->
    <div id="subTypeModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="subTypeModalTitle">Add Equipment Sub-Type</h2>
            </div>
            <form method="POST" action="">
                <div class="modal-body">
                    <input type="hidden" name="subtype_action" id="subtype_action" value="add_subtype">
                    <input type="hidden" name="subtype_id" id="subtype_id" value="">
                    
                    <div class="form-group">
                        <label>Code *</label>
                        <input type="text" name="subtype_code" id="subtype_code" required maxlength="2" placeholder="e.g., 01, 02">
                        <div class="text-muted">2-digit code for the sub-type</div>
                    </div>
                    
                    <div class="form-group">
                        <label>Name *</label>
                        <input type="text" name="subtype_name" id="subtype_name" required placeholder="e.g., Land, Office Equipment">
                    </div>
                    
                    <div class="form-group">
                        <label>Parent Type of Equipment *</label>
                        <select name="type_of_equipment_id" id="type_of_equipment_id" required>
                            <option value="">-- Select Parent Type --</option>
                            <?php foreach ($types as $type): ?>
                            <option value="<?= $type['id'] ?>">[<?= htmlspecialchars($type['code']) ?>] <?= htmlspecialchars($type['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-cancel" onclick="closeSubTypeModal()">Cancel</button>
                    <button type="submit" class="btn-submit">Save</button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Confirm Delete</h2>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete <strong id="deleteItemName"></strong>?</p>
                <p style="color: #dc3545; margin-top: 10px;">This action cannot be undone!</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeDeleteModal()">Cancel</button>
                <button type="button" class="btn-submit" style="background: #dc3545;" onclick="confirmDelete()">Delete</button>
            </div>
        </div>
    </div>
    
    <script>
        // Type of Equipment Modals
        function openTypeModal(action, data = null) {
            const modal = document.getElementById('typeModal');
            const title = document.getElementById('typeModalTitle');
            const actionInput = document.getElementById('type_action');
            const idInput = document.getElementById('type_id');
            const codeInput = document.getElementById('type_code');
            const nameInput = document.getElementById('type_name');
            
            if (action === 'add') {
                title.textContent = 'Add Type of Equipment';
                actionInput.value = 'add_type';
                idInput.value = '';
                codeInput.value = '';
                nameInput.value = '';
            } else if (action === 'edit' && data) {
                title.textContent = 'Edit Type of Equipment';
                actionInput.value = 'edit_type';
                idInput.value = data.id;
                codeInput.value = data.code;
                nameInput.value = data.name;
            }
            
            modal.style.display = 'block';
        }
        
        function closeTypeModal() {
            document.getElementById('typeModal').style.display = 'none';
        }
        
        function editType(data) {
            openTypeModal('edit', data);
        }
        
        // Equipment Sub-Type Modals
        function openSubTypeModal(action, data = null) {
            const modal = document.getElementById('subTypeModal');
            const title = document.getElementById('subTypeModalTitle');
            const actionInput = document.getElementById('subtype_action');
            const idInput = document.getElementById('subtype_id');
            const codeInput = document.getElementById('subtype_code');
            const nameInput = document.getElementById('subtype_name');
            const parentSelect = document.getElementById('type_of_equipment_id');
            
            if (action === 'add') {
                title.textContent = 'Add Equipment Sub-Type';
                actionInput.value = 'add_subtype';
                idInput.value = '';
                codeInput.value = '';
                nameInput.value = '';
                parentSelect.value = '';
            } else if (action === 'edit' && data) {
                title.textContent = 'Edit Equipment Sub-Type';
                actionInput.value = 'edit_subtype';
                idInput.value = data.id;
                codeInput.value = data.code;
                nameInput.value = data.name;
                parentSelect.value = data.type_of_equipment_id;
            }
            
            modal.style.display = 'block';
        }
        
        function closeSubTypeModal() {
            document.getElementById('subTypeModal').style.display = 'none';
        }
        
        function editSubType(data) {
            openSubTypeModal('edit', data);
        }
        
        // Delete functionality
        let deleteForm = null;
        let deleteId = null;
        let deleteName = '';
        let deleteType = '';
        
        function deleteType(id, name) {
            deleteId = id;
            deleteName = name;
            deleteType = 'type';
            document.getElementById('deleteItemName').textContent = name;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function deleteSubType(id, name) {
            deleteId = id;
            deleteName = name;
            deleteType = 'subtype';
            document.getElementById('deleteItemName').textContent = name;
            document.getElementById('deleteModal').style.display = 'block';
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            deleteId = null;
        }
        
        function confirmDelete() {
            if (deleteId) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                
                if (deleteType === 'type') {
                    form.innerHTML = `
                        <input type="hidden" name="type_action" value="delete_type">
                        <input type="hidden" name="type_id" value="${deleteId}">
                    `;
                } else {
                    form.innerHTML = `
                        <input type="hidden" name="subtype_action" value="delete_subtype">
                        <input type="hidden" name="subtype_id" value="${deleteId}">
                    `;
                }
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const typeModal = document.getElementById('typeModal');
            const subTypeModal = document.getElementById('subTypeModal');
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target == typeModal) closeTypeModal();
            if (event.target == subTypeModal) closeSubTypeModal();
            if (event.target == deleteModal) closeDeleteModal();
        }
        
        // Show messages
        <?php if (isset($_SESSION['message'])): ?>
        showAlert('<?= $_SESSION['message'] ?>', 'success');
        <?php unset($_SESSION['message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
        showAlert('<?= $_SESSION['error'] ?>', 'error');
        <?php unset($_SESSION['error']); ?>
        <?php endif; ?>
        
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.textContent = message;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
        }
    </script>
</body>
</html>