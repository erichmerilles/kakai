<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
$input = json_decode(file_get_contents('php://input'), true);
$order_id = intval($input['order_id'] ?? 0);
$status = $conn->real_escape_string($input['status'] ?? '');
if(!$order_id || !$status){ echo json_encode(['success'=>false,'message'=>'Missing']); exit; }
$stmt = $conn->prepare("UPDATE orders SET status=? WHERE order_id=?");
$stmt->bind_param('si', $status, $order_id);
$stmt->execute();
echo json_encode(['success'=>true]);
?>
