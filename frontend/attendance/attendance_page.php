<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// role validation
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}
requirePermission('att_view'); // permission check
$activeModule = 'employee';
$filterDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

// fetch attendance logs
$attendanceLogs = [];
try {
    // join with employees table to get names
    $stmt = $pdo->prepare("
        SELECT a.attendance_id, e.first_name, e.last_name, e.employee_code, 
               a.time_in, a.time_out, a.status, a.total_hours
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        WHERE DATE(a.time_in) = ?
        ORDER BY a.time_in DESC
    ");
    $stmt->execute([$filterDate]);
    $attendanceLogs = $stmt->fetchAll();
} catch (PDOException $e) {
    $errorMsg = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Log | KakaiOne</title>
    <?php include '../includes/links.php'; ?>

</head>

<body class="bg-light">

    <?php include '../includes/sidebar.php'; ?>

    <main id="main-content" style="margin-left: 250px; padding: 25px; transition: margin-left 0.3s;">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold text-dark"><i class="bi bi-calendar-check-fill me-2 text-warning"></i>Attendance Log</h3>
                    <p class="text-muted mb-0">Records for <strong><?= date('F j, Y', strtotime($filterDate)) ?></strong></p>
                </div>

                <form method="GET" class="d-flex gap-2">
                    <input type="date" name="date" class="form-control" value="<?= $filterDate ?>" onchange="this.form.submit()">
                    <button type="submit" class="btn btn-warning text-dark fw-semibold"><i class="bi bi-search"></i></button>
                    <?php if ($filterDate !== date('Y-m-d')): ?>
                        <a href="attendance_page.php" class="btn btn-secondary" title="Reset to Today"><i class="bi bi-arrow-counterclockwise"></i></a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-secondary">
                                <tr>
                                    <th class="ps-4 py-3">Employee</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Total Hours</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($attendanceLogs)): ?>
                                    <?php foreach ($attendanceLogs as $log): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center fw-bold me-3" style="width: 40px; height: 40px;">
                                                        <?= strtoupper(substr($log['first_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0 fw-semibold"><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($log['employee_code']) ?></small>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <span class="fw-medium text-dark">
                                                    <?= date('h:i A', strtotime($log['time_in'])) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?php if ($log['time_out']): ?>
                                                    <span class="fw-medium text-dark">
                                                        <?= date('h:i A', strtotime($log['time_out'])) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary text-light">-- : --</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?= $log['total_hours'] ? number_format($log['total_hours'], 2) . ' hrs' : '-' ?>
                                            </td>

                                            <td>
                                                <?php
                                                $statusClass = 'secondary';
                                                if ($log['status'] === 'Present') $statusClass = 'success';
                                                elseif ($log['status'] === 'Late') $statusClass = 'warning';
                                                elseif ($log['status'] === 'Absent') $statusClass = 'danger';
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?> rounded-pill px-3">
                                                    <?= htmlspecialchars($log['status']) ?>
                                                </span>
                                            </td>

                                            <td class="text-end pe-4">
                                                <?php if (hasPermission('att_approve')): ?>
                                                    <button class="btn btn-sm btn-outline-secondary" title="Edit Record">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-calendar-x display-6 d-block mb-3 opacity-50"></i>
                                            No attendance records found for this date.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

</body>

</html>