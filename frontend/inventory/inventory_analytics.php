<?php
session_start();
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../includes/auth_check.php';

$activeModule = 'inventory';
if (!isset($_SESSION['user_id'])) {
  header('Location: ../../index.php');
  exit;
}

// fetch summary data
$chartData = ['Stock In' => 0, 'Stock Out' => 0];
try {
  $stmt = $pdo->query("SELECT type, COUNT(*) as count FROM inventory_movements GROUP BY type");
  while ($row = $stmt->fetch()) {
    $chartData[$row['type']] = $row['count'];
  }
} catch (PDOException $e) {
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Inventory Analytics | KakaiOne</title>
  <?php include '../includes/links.php'; ?>
</head>

<body class="bg-light">

  <?php include '../includes/sidebar.php'; ?>

  <div id="dashboardContainer">
    <main id="main-content" style="margin-left: 250px; padding: 25px; transition: margin-left 0.3s;">
      <div class="container">

        <div class="d-flex justify-content-between align-items-center mb-4">
          <h3 class="fw-bold">
            <i class="bi bi-bar-chart-fill me-2 text-warning"></i>Inventory Analytics
          </h3>
          <a href="inventory_overview.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left"></i> Back
          </a>
        </div>

        <div class="row g-4">
          <div class="col-md-6">
            <div class="card shadow-sm h-100">
              <div class="card-header bg-dark text-white">
                <i class="bi bi-pie-chart me-2"></i>Movement Distribution
              </div>
              <div class="card-body">
                <canvas id="movementChart"></canvas>
              </div>
            </div>
          </div>

          <div class="col-md-6">
            <div class="card shadow-sm h-100">
              <div class="card-header bg-dark text-white">
                <i class="bi bi-graph-up-arrow me-2"></i>Monthly Trends
              </div>
              <div class="card-body d-flex align-items-center justify-content-center text-muted">
                <div>
                  <i class="bi bi-bar-chart-steps display-4"></i>
                  <p class="mt-2">Trend data coming soon...</p>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </main>
  </div>

  <script>
    // initialize movement chart
    const ctx = document.getElementById('movementChart').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: ['Stock In', 'Stock Out'],
        datasets: [{
          data: [<?= $chartData['Stock In'] ?? 0 ?>, <?= $chartData['Stock Out'] ?? 0 ?>],
          backgroundColor: ['#198754', '#ffc107'],
          hoverOffset: 4
        }]
      },
      options: {
        responsive: true,
        plugins: {
          legend: {
            position: 'bottom'
          }
        }
      }
    });
  </script>
</body>

</html>