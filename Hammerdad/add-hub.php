<?php
require_once 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$hub        = trim($data['hub_name']);
$first_name = $data['first_name'];
$last_name  = $data['last_name'];
$email      = trim($data['email'] ?? '');
$email      = $email === '' ? null : $email;

try {
    // Check if hub name already exists (case-insensitive)
    $checkHub = $conn->prepare("SELECT hub_id FROM hubs WHERE LOWER(hub_name) = LOWER(?)");
    $checkHub->bind_param("s", $hub);
    $checkHub->execute();
    if ($checkHub->get_result()->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Hub name already exists.']);
        exit;
    }

    // Generate next manager_id (M001, M002, ...)
    $result = $conn->query("SELECT manager_id FROM managers ORDER BY manager_id DESC LIMIT 1");
    $lastManager = $result->fetch_assoc();

    if ($lastManager) {
        $lastNum = (int) substr($lastManager['manager_id'], 1);
        $newNum = $lastNum + 1;
    } else {
        $newNum = 1;
    }
    $manager_id = 'M' . str_pad($newNum, 3, '0', STR_PAD_LEFT);

    // Insert manager (email can be NULL)
    $stmt = $conn->prepare("INSERT INTO managers (manager_id, first_name, last_name, email) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $manager_id, $first_name, $last_name, $email);
    $stmt->execute();

    // Generate hub_id from first 3 letters of hub_name
    $hub_id = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $hub), 0, 3));

    // Ensure hub_id uniqueness
    $checkStmt = $conn->prepare("SELECT hub_id FROM hubs WHERE hub_id = ?");
    $checkStmt->bind_param("s", $hub_id);
    $checkStmt->execute();

    if ($checkStmt->get_result()->num_rows > 0) {
        $counter = 1;
        $baseId = substr($hub_id, 0, 2);
        do {
            $hub_id = $baseId . $counter;
            $checkStmt->bind_param("s", $hub_id);
            $checkStmt->execute();
            $counter++;
        } while ($checkStmt->get_result()->num_rows > 0);
    }

    // Insert hub
    $stmt2 = $conn->prepare("INSERT INTO hubs (hub_id, hub_name, manager_id) VALUES (?, ?, ?)");
    $stmt2->bind_param("sss", $hub_id, $hub, $manager_id);
    $stmt2->execute();

    echo json_encode(['success' => true, 'hub_id' => $hub_id]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
