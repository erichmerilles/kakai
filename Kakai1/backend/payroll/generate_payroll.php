<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';
require_once __DIR__ . '/../utils/logger.php';

// check permissions
if (!hasPermission('payroll_generate')) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
  exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$start_date = isset($input['start_date']) ? date('Y-m-d', strtotime($input['start_date'])) : null;
$end_date   = isset($input['end_date']) ? date('Y-m-d', strtotime($input['end_date'])) : null;
$employee_id = $input['employee_id'] ?? 'all';
$created_by = $_SESSION['user_id'] ?? null;

if (!$start_date || !$end_date) {
  echo json_encode(['success' => false, 'message' => 'Valid date range required.']);
  exit;
}

try {
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

  // auto migartion for cash_advance balance column if it doesn't exist
  try {
    $pdo->query("SELECT balance FROM cash_advance LIMIT 1");
  } catch (PDOException $e) {
    $pdo->exec("ALTER TABLE cash_advance ADD COLUMN balance DECIMAL(10,2) DEFAULT NULL AFTER amount");
    $pdo->exec("UPDATE cash_advance SET balance = amount");
  }

  // start payroll transaction
  $pdo->beginTransaction();

  // fetch payroll settings
  $settingsQuery = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
  $settings = $settingsQuery->fetchAll(PDO::FETCH_KEY_PAIR);

  $WKDAY_RATE = floatval($settings['weekday_rate'] ?? 63.63);
  $SUN_RATE   = floatval($settings['sunday_rate'] ?? 80.00);
  $WKDAY_FULL = floatval($settings['weekday_full'] ?? 700.00);
  $SUN_FULL   = floatval($settings['sunday_full'] ?? 800.00);
  $WKDAY_LOGOUT_CAP = $settings['weekday_logout'] ?? '17:00:00';
  $SUN_LOGOUT_CAP   = $settings['sunday_logout'] ?? '16:00:00';

  $WKDAY_MAX_HOURS = 10;
  $SUN_MAX_HOURS = 9;
  $OT_RATE_MULTIPLIER = 1.25;

  // create payroll run
  $stmt = $pdo->prepare("INSERT INTO payroll_runs (start_date, end_date, created_by, created_at) VALUES (?, ?, ?, NOW())");
  $stmt->execute([$start_date, $end_date, $created_by]);
  $payroll_id = $pdo->lastInsertId();

  // select employees to process
  if ($employee_id === 'all') {
    $employees = $pdo->query("SELECT employee_id, daily_rate FROM employees WHERE status = 'Active' AND role = 'Employee'")->fetchAll(PDO::FETCH_ASSOC);
  } else {
    $empStmt = $pdo->prepare("SELECT employee_id, daily_rate FROM employees WHERE employee_id = ? AND status = 'Active' AND role = 'Employee'");
    $empStmt->execute([$employee_id]);
    $employees = $empStmt->fetchAll(PDO::FETCH_ASSOC);
  }

  if (empty($employees)) {
    throw new Exception("No eligible employees found for this selection.");
  }

  $total_run_gross = 0;
  $total_run_deductions = 0;
  $total_run_net = 0;
  $entries_created = 0;

  foreach ($employees as $e) {
    $empId = (int)$e['employee_id'];

    // fetch unpaid attendance
    $attStmt = $pdo->prepare("
            SELECT attendance_id, DATE(time_in) as day, time_in, time_out, approved_overtime
            FROM attendance
            WHERE employee_id = ? AND DATE(time_in) BETWEEN ? AND ?
              AND time_in IS NOT NULL AND time_out IS NOT NULL
              AND IFNULL(is_paid, 0) = 0
        ");
    $attStmt->execute([$empId, $start_date, $end_date]);
    $attRows = $attStmt->fetchAll(PDO::FETCH_ASSOC);

    $gross_pay = 0.0;
    $total_ot_pay = 0.0;
    $details = [];
    $att_ids = [];

    foreach ($attRows as $row) {
      $timeInTs = strtotime($row['time_in']);
      $timeOutTs = strtotime($row['time_out']);
      if ($timeOutTs <= $timeInTs) continue;

      $att_ids[] = $row['attendance_id'];

      $dayDate = $row['day'];
      $is_sunday = (date('w', $timeInTs) == 0);
      $max_hours = $is_sunday ? $SUN_MAX_HOURS : $WKDAY_MAX_HOURS;
      $full_pay = $is_sunday ? $SUN_FULL : $WKDAY_FULL;
      $hourly_rate = $is_sunday ? $SUN_RATE : $WKDAY_RATE;
      $logout_cap = $is_sunday ? $SUN_LOGOUT_CAP : $WKDAY_LOGOUT_CAP;

      $capTs = strtotime($dayDate . ' ' . $logout_cap);
      if ($timeOutTs > $capTs) $timeOutTs = $capTs;

      $hours = max(0, ($timeOutTs - $timeInTs) / 3600.0);
      $dayPay = ($hours >= $max_hours) ? $full_pay : min($full_pay, $hours * $hourly_rate);
      $dayOtPay = (float)$row['approved_overtime'] * ($hourly_rate * $OT_RATE_MULTIPLIER);

      $dayTotal = round($dayPay + $dayOtPay, 2);
      $gross_pay += $dayTotal;
      $total_ot_pay += $dayOtPay;

      $details[] = ['date' => $dayDate, 'reg_hours' => round($hours, 2), 'reg_pay' => round($dayPay, 2), 'ot_pay' => round($dayOtPay, 2), 'total' => $dayTotal];
    }

    // process if there's any gross pay
    if ($gross_pay > 0) {
      $entries_created++;

      // deduct cash advances using the balance column
      $caStmt = $pdo->prepare("SELECT ca_id, COALESCE(balance, amount) as current_balance FROM cash_advance WHERE employee_id = ? AND status = 'Approved' ORDER BY created_at ASC");
      $caStmt->execute([$empId]);
      $cashRows = $caStmt->fetchAll(PDO::FETCH_ASSOC);

      $available_pay = $gross_pay;
      $total_deducted = 0.0;
      $ca_updates = []; // To store balance updates

      foreach ($cashRows as $c) {
        if ($available_pay <= 0) break;

        $ca_id = $c['ca_id'];
        $current_balance = (float)$c['current_balance'];

        if ($current_balance <= 0) continue;

        if ($available_pay >= $current_balance) {
          // Deduct full remaining balance
          $total_deducted += $current_balance;
          $available_pay -= $current_balance;
          $ca_updates[] = ['ca_id' => $ca_id, 'new_balance' => 0, 'status' => 'Paid'];
        } else {
          // Partial deduction (salary is less than the advance)
          $total_deducted += $available_pay;
          $new_balance = $current_balance - $available_pay;
          $ca_updates[] = ['ca_id' => $ca_id, 'new_balance' => $new_balance, 'status' => 'Approved'];
          $available_pay = 0;
        }
      }

      $net = round($gross_pay - $total_deducted, 2);

      // insert payroll entry
      $ins = $pdo->prepare("INSERT INTO payroll_entries (payroll_id, employee_id, gross_pay, overtime_pay, cash_advance, net_pay, details) VALUES (?, ?, ?, ?, ?, ?, ?)");
      $ins->execute([$payroll_id, $empId, $gross_pay, $total_ot_pay, $total_deducted, $net, json_encode($details)]);

      // mark attendance as paid
      if (!empty($att_ids)) {
        $placeholders = implode(',', array_fill(0, count($att_ids), '?'));
        $updAtt = $pdo->prepare("UPDATE attendance SET is_paid = 1, payroll_id = ? WHERE attendance_id IN ($placeholders)");
        $updAtt->execute(array_merge([$payroll_id], $att_ids));
      }

      // update Cash Advances balance and status
      if (!empty($ca_updates)) {
        $updCA = $pdo->prepare("UPDATE cash_advance SET balance = ?, status = ? WHERE ca_id = ?");
        foreach ($ca_updates as $upd) {
          $updCA->execute([$upd['new_balance'], $upd['status'], $upd['ca_id']]);
        }
      }

      $total_run_gross += $gross_pay;
      $total_run_deductions += $total_deducted;
      $total_run_net += $net;
    }
  }

  if ($entries_created === 0) {
    throw new Exception("No unpaid attendance records found for the selected employee(s) in this date range.");
  }

  // finalize Run
  $pdo->prepare("UPDATE payroll_runs SET total_gross = ?, total_deductions = ?, total_net = ? WHERE payroll_id = ?")
    ->execute([$total_run_gross, $total_run_deductions, $total_run_net, $payroll_id]);

  $pdo->commit();

  // log activity
  $logMsg = "Generated payroll run #$payroll_id for period $start_date to $end_date ($entries_created employee(s) processed)";
  logActivity($pdo, $_SESSION['user_id'], 'Generate', 'Payroll', $logMsg);

  echo json_encode(['success' => true, 'message' => 'Payroll generated successfully.', 'payroll_id' => $payroll_id]);
} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
