<?php
session_start();
include 'connect.php';
require_once 'includes/audit_logger.php';

// ✅ Prevent cached access after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ✅ Ensure admin is logged in
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php?error=unauthorized");
    exit();
}

// ✅ Auto logout after 1 hour
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: login.php?message=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();

$message = '';
$error = '';

// Define a mapping of device types and field names to database tables and their value columns
$fieldToTableMapping = [
    'general' => [ // Fields that are not specific to one device type's brand or duty
        'Floor' => ['table' => 'device_floors', 'column' => 'floor_name'],
        'Memory Capacity' => ['table' => 'memory_capacity', 'column' => 'capacity'],
        'Operating System' => ['table' => 'operating_systems', 'column' => 'name'],
        'Microsoft Office' => ['table' => 'ms_office_versions', 'column' => 'version'],
        'Hard Disk' => ['table' => 'hard_disks', 'column' => 'capacity'],
        'State' => ['table' => 'device_states', 'column' => 'state'], // General state table
        'Connectivity' => ['table' => 'device_connectivity', 'column' => 'connectivity_type'],
        'Scanner Type' => ['table' => 'scanner_types', 'column' => 'scanner_type'],
        'CCTV Type' => ['table' => 'cctv_types', 'column' => 'type_name'],
        'Power Type' => ['table' => 'cctv_power_types', 'column' => 'power_type'],
        'Resolution' => ['table' => 'cctv_resolutions', 'column' => 'resolution'],
    ],
    'desktop' => [
        'Brand' => ['table' => 'laptop_desktop_brands', 'column' => 'name'],
    ],
    'laptop' => [
        'Brand' => ['table' => 'laptop_desktop_brands', 'column' => 'name'],
    ],
    'ups' => [
        'Brand' => ['table' => 'ups_brands', 'column' => 'name'],
        'Duty' => ['table' => 'ups_duty', 'column' => 'duty_type'], // Specific UPS Duty
    ],
    'printer' => [
        'Brand' => ['table' => 'printer_brands', 'column' => 'name'],
        'Duty' => ['table' => 'printer_duty', 'column' => 'duty_type'], // Specific Printer Duty
    ],
    'scanner' => [
        'Brand' => ['table' => 'scanner_brands', 'column' => 'name'],
    ],
    'tablet' => [
        'Brand' => ['table' => 'tablet_brands', 'column' => 'name'],
    ],
    'wireless_access_point' => [
        'Brand' => ['table' => 'wireless_access_point_brands', 'column' => 'name'],
    ],
    'projector' => [
        'Brand' => ['table' => 'projector_brands', 'column' => 'name'],
    ],
    'network_switches' => [
        'Brand' => ['table' => 'network_switches_brands', 'column' => 'name'],
    ],
    'cctv' => [
        'Brand' => ['table' => 'cctv_brands', 'column' => 'name'],
    ],
    'ip_phone' => [
        'Brand' => ['table' => 'ip_phone_brands', 'column' => 'name'],
    ],
    'router' => [
        'Brand' => ['table' => 'router_brands', 'column' => 'name'],
    ],
    'firewall' => [
        'Brand' => ['table' => 'firewall_brands', 'column' => 'name'],
    ],
];

// List of all device types for the first dropdown
$allDeviceTypes = [
    'desktop' => 'Desktop',
    'laptop' => 'Laptop',
    'tablet' => 'Tablet',
    'ups' => 'UPS',
    'wireless_access_point' => 'Wireless Access Point',
    'projector' => 'Projector',
    'printer' => 'Printer',
    'scanner' => 'Scanner',
    'network_switches' => 'Network Switches',
    'cctv' => 'CCTV',
    'ip_phone' => 'IP Phone',
    'router' => 'Router',
    'firewall' => 'Firewall',
    'general' => 'General (Non-Device Specific)' // Option for general fields
];

// Function to fetch options from a database table (now also fetches ID)
function fetchOptionsFromDB($conn, $tableName, $columnName) {
    $options = [];
    $query = "SELECT id, {$columnName} FROM {$tableName} ORDER BY {$columnName} ASC";
    if ($result = mysqli_query($conn, $query)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $options[] = ['id' => $row['id'], 'value' => $row[$columnName]];
        }
        mysqli_free_result($result);
    } else {
        error_log("[manage_options.php ERROR] Error fetching options from {$tableName}: " . mysqli_error($conn));
    }
    return $options;
}

