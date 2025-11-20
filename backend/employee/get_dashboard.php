<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

if (!isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$empId = $_SESSION['employee_id'];

// Get Summary Stats
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

// Days Present
$presentQ = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE employee_id = $empId AND status = 'Approved' AND DATE(created_at) BETWEEN '$monthStart' AND '$monthEnd'");
$present = $presentQ->fetch_assoc()['count'];

// Absences
$absentQ = $conn->query("SELECT COUNT(*) as count FROM attendance WHERE employee_id = $empId AND (status = 'Absent' OR status = 'Rejected') AND DATE(created_at) BETWEEN '$monthStart' AND '$monthEnd'");
$absent = $absentQ->fetch_assoc()['count'];

// Total Hours
$hoursQ = $conn->query("
    SELECT SUM(TIMESTAMPDIFF(HOUR, time_in, IFNULL(time_out, NOW()))) as hours 
    FROM attendance 
    WHERE employee_id = $empId AND status = 'Approved' AND DATE(created_at) BETWEEN '$monthStart' AND '$monthEnd'
");
$totalHours = $hoursQ->fetch_assoc()['hours'] ?? 0;

// Average Clock In
$avgQ = $conn->query("SELECT AVG(TIME_TO_SEC(TIME(time_in))) as avg_sec FROM attendance WHERE employee_id = $empId AND status = 'Approved'");
$avgRow = $avgQ->fetch_assoc();
$avgTime = $avgRow['avg_sec'] ? date('h:i A', $avgRow['avg_sec']) : '--:--';

// Calendar Data
$calData = [];
$calQ = $conn->query("SELECT DATE(created_at) as date, status, time_in FROM attendance WHERE employee_id = $empId AND MONTH(created_at) = MONTH(CURRENT_DATE())");
while($row = $calQ->fetch_assoc()) {
    $color = '#6c757d';
    // Check for 'Approved'
    if ($row['status'] == 'Approved') $color = '#198754'; 
    if ($row['status'] == 'Absent' || $row['status'] == 'Rejected') $color = '#dc3545';
    if ($row['status'] == 'Pending Approval') $color = '#ffc107';
    
    $calData[] = [
        'title' => $row['status'],
        'start' => $row['date'],
        'backgroundColor' => $color,
        'borderColor' => $color
    ];
}

// Get Current Status
$statusQ = $conn->query("SELECT * FROM attendance WHERE employee_id = $empId AND DATE(created_at) = CURDATE() ORDER BY attendance_id DESC LIMIT 1");

$currentStatus = 'Off Duty';
$lastLog = 'N/A';

if ($statusQ && $statusQ->num_rows > 0) {
    $r = $statusQ->fetch_assoc();
    $lastLog = date('M d, h:i A', strtotime($r['created_at']));
    
    if ($r['time_out'] !== null) {
        $currentStatus = 'Clocked Out';
    } elseif ($r['break_out_time'] !== null && $r['break_in_time'] === null) {
        $currentStatus = 'On Break';
    } else {
        $currentStatus = $r['status']; 
    }
}

echo json_encode([
    'success' => true,
    'stats' => [
        'present' => $present,
        'absent' => $absent,
        'hours' => round($totalHours, 1),
        'avg_in' => $avgTime
    ],
    'calendar' => $calData,
    'profile' => [
        'status' => $currentStatus,
        'last_log' => $lastLog
    ]
]);
?>