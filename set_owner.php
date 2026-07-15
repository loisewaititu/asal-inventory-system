<?php
session_start();
require_once 'connect.php'; // DB connection
require_once 'includes/audit_logger.php'; // Audit trail

// Prevent cached access after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Current admin details
$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

// Ensure user is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: loginpage.php?error=unauthorized");
    exit();
}

// Session timeout (1 hour)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: loginpage.php?message=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();

include 'connect.php';

// Validate incoming GET params
if (!isset($_GET['equipment_id']) || !isset($_GET['type'])) {
    die("Invalid equipment data.");
}

$equipment_id = intval($_GET['equipment_id']);
$equipment_type = $_GET['type'];

// Validate allowed types
$allowed_types = ['desktop', 'mouse', 'keyboard', 'ups', 'cpu', 'scanner', 'printer', 'paper_shredder'];
if (!in_array($equipment_type, $allowed_types)) {
    die("Invalid equipment type.");
}

// Fetch all users
$users = $conn->query("SELECT id, username, email FROM register");

// Fetch serial number to show
$serial_no = 'Unknown';
$stmt = $conn->prepare("SELECT serial_no FROM `$equipment_type` WHERE id = ?");
$stmt->bind_param("i", $equipment_id);
$stmt->execute();
$res = $stmt->get_result();
if ($res->num_rows > 0) {
    $serial_no = $res->fetch_assoc()['serial_no'];
}
$stmt->close();
?>


<!DOCTYPE html>
<html>
<head>
    <title>Reassign Equipment Owner</title>
    <style>
    body {
        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        /* Replaced light pink/purple gradient with Akaroa to Tan for a warm and inviting background */
        background: linear-gradient(to right, #D2BCA1, #D5B893); /* Akaroa, Tan */
        padding: 60px;
        text-align: center;
        /* Default body text color to Coffee */
        color: #6F4D38; /* Coffee */
    }

    form {
        background-color: white; /* Clean white background for the form */
        padding: 40px; /* More generous padding */
        margin: auto;
        max-width: 500px; /* Slightly increased max-width */
        border-radius: 15px; /* More rounded corners */
        /* Replaced existing shadow with a richer Walnut shadow */
        box-shadow: 0 8px 25px rgba(111, 72, 28, 0.25); /* Walnut shadow */
        box-sizing: border-box; /* Include padding in width */
    }

    label, select, button {
        width: 100%;
        margin-bottom: 20px; /* More space between elements */
        font-size: 16px;
        box-sizing: border-box; /* Ensures padding/border are included in width */
    }

    /* Style for labels */
    label {
        display: block; /* Ensure label takes full width */
        text-align: left; /* Align label text to the left */
        font-weight: bold;
        color: #25344F; /* Space Cadet for labels */
        margin-bottom: 8px; /* Space between label and input */
    }

    /* Style for select dropdowns */
    select {
        padding: 12px; /* More padding */
        /* Border using Slate Gray */
        border: 1px solid #617891; /* Slate Gray */
        border-radius: 8px; /* More rounded select field */
        background-color: rgba(210, 188, 161, 0.1); /* Light Akaroa background */
        color: #6F4D38; /* Coffee for select text */
        appearance: none; /* Remove default arrow */
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: url('data:image/svg+xml;utf8,<svg fill="%236F4D38" height="24" viewBox="0 0 24 24" width="24" xmlns="http://www.w3.org/2000/svg"><path d="M7 10l5 5 5-5z"/><path d="M0 0h24v24H0z" fill="none"/></svg>'); /* Custom arrow */
        background-repeat: no-repeat;
        background-position: right 10px center;
        background-size: 20px;
        cursor: pointer;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
    }

    select:focus {
        outline: none;
        /* Focus highlight using Desert accent */
        border-color: #A76825; /* Desert */
        box-shadow: 0 0 0 3px rgba(167, 104, 37, 0.2); /* Desert shadow for focus */
    }

    h2 {
        /* Replaced #6a1b9a with Space Cadet for a strong heading */
        color: #25344F; /* Space Cadet */
        text-align: center;
        margin-bottom: 30px;
        font-size: 36px; /* Larger heading */
        letter-spacing: 1px;
    }

    button {
        /* Replaced #ab47bc with Desert for a vibrant call to action */
        background-color: #A76825; /* Desert */
        color: white;
        border: none;
        padding: 14px 25px; /* More generous padding */
        cursor: pointer;
        border-radius: 8px; /* More rounded button */
        font-weight: bold; /* Make button text bold */
        font-size: 18px; /* Larger font size */
        transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
        display: block; /* Ensure button takes full width */
        margin: 0 auto; /* Center the button */
        max-width: 250px; /* Limit button width */
    }

    button:hover {
        /* Replaced #8e24aa with Walnut for a rich hover effect */
        background-color: #6F481C; /* Walnut */
        transform: translateY(-2px); /* Slight lift effect */
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2); /* Deeper shadow on hover */
    }

    /* Responsive Adjustments */
    @media screen and (max-width: 768px) {
        body {
            padding: 40px;
        }
        form {
            padding: 30px;
            border-radius: 12px;
        }
        h2 {
            font-size: 30px;
            margin-bottom: 25px;
        }
        select, button {
            padding: 12px;
            font-size: 15px;
        }
    }

    @media screen and (max-width: 480px) {
        body {
            padding: 20px;
        }
        form {
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(111, 72, 28, 0.15); /* Lighter shadow on small screens */
        }
        h2 {
            font-size: 26px;
            margin-bottom: 20px;
        }
        label, select, button {
            margin-bottom: 15px;
            font-size: 14px;
        }
        select {
            padding: 10px;
        }
        button {
            padding: 10px;
            font-size: 16px;
            max-width: 100%;
        }
    }
</style>
</head>
<body>

<h2>Reassign Owner</h2>
<p><strong>Equipment:</strong> <?= htmlspecialchars(ucfirst($equipment_type)) ?><br>
   <strong>Serial Number:</strong> <?= htmlspecialchars($serial_no) ?></p>

<form method="POST" action="save_owner.php">
    <input type="hidden" name="equipment_id" value="<?= $equipment_id ?>">
    <input type="hidden" name="equipment_type" value="<?= htmlspecialchars($equipment_type) ?>">

    <label for="id">Select User:</label>
    <select name="id" id="id" required>
        <option value="" disabled selected>Select user...</option>
        <?php while ($user = $users->fetch_assoc()): ?>
            <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['username']) ?> (<?= htmlspecialchars($user['email']) ?>)</option>
        <?php endwhile; ?>
    </select>

    <label for="reason">Reason for Change:</label>
    <input type="text" name="reason" id="reason" required placeholder="e.g., Transferred to new department">

    <button type="submit">Reassign Owner</button>
</form>
<a href="admin_dashboard.php" style="
        display: inline-block;
        background-color: #6a1b9a;
        color: white;
        padding: 10px 20px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
    ">
        ← Back to Dashboard
    </a>
</body>
</html>

