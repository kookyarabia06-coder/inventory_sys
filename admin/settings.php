<?php

$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Require admin or superadmin role
requireRole('admin' || 'superadmin' || 'supply');

$page_title = 'System Settings';
$page_description = 'Manage fund clusters and system configurations';

$message = '';
$error = '';

// Handle low stock threshold update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_threshold') {
    $low_stock_threshold = intval($_POST['low_stock_threshold']);
    
    if ($low_stock_threshold >= 0) {
        // Check if setting exists
        $check = $conn->query("SELECT id FROM system_settings WHERE setting_key = 'low_stock_threshold'");
        
        if ($check && $check->num_rows > 0) {
            $stmt = $conn->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ?, updated_at = CURRENT_TIMESTAMP WHERE setting_key = 'low_stock_threshold'");
            $stmt->bind_param("si", $low_stock_threshold, $_SESSION['user_id']);
        } else {
            $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value, description, updated_by) VALUES ('low_stock_threshold', ?, 'Low stock alert threshold quantity', ?)");
            $stmt->bind_param("si", $low_stock_threshold, $_SESSION['user_id']);
        }
        
        if ($stmt->execute()) {
            $message = "Low stock threshold updated successfully!";
        } else {
            $error = "Failed to update low stock threshold.";
        }
        $stmt->close();
    } else {
        $error = "Please enter a valid number.";
    }
}

// Handle fund cluster add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_fund') {
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    
    if (empty($code) || empty($name)) {
        $error = "Fund code and name are required.";
    } else {
        $stmt = $conn->prepare("INSERT INTO fund_cluster (code, name, description, status) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $code, $name, $description, $status);
        
        if ($stmt->execute()) {
            $message = "Fund cluster added successfully!";
        } else {
            $error = "Failed to add fund cluster. Code may already exist.";
        }
        $stmt->close();
    }
}

// Handle fund cluster edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_fund') {
    $id = intval($_POST['id']);
    $code = trim($_POST['code']);
    $name = trim($_POST['name']);
    $description = trim($_POST['description']);
    $status = $_POST['status'];
    
    if (empty($code) || empty($name)) {
        $error = "Fund code and name are required.";
    } else {
        $stmt = $conn->prepare("UPDATE fund_cluster SET code = ?, name = ?, description = ?, status = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $code, $name, $description, $status, $id);
        
        if ($stmt->execute()) {
            $message = "Fund cluster updated successfully!";
        } else {
            $error = "Failed to update fund cluster.";
        }
        $stmt->close();
    }
}

// Handle fund cluster delete
if (isset($_GET['delete_fund']) && is_numeric($_GET['delete_fund'])) {
    $id = intval($_GET['delete_fund']);
    
    $stmt = $conn->prepare("DELETE FROM fund_cluster WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        $message = "Fund cluster deleted successfully!";
    } else {
        $error = "Failed to delete fund cluster. It may be in use.";
    }
    $stmt->close();
}

// Get current low stock threshold
$low_stock_threshold = 5; // default
$result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold'");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $low_stock_threshold = intval($row['setting_value']);
}

// Get all fund clusters
$fund_clusters = $conn->query("SELECT * FROM fund_cluster ORDER BY code");

include INCLUDE_PATH . '/header.php';
?>

<style>
/* System Settings Page Styles */
.settings-container {
    display: flex;
    flex-direction: column;
    gap: 30px;
}

.settings-card {
    background: var(--white);
    border-radius: 12px;
    padding: 25px;
    box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
    border-left: 4px solid var(--primary);
    transition: transform 0.2s, box-shadow 0.2s;
}

.settings-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(107, 140, 255, 0.15);
}

.settings-card h2 {
    color: var(--primary);
    font-size: 20px;
    margin-bottom: 10px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-light);
}

.settings-card h2 i {
    color: var(--accent);
    margin-right: 10px;
}

.settings-card p {
    color: var(--text-secondary);
    margin-bottom: 20px;
    font-size: 14px;
}

/* Card header with button */
.card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-light);
}

.card-header h2 {
    margin: 0;
    padding: 0;
    border-bottom: none;
}

.card-header h2 i {
    color: var(--accent);
    margin-right: 10px;
}

