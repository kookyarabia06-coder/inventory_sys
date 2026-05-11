<?php
/**
 * All Inventory Page (Admin)
 * Complete inventory management system - view and manage all inventory items
 */

// Add output buffering at the very top to prevent header warnings
ob_start();

// Get the absolute path to the root directory
$root_path = dirname(__DIR__);

// Load configuration and auth
require_once $root_path . '/config.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/functions.php';

// Include barcode library
require_once $root_path . '/vendor/autoload.php';
use Picqer\Barcode\BarcodeGeneratorPNG as BarcodeGen;

// Require admin role
requireRole('admin' || 'superadmin' || 'supply');

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = 'All Inventory';
$page_description = 'View and manage all inventory items';

// ============================================
// GET LOW STOCK THRESHOLD FROM SETTINGS
// ============================================
$threshold_result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'low_stock_threshold'");
$low_stock_threshold = 5;
if ($threshold_result && $threshold_result->num_rows > 0) {
    $low_stock_threshold = intval($threshold_result->fetch_assoc()['setting_value']);
}
$critical_threshold = max(1, floor($low_stock_threshold / 2));

// Get equipment types for dropdown
$equipment = $conn->query("SELECT * FROM equipment ORDER BY name");
$type_of_equipment = $conn->query("SELECT id, code, name FROM type_of_equipment ORDER BY code");
$sections = $conn->query("SELECT s.*, d.name as department_name FROM sections s LEFT JOIN departments d ON s.department_id = d.id ORDER BY d.name, s.name");

// Get distinct categories, conditions, locations for filter dropdowns
$categories = $conn->query("SELECT DISTINCT category FROM inventory WHERE category IS NOT NULL AND category != '' ORDER BY category");
$conditions = $conn->query("SELECT DISTINCT condition_text FROM inventory WHERE condition_text IS NOT NULL AND condition_text != '' ORDER BY condition_text");

// ============================================
// AJAX HANDLERS
// ============================================

if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_item' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT i.*, e.name as equipment_name, s.name as section_name, toe.name as type_equipment_name, (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') as is_issued FROM inventory i LEFT JOIN equipment e ON i.equipment_id = e.id LEFT JOIN sections s ON i.section_id = s.id LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id WHERE i.id = ? LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    echo json_encode(['success' => true, 'data' => $item]);
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_edit_item' && isset($_GET['id'])) {
    header('Content-Type: application/json');
    $id = (int)$_GET['id'];
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $item = $result->fetch_assoc();
    $stmt->close();
    echo json_encode(['success' => true, 'data' => $item]);
    exit;
}

if (isset($_GET['get_multiple_items'])) {
    header('Content-Type: application/json');
    $property_no = $_GET['property_no'] ?? '';
    if (empty($property_no)) {
        echo json_encode(['error' => 'No property number provided']);
        exit;
    }
    $base_property = preg_replace('/-\d+$/', '', $property_no);
    $stmt = $conn->prepare("SELECT i.*, e.name as equipment_name FROM inventory i LEFT JOIN equipment e ON i.equipment_id = e.id WHERE i.property_no LIKE ? ORDER BY i.property_no");
    $like_pattern = $base_property . '%';
    $stmt->bind_param("s", $like_pattern);
    $stmt->execute();
    $result = $stmt->get_result();
    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = ['id' => $row['id'], 'property_no' => $row['property_no'], 'article_name' => $row['article_name'], 'barcode_data' => $row['barcode_data'], 'quantity' => $row['qty_physical_count'], 'uom' => $row['uom'], 'unit_value' => $row['unit_value']];
    }
    $stmt->close();
    echo json_encode(['success' => true, 'items' => $items, 'count' => count($items), 'base_property' => $base_property]);
    exit;
}

