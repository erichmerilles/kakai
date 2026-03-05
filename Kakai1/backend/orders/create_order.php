<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';
require_once __DIR__ . '/../utils/logger.php';

// Check permissions
if (!hasPermission('order_create')) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized: You do not have permission to create orders.']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || !isset($input['items'])) {
  echo json_encode(['success' => false, 'message' => 'Invalid payload']);
  exit;
}

$customer_id = !empty($input['customer_id']) ? intval($input['customer_id']) : null;
$payment_method = trim($input['payment_method'] ?? 'Cash');

// NEW: Automatic Business Rule
// Cash transactions are considered paid immediately at the point of sale.
$payment_status = ($payment_method === 'Cash') ? 'Paid' : 'Unpaid';

$items = $input['items'];
$total = 0;
foreach ($items as $pid => $it) {
  $total += (float)$it['price'] * (int)$it['qty'];
}

try {
  $pdo->beginTransaction();

  // 1. Insert the main order record with automatic payment status
  $stmt = $pdo->prepare("INSERT INTO orders (customer_id, order_date, status, payment_status, total_amount, payment_method) VALUES (?, NOW(), 'Pending', ?, ?, ?)");
  $stmt->execute([$customer_id, $payment_status, $total, $payment_method]);
  $order_id = $pdo->lastInsertId();

  // 2. Prepare statements for items and stock updates
  $insItem = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");

  // This query deducts stock and recalculates the item status automatically
  $updInv = $pdo->prepare("
        UPDATE inventory 
        SET quantity = quantity - ?, 
            status = CASE 
                WHEN (quantity - ?) <= 0 THEN 'Out of Stock' 
                WHEN (quantity - ?) <= reorder_level THEN 'Low Stock' 
                ELSE 'Available' 
            END 
        WHERE item_id = ? AND quantity >= ?
    ");

  // Statement for auditing stock movements
  $insMove = $pdo->prepare("INSERT INTO inventory_movements (item_id, user_id, type, quantity, remarks, created_at) VALUES (?, ?, 'OUT', ?, ?, NOW())");

  foreach ($items as $pid => $it) {
    $pid = intval($pid);
    $qty = intval($it['qty']);
    $price = floatval($it['price']);
    $subtotal = round($price * $qty, 2);

    // a. Record the individual order item
    $insItem->execute([$order_id, $pid, $qty, $price, $subtotal]);

    // b. Deduct stock and update status (passes qty 3 times for the CASE logic)
    $updInv->execute([$qty, $qty, $qty, $pid, $qty]);

    if ($updInv->rowCount() === 0) {
      // This triggers if the WHERE clause fails (quantity >= $qty)
      throw new Exception("Insufficient stock for item: " . ($it['product_name'] ?? "ID $pid"));
    }

    // c. Record a 'Stock OUT' movement for the audit trail
    $remarks = "Order #$order_id fulfillment";
    $insMove->execute([$pid, $_SESSION['user_id'], $qty, $remarks]);
  }

  $pdo->commit();

  // Log the overall system activity
  logActivity($pdo, $_SESSION['user_id'], 'Create', 'Ordering', "Processed order #$order_id ($payment_method) - Total: ₱" . number_format($total, 2));

  echo json_encode([
    'success' => true,
    'order_id' => $order_id,
    'message' => "Order placed successfully! Payment set to $payment_status."
  ]);
} catch (Exception $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
