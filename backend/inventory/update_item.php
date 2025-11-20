<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$item_id = intval($data['item_id'] ?? 0);
$name = $data['item_name'] ?? '';
$category = $data['category_id'] ?? null;
$quantity = intval($data['quantity'] ?? 0);
$unit_price = floatval($data['unit_price'] ?? 0);
$reorder = intval($data['reorder_level'] ?? 10);
$supplier = $data['supplier_id'] ?? null;

try {
  // fetch current qty
  $s = $pdo->prepare('SELECT quantity FROM inventory WHERE item_id = ?');
  $s->execute([$item_id]);
  $orig = $s->fetch(PDO::FETCH_ASSOC);
  $orig_qty = $orig ? intval($orig['quantity']) : 0;

  $status = 'Available';
  if ($quantity <= 0) $status = 'Out of Stock';
  elseif ($quantity <= $reorder) $status = 'Low Stock';

  $stmt = $pdo->prepare('UPDATE inventory SET item_name=?, category_id=?, quantity=?, unit_price=?, reorder_level=?, supplier_id=?, status=? WHERE item_id=?');
  $stmt->execute([$name, $category, $quantity, $unit_price, $reorder, $supplier, $status, $item_id]);

  // record adjustment movement if qty changed
  $delta = $quantity - $orig_qty;
  if ($delta != 0) {
    $type = $delta > 0 ? 'IN' : 'OUT';
    $m = $pdo->prepare('INSERT INTO inventory_movements (item_id, `type`, quantity, remarks) VALUES (?, ?, ?, ?)');
    $m->execute([$item_id, $type, abs($delta), 'adjustment']);
  }

  if ($status === 'Low Stock' || $status === 'Out of Stock') {
    $msg = "Inventory alert: item_id={$item_id} now {$quantity} (reorder={$reorder})";
    $n = $pdo->prepare('INSERT INTO notifications (employee_id, type, message, status) VALUES (NULL, ?, ?, ?)');
    $n->execute(['Inventory', $msg, 'Unread']);
  }

  echo json_encode(['success'=>true,'message'=>'Item updated']);
} catch (Exception $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
