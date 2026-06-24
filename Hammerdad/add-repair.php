<?php
header('Content-Type: application/json');
require 'db.php';

$data           = json_decode(file_get_contents('php://input'), true);
$part_name      = $conn->real_escape_string(strtoupper(trim($data['repair'] ?? '')));
$price          = floatval($data['price'] ?? 0);
$qty            = intval($data['qty'] ?? 100);
$repair_type_id = $conn->real_escape_string(trim($data['repair_type_id'] ?? ''));

if (!$part_name || $price <= 0 || !$repair_type_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid input.']);
    exit;
}

// Generate repair_id
$last = $conn->query("SELECT repair_id FROM repair ORDER BY repair_id DESC LIMIT 1")->fetch_assoc();
$num  = $last ? intval(substr($last['repair_id'], 1)) + 1 : 1;
$repair_id = 'P' . str_pad($num, 3, '0', STR_PAD_LEFT);

$conn->query("INSERT INTO repair (repair_id, part_name, price, amount, repair_type_id) VALUES ('$repair_id', '$part_name', $price, $qty, '$repair_type_id')");

if ($conn->affected_rows > 0) {
    echo json_encode(['success' => true, 'repair_id' => $repair_id]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add repair.']);
}
