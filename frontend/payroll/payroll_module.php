<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
$activeModule = 'payroll';
include '../includes/sidebar.php';
include '../includes/links.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.php');
  exit;
}

?>
<?php include '../includes/links.php'; ?>
<!--<?php include 'p_sidebar.php'; ?>-->

<!--<div id="sidebar" class="d-flex flex-column">
  <div class="text-center mb-4">
    <img src="../assets/images/logo.jpg" width="100" class="rounded mb-2">
    <h5 class="fw-bold text-light">KakaiOne</h5>
    <p class="small text-light">Payroll</p>
  </div>
  <nav class="nav flex-column px-3">
    <a href="payroll_module.php" class="nav-link active"><i class="bi bi-wallet2 me-2"></i>Payroll</a>
    <a href="../employee/employee_module.php" class="nav-link"><i class="bi bi-people-fill me-2"></i>Employees</a>
    <a href="../attendance/attendance_page.php" class="nav-link"><i class="bi bi-calendar-check me-2"></i>Attendance</a>
    <div class="mt-auto">
      <form action="../../backend/auth/logout.php" method="POST">
        <button class="btn btn-outline-light btn-sm w-100">Logout</button>
      </form>
      <p class="text-center text-secondary small mt-3 mb-0">© 2025 KakaiOne</p>
    </div>
  </nav>
</div>-->

<main id="main-content">
  <div class="container-fluid p-4">
    <h3 class="fw-bold mb-4"><i class="bi bi-wallet2 me-2"></i>Payroll</h3>

    <div class="module-card mb-4">
      <h5 class="mb-3">Generate Payroll (custom cutoff)</h5>
      <div class="row g-2 align-items-end">
        <div class="col-md-3">
          <label class="form-label">Start Date</label>
          <input type="date" id="startDate" class="form-control" />
        </div>
        <div class="col-md-3">
          <label class="form-label">End Date</label>
          <input type="date" id="endDate" class="form-control" />
        </div>
        <div class="col-md-3">
          <button id="generateBtn" class="btn btn-pri">Generate Payroll</button>
        </div>
      </div>
      <div id="genMessage" class="mt-3"></div>
    </div>

    <div class="module-card">
      <h5 class="mb-3">Payroll Runs</h5>
      <div class="table-responsive">
        <table class="table table-hover align-middle" id="runsTable">
          <thead class="table-light">
            <tr>
              <th>Run ID</th>
              <th>Period</th>
              <th>Created</th>
              <th>Gross</th>
              <th>Deductions</th>
              <th>Net</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>

  </div>
</main>

<script>
  async function loadRuns() {
    const res = await fetch('../../backend/payroll/get_runs.php').then(r => r.json());
    const tbody = document.querySelector('#runsTable tbody');
    tbody.innerHTML = '';
    if (!res.success) return;
    res.data.forEach(r => {
      const tr = document.createElement('tr');
      tr.innerHTML = `<td>${r.payroll_id}</td>
      <td>${r.start_date} → ${r.end_date}</td>
      <td>${r.created_at}</td>
      <td>₱${Number(r.total_gross).toFixed(2)}</td>
      <td>₱${Number(r.total_deductions).toFixed(2)}</td>
      <td><strong>₱${Number(r.total_net).toFixed(2)}</strong></td>
      <td>
        <button class="btn btn-sm btn-outline-primary" onclick="viewRun(${r.payroll_id})">View</button>
      </td>`;
      tbody.appendChild(tr);
    });
  }

  document.getElementById('generateBtn').addEventListener('click', async () => {
    const start = document.getElementById('startDate').value;
    const end = document.getElementById('endDate').value;
    const msg = document.getElementById('genMessage');
    msg.innerHTML = '';
    if (!start || !end) {
      msg.innerHTML = '<div class="alert alert-warning">Choose start and end dates</div>';
      return;
    }
    msg.innerHTML = '<div class="alert alert-info">Generating payroll — please wait...</div>';
    const res = await fetch('../../backend/payroll/generate_payroll.php', {
      method: 'POST',
      body: JSON.stringify({
        start_date: start,
        end_date: end
      })
    }).then(r => r.json());
    if (res.success) {
      msg.innerHTML = '<div class="alert alert-success">Payroll generated. Run ID: ' + res.payroll_id + '</div>';
      loadRuns();
    } else {
      msg.innerHTML = '<div class="alert alert-danger">' + (res.message || 'Error') + '</div>';
    }
  });

  function viewRun(id) {
    const win = window.open('', '_blank', 'width=1000,height=800');

    const html = `
    <html>
    <head>
      <title>Payroll Run ${id}</title>
      <link rel="stylesheet" href="../../frontend/assets/css/style.css">
    </head>
    <body>
      <div id="root" class="p-4">Loading...</div>

      <script>
        (async function() {
          const res = await fetch('../../backend/payroll/get_entries.php?payroll_id=${id}');
          const data = await res.json();
          const root = document.getElementById('root');

          if (!data.success) {
            root.innerHTML = "<div>Failed to load payroll data.</div>";
            return;
          }

          let table = "<h3>Payroll Run ${id}</h3>";
          table += "<table style='width:100%; border-collapse:collapse;' border='1' cellpadding='6'>";
          table += "<tr><th>Employee</th><th>Gross</th><th>Cash Advance</th><th>Net</th><th>Payslip</th></tr>";

          data.data.forEach(row => {
            table += "<tr>";
            table += "<td>" + row.first_name + " " + row.last_name + "</td>";
            table += "<td>₱" + Number(row.gross_pay).toFixed(2) + "</td>";
            table += "<td>₱" + Number(row.cash_advance).toFixed(2) + "</td>";
            table += "<td><strong>₱" + Number(row.net_pay).toFixed(2) + "</strong></td>";
            table += "<td><a href='../../backend/payroll/payslip.php?payroll_id=${id}&employee_id=" + row.employee_id + "' target='_blank'>Print</a></td>";
            table += "</tr>";
          });

          table += "</table>";
          root.innerHTML = table;
        })();
      <\/script>
    </body>
    </html>
  `;

    win.document.write(html);
    win.document.close();
  }

  loadRuns();
</script>