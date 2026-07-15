<?php
session_start();
require 'connect.php';
require 'includes/audit_logger.php';

// ✅ Prevent cached access after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ✅ Auto logout after 1 hour of inactivity
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: loginpage.php?message=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();

// ✅ Require admin login
if (!isset($_SESSION['admin_username']) || $_SESSION['role'] !== 'admin') {
    header("Location: loginpage.php?error=unauthorized");
    exit();
}

$equipment_id = $_GET['id'] ?? 0;
$equipment_type = $_GET['type'] ?? '';

$valid_tables = [
    'laptop', 'desktop', 'scanner', 'printer',
    'router', 'tablet', 'ups', 'firewall',
    'network_switches', 'wireless_access_point', 'projector', 'cctv', 'ip_phone'
];

if (!in_array($equipment_type, $valid_tables)) {
    die("Invalid equipment type.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $reason = $_POST['reason'] ?? '';
    $admin = $_SESSION['admin_username'];

    if (empty($reason)) {
        die("Reason is required.");
    }

    // Step 1: Fetch current equipment info before deletion
    $stmt = $conn->prepare("SELECT * FROM `$equipment_type` WHERE id = ?");
    $stmt->bind_param("i", $equipment_id);
    $stmt->execute();
    $equipment = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$equipment) {
        die("Equipment not found.");
    }

    $serial_no = $equipment['serial_no'] ?? 'N/A';
    $brand = $equipment['brand'] ?? '';
    $model = $equipment['model'] ?? '';
    $assigned_to = $equipment['assigned_to'] ?? null;

    // Step 2: Get full name + designation of previous owner
    $owner_stmt = $conn->prepare("
        SELECT CONCAT(First_Names, ' ', Last_Name, ' (', designation, ')') AS full_name
        FROM register WHERE email = ?
        UNION
        SELECT CONCAT(First_Names, ' ', Last_Name, ' (', designation, ')') AS full_name
        FROM staff WHERE email = ?
    ");
    $owner_stmt->bind_param("ss", $assigned_to, $assigned_to);
    $owner_stmt->execute();
    $res = $owner_stmt->get_result();
    $previous_owner = $res->fetch_assoc()['full_name'] ?? 'Unknown';
    $owner_stmt->close();

    // ✅ Step 3: Insert into disposed_equipment INCLUDING serial number
    $insert = $conn->prepare("INSERT INTO disposed_equipment (equipment_type, equipment_id, serial_no, reason, disposed_by) VALUES (?, ?, ?, ?, ?)");
    $insert->bind_param("sssss", $equipment_type, $equipment_id, $serial_no, $reason, $admin);

    $insert->execute();
    $insert->close();

    // Step 4: Delete from original table
    $delete = $conn->prepare("DELETE FROM `$equipment_type` WHERE id = ?");
    $delete->bind_param("i", $equipment_id);
    $delete->execute();
    $delete->close();

    // Step 5: Audit log with full description
    $equipment_name = "$brand $model";
    $desc = "Disposed $equipment_type: $equipment_name (Serial: $serial_no) previously assigned to $previous_owner";

    log_activity(
        $_SESSION['admin_id'],
        $_SESSION['admin_username'],
        'DISPOSED',
        $desc,
        $equipment_type,
        $equipment_id,
        $equipment, // old data
        null        // new data (none after disposal)
    );

    // Step 6: Redirect
    header("Location: admin_dashboard.php?disposed=success");
    exit();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dispose Equipment</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to bottom right, #D2BCA1, #D5B893); /* Akaroa to Tan gradient */
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            color: #6F4D38; /* Coffee for general text */
        }

        form {
            background: #ffffff; /* White background for the form */
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(111, 72, 28, 0.3); /* Walnut shadow */
            width: 100%;
            max-width: 500px;
            box-sizing: border-box;
            text-align: center;
        }

        h2 {
            color: #632024; /* Caput Mortuum for heading */
            margin-top: 0;
            margin-bottom: 25px;
            font-size: 36px;
            letter-spacing: 1px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
        }

        p {
            font-size: 18px;
            margin-bottom: 25px;
            line-height: 1.5;
            color: #6F4D38; /* Coffee */
        }

        textarea {
            width: calc(100% - 30px); /* Adjust for padding */
            padding: 15px;
            margin-bottom: 25px;
            border: 2px solid #D2BCA1; /* Akaroa border */
            border-radius: 10px;
            font-size: 16px;
            color: #25344F; /* Space Cadet for input text */
            background-color: #FDFDFD; /* Near white background */
            resize: vertical; /* Allow vertical resizing */
            min-height: 120px; /* Minimum height for textarea */
            box-sizing: border-box;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }

        textarea:focus {
            outline: none;
            border-color: #A76825; /* Desert on focus */
            box-shadow: 0 0 0 4px rgba(167, 104, 37, 0.2); /* Desert shadow on focus */
        }

        button[type="submit"] {
            background-color: #632024; /* Caput Mortuum for button */
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            box-shadow: 0 4px 15px rgba(99, 32, 36, 0.2); /* Caput Mortuum shadow */
        }

        button[type="submit"]:hover {
            background-color: #A76825; /* Desert on hover */
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(167, 104, 37, 0.3); /* Desert shadow on hover */
        }

        /* Responsive adjustments */
        @media (max-width: 600px) {
            form {
                padding: 25px;
                margin: 20px;
            }
            h2 {
                font-size: 30px;
            }
            p {
                font-size: 16px;
            }
            textarea {
                padding: 10px;
                min-height: 100px;
            }
            button[type="submit"] {
                padding: 12px 25px;
                font-size: 18px;
            }
        }
    </style>
</head>
<body>

   <form method="post" style="max-width: 500px; margin: 0 auto; padding: 20px; background: #f9f9f9; border-radius: 8px;">
    <h2 style="text-align: center;">Dispose Equipment</h2>
    
    <p style="font-weight: bold;">Why are you disposing this equipment?</p>
    
    <select name="reason" required style="width: 100%; padding: 10px; border-radius: 5px; border: 1px solid #ccc;">
        <option value="">-- Select a Reason --</option>
        <option value="Damaged beyond repair">Damaged beyond repair</option>
        <option value="Obsolete">Obsolete</option>
        <option value="Lost">Lost</option>
        <option value="Stolen">Stolen</option>
        <option value="Replaced by new equipment">Replaced by new equipment</option>
        <option value="Decommissioned">Decommissioned</option>
        <option value="Other">Other</option>
    </select>
    
    <br><br>
    <button type="submit" style="width: 100%; background-color: #8b0000; color: white; padding: 10px 20px; border: none; border-radius: 5px; font-weight: bold;">
        Confirm Dispose
    </button>
</form>

</body>
</html>