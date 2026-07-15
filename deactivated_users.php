<?php
session_start();
require_once 'connect.php';

if (!isset($_SESSION['admin_username'])) {
    header("Location: loginpage.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deactivated Users</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to bottom right, #D2BCA1, #D5B893); /* Akaroa to Tan gradient for background */
            color: #6F4D38; /* Coffee for general text */
            margin: 0;
            padding: 30px; /* Overall page padding */
            line-height: 1.6;
            min-height: 100vh;
            box-sizing: border-box;
        }

        h2 {
            color: #632024; /* Caput Mortuum for main heading */
            text-align: center;
            margin-bottom: 40px;
            font-size: 3.2em; /* Larger main heading */
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
            letter-spacing: 1.5px;
            padding-bottom: 15px;
            border-bottom: 3px solid #A76825; /* Desert accent line */
            display: inline-block; /* To make border fit content */
            margin-left: auto;
            margin-right: auto;
            display: block; /* To center the inline-block element */
            width: fit-content;
        }

        h3 {
            color: #25344F; /* Space Cadet for section headings */
            margin-top: 50px;
            margin-bottom: 25px;
            font-size: 2em; /* Slightly smaller section headings */
            border-bottom: 2px solid #617891; /* Slate Gray accent line */
            padding-bottom: 10px;
            display: inline-block;
            letter-spacing: 0.8px;
        }

        .section {
            background-color: #ffffff; /* White background for each section */
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 8px 25px rgba(111, 72, 28, 0.2); /* Walnut shadow */
            margin-bottom: 40px; /* Space between sections */
            overflow-x: auto; /* Allow horizontal scroll for tables within sections */
        }

        table {
            width: 100%;
            border-collapse: separate; /* Use separate for border-radius */
            border-spacing: 0; /* Remove default spacing */
            margin-top: 20px;
            border-radius: 10px; /* Rounded corners for the table */
            overflow: hidden; /* Ensures border-radius applies */
            min-width: 800px; /* Ensure horizontal scroll on smaller screens */
        }

        th, td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid #D2BCA1; /* Akaroa for horizontal dividers */
            border-right: 1px solid #D2BCA1; /* Akaroa for vertical dividers */
            color: #6F4D38; /* Coffee for cell text */
        }

        th {
            background-color: #273F5B; /* Rhino for table headers */
            color: #D5B893; /* Tan for header text */
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.95em;
            letter-spacing: 0.5px;
            position: sticky;
            top: 0;
            z-index: 1; /* For sticky header */
        }

        /* Rounded corners for header cells */
        th:first-child {
            border-top-left-radius: 10px;
        }
        th:last-child {
            border-top-right-radius: 10px;
            border-right: none; /* Remove extra border on last header cell */
        }

        td:last-child {
            border-right: none; /* Remove extra border on last data cell */
        }

        tr:nth-child(even) {
            background-color: #F8F8F8; /* Near white for even rows */
        }

        tr:hover {
            background-color: #D5B893; /* Tan on row hover */
            color: #25344F; /* Space Cadet text on hover */
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        /* Specific column styling for readability */
        td:nth-child(4) { /* Email column */
            font-weight: 500;
            color: #A76825; /* Desert for emails */
        }
        td:nth-child(5) { /* Role/Department column */
            font-weight: 600;
            color: #632024; /* Caput Mortuum for roles */
        }

        /* "No data found" row styling */
        table tr td[colspan] {
            text-align: center;
            font-style: italic;
            color: #7F715F; /* Sandstone for "no data" text */
            padding: 20px;
            background-color: #f9f9f9;
        }
       
        .center-button {
    text-align: center;
    margin: 40px 0;
}

.back-btn {
    display: inline-block;
    background-color: #632024; /* Maroonish brown */
    color: white;
    padding: 12px 30px;
    text-decoration: none;
    font-weight: bold;
    font-size: 16px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    transition: background 0.3s ease, transform 0.2s ease;
}

.back-btn:hover {
    background-color: #8b3a3a; /* Slightly lighter maroon */
    transform: translateY(-2px);
}

        /* Responsive adjustments */
        @media (max-width: 768px) {
            body {
                padding: 15px;
            }
            h2 {
                font-size: 2.5em;
                margin-bottom: 30px;
            }
            h3 {
                font-size: 1.6em;
                margin-top: 30px;
                margin-bottom: 20px;
            }
            .section {
                padding: 20px;
            }
            th, td {
                padding: 10px;
                font-size: 0.85em;
            }
            table {
                min-width: 600px; /* Adjust min-width for smaller screens if content allows */
            }
        }

        @media (max-width: 480px) {
            h2 {
                font-size: 2em;
            }
            h3 {
                font-size: 1.4em;
            }
            .section {
                padding: 15px;
            }
            th, td {
                padding: 8px;
                font-size: 0.75em;
            }
            table {
                min-width: 400px; /* Further adjust for very small screens */
            }
        }
    </style>
</head>
<body>

    <h2>🗃️ Deactivated Users</h2>

    <!-- Deleted User Accounts Section -->
    <div class="section">
        <h3>❌ Deleted User Accounts</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Super Admin?</th>
                <th>Deleted At</th>
            </tr>

            <?php
            $deleted = $conn->query("SELECT * FROM deleted_users_archive ORDER BY deleted_at DESC");
            if ($deleted && $deleted->num_rows > 0) {
                while ($row = $deleted->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['First_Names']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Last_Name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['role']) . "</td>";
                    echo "<td>" . ($row['is_super_admin'] ? 'Yes' : 'No') . "</td>";
                    echo "<td>" . htmlspecialchars($row['deleted_at']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='7'>No deleted users found.</td></tr>";
            }
            ?>
        </table>
    </div>

    <!-- Removed Staff Section -->
    <div class="section">
        <h3>🚫 Removed Employees</h3>
        <table>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Personal Number</th>
                <th>Email</th>
                <th>Department</th>
                <th>Removal Reason</th>
                <th>Removal Date</th>
            </tr>

            <?php
            $removed = $conn->query("SELECT * FROM removed_staff_archives ORDER BY removal_date DESC");
            if ($removed && $removed->num_rows > 0) {
                while ($row = $removed->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['id']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['First_Names']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Last_Name']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['Personal_number']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['email']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['department']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['removal_reason']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['removal_date']) . "</td>";
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='7'>No removed staff found.</td></tr>";
            }
            ?>
        </table>
    </div>
<div class="center-button">
    <a href="admin_dashboard.php" class="back-btn">← Back to Dashboard</a>
</div>

</body>
</html>
