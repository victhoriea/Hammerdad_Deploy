<?php
ini_set('display_errors', 0);
error_reporting(0);
header('Content-Type: application/json');
require 'db.php';

$data     = json_decode(file_get_contents('php://input'), true);
$check_no = trim($data['id'] ?? '');

if (!$check_no) {
    echo json_encode(['success' => false, 'message' => 'Invalid record ID.']);
    exit;
}

$check_no = $conn->real_escape_string($check_no);

$conn->begin_transaction();

try {
    // Delete related part condition records first (FK constraint)
    $conn->query("DELETE FROM check_parts WHERE check_no = '$check_no'");

    // Delete the maintenance check record
    $conn->query("DELETE FROM maintenance_check WHERE check_no = '$check_no'");

    if ($conn->error) {
        throw new Exception($conn->error);
    }

    $conn->commit();
    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'message' => 'Delete failed: ' . $e->getMessage()]);
}