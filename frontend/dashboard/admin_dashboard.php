<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
$activeModule = 'dashboard';

// role validation
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
  header('Location: ../../index.php');
  exit;
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// EMPLOYEE DIRECTORY

$employeeQuery = $conn->query("
  SELECT user_id, employee_id, username, role, status, created_at 
  FROM users 
  WHERE role = 'Employee'
");
$employees = $employeeQuery ? $employeeQuery->fetch_all(MYSQLI_ASSOC) : [];

// ATTENDANCE APPROVAL REQUESTS

$requestQuery = $conn->query("
  SELECT a.attendance_id, u.username AS name, a.status, a.created_at
  FROM attendance a
  JOIN users u ON u.employee_id = a.employee_id
  WHERE a.status = 'Pending Approval'
  ORDER BY a.created_at DESC
");
$attendanceRequests = $requestQuery ? $requestQuery->fetch_all(MYSQLI_ASSOC) : [];

// NOTIFICATIONS

$notifications = [];

// Employees who haven't clocked in today
$notClockedQuery = $conn->query("
  SELECT username 
  FROM users 
  WHERE role = 'employee' 
  AND employee_id NOT IN (
    SELECT employee_id FROM attendance WHERE DATE(created_at) = CURDATE()
  )
");
if ($notClockedQuery && $notClockedQuery->num_rows > 0) {
  $notifications[] = "{$notClockedQuery->num_rows} employee(s) have not clocked in today.";
}

// Ongoing shifts (time_in but no time_out)
$ongoingQuery = $conn->query("
  SELECT username 
  FROM users 
  WHERE employee_id IN (
    SELECT employee_id FROM attendance WHERE DATE(created_at) = CURDATE() AND time_out IS NULL
  )
");
if ($ongoingQuery && $ongoingQuery->num_rows > 0) {
  $notifications[] = "{$ongoingQuery->num_rows} employee(s) have ongoing shifts.";
}

if (empty($notifications)) {
  $notifications[] = "No new notifications.";
}
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
    <!-- SIDEBAR -->
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
      <!-- <a href="#" class="nav-link"><i class="bi bi-envelope-paper me-2"></i>Requests</a> -->
      <!-- <a href="#" class="nav-link"><i class="bi bi-bell me-2"></i>Notifications</a> -->

      <div class="mt-auto">
        <form action="../../backend/auth/logout.php" method="POST" class="mt-3">
          <button type="submit" class="btn btn-outline-light btn-sm w-100">
            <i class="bi bi-box-arrow-right me-1"></i>Logout
          </button>
        </form>
        <p class="text-center text-secondary small mt-3 mb-0">© 2025 KakaiOne</p>
      </div>
    </nav>

    <!-- MAIN CONTENT -->
    <main id="main-content">
      <div class="container-fluid">
        <h3 class="fw-bold mb-4"><i class="bi bi-speedometer2 me-2"></i>Dashboard</h3>

        <!-- EMPLOYEE DIRECTORY -->
        <div class="module-card">
          <h5><i class="bi bi-people-fill me-2 text-primary"></i>Employee Directory</h5>
          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th><i class="bi bi-person-circle"></i> Username</th>
                  <th><i class="bi bi-briefcase"></i> Role</th>
                  <th><i class="bi bi-activity"></i> Status</th>
                  <th><i class="bi bi-calendar-event"></i> Created</th>
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
                  <tr>
                    <td colspan="5" class="text-center text-muted">No employees found.</td>
                  </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- ATTENDANCE APPROVAL REQUESTS -->
        <div class="module-card">
          <h5><i class="bi bi-envelope-paper me-2 text-warning"></i>Attendance Approval Requests</h5>
          <table class="table align-middle">
            <thead class="table-light">
              <tr>
                <th>Employee Name</th>
                <th>Status</th>
                <th>Date Requested</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!empty($attendanceRequests)): ?>
                <?php foreach ($attendanceRequests as $req): ?>
                  <tr>
                    <td><?= htmlspecialchars($req['name']); ?></td>
                    <td><?= htmlspecialchars($req['status']); ?></td>
                    <td><?= htmlspecialchars(date('Y-m-d H:i', strtotime($req['created_at']))); ?></td>
                    <td>
                      <button class="btn btn-sm btn-success"><i class="bi bi-check-circle"></i> Approve</button>
                      <button class="btn btn-sm btn-danger"><i class="bi bi-x-circle"></i> Decline</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php else: ?>
                <tr>
                  <td colspan="4" class="text-center text-muted">No pending requests.</td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- NOTIFICATIONS -->
        <div class="module-card">
          <h5><i class="bi bi-bell me-2 text-info"></i>Notifications / Reminders</h5>
          <ul class="list-group">
            <?php foreach ($notifications as $note): ?>
              <li class="list-group-item"><i class="bi bi-info-circle text-primary me-2"></i><?= htmlspecialchars($note); ?></li>
            <?php endforeach; ?>
          </ul>
        </div>
      </div>
    </main>
  </div>

</body>

</html>



<!-- {php} ATTENDANCE SUMMARY (Counts per status this month)

$attendanceStats = ['Approved' => 0, 'Pending Approval' => 0, 'Rejected' => 0];

$attendanceQuery = $conn->query("
  SELECT status, COUNT(*) AS total
  FROM attendance
  WHERE MONTH(created_at) = MONTH(CURRENT_DATE())
  GROUP BY status
");

if ($attendanceQuery) {
  while ($row = $attendanceQuery->fetch_assoc()) {
    $attendanceStats[$row['status']] = (int)$row['total'];
  }
}
            -->

<!-- {frontend} ATTENDANCE SUMMARY 
        <div class="module-card">
          <h5><i class="bi bi-calendar-check me-2 text-success"></i>Attendance Summary</h5>
          <div class="row align-items-center">
            <div class="col-md-8">
              <canvas id="attendanceChart" height="120"></canvas>
            </div>
            <div class="col-md-4 text-center">
              <p><i class="bi bi-check-circle text-success"></i> <strong>Approved:</strong> <?= $attendanceStats['Approved']; ?></p>
              <p><i class="bi bi-hourglass-split text-warning"></i> <strong>Pending:</strong> <?= $attendanceStats['Pending Approval']; ?></p>
              <p><i class="bi bi-x-circle text-danger"></i> <strong>Rejected:</strong> <?= $attendanceStats['Rejected']; ?></p>
            </div>
          </div>
        </div> -->

<!-- CHART
  <script>
    const ctx = document.getElementById('attendanceChart').getContext('2d');
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: ['Approved', 'Pending Approval', 'Rejected'],
        datasets: [{
          data: [<?= $attendanceStats['Approved']; ?>, <?= $attendanceStats['Pending Approval']; ?>, <?= $attendanceStats['Rejected']; ?>],
          backgroundColor: ['#198754', '#ffc107', '#dc3545'],
          borderWidth: 1
        }]
      },
      options: {
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true } }
      }
    });
  </script> -->