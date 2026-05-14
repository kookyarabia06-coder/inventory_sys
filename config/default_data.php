<?php
/**
 * Default Data Seeder
 */

// Insert default equipment if not exists
$check = $conn->query("SELECT * FROM equipment LIMIT 1");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO equipment (name, category, description) VALUES 
                  ('Dummy Equipment', 'GENERAL', 'Placeholder'),
                  ('Laptop', 'ICT', 'Office Laptop'),
                  ('Desktop PC', 'ICT', 'Computer'),
                  ('Medical Bed', 'MEDICAL', 'Hospital Bed'),
                  ('PPE Set', 'SAFETY', 'Personal Protective Equipment'),
                  ('Semi-Expendable Item', 'SUPPLIES', 'Semi-expendable supplies')");
}

// Insert default users if not exists
$users = [
    ['superadmin', 'Super', 'Admin', 'superadmin@test.com', 'super_admin', 'admin123'],
    ['admin', 'System', 'Admin', 'admin@test.com', 'admin', 'admin123'],
    ['user', 'Regular', 'User', 'user@test.com', 'user', 'user123']
];

foreach ($users as $u) {
    $check = $conn->query("SELECT * FROM users WHERE username = '$u[0]'");
    if ($check->num_rows == 0) {
        $hash = password_hash($u[5], PASSWORD_DEFAULT);
        $conn->query("INSERT INTO users (username, firstname, lastname, email, role, password, status) 
                      VALUES ('$u[0]', '$u[1]', '$u[2]', '$u[3]', '$u[4]', '$hash', 'active')");
    }
}

// Insert sample buildings if not exists
$check = $conn->query("SELECT * FROM buildings LIMIT 1");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO buildings (name, floor) VALUES 
                  ('Main Building', 3),
                  ('Ward Building', 2),
                  ('Annex Building', 1),
                  ('Warehouse', 1)");
}

// Insert sample departments if not exists
$check = $conn->query("SELECT * FROM departments LIMIT 1");
if ($check->num_rows == 0) {
    $conn->query("INSERT INTO departments (building_id, name) VALUES 
                  (1, 'Emergency Department'),
                  (1, 'Pharmacy'),
                  (2, 'ICU'),
                  (2, 'Surgery'),
                  (3, 'Administration'),
                  (4, 'Storage')");
}

// Insert sample sections if not exists
// $check = $conn->query("SELECT * FROM sections LIMIT 1");
// if ($check->num_rows == 0) {
//     $conn->query("INSERT INTO sections (department_id, name) VALUES 
//                   (1, 'Triage'),
//                   (1, 'Treatment Room'),
//                   (2, 'Dispensing'),
//                   (3, 'ICU Ward A'),
//                   (3, 'ICU Ward B'),
//                   (4, 'Operating Room 1'),
//                   (4, 'Operating Room 2')");
// }

// Insert default system settings if not exists
$settings = [
    ['system_name', 'Inventory Management System', 'System name'],
    ['company_name', 'Your Company Name', 'Company name'],
    ['system_email', 'admin@example.com', 'System email'],
    ['items_per_page', '10', 'Items per page in listings'],
    ['enable_audit_trail', '1', 'Enable audit trail logging'],
    ['session_timeout', '3600', 'Session timeout in seconds']
];

foreach ($settings as $s) {
    $check = $conn->query("SELECT * FROM system_settings WHERE setting_key = '$s[0]'");
    if ($check->num_rows == 0) {
        $conn->query("INSERT INTO system_settings (setting_key, setting_value, description) 
                      VALUES ('$s[0]', '$s[1]', '$s[2]')");
    }
}

// Insert default supply user if not exists
$check = $conn->query("SELECT * FROM users WHERE username = 'supply'");
if ($check->num_rows == 0) {
    $hash = password_hash('supply123', PASSWORD_DEFAULT);
    $conn->query("INSERT INTO users (username, firstname, lastname, email, role, password, status) 
                  VALUES ('supply', 'Supply', 'Officer', 'supply@test.com', 'supply', '$hash', 'active')");
}
?>