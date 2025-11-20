<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$item_id = intval($data['item_id'] ?? 0);
$type = ($data['type'] ?? 'OUT') === 'IN' ? 'IN' : 'OUT';
$qty = intval($data['quantity'] ?? 0);
$remarks = $data['remarks'] ?? null;

if (!$item_id || $qty <= 0) {
  echo json_encode(['success'=>false,'message'=>'Invalid payload']);
  exit;
}

try {
  $pdo->beginTransaction();

  // insert movement
  $m = $pdo->prepare('INSERT INTO inventory_movements (item_id, `type`, quantity, remarks) VALUES (?, ?, ?, ?)');
  $m->execute([$item_id, $type, $qty, $remarks]);

  // update inventory safely
  if ($type === 'IN') {
    $u = $pdo->prepare('UPDATE inventory SET quantity = quantity + ? WHERE item_id = ?');
    $u->execute([$qty, $item_id]);
  } else {
    $u = $pdo->prepare('UPDATE inventory SET quantity = GREATEST(quantity - ?, 0) WHERE item_id = ?');
    $u->execute([$qty, $item_id]);
  }

  // check low stock and insert notification if needed
  $s = $pdo->prepare('SELECT quantity, reorder_level FROM inventory WHERE item_id = ?');
  $s->execute([$item_id]);
  $it = $s->fetch(PDO::FETCH_ASSOC);
  if ($it) {
    $status = 'Available';
    if ($it['quantity'] <= 0) $status = 'Out of Stock';
    elseif ($it['quantity'] <= $it['reorder_level']) $status = 'Low Stock';

    $upd = $pdo->prepare('UPDATE inventory SET status = ? WHERE item_id = ?');
    $upd->execute([$status, $item_id]);

    if ($status === 'Low Stock' || $status === 'Out of Stock') {
      // create notification (employee_id NULL means system notification)
      $msg = "Inventory alert: item_id={$item_id} reached {$it['quantity']} (reorder={$it['reorder_level']})";
      $n = $pdo->prepare('INSERT INTO notifications (employee_id, type, message, status) VALUES (NULL, ?, ?, ?)');
      $n->execute(['Inventory', $msg, 'Unread']);
    }
  }

  $pdo->commit();
  echo json_encode(['success'=>true,'message'=>'Movement recorded']);
} catch (Exception $e) {
  $pdo->rollBack();
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
