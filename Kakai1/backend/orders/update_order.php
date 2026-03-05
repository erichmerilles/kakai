<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';

$data = json_decode(file_get_contents('php://input'), true);
$order_id = intval($data['order_id'] ?? 0);
$customer_id = !empty($data['customer_id']) ? intval($data['customer_id']) : null;
$payment_method = trim($data['payment_method'] ?? 'Cash');
$items = $data['items']; // Updated cart object

if (!$order_id || empty($items)) {
    echo json_encode(['success' => false, 'message' => 'Cannot update an empty order.']);
    exit;
}

// NEW: Automatic Business Rule
// If payment method is updated to Cash, set status to Paid.
$payment_status = ($payment_method === 'Cash') ? 'Paid' : 'Unpaid';

try {
    $pdo->beginTransaction();

    // 1. Fetch OLD items and revert inventory stock
    $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
    $stmt->execute([$order_id]);
    $old_items = $stmt->fetchAll();

    foreach ($old_items as $old) {
        $revert = $pdo->prepare("UPDATE inventory SET quantity = quantity + ? WHERE item_id = ?");
        $revert->execute([$old['quantity'], $old['product_id']]);
    }

    // 2. Delete existing order items to start fresh
    $pdo->prepare("DELETE FROM order_items WHERE order_id = ?")->execute([$order_id]);

    // 3. Process NEW items and calculate total
    $total_amount = 0;
    foreach ($items as $product_id => $details) {
        $qty = intval($details['qty']);
        $price = floatval($details['price']);
        $subtotal = round($price * $qty, 2);
        $total_amount += $subtotal;

        // Insert new order item
        $ins = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
        $ins->execute([$order_id, $product_id, $qty, $price, $subtotal]);

        // Deduct new stock and update status based on reorder_level
        $updInv = $pdo->prepare("
            UPDATE inventory 
            SET quantity = quantity - ?, 
                status = CASE 
                    WHEN (quantity - ?) <= 0 THEN 'Out of Stock' 
                    WHEN (quantity - ?) <= reorder_level THEN 'Low Stock' 
                    ELSE 'Available' 
                END 
            WHERE item_id = ? AND (quantity - ?) >= 0
        ");
        $updInv->execute([$qty, $qty, $qty, $product_id, $qty]);

        if ($updInv->rowCount() === 0) {
            throw new Exception("Insufficient stock for item: " . ($details['product_name'] ?? "ID $product_id"));
        }
    }

    // 4. Update the main Order record with automatic payment status
    $updOrder = $pdo->prepare("UPDATE orders SET customer_id = ?, total_amount = ?, payment_method = ?, payment_status = ? WHERE order_id = ?");
    $updOrder->execute([$customer_id, $total_amount, $payment_method, $payment_status, $order_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => "Order #$order_id updated. Payment set to $payment_status."]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Update Error: ' . $e->getMessage()]);
}
