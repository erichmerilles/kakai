<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

define('STORE_PUBLIC_IP', '127.0.0.1'); 

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$employee_id = $_SESSION['employee_id'];
$action = $_POST['action'] ?? '';
$reason = $_POST['reason'] ?? 'Standard';

// CLOCK IN
if ($action === 'clock_in') {
    // IP Address Check
    $userIP = $_SERVER['REMOTE_ADDR'];

    // Check if IP matches Store IP
    if ($userIP !== STORE_PUBLIC_IP && $userIP !== '127.0.0.1' && $userIP !== '::1') {
        echo json_encode([
            'success' => false, 
            'message' => "Access Denied: You are not connected to the Store WiFi. (Detected IP: $userIP)"
        ]);
        exit;
    }

    // Check if already clocked in today
    $today = date('Y-m-d');
    $check = $conn->query("SELECT * FROM attendance WHERE employee_id = $employee_id AND DATE(created_at) = '$today'");
    if ($check->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'You already have an attendance record for today.']);
        exit;
    }

    // Insert Record (Status: Pending Approval)
    $stmt = $conn->prepare("INSERT INTO attendance (employee_id, time_in, status, created_at) VALUES (?, NOW(), 'Pending Approval', NOW())");
    $stmt->bind_param("i", $employee_id);
    
    if ($stmt->execute()) {
        // Notify Admin
        $empName = $_SESSION['username'] ?? 'Employee';
        $msg = "$empName requested Clock-In via Store WiFi ($userIP). Approval needed.";
        $conn->query("INSERT INTO notifications (type, message, status) VALUES ('Attendance', '$msg', 'Unread')");
        
        echo json_encode(['success' => true, 'message' => 'Clock-in request sent to Admin for approval.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Database error.']);
    }
}

// CLOCK OUT / BREAK
elseif ($action === 'clock_out') {
    $today = date('Y-m-d');
    $query = "SELECT attendance_id, time_in, status FROM attendance WHERE employee_id = $employee_id AND DATE(created_at) = '$today' ORDER BY attendance_id DESC LIMIT 1";
    $result = $conn->query($query);
    $record = $result->fetch_assoc();

    if (!$record) {
        echo json_encode(['success' => false, 'message' => 'No active attendance record found for today.']);
        exit;
    }

    // LOGIC BASED ON REASON
    if ($reason === 'Break') {
        $conn->query("UPDATE attendance SET break_out_time = NOW(), time_out_reason = 'Break' WHERE attendance_id = {$record['attendance_id']}");
        echo json_encode(['success' => true, 'message' => 'You are now on break. Clock in again when you return.']);
    } 
    elseif ($reason === 'Return_Break') {
        $conn->query("UPDATE attendance SET break_in_time = NOW(), time_out_reason = 'Standard' WHERE attendance_id = {$record['attendance_id']}");
         echo json_encode(['success' => true, 'message' => 'Welcome back from break.']);
    }
    else {
        $stmt = $conn->prepare("UPDATE attendance SET time_out = NOW(), time_out_reason = ? WHERE attendance_id = ?");
        $stmt->bind_param("si", $reason, $record['attendance_id']);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Clocked out successfully (' . $reason . ').']);
        }
    }
}
?>