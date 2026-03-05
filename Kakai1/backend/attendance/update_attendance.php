<?php
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';

header('Content-Type: application/json');

// check permissions
if (!hasPermission('att_edit')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: You do not have permission to edit attendance.']);
    exit;
}

$id = $_POST['attendance_id'] ?? null;
$timeIn = $_POST['time_in'] ?? null;
$timeOut = $_POST['time_out'] ?? null;
$manualStatus = $_POST['status'] ?? 'Present';

if (!$id || !$timeIn) {
    echo json_encode(['success' => false, 'message' => 'Time In is required']);
    exit;
}

try {
    $totalHours = 0;
    $pendingOvertime = 0;
    $finalTimeOut = null;

    $timeInParsed = strtotime($timeIn);
    $logDate = date('Y-m-d', $timeInParsed);

    // auto determine status based on time in
    $timeInHourMinute = date('H:i', $timeInParsed);
    if ($manualStatus === 'Present' || $manualStatus === 'Late') {
        $status = ($timeInHourMinute > '07:00') ? 'Late' : 'Present';
    } else {
        $status = $manualStatus;
    }

    if (!empty($timeOut)) {
        $timeOutParsed = strtotime($timeOut);

        if ($timeOutParsed > $timeInParsed) {
            $finalTimeOut = $timeOut;

            // calculate start time
            $sevenAM = strtotime($logDate . ' 07:00:00');
            // calculate start time for hours calculation
            $calcTimeIn = ($timeInParsed < $sevenAM) ? $sevenAM : $timeInParsed;

            // 5 pm cap for regular hours
            $fivePM = strtotime($logDate . ' 17:00:00');

            // time-out after 5 PM is pending OT
            if ($timeOutParsed > $fivePM) {
                $calcTimeOut = $fivePM;

                // calculate excess time for overtime
                $otStartTime = ($timeInParsed > $fivePM) ? $timeInParsed : $fivePM;
                $pendingOvertime = round(($timeOutParsed - $otStartTime) / 3600, 2);
            } else {
                $calcTimeOut = $timeOutParsed;
                $pendingOvertime = 0;
            }

            // calculate total regular hours
            $diff = $calcTimeOut - $calcTimeIn;
            $totalHours = round($diff / 3600, 2);

            // prevent negative hours
            if ($totalHours < 0) {
                $totalHours = 0;
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Time Out cannot be before Time In']);
            exit;
        }
    }

    // update record
    $stmt = $pdo->prepare("
        UPDATE attendance 
        SET time_in = ?, time_out = ?, status = ?, total_hours = ?, pending_overtime = ?, approved_overtime = 0 
        WHERE attendance_id = ?
    ");

    $stmt->execute([$timeIn, $finalTimeOut, $status, $totalHours, $pendingOvertime, $id]);

    echo json_encode(['success' => true, 'message' => 'Attendance updated successfully']);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
