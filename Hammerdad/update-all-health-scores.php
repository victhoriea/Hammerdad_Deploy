<?php
require_once 'db.php';
require_once 'update-health-score.php';

$result = $conn->query("SELECT plate_no FROM motorcycles");

$count = 0;

while ($row = $result->fetch_assoc()) {
    updateHealthScore($row['plate_no'], false);
    $count++;
}

echo "Updated health scores for $count motorcycles without sending emails.";
?>