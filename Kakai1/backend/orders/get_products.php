<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

/**
 * Fetch product list from the inventory table.
 * We map the inventory columns to the keys expected by the ordering frontend.
 */
try {
    // We use item_id as product_id, item_name as product_name, unit_price as price, and quantity as stock
    $query = "SELECT 
                item_id as product_id, 
                item_name as product_name, 
                unit_price as price, 
                quantity as stock 
              FROM inventory 
              WHERE status != 'Out of Stock' 
              ORDER BY item_name ASC";

    // Using mysqli as per your existing backend order files
    $result = $conn->query($query);

    if ($result) {
        $data = $result->fetch_all(MYSQLI_ASSOC);
        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
