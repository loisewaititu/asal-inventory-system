<?php
// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ✅ Prevent cached pages from being accessed after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ✅ Auto logout after 1 hour of inactivity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: loginpage.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

// ✅ Ensure only admins can access
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: loginpage.php");
    exit();
}
if (isset($_GET['reassigned']) && $_GET['reassigned'] === 'success') {
    echo '<div style="
        background-color: #d4edda;
        color:rgb(77, 22, 34);
        padding: 15px;
        margin: 20px auto;
        text-align: center;
        width: 80%;
        border: 1px solid #c3e6cb;
        border-radius: 8px;
        font-weight: bold;
    ">
        ✅ Equipment reassigned successfully!
    </div>';
}

$conn = new mysqli("localhost", "root", "", "jl_tracking_system");
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);
$conn->set_charset("utf8");

// Filters
$filter = $_GET['type'] ?? 'all';
$search = $_GET['search'] ?? '';
$filterCondition = "1=1";
if ($filter !== 'all') $filterCondition .= " AND equipment_type = '$filter'";
if (!empty($search)) $filterCondition .= " AND serial_no LIKE '%$search%'";

// UNION Query - Updated 'desktop' selection
$sqlUnion = "
SELECT 'desktop' AS equipment_type, id, date, floor, room, department, brand, model, serial_no,
memory_capacity AS memory, processor_type, operating_system, microsoft_office, hard_disk,
state, connected_to_ups, assigned_to,
is_it_all_in_one, cpu_brand, cpu_serial_no, cpu_state, /* Changed type_of_desktop to is_it_all_in_one */
mouse_brand, mouse_serial_no, mouse_state,
keyboard_brand, keyboard_serial_no, keyboard_state
FROM desktop

UNION ALL

SELECT 'laptop', id, date, floor, room, department, brand, model, serial_no,
memory_capacity, processor_type, operating_system, microsoft_office, hard_disk,
state, NULL, assigned_to,
NULL, NULL, NULL, NULL,
NULL, NULL, NULL,
NULL, NULL, NULL
FROM laptop

UNION ALL

SELECT 'tablet', id, date, floor, room, department, brand, model, serial_no,
NULL, NULL, NULL, NULL, NULL,
state, NULL, assigned_to,
NULL, NULL, NULL, NULL,
NULL, NULL, NULL,
NULL, NULL, NULL
FROM tablet

UNION ALL

SELECT 'ups', id, date, floor, room, department, brand, model, serial_no,
NULL, NULL, NULL, NULL, NULL,
state, NULL, assigned_to,
NULL, NULL, NULL, NULL,
NULL, NULL, NULL,
NULL, NULL, NULL
FROM ups

UNION ALL

SELECT 'scanner', id, date, floor, room, department, brand, model, serial_no,
NULL, NULL, NULL, NULL, NULL,
state, NULL, assigned_to,
NULL, NULL, NULL, NULL,
NULL, NULL, NULL,
NULL, NULL, NULL
FROM scanner

UNION ALL

SELECT 'printer', id, date, floor, room, department, brand, model, serial_no,
NULL, NULL, NULL, NULL, NULL,
state, NULL, assigned_to,
NULL, NULL, NULL, NULL,
NULL, NULL, NULL,
NULL, NULL, NULL
FROM printer

UNION ALL

SELECT 'router', id, date, floor, room, department, brand, model, serial_no,
NULL, NULL, NULL, NULL, NULL,
state, NULL, assigned_to,
NULL, NULL, NULL, NULL,
NULL, NULL, NULL,
NULL, NULL, NULL
FROM router

UNION ALL

SELECT 'firewall', id, date, floor, room, department, brand, model, serial_no,
NULL, NULL, NULL, NULL, NULL,
state, NULL, assigned_to,
NULL, NULL, NULL, NULL,
NULL, NULL, NULL,
NULL, NULL, NULL
FROM firewall

UNION ALL

SELECT 'wireless_access_point', id, date, floor, room, department, brand, model, serial_no,
NULL, NULL, NULL, NULL, NULL,
state, NULL, assigned_to,
NULL, NULL, NULL, NULL,
NULL, NULL, NULL,
NULL, NULL, NULL
FROM wireless_access_point

UNION ALL

SELECT 'projector', id, date, floor, room, department, brand, model, serial_no,
NULL, NULL, NULL, NULL, NULL,
state, NULL, assigned_to,
NULL, NULL, NULL, NULL,
NULL, NULL, NULL,
NULL, NULL, NULL
FROM projector

UNION ALL

SELECT 'cctv', id, date, floor, room, department, brand, model, serial_no,
NULL, NULL, NULL, NULL, NULL,
state, NULL, NULL,
NULL, NULL, NULL, NULL,
NULL, NULL, NULL,
NULL, NULL, NULL
FROM cctv

UNION ALL

SELECT 'ip_phone', id, date, floor, room, department, brand, model, serial_no,
NULL, NULL, NULL, NULL, NULL,
state, NULL, assigned_to,
NULL, NULL, NULL, NULL,
NULL, NULL, NULL,
NULL, NULL, NULL
FROM ip_phone

UNION ALL

SELECT 'network_switches', id, date, floor, room, department, brand, model, serial_no,
NULL, NULL, NULL, NULL, NULL,
state, NULL, assigned_to,
NULL, NULL, NULL, NULL,
NULL, NULL, NULL,
NULL, NULL, NULL
FROM network_switches
";

