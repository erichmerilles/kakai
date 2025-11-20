<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Admin checker
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../auth/login.php');
  exit;
}

// Get employee ID
if (!isset($_GET['id'])) {
  header('Location: employee_module.php');
  exit;
}

$employee_id = intval($_GET['id']);

// Fetch employee
$stmt_emp = $conn->prepare("SELECT username FROM users WHERE employee_id = ?");
$stmt_emp->bind_param('i', $employee_id);
$stmt_emp->execute();
$result_emp = $stmt_emp->get_result();
$emp = $result_emp->fetch_assoc();
$stmt_emp->close();

if (!$emp) {
  die("Employee not found.");
}

// Define modules
$modules = [
  'Employee Management',
  'Attendance',
  'Leave / Schedule Requests',
  'Payroll',
  'Reports'
];

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $admin_id = $_SESSION['user_id'];
  
  $conn->begin_transaction();
  try {
    // Delete existing access
    $stmt_del = $conn->prepare("DELETE FROM employee_access WHERE employee_id = ?");
    $stmt_del->bind_param('i', $employee_id);
    $stmt_del->execute();
    $stmt_del->close();

    $stmt_ins = $conn->prepare("
      INSERT INTO employee_access (employee_id, module_name, can_view, can_edit, can_delete, granted_by, granted_at)
      VALUES (?, ?, ?, ?, ?, ?, NOW())
    ");
    
    foreach ($modules as $mod) {
      $can_view = isset($_POST['view'][$mod]) ? 1 : 0;
      $can_edit = isset($_POST['edit'][$mod]) ? 1 : 0;
      $can_delete = isset($_POST['delete'][$mod]) ? 1 : 0;

      if ($can_view || $can_edit || $can_delete) {
        $stmt_ins->bind_param("isiiii", $employee_id, $mod, $can_view, $can_edit, $can_delete, $admin_id);
        $stmt_ins->execute();
      }
    }
    $stmt_ins->close();
    
    $conn->commit();
    $_SESSION['message'] = "Access rights updated successfully!";
    header("Location: employee_module.php");
    exit;
  } catch (Exception $e) {
    $conn->rollback();
    error_log("Access control update failed: " . $e->getMessage());
    $_SESSION['message'] = "Error updating access rights.";
    header("Location: employee_module.php?error=db");
    exit;
  }
}

// Fetch current access settings
$currentAccess = [];
$stmt_access = $conn->prepare("SELECT * FROM employee_access WHERE employee_id = ?");
$stmt_access->bind_param('i', $employee_id);
$stmt_access->execute();
$result = $stmt_access->get_result();

while ($row = $result->fetch_assoc()) {
  $currentAccess[$row['module_name']] = [
    'view' => $row['can_view'],
    'edit' => $row['can_edit'],
    'delete' => $row['can_delete']
  ];
}
$stmt_access->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Access Control | KakaiOne</title>
  <?php include '../includes/links.php'; ?>
</head>
<body class="bg-light">

<div class="container py-5">
  <div class="card shadow-sm border-0">
    <div class="card-header bg-warning text-dark fw-bold">
      <i class="bi bi-shield-lock"></i> Access Control for <?= htmlspecialchars($emp['username']); ?>
    </div>

    <div class="card-body">
      <form method="POST">
        <table class="table table-bordered align-middle text-center">
          <thead class="table-light">
            <tr>
              <th class="text-start">Module</th>
              <th>View</th>
              <th>Edit</th>
              <th>Delete</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($modules as $mod): ?>
              <?php 
                $view = $currentAccess[$mod]['view'] ?? 0;
                $edit = $currentAccess[$mod]['edit'] ?? 0;
                $delete = $currentAccess[$mod]['delete'] ?? 0;
                $safe_mod = htmlspecialchars($mod);
              ?>
              <tr>
                <td class="text-start fw-semibold"><?= $safe_mod; ?></td>
                <td><input type="checkbox" name="view[<?= $safe_mod ?>]" <?= $view ? 'checked' : '' ?>></td>
                <td><input type="checkbox" name="edit[<?= $safe_mod ?>]" <?= $edit ? 'checked' : '' ?>></td>
                <td><input type="checkbox" name="delete[<?= $safe_mod ?>]" <?= $delete ? 'checked' : '' ?>></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <div class="text-end mt-3">
          <button type="submit" class="btn btn-success">
            <i class="bi bi-save"></i> Save Changes
          </button>
          <a href="employee_module.php" class="btn btn-secondary">Back</a>
        </div>
      </form>
    </div>
  </div>
</div>

</body>
</html>