<?php
$mysqli = new mysqli("localhost", "root", "", "jl_tracking_system");

session_start();
require_once 'connect.php'; // your DB connection file
require_once 'includes/audit_logger.php';
require_once 'includes/auth_check.php';

$current_admin_id = $_SESSION['admin_id'] ?? 0;
$current_admin_name = $_SESSION['admin_username'] ?? 'Unknown';

if ($mysqli->connect_error) {
    die("Database connection failed: " . $mysqli->connect_error);
}

$equipment_type = $_POST['equipment_type'] ?? '';
$id = intval($_POST['id'] ?? 0);

$valid_equipment = [
    'desktop' => 'desktop',
    'mouse' => 'mouse',
    'keyboard' => 'keyboard',
    'scanner' => 'scanner',
    'cpu' => 'cpu',
    'printer' => 'printer',
    'ups' => 'ups',
    'paper_shredder' => 'paper_shredder'
];

if (!array_key_exists($equipment_type, $valid_equipment)) {
    die("Invalid equipment type.");
}

$table = $valid_equipment[$equipment_type];

// Helper function
function get_post($name) {
    return $_POST[$name] ?? '';
}

// Common fields
$date = get_post('date');
$floor = get_post('floor');
$room = get_post('room');
$department = get_post('department');
$brand = get_post('brand');
$model = get_post('model');
$serial_no = get_post('serial_no');
$state = get_post('state');
$assigned_to = get_post('assigned_to');

// Equipment-specific update logic
if ($equipment_type === 'desktop') {
    $memory = get_post('memory');
    $processor = get_post('processor');
    $os = get_post('os');
    $office = get_post('office');
    $hard_disk = get_post('hard_disk');
    $connected_ups = get_post('connected_ups');

    $stmt = $mysqli->prepare("UPDATE `$table` SET date=?, floor=?, room=?, department=?, brand=?, model=?, serial_no=?, memory=?, processor=?, os=?, office=?, hard_disk=?, connected_ups=?, state=?, assigned_to=? WHERE id=?");
    $stmt->bind_param("sssssssssssssssi", $date, $floor, $room, $department, $brand, $model, $serial_no, $memory, $processor, $os, $office, $hard_disk, $connected_ups, $state, $assigned_to, $id);

} elseif ($equipment_type === 'mouse' || $equipment_type === 'keyboard' || $equipment_type === 'ups' || $equipment_type === 'paper_shredder') {
    $stmt = $mysqli->prepare("UPDATE `$table` SET date=?, floor=?, room=?, department=?, brand=?, model=?, serial_no=?, state=?, assigned_to=? WHERE id=?");
    $stmt->bind_param("sssssssssi", $date, $floor, $room, $department, $brand, $model, $serial_no, $state, $assigned_to, $id);

} elseif ($equipment_type === 'scanner') {
    $scanner_type = get_post('scanner_type');
    $connectivity = get_post('connectivity');

    $stmt = $mysqli->prepare("UPDATE `$table` SET date=?, floor=?, room=?, department=?, brand=?, serial_no=?, scanner_type=?, connectivity=?, state=?, assigned_to=? WHERE id=?");
    $stmt->bind_param("ssssssssssi", $date, $floor, $room, $department, $brand, $serial_no, $scanner_type, $connectivity, $state, $assigned_to, $id);

} elseif ($equipment_type === 'printer') {
    $printer_type = get_post('printer_type');
    $connectivity = get_post('connectivity');

    $stmt = $mysqli->prepare("UPDATE `$table` SET date=?, floor=?, room=?, department=?, printer_type=?, model=?, connectivity=?, brand=?, serial_no=?, state=?, assigned_to=? WHERE id=?");
    $stmt->bind_param("ssssssssssssi", $date, $floor, $room, $department, $printer_type, $model, $connectivity, $brand, $serial_no, $state, $assigned_to, $id);

} elseif ($equipment_type === 'cpu') {
    $cache = get_post('cache');

    $stmt = $mysqli->prepare("UPDATE `$table` SET date=?, floor=?, room=?, department=?, brand=?, model=?, serial_no=?, cache=?, state=?, assigned_to=? WHERE id=?");
    $stmt->bind_param("ssssssssssi", $date, $floor, $room, $department, $brand, $model, $serial_no, $cache, $state, $assigned_to, $id);
}

if (!$stmt) {
    die("Prepare failed: " . $mysqli->error);
}

$stmt->execute();
$stmt->close();
$mysqli->close();

header("Location: admin_dashboard.php");
exit();
?>
