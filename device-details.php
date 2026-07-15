<?php
session_start();
include 'connect.php';

// ✅ Prevent cached access after logout
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ✅ Auto logout after 1 hour
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600)) {
    session_unset();
    session_destroy();
    header("Location: loginpage.php?message=session_expired");
    exit();
}
$_SESSION['last_activity'] = time();

// ✅ Require login
if (!isset($_SESSION['email']) || !isset($_SESSION['role'])) {
    header("Location: loginpage.php?error=unauthorized");
    exit();
}

$errors = [];
$old_input = [];

// Restore error messages if any
if (isset($_SESSION['errors'])) {
    $errors = $_SESSION['errors'];
    unset($_SESSION['errors']);
}

// Restore old form input if any
if (isset($_SESSION['old_input'])) {
    $old_input = $_SESSION['old_input'];
    unset($_SESSION['old_input']);
}
$equipmentFields = [
    'desktop' => [
        'Is it All-in-one?',
        'Date', 'Floor', 'Room', 'Department', 'Brand', 'Model', 'Serial No',
        'Memory Capacity', 'Processor Type', 'Operating System', 'Microsoft Office',
        'Hard Disk', 'Connected To UPS', 'Status of Desktop',
        'Cpu Brand', 'Cpu Serial No', 'Cpu State',
        'Mouse Brand', 'Mouse Serial No', 'Mouse State', 'Keyboard Brand', 'Keyboard Serial No', 'Keyboard State', 'Assigned To'
    ],
    'laptop' => [
        'Date', 'Floor', 'Room', 'Department', 'Brand', 'Model', 'Serial No',
        'Memory Capacity', 'Processor Type', 'Operating System', 'Microsoft Office',
        'Hard Disk', 'State', 'Assigned To'
    ],
    'tablet' => [
        'Date', 'Floor', 'Room', 'Department', 'Brand', 'Model', 'Serial No',
        'State', 'Assigned To'
    ],
    'ups' => [
        'Date', 'Floor', 'Room', 'Department', 'Brand', 'Model', 'Serial No', 'Duty',
        'State', 'Assigned To'
    ],
    'wireless_access_point' => [
        'Date', 'Floor', 'Room', 'Department', 'Brand', 'Model', 'Serial No',
        'State', 'Assigned To'
    ],
    'printer' => [
        'Date', 'Floor', 'Room', 'Department', 'Brand', 'Model', 'Duty',
        'Connectivity', 'Serial No', 'State', 'Assigned To'
    ],
    'scanner' => [
        'Date', 'Floor', 'Room', 'Department', 'Brand', 'Model', 'Serial No', 'Scanner Type',
        'Connectivity', 'State', 'Assigned To'
    ],
    'projector' => [
        'Date', 'Floor', 'Room', 'Department', 'Brand', 'Model', 'Serial No',
        'State', 'Assigned To'
    ],
    'network_switches' => [
        'Date', 'Floor', 'Room', 'Department', 'Brand', 'Model', 'Serial No', 'Number of ports',
        'State', 'Assigned To'
    ],
    'cctv' => [
        'Date', 'Floor', 'Room', 'Department', 'Brand', 'Model', 'Serial No', 'Cctv Type',
        'Power Type', 'Resolution', 'State'
    ],
    'ip_phone' => [
        'Date', 'Floor', 'Room', 'Department', 'Brand', 'Model', 'Serial No', 'MAC Address',
        'State', 'Assigned To'
    ],
    'router' => [
        'Date', 'Floor', 'Room', 'Department', 'Brand', 'Model', 'Serial No', 'MAC Address',
        'State', 'Assigned To'
    ],
    'firewall' => [
        'Date', 'Floor', 'Room', 'Department', 'Brand', 'Model', 'Firmware version', 'Serial No',
        'State', 'Assigned To'
    ]
];

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
if (!array_key_exists($type, $equipmentFields)) {
    die("Unknown equipment type. Please select a valid type.");
}
$displayFields = $equipmentFields[$type];

$staffOptions = [];
$staffQuery = $conn->query("SELECT email, First_Names, Last_Name, designation FROM staff ORDER BY First_Names ASC, Last_Name ASC");
if ($staffQuery) {
    while ($row = $staffQuery->fetch_assoc()) {
        $staffOptions[] = $row;
    }
}

$adminOptions = [];
$userOptions = [];
$registerQuery = $conn->query("SELECT email, First_Names, Last_Name, designation, role FROM register ORDER BY First_Names ASC, Last_Name ASC");
if ($registerQuery) {
    while ($row = $registerQuery->fetch_assoc()) {
        if ($row['role'] === 'admin') {
            $adminOptions[] = $row;
        } else {
            $userOptions[] = $row;
        }
    }
}

