<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false]);
    exit;
}

// We dynamically calculate total_deductions directly from the payroll_entries 
// table to guarantee it matches the exact Cash Advance deductions.
$query = "
    SELECT 
        r.payroll_id, 
        r.start_date, 
        r.end_date, 
        r.created_at, 
        r.total_gross, 
        r.total_net, 
        (SELECT COALESCE(SUM(cash_advance), 0) FROM payroll_entries WHERE payroll_id = r.payroll_id) as total_deductions
    FROM payroll_runs r 
    ORDER BY r.payroll_id DESC
";

// Add is_published if the column exists in your schema
$checkCol = $conn->query("SHOW COLUMNS FROM payroll_runs LIKE 'is_published'");
if ($checkCol && $checkCol->num_rows > 0) {
    $query = str_replace("r.created_at,", "r.created_at, r.is_published,", $query);
}

$res = $conn->query($query);
$runs = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];

echo json_encode(['success' => true, 'data' => $runs]);
