<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
$input = json_decode(file_get_contents('php://input'), true);
$fn = $conn->real_escape_string($input['full_name'] ?? '');
$phone = $conn->real_escape_string($input['phone'] ?? '');
$email = $conn->real_escape_string($input['email'] ?? '');
if(!$fn) { echo json_encode(['success'=>false,'message'=>'Name required']); exit; }
$stmt = $conn->prepare("INSERT INTO customers (full_name, phone, email) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $fn, $phone, $email);
$stmt->execute();
echo json_encode(['success'=>true,'customer_id'=>$stmt->insert_id]);
?>
