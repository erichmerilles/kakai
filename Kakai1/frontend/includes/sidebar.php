<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/auth_check.php';

//set active module
$activeModule = isset($activeModule) ? $activeModule : 'dashboard';
$currentPage = basename($_SERVER['PHP_SELF']);
$baseUrl = '/kakai1/frontend';

$dashboardLink = ($_SESSION['role'] === 'Admin')
    ? $baseUrl . '/dashboard/admin_dashboard.php'
    : $baseUrl . '/dashboard/employee_dashboard.php';
?>

<style>
    #sidebar {
        background-color: #1e1e2d;
        box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
        z-index: 1000;
    }

    .sidebar-logo {
        border: 2px solid #ffc107;
        border-radius: 50%;
        padding: 3px;
        background: #fff;
        transition: transform 0.3s ease;
    }

    .sidebar-logo:hover {
        transform: rotate(5deg) scale(1.05);
    }

    /* Main Links */
    #sidebar .nav-link {
        color: #a2a3b7;
        border-radius: 8px;
        padding: 10px 15px;
        margin-bottom: 4px;
        transition: all 0.3s ease;
        font-weight: 500;
    }

    #sidebar .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.05);
        color: #ffffff;
        transform: translateX(4px);
    }

    /* Active Main Link */
    #sidebar .nav-link.active {
        background-color: #ffc107 !important;
        color: #212529 !important;
        box-shadow: 0 4px 6px rgba(255, 193, 7, 0.3);
        font-weight: 700;
    }

    /* Sub Menu Container */
    .sub-menu-container {
        margin-left: 20px;
        padding-left: 10px;
        border-left: 2px solid rgba(255, 255, 255, 0.1);
        margin-bottom: 10px;
    }

    /* Sub Links */
    #sidebar .sub-link {
        font-size: 0.85rem;
        padding: 8px 15px;
        color: #8b8c9e !important;
        border-radius: 6px;
    }

    #sidebar .sub-link:hover {
        color: #ffc107 !important;
        background-color: rgba(255, 193, 7, 0.05);
        transform: translateX(4px);
    }

    /* Active Sub Link */
    #sidebar .sub-link.active-sub {
        color: #ffc107 !important;
        font-weight: 600;
        background-color: rgba(255, 193, 7, 0.1);
    }

    /* Badges & Section Headers */
    .section-header {
        font-size: 0.7rem;
        letter-spacing: 1.5px;
        color: #6c6d83;
        text-transform: uppercase;
        margin-top: 15px;
        margin-bottom: 5px;
        padding-left: 15px;
        font-weight: 700;
    }
</style>

