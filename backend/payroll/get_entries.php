<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false]); exit; }

$pid = isset($_GET['payroll_id']) ? intval($_GET['payroll_id']) : 0;
if (!$pid) { echo json_encode(['success'=>false,'message'=>'payroll_id required']); exit; }

$stmt = $conn->prepare("SELECT p.id, p.employee_id, e.first_name, e.last_name, p.gross_pay, p.cash_advance, p.net_pay, p.details FROM payroll_entries p JOIN employees e ON p.employee_id = e.employee_id WHERE p.payroll_id = ?");
$stmt->bind_param('i', $pid);
$stmt->execute();
$res = $stmt->get_result();
$data = $res->fetch_all(MYSQLI_ASSOC);
$stmt->close();

echo json_encode(['success'=>true, 'data'=>$data]);
?>
