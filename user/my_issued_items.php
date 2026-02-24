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
        CONCAT(u.firstname, ' ', u.lastname) as issued_by_name
    FROM user_inventory ui
    JOIN inventory i ON ui.inventory_id = i.id
    LEFT JOIN equipment_issuance ei ON ui.issuance_id = ei.id
    LEFT JOIN users u ON ei.issued_by = u.id
    WHERE ui.user_id = $user_id AND ui.status = 'active'
    ORDER BY ui.assigned_date DESC
";

$result = $conn->query($query);

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
        <div class="card-value"><?php echo $result->num_rows; ?></div>
        <div class="card-label">Currently in your possession</div>
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-clock"></i>
        </div>
        <h3>Pending Returns</h3>
        <?php
        $pending = $conn->query("
            SELECT COUNT(*) as count FROM equipment_issuance 
            WHERE issued_to = $user_id AND status = 'issued' AND expected_return < CURDATE()
        ")->fetch_assoc();
        ?>
        <div class="card-value"><?php echo $pending['count']; ?></div>
        <div class="card-label">Overdue items</div>
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
        <div class="card-value"><?php echo $total['count']; ?></div>
        <div class="card-label">All time issuances</div>
    </div>
</div>

<!-- Currently Issued Items -->
<div class="table-container">
    <div class="table-header">
        <h2>Currently Issued Items</h2>
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
                <th>Article Name</th>
                <th>Property No.</th>
                <th>Quantity</th>
                <th>Unit Value</th>
                <th>Issued Date</th>
                <th>Expected Return</th>
                <th>Purpose</th>
                <th>Condition</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($row['article_name']); ?></strong>
                        <br>
                        <small><?php echo htmlspecialchars($row['item_description']); ?></small>
                    </td>
                    <td><?php echo htmlspecialchars($row['property_no']); ?></td>
                    <td><?php echo $row['quantity_assigned'] . ' ' . $row['uom']; ?></td>
                    <td><?php echo formatCurrency($row['unit_value']); ?></td>
                    <td><?php echo formatDate($row['issued_date']); ?></td>
                    <td>
                        <?php echo formatDate($row['expected_return']); ?>
                        <?php if (strtotime($row['expected_return']) < time()): ?>
                            <br><span class="badge badge-danger">Overdue</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['purpose']); ?></td>
                    <td><?php echo htmlspecialchars($row['condition_on_issue']); ?></td>
                    <td><?php echo getStatusBadge($row['status']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="viewItemDetails(<?php echo $row['inventory_id']; ?>)">
                            <i class="fas fa-eye"></i>
                        </button>
                        <?php if ($row['status'] == 'active'): ?>
                        <button class="btn btn-sm btn-secondary" onclick="requestReturn(<?php echo $row['id']; ?>)">
                            <i class="fas fa-undo"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="10" style="text-align: center; padding: 40px;">
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
        <h2>Issuance History</h2>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Article Name</th>
                <th>Property No.</th>
                <th>Issued By</th>
                <th>Issued Date</th>
                <th>Returned Date</th>
                <th>Quantity</th>
                <th>Status</th>
                <th>Condition</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($history_result->num_rows > 0): ?>
                <?php while ($row = $history_result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo htmlspecialchars($row['article_name']); ?></td>
                    <td><?php echo htmlspecialchars($row['property_no']); ?></td>
                    <td><?php echo htmlspecialchars($row['issued_by_name']); ?></td>
                    <td><?php echo formatDate($row['issued_date']); ?></td>
                    <td><?php echo formatDate($row['actual_return']); ?></td>
                    <td><?php echo $row['quantity_issued']; ?></td>
                    <td><?php echo getStatusBadge($row['status']); ?></td>
                    <td>
                        Issue: <?php echo $row['condition_on_issue']; ?><br>
                        Return: <?php echo $row['condition_on_return'] ?: 'N/A'; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        No issuance history found
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<script>
function viewItemDetails(itemId) {
    // AJAX call to get item details
    ajaxRequest(`/api/get_item_details.php?id=${itemId}`, 'GET', null, function(err, response) {
        if (!err && response) {
            let content = `
                <div style="margin-bottom: 20px;">
                    <h3>${response.article_name}</h3>
                    <p><strong>Property No:</strong> ${response.property_no}</p>
                    <p><strong>Description:</strong> ${response.description}</p>
                    <p><strong>Category:</strong> ${response.category}</p>
                    <p><strong>Unit Value:</strong> ${formatCurrency(response.unit_value)}</p>
                    <p><strong>Location:</strong> ${response.section_name}</p>
                </div>
            `;
            showModal('Item Details', content);
        }
    });
}

function requestReturn(issuanceId) {
    if (confirm('Are you sure you want to request return of this item?')) {
        ajaxRequest('/api/request_return.php', 'POST', { issuance_id: issuanceId }, function(err, response) {
            if (!err && response.success) {
                location.reload();
            } else {
                alert('Error requesting return');
            }
        });
    }
}

function searchTable(inputId, tableId) {
    let input = document.getElementById(inputId);
    let filter = input.value.toUpperCase();
    let table = document.getElementById(tableId);
    let tr = table.getElementsByTagName('tr');
    
    for (let i = 1; i < tr.length; i++) {
        let tdArray = tr[i].getElementsByTagName('td');
        let found = false;
        for (let j = 0; j < tdArray.length - 1; j++) {
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
</script>

<?php include '../includes/footer.php'; ?>