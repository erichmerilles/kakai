<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// role validation
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

$employee_id = $_SESSION['employee_id'];

// date filtering
$filterInput = isset($_GET['month']) ? $_GET['month'] : date('Y-m');
$year = date('Y', strtotime($filterInput));
$month = date('m', strtotime($filterInput));

// fetch attendance
$logs = [];
$stats = ['present' => 0, 'late' => 0, 'absent' => 0];

try {
    $stmt = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE employee_id = ? 
        AND MONTH(time_in) = ? 
        AND YEAR(time_in) = ?
        ORDER BY time_in DESC
    ");
    $stmt->execute([$employee_id, $month, $year]);
    $logs = $stmt->fetchAll();

    // calculate stats
    foreach ($logs as $log) {
        $status = strtolower($log['status']);
        if (isset($stats[$status])) {
            $stats[$status]++;
        }
    }
} catch (PDOException $e) {
    // handle error
}

// active module
$activeModule = 'dashboard';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance History | KakaiOne</title>
    <?php include '../includes/links.php'; ?>
</head>

<body>

    <?php include '../includes/sidebar.php'; ?>

    <main id="main-content" class="main-content-wrapper">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold mb-0">My Attendance</h3>
                    <p class="text-muted">History for <strong><?= date('F Y', strtotime($filterInput)); ?></strong></p>
                </div>
                <div>
                    <form method="GET" class="d-flex align-items-center gap-2">
                        <label class="fw-bold text-secondary">Select Month:</label>
                        <input type="month" name="month" class="form-control" value="<?= $filterInput ?>" onchange="this.form.submit()">
                    </form>
                </div>
            </div>

            <div class="row g-3 mb-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-3 text-center" style="border-bottom: 4px solid #198754 !important;">
                        <h6 class="text-muted text-uppercase small ls-1">Present</h6>
                        <h2 class="fw-bold text-success mb-0"><?= $stats['present'] ?></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-3 text-center" style="border-bottom: 4px solid #ffc107 !important;">
                        <h6 class="text-muted text-uppercase small ls-1">Late</h6>
                        <h2 class="fw-bold text-warning mb-0"><?= $stats['late'] ?></h2>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm p-3 text-center" style="border-bottom: 4px solid #dc3545 !important;">
                        <h6 class="text-muted text-uppercase small ls-1">Absent</h6>
                        <h2 class="fw-bold text-danger mb-0"><?= $stats['absent'] ?></h2>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4 py-3">Date</th>
                                    <th>Day</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Total Hours</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($logs)): ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark">
                                                <?= date('M d, Y', strtotime($log['time_in'])); ?>
                                            </td>
                                            <td class="text-muted small text-uppercase">
                                                <?= date('l', strtotime($log['time_in'])); ?>
                                            </td>
                                            <td class="text-success fw-bold">
                                                <?= date('h:i A', strtotime($log['time_in'])); ?>
                                            </td>
                                            <td class="text-danger">
                                                <?= $log['time_out'] ? date('h:i A', strtotime($log['time_out'])) : '--:--'; ?>
                                            </td>
                                            <td>
                                                <?= $log['total_hours'] ? number_format($log['total_hours'], 2) . ' hrs' : '-'; ?>
                                            </td>
                                            <td>
                                                <?php
                                                $badge = 'secondary';
                                                if ($log['status'] == 'Present') $badge = 'success';
                                                if ($log['status'] == 'Late') $badge = 'warning';
                                                if ($log['status'] == 'Absent') $badge = 'danger';
                                                ?>
                                                <span class="badge bg-<?= $badge; ?> px-3 rounded-pill"><?= $log['status']; ?></span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-calendar-x display-4 d-block mb-3 opacity-25"></i>
                                            No attendance records found for this month.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- <div class="mt-3">
                <a href="../dashboard/employee_dashboard.php" class="btn btn-secondary btn-sm">
                    <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div> -->

        </div>
    </main>

</body>

</html>