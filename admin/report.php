<?php
// Get the absolute path to the root directory
$root_path = dirname(__DIR__);
// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}

// Handle export requests - BEFORE including header to send headers
$issued_to = isset($_GET['issued_to']) ? sanitize($_GET['issued_to']) : '';
$issued_by = isset($_GET['issued_by']) ? sanitize($_GET['issued_by']) : '';
$export_type = isset($_GET['export']) ? sanitize($_GET['export']) : '';
$date_from = isset($_GET['date_from']) ? sanitize($_GET['date_from']) : '';
$date_to = isset($_GET['date_to']) ? sanitize($_GET['date_to']) : '';
$category = isset($_GET['category']) ? sanitize($_GET['category']) : '';
$condition = isset($_GET['condition']) ? sanitize($_GET['condition']) : '';
$status_filter = isset($_GET['status']) ? sanitize($_GET['status']) : '';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build query filter using prepared statements
$where_clause = "1=1";
$params = [];
$types = "";

if ($date_from) {
    $where_clause .= " AND DATE(i.date_added) >= ?";
    $params[] = $date_from;
    $types .= "s";
}
if ($date_to) {
    $where_clause .= " AND DATE(i.date_added) <= ?";
    $params[] = $date_to;
    $types .= "s";
}
if ($category) {
    $where_clause .= " AND i.category = ?";
    $params[] = $category;
    $types .= "s";
}
if ($condition) {
    $where_clause .= " AND i.condition_text = ?";
    $params[] = $condition;
    $types .= "s";
}
if ($status_filter === 'condemned') {
    $where_clause .= " AND i.condition_text IN ('For Condemn', 'For Disposal', 'Non-Serviceable')";
} elseif ($status_filter === 'serviceable') {
    $where_clause .= " AND i.condition_text IN ('Good', 'Fair', 'Serviceable')";
} elseif ($status_filter === 'under_repair') {
    $where_clause .= " AND i.condition_text = 'Under Repair'";
}

