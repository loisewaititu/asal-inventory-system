<?php
require 'connect.php';
require 'includes/audit_logger.php'; // <-- Include your audit logging function

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

// ✅ Restrict access to admins only
if (!isset($_SESSION['admin_username']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: loginpage.php?error=unauthorized");
    exit();
}

$error = ''; // Initialize error variable
$success_message = ''; // Initialize success message variable

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dept_name = trim($_POST['department_name'] ?? '');

    if (!empty($dept_name)) {
        // Check if department already exists
        $checkStmt = $conn->prepare("SELECT COUNT(*) FROM departments WHERE name = ?");
        if ($checkStmt === false) {
            error_log("Department check prepare error: " . $conn->error);
            $error = "Database error during check. Please try again.";
        } else {
            $checkStmt->bind_param("s", $dept_name);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            $row = $checkResult->fetch_row();
            if ($row[0] > 0) {
                $error = "Department '$dept_name' already exists.";
            } else {
                // Insert new department
                $stmt = $conn->prepare("INSERT INTO departments (name) VALUES (?)");
                if ($stmt === false) {
                    error_log("Department insert prepare error: " . $conn->error);
                    $error = "Database error during insert. Please try again.";
                } else {
                    $stmt->bind_param("s", $dept_name);

                    if ($stmt->execute()) {
                        // Audit log
                        $adminName = $_SESSION['admin_username'] ?? 'Unknown Admin'; // Use admin_username for display
                        log_activity(
                            $_SESSION['admin_id'] ?? null, // admin_id
                            $adminName,
                            'ADD_DEPARTMENT', // Action type
                            "Added new department: $dept_name", // Description
                            'departments', // Table affected
                            $stmt->insert_id, // ID of new department
                            [], // Old data (empty for add)
                            ['name' => $dept_name] // New data
                        );

                        $success_message = "Department added successfully!";
                        // Instead of alert and redirect, display message and then a link or simply redirect after displaying.
                        // For a cleaner UX without alert, just set success_message and let the page render.
                        // You could still redirect if preferred: header("Location: staff.php?dept_added=success"); exit();
                        // For now, I'll display the success message on the current page.
                    } else {
                        // This else block might be for other DB errors during execution, not just duplicate.
                        error_log("Department insert execute error: " . $stmt->error);
                        $error = "Error adding department: " . $stmt->error; // More specific error
                    }
                    $stmt->close();
                }
            }
            $checkStmt->close();
        }
    } else {
        $error = "Please enter a department name.";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Add Department</title>
    <link rel="stylesheet" href="search1.css"> <style>
        /* Specific styles for Add Department page, consistent with your palette */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to bottom right, #D2BCA1, #D5B893); /* Akaroa to Tan gradient */
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            box-sizing: border-box;
            color: #6F4D38; /* Coffee for general text */
        }

        .container {
            background-color: #ffffff; /* White background for the form container */
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(111, 72, 28, 0.3); /* Walnut shadow */
            width: 100%;
            max-width: 450px; /* Adjusted max-width for department form */
            text-align: left;
            border: 1px solid #D5B893; /* Tan border */
            display: flex;
            flex-direction: column;
            align-items: stretch;
        }

        h2 {
            color: #25344F; /* Space Cadet for main heading */
            font-size: 2.5em;
            margin-bottom: 30px;
            text-align: center;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.1);
            letter-spacing: 0.5px;
            position: relative;
            padding-bottom: 10px;
        }

        h2::after {
            content: '';
            width: 80px;
            height: 3px;
            background-color: #A76825; /* Desert accent */
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            border-radius: 55px;
        }

        label {
            display: block;
            font-weight: 600;
            color: #25344F; /* Space Cadet */
            margin-bottom: 8px;
            font-size: 1em;
        }

        input[type="text"] {
            width: 100%;
            padding: 12px 10px;
            border: 2px solid #D2BCA1; /* Akaroa border */
            border-radius: 8px;
            font-size: 1em;
            color: #6F4D38; /* Coffee */
            background-color: #fcfcfc;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.08);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box;
            margin-bottom: 20px; /* Space after input field */
        }

        input[type="text"]:focus {
            border-color: #A76825; /* Desert on focus */
            box-shadow: 0 0 0 3px rgba(167, 104, 37, 0.2); /* Desert focus glow */
            outline: none;
        }

        button[type="submit"] {
            background-color: #25344F; /* Space Cadet for the button */
            color: white;
            padding: 14px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            font-weight: 600;
            letter-spacing: 0.5px;
            width: fit-content; /* Make button width fit its content */
            margin-top: 10px; /* Space above submit button */
            align-self: center; /* Center button within flex container */
        }

        button[type="submit"]:hover {
            background-color: #273F5B; /* Rhino on hover */
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 52, 79, 0.3); /* Space Cadet shadow on hover */
        }

        button[type="submit"]:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(37, 52, 79, 0.4);
        }

        .message-error {
            color: #dc3545; /* Red */
            background-color: #ffe6e6; /* Light red */
            border: 1px solid #dc3545;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }

        .message-success {
            color: #28a745; /* Green */
            background-color: #e6ffe6; /* Light green */
            border: 1px solid #28a745;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
        }

        .link-group {
            margin-top: 30px;
            text-align: center;
            display: flex;
            flex-direction: column;
            gap: 15px; /* Space between links */
        }

        .link-group a {
            color: #273F5B; /* Rhino for links */
            text-decoration: none;
            font-weight: 600;
            font-size: 1em;
            transition: color 0.3s ease, transform 0.2s ease;
        }

        .link-group a:hover {
            color: #A76825; /* Desert on hover */
            transform: translateX(5px);
        }

        /* Specific style for "Back to Staff Page" to match previous "Go Back" button styles */
        .link-group .back-to-staff {
            background-color: #A76825; /* Desert for back button */
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            display: inline-block; /* Makes padding work */
            width: fit-content; /* Adjust width to content */
            margin: 0 auto; /* Center the block element */
            box-shadow: 0 3px 10px rgba(167, 104, 37, 0.2);
        }

        .link-group .back-to-staff:hover {
            background-color: #7F715F; /* Sandstone on hover */
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(167, 104, 37, 0.3);
        }

        /* Responsive adjustments */
        @media (max-width: 600px) {
            .container {
                padding: 25px;
                margin: 0 15px;
            }
            h2 {
                font-size: 2em;
                margin-bottom: 20px;
            }
            input[type="text"] {
                padding: 10px;
                font-size: 0.95em;
                margin-bottom: 15px;
            }
            button[type="submit"],
            .link-group .back-to-staff {
                padding: 12px 20px;
                font-size: 1em;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Add Department</h2>

        <?php if (!empty($error)): ?>
            <p class="message-error"><?= htmlspecialchars($error) ?></p>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <p class="message-success"><?= htmlspecialchars($success_message) ?></p>
        <?php endif; ?>

        <form method="POST" action="">
            <label for="department_name">Department Name:</label>
            <input type="text" id="department_name" name="department_name" required>
            <button type="submit">Add Department</button>
        </form>

        <div class="link-group">
            <a href="manage_departments.php">Manage Departments</a>
            <a href="staff.php" class="back-to-staff">← Back to Staff Page</a>
        </div>
    </div>
</body>
</html>