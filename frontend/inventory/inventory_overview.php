<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
$activeModule = 'inventory';
require_once __DIR__ . '/../includes/auth_check.php'; // Ensure permissions

// role validation (Optional, based on your preference)
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}
requirePermission('inv_view');

// 1. FETCH INVENTORY ITEMS (Directory)
try {
    $stmt = $pdo->query("
        SELECT item_id, name, category, stock, reorder_level, price, status, created_at
        FROM inventory 
        ORDER BY name ASC
    ");
    $inventoryItems = $stmt->fetchAll();
} catch (PDOException $e) {
    $inventoryItems = [];
}

// 2. INVENTORY SUMMARY (Stats for Chart)
$inventoryStats = ['active' => 0, 'low_stock' => 0, 'out_of_stock' => 0];
try {
    // Count Active items (Stock > Reorder Level)
    $stmtActive = $pdo->query("SELECT COUNT(*) FROM inventory WHERE stock > reorder_level AND stock > 0");
    $inventoryStats['active'] = $stmtActive->fetchColumn();

    // Count Low Stock (0 < Stock <= Reorder Level)
    $stmtLow = $pdo->query("SELECT COUNT(*) FROM inventory WHERE stock <= reorder_level AND stock > 0");
    $inventoryStats['low_stock'] = $stmtLow->fetchColumn();

    // Count Out of Stock (Stock == 0)
    $stmtOut = $pdo->query("SELECT COUNT(*) FROM inventory WHERE stock = 0");
    $inventoryStats['out_of_stock'] = $stmtOut->fetchColumn();
} catch (PDOException $e) {
}

// 3. RECENT MOVEMENTS (Logs)
$movements = [];
try {
    $stmt = $pdo->query("
        SELECT m.movement_id, m.type, m.quantity, m.created_at, i.name, u.username
        FROM inventory_movements m
        JOIN inventory i ON m.item_id = i.item_id
        JOIN users u ON m.user_id = u.user_id
        ORDER BY m.created_at DESC LIMIT 10
    ");
    $movements = $stmt->fetchAll();
} catch (PDOException $e) {
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KakaiOne | Inventory Management</title>
    <?php include '../includes/links.php'; ?>
</head>

<body class="bg-light">

    <?php include '../includes/sidebar.php'; ?>

    <div id="dashboardContainer">
        <main id="main-content" style="margin-left: 250px; padding: 25px; transition: margin-left 0.3s;">

            <div class="container">

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h3 class="fw-bold">
                        <i class="bi bi-box-seam-fill me-2 text-warning"></i>Inventory Management
                    </h3>

                    <div class="d-flex flex-column gap-3" style="max-width: 250px;">
                        <!--<a href="../dashboard/admin_dashboard.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Back
                        </a>-->
                        <?php if (hasPermission('inv_add')): ?>
                            <a href="inventory_form.php" class="btn btn-warning">
                                <i class="bi bi-plus-lg"></i> Add Item
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <i class="bi bi-list-ul me-2"></i>Inventory Directory
                    </div>

                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Stock Level</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>

                                <tbody>
                                    <?php if (!empty($inventoryItems)): ?>
                                        <?php foreach ($inventoryItems as $item): ?>
                                            <tr>
                                                <td class="fw-bold"><?= htmlspecialchars($item['name']); ?></td>
                                                <td><?= htmlspecialchars($item['category']); ?></td>

                                                <td>
                                                    <?php if ($item['stock'] == 0): ?>
                                                        <span class="text-danger fw-bold">Out of Stock</span>
                                                    <?php elseif ($item['stock'] <= $item['reorder_level']): ?>
                                                        <span class="text-warning fw-bold"><?= $item['stock']; ?> (Low)</span>
                                                    <?php else: ?>
                                                        <span class="text-success fw-bold"><?= $item['stock']; ?></span>
                                                    <?php endif; ?>
                                                </td>

                                                <td>₱ <?= number_format($item['price'], 2); ?></td>

                                                <td>
                                                    <span class="badge bg-<?= $item['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                                                        <?= htmlspecialchars($item['status']); ?>
                                                    </span>
                                                </td>

                                                <td>
                                                    <a href="view_item.php?id=<?= $item['item_id']; ?>" class="btn btn-sm btn-info" title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <?php if (hasPermission('inv_edit')): ?>
                                                        <a href="inventory_form.php?id=<?= $item['item_id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (hasPermission('inv_delete')): ?>
                                                        <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $item['item_id']; ?>)" title="Delete">
                                                            <i class="bi bi-trash"></i>
                                                        </button>
                                                    <?php endif; ?>
                                                </td>

                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="6" class="text-center text-muted">No inventory items found.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <i class="bi bi-bar-chart me-2"></i>Inventory Summary
                    </div>
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <canvas id="inventoryChart" height="100"></canvas>
                            </div>
                            <div class="col-md-4">
                                <p><i class="bi bi-check-circle text-success"></i> Good Stock: <?= $inventoryStats['active']; ?></p>
                                <p><i class="bi bi-exclamation-triangle text-warning"></i> Low Stock: <?= $inventoryStats['low_stock']; ?></p>
                                <p><i class="bi bi-x-circle text-danger"></i> Out of Stock: <?= $inventoryStats['out_of_stock']; ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4 shadow-sm">
                    <div class="card-header bg-dark text-white">
                        <i class="bi bi-arrow-left-right me-2"></i>Recent Movements
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped align-middle">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Item Name</th>
                                        <th>Type</th>
                                        <th>Quantity</th>
                                        <th>Handled By</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($movements)): ?>
                                        <?php foreach ($movements as $mov): ?>
                                            <tr>
                                                <td><?= date('Y-m-d H:i', strtotime($mov['created_at'])); ?></td>
                                                <td class="fw-bold"><?= htmlspecialchars($mov['name']); ?></td>
                                                <td>
                                                    <?php if ($mov['type'] === 'Stock In'): ?>
                                                        <span class="badge bg-success"><i class="bi bi-arrow-down"></i> Stock In</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning text-dark"><i class="bi bi-arrow-up"></i> Stock Out</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= $mov['quantity']; ?></td>
                                                <td><?= htmlspecialchars($mov['username']); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center text-muted">No recent movements.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        // 1. Initialize Inventory Chart
        const ctx = document.getElementById('inventoryChart').getContext('2d');
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['Healthy Stock', 'Low Stock', 'Out of Stock'],
                datasets: [{
                    data: [<?= $inventoryStats['active']; ?>, <?= $inventoryStats['low_stock']; ?>, <?= $inventoryStats['out_of_stock']; ?>],
                    backgroundColor: ['#198754', '#ffc107', '#dc3545']
                }]
            },
            options: {
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        // 2. Delete Confirmation
        function confirmDelete(itemId) {
            Swal.fire({
                title: 'Delete Item?',
                text: "This action cannot be undone. The item will be removed from the system.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Make sure you have this backend endpoint ready or create it
                    window.location.href = `../../backend/inventory/delete_item.php?id=${itemId}`;
                }
            });
        }
    </script>

</body>

</html>