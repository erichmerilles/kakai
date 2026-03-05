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

        $name = trim($_POST['supplier_name']);
        $contact_person = trim($_POST['contact_person']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);

        if (empty($name)) {
            $errorMsg = "Supplier Name is required.";
        } else {
            try {
                // Check if exists
                $check = $pdo->prepare("SELECT supplier_id FROM suppliers WHERE supplier_name = ?");
                $check->execute([$name]);
                if ($check->rowCount() > 0) {
                    $errorMsg = "A supplier with this name already exists.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO suppliers (supplier_name, contact_person, phone, email, address, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
                    $stmt->execute([$name, $contact_person, $phone, $email, $address]);
                    $successMsg = "Supplier added successfully!";
                }
            } catch (PDOException $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }

    // edit supplier
    if (isset($_POST['action']) && $_POST['action'] === 'edit') {
        if (!hasPermission('inv_edit')) {
            die("Permission denied.");
        }

        $id = intval($_POST['supplier_id']);
        $name = trim($_POST['supplier_name']);
        $contact_person = trim($_POST['contact_person']);
        $phone = trim($_POST['phone']);
        $email = trim($_POST['email']);
        $address = trim($_POST['address']);

        if (empty($name)) {
            $errorMsg = "Supplier Name is required.";
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE suppliers SET supplier_name = ?, contact_person = ?, phone = ?, email = ?, address = ? WHERE supplier_id = ?");
                $stmt->execute([$name, $contact_person, $phone, $email, $address, $id]);
                $successMsg = "Supplier updated successfully!";
            } catch (PDOException $e) {
                $errorMsg = "Error: " . $e->getMessage();
            }
        }
    }

    // delete supplier
    if (isset($_POST['action']) && $_POST['action'] === 'delete') {
        if (!hasPermission('inv_delete')) {
            die("Permission denied.");
        }
        $id = intval($_POST['supplier_id']);
        try {
            // Check if used before deleting
            $check = $pdo->prepare("SELECT item_id FROM inventory WHERE supplier_id = ?");
            $check->execute([$id]);
            if ($check->rowCount() > 0) {
                $errorMsg = "Cannot delete: This supplier is currently attached to items in your inventory. Reassign or delete those items first.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
                $stmt->execute([$id]);
                $successMsg = "Supplier deleted successfully!";
            }
        } catch (PDOException $e) {
            $errorMsg = "Error: " . $e->getMessage();
        }
    }
}

// fetch suppliers
$suppliers = [];
try {
    $stmt = $pdo->query("SELECT * FROM suppliers ORDER BY supplier_name ASC");
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // handle error
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Suppliers | KakaiOne</title>
    <?php include '../includes/links.php'; ?>
</head>

<body class="bg-light">

    <?php include '../includes/sidebar.php'; ?>

    <main id="main-content" class="main-content-wrapper">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 class="fw-bold text-dark">
                    <i class="bi bi-truck me-2 text-warning"></i>Manage Suppliers
                </h3>
                <div class="d-flex gap-2">
                    <a href="inventory_overview.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Inventory
                    </a>
                    <?php if (hasPermission('inv_add')): ?>
                        <button class="btn btn-warning fw-bold" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                            <i class="bi bi-plus-lg"></i> Add Supplier
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
                                    <th class="ps-4">Company Name</th>
                                    <th>Contact Person</th>
                                    <th>Contact Info</th>
                                    <th>Address</th>
                                    <?php if (hasPermission('inv_edit') || hasPermission('inv_delete')): ?>
                                        <th class="text-end pe-4">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($suppliers)): ?>
                                    <?php foreach ($suppliers as $sup): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($sup['supplier_name']) ?></td>
                                            <td><?= htmlspecialchars($sup['contact_person'] ?? 'N/A') ?></td>
                                            <td>
                                                <div class="d-flex flex-column">
                                                    <?php if (!empty($sup['phone'])): ?>
                                                        <small><i class="bi bi-telephone text-muted me-1"></i><?= htmlspecialchars($sup['phone']) ?></small>
                                                    <?php endif; ?>
                                                    <?php if (!empty($sup['email'])): ?>
                                                        <small><i class="bi bi-envelope text-muted me-1"></i><?= htmlspecialchars($sup['email']) ?></small>
                                                    <?php endif; ?>
                                                    <?php if (empty($sup['phone']) && empty($sup['email'])): ?>
                                                        <span class="text-muted small">No contact info</span>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                            <td class="text-muted small">
                                                <span class="d-inline-block text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($sup['address']) ?>">
                                                    <?= htmlspecialchars($sup['address'] ?? 'N/A') ?>
                                                </span>
                                            </td>

                                            <?php if (hasPermission('inv_edit') || hasPermission('inv_delete')): ?>
                                                <td class="text-end pe-4">
                                                    <?php if (hasPermission('inv_edit')): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-primary me-1"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#editModal<?= $sup['supplier_id'] ?>">
                                                            <i class="bi bi-pencil"></i>
                                                        </button>
                                                    <?php endif; ?>

                                                    <?php if (hasPermission('inv_delete')): ?>
                                                        <button type="button" class="btn btn-sm btn-outline-danger"
                                                            onclick="confirmDelete(<?= $sup['supplier_id'] ?>, '<?= htmlspecialchars(addslashes($sup['supplier_name'])) ?>')">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-truck display-6 d-block mb-3 opacity-25"></i>
                                            No suppliers found.
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

    <div class="modal fade" id="addSupplierModal" tabindex="-1">
        <div class="modal-dialog">
            <form method="POST" class="modal-content">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title fw-bold">Add New Supplier</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Company Name <span class="text-danger">*</span></label>
                        <input type="text" name="supplier_name" class="form-control" placeholder="Enter company name..." required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" placeholder="Enter contact person...">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="Enter phone number...">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="Enter email address...">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold small">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Full business address..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning fw-bold">Add Supplier</button>
                </div>
            </form>
        </div>
    </div>

    <?php foreach ($suppliers as $sup): ?>
        <div class="modal fade" id="editModal<?= $sup['supplier_id'] ?>" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title fw-bold">Edit Supplier</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="supplier_id" value="<?= $sup['supplier_id'] ?>">

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="supplier_name" class="form-control" value="<?= htmlspecialchars($sup['supplier_name']) ?>" required>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small">Contact Person</label>
                                <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($sup['contact_person']) ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold text-dark small">Phone Number</label>
                                <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($sup['phone']) ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($sup['email']) ?>">
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark small">Address</label>
                            <textarea name="address" class="form-control" rows="2"><?= htmlspecialchars($sup['address']) ?></textarea>
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
        <input type="hidden" name="supplier_id" id="deleteId">
    </form>

    <script>
        function confirmDelete(id, name) {
            Swal.fire({
                title: 'Delete Supplier?',
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