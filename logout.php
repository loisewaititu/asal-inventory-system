<?php
session_start();

// Include database connection and audit logger
require_once 'connect.php';
require_once 'includes/audit_logger.php';

// Capture admin info before session is destroyed
$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

// ✅ Log the logout action before destroying session
log_activity(
    $current_admin_id,
    $current_admin_name,
    'ADMIN_LOGOUT',
    'Admin logged out.',
    'admin',
    $current_admin_id
);

// Destroy session
session_unset(); // remove all session variables
session_destroy(); // destroy the session

// ✅ Prevent caching so back button won’t reload old pages
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Redirect to login page
header("Location: loginpage.php");
exit();
?>

