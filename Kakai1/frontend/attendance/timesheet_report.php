<?php
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// set active module
$activeModule = 'employee';

// role validation
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

// check permissions
requirePermission('att_view');
$canGeneratePayroll = hasPermission('payroll_generate');

// default date range
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-6 days'));

// validation for date inputs
if (strtotime($endDate) < strtotime($startDate)) {
    $endDate = $startDate;
}

$reportData = [];

// rates initialization
$hourlyRate = 0.00;
$sundayRate = 0.00;
$weekdayFull = 0.00;
$sundayFull = 0.00;
$otMultiplier = 1.25; // overtime pay

try {
    // fetch settings
    $stmtSettings = $pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('weekday_rate', 'sunday_rate', 'weekday_full', 'sunday_full')");
    $settings = $stmtSettings->fetchAll(PDO::FETCH_KEY_PAIR);

    // set regular hourly rate
    $hourlyRate = isset($settings['weekday_rate']) ? (float)$settings['weekday_rate'] : 0.00;
    // set sunday hourly rate
    $sundayRate = isset($settings['sunday_rate']) ? (float)$settings['sunday_rate'] : ($hourlyRate * 1.30);

    // set full shift rates
    $weekdayFull = isset($settings['weekday_full']) ? (float)$settings['weekday_full'] : ($hourlyRate * 10);
    $sundayFull = isset($settings['sunday_full']) ? (float)$settings['sunday_full'] : ($sundayRate * 10);
} catch (PDOException $e) {
    // handle error
}

