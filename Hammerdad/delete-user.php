<?php
require 'auth.php';
require 'db.php';
header('Content-Type: application/json');

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$target_user_id = intval($data['user_id'] ?? 0);
$admin_password = trim($data['admin_password'] ?? '');

if (!$target_user_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid user.']);
    exit;
}

// Prevent admin from deleting themselves
if ($target_user_id === intval($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'You cannot delete your own account.']);
    exit;
}

// Verify admin password
$adminRow = $conn->query("SELECT password FROM users WHERE user_id={$_SESSION['user_id']}")->fetch_assoc();
if (!$adminRow || $adminRow['password'] !== $admin_password) {
    echo json_encode(['success' => false, 'message' => 'Incorrect admin password.']);
    exit;
}

$conn->query("DELETE FROM users WHERE user_id = $target_user_id");

if ($conn->error) {
    echo json_encode(['success' => false, 'message' => $conn->error]);
    exit;
}

echo json_encode(['success' => true]);
?>