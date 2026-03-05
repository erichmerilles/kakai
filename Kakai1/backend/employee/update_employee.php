<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';
require_once __DIR__ . '/../utils/logger.php';

header('Content-Type: application/json');

// check permissions
if (!hasPermission('emp_edit')) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized: You do not have permission to edit employees.']);
  exit;
}

// get employee ID
$empId = $_POST['employee_id'] ?? null;
if (!$empId) {
  echo json_encode(['success' => false, 'message' => 'Employee ID is missing.']);
  exit;
}

// extract and sanitize input
$first_name = trim($_POST['first_name'] ?? '');
$last_name  = trim($_POST['last_name'] ?? '');
$email      = trim($_POST['email'] ?? '');
$contact    = trim($_POST['contact_number'] ?? '');
$position   = trim($_POST['position'] ?? '');
$daily_rate = floatval($_POST['daily_rate'] ?? 0);
$status     = trim($_POST['status'] ?? 'Active');
$date_hired = $_POST['date_hired'] ?? null;

// hanlde optional password update
$newPassword = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

try {
  $pdo->beginTransaction();

  // update employees table
  $stmtEmp = $pdo->prepare("
        UPDATE employees
        SET first_name = ?, last_name = ?, email = ?, contact_number = ?, position = ?, daily_rate = ?, status = ?, date_hired = ?
        WHERE employee_id = ?
    ");
  $stmtEmp->execute([$first_name, $last_name, $email, $contact, $position, $daily_rate, $status, $date_hired, $empId]);

  // update users table
  if ($newPassword) {
    $stmtUser = $pdo->prepare("UPDATE users SET username = ?, status = ?, password = ? WHERE employee_id = ?");
    $stmtUser->execute([$email, $status, $newPassword, $empId]);
  } else {
    $stmtUser = $pdo->prepare("UPDATE users SET username = ?, status = ? WHERE employee_id = ?");
    $stmtUser->execute([$email, $status, $empId]);
  }

  $pdo->commit();

  // log activity
  logActivity($pdo, $_SESSION['user_id'], 'Update', 'Employee', "Updated details for Employee ID: $empId ($first_name $last_name)");

  echo json_encode(['success' => true, 'message' => 'Employee and login ID updated successfully!']);
} catch (PDOException $e) {
  if ($pdo->inTransaction()) {
    $pdo->rollBack();
  }

  // check for duplicate entry
  if ($e->getCode() == 23000 || $e->errorInfo[1] == 1062) {
    echo json_encode(['success' => false, 'message' => 'Error: This email is already used by another account.']);
  } else {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
  }
}
