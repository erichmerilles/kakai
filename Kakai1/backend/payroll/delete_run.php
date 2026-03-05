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

$payroll_id = intval($_GET['id'] ?? 0);

if (!$payroll_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid Payroll ID.']);
    exit;
}

try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->beginTransaction();

    // revert cash advances
    $stmtRevertCA = $pdo->prepare("UPDATE cash_advance SET is_paid = 0, payroll_id = NULL WHERE payroll_id = ?");
    $stmtRevertCA->execute([$payroll_id]);

    // revert attendance records
    $stmtRevertAtt = $pdo->prepare("UPDATE attendance SET is_paid = 0, payroll_id = NULL WHERE payroll_id = ?");
    $stmtRevertAtt->execute([$payroll_id]);

    // delete payroll entries and run
    $pdo->prepare("DELETE FROM payroll_entries WHERE payroll_id = ?")->execute([$payroll_id]);
    $pdo->prepare("DELETE FROM payroll_runs WHERE payroll_id = ?")->execute([$payroll_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Payroll Run deleted successfully. Data reverted.']);
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
}
