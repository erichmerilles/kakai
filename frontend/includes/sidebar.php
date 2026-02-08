<?php
// Ensure session/auth
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/auth_check.php';

// Default active module
$activeModule = isset($activeModule) ? $activeModule : 'dashboard';

// 1. GET CURRENT PAGE NAME (e.g., 'employee_module.php')
$currentPage = basename($_SERVER['PHP_SELF']);

// Base URL Helper
$baseUrl = '/kakai1/frontend';
?>

<nav id="sidebar">

    <div class="text-center mb-4">
        <img src="<?= $baseUrl ?>/assets/images/logo.jpg" alt="Logo" width="80" height="80" style="border-radius: 50%; margin-bottom:10px;">
        <h5 class="fw-bold text-light">KakaiOne</h5>
        <p class="small text-light mb-3">
            <?= htmlspecialchars($_SESSION['role'] ?? 'User') ?> Panel
        </p>
    </div>

    <div class="nav flex-column flex-grow-1">

        <a href="<?= $baseUrl ?>/dashboard/admin_dashboard.php" class="nav-link mb-2 <?= $activeModule === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2 me-2"></i>Dashboard
        </a>

        <?php if (hasPermission('emp_view')): ?>
            <a href="<?= $baseUrl ?>/employee/employee_module.php" class="nav-link mb-2 <?= $activeModule === 'employee' ? 'active' : '' ?>">
                <i class="bi bi-people-fill me-2"></i>Employees
            </a>

            <?php if ($activeModule === 'employee'): ?>
                <div class="ms-3 mb-2 ps-2 border-start border-secondary">

                    <a href="<?= $baseUrl ?>/employee/employee_module.php"
                        class="nav-link py-1 small <?= $currentPage == 'employee_module.php' ? 'text-warning fw-bold' : 'text-light' ?>"
                        style="opacity: <?= $currentPage == 'employee_module.php' ? '1' : '0.8' ?>;">
                        <i class="bi bi-caret-right-fill"></i> Overview
                    </a>

                    <?php if (hasPermission('att_view')): ?>
                        <a href="<?= $baseUrl ?>/attendance/attendance_page.php"
                            class="nav-link py-1 small <?= $currentPage == 'attendance_page.php' ? 'text-warning fw-bold' : 'text-light' ?>"
                            style="opacity: <?= $currentPage == 'attendance_page.php' ? '1' : '0.8' ?>;">
                            <i class="bi bi-caret-right-fill"></i> Attendance
                        </a>
                    <?php endif; ?>

                    <a href="<?= $baseUrl ?>/requests/leave_requests.php"
                        class="nav-link py-1 small <?= $currentPage == 'leave_requests.php' ? 'text-warning fw-bold' : 'text-light' ?>"
                        style="opacity: <?= $currentPage == 'leave_requests.php' ? '1' : '0.8' ?>;">
                        <i class="bi bi-caret-right-fill"></i> Leaves
                    </a>

                    <a href="<?= $baseUrl ?>/requests/cash_advance.php"
                        class="nav-link py-1 small <?= $currentPage == 'cash_advance.php' ? 'text-warning fw-bold' : 'text-light' ?>"
                        style="opacity: <?= $currentPage == 'cash_advance.php' ? '1' : '0.8' ?>;">
                        <i class="bi bi-caret-right-fill"></i> Cash Advance
                    </a>

                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (hasPermission('inv_view')): ?>
            <a href="<?= $baseUrl ?>/inventory/inventory_overview.php"
                class="nav-link mb-2 <?= $activeModule === 'inventory' ? 'active' : '' ?>">
                <i class="bi bi-box-seam me-2"></i>Inventory
            </a>

            <?php if ($activeModule === 'inventory'): ?>
                <div class="ms-3 mb-2 ps-2 border-start border-secondary">

                    <a href="<?= $baseUrl ?>/inventory/inventory_overview.php"
                        class="nav-link py-1 small <?= $currentPage == 'inventory_overview.php' ? 'text-warning fw-bold' : 'text-light' ?>"
                        style="opacity: <?= $currentPage == 'inventory_overview.php' ? '1' : '0.8' ?>;">
                        <i class="bi bi-caret-right-fill"></i> Overview
                    </a>

                    <?php if (hasPermission('inv_add') || hasPermission('inv_edit')): ?>
                        <a href="<?= $baseUrl ?>/inventory/inventory_form.php"
                            class="nav-link py-1 small <?= $currentPage == 'inventory_form.php' ? 'text-warning fw-bold' : 'text-light' ?>"
                            style="opacity: <?= $currentPage == 'inventory_form.php' ? '1' : '0.8' ?>;">
                            <i class="bi bi-caret-right-fill"></i> Add/Edit Item
                        </a>
                    <?php endif; ?>

                    <?php if (hasPermission('inv_stock_in') || hasPermission('inv_stock_out')): ?>
                        <a href="<?= $baseUrl ?>/inventory/inventory_movements.php"
                            class="nav-link py-1 small <?= $currentPage == 'inventory_movements.php' ? 'text-warning fw-bold' : 'text-light' ?>"
                            style="opacity: <?= $currentPage == 'inventory_movements.php' ? '1' : '0.8' ?>;">
                            <i class="bi bi-caret-right-fill"></i> Stock Movements
                        </a>
                    <?php endif; ?>

                    <a href="<?= $baseUrl ?>/inventory/inventory_analytics.php"
                        class="nav-link py-1 small <?= $currentPage == 'inventory_analytics.php' ? 'text-warning fw-bold' : 'text-light' ?>"
                        style="opacity: <?= $currentPage == 'inventory_analytics.php' ? '1' : '0.8' ?>;">
                        <i class="bi bi-caret-right-fill"></i> Analytics
                    </a>

                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (hasPermission('payroll_view')): ?>
            <a href="<?= $baseUrl ?>/payroll/payroll_module.php" class="nav-link mb-2 <?= $activeModule === 'payroll' ? 'active' : '' ?>">
                <i class="bi bi-cash-coin me-2"></i>Payroll
            </a>
        <?php endif; ?>

        <?php if (hasPermission('order_view')): ?>
            <a href="<?= $baseUrl ?>/ordering/ordering_module.php" class="nav-link mb-2 <?= $activeModule === 'ordering' ? 'active' : '' ?>">
                <i class="bi bi-cart-check me-2"></i>Ordering
            </a>
        <?php endif; ?>

    </div>

    <div class="mt-auto">
        <form action="/kakai1/backend/auth/logout.php" method="POST">
            <button class="btn btn-outline-light w-100 btn-sm mt-3">
                <i class="bi bi-box-arrow-right me-1"></i> Logout
            </button>
        </form>
        <p class="text-center text-secondary small mt-3 mb-0">© 2025 KakaiOne</p>
    </div>
</nav>

<?php if ($activeModule === 'inventory'): ?>
    <!--<script>
        document.addEventListener("DOMContentLoaded", () => {
            const subLinks = document.querySelectorAll("#inventory-submenu .sub-link");

            function handleSubNavClick(e) {
                e.preventDefault();
                const targetId = e.currentTarget.getAttribute('data-target');

                // Reset sub-links
                subLinks.forEach(link => {
                    link.classList.remove("text-warning", "fw-bold", "active");
                    link.classList.add("text-light");
                    link.style.opacity = "0.8";
                });

                // Activate clicked sub-link
                e.currentTarget.classList.remove("text-light");
                e.currentTarget.classList.add("text-warning", "fw-bold", "active");
                e.currentTarget.style.opacity = "1";

                // Dispatch Event
                window.dispatchEvent(new CustomEvent('inventory-nav-change', {
                    detail: {
                        targetId
                    }
                }));
            }

            subLinks.forEach(link => link.addEventListener('click', handleSubNavClick));
        });
    </script>-->
<?php endif; ?>