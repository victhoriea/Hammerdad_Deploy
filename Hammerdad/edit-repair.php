<?php
header('Content-Type: application/json');
require 'db.php';

$data      = json_decode(file_get_contents('php://input'), true);
$repair_id = $conn->real_escape_string($data['repair_id'] ?? '');
$part_name = $conn->real_escape_string($data['repair'] ?? '');
$price     = floatval($data['price'] ?? 0);
$qty       = intval($data['qty'] ?? 100);

if (!$repair_id || !$part_name || $price <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

$conn->query("UPDATE repair SET part_name='$part_name', price=$price, amount=$qty WHERE repair_id='$repair_id'");

echo json_encode(['success' => $conn->errno === 0]);