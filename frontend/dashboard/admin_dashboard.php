<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Redirect if not logged in or not Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
  header('Location: ../../index.php');
  exit;
}

// EMPLOYEE DIRECTORY
$employeeQuery = $conn->query("
  SELECT user_id, employee_id, username, role, status, created_at 
  FROM users 
  WHERE role = 'Employee'
");
$employees = $employeeQuery ? $employeeQuery->fetch_all(MYSQLI_ASSOC) : [];

// NOTIFICATIONS
$notifications = [];
$notClockedQuery = $conn->query("SELECT username FROM users WHERE role = 'employee' AND employee_id NOT IN (SELECT employee_id FROM attendance WHERE DATE(created_at) = CURDATE())");
if ($notClockedQuery && $notClockedQuery->num_rows > 0) $notifications[] = "{$notClockedQuery->num_rows} employee(s) have not clocked in today.";

$ongoingQuery = $conn->query("SELECT username FROM users WHERE employee_id IN (SELECT employee_id FROM attendance WHERE DATE(created_at) = CURDATE() AND time_out IS NULL)");
if ($ongoingQuery && $ongoingQuery->num_rows > 0) $notifications[] = "{$ongoingQuery->num_rows} employee(s) have ongoing shifts.";

if (empty($notifications)) $notifications[] = "No new notifications.";
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KakaiOne | Admin Dashboard</title>
  <?php include '../includes/links.php'; ?>
</head>
<body>
  <div id="dashboardContainer">
    <nav id="sidebar">
      <div class="text-center mb-4">
        <img src="../assets/images/logo.jpg" alt="KakaiOne Logo" width="80" height="80" style="border-radius: 50%; margin-bottom:10px;">
        <h5 class="fw-bold text-light">KakaiOne</h5>
        <p class="small text-light mb-3">Admin Panel</p>
      </div>

      <a href="admin_dashboard.php" class="nav-link active"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
      <a href="../employee/employee_module.php" class="nav-link"><i class="bi bi-people-fill me-2"></i>Employees</a>
      <a href="../inventory/inventory_overview.php" class="nav-link"><i class="bi bi-box-seam me-2"></i>Inventory</a>
      <a href="../payroll/payroll_module.php" class="nav-link"><i class="bi bi-cash-coin me-2"></i>Payroll</a>
      <a href="#" class="nav-link"><i class="bi bi-graph-up-arrow me-2"></i>Sales Analytics</a>
      <a href="../ordering/ordering_module.php" class="nav-link"><i class="bi bi-cart-check me-2"></i>Ordering</a>

      <div class="mt-auto">
        <form action="../../backend/auth/logout.php" method="POST" class="mt-3">
          <button type="submit" class="btn btn-outline-light btn-sm w-100">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
          </button>
        </form>
        <p class="text-center text-secondary small mt-3 mb-0">© 2025 KakaiOne</p>
      </div>
    </nav>

    <main id="main-content">
      <div class="container-fluid">
        <h3 class="fw-bold mb-4"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h3>

        <div class="module-card mb-4">
          <h5 class="mb-3"><i class="bi bi-clock-history me-2 text-warning"></i>Attendance Approval Requests</h5>
          <div class="table-responsive">
            <table class="table align-middle table-hover">
              <thead class="table-light">
                <tr>
                  <th>Employee Name</th>
                  <th>Status</th>
                  <th>Clock In Time</th>
                  <th>Date Requested</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody id="requestsTableBody">
                 <tr><td colspan="5" class="text-center text-muted py-4">Loading requests...</td></tr>
              </tbody>
            </table>
          </div>
        </div>

        <div class="module-card mb-4">
          <h5><i class="bi bi-people-fill me-2 text-primary"></i>Employee Directory</h5>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th>Username</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Joined Date</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!empty($employees)): ?>
                  <?php foreach ($employees as $emp): ?>
                    <tr>
                      <td><?= htmlspecialchars($emp['username']); ?></td>
                      <td><?= htmlspecialchars($emp['role']); ?></td>
                      <td>
                        <span class="badge bg-<?= $emp['status'] === 'active' ? 'success' : 'secondary'; ?>">
                          <?= ucfirst($emp['status']); ?>
                        </span>
                      </td>
                      <td><?= date('Y-m-d', strtotime($emp['created_at'])); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center text-muted">No employees found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="module-card">
          <h5><i class="bi bi-bell me-2 text-info"></i>System Notifications</h5>
          <ul class="list-group list-group-flush">
            <?php foreach ($notifications as $note): ?>
              <li class="list-group-item bg-transparent border-bottom"><i class="bi bi-info-circle text-primary me-2"></i><?= htmlspecialchars($note); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </main>
  </div>

  <script>
    // LIVE REQUESTS
    async function loadRequests() {
        try {
            const res = await fetch('../../backend/attendance/get_requests.php');
            const json = await res.json();
            const tbody = document.getElementById('requestsTableBody');
            
            // Handle empty state
            if (!json.success || !json.data || json.data.length === 0) {
                if (tbody.innerHTML.indexOf('No pending requests') === -1) {
                   tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4">No pending requests found.</td></tr>';
                }
                return;
            }

            // Table Rows
            let html = '';
            json.data.forEach(req => {
                html += `
                    <tr id="row-${req.attendance_id}">
                      <td class="fw-bold">${req.name}</td>
                      <td><span class="badge bg-warning text-dark">${req.status}</span></td>
                      <td>${req.time_in_fmt}</td>
                      <td>${req.date_fmt}</td>
                      <td>
                        <button class="btn btn-sm btn-success me-1" onclick="updateAttendance(${req.attendance_id}, 'approve')">
                            <i class="bi bi-check-circle"></i> Approve
                        </button>
                        <button class="btn btn-sm btn-danger" onclick="updateAttendance(${req.attendance_id}, 'reject')">
                            <i class="bi bi-x-circle"></i> Decline
                        </button>
                      </td>
                    </tr>
                `;
            });
            
            if (tbody.innerHTML !== html) {
                tbody.innerHTML = html;
            }

        } catch (e) {
            console.error("Polling error", e);
        }
    }

    // Start Polling
    setInterval(loadRequests, 3000);
    loadRequests();

    // APPROVE/DECLINE ACTION
    async function updateAttendance(id, action) {
        const actionText = action === 'approve' ? 'Approve' : 'Decline';
        const confirmButtonColor = action === 'approve' ? '#198754' : '#dc3545';

        const result = await Swal.fire({
            title: `Confirm ${actionText}?`,
            text: `Are you sure you want to ${actionText.toLowerCase()} this clock-in request?`,
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: confirmButtonColor,
            confirmButtonText: `Yes, ${actionText}`
        });

        if (!result.isConfirmed) return;

        try {
            const res = await fetch('../../backend/attendance/update_status.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ attendance_id: id, action: action })
            });
            const data = await res.json();

            if (data.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: data.message,
                    timer: 1500,
                    showConfirmButton: false
                });
                loadRequests();
            } else {
                Swal.fire('Error', data.message, 'error');
            }
        } catch (error) {
            console.error(error);
            Swal.fire('Error', 'Failed to connect to server.', 'error');
        }
    }
  </script>
</body>
</html>