$sql = "SELECT * FROM ($sqlUnion) AS combined WHERE $filterCondition ORDER BY date DESC, equipment_type, id";
$result = $conn->query($sql);
if (!$result) die("Query failed: " . $conn->error);

function safe($v) {
    return $v === null || $v === '' ? '-' : htmlspecialchars($v);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard</title>
    <link rel="stylesheet" href="admin2.css">
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
    <a href="Staff.php"><i class="fas fa-users"></i> Employee</a>
    <a href="audit_trail.php" style="text-decoration: none;">📋 Audit Logs</a>
    <li><a href="disposed_equipment.php"><i class="fas fa-trash"></i> Disposed Equipment</a></li>
    <li><a href="deactivated_users.php"><i class="fas fa-user-slash"></i> Deactivated Users</a></li>
    <li><a href="manage_options.php"><i class="fas fa-tools"></i>Dropdown Options</a></li>
    <a href="search.php"><i class="fas fa-search"></i> Search</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<script>
function toggleDropdown() {
    var dropdownContent = document.getElementById("equipmentDropdown");
    var dropdownToggle = document.querySelector(".dropdown-toggle"); // Get the parent for rotating arrow

    if (dropdownContent.style.display === "none" || dropdownContent.style.display === "") {
        dropdownContent.style.display = "flex"; // Changed to 'flex' as per your CSS for dropdown-content
        dropdownToggle.classList.add("open"); // Add class to rotate arrow
    } else {
        dropdownContent.style.display = "none";
        dropdownToggle.classList.remove("open"); // Remove class to reset arrow
    }
}

// Optional: Close dropdown if clicked outside
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
    <h2>ICT Equipment Database</h2>


    <form method="GET" action="download_pdf_report.php" class="download-report">
        <input type="hidden" name="type" value="<?= htmlspecialchars($filter) ?>">
        <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Download Report (PDF)</button>
    </form>

    <?php if ($result->num_rows > 0): ?>
    <div class="table-container">
        <table>
            <thead>
            <tr>
                <th>Type</th><th>Date</th><th>Floor</th><th>Room</th><th>Department</th>
                <th>Brand</th><th>Model</th><th>Serial No</th><th>Memory</th><th>Processor</th>
                <th>OS</th><th>Office</th><th>Hard Disk</th><th>State</th><th>UPS</th><th>Assigned</th>
                <th>Is it All-in-one?</th><th>CPU Brand</th><th>CPU Serial</th><th>CPU State</th>
                <th>Mouse Brand</th><th>Mouse Serial</th><th>Mouse State</th>
                <th>Keyboard Brand</th><th>Keyboard Serial</th><th>Keyboard State</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
                <td><?= safe($row['equipment_type']) ?></td>
                <td><?= safe($row['date']) ?></td>
                <td><?= safe($row['floor']) ?></td>
                <td><?= safe($row['room']) ?></td>
                <td><?= safe($row['department']) ?></td>
                <td><?= safe($row['brand']) ?></td>
                <td><?= safe($row['model']) ?></td>
                <td><?= safe($row['serial_no']) ?></td>
                <td><?= safe($row['memory']) ?></td>
                <td><?= safe($row['processor_type']) ?></td>
                <td><?= safe($row['operating_system']) ?></td>
                <td><?= safe($row['microsoft_office']) ?></td>
                <td><?= safe($row['hard_disk']) ?></td>
                <td><?= safe($row['state']) ?></td>
                <td><?= safe($row['connected_to_ups']) ?></td>
                <td><?= safe($row['assigned_to']) ?></td>

                <td>
                <?php
                    // Display 'Yes' for 'Yes' or '1'
                    if ($row['is_it_all_in_one'] === 'Yes' || $row['is_it_all_in_one'] === '1') {
                        echo 'Yes';
                    }
                    // Display 'No' for 'No' or '0'
                    elseif ($row['is_it_all_in_one'] === 'No' || $row['is_it_all_in_one'] === '0') {
                        echo 'No';
                    }
                    // For anything else (like NULL, empty, or 'N/A'), use the default safe function behavior
                    else {
                        echo safe($row['is_it_all_in_one']);
                    }
                ?>
                </td>
                <td><?= safe($row['cpu_brand']) ?></td>
                <td><?= safe($row['cpu_serial_no']) ?></td>
                <td><?= safe($row['cpu_state']) ?></td>
                <td><?= safe($row['mouse_brand']) ?></td>
                <td><?= safe($row['mouse_serial_no']) ?></td>
                <td><?= safe($row['mouse_state']) ?></td>
                <td><?= safe($row['keyboard_brand']) ?></td>
                <td><?= safe($row['keyboard_serial_no']) ?></td>
                <td><?= safe($row['keyboard_state']) ?></td>

                <td>
    <a href="assign_equipment.php?type=<?= $row['equipment_type'] ?>&id=<?= $row['id'] ?>">Assign</a> |
    <a href="equipment_history.php?type=<?= $row['equipment_type'] ?>&id=<?= $row['id'] ?>">History</a> |
    <a href="edit_device.php?equipment_type=<?= $row['equipment_type'] ?>&id=<?= $row['id'] ?>">Edit</a> |
    <a href="dispose_equipment.php?id=<?= $row['id'] ?>&type=<?= $row['equipment_type'] ?>"
    class="dispose-btn"
    onclick="return confirm('Are you sure you want to dispose of this equipment?');">
    Dispose
</a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div> <?php else: ?>
        <p>No results found.</p>
    <?php endif; ?>
</div>

</body>
</html>