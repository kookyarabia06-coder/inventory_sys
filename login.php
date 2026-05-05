<?php
/**
 * Login Page - Remember Me saves verification status
 */

require_once __DIR__ . '/config.php';
require_once CONFIG_PATH . '/database.php';
require_once INCLUDE_PATH . '/functions.php';
require_once INCLUDE_PATH . '/auth.php';
require_once INCLUDE_PATH . '/smtp_mailer.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    $user = getCurrentUser();
    if ($user) {
        $redirect = SITE_URL;
        switch ($user['role']) {
            case 'super_admin': $redirect .= '/superadmin/dashboard.php'; break;
            case 'admin': $redirect .= '/admin/dashboard.php'; break;
            case 'supply': $redirect .= '/supply/dashboard.php'; break;
            default: $redirect .= '/user/dashboard.php';
        }
        header('Location: ' . $redirect);
        exit();
    }
}

// Check for saved verification status from cookie (Remember Me)
$verification_passed = false;
if (isset($_COOKIE['verification_status']) && $_COOKIE['verification_status'] == '1') {
    $verification_passed = true;
}

$error = '';
$show_captcha = false;

// Create user_sessions table
$conn->query("CREATE TABLE IF NOT EXISTS `user_sessions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `session_token` varchar(255) NOT NULL,
    `created_at` datetime NOT NULL,
    `expires_at` datetime NOT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `session_token` (`session_token`),
    KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add OTP columns
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_otp VARCHAR(10) NULL DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_otp_expiry DATETIME NULL DEFAULT NULL");

// Handle Forgot Password
if (isset($_POST['forgot_password'])) {
    $reset_email = trim($_POST['reset_email']);
    if (!empty($reset_email)) {
        $check = $conn->prepare("SELECT id, username FROM users WHERE email = ?");
        $check->bind_param("s", $reset_email);
        $check->execute();
        $result = $check->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $update = $conn->prepare("UPDATE users SET reset_otp = ?, reset_otp_expiry = ? WHERE email = ?");
            $update->bind_param("sss", $otp, $expiry, $reset_email);
            $update->execute();
            $update->close();
            
            if (function_exists('sendForgotPasswordOTP')) {
                sendForgotPasswordOTP($reset_email, $otp, $row['username']);
            }
            $reset_success = "OTP sent! Valid for 10 minutes.";
        } else {
            $reset_error = "Email not found.";
        }
        $check->close();
    } else {
        $reset_error = "Enter your email address.";
    }
}

