<?php
require_once __DIR__ . '/../../config/db.php';

if (!isset($_GET['id'])) {
  header('Location: employee_module.php');
  exit;
}

$empId = intval($_GET['id']);

$conn->begin_transaction();
try {
  // Delete from users table using a prepared statement
  $stmt_user = $conn->prepare("DELETE FROM users WHERE employee_id=?");
  $stmt_user->bind_param('i', $empId);
  $stmt_user->execute();
  $stmt_user->close();

  // Delete from employees table using a prepared statement
  $stmt_emp = $conn->prepare("DELETE FROM employees WHERE employee_id=?");
  $stmt_emp->bind_param('i', $empId);
  $stmt_emp->execute();
  $stmt_emp->close();
  
  $conn->commit();
  header('Location: employee_module.php?msg=deleted');
} catch (Exception $e) {
  $conn->rollback();
  // Log error instead of exposing it via header
  error_log("Employee deletion failed for ID {$empId}: " . $e->getMessage());
  header('Location: employee_module.php?error=delete_failed'); 
}
exit;
?>