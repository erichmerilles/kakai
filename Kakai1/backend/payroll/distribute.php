<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';

// check permissions
if (!hasPermission('payroll_generate')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$payroll_id = intval($input['payroll_id'] ?? 0);

if (!$payroll_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Payroll ID.']);
    exit;
}

try {
    // update the run to published
    $stmt = $pdo->prepare("UPDATE payroll_runs SET is_published = 1 WHERE payroll_id = ?");
    $stmt->execute([$payroll_id]);

    echo json_encode(['success' => true, 'message' => 'Payslips have been successfully distributed to employees!']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
