<?php
session_start();
include 'connect.php';
require_once 'includes/audit_logger.php';

$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

// Auto logout after 1 hour (3600 seconds)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 3600) {
    session_unset();
    session_destroy();
    header("Location: loginpage.php?timeout=1");
    exit();
}
$_SESSION['last_activity'] = time();

// Role-based access control
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'user'])) {
    header("Location: loginpage.php?unauthorized=1");
    exit();
}

$type = $_GET['type'] ?? 'unknown';

// Success message display (if redirected from successful submission)
if (isset($_GET['success']) && $_GET['success'] == 1) {
    echo "<div id='successMsg' style='background:#d4edda; color:#155724; padding:10px; margin:10px 0; border:1px solid #c3e6cb;'>
                ✅ " . htmlspecialchars(ucfirst($type)) . " submitted successfully!
              </div>";
}

// Function to fetch options from a database table for validation
function getDbOptions($conn, $tableName, $columnName) {
    $options = [];
    $query = "SELECT {$columnName} FROM {$tableName}";
    if ($result = mysqli_query($conn, $query)) {
        while ($row = mysqli_fetch_assoc($result)) {
            $options[] = $row[$columnName];
        }
        mysqli_free_result($result);
    } else {
        error_log("Error fetching options from {$tableName} for validation: " . mysqli_error($conn));
    }
    return $options;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Initialize an errors array and a data array to store sanitized inputs
    $errors = [];
    $input_data = []; // This will store validated and trimmed data

    // --- 1. Validate Common Fields ---

    // Date
    $input_data['date'] = trim($_POST['date'] ?? '');
    if (empty($input_data['date'])) {
        $errors['date'] = "Date is required.";
    } elseif (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $input_data['date']) || !strtotime($input_data['date'])) {
        $errors['date'] = "Invalid date format. Use YYYY-MM-DD.";
    }

    // Floor
    $input_data['floor'] = trim($_POST['floor'] ?? '');
    $allowed_floors = getDbOptions($conn, 'device_floors', 'floor_name');
    if (empty($input_data['floor'])) {
        $errors['floor'] = "Floor is required.";
    } elseif (!in_array($input_data['floor'], $allowed_floors)) {
        $errors['floor'] = "Invalid floor selected.";
    }

    // Room
    $input_data['room'] = trim($_POST['room'] ?? '');
    if (empty($input_data['room'])) {
        $errors['room'] = "Room is required.";
    } elseif (strlen($input_data['room']) > 50) {
        $errors['room'] = "Room name too long.";
    }

    // Department
    $input_data['department'] = trim($_POST['department'] ?? '');
    if (empty($input_data['department'])) {
        $errors['department'] = "Department is required.";
    } elseif (strlen($input_data['department']) > 100) {
        $errors['department'] = "Department name too long.";
    }

    // Brand (Now uses type-specific brand tables for validation)
    $input_data['brand'] = trim($_POST['brand'] ?? '');
    $allowed_brands = [];
    if ($type === 'desktop' || $type === 'laptop') {
        $allowed_brands = getDbOptions($conn, 'laptop_desktop_brands', 'name');
    } elseif ($type === 'ups') {
        $allowed_brands = getDbOptions($conn, 'ups_brands', 'name');
    } elseif ($type === 'printer') {
        $allowed_brands = getDbOptions($conn, 'printer_brands', 'name');
    } elseif ($type === 'scanner') {
        $allowed_brands = getDbOptions($conn, 'scanner_brands', 'name');
    } elseif ($type === 'tablet') {
        $allowed_brands = getDbOptions($conn, 'tablet_brands', 'name');
    } elseif ($type === 'wireless_access_point') {
        $allowed_brands = getDbOptions($conn, 'wireless_access_point_brands', 'name');
    } elseif ($type === 'projector') {
        $allowed_brands = getDbOptions($conn, 'projector_brands', 'name');
    } elseif ($type === 'network_switches') {
        $allowed_brands = getDbOptions($conn, 'network_switches_brands', 'name');
    } elseif ($type === 'cctv') {
        $allowed_brands = getDbOptions($conn, 'cctv_brands', 'name');
    } elseif ($type === 'ip_phone') {
        $allowed_brands = getDbOptions($conn, 'ip_phone_brands', 'name');
    } elseif ($type === 'router') {
        $allowed_brands = getDbOptions($conn, 'router_brands', 'name');
    } elseif ($type === 'firewall') {
        $allowed_brands = getDbOptions($conn, 'firewall_brands', 'name');
    }

    if (empty($input_data['brand'])) {
        $errors['brand'] = "Brand is required.";
    } elseif (!in_array($input_data['brand'], $allowed_brands)) {
        $errors['brand'] = "Invalid Brand selected for this equipment type.";
    }

    // Model
    $input_data['model'] = trim($_POST['model'] ?? '');
    if (empty($input_data['model'])) {
        $errors['model'] = "Model is required.";
    } elseif (strlen($input_data['model']) > 50) {
        $errors['model'] = "Model name too long.";
    }

    // Serial No (Required, and consider if you have a specific format like alphanumeric)
    $input_data['serial_no'] = trim($_POST['serial_no'] ?? '');
    if (empty($input_data['serial_no'])) {
        $errors['serial_no'] = "Serial Number is required.";
    } elseif (!preg_match("/^[a-zA-Z0-9-]{3,50}$/", $input_data['serial_no'])) {
        $errors['serial_no'] = "Serial Number must be alphanumeric and between 3-50 characters.";
    }

    // State (dynamic name based on type, assuming it's a selection from a predefined list)
    $state_field_name = ($type === 'desktop') ? 'status_of_desktop' : 'state';
    $input_data['item_state'] = trim($_POST[$state_field_name] ?? '');
    $allowed_states = getDbOptions($conn, 'device_states', 'state');
    if (empty($input_data['item_state'])) {
        $errors['item_state'] = "State is required.";
    } elseif (!in_array($input_data['item_state'], $allowed_states)) {
        $errors['item_state'] = "Invalid state selected.";
    }

    // Assigned To (Email format and existence check)
    if ($type !== 'cctv') {
        $input_data['assigned_to_email'] = trim($_POST['assigned_to'] ?? '');
        if (empty($input_data['assigned_to_email'])) {
            $errors['assigned_to_email'] = "Assigned To (email) is required.";
        } elseif (!filter_var($input_data['assigned_to_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['assigned_to_email'] = "Invalid email format for 'Assigned To'.";
        } else {
                $stmtU = $conn->prepare("SELECT id FROM register WHERE email=? UNION SELECT id FROM staff WHERE email=?");
            if ($stmtU === false) {
                error_log("save_device.php assigned_to_email prepare error: " . $conn->error);
                $errors['db_error'] = "Database error looking up assigned user.";
            } else {
                $stmtU->bind_param("ss", $input_data['assigned_to_email'], $input_data['assigned_to_email']);
                $stmtU->execute();
                $resU = $stmtU->get_result();
                $uid = $resU->fetch_assoc()['id'] ?? null;
                if (!$uid) {
                    $errors['assigned_to_email'] = "Assigned user '" . htmlspecialchars($input_data['assigned_to_email']) . "' not found in the system.";
                }
                $input_data['uid'] = $uid;
                $stmtU->close();
            }
        }
    } else {
        $input_data['assigned_to_email'] = null;
        $input_data['uid'] = null;
    }

    // --- 2. Validate Type-Specific Fields ---

    if ($type === 'desktop' || $type === 'laptop') {
        // Memory Capacity
        $input_data['memory_capacity'] = trim($_POST['memory_capacity'] ?? '');
        $allowed_memory = getDbOptions($conn, 'memory_capacity', 'capacity');
        if (empty($input_data['memory_capacity'])) {
            $errors['memory_capacity'] = "Memory Capacity is required.";
        } elseif (!in_array($input_data['memory_capacity'], $allowed_memory)) {
            $errors['memory_capacity'] = "Invalid memory capacity selected.";
        }

        // Processor Type
        $input_data['processor_type'] = trim($_POST['processor_type'] ?? '');
        if (empty($input_data['processor_type'])) {
            $errors['processor_type'] = "Processor Type is required.";
        } elseif (strlen($input_data['processor_type']) > 50) {
            $errors['processor_type'] = "Processor Type too long.";
        }

        // Operating System
        $input_data['operating_system'] = trim($_POST['operating_system'] ?? '');
        $allowed_os = getDbOptions($conn, 'operating_systems', 'name');
        if (empty($input_data['operating_system'])) {
            $errors['operating_system'] = "Operating System is required.";
        } elseif (!in_array($input_data['operating_system'], $allowed_os)) {
            $errors['operating_system'] = "Invalid Operating System selected.";
        }

        // Microsoft Office
        $input_data['microsoft_office'] = trim($_POST['microsoft_office'] ?? '');
        $allowed_office = getDbOptions($conn, 'ms_office_versions', 'version');
        if (empty($input_data['microsoft_office'])) {
            $errors['microsoft_office'] = "Microsoft Office version is required.";
        } elseif (!in_array($input_data['microsoft_office'], $allowed_office)) {
            $errors['microsoft_office'] = "Invalid Microsoft Office version selected.";
        }

        // Hard Disk
        $input_data['hard_disk'] = trim($_POST['hard_disk'] ?? '');
        $allowed_harddisk = getDbOptions($conn, 'hard_disks', 'capacity');
        if (empty($input_data['hard_disk'])) {
            $errors['hard_disk'] = "Hard Disk capacity is required.";
        } elseif (!in_array($input_data['hard_disk'], $allowed_harddisk)) {
            $errors['hard_disk'] = "Invalid Hard Disk capacity selected.";
        }
    }

    // Desktop specific additional fields
    if ($type === 'desktop') {
        $input_data['connected_to_ups'] = trim($_POST['connected_to_ups'] ?? '');
        $allowed_ups = ['Yes', 'No', 'N/A']; // This remains static
        if (empty($input_data['connected_to_ups'])) {
            $errors['connected_to_ups'] = "Connected to UPS? is required.";
        } elseif (!in_array($input_data['connected_to_ups'], $allowed_ups)) {
            $errors['connected_to_ups'] = "Invalid value for 'Connected to UPS?'.";
        }

        $input_data['is_it_all_in_one'] = trim($_POST['is_it_all_in_one'] ?? '');
        $allowed_all_in_one = ['Yes', 'No']; // This remains static
        if (empty($input_data['is_it_all_in_one'])) {
            $errors['is_it_all_in_one'] = "Is it All-in-one? is required.";
        } elseif (!in_array($input_data['is_it_all_in_one'], $allowed_all_in_one)) {
            $errors['is_it_all_in_one'] = "Invalid value for 'Is it All-in-one?'.";
        }

        // CPU related fields (conditional requirement and N/A assignment)
        $input_data['cpu_brand'] = trim($_POST['cpu_brand'] ?? '');
        $input_data['cpu_serial_no'] = trim($_POST['cpu_serial_no'] ?? '');
        $input_data['cpu_state'] = trim($_POST['cpu_state'] ?? '');

        if ($input_data['is_it_all_in_one'] === 'Yes') {
            $input_data['cpu_brand'] = 'N/A';
            $input_data['cpu_serial_no'] = 'N/A';
            $input_data['cpu_state'] = 'N/A';
        } else {
            if (empty($input_data['cpu_brand'])) $errors['cpu_brand'] = "CPU Brand is required for non-All-in-one desktops.";
            if (empty($input_data['cpu_serial_no'])) $errors['cpu_serial_no'] = "CPU Serial No is required for non-All-in-one desktops.";
            if (empty($input_data['cpu_state'])) {
                $errors['cpu_state'] = "CPU State is required for non-All-in-one desktops.";
            } elseif (!in_array($input_data['cpu_state'], $allowed_states)) {
                $errors['cpu_state'] = "Invalid CPU state selected.";
            }
        }

        // Mouse and Keyboard fields (assuming they are always required for desktops)
        $input_data['mouse_brand']        = trim($_POST['mouse_brand'] ?? '');
        if (empty($input_data['mouse_brand'])) $errors['mouse_brand'] = "Mouse Brand is required.";
        $input_data['mouse_serial_no']    = trim($_POST['mouse_serial_no'] ?? '');
        if (empty($input_data['mouse_serial_no'])) $errors['mouse_serial_no'] = "Mouse Serial No is required.";
        $input_data['mouse_state']        = trim($_POST['mouse_state'] ?? '');
        if (empty($input_data['mouse_state'])) {
            $errors['mouse_state'] = "Mouse State is required.";
        } elseif (!in_array($input_data['mouse_state'], $allowed_states)) {
            $errors['mouse_state'] = "Invalid Mouse state selected.";
        }

        $input_data['keyboard_brand']     = trim($_POST['keyboard_brand'] ?? '');
        if (empty($input_data['keyboard_brand'])) $errors['keyboard_brand'] = "Keyboard Brand is required.";
        $input_data['keyboard_serial_no'] = trim($_POST['keyboard_serial_no'] ?? '');
        if (empty($input_data['keyboard_serial_no'])) $errors['keyboard_serial_no'] = "Keyboard Serial No is required.";
        $input_data['keyboard_state']     = trim($_POST['keyboard_state'] ?? '');
        if (empty($input_data['keyboard_state'])) {
            $errors['keyboard_state'] = "Keyboard State is required.";
        } elseif (!in_array($input_data['keyboard_state'], $allowed_states)) {
            $errors['keyboard_state'] = "Invalid Keyboard state selected.";
        }
    }

    // UPS specific
    if ($type === 'ups') {
        $input_data['duty'] = trim($_POST['duty'] ?? '');
        $allowed_duty = getDbOptions($conn, 'ups_duty', 'duty_type'); // Sourced from ups_duty
        if (empty($input_data['duty'])) {
            $errors['duty'] = "Duty is required.";
        } elseif (!in_array($input_data['duty'], $allowed_duty)) {
            $errors['duty'] = "Invalid duty value for UPS.";
        }
    }

    // Printer specific
    if ($type === 'printer') {
        $input_data['duty'] = trim($_POST['duty'] ?? '');
        $allowed_duty_printer = getDbOptions($conn, 'printer_duty', 'duty_type'); // Sourced from printer_duty
        if (empty($input_data['duty'])) {
            $errors['duty'] = "Duty is required.";
        } elseif (!in_array($input_data['duty'], $allowed_duty_printer)) {
            $errors['duty'] = "Invalid duty value for Printer.";
        }

        $input_data['connectivity'] = trim($_POST['connectivity'] ?? '');
        $allowed_connectivity = getDbOptions($conn, 'device_connectivity', 'connectivity_type');
        if (empty($input_data['connectivity'])) {
            $errors['connectivity'] = "Connectivity is required.";
        } elseif (!in_array($input_data['connectivity'], $allowed_connectivity)) {
            $errors['connectivity'] = "Invalid connectivity value.";
        }
    }

    // Scanner specific
    if ($type === 'scanner') {
        $input_data['scanner_type'] = trim($_POST['scanner_type'] ?? '');
        $allowed_scanner_types = getDbOptions($conn, 'scanner_types', 'scanner_type');
        if (empty($input_data['scanner_type'])) {
            $errors['scanner_type'] = "Scanner Type is required.";
        } elseif (!in_array($input_data['scanner_type'], $allowed_scanner_types)) {
            $errors['scanner_type'] = "Invalid Scanner Type selected.";
        }

        $input_data['connectivity'] = trim($_POST['connectivity'] ?? '');
        $allowed_connectivity_scanner = getDbOptions($conn, 'device_connectivity', 'connectivity_type');
        if (empty($input_data['connectivity'])) {
            $errors['connectivity'] = "Connectivity is required.";
        } elseif (!in_array($input_data['connectivity'], $allowed_connectivity_scanner)) {
            $errors['connectivity'] = "Invalid connectivity value.";
        }
    }

    // Network Switches specific
    if ($type === 'network_switches') {
        $input_data['number_of_ports'] = trim($_POST['number_of_ports'] ?? '');
        if (empty($input_data['number_of_ports'])) {
            $errors['number_of_ports'] = "Number of Ports is required.";
        } elseif (!is_numeric($input_data['number_of_ports']) || $input_data['number_of_ports'] < 1 || $input_data['number_of_ports'] > 256) {
            $errors['number_of_ports'] = "Number of Ports must be a positive number (e.g., 1-256).";
        }
    }

    // CCTV specific
    if ($type === 'cctv') {
        $input_data['cctv_type'] = trim($_POST['cctv_type'] ?? '');
        $allowed_cctv_types = getDbOptions($conn, 'cctv_types', 'type_name');
        if (empty($input_data['cctv_type'])) {
            $errors['cctv_type'] = "CCTV Type is required.";
        } elseif (!in_array($input_data['cctv_type'], $allowed_cctv_types)) {
            $errors['cctv_type'] = "Invalid CCTV Type selected.";
        }

        $input_data['power_type'] = trim($_POST['power_type'] ?? '');
        $allowed_power_types = getDbOptions($conn, 'cctv_power_types', 'power_type');
        if (empty($input_data['power_type'])) {
            $errors['power_type'] = "Power Type is required.";
        } elseif (!in_array($input_data['power_type'], $allowed_power_types)) {
            $errors['power_type'] = "Invalid Power Type selected.";
        }

        $input_data['resolution'] = trim($_POST['resolution'] ?? '');
        $allowed_resolutions = getDbOptions($conn, 'cctv_resolutions', 'resolution');
        if (empty($input_data['resolution'])) {
            $errors['resolution'] = "Resolution is required.";
        } elseif (!in_array($input_data['resolution'], $allowed_resolutions)) {
            $errors['resolution'] = "Invalid Resolution selected.";
        }
    }

    // IP Phone & Router specific
    if ($type === 'ip_phone' || $type === 'router') {
        $input_data['mac_address'] = trim($_POST['mac_address'] ?? '');
        if (empty($input_data['mac_address'])) {
            $errors['mac_address'] = "MAC Address is required.";
        } elseif (!preg_match("/^([0-9A-Fa-f]{2}[:-]){5}([0-9A-Fa-f]{2})$/", $input_data['mac_address'])) {
            $errors['mac_address'] = "Invalid MAC Address format (e.g., AA:BB:CC:DD:EE:FF).";
        }
    }

    // Firewall specific
    if ($type === 'firewall') {
        $input_data['firmware_version'] = trim($_POST['firmware_version'] ?? '');
        if (empty($input_data['firmware_version'])) {
            $errors['firmware_version'] = "Firmware Version is required.";
        } elseif (strlen($input_data['firmware_version']) > 50) {
            $errors['firmware_version'] = "Firmware Version too long.";
        }
    }

    // --- 3. Handle Validation Results ---

    if (!empty($errors)) {
        // If there are errors, store them in session and redirect back to the form page
        $_SESSION['errors'] = $errors;
        $_SESSION['old_input'] = $_POST; // Store original POST data to re-populate the form
        header("Location: device-details.php?type=$type&validation_failed=1");
        exit();
    }

    // --- 4. If no errors, proceed with database operations ---

    // Check for duplicate serial number across all equipment tables (perform again after all other validation)
    $stmtSerial = $conn->prepare("
        SELECT serial_no FROM (
            SELECT serial_no FROM desktop
            UNION ALL SELECT serial_no FROM laptop
            UNION ALL SELECT serial_no FROM tablet
            UNION ALL SELECT serial_no FROM ups
            UNION ALL SELECT serial_no FROM printer
            UNION ALL SELECT serial_no FROM scanner
            UNION ALL SELECT serial_no FROM wireless_access_point
            UNION ALL SELECT serial_no FROM projector
            UNION ALL SELECT serial_no FROM network_switches
            UNION ALL SELECT serial_no FROM cctv
            UNION ALL SELECT serial_no FROM ip_phone
            UNION ALL SELECT serial_no FROM router
            UNION ALL SELECT serial_no FROM firewall
        ) x WHERE serial_no = ? LIMIT 1
    ");
    // Use the already validated and trimmed serial_no from $input_data
    $stmtSerial->bind_param("s", $input_data['serial_no']);
    $stmtSerial->execute();
    if ($stmtSerial->get_result()->num_rows > 0) {
        $errors['serial_no_duplicate'] = "Serial number '" . htmlspecialchars($input_data['serial_no']) . "' already exists. Please use a unique serial number.";
        $_SESSION['errors'] = $errors;
        $_SESSION['old_input'] = $_POST; // Store original POST data again
        header("Location: device-details.php?type=$type&validation_failed=1");
        exit();
    }
    $stmtSerial->close();

    $sql = "";
    switch ($type) {
        case 'desktop':
            $sql = "INSERT INTO desktop (date,floor,room,department,brand,model,serial_no,
                                    memory_capacity,processor_type,operating_system,microsoft_office,hard_disk,
                                    connected_to_ups,state,is_it_all_in_one,cpu_brand,cpu_serial_no,cpu_state,
                                    mouse_brand,mouse_serial_no,mouse_state,keyboard_brand,keyboard_serial_no,keyboard_state,
                                    assigned_to)
                            VALUES ('" . mysqli_real_escape_string($conn, $input_data['date']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['floor']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['room']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['department']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['model']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['memory_capacity']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['processor_type']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['operating_system']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['microsoft_office']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['hard_disk']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['connected_to_ups']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['item_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['is_it_all_in_one']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['cpu_brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['cpu_serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['cpu_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['mouse_brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['mouse_serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['mouse_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['keyboard_brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['keyboard_serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['keyboard_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['assigned_to_email']) . "')";
            break;

        case 'laptop':
            $sql = "INSERT INTO laptop (date,floor,room,department,brand,model,serial_no,
                                    memory_capacity,processor_type,operating_system,microsoft_office,hard_disk,state,assigned_to)
                            VALUES ('" . mysqli_real_escape_string($conn, $input_data['date']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['floor']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['room']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['department']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['model']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['memory_capacity']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['processor_type']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['operating_system']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['microsoft_office']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['hard_disk']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['item_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['assigned_to_email']) . "')";
            break;

        case 'tablet':
            $sql = "INSERT INTO tablet (date,floor,room,department,brand,model,serial_no,state,assigned_to)
                            VALUES ('" . mysqli_real_escape_string($conn, $input_data['date']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['floor']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['room']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['department']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['model']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['item_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['assigned_to_email']) . "')";
            break;

        case 'ups':
            $sql = "INSERT INTO ups (date,floor,room,department,brand,model,serial_no,duty,state,assigned_to)
                            VALUES ('" . mysqli_real_escape_string($conn, $input_data['date']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['floor']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['room']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['department']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['model']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['duty']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['item_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['assigned_to_email']) . "')";
            break;

        case 'printer':
            $sql = "INSERT INTO printer (date,floor,room,department,brand,model,duty,connectivity,serial_no,state,assigned_to)
                            VALUES ('" . mysqli_real_escape_string($conn, $input_data['date']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['floor']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['room']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['department']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['model']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['duty']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['connectivity']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['item_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['assigned_to_email']) . "')";
            break;

        case 'scanner':
            $sql = "INSERT INTO scanner (date,floor,room,department,brand,model,serial_no,scanner_type,connectivity,state,assigned_to)
                            VALUES ('" . mysqli_real_escape_string($conn, $input_data['date']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['floor']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['room']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['department']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['model']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['scanner_type']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['connectivity']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['item_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['assigned_to_email']) . "')";
            break;

        case 'projector':
            $sql = "INSERT INTO projector (date,floor,room,department,brand,model,serial_no,state,assigned_to)
                            VALUES ('" . mysqli_real_escape_string($conn, $input_data['date']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['floor']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['room']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['department']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['model']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['item_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['assigned_to_email']) . "')";
            break;

        case 'network_switches':
            $sql = "INSERT INTO network_switches (date,floor,room,department,brand,model,serial_no,number_of_ports,state,assigned_to)
                            VALUES ('" . mysqli_real_escape_string($conn, $input_data['date']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['floor']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['room']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['department']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['model']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['number_of_ports']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['item_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['assigned_to_email']) . "')";
            break;

        case 'cctv':
            $sql = "INSERT INTO cctv (date, floor, room, department, brand, model, serial_no, cctv_type, power_type, resolution, state)
                            VALUES ('" . mysqli_real_escape_string($conn, $input_data['date']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['floor']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['room']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['department']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['model']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['cctv_type']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['power_type']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['resolution']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['item_state']) . "')";
            break;

        case 'ip_phone':
            $sql = "INSERT INTO ip_phone (date,floor,room,department,brand,model,serial_no,mac_address,state,assigned_to)
                            VALUES ('" . mysqli_real_escape_string($conn, $input_data['date']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['floor']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['room']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['department']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['model']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['mac_address']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['item_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['assigned_to_email']) . "')";
            break;

        case 'router':
            $sql = "INSERT INTO router (date,floor,room,department,brand,model,serial_no,mac_address,state,assigned_to)
                            VALUES ('" . mysqli_real_escape_string($conn, $input_data['date']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['floor']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['room']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['department']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['model']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['mac_address']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['item_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['assigned_to_email']) . "')";
            break;

        case 'firewall':
            $sql = "INSERT INTO firewall (date,floor,room,department,brand,model,serial_no,firmware_version,state,assigned_to)
                            VALUES ('" . mysqli_real_escape_string($conn, $input_data['date']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['floor']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['room']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['department']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['model']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['firmware_version']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['item_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['assigned_to_email']) . "')";
            break;

        case 'wireless_access_point':
            $sql = "INSERT INTO wireless_access_point (date,floor,room,department,brand,model,serial_no,state,assigned_to)
                            VALUES ('" . mysqli_real_escape_string($conn, $input_data['date']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['floor']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['room']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['department']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['brand']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['model']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['serial_no']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['item_state']) . "',
                                    '" . mysqli_real_escape_string($conn, $input_data['assigned_to_email']) . "')";
            break;

        default:
            echo "❌ Error: Unknown equipment type.";
            exit;
    }

    // Execute the SQL query
    if ($conn->query($sql)) {
        $eid = $conn->insert_id; // Get the ID of the newly inserted equipment

        // Insert into equipment_ownership for initial assignment
        $stmt = $conn->prepare("INSERT INTO equipment_ownership (equipment_type, equipment_id, previous_user_id, current_user_id, change_reason, change_date) VALUES (?, ?, NULL, ?, 'Initial Assignment', NOW())");
        $stmt->bind_param("sis", $type, $eid, $input_data['uid']);
        $stmt->execute();
        $stmt->close();

        // Log the activity
        log_activity($current_admin_id, $current_admin_name, 'EQUIPMENT_CREATED',
            "Added equipment of type " . ucfirst($type) . " with ID $eid and Serial No: " . $input_data['serial_no'], 'equipment', $eid, null, $_POST);

        // Redirect with success message
        header("Location: device-details.php?type=$type&success=1");
        exit;
    } else {
        echo "❌ DB Error: " . $conn->error;
    }
}
?>