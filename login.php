<?php
/**
 * Login Page
 * Handles user authentication with FULL audit trail integration
 */

// For root files, use __DIR__ directly
define('ROOT_PATH', __DIR__);

// Load configuration
require_once ROOT_PATH . '/config.php';

// Load database
require_once ROOT_PATH . '/config/database.php';

// Load auth.php for authentication functions
require_once ROOT_PATH . '/includes/auth.php';

// Load smtp mailer for OTP
require_once ROOT_PATH . '/includes/smtp_mailer.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ============================================
// FIXED AUDIT TRAIL FUNCTION - MATCHES YOUR TABLE STRUCTURE
// ============================================
function logAuditEvent($conn, $user_id, $action, $category, $description, $details = null, $table_name = null, $record_id = null, $old_value = null, $new_value = null) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    // For user_id = 0 or null, use NULL in database
    $user_id_param = ($user_id > 0) ? $user_id : null;
    
    // Prepare the statement with all columns
    $stmt = $conn->prepare("INSERT INTO audit_trail (user_id, action, action_category, description, ip_address, user_agent, details, table_name, record_id, old_value, new_value, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    
    if (!$stmt) {
        error_log("Audit trail prepare failed: " . $conn->error);
        // Fallback to simple insert
        $simple_query = "INSERT INTO audit_trail (user_id, action, action_category, description, ip_address, user_agent, created_at) VALUES ($user_id_param, '$action', '$category', '$description', '$ip', '$user_agent', NOW())";
        return $conn->query($simple_query);
    }
    
    $stmt->bind_param("isssssssiss", 
        $user_id_param, 
        $action, 
        $category, 
        $description, 
        $ip, 
        $user_agent, 
        $details, 
        $table_name, 
        $record_id, 
        $old_value, 
        $new_value
    );
    
    $result = $stmt->execute();
    
    if (!$result) {
        error_log("Audit trail insert failed: " . $stmt->error);
    }
    
    $stmt->close();
    return $result;
}

// ============================================
// REDIRECT IF ALREADY LOGGED IN
// ============================================
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

$error = '';

// ============================================
// DATABASE TABLE SETUP FOR OTP
// ============================================
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_otp VARCHAR(10) NULL DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS reset_otp_expiry DATETIME NULL DEFAULT NULL");

