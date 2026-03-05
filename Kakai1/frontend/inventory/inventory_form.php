<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// set active module
$activeModule = 'inventory';

// role validation
if (!isset($_SESSION['user_id'])) {
  header('Location: ../../index.php');
  exit;
}

// add/edit logic
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = ($id > 0);

// check permissions
if ($isEdit) {
  requirePermission('inv_edit');
} else {
  requirePermission('inv_add');
}

// initialize empty item data
$item = [
  'item_name' => '',
  'category_id' => '',
  'quantity' => 0,
  'unit_price' => 0.00,
  'supplier_id' => '',
  'reorder_level' => 10,
  'status' => 'Available',
  'image_path' => ''
];

$errorMsg = '';
$successMsg = '';

// handle add category form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
  $newCatName = trim($_POST['new_category_name']);
  if (!empty($newCatName)) {
    try {
      $check = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = ?");
      $check->execute([$newCatName]);
      if ($check->rowCount() > 0) {
        $errorMsg = "Category '$newCatName' already exists.";
      } else {
        $stmt = $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)");
        $stmt->execute([$newCatName]);
        $successMsg = "Category '$newCatName' added successfully!";
      }
    } catch (PDOException $e) {
      $errorMsg = "Error adding category: " . $e->getMessage();
    }
  } else {
    $errorMsg = "Category name cannot be empty.";
  }
}

// handle add/edit item form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action'])) {
  $name = trim($_POST['item_name']);
  $category_id = intval($_POST['category_id']);
  $qty = intval($_POST['quantity']);
  $price = floatval($_POST['unit_price']);
  $supplier_id = intval($_POST['supplier_id']);
  $reorder = intval($_POST['reorder_level']);
  $status = ($qty == 0) ? 'Out of Stock' : (($qty <= $reorder) ? 'Low Stock' : 'Available');

  // --- DUPLICATE NAME CHECK ---
  try {
    if ($isEdit) {
      $dupCheck = $pdo->prepare("SELECT item_id FROM inventory WHERE item_name = ? AND item_id != ?");
      $dupCheck->execute([$name, $id]);
    } else {
      $dupCheck = $pdo->prepare("SELECT item_id FROM inventory WHERE item_name = ?");
      $dupCheck->execute([$name]);
    }

    if ($dupCheck->rowCount() > 0) {
      $errorMsg = "Duplicate Entry: An item named '$name' already exists in the inventory.";
    }
  } catch (PDOException $e) {
    $errorMsg = "Validation error: " . $e->getMessage();
  }

  // Proceed only if no duplicate error was found
  if (empty($errorMsg)) {
    // FILE UPLOAD LOGIC
    $finalImagePath = $_POST['current_image'] ?? '';
    if (isset($_FILES['item_image']) && $_FILES['item_image']['error'] === UPLOAD_ERR_OK) {
      $fileTmpPath = $_FILES['item_image']['tmp_name'];
      $fileName = $_FILES['item_image']['name'];
      $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
      $allowed = array('jpg', 'png', 'jpeg', 'webp');

      if (in_array($fileExtension, $allowed)) {
        $newFileName = md5(time() . $fileName) . '.' . $fileExtension;
        $uploadFileDir = __DIR__ . '/../assets/uploads/';
        if (!is_dir($uploadFileDir)) {
          mkdir($uploadFileDir, 0755, true);
        }
        if (move_uploaded_file($fileTmpPath, $uploadFileDir . $newFileName)) {
          $finalImagePath = 'assets/uploads/' . $newFileName;
        }
      }
    }

    if (empty($name) || empty($category_id)) {
      $errorMsg = "Item Name and Category are required.";
      $item = $_POST;
    } else {
      try {
        $pdo->beginTransaction();

        if ($isEdit) {
          $oldStmt = $pdo->prepare("SELECT quantity FROM inventory WHERE item_id = ?");
          $oldStmt->execute([$id]);
          $oldQty = $oldStmt->fetchColumn();

          $sql = "UPDATE inventory 
                            SET item_name = ?, category_id = ?, quantity = ?, unit_price = ?, supplier_id = ?, reorder_level = ?, status = ?, image_path = ?
                            WHERE item_id = ?";
          $stmt = $pdo->prepare($sql);
          $stmt->execute([$name, $category_id, $qty, $price, $supplier_id, $reorder, $status, $finalImagePath, $id]);

          if ($qty != $oldQty) {
            $diff = abs($qty - $oldQty);
            $moveType = ($qty > $oldQty) ? 'IN' : 'OUT';
            $remarks = "Manual update: Quantity changed from $oldQty to $qty";
            $moveSql = "INSERT INTO inventory_movements (item_id, user_id, type, quantity, remarks, created_at) VALUES (?, ?, ?, ?, ?, NOW())";
            $pdo->prepare($moveSql)->execute([$id, $_SESSION['user_id'], $moveType, $diff, $remarks]);
          }
          $successMsg = "Item updated successfully!";
        } else {
          $sql = "INSERT INTO inventory (item_name, category_id, quantity, unit_price, supplier_id, reorder_level, status, image_path, created_at) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())";
          $stmt = $pdo->prepare($sql);
          $stmt->execute([$name, $category_id, $qty, $price, $supplier_id, $reorder, $status, $finalImagePath]);

          $newItemId = $pdo->lastInsertId();
          if ($qty > 0) {
            $moveSql = "INSERT INTO inventory_movements (item_id, user_id, type, quantity, remarks, created_at) VALUES (?, ?, 'IN', ?, 'Initial stock creation', NOW())";
            $pdo->prepare($moveSql)->execute([$newItemId, $_SESSION['user_id'], $qty]);
          }
          $successMsg = "New item added successfully!";
        }
        $pdo->commit();
      } catch (PDOException $e) {
        $pdo->rollBack();
        $errorMsg = "Database Error: " . $e->getMessage();
        $item = $_POST;
      }
    }
  } else {
    // If duplicate found, keep the form data populated
    $item = $_POST;
    if ($isEdit) $item['image_path'] = $_POST['current_image'];
  }
}

