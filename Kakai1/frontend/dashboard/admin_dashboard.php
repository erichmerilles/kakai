<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// set active module
$activeModule = 'dashboard';

// role validation
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
  header('Location: ../../index.php');
  exit;
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// greeting based on time
$hour = date('H');
$greeting = ($hour < 12) ? 'Good morning' : (($hour < 18) ? 'Good afternoon' : 'Good evening');

// fetch dashboard stats using PDO
$dashboardStats = [
  'active_employees' => 0,
  'pending_overtimes' => 0,
  'low_stock' => 0
];

try {
  // total active employees
  $stmt = $pdo->query("SELECT COUNT(*) FROM employees WHERE status = 'Active'");
  $dashboardStats['active_employees'] = $stmt->fetchColumn() ?: 0;

  // pending overtime requests (Attendance requiring approval)
  $stmt = $pdo->query("SELECT COUNT(*) FROM attendance WHERE status = 'Pending Approval' OR pending_overtime > 0");
  $dashboardStats['pending_overtimes'] = $stmt->fetchColumn() ?: 0;

  // low stock items
  $stmt = $pdo->query("SELECT COUNT(*) FROM inventory WHERE quantity <= reorder_level");
  $dashboardStats['low_stock'] = $stmt->fetchColumn() ?: 0;
} catch (PDOException $e) {
  // Handle error silently for UI
}

