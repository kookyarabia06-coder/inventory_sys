<?php
/**
 * SMTP Email Sender using PHPMailer
 */

// Load config first - this is critical
require_once __DIR__ . '/../config.php';

// Check if PHPMailer exists
$phpmailer_path = __DIR__ . '/../PHPMailer/PHPMailer.php';
if (!file_exists($phpmailer_path)) {
    // Try alternative path
    $phpmailer_path = __DIR__ . '/../PHPMailer/src/PHPMailer.php';
}

if (!file_exists($phpmailer_path)) {
    error_log("PHPMailer not found. Please install PHPMailer in the PHPMailer folder.");
    // Fallback to simple mail function
    function sendSMTPEmail($to, $subject, $html_message, $plain_message = '') {
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . NOREPLY_EMAIL . "\r\n";
        return mail($to, $subject, $html_message, $headers);
    }
    
    function sendApprovalEmail($user) {
        $site_name = SITE_NAME;
        $subject = "Account Approved - Welcome to " . $site_name;
        $message = "<h2>Welcome!</h2><p>Your account has been approved.</p>";
        return sendSMTPEmail($user['email'], $subject, $message);
    }
    
    function sendRejectionEmail($user) {
        $site_name = SITE_NAME;
        $subject = "Account Registration Update - " . $site_name;
        $message = "<h2>Account Update</h2><p>Your account has been rejected.</p>";
        return sendSMTPEmail($user['email'], $subject, $message);
    }
    
    function sendForgotPasswordOTP($email, $otp, $username) {
        global $SITE_NAME;
        $subject = "Password Reset OTP - " . $SITE_NAME;
        $message = "<h2>Password Reset OTP</h2>
                    <p>Hello <strong>$username</strong>,</p>
                    <p>Your One-Time Password (OTP) for password reset is:</p>
                    <h1 style='font-size: 32px; letter-spacing: 5px;'>$otp</h1>
                    <p>This OTP expires in 10 minutes.</p>
                    <p>If you did not request this, please ignore this email.</p>";
        return sendSMTPEmail($email, $subject, $message);
    }
    
    function testEmailConfig() {
        return sendSMTPEmail(SUPPORT_EMAIL, "Test", "<h2>Test</h2>");
    }
    
    return;
}

require_once __DIR__ . '/../PHPMailer/PHPMailer.php';
require_once __DIR__ . '/../PHPMailer/SMTP.php';
require_once __DIR__ . '/../PHPMailer/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Get constants with fallback values
$SMTP_HOST = defined('SMTP_HOST') ? SMTP_HOST : 'smtp.gmail.com';
$SMTP_PORT = defined('SMTP_PORT') ? SMTP_PORT : 587;
$SMTP_USER = defined('SMTP_USER') ? SMTP_USER : 'veripoolresort@gmail.com';
$SMTP_PASS = defined('SMTP_PASS_CLEAN') ? SMTP_PASS_CLEAN : 'vxoxgejvdrubhwpz';
$NOREPLY_EMAIL = defined('NOREPLY_EMAIL') ? NOREPLY_EMAIL : $SMTP_USER;
$SUPPORT_EMAIL = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : $SMTP_USER;
$SITE_NAME = defined('SITE_NAME') ? SITE_NAME : 'IMS';
$SITE_URL = defined('SITE_URL') ? SITE_URL : 'http://localhost/inventory_sys';

/**
 * Send email using SMTP
 */
