<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';

header('Content-Type: application/json');

// check permissions
if (!hasPermission('inv_edit')) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized: You do not have permission to edit inventory items.']);
  exit;
}

// json payload and form data
$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
  $data = $_POST;
}

$item_id = intval($data['item_id'] ?? 0);
$name = trim($data['item_name'] ?? '');
$category = !empty($data['category_id']) ? $data['category_id'] : null;
$quantity = intval($data['quantity'] ?? 0);
$unit_price = floatval($data['unit_price'] ?? 0);
$reorder = intval($data['reorder_level'] ?? 10);
$supplier = !empty($data['supplier_id']) ? $data['supplier_id'] : null;
$user_id = $_SESSION['user_id'] ?? null;

if ($item_id === 0 || empty($name)) {
  echo json_encode(['success' => false, 'message' => 'Item ID and Name are required.']);
  exit;
}

try {
  // begin transaction
  $pdo->beginTransaction();

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
    // recent activity log
    $m = $pdo->prepare('INSERT INTO inventory_movements (item_id, user_id, `type`, quantity, remarks, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
    $m->execute([$item_id, $user_id, $type, abs($delta), 'Manual adjustment']);
  }

  if ($status === 'Low Stock' || $status === 'Out of Stock') {
    $msg = "Inventory alert: item_id={$item_id} now {$quantity} (reorder={$reorder})";
    $n = $pdo->prepare('INSERT INTO notifications (employee_id, type, message, status, created_at) VALUES (NULL, ?, ?, ?, NOW())');
    $n->execute(['Inventory', $msg, 'Unread']);
  }

  $pdo->commit();
  echo json_encode(['success' => true, 'message' => 'Item updated successfully.']);
} catch (Exception $e) {
  $pdo->rollBack();
  echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
