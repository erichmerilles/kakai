<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';

// check permissions
if (!hasPermission('order_view')) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized: You do not have permission to update order statuses.'
    ]);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$order_id = intval($input['order_id'] ?? 0);
$new_status = trim($input['status'] ?? '');

if (!$order_id || empty($new_status)) {
    echo json_encode(['success' => false, 'message' => 'Missing order ID or status.']);
    exit;
}

try {
    $conn->begin_transaction();

    // get current order status
    $stmt = $conn->prepare("SELECT status, payment_status FROM orders WHERE order_id = ?");
    $stmt->bind_param('i', $order_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $order = $result->fetch_assoc();
    $stmt->close();

    if (!$order) {
        throw new Exception("Order not found.");
    }

    $old_status = $order['status'];

    // 1. Logic for CANCELLATION (Reverting Stock)
    if ($new_status === 'Cancelled' && $old_status !== 'Cancelled') {
        $itemStmt = $conn->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $itemStmt->bind_param('i', $order_id);
        $itemStmt->execute();
        $items = $itemStmt->get_result();

        while ($item = $items->fetch_assoc()) {
            $p_id = $item['product_id'];
            $qty = $item['quantity'];

            $updateInv = $conn->prepare("UPDATE inventory SET quantity = quantity + ?, status = 'Available' WHERE item_id = ?");
            $updateInv->bind_param('ii', $qty, $p_id);
            $updateInv->execute();
            $updateInv->close();

            $moveStmt = $conn->prepare("INSERT INTO inventory_movements (item_id, user_id, type, quantity, remarks) VALUES (?, ?, 'IN', ?, ?)");
            $remarks = "Stock restored from Cancelled Order #$order_id";
            $u_id = $_SESSION['user_id'];
            $moveStmt->bind_param('iiis', $p_id, $u_id, $qty, $remarks);
            $moveStmt->execute();
            $moveStmt->close();
        }
        $itemStmt->close();
    }

    // 2. Prepare the Update Statement
    // We update payment_status to 'Paid' ONLY if status is 'Completed'
    if ($new_status === 'Completed') {
        $stmt = $conn->prepare("UPDATE orders SET status = ?, payment_status = 'Paid' WHERE order_id = ?");
    } else {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
    }

    $stmt->bind_param('si', $new_status, $order_id);

    // 3. Check for execution success, not just affected rows
    if (!$stmt->execute()) {
        throw new Exception("Database update failed: " . $stmt->error);
    }
    $stmt->close();

    $conn->commit();

    echo json_encode([
        'success' => true,
        'message' => "Order #$order_id successfully updated to $new_status."
    ]);
} catch (Exception $e) {
    if (isset($conn)) $conn->rollback();
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

if (isset($conn)) $conn->close();
