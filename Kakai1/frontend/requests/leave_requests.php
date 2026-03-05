<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

// 1. Auth & Role Check
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

$is_admin = ($_SESSION['role'] === 'Admin');
$my_id = $_SESSION['employee_id'];
$activeModule = 'employee'; // Or 'dashboard'

// 2. Handle Form Actions
$actionMsg = '';
$actionType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // --- CASE A: ADMIN APPROVE/REJECT ---
    if (isset($_POST['request_id'], $_POST['action']) && $is_admin) {
        if (!hasPermission('att_approve')) {
            die("You do not have permission to perform this action.");
        }

        $reqId = intval($_POST['request_id']);
        $action = $_POST['action'];

        try {
            $stmt = $pdo->prepare("UPDATE leave_requests SET status = ? WHERE leave_id = ?");
            if ($stmt->execute([$action, $reqId])) {
                $actionType = 'success';
                $actionMsg = "Request marked as " . htmlspecialchars($action) . ".";
            }
        } catch (PDOException $e) {
            $actionType = 'danger';
            $actionMsg = "Error: " . $e->getMessage();
        }
    }

    // --- CASE B: EMPLOYEE FILE LEAVE ---
    if (isset($_POST['file_leave']) && !$is_admin) {
        $type = $_POST['leave_type'];
        $start = $_POST['start_date'];
        $end = $_POST['end_date'];
        $reason = $_POST['reason'];

        try {
            $stmt = $pdo->prepare("INSERT INTO leave_requests (employee_id, leave_type, start_date, end_date, reason, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
            if ($stmt->execute([$my_id, $type, $start, $end, $reason])) {
                $actionType = 'success';
                $actionMsg = "Leave request filed successfully!";
            }
        } catch (PDOException $e) {
            $actionType = 'danger';
            $actionMsg = "Error: " . $e->getMessage();
        }
    }
}

// 3. Fetch Requests Logic
$requests = [];
$currentTab = isset($_GET['tab']) ? $_GET['tab'] : 'Pending';
$allowedTabs = ['Pending', 'Approved', 'Rejected'];
if (!in_array($currentTab, $allowedTabs)) $currentTab = 'Pending';

