<!-- register redirect -->
<?php



// Load configuration
require_once __DIR__ . '/config.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDE_PATH . '/functions.php';
require_once INCLUDE_PATH . '/auth.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: ' . getDashboardUrl());
    exit();
}


$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get form data
    $firstname = sanitize($_POST['firstname']);
    $lastname = sanitize($_POST['lastname']);
    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
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
        $check = $conn->query("SELECT id FROM users WHERE username = '$username'");
        if ($check && $check->num_rows > 0) {
            $errors[] = "Username already taken. Please choose another.";
        }
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email already exists
        $check = $conn->query("SELECT id FROM users WHERE email = '$email'");
        if ($check && $check->num_rows > 0) {
            $errors[] = "Email already registered. Please use another or login.";
        }
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
        $role = 'user'; // Default role for new registrations
        $status = 'pending'; // Set to pending - requires admin approval
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
            
            // Create notification for admins
            createAdminNotification("New user registration pending: $username ($email)");
            
            // Don't auto-login, show success message
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

/**
 * Create notification for admins about pending registration
 */
function createAdminNotification($message) {
    global $conn;
    
    // Get all admin and super admin users
    $admins = $conn->query("SELECT id FROM users WHERE role IN ('admin', 'super_admin') AND status = 'active'");
    
    if ($admins && $admins->num_rows > 0) {
        while ($admin = $admins->fetch_assoc()) {
            // Insert notification for each admin
            // You can create a notifications table or use activity_log
            logActivity('Pending Registration', $admin['id'], $message);
        }
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
    
    <!-- IMS CUBES ICON -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 640 640'%3E%3Cpath fill='%236B8CFF' d='M348 62.7C330.7 52.7 309.3 52.7 292 62.7L207.8 111.3C190.5 121.3 179.8 139.8 179.8 159.8L179.8 261.7L91.5 312.7C74.2 322.7 63.5 341.2 63.5 361.2L63.5 458.5C63.5 478.5 74.2 497 91.5 507L175.8 555.6C193.1 565.6 214.5 565.6 231.8 555.6L320.1 504.6L408.4 555.6C425.7 565.6 447.1 565.6 464.4 555.6L548.5 507C565.8 497 576.5 478.5 576.5 458.5L576.5 361.2C576.5 341.2 565.8 322.7 548.5 312.7L460.2 261.7L460.2 159.8C460.2 139.8 449.5 121.3 432.2 111.3L348 62.7zM296 356.6L296 463.1L207.7 514.1C206.5 514.8 205.1 515.2 203.7 515.2L203.7 409.9L296 356.6zM527.4 357.2C528.1 358.4 528.5 359.8 528.5 361.2L528.5 458.5C528.5 461.4 527 464 524.5 465.4L440.2 514C439 514.7 437.6 515.1 436.2 515.1L436.2 409.8L527.4 357.2zM412.3 159.8L412.3 261.7L320 315L320 208.5L411.2 155.9C411.9 157.1 412.3 158.5 412.3 159.9z'/%3E%3C/svg%3E">
    
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
            width: 500px;
            max-width: 100%;
            padding: 35px;
            animation: slideIn 0.5s ease;
            border: 1px solid #E0E0E0;
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
            align-items: center;
            gap: 10px;
        }
        
        .success i {
            font-size: 24px;
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
            background-color: #E3F2FD;
            color: #1976D2;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            border-left: 3px solid #2196F3;
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
            <i class="fas fa-cubes"></i>
            <h1>Create Account</h1>
            <p><?php echo $settings['system_name'] ?? 'IMS'; ?></p>
        </div>
        
        <div class="info-box">
            <i class="fas fa-info-circle"></i>
            <span>Registration requires admin approval. You will be notified once your account is activated.</span>
        </div>
        
        <?php if (!empty($errors)): ?>
            <div class="errors">
                <strong>Please fix the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="success">
                <i class="fas fa-check-circle"></i>
                <div>
                    <strong><?php echo $success; ?></strong>
                    <p style="margin-top: 5px;">You can now <a href="<?php echo SITE_URL; ?>/login.php" style="color: #2E7D32; font-weight: 600;">login</a> once your account is approved.</p>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (!$success): ?>
        <form method="POST" action="" id="registerForm" onsubmit="return validateForm()">
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
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="password" name="password" 
                           placeholder="Create a strong password" required>
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
                <div class="input-group">
                    <i class="fas fa-lock"></i>
                    <input type="password" id="confirm_password" name="confirm_password" 
                           placeholder="Re-enter your password" required>
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
            <p>  Already have an account? </p> <a href="<?php echo SITE_URL; ?>/login">Sign In</a></p>
        </div>
    </div>
    
    <script>
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
        
        password.addEventListener('input', validatePassword);
        confirmPassword.addEventListener('input', checkPasswordMatch);
        
        // Username availability check (AJAX)
        const username = document.getElementById('username');
        const usernameFeedback = document.getElementById('username-feedback');
        let usernameTimer;
        
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
                // AJAX check username availability
                fetch('<?php echo SITE_URL; ?>/api/check_username.php?username=' + encodeURIComponent(value))
                    .then(response => response.json())
                    .then(data => {
                        if (data.available) {
                            usernameFeedback.innerHTML = '<i class="fas fa-check-circle"></i> Username is available';
                            usernameFeedback.classList.add('valid');
                        } else {
                            usernameFeedback.innerHTML = '<i class="fas fa-exclamation-circle"></i> Username is already taken';
                            usernameFeedback.classList.remove('valid');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                    });
            }, 500);
        });
        
        // Form validation before submit
        function validateForm() {
            const btn = document.getElementById('registerBtn');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
            btn.disabled = true;
            return true;
        }
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
    </style>
</body>
</html>