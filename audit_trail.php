<?php
require_once 'connect.php';
require_once 'includes/audit_logger.php';
session_start();

// Only allow admins
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$current_month = $_GET['month'] ?? date('Y-m');

// Get logs for the selected month
$stmt = $conn->prepare("SELECT * FROM audit_log WHERE DATE_FORMAT(timestamp, '%Y-%m') = ? ORDER BY timestamp DESC");
$stmt->bind_param("s", $current_month);
$stmt->execute();
$result = $stmt->get_result();

$grouped_logs = [];
while ($row = $result->fetch_assoc()) {
    $date = date('d F Y', strtotime($row['timestamp']));
    $grouped_logs[$date][] = $row;
}

// Secure output
function safe($data) {
    return htmlspecialchars($data ?? '', ENT_QUOTES, 'UTF-8');
}

// Get available months
$month_stmt = $conn->query("SELECT DISTINCT DATE_FORMAT(timestamp, '%Y-%m') AS month FROM audit_log ORDER BY month DESC");
$months = [];
while ($row = $month_stmt->fetch_assoc()) {
    $months[] = $row['month'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Audit Trail - Admin Dashboard</title>
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="audit1.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>

<div class="sidebar">
    <h3><i class="fas fa-user-shield"></i> Admin Menu</h3>
    <a href="admins.php"><i class="fas fa-users-cog"></i> Admins</a>
    <div class="dropdown">
        <div class="sidebar-link dropdown-toggle" onclick="toggleDropdown()">
            <i class="fas fa-box"></i> Equipments <i class="fas fa-caret-down" style="float:right;"></i>
        </div>
        <div class="dropdown-content" id="equipmentDropdown">
            <a href="?type=all"><i class="fas fa-list"></i> All Equipments</a>
            <a href="?type=desktop"><i class="fas fa-desktop"></i> Desktops</a>
            <a href="?type=laptop"><i class="fas fa-laptop"></i> Laptops</a>
            <a href="?type=tablet"><i class="fas fa-tablet-alt"></i> Tablets</a>
            <a href="?type=ups"><i class="fas fa-battery-full"></i> UPS</a>
            <a href="?type=scanner"><i class="fas fa-print"></i> Scanners</a>
            <a href="?type=printer"><i class="fas fa-print"></i> Printers</a>
            <a href="?type=router"><i class="fas fa-wifi"></i> Routers</a>
            <a href="?type=firewall"><i class="fas fa-shield-alt"></i> Firewalls</a>
            <a href="?type=wireless_access_point"><i class="fas fa-network-wired"></i> Wireless APs</a>
            <a href="?type=projector"><i class="fas fa-video"></i> Projectors</a>
            <a href="?type=cctv"><i class="fas fa-video"></i> CCTV</a>
            <a href="?type=ip_phone"><i class="fas fa-phone"></i> IP Phones</a>
            <a href="?type=network_switches"><i class="fas fa-ethernet"></i> Network Switches</a>
        </div>
    </div>
    <a href="graph.php"><i class="fas fa-chart-bar"></i> Graphs</a>
    <a href="admin_management.php"><i class="fas fa-user-cog"></i> Admin Management</a>
    <a href="Staff.php"><i class="fas fa-users"></i> Employees</a>
    <a href="admin_dashboard.php"><i class="fas fa-users"></i> Back to Dashboard</a>
    <a href="audit_trail.php"><i class="fas fa-clipboard-list"></i> Audit Trail</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<script>
    function toggleDropdown() {
        var dropdownContent = document.getElementById("equipmentDropdown");
        var dropdownToggle = document.querySelector(".dropdown-toggle");
        if (dropdownContent.style.display === "none" || dropdownContent.style.display === "") {
            dropdownContent.style.display = "flex";
            dropdownToggle.classList.add("open");
        } else {
            dropdownContent.style.display = "none";
            dropdownToggle.classList.remove("open");
        }
    }
    document.addEventListener('click', function(event) {
        const dropdown = document.querySelector('.dropdown');
        const dropdownContent = document.getElementById("equipmentDropdown");
        const dropdownToggle = document.querySelector(".dropdown-toggle");
        if (!dropdown.contains(event.target) && dropdownContent.style.display === 'flex') {
            dropdownContent.style.display = 'none';
            dropdownToggle.classList.remove("open");
        }
    });
</script>

<div class="main">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
        <h2 style="margin: 0;"><i class="fas fa-clipboard-list"></i> Audit Trail</h2>

        <a href="download_audit_report.php" target="_blank" style="
            background-color: #2c3e50;
            color: white;
            padding: 10px 15px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
        ">
            ⬇ Download Report (PDF)
        </a>
    </div>

    <div class="filter-container">
        <form method="GET" action="">
            <label for="month">Filter by Month: </label>
            <select name="month" id="month">
                <?php foreach ($months as $month): ?>
                    <option value="<?= safe($month) ?>" <?= $month == $current_month ? 'selected' : '' ?>>
                        <?= date("F Y", strtotime($month . "-01")) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Apply</button>
        </form>
    </div>

    <div class="audit-table-container">
        <?php if (count($grouped_logs) > 0): ?>
            <?php foreach ($grouped_logs as $date => $entries): ?>
                <div class="date-heading"><?= safe($date) ?></div>
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>Entity Type</th>
                            <th>Entity ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($entries as $row): ?>
                            <?php
                                $desc = $row['description'];
                                if ($row['action_type'] === 'DISPOSED') {
                                    $desc = "Disposed by {$row['user_name']}: {$row['entity_type']} (Serial: {$row['description']})";
                                }
                                if ($row['action_type'] === 'ADDED') {
                                    $desc = "Added by {$row['user_name']}: {$row['entity_type']} (Serial: {$row['description']})";
                                }
                            ?>
                            <tr>
                                <td><?= safe(date('H:i:s', strtotime($row['timestamp']))) ?></td>
                                <td><?= safe($row['user_name']) ?></td>
                                <td><?= safe($row['action_type']) ?></td>
                                <td><?= safe($desc) ?></td>
                                <td><?= safe($row['entity_type']) ?></td>
                                <td><?= safe($row['entity_id']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No audit logs found for this month.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>

<?php
$stmt->close();
$conn->close();
?>
