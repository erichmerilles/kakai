<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
$pid = intval($_GET['order_id'] ?? 0);
$stmt = $conn->prepare("SELECT o.*, c.full_name FROM orders o LEFT JOIN customers c ON o.customer_id=c.customer_id WHERE o.order_id=?");
$stmt->bind_param('i', $pid); $stmt->execute(); $ord = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$ord) { echo json_encode(['success'=>false]); exit; }

$stmt = $conn->prepare("SELECT oi.*, p.product_name FROM order_items oi LEFT JOIN products p ON oi.product_id=p.product_id WHERE oi.order_id=?");
$stmt->bind_param('i', $pid); $stmt->execute(); $items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC); $stmt->close();

$ord['items'] = $items;
echo json_encode(['success'=>true,'data'=>$ord]);
?>
