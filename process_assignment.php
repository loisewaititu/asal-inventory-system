<?php
include 'connect.php';
session_start();
require_once 'includes/audit_logger.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    exit("Invalid request method.");
}

// Get equipment type and ID — prioritize POST, fallback to GET
$equipment_type = $_POST['equipment_type'] ?? ($_GET['type'] ?? '');
$equipment_id = intval($_POST['equipment_id'] ?? ($_GET['id'] ?? 0));
$new_email = $_POST['new_owner'] ?? '';
$reason = $_POST['reason_for_change'] ?? '';

// Validate inputs
if (empty($equipment_type) || !$equipment_id) {
    exit("Invalid or missing equipment selection.");
}
if (empty($new_email)) {
    exit("Invalid new user.");
}
if (empty($reason)) {
    exit("Please provide a reason.");
}

// Fetch previous owner
$previous_email = null;
$stmt = $conn->prepare("SELECT assigned_to FROM `$equipment_type` WHERE id = ?");
$stmt->bind_param("i", $equipment_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $previous_email = $row['assigned_to'];
} else {
    exit("Equipment not found.");
}
$stmt->close();

// Update assignment
$update = $conn->prepare("UPDATE `$equipment_type` SET assigned_to = ? WHERE id = ?");
$update->bind_param("si", $new_email, $equipment_id);
$update->execute();
$update->close();

// Get user IDs
$prev_id = null;
$curr_id = null;

if (!empty($previous_email)) {
    $q1 = $conn->prepare("SELECT id FROM register WHERE email = ?");
    $q1->bind_param("s", $previous_email);
    $q1->execute();
    $res1 = $q1->get_result();
    if ($res1 && $res1->num_rows > 0) {
        $prev_id = $res1->fetch_assoc()['id'];
    }
    $q1->close();
}

$q2 = $conn->prepare("SELECT id FROM register WHERE email = ?");
$q2->bind_param("s", $new_email);
$q2->execute();
$res2 = $q2->get_result();
if ($res2 && $res2->num_rows > 0) {
    $curr_id = $res2->fetch_assoc()['id'];
} else {
    exit("New user not found in system.");
}
$q2->close();

// Log to equipment_ownership
$log = $conn->prepare("INSERT INTO equipment_ownership (equipment_type, equipment_id, previous_user_id, current_user_id, change_reason, change_date) VALUES (?, ?, ?, ?, ?, NOW())");
$log->bind_param("siiis", $equipment_type, $equipment_id, $prev_id, $curr_id, $reason);

$log->execute();
$log->close();

// Audit log
log_audit("Equipment Reassigned", "Equipment ID $equipment_id ($equipment_type) reassigned from $previous_email to $new_email. Reason: $reason");

// Redirect back with success message
echo "<script>
    alert('Equipment reassigned successfully.');
    window.location.href = 'admin_dashboard.php?type=" . urlencode($equipment_type) . "&id=" . urlencode($equipment_id) . "';
</script>";
exit;
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reassign Equipment</title>
    <style>
    body {
        font-family: 'Segoe UI', sans-serif;
        background: linear-gradient(to right, #D2BCA1, #D5B893);
        color: #6F4D38;
        padding: 40px;
        display: flex;
        justify-content: center;
        align-items: center;
        min-height: 100vh;
        box-sizing: border-box;
    }

    h2 {
        text-align: center;
        color: #25344F;
        margin-bottom: 35px;
        font-size: 36px;
        letter-spacing: 1.5px;
        text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
    }

    form {
        background-color: rgba(210, 188, 161, 0.95);
        max-width: 550px;
        width: 100%;
        padding: 40px;
        border-radius: 20px;
        box-shadow: 0 12px 25px rgba(111, 72, 28, 0.35);
        border: 1px solid rgba(111, 77, 56, 0.1);
        box-sizing: border-box;
    }

    label, select, button {
        display: block;
        width: 100%;
        margin-bottom: 25px;
        box-sizing: border-box;
    }

    label {
        color: #6F4D38;
        font-weight: 600;
        font-size: 17px;
        margin-bottom: 8px;
    }

    select, button {
        padding: 15px;
        border-radius: 10px;
        border: 2px solid #617891;
        background: #FDFDFD;
        color: #25344F;
        font-size: 16px;
        transition: all 0.3s ease;
    }

    select:focus {
        outline: none;
        border-color: #A76825;
        box-shadow: 0 0 0 4px rgba(167, 104, 37, 0.2);
    }

    button {
        background-color: #A76825;
        color: white;
        font-weight: bold;
        cursor: pointer;
        font-size: 18px;
        letter-spacing: 0.5px;
        transition: all 0.3s ease;
        margin-top: 20px;
    }

    button:hover {
        background-color: #6F481C;
        transform: translateY(-3px);
        box-shadow: 0 8px 15px rgba(0, 0, 0, 0.2);
    }

    a {
        padding: 12px 25px;
        background-color: #273F5B;
        color: white;
        text-decoration: none;
        border-radius: 8px;
        display: inline-block;
        margin-top: 30px;
        font-weight: 600;
        font-size: 16px;
        transition: all 0.3s ease;
        text-align: center;
    }

    a:hover {
        background-color: #617891;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
    }

    @media (max-width: 600px) {
        body {
            padding: 20px;
        }
        h2 {
            font-size: 30px;
            margin-bottom: 25px;
        }
        form {
            padding: 30px;
            border-radius: 15px;
        }
        label {
            font-size: 16px;
        }
        select, button {
            padding: 12px;
            font-size: 15px;
        }
        button {
            font-size: 17px;
        }
        a {
            padding: 10px 20px;
            font-size: 15px;
        }
    }
    </style>
</head>
<body>

<h2>Reassign Equipment</h2>

<form method="POST" action="process_assignment.php">
    <label for="equipment_id">Equipment:</label>
    <select name="equipment_id" id="equipment_id" required>
        <?php while ($eq = $equipments->fetch_assoc()):
            $value = "{$eq['equipment_type']}|{$eq['id']}";
            $display = ucfirst($eq['equipment_type']) . " - {$eq['brand']} {$eq['model']} ({$eq['serial_no']})";
            echo "<option value='$value'>$display</option>";
        endwhile; ?>
    </select>

    <label for="new_id">Reassign To:</label>
    <select name="new_id" required>
    <?php while ($user = $users->fetch_assoc()): ?>
        <option value="<?= htmlspecialchars($user['email']) ?>">
            <?= htmlspecialchars("{$user['First_Names']} ({$user['designation']})") ?>
        </option>
    <?php endwhile; ?>
</select>

    <label for="reason">Reason for Change:</label>
    <select name="reason" id="reason" required>
        <option value="Transferred">Transferred</option>
        <option value="Leave">Leave</option>
        <option value="Retired">Retired</option>
    </select>

    <button type="submit">Set Owner</button>
</form>

<a href="admin_dashboard.php">⬅ Go Back to Dashboard</a>

</body>
</html>
