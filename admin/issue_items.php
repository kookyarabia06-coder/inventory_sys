<?php
/**
 * Issue Items Page (Admin)
 * Handle item issuance and reissuance
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
    $inventory_id = (int)$_POST['inventory_id'];
    $issued_to = (int)$_POST['issued_to'];
    $quantity = floatval($_POST['quantity']);
    $purpose = sanitize($_POST['purpose']);
    $condition = sanitize($_POST['condition']);
    $remarks = sanitize($_POST['remarks'] ?? '');
    
    // Check inventory availability
    $item = $conn->query("SELECT * FROM inventory WHERE id = $inventory_id")->fetch_assoc();
    
    if ($item && $item['qty_physical_count'] >= $quantity) {
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Create issuance record (removed expected_return)
            $stmt = $conn->prepare("
                INSERT INTO equipment_issuance 
                (inventory_id, issued_to, issued_by, quantity_issued, purpose, condition_on_issue, remarks, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'issued')
            ");
            $stmt->bind_param("iiidsss", $inventory_id, $issued_to, $_SESSION['user_id'], $quantity, $purpose, $condition, $remarks);
            $stmt->execute();
            $issuance_id = $stmt->insert_id;
            
            // Update inventory quantity
            $new_quantity = $item['qty_physical_count'] - $quantity;
            $conn->query("UPDATE inventory SET qty_physical_count = $new_quantity WHERE id = $inventory_id");
            
            // Add to user inventory
            $stmt = $conn->prepare("
                INSERT INTO user_inventory (user_id, inventory_id, issuance_id, quantity_assigned, status) 
                VALUES (?, ?, ?, ?, 'active')
            ");
            $stmt->bind_param("iiid", $issued_to, $inventory_id, $issuance_id, $quantity);
            $stmt->execute();
            
            // Log activity
            logActivity('Issue Item', $inventory_id, "Issued $quantity of item ID: $inventory_id to user ID: $issued_to");
            
            $conn->commit();
            $_SESSION['success'] = "Item issued successfully";
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Error issuing item: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Insufficient quantity available";
    }
    
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

// Handle reissue
if (isset($_GET['reissue']) && is_numeric($_GET['reissue'])) {
    $original_issuance_id = (int)$_GET['reissue'];
    
    // Get the original issuance details
    $original = $conn->query("
        SELECT ei.*, i.article_name, i.qty_physical_count 
        FROM equipment_issuance ei
        JOIN inventory i ON ei.inventory_id = i.id
        WHERE ei.id = $original_issuance_id
    ")->fetch_assoc();
    
    if ($original) {
        // Check if there's enough stock
        if ($original['qty_physical_count'] >= $original['quantity_issued']) {
            $conn->begin_transaction();
            
            try {
                // Mark original as returned (if not already)
                if ($original['status'] != 'returned') {
                    $conn->query("
                        UPDATE equipment_issuance 
                        SET status = 'returned', 
                            actual_return = NOW(),
                            condition_on_return = 'Good'
                        WHERE id = $original_issuance_id
                    ");
                    
                    // Update user inventory
                    $conn->query("
                        UPDATE user_inventory 
                        SET status = 'returned' 
                        WHERE issuance_id = $original_issuance_id
                    ");
                }
                
                // Create new issuance (reissue)
                $stmt = $conn->prepare("
                    INSERT INTO equipment_issuance 
                    (inventory_id, issued_to, issued_by, quantity_issued, purpose, condition_on_issue, remarks, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'issued')
                ");
                $stmt->bind_param("iiidsss", 
                    $original['inventory_id'], 
                    $original['issued_to'], 
                    $_SESSION['user_id'], 
                    $original['quantity_issued'], 
                    $original['purpose'], 
                    $original['condition_on_issue'], 
                    "Reissued from original issuance #$original_issuance_id"
                );
                $stmt->execute();
                $new_issuance_id = $stmt->insert_id;
                
                // Update inventory quantity
                $new_quantity = $original['qty_physical_count'] - $original['quantity_issued'];
                $conn->query("
                    UPDATE inventory 
                    SET qty_physical_count = $new_quantity 
                    WHERE id = {$original['inventory_id']}
                ");
                
                // Add to user inventory
                $stmt = $conn->prepare("
                    INSERT INTO user_inventory (user_id, inventory_id, issuance_id, quantity_assigned, status) 
                    VALUES (?, ?, ?, ?, 'active')
                ");
                $stmt->bind_param("iiid", 
                    $original['issued_to'], 
                    $original['inventory_id'], 
                    $new_issuance_id, 
                    $original['quantity_issued']
                );
                $stmt->execute();
                
                logActivity('Reissue Item', $original['inventory_id'], "Reissued {$original['quantity_issued']} items from issuance #$original_issuance_id");
                
                $conn->commit();
                $_SESSION['success'] = "Item reissued successfully";
            } catch (Exception $e) {
                $conn->rollback();
                $_SESSION['error'] = "Error reissuing item: " . $e->getMessage();
            }
        } else {
            $_SESSION['error'] = "Insufficient stock to reissue item";
        }
    }
    
    header('Location: ' . SITE_URL . '/admin/issue_items.php');
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

// Get current issuances (removed expected_return from query)
$issuances = $conn->query("
    SELECT ei.*, 
           i.article_name, i.property_no,
           CONCAT(u.firstname, ' ', u.lastname) as issued_to_name,
           CONCAT(ub.firstname, ' ', ub.lastname) as issued_by_name
    FROM equipment_issuance ei
    JOIN inventory i ON ei.inventory_id = i.id
    JOIN users u ON ei.issued_to = u.id
    JOIN users ub ON ei.issued_by = ub.id
    WHERE ei.status = 'issued'
    ORDER BY ei.issued_date DESC
");

include INCLUDE_PATH . '/header.php';
?>

<div class="stats-grid">
    <!-- Issue Form -->
    <div class="stat-chart">
        <h3><i class="fas fa-hand-holding"></i> Issue New Item</h3>
        <form method="POST" action="" onsubmit="return validateIssueForm()">
            <div class="form-group">
                <label>Select Item *</label>
                <select name="inventory_id" id="inventory_id" class="form-control" required onchange="updateItemDetails()">
                    <option value="">-- Select Item --</option>
                    <?php if ($inventory_items && $inventory_items->num_rows > 0): ?>
                        <?php while($item = $inventory_items->fetch_assoc()): ?>
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
            
            <div class="form-group">
                <label>Issue To *</label>
                <select name="issued_to" class="form-control" required>
                    <option value="">-- Select User --</option>
                    <?php if ($users && $users->num_rows > 0): ?>
                        <?php while($user = $users->fetch_assoc()): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['firstname'] . ' ' . $user['lastname'] . ' (' . $user['username'] . ')'); ?>
                        </option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Quantity *</label>
                <input type="number" name="quantity" id="quantity" class="form-control" min="0.01" step="0.01" required>
            </div>
            
            <div class="form-group">
                <label>Condition on Issue</label>
                <select name="condition" class="form-control">
                    <option value="Good">Good</option>
                    <option value="Fair">Fair</option>
                    <option value="Poor">Poor</option>
                    <option value="New">New</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Purpose</label>
                <textarea name="purpose" class="form-control" rows="3" required placeholder="Reason for issuing this item"></textarea>
            </div>
            
            <div class="form-group">
                <label>Remarks (Optional)</label>
                <textarea name="remarks" class="form-control" rows="2" placeholder="Any additional notes"></textarea>
            </div>
            
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-hand-holding"></i> Issue Item
            </button>
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
                        <?php while($issue = $issuances->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($issue['article_name']); ?></strong>
                                <br><small><?php echo htmlspecialchars($issue['property_no'] ?? 'N/A'); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($issue['issued_to_name']); ?></td>
                            <td><?php echo $issue['quantity_issued']; ?></td>
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
                                       onclick="return confirm('Create a new issuance for this item? The original will be marked as returned.')">
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
    let quantity = parseFloat(document.getElementById('quantity').value);
    let itemId = document.getElementById('inventory_id').value;
    
    if (!itemId) {
        alert('Please select an item');
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