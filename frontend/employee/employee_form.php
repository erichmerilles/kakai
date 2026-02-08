<?php
/*session_start();
require_once __DIR__ . '/../../config/db.php';

// role validation
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
  header('Location: ../auth/login.php');
  exit;
}

$successMsg = '';
$errorMsg = '';

$empId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $empId > 0;
$employee = [];

// fetch employee data
if ($isEdit) {
  $stmt = $conn->prepare("
    SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, e.email, e.position, e.role, e.status, e.date_hired
    FROM employees e
    WHERE e.employee_id = ?
  ");
  $stmt->bind_param("i", $empId);
  $stmt->execute();
  $result = $stmt->get_result();
  $employee = $result->fetch_assoc() ?: [];
  $stmt->close();

  if (empty($employee)) {
    $errorMsg = "Employee not found.";
    $isEdit = false;
  }
}

// generate employee code for new entries
if (!$isEdit) {
  $result = $conn->query("SELECT employee_code FROM employees ORDER BY employee_id DESC LIMIT 1");
  if ($result && $row = $result->fetch_assoc()) {
    preg_match('/(\d+)$/', $row['employee_code'], $matches);
    $nextNum = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
  } else {
    $nextNum = 1;
  }
  $employee['employee_code'] = 'EMP-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
}

// add/edit employee logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $employee_code = trim($_POST['employee_code'] ?? '');
  $first_name = trim($_POST['first_name'] ?? '');
  $last_name = trim($_POST['last_name'] ?? '');
  $email = trim($_POST['email'] ?? '');
  $position = trim($_POST['position'] ?? '');
  $role = trim($_POST['role'] ?? 'Employee');
  $status = trim($_POST['status'] ?? 'Active');
  $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

  $conn->begin_transaction();

  try {
    if ($isEdit) {
      // UPDATE EMPLOYEE
      $stmt = $conn->prepare("
        UPDATE employees
        SET employee_code=?, first_name=?, last_name=?, email=?, position=?, role=?, status=?
        WHERE employee_id=?
      ");
      $stmt->bind_param("sssssssi", $employee_code, $first_name, $last_name, $email, $position, $role, $status, $empId);
      $stmt->execute();

      // update user account
      if ($password) {
        $stmtUser = $conn->prepare("
          UPDATE users SET username=?, password=?, role=?, status=? WHERE employee_id=?
        ");
        $stmtUser->bind_param("ssssi", $email, $password, $role, $status, $empId);
      } else {
        $stmtUser = $conn->prepare("
          UPDATE users SET username=?, role=?, status=? WHERE employee_id=?
        ");
        $stmtUser->bind_param("sssi", $email, $role, $status, $empId);
      }
      $stmtUser->execute();

      $conn->commit();
      $successMsg = "Employee updated successfully!";
    } else {
      // add employee
      $date_hired = date('Y-m-d');
      if (strtolower($role) === 'employee') {
        $stmtEmp = $conn->prepare("
          INSERT INTO employees (employee_code, first_name, last_name, email, position, role, status, date_hired)
          VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtEmp->bind_param("ssssssss", $employee_code, $first_name, $last_name, $email, $position, $role, $status, $date_hired);
        $stmtEmp->execute();
        $employee_id = $conn->insert_id;

        $stmtUser = $conn->prepare("
          INSERT INTO users (employee_id, username, password, role, status)
          VALUES (?, ?, ?, ?, ?)
        ");
        $stmtUser->bind_param("issss", $employee_id, $email, $password, $role, $status);
        $stmtUser->execute();
      } else {
        $stmtUser = $conn->prepare("
          INSERT INTO users (username, password, role, status)
          VALUES (?, ?, ?, ?)
        ");
        $stmtUser->bind_param("ssss", $email, $password, $role, $status);
        $stmtUser->execute();
      }

      $conn->commit();
      $successMsg = "Employee added successfully!";
    }
  } catch (Exception $e) {
    $conn->rollback();
    $errorMsg = "Operation failed: " . $e->getMessage();
  }

  // redirect back to module
  if ($successMsg) {
    echo "<script>
      setTimeout(() => {
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: '$successMsg',
          confirmButtonColor: '#3085d6',
          confirmButtonText: 'OK'
        }).then(() => {
          window.location.href = 'employee_module.php';
        });
      }, 300);
    </script>";
  }
}

// deact/react/delete logic
if ($isEdit && $_SERVER['REQUEST_METHOD'] === 'POST') {
  try {
    if (isset($_POST['deactivate_employee'])) {
      $conn->begin_transaction();
      $stmtEmp = $conn->prepare("UPDATE employees SET status='Inactive' WHERE employee_id=?");
      $stmtEmp->bind_param("i", $empId);
      $stmtEmp->execute();

      $stmtUser = $conn->prepare("UPDATE users SET status='Inactive' WHERE employee_id=?");
      $stmtUser->bind_param("i", $empId);
      $stmtUser->execute();

      $conn->commit();

      echo "<script>
        setTimeout(() => {
          Swal.fire({
            icon: 'success',
            title: 'Deactivated!',
            text: 'Employee has been deactivated successfully.',
            confirmButtonColor: '#3085d6'
          }).then(() => {
            window.location.href = 'employee_module.php';
          });
        }, 300);
      </script>";
      exit;
    }

    if (isset($_POST['reactivate_employee'])) {
      $conn->begin_transaction();
      $stmtEmp = $conn->prepare("UPDATE employees SET status='Active' WHERE employee_id=?");
      $stmtEmp->bind_param("i", $empId);
      $stmtEmp->execute();

      $stmtUser = $conn->prepare("UPDATE users SET status='Active' WHERE employee_id=?");
      $stmtUser->bind_param("i", $empId);
      $stmtUser->execute();

      $conn->commit();

      echo "<script>
        setTimeout(() => {
          Swal.fire({
            icon: 'success',
            title: 'Reactivated!',
            text: 'Employee has been reactivated successfully.',
            confirmButtonColor: '#3085d6'
          }).then(() => {
            window.location.href = 'employee_module.php';
          });
        }, 300);
      </script>";
      exit;
    }

    if (isset($_POST['delete_employee'])) {
      $conn->begin_transaction();
      $stmtUser = $conn->prepare("DELETE FROM users WHERE employee_id=?");
      $stmtUser->bind_param("i", $empId);
      $stmtUser->execute();

      $stmtEmp = $conn->prepare("DELETE FROM employees WHERE employee_id=?");
      $stmtEmp->bind_param("i", $empId);
      $stmtEmp->execute();

      $conn->commit();

      echo "<script>
        setTimeout(() => {
          Swal.fire({
            icon: 'success',
            title: 'Deleted!',
            text: 'Employee has been permanently deleted.',
            confirmButtonColor: '#3085d6'
          }).then(() => {
            window.location.href = 'employee_module.php';
          });
        }, 300);
      </script>";
      exit;
    }
  } catch (Exception $e) {
    $conn->rollback();
    $errorMsg = 'Action failed: ' . $e->getMessage();
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isEdit ? 'Edit' : 'Add' ?> Employee | KakaiOne</title>
  <?php include '../includes/links.php'; ?>
</head>
<body class="bg-light">

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold">
      <i class="bi <?= $isEdit ? 'bi-pencil-fill' : 'bi-person-plus-fill' ?> me-2 text-warning"></i>
      <?= $isEdit ? 'Edit Employee' : 'Add New Employee' ?>
    </h3>
    <a href="employee_module.php" class="btn btn-secondary">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <?php if ($successMsg): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
          <i class="bi bi-check-circle-fill me-2"></i><?= $successMsg ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php elseif ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $errorMsg ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="POST">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Employee Code</label>
            <input type="text" name="employee_code" class="form-control" value="<?= htmlspecialchars($employee['employee_code'] ?? '') ?>" <?= $isEdit ? 'readonly' : 'required' ?>>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Email (used as username)</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($employee['email'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">First Name</label>
            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($employee['first_name'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Last Name</label>
            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($employee['last_name'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Position</label>
            <input type="text" name="position" class="form-control" value="<?= htmlspecialchars($employee['position'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Role</label>
            <select name="role" class="form-select" required>
              <option value="Employee" <?= isset($employee['role']) && $employee['role'] === 'Employee' ? 'selected' : '' ?>>Employee</option>
              <option value="Admin" <?= isset($employee['role']) && $employee['role'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold"><?= $isEdit ? 'New Password (optional)' : 'Password' ?></label>
            <input type="password" name="password" class="form-control" placeholder="<?= $isEdit ? 'Leave blank to keep current' : 'Enter password' ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" class="form-select">
              <option value="Active" <?= isset($employee['status']) && $employee['status'] === 'Active' ? 'selected' : '' ?>>Active</option>
              <option value="Inactive" <?= isset($employee['status']) && $employee['status'] === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>
          <div class="col-12 d-flex justify-content-between align-items-center mt-4">
            <?php if (isset($employee['status'])): ?>
              <?php if ($employee['status'] === 'Active'): ?>
                <button 
                  type="button" 
                  class="btn btn-outline-danger fw-semibold px-4"
                  onclick="confirmDeactivate(<?= $employee['employee_id'] ?>)">
                  <i class="bi bi-person-dash-fill"></i> Deactivate Employee
                </button>
              <?php elseif ($employee['status'] === 'Inactive'): ?>
                <div class="btn-group">
                  <button 
                    type="button" 
                    class="btn btn-outline-success fw-semibold px-4"
                    onclick="confirmReactivate(<?= $employee['employee_id'] ?>)">
                    <i class="bi bi-person-check-fill"></i> Reactivate
                  </button>
                  <button 
                    type="button" 
                    class="btn btn-danger fw-semibold px-4"
                    onclick="confirmDelete(<?= $employee['employee_id'] ?>)">
                    <i class="bi bi-trash3-fill"></i> Delete
                  </button>
                </div>
              <?php endif; ?>
            <?php endif; ?>
            <button type="submit" class="btn btn-warning fw-semibold px-4">
              <i class="bi bi-save"></i> <?= $isEdit ? 'Update' : 'Save' ?> Employee
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
function confirmDelete(empId) {
  Swal.fire({
    title: 'Are you sure?',
    text: "This action will permanently delete the employee record.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, delete it',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.createElement('form');
      form.method = 'POST';
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'delete_employee';
      input.value = '1';
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    }
  });
}

function confirmDeactivate(empId) {
  Swal.fire({
    title: 'Deactivate Employee?',
    text: "This will disable the employee's account but keep their record in the system.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, deactivate',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.createElement('form');
      form.method = 'POST';
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'deactivate_employee';
      input.value = '1';
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    }
  });
}

function confirmReactivate(empId) {
  Swal.fire({
    title: 'Reactivate Employee?',
    text: "This will restore the employee's access and set their status to Active.",
    icon: 'question',
    showCancelButton: true,
    confirmButtonColor: '#28a745',
    cancelButtonColor: '#6c757d',
    confirmButtonText: 'Yes, reactivate',
    cancelButtonText: 'Cancel'
  }).then((result) => {
    if (result.isConfirmed) {
      const form = document.createElement('form');
      form.method = 'POST';
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'reactivate_employee';
      input.value = '1';
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    }
  });
}
</script>
</body>
</html>*/

