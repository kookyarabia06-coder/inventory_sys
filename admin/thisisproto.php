<?php
// Database configuration
$host = 'localhost';
$dbname = 'inventory_sys';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// FIRST: Fix collation for existing tables and recreate triggers
try {
    // Set collation for both tables to match
    $pdo->exec("ALTER TABLE type_of_equipment CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("ALTER TABLE equipment_sub_type CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    
    // Drop existing triggers if they exist
    $pdo->exec("DROP TRIGGER IF EXISTS trg_type_of_equipment_before_insert");
    $pdo->exec("DROP TRIGGER IF EXISTS trg_equipment_sub_type_before_insert");
    
    // Recreate trigger for Type of Equipment
    $pdo->exec("
    CREATE TRIGGER trg_type_of_equipment_before_insert 
    BEFORE INSERT ON type_of_equipment 
    FOR EACH ROW
    BEGIN
        DECLARE next_num INT;
        
        IF NEW.code IS NULL OR NEW.code = '' THEN
            SELECT COALESCE(MAX(CAST(code AS UNSIGNED)), 0) + 1 INTO next_num 
            FROM type_of_equipment;
            SET NEW.code = LPAD(next_num, 2, '0');
        END IF;
    END
    ");
    
    // Recreate trigger for Equipment Sub-Type with COLLATE fix
    $pdo->exec("
    CREATE TRIGGER trg_equipment_sub_type_before_insert 
    BEFORE INSERT ON equipment_sub_type 
    FOR EACH ROW
    BEGIN
        DECLARE type_code VARCHAR(2);
        DECLARE next_sub_num INT;
        
        -- Get the parent type code
        SELECT code INTO type_code 
        FROM type_of_equipment 
        WHERE id = NEW.type_of_equipment_id;
        
        -- Get next number for this specific equipment type
        -- FIXED: Explicitly cast to avoid collation issues
        SELECT COALESCE(MAX(CAST(SUBSTRING(code, 3) AS UNSIGNED)), 0) + 1 INTO next_sub_num
        FROM equipment_sub_type 
        WHERE CAST(code AS CHAR) LIKE CONCAT(CAST(type_code AS CHAR), '%');
        
        -- Combine type code (2 digits) + subtype sequential number (2 digits)
        SET NEW.code = CONCAT(type_code, LPAD(next_sub_num, 2, '0'));
    END
    ");
    
} catch(PDOException $e) {
    // Triggers might already exist or tables might need adjustment
    // Continue execution
}

// Handle Type of Equipment Actions
if (isset($_POST['add_type'])) {
    $name = trim($_POST['name']);
    $stmt = $pdo->prepare("INSERT INTO type_of_equipment (name) VALUES (?)");
    $stmt->execute([$name]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['edit_type'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $stmt = $pdo->prepare("UPDATE type_of_equipment SET name = ? WHERE id = ?");
    $stmt->execute([$name, $id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_GET['delete_type'])) {
    $id = $_GET['delete_type'];
    // Check if has sub-types
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM equipment_sub_type WHERE type_of_equipment_id = ?");
    $stmt->execute([$id]);
    if ($stmt->fetchColumn() > 0) {
        $error = "Cannot delete: This equipment type has sub-types. Delete sub-types first.";
    } else {
        $stmt = $pdo->prepare("DELETE FROM type_of_equipment WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}

// Handle Equipment Sub-Type Actions
if (isset($_POST['add_subtype'])) {
    $name = trim($_POST['name']);
    $type_id = $_POST['type_id'];
    $stmt = $pdo->prepare("INSERT INTO equipment_sub_type (name, type_of_equipment_id) VALUES (?, ?)");
    $stmt->execute([$name, $type_id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_POST['edit_subtype'])) {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $type_id = $_POST['type_id'];
    $stmt = $pdo->prepare("UPDATE equipment_sub_type SET name = ?, type_of_equipment_id = ? WHERE id = ?");
    $stmt->execute([$name, $type_id, $id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if (isset($_GET['delete_subtype'])) {
    $id = $_GET['delete_subtype'];
    $stmt = $pdo->prepare("DELETE FROM equipment_sub_type WHERE id = ?");
    $stmt->execute([$id]);
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// Fetch all data
$types = $pdo->query("SELECT * FROM type_of_equipment ORDER BY code")->fetchAll();
$subtypes = $pdo->query("
    SELECT s.*, t.name as type_name, t.code as type_code 
    FROM equipment_sub_type s 
    JOIN type_of_equipment t ON s.type_of_equipment_id = t.id 
    ORDER BY t.code, s.code
")->fetchAll();

// Get data for edit forms
$edit_type = null;
if (isset($_GET['edit_type_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM type_of_equipment WHERE id = ?");
    $stmt->execute([$_GET['edit_type_id']]);
    $edit_type = $stmt->fetch();
}

$edit_subtype = null;
if (isset($_GET['edit_subtype_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM equipment_sub_type WHERE id = ?");
    $stmt->execute([$_GET['edit_subtype_id']]);
    $edit_subtype = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title></title>
    
    <!-- FAVICON - Try multiple paths -->
    <link rel="icon" type="image/png" href="../assets/icons/armmc.png">
    <link rel="icon" type="image/png" href="../assets/icons/armmc.png">
    <link rel="icon" type="image/png" href="../assets/icons/armmc.png">
    <link rel="apple-touch-icon" href="../assets/icons/armmc.png">
    <link rel="shortcut icon" href="../assets/icons/armmc.png">

</head>
<body>
    <div class="container"> 
        <header>
            <h1>Equipment Management System</h1>
            <p>Manage Type of Equipment and Equipment Sub-Types</p>
        </header>
        
        <div class="content">
            <!-- LEFT SECTION: TYPE OF EQUIPMENT -->
            <div class="section">
                <h2> Type of Equipment</h2>
                
                <?php if (isset($error)): ?>
                    <div class="alert"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Add/Edit Type Form -->
                <?php if ($edit_type): ?>
                    <form method="POST" class="form-group">
                        <h3>Edit Equipment Type</h3>
                        <input type="hidden" name="id" value="<?php echo $edit_type['id']; ?>">
                        <div class="form-group">
                            <label>Name:</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($edit_type['name']); ?>" required>
                        </div>
                        <button type="submit" name="edit_type">Update Type</button>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="cancel-btn" style="margin-left: 10px;">Cancel</a>
                    </form>
                <?php else: ?>
                    <form method="POST" class="form-group">
                        <h3>Add New Equipment Type</h3>
                        <div class="form-group">
                            <label>Name:</label>
                            <input type="text" name="name" placeholder="e.g., Heavy Machinery" required>
                        </div>
                        <button type="submit" name="add_type">Add Type</button>
                    </form>
                <?php endif; ?>
                
                <!-- Types List Table -->
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($types as $type): ?>
                        <tr>
                            <td><span class="code-badge"><?php echo htmlspecialchars($type['code']); ?></span></td>
                            <td><?php echo htmlspecialchars($type['name']); ?></td>
                            <td class="actions">
                                <a href="?edit_type_id=<?php echo $type['id']; ?>" class="edit-btn"> Edit</a>
                                <a href="?delete_type=<?php echo $type['id']; ?>" class="delete-btn" onclick="return confirm('Delete this equipment type? This will also delete all related sub-types.')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($types)): ?>
                        <tr>
                            <td colspan="3" style="text-align: center;">No equipment types found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- RIGHT SECTION: EQUIPMENT SUB-TYPE -->
            <div class="section">
                <h2>🔧 Equipment Sub-Type</h2>
                
                <!-- Add/Edit Sub-Type Form -->
                <?php if ($edit_subtype): ?>
                    <form method="POST" class="form-group">
                        <h3>Edit Equipment Sub-Type</h3>
                        <input type="hidden" name="id" value="<?php echo $edit_subtype['id']; ?>">
                        <div class="form-group">
                            <label>Equipment Type:</label>
                            <select name="type_id" required>
                                <option value="">Select Type</option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>" <?php echo $edit_subtype['type_of_equipment_id'] == $type['id'] ? 'selected' : ''; ?>>
                                        <?php echo $type['code'] . ' - ' . htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sub-Type Name:</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($edit_subtype['name']); ?>" required>
                        </div>
                        <button type="submit" name="edit_subtype">Update Sub-Type</button>
                        <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="cancel-btn" style="margin-left: 10px;">Cancel</a>
                    </form>
                <?php else: ?>
                    <form method="POST" class="form-group">
                        <h3>Add New Equipment Sub-Type</h3>
                        <div class="form-group">
                            <label>Equipment Type:</label>
                            <select name="type_id" required>
                                <option value="">Select Equipment Type</option>
                                <?php foreach ($types as $type): ?>
                                    <option value="<?php echo $type['id']; ?>">
                                        <?php echo $type['code'] . ' - ' . htmlspecialchars($type['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Sub-Type Name:</label>
                            <input type="text" name="name" placeholder="e.g., Excavator" required>
                        </div>
                        <button type="submit" name="add_subtype">Add Sub-Type</button>
                    </form>
                <?php endif; ?>
                
                <!-- Sub-Types List Table -->
                <table>
                    <thead>
                        <tr>
                            <th>Code</th>
                            <th>Parent Type</th>
                            <th>Name</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($subtypes as $subtype): ?>
                        <tr>
                            <td><span class="code-badge"><?php echo htmlspecialchars($subtype['code']); ?></span></td>
                            <td><?php echo htmlspecialchars($subtype['type_name']); ?></td>
                            <td><?php echo htmlspecialchars($subtype['name']); ?></td>
                            <td class="actions">
                                <a href="?edit_subtype_id=<?php echo $subtype['id']; ?>" class="edit-btn">Edit</a>
                                <a href="?delete_subtype=<?php echo $subtype['id']; ?>" class="delete-btn" onclick="return confirm('Delete this sub-type?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($subtypes)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center;">No equipment sub-types found</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>