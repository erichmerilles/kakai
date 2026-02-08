<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$activeModule = 'inventory';

// role validation
if (!isset($_SESSION['user_id'])) {
  header('Location: ../../index.php');
  exit;
}
requirePermission('inv_stock_in');

// fetch movements data
$movements = [];
try {
  $stmt = $pdo->query("
        SELECT m.movement_id, m.type, m.quantity, m.created_at, m.remarks, 
               i.name as item_name, i.item_id, u.username
        FROM inventory_movements m
        JOIN inventory i ON m.item_id = i.item_id
        JOIN users u ON m.user_id = u.user_id
        ORDER BY m.created_at DESC LIMIT 100
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
  <title>Stock Movements | KakaiOne</title>
  <?php include '../includes/links.php'; ?>
</head>

<body class="bg-light">

  <?php include '../includes/sidebar.php'; ?>

  <div id="dashboardContainer">
    <main id="main-content" style="margin-left: 250px; padding: 25px; transition: margin-left 0.3s;">
      <div class="container">

        <div class="d-flex justify-content-between align-items-center mb-4">
          <h3 class="fw-bold">
            <i class="bi bi-arrow-left-right me-2 text-warning"></i>Stock Movements
          </h3>
          <div class="d-flex gap-2">
            <a href="inventory_overview.php" class="btn btn-secondary">
              <i class="bi bi-arrow-left"></i> Back
            </a>
            <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#movementModal">
              <i class="bi bi-plus-slash-minus"></i> Adjust Stock
            </button>
          </div>
        </div>

        <div class="card shadow-sm">
          <div class="card-header bg-dark text-white">
            <i class="bi bi-clock-history me-2"></i>Transaction History
          </div>
          <div class="card-body p-0">
            <div class="table-responsive">
              <table class="table table-striped align-middle mb-0">
                <thead class="bg-light">
                  <tr>
                    <th class="ps-4">Date & Time</th>
                    <th>Item Name</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Remarks</th>
                    <th>Handled By</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (!empty($movements)): ?>
                    <?php foreach ($movements as $mov): ?>
                      <tr>
                        <td class="ps-4 text-muted small">
                          <?= date('M d, Y h:i A', strtotime($mov['created_at'])); ?>
                        </td>
                        <td class="fw-bold"><?= htmlspecialchars($mov['item_name']); ?></td>
                        <td>
                          <?php if ($mov['type'] === 'Stock In'): ?>
                            <span class="badge bg-success"><i class="bi bi-arrow-down"></i> Stock In</span>
                          <?php else: ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-arrow-up"></i> Stock Out</span>
                          <?php endif; ?>
                        </td>
                        <td class="fw-bold fs-6"><?= $mov['quantity']; ?></td>
                        <td class="text-muted fst-italic small"><?= htmlspecialchars($mov['remarks'] ?? '-'); ?></td>
                        <td>
                          <div class="d-flex align-items-center">
                            <div class="bg-secondary bg-opacity-10 rounded-circle p-1 me-2"><i class="bi bi-person-fill"></i></div>
                            <?= htmlspecialchars($mov['username']); ?>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                    <tr>
                      <td colspan="6" class="text-center py-4 text-muted">No movements found.</td>
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

</body>

</html>