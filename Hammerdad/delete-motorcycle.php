<?php
require 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$plate_no = trim($data['id'] ?? '');

if (!$plate_no) {
    echo json_encode(['success' => false, 'message' => 'Invalid plate number.']);
    exit;
}

$conn->begin_transaction();

try {
    $plate_no = $conn->real_escape_string($plate_no);

    // Get transaction IDs first
    $result = $conn->query("
        SELECT transaction_id
        FROM repair_transactions
        WHERE plate_no = '$plate_no'
    ");

    $transactionIds = [];
    while ($row = $result->fetch_assoc()) {
        $transactionIds[] = (int)$row['transaction_id'];
    }

    // Delete transaction parts
    if (!empty($transactionIds)) {
        $ids = implode(',', $transactionIds);
        $conn->query("DELETE FROM transaction_parts WHERE transaction_id IN ($ids)");
    }

    // Delete repair/PMS transactions
    $conn->query("DELETE FROM repair_transactions WHERE plate_no = '$plate_no'");

    // Get maintenance check IDs first
    $checkResult = $conn->query("
        SELECT check_no
        FROM maintenance_check
        WHERE plate_no = '$plate_no'
    ");

    $checkIds = [];
    while ($row = $checkResult->fetch_assoc()) {
        $checkIds[] = (int)$row['check_no'];
    }

    // Delete check parts
    if (!empty($checkIds)) {
        $ids = implode(',', $checkIds);
        $conn->query("DELETE FROM check_parts WHERE check_no IN ($ids)");
    }

    // Delete maintenance checks
    $conn->query("DELETE FROM maintenance_check WHERE plate_no = '$plate_no'");

    // Delete predictions, if these tables exist
    $conn->query("DELETE FROM predictions_pms WHERE plate_no = '$plate_no'");
    $conn->query("DELETE FROM predictions_repair WHERE plate_no = '$plate_no'");
    $conn->query("DELETE FROM predictions_maintenance WHERE plate_no = '$plate_no'");

    // Finally delete motorcycle
    $conn->query("DELETE FROM motorcycles WHERE plate_no = '$plate_no'");

    if ($conn->affected_rows < 1) {
        throw new Exception("Motorcycle not found.");
    }

    $conn->commit();

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();

    echo json_encode([
        'success' => false,
        'message' => 'Failed to delete motorcycle: ' . $e->getMessage()
    ]);
}