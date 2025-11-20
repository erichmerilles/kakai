<?php
date_default_timezone_set('Asia/Manila');

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "kakaione";

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Timezone to Philippines
$conn->query("SET time_zone = '+08:00'");
?>