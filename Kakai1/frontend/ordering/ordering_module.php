<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// set active module
$activeModule = 'ordering';

// role validation
if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.php');
  exit;
}

// check page permissions
requirePermission('order_view');

// check permissions
$canCreateOrder = hasPermission('order_create');
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ordering Dashboard | KakaiOne</title>
  <?php include '../includes/links.php'; ?>
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

    .border-left-primary {
      border-left-color: #0d6efd !important;
    }

    .border-left-success {
      border-left-color: #198754 !important;
    }

    .border-left-warning {
      border-left-color: #ffc107 !important;
    }

    .border-left-info {
      border-left-color: #0dcaf0 !important;
    }

    .border-left-dark {
      border-left-color: #212529 !important;
    }

    .order-badge {
      font-size: 0.75rem;
      padding: 5px 12px;
      border-radius: 50px;
      font-weight: 600;
      text-transform: capitalize;
    }

    .table-hover tbody tr:hover {
      background-color: rgba(255, 193, 7, 0.03);
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
            <i class="bi bi-cart-check-fill me-2 text-warning"></i>Ordering Dashboard
          </h3>
          <p class="text-muted small mb-0">Unified management for sales performance and order fulfillment.</p>
        </div>
        <div class="d-flex gap-2">
          <button class="btn btn-white border shadow-sm" onclick="loadOverview()" title="Refresh Data">
            <i class="bi bi-arrow-clockwise"></i>
          </button>
          <a href="ordering_analytics.php" class="btn btn-outline-dark shadow-sm">
            <i class="bi bi-graph-up-arrow me-1"></i> Analytics
          </a>
          <?php if ($canCreateOrder): ?>
            <a href="order_create.php" class="btn btn-warning fw-bold shadow-sm px-4">
              <i class="bi bi-plus-lg me-1"></i> New Order
            </a>
          <?php endif; ?>
        </div>
      </div>

      <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
          <div class="card stat-card border-0 shadow-sm border-left-success h-100 py-2">
            <div class="card-body">
              <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                  <div class="text-xs fw-bold text-success text-uppercase mb-1" style="font-size: 0.7rem;">Sales Today (Realized)</div>
                  <div class="h4 mb-0 fw-bold text-dark" id="salesToday">
                    <div class="spinner-border spinner-border-sm text-success" role="status"></div>
                  </div>
                </div>
                <div class="col-auto"><i class="bi bi-cash-coin fs-1 text-success opacity-50"></i></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
          <div class="card stat-card border-0 shadow-sm border-left-warning h-100 py-2">
            <div class="card-body">
              <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                  <div class="text-xs fw-bold text-warning text-uppercase mb-1" style="font-size: 0.7rem;">Total Orders</div>
                  <div class="h4 mb-0 fw-bold text-dark" id="totalOrders">
                    <div class="spinner-border spinner-border-sm text-warning" role="status"></div>
                  </div>
                </div>
                <div class="col-auto"><i class="bi bi-receipt fs-1 text-warning opacity-50"></i></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
          <div class="card stat-card border-0 shadow-sm border-left-dark h-100 py-2">
            <div class="card-body">
              <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                  <div class="text-xs fw-bold text-dark text-uppercase mb-1" style="font-size: 0.7rem;">Pending Fulfillment</div>
                  <div class="h4 mb-0 fw-bold text-dark" id="openOrders">
                    <div class="spinner-border spinner-border-sm text-dark" role="status"></div>
                  </div>
                </div>
                <div class="col-auto"><i class="bi bi-hourglass-split fs-1 text-dark opacity-50"></i></div>
              </div>
            </div>
          </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-4">
          <div class="card stat-card border-0 shadow-sm border-left-info h-100 py-2">
            <div class="card-body">
              <div class="row no-gutters align-items-center">
                <div class="col mr-2">
                  <div class="text-xs fw-bold text-info text-uppercase mb-1" style="font-size: 0.7rem;">Active Products</div>
                  <div class="h4 mb-0 fw-bold text-dark" id="totalProducts">
                    <div class="spinner-border spinner-border-sm text-info" role="status"></div>
                  </div>
                </div>
                <div class="col-auto"><i class="bi bi-box-seam fs-1 text-info opacity-50"></i></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div class="row">
        <div class="col-lg-8">
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
              <span class="fw-bold"><i class="bi bi-clock-history me-2 text-warning"></i>Recent Transactions</span>
              <div class="input-group input-group-sm w-50">
                <input type="text" id="orderSearch" class="form-control border-0" placeholder="Search orders...">
                <span class="input-group-text bg-white border-0"><i class="bi bi-search"></i></span>
              </div>
            </div>
            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="recentOrdersTable">
                  <thead class="table-light">
                    <tr>
                      <th class="ps-4">ID</th>
                      <th>Customer</th>
                      <th>Status</th>
                      <th>Total</th>
                      <th class="text-end pe-4">Action</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
            </div>
            <div class="card-footer bg-white border-0 text-center py-3">
              <a href="order_list.php" class="text-decoration-none small fw-bold text-warning">VIEW ALL TRANSACTIONS <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
        </div>

        <div class="col-lg-4">
          <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white py-3 fw-bold border-bottom">
              <i class="bi bi-gear-fill text-warning me-2"></i>Management Tools
            </div>
            <div class="card-body p-0">
              <div class="list-group list-group-flush">
                <a href="manage_customers.php" class="list-group-item list-group-item-action py-3">
                  <div class="row no-gutters align-items-center">
                    <div class="col-auto me-3">
                      <div class="bg-primary bg-opacity-10 rounded p-2"><i class="bi bi-people-fill text-primary"></i></div>
                    </div>
                    <div class="col">
                      <div class="fw-bold small text-dark">Customer Registry</div>
                      <div class="text-muted extra-small" style="font-size: 0.7rem;">Manage profiles and orders.</div>
                    </div>
                  </div>
                </a>
                <a href="ordering_analytics.php" class="list-group-item list-group-item-action py-3">
                  <div class="row no-gutters align-items-center">
                    <div class="col-auto me-3">
                      <div class="bg-success bg-opacity-10 rounded p-2"><i class="bi bi-bar-chart-line-fill text-success"></i></div>
                    </div>
                    <div class="col">
                      <div class="fw-bold small text-dark">Sales Analytics</div>
                      <div class="text-muted extra-small" style="font-size: 0.7rem;">Performance and metrics.</div>
                    </div>
                  </div>
                </a>
                <a href="../inventory/inventory_overview.php" class="list-group-item list-group-item-action py-3">
                  <div class="row no-gutters align-items-center">
                    <div class="col-auto me-3">
                      <div class="bg-info bg-opacity-10 rounded p-2"><i class="bi bi-box-seam-fill text-info"></i></div>
                    </div>
                    <div class="col">
                      <div class="fw-bold small text-dark">Inventory Sync</div>
                      <div class="text-muted extra-small" style="font-size: 0.7rem;">Check live stock levels.</div>
                    </div>
                  </div>
                </a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <script>
    document.getElementById('orderSearch').addEventListener('keyup', function() {
      let filter = this.value.toLowerCase();
      let rows = document.querySelectorAll('#recentOrdersTable tbody tr');
      rows.forEach(row => {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
      });
    });

    async function loadOverview() {
      try {
        const spinners = ['totalProducts', 'totalOrders', 'openOrders', 'salesToday'];
        spinners.forEach(id => {
          document.getElementById(id).innerHTML = '<div class="spinner-border spinner-border-sm text-secondary" role="status"></div>';
        });

        const [pRes, oRes] = await Promise.all([
          fetch('../../backend/orders/get_products.php').then(r => r.json()),
          fetch('../../backend/orders/get_orders.php').then(r => r.json())
        ]);

        document.getElementById('totalProducts').innerText = pRes.success ? pRes.data.length : '0';

        let totalCount = 0,
          openCount = 0,
          salesDay = 0;
        const today = new Date().toISOString().slice(0, 10);

        if (oRes.success && oRes.data) {
          totalCount = oRes.data.length;

          oRes.data.forEach(r => {
            const statusVal = r.status ? r.status.trim().toLowerCase() : '';
            const isToday = r.order_date && r.order_date.startsWith(today);

            // Pending Fulfillment Count
            if (!['delivered', 'completed', 'cancelled'].includes(statusVal)) {
              openCount++;
            }

            // Sales Today (Only realized revenue)
            if (isToday && ['completed', 'delivered'].includes(statusVal)) {
              salesDay += parseFloat(r.total_amount || 0);
            }
          });

          const tbody = document.querySelector('#recentOrdersTable tbody');
          tbody.innerHTML = '';

          oRes.data.slice(0, 8).forEach(r => {
            // STATUS NORMALIZATION (Case insensitive fix)
            const currentStatusText = r.status ? r.status.trim() : 'Pending';
            const statusLower = currentStatusText.toLowerCase();

            let badge = 'bg-secondary';
            if (statusLower === 'pending') badge = 'bg-warning text-dark';
            else if (statusLower === 'processing') badge = 'bg-info text-dark';
            else if (statusLower === 'completed' || statusLower === 'delivered') badge = 'bg-success';
            else if (statusLower === 'cancelled') badge = 'bg-danger';

            const tr = document.createElement('tr');
            tr.innerHTML = `
                            <td class="ps-4 fw-bold text-dark">#${r.order_id}</td>
                            <td>
                                <div class="fw-bold small text-dark">${r.full_name ?? '<span class="text-muted fst-italic">Walk-in</span>'}</div>
                                <div class="text-muted extra-small" style="font-size: 0.75rem;">${r.order_date}</div>
                            </td>
                            <td><span class="badge order-badge ${badge}">${currentStatusText}</span></td>
                            <td class="fw-bold text-dark">₱${Number(r.total_amount).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                            <td class="text-end pe-4">
                                <div class="btn-group shadow-sm">
                                    <a class="btn btn-sm btn-outline-primary" href="order_view.php?id=${r.order_id}" title="View Details">
                                        <i class="bi bi-eye"></i>
                                    </a>
                                    ${statusLower === 'pending' ? `
                                        <button class="btn btn-sm btn-success" onclick="markAsDone(${r.order_id})" title="Mark as Done">
                                            <i class="bi bi-check-lg"></i> Done
                                        </button>
                                    ` : ''}
                                </div>
                            </td>
                        `;
            tbody.appendChild(tr);
          });
        } else {
          document.querySelector('#recentOrdersTable tbody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No transactions found.</td></tr>';
        }

        document.getElementById('totalOrders').innerText = totalCount;
        document.getElementById('openOrders').innerText = openCount;
        document.getElementById('salesToday').innerText = '₱' + salesDay.toLocaleString(undefined, {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });

      } catch (error) {
        console.error("Dashboard error:", error);
      }
    }

    async function markAsDone(id) {
      const confirm = await Swal.fire({
        title: 'Complete Order #' + id + '?',
        text: "This will set status to Completed and Payment to Paid.",
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Yes, Mark Done'
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
              status: 'Completed'
            })
          }).then(r => r.json());

          if (res.success) {
            Swal.fire({
              icon: 'success',
              title: 'Order Completed',
              timer: 1500,
              showConfirmButton: false
            });

            // Wait for database to commit before refreshing UI
            setTimeout(loadOverview, 400);
          } else {
            Swal.fire('Error', res.message || 'Update failed', 'error');
          }
        } catch (err) {
          Swal.fire('Error', 'Communication error with server.', 'error');
        }
      }
    }

    window.addEventListener('load', loadOverview);
  </script>
</body>

</html>