<nav id="sidebar" class="d-flex flex-column p-3 vh-100 position-fixed" style="width: 250px; overflow-y: auto;">

    <div class="text-center mb-4 mt-2 logo-container pb-3 border-bottom border-secondary border-opacity-25">
        <img src="<?= $baseUrl ?>/assets/images/logo.jpg" alt="Logo" class="sidebar-logo mb-2" style="width: 70px; height: 70px; object-fit: cover;">
        <h5 class="fw-bold text-white mt-2 mb-1" style="letter-spacing: 0.5px;">KakaiOne</h5>
        <?php
        $roleColor = ($_SESSION['role'] === 'Admin') ? 'bg-danger' : 'bg-primary';
        ?>
        <span class="badge <?= $roleColor ?> bg-opacity-75 px-3 py-1 rounded-pill fw-normal">
            <i class="bi bi-person-badge me-1"></i> <?= htmlspecialchars($_SESSION['role'] ?? 'User') ?>
        </span>
    </div>

    <div class="nav flex-column flex-grow-1">

        <div class="section-header">Main Menu</div>

        <a href="<?= $dashboardLink ?>" class="nav-link <?= $activeModule === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2 me-2 fs-5 align-middle"></i> <span>Dashboard</span>
        </a>

        <?php if (hasPermission('emp_view')): ?>
            <a href="<?= $baseUrl ?>/employee/employee_module.php" class="nav-link <?= $activeModule === 'employee' ? 'active' : '' ?>">
                <i class="bi bi-people-fill me-2 fs-5 align-middle"></i> <span>Employees</span>
            </a>
            <?php if ($activeModule === 'employee'): ?>
                <div class="sub-menu-container">
                    <a href="<?= $baseUrl ?>/employee/employee_module.php" class="nav-link py-1 sub-link <?= $currentPage == 'employee_module.php' ? 'active-sub' : '' ?>">
                        <i class="bi bi-dot"></i> Directory & Overview
                    </a>

                    <?php if (hasPermission('att_view')): ?>
                        <a href="<?= $baseUrl ?>/attendance/attendance_page.php" class="nav-link py-1 sub-link <?= $currentPage == 'attendance_page.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-dot"></i> Daily Attendance
                        </a>

                        <a href="<?= $baseUrl ?>/attendance/timesheet_report.php" class="nav-link py-1 sub-link <?= $currentPage == 'timesheet_report.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-dot"></i> Timesheet Reports
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (hasPermission('inv_view')): ?>
            <a href="<?= $baseUrl ?>/inventory/inventory_overview.php" class="nav-link <?= $activeModule === 'inventory' ? 'active' : '' ?>">
                <i class="bi bi-box-seam me-2 fs-5 align-middle"></i> <span>Inventory</span>
            </a>
            <?php if ($activeModule === 'inventory'): ?>
                <div class="sub-menu-container">
                    <a href="<?= $baseUrl ?>/inventory/inventory_overview.php" class="nav-link py-1 sub-link <?= $currentPage == 'inventory_overview.php' ? 'active-sub' : '' ?>">
                        <i class="bi bi-dot"></i> Overview
                    </a>

                    <?php if (hasPermission('inv_add') || hasPermission('inv_edit')): ?>
                        <!--<a href="<?= $baseUrl ?>/inventory/inventory_form.php" class="nav-link py-1 sub-link <?= $currentPage == 'inventory_form.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-dot"></i> Add/Edit Item
                        </a>-->
                        <a href="<?= $baseUrl ?>/inventory/manage_categories.php" class="nav-link py-1 sub-link <?= $currentPage == 'manage_categories.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-dot"></i> Categories
                        </a>
                        <a href="<?= $baseUrl ?>/inventory/manage_suppliers.php" class="nav-link py-1 sub-link <?= $currentPage == 'manage_suppliers.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-dot"></i> Suppliers
                        </a>
                    <?php endif; ?>

                    <?php if (hasPermission('inv_stock_in') || hasPermission('inv_stock_out')): ?>
                        <a href="<?= $baseUrl ?>/inventory/inventory_movements.php" class="nav-link py-1 sub-link <?= $currentPage == 'inventory_movements.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-dot"></i> Stock Movements
                        </a>
                    <?php endif; ?>

                    <a href="<?= $baseUrl ?>/inventory/inventory_analytics.php" class="nav-link py-1 sub-link <?= $currentPage == 'inventory_analytics.php' ? 'active-sub' : '' ?>">
                        <i class="bi bi-dot"></i> Analytics
                    </a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (hasPermission('payroll_view') || hasPermission('ca_manage')): ?>
            <a href="<?= $baseUrl ?>/payroll/payroll_module.php" class="nav-link <?= $activeModule === 'payroll' ? 'active' : '' ?>">
                <i class="bi bi-cash-coin me-2 fs-5 align-middle"></i> <span>Payroll</span>
            </a>
            <?php if ($activeModule === 'payroll'): ?>
                <div class="sub-menu-container">
                    <?php if (hasPermission('payroll_view')): ?>
                        <a href="<?= $baseUrl ?>/payroll/payroll_module.php" class="nav-link py-1 sub-link <?= $currentPage == 'payroll_module.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-dot"></i> Generate & History
                        </a>
                    <?php endif; ?>

                    <?php if (hasPermission('ca_manage') || hasPermission('ca_view')): ?>
                        <a href="<?= $baseUrl ?>/requests/cash_advance.php" class="nav-link py-1 sub-link <?= $currentPage == 'cash_advance.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-dot"></i> Manage Cash Advances
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if (hasPermission('order_view')): ?>
            <a href="<?= $baseUrl ?>/ordering/ordering_module.php" class="nav-link <?= $activeModule === 'ordering' ? 'active' : '' ?>">
                <i class="bi bi-cart-check me-2 fs-5 align-middle"></i> <span>Ordering</span>
            </a>
            <?php if ($activeModule === 'ordering'): ?>
                <div class="sub-menu-container">
                    <a href="<?= $baseUrl ?>/ordering/ordering_module.php" class="nav-link py-1 sub-link <?= $currentPage == 'ordering_module.php' ? 'active-sub' : '' ?>">
                        <i class="bi bi-dot"></i> Dashboard
                    </a>

                    <?php if (hasPermission('order_create')): ?>
                        <a href="<?= $baseUrl ?>/ordering/order_create.php" class="nav-link py-1 sub-link <?= $currentPage == 'order_create.php' ? 'active-sub' : '' ?>">
                            <i class="bi bi-dot"></i> Create New Order
                        </a>
                    <?php endif; ?>

                    <a href="<?= $baseUrl ?>/ordering/order_list.php" class="nav-link py-1 sub-link <?= ($currentPage == 'order_list.php' || $currentPage == 'order_view.php') ? 'active-sub' : '' ?>">
                        <i class="bi bi-dot"></i> Order History
                    </a>

                    <!--<a href="<?= $baseUrl ?>/ordering/manage_customers.php" class="nav-link py-1 sub-link <?= $currentPage == 'manage_customers.php' ? 'active-sub' : '' ?>">
                        <i class="bi bi-dot"></i> Customer Registry
                    </a>

                    <a href="<?= $baseUrl ?>/ordering/ordering_analytics.php" class="nav-link py-1 sub-link <?= $currentPage == 'ordering_analytics.php' ? 'active-sub' : '' ?>">
                        <i class="bi bi-dot"></i> Sales Analytics
                    </a>-->
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($_SESSION['role'] === 'Admin'): ?>
            <a href="<?= $baseUrl ?>/sales/sales_dashboard.php" class="nav-link <?= $activeModule === 'sales' ? 'active' : '' ?>">
                <i class="bi bi-graph-up-arrow me-2 fs-5 align-middle"></i> <span>Sales</span>
            </a>
        <?php endif; ?>

        <?php if ($_SESSION['role'] === 'Admin'): ?>
            <div class="section-header border-top border-secondary border-opacity-25 pt-3 mt-3">System & Security</div>
            <a href="<?= $baseUrl ?>/settings/activity_log.php" class="nav-link <?= $activeModule === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-shield-check me-2 fs-5 align-middle"></i> <span>Activity log</span>
            </a>
        <?php endif; ?>

        <?php if ($_SESSION['role'] !== 'Admin'): ?>
            <div class="section-header border-top border-secondary border-opacity-25 pt-3 mt-3">My Requests</div>

            <a href="<?= $baseUrl ?>/payroll/my_payslips.php" class="nav-link <?= $currentPage === 'my_payslips.php' ? 'active' : '' ?>">
                <i class="bi bi-file-earmark-medical me-2 fs-5 align-middle"></i> <span>My Payslips</span>
            </a>

            <a href="<?= $baseUrl ?>/requests/cash_advance.php" class="nav-link <?= ($currentPage === 'cash_advance.php' || $activeModule === 'requests') ? 'active' : '' ?>">
                <i class="bi bi-cash-stack me-2 fs-5 align-middle"></i> <span>Request Cash Advance</span>
            </a>

            <a href="<?= $baseUrl ?>/settings/activity_log.php" class="nav-link <?= $activeModule === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-card-checklist me-2 fs-5 align-middle"></i> <span>My Activity Log</span>
            </a>
        <?php endif; ?>

    </div>

    <div class="mt-auto pt-3 border-top border-secondary border-opacity-25">
        <form action="/kakai1/backend/auth/logout.php" method="POST">
            <button class="btn btn-outline-light w-100 btn-sm py-2 text-start px-3 rounded-3" style="transition: all 0.2s;">
                <i class="bi bi-box-arrow-right me-2 text-danger"></i> Logout
            </button>
        </form>
        <p class="text-center text-secondary small mt-3 mb-0" style="font-size: 0.75rem;">
            &copy; <?= date('Y') ?> KakaiOne System
        </p>
    </div>
</nav>