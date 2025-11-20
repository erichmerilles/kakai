<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
  header('Location: ../auth/login.php');
  exit;
}

// Fetch Employees
$employeeQuery = $conn->query("
  SELECT e.employee_id, e.first_name, e.last_name, e.position, e.status, e.date_hired
  FROM employees e
  ORDER BY e.date_hired DESC
");
$employees = $employeeQuery ? $employeeQuery->fetch_all(MYSQLI_ASSOC) : [];

// Attendance Summary
$attendanceStats = ['on_time' => 0, 'late' => 0, 'absent' => 0];
$aQuery = $conn->query("SELECT status, COUNT(*) AS total FROM attendance GROUP BY status");
if ($aQuery) {
  while ($row = $aQuery->fetch_assoc()) {
    $attendanceStats[strtolower(str_replace(' ', '_', $row['status']))] = $row['total'];
  }
}

// Leave Requests
$reqQuery = $conn->query("
  SELECT lr.leave_id, u.username, lr.leave_type, lr.start_date, lr.end_date, lr.status
  FROM leave_requests lr
  JOIN users u ON lr.employee_id = u.employee_id
  WHERE lr.status = 'Pending'
");
$leaveRequests = $reqQuery ? $reqQuery->fetch_all(MYSQLI_ASSOC) : [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KakaiOne | Employee Management</title>
  <?php include '../includes/links.php'; ?>
  <?php include 'e_sidebar.php'; ?>
</head>
<body>

    <div id="dashboardContainer">
    <main id="main-content">

      <div class="container">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h3 class="fw-bold">
              <i class="bi bi-people-fill me-2 text-warning"></i>Employee Management
            </h3>

            <div class="d-flex flex-column gap-3" style="max-width: 250px;">
              <a href="../dashboard/admin_dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back
              </a>
              <a href="employee_form.php" class="btn btn-warning">
                <i class="bi bi-person-plus"></i> Add Employee
              </a>
            </div>
        </div>

        <div class="card mb-4 shadow-sm">
          <div class="card-header bg-dark text-white">
            <i class="bi bi-list-ul me-2"></i>Employee Directory
          </div>

          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-striped align-middle">
                <thead>
                  <tr>
                    <th>Full Name</th>
                    <th>Position</th>
                    <th>Status</th>
                    <th>Date Hired</th>
                    <th>Action</th>
                  </tr>
                </thead>

                <tbody>
                  <?php if (!empty($employees)): ?>
                    <?php foreach ($employees as $emp): ?>
                      <tr>
                        <td><?= htmlspecialchars($emp['first_name'].' '.$emp['last_name']); ?></td>
                        <td><?= htmlspecialchars($emp['position']); ?></td>

                        <td>
                          <span class="badge bg-<?= $emp['status'] === 'Active' ? 'success' : 'secondary'; ?>">
                            <?= htmlspecialchars($emp['status']); ?>
                          </span>
                        </td>

                        <td><?= date('Y-m-d', strtotime($emp['date_hired'])); ?></td>

                        <td>
                          <a href="view_employee.php?id=<?= $emp['employee_id']; ?>" class="btn btn-sm btn-info">
                            <i class="bi bi-eye"></i>
                          </a>
                          <a href="employee_form.php?id=<?= $emp['employee_id']; ?>" class="btn btn-sm btn-warning">
                            <i class="bi bi-pencil"></i>
                          </a>
                          <button class="btn btn-sm btn-danger" onclick="confirmDeactivate(<?= $emp['employee_id']; ?>)">
                          <i class="bi bi-x-circle"></i>
                          </button>
                          <a href="access_control.php?id=<?= $emp['employee_id']; ?>" class="btn btn-sm btn-warning">
                              <i class="bi bi-shield-lock"></i> Access
                          </a>
                        </td>

                      </tr>
                    <?php endforeach; ?>
                  <?php else: ?>
                      <tr><td colspan="6" class="text-center">No employees found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card mb-4 shadow-sm">
          <div class="card-header bg-dark text-white">
            <i class="bi bi-bar-chart me-2"></i>Attendance Summary
          </div>

          <div class="card-body">
            <div class="row align-items-center">
              <div class="col-md-8">
                <canvas id="attendanceChart" height="100"></canvas>
              </div>

              <div class="col-md-4">
                <p><i class="bi bi-check-circle text-success"></i> On-Time: <?= $attendanceStats['on_time']; ?></p>
                <p><i class="bi bi-clock text-warning"></i> Late: <?= $attendanceStats['late']; ?></p>
                <p><i class="bi bi-x-circle text-danger"></i> Absent: <?= $attendanceStats['absent']; ?></p>
              </div>
            </div>
          </div>
        </div>

        <div class="card mb-4 shadow-sm">
          <div class="card-header bg-dark text-white">
            <i class="bi bi-envelope-paper me-2"></i>Leave Requests
          </div>

          <div class="card-body">
            <table class="table table-striped align-middle">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Type</th>
                  <th>Date Requested</th>
                  <th>Action</th>
                </tr>
              </thead>

              <tbody>
                <?php if (!empty($leaveRequests)): ?>
                  <?php foreach ($leaveRequests as $req): ?>
                    <tr>
                      <td><?= htmlspecialchars($req['username']); ?></td>
                      <td><?= htmlspecialchars($req['leave_type']); ?></td>
                      <td><?= htmlspecialchars($req['start_date']." to ".$req['end_date']); ?></td>

                      <td>
                        <a href="../../backend/employees/approve_request.php?id=<?= $req['leave_id']; ?>" class="btn btn-sm btn-success"><i class="bi bi-check-circle"></i></a>
                        <a href="../../backend/employees/decline_request.php?id=<?= $req['leave_id']; ?>" class="btn btn-sm btn-danger"><i class="bi bi-x-circle"></i></a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php else: ?>
                  <tr><td colspan="4" class="text-center">No pending requests.</td></tr>
                <?php endif; ?>
              </tbody>

            </table>
          </div>
        </div>

      </div>

    </main>

</div>

<script>
const ctx = document.getElementById('attendanceChart').getContext('2d');
new Chart(ctx, {
  type: 'bar',
  data: {
    labels: ['On Time', 'Late', 'Absent'],
    datasets: [{
      data: [<?= $attendanceStats['on_time']; ?>, <?= $attendanceStats['late']; ?>, <?= $attendanceStats['absent']; ?>],
      backgroundColor: ['#198754', '#ffc107', '#dc3545']
    }]
  },
  options: { plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
});

function confirmDeactivate(empId) {
  Swal.fire({
    title: 'Deactivate Employee?',
    text: "This will disable the employee's account but keep their record in the system.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, deactivate',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      window.location.href = `employee_form.php?id=${empId}&action=deactivate`;
    }
  });
}

</script>

</body>
</html>