try {
    if ($is_admin) {
        // show all requests for admin
        $stmt = $pdo->prepare("
            SELECT lr.leave_id, lr.leave_type, lr.start_date, lr.end_date, lr.reason, lr.status, lr.created_at,
                   e.first_name, e.last_name, e.employee_code, e.position
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            WHERE lr.status = ?
            ORDER BY lr.created_at DESC
        ");
        $stmt->execute([$currentTab]);
    } else {
        // show self requests for employee
        $stmt = $pdo->prepare("
            SELECT lr.leave_id, lr.leave_type, lr.start_date, lr.end_date, lr.reason, lr.status, lr.created_at,
                   e.first_name, e.last_name, e.employee_code, e.position
            FROM leave_requests lr
            JOIN employees e ON lr.employee_id = e.employee_id
            WHERE lr.employee_id = ?
            ORDER BY lr.created_at DESC
        ");
        $stmt->execute([$my_id]);
    }
    $requests = $stmt->fetchAll();
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
    <title>Leave Requests | KakaiOne</title>
    <?php include '../includes/links.php'; ?>
</head>

<body class="bg-light">

    <?php include '../includes/sidebar.php'; ?>

    <main id="main-content" class="main-content-wrapper">
        <div class="container-fluid">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h3 class="fw-bold text-dark">
                        <i class="bi bi-envelope-paper me-2 text-warning"></i>
                        <?= $is_admin ? 'Leave Requests' : 'My Leave Requests' ?>
                    </h3>
                </div>

                <?php if (!$is_admin): ?>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#fileLeaveModal">
                        <i class="bi bi-plus-lg me-1"></i> File Leave
                    </button>
                <?php endif; ?>
            </div>

            <?php if ($actionMsg): ?>
                <div class="alert alert-<?= $actionType ?> alert-dismissible fade show" role="alert">
                    <?= $actionMsg ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($is_admin): ?>
                <ul class="nav nav-tabs mb-4 border-bottom-0">
                    <li class="nav-item">
                        <a class="nav-link <?= $currentTab === 'Pending' ? 'active fw-bold border border-bottom-0 bg-white' : 'text-secondary' ?>" href="?tab=Pending">
                            <i class="bi bi-hourglass-split me-1"></i> Pending
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentTab === 'Approved' ? 'active fw-bold border border-bottom-0 bg-white' : 'text-secondary' ?>" href="?tab=Approved">
                            <i class="bi bi-check-circle me-1"></i> Approved
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $currentTab === 'Rejected' ? 'active fw-bold border border-bottom-0 bg-white' : 'text-secondary' ?>" href="?tab=Rejected">
                            <i class="bi bi-x-circle me-1"></i> Rejected
                        </a>
                    </li>
                </ul>
            <?php endif; ?>

            <div class="card shadow-sm border-0">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <?php if ($is_admin): ?>
                                        <th class="ps-4 py-3">Employee</th>
                                    <?php endif; ?>
                                    <th class="<?= $is_admin ? '' : 'ps-4' ?>">Leave Type</th>
                                    <th>Duration</th>
                                    <th>Reason</th>
                                    <th>Date Filed</th>
                                    <th>Status</th>
                                    <?php if ($is_admin && $currentTab === 'Pending'): ?>
                                        <th class="text-end pe-4">Actions</th>
                                    <?php endif; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($requests)): ?>
                                    <?php foreach ($requests as $req): ?>
                                        <tr>
                                            <?php if ($is_admin): ?>
                                                <td class="ps-4">
                                                    <div class="d-flex align-items-center">
                                                        <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex align-items-center justify-content-center fw-bold me-3" style="width: 40px; height: 40px;">
                                                            <?= strtoupper(substr($req['first_name'], 0, 1)) ?>
                                                        </div>
                                                        <div>
                                                            <h6 class="mb-0 fw-semibold text-dark"><?= htmlspecialchars($req['first_name'] . ' ' . $req['last_name']) ?></h6>
                                                            <small class="text-muted"><?= htmlspecialchars($req['position']) ?></small>
                                                        </div>
                                                    </div>
                                                </td>
                                            <?php endif; ?>

                                            <td class="<?= $is_admin ? '' : 'ps-4' ?>">
                                                <span class="badge bg-info text-dark bg-opacity-25 border border-info border-opacity-25">
                                                    <?= htmlspecialchars($req['leave_type']) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <div class="d-flex flex-column">
                                                    <span class="fw-medium"><?= date('M d, Y', strtotime($req['start_date'])) ?></span>
                                                    <small class="text-muted">to <?= date('M d, Y', strtotime($req['end_date'])) ?></small>
                                                </div>
                                            </td>

                                            <td style="max-width: 250px;">
                                                <span class="d-inline-block text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($req['reason']) ?>">
                                                    <?= htmlspecialchars($req['reason']) ?>
                                                </span>
                                            </td>

                                            <td class="text-muted small">
                                                <?= date('M d, Y', strtotime($req['created_at'])) ?>
                                            </td>

                                            <td>
                                                <?php
                                                $badge = 'secondary';
                                                if ($req['status'] === 'Approved') $badge = 'success';
                                                elseif ($req['status'] === 'Rejected') $badge = 'danger';
                                                elseif ($req['status'] === 'Pending') $badge = 'warning';
                                                ?>
                                                <span class="badge bg-<?= $badge ?>"><?= $req['status'] ?></span>
                                            </td>

                                            <?php if ($is_admin && $currentTab === 'Pending'): ?>
                                                <td class="text-end pe-4">
                                                    <button onclick="confirmAction(<?= $req['leave_id'] ?>, 'Approved')" class="btn btn-sm btn-success me-1">
                                                        <i class="bi bi-check-lg"></i> Approve
                                                    </button>
                                                    <button onclick="confirmAction(<?= $req['leave_id'] ?>, 'Rejected')" class="btn btn-sm btn-outline-danger">
                                                        <i class="bi bi-x-lg"></i> Reject
                                                    </button>
                                                </td>
                                            <?php endif; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="<?= $is_admin ? '7' : '6' ?>" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox display-6 d-block mb-3 opacity-50"></i>
                                            No leave requests found.
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

    <form id="actionForm" method="POST" style="display:none;">
        <input type="hidden" name="request_id" id="reqIdInput">
        <input type="hidden" name="action" id="actionInput">
    </form>

    <?php if (!$is_admin): ?>
        <div class="modal fade" id="fileLeaveModal" tabindex="-1">
            <div class="modal-dialog">
                <form method="POST" class="modal-content">
                    <div class="modal-header bg-warning">
                        <h5 class="modal-title fw-bold">File Leave Request</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="file_leave" value="1">

                        <div class="mb-3">
                            <label class="form-label">Leave Type</label>
                            <select name="leave_type" class="form-select" required>
                                <option value="Sick Leave">Sick Leave</option>
                                <option value="Vacation Leave">Vacation Leave</option>
                                <option value="Emergency Leave">Emergency Leave</option>
                                <option value="Maternity/Paternity Leave">Maternity/Paternity Leave</option>
                            </select>
                        </div>

                        <div class="row">
                            <div class="col-6 mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" name="start_date" class="form-control" required>
                            </div>
                            <div class="col-6 mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea name="reason" class="form-control" rows="3" required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Request</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <script>
        function confirmAction(id, action) {
            let title = action === 'Approved' ? 'Approve Request?' : 'Reject Request?';
            let btnColor = action === 'Approved' ? '#198754' : '#dc3545';
            let text = action === 'Approved' ? 'This will mark the leave as official.' : 'This request will be denied.';

            Swal.fire({
                title: title,
                text: text,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: btnColor,
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, ' + action
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('reqIdInput').value = id;
                    document.getElementById('actionInput').value = action;
                    document.getElementById('actionForm').submit();
                }
            });
        }
    </script>

</body>

</html>