// --- Handle Form Submissions (Add and Delete) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("[manage_options.php DEBUG] POST request received. Action: " . ($_POST['action'] ?? 'N/A'));

    if (isset($_POST['action']) && $_POST['action'] === 'add_option') {
        // Handle Add Option
        $device_type_selected = $_POST['device_type'] ?? '';
        $field_name_key = $_POST['field_name'] ?? '';
        $value = trim($_POST['value'] ?? '');

        if (empty($device_type_selected) || empty($field_name_key) || empty($value)) {
            $error = "Please select a device type, field, and provide a value.";
        } elseif (!isset($fieldToTableMapping[$device_type_selected][$field_name_key])) {
            $error = "Invalid field selected for the chosen device type.";
        } else {
            $table = $fieldToTableMapping[$device_type_selected][$field_name_key]['table'];
            $column = $fieldToTableMapping[$device_type_selected][$field_name_key]['column'];

            $stmt = $conn->prepare("SELECT * FROM {$table} WHERE {$column} = ?");
            if ($stmt === false) {
                $error = "Database prepare error: " . $conn->error;
                error_log("[manage_options.php ERROR] Add option prepare error: " . $conn->error);
            } else {
                $stmt->bind_param("s", $value);
                $stmt->execute();
                $res = $stmt->get_result();

                if ($res->num_rows == 0) {
                    $insert = $conn->prepare("INSERT INTO {$table} ({$column}) VALUES (?)");
                    if ($insert === false) {
                        $error = "Database insert prepare error: " . $conn->error;
                        error_log("[manage_options.php ERROR] Add option insert prepare error: " . $conn->error);
                    } else {
                        $insert->bind_param("s", $value);
                        if ($insert->execute()) {
                            $message = "Option added successfully to '{$field_name_key}' for '{$allDeviceTypes[$device_type_selected]}'.";
                            error_log("[manage_options.php DEBUG] Option added: {$value} to {$table}.{$column}");
                        } else {
                            $error = "Error adding option: " . $insert->error;
                            error_log("[manage_options.php ERROR] Add option execute error: " . $insert->error);
                        }
                        $insert->close();
                    }
                } else {
                    $error = "Option '{$value}' already exists in '{$field_name_key}' for '{$allDeviceTypes[$device_type_selected]}'.";
                    error_log("[manage_options.php DEBUG] Option already exists: {$value} in {$table}.{$column}");
                }
                $stmt->close();
            }
        }
    } elseif (isset($_POST['action']) && $_POST['action'] === 'delete_option') {
        // Handle Delete Option
        $delete_device_type = $_POST['delete_device_type'] ?? '';
        $delete_field_name = $_POST['delete_field_name'] ?? '';
        $delete_value_id = $_POST['delete_value_id'] ?? '';

        error_log("[manage_options.php DEBUG] Delete request for: Type={$delete_device_type}, Field={$delete_field_name}, ID={$delete_value_id}");

        if (empty($delete_device_type) || empty($delete_field_name) || empty($delete_value_id)) {
            $error = "Invalid deletion request. Missing parameters.";
            error_log("[manage_options.php ERROR] Delete request missing parameters.");
        } elseif (!isset($fieldToTableMapping[$delete_device_type][$delete_field_name])) {
            $error = "Invalid field for deletion.";
            error_log("[manage_options.php ERROR] Invalid field for deletion: {$delete_field_name} for {$delete_device_type}");
        } else {
            $table = $fieldToTableMapping[$delete_device_type][$delete_field_name]['table'];
            $column = $fieldToTableMapping[$delete_device_type][$delete_field_name]['column'];

            // 1. Get the actual value string from its ID
            $actualValueToDelete = null;
            $stmtGetValue = $conn->prepare("SELECT {$column} FROM {$table} WHERE id = ?");
            if ($stmtGetValue) {
                $stmtGetValue->bind_param("i", $delete_value_id);
                $stmtGetValue->execute();
                $resultValue = $stmtGetValue->get_result();
                $rowValue = $resultValue->fetch_assoc();
                $actualValueToDelete = $rowValue[$column] ?? null;
                $stmtGetValue->close();
                error_log("[manage_options.php DEBUG] Fetched value to delete: '{$actualValueToDelete}' from {$table} with ID {$delete_value_id}");
            } else {
                $error = "Database prepare error getting value for deletion check: " . $conn->error;
                error_log("[manage_options.php ERROR] Get value prepare error: " . $conn->error);
            }

            if ($actualValueToDelete === null) {
                $error = "Option not found or invalid ID provided.";
                error_log("[manage_options.php ERROR] Value to delete is null for ID {$delete_value_id} in {$table}.{$column}");
            } else {
                // 2. Check if the value is in use in any equipment table
                $inUse = false;
                $equipmentTables = ['desktop', 'laptop', 'tablet', 'ups', 'printer', 'scanner', 'wireless_access_point', 'projector', 'network_switches', 'cctv', 'ip_phone', 'router', 'firewall'];

                if ($delete_field_name === 'Duty') {
                    // Special check for 'Duty' across 'printer' and 'ups' tables
                    $checkQueries = [
                        "SELECT 1 FROM printer WHERE duty = ? LIMIT 1",
                        "SELECT 1 FROM ups WHERE duty = ? LIMIT 1"
                    ];
                    foreach ($checkQueries as $query) {
                        $stmtCheck = $conn->prepare($query);
                        if ($stmtCheck) {
                            $stmtCheck->bind_param("s", $actualValueToDelete);
                            $stmtCheck->execute();
                            if ($stmtCheck->get_result()->num_rows > 0) {
                                $inUse = true;
                                error_log("[manage_options.php DEBUG] Duty '{$actualValueToDelete}' found in use by query: {$query}");
                                $stmtCheck->close();
                                break; // Found in use, stop checking
                            }
                            $stmtCheck->close();
                        } else {
                            error_log("[manage_options.php ERROR] Error preparing duty check query ({$query}): " . $conn->error);
                        }
                    }
                } elseif ($delete_field_name === 'State') {
                    // Special check for 'State' across relevant columns in equipment tables
                    foreach ($equipmentTables as $eqTable) {
                        $columnsToScan = ['state'];
                        if ($eqTable === 'desktop') {
                            $columnsToScan[] = 'status_of_desktop'; // Desktop's main state column
                            $columnsToScan[] = 'cpu_state';
                            $columnsToScan[] = 'mouse_state';
                            $columnsToScan[] = 'keyboard_state';
                        }
                        foreach ($columnsToScan as $col) {
                            // Check if the column exists in the current equipment table
                            $checkColumnExists = $conn->query("SHOW COLUMNS FROM {$eqTable} LIKE '{$col}'");
                            if ($checkColumnExists && $checkColumnExists->num_rows > 0) {
                                $stmtCheck = $conn->prepare("SELECT 1 FROM {$eqTable} WHERE {$col} = ? LIMIT 1");
                                if ($stmtCheck) {
                                    $stmtCheck->bind_param("s", $actualValueToDelete);
                                    $stmtCheck->execute();
                                    if ($stmtCheck->get_result()->num_rows > 0) {
                                        $inUse = true;
                                        error_log("[manage_options.php DEBUG] State '{$actualValueToDelete}' found in use by {$eqTable}.{$col}");
                                        $stmtCheck->close();
                                        break 2; // Found in use, break both inner loops
                                    }
                                    $stmtCheck->close();
                                } else {
                                    error_log("[manage_options.php ERROR] Error preparing state check query for {$eqTable}.{$col}: " . $conn->error);
                                }
                            }
                        }
                    }
                } else {
                    // For all other fields, use a direct column mapping
                    $equipmentColumnMap = [
                        'Brand' => 'brand',
                        'Floor' => 'floor',
                        'Memory Capacity' => 'memory_capacity',
                        'Operating System' => 'operating_system',
                        'Microsoft Office' => 'microsoft_office',
                        'Hard Disk' => 'hard_disk',
                        'Connectivity' => 'connectivity',
                        'Scanner Type' => 'scanner_type',
                        'CCTV Type' => 'cctv_type',
                        'Power Type' => 'power_type',
                        'Resolution' => 'resolution',
                        // Note: 'Duty' and 'State' are handled above
                    ];
                    $columnToCheck = $equipmentColumnMap[$delete_field_name] ?? '';

                    if ($columnToCheck) {
                        foreach ($equipmentTables as $eqTable) {
                            // Check if the column exists in the current equipment table
                            $checkColumnExists = $conn->query("SHOW COLUMNS FROM {$eqTable} LIKE '{$columnToCheck}'");
                            if ($checkColumnExists && $checkColumnExists->num_rows > 0) {
                                $stmtCheck = $conn->prepare("SELECT 1 FROM {$eqTable} WHERE {$columnToCheck} = ? LIMIT 1");
                                if ($stmtCheck) {
                                    $stmtCheck->bind_param("s", $actualValueToDelete);
                                    $stmtCheck->execute();
                                    if ($stmtCheck->get_result()->num_rows > 0) {
                                        $inUse = true;
                                        error_log("[manage_options.php DEBUG] '{$actualValueToDelete}' found in use by {$eqTable}.{$columnToCheck}");
                                        $stmtCheck->close();
                                        break; // Found in use, stop checking this type
                                    }
                                    $stmtCheck->close();
                                } else {
                                    error_log("[manage_options.php ERROR] Error preparing general check query for {$eqTable}.{$columnToCheck}: " . $conn->error);
                                }
                            }
                        }
                    } else {
                        error_log("[manage_options.php ERROR] No equipment column mapping found for field: {$delete_field_name}");
                    }
                }

                // 3. Perform deletion or show error based on 'inUse' status
                if ($inUse) {
                    $error = "Cannot delete '{$actualValueToDelete}'. It is currently assigned to one or more devices. Please reassign devices before deleting this option.";
                    error_log("[manage_options.php DEBUG] Deletion blocked: '{$actualValueToDelete}' is in use.");
                } else {
                    $delete = $conn->prepare("DELETE FROM {$table} WHERE id = ?");
                    if ($delete === false) {
                        $error = "Database delete prepare error: " . $conn->error;
                        error_log("[manage_options.php ERROR] Delete prepare error: " . $conn->error);
                    } else {
                        $delete->bind_param("i", $delete_value_id);
                        if ($delete->execute()) {
                            $message = "Option deleted successfully from '{$delete_field_name}'.";
                            error_log("[manage_options.php DEBUG] Option '{$actualValueToDelete}' (ID: {$delete_value_id}) deleted successfully from {$table}.");
                        } else {
                            $error = "Error deleting option: " . $delete->error;
                            error_log("[manage_options.php ERROR] Delete execute error: " . $delete->error);
                        }
                        $delete->close();
                    }
                }
            }
        }
    }
}

