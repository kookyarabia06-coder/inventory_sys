<?php
/**
 * Debug Page - Show all barcodes in system
 */

$root_path = dirname(__FILE__);
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';

requireLogin();

// Get all inventory with barcodes
$items = $conn->query("
    SELECT id, article_name, barcode_data, property_no, qty_physical_count, category, uom
    FROM inventory 
    WHERE qty_physical_count > 0
    ORDER BY barcode_data DESC
");

include INCLUDE_PATH . '/header.php';
?>

<style>
    .debug-table {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    }
    .debug-table table {
        width: 100%;
        border-collapse: collapse;
    }
    .debug-table th, .debug-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #E0E0E0;
    }
    .debug-table th {
        background: #6B8CFF;
        color: white;
        font-weight: 600;
    }
    .debug-table tr:hover {
        background: #F8F9FA;
    }
    .no-barcode {
        background: #fff3cd;
        color: #856404;
    }
    .barcode-code {
        font-family: monospace;
        background: #f0f0f0;
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
    }
    .alert-warning {
        background: #FFF3E0;
        border-left: 4px solid #FF9800;
        padding: 15px;
        margin-bottom: 20px;
        border-radius: 4px;
    }
</style>

<div class="debug-table">
    <h2><i class="fas fa-barcode"></i> Barcode Inventory Debug</h2>
    
    <div class="alert-warning">
        <strong>ℹ️ Info:</strong> This page shows all items in your system with their barcodes. Use this to verify that your barcodes are stored correctly.
    </div>

    <?php
    $total_items = 0;
    $items_with_barcode = 0;
    
    if ($items && $items->num_rows > 0):
        while($item = $items->fetch_assoc()) {
            $total_items++;
            if ($item['barcode_data']) $items_with_barcode++;
        }
        $items->data_seek(0); // Reset pointer
    ?>
    
    <div style="margin-bottom: 20px;">
        <p><strong>Total Items:</strong> <?php echo $total_items; ?> | <strong>Items with Barcodes:</strong> <?php echo $items_with_barcode; ?></p>
    </div>
    
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Article Name</th>
                <th>Barcode Data</th>
                <th>Property No</th>
                <th>Category</th>
                <th>Available Qty</th>
            </tr>
        </thead>
        <tbody>
            <?php while($item = $items->fetch_assoc()): ?>
            <tr <?php echo !$item['barcode_data'] ? 'class="no-barcode"' : ''; ?>>
                <td><?php echo $item['id']; ?></td>
                <td><?php echo htmlspecialchars($item['article_name']); ?></td>
                <td>
                    <?php if ($item['barcode_data']): ?>
                        <span class="barcode-code"><?php echo htmlspecialchars($item['barcode_data']); ?></span>
                    <?php else: ?>
                        <span style="color: #999;">— No barcode —</span>
                    <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($item['property_no'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($item['category'] ?? 'N/A'); ?></td>
                <td><?php echo $item['qty_physical_count'] . ' ' . ($item['uom'] ?? 'pcs'); ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
    
    <?php else: ?>
    <div class="alert-warning">
        No items found in inventory
    </div>
    <?php endif; ?>
</div>

<script>
    console.log('To test barcode scanner:');
    console.log('1. Open the Issue Items page');
    console.log('2. Open browser console (F12)');
    console.log('3. Scan a barcode with your scanner');
    console.log('4. Check the console output for debug information');
</script>

<?php include INCLUDE_PATH . '/footer.php'; ?>