if (isset($_GET['generate_barcode'])) {
    ob_clean();
    header('Content-Type: application/json');
    $barcode_value = $_GET['barcode_value'] ?? '';
    if (empty($barcode_value)) {
        echo json_encode(['error' => 'Please provide barcode value']);
        exit;
    }
    try {
        $generator = new BarcodeGen();
        $barcode = base64_encode($generator->getBarcode($barcode_value, $generator::TYPE_CODE_128));
        echo json_encode(['success' => true, 'barcode' => $barcode, 'value' => $barcode_value]);
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

// ============================================
// FORM HANDLERS
// ============================================

if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    if (!isset($_GET['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_GET['csrf_token'])) {
        die('Invalid CSRF token');
    }
    $id = (int)$_GET['delete'];
    $stmt = $conn->prepare("SELECT id FROM equipment_issuance WHERE inventory_id = ? AND status = 'issued' LIMIT 1");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $check = $stmt->get_result();
    if ($check && $check->num_rows > 0) {
        $_SESSION['error'] = "Cannot delete item that is currently issued";
    } else {
        $stmt2 = $conn->prepare("DELETE FROM inventory WHERE id = ?");
        $stmt2->bind_param("i", $id);
        if ($stmt2->execute() && $stmt2->affected_rows > 0) {
            $_SESSION['success'] = "Inventory item deleted successfully";
        } else {
            $_SESSION['error'] = "Error deleting item or item not found";
        }
        $stmt2->close();
    }
    $stmt->close();
    header('Location: ' . SITE_URL . '/admin/all_inventory.php');
    exit();
}

// ============================================
// DISPLAY DATA
// ============================================

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 20;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$low_stock_filter = isset($_GET['low_stock']) && $_GET['low_stock'] == 1;
$filter_category = isset($_GET['filter_category']) ? sanitize($_GET['filter_category']) : '';
$filter_condition = isset($_GET['filter_condition']) ? sanitize($_GET['filter_condition']) : '';
$filter_location = isset($_GET['filter_location']) ? (int)$_GET['filter_location'] : 0;
$filter_status = isset($_GET['filter_status']) ? sanitize($_GET['filter_status']) : '';
$sort_by = isset($_GET['sort_by']) ? sanitize($_GET['sort_by']) : 'id';
$sort_order = isset($_GET['sort_order']) && $_GET['sort_order'] == 'ASC' ? 'ASC' : 'DESC';

$query = "SELECT i.*, e.name as equipment_name, s.name as section_name, toe.name as type_equipment_name, (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') as is_issued, CASE WHEN i.property_no LIKE '%-%' THEN 1 ELSE 0 END as is_multiple FROM inventory i LEFT JOIN equipment e ON i.equipment_id = e.id LEFT JOIN sections s ON i.section_id = s.id LEFT JOIN type_of_equipment toe ON i.type_equipment_id = toe.id WHERE 1=1";
$count_query = "SELECT COUNT(*) as total FROM inventory WHERE 1=1";
$params = [];
$types = "";

if ($low_stock_filter) {
    $query .= " AND i.qty_physical_count <= $low_stock_threshold";
    $count_query .= " AND qty_physical_count <= $low_stock_threshold";
}
if (!empty($filter_category)) {
    $query .= " AND i.category = ?";
    $count_query .= " AND category = ?";
    $params[] = $filter_category;
    $types .= "s";
}
if (!empty($filter_condition)) {
    $query .= " AND i.condition_text = ?";
    $count_query .= " AND condition_text = ?";
    $params[] = $filter_condition;
    $types .= "s";
}
if ($filter_location > 0) {
    $query .= " AND i.section_id = ?";
    $count_query .= " AND section_id = ?";
    $params[] = $filter_location;
    $types .= "i";
}
if (!empty($filter_status)) {
    if ($filter_status == 'issued') {
        $query .= " AND (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') > 0";
        $count_query .= " AND (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') > 0";
    } elseif ($filter_status == 'available') {
        $query .= " AND (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') = 0";
        $count_query .= " AND (SELECT COUNT(*) FROM equipment_issuance WHERE inventory_id = i.id AND status = 'issued') = 0";
    }
}
if ($search) {
    $query .= " AND (i.article_name LIKE ? OR i.property_no LIKE ? OR i.description LIKE ?)";
    $count_query .= " AND (article_name LIKE ? OR property_no LIKE ? OR description LIKE ?)";
    $search_term = "%$search%";
    $params = array_merge($params, [$search_term, $search_term, $search_term]);
    $types .= "sss";
}

// Add sorting
$allowed_sort = ['id', 'article_name', 'property_no', 'qty_physical_count', 'unit_value'];
if (!in_array($sort_by, $allowed_sort)) {
    $sort_by = 'id';
}
$query .= " ORDER BY i.$sort_by $sort_order";

$stmt = $conn->prepare($count_query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$total_result = $stmt->get_result();
$total_rows = $total_result->fetch_assoc()['total'];
$stmt->close();

$offset = ($page - 1) * $per_page;
$query .= " LIMIT ? OFFSET ?";

$stmt = $conn->prepare($query);
if (!empty($params)) {
    $all_params = array_merge($params, [$per_page, $offset]);
    $all_types = $types . "ii";
    $stmt->bind_param($all_types, ...$all_params);
} else {
    $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$result = $stmt->get_result();

$inventory_items = [];
while ($row = $result->fetch_assoc()) {
    $inventory_items[] = $row;
}
$stmt->close();

$total_items = $total_rows;
$issued_items = 0;
$low_stock = 0;
$total_value = 0;
foreach ($inventory_items as $item) {
    if ($item['is_issued'] > 0) $issued_items++;
    if ($item['qty_physical_count'] <= $low_stock_threshold) $low_stock++;
    $total_value += $item['unit_value'] * $item['qty_physical_count'];
}

include INCLUDE_PATH . '/header.php';
?>

<style>
* {
    box-sizing: border-box;
}

:root {
    --primary: #6B8CFF;
    --secondary: #8FB5FF;
    --accent: #F8B0C0;
    --accent-light: #FFD8E0;
    --success-light: #C5E8C5;
    --light: #F5F7FA;
    --white: #FFFFFF;
    --border-light: #E2E8F0;
    --text-primary: #1E293B;
    --text-secondary: #475569;
    --text-muted: #94A3B8;
    --text-light: #FFFFFF;
    --success: #10B981;
    --danger: #EF4444;
    --warning: #F59E0B;
    --info: #3B82F6;
}

body {
    background-color: var(--light);
    color: var(--text-primary);
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

/* Dashboard Cards */
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.card {
    background: var(--white);
    border-radius: 16px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
    border-left: 4px solid var(--primary);
    transition: all 0.2s ease;
}

.card:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1);
}

.card-icon {
    width: 48px;
    height: 48px;
    background: var(--accent-light);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 12px;
}

.card-icon i {
    font-size: 22px;
    color: var(--primary);
}

.card h3 {
    color: var(--text-secondary);
    font-size: 13px;
    margin-bottom: 8px;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.card .card-value {
    color: var(--primary);
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 5px;
}

.card .card-label {
    color: var(--text-muted);
    font-size: 12px;
}

.card-link {
    display: inline-block;
    margin-top: 8px;
    font-size: 11px;
    color: var(--secondary);
    text-decoration: none;
}

.card-link:hover {
    color: var(--primary);
    text-decoration: underline;
}

.text-warning {
    color: var(--warning) !important;
}

/* Table Container */
.table-container {
    background: var(--white);
    border-radius: 16px;
    padding: 20px;
    margin-bottom: 30px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--accent-light);
    flex-wrap: wrap;
    gap: 10px;
}

.table-header h2 {
    color: var(--primary);
    font-size: 18px;
    margin: 0;
    font-weight: 600;
}

.table-header h2 i {
    color: var(--accent);
    margin-right: 10px;
}

.table-header p {
    color: var(--text-muted);
    font-size: 13px;
    margin: 0;
}

/* SIMPLE FILTER BAR - NO BACKGROUND */
.filter-bar-simple {
    margin-bottom: 20px;
    padding: 0;
    background: transparent;
}

.filter-row-simple {
    display: flex;
    flex-wrap: wrap;
    gap: 15px;
    align-items: flex-end;
    margin-bottom: 15px;
}

.filter-group-simple {
    flex: 1;
    min-width: 140px;
}

.filter-group-simple label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 6px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-group-simple select,
.filter-group-simple input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 13px;
    background: var(--white);
    transition: all 0.2s;
}

.filter-group-simple select:focus,
.filter-group-simple input:focus {
    outline: none;
    border-color: var(--primary);
}

.filter-actions-simple {
    display: flex;
    gap: 8px;
    align-items: center;
}

.btn-filter-simple {
    padding: 8px 20px;
    background: var(--primary);
    color: white;
    border: none;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-filter-simple:hover {
    background: #5a7ae6;
}

.btn-reset-simple {
    padding: 8px 16px;
    background: transparent;
    color: var(--text-secondary);
    border: 1px solid var(--border-light);
    border-radius: 8px;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-reset-simple:hover {
    background: var(--light);
    border-color: var(--danger);
    color: var(--danger);
}

/* Sort Row */
.sort-row {
    display: flex;
    align-items: center;
    gap: 15px;
    padding-top: 15px;
    border-top: 1px solid var(--border-light);
    flex-wrap: wrap;
}

.sort-row label {
    font-size: 12px;
    color: var(--text-muted);
}

.sort-row select {
    padding: 6px 10px;
    border: 1px solid var(--border-light);
    border-radius: 6px;
    font-size: 12px;
    background: var(--white);
}

.sort-row button {
    padding: 6px 14px;
    background: transparent;
    color: var(--primary);
    border: 1px solid var(--primary);
    border-radius: 6px;
    font-size: 11px;
    cursor: pointer;
    transition: all 0.2s;
}

.sort-row button:hover {
    background: var(--primary);
    color: white;
}

/* Active Filters Tags */
.active-filters-simple {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid var(--border-light);
}

.filter-tag-simple {
    background: var(--accent-light);
    padding: 4px 12px;
    border-radius: 20px;
    font-size: 11px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.filter-tag-simple i {
    cursor: pointer;
    font-size: 10px;
    color: var(--text-muted);
}

.filter-tag-simple i:hover {
    color: var(--danger);
}

/* Search Box */
.search-box {
    display: flex;
    gap: 12px;
    margin-bottom: 0;
    flex-wrap: wrap;
}

.search-box input[type="text"] {
    padding: 12px 16px;
    border: 1px solid var(--border-light);
    border-radius: 12px;
    font-size: 14px;
    flex: 1;
    min-width: 200px;
    transition: all 0.2s;
}

.search-box input[type="text"]:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.1);
}

.search-box button {
    padding: 12px 24px;
    background: var(--primary);
    color: var(--text-light);
    border: none;
    border-radius: 12px;
    font-size: 14px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
}

.search-box button:hover {
    background: #5a7ae6;
}

/* Low Stock Banner */
.low-stock-banner {
    background: #FEF3C7;
    border-left: 4px solid var(--warning);
    padding: 12px 16px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
}

.low-stock-banner .btn-clear {
    background: white;
    color: var(--warning);
    border: none;
    padding: 5px 12px;
    border-radius: 6px;
    font-size: 12px;
    cursor: pointer;
}

/* Inventory Table */
.inventory-wrapper {
    overflow-x: auto;
    border-radius: 12px;
    border: 1px solid var(--border-light);
}

.inventory-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
    min-width: 1100px;
}

.inventory-table thead {
    background: var(--light);
}

.inventory-table th {
    padding: 14px 12px;
    text-align: left;
    color: var(--text-secondary);
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--accent-light);
    white-space: nowrap;
}

.inventory-table td {
    padding: 14px 12px;
    border-bottom: 1px solid var(--border-light);
    color: var(--text-secondary);
    vertical-align: top;
}

.inventory-table tbody tr:hover {
    background-color: var(--light);
}

/* Stock Highlighting */
.stock-critical-row {
    background-color: #FEE2E2 !important;
}

.stock-critical-row:hover {
    background-color: #FECACA !important;
}

.stock-warning-row {
    background-color: #FEF3C7 !important;
}

.stock-warning-row:hover {
    background-color: #FDE68A !important;
}

.quantity-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 12px;
    font-weight: 600;
}

.quantity-critical {
    background: #FEE2E2;
    color: #DC2626;
}

.quantity-warning {
    background: #FEF3C7;
    color: #D97706;
}

.quantity-normal {
    background: #D1FAE5;
    color: #059669;
}

/* Article Name */
.article-name {
    font-weight: 600;
    color: var(--text-primary);
    margin-bottom: 4px;
}

.article-description {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 4px;
}

/* Barcode */
.barcode-img {
    max-width: 100px;
    height: auto;
    cursor: pointer;
    border: 1px solid var(--border-light);
    padding: 3px;
    border-radius: 6px;
    background: var(--white);
    display: inline-block;
}

.barcode-img:hover {
    border-color: var(--accent);
}

.barcode-text {
    font-family: monospace;
    font-size: 10px;
    color: var(--text-muted);
    margin-top: 4px;
    display: block;
}

/* Type */
.type-main {
    font-weight: 500;
    color: var(--text-primary);
}

.type-sub {
    font-size: 11px;
    color: var(--text-muted);
    margin-top: 2px;
}

.unit-value {
    font-weight: 500;
    color: var(--text-primary);
    white-space: nowrap;
}

.location-text {
    color: var(--text-secondary);
}

/* Badges */
.condition-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
}

