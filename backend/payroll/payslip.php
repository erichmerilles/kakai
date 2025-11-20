<?php
require_once __DIR__ . '/../../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { die('Not authenticated'); }

$payroll_id = intval($_GET['payroll_id'] ?? 0);
$employee_id = intval($_GET['employee_id'] ?? 0);
if (!$payroll_id || !$employee_id) die('payroll_id & employee_id required');

$stmt = $conn->prepare("
  SELECT p.*, e.first_name, e.last_name 
  FROM payroll_entries p
  JOIN employees e ON p.employee_id = e.employee_id
  WHERE p.payroll_id = ? AND p.employee_id = ?
");
$stmt->bind_param('ii', $payroll_id, $employee_id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

$runQ = $conn->prepare("SELECT start_date, end_date FROM payroll_runs WHERE payroll_id = ?");
$runQ->bind_param('i', $payroll_id);
$runQ->execute();
$run = $runQ->get_result()->fetch_assoc();
$runQ->close();

$details = json_decode($row['details'] ?? '[]', true);
?>
<!doctype html>
<html>
<head>
  <?php include __DIR__ . '/../../frontend/includes/links.php'; ?>
  <style>
    .payslip { max-width:800px; margin:20px auto; background:#fff; padding:20px; border-radius:8px; }
    .payslip h3 { margin-top:0; color:#4b2c06; }
    .muted { color:#666; }
    .table td, .table th { padding:6px; }
  </style>
</head>
<body>
<div class="payslip">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <div>
      <img src="../../frontend/assets/images/logo.jpg" style="height:60px">
    </div>
    <div class="text-end">
      <h4>KakaiOne</h4>
      <div class="muted">Payslip</div>
    </div>
  </div>

  <h5>Employee: <?= htmlspecialchars($row['first_name'].' '.$row['last_name']); ?></h5>
  <p>Payroll Period: <?= htmlspecialchars($run['start_date']) ?> — <?= htmlspecialchars($run['end_date']) ?></p>

  <table class="table table-bordered mb-3">
    <tr><th>Gross Pay</th><td>₱<?= number_format($row['gross_pay'],2) ?></td></tr>
    <tr><th>Cash Advance</th><td>₱<?= number_format($row['cash_advance'],2) ?></td></tr>
    <tr><th>Net Pay</th><td><strong>₱<?= number_format($row['net_pay'],2) ?></strong></td></tr>
  </table>

  <h6>Daily breakdown</h6>
  <table class="table table-sm table-striped">
    <thead><tr><th>Date</th><th>Hours</th><th>Day Pay</th></tr></thead>
    <tbody>
    <?php foreach ($details as $d): ?>
      <tr>
        <td><?= htmlspecialchars($d['date']) ?></td>
        <td><?= htmlspecialchars($d['hours']) ?></td>
        <td>₱<?= number_format($d['day_pay'],2) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>

  <div class="text-end mt-3">
    <button class="btn btn-pri" onclick="window.print()">Print Payslip</button>
    <a href="../../frontend/payroll/payroll_module.php" class="btn btn-outline-secondary">Back</a>
  </div>
</div>
</body>
</html>
