<?php
require 'db.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$plate_no = $conn->real_escape_string(trim($data['plate_no']));
$model_id = $conn->real_escape_string($data['model_id']);
$hub_id   = $conn->real_escape_string($data['hub_id']);

// Check if plate_no already exists
$check = $conn->query("SELECT plate_no FROM motorcycles WHERE plate_no = '$plate_no'");
if ($check->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Plate number already exists.']);
    exit;
}

// Insert motorcycle
$sql = "INSERT INTO motorcycles (plate_no, model_id, hub_id, health_score) 
        VALUES ('$plate_no', '$model_id', '$hub_id', 100)";

if ($conn->query($sql)) {
    echo json_encode(['success' => true, 'message' => 'Motorcycle added successfully.']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add motorcycle.']);
}
?>