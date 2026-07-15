<?php
session_start();
require_once 'connect.php'; // DB connection
require_once 'includes/audit_logger.php'; // Audit trail

// ✅ Prevent cached access after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

// ✅ Auto logout after 1 hour of inactivity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: loginpage.php?message=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();

// ✅ Check if logged in and is an admin
if (!isset($_SESSION['email']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: loginpage.php?error=unauthorized");
    exit();
}

$email = $_SESSION['email'];
$check = $conn->query("SELECT * FROM register WHERE email='$email' AND is_super_admin = 1");

// ✅ Must be exactly one super admin
if ($check->num_rows !== 1) {
    echo "Access denied. Not a super admin.";
    exit();
}

// Handle deletion request
if (isset($_POST['id'])) {
    $id = intval($_POST['id']);

    // Get the user being deleted
    $result = $conn->query("SELECT * FROM register WHERE id = $id LIMIT 1");
    if (!$result || $result->num_rows === 0) {
        echo "User not found.";
        exit();
    }

    $target = $result->fetch_assoc();

    // Prevent deleting other super admins
    if ($target['is_super_admin']) {
        echo "❌ You cannot delete another super admin.";
        exit();
    }

    // Archive the user before deletion
    $stmt = $conn->prepare("
        INSERT INTO deleted_users_archive (id, First_Names, Last_Name, email, role, is_super_admin)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "issssi",
        $target['id'],
        $target['First_Names'],
        $target['Last_Name'],
        $target['email'],
        $target['role'],
        $target['is_super_admin']
    );
    $stmt->execute();

    // Delete the user from register
    $conn->query("DELETE FROM register WHERE id = $id");

    // Log deletion
    $full_name = $target['First_Names'] . ' ' . $target['Last_Name'];
    log_activity(
        $current_admin_id,
        $current_admin_name,
        'USER_DELETED',
        "Deleted user: $full_name ({$target['email']})",
        'user',
        $id,
        $target,
        null
    );

    header("Location: admin_management.php?delete=success");
    exit();
} else {
    echo "Invalid request.";
}
?>
