<?php
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request method"]);
    exit;
}

$item_name      = trim($_POST['item_name'] ?? '');
$category_id    = intval($_POST['category_id'] ?? 0); 
$quantity       = intval($_POST['quantity'] ?? 0);
$unit_price     = floatval($_POST['unit_price'] ?? 0.0);
$reorder_level  = intval($_POST['reorder_level'] ?? 10);
$supplier_id    = intval($_POST['supplier_id'] ?? 0); 

if (empty($item_name)) {
    echo json_encode(["success" => false, "message" => "Item name is required"]);
    exit;
}

// Determine status
$status = 'Available';
if ($quantity <= 0) $status = 'Out of Stock';
elseif ($quantity <= $reorder_level) $status = 'Low Stock';

$stmt = $conn->prepare("
    INSERT INTO inventory 
    (item_name, category_id, quantity, unit_price, reorder_level, supplier_id, status)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "siiddis", 
    $item_name,
    $category_id,
    $quantity,
    $unit_price,
    $reorder_level,
    $supplier_id,
    $status
);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Item added successfully"]);
} else {
    // Log error
    error_log("Inventory item insert failed: " . $stmt->error);
    echo json_encode(["success" => false, "message" => "Insert failed due to a database error."]);
}
$stmt->close();
?>