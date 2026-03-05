<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// set active module for sidebar highlighting
$activeModule = 'ordering';

// role validation
if (!isset($_SESSION['user_id'])) {
  header('Location: ../../index.php');
  exit;
}

requirePermission('order_view');
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Order Management | KakaiOne</title>
  <?php include '../includes/links.php'; ?>
  <style>
    .filter-select {
      min-width: 150px;
    }

    .table-hover tbody tr:hover {
      background-color: rgba(255, 193, 7, 0.05);
    }

    .btn-action {
      width: 32px;
      height: 32px;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border-radius: 6px;
    }
  </style>
</head>

<body class="bg-light">

  <?php include '../includes/sidebar.php'; ?>

  <main id="main-content" style="margin-left: 260px; padding: 25px; transition: margin-left 0.3s;">
    <div class="container-fluid">

      <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
          <h3 class="fw-bold text-dark mb-1">
            <i class="bi bi-receipt me-2 text-warning"></i>Order Management
          </h3>
          <p class="text-muted small mb-0">Track customer transactions and manage fulfillment pipelines.</p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-secondary shadow-sm" onclick="loadOrders()" title="Refresh List">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
          <a href="ordering_module.php" class="btn btn-secondary shadow-sm">
            <i class="bi bi-grid-fill"></i> Dashboard
          </a>
          <?php if (hasPermission('order_create')): ?>
            <a href="order_create.php" class="btn btn-warning fw-bold shadow-sm">
              <i class="bi bi-plus-lg"></i> Create Order
            </a>
          <?php endif; ?>
        </div>
      </div>

      <div class="card shadow-sm border-0">
        <div class="card-header bg-dark text-white py-3">
          <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <span class="fw-bold"><i class="bi bi-list-ul me-2"></i>Order Registry</span>

            <div class="d-flex gap-2 flex-grow-1 justify-content-md-end">
              <select id="statusFilter" class="form-select form-select-sm filter-select shadow-none" onchange="filterOrders()">
                <option value="">All Statuses</option>
                <option value="Pending">Pending</option>
                <option value="Processing">Processing</option>
                <option value="Delivered">Delivered</option>
                <option value="Cancelled">Cancelled</option>
              </select>

              <div class="input-group input-group-sm w-50">
                <input type="text" id="orderSearch" class="form-control" placeholder="Search ID or Customer..." onkeyup="filterOrders()">
                <span class="input-group-text bg-white border-start-0"><i class="bi bi-search"></i></span>
              </div>
            </div>
          </div>
        </div>
        <div class="card-body p-0">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0" id="ordersTable">
              <thead class="table-light">
                <tr>
                  <th class="ps-4">Order ID</th>
                  <th>Customer</th>
                  <th>Status</th>
                  <th>Payment</th>
                  <th>Total Amount</th>
                  <th>Date</th>
                  <th class="text-end pe-4">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr>
                  <td colspan="7" class="text-center py-5 text-muted">
                    <div class="spinner-border spinner-border-sm text-warning me-2"></div>
                    Fetching live order data...
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
        <div class="card-footer bg-white py-3 border-0 text-muted small">
          <i class="bi bi-info-circle me-1"></i> Orders are automatically sorted by the most recent transaction.
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    let allOrders = [];

    function filterOrders() {
      const searchText = document.getElementById('orderSearch').value.toLowerCase();
      const statusType = document.getElementById('statusFilter').value;
      const rows = document.querySelectorAll('#ordersTable tbody tr');

      rows.forEach(row => {
        if (row.cells.length < 3) return;
        const text = row.innerText.toLowerCase();
        const statusCell = row.cells[2].innerText;
        const matchesSearch = text.includes(searchText);
        const matchesStatus = statusType === "" || statusCell.includes(statusType);
        row.style.display = (matchesSearch && matchesStatus) ? '' : 'none';
      });
    }

    async function loadOrders() {
      try {
        const res = await fetch('../../backend/orders/get_orders.php').then(r => r.json());
        const tb = document.querySelector('#ordersTable tbody');
        tb.innerHTML = '';

        if (!res.success || res.data.length === 0) {
          tb.innerHTML = '<tr><td colspan="7" class="text-center py-5 text-muted">No orders found.</td></tr>';
          return;
        }

        allOrders = res.data;

        res.data.forEach(o => {
          const tr = document.createElement('tr');

          let statusBadge = 'bg-secondary';
          if (o.status === 'Pending') statusBadge = 'bg-warning text-dark';
          if (o.status === 'Processing') statusBadge = 'bg-info text-dark';
          if (o.status === 'Delivered' || o.status === 'Completed') statusBadge = 'bg-success';
          if (o.status === 'Cancelled') statusBadge = 'bg-danger';

          const paymentColor = o.payment_status === 'Paid' ? 'text-success' : 'text-danger';
          const paymentIcon = o.payment_status === 'Paid' ? 'bi-check-circle-fill' : 'bi-clock-history';

          tr.innerHTML = `
                        <td class="ps-4 fw-bold text-dark">#${o.order_id}</td>
                        <td>
                            <div class="fw-bold">${o.full_name ?? '<span class="text-muted fst-italic">Walk-in</span>'}</div>
                            <div class="text-muted extra-small" style="font-size: 0.75rem;">${o.payment_method ?? 'Cash'}</div>
                        </td>
                        <td><span class="badge ${statusBadge}">${o.status}</span></td>
                        <td><span class="${paymentColor} small fw-bold"><i class="bi ${paymentIcon} me-1"></i>${o.payment_status}</span></td>
                        <td class="fw-bold">₱${Number(o.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                        <td class="text-muted small">${o.order_date}</td>
                        <td class="text-end pe-4">
                            <div class="d-flex justify-content-end gap-1">
                                <a class="btn btn-action btn-outline-primary" href="order_view.php?id=${o.order_id}" title="View Invoice">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <div class="dropdown">
                                    <button class="btn btn-action btn-outline-dark dropdown-toggle no-caret" data-bs-toggle="dropdown" title="Update Status">
                                        <i class="bi bi-arrow-repeat"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                                        <li><h6 class="dropdown-header">Fulfillment</h6></li>
                                        <li><a class="dropdown-item small" href="#" onclick="updateStatus(${o.order_id}, 'Processing')">Mark Processing</a></li>
                                        <li><a class="dropdown-item small" href="#" onclick="updateStatus(${o.order_id}, 'Delivered')">Mark Delivered</a></li>
                                        <li><hr class="dropdown-divider"></li>
                                        <li><a class="dropdown-item small text-danger" href="#" onclick="updateStatus(${o.order_id}, 'Cancelled')">Cancel Order</a></li>
                                    </ul>
                                </div>
                                <button class="btn btn-action btn-outline-secondary" onclick="editOrder(${o.order_id})" title="Edit Order">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-action btn-outline-danger" onclick="deleteOrder(${o.order_id})" title="Delete Order">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>`;
          tb.appendChild(tr);
        });
        filterOrders();
      } catch (error) {
        console.error("Fetch error:", error);
      }
    }

    async function updateStatus(id, status) {
      const confirm = await Swal.fire({
        title: 'Update Order #' + id + '?',
        text: `Change status to "${status}"?`,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, update it'
      });

      if (confirm.isConfirmed) {
        try {
          const res = await fetch('../../backend/orders/update_status.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              order_id: id,
              status: status
            })
          }).then(r => r.json());

          if (res.success) {
            Swal.fire({
              icon: 'success',
              title: 'Updated!',
              timer: 1500,
              showConfirmButton: false
            });
            loadOrders();
          } else {
            Swal.fire('Error', res.message || 'Operation failed', 'error');
          }
        } catch (err) {
          Swal.fire('Error', 'Communication error', 'error');
        }
      }
    }

    function editOrder(id) {
      // Redirect to the create order page with an ID to trigger edit mode
      window.location.href = `order_create.php?edit_id=${id}`;
    }

    async function deleteOrder(id) {
      const confirm = await Swal.fire({
        title: 'Delete Order #' + id + '?',
        text: "This action will revert inventory stock and remove the order permanently.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, delete it'
      });

      if (confirm.isConfirmed) {
        try {
          const res = await fetch('../../backend/orders/delete_order.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              order_id: id
            })
          }).then(r => r.json());

          if (res.success) {
            Swal.fire('Deleted!', 'The order has been removed.', 'success');
            loadOrders();
          } else {
            Swal.fire('Error', res.message || 'Deletion failed', 'error');
          }
        } catch (err) {
          Swal.fire('Error', 'Server error', 'error');
        }
      }
    }

    window.addEventListener('load', loadOrders);
  </script>
</body>

</html>