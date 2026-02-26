<?php
/**
 * Admin Dashboard
 */

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php'; // Add this for helper functions

// Require admin role
requireRole('admin');

$page_title = 'Admin Dashboard';
$page_description = 'Manage inventory and track items';

// Initialize stats array
$stats = [];

// Total inventory
$result = $conn->query("SELECT 
    COUNT(*) as total_items,
    SUM(qty_physical_count) as total_qty,
    SUM(unit_value * qty_physical_count) as total_value
FROM inventory");
if ($result) {
    $stats['inventory'] = $result->fetch_assoc();
} else {
    $stats['inventory'] = ['total_items' => 0, 'total_qty' => 0, 'total_value' => 0];
}

// Pending issues
$result = $conn->query("SELECT COUNT(*) as count FROM equipment_issuance WHERE status = 'issued'");
$stats['pending_issues'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Low stock items
$result = $conn->query("SELECT COUNT(*) as count FROM inventory WHERE qty_physical_count <= 5");
$stats['low_stock'] = ($result && $result->num_rows > 0) ? $result->fetch_assoc()['count'] : 0;

// Recent issuances
$recent_issues = $conn->query("
    SELECT ei.*, 
           i.article_name,
           CONCAT(u.firstname, ' ', u.lastname) as issued_to_name
    FROM equipment_issuance ei
    JOIN inventory i ON ei.inventory_id = i.id
    JOIN users u ON ei.issued_to = u.id
    ORDER BY ei.issued_date DESC
    LIMIT 10
");

// Low stock items list
$low_stock_items = $conn->query("
    SELECT i.*, s.name as section_name 
    FROM inventory i
    LEFT JOIN sections s ON i.section_id = s.id
    WHERE i.qty_physical_count <= 5 
    ORDER BY i.qty_physical_count ASC 
    LIMIT 10
");

include INCLUDE_PATH . '/header.php';
?>

<!-- Statistics Cards -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-boxes"></i>
        </div>
        <h3>Total Items</h3>
        <div class="card-value"><?php echo number_format($stats['inventory']['total_items'] ?? 0); ?></div>
        
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-hand-holding"></i>
        </div>
        <h3>Issued Items</h3>
        <div class="card-value"><?php echo $stats['pending_issues']; ?></div>
       
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-exclamation-triangle"></i>
        </div>
        <h3>Low Stock</h3>
        <div class="card-value text-warning"><?php echo $stats['low_stock']; ?></div>
       
    </div>
    
    <div class="card">
        <div class="card-icon">
            <i class="fas fa-peso-sign"></i>
        </div>
        <h3>Total Value</h3>
        <div class="card-value"><?php echo formatCurrency($stats['inventory']['total_value'] ?? 0); ?></div>
        
    </div>
</div>

<!-- Quick Actions - FIXED PATHS -->
<div class="table-container">
    <div class="table-header">
        <h2>Quick Actions</h2>
    </div>
    <div style="display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="<?php echo SITE_URL; ?>/admin/add_inventory.php" class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Inventory
        </a>
        <a href="<?php echo SITE_URL; ?>/admin/issue_items.php" class="btn btn-primary">
            <i class="fas fa-hand-holding"></i> Issue Items
        </a>
        
    </div>
</div>

<div class="stats-grid">
    <!-- Recent Issuances -->
    <div class="stat-chart">
        <h3><i class="fas fa-hand-holding"></i> Recent Issuances</h3>
        <table style="width: 100%;">
            <thead>
                <tr>
                    <th>Item</th>
                    <th>Issued To</th>
                    <th>Date</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($recent_issues && $recent_issues->num_rows > 0): ?>
                    <?php while($issue = $recent_issues->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($issue['article_name']); ?></td>
                        <td><?php echo htmlspecialchars($issue['issued_to_name']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($issue['issued_date'])); ?></td>
                        <td><?php echo getStatusBadge($issue['status']); ?></td>
                    </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4" class="text-center">No recent issuances</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Low Stock Alerts -->
    <div class="stat-chart">
        <h3><i class="fas fa-exclamation-triangle text-warning"></i> Low Stock Alerts</h3>
        <?php if ($low_stock_items && $low_stock_items->num_rows > 0): ?>
            <table style="width: 100%;">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Qty</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($item = $low_stock_items->fetch_assoc()): ?>
                    <tr class="<?php echo $item['qty_physical_count'] <= 2 ? 'stock-alert-row' : ''; ?>">
                        <td>
                            <strong><?php echo htmlspecialchars($item['article_name']); ?></strong>
                            <br><small><?php echo htmlspecialchars($item['property_no'] ?? ''); ?></small>
                        </td>
                        <td>
                            <span class="badge badge-danger"><?php echo $item['qty_physical_count']; ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($item['section_name'] ?? 'N/A'); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="text-center text-success">
                <i class="fas fa-check-circle"></i> All items have sufficient stock
            </p>
        <?php endif; ?>
    </div>
</div>

<!-- Recent Orders -->
<div class="table-container">
    <div class="table-header">
        <h2>Recent Orders</h2>
        <a href="<?php echo SITE_URL; ?>/admin/issue_items.php" class="btn btn-sm btn-primary">View All</a>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>SI No</th>
                <th>Product Name</th>
                <th>P. Price</th>
                <th>Quantity</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sample_orders = $conn->query("
                SELECT ei.*, i.article_name, i.unit_value,
                       CONCAT(u.firstname, ' ', u.lastname) as customer
                FROM equipment_issuance ei
                JOIN inventory i ON ei.inventory_id = i.id
                JOIN users u ON ei.issued_to = u.id
                ORDER BY ei.issued_date DESC
                LIMIT 5
            ");
            
            if ($sample_orders && $sample_orders->num_rows > 0):
                $count = 1;
                while($order = $sample_orders->fetch_assoc()):
            ?>
            <tr>
                <td><?php echo str_pad($count++, 2, '0', STR_PAD_LEFT); ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($order['article_name']); ?></strong>
                    <br><small><?php echo htmlspecialchars($order['customer']); ?></small>
                </td>
                <td><?php echo formatCurrency($order['unit_value']); ?></td>
                <td><?php echo $order['quantity_issued']; ?></td>
                <td><?php echo formatCurrency($order['unit_value'] * $order['quantity_issued']); ?></td>
                <td>COO</td>
                <td><?php echo getStatusBadge($order['status']); ?></td>
                <td>
                    <button class="btn btn-sm btn-primary" onclick="viewOrder(<?php echo $order['id']; ?>)">
                        <i class="fas fa-eye"></i>
                    </button>
                </td>
            </tr>
            <?php 
                endwhile;
            else:
            ?>
            <tr>
                <td colspan="8" class="text-center">No orders found</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>


<script>
function viewOrder(orderId) {
    ajaxRequest('<?php echo SITE_URL; ?>/api/get_order_details.php?id=' + orderId, 'GET', null, function(err, response) {
        if (!err && response) {
            let content = `
                <div style="margin-bottom: 20px;">
                    <h3>Order #${response.id}</h3>
                    <table style="width: 100%;">
                        <tr><td><strong>Item:</strong></td><td>${response.article_name}</td></tr>
                        <tr><td><strong>Customer:</strong></td><td>${response.customer}</td></tr>
                        <tr><td><strong>Quantity:</strong></td><td>${response.quantity_issued}</td></tr>
                        <tr><td><strong>Unit Price:</strong></td><td>${formatCurrency(response.unit_value)}</td></tr>
                        <tr><td><strong>Total:</strong></td><td>${formatCurrency(response.unit_value * response.quantity_issued)}</td></tr>
                        <tr><td><strong>Issue Date:</strong></td><td>${formatDate(response.issued_date)}</td></tr>
                        <tr><td><strong>Status:</strong></td><td>${response.status}</td></tr>
                        <tr><td><strong>Purpose:</strong></td><td>${response.purpose || 'N/A'}</td></tr>
                    </table>
                </div>
            `;
            showModal('Order Details', content);
        } else {
            alert('Error loading order details');
        }
    });
}

// Helper functions (if not already defined)
function formatCurrency(amount) {
    return '₱' + parseFloat(amount).toFixed(2).replace(/\d(?=(\d{3})+\.)/g, '$&,');
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    let date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function showModal(title, content) {
    // Create modal if it doesn't exist
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