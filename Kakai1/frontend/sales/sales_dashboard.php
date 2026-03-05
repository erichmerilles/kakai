<?php
session_start();
require_once __DIR__ . '/../../config/db.php';

// set active module
$activeModule = 'sales';

// role validation
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'Admin') {
    header('Location: ../../index.php');
    exit;
}

$username = $_SESSION['username'];
$role = $_SESSION['role'];

// greeting based on time
$hour = date('H');
$greeting = ($hour < 12) ? 'Good morning' : (($hour < 18) ? 'Good afternoon' : 'Good evening');

// fetch sales dashboard stats
$salesStats = [
    'today_sales' => 0.00,
    'total_revenue' => 0.00,
    'total_orders' => 0,
    'pending_orders' => 0
];

if (isset($conn)) {
    // today's sales
    $res = $conn->query("SELECT SUM(total_amount) as total FROM sales WHERE DATE(sale_date) = CURDATE()");
    if ($res && $row = $res->fetch_assoc()) $salesStats['today_sales'] = $row['total'] ?? 0;

    // total revenue all-time
    $res = $conn->query("SELECT SUM(total_amount) as total FROM sales");
    if ($res && $row = $res->fetch_assoc()) $salesStats['total_revenue'] = $row['total'] ?? 0;

    // total orders
    $res = $conn->query("SELECT COUNT(*) as count FROM orders");
    if ($res && $row = $res->fetch_assoc()) $salesStats['total_orders'] = $row['count'] ?? 0;

    // pending orders
    $res = $conn->query("SELECT COUNT(*) as count FROM orders WHERE status = 'Pending'");
    if ($res && $row = $res->fetch_assoc()) $salesStats['pending_orders'] = $row['count'] ?? 0;
}

