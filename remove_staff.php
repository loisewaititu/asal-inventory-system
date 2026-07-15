<?php
include 'connect.php';
session_start();
require_once 'includes/audit_logger.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'], $_POST['reason'])) {
    $id = $_POST['id'];
    $reason = $_POST['reason'];

    // Fetch staff
    $stmt = $conn->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $staff = $result->fetch_assoc();
    $stmt->close();

    if ($staff) {
    // Check if critical fields exist
    if (empty($staff['Personal_number'])) {
        die("Error: Missing Personal Number. Cannot archive.");
    }

    $insert = $conn->prepare("INSERT INTO removed_staff_archives 
        (First_Names, Last_Name, Personal_number, email, department, removal_reason, removal_date) 
        VALUES (?, ?, ?, ?, ?, ?, NOW())");
    $insert->bind_param(
        "ssssss",
        $staff['First_Names'],
        $staff['Last_Name'],
        $staff['Personal_number'],
        $staff['email'],
        $staff['department'],
        $reason
    );
    $insert->execute();
    $insert->close();

    // Remove from staff table
    $delete = $conn->prepare("DELETE FROM staff WHERE id = ?");
    $delete->bind_param("i", $id);
    $delete->execute();
    $delete->close();

    echo "<script>alert('Staff removed and archived.'); window.location.href='Staff.php';</script>";
}
 else {
        echo "Staff not found.";
    }
} else {
    echo "Invalid request.";
}
?>
