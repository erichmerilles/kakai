<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$activeModule = 'inventory';
if (!isset($_SESSION['user_id'])) {
  header('Location: ../../index.php');
  exit;
}

requirePermission('payroll_analytics'); // Or relevant permission for analytics

// 1. Fetch Movement Distribution (IN vs OUT)
$distData = ['IN' => 0, 'OUT' => 0];
try {
  $stmt = $pdo->query("SELECT type, SUM(quantity) as total FROM inventory_movements GROUP BY type");
  while ($row = $stmt->fetch()) {
    $distData[$row['type']] = (int)$row['total'];
  }
} catch (PDOException $e) {
}

// 2. Fetch Monthly Trends (Last 6 Months)
$months = [];
$monthlyIn = [];
$monthlyOut = [];
try {
  $stmt = $pdo->query("
        SELECT 
            DATE_FORMAT(created_at, '%b %Y') as month_label,
            SUM(CASE WHEN type = 'IN' THEN quantity ELSE 0 END) as total_in,
            SUM(CASE WHEN type = 'OUT' THEN quantity ELSE 0 END) as total_out
        FROM inventory_movements 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
        GROUP BY month_label 
        ORDER BY MIN(created_at) ASC
    ");
  while ($row = $stmt->fetch()) {
    $months[] = $row['month_label'];
    $monthlyIn[] = (int)$row['total_in'];
    $monthlyOut[] = (int)$row['total_out'];
  }
} catch (PDOException $e) {
}

// 3. Top 5 Moving Items
$topItems = [];
$topQtys = [];
try {
  $stmt = $pdo->query("
        SELECT i.item_name, SUM(m.quantity) as moved
        FROM inventory_movements m
        JOIN inventory i ON m.item_id = i.item_id
        GROUP BY m.item_id
        ORDER BY moved DESC
        LIMIT 5
    ");
  while ($row = $stmt->fetch()) {
    $topItems[] = $row['item_name'];
    $topQtys[] = (int)$row['moved'];
  }
} catch (PDOException $e) {
}

// 4. Value Distribution by Category
$catLabels = [];
$catValues = [];
try {
  $stmt = $pdo->query("
        SELECT c.category_name, SUM(i.quantity * i.unit_price) as total_val
        FROM inventory i
        LEFT JOIN categories c ON i.category_id = c.category_id
        GROUP BY c.category_id
        HAVING total_val > 0
    ");
  while ($row = $stmt->fetch()) {
    $catLabels[] = $row['category_name'] ?? 'Uncategorized';
    $catValues[] = (float)$row['total_val'];
  }
} catch (PDOException $e) {
}

// 5. NEW: Inventory Forecasting (Average Daily Burn Rate)
$forecastReport = [];
try {
  $stmt = $pdo->query("
        SELECT 
            i.item_id, 
            i.item_name, 
            i.quantity as current_stock,
            COALESCE(usage_data.avg_daily_out, 0) as avg_daily_out
        FROM inventory i
        LEFT JOIN (
            SELECT 
                item_id, 
                SUM(quantity) / 90.0 as avg_daily_out
            FROM inventory_movements 
            WHERE type = 'OUT' AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
            GROUP BY item_id
        ) usage_data ON i.item_id = usage_data.item_id
        WHERE i.status != 'Out of Stock'
        ORDER BY (i.quantity / NULLIF(usage_data.avg_daily_out, 0)) ASC
        LIMIT 5
    ");
  $forecastReport = $stmt->fetchAll();
} catch (PDOException $e) {
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <link rel="icon" type="image/png" href="../assets/images/logo.png">
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventory Analytics | KakaiOne</title>
  <?php include '../includes/links.php'; ?>
  <style>
    .chart-container {
      position: relative;
      height: 300px;
      width: 100%;
    }

    .forecast-card {
      border-top: 4px solid #ffc107;
    }
  </style>
</head>

<body class="bg-light">
  <?php include '../includes/sidebar.php'; ?>

  <div id="dashboardContainer">
    <main id="main-content" style="margin-left: 250px; padding: 25px; transition: margin-left 0.3s;">
      <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
          <h3 class="fw-bold">
            <i class="bi bi-bar-chart-fill me-2 text-warning"></i>Inventory Analytics & Forecasting
          </h3>
          <a href="inventory_overview.php" class="btn btn-secondary shadow-sm">
            <i class="bi bi-arrow-left"></i> Back
          </a>
        </div>

        <div class="row mb-4">
          <div class="col-12">
            <div class="card shadow-sm border-0 forecast-card">
              <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold text-dark"><i class="bi bi-magic me-2 text-warning"></i>Stock Depletion Forecast (Next 30 Days)</h6>
                <span class="badge bg-light text-dark border small">Based on 90-day Burn Rate</span>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table align-middle mb-0">
                    <thead class="table-light">
                      <tr>
                        <th>Product Name</th>
                        <th>Current Stock</th>
                        <th>Avg Daily Sales</th>
                        <th>Est. Days Left</th>
                        <th>Recommended Action</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (!empty($forecastReport)): ?>
                        <?php foreach ($forecastReport as $row):
                          $daysLeft = ($row['avg_daily_out'] > 0) ? floor($row['current_stock'] / $row['avg_daily_out']) : '∞';
                          $statusClass = ($daysLeft !== '∞' && $daysLeft < 7) ? 'text-danger fw-bold' : 'text-dark';
                        ?>
                          <tr>
                            <td class="fw-bold"><?= htmlspecialchars($row['item_name']) ?></td>
                            <td><?= number_format($row['current_stock']) ?> units</td>
                            <td><?= number_format($row['avg_daily_out'], 2) ?> /day</td>
                            <td class="<?= $statusClass ?>">
                              <?= ($daysLeft === '∞') ? 'Stable' : $daysLeft . ' Days' ?>
                            </td>
                            <td>
                              <?php if ($daysLeft !== '∞' && $daysLeft < 7): ?>
                                <span class="badge bg-danger">Critical: Reorder Now</span>
                              <?php elseif ($daysLeft !== '∞' && $daysLeft < 15): ?>
                                <span class="badge bg-warning text-dark">Restock Suggested</span>
                              <?php else: ?>
                                <span class="badge bg-success">Maintain Level</span>
                              <?php endif; ?>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <tr>
                          <td colspan="5" class="text-center py-3 text-muted">Not enough transaction history to generate forecast.</td>
                        </tr>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-4 mb-4">
          <div class="col-lg-4">
            <div class="card shadow-sm border-0 h-100">
              <div class="card-header bg-dark text-white fw-bold py-3"><i class="bi bi-pie-chart me-2"></i>Stock Movement Ratio</div>
              <div class="card-body">
                <div class="chart-container">
                  <canvas id="distChart"></canvas>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-8">
            <div class="card shadow-sm border-0 h-100">
              <div class="card-header bg-dark text-white fw-bold py-3"><i class="bi bi-graph-up me-2"></i>Stock Volume Trends</div>
              <div class="card-body">
                <div class="chart-container">
                  <canvas id="trendChart"></canvas>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="row g-4">
          <div class="col-lg-6">
            <div class="card shadow-sm border-0">
              <div class="card-header bg-dark text-white fw-bold py-3"><i class="bi bi-fire me-2"></i>Top Moving Products</div>
              <div class="card-body">
                <div class="chart-container">
                  <canvas id="topItemsChart"></canvas>
                </div>
              </div>
            </div>
          </div>

          <div class="col-lg-6">
            <div class="card shadow-sm border-0">
              <div class="card-header bg-dark text-white fw-bold py-3"><i class="bi bi-wallet2 me-2"></i>Inventory Value by Category</div>
              <div class="card-body">
                <div class="chart-container">
                  <canvas id="valueChart"></canvas>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    const chartOptions = {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: 'bottom'
        }
      }
    };

    // 1. Movement Distribution
    new Chart(document.getElementById('distChart'), {
      type: 'doughnut',
      data: {
        labels: ['Stock In', 'Stock Out'],
        datasets: [{
          data: [<?= $distData['IN'] ?>, <?= $distData['OUT'] ?>],
          backgroundColor: ['#198754', '#ffc107'],
          borderWidth: 0
        }]
      },
      options: chartOptions
    });

    // 2. Monthly Trend Chart
    new Chart(document.getElementById('trendChart'), {
      type: 'bar',
      data: {
        labels: <?= json_encode($months) ?>,
        datasets: [{
            label: 'Stock In',
            data: <?= json_encode($monthlyIn) ?>,
            backgroundColor: '#198754'
          },
          {
            label: 'Stock Out',
            data: <?= json_encode($monthlyOut) ?>,
            backgroundColor: '#ffc107'
          }
        ]
      },
      options: chartOptions
    });

    // 3. Top Items Chart (Horizontal Bar)
    new Chart(document.getElementById('topItemsChart'), {
      type: 'bar',
      data: {
        labels: <?= json_encode($topItems) ?>,
        datasets: [{
          label: 'Units Moved',
          data: <?= json_encode($topQtys) ?>,
          backgroundColor: '#0d6efd'
        }]
      },
      options: {
        indexAxis: 'y',
        ...chartOptions
      }
    });

    // 4. Value Distribution Chart
    new Chart(document.getElementById('valueChart'), {
      type: 'pie',
      data: {
        labels: <?= json_encode($catLabels) ?>,
        datasets: [{
          data: <?= json_encode($catValues) ?>,
          backgroundColor: ['#0d6efd', '#6610f2', '#d63384', '#fd7e14', '#20c997']
        }]
      },
      options: chartOptions
    });
  </script>
</body>

</html>