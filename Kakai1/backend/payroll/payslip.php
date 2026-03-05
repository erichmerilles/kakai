<?php
require_once __DIR__ . '/../../config/db.php';
session_start();
require_once __DIR__ . '/../../frontend/includes/auth_check.php';

$pid = intval($_GET['payroll_id'] ?? 0);
$eid = intval($_GET['employee_id'] ?? 0);
$my_id = $_SESSION['employee_id'] ?? 0;
$is_admin = hasPermission('payslip_print');

// check permissions
if (!$is_admin && $eid !== $my_id) {
    die('Unauthorized access.');
}

// fetch payroll data and employee details
$stmt = $pdo->prepare("
    SELECT pe.*, e.first_name, e.last_name, e.position, e.employee_code, pr.start_date, pr.end_date 
    FROM payroll_entries pe 
    JOIN employees e ON pe.employee_id = e.employee_id 
    JOIN payroll_runs pr ON pe.payroll_id = pr.payroll_id 
    WHERE pe.payroll_id = ? AND pe.employee_id = ?
");
$stmt->execute([$pid, $eid]);
$data = $stmt->fetch();
if (!$data) die('Payslip not found.');

// fetch attendance details for the period
$details = json_decode($data['details'] ?? '[]', true);
$reg_pay = $data['gross_pay'] - $data['overtime_pay'];

// calculate summary pay
$days_present = count($details);
$total_reg_hours = 0;
$total_ot_hours = 0;

foreach ($details as $day) {
  $total_reg_hours += $day['reg_hours'] ?? 0;
  $total_ot_hours += isset($day['ot_pay']) && $day['ot_pay'] > 0 ? ($day['ot_hours'] ?? 0) : 0; // rough estimation if ot_hours isn't directly saved, but we'll use count
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Payslip - <?= $data['employee_code'] ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body {
      background: #e9ecef;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .payslip-card {
      max-width: 800px;
      margin: 40px auto;
      background: #fff;
      padding: 40px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      border-top: 8px solid #212529;
    }

    .amount {
      font-family: 'Courier New', Courier, monospace;
      font-weight: bold;
      font-size: 1.1rem;
    }

    .metric-box {
      border: 1px solid #dee2e6;
      border-radius: 6px;
      padding: 10px;
      text-align: center;
      background: #f8f9fa;
    }

    @media print {
      body {
        background: #fff;
      }

      .payslip-card {
        border: none;
        box-shadow: none;
        margin: 0;
        padding: 20px;
      }

      .no-print {
        display: none;
      }
    }
  </style>
</head>

<body>
  <div class="text-center no-print mt-3 mb-3">
    <button onclick="window.print()" class="btn btn-dark fw-bold shadow-sm"><i class="bi bi-printer me-2"></i> Print Official Payslip</button>
  </div>

  <div class="payslip-card">
    <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-4">
      <div>
        <h3 class="fw-bold mb-0 text-dark" style="letter-spacing: 1px;">KAKAI ONE</h3>
        <span class="text-muted small text-uppercase fw-bold">Official Salary Statement</span>
      </div>
      <div class="text-end">
        <div class="small fw-bold text-muted text-uppercase mb-1">Payroll Batch</div>
        <h4 class="text-dark mb-0 fw-bold">#<?= str_pad($pid, 5, '0', STR_PAD_LEFT) ?></h4>
      </div>
    </div>

    <div class="row mb-4">
      <div class="col-7">
        <p class="text-muted small mb-1 text-uppercase fw-bold">Employee</p>
        <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($data['last_name'] . ', ' . $data['first_name']) ?></h5>
        <div class="text-muted"><?= htmlspecialchars($data['position']) ?> | ID: <?= $data['employee_code'] ?></div>
      </div>
      <div class="col-5 text-end">
        <p class="text-muted small mb-1 text-uppercase fw-bold">Pay Period</p>
        <div class="fw-bold text-dark"><?= date('F d, Y', strtotime($data['start_date'])) ?></div>
        <div class="fw-bold text-dark">to <?= date('F d, Y', strtotime($data['end_date'])) ?></div>
      </div>
    </div>

    <div class="row mb-5 g-2">
      <div class="col-4">
        <div class="metric-box">
          <div class="text-muted small text-uppercase fw-bold">Days Present</div>
          <div class="fs-4 fw-bold text-dark"><?= $days_present ?></div>
        </div>
      </div>
      <div class="col-4">
        <div class="metric-box">
          <div class="text-muted small text-uppercase fw-bold">Total Reg. Hours</div>
          <div class="fs-4 fw-bold text-dark"><?= number_format($total_reg_hours, 1) ?></div>
        </div>
      </div>
      <div class="col-4">
        <div class="metric-box">
          <div class="text-muted small text-uppercase fw-bold">Policy Applied</div>
          <div class="fs-6 fw-bold text-danger mt-2">No Work, No Pay</div>
        </div>
      </div>
    </div>

    <div class="row mb-4">
      <div class="col-6 pe-4 border-end">
        <h6 class="fw-bold text-success mb-3 border-bottom pb-2 text-uppercase">Earnings</h6>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Basic Pay (Prorated)</span>
          <span class="amount text-dark">₱<?= number_format($reg_pay, 2) ?></span>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Approved Overtime</span>
          <span class="amount text-dark">₱<?= number_format($data['overtime_pay'], 2) ?></span>
        </div>
      </div>
      <div class="col-6 ps-4">
        <h6 class="fw-bold text-danger mb-3 border-bottom pb-2 text-uppercase">Deductions</h6>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Cash Advances</span>
          <span class="amount text-danger">- ₱<?= number_format($data['cash_advance'], 2) ?></span>
        </div>
      </div>
    </div>

    <div class="bg-dark text-white p-4 rounded d-flex justify-content-between align-items-center mt-5 shadow-sm">
      <span class="text-uppercase fw-bold" style="letter-spacing: 1px;">Net Take Home Pay</span>
      <h2 class="fw-bold mb-0 amount text-warning" style="font-size: 2.2rem;">₱<?= number_format($data['net_pay'], 2) ?></h2>
    </div>

    <div class="text-center mt-4 text-muted small fst-italic">
      I acknowledge receipt of the above amount as full compensation for the period stated.<br><br>
      ______________________________________<br>
      Employee Signature
    </div>
  </div>
</body>

</html>