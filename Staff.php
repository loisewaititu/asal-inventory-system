<?php
session_start();
require_once 'connect.php';           // Database connection
require_once 'includes/audit_logger.php'; // Audit logging

// Prevent cached access after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Current admin info
$current_admin_id   = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

// Ensure admin login
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: loginpage.php?error=unauthorized");
    exit();
}

// Auto logout after 1 hour
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: loginpage.php?message=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();
?>


<!DOCTYPE html>
<html>
<head>
    <title>Employee Management</title>
    <style>
    body {
        margin: 0;
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; /* Consistent font */
        /* Replaced light purple background with a soft Akaroa background */
        background-color: #D2BCA1; /* Akaroa */
        color: #6F4D38; /* Coffee for default text */
    }

    .sidebar {
        width: 260px; /* Slightly wider sidebar */
        /* Replaced existing background with Space Cadet for a strong, dark sidebar */
        background: #25344F; /* Space Cadet */
        color: white; /* White text for sidebar */
        height: 100vh;
        position: fixed;
        padding-top: 25px; /* More padding at the top */
        overflow-y: auto;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.2); /* Subtle shadow for depth */
    }

    .sidebar h2 {
        text-align: center;
        margin-bottom: 30px; /* More spacing below heading */
        font-size: 28px; /* Larger heading */
        color: #D5B893; /* Tan for sidebar heading */
    }

    .sidebar ul {
        list-style-type: none;
        padding-left: 20px;
        margin: 0;
    }

    .sidebar ul li {
        padding: 12px 20px; /* More padding for list items */
        cursor: pointer;
        transition: background 0.3s ease, color 0.3s ease;
        border-left: 5px solid transparent; /* Highlight on hover/active */
    }

    .sidebar ul li:hover {
        /* Hover background using a transparent Tan */
        background: rgba(213, 184, 147, 0.1); /* Tan (10% opacity) */
        border-left-color: #D5B893; /* Tan for border highlight */
    }

    .sidebar ul li a {
        color: white;
        text-decoration: none;
        display: block;
        font-size: 16px;
        display: flex; /* Use flexbox for icon and text alignment */
        align-items: center; /* Vertically center icon and text */
    }

    .sidebar ul li a i {
        margin-right: 10px; /* Space between icon and text */
        color: #D5B893; /* Tan for icons */
        font-size: 18px; /* Slightly larger icon size */
        transition: color 0.3s ease;
    }

    .sidebar ul li a:hover {
        color: #D5B893; /* Tan text on hover */
    }

    .sidebar ul li a:hover i {
        color: white; /* White icon on hover for contrast */
    }

    /* Active state for sidebar links (if you implement JS to set an 'active' class) */
    .sidebar ul li.active {
        background: rgba(213, 184, 147, 0.2); /* Slightly darker transparent Tan for active */
        border-left-color: #A76825; /* Desert for active border highlight */
    }

    .sidebar ul li.active a {
        color: #D5B893; /* Tan for active text */
        font-weight: bold;
    }

    .sidebar ul li.active a i {
        color: #A76825; /* Desert for active icon */
    }


    .dropdown {
        display: none;
        list-style-type: none;
        padding-left: 25px; /* Deeper indent for dropdown items */
        background-color: rgba(0, 0, 0, 0.1); /* Slightly darker background for dropdown */
    }

    .dropdown li {
        padding: 8px 0; /* Adjust padding for dropdown items */
        font-size: 15px;
    }

    .dropdown.active {
        display: block;
    }

    .main {
        margin-left: 280px; /* Adjusted margin for slightly wider sidebar */
        padding: 30px;
    }

    table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 25px; /* More space above table */
        background: white; /* Clean white background */
        /* Shadow using Walnut */
        box-shadow: 0 5px 15px rgba(111, 72, 28, 0.15); /* Walnut shadow */
        border-radius: 12px; /* Rounded table corners */
        overflow: hidden; /* Ensure rounded corners clip content */
    }

    table th, table td {
        /* Border using Akaroa */
        border: 1px solid #D2BCA1; /* Akaroa */
        padding: 14px 18px; /* More padding for cells */
        text-align: left;
    }

    table th {
        /* Header background using Caput Mortuum */
        background-color: #632024; /* Caput Mortuum */
        /* Header text color using Tan */
        color: #D5B893; /* Tan */
        font-weight: bold;
        text-transform: uppercase;
        font-size: 15px;
    }

    /* Rounded corners for table headers */
    table th:first-child {
        border-top-left-radius: 12px;
    }
    table th:last-child {
        border-top-right-radius: 12px;
    }

    table tr:nth-child(even) {
        /* Even row background using light transparent Akaroa */
        background-color: rgba(210, 188, 161, 0.2); /* Akaroa (20% opacity) */
    }

    table tr:hover {
        /* Hover background using Tan */
        background-color: #D5B893; /* Tan */
        /* Hover text color using Space Cadet */
        color: #25344F; /* Space Cadet */
        transition: background-color 0.3s ease, color 0.3s ease;
    }
    table tr:hover td {
        border-color: #A76825; /* Desert border on hover for subtle pop */
    }

    button.remove-btn {
        /* Remove button background using Caput Mortuum */
        background-color: #632024; /* Caput Mortuum */
        color: white;
        padding: 8px 15px; /* More padding */
        border: none;
        border-radius: 6px; /* Rounded buttons */
        cursor: pointer;
        font-size: 13px;
        transition: background-color 0.3s ease, transform 0.2s ease;
    }

    button.remove-btn:hover {
        /* Remove button hover using a darker Caput Mortuum */
        background-color: #4B1A1E; /* Darker Caput Mortuum */
        transform: translateY(-1px);
    }

    .download-form {
        margin-bottom: 25px; /* More space below form */
        text-align: right; /* Align download button to the right */
    }

    .download-btn {
        /* Download button background using Desert */
        background-color: #A76825; /* Desert */
        color: white;
        padding: 12px 20px; /* More padding */
        border: none;
        border-radius: 8px; /* Rounded button */
        font-size: 15px;
        cursor: pointer;
        font-weight: bold;
        transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
    }

    .download-btn:hover {
        /* Download button hover using Walnut */
        background-color: #6F481C; /* Walnut */
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    .filter-select {
        padding: 8px 12px; /* More padding */
        font-size: 14px;
        border-radius: 6px; /* Rounded select */
        margin-right: 15px; /* More margin */
        /* Border using Slate Gray */
        border: 1px solid #617891; /* Slate Gray */
        /* Background using light Akaroa */
        background-color: rgba(210, 188, 161, 0.1); /* Light Akaroa */
        color: #6F4D38; /* Coffee for text */
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
        appearance: none; /* Remove default arrow */
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url('data:image/svg+xml;utf8,<svg fill="%236F4D38" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>'); /* Custom arrow */
        background-repeat: no-repeat;
        background-position: right 8px center;
        background-size: 18px;
        cursor: pointer;
    }

    .filter-select:focus {
        outline: none;
        /* Focus highlight using Desert accent */
        border-color: #A76825; /* Desert */
        box-shadow: 0 0 0 3px rgba(167, 104, 37, 0.2); /* Desert shadow for focus */
    }

    /* Responsive Adjustments */
    @media screen and (max-width: 768px) {
        .sidebar {
            width: 200px;
        }
        .sidebar h2 {
            font-size: 24px;
            margin-bottom: 20px;
        }
        .main {
            margin-left: 220px;
            padding: 20px;
        }
        table th, table td {
            padding: 10px 14px;
            font-size: 13px;
        }
        .download-btn {
            padding: 10px 16px;
            font-size: 14px;
        }
        .filter-select {
            padding: 6px 10px;
            font-size: 13px;
            margin-bottom: 10px; /* Stack filters */
        }
        .download-form {
            text-align: center; /* Center button on smaller screens */
        }
    }

    @media screen and (max-width: 480px) {
        .sidebar {
            width: 100%;
            height: auto;
            position: relative;
            padding-top: 15px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .sidebar ul {
            padding-left: 10px;
        }
        .sidebar ul li {
            padding: 8px 10px;
            font-size: 14px;
        }
        .main {
            margin-left: 0;
            padding: 15px;
        }
        table {
            border-radius: 8px;
        }
        table th, table td {
            padding: 8px 10px;
            font-size: 12px;
        }
        .remove-btn, .download-btn {
            padding: 6px 10px;
            font-size: 12px;
        }
        .filter-select {
            width: 100%;
            margin-right: 0;
        }
    }
</style>
    <script>
        function toggleDropdown(id) {
            const dropdown = document.getElementById(id);
            dropdown.classList.toggle('active');
        }

        function confirmRemove(form) {
            const reason = prompt("Why are you removing this staff? (Retirement, Leave, Transferred)");
            const validReasons = ['Retirement', 'Leave', 'Transferred'];
            if (!reason || !validReasons.includes(reason)) {
                alert("Invalid reason. Please enter: Retirement, Leave, or Transferred.");
                return false;
            }
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'reason';
            input.value = reason;
            form.appendChild(input);
            return true;
        }
    </script>
</head>
<body>

<div class="sidebar">
    <h2>Employee Menu</h2>
    <ul>
        <li onclick="toggleDropdown('all-staff')">All Employees ▾
            <ul id="all-staff" class="dropdown">
                <li>Research & Planning Division</li>
                <li>Research & Policy Development</li>
                <li>Knowledge Management</li>
                <li>Partnership & Resource Mobilization</li>
                <li>Value Chain & Entrepreneurship</li>
                <li>Livelihood Diversification</li>
                <li>Special Programs - Human Capital Development</li>
                <li>ASALS Programmes</li>
                <li>Climate Change Response</li>
                <li>Strategic Programs</li>
                <li onclick="toggleDropdown('special-programs')">Special Programs ▾
                    <ul id="special-programs" class="dropdown">
                        <li>Human Capital Development</li>
                        <li>ASALS Programmes</li>
                        <li>Climate Change Response</li>
                    </ul>
                </li>
                <li>Strategic Programs</li>
                <li onclick="toggleDropdown('social-integration')">Community Social Integration Div ▾
                    <ul id="social-integration" class="dropdown">
                        <li>Peace & Building Conflict Mgt</li>
                        <li>Integrated Cross Border Section</li>
                    </ul>
                </li>
                <li>HR</li>
                <li>Administration</li>
                <li>Finance</li>
                <li>Counselling</li>
                <li>Accounts</li>
                <li>CPPMU</li>
                <li>ICT</li>
                <li>SCMS</li>
                <li>PRO/PCO</li>
                <li>Legal</li>
                <li>Internal Audit</li>
            </ul>
        </li>
        <li><a href="add_staff.php">Add Employee</a></li>
        <li><a href="add_department.php"><i class="fas fa-plus-circle"></i> Add Department</a></li>
        <li><a href="manage_departments.php">Manage Departments</a></li>
        <li><a href="admin_dashboard.php">Back to Dashboard</a></li>
        <li><a href="logout.php">Logout</a></li>
    </ul>
</div>

<div class="main">
    <h1>All Employees</h1>

    <form class="download-form" method="GET" action="download_report.php">
        <select name="department" class="filter-select">
            <option value="">All Departments</option>
            <?php
            $dep_query = $conn->query("SELECT DISTINCT department FROM staff ORDER BY department ASC");
            while ($dep = $dep_query->fetch_assoc()) {
                $selected = (isset($_GET['department']) && $_GET['department'] === $dep['department']) ? 'selected' : '';
                echo "<option value=\"" . htmlspecialchars($dep['department']) . "\" $selected>" . htmlspecialchars($dep['department']) . "</option>";
            }
            ?>
        </select>
        <button type="submit" class="download-btn">📄 Download PDF Report</button>
    </form>

    <table>
        <tr>
            <th>ID</th>
            <th>First Names</th>
            <th>Last Name</th>
            <th>Designation</th>
            <th>Personal Number</th>
            <th>Email</th>
            <th>Department</th>
            <th>Actions</th>
        </tr>

        <?php
        $filter = isset($_GET['department']) && $_GET['department'] !== "" ? $conn->real_escape_string($_GET['department']) : "";

        $sql = $filter
            ? "SELECT id, First_Names, Last_Name, designation, Personal_number, email, department FROM staff WHERE department='$filter' ORDER BY Last_Name ASC, First_Names ASC"
            : "SELECT id, First_Names, Last_Name, designation, Personal_number, email, department FROM staff ORDER BY department ASC, Last_Name ASC, First_Names ASC";

        $result = $conn->query($sql);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                echo "<td>" . htmlspecialchars($row['First_Names']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Last_Name']) . "</td>";
                echo "<td>" . htmlspecialchars($row['designation']) . "</td>";
                echo "<td>" . htmlspecialchars($row['Personal_number']) . "</td>";
                echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                echo "<td>" . htmlspecialchars($row['department']) . "</td>";
                echo "<td>
                    <form method='post' action='remove_staff.php' onsubmit='return confirmRemove(this);'>
                        <input type='hidden' name='id' value='" . htmlspecialchars($row['id']) . "'>
                        <button type='submit' class='remove-btn'>Remove</button>
                    </form>
                </td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='8'>No staff found.</td></tr>";
        }
        ?>
    </table>
</div>

</body>
</html>
