<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// set active module
$activeModule = 'payroll';

// role validation
if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.php');
  exit;
}

// check page permissions
requirePermission('payroll_view');

// check permissions
$canGenerate = hasPermission('payroll_generate');
$canPrint = hasPermission('payslip_print');

// fetch active employees for the dropdown
$activeEmployees = [];
try {
  $empStmt = $pdo->query("SELECT employee_id, first_name, last_name FROM employees WHERE status = 'Active' AND role = 'Employee' ORDER BY first_name ASC");
  $activeEmployees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}

// fetch payroll summary stats for KPI cards
$payrollStats = [
  'total_runs' => 0,
  'total_payout' => 0,
  'latest_gross' => 0,
  'total_deductions' => 0
];

try {
  // total runs
  $stmt1 = $pdo->query("SELECT COUNT(*) FROM payroll_runs");
  $payrollStats['total_runs'] = $stmt1->fetchColumn() ?: 0;

  // sum of all net payouts and deductions
  $stmt2 = $pdo->query("SELECT SUM(total_net), SUM(total_deductions) FROM payroll_runs");
  $sums = $stmt2->fetch(PDO::FETCH_NUM);
  if ($sums) {
    $payrollStats['total_payout'] = $sums[0] ?: 0;
    $payrollStats['total_deductions'] = $sums[1] ?: 0;
  }

  // latest gross payout
  $stmt3 = $pdo->query("SELECT total_gross FROM payroll_runs ORDER BY created_at DESC LIMIT 1");
  $payrollStats['latest_gross'] = $stmt3->fetchColumn() ?: 0;
} catch (PDOException $e) {
  // handle database errors
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>KakaiOne | Payroll Management</title>

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

    /* Custom styling for sleek tabs */
    .nav-tabs .nav-link {
      border: none;
      color: #6c757d;
      border-radius: 0;
      padding: 12px 20px;
      transition: 0.3s;
    }

    .nav-tabs .nav-link.active {
      border-bottom: 3px solid #ffc107 !important;
      color: #000;
      background: transparent;
      font-weight: bold;
    }

    .nav-tabs .nav-link:hover:not(.active) {
      border-bottom: 3px solid #dee2e6;
      color: #495057;
    }

    @media print {

      #sidebar,
      .btn,
      .input-group,
      .generate-section,
      .nav-tabs {
        display: none !important;
      }

      #main-content {
        margin-left: 0 !important;
        padding: 0 !important;
      }

      .col-lg-8,
      .col-lg-12 {
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
              <i class="bi bi-wallet2 me-2 text-warning"></i>Payroll Management
            </h3>
            <p class="text-muted mb-0">Generate payslips, track disbursements, and review financial analytics.</p>
          </div>
        </div>

        <div class="row mb-4">
          <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-0 shadow-sm border-left-primary h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Payroll Runs</div>
                    <div class="h4 mb-0 fw-bold text-dark"><?= $payrollStats['total_runs']; ?></div>
                  </div>
                  <div class="col-auto"><i class="bi bi-calculator fa-2x text-primary opacity-50 fs-1"></i></div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-0 shadow-sm border-left-success h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs fw-bold text-success text-uppercase mb-1">Total Net Payout</div>
                    <div class="h4 mb-0 fw-bold text-dark">₱ <?= number_format($payrollStats['total_payout'], 2); ?></div>
                  </div>
                  <div class="col-auto"><i class="bi bi-cash fa-2x text-success opacity-50 fs-1"></i></div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-0 shadow-sm border-left-warning h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs fw-bold text-warning text-uppercase mb-1">Last Gross Payout</div>
                    <div class="h4 mb-0 fw-bold text-dark">₱ <?= number_format($payrollStats['latest_gross'], 2); ?></div>
                  </div>
                  <div class="col-auto"><i class="bi bi-wallet fa-2x text-warning opacity-50 fs-1"></i></div>
                </div>
              </div>
            </div>
          </div>

          <div class="col-xl-3 col-md-6 mb-3">
            <div class="card stat-card border-0 shadow-sm border-left-danger h-100 py-2">
              <div class="card-body">
                <div class="row no-gutters align-items-center">
                  <div class="col mr-2">
                    <div class="text-xs fw-bold text-danger text-uppercase mb-1">Total Deductions</div>
                    <div class="h4 mb-0 fw-bold text-dark">₱ <?= number_format($payrollStats['total_deductions'], 2); ?></div>
                  </div>
                  <div class="col-auto"><i class="bi bi-graph-down-arrow fa-2x text-danger opacity-50 fs-1"></i></div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <ul class="nav nav-tabs mb-4 border-bottom" id="payrollTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="generation-tab" data-bs-toggle="tab" data-bs-target="#generation" type="button" role="tab">
              <i class="bi bi-gear-wide-connected me-1"></i> Generation & History
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="analytics-tab" data-bs-toggle="tab" data-bs-target="#analytics" type="button" role="tab" onclick="loadSummary()">
              <i class="bi bi-bar-chart-line me-1"></i> Analytics & Summary
            </button>
          </li>
        </ul>

        <div class="tab-content" id="payrollTabsContent">

          <div class="tab-pane fade show active" id="generation" role="tabpanel">
            <div class="row">
              <?php if ($canGenerate): ?>
                <div class="col-lg-4 generate-section">
                  <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-dark text-white">
                      <i class="bi bi-gear-fill me-2"></i>Generate Payroll
                    </div>
                    <div class="card-body p-4">
                      <p class="small text-muted mb-4">Select a custom cutoff date to calculate employee attendance, deductions, and final net pay.</p>

                      <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">Start Date <span class="text-danger">*</span></label>
                        <input type="date" id="startDate" class="form-control bg-light" />
                      </div>
                      <div class="mb-3">
                        <label class="form-label fw-bold small text-dark">End Date <span class="text-danger">*</span></label>
                        <input type="date" id="endDate" class="form-control bg-light" />
                      </div>

                      <div class="mb-4">
                        <label class="form-label fw-bold small text-dark">Select Employee <span class="text-danger">*</span></label>
                        <select id="employeeSelect" class="form-select bg-light fw-bold text-primary">
                          <option value="all">✦ All Eligible Employees (Batch Run)</option>
                          <?php foreach ($activeEmployees as $emp): ?>
                            <option value="<?= $emp['employee_id'] ?>">
                              <?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <button id="generateBtn" class="btn btn-warning w-100 fw-bold text-dark py-2 shadow-sm">
                        <i class="bi bi-gear-wide-connected me-1"></i> Process Payroll
                      </button>

                      <div id="genMessage" class="mt-3"></div>
                    </div>
                  </div>
                </div>
                <div class="col-lg-8">
                <?php else: ?>
                  <div class="col-lg-12">
                  <?php endif; ?>

                  <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                      <span><i class="bi bi-clock-history me-2"></i>Payroll Runs History</span>
                      <div class="input-group input-group-sm w-50">
                        <input type="text" id="tableSearch" class="form-control" placeholder="Search period or run ID...">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                      </div>
                    </div>
                    <div class="card-body p-0">
                      <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0" id="runsTable">
                          <thead class="table-light">
                            <tr>
                              <th class="ps-4">Run ID</th>
                              <th>Period</th>
                              <th>Gross Pay</th>
                              <th>Deductions</th>
                              <th>Total Net</th>
                              <th class="text-end pe-4">Action</th>
                            </tr>
                          </thead>
                          <tbody>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                  </div>
                </div>
            </div>

            <div class="tab-pane fade" id="analytics" role="tabpanel">
              <div class="row">
                <div class="col-lg-4">
                  <div class="card mb-4 shadow-sm border-0">
                    <div class="card-header bg-dark text-white">
                      <i class="bi bi-funnel-fill me-2"></i>Analytics Controls
                    </div>
                    <div class="card-body p-4">
                      <p class="small text-muted mb-4">Filter the payroll data to view daily, weekly, or monthly company expense trends.</p>

                      <div class="mb-4">
                        <label class="form-label fw-bold small text-dark">Time Period Filter <span class="text-danger">*</span></label>
                        <select id="summaryFilter" class="form-select bg-light fw-bold text-primary">
                          <option value="daily">Daily View</option>
                          <option value="weekly">Weekly View</option>
                          <option value="monthly" selected>Monthly View</option>
                        </select>
                      </div>

                      <button onclick="exportSummaryCSV()" class="btn btn-success w-100 fw-bold text-white py-2 shadow-sm">
                        <i class="bi bi-file-earmark-excel me-1"></i> Export Data
                      </button>
                    </div>
                  </div>
                </div>

                <div class="col-lg-8">
                  <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                      <span><i class="bi bi-graph-up-arrow me-2"></i>Store Expense Trends</span>
                    </div>
                    <div class="card-body">
                      <div style="position: relative; height: 35vh; width: 100%;">
                        <canvas id="payrollChart"></canvas>
                      </div>
                    </div>
                  </div>

                  <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                      <span><i class="bi bi-table me-2"></i>Combined Data Breakdown</span>
                    </div>
                    <div class="card-body p-0">
                      <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0" id="summaryTable">
                          <thead class="table-light">
                            <tr>
                              <th class="ps-4">Time Period</th>
                              <th>Employees Processed</th>
                              <th>Gross Disbursed</th>
                              <th>Cash Advances Deducted</th>
                              <th class="fw-bold text-success">Net Store Payout</th>
                            </tr>
                          </thead>
                          <tbody>
                          </tbody>
                        </table>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div>
        </div>
    </main>
  </div>

  <script>
    // table search filter
    document.getElementById('tableSearch').addEventListener('keyup', function() {
      let filter = this.value.toLowerCase();
      let rows = document.querySelectorAll('#runsTable tbody tr');

      rows.forEach(row => {
        // skip empty state row
        if (row.cells.length === 1) return;
        let text = row.innerText.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
      });
    });

    // load history
    async function loadRuns() {
      try {
        const res = await fetch('../../backend/payroll/get_runs.php').then(r => r.json());
        const tbody = document.querySelector('#runsTable tbody');
        tbody.innerHTML = '';

        if (!res.success || !res.data || res.data.length === 0) {
          tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-5"><i class="bi bi-inbox display-6 d-block mb-3 opacity-25"></i>No payroll runs found.</td></tr>';
          return;
        }

        res.data.forEach(r => {
          let actionHtml = '';

          // If already published, show a green badge
          if (r.is_published == 1) {
            actionHtml += `<span class="badge bg-success me-2 py-2"><i class="bi bi-check2-all"></i> Distributed</span>`;
          } else {
            // If NOT published, show the Distribute button
            <?php if ($canGenerate): ?>
              actionHtml += `<button class="btn btn-sm btn-warning shadow-sm px-3 me-2 fw-bold text-dark" onclick="distributeRun(${r.payroll_id})" title="Distribute to Employees"><i class="bi bi-send-fill me-1"></i> Distribute</button>`;
            <?php endif; ?>
          }

          // view details button for all runs
          actionHtml += `<button class="btn btn-sm btn-info text-white shadow-sm px-2 me-1" onclick="viewRun(${r.payroll_id})" title="View Details"><i class="bi bi-eye"></i></button>`;

          // delete button only for unpublished runs
          <?php if ($canGenerate): ?>
            if (r.is_published == 0) {
              actionHtml += `<button class="btn btn-sm btn-danger shadow-sm px-2" onclick="deleteRun(${r.payroll_id})" title="Delete Run"><i class="bi bi-trash"></i></button>`;
            }
          <?php endif; ?>

          const tr = document.createElement('tr');
          tr.innerHTML = `
                <td class="ps-4"><span class="badge bg-secondary rounded-pill">#${r.payroll_id}</span></td>
                <td>
                    <div class="fw-bold text-dark">${r.start_date} <i class="bi bi-arrow-right-short text-muted"></i> ${r.end_date}</div>
                    <div class="small text-muted">Ran on: ${r.created_at}</div>
                </td>
                <td class="text-muted fw-semibold">₱${Number(r.total_gross).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="text-danger fw-semibold">-₱${Number(r.total_deductions).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="fw-bold text-success fs-6">₱${Number(r.total_net).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td class="text-end pe-4">${actionHtml}</td>`;
          tbody.appendChild(tr);
        });
      } catch (err) {
        console.error("Error loading runs", err);
      }
    }

    <?php if ($canGenerate): ?>
      // generate payroll
      document.getElementById('generateBtn').addEventListener('click', async () => {
        const start = document.getElementById('startDate').value;
        const end = document.getElementById('endDate').value;
        const empId = document.getElementById('employeeSelect').value;

        if (!start || !end) {
          Swal.fire('Missing Dates', 'Please select both start and end dates.', 'warning');
          return;
        }

        const confirm = await Swal.fire({
          title: 'Process Payroll?',
          text: `Calculate pay and deduct cash advances for the period: ${start} to ${end}?`,
          icon: 'question',
          showCancelButton: true,
          confirmButtonColor: '#ffc107',
          cancelButtonColor: '#6c757d',
          confirmButtonText: '<span class="text-dark fw-bold">Yes, Process Now</span>'
        });

        if (!confirm.isConfirmed) return;

        const btn = document.getElementById('generateBtn');
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Calculating...';

        try {
          const res = await fetch('../../backend/payroll/generate_payroll.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              start_date: start,
              end_date: end,
              employee_id: empId
            })
          }).then(r => r.json());

          if (res.success) {
            Swal.fire('Success!', `Payroll Batch generated successfully.`, 'success').then(() => location.reload());
          } else {
            Swal.fire('Error', res.message || 'Error generating payroll.', 'error');
          }
        } catch (error) {
          Swal.fire('Server Error', 'Failed to communicate with the server.', 'error');
        } finally {
          btn.disabled = false;
          btn.innerHTML = originalText;
        }
      });

      async function deleteRun(id) {
        const confirm = await Swal.fire({
          title: 'Delete Payroll Run?',
          text: 'Permanently delete this record? Attendance and cash advances will be marked as unpaid again.',
          icon: 'warning',
          showCancelButton: true,
          confirmButtonColor: '#dc3545',
          confirmButtonText: 'Yes, Delete it!'
        });

        if (confirm.isConfirmed) {
          try {
            const res = await fetch(`../../backend/payroll/delete_run.php?id=${id}`, {
              method: 'POST'
            }).then(r => r.json());
            if (res.success) {
              Swal.fire('Deleted!', 'Payroll run deleted and records reverted.', 'success').then(() => location.reload());
            } else {
              Swal.fire('Error', res.message, 'error');
            }
          } catch (e) {
            Swal.fire('Error', 'Server communication failed.', 'error');
          }
        }
      }
    <?php endif; ?>

    async function distributeRun(id) {
      const confirm = await Swal.fire({
        title: 'Distribute Payslips?',
        text: 'This will lock the payroll and make the payslips visible to the employees. Proceed?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        confirmButtonText: 'Yes, Distribute'
      });

      if (confirm.isConfirmed) {
        try {
          const res = await fetch(`../../backend/payroll/distribute.php`, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json'
            },
            body: JSON.stringify({
              payroll_id: id
            })
          }).then(r => r.json());

          if (res.success) {
            Swal.fire('Distributed!', res.message, 'success').then(() => location.reload());
          } else {
            Swal.fire('Error', res.message, 'error');
          }
        } catch (e) {
          Swal.fire('Error', 'Server communication failed.', 'error');
        }
      }
    }

    // view details popup
    function viewRun(id) {
      const win = window.open('', '_blank', 'width=1100,height=800');
      const html = `<!DOCTYPE html><html lang="en"><head><title>Payroll Run Master List #${id}</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><style>body { background: #f8f9fa; padding: 40px; font-family: 'Segoe UI', sans-serif; } .report-card { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); } .table thead { background: #212529; color: white; } .amount { font-family: monospace; font-weight: bold; } @media print { .no-print { display: none; } body { padding: 0; background: white; } .report-card { box-shadow: none; border: none; } }</style></head><body><div class="container-fluid report-card"><div id="root" class="text-center my-5"><div class="spinner-border text-warning" role="status"></div><p class="mt-2 text-muted">Fetching Master Payroll Data...</p></div></div><script>(async function(){try{const res = await fetch('../../backend/payroll/get_entries.php?payroll_id=${id}');const data = await res.json();const root = document.getElementById('root');if(!data.success){root.innerHTML = '<div class="alert alert-danger">Error: ' + data.message + '</div>';return;}let html = '<div class="d-flex justify-content-between align-items-center mb-4">';html += '<div><h3 class="fw-bold mb-0 text-dark">Payroll Master Report</h3><p class="text-muted mb-0">Batch Run #${id}</p></div>';<?php if ($canPrint): ?>html += '<button onclick="window.print()" class="btn btn-dark shadow-sm no-print"><i class="bi bi-printer"></i> Print Report</button>';<?php endif; ?>html += '</div>';html += '<div class="table-responsive">';html += '<table class="table table-hover table-bordered align-middle">';html += '<thead class="table-dark"><tr><th class="ps-3">ID Code</th><th>Employee Name</th><th>Regular Gross</th><th>Overtime</th><th>Advances</th><th>Net Pay</th><th class="text-center no-print">Payslip</th></tr></thead>';html += '<tbody>';data.data.forEach(row => {html += '<tr>';html += '<td class="ps-3"><span class="badge bg-light text-dark border">' + row.employee_code + '</span></td>';html += '<td class="fw-bold text-dark">' + row.last_name + ', ' + row.first_name + '</td>';let regGross = Number(row.gross_pay) - Number(row.overtime_pay);html += '<td class="amount text-secondary">₱' + regGross.toLocaleString(undefined, {minimumFractionDigits: 2}) + '</td>';html += '<td class="amount text-success">+₱' + Number(row.overtime_pay).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</td>';html += '<td class="amount text-danger">-₱' + Number(row.cash_advance).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</td>';html += '<td class="amount fw-bold text-primary" style="font-size: 1.1rem;">₱' + Number(row.net_pay).toLocaleString(undefined, {minimumFractionDigits: 2}) + '</td>';<?php if ($canPrint): ?>html += '<td class="text-center no-print"><a href="../../backend/payroll/payslip.php?payroll_id=${id}&employee_id=' + row.employee_id + '" target="_blank" class="btn btn-sm btn-outline-dark px-3 rounded-pill">View Slip</a></td>';<?php else: ?>html += '<td class="text-center no-print text-muted small">N/A</td>';<?php endif; ?>html += '</tr>';});html += '</tbody></table></div>';root.innerHTML = html;root.classList.remove('text-center', 'my-5');}catch(e){document.getElementById('root').innerHTML = '<div class="alert alert-danger">Connection Error. Failed to load report.</div>';}})();<\/script></body></html>`;
      win.document.write(html);
      win.document.close();
    }

    // auto trigger from timesheet page
    document.addEventListener("DOMContentLoaded", () => {
      const urlParams = new URLSearchParams(window.location.search);
      if (urlParams.get('auto_gen') === '1') {
        const start = urlParams.get('start');
        const end = urlParams.get('end');
        const empId = urlParams.get('emp_id');

        if (start && end && document.getElementById('generateBtn')) {
          document.getElementById('startDate').value = start;
          document.getElementById('endDate').value = end;
          if (empId) document.getElementById('employeeSelect').value = empId;

          setTimeout(() => {
            document.getElementById('generateBtn').click();
            window.history.replaceState({}, document.title, window.location.pathname);
          }, 400);
        }
      }
    });

    // analytics and summary logic

    let payrollChart = null;

    async function loadSummary(forceFilter = null) {
      const filter = forceFilter || document.getElementById('summaryFilter').value;

      try {
        const res = await fetch(`../../backend/payroll/get_summary.php?filter=${filter}`).then(r => r.json());
        if (!res.success) return;

        // render chart
        const ctx = document.getElementById('payrollChart').getContext('2d');
        if (payrollChart) payrollChart.destroy();

        payrollChart = new Chart(ctx, {
          type: 'bar',
          data: {
            labels: res.labels,
            datasets: [{
                label: 'Gross Pay Paid',
                data: res.gross,
                backgroundColor: '#0d6efd',
                borderRadius: 4
              },
              {
                label: 'Advances Recovered',
                data: res.deductions,
                backgroundColor: '#dc3545',
                borderRadius: 4
              },
              {
                label: 'Net Disbursed',
                data: res.net,
                backgroundColor: '#198754',
                borderRadius: 4
              }
            ]
          },
          options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
              tooltip: {
                mode: 'index',
                intersect: false
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                ticks: {
                  callback: function(value) {
                    return '₱' + value.toLocaleString();
                  }
                }
              },
              x: {
                grid: {
                  display: false
                }
              }
            }
          }
        });

        // populate summary table
        let tbody = '';
        res.raw_data.forEach(r => {
          tbody += `<tr>
                    <td class="ps-4 fw-bold text-dark">${r.label}</td>
                    <td><span class="badge bg-secondary">${r.run_count} processed</span></td>
                    <td class="text-primary fw-medium">₱${Number(r.total_gross).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    <td class="text-danger fw-medium">-₱${Number(r.total_deductions).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                    <td class="text-success fw-bold fs-6">₱${Number(r.total_net).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                </tr>`;
        });
        document.querySelector('#summaryTable tbody').innerHTML = tbody || '<tr><td colspan="5" class="text-center py-4 text-muted">No data available</td></tr>';

      } catch (e) {
        console.error('Error loading summary chart', e);
      }
    }

    // filter change
    document.getElementById('summaryFilter').addEventListener('change', (e) => loadSummary(e.target.value));

    // CSV export logic
    function exportSummaryCSV() {
      let table = document.getElementById("summaryTable");
      let rows = table.querySelectorAll("tr");
      let csvContent = "data:text/csv;charset=utf-8,";

      rows.forEach(row => {
        let cols = row.querySelectorAll("td, th");
        let rowData = [];
        cols.forEach(col => {
          let text = col.innerText.replace(/₱|,/g, "").trim();
          rowData.push('"' + text + '"');
        });
        csvContent += rowData.join(",") + "\n";
      });

      // download button logic
      let encodedUri = encodeURI(csvContent);
      let link = document.createElement("a");
      link.setAttribute("href", encodedUri);
      link.setAttribute("download", `Payroll_Summary_${document.getElementById('summaryFilter').value}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
    }

    // initial load
    loadRuns();
  </script>
</body>

</html>