$display_title_type = ($type === 'cctv') ? 'CCTV' : htmlspecialchars(ucfirst($type));

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Device Details - <?= $display_title_type ?></title>
    <style>
        body {
            font-family: 'Segoe UI', sans-serif;
            background-color: #D2BCA1;
            padding: 40px;
            color: #6F4D38;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            box-sizing: border-box;
        }
        h2 {
            color: #25344F;
            text-align: center;
            font-size: 32px;
            margin-bottom: 30px;
        }
        form {
            background: white;
            padding: 30px;
            border-radius: 12px;
            max-width: 650px;
            margin: auto;
            box-shadow: 0 8px 30px rgba(111, 72, 28, 0.2);
            max-height: 80vh;
            overflow-y: auto;
            box-sizing: border-box;
        }
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
            color: #6F4D38;
            font-size: 15px;
        }
        input, select {
            width: calc(100% - 24px);
            padding: 12px;
            margin-bottom: 20px;
            border: 1px solid #617891;
            border-radius: 8px;
            font-size: 16px;
            color: #25344F;
            background-color: #FDFDFD;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
            box-sizing: border-box;
        }
        input:focus, select:focus {
            outline: none;
            border-color: #A76825;
            box-shadow: 0 0 0 3px rgba(167, 104, 37, 0.2);
        }
        input:disabled, select:disabled {
            background-color: #f5f5f5;
            color: #999;
            cursor: not-allowed;
        }
        button {
            padding: 14px 25px;
            background: #A76825;
            color: white;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 17px;
            font-weight: bold;
            transition: background 0.3s ease, transform 0.2s ease, box-shadow 0.3s ease;
            display: block;
            width: 100%;
            max-width: 250px;
            margin: 25px auto 0 auto;
        }
        button:hover {
            background: #6F481C;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
        }
        .form-group {
            margin-bottom: 20px;
            transition: all 0.3s ease-in-out;
        }
        .cpu-field {
            display: none;
        }
        .error-message {
            color: red;
            font-size: 0.85em;
            margin-top: -15px;
            margin-bottom: 10px;
            display: block;
        }
        .validation-errors {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin: 20px auto;
            text-align: center;
            width: 80%;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            font-weight: bold;
        }
        .validation-errors ul {
            list-style-type: none;
            padding: 0;
            margin-top: 10px;
        }
        .validation-errors li {
            margin-bottom: 5px;
        }
        @media screen and (max-width: 768px) {
            body {
                padding: 20px;
            }
            form {
                padding: 20px;
            }
            h2 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div style="background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 10px rgba(0,0,0,0.1); width: 100%; max-width: 600px;">
        <h2 style="text-align: center;">Enter Details for: <?= $display_title_type ?></h2>

        <?php if (!empty($errors)): ?>
            <div class="validation-errors">
                Please correct the following errors:
                <ul style="list-style-type: none; padding: 0; margin-top: 10px;">
                    <?php foreach ($errors as $field_name => $message): ?>
                        <li><?= htmlspecialchars($message) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php
        if (isset($_GET['success']) && $_GET['success'] == 1) {
            echo "<div id='successMsg' style='background:#d4edda; color:#155724; padding:10px; margin:10px 0; border:1px solid #c3e6cb; text-align: center;'>
                            ✅ " . htmlspecialchars(ucfirst($type)) . " submitted successfully!
                        </div>";
        }
        ?>

        <form action="save_device.php?type=<?= urlencode($type) ?>" method="post" id="deviceForm">
<?php
function fetchOptionsFromDB($conn, $tableName, $columnName) {
    $options = [];
    $query = "SELECT {$columnName} FROM {$tableName} ORDER BY {$columnName} ASC";
    if ($result = mysqli_query($conn, $query)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $options[] = $row[$columnName];
        }
        mysqli_free_result($result);
    } else {
        error_log("Error fetching options from {$tableName}: " . mysqli_error($conn));
    }
    return $options;
}

