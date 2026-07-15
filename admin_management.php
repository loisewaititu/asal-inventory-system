<?php
session_start();
require_once 'connect.php'; // DB connection
require_once 'includes/audit_logger.php'; // audit logging

$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

// ✅ Prevent cached pages from being accessed after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ✅ Auto logout after 1 hour
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: loginpage.php?message=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();

// ✅ Redirect if not logged in
if (!isset($_SESSION['email']) || !isset($_SESSION['role'])) {
    header("Location: loginpage.php?error=unauthorized");
    exit();
}
// Only admins allowed
if ($_SESSION['role'] !== 'admin') {
    echo "Access denied. You are not an admin.";
    exit();
}

// Only super admins allowed
$email = $_SESSION['email'];
$check = $conn->query("SELECT * FROM register WHERE email='$email' AND is_super_admin = 1");

if ($check->num_rows !== 1) {
    echo "Access denied. You are not a super admin.";
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Management - J.L Tracking System</title>
    <link rel="stylesheet" href="management.css">
</head>
<body>

<h2>User Management</h2>

<table>
    <tr>
        <th>ID</th>
        <th>Full Name</th>
        <th>Email</th>
        <th>Role</th>
        <th>Super Admin</th>
        <th>Actions</th>
    </tr>

    <?php
    $result = $conn->query("SELECT * FROM register ORDER BY role='admin' DESC, id ASC");

    while ($row = $result->fetch_assoc()) {
        $user_id = $row['id'];
        $full_name = htmlspecialchars($row['First_Names'] . ' ' . $row['Last_Name']);
        $email = htmlspecialchars($row['email']);
        $role = ucfirst($row['role']);
        $is_super_admin = $row['is_super_admin'] ? "<span class='yes'>Yes</span>" : "<span class='no'>No</span>";

        echo "<tr>
            <td>$user_id</td>
            <td>$full_name</td>
            <td>$email</td>
            <td>$role</td>
            <td>$is_super_admin</td>
            <td>";

        // Actions only for non-super admins
        if (!$row['is_super_admin']) {
            if ($row['role'] === 'admin') {
                echo "
                    <form method='POST' action='remove_admin.php' style='display:inline;'>
                        <input type='hidden' name='id' value='$user_id'>
                        <button type='submit' class='btn'>Remove Admin</button>
                    </form>
                    <form method='POST' action='deactivate_user.php' style='display:inline; margin-left:5px;' onsubmit=\"return confirm('Are you sure you want to deactivate this admin?');\">
                        <input type='hidden' name='id' value='$user_id'>
                        <button type='submit' class='btn btn-deactivate'>Deactivate</button>
                    </form>
                ";
            }

            if ($row['role'] === 'user') {
                echo "
                    <form method='POST' action='promote_admin.php' style='display:inline;'>
                        <input type='hidden' name='id' value='$user_id'>
                        <button type='submit' class='btn'>Make Admin</button>
                    </form>
                    <form method='POST' action='delete_user.php' style='display:inline; margin-left:5px;' onsubmit=\"return confirm('Are you sure you want to deactivate this user?');\">
                        <input type='hidden' name='id' value='$user_id'>
                        <button type='submit' class='btn btn-deactivate'>Deactivate</button>
                    </form>
                ";
            }
        }

        echo "</td></tr>";
    }
    ?>
</table>

<div style="text-align: center; margin: 40px 0;">
    <a href="admin_dashboard.php" style="
        display: inline-block;
        background-color:rgb(73, 12, 12);
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
    ">
        ← Back to Dashboard
    </a>
</div>

</body>
</html>
