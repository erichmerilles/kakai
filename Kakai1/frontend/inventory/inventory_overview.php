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

requirePermission('inv_view');

// fetch inventory items
try {
    $stmt = $pdo->query("
        SELECT 
            i.item_id, 
            i.item_name, 
            c.category_name, 
            i.quantity, 
            i.reorder_level, 
            i.unit_price, 
            i.status, 
            i.created_at
        FROM inventory i
        LEFT JOIN categories c ON i.category_id = c.category_id
        ORDER BY i.item_name ASC
    ");
    $inventoryItems = $stmt->fetchAll();
} catch (PDOException $e) {
    $inventoryItems = [];
}

// inventory summary stats
$inventoryStats = ['active' => 0, 'low_stock' => 0, 'out_of_stock' => 0, 'total_value' => 0];
try {
    // count active items
    $stmtActive = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity > reorder_level AND quantity > 0");
    $inventoryStats['active'] = $stmtActive->fetchColumn();

    // count low stock items
    $stmtLow = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= reorder_level AND quantity > 0");
    $inventoryStats['low_stock'] = $stmtLow->fetchColumn();

    // count out of stock items
    $stmtOut = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity = 0");
    $inventoryStats['out_of_stock'] = $stmtOut->fetchColumn();

    // calcute total inventory value
    $stmtValue = $pdo->query("SELECT SUM(quantity * unit_price) FROM inventory WHERE quantity > 0");
    $inventoryStats['total_value'] = $stmtValue->fetchColumn() ?: 0;
} catch (PDOException $e) {
}

// recent inventory movements
$movements = [];
try {
    $stmt = $pdo->query("
        SELECT m.movement_id, m.type, m.quantity, m.created_at, i.item_name, u.username
        FROM inventory_movements m
        JOIN inventory i ON m.item_id = i.item_id
        JOIN users u ON m.user_id = u.user_id
        ORDER BY m.created_at DESC LIMIT 5
    ");
    $movements = $stmt->fetchAll();
} catch (PDOException $e) {
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KakaiOne | Inventory Management</title>
    <?php include '../includes/links.php'; ?>
    <style>
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
            border-radius: 8px;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .border-left-primary {
            border-left-color: #0d6efd !important;
        }

        .border-left-success {
            border-left-color: #198754 !important;
        }

        .border-left-warning {
            border-left-color: #ffc107 !important;
        }

        .border-left-danger {
            border-left-color: #dc3545 !important;
        }

        @media print {

            #sidebar,
            .btn,
            .d-flex.gap-2,
            .input-group {
                display: none !important;
            }

            #main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .col-lg-8 {
                width: 100% !important;
            }
        }
    </style>
</head>

<body class="bg-light">

    <?php include '../includes/sidebar.php'; ?>

    <div id="dashboardContainer">
        <main id="main-content" style="margin-left: 250px; padding: 25px; transition: margin-left 0.3s;">

            <div class="container-fluid">

                <div class="d-flex justify-content-between align-items-end mb-4">
                    <div>
                        <h3 class="fw-bold text-dark mb-1">
                            <i class="bi bi-box-seam-fill me-2 text-warning"></i>Inventory Dashboard
                        </h3>
                        <p class="text-muted small mb-0">Monitor stock levels, values, and movements.</p>
                    </div>

                    <div class="d-flex gap-2">
                        <?php if (hasPermission('inv_add')): ?>
                            <a href="inventory_form.php" class="btn btn-warning shadow-sm">
                                <i class="bi bi-plus-lg"></i> Add Item
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row mb-4">

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card border-0 shadow-sm border-left-primary h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Value</div>
                                        <div class="h5 mb-0 fw-bold text-dark">
                                            ₱ <?= number_format($inventoryStats['total_value'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-cash-stack text-primary opacity-50 fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card border-0 shadow-sm border-left-success h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs fw-bold text-success text-uppercase mb-1">Good Stock</div>
                                        <div class="h4 mb-0 fw-bold text-dark">
                                            <?= $inventoryStats['active']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-check-circle text-success opacity-50 fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card border-0 shadow-sm border-left-warning h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Low Stock Alerts</div>
                                        <div class="h4 mb-0 fw-bold text-dark">
                                            <?= $inventoryStats['low_stock']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-exclamation-triangle text-warning opacity-50 fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card border-0 shadow-sm border-left-danger h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs fw-bold text-danger text-uppercase mb-1">Out of Stock</div>
                                        <div class="h4 mb-0 fw-bold text-dark">
                                            <?= $inventoryStats['out_of_stock']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="bi bi-x-circle text-danger opacity-50 fs-1"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-list-ul me-2"></i>Master Item List</span>
                                <div class="input-group input-group-sm w-50">
                                    <input type="text" id="tableSearch" class="form-control" placeholder="Search item or category...">
                                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                </div>
                            </div>

                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle mb-0" id="inventoryTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3">Item Name</th>
                                                <th>Category</th>
                                                <th>Stock Level</th>
                                                <th>Unit Price</th>
                                                <th>Status</th>
                                                <th class="text-end pe-3">Action</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <?php if (!empty($inventoryItems)): ?>
                                                <?php foreach ($inventoryItems as $item): ?>
                                                    <tr>
                                                        <td class="ps-3 fw-bold text-dark"><?= htmlspecialchars($item['item_name']); ?></td>
                                                        <td><span class="badge bg-secondary"><?= htmlspecialchars($item['category_name'] ?? 'Uncategorized'); ?></span></td>

                                                        <td>
                                                            <?php if ($item['quantity'] == 0): ?>
                                                                <span class="text-danger fw-bold">0 <small class="text-muted">(Out)</small></span>
                                                            <?php elseif ($item['quantity'] <= $item['reorder_level']): ?>
                                                                <span class="text-warning fw-bold text-dark"><?= $item['quantity']; ?> <small class="text-muted">(Low)</small></span>
                                                            <?php else: ?>
                                                                <span class="text-success fw-bold"><?= $item['quantity']; ?></span>
                                                            <?php endif; ?>
                                                        </td>

                                                        <td class="text-muted">₱ <?= number_format($item['unit_price'], 2); ?></td>

                                                        <td>
                                                            <?php
                                                            $badgeClass = 'success';
                                                            if ($item['status'] === 'Low Stock') $badgeClass = 'warning text-dark';
                                                            if ($item['status'] === 'Out of Stock' || $item['status'] === 'Unavailable') $badgeClass = 'danger';
                                                            ?>
                                                            <span class="badge bg-<?= $badgeClass; ?>">
                                                                <?= htmlspecialchars($item['status']); ?>
                                                            </span>
                                                        </td>

                                                        <td class="text-end pe-3">
                                                            <a href="view_item.php?id=<?= $item['item_id']; ?>" class="btn btn-sm btn-info text-white" title="View">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <?php if (hasPermission('inv_edit')): ?>
                                                                <a href="inventory_form.php?id=<?= $item['item_id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                                                                    <i class="bi bi-pencil"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                            <?php if (hasPermission('inv_delete')): ?>
                                                                <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?= $item['item_id']; ?>, '<?= htmlspecialchars(addslashes($item['item_name'])); ?>')" title="Delete">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">
                                                        No inventory items found.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 chart-section">

                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-dark text-white">
                                <i class="bi bi-pie-chart-fill me-2"></i>Stock Health
                            </div>
                            <div class="card-body d-flex justify-content-center">
                                <canvas id="inventoryChart" style="max-height: 250px;"></canvas>
                            </div>
                        </div>

                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-arrow-left-right me-2"></i>Recent Activity</span>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php if (!empty($movements)): ?>
                                        <?php foreach ($movements as $mov): ?>
                                            <li class="list-group-item px-3 py-3">
                                                <div class="d-flex w-100 justify-content-between align-items-center">
                                                    <h6 class="mb-1 fw-bold text-dark"><?= htmlspecialchars($mov['item_name']); ?></h6>
                                                    <small class="text-muted"><?= date('M d, H:i', strtotime($mov['created_at'])); ?></small>
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center mt-2">
                                                    <?php if ($mov['type'] === 'IN'): ?>
                                                        <span class="badge bg-success"><i class="bi bi-arrow-down-left"></i> Stock In</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-danger"><i class="bi bi-arrow-up-right"></i> Stock Out</span>
                                                    <?php endif; ?>
                                                    <span class="fw-bold fs-6">Qty: <?= $mov['quantity']; ?></span>
                                                </div>
                                                <small class="text-muted mt-1 d-block">By: <?= htmlspecialchars($mov['username']); ?></small>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="list-group-item text-center text-muted p-4">No recent movements.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // search filter for inventory table
        document.getElementById('tableSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#inventoryTable tbody tr');

            rows.forEach(row => {
                if (row.cells.length === 1) return;

                let text = row.innerText.toLowerCase();
                if (text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // initialize inventory summary chart
        const ctx = document.getElementById('inventoryChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Good Stock', 'Low Stock', 'Out of Stock'],
                datasets: [{
                    data: [<?= $inventoryStats['active']; ?>, <?= $inventoryStats['low_stock']; ?>, <?= $inventoryStats['out_of_stock']; ?>],
                    backgroundColor: ['#198754', '#ffc107', '#dc3545'],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                cutout: '70%',
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            usePointStyle: true
                        }
                    }
                }
            }
        });

        // delete confirmation
        function confirmDelete(itemId, itemName) {
            Swal.fire({
                title: 'Delete Item?',
                html: `Are you sure you want to delete <b>${itemName}</b>?<br>This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete it',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `../../backend/inventory/delete_item.php?id=${itemId}`;
                }
            });
        }
    </script>

</body>

</html>