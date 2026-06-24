<?php
require_once 'db.php';
require_once 'mail-config.php';

function sendManagerNotification($plate_no, $subject, $body)
{
    global $conn;

    $stmt = $conn->prepare("
        SELECT 
            mg.email,
            mg.first_name,
            mg.last_name,
            h.hub_name
        FROM motorcycles m
        JOIN hubs h ON m.hub_id = h.hub_id
        JOIN managers mg ON h.manager_id = mg.manager_id
        WHERE m.plate_no = ?
        LIMIT 1
    ");

    $stmt->bind_param("s", $plate_no);
    $stmt->execute();

    $manager = $stmt->get_result()->fetch_assoc();

    if (!$manager || empty($manager['email'])) {
        return false;
    }

    $managerName = trim($manager['first_name'] . ' ' . $manager['last_name']);

    return sendMail(
        $manager['email'],
        $managerName,
        $subject,
        $body
    );
}
?>