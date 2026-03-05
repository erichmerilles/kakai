<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';
require_once __DIR__ . '/../utils/logger.php';

// check permissions
if (!hasPermission('emp_delete')) {
  header('Location: ../../frontend/employee/employee_module.php?error=unauthorized');
  exit;
}

// validate employee ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
  header('Location: ../../frontend/employee/employee_module.php');
  exit;
}

$empId = intval($_GET['id']);

try {
  // begin transaction
  $pdo->beginTransaction();

  // Fetch employee details for the activity log BEFORE deleting them
  $stmtFetch = $pdo->prepare("SELECT first_name, last_name, employee_code FROM employees WHERE employee_id = ?");
  $stmtFetch->execute([$empId]);
  $empDetails = $stmtFetch->fetch(PDO::FETCH_ASSOC);

  if (!$empDetails) {
    throw new Exception("Employee not found.");
  }

  $empName = trim($empDetails['first_name'] . ' ' . $empDetails['last_name']);
  $empCode = $empDetails['employee_code'];

  // delete from employee_permissions table
  $stmtPerms = $pdo->prepare("DELETE FROM employee_permissions WHERE employee_id = ?");
  $stmtPerms->execute([$empId]);

  // delete from users table (if exists)
  $stmtUser = $pdo->prepare("DELETE FROM users WHERE employee_id = ?");
  $stmtUser->execute([$empId]);

  // delete from employees table
  $stmtEmp = $pdo->prepare("DELETE FROM employees WHERE employee_id = ?");
  $stmtEmp->execute([$empId]);

  // commit transaction
  $pdo->commit();

  // log activity
  logActivity($pdo, $_SESSION['user_id'], 'Delete', 'Employee', "Deleted employee: $empName ($empCode)");

  header('Location: ../../frontend/employee/employee_module.php?msg=deleted');
} catch (Exception $e) {
  // rollback transaction on error
  $pdo->rollBack();
  header('Location: ../../frontend/employee/employee_module.php?error=delete_failed');
}
exit;
