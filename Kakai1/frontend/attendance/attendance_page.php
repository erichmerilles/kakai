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
$filterDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$attendanceLogs = [];
$dailyStats = [
    'present' => 0,
    'late' => 0,
    'absent' => 0,
    'total_hours' => 0
];

try {
    // fetch latest attendance
    $stmt = $pdo->prepare("
        SELECT e.employee_id, e.first_name, e.last_name, e.employee_code, 
               a.attendance_id, a.time_in, a.time_out, a.status, a.total_hours
        FROM employees e
        LEFT JOIN attendance a ON e.employee_id = a.employee_id AND DATE(a.time_in) = ?
        WHERE e.status = 'Active' AND e.role = 'Employee'
        ORDER BY e.first_name ASC, a.time_in DESC
    ");
    $stmt->execute([$filterDate]);
    $rawLogs = $stmt->fetchAll();

    $seenEmployees = [];

    // calculate daily stats
    foreach ($rawLogs as $log) {

        // look for duplicate emp id
        if (in_array($log['employee_code'], $seenEmployees)) {
            continue;
        }
        $seenEmployees[] = $log['employee_code'];

        if (empty($log['attendance_id'])) {
            // no attendance means absent
            $log['status'] = 'Absent / No Record';
            $dailyStats['absent']++;
        } else {
            $status = strtolower($log['status']);
            if ($status === 'present') $dailyStats['present']++;
            elseif ($status === 'late') $dailyStats['late']++;
            elseif ($status === 'absent') $dailyStats['absent']++;

            $dailyStats['total_hours'] += (float)$log['total_hours'];
        }

        $attendanceLogs[] = $log;
    }
} catch (PDOException $e) {
    $errorMsg = "Error fetching data: " . $e->getMessage();
}

// fetch pending overtime requests
try {
    $stmt = $pdo->query("
        SELECT a.attendance_id, e.first_name, e.last_name, a.time_in, a.time_out, a.pending_overtime 
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        WHERE a.pending_overtime > 0
        ORDER BY a.time_in ASC
    ");
    $pendingOTRequests = $stmt->fetchAll();
} catch (PDOException $e) {
    // handle error
}

// fetch all active employees for manual log
$activeEmployeesList = [];
try {
    $empStmt = $pdo->query("SELECT employee_id, first_name, last_name FROM employees WHERE status = 'Active' AND role = 'Employee' ORDER BY first_name ASC");
    $activeEmployeesList = $empStmt->fetchAll();
} catch (PDOException $e) {
    // handle error
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Log | KakaiOne</title>
    <?php include '../includes/links.php'; ?>
    <style>
        .stat-card {
            border-left: 4px solid;
            transition: transform 0.2s;
            border-radius: 8px;
        }

        .stat-card:hover {
            transform: translateY(-3px);
        }

        .border-left-primary {
            border-left-color: #0d6efd !important;
        }

        .border-left-success {
            border-left-color: #198754 !important;
        }

        .border-left-warning {
            border-left-color: #ffc107 !important;
        }

        .border-left-danger {
            border-left-color: #dc3545 !important;
        }

        @media print {

            #sidebar,
            .btn,
            .input-group,
            .filter-form,
            .action-cols {
                display: none !important;
            }

            #main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
        }
    </style>
</head>

<body class="bg-light">

    <?php include '../includes/sidebar.php'; ?>

    <main id="main-content" style="margin-left: 250px; padding: 25px; transition: margin-left 0.3s;">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-end mb-4">
                <div>
                    <h3 class="fw-bold text-dark mb-1"><i class="bi bi-calendar-check-fill me-2 text-warning"></i>Daily Attendance</h3>
                    <p class="text-muted mb-0">Records for <strong><?= date('F j, Y', strtotime($filterDate)) ?></strong></p>
                </div>

                <div class="d-flex gap-2 align-items-center filter-form">
                    <form method="GET" class="d-flex gap-2 mb-0 me-2 border-end pe-3">
                        <input type="date" name="date" class="form-control shadow-sm" value="<?= $filterDate ?>" onchange="this.form.submit()">
                        <?php if ($filterDate !== date('Y-m-d')): ?>
                            <a href="attendance_page.php" class="btn btn-secondary shadow-sm" title="Reset to Today">
                                <i class="bi bi-arrow-counterclockwise"></i> Today
                            </a>
                        <?php endif; ?>
                    </form>

                    <?php if (hasPermission('att_approve')): ?>
                        <button type="button" class="btn btn-primary shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#manualLogModal">
                            <i class="bi bi-plus-circle me-1"></i> Manual Log
                        </button>
                    <?php endif; ?>

                    <button onclick="window.print()" class="btn btn-secondary shadow-sm">
                        <i class="bi bi-printer"></i> Print
                    </button>
                </div>
            </div>

            <div class="row mb-4">
                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card border-0 shadow-sm border-left-success h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-success text-uppercase mb-1">On Time</div>
                                    <div class="h4 mb-0 fw-bold text-dark"><?= $dailyStats['present']; ?></div>
                                </div>
                                <div class="col-auto"><i class="bi bi-check-circle fa-2x text-success opacity-50 fs-1"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card border-0 shadow-sm border-left-warning h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-warning text-uppercase mb-1">Late Arrivals</div>
                                    <div class="h4 mb-0 fw-bold text-dark"><?= $dailyStats['late']; ?></div>
                                </div>
                                <div class="col-auto"><i class="bi bi-clock-history fa-2x text-warning opacity-50 fs-1"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card border-0 shadow-sm border-left-danger h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-danger text-uppercase mb-1">Missing / Absent</div>
                                    <div class="h4 mb-0 fw-bold text-dark"><?= $dailyStats['absent']; ?></div>
                                </div>
                                <div class="col-auto"><i class="bi bi-x-circle fa-2x text-danger opacity-50 fs-1"></i></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-3">
                    <div class="card stat-card border-0 shadow-sm border-left-primary h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Hours Logged</div>
                                    <div class="h4 mb-0 fw-bold text-dark"><?= number_format($dailyStats['total_hours'], 2); ?> <small class="fs-6 text-muted">hrs</small></div>
                                </div>
                                <div class="col-auto"><i class="bi bi-stopwatch fa-2x text-primary opacity-50 fs-1"></i></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if (!empty($pendingOTRequests) && hasPermission('att_approve')): ?>
                <div class="card shadow-sm border-warning mb-4">
                    <div class="card-header bg-warning text-dark d-flex justify-content-between align-items-center py-3">
                        <span class="fw-bold"><i class="bi bi-clock-history me-2"></i>Pending Overtime Requests</span>
                        <span class="badge bg-dark rounded-pill"><?= count($pendingOTRequests) ?> Pending</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Employee</th>
                                        <th>Date</th>
                                        <th>Time Out (Actual)</th>
                                        <th class="text-center text-danger fw-bold">Excess Time</th>
                                        <th class="text-end pe-4 action-cols">Admin Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingOTRequests as $ot): ?>
                                        <tr>
                                            <td class="ps-4 fw-bold text-dark"><?= htmlspecialchars($ot['first_name'] . ' ' . $ot['last_name']) ?></td>
                                            <td><?= date('M d, Y', strtotime($ot['time_in'])) ?></td>
                                            <td><span class="text-danger fw-bold"><?= date('h:i A', strtotime($ot['time_out'])) ?></span></td>
                                            <td class="text-center">
                                                <span class="badge bg-danger fs-6"><?= number_format($ot['pending_overtime'], 2) ?> hrs</span>
                                            </td>
                                            <td class="text-end pe-4 action-cols">
                                                <button class="btn btn-sm btn-success shadow-sm" onclick="approveOT(<?= $ot['attendance_id'] ?>)" title="Approve as Paid OT">
                                                    <i class="bi bi-check-circle me-1"></i> Approve
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger shadow-sm ms-1" onclick="declineOT(<?= $ot['attendance_id'] ?>)" title="Decline (Cap at 5 PM)">
                                                    <i class="bi bi-x-circle me-1"></i> Decline
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="card shadow-sm border-0 mb-4">
                <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
                    <span class="fw-bold"><i class="bi bi-list-ul me-2"></i>Daily Roster</span>
                    <div class="input-group input-group-sm w-25">
                        <input type="text" id="attendanceSearch" class="form-control" placeholder="Search employee...">
                        <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-striped align-middle mb-0" id="attendanceTable">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-4">Employee</th>
                                    <th>Time In</th>
                                    <th>Time Out</th>
                                    <th>Total Hours</th>
                                    <th>Status</th>
                                    <th class="text-end pe-4 action-cols">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($attendanceLogs)): ?>
                                    <?php foreach ($attendanceLogs as $log): ?>
                                        <tr>
                                            <td class="ps-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold me-3" style="width: 40px; height: 40px;">
                                                        <?= strtoupper(substr($log['first_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-0 fw-bold text-dark"><?= htmlspecialchars($log['first_name'] . ' ' . $log['last_name']) ?></h6>
                                                        <small class="text-muted"><?= htmlspecialchars($log['employee_code']) ?></small>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <?php if (!empty($log['time_in'])): ?>
                                                    <span class="fw-bold text-dark">
                                                        <?= date('h:i A', strtotime($log['time_in'])) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-- : --</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <?php if (!empty($log['time_out'])): ?>
                                                    <span class="fw-bold text-dark">
                                                        <?= date('h:i A', strtotime($log['time_out'])) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">-- : --</span>
                                                <?php endif; ?>
                                            </td>

                                            <td>
                                                <span class="fw-bold text-dark"><?= !empty($log['total_hours']) ? number_format($log['total_hours'], 2) . ' hrs' : '-' ?></span>
                                            </td>

                                            <td>
                                                <?php
                                                $statusClass = 'secondary';
                                                $displayStatus = htmlspecialchars($log['status']);

                                                if ($log['status'] === 'Present') {
                                                    $statusClass = 'success';
                                                    $displayStatus = 'Present / On-Time';
                                                } elseif ($log['status'] === 'Late') {
                                                    $statusClass = 'warning text-dark';
                                                } elseif ($log['status'] === 'Absent') {
                                                    $statusClass = 'danger';
                                                } elseif ($log['status'] === 'Absent / No Record') {
                                                    $statusClass = 'danger';
                                                }
                                                ?>
                                                <span class="badge bg-<?= $statusClass ?>">
                                                    <?= $displayStatus ?>
                                                </span>
                                            </td>

                                            <td class="text-end pe-4 action-cols">
                                                <?php if (hasPermission('att_approve') && !empty($log['attendance_id'])): ?>
                                                    <button class="btn btn-sm btn-secondary" onclick="editAttendance(<?= $log['attendance_id'] ?>)" title="Edit Record">
                                                        <i class="bi bi-pencil-square"></i> Edit
                                                    </button>
                                                <?php elseif (empty($log['attendance_id'])): ?>
                                                    <span class="text-muted small fst-italic">No record</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-calendar-x display-6 d-block mb-3 opacity-25"></i>
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

    <div class="modal fade" id="editAttendanceModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content shadow">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square me-2"></i>Edit Attendance</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editAttendanceForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="attendance_id" id="edit_attendance_id">

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Time In</label>
                            <input type="datetime-local" name="time_in" id="edit_time_in" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Time Out</label>
                            <input type="datetime-local" name="time_out" id="edit_time_out" class="form-control">
                            <small class="text-muted mt-1 d-block">Clear this if the employee hasn't clocked out yet.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">Status</label>
                            <select name="status" id="edit_status" class="form-select fw-bold">
                                <option value="Present">Present (On-Time)</option>
                                <option value="Late">Late</option>
                                <option value="Absent">Absent</option>
                                <option value="Half Day">Half Day</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="manualLogModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content shadow">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-clipboard-plus me-2"></i>Add Manual Log</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="manualLogForm">
                    <div class="modal-body p-4">
                        <div class="alert alert-info py-2 small mb-4">
                            <i class="bi bi-info-circle-fill me-2"></i>Use this to manually log attendance if an employee forgot to clock in.
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">Select Employee <span class="text-danger">*</span></label>
                            <select name="employee_id" class="form-select" required>
                                <option value="" disabled selected>-- Choose Employee --</option>
                                <?php foreach ($activeEmployeesList as $emp): ?>
                                    <option value="<?= $emp['employee_id'] ?>"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">Time In <span class="text-danger">*</span></label>
                            <input type="datetime-local" name="time_in" class="form-control" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">Time Out</label>
                            <input type="datetime-local" name="time_out" class="form-control">
                            <small class="text-muted d-block mt-1">Leave blank if the shift is not yet over.</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small text-dark">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select fw-bold" required>
                                <option value="Present">Present (On-Time)</option>
                                <option value="Late">Late</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4">Submit Log</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // search filter
        document.getElementById('attendanceSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#attendanceTable tbody tr');

            rows.forEach(row => {
                if (row.cells.length === 1) return; // skip empty row
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });

        // open modal and fetch data
        async function editAttendance(id) {
            try {
                const res = await fetch(`../../backend/attendance/get_attendance.php?id=${id}`);
                const json = await res.json();

                if (json.success) {
                    const data = json.data;
                    document.getElementById('edit_attendance_id').value = data.attendance_id;

                    // value format
                    document.getElementById('edit_time_in').value = data.time_in_fmt;
                    document.getElementById('edit_time_out').value = data.time_out_fmt;
                    document.getElementById('edit_status').value = data.status;

                    const modal = new bootstrap.Modal(document.getElementById('editAttendanceModal'));
                    modal.show();
                } else {
                    Swal.fire('Error', json.message, 'error');
                }
            } catch (error) {
                console.error(error);
                Swal.fire('Error', 'Failed to fetch record details.', 'error');
            }
        }

        // handle form submission
        document.getElementById('editAttendanceForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            try {
                const res = await fetch('../../backend/attendance/update_attendance.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Updated!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            } catch (error) {
                console.error(error);
                Swal.fire('Error', 'Something went wrong.', 'error');
            }
        });

        // handle manual log submission
        document.getElementById('manualLogForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);

            try {
                const res = await fetch('../../backend/attendance/manual_log.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();

                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Log Added!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            } catch (error) {
                console.error(error);
                Swal.fire('Error', 'Something went wrong processing the manual log.', 'error');
            }
        });

        // overtime approval logic
        function approveOT(id) {
            Swal.fire({
                title: 'Approve Overtime?',
                text: "This will move the excess hours to Approved Overtime.",
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                confirmButtonText: 'Yes, Approve'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const res = await fetch(`../../backend/attendance/approve_ot.php?id=${id}`);
                        const data = await res.json();
                        if (data.success) {
                            Swal.fire({
                                    icon: 'success',
                                    title: 'Approved!',
                                    timer: 1500,
                                    showConfirmButton: false
                                })
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    } catch (e) {
                        Swal.fire('Error', 'Server failed to process.', 'error');
                    }
                }
            });
        }

        function declineOT(id) {
            Swal.fire({
                title: 'Decline Overtime?',
                text: "This will discard the excess hours. The employee's pay will remain capped at 5:00 PM.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, Decline'
            }).then(async (result) => {
                if (result.isConfirmed) {
                    try {
                        const res = await fetch(`../../backend/attendance/decline_ot.php?id=${id}`);
                        const data = await res.json();
                        if (data.success) {
                            Swal.fire({
                                    icon: 'success',
                                    title: 'Declined!',
                                    timer: 1500,
                                    showConfirmButton: false
                                })
                                .then(() => location.reload());
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    } catch (e) {
                        Swal.fire('Error', 'Server failed to process.', 'error');
                    }
                }
            });
        }
    </script>

</body>

</html>