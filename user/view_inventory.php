<?php
$page_title = 'View Inventory';
$page_description = 'Browse all available inventory items';

require_once '../includes/auth.php';
requireLogin();
requireRole('user');
require_once '../config/database.php';

// Ensure database connection exists
if (!isset($conn) || !$conn) {
    die("Database connection failed. Please check your configuration.");
}

$conn->set_charset("utf8mb4");

// DEBUG: Check if any result exists before we start
echo "<!-- Debug: Starting fresh -->\n";

// Sanitize and validate inputs
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 10;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Limit search length to prevent performance issues
if (strlen($search) > 100) {
    $search = substr($search, 0, 100);
}

// Build base query with proper escaping
$base_query = "
    SELECT 
        i.id,
        i.article_name,
        i.property_no,
        i.description,
        i.category,
        i.qty_physical_count,
        i.uom,
        i.unit_value,
        i.equipment_id,
        i.section_id,
        e.name as equipment_name,
        s.name as section_name
    FROM inventory i
    LEFT JOIN equipment e ON i.equipment_id = e.id
    LEFT JOIN sections s ON i.section_id = s.id
    WHERE 1=1
";

// Count query using DISTINCT to avoid JOIN duplication
$count_query = "SELECT COUNT(DISTINCT i.id) as total FROM inventory i
                LEFT JOIN equipment e ON i.equipment_id = e.id
                LEFT JOIN sections s ON i.section_id = s.id
                WHERE 1=1";

// Add search condition safely
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $search_condition = " AND (i.article_name LIKE '%$search_escaped%' 
                           OR i.property_no LIKE '%$search_escaped%'
                           OR i.description LIKE '%$search_escaped%')";
    $base_query .= $search_condition;
    $count_query .= $search_condition;
}

$base_query .= " ORDER BY i.article_name ASC";

// Get total count
$count_result = mysqli_query($conn, $count_query);
if (!$count_result) {
    error_log("Database error in count query: " . mysqli_error($conn));
    die("An error occurred while counting records. Please try again later.");
}
$total_row = mysqli_fetch_assoc($count_result);
$total_rows = $total_row['total'];

// Calculate offset and get paginated data
$offset = ($page - 1) * $per_page;
$data_query = $base_query . " LIMIT $offset, $per_page";
$data_result = mysqli_query($conn, $data_query);

if (!$data_result) {
    error_log("Database error in data query: " . mysqli_error($conn));
    die("An error occurred while fetching records. Please try again later.");
}

$data = [];
while ($row = mysqli_fetch_assoc($data_result)) {
    $data[] = $row;
}

// If no data found and not on page 1, redirect to page 1
if (empty($data) && $page > 1 && $total_rows > 0) {
    $redirect_url = "?page=1";
    if ($search) {
        $redirect_url .= "&search=" . urlencode($search);
    }
    header("Location: " . $redirect_url);
    exit();
}

// Create the result array
$result = [
    'data' => $data,
    'current_page' => $page,
    'per_page' => $per_page,
    'total_rows' => $total_rows,
    'total_pages' => $total_rows > 0 ? ceil($total_rows / $per_page) : 1,
    'has_previous' => $page > 1,
    'has_next' => $page < ceil($total_rows / $per_page)
];

echo "<!-- Debug: result type = " . gettype($result) . " -->\n";
echo "<!-- Debug: data count = " . count($result['data']) . " -->\n";

include '../includes/header.php';
?>

