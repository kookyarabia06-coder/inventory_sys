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
    
    <!-- IMS CUBES ICON - Updated with new color palette -->
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 640 640'%3E%3Cpath fill='%236B8CFF' d='M348 62.7C330.7 52.7 309.3 52.7 292 62.7L207.8 111.3C190.5 121.3 179.8 139.8 179.8 159.8L179.8 261.7L91.5 312.7C74.2 322.7 63.5 341.2 63.5 361.2L63.5 458.5C63.5 478.5 74.2 497 91.5 507L175.8 555.6C193.1 565.6 214.5 565.6 231.8 555.6L320.1 504.6L408.4 555.6C425.7 565.6 447.1 565.6 464.4 555.6L548.5 507C565.8 497 576.5 478.5 576.5 458.5L576.5 361.2C576.5 341.2 565.8 322.7 548.5 312.7L460.2 261.7L460.2 159.8C460.2 139.8 449.5 121.3 432.2 111.3L348 62.7zM296 356.6L296 463.1L207.7 514.1C206.5 514.8 205.1 515.2 203.7 515.2L203.7 409.9L296 356.6zM527.4 357.2C528.1 358.4 528.5 359.8 528.5 361.2L528.5 458.5C528.5 461.4 527 464 524.5 465.4L440.2 514C439 514.7 437.6 515.1 436.2 515.1L436.2 409.8L527.4 357.2zM412.3 159.8L412.3 261.7L320 315L320 208.5L411.2 155.9C411.9 157.1 412.3 158.5 412.3 159.9z'/%3E%3C/svg%3E">
    <link rel="shortcut icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 640 640'%3E%3Cpath fill='%236B8CFF' d='M348 62.7C330.7 52.7 309.3 52.7 292 62.7L207.8 111.3C190.5 121.3 179.8 139.8 179.8 159.8L179.8 261.7L91.5 312.7C74.2 322.7 63.5 341.2 63.5 361.2L63.5 458.5C63.5 478.5 74.2 497 91.5 507L175.8 555.6C193.1 565.6 214.5 565.6 231.8 555.6L320.1 504.6L408.4 555.6C425.7 565.6 447.1 565.6 464.4 555.6L548.5 507C565.8 497 576.5 478.5 576.5 458.5L576.5 361.2C576.5 341.2 565.8 322.7 548.5 312.7L460.2 261.7L460.2 159.8C460.2 139.8 449.5 121.3 432.2 111.3L348 62.7zM296 356.6L296 463.1L207.7 514.1C206.5 514.8 205.1 515.2 203.7 515.2L203.7 409.9L296 356.6zM527.4 357.2C528.1 358.4 528.5 359.8 528.5 361.2L528.5 458.5C528.5 461.4 527 464 524.5 465.4L440.2 514C439 514.7 437.6 515.1 436.2 515.1L436.2 409.8L527.4 357.2zM412.3 159.8L412.3 261.7L320 315L320 208.5L411.2 155.9C411.9 157.1 412.3 158.5 412.3 159.9z'/%3E%3C/svg%3E">
    
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
            filter: drop-shadow(0 0 5px #F8B0C0);
            transition: all 0.3s ease;
        }
        
        /* Login page styles with professional color palette */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', 'Segoe UI', sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #2C3E50 0%, #34495E 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            background: #FFFFFF;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 420px;
            padding: 35px;
            text-align: center;
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
            margin-bottom: 25px;
        }
        
        .logo i {
            font-size: 55px;
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
            font-size: 28px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .logo p {
            color: #6B6B6B;
            font-size: 13px;
            margin-top: 5px;
        }
        
        .form-group {
            margin-bottom: 20px;
            text-align: left;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 6px;
            color: #3A3A3A;
            font-weight: 500;
            font-size: 14px;
        }
        
        .form-group label i {
            color: #6B8CFF;
            margin-right: 5px;
            font-size: 13px;
        }
        
        .input-icon {
            position: relative;
        }
        
        .input-icon i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9E9E9E;
            transition: color 0.3s;
            font-size: 14px;
        }
        
        .input-icon input {
            width: 100%;
            padding: 12px 15px 12px 40px;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            color: #3A3A3A;
            background-color: #FAFAFA;
        }
        
        .input-icon input:focus {
            outline: none;
            border-color: #6B8CFF;
            background-color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.05);
        }
        
        .input-icon input:focus + i {
            color: #6B8CFF;
        }
        
        .input-icon input::placeholder {
            color: #9E9E9E;
            opacity: 0.7;
            font-size: 13px;
        }
        
        .password-field {
            position: relative;
        }
        
        .password-field input {
            width: 100%;
            padding: 12px 40px 12px 40px;
            border: 1px solid #E0E0E0;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            color: #3A3A3A;
            background-color: #FAFAFA;
        }
        
        .password-field input:focus {
            outline: none;
            border-color: #6B8CFF;
            background-color: #FFFFFF;
            box-shadow: 0 0 0 3px rgba(107, 140, 255, 0.05);
        }
        
        .password-field input::placeholder {
            color: #9E9E9E;
            opacity: 0.7;
            font-size: 13px;
        }
        
        .password-field .lock-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #9E9E9E;
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
            font-size: 14px;
        }
        
        .password-field .toggle-password:hover {
            color: #6B8CFF;
        }
        
        .btn-login {
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
            position: relative;
            overflow: hidden;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            background: #8FB5FF;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(107, 140, 255, 0.2);
        }
        
        .btn-login:active {
            transform: translateY(0);
        }
        
        .btn-login i {
            margin-right: 8px;
        }
        
        .btn-login:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .error {
            background-color: #FFEBEE;
            color: #C62828;
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 13px;
            text-align: left;
            border-left: 3px solid #f44336;
            animation: shake 0.5s;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .error i {
            font-size: 16px;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-3px); }
            20%, 40%, 60%, 80% { transform: translateX(3px); }
        }
        
        .remember-me {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin: 15px 0;
        }
        
        .remember-me label {
            display: flex;
            align-items: center;
            gap: 6px;
            color: #6B6B6B;
            font-size: 13px;
            cursor: pointer;
        }
        
        .remember-me input[type="checkbox"] {
            width: 14px;
            height: 14px;
            cursor: pointer;
            accent-color: #6B8CFF;
        }
        
        .forgot-password {
            color: #6B8CFF;
            text-decoration: none;
            font-size: 13px;
            transition: color 0.3s;
            font-weight: 500;
        }
        
        .forgot-password:hover {
            color: #8FB5FF;
            text-decoration: underline;
        }
        
        .footer {
            margin-top: 25px;
            color: #9E9E9E;
            font-size: 12px;
            border-top: 1px solid #F0F0F0;
            padding-top: 20px;
        }
        
        .footer p {
            margin: 3px 0;
        }
        
        .demo-credentials {
            background-color: #F8F9FA;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0 10px;
            font-size: 12px;
            border: 1px solid #E0E0E0;
        }
        
        .demo-credentials h4 {
            color: #3A3A3A;
            margin-bottom: 12px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 5px;
            justify-content: center;
        }
        
        .demo-credentials h4 i {
            color: #F8B0C0;
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
            border: 1px solid #E0E0E0;
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .demo-item:hover {
            border-color: #F8B0C0;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(248, 176, 192, 0.15);
            background: #F8B0C0;
        }
        
        .demo-item:hover i,
        .demo-item:hover span,
        .demo-item:hover small {
            color: white;
        }
        
        .demo-item i {
            color: #6B8CFF;
            font-size: 14px;
            transition: color 0.3s;
        }
        
        .demo-item span {
            font-weight: 500;
            color: #3A3A3A;
            font-size: 12px;
            transition: color 0.3s;
        }
        
        .demo-item small {
            color: #9E9E9E;
            margin-left: auto;
            font-size: 11px;
            transition: color 0.3s;
        }
        
        .spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 480px) {
            .login-container {
                padding: 25px 20px;
                width: 100%;
            }
            
            .demo-grid {
                grid-template-columns: 1fr;
            }
            
            .logo i {
                font-size: 50px;
            }
            
            .logo h1 {
                font-size: 24px;
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
                    <input type="checkbox" name="remember"> Remember me
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
            
            usernameField.style.borderColor = '#6B8CFF';
            passwordField.style.borderColor = '#6B8CFF';
            usernameField.style.backgroundColor = '#FFFFFF';
            passwordField.style.backgroundColor = '#FFFFFF';
            
            // Add success effect to demo item
            const demoItems = document.querySelectorAll('.demo-item');
            demoItems.forEach(item => {
                item.style.background = '';
                item.style.borderColor = '#E0E0E0';
            });
            
            // Find and highlight clicked item
            event.currentTarget.style.background = '#F8B0C0';
            event.currentTarget.style.borderColor = '#F8B0C0';
            
            setTimeout(() => {
                usernameField.style.borderColor = '#E0E0E0';
                passwordField.style.borderColor = '#E0E0E0';
                usernameField.style.backgroundColor = '#FAFAFA';
                passwordField.style.backgroundColor = '#FAFAFA';
            }, 1500);
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