<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

header('Content-Type: application/json');

// role validation
if (!isset($_SESSION['user_id']) || !isset($_SESSION['employee_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// allowed ip
$allowedIPs = [
    '192.168.10.20',  // actual ip address
    '::1',            // Localhost
];

// get user IP address
$userIP = $_SERVER['REMOTE_ADDR'];

/* for proxy ip
if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
    $userIP = $_SERVER['HTTP_X_FORWARDED_FOR'];
}*/

// validate IP
if (!in_array($userIP, $allowedIPs)) {
    echo json_encode([
        'success' => false,
        'message' => "Restricted Access! You must be connected to the store Wi-Fi. (Your IP: $userIP)"
    ]);
    exit;
}


$employee_id = $_SESSION['employee_id'];
$input = json_decode(file_get_contents("php://input"), true);
$type = $input['type'] ?? '';

if ($type === 'in') {
    // time in logic
    try {
        // check if clocked in 
        $stmt = $pdo->prepare("SELECT attendance_id FROM attendance WHERE employee_id = ? AND DATE(time_in) = CURDATE()");
        $stmt->execute([$employee_id]);

        if ($stmt->fetch()) {
            echo json_encode(['success' => false, 'message' => 'You have already clocked in today.']);
            exit;
        }

        // work starts at 7 am, otherwise late
        $status = (date('H:i') > '07:00') ? 'Late' : 'Present';

        // insert record
        $insert = $pdo->prepare("
            INSERT INTO attendance (employee_id, time_in, status, created_at) 
            VALUES (?, NOW(), ?, NOW())
        ");
        $insert->execute([$employee_id, $status]);

        echo json_encode(['success' => true, 'message' => 'Time In recorded successfully!']);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} elseif ($type === 'out') {
    // time out logic
    try {
        $stmt = $pdo->prepare("
            SELECT attendance_id, time_in 
            FROM attendance 
            WHERE employee_id = ? AND DATE(time_in) = CURDATE() AND time_out IS NULL 
            LIMIT 1
        ");
        $stmt->execute([$employee_id]);
        $record = $stmt->fetch();

        if (!$record) {
            echo json_encode(['success' => false, 'message' => 'No active Time In record found for today.']);
            exit;
        }

        // auto cap time out to 5 pm
        $timeIn = new DateTime($record['time_in']);
        $now = new DateTime();

        // define 5 pm
        $fivePM = new DateTime(date('Y-m-d 17:00:00'));

        // force time out to be 5 pm
        if ($now > $fivePM) {
            $timeOut = $fivePM;
            $autoCapped = true;
        } else {
            $timeOut = $now;
            $autoCapped = false;
        }

        // calculate total hours
        $interval = $timeIn->diff($timeOut);
        $totalHours = $interval->h + ($interval->i / 60);

        // clock in after 5 pm automatic 0 hours
        if ($timeOut < $timeIn) {
            $totalHours = 0;
        }

        // update record
        $update = $pdo->prepare("
            UPDATE attendance 
            SET time_out = ?, total_hours = ? 
            WHERE attendance_id = ?
        ");
        $update->execute([$timeOut->format('Y-m-d H:i:s'), $totalHours, $record['attendance_id']]);

        // provide feedback message
        if ($autoCapped) {
            echo json_encode(['success' => true, 'message' => 'Time Out recorded! (System automatically capped your hours to 5:00 PM)']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Time Out recorded! See you tomorrow.']);
        }
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action type.']);
}
