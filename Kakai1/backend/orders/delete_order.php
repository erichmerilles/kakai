<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';

if (!hasPermission('order_delete')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = intval($input['order_id'] ?? 0);

if (!$order_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Order ID.']);
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Get items to revert stock
    $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

    foreach ($items as $item) {
        // 2. Revert stock in inventory
        $upd = $pdo->prepare("UPDATE inventory SET quantity = quantity + ?, status = 'Available' WHERE item_id = ?");
        $upd->execute([$item['quantity'], $item['product_id']]);

        // 3. Record 'Stock IN' movement for audit trail
        $move = $pdo->prepare("INSERT INTO inventory_movements (item_id, user_id, type, quantity, remarks, created_at) VALUES (?, ?, 'IN', ?, ?, NOW())");
        $remarks = "Stock returned from deleted Order #$order_id";
        $move->execute([$item['product_id'], $_SESSION['user_id'], $item['quantity'], $remarks]);
    }

    // 4. Delete Order (Order items will be deleted automatically if you have ON DELETE CASCADE)
    // If not, delete order_items first:
    $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);
    $pdo->prepare("DELETE FROM orders WHERE order_id = ?")->execute([$order_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Order deleted and stock reverted.']);
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
