<?php
session_start();
header('Content-Type: application/json');

$host = 'localhost';
$dbname = 'inventory_sys';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
    exit();
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Handle Add
if ($action === 'add') {
    $account_code = $_POST['account_code'] ?? '';
    $account_name = $_POST['account_name'] ?? '';
    $account_type = $_POST['account_type'] ?? '';
    $equipment_id = !empty($_POST['equipment_id']) ? $_POST['equipment_id'] : null;
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $level = $_POST['level'] ?? 1;
    $description = $_POST['description'] ?? null;
    $is_active = $_POST['is_active'] ?? 1;
    
    if (empty($account_code) || empty($account_name) || empty($account_type)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit();
    }
    
    try {
        $query = "INSERT INTO general_ledger_accounts 
                  (account_code, account_name, account_type, equipment_id, parent_id, level, description, is_active, date_created, date_updated) 
                  VALUES 
                  (:account_code, :account_name, :account_type, :equipment_id, :parent_id, :level, :description, :is_active, NOW(), NOW())";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':account_code' => $account_code,
            ':account_name' => $account_name,
            ':account_type' => $account_type,
            ':equipment_id' => $equipment_id,
            ':parent_id' => $parent_id,
            ':level' => $level,
            ':description' => $description,
            ':is_active' => $is_active
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Account added successfully!', 'id' => $pdo->lastInsertId()]);
        exit();
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit();
    }
}

// Handle Edit
elseif ($action === 'edit') {
    $id = $_POST['id'] ?? '';
    $account_code = $_POST['account_code'] ?? '';
    $account_name = $_POST['account_name'] ?? '';
    $account_type = $_POST['account_type'] ?? '';
    $equipment_id = !empty($_POST['equipment_id']) ? $_POST['equipment_id'] : null;
    $parent_id = !empty($_POST['parent_id']) ? $_POST['parent_id'] : null;
    $level = $_POST['level'] ?? 1;
    $description = $_POST['description'] ?? null;
    $is_active = $_POST['is_active'] ?? 1;
    
    if (empty($id) || empty($account_code) || empty($account_name) || empty($account_type)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all required fields']);
        exit();
    }
    
    try {
        $query = "UPDATE general_ledger_accounts 
                  SET account_code = :account_code,
                      account_name = :account_name,
                      account_type = :account_type,
                      equipment_id = :equipment_id,
                      parent_id = :parent_id,
                      level = :level,
                      description = :description,
                      is_active = :is_active,
                      date_updated = NOW()
                  WHERE id = :id";
        
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            ':id' => $id,
            ':account_code' => $account_code,
            ':account_name' => $account_name,
            ':account_type' => $account_type,
            ':equipment_id' => $equipment_id,
            ':parent_id' => $parent_id,
            ':level' => $level,
            ':description' => $description,
            ':is_active' => $is_active
        ]);
        
        echo json_encode(['success' => true, 'message' => 'Account updated successfully!']);
              header('Location: equptment_con.php');
        exit();
        
    } catch(PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        exit();
    }
}

// Handle Delete
elseif ($action === 'delete') {
    $id = $_GET['id'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Invalid account ID']);
        exit();
    }
    
    try {
        $query = "DELETE FROM general_ledger_accounts WHERE id = :id";
        $stmt = $pdo->prepare($query);
        $stmt->execute([':id' => $id]);
        
        echo json_encode(['success' => true, 'message' => 'Account deleted successfully!']);
        exit();
        
    } catch(PDOException $e) {
        if ($e->getCode() == '23000') {
            echo json_encode(['success' => false, 'message' => 'Cannot delete this account because it is being used by other records']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
        }
        exit();
    }
}

// If no valid action
echo json_encode(['success' => false, 'message' => 'Invalid action']);
exit();
?>