<?php
include 'connect.php';
session_start();
require_once 'connect.php'; // your DB connection file
require_once 'includes/audit_logger.php';

$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $equipment_id = intval($_POST['equipment_id']);
    $equipment_type = $_POST['equipment_type'];
    $new_user_id = intval($_POST['id']);
    $change_reason = trim($_POST['reason']);
    $change_date = date("Y-m-d H:i:s");

    // Validate type
    $allowed_types = ['desktop', 'mouse', 'keyboard', 'ups', 'cpu', 'scanner', 'printer', 'paper_shredder'];
    if (!in_array($equipment_type, $allowed_types)) {
        die("Invalid equipment type.");
    }

    // Get the previous owner (if any)
    $stmt = $conn->prepare("SELECT current_user_id FROM equipment_ownership WHERE equipment_id = ? AND equipment_type = ? ORDER BY change_date DESC LIMIT 1");
    $stmt->bind_param("is", $equipment_id, $equipment_type);
    $stmt->execute();
    $result = $stmt->get_result();
    $previous_user_id = ($result->num_rows > 0) ? $result->fetch_assoc()['current_user_id'] : null;
    $stmt->close();

    // Insert new ownership record
    $stmt = $conn->prepare("INSERT INTO equipment_ownership (equipment_type, equipment_id, previous_user_id, current_user_id, change_reason, change_date) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("siiiss", $equipment_type, $equipment_id, $previous_user_id, $new_user_id, $change_reason, $change_date);

    if ($stmt->execute()) {
        echo "
        <script>
            alert('✅ Owner assigned successfully!');
            window.location.href = 'admin_dashboard.php';
        </script>
        ";
    } else {
        echo "❌ Error assigning owner: " . $stmt->error;
    }

    $stmt->close();
    $conn->close();
} else {
    echo "❌ Invalid request.";
}
