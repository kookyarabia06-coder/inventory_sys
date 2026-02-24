<?php
/**
 * User Dashboard
 * End-user view showing their issued items and available inventory
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Require user role
requireRole('user');

// Get current user
$currentUser = getCurrentUser();
if (!$currentUser) {
    // If user not found, logout
    header('Location: ' . SITE_URL . '/logout');
    exit();
}

$user_id = (int)$currentUser['id']; // Cast to integer for security

$page_title = 'My Dashboard';
$page_description = 'Overview of your issued items and available inventory';

// Get user's issued items count with error handling
$issued_count = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM user_inventory WHERE user_id = $user_id AND status = 'active'");
if ($result && $row = $result->fetch_assoc()) {
    $issued_count = $row['count'];
}

// Get overdue items count
$overdue_count = 0;
$result = $conn->query("
    SELECT COUNT(*) as count 
    FROM equipment_issuance ei
    JOIN user_inventory ui ON ei.id = ui.issuance_id
    WHERE ui.user_id = $user_id 
    AND ei.status = 'issued' 
    AND ei.expected_return < CURDATE()
");
if ($result && $row = $result->fetch_assoc()) {
    $overdue_count = $row['count'];
}

// Get total items ever issued to user
$total_issued = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM equipment_issuance WHERE issued_to = $user_id");
if ($result && $row = $result->fetch_assoc()) {
    $total_issued = $row['count'];
}

// Get user's currently issued items
$current_items = $conn->query("
    SELECT 
        ui.*,
        i.article_name,
        i.property_no,
        i.description,
        i.uom,
        i.unit_value,
        ei.issued_date,
        ei.expected_return,
        ei.purpose,
        ei.condition_on_issue,
        CONCAT(u.firstname, ' ', u.lastname) as issued_by_name
    FROM user_inventory ui
    JOIN inventory i ON ui.inventory_id = i.id
    JOIN equipment_issuance ei ON ui.issuance_id = ei.id
    JOIN users u ON ei.issued_by = u.id
    WHERE ui.user_id = $user_id AND ui.status = 'active'
    ORDER BY ei.expected_return ASC
");

if (!$current_items) {
    // If query fails, create an empty result set
    $current_items = $conn->query("SELECT * FROM user_inventory WHERE 1=0");
}

// Get available inventory (items in stock)
$available_items = $conn->query("
    SELECT i.*, e.name as equipment_name, s.name as section_name
    FROM inventory i
    LEFT JOIN equipment e ON i.equipment_id = e.id
    LEFT JOIN sections s ON i.section_id = s.id
    WHERE i.qty_physical_count > 0
    ORDER BY i.article_name
    LIMIT 10
");

if (!$available_items) {
    $available_items = $conn->query("SELECT * FROM inventory WHERE 1=0");
}

// Get recent activity for this user
$recent_activity = $conn->query("
    SELECT al.*, i.article_name
    FROM activity_log al
    LEFT JOIN inventory i ON al.item_id = i.id
    WHERE al.user_id = $user_id
    ORDER BY al.date_created DESC
    LIMIT 5
");

if (!$recent_activity) {
    $recent_activity = $conn->query("SELECT * FROM activity_log WHERE 1=0");
}

// Get total available items count
$total_available = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE qty_physical_count > 0");
if ($result && $row = $result->fetch_assoc()) {
    $total_available = $row['count'];
}

include INCLUDE_PATH . '/header.php';
?>

<!-- Welcome Banner -->
<div class="profile-header" style="margin-bottom: 30px; background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 20px;">
        <div>
            <h1 style="color: white; font-size: 32px; margin-bottom: 10px;">
                Welcome back, <?php echo htmlspecialchars($currentUser['firstname'] . ' ' . $currentUser['lastname']); ?>!
            </h1>
            <p style="color: rgba(255,255,255,0.9); font-size: 16px;">
                Here's what's happening with your inventory items today.
            </p>
        </div>
        <div class="user-avatar" style="width: 80px; height: 80px; font-size: 40px; border-color: white; background: rgba(255,255,255,0.2);">
            <?php if (!empty($currentUser['avatar']) && file_exists(UPLOAD_PATH . '/avatars/' . $currentUser['avatar'])): ?>
                <img src="<?php echo SITE_URL; ?>/uploads/avatars/<?php echo $currentUser['avatar']; ?>" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
            <?php else: ?>
                <i class="fas fa-user"></i>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Statistics Cards -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-box"></i>
        </div>
        <h3>Currently Issued</h3>
        <div class="card-value"><?php echo $issued_count; ?></div>
        <div class="card-label">Items in your possession</div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-clock"></i>
        </div>
        <h3>Overdue Items</h3>
        <div class="card-value <?php echo $overdue_count > 0 ? 'text-danger' : ''; ?>">
            <?php echo $overdue_count; ?>
        </div>
        <div class="card-label">Need to return</div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-history"></i>
        </div>
        <h3>Total Issued</h3>
        <div class="card-value"><?php echo $total_issued; ?></div>
        <div class="card-label">All time issuances</div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <h3>Available Items</h3>
        <div class="card-value"><?php echo $total_available; ?></div>
        <div class="card-label">Items in stock</div>
    </div>
</div>

<!-- Main Content Grid -->
<div class="stats-grid">
    <!-- Currently Issued Items -->
    <div class="stat-chart" style="grid-column: span 2;">
        <div class="table-header">
            <h3><i class="fas fa-clipboard-list"></i> Items Currently Issued to You</h3>
            <a href="<?php echo SITE_URL; ?>/user/my_issued_items" class="btn btn-sm btn-primary">View All</a>
        </div>
        
        <?php if ($current_items && $current_items->num_rows > 0): ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Property No.</th>
                            <th>Quantity</th>
                            <th>Issue Date</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($item = $current_items->fetch_assoc()): 
                            $is_overdue = strtotime($item['expected_return']) < time();
                        ?>
                        <tr class="<?php echo $is_overdue ? 'stock-alert-row' : ''; ?>">
                            <td>
                                <strong><?php echo htmlspecialchars($item['article_name']); ?></strong>
                                <br><small><?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 50)) . '...'; ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?></td>
                            <td><?php echo $item['quantity_assigned'] . ' ' . ($item['uom'] ?? ''); ?></td>
                            <td><?php echo formatDate($item['issued_date']); ?></td>
                            <td>
                                <?php echo formatDate($item['expected_return']); ?>
                                <?php if ($is_overdue): ?>
                                    <br><span class="badge badge-danger">Overdue</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php 
                                if ($is_overdue) {
                                    echo '<span class="badge badge-danger">Overdue</span>';
                                } else {
                                    echo '<span class="badge badge-success">Active</span>';
                                }
                                ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="viewItemDetails(<?php echo $item['inventory_id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-success" onclick="requestExtension(<?php echo $item['issuance_id']; ?>)">
                                    <i class="fas fa-clock"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-box-open"></i>
                <h3>No Items Issued</h3>
                <p>You don't have any items currently issued to you.</p>
                <a href="<?php echo SITE_URL; ?>/user/view_inventory" class="btn btn-primary">
                    <i class="fas fa-search"></i> Browse Inventory
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Quick Actions & Recent Activity -->
    <div class="stat-chart">
        <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
        <div style="display: flex; flex-direction: column; gap: 10px;">
            <a href="<?php echo SITE_URL; ?>/user/view_inventory" class="btn btn-primary" style="justify-content: flex-start;">
                <i class="fas fa-search"></i> Browse Available Items
            </a>
            <a href="<?php echo SITE_URL; ?>/user/my_issued_items" class="btn btn-secondary" style="justify-content: flex-start;">
                <i class="fas fa-clipboard-list"></i> View My Issued Items
            </a>
            <a href="<?php echo SITE_URL; ?>/profile" class="btn btn-secondary" style="justify-content: flex-start;">
                <i class="fas fa-user-circle"></i> Update Profile
            </a>
            <button class="btn btn-secondary" style="justify-content: flex-start;" onclick="reportIssue()">
                <i class="fas fa-exclamation-triangle"></i> Report an Issue
            </button>
        </div>
        
        <h3 style="margin-top: 30px;"><i class="fas fa-history"></i> Recent Activity</h3>
        <div class="activity-list">
            <?php if ($recent_activity && $recent_activity->num_rows > 0): ?>
                <?php while($activity = $recent_activity->fetch_assoc()): ?>
                <div class="activity-item">
                    <div class="activity-icon">
                        <?php
                        $icons = [
                            'Issue' => 'fa-hand-holding',
                            'Return' => 'fa-undo',
                            'Request' => 'fa-paper-plane',
                            'View' => 'fa-eye',
                            'Login' => 'fa-sign-in-alt',
                            'Logout' => 'fa-sign-out-alt'
                        ];
                        $icon = $icons[$activity['action']] ?? 'fa-circle';
                        ?>
                        <i class="fas <?php echo $icon; ?>"></i>
                    </div>
                    <div class="activity-content">
                        <div class="activity-title">
                            <strong><?php echo htmlspecialchars($activity['action']); ?></strong>
                            <?php if (!empty($activity['article_name'])): ?>
                                - <?php echo htmlspecialchars($activity['article_name']); ?>
                            <?php endif; ?>
                        </div>
                        <div class="activity-time">
                            <i class="far fa-clock"></i> 
                            <?php echo formatDate($activity['date_created']); ?>
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            <?php else: ?>
                <p class="text-center">No recent activity</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Available Items Carousel -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-boxes"></i> Available Items You Might Need</h2>
        <a href="<?php echo SITE_URL; ?>/user/view_inventory" class="btn btn-sm btn-primary">View All</a>
    </div>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
        <?php if ($available_items && $available_items->num_rows > 0): ?>
            <?php while($item = $available_items->fetch_assoc()): ?>
            <div class="card" style="padding: 15px; cursor: pointer;" onclick="viewItemDetails(<?php echo $item['id']; ?>)">
                <div style="display: flex; justify-content: space-between; align-items: start;">
                    <div>
                        <h4 style="color: var(--primary); margin-bottom: 5px;">
                            <?php echo htmlspecialchars($item['article_name']); ?>
                        </h4>
                        <p style="font-size: 12px; color: #666;">
                            <?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?>
                        </p>
                    </div>
                    <span class="badge badge-success">Available</span>
                </div>
                <p style="font-size: 13px; margin: 10px 0;">
                    <?php echo htmlspecialchars(substr($item['description'] ?? '', 0, 60)) . '...'; ?>
                </p>
                <div style="display: flex; justify-content: space-between; font-size: 12px; color: #666;">
                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($item['section_name'] ?? 'N/A'); ?></span>
                    <span><i class="fas fa-cubes"></i> Stock: <?php echo $item['qty_physical_count']; ?></span>
                </div>
                <button class="btn btn-sm btn-primary" style="width: 100%; margin-top: 10px;" 
                        onclick="event.stopPropagation(); requestItem(<?php echo $item['id']; ?>)">
                    <i class="fas fa-hand-holding"></i> Request Item
                </button>
            </div>
            <?php endwhile; ?>
        <?php else: ?>
            <div class="empty-state" style="grid-column: 1/-1;">
                <i class="fas fa-box-open"></i>
                <p>No items available at the moment</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Request Extension Modal -->
<div id="extensionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Request Extension</h2>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="extensionForm" onsubmit="submitExtension(event)">
                <input type="hidden" id="extension_issuance_id" name="issuance_id">
                
                <div class="form-group">
                    <label>Current Due Date</label>
                    <p id="current_due_date" style="font-weight: bold; padding: 10px; background: var(--light); border-radius: 6px;"></p>
                </div>
                
                <div class="form-group">
                    <label for="new_return_date">New Return Date *</label>
                    <input type="date" class="form-control" id="new_return_date" name="new_return_date" 
                           min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="extension_reason">Reason for Extension *</label>
                    <textarea class="form-control" id="extension_reason" name="reason" rows="3" required></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('extensionModal')">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Report Issue Modal -->
<div id="reportModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Report an Issue</h2>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="reportForm" onsubmit="submitReport(event)">
                <div class="form-group">
                    <label for="issue_item">Related Item (Optional)</label>
                    <select class="form-control" id="issue_item" name="item_id">
                        <option value="">-- Select Item --</option>
                        <?php 
                        $user_items = $conn->query("
                            SELECT i.id, i.article_name, i.property_no
                            FROM user_inventory ui
                            JOIN inventory i ON ui.inventory_id = i.id
                            WHERE ui.user_id = $user_id AND ui.status = 'active'
                        ");
                        if ($user_items) {
                            while($item = $user_items->fetch_assoc()):
                        ?>
                        <option value="<?php echo $item['id']; ?>">
                            <?php echo htmlspecialchars($item['article_name'] . ' (' . $item['property_no'] . ')'); ?>
                        </option>
                        <?php 
                            endwhile;
                        }
                        ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="issue_type">Issue Type *</label>
                    <select class="form-control" id="issue_type" name="issue_type" required>
                        <option value="">-- Select Type --</option>
                        <option value="damaged">Damaged Item</option>
                        <option value="lost">Lost Item</option>
                        <option value="wrong_item">Wrong Item Received</option>
                        <option value="missing_parts">Missing Parts</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="issue_description">Description *</label>
                    <textarea class="form-control" id="issue_description" name="description" rows="4" required></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('reportModal')">Cancel</button>
                    <button type="submit" class="btn btn-danger">Submit Report</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Global functions for modals
function viewItemDetails(itemId) {
    ajaxRequest('<?php echo SITE_URL; ?>/api/get_item_details.php?id=' + itemId, 'GET', null, function(err, response) {
        if (!err && response) {
            let content = `
                <div style="margin-bottom: 20px;">
                    <h3>${response.article_name}</h3>
                    <table style="width: 100%;">
                        <tr><td><strong>Property No:</strong></td><td>${response.property_no || 'N/A'}</td></tr>
                        <tr><td><strong>Description:</strong></td><td>${response.description || 'N/A'}</td></tr>
                        <tr><td><strong>Category:</strong></td><td>${response.category || 'N/A'}</td></tr>
                        <tr><td><strong>Equipment Type:</strong></td><td>${response.equipment_name || 'N/A'}</td></tr>
                        <tr><td><strong>Unit Value:</strong></td><td>${formatCurrency(response.unit_value || 0)}</td></tr>
                        <tr><td><strong>Available Quantity:</strong></td><td>${response.qty_physical_count || 0} ${response.uom || ''}</td></tr>
                        <tr><td><strong>Location:</strong></td><td>${response.section_name || 'N/A'}</td></tr>
                        <tr><td><strong>Condition:</strong></td><td>${response.condition_text || 'Good'}</td></tr>
                    </table>
                </div>
            `;
            showModal('Item Details', content);
        } else {
            alert('Error loading item details');
        }
    });
}

function requestItem(itemId) {
    // Redirect to view inventory with pre-selected item
    window.location.href = '<?php echo SITE_URL; ?>/user/view_inventory?request=' + itemId;
}

function requestExtension(issuanceId) {
    ajaxRequest('<?php echo SITE_URL; ?>/api/get_issuance_details.php?id=' + issuanceId, 'GET', null, function(err, response) {
        if (!err && response) {
            document.getElementById('extension_issuance_id').value = issuanceId;
            document.getElementById('current_due_date').textContent = formatDate(response.expected_return);
            document.getElementById('extensionModal').style.display = 'block';
        } else {
            alert('Error loading issuance details');
        }
    });
}

function submitExtension(e) {
    e.preventDefault();
    
    let formData = {
        issuance_id: document.getElementById('extension_issuance_id').value,
        new_return_date: document.getElementById('new_return_date').value,
        reason: document.getElementById('extension_reason').value
    };
    
    ajaxRequest('<?php echo SITE_URL; ?>/api/request_extension.php', 'POST', formData, function(err, response) {
        if (!err && response && response.success) {
            alert('Extension request submitted successfully!');
            closeModal('extensionModal');
            location.reload();
        } else {
            alert('Error: ' + (err ? err.message : (response ? response.message : 'Failed to submit request')));
        }
    });
}

function reportIssue() {
    document.getElementById('reportModal').style.display = 'block';
}

function submitReport(e) {
    e.preventDefault();
    
    let formData = {
        item_id: document.getElementById('issue_item').value,
        issue_type: document.getElementById('issue_type').value,
        description: document.getElementById('issue_description').value
    };
    
    ajaxRequest('<?php echo SITE_URL; ?>/api/report_issue.php', 'POST', formData, function(err, response) {
        if (!err && response && response.success) {
            alert('Issue reported successfully! An administrator will review your report.');
            closeModal('reportModal');
            document.getElementById('reportForm').reset();
        } else {
            alert('Error: ' + (err ? err.message : (response ? response.message : 'Failed to submit report')));
        }
    });
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

// Helper functions
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    let date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function showModal(title, content) {
    // Create modal dynamically if it doesn't exist
    let modalId = 'dynamic-modal';
    let modal = document.getElementById(modalId);
    
    if (!modal) {
        modal = document.createElement('div');
        modal.id = modalId;
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modal-title"></h2>
                    <span class="modal-close" onclick="closeModal('${modalId}')">&times;</span>
                </div>
                <div class="modal-body" id="modal-body"></div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    document.getElementById('modal-title').textContent = title;
    document.getElementById('modal-body').innerHTML = content;
    modal.style.display = 'block';
}

function ajaxRequest(url, method, data, callback) {
    let xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                try {
                    let response = JSON.parse(xhr.responseText);
                    callback(null, response);
                } catch (e) {
                    callback(e, null);
                }
            } else {
                callback(new Error('Request failed with status ' + xhr.status), null);
            }
        }
    };
    
    xhr.send(data ? JSON.stringify(data) : null);
}
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>