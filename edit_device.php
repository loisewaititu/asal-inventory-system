<?php
$mysqli = new mysqli("localhost", "root", "", "jl_tracking_system");

session_start();
require_once 'connect.php'; // your DB connection file
require_once 'includes/audit_logger.php';

$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

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

// ✅ Database connection check
if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}


$equipment_type = $_GET['equipment_type'] ?? ($_POST['equipment_type'] ?? '');
$id = intval($_GET['id'] ?? $_POST['id'] ?? 0);

$valid_equipment = [
    'desktop',
    'laptop',
    'tablet',
    'ups',
    'wireless_access_point',
    'printer',
    'scanner',
    'projector',
    'network_switches',
    'cctv',
    'firewall',
    'ip_phone',
    'router'
];

if (!in_array($equipment_type, $valid_equipment)) {
    die("Invalid equipment type.");
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // STEP 1: Get old data
    $old = $mysqli->query("SELECT * FROM `$equipment_type` WHERE id = $id")->fetch_assoc();

    // STEP 2: Build update query dynamically
    $fields = [];
    $params = [];
    $types = '';

    foreach ($_POST as $key => $value) {
        if (in_array($key, ['id', 'equipment_type'])) continue;
        $fields[] = "`$key` = ?";
        $params[] = $value;
        $types .= 's';
    }

    $params[] = $id;
    $types .= 'i';

    $sql = "UPDATE `$equipment_type` SET " . implode(', ', $fields) . " WHERE id = ?";
    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    // STEP 3: Get new data
    $new = $mysqli->query("SELECT * FROM `$equipment_type` WHERE id = $id")->fetch_assoc();

    // STEP 4: Log the update
    log_activity(
        $current_admin_id,
        $current_admin_name,
        'EQUIPMENT_UPDATED',
        "Updated equipment (ID: $id, Type: $equipment_type)",
        'equipment',
        $id,
        $old,
        $new
    );

    // Redirect or show message
    header("Location: admin_dashboard.php?equipment_type=$equipment_type&id=$id&updated=1");
    exit();
}

// If GET, load forms
$query = "SELECT * FROM `$equipment_type` WHERE id = ?";
$stmt = $mysqli->prepare($query);

if (!$stmt) {
    die("Prepare failed: " . $mysqli->error);
}

$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("Device not found.");
}

$device = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit <?php echo ucfirst($equipment_type); ?> Details</title>
    <style>
    body {
        background: linear-gradient(to right, #D2BCA1, #D5B893);
        font-family: 'Segoe UI', sans-serif;
        margin: 0;
        height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        color: #6F4D38;
    }

    h2 {
        color: #25344F;
        text-align: center;
        margin-bottom: 25px;
        font-size: 32px;
    }

    form {
        background-color: rgba(255, 255, 255, 0.9);
        padding: 30px;
        border-radius: 12px;
        max-width: 600px;
        width: 100%;
        max-height: 85vh;
        overflow-y: auto;
        box-shadow: 0px 8px 30px rgba(111, 72, 28, 0.25);
        box-sizing: border-box;
    }

    input[type="text"] {
        width: calc(100% - 24px);
        padding: 12px;
        margin: 8px 0 20px 0;
        border: 1px solid #617891;
        border-radius: 8px;
        font-size: 16px;
        color: #25344F;
        transition: border-color 0.3s ease, box-shadow 0.3s ease;
        box-sizing: border-box;
    }

    input[type="text"]:focus {
        outline: none;
        border-color: #A76825;
        box-shadow: 0 0 0 3px rgba(167, 104, 37, 0.2);
    }

    button {
        background-color: #A76825;
        color: white;
        padding: 14px 25px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 17px;
        font-weight: bold;
        transition: background-color 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
        display: block;
        width: 100%;
        max-width: 200px;
        margin: 20px auto 0 auto;
    }

    button:hover {
        background-color: #6F481C;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    @media screen and (max-width: 768px) {
        body {
            padding: 20px;
        }
        form {
            padding: 20px;
            border-radius: 8px;
        }
        h2 {
            font-size: 28px;
            margin-bottom: 20px;
        }
        input[type="text"] {
            padding: 10px;
            margin: 8px 0 15px 0;
            font-size: 15px;
        }
        button {
            padding: 12px 20px;
            font-size: 16px;
            max-width: 100%;
            margin-top: 15px;
        }
    }

    @media screen and (max-width: 480px) {
        body {
            padding: 15px;
        }
        form {
            padding: 15px;
            box-shadow: none;
            border: 1px solid #D2BCA1;
        }
        h2 {
            font-size: 24px;
        }
        input[type="text"] {
            font-size: 14px;
            padding: 8px;
            margin: 6px 0 12px 0;
        }
        button {
            font-size: 15px;
            padding: 10px 15px;
            margin-top: 12px;
        }
    }
    </style>
</head>
<body>
    <h2>Edit <?php echo ucfirst($equipment_type); ?> Details</h2>

<?php if (isset($_GET['updated'])): ?>
    <script>
        alert('Device updated successfully!');
        window.location.href = 'admin_dashboard.php';
    </script>
<?php endif; ?>

<form method="POST" action="">
    <input type="hidden" name="equipment_type" value="<?php echo htmlspecialchars($equipment_type); ?>">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($id); ?>">

    <?php foreach ($device as $key => $value): ?>
        <?php if ($key === 'id') continue; ?>
        <label><?php echo ucfirst(str_replace('_', ' ', $key)); ?>:</label>
        <?php if ($key === 'assigned_to'): ?>
            <input type="text" value="<?php echo htmlspecialchars($value); ?>" readonly><br>
        <?php else: ?>
            <input type="text" name="<?php echo htmlspecialchars($key); ?>" value="<?php echo htmlspecialchars($value); ?>"><br>
        <?php endif; ?>
    <?php endforeach; ?>

    <button type="submit">Update Device</button>
</form>
<div style="margin-top: 20px;">
    <a href="admin_dashboard.php" style="
        display: inline-block;
        background-color: maroon;
        color: white;
        padding: 10px 20px;
        text-decoration: none;
        border-radius: 5px;
        font-weight: bold;
    ">Back to Dashboard</a>
</div>
</body>
</html>