function sendSMTPEmail($to, $subject, $html_message, $plain_message = '') {
    global $SMTP_HOST, $SMTP_PORT, $SMTP_USER, $SMTP_PASS, $NOREPLY_EMAIL, $SITE_NAME, $SUPPORT_EMAIL;
    
    try {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host       = $SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $SMTP_USER;
        $mail->Password   = $SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = $SMTP_PORT;
        
        // Timeout settings
        $mail->Timeout = 30;
        
        // Sender and recipient
        $mail->setFrom($NOREPLY_EMAIL, $SITE_NAME);
        $mail->addAddress($to);
        $mail->addReplyTo($SUPPORT_EMAIL, $SITE_NAME);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html_message;
        $mail->AltBody = $plain_message ?: strip_tags($html_message);
        
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email could not be sent. Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Generate a random 6-digit OTP
 */
function generateOTP() {
    return sprintf("%06d", mt_rand(1, 999999));
}

/**
 * Send approval email to user - With brand colors
 */
function sendApprovalEmail($user) {
    global $SITE_NAME, $SITE_URL;
    
    $subject = "Account Approved - Welcome to " . $SITE_NAME;
    
    // HTML Email Template with brand colors
    $html_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Account Approved</title>
        <style>
            :root {
                --primary: #6B8CFF;
                --secondary: #8FB5FF;
                --accent: #F8B0C0;
                --accent-light: #FFD8E0;
                --success-light: #C5E8C5;
                --light: #F0F0F0;
                --white: #FFFFFF;
                --border-light: #E0E0E0;
                --text-primary: #3A3A3A;
                --text-secondary: #6B6B6B;
                --text-muted: #9E9E9E;
                --text-light: #FFFFFF;
                --success: #4CAF50;
                --danger: #f44336;
                --warning: #FFB74D;
                --info: #8FB5FF;
            }
            
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                line-height: 1.6;
                color: var(--text-primary);
                margin: 0;
                padding: 0;
                background-color: var(--light);
            }
            
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: var(--white);
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
            }
            
            .header {
                background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
                color: var(--text-light);
                padding: 30px;
                text-align: center;
            }
            
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 600;
            }
            
            .header p {
                margin: 10px 0 0;
                opacity: 0.9;
                font-size: 14px;
            }
            
            .content {
                padding: 30px;
                background: var(--white);
            }
            
            .greeting {
                font-size: 18px;
                margin-bottom: 20px;
                color: var(--text-primary);
            }
            
            .message {
                margin-bottom: 25px;
                color: var(--text-secondary);
            }
            
            .credentials {
                background: var(--light);
                padding: 20px;
                border-left: 4px solid var(--primary);
                margin: 25px 0;
                border-radius: 8px;
            }
            
            .credentials p {
                margin: 8px 0;
                color: var(--text-primary);
            }
            
            .credentials strong {
                color: var(--primary);
            }
            
            .button {
                display: inline-block;
                padding: 12px 30px;
                background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
                color: var(--text-light);
                text-decoration: none;
                border-radius: 8px;
                margin: 20px 0;
                font-weight: 500;
                transition: all 0.3s ease;
            }
            
            .button:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(107, 140, 255, 0.3);
            }
            
            .security-tips {
                background: var(--accent-light);
                padding: 20px;
                border-radius: 8px;
                margin: 25px 0;
                border-left: 4px solid var(--accent);
            }
            
            .security-tips h3 {
                margin-top: 0;
                color: var(--text-primary);
                font-size: 16px;
            }
            
            .security-tips ul {
                margin: 10px 0 0;
                padding-left: 20px;
                color: var(--text-secondary);
            }
            
            .security-tips li {
                margin: 8px 0;
            }
            
            .footer {
                background: var(--light);
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: var(--text-muted);
                border-top: 1px solid var(--border-light);
            }
            
            .footer a {
                color: var(--primary);
                text-decoration: none;
            }
            
            .footer a:hover {
                text-decoration: underline;
            }
            
            .highlight {
                color: var(--primary);
                font-weight: 600;
            }
            
            @media (max-width: 480px) {
                .container {
                    margin: 10px;
                    border-radius: 12px;
                }
                .header {
                    padding: 20px;
                }
                .content {
                    padding: 20px;
                }
                .button {
                    padding: 10px 20px;
                    font-size: 14px;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome Aboard</h1>
                <p>Your account has been approved</p>
            </div>
            <div class='content'>
                <div class='greeting'>
                    Dear <strong>" . htmlspecialchars($user['firstname']) . " " . htmlspecialchars($user['lastname']) . "</strong>,
                </div>
                
                <div class='message'>
                    Great news! Your account registration has been <span class='highlight'>APPROVED</span> by the administrator.
                </div>
                
                <div class='credentials'>
                    <p><strong>Your Login Credentials:</strong></p>
                    <p><strong>Email:</strong> " . htmlspecialchars($user['email']) . "</p>
                    <p><strong>Username:</strong> " . htmlspecialchars($user['username']) . "</p>
                    <p><strong>Role:</strong> " . ucfirst(htmlspecialchars($user['role'])) . "</p>
                </div>
                
                <p>You can now access the system using your credentials:</p>
                <div style='text-align: center;'>
                    <a href='{$SITE_URL}/login.php' class='button'>Login to Your Account</a>
                </div>
                
                <div class='security-tips'>
                    <h3>Security Tips</h3>
                    <ul>
                        <li>Change your password after first login</li>
                        <li>Never share your login credentials with anyone</li>
                        <li>Contact support if you notice any suspicious activity</li>
                        <li>Always log out after each session</li>
                    </ul>
                </div>
                
                <p>We're excited to have you with us!</p>
                
                <p>Best regards,<br>
                <strong>{$SITE_NAME} Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>Need help? Contact us at: <a href='mailto:" . SUPPORT_EMAIL . "'>" . SUPPORT_EMAIL . "</a></p>
                <p>&copy; " . date('Y') . " {$SITE_NAME}. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $plain_message = "Welcome to {$SITE_NAME}!\n\n";
    $plain_message .= "Dear " . $user['firstname'] . " " . $user['lastname'] . ",\n\n";
    $plain_message .= "Great news! Your account has been APPROVED by the administrator.\n\n";
    $plain_message .= "Your Login Credentials:\n";
    $plain_message .= "Email: " . $user['email'] . "\n";
    $plain_message .= "Username: " . $user['username'] . "\n";
    $plain_message .= "Role: " . ucfirst($user['role']) . "\n\n";
    $plain_message .= "Login URL: {$SITE_URL}/login.php\n\n";
    $plain_message .= "Security Tips:\n";
    $plain_message .= "- Change your password after first login\n";
    $plain_message .= "- Never share your login credentials with anyone\n";
    $plain_message .= "- Contact support if you notice any suspicious activity\n\n";
    $plain_message .= "We're excited to have you with us!\n\n";
    $plain_message .= "Best regards,\n{$SITE_NAME} Team\n\n";
    $plain_message .= "---\n";
    $plain_message .= "This is an automated message. Need help? Contact: " . SUPPORT_EMAIL . "\n";
    
    return sendSMTPEmail($user['email'], $subject, $html_message, $plain_message);
}

/**
 * Send rejection email to user - With brand colors
 */
function sendRejectionEmail($user) {
    global $SITE_NAME;
    
    $subject = "Account Registration Update - " . $SITE_NAME;
    
    $html_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Account Update</title>
        <style>
            :root {
                --primary: #6B8CFF;
                --secondary: #8FB5FF;
                --accent: #F8B0C0;
                --accent-light: #FFD8E0;
                --success-light: #C5E8C5;
                --light: #F0F0F0;
                --white: #FFFFFF;
                --border-light: #E0E0E0;
                --text-primary: #3A3A3A;
                --text-secondary: #6B6B6B;
                --text-muted: #9E9E9E;
                --text-light: #FFFFFF;
                --success: #4CAF50;
                --danger: #f44336;
                --warning: #FFB74D;
                --info: #8FB5FF;
            }
            
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                line-height: 1.6;
                color: var(--text-primary);
                margin: 0;
                padding: 0;
                background-color: var(--light);
            }
            
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: var(--white);
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
            }
            
            .header {
                background: linear-gradient(135deg, var(--danger) 0%, #d32f2f 100%);
                color: var(--text-light);
                padding: 30px;
                text-align: center;
            }
            
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 600;
            }
            
            .header p {
                margin: 10px 0 0;
                opacity: 0.9;
                font-size: 14px;
            }
            
            .content {
                padding: 30px;
                background: var(--white);
            }
            
            .greeting {
                font-size: 18px;
                margin-bottom: 20px;
                color: var(--text-primary);
            }
            
            .info-box {
                background: #FFF3E0;
                padding: 20px;
                border-left: 4px solid var(--warning);
                margin: 25px 0;
                border-radius: 8px;
            }
            
            .info-box p {
                margin: 0;
                color: #E65100;
            }
            
            .reasons {
                margin: 25px 0;
                padding-left: 20px;
                color: var(--text-secondary);
            }
            
            .reasons li {
                margin: 8px 0;
            }
            
            .footer {
                background: var(--light);
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: var(--text-muted);
                border-top: 1px solid var(--border-light);
            }
            
            .footer a {
                color: var(--primary);
                text-decoration: none;
            }
            
            .footer a:hover {
                text-decoration: underline;
            }
            
            @media (max-width: 480px) {
                .container {
                    margin: 10px;
                    border-radius: 12px;
                }
                .header {
                    padding: 20px;
                }
                .content {
                    padding: 20px;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Account Update</h1>
                <p>Regarding your registration</p>
            </div>
            <div class='content'>
                <div class='greeting'>
                    Dear <strong>" . htmlspecialchars($user['firstname']) . " " . htmlspecialchars($user['lastname']) . "</strong>,
                </div>
                
                <p>Thank you for your interest in <strong>{$SITE_NAME}</strong>.</p>
                
                <div class='info-box'>
                    <p>After careful review, we regret to inform you that your account registration has been <strong>REJECTED</strong>.</p>
                </div>
                
                <p><strong>Possible reasons for rejection:</strong></p>
                <ul class='reasons'>
                    <li>Incomplete or invalid information provided</li>
                    <li>Not meeting the registration criteria</li>
                    <li>Duplicate account detected</li>
                    <li>Failed verification checks</li>
                </ul>
                
                <p>If you believe this is an error or would like to reapply, please contact our support team.</p>
                
                <p>We appreciate your understanding.</p>
                
                <p>Best regards,<br>
                <strong>{$SITE_NAME} Team</strong></p>
            </div>
            <div class='footer'>
                <p>Need assistance? Contact: <a href='mailto:" . SUPPORT_EMAIL . "'>" . SUPPORT_EMAIL . "</a></p>
                <p>&copy; " . date('Y') . " {$SITE_NAME}. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $plain_message = "Account Registration Update - {$SITE_NAME}\n\n";
    $plain_message .= "Dear " . $user['firstname'] . " " . $user['lastname'] . ",\n\n";
    $plain_message .= "Thank you for your interest in {$SITE_NAME}.\n\n";
    $plain_message .= "After careful review, we regret to inform you that your account registration has been REJECTED.\n\n";
    $plain_message .= "Possible reasons for rejection:\n";
    $plain_message .= "- Incomplete or invalid information provided\n";
    $plain_message .= "- Not meeting the registration criteria\n";
    $plain_message .= "- Duplicate account detected\n";
    $plain_message .= "- Failed verification checks\n\n";
    $plain_message .= "If you believe this is an error, please contact our support team.\n\n";
    $plain_message .= "We appreciate your understanding.\n\n";
    $plain_message .= "Best regards,\n{$SITE_NAME} Team\n\n";
    $plain_message .= "Need help? Contact: " . SUPPORT_EMAIL . "\n";
    
    return sendSMTPEmail($user['email'], $subject, $html_message, $plain_message);
}

/**
 * Send forgot password OTP email (6-digit code instead of token)
 */
function sendForgotPasswordOTP($email, $otp, $username) {
    global $SITE_NAME, $SITE_URL, $SUPPORT_EMAIL;
    
    $subject = "Password Reset OTP - " . $SITE_NAME;
    
    // HTML Email Template for OTP
    $html_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Password Reset OTP</title>
        <style>
            :root {
                --primary: #6B8CFF;
                --secondary: #8FB5FF;
                --accent: #F8B0C0;
                --accent-light: #FFD8E0;
                --light: #F0F0F0;
                --white: #FFFFFF;
                --border-light: #E0E0E0;
                --text-primary: #3A3A3A;
                --text-secondary: #6B6B6B;
                --text-muted: #9E9E9E;
                --text-light: #FFFFFF;
                --success: #4CAF50;
                --warning: #FF9800;
                --info: #2196F3;
            }
            
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                line-height: 1.6;
                color: var(--text-primary);
                margin: 0;
                padding: 0;
                background-color: var(--light);
            }
            
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: var(--white);
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
            }
            
            .header {
                background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
                color: var(--text-light);
                padding: 30px;
                text-align: center;
            }
            
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 600;
            }
            
            .header p {
                margin: 10px 0 0;
                opacity: 0.9;
                font-size: 14px;
            }
            
            .content {
                padding: 30px;
                background: var(--white);
            }
            
            .greeting {
                font-size: 18px;
                margin-bottom: 20px;
                color: var(--text-primary);
            }
            
            .message {
                margin-bottom: 25px;
                color: var(--text-secondary);
            }
            
            .otp-box {
                background: linear-gradient(135deg, #f0f4ff 0%, #e8edff 100%);
                padding: 30px;
                margin: 25px 0;
                border-radius: 16px;
                text-align: center;
                border: 2px dashed var(--primary);
            }
            
            .otp-code {
                font-size: 48px;
                font-weight: 800;
                letter-spacing: 10px;
                color: var(--primary);
                font-family: 'Courier New', monospace;
                background: white;
                padding: 15px 20px;
                border-radius: 12px;
                display: inline-block;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .warning-box {
                background: #FFF3E0;
                padding: 15px;
                border-left: 4px solid var(--warning);
                margin: 25px 0;
                border-radius: 8px;
                font-size: 13px;
            }
            
            .timer-text {
                color: var(--warning);
                font-weight: 600;
            }
            
            .footer {
                background: var(--light);
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: var(--text-muted);
                border-top: 1px solid var(--border-light);
            }
            
            .footer a {
                color: var(--primary);
                text-decoration: none;
            }
            
            .footer a:hover {
                text-decoration: underline;
            }
            
            @media (max-width: 480px) {
                .container {
                    margin: 10px;
                    border-radius: 12px;
                }
                .header {
                    padding: 20px;
                }
                .content {
                    padding: 20px;
                }
                .otp-code {
                    font-size: 32px;
                    letter-spacing: 5px;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Password Reset OTP</h1>
                <p>Your One-Time Password for password reset</p>
            </div>
            <div class='content'>
                <div class='greeting'>
                    Dear <strong>" . htmlspecialchars($username) . "</strong>,
                </div>
                
                <div class='message'>
                    We received a request to reset the password for your account. Use the OTP below to verify your identity:
                </div>
                
                <div class='otp-box'>
                    <div class='otp-code'>" . $otp . "</div>
                    <p style='margin-top: 15px; font-size: 14px;'>Enter this code in the password reset form</p>
                </div>
                
                <div class='warning-box'>
                    <strong>⚠️ Important Security Information:</strong>
                    <ul style='margin: 10px 0 0 20px;'>
                        <li>This OTP is valid for <span class='timer-text'>10 minutes</span> only</li>
                        <li>Never share this OTP with anyone</li>
                        <li>If you didn't request this, please ignore this email</li>
                        <li>For security, do not forward this email</li>
                    </ul>
                </div>
                
                <p>After verifying with this OTP, you will be able to set a new password for your account.</p>
                
                <p>Best regards,<br>
                <strong>{$SITE_NAME} Security Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated message. Please do not reply to this email.</p>
                <p>Need help? Contact us at: <a href='mailto:" . SUPPORT_EMAIL . "'>" . SUPPORT_EMAIL . "</a></p>
                <p>&copy; " . date('Y') . " {$SITE_NAME}. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $plain_message = "PASSWORD RESET OTP - {$SITE_NAME}\n\n";
    $plain_message .= "Dear " . htmlspecialchars($username) . ",\n\n";
    $plain_message .= "We received a request to reset your password.\n\n";
    $plain_message .= "Your One-Time Password (OTP) is: " . $otp . "\n\n";
    $plain_message .= "This OTP is valid for 10 minutes.\n\n";
    $plain_message .= "Important Security Information:\n";
    $plain_message .= "- Never share this OTP with anyone\n";
    $plain_message .= "- If you didn't request this, please ignore this email\n";
    $plain_message .= "- For security, do not forward this email\n\n";
    $plain_message .= "After verifying with this OTP, you will be able to set a new password.\n\n";
    $plain_message .= "Best regards,\n{$SITE_NAME} Security Team\n\n";
    $plain_message .= "Need help? Contact: " . SUPPORT_EMAIL . "\n";
    
    return sendSMTPEmail($email, $subject, $html_message, $plain_message);
}

/**
 * Send password reset email with token link
 */
function sendForgotPasswordEmail($email, $token, $username) {
    global $SITE_NAME, $SITE_URL, $SUPPORT_EMAIL;
    
    $subject = "Password Reset Request - " . $SITE_NAME;
    $reset_link = $SITE_URL . "/reset_password.php?token=" . $token;
    
    // HTML Email Template for password reset
    $html_message = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Password Reset Request</title>
        <style>
            :root {
                --primary: #6B8CFF;
                --secondary: #8FB5FF;
                --accent: #F8B0C0;
                --accent-light: #FFD8E0;
                --light: #F0F0F0;
                --white: #FFFFFF;
                --border-light: #E0E0E0;
                --text-primary: #3A3A3A;
                --text-secondary: #6B6B6B;
                --text-muted: #9E9E9E;
                --text-light: #FFFFFF;
                --success: #4CAF50;
                --warning: #FF9800;
            }
            
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
                line-height: 1.6;
                color: var(--text-primary);
                margin: 0;
                padding: 0;
                background-color: var(--light);
            }
            
            .container {
                max-width: 600px;
                margin: 20px auto;
                background: var(--white);
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
            }
            
            .header {
                background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
                color: var(--text-light);
                padding: 30px;
                text-align: center;
            }
            
            .header h1 {
                margin: 0;
                font-size: 28px;
                font-weight: 600;
            }
            
            .header p {
                margin: 10px 0 0;
                opacity: 0.9;
                font-size: 14px;
            }
            
            .content {
                padding: 30px;
                background: var(--white);
            }
            
            .greeting {
                font-size: 16px;
                margin-bottom: 20px;
                color: var(--text-primary);
            }
            
            .button {
                display: inline-block;
                padding: 12px 30px;
                background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
                color: var(--text-light);
                text-decoration: none;
                border-radius: 8px;
                font-weight: 600;
                margin: 20px 0;
            }
            
            .button:hover {
                opacity: 0.9;
            }
            
            .link-container {
                background-color: var(--light);
                padding: 15px;
                border-radius: 8px;
                margin: 20px 0;
                word-break: break-all;
            }
            
            .link-container a {
                color: var(--primary);
                text-decoration: none;
            }
            
            .link-container a:hover {
                text-decoration: underline;
            }
            
            .warning-box {
                background: var(--accent-light);
                padding: 15px;
                border-radius: 8px;
                border-left: 4px solid var(--accent);
                margin: 20px 0;
                color: var(--text-secondary);
                font-size: 14px;
            }
            
            .footer {
                background: var(--light);
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: var(--text-muted);
                border-top: 1px solid var(--border-light);
            }
            
            .footer a {
                color: var(--primary);
                text-decoration: none;
            }
            
            @media (max-width: 480px) {
                .container {
                    margin: 10px;
                    border-radius: 12px;
                }
                .header {
                    padding: 20px;
                }
                .content {
                    padding: 20px;
                }
                .button {
                    display: block;
                    text-align: center;
                }
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Password Reset Request</h1>
                <p>Secure your account</p>
            </div>
            <div class='content'>
                <div class='greeting'>
                    Hello <strong>" . htmlspecialchars($username) . "</strong>,
                </div>
                
                <p>We received a request to reset your password. If you made this request, please click the button below to reset your password:</p>
                
                <center>
                    <a href='" . htmlspecialchars($reset_link) . "' class='button'>Reset Your Password</a>
                </center>
                
                <p>Or copy and paste this link in your browser:</p>
                <div class='link-container'>
                    <a href='" . htmlspecialchars($reset_link) . "'>" . htmlspecialchars($reset_link) . "</a>
                </div>
                
                <div class='warning-box'>
                    <strong>⏰ This link expires in 1 hour</strong><br>
                    If you did not request a password reset, please ignore this email. Your account is safe.
                </div>
                
                <p>For security reasons:</p>
                <ul>
                    <li>Never share this link with anyone</li>
                    <li>We will never ask for your password via email</li>
                    <li>Always verify the sender's email address</li>
                </ul>
                
                <p>Need help? Contact our support team at <a href='mailto:" . htmlspecialchars(SUPPORT_EMAIL) . "'>" . htmlspecialchars(SUPPORT_EMAIL) . "</a></p>
            </div>
            <div class='footer'>
                <p>© " . date('Y') . " " . htmlspecialchars($SITE_NAME) . ". All rights reserved.</p>
                <p>This is an automated email. Please do not reply to this message.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $plain_message = "Password Reset Request\n\n";
    $plain_message .= "Hello " . $username . ",\n\n";
    $plain_message .= "We received a request to reset your password. Click the link below to reset it:\n\n";
    $plain_message .= $reset_link . "\n\n";
    $plain_message .= "This link expires in 1 hour.\n\n";
    $plain_message .= "If you did not request this, please ignore this email.\n\n";
    $plain_message .= "Best regards,\n" . $SITE_NAME . " Security Team\n\n";
    $plain_message .= "Need help? Contact: " . SUPPORT_EMAIL . "\n";
    
    return sendSMTPEmail($email, $subject, $html_message, $plain_message);
}

/**
 * Test email configuration
 */
function testEmailConfig() {
    global $SUPPORT_EMAIL, $SITE_NAME;
    
    $test_html = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <title>SMTP Test</title>
        <style>
            :root {
                --primary: #6B8CFF;
                --secondary: #8FB5FF;
                --accent: #F8B0C0;
                --accent-light: #FFD8E0;
                --success-light: #C5E8C5;
                --light: #F0F0F0;
                --white: #FFFFFF;
                --border-light: #E0E0E0;
                --text-primary: #3A3A3A;
                --text-secondary: #6B6B6B;
                --text-muted: #9E9E9E;
                --text-light: #FFFFFF;
                --success: #4CAF50;
                --danger: #f44336;
                --warning: #FFB74D;
                --info: #8FB5FF;
            }
            
            body {
                font-family: 'Inter', Arial, sans-serif;
                line-height: 1.6;
                background-color: var(--light);
                margin: 0;
                padding: 20px;
            }
            .container {
                max-width: 600px;
                margin: 0 auto;
                background: var(--white);
                border-radius: 16px;
                overflow: hidden;
                box-shadow: 0 4px 12px rgba(107, 140, 255, 0.1);
            }
            .header {
                background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
                color: var(--text-light);
                padding: 30px;
                text-align: center;
            }
            .header h2 {
                margin: 0;
            }
            .content {
                background: var(--white);
                padding: 30px;
            }
            .success {
                color: var(--success);
                font-weight: bold;
            }
            .footer {
                text-align: center;
                margin-top: 20px;
                padding: 20px;
                font-size: 12px;
                color: var(--text-muted);
                border-top: 1px solid var(--border-light);
                background-color: var(--light);
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>SMTP Configuration Test</h2>
            </div>
            <div class='content'>
                <p>Dear Administrator,</p>
                <p class='success'>Your SMTP settings are working correctly!</p>
                <p>This is a test email from your <strong>{$SITE_NAME}</strong> system.</p>
                <p><strong>Time:</strong> " . date('Y-m-d H:i:s') . "</p>
                <p><strong>Server:</strong> SMTP_HOST:" . SMTP_PORT . "</p>
                <hr>
                <p>You can now send approval, rejection, and OTP reset emails to users.</p>
            </div>
            <div class='footer'>
                <p>This is an automated test message. Please do not reply.</p>
                <p>&copy; " . date('Y') . " {$SITE_NAME}. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    $test_plain = "SMTP Configuration Test\n\n";
    $test_plain .= "Your SMTP settings are working correctly!\n";
    $test_plain .= "Time: " . date('Y-m-d H:i:s') . "\n";
    $test_plain .= "This is a test email from your {$SITE_NAME} system.\n";
    $test_plain .= "You can now send approval, rejection, and OTP reset emails.\n";
    
    return sendSMTPEmail(SUPPORT_EMAIL, "SMTP Test - " . $SITE_NAME, $test_html, $test_plain);
}
?>