<?php
include '../config/database.php';
// Check database connection
if ($conn->connect_error) {
    exit("Database connection failed: " . $conn->connect_error);
}
//Get User respective inventory 
$sql = "SELECT * FROM inventory WHERE id = ? ORDER BY article_name ASC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();







include '../includes/header.php';
?>
<h1>View Inventory</h1>



<!-- table for inventory -->
<table class="table table-striped">
    <thead>
        <tr>
            <th>Article Name</th>
            <th>Description</th>
            <th>Property No</th>
            <th>Unit of Measure</th>
            <th>Unit Value</th>
            <th>Status</th>
            <th>Remarks</th>

            
        </tr>
    </thead>    
    <tbody>
        <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td>
                <?php echo htmlspecialchars($row['article_name']); ?>
                <?php if (!empty($row['description'])): ?>
                    <br><small class="text-muted"><?php echo htmlspecialchars($row['description']); ?></small>
                <?php endif; ?>
            </td>
            <td><?php echo htmlspecialchars($row['description']); ?></td>
            <td><?php echo htmlspecialchars($row['property_no']); ?></td>
            <td><?php echo htmlspecialchars($row['oum']); ?></td>
            <td><?php echo htmlspecialchars($row['qty_property_card']); ?></td>
            <td><?php echo htmlspecialchars($row['Status']); ?></td>
            <td><?php echo htmlspecialchars($row['Remarks']); ?></td>

        </tr>
        <?php endwhile; ?>
    </tbody>
</table>
<?php
include '../includes/footer.php';?> 