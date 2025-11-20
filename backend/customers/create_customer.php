<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
$input = json_decode(file_get_contents('php://input'), true);

$fn = $input['full_name'] ?? '';
$phone = $input['phone'] ?? '';
$email = $input['email'] ?? '';

if(!$fn) { echo json_encode(['success'=>false,'message'=>'Name required']); exit; }
$stmt = $conn->prepare("INSERT INTO customers (full_name, phone, email) VALUES (?, ?, ?)");
$stmt->bind_param('sss', $fn, $phone, $email);
$stmt->execute();

// Check for successful insertion
if ($stmt->affected_rows > 0) {
    echo json_encode(['success'=>true,'customer_id'=>$stmt->insert_id]);
} else {
    // Log error
    error_log("Failed to create customer: " . $stmt->error);
    echo json_encode(['success'=>false,'message'=>'Failed to create customer due to a database error.']);
}
$stmt->close();
?>