<?php
require 'db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$check_date           = $conn->real_escape_string($data['check_date']);
$plate_no             = $conn->real_escape_string($data['plate_no']);
$mileage              = isset($data['mileage']) && $data['mileage'] !== null ? intval($data['mileage']) : 'NULL';
$no_issues = isset($data['no_issues'])
    ? intval($data['no_issues'])
    : 0;
$fuel_type_id         = $conn->real_escape_string($data['fuel_type_id']);
$transmission_type_id = $conn->real_escape_string($data['transmission_type_id']);
$battery              = $conn->real_escape_string($data['battery']);
$tire                 = $conn->real_escape_string($data['tire']);
$brake                = $conn->real_escape_string($data['brake']);

// Part IDs — must match your parts table
// PT001 = Tire, PT002 = Brake, PT003 = Battery
$partIds = [
    'battery' => 'BTY',
    'tire'    => 'TRE',
    'brake'   => 'BRK',
];

try {
    // Insert maintenance_check
    $conn->query("
        INSERT INTO maintenance_check
        (plate_no, check_date, mileage, no_issues, fuel_type_id, transmission_type_id)
        VALUES
        ('$plate_no', '$check_date', $mileage, $no_issues, '$fuel_type_id', '$transmission_type_id')
    ");

    if ($conn->error) {
        echo json_encode(['success' => false, 'message' => 'Failed to insert record: ' . $conn->error]);
        exit;
    }

    $check_no = $conn->insert_id;

    // Insert check_parts for each condition
    $checks = [
        'battery' => $battery,
        'tire'    => $tire,
        'brake'   => $brake,
    ];

    foreach ($checks as $partKey => $condition_id) {
        $parts_id = $partIds[$partKey];

        $conn->query("
            INSERT INTO check_parts (check_no, condition_id, parts_id)
            VALUES ($check_no, '$condition_id', '$parts_id')
        ");

        if ($conn->error) {
            echo json_encode([
                'success' => false,
                'message' => "Failed to insert part condition for $partKey: " . $conn->error
            ]);
            exit;
        }
    }

    $python = "C:\\Python314\\python.exe";
    $script = __DIR__ . "\prediction\update_maintenance_prediction.py";

    $cmd = '"' . $python . '" "' . $script . '" "' . $check_no . '" 2>&1';
    $prediction_output = shell_exec($cmd);

    error_log("CMD: " . $cmd);
    error_log("OUTPUT: " . $prediction_output);

    // Update maintenance prediction after saving maintenance check
    $python = "C:\\Python314\\python.exe";
    $script = __DIR__ . "\prediction\update_maintenance_prediction.py";

    $cmd = '"' . $python . '" "' . $script . '" "' . $plate_no . '" 2>&1';
    $prediction_output = shell_exec($cmd);

    echo json_encode([
        'success' => true,
        'check_no' => $check_no,
        'prediction_output' => $prediction_output
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
