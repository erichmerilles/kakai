<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// role validation
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

// check permissions
requirePermission('inv_view');
$activeModule = 'inventory';

$successMsg = '';
$errorMsg = '';

// handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // add category
    if (isset($_POST['action']) && $_POST['action'] === 'add') {
        if (!hasPermission('inv_add')) {
            die("Permission denied.");
        }

        $name = trim($_POST['category_name']);
        $desc = trim($_POST['description']);

        if (empty($name)) {
            $errorMsg = "Category Name is required.";
        } else {
            try {
                // Check if exists
                $check = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = ?");
                $check->execute([$name]);
                if ($check->rowCount() > 0) {
                    $errorMsg = "Category already exists.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO categories (category_name, description) VALUES (?, ?)");
                    $stmt->execute([$name, $desc]);
                    $successMsg = "Category added successfully!";
                }
            } catch (PDOException $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }

    // edit category
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        if (!hasPermission('inv_edit')) {
            die("Permission denied.");
        }

        $id = intval($_POST['category_id']);
        $name = trim($_POST['category_name']);
        $desc = trim($_POST['description']);

        if (empty($name)) {
            $errorMsg = "Category Name is required.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE categories SET category_name = ?, description = ? WHERE category_id = ?");
                $stmt->execute([$name, $desc, $id]);
                $successMsg = "Category updated successfully!";
            } catch (PDOException $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }

    // delete category
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        if (!hasPermission('inv_delete')) {
            die("Permission denied.");
        }
        $id = intval($_POST['category_id']);
        try {
            // Check if used before deleting
            $check = $pdo->prepare("SELECT item_id FROM inventory WHERE category_id = ?");
            $check->execute([$id]);
            if ($check->rowCount() > 0) {
                $errorMsg = "Cannot delete: This category is currently used by items in your inventory. Reassign or delete those items first.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
                $stmt->execute([$id]);
                $successMsg = "Category deleted successfully!";
            }
        } catch (PDOException $e) {
            $errorMsg = "Error: " . $e->getMessage();
        }
    }
}

// fetch categories
$categories = [];
try {
    $stmt = $pdo->query("SELECT * FROM categories ORDER BY category_name ASC");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // hanlde error
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories | KakaiOne</title>
    <?php include '../includes/links.php'; ?>
</head>

<body class="bg-light">

    <?php include '../includes/sidebar.php'; ?>

    <main id="main-content" class="main-content-wrapper">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold text-dark">
                    <i class="bi bi-tags-fill me-2 text-warning"></i>Manage Categories
                </h3>
                <div class="d-flex gap-2">
                    <a href="inventory_overview.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Inventory
                    </a>
                    <?php if (hasPermission('inv_add')): ?>
                        <button class="btn btn-warning fw-bold" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="bi bi-plus-lg"></i> Add Category
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($successMsg): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i> <?= $successMsg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= $errorMsg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Category Name</th>
                                    <th>Description</th>
                                    <?php if (hasPermission('inv_edit') || hasPermission('inv_delete')): ?>
                                        <th class="text-end pe-4">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($categories)): ?>
                                    <?php foreach ($categories as $cat): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($cat['category_name']) ?></td>
                                            <td class="text-muted"><?= htmlspecialchars($cat['description']) ?></td>

                                            <?php if (hasPermission('inv_edit') || hasPermission('inv_delete')): ?>
                                                <td class="text-end pe-4">
                                                    <?php if (hasPermission('inv_edit')): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editModal<?= $cat['category_id'] ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if (hasPermission('inv_delete')): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                            onclick="confirmDelete(<?= $cat['category_id'] ?>, '<?= htmlspecialchars($cat['category_name']) ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-5 text-muted">
                                            <i class="bi bi-tags display-6 d-block mb-3 opacity-25"></i>
                                            No categories found.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title fw-bold">Add New Category</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label class="form-label fw-bold">Category Name <span class="text-danger">*</span></label>
                        <input type="text" name="category_name" class="form-control" placeholder="e.g., Biscuits" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">Description</label>
                        <textarea name="description" class="form-control" rows="3" placeholder="Optional details..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold">Add Category</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($categories as $cat): ?>
        <div class="modal fade" id="editModal<?= $cat['category_id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">Edit Category</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Category Name <span class="text-danger">*</span></label>
                            <input type="text" name="category_name" class="form-control" value="<?= htmlspecialchars($cat['category_name']) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Description</label>
                            <textarea name="description" class="form-control" rows="3"><?= htmlspecialchars($cat['description']) ?></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

    <form id="deleteForm" method="POST" style="display:none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="category_id" id="deleteId">
    </form>

    <script>
        function confirmDelete(id, name) {
            Swal.fire({
                title: 'Delete Category?',
                text: `Are you sure you want to delete "${name}"? This cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteId').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }
    </script>

</body>

</html>