try {
    // fetch attendance logs
    $stmt = $pdo->prepare("
        SELECT e.employee_id, e.first_name, e.last_name, 
               a.*, DATE(a.time_in) as log_date
        FROM employees e
        JOIN attendance a ON e.employee_id = a.employee_id
        WHERE DATE(a.time_in) BETWEEN ? AND ?
        ORDER BY e.first_name ASC, a.time_in ASC
    ");
    $stmt->execute([$startDate, $endDate]);
    $rawLogs = $stmt->fetchAll();

    // group data by employee
    foreach ($rawLogs as $row) {
        $empId = $row['employee_id'];

        // initialize employee data
        if (!isset($reportData[$empId])) {
            $reportData[$empId] = [
                'name' => $row['first_name'] . ' ' . $row['last_name'],
                'regular_hours' => 0,
                'sunday_hours' => 0,
                'ot_hours' => 0,
                'total_hours' => 0,
                'days_present' => 0,
                'lates' => 0,
                'est_gross' => 0,
                'daily_logs' => []
            ];
        }

        $logDate = $row['log_date'];

        // fetch exact hours from db
        $hours = (float)$row['total_hours'];
        $approvedOT = isset($row['approved_overtime']) ? (float)$row['approved_overtime'] : 0;

        $dailyPay = 0;

        // identify day of week
        if (date('w', strtotime($logDate)) == 0) {

            // 1. calculate sunday pay
            if ($hours >= 10) {
                $regPay = $sundayFull; // flat rate for sunday shift
            } else {
                $regPay = $hours * $sundayRate; // prorated sunday pay
            }

            // calculate sunday overtime pay
            $otPay = $approvedOT * ($sundayRate * $otMultiplier);

            // add to daily total
            $dailyPay = $regPay + $otPay;
            $reportData[$empId]['sunday_hours'] += $hours;
        } else {

            // calculate weekday pay
            if ($hours >= 10) {
                $regPay = $weekdayFull; // flat rate for regular shift
            } else {
                $regPay = $hours * $hourlyRate; // prorated weekday pay
            }

            // calculate weekday overtime pay
            $otPay = $approvedOT * ($hourlyRate * $otMultiplier);

            // add to daily total
            $dailyPay = $regPay + $otPay;
            $reportData[$empId]['regular_hours'] += $hours;
        }

        // total daily pay
        $reportData[$empId]['est_gross'] += $dailyPay;

        // fetch stats for summary
        $reportData[$empId]['ot_hours'] += $approvedOT;
        $reportData[$empId]['total_hours'] += ($hours + $approvedOT);
        $reportData[$empId]['days_present']++;

        if (strtolower($row['status']) === 'late') {
            $reportData[$empId]['lates']++;
        }

        // store daily breakdown
        $reportData[$empId]['daily_logs'][] = [
            'date' => date('M d, Y', strtotime($logDate)),
            'day_type' => (date('w', strtotime($logDate)) == 0) ? 'Sunday' : 'Weekday',
            'time_in' => !empty($row['time_in']) ? date('h:i A', strtotime($row['time_in'])) : '--:--',
            'time_out' => !empty($row['time_out']) ? date('h:i A', strtotime($row['time_out'])) : '--:--',
            'reg_hours' => $hours,
            'ot_hours' => $approvedOT,
            'daily_pay' => $dailyPay
        ];
    }
} catch (PDOException $e) {
    $errorMsg = "Error fetching data: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timesheet Report | KakaiOne</title>
    <?php include '../includes/links.php'; ?>
    <style>
        @media print {

            #sidebar,
            .filter-card,
            .btn,
            .alert {
                display: none !important;
            }

            #main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            body {
                background-color: #fff !important;
            }

            .card {
                border: none !important;
                box-shadow: none !important;
            }

            .card-header {
                background-color: transparent !important;
                color: #000 !important;
                border-bottom: 2px solid #000 !important;
                padding-left: 0 !important;
            }

            .print-header {
                display: block !important;
                text-align: center;
                margin-bottom: 20px;
            }

            .action-col {
                display: none !important;
            }
        }

        .print-header {
            display: none;
        }

        .clickable-badge {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .clickable-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 4px 6px rgba(13, 110, 253, 0.3);
        }
    </style>
</head>

<body class="bg-light">

    <?php include '../includes/sidebar.php'; ?>

    <main id="main-content" style="margin-left: 250px; padding: 25px; transition: margin-left 0.3s;">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-1">
                        <i class="bi bi-file-earmark-spreadsheet-fill me-2 text-warning"></i>Timesheet Reports
                    </h3>
                    <p class="text-muted mb-0">Generate exact attendance hours, approved OT, and estimated pay.</p>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-4 filter-card">
                <div class="card-body">
                    <form method="GET" class="row align-items-end g-3" id="filterForm">
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">Start Date</label>
                            <input type="date" name="start_date" id="startDate" class="form-control" value="<?= $startDate ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold small text-muted">End Date</label>
                            <input type="date" name="end_date" id="endDate" class="form-control" value="<?= $endDate ?>" required>
                            <div id="dateError" class="invalid-feedback" style="display:none;">End date cannot be before start date.</div>
                        </div>
                        <div class="col-md-4 d-flex gap-2">
                            <button type="submit" class="btn btn-warning fw-bold text-dark w-100" id="generateBtn">
                                <i class="bi bi-funnel"></i> Generate Summary
                            </button>
                            <button type="button" class="btn btn-secondary w-100" onclick="window.print()">
                                <i class="bi bi-printer"></i> Print Report
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="print-header">
                    <h2 class="fw-bold mb-1">KakaiOne System</h2>
                    <h5>Timesheet Summary Report</h5>
                    <p>Period: <strong><?= date('M d, Y', strtotime($startDate)) ?></strong> to <strong><?= date('M d, Y', strtotime($endDate)) ?></strong></p>
                </div>

                <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold"><i class="bi bi-table me-2"></i>Total Payroll Data</span>
                    <div>
                        <span class="badge bg-light text-dark fs-6 me-2 border">Reg: ₱<?= number_format($hourlyRate, 2) ?>/hr | Full: ₱<?= number_format($weekdayFull, 2) ?></span>
                        <span class="badge bg-warning text-dark fs-6">Sun: ₱<?= number_format($sundayRate, 2) ?>/hr | Full: ₱<?= number_format($sundayFull, 2) ?></span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Employee Name</th>
                                    <th class="text-center">Days Worked</th>
                                    <th class="text-center">Late</th>
                                    <th class="text-center">Reg. Hrs</th>
                                    <th class="text-center text-warning fw-bold">Sun. Hrs</th>
                                    <th class="text-center text-primary fw-bold">Apprv. OT</th>
                                    <th class="text-center">Total Hrs</th>
                                    <th class="text-end pe-4">Estimated Gross Pay</th>
                                    <?php if ($canGeneratePayroll): ?>
                                        <th class="text-center pe-4 action-col">Action</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($reportData)): ?>
                                    <?php
                                    $grandTotalPay = 0;
                                    foreach ($reportData as $empId => $data):
                                        $grandTotalPay += $data['est_gross'];
                                    ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($data['name']) ?></td>

                                            <td class="text-center">
                                                <button class="btn btn-sm btn-primary rounded-pill px-3 py-1 fw-bold clickable-badge shadow-sm" onclick="viewDailySummary(<?= $empId ?>)" title="View Daily Breakdown">
                                                    <i class="bi bi-eye-fill me-1"></i> <?= $data['days_present'] ?> Days
                                                </button>
                                            </td>

                                            <td class="text-center">
                                                <?php if ($data['lates'] > 0): ?>
                                                    <span class="badge bg-danger fs-6"><?= $data['lates'] ?></span>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>

                                            <td class="text-center fw-medium"><?= number_format($data['regular_hours'], 2) ?></td>

                                            <td class="text-center fw-bold text-warning bg-warning bg-opacity-10 border-start border-end">
                                                <?= number_format($data['sunday_hours'], 2) ?>
                                            </td>

                                            <td class="text-center fw-bold text-primary">
                                                <?= number_format($data['ot_hours'], 2) ?>
                                            </td>

                                            <td class="text-center fw-bold fs-5 text-dark"><?= number_format($data['total_hours'], 2) ?></td>

                                            <td class="text-end pe-4 fw-bold text-success fs-5">₱<?= number_format($data['est_gross'], 2) ?></td>

                                            <?php if ($canGeneratePayroll): ?>
                                                <td class="text-center pe-4 action-col">
                                                    <a href="../payroll/payroll_module.php?auto_gen=1&start=<?= $startDate ?>&end=<?= $endDate ?>&emp_id=<?= $empId ?>" class="btn btn-sm btn-success fw-bold shadow-sm" title="Generate Payroll for <?= htmlspecialchars($data['name']) ?>">
                                                        <i class="bi bi-gear-wide-connected me-1"></i> Generate
                                                    </a>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-dark text-white fw-bold">
                                        <td colspan="7" class="text-end ps-4">GRAND TOTAL ESTIMATED PAYOUT:</td>
                                        <td class="text-end pe-4 fs-5 text-warning">₱<?= number_format($grandTotalPay, 2) ?></td>
                                        <?php if ($canGeneratePayroll): ?>
                                            <td class="action-col"></td>
                                        <?php endif; ?>
                                    </tr>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?= $canGeneratePayroll ? '9' : '8' ?>" class="text-center py-5 text-muted">
                                            <i class="bi bi-file-earmark-x display-6 d-block mb-3 opacity-25"></i>
                                            No attendance records found for this date range.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="alert alert-info mt-4 border-0 shadow-sm d-flex align-items-center">
                <i class="bi bi-info-circle-fill fs-2 me-3 text-info"></i>
                <div class="small">
                    <strong>Notes:</strong><br>
                    1. <b>Strict 7:00 AM Start:</b> Clock-ins before 7:00 AM are counted starting exactly at 7:00 AM.<br>
                    2. <b>4:00 and 5:00 PM Cap:</b> Regular hours are capped at 5:00 PM. Sundays are capped at 4:00 PM.<br>
                    3. <b>Admin-Approved Overtime:</b> Any time out after 5:00 PM is placed in pending and must be approved. Approved OT is calculated with a standard 25% OT pay.
                </div>
            </div>

        </div>
    </main>

    <div class="modal fade" id="dailySummaryModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content shadow">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold" id="modalEmpName"><i class="bi bi-calendar-check me-2"></i>Daily Breakdown</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0" id="modalDailyTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Log Times</th>
                                    <th class="text-center">Reg. Hrs</th>
                                    <th class="text-center text-primary">OT Hrs</th>
                                    <th class="text-end pe-4">Daily Gross Pay</th>
                                </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light border-top-0 py-2">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const reportData = <?= json_encode($reportData) ?>;
    </script>

    <script>
        // validation for date inputs
        const startDateInput = document.getElementById('startDate');
        const endDateInput = document.getElementById('endDate');
        const form = document.getElementById('filterForm');
        const errorMsg = document.getElementById('dateError');
        const submitBtn = document.getElementById('generateBtn');

        function validateDates() {
            const start = new Date(startDateInput.value);
            const end = new Date(endDateInput.value);

            if (end < start) {
                endDateInput.classList.add('is-invalid');
                errorMsg.style.display = 'block';
                submitBtn.disabled = true;
                endDateInput.min = startDateInput.value;
            } else {
                endDateInput.classList.remove('is-invalid');
                errorMsg.style.display = 'none';
                submitBtn.disabled = false;
                endDateInput.min = startDateInput.value;
            }
        }

        startDateInput.addEventListener('change', validateDates);
        endDateInput.addEventListener('change', validateDates);
        validateDates();

        // view daily summary
        function viewDailySummary(empId) {
            const data = reportData[empId];

            // modal tile with employee name
            document.getElementById('modalEmpName').innerHTML = `<i class="bi bi-person-fill me-2"></i>${data.name} - Daily Breakdown`;

            let tbodyHtml = '';
            let totalGross = 0;
            let totalRegHrs = 0;
            let totalOTHrs = 0;

            // loops for daily logs
            data.daily_logs.forEach(log => {
                let badgeClass = log.day_type === 'Sunday' ? 'bg-warning text-dark' : 'bg-secondary';

                tbodyHtml += `<tr>
                    <td class="ps-4">
                        <span class="fw-bold d-block text-dark">${log.date}</span>
                        <span class="badge ${badgeClass}" style="font-size:0.65rem;">${log.day_type}</span>
                    </td>
                    <td>
                        <small class="text-muted"><i class="bi bi-box-arrow-in-right text-success"></i> ${log.time_in}</small><br>
                        <small class="text-muted"><i class="bi bi-box-arrow-left text-danger"></i> ${log.time_out}</small>
                    </td>
                    <td class="text-center fw-medium">${log.reg_hours.toFixed(2)}</td>
                    <td class="text-center text-primary fw-bold">${log.ot_hours.toFixed(2)}</td>
                    <td class="text-end pe-4 fw-bold text-success">₱${log.daily_pay.toFixed(2)}</td>
                </tr>`;

                totalGross += log.daily_pay;
                totalRegHrs += log.reg_hours;
                totalOTHrs += log.ot_hours;
            });

            // footer summary row
            tbodyHtml += `<tr class="table-dark fw-bold">
                <td colspan="2" class="text-end pe-3">TOTAL SUMMARY:</td>
                <td class="text-center">${totalRegHrs.toFixed(2)}</td>
                <td class="text-center text-primary">${totalOTHrs.toFixed(2)}</td>
                <td class="text-end pe-4 text-warning fs-5">₱${totalGross.toFixed(2)}</td>
            </tr>`;

            // insert into table
            document.querySelector('#modalDailyTable tbody').innerHTML = tbodyHtml;

            // show modal
            const modal = new bootstrap.Modal(document.getElementById('dailySummaryModal'));
            modal.show();
        }
    </script>
</body>

</html>