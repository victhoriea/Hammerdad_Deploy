<?php
require_once 'auth.php';
require_once 'db.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$adminPassword = $data['admin_password'] ?? '';

$stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin || !($adminPassword === $admin['password'] || password_verify($adminPassword, $admin['password']))) {
    echo json_encode(['success' => false, 'message' => 'Incorrect password.']);
    exit;
}

$firstName = trim($data['first_name'] ?? '');
$lastName  = trim($data['last_name'] ?? '');
$username  = trim($data['username'] ?? '');
$password  = $data['password'] ?? '';
$role      = strtolower(trim($data['role'] ?? ''));

if (!$firstName || !$lastName || !$username || !$password || !$role) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ?");
$stmt->bind_param('s', $username);
$stmt->execute();

if ($stmt->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'Username is already taken.']);
    exit;
}

/* Store password as plain text */
$stmt = $conn->prepare("
    INSERT INTO users (first_name, last_name, username, password, role)
    VALUES (?, ?, ?, ?, ?)
");
$stmt->bind_param('sssss', $firstName, $lastName, $username, $password, $role);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add user.']);
}
?>
