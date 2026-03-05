<?php
require_once 'db.php';
$query = $pdo->query("DESCRIBE employees");
echo "<pre>";
print_r($query->fetchAll(PDO::FETCH_COLUMN));
echo "</pre>";

$query = $pdo->query("DESCRIBE users");
echo "<pre>";
print_r($query->fetchAll(PDO::FETCH_COLUMN));
echo "</pre>";