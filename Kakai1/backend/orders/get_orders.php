<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
$q = "SELECT o.order_id, o.customer_id, c.full_name, o.order_date, o.status, o.payment_status, o.total_amount
      FROM orders o
      LEFT JOIN customers c ON o.customer_id = c.customer_id
      ORDER BY o.order_date DESC";
$res = $conn->query($q);
$data = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
echo json_encode(['success'=>true,'data'=>$data]);
?>