// --- Fetch all existing options for display ---
$allExistingOptions = [];
foreach ($fieldToTableMapping as $deviceTypeKey => $fields) {
    foreach ($fields as $fieldName => $mapping) {
        $options = fetchOptionsFromDB($conn, $mapping['table'], $mapping['column']);
        foreach ($options as $option) {
            $allExistingOptions[] = [
                'device_type_key' => $deviceTypeKey,
                'device_type_display' => $allDeviceTypes[$deviceTypeKey] ?? ucfirst($deviceTypeKey),
                'field_name' => $fieldName,
                'value_id' => $option['id'],
                'value_display' => $option['value'],
                'table_name' => $mapping['table'], // For debugging/reference
                'column_name' => $mapping['column'] // For debugging/reference
            ];
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Dropdown Options</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #DCCCA3;
            color: #333333;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
            position: relative; /* For positioning the back button */
        }
        h2 {
            color: #333333;
            margin-bottom: 25px;
            text-align: center;
        }
        .container {
            background-color: #FFFFFF;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 700px; /* Increased max-width for better table display */
            box-sizing: border-box;
            margin-bottom: 20px;
        }
        form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-bottom: 30px; /* Space between add form and list */
        }
        label {
            font-weight: bold;
            color: #333333;
            margin-bottom: 5px;
            display: block;
        }
        select,
        input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #CCCCCC;
            border-radius: 4px;
            background-color: #FFFFFF;
            color: #333333;
            box-sizing: border-box;
            font-size: 16px;
        }
        select:focus,
        input[type="text"]:focus {
            border-color: #999999;
            outline: none;
            box-shadow: 0 0 5px rgba(0, 0, 0, 0.1);
        }
        input[type="submit"] {
            background-color: #4A6D7C;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 18px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        input[type="submit"]:hover {
            background-color: #5A7D8C;
        }
        p.message { /* Changed from 'p' to 'p.message' to avoid conflict with table p tags */
            text-align: center;
            margin-top: 15px;
            padding: 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        p.message:empty {
            display: none;
        }
        p.success {
            background-color: #D4EDDA;
            color: #155724;
            border: 1px solid #C3E6CB;
        }
        p.error {
            background-color: #F8D7DA;
            color: #721C24;
            border: 1px solid #F5C6CB;
        }
        a {
            display: inline-block;
            background-color: transparent;
            color: #333333;
            padding: 10px 20px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 20px;
            transition: color 0.3s ease, text-decoration 0.3s ease;
        }
        a:hover {
            color: #000000;
            text-decoration: underline;
        }

        /* Styles for the options table */
        .options-table-container {
            background-color: #FFFFFF;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 700px;
            box-sizing: border-box;
            margin-top: 30px; /* Space above the table */
            overflow-x: auto; /* For responsiveness on small screens */
        }
        .options-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        .options-table th, .options-table td {
            border: 1px solid #DDDDDD;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        .options-table th {
            background-color: #F2F2F2;
            font-weight: bold;
            color: #555555;
        }
        .options-table tr:nth-child(even) {
            background-color: #F9F9F9;
        }
        .options-table tr:hover {
            background-color: #F0F0F0;
        }
        .delete-btn {
            background-color: #522e32ff; /* Red */
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }
        .delete-btn:hover {
            background-color: #2c1717ff; /* Darker red */
        }

        /* Modal Styles */
        .modal {
            display: none; /* Hidden by default */
            position: fixed; /* Stay in place */
            z-index: 1000; /* Sit on top */
            left: 0;
            top: 0;
            width: 100%; /* Full width */
            height: 100%; /* Full height */
            overflow: auto; /* Enable scroll if needed */
            background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background-color: #fefefe;
            margin: auto;
            padding: 20px;
            border: 1px solid #888;
            border-radius: 8px;
            width: 80%;
            max-width: 400px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
        }
        .modal-content h3 {
            margin-top: 0;
            color: #333;
        }
        .modal-buttons {
            margin-top: 20px;
            display: flex;
            justify-content: space-around;
        }
        .modal-buttons button {
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            transition: background-color 0.3s ease;
        }
        .modal-buttons .confirm-delete {
            background-color: #523e40ff;
            color: white;
            border: none;
        }
        .modal-buttons .confirm-delete:hover {
            background-color: #643f42ff;
        }
        .modal-buttons .cancel-delete {
            background-color: #643838ff;
            color: white;
            border: none;
        }
        .modal-buttons .cancel-delete:hover {
            background-color: #5A6268;
        }
        .back-to-dashboard-top-right {
            position: absolute;
            top: 20px;
            right: 20px;
            background-color: #25344F;
            color: white;
            padding: 10px 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background-color 0.3s ease;
            z-index: 10; /* Ensure it's above other elements */
        }
        .back-to-dashboard-top-right:hover {
            background-color: #334966;
            text-decoration: none;
        }
    </style>
</head>
<body>
<a href="admin_dashboard.php" class="back-to-dashboard-top-right">← Back to Dashboard</a>

<div class="container">
    <h2>Add New Dropdown Option</h2>

    <?php if ($message): ?>
        <p class="message success">
            <?= htmlspecialchars($message) ?>
        </p>
    <?php endif; ?>
    <?php if ($error): ?>
        <p class="message error">
            <?= htmlspecialchars($error) ?>
        </p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="action" value="add_option">
        <label for="device_type">Select Device Type:</label>
        <select name="device_type" id="device_type" required onchange="updateFieldOptions()">
            <option value="">-- Select Device Type --</option>
            <?php foreach ($allDeviceTypes as $key => $display): ?>
                <option value="<?= htmlspecialchars($key) ?>"><?= htmlspecialchars($display) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="field_name">Field to Manage:</label>
        <select name="field_name" id="field_name" required>
            <option value="">-- Select Field --</option>
            <!-- Options will be populated by JavaScript -->
        </select>

        <label for="value">Value:</label>
        <input type="text" name="value" id="value" required>

        <input type="submit" value="Add Option">
    </form>
</div>

<div class="options-table-container">
    <h2>Existing Dropdown Options</h2>
    <?php if (empty($allExistingOptions)): ?>
        <p style="text-align: center;">No options found. Add some using the form above!</p>
    <?php else: ?>
        <table class="options-table">
            <thead>
                <tr>
                    <th>Device Type / Category</th>
                    <th>Field</th>
                    <th>Value</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allExistingOptions as $option): ?>
                    <tr>
                        <td><?= htmlspecialchars($option['device_type_display']) ?></td>
                        <td><?= htmlspecialchars($option['field_name']) ?></td>
                        <td><?= htmlspecialchars($option['value_display']) ?></td>
                        <td>
                            <button type="button" class="delete-btn"
                                data-device-type="<?= htmlspecialchars($option['device_type_key']) ?>"
                                data-field-name="<?= htmlspecialchars($option['field_name']) ?>"
                                data-value-id="<?= htmlspecialchars($option['value_id']) ?>"
                                data-value-display="<?= htmlspecialchars($option['value_display']) ?>"
                                onclick="showDeleteConfirmation(this)">
                                Delete
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<a href="admin_dashboard.php">← Back to Dashboard</a>