session_start();
require_once __DIR__ . '/../../config/db.php';
include '../includes/links.php';

// role validation
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
  header('Location: ../auth/login.php');
  exit;
}

$successMsg = '';
$errorMsg = '';

$empId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$isEdit = $empId > 0;
$employee = [];

// fetch employee data

if ($isEdit) {
  try {
    $stmt = $pdo->prepare("
      SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, e.email, 
             e.contact_number, e.position, e.daily_rate, e.role, e.status, e.date_hired
      FROM employees e
      WHERE e.employee_id = ?
    ");
    $stmt->execute([$empId]);
    $employee = $stmt->fetch();

    if (!$employee) {
      $errorMsg = "Employee not found.";
      $isEdit = false;
    }
  } catch (PDOException $e) {
    $errorMsg = "Database error: " . $e->getMessage();
  }
}

// auto generate employee code for new entries
if (!$isEdit) {
  try {
    $stmt = $pdo->query("SELECT employee_code FROM employees ORDER BY employee_id DESC LIMIT 1");
    $lastCode = $stmt->fetchColumn();
    
    if ($lastCode && preg_match('/(\d+)$/', $lastCode, $matches)) {
      $nextNum = intval($matches[1]) + 1;
    } else {
      $nextNum = 1;
    }
    $employee['employee_code'] = 'EMP-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
  } catch (PDOException $e) {
    $employee['employee_code'] = 'EMP-0001';
  }
}

// form submission add/edit

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['action_type'])) {
  $inputData = [
      'employee_code' => trim($_POST['employee_code'] ?? ''),
      'first_name'    => trim($_POST['first_name'] ?? ''),
      'last_name'     => trim($_POST['last_name'] ?? ''),
      'email'         => trim($_POST['email'] ?? ''),
      'contact_number'=> trim($_POST['contact_number'] ?? ''),
      'position'      => trim($_POST['position'] ?? ''),
      'daily_rate'    => floatval($_POST['daily_rate'] ?? 0),
      'role'          => trim($_POST['role'] ?? 'Employee'),
      'status'        => trim($_POST['status'] ?? 'Active')
  ];

  $password = !empty($_POST['password']) ? password_hash($_POST['password'], PASSWORD_DEFAULT) : null;

  try {
    $pdo->beginTransaction();

    if ($isEdit) {
      // update logic
      $stmt = $pdo->prepare("
        UPDATE employees
        SET employee_code=?, first_name=?, last_name=?, email=?, contact_number=?, position=?, daily_rate=?, role=?, status=?
        WHERE employee_id=?
      ");
      $stmt->execute([
          $inputData['employee_code'], $inputData['first_name'], $inputData['last_name'], 
          $inputData['email'], $inputData['contact_number'], $inputData['position'], 
          $inputData['daily_rate'], $inputData['role'], $inputData['status'], $empId
      ]);

      // update user accoutn
      if ($password) {
        $stmtUser = $pdo->prepare("UPDATE users SET username=?, password=?, role=?, status=? WHERE employee_id=?");
        $stmtUser->execute([$inputData['email'], $password, $inputData['role'], $inputData['status'], $empId]);
      } else {
        $stmtUser = $pdo->prepare("UPDATE users SET username=?, role=?, status=? WHERE employee_id=?");
        $stmtUser->execute([$inputData['email'], $inputData['role'], $inputData['status'], $empId]);
      }

      $successMsg = "Employee updated successfully!";

    } else {
      // insert logic
      $date_hired = date('Y-m-d');
      $stmtEmp = $pdo->prepare("
        INSERT INTO employees (employee_code, first_name, last_name, email, contact_number, position, daily_rate, role, status, date_hired)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
      ");
      $stmtEmp->execute([
          $inputData['employee_code'], $inputData['first_name'], $inputData['last_name'], 
          $inputData['email'], $inputData['contact_number'], $inputData['position'], 
          $inputData['daily_rate'], $inputData['role'], $inputData['status'], $date_hired
      ]);
      $employee_id = $pdo->lastInsertId();

      // create user account
      $defaultPass = $password ?: password_hash('password123', PASSWORD_DEFAULT);
      $stmtUser = $pdo->prepare("INSERT INTO users (employee_id, username, password, role, status) VALUES (?, ?, ?, ?, ?)");
      $stmtUser->execute([$employee_id, $inputData['email'], $defaultPass, $inputData['role'], $inputData['status']]);

      $successMsg = "Employee added successfully!";
    }

    $pdo->commit();
    
    $employee = array_merge($employee, $inputData);

  } catch (PDOException $e) {
    $pdo->rollBack();

    // see if data exists
    if ($e->errorInfo[1] == 1062) { 
        if (strpos($e->getMessage(), 'email') !== false) {
            $errorMsg = "The email address <strong>" . htmlspecialchars($inputData['email']) . "</strong> is already taken.";
        } elseif (strpos($e->getMessage(), 'employee_code') !== false) {
            $errorMsg = "The Employee Code <strong>" . htmlspecialchars($inputData['employee_code']) . "</strong> already exists.";
        } else {
            $errorMsg = "Duplicate record found. Please check your inputs.";
        }
    } else {
        $errorMsg = "System Error: " . $e->getMessage();
    }

    $employee = array_merge($employee, $inputData);
  }

  // redirect on success
  if ($successMsg) {
    echo "<script>
      setTimeout(() => {
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: '$successMsg',
          confirmButtonColor: '#ffc107',
          confirmButtonText: 'OK'
        }).then(() => {
          window.location.href = 'employee_module.php';
        });
      }, 300);
    </script>";
  }
}

