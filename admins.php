<?php
session_start();
require_once 'connect.php';

// Auth check
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: loginpage.php");
    exit();
}

// Fetch all admins from register table
$sql = "
    SELECT 
        id,
        First_Names,
        Last_Name,
        email,
        role,
        is_super_admin,
        designation
    FROM register
    WHERE role = 'admin'
    ORDER BY id ASC
";
$result = $conn->query($sql);
if (!$result) {
    die("Query Error: " . $conn->error);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Registered Admins</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
   <style>
    
    body {
        font-family: Arial, sans-serif;
        /* Subtle, warm background from Akaroa */
        background: rgba(210, 188, 161, 0.3); /* Akaroa #D2BCA1 with 30% opacity */
        margin: 0;
        padding: 0;
    }
    .container {
        padding: 40px;
        margin-left: 260px; /* Adjust as per your sidebar width */
    }
    h2 {
        /* Changed from Space Cadet (blue) to Coffee (deep brown) */
        color: #6F4D38; /* Coffee */
        font-size: 30px;
        /* Keep Desert for the accent line */
        border-bottom: 3px solid #A76825; /* Desert */
        display: inline-block;
        margin-bottom: 20px;
    }
    table {
        width: 100%;
        border-collapse: collapse;
        background: white; /* Keep white for content clarity */
        /* Using Walnut for a subtle, earthy shadow */
        box-shadow: 0 0 8px rgba(111, 72, 28, 0.15); /* Walnut #6F481C with 15% opacity */
    }
    th, td {
        padding: 12px;
        text-align: left;
        /* Using Akaroa for soft table row dividers */
        border-bottom: 1px solid #D2BCA1; /* Akaroa */
    }
    th {
        /* Changed from Space Cadet (blue) to Caput Mortuum (deep reddish-brown) */
        background:rgb(73, 55, 56); /* Caput Mortuum for table headers */
        color: #D5B893; /* Tan for header text - good contrast */
    }
    tr:hover {
        /* Using Tan for row hover effect */
        background-color: #D5B893; /* Tan */
        /* Changed from Space Cadet (blue) to Coffee (deep brown) for text on hover */
        color: #6F4D38; /* Coffee */
    }
    .sidebar {
        position: fixed;
        width: 240px;
        height: 100vh;
        /* Changed from Space Cadet (blue) to Caput Mortuum (deep reddish-brown) */
        background-color:rgb(73, 58, 59); /* Caput Mortuum for sidebar background */
        color: white; /* Keep white for strong contrast against dark background */
        padding: 20px;
    }
    .sidebar h3 {
        color: #D5B893; /* Tan for sidebar heading */
        /* Changed from Slate Gray (blueish-gray) to Sandstone (muted brown/gray) */
        border-bottom: 1px solid #7F715F; /* Sandstone */
        padding-bottom: 10px;
    }
    .sidebar a {
        display: block;
        color: #D5B893; /* Tan for sidebar links */
        padding: 12px 0;
        text-decoration: none;
    }
    .sidebar a:hover {
        /* Changed from Rhino (lighter blue) to Walnut (darker brown) for hover background */
        background: #6F481C; /* Walnut */
        color: white; /* Keep white for hover text color */
        padding-left: 10px; /* Keep existing animation */
    }
</style>
</head>
<body>

<div class="sidebar">
    <h3><i class="fas fa-user-shield"></i> Admin Menu</h3>
    <a href="admins.php"><i class="fas fa-users-cog"></i> Admins</a>
    <a href="admin_dashboard.php?type=all"><i class="fas fa-list"></i> All Equipment</a>
    <a href="graph.php"><i class="fas fa-chart-bar"></i> Graphs</a>
    <a href="audit_trail.php"><i class="fas fa-clipboard-list"></i> Audit Logs</a>
    <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
</div>

<div class="container">
    <h2>Registered Admins</h2>

    <?php if ($result->num_rows > 0): ?>
        <table>
            <tr>
                <th>ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Designation</th>
                <th>Super Admin</th>
            </tr>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['First_Names']) ?></td>
                    <td><?= htmlspecialchars($row['Last_Name']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td><?= htmlspecialchars($row['designation']) ?></td>
                    <td><?= $row['is_super_admin'] ? '✅ Yes' : '❌ No' ?></td>
                </tr>
            <?php endwhile; ?>
        </table>
    <?php else: ?>
        <p>No admins found.</p>
    <?php endif; ?>
</div>

</body>
</html>
