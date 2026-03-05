<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';

// check permissions
if (!hasPermission('payroll_view')) {
    echo json_encode(['success' => false]);
    exit;
}

$pid = intval($_GET['payroll_id'] ?? 0);
if (!$pid) {
    echo json_encode(['success' => false]);
    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT p.*, e.first_name, e.last_name, e.employee_code 
        FROM payroll_entries p JOIN employees e ON p.employee_id = e.employee_id 
        WHERE p.payroll_id = ? ORDER BY e.last_name ASC
    ");
    $stmt->execute([$pid]);
    echo json_encode(['success' => true, 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
} catch (Exception $e) {
    echo json_encode(['success' => false]);
}