// recent sales transactions
$recentSales = [];
if (isset($conn)) {
    $salesQuery = $conn->query("
        SELECT s.sale_id, s.sale_date, s.total_amount, s.payment_method, o.status 
        FROM sales s
        LEFT JOIN orders o ON s.order_id = o.order_id
        ORDER BY s.sale_date DESC 
        LIMIT 10
    ");
    $recentSales = $salesQuery ? $salesQuery->fetch_all(MYSQLI_ASSOC) : [];
}

// top selling products
$topProducts = [];
if (isset($conn)) {
    $topProductsQuery = $conn->query("
        SELECT p.product_name, SUM(si.quantity) as total_sold 
        FROM sale_items si
        JOIN products p ON si.product_id = p.product_id
        GROUP BY si.product_id
        ORDER BY total_sold DESC
        LIMIT 5
    ");
    $topProducts = $topProductsQuery ? $topProductsQuery->fetch_all(MYSQLI_ASSOC) : [];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <link rel="icon" type="image/png" href="../assets/images/logo.png">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KakaiOne | Sales Dashboard</title>
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

        .border-left-info {
            border-left-color: #0dcaf0 !important;
        }

        .module-card {
            border-radius: 8px;
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            background: #fff;
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
                        <h3 class="fw-bold text-dark mb-1">Sales Overview</h3>
                        <p class="text-muted mb-0"><?= $greeting ?>, <strong><?= htmlspecialchars($username) ?></strong>. Here is your sales performance.</p>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-dark px-3 py-2 fs-6 shadow-sm" id="liveClock">
                            <i class="bi bi-clock"></i> Loading time...
                        </span>
                        <div class="small text-muted mt-1 fw-bold"><?= date('l, F j, Y') ?></div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card border-0 shadow-sm border-left-success h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs fw-bold text-success text-uppercase mb-1">Today's Sales</div>
                                        <div class="h4 mb-0 fw-bold text-dark">₱<?= number_format($salesStats['today_sales'], 2); ?></div>
                                    </div>
                                    <div class="col-auto"><i class="bi bi-cash-stack fa-2x text-success opacity-50 fs-1"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card border-0 shadow-sm border-left-primary h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs fw-bold text-primary text-uppercase mb-1">Total Revenue</div>
                                        <div class="h4 mb-0 fw-bold text-dark">₱<?= number_format($salesStats['total_revenue'], 2); ?></div>
                                    </div>
                                    <div class="col-auto"><i class="bi bi-wallet2 fa-2x text-primary opacity-50 fs-1"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card border-0 shadow-sm border-left-info h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs fw-bold text-info text-uppercase mb-1">Total Orders</div>
                                        <div class="h4 mb-0 fw-bold text-dark"><?= number_format($salesStats['total_orders']); ?></div>
                                    </div>
                                    <div class="col-auto"><i class="bi bi-cart-check fa-2x text-info opacity-50 fs-1"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-xl-3 col-md-6 mb-3">
                        <div class="card stat-card border-0 shadow-sm border-left-warning h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs fw-bold text-warning text-uppercase mb-1">Pending Orders</div>
                                        <div class="h4 mb-0 fw-bold text-dark"><?= number_format($salesStats['pending_orders']); ?></div>
                                    </div>
                                    <div class="col-auto"><i class="bi bi-hourglass-split fa-2x text-warning opacity-50 fs-1"></i></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-8">

                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-receipt me-2"></i>Recent Sales Transactions</span>
                                <div class="input-group input-group-sm w-50">
                                    <input type="text" id="salesSearch" class="form-control" placeholder="Search transactions...">
                                    <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <div class="table-responsive">
                                    <table class="table table-striped align-middle mb-0" id="salesTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="ps-3"><i class="bi bi-hash"></i> Sale ID</th>
                                                <th><i class="bi bi-calendar-event"></i> Date</th>
                                                <th><i class="bi bi-credit-card"></i> Payment Method</th>
                                                <th><i class="bi bi-info-circle"></i> Status</th>
                                                <th class="pe-3 text-end"><i class="bi bi-currency-dollar"></i> Amount</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (!empty($recentSales)): ?>
                                                <?php foreach ($recentSales as $sale): ?>
                                                    <tr>
                                                        <td class="ps-3 fw-bold text-dark">SL-<?= str_pad($sale['sale_id'], 5, '0', STR_PAD_LEFT); ?></td>
                                                        <td class="text-muted small"><?= date('M d, Y h:i A', strtotime($sale['sale_date'])); ?></td>
                                                        <td><?= htmlspecialchars($sale['payment_method'] ?? 'Cash'); ?></td>
                                                        <td>
                                                            <?php
                                                            $statusStr = $sale['status'] ?? 'Completed';
                                                            $badgeClass = ($statusStr === 'Pending') ? 'warning' : (($statusStr === 'Delivered' || $statusStr === 'Completed') ? 'success' : 'primary');
                                                            ?>
                                                            <span class="badge bg-<?= $badgeClass; ?>"><?= htmlspecialchars($statusStr); ?></span>
                                                        </td>
                                                        <td class="text-end pe-3 fw-bold text-success">₱<?= number_format($sale['total_amount'], 2); ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">
                                                        No recent sales found.
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="col-lg-4 mt-4 mt-lg-0">
                        <div class="card mb-4 shadow-sm">
                            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                                <span><i class="bi bi-graph-up-arrow me-2 text-info"></i>Top Selling Products</span>
                            </div>
                            <div class="card-body p-0">
                                <ul class="list-group list-group-flush">
                                    <?php if (!empty($topProducts)): ?>
                                        <?php foreach ($topProducts as $index => $product): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-start py-3">
                                                <div class="ms-2 me-auto">
                                                    <div class="fw-bold text-dark">
                                                        <span class="badge bg-secondary me-2">#<?= $index + 1 ?></span>
                                                        <?= htmlspecialchars($product['product_name']); ?>
                                                    </div>
                                                </div>
                                                <span class="badge bg-success rounded-pill"><?= htmlspecialchars($product['total_sold']); ?> sold</span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <li class="list-group-item text-center py-4 text-muted">Not enough data to display top products.</li>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </main>
    </div>

    <script>
        // clock script
        function updateClock() {
            const now = new Date();
            let hours = now.getHours();
            let minutes = now.getMinutes();
            let seconds = now.getSeconds();
            const ampm = hours >= 12 ? 'PM' : 'AM';

            hours = hours % 12;
            hours = hours ? hours : 12;
            minutes = minutes < 10 ? '0' + minutes : minutes;
            seconds = seconds < 10 ? '0' + seconds : seconds;

            const strTime = hours + ':' + minutes + ':' + seconds + ' ' + ampm;
            document.getElementById('liveClock').innerHTML = '<i class="bi bi-clock me-1"></i> ' + strTime;
        }
        setInterval(updateClock, 1000);
        updateClock(); // initial call

        // sales search filter
        document.getElementById('salesSearch').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#salesTable tbody tr');

            rows.forEach(row => {
                if (row.cells.length === 1) return;
                let text = row.innerText.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        });
    </script>
</body>

</html>