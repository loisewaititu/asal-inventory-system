<?php 
session_start();
require 'connect.php';
require 'includes/audit_logger.php';

// ✅ Prevent cached pages from being accessed after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ✅ Check admin login
if (!isset($_SESSION['admin_username']) || $_SESSION['role'] !== 'admin') {
    header("Location: loginpage.php?error=unauthorized");
    exit();
}

// ✅ Session timeout (1 hour)
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 3600) {
    session_unset();
    session_destroy();
    header("Location: loginpage.php?message=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();

$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';
$equipment_type = $_GET['type'] ?? ($_POST['equipment_type'] ?? null);
$equipment_id = $_GET['id'] ?? ($_POST['equipment_id'] ?? null);


$equipment = null;
$preselected = null;
$current_owner_name = null;

// Helper function to get user ID from email
function getUserIdByEmail($conn, $email) {
    $stmt = $conn->prepare("SELECT id FROM register WHERE email = ? UNION SELECT id FROM staff WHERE email = ?");
    $stmt->bind_param("ss", $email, $email);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    return $row ? $row['id'] : null;
}

// ✅ Fetch equipment if type and ID are valid
if ($equipment_type && $equipment_id) {
    $stmt = $conn->prepare("SELECT id, brand, model, serial_no, assigned_to FROM `$equipment_type` WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("i", $equipment_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $equipment = $row; // ✅ Set the $equipment array
            $preselected = $row;

            $email = $row['assigned_to'];
            $stmtName = $conn->prepare("
                SELECT CONCAT(First_Names, ' (', designation, ')') AS owner_display FROM register WHERE email = ? 
                UNION 
                SELECT CONCAT(First_Names, ' (', designation, ')') AS owner_display FROM staff WHERE email = ?
            ");
            $stmtName->bind_param("ss", $email, $email);
            $stmtName->execute();
            $resName = $stmtName->get_result();
            if ($owner = $resName->fetch_assoc()) {
                $current_owner_name = $owner['owner_display'];
            }
            $stmtName->close();
        } else {
            // Equipment not found
            header("Location: dashboard.php?error=Invalid equipment ID");
            exit();
        }
        $stmt->close();
    } else {
        // Invalid equipment table
        header("Location: dashboard.php?error=Invalid equipment type");
        exit();
    }
} else {
    // Missing required URL parameters
    header("Location: dashboard.php?error=Missing equipment selection");
    exit();
}

// ✅ Handle assignment form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_owner'])) {
    $new_owner = $_POST['new_owner'];
    $reason = $_POST['reason'] ?? 'Reassigned';

    $old_data = $conn->query("SELECT * FROM `$equipment_type` WHERE id = $equipment_id")->fetch_assoc();

    $update_stmt = $conn->prepare("UPDATE `$equipment_type` SET assigned_to = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_owner, $equipment_id);

    if ($update_stmt->execute()) {
        $new_data = $conn->query("SELECT * FROM `$equipment_type` WHERE id = $equipment_id")->fetch_assoc();
        $serial = $new_data['serial_no'] ?? 'unknown';

        $owner_display = $conn->prepare("
            SELECT CONCAT(First_Names, ' (', designation, ')') AS display_name FROM register WHERE email = ? 
            UNION 
            SELECT CONCAT(First_Names, ' (', designation, ')') AS display_name FROM staff WHERE email = ?
        ");
        $owner_display->bind_param("ss", $new_owner, $new_owner);
        $owner_display->execute();
        $owner_result = $owner_display->get_result();
        $new_owner_display = $owner_result->fetch_assoc()['display_name'] ?? $new_owner;

        // 🟢 Log description
        $desc = "Assigned $equipment_type (Serial: $serial) to $new_owner_display";

        log_activity(
            $current_admin_id,
            $current_admin_name,
            'EQUIPMENT_ASSIGNED',
            $desc,
            $equipment_type,
            $equipment_id,
            $old_data,
            $new_data
        );

        // ✅ Save to equipment_ownership
        $previous_email = $old_data['assigned_to'];
        $previous_user_id = getUserIdByEmail($conn, $previous_email);
        $current_user_id = getUserIdByEmail($conn, $new_owner);

        if ($previous_user_id !== null && $current_user_id !== null) {
            $insertHistory = $conn->prepare("INSERT INTO equipment_ownership (equipment_type, equipment_id, previous_user_id, current_user_id, change_reason) VALUES (?, ?, ?, ?, ?)");
            $insertHistory->bind_param("siiis", $equipment_type, $equipment_id, $previous_user_id, $current_user_id, $reason);
            $insertHistory->execute();
            $insertHistory->close();
        } else {
            error_log("⚠ Ownership history not saved: Missing user ID(s) for equipment ID $equipment_id.");
        }

        header("Location: assign_equipment.php?type=$equipment_type&id=$equipment_id&reassigned=success");
        exit();
    } else {
        error_log("Assignment update failed: " . $conn->error);
    }

    $update_stmt->close();
}

// ✅ Load users for the assignment dropdown
$users_sql = "SELECT email, First_Names, designation FROM register 
              UNION 
              SELECT email, First_Names, designation FROM staff";
$users = $conn->query($users_sql);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Reassign Equipment</title>
    <style>
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

        form {
            background-color: #ffffff;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(111, 72, 28, 0.3); /* Walnut shadow for depth */
            width: 100%;
            max-width: 500px;
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
            text-align: center; /* Center the title */
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
            border-radius: 55px; /* Rounded ends for the underline */
        }

        .form-row {
            display: flex;
            align-items: center; /* Align label and input vertically */
            margin-bottom: 20px;
        }

        .form-row label {
            flex-basis: 120px; /* Fixed width for labels */
            margin-right: 15px;
            font-weight: 600;
            color: #25344F; /* Space Cadet */
            font-size: 1em;
            text-align: left; /* Ensure label text is left-aligned */
        }

        .form-row .input-wrapper {
            flex-grow: 1; /* Input takes remaining space */
        }

        input[type="text"],
        select {
            width: 100%; /* Fill the container */
            padding: 12px 10px;
            border: 2px solid #D2BCA1; /* Akaroa border */
            border-radius: 8px;
            font-size: 1em;
            color: #6F4D38; /* Coffee */
            background-color: #fcfcfc;
            box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.08);
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box; /* Include padding and border in the element's total width and height */
        }

        input[type="text"][readonly] {
            font-weight: normal;
            color: #6F4D38;
            background-color: #f0f0f0; /* Light gray background for readonly */
            cursor: default;
        }

        select {
            appearance: none; /* Remove default arrow */
            -webkit-appearance: none;
            -moz-appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%236F4D38%22%20d%3D%22M287%2069.4a17.6%2017.6%200%200%200-13%205.4L146.2%20208.6%2018.8%2074.8c-2.4-2.4-5.6-4.2-9-4.8-3.4-.6-7.2-.2-10.4%201.4-1.2%200-2.4.6-3.6%201.2-1.2.6-2.4%201.2-3.6%202.4-4.2%204.2-6%209.8-6%2015.4s1.8%2011.2%206%2015.4L133.2%20227.6c5.2%205.2%2011.8%207.8%2018.8%207.8s13.6-2.6%2018.8-7.8L287%20101.4c4.2-4.2%206-9.8%206-15.4s-1.8-11.2-6-15.4z%22%2F%3E%3C%2Fsvg%3E'); /* Custom arrow */
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 12px;
        }

        input[type="text"]:focus,
        select:focus {
            border-color: #A76825; /* Desert on focus */
            box-shadow: 0 0 0 3px rgba(167, 104, 37, 0.2); /* Desert focus glow */
            outline: none;
        }

        .button-group {
            display: flex;
            flex-direction: column; /* Stack buttons vertically */
            align-items: center; /* Center buttons horizontally within the column */
            gap: 15px; /* Space between buttons */
            margin-top: 30px;
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

        .go-back-btn {
            background-color: #A76825; /* Desert for back button */
            color: white;
            padding: 14px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1.1em;
            cursor: pointer;
            text-decoration: none; /* For the <a> tag */
            display: inline-block; /* For the <a> tag */
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            font-weight: 600;
            letter-spacing: 0.5px;
            width: fit-content; /* Make button width fit its content */
        }

        .go-back-btn:hover {
            background-color: #7F715F; /* Sandstone on hover */
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(167, 104, 37, 0.3); /* Desert shadow on hover */
        }

        .go-back-btn:active {
            transform: translateY(0);
            box-shadow: 0 2px 8px rgba(167, 104, 37, 0.4);
        }

        .invalid-selection-message {
            background-color: rgba(255, 255, 255, 0.9);
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(111, 72, 28, 0.3);
            text-align: center;
            width: 100%;
            max-width: 400px;
            border: 1px solid #D5B893;
        }

        .invalid-selection-message h3 {
            color: #632024; /* Caput Mortuum for error heading */
            font-size: 1.8em;
            margin-bottom: 20px;
        }

        .invalid-selection-message a {
            display: inline-block;
            margin-top: 20px;
            color: #A76825; /* Desert for link */
            text-decoration: none;
            font-weight: 600;
            padding: 8px 15px;
            border: 1px solid #A76825;
            border-radius: 5px;
            transition: background-color 0.3s ease, color 0.3s ease;
        }

        .invalid-selection-message a:hover {
            background-color: #A76825;
            color: white;
        }

        /* Responsive adjustments */
        @media (max-width: 600px) {
            form {
                padding: 25px;
                margin: 0 15px;
            }
            h2 {
                font-size: 2em;
                margin-bottom: 20px;
            }
            .form-row {
                flex-direction: column; /* Stack label and input on small screens */
                align-items: flex-start; /* Align stacked items to the left */
            }
            .form-row label {
                flex-basis: auto; /* Remove fixed width */
                margin-right: 0;
                margin-bottom: 5px; /* Space between label and input */
            }
            input[type="text"],
            select {
                padding: 10px;
                font-size: 0.95em;
            }
            .button-group {
                margin-top: 20px;
            }
            button[type="submit"],
            .go-back-btn {
                padding: 12px 20px;
                font-size: 1em;
                width: 100%; /* Full width on smaller screens */
            }
        }
    </style>
</head>
<body>

<?php if ($preselected): ?>
    <form method="post" action="process_assignment.php">
        <input type="hidden" name="equipment_type" value="<?= htmlspecialchars($_GET['type'] ?? '') ?>">
        <input type="hidden" name="equipment_id" value="<?= htmlspecialchars($_GET['id'] ?? '') ?>">

    <h2>Reassign Equipment</h2>

    <div class="form-row">
        <label>Equipment:</label>
        <div class="input-wrapper">
            <input type="text" value="<?= htmlspecialchars(ucfirst($_GET['type'] ?? '')) ?> <?= htmlspecialchars($equipment['brand'] ?? '') ?> <?= htmlspecialchars($equipment['model'] ?? '') ?> (<?= htmlspecialchars($equipment['serial_no'] ?? '') ?>)" readonly>
        </div>
    </div>

    <div class="form-row">
        <label>Current Owner:</label>
        <div class="input-wrapper">
            <input type="text" value="<?= $current_owner_name ?>" readonly>
        </div>
    </div>

    <div class="form-row">
        <label for="new_owner">Reassign To:</label>
        <div class="input-wrapper">
            <select name="new_owner" id="new_owner" required>
                <option value="">-- Select User --</option>
                <?php while ($user = $users->fetch_assoc()): ?>
                    <option value="<?= htmlspecialchars($user['email']) ?>">
                        <?= htmlspecialchars($user['First_Names']) ?> (<?= htmlspecialchars($user['designation']) ?>)
                    </option>
                <?php endwhile; ?>
            </select>
        </div>
    </div>

    <div class="form-row">
        <label for="reason_for_change">Reason for Change:</label>
        <div class="input-wrapper">
            <select name="reason_for_change" id="reason_for_change" required>
                <option value="">-- Select Reason --</option>
                <option value="Transferred">Transferred</option>
                <option value="New Assignment">New Assignment</option>
                <option value="Re-allocation">Re-allocation</option>
                <option value="Leave">Leave</option>
                <option value="Retirement">Retirement</option>
            </select>
        </div>
    </div>

    <div class="button-group">
        <button type="submit">Set Owner</button>
        <a href="admin_dashboard.php" class="go-back-btn">← Go Back to Dashboard</a>
    </div>
</form>

<?php else: ?>
    <div class="invalid-selection-message">
        <h3>Invalid or missing equipment selection.</h3>
        <a href="admin_dashboard.php">← Back to Dashboard</a>
    </div>
<?php endif; ?>

</body>
</html>