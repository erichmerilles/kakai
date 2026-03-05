<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';
require_once __DIR__ . '/../utils/logger.php';

// check permissions
if (!hasPermission('att_approve')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: Admin access required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $empId   = intval($_POST['employee_id'] ?? 0);
    $timeIn  = $_POST['time_in'] ?? null;
    $timeOut = $_POST['time_out'] ?? null;
    $status  = $_POST['status'] ?? 'Present';

    if (!$empId || !$timeIn) {
        echo json_encode(['success' => false, 'message' => 'Employee and Time In are required.']);
        exit;
    }

    // timestamps comparison
    $startTs = strtotime($timeIn);
    $dateOnly = date('Y-m-d', $startTs);

    try {
        // look for duplicate record 
        $checkStmt = $pdo->prepare("SELECT attendance_id FROM attendance WHERE employee_id = ? AND DATE(time_in) = ?");
        $checkStmt->execute([$empId, $dateOnly]);

        if ($checkStmt->fetch()) {
            echo json_encode([
                'success' => false,
                'message' => 'Action Blocked: This employee already has an attendance record for ' . date('M d, Y', $startTs) . '. Please edit the existing record instead.'
            ]);
            exit;
        }

        // time out logic
        $totalHours = 0;
        if (!empty($timeOut)) {
            $endTs = strtotime($timeOut);

            if ($endTs <= $startTs) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Invalid Logic: Time Out cannot be earlier than or equal to Time In.'
                ]);
                exit;
            }

            // calculate total hours
            $totalHours = ($endTs - $startTs) / 3600;
        }

        // insert attendance record
        $insStmt = $pdo->prepare("
            INSERT INTO attendance (employee_id, time_in, time_out, status, total_hours, created_at) 
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $insStmt->execute([
            $empId,
            $timeIn,
            !empty($timeOut) ? $timeOut : null,
            $status,
            $totalHours
        ]);

        // log activity
        logActivity($pdo, $_SESSION['user_id'], 'Manual Entry', 'Attendance', "Manually logged attendance for Employee ID: $empId on " . date('M d, Y', $startTs));

        echo json_encode(['success' => true, 'message' => 'Manual attendance log recorded successfully.']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database Error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
}
