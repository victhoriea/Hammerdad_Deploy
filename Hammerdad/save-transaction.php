<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

require 'db.php';
require_once 'update-health-score.php';

$data = json_decode(file_get_contents('php://input'), true);
$type = $conn->real_escape_string($data['type']);
$date = $conn->real_escape_string($data['date']);
$transactionsInput = $data['transactions'];

// Get type_id
$typeRow = $conn->query("SELECT type_id FROM service_types WHERE type='$type'")->fetch_assoc();
$type_id = $conn->real_escape_string($typeRow['type_id'] ?? $type);

$savedTransactions = [];

foreach ($transactionsInput as $txn) {
    $plate_no = $conn->real_escape_string($txn['plate_no']);
    $labor = floatval($txn['labor_cost'] ?? 0);
    $mechanic = trim($txn['mechanic'] ?? '');
    $rows     = $txn['rows'];

    if ($type === 'PMS') {
        $labor = 900;
    }

    // Get mechanic_id (nullable)
    $mechanic_id = null;
    if ($mechanic !== '') {
        $nameParts = explode(' ', $mechanic, 2);
        $firstName = $conn->real_escape_string($nameParts[0]);
        $lastName  = $conn->real_escape_string($nameParts[1] ?? '');
        $mechRow   = $conn->query("SELECT mechanic_id FROM mechanics WHERE first_name='$firstName' AND last_name='$lastName'")->fetch_assoc();
        $mechanic_id = $mechRow['mechanic_id'] ?? null;
    }

    $mechanicValue = $mechanic_id ? "'$mechanic_id'" : "NULL";

    // Get hub
    $hub = $conn->query("
        SELECT h.hub_name 
        FROM motorcycles m 
        JOIN hubs h ON m.hub_id = h.hub_id 
        WHERE m.plate_no = '$plate_no'
    ")->fetch_assoc();
    $hub_name = $hub['hub_name'] ?? '';

    // Insert transaction
    $conn->query("
        INSERT INTO repair_transactions (plate_no, status_id, type_id, mechanic_id, labor_cost, date)
        VALUES ('$plate_no', 'COM', '$type_id', $mechanicValue, $labor, '$date')
    ");

    if ($conn->error) {
        echo json_encode(['success' => false, 'error' => "Insert transaction failed: " . $conn->error]);
        exit;
    }

    $transaction_id = $conn->insert_id;

    // Insert repair items and deduct amount
    foreach ($rows as $item) {
        $part_name = $conn->real_escape_string(trim($item['work_name']));
        $qty = intval($item['amount'] ?? 1);

        // Get repair_id and current amount
        $repairRow = $conn->query("
            SELECT repair_id, amount, price 
            FROM repair 
            WHERE part_name = '$part_name' 
            LIMIT 1
        ")->fetch_assoc();

        $repair_id = $repairRow['repair_id'] ?? null;
        $unit_price = floatval($repairRow['price'] ?? 0);

        if (!$repair_id) {
            error_log("Part not found in repair table: '$part_name'");
            continue;
        }

        // Check if enough stock
        $currentAmount = intval($repairRow['amount']);
        if ($currentAmount < $qty) {
            echo json_encode([
                'success' => false,
                'error'   => "Insufficient stock for '$part_name'. Available: $currentAmount, Required: $qty"
            ]);
            exit;
        }

        // Insert into transaction_parts
        $conn->query("
            INSERT INTO transaction_parts (transaction_id, amount, repair_id, unit_price)
            VALUES ($transaction_id, $qty, '$repair_id', $unit_price)
        ");

        if ($conn->error) {
            echo json_encode(['success' => false, 'error' => "Insert part failed for '$part_name': " . $conn->error]);
            exit;
        }

        // Deduct amount from repair table
        $conn->query("
            UPDATE repair SET amount = amount - $qty WHERE repair_id = '$repair_id'
        ");

        if ($conn->error) {
            echo json_encode(['success' => false, 'error' => "Stock deduction failed for '$part_name': " . $conn->error]);
            exit;
        }
    }

    // Compute total for JSON response only
    $parts_total = array_sum(array_map(fn($i) => ($i['amount'] ?? 1) * ($i['price'] ?? 0), $rows));
    $total       = $parts_total + $labor;

    $savedTransactions[] = [
        'transaction_id' => $transaction_id,
        'date'           => $date,
        'plate_no'       => $plate_no,
        'hub_name'       => $hub_name,
        'type_id'        => $type,
        'total'          => $total,
    ];
}

// Update predictions for affected motorcycles
$uniquePlates = array_unique(array_column($savedTransactions, 'plate_no'));

$python = "C:\\Python314\\python.exe";

$pmsScript = __DIR__ . "\\prediction\\update_pms_prediction.py";
$repairScript = __DIR__ . "\\prediction\\update_repair_prediction.py";

$predictionOutputs = [];

foreach ($uniquePlates as $plate) {
    // Update PMS prediction
    $pmsCmd = '"' . $python . '" "' . $pmsScript . '" "' . $plate . '" 2>&1';
    $predictionOutputs[$plate]['pms'] = shell_exec($pmsCmd);

    // Update Repair prediction
    $repairCmd = '"' . $python . '" "' . $repairScript . '" "' . $plate . '" 2>&1';
    $predictionOutputs[$plate]['repair'] = shell_exec($repairCmd);

    // Update health score and send poor health email if needed
    try {
        $healthResult = updateHealthScore($plate);

        $predictionOutputs[$plate]['health'] = $healthResult;
    } catch (Throwable $e) {
        $predictionOutputs[$plate]['health_error'] = $e->getMessage();
    }
}

echo json_encode([
    'success' => true,
    'transactions' => $savedTransactions,
    'prediction_output' => $predictionOutputs
]);