<!-- Search Section -->
<div class="table-container">
    <div class="table-header">
        <h2>Search Inventory</h2>
    </div>
    
    <form method="GET" action="" class="search-box">
        <input type="text" name="search" placeholder="Search by article name, property no., or description..." 
               value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">
            <i class="fas fa-search"></i> Search
        </button>
        <?php if ($search): ?>
        <a href="/user/view_inventory" class="btn btn-secondary">
            <i class="fas fa-times"></i> Clear
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Inventory Items -->
<div class="table-container">
    <div class="table-header">
        <h2>Inventory Items</h2>
        <?php 
        // SAFETY CHECK: Ensure $result is an array before using it
        if (!is_array($result)) {
            die('Error: $result is not an array. Type: ' . gettype($result) . '. Please check your code.');
        }
        ?>
        <p>Showing <?php echo count($result['data']); ?> of <?php echo $result['total_rows']; ?> items</p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>Article Name</th>
                <th>Property No.</th>
                <th>Category</th>
                <th>Quantity</th>
                <th>Unit Value</th>
                <th>Location</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($result['data']) > 0): ?>
                <?php foreach ($result['data'] as $row): ?>
                <tr>
                    <td>
                        <strong><?php echo htmlspecialchars($row['article_name']); ?></strong>
                        <?php if ($row['description']): ?>
                        <br><small><?php echo htmlspecialchars(substr($row['description'], 0, 50)) . '...'; ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($row['property_no']); ?></td>
                    <td>
                        <?php echo htmlspecialchars($row['category']); ?>
                        <br><small><?php echo htmlspecialchars($row['equipment_name']); ?></small>
                    </td>
                    <td>
                        <?php echo $row['qty_physical_count']; ?> <?php echo $row['uom']; ?>
                    </td>
                    <td><?php echo formatCurrency($row['unit_value']); ?></td>
                    <td><?php echo htmlspecialchars($row['section_name']); ?></td>
                    <td>
                        <?php 
                        if ($row['qty_physical_count'] > 0) {
                            echo '<span class="badge badge-success">Available</span>';
                        } else {
                            echo '<span class="badge badge-danger">Out of Stock</span>';
                        }
                        ?>
                    </td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="viewItemDetails(<?php echo $row['id']; ?>)">
                            <i class="fas fa-eye"></i> View
                        </button>
                        <?php if ($row['qty_physical_count'] > 0): ?>
                        <button class="btn btn-sm btn-success" onclick="requestItem(<?php echo $row['id']; ?>)">
                            <i class="fas fa-hand-holding"></i> Request
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="8" style="text-align: center; padding: 40px;">
                        <i class="fas fa-box-open" style="font-size: 48px; color: #ccc; margin-bottom: 10px;"></i>
                        <br>
                        No inventory items found
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <!-- Pagination -->
    <?php if ($result['total_pages'] > 1): ?>
    <div style="display: flex; justify-content: center; margin-top: 20px; gap: 5px;">
        <?php if ($page > 1): ?>
            <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-sm btn-secondary">
                <i class="fas fa-angle-double-left"></i>
            </a>
            <a href="?page=<?php echo $page-1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-sm btn-secondary">
                <i class="fas fa-angle-left"></i>
            </a>
        <?php endif; ?>
        
        <span style="padding: 8px 15px;">
            Page <?php echo $page; ?> of <?php echo $result['total_pages']; ?>
        </span>
        
        <?php if ($page < $result['total_pages']): ?>
            <a href="?page=<?php echo $page+1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-sm btn-secondary">
                <i class="fas fa-angle-right"></i>
            </a>
            <a href="?page=<?php echo $result['total_pages']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="btn btn-sm btn-secondary">
                <i class="fas fa-angle-double-right"></i>
            </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Request Item Modal -->