// deact/react/delete logic

if ($isEdit && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
  $action = $_POST['action_type'];
  
  try {
    $pdo->beginTransaction();

    if ($action === 'deactivate') {
      $pdo->prepare("UPDATE employees SET status='Inactive' WHERE employee_id=?")->execute([$empId]);
      $pdo->prepare("UPDATE users SET status='Inactive' WHERE employee_id=?")->execute([$empId]);
      $title = 'Deactivated!'; $text = 'Employee is now inactive.';
    } elseif ($action === 'reactivate') {
      $pdo->prepare("UPDATE employees SET status='Active' WHERE employee_id=?")->execute([$empId]);
      $pdo->prepare("UPDATE users SET status='Active' WHERE employee_id=?")->execute([$empId]);
      $title = 'Reactivated!'; $text = 'Employee is now active.';
    } elseif ($action === 'delete') {
      $pdo->prepare("DELETE FROM users WHERE employee_id=?")->execute([$empId]);
      $pdo->prepare("DELETE FROM employees WHERE employee_id=?")->execute([$empId]);
      $title = 'Deleted!'; $text = 'Employee permanently deleted.';
    }

    $pdo->commit();

    echo "<script>
      setTimeout(() => {
        Swal.fire({
          icon: 'success',
          title: '$title',
          text: '$text',
          confirmButtonColor: '#3085d6'
        }).then(() => {
          window.location.href = 'employee_module.php';
        });
      }, 300);
    </script>";
    exit;

  } catch (Exception $e) {
    $pdo->rollBack();
    $errorMsg = 'Action failed: ' . $e->getMessage();
  }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $isEdit ? 'Edit' : 'Add' ?> Employee | KakaiOne</title>
  <?php include '../includes/links.php'; ?>
  <!--<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>-->
</head>
<body class="bg-light">

<div class="container py-4">
  <div class="d-flex justify-content-between align-items-center mb-4">
    <h3 class="fw-bold">
      <i class="bi <?= $isEdit ? 'bi-pencil-fill' : 'bi-person-plus-fill' ?> me-2 text-warning"></i>
      <?= $isEdit ? 'Edit Employee' : 'Add New Employee' ?>
    </h3>
    <a href="employee_module.php" class="btn btn-secondary">
      <i class="bi bi-arrow-left"></i> Back
    </a>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <?php if ($errorMsg): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="bi bi-exclamation-triangle-fill me-2"></i><?= $errorMsg ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>

      <form method="POST">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label fw-semibold">Employee Code</label>
            <input type="text" name="employee_code" class="form-control" value="<?= htmlspecialchars($employee['employee_code'] ?? '') ?>" <?= $isEdit ? 'readonly' : 'readonly' ?> style="background-color: #e9ecef;">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Email (Username)</label>
            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($employee['email'] ?? '') ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">First Name</label>
            <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($employee['first_name'] ?? '') ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Last Name</label>
            <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($employee['last_name'] ?? '') ?>" required>
          </div>

          <div class="col-md-6">
            <label class="form-label fw-semibold">Contact Number</label>
            <input type="text" name="contact_number" class="form-control" placeholder="e.g. 0912 345 6789" value="<?= htmlspecialchars($employee['contact_number'] ?? '') ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label fw-semibold">Daily Rate (₱)</label>
            <input type="number" step="0.01" name="daily_rate" class="form-control" placeholder="0.00" value="<?= htmlspecialchars($employee['daily_rate'] ?? '500.00') ?>" required>
          </div>

          <div class="col-md-4">
            <label class="form-label fw-semibold">Position</label>
            <input type="text" name="position" class="form-control" value="<?= htmlspecialchars($employee['position'] ?? '') ?>" required>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Role</label>
            <select name="role" class="form-select" required>
              <option value="Employee" <?= (isset($employee['role']) && $employee['role'] === 'Employee') ? 'selected' : '' ?>>Employee</option>
              <option value="Admin" <?= (isset($employee['role']) && $employee['role'] === 'Admin') ? 'selected' : '' ?>>Admin</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label fw-semibold">Status</label>
            <select name="status" class="form-select">
              <option value="Active" <?= (isset($employee['status']) && $employee['status'] === 'Active') ? 'selected' : '' ?>>Active</option>
              <option value="Inactive" <?= (isset($employee['status']) && $employee['status'] === 'Inactive') ? 'selected' : '' ?>>Inactive</option>
            </select>
          </div>

          <div class="col-md-12">
            <label class="form-label fw-semibold"><?= $isEdit ? 'Reset Password (Optional)' : 'Password' ?></label>
            <input type="password" name="password" class="form-control" placeholder="<?= $isEdit ? 'Leave blank to keep current' : 'Enter password' ?>">
          </div>

          <div class="col-12 d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
            
            <div>
              <?php if ($isEdit): ?>
                <?php if ($employee['status'] === 'Active'): ?>
                  <button type="button" class="btn btn-outline-danger fw-semibold" onclick="triggerAction('deactivate')">
                    <i class="bi bi-person-dash-fill"></i> Deactivate
                  </button>
                <?php else: ?>
                  <button type="button" class="btn btn-outline-success fw-semibold" onclick="triggerAction('reactivate')">
                    <i class="bi bi-person-check-fill"></i> Reactivate
                  </button>
                  <button type="button" class="btn btn-danger fw-semibold ms-2" onclick="triggerAction('delete')">
                    <i class="bi bi-trash3-fill"></i> Delete
                  </button>
                <?php endif; ?>
              <?php endif; ?>
            </div>

            <button type="submit" class="btn btn-warning fw-semibold px-5">
              <i class="bi bi-save"></i> <?= $isEdit ? 'Save Changes' : 'Create Employee' ?>
            </button>

          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<form id="actionForm" method="POST" style="display:none;">
    <input type="hidden" name="action_type" id="actionTypeInput">
</form>

<script>
function triggerAction(action) {
  let title, text, btnColor, confirmText;

  if (action === 'delete') {
    title = 'Are you sure?';
    text = "This will permanently delete the employee record and cannot be undone.";
    btnColor = '#d33';
    confirmText = 'Yes, delete it!';
  } else if (action === 'deactivate') {
    title = 'Deactivate Employee?';
    text = "User will not be able to log in.";
    btnColor = '#d33';
    confirmText = 'Yes, deactivate';
  } else {
    title = 'Reactivate Employee?';
    text = "User access will be restored.";
    btnColor = '#28a745';
    confirmText = 'Yes, reactivate';
  }

  Swal.fire({
    title: title,
    text: text,
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: btnColor,
    cancelButtonColor: '#6c757d',
    confirmButtonText: confirmText
  }).then((result) => {
    if (result.isConfirmed) {
      document.getElementById('actionTypeInput').value = action;
      document.getElementById('actionForm').submit();
    }
  });
}
</script>

</body>
</html>