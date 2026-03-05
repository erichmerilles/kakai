<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// set active module for sidebar
$activeModule = 'employee';

// role validation
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../../index.php');
    exit;
}

$empId = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($empId === 0) {
    header('Location: employee_module.php');
    exit;
}

$successMsg = '';
$errorMsg = '';

// fetch employee details
$stmt = $pdo->prepare("SELECT first_name, last_name, position FROM employees WHERE employee_id = ?");
$stmt->execute([$empId]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee not found.");
}

// extract initials for avatar
$initials = strtoupper(substr($employee['first_name'], 0, 1) . substr($employee['last_name'], 0, 1));

// module and feature definitions
$modules = [
    'Employees & HR' => [
        'icon' => 'bi-people',
        'features' => [
            'emp_view'   => 'View Employee Directory',
            'emp_add'    => 'Add New Employee',
            'emp_edit'   => 'Edit Employee Details',
            'emp_delete' => 'Delete/Deactivate Employee',
        ]
    ],
    'Attendance Management' => [
        'icon' => 'bi-calendar-check',
        'features' => [
            'att_view'    => 'View Attendance & Timesheets',
            'att_edit'    => 'Edit Attendance Records',
            'att_approve' => 'Approve Overtime & Add Manual Logs',
        ]
    ],
    'Payroll & Cash Advances' => [
        'icon' => 'bi-wallet2',
        'features' => [
            'payroll_view'       => 'View Payroll History',
            'payroll_analytics'  => 'View Payroll Analytics & Trends',
            'payroll_generate'   => 'Generate & Delete Payroll Runs',
            'payroll_distribute' => 'Distribute Payslips to Employees',
            'payslip_print'      => 'Print Employee Payslips',
            //'ca_view'            => 'View Cash Advance Lists',
            //'ca_manage'          => 'Manage & Approve Cash Advances',
        ]
    ],
    'Inventory Management' => [
        'icon' => 'bi-box-seam',
        'features' => [
            'inv_view'       => 'View Inventory & Analytics',
            'inv_add'        => 'Add Items, Categories & Suppliers',
            'inv_edit'       => 'Edit Inventory Items',
            'inv_delete'     => 'Delete Inventory Items',
            'inv_stock_in'   => 'Process Stock IN',
            'inv_stock_out'  => 'Process Stock OUT',
            'inv_cat_manage' => 'Manage Categories',
            'inv_sup_manage' => 'Manage Suppliers',
        ]
    ],
    'POS & Ordering' => [
        'icon' => 'bi-cart-check',
        'features' => [
            'order_view'   => 'View Order List & History',
            'order_create' => 'Create POS / New Orders',
            'order_status' => 'Update Order Status & Payment',
        ]
    ]
];

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // build the SQL dynamically
        $validKeys = [];
        foreach ($modules as $moduleData) {
            foreach ($moduleData['features'] as $key => $label) {
                $validKeys[] = $key;
            }
        }

        $columns = ['employee_id'];
        $values = [$empId];
        $updateParts = [];

        foreach ($validKeys as $key) {
            // checkbox value
            $val = isset($_POST[$key]) ? 1 : 0;

            $columns[] = $key;
            $values[] = $val;

            // for duplicate key update
            $updateParts[] = "$key = VALUES($key)";
        }

        $colString = implode(", ", $columns);
        $valString = implode(", ", array_fill(0, count($values), "?"));
        $updateString = implode(", ", $updateParts);

        // for add/update permissions
        $sql = "INSERT INTO employee_permissions ($colString) VALUES ($valString) 
                ON DUPLICATE KEY UPDATE $updateString";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);

        $successMsg = "Permissions updated successfully!";
    } catch (PDOException $e) {
        $errorMsg = "Database Error: " . $e->getMessage();
    }
}

