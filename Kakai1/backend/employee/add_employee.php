<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';
require_once __DIR__ . '/../utils/logger.php';

header('Content-Type: application/json');

// check permissions
if (!hasPermission('emp_add')) {
  echo json_encode(['success' => false, 'message' => 'Unauthorized']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $first_name = trim($_POST['first_name'] ?? '');
  $last_name  = trim($_POST['last_name'] ?? '');
  $email      = trim($_POST['email'] ?? '');
  $contact    = trim($_POST['contact_number'] ?? '');
  $position   = trim($_POST['position'] ?? '');
  $status     = trim($_POST['status'] ?? 'Active');
  $daily_rate = floatval($_POST['daily_rate'] ?? 0);
  $date_hired = $_POST['date_hired'] ?? date('Y-m-d');
  $password   = $_POST['password'] ?? '';

  if (empty($first_name) || empty($last_name) || empty($email) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'First Name, Last Name, Email, and Password are required.']);
    exit;
  }

  $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

  try {
    $pdo->beginTransaction();

    // insert into employees table
    $stmtEmp = $pdo->prepare("
            INSERT INTO employees 
            (first_name, last_name, email, contact_number, position, daily_rate, status, date_hired, role, password) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

    $stmtEmp->execute([
      $first_name,
      $last_name,
      $email,
      $contact,
      $position,
      $daily_rate,
      $status,
      $date_hired,
      'Employee',
      $hashedPassword
    ]);

    // get the generated ID
    $employee_id = $pdo->lastInsertId();

    // generate and update employee code
    $employee_code = "EMP-" . str_pad($employee_id, 4, "0", STR_PAD_LEFT);

    $stmtUpdateCode = $pdo->prepare("UPDATE employees SET employee_code = ? WHERE employee_id = ?");
    $stmtUpdateCode->execute([$employee_code, $employee_id]);

    // insert into users table
    $stmtUser = $pdo->prepare("
            INSERT INTO users 
            (employee_id, username, password, role, status, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

    $stmtUser->execute([
      $employee_id,
      $email,
      $hashedPassword,
      'Employee',
      $status
    ]);

    // initialize permissions for the new employee
    $stmtPerms = $pdo->prepare("INSERT INTO employee_permissions (employee_id) VALUES (?)");
    $stmtPerms->execute([$employee_id]);

    $pdo->commit();

    // log activity
    logActivity($pdo, $_SESSION['user_id'], 'Create', 'Employee', "Added a new employee: $first_name $last_name ($employee_code)");

    echo json_encode(['success' => true, 'message' => "Employee created successfully! Assigned ID: $employee_code. Use email to login."]);
  } catch (PDOException $e) {
    $pdo->rollBack();
    if ($e->getCode() == 23000) {
      echo json_encode(['success' => false, 'message' => 'Error: Email already exists.']);
    } else {
      echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
  }
}
