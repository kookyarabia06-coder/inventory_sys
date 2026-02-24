<?php
/**
 * Database Configuration
 * Handles database connection and table creation
 */

// Database credentials
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'inventory_sys');

/**
 * Establish database connection
 * @return mysqli Database connection object
 */
function getConnection() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS);
    
    // Check connection
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }
    
    // Create database if not exists
    $sql = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
    if ($conn->query($sql) === TRUE) {
        $conn->select_db(DB_NAME);
    } else {
        die("Error creating database: " . $conn->error);
    }
    
    return $conn;
}

$conn = getConnection();

/**
 * Create all necessary tables
 * @param mysqli $conn Database connection
 */
function createTables($conn) {
    // Users table
    $conn->query("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        firstname VARCHAR(100),
        lastname VARCHAR(100),
        username VARCHAR(80) UNIQUE,
        password VARCHAR(255),
        email VARCHAR(100),
        role ENUM('super_admin', 'admin', 'user') DEFAULT 'user',
        status ENUM('active', 'inactive') DEFAULT 'active',
        avatar VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Buildings table
    $conn->query("CREATE TABLE IF NOT EXISTS buildings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(255) NOT NULL,
        floor INT DEFAULT 1
    )");
    
    // Departments table
    $conn->query("CREATE TABLE IF NOT EXISTS departments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        building_id INT,
        name VARCHAR(255) NOT NULL,
        FOREIGN KEY (building_id) REFERENCES buildings(id) ON DELETE SET NULL
    )");
    
    // Sections table
    $conn->query("CREATE TABLE IF NOT EXISTS sections (
        id INT AUTO_INCREMENT PRIMARY KEY,
        department_id INT,
        name VARCHAR(255) NOT NULL,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL
    )");
    
    // Equipment types table
    $conn->query("CREATE TABLE IF NOT EXISTS equipment (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        category VARCHAR(50) NOT NULL,
        description VARCHAR(255)
    )");
    
    // Employees table
    $conn->query("CREATE TABLE IF NOT EXISTS employees (
        id INT AUTO_INCREMENT PRIMARY KEY,
        firstname VARCHAR(100) NOT NULL,
        lastname VARCHAR(100) NOT NULL,
        middlename VARCHAR(100),
        email VARCHAR(150) UNIQUE,
        contact VARCHAR(50),
        department_id INT,
        section_id INT,
        position VARCHAR(100),
        date_hired DATE,
        status ENUM('Active','Inactive') DEFAULT 'Active',
        date_created TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        date_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (department_id) REFERENCES departments(id) ON DELETE SET NULL,
        FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL
    )");
    
    // Inventory table
    $conn->query("CREATE TABLE IF NOT EXISTS inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        article_name VARCHAR(255) NOT NULL,
        description TEXT,
        property_no VARCHAR(120) UNIQUE,
        uom VARCHAR(50),
        qty_property_card DECIMAL(12,2) DEFAULT 0.00,
        qty_physical_count DECIMAL(12,2) DEFAULT 0.00,
        location_id INT,
        condition_text VARCHAR(100),
        remarks TEXT,
        certified_correct TEXT,
        approved_by INT,
        verified_by INT,
        section_id INT,
        date_added TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        date_updated TIMESTAMP NULL,
        fund_cluster VARCHAR(50),
        unit_value DECIMAL(12,2) DEFAULT 0.00,
        equipment_id INT DEFAULT 1,
        type_equipment VARCHAR(50) DEFAULT '',
        category VARCHAR(50) DEFAULT '',
        allocate_to INT,
        barcode_data VARCHAR(255),
        created_by INT,
        current_holder INT,
        FOREIGN KEY (section_id) REFERENCES sections(id) ON DELETE SET NULL,
        FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (equipment_id) REFERENCES equipment(id) ON DELETE SET NULL,
        FOREIGN KEY (location_id) REFERENCES departments(id) ON DELETE SET NULL,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
        FOREIGN KEY (current_holder) REFERENCES users(id) ON DELETE SET NULL,
        INDEX idx_barcode (barcode_data)
    )");
    
    // Equipment issuance table
    $conn->query("CREATE TABLE IF NOT EXISTS equipment_issuance (
        id INT AUTO_INCREMENT PRIMARY KEY,
        inventory_id INT NOT NULL,
        issued_to INT NOT NULL,
        issued_by INT NOT NULL,
        quantity_issued DECIMAL(12,2) NOT NULL,
        purpose VARCHAR(255),
        location_used VARCHAR(255),
        expected_return DATE,
        actual_return DATE,
        status ENUM('issued', 'returned', 'partial') DEFAULT 'issued',
        condition_on_issue VARCHAR(100),
        condition_on_return VARCHAR(100),
        remarks TEXT,
        issued_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        returned_date TIMESTAMP NULL,
        FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE,
        FOREIGN KEY (issued_to) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (issued_by) REFERENCES users(id) ON DELETE CASCADE
    )");
    
    // User inventory table
    $conn->query("CREATE TABLE IF NOT EXISTS user_inventory (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        inventory_id INT NOT NULL,
        issuance_id INT,
        quantity_assigned DECIMAL(12,2) NOT NULL,
        assigned_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        status ENUM('active', 'returned', 'lost', 'damaged') DEFAULT 'active',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (inventory_id) REFERENCES inventory(id) ON DELETE CASCADE,
        FOREIGN KEY (issuance_id) REFERENCES equipment_issuance(id) ON DELETE SET NULL,
        UNIQUE KEY unique_user_item (user_id, inventory_id, status)
    )");
    
    // Activity log table
    $conn->query("CREATE TABLE IF NOT EXISTS activity_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(50) NOT NULL,
        item_id INT,
        details TEXT,
        date_created DATETIME NOT NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    
    // Audit trail table
    $conn->query("CREATE TABLE IF NOT EXISTS audit_trail (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(50) NOT NULL,
        table_name VARCHAR(50),
        record_id INT,
        old_value TEXT,
        new_value TEXT,
        ip_address VARCHAR(45),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )");
    
    // System settings table
    $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE,
        setting_value TEXT,
        description VARCHAR(255),
        updated_by INT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
    )");
}
// Modify the users table to include the new role
$conn->query("ALTER TABLE users MODIFY COLUMN role ENUM('super_admin', 'admin', 'supply', 'user') DEFAULT 'user'");
// Create tables
createTables($conn);

// Insert default data if needed
include_once 'default_data.php';
?>