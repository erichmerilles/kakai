<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';
require_once __DIR__ . '/../utils/logger.php';

header('Content-Type: application/json');

// json payload and form data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
  $data = $_POST;
}

$item_id = intval($data['item_id'] ?? 0);
$type = ($data['type'] ?? 'OUT') === 'IN' ? 'IN' : 'OUT';
$qty = intval($data['quantity'] ?? 0);
$remarks = trim($data['remarks'] ?? '');
$user_id = $_SESSION['user_id'] ?? null;

// check permissions
if ($type === 'IN' && !hasPermission('inv_stock_in')) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized: You do not have permission to process Stock IN.']);
  exit;
}
if ($type === 'OUT' && !hasPermission('inv_stock_out')) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized: You do not have permission to process Stock OUT.']);
  exit;
}

if (!$item_id || $qty <= 0) {
  echo json_encode(['success' => false, 'message' => 'Invalid payload: Item ID and a positive quantity are required.']);
  exit;
}

try {
  $pdo->beginTransaction();

  // insert movement
  $m = $pdo->prepare('INSERT INTO inventory_movements (item_id, user_id, `type`, quantity, remarks, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
  $m->execute([$item_id, $user_id, $type, $qty, $remarks]);

  // update inventory safely
  if ($type === 'IN') {
    $u = $pdo->prepare('UPDATE inventory SET quantity = quantity + ? WHERE item_id = ?');
    $u->execute([$qty, $item_id]);
  } else {
    $u = $pdo->prepare('UPDATE inventory SET quantity = GREATEST(quantity - ?, 0) WHERE item_id = ?');
    $u->execute([$qty, $item_id]);
  }

  // check low stock, get item name, and insert notification if needed
  $s = $pdo->prepare('SELECT item_name, quantity, reorder_level FROM inventory WHERE item_id = ?');
  $s->execute([$item_id]);
  $it = $s->fetch(PDO::FETCH_ASSOC);

  $itemName = "Unknown Item (ID: $item_id)"; // fallback name

  if ($it) {
    $itemName = $it['item_name'];
    $status = 'Available';
    if ($it['quantity'] <= 0) {
      $status = 'Out of Stock';
    } elseif ($it['quantity'] <= $it['reorder_level']) {
      $status = 'Low Stock';
    }

    $upd = $pdo->prepare('UPDATE inventory SET status = ? WHERE item_id = ?');
    $upd->execute([$status, $item_id]);

    if ($status === 'Low Stock' || $status === 'Out of Stock') {
      // create notification
      $msg = "Inventory alert: $itemName reached {$it['quantity']} (reorder={$it['reorder_level']})";
      $n = $pdo->prepare('INSERT INTO notifications (employee_id, type, message, status, created_at) VALUES (NULL, ?, ?, ?, NOW())');
      $n->execute(['Inventory', $msg, 'Unread']);
    }
  }

  $pdo->commit();

  // log activity
  logActivity($pdo, $_SESSION['user_id'], 'Stock Update', 'Inventory', "Stock $type: $qty units for item: $itemName");

  echo json_encode(['success' => true, 'message' => 'Movement recorded successfully.']);
} catch (Exception $e) {
  $pdo->rollBack();
  echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
