<?php
require_once __DIR__ . '/../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $full_name = trim($_POST['full_name']);
  $role = trim($_POST['role']);
  $email = trim($_POST['email']);
  $contact_number = trim($_POST['contact_number']);
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];

  if ($password !== $confirm_password) {
    die("Passwords do not match.");
  }

  $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
  $status = 'active';

  $stmt = $conn->prepare("INSERT INTO users (username, password, role, email, contact_number, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
  $stmt->bind_param("ssssss", $full_name, $hashedPassword, $role, $email, $contact_number, $status);

  if ($stmt->execute()) {
    header("Location: ../../frontend/dashboard/employee_module.php");
    exit;
  } else {
    echo "Error: " . $stmt->error;
  }
}
?>
