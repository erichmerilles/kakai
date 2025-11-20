<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
header('Content-Type: application/json');

// Ensure User is Admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$attendance_id = intval($input['attendance_id'] ?? 0);
$action = $input['action'] ?? ''; 

if (!$attendance_id || !in_array($action, ['approve', 'reject'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request parameters.']);
    exit;
}

$newStatus = ($action === 'approve') ? 'Approved' : 'Rejected';

try {
    $stmt = $conn->prepare("UPDATE attendance SET status = ? WHERE attendance_id = ?");
    $stmt->bind_param("si", $newStatus, $attendance_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => "Attendance marked as $newStatus."]);
    } else {
        throw new Exception("Failed to update database.");
    }
} catch (Exception $e) {
    error_log("Attendance update error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
}
?>