// Handle Verify OTP
if (isset($_POST['verify_otp'])) {
    $otp_code = trim($_POST['otp_code']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $reset_email = $_SESSION['reset_email'] ?? '';
    
    if (empty($otp_code) || empty($new_password) || empty($confirm_password)) {
        $reset_error_msg = "Fill all fields.";
    } elseif ($new_password !== $confirm_password) {
        $reset_error_msg = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $reset_error_msg = "Password must be at least 6 characters.";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE email = ? AND reset_otp = ? AND reset_otp_expiry > NOW()");
        $check->bind_param("ss", $reset_email, $otp_code);
        $check->execute();
        $result = $check->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ?, reset_otp = NULL, reset_otp_expiry = NULL WHERE id = ?");
            $update->bind_param("si", $hashed, $row['id']);
            $update->execute();
            $update->close();
            $reset_success_msg = "Password reset successfully!";
        } else {
            $reset_error_msg = "Invalid or expired OTP.";
        }
        $check->close();
    }
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['forgot_password']) && !isset($_POST['verify_otp'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    // Check if verification is needed (passed via hidden field from puzzle completion)
    $verification_ok = false;
    
    // If user has valid verification cookie from previous login, skip puzzle
    if ($verification_passed) {
        $verification_ok = true;
    } 
    // Otherwise, check if they just completed the puzzle
    else if (isset($_POST['verification_passed']) && $_POST['verification_passed'] == '1') {
        $verification_ok = true;
    }
    
    if (!$verification_ok) {
        $error = '🔒 Please complete the security verification first.';
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Check lock
            if ($row['locked_until'] && strtotime($row['locked_until']) > time()) {
                $remaining = ceil((strtotime($row['locked_until']) - time()) / 60);
                $error = "⏰ Account locked. Try again after {$remaining} minutes.";
            } elseif (password_verify($password, $row['password'])) {
                // Login success
                $conn->query("UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = {$row['id']}");
                
                $_SESSION['user_id'] = $row['id'];
                $_SESSION['role'] = $row['role'];
                $_SESSION['username'] = $row['username'];
                $_SESSION['user_name'] = $row['firstname'] . ' ' . $row['lastname'];
                $_SESSION['user_email'] = $row['email'];
                
                // Handle Remember Me - Save verification status to cookie
                if ($remember) {
                    // Save verification status for 1 hour
                    setcookie('verification_status', '1', time() + 3600, "/", "", false, true);
                    setcookie('remember_username', $username, time() + 3600, "/", "", false, true);
                } else {
                    // Clear cookies if not remembering
                    setcookie('verification_status', '', time() - 3600, "/");
                    setcookie('remember_username', '', time() - 3600, "/");
                }
                
                // Redirect
                $redirect = SITE_URL;
                switch ($row['role']) {
                    case 'super_admin': $redirect .= '/superadmin/dashboard.php'; break;
                    case 'admin': $redirect .= '/admin/dashboard.php'; break;
                    case 'supply': $redirect .= '/supply/dashboard.php'; break;
                    default: $redirect .= '/user/dashboard.php';
                }
                header('Location: ' . $redirect);
                exit();
            } else {
                // Failed login
                $attempts = (int)($row['login_attempts'] ?? 0) + 1;
                $remaining = 5 - $attempts;
                
                if ($attempts >= 5) {
                    $lock_until = date('Y-m-d H:i:s', time() + (15 * 60));
                    $conn->query("UPDATE users SET login_attempts = $attempts, locked_until = '$lock_until' WHERE id = {$row['id']}");
                    $error = "❌ Too many attempts. Account locked for 15 minutes.";
                } else {
                    $conn->query("UPDATE users SET login_attempts = $attempts WHERE id = {$row['id']}");
                    $error = "❌ Invalid password. {$remaining} attempt(s) remaining.";
                }
                
                if ($attempts >= 3) {
                    $show_captcha = true;
                }
            }
        } else {
            $error = '❌ Username or email not found.';
        }
        $stmt->close();
    }
}

// AJAX verification completion
if (isset($_POST['verification_complete'])) {
    echo json_encode(['success' => true]);
    exit();
}