.condition-new { background: #D1FAE5; color: #059669; }
.condition-good { background: #DBEAFE; color: #2563EB; }
.condition-fair { background: #FEF3C7; color: #D97706; }
.condition-poor { background: #FEE2E2; color: #DC2626; }
.condition-serviceable, .condition-servicable { background: #D1FAE5; color: #059669; }

.status-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 20px;
    font-size: 11px;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
}

.status-issued {
    background: #FEF3C7;
    color: #D97706;
}

.status-available {
    background: #D1FAE5;
    color: #059669;
}

/* Action Buttons */
.action-buttons {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    border: none;
    color: var(--text-light);
    text-decoration: none;
    transition: all 0.2s;
    cursor: pointer;
    font-size: 13px;
}

.action-btn.view { background-color: var(--primary); }
.action-btn.edit { background-color: var(--secondary); }
.action-btn.barcode { background-color: var(--accent); color: var(--text-primary); }
.action-btn.issue { background-color: var(--success); }
.action-btn.delete { background-color: var(--danger); }

.action-btn:hover {
    transform: translateY(-2px);
    filter: brightness(0.95);
}

/* Modal */
.modal {
    display: none;
    position: fixed;
    z-index: 1000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0,0,0,0.5);
    backdrop-filter: blur(4px);
}

.modal-content {
    background: var(--white);
    margin: 5% auto;
    border-radius: 20px;
    box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25);
    animation: modalSlideIn 0.3s ease;
    max-width: 800px;
    width: 90%;
    max-height: 85vh;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

@keyframes modalSlideIn {
    from { transform: translateY(-30px); opacity: 0; }
    to { transform: translateY(0); opacity: 1; }
}

.modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px 24px;
    border-bottom: 1px solid var(--border-light);
}

.modal-header h2 {
    color: var(--primary);
    font-size: 18px;
    margin: 0;
    font-weight: 600;
}

.modal-close {
    font-size: 28px;
    cursor: pointer;
    color: var(--text-muted);
    transition: color 0.2s;
    line-height: 1;
}

.modal-close:hover {
    color: var(--danger);
}

.modal-body {
    padding: 24px;
    overflow-y: auto;
    flex: 1;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border-light);
    text-align: right;
    background: var(--light);
    display: flex;
    gap: 12px;
    justify-content: flex-end;
}

/* Buttons */
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 20px;
    border: none;
    border-radius: 10px;
    font-size: 13px;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s;
    text-decoration: none;
}

