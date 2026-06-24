<?php
require_once __DIR__ . '/db.php';

function logNotification($plate_no, $message) {
    global $conn;

    $stmt = $conn->prepare("
        INSERT INTO notifications (plate_no, message)
        VALUES (?, ?)
    ");

    $stmt->bind_param("ss", $plate_no, $message);
    return $stmt->execute();
}
?>