$remembered_username = isset($_COOKIE['remember_username']) ? $_COOKIE['remember_username'] : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IMS | Rodriguez Medical Center</title>
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/icons/armmc.png">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/icons/armmc.png">
    <link rel="shortcut icon" href="<?php echo SITE_URL; ?>/assets/icons/armmc.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background: linear-gradient(135deg, #2C3E50 0%, #34495E 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-container { background: #FFFFFF; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); width: 450px; max-width: 100%; padding: 35px; animation: slideIn 0.5s ease; border: 1px solid #E0E0E0; }
        @keyframes slideIn { from { opacity: 0; transform: translateY(-15px); } to { opacity: 1; transform: translateY(0); } }
        .logo { text-align: center; margin-bottom: 25px; }
        .logo img { width: 80px; height: auto; margin-bottom: 10px; }
        .logo h1 { color: #3A3A3A; font-size: 28px; font-weight: 600; }
        .logo p { color: #6B6B6B; font-size: 13px; margin-top: 5px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 6px; color: #3A3A3A; font-weight: 500; font-size: 14px; }
        .form-group label i { color: #6B8CFF; margin-right: 5px; }
        .input-icon { position: relative; }
        .input-icon i { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9E9E9E; }
        .input-icon input { width: 100%; padding: 12px 15px 12px 40px; border: 1px solid #E0E0E0; border-radius: 8px; font-size: 14px; background-color: #FAFAFA; transition: all 0.3s; }
        .input-icon input:focus { outline: none; border-color: #6B8CFF; background-color: #FFFFFF; box-shadow: 0 0 0 3px rgba(107,140,255,0.05); }
        .password-field { position: relative; }
        .password-field input { width: 100%; padding: 12px 45px 12px 40px; border: 1px solid #E0E0E0; border-radius: 8px; font-size: 14px; background-color: #FAFAFA; box-sizing: border-box; }
        .password-field input:focus { outline: none; border-color: #6B8CFF; background-color: #FFFFFF; box-shadow: 0 0 0 3px rgba(107,140,255,0.05); }
        .password-field .lock-icon { position: absolute; left: 12px; top: 50%; transform: translateY(-50%); color: #9E9E9E; pointer-events: none; }
        .password-field .toggle-password { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: #9E9E9E; cursor: pointer; }
        .password-field .toggle-password:hover { color: #6B8CFF; }
        .security-checkbox { display: flex; align-items: center; gap: 12px; padding: 12px 0; cursor: pointer; }
        .security-checkbox input { width: 18px; height: 18px; cursor: pointer; accent-color: #6B8CFF; pointer-events: none; }
        .security-checkbox label { color: #3A3A3A; font-size: 14px; font-weight: 500; cursor: pointer; }
        .security-checkbox label i { color: #6B8CFF; margin-right: 6px; }
        .security-checkbox .status-icon { margin-left: auto; font-size: 14px; }
        .security-checkbox .status-icon.locked { color: #9E9E9E; }
        .security-checkbox .status-icon.unlocked { color: #4CAF50; }
        .btn-login { width: 100%; padding: 12px; background: #6B8CFF; color: white; border: none; border-radius: 8px; font-size: 15px; font-weight: 500; cursor: pointer; margin-top: 10px; transition: all 0.3s; }
        .btn-login:hover { background: #8FB5FF; transform: translateY(-1px); box-shadow: 0 4px 8px rgba(107,140,255,0.2); }
        .btn-login:disabled { opacity: 0.6; cursor: not-allowed; }
        .error { background-color: #FFEBEE; color: #C62828; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-size: 13px; border-left: 3px solid #f44336; display: flex; align-items: center; gap: 10px; }
        .remember-me { display: flex; align-items: center; justify-content: space-between; margin: 15px 0; }
        .remember-me label { display: flex; align-items: center; gap: 6px; color: #6B6B6B; font-size: 13px; cursor: pointer; }
        .remember-me input { width: 14px; height: 14px; cursor: pointer; accent-color: #6B8CFF; }
        .forgot-password { color: #6B8CFF; text-decoration: none; font-size: 13px; cursor: pointer; }
        .forgot-password:hover { color: #8FB5FF; text-decoration: underline; }
        .register-link { text-align: center; margin: 20px 0 15px; padding-top: 15px; border-top: 1px solid #F0F0F0; }
        .register-link p { color: #6B6B6B; font-size: 13px; }
        .register-link a { color: #6B8CFF; text-decoration: none; font-weight: 600; margin-left: 5px; }
        .demo-credentials { background-color: #F8F9FA; padding: 15px; border-radius: 10px; margin: 20px 0 10px; font-size: 12px; border: 1px solid #E0E0E0; }
        .demo-credentials h4 { color: #3A3A3A; margin-bottom: 12px; font-size: 13px; font-weight: 600; display: flex; align-items: center; gap: 5px; justify-content: center; }
        .demo-credentials h4 i { color: #F8B0C0; }
        .demo-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; }
        .demo-item { background: white; padding: 8px; border-radius: 6px; cursor: pointer; transition: all 0.3s; border: 1px solid #E0E0E0; display: flex; align-items: center; gap: 5px; }
        .demo-item:hover { border-color: #F8B0C0; background: #F8B0C0; }
        .demo-item:hover i, .demo-item:hover span, .demo-item:hover small { color: white; }
        .demo-item i { color: #6B8CFF; font-size: 14px; }
        .demo-item span { font-weight: 500; color: #3A3A3A; font-size: 12px; }
        .demo-item small { color: #9E9E9E; margin-left: auto; font-size: 11px; }
        .footer { margin-top: 20px; text-align: center; color: #9E9E9E; font-size: 11px; border-top: 1px solid #F0F0F0; padding-top: 15px; }
        .spinner { display: inline-block; width: 16px; height: 16px; border: 2px solid rgba(255,255,255,0.3); border-radius: 50%; border-top-color: white; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Modal Styles */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal-content { background: white; border-radius: 20px; width: 400px; max-width: 90%; padding: 30px 25px; text-align: center; animation: modalBounce 0.4s ease; position: relative; }
        @keyframes modalBounce { 0% { opacity: 0; transform: scale(0.8); } 70% { transform: scale(1.05); } 100% { opacity: 1; transform: scale(1); } }
        .modal-close-btn { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #9E9E9E; }
        .modal-close-btn:hover { color: #6B8CFF; }
        .modal-icon { font-size: 60px; margin-bottom: 15px; }
        .modal-icon.success { color: #4CAF50; }
        .modal-icon.error { color: #f44336; }
        .modal-icon.warning { color: #FF9800; }
        .modal-icon.info { color: #6B8CFF; }
        .modal-content h3 { color: #3A3A3A; font-size: 22px; margin-bottom: 10px; }
        .modal-content p { color: #6B6B6B; font-size: 14px; margin-bottom: 20px; }
        .modal-input { width: 100%; padding: 12px 15px; border: 1px solid #E0E0E0; border-radius: 10px; font-size: 14px; margin-bottom: 15px; }
        .modal-input:focus { outline: none; border-color: #6B8CFF; }
        .modal-btn { background: linear-gradient(135deg, #6B8CFF 0%, #8FB5FF 100%); color: white; border: none; padding: 12px 30px; border-radius: 40px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; }
        .modal-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(107,140,255,0.3); }
        .modal-btn-secondary { background: #f5f5f5; color: #3A3A3A; margin-top: 10px; }
        .modal-btn-secondary:hover { background: #E0E0E0; transform: translateY(-2px); }
        .modal-divider { display: flex; align-items: center; margin: 20px 0; color: #9E9E9E; font-size: 12px; }
        .modal-divider::before, .modal-divider::after { content: ""; flex: 1; height: 1px; background: #E0E0E0; }
        .modal-divider span { padding: 0 10px; }
        
        /* Puzzle Modal */
        .puzzle-modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); z-index: 1500; justify-content: center; align-items: center; }
        .puzzle-modal.active { display: flex; }
        .puzzle-container { background: white; border-radius: 16px; width: 420px; max-width: 90%; padding: 25px; text-align: center; animation: modalSlideIn 0.3s ease; position: relative; }
        @keyframes modalSlideIn { from { opacity: 0; transform: translateY(-20px); } to { opacity: 1; transform: translateY(0); } }
        .puzzle-close { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #9E9E9E; }
        .puzzle-close:hover { color: #6B8CFF; }
        .progress-wrapper { margin-bottom: 20px; }
        .progress-text { font-size: 13px; color: #6B6B6B; margin-bottom: 8px; }
        .progress-text span { color: #6B8CFF; font-weight: 600; }
        .progress-bar-container { background: #E0E0E0; border-radius: 10px; height: 6px; overflow: hidden; }
        .progress-bar-fill { background: #6B8CFF; height: 100%; border-radius: 10px; transition: width 0.3s ease; width: 0%; }
        .question-text { font-size: 18px; font-weight: 600; color: #3A3A3A; margin-bottom: 20px; }
        .puzzle-options { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 20px; }
        .puzzle-option { background: #F8F9FA; border: 1px solid #E0E0E0; border-radius: 10px; padding: 12px; cursor: pointer; font-size: 14px; font-weight: 500; color: #3A3A3A; transition: all 0.2s; }
        .puzzle-option:hover { border-color: #6B8CFF; background: #F0F4FF; }
        .puzzle-option.selected { border-color: #6B8CFF; background: #6B8CFF; color: white; }
        .verify-btn { background: #6B8CFF; color: white; border: none; padding: 12px; border-radius: 10px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; }
        .verify-btn:hover { background: #8FB5FF; }
        .puzzle-message { margin-top: 12px; font-size: 12px; padding: 8px; border-radius: 8px; }
        .puzzle-message.error { background: #FFEBEE; color: #C62828; }
        .puzzle-message.success { background: #E8F5E9; color: #2E7D32; }
        @media (max-width: 480px) { .login-container { padding: 25px 20px; } .demo-grid { grid-template-columns: 1fr; } .puzzle-options { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <img src="assets/icons/armmc.png" alt="Rodriguez Memorial Medical Center">
            <h1>IMS</h1>
            <p>Rodriguez Memorial Medical Center</p>
        </div>
        
        <?php if ($error): ?>
            <div class="error"><i class="fas fa-exclamation-circle"></i><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm">
            <input type="hidden" name="verification_passed" id="verification_passed" value="0">
            
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> Username or Email</label>
                <div class="input-icon">
                    <i class="fas fa-user"></i>
                    <input type="text" id="username" name="username" required placeholder="Enter your username or email" value="<?php echo htmlspecialchars($remembered_username); ?>" autocomplete="username">
                </div>
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <div class="password-field">
                    <i class="fas fa-lock lock-icon"></i>
                    <input type="password" id="password" name="password" required placeholder="Enter your password" autocomplete="current-password">
                    <i class="fas fa-eye toggle-password" onclick="togglePassword()" title="Show/Hide Password"></i>
                </div>
            </div>
            
            <div class="security-checkbox" id="securityCheckbox">
                <input type="checkbox" id="robot_check" name="robot_check_temp" <?php echo $verification_passed ? 'checked' : ''; ?>>
                <label for="robot_check"><i class="fas fa-shield-alt"></i> Security Verification</label>
                <div class="status-icon <?php echo $verification_passed ? 'unlocked' : 'locked'; ?>" id="securityStatusIcon">
                    <i class="fas <?php echo $verification_passed ? 'fa-unlock-alt' : 'fa-lock'; ?>"></i>
                </div>
            </div>
            
            <div class="remember-me">
                <label><input type="checkbox" name="remember" id="rememberCheckbox"> Remember me (1 hour - no re-verification)</label>
                <a class="forgot-password" onclick="showForgotPasswordModal()">Forgot Password?</a>
            </div>
            
            <button type="submit" class="btn-login" id="loginBtn"><i class="fas fa-sign-in-alt"></i> Login</button>
        </form>
        
        <div class="register-link"><p>Don't have an account? <a href="<?php echo SITE_URL; ?>/register.php">Register here</a></p></div>
        
        <div class="demo-credentials">
            <h4><i class="fas fa-info-circle"></i> Demo Credentials (Click to auto-fill)</h4>
            <div class="demo-grid">
                <div class="demo-item" onclick="fillCredentials('superadmin', 'admin123', event)"><i class="fas fa-crown"></i><span>Super Admin</span><small>superadmin</small></div>
                <div class="demo-item" onclick="fillCredentials('admin', 'admin123', event)"><i class="fas fa-tools"></i><span>Admin</span><small>admin</small></div>
                <div class="demo-item" onclick="fillCredentials('supply', 'supply123', event)"><i class="fas fa-truck"></i><span>Supply Officer</span><small>supply</small></div>
                <div class="demo-item" onclick="fillCredentials('user', 'user123', event)"><i class="fas fa-user"></i><span>End User</span><small>user</small></div>
            </div>
        </div>
        
        <div class="footer"><p>&copy; <?php echo date('Y'); ?> IMS. All rights reserved.</p><p>Version 1.0.0</p></div>
    </div>
    
    <!-- Puzzle Modal -->
    <div id="puzzleModal" class="puzzle-modal">
        <div class="puzzle-container">
            <span class="puzzle-close" onclick="closePuzzleModal()">&times;</span>
            <h3><i class="fas fa-puzzle-piece"></i> Security Verification</h3>
            <p>Answer 3 questions correctly to verify you are human</p>
            <div class="progress-wrapper">
                <div class="progress-text">Progress: <span id="completedCount">0</span> / <span>3</span> correct</div>
                <div class="progress-bar-container"><div class="progress-bar-fill" id="progressFill"></div></div>
            </div>
            <div class="question-text" id="questionText"></div>
            <div class="puzzle-options" id="optionsContainer"></div>
            <button class="verify-btn" id="verifyBtn" onclick="verifyAnswer()">Submit Answer</button>
            <div id="puzzleMessage" class="puzzle-message"></div>
        </div>
    </div>
    
    <!-- Custom Modal -->
    <div id="customModal" class="modal-overlay">
        <div class="modal-content">
            <span class="modal-close-btn" onclick="closeCustomModal()">&times;</span>
            <div id="modalIcon" class="modal-icon success"><i class="fas fa-check-circle"></i></div>
            <h3 id="modalTitle">Success</h3>
            <p id="modalMessage">Operation completed successfully.</p>
            <button class="modal-btn" onclick="closeCustomModal()">OK</button>
        </div>
    </div>
    
    <!-- Forgot Password Modal -->
    <div id="forgotPasswordModal" class="modal-overlay">
        <div class="modal-content">
            <span class="modal-close-btn" onclick="closeForgotPasswordModal()">&times;</span>
            <div class="modal-icon info"><i class="fas fa-key"></i></div>
            <h3>Reset Password</h3>
            <p>Enter your email address to receive a 6-digit OTP.</p>
            <form id="forgotPasswordForm" method="POST" action="">
                <input type="email" name="reset_email" class="modal-input" placeholder="Email Address" required>
                <input type="hidden" name="forgot_password" value="1">
                <button type="submit" class="modal-btn">Send OTP</button>
            </form>
            <div class="modal-divider"><span>or</span></div>
            <button class="modal-btn modal-btn-secondary" onclick="showResetWithOTPModal()">I have an OTP</button>
            <?php if (isset($reset_success)): ?>
                <div class="puzzle-message success" style="margin-top: 15px;"><?php echo $reset_success; ?></div>
            <?php endif; ?>
            <?php if (isset($reset_error)): ?>
                <div class="puzzle-message error" style="margin-top: 15px;"><?php echo $reset_error; ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Reset OTP Modal -->
    <div id="resetOTPModal" class="modal-overlay">
        <div class="modal-content">
            <span class="modal-close-btn" onclick="closeResetOTPModal()">&times;</span>
            <div class="modal-icon info"><i class="fas fa-lock"></i></div>
            <h3>Enter OTP & New Password</h3>
            <p>Enter the 6-digit OTP from your email and your new password.</p>
            <form id="resetPasswordForm" method="POST" action="">
                <input type="text" name="otp_code" class="modal-input" placeholder="6-digit OTP" maxlength="6" required>
                <input type="password" name="new_password" class="modal-input" placeholder="New Password" id="resetNewPassword" required>
                <input type="password" name="confirm_password" class="modal-input" placeholder="Confirm New Password" id="resetConfirmPassword" required>
                <input type="hidden" name="verify_otp" value="1">
                <button type="submit" class="modal-btn">Reset Password</button>
            </form>
            <?php if (isset($reset_success_msg)): ?>
                <div class="puzzle-message success" style="margin-top: 15px;"><?php echo $reset_success_msg; ?></div>
            <?php endif; ?>
            <?php if (isset($reset_error_msg)): ?>
                <div class="puzzle-message error" style="margin-top: 15px;"><?php echo $reset_error_msg; ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        const allPuzzles = [
            { question: "What is 8 + 5?", options: ["11", "12", "13", "14"], correct: 2 },
            { question: "Which of these is a fruit?", options: ["🍅 Tomato", "🥕 Carrot", "🍎 Apple", "🥔 Potato"], correct: 2 },
            { question: "Select the number greater than 15", options: ["10", "12", "18", "14"], correct: 2 },
            { question: "What is 12 - 7?", options: ["4", "5", "6", "7"], correct: 1 },
            { question: "Which one is a vehicle?", options: ["✈️ Airplane", "📚 Book", "✏️ Pencil", "☕ Coffee"], correct: 0 },
            { question: "What is 3 × 4?", options: ["10", "11", "12", "13"], correct: 2 },
            { question: "Color of the sky on a clear day?", options: ["🟢 Green", "🔴 Red", "🔵 Blue", "🟡 Yellow"], correct: 2 },
            { question: "What is 9 + 6?", options: ["14", "15", "16", "17"], correct: 1 },
            { question: "Which animal says 'Meow'?", options: ["🐶 Dog", "🐱 Cat", "🐄 Cow", "🐔 Chicken"], correct: 1 },
            { question: "What is 20 ÷ 4?", options: ["3", "4", "5", "6"], correct: 2 }
        ];
        
        let correctCount = 0, currentQuestion = null, selectedAnswer = null, isVerifying = false;
        let verificationCompleted = <?php echo $verification_passed ? 'true' : 'false'; ?>;
        
        function showCustomModal(icon, title, message, type) {
            let modal = document.getElementById('customModal'), iconEl = document.getElementById('modalIcon');
            iconEl.innerHTML = `<i class="fas ${icon}"></i>`; iconEl.className = `modal-icon ${type}`;
            document.getElementById('modalTitle').innerText = title; document.getElementById('modalMessage').innerText = message;
            modal.classList.add('active');
        }
        function closeCustomModal() { document.getElementById('customModal').classList.remove('active'); }
        function showForgotPasswordModal() { document.getElementById('forgotPasswordModal').classList.add('active'); }
        function closeForgotPasswordModal() { document.getElementById('forgotPasswordModal').classList.remove('active'); }
        function showResetWithOTPModal() { closeForgotPasswordModal(); document.getElementById('resetOTPModal').classList.add('active'); }
        function closeResetOTPModal() { document.getElementById('resetOTPModal').classList.remove('active'); }
        function showVerificationRequiredModal() { showCustomModal('fa-exclamation-triangle', 'Verification Required', 'Please complete the security verification first.', 'warning'); }
        function showSuccessPopup() { showCustomModal('fa-check-circle', 'Verification Complete!', 'Security verification saved for 1 hour!', 'success'); }
        
        function updateCheckboxUI() {
            let cb = document.getElementById('robot_check'), si = document.getElementById('securityStatusIcon'), hf = document.getElementById('verification_passed');
            if (verificationCompleted) { cb.checked = true; si.innerHTML = '<i class="fas fa-unlock-alt"></i>'; si.className = 'status-icon unlocked'; hf.value = '1'; }
            else { cb.checked = false; si.innerHTML = '<i class="fas fa-lock"></i>'; si.className = 'status-icon locked'; hf.value = '0'; }
        }
        
        function completeVerification() {
            verificationCompleted = true; updateCheckboxUI();
            showSuccessPopup();
        }
        
        function closePuzzleModal() { document.getElementById('puzzleModal').classList.remove('active'); }
        function getRandomQuestion() { return { ...allPuzzles[Math.floor(Math.random() * allPuzzles.length)] }; }
        
        function startPuzzles() {
            if (verificationCompleted) { showSuccessPopup(); return; }
            correctCount = 0; updateModalProgress(); getNewQuestion(); document.getElementById('puzzleModal').classList.add('active');
        }
        
        function getNewQuestion() {
            currentQuestion = getRandomQuestion(); selectedAnswer = null;
            document.getElementById('questionText').innerHTML = currentQuestion.question;
            let html = ''; currentQuestion.options.forEach((opt, i) => { html += `<div class="puzzle-option" onclick="selectOption(${i})">${opt}</div>`; });
            document.getElementById('optionsContainer').innerHTML = html; document.getElementById('puzzleMessage').innerHTML = '';
            document.getElementById('verifyBtn').innerHTML = correctCount === 2 ? 'Complete ✓' : 'Submit Answer'; document.getElementById('verifyBtn').disabled = false;
        }
        
        function updateModalProgress() {
            document.getElementById('completedCount').innerText = correctCount;
            document.getElementById('progressFill').style.width = (correctCount / 3) * 100 + '%';
        }
        
        function selectOption(i) { selectedAnswer = i; document.querySelectorAll('.puzzle-option').forEach((opt, idx) => { if (idx === i) opt.classList.add('selected'); else opt.classList.remove('selected'); }); }
        
        async function verifyAnswer() {
            if (isVerifying) return;
            if (selectedAnswer === null) { document.getElementById('puzzleMessage').innerHTML = '<i class="fas fa-exclamation-triangle"></i> Select an answer'; document.getElementById('puzzleMessage').className = 'puzzle-message error'; return; }
            isVerifying = true; let btn = document.getElementById('verifyBtn'); btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Checking...';
            if (selectedAnswer === currentQuestion.correct) {
                correctCount++; updateModalProgress();
                if (correctCount >= 3) {
                    document.getElementById('puzzleMessage').innerHTML = '<i class="fas fa-check-circle"></i> Complete!'; document.getElementById('puzzleMessage').className = 'puzzle-message success';
                    completeVerification(); setTimeout(() => { document.getElementById('puzzleModal').classList.remove('active'); isVerifying = false; }, 1000);
                } else {
                    document.getElementById('puzzleMessage').innerHTML = '<i class="fas fa-check-circle"></i> Correct! Next...'; document.getElementById('puzzleMessage').className = 'puzzle-message success';
                    setTimeout(() => { getNewQuestion(); isVerifying = false; }, 800);
                }
            } else {
                document.getElementById('puzzleMessage').innerHTML = '<i class="fas fa-times-circle"></i> Wrong! Try another...'; document.getElementById('puzzleMessage').className = 'puzzle-message error';
                setTimeout(() => { getNewQuestion(); isVerifying = false; }, 1200);
            }
        }
        
        function togglePassword() {
            let pwd = document.getElementById('password'), icon = document.querySelector('.toggle-password');
            if (pwd.type === 'password') { pwd.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
            else { pwd.type = 'password'; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
        }
        
        function fillCredentials(u, p, e) {
            document.getElementById('username').value = u; document.getElementById('password').value = p;
            let uf = document.getElementById('username'), pf = document.getElementById('password');
            uf.style.borderColor = '#6B8CFF'; pf.style.borderColor = '#6B8CFF';
            document.querySelectorAll('.demo-item').forEach(i => i.style.background = '');
            if (e && e.currentTarget) e.currentTarget.style.background = '#F8B0C0';
            setTimeout(() => { uf.style.borderColor = '#E0E0E0'; pf.style.borderColor = '#E0E0E0'; }, 1500);
        }
        
        document.getElementById('securityCheckbox')?.addEventListener('click', function(e) {
            e.preventDefault();
            if (!verificationCompleted) startPuzzles();
            else showSuccessPopup();
        });
        
        document.getElementById('loginForm')?.addEventListener('submit', function(e) {
            if (!verificationCompleted) {
                e.preventDefault();
                showVerificationRequiredModal();
                return false;
            }
            let btn = document.getElementById('loginBtn');
            btn.innerHTML = '<span class="spinner"></span> Logging in...';
            btn.disabled = true;
        });
        
        window.addEventListener('load', function() { document.getElementById('username')?.focus(); });
        
        ['customModal', 'forgotPasswordModal', 'resetOTPModal'].forEach(modalId => {
            let modal = document.getElementById(modalId);
            if (modal) modal.addEventListener('click', function(e) { if (e.target === this) this.classList.remove('active'); });
        });
    </script>
</body>
</html>