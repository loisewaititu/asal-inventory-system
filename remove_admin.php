<?php
session_start();
include 'connect.php';

session_start();
require_once 'connect.php'; // your DB connection file
require_once 'includes/audit_logger.php';

$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

if (!isset($_SESSION['email'])) exit();

$email = $_SESSION['email'];
$check = $conn->query("SELECT * FROM register WHERE email='$email' AND is_super_admin=1");
if ($check->num_rows !== 1) exit("Unauthorized");

$id = $_POST['id'];
$conn->query("UPDATE register SET role='user', is_super_admin=0 WHERE id=$id");
header("Location: admin_management.php");
?>