// fetch dropdown data
try {
  $suppliers = $pdo->query("SELECT * FROM suppliers ORDER BY supplier_name ASC")->fetchAll(PDO::FETCH_ASSOC);
  $categories = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC")->fetchAll(PDO::FETCH_ASSOC);
  if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM inventory WHERE item_id = ?");
    $stmt->execute([$id]);
    $item = $stmt->fetch(PDO::FETCH_ASSOC) ?: $item;
  }
} catch (PDOException $e) {
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isEdit ? 'Edit Item' : 'Add Item' ?> | KakaiOne</title>
  <?php include '../includes/links.php'; ?>
</head>

<body class="bg-light">
  <?php include '../includes/sidebar.php'; ?>

  <main id="main-content" style="margin-left: 260px; padding: 25px; transition: margin-left 0.3s;">
    <div class="container-fluid">
      <div class="d-flex justify-content-between align-items-start mb-4">
        <h3 class="fw-bold text-dark mt-2">
          <i class="bi bi-box-seam me-2 text-warning"></i>
          <?= $isEdit ? 'Edit Inventory Item' : 'Add New Item' ?>
        </h3>
        <div class="d-flex flex-column gap-2 text-end">
          <a href="inventory_overview.php" class="btn btn-secondary btn-sm px-3">
            <i class="bi bi-arrow-left"></i> Back to List
          </a>
          <button type="button" class="btn btn-outline-warning text-dark btn-sm fw-bold px-3" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
            <i class="bi bi-plus-circle"></i> Add Category
          </button>
        </div>
      </div>

      <div class="card shadow-sm border-0">
        <div class="card-header bg-warning bg-opacity-10 border-bottom border-warning border-opacity-25">
          <h6 class="mb-0 fw-bold text-dark">Item Details</h6>
        </div>
        <div class="card-body p-4">
          <form method="POST" enctype="multipart/form-data" id="inventoryForm">
            <div class="row g-3">
              <div class="col-md-12 mb-2">
                <label class="form-label fw-bold">Product Image</label>
                <div class="d-flex align-items-center gap-3 p-3 border rounded bg-white">
                  <div class="border rounded p-1 shadow-sm" style="width: 80px; height: 80px; overflow: hidden; background: #f8f9fa;">
                    <?php if (!empty($item['image_path'])): ?>
                      <img src="/kakai1/frontend/<?= $item['image_path'] ?>" class="w-100 h-100" style="object-fit: cover;">
                    <?php else: ?>
                      <div class="h-100 d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image fs-3"></i></div>
                    <?php endif; ?>
                  </div>
                  <div class="flex-grow-1">
                    <input type="file" name="item_image" class="form-control" accept="image/*">
                    <input type="hidden" name="current_image" value="<?= htmlspecialchars($item['image_path'] ?? '') ?>">
                  </div>
                </div>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-bold small">Item Name <span class="text-danger">*</span></label>
                <input type="text" id="itemNameInput" name="item_name" class="form-control" value="<?= htmlspecialchars($item['item_name']) ?>" required>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-bold small">Category <span class="text-danger">*</span></label>
                <select name="category_id" class="form-select" required>
                  <option value="">Select Category</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['category_id'] ?>" <?= ($item['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($cat['category_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-4">
                <label class="form-label fw-bold small">Current Stock</label>
                <input type="number" name="quantity" class="form-control" value="<?= $item['quantity'] ?>" min="0" required>
              </div>

              <div class="col-md-4">
                <label class="form-label fw-bold small">Unit Price (₱)</label>
                <input type="number" step="0.01" name="unit_price" class="form-control" value="<?= $item['unit_price'] ?>">
              </div>

              <div class="col-md-4">
                <label class="form-label fw-bold small">Supplier</label>
                <select name="supplier_id" class="form-select" required>
                  <option value="">Select Supplier</option>
                  <?php foreach ($suppliers as $sup): ?>
                    <option value="<?= $sup['supplier_id'] ?>" <?= ($item['supplier_id'] == $sup['supplier_id']) ? 'selected' : '' ?>>
                      <?= htmlspecialchars($sup['supplier_name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label fw-bold small">Reorder Level</label>
                <input type="number" name="reorder_level" class="form-control" value="<?= $item['reorder_level'] ?>" min="1">
                <div class="form-text">Flag as "Low Stock" when quantity drops below this.</div>
              </div>

              <div class="col-12 mt-5 pt-3 border-top text-end">
                <a href="inventory_overview.php" class="btn btn-light border me-2">Cancel</a>
                <button type="button" class="btn btn-warning px-5 fw-bold shadow-sm" onclick="confirmSubmission()">
                  <i class="bi bi-save me-1"></i> <?= $isEdit ? 'Save Changes' : 'Add Item' ?>
                </button>
              </div>
            </div>
          </form>
        </div>
      </div>
    </div>
  </main>

  <div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog modal-sm modal-dialog-centered">
      <form method="POST" class="modal-content shadow border-0">
        <div class="modal-header bg-warning py-2">
          <h6 class="modal-title fw-bold">Add New Category</h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="action" value="add_category">
          <label class="form-label small fw-bold">Category Name</label>
          <input type="text" name="new_category_name" class="form-control" required placeholder="Enter name...">
        </div>
        <div class="modal-footer p-1">
          <button type="submit" class="btn btn-sm btn-dark w-100">Add Category</button>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    // Form Confirmation Swal
    function confirmSubmission() {
      const form = document.getElementById('inventoryForm');
      if (!form.checkValidity()) {
        form.reportValidity();
        return;
      }

      Swal.fire({
        title: 'Confirm Save',
        text: "Do you want to save these inventory details?",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Save it!'
      }).then((result) => {
        if (result.isConfirmed) {
          form.submit();
        }
      });
    }
  </script>

  <?php if ($successMsg): ?>
    <script>
      Swal.fire({
        icon: 'success',
        title: 'Success!',
        text: '<?= addslashes($successMsg) ?>',
        timer: 2000,
        showConfirmButton: false
      }).then(() => {
        window.location.href = 'inventory_overview.php';
      });
    </script>
  <?php endif; ?>

  <?php if ($errorMsg): ?>
    <script>
      Swal.fire({
        icon: 'error',
        title: 'Action Denied',
        text: '<?= addslashes($errorMsg) ?>',
        confirmButtonColor: '#dc3545'
      }).then(() => {
        const input = document.getElementById('itemNameInput');
        if (input && ('<?= addslashes($errorMsg) ?>'.includes('Entry') || '<?= addslashes($errorMsg) ?>'.includes('Duplicate'))) {
          input.classList.add('is-invalid');
          input.focus();
          input.addEventListener('input', function() {
            this.classList.remove('is-invalid');
          }, {
            once: true
          });
        }
      });
    </script>
  <?php endif; ?>
</body>

</html>