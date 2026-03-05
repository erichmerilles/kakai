<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../frontend/includes/auth_check.php';

header('Content-Type: application/json');

// check permission
if (!hasPermission('att_view')) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized: You do not have permission to view attendance records.']);
    exit;
}

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'No ID provided']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM attendance WHERE attendance_id = ?");
    $stmt->execute([$_GET['id']]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($data) {
        // format date
        $data['time_in_fmt'] = date('Y-m-d\TH:i', strtotime($data['time_in']));
        $data['time_out_fmt'] = $data['time_out'] ? date('Y-m-d\TH:i', strtotime($data['time_out'])) : '';

        echo json_encode(['success' => true, 'data' => $data]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Record not found']);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