// fetch existing permissions
$currentPerms = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM employee_permissions WHERE employee_id = ?");
    $stmt->execute([$empId]);
    $currentPerms = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Exception $e) {
    // defaults to empty if no record found
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Control | KakaiOne</title>
    <?php include '../includes/links.php'; ?>
    <style>
        .permission-item {
            transition: all 0.2s ease-in-out;
            border: 1px solid transparent;
        }

        .permission-item:hover {
            background-color: rgba(255, 193, 7, 0.1) !important;
            border-color: rgba(255, 193, 7, 0.3);
            transform: translateX(4px);
        }

        .form-check-input {
            cursor: pointer;
        }

        .form-check-input:checked {
            background-color: #198754;
            border-color: #198754;
        }

        /* Make entire row clickable */
        .stretched-link::after {
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            z-index: 1;
            content: "";
        }
    </style>
</head>

<body class="bg-light">

    <?php include '../includes/sidebar.php'; ?>

    <div id="dashboardContainer">
        <main id="main-content" style="margin-left: 250px; padding: 25px; transition: margin-left 0.3s;">
            <div class="container-fluid">

                <?php if ($successMsg): ?>
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            Swal.fire({
                                icon: 'success',
                                title: 'Access Updated!',
                                text: '<?= $successMsg ?>',
                                timer: 2000,
                                showConfirmButton: false
                            });
                        });
                    </script>
                <?php elseif ($errorMsg): ?>
                    <div class="alert alert-danger mb-4 shadow-sm"><i class="bi bi-exclamation-triangle me-2"></i><?= $errorMsg ?></div>
                <?php endif; ?>

                <div class="card shadow-sm border-0 mb-4 bg-white rounded">
                    <div class="card-body d-flex align-items-center justify-content-between p-4">
                        <div class="d-flex align-items-center">
                            <div class="bg-primary bg-opacity-10 text-primary rounded-circle d-flex justify-content-center align-items-center me-4 shadow-sm" style="width: 70px; height: 70px; font-size: 1.8rem; font-weight: bold;">
                                <?= $initials ?>
                            </div>
                            <div>
                                <h3 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></h3>
                                <div class="text-muted d-flex align-items-center">
                                    <i class="bi bi-briefcase-fill me-2"></i> <?= htmlspecialchars($employee['position'] ?? 'Employee') ?>
                                    <span class="mx-2">|</span>
                                    <i class="bi bi-shield-lock-fill me-2 text-warning"></i> Access Control Management
                                </div>
                            </div>
                        </div>
                        <a href="employee_module.php" class="btn btn-secondary shadow-sm fw-bold">
                            <i class="bi bi-arrow-left me-1"></i> Back
                        </a>
                    </div>
                </div>

                <form method="POST">
                    <div class="row g-4 mb-4">
                        <?php foreach ($modules as $moduleName => $moduleData): ?>
                            <div class="col-md-6 col-lg-6">
                                <div class="card h-100 shadow-sm border-0">
                                    <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center py-3">
                                        <span class="fs-5"><i class="bi <?= $moduleData['icon'] ?> me-2 text-warning"></i> <?= $moduleName ?></span>
                                        <div class="form-check form-switch m-0 d-flex align-items-center" title="Toggle all permissions for <?= $moduleName ?>">
                                            <input class="form-check-input select-all-toggle fs-4 m-0 shadow-sm" type="checkbox" data-target="<?= strtolower(str_replace([' ', '&'], '', $moduleName)) ?>">
                                        </div>
                                    </div>
                                    <div class="card-body p-3">
                                        <div class="d-flex flex-column gap-2" id="<?= strtolower(str_replace([' ', '&'], '', $moduleName)) ?>">
                                            <?php foreach ($moduleData['features'] as $key => $label): ?>
                                                <?php
                                                // check permission
                                                $isChecked = isset($currentPerms[$key]) && $currentPerms[$key] == 1 ? 'checked' : '';
                                                ?>
                                                <div class="form-check form-switch p-3 bg-light rounded permission-item d-flex justify-content-between align-items-center m-0 position-relative shadow-sm">
                                                    <label class="form-check-label fw-bold text-dark stretched-link mb-0" for="<?= $key ?>" style="cursor: pointer;">
                                                        <?= $label ?>
                                                    </label>
                                                    <input class="form-check-input fs-5 m-0 z-2 shadow-sm" type="checkbox" name="<?= $key ?>" id="<?= $key ?>" value="1" <?= $isChecked ?>>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="card shadow-sm border-0 bg-white p-4">
                        <div class="d-flex justify-content-end gap-3 align-items-center">
                            <span class="text-muted small me-auto"><i class="bi bi-info-circle-fill me-1 text-primary"></i> Checking a box grants the employee access to that specific feature. Changes will take effect immediately.</span>
                            <a href="employee_module.php" class="btn btn-outline-secondary px-4 fw-bold">Cancel</a>
                            <button type="submit" class="btn btn-warning px-5 fw-bold text-dark shadow-sm">
                                <i class="bi bi-save me-2"></i>Save Permissions
                            </button>
                        </div>
                    </div>

                </form>

            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // select all toggle logic
        document.querySelectorAll('.select-all-toggle').forEach(toggle => {
            toggle.addEventListener('change', function() {
                const targetId = this.getAttribute('data-target');
                const checkboxes = document.querySelectorAll(`#${targetId} input[type="checkbox"]`);
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        });

        // initialize master toggles based on current permissions
        document.addEventListener('DOMContentLoaded', () => {
            document.querySelectorAll('.select-all-toggle').forEach(toggle => {
                const targetId = toggle.getAttribute('data-target');
                const checkboxes = document.querySelectorAll(`#${targetId} input[type="checkbox"]`);
                const allChecked = Array.from(checkboxes).every(cb => cb.checked);

                if (checkboxes.length > 0) {
                    toggle.checked = allChecked;
                }

                // master switch logic when individual checkboxes are toggled
                checkboxes.forEach(cb => {
                    cb.addEventListener('change', () => {
                        toggle.checked = Array.from(checkboxes).every(c => c.checked);
                    });
                });
            });
        });
    </script>
</body>

</html>