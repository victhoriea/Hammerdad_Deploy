<?php
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');

require 'db.php';

$data      = json_decode(file_get_contents('php://input'), true);
$repair_id = trim($data['id'] ?? '');

if (!$repair_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid repair ID.']);
    exit;
}

$repair_id = $conn->real_escape_string($repair_id);  // escape after the check

// Check if this part is used in any transaction before deleting
$inUse = $conn->query("SELECT COUNT(*) AS cnt FROM transaction_parts WHERE repair_id = '$repair_id'")->fetch_assoc();
if ($inUse['cnt'] > 0) {
    echo json_encode(['success' => false, 'message' => 'Cannot delete as this part is used in existing transactions.']);
    exit;
}

$conn->query("DELETE FROM repair WHERE repair_id = '$repair_id'");

if ($conn->error) {
    echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $conn->error]);
    exit;
}

echo json_encode(['success' => true]);
exit;