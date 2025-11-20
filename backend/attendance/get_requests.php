<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

// Ensure user is Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Fetch pending requests
$requestQuery = $conn->query("
  SELECT a.attendance_id, u.username AS name, a.status, a.created_at, a.time_in
  FROM attendance a
  JOIN users u ON u.employee_id = a.employee_id
  WHERE a.status = 'Pending Approval'
  ORDER BY a.created_at DESC
");

$data = [];
if ($requestQuery) {
    while ($row = $requestQuery->fetch_assoc()) {
        $row['time_in_fmt'] = date('h:i A', strtotime($row['time_in']));
        $row['date_fmt'] = date('M d, Y', strtotime($row['created_at']));
        $data[] = $row;
    }
}

echo json_encode(['success' => true, 'data' => $data]);
?>