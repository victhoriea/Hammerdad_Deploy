<?php
require 'db.php';
header('Content-Type: application/json');

$data           = json_decode(file_get_contents('php://input'), true);
$transaction_id = intval($data['id'] ?? 0);

if (!$transaction_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid transaction ID.']);
    exit;
}

$conn->begin_transaction();

try {
    // Get plate_no and type_id before deleting (needed for prediction update)
    $row = $conn->query("SELECT plate_no, type_id FROM repair_transactions WHERE transaction_id = $transaction_id")->fetch_assoc();
    $plate_no = $row['plate_no'] ?? null;
    $type_id  = $row['type_id'] ?? null;

    // Delete parts first (FK constraint)
    $stmt = $conn->prepare("DELETE FROM transaction_parts WHERE transaction_id = ?");
    $stmt->bind_param('i', $transaction_id);
    $stmt->execute();

    // Delete transaction
    $stmt = $conn->prepare("DELETE FROM repair_transactions WHERE transaction_id = ?");
    $stmt->bind_param('i', $transaction_id);
    $stmt->execute();

    $conn->commit();

    // Re-run prediction for affected plate
    if ($plate_no) {
        $python = "C:\\Python314\\python.exe";

        if ($type_id === 'PMS') {
            $script = __DIR__ . "\\prediction\\update_pms_prediction.py";
            $cmd    = '"' . $python . '" "' . $script . '" "' . $plate_no . '" 2>&1';
            shell_exec($cmd);
        } else {
            $script = __DIR__ . "\\prediction\\update_repair_prediction.py";
            $cmd    = '"' . $python . '" "' . $script . '" "' . $plate_no . '" 2>&1';
            shell_exec($cmd);
        }

        // ✅ Recalculate health score after deletion
        require_once __DIR__ . '/update-health-score.php';
        updateHealthScore($plate_no, false);  // false = don't send email on delete
    }

    echo json_encode(['success' => true]);

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}