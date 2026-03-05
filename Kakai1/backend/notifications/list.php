<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');
try {
  $stmt = $pdo->query('SELECT * FROM notifications ORDER BY created_at DESC LIMIT 100');
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['success'=>true,'data'=>$rows]);
} catch (Exception $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
