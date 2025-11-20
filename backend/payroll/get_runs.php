<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
session_start();
if (!isset($_SESSION['user_id'])) { echo json_encode(['success'=>false]); exit; }

$res = $conn->query("SELECT payroll_id, start_date, end_date, created_at, total_gross, total_deductions, total_net FROM payroll_runs ORDER BY payroll_id DESC");
$runs = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
echo json_encode(['success'=>true, 'data'=>$runs]);
?>
