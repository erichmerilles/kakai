<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// 1. Sidebar Highlight & Auth
$activeModule = 'inventory';
if (!isset($_SESSION['user_id'])) {
  header('Location: ../../index.php');
  exit;
}
requirePermission('inv_add'); // OR 'inv_edit' depending on action

// 2. Initialize Variables
$isEdit = false;
$item = [
  'name' => '',
  'category' => '',
  'price' => '',
  'stock' => '',
  'reorder_level' => '',
  'status' => 'Active'
];

// 3. Check if Edit Mode
if (isset($_GET['id'])) {
  $isEdit = true;
  $itemId = intval($_GET['id']);
  try {
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE item_id = ?");
    $stmt->execute([$itemId]);
    $fetched = $stmt->fetch();
    if ($fetched) $item = $fetched;
  } catch (PDOException $e) { /* Handle error */
  }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isEdit ? 'Edit Item' : 'Add Item' ?> | KakaiOne</title>
  <?php include '../includes/links.php'; ?>
</head>

<body class="bg-light">

  <?php include '../includes/sidebar.php'; ?>

  <div id="dashboardContainer">
    <main id="main-content" style="margin-left: 250px; padding: 25px; transition: margin-left 0.3s;">
      <div class="container">

        <div class="d-flex justify-content-between align-items-center mb-4">
          <h3 class="fw-bold">
            <i class="bi bi-box-seam-fill me-2 text-warning"></i><?= $isEdit ? 'Edit Inventory Item' : 'Add New Item' ?>
          </h3>
          <a href="inventory_overview.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back
          </a>
        </div>

        <div class="card shadow-sm">
          <div class="card-header bg-dark text-white">
            <i class="bi bi-pencil-square me-2"></i>Item Details
          </div>
          <div class="card-body">

            <form action="../../backend/inventory/save_item.php" method="POST">
              <?php if ($isEdit): ?>
                <input type="hidden" name="item_id" value="<?= $itemId ?>">
              <?php endif; ?>

              <div class="row g-3">
                <div class="col-md-6">
                  <label class="form-label fw-bold">Item Name</label>
                  <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($item['name']) ?>" required>
                </div>

                <div class="col-md-6">
                  <label class="form-label fw-bold">Category</label>
                  <select name="category" class="form-select" required>
                    <option value="">Select Category</option>
                    <option value="Raw Material" <?= $item['category'] == 'Raw Material' ? 'selected' : '' ?>>Raw Material</option>
                    <option value="Finished Good" <?= $item['category'] == 'Finished Good' ? 'selected' : '' ?>>Finished Good</option>
                    <option value="Packaging" <?= $item['category'] == 'Packaging' ? 'selected' : '' ?>>Packaging</option>
                  </select>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold">Price (₱)</label>
                  <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($item['price']) ?>" required>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold">Initial Stock</label>
                  <input type="number" name="stock" class="form-control" value="<?= htmlspecialchars($item['stock']) ?>" <?= $isEdit ? 'readonly' : '' ?> required>
                  <?php if ($isEdit): ?>
                    <small class="text-muted">Use 'Stock Movements' to adjust quantity.</small>
                  <?php endif; ?>
                </div>

                <div class="col-md-4">
                  <label class="form-label fw-bold">Reorder Level</label>
                  <input type="number" name="reorder_level" class="form-control" value="<?= htmlspecialchars($item['reorder_level']) ?>" required>
                </div>

                <div class="col-md-12">
                  <label class="form-label fw-bold">Status</label>
                  <div class="d-flex gap-4">
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="status" value="Active" id="statusActive" <?= $item['status'] == 'Active' ? 'checked' : '' ?>>
                      <label class="form-check-label" for="statusActive">Active</label>
                    </div>
                    <div class="form-check">
                      <input class="form-check-input" type="radio" name="status" value="Inactive" id="statusInactive" <?= $item['status'] == 'Inactive' ? 'checked' : '' ?>>
                      <label class="form-check-label" for="statusInactive">Inactive</label>
                    </div>
                  </div>
                </div>
              </div>

              <hr class="my-4">

              <div class="text-end">
                <button type="submit" class="btn btn-warning fw-bold text-dark px-4">
                  <i class="bi bi-save me-1"></i> Save Item
                </button>
              </div>
            </form>

          </div>
        </div>

      </div>
    </main>
  </div>
</body>

</html>