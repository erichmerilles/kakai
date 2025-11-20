<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// Only Employees can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Employee') {
    header('Location: ../../index.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KakaiOne | Employee Dashboard</title>
    <?php include '../includes/links.php'; ?>
</head>
<body class="bg-light">

<div id="dashboardContainer" class="d-flex">
    
    <?php include '../employee/e_sidebar.php'; ?>

    <main id="main-content" class="flex-grow-1 p-4 position-relative">
        
        <div class="profile-badge">
            <img src="../assets/images/logo.jpg" width="40" height="40" class="rounded-circle border">
            <div>
                <div class="fw-bold small"><?= htmlspecialchars($_SESSION['username']); ?></div>
                <div class="text-muted small" style="font-size: 0.75rem;">
                    <span id="statusDot" class="status-indicator status-off"></span> 
                    <span id="statusText">Checking...</span>
                </div>
            </div>
        </div>

        <h3 class="fw-bold text-dark mb-4">Dashboard</h3>

        <div class="card border-0 shadow-sm mb-4 attendance-hero">
            <div class="card-body p-4 text-white d-flex justify-content-between align-items-center flex-wrap gap-3">
                <div>
                    <h4 class="mb-1" id="greeting">Hello, <?= htmlspecialchars($_SESSION['username']); ?>!</h4>
                    <p class="mb-0 opacity-75">
                        <i class="bi bi-wifi me-1"></i> Ensure you are connected to <strong>Store Wi-Fi</strong> to clock in.
                    </p>
                </div>
                <div id="actionButtons">
                    <button class="btn btn-light btn-lg fw-bold text-dark px-5 shadow disabled">
                        Loading...
                    </button>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stats-card">
                    <div class="text-secondary small">Days Present</div>
                    <h2 class="fw-bold text-success mb-0" id="statPresent">0</h2>
                    <small class="text-muted">This Month</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="border-color: #dc3545;">
                    <div class="text-secondary small">Absences</div>
                    <h2 class="fw-bold text-danger mb-0" id="statAbsent">0</h2>
                    <small class="text-muted">This Month</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="border-color: #0d6efd;">
                    <div class="text-secondary small">Hours Worked</div>
                    <h2 class="fw-bold text-primary mb-0" id="statHours">0</h2>
                    <small class="text-muted">This Month</small>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stats-card" style="border-color: #ffc107;">
                    <div class="text-secondary small">Avg Clock In</div>
                    <h2 class="fw-bold text-warning mb-0" id="statAvg">--:--</h2>
                    <small class="text-muted">Time</small>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-white fw-bold border-0 pt-3">
                        <i class="bi bi-calendar-week me-2"></i> Attendance Calendar
                    </div>
                    <div class="card-body">
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 mb-3">
                    <div class="card-body">
                        <h6 class="fw-bold mb-3">Quick Actions</h6>
                        <button class="btn btn-outline-primary w-100 mb-2 text-start" onclick="window.location.href='../requests/leave_requests.php'">
                            <i class="bi bi-envelope-paper me-2"></i> Apply for Leave
                        </button>
                        <button class="btn btn-outline-secondary w-100 text-start">
                            <i class="bi bi-file-earmark-text me-2"></i> View Payslip
                        </button>
                    </div>
                </div>

                <div class="card shadow-sm border-0">
                    <div class="card-header bg-white fw-bold border-0">
                        Activity Log
                    </div>
                    <ul class="list-group list-group-flush small" id="activityLog">
                        <li class="list-group-item text-muted">Loading...</li>
                    </ul>
                </div>
            </div>
        </div>

    </main>
</div>

<div class="modal fade" id="clockOutModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-warning border-0">
                <h5 class="modal-title fw-bold text-dark"><i class="bi bi-exclamation-triangle"></i> Early Clock Out?</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>It is currently before the end of your shift. Please select a reason:</p>
                <div class="d-grid gap-2">
                    <button class="btn btn-outline-dark p-3 text-start" onclick="confirmClockOut('Break')">
                        <strong>Break</strong> <br><small>I will return later today.</small>
                    </button>
                    <button class="btn btn-outline-danger p-3 text-start" onclick="confirmClockOut('Emergency')">
                        <strong>Emergency</strong> <br><small>Requires HR Review.</small>
                    </button>
                    <button class="btn btn-outline-secondary p-3 text-start" onclick="confirmClockOut('Half-day')">
                        <strong>Half-day</strong> <br><small>Permanent clock out for the day.</small>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // INITIALIZATION
    document.addEventListener('DOMContentLoaded', function() {
        loadDashboardData();
        // LIVE UPDATE: every 3 seconds to check for Admin Approval
        setInterval(loadDashboardData, 3000);
    });

    // DATA LOADING
    async function loadDashboardData() {
        try {
            const res = await fetch('../../backend/employee/get_dashboard.php');
            const data = await res.json();

            if(data.success) {
                // Update Stats
                document.getElementById('statPresent').innerText = data.stats.present;
                document.getElementById('statAbsent').innerText = data.stats.absent;
                document.getElementById('statHours').innerText = data.stats.hours;
                document.getElementById('statAvg').innerText = data.stats.avg_in;

                // Update Profile Status
                updateStatusUI(data.profile.status);
                
                // Update Log
                document.getElementById('activityLog').innerHTML = `
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        Last Activity <span>${data.profile.last_log}</span>
                    </li>`;

                // Init Calendar
                var calendarEl = document.getElementById('calendar');
                if(calendarEl.innerHTML === "") {
                    var calendar = new FullCalendar.Calendar(calendarEl, {
                        initialView: 'dayGridMonth',
                        height: 350,
                        events: data.calendar,
                        headerToolbar: { left: 'title', center: '', right: 'prev,next' }
                    });
                    calendar.render();
                }
            }
        } catch (error) {
            console.error('Failed to load dashboard data:', error);
        }
    }

    // UI MANAGEMENT
    function updateStatusUI(status) {
        const btnArea = document.getElementById('actionButtons');
        const statusText = document.getElementById('statusText');
        const statusDot = document.getElementById('statusDot');

        // Don't refresh HTML if status hasn't changed
        if (statusText.innerText === status) return;

        statusText.innerText = status;
        statusDot.className = 'status-indicator';

        // Check for 'Approved' or 'Present'
        if (status === 'Pending Approval' || status === 'Approved' || status === 'Present') {
            statusDot.classList.add('status-on');
            // Show Clock Out Button
            btnArea.innerHTML = `
                <button onclick="tryClockOut()" class="btn btn-danger btn-lg fw-bold px-5 shadow">
                    <i class="bi bi-box-arrow-right me-2"></i> Clock Out
                </button>`;
        } 
        else if (status === 'On Break') {
            statusDot.classList.add('status-pending');
            // Show End Break Button
            btnArea.innerHTML = `
                <button onclick="performAttendanceAction('clock_out', 'Return_Break')" class="btn btn-info btn-lg fw-bold px-5 text-white shadow">
                    <i class="bi bi-arrow-return-left me-2"></i> End Break
                </button>`;
        }
        else {
            // Off Duty or Clocked Out
            statusDot.classList.add('status-off');
            // Show Clock In Button
            btnArea.innerHTML = `
                <button onclick="tryClockIn()" class="btn btn-light btn-lg fw-bold text-dark px-5 shadow">
                    <i class="bi bi-fingerprint me-2"></i> Clock In
                </button>`;
        }
    }

    // CLOCK IN
    function tryClockIn() {
        Swal.fire({
            title: 'Verifying Network...',
            text: 'Checking if you are connected to the Store Wi-Fi.',
            allowOutsideClick: false,
            didOpen: () => Swal.showLoading()
        });

        performAttendanceAction('clock_in');
    }

    // CLOCK OUT
    function tryClockOut() {
        const now = new Date();
        const day = now.getDay();
        const hour = now.getHours();
        const endHour = (day === 0) ? 16 : 17; 
        
        if (hour < endHour) {
            const modal = new bootstrap.Modal(document.getElementById('clockOutModal'));
            modal.show();
        } else {
            Swal.fire({
                title: 'End Shift?',
                text: 'Confirm clock out for the day.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                confirmButtonText: 'Yes, Clock Out'
            }).then((result) => {
                if (result.isConfirmed) {
                    performAttendanceAction('clock_out', 'Standard');
                }
            });
        }
    }

    // Helper to handle modal
    function confirmClockOut(reason) {
        const modalEl = document.getElementById('clockOutModal');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal.hide();
        
        performAttendanceAction('clock_out', reason);
    }

    // API HANDLER
    async function performAttendanceAction(action, reason = null) {
        const fd = new FormData();
        fd.append('action', action);
        if (reason) fd.append('reason', reason);

        try {
            const res = await fetch('../../backend/attendance/attendance_action.php', {
                method: 'POST',
                body: fd
            });
            const data = await res.json();

            if (data.success) {
                Swal.fire({
                    title: 'Success',
                    text: data.message,
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => {
                    loadDashboardData();
                });
            } else {
                Swal.fire({
                    title: 'Action Failed',
                    text: data.message,
                    icon: 'error'
                });
            }
        } catch (e) {
            Swal.fire('System Error', 'Could not connect to the server.', 'error');
            console.error(e);
        }
    }
</script>

</body>
</html>