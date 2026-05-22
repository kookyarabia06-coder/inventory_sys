<?php
require_once '../config.php';
require_once INCLUDE_PATH . '/auth.php';

// Only allow admin to run this

echo "<h1>Fixing Property Numbers</h1>";

// Get all records with old format
$query = "SELECT id, property_no, barcode_data FROM semi_ppe WHERE property_no REGEXP '^[0-9]{8}-[0-9]+$'";
$result = $conn->query($query);

if ($result->num_rows === 0) {
    echo "<p>No records need updating.</p>";
    exit;
}

echo "<p>Found " . $result->num_rows . " records to update...</p>";

$updated = 0;
while ($row = $result->fetch_assoc()) {
    $old_property = $row['property_no'];
    
    // Convert YYYYMMDD-XXX to YYYY-MM-DD-XXXX
    $year = substr($old_property, 0, 4);
    $month = substr($old_property, 4, 2);
    $day = substr($old_property, 6, 2);
    $seq = substr($old_property, 9);
    
    $new_property = $year . '-' . $month . '-' . $day . '-' . str_pad($seq, 4, '0', STR_PAD_LEFT);
    
    // Update the record
    $stmt = $conn->prepare("UPDATE semi_ppe SET property_no = ? WHERE id = ?");
    $stmt->bind_param("si", $new_property, $row['id']);
    
    if ($stmt->execute()) {
        $updated++;
        echo "<p>Updated: {$old_property} → {$new_property}</p>";
    }
    $stmt->close();
}

echo "<p><strong>Complete! Updated {$updated} records.</strong></p>";
echo "<p><a href='semi_expendable.php'>Go back to Semi-Expendable page</a></p>";
?>