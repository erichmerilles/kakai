<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';

header('Content-Type: application/json');

// check permission
if (!hasPermission('att_approve')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: You do not have permission to approve overtime.']);
    exit;
}

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id === 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid attendance ID.']);
    exit;
}

try {
    // move pending OT to approved OT, and reset pending to 0
    $stmt = $pdo->prepare("
        UPDATE attendance 
        SET approved_overtime = pending_overtime, pending_overtime = 0 
        WHERE attendance_id = ?
    ");
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['success' => true, 'message' => 'Overtime approved successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record not found or already updated.']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
