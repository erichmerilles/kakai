<?php
session_start();
date_default_timezone_set('Asia/Manila');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// set active module
$activeModule = 'employee';

// role validation
requirePermission('emp_view');

// fetch employees
try {
    $stmt = $pdo->prepare("
        SELECT employee_id, employee_code, first_name, last_name, position, status, date_hired, contact_number, email
        FROM employees 
        WHERE role = 'Employee' 
        ORDER BY employee_id DESC
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll();
} catch (PDOException $e) {
    $employees = [];
}

// calculate employee stats for KPI cards
$totalEmployees = count($employees);
$activeEmployees = 0;
foreach ($employees as $emp) {
    if (strtolower($emp['status']) === 'active') {
        $activeEmployees++;
    }
}

// daily attendance summary
$attendanceStats = ['on_time' => 0, 'late' => 0, 'absent' => 0];
$today = date('Y-m-d');

try {
    $stmt = $pdo->prepare("
        SELECT a.attendance_id, a.status
        FROM employees e
        LEFT JOIN attendance a ON a.attendance_id = (
            SELECT MAX(attendance_id) 
            FROM attendance sub_a 
            WHERE sub_a.employee_id = e.employee_id AND DATE(sub_a.time_in) = ?
        )
        WHERE e.status = 'Active' AND e.role = 'Employee'
    ");
    $stmt->execute([$today]);
    $dailyRecords = $stmt->fetchAll();

    foreach ($dailyRecords as $record) {
        if (empty($record['attendance_id'])) {
            $attendanceStats['absent']++;
        } else {
            $status = strtolower($record['status']);
            if ($status === 'present') {
                $attendanceStats['on_time']++;
            } elseif ($status === 'late') {
                $attendanceStats['late']++;
            } elseif ($status === 'absent') {
                $attendanceStats['absent']++;
            }
        }
    }
} catch (PDOException $e) {
}

// fetch pending overtime requests
$pendingOTRequests = [];
try {
    $stmt = $pdo->query("
        SELECT a.attendance_id, e.first_name, e.last_name, a.time_in, a.pending_overtime 
        FROM attendance a
        JOIN employees e ON a.employee_id = e.employee_id
        WHERE a.pending_overtime > 0
        ORDER BY a.time_in ASC
    ");
    $pendingOTRequests = $stmt->fetchAll();
} catch (PDOException $e) {
}
$pendingOTCount = count($pendingOTRequests);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KakaiOne | Employee Management</title>
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
            .chart-section {
                display: none !important;
            }

            #main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }

            .col-lg-8 {
                width: 100% !important;
            }
        }
    </style>
</head>

<body class="bg-light">

    <?php include '../includes/sidebar.php'; ?>

    <div id="dashboardContainer">
        <main id="main-content" style="margin-left: 250px; padding: 25px; transition: margin-left 0.3s;">
            <div class="container-fluid">

                <div class="d-flex justify-content-between align-items-end mb-4">
                    <div>
                        <h3 class="fw-bold text-dark mb-1">
                            <i class="bi bi-people-fill me-2 text-warning"></i>Employee Management
                        </h3>
                        <p class="text-muted mb-0">Manage staff records, access control, and overtime approvals.</p>
                    </div>
                    <div class="d-flex gap-2 text-end">
                        <?php if (hasPermission('emp_add')): ?>
                            <button type="button" class="btn btn-warning shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addEmployeeModal">
                                <i class="bi bi-person-plus me-1"></i> Add Employee
                            </button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card border-0 shadow-sm border-left-primary h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Employees</div>
                                        <div class="h4 mb-0 fw-bold text-dark"><?= $totalEmployees; ?></div>
                                    </div>
                                    <div class="col-auto"><i class="bi bi-people fa-2x text-primary opacity-50 fs-1"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card border-0 shadow-sm border-left-success h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs fw-bold text-success text-uppercase mb-1">Active Staff</div>
                                        <div class="h4 mb-0 fw-bold text-dark"><?= $activeEmployees; ?></div>
                                    </div>
                                    <div class="col-auto"><i class="bi bi-person-check fa-2x text-success opacity-50 fs-1"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card border-0 shadow-sm border-left-danger h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs fw-bold text-danger text-uppercase mb-1">Today's Lates</div>
                                        <div class="h4 mb-0 fw-bold text-dark"><?= $attendanceStats['late']; ?></div>
                                    </div>
                                    <div class="col-auto"><i class="bi bi-clock-history fa-2x text-danger opacity-50 fs-1"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card border-0 shadow-sm border-left-warning h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Overtime</div>
                                        <div class="h4 mb-0 fw-bold text-dark"><?= $pendingOTCount; ?></div>
                                    </div>
                                    <div class="col-auto"><i class="bi bi-clock fa-2x text-warning opacity-50 fs-1"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">
                        <div class="card mb-4 shadow-sm border-0">
                            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center py-3">
                                <span class="fw-bold"><i class="bi bi-list-ul me-2"></i>Employee Directory</span>
                                <div class="input-group input-group-sm w-50">
                                    <input type="text" id="employeeSearch" class="form-control" placeholder="Search code, name, position...">
                                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle mb-0" id="employeeTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-4">ID Code</th>
                                                <th>Full Name</th>
                                                <th>Position</th>
                                                <th>Status</th>
                                                <th>Date Hired</th>
                                                <th class="text-end pe-4">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($employees)): ?>
                                                <?php foreach ($employees as $emp): ?>
                                                    <tr>
                                                        <td class="ps-4">
                                                            <span class="badge bg-light text-dark border fw-bold"><?= htmlspecialchars($emp['employee_code'] ?? 'N/A'); ?></span>
                                                        </td>
                                                        <td class="fw-bold text-dark"><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']); ?></td>
                                                        <td><?= htmlspecialchars($emp['position']); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?= (strtolower($emp['status']) === 'active') ? 'success' : 'secondary'; ?>">
                                                                <?= htmlspecialchars($emp['status']); ?>
                                                            </span>
                                                        </td>
                                                        <td class="text-muted small"><?= date('M d, Y', strtotime($emp['date_hired'])); ?></td>
                                                        <td class="text-end pe-4">
                                                            <button class="btn btn-sm btn-info text-white shadow-sm" onclick="viewEmployee(<?= $emp['employee_id']; ?>)" title="View">
                                                                <i class="bi bi-eye"></i>
                                                            </button>

                                                            <?php if (hasPermission('emp_edit')): ?>
                                                                <button class="btn btn-sm btn-warning shadow-sm" onclick="editEmployee(<?= $emp['employee_id']; ?>)" title="Edit">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                            <?php endif; ?>

                                                            <?php if (hasPermission('emp_delete')): ?>
                                                                <button class="btn btn-sm btn-danger shadow-sm" onclick="confirmDeactivate(<?= $emp['employee_id']; ?>)" title="Deactivate">
                                                                    <i class="bi bi-x-circle"></i>
                                                                </button>
                                                            <?php endif; ?>

                                                            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'Admin'): ?>
                                                                <a href="access_control.php?id=<?= $emp['employee_id']; ?>" class="btn btn-sm btn-dark shadow-sm" title="Access Control">
                                                                    <i class="bi bi-shield-lock"></i>
                                                                </a>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="6" class="text-center text-muted py-4">No employees found.</td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-4 chart-section">
                        <div class="card mb-4 shadow-sm border-0">
                            <div class="card-header bg-dark text-white py-3">
                                <span class="fw-bold"><i class="bi bi-pie-chart-fill me-2"></i>Today's Attendance</span>
                            </div>
                            <div class="card-body">
                                <div class="d-flex justify-content-center mb-3">
                                    <canvas id="attendanceChart" style="max-height: 200px;"></canvas>
                                </div>
                                <div class="d-flex justify-content-around text-center border-top pt-3">
                                    <div>
                                        <h5 class="fw-bold text-success mb-0"><?= $attendanceStats['on_time']; ?></h5>
                                        <small class="text-muted">On Time</small>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold text-warning mb-0"><?= $attendanceStats['late']; ?></h5>
                                        <small class="text-muted">Late</small>
                                    </div>
                                    <div>
                                        <h5 class="fw-bold text-danger mb-0"><?= $attendanceStats['absent']; ?></h5>
                                        <small class="text-muted">Absent</small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if (hasPermission('att_approve')): ?>
                            <div class="card mb-4 shadow-sm border-warning">
                                <div class="card-header bg-warning text-dark py-3 d-flex justify-content-between align-items-center">
                                    <span class="fw-bold"><i class="bi bi-clock-history me-2"></i>Pending Overtime</span>
                                    <span class="badge bg-dark rounded-pill"><?= $pendingOTCount ?></span>
                                </div>
                                <div class="card-body p-0">
                                    <ul class="list-group list-group-flush">
                                        <?php if (!empty($pendingOTRequests)): ?>
                                            <?php foreach ($pendingOTRequests as $ot): ?>
                                                <li class="list-group-item d-flex justify-content-between align-items-center p-3">
                                                    <div>
                                                        <h6 class="mb-1 fw-bold text-dark"><?= htmlspecialchars($ot['first_name'] . ' ' . $ot['last_name']) ?></h6>
                                                        <div class="small text-muted mt-1">
                                                            <?= date('M d, Y', strtotime($ot['time_in'])) ?> &bull;
                                                            <strong class="text-danger"><?= number_format($ot['pending_overtime'], 2) ?> hrs excess</strong>
                                                        </div>
                                                    </div>
                                                    <div class="pe-1">
                                                        <a href="../attendance/attendance_page.php" class="btn btn-sm btn-outline-primary">Review <i class="bi bi-arrow-right-short"></i></a>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <li class="list-group-item text-center text-muted py-4">
                                                <i class="bi bi-check2-circle fs-3 d-block text-success opacity-50 mb-2"></i>
                                                No pending overtime.
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <div class="modal fade" id="addEmployeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content shadow">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-person-plus me-2"></i>Add New Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="addEmployeeForm">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">First Name <span class="text-danger">*</span></label>
                                <input type="text" name="first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Last Name <span class="text-danger">*</span></label>
                                <input type="text" name="last_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Email <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Contact Number</label>
                                <input type="text" name="contact_number" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Position <span class="text-danger">*</span></label>
                                <input type="text" name="position" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Status</label>
                                <select name="status" class="form-select">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Hourly Rate (₱) <span class="text-danger">*</span></label>
                                <input type="number" step="0.01" name="daily_rate" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Date Hired <span class="text-danger">*</span></label>
                                <input type="date" name="date_hired" class="form-control" value="<?= date('Y-m-d') ?>" required>
                            </div>
                            <div class="col-12 bg-light p-3 rounded border">
                                <label class="form-label fw-bold">Initial Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control" placeholder="Create temporary password" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning fw-bold text-dark px-4">Save Employee</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="viewEmployeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content shadow">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title fw-bold"><i class="bi bi-person-badge me-2"></i>Employee Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <div class="bg-secondary bg-opacity-25 rounded-circle d-inline-flex align-items-center justify-content-center text-primary mb-2" style="width: 80px; height: 80px; font-size: 2.5rem;">
                        <i class="bi bi-person-fill"></i>
                    </div>
                    <h4 class="fw-bold mb-0 text-dark" id="view_fullname">Loading...</h4>
                    <p class="text-muted mb-2" id="view_position">Loading...</p>
                    <div class="mb-3"><span class="badge bg-success px-3 py-1 rounded-pill" id="view_status">Active</span></div>

                    <div class="bg-light rounded p-3 border text-start">
                        <div class="row g-3">
                            <div class="col-6">
                                <small class="text-muted d-block">ID Code</small>
                                <span class="fw-bold" id="view_code">-</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Contact #</small>
                                <span class="fw-bold" id="view_contact">-</span>
                            </div>
                            <div class="col-12">
                                <small class="text-muted d-block">Email</small>
                                <span class="fw-bold" id="view_email">-</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Hired</small>
                                <span class="fw-bold" id="view_hired">-</span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted d-block">Rate</small>
                                <span class="fw-bold text-success" id="view_rate">-</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light border-0">
                    <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="editEmployeeModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content shadow">
                <div class="modal-header bg-warning">
                    <h5 class="modal-title fw-bold text-dark"><i class="bi bi-pencil-square me-2"></i>Edit Employee</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form id="editEmployeeForm">
                    <div class="modal-body p-4">
                        <input type="hidden" name="employee_id" id="edit_employee_id">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">First Name</label>
                                <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Last Name</label>
                                <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Email</label>
                                <input type="email" name="email" id="edit_email" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Contact #</label>
                                <input type="text" name="contact_number" id="edit_contact_number" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Position</label>
                                <input type="text" name="position" id="edit_position" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Status</label>
                                <select name="status" id="edit_status" class="form-select">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Rate (₱)</label>
                                <input type="number" step="0.01" name="daily_rate" id="edit_daily_rate" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Hired Date</label>
                                <input type="date" name="date_hired" id="edit_date_hired" class="form-control" required>
                            </div>
                            <div class="col-12 bg-light p-3 rounded border">
                                <p class="small text-muted mb-1">Leave blank to keep current password.</p>
                                <label class="form-label fw-bold">New Password</label>
                                <input type="password" name="password" class="form-control" placeholder="********">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light border-0">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning fw-bold text-dark px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // chart initialization
        new Chart(document.getElementById('attendanceChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['On Time', 'Late', 'Absent'],
                datasets: [{
                    data: [<?= $attendanceStats['on_time']; ?>, <?= $attendanceStats['late']; ?>, <?= $attendanceStats['absent']; ?>],
                    backgroundColor: ['#198754', '#ffc107', '#dc3545'],
                    borderWidth: 0
                }]
            },
            options: {
                cutout: '70%',
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });

        // search filter
        document.getElementById('employeeSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#employeeTable tbody tr');
            rows.forEach(row => {
                row.style.display = row.innerText.toLowerCase().includes(filter) ? '' : 'none';
            });
        });

        // add employee submit
        document.getElementById('addEmployeeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            try {
                const res = await fetch('../../backend/employee/add_employee.php', {
                    method: 'POST',
                    body: new FormData(this)
                });
                const data = await res.json();
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Added!',
                        text: data.message,
                        timer: 1500,
                        showConfirmButton: false
                    }).then(() => location.reload());
                } else {
                    Swal.fire('Error', data.message, 'error');
                }
            } catch (err) {
                Swal.fire('Error', 'Server error.', 'error');
            } finally {
                submitBtn.disabled = false;
            }
        });

        // view employee details
        async function viewEmployee(id) {
            new bootstrap.Modal(document.getElementById('viewEmployeeModal')).show();
            try {
                const res = await fetch(`../../backend/employee/get_employee.php?id=${id}`);
                const json = await res.json();
                if (json.success) {
                    const d = json.data;
                    document.getElementById('view_fullname').innerText = d.first_name + ' ' + d.last_name;
                    document.getElementById('view_position').innerText = d.position;
                    document.getElementById('view_code').innerText = d.employee_code || 'N/A';
                    document.getElementById('view_email').innerText = d.email || 'N/A';
                    document.getElementById('view_contact').innerText = d.contact_number || 'N/A';
                    document.getElementById('view_hired').innerText = d.date_hired;
                    document.getElementById('view_rate').innerText = '₱' + parseFloat(d.daily_rate).toFixed(2);
                    const b = document.getElementById('view_status');
                    b.innerText = d.status;
                    b.className = `badge rounded-pill px-3 py-1 bg-${d.status.toLowerCase() === 'active' ? 'success' : 'secondary'}`;
                }
            } catch (e) {
                console.error(e);
            }
        }

        // edit employee load data
        async function editEmployee(id) {
            try {
                const res = await fetch(`../../backend/employee/get_employee.php?id=${id}`);
                const json = await res.json();
                if (json.success) {
                    const d = json.data;
                    document.getElementById('edit_employee_id').value = d.employee_id;
                    document.getElementById('edit_first_name').value = d.first_name;
                    document.getElementById('edit_last_name').value = d.last_name;
                    document.getElementById('edit_email').value = d.email;
                    document.getElementById('edit_contact_number').value = d.contact_number;
                    document.getElementById('edit_position').value = d.position;
                    document.getElementById('edit_status').value = d.status;
                    document.getElementById('edit_daily_rate').value = d.daily_rate;
                    document.getElementById('edit_date_hired').value = d.date_hired;
                    new bootstrap.Modal(document.getElementById('editEmployeeModal')).show();
                }
            } catch (e) {
                alert('Load failed.');
            }
        }

        // edit employee submit
        document.getElementById('editEmployeeForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            try {
                const res = await fetch('../../backend/employee/update_employee.php', {
                    method: 'POST',
                    body: new FormData(this)
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
            } catch (err) {
                Swal.fire('Error', 'Update failed.', 'error');
            }
        });

        // Deactivate
        function confirmDeactivate(id) {
            Swal.fire({
                title: 'Deactivate?',
                text: "Disable this account?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes'
            }).then((r) => {
                if (r.isConfirmed) window.location.href = `../../backend/employee/delete_employee.php?id=${id}`;
            });
        }
    </script>
</body>

</html>