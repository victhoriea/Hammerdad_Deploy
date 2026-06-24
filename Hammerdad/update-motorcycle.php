<?php
require 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$plate_no = $conn->real_escape_string($data['plate_no']);
$model_id = $conn->real_escape_string($data['model_id']);
$hub_id   = $conn->real_escape_string($data['hub_id']);

$sql = "UPDATE motorcycles SET model_id = '$model_id', hub_id = '$hub_id' WHERE plate_no = '$plate_no'";

if ($conn->query($sql)) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update motorcycle.']);
}
?>