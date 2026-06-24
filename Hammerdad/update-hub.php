<?php
require_once 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$hub_id     = $data['hub_id'] ?? '';
$hub_name   = trim($data['hub_name'] ?? '');
$first_name = trim($data['first_name'] ?? '');
$last_name  = trim($data['last_name'] ?? '');
$email      = trim($data['email'] ?? '');

// hub_id, hub_name, first_name, last_name are required; email is optional
if (!$hub_id || !$hub_name || !$first_name || !$last_name) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

// Validate email only if provided
if ($email !== '') {
    $emailRegex = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';
    if (!preg_match($emailRegex, $email)) {
        echo json_encode(['success' => false, 'message' => 'Please enter a valid e-mail address.']);
        exit;
    }
}

try {
    // Get manager_id from hub
    $stmt = $conn->prepare("SELECT manager_id FROM hubs WHERE hub_id = ?");
    $stmt->bind_param("s", $hub_id);
    $stmt->execute();
    $hub = $stmt->get_result()->fetch_assoc();

    if (!$hub) {
        echo json_encode(['success' => false, 'message' => 'Hub not found.']);
        exit;
    }

    $manager_id = $hub['manager_id'];

    // Update hub name
    $hubStmt = $conn->prepare("UPDATE hubs SET hub_name = ? WHERE hub_id = ?");
    $hubStmt->bind_param("ss", $hub_name, $hub_id);
    $hubStmt->execute();

    // Update manager info (email nullable — pass null if empty)
    $emailValue = $email !== '' ? $email : null;
    $updateStmt = $conn->prepare("UPDATE managers SET first_name = ?, last_name = ?, email = ? WHERE manager_id = ?");
    $updateStmt->bind_param("ssss", $first_name, $last_name, $emailValue, $manager_id);
    $updateStmt->execute();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