.btn-primary {
    background: linear-gradient(135deg, var(--accent) 0%, #e69eb0 100%);
    color: var(--text-primary);
}

.btn-primary:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(248, 176, 192, 0.4);
}

.btn-secondary {
    background-color: var(--secondary);
    color: var(--text-light);
}

.btn-secondary:hover {
    background-color: #7a9fe6;
}

.btn-sm {
    padding: 6px 12px;
    font-size: 11px;
}

/* Detail View */
.detail-section {
    margin-bottom: 24px;
    border: 1px solid var(--border-light);
    border-radius: 12px;
    overflow: hidden;
}

.detail-header {
    background: var(--light);
    padding: 12px 16px;
    font-weight: 600;
    color: var(--primary);
    border-bottom: 1px solid var(--border-light);
}

.detail-header i {
    margin-right: 8px;
}

.detail-content {
    padding: 16px;
}

.detail-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
}

.detail-item {
    padding: 8px 0;
}

.detail-label {
    font-weight: 600;
    color: var(--text-muted);
    margin-bottom: 4px;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.detail-value {
    color: var(--text-primary);
    font-size: 14px;
    word-break: break-word;
}

/* Pagination */
.pagination {
    display: flex;
    justify-content: center;
    gap: 6px;
    margin-top: 30px;
    flex-wrap: wrap;
}

.pagination a,
.pagination span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 36px;
    height: 36px;
    padding: 0 10px;
    border-radius: 10px;
    background: var(--white);
    color: var(--text-secondary);
    text-decoration: none;
    border: 1px solid var(--border-light);
    transition: all 0.2s;
    font-size: 13px;
}

.pagination a:hover {
    background: var(--primary);
    color: var(--text-light);
    border-color: var(--primary);
}

.pagination .active {
    background: var(--primary);
    color: var(--text-light);
    border-color: var(--primary);
}

/* Sticky Scan Button */
.sticky-scan-button-container {
    position: sticky;
    bottom: 30px;
    display: flex;
    justify-content: flex-end;
    margin-top: -50px;
    padding-right: 20px;
    padding-bottom: 20px;
    pointer-events: none;
    z-index: 100;
}

.sticky-scan-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 14px 28px;
    background: linear-gradient(135deg, var(--accent) 0%, #e69eb0 100%);
    color: var(--text-primary);
    font-weight: 600;
    font-size: 14px;
    border-radius: 50px;
    text-decoration: none;
    box-shadow: 0 10px 25px -5px rgba(248, 176, 192, 0.5);
    border: 2px solid white;
    pointer-events: auto;
    transition: all 0.3s ease;
}

.sticky-scan-button:hover {
    transform: translateY(-3px) scale(1.02);
}

/* Alert */
.alert {
    padding: 14px 18px;
    border-radius: 12px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 12px;
    border-left: 4px solid transparent;
    font-size: 13px;
}