// Fetch all dropdown options from DB
// Brand options are now conditional based on $type
$brandOptions = [];
if ($type === 'desktop' || $type === 'laptop') {
    $brandOptions = fetchOptionsFromDB($conn, 'laptop_desktop_brands', 'name');
} elseif ($type === 'ups') {
    $brandOptions = fetchOptionsFromDB($conn, 'ups_brands', 'name');
} elseif ($type === 'printer') {
    $brandOptions = fetchOptionsFromDB($conn, 'printer_brands', 'name');
} elseif ($type === 'scanner') {
    $brandOptions = fetchOptionsFromDB($conn, 'scanner_brands', 'name');
} elseif ($type === 'tablet') {
    $brandOptions = fetchOptionsFromDB($conn, 'tablet_brands', 'name');
} elseif ($type === 'wireless_access_point') {
    $brandOptions = fetchOptionsFromDB($conn, 'wireless_access_point_brands', 'name');
} elseif ($type === 'projector') {
    $brandOptions = fetchOptionsFromDB($conn, 'projector_brands', 'name');
} elseif ($type === 'network_switches') {
    $brandOptions = fetchOptionsFromDB($conn, 'network_switches_brands', 'name');
} elseif ($type === 'cctv') {
    $brandOptions = fetchOptionsFromDB($conn, 'cctv_brands', 'name');
} elseif ($type === 'ip_phone') {
    $brandOptions = fetchOptionsFromDB($conn, 'ip_phone_brands', 'name');
} elseif ($type === 'router') {
    $brandOptions = fetchOptionsFromDB($conn, 'router_brands', 'name');
} elseif ($type === 'firewall') {
    $brandOptions = fetchOptionsFromDB($conn, 'firewall_brands', 'name');
}


$memoryOptions = fetchOptionsFromDB($conn, 'memory_capacity', 'capacity');
$harddiskOptions = fetchOptionsFromDB($conn, 'hard_disks', 'capacity');
$osOptions = fetchOptionsFromDB($conn, 'operating_systems', 'name');
$msOfficeOptions = fetchOptionsFromDB($conn, 'ms_office_versions', 'version');
$floorOptions = fetchOptionsFromDB($conn, 'device_floors', 'floor_name');
$stateOptions = fetchOptionsFromDB($conn, 'device_states', 'state');

// Duty options are now conditional based on $type
$dutyOptions = [];
if ($type === 'ups') {
    $dutyOptions = fetchOptionsFromDB($conn, 'ups_duty', 'duty_type');
} elseif ($type === 'printer') {
    $dutyOptions = fetchOptionsFromDB($conn, 'printer_duty', 'duty_type');
}


$connectivityOptions = fetchOptionsFromDB($conn, 'device_connectivity', 'connectivity_type');
$scannerTypeOptions = fetchOptionsFromDB($conn, 'scanner_types', 'scanner_type');
$cctvTypeOptions = fetchOptionsFromDB($conn, 'cctv_types', 'type_name');
$powerTypeOptions = fetchOptionsFromDB($conn, 'cctv_power_types', 'power_type');
$resolutionOptions = fetchOptionsFromDB($conn, 'cctv_resolutions', 'resolution');

$connectedToUpsOptions = ['Yes', 'No', 'N/A'];
$isAllInOneOptions = ['No', 'Yes'];
?>

