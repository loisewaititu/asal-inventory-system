<?php
function log_activity($userId, $userName, $actionType, $description, $entityType = null, $entityId = null, $oldValue = null, $newValue = null) {
    global $conn;

    if (!isset($conn)) return false;

    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt = $conn->prepare("INSERT INTO audit_log 
        (user_id, user_name, action_type, description, entity_type, entity_id, old_value, new_value, ip_address) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

    $oldJson = $oldValue ? json_encode($oldValue) : null;
    $newJson = $newValue ? json_encode($newValue) : null;

    $stmt->bind_param("issssisss", 
        $userId, $userName, $actionType, $description, $entityType, $entityId, $oldJson, $newJson, $ip);

    $stmt->execute();
    $stmt->close();
}

function log_audit($action, $user_name) {
    global $conn;
    $stmt = $conn->prepare("INSERT INTO audit_log (action_type, user_name, timestamp) VALUES (?, ?, NOW())");
    if (!$stmt) {
        die("Audit Log Error: " . $conn->error);
    }
    $stmt->bind_param("ss", $action, $user_name);
    $stmt->execute();
    $stmt->close();
}

?>
