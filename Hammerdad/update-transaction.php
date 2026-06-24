<?php
require 'db.php';
header('Content-Type: application/json');

$data            = json_decode(file_get_contents('php://input'), true);
$transaction_id  = intval($data['transaction_id']);
$labor_cost      = floatval($data['labor_cost']);
$to_delete       = $data['to_delete'] ?? [];
$to_add          = $data['to_add'] ?? [];

if (!$transaction_id) {
    echo json_encode(['success' => false, 'error' => 'Invalid transaction ID.']);
    exit;
}

// Get plate_no first
$stmt = $conn->prepare("
    SELECT plate_no, type_id
    FROM repair_transactions
    WHERE transaction_id = ?
");
$stmt->bind_param('i', $transaction_id);
$stmt->execute();
$txn = $stmt->get_result()->fetch_assoc();

if (!$txn) {
    echo json_encode(['success' => false, 'error' => 'Transaction not found.']);
    exit;
}

$plate_no = $txn['plate_no'];
$type_id  = $txn['type_id'];

$conn->begin_transaction();

try {
    // 1. Update labor cost
    $stmt = $conn->prepare("
        UPDATE repair_transactions
        SET labor_cost = ?
        WHERE transaction_id = ?
    ");
    $stmt->bind_param('di', $labor_cost, $transaction_id);
    $stmt->execute();

    // 2. Delete removed parts
    foreach ($to_delete as $repairid) {
        $repairid = $conn->real_escape_string($repairid);

        $stmt = $conn->prepare("
            DELETE FROM transaction_parts
            WHERE transaction_id = ?
              AND repair_id = ?
        ");
        $stmt->bind_param('is', $transaction_id, $repairid);
        $stmt->execute();
    }

    // 3. Insert new parts
    foreach ($to_add as $item) {
        $repair_id = $conn->real_escape_string($item['repair_id']);
        $qty       = intval($item['qty']);

        $stmt = $conn->prepare("
            INSERT INTO transaction_parts
            (transaction_id, repair_id, quantity)
            VALUES (?, ?, ?)
        ");
        $stmt->bind_param('isi', $transaction_id, $repair_id, $qty);
        $stmt->execute();
    }

    $conn->commit();

    // Update repair prediction only if this is a repair transaction
    $prediction_output = null;

    if ($type_id === 'REP') {
        $python = "C:\\Users\\Jelo\\AppData\\Local\\Programs\\Python\\Python312\\python.exe";
        $script = __DIR__ . "\\prediction\\update_repair_prediction.py";

        $cmd = '"' . $python . '" "' . $script . '" "' . $plate_no . '" 2>&1';
        $prediction_output = shell_exec($cmd);
    }

    echo json_encode([
        'success' => true,
        'prediction_output' => $prediction_output
    ]);

} catch (Exception $e) {
    $conn->rollback();

    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
