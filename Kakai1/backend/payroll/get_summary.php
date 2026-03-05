<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';

// check permissions
if (!hasPermission('payroll_view')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$filter = $_GET['filter'] ?? 'monthly';

// filter logic for grouping
if ($filter === 'daily') {
    $groupBy = "DATE_FORMAT(created_at, '%b %d, %Y')";
    $orderBy = "DATE(created_at)";
} elseif ($filter === 'weekly') {
    $groupBy = "CONCAT('Week ', WEEK(created_at, 1), ', ', YEAR(created_at))";
    $orderBy = "YEARWEEK(created_at, 1)";
} else {
    $groupBy = "DATE_FORMAT(created_at, '%M %Y')";
    $orderBy = "DATE_FORMAT(created_at, '%Y-%m')";
}

try {
    // total payroll summary
    $sql = "
        SELECT 
            $groupBy as label,
            SUM(total_gross) as total_gross,
            SUM(total_deductions) as total_deductions,
            SUM(total_net) as total_net,
            COUNT(payroll_id) as run_count
        FROM payroll_runs
        GROUP BY $groupBy, $orderBy
        ORDER BY $orderBy ASC
        LIMIT 15
    ";

    $stmt = $pdo->query($sql);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // arrays for chart data
    $labels = [];
    $gross = [];
    $deductions = [];
    $net = [];

    foreach ($results as $r) {
        $labels[] = $r['label'];
        $gross[] = (float)$r['total_gross'];
        $deductions[] = (float)$r['total_deductions'];
        $net[] = (float)$r['total_net'];
    }

    echo json_encode([
        'success' => true,
        'labels' => $labels,
        'gross' => $gross,
        'deductions' => $deductions,
        'net' => $net,
        'raw_data' => $results
    ]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
