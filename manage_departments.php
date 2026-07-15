<?php
require 'connect.php';
require 'includes/audit_logger.php';
session_start();

// ✅ Prevent cached access after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ✅ Ensure admin is logged in
if (!isset($_SESSION['admin_username']) || $_SESSION['role'] !== 'admin') {
    header("Location: loginpage.php?error=unauthorized");
    exit();
}

// ✅ Session timeout (auto logout after 1 hour)
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 3600) {
    session_unset();
    session_destroy();
    header("Location: loginpage.php?message=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();

$admin_id = $_SESSION['admin_id'] ?? null;
$admin_name = $_SESSION['admin_username'] ?? 'Unknown Admin';

// Message handling
$message = '';
$message_type = ''; // 'success' or 'error'

// Delete department
if (isset($_GET['delete'])) {
    $id_to_delete = intval($_GET['delete']);

    // Get department name before deleting (using prepared statement)
    $stmt_get_name = $conn->prepare("SELECT name FROM departments WHERE id = ?");
    if ($stmt_get_name === false) {
        error_log("manage_departments.php: Get name prepare error: " . $conn->error);
        $message = "Database error fetching department name.";
        $message_type = 'error';
    } else {
        $stmt_get_name->bind_param("i", $id_to_delete);
        $stmt_get_name->execute();
        $result_name = $stmt_get_name->get_result();
        $dept = $result_name->fetch_assoc();
        $stmt_get_name->close();

        if ($dept) {
            $dept_name_to_delete = $dept['name'];

            // Check if department is used by any staff (using prepared statement)
            $check_usage_stmt = $conn->prepare("SELECT COUNT(*) FROM staff WHERE department = ?");
            if ($check_usage_stmt === false) {
                error_log("manage_departments.php: Check usage prepare error: " . $conn->error);
                $message = "Database error checking department usage.";
                $message_type = 'error';
            } else {
                $check_usage_stmt->bind_param("s", $dept_name_to_delete);
                $check_usage_stmt->execute();
                $check_usage_stmt->bind_result($count);
                $check_usage_stmt->fetch();
                $check_usage_stmt->close();

                if ($count > 0) {
                    $message = "Cannot delete: Department '$dept_name_to_delete' is assigned to $count staff member(s).";
                    $message_type = 'error';
                } else {
                    // Delete department (using prepared statement)
                    $delete_stmt = $conn->prepare("DELETE FROM departments WHERE id = ?");
                    if ($delete_stmt === false) {
                        error_log("manage_departments.php: Delete prepare error: " . $conn->error);
                        $message = "Database error during deletion.";
                        $message_type = 'error';
                    } else {
                        $delete_stmt->bind_param("i", $id_to_delete);
                        if ($delete_stmt->execute()) {
                            $message = "Department '$dept_name_to_delete' deleted successfully!";
                            $message_type = 'success';
                            // Audit log
                            log_activity(
                                $admin_id,
                                $admin_name,
                                'DELETE_DEPARTMENT',
                                "Deleted department: $dept_name_to_delete (ID: $id_to_delete)",
                                'departments',
                                $id_to_delete,
                                ['name' => $dept_name_to_delete],
                                [] // No new data for delete
                            );
                        } else {
                            error_log("manage_departments.php: Delete execute error: " . $delete_stmt->error);
                            $message = "Failed to delete department: " . $delete_stmt->error;
                            $message_type = 'error';
                        }
                        $delete_stmt->close();
                    }
                }
            }
        } else {
            $message = "Department not found.";
            $message_type = 'error';
        }
    }
    // Redirect to clear GET parameters if any action was taken
    header("Location: manage_departments.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit;
}

// Update department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_id'])) {
    $edit_id = intval($_POST['edit_id']);
    $new_name = trim($_POST['new_name']);

    if (!empty($new_name)) {
        // Get current name before updating (using prepared statement)
        $stmt_get_old_name = $conn->prepare("SELECT name FROM departments WHERE id = ?");
        if ($stmt_get_old_name === false) {
            error_log("manage_departments.php: Update get old name prepare error: " . $conn->error);
            $message = "Database error fetching old department name.";
            $message_type = 'error';
        } else {
            $stmt_get_old_name->bind_param("i", $edit_id);
            $stmt_get_old_name->execute();
            $result_old_name = $stmt_get_old_name->get_result();
            $dept_old = $result_old_name->fetch_assoc();
            $stmt_get_old_name->close();

            if ($dept_old) {
                $old_name = $dept_old['name'];

                // Check if the new name already exists for another department
                $check_duplicate_stmt = $conn->prepare("SELECT COUNT(*) FROM departments WHERE name = ? AND id != ?");
                if ($check_duplicate_stmt === false) {
                    error_log("manage_departments.php: Duplicate check prepare error: " . $conn->error);
                    $message = "Database error checking for duplicate department name.";
                    $message_type = 'error';
                } else {
                    $check_duplicate_stmt->bind_param("si", $new_name, $edit_id);
                    $check_duplicate_stmt->execute();
                    $check_duplicate_stmt->bind_result($duplicate_count);
                    $check_duplicate_stmt->fetch();
                    $check_duplicate_stmt->close();

                    if ($duplicate_count > 0) {
                        $message = "Error: Department name '$new_name' already exists.";
                        $message_type = 'error';
                    } else {
                        // Update department (using prepared statement)
                        $update_stmt = $conn->prepare("UPDATE departments SET name = ? WHERE id = ?");
                        if ($update_stmt === false) {
                            error_log("manage_departments.php: Update prepare error: " . $conn->error);
                            $message = "Database error during update.";
                            $message_type = 'error';
                        } else {
                            $update_stmt->bind_param("si", $new_name, $edit_id);
                            if ($update_stmt->execute()) {
                                $message = "Department updated from '$old_name' to '$new_name' successfully!";
                                $message_type = 'success';
                                // Audit log
                                log_activity(
                                    $admin_id,
                                    $admin_name,
                                    'UPDATE_DEPARTMENT',
                                    "Changed department name from '$old_name' to '$new_name' (ID: $edit_id)",
                                    'departments',
                                    $edit_id,
                                    ['old_name' => $old_name],
                                    ['new_name' => $new_name]
                                );

                                // Also update staff records if they are using the old department name
                                // This assumes staff.department stores the department NAME, not ID.
                                // If it stores ID, this step is not needed.
                                $update_staff_dept_stmt = $conn->prepare("UPDATE staff SET department = ? WHERE department = ?");
                                if ($update_staff_dept_stmt === false) {
                                    error_log("manage_departments.php: Staff dept update prepare error: " . $conn->error);
                                } else {
                                    $update_staff_dept_stmt->bind_param("ss", $new_name, $old_name);
                                    $update_staff_dept_stmt->execute();
                                    $update_staff_dept_stmt->close();
                                }

                            } else {
                                error_log("manage_departments.php: Update execute error: " . $update_stmt->error);
                                $message = "Failed to update department: " . $update_stmt->error;
                                $message_type = 'error';
                            }
                            $update_stmt->close();
                        }
                    }
                }
            } else {
                $message = "Department to edit not found.";
                $message_type = 'error';
            }
        }
    } else {
        $message = "New department name cannot be empty.";
        $message_type = 'error';
    }
    // Redirect to clear POST data and show message
    header("Location: manage_departments.php?msg=" . urlencode($message) . "&type=" . urlencode($message_type));
    exit;
}

// Fetch messages from GET parameters after redirect
if (isset($_GET['msg']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['msg']);
    $message_type = htmlspecialchars($_GET['type']);
}

// Fetch departments for display
$departments_result = $conn->query("SELECT * FROM departments ORDER BY name ASC");
$departments = [];
if ($departments_result) {
    while ($row = $departments_result->fetch_assoc()) {
        $departments[] = $row;
    }
} else {
    error_log("manage_departments.php: Fetch departments error: " . $conn->error);
    $message = "Error fetching departments from database.";
    $message_type = 'error';
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Departments</title>
    <link rel="stylesheet" href="search1.css"> <style>
        /* General body styling for consistent background and font */
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(to bottom right, #D2BCA1, #D5B893); /* Akaroa to Tan gradient */
            display: flex;
            flex-direction: column; /* Allow content to stack vertically */
            justify-content: flex-start; /* Align content to the top */
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 40px 20px; /* Add top/bottom padding */
            box-sizing: border-box;
            color: #6F4D38; /* Coffee for general text */
        }

        .container {
            background-color: #ffffff; /* White background for the main content area */
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(111, 72, 28, 0.3); /* Walnut shadow */
            width: 100%;
            max-width: 800px; /* Wider container for the table */
            text-align: left;
            border: 1px solid #D5B893; /* Tan border */
            box-sizing: border-box;
            margin-bottom: 30px; /* Space before the back link */
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

        /* Message Box Styling */
        .message-box {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            text-align: center;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .message-success {
            background-color: #e6ffe6; /* Light green */
            color: #28a745; /* Green */
            border: 1px solid #28a745;
        }

        .message-error {
            background-color: #ffe6e6; /* Light red */
            color: #dc3545; /* Red */
            border: 1px solid #dc3545;
        }

        /* Table Styles */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 30px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            overflow: hidden; /* Ensures rounded corners are applied to table */
        }

        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #E0D1C4; /* Light border for rows */
            font-size: 0.95em;
        }

        th {
            background-color: #25344F; /* Space Cadet for table headers */
            color: white;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        tr:nth-child(even) {
            background-color: #F8F4F0; /* Very light tan for even rows */
        }

        tr:hover {
            background-color: #EEDDCC; /* Slightly darker tan on hover */
        }

        /* Action Buttons (Edit/Delete) */
        .action-buttons a {
            display: inline-block;
            padding: 8px 12px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: 600;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            margin-right: 5px; /* Space between buttons */
            font-size: 0.85em;
            text-align: center;
        }

        .edit-btn {
            background-color: #A76825; /* Desert */
            color: white;
        }

        .edit-btn:hover {
            background-color: #6F481C; /* Darker Desert */
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(167, 104, 37, 0.3);
        }

        .delete-btn {
            background-color: #dc3545; /* Red */
            color: white;
        }

        .delete-btn:hover {
            background-color: #c82333; /* Darker red */
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
        }

        /* Edit Form within Table */
        .edit-form-row input[type="text"] {
            width: calc(100% - 70px); /* Adjust width to fit button next to it */
            padding: 8px;
            border: 1px solid #D2BCA1;
            border-radius: 5px;
            box-sizing: border-box;
            font-size: 0.9em;
            margin-right: 5px;
        }

        .edit-form-row button {
            padding: 8px 12px;
            border: none;
            border-radius: 5px;
            background-color: #28a745; /* Green for Save */
            color: white;
            cursor: pointer;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }

        .edit-form-row button:hover {
            background-color: #218838; /* Darker Green */
        }

        /* Links outside table */
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

        .link-group .add-department-btn {
            background-color: #A76825; /* Desert for add department button */
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            display: inline-block;
            width: fit-content;
            margin: 0 auto; /* Center the block element */
            box-shadow: 0 3px 10px rgba(167, 104, 37, 0.2);
            text-decoration: none; /* Override default link styling */
        }

        .link-group .add-department-btn:hover {
            background-color: #7F715F; /* Sandstone on hover */
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(167, 104, 37, 0.3);
        }

        .link-group .back-to-staff {
            background-color: #273F5B; /* Space Cadet for back button */
            color: white;
            padding: 12px 25px;
            border-radius: 8px;
            display: inline-block;
            width: fit-content;
            margin: 0 auto;
            box-shadow: 0 3px 10px rgba(37, 52, 79, 0.2);
            text-decoration: none; /* Override default link styling */
        }

        .link-group .back-to-staff:hover {
            background-color: #617891; /* Rhino on hover */
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(37, 52, 79, 0.3);
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .container {
                padding: 30px;
                max-width: 95%;
            }
            h2 {
                font-size: 2em;
            }
            th, td {
                padding: 10px;
                font-size: 0.9em;
            }
            .action-buttons a {
                padding: 6px 10px;
                font-size: 0.8em;
                margin-right: 3px;
            }
            .edit-form-row input[type="text"] {
                width: calc(100% - 60px);
                padding: 6px;
            }
            .edit-form-row button {
                padding: 6px 10px;
            }
        }

        @media (max-width: 480px) {
            .container {
                padding: 20px;
            }
            h2 {
                font-size: 1.8em;
            }
            th, td {
                font-size: 0.85em;
                word-break: break-word; /* Helps with long names on small screens */
            }
            .action-buttons a {
                display: block; /* Stack buttons vertically on very small screens */
                margin-bottom: 5px;
                width: 100%;
            }
            .link-group a {
                font-size: 0.9em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Manage Departments</h2>

        <?php if (!empty($message)): ?>
            <div class="message-box message-<?= $message_type ?>">
                <?= $message ?>
            </div>
        <?php endif; ?>

        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Department Name</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($departments)): ?>
                    <?php foreach ($departments as $dept): ?>
                        <tr>
                            <td><?= htmlspecialchars($dept['id']) ?></td>
                            <td>
                                <span id="dept_name_<?= htmlspecialchars($dept['id']) ?>"><?= htmlspecialchars($dept['name']) ?></span>
                                <form method="POST" action="manage_departments.php" style="display: none;" id="edit_form_<?= htmlspecialchars($dept['id']) ?>" class="edit-form-row">
                                    <input type="hidden" name="edit_id" value="<?= htmlspecialchars($dept['id']) ?>">
                                    <input type="text" name="new_name" value="<?= htmlspecialchars($dept['name']) ?>" required>
                                    <button type="submit">Save</button>
                                    <button type="button" onclick="cancelEdit(<?= htmlspecialchars($dept['id']) ?>)">Cancel</button>
                                </form>
                            </td>
                            <td class="action-buttons">
                                <a href="#" class="edit-btn" onclick="toggleEdit(<?= htmlspecialchars($dept['id']) ?>); return false;">Edit</a>
                                <a href="manage_departments.php?delete=<?= htmlspecialchars($dept['id']) ?>" class="delete-btn" onclick="return confirm('Are you sure you want to delete department \'<?= htmlspecialchars($dept['name']) ?>\'? This cannot be undone if not assigned to staff.');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align: center;">No departments found.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="link-group">
            <a href="add_department.php" class="add-department-btn">Add New Department</a>
            <a href="staff.php" class="back-to-staff">← Back to Staff Page</a>
        </div>
    </div>

    <script>
        function toggleEdit(id) {
            const displaySpan = document.getElementById('dept_name_' + id);
            const editForm = document.getElementById('edit_form_' + id);
            const editButton = document.querySelector('#dept_name_' + id).closest('td').querySelector('.edit-btn');
            const deleteButton = document.querySelector('#dept_name_' + id).closest('td').querySelector('.delete-btn');

            if (displaySpan.style.display !== 'none') {
                displaySpan.style.display = 'none';
                editForm.style.display = 'flex'; // Use flex for horizontal alignment of input/buttons
                editButton.style.display = 'none'; // Hide edit button
                deleteButton.style.display = 'none'; // Hide delete button
            } else {
                displaySpan.style.display = 'inline';
                editForm.style.display = 'none';
                editButton.style.display = 'inline-block'; // Show edit button
                deleteButton.style.display = 'inline-block'; // Show delete button
            }
        }

        function cancelEdit(id) {
            const displaySpan = document.getElementById('dept_name_' + id);
            const editForm = document.getElementById('edit_form_' + id);
            const editButton = document.querySelector('#dept_name_' + id).closest('td').querySelector('.edit-btn');
            const deleteButton = document.querySelector('#dept_name_' + id).closest('td').querySelector('.delete-btn');

            displaySpan.style.display = 'inline';
            editForm.style.display = 'none';
            editButton.style.display = 'inline-block';
            deleteButton.style.display = 'inline-block';
            // Reset the input field to its original value if needed (optional)
            editForm.querySelector('input[name="new_name"]').value = displaySpan.textContent;
        }
    </script>
</body>
</html>