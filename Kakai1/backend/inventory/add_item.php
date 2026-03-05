<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';
require_once __DIR__ . '/../utils/logger.php';

header('Content-Type: application/json');

// Check permissions
if (!hasPermission('inv_add')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: You do not have permission to add inventory items.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Invalid request"]);
    exit;
}

// Sanitize inputs
$item_name     = trim($_POST['item_name'] ?? '');
$category_id   = !empty($_POST['category_id']) ? intval($_POST['category_id']) : null;
$quantity      = intval($_POST['quantity'] ?? 0);
$unit_price    = floatval($_POST['unit_price'] ?? 0);
$reorder_level = intval($_POST['reorder_level'] ?? 10);
$supplier_id   = !empty($_POST['supplier_id']) ? intval($_POST['supplier_id']) : null;

if (empty($item_name)) {
    echo json_encode(["success" => false, "message" => "Item name is required"]);
    exit;
}

// Handle Image Upload
$image_path = '';
if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../../frontend/assets/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $fileExtension = strtolower(pathinfo($_FILES['item_image']['name'], PATHINFO_EXTENSION));
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($fileExtension, $allowedExtensions)) {
        $newFileName = md5(time() . $item_name) . '.' . $fileExtension;
        if (move_uploaded_file($_FILES['item_image']['tmp_name'], $uploadDir . $newFileName)) {
            $image_path = 'assets/uploads/' . $newFileName;
        }
    }
}

// Determine initial status
$status = 'Available';
if ($quantity <= 0) {
    $status = 'Out of Stock';
} elseif ($quantity <= $reorder_level) {
    $status = 'Low Stock';
}

try {
    $pdo->beginTransaction(); // Use transaction for data integrity

    // Insert new item
    $stmt = $pdo->prepare("
        INSERT INTO inventory (item_name, category_id, quantity, unit_price, reorder_level, supplier_id, status, image_path, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        $item_name,
        $category_id,
        $quantity,
        $unit_price,
        $reorder_level,
        $supplier_id,
        $status,
        $image_path
    ]);

    $newItemId = $pdo->lastInsertId(); // Get the ID for the movement record

    // Record the initial stock movement
    if ($quantity > 0) {
        $moveSql = "INSERT INTO inventory_movements (item_id, user_id, type, quantity, remarks, created_at) 
                    VALUES (?, ?, 'IN', ?, 'Initial stock upon product creation', NOW())";
        $moveStmt = $pdo->prepare($moveSql);
        $moveStmt->execute([$newItemId, $_SESSION['user_id'], $quantity]);
    }

    $pdo->commit();

    // Log activity
    logActivity($pdo, $_SESSION['user_id'], 'Create', 'Inventory', "Added new inventory item: $item_name (Initial Qty: $quantity)");

    echo json_encode(["success" => true, "message" => "Item added and stock movement recorded successfully"]);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
