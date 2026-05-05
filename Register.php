<?php
/**
 * Register Page - User Registration with Admin Approval
 */

// Load configuration
require_once __DIR__ . '/config.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDE_PATH . '/functions.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/smtp_mailer.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . getDashboardUrl());
    exit();
}

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $firstname = sanitize($_POST['firstname'] ?? '');
    $lastname = sanitize($_POST['lastname'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms = isset($_POST['terms']) ? true : false;
    
    // Validation
    if (empty($firstname)) {
        $errors[] = "First name is required";
    }
    
    if (empty($lastname)) {
        $errors[] = "Last name is required";
    }
    
    if (empty($username)) {
        $errors[] = "Username is required";
    } elseif (strlen($username) < 3) {
        $errors[] = "Username must be at least 3 characters long";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = "Username can only contain letters, numbers, and underscores";
    } else {
        // Check if username already exists
        $check = $conn->prepare("SELECT id FROM users WHERE username = ?");
        $check->bind_param("s", $username);
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Username already taken. Please choose another.";
        }
        $check->close();
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email already exists
        $check = $conn->prepare("SELECT id FROM users WHERE email = ?");
        $check->bind_param("s", $email);
        $check->execute();
        $result = $check->get_result();
        if ($result->num_rows > 0) {
            $errors[] = "Email already registered. Please use another or login.";
        }
        $check->close();
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long";
    } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter, one lowercase letter, and one number";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    if (!$terms) {
        $errors[] = "You must agree to the Terms and Conditions";
    }
    
    // If no errors, create user with pending status
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $role = 'user';
        $status = 'pending';
        $created_at = date('Y-m-d H:i:s');
        
        $stmt = $conn->prepare("
            INSERT INTO users (firstname, lastname, username, email, password, role, status, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->bind_param("ssssssss", $firstname, $lastname, $username, $email, $hashed_password, $role, $status, $created_at);
        
        if ($stmt->execute()) {
            $user_id = $stmt->insert_id;
            
            // Log activity
            logActivity('Register', $user_id, "New user registered: $username (pending approval)");
            
            // Log to audit trail
            if (function_exists('logUserRegistration')) {
                logUserRegistration($user_id, [
                    'username' => $username,
                    'email' => $email,
                    'firstname' => $firstname,
                    'lastname' => $lastname
                ]);
            }
            
            // Get admin emails
            $admin_query = $conn->prepare("SELECT email, firstname, lastname FROM users WHERE role IN ('admin', 'super_admin') AND status = 'active'");
            $admin_query->execute();
            $admins = $admin_query->get_result();
            
            // HTML Email Template for Admin Notification
            $admin_html_message = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>New Registration</title>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #3A3A3A; }
                    .container { max-width: 600px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1); }
                    .header { background: linear-gradient(135deg, #6B8CFF 0%, #8FB5FF 100%); color: white; padding: 30px; text-align: center; }
                    .content { padding: 30px; }
                    .user-details { background: #F0F0F0; padding: 20px; border-radius: 12px; margin: 20px 0; }
                    .button { display: inline-block; padding: 12px 24px; background-color: #6B8CFF; color: white; text-decoration: none; border-radius: 8px; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>New User Registration</h1>
                        <p>A new user has registered and needs approval</p>
                    </div>
                    <div class='content'>
                        <div class='user-details'>
                            <p><strong>Name:</strong> $firstname $lastname</p>
                            <p><strong>Username:</strong> $username</p>
                            <p><strong>Email:</strong> $email</p>
                            <p><strong>Registered:</strong> " . date('Y-m-d H:i:s') . "</p>
                        </div>
                        <p>Please login to the admin panel to approve or reject this registration.</p>
                        <div style='text-align: center;'>
                            <a href='" . SITE_URL . "/admin/users.php' class='button'>View Pending Registrations</a>
                        </div>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $admin_plain_message = "New User Registration Pending Approval\n\n";
            $admin_plain_message .= "Name: $firstname $lastname\n";
            $admin_plain_message .= "Username: $username\n";
            $admin_plain_message .= "Email: $email\n";
            $admin_plain_message .= "Registered: " . date('Y-m-d H:i:s') . "\n\n";
            $admin_plain_message .= "Please login to the admin panel to approve or reject this registration.\n";
            $admin_plain_message .= SITE_URL . "/admin/users.php\n";
            
            // Send to admins using SMTP
            while ($admin = $admins->fetch_assoc()) {
                sendSMTPEmail($admin['email'], "New User Registration Pending Approval - " . SITE_NAME, $admin_html_message, $admin_plain_message);
            }
            $admin_query->close();
            
            // HTML Email Template for User Confirmation
            $user_html_message = "
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset='UTF-8'>
                <title>Registration Received</title>
                <style>
                    body { font-family: 'Segoe UI', Arial, sans-serif; line-height: 1.6; color: #3A3A3A; }
                    .container { max-width: 600px; margin: 0 auto; background: #FFFFFF; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1); }
                    .header { background: linear-gradient(135deg, #6B8CFF 0%, #8FB5FF 100%); color: white; padding: 30px; text-align: center; }
                    .content { padding: 30px; }
                    .info-box { background: #FFF9E6; padding: 20px; border-left: 4px solid #FFB74D; border-radius: 8px; margin: 20px 0; }
                    .footer { background: #F0F0F0; padding: 20px; text-align: center; font-size: 12px; color: #9E9E9E; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Registration Received</h1>
                        <p>Thank you for registering</p>
                    </div>
                    <div class='content'>
                        <h3>Dear $firstname,</h3>
                        <p>Thank you for registering with " . SITE_NAME . ".</p>
                        
                        <div class='info-box'>
                            <p><strong>Your account has been created successfully and is pending admin approval.</strong></p>
                            <p>You will receive an email notification once your account has been approved.</p>
                        </div>
                        
                        <p><strong>Registration Details:</strong></p>
                        <ul>
                            <li>Username: $username</li>
                            <li>Email: $email</li>
                        </ul>
                        
                        <p>If you have any questions, please contact the system administrator.</p>
                        
                        <p>Best regards,<br>
                        <strong>" . SITE_NAME . " Team</strong></p>
                    </div>
                    <div class='footer'>
                        <p>This is an automated message. Please do not reply to this email.</p>
                    </div>
                </div>
            </body>
            </html>
            ";
            
            $user_plain_message = "Registration Received - Awaiting Approval\n\n";
            $user_plain_message .= "Dear $firstname,\n\n";
            $user_plain_message .= "Thank you for registering with " . SITE_NAME . ".\n\n";
            $user_plain_message .= "Your account has been created successfully and is pending admin approval.\n";
            $user_plain_message .= "You will receive an email notification once your account has been approved.\n\n";
            $user_plain_message .= "Registration Details:\n";
            $user_plain_message .= "Username: $username\n";
            $user_plain_message .= "Email: $email\n\n";
            $user_plain_message .= "If you have any questions, please contact the system administrator.\n\n";
            $user_plain_message .= "Regards,\n" . SITE_NAME . " Team\n";
            
            // Send confirmation to user using SMTP
            sendSMTPEmail($email, "Registration Received - Awaiting Approval - " . SITE_NAME, $user_html_message, $user_plain_message);
            
            $success = "Registration successful! Your account is pending approval from an administrator. You will be notified once your account is activated.";
            
            // Clear POST data
            $_POST = [];
        } else {
            $errors[] = "Registration failed: " . $conn->error;
        }
        $stmt->close();
    }
}

// Get site settings
$settings = [];
$result = $conn->query("SELECT setting_key, setting_value FROM system_settings");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo $settings['system_name'] ?? 'IMS'; ?></title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FAVICON -->
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/icons/armmc.png">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/icons/armmc.png">
    <link rel="shortcut icon" href="<?php echo SITE_URL; ?>/assets/icons/armmc.png">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #2C3E50 0%, #34495E 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .register-container {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 550px;
            max-width: 100%;
            padding: 35px;
            animation: slideIn 0.5s ease;
            border: 1px solid #E0E0E0;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        .register-container::-webkit-scrollbar {
            width: 8px;
        }
        
        .register-container::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        
        .register-container::-webkit-scrollbar-thumb {
            background: #6B8CFF;
            border-radius: 10px;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-15px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 25px;
        }
        
        .logo i {
            font-size: 50px;
            color: #6B8CFF;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
                color: #F8B0C0;
            }
            100% {
                transform: scale(1);
                color: #6B8CFF;
            }
        }
        
        .logo h1 {
            color: #3A3A3A;
            margin-top: 8px;
            font-size: 26px;
            font-weight: 600;
        }
        
        .logo p {
            color: #6B6B6B;
            font-size: 13px;
            margin-top: 5px;
        }
        
        .errors {
            background-color: #FFEBEE;
            color: #C62828;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 3px solid #f44336;
        }
        
        .errors ul {
            margin-left: 20px;
        }
        
        .success {
            background-color: #E8F5E9;
            color: #2E7D32;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            border-left: 3px solid #4CAF50;
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .success i {
            font-size: 24px;
            margin-right: 10px;
        }
        
        .success-content {
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .success a {
            color: #2E7D32;
            font-weight: 600;
            text-decoration: underline;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #3A3A3A;
            font-weight: 500;
            font-size: 13px;
        }
        
        .form-group label i {
            color: #6B8CFF;
            margin-right: 5px;
            font-size: 12px;
        }
        
        .form-group .required {
            color: #f44336;
            margin-left: 3px;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9E9E9E;
            transition: color 0.3s;
            font-size: 14px;
        }
        
        .input-group input, 
        .input-group select {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            color: #3A3A3A;
            background-color: #FAFAFA;
        }
        
        /* FIXED Password Field Styles - No Overlapping */
        .password-field {
            position: relative;
        }
        
        .password-field input {
            width: 100%;
            padding: 12px 45px 12px 40px;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            background-color: #FAFAFA;
            box-sizing: border-box;
        }
        
        .password-field input:focus {
            outline: none;
            border-color: #6B8CFF;
            background-color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.05);
        }
        
        .password-field .lock-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9E9E9E;
            pointer-events: none;
            font-size: 14px;
        }
        
        .password-field .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9E9E9E;
            cursor: pointer;
            transition: color 0.3s;
            font-size: 16px;
            z-index: 2;
        }
        
        .password-field .toggle-password:hover {
            color: #6B8CFF;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #6B8CFF;
            background-color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.05);
        }
        
        .input-group input:focus + i {
            color: #6B8CFF;
        }
        
        .input-group input::placeholder {
            color: #9E9E9E;
            opacity: 0.7;
            font-size: 13px;
        }
        
        .password-hint {
            font-size: 11px;
            color: #9E9E9E;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .password-hint i {
            font-size: 10px;
            width: 14px;
        }
        
        .password-hint.valid {
            color: #4CAF50;
        }
        
        .terms-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .terms-group input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #6B8CFF;
        }
        
        .terms-group label {
            color: #6B6B6B;
            font-size: 13px;
            cursor: pointer;
        }
        
        .terms-group a {
            color: #6B8CFF;
            text-decoration: none;
        }
        
        .terms-group a:hover {
            text-decoration: underline;
        }
        
        .btn-register {
            width: 100%;
            padding: 12px;
            background: #6B8CFF;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        
        .btn-register:hover {
            background: #8FB5FF;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(107, 140, 255, 0.2);
        }
        
        .btn-register:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #F0F0F0;
            color: #6B6B6B;
            font-size: 13px;
        }
        
        .login-link a {
            color: #6B8CFF;
            text-decoration: none;
            font-weight: 500;
            margin-left: 5px;
        }
        
        .login-link a:hover {
            text-decoration: underline;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .info-box {
            background-color: #FFF3E0;
            color: #E65100;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 3px solid #FF9800;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .info-box i {
            font-size: 18px;
        }
        
        @media (max-width: 480px) {
            .register-container {
                padding: 25px 20px;
            }
            
            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="logo">
            <img src="assets/icons/armmc.png" alt="Rodriguez Memorial Medical Center" style="width: 100px; height: auto; margin-bottom: 15px;">
            <h1>Create Account</h1>
            <p><?php echo htmlspecialchars($settings['system_name'] ?? 'IMS'); ?></p>
        </div>
        
        <div class="info-box">
            <i class="fas fa-clock"></i>
            <span><strong>Admin Approval Required:</strong> Your account will be pending until approved by an administrator. You will receive an email notification once approved.</span>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <strong><i class="fas fa-exclamation-triangle"></i> Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <div class="success-content">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong><?php echo htmlspecialchars($success); ?></strong>
                        <p style="margin-top: 8px; font-size: 13px;">
                            <i class="fas fa-envelope"></i> A confirmation email has been sent to your email address.
                        </p>
                        <p style="margin-top: 5px;">
                            <a href="<?php echo SITE_URL ?? ''; ?>/login.php">
                                <i class="fas fa-sign-in-alt"></i> Click here to login once approved
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!$success): ?>
        <form method="POST" action="" id="registerForm">
            <div class="form-row">
                <div class="form-group">
                    <label for="firstname">
                        <i class="fas fa-user"></i> First Name <span class="required">*</span>
                    </label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" id="firstname" name="firstname" 
                               placeholder="Enter first name"
                               value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>" 
                               required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="lastname">
                        <i class="fas fa-user"></i> Last Name <span class="required">*</span>
                    </label>
                    <div class="input-group">
                        <i class="fas fa-user"></i>
                        <input type="text" id="lastname" name="lastname" 
                               placeholder="Enter last name"
                               value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>" 
                               required>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-at"></i> Username <span class="required">*</span>
                </label>
                <div class="input-group">
                    <i class="fas fa-at"></i>
                    <input type="text" id="username" name="username" 
                           placeholder="Choose a username (letters, numbers, underscores only)"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                           required pattern="[a-zA-Z0-9_]+" 
                           title="Username can only contain letters, numbers, and underscores">
                </div>
                <div id="username-feedback" class="password-hint"></div>
            </div>
            
            <div class="form-group">
                <label for="email">
                    <i class="fas fa-envelope"></i> Email Address <span class="required">*</span>
                </label>
                <div class="input-group">
                    <i class="fas fa-envelope"></i>
                    <input type="email" id="email" name="email" 
                           placeholder="Enter your email address"
                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                           required>
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Password <span class="required">*</span>
                </label>
                <div class="password-field">
                    <i class="fas fa-lock lock-icon"></i>
                    <input type="password" id="password" name="password" 
                           placeholder="Create a strong password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('password')" title="Show/Hide Password"></i>
                </div>
                <div class="password-hint" id="password-length">
                    <i class="fas fa-circle"></i> At least 6 characters
                </div>
                <div class="password-hint" id="password-uppercase">
                    <i class="fas fa-circle"></i> At least one uppercase letter
                </div>
                <div class="password-hint" id="password-lowercase">
                    <i class="fas fa-circle"></i> At least one lowercase letter
                </div>
                <div class="password-hint" id="password-number">
                    <i class="fas fa-circle"></i> At least one number
                </div>
            </div>
            
            <div class="form-group">
                <label for="confirm_password">
                    <i class="fas fa-lock"></i> Confirm Password <span class="required">*</span>
                </label>
                <div class="password-field">
                    <i class="fas fa-lock lock-icon"></i>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           placeholder="Re-enter your password" required>
                    <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password')" title="Show/Hide Password"></i>
                </div>
                <div id="password-match" class="password-hint"></div>
            </div>
            
            <div class="terms-group">
                <input type="checkbox" id="terms" name="terms" required>
                <label for="terms">
                    I agree to the <a href="#" onclick="alert('Terms and Conditions would be shown here'); return false;">Terms and Conditions</a> and <a href="#" onclick="alert('Privacy Policy would be shown here'); return false;">Privacy Policy</a>
                </label>
            </div>
            
            <button type="submit" class="btn-register" id="registerBtn">
                <i class="fas fa-user-plus"></i> Register
            </button>
        </form>
        <?php endif; ?>
        
        <div class="login-link">
            <p>Already have an account? <a href="<?php echo SITE_URL ?? ''; ?>/login.php">Sign In</a></p>
        </div>
    </div>
    
    <script>
        // Toggle password visibility function
        function togglePassword(fieldId) {
            const passwordInput = document.getElementById(fieldId);
            const toggleIcon = passwordInput.parentElement.querySelector('.toggle-password');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
                toggleIcon.title = 'Hide Password';
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
                toggleIcon.title = 'Show Password';
            }
        }
        
        // Real-time password validation
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('confirm_password');
        
        function validatePassword() {
            const value = password.value;
            
            // Length check
            const lengthHint = document.getElementById('password-length');
            if (value.length >= 6) {
                lengthHint.innerHTML = '<i class="fas fa-check-circle"></i> At least 6 characters';
                lengthHint.classList.add('valid');
            } else {
                lengthHint.innerHTML = '<i class="fas fa-circle"></i> At least 6 characters';
                lengthHint.classList.remove('valid');
            }
            
            // Uppercase check
            const upperHint = document.getElementById('password-uppercase');
            if (/[A-Z]/.test(value)) {
                upperHint.innerHTML = '<i class="fas fa-check-circle"></i> At least one uppercase letter';
                upperHint.classList.add('valid');
            } else {
                upperHint.innerHTML = '<i class="fas fa-circle"></i> At least one uppercase letter';
                upperHint.classList.remove('valid');
            }
            
            // Lowercase check
            const lowerHint = document.getElementById('password-lowercase');
            if (/[a-z]/.test(value)) {
                lowerHint.innerHTML = '<i class="fas fa-check-circle"></i> At least one lowercase letter';
                lowerHint.classList.add('valid');
            } else {
                lowerHint.innerHTML = '<i class="fas fa-circle"></i> At least one lowercase letter';
                lowerHint.classList.remove('valid');
            }
            
            // Number check
            const numberHint = document.getElementById('password-number');
            if (/[0-9]/.test(value)) {
                numberHint.innerHTML = '<i class="fas fa-check-circle"></i> At least one number';
                numberHint.classList.add('valid');
            } else {
                numberHint.innerHTML = '<i class="fas fa-circle"></i> At least one number';
                numberHint.classList.remove('valid');
            }
            
            // Password match
            checkPasswordMatch();
        }
        
        function checkPasswordMatch() {
            const matchHint = document.getElementById('password-match');
            if (confirmPassword.value) {
                if (password.value === confirmPassword.value) {
                    matchHint.innerHTML = '<i class="fas fa-check-circle"></i> Passwords match';
                    matchHint.classList.add('valid');
                } else {
                    matchHint.innerHTML = '<i class="fas fa-exclamation-circle"></i> Passwords do not match';
                    matchHint.classList.remove('valid');
                }
            } else {
                matchHint.innerHTML = '';
            }
        }
        
        if (password) {
            password.addEventListener('input', validatePassword);
        }
        if (confirmPassword) {
            confirmPassword.addEventListener('input', checkPasswordMatch);
        }
        
        // Username availability check (AJAX)
        const username = document.getElementById('username');
        const usernameFeedback = document.getElementById('username-feedback');
        let usernameTimer;
        
        if (username) {
            username.addEventListener('input', function() {
                clearTimeout(usernameTimer);
                const value = this.value;
                
                if (value.length < 3) {
                    usernameFeedback.innerHTML = '<i class="fas fa-exclamation-circle"></i> Username must be at least 3 characters';
                    usernameFeedback.classList.remove('valid');
                    return;
                }
                
                if (!/^[a-zA-Z0-9_]+$/.test(value)) {
                    usernameFeedback.innerHTML = '<i class="fas fa-exclamation-circle"></i> Only letters, numbers, and underscores allowed';
                    usernameFeedback.classList.remove('valid');
                    return;
                }
                
                usernameTimer = setTimeout(function() {
                    // Check username availability via AJAX
                    const xhr = new XMLHttpRequest();
                    xhr.open('GET', '<?php echo SITE_URL ?? ''; ?>/api/check_username.php?username=' + encodeURIComponent(value), true);
                    xhr.onload = function() {
                        if (xhr.status === 200) {
                            try {
                                const data = JSON.parse(xhr.responseText);
                                if (data.available) {
                                    usernameFeedback.innerHTML = '<i class="fas fa-check-circle"></i> Username is available';
                                    usernameFeedback.classList.add('valid');
                                } else {
                                    usernameFeedback.innerHTML = '<i class="fas fa-exclamation-circle"></i> Username is already taken';
                                    usernameFeedback.classList.remove('valid');
                                }
                            } catch(e) {
                                console.error('Error parsing response');
                            }
                        }
                    };
                    xhr.onerror = function() {
                        console.error('AJAX error');
                    };
                    xhr.send();
                }, 500);
            });
        }
        
        // Form validation before submit
        document.getElementById('registerForm')?.addEventListener('submit', function() {
            const btn = document.getElementById('registerBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            btn.disabled = true;
        });
    </script>
    
    <style>
        .password-hint.valid {
            color: #4CAF50;
        }
        
        .password-hint i {
            width: 16px;
            margin-right: 5px;
        }
        
        #username-feedback.valid {
            color: #4CAF50;
        }
        
        .success a {
            color: #2E7D32;
            text-decoration: underline;
        }
        
        .success a:hover {
            color: #1B5E20;
        }
    </style>
</body>
</html>