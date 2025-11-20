<?php
require_once __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$item_id = intval($_GET['item_id'] ?? 0);
if (!$item_id) { echo json_encode(['success'=>false,'message'=>'Missing item_id']); exit; }

try {
  $stmt = $pdo->prepare('DELETE FROM inventory WHERE item_id = ?');
  $stmt->execute([$item_id]);
  echo json_encode(['success'=>true,'message'=>'Item deleted']);
} catch (Exception $e) {
  echo json_encode(['success'=>false,'message'=>$e->getMessage()]);
}
?>
