<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
session_start();
$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['items'])) { echo json_encode(['success'=>false,'message'=>'Invalid payload']); exit; }

$customer_id = !empty($input['customer_id']) ? intval($input['customer_id']) : null;
$payment_method = $conn->real_escape_string($input['payment_method'] ?? 'Cash');

$items = $input['items']; // { productId: { price, qty } }
$total = 0;
foreach ($items as $pid => $it) {
  $total += (float)$it['price'] * (int)$it['qty'];
}

$conn->begin_transaction();
try {
  // insert order
  $stmt = $conn->prepare("INSERT INTO orders (customer_id, order_date, status, payment_status, total_amount, payment_method) VALUES (?, NOW(), 'Pending', 'Unpaid', ?, ?)");
  $stmt->bind_param('ids', $customer_id, $total, $payment_method);
  $stmt->execute();
  $order_id = $stmt->insert_id;
  $stmt->close();

  // insert items and deduct stock
  $ins = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
  $upd = $conn->prepare("UPDATE products SET stock = stock - ? WHERE product_id = ? AND stock >= ?");
  foreach ($items as $pid => $it) {
    $pid = intval($pid);
    $qty = intval($it['qty']);
    $price = floatval($it['price']);
    $subtotal = round($price * $qty,2);
    $ins->bind_param('iiidd', $order_id, $pid, $qty, $price, $subtotal);
    $ins->execute();

    // deduct product stock (ensure stock >= qty)
    $upd->bind_param('iii', $qty, $pid, $qty);
    $upd->execute();
    if ($upd->affected_rows === 0) {
      throw new Exception("Insufficient stock for product ID {$pid}");
    }
    $total -= 0; // keep total as computed
  }
  $ins->close();
  $upd->close();

  $conn->commit();
  echo json_encode(['success'=>true,'order_id'=>$order_id]);
} catch (Exception $e) {
  $conn->rollback();
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
