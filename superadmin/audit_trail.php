<?php
/**
 * Audit Trail Page (Super Admin)
 * View all system changes and activities
 */

$page_title = 'Audit Trail';
$page_description = 'Complete history of all system changes';

require_once '../includes/auth.php';
requireRole('super_admin');

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 50;

// Filters
$filter_action = isset($_GET['action']) ? sanitize($_GET['action']) : '';
$filter_table = isset($_GET['table']) ? sanitize($_GET['table']) : '';
$filter_user = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build query
$query = "
    SELECT at.*, 
           CONCAT(u.firstname, ' ', u.lastname) as user_name,
           u.username
    FROM audit_trail at
    LEFT JOIN users u ON at.user_id = u.id
    WHERE 1=1
";

if ($filter_action) {
    $query .= " AND at.action = '$filter_action'";
}
if ($filter_table) {
    $query .= " AND at.table_name = '$filter_table'";
}
if ($filter_user) {
    $query .= " AND at.user_id = $filter_user";
}
if ($date_from) {
    $query .= " AND DATE(at.created_at) >= '$date_from'";
}
if ($date_to) {
    $query .= " AND DATE(at.created_at) <= '$date_to'";
}

$query .= " ORDER BY at.created_at DESC";

// Get paginated results
$audit_logs = paginate($query, $page, $per_page);

// Get unique actions for filter
$actions = $conn->query("SELECT DISTINCT action FROM audit_trail ORDER BY action");
$tables = $conn->query("SELECT DISTINCT table_name FROM audit_trail WHERE table_name IS NOT NULL ORDER BY table_name");
$users = $conn->query("SELECT id, username, firstname, lastname FROM users ORDER BY username");

// Build filter query string
function buildFilterQuery() {
    $params = [];
    if (!empty($_GET['action'])) $params[] = 'action=' . urlencode($_GET['action']);
    if (!empty($_GET['table'])) $params[] = 'table=' . urlencode($_GET['table']);
    if (!empty($_GET['user_id'])) $params[] = 'user_id=' . urlencode($_GET['user_id']);
    if (!empty($_GET['date_from'])) $params[] = 'date_from=' . urlencode($_GET['date_from']);
    if (!empty($_GET['date_to'])) $params[] = 'date_to=' . urlencode($_GET['date_to']);
    return !empty($params) ? '&' . implode('&', $params) : '';
}

include '../includes/header.php';
?>

