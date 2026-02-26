<?php
/**
 * My Issued Items Page (End-User)
 * Shows all items issued to the current user
 */

$page_title = 'My Issued Items';
$page_description = 'View all items currently issued to you';

require_once '../includes/auth.php';
requireLogin();
requireRole('user');

$user_id = $_SESSION['user_id'];

// Get issued items for current user
$query = "
    SELECT 
        ui.*,
        i.article_name,
        i.property_no,
        i.description as item_description,
        i.unit_value,
        i.uom,
        ei.issued_date,
        ei.expected_return,
        ei.purpose,
        ei.condition_on_issue,
        ei.status as issuance_status,
        CONCAT(u.firstname, ' ', u.lastname) as issued_by_name
    FROM user_inventory ui
    JOIN inventory i ON ui.inventory_id = i.id
    LEFT JOIN equipment_issuance ei ON ui.issuance_id = ei.id
    LEFT JOIN users u ON ei.issued_by = u.id
    WHERE ui.user_id = $user_id AND ui.status = 'active'
    ORDER BY 
        CASE 
            WHEN ei.expected_return < CURDATE() THEN 0 
            ELSE 1 
        END,
        ei.expected_return ASC
";

$result = $conn->query($query);

// Get overdue count
$overdue_count = 0;
$overdue_result = $conn->query("
    SELECT COUNT(*) as count 
    FROM equipment_issuance ei
    JOIN user_inventory ui ON ei.id = ui.issuance_id
    WHERE ui.user_id = $user_id 
    AND ui.status = 'active'
    AND ei.expected_return IS NOT NULL 
    AND ei.expected_return < CURDATE()
");
if ($overdue_result) {
    $overdue_count = $overdue_result->fetch_assoc()['count'];
}

// Get issuance history
$history_query = "
    SELECT 
        ei.*,
        i.article_name,
        i.property_no,
        CONCAT(u.firstname, ' ', u.lastname) as issued_to_name,
        CONCAT(ub.firstname, ' ', ub.lastname) as issued_by_name
    FROM equipment_issuance ei
    JOIN inventory i ON ei.inventory_id = i.id
    JOIN users u ON ei.issued_to = u.id
    JOIN users ub ON ei.issued_by = ub.id
    WHERE ei.issued_to = $user_id AND ei.status != 'issued'
    ORDER BY ei.issued_date DESC
    LIMIT 20
";

$history_result = $conn->query($history_query);

include '../includes/header.php';
?>

<!-- Dashboard Cards -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-box"></i>
        </div>
        <h3>Total Items Issued</h3>
        <?php
        $total_items = $conn->query("
            SELECT COUNT(*) as count FROM user_inventory 
            WHERE user_id = $user_id AND status = 'active'
        ")->fetch_assoc();
        ?>
        <div class="card-value"><?php echo $total_items['count'] ?? 0; ?></div>
        <div class="card-label">Currently in your possession</div>
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
        <h3>Total History</h3>
        <?php
        $total = $conn->query("
            SELECT COUNT(*) as count FROM equipment_issuance WHERE issued_to = $user_id
        ")->fetch_assoc();
        ?>
        <div class="card-value"><?php echo $total['count'] ?? 0; ?></div>
        <div class="card-label">All time issuances</div>
    </div>
</div>

<!-- Currently Issued Items -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-clipboard-list"></i> Currently Issued Items</h2>
        <div class="search-box" style="width: 300px;">
            <input type="text" id="searchIssued" placeholder="Search items...">
            <button onclick="searchTable('searchIssued', 'issuedTable')">
                <i class="fas fa-search"></i>
            </button>
        </div>
    </div>
    
    <table id="issuedTable">
        <thead>
            <tr>
                <th>Item</th>
                <th>Property No.</th>
                <th>Quantity</th>
                <th>Issue Date</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): 
                    $is_overdue = false;
                    $due_date = 'Not Set';
                    $due_date_class = '';
                    
                    if (!empty($row['expected_return']) && $row['expected_return'] != '0000-00-00') {
                        $due_date = date('M d, Y', strtotime($row['expected_return']));
                        $is_overdue = strtotime($row['expected_return']) < time();
                        $due_date_class = $is_overdue ? 'text-danger' : '';
                    }
                ?>
                <tr class="<?php echo $is_overdue ? 'stock-alert-row' : ''; ?>">
                    <td>
                        <strong><?php echo htmlspecialchars($row['article_name']); ?></strong>
                        <?php if (!empty($row['item_description'])): ?>
                        <br><small><?php echo htmlspecialchars(substr($row['item_description'], 0, 50)) . '...'; ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['property_no'] ?? 'N/A'); ?></td>
                    <td><?php echo $row['quantity_assigned'] . ' ' . ($row['uom'] ?? ''); ?></td>
                    <td><?php echo !empty($row['issued_date']) ? date('M d, Y', strtotime($row['issued_date'])) : 'N/A'; ?></td>
                    <td class="<?php echo $due_date_class; ?>">
                        <?php echo $due_date; ?>
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
                        <div class="action-buttons">
                            <button class="action-btn view" onclick="viewItemDetails(<?php echo $row['inventory_id']; ?>)" title="View Details">
                                <i class="fas fa-eye"></i>
                            </button>
                            <button class="action-btn edit" onclick="requestExtension(<?php echo $row['issuance_id']; ?>)" title="Request Extension">
                                <i class="fas fa-clock"></i>
                            </button>
                        </div>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px;">
                        <i class="fas fa-box-open" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                        <br>
                        No items currently issued to you
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Issuance History -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-history"></i> Issuance History</h2>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Property No.</th>
                <th>Issued By</th>
                <th>Issue Date</th>
                <th>Return Date</th>
                <th>Quantity</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($history_result && $history_result->num_rows > 0): ?>
                <?php while ($row = $history_result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($row['article_name']); ?></strong>
                    </td>
                    <td><?php echo htmlspecialchars($row['property_no'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($row['issued_by_name']); ?></td>
                    <td><?php echo !empty($row['issued_date']) ? date('M d, Y', strtotime($row['issued_date'])) : 'N/A'; ?></td>
                    <td>
                        <?php 
                        if (!empty($row['actual_return']) && $row['actual_return'] != '0000-00-00') {
                            echo date('M d, Y', strtotime($row['actual_return']));
                        } else {
                            echo '<span class="badge badge-warning">Not returned</span>';
                        }
                        ?>
                    </td>
                    <td><?php echo $row['quantity_issued'] . ' ' . ($row['uom'] ?? ''); ?></td>
                    <td><?php echo getStatusBadge($row['status']); ?></td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px;">
                        No issuance history found
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Request Extension Modal -->
<div id="extensionModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Request Extension</h2>
            <span class="modal-close" onclick="closeModal('extensionModal')">&times;</span>
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

<script>
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

function requestExtension(issuanceId) {
    ajaxRequest('<?php echo SITE_URL; ?>/api/get_issuance_details.php?id=' + issuanceId, 'GET', null, function(err, response) {
        if (!err && response) {
            document.getElementById('extension_issuance_id').value = issuanceId;
            
            let dueDate = response.expected_return;
            if (dueDate && dueDate != '0000-00-00') {
                document.getElementById('current_due_date').textContent = formatDate(dueDate);
            } else {
                document.getElementById('current_due_date').textContent = 'No due date set';
            }
            
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

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function searchTable(inputId, tableId) {
    let input = document.getElementById(inputId);
    let filter = input.value.toUpperCase();
    let table = document.getElementById(tableId);
    let tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        let tdArray = tr[i].getElementsByTagName('td');
        let found = false;
        for (let j = 0; j < tdArray.length; j++) {
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

function formatCurrency(amount) {
    return '₱' + parseFloat(amount || 0).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function formatDate(dateString) {
    if (!dateString || dateString == '0000-00-00') return 'N/A';
    let date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function showModal(title, content) {
    let modal = document.getElementById('dynamic-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'dynamic-modal';
        modal.className = 'modal';
        modal.innerHTML = `
            <div class="modal-content">
                <div class="modal-header">
                    <h2 id="modal-title"></h2>
                    <span class="modal-close" onclick="document.getElementById('dynamic-modal').style.display='none'">&times;</span>
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

<style>
/* Additional styles for overdue highlighting */
.stock-alert-row {
    background-color: #fff3e0 !important;
}

.stock-alert-row td {
    color: #e65100 !important;
}

.text-danger {
    color: #dc3545 !important;
    font-weight: 600;
}

.action-buttons {
    display: flex;
    gap: 5px;
    justify-content: center;
}

.action-btn {
    width: 32px;
    height: 32px;
    border-radius: 4px;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s;
}

.action-btn.view {
    background-color: #e3f2fd;
    color: #1976d2;
}

.action-btn.view:hover {
    background-color: #bbdefb;
    transform: translateY(-2px);
}

.action-btn.edit {
    background-color: #fff3e0;
    color: #f57c00;
}

.action-btn.edit:hover {
    background-color: #ffe0b2;
    transform: translateY(-2px);
}

.action-btn i {
    font-size: 14px;
}
</style>

<?php include '../includes/footer.php'; ?>