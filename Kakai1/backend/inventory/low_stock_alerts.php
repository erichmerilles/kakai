<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

try {
  $stmt = $pdo->query('SELECT * FROM inventory WHERE quantity <= reorder_level ORDER BY quantity ASC');
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['success'=>true,'data'=>$rows]);
} catch (Exception $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