<!-- Filters -->
<div class="filter-section">
    <h3>Filter Audit Trail</h3>
    <form method="GET" action="" class="filter-grid">
        <div class="form-group">
            <label>Action</label>
            <select name="action" class="form-control">
                <option value="">All Actions</option>
                <?php while($action = $actions->fetch_assoc()): ?>
                <option value="<?php echo $action['action']; ?>" 
                    <?php echo $filter_action == $action['action'] ? 'selected' : ''; ?>>
                    <?php echo $action['action']; ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Table</label>
            <select name="table" class="form-control">
                <option value="">All Tables</option>
                <?php while($table = $tables->fetch_assoc()): ?>
                <option value="<?php echo $table['table_name']; ?>" 
                    <?php echo $filter_table == $table['table_name'] ? 'selected' : ''; ?>>
                    <?php echo $table['table_name']; ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>User</label>
            <select name="user_id" class="form-control">
                <option value="">All Users</option>
                <?php while($user = $users->fetch_assoc()): ?>
                <option value="<?php echo $user['id']; ?>" 
                    <?php echo $filter_user == $user['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                </option>
                <?php endwhile; ?>
            </select>
        </div>
        
        <div class="form-group">
            <label>Date From</label>
            <input type="date" name="date_from" class="form-control" value="<?php echo $date_from; ?>">
        </div>
        
        <div class="form-group">
            <label>Date To</label>
            <input type="date" name="date_to" class="form-control" value="<?php echo $date_to; ?>">
        </div>
        
        <div class="form-group" style="display: flex; align-items: flex-end;">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <a href="?" class="btn btn-secondary" style="margin-left: 10px;">
                <i class="fas fa-times"></i> Clear
            </a>
        </div>
    </form>
</div>

<!-- Audit Trail Table -->
<div class="table-container">
    <div class="table-header">
        <h2>Audit Trail</h2>
        <div>
            <button class="btn btn-sm btn-secondary" onclick="exportAuditTrail()">
                <i class="fas fa-download"></i> Export
            </button>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Timestamp</th>
                <th>User</th>
                <th>Action</th>
                <th>Table</th>
                <th>Record ID</th>
                <th>IP Address</th>
                <th>Changes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($audit_logs['data']) > 0): ?>
                <?php foreach ($audit_logs['data'] as $log): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($log['created_at'])); ?></td>
                    <td>
                        <?php if ($log['user_name']): ?>
                            <strong><?php echo htmlspecialchars($log['user_name']); ?></strong>
                            <br><small><?php echo htmlspecialchars($log['username']); ?></small>
                        <?php else: ?>
                            <em>System</em>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php
                        $action_colors = [
                            'INSERT' => 'badge-success',
                            'UPDATE' => 'badge-warning',
                            'DELETE' => 'badge-danger',
                            'LOGIN' => 'badge-info'
                        ];
                        $badge_class = $action_colors[$log['action']] ?? 'badge-secondary';
                        ?>
                        <span class="badge <?php echo $badge_class; ?>">
                            <?php echo htmlspecialchars($log['action']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($log['table_name']); ?></td>
                    <td><?php echo $log['record_id'] ?: '-'; ?></td>
                    <td><?php echo htmlspecialchars($log['ip_address']); ?></td>
                    <td>
                        <?php if ($log['old_value'] || $log['new_value']): ?>
                            <button class="btn btn-sm btn-primary" onclick="viewChanges(<?php echo $log['id']; ?>)">
                                <i class="fas fa-code"></i> View
                            </button>
                        <?php else: ?>
                            -
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" class="text-center">No audit records found</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($audit_logs['total_pages'] > 1): ?>
    <div style="display: flex; justify-content: center; margin-top: 20px; gap: 5px;">
        <?php if ($page > 1): ?>
            <a href="?page=1<?php echo buildFilterQuery(); ?>" class="btn btn-sm btn-secondary">
                <i class="fas fa-angle-double-left"></i>
            </a>
            <a href="?page=<?php echo $page-1; ?><?php echo buildFilterQuery(); ?>" class="btn btn-sm btn-secondary">
                <i class="fas fa-angle-left"></i>
            </a>
        <?php endif; ?>
        
        <span style="padding: 8px 15px;">
            Page <?php echo $page; ?> of <?php echo $audit_logs['total_pages']; ?>
        </span>
        
        <?php if ($page < $audit_logs['total_pages']): ?>
            <a href="?page=<?php echo $page+1; ?><?php echo buildFilterQuery(); ?>" class="btn btn-sm btn-secondary">
                <i class="fas fa-angle-right"></i>
            </a>
            <a href="?page=<?php echo $audit_logs['total_pages']; ?><?php echo buildFilterQuery(); ?>" class="btn btn-sm btn-secondary">
                <i class="fas fa-angle-double-right"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Changes Modal -->
<div id="changesModal" class="modal">
    <div class="modal-content" style="max-width: 800px;">
        <div class="modal-header">
            <h2>Change Details</h2>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body" id="changesContent">
            <!-- Content loaded via AJAX -->
        </div>
    </div>
</div>

<script>
function viewChanges(logId) {
    ajaxRequest('/api/get_audit_changes.php?id=' + logId, 'GET', null, function(err, response) {
        if (!err && response) {
            let html = '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">';
            
            if (response.old_value) {
                html += '<div><h4>Old Values</h4><pre>' + JSON.stringify(JSON.parse(response.old_value), null, 2) + '</pre></div>';
            }
            
            if (response.new_value) {
                html += '<div><h4>New Values</h4><pre>' + JSON.stringify(JSON.parse(response.new_value), null, 2) + '</pre></div>';
            }
            
            html += '</div>';
            
            document.getElementById('changesContent').innerHTML = html;
            document.getElementById('changesModal').style.display = 'block';
        }
    });
}

function buildFilterQuery() {
    let params = new URLSearchParams(window.location.search);
    params.delete('page');
    let query = params.toString();
    return query ? '&' + query : '';
}

function exportAuditTrail() {
    let params = new URLSearchParams(window.location.search);
    window.location.href = '/api/export_audit.php?' + params.toString();
}
</script>

<?php include '../includes/footer.php'; ?>