// NEW: Filter by Issued To (matches either assigned user OR allocated user)
if ($issued_to) {
    $where_clause .= " AND (CONCAT(u.firstname, ' ', u.lastname) LIKE ? OR CONCAT(alloc.firstname, ' ', alloc.lastname) LIKE ?)";
    $search_term = "%$issued_to%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

// NEW: Filter by Issued From (who issued the equipment)
if ($issued_by) {
    $where_clause .= " AND CONCAT(issuer.firstname, ' ', issuer.lastname) LIKE ?";
    $params[] = "%$issued_by%";
    $types .= "s";
}

// Get total count for pagination
$count_sql = "SELECT COUNT(*) as total FROM inventory i WHERE $where_clause";
$count_stmt = $conn->prepare($count_sql);
if (!empty($params)) {
    $count_stmt->bind_param($types, ...$params);
}
$count_stmt->execute();
$total_result = $count_stmt->get_result();
$total_items = $total_result->fetch_assoc()['total'];
$total_pages = ceil($total_items / $per_page);

// Get inventory data with user assignments - USING LASTNAME
$query = "SELECT i.*, 
          e.name as equipment_name, 
          s.name as section_name, 
          d.name as department_name,
          b.name as building_name,
          ui.user_id as assigned_user_id,
          CONCAT(u.firstname, ' ', u.lastname) as issued_to_name,
          u.lastname as issued_to_lastname,
          ui.assigned_date,
          ui.status as assignment_status,
          ei.issued_date as issuance_date,
          CONCAT(issuer.firstname, ' ', issuer.lastname) as issued_by_name,
          -- Get the actual name for allocate_to (not the ID)
          alloc.firstname as alloc_firstname,
          alloc.lastname as alloc_lastname,
          CONCAT(alloc.firstname, ' ', alloc.lastname) as allocated_to_name,
          -- Get creator name
          creator.firstname as creator_firstname,
          creator.lastname as creator_lastname,
          CONCAT(creator.firstname, ' ', creator.lastname) as created_by_name,
          -- Get current holder name
          holder.firstname as holder_firstname,
          holder.lastname as holder_lastname,
          CONCAT(holder.firstname, ' ', holder.lastname) as current_holder_name
          FROM inventory i
          LEFT JOIN equipment e ON i.equipment_id = e.id
          LEFT JOIN sections s ON i.section_id = s.id
          LEFT JOIN departments d ON s.department_id = d.id
          LEFT JOIN buildings b ON d.building_id = b.id
          LEFT JOIN user_inventory ui ON i.id = ui.inventory_id AND ui.status = 'active'
          LEFT JOIN users u ON ui.user_id = u.id
          LEFT JOIN equipment_issuance ei ON i.id = ei.inventory_id
          LEFT JOIN users issuer ON ei.issued_by = issuer.id
          -- JOIN for allocate_to (who the item is allocated to)
          LEFT JOIN users alloc ON i.allocate_to = alloc.id
          -- JOIN for created_by
          LEFT JOIN users creator ON i.created_by = creator.id
          -- JOIN for current_holder
          LEFT JOIN users holder ON i.current_holder = holder.id
          WHERE $where_clause
          ORDER BY i.date_added DESC
          LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
$params[] = $per_page;
$params[] = $offset;
$types .= "ii";
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();
$inventory_items = $result ? $result->fetch_all(MYSQLI_ASSOC) : [];

// Helper function to get issued to name
function getIssuedToName($item) {
    if (!empty($item['issued_to_name'])) {
        return $item['issued_to_name'];
    } elseif (!empty($item['alloc_lastname'])) {
        return $item['alloc_firstname'] . ' ' . $item['alloc_lastname'];
    } else {
        return '—';
    }
}

// Calculate summary totals
$summary_query = "SELECT 
                    COUNT(*) as total_items,
                    SUM(i.qty_physical_count) as total_quantity,
                    SUM(i.unit_value * i.qty_physical_count) as total_value,
                    COUNT(CASE WHEN i.condition_text IN ('For Condemn', 'For Disposal', 'Non-Serviceable') THEN 1 END) as condemned_count,
                    COUNT(CASE WHEN ui.user_id IS NOT NULL THEN 1 END) as issued_count
                  FROM inventory i 
                  LEFT JOIN user_inventory ui ON i.id = ui.inventory_id AND ui.status = 'active'
                  WHERE $where_clause";
$summary_stmt = $conn->prepare($summary_query);
$summary_params = array_slice($params, 0, -2);
$summary_types = substr($types, 0, -2);
if (!empty($summary_params)) {
    $summary_stmt->bind_param($summary_types, ...$summary_params);
}
$summary_stmt->execute();
$summary = $summary_stmt->get_result()->fetch_assoc();

// Handle PDF export
if ($export_type === 'pdf') {
    require_once $root_path . '/vendor/autoload.php';
    
    $pdf = new TCPDF('L', 'mm', 'A4');
    $pdf->SetCreator('Inventory System');
    $pdf->SetAuthor('System Admin');
    $pdf->SetTitle('Detailed Inventory Report');
    $pdf->SetMargins(8, 8, 8);
    $pdf->AddPage();
    
    // Header
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'DETAILED INVENTORY REPORT', 0, 1, 'C');
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'Generated: ' . date('Y-m-d H:i:s'), 0, 1, 'R');
    
    if ($date_from || $date_to) {
        $date_range = ($date_from ? date('M d, Y', strtotime($date_from)) : 'Start') . ' to ' . 
                      ($date_to ? date('M d, Y', strtotime($date_to)) : 'Now');
        $pdf->Cell(0, 5, 'Date Range: ' . $date_range, 0, 1, 'L');
    }
    if ($category) {
        $pdf->Cell(0, 5, 'Category: ' . $category, 0, 1, 'L');
    }
    if ($condition) {
        $pdf->Cell(0, 5, 'Condition: ' . $condition, 0, 1, 'L');
    }
    
    // Summary
    $pdf->ln(3);
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(0, 6, 'SUMMARY STATISTICS', 0, 1, 'L');
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(50, 5, 'Total Items: ' . number_format($summary['total_items']), 0, 0, 'L');
    $pdf->Cell(50, 5, 'Total Quantity: ' . number_format($summary['total_quantity']), 0, 0, 'L');
    $pdf->Cell(0, 5, 'Total Value: ₱' . number_format($summary['total_value'], 2), 0, 1, 'L');
    $pdf->Cell(50, 5, 'Condemned Items: ' . number_format($summary['condemned_count']), 0, 0, 'L');
    $pdf->Cell(0, 5, 'Issued Items: ' . number_format($summary['issued_count']), 0, 1, 'L');
    
    $pdf->ln(5);
    
    // Table headers
    $pdf->SetFont('helvetica', 'B', 7);
    $pdf->SetFillColor(200, 220, 255);
    $headers = ['#', 'Property No', 'Article Name', 'Category', 'Condition', 'Qty PC', 'Qty Count', 'Diff', 'Unit Val', 'Total Val', 'Date Added', 'Issued To'];
    $widths = [8, 35, 55, 30, 25, 12, 12, 12, 20, 25, 25, 35];
    
    foreach ($headers as $i => $header) {
        $pdf->Cell($widths[$i], 7, $header, 1, 0, 'C', true);
    }
    $pdf->Ln();

    // Data rows
    $pdf->SetFont('helvetica', '', 6.5);
    $row_num = 1;
    $fill = false;
    
    foreach ($inventory_items as $item) {
        $diff = $item['qty_physical_count'] - $item['qty_property_card'];
        $total_value = $item['unit_value'] * $item['qty_physical_count'];
        // FIXED: Use the helper function for PDF
        $issued_to = getIssuedToName($item);
        
        $pdf->Cell($widths[0], 6, $row_num++, 1, 0, 'C', $fill);
        $pdf->Cell($widths[1], 6, substr($item['property_no'] ?? 'N/A', 0, 20), 1, 0, 'L', $fill);
        $pdf->Cell($widths[2], 6, substr($item['article_name'], 0, 40), 1, 0, 'L', $fill);
        $pdf->Cell($widths[3], 6, substr($item['category'] ?? 'N/A', 0, 20), 1, 0, 'L', $fill);
        $pdf->Cell($widths[4], 6, substr($item['condition_text'] ?? 'N/A', 0, 15), 1, 0, 'L', $fill);
        $pdf->Cell($widths[5], 6, number_format($item['qty_property_card'], 0), 1, 0, 'R', $fill);
        $pdf->Cell($widths[6], 6, number_format($item['qty_physical_count'], 0), 1, 0, 'R', $fill);
        $pdf->Cell($widths[7], 6, number_format($diff, 0), 1, 0, 'R', $fill);
        $pdf->Cell($widths[8], 6, '₱' . number_format($item['unit_value'], 2), 1, 0, 'R', $fill);
        $pdf->Cell($widths[9], 6, '₱' . number_format($total_value, 2), 1, 0, 'R', $fill);
        $pdf->Cell($widths[10], 6, date('Y-m-d', strtotime($item['date_added'])), 1, 0, 'C', $fill);
        $pdf->Cell($widths[11], 6, substr($issued_to, 0, 25), 1, 1, 'L', $fill);
        $fill = !$fill;
    }

    $pdf->Output('detailed_inventory_report_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}

// Handle Excel export
if ($export_type === 'excel') {
    require_once $root_path . '/vendor/autoload.php';
    
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Detailed Inventory Report');

    // Title
    $sheet->setCellValue('A1', 'DETAILED INVENTORY REPORT');
    $sheet->mergeCells('A1:O1');
    $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
    
    // Info
    $sheet->setCellValue('A2', 'Generated: ' . date('Y-m-d H:i:s'));
    $sheet->setCellValue('A3', 'Generated By: ' . ($_SESSION['username'] ?? 'System'));
    
    $row = 4;
    if ($date_from || $date_to) {
        $date_range = ($date_from ? $date_from : 'Start') . ' to ' . ($date_to ? $date_to : 'Now');
        $sheet->setCellValue('A' . $row, 'Date Range: ' . $date_range);
        $row++;
    }
    if ($category) {
        $sheet->setCellValue('A' . $row, 'Category: ' . $category);
        $row++;
    }
    if ($condition) {
        $sheet->setCellValue('A' . $row, 'Condition: ' . $condition);
        $row++;
    }
    
    // Summary section
    $sheet->setCellValue('A' . ($row+1), 'SUMMARY');
    $sheet->getStyle('A' . ($row+1))->getFont()->setBold(true);
    $sheet->setCellValue('A' . ($row+2), 'Total Items:');
    $sheet->setCellValue('B' . ($row+2), $summary['total_items']);
    $sheet->setCellValue('A' . ($row+3), 'Total Quantity:');
    $sheet->setCellValue('B' . ($row+3), $summary['total_quantity']);
    $sheet->setCellValue('A' . ($row+4), 'Total Value:');
    $sheet->setCellValue('B' . ($row+4), $summary['total_value']);
    $sheet->setCellValue('A' . ($row+5), 'Condemned Items:');
    $sheet->setCellValue('B' . ($row+5), $summary['condemned_count']);
    $sheet->setCellValue('A' . ($row+6), 'Issued Items:');
    $sheet->setCellValue('B' . ($row+6), $summary['issued_count']);
    
    $row += 9;

    // Column headers - expanded for detailed report
 $headers = ['ID', 'Property No', 'Article Name', 'Description', 'Category', 'Section', 'Department', 
            'Building', 'Condition', 'Qty (PC)', 'Qty (Count)', 'Difference', 'Unit Value', 'Total Value', 
            'Date Added', 'Issued To', 'Issued Date', 'Remarks'];
    $col = 'A';
    foreach ($headers as $header) {
        $sheet->setCellValue($col . $row, $header);
        $sheet->getStyle($col . $row)->getFont()->setBold(true);
        $sheet->getStyle($col . $row)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->setStartColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFD9E8F5'));
        $col++;
    }

    // Add data
    $data_row = $row + 1;
    foreach ($inventory_items as $item) {
        $diff = $item['qty_physical_count'] - $item['qty_property_card'];
        $total_value = $item['unit_value'] * $item['qty_physical_count'];
        // FIXED: Use the helper function for Excel
        $issued_to = getIssuedToName($item);
        
        $sheet->setCellValue('A' . $data_row, $item['id']);
        $sheet->setCellValue('B' . $data_row, $item['property_no'] ?? 'N/A');
        $sheet->setCellValue('C' . $data_row, $item['article_name']);
        $sheet->setCellValue('D' . $data_row, $item['description'] ?? '');
        $sheet->setCellValue('E' . $data_row, $item['category'] ?? 'N/A');
        $sheet->setCellValue('F' . $data_row, $item['section_name'] ?? 'N/A');
        $sheet->setCellValue('G' . $data_row, $item['department_name'] ?? 'N/A');
        $sheet->setCellValue('H' . $data_row, $item['building_name'] ?? 'N/A');
        $sheet->setCellValue('I' . $data_row, $item['condition_text'] ?? 'N/A');
        $sheet->setCellValue('J' . $data_row, $item['qty_property_card']);
        $sheet->setCellValue('K' . $data_row, $item['qty_physical_count']);
        $sheet->setCellValue('L' . $data_row, $diff);
        $sheet->setCellValue('M' . $data_row, $item['unit_value']);
        $sheet->setCellValue('N' . $data_row, $total_value);
        $sheet->setCellValue('O' . $data_row, date('Y-m-d', strtotime($item['date_added'])));
        $sheet->setCellValue('P' . $data_row, $issued_to);
$sheet->setCellValue('Q' . $data_row, $item['assigned_date'] ? date('Y-m-d', strtotime($item['assigned_date'])) : '');
$sheet->setCellValue('R' . $data_row, $item['remarks'] ?? '');
        $sheet->setCellValue('S' . $data_row, $item['remarks'] ?? '');
        $data_row++;
    }

    // Auto-size columns
    foreach (range('A', 'R') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="detailed_inventory_report_' . date('Y-m-d') . '.xlsx"');
    $writer->save('php://output');
    exit;
}

// Get filter options
$categories_result = $conn->query("SELECT DISTINCT category FROM inventory WHERE category IS NOT NULL AND category != '' ORDER BY category");
$categories = $categories_result ? $categories_result->fetch_all(MYSQLI_ASSOC) : [];

$conditions_result = $conn->query("SELECT DISTINCT condition_text FROM inventory WHERE condition_text IS NOT NULL AND condition_text != '' ORDER BY condition_text");
$conditions = $conditions_result ? $conditions_result->fetch_all(MYSQLI_ASSOC) : [];

// Now include header
$page_title = 'Detailed Reports';
$page_description = 'View detailed inventory data with issuance information';

include INCLUDE_PATH . '/header.php';
?>

<style>
.inventory-table {
    width: 100%;
    border-collapse: collapse;
}

.text-left {
    text-align: left;
}

.text-center {
    text-align: center;
}

.text-right {
    text-align: right;
}

/* Column width suggestions */
.col-sn {
    width: 40px;
    text-align: center;
}

.col-property {
    width: 110px;
    text-align: left;
}

.col-article {
    width: 200px;
    text-align: left;
}

.col-category {
    width: 100px;
    text-align: center;
}

.col-condition {
    width: 100px;
    text-align: center;
}

.col-qty {
    width: 80px;
    text-align: right;
}

.col-diff {
    width: 70px;
    text-align: center;
}

.col-value {
    width: 100px;
    text-align: right;
}

.col-date {
    width: 100px;
    text-align: center;
}

.col-issued {
    width: 120px;
    text-align: left;
}

.col-remarks {
    width: 150px;
    text-align: left;
}

/* Ensure table container handles overflow */
.table-responsive {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
}
:root {
    --primary: #6B8CFF;
    --secondary: #8FB5FF;
    --accent: #F8B0C0;
    --accent-light: #FFD8E0;
    --success-light: #C5E8C5;
    --light: #F0F0F0;
    --white: #FFFFFF;
    --border-light: #E0E0E0;
    --text-primary: #3A3A3A;
    --text-secondary: #6B6B6B;
    --text-muted: #9E9E9E;
    --text-light: #FFFFFF;
    --success: #4CAF50;
    --danger: #f44336;
    --warning: #FF9800;
    --info: #2196F3;
}

body {
    background-color: var(--light);
    color: var(--text-primary);
}

/* Summary Cards */
.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.summary-card {
    background: var(--white);
    border-radius: 12px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    transition: transform 0.2s;
}

.summary-card:hover {
    transform: translateY(-2px);
}

.summary-card .value {
    font-size: 28px;
    font-weight: 700;
    color: var(--primary);
    margin: 10px 0;
}

.summary-card .label {
    color: var(--text-muted);
    font-size: 13px;
}

.summary-card .icon {
    font-size: 32px;
    color: var(--accent);
}

/* Table Container */
.table-container {
    background: var(--white);
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    overflow-x: auto;
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 2px solid var(--accent-light);
    flex-wrap: wrap;
    gap: 15px;
}

.table-header h2 {
    color: var(--primary);
    font-size: 18px;
    margin: 0;
}

/* Filter Section */
.filter-section {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
    align-items: flex-end;
}

.filter-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.filter-group label {
    font-size: 11px;
    color: var(--text-muted);
    font-weight: 500;
}

.filter-section input,
.filter-section select {
    padding: 8px 12px;
    border: 1px solid var(--border-light);
    border-radius: 6px;
    font-size: 13px;
    min-width: 130px;
}

.filter-section input:focus,
.filter-section select:focus {
    outline: none;
    border-color: var(--primary);
}

/* Button Styles */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 8px 16px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-primary {
    background-color: var(--accent);
    color: var(--text-primary);
}

.btn-primary:hover {
    background-color: #e69eb0;
}

.btn-secondary {
    background-color: var(--secondary);
    color: var(--text-light);
}

.btn-secondary:hover {
    background-color: #7a9fe6;
}

.btn-danger {
    background-color: var(--danger);
    color: var(--text-light);
}

.btn-success {
    background-color: var(--success);
    color: var(--text-light);
}

.btn-outline {
    background: transparent;
    border: 1px solid var(--border-light);
    color: var(--text-secondary);
}

.btn-outline:hover {
    border-color: var(--primary);
    color: var(--primary);
}

/* Export Buttons */
.export-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

/* Table Styles */
table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
    min-width: 1200px;
}

thead tr {
    background: linear-gradient(to right, var(--light), var(--white));
    border-bottom: 2px solid var(--accent-light);
}

th {
    padding: 12px 8px;
    text-align: left;
    color: var(--primary);
    font-weight: 600;
    white-space: nowrap;
}

td {
    padding: 12px 8px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
}

tr:hover td {
    background-color: rgba(107, 140, 255, 0.03);
}

/* Badges */
.badge {
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    display: inline-block;
    white-space: nowrap;
}

.badge-info {
    background-color: #E3F2FD;
    color: var(--info);
}

.badge-success {
    background-color: var(--success-light);
    color: var(--success);
}

.badge-danger {
    background-color: #ffebee;
    color: var(--danger);
}

.badge-warning {
    background-color: #FFF3E0;
    color: var(--warning);
}

.badge-secondary {
    background-color: var(--light);
    color: var(--text-secondary);
}

/* Condition specific badges */
.badge-good, .badge-serviceable {
    background-color: var(--success-light);
    color: var(--success);
}

.badge-fair {
    background-color: #FFF3E0;
    color: var(--warning);
}

.badge-poor, .badge-for-condemn, .badge-for-disposal, .badge-non-serviceable {
    background-color: #ffebee;
    color: var(--danger);
}

.badge-under-repair {
    background-color: #E3F2FD;
    color: var(--info);
}

/* Text alignment */
.text-center {
    text-align: center;
}

.text-right {
    text-align: right;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 5px;
    margin-top: 20px;
    flex-wrap: wrap;
}

.pagination a, .pagination span {
    padding: 8px 12px;
    border: 1px solid var(--border-light);
    border-radius: 6px;
    text-decoration: none;
    color: var(--text-secondary);
}

.pagination a:hover {
    background-color: var(--primary);
    color: white;
}

.pagination .active {
    background-color: var(--primary);
    color: white;
}

/* No Data */
.no-data {
    text-align: center;
    padding: 60px 20px;
    color: var(--text-muted);
}

.no-data i {
    font-size: 48px;
    margin-bottom: 15px;
    opacity: 0.5;
}

/* Responsive */
@media (max-width: 768px) {
    .summary-cards {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-section {
        flex-direction: column;
        align-items: stretch;
    }
    
    .filter-group input,
    .filter-group select {
        width: 100%;
    }
}
</style>

<!-- Summary Cards -->
<div class="summary-cards">
    <div class="summary-card">
        <div class="icon"><i class="fas fa-boxes"></i></div>
        <div class="value"><?= number_format((float)($summary['total_items'] ?? 0)) ?></div>
        <div class="label">Total Items</div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="fas fa-cubes"></i></div>
        <div class="value"><?= number_format((float)($summary['total_quantity'] ?? 0)) ?></div>
        <div class="label">Total Quantity</div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="fas fa-chart-line"></i></div>
        <div class="value">₱<?= number_format((float)($summary['total_value'] ?? 0), 2) ?></div>
        <div class="label">Total Value</div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="fas fa-trash-alt"></i></div>
        <div class="value"><?= number_format((float)($summary['condemned_count'] ?? 0)) ?></div>
        <div class="label">Condemned Items</div>
    </div>
    <div class="summary-card">
        <div class="icon"><i class="fas fa-user-check"></i></div>
        <div class="value"><?= number_format((float)($summary['issued_count'] ?? 0)) ?></div>
        <div class="label">Issued Items</div>
    </div>
</div>

<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-file-alt"></i> Detailed Inventory Report</h2>
    </div>

    <!-- Filters -->
    <form method="GET" class="filter-section">
        <div class="filter-group">
            <div class="filter-group">
    <label>Issued To (Person)</label>
    <input type="text" name="issued_to" value="<?= htmlspecialchars($issued_to) ?>" 
           placeholder="Search by name" style="min-width: 180px;">
</div>
<div class="filter-group">
    <label>Issued By (From)</label>
    <input type="text" name="issued_by" value="<?= htmlspecialchars($issued_by) ?>" 
           placeholder="Search by name" style="min-width: 180px;">
</div>
            <label>From Date</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
        </div>
        <div class="filter-group">
            <label>To Date</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
        </div>
        <div class="filter-group">
            <label>Category</label>
            <select name="category">
                <option value="">All Categories</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $category === $cat['category'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cat['category']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Condition</label>
            <select name="condition">
                <option value="">All Conditions</option>
                <?php foreach ($conditions as $cond): ?>
                <option value="<?= htmlspecialchars($cond['condition_text']) ?>" <?= $condition === $cond['condition_text'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($cond['condition_text']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label>Status</label>
            <select name="status">
                <option value="">All Status</option>
                <option value="serviceable" <?= $status_filter === 'serviceable' ? 'selected' : '' ?>>Serviceable</option>
                <option value="condemned" <?= $status_filter === 'condemned' ? 'selected' : '' ?>>Condemned</option>
                <option value="under_repair" <?= $status_filter === 'under_repair' ? 'selected' : '' ?>>Under Repair</option>
            </select>
        </div>
        <div class="filter-group">
            <label>&nbsp;</label>
            <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Filter</button>
        </div>
        <div class="filter-group">
            <label>&nbsp;</label>
            <a href="report.php" class="btn btn-outline"><i class="fas fa-redo"></i> Reset</a>
        </div>
    </form>

    <!-- Export Buttons -->
    <div class="export-buttons">
      <form method="GET" style="display: inline;">
    <?php if ($date_from): ?><input type="hidden" name="date_from" value="<?= htmlspecialchars($date_from) ?>"><?php endif; ?>
    <?php if ($date_to): ?><input type="hidden" name="date_to" value="<?= htmlspecialchars($date_to) ?>"><?php endif; ?>
    <?php if ($category): ?><input type="hidden" name="category" value="<?= htmlspecialchars($category) ?>"><?php endif; ?>
    <?php if ($condition): ?><input type="hidden" name="condition" value="<?= htmlspecialchars($condition) ?>"><?php endif; ?>
    <?php if ($status_filter): ?><input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>"><?php endif; ?>
    <?php if ($issued_to): ?><input type="hidden" name="issued_to" value="<?= htmlspecialchars($issued_to) ?>"><?php endif; ?>
    <?php if ($issued_by): ?><input type="hidden" name="issued_by" value="<?= htmlspecialchars($issued_by) ?>"><?php endif; ?>
    <button type="submit" name="export" value="pdf" class="btn btn-danger">
        <i class="fas fa-file-pdf"></i> Export to PDF
    </button>
    <button type="submit" name="export" value="excel" class="btn btn-success">
        <i class="fas fa-file-excel"></i> Export to Excel
    </button>
</form>
    </div>

      <!-- Report Table -->
    <?php if (empty($inventory_items)): ?>
        <div class="no-data">
            <i class="fas fa-inbox"></i>
            <p>No inventory items found with the current filters</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="inventory-table">
                <thead>
                    <tr>
                        <th class="col-sn">#</th>
                        <th class="col-property">Property No</th>
                        <th class="col-article">Article Name</th>
                        <th class="col-category">Category</th>
                        <th class="col-condition">Condition</th>
                        <th class="col-qty">Qty (PC)</th>
                        <th class="col-qty">Qty (Remaining)</th>
                        <th class="col-diff">Diff</th>
                        <th class="col-value">Unit Value</th>
                        <th class="col-value">Total Value</th>
                        <th class="col-date">Date Added</th>
                        <th class="col-issued">Issued To</th>
                        <th class="col-remarks">Remarks</th>
                    </tr>
                </thead>
                <tbody>
            <?php $counter = $offset + 1; ?>
<?php foreach ($inventory_items as $item): ?>
<?php
    // Cast all numeric values to float to prevent null errors
    $qty_pc = (float)($item['qty_property_card'] ?? 0);
    $qty_count = (float)($item['qty_physical_count'] ?? 0);
    $unit_val = (float)($item['unit_value'] ?? 0);
    
    $diff = $qty_count - $qty_pc;
    if ($diff > 0) {
        $diff_badge = 'badge-success';
        $diff_text = '+' . number_format($diff, 0);
    } elseif ($diff < 0) {
        $diff_badge = 'badge-danger';
        $diff_text = number_format($diff, 0);
    } else {
        $diff_badge = 'badge-secondary';
        $diff_text = number_format($diff, 0);
    }
    $total_value = $unit_val * $qty_count;
    
    // Condition badge class
    $condition_class = 'badge-secondary';
    $condition_lower = strtolower($item['condition_text'] ?? '');
    if (in_array($condition_lower, ['good', 'serviceable'])) {
        $condition_class = 'badge-good';
    } elseif (in_array($condition_lower, ['fair'])) {
        $condition_class = 'badge-fair';
    } elseif (in_array($condition_lower, ['poor', 'for condemn', 'for disposal', 'non-serviceable'])) {
        $condition_class = 'badge-poor';
    } elseif ($condition_lower === 'under repair') {
        $condition_class = 'badge-under-repair';
    }
    
    // Get issued to name
    $issued_to = getIssuedToName($item);
?>
<tr>
    <td class="text-center"><?= $counter++ ?></td>
    <td class="text-left"><code><?= htmlspecialchars($item['property_no'] ?? 'N/A') ?></code></td>
    <td class="text-left"><?= htmlspecialchars(substr($item['article_name'] ?? '', 0, 50)) ?></td>
    <td class="text-center"><span class="badge badge-info"><?= htmlspecialchars($item['category'] ?? 'N/A') ?></span></td>
    <td class="text-center"><span class="badge <?= $condition_class ?>"><?= htmlspecialchars($item['condition_text'] ?? 'N/A') ?></span></td>
    <td class="text-right"><?= number_format($qty_pc, 0) ?></td>
    <td class="text-right"><?= number_format($qty_count, 0) ?></td>
    <td class="text-center"><span class="badge <?= $diff_badge ?>"><?= $diff_text ?></span></td>
    <td class="text-right">₱<?= number_format($unit_val, 2) ?></td>
    <td class="text-right"><strong>₱<?= number_format($total_value, 2) ?></strong></td>
    <td class="text-center"><?= date('Y-m-d', strtotime($item['date_added'] ?? 'now')) ?></td>
    <td class="text-left"><?= htmlspecialchars($issued_to) ?></td>
    <td class="text-left"><?= htmlspecialchars(substr($item['remarks'] ?? '', 0, 30)) ?></td>
</tr>
<?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">&laquo; Prev</a>
            <?php endif; ?>
            
            <?php 
            $start_page = max(1, $page - 2);
            $end_page = min($total_pages, $page + 2);
            for ($i = $start_page; $i <= $end_page; $i++): 
            ?>
                <?php if ($i == $page): ?>
                    <span class="active"><?= $i ?></span>
                <?php else: ?>
                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>"><?= $i ?></a>
                <?php endif; ?>
            <?php endfor; ?>
            
            <?php if ($page < $total_pages): ?>
                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next &raquo;</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php include INCLUDE_PATH . '/footer.php'; ?>