// ============================================
// HANDLE FORGOT PASSWORD - SEND OTP
// ============================================
if (isset($_POST['forgot_password'])) {
    $reset_email = trim($_POST['reset_email']);
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    if (!empty($reset_email)) {
        $check = $conn->prepare("SELECT id, username, email FROM users WHERE email = ?");
        $check->bind_param("s", $reset_email);
        $check->execute();
        $result = $check->get_result();
        
        if ($row = $result->fetch_assoc()) {
            // Generate 6-digit OTP
            $otp = sprintf("%06d", mt_rand(1, 999999));
            $expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Store OTP in database
            $update = $conn->prepare("UPDATE users SET reset_otp = ?, reset_otp_expiry = ? WHERE id = ?");
            $update->bind_param("ssi", $otp, $expiry, $row['id']);
            $update->execute();
            $update->close();
            
            // Send OTP email
            $email_sent = sendForgotPasswordOTP($reset_email, $otp, $row['username']);
            
            if ($email_sent) {
                $_SESSION['reset_success'] = "OTP sent to " . htmlspecialchars($reset_email) . "! Valid for 10 minutes.";
                $_SESSION['reset_email'] = $reset_email;
                
                // LOG: OTP Sent Successfully
                logAuditEvent($conn, $row['id'], 'PASSWORD_RESET_OTP_SENT', 'SECURITY', 
                    "Password reset OTP sent successfully to email: $reset_email", 
                    "User: {$row['username']} | IP: $ip_address", 'users', $row['id']);
            } else {
                $_SESSION['reset_error'] = "Failed to send OTP email. Please check SMTP settings.";
                
                // LOG: OTP Send Failed
                logAuditEvent($conn, $row['id'], 'PASSWORD_RESET_OTP_FAILED', 'SECURITY', 
                    "Failed to send password reset OTP to email: $reset_email", 
                    "User: {$row['username']} | SMTP error | IP: $ip_address", 'users', $row['id']);
            }
        } else {
            $_SESSION['reset_error'] = "Email address not found in our records.";
            
            // LOG: Email Not Found
            logAuditEvent($conn, 0, 'PASSWORD_RESET_EMAIL_NOT_FOUND', 'SECURITY', 
                "Password reset attempted with non-existent email: $reset_email", 
                "IP: $ip_address");
        }
        $check->close();
    } else {
        $_SESSION['reset_error'] = "Please enter your email address.";
        
        // LOG: Empty Email Attempt
        logAuditEvent($conn, 0, 'PASSWORD_RESET_EMPTY_EMAIL', 'SECURITY', 
            "Password reset attempted with empty email field", 
            "IP: $ip_address");
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ============================================
// DISPLAY RESET MESSAGES
// ============================================
$reset_success = isset($_SESSION['reset_success']) ? $_SESSION['reset_success'] : null;
$reset_error = isset($_SESSION['reset_error']) ? $_SESSION['reset_error'] : null;
$reset_success_msg = isset($_SESSION['reset_success_msg']) ? $_SESSION['reset_success_msg'] : null;
$reset_error_msg = isset($_SESSION['reset_error_msg']) ? $_SESSION['reset_error_msg'] : null;
unset($_SESSION['reset_success'], $_SESSION['reset_error'], $_SESSION['reset_success_msg'], $_SESSION['reset_error_msg']);

// ============================================
// HANDLE VERIFY OTP AND RESET PASSWORD
// ============================================
if (isset($_POST['verify_otp'])) {
    $otp_code = trim($_POST['otp_code']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $reset_email = $_SESSION['reset_email'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    if (empty($otp_code) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['reset_error_msg'] = "Please fill in all fields.";
        
        // LOG: Incomplete Form Submission
        logAuditEvent($conn, 0, 'PASSWORD_RESET_INCOMPLETE', 'SECURITY', 
            "Password reset form submitted with missing fields", 
            "Email: $reset_email | IP: $ip_address");
            
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['reset_error_msg'] = "Passwords do not match.";
        
        // LOG: Password Mismatch
        logAuditEvent($conn, 0, 'PASSWORD_RESET_MISMATCH', 'SECURITY', 
            "Password reset attempted with mismatched passwords", 
            "Email: $reset_email | IP: $ip_address");
            
    } elseif (strlen($new_password) < 6) {
        $_SESSION['reset_error_msg'] = "Password must be at least 6 characters long.";
        
        // LOG: Weak Password Attempt
        logAuditEvent($conn, 0, 'PASSWORD_RESET_WEAK_PASSWORD', 'SECURITY', 
            "Password reset attempted with weak password (less than 6 chars)", 
            "Email: $reset_email | IP: $ip_address");
            
    } else {
        $check = $conn->prepare("SELECT id, username FROM users WHERE email = ? AND reset_otp = ? AND reset_otp_expiry > NOW()");
        $check->bind_param("ss", $reset_email, $otp_code);
        $check->execute();
        $result = $check->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $update = $conn->prepare("UPDATE users SET password = ?, reset_otp = NULL, reset_otp_expiry = NULL WHERE id = ?");
            $update->bind_param("si", $hashed, $row['id']);
            $update->execute();
            $update->close();
            $_SESSION['reset_success_msg'] = "Password has been reset successfully! You can now login.";
            
            // LOG: Password Reset Success
            logAuditEvent($conn, $row['id'], 'PASSWORD_RESET_SUCCESS', 'SECURITY', 
                "Password reset successfully using OTP", 
                "User: {$row['username']} | Email: $reset_email | IP: $ip_address", 'users', $row['id']);
            
            unset($_SESSION['reset_email']);
        } else {
            $_SESSION['reset_error_msg'] = "Invalid or expired OTP. Please request a new one.";
            
            // LOG: Invalid OTP Attempt
            logAuditEvent($conn, 0, 'PASSWORD_RESET_INVALID_OTP', 'SECURITY', 
                "Invalid or expired OTP used for password reset", 
                "Email: $reset_email | OTP entered: $otp_code | IP: $ip_address");
        }
        $check->close();
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

// ============================================
// HANDLE LOGIN
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && !isset($_POST['forgot_password']) && !isset($_POST['verify_otp'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
    
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $stmt->bind_param("ss", $username, $username);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        
        // ============================================
        // LOCK CHECK WITH AUTO-UNLOCK
        // ============================================
        $is_locked = false;
        $lock_message = '';
        $current_time = time();
        
        if (!empty($row['locked_until']) && $row['locked_until'] != '0000-00-00 00:00:00') {
            $lock_timestamp = strtotime($row['locked_until']);
            
            if ($lock_timestamp > $current_time) {
                $is_locked = true;
                $remaining_seconds = $lock_timestamp - $current_time;
                $remaining_minutes = ceil($remaining_seconds / 60);
                $lock_message = "⏰ Account locked. Please try again after {$remaining_minutes} minute(s).";
                
                // LOG: Failed Login on Locked Account
                logAuditEvent($conn, $row['id'], 'LOGIN_ATTEMPT_LOCKED', 'SECURITY', 
                    "Login attempt on locked account: $username", 
                    "Locked until: {$row['locked_until']} | IP: $ip_address", 'users', $row['id']);
            } else {
                // Lock expired - automatically unlock
                $conn->query("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE id = {$row['id']}");
                
                // LOG: Account Auto-Unlocked
                logAuditEvent($conn, $row['id'], 'ACCOUNT_AUTO_UNLOCKED', 'SECURITY', 
                    "Account automatically unlocked after lock period expired", 
                    "Locked until was: {$row['locked_until']} | IP: $ip_address", 'users', $row['id']);
                    
                $row['locked_until'] = null;
                $row['login_attempts'] = 0;
            }
        }
        
        if ($is_locked) {
            $error = $lock_message;
        } elseif (password_verify($password, $row['password'])) {
            // ============================================
            // LOGIN SUCCESS
            // ============================================
            $conn->query("UPDATE users SET login_attempts = 0, locked_until = NULL, last_login = NOW() WHERE id = {$row['id']}");
            
            $_SESSION['user_id'] = $row['id'];
            $_SESSION['role'] = $row['role'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['user_name'] = $row['firstname'] . ' ' . $row['lastname'];
            $_SESSION['user_email'] = $row['email'];
            
            if ($remember) {
                setcookie('remember_username', $username, time() + (86400 * 30), "/", "", false, true);
            } else {
                setcookie('remember_username', '', time() - 3600, "/");
            }
            
            // LOG: Successful Login - THIS IS THE CRITICAL LOG
            $log_result = logAuditEvent($conn, $row['id'], 'LOGIN_SUCCESS', 'AUTHENTICATION', 
                "User logged in successfully: $username", 
                "Role: {$row['role']} | Remember Me: " . ($remember ? 'Yes' : 'No') . " | IP: $ip_address", 
                'users', $row['id']);
            
            // Debug: Check if log was inserted
            if (!$log_result) {
                error_log("Failed to insert login audit for user: $username");
            }
            
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
            // ============================================
            // LOGIN FAILED - INCREMENT ATTEMPTS
            // ============================================
            $attempts = (int)($row['login_attempts'] ?? 0) + 1;
            $remaining = 5 - $attempts;
            
            // LOG: Failed Login Attempt
            logAuditEvent($conn, $row['id'], 'LOGIN_FAILED', 'AUTHENTICATION', 
                "Failed login attempt for username: $username", 
                "Attempt $attempts of 5 | Remaining attempts: $remaining | IP: $ip_address", 'users', $row['id']);
            
            if ($attempts >= 5) {
                $lock_until = date('Y-m-d H:i:s', time() + (15 * 60));
                $conn->query("UPDATE users SET login_attempts = $attempts, locked_until = '$lock_until' WHERE id = {$row['id']}");
                $error = "❌ Too many failed attempts. Account locked for 15 minutes.";
                
                // LOG: Account Locked
                logAuditEvent($conn, $row['id'], 'ACCOUNT_LOCKED', 'SECURITY', 
                    "Account locked after $attempts failed login attempts", 
                    "Locked until: $lock_until | User: $username | IP: $ip_address", 'users', $row['id']);
            } else {
                $conn->query("UPDATE users SET login_attempts = $attempts WHERE id = {$row['id']}");
                $error = "❌ Invalid password. {$remaining} attempt(s) remaining.";
            }
        }
    } else {
        // ============================================
        // USER NOT FOUND
        // ============================================
        $error = '❌ Username or email not found.';
        
        // LOG: User Not Found Attempt
        logAuditEvent($conn, 0, 'LOGIN_USER_NOT_FOUND', 'AUTHENTICATION', 
            "Failed login attempt - username/email not found: $username", 
            "IP: $ip_address | User Agent: $user_agent");
    }
    $stmt->close();
}

// ============================================
// CHECK FOR EXPIRED LOCKS ON EVERY PAGE LOAD
// ============================================
$expired_locks = $conn->query("SELECT id, username, locked_until FROM users WHERE locked_until IS NOT NULL AND locked_until < NOW()");
if ($expired_locks && $expired_locks->num_rows > 0) {
    while ($locked_user = $expired_locks->fetch_assoc()) {
        // LOG: Lock Expired (will be cleared by the query below)
        logAuditEvent($conn, $locked_user['id'], 'LOCK_EXPIRED', 'SECURITY', 
            "Account lock expired automatically", 
            "User: {$locked_user['username']} | Locked until was: {$locked_user['locked_until']}", 'users', $locked_user['id']);
    }
}
$conn->query("UPDATE users SET login_attempts = 0, locked_until = NULL WHERE locked_until IS NOT NULL AND locked_until < NOW()");

$remembered_username = isset($_COOKIE['remember_username']) ? $_COOKIE['remember_username'] : '';

// Debug: Check if audit trail has any records
$audit_check = $conn->query("SELECT COUNT(*) as total FROM audit_trail");
if ($audit_check) {
    $total = $audit_check->fetch_assoc()['total'];
    error_log("Audit trail total records: $total");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Keep your existing head section - unchanged -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - IMS | Rodriguez Medical Center</title>
    <link rel="icon" type="image/png" href="<?php echo SITE_URL; ?>/assets/icons/armmc.png">
    <link rel="apple-touch-icon" href="<?php echo SITE_URL; ?>/assets/icons/armmc.png">
    <link rel="shortcut icon" href="<?php echo SITE_URL; ?>/assets/icons/armmc.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Keep your existing styles - they are fine -->
    <style>
        /* Keep all your existing styles - they are the same */
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
        .modal-input { width: 100%; padding: 12px 15px; border: 1px solid #E0E0E0; border-radius: 10px; font-size: 14px; margin-bottom: 15px; transition: all 0.3s; }
        .modal-input:focus { outline: none; border-color: #6B8CFF; }
        .modal-btn { background: linear-gradient(135deg, #6B8CFF 0%, #8FB5FF 100%); color: white; border: none; padding: 12px 30px; border-radius: 40px; font-size: 14px; font-weight: 600; cursor: pointer; width: 100%; }
        .modal-btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(107,140,255,0.3); }
        .modal-btn-secondary { background: #f5f5f5; color: #3A3A3A; margin-top: 10px; }
        .modal-btn-secondary:hover { background: #E0E0E0; transform: translateY(-2px); }
        .modal-divider { display: flex; align-items: center; margin: 20px 0; color: #9E9E9E; font-size: 12px; }
        .modal-divider::before, .modal-divider::after { content: ""; flex: 1; height: 1px; background: #E0E0E0; }
        .modal-divider span { padding: 0 10px; }
        
        .modal-password-field {
            position: relative;
            margin-bottom: 15px;
        }
        .modal-password-field input {
            width: 100%;
            padding: 12px 45px 12px 15px;
            border: 1px solid #E0E0E0;
            border-radius: 10px;
            font-size: 14px;
            transition: all 0.3s;
            box-sizing: border-box;
        }
        .modal-password-field input:focus {
            outline: none;
            border-color: #6B8CFF;
        }
        .modal-password-field .toggle-password {
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
        .modal-password-field .toggle-password:hover {
            color: #6B8CFF;
        }
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
            
            <div class="remember-me">
                <label><input type="checkbox" name="remember" id="rememberCheckbox"> Remember me</label>
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
            <?php if ($reset_success): ?>
                <div class="puzzle-message success" style="margin-top: 15px;"><?php echo $reset_success; ?></div>
            <?php endif; ?>
            <?php if ($reset_error): ?>
                <div class="puzzle-message error" style="margin-top: 15px;"><?php echo $reset_error; ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Reset OTP Modal with Show Password Toggle -->
    <div id="resetOTPModal" class="modal-overlay">
        <div class="modal-content">
            <span class="modal-close-btn" onclick="closeResetOTPModal()">&times;</span>
            <div class="modal-icon info"><i class="fas fa-lock"></i></div>
            <h3>Enter OTP & New Password</h3>
            <p>Enter the 6-digit OTP from your email and your new password.</p>
            <form id="resetPasswordForm" method="POST" action="">
                <input type="text" name="otp_code" class="modal-input" placeholder="6-digit OTP" maxlength="6" required>
                
                <div class="modal-password-field">
                    <input type="password" name="new_password" id="resetNewPassword" class="modal-input" placeholder="New Password" required style="padding-right: 45px;">
                    <i class="fas fa-eye toggle-password" onclick="toggleResetPassword('resetNewPassword')" title="Show/Hide Password"></i>
                </div>
                
                <div class="modal-password-field">
                    <input type="password" name="confirm_password" id="resetConfirmPassword" class="modal-input" placeholder="Confirm New Password" required style="padding-right: 45px;">
                    <i class="fas fa-eye toggle-password" onclick="toggleResetPassword('resetConfirmPassword')" title="Show/Hide Password"></i>
                </div>
                
                <input type="hidden" name="verify_otp" value="1">
                <button type="submit" class="modal-btn">Reset Password</button>
            </form>
            <?php if ($reset_success_msg): ?>
                <div class="puzzle-message success" style="margin-top: 15px;"><?php echo $reset_success_msg; ?></div>
            <?php endif; ?>
            <?php if ($reset_error_msg): ?>
                <div class="puzzle-message error" style="margin-top: 15px;"><?php echo $reset_error_msg; ?></div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
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
        
        function togglePassword() {
            let pwd = document.getElementById('password'), icon = document.querySelector('.password-field .toggle-password');
            if (pwd.type === 'password') { pwd.type = 'text'; icon.classList.remove('fa-eye'); icon.classList.add('fa-eye-slash'); }
            else { pwd.type = 'password'; icon.classList.remove('fa-eye-slash'); icon.classList.add('fa-eye'); }
        }
        
        function toggleResetPassword(fieldId) {
            let pwd = document.getElementById(fieldId);
            let icon = pwd.parentElement.querySelector('.toggle-password');
            if (pwd.type === 'password') { 
                pwd.type = 'text'; 
                icon.classList.remove('fa-eye'); 
                icon.classList.add('fa-eye-slash'); 
                icon.title = 'Hide Password';
            } else { 
                pwd.type = 'password'; 
                icon.classList.remove('fa-eye-slash'); 
                icon.classList.add('fa-eye'); 
                icon.title = 'Show Password';
            }
        }
        
        function fillCredentials(u, p, e) {
            document.getElementById('username').value = u; document.getElementById('password').value = p;
            let uf = document.getElementById('username'), pf = document.getElementById('password');
            uf.style.borderColor = '#6B8CFF'; pf.style.borderColor = '#6B8CFF';
            document.querySelectorAll('.demo-item').forEach(i => i.style.background = '');
            if (e && e.currentTarget) e.currentTarget.style.background = '#F8B0C0';
            setTimeout(() => { uf.style.borderColor = '#E0E0E0'; pf.style.borderColor = '#E0E0E0'; }, 1500);
        }
        
        document.getElementById('loginForm')?.addEventListener('submit', function(e) {
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