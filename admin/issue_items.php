<?php
/**
 * Issue Items Page (Admin)
 * Handle item issuance and reissuance to different users
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Require admin role
requireRole('admin');

$page_title = 'Issue Items';
$page_description = 'Issue inventory items to users';

// Handle issuance form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    // Check if this is a reissue or new issue
    $is_reissue = isset($_POST['is_reissue']) && $_POST['is_reissue'] == '1';
    $original_issuance_id = $is_reissue ? (int)$_POST['original_issuance_id'] : null;
    
    $inventory_id = (int)$_POST['inventory_id'];
    $issued_to = (int)$_POST['issued_to'];
    $quantity = floatval($_POST['quantity']);
    $purpose = sanitize($_POST['purpose']);
    $condition = sanitize($_POST['condition']);
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // If this is a reissue, handle the original issuance first
        if ($is_reissue && $original_issuance_id) {
            // Get the original issuance details
            $original = $conn->query("
                SELECT ei.*, i.qty_physical_count 
                FROM equipment_issuance ei
                JOIN inventory i ON ei.inventory_id = i.id
                WHERE ei.id = $original_issuance_id
            ")->fetch_assoc();
            
            if ($original) {
                // Mark original as returned
                $conn->query("
                    UPDATE equipment_issuance 
                    SET status = 'returned', 
                        actual_return = NOW(),
                        condition_on_return = 'Good',
                        remarks = CONCAT(remarks, ' | Reissued to new user on ', NOW())
                    WHERE id = $original_issuance_id
                ");
                
                // Update user inventory for original owner
                $conn->query("
                    UPDATE user_inventory 
                    SET status = 'returned' 
                    WHERE issuance_id = $original_issuance_id
                ");
                
                // Add note to remarks about reissue
                $remarks = "Reissued from original issuance #$original_issuance_id. " . $remarks;
                
                // For reissue, we DON'T need to check inventory because we're using the returned item
                // The quantity should be the same as the original
                $quantity = $original['quantity_issued'];
            }
        } else {
            // For new issue, check inventory availability
            $item = $conn->query("SELECT * FROM inventory WHERE id = $inventory_id")->fetch_assoc();
            
            if (!$item || $item['qty_physical_count'] < $quantity) {
                throw new Exception("Insufficient quantity available. Available: " . ($item['qty_physical_count'] ?? 0) . ", Requested: $quantity");
            }
            
            // Update inventory quantity for new issue
            $new_quantity = $item['qty_physical_count'] - $quantity;
            $conn->query("UPDATE inventory SET qty_physical_count = $new_quantity WHERE id = $inventory_id");
        }
        
        // Create new issuance record
        $stmt = $conn->prepare("
            INSERT INTO equipment_issuance 
            (inventory_id, issued_to, issued_by, quantity_issued, purpose, condition_on_issue, remarks, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'issued')
        ");
        $stmt->bind_param("iiidsss", $inventory_id, $issued_to, $_SESSION['user_id'], $quantity, $purpose, $condition, $remarks);
        $stmt->execute();
        $issuance_id = $stmt->insert_id;
        
        // Add to new user's inventory
        $stmt = $conn->prepare("
            INSERT INTO user_inventory (user_id, inventory_id, issuance_id, quantity_assigned, status) 
            VALUES (?, ?, ?, ?, 'active')
        ");
        $stmt->bind_param("iiid", $issued_to, $inventory_id, $issuance_id, $quantity);
        $stmt->execute();
        
        // Log activity
        $action = $is_reissue ? 'Reissue Item' : 'Issue Item';
        logActivity($action, $inventory_id, 
            ($is_reissue ? "Reissued item to user ID: $issued_to (from original issuance #$original_issuance_id)" : 
            "Issued $quantity of item to user ID: $issued_to"));
        
        $conn->commit();
        
        if ($is_reissue) {
            $_SESSION['success'] = "Item reissued successfully to new user. Original owner's item has been returned.";
        } else {
            $_SESSION['success'] = "Item issued successfully";
        }
        
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['error'] = "Error: " . $e->getMessage();
    }
    
    // Clear reissue session
    unset($_SESSION['reissue_from']);
    
    header('Location: ' . SITE_URL . '/admin/issue_items.php');
    exit();
}

// Handle return
if (isset($_GET['return']) && is_numeric($_GET['return'])) {
    $issuance_id = (int)$_GET['return'];
    
    $issuance = $conn->query("
        SELECT ei.*, i.qty_physical_count 
        FROM equipment_issuance ei
        JOIN inventory i ON ei.inventory_id = i.id
        WHERE ei.id = $issuance_id
    ")->fetch_assoc();
    
    if ($issuance) {
        $conn->begin_transaction();
        
        try {
            // Update issuance record
            $conn->query("
                UPDATE equipment_issuance 
                SET status = 'returned', 
                    actual_return = NOW(),
                    condition_on_return = 'Good'
                WHERE id = $issuance_id
            ");
            
            // Return to inventory
            $new_quantity = $issuance['qty_physical_count'] + $issuance['quantity_issued'];
            $conn->query("
                UPDATE inventory 
                SET qty_physical_count = $new_quantity 
                WHERE id = {$issuance['inventory_id']}
            ");
            
            // Update user inventory
            $conn->query("
                UPDATE user_inventory 
                SET status = 'returned' 
                WHERE issuance_id = $issuance_id
            ");
            
            logActivity('Return Item', $issuance['inventory_id'], "Returned {$issuance['quantity_issued']} items");
            
            $conn->commit();
            $_SESSION['success'] = "Item returned successfully";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error returning item: " . $e->getMessage();
        }
    }
    
    header('Location: ' . SITE_URL . '/admin/issue_items.php');
    exit();
}

// Handle reissue - store in session and redirect
if (isset($_GET['reissue']) && is_numeric($_GET['reissue'])) {
    $original_issuance_id = (int)$_GET['reissue'];
    
    // Store the original issuance ID in session for the form
    $_SESSION['reissue_from'] = $original_issuance_id;
    
    // Redirect to show the reissue form
    header('Location: ' . SITE_URL . '/admin/issue_items.php?show_reissue=1');
    exit();
}

// Get inventory items with stock
$inventory_items = $conn->query("
    SELECT i.*, e.name as equipment_name, s.name as section_name
    FROM inventory i
    LEFT JOIN equipment e ON i.equipment_id = e.id
    LEFT JOIN sections s ON i.section_id = s.id
    WHERE i.qty_physical_count > 0
    ORDER BY i.article_name
");

// Get users for dropdown
$users = $conn->query("
    SELECT id, username, firstname, lastname 
    FROM users 
    WHERE status = 'active' 
    ORDER BY firstname, lastname
");

// Get current issuances
$issuances = $conn->query("
    SELECT ei.*, 
           i.article_name, i.property_no, i.uom,
           CONCAT(u.firstname, ' ', u.lastname) as issued_to_name,
           CONCAT(ub.firstname, ' ', ub.lastname) as issued_by_name
    FROM equipment_issuance ei
    JOIN inventory i ON ei.inventory_id = i.id
    JOIN users u ON ei.issued_to = u.id
    JOIN users ub ON ei.issued_by = ub.id
    WHERE ei.status = 'issued'
    ORDER BY ei.issued_date DESC
");

// Get reissue details if available
$reissue_item = null;
$reissue_from_id = null;
if (isset($_SESSION['reissue_from'])) {
    $reissue_from_id = (int)$_SESSION['reissue_from'];
    
    $reissue_item = $conn->query("
        SELECT 
            ei.*, 
            i.article_name, 
            i.property_no, 
            i.uom,
            i.qty_physical_count as available_stock,
            CONCAT(original_user.firstname, ' ', original_user.lastname) as issued_to_name,
            original_user.id as original_user_id,
            original_user.username as original_username
        FROM equipment_issuance ei
        JOIN inventory i ON ei.inventory_id = i.id
        JOIN users original_user ON ei.issued_to = original_user.id
        WHERE ei.id = $reissue_from_id
    ")->fetch_assoc();
    
    // If no reissue item found, clear the session
    if (!$reissue_item) {
        unset($_SESSION['reissue_from']);
    }
}

include INCLUDE_PATH . '/header.php';
?>

<div class="stats-grid">
    <!-- Issue Form -->
    <div class="stat-chart">
        <h3><i class="fas fa-hand-holding"></i> 
            <?php echo $reissue_item ? 'Reissue Item to Different User' : 'Issue New Item'; ?>
        </h3>
        
        <?php if ($reissue_item): ?>
        <div class="alert alert-warning" style="margin-bottom: 20px;">
            <i class="fas fa-exclamation-triangle"></i>
            <strong>Important:</strong> Reissuing this item will automatically return it from 
            <strong><?php echo htmlspecialchars($reissue_item['issued_to_name']); ?></strong> 
            and issue it to the new user. No inventory check needed as the item is being transferred.
        </div>
        
        <div class="alert alert-info" style="margin-bottom: 20px;">
            <i class="fas fa-info-circle"></i>
            Reissuing <strong><?php echo htmlspecialchars($reissue_item['article_name']); ?></strong> 
            (<?php echo htmlspecialchars($reissue_item['property_no']); ?>) 
            that was originally issued to <strong><?php echo htmlspecialchars($reissue_item['issued_to_name']); ?></strong>
            on <?php echo date('M d, Y', strtotime($reissue_item['issued_date'])); ?>.
        </div>
        <?php endif; ?>
        
        <form method="POST" action="" onsubmit="return validateIssueForm()">
            <?php if ($reissue_item): ?>
            <input type="hidden" name="is_reissue" value="1">
            <input type="hidden" name="original_issuance_id" value="<?php echo $reissue_from_id; ?>">
            <input type="hidden" name="inventory_id" value="<?php echo $reissue_item['inventory_id']; ?>">
            <input type="hidden" name="quantity" value="<?php echo htmlspecialchars($reissue_item['quantity_issued']); ?>">
            <?php endif; ?>
            
            <?php if (!$reissue_item): ?>
            <div class="form-group">
                <label>Select Item *</label>
                <select name="inventory_id" id="inventory_id" class="form-control" required onchange="updateItemDetails()">
                    <option value="">-- Select Item --</option>
                    <?php if ($inventory_items && $inventory_items->num_rows > 0): ?>
                        <?php 
                        // Reset pointer for inventory_items
                        if ($inventory_items) $inventory_items->data_seek(0);
                        while($item = $inventory_items->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $item['id']; ?>" 
                                data-qty="<?php echo $item['qty_physical_count']; ?>"
                                data-uom="<?php echo $item['uom']; ?>">
                            <?php echo htmlspecialchars($item['article_name'] . ' (' . ($item['property_no'] ?? 'No Prop #') . ') - Available: ' . $item['qty_physical_count'] . ' ' . $item['uom']); ?>
                        </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div id="item_details" style="background: var(--light); padding: 10px; border-radius: 6px; margin-bottom: 20px; display: none;">
                <!-- Item details will be shown here -->
            </div>
            <?php endif; ?>
            
            <?php if ($reissue_item): ?>
            <div class="form-group">
                <label>Item</label>
                <div class="form-control" style="background: #f0f0f0; padding: 10px;" readonly>
                    <strong><?php echo htmlspecialchars($reissue_item['article_name']); ?></strong><br>
                    <small>Property No: <?php echo htmlspecialchars($reissue_item['property_no'] ?? 'N/A'); ?></small><br>
                    <small>Quantity: <?php echo $reissue_item['quantity_issued']; ?> <?php echo htmlspecialchars($reissue_item['uom'] ?? ''); ?></small>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Issue To *</label>
                <select name="issued_to" id="issued_to" class="form-control" required>
                    <option value="">-- Select User --</option>
                    <?php if ($users && $users->num_rows > 0): ?>
                        <?php 
                        // Reset pointer for users
                        if ($users) $users->data_seek(0);
                        while($user = $users->fetch_assoc()): 
                        ?>
                        <option value="<?php echo $user['id']; ?>"
                            <?php echo ($reissue_item && $reissue_item['original_user_id'] == $user['id']) ? 'disabled class="text-muted"' : ''; ?>>
                            <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                            <?php echo ($reissue_item && $reissue_item['original_user_id'] == $user['id']) ? ' (Original Owner - Cannot Reissue to Same User)' : ''; ?>
                        </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
                <?php if ($reissue_item): ?>
                <small class="text-muted">The original owner (<?php echo htmlspecialchars($reissue_item['issued_to_name']); ?>) is disabled. Select a different user.</small>
                <?php endif; ?>
            </div>
            
            <?php if (!$reissue_item): ?>
            <div class="form-group">
                <label>Quantity *</label>
                <input type="number" name="quantity" id="quantity" class="form-control" min="0.01" step="0.01" required>
            </div>
            <?php endif; ?>
            
            <div class="form-group">
                <label>Condition on Issue</label>
                <select name="condition" class="form-control">
                    <option value="Good" <?php echo ($reissue_item && $reissue_item['condition_on_issue'] == 'Good') ? 'selected' : ''; ?>>Good</option>
                    <option value="Fair" <?php echo ($reissue_item && $reissue_item['condition_on_issue'] == 'Fair') ? 'selected' : ''; ?>>Fair</option>
                    <option value="Poor" <?php echo ($reissue_item && $reissue_item['condition_on_issue'] == 'Poor') ? 'selected' : ''; ?>>Poor</option>
                    <option value="New" <?php echo ($reissue_item && $reissue_item['condition_on_issue'] == 'New') ? 'selected' : ''; ?>>New</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Purpose</label>
                <textarea name="purpose" class="form-control" rows="3" required 
                          placeholder="Reason for issuing this item"><?php echo $reissue_item ? htmlspecialchars($reissue_item['purpose'] ?? '') : ''; ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Remarks (Optional)</label>
                <textarea name="remarks" class="form-control" rows="2" 
                          placeholder="Any additional notes"><?php 
                    if ($reissue_item) {
                        echo "Reissued from original issuance #$reissue_from_id. " . ($reissue_item['remarks'] ?? '');
                    }
                ?></textarea>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-hand-holding"></i> 
                    <?php echo $reissue_item ? 'Reissue to Selected User' : 'Issue Item'; ?>
                </button>
                <?php if ($reissue_item): ?>
                <a href="<?php echo SITE_URL; ?>/admin/issue_items.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Cancel Reissue
                </a>
                <?php endif; ?>
            </div>
        </form>
    </div>
    
    <!-- Currently Issued Items -->
    <div class="stat-chart">
        <h3><i class="fas fa-clipboard-list"></i> Currently Issued Items</h3>
        <div style="max-height: 500px; overflow-y: auto;">
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Issued To</th>
                        <th>Qty</th>
                        <th>Issue Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($issuances && $issuances->num_rows > 0): ?>
                        <?php 
                        // Reset pointer for issuances
                        if ($issuances) $issuances->data_seek(0);
                        while($issue = $issuances->fetch_assoc()): 
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($issue['article_name']); ?></strong>
                                <br><small><?php echo htmlspecialchars($issue['property_no'] ?? 'N/A'); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($issue['issued_to_name']); ?></td>
                            <td><?php echo $issue['quantity_issued'] . ' ' . htmlspecialchars($issue['uom'] ?? ''); ?></td>
                            <td><?php echo date('M d, Y', strtotime($issue['issued_date'])); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="?return=<?php echo $issue['id']; ?>" 
                                       class="action-btn success"
                                       onclick="return confirm('Mark this item as returned?')">
                                        <i class="fas fa-undo"></i> Return
                                    </a>
                                    <a href="?reissue=<?php echo $issue['id']; ?>" 
                                       class="action-btn edit"
                                       title="Reissue to different user">
                                        <i class="fas fa-redo"></i> Reissue
                                    </a>
                                    <button class="action-btn view" onclick="viewIssuanceDetails(<?php echo $issue['id']; ?>)">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center">No items currently issued</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Issuance History -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-history"></i> Issuance History</h2>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Item</th>
                <th>Issued To</th>
                <th>Issued By</th>
                <th>Quantity</th>
                <th>Purpose</th>
                <th>Status</th>
                <th>Return Date</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $history = $conn->query("
                SELECT ei.*, 
                       i.article_name, i.property_no,
                       CONCAT(u.firstname, ' ', u.lastname) as issued_to_name,
                       CONCAT(ub.firstname, ' ', ub.lastname) as issued_by_name
                FROM equipment_issuance ei
                JOIN inventory i ON ei.inventory_id = i.id
                JOIN users u ON ei.issued_to = u.id
                JOIN users ub ON ei.issued_by = ub.id
                WHERE ei.status != 'issued'
                ORDER BY ei.issued_date DESC
                LIMIT 20
            ");
            
            if ($history && $history->num_rows > 0):
                while($item = $history->fetch_assoc()):
            ?>
            <tr>
                <td><?php echo date('M d, Y', strtotime($item['issued_date'])); ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($item['article_name']); ?></strong>
                    <br><small><?php echo htmlspecialchars($item['property_no'] ?? ''); ?></small>
                </td>
                <td><?php echo htmlspecialchars($item['issued_to_name']); ?></td>
                <td><?php echo htmlspecialchars($item['issued_by_name']); ?></td>
                <td><?php echo $item['quantity_issued']; ?></td>
                <td><?php echo htmlspecialchars(substr($item['purpose'] ?? '', 0, 30)) . '...'; ?></td>
                <td><?php echo getStatusBadge($item['status']); ?></td>
                <td><?php echo $item['actual_return'] ? date('M d, Y', strtotime($item['actual_return'])) : 'N/A'; ?></td>
            </tr>
            <?php 
                endwhile;
            else:
            ?>
            <tr>
                <td colspan="8" class="text-center">No issuance history found</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
let inventoryItems = <?php 
    $items = $conn->query("SELECT * FROM inventory WHERE qty_physical_count > 0");
    $items_data = [];
    if ($items) {
        while($item = $items->fetch_assoc()) {
            $items_data[$item['id']] = $item;
        }
    }
    echo json_encode($items_data);
?>;

function updateItemDetails() {
    let itemId = document.getElementById('inventory_id').value;
    let detailsDiv = document.getElementById('item_details');
    let quantityInput = document.getElementById('quantity');
    
    if (itemId && inventoryItems[itemId]) {
        let item = inventoryItems[itemId];
        detailsDiv.style.display = 'block';
        detailsDiv.innerHTML = `
            <strong>${item.article_name}</strong><br>
            Property No: ${item.property_no || 'N/A'}<br>
            Available: ${item.qty_physical_count} ${item.uom}<br>
            Unit Value: ${formatCurrency(item.unit_value)}<br>
            Category: ${item.category || 'N/A'}
        `;
        quantityInput.max = item.qty_physical_count;
    } else {
        detailsDiv.style.display = 'none';
    }
}

function validateIssueForm() {
    <?php if ($reissue_item): ?>
    // For reissue, only validate user selection
    let selectedUser = document.getElementById('issued_to').value;
    let originalOwner = <?php echo $reissue_item ? $reissue_item['original_user_id'] : 'null'; ?>;
    
    if (!selectedUser) {
        alert('Please select a user to reissue to');
        return false;
    }
    
    if (selectedUser == originalOwner) {
        alert('Cannot reissue to the same user. Please select a different user.');
        return false;
    }
    
    <?php else: ?>
    // For new issue, validate all fields
    let quantity = parseFloat(document.getElementById('quantity').value);
    let itemId = document.getElementById('inventory_id').value;
    let issuedTo = document.getElementById('issued_to').value;
    
    if (!itemId) {
        alert('Please select an item');
        return false;
    }
    
    if (!issuedTo) {
        alert('Please select a user to issue to');
        return false;
    }
    
    if (quantity <= 0) {
        alert('Quantity must be greater than 0');
        return false;
    }
    
    if (itemId && inventoryItems[itemId]) {
        let maxQty = inventoryItems[itemId].qty_physical_count;
        if (quantity > maxQty) {
            alert('Quantity cannot exceed available stock (' + maxQty + ')');
            return false;
        }
    }
    <?php endif; ?>
    
    return true;
}

function viewIssuanceDetails(issuanceId) {
    ajaxRequest('<?php echo SITE_URL; ?>/api/get_issuance_details.php?id=' + issuanceId, 'GET', null, function(err, response) {
        if (!err && response) {
            let content = `
                <div style="margin-bottom: 20px;">
                    <h3>Issuance #${response.id}</h3>
                    <table style="width: 100%;">
                        <tr><td><strong>Item:</strong></td><td>${response.article_name}</td></tr>
                        <tr><td><strong>Property No:</strong></td><td>${response.property_no || 'N/A'}</td></tr>
                        <tr><td><strong>Issued To:</strong></td><td>${response.issued_to_name}</td></tr>
                        <tr><td><strong>Issued By:</strong></td><td>${response.issued_by_name}</td></tr>
                        <tr><td><strong>Quantity:</strong></td><td>${response.quantity_issued}</td></tr>
                        <tr><td><strong>Issue Date:</strong></td><td>${formatDate(response.issued_date)}</td></tr>
                        <tr><td><strong>Purpose:</strong></td><td>${response.purpose || 'N/A'}</td></tr>
                        <tr><td><strong>Condition on Issue:</strong></td><td>${response.condition_on_issue || 'Good'}</td></tr>
                        <tr><td><strong>Status:</strong></td><td>${response.status}</td></tr>
                        <tr><td><strong>Return Date:</strong></td><td>${response.actual_return ? formatDate(response.actual_return) : 'Not returned'}</td></tr>
                        <tr><td><strong>Remarks:</strong></td><td>${response.remarks || 'N/A'}</td></tr>
                    </table>
                </div>
            `;
            showModal('Issuance Details', content);
        } else {
            alert('Error loading issuance details');
        }
    });
}

function formatCurrency(amount) {
    return '₱' + parseFloat(amount || 0).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
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

<?php include INCLUDE_PATH . '/footer.php'; ?>