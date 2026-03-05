<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// set active module
$activeModule = 'settings';

// role validation
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../index.php');
    exit;
}

$currentUserId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];

// fetch logs based on role
$logs = [];
if (isset($conn)) {
    if ($userRole === 'Admin') {
        // admin logs
        $query = "
      SELECT al.log_id, al.action, al.module, al.description, al.created_at, u.username, u.role
      FROM activity_logs al
      LEFT JOIN users u ON al.user_id = u.user_id
      ORDER BY al.created_at DESC
    ";
        $stmt = $conn->prepare($query);
    } else {
        // employee logs
        $query = "
      SELECT al.log_id, al.action, al.module, al.description, al.created_at, u.username, u.role
      FROM activity_logs al
      LEFT JOIN users u ON al.user_id = u.user_id
      WHERE al.user_id = ?
      ORDER BY al.created_at DESC
    ";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("i", $currentUserId);
    }

    if ($stmt->execute()) {
        $res = $stmt->get_result();
        $logs = $res->fetch_all(MYSQLI_ASSOC);
    }
    $stmt->close();
}

$pageTitle = ($userRole === 'Admin') ? 'System Activity Log' : 'My Activity Log';
$pageDesc = ($userRole === 'Admin') ? 'Monitor user actions and system changes across the platform.' : 'Review your recent actions and interactions within the system.';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KakaiOne | <?= $pageTitle ?></title>

    <?php include '../includes/links.php'; ?>

    <style>
        .log-badge {
            width: 85px;
            display: inline-block;
            text-align: center;
        }

        /* Customizing Bootstrap Table Search Input to match your UI */
        .fixed-table-toolbar .search input {
            border-radius: 6px;
            border: 1px solid #dee2e6;
            padding: 0.375rem 0.75rem;
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
                            <i class="bi <?= ($userRole === 'Admin') ? 'bi-shield-check text-danger' : 'bi-person-lines-fill text-primary' ?> me-2"></i>
                            <?= $pageTitle ?>
                        </h3>
                        <p class="text-muted mb-0"><?= $pageDesc ?></p>
                    </div>
                </div>

                <div class="card shadow-sm border-0 rounded-3 mb-4">
                    <div class="card-body">

                        <table id="logTable"
                            data-toggle="table"
                            data-search="true"
                            data-pagination="true"
                            data-page-size="15"
                            data-page-list="[15, 25, 50, 100, all]"
                            data-classes="table table-hover table-borderless align-middle"
                            class="w-100">
                            <thead class="table-light border-bottom">
                                <tr>
                                    <th data-field="timestamp" data-sortable="true" class="ps-3"><i class="bi bi-clock me-1"></i> Timestamp</th>
                                    <?php if ($userRole === 'Admin'): ?>
                                        <th data-field="user" data-sortable="true"><i class="bi bi-person me-1"></i> User</th>
                                    <?php endif; ?>
                                    <th data-field="module" data-sortable="true"><i class="bi bi-box me-1"></i> Module</th>
                                    <th data-field="action" data-sortable="true"><i class="bi bi-lightning me-1"></i> Action</th>
                                    <th data-field="description" data-sortable="true"><i class="bi bi-card-text me-1"></i> Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($logs)): ?>
                                    <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td class="ps-3 text-muted small" style="white-space: nowrap;">
                                                <?= date('Y/m/d H:i:s', strtotime($log['created_at'])) ?>
                                            </td>

                                            <?php if ($userRole === 'Admin'): ?>
                                                <td class="fw-bold text-dark">
                                                    <?= htmlspecialchars($log['username'] ?? 'System / Deleted') ?>
                                                    <?php if (isset($log['role']) && $log['role'] === 'Admin'): ?>
                                                        <i class="bi bi-star-fill text-warning ms-1" style="font-size: 0.7rem;" title="Admin"></i>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endif; ?>

                                            <td>
                                                <span class="badge bg-secondary text-light"><?= htmlspecialchars($log['module']) ?></span>
                                            </td>

                                            <td>
                                                <?php
                                                $action = htmlspecialchars($log['action']);
                                                $badgeColor = 'primary';
                                                $actionLower = strtolower($action);

                                                if (strpos($actionLower, 'create') !== false || strpos($actionLower, 'add') !== false || strpos($actionLower, 'login') !== false || strpos($actionLower, 'approve') !== false) {
                                                    $badgeColor = 'success';
                                                } elseif (strpos($actionLower, 'delete') !== false || strpos($actionLower, 'remove') !== false || strpos($actionLower, 'decline') !== false) {
                                                    $badgeColor = 'danger';
                                                } elseif (strpos($actionLower, 'update') !== false || strpos($actionLower, 'edit') !== false) {
                                                    $badgeColor = 'warning text-dark';
                                                }
                                                ?>
                                                <span class="badge bg-<?= $badgeColor ?> log-badge rounded-pill"><?= $action ?></span>
                                            </td>

                                            <td class="text-secondary">
                                                <?= htmlspecialchars($log['description']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>

                    </div>
                </div>

            </div>

        </main>
    </div>

</body>

</html>