<?php foreach ($displayFields as $field): ?>
    <?php
    $name = strtolower(str_replace([' ', '-', '?', '(', ')', '.'], ['_', '_', '', '', '', ''], $field));
    $error_key = $name;

    if ($name === 'status_of_desktop' || $name === 'state') {
        $error_key = 'item_state';
    } elseif ($name === 'assigned_to') {
        $error_key = 'assigned_to_email';
    }

    $isDate = $name === 'date';
    $isDropdown = false;
    $dropdownOptions = [];

    switch ($field) {
        case 'Assigned To':
            $isDropdown = true;
            break;
        case 'Floor':
            $isDropdown = true;
            $dropdownOptions = $floorOptions;
            break;
        case 'Connected To UPS':
            $isDropdown = true;
            $dropdownOptions = $connectedToUpsOptions;
            break;
        case 'Operating System':
            $isDropdown = true;
            $dropdownOptions = $osOptions;
            break;
        case 'Microsoft Office':
            $isDropdown = true;
            $dropdownOptions = $msOfficeOptions;
            break;
        case 'State':
        case 'Status of Desktop':
        case 'CPU State':
        case 'Mouse State':
        case 'Keyboard State':
            $isDropdown = true;
            $dropdownOptions = $stateOptions;
            break;
        case 'Duty':
            $isDropdown = true;
            $dropdownOptions = $dutyOptions; // This will now contain the correct duty options based on $type
            break;
        case 'Connectivity':
            $isDropdown = true;
            $dropdownOptions = $connectivityOptions;
            break;
        case 'Brand':
            $isDropdown = true;
            $dropdownOptions = $brandOptions;
            break;
        case 'Memory Capacity':
            $isDropdown = true;
            $dropdownOptions = $memoryOptions;
            break;
        case 'Hard Disk':
            $isDropdown = true;
            $dropdownOptions = $harddiskOptions;
            break;
        case 'Scanner Type':
            $isDropdown = true;
            $dropdownOptions = $scannerTypeOptions;
            break;
        case 'Cctv Type':
            $isDropdown = true;
            $dropdownOptions = $cctvTypeOptions;
            $error_key = 'cctv_type';
            break;
        case 'Power Type':
            $isDropdown = true;
            $dropdownOptions = $powerTypeOptions;
            break;
        case 'Resolution':
            $isDropdown = true;
            $dropdownOptions = $resolutionOptions;
            break;
        case 'Is it All-in-one?':
            $isDropdown = true;
            $dropdownOptions = $isAllInOneOptions;
            break;
        case 'Serial No':
            $isDropdown = true;
            $dropdownOptions = ['N/A'];
            break;
    }

    $isCpuField = in_array($name, ['cpu_brand', 'cpu_serial_no', 'cpu_state']);
    $isSoftwareField = in_array($name, ['operating_system', 'microsoft_office', 'hard_disk', 'memory_capacity', 'processor_type']);
    ?>

    <div class="form-group <?= $isCpuField ? 'cpu-field' : '' ?> <?= $isSoftwareField ? 'software-field' : '' ?>">
        <label for="<?= $name ?>"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $field))) ?>:</label>

        <?php if ($isDropdown): ?>
            <?php if ($field === 'Is it All-in-one?'): ?>
                <select name="<?= $name ?>" id="<?= $name ?>" required onchange="toggleCpuFields()">
                    <option value="">-- Select --</option>
                    <?php foreach ($dropdownOptions as $option): ?>
                        <option value="<?= htmlspecialchars($option) ?>" <?= (($old_input[$name] ?? 'No') === $option) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php elseif ($field === 'Assigned To'): ?>
                <select name="<?= $name ?>" id="<?= $name ?>" required>
                    <option value="">-- Select Recipient --</option>
                    <?php if (!empty($staffOptions)): ?>
                        <optgroup label="Staff Members">
                            <?php foreach ($staffOptions as $staff): ?>
                                <option value="<?= htmlspecialchars($staff['email']) ?>" <?= (($old_input[$name] ?? '') === $staff['email']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($staff['First_Names'] . ' ' . $staff['Last_Name'] . ' (' . $staff['designation'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                    <?php if (!empty($adminOptions)): ?>
                        <optgroup label="Admins">
                            <?php foreach ($adminOptions as $admin): ?>
                                <option value="<?= htmlspecialchars($admin['email']) ?>" <?= (($old_input[$name] ?? '') === $admin['email']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($admin['First_Names'] . ' ' . $admin['Last_Name'] . ' (' . $admin['designation'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                    <?php if (!empty($userOptions)): ?>
                        <optgroup label="Regular Users">
                            <?php foreach ($userOptions as $user): ?>
                                <option value="<?= htmlspecialchars($user['email']) ?>" <?= (($old_input[$name] ?? '') === $user['email']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['First_Names'] . ' ' . $user['Last_Name'] . ' (' . $user['designation'] . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    <?php endif; ?>
                </select>
            <?php elseif ($field === 'Serial No'): ?>
                <select name="<?= $name ?>" id="<?= $name ?>" required>
                    <option value="">-- Select Serial No Option --</option>
                    <option value="N/A" <?= (($old_input[$name] ?? '') === 'N/A') ? 'selected' : '' ?>>N/A</option>
                    <option value="custom">Enter Custom Serial No</option>
                </select>
                <input type="text" name="<?= $name ?>_custom" id="<?= $name ?>_custom" 
                       style="display: none; margin-top: 10px;" 
                       placeholder="Enter custom serial number" 
                       value="<?= htmlspecialchars($old_input[$name.'_custom'] ?? '') ?>">
            <?php else: ?>
                <select name="<?= $name ?>" id="<?= $name ?>" required <?= ($field === 'Status of Desktop' || $field === 'State') ? 'onchange="toggleSoftwareFields(this)"' : '' ?>>
                    <option value="">-- Select <?= htmlspecialchars(ucwords(str_replace('_', ' ', $field))) ?> --</option>
                    <?php foreach ($dropdownOptions as $option): ?>
                        <option value="<?= htmlspecialchars($option) ?>" <?= (($old_input[$name] ?? '') === $option) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($option) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        <?php else: ?>
            <input type="<?= $isDate ? 'date' : 'text' ?>" name="<?= $name ?>" id="<?= $name ?>" 
                   value="<?= htmlspecialchars($old_input[$name] ?? '') ?>" 
                   required 
                   <?= ($isSoftwareField) ? 'class="software-field"' : '' ?>>
        <?php endif; ?>

        <?php if (isset($errors[$error_key])): ?>
            <span class="error-message"><?= htmlspecialchars($errors[$error_key]) ?></span>
        <?php endif; ?>
    </div>
<?php endforeach; ?>

        <div style="text-align: center;">
            <button type="submit" style="padding: 10px 20px; font-weight: bold; border-radius: 5px; background-color: #A76825; color: white; border: none;">Submit</button>
            <a href="Dashboard.php" style="
                display: inline-block;
                background-color:#25344F;
                color: white;
                padding: 10px 20px;
                border-radius: 5px;
                text-decoration: none;
                font-weight: bold;
                margin-left: 10px;
            ">
                ← Back to Dashboard
            </a>
        </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const urlParams = new URLSearchParams(window.location.search);
        const type = urlParams.get('type');

        // Initialize serial number dropdown functionality
        initializeSerialNoDropdowns();
        
        // Initialize state/status change functionality for desktop and laptop
        if (type === 'desktop' || type === 'laptop') {
            initializeStateChangeHandler();
            // Don't call toggleSoftwareFields() on page load - wait for user selection
        }

        if (type === 'desktop') {
            const allInOneSelect = document.getElementById('is_it_all_in_one');
            const cpuFields = document.querySelectorAll('.cpu-field');

            function toggleCpuFields() {
                if (allInOneSelect) {
                    const isAllInOne = allInOneSelect.value === 'Yes';
                    cpuFields.forEach(field => {
                        field.style.display = isAllInOne ? 'none' : 'block';
                        const inputs = field.querySelectorAll('input, select');
                        inputs.forEach(input => {
                            if (isAllInOne) {
                                input.removeAttribute('required');
                            } else {
                                input.setAttribute('required', 'required');
                            }
                        });
                        const errorMessage = field.querySelector('.error-message');
                        if (errorMessage) {
                            errorMessage.style.display = isAllInOne ? 'none' : 'block';
                        }
                    });
                }
            }

            toggleCpuFields();

            if (allInOneSelect) {
                allInOneSelect.addEventListener('change', toggleCpuFields);
            }
        }
    });

    function initializeSerialNoDropdowns() {
        const serialNoSelects = document.querySelectorAll('select[name="serial_no"]');
        
        serialNoSelects.forEach(select => {
            select.addEventListener('change', function() {
                const customInput = document.getElementById(this.id + '_custom');
                if (this.value === 'custom') {
                    customInput.style.display = 'block';
                    customInput.setAttribute('required', 'required');
                    this.removeAttribute('required');
                } else {
                    customInput.style.display = 'none';
                    customInput.removeAttribute('required');
                    this.setAttribute('required', 'required');
                }
            });

            // Trigger change event on page load if custom is selected
            if (select.value === 'custom') {
                select.dispatchEvent(new Event('change'));
            }
        });
    }

    function initializeStateChangeHandler() {
        const stateSelect = document.querySelector('select[name="status_of_desktop"], select[name="state"]');
        if (stateSelect) {
            stateSelect.addEventListener('change', toggleSoftwareFields);
        }
    }

    function toggleSoftwareFields(stateElement = null) {
        const stateSelect = stateElement || document.querySelector('select[name="status_of_desktop"], select[name="state"]');
        
        if (!stateSelect) return;

        const selectedValue = stateSelect.value;
        const isWorking = selectedValue === 'Working';
        const softwareFields = document.querySelectorAll('.software-field');
        
        // Only apply changes if a status is actually selected
        if (selectedValue !== '') {
            softwareFields.forEach(field => {
                const input = field.querySelector('input, select');
                if (input) {
                    if (!isWorking) {
                        // Only disable if status is NOT "Working"
                        input.disabled = true;
                        input.removeAttribute('required');
                        // Set default values for non-working devices
                        if (input.tagName === 'SELECT') {
                            input.value = '';
                        } else if (input.type === 'text') {
                            input.value = 'N/A';
                        }
                    } else {
                        // Enable if status is "Working"
                        input.disabled = false;
                        input.setAttribute('required', 'required');
                        // Clear N/A values when enabling
                        if (input.value === 'N/A') {
                            input.value = '';
                        }
                    }
                }
            });
        } else {
            // If no status selected, ensure all fields are enabled
            softwareFields.forEach(field => {
                const input = field.querySelector('input, select');
                if (input) {
                    input.disabled = false;
                    input.setAttribute('required', 'required');
                }
            });
        }
    }
</script>
</body>
</html>