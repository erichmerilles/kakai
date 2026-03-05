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

// Check for permission to view stock movements
requirePermission('inv_stock_in');

// Fetch items for the "Adjust Stock" dropdown
$items = [];
try {
  $items = $pdo->query("SELECT item_id, item_name, quantity FROM inventory ORDER BY item_name ASC")->fetchAll();
} catch (PDOException $e) {
  // Handle error silently or log it
}

// Fetch movements data - Increased limit as DataTables handles pagination
$movements = [];
try {
  $stmt = $pdo->query("
        SELECT m.movement_id, m.type, m.quantity, m.created_at, m.remarks, 
               i.item_name, i.item_id, u.username
        FROM inventory_movements m
        JOIN inventory i ON m.item_id = i.item_id
        JOIN users u ON m.user_id = u.user_id
        ORDER BY m.created_at DESC LIMIT 500
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
  <title>Stock Movements | KakaiOne</title>
  <?php include '../includes/links.php'; ?>

  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">

  <style>
    .stat-card {
      border-left: 4px solid;
      transition: transform 0.2s;
      border-radius: 8px;
    }

    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1) !important;
    }

    /* Customize DataTables Search Box */
    .dataTables_filter input {
      border-radius: 20px;
      padding: 5px 15px;
      border: 1px solid #dee2e6;
      margin-bottom: 10px;
    }

    .page-item.active .page-link {
      background-color: #ffc107;
      border-color: #ffc107;
      color: #000;
    }

    .order-badge {
      font-size: 0.75rem;
      padding: 5px 12px;
      border-radius: 50px;
    }
  </style>
</head>

<body class="bg-light">

  <?php include '../includes/sidebar.php'; ?>

  <div id="dashboardContainer">
    <main id="main-content" style="margin-left: 250px; padding: 25px; transition: margin-left 0.3s;">
      <div class="container-fluid">

        <div class="d-flex justify-content-between align-items-center mb-4">
          <div>
            <h3 class="fw-bold">
              <i class="bi bi-arrow-left-right me-2 text-warning"></i>Stock Movements
            </h3>
            <p class="text-muted small mb-0">Detailed history of all stock adjustments and transactions.</p>
          </div>
          <div class="d-flex gap-2">
            <a href="inventory_overview.php" class="btn btn-secondary shadow-sm">
              <i class="bi bi-arrow-left"></i> Back
            </a>
            <?php if (hasPermission('inv_stock_in') || hasPermission('inv_stock_out')): ?>
              <button class="btn btn-warning shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#movementModal">
                <i class="bi bi-plus-slash-minus"></i> Adjust Stock
              </button>
            <?php endif; ?>
          </div>
        </div>

        <div class="card shadow-sm border-0">
          <div class="card-header bg-dark text-white py-3">
            <i class="bi bi-clock-history me-2"></i>Transaction History
          </div>
          <div class="card-body p-4">
            <div class="table-responsive">
              <table class="table table-hover align-middle mb-0" id="movementsTable">
                <thead class="table-light">
                  <tr>
                    <th>Date & Time</th>
                    <th>Item Name</th>
                    <th>Type</th>
                    <th>Qty</th>
                    <th>Remarks</th>
                    <th>Handled By</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($movements as $mov): ?>
                    <tr>
                      <td data-sort="<?= strtotime($mov['created_at']) ?>" class="text-muted small">
                        <?= date('M d, Y h:i A', strtotime($mov['created_at'])); ?>
                      </td>
                      <td class="fw-bold"><?= htmlspecialchars($mov['item_name']); ?></td>
                      <td>
                        <?php if ($mov['type'] === 'IN'): ?>
                          <span class="badge bg-success bg-opacity-10 text-success border border-success rounded-pill px-3">
                            <i class="bi bi-arrow-down-left"></i> Stock In
                          </span>
                        <?php else: ?>
                          <span class="badge bg-danger bg-opacity-10 text-danger border border-danger rounded-pill px-3">
                            <i class="bi bi-arrow-up-right"></i> Stock Out
                          </span>
                        <?php endif; ?>
                      </td>
                      <td class="fw-bold <?= $mov['type'] === 'IN' ? 'text-success' : 'text-danger' ?>">
                        <?= ($mov['type'] === 'IN' ? '+' : '-') . number_format($mov['quantity']); ?>
                      </td>
                      <td class="text-muted fst-italic small"><?= htmlspecialchars($mov['remarks'] ?? '-'); ?></td>
                      <td>
                        <div class="d-flex align-items-center">
                          <div class="bg-secondary bg-opacity-10 rounded-circle p-1 me-2" style="width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">
                            <i class="bi bi-person-fill small"></i>
                          </div>
                          <span class="small"><?= htmlspecialchars($mov['username']); ?></span>
                        </div>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <div class="modal fade" id="movementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content shadow border-0">
        <div class="modal-header bg-warning">
          <h5 class="modal-title fw-bold text-dark"><i class="bi bi-plus-slash-minus me-2"></i>Adjust Stock Levels</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <form id="movementForm">
          <div class="modal-body p-4">
            <div class="mb-3">
              <label class="form-label small fw-bold">Select Product <span class="text-danger">*</span></label>
              <select name="item_id" class="form-select" required>
                <option value="">-- Choose Item --</option>
                <?php foreach ($items as $item): ?>
                  <option value="<?= $item['item_id'] ?>">
                    <?= htmlspecialchars($item['item_name']) ?> (Current: <?= $item['quantity'] ?>)
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="row g-3 mb-3">
              <div class="col-6">
                <label class="form-label small fw-bold">Transaction Type <span class="text-danger">*</span></label>
                <select name="type" class="form-select" required>
                  <option value="IN">Stock IN (+)</option>
                  <option value="OUT">Stock OUT (-)</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label small fw-bold">Quantity <span class="text-danger">*</span></label>
                <input type="number" name="quantity" class="form-control" min="1" required>
              </div>
            </div>

            <div class="mb-0">
              <label class="form-label small fw-bold">Remarks / Reason</label>
              <textarea name="remarks" class="form-control" rows="2" placeholder="e.g. Restock, Damage, Sales..."></textarea>
            </div>
          </div>
          <div class="modal-footer bg-light border-0">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-warning fw-bold text-dark px-4 shadow-sm">Process Adjustment</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

  <script>
    $(document).ready(function() {
      // Initialize DataTables
      $('#movementsTable').DataTable({
        "order": [
          [0, "desc"]
        ], // Default sort by date (column 0) newest first
        "pageLength": 10,
        "language": {
          "search": "_INPUT_",
          "searchPlaceholder": "Search movement logs...",
          "paginate": {
            "previous": '<i class="bi bi-chevron-left"></i>',
            "next": '<i class="bi bi-chevron-right"></i>'
          }
        }
      });
    });

    // AJAX Submission to movement.php
    document.getElementById('movementForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const submitBtn = this.querySelector('button[type="submit"]');
      submitBtn.disabled = true;

      try {
        const formData = new FormData(this);
        const payload = Object.fromEntries(formData.entries());

        const res = await fetch('../../backend/inventory/movement.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json'
          },
          body: JSON.stringify(payload)
        });

        const data = await res.json();
        if (data.success) {
          Swal.fire({
            icon: 'success',
            title: 'Inventory Updated',
            text: data.message,
            timer: 1500,
            showConfirmButton: false
          }).then(() => location.reload());
        } else {
          Swal.fire('Error', data.message, 'error');
        }
      } catch (err) {
        Swal.fire('Error', 'Communication error with server.', 'error');
      } finally {
        submitBtn.disabled = false;
      }
    });
  </script>
</body>

</html>