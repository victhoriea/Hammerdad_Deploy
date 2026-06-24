<?php
session_start();
require_once 'db.php';

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$stmt = $conn->prepare("SELECT user_id, password, role, first_name, last_name FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user && ($user['password'] === $password || password_verify($password, $user['password']))) {
    $_SESSION['user_id']    = $user['user_id'];
    $_SESSION['username']   = $username;
    $_SESSION['role']       = $user['role'];
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name']  = $user['last_name'];
    header('Location: dashboard.php');
    exit;
} else {
    header('Location: log-in.php?error=1');
    exit;
}
