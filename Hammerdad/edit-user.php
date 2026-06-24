<?php
require_once 'auth.php';
require 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);

$firstName   = trim($data['first_name'] ?? '');
$lastName    = trim($data['last_name']  ?? '');
$newPassword = $data['new_password']    ?? '';

$passwordOnly = (!$firstName && !$lastName && $newPassword !== '');

if (!$passwordOnly && (!$firstName || !$lastName)) {
    echo json_encode(['success' => false, 'message' => 'Name fields cannot be empty.']);
    exit;
}

if ($newPassword !== '') {
    if (strlen($newPassword) < 6) {
        echo json_encode(['success' => false, 'message' => 'Password must be at least 6 characters.']);
        exit;
    }
    $hashed = password_hash($newPassword, PASSWORD_DEFAULT);

    if ($passwordOnly) {
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE user_id = ?");
        $stmt->bind_param('si', $hashed, $_SESSION['user_id']);
    } else {
        $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, password = ? WHERE user_id = ?");
        $stmt->bind_param('sssi', $firstName, $lastName, $hashed, $_SESSION['user_id']);
    }
} else {
    $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE user_id = ?");
    $stmt->bind_param('ssi', $firstName, $lastName, $_SESSION['user_id']);
}

if ($stmt->execute()) {
    if (!$passwordOnly) {
        $_SESSION['first_name'] = $firstName;
        $_SESSION['last_name']  = $lastName;
    }
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to update profile.']);
}