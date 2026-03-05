<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

try {
    $query = "
        SELECT i.*, 
               c.category_name AS category_name,
               s.supplier_name AS supplier_name
        FROM inventory i
        LEFT JOIN inventory_category c ON i.category_id = c.category_id
        LEFT JOIN supplier s ON i.supplier_id = s.supplier_id
        ORDER BY i.item_id DESC
    ";
    $stmt = $pdo->query($query);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "data" => $items]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
