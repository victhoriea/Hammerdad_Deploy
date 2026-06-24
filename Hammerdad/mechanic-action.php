<?php
require 'auth.php';
require 'db.php';
header('Content-Type: application/json');

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$data   = json_decode(file_get_contents('php://input'), true);
$action = $data['action'] ?? '';

// ── ADD ──────────────────────────────────────────────────────
if ($action === 'add') {
    $first_name = $conn->real_escape_string(trim($data['first_name']));
    $last_name  = $conn->real_escape_string(trim($data['last_name']));

    // Generate next mechanic_id (MC001, MC002, ...)
    $lastRow = $conn->query("SELECT mechanic_id FROM mechanics ORDER BY mechanic_id DESC LIMIT 1")->fetch_assoc();
    if ($lastRow) {
        $lastNum = (int) substr($lastRow['mechanic_id'], 2);
        $newId   = 'MC' . str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $newId = 'MC001';
    }

    $conn->query("INSERT INTO mechanics (mechanic_id, first_name, last_name) VALUES ('$newId', '$first_name', '$last_name')");

    if ($conn->error) {
        echo json_encode(['success' => false, 'message' => $conn->error]);
        exit;
    }

    echo json_encode(['success' => true, 'mechanic_id' => $newId]);
}

// ── EDIT ─────────────────────────────────────────────────────
elseif ($action === 'edit') {
    $mechanic_id = $conn->real_escape_string($data['mechanic_id']);
    $first_name  = $conn->real_escape_string(trim($data['first_name']));
    $last_name   = $conn->real_escape_string(trim($data['last_name']));

    $conn->query("UPDATE mechanics SET first_name='$first_name', last_name='$last_name' WHERE mechanic_id='$mechanic_id'");

    if ($conn->error) {
        echo json_encode(['success' => false, 'message' => $conn->error]);
        exit;
    }

    echo json_encode(['success' => true]);
}

// ── DELETE ────────────────────────────────────────────────────
elseif ($action === 'delete') {
    $mechanic_id = $conn->real_escape_string($data['mechanic_id']);

    // Check if mechanic has transactions
    $check = $conn->query("SELECT COUNT(*) as count FROM repair_transactions WHERE mechanic_id='$mechanic_id'")->fetch_assoc();
    if ($check['count'] > 0) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete mechanic with existing transactions.']);
        exit;
    }

    $conn->query("DELETE FROM mechanics WHERE mechanic_id='$mechanic_id'");

    if ($conn->error) {
        echo json_encode(['success' => false, 'message' => $conn->error]);
        exit;
    }

    echo json_encode(['success' => true]);
}

else {
    echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}
?>