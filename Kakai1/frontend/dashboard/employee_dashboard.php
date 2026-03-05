<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// set active module
$activeModule = 'dashboard';

// role validation
if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$employee_id = $_SESSION['employee_id'];

// check today attendance status
$attendanceStatus = 'clock_in';

try {
    // check for record created today
    $stmt = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE employee_id = ? AND DATE(time_in) = CURDATE() 
        LIMIT 1
    ");
    $stmt->execute([$employee_id]);
    $todayLog = $stmt->fetch();

    if ($todayLog) {
        if ($todayLog['time_out'] === NULL) {
            $attendanceStatus = 'clock_out';
        } else {
            $attendanceStatus = 'completed';
        }
    }
} catch (PDOException $e) {
    // handle error
}

// fetch recent attendance history
try {
    $histStmt = $pdo->prepare("
        SELECT * FROM attendance 
        WHERE employee_id = ? 
        ORDER BY time_in DESC 
        LIMIT 5
    ");
    $histStmt->execute([$employee_id]);
    $history = $histStmt->fetchAll();
} catch (PDOException $e) {
    $history = [];
}

// fetch specific permission
$permissions = [];
try {
    $permStmt = $pdo->prepare("SELECT * FROM employee_permissions WHERE employee_id = ?");
    $permStmt->execute([$employee_id]);
    $permissions = $permStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // handle error
}

// check permissions
$accessibleModules = [];
if ($permissions) {
    if (!empty($permissions['inv_view'])) {
        $accessibleModules[] = ['title' => 'Inventory', 'desc' => 'Manage stock and items', 'icon' => 'bi-box-seam', 'color' => 'primary', 'link' => '../inventory/inventory_overview.php'];
    }
    if (!empty($permissions['order_view']) || !empty($permissions['order_create'])) {
        $accessibleModules[] = ['title' => 'Ordering & POS', 'desc' => 'Process customer orders', 'icon' => 'bi-cart-check', 'color' => 'success', 'link' => '../ordering/ordering_module.php'];
    }
    if (!empty($permissions['emp_view'])) {
        $accessibleModules[] = ['title' => 'Employees', 'desc' => 'View staff directory', 'icon' => 'bi-people', 'color' => 'info', 'link' => '../employee/employee_module.php'];
    }
    if (!empty($permissions['att_approve']) || !empty($permissions['att_view'])) {
        $accessibleModules[] = ['title' => 'Attendance Admin', 'desc' => 'Manage timesheets & OT', 'icon' => 'bi-calendar-check', 'color' => 'warning', 'link' => '../attendance/attendance_page.php'];
    }
    if (!empty($permissions['payroll_view'])) {
        $accessibleModules[] = ['title' => 'Payroll', 'desc' => 'View payroll records', 'icon' => 'bi-cash-coin', 'color' => 'danger', 'link' => '../payroll/payroll_module.php'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KakaiOne | Employee Dashboard</title>
    <?php include '../includes/links.php'; ?>
    <style>
        .access-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            color: inherit;
        }

        .access-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1) !important;
        }

        .digital-clock {
            font-size: 2.5rem;
            font-weight: 800;
            font-family: monospace;
            color: #212529;
            letter-spacing: 2px;
        }
    </style>
</head>

<body class="bg-light">

    <?php include '../includes/sidebar.php'; ?>

    <div id="dashboardContainer">
        <main id="main-content" style="margin-left: 250px; padding: 25px; transition: margin-left 0.3s;">
            <div class="container-fluid">

                <div class="d-flex justify-content-between align-items-center mb-4 border-bottom pb-3 border-secondary border-opacity-25">
                    <div>
                        <h3 class="fw-bold text-dark mb-1">Welcome, <?= htmlspecialchars($_SESSION['username'] ?? 'Employee'); ?>!</h3>
                        <p class="text-muted mb-0">Here is your daily overview and authorized tools.</p>
                    </div>
                    <div>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary fs-6 px-3 py-2 rounded-pill shadow-sm">
                            <i class="bi bi-person-badge-fill me-1"></i> <?= htmlspecialchars($_SESSION['role'] ?? 'Staff'); ?>
                        </span>
                    </div>
                </div>

                <div class="row g-4 mb-4">
                    <div class="col-lg-5">
                        <div class="card shadow-sm border-0 h-100 text-center">
                            <div class="card-header bg-dark text-white fw-bold text-start py-3">
                                <i class="bi bi-clock-fill me-2 text-warning"></i>Time Tracker
                            </div>
                            <div class="card-body d-flex flex-column justify-content-center align-items-center py-5 bg-white">
                                <h6 class="text-uppercase text-muted fw-bold tracking-wider mb-1">Current Time</h6>
                                <div class="digital-clock text-primary" id="liveClock">00:00:00 AM</div>
                                <div class="text-muted fs-5 mb-4" id="liveDate">Loading date...</div>

                                <div class="w-100 px-4">
                                    <?php if ($attendanceStatus === 'clock_in'): ?>
                                        <button onclick="handleAttendance('in')" class="btn btn-success btn-lg w-100 shadow-sm fw-bold rounded-pill">
                                            <i class="bi bi-box-arrow-in-right me-2 fs-5 align-middle"></i> Clock In Now
                                        </button>
                                        <p class="mt-3 small text-muted">You have not started your shift yet today.</p>
                                    <?php elseif ($attendanceStatus === 'clock_out'): ?>
                                        <button onclick="handleAttendance('out')" class="btn btn-danger btn-lg w-100 shadow-sm fw-bold rounded-pill">
                                            <i class="bi bi-box-arrow-left me-2 fs-5 align-middle"></i> Clock Out Now
                                        </button>
                                        <p class="mt-3 small text-info fw-bold"><i class="bi bi-info-circle me-1"></i> Your shift is currently active.</p>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-lg w-100 shadow-sm fw-bold rounded-pill disabled" disabled>
                                            <i class="bi bi-check-circle-fill me-2 fs-5 align-middle"></i> Shift Completed
                                        </button>
                                        <p class="mt-3 small text-success fw-bold">You have successfully completed your shift for today.</p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="card shadow-sm border-0 h-100">
                            <div class="card-header bg-dark text-white fw-bold text-start py-3 d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-grid-1x2-fill me-2 text-info"></i>My Authorized Modules</span>
                                <span class="badge bg-light text-dark rounded-pill"><?= count($accessibleModules) ?> Accessible</span>
                            </div>
                            <div class="card-body bg-white p-4">
                                <?php if (!empty($accessibleModules)): ?>
                                    <div class="row g-3">
                                        <?php foreach ($accessibleModules as $module): ?>
                                            <div class="col-md-6">
                                                <a href="<?= $module['link'] ?>" class="card access-card border bg-light h-100 text-decoration-none">
                                                    <div class="card-body d-flex align-items-center">
                                                        <div class="rounded-circle bg-<?= $module['color'] ?> bg-opacity-10 d-flex justify-content-center align-items-center me-3" style="width: 50px; height: 50px;">
                                                            <i class="bi <?= $module['icon'] ?> text-<?= $module['color'] ?> fs-3"></i>
                                                        </div>
                                                        <div>
                                                            <h6 class="fw-bold mb-1 text-dark"><?= $module['title'] ?></h6>
                                                            <small class="text-muted"><?= $module['desc'] ?></small>
                                                        </div>
                                                    </div>
                                                </a>
                                            </div>
                                        <?php endforeach; ?>

                                        <div class="col-md-6">
                                            <a href="../requests/cash_advance.php" class="card access-card border bg-light h-100 text-decoration-none">
                                                <div class="card-body d-flex align-items-center">
                                                    <div class="rounded-circle bg-secondary bg-opacity-10 d-flex justify-content-center align-items-center me-3" style="width: 50px; height: 50px;">
                                                        <i class="bi bi-wallet2 text-secondary fs-3"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="fw-bold mb-1 text-dark">My Cash Advances</h6>
                                                        <small class="text-muted">Request or view Cash Advances</small>
                                                    </div>
                                                </div>
                                            </a>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted py-5">
                                        <i class="bi bi-shield-lock display-1 opacity-25 mb-3 d-block"></i>
                                        <h5 class="fw-bold">Limited Access</h5>
                                        <p>You currently only have access to time tracking and personal requests. <br>Please contact your Administrator if you need module access.</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
                        <h6 class="mb-0 fw-bold"><i class="bi bi-journal-text me-2 text-warning"></i>My Recent Attendance</h6>
                        <a href="../attendance/my_attendance.php" class="btn btn-sm btn-outline-light text-white border-secondary">View Full History <i class="bi bi-arrow-right"></i></a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th class="ps-4">Date</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th class="text-center">Total Hrs</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($history)): ?>
                                        <?php foreach ($history as $log): ?>
                                            <tr>
                                                <td class="ps-4 fw-bold text-dark"><?= date('M d, Y', strtotime($log['time_in'])); ?></td>
                                                <td>
                                                    <span class="text-success fw-medium"><i class="bi bi-box-arrow-in-right me-1"></i> <?= date('h:i A', strtotime($log['time_in'])); ?></span>
                                                </td>
                                                <td>
                                                    <?php if ($log['time_out']): ?>
                                                        <span class="text-danger fw-medium"><i class="bi bi-box-arrow-left me-1"></i> <?= date('h:i A', strtotime($log['time_out'])); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted fst-italic">--:--</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center fw-bold">
                                                    <?= $log['total_hours'] ? number_format($log['total_hours'], 2) . ' <small class="text-muted fw-normal">hrs</small>' : '-'; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $badge = 'secondary';
                                                    if ($log['status'] == 'Present') $badge = 'success';
                                                    if ($log['status'] == 'Late') $badge = 'warning text-dark';
                                                    ?>
                                                    <span class="badge bg-<?= $badge; ?> px-2 py-1"><?= htmlspecialchars($log['status']); ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="5" class="text-center py-5 text-muted">
                                                <i class="bi bi-calendar-x display-4 d-block mb-2 opacity-25"></i>
                                                No attendance records yet.
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
    </div>

    <script>
        // live clock update
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            const ampm = hours >= 12 ? 'PM' : 'AM';
            hours = hours % 12;
            hours = hours ? hours : 12;
            document.getElementById('liveClock').textContent = `${hours}:${minutes}:${seconds} ${ampm}`;
            document.getElementById('liveDate').textContent = now.toLocaleDateString('en-US', {
                weekday: 'long',
                year: 'numeric',
                month: 'long',
                day: 'numeric'
            });
        }
        setInterval(updateClock, 1000);
        updateClock();

        // attendance logic 
        async function handleAttendance(type) {
            const actionText = type === 'in' ? 'Clock In' : 'Clock Out';
            const result = await Swal.fire({
                title: `Confirm ${actionText}?`,
                text: `Are you sure you want to record your ${actionText} now?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: type === 'in' ? '#198754' : '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: `Yes, ${actionText}`
            });

            if (result.isConfirmed) {
                try {
                    const response = await fetch('../../backend/attendance/log_attendance.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            type: type
                        })
                    });

                    const data = await response.json();

                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('Error', data.message || 'Something went wrong', 'error');
                    }
                } catch (error) {
                    console.error(error);
                    Swal.fire('Error', 'Unable to connect to server.', 'error');
                }
            }
        }
    </script>
</body>

</html>