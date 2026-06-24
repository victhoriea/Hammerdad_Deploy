<?php
require 'db.php';
$name = $_GET['name'] ?? '';
$row = $conn->query("SELECT price FROM repair WHERE part_name = '" . $conn->real_escape_string($name) . "'")->fetch_assoc();
echo json_encode(['price' => $row['price'] ?? 0]);