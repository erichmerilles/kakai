<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$item_id = intval($_GET['item_id'] ?? 0);

try {
  // 1) collect out-usage from inventory_movements (last 12 months)
  $params = [];
  $sql = "SELECT im.item_id, DATE_FORMAT(im.created_at, '%Y-%m') AS ym, SUM(im.quantity) AS used
          FROM inventory_movements im
          WHERE im.type = 'OUT'";
  if ($item_id) { $sql .= ' AND im.item_id = ?'; $params[] = $item_id; }
  $sql .= ' GROUP BY im.item_id, ym ORDER BY ym DESC LIMIT 12';

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $by_item = [];
  foreach ($rows as $r) {
    $id = $r['item_id'];
    $by_item[$id][$r['ym']] = intval($r['used']);
  }

  $result = [];
  foreach ($by_item as $id => $months) {
    $vals = array_values($months);
    $avg = count($vals) ? array_sum($vals)/count($vals) : 0;
    $result[$id] = [
      'past_months' => $months,
      'avg_monthly_out' => round($avg,2),
      'forecast_next_3_months' => [round($avg,2), round($avg,2), round($avg,2)]
    ];
  }

  // 2) If no movement data, fallback: try matching inventory.item_name -> products -> sale_items
  if (empty($result)) {
    $stmt2 = $pdo->query("SELECT i.item_id, i.item_name FROM inventory i");
    $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    foreach ($items as $it) {
      $p = $pdo->prepare('SELECT product_id FROM products WHERE product_name = ? LIMIT 1');
      $p->execute([$it['item_name']]);
      $prod = $p->fetch(PDO::FETCH_ASSOC);
      if ($prod) {
        $ps = $pdo->prepare("SELECT DATE_FORMAT(s.sale_date, '%Y-%m') AS ym, SUM(si.quantity) AS used
                             FROM sale_items si
                             JOIN sales s ON si.sale_id = s.sale_id
                             WHERE si.product_id = ?
                             GROUP BY ym ORDER BY ym DESC LIMIT 12");
        $ps->execute([$prod['product_id']]);
        $rows = $ps->fetchAll(PDO::FETCH_ASSOC);
        $vals = array_map(fn($r)=>intval($r['used']), $rows);
        $avg = count($vals) ? array_sum($vals)/count($vals) : 0;
        $result[$it['item_id']] = [
          'past_months' => array_column($rows, 'used', 'ym'),
          'avg_monthly_out' => round($avg,2),
          'forecast_next_3_months' => [round($avg,2), round($avg,2), round($avg,2)]
        ];
      }
    }
  }

  echo json_encode(['success'=>true,'forecast'=>$result]);
} catch (Exception $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
