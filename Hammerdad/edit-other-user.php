<?php
require_once 'auth.php';
require 'db.php';

header('Content-Type: application/json');

if ($_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$targetId      = intval($data['user_id']    ?? 0);
$newUsername   = trim($data['username']     ?? '');
$firstName     = trim($data['first_name']   ?? '');
$lastName      = trim($data['last_name']    ?? '');
$newPassword   = $data['new_password']      ?? '';
$adminPassword = $data['admin_password']    ?? '';

if (!$targetId || !$newUsername || !$firstName || !$lastName || !$adminPassword) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields.']);
    exit;
}

// Prevent editing yourself via this endpoint
if ($targetId === (int) $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'Use the Edit Profile popup to edit your own account.']);
    exit;
}

if ($newPassword !== '' && strlen($newPassword) < 6) {
    echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
    exit;
}

// Verify admin's own password
$stmt = $conn->prepare("SELECT password FROM users WHERE user_id = ?");
$stmt->bind_param('i', $_SESSION['user_id']);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

// Supports both plaintext (old) and hashed (new) passwords
if (!$admin || !(password_verify($adminPassword, $admin['password']) || $adminPassword === $admin['password'])) {
    echo json_encode(['success' => false, 'message' => 'Incorrect admin password.']);
    exit;
}

// Check username uniqueness (excluding the target user)
$stmt = $conn->prepare("SELECT user_id FROM users WHERE username = ? AND user_id != ?");
$stmt->bind_param('si', $newUsername, $targetId);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    echo json_encode(['success' => false, 'message' => 'That username is already taken.']);
    exit;
}

// Apply update
if ($newPassword !== '') {
    $stmt = $conn->prepare("UPDATE users SET username = ?, first_name = ?, last_name = ?, password = ? WHERE user_id = ?");
    $stmt->bind_param('ssssi', $newUsername, $firstName, $lastName, $newPassword, $targetId);
} else {
    $stmt = $conn->prepare("UPDATE users SET username = ?, first_name = ?, last_name = ? WHERE user_id = ?");
    $stmt->bind_param('sssi', $newUsername, $firstName, $lastName, $targetId);
}

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update user.']);
}