.alert-success { background-color: #ECFDF5; color: #059669; border-left-color: #10B981; }
.alert-danger { background-color: #FEF2F2; color: #DC2626; border-left-color: #EF4444; }

.text-muted { color: var(--text-muted) !important; }
.text-center { text-align: center; }
.mt-3 { margin-top: 16px; }

/* Responsive */
@media (max-width: 768px) {
    .dashboard-cards {
        grid-template-columns: repeat(2, 1fr);
        gap: 12px;
    }
    
    .filter-row-simple {
        flex-direction: column;
    }
    
    .filter-group-simple {
        width: 100%;
    }
    
    .filter-actions-simple {
        width: 100%;
    }
    
    .btn-filter-simple, .btn-reset-simple {
        flex: 1;
    }
    
    .search-box {
        flex-direction: column;
    }
    
    .modal-content {
        margin: 10px;
        width: auto;
        max-height: 90vh;
    }
    
    .detail-grid {
        grid-template-columns: 1fr;
        gap: 8px;
    }
}
</style>

<!-- Display Success/Error Messages -->
<?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
    </div>
<?php endif; ?>

<?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
    </div>
<?php endif; ?>

<!-- Statistics Cards -->
<div class="dashboard-cards">
    <div class="card">
        <div class="card-icon"><i class="fas fa-boxes"></i></div>
        <h3>Total Items</h3>
        <div class="card-value"><?php echo number_format($total_items); ?></div>
        <div class="card-label">All inventory items</div>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-hand-holding"></i></div>
        <h3>Issued Items</h3>
        <div class="card-value"><?php echo number_format($issued_items); ?></div>
        <div class="card-label">Currently issued</div>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h3>Low Stock</h3>
        <div class="card-value <?php echo $low_stock > 0 ? 'text-warning' : ''; ?>"><?php echo number_format($low_stock); ?></div>
        <div class="card-label">Need reorder (≤<?php echo $low_stock_threshold; ?>)</div>
        <?php if ($low_stock > 0 && !$low_stock_filter): ?>
        <a href="?low_stock=1" class="card-link">View low stock items →</a>
        <?php endif; ?>
    </div>
    
    <div class="card">
        <div class="card-icon"><i class="fas fa-dollar-sign"></i></div>
        <h3>Total Value</h3>
        <div class="card-value">₱<?php echo number_format($total_value, 2); ?></div>
        <div class="card-label">Inventory value</div>
    </div>
</div>

<!-- Low Stock Filter Banner -->
<?php if ($low_stock_filter): ?>
<div class="low-stock-banner">
    <div>
        <i class="fas fa-exclamation-triangle"></i> 
        <strong>Showing low stock items</strong> (Quantity ≤ <?php echo $low_stock_threshold; ?> units)
    </div>
    <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php" class="btn-clear">
        <i class="fas fa-times"></i> Clear Filter
    </a>
</div>
<?php endif; ?>

<!-- ============================================ -->
<!-- FILTER BAR - MOVED ABOVE SEARCH -->
<!-- ============================================ -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-filter"></i> Filter Inventory</h2>
    </div>
    
    <div class="filter-bar-simple">
        <form method="GET" action="" id="filterForm">
            <?php if ($search): ?>
                <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
            <?php endif; ?>
            <?php if ($low_stock_filter): ?>
                <input type="hidden" name="low_stock" value="1">
            <?php endif; ?>
            
            <div class="filter-row-simple">
                <div class="filter-group-simple">
                    <label><i class="fas fa-tag"></i> CATEGORY</label>
                    <select name="filter_category">
                        <option value="">All Categories</option>
                        <?php if ($categories && $categories->num_rows > 0): ?>
                            <?php while($cat = $categories->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($cat['category']); ?>" 
                                    <?php echo $filter_category == $cat['category'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat['category']); ?>
                                </option>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="filter-group-simple">
                    <label><i class="fas fa-tools"></i> CONDITION</label>
                    <select name="filter_condition">
                        <option value="">All Conditions</option>
                        <option value="Serviceable" <?php echo $filter_condition == 'Serviceable' ? 'selected' : ''; ?>>Serviceable</option>
                        <option value="Good" <?php echo $filter_condition == 'Good' ? 'selected' : ''; ?>>Good</option>
                        <option value="Fair" <?php echo $filter_condition == 'Fair' ? 'selected' : ''; ?>>Fair</option>
                        <option value="Poor" <?php echo $filter_condition == 'Poor' ? 'selected' : ''; ?>>Poor</option>
                        <option value="New" <?php echo $filter_condition == 'New' ? 'selected' : ''; ?>>New</option>
                        <?php if ($conditions && $conditions->num_rows > 0): ?>
                            <?php while($cond = $conditions->fetch_assoc()): ?>
                                <?php if (!in_array($cond['condition_text'], ['Serviceable', 'Good', 'Fair', 'Poor', 'New'])): ?>
                                <option value="<?php echo htmlspecialchars($cond['condition_text']); ?>" 
                                    <?php echo $filter_condition == $cond['condition_text'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cond['condition_text']); ?>
                                </option>
                                <?php endif; ?>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="filter-group-simple">
                    <label><i class="fas fa-map-marker-alt"></i> LOCATION</label>
                    <select name="filter_location">
                        <option value="0">All Locations</option>
                        <?php 
                        $sections_query = $conn->query("SELECT id, name FROM sections ORDER BY name");
                        if ($sections_query && $sections_query->num_rows > 0):
                            while($loc = $sections_query->fetch_assoc()): 
                        ?>
                            <option value="<?php echo $loc['id']; ?>" 
                                <?php echo $filter_location == $loc['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($loc['name']); ?>
                            </option>
                        <?php 
                            endwhile;
                        endif; 
                        ?>
                    </select>
                </div>
                
                <div class="filter-group-simple">
                    <label><i class="fas fa-circle"></i> STATUS</label>
                    <select name="filter_status">
                        <option value="">All Status</option>
                        <option value="available" <?php echo $filter_status == 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="issued" <?php echo $filter_status == 'issued' ? 'selected' : ''; ?>>Issued</option>
                    </select>
                </div>
                
                <div class="filter-actions-simple">
                    <button type="submit" class="btn-filter-simple">
                        <i class="fas fa-search"></i> Apply Filters
                    </button>
                    <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php" class="btn-reset-simple">
                        <i class="fas fa-undo"></i> Reset
                    </a>
                </div>
            </div>
            
            <div class="sort-row">
                <label><i class="fas fa-sort"></i> Sort By:</label>
                <select name="sort_by">
                    <option value="id" <?php echo $sort_by == 'id' ? 'selected' : ''; ?>>Date Added (Recent)</option>
                    <option value="article_name" <?php echo $sort_by == 'article_name' ? 'selected' : ''; ?>>Article Name</option>
                    <option value="property_no" <?php echo $sort_by == 'property_no' ? 'selected' : ''; ?>>Property No.</option>
                    <option value="qty_physical_count" <?php echo $sort_by == 'qty_physical_count' ? 'selected' : ''; ?>>Quantity</option>
                    <option value="unit_value" <?php echo $sort_by == 'unit_value' ? 'selected' : ''; ?>>Unit Value</option>
                </select>
                <select name="sort_order">
                    <option value="DESC" <?php echo $sort_order == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                    <option value="ASC" <?php echo $sort_order == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                </select>
                <button type="submit" form="filterForm">
                    <i class="fas fa-arrow-down-wide-short"></i> Sort
                </button>
            </div>
            
            <!-- Active Filters Display -->
            <?php if ($filter_category || $filter_condition || $filter_location || $filter_status): ?>
            <div class="active-filters-simple">
                <span style="font-size: 11px; color: var(--text-muted);"><i class="fas fa-filter"></i> Active filters:</span>
                <?php if ($filter_category): ?>
                    <span class="filter-tag-simple">
                        Category: <?php echo htmlspecialchars($filter_category); ?>
                        <i class="fas fa-times" onclick="removeFilter('filter_category')"></i>
                    </span>
                <?php endif; ?>
                <?php if ($filter_condition): ?>
                    <span class="filter-tag-simple">
                        Condition: <?php echo htmlspecialchars($filter_condition); ?>
                        <i class="fas fa-times" onclick="removeFilter('filter_condition')"></i>
                    </span>
                <?php endif; ?>
                <?php if ($filter_location > 0): 
                    $loc_name = $conn->query("SELECT name FROM sections WHERE id = $filter_location")->fetch_assoc();
                ?>
                    <span class="filter-tag-simple">
                        Location: <?php echo htmlspecialchars($loc_name['name'] ?? 'Unknown'); ?>
                        <i class="fas fa-times" onclick="removeFilter('filter_location')"></i>
                    </span>
                <?php endif; ?>
                <?php if ($filter_status): ?>
                    <span class="filter-tag-simple">
                        Status: <?php echo ucfirst($filter_status); ?>
                        <i class="fas fa-times" onclick="removeFilter('filter_status')"></i>
                    </span>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Search -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-search"></i> Search Inventory</h2>
    </div>
    <form method="GET" action="" class="search-box">
        <input type="text" name="search" placeholder="Search by article name, property no., or description..." 
               value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">
            <i class="fas fa-search"></i> Search
        </button>
        <?php if ($search): ?>
        <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Clear
        </a>
        <?php endif; ?>
    </form>
</div>

<!-- Inventory Table -->
<div class="table-container">
    <div class="table-header">
        <h2><i class="fas fa-boxes"></i> Inventory Items List</h2>
        <p>Showing <?php echo number_format(count($inventory_items)); ?> of <?php echo number_format($total_rows); ?> items</p>
    </div>
    
    <div class="inventory-wrapper">
        <table class="inventory-table">
            <thead>
                <tr>
                    <th>ARTICLE NAME</th>
                    <th>PROPERTY NO.</th>
                    <th>BARCODE</th>
                    <th>TYPE</th>
                    <th>QTY</th>
                    <th>UNIT VALUE</th>
                    <th>LOCATION</th>
                    <th>CONDITION</th>
                    <th>STATUS</th>
                    <th>ACTIONS</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($inventory_items) > 0): ?>
                    <?php foreach ($inventory_items as $item): ?>
                    <?php
                    $row_class = '';
                    $quantity_badge_class = 'quantity-normal';
                    $qty = $item['qty_physical_count'];
                    
                    if ($qty <= $critical_threshold) {
                        $row_class = 'stock-critical-row';
                        $quantity_badge_class = 'quantity-critical';
                    } elseif ($qty <= $low_stock_threshold) {
                        $row_class = 'stock-warning-row';
                        $quantity_badge_class = 'quantity-warning';
                    }
                    
                    // Fix condition display - handle "Serviceable" properly
                    $condition_text = $item['condition_text'] ?? 'Good';
                    $condition_lower = strtolower($condition_text);
                    $condition_class = 'condition-good';
                    if ($condition_lower == 'new') $condition_class = 'condition-new';
                    elseif ($condition_lower == 'good') $condition_class = 'condition-good';
                    elseif ($condition_lower == 'fair') $condition_class = 'condition-fair';
                    elseif ($condition_lower == 'poor') $condition_class = 'condition-poor';
                    elseif ($condition_lower == 'serviceable' || $condition_lower == 'servicable') $condition_class = 'condition-serviceable';
                    ?>
                    <tr class="<?php echo $row_class; ?>">
                        <td>
                            <div class="article-name"><?php echo htmlspecialchars($item['article_name']); ?></div>
                            <?php if (!empty($item['description'])): ?>
                            <div class="article-description"><?php echo htmlspecialchars(substr($item['description'], 0, 50)); ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="property-no"><?php echo htmlspecialchars($item['property_no']); ?></td>
                        <td>
                            <?php if (!empty($item['barcode_data'])): ?>
                                <img src="generate_barcode.php?code=<?php echo urlencode($item['barcode_data']); ?>&width=100&height=30" 
                                     alt="Barcode" class="barcode-img"
                                     onclick="showBarcodeModal('<?php echo $item['barcode_data']; ?>', '<?php echo htmlspecialchars(addslashes($item['article_name'])); ?>')">
                                <span class="barcode-text"><?php echo htmlspecialchars(substr($item['barcode_data'], 0, 15)); ?>...</span>
                            <?php else: ?>
                                <span class="text-muted">No barcode</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="type-main"><?php echo htmlspecialchars($item['type_equipment_name'] ?? $item['category'] ?? 'N/A'); ?></div>
                            <?php if (!empty($item['equipment_name'])): ?>
                            <div class="type-sub"><?php echo htmlspecialchars($item['equipment_name']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="quantity-badge <?php echo $quantity_badge_class; ?>">
                                <?php echo number_format($item['qty_physical_count']); ?> <?php echo htmlspecialchars($item['uom']); ?>
                            </span>
                        </td>
                        <td><span class="unit-value">₱<?php echo number_format($item['unit_value'], 2); ?></span></td>
                        <td><span class="location-text"><?php echo htmlspecialchars($item['section_name'] ?? 'N/A'); ?></span></td>
                        <td>
                            <span class="condition-badge <?php echo $condition_class; ?>">
                                <?php echo htmlspecialchars($condition_text); ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($item['is_issued'] > 0): ?>
                                <span class="status-badge status-issued">Issued</span>
                            <?php else: ?>
                                <span class="status-badge status-available">Available</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-buttons">
                                <button class="action-btn view" onclick="viewItem(<?php echo $item['id']; ?>)" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <a href="<?php echo SITE_URL; ?>/admin/all_inventory.php?edit=<?php echo $item['id']; ?>" class="action-btn edit" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($item['is_multiple'] && !empty($item['barcode_data'])): ?>
                                <button class="action-btn barcode" onclick="viewAllBarcodes('<?php echo $item['property_no']; ?>', '<?php echo htmlspecialchars(addslashes($item['article_name'])); ?>')" title="View All Barcodes">
                                    <i class="fas fa-layer-group"></i>
                                </button>
                                <?php endif; ?>
                                <a href="<?php echo SITE_URL; ?>/admin/issue_items.php?item=<?php echo $item['id']; ?>" class="action-btn issue" title="Issue">
                                    <i class="fas fa-hand-holding"></i>
                                </a>
                                <?php if ($item['is_issued'] == 0): ?>
                                <a href="?delete=<?php echo $item['id']; ?>&csrf_token=<?php echo $_SESSION['csrf_token']; ?>" 
                                   class="action-btn delete" 
                                   onclick="return confirm('Are you sure you want to delete this item?')"
                                   title="Delete">
                                    <i class="fas fa-trash"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" class="text-center" style="padding: 60px 20px;">
                            <i class="fas fa-boxes" style="font-size: 48px; color: var(--text-muted); margin-bottom: 16px; display: block;"></i>
                            <p style="color: var(--text-muted); margin-bottom: 20px;">No inventory items found</p>
                            <a href="<?php echo SITE_URL; ?>/admin/add_inventory.php" class="btn btn-primary">Add Your First Item</a>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <!-- Pagination -->
    <?php if (ceil($total_rows / $per_page) > 1): ?>
    <div class="pagination">
        <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page-1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $low_stock_filter ? '&low_stock=1' : ''; ?><?php echo $filter_category ? '&filter_category='.urlencode($filter_category) : ''; ?><?php echo $filter_condition ? '&filter_condition='.urlencode($filter_condition) : ''; ?><?php echo $filter_location ? '&filter_location='.$filter_location : ''; ?><?php echo $filter_status ? '&filter_status='.$filter_status : ''; ?><?php echo "&sort_by=$sort_by&sort_order=$sort_order"; ?>">« Prev</a>
        <?php endif; ?>
        
        <?php
        $total_pages = ceil($total_rows / $per_page);
        $start = max(1, $page - 2);
        $end = min($total_pages, $page + 2);
        for ($i = $start; $i <= $end; $i++):
        ?>
            <a href="?page=<?php echo $i; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $low_stock_filter ? '&low_stock=1' : ''; ?><?php echo $filter_category ? '&filter_category='.urlencode($filter_category) : ''; ?><?php echo $filter_condition ? '&filter_condition='.urlencode($filter_condition) : ''; ?><?php echo $filter_location ? '&filter_location='.$filter_location : ''; ?><?php echo $filter_status ? '&filter_status='.$filter_status : ''; ?><?php echo "&sort_by=$sort_by&sort_order=$sort_order"; ?>" class="<?php echo $i == $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor; ?>
        
        <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page+1; ?><?php echo $search ? '&search='.urlencode($search) : ''; ?><?php echo $low_stock_filter ? '&low_stock=1' : ''; ?><?php echo $filter_category ? '&filter_category='.urlencode($filter_category) : ''; ?><?php echo $filter_condition ? '&filter_condition='.urlencode($filter_condition) : ''; ?><?php echo $filter_location ? '&filter_location='.$filter_location : ''; ?><?php echo $filter_status ? '&filter_status='.$filter_status : ''; ?><?php echo "&sort_by=$sort_by&sort_order=$sort_order"; ?>">Next »</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<!-- Sticky Scan Barcode Button -->
<div class="sticky-scan-button-container">
    <a href="<?php echo SITE_URL; ?>/admin/barcode_scanner.php" class="sticky-scan-button">
        <i class="fas fa-camera"></i> SCAN BARCODE
    </a>
</div>

<!-- Rest of modals and scripts remain the same -->
<!-- View Item Modal -->
<div id="viewModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2><i class="fas fa-info-circle"></i> Item Details</h2>
            <span class="modal-close" onclick="closeModal('viewModal')">&times;</span>
        </div>
        <div class="modal-body" id="viewModalContent">
            <div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewModal')">Close</button>
        </div>
    </div>
</div>

<!-- View All Barcodes Modal -->
<div id="viewAllBarcodesModal" class="modal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h2 id="allBarcodesModalTitle">All Barcodes</h2>
            <span class="modal-close" onclick="closeModal('viewAllBarcodesModal')">&times;</span>
        </div>
        <div class="modal-body" id="allBarcodesContent">
            <div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-primary" onclick="printAllBarcodes()">Print All</button>
            <button type="button" class="btn btn-secondary" onclick="closeModal('viewAllBarcodesModal')">Close</button>
        </div>
    </div>
</div>

<!-- Barcode Modal -->
<div id="barcodeModal" class="modal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-header">
            <h2 id="barcodeModalTitle">Barcode</h2>
            <span class="modal-close" onclick="closeModal('barcodeModal')">&times;</span>
        </div>
        <div class="modal-body" style="text-align: center;">
            <div id="barcodeModalImage"></div>
            <div id="barcodeModalNumber" style="font-family: monospace; margin-top: 15px;"></div>
            <div style="margin-top: 20px;">
                <button class="btn btn-primary" onclick="printCurrentBarcode()">Print Barcode</button>
            </div>
        </div>
    </div>
</div>

<script>
function removeFilter(filterName) {
    let url = new URL(window.location.href);
    url.searchParams.delete(filterName);
    window.location.href = url.toString();
}

function viewItem(id) {
    let modal = document.getElementById('viewModal');
    let content = document.getElementById('viewModalContent');
    modal.style.display = 'block';
    content.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    fetch('?ajax=get_item&id=' + id)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                let item = data.data;
                let statusBadge = item.is_issued > 0 ? '<span class="status-badge status-issued">Issued</span>' : '<span class="status-badge status-available">Available</span>';
                
                let conditionText = item.condition_text || 'Good';
                let conditionLower = conditionText.toLowerCase();
                let conditionClass = 'condition-good';
                if (conditionLower == 'new') conditionClass = 'condition-new';
                else if (conditionLower == 'good') conditionClass = 'condition-good';
                else if (conditionLower == 'fair') conditionClass = 'condition-fair';
                else if (conditionLower == 'poor') conditionClass = 'condition-poor';
                else if (conditionLower == 'serviceable' || conditionLower == 'servicable') conditionClass = 'condition-serviceable';
                
                let quantityClass = 'quantity-normal';
                let qty = item.qty_physical_count;
                let threshold = <?php echo $low_stock_threshold; ?>;
                let critical = <?php echo $critical_threshold; ?>;
                if (qty <= critical) quantityClass = 'quantity-critical';
                else if (qty <= threshold) quantityClass = 'quantity-warning';
                
                let html = `
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-info-circle"></i> Basic Information</div><div class="detail-content"><div class="detail-grid">
                        <div class="detail-item"><div class="detail-label">Article Name</div><div class="detail-value"><strong>${escapeHtml(item.article_name)}</strong></div></div>
                        <div class="detail-item"><div class="detail-label">Property Number</div><div class="detail-value">${escapeHtml(item.property_no || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Description</div><div class="detail-value">${escapeHtml(item.description || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Status</div><div class="detail-value">${statusBadge}</div></div>
                    </div></div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-tags"></i> Classification</div><div class="detail-content"><div class="detail-grid">
                        <div class="detail-item"><div class="detail-label">Category</div><div class="detail-value">${escapeHtml(item.category || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Type of Equipment</div><div class="detail-value">${escapeHtml(item.type_equipment_name || item.type_equipment || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Equipment Type</div><div class="detail-value">${escapeHtml(item.equipment_name || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Location</div><div class="detail-value">${escapeHtml(item.section_name || 'N/A')}</div></div>
                        <div class="detail-item"><div class="detail-label">Condition</div><div class="detail-value"><span class="condition-badge ${conditionClass}">${escapeHtml(conditionText)}</span></div></div>
                    </div></div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-calculator"></i> Quantity and Value</div><div class="detail-content"><div class="detail-grid">
                        <div class="detail-item"><div class="detail-label">Quantity</div><div class="detail-value"><span class="quantity-badge ${quantityClass}">${item.qty_physical_count} ${escapeHtml(item.uom)}</span></div></div>
                        <div class="detail-item"><div class="detail-label">Unit Value</div><div class="detail-value">₱${parseFloat(item.unit_value).toFixed(2)}</div></div>
                        <div class="detail-item"><div class="detail-label">Total Value</div><div class="detail-value">₱${(item.qty_physical_count * item.unit_value).toFixed(2)}</div></div>
                        <div class="detail-item"><div class="detail-label">Fund Cluster</div><div class="detail-value">${escapeHtml(item.fund_cluster || 'N/A')}</div></div>
                    </div></div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-calendar"></i> Dates</div><div class="detail-content"><div class="detail-grid">
                        <div class="detail-item"><div class="detail-label">Date Added</div><div class="detail-value">${new Date(item.date_added).toLocaleString()}</div></div>
                        <div class="detail-item"><div class="detail-label">Last Updated</div><div class="detail-value">${item.date_updated ? new Date(item.date_updated).toLocaleString() : 'Never'}</div></div>
                    </div></div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-barcode"></i> Barcode</div><div class="detail-content">
                        ${item.barcode_data ? `
                        <div class="detail-item"><div class="detail-label">Barcode Value</div><div class="detail-value">${escapeHtml(item.barcode_data)}</div></div>
                        <div style="text-align: center; margin-top: 15px;">
                            <img src="generate_barcode.php?code=${encodeURIComponent(item.barcode_data)}&width=300&height=70" style="max-width: 100%; border: 1px solid #E2E8F0; padding: 10px; border-radius: 8px;">
                            <br><button class="btn btn-primary btn-sm mt-2" onclick="printBarcode('${item.barcode_data}', '${escapeHtml(item.article_name)}')"><i class="fas fa-print"></i> Print Barcode</button>
                        </div>
                        ` : '<div class="detail-value">No barcode assigned</div>'}
                    </div></div>
                    <div class="detail-section"><div class="detail-header"><i class="fas fa-comment"></i> Remarks</div><div class="detail-content"><div class="detail-value">${escapeHtml(item.remarks || 'No remarks')}</div></div></div>
                `;
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-danger">Error loading item: ' + (data.message || 'Unknown error') + '</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading item details</div>';
        });
}

function viewAllBarcodes(propertyNo, itemName) {
    let modal = document.getElementById('viewAllBarcodesModal');
    let content = document.getElementById('allBarcodesContent');
    document.getElementById('allBarcodesModalTitle').innerHTML = 'All Barcodes - ' + escapeHtml(itemName);
    modal.style.display = 'block';
    content.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading...</div>';
    
    fetch('?get_multiple_items=1&property_no=' + encodeURIComponent(propertyNo))
        .then(response => response.json())
        .then(data => {
            if (data.success && data.items.length > 0) {
                let html = `<p><strong>Found ${data.count} items in this set:</strong></p><div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px;">`;
                data.items.forEach((item, index) => {
                    html += `<div style="border: 1px solid #E2E8F0; border-radius: 12px; padding: 16px; text-align: center;">
                        <div style="font-weight: 600; color: #6B8CFF; margin-bottom: 10px;">Item ${index + 1}: ${escapeHtml(item.property_no)}</div>
                        <div style="margin: 10px 0;"><img src="generate_barcode.php?code=${encodeURIComponent(item.barcode_data)}&width=200&height=50" style="max-width: 100%;"></div>
                        <div style="font-family: monospace; font-size: 11px; margin-bottom: 10px;">${escapeHtml(item.barcode_data)}</div>
                        <div style="display: flex; gap: 8px; justify-content: center;">
                            <button class="btn btn-secondary btn-sm" onclick="showBarcodeModal('${item.barcode_data}', '${escapeHtml(item.article_name)} - ${escapeHtml(item.property_no)}')">View</button>
                            <button class="btn btn-primary btn-sm" onclick="printBarcode('${item.barcode_data}', '${escapeHtml(item.article_name)} - ${escapeHtml(item.property_no)}')">Print</button>
                        </div>
                    </div>`;
                });
                html += '</div>';
                content.innerHTML = html;
            } else {
                content.innerHTML = '<div class="alert alert-warning">No related items found.</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Error loading barcodes</div>';
        });
}

function printAllBarcodes() {
    let content = document.getElementById('allBarcodesContent').innerHTML;
    let title = document.getElementById('allBarcodesModalTitle').innerHTML;
    let printWindow = window.open('', '_blank');
    printWindow.document.write(`<html><head><title>${escapeHtml(title)}</title><style>body{font-family:Arial,sans-serif;padding:20px;} .barcodes-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:20px;} .barcode-card{border:2px solid #6B8CFF;padding:15px;text-align:center;border-radius:8px;} button{display:none;} @media print{button{display:none;}}</style></head><body><div class="barcodes-grid">${content.replace(/<button.*?<\/button>/g, '')}</div><script>window.onload=function(){window.print();setTimeout(function(){window.close();},500);}<\/script></body></html>`);
    printWindow.document.close();
}

function showBarcodeModal(barcodeData, itemName) {
    let modal = document.getElementById('barcodeModal');
    document.getElementById('barcodeModalTitle').innerHTML = 'Barcode - ' + escapeHtml(itemName);
    document.getElementById('barcodeModalImage').innerHTML = '<img src="generate_barcode.php?code=' + encodeURIComponent(barcodeData) + '&width=350&height=80" alt="Barcode" style="max-width: 100%; border: 2px solid #E2E8F0; padding: 15px; border-radius: 12px;">';
    document.getElementById('barcodeModalNumber').innerHTML = '<strong>' + escapeHtml(barcodeData) + '</strong>';
    modal.style.display = 'block';
}

function printBarcode(barcodeData, itemName) {
    let printWindow = window.open('', '_blank');
    printWindow.document.write(`<html><head><title>Print Barcode - ${escapeHtml(itemName)}</title><style>body{text-align:center;font-family:Arial,sans-serif;margin:50px;} .barcode-container{border:2px solid #6B8CFF;border-radius:12px;padding:30px;max-width:400px;margin:0 auto;}</style></head><body><div class="barcode-container"><img src="generate_barcode.php?code=${encodeURIComponent(barcodeData)}&width=400&height=100" style="max-width:100%;"><div class="item-name" style="font-size:14px;font-weight:bold;color:#6B8CFF;margin-top:15px;">${escapeHtml(itemName)}</div><div class="barcode-number" style="font-family:monospace;font-size:12px;margin-top:10px;">${escapeHtml(barcodeData)}</div></div><script>window.onload=function(){window.print();setTimeout(function(){window.close();},500);}<\/script></body></html>`);
    printWindow.document.close();
}

function printCurrentBarcode() {
    let barcodeData = document.getElementById('barcodeModalNumber').innerHTML.replace(/<[^>]*>/g, '');
    let itemName = document.getElementById('barcodeModalTitle').innerHTML.replace('Barcode - ', '');
    printBarcode(barcodeData, itemName);
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

function escapeHtml(text) {
    if (!text) return '';
    let div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeModal('viewModal');
        closeModal('viewAllBarcodesModal');
        closeModal('barcodeModal');
    }
});

window.onclick = function(event) {
    if (event.target.classList.contains('modal')) {
        event.target.style.display = 'none';
    }
}
</script>

<?php
include INCLUDE_PATH . '/footer.php';
ob_end_flush();
?>