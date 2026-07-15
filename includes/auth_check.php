<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../connect.php';        // DB connection
require_once __DIR__ . '/audit_logger.php';      // Audit logging

// Prevent cached access after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Current admin info
$current_admin_id   = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

// Ensure admin login
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../loginpage.php?error=unauthorized");
    exit();
}

// Auto logout after 1 hour
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: ../loginpage.php?message=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();
?>
