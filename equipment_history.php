<?php
session_start();

if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: loginpage.php?message=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();

include 'connect.php';

// Validate input
if (!isset($_GET['type']) || !isset($_GET['id'])) {
    die("Equipment not specified.");
}

$equipment_type = $_GET['type'];
$equipment_id = intval($_GET['id']);

// Updated list of valid equipment tables
$allowed_tables = [
    'desktop', 'ups', 'scanner', 'printer', 'firewall', 'laptop',
    'network_switches', 'projector', 'router', 'tablet', 'ip_phone',
    'wireless_access_point', 'cctv'
];

if (!in_array($equipment_type, $allowed_tables)) {
    die("Invalid equipment type.");
}

// Function to get owner's full name + designation from register or staff
function getOwnerName($conn, $user_id) {
    if (!$user_id) return '-';

    // Try register table
    $stmt = $conn->prepare("SELECT First_Names, designation FROM register WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $stmt->close();
        return "{$row['First_Names']} ({$row['designation']})";
    }
    $stmt->close();

    // Try staff table
    $stmt = $conn->prepare("SELECT First_Names, designation FROM staff WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $stmt->close();
        return "{$row['First_Names']} ({$row['designation']})";
    }
    $stmt->close();

    return '-';
}

// Fetch ownership history
$query = "SELECT * FROM equipment_ownership WHERE equipment_type = ? AND equipment_id = ? ORDER BY change_date DESC";
$stmt = $conn->prepare($query);
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}
$stmt->bind_param("si", $equipment_type, $equipment_id);
$stmt->execute();
$result = $stmt->get_result();

// Fetch serial number
$serial_no = "Not Found";
$serial_stmt = $conn->prepare("SELECT serial_no FROM `$equipment_type` WHERE id = ?");
$serial_stmt->bind_param("i", $equipment_id);
$serial_stmt->execute();
$serial_result = $serial_stmt->get_result();
if ($serial_result->num_rows > 0) {
    $serial_no = $serial_result->fetch_assoc()['serial_no'];
}
$serial_stmt->close();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Equipment Ownership History</title>
    <style>
    body {
        font-family: 'Segoe UI', sans-serif;
        background: linear-gradient(to right, #D2BCA1, #D5B893);
        color: #6F4D38;
        padding: 40px;
        text-align: center;
    }
    h2 {
        color: #25344F;
        margin-bottom: 30px;
        font-size: 38px;
        letter-spacing: 1px;
    }
    table {
        width: 95%;
        margin: auto;
        border-collapse: collapse;
        background-color: white;
        box-shadow: 0 8px 30px rgba(111, 72, 28, 0.25);
        border-radius: 12px;
        overflow: hidden;
    }
    th, td {
        padding: 15px;
        border-bottom: 1px solid #D2BCA1;
        color: #6F4D38;
    }
    th {
        background-color: #632024;
        color: #D5B893;
        font-weight: bold;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }
    th:first-child { border-top-left-radius: 12px; }
    th:last-child { border-top-right-radius: 12px; }
    tr:nth-child(even) {
        background-color: rgba(210, 188, 161, 0.4);
    }
    tr:hover {
        background-color: #D5B893;
        color: #25344F;
        transition: 0.3s ease;
    }
    tr:hover td {
        border-color: #A76825;
    }
    a.button {
        padding: 14px 25px;
        background-color: #A76825;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        display: inline-block;
        margin-top: 35px;
        font-size: 17px;
        font-weight: bold;
        transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
    }
    a.button:hover {
        background-color: #6F481C;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }
    @media screen and (max-width: 768px) {
        body { padding: 20px; }
        h2 { font-size: 32px; margin-bottom: 25px; }
        table { width: 100%; }
        th, td { padding: 12px; font-size: 14px; }
        a.button { padding: 12px 20px; font-size: 16px; }
    }
    @media screen and (max-width: 480px) {
        body { padding: 15px; }
        h2 { font-size: 26px; margin-bottom: 20px; }
        th, td { padding: 10px; font-size: 12px; }
        a.button { padding: 10px 15px; font-size: 15px; }
    }
    </style>
</head>
<body>

<h2>Ownership History for <?= htmlspecialchars(ucfirst($equipment_type)) ?> (Serial: <?= htmlspecialchars($serial_no) ?>)</h2>

<?php if ($result->num_rows > 0): ?>
<table>
    <tr>
        <th>Equipment Type</th>
        <th>Serial Number</th>
        <th>Previous Owner</th>
        <th>Current Owner</th>
        <th>Reason</th>
        <th>Date Changed</th>
    </tr>
    <?php while ($row = $result->fetch_assoc()): ?>
        <tr>
            <td><?= htmlspecialchars(ucfirst($equipment_type)) ?></td>
            <td><?= htmlspecialchars($serial_no) ?></td>
            <td><?= htmlspecialchars(getOwnerName($conn, $row['previous_user_id'])) ?></td>
            <td><?= htmlspecialchars(getOwnerName($conn, $row['current_user_id'])) ?></td>
            <td><?= htmlspecialchars($row['change_reason']) ?></td>
            <td><?= htmlspecialchars($row['change_date']) ?></td>
        </tr>
    <?php endwhile; ?>
</table>
<?php else: ?>
    <p>No ownership history found for this equipment.</p>
<?php endif; ?>

<a href="admin_dashboard.php" class="button">⬅ Go Back to Dashboard</a>

</body>
</html>
