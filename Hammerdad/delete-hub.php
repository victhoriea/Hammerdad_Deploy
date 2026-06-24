<?php
require_once 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$hub_id = $data['hub_id'] ?? '';

if (!$hub_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid hub ID.']);
    exit;
}

try {
    // Check if hub has motorcycles
    $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM motorcycles WHERE hub_id = ?");
    $checkStmt->bind_param("s", $hub_id);
    $checkStmt->execute();
    $count = $checkStmt->get_result()->fetch_assoc()['count'];

    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete hub with existing motorcycles. Please reassign or remove them first.']);
        exit;
    }

    // Get manager_id before deleting hub
    $hubStmt = $conn->prepare("SELECT manager_id FROM hubs WHERE hub_id = ?");
    $hubStmt->bind_param("s", $hub_id);
    $hubStmt->execute();
    $hub = $hubStmt->get_result()->fetch_assoc();

    if (!$hub) {
        echo json_encode(['success' => false, 'message' => 'Hub not found.']);
        exit;
    }

    $manager_id = $hub['manager_id'];

    // Delete hub
    $deleteHub = $conn->prepare("DELETE FROM hubs WHERE hub_id = ?");
    $deleteHub->bind_param("s", $hub_id);
    $deleteHub->execute();

    // Delete associated manager
    if ($manager_id) {
        $deleteManager = $conn->prepare("DELETE FROM managers WHERE manager_id = ?");
        $deleteManager->bind_param("s", $manager_id);
        $deleteManager->execute();
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>