<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Ensure only admin can decline
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
  header('Location: ../../frontend/auth/login.php');
  exit;
}

if (isset($_GET['id'])) {
  $leave_id = intval($_GET['id']);
  $admin_id = $_SESSION['user_id'];

  $update = $conn->prepare("
    UPDATE leave_requests 
    SET status = 'Declined', reviewed_by = ?, reviewed_at = NOW() 
    WHERE leave_id = ?
  ");
  $update->bind_param('ii', $admin_id, $leave_id);

  if ($update->execute()) {
    $_SESSION['message'] = "Leave request #$leave_id declined.";
  } else {
    $_SESSION['message'] = "Error declining request: " . $conn->error;
  }
}

header('Location: ../../frontend/dashboard/employee_module.php');
exit;
?>
