<?php
/**
 * Index Page - Redirect to login or dashboard
 */

require_once 'config.php';
require_once INCLUDE_PATH . '/auth.php';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . getDashboardUrl());
} else {
    header('Location: ' . SITE_URL . '/login');
}
exit();
session_start();

if (isset($_SESSION['user_id'])) {
    // Redirect based on role
    $role = $_SESSION['user_role'] ?? 'user';
    
    switch ($role) {
        case 'super_admin':
            header('Location: /superadmin/dashboard');
            break;
        case 'admin':
            header('Location: /admin/dashboard');
            break;
		case 'supply':
            header('Location: /supply/dashboard');
            break;
        default:
            header('Location: /user/dashboard');
    }
} else {
    header('Location: /login');
}
exit();
?>