<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

// products table in your dump has columns product_id, product_name, price, stock
$res = $conn->query("SELECT product_id, product_name, price, stock FROM products ORDER BY product_name ASC");
$data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
echo json_encode(['success'=>true,'data'=>$data]);
?>
