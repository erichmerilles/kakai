<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// set active module
$activeModule = 'employee';

// role validation
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$my_id = $_SESSION['employee_id'];

// fetch payslips for the logged-in employee
$payslips = [];
try {
    $stmt = $pdo->prepare("
        SELECT pr.payroll_id, pr.start_date, pr.end_date, pe.net_pay 
        FROM payroll_entries pe
        JOIN payroll_runs pr ON pe.payroll_id = pr.payroll_id
        WHERE pe.employee_id = ? AND pr.is_published = 1
        ORDER BY pr.created_at DESC
    ");
    $stmt->execute([$my_id]);
    $payslips = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <title>My Payslips | KakaiOne</title>
    <?php include '../includes/links.php'; ?>
</head>

<body class="bg-light">

    <?php include '../includes/sidebar.php'; ?>

    <main id="main-content" style="margin-left: 250px; padding: 25px;">
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-1">
                        <i class="bi bi-envelope-paper me-2 text-warning"></i>My Payslips
                    </h3>
                    <p class="text-muted mb-0">View and print your official distributed salary records.</p>
                </div>
            </div>

            <div class="row">
                <?php if (empty($payslips)): ?>
                    <div class="col-12">
                        <div class="card shadow-sm border-0 text-center py-5">
                            <i class="bi bi-inbox text-muted display-4 mb-3 opacity-25"></i>
                            <h5 class="text-muted">No payslips available yet.</h5>
                            <p class="small text-muted">Payslips will appear here once HR distributes them.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($payslips as $slip): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card shadow-sm border-0 h-100" style="border-top: 4px solid #212529 !important;">
                                <div class="card-body p-4 text-center">
                                    <div class="mb-3">
                                        <i class="bi bi-cash-stack text-success" style="font-size: 3rem; opacity: 0.8;"></i>
                                    </div>
                                    <h6 class="text-muted text-uppercase fw-bold small mb-1">Pay Period</h6>
                                    <div class="fw-bold text-dark mb-3">
                                        <?= date('M d', strtotime($slip['start_date'])) ?> — <?= date('M d, Y', strtotime($slip['end_date'])) ?>
                                    </div>

                                    <div class="bg-light rounded p-2 mb-4">
                                        <span class="small text-muted d-block">Net Take Home</span>
                                        <span class="fw-bold fs-5 text-primary">₱<?= number_format($slip['net_pay'], 2) ?></span>
                                    </div>

                                    <a href="../../backend/payroll/payslip.php?payroll_id=<?= $slip['payroll_id'] ?>&employee_id=<?= $my_id ?>"
                                        target="_blank"
                                        class="btn btn-dark w-100 fw-bold rounded-pill shadow-sm">
                                        <i class="bi bi-printer me-1"></i> View & Print Slip
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>

</html>