<!-- Delete Confirmation Modal -->
<div id="deleteConfirmationModal" class="modal">
    <div class="modal-content">
        <h3>Confirm Deletion</h3>
        <p>Are you sure you want to delete "<span id="modalValueDisplay"></span>"?</p>
        <p style="font-size: 0.9em; color: #888;">This action cannot be undone and will only succeed if the option is not currently assigned to any devices.</p>
        <div class="modal-buttons">
            <button class="cancel-delete" onclick="hideDeleteConfirmation()">Cancel</button>
            <form method="post" id="deleteForm" style="display: inline-block;">
                <input type="hidden" name="action" value="delete_option">
                <input type="hidden" name="delete_device_type" id="modalDeviceType">
                <input type="hidden" name="delete_field_name" id="modalFieldName">
                <input type="hidden" name="delete_value_id" id="modalValueId">
                <button type="submit" class="confirm-delete">Delete</button>
            </form>
        </div>
    </div>
</div>

<script>
    const fieldMapping = <?= json_encode($fieldToTableMapping) ?>;
    const deviceTypeSelect = document.getElementById('device_type');
    const fieldNameSelect = document.getElementById('field_name');

    function updateFieldOptions() {
        const selectedDeviceType = deviceTypeSelect.value;
        fieldNameSelect.innerHTML = '<option value="">-- Select Field --</option>'; // Clear existing options

        if (selectedDeviceType && fieldMapping[selectedDeviceType]) {
            // Add device-specific fields
            for (const fieldKey in fieldMapping[selectedDeviceType]) {
                const option = document.createElement('option');
                option.value = fieldKey;
                option.textContent = fieldKey;
                fieldNameSelect.appendChild(option);
            }
        }

        // Always add general fields (but only once, and correctly)
        let generalOptgroup = fieldNameSelect.querySelector('optgroup[label="General Fields"]');
        if (!generalOptgroup) {
            generalOptgroup = document.createElement('optgroup');
            generalOptgroup.label = "General Fields";
            fieldNameSelect.appendChild(generalOptgroup);
        } else {
            generalOptgroup.innerHTML = ''; // Clear existing general options if updating
        }

        if (fieldMapping['general']) {
            for (const fieldKey in fieldMapping['general']) {
                const option = document.createElement('option');
                option.value = fieldKey;
                option.textContent = fieldKey;
                generalOptgroup.appendChild(option);
            }
        }
    }

    // Call on page load to initialize if a device type was pre-selected (e.g., from old input)
    updateFieldOptions();

    // If there was old input, try to re-select the device type and then update fields
    const oldDeviceType = "<?= htmlspecialchars($_POST['device_type'] ?? '') ?>";
    const oldFieldName = "<?= htmlspecialchars($_POST['field_name'] ?? '') ?>";
    if (oldDeviceType) {
        deviceTypeSelect.value = oldDeviceType;
        updateFieldOptions(); // Populate fields based on old device type
        if (oldFieldName) {
            fieldNameSelect.value = oldFieldName; // Select old field name
        }
    }

    // --- Modal and Delete Logic ---
    const modal = document.getElementById('deleteConfirmationModal');
    const modalValueDisplay = document.getElementById('modalValueDisplay');
    const modalDeviceType = document.getElementById('modalDeviceType');
    const modalFieldName = document.getElementById('modalFieldName');
    const modalValueId = document.getElementById('modalValueId');

    function showDeleteConfirmation(button) {
        console.log("Delete button clicked."); // Debugging
        const deviceType = button.getAttribute('data-device-type');
        const fieldName = button.getAttribute('data-field-name');
        const valueId = button.getAttribute('data-value-id');
        const valueDisplay = button.getAttribute('data-value-display');

        console.log("Data from button:", { deviceType, fieldName, valueId, valueDisplay }); // Debugging

        modalValueDisplay.textContent = valueDisplay;
        modalDeviceType.value = deviceType;
        modalFieldName.value = fieldName;
        modalValueId.value = valueId;

        console.log("Modal hidden fields set to:", {
            modalDeviceType: modalDeviceType.value,
            modalFieldName: modalFieldName.value,
            modalValueId: modalValueId.value
        }); // Debugging

        modal.style.display = 'flex'; // Use flex to center the modal
    }

    function hideDeleteConfirmation() {
        modal.style.display = 'none';
    }

    // Close modal if clicked outside (optional, but good UX)
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }
</script>
</body>
</html>
