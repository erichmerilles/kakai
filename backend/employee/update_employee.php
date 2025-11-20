<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../auth/login.php');
  exit;
}

if (!isset($_GET['id'])) {
  header('Location: employee_module.php');
  exit;
}

$empId = intval($_GET['id']);
$successMsg = '';
$errorMsg = '';

// FETCH EMPLOYEE
$stmt = $conn->prepare("
  SELECT e.employee_id, e.full_name, e.position, e.status AS emp_status,
         u.username, u.role, u.status AS user_status
  FROM employees e
  LEFT JOIN users u ON e.employee_id = u.employee_id
  WHERE e.employee_id = ?
");
$stmt->bind_param('i', $empId);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $fullname = trim($_POST['fullname']);
  $position = trim($_POST['position']);
  $role = trim($_POST['role']);
  $status = trim($_POST['status']);
  $newPassword = $_POST['password'] ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

  $conn->begin_transaction();
  try {
    // UPDATE employees
    $stmtEmp = $conn->prepare("
      UPDATE employees
      SET full_name=?, position=?, status=?
      WHERE employee_id=?
    ");
    $stmtEmp->bind_param('sssi', $fullname, $position, $status, $empId);
    $updateEmpResult = $stmtEmp->execute();
    $stmtEmp->close();

    // UPDATE users
    if ($newPassword) {
      $stmtUser = $conn->prepare("
        UPDATE users
        SET role=?, status=?, password=?
        WHERE employee_id=?
      ");
      $stmtUser->bind_param('sssi', $role, $status, $newPassword, $empId);
    } else {
      $stmtUser = $conn->prepare("
        UPDATE users
        SET role=?, status=?
        WHERE employee_id=?
      ");
      $stmtUser->bind_param('ssi', $role, $status, $empId);
    }
    $updateUserResult = $stmtUser->execute();
    $stmtUser->close();

    if ($updateEmpResult && $updateUserResult) {
      $conn->commit();
      $successMsg = "Employee updated successfully.";
      // Refresh $employee data
      $employee = [
          'full_name' => $fullname,
          'position' => $position,
          'role' => $role,
          'user_status' => $status,
      ];
    } else {
      throw new Exception("Database error during update.");
    }
  } catch (Exception $e) {
    $conn->rollback();
    $errorMsg = "Failed to update: " . $e->getMessage();
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit Employee | KakaiOne</title>
  <?php include '../includes/links.php'; ?>
</head>
<body class="bg-light">

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold"><i class="bi bi-pencil-square text-warning me-2"></i>Edit Employee</h3>
    <a href="employee_module.php" class="btn btn-secondary">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <?php if ($successMsg): ?>
        <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
      <?php elseif ($errorMsg): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
      <?php endif; ?>

      <?php if (!$employee): ?>
        <div class="alert alert-danger">Employee data not found.</div>
      <?php else: ?>
      <form method="POST">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Full Name</label>
            <input type="text" name="fullname" class="form-control" value="<?= htmlspecialchars($employee['full_name'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Position</label>
            <input type="text" name="position" class="form-control" value="<?= htmlspecialchars($employee['position'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Role</label>
            <select name="role" class="form-select" required>
              <option <?= ($employee['role'] ?? '')=='employee'?'selected':'' ?>>employee</option>
              <option <?= ($employee['role'] ?? '')=='manager'?'selected':'' ?>>manager</option>
              <option <?= ($employee['role'] ?? '')=='supervisor'?'selected':'' ?>>supervisor</option>
              <option <?= ($employee['role'] ?? '')=='admin'?'selected':'' ?>>admin</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" class="form-select">
              <option value="active" <?= ($employee['user_status'] ?? '')=='active'?'selected':'' ?>>Active</option>
              <option value="inactive" <?= ($employee['user_status'] ?? '')=='inactive'?'selected':'' ?>>Inactive</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Reset Password (Optional)</label>
            <input type="password" name="password" class="form-control" placeholder="Enter new password">
          </div>
          <div class="col-md-12">
            <button type="submit" class="btn btn-warning fw-semibold">
              <i class="bi bi-save"></i> Update Employee
            </button>
          </div>
        </div>
      </form>
      <?php endif; ?>
    </div>
  </div>
</div>

</body>
</html>