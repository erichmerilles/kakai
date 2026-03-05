<div id="sidebar" class="d-flex flex-column">
    <div class="text-center mb-4">
        <img src="../assets/images/logo.jpg" alt="KakaiOne Logo" width="80" height="80" style="border-radius: 50%; margin-bottom:10px;">
        <h5 class="fw-bold text-light">KakaiOne</h5>
        <p class="small">Employee Module</p>
    </div>

        <a href="../dashboard/admin_dashboard.php" class="nav-link"><i class="bi bi-speedometer2 me-2"></i>Dashboard</a>
        <a href="employee_module.php" class="nav-link active"><i class="bi bi-people-fill me-2"></i>Overview</a>
        <a href="../attendance/attendance_page.php" class="nav-link"><i class="bi bi-calendar-check me-2"></i>Attendance</a>
        <a href="../requests/leave_requests.php" class="nav-link"><i class="bi bi-envelope-paper me-2"></i>Leave Requests</a>
        <a href="../requests/cash_advance.php" class="nav-link"><i class="bi bi-cash-coin me-2"></i>Cash Advance</a>

        <div class="mt-auto">
            <form action="../../backend/auth/logout.php" method="POST">
                <button class="btn btn-outline-light w-100 btn-sm mt-3">
                    <i class="bi bi-box-arrow-right me-1"></i> Logout
                </button>
            </form>
            <p class="text-center text-secondary small mt-3">© 2025 KakaiOne</p>
        </div>
</div>