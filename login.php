<?php
/**
 * Login Page
 */

// Load configuration
require_once __DIR__ . '/config.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDE_PATH . '/functions.php';
require_once INCLUDE_PATH . '/auth.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $user = getCurrentUser();
    if ($user) {
        switch ($user['role']) {
            case 'super_admin':
                header('Location: ' . SITE_URL . '/superadmin/dashboard.php');
                break;
            case 'admin':
                header('Location: ' . SITE_URL . '/admin/dashboard.php');
                break;
            case 'supply':
                header('Location: ' . SITE_URL . '/supply/dashboard.php');
                break;
            default:
                header('Location: ' . SITE_URL . '/user/dashboard.php');
        }
        exit();
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    $result = $conn->query("SELECT * FROM users WHERE username = '$username' AND status = 'active'");
    
    if ($result && $row = $result->fetch_assoc()) {
        if (password_verify($password, $row['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['user_role'] = $row['role'];
            $_SESSION['user_name'] = $row['firstname'] . ' ' . $row['lastname'];
            
            // Log activity
            logActivity('Login', $row['id'], 'User logged in');
            
            // Redirect based on role
            switch ($row['role']) {
                case 'super_admin':
                    header('Location: ' . SITE_URL . '/superadmin/dashboard.php');
                    break;
                case 'admin':
                    header('Location: ' . SITE_URL . '/admin/dashboard.php');
                    break;
                case 'supply':
                    header('Location: ' . SITE_URL . '/supply/dashboard.php');
                    break;
                default:
                    header('Location: ' . SITE_URL . '/user/dashboard.php');
            }
            exit();
        } else {
            logActivity('Failed Login', 0, "Failed login attempt for username: $username");
            $error = 'Invalid password';
        }
    } else {
        logActivity('Failed Login', 0, "Failed login attempt - username not found: $username");
        $error = 'Username not found or account inactive';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IMS</title>
    
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- IIMS CUBES ICON - Using new color palette -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 640 640'%3E%3Cpath fill='%231A3263' d='M348 62.7C330.7 52.7 309.3 52.7 292 62.7L207.8 111.3C190.5 121.3 179.8 139.8 179.8 159.8L179.8 261.7L91.5 312.7C74.2 322.7 63.5 341.2 63.5 361.2L63.5 458.5C63.5 478.5 74.2 497 91.5 507L175.8 555.6C193.1 565.6 214.5 565.6 231.8 555.6L320.1 504.6L408.4 555.6C425.7 565.6 447.1 565.6 464.4 555.6L548.5 507C565.8 497 576.5 478.5 576.5 458.5L576.5 361.2C576.5 341.2 565.8 322.7 548.5 312.7L460.2 261.7L460.2 159.8C460.2 139.8 449.5 121.3 432.2 111.3L348 62.7zM296 356.6L296 463.1L207.7 514.1C206.5 514.8 205.1 515.2 203.7 515.2L203.7 409.9L296 356.6zM527.4 357.2C528.1 358.4 528.5 359.8 528.5 361.2L528.5 458.5C528.5 461.4 527 464 524.5 465.4L440.2 514C439 514.7 437.6 515.1 436.2 515.1L436.2 409.8L527.4 357.2zM412.3 159.8L412.3 261.7L320 315L320 208.5L411.2 155.9C411.9 157.1 412.3 158.5 412.3 159.9z'/%3E%3C/svg%3E">
    <link rel="shortcut icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 640 640'%3E%3Cpath fill='%231A3263' d='M348 62.7C330.7 52.7 309.3 52.7 292 62.7L207.8 111.3C190.5 121.3 179.8 139.8 179.8 159.8L179.8 261.7L91.5 312.7C74.2 322.7 63.5 341.2 63.5 361.2L63.5 458.5C63.5 478.5 74.2 497 91.5 507L175.8 555.6C193.1 565.6 214.5 565.6 231.8 555.6L320.1 504.6L408.4 555.6C425.7 565.6 447.1 565.6 464.4 555.6L548.5 507C565.8 497 576.5 478.5 576.5 458.5L576.5 361.2C576.5 341.2 565.8 322.7 548.5 312.7L460.2 261.7L460.2 159.8C460.2 139.8 449.5 121.3 432.2 111.3L348 62.7zM296 356.6L296 463.1L207.7 514.1C206.5 514.8 205.1 515.2 203.7 515.2L203.7 409.9L296 356.6zM527.4 357.2C528.1 358.4 528.5 359.8 528.5 361.2L528.5 458.5C528.5 461.4 527 464 524.5 465.4L440.2 514C439 514.7 437.6 515.1 436.2 515.1L436.2 409.8L527.4 357.2zM412.3 159.8L412.3 261.7L320 315L320 208.5L411.2 155.9C411.9 157.1 412.3 158.5 412.3 159.9z'/%3E%3C/svg%3E">
    
    <!-- Add pop animation to the favicon -->
    <style>
        /* This makes the browser tab icon pop */
        link[rel="icon"] {
            animation: faviconPop 3s infinite;
        }
        
        @keyframes faviconPop {
            0%, 100% { 
                transform: scale(1); 
            }
            50% { 
                transform: scale(1.2); 
            }
        }
        
        /* Add glow effect with new accent color */
        link[rel="icon"] {
            filter: drop-shadow(0 0 5px #FAB95B);
            transition: all 0.3s ease;
        }
        
        /* Login page styles with new color palette */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #1A3263 0%, #547792 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: #FFFFFF;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(26, 50, 99, 0.2);
            width: 450px;
            padding: 40px;
            text-align: center;
            animation: slideIn 0.5s ease;
            border-top: 4px solid #FAB95B;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .logo {
            margin-bottom: 30px;
        }
        
        .logo i {
            font-size: 60px;
            color: #1A3263;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
                color: #FAB95B;
            }
            100% {
                transform: scale(1);
                color: #1A3263;
            }
        }
        
        .logo h1 {
            color: #1A3263;
            margin-top: 10px;
            font-size: 32px;
            font-weight: 700;
            letter-spacing: 1px;
        }
        
        .logo p {
            color: #547792;
            font-size: 14px;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #1A3263;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group label i {
            color: #547792;
            margin-right: 5px;
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #547792;
            transition: color 0.3s;
        }
        
        .input-icon input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 2px solid #E8E2DB;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            color: #1A3263;
        }
        
        .input-icon input:focus {
            outline: none;
            border-color: #FAB95B;
            box-shadow: 0 0 0 3px rgba(250, 185, 91, 0.1);
        }
        
        .input-icon input:focus + i {
            color: #FAB95B;
        }
        
        .input-icon input::placeholder {
            color: #547792;
            opacity: 0.6;
        }
        
        .password-field {
            position: relative;
        }
        
        .password-field input {
            width: 100%;
            padding: 12px 40px 12px 40px;
            border: 2px solid #E8E2DB;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            color: #1A3263;
        }
        
        .password-field input:focus {
            outline: none;
            border-color: #FAB95B;
            box-shadow: 0 0 0 3px rgba(250, 185, 91, 0.1);
        }
        
        .password-field input::placeholder {
            color: #547792;
            opacity: 0.6;
        }
        
        .password-field .lock-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #547792;
        }
        
        .password-field .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #547792;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .password-field .toggle-password:hover {
            color: #FAB95B;
        }
        
        .btn-login {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #1A3263 0%, #547792 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(26, 50, 99, 0.3);
        }
        
        .btn-login:hover::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(250, 185, 91, 0.2), transparent);
            animation: shine 1.5s infinite;
        }
        
        @keyframes shine {
            to {
                left: 100%;
            }
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login i {
            margin-right: 8px;
        }
        
        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            text-align: left;
            border-left: 4px solid #dc3545;
            animation: shake 0.5s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error i {
            font-size: 18px;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }
        
        .remember-me label {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #547792;
            font-size: 14px;
            cursor: pointer;
        }
        
        .remember-me input[type="checkbox"] {
            width: 16px;
            height: 16px;
            cursor: pointer;
            accent-color: #FAB95B;
        }
        
        .forgot-password {
            color: #1A3263;
            text-decoration: none;
            font-size: 14px;
            transition: color 0.3s;
            font-weight: 500;
        }
        
        .forgot-password:hover {
            color: #FAB95B;
            text-decoration: underline;
        }
        
        .footer {
            margin-top: 30px;
            color: #547792;
            font-size: 12px;
            border-top: 1px solid #E8E2DB;
            padding-top: 20px;
        }
        
        .footer p {
            margin: 5px 0;
        }
        
        .demo-credentials {
            background-color: #E8E2DB;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0 10px;
            font-size: 12px;
        }
        
        .demo-credentials h4 {
            color: #1A3263;
            margin-bottom: 10px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .demo-credentials h4 i {
            color: #FAB95B;
        }
        
        .demo-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 8px;
        }
        
        .demo-item {
            background: white;
            padding: 8px;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid transparent;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .demo-item:hover {
            border-color: #FAB95B;
            transform: translateY(-2px);
            box-shadow: 0 3px 10px rgba(250, 185, 91, 0.2);
            background-color: #FAB95B;
        }
        
        .demo-item:hover i,
        .demo-item:hover span,
        .demo-item:hover small {
            color: #1A3263;
        }
        
        .demo-item i {
            color: #1A3263;
            font-size: 14px;
        }
        
        .demo-item span {
            font-weight: 600;
            color: #1A3263;
        }
        
        .demo-item small {
            color: #547792;
            margin-left: auto;
        }
        
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #FAB95B;
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }
            
            .demo-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="fas fa-cubes"></i>
            <h1>IMS</h1>
            <p>Inventory Management System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <div class="form-group">
                <label for="username">
                    <i class="fas fa-user"></i> Username
                </label>
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" 
                           id="username" 
                           name="username" 
                           required 
                           placeholder="Enter your username"
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                           autocomplete="username">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password">
                    <i class="fas fa-lock"></i> Password
                </label>
                <div class="password-field">
                    <i class="fas fa-lock lock-icon"></i>
                    <input type="password" 
                           id="password" 
                           name="password" 
                           required 
                           placeholder="Enter your password"
                           autocomplete="current-password">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword()" title="Show/Hide Password"></i>
                </div>
            </div>
            
            <div class="remember-me">
                <label>
                    <input type="checkbox" name="remember"> Remember me for 30 days
                </label>
                <a href="#" class="forgot-password" onclick="alert('Please contact your system administrator to reset your password.'); return false;">
                    Forgot Password?
                </a>
            </div>
            
            <button type="submit" class="btn-login" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i> Login
            </button>
        </form>
        
        <div class="demo-credentials">
            <h4>
                <i class="fas fa-info-circle"></i>
                Demo Credentials (Click to auto-fill)
            </h4>
            <div class="demo-grid">
                <div class="demo-item" onclick="fillCredentials('superadmin', 'admin123')">
                    <i class="fas fa-crown"></i>
                    <span>Super Admin</span>
                    <small>superadmin</small>
                </div>
                <div class="demo-item" onclick="fillCredentials('admin', 'admin123')">
                    <i class="fas fa-tools"></i>
                    <span>Admin</span>
                    <small>admin</small>
                </div>
                <div class="demo-item" onclick="fillCredentials('supply', 'supply123')">
                    <i class="fas fa-truck"></i>
                    <span>Supply Officer</span>
                    <small>supply</small>
                </div>
                <div class="demo-item" onclick="fillCredentials('user', 'user123')">
                    <i class="fas fa-user"></i>
                    <span>End User</span>
                    <small>user</small>
                </div>
            </div>
        </div>
        
        <div class="footer">
            <p>&copy; <?php echo date('Y'); ?> IMS. All rights reserved.</p>
            <p>Version 1.0.0</p>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.querySelector('.toggle-password');
            
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
        
        // Fill credentials function
        function fillCredentials(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            
            // Highlight the filled fields
            const usernameField = document.getElementById('username');
            const passwordField = document.getElementById('password');
            
            usernameField.style.borderColor = '#FAB95B';
            passwordField.style.borderColor = '#FAB95B';
            
            // Add success effect to demo item
            const demoItems = document.querySelectorAll('.demo-item');
            demoItems.forEach(item => {
                item.style.backgroundColor = '';
            });
            
            // Find and highlight clicked item
            event.currentTarget.style.backgroundColor = '#FAB95B';
            
            setTimeout(() => {
                usernameField.style.borderColor = '#E8E2DB';
                passwordField.style.borderColor = '#E8E2DB';
            }, 1000);
        }
        
        // Add loading state to button
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            
            btn.innerHTML = '<span class="spinner"></span> Logging in...';
            btn.disabled = true;
        });
        
        // Auto-focus username field
        window.addEventListener('load', function() {
            document.getElementById('username').focus();
        });
    </script>
</body>
</html>