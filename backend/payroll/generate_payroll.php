<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
  echo json_encode(['success'=>false,'message'=>'Not authenticated']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$start = $input['start_date'] ?? null;
$end   = $input['end_date'] ?? null;

if (!$start || !$end) {
  echo json_encode(['success'=>false,'message'=>'Provide start_date and end_date (YYYY-MM-DD)']);
  exit;
}

// normalize dates
$start_date = date('Y-m-d', strtotime($start));
$end_date   = date('Y-m-d', strtotime($end));
$created_by = $_SESSION['user_id'];

// payroll rules constants
define('WKDAY_MAX_HOURS', 11);    // Mon-Sat
define('WKDAY_FULL_PAY', 700.00);
define('SUN_MAX_HOURS', 10);      // Sunday
define('SUN_FULL_PAY', 800.00);

$conn->begin_transaction();

try {
  // create payroll run
  $stmt = $conn->prepare("INSERT INTO payroll_runs (start_date, end_date, created_by) VALUES (?, ?, ?)");
  $stmt->bind_param('ssi', $start_date, $end_date, $created_by);
  $stmt->execute();
  $payroll_id = $stmt->insert_id;
  $stmt->close();

  // fetch employees (all active employees; adapt query if you want subset)
  $empQ = $conn->query("SELECT employee_id FROM employees WHERE 1");
  $employees = $empQ ? $empQ->fetch_all(MYSQLI_ASSOC) : [];

  $total_gross = 0;
  $total_deductions = 0;
  $total_net = 0;

  foreach ($employees as $e) {
    $empId = (int)$e['employee_id'];
    // fetch attendance rows for employee within date range where time_in/time_out exist
    $attStmt = $conn->prepare("
      SELECT DATE(created_at) as day, time_in, time_out
      FROM attendance
      WHERE employee_id = ? 
        AND DATE(created_at) BETWEEN ? AND ?
        AND time_in IS NOT NULL
        AND time_out IS NOT NULL
    ");
    $attStmt->bind_param('iss', $empId, $start_date, $end_date);
    $attStmt->execute();
    $res = $attStmt->get_result();
    $attRows = $res->fetch_all(MYSQLI_ASSOC);
    $attStmt->close();

    $gross_pay = 0.0;
    $details = [];

    // group by day (attendance table likely stores one row per record; if multiple per day adjust)
    foreach ($attRows as $row) {
      $day = $row['day'];
      // compute hours between time_in/time_out
      $timeIn = strtotime($row['time_in']);
      $timeOut = strtotime($row['time_out']);
      if (!$timeIn || !$timeOut || $timeOut <= $timeIn) {
        continue; // ignore invalid
      }

      $hours = ($timeOut - $timeIn) / 3600.0;
      // determine weekday: 0=Sun..6=Sat via PHP's date('w')
      $w = date('w', strtotime($day));

      if ($w == 0) {
        // Sunday
        $max = SUN_MAX_HOURS;
        $fullPay = SUN_FULL_PAY;
      } else {
        // Mon-Sat
        $max = WKDAY_MAX_HOURS;
        $fullPay = WKDAY_FULL_PAY;
      }

      // cap hours at max
      if ($hours >= $max) {
        $dayPay = $fullPay;
        $usedHours = $max;
      } else {
        // fractional pay
        $dayPay = ($hours / $max) * $fullPay;
        $usedHours = $hours;
      }

      // round to 2 decimals
      $dayPay = round($dayPay, 2);
      $gross_pay += $dayPay;

      $details[] = [
        'date' => $day,
        'weekday' => $w,
        'hours' => round($usedHours, 2),
        'day_pay' => $dayPay
      ];
    }

    // get cash advances (approved and unpaid)
    $caStmt = $conn->prepare("SELECT cash_id, amount FROM cash_advances WHERE employee_id = ? AND status = 'Approved' AND paid = 0");
    $caStmt->bind_param('i', $empId);
    $caStmt->execute();
    $caRes = $caStmt->get_result();
    $cashRows = $caRes->fetch_all(MYSQLI_ASSOC);
    $caStmt->close();

    $cash_total = 0.0;
    $cash_ids = [];
    foreach ($cashRows as $c) {
      $cash_total += (float)$c['amount'];
      $cash_ids[] = (int)$c['cash_id'];
    }

    $net = round($gross_pay - $cash_total, 2);
    if ($net < 0) $net = 0.00;

    // insert payroll entry
    $ins = $conn->prepare("
      INSERT INTO payroll_entries (payroll_id, employee_id, gross_pay, cash_advance, net_pay, details)
      VALUES (?, ?, ?, ?, ?, ?)
    ");
    $details_json = json_encode($details);
    $ins->bind_param('iiddds', $payroll_id, $empId, $gross_pay, $cash_total, $net, $details_json);
    $ins->execute();
    $ins->close();

    // mark cash advances as paid and link to payroll run
    if (count($cash_ids) > 0) {
      $ids = implode(',', array_map('intval', $cash_ids));
      $conn->query("UPDATE cash_advances SET paid=1, payroll_id={$payroll_id} WHERE cash_id IN ({$ids})");
    }

    $total_gross += $gross_pay;
    $total_deductions += $cash_total;
    $total_net += $net;
  }

  // update payroll run totals
  $u = $conn->prepare("UPDATE payroll_runs SET total_gross = ?, total_deductions = ?, total_net = ? WHERE payroll_id = ?");
  $u->bind_param('dddi', $total_gross, $total_deductions, $total_net, $payroll_id);
  $u->execute();
  $u->close();

  $conn->commit();

  echo json_encode(['success'=>true, 'message'=>'Payroll generated', 'payroll_id'=>$payroll_id]);

} catch (Exception $ex) {
  $conn->rollback();
  echo json_encode(['success'=>false,'message'=>'Error: '.$ex->getMessage()]);
}
?>
