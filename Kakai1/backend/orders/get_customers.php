<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
$res = $conn->query("SELECT customer_id, full_name, phone, email FROM customers ORDER BY full_name ASC");
$data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
echo json_encode(['success'=>true,'data'=>$data]);
?>
