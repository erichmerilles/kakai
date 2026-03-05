<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// role validation
if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.php');
  exit();
}

// get emp_id
$employee_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($employee_id <= 0) {
  die("Invalid employee ID.");
}

// fetch employee details
$stmt = $conn->prepare("
  SELECT e.employee_id, e.employee_code, e.first_name, e.last_name, 
         e.email, e.position, e.role, e.status, e.date_hired, e.created_at
  FROM employees e
  WHERE e.employee_id = ? AND e.role = 'Employee'
");
$stmt->bind_param("i", $employee_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
  die("Employee not found or not an Employee role.");
}

$employee = $result->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php include '../includes/links.php'; ?>
  <title>View Employee</title>
</head>

<body class="bg-light">

  <div class="container mt-5">
    <div class="card shadow-sm">
      <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
        <h5 class="mb-0"><i class="bi bi-person-lines-fill me-2"></i>Employee Details</h5>
        <a href="employee_module.php" class="btn btn-light btn-sm"><i class="bi bi-arrow-left"></i> Back</a>
      </div>
      <div class="card-body">
        <div class="row mb-3">
          <div class="col-md-6">
            <h6><i class="bi bi-person-fill me-2"></i>Full Name:</h6>
            <p class="fw-semibold"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></p>
          </div>
          <div class="col-md-6">
            <h6><i class="bi bi-envelope-fill me-2"></i>Email:</h6>
            <p><?= htmlspecialchars($employee['email']) ?></p>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <h6><i class="bi bi-hash me-2"></i>Employee Code:</h6>
            <p><?= htmlspecialchars($employee['employee_code']) ?></p>
          </div>
          <div class="col-md-6">
            <h6><i class="bi bi-briefcase-fill me-2"></i>Position:</h6>
            <p><?= htmlspecialchars($employee['position']) ?></p>
          </div>
        </div>

        <div class="row mb-3">
          <div class="col-md-6">
            <h6><i class="bi bi-person-badge-fill me-2"></i>Role:</h6>
            <p><?= htmlspecialchars($employee['role']) ?></p>
          </div>
          <div class="col-md-6">
            <h6><i class="bi bi-check-circle-fill me-2"></i>Status:</h6>
            <span class="badge <?= $employee['status'] === 'Active' ? 'bg-success' : 'bg-danger' ?>">
              <?= htmlspecialchars($employee['status']) ?>
            </span>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <h6><i class="bi bi-calendar-event-fill me-2"></i>Date Hired:</h6>
            <p><?= htmlspecialchars($employee['date_hired'] ? date('F d, Y', strtotime($employee['date_hired'])) : 'N/A') ?></p>
          </div>
          <div class="col-md-6">
            <h6><i class="bi bi-calendar-check-fill me-2"></i>Date Created:</h6>
            <p><?= date('F d, Y', strtotime($employee['created_at'])) ?></p>
          </div>
        </div>
      </div>
    </div>
  </div>

</body>

</html>