.btn-open-modal {
    padding: 8px 20px;
    background-color: var(--accent);
    color: var(--text-primary);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-open-modal:hover {
    background-color: #e69eb0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(248, 176, 192, 0.3);
}

.form-group-settings {
    margin-bottom: 20px;
}

.form-group-settings label {
    display: block;
    margin-bottom: 8px;
    font-weight: 500;
    color: var(--text-primary);
    font-size: 14px;
}

.form-group-settings input[type="number"],
.form-group-settings input[type="text"],
.form-group-settings textarea,
.form-group-settings select {
    width: 100%;
    max-width: 400px;
    padding: 10px 12px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 14px;
    color: var(--text-primary);
    transition: all 0.3s;
    background-color: var(--white);
}

.form-group-settings input:focus,
.form-group-settings textarea:focus,
.form-group-settings select:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.form-group-settings small {
    display: block;
    margin-top: 5px;
    color: var(--text-muted);
    font-size: 12px;
}

.btn-save {
    padding: 10px 24px;
    background-color: var(--accent);
    color: var(--text-primary);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-save:hover {
    background-color: #e69eb0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(248, 176, 192, 0.3);
}

.btn-add {
    padding: 10px 24px;
    background-color: var(--accent);
    color: var(--text-primary);
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s;
}

.btn-add:hover {
    background-color: #e69eb0;
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(248, 176, 192, 0.3);
}

.btn-edit {
    padding: 5px 12px;
    background-color: var(--secondary);
    color: var(--text-light);
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    transition: all 0.2s;
    margin-right: 5px;
}

.btn-edit:hover {
    background-color: #7a9fe6;
    transform: translateY(-1px);
}

.btn-delete {
    padding: 5px 12px;
    background-color: var(--danger);
    color: var(--text-light);
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    text-decoration: none;
    display: inline-block;
    transition: all 0.2s;
}

.btn-delete:hover {
    opacity: 0.9;
    transform: translateY(-1px);
}

.btn-cancel {
    padding: 8px 16px;
    background-color: #6c757d;
    color: var(--text-light);
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-size: 14px;
    transition: all 0.2s;
    margin-right: 10px;
}

.btn-cancel:hover {
    background-color: #5a6268;
    transform: translateY(-1px);
}

.current-setting {
    margin-top: 15px;
    padding: 12px 15px;
    background-color: var(--light);
    border-radius: 8px;
    border-left: 3px solid var(--primary);
}

.current-setting strong {
    color: var(--primary);
}

.table-wrapper-settings {
    overflow-x: auto;
    margin-top: 20px;
}

.settings-table {
    width: 100%;
    border-collapse: collapse;
}

.settings-table thead tr {
    background: linear-gradient(to right, var(--light), var(--white));
    border-bottom: 2px solid var(--accent-light);
}

.settings-table th {
    padding: 12px 10px;
    text-align: left;
    color: var(--primary);
    font-weight: 600;
    font-size: 13px;
}

.settings-table td {
    padding: 12px 10px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
    font-size: 13px;
}

.settings-table tr:hover td {
    background-color: var(--light);
}

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
}

.status-active {
    background-color: var(--success-light);
    color: var(--success);
}

.status-inactive {
    background-color: #ffebee;
    color: var(--danger);
}

/* Modal Styles */
.modal-overlay {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(3px);
}

.modal-container {
    background-color: var(--white);
    margin: 5% auto;
    padding: 25px;
    border-radius: 12px;
    width: 500px;
    max-width: 90%;
    box-shadow: 0 10px 30px rgba(107, 140, 255, 0.2);
    animation: modalSlideIn 0.3s;
}

@keyframes modalSlideIn {
    from {
        transform: translateY(-30px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header-settings {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 10px;
    border-bottom: 2px solid var(--accent-light);
}

.modal-header-settings h3 {
    color: var(--primary);
    margin: 0;
    font-size: 20px;
}

.modal-header-settings h3 i {
    color: var(--accent);
    margin-right: 10px;
}

.modal-close {
    cursor: pointer;
    font-size: 28px;
    font-weight: bold;
    color: var(--text-muted);
    transition: color 0.2s;
}

.modal-close:hover {
    color: var(--accent);
}

.modal-footer {
    text-align: right;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid var(--border-light);
}

.empty-state {
    text-align: center;
    padding: 40px 20px;
    color: var(--text-muted);
}

.empty-state i {
    font-size: 48px;
    margin-bottom: 10px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .settings-card {
        padding: 20px;
    }
    
    .modal-container {
        margin: 20% auto;
        padding: 20px;
    }
    
    .form-group-settings input[type="number"],
    .form-group-settings input[type="text"],
    .form-group-settings textarea,
    .form-group-settings select {
        max-width: 100%;
    }
    
    .settings-table th,
    .settings-table td {
        padding: 8px 6px;
        font-size: 12px;
    }
    
    .btn-edit, .btn-delete {
        padding: 4px 8px;
        font-size: 11px;
    }
    
    .card-header {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
    }
}
</style>

<div class="settings-container">
    <!-- Alert Messages -->
    <?php if ($message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($message); ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <!-- Low Stock Alert Settings Card -->
    <div class="settings-card">
        <h2><i class="fas fa-bell"></i> Low Stock Alert Settings</h2>
        <p>Set the quantity threshold that determines when an item is considered "Low Stock".</p>
        
        <form method="POST" action="">
            <input type="hidden" name="action" value="update_threshold">
            
            <div class="form-group-settings">
                <label for="low_stock_threshold">Low Stock Threshold:</label>
                <input type="number" name="low_stock_threshold" id="low_stock_threshold" 
                       value="<?php echo $low_stock_threshold; ?>" min="0">
                <small>Items with quantity &lt;= this number will appear in Low Stock alerts.</small>
            </div>
            
            <div>
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Save Threshold
                </button>
            </div>
        </form>
        
        <div class="current-setting">
            <i class="fas fa-info-circle"></i> <strong>Current Setting:</strong> Items with quantity of 
            <strong><?php echo $low_stock_threshold; ?></strong> or less are flagged as Low Stock.
        </div>
    </div>

    <!-- Fund Clusters List Card with Add Button -->
    <div class="settings-card">
        <div class="card-header">
            <h2><i class="fas fa-list"></i> Fund Clusters List</h2>
            <button type="button" onclick="openAddModal()" class="btn-open-modal">
                <i class="fas fa-plus"></i> Add Fund Cluster
            </button>
        </div>
        
        <?php if ($fund_clusters && $fund_clusters->num_rows > 0): ?>
        <div class="table-wrapper-settings">
            <table class="settings-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($fund = $fund_clusters->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $fund['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($fund['code']); ?></strong></td>
                        <td><?php echo htmlspecialchars($fund['name']); ?></td>
                        <td><?php echo htmlspecialchars($fund['description']); ?></td>
                        <td>
                            <span class="status-badge <?php echo $fund['status'] == 'active' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo ucfirst($fund['status']); ?>
                            </span>
                        </td>
                        <td><?php echo date('Y-m-d', strtotime($fund['date_created'])); ?></td>
                        <td>
                            <button onclick="openEditModal(<?php echo htmlspecialchars(json_encode($fund)); ?>)" class="btn-edit">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <a href="?delete_fund=<?php echo $fund['id']; ?>" 
                               onclick="return confirm('Are you sure you want to delete fund cluster: <?php echo htmlspecialchars($fund['code']); ?> - <?php echo htmlspecialchars($fund['name']); ?>?')"
                               class="btn-delete">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <p>No fund clusters found. Click the "Add Fund Cluster" button to create one.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Fund Modal -->
<div id="addModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header-settings">
            <h3><i class="fas fa-plus-circle"></i> Add New Fund Cluster</h3>
            <span class="modal-close" onclick="closeAddModal()">&times;</span>
        </div>
        
        <form method="POST" action="" id="addForm">
            <input type="hidden" name="action" value="add_fund">
            
            <div class="form-group-settings">
                <label for="add_code">Code: <span style="color: var(--danger);">*</span></label>
                <input type="text" name="code" id="add_code" required>
                <small>Example: RAF, IGF, FUND_151, TRUST</small>
            </div>
            
            <div class="form-group-settings">
                <label for="add_name">Name: <span style="color: var(--danger);">*</span></label>
                <input type="text" name="name" id="add_name" required>
                <small>Example: Regular Agency Funds (RAF)</small>
            </div>
            
            <div class="form-group-settings">
                <label for="add_description">Description:</label>
                <textarea name="description" id="add_description" rows="3"></textarea>
                <small>Optional description for this fund cluster.</small>
            </div>
            
            <div class="form-group-settings">
                <label for="add_status">Status:</label>
                <select name="status" id="add_status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeAddModal()" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Add Fund Cluster
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header-settings">
            <h3><i class="fas fa-edit"></i> Edit Fund Cluster</h3>
            <span class="modal-close" onclick="closeEditModal()">&times;</span>
        </div>
        
        <form method="POST" action="" id="editForm">
            <input type="hidden" name="action" value="edit_fund">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="form-group-settings">
                <label>Code: <span style="color: var(--danger);">*</span></label>
                <input type="text" name="code" id="edit_code" required>
            </div>
            
            <div class="form-group-settings">
                <label>Name: <span style="color: var(--danger);">*</span></label>
                <input type="text" name="name" id="edit_name" required>
            </div>
            
            <div class="form-group-settings">
                <label>Description:</label>
                <textarea name="description" id="edit_description" rows="3"></textarea>
            </div>
            
            <div class="form-group-settings">
                <label>Status:</label>
                <select name="status" id="edit_status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            
            <div class="modal-footer">
                <button type="button" onclick="closeEditModal()" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openAddModal() {
    document.getElementById('addModal').style.display = 'block';
    // Clear form fields
    document.getElementById('add_code').value = '';
    document.getElementById('add_name').value = '';
    document.getElementById('add_description').value = '';
    document.getElementById('add_status').value = 'active';
}

function closeAddModal() {
    document.getElementById('addModal').style.display = 'none';
}

function openEditModal(fund) {
    document.getElementById('edit_id').value = fund.id;
    document.getElementById('edit_code').value = fund.code;
    document.getElementById('edit_name').value = fund.name;
    document.getElementById('edit_description').value = fund.description || '';
    document.getElementById('edit_status').value = fund.status;
    document.getElementById('editModal').style.display = 'block';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// Close modals when clicking outside
window.onclick = function(event) {
    var addModal = document.getElementById('addModal');
    var editModal = document.getElementById('editModal');
    
    if (event.target == addModal) {
        addModal.style.display = 'none';
    }
    if (event.target == editModal) {
        editModal.style.display = 'none';
    }
}
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>