<div id="requestModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>Request Item</h2>
            <span class="modal-close">&times;</span>
        </div>
        <div class="modal-body">
            <form id="requestForm" onsubmit="submitRequest(event)">
                <input type="hidden" id="request_item_id" name="item_id">
                
                <div class="form-group">
                    <label>Item Details</label>
                    <div id="item_details" style="background: var(--light); padding: 10px; border-radius: 6px;"></div>
                </div>
                
                <div class="form-group">
                    <label for="quantity">Quantity</label>
                    <input type="number" class="form-control" id="quantity" name="quantity" min="1" required>
                    <small id="available_qty"></small>
                </div>
                
                <div class="form-group">
                    <label for="purpose">Purpose</label>
                    <textarea class="form-control" id="purpose" name="purpose" rows="3" required></textarea>
                </div>
                
                <div class="form-group">
                    <label for="expected_return">Expected Return Date</label>
                    <input type="date" class="form-control" id="expected_return" name="expected_return" 
                           min="<?php echo date('Y-m-d'); ?>" required>
                </div>
                
                <div class="form-group">
                    <label for="remarks">Remarks (Optional)</label>
                    <textarea class="form-control" id="remarks" name="remarks" rows="2"></textarea>
                </div>
                
                <div style="display: flex; gap: 10px; justify-content: flex-end;">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let currentItem = null;

function viewItemDetails(itemId) {
    ajaxRequest(`/api/get_item_details.php?id=${itemId}`, 'GET', null, function(err, response) {
        if (!err && response) {
            let content = `
                <div style="margin-bottom: 20px;">
                    <h3>${response.article_name}</h3>
                    <table style="width: 100%;">
                        <tr>
                            <td><strong>Property No:</strong></td>
                            <td>${response.property_no}</td>
                        </tr>
                        <tr>
                            <td><strong>Description:</strong></td>
                            <td>${response.description || 'N/A'}</td>
                        </tr>
                        <tr>
                            <td><strong>Category:</strong></td>
                            <td>${response.category} / ${response.equipment_name}</td>
                        </tr>
                        <tr>
                            <td><strong>Unit Value:</strong></td>
                            <td>${formatCurrency(response.unit_value)}</td>
                        </tr>
                        <tr>
                            <td><strong>Available Quantity:</strong></td>
                            <td>${response.qty_physical_count} ${response.uom}</td>
                        </tr>
                        <tr>
                            <td><strong>Location:</strong></td>
                            <td>${response.section_name}</td>
                        </tr>
                        <tr>
                            <td><strong>Condition:</strong></td>
                            <td>${response.condition_text || 'Good'}</td>
                        </tr>
                        <tr>
                            <td><strong>Fund Cluster:</strong></td>
                            <td>${response.fund_cluster || 'N/A'}</td>
                        </tr>
                    </table>
                </div>
            `;
            showModal('Item Details', content);
        }
    });
}

function requestItem(itemId) {
    ajaxRequest(`/api/get_item_details.php?id=${itemId}`, 'GET', null, function(err, response) {
        if (!err && response) {
            currentItem = response;
            document.getElementById('request_item_id').value = response.id;
            document.getElementById('item_details').innerHTML = `
                <strong>${response.article_name}</strong><br>
                Property No: ${response.property_no}<br>
                Available: ${response.qty_physical_count} ${response.uom}<br>
                Unit Value: ${formatCurrency(response.unit_value)}
            `;
            document.getElementById('available_qty').innerHTML = `Available: ${response.qty_physical_count}`;
            document.getElementById('quantity').max = response.qty_physical_count;
            document.getElementById('requestModal').style.display = 'block';
        }
    });
}

function submitRequest(e) {
    e.preventDefault();
    
    let formData = {
        item_id: document.getElementById('request_item_id').value,
        quantity: document.getElementById('quantity').value,
        purpose: document.getElementById('purpose').value,
        expected_return: document.getElementById('expected_return').value,
        remarks: document.getElementById('remarks').value
    };
    
    if (formData.quantity > currentItem.qty_physical_count) {
        alert('Quantity requested exceeds available stock');
        return;
    }
    
    ajaxRequest('/api/submit_request.php', 'POST', formData, function(err, response) {
        if (!err && response.success) {
            alert('Request submitted successfully!');
            closeModal();
            location.reload();
        } else {
            alert('Error submitting request: ' + (err ? err.message : 'Unknown error'));
        }
    });
}

function closeModal() {
    document.getElementById('requestModal').style.display = 'none';
    document.getElementById('requestForm').reset();
}
</script>

<?php include '../includes/footer.php'; ?>