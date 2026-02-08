<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

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
$stmt = $pdo->prepare("SELECT first_name, last_name FROM employees WHERE employee_id = ?");
$stmt->execute([$empId]);
$employee = $stmt->fetch();

if (!$employee) {
    die("Employee not found.");
}

// module permissions structure
$modules = [
    'Inventory' => [
        'inv_view' => 'View Inventory',
        'inv_add' => 'Add New Items',
        'inv_edit' => 'Edit Items',
        'inv_delete' => 'Delete Items',
        'inv_stock_in' => 'Stock IN',
        'inv_stock_out' => 'Stock OUT',
    ],
    'Ordering' => [
        'order_view' => 'View Orders',
        'order_create' => 'Create New Order',
        'order_status' => 'Update Order Status',
    ],
    'Employees' => [
        'emp_view' => 'View Employee List',
        'emp_add' => 'Add New Employee',
        'emp_edit' => 'Edit Employee Details',
    ],
    'Payroll & Attendance' => [
        'att_view' => 'View Attendance',
        'att_approve' => 'Approve/Reject Attendance',
        'payroll_view' => 'View Payroll Records',
        'payroll_generate' => 'Generate Payroll',
    ]
];

// handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // build the SQL dynamically
        $columns = ['employee_id'];
        $values = [$empId];
        $updateParts = [];

        foreach ($modules as $group => $features) {
            foreach ($features as $key => $label) {
                // checkbox value
                $val = isset($_POST[$key]) ? 1 : 0;
                
                $columns[] = $key;
                $values[] = $val;
                
                // for duplicate key update
                $updateParts[] = "$key = VALUES($key)";
            }
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Control | KakaiOne</title>
    <?php include '../includes/links.php'; ?>
    <style>
        .module-card { transition: transform 0.2s; }
        .module-card:hover { transform: translateY(-5px); }
        .form-check-input:checked { background-color: #ffc107; border-color: #ffc107; }
    </style>
</head>
<body class="bg-light">

<div class="container py-5">
    
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-0">
                <i class="bi bi-shield-lock-fill text-warning me-2"></i>Access Control
            </h2>
            <p class="text-muted mb-0">Manage permissions for <strong><?= htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']) ?></strong></p>
        </div>
        <a href="employee_module.php" class="btn btn-secondary"><i class="bi bi-arrow-left"></i> Back to List</a>
    </div>

    <?php if ($successMsg): ?>
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                Swal.fire({ icon: 'success', title: 'Saved!', text: '<?= $successMsg ?>', confirmButtonColor: '#ffc107' });
            });
        </script>
    <?php elseif ($errorMsg): ?>
        <div class="alert alert-danger"><?= $errorMsg ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="row g-4">
            
            <?php foreach ($modules as $moduleName => $features): ?>
            <div class="col-md-6 col-lg-6">
                <div class="card h-100 shadow-sm module-card border-0">
                    <div class="card-header bg-dark text-white fw-bold d-flex justify-content-between align-items-center">
                        <span><?= $moduleName ?></span>
                        <div class="form-check form-switch">
                            <input class="form-check-input select-all-toggle" type="checkbox" data-target="<?= strtolower(str_replace([' ', '&'], '', $moduleName)) ?>">
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-column gap-3" id="<?= strtolower(str_replace([' ', '&'], '', $moduleName)) ?>">
                            <?php foreach ($features as $key => $label): ?>
                                <?php 
                                    // Check if permission is 1 (true)
                                    $isChecked = isset($currentPerms[$key]) && $currentPerms[$key] == 1 ? 'checked' : ''; 
                                ?>
                                <div class="form-check form-switch p-2 rounded border bg-light d-flex justify-content-between align-items-center px-3 position-relative">
                                    <label class="form-check-label fw-medium stretched-link" for="<?= $key ?>">
                                        <?= $label ?>
                                    </label>
                                    <input class="form-check-input fs-5" type="checkbox" name="<?= $key ?>" id="<?= $key ?>" value="1" <?= $isChecked ?>>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

        </div>

        <div class="fixed-bottom bg-white border-top shadow-lg p-3">
            <div class="container d-flex justify-content-end gap-3">
                <button type="button" class="btn btn-outline-secondary" onclick="window.history.back()">Cancel</button>
                <button type="submit" class="btn btn-warning px-5 fw-bold"><i class="bi bi-save me-2"></i>Save Permissions</button>
            </div>
        </div>
        <div style="height: 100px;"></div> 
    </form>

</div>

<script>
// select all logic
document.querySelectorAll('.select-all-toggle').forEach(toggle => {
    toggle.addEventListener('change', function() {
        const targetId = this.getAttribute('data-target');
        const checkboxes = document.querySelectorAll(`#${targetId} input[type="checkbox"]`);
        checkboxes.forEach(cb => cb.checked = this.checked);
    });
});
</script>

</body>
</html>