// employee directory
$employees = [];
try {
  $stmt = $pdo->query("
        SELECT u.user_id, u.employee_id, u.username, u.role, u.status, u.created_at, e.first_name, e.last_name 
        FROM users u
        LEFT JOIN employees e ON u.employee_id = e.employee_id
        WHERE u.role = 'Employee'
    ");
  $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

// attendance approval requests (OVERTIME)
$attendanceRequests = [];
try {
  $stmt = $pdo->query("
        SELECT a.attendance_id, CONCAT(e.first_name, ' ', e.last_name) AS name, a.status, a.time_out, a.pending_overtime
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        WHERE a.status = 'Pending Approval' OR a.pending_overtime > 0
        ORDER BY a.time_out DESC
    ");
  $attendanceRequests = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

// notifications
$notifications = [];
try {
  // employees that are not logged in today
  $stmt = $pdo->query("
        SELECT username 
        FROM users 
        WHERE role = 'employee' 
        AND employee_id NOT IN (
            SELECT employee_id FROM attendance WHERE DATE(time_in) = CURDATE()
        )
    ");
  $notClocked = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($notClocked) > 0) {
    $notifications[] = [
      'icon' => 'bi-person-x',
      'color' => 'text-warning',
      'msg' => count($notClocked) . " employee(s) have not clocked in today."
    ];
  }

  // ongoing shifts
  $stmt = $pdo->query("
        SELECT username 
        FROM users 
        WHERE employee_id IN (
            SELECT employee_id FROM attendance WHERE DATE(time_in) = CURDATE() AND time_out IS NULL
        )
    ");
  $ongoing = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (count($ongoing) > 0) {
    $notifications[] = [
      'icon' => 'bi-clock-history',
      'color' => 'text-primary',
      'msg' => count($ongoing) . " employee(s) have ongoing shifts."
    ];
  }
} catch (PDOException $e) {
}

if (empty($notifications)) {
  $notifications[] = [
    'icon' => 'bi-check-circle',
    'color' => 'text-success',
    'msg' => "System is running smoothly. No new notifications."
  ];
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KakaiOne | Admin Dashboard</title>
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

    .module-card {
      border-radius: 8px;
      border: none;
      box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
      background: #fff;
    }

    .module-card-header {
      background: #fff;
      border-bottom: 1px solid rgba(0, 0, 0, .125);
      padding: 15px 20px;
      border-radius: 8px 8px 0 0;
      font-weight: bold;
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
            <h3 class="fw-bold text-dark mb-1">Dashboard</h3>
            <p class="text-muted mb-0"><?= $greeting ?>, <strong><?= htmlspecialchars($username) ?></strong>. Here is what's happening today.</p>
          </div>
          <div class="text-end">
            <span class="badge bg-dark px-3 py-2 fs-6 shadow-sm" id="liveClock">
              <i class="bi bi-clock"></i> Loading time...
            </span>
            <div class="small text-muted mt-1 fw-bold"><?= date('l, F j, Y') ?></div>
          </div>
        </div>

        <div class="row mb-4">
          <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-0 shadow-sm border-left-primary h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs fw-bold text-primary text-uppercase mb-1">Active Staff</div>
                    <div class="h4 mb-0 fw-bold text-dark"><?= $dashboardStats['active_employees']; ?></div>
                  </div>
                  <div class="col-auto"><i class="bi bi-people fa-2x text-primary opacity-50 fs-1"></i></div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-0 shadow-sm border-left-warning h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Overtimes</div>
                    <div class="h4 mb-0 fw-bold text-dark"><?= $dashboardStats['pending_overtimes']; ?></div>
                  </div>
                  <div class="col-auto"><i class="bi bi-clock-history fa-2x text-warning opacity-50 fs-1"></i></div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-0 shadow-sm border-left-danger h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs fw-bold text-danger text-uppercase mb-1">Inventory Alerts</div>
                    <div class="h4 mb-0 fw-bold text-dark"><?= $dashboardStats['low_stock']; ?></div>
                  </div>
                  <div class="col-auto"><i class="bi bi-box-seam fa-2x text-danger opacity-50 fs-1"></i></div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-0 shadow-sm border-left-success h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs fw-bold text-success text-uppercase mb-1">System Status</div>
                    <div class="h5 mb-0 fw-bold text-dark mt-2 mb-0">Online <i class="bi bi-circle-fill text-success ms-1" style="font-size: 0.8rem;"></i></div>
                  </div>
                  <div class="col-auto"><i class="bi bi-server fa-2x text-success fs-1"></i></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-lg-8">

            <div class="card mb-4 shadow-sm border-0">
              <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-people-fill me-2"></i>Employee Directory</span>
                <div class="input-group input-group-sm w-50">
                  <input type="text" id="employeeSearch" class="form-control" placeholder="Search employees...">
                  <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                </div>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped table-hover align-middle mb-0" id="employeeTable">
                    <thead class="table-light">
                      <tr>
                        <th class="ps-4"><i class="bi bi-person-circle"></i> Full Name</th>
                        <th><i class="bi bi-briefcase"></i> Role</th>
                        <th><i class="bi bi-activity"></i> Status</th>
                        <th class="pe-4"><i class="bi bi-calendar-event"></i> Created</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($employees)): ?>
                        <?php foreach ($employees as $emp): ?>
                          <tr>
                            <td class="ps-4 fw-bold text-dark">
                              <?= htmlspecialchars(trim($emp['first_name'] . ' ' . $emp['last_name'])) ?: htmlspecialchars($emp['username']); ?>
                            </td>
                            <td><?= htmlspecialchars($emp['role']); ?></td>
                            <td>
                              <span class="badge bg-<?= strtolower($emp['status']) === 'active' ? 'success' : 'secondary'; ?>">
                                <?= ucfirst($emp['status']); ?>
                              </span>
                            </td>
                            <td class="text-muted small pe-4"><?= date('M d, Y', strtotime($emp['created_at'])); ?></td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="4" class="text-center py-5 text-muted">
                            <i class="bi bi-people display-6 d-block mb-2 opacity-25"></i>
                            No employees found.
                          </td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

            <div class="card mb-4 shadow-sm border-warning">
              <div class="card-header bg-warning text-dark py-3">
                <i class="bi bi-clock-history me-2"></i><strong>Pending Overtimes & Approvals</strong>
              </div>
              <div class="card-body p-0">
                <div class="table-responsive">
                  <table class="table table-striped align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th class="ps-4">Employee Name</th>
                        <th>Request Type</th>
                        <th>Shift Ended At</th>
                        <th class="text-end pe-4">Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($attendanceRequests)): ?>
                        <?php foreach ($attendanceRequests as $req): ?>
                          <tr>
                            <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($req['name']); ?></td>
                            <td>
                              <?php if ($req['pending_overtime'] > 0): ?>
                                <span class="badge bg-danger">Overtime (<?= number_format($req['pending_overtime'], 1) ?> hrs)</span>
                              <?php else: ?>
                                <span class="badge bg-warning text-dark">Time Out Review</span>
                              <?php endif; ?>
                            </td>
                            <td class="text-muted small">
                              <?= !empty($req['time_out']) ? date('M d, Y - h:i A', strtotime($req['time_out'])) : '<span class="text-danger fst-italic">Missing Time Out</span>' ?>
                            </td>
                            <td class="text-end pe-4">
                              <a href="../attendance/attendance_page.php<?= !empty($req['time_out']) ? '?date=' . date('Y-m-d', strtotime($req['time_out'])) : '' ?>" class="btn btn-sm btn-primary shadow-sm" title="Review Request">
                                <i class="bi bi-eye"></i> Review
                              </a>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="4" class="text-center py-5 text-muted">
                            <i class="bi bi-check-circle display-6 d-block mb-2 text-success opacity-50"></i>
                            All caught up! No pending overtime requests.
                          </td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>

          </div>

          <div class="col-lg-4 mt-4 mt-lg-0">
            <div class="card mb-4 shadow-sm border-0">
              <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="bi bi-bell-fill me-2 text-info"></i>Activity Feed</span>
                <span class="badge bg-danger rounded-pill"><?= count($notifications) ?></span>
              </div>
              <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                  <?php foreach ($notifications as $note): ?>
                    <li class="list-group-item d-flex align-items-start py-3">
                      <i class="bi <?= $note['icon'] ?> <?= $note['color'] ?> fs-4 me-3 mt-1"></i>
                      <div>
                        <h6 class="mb-1 fw-bold text-dark text-sm">System Update</h6>
                        <p class="mb-0 text-muted small"><?= htmlspecialchars($note['msg']); ?></p>
                      </div>
                    </li>
                  <?php endforeach; ?>
                </ul>
              </div>
            </div>
          </div>

        </div>

      </div>

    </main>
  </div>

  <script>
    // clock script
    function updateClock() {
      const now = new Date();
      let hours = now.getHours();
      let minutes = now.getMinutes();
      let seconds = now.getSeconds();
      const ampm = hours >= 12 ? 'PM' : 'AM';

      hours = hours % 12;
      hours = hours ? hours : 12;
      minutes = minutes < 10 ? '0' + minutes : minutes;
      seconds = seconds < 10 ? '0' + seconds : seconds;

      const strTime = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
      document.getElementById('liveClock').innerHTML = '<i class="bi bi-clock me-1"></i> ' + strTime;
    }
    setInterval(updateClock, 1000);
    updateClock(); // initial call

    // employee search filter
    document.getElementById('employeeSearch').addEventListener('keyup', function() {
      let filter = this.value.toLowerCase();
      let rows = document.querySelectorAll('#employeeTable tbody tr');

      rows.forEach(row => {
        if (row.cells.length === 1) return;
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
      